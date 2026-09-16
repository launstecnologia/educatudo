<?php
/**
 * Colégio Educa — apaga configuração de notas/boletim e as provas do seed (EM25*).
 *
 * Remove: modelos e eventos de boletim, boletins gerados, quadros, tipos de nota,
 * agrupamentos, regras acadêmicas e as provas/blocos criados pelos seeds de bimestre
 * (títulos EM25 / EM25B2 / EM25B3 / EM25B4). Alunos e turmas ficam.
 *
 * Uso (container PHP):
 *   php scripts/reset_config_notas_educa.php
 *   php scripts/reset_config_notas_educa.php --confirmar
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
require_once $basePath . '/app/Core/Database.php';

const TENANT_DB = 'educatudo_educa';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

$confirmar = in_array('--confirmar', $argv ?? [], true);
$ajuda = in_array('--ajuda', $argv ?? [], true)
    || in_array('--help', $argv ?? [], true)
    || in_array('-h', $argv ?? [], true);
if ($ajuda) {
    echo <<<TXT
Apaga boletim/quadro/tipos e as provas do seed (EM25*) do Colégio Educa (teste local).

Não apaga: alunos e turmas.

  php scripts/reset_config_notas_educa.php              (mostra o que seria apagado)
  php scripts/reset_config_notas_educa.php --confirmar  (apaga de verdade)

TXT;
    exit(0);
}

$appEnv = strtolower((string) env('APP_ENV', 'production'));
if (!in_array($appEnv, ['development', 'dev', 'local'], true)) {
    fail('Abortado: APP_ENV não é development/local.');
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . TENANT_DB . ';charset=utf8mb4';
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
Database::setCurrentInstance(Database::createFromPdo($pdo, [
    'host' => $host,
    'port' => $port,
    'name' => TENANT_DB,
    'user' => $dbUser,
    'pass' => $dbPass,
]));

$db = Database::getInstance();

function tabelaBase($db, string $nome): bool
{
    $row = $db->fetch(
        "SELECT 1 AS ok
           FROM information_schema.tables
          WHERE table_schema = DATABASE()
            AND table_name = :n
            AND table_type = 'BASE TABLE'
          LIMIT 1",
        ['n' => $nome]
    );
    return !empty($row['ok']);
}

function colunaExiste($db, string $tabela, string $coluna): bool
{
    $row = $db->fetch(
        "SELECT 1 AS ok
           FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name = :t
            AND column_name = :c
          LIMIT 1",
        ['t' => $tabela, 'c' => $coluna]
    );
    return !empty($row['ok']);
}

function contar($db, string $tabela, string $whereSql = '', array $params = []): int
{
    if (!tabelaBase($db, $tabela)) {
        return 0;
    }
    $sql = 'SELECT COUNT(*) AS n FROM `' . $tabela . '`';
    if ($whereSql !== '') {
        $sql .= ' WHERE ' . $whereSql;
    }
    $row = $db->fetch($sql, $params);
    return (int) ($row['n'] ?? 0);
}

function apagar($db, string $tabela, bool $executar): int
{
    $n = contar($db, $tabela);
    if ($n <= 0 || !$executar) {
        return $n;
    }
    $db->query('DELETE FROM `' . $tabela . '`');
    return $n;
}

function idsTituloLike($db, string $tabela, string $like): array
{
    if (!tabelaBase($db, $tabela) || !colunaExiste($db, $tabela, 'titulo')) {
        return [];
    }
    $rows = $db->fetchAll(
        'SELECT id FROM `' . $tabela . '` WHERE titulo LIKE :p',
        ['p' => $like]
    ) ?: [];
    $ids = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function idsSeedProvas($db, array $blocoIds): array
{
    $ids = [];
    foreach (idsTituloLike($db, 'provas', 'EM25%') as $id) {
        $ids[$id] = $id;
    }
    if ($blocoIds !== [] && tabelaBase($db, 'provas_blocos_vinculo')) {
        foreach (array_chunk($blocoIds, 400) as $parte) {
            [$in, $params] = placeholdersIn($parte);
            $rows = $db->fetchAll(
                'SELECT prova_id AS id FROM provas_blocos_vinculo WHERE bloco_id IN (' . $in . ')',
                $params
            ) ?: [];
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }
    }
    return array_values($ids);
}

/**
 * @param list<int> $ids
 * @return array{0:string,1:array<string,int>}
 */
function placeholdersIn(array $ids): array
{
    $ph = [];
    $params = [];
    foreach (array_values($ids) as $i => $id) {
        $k = 'id' . $i;
        $ph[] = ':' . $k;
        $params[$k] = (int) $id;
    }
    return [implode(',', $ph), $params];
}

