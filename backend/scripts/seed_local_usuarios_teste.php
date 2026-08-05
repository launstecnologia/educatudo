<?php
/**
 * Seed de usuários de teste para ambiente local (Docker).
 *
 * Uso (dentro do container PHP):
 *   php scripts/seed_local_usuarios_teste.php
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

const SCHOOL_SLUG = 'colag';
const TENANT_DB = 'educatudo_colag';

const MASTER_EMAIL = 'admin@local.educatudo';
const MASTER_SENHA = 'Teste@123';
const MASTER_NOME = 'Admin Master Local';

const ADMIN_ESCOLA_EMAIL = 'admin@colag.local';
const ADMIN_ESCOLA_SENHA = 'Teste@123';
const ADMIN_ESCOLA_NOME = 'Admin Colag';

const ALUNO_NICKNAME = 'aluno.teste';
const ALUNO_SENHA = 'Teste@123';
const ALUNO_NOME = 'Aluno Teste';
const ALUNO_RA = 'TESTE001';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
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

function ensureMasterAvatarUrl(PDO $masterPdo): void
{
    $check = $masterPdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'usuarios_master'
           AND COLUMN_NAME = 'avatar_url'"
    )->fetchColumn();
    if ((int) $check > 0) {
        return;
    }
    $masterPdo->exec(
        "ALTER TABLE usuarios_master ADD COLUMN avatar_url VARCHAR(500) NULL DEFAULT NULL
         COMMENT 'URL da foto de perfil' AFTER nome"
    );
    $masterPdo->prepare(
        'INSERT IGNORE INTO migrations_master (migration_name) VALUES (?)'
    )->execute(['2026_07_09_usuarios_master_avatar_url_master.sql']);
    println('→ Patch master: coluna avatar_url adicionada.');
}

function ensureUsuarioMaster(PDO $masterPdo): void
{
    $stmt = $masterPdo->prepare('SELECT id FROM usuarios_master WHERE email = ? LIMIT 1');
    $stmt->execute([MASTER_EMAIL]);
    if ($stmt->fetch()) {
        println('→ Master: usuário já existe (' . MASTER_EMAIL . ')');
        return;
    }

    $hash = password_hash(MASTER_SENHA, PASSWORD_DEFAULT);
    $ins = $masterPdo->prepare(
        'INSERT INTO usuarios_master (email, senha_hash, nome, ativo) VALUES (?, ?, ?, 1)'
    );
    $ins->execute([MASTER_EMAIL, $hash, MASTER_NOME]);
    println('→ Master: usuário criado (' . MASTER_EMAIL . ')');
}

function ensureAdminEscola(PDO $tenantPdo): void
{
    $stmt = $tenantPdo->prepare("SELECT id FROM usuarios WHERE email = ? AND tipo = 'admin_escola' LIMIT 1");
    $stmt->execute([ADMIN_ESCOLA_EMAIL]);
    if ($stmt->fetch()) {
        println('→ Colag admin: usuário já existe (' . ADMIN_ESCOLA_EMAIL . ')');
        return;
    }

    $hash = password_hash(ADMIN_ESCOLA_SENHA, PASSWORD_DEFAULT);
    $ins = $tenantPdo->prepare(
        "INSERT INTO usuarios (tipo, perfil_admin, nome, email, senha_hash, ativo)
         VALUES ('admin_escola', 'dev', ?, ?, ?, 1)"
    );
    $ins->execute([ADMIN_ESCOLA_NOME, ADMIN_ESCOLA_EMAIL, $hash]);
    println('→ Colag admin: usuário criado (' . ADMIN_ESCOLA_EMAIL . ')');
}

function ensureAlunoTeste(PDO $tenantPdo): void
{
    $stmt = $tenantPdo->prepare('SELECT id FROM alunos WHERE nickname = ? OR ra = ? LIMIT 1');
    $stmt->execute([ALUNO_NICKNAME, ALUNO_RA]);
    if ($stmt->fetch()) {
        println('→ Colag aluno: usuário já existe (' . ALUNO_NICKNAME . ')');
        return;
    }

    $hash = password_hash(ALUNO_SENHA, PASSWORD_DEFAULT);
    $ins = $tenantPdo->prepare(
        'INSERT INTO alunos (
            nome, email, senha_hash, ra, nickname, serie,
            ativo, status, pagante, primeiro_acesso, password
         ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, 1, 0, ?)'
    );
    $ins->execute([
        ALUNO_NOME,
        'aluno.teste@colag.local',
        $hash,
        ALUNO_RA,
        ALUNO_NICKNAME,
        '1º Ano',
        'ACTIVE',
        '',
    ]);
    println('→ Colag aluno: usuário criado (' . ALUNO_NICKNAME . ')');
}

function ensureAlunoTurmaEMatricula(PDO $tenantPdo): void
{
    $stmt = $tenantPdo->prepare('SELECT id, turma_id FROM alunos WHERE nickname = ? LIMIT 1');
    $stmt->execute([ALUNO_NICKNAME]);
    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$aluno) {
        return;
    }

    $alunoId = (int) $aluno['id'];
    $turmaAtual = (int) ($aluno['turma_id'] ?? 0);

    $turmaRow = $tenantPdo->query(
        "SELECT t.id
         FROM turmas t
         INNER JOIN jornadas j ON j.turma_id = t.id AND j.ativo = 1
         WHERE t.ativo = 1
         ORDER BY j.id DESC
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if (!$turmaRow) {
        $turmaRow = $tenantPdo->query(
            'SELECT id FROM turmas WHERE ativo = 1 ORDER BY id ASC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
    }

    if (!$turmaRow) {
        println('→ Colag aluno: nenhuma turma ativa — matrícula não aplicada');
        return;
    }

    $turmaId = (int) $turmaRow['id'];
    if ($turmaAtual === $turmaId) {
        println('→ Colag aluno: já vinculado à turma id=' . $turmaId);
    } else {
        $tenantPdo->prepare('UPDATE alunos SET turma_id = :turma_id WHERE id = :id')
            ->execute(['turma_id' => $turmaId, 'id' => $alunoId]);
        println('→ Colag aluno: turma principal definida (id=' . $turmaId . ')');
    }

    $hasMatricula = (bool) $tenantPdo->query("SHOW TABLES LIKE 'matricula'")->fetch(PDO::FETCH_NUM);
    if (!$hasMatricula) {
        return;
    }

    $matriculaAtiva = $tenantPdo->prepare(
        "SELECT id FROM matricula WHERE aluno_id = :aluno_id AND turma_id = :turma_id AND status = 'ativa' LIMIT 1"
    );
    $matriculaAtiva->execute(['aluno_id' => $alunoId, 'turma_id' => $turmaId]);
    if ($matriculaAtiva->fetch()) {
        println('→ Colag aluno: matrícula ativa já existe');
        return;
    }

    $anoRow = $tenantPdo->query(
        'SELECT id FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    $anoLetivoId = (int) ($anoRow['id'] ?? 0);
    if ($anoLetivoId <= 0) {
        println('→ Colag aluno: ano letivo ativo não encontrado — matrícula não criada');
        return;
    }

    $tenantPdo->prepare(
        "INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status, created_at, updated_at)
         VALUES (:aluno_id, :turma_id, :ano_letivo_id, CURDATE(), 'ativa', NOW(), NOW())"
    )->execute([
        'aluno_id' => $alunoId,
        'turma_id' => $turmaId,
        'ano_letivo_id' => $anoLetivoId,
    ]);
    println('→ Colag aluno: matrícula criada na turma id=' . $turmaId);
}

function ensureEscolaRegistrada(PDO $masterPdo): int
{
    $stmt = $masterPdo->prepare('SELECT id, nome, dominio FROM escolas WHERE slug = ? LIMIT 1');
    $stmt->execute([SCHOOL_SLUG]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        fwrite(STDERR, "Escola slug '" . SCHOOL_SLUG . "' não encontrada. Rode ./scripts/init-local.sh primeiro.\n");
        exit(1);
    }
    println('→ Escola teste: ' . $row['nome'] . ' (http://' . $row['dominio'] . ')');
    return (int) $row['id'];
}

println('== EducaTudo — seed usuários de teste (local) ==');

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$masterDb = (string) env('DB_NAME', 'educatudo_master');
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');

$hostsLocaisPermitidos = ['mysql', 'localhost', '127.0.0.1', '::1'];
if (!in_array($host, $hostsLocaisPermitidos, true)) {
    fwrite(STDERR, "Abortado: DB_HOST={$host} não parece ambiente local.\n");
    exit(1);
}

$masterPdo = connectPdo($host, $port, $masterDb, $dbUser, $dbPass);
ensureEscolaRegistrada($masterPdo);
ensureMasterAvatarUrl($masterPdo);
ensureUsuarioMaster($masterPdo);

$tenantPdo = connectPdo($host, $port, TENANT_DB, $dbUser, $dbPass);
ensureAdminEscola($tenantPdo);
ensureAlunoTeste($tenantPdo);
ensureAlunoTurmaEMatricula($tenantPdo);

println('');
println('Credenciais de teste:');
println('');
println('Master:       http://master.localhost/master');
println('              ' . MASTER_EMAIL . ' / ' . MASTER_SENHA);
println('');
println('Admin Colag:  http://colag.localhost/admin');
println('              ' . ADMIN_ESCOLA_EMAIL . ' / ' . ADMIN_ESCOLA_SENHA);
println('');
println('Aluno Colag:  http://colag.localhost/');
println('              ' . ALUNO_NICKNAME . ' / ' . ALUNO_SENHA);
println('');
