<?php
/**
 * Seed base Santa Maria 2024 — só a aluna Ana Luiza (1ª série A).
 *
 * Cria: ano letivo (bimestre), matérias, agrupamentos, turma, aluna,
 * regra de aprovação, recuperações (semestral + prova final), eventos
 * avaliativos (PP/PU/CH/PE nos 4 bimestres + R1/R2/RF), notas e faltas.
 *
 * NÃO cria: modelo de Demonstrativo, boletim_regras, layout de boletim
 * nem resultados gerados — você monta isso do zero na escola.
 *
 * Idempotente (prefixo SM24). Aceita banco de qualquer escola teste.
 *
 * Uso (no container PHP / servidor):
 *   php scripts/seed_santa_maria_ana_2024_base.php --db=NOME_DO_BANCO --confirmar
 *   php scripts/seed_santa_maria_ana_2024_base.php --db=educatudo_educa --confirmar --dry-run
 *
 * Senha da aluna: Teste@123
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

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

use App\Core\Database;
use App\Core\PeriodoLetivo;
use App\Dominio\Escola\Models\ComponenteCurricular;
use App\Dominio\Escola\Services\AnoLetivoService;
use App\Dominio\Pessoas\Services\AlunoMovimentacaoService;
use App\Modulos\AgrupamentosComponentes\Models\AgrupamentoComponente;
use App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService;
use App\Modulos\Provas\Models\ExamBlock;
use App\Modulos\Provas\Models\ExamBlockManualGrade;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use App\Modulos\RegrasAcademicas\Services\RegraAcademicaService;
use App\Modulos\VidaEscolar\Models\SchoolAbsence;

const ANO = 2024;
const PREFIXO = 'SM24';
const TURMA_NOME = '1A Santa Maria';
const ALUNO_NOME = 'Ana Luiza Oliveira De Souza';
const ALUNO_RA = '24254';
const ALUNO_NICK = 'sm24.analuiza';
const MEDIA_SEMESTRE = 7.0;
const SENHA_PADRAO = 'Teste@123';
const CODIGO_REGRA = 'sm24-aprovacao-2024';
const NOME_REGRA = 'Aprovação Santa Maria 2024';
const FORMULA_FINAL = '(m1 + m2 + m3 + m4) / 4';

/** @var array<int, array{pp:string,pu:string,ch:string,pe:string,rec:string}> */
const DATAS = [
    1 => ['pp' => '2024-03-08', 'pu' => '2024-04-12', 'ch' => '2024-04-13', 'pe' => '2024-04-15', 'rec' => '2024-07-08'],
    2 => ['pp' => '2024-05-17', 'pu' => '2024-06-21', 'ch' => '2024-06-22', 'pe' => '2024-06-24', 'rec' => '2024-07-08'],
    3 => ['pp' => '2024-08-16', 'pu' => '2024-09-20', 'ch' => '2024-09-21', 'pe' => '2024-09-23', 'rec' => '2024-12-09'],
    4 => ['pp' => '2024-10-18', 'pu' => '2024-11-22', 'ch' => '2024-11-23', 'pe' => '2024-11-25', 'rec' => '2024-12-09'],
];

/**
 * Notas de 2ª Chamada (CH) — só onde o boletim anexo traz valor.
 *
 * @var array<string, array<int, float>>
 */
const CH_NOTAS = [
    'Física 1' => [3 => 2.3],
    'Física 2' => [3 => 2.3],
    'Química 1' => [3 => 2.3],
    'Química 2' => [3 => 2.3],
    'Biologia 1' => [3 => 2.3],
    'Biologia 2' => [3 => 2.3],
    'Matemática 1' => [3 => 2.3],
    'Matemática 2' => [3 => 2.3],
];

/**
 * Notas do boletim. Chave = matéria lançável.
 * pp/pu/pe por bimestre; rec_s1 = recuperação do 1º semestre no desdobramento.
 *
 * @var array<string, array{
 *   pp:array<int,?float>,
 *   pu:array<int,?float>,
 *   pe:array<int,?float>,
 *   fal:array<int,int>,
 *   rec_s1:?float
 * }>
 */
