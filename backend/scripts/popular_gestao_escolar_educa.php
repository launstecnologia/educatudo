<?php
/**
 * Popula Gestão Escolar do Colégio Educa: processos de matrícula, Só Faltas e catraca.
 *
 * Uso (container PHP):
 *   php scripts/popular_gestao_escolar_educa.php
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
require_once $basePath . '/app/Models/Education/SchoolAbsence.php';
require_once $basePath . '/app/Modulos/matricula/Models/MatriculaProcesso.php';
require_once $basePath . '/app/Modulos/presenca/Models/PresencaEvento.php';

use App\Modulos\Matricula\Models\MatriculaProcesso;

const TENANT_DB = 'educatudo_educa';
const SOURCE_DB = 'educatudo_colag';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

function validarIdentificadorMysql(string $value, string $label): string
{
    if ($value === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $value) || strlen($value) > 64) {
        throw new InvalidArgumentException("Identificador MySQL inválido ({$label}): {$value}");
    }
    return $value;
}

/**
 * Copia modelos de declaração/contrato/documento oficial do Colag (seeds das migrations).
 * Idempotente: só insere códigos/tipos ainda ausentes.
 */
function copiarModelosDocumentos(PDO $pdo, string $origem, string $destino): void
{
    $origem = validarIdentificadorMysql($origem, 'origem');
    $destino = validarIdentificadorMysql($destino, 'destino');
    $copias = [
        ['secretaria_modelos_documentos', 'codigo'],
        ['matricula_contrato_regras', 'tipo'],
        ['resultado_documento_layouts', 'tipo'],
    ];
    foreach ($copias as [$tabela, $uk]) {
        $tabela = validarIdentificadorMysql($tabela, 'tabela');
        $uk = validarIdentificadorMysql($uk, 'uk');
        $temOrigem = (bool) $pdo->query(
            'SHOW TABLES FROM `' . $origem . '` LIKE ' . $pdo->quote($tabela)
        )->fetchColumn();
        $temDestino = (bool) $pdo->query(
            'SHOW TABLES FROM `' . $destino . '` LIKE ' . $pdo->quote($tabela)
        )->fetchColumn();
        if (!$temOrigem || !$temDestino) {
            println("  {$tabela}: tabela ausente, ignorado");
            continue;
        }
        $cols = $pdo->query(
            'SELECT d.COLUMN_NAME FROM information_schema.COLUMNS d'
            . ' INNER JOIN information_schema.COLUMNS o'
            . '   ON o.TABLE_SCHEMA = ' . $pdo->quote($origem)
            . '  AND o.TABLE_NAME = d.TABLE_NAME'
            . '  AND o.COLUMN_NAME = d.COLUMN_NAME'
            . ' WHERE d.TABLE_SCHEMA = ' . $pdo->quote($destino)
            . '   AND d.TABLE_NAME = ' . $pdo->quote($tabela)
            . "   AND d.EXTRA NOT LIKE '%auto_increment%'"
            . ' ORDER BY d.ORDINAL_POSITION'
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $cols = array_map(static fn ($c) => validarIdentificadorMysql((string) $c, 'coluna'), $cols);
        if ($cols === []) {
            println("  {$tabela}: sem colunas copiáveis, ignorado");
            continue;
        }
        $lista = '`' . implode('`,`', $cols) . '`';
        $n = (int) $pdo->exec(
            'INSERT INTO `' . $destino . '`.`' . $tabela . '` (' . $lista . ')'
            . ' SELECT ' . $lista
            . ' FROM `' . $origem . '`.`' . $tabela . '` src'
            . ' WHERE NOT EXISTS ('
            . ' SELECT 1 FROM `' . $destino . '`.`' . $tabela . '` dst'
            . ' WHERE dst.`' . $uk . '` = src.`' . $uk . '`'
            . ')'
        );
        println("  {$tabela}: {$n} linha(s) copiada(s)");
    }
}

function periodoBimestre(int $ano, int $bim): array
{
    $bim = max(1, min(4, $bim));
    $mesInicio = ($bim - 1) * 3 + 1;
    $inicio = sprintf('%04d-%02d-01', $ano, $mesInicio);
    $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $ano, $mesInicio + 2)));
    if ($bim === 1) {
        $inicio = max($inicio, $ano . '-02-01');
    }
    return [$inicio, min($fim, $ano . '-12-15')];
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

