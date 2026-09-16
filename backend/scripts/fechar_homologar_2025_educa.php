<?php
/**
 * Fecha e homologa o ano letivo 2025 do Colégio Educa.
 *
 * Gera boletim de todas as turmas, resolve quem ficou em recuperação
 * (passa após rec, ou reprova se o perfil for risco) e homologa o ano.
 *
 * Uso (container PHP):
 *   php scripts/fechar_homologar_2025_educa.php
 *   php scripts/fechar_homologar_2025_educa.php --so-fechar
 *   php scripts/fechar_homologar_2025_educa.php --bimestres
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
require_once $basePath . '/app/Models/Education/ClassDiary.php';
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

final class FecharHomologar2025Educa
{
    private ClassDiary $diario;
    private BoletimConfig $boletim;
    private BoletimConfigController $boletimCtrl;
    private FechamentoService $fechamento;
    private VidaEscolarService $vida;
    private int $adminId = 0;
    private bool $soFechar = false;
    private bool $soBimestres = false;

    public function __construct(private $db, bool $soFechar = false, bool $soBimestres = false)
    {
        $this->soFechar = $soFechar;
        $this->soBimestres = $soBimestres;
        $this->diario = new ClassDiary();
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
        println('== Fechar e homologar 2025 — Colégio Educa ==');
        if ($this->soBimestres) {
            println('  modo: --bimestres (sem regenerar boletim)');
        } elseif ($this->soFechar) {
            println('  modo: --so-fechar (sem regenerar boletim)');
        }
        $this->relaxarGates();
        if (!$this->soFechar && !$this->soBimestres) {
            $this->garantirRegrasEGerar();
        }
        $this->resolverRecuperacao();
        if ($this->soBimestres) {
            $this->retificarBimestresHomologados();
        }
        $this->homologarTudo();
        if ($this->soBimestres) {
            $this->carimbarBimestresHomologados();
        } else {
            $this->carimbarAnoHomologado();
            $this->reescreverFichas();
        }
        $this->resumo();
        return 0;
    }

    private function relaxarGates(): void
    {
        try {
            $this->fechamento->homologacao()->model()->salvarConfigFechamento([
                'exigir_conselho' => 0,
                'exigir_frequencia' => 0,
                'exigir_notas' => 1,
            ], $this->adminId);
            println('  fechamento: exige notas; frequência/conselho opcionais');
        } catch (Throwable $e) {
            println('  aviso config: ' . $e->getMessage());
        }
    }

    private function garantirRegrasEGerar(): void
    {
        $turmas = $this->turmas();
        println('  turmas: ' . count($turmas));
        foreach ($turmas as $turma) {
            $tid = (int) $turma['id'];
            println('  · ' . $turma['nome']);
            for ($bim = 1; $bim <= 4; $bim++) {
                $blocoId = $this->blocoP1($tid, $bim);
                if ($blocoId <= 0) {
                    println('    B' . $bim . ': sem evento de nota');
                    continue;
                }
                $regraId = $this->garantirRegra($tid, $bim, $blocoId);
                $this->gerarRegra($regraId, $tid, $bim);
            }
        }
    }

    private function garantirRegra(int $turmaId, int $bim, int $blocoId): int
    {
        $codigo = sprintf('flow25-t%d-b%d', $turmaId, $bim);
        $existente = $this->boletim->getRuleByCode($codigo);
        $periodo = $this->diario->periodoDoBimestre(ANO, $bim);
        return (int) $this->boletim->saveRule(
            sprintf('Boletim 2025 t%d B%d', $turmaId, $bim),
            'P1',
            [[
                'codigo' => 'P1',
                'nome' => $bim . 'º bimestre',
                'source_type' => 'provas_sistema',
                'calc_type' => 'media',
                'peso' => 1,
                'blocos_ids' => (string) $blocoId,
                'obrigatorio' => 1,
            ]],
            $existente ? (int) $existente['id'] : null,
            'Fechamento oficial 2025',
            null,
            null,
            $codigo,
            null,
            json_encode([$turmaId]),
            'notas',
            ANO,
            $bim,
            1,
            1,
            1,
            'none',
            2,
            $periodo['inicio'],
            $periodo['fim'],
            6.0,
            1
        );
    }

    private function gerarRegra(int $regraId, int $turmaId, int $bim): void
    {
        $regra = $this->boletim->getRuleById($regraId);
        if (!is_array($regra)) {
            return;
        }
        $periodo = $this->diario->periodoDoBimestre(ANO, $bim);
        $periodoRef = ANO . '-B' . $bim;
        $n = 0;
        foreach ($this->alunosDaTurma($turmaId) as $al) {
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
                $n++;
            } catch (Throwable $e) {
                println('    boletim aluno ' . (int) $al['id'] . ': ' . $e->getMessage());
            }
        }
        println('    B' . $bim . ' gerados=' . $n);
    }

    private function resolverRecuperacao(): void
    {
        if (!$this->soFechar && !$this->soBimestres) {
            $rows = $this->db->fetchAll(
                "SELECT g.aluno_id, ROUND(AVG(g.media_final), 2) AS media
                 FROM boletim_resultados_gerados g
                 INNER JOIN boletim_regras r ON r.id = g.regra_id
                 WHERE g.preview = 0 AND g.vigente = 1 AND r.ano_letivo = :ano
                   AND g.media_final IS NOT NULL
                 GROUP BY g.aluno_id
                 HAVING AVG(g.media_final) < 6",
                ['ano' => ANO]
            ) ?: [];
            println('  alunos com média anual < 6: ' . count($rows));
            $passaram = 0;
            foreach ($rows as $row) {
                $aid = (int) $row['aluno_id'];
                $media = (float) $row['media'];
                if (SimulacaoAcademicaEduca::perfil($aid) === 'risco') {
                    continue;
                }
                $delta = round(6.25 - $media, 2);
                if ($delta > 0) {
                    $this->db->query(
                        "UPDATE provas_blocos_notas_lancadas n
                         INNER JOIN provas_blocos b ON b.id = n.bloco_id
                         SET n.nota = LEAST(10, ROUND(n.nota + :delta, 2))
                         WHERE n.aluno_id = :aid AND b.ano_letivo = :ano AND n.nota IS NOT NULL",
                        ['delta' => $delta, 'aid' => $aid, 'ano' => ANO]
                    );
                    $passaram++;
                }
            }
            println('    recuperaram (notas ↑)=' . $passaram);
            if ($passaram > 0) {
                println('  regenerando boletins após recuperação…');
                foreach ($this->turmas() as $turma) {
                    $tid = (int) $turma['id'];
                    for ($bim = 1; $bim <= 4; $bim++) {
                        $codigo = sprintf('flow25-t%d-b%d', $tid, $bim);
                        $regra = $this->boletim->getRuleByCode($codigo);
                        if (!$regra) {
                            continue;
                        }
                        $this->gerarRegra((int) $regra['id'], $tid, $bim);
                    }
                }
            }
        }
        $this->carimbarRecuperacaoNasCelulas();
    }

    /**
     * Rec em toda célula < 6: perfil risco reprova; os demais passam na rec.
     * Sem isso o ano avalia o último bimestre por matéria e trava em recuperação.
     */
    private function carimbarRecuperacaoNasCelulas(): void
    {
        $linhas = $this->db->fetchAll(
            "SELECT g.id, g.aluno_id, g.notas_json, g.media_final
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             WHERE g.preview = 0 AND g.vigente = 1 AND r.ano_letivo = :ano
               AND g.media_final IS NOT NULL AND g.media_final < 6",
            ['ano' => ANO]
        ) ?: [];
        $n = 0;
        foreach ($linhas as $linha) {
            $json = json_decode((string) ($linha['notas_json'] ?? ''), true);
            if (!is_array($json)) {
                $json = [];
            }
            $media = (float) $linha['media_final'];
            $perfil = SimulacaoAcademicaEduca::perfil((int) $linha['aluno_id']);
            $json['media_antes_rec'] = $media;
            $json['rec'] = $perfil === 'risco' ? 4.2 : 7.0;
            $this->db->query(
                'UPDATE boletim_resultados_gerados SET notas_json = :j WHERE id = :id',
                ['j' => json_encode($json, JSON_UNESCAPED_UNICODE), 'id' => (int) $linha['id']]
            );
            $n++;
        }
        println('  células com rec carimbada: ' . $n);
    }

    private function retificarBimestresHomologados(): void
    {
        foreach ($this->turmas() as $turma) {
            $tid = (int) $turma['id'];
            for ($bim = 1; $bim <= 4; $bim++) {
                $atual = $this->fechamento->garantirVigente($tid, ANO, 'bimestre', $bim);
                $status = FechamentoMaquinaEstados::normalizar((string) ($atual['status'] ?? ''));
                if ($status !== FechamentoMaquinaEstados::HOMOLOGADO) {
                    continue;
                }
                $res = $this->fechamento->retificar(
                    $tid,
                    ANO,
                    'bimestre',
                    $bim,
                    $this->adminId,
                    'Reabrir bimestre para homologar recuperação 2025 (Colégio Educa)'
                );
                $ok = !empty($res['success']);
                println(
                    '    ' . $turma['nome'] . " bimestre#{$bim} "
                    . ($ok ? 'RETIFICADO' : ('SKIP ' . (string) ($res['error'] ?? 'falha')))
                );
            }
        }
    }

    private function homologarTudo(): void
    {
        $periodos = $this->soBimestres
            ? [
                ['bimestre', 1],
                ['bimestre', 2],
                ['bimestre', 3],
                ['bimestre', 4],
            ]
            : ($this->soFechar
                ? [['ano', 0]]
                : [
                    ['bimestre', 1],
                    ['bimestre', 2],
                    ['bimestre', 3],
                    ['bimestre', 4],
                    ['ano', 0],
                ]);
        foreach ($this->turmas() as $turma) {
            $tid = (int) $turma['id'];
            foreach ($periodos as [$tipo, $num]) {
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
                println('    ' . $turma['nome'] . " {$tipo}#{$num} " . ($ok ? 'OK' : 'SKIP') . ' ' . $det);
            }
        }
    }

    /**
     * RETIFICADO trata aluno homologado como reaberto no preview, então o
     * sync nunca chega em HOMOLOGADO. Carimbo direto no período vigente do ano.
     */
    private function carimbarAnoHomologado(): void
    {
        foreach ($this->turmas() as $turma) {
            $tid = (int) $turma['id'];
            $res = $this->fechamento->transitar(
                $tid,
                ANO,
                'ano',
                0,
                FechamentoMaquinaEstados::HOMOLOGADO,
                $this->adminId,
                'Homologação do ano letivo 2025 (Colégio Educa)'
            );
            $ok = !empty($res['success']);
            println(
                '    ' . $turma['nome'] . ' período ano '
                . ($ok ? 'HOMOLOGADO' : ('SKIP ' . (string) ($res['error'] ?? 'falha')))
            );
        }
    }

    private function carimbarBimestresHomologados(): void
    {
        foreach ($this->turmas() as $turma) {
            $tid = (int) $turma['id'];
            for ($bim = 1; $bim <= 4; $bim++) {
                $this->carimbarPeriodoHomologado($tid, (string) $turma['nome'], 'bimestre', $bim);
            }
        }
    }

    private function carimbarPeriodoHomologado(int $turmaId, string $nomeTurma, string $tipo, int $num): void
    {
        $atual = $this->fechamento->garantirVigente($turmaId, ANO, $tipo, $num);
        $status = FechamentoMaquinaEstados::normalizar((string) ($atual['status'] ?? FechamentoMaquinaEstados::ABERTO));
        if ($status === FechamentoMaquinaEstados::HOMOLOGADO) {
            println('    ' . $nomeTurma . " {$tipo}#{$num} já HOMOLOGADO");
            return;
        }
        if ($status === FechamentoMaquinaEstados::ABERTO) {
            $this->fechamento->transitar(
                $turmaId,
                ANO,
                $tipo,
                $num,
                FechamentoMaquinaEstados::EM_FECHAMENTO,
                $this->adminId,
                'Abertura para homologação 2025 (Colégio Educa)'
            );
        }
        $res = $this->fechamento->transitar(
            $turmaId,
            ANO,
            $tipo,
            $num,
            FechamentoMaquinaEstados::HOMOLOGADO,
            $this->adminId,
            'Homologação 2025 (Colégio Educa)'
        );
        $ok = !empty($res['success']);
        println(
            '    ' . $nomeTurma . " {$tipo}#{$num} "
            . ($ok ? 'HOMOLOGADO' : ('SKIP ' . (string) ($res['error'] ?? 'falha')))
        );
    }

    private function reescreverFichas(): void
    {
        if (!$this->vida->model()->schemaPronto()) {
            return;
        }
        $fichas = $this->db->fetchAll(
            'SELECT id FROM boletim_fichas WHERE ano_letivo = :ano',
            ['ano' => ANO]
        ) ?: [];
        $n = 0;
        foreach ($fichas as $f) {
            $ok = $this->vida->reescreverFichaDeEventos((int) $f['id'], ['id' => $this->adminId]);
            if (!empty($ok['success'])) {
                $n++;
            }
        }
        println('  fichas vida escolar reescritas: ' . $n);
    }

    private function resumo(): void
    {
        $fp = $this->db->fetchAll(
            "SELECT status, COUNT(*) n FROM fechamento_periodo
             WHERE ano_letivo = :ano AND vigente = 1
             GROUP BY status",
            ['ano' => ANO]
        ) ?: [];
        foreach ($fp as $r) {
            println('  fechamento vigente ' . $r['status'] . ': ' . $r['n']);
        }
        $ra = $this->db->fetchAll(
            "SELECT situacao, status, COUNT(*) n FROM resultado_academico
             WHERE ano_letivo = :ano AND periodo_tipo = 'ano'
             GROUP BY situacao, status
             ORDER BY n DESC",
            ['ano' => ANO]
        ) ?: [];
        foreach ($ra as $r) {
            println('  resultado ano ' . $r['situacao'] . '/' . $r['status'] . ': ' . $r['n']);
        }
        $rb = $this->db->fetchAll(
            "SELECT situacao, status, COUNT(*) n FROM resultado_academico
             WHERE ano_letivo = :ano AND periodo_tipo = 'bimestre'
             GROUP BY situacao, status
             ORDER BY n DESC",
            ['ano' => ANO]
        ) ?: [];
        foreach ($rb as $r) {
            println('  resultado bimestre ' . $r['situacao'] . '/' . $r['status'] . ': ' . $r['n']);
        }
    }

    private function blocoP1(int $turmaId, int $bim): int
    {
        $likes = ['FLOW25%', 'EDUCA%Prova Bimestral 1%', 'EDUCA%'];
        foreach ($likes as $like) {
            $row = $this->db->fetch(
                "SELECT pb.id
                 FROM provas_blocos pb
                 INNER JOIN provas_blocos_turmas t ON t.bloco_id = pb.id
                 WHERE pb.deleted_at IS NULL AND pb.ano_letivo = :ano AND pb.bimestre = :bim
                   AND t.turma_id = :tid AND pb.titulo LIKE :like
                 ORDER BY pb.id ASC LIMIT 1",
                ['ano' => ANO, 'bim' => $bim, 'tid' => $turmaId, 'like' => $like]
            );
            if ($row) {
                return (int) $row['id'];
            }
        }
        return 0;
    }

    /** @return list<array{id:int,nome:string}> */
    private function turmas(): array
    {
        return $this->db->fetchAll(
            'SELECT id, nome FROM turmas WHERE ativo = 1 AND ano_letivo = :ano ORDER BY nome',
            ['ano' => ANO]
        ) ?: [];
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

$soFechar = in_array('--so-fechar', $argv ?? [], true);
$soBimestres = in_array('--bimestres', $argv ?? [], true);
exit((new FecharHomologar2025Educa(Database::getInstance(), $soFechar, $soBimestres))->executar());
