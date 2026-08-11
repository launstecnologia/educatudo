<?php
/**
 * EducaTudo — matricula aluno de teste em todas as turmas ativas (visão completa).
 *
 * Uso:
 *   php scripts/liberar_aluno_teste.php --ra=c001
 *   php scripts/liberar_aluno_teste.php --email=joao.silva@escola.com
 *   php scripts/liberar_aluno_teste.php --escola-id=1 --ra=c001
 *   php scripts/liberar_aluno_teste.php --all-escolas --ra=c001
 *
 * Requer conexão com master (DB_* no .env) para resolver tenant(s).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via CLI.\n");
}

$args = array_slice($argv, 1);
$ra = null;
$email = null;
$escolaIdFilter = null;
$allEscolas = in_array('--all-escolas', $args, true);
$dryRun = in_array('--dry-run', $args, true);

foreach ($args as $arg) {
    if (preg_match('/^--ra=(.+)$/', $arg, $m)) {
        $ra = trim($m[1]);
    } elseif (preg_match('/^--email=(.+)$/', $arg, $m)) {
        $email = trim($m[1]);
    } elseif (preg_match('/^--escola-id=(\d+)$/', $arg, $m)) {
        $escolaIdFilter = (int) $m[1];
    }
}

if ($ra === null && $email === null) {
    exit("Informe --ra= ou --email=\n");
}

$basePath = dirname(__DIR__);
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Core/MasterSecretVault.php';

function connectTenantPdo(array $row): ?PDO
{
    $host = (string) ($row['host'] ?? '127.0.0.1');
    $port = (int) ($row['porta'] ?? 3306);
    $dbName = (string) ($row['nome_banco'] ?? '');
    $user = (string) ($row['usuario'] ?? '');
    try {
        $pass = MasterSecretVault::decryptDbPassword($row['senha_criptografada'] ?? '');
    } catch (Throwable $e) {
        echo "  ✗ senha não decifra: {$e->getMessage()}\n";
        return null;
    }
    try {
        return new PDO(
            "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        echo "  ✗ conexão falhou ({$dbName}): {$e->getMessage()}\n";
        return null;
    }
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 AS ok FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);
    return !empty($stmt->fetch()['ok']);
}

function findAluno(PDO $pdo, ?string $ra, ?string $email): ?array
{
    if ($ra !== null && $ra !== '') {
        $stmt = $pdo->prepare(
            "SELECT a.id, a.nome, a.ra, a.email, a.turma_id, a.ativo, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.ra = :ra OR a.codigo_aluno = :ra2
             LIMIT 1"
        );
        $stmt->execute(['ra' => $ra, 'ra2' => $ra]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }
    if ($email !== null && $email !== '') {
        $stmt = $pdo->prepare(
            "SELECT a.id, a.nome, a.ra, a.email, a.turma_id, a.ativo, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.email = :email
             LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }
    return null;
}

function liberarAluno(PDO $pdo, array $aluno, bool $dryRun): array
{
    $alunoId = (int) $aluno['id'];
    if (!tableExists($pdo, 'matricula')) {
        return ['ok' => false, 'error' => 'Tabela matricula ausente'];
    }

    $turmas = $pdo->query(
        "SELECT id, nome, ano_letivo_id FROM turmas
         WHERE (ativo = 1 OR ativo IS NULL) AND ano_letivo_id IS NOT NULL
         ORDER BY nome"
    )->fetchAll();

    if ($turmas === []) {
        return ['ok' => false, 'error' => 'Nenhuma turma ativa com ano_letivo_id'];
    }

    $inserted = 0;
    $skipped = 0;

    if (!$dryRun) {
        $upd = $pdo->prepare('UPDATE alunos SET ativo = 1, updated_at = NOW() WHERE id = :id');
        $upd->execute(['id' => $alunoId]);
    }

    $ins = $pdo->prepare(
        "INSERT IGNORE INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
         VALUES (:aluno_id, :turma_id, :ano_letivo_id, CURDATE(), 'ativa')"
    );

    foreach ($turmas as $t) {
        $tid = (int) $t['id'];
        $anoId = (int) $t['ano_letivo_id'];
        if ($tid <= 0 || $anoId <= 0) {
            continue;
        }
        if ($dryRun) {
            $check = $pdo->prepare(
                "SELECT id FROM matricula
                 WHERE aluno_id = :aluno_id AND turma_id = :turma_id AND ano_letivo_id = :ano
                 LIMIT 1"
            );
            $check->execute(['aluno_id' => $alunoId, 'turma_id' => $tid, 'ano' => $anoId]);
            if ($check->fetch()) {
                $skipped++;
            } else {
                $inserted++;
            }
            continue;
        }
        $ins->execute([
            'aluno_id' => $alunoId,
            'turma_id' => $tid,
            'ano_letivo_id' => $anoId,
        ]);
        if ($ins->rowCount() > 0) {
            $inserted++;
        } else {
            $skipped++;
        }
    }

    if (!$dryRun && tableExists($pdo, 'config_layout')) {
        $pdo->exec(
            "INSERT INTO config_layout (config_key, config_value) VALUES
                ('module_jornadas', '1'),
                ('module_provas', '1')
             ON DUPLICATE KEY UPDATE config_value = '1'"
        );
    }

    $countMat = $pdo->prepare(
        "SELECT COUNT(*) AS c FROM matricula WHERE aluno_id = :id AND status = 'ativa'"
    );
    $countMat->execute(['id' => $alunoId]);
    $totalMat = (int) ($countMat->fetch()['c'] ?? 0);

    return [
        'ok' => true,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'total_matriculas_ativas' => $totalMat,
        'total_turmas' => count($turmas),
    ];
}

echo "EducaTudo — liberar aluno de teste\n";
echo 'Modo: ' . ($dryRun ? 'dry-run (sem gravar)' : 'execução') . "\n\n";

$cfg = Database::getConfigFromEnv();
$port = !empty($cfg['port']) ? (int) $cfg['port'] : 3306;
$masterPdo = new PDO(
    "mysql:host={$cfg['host']};port={$port};dbname={$cfg['name']};charset=utf8mb4",
    (string) $cfg['user'],
    (string) ($cfg['pass'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$escolas = $masterPdo->query(
    'SELECT e.id, e.nome, e.slug, e.ativo,
            b.host, b.porta, b.nome_banco, b.usuario, b.senha_criptografada
     FROM escolas e
     INNER JOIN config_escolas_banco b ON b.escola_id = e.id
     ORDER BY e.id'
)->fetchAll();

$found = false;
foreach ($escolas as $row) {
    $escolaId = (int) $row['id'];
    if ($escolaIdFilter !== null && $escolaId !== $escolaIdFilter) {
        continue;
    }
    if (!$allEscolas && $escolaIdFilter === null) {
        // Busca em todas até achar o aluno
    }

    $slug = (string) ($row['slug'] ?? '');
    echo "Escola {$escolaId} ({$slug})...\n";
    $tenantPdo = connectTenantPdo($row);
    if (!$tenantPdo) {
        continue;
    }

    $aluno = findAluno($tenantPdo, $ra, $email);
    if (!$aluno) {
        echo "  · aluno não encontrado\n";
        continue;
    }

    $found = true;
    echo "  ✓ Aluno #{$aluno['id']} — {$aluno['nome']} (RA {$aluno['ra']}, turma: {$aluno['turma_nome']})\n";

    $result = liberarAluno($tenantPdo, $aluno, $dryRun);
    if (!$result['ok']) {
        echo "  ✗ {$result['error']}\n";
        break;
    }

    echo "  · Turmas no tenant: {$result['total_turmas']}\n";
    echo '  · Matrículas novas: ' . $result['inserted'] . ' · já existentes: ' . $result['skipped'] . "\n";
    echo "  · Total matrículas ativas: {$result['total_matriculas_ativas']}\n";
    if (!$dryRun) {
        echo "  · Módulos jornadas/provas habilitados em config_layout\n";
    }
    echo "\nConcluído. O aluno passa a ver jornadas e provas de todas as turmas matriculadas.\n";
    break;
}

if (!$found) {
    echo "\nNenhum aluno encontrado";
    if ($ra) {
        echo " com RA {$ra}";
    }
    if ($email) {
        echo " / email {$email}";
    }
    echo ".\nUse --escola-id=N ou --all-escolas para varrer tenants.\n";
    exit(1);
}