println('== Gestão Escolar — Colégio Educa ==');

println('  modelos de documentos (declarações, contratos, oficiais):');
copiarModelosDocumentos($pdo, SOURCE_DB, TENANT_DB);

$admin = $db->fetch("SELECT id FROM usuarios WHERE email = 'admin@educa.local' LIMIT 1");
$adminId = $admin ? (int) $admin['id'] : 0;
$ano2026 = $db->fetch('SELECT id FROM ano_letivo WHERE ano = 2026 LIMIT 1');
$ano2026Id = $ano2026 ? (int) $ano2026['id'] : 0;
if ($ano2026Id <= 0) {
    fail('Ano letivo 2026 não encontrado.');
}

$alunos2026 = $db->fetchAll(
    "SELECT a.id, a.nome, a.email, a.nickname, a.data_nasc, a.ra, a.turma_id, t.serie_id, t.nome AS turma_nome
     FROM alunos a
     INNER JOIN matricula m ON m.aluno_id = a.id AND m.status = 'ativa'
     INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = 2026
     INNER JOIN turmas t ON t.id = m.turma_id
     ORDER BY a.id"
) ?: [];
println('  alunos ativos 2026: ' . count($alunos2026));

$processos = new MatriculaProcesso($db);
$jaProcessos = (int) ($db->fetch('SELECT COUNT(*) c FROM matricula_processos')['c'] ?? 0);
if ($jaProcessos > 0) {
    println('  processos de matrícula já existem (' . $jaProcessos . '), não duplica.');
} else {
    $criados = 0;
    foreach ($alunos2026 as $i => $al) {
        $nick = (string) ($al['nickname'] ?? '');
        $tipo = str_starts_with($nick, 'edt') ? 'transferencia' : (str_starts_with($nick, 'ed26') ? 'nova' : 'rematricula');
        $processos->create([
            'tipo' => $tipo,
            'status' => 'enturmada',
            'aluno_id' => (int) $al['id'],
            'ano_letivo_id' => $ano2026Id,
            'turma_id' => (int) $al['turma_id'],
            'serie_id' => (int) ($al['serie_id'] ?? 0) ?: null,
            'aluno_nome' => (string) $al['nome'],
            'aluno_email' => $al['email'] ?? null,
            'aluno_data_nasc' => $al['data_nasc'] ?? null,
            'resp_nome' => 'Responsável de ' . $al['nome'],
            'resp_email' => 'resp.' . $nick . '@educa.local',
            'resp_telefone' => '1198' . sprintf('%07d', (int) $al['id']),
            'resp_parentesco' => 'pai',
            'origem' => $tipo === 'nova' ? 'site' : 'interno',
            'pagamento_status' => 'pago',
            'observacoes' => 'Processo gerado na população E2E — aluno já enturmado em 2026.',
            'criado_por' => $adminId ?: null,
        ]);
        $criados++;
        unset($i);
    }

    $pipeline = [
        ['rascunho', 'nova', 'site', 'Helena Costa'],
        ['rascunho', 'nova', 'whatsapp', 'Otávio Martins'],
        ['rascunho', 'nova', 'indicacao', 'Lara Pires'],
        ['rascunho', 'transferencia', 'evento', 'Igor Vasconcelos'],
        ['aguardando_assinatura', 'nova', 'site', 'Beatriz Nunes'],
        ['aguardando_assinatura', 'nova', 'site', 'Caio Freitas'],
        ['aguardando_assinatura', 'rematricula', 'interno', 'Duda Azevedo'],
        ['aguardando_assinatura', 'nova', 'whatsapp', 'Enzo Barros'],
        ['aguardando_assinatura', 'transferencia', 'indicacao', 'Flávia Moura'],
        ['aguardando_assinatura', 'nova', 'site', 'Gustavo Pena'],
        ['confirmada', 'nova', 'site', 'Heloísa Ramos'],
        ['confirmada', 'nova', 'evento', 'Igor Santana'],
        ['confirmada', 'rematricula', 'interno', 'Júlia Prado'],
        ['confirmada', 'nova', 'site', 'Kaique Lopes'],
        ['confirmada', 'transferencia', 'whatsapp', 'Lívia Castro'],
        ['confirmada', 'nova', 'site', 'Murilo Teixeira'],
        ['confirmada', 'nova', 'indicacao', 'Nina Ferraz'],
        ['confirmada', 'rematricula', 'interno', 'Otto Braga'],
    ];
    $turma6a = $db->fetch("SELECT id, serie_id FROM turmas WHERE nome = '6º A 2026' LIMIT 1");
    foreach ($pipeline as $j => $p) {
        $processos->create([
            'tipo' => $p[1],
            'status' => $p[0],
            'aluno_id' => null,
            'ano_letivo_id' => $ano2026Id,
            'turma_id' => in_array($p[0], ['confirmada', 'aguardando_assinatura'], true) ? (int) ($turma6a['id'] ?? 0) ?: null : null,
            'serie_id' => (int) ($turma6a['serie_id'] ?? 0) ?: null,
            'aluno_nome' => $p[3],
            'aluno_email' => 'candidato' . ($j + 1) . '@educa.local',
            'aluno_data_nasc' => sprintf('2014-05-%02d', min(28, $j + 1)),
            'resp_nome' => 'Responsável de ' . $p[3],
            'resp_email' => 'candidato.resp' . ($j + 1) . '@educa.local',
            'resp_telefone' => '1197' . sprintf('%07d', $j + 1),
            'resp_parentesco' => 'mae',
            'origem' => $p[2],
            'pagamento_status' => $p[0] === 'confirmada' ? 'pago' : 'aguardando',
            'observacoes' => 'Candidato da captação 2026 (pipeline).',
            'criado_por' => $adminId ?: null,
        ]);
        $criados++;
    }
    println('  processos de matrícula: ' . $criados);
}

