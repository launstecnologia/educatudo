<?php
/**
 * Recalibra o Colégio Educa 2025: notas por matéria, faltas no diário e
 * boletins diferentes entre alunos. O seed original deixava tudo igual.
 *
 * Uso (container PHP):
 *   php scripts/realistizar_2025_educa.php
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
require_once $basePath . '/app/Core/BaseController.php';
require_once $basePath . '/app/Core/LayoutHelper.php';
require_once $basePath . '/app/Models/Education/ClassDiary.php';
require_once $basePath . '/app/Models/Education/SchoolAbsence.php';
require_once $basePath . '/app/Models/System/BoletimConfig.php';
require_once $basePath . '/app/Controllers/Admin/BoletimConfigController.php';
require_once $basePath . '/app/Modulos/fechamento/Services/FechamentoService.php';
require_once $basePath . '/app/Services/ResultadoHomologacaoService.php';
require_once $basePath . '/app/Modulos/vida-escolar/Services/VidaEscolarService.php';
require_once $basePath . '/scripts/lib/SimulacaoAcademicaEduca.php';

use App\Modulos\VidaEscolar\Services\VidaEscolarService;

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

final class Realistizar2025Educa
{
    private ClassDiary $diario;
    private SchoolAbsence $faltas;
    private BoletimConfig $boletim;
    private BoletimConfigController $boletimCtrl;
    private FechamentoService $fechamento;
    private VidaEscolarService $vida;
    private int $adminId = 0;

    public function __construct(private $db)
    {
        $this->diario = new ClassDiary();
        $this->faltas = new SchoolAbsence();
        $this->boletim = new BoletimConfig();
        $this->boletim->ensureSchema();
        $this->boletimCtrl = self::montarBoletimController();
        $this->fechamento = new FechamentoService();
        $this->vida = new VidaEscolarService();
        $admin = $this->db->fetch(
            "SELECT id FROM usuarios WHERE tipo = 'admin_escola' AND ativo = 1 ORDER BY id ASC LIMIT 1"
        );
        $this->adminId = (int) ($admin['id'] ?? 1);
    }

    public function executar(): int
    {
        println('== Realistizar 2025 — Colégio Educa ==');
        $this->atualizarNotas();
        $this->atualizarFrequencias();
        $this->reconsolidarFaltas();
        $this->gerarBoletins();
        $this->rehomologar();
        $this->reescreverFichas();
        $this->resumo();
        return 0;
    }

    private function atualizarNotas(): void
    {
        $rows = $this->db->fetchAll(
            "SELECT n.id, n.aluno_id, n.materia_id, b.bimestre, b.titulo
             FROM provas_blocos_notas_lancadas n
             INNER JOIN provas_blocos b ON b.id = n.bloco_id
             WHERE b.ano_letivo = :ano AND b.deleted_at IS NULL AND n.nota IS NOT NULL",
            ['ano' => ANO]
        ) ?: [];
        println('  notas a recalibrar: ' . count($rows));
        $pdo = $this->db->getPdo();
        $n = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $sql = 'UPDATE provas_blocos_notas_lancadas SET nota = CASE id';
            $params = [];
            $ids = [];
            foreach ($chunk as $i => $row) {
                $nota = SimulacaoAcademicaEduca::nota(
                    (int) $row['aluno_id'],
                    (int) $row['materia_id'],
                    (int) $row['bimestre'],
                    SimulacaoAcademicaEduca::tipoDoTitulo((string) $row['titulo'])
                );
                $sql .= " WHEN :id{$i} THEN :n{$i}";
                $params['id' . $i] = (int) $row['id'];
                $params['n' . $i] = $nota;
                $params['idin' . $i] = (int) $row['id'];
                $ids[] = ':idin' . $i;
            }
            $sql .= ' END WHERE id IN (' . implode(',', $ids) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $n += count($chunk);
        }
        println('    notas gravadas: ' . $n);
    }

    private function atualizarFrequencias(): void
    {
        $rows = $this->db->fetchAll(
            "SELECT df.id, df.aluno_id, da.materia_id, da.data_aula
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE da.data_aula BETWEEN :ini AND :fim
               AND da.status <> 'cancelada'",
            ['ini' => ANO . '-01-01', 'fim' => ANO . '-12-31']
        ) ?: [];
        println('  frequências a recalibrar: ' . count($rows));
        $pdo = $this->db->getPdo();
        $n = 0;
        $porSit = [];
        foreach (array_chunk($rows, 200) as $chunk) {
            $sql = 'UPDATE diario_frequencias SET situacao = CASE id';
            $params = [];
            $ids = [];
            foreach ($chunk as $i => $row) {
                $data = (string) $row['data_aula'];
                $bim = max(1, min(4, (int) ceil(((int) substr($data, 5, 2)) / 3)));
                $sit = SimulacaoAcademicaEduca::situacaoFrequencia(
                    (int) $row['aluno_id'],
                    (int) $row['materia_id'],
                    $bim,
                    $data
                );
                $porSit[$sit] = ($porSit[$sit] ?? 0) + 1;
                $sql .= " WHEN :id{$i} THEN :s{$i}";
                $params['id' . $i] = (int) $row['id'];
                $params['s' . $i] = $sit;
                $params['idin' . $i] = (int) $row['id'];
                $ids[] = ':idin' . $i;
            }
            $sql .= ' END WHERE id IN (' . implode(',', $ids) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $n += count($chunk);
        }
        $det = [];
        foreach ($porSit as $k => $v) {
            $det[] = $k . '=' . $v;
        }
        println('    frequências gravadas: ' . $n . ' (' . implode(', ', $det) . ')');
    }

    private function reconsolidarFaltas(): void
    {
        $eventos = $this->db->fetchAll(
            'SELECT id, bimestre FROM faltas_eventos WHERE ativo = 1 AND ano_letivo = :ano',
            ['ano' => ANO]
        ) ?: [];
        $total = 0;
        foreach ($eventos as $ev) {
            $eventoId = (int) $ev['id'];
            $bim = 0;
            if (preg_match('/([1-4])/', (string) $ev['bimestre'], $m)) {
                $bim = (int) $m[1];
            }
            if ($bim < 1 || $bim > 4) {
                continue;
            }
            $periodo = $this->diario->periodoDoBimestre(ANO, $bim);
            $rows = $this->db->fetchAll(
                "SELECT df.aluno_id, da.materia_id, COUNT(*) AS n
                 FROM diario_frequencias df
                 INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
                 WHERE df.situacao = 'falta'
                   AND da.status <> 'cancelada'
                   AND da.data_aula BETWEEN :ini AND :fim
                 GROUP BY df.aluno_id, da.materia_id",
                ['ini' => $periodo['inicio'], 'fim' => $periodo['fim']]
            ) ?: [];
            $payload = [];
            foreach ($rows as $r) {
                $aid = (int) $r['aluno_id'];
                $mid = (int) $r['materia_id'];
                if ($aid <= 0 || $mid <= 0) {
                    continue;
                }
                $payload[$aid][$mid] = (string) ((int) $r['n']);
            }
            if ($payload === []) {
                continue;
            }
            $this->faltas->upsertLancamentos($eventoId, $payload, [], $this->adminId);
            $total += count($payload);
        }
        println('  lançamentos de falta: ' . $total . ' alunos (eventos com falta > 0)');
    }

    private function gerarBoletins(): void
    {
        $regras = $this->db->fetchAll(
            "SELECT id, codigo, bimestre FROM boletim_regras
             WHERE ano_letivo = :ano AND bimestre BETWEEN 1 AND 4
               AND (codigo LIKE 'educa-%' OR codigo LIKE 'flow25-%')",
            ['ano' => ANO]
        ) ?: [];
        println('  regras de boletim: ' . count($regras));
        $gerados = 0;
        foreach ($regras as $row) {
            $regraId = (int) $row['id'];
            $bim = (int) $row['bimestre'];
            $regra = $this->boletim->getRuleById($regraId);
            if (!$regra) {
                continue;
            }
            $turmaIds = json_decode((string) ($regra['turmas_ids'] ?? '[]'), true);
            if (!is_array($turmaIds) || $turmaIds === []) {
                continue;
            }
            $periodoRef = ANO . '-B' . $bim;
            $periodo = $this->diario->periodoDoBimestre(ANO, $bim);
            foreach ($turmaIds as $turmaId) {
                $turmaId = (int) $turmaId;
                $alunos = $this->alunosDaTurma($turmaId);
                foreach ($alunos as $al) {
                    try {
                        $sim = $this->boletimCtrl->simularRegraAluno(
                            $regra,
                            (int) $al['id'],
                            $periodoRef,
                            $periodo['inicio'],
                            $periodo['fim']
                        );
                        $matriz = $sim['matriz_materias'] ?? null;
                        $colunas = is_array($matriz) && is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
                        $linhas = is_array($matriz) && is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
                        $this->boletim->replaceGeneratedResultsForAluno(
                            $regraId,
                            (int) $al['id'],
                            $periodoRef,
                            $periodo['inicio'],
                            $periodo['fim'],
                            $colunas,
                            $linhas,
                            false
                        );
                        $gerados++;
                    } catch (Throwable $e) {
                        println('    boletim falhou aluno ' . (int) $al['id'] . ': ' . $e->getMessage());
                    }
                }
            }
            println('    ' . $row['codigo'] . ' ok');
        }
        println('    boletins gerados: ' . $gerados);
    }

    private function rehomologar(): void
    {
        $turmas = $this->db->fetchAll(
            'SELECT id, nome FROM turmas WHERE ativo = 1 AND ano_letivo = :ano ORDER BY nome',
            ['ano' => ANO]
        ) ?: [];
        $just = 'Recalibração do seed 2025 (notas e faltas com variação real).';
        $periodos = [
            ['bimestre', 1],
            ['bimestre', 2],
            ['bimestre', 3],
            ['bimestre', 4],
            ['ano', 0],
        ];
        foreach ($turmas as $t) {
            $tid = (int) $t['id'];
            foreach ($periodos as [$tipo, $num]) {
                $vigente = $this->fechamento->model()->findVigente($tid, ANO, $tipo, $num);
                $st = strtoupper((string) ($vigente['status'] ?? ''));
                if ($st === 'HOMOLOGADO') {
                    $ret = $this->fechamento->retificar($tid, ANO, $tipo, $num, $this->adminId, $just);
                    if (empty($ret['success'])) {
                        println('    retificar ' . $t['nome'] . " {$tipo}#{$num}: " . ($ret['error'] ?? 'falha'));
                        continue;
                    }
                }
                $res = $this->fechamento->homologacao()->homologarTurma(
                    $tid,
                    ANO,
                    $tipo,
                    $num,
                    $this->adminId,
                    [],
                    true
                );
                $ok = !empty($res['success']);
                $det = $ok
                    ? ('homologados=' . (int) ($res['homologados'] ?? 0) . ' ignorados=' . (int) ($res['ignorados'] ?? 0))
                    : (string) ($res['error'] ?? 'falha');
                println('    ' . $t['nome'] . " {$tipo}#{$num} " . ($ok ? 'OK' : 'SKIP') . ' ' . $det);
            }
        }
    }

    private function reescreverFichas(): void
    {
        if (!$this->vida->model()->schemaPronto()) {
            println('  vida escolar: schema ausente');
            return;
        }
        $fichas = $this->db->fetchAll(
            'SELECT id FROM boletim_fichas WHERE ano_letivo = :ano',
            ['ano' => ANO]
        ) ?: [];
        println('  fichas vida escolar: ' . count($fichas));
        $n = 0;
        foreach ($fichas as $f) {
            $ok = $this->vida->reescreverFichaDeEventos((int) $f['id'], ['id' => $this->adminId]);
            if (!empty($ok['success'])) {
                $n++;
            }
        }
        println('    reescritas: ' . $n);
    }

    private function resumo(): void
    {
        $notas = $this->db->fetch(
            "SELECT MIN(n.nota) mn, MAX(n.nota) mx, ROUND(AVG(n.nota),2) md, COUNT(DISTINCT n.nota) distintos
             FROM provas_blocos_notas_lancadas n
             INNER JOIN provas_blocos b ON b.id = n.bloco_id
             WHERE b.ano_letivo = :ano AND n.nota IS NOT NULL",
            ['ano' => ANO]
        );
        $freq = $this->db->fetchAll(
            "SELECT df.situacao, COUNT(*) c
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE da.data_aula BETWEEN :ini AND :fim
             GROUP BY df.situacao",
            ['ini' => ANO . '-01-01', 'fim' => ANO . '-12-31']
        ) ?: [];
        println('  notas 2025 min=' . ($notas['mn'] ?? '?') . ' max=' . ($notas['mx'] ?? '?')
            . ' média=' . ($notas['md'] ?? '?') . ' valores distintos=' . ($notas['distintos'] ?? '?'));
        foreach ($freq as $r) {
            println('  frequência ' . $r['situacao'] . ': ' . $r['c']);
        }
        println('Pronto. Recarregue a Vida escolar do aluno — notas e faltas devem variar por componente.');
    }

    /** @return list<array{id:int}> */
    private function alunosDaTurma(int $turmaId): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT a.id
             FROM alunos a
             INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t
             INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
             WHERE m.status IN ('ativa', 'concluido', 'transferido')",
            ['t' => $turmaId, 'ano' => ANO]
        ) ?: [];
    }

    private static function montarBoletimController(): BoletimConfigController
    {
        $ref = new ReflectionClass(BoletimConfigController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('boletimConfig');
        $prop->setAccessible(true);
        $cfg = new BoletimConfig();
        $cfg->ensureSchema();
        $prop->setValue($ctrl, $cfg);
        return $ctrl;
    }
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

exit((new Realistizar2025Educa(Database::getInstance()))->executar());
