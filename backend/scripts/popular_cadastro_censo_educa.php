<?php
/**
 * Completa Censo/INEP, cadastro civil, ficha complementar e filiação
 * dos alunos do Colégio Educa.
 *
 * Uso (container PHP):
 *   php scripts/popular_cadastro_censo_educa.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

ini_set('memory_limit', '512M');
set_time_limit(0);

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Models/User/StudentComplementaryRecord.php';

const TENANT_DB = 'educatudo_educa';
const SENHA = 'Teste@123';

const NOMES_MAE = ['Ana', 'Carla', 'Elisa', 'Gabriela', 'Helena', 'Isabela', 'Karina', 'Marina', 'Olivia', 'Rafaela', 'Talita', 'Yasmin', 'Beatriz', 'Lúcia'];
const NOMES_PAI = ['Bruno', 'Diego', 'Eduardo', 'Felipe', 'Henrique', 'João', 'Lucas', 'Marcos', 'Nicolas', 'Pedro', 'Roberto', 'Samuel', 'Vitor', 'Caio'];
const RUAS = ['Rua das Palmeiras', 'Avenida Paulista', 'Rua Augusta', 'Rua da Consolação', 'Rua Vergueiro', 'Avenida Rebouças', 'Rua Pamplona', 'Rua Bela Cintra', 'Rua Oscar Freire', 'Avenida Brigadeiro Luís Antônio'];
const BAIRROS = ['Centro', 'Bela Vista', 'Consolação', 'Jardins', 'Moema', 'Perdizes', 'Pinheiros', 'Vila Mariana'];
const CORES = ['Parda', 'Branca', 'Parda', 'Branca', 'Parda', 'Preta', 'Branca', 'Parda', 'Amarela', 'Branca'];
const SANGUE = ['A+', 'O+', 'B+', 'A-', 'O+', 'O-', 'AB+', 'A+', 'O+', 'B+'];
const PLANOS = ['Unimed', 'SulAmérica', 'Bradesco Saúde', 'Amil', null, null];

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

function colunaExiste($db, string $tabela, string $coluna): bool
{
    $row = $db->fetch(
        "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
        ['t' => $tabela, 'c' => $coluna]
    );
    return (int) ($row['n'] ?? 0) > 0;
}

function garantirColuna($db, string $tabela, string $coluna, string $definicao): void
{
    if (!preg_match('/^[a-z_]+$/', $tabela) || !preg_match('/^[a-z_]+$/', $coluna)) {
        throw new InvalidArgumentException('Identificador inválido.');
    }
    if (colunaExiste($db, $tabela, $coluna)) {
        return;
    }
    $db->query("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
}

function gerarCpf(int $seed): string
{
    $n = [];
    $x = abs($seed) + 104729;
    for ($i = 0; $i < 9; $i++) {
        $x = abs(($x * 1103515245 + 12345) % 2147483647);
        $n[] = $x % 10;
    }
    if (count(array_unique($n)) === 1) {
        $n[8] = ($n[8] + 3) % 10;
    }
    $soma = 0;
    for ($i = 0, $p = 10; $i < 9; $i++, $p--) {
        $soma += $n[$i] * $p;
    }
    $d1 = ($soma * 10) % 11;
    $n[] = $d1 === 10 ? 0 : $d1;
    $soma = 0;
    for ($i = 0, $p = 11; $i < 10; $i++, $p--) {
        $soma += $n[$i] * $p;
    }
    $d2 = ($soma * 10) % 11;
    $n[] = $d2 === 10 ? 0 : $d2;
    return implode('', $n);
}

function sobrenomeDe(string $nome): string
{
    $limpo = trim(preg_replace('/\s*\(.*\)$/', '', $nome) ?? $nome);
    $partes = preg_split('/\s+/', $limpo) ?: [];
    return (string) (end($partes) ?: 'Silva');
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

try {
    $pdo = new PDO(
        'mysql:host=' . $host . ';port=' . $port . ';dbname=' . TENANT_DB . ';charset=utf8mb4',
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    Database::setCurrentInstance(Database::createFromPdo($pdo, [
        'host' => $host, 'port' => $port, 'name' => TENANT_DB, 'user' => $dbUser, 'pass' => $dbPass,
    ]));
    $db = Database::getInstance();

    println('== Cadastro / Censo — Colégio Educa ==');

    $sqlFicha = $basePath . '/database/migrations/2026_06_25_alunos_ficha_complementar.sql';
    if (is_file($sqlFicha)) {
        $pdo->exec((string) file_get_contents($sqlFicha));
    }

    garantirColuna($db, 'alunos', 'cpf', 'VARCHAR(14) NULL');
    garantirColuna($db, 'alunos', 'foto_url', 'TEXT NULL');
    garantirColuna($db, 'alunos', 'telefone', 'VARCHAR(20) NULL');
    garantirColuna($db, 'alunos', 'celular', 'VARCHAR(20) NULL');
    garantirColuna($db, 'alunos', 'rg', 'VARCHAR(20) NULL');
    garantirColuna($db, 'alunos', 'logradouro', 'VARCHAR(255) NULL');
    garantirColuna($db, 'alunos', 'numero', 'VARCHAR(20) NULL');
    garantirColuna($db, 'alunos', 'complemento', 'VARCHAR(100) NULL');
    garantirColuna($db, 'alunos', 'bairro', 'VARCHAR(120) NULL');
    garantirColuna($db, 'alunos', 'cidade', 'VARCHAR(120) NULL');
    garantirColuna($db, 'alunos', 'uf', 'CHAR(2) NULL');
    garantirColuna($db, 'alunos', 'cep', 'VARCHAR(8) NULL');
    garantirColuna($db, 'alunos', 'nome_social', 'VARCHAR(255) NULL');
    garantirColuna($db, 'alunos', 'nacionalidade', 'VARCHAR(60) NULL');
    garantirColuna($db, 'alunos', 'naturalidade', 'VARCHAR(120) NULL');
    garantirColuna($db, 'alunos', 'uf_nascimento', 'CHAR(2) NULL');
    garantirColuna($db, 'alunos', 'cor_raca', 'VARCHAR(20) NULL');
    garantirColuna($db, 'alunos', 'orgao_emissor', 'VARCHAR(30) NULL');
    garantirColuna($db, 'alunos', 'uf_rg', 'CHAR(2) NULL');
    garantirColuna($db, 'alunos', 'certidao_nascimento', 'VARCHAR(80) NULL');
    garantirColuna($db, 'alunos', 'certidao_livro', 'VARCHAR(20) NULL');
    garantirColuna($db, 'alunos', 'certidao_folha', 'VARCHAR(20) NULL');
    garantirColuna($db, 'alunos', 'certidao_termo', 'VARCHAR(20) NULL');
    garantirColuna($db, 'alunos', 'nis', 'VARCHAR(20) NULL');
    garantirColuna($db, 'alunos', 'passaporte', 'VARCHAR(30) NULL');
    garantirColuna($db, 'alunos', 'rne', 'VARCHAR(30) NULL');
    garantirColuna($db, 'alunos', 'zona', 'VARCHAR(10) NULL');
    garantirColuna($db, 'alunos', 'pais', 'VARCHAR(60) NULL');
    garantirColuna($db, 'alunos', 'whatsapp', 'VARCHAR(20) NULL');
    garantirColuna($db, 'alunos', 'email_secundario', 'VARCHAR(255) NULL');
    garantirColuna($db, 'alunos', 'nome_mae', 'VARCHAR(255) NULL');
    garantirColuna($db, 'alunos', 'nome_pai', 'VARCHAR(255) NULL');
    garantirColuna($db, 'alunos', 'codigo_inep', 'VARCHAR(20) NULL');

    garantirColuna($db, 'unidades', 'dependencia_administrativa', 'VARCHAR(20) NULL');

    garantirColuna($db, 'responsaveis', 'rg', 'VARCHAR(20) NULL');
    garantirColuna($db, 'responsaveis', 'celular', 'VARCHAR(20) NULL');
    garantirColuna($db, 'responsaveis', 'data_nascimento', 'DATE NULL');
    garantirColuna($db, 'responsaveis', 'endereco', 'VARCHAR(255) NULL');
    garantirColuna($db, 'responsaveis', 'numero', 'VARCHAR(20) NULL');
    garantirColuna($db, 'responsaveis', 'complemento', 'VARCHAR(100) NULL');
    garantirColuna($db, 'responsaveis', 'bairro', 'VARCHAR(120) NULL');
    garantirColuna($db, 'responsaveis', 'cidade', 'VARCHAR(120) NULL');
    garantirColuna($db, 'responsaveis', 'uf', 'CHAR(2) NULL');
    garantirColuna($db, 'responsaveis', 'cep', 'VARCHAR(9) NULL');
    garantirColuna($db, 'responsaveis', 'observacoes', 'TEXT NULL');

    println('  schema: colunas de censo/cadastro garantidas');

    $db->query("UPDATE unidades SET dependencia_administrativa = 'privada' WHERE dependencia_administrativa IS NULL OR dependencia_administrativa = ''");
    $db->query("UPDATE unidades SET inep = CASE
        WHEN tipo = 'matriz' THEN COALESCE(NULLIF(inep, ''), '35012345')
        ELSE COALESCE(NULLIF(inep, ''), '35012346')
    END");

    $hash = password_hash(SENHA, PASSWORD_DEFAULT);
    $fichaModel = new StudentComplementaryRecord();
    $alunos = $db->fetchAll('SELECT id, nome, nickname, ra, email, data_nasc, turma_id FROM alunos ORDER BY id') ?: [];
    println('  alunos: ' . count($alunos));

    $atualizados = 0;
    $fichas = 0;
    $responsaveis = 0;

    foreach ($alunos as $al) {
        $id = (int) $al['id'];
        $nick = (string) ($al['nickname'] ?? ('al' . $id));
        $nome = (string) $al['nome'];
        $sobrenome = sobrenomeDe($nome);
        $semPai = ($id % 23 === 0);
        $nomeMae = NOMES_MAE[$id % count(NOMES_MAE)] . ' ' . $sobrenome;
        $nomePai = $semPai ? null : (NOMES_PAI[$id % count(NOMES_PAI)] . ' ' . $sobrenome);
        $rua = RUAS[$id % count(RUAS)];
        $bairro = BAIRROS[$id % count(BAIRROS)];
        $numero = (string) (10 + ($id % 890));
        $cep = sprintf('01%03d%03d', ($id % 400) + 100, ($id % 900) + 10);
        $foneMae = '11' . sprintf('9%08d', 70000000 + $id);
        $fonePai = '11' . sprintf('9%08d', 80000000 + $id);
        $foneAluno = '11' . sprintf('9%08d', 90000000 + $id);
        $cpfAluno = gerarCpf($id * 17 + 101);
        $rgAluno = sprintf('%02d.%03d.%03d-%d', ($id % 90) + 10, ($id * 3) % 1000, ($id * 7) % 1000, $id % 10);
        $inep = '3518' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
        $nis = str_pad((string) (10000000000 + $id * 17), 11, '0', STR_PAD_LEFT);
        $certidao = '1234567' . str_pad((string) (2020 + ($id % 6)), 4, '0', STR_PAD_LEFT)
            . '01' . str_pad((string) $id, 19, '0', STR_PAD_LEFT);
        $zona = ($id % 31 === 0) ? 'rural' : 'urbana';
        $cor = CORES[$id % count(CORES)];
        $nasc = (string) ($al['data_nasc'] ?? '2012-03-01');

        $db->query(
            "UPDATE alunos SET
                cpf = :cpf,
                rg = :rg,
                telefone = :telefone,
                celular = :celular,
                whatsapp = :whatsapp,
                email_secundario = :email_sec,
                logradouro = :logradouro,
                numero = :numero,
                complemento = :complemento,
                bairro = :bairro,
                cidade = :cidade,
                uf = :uf,
                cep = :cep,
                nome_mae = :nome_mae,
                nome_pai = :nome_pai,
                codigo_inep = :codigo_inep,
                nacionalidade = :nacionalidade,
                naturalidade = :naturalidade,
                uf_nascimento = :uf_nascimento,
                cor_raca = :cor_raca,
                orgao_emissor = :orgao_emissor,
                uf_rg = :uf_rg,
                certidao_nascimento = :certidao,
                certidao_livro = :livro,
                certidao_folha = :folha,
                certidao_termo = :termo,
                nis = :nis,
                zona = :zona,
                pais = :pais,
                codigo_aluno = COALESCE(NULLIF(codigo_aluno, ''), :codigo_aluno)
             WHERE id = :id",
            [
                'cpf' => $cpfAluno,
                'rg' => $rgAluno,
                'telefone' => '1130001' . sprintf('%03d', $id % 1000),
                'celular' => $foneAluno,
                'whatsapp' => $foneAluno,
                'email_sec' => $nick . '.familia@educa.local',
                'logradouro' => $rua,
                'numero' => $numero,
                'complemento' => ($id % 5 === 0) ? 'Apto ' . (10 + ($id % 40)) : null,
                'bairro' => $bairro,
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'cep' => $cep,
                'nome_mae' => $nomeMae,
                'nome_pai' => $nomePai,
                'codigo_inep' => $inep,
                'nacionalidade' => 'Brasileira',
                'naturalidade' => 'São Paulo',
                'uf_nascimento' => 'SP',
                'cor_raca' => $cor,
                'orgao_emissor' => 'SSP',
                'uf_rg' => 'SP',
                'certidao' => substr($certidao, 0, 32),
                'livro' => sprintf('%03d', ($id % 90) + 1),
                'folha' => sprintf('%03d', ($id % 200) + 1),
                'termo' => sprintf('%05d', $id),
                'nis' => $nis,
                'zona' => $zona,
                'pais' => 'Brasil',
                'codigo_aluno' => strtoupper(str_replace('.', '', $nick)),
                'id' => $id,
            ]
        );
        $atualizados++;

        $maeId = upsertResponsavel($db, $hash, [
            'email' => 'mae.' . $nick . '@educa.local',
            'nome' => $nomeMae,
            'cpf' => gerarCpf($id * 31 + 3),
            'rg' => sprintf('%02d.%03d.%03d-%d', 20, ($id * 5) % 1000, ($id * 11) % 1000, 1),
            'telefone' => $foneMae,
            'celular' => $foneMae,
            'data_nascimento' => sprintf('%04d-04-%02d', (int) substr($nasc, 0, 4) - 28, min(28, ($id % 27) + 1)),
            'endereco' => $rua,
            'numero' => $numero,
            'complemento' => ($id % 5 === 0) ? 'Apto ' . (10 + ($id % 40)) : null,
            'bairro' => $bairro,
            'cidade' => 'São Paulo',
            'uf' => 'SP',
            'cep' => substr($cep, 0, 5) . '-' . substr($cep, 5),
        ]);
        vincularResponsavel($db, $id, $maeId, 'mae', true);
        $responsaveis++;

        if ($nomePai !== null) {
            $paiEmail = 'pai.' . $nick . '@educa.local';
            $paiId = upsertResponsavel($db, $hash, [
                'email' => $paiEmail,
                'nome' => $nomePai,
                'cpf' => gerarCpf($id * 31 + 7),
                'rg' => sprintf('%02d.%03d.%03d-%d', 30, ($id * 9) % 1000, ($id * 13) % 1000, 2),
                'telefone' => $fonePai,
                'celular' => $fonePai,
                'data_nascimento' => sprintf('%04d-08-%02d', (int) substr($nasc, 0, 4) - 30, min(28, ($id % 26) + 1)),
                'endereco' => $rua,
                'numero' => $numero,
                'complemento' => ($id % 5 === 0) ? 'Apto ' . (10 + ($id % 40)) : null,
                'bairro' => $bairro,
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'cep' => substr($cep, 0, 5) . '-' . substr($cep, 5),
            ]);
            vincularResponsavel($db, $id, $paiId, 'pai', false);
            $responsaveis++;
        }

        $db->query('UPDATE alunos SET responsavel_id = :r WHERE id = :id', ['r' => $maeId, 'id' => $id]);

        $usaVan = ($id % 17 === 0);
        $ficha = [
            'tipo_sanguineo' => SANGUE[$id % count(SANGUE)],
            'plano_saude' => PLANOS[$id % count(PLANOS)],
            'plano_saude_numero' => PLANOS[$id % count(PLANOS)] ? ('EDU' . str_pad((string) $id, 8, '0', STR_PAD_LEFT)) : null,
            'hospital_referencia' => ($id % 4 === 0) ? 'Hospital das Clínicas' : 'Santa Casa de São Paulo',
            'alergias' => ($id % 11 === 0) ? 'Dipirona' : (($id % 29 === 0) ? 'Picada de inseto' : null),
            'medicamentos_uso' => ($id % 19 === 0) ? 'Bombinha de asma (salbutamol) se crise' : null,
            'condicoes_cronicas' => ($id % 19 === 0) ? 'Asma leve' : null,
            'deficiencias_obs' => ($id % 41 === 0) ? 'Usa óculos para miopia' : null,
            'contato_emergencia_nome' => $nomeMae,
            'contato_emergencia_telefone' => $foneMae,
            'contato_emergencia_parentesco' => 'Mãe',
            'restricoes_alimentares' => ($id % 13 === 0) ? 'Intolerância à lactose' : null,
            'alimentacao_obs' => ($id % 13 === 0) ? 'Preferir merenda sem leite.' : null,
            'usa_transporte_escolar' => $usaVan ? 1 : 0,
            'transporte_tipo' => $usaVan ? 'escolar' : 'proprio',
            'transporte_rota' => $usaVan ? 'Linha Fund II / EM — manhã' : null,
            'transporte_ponto' => $usaVan ? ($rua . ', ' . $numero) : null,
            'transporte_responsavel' => $usaVan ? 'Van Educa — Sr. Paulo' : null,
            'transporte_telefone' => $usaVan ? '11960001000' : null,
            'observacoes_gerais' => null,
        ];
        $fichaModel->upsert($id, $ficha);
        $fichas++;
    }

    if (colunaExiste($db, 'matricula_processos', 'aluno_nome_mae')) {
        $sets = [
            'mp.aluno_nome_mae = a.nome_mae',
            'mp.aluno_nome_pai = a.nome_pai',
            'mp.aluno_codigo_inep = a.codigo_inep',
            'mp.aluno_data_nasc = COALESCE(mp.aluno_data_nasc, a.data_nasc)',
            "mp.resp_nome = COALESCE(NULLIF(mp.resp_nome, ''), a.nome_mae)",
        ];
        if (colunaExiste($db, 'matricula_processos', 'aluno_cpf')) {
            $sets[] = 'mp.aluno_cpf = a.cpf';
        }
        if (colunaExiste($db, 'matricula_processos', 'aluno_endereco')) {
            $sets[] = "mp.aluno_endereco = CONCAT(a.logradouro, ', ', a.numero, ' — ', a.bairro, ', ', a.cidade, '/', a.uf)";
        }
        $db->query(
            'UPDATE matricula_processos mp
             INNER JOIN alunos a ON a.id = mp.aluno_id
             SET ' . implode(', ', $sets) . '
             WHERE mp.aluno_id IS NOT NULL'
        );
    }

    $totais = [
        'alunos' => $atualizados,
        'fichas' => $fichas,
        'vinculos_responsaveis' => $responsaveis,
        'com_inep' => (int) ($db->fetch("SELECT COUNT(*) n FROM alunos WHERE ativo = 1 AND codigo_inep IS NOT NULL AND codigo_inep <> ''")['n'] ?? 0),
        'com_mae' => (int) ($db->fetch("SELECT COUNT(*) n FROM alunos WHERE ativo = 1 AND nome_mae IS NOT NULL AND nome_mae <> ''")['n'] ?? 0),
        'com_cpf' => (int) ($db->fetch("SELECT COUNT(*) n FROM alunos WHERE ativo = 1 AND cpf IS NOT NULL AND cpf <> ''")['n'] ?? 0),
        'responsaveis' => (int) ($db->fetch('SELECT COUNT(*) n FROM responsaveis')['n'] ?? 0),
    ];
    println('  totais: ' . json_encode($totais, JSON_UNESCAPED_UNICODE));
    println('');
    println('Telas:');
    println('  http://educa.localhost/admin/reports/censo');
    println('  http://educa.localhost/admin/students');
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}

function upsertResponsavel($db, string $hash, array $dados): int
{
    $exist = $db->fetch('SELECT id FROM responsaveis WHERE email = :e LIMIT 1', ['e' => $dados['email']]);
    $params = [
        'nome' => $dados['nome'],
        'cpf' => $dados['cpf'],
        'telefone' => $dados['telefone'],
        'celular' => $dados['celular'],
        'rg' => $dados['rg'],
        'data_nascimento' => $dados['data_nascimento'],
        'endereco' => $dados['endereco'],
        'numero' => $dados['numero'],
        'complemento' => $dados['complemento'],
        'bairro' => $dados['bairro'],
        'cidade' => $dados['cidade'],
        'uf' => $dados['uf'],
        'cep' => $dados['cep'],
    ];
    if ($exist) {
        $db->query(
            "UPDATE responsaveis SET
                nome = :nome, cpf = :cpf, telefone = :telefone, celular = :celular, rg = :rg,
                data_nascimento = :data_nascimento, endereco = :endereco, numero = :numero,
                complemento = :complemento, bairro = :bairro, cidade = :cidade, uf = :uf, cep = :cep, ativo = 1
             WHERE id = :id",
            $params + ['id' => (int) $exist['id']]
        );
        return (int) $exist['id'];
    }
    return (int) $db->insert(
        "INSERT INTO responsaveis
            (nome, email, senha_hash, cpf, telefone, celular, rg, data_nascimento,
             endereco, numero, complemento, bairro, cidade, uf, cep, ativo, password)
         VALUES
            (:nome, :email, :senha_hash, :cpf, :telefone, :celular, :rg, :data_nascimento,
             :endereco, :numero, :complemento, :bairro, :cidade, :uf, :cep, 1, '')",
        $params + ['email' => $dados['email'], 'senha_hash' => $hash]
    );
}

function vincularResponsavel($db, int $alunoId, int $respId, string $tipo, bool $financeiro): void
{
    $ja = $db->fetch(
        'SELECT id FROM alunos_responsaveis WHERE aluno_id = :a AND responsavel_id = :p LIMIT 1',
        ['a' => $alunoId, 'p' => $respId]
    );
    $flags = [
        'a' => $alunoId,
        'p' => $respId,
        'tv' => $tipo,
        'parentesco' => $tipo === 'mae' ? 'Mãe' : 'Pai',
        'fin' => $financeiro ? 1 : 0,
        'retirar' => 1,
        'boletos' => $financeiro ? 1 : 0,
        'boletim' => 1,
        'notif' => 1,
        'ped' => $financeiro ? 1 : 0,
        'assina' => $financeiro ? 1 : 0,
    ];
    if ($ja) {
        $db->query(
            "UPDATE alunos_responsaveis SET
                tipo_vinculo = :tv, parentesco = :parentesco, is_financeiro = :fin,
                pode_retirar = :retirar, recebe_boletos = :boletos, recebe_boletim = :boletim,
                recebe_notificacoes = :notif, responsavel_pedagogico = :ped, assina_documentos = :assina, ativo = 1
             WHERE id = :id",
            $flags + ['id' => (int) $ja['id']]
        );
        return;
    }
    $db->insert(
        "INSERT INTO alunos_responsaveis
            (aluno_id, responsavel_id, tipo_vinculo, parentesco, is_financeiro, ativo,
             pode_retirar, recebe_boletos, recebe_boletim, recebe_notificacoes,
             responsavel_pedagogico, assina_documentos)
         VALUES
            (:a, :p, :tv, :parentesco, :fin, 1, :retirar, :boletos, :boletim, :notif, :ped, :assina)",
        $flags
    );
}