$absence = new SchoolAbsence();
$absence->ensureSchema();
$jaFaltas = (int) ($db->fetch('SELECT COUNT(*) c FROM faltas_eventos WHERE ativo = 1')['c'] ?? 0);
if ($jaFaltas > 0) {
    println('  eventos de faltas já existem (' . $jaFaltas . '), não duplica.');
} else {
    $anosFaltas = [
        2023 => [1, 2, 3, 4],
        2024 => [1, 2, 3, 4],
        2025 => [1, 2, 3, 4],
        2026 => [1, 2],
    ];
    $eventosCriados = 0;
    $lancamentos = 0;
    foreach ($anosFaltas as $ano => $bims) {
        $turmasAno = $db->fetchAll(
            'SELECT id FROM turmas WHERE ano_letivo = :a AND ativo = 1',
            ['a' => $ano]
        ) ?: [];
        $turmaIds = array_map(static fn($r) => (int) $r['id'], $turmasAno);
        if ($turmaIds === []) {
            continue;
        }
        foreach ($bims as $bim) {
            [$ini, $fim] = periodoBimestre($ano, $bim);
            $nome = sprintf('Faltas %dº bimestre %d', $bim, $ano);
            $exist = $db->fetch('SELECT id FROM faltas_eventos WHERE nome = :n AND ativo = 1 LIMIT 1', ['n' => $nome]);
            $eventoId = $exist
                ? (int) $exist['id']
                : $absence->createEvento($nome, (string) $bim, $ano, $turmaIds, $adminId ?: null, [], 'diario');
            $rows = $db->fetchAll(
                "SELECT df.aluno_id, da.materia_id,
                        SUM(CASE WHEN df.situacao IN ('falta','falta_justificada') THEN 1 ELSE 0 END) AS faltas
                 FROM diario_frequencias df
                 INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
                 INNER JOIN turmas t ON t.id = da.turma_id
                 WHERE t.ano_letivo = :ano
                   AND da.data_aula BETWEEN :ini AND :fim
                 GROUP BY df.aluno_id, da.materia_id
                 HAVING faltas > 0",
                ['ano' => $ano, 'ini' => $ini, 'fim' => $fim]
            ) ?: [];
            $porAluno = [];
            foreach ($rows as $row) {
                $aid = (int) $row['aluno_id'];
                $mid = (int) $row['materia_id'];
                $porAluno[$aid][$mid] = (string) $row['faltas'];
            }
            if ($porAluno !== []) {
                $absence->upsertLancamentos($eventoId, $porAluno, [], $adminId ?: null);
                $lancamentos += count($rows);
            }
            $eventosCriados++;
        }
    }
    println("  faltas: {$eventosCriados} eventos, {$lancamentos} lançamentos");
}

