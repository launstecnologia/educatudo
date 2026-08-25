<?php
/**
 * Completa notas do Colégio Educa para alunos que entraram depois do seed
 * (ex.: vagas preenchidas por seed_alunos_turmas.php).
 *
 * - 2025: 4 bimestres
 * - 2026: 1º e 2º bimestres
 *
 * Uso (container PHP):
 *   php scripts/preencher_notas_educa.php
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
require_once $basePath . '/app/Core/BaseController.php';
require_once $basePath . '/app/Models/Education/ClassDiary.php';
require_once $basePath . '/app/Models/Exams/ExamBlockManualGrade.php';
require_once $basePath . '/app/Models/System/BoletimConfig.php';
require_once $basePath . '/app/Controllers/Admin/BoletimConfigController.php';
require_once $basePath . '/app/Services/ResultadoHomologacaoService.php';

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

final class PreencherNotasEduca
{
    /** @var array<int, list<int>> */
    private array $alvos = [
        2025 => [1, 2, 3, 4],
        2026 => [1, 2],
    ];

    public function __construct(
        private $db,
        private ExamBlockManualGrade $notas,
        private BoletimConfig $boletim,
        private BoletimConfigController $boletimCtrl,
        private ClassDiary $diario,
        private ResultadoHomologacaoService $homologacao,
        private int $adminId
    ) {
        $this->boletim->ensureSchema();
    }

    public function executar(): int
    {
        println('== Completar notas Colégio Educa (2025 + 2026 B1/B2) ==');
        $notasNovas = 0;
        $freqNovas = 0;
        $boletins = 0;

        foreach ($this->alvos as $ano => $bims) {
            foreach ($bims as $bim) {
                println("  {$ano} B{$bim}");
                $notasNovas += $this->completarNotas($ano, $bim);
                $freqNovas += $this->completarFrequencia($ano, $bim);
                $boletins += $this->gerarBoletins($ano, $bim);
            }
        }

        println("  notas novas={$notasNovas}  frequências={$freqNovas}  boletins={$boletins}");
        $this->homologarPeriodos();
        $this->resumo();
        return 0;
    }

    private function completarNotas(int $ano, int $bim): int
    {
        $blocos = $this->db->fetchAll(
            "SELECT id, titulo FROM provas_blocos
             WHERE deleted_at IS NULL AND titulo LIKE 'EDUCA%'
               AND ano_letivo = :ano AND bimestre = :bim",
            ['ano' => $ano, 'bim' => $bim]
        ) ?: [];
        $inseridas = 0;
        foreach ($blocos as $bloco) {
            $blocoId = (int) $bloco['id'];
            $tipoEv = $this->tipoDoTitulo((string) $bloco['titulo']);
            $turmas = $this->db->fetchAll(
                'SELECT turma_id FROM provas_blocos_turmas WHERE bloco_id = :id',
                ['id' => $blocoId]
            ) ?: [];
            $profs = $this->db->fetchAll(
                'SELECT professor_id, materia_id FROM provas_blocos_professores WHERE bloco_id = :id',
                ['id' => $blocoId]
            ) ?: [];
            foreach ($turmas as $t) {
                $turmaId = (int) $t['turma_id'];
                $alunos = $this->alunosDaTurma($turmaId, $ano);
                if ($alunos === [] || $profs === []) {
                    continue;
                }
                foreach ($profs as $p) {
                    $pid = (int) $p['professor_id'];
                    $mid = (int) $p['materia_id'];
                    $jaTem = $this->alunosComNota($blocoId, $pid, $mid, $turmaId);
                    $linhas = [];
                    foreach ($alunos as $al) {
                        $aid = (int) $al['id'];
                        if (isset($jaTem[$aid])) {
                            continue;
                        }
                        $linhas[] = [
                            'turma_id' => $turmaId,
                            'aluno_id' => $aid,
                            'nota' => $this->notaDoAluno($aid, $bim, $tipoEv),
                        ];
                    }
                    if ($linhas === []) {
                        continue;
                    }
                    $this->notas->upsertLinhas($blocoId, $pid, $mid, $linhas);
                    $inseridas += count($linhas);
                }
            }
        }
        println("    notas lançadas: {$inseridas}");
        return $inseridas;
    }

    private function completarFrequencia(int $ano, int $bim): int
    {
        $periodo = $this->diario->periodoDoBimestre($ano, $bim);
        $stmt = $this->db->query(
            "INSERT IGNORE INTO diario_frequencias (diario_aula_id, aluno_id, situacao, origem)
             SELECT a.id, m.aluno_id, 'presente', 'ajuste_gestao'
             FROM diario_aulas a
             INNER JOIN matricula m ON m.turma_id = a.turma_id
             INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
             WHERE a.data_aula BETWEEN :ini AND :fim
               AND a.status = 'finalizada'
               AND m.status IN ('ativa', 'concluido', 'transferido')",
            [
                'ano' => $ano,
                'ini' => $periodo['inicio'],
                'fim' => $periodo['fim'],
            ]
        );
        $n = $stmt ? (int) $stmt->rowCount() : 0;
        println("    frequências inseridas: {$n}");
        return $n;
    }

    private function gerarBoletins(int $ano, int $bim): int
    {
        $regras = $this->db->fetchAll(
            'SELECT id FROM boletim_regras WHERE ano_letivo = :ano AND bimestre = :bim AND codigo LIKE :c',
            ['ano' => $ano, 'bim' => $bim, 'c' => 'educa-%']
        ) ?: [];
        $periodoRef = $ano . '-B' . $bim;
        $periodo = $this->diario->periodoDoBimestre($ano, $bim);
        $gerados = 0;
        foreach ($regras as $row) {
            $regraId = (int) $row['id'];
            $regra = $this->boletim->getRuleById($regraId);
            if (!$regra) {
                continue;
            }
            $turmaIds = json_decode((string) ($regra['turmas_ids'] ?? '[]'), true);
            if (!is_array($turmaIds) || $turmaIds === []) {
                continue;
            }
            foreach ($turmaIds as $turmaId) {
                $turmaId = (int) $turmaId;
                foreach ($this->alunosDaTurma($turmaId, $ano) as $al) {
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
        }
        println("    boletins gerados: {$gerados}");
        return $gerados;
    }

    private function homologarPeriodos(): void
    {
        $turmas = $this->db->fetchAll(
            'SELECT id, ano_letivo FROM turmas WHERE ativo = 1 AND ano_letivo IN (2025, 2026)'
        ) ?: [];
        foreach ($turmas as $t) {
            $turmaId = (int) $t['id'];
            $ano = (int) $t['ano_letivo'];
            $periodos = $ano === 2025
                ? [['ano', 0], ['bimestre', 1], ['bimestre', 2], ['bimestre', 3], ['bimestre', 4]]
                : [['bimestre', 1], ['bimestre', 2]];
            foreach ($periodos as [$tipo, $num]) {
                $res = $this->homologacao->homologarTurma($turmaId, $ano, $tipo, $num, $this->adminId, [], true);
                $ok = !empty($res['success']);
                $det = $ok
                    ? ('homologados=' . (int) ($res['homologados'] ?? 0) . ' ignorados=' . (int) ($res['ignorados'] ?? 0))
                    : (string) ($res['error'] ?? 'falha');
                println("    homologar turma={$turmaId} {$ano} {$tipo}#{$num} " . ($ok ? 'OK' : 'FAIL') . " {$det}");
            }
        }
    }

    private function resumo(): void
    {
        $rows = $this->db->fetchAll(
            "SELECT r.ano_letivo, r.bimestre, COUNT(DISTINCT g.aluno_id) alunos
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             WHERE g.preview = 0 AND r.codigo LIKE 'educa-%'
               AND ((r.ano_letivo = 2025 AND r.bimestre BETWEEN 1 AND 4)
                 OR (r.ano_letivo = 2026 AND r.bimestre IN (1, 2)))
             GROUP BY r.ano_letivo, r.bimestre
             ORDER BY r.ano_letivo, r.bimestre"
        ) ?: [];
        println('  boletim por período:');
        foreach ($rows as $r) {
            println('    ' . $r['ano_letivo'] . ' B' . $r['bimestre'] . ': ' . $r['alunos'] . ' alunos');
        }
    }

    /** @return list<array{id:int}> */
    private function alunosDaTurma(int $turmaId, int $ano): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT a.id
             FROM alunos a
             INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t
             INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
             WHERE m.status IN ('ativa', 'concluido', 'transferido')",
            ['t' => $turmaId, 'ano' => $ano]
        ) ?: [];
    }

    /** @return array<int, true> */
    private function alunosComNota(int $blocoId, int $professorId, int $materiaId, int $turmaId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT aluno_id FROM provas_blocos_notas_lancadas
             WHERE bloco_id = :b AND professor_id = :p AND materia_id = :m AND turma_id = :t
               AND nota IS NOT NULL',
            ['b' => $blocoId, 'p' => $professorId, 'm' => $materiaId, 't' => $turmaId]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['aluno_id']] = true;
        }
        return $out;
    }

    private function tipoDoTitulo(string $titulo): string
    {
        if (stripos($titulo, 'Prova Bimestral 2') !== false) {
            return 'p2';
        }
        if (stripos($titulo, 'Atividade') !== false) {
            return 'atv';
        }
        if (stripos($titulo, 'Recupera') !== false) {
            return 'rec';
        }
        return 'p1';
    }

    private function notaDoAluno(int $alunoId, int $bim, string $tipoEv): float
    {
        $base = [8.0, 8.2, 8.5, 8.3][$bim - 1] ?? 8.0;
        $base += ($alunoId % 7) * 0.1;
        if ($tipoEv === 'p2') {
            $base = min(10, $base + 0.3);
        } elseif ($tipoEv === 'atv') {
            $base = max(0, $base - 0.4);
        } elseif ($tipoEv === 'rec') {
            $base = 8.0;
        }
        return round(min(10, $base), 1);
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

    public static function boot($db): self
    {
        $admin = $db->fetch('SELECT id FROM usuarios WHERE email = :e LIMIT 1', ['e' => 'admin@educa.local']);
        $adminId = (int) ($admin['id'] ?? 1);
        return new self(
            $db,
            new ExamBlockManualGrade(),
            new BoletimConfig(),
            self::montarBoletimController(),
            new ClassDiary(),
            new ResultadoHomologacaoService(),
            $adminId
        );
    }
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

try {
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
    exit(PreencherNotasEduca::boot(Database::getInstance())->executar());
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
