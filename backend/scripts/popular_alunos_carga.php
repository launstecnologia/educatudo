<?php
/**
 * Popula alunos de carga com nickname + senha iguais, prontos para logar no portal.
 *
 * Uso (container PHP):
 *   php scripts/popular_alunos_carga.php --total=10000 --confirmar
 *   php scripts/popular_alunos_carga.php --total=10000 --turma-id=12 --confirmar
 *
 * Login do aluno: carga00001 … carga10000
 * Senha: env CARGA_SENHA ou --senha= (padrão Carga@2026). Não use 123456.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/config/app.php';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function parseArgs(array $argv): array
{
    $senhaEnv = getenv('CARGA_SENHA');
    $out = [
        'total' => 10000,
        'prefixo' => 'carga',
        'senha' => ($senhaEnv !== false && $senhaEnv !== '') ? (string) $senhaEnv : 'Carga@2026',
        'turma_id' => 0,
        'lote' => 200,
        'db' => (string) env('TENANT_DB', 'educatudo_colag'),
        'confirmar' => false,
        'confirmar_remoto' => false,
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--confirmar') {
            $out['confirmar'] = true;
            continue;
        }
        if ($arg === '--confirmar-remoto') {
            $out['confirmar_remoto'] = true;
            continue;
        }
        if (preg_match('/^--([a-z0-9-]+)=(.+)$/', $arg, $m)) {
            $chave = str_replace('-', '_', $m[1]);
            $valor = $m[2];
            if (in_array($chave, ['total', 'turma_id', 'lote'], true)) {
                $out[$chave] = (int) $valor;
            } elseif (in_array($chave, ['db', 'prefixo', 'senha'], true)) {
                $out[$chave] = $valor;
            }
        }
    }
    $out['total'] = max(1, min(50000, (int) $out['total']));
    $out['lote'] = max(10, min(500, (int) $out['lote']));
    $out['prefixo'] = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $out['prefixo']) ?: 'carga');
    return $out;
}

function connectPdo(string $host, int $port, string $db, string $user, string $pass): PDO
{
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    return $pdo;
}

function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute(['t' => $tabela, 'c' => $coluna]);
    return (int) $stmt->fetchColumn() > 0;
}

function tabelaExiste(PDO $pdo, string $tabela): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1'
    );
    $stmt->execute(['t' => $tabela]);
    return (bool) $stmt->fetch();
}

function nicknameCarga(string $prefixo, int $indice): string
{
    return $prefixo . str_pad((string) $indice, 5, '0', STR_PAD_LEFT);
}

function likeEscape(string $valor): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
}

$opts = parseArgs($argv);
if ($opts['senha'] === '123456') {
    fwrite(STDERR, "Abortado: senha 123456 força troca no login. Use outra (ex.: Carga@2026).\n");
    exit(1);
}

if (!preg_match('/^[a-z0-9_]+$/', $opts['db'])) {
    fwrite(STDERR, "Abortado: --db inválido. Use só letras, números e underscore.\n");
    exit(1);
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
$ambiente = defined('ENVIRONMENT') ? (string) ENVIRONMENT : 'production';
$hostsLocais = ['mysql', 'localhost', '127.0.0.1', '::1'];
$hostPareceLocal = in_array($host, $hostsLocais, true);
$precisaRemoto = !$hostPareceLocal || $ambiente !== 'development';

if ($precisaRemoto && !$opts['confirmar_remoto']) {
    fwrite(STDERR, "Ambiente={$ambiente} host={$host}. Passe --confirmar-remoto se for de propósito.\n");
    exit(1);
}

if (!$opts['confirmar']) {
    fwrite(STDERR, "Abortado: confirme o destino com --confirmar (banco={$opts['db']} total={$opts['total']}).\n");
    exit(1);
}

$pdo = connectPdo($host, $port, $opts['db'], $dbUser, $dbPass);

$turmaId = (int) $opts['turma_id'];
if ($turmaId > 0) {
    $turma = $pdo->prepare('SELECT id FROM turmas WHERE id = :id LIMIT 1');
    $turma->execute(['id' => $turmaId]);
    if (!$turma->fetch()) {
        fwrite(STDERR, "Abortado: turma {$turmaId} não existe neste banco.\n");
        exit(1);
    }
}

$anoLetivoId = 0;
if ($turmaId > 0 && tabelaExiste($pdo, 'ano_letivo')) {
    $ano = $pdo->query('SELECT id FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC LIMIT 1')->fetch();
    $anoLetivoId = (int) ($ano['id'] ?? 0);
}

$temCodigo = colunaExiste($pdo, 'alunos', 'codigo_aluno');
$temPrimeiro = colunaExiste($pdo, 'alunos', 'primeiro_acesso');
$temStatus = colunaExiste($pdo, 'alunos', 'status');
$temPassword = colunaExiste($pdo, 'alunos', 'password');
$temPagante = colunaExiste($pdo, 'alunos', 'pagante');
$temSerie = colunaExiste($pdo, 'alunos', 'serie');
$temMatricula = tabelaExiste($pdo, 'matricula');

$hash = password_hash($opts['senha'], PASSWORD_DEFAULT);

$colunas = ['nome', 'nickname', 'email', 'senha_hash', 'ra', 'ativo'];
if ($temCodigo) {
    $colunas[] = 'codigo_aluno';
}
if ($temPrimeiro) {
    $colunas[] = 'primeiro_acesso';
}
if ($temStatus) {
    $colunas[] = 'status';
}
if ($temPassword) {
    $colunas[] = 'password';
}
if ($temPagante) {
    $colunas[] = 'pagante';
}
if ($temSerie) {
    $colunas[] = 'serie';
}
if ($turmaId > 0 && colunaExiste($pdo, 'alunos', 'turma_id')) {
    $colunas[] = 'turma_id';
}

$jaExistem = [];
$prefixLike = likeEscape($opts['prefixo']) . '%';
$existentes = $pdo->prepare(
    "SELECT nickname, ra, email FROM alunos
     WHERE nickname LIKE :p ESCAPE '\\\\'
        OR ra LIKE :p2 ESCAPE '\\\\'
        OR email LIKE :p3 ESCAPE '\\\\'"
);
$existentes->execute([
    'p' => $prefixLike,
    'p2' => $prefixLike,
    'p3' => likeEscape($opts['prefixo']) . '%@carga.local',
]);
foreach ($existentes->fetchAll() as $row) {
    foreach (['nickname', 'ra'] as $campo) {
        $valor = strtolower(trim((string) ($row[$campo] ?? '')));
        if ($valor !== '') {
            $jaExistem[$valor] = true;
        }
    }
}

println('== EducaTudo — popular alunos de carga ==');
println('Ambiente: ' . $ambiente . '  host=' . $host . '  banco=' . $opts['db']);
println('Total: ' . $opts['total'] . '  prefixo=' . $opts['prefixo']);
println('Turma: ' . ($turmaId > 0 ? (string) $turmaId : 'nenhuma (passe --turma-id para matricular)'));
println('Já existiam com esse prefixo: ' . count($jaExistem));

$inseridos = 0;
$pulados = 0;
$idsNovos = [];
$placeholders = '(' . implode(', ', array_fill(0, count($colunas), '?')) . ')';

$pdo->beginTransaction();
try {
    $loteLinhas = [];
    $nicksLote = [];
    $flush = static function () use ($pdo, $colunas, $placeholders, &$loteLinhas, &$nicksLote, &$inseridos, &$idsNovos): void {
        if ($loteLinhas === [] || $nicksLote === []) {
            return;
        }
        $sql = 'INSERT INTO alunos (' . implode(', ', $colunas) . ') VALUES '
            . implode(', ', array_fill(0, count($nicksLote), $placeholders));
        $pdo->prepare($sql)->execute($loteLinhas);

        $ph = implode(',', array_fill(0, count($nicksLote), '?'));
        $buscar = $pdo->prepare(
            'SELECT id FROM alunos WHERE nickname IN (' . $ph . ') AND email LIKE ?'
        );
        $buscar->execute(array_merge($nicksLote, ['%@carga.local']));
        $idsLote = array_map('intval', $buscar->fetchAll(PDO::FETCH_COLUMN));
        if (count($idsLote) !== count($nicksLote)) {
            throw new RuntimeException(
                'Lote inconsistente: inseridos ' . count($nicksLote)
                . ' nicknames, encontrados ' . count($idsLote) . ' IDs.'
            );
        }
        foreach ($idsLote as $id) {
            $idsNovos[] = $id;
        }

        $inseridos += count($nicksLote);
        $loteLinhas = [];
        $nicksLote = [];
    };

    for ($i = 1; $i <= $opts['total']; $i++) {
        $nick = nicknameCarga($opts['prefixo'], $i);
        if (isset($jaExistem[strtolower($nick)])) {
            $pulados++;
            continue;
        }
        $valores = [
            'nome' => 'Aluno Carga ' . $nick,
            'nickname' => $nick,
            'email' => $nick . '@carga.local',
            'senha_hash' => $hash,
            'ra' => $nick,
            'ativo' => 1,
        ];
        if ($temCodigo) {
            $valores['codigo_aluno'] = $nick;
        }
        if ($temPrimeiro) {
            $valores['primeiro_acesso'] = 0;
        }
        if ($temStatus) {
            $valores['status'] = 'ACTIVE';
        }
        if ($temPassword) {
            $valores['password'] = '';
        }
        if ($temPagante) {
            $valores['pagante'] = 0;
        }
        if ($temSerie) {
            $valores['serie'] = 'Não informada';
        }
        if (in_array('turma_id', $colunas, true)) {
            $valores['turma_id'] = $turmaId;
        }
        foreach ($colunas as $col) {
            $loteLinhas[] = $valores[$col];
        }
        $nicksLote[] = $nick;
        if (count($nicksLote) >= $opts['lote']) {
            $flush();
            if ($inseridos % 1000 === 0) {
                println('→ inseridos ' . $inseridos);
            }
        }
    }
    $flush();

    if ($temMatricula && $turmaId > 0 && $anoLetivoId > 0 && $idsNovos !== []) {
        $insMat = $pdo->prepare(
            'INSERT IGNORE INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status, created_at, updated_at)
             VALUES (:aluno_id, :turma_id, :ano_letivo_id, CURDATE(), \'ativa\', NOW(), NOW())'
        );
        foreach ($idsNovos as $alunoId) {
            $insMat->execute([
                'aluno_id' => (int) $alunoId,
                'turma_id' => $turmaId,
                'ano_letivo_id' => $anoLetivoId,
            ]);
        }
        println('→ matrículas ativas na turma ' . $turmaId . ': ' . count($idsNovos));
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

println('');
println('Inseridos agora: ' . $inseridos);
println('Já existiam (pulados): ' . $pulados);
println('');
println('Login no portal do aluno:');
println('  ' . nicknameCarga($opts['prefixo'], 1) . ' … ' . nicknameCarga($opts['prefixo'], $opts['total']));
println('  senha: a mesma para todos (CARGA_SENHA ou --senha; não exibida)');
println('');