function apagarPorIds($db, string $tabela, string $coluna, array $ids, bool $executar): int
{
    if ($ids === [] || !tabelaBase($db, $tabela) || !colunaExiste($db, $tabela, $coluna)) {
        return 0;
    }
    if (!preg_match('/^[a-z0-9_]+$/', $tabela) || !preg_match('/^[a-z0-9_]+$/', $coluna)) {
        return 0;
    }
    $n = 0;
    foreach (array_chunk($ids, 400) as $parte) {
        [$in, $params] = placeholdersIn($parte);
        $row = $db->fetch(
            'SELECT COUNT(*) AS n FROM `' . $tabela . '` WHERE `' . $coluna . '` IN (' . $in . ')',
            $params
        );
        $qtd = (int) ($row['n'] ?? 0);
        $n += $qtd;
        if ($executar && $qtd > 0) {
            $db->query('DELETE FROM `' . $tabela . '` WHERE `' . $coluna . '` IN (' . $in . ')', $params);
        }
    }
    return $n;
}

function idsQuestoes($db, array $provaIds): array
{
    if ($provaIds === [] || !tabelaBase($db, 'provas_questoes')) {
        return [];
    }
    $ids = [];
    foreach (array_chunk($provaIds, 400) as $parte) {
        [$in, $params] = placeholdersIn($parte);
        $rows = $db->fetchAll(
            'SELECT id FROM provas_questoes WHERE prova_id IN (' . $in . ')',
            $params
        ) ?: [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
    }
    return array_values($ids);
}

function idsBlocoProfessores($db, array $blocoIds): array
{
    if ($blocoIds === [] || !tabelaBase($db, 'provas_blocos_professores')) {
        return [];
    }
    $ids = [];
    foreach (array_chunk($blocoIds, 400) as $parte) {
        [$in, $params] = placeholdersIn($parte);
        $rows = $db->fetchAll(
            'SELECT id FROM provas_blocos_professores WHERE bloco_id IN (' . $in . ')',
            $params
        ) ?: [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
    }
    return array_values($ids);
}

$blocoSeedIds = idsTituloLike($db, 'provas_blocos', 'EM25%');
$provaSeedIds = idsSeedProvas($db, $blocoSeedIds);

$contagem = [
    'Boletins gerados' => contar($db, 'boletim_resultados_gerados'),
    'Eventos de notas (regras)' => contar($db, 'boletim_regras'),
    'Modelos de boletim' => contar($db, 'boletins'),
    'Quadros de notas' => contar($db, 'quadros_notas'),
    'Tipos de nota' => contar($db, 'provas_tipos_avaliacao'),
    'Notas finais por tipo' => contar($db, 'notas_tipo_finais'),
    'Agrupamentos de componentes' => contar($db, 'agrupamentos_componentes'),
    'Regras acadêmicas' => contar($db, 'regras_academicas'),
    'Blocos do seed (EM25*)' => count($blocoSeedIds),
    'Provas do seed (EM25*)' => count($provaSeedIds),
];

println('== Reset configuração de notas — Colégio Educa ==');
foreach ($contagem as $rotulo => $n) {
    println('  ' . $rotulo . ': ' . $n);
}

if (!$confirmar) {
    println('');
    println('Nada foi apagado. Para executar:');
    println('  docker exec php_app_educatudo php /var/www/html/scripts/reset_config_notas_educa.php --confirmar');
    exit(0);
}

println('');
println('Apagando…');

try {
    $db->beginTransaction();

    if (tabelaBase($db, 'provas_blocos')) {
    $sets = [];
    foreach (['tipo_avaliacao_id', 'grupo_regras_notas_id', 'grupo_regras_marca_id', 'grupo_regras_tipo_id'] as $col) {
        if (colunaExiste($db, 'provas_blocos', $col)) {
            $sets[] = '`' . $col . '` = NULL';
        }
    }
    if ($sets !== []) {
        $db->query('UPDATE provas_blocos SET ' . implode(', ', $sets));
    }
}

if (tabelaBase($db, 'boletins')) {
    $sets = [];
    if (colunaExiste($db, 'boletins', 'regra_id')) {
        $sets[] = 'regra_id = NULL';
    }
    if (colunaExiste($db, 'boletins', 'regra_academica_id')) {
        $sets[] = 'regra_academica_id = NULL';
    }
    if ($sets !== []) {
        $db->query('UPDATE boletins SET ' . implode(', ', $sets));
    }
}

$ordem = [
    'boletim_resultados_gerados',
    'boletim_notas_manuais',
    'boletim_alunos_travados',
    'boletim_geracoes',
    'boletim_log_geracoes',
    'boletim_observacoes',
    'boletim_componentes',
    'boletim_regras',
    'boletins',
    'provas_blocos_quadros_notas',
    'quadros_notas_bloco_colunas',
    'quadros_notas_bloco_materias',
    'quadros_notas_blocos',
    'quadros_notas_colunas',
    'quadros_notas',
    'notas_tipo_finais',
    'provas_tipos_avaliacao',
    'agrupamentos_componentes_itens',
    'agrupamentos_componentes',
    'regras_academicas_historico',
    'regras_academicas',
];

foreach ($ordem as $tabela) {
    $n = apagar($db, $tabela, true);
    if ($n > 0) {
        println('  · ' . $tabela . ': ' . $n);
    }
}

if (tabelaBase($db, 'boletim_ficha_celulas')) {
    $db->query(
        "UPDATE boletim_ficha_celulas
            SET nota = NULL, conceito = NULL, faltas = NULL, origem = 'vazia'
          WHERE origem = 'calculada'
            AND status IN ('aberta', 'reaberta')"
    );
    println('  · ficha: notas calculadas abertas limpas');
}

if (tabelaBase($db, 'fechamento_periodo')) {
    $n = contar($db, 'fechamento_periodo', 'ano_letivo = :ano', ['ano' => 2025]);
    if ($n > 0) {
        $db->query('DELETE FROM fechamento_periodo WHERE ano_letivo = :ano', ['ano' => 2025]);
        println('  · fechamento_periodo 2025: ' . $n);
    }
}

    $questaoIds = idsQuestoes($db, $provaSeedIds);
    $bpIds = idsBlocoProfessores($db, $blocoSeedIds);
    if ($blocoSeedIds !== [] && tabelaBase($db, 'diario_aulas') && colunaExiste($db, 'diario_aulas', 'evento_bloco_id')) {
        foreach (array_chunk($blocoSeedIds, 400) as $parte) {
            [$in, $params] = placeholdersIn($parte);
            $db->query(
                'UPDATE diario_aulas SET evento_bloco_id = NULL WHERE evento_bloco_id IN (' . $in . ')',
                $params
            );
        }
    }
    $db->query('SET FOREIGN_KEY_CHECKS = 0');
    $provasApagadas = [
        'provas_respostas' => apagarPorIds($db, 'provas_respostas', 'prova_id', $provaSeedIds, true),
        'provas_respostas_log' => apagarPorIds($db, 'provas_respostas_log', 'prova_id', $provaSeedIds, true),
        'provas_realizacoes' => apagarPorIds($db, 'provas_realizacoes', 'prova_id', $provaSeedIds, true),
        'provas_validacoes_log' => apagarPorIds($db, 'provas_validacoes_log', 'prova_id', $provaSeedIds, true),
        'provas_log_eventos' => apagarPorIds($db, 'provas_log_eventos', 'prova_id', $provaSeedIds, true),
        'provas_final' => apagarPorIds($db, 'provas_final', 'prova_id', $provaSeedIds, true),
        'provas_alternativas' => apagarPorIds($db, 'provas_alternativas', 'questao_id', $questaoIds, true),
        'provas_questoes' => apagarPorIds($db, 'provas_questoes', 'prova_id', $provaSeedIds, true),
        'provas_turmas' => apagarPorIds($db, 'provas_turmas', 'prova_id', $provaSeedIds, true),
        'provas_blocos_vinculo' => apagarPorIds($db, 'provas_blocos_vinculo', 'prova_id', $provaSeedIds, true),
        'provas_blocos_notas_lancadas' => apagarPorIds($db, 'provas_blocos_notas_lancadas', 'bloco_id', $blocoSeedIds, true),
        'provas_blocos_professores_turmas' => apagarPorIds($db, 'provas_blocos_professores_turmas', 'bloco_professor_id', $bpIds, true),
        'provas_blocos_professores' => apagarPorIds($db, 'provas_blocos_professores', 'bloco_id', $blocoSeedIds, true),
        'provas_blocos_turmas' => apagarPorIds($db, 'provas_blocos_turmas', 'bloco_id', $blocoSeedIds, true),
        'provas_professores' => apagarPorIds($db, 'provas_professores', 'bloco_id', $blocoSeedIds, true),
        'provas_blocos_grupos_regras' => apagarPorIds($db, 'provas_blocos_grupos_regras', 'bloco_id', $blocoSeedIds, true),
        'provas_blocos_quadros_notas' => apagarPorIds($db, 'provas_blocos_quadros_notas', 'bloco_id', $blocoSeedIds, true),
        'provas' => apagarPorIds($db, 'provas', 'id', $provaSeedIds, true),
        'provas_blocos' => apagarPorIds($db, 'provas_blocos', 'id', $blocoSeedIds, true),
    ];
    $db->query('SET FOREIGN_KEY_CHECKS = 1');
    foreach ($provasApagadas as $tabela => $n) {
        if ($n > 0) {
            println('  · ' . $tabela . ': ' . $n);
        }
    }

    $db->commit();
} catch (Throwable $e) {
    try {
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $ignored) {
    }
    if ($db->inTransaction()) {
        $db->rollback();
    }
    fail('Falha no reset (nada ficou gravado): ' . $e->getMessage());
}

println('');
println('Pronto. Configuração e provas do seed (EM25*) foram apagadas.');
println('Alunos e turmas continuam. Pode rodar de novo os seeds de cada bimestre:');
println('  php scripts/seed_provas_1_bimestre_educa.php');
println('  php scripts/seed_provas_2_bimestre_educa.php');
println('  php scripts/seed_provas_3_bimestre_educa.php');
println('  php scripts/seed_provas_4_bimestre_educa.php');
exit(0);