const NOTAS = [
    'Língua Portuguesa' => [
        'pp' => [1 => 8.5, 2 => 7.0, 3 => 8.5, 4 => 8.5],
        'pu' => [1 => 7.5, 2 => 8.8, 3 => 7.1, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
        'rec_s1' => null,
    ],
    'Redação' => [
        'pp' => [1 => 9.3, 2 => 9.7, 3 => 6.5, 4 => 9.0],
        'pu' => [1 => 8.6, 2 => 7.0, 3 => 8.1, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
        'rec_s1' => null,
    ],
    'Literatura' => [
        'pp' => [1 => 9.0, 2 => 9.5, 3 => 8.0, 4 => 10.0],
        'pu' => [1 => 7.5, 2 => 8.8, 3 => 7.1, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 1, 4 => 0],
        'rec_s1' => null,
    ],
    'Arte' => [
        'pp' => [1 => 10.0, 2 => 6.5, 3 => 10.0, 4 => 8.0],
        'pu' => [1 => 7.5, 2 => 8.8, 3 => 7.1, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 1, 4 => 0],
        'rec_s1' => null,
    ],
    'História 1' => [
        'pp' => [1 => 5.0, 2 => 5.0, 3 => 4.0, 4 => null],
        'pu' => [1 => 4.1, 2 => 4.6, 3 => 4.8, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
        'rec_s1' => null,
    ],
    'História 2' => [
        'pp' => [1 => 5.0, 2 => 5.0, 3 => 4.0, 4 => 2.0],
        'pu' => [1 => 4.0, 2 => 4.5, 3 => 4.8, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 4, 4 => 0],
        'rec_s1' => null,
    ],
    'Geografia' => [
        'pp' => [1 => 9.0, 2 => 10.0, 3 => 10.0, 4 => 8.5],
        'pu' => [1 => 8.1, 2 => 9.1, 3 => 9.6, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 3, 4 => 0],
        'rec_s1' => null,
    ],
    'Sociologia' => [
        'pp' => [1 => 8.5, 2 => 8.0, 3 => 9.0, 4 => 6.5],
        'pu' => [1 => 8.1, 2 => 9.1, 3 => 9.6, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
        'rec_s1' => null,
    ],
    'Filosofia' => [
        'pp' => [1 => 8.5, 2 => 6.0, 3 => 9.5, 4 => null],
        'pu' => [1 => 8.1, 2 => 9.1, 3 => 9.6, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
        'rec_s1' => null,
    ],
    'Matemática 1' => [
        'pp' => [1 => 3.0, 2 => 3.0, 3 => 4.0, 4 => null],
        'pu' => [1 => 1.7, 2 => 2.4, 3 => 3.1, 4 => null],
        'pe' => [1 => 0.5, 2 => 0.5, 3 => 0.5, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 4, 4 => 0],
        'rec_s1' => 3.5,
    ],
    'Matemática 2' => [
        'pp' => [1 => 2.1, 2 => 0.5, 3 => 4.9, 4 => null],
        'pu' => [1 => 1.7, 2 => 2.4, 3 => 3.1, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
        'rec_s1' => 3.5,
    ],
    'Física 1' => [
        'pp' => [1 => 3.0, 2 => 2.1, 3 => 3.2, 4 => null],
        'pu' => [1 => 2.8, 2 => 3.2, 3 => 2.3, 4 => null],
        'pe' => [1 => null, 2 => 0.5, 3 => 0.5, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
        'rec_s1' => 3.5,
    ],
    'Física 2' => [
        'pp' => [1 => 4.0, 2 => 3.0, 3 => 3.1, 4 => null],
        'pu' => [1 => 2.7, 2 => 3.1, 3 => 2.3, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
        'rec_s1' => 3.5,
    ],
    'Química 1' => [
        'pp' => [1 => 2.5, 2 => 2.0, 3 => 3.2, 4 => null],
        'pu' => [1 => 2.8, 2 => 3.2, 3 => 2.3, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
        'rec_s1' => 5.0,
    ],
    'Química 2' => [
        'pp' => [1 => 5.0, 2 => 3.0, 3 => 3.1, 4 => null],
        'pu' => [1 => 2.7, 2 => 3.1, 3 => 2.3, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 4, 4 => 0],
        'rec_s1' => 5.0,
    ],
    'Biologia 1' => [
        'pp' => [1 => 2.0, 2 => 3.4, 3 => 3.2, 4 => null],
        'pu' => [1 => 2.8, 2 => 3.2, 3 => 2.3, 4 => null],
        'pe' => [1 => 0.5, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 3, 4 => 0],
        'rec_s1' => 5.0,
    ],
    'Biologia 2' => [
        'pp' => [1 => 4.0, 2 => 3.0, 3 => 3.1, 4 => null],
        'pu' => [1 => 2.7, 2 => 3.1, 3 => 2.3, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 4, 4 => 0],
        'rec_s1' => 5.0,
    ],
    'Língua Inglesa' => [
        'pp' => [1 => 6.0, 2 => 4.5, 3 => 8.0, 4 => null],
        'pu' => [1 => 7.5, 2 => 8.8, 3 => 7.1, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
        'rec_s1' => null,
    ],
    'Educação Física' => [
        'pp' => [1 => 10.0, 2 => 10.0, 3 => 9.0, 4 => 10.0],
        'pu' => [1 => 7.5, 2 => 8.8, 3 => 7.1, 4 => null],
        'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
        'fal' => [1 => 0, 2 => 0, 3 => 1, 4 => 0],
        'rec_s1' => null,
    ],
];

/** @var array<string, list<string>> */
const AGRUPAMENTOS = [
    'História' => ['História 1', 'História 2'],
    'Matemática' => ['Matemática 1', 'Matemática 2'],
    'Física' => ['Física 1', 'Física 2'],
    'Química' => ['Química 1', 'Química 2'],
    'Biologia' => ['Biologia 1', 'Biologia 2'],
];

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

final class SeedSantaMariaAna2024Base
{
    private ExamBlock $blocos;
    private ExamBlockManualGrade $notas;
    private EventoAvaliativoService $eventos;
    private ComponenteCurricular $materiasModel;
    private SchoolAbsence $faltas;
    private int $adminId = 0;
    private int $anoId = 0;
    private int $turmaId = 0;
    private int $alunoId = 0;
    private int $serieId = 0;
    private int $cursoId = 0;
    private int $matrizId = 0;
    private int $unidadeId = 0;
    private int $regraAcademicaId = 0;
    private string $hash = '';
    private string $tenantDb;
    /** @var array<string,int> */
    private array $materiaIds = [];
    /** @var array<string,int> */
    private array $professorPorMateria = [];
    /** @var array<string,int> */
    private array $eventoIds = [];
    /** @var array<string,int> */
    private array $blocoIds = [];
    /** @var array<int,int> bimestre => faltas_eventos.id */
    private array $faltasEventoIds = [];

    public function __construct(private $db, private bool $dryRun, string $tenantDb)
    {
        $this->tenantDb = $tenantDb;
        $this->blocos = new ExamBlock();
        $this->notas = new ExamBlockManualGrade();
        $this->eventos = new EventoAvaliativoService();
        $this->materiasModel = new ComponenteCurricular();
        $this->faltas = new SchoolAbsence();
        $this->faltas->ensureSchema();
        $this->hash = password_hash(SENHA_PADRAO, PASSWORD_DEFAULT);
    }

    public function executar(): int
    {
        println('== Seed base Santa Maria · Ana · ' . ANO . ' ==');
        println('  banco: ' . $this->tenantDb);
        println('  sem demonstrativo / sem boletim (monte do zero depois)');
        if ($this->dryRun) {
            println('Modo dry-run (não grava).');
        }

        $this->adminId = $this->resolverAdmin();
        $this->anoId = $this->garantirAnoLetivo();
        $this->resolverEstruturaEscola();
        $this->garantirMaterias();
        $this->garantirAgrupamentos();
        $this->garantirProfessores();
        $this->turmaId = $this->garantirTurma();
        $this->alunoId = $this->garantirAluno();
        $this->regraAcademicaId = $this->garantirRegraAprovacao();
        $this->garantirRecuperacaoConfig();
        $this->criarEventosENotas();
        $this->criarFaltasELancamentos();

        println('');
        println('Pronto.');
        println('  Ano: ' . ANO . ' (id=' . $this->anoId . ', bimestre)');
        println('  Turma: ' . TURMA_NOME . ' (#' . $this->turmaId . ')');
        println('  Aluno: ' . ALUNO_NOME . ' (#' . $this->alunoId . ', RA ' . ALUNO_RA . ')');
        println('  Regra: ' . NOME_REGRA . ' (#' . $this->regraAcademicaId . ')');
        println('  Eventos avaliativos: ' . count($this->eventoIds));
        println('  Eventos de faltas: ' . count($this->faltasEventoIds));
        println('  Senha da aluna: ' . SENHA_PADRAO);
        println('  Próximo passo: montar Demonstrativo e Boletim do zero no admin.');
        return 0;
    }

    private function resolverAdmin(): int
    {
        $row = $this->db->fetch(
            "SELECT id FROM usuarios
              WHERE (email LIKE 'admin@%' OR tipo = 'admin_escola') AND COALESCE(ativo,1) = 1
              ORDER BY id ASC LIMIT 1"
        );
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            fail('Nenhum admin ativo nesta escola. Cadastre um admin antes do seed.');
        }
        println('  admin_id=' . $id);
        return $id;
    }

    private function garantirAnoLetivo(): int
    {
        $row = $this->db->fetch('SELECT id, periodo_tipo FROM ano_letivo WHERE ano = :a LIMIT 1', ['a' => ANO]);
        if ($row) {
            $id = (int) $row['id'];
            $tipo = (string) ($row['periodo_tipo'] ?? '');
            if ($tipo !== '' && $tipo !== 'bimestre' && PeriodoLetivo::temColunaPeriodoTipo()) {
                if (!$this->dryRun) {
                    $this->db->query(
                        'UPDATE ano_letivo SET periodo_tipo = :t, data_inicio = :i, data_fim = :f, ativo = 1 WHERE id = :id',
                        ['t' => 'bimestre', 'i' => ANO . '-02-01', 'f' => ANO . '-12-20', 'id' => $id]
                    );
                    PeriodoLetivo::invalidarCache(ANO);
                }
                println('  ano ' . ANO . ' ajustado para bimestre (#' . $id . ')');
            } else {
                println('  ano ' . ANO . ' já existe (#' . $id . ')');
            }
            return $id;
        }
        if ($this->dryRun) {
            println('  [dry-run] criaria ano letivo ' . ANO);
            return 0;
        }
        $svc = new AnoLetivoService($this->db);
        $criado = $svc->cadastrar([
            'ano' => ANO,
            'data_inicio' => ANO . '-02-01',
            'data_fim' => ANO . '-12-20',
            'periodo_tipo' => 'bimestre',
            'ativo' => 1,
            'periodos' => [
                1 => ['data_inicio' => ANO . '-02-01', 'data_fim' => ANO . '-04-30', 'dias_letivos' => '50'],
                2 => ['data_inicio' => ANO . '-05-01', 'data_fim' => ANO . '-06-30', 'dias_letivos' => '45'],
                3 => ['data_inicio' => ANO . '-08-01', 'data_fim' => ANO . '-09-30', 'dias_letivos' => '45'],
                4 => ['data_inicio' => ANO . '-10-01', 'data_fim' => ANO . '-12-20', 'dias_letivos' => '55'],
            ],
        ]);
        println('  ano ' . ANO . ' criado (#' . $criado['id'] . ')');
        return (int) $criado['id'];
    }

    private function resolverEstruturaEscola(): void
    {
        $serie = $this->db->fetch(
            "SELECT id FROM serie
              WHERE nome LIKE '%1%Série%EM%' OR nome LIKE '%1ª Série%EM%' OR nome LIKE '1% Série EM'
              ORDER BY id ASC LIMIT 1"
        );
        if (!$serie) {
            $serie = $this->db->fetch("SELECT id FROM serie WHERE nome LIKE '%1%' ORDER BY id ASC LIMIT 1");
        }
        $this->serieId = (int) ($serie['id'] ?? 0);

        $curso = $this->db->fetch(
            "SELECT id FROM curso WHERE nome LIKE '%Médio%' OR nome LIKE '%Medio%' ORDER BY id ASC LIMIT 1"
        );
        $this->cursoId = (int) ($curso['id'] ?? 0);

        $matriz = $this->db->fetch(
            'SELECT id FROM matrizes_curriculares ORDER BY id ASC LIMIT 1'
        );
        $this->matrizId = (int) ($matriz['id'] ?? 0);

        $unidade = null;
        if ($this->db->tableExists('unidades')) {
            $unidade = $this->db->fetch('SELECT id FROM unidades ORDER BY id ASC LIMIT 1');
        }
        $this->unidadeId = (int) ($unidade['id'] ?? 0);
        if ($this->unidadeId <= 0) {
            $alunoUnidade = $this->db->fetch(
                'SELECT unidade_id FROM alunos WHERE unidade_id IS NOT NULL AND unidade_id > 0 ORDER BY id ASC LIMIT 1'
            );
            $this->unidadeId = (int) ($alunoUnidade['unidade_id'] ?? 0);
        }

        $ref = $this->db->fetch(
            'SELECT serie_id, curso_novo_id, matriz_curricular_id
               FROM turmas
              WHERE serie_id IS NOT NULL OR curso_novo_id IS NOT NULL OR matriz_curricular_id IS NOT NULL
              ORDER BY id ASC LIMIT 1'
        );
        if ($ref) {
            $this->serieId = $this->serieId > 0 ? $this->serieId : (int) ($ref['serie_id'] ?? 0);
            $this->cursoId = $this->cursoId > 0 ? $this->cursoId : (int) ($ref['curso_novo_id'] ?? 0);
            $this->matrizId = $this->matrizId > 0 ? $this->matrizId : (int) ($ref['matriz_curricular_id'] ?? 0);
        }
        if ($this->serieId <= 0) {
            println('  aviso: série 1ª EM não encontrada — turma pode ficar sem serie_id');
        }
    }

    private function garantirMaterias(): void
    {
        $precisamos = array_merge(array_keys(AGRUPAMENTOS), array_keys(NOTAS));
        $precisamos = array_values(array_unique($precisamos));

        foreach ($precisamos as $nome) {
            $row = $this->db->fetch(
                'SELECT id, pai_id, permite_avaliacao FROM materias WHERE nome = :n ORDER BY id ASC LIMIT 1',
                ['n' => $nome]
            );
            if ($row) {
                $this->materiaIds[$nome] = (int) $row['id'];
                continue;
            }
            if ($this->dryRun) {
                println('  [dry-run] criaria matéria ' . $nome);
                $this->materiaIds[$nome] = 0;
                continue;
            }
            $paiNome = null;
            foreach (AGRUPAMENTOS as $grupo => $filhos) {
                if (in_array($nome, $filhos, true)) {
                    $paiNome = $grupo;
                    break;
                }
            }
            $paiId = $paiNome !== null ? ($this->materiaIds[$paiNome] ?? 0) : null;
            if ($paiNome !== null && ($paiId === null || $paiId <= 0)) {
                // garante o pai antes do filho
                if (!isset($this->materiaIds[$paiNome])) {
                    $this->criarMateria($paiNome, null, false);
                }
                $paiId = $this->materiaIds[$paiNome] ?? 0;
            }
            $avaliavel = !isset(AGRUPAMENTOS[$nome]);
            $this->criarMateria($nome, $paiId > 0 ? $paiId : null, $avaliavel);
        }

        // Pais de agrupamento não recebem nota direta.
        if (!$this->dryRun) {
            foreach (array_keys(AGRUPAMENTOS) as $pai) {
                $id = $this->materiaIds[$pai] ?? 0;
                if ($id <= 0) {
                    continue;
                }
                $this->db->query(
                    'UPDATE materias SET permite_avaliacao = 0 WHERE id = :id',
                    ['id' => $id]
                );
                foreach (AGRUPAMENTOS[$pai] as $filho) {
                    $fid = $this->materiaIds[$filho] ?? 0;
                    if ($fid > 0) {
                        $this->db->query(
                            'UPDATE materias SET pai_id = :p, permite_avaliacao = 1, ativo = 1 WHERE id = :id',
                            ['p' => $id, 'id' => $fid]
                        );
                    }
                }
            }
            // LP no Educa é pai de Gramática/etc. Para o boletim Santa Maria
            // usamos a própria LP como lançável neste seed (código SM24).
            $lpId = $this->materiaIds['Língua Portuguesa'] ?? 0;
            if ($lpId > 0) {
                $sm = $this->db->fetch(
                    "SELECT id FROM materias WHERE codigo = :c LIMIT 1",
                    ['c' => PREFIXO . '-LP']
                );
                if ($sm) {
                    $this->materiaIds['Língua Portuguesa'] = (int) $sm['id'];
                } else {
                    $novo = $this->criarMateria('Língua Portuguesa', null, true, PREFIXO . '-LP');
                    // sobrescreve o mapa para lançamento
                    $this->materiaIds['Língua Portuguesa'] = $novo;
                }
            }
        }

        println('  matérias mapeadas: ' . count(array_filter($this->materiaIds)));
    }

    private function criarMateria(string $nome, ?int $paiId, bool $avaliavel, ?string $codigo = null): int
    {
        if ($codigo === null) {
            $limpo = preg_replace('/[^A-Za-z0-9]+/', '', strtr($nome, [
                'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
                'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
                'ú' => 'u', 'ç' => 'c', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ç' => 'C',
            ])) ?: 'MAT';
            $codigo = PREFIXO . '-' . strtoupper(substr($limpo, 0, 10));
        }
        $id = (int) $this->materiasModel->create([
            'nome' => $nome,
            'codigo' => $codigo,
            'sigla' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?: 'SM', -4)),
            'area_conhecimento' => 'linguagens',
            'tipo' => 'formacao_geral',
            'etapa_infantil' => 'nao_aplica',
            'etapa_fund_i' => 'nao_aplica',
            'etapa_fund_ii' => 'nao_aplica',
            'etapa_medio' => 'obrigatoria',
            'descricao' => 'Seed ' . PREFIXO,
            'cor' => '#4F46E5',
            'ordem' => 100,
            'permite_avaliacao' => $avaliavel ? 1 : 0,
            'permite_frequencia' => 1,
            'permite_plano_aula' => 1,
            'permite_diario' => 1,
            'ativo' => 1,
            'pai_id' => $paiId,
        ]);
        $this->materiaIds[$nome] = $id;
        println('  matéria criada: ' . $nome . ' (#' . $id . ')');
        return $id;
    }

    private function garantirAgrupamentos(): void
    {
        $model = new AgrupamentoComponente();
        if (!$model->tabelasProntas()) {
            println('  agrupamentos: tabela ausente (pulei)');
            return;
        }
        foreach (AGRUPAMENTOS as $pai => $filhos) {
            $nome = PREFIXO . ' ' . $pai;
            $exist = $this->db->fetch(
                'SELECT id FROM agrupamentos_componentes WHERE nome = :n LIMIT 1',
                ['n' => $nome]
            );
            $ids = [];
            foreach ($filhos as $filho) {
                $mid = $this->materiaIds[$filho] ?? 0;
                if ($mid > 0) {
                    $ids[] = $mid;
                }
            }
            $rotuloId = $this->materiaIds[$pai] ?? 0;
            if ($exist) {
                if (!$this->dryRun) {
                    $model->atualizar((int) $exist['id'], [
                        'nome' => $nome,
                        'modo' => 'soma',
                        'aplicar_em' => 'boletim',
                        'divisor' => null,
                        'materia_rotulo_id' => $rotuloId > 0 ? $rotuloId : null,
                        'ativo' => 1,
                    ], $ids);
                }
                continue;
            }
            if ($this->dryRun || $ids === []) {
                continue;
            }
            $model->criar([
                'nome' => $nome,
                'modo' => 'soma',
                'aplicar_em' => 'boletim',
                'divisor' => null,
                'materia_rotulo_id' => $rotuloId > 0 ? $rotuloId : null,
                'ativo' => 1,
            ], $ids);
            println('  agrupamento: ' . $nome);
        }
    }

    private function garantirProfessores(): void
    {
        $mapa = [
            'Língua Portuguesa' => 'Língua Portuguesa',
            'Redação' => 'Literatura',
            'Literatura' => 'Literatura',
            'Arte' => 'Arte',
            'História 1' => 'História',
            'História 2' => 'História',
            'Geografia' => 'Geografia',
            'Sociologia' => 'Sociologia',
            'Filosofia' => 'Filosofia',
            'Matemática 1' => 'Matemática',
            'Matemática 2' => 'Matemática',
            'Física 1' => 'Física',
            'Física 2' => 'Física',
            'Química 1' => 'Química',
            'Química 2' => 'Química',
            'Biologia 1' => 'Biologia',
            'Biologia 2' => 'Biologia',
            'Língua Inglesa' => 'Língua Inglesa',
            'Educação Física' => 'Educação Física',
        ];
        $profs = $this->db->fetchAll('SELECT id, nome, materias FROM professores WHERE ativo = 1') ?: [];
        if ($profs === [] && !$this->dryRun) {
            $pidFallback = (int) $this->db->insert(
                "INSERT INTO professores (nome, email, senha_hash, codigo_prof, ativo, materias, password)
                 VALUES (:n, :e, :s, :c, 1, :m, '')",
                [
                    'n' => PREFIXO . ' Professor Geral',
                    'e' => strtolower(PREFIXO) . '.prof@escola.local',
                    's' => $this->hash,
                    'c' => PREFIXO . '-PROF',
                    'm' => json_encode(['Matemática', 'História', 'Língua Portuguesa'], JSON_UNESCAPED_UNICODE),
                ]
            );
            if ($pidFallback > 0) {
                $profs[] = ['id' => $pidFallback, 'nome' => PREFIXO . ' Professor Geral', 'materias' => '[]'];
                println('  professor fallback criado (#' . $pidFallback . ')');
            }
        }
        $porArea = [];
        foreach ($profs as $p) {
            $nomes = json_decode((string) ($p['materias'] ?? '[]'), true);
            if (!is_array($nomes)) {
                continue;
            }
            foreach ($nomes as $n) {
                $chave = mb_strtolower(trim((string) $n));
                if ($chave !== '' && !isset($porArea[$chave])) {
                    $porArea[$chave] = (int) $p['id'];
                }
            }
        }
        foreach ($mapa as $materia => $area) {
            $pid = $porArea[mb_strtolower($area)] ?? 0;
            if ($pid <= 0) {
                $qualquer = (int) ($profs[0]['id'] ?? 0);
                $pid = $qualquer;
            }
            $this->professorPorMateria[$materia] = $pid;
        }
        println('  professores mapeados: ' . count($this->professorPorMateria));
    }

    private function garantirTurma(): int
    {
        $row = $this->db->fetch(
            'SELECT id FROM turmas WHERE nome = :n AND ano_letivo = :a LIMIT 1',
            ['n' => TURMA_NOME, 'a' => ANO]
        );
        if ($row) {
            $id = (int) $row['id'];
            println('  turma já existe (#' . $id . ')');
            return $id;
        }
        if ($this->dryRun) {
            println('  [dry-run] criaria turma ' . TURMA_NOME);
            return 0;
        }
        $id = (int) $this->db->insert(
            'INSERT INTO turmas
                (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, turno, vagas, observacoes)
             VALUES
                (:nome, :ano, :ano_id, :serie, :serie_id, :matriz, :curso, 1, :turno, 40, :obs)',
            [
                'nome' => TURMA_NOME,
                'ano' => ANO,
                'ano_id' => $this->anoId > 0 ? $this->anoId : null,
                'serie' => '1ª Série EM',
                'serie_id' => $this->serieId > 0 ? $this->serieId : null,
                'matriz' => $this->matrizId > 0 ? $this->matrizId : null,
                'curso' => $this->cursoId > 0 ? $this->cursoId : null,
                'turno' => 'manha',
                'obs' => PREFIXO . ' boletim Santa Maria',
            ]
        );
        println('  turma criada (#' . $id . ')');
        return $id;
    }

    private function garantirAluno(): int
    {
        $row = $this->db->fetch(
            'SELECT id FROM alunos WHERE nickname = :n OR ra = :ra ORDER BY id ASC LIMIT 1',
            ['n' => ALUNO_NICK, 'ra' => ALUNO_RA]
        );
        if ($row) {
            $id = (int) $row['id'];
            if (!$this->dryRun) {
                $this->db->query(
                    'UPDATE alunos SET nome = :nome, turma_id = :t, ativo = 1, status = :st, unidade_id = :u WHERE id = :id',
                    [
                        'nome' => ALUNO_NOME,
                        't' => $this->turmaId,
                        'st' => 'ACTIVE',
                        'u' => $this->unidadeId > 0 ? $this->unidadeId : null,
                        'id' => $id,
                    ]
                );
                $this->matricular($id);
            }
            println('  aluno já existe (#' . $id . ')');
            return $id;
        }
        if ($this->dryRun) {
            println('  [dry-run] criaria aluno ' . ALUNO_NOME);
            return 0;
        }
        $id = (int) $this->db->insert(
            "INSERT INTO alunos
                (nome, nickname, email, senha_hash, ra, turma_id, serie, unidade_id, data_nasc, ativo, pagante, status, password, primeiro_acesso)
             VALUES
                (:nome, :nickname, :email, :senha_hash, :ra, :turma_id, :serie, :unidade_id, :data_nasc, 1, 1, 'ACTIVE', '', 0)",
            [
                'nome' => ALUNO_NOME,
                'nickname' => ALUNO_NICK,
                'email' => ALUNO_NICK . '@educa.local',
                'senha_hash' => $this->hash,
                'ra' => ALUNO_RA,
                'turma_id' => $this->turmaId,
                'serie' => '1ª Série EM',
                'unidade_id' => $this->unidadeId > 0 ? $this->unidadeId : null,
                'data_nasc' => '2009-05-12',
            ]
        );
        $this->matricular($id);
        println('  aluno criado (#' . $id . ')');
        return $id;
    }

    private function matricular(int $alunoId): void
    {
        if ($alunoId <= 0 || $this->turmaId <= 0 || $this->anoId <= 0) {
            return;
        }
        try {
            (new AlunoMovimentacaoService())->vincularAlunoTurma(
                $alunoId,
                $this->turmaId,
                $this->anoId,
                true,
                ANO . '-02-01'
            );
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'já possui matrícula') === false) {
                println('  aviso matrícula: ' . $e->getMessage());
            }
        }
    }

    private function garantirRegraAprovacao(): int
    {
        if ($this->dryRun) {
            println('  [dry-run] criaria regra ' . NOME_REGRA);
            return 0;
        }
        $svc = new RegraAcademicaService();
        $exist = $this->db->fetch(
            "SELECT id FROM regras_academicas
              WHERE (codigo = :c OR (nome = :n AND ano_letivo = :a))
              ORDER BY CASE WHEN codigo = :c2 THEN 0 ELSE 1 END, id ASC
              LIMIT 1",
            [
                'c' => CODIGO_REGRA,
                'c2' => CODIGO_REGRA,
                'n' => NOME_REGRA,
                'a' => ANO,
            ]
        );
        $materiasIds = [];
        foreach (array_keys(NOTAS) as $nome) {
            $mid = $this->materiaIds[$nome] ?? 0;
            if ($mid > 0) {
                $materiasIds[] = $mid;
            }
        }
        $payload = [
            'nome' => NOME_REGRA,
            'codigo' => CODIGO_REGRA,
            'ano_letivo' => ANO,
            'serie_id' => $this->serieId > 0 ? $this->serieId : null,
            'periodo_tipo' => 'bimestre',
            'media_minima' => MEDIA_SEMESTRE,
            'escala' => '10',
            'frequencia_minima' => 75,
            'usar_frequencia' => 0,
            'round_mode' => 'none',
            'decimal_places' => 1,
            'formula_media' => null,
            'formula_final' => FORMULA_FINAL,
            'recuperacao_tipo' => 'periodo',
            'recuperacao_composicao' => 'maior_nota',
            'finalidade' => 'oficial',
            'vis_aluno' => 1,
            'vis_pais' => 1,
            'ativo' => 1,
            'materias_ids' => $materiasIds,
            'series_ids' => $this->serieId > 0 ? [$this->serieId] : [],
        ];
        if ($exist) {
            $id = (int) $exist['id'];
            $upd = $svc->atualizar($id, $payload);
            if (empty($upd['success'])) {
                fail('Atualizar regra: ' . (string) ($upd['error'] ?? 'falha'));
            }
            println('  regra de aprovação atualizada (#' . $id . ')');
            return $id;
        }
        $cri = $svc->criar($payload);
        if (empty($cri['success'])) {
            fail('Criar regra: ' . (string) ($cri['error'] ?? 'falha'));
        }
        $id = (int) ($cri['id'] ?? 0);
        println('  regra de aprovação criada (#' . $id . ')');
        return $id;
    }

    private function garantirRecuperacaoConfig(): void
    {
        if ($this->dryRun || $this->regraAcademicaId <= 0) {
            return;
        }
        $rec = new RecuperacaoConfig();
        $desejadas = [
            [
                'nome' => 'Recuperação semestral',
                'quando' => 'par',
                'aplica_em' => 'par',
                'nota_minima' => MEDIA_SEMESTRE,
                'nota_maxima' => 10.0,
                'efeito' => 'maior_nota',
                'formula' => null,
                'permite_aumentar' => 0,
            ],
            [
                'nome' => 'Prova Final',
                'quando' => 'final',
                'aplica_em' => null,
                'nota_minima' => MEDIA_SEMESTRE,
                'nota_maxima' => 10.0,
                'efeito' => 'maior_nota',
                'formula' => null,
                'permite_aumentar' => 0,
            ],
        ];
        $jaTem = [];
        foreach ($rec->listarDaRegra($this->regraAcademicaId) as $linha) {
            $jaTem[(string) ($linha['quando'] ?? '')] = (int) ($linha['id'] ?? 0);
        }
        foreach ($desejadas as $payload) {
            $quando = (string) $payload['quando'];
            $dados = array_merge($payload, [
                'ano_letivo' => ANO,
                'regra_id' => $this->regraAcademicaId,
            ]);
            if (!empty($jaTem[$quando])) {
                $rec->atualizar((int) $jaTem[$quando], $dados);
                println('  recuperação "' . $payload['nome'] . '" atualizada');
                continue;
            }
            $orfa = $this->db->fetch(
                "SELECT id FROM recuperacoes
                  WHERE ano_letivo = :a AND quando = :q AND deleted_at IS NULL
                    AND (regra_id IS NULL OR regra_id = 0)
                  ORDER BY id ASC LIMIT 1",
                ['a' => ANO, 'q' => $quando]
            );
            if ($orfa) {
                $rid = (int) $orfa['id'];
                $this->db->query(
                    'UPDATE recuperacoes SET regra_id = :r, nome = :n, nota_minima = :min, nota_maxima = :max,
                            efeito = :e, aplica_em = :ap, formula = :f, permite_aumentar = :pa
                      WHERE id = :id',
                    [
                        'r' => $this->regraAcademicaId,
                        'n' => $payload['nome'],
                        'min' => $payload['nota_minima'],
                        'max' => $payload['nota_maxima'],
                        'e' => $payload['efeito'],
                        'ap' => $payload['aplica_em'],
                        'f' => $payload['formula'],
                        'pa' => (int) $payload['permite_aumentar'],
                        'id' => $rid,
                    ]
                );
                println('  recuperação "' . $payload['nome'] . '" vinculada (#' . $rid . ')');
                continue;
            }
            $novoId = $rec->criar($dados);
            println('  recuperação "' . $payload['nome'] . '" criada (#' . $novoId . ')');
        }
    }

    private function criarEventosENotas(): void
    {
        $pares = $this->paresProfessorMateria();
        if ($pares === [] && !$this->dryRun) {
            fail('Sem pares professor/matéria para lançar notas.');
        }

        foreach ([1, 2, 3, 4] as $bimestre) {
            foreach ([
                'pp' => 'Prova Parcial',
                'pu' => 'Prova de Unidade',
                'ch' => '2ª Chamada',
                'pe' => 'Ponto Extra',
            ] as $slug => $nome) {
                $chave = $bimestre . ':' . $slug;
                $tituloEvento = PREFIXO . ' ' . $nome;
                $exist = $this->db->fetch(
                    'SELECT id FROM eventos_avaliativos
                      WHERE deleted_at IS NULL AND ano_letivo = :a AND bimestre = :b AND nome = :n LIMIT 1',
                    ['a' => ANO, 'b' => $bimestre, 'n' => $tituloEvento]
                );
                if (!$exist && $slug === 'ch') {
                    // Compatível com nome antigo sem acento.
                    $exist = $this->db->fetch(
                        'SELECT id FROM eventos_avaliativos
                          WHERE deleted_at IS NULL AND ano_letivo = :a AND bimestre = :b
                            AND (sigla = :s OR nome LIKE :n2) LIMIT 1',
                        ['a' => ANO, 'b' => $bimestre, 's' => 'CH', 'n2' => PREFIXO . '%Chamada%']
                    );
                }
                if ($exist) {
                    $this->eventoIds[$chave] = (int) $exist['id'];
                    println('  ' . $bimestre . 'º · ' . $nome . ' já existe (#' . $exist['id'] . ')');
                } elseif ($this->dryRun) {
                    println('  [dry-run] criaria ' . $bimestre . 'º · ' . $nome);
                } else {
                    $data = DATAS[$bimestre][$slug];
                    $sigla = strtoupper($slug);
                    $res = $this->eventos->criar([
                        'nome' => $tituloEvento,
                        'descricao' => $nome . ' do ' . $bimestre . 'º bimestre — seed Santa Maria.',
                        'ano_letivo' => ANO,
                        'bimestre' => $bimestre,
                        'ativo' => '1',
                        'canal_origem' => 'offline',
                        'modo_offline' => 'lancamento_direto',
                        'configuracao_nota' => 'coordenacao_calcula',
                        'tipo_prova' => 'original',
                        'escala_max' => '10',
                        'incluir_media' => '0',
                        'sigla' => $sigla,
                        'liberar_nota_aluno' => '1',
                        'turmas' => [$this->turmaId],
                        'pares_padrao' => $pares,
                        'subeventos' => [['titulo' => $nome, 'data' => $data]],
                    ], $this->adminId);
                    if (empty($res['success'])) {
                        fail($bimestre . 'º · ' . $nome . ': ' . (string) ($res['error'] ?? 'falha'));
                    }
                    $this->eventoIds[$chave] = (int) $res['id'];
                    println('  ' . $bimestre . 'º · ' . $nome . ' criado (#' . $res['id'] . ')');
                }
            }
        }

        $this->garantirEventoRecuperacao(
            81,
            '81:rec',
            PREFIXO . ' Recuperação semestral',
            'Recuperação do 1º semestre — seed Santa Maria.',
            'REC',
            'Recuperação 1º semestre',
            DATAS[1]['rec'],
            'rec_s1',
            $pares
        );
        $this->garantirEventoRecuperacao(
            82,
            '82:rec',
            PREFIXO . ' Recuperação 2º semestre',
            'Recuperação do 2º semestre — seed Santa Maria.',
            'REC',
            'Recuperação 2º semestre',
            DATAS[3]['rec'],
            null,
            $pares
        );
        $this->garantirEventoRecuperacao(
            90,
            '90:pf',
            PREFIXO . ' Prova Final',
            'Prova Final — seed Santa Maria.',
            'PF',
            'Prova Final',
            ANO . '-12-16',
            null,
            $pares
        );

        if ($this->dryRun) {
            return;
        }
        $this->mapearBlocos();
        $this->lancarNotas();
    }

    /**
     * @param list<array{professor_id:int,materia_id:int,turmas:list<int>}> $pares
     */
    private function garantirEventoRecuperacao(
        int $bimestreCodigo,
        string $chave,
        string $nome,
        string $descricao,
        string $sigla,
        string $tituloSub,
        string $data,
        ?string $campoNota,
        array $pares
    ): void {
        $exist = $this->db->fetch(
            'SELECT id FROM eventos_avaliativos
              WHERE deleted_at IS NULL AND ano_letivo = :a AND bimestre = :b AND nome = :n LIMIT 1',
            ['a' => ANO, 'b' => $bimestreCodigo, 'n' => $nome]
        );
        if ($exist) {
            $this->eventoIds[$chave] = (int) $exist['id'];
            println('  ' . $nome . ' já existe (#' . $exist['id'] . ')');
            return;
        }
        if ($this->dryRun) {
            println('  [dry-run] criaria ' . $nome);
            return;
        }
        $alunosRec = [];
        if ($campoNota !== null) {
            foreach (NOTAS as $materia => $dados) {
                if (($dados[$campoNota] ?? null) === null) {
                    continue;
                }
                $mid = $this->materiaIds[$materia] ?? 0;
                if ($mid > 0) {
                    $alunosRec[] = $this->alunoId . ':' . $mid;
                }
            }
        }
        $payload = [
            'nome' => $nome,
            'descricao' => $descricao,
            'ano_letivo' => ANO,
            'bimestre' => $bimestreCodigo,
            'ativo' => '1',
            'canal_origem' => 'offline',
            'modo_offline' => 'lancamento_direto',
            'configuracao_nota' => 'coordenacao_calcula',
            'tipo_prova' => 'original',
            'escala_max' => '10',
            'incluir_media' => '0',
            'sigla' => $sigla,
            'liberar_nota_aluno' => '1',
            'turmas' => [$this->turmaId],
            'pares_padrao' => $pares,
            'subeventos' => [['titulo' => $tituloSub, 'data' => $data]],
        ];
        if ($alunosRec !== []) {
            $payload['definir_alunos_recuperacao'] = '1';
            $payload['alunos_recuperacao'] = $alunosRec;
        }
        $res = $this->eventos->criar($payload, $this->adminId);
        if (empty($res['success']) && $alunosRec !== []) {
            unset($payload['definir_alunos_recuperacao'], $payload['alunos_recuperacao']);
            $res = $this->eventos->criar($payload, $this->adminId);
        }
        if (empty($res['success'])) {
            fail($nome . ': ' . (string) ($res['error'] ?? 'falha'));
        }
        $this->eventoIds[$chave] = (int) $res['id'];
        println('  ' . $nome . ' criada (#' . $res['id'] . ')');
    }

    /**
     * @return list<array{professor_id:int,materia_id:int,turmas:list<int>}>
     */
    private function paresProfessorMateria(): array
    {
        $pares = [];
        foreach (array_keys(NOTAS) as $materia) {
            $mid = $this->materiaIds[$materia] ?? 0;
            $pid = $this->professorPorMateria[$materia] ?? 0;
            if ($mid <= 0 || $pid <= 0) {
                continue;
            }
            $pares[] = [
                'professor_id' => $pid,
                'materia_id' => $mid,
                'turmas' => [$this->turmaId],
            ];
        }
        return $pares;
    }

    private function mapearBlocos(): void
    {
        $rows = $this->db->fetchAll(
            'SELECT e.id AS evento_id, e.bimestre, e.nome, e.sigla, pb.id AS bloco_id
               FROM eventos_avaliativos e
               INNER JOIN provas_blocos pb ON pb.evento_avaliativo_id = e.id AND pb.deleted_at IS NULL
              WHERE e.deleted_at IS NULL AND e.ano_letivo = :a AND e.nome LIKE :p',
            ['a' => ANO, 'p' => PREFIXO . ' %']
        ) ?: [];
        foreach ($rows as $r) {
            $bim = (int) $r['bimestre'];
            $sigla = strtolower((string) ($r['sigla'] ?? ''));
            if ($sigla === '') {
                $nomeEv = (string) ($r['nome'] ?? '');
                if (str_contains($nomeEv, 'Parcial')) {
                    $sigla = 'pp';
                } elseif (str_contains($nomeEv, 'Unidade')) {
                    $sigla = 'pu';
                } elseif (str_contains($nomeEv, 'Chamada') || str_contains(strtolower($nomeEv), '2a')) {
                    $sigla = 'ch';
                } elseif (str_contains($nomeEv, 'Ponto Extra')) {
                    $sigla = 'pe';
                } else {
                    $sigla = 'rec';
                }
            }
            $this->blocoIds[$bim . ':' . $sigla] = (int) $r['bloco_id'];
            $this->eventoIds[$bim . ':' . $sigla] = (int) $r['evento_id'];
        }
    }

    private function lancarNotas(): void
    {
        $inseridas = 0;
        foreach (NOTAS as $materia => $dados) {
            $mid = $this->materiaIds[$materia] ?? 0;
            $pid = $this->professorPorMateria[$materia] ?? 0;
            if ($mid <= 0 || $pid <= 0) {
                println('  pulou ' . $materia . ' (sem id)');
                continue;
            }
            foreach ([1, 2, 3, 4] as $bim) {
                foreach (['pp', 'pu', 'ch', 'pe'] as $slug) {
                    $nota = $dados[$slug][$bim] ?? null;
                    if ($slug === 'ch') {
                        $nota = CH_NOTAS[$materia][$bim] ?? $nota;
                    }
                    $blocoId = $this->blocoIds[$bim . ':' . $slug] ?? 0;
                    if ($blocoId <= 0) {
                        continue;
                    }
                    // Sem lançamento (PE/CH/PP/PU nulos) → remove a linha. Não grava 0:
                    // 0 só vale se o professor lançou zero de propósito.
                    if ($nota === null) {
                        $this->db->query(
                            'DELETE FROM provas_blocos_notas_lancadas
                              WHERE bloco_id = :b AND materia_id = :m AND aluno_id = :a',
                            ['b' => $blocoId, 'm' => $mid, 'a' => $this->alunoId]
                        );
                        continue;
                    }
                    $ja = $this->db->fetch(
                        'SELECT id FROM provas_blocos_notas_lancadas
                          WHERE bloco_id = :b AND materia_id = :m AND aluno_id = :a LIMIT 1',
                        ['b' => $blocoId, 'm' => $mid, 'a' => $this->alunoId]
                    );
                    if ($ja) {
                        $this->db->query(
                            'UPDATE provas_blocos_notas_lancadas
                                SET nota = :n, professor_id = :p, turma_id = :t
                              WHERE id = :id',
                            ['n' => $nota, 'p' => $pid, 't' => $this->turmaId, 'id' => (int) $ja['id']]
                        );
                    } else {
                        $this->notas->upsertLinhas($blocoId, $pid, $mid, [[
                            'turma_id' => $this->turmaId,
                            'aluno_id' => $this->alunoId,
                            'nota' => $nota,
                        ]]);
                    }
                    $inseridas++;
                }
            }
            $rec = $dados['rec_s1'];
            if ($rec !== null) {
                $blocoId = $this->blocoIds['81:rec'] ?? 0;
                if ($blocoId > 0) {
                    $this->notas->upsertLinhas($blocoId, $pid, $mid, [[
                        'turma_id' => $this->turmaId,
                        'aluno_id' => $this->alunoId,
                        'nota' => $rec,
                    ]]);
                    $inseridas++;
                }
            }
        }
        println('  notas gravadas/atualizadas: ' . $inseridas);
    }

    private function criarFaltasELancamentos(): void
    {
        println('  faltas por bimestre…');
        if ($this->turmaId <= 0 || $this->alunoId <= 0) {
            println('  pulou faltas (turma/aluno ausente)');
            return;
        }
        if ($this->dryRun) {
            foreach ([1, 2, 3, 4] as $bim) {
                println('  [dry-run] criaria ' . PREFIXO . ' Faltas ' . $bim . 'º bimestre ' . ANO);
            }
            return;
        }

        $criados = 0;
        $lancamentos = 0;
        foreach ([1, 2, 3, 4] as $bim) {
            $nome = sprintf('%s Faltas %dº bimestre %d', PREFIXO, $bim, ANO);
            // Só reaproveita o evento SM24 deste bimestre (nome exato) —
            // LIKE em "2024" misturava B1/B2/B4 no mesmo id.
            $exist = $this->db->fetch(
                'SELECT id FROM faltas_eventos WHERE nome = :n AND ano_letivo = :ano AND ativo = 1 LIMIT 1',
                ['n' => $nome, 'ano' => ANO]
            );
            $eventoId = $exist
                ? (int) $exist['id']
                : $this->faltas->createEvento(
                    $nome,
                    (string) $bim,
                    ANO,
                    [$this->turmaId],
                    $this->adminId > 0 ? $this->adminId : null,
                    [],
                    'manual'
                );
            if ($eventoId <= 0) {
                println('  B' . $bim . ': falha ao criar evento de faltas');
                continue;
            }
            // Garante turma SM24 no evento (mesmo se reaproveitou outro).
            $ev = $this->faltas->getEventoById($eventoId);
            $turmas = is_array($ev['turmas_ids'] ?? null) ? $ev['turmas_ids'] : [];
            if (!in_array($this->turmaId, $turmas, true)) {
                $turmas[] = $this->turmaId;
                $this->faltas->updateEvento(
                    $eventoId,
                    (string) ($ev['nome'] ?? $nome),
                    (string) ($ev['bimestre'] ?? (string) $bim),
                    ANO,
                    $turmas,
                    is_array($ev['materias_ids'] ?? null) ? $ev['materias_ids'] : [],
                    (string) ($ev['origem'] ?? 'manual')
                );
            }
            $this->faltasEventoIds[$bim] = $eventoId;
            $criados++;

            $porAluno = [$this->alunoId => []];
            foreach (NOTAS as $materia => $dados) {
                $mid = $this->materiaIds[$materia] ?? 0;
                if ($mid <= 0) {
                    continue;
                }
                $qtd = (int) ($dados['fal'][$bim] ?? 0);
                $porAluno[$this->alunoId][$mid] = (string) max(0, $qtd);
            }
            if ($porAluno[$this->alunoId] !== []) {
                $this->faltas->upsertLancamentos(
                    $eventoId,
                    $porAluno,
                    [],
                    $this->adminId > 0 ? $this->adminId : null
                );
                $lancamentos += count($porAluno[$this->alunoId]);
            }
            println('  B' . $bim . ': evento #' . $eventoId . ' (' . $nome . ')');
        }
        println('  faltas: ' . $criados . ' eventos, ' . $lancamentos . ' lançamentos (aluno)');
    }

}

$uso = 'Uso: php scripts/seed_santa_maria_ana_2024_base.php --db=NOME_DO_BANCO --confirmar [--dry-run]';
$dbName = '';
$dryRun = false;
$confirmar = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if ($arg === '--confirmar') {
        $confirmar = true;
        continue;
    }
    if (str_starts_with($arg, '--db=')) {
        $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', substr($arg, 5)) ?: '';
        continue;
    }
    fail('Argumento inválido: ' . $arg . PHP_EOL . $uso);
}

if ($dbName === '' || strlen($dbName) > 64) {
    fail('Informe o banco da escola com --db=NOME_DO_BANCO.' . PHP_EOL . $uso);
}
if (!$confirmar) {
    fail(
        'Por segurança, confirme com --confirmar.' . PHP_EOL
        . 'Este seed grava dados SM24 no banco "' . $dbName . '".' . PHP_EOL
        . $uso
    );
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');

try {
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    Database::setCurrentInstance(Database::createFromPdo($pdo, [
        'host' => $host,
        'port' => $port,
        'name' => $dbName,
        'user' => $dbUser,
        'pass' => $dbPass,
    ]));
} catch (Throwable $e) {
    fail('Não conectou em ' . $dbName . '. ' . $e->getMessage());
}

exit((new SeedSantaMariaAna2024Base(Database::getInstance(), $dryRun, $dbName))->executar());
