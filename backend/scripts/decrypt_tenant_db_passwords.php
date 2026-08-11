<?php
/**
 * EducaTudo - Reversão da migração: volta senhas de banco dos tenants para TEXTO PURO.
 *
 * Use isto para RECUPERAR o acesso quando o app web não consegue decifrar
 * (erro "Access denied ... using password: NO"), OU antes de re-cifrar com a
 * chave correta.
 *
 * IMPORTANTE: rode no MESMO ambiente/.env onde você rodou o encrypt_tenant_db_passwords.php,
 * porque só lá a MASTER_ENCRYPTION_KEY consegue decifrar os valores.
 *
 * É IDEMPOTENTE: valores já em texto puro (sem "enc:v1:") são pulados.
 * Valores que NÃO decifram (chave errada) são reportados e NÃO são tocados.
 *
 * USO:
 *   Diagnóstico (não grava; mostra quais decifram com a chave atual):
 *     php scripts/decrypt_tenant_db_passwords.php --dry-run
 *   Reverter para texto puro:
 *     php scripts/decrypt_tenant_db_passwords.php --apply
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via linha de comando (CLI).\n");
}

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$apply = in_array('--apply', $args, true);

if (!$dryRun && !$apply) {
    fwrite(STDERR, "Escolha um modo:\n"
        . "  --dry-run   mostra quais senhas decifram com a chave atual, sem gravar\n"
        . "  --apply     grava as senhas decifradas (texto puro) no banco master\n");
    exit(2);
}
if ($dryRun && $apply) {
    fwrite(STDERR, "Use apenas um: --dry-run OU --apply.\n");
    exit(2);
}

$basePath = dirname(__DIR__);
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Core/MasterSecretVault.php';

// Mostra (mascarada) a chave em uso, para você confirmar o ambiente.
try {
    $keyBin = MasterSecretVault::getKey();
    $fingerprint = substr(hash('sha256', $keyBin), 0, 12);
    echo "Fingerprint da MASTER_ENCRYPTION_KEY em uso: {$fingerprint}\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "ERRO: " . $e->getMessage() . "\n");
    exit(1);
}

$cfg = Database::getConfigFromEnv();
$port = !empty($cfg['port']) ? (int) $cfg['port'] : 3306;
$dsn = "mysql:host={$cfg['host']};port={$port};dbname={$cfg['name']};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, (string) $cfg['user'], (string) ($cfg['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "ERRO ao conectar no banco master: " . $e->getMessage() . "\n");
    exit(1);
}

echo "== Reversão de senhas de banco dos tenants (para texto puro) ==\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (nada será gravado)" : "APPLY (gravando)") . "\n";
echo "Banco master: {$cfg['name']} @ {$cfg['host']}:{$port}\n\n";

$rows = $pdo->query("SELECT id, escola_id, nome_banco, senha_criptografada FROM config_escolas_banco ORDER BY escola_id")->fetchAll(PDO::FETCH_ASSOC);

$total = count($rows);
$jaTexto = 0;
$revertidas = 0;
$falhas = 0;

$update = $pdo->prepare("UPDATE config_escolas_banco SET senha_criptografada = ? WHERE id = ?");

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $escolaId = (int) $row['escola_id'];
    $banco = (string) ($row['nome_banco'] ?? '');
    $senha = (string) ($row['senha_criptografada'] ?? '');
    $rotulo = "escola_id={$escolaId} (banco={$banco})";

    if (!MasterSecretVault::isEncryptedDbPassword($senha)) {
        $jaTexto++;
        echo "  - {$rotulo}: já em texto puro, ignorada\n";
        continue;
    }

    $plain = MasterSecretVault::decryptDbPassword($senha);
    if ($plain === '') {
        // Chave errada: decifragem falhou. NÃO grava (não perde o valor cifrado).
        $falhas++;
        fwrite(STDERR, "  ! {$rotulo}: NÃO decifrou com a chave atual (chave incorreta para este ambiente).\n");
        continue;
    }

    if ($dryRun) {
        echo "  * {$rotulo}: decifra OK (seria revertida para texto puro)\n";
        $revertidas++;
        continue;
    }

    try {
        $update->execute([$plain, $id]);
        $revertidas++;
        echo "  + {$rotulo}: revertida para texto puro\n";
    } catch (PDOException $e) {
        $falhas++;
        fwrite(STDERR, "  ! {$rotulo}: erro ao gravar - " . $e->getMessage() . "\n");
    }
}

echo "\n== Resumo ==\n";
echo "Total de registros: {$total}\n";
echo "Já em texto puro (puladas): {$jaTexto}\n";
echo ($dryRun ? "Decifram OK: " : "Revertidas: ") . "{$revertidas}\n";
echo "Falhas de decifragem (chave errada): {$falhas}\n";

if ($falhas > 0) {
    echo "\nATENÇÃO: houve falhas de decifragem — esta NÃO é a chave que cifrou os dados.\n";
    echo "Rode no ambiente/.env onde você executou o encrypt, ou restaure o backup.\n";
}
if ($dryRun) {
    echo "\nNada foi gravado (dry-run). Rode com --apply para reverter.\n";
}

exit($falhas > 0 ? 1 : 0);
