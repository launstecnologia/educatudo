<?php
/**
 * EducaTudo - Migração única: cifrar senhas de banco dos tenants
 *
 * Percorre config_escolas_banco (banco MASTER) e cifra as senhas que ainda
 * estão em texto puro, usando MasterSecretVault::encryptDbPassword().
 *
 * É IDEMPOTENTE: valores já cifrados (prefixo "enc:v1:") são pulados, então
 * rodar de novo não causa dano.
 *
 * PRÉ-REQUISITOS:
 *   - MASTER_ENCRYPTION_KEY definida no .env (a MESMA que a aplicação usa).
 *   - Backup da tabela config_escolas_banco feito ANTES de rodar.
 *
 * USO:
 *   Simular (não grava, só mostra o que faria):
 *     php scripts/encrypt_tenant_db_passwords.php --dry-run
 *   Aplicar de verdade:
 *     php scripts/encrypt_tenant_db_passwords.php --apply
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
        . "  --dry-run   mostra o que seria alterado, sem gravar\n"
        . "  --apply     aplica a cifragem no banco master\n");
    exit(2);
}
if ($dryRun && $apply) {
    fwrite(STDERR, "Use apenas um: --dry-run OU --apply.\n");
    exit(2);
}

$basePath = dirname(__DIR__);

// Carrega .env + helper env() (necessário para MasterSecretVault::getKey()).
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Core/MasterSecretVault.php';

// Garante que a chave de cifragem está configurada ANTES de tocar no banco.
try {
    MasterSecretVault::getKey();
} catch (\Throwable $e) {
    fwrite(STDERR, "ERRO: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Defina MASTER_ENCRYPTION_KEY no .env antes de rodar.\n");
    exit(1);
}

// Conecta ao banco MASTER (mesmas credenciais DB_* usadas pelo bootstrap).
$cfg = Database::getConfigFromEnv();
foreach (['host', 'name', 'user'] as $k) {
    if (empty($cfg[$k]) && $cfg[$k] !== '0') {
        fwrite(STDERR, "ERRO: configuração do banco master incompleta no .env (DB_HOST, DB_NAME, DB_USER, DB_PASS).\n");
        exit(1);
    }
}
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

echo "== Cifragem de senhas de banco dos tenants ==\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (nada será gravado)" : "APPLY (gravando)") . "\n";
echo "Banco master: {$cfg['name']} @ {$cfg['host']}:{$port}\n\n";

$rows = $pdo->query("SELECT id, escola_id, nome_banco, senha_criptografada FROM config_escolas_banco ORDER BY escola_id")->fetchAll(PDO::FETCH_ASSOC);

$total = count($rows);
$jaCifradas = 0;
$vazias = 0;
$cifradasAgora = 0;
$erros = 0;

$update = $pdo->prepare("UPDATE config_escolas_banco SET senha_criptografada = ? WHERE id = ?");

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $escolaId = (int) $row['escola_id'];
    $banco = (string) ($row['nome_banco'] ?? '');
    $senha = (string) ($row['senha_criptografada'] ?? '');
    $rotulo = "escola_id={$escolaId} (banco={$banco})";

    if ($senha === '') {
        $vazias++;
        echo "  - {$rotulo}: senha vazia, ignorada\n";
        continue;
    }
    if (MasterSecretVault::isEncryptedDbPassword($senha)) {
        $jaCifradas++;
        echo "  - {$rotulo}: já cifrada, ignorada\n";
        continue;
    }

    $cifrada = MasterSecretVault::encryptDbPassword($senha);
    if ($cifrada === null || !MasterSecretVault::isEncryptedDbPassword($cifrada)) {
        $erros++;
        fwrite(STDERR, "  ! {$rotulo}: FALHA ao cifrar (verifique OpenSSL / chave).\n");
        continue;
    }

    // Verificação de segurança: decifrar de volta deve devolver o texto original.
    if (MasterSecretVault::decryptDbPassword($cifrada) !== $senha) {
        $erros++;
        fwrite(STDERR, "  ! {$rotulo}: FALHA na verificação (decifragem não confere). NÃO gravado.\n");
        continue;
    }

    if ($dryRun) {
        echo "  * {$rotulo}: seria cifrada\n";
        $cifradasAgora++;
        continue;
    }

    try {
        $update->execute([$cifrada, $id]);
        $cifradasAgora++;
        echo "  + {$rotulo}: cifrada e gravada\n";
    } catch (PDOException $e) {
        $erros++;
        fwrite(STDERR, "  ! {$rotulo}: erro ao gravar - " . $e->getMessage() . "\n");
    }
}

echo "\n== Resumo ==\n";
echo "Total de registros: {$total}\n";
echo "Já cifradas (puladas): {$jaCifradas}\n";
echo "Senhas vazias (puladas): {$vazias}\n";
echo ($dryRun ? "Seriam cifradas: " : "Cifradas agora: ") . "{$cifradasAgora}\n";
echo "Erros: {$erros}\n";

if ($dryRun) {
    echo "\nNada foi gravado (dry-run). Rode com --apply para aplicar.\n";
}

exit($erros > 0 ? 1 : 0);
