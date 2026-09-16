<?php
/**
 * Seed Colégio Educa — fluxo completo de 2025 (notas → boletim → fechamento → histórico).
 *
 * Idempotente. Usa turmas/alunos já existentes; cria o que faltar
 * (quadro, modelo, lançamentos, boletim oficial, painel de fechamento, rascunho de histórico).
 *
 * Uso (container PHP, após init_colegio_educa.php):
 *   php scripts/seed_fluxo_fechamento_2025_educa.php
 *   php scripts/seed_fluxo_fechamento_2025_educa.php --todas
 *   php scripts/seed_fluxo_fechamento_2025_educa.php --todas --concluir
 * Recalibrar notas/faltas (variação real):
 *   php scripts/realistizar_2025_educa.php
 * Fechar e homologar o ano:
 *   php scripts/fechar_homologar_2025_educa.php
 *
 * Se ainda não houver turma 2025:
 *   php scripts/popular_colegio_educa.php
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
require_once $basePath . '/app/Models/Exams/ExamBlock.php';
require_once $basePath . '/app/Models/Exams/ExamBlockManualGrade.php';
require_once $basePath . '/app/Models/Exams/ExamEvaluationType.php';
require_once $basePath . '/app/Models/System/BoletimConfig.php';
require_once $basePath . '/app/Controllers/Admin/BoletimConfigController.php';
require_once $basePath . '/app/Modulos/grupos-regras-notas/Services/GrupoRegrasNotasService.php';
require_once $basePath . '/app/Modulos/boletins/Services/BoletimCadastroService.php';
require_once $basePath . '/app/Services/RegraAcademicaService.php';
require_once $basePath . '/app/Modulos/fechamento/Services/FechamentoService.php';
require_once $basePath . '/app/Modulos/fechamento/Services/FechamentoMaquinaEstados.php';
require_once $basePath . '/app/Services/ResultadoHomologacaoService.php';
require_once $basePath . '/app/Services/HistoricoEscolarService.php';
require_once $basePath . '/app/Models/Education/ClassDiary.php';
require_once $basePath . '/scripts/lib/SimulacaoAcademicaEduca.php';

use App\Modulos\Boletins\Services\BoletimCadastroService;
use App\Services\HistoricoEscolarService;

const TENANT_DB = 'educatudo_educa';
const ANO = 2025;
const PREFIXO = 'FLOW25';
const QUADRO_NOME = 'Quadro 2025 — fluxo completo';
const MODELO_NOME = 'Modelo de Boletim 2025 — fluxo';
const LIMITE_TURMAS = 2;

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

final class SeedFluxoFechamento2025
{
    private ExamBlock $provas;
    private ExamBlockManualGrade $notas;
    private BoletimConfig $boletim;
    private BoletimConfigController $boletimCtrl;
    private GrupoRegrasNotasService $quadroSvc;
    private BoletimCadastroService $modeloSvc;
    private FechamentoService $fechamento;
    private ResultadoHomologacaoService $homologacao;
    private HistoricoEscolarService $historico;
    private int $adminId = 0;
    private int $anoLetivoId = 0;
    private int $tipoNotaId = 0;
    private int $regraAcademicaId = 0;
    private int $quadroId = 0;
    /** @var list<int> */
    private array $colunaIds = [];
    private int $modeloId = 0;
    /** @var list<array{id:int,nome:string}> */
    private array $turmas = [];
    /** @var list<int> */
    private array $historicoAlunos = [];

    public function __construct(private $db, private bool $todas, private bool $concluir)
    {
        $this->provas = new ExamBlock();
        $this->notas = new ExamBlockManualGrade();
        $this->boletim = new BoletimConfig();
        $this->boletim->ensureSchema();
        $this->boletimCtrl = $this->montarBoletimController();
        $this->quadroSvc = new GrupoRegrasNotasService();
        $this->modeloSvc = new BoletimCadastroService();
        $this->fechamento = new FechamentoService();
        $this->homologacao = $this->fechamento->homologacao();
        $this->historico = new HistoricoEscolarService();
    }

    public function executar(): int
    {
        println('== Seed fluxo completo 2025 — Colégio Educa ==');
        $this->adminId = $this->resolverAdmin();
        $this->anoLetivoId = $this->resolverAnoLetivo();
        $this->turmas = $this->resolverTurmas();
        println('  admin_id=' . $this->adminId . '  ano_letivo_id=' . $this->anoLetivoId . '  turmas=' . count($this->turmas));

        $this->tipoNotaId = $this->garantirTipoNota();
        $this->regraAcademicaId = $this->garantirRegra();
        $this->relaxarGatesFechamento();
        $this->quadroId = $this->garantirQuadro();
        $this->modeloId = $this->garantirModelo();

        foreach ($this->turmas as $turma) {
            $tid = (int) $turma['id'];
            println('  · Turma ' . $turma['nome'] . ' (#' . $tid . ')');
            $this->popularTurma($tid);
        }

        if ($this->concluir) {
            $this->encerrarMatriculasAno();
        }

        $this->gerarHistoricosAmostra();
        $this->imprimirRoteiro();
        return 0;
    }

    private function resolverAdmin(): int
    {
        $row = $this->db->fetch(
            "SELECT id FROM usuarios
              WHERE tipo = 'admin_escola' AND ativo = 1
              ORDER BY FIELD(perfil_admin, 'dev', 'diretor', 'coordenador') ASC, id ASC
              LIMIT 1"
        );
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            fail('Nenhum admin ativo. Rode: php scripts/init_colegio_educa.php && php scripts/popular_colegio_educa.php');
        }
        return $id;
    }

    private function resolverAnoLetivo(): int
    {
        $row = $this->db->fetch('SELECT id FROM ano_letivo WHERE ano = :a LIMIT 1', ['a' => ANO]);
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            fail('Ano letivo 2025 não cadastrado. Rode php scripts/popular_colegio_educa.php');
        }
        return $id;
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    private function resolverTurmas(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, nome FROM turmas WHERE ano_letivo = :a AND ativo = 1 ORDER BY nome ASC',
            ['a' => ANO]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $alunos = $this->alunosDaTurma($id);
            if ($alunos === []) {
                continue;
            }
            $out[] = ['id' => $id, 'nome' => (string) ($r['nome'] ?? ('#' . $id))];
        }
        if ($out === []) {
            fail('Nenhuma turma 2025 com aluno matriculado. Rode php scripts/popular_colegio_educa.php');
        }
        if ($this->todas) {
            return $out;
        }
        return array_slice($out, 0, LIMITE_TURMAS);
    }

    private function garantirTipoNota(): int
    {
        $tipos = (new ExamEvaluationType())->getAllActive();
        foreach ($tipos as $t) {
            $id = (int) ($t['id'] ?? 0);
            if ($id > 0) {
                println('  tipo de nota: #' . $id . ' ' . ($t['nome'] ?? ''));
                return $id;
            }
        }
        fail('Cadastre um Tipo de Nota em /admin/provas/tipos-avaliacao');
    }

    private function garantirRegra(): int
    {
        $row = $this->db->fetch(
            'SELECT id FROM regras_academicas WHERE ativo = 1 ORDER BY id ASC LIMIT 1'
        );
        if ($row) {
            $id = (int) $row['id'];
            println('  regra de aprovação: #' . $id);
            return $id;
        }
        $svc = new RegraAcademicaService();
        $res = $svc->criar([
            'nome' => 'Regra 2025 — fluxo',
            'codigo' => 'flow25-regra',
            'ano_letivo' => ANO,
            'media_minima' => 6.0,
            'frequencia_minima' => 75.0,
            'usar_frequencia' => 0,
            'periodo_tipo' => 'ano',
            'recuperacao_tipo' => 'periodo',
            'recuperacao_composicao' => 'maior_nota',
            'round_mode' => 'none',
            'decimal_places' => 2,
            'ativo' => 1,
        ], $this->adminId, 'Seed FLOW25');
        $id = (int) ($res['id'] ?? 0);
        if ($id <= 0) {
            fail('Não foi possível criar a regra de aprovação: ' . ($res['error'] ?? ''));
        }
        println('  regra de aprovação criada: #' . $id);
        return $id;
    }

    private function relaxarGatesFechamento(): void
    {
        try {
            $this->homologacao->model()->salvarConfigFechamento([
                'exigir_conselho' => 0,
                'exigir_frequencia' => 0,
                'exigir_notas' => 1,
            ], $this->adminId);
            println('  fechamento: exige notas; frequência/conselho opcionais (demo)');
        } catch (Throwable $e) {
            println('  aviso: não gravou config de fechamento (' . $e->getMessage() . ')');
        }
    }

    private function garantirQuadro(): int
    {
        if (!$this->quadroSvc->model()->tabelasProntas()) {
            fail('Rode no Master as migrations 2026_09_01_grupos_regras_notas.sql e 2026_09_10_quadros_notas.sql');
        }
        try {
            $this->db->query(
                'DELETE q FROM quadros_notas q
                 LEFT JOIN quadros_notas_colunas c ON c.grupo_id = q.id
                 WHERE q.nome = :n AND c.id IS NULL',
                ['n' => QUADRO_NOME]
            );
        } catch (Throwable $e) {
            // ignore
        }
        $exist = $this->db->fetch(
            'SELECT q.id
               FROM quadros_notas q
               INNER JOIN quadros_notas_colunas c ON c.grupo_id = q.id
              WHERE q.ativo = 1
              GROUP BY q.id
              HAVING COUNT(*) >= 4
              ORDER BY q.id ASC
              LIMIT 1'
        );
        $id = (int) ($exist['id'] ?? 0);
        if ($id <= 0) {
            $res = $this->quadroSvc->salvar([
                'nome' => QUADRO_NOME,
                'descricao' => 'Quadro do seed de fluxo 2025 (colunas = bimestres).',
                'ativo' => 1,
                'modo' => 'simples',
                'escala_max' => 10,
                'criterio_calculo' => 'ultima',
                'ritmo_intervalo_semanas' => 8,
                'ritmo_data_inicio' => ANO . '-02-10',
                'colunas' => [
                    ['codigo' => 'b1', 'nome' => '1º bimestre', 'numero' => 1],
                    ['codigo' => 'b2', 'nome' => '2º bimestre', 'numero' => 2],
                    ['codigo' => 'b3', 'nome' => '3º bimestre', 'numero' => 3],
                    ['codigo' => 'b4', 'nome' => '4º bimestre', 'numero' => 4],
                ],
            ]);
            if (empty($res['success'])) {
                fail('Quadro: ' . ($res['error'] ?? 'falha') . ' (se a FK do rename 09_10 quebrou, use um quadro já existente em /admin/quadros-notas)');
            }
            $id = (int) ($res['id'] ?? 0);
        }
        $this->quadroId = $id;
        $grupo = $this->quadroSvc->carregarCompleto($this->quadroId);
        $this->colunaIds = [];
        foreach ($grupo['marcas'] ?? [] as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid > 0) {
                $this->colunaIds[] = $cid;
            }
        }
        $this->colunaIds = array_slice($this->colunaIds, 0, 4);
        println('  quadro: #' . $this->quadroId . ' ' . (string) ($grupo['nome'] ?? '') . ' (' . count($this->colunaIds) . ' colunas)');
        return $this->quadroId;
    }

    private function garantirModelo(): int
    {
        if (!$this->modeloSvc->model()->tabelasProntas()) {
            println('  aviso: tabela boletins ausente — pule Modelo. Rode 2026_09_02_boletins.sql');
            return 0;
        }
        $exist = $this->db->fetch('SELECT id FROM boletins WHERE nome = :n LIMIT 1', ['n' => MODELO_NOME]);
        $id = (int) ($exist['id'] ?? 0);
        $materias = $this->db->fetchAll('SELECT id FROM materias ORDER BY nome ASC LIMIT 20') ?: [];
        $materiaIds = array_values(array_filter(array_map(static fn ($r) => (int) ($r['id'] ?? 0), $materias)));
        if ($materiaIds === []) {
            println('  aviso: sem matérias para o modelo de boletim');
            return 0;
        }
        $res = $this->modeloSvc->salvar([
            'nome' => MODELO_NOME,
            'finalidade' => 'oficial',
            'ano_letivo' => ANO,
            'materias_ids' => $materiaIds,
            'regra_academica_id' => $this->regraAcademicaId,
            'vis_aluno' => 1,
            'vis_pais' => 1,
            'vis_coordenacao' => 1,
            'ativo' => 1,
        ], $id > 0 ? $id : null);
        if (empty($res['success'])) {
            println('  aviso modelo: ' . ($res['error'] ?? 'falha'));
            return $id;
        }
        $this->modeloId = (int) ($res['id'] ?? $id);
        println('  modelo de boletim: #' . $this->modeloId);
        return $this->modeloId;
    }

    private function turmaJaHomologada(int $turmaId): bool
    {
        $alunosN = count($this->alunosDaTurma($turmaId));
        if ($alunosN <= 0) {
            return false;
        }
        $ja = $this->db->fetch(
            "SELECT COUNT(*) AS n FROM resultado_academico
              WHERE turma_id = :t AND ano_letivo = :a AND periodo_tipo = 'ano' AND status = 'homologado'",
            ['t' => $turmaId, 'a' => ANO]
        );
        return (int) ($ja['n'] ?? 0) >= $alunosN;
    }

    private function popularTurma(int $turmaId): void
    {
        if ($this->turmaJaHomologada($turmaId)) {
            println('    alunos já homologados — alinha o painel');
            $this->fecharEHomologar($turmaId);
            $alunos = $this->alunosDaTurma($turmaId);
            foreach (array_slice($alunos, 0, 2) as $al) {
                $this->historicoAlunos[] = (int) $al['id'];
            }
            return;
        }
        $eventosPorBim = [];
        for ($bim = 1; $bim <= 4; $bim++) {
            $blocoId = $this->garantirEventoBimestre($turmaId, $bim);
            $this->lancarNotas($blocoId, $turmaId, $bim);
            $eventosPorBim[$bim] = $blocoId;
        }
        for ($bim = 1; $bim <= 4; $bim++) {
            $this->gerarBoletimBimestre($turmaId, $bim, $eventosPorBim[$bim]);
        }
        $this->fecharEHomologar($turmaId);
        $alunos = $this->alunosDaTurma($turmaId);
        foreach (array_slice($alunos, 0, 2) as $al) {
            $this->historicoAlunos[] = (int) $al['id'];
        }
    }

    private function garantirEventoBimestre(int $turmaId, int $bim): int
    {
        $titulo = sprintf('%s %dº bimestre — turma %d', PREFIXO, $bim, $turmaId);
        $exist = $this->db->fetch(
            'SELECT id FROM provas_blocos WHERE titulo = :t AND deleted_at IS NULL LIMIT 1',
            ['t' => $titulo]
        );
        if ($exist) {
            return (int) $exist['id'];
        }
        $legado = $this->db->fetch(
            "SELECT pb.id
               FROM provas_blocos pb
               INNER JOIN provas_blocos_turmas t ON t.bloco_id = pb.id
              WHERE pb.deleted_at IS NULL AND pb.ano_letivo = :ano AND pb.bimestre = :bim
                AND t.turma_id = :tid
                AND pb.titulo LIKE 'EDUCA%Prova Bimestral 1%'
              ORDER BY pb.id ASC LIMIT 1",
            ['ano' => ANO, 'bim' => $bim, 'tid' => $turmaId]
        );
        if ($legado) {
            return (int) $legado['id'];
        }
        $periodo = $this->periodoBimestre($bim);
        $colunaId = $this->colunaIds[$bim - 1] ?? 0;
        $profs = $this->professoresDaTurma($turmaId);
        $id = (int) $this->provas->create([
            'titulo' => $titulo,
            'descricao' => '[q:' . $this->quadroId . ':c:' . $colunaId . ':b:0] Seed fluxo 2025.',
            'data_prova' => $periodo['inicio'],
            'hora_inicio' => '08:00:00',
            'hora_fim' => '09:30:00',
            'criado_por' => $this->adminId,
            'tipo_prova' => 'original',
            'configuracao_nota' => 'coordenacao_calcula',
            'formato_evento' => 'lancamento_nota',
            'ano_letivo' => ANO,
            'bimestre' => $bim,
            'tipo_avaliacao_id' => $this->tipoNotaId,
            'semana' => $bim,
            'grupo_regras_notas_id' => $this->quadroId,
            'grupo_regras_marca_id' => $colunaId > 0 ? $colunaId : null,
            'grupos_regras_vinculos' => [[
                'grupo_id' => $this->quadroId,
                'tipo_id' => null,
                'marca_id' => $colunaId > 0 ? $colunaId : null,
            ]],
            'liberado' => 1,
            'ativo' => 1,
            'visivel_no_portal_aluno' => 1,
            'turmas' => [$turmaId],
            'professores' => $profs,
        ]);
        if ($id <= 0) {
            fail('Não criou lançamento ' . $titulo);
        }
        println('    evento criado #' . $id . ' B' . $bim);
        return $id;
    }

    private function lancarNotas(int $blocoId, int $turmaId, int $bim): void
    {
        $alunos = $this->alunosDaTurma($turmaId);
        $profs = $this->db->fetchAll(
            'SELECT professor_id, materia_id FROM provas_blocos_professores WHERE bloco_id = :id',
            ['id' => $blocoId]
        ) ?: [];
        if ($profs === []) {
            $profs = $this->db->fetchAll(
                'SELECT professor_id, materia_id FROM grade_horaria WHERE turma_id = :t GROUP BY professor_id, materia_id',
                ['t' => $turmaId]
            ) ?: [];
        }
        if ($alunos === [] || $profs === []) {
            println('    B' . $bim . ': sem professor/aluno para lançar nota');
            return;
        }
        foreach ($profs as $p) {
            $pid = (int) ($p['professor_id'] ?? 0);
            $mid = (int) ($p['materia_id'] ?? 0);
            if ($pid <= 0 || $mid <= 0) {
                continue;
            }
            $linhas = [];
            foreach ($alunos as $i => $al) {
                $aid = (int) $al['id'];
                $linhas[] = [
                    'turma_id' => $turmaId,
                    'aluno_id' => $aid,
                    'nota' => $this->notaDemo($aid, $mid, $bim),
                ];
            }
            try {
                $this->notas->upsertLinhas($blocoId, $pid, $mid, $linhas);
            } catch (Throwable $e) {
                println('    aviso nota B' . $bim . ': ' . $e->getMessage());
                return;
            }
        }
        println('    notas B' . $bim . ' ok');
    }

    private function notaDemo(int $alunoId, int $materiaId, int $bim): float
    {
        return SimulacaoAcademicaEduca::nota($alunoId, $materiaId, $bim, 'p1');
    }

    /**
     * @param array<int,int> $eventosPorBim
     */
    private function gerarBoletimBimestre(int $turmaId, int $bim, int $blocoId): void
    {
        $codigo = sprintf('flow25-t%d-b%d', $turmaId, $bim);
        $periodo = $this->periodoBimestre($bim);
        $periodoRef = ANO . '-B' . $bim;
        $existente = $this->boletim->getRuleByCode($codigo);
        $regraId = $this->boletim->saveRule(
            sprintf('Boletim fluxo 2025 t%d B%d', $turmaId, $bim),
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
            'Seed FLOW25',
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
        if ($regraId > 0 && $this->modeloId > 0) {
            $this->boletim->setBoletimId((int) $regraId, $this->modeloId);
        }
        if ($this->quadroId > 0 && $regraId > 0) {
            $colunaId = $this->colunaIds[$bim - 1] ?? 0;
            $this->boletim->mesclarExtrasJson((int) $regraId, [
                'quadro_notas_id' => $this->quadroId,
                'grupo_regras_notas_id' => $this->quadroId,
                'destinos' => [[
                    'grupo_id' => $this->quadroId,
                    'quadro_id' => $this->quadroId,
                    'tipo_id' => null,
                    'marca_id' => $colunaId > 0 ? $colunaId : null,
                    'coluna_id' => $colunaId > 0 ? $colunaId : null,
                ]],
            ]);
        }
        $regra = $this->boletim->getRuleById((int) $regraId);
        if (!is_array($regra)) {
            println('    boletim B' . $bim . ': regra inválida');
            return;
        }
        $gerados = 0;
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
                    (int) $regraId,
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
                println('    boletim aluno ' . (int) $al['id'] . ': ' . $e->getMessage());
            }
        }
        println('    boletim B' . $bim . ' gerados=' . $gerados);
    }

    private function fecharEHomologar(int $turmaId): void
    {
        $diario = new ClassDiary();
        $aulas = $diario->completarSlotsVencidos($turmaId, ANO . '-01-01', ANO . '-12-31');
        if ($aulas > 0) {
            println('    diário histórico: ' . $aulas . ' aulas finalizadas');
        }
        if (!$this->fechamento->model()->schemaPronto()) {
            println('    aviso: schema fechamento_periodo ausente');
            return;
        }
        $this->fechamento->garantirVigente($turmaId, ANO, 'ano', 0, $this->regraAcademicaId);
        $ja = $this->db->fetch(
            "SELECT COUNT(*) AS n FROM resultado_academico
              WHERE turma_id = :t AND ano_letivo = :a AND periodo_tipo = 'ano' AND status = 'homologado'",
            ['t' => $turmaId, 'a' => ANO]
        );
        $alunosN = count($this->alunosDaTurma($turmaId));
        $homologadosN = (int) ($ja['n'] ?? 0);

        $emRec = $this->db->fetch(
            "SELECT COUNT(*) AS n FROM resultado_academico
              WHERE turma_id = :t AND ano_letivo = :a AND periodo_tipo = 'ano'
                AND status = 'homologado' AND situacao IN ('recuperacao', 'exame_final')",
            ['t' => $turmaId, 'a' => ANO]
        );
        $aindaRec = (int) ($emRec['n'] ?? 0);

        $vig = $this->fechamento->model()->findVigente($turmaId, ANO, 'ano', 0);
        $status = (string) ($vig['status'] ?? 'ABERTO');
        if ($homologadosN >= $alunosN && $alunosN > 0) {
            if ($aindaRec > 0) {
                println('    aviso: ' . $aindaRec . ' aluno(s) ainda em recuperação — o ano não fecha homologado.');
                if ($status === FechamentoMaquinaEstados::ABERTO) {
                    $this->fechamento->transitar(
                        $turmaId, ANO, 'ano', 0,
                        FechamentoMaquinaEstados::EM_FECHAMENTO,
                        $this->adminId,
                        'Seed FLOW25: fechamento com aluno em recuperação.'
                    );
                    $status = FechamentoMaquinaEstados::EM_FECHAMENTO;
                }
                if ($status === FechamentoMaquinaEstados::EM_FECHAMENTO) {
                    $rec = $this->fechamento->transitar(
                        $turmaId, ANO, 'ano', 0,
                        FechamentoMaquinaEstados::EM_RECUPERACAO,
                        $this->adminId,
                        'Seed FLOW25: recuperação pendente no ano.'
                    );
                    println('    painel anual: ' . (!empty($rec['success']) ? 'EM_RECUPERACAO' : ($rec['error'] ?? $status)));
                } elseif ($status === FechamentoMaquinaEstados::HOMOLOGADO) {
                    println('    período já HOMOLOGADO com aluno em recuperação — corrija o snapshot.');
                }
                return;
            }
            if ($status === FechamentoMaquinaEstados::ABERTO) {
                $this->fechamento->transitar(
                    $turmaId, ANO, 'ano', 0,
                    FechamentoMaquinaEstados::EM_FECHAMENTO,
                    $this->adminId,
                    'Seed FLOW25: alunos já homologados — alinha o painel.'
                );
            }
            $fim = $this->fechamento->transitar(
                $turmaId, ANO, 'ano', 0,
                FechamentoMaquinaEstados::HOMOLOGADO,
                $this->adminId,
                'Seed FLOW25: período anual 2025 homologado (alunos já tinham snapshot).'
            );
            println('    painel anual: ' . (!empty($fim['success']) ? 'HOMOLOGADO' : ($fim['error'] ?? $status)));
            return;
        }

        if ($status === FechamentoMaquinaEstados::ABERTO) {
            $this->fechamento->transitar(
                $turmaId, ANO, 'ano', 0,
                FechamentoMaquinaEstados::EM_FECHAMENTO,
                $this->adminId,
                'Seed FLOW25 iniciou o fechamento anual.'
            );
        }
        $res = $this->homologacao->homologarTurma($turmaId, ANO, 'ano', 0, $this->adminId, [], true);
        println(
            '    homologar: '
            . (!empty($res['success'])
                ? ('homologados=' . ($res['homologados'] ?? 0) . ' ignorados=' . ($res['ignorados'] ?? 0))
                : ($res['error'] ?? 'falha'))
        );
    }

    private function gerarHistoricosAmostra(): void
    {
        $ids = array_values(array_unique($this->historicoAlunos));
        $n = 0;
        foreach (array_slice($ids, 0, 4) as $alunoId) {
            try {
                $res = $this->historico->gerarRascunho(
                    $alunoId,
                    'Solicitacao',
                    $this->adminId,
                    'Histórico gerado pelo seed de fluxo 2025.'
                );
                if (!empty($res['success'])) {
                    $n++;
                    println('  histórico aluno #' . $alunoId . ' → ' . ($res['id'] ?? ''));
                } else {
                    println('  histórico aluno #' . $alunoId . ': ' . ($res['error'] ?? 'falha'));
                }
            } catch (Throwable $e) {
                println('  histórico aluno #' . $alunoId . ': ' . $e->getMessage());
            }
        }
        println('  históricos gerados: ' . $n);
    }

    private function encerrarMatriculasAno(): void
    {
        $antes = $this->db->fetch(
            "SELECT COUNT(*) AS n FROM matricula
              WHERE ano_letivo_id = :ano AND status = 'ativa'",
            ['ano' => $this->anoLetivoId]
        );
        $n = (int) ($antes['n'] ?? 0);
        if ($n <= 0) {
            println('  matrículas 2025: nenhuma ativa (já encerradas)');
            return;
        }
        $this->db->query(
            "UPDATE matricula SET status = 'concluido', data_saida = :saida
              WHERE ano_letivo_id = :ano AND status = 'ativa'",
            ['saida' => ANO . '-12-15', 'ano' => $this->anoLetivoId]
        );
        println('  matrículas 2025: ' . $n . ' ativas → concluido (saída 15/12/' . ANO . ')');
    }

    /**
     * @return list<array{professor_id:int,materia_id:int,quantidade_questoes:int,turmas:list<int>}>
     */
    private function professoresDaTurma(int $turmaId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT professor_id, materia_id FROM grade_horaria
              WHERE turma_id = :t GROUP BY professor_id, materia_id',
            ['t' => $turmaId]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $pid = (int) ($r['professor_id'] ?? 0);
            $mid = (int) ($r['materia_id'] ?? 0);
            if ($pid <= 0 || $mid <= 0) {
                continue;
            }
            $out[] = [
                'professor_id' => $pid,
                'materia_id' => $mid,
                'quantidade_questoes' => 1,
                'turmas' => [$turmaId],
            ];
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    private function alunosDaTurma(int $turmaId): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.nome
               FROM alunos a
               INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t AND m.ano_letivo_id = :ano
              WHERE m.status IN ('ativa','concluido','transferido')
              ORDER BY a.nome ASC",
            ['t' => $turmaId, 'ano' => $this->anoLetivoId]
        ) ?: [];
    }

    /**
     * @return array{inicio:string,fim:string}
     */
    private function periodoBimestre(int $bim): array
    {
        $map = [
            1 => [ANO . '-02-03', ANO . '-04-30'],
            2 => [ANO . '-05-02', ANO . '-06-30'],
            3 => [ANO . '-08-01', ANO . '-10-10'],
            4 => [ANO . '-10-13', ANO . '-12-12'],
        ];
        $p = $map[$bim] ?? $map[1];
        return ['inicio' => $p[0], 'fim' => $p[1]];
    }

    private function montarBoletimController(): BoletimConfigController
    {
        $ref = new ReflectionClass(BoletimConfigController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('boletimConfig');
        $prop->setAccessible(true);
        $prop->setValue($ctrl, $this->boletim);
        return $ctrl;
    }

    private function imprimirRoteiro(): void
    {
        $base = 'http://educa.localhost';
        $t0 = $this->turmas[0]['id'] ?? 0;
        $aluno0 = $this->historicoAlunos[0] ?? 0;
        println('');
        println('Pronto. Entre como admin da escola Educa e siga nesta ordem (ano = 2025, período = ano inteiro):');
        println('  1. Implantar acadêmico     ' . $base . '/admin/implantar-academico');
        println('  2. Quadro de Notas         ' . $base . '/admin/quadros-notas/' . $this->quadroId . '/editar');
        println('  3. Lançamento de Notas     ' . $base . '/admin/provas');
        println('  4. Painel de Fechamento    ' . $base . '/admin/fechamento?ano_letivo=' . ANO . '&periodo_tipo=ano&periodo_numero=0');
        if ($t0 > 0) {
            println('     Turma seed              ' . $base . '/admin/fechamento/turma/' . $t0 . '?ano_letivo=' . ANO . '&periodo_tipo=ano&periodo_numero=0');
        }
        println('  5. Homologações            ' . $base . '/admin/homologacoes?ano_letivo=' . ANO);
        println('  6. Boletins/atas/fichas    ' . $base . '/admin/documentos-periodo?ano_letivo=' . ANO);
        if ($aluno0 > 0) {
            println('  7. Histórico escolar       ' . $base . '/admin/students/' . $aluno0 . '/historico-escolar');
        }
        println('');
        println('Turmas deste seed: ' . implode(', ', array_map(static fn ($t) => $t['nome'], $this->turmas)));
        println('Para todas as turmas 2025 + matrículas concluídas:');
        println('  php scripts/seed_fluxo_fechamento_2025_educa.php --todas --concluir');
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
    fail('Não conectou em ' . TENANT_DB . '. Suba o Docker e rode init_colegio_educa.php. ' . $e->getMessage());
}

Database::setCurrentInstance(Database::createFromPdo($pdo, [
    'host' => $host,
    'port' => $port,
    'name' => TENANT_DB,
    'user' => $dbUser,
    'pass' => $dbPass,
]));

$todas = in_array('--todas', $argv ?? [], true);
$concluir = in_array('--concluir', $argv ?? [], true);
$seed = new SeedFluxoFechamento2025(Database::getInstance(), $todas, $concluir);
exit($seed->executar());
