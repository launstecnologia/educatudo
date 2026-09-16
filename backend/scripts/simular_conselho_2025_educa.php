<?php
/**
 * Conselho de Classe 2025 no Colégio Educa: sessões 1º–4º bimestre em todas
 * as turmas, deliberações (incluindo aprovado pelo conselho), encaminhamentos,
 * observações, ata — e re-homologação do ano.
 *
 * Uso (container PHP):
 *   php scripts/simular_conselho_2025_educa.php
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
require_once $basePath . '/app/Services/ConselhoService.php';
require_once $basePath . '/app/Modulos/fechamento/Services/FechamentoService.php';
require_once $basePath . '/app/Modulos/vida-escolar/Services/VidaEscolarService.php';
use App\Modulos\ConselhoClasse\Services\ConselhoService;

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
$conselhos = new ConselhoService();
$fechamento = new FechamentoService();
$admin = $db->fetch(
    "SELECT id, nome FROM usuarios WHERE tipo = 'admin_escola' AND ativo = 1 ORDER BY id ASC LIMIT 1"
);
$adminId = (int) ($admin['id'] ?? 1);
$adminNome = trim((string) ($admin['nome'] ?? 'Coordenação'));

$datas = [
    1 => ANO . '-04-10',
    2 => ANO . '-06-25',
    3 => ANO . '-09-18',
    4 => ANO . '-12-05',
];

println('== Conselho de Classe 2025 — Colégio Educa ==');

$turmas = $db->fetchAll(
    'SELECT id, nome FROM turmas WHERE ativo = 1 AND ano_letivo = :ano ORDER BY nome',
    ['ano' => ANO]
) ?: [];

$aprovadosConselho = [];
$sessoes = 0;
$delibs = 0;
$encs = 0;
$obs = 0;
$atas = 0;

foreach ($turmas as $turma) {
    $tid = (int) $turma['id'];
    $alunos = $db->fetchAll(
        "SELECT DISTINCT a.id, a.nome
         FROM alunos a
         INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t
         INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
         WHERE m.status IN ('ativa', 'concluido', 'transferido')
         ORDER BY a.nome",
        ['t' => $tid, 'ano' => ANO]
    ) ?: [];
    $sits = [];
    foreach ($db->fetchAll(
        "SELECT aluno_id, situacao FROM resultado_academico
         WHERE turma_id = :t AND ano_letivo = :ano AND periodo_tipo = 'ano' AND status = 'homologado'",
        ['t' => $tid, 'ano' => ANO]
    ) ?: [] as $r) {
        $sits[(int) $r['aluno_id']] = (string) $r['situacao'];
    }
    $profs = $conselhos->model()->professoresDaTurma($tid);
    $profId = (int) ($profs[0]['id'] ?? 0);

    for ($bim = 1; $bim <= 4; $bim++) {
        $res = $conselhos->criar([
            'turma_id' => $tid,
            'ano_letivo' => ANO,
            'bimestre' => $bim,
            'data_reuniao' => $datas[$bim],
            'pauta' => $bim === 4
                ? 'Conselho final ' . $turma['nome'] . ' — análise de rendimento, frequência e recuperação.'
                : 'Conselho do ' . $bim . 'º bimestre — ' . $turma['nome'] . '.',
        ], $adminId);
        if (empty($res['success'])) {
            println('    ' . $turma['nome'] . ' B' . $bim . ' SKIP ' . (string) ($res['error'] ?? 'criar'));
            continue;
        }
        $sid = (int) $res['id'];
        $sessao = $conselhos->model()->findById($sid);
        $status = (string) ($sessao['status'] ?? '');
        if ($status === 'finalizado') {
            $conselhos->reabrir($sid, $adminId);
        }
        $sessao = $conselhos->model()->findById($sid);
        $status = (string) ($sessao['status'] ?? '');
        if (in_array($status, ['em_preparacao', 'reaberto'], true)) {
            $conselhos->abrir($sid, $adminId);
        }

        $jaCoord = $db->fetch(
            "SELECT id FROM conselho_participantes WHERE sessao_id = :s AND cargo = 'coordenacao' LIMIT 1",
            ['s' => $sid]
        );
        if (!$jaCoord) {
            $db->insert(
                "INSERT INTO conselho_participantes (sessao_id, usuario_id, nome, cargo, presente)
                 VALUES (:s, :u, :n, 'coordenacao', 1)",
                ['s' => $sid, 'u' => $adminId, 'n' => $adminNome !== '' ? $adminNome : 'Coordenação']
            );
        }

        $jaSalvou = false;
        foreach ($alunos as $al) {
            $aid = (int) $al['id'];
            $sit = $sits[$aid] ?? 'aprovado';
            $anterior = $sit !== '' ? $sit : 'sem_dados';

            if ($bim < 4) {
                if (in_array($sit, ['reprovado_rendimento', 'reprovado_frequencia', 'aprovado_recuperacao'], true)) {
                    $enc = $conselhos->encaminhar($sid, [
                        'aluno_id' => $aid,
                        'tipo' => $sit === 'reprovado_frequencia' ? 'contato_responsavel' : 'acompanhamento_pedagogico',
                        'detalhe' => $sit === 'reprovado_frequencia'
                            ? 'Família convocada para acompanhar frequência no bimestre.'
                            : 'Acompanhamento pedagógico das disciplinas com média abaixo da mínima.',
                    ], $adminId);
                    if (!empty($enc['success'])) {
                        $encs++;
                    }
                }
                continue;
            }

            $decisao = 'manter';
            $just = 'Conselho mantém o resultado preliminar do fechamento anual.';
            if ($sit === 'reprovado_rendimento' && !$jaSalvou) {
                $decisao = 'aprovado_conselho';
                $just = 'Aluno em recuperação com evolução no 2º semestre. Conselho delibera aprovação considerando trajetória e comprometimento.';
                $jaSalvou = true;
                $aprovadosConselho[] = [
                    'id' => $aid,
                    'nome' => (string) $al['nome'],
                    'turma' => (string) $turma['nome'],
                ];
            } elseif ($sit === 'reprovado_rendimento') {
                $decisao = 'retido';
                $just = 'Médias abaixo da mínima mesmo após recuperação. Conselho mantém a retenção.';
            } elseif ($sit === 'reprovado_frequencia') {
                $decisao = 'manter';
                $just = 'Frequência abaixo do mínimo legal (LDB). Conselho registra e mantém a reprovação por frequência.';
            } elseif ($sit === 'aprovado_recuperacao') {
                $just = 'Aprovado após recuperação. Conselho registra e mantém o resultado.';
            } elseif ($sit === 'transferido') {
                $decisao = 'transferido';
                $just = 'Aluno transferido no decorrer do ano. Conselho registra a situação.';
            }

            $conselhos->model()->inserirDeliberacao([
                'sessao_id' => $sid,
                'aluno_id' => $aid,
                'materia_id' => 0,
                'resultado_anterior' => $anterior,
                'resultado_decisao' => $decisao,
                'justificativa' => $just,
                'registrado_por' => $adminId,
            ]);
            $delibs++;

            if (in_array($decisao, ['aprovado_conselho', 'retido'], true) || in_array($sit, ['aprovado_recuperacao', 'reprovado_frequencia'], true)) {
                $tipoEnc = $decisao === 'aprovado_conselho' ? 'decisao_final'
                    : ($sit === 'reprovado_frequencia' ? 'contato_responsavel' : 'recuperacao');
                $enc = $conselhos->encaminhar($sid, [
                    'aluno_id' => $aid,
                    'tipo' => $tipoEnc,
                    'detalhe' => $just,
                ], $adminId);
                if (!empty($enc['success'])) {
                    $encs++;
                }
                if ($profId > 0) {
                    $obsRes = $conselhos->registrarObservacao(
                        $sid,
                        $aid,
                        $profId,
                        $decisao === 'aprovado_conselho'
                            ? 'Participou das atividades de recuperação e apresentou evolução. Indico aprovação pelo Conselho.'
                            : 'Registro do Conselho: situação discutida com a equipe e a família.'
                    );
                    if (!empty($obsRes['success'])) {
                        $obs++;
                    }
                }
            }
        }

        $ata = $conselhos->gerarAta($sid, [
            'pauta' => (string) ($sessao['pauta'] ?? ''),
            'sintese' => $bim === 4
                ? 'Reunião final do ano letivo ' . ANO . '. Analisados rendimento, frequência, recuperação e encaminhamentos.'
                : 'Reunião bimestral. Pontos de atenção em rendimento e frequência foram registrados.',
            'decisoes' => $bim === 4
                ? 'Deliberações individuais lançadas na ata. Casos-limite aprovados pelo Conselho; retenções mantidas quando a recuperação não foi suficiente.'
                : 'Encaminhamentos pedagógicos e contato com responsáveis quando necessário.',
        ], $adminId);
        if (!empty($ata['success'])) {
            $atas++;
        }
        $conselhos->finalizar($sid, $adminId);
        $sessoes++;
    }
    println('  · ' . $turma['nome']);
}

println('  sessões finalizadas: ' . $sessoes);
println('  deliberações: ' . $delibs);
println('  encaminhamentos: ' . $encs);
println('  observações: ' . $obs);
println('  atas: ' . $atas);

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
            'Reabrir ano após Conselho de Classe 2025 (Colégio Educa)'
        );
    }
    $res = $fechamento->homologacao()->homologarTurma($tid, ANO, 'ano', 0, $adminId, [], true);
    $ok = !empty($res['success']);
    println('    ' . $turma['nome'] . ' ano ' . ($ok ? 'OK' : 'SKIP') . ' ' . (string) ($res['error'] ?? ('homologados=' . (int) ($res['homologados'] ?? 0))));
    $atual = $fechamento->garantirVigente($tid, ANO, 'ano', 0);
    $st = FechamentoMaquinaEstados::normalizar((string) ($atual['status'] ?? ''));
    if ($st === FechamentoMaquinaEstados::ABERTO) {
        $fechamento->transitar($tid, ANO, 'ano', 0, FechamentoMaquinaEstados::EM_FECHAMENTO, $adminId, 'Abertura pós-conselho 2025');
    }
    if ($st !== FechamentoMaquinaEstados::HOMOLOGADO) {
        $fechamento->transitar($tid, ANO, 'ano', 0, FechamentoMaquinaEstados::HOMOLOGADO, $adminId, 'Homologação após Conselho 2025');
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

println('  alunos aprovados pelo Conselho (B4):');
foreach (array_slice($aprovadosConselho, 0, 8) as $ex) {
    println('    ' . $ex['nome'] . ' (id ' . $ex['id'] . ', ' . $ex['turma'] . ')');
}

$adminUser = [
    'id' => $adminId,
    'nome' => $adminNome,
    'tipo' => 'admin_escola',
];
$vida = new \App\Modulos\VidaEscolar\Services\VidaEscolarService();
$faltando = $db->fetchAll(
    "SELECT ra.aluno_id, ra.turma_id, ra.situacao
     FROM resultado_academico ra
     LEFT JOIN boletim_fichas f
       ON f.aluno_id = ra.aluno_id AND f.turma_id = ra.turma_id AND f.ano_letivo = ra.ano_letivo
     WHERE ra.ano_letivo = :ano AND ra.periodo_tipo = 'ano' AND ra.status = 'homologado'
       AND ra.situacao IN ('aprovado_conselho', 'reprovado_rendimento', 'reprovado_frequencia')
       AND f.id IS NULL",
    ['ano' => ANO]
) ?: [];
foreach ($faltando as $row) {
    $aid = (int) $row['aluno_id'];
    $tid = (int) $row['turma_id'];
    $ok = $vida->garantirFicha($aid, $tid, ANO, $adminId);
    if (empty($ok['success'])) {
        continue;
    }
    $fid = (int) $ok['id'];
    $vida->sincronizarDeEventosGerados($aid, $adminUser, null, null, $fid, false, false);
    $vida->homologarFicha($fid, $adminUser);
}
$fichas = $db->fetchAll('SELECT id FROM boletim_fichas WHERE ano_letivo = :ano', ['ano' => ANO]) ?: [];
foreach ($fichas as $f) {
    $vida->sincronizarEscolarizacaoDaFicha((int) $f['id']);
}
println('  vida escolar 2025 sincronizada: ' . count($fichas) . ' fichas, ' . count($faltando) . ' criadas para conselho/retenção');