$eventosPresenca = new PresencaEvento();
if (!$eventosPresenca->tabelasProntas()) {
    fail('Tabela presenca_eventos ausente.');
}

$datas = [];
$cursor = new DateTime('2026-08-17');
$limite = new DateTime('2026-08-22');
for ($d = clone $cursor; $d <= $limite; $d->modify('+1 day')) {
    $datas[] = $d->format('Y-m-d');
}

$jaPresenca = (int) ($db->fetch('SELECT COUNT(*) c FROM presenca_eventos')['c'] ?? 0);
if ($jaPresenca > 0) {
    println('  presença já existe (' . $jaPresenca . ' eventos), não duplica.');
} else {
    $inseridos = 0;
    foreach ($datas as $data) {
        $n = 0;
        foreach ($alunos2026 as $al) {
            $aid = (int) $al['id'];
            $nick = (string) ($al['nickname'] ?? '');
            $diaSemana = (int) date('N', strtotime($data));
            $faltaCatraca = str_contains($nick, 'b10') || ($aid % 17 === 0 && $diaSemana >= 4);
            if ($faltaCatraca) {
                continue;
            }
            $atraso = ($aid % 11 === 0);
            $entradaHora = $atraso ? '07:42:00' : sprintf('07:%02d:00', 18 + ($aid % 10));
            $saidaHora = sprintf('12:%02d:00', 2 + ($aid % 8));
            $origem = ($aid % 5 === 0) ? 'manual_secretaria' : 'facial';
            foreach ([['entrada', $entradaHora], ['saida', $saidaHora]] as [$tipo, $hora]) {
                $res = $eventosPresenca->inserir([
                    'aluno_id' => $aid,
                    'tipo' => $tipo,
                    'ocorrido_em' => $data . ' ' . $hora,
                    'origem' => $origem,
                    'id_externo' => 'educa-' . $aid . '-' . $data . '-' . $tipo,
                    'identificador_bruto' => (string) ($al['ra'] ?? $nick),
                    'registrado_por' => $origem === 'manual_secretaria' ? $adminId : null,
                ]);
                if (empty($res['duplicado'])) {
                    $eventosPresenca->marcarProcessado((int) $res['id']);
                    $inseridos++;
                    $n++;
                }
            }
        }
        println("    {$data}: {$n} registros");
    }
    println('  presença: ' . $inseridos . ' eventos (entrada/saída)');
}

$totais = [
    'processos' => (int) ($db->fetch('SELECT COUNT(*) c FROM matricula_processos')['c'] ?? 0),
    'faltas_eventos' => (int) ($db->fetch('SELECT COUNT(*) c FROM faltas_eventos WHERE ativo = 1')['c'] ?? 0),
    'faltas_lancamentos' => (int) ($db->fetch('SELECT COUNT(*) c FROM faltas_lancamentos')['c'] ?? 0),
    'presenca' => (int) ($db->fetch('SELECT COUNT(*) c FROM presenca_eventos')['c'] ?? 0),
];
println('  totais: ' . json_encode($totais, JSON_UNESCAPED_UNICODE));
println('');
println('Telas:');
println('  http://educa.localhost/admin/matricula');
println('  http://educa.localhost/admin/faltas');
println('  http://educa.localhost/admin/presenca?data=2026-08-22');
println('  http://educa.localhost/admin/modelos-documentos?categoria=todos');
exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
