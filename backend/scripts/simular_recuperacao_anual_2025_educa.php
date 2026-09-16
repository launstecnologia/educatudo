<?php
/**
 * Monta cenários de recuperação anual no Colégio Educa (2025):
 *  - perfil recuperação: média anual < 6, rec alta → aprovado após rec
 *  - perfil risco: média anual < 6, rec baixa → reprovado com rec lançada
 *
 * Uso (container PHP):
 *   php scripts/simular_recuperacao_anual_2025_educa.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

ini_set('memory_limit', '1024M');
set_time_limit(0);

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Modulos/fechamento/Services/FechamentoService.php';
require_once $basePath . '/app/Services/ResultadoHomologacaoService.php';
require_once $basePath . '/scripts/lib/SimulacaoAcademicaEduca.php';

const TENANT_DB = 'educatudo_educa';
const ANO = 2025;

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail('Abortado: DB_HOST=' . $host . ' não parece local.');
}

$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . TENANT_DB . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fail('Não conectou em ' . TENANT_DB . '. ' . $e->getMessage());
}

Database::setCurrentInstance(Database::createFromPdo($pdo, [
    'host' => $host,
    'port' => $port,
    'name' => TENANT_DB,
    'user' => $dbUser,
    'pass' => $dbPass,
]));

$db = Database::getInstance();
$fechamento = new FechamentoService();
$admin = $db->fetch(
    "SELECT id FROM usuarios WHERE tipo = 'admin_escola' AND ativo = 1 ORDER BY id ASC LIMIT 1"
);
$adminId = (int) ($admin['id'] ?? 1);

println('== Simular recuperação anual 2025 — Colégio Educa ==');

$restauradas = 0;
$comAntes = $db->fetchAll(
    "SELECT g.id, g.notas_json, g.media_final
     FROM boletim_resultados_gerados g
     INNER JOIN boletim_regras r ON r.id = g.regra_id
     WHERE g.preview = 0 AND g.vigente = 1 AND r.ano_letivo = :ano",
    ['ano' => ANO]
) ?: [];
foreach ($comAntes as $row) {
    $json = json_decode((string) ($row['notas_json'] ?? ''), true);
    if (!is_array($json) || !isset($json['media_antes_rec']) || !is_numeric($json['media_antes_rec'])) {
        continue;
    }
    $antes = round((float) $json['media_antes_rec'], 2);
    $atual = is_numeric($row['media_final'] ?? null) ? (float) $row['media_final'] : null;
    if ($atual !== null && abs($atual - 6.5) > 0.051) {
        continue;
    }
    $json['P1'] = $antes;
    $json['media_final'] = $antes;
    $db->query(
        'UPDATE boletim_resultados_gerados SET media_final = :m, notas_json = :j WHERE id = :id',
        ['m' => $antes, 'j' => json_encode($json, JSON_UNESCAPED_UNICODE), 'id' => (int) $row['id']]
    );
    $restauradas++;
}
println('  médias restauradas (tinham 6,5 de rec): ' . $restauradas);

$alunos = $db->fetchAll(
    "SELECT DISTINCT a.id, a.nome, m.turma_id, t.nome AS turma_nome
     FROM alunos a
     INNER JOIN matricula m ON m.aluno_id = a.id
     INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
     INNER JOIN turmas t ON t.id = m.turma_id AND t.ano_letivo = :ano_t
     WHERE m.status IN ('ativa', 'concluido', 'transferido')",
    ['ano' => ANO, 'ano_t' => ANO]
) ?: [];

$passaram = 0;
$reprovaram = 0;
$exemplosPassou = [];
$exemplosReprovou = [];

foreach ($alunos as $al) {
    $aid = (int) $al['id'];
    $perfil = SimulacaoAcademicaEduca::perfil($aid);
    if ($perfil !== 'recuperacao' && $perfil !== 'risco') {
        continue;
    }
    $passou = $perfil === 'recuperacao';
    $qtd = $passou ? 2 : 3;
    $notasBim = $passou ? [5.0, 5.2, 5.3, 5.5] : [4.0, 4.2, 4.4, 4.6];
    $rec = $passou ? 7.5 : 4.0;

    $materias = $db->fetchAll(
        "SELECT g.materia_id, g.materia_nome, ROUND(AVG(g.media_final), 2) AS media
         FROM boletim_resultados_gerados g
         INNER JOIN boletim_regras r ON r.id = g.regra_id
         WHERE g.preview = 0 AND g.vigente = 1 AND g.aluno_id = :aid AND r.ano_letivo = :ano
           AND g.materia_id IS NOT NULL
         GROUP BY g.materia_id, g.materia_nome
         ORDER BY media ASC, g.materia_nome ASC",
        ['aid' => $aid, 'ano' => ANO]
    ) ?: [];
    $materias = array_slice($materias, 0, $qtd);

    foreach ($materias as $mat) {
        $mid = (int) $mat['materia_id'];
        for ($bim = 1; $bim <= 4; $bim++) {
            $cel = $db->fetch(
                "SELECT g.id, g.notas_json
                 FROM boletim_resultados_gerados g
                 INNER JOIN boletim_regras r ON r.id = g.regra_id
                 WHERE g.preview = 0 AND g.vigente = 1 AND g.aluno_id = :aid
                   AND g.materia_id = :mid AND r.ano_letivo = :ano AND r.bimestre = :bim
                 ORDER BY g.id DESC LIMIT 1",
                ['aid' => $aid, 'mid' => $mid, 'ano' => ANO, 'bim' => $bim]
            );
            if (!$cel) {
                continue;
            }
            $json = json_decode((string) ($cel['notas_json'] ?? ''), true);
            if (!is_array($json)) {
                $json = [];
            }
            $nota = $notasBim[$bim - 1];
            $json['P1'] = $nota;
            $json['media_final'] = $nota;
            $json['media_antes_rec'] = $nota;
            $json['rec'] = $rec;
            $db->query(
                'UPDATE boletim_resultados_gerados SET media_final = :m, notas_json = :j WHERE id = :id',
                ['m' => $nota, 'j' => json_encode($json, JSON_UNESCAPED_UNICODE), 'id' => (int) $cel['id']]
            );
        }
    }

    $info = [
        'id' => $aid,
        'nome' => (string) $al['nome'],
        'turma_id' => (int) $al['turma_id'],
        'turma' => (string) $al['turma_nome'],
        'materias' => array_column($materias, 'materia_nome'),
    ];
    if ($passou) {
        $passaram++;
        if (count($exemplosPassou) < 3) {
            $exemplosPassou[] = $info;
        }
    } else {
        $reprovaram++;
        if (count($exemplosReprovou) < 3) {
            $exemplosReprovou[] = $info;
        }
    }
}

println('  alunos que passam na rec: ' . $passaram);
println('  alunos que reprovam na rec: ' . $reprovaram);

$turmas = $db->fetchAll(
    'SELECT id, nome FROM turmas WHERE ativo = 1 AND ano_letivo = :ano ORDER BY nome',
    ['ano' => ANO]
) ?: [];

foreach ($turmas as $turma) {
    $tid = (int) $turma['id'];
    $atual = $fechamento->garantirVigente($tid, ANO, 'ano', 0);
    $status = FechamentoMaquinaEstados::normalizar((string) ($atual['status'] ?? ''));
    if ($status === FechamentoMaquinaEstados::HOMOLOGADO) {
        $fechamento->retificar(
            $tid,
            ANO,
            'ano',
            0,
            $adminId,
            'Reabrir ano para homologar recuperação anual 2025 (Colégio Educa)'
        );
    }
}

foreach ($turmas as $turma) {
    $tid = (int) $turma['id'];
    $res = $fechamento->homologacao()->homologarTurma($tid, ANO, 'ano', 0, $adminId, [], true);
    $ok = !empty($res['success']);
    $det = $ok
        ? ('homologados=' . (int) ($res['homologados'] ?? 0) . ' ignorados=' . (int) ($res['ignorados'] ?? 0))
        : (string) ($res['error'] ?? 'falha');
    println('    ' . $turma['nome'] . ' ano ' . ($ok ? 'OK' : 'SKIP') . ' ' . $det);

    $atual = $fechamento->garantirVigente($tid, ANO, 'ano', 0);
    $status = FechamentoMaquinaEstados::normalizar((string) ($atual['status'] ?? ''));
    if ($status === FechamentoMaquinaEstados::ABERTO) {
        $fechamento->transitar(
            $tid,
            ANO,
            'ano',
            0,
            FechamentoMaquinaEstados::EM_FECHAMENTO,
            $adminId,
            'Abertura para homologação 2025 (Colégio Educa)'
        );
    }
    if ($status !== FechamentoMaquinaEstados::HOMOLOGADO) {
        $fechamento->transitar(
            $tid,
            ANO,
            'ano',
            0,
            FechamentoMaquinaEstados::HOMOLOGADO,
            $adminId,
            'Homologação do ano letivo 2025 (Colégio Educa)'
        );
    }
}

$ra = $db->fetchAll(
    "SELECT situacao, status, COUNT(*) n FROM resultado_academico
     WHERE ano_letivo = :ano AND periodo_tipo = 'ano'
     GROUP BY situacao, status
     ORDER BY n DESC",
    ['ano' => ANO]
) ?: [];
foreach ($ra as $r) {
    println('  resultado ano ' . $r['situacao'] . '/' . $r['status'] . ': ' . $r['n']);
}

println('  exemplos — passou na rec:');
foreach ($exemplosPassou as $ex) {
    println('    ' . $ex['nome'] . ' (id ' . $ex['id'] . ', ' . $ex['turma'] . ') · ' . implode(', ', $ex['materias']));
}
println('  exemplos — reprovou na rec:');
foreach ($exemplosReprovou as $ex) {
    println('    ' . $ex['nome'] . ' (id ' . $ex['id'] . ', ' . $ex['turma'] . ') · ' . implode(', ', $ex['materias']));
}
