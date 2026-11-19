<?php
/**
 * Seed local do Colégio Educa: Ensino Médio trimestral 2023 (EM23).
 *
 * Cria/atualiza:
 * - ano letivo 2023 com 3 trimestres
 * - turmas 1A, 1B, 2A, 2B, 3A, 3B
 * - matérias isoladas EM23-*
 * - professores EM23-*
 * - ~18 alunos por turma (108 no total)
 * - regra acadêmica, recuperações, eventos avaliativos e notas
 * - regras de demonstrativo/boletim e resultados gerados
 *
 * Uso:
 *   php scripts/seed_em_trimestral_2023_educa.php
 *   php scripts/seed_em_trimestral_2023_educa.php --dry-run
 *   php scripts/seed_em_trimestral_2023_educa.php --ajuda
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

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

use App\Core\Database;
use App\Dominio\Escola\Models\ComponenteCurricular;
use App\Dominio\Escola\Services\AnoLetivoService;
use App\Dominio\Pessoas\Services\AlunoMovimentacaoService;
use App\Modulos\Boletins\Controllers\BoletimConfigController;
use App\Modulos\Boletins\Models\BoletimConfig;
use App\Modulos\Boletins\Services\BoletimCadastroService;
use App\Modulos\EventosAvaliativos\Models\BoletimLayout;
use App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService;
use App\Modulos\Provas\Models\ExamBlockManualGrade;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use App\Modulos\RegrasAcademicas\Services\RegraAcademicaService;

const TENANT_DB = 'educatudo_educa';
const ANO = 2023;
const PREFIXO = 'EM23';
const SENHA_PADRAO = 'Teste@123';
const CURSO_EM_ID = 2;
const ALUNOS_POR_TURMA = 18;

/** @var array<int, array{nome:string,serie_id:int,serie:string}> */
const TURMAS_EM = [
    1 => ['nome' => '1A', 'serie_id' => 5, 'serie' => '1ª Série EM'],
    2 => ['nome' => '1B', 'serie_id' => 5, 'serie' => '1ª Série EM'],
    3 => ['nome' => '2A', 'serie_id' => 6, 'serie' => '2ª Série EM'],
    4 => ['nome' => '2B', 'serie_id' => 6, 'serie' => '2ª Série EM'],
    5 => ['nome' => '3A', 'serie_id' => 7, 'serie' => '3ª Série EM'],
    6 => ['nome' => '3B', 'serie_id' => 7, 'serie' => '3ª Série EM'],
];

/** @var array<int, array{data_inicio:string,data_fim:string,dias_letivos:string}> */
const PERIODOS_2023 = [
    1 => ['data_inicio' => '2023-02-01', 'data_fim' => '2023-05-19', 'dias_letivos' => '66'],
    2 => ['data_inicio' => '2023-05-22', 'data_fim' => '2023-09-08', 'dias_letivos' => '70'],
    3 => ['data_inicio' => '2023-09-11', 'data_fim' => '2023-12-20', 'dias_letivos' => '70'],
];

/**
 * @var array<string, array{
 *   nome:string,
 *   codigo:string,
 *   sigla:string,
 *   area:string,
 *   cor:string,
 *   professor:string,
 *   email:string
 * }>
 */
const MATERIAS_EM23 = [
    'portugues' => [
        'nome' => 'Língua Portuguesa',
        'codigo' => 'EM23-POR',
        'sigla' => 'POR',
        'area' => 'linguagens',
        'cor' => '#1D4ED8',
        'professor' => 'Helena Azevedo',
        'email' => 'portugues.em23@educa.local',
    ],
    'matematica' => [
        'nome' => 'Matemática',
        'codigo' => 'EM23-MAT',
        'sigla' => 'MAT',
        'area' => 'matematica',
        'cor' => '#0F766E',
        'professor' => 'Marcos Tavares',
        'email' => 'matematica.em23@educa.local',
    ],
    'historia' => [
        'nome' => 'História',
        'codigo' => 'EM23-HIS',
        'sigla' => 'HIS',
        'area' => 'ciencias_humanas',
        'cor' => '#B45309',
        'professor' => 'Hugo Sampaio',
        'email' => 'historia.em23@educa.local',
    ],
    'geografia' => [
        'nome' => 'Geografia',
        'codigo' => 'EM23-GEO',
        'sigla' => 'GEO',
        'area' => 'ciencias_humanas',
        'cor' => '#3F6212',
        'professor' => 'Paulo Vieira',
        'email' => 'geografia.em23@educa.local',
    ],
    'fisica' => [
        'nome' => 'Física',
        'codigo' => 'EM23-FIS',
        'sigla' => 'FIS',
        'area' => 'ciencias_natureza',
        'cor' => '#7C3AED',
        'professor' => 'Andre Pacheco',
        'email' => 'fisica.em23@educa.local',
    ],
    'quimica' => [
        'nome' => 'Química',
        'codigo' => 'EM23-QUI',
        'sigla' => 'QUI',
        'area' => 'ciencias_natureza',
        'cor' => '#DC2626',
        'professor' => 'Renata Blanco',
        'email' => 'quimica.em23@educa.local',
    ],
    'biologia' => [
        'nome' => 'Biologia',
        'codigo' => 'EM23-BIO',
        'sigla' => 'BIO',
        'area' => 'ciencias_natureza',
        'cor' => '#059669',
        'professor' => 'Natalia Correia',
        'email' => 'biologia.em23@educa.local',
    ],
    'sociologia' => [
        'nome' => 'Sociologia',
        'codigo' => 'EM23-SOC',
        'sigla' => 'SOC',
        'area' => 'ciencias_humanas',
        'cor' => '#9333EA',
        'professor' => 'Carla Nunes',
        'email' => 'sociologia.em23@educa.local',
    ],
    'filosofia' => [
        'nome' => 'Filosofia',
        'codigo' => 'EM23-FIL',
        'sigla' => 'FIL',
        'area' => 'ciencias_humanas',
        'cor' => '#7C2D12',
        'professor' => 'Diego Martins',
        'email' => 'filosofia.em23@educa.local',
    ],
    'arte' => [
        'nome' => 'Arte',
        'codigo' => 'EM23-ART',
        'sigla' => 'ART',
        'area' => 'linguagens',
        'cor' => '#DB2777',
        'professor' => 'Lucia Prado',
        'email' => 'arte.em23@educa.local',
    ],
    'ed_fisica' => [
        'nome' => 'Educação Física',
        'codigo' => 'EM23-EDF',
        'sigla' => 'EDF',
        'area' => 'linguagens',
        'cor' => '#EA580C',
        'professor' => 'Erica Fontes',
        'email' => 'edfisica.em23@educa.local',
    ],
    'ingles' => [
        'nome' => 'Língua Inglesa',
        'codigo' => 'EM23-ING',
        'sigla' => 'ING',
        'area' => 'linguagens',
        'cor' => '#2563EB',
        'professor' => 'Camila Rocha',
        'email' => 'ingles.em23@educa.local',
    ],
];

/** @var array<int, array<string, string>> */
const DATAS_TRIMESTRES = [
    1 => [
        'PP1' => '2023-02-20',
        'PP2' => '2023-03-13',
        'PP3' => '2023-04-03',
        'PP4' => '2023-04-24',
        'TR' => '2023-04-10',
        'SIM' => '2023-04-17',
        'PT' => '2023-05-08',
        'REC' => '2023-05-15',
    ],
    2 => [
        'PP1' => '2023-05-29',
        'PP2' => '2023-06-19',
        'PP3' => '2023-07-10',
        'PP4' => '2023-08-07',
        'TR' => '2023-07-24',
        'SIM' => '2023-08-14',
        'PT' => '2023-08-28',
        'REC' => '2023-09-04',
    ],
    3 => [
        'PP1' => '2023-09-18',
        'PP2' => '2023-10-09',
        'PP3' => '2023-10-30',
        'PP4' => '2023-11-13',
        'TR' => '2023-11-06',
        'SIM' => '2023-11-20',
        'PT' => '2023-12-04',
        'REC' => '2023-12-11',
    ],
];

const DATA_REC_FINAL = '2023-12-15';

/** @var array<string, array{nome:string,peso:int}> */
const EVENTOS_BASE = [
    'PP1' => ['nome' => 'Prova Parcial 1', 'peso' => 1],
    'PP2' => ['nome' => 'Prova Parcial 2', 'peso' => 1],
    'PP3' => ['nome' => 'Prova Parcial 3', 'peso' => 1],
    'PP4' => ['nome' => 'Prova Parcial 4', 'peso' => 1],
    'TR' => ['nome' => 'Trabalho', 'peso' => 1],
    'SIM' => ['nome' => 'Simulado', 'peso' => 2],
    'PT' => ['nome' => 'Prova Trimestral', 'peso' => 3],
];

/** @var list<string> */
const NOMES = [
    'Ana', 'Beatriz', 'Bianca', 'Bruno', 'Camila', 'Caio', 'Carla', 'Clara', 'Daniel', 'Diego',
    'Eduarda', 'Elisa', 'Felipe', 'Fernanda', 'Gabriel', 'Gabriela', 'Helena', 'Henrique', 'Igor', 'Isabela',
    'Joao', 'Julia', 'Karina', 'Larissa', 'Laura', 'Leonardo', 'Leticia', 'Lucas', 'Luiza', 'Manuela',
    'Marcos', 'Marina', 'Mateus', 'Natalia', 'Nicolas', 'Olivia', 'Pedro', 'Rafaela', 'Samuel', 'Sofia',
    'Talita', 'Thiago', 'Valentina', 'Vanessa', 'Vitor', 'Yasmin',
];

/** @var list<string> */
const SOBRENOMES_1 = [
    'Almeida', 'Andrade', 'Araujo', 'Barbosa', 'Campos', 'Cardoso', 'Castro', 'Costa', 'Dias', 'Ferreira',
    'Freitas', 'Gomes', 'Lima', 'Lopes', 'Machado', 'Martins', 'Melo', 'Mendes', 'Moreira', 'Nogueira',
    'Oliveira', 'Pereira', 'Queiroz', 'Ribeiro', 'Rocha', 'Santana', 'Santos', 'Silva', 'Souza', 'Vieira',
];

/** @var list<string> */
const SOBRENOMES_2 = [
    'Azevedo', 'Barros', 'Brito', 'Correia', 'Cunha', 'Duarte', 'Farias', 'Fonseca', 'Leite', 'Lemos',
    'Marques', 'Monteiro', 'Moraes', 'Moura', 'Neves', 'Pacheco', 'Prado', 'Ramos', 'Rezende', 'Teixeira',
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

function mostrarUso(): void
{
    echo <<<TXT
Seed EM23 — Ensino Médio trimestral 2023 do Colégio Educa.

Uso:
  php scripts/seed_em_trimestral_2023_educa.php
  php scripts/seed_em_trimestral_2023_educa.php --dry-run
  php scripts/seed_em_trimestral_2023_educa.php --ajuda

Regras:
  - roda apenas em CLI
  - só conecta no tenant educatudo_educa
  - aborta se DB_HOST não parecer local
  - é idempotente

TXT;
}

/**
 * @param list<string> $argv
 * @return array{dry_run:bool}
 */
function lerOpcoes(array $argv): array
{
    $dryRun = false;
    foreach (array_slice($argv, 1) as $arg) {
        if (in_array($arg, ['--ajuda', '--help', '-h'], true)) {
            mostrarUso();
            exit(0);
        }
        if ($arg === '--dry-run') {
            $dryRun = true;
            continue;
        }
        fail('Argumento inválido: ' . $arg . PHP_EOL . 'Use --ajuda para ver as opções.');
    }
    return ['dry_run' => $dryRun];
}

final class SeedEmTrimestral2023Educa
{
    private AnoLetivoService $anoLetivo;
    private AlunoMovimentacaoService $movimentacao;
    private ComponenteCurricular $materiasModel;
    private EventoAvaliativoService $eventos;
    private ExamBlockManualGrade $notas;
    private BoletimConfig $boletim;
    private BoletimConfigController $boletimCtrl;
    private RegraAcademicaService $regraService;
    private RecuperacaoConfig $recuperacaoModel;
    private ?BoletimCadastroService $boletimCadastro = null;
    private ?BoletimLayout $boletimLayout = null;

    private int $adminId = 0;
    private int $anoId = 0;
    private int $unidadeEmId = 0;
    private int $regraAcademicaId = 0;
    private int $modeloBoletimId = 0;
    private int $layoutBoletimId = 0;

    /** @var array<int,int> serie_id => matriz_id */
    private array $matrizPorSerie = [];
    /** @var array<string,int> codigo => materia_id */
    private array $materiaIdPorCodigo = [];
    /** @var array<string,int> codigo => professor_id */
    private array $professorIdPorCodigo = [];
    /** @var array<string,int> nome_turma => turma_id */
    private array $turmaIdPorNome = [];
    /** @var array<int, array{turma_id:int,nickname:string,nome:string,turma_nome:string}> */
    private array $alunos = [];
    /** @var array<string,int> chave evento => evento_id */
    private array $eventoIdPorChave = [];
    /** @var array<string,int> chave evento => bloco_id */
    private array $blocoIdPorChave = [];
    /** @var array<int, array<int, array{trimestres:array<int, array{eventos:array<string,float>, media:float, rec:?float, media_pos_rec:float}>, media_anual:float, rec_final:?float, media_final:float}>> */
    private array $planoNotas = [];
    /** @var array<int, list<string>> trimestre => ["aluno_id:materia_id"] */
    private array $paresRecuperacaoTrimestre = [];
    /** @var list<string> */
    private array $paresRecuperacaoFinal = [];
    /** @var array<string,int> codigo_regra => regra_id */
    private array $regrasBoletim = [];
    /** @var array<string,int|bool> */
    private array $stats = [
        'materias_criadas' => 0,
        'professores_criados' => 0,
        'turmas_criadas' => 0,
        'alunos_criados' => 0,
        'eventos_criados' => 0,
        'eventos_pp_antigos_desativados' => 0,
        'blocos_pp_antigos_desativados' => 0,
        'regras_salvas' => 0,
        'resultados_gerados' => 0,
        'erros_boletim' => 0,
        'layout_criado' => 0,
        'modelo_criado' => 0,
    ];

    public function __construct(private Database $db, private bool $dryRun)
    {
        $this->anoLetivo = new AnoLetivoService($db);
        $this->movimentacao = new AlunoMovimentacaoService();
        $this->materiasModel = new ComponenteCurricular();
        $this->eventos = new EventoAvaliativoService();
        $this->notas = new ExamBlockManualGrade();
        $this->boletim = new BoletimConfig();
        $this->boletim->ensureSchema();
        $this->boletimCtrl = $this->montarBoletimController();
        $this->regraService = new RegraAcademicaService();
        $this->recuperacaoModel = new RecuperacaoConfig();

        try {
            $this->boletimCadastro = new BoletimCadastroService();
        } catch (Throwable $e) {
            $this->boletimCadastro = null;
        }
        try {
            $this->boletimLayout = new BoletimLayout();
        } catch (Throwable $e) {
            $this->boletimLayout = null;
        }
    }

    public function executar(): int
    {
        println('== Seed EM23 · Ensino Médio trimestral 2023 · Colégio Educa ==');
        if ($this->dryRun) {
            println('Modo dry-run: nenhuma gravação será feita.');
        }

        $this->adminId = $this->resolverAdmin();
        $this->carregarEstrutura();
        $this->garantirAnoLetivo();
        $this->garantirTurmas();
        $this->garantirMaterias();
        $this->garantirProfessores();
        $this->garantirAlunos();
        $this->planejarNotas();
        $this->garantirRegraAcademica();
        $this->garantirRecuperacoes();
        $this->garantirEventos();
        $this->mapearBlocos();
        $this->lancarNotas();
        $this->salvarRegrasBoletim();
        $this->configurarModeloELayout();
        $this->gerarResultadosBoletim();
        $this->resumo();

        $errosBoletim = (int) ($this->stats['erros_boletim'] ?? 0);
        if ($errosBoletim > 0) {
            println('  Atenção: ' . $errosBoletim . ' falha(s) ao gerar boletim.');
            return 1;
        }

        return 0;
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

    private function resolverAdmin(): int
    {
        $row = $this->db->fetch(
            "SELECT id
               FROM usuarios
              WHERE COALESCE(ativo, 1) = 1
                AND (tipo = 'admin_escola' OR email LIKE 'admin@%')
              ORDER BY id ASC
              LIMIT 1"
        );
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            fail('Nenhum admin ativo encontrado no Colégio Educa.');
        }
        println('  admin_id=' . $id);
        return $id;
    }

    private function carregarEstrutura(): void
    {
        foreach ([5, 6, 7] as $serieId) {
            $matriz = $this->db->fetch(
                'SELECT id FROM matrizes_curriculares WHERE serie_id = :serie AND curso_id = :curso ORDER BY id ASC LIMIT 1',
                ['serie' => $serieId, 'curso' => CURSO_EM_ID]
            );
            if (!$matriz) {
                $matriz = $this->db->fetch(
                    'SELECT matriz_curricular_id AS id FROM turmas WHERE serie_id = :serie AND matriz_curricular_id IS NOT NULL ORDER BY id ASC LIMIT 1',
                    ['serie' => $serieId]
                );
            }
            $matrizId = (int) ($matriz['id'] ?? 0);
            if ($matrizId <= 0) {
                fail('Não encontrei matriz curricular para a série #' . $serieId . '.');
            }
            $this->matrizPorSerie[$serieId] = $matrizId;
        }

        if ($this->temTabela('unidades')) {
            $unidade = $this->db->fetch(
                "SELECT id
                   FROM unidades
                  WHERE nome LIKE :q
                  ORDER BY id ASC
                  LIMIT 1",
                ['q' => '%Médio%']
            );
            if (!$unidade) {
                $unidade = $this->db->fetch('SELECT id FROM unidades ORDER BY id ASC LIMIT 1');
            }
            $this->unidadeEmId = (int) ($unidade['id'] ?? 0);
        }
    }

    private function garantirAnoLetivo(): void
    {
        $payload = [
            'ano' => ANO,
            'data_inicio' => PERIODOS_2023[1]['data_inicio'],
            'data_fim' => PERIODOS_2023[3]['data_fim'],
            'periodo_tipo' => 'trimestre',
            'ativo' => 1,
            'periodos' => PERIODOS_2023,
        ];
        $row = $this->db->fetch('SELECT id FROM ano_letivo WHERE ano = :ano LIMIT 1', ['ano' => ANO]);
        if ($row) {
            $this->anoId = (int) $row['id'];
            if ($this->dryRun) {
                println('  [dry-run] ajustaria ano letivo 2023 (#' . $this->anoId . ') para trimestre');
                return;
            }
            $this->anoLetivo->atualizar($this->anoId, $payload);
            println('  ano letivo 2023 atualizado (#' . $this->anoId . ')');
            return;
        }

        if ($this->dryRun) {
            println('  [dry-run] criaria ano letivo 2023 trimestral');
            return;
        }

        $criado = $this->anoLetivo->cadastrar($payload);
        $this->anoId = (int) ($criado['id'] ?? 0);
        println('  ano letivo 2023 criado (#' . $this->anoId . ')');
    }

    private function garantirTurmas(): void
    {
        foreach (TURMAS_EM as $def) {
            $nome = $def['nome'];
            $row = $this->db->fetch(
                'SELECT id FROM turmas WHERE nome = :nome AND ano_letivo = :ano LIMIT 1',
                ['nome' => $nome, 'ano' => ANO]
            );
            $params = [
                'nome' => $nome,
                'ano' => ANO,
                'ano_id' => $this->anoId > 0 ? $this->anoId : null,
                'serie' => $def['serie'],
                'serie_id' => $def['serie_id'],
                'matriz' => $this->matrizPorSerie[$def['serie_id']] ?? null,
                'curso' => CURSO_EM_ID,
                'turno' => 'manha',
                'obs' => PREFIXO . ' trimestral 2023',
            ];
            if ($row) {
                $id = (int) $row['id'];
                $this->turmaIdPorNome[$nome] = $id;
                if ($this->dryRun) {
                    continue;
                }
                $this->db->query(
                    'UPDATE turmas
                        SET serie = :serie,
                            serie_id = :serie_id,
                            ano_letivo = :ano,
                            ano_letivo_id = :ano_id,
                            matriz_curricular_id = :matriz,
                            curso_novo_id = :curso,
                            ativo = 1,
                            turno = :turno,
                            vagas = 40,
                            observacoes = :obs
                      WHERE id = :id',
                    $params + ['id' => $id]
                );
                continue;
            }

            if ($this->dryRun) {
                println('  [dry-run] criaria turma ' . $nome . ' (' . $def['serie'] . ')');
                continue;
            }

            $id = (int) $this->db->insert(
                'INSERT INTO turmas
                    (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, turno, vagas, observacoes)
                 VALUES
                    (:nome, :ano, :ano_id, :serie, :serie_id, :matriz, :curso, 1, :turno, 40, :obs)',
                $params
            );
            $this->turmaIdPorNome[$nome] = $id;
            $this->stats['turmas_criadas']++;
            println('  turma criada: ' . $nome . ' (#' . $id . ')');
        }
    }

    private function garantirMaterias(): void
    {
        $ordem = 220;
        foreach (MATERIAS_EM23 as $meta) {
            $row = $this->db->fetch(
                'SELECT id FROM materias WHERE codigo = :codigo LIMIT 1',
                ['codigo' => $meta['codigo']]
            );
            if ($row) {
                $id = (int) $row['id'];
                $this->materiaIdPorCodigo[$meta['codigo']] = $id;
                if (!$this->dryRun) {
                    $this->db->query(
                        'UPDATE materias
                            SET nome = :nome,
                                sigla = :sigla,
                                area_conhecimento = :area,
                                descricao = :descricao,
                                cor = :cor,
                                ordem = :ordem,
                                permite_avaliacao = 1,
                                permite_frequencia = 1,
                                permite_plano_aula = 1,
                                permite_diario = 1,
                                ativo = 1,
                                pai_id = NULL
                          WHERE id = :id',
                        [
                            'nome' => $meta['nome'],
                            'sigla' => $meta['sigla'],
                            'area' => $meta['area'],
                            'descricao' => 'Seed ' . PREFIXO . ' 2023',
                            'cor' => $meta['cor'],
                            'ordem' => $ordem,
                            'id' => $id,
                        ]
                    );
                }
                $ordem++;
                continue;
            }

            if ($this->dryRun) {
                println('  [dry-run] criaria matéria ' . $meta['codigo'] . ' · ' . $meta['nome']);
                $ordem++;
                continue;
            }

            $id = (int) $this->materiasModel->create([
                'nome' => $meta['nome'],
                'codigo' => $meta['codigo'],
                'sigla' => $meta['sigla'],
                'area_conhecimento' => $meta['area'],
                'tipo' => 'formacao_geral',
                'etapa_infantil' => 'nao_aplica',
                'etapa_fund_i' => 'nao_aplica',
                'etapa_fund_ii' => 'nao_aplica',
                'etapa_medio' => 'obrigatoria',
                'descricao' => 'Seed ' . PREFIXO . ' 2023',
                'cor' => $meta['cor'],
                'ordem' => $ordem,
                'permite_avaliacao' => 1,
                'permite_frequencia' => 1,
                'permite_plano_aula' => 1,
                'permite_diario' => 1,
                'ativo' => 1,
                'pai_id' => null,
            ]);
            $this->materiaIdPorCodigo[$meta['codigo']] = $id;
            $this->stats['materias_criadas']++;
            println('  matéria criada: ' . $meta['codigo'] . ' (#' . $id . ')');
            $ordem++;
        }
    }

    private function garantirProfessores(): void
    {
        $turmasJson = $this->json(array_values(array_map('intval', $this->turmaIdPorNome)));
        $hash = password_hash(SENHA_PADRAO, PASSWORD_DEFAULT);

        foreach (MATERIAS_EM23 as $meta) {
            $codigoProf = $meta['codigo'];
            $materiasJson = $this->json([$meta['nome']]);
            $row = $this->db->fetch(
                'SELECT id FROM professores WHERE codigo_prof = :codigo LIMIT 1',
                ['codigo' => $codigoProf]
            );
            if ($row) {
                $id = (int) $row['id'];
                $this->professorIdPorCodigo[$codigoProf] = $id;
                if (!$this->dryRun) {
                    $this->db->query(
                        'UPDATE professores
                            SET nome = :nome,
                                email = :email,
                                materias = :materias,
                                turmas = :turmas,
                                ativo = 1,
                                pagante = 1
                          WHERE id = :id',
                        [
                            'nome' => $meta['professor'],
                            'email' => $meta['email'],
                            'materias' => $materiasJson,
                            'turmas' => $turmasJson,
                            'id' => $id,
                        ]
                    );
                }
                continue;
            }

            if ($this->dryRun) {
                println('  [dry-run] criaria professor ' . $codigoProf . ' · ' . $meta['professor']);
                continue;
            }

            $id = (int) $this->db->insert(
                'INSERT INTO professores
                    (nome, email, senha_hash, codigo_prof, materias, turmas, ativo, pagante, password)
                 VALUES
                    (:nome, :email, :senha, :codigo, :materias, :turmas, 1, 1, :password)',
                [
                    'nome' => $meta['professor'],
                    'email' => $meta['email'],
                    'senha' => $hash,
                    'codigo' => $codigoProf,
                    'materias' => $materiasJson,
                    'turmas' => $turmasJson,
                    'password' => '',
                ]
            );
            $this->professorIdPorCodigo[$codigoProf] = $id;
            $this->stats['professores_criados']++;
            println('  professor criado: ' . $meta['professor'] . ' (#' . $id . ')');
        }
    }

    private function garantirAlunos(): void
    {
        $hash = password_hash(SENHA_PADRAO, PASSWORD_DEFAULT);
        $temStatus = $this->temColuna('alunos', 'status');
        $temPassword = $this->temColuna('alunos', 'password');
        $temPrimeiroAcesso = $this->temColuna('alunos', 'primeiro_acesso');
        $temObservacoes = $this->temColuna('alunos', 'observacoes');
        $temUnidade = $this->temColuna('alunos', 'unidade_id');

        $ordemGlobal = 0;
        foreach (TURMAS_EM as $def) {
            $turmaNome = $def['nome'];
            $turmaId = (int) ($this->turmaIdPorNome[$turmaNome] ?? 0);
            if ($turmaId <= 0) {
                continue;
            }
            for ($slot = 1; $slot <= ALUNOS_POR_TURMA; $slot++) {
                $ordemGlobal++;
                $nickname = 'em23.' . strtolower($turmaNome) . sprintf('%02d', $slot);
                $ra = 'EM23' . strtoupper($turmaNome) . sprintf('%02d', $slot);
                $nome = $this->nomeAluno($ordemGlobal);
                $dataNasc = $this->dataNascimentoAluno($def['serie_id'], $slot);
                $row = $this->db->fetch(
                    'SELECT id FROM alunos WHERE nickname = :nick OR ra = :ra ORDER BY id ASC LIMIT 1',
                    ['nick' => $nickname, 'ra' => $ra]
                );

                if ($row) {
                    $alunoId = (int) $row['id'];
                    if (!$this->dryRun) {
                        $params = [
                            'nome' => $nome,
                            'nick' => $nickname,
                            'email' => $nickname . '@educa.local',
                            'ra' => $ra,
                            'turma' => $turmaId,
                            'serie' => $def['serie'],
                            'nasc' => $dataNasc,
                            'id' => $alunoId,
                        ];
                        $sql = 'UPDATE alunos
                                   SET nome = :nome,
                                       nickname = :nick,
                                       email = :email,
                                       ra = :ra,
                                       turma_id = :turma,
                                       serie = :serie,
                                       data_nasc = :nasc,
                                       ativo = 1,
                                       pagante = 1';
                        if ($temStatus) {
                            $sql .= ", status = 'ACTIVE'";
                        }
                        if ($temUnidade && $this->unidadeEmId > 0) {
                            $sql .= ', unidade_id = :unidade';
                            $params['unidade'] = $this->unidadeEmId;
                        }
                        if ($temObservacoes) {
                            $sql .= ', observacoes = :obs';
                            $params['obs'] = PREFIXO . ' · seed trimestral 2023';
                        }
                        $sql .= ' WHERE id = :id';
                        $this->db->query($sql, $params);
                        $this->matricularAluno($alunoId, $turmaId);
                    }
                    $this->alunos[$alunoId] = [
                        'turma_id' => $turmaId,
                        'nickname' => $nickname,
                        'nome' => $nome,
                        'turma_nome' => $turmaNome,
                    ];
                    continue;
                }

                if ($this->dryRun) {
                    continue;
                }

                $cols = [
                    'nome', 'nickname', 'email', 'senha_hash', 'ra', 'turma_id', 'serie',
                    'data_nasc', 'ativo', 'pagante',
                ];
                $params = [
                    'nome' => $nome,
                    'nickname' => $nickname,
                    'email' => $nickname . '@educa.local',
                    'senha_hash' => $hash,
                    'ra' => $ra,
                    'turma_id' => $turmaId,
                    'serie' => $def['serie'],
                    'data_nasc' => $dataNasc,
                    'ativo' => 1,
                    'pagante' => 1,
                ];
                if ($temStatus) {
                    $cols[] = 'status';
                    $params['status'] = 'ACTIVE';
                }
                if ($temPassword) {
                    $cols[] = 'password';
                    $params['password'] = '';
                }
                if ($temPrimeiroAcesso) {
                    $cols[] = 'primeiro_acesso';
                    $params['primeiro_acesso'] = 0;
                }
                if ($temUnidade && $this->unidadeEmId > 0) {
                    $cols[] = 'unidade_id';
                    $params['unidade_id'] = $this->unidadeEmId;
                }
                if ($temObservacoes) {
                    $cols[] = 'observacoes';
                    $params['observacoes'] = PREFIXO . ' · seed trimestral 2023';
                }

                $placeholders = implode(', ', array_map(static fn (string $col): string => ':' . $col, $cols));
                $alunoId = (int) $this->db->insert(
                    'INSERT INTO alunos (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')',
                    $params
                );
                $this->matricularAluno($alunoId, $turmaId);
                $this->alunos[$alunoId] = [
                    'turma_id' => $turmaId,
                    'nickname' => $nickname,
                    'nome' => $nome,
                    'turma_nome' => $turmaNome,
                ];
                $this->stats['alunos_criados']++;
            }
        }

        println('  alunos EM23 mapeados: ' . count($this->alunos));
    }

    private function matricularAluno(int $alunoId, int $turmaId): void
    {
        if ($alunoId <= 0 || $turmaId <= 0 || $this->anoId <= 0) {
            return;
        }
        try {
            $this->movimentacao->vincularAlunoTurma($alunoId, $turmaId, $this->anoId, true, PERIODOS_2023[1]['data_inicio']);
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'já possui matrícula') === false) {
                println('  aviso matrícula aluno #' . $alunoId . ': ' . $e->getMessage());
            }
        }
    }

    private function planejarNotas(): void
    {
        if ($this->alunos === [] || $this->materiaIdPorCodigo === []) {
            return;
        }

        $this->paresRecuperacaoTrimestre = [1 => [], 2 => [], 3 => []];
        $this->paresRecuperacaoFinal = [];

        foreach ($this->alunos as $alunoId => $aluno) {
            foreach (MATERIAS_EM23 as $meta) {
                $materiaId = (int) ($this->materiaIdPorCodigo[$meta['codigo']] ?? 0);
                if ($materiaId <= 0) {
                    continue;
                }
                $plano = [
                    'trimestres' => [],
                    'media_anual' => 0.0,
                    'rec_final' => null,
                    'media_final' => 0.0,
                ];

                $somaMedias = 0.0;
                foreach ([1, 2, 3] as $tri) {
                    $eventos = [];
                    foreach (EVENTOS_BASE as $sigla => $ev) {
                        $eventos[$sigla] = $this->notaDeterministica($aluno['nickname'], $meta['codigo'], $tri, $sigla);
                    }
                    $media = $this->mediaTrimestral($eventos);
                    $rec = null;
                    $mediaPosRec = $media;
                    if ($media < 60.0) {
                        $rec = $this->notaRecuperacaoTrimestral($aluno['nickname'], $meta['codigo'], $tri, $media);
                        $mediaPosRec = max($media, $rec);
                        $this->paresRecuperacaoTrimestre[$tri][] = $alunoId . ':' . $materiaId;
                    }
                    $plano['trimestres'][$tri] = [
                        'eventos' => $eventos,
                        'media' => $media,
                        'rec' => $rec,
                        'media_pos_rec' => $mediaPosRec,
                    ];
                    $somaMedias += $mediaPosRec;
                }

                $mediaAnual = round($somaMedias / 3, 1);
                $recFinal = null;
                $mediaFinal = $mediaAnual;
                if ($mediaAnual < 60.0) {
                    $recFinal = $this->notaRecuperacaoFinal($aluno['nickname'], $meta['codigo'], $mediaAnual);
                    $mediaFinal = max($mediaAnual, $recFinal);
                    $this->paresRecuperacaoFinal[] = $alunoId . ':' . $materiaId;
                }
                $plano['media_anual'] = $mediaAnual;
                $plano['rec_final'] = $recFinal;
                $plano['media_final'] = round($mediaFinal, 1);

                $this->planoNotas[$alunoId][$materiaId] = $plano;
            }
        }
    }

    private function garantirRegraAcademica(): void
    {
        $materiasIds = array_values(array_map('intval', $this->materiaIdPorCodigo));
        $payload = [
            'nome' => 'Aprovação EM Trimestral 2023',
            'codigo' => 'em23-aprovacao-2023',
            'ano_letivo' => ANO,
            'curso_id' => CURSO_EM_ID,
            'periodo_tipo' => 'trimestre',
            'media_minima' => 60,
            'escala' => '100',
            'frequencia_minima' => 75,
            'usar_frequencia' => 1,
            'round_mode' => 'none',
            'decimal_places' => 1,
            'formula_media' => null,
            'formula_final' => '(m1 + m2 + m3) / 3',
            'recuperacao_tipo' => 'periodo',
            'recuperacao_composicao' => 'maior_nota',
            'finalidade' => 'oficial',
            'vis_aluno' => 1,
            'vis_pais' => 1,
            'ativo' => 1,
            'materias_ids' => $materiasIds,
            'series_ids' => [5, 6, 7],
        ];

        $exist = $this->db->fetch(
            "SELECT id
               FROM regras_academicas
              WHERE codigo = :codigo
                 OR (nome = :nome AND ano_letivo = :ano)
              ORDER BY CASE WHEN codigo = :codigo2 THEN 0 ELSE 1 END, id ASC
              LIMIT 1",
            [
                'codigo' => 'em23-aprovacao-2023',
                'codigo2' => 'em23-aprovacao-2023',
                'nome' => 'Aprovação EM Trimestral 2023',
                'ano' => ANO,
            ]
        );

        if ($this->dryRun) {
            println('  [dry-run] ' . ($exist ? 'atualizaria' : 'criaria') . ' regra acadêmica EM23');
            $this->regraAcademicaId = (int) ($exist['id'] ?? 0);
            return;
        }

        if ($exist) {
            $this->regraAcademicaId = (int) $exist['id'];
            $res = $this->regraService->atualizar($this->regraAcademicaId, $payload);
            if (empty($res['success'])) {
                fail('Atualizar regra acadêmica: ' . (string) ($res['error'] ?? 'falha'));
            }
        } else {
            $res = $this->regraService->criar($payload);
            if (empty($res['success'])) {
                fail('Criar regra acadêmica: ' . (string) ($res['error'] ?? 'falha'));
            }
            $this->regraAcademicaId = (int) ($res['id'] ?? 0);
        }

        // O service zera codigo no normalizar(); grava o código canônico do seed.
        if ($this->regraAcademicaId > 0 && $this->temColuna('regras_academicas', 'codigo')) {
            $this->db->query(
                'UPDATE regras_academicas SET codigo = :codigo WHERE id = :id',
                ['codigo' => 'em23-aprovacao-2023', 'id' => $this->regraAcademicaId]
            );
        }

        println('  regra acadêmica #' . $this->regraAcademicaId);
    }

    private function garantirRecuperacoes(): void
    {
        if ($this->regraAcademicaId <= 0 && !$this->dryRun) {
            fail('Regra acadêmica EM23 não foi criada.');
        }

        $desejadas = [
            [
                'nome' => 'EM23 Recuperação do trimestre',
                'quando' => 'bimestre',
                'aplica_em' => null,
                'nota_minima' => 60.0,
                'nota_maxima' => 100.0,
                'efeito' => 'maior_nota',
                'formula' => null,
                'permite_aumentar' => 0,
            ],
            [
                'nome' => 'EM23 Recuperação final',
                'quando' => 'final',
                'aplica_em' => null,
                'nota_minima' => 60.0,
                'nota_maxima' => 100.0,
                'efeito' => 'maior_nota',
                'formula' => null,
                'permite_aumentar' => 0,
            ],
        ];

        if ($this->dryRun) {
            println('  [dry-run] atualizaria/criaria recuperações EM23');
            return;
        }

        $existentes = [];
        foreach ($this->recuperacaoModel->listarDaRegra($this->regraAcademicaId) as $linha) {
            $existentes[(string) ($linha['quando'] ?? '')] = (int) ($linha['id'] ?? 0);
        }

        foreach ($desejadas as $payload) {
            $quando = (string) $payload['quando'];
            $dados = $payload + [
                'ano_letivo' => ANO,
                'regra_id' => $this->regraAcademicaId,
            ];
            if (!empty($existentes[$quando])) {
                $this->recuperacaoModel->atualizar((int) $existentes[$quando], $dados);
                continue;
            }

            $orfa = $this->db->fetch(
                "SELECT id
                   FROM recuperacoes
                  WHERE ano_letivo = :ano
                    AND quando = :quando
                    AND nome = :nome
                    AND deleted_at IS NULL
                    AND (regra_id IS NULL OR regra_id = 0)
                  ORDER BY id ASC
                  LIMIT 1",
                [
                    'ano' => ANO,
                    'quando' => $quando,
                    'nome' => $payload['nome'],
                ]
            );
            if ($orfa) {
                $this->db->query(
                    'UPDATE recuperacoes
                        SET regra_id = :regra,
                            nome = :nome,
                            nota_minima = :nota_minima,
                            nota_maxima = :nota_maxima,
                            efeito = :efeito,
                            aplica_em = :aplica_em,
                            formula = :formula,
                            permite_aumentar = :permite_aumentar
                      WHERE id = :id',
                    [
                        'regra' => $this->regraAcademicaId,
                        'nome' => $payload['nome'],
                        'nota_minima' => $payload['nota_minima'],
                        'nota_maxima' => $payload['nota_maxima'],
                        'efeito' => $payload['efeito'],
                        'aplica_em' => $payload['aplica_em'],
                        'formula' => $payload['formula'],
                        'permite_aumentar' => $payload['permite_aumentar'],
                        'id' => (int) $orfa['id'],
                    ]
                );
                continue;
            }

            $this->recuperacaoModel->criar($dados);
        }

        println('  recuperações EM23 sincronizadas');
    }

    private function garantirEventos(): void
    {
        foreach ([1, 2, 3] as $tri) {
            $this->garantirEventoProvaParcial($tri);
            foreach (['TR', 'SIM', 'PT'] as $sigla) {
                $meta = EVENTOS_BASE[$sigla];
                $this->garantirEventoRegular($tri, $sigla, $meta['nome'], DATAS_TRIMESTRES[$tri][$sigla]);
            }
            $this->garantirEventoRecuperacaoTrimestre($tri);
        }
        $this->garantirEventoRecuperacaoFinal();
    }

    private function garantirEventoProvaParcial(int $tri): void
    {
        $this->desativarEventosParciaisAntigos($tri);

        $titulo = PREFIXO . ' T' . $tri . ' Prova Parcial';
        $chave = $this->chaveEvento($tri, 'PP');
        $row = $this->db->fetch(
            'SELECT id FROM eventos_avaliativos WHERE deleted_at IS NULL AND ano_letivo = :ano AND bimestre = :bim AND nome = :nome LIMIT 1',
            ['ano' => ANO, 'bim' => $tri, 'nome' => $titulo]
        );

        $payload = [
            'nome' => $titulo,
            'descricao' => PREFIXO . ' · provas parciais do ' . $tri . 'º trimestre de 2023.',
            'ano_letivo' => ANO,
            'bimestre' => $tri,
            'ativo' => '1',
            'canal_origem' => 'offline',
            'modo_offline' => 'eventos',
            'configuracao_nota' => 'coordenacao_calcula',
            'tipo_prova' => 'original',
            'escala_max' => '100',
            'incluir_media' => '1',
            'sigla' => 'PP',
            'liberar_nota_aluno' => '1',
            'turmas' => array_values(array_map('intval', $this->turmaIdPorNome)),
            'pares_padrao' => $this->paresProfessorMateria(),
            'subeventos' => $this->subeventosProvaParcial($tri),
        ];

        if ($row) {
            $eventoId = (int) $row['id'];
            $this->eventoIdPorChave[$chave] = $eventoId;
            if ($this->dryRun) {
                println('  [dry-run] atualizaria evento ' . $titulo . ' com 4 subeventos');
                return;
            }

            $payload['subeventos'] = $this->anexarIdsDosBlocosParciais($eventoId, $this->subeventosProvaParcial($tri));
            $res = $this->eventos->atualizar($eventoId, $payload, $this->adminId);
            if (empty($res['success'])) {
                fail('Atualizar evento "' . $titulo . '": ' . (string) ($res['error'] ?? 'falha'));
            }
            return;
        }

        if ($this->dryRun) {
            println('  [dry-run] criaria evento ' . $titulo . ' com 4 subeventos');
            return;
        }

        $res = $this->eventos->criar($payload, $this->adminId);
        if (empty($res['success'])) {
            fail('Criar evento "' . $titulo . '": ' . (string) ($res['error'] ?? 'falha'));
        }
        $this->eventoIdPorChave[$chave] = (int) ($res['id'] ?? 0);
        $this->stats['eventos_criados']++;
    }

    private function desativarEventosParciaisAntigos(int $tri): void
    {
        $nomes = $this->nomesEventosParciaisAntigos($tri);
        $params = [
            'ano' => ANO,
            'bim' => $tri,
            'nome1' => $nomes[0],
            'nome2' => $nomes[1],
            'nome3' => $nomes[2],
            'nome4' => $nomes[3],
        ];
        $rows = $this->db->fetchAll(
            'SELECT e.id AS evento_id, pb.id AS bloco_id
               FROM eventos_avaliativos e
               LEFT JOIN provas_blocos pb
                      ON pb.evento_avaliativo_id = e.id
                     AND pb.deleted_at IS NULL
              WHERE e.deleted_at IS NULL
                AND e.ano_letivo = :ano
                AND e.bimestre = :bim
                AND e.nome IN (:nome1, :nome2, :nome3, :nome4)',
            $params
        ) ?: [];

        $eventoIds = [];
        $blocoIds = [];
        foreach ($rows as $row) {
            $eventoId = (int) ($row['evento_id'] ?? 0);
            $blocoId = (int) ($row['bloco_id'] ?? 0);
            if ($eventoId > 0) {
                $eventoIds[$eventoId] = $eventoId;
            }
            if ($blocoId > 0) {
                $blocoIds[$blocoId] = $blocoId;
            }
        }

        if ($eventoIds === []) {
            return;
        }

        if ($this->dryRun) {
            println('  [dry-run] desativaria ' . count($eventoIds) . ' evento(s) PP antigo(s) e ' . count($blocoIds) . ' bloco(s) no T' . $tri);
            return;
        }

        try {
            $this->db->beginTransaction();
            if ($blocoIds !== []) {
                $inBlocos = $this->placeholders('bloco_pp_', array_values($blocoIds));
                $this->db->query(
                    'UPDATE provas_blocos
                        SET deleted_at = NOW(),
                            liberado = 0
                      WHERE deleted_at IS NULL
                        AND id IN (' . $inBlocos['sql'] . ')',
                    $inBlocos['params']
                );
            }

            $inEventos = $this->placeholders('evento_pp_', array_values($eventoIds));
            $this->db->query(
                'UPDATE eventos_avaliativos
                    SET deleted_at = NOW(),
                        ativo = 0
                  WHERE deleted_at IS NULL
                    AND id IN (' . $inEventos['sql'] . ')',
                $inEventos['params']
            );
            $this->db->commit();
        } catch (Throwable $e) {
            try {
                $this->db->rollback();
            } catch (Throwable $ignored) {
            }
            fail('Falha ao desativar PP antigos do T' . $tri . ': ' . $e->getMessage());
        }

        $this->stats['eventos_pp_antigos_desativados'] = (int) ($this->stats['eventos_pp_antigos_desativados'] ?? 0) + count($eventoIds);
        $this->stats['blocos_pp_antigos_desativados'] = (int) ($this->stats['blocos_pp_antigos_desativados'] ?? 0) + count($blocoIds);
        println('  eventos PP antigos desativados T' . $tri . ': ' . count($eventoIds) . ' evento(s), ' . count($blocoIds) . ' bloco(s)');
    }

    private function garantirEventoRegular(int $tri, string $sigla, string $nome, string $data): void
    {
        $titulo = PREFIXO . ' T' . $tri . ' ' . $nome;
        $chave = $this->chaveEvento($tri, $sigla);
        $row = $this->db->fetch(
            'SELECT id FROM eventos_avaliativos WHERE deleted_at IS NULL AND ano_letivo = :ano AND bimestre = :bim AND nome = :nome LIMIT 1',
            ['ano' => ANO, 'bim' => $tri, 'nome' => $titulo]
        );
        if ($row) {
            $this->eventoIdPorChave[$chave] = (int) $row['id'];
            return;
        }

        if ($this->dryRun) {
            println('  [dry-run] criaria evento ' . $titulo);
            return;
        }

        $res = $this->eventos->criar([
            'nome' => $titulo,
            'descricao' => PREFIXO . ' · ' . $nome . ' do ' . $tri . 'º trimestre de 2023.',
            'ano_letivo' => ANO,
            'bimestre' => $tri,
            'ativo' => '1',
            'canal_origem' => 'offline',
            'modo_offline' => 'lancamento_direto',
            'configuracao_nota' => 'coordenacao_calcula',
            'tipo_prova' => 'original',
            'escala_max' => '100',
            'incluir_media' => '0',
            'sigla' => $sigla,
            'liberar_nota_aluno' => '1',
            'turmas' => array_values(array_map('intval', $this->turmaIdPorNome)),
            'pares_padrao' => $this->paresProfessorMateria(),
            'subeventos' => [[
                'titulo' => $nome,
                'data' => $data,
            ]],
        ], $this->adminId);
        if (empty($res['success'])) {
            fail('Criar evento "' . $titulo . '": ' . (string) ($res['error'] ?? 'falha'));
        }
        $this->eventoIdPorChave[$chave] = (int) ($res['id'] ?? 0);
        $this->stats['eventos_criados']++;
    }

    private function garantirEventoRecuperacaoTrimestre(int $tri): void
    {
        $codigo = RecuperacaoConfig::CODIGO_CICLO_BASE + $tri;
        $titulo = PREFIXO . ' T' . $tri . ' Recuperação do trimestre';
        $chave = $this->chaveEvento($codigo, 'REC');
        $row = $this->db->fetch(
            'SELECT id FROM eventos_avaliativos WHERE deleted_at IS NULL AND ano_letivo = :ano AND bimestre = :bim AND nome = :nome LIMIT 1',
            ['ano' => ANO, 'bim' => $codigo, 'nome' => $titulo]
        );
        if ($row) {
            $this->eventoIdPorChave[$chave] = (int) $row['id'];
            return;
        }

        if ($this->dryRun) {
            println('  [dry-run] criaria evento ' . $titulo);
            return;
        }

        $res = $this->eventos->criar([
            'nome' => $titulo,
            'descricao' => PREFIXO . ' · recuperação do ' . $tri . 'º trimestre de 2023.',
            'ano_letivo' => ANO,
            'bimestre' => $codigo,
            'ativo' => '1',
            'canal_origem' => 'offline',
            'modo_offline' => 'lancamento_direto',
            'configuracao_nota' => 'coordenacao_calcula',
            'tipo_prova' => 'original',
            'escala_max' => '100',
            'incluir_media' => '0',
            'sigla' => 'REC',
            'liberar_nota_aluno' => '1',
            'definir_alunos_recuperacao' => '1',
            'alunos_recuperacao' => $this->paresRecuperacaoTrimestre[$tri] ?? [],
            'turmas' => array_values(array_map('intval', $this->turmaIdPorNome)),
            'pares_padrao' => $this->paresProfessorMateria(),
            'subeventos' => [[
                'titulo' => 'Recuperação',
                'data' => DATAS_TRIMESTRES[$tri]['REC'],
            ]],
        ], $this->adminId);
        if (empty($res['success'])) {
            fail('Criar recuperação do trimestre T' . $tri . ': ' . (string) ($res['error'] ?? 'falha'));
        }
        $this->eventoIdPorChave[$chave] = (int) ($res['id'] ?? 0);
        $this->stats['eventos_criados']++;
    }

    private function garantirEventoRecuperacaoFinal(): void
    {
        $codigo = RecuperacaoConfig::CODIGO_FINAL;
        $titulo = PREFIXO . ' Rec Final';
        $chave = $this->chaveEvento($codigo, 'REC');
        $row = $this->db->fetch(
            'SELECT id FROM eventos_avaliativos WHERE deleted_at IS NULL AND ano_letivo = :ano AND bimestre = :bim AND nome = :nome LIMIT 1',
            ['ano' => ANO, 'bim' => $codigo, 'nome' => $titulo]
        );
        if ($row) {
            $this->eventoIdPorChave[$chave] = (int) $row['id'];
            return;
        }

        if ($this->dryRun) {
            println('  [dry-run] criaria evento ' . $titulo);
            return;
        }

        $res = $this->eventos->criar([
            'nome' => $titulo,
            'descricao' => PREFIXO . ' · recuperação final anual de 2023.',
            'ano_letivo' => ANO,
            'bimestre' => $codigo,
            'ativo' => '1',
            'canal_origem' => 'offline',
            'modo_offline' => 'lancamento_direto',
            'configuracao_nota' => 'coordenacao_calcula',
            'tipo_prova' => 'original',
            'escala_max' => '100',
            'incluir_media' => '0',
            'sigla' => 'REC',
            'liberar_nota_aluno' => '1',
            'definir_alunos_recuperacao' => '1',
            'alunos_recuperacao' => $this->paresRecuperacaoFinal,
            'turmas' => array_values(array_map('intval', $this->turmaIdPorNome)),
            'pares_padrao' => $this->paresProfessorMateria(),
            'subeventos' => [[
                'titulo' => 'Recuperação final',
                'data' => DATA_REC_FINAL,
            ]],
        ], $this->adminId);
        if (empty($res['success'])) {
            fail('Criar recuperação final: ' . (string) ($res['error'] ?? 'falha'));
        }
        $this->eventoIdPorChave[$chave] = (int) ($res['id'] ?? 0);
        $this->stats['eventos_criados']++;
    }

    /**
     * @return list<array{professor_id:int,materia_id:int,turmas:list<int>}>
     */
    private function paresProfessorMateria(): array
    {
        $turmas = array_values(array_map('intval', $this->turmaIdPorNome));
        $pares = [];
        foreach (MATERIAS_EM23 as $meta) {
            $materiaId = (int) ($this->materiaIdPorCodigo[$meta['codigo']] ?? 0);
            $professorId = (int) ($this->professorIdPorCodigo[$meta['codigo']] ?? 0);
            if ($materiaId <= 0 || $professorId <= 0) {
                continue;
            }
            $pares[] = [
                'professor_id' => $professorId,
                'materia_id' => $materiaId,
                'turmas' => $turmas,
            ];
        }
        return $pares;
    }

    private function mapearBlocos(): void
    {
        if ($this->dryRun) {
            return;
        }
        $rows = $this->db->fetchAll(
            'SELECT e.id AS evento_id, e.bimestre, e.sigla, e.nome, pb.id AS bloco_id, pb.titulo
               FROM eventos_avaliativos e
               INNER JOIN provas_blocos pb ON pb.evento_avaliativo_id = e.id AND pb.deleted_at IS NULL
              WHERE e.deleted_at IS NULL
                AND e.ano_letivo = :ano
                AND (e.nome LIKE :pref OR e.nome = :rec_final)
              ORDER BY e.bimestre ASC, e.id ASC',
            [
                'ano' => ANO,
                'pref' => PREFIXO . ' %',
                'rec_final' => PREFIXO . ' Rec Final',
            ]
        ) ?: [];

        foreach ($rows as $row) {
            $bim = (int) ($row['bimestre'] ?? 0);
            $sigla = strtoupper(trim((string) ($row['sigla'] ?? '')));
            $nomeEvento = (string) ($row['nome'] ?? '');
            $eventoId = (int) ($row['evento_id'] ?? 0);
            $blocoId = (int) ($row['bloco_id'] ?? 0);

            if ($sigla === 'PP' || ($sigla === '' && stripos($nomeEvento, 'Prova Parcial') !== false)) {
                $this->eventoIdPorChave[$this->chaveEvento($bim, 'PP')] = $eventoId;
                $siglaParcial = $this->resolverSiglaParcialPorTitulo((string) ($row['titulo'] ?? ''));
                if ($siglaParcial === null) {
                    continue;
                }
                $this->blocoIdPorChave[$this->chaveEvento($bim, $siglaParcial)] = $blocoId;
                continue;
            }

            if ($sigla === '') {
                $sigla = str_contains($nomeEvento, 'Rec') ? 'REC' : 'PT';
            }
            $chave = $this->chaveEvento($bim, $sigla);
            $this->eventoIdPorChave[$chave] = $eventoId;
            $this->blocoIdPorChave[$chave] = $blocoId;
        }
    }

    private function lancarNotas(): void
    {
        if ($this->dryRun) {
            println('  [dry-run] lançaria notas nos eventos EM23');
            return;
        }

        foreach ([1, 2, 3] as $tri) {
            foreach (array_keys(EVENTOS_BASE) as $sigla) {
                $blocoId = (int) ($this->blocoIdPorChave[$this->chaveEvento($tri, $sigla)] ?? 0);
                if ($blocoId <= 0) {
                    fail('Bloco do evento ' . $tri . '/' . $sigla . ' não encontrado.');
                }
                foreach (MATERIAS_EM23 as $meta) {
                    $materiaId = (int) ($this->materiaIdPorCodigo[$meta['codigo']] ?? 0);
                    $professorId = (int) ($this->professorIdPorCodigo[$meta['codigo']] ?? 0);
                    if ($materiaId <= 0 || $professorId <= 0) {
                        continue;
                    }
                    $linhas = [];
                    foreach ($this->alunos as $alunoId => $aluno) {
                        $nota = $this->planoNotas[$alunoId][$materiaId]['trimestres'][$tri]['eventos'][$sigla] ?? null;
                        if ($nota === null) {
                            continue;
                        }
                        $linhas[] = [
                            'turma_id' => (int) $aluno['turma_id'],
                            'aluno_id' => (int) $alunoId,
                            'nota' => $nota,
                        ];
                    }
                    if ($linhas !== []) {
                        $this->upsertNotasEmLote($blocoId, $professorId, $materiaId, $linhas);
                    }
                }
            }

            $blocoRecId = (int) ($this->blocoIdPorChave[$this->chaveEvento(RecuperacaoConfig::CODIGO_CICLO_BASE + $tri, 'REC')] ?? 0);
            if ($blocoRecId > 0) {
                $this->lancarRecuperacaoTrimestre($tri, $blocoRecId);
            }
        }

        $blocoRecFinalId = (int) ($this->blocoIdPorChave[$this->chaveEvento(RecuperacaoConfig::CODIGO_FINAL, 'REC')] ?? 0);
        if ($blocoRecFinalId > 0) {
            $this->lancarRecuperacaoFinal($blocoRecFinalId);
        }

        println('  notas EM23 sincronizadas');
    }

    private function lancarRecuperacaoTrimestre(int $tri, int $blocoId): void
    {
        foreach (MATERIAS_EM23 as $meta) {
            $materiaId = (int) ($this->materiaIdPorCodigo[$meta['codigo']] ?? 0);
            $professorId = (int) ($this->professorIdPorCodigo[$meta['codigo']] ?? 0);
            if ($materiaId <= 0 || $professorId <= 0) {
                continue;
            }
            $linhas = [];
            $elegiveis = [];
            foreach ($this->alunos as $alunoId => $aluno) {
                $rec = $this->planoNotas[$alunoId][$materiaId]['trimestres'][$tri]['rec'] ?? null;
                if ($rec === null) {
                    continue;
                }
                $elegiveis[] = (int) $alunoId;
                $linhas[] = [
                    'turma_id' => (int) $aluno['turma_id'],
                    'aluno_id' => (int) $alunoId,
                    'nota' => $rec,
                ];
            }
            if ($linhas !== []) {
                $this->upsertNotasEmLote($blocoId, $professorId, $materiaId, $linhas);
            }
            $this->limparNotasNaoElegiveis($blocoId, $materiaId, $elegiveis);
        }
    }

    private function lancarRecuperacaoFinal(int $blocoId): void
    {
        foreach (MATERIAS_EM23 as $meta) {
            $materiaId = (int) ($this->materiaIdPorCodigo[$meta['codigo']] ?? 0);
            $professorId = (int) ($this->professorIdPorCodigo[$meta['codigo']] ?? 0);
            if ($materiaId <= 0 || $professorId <= 0) {
                continue;
            }
            $linhas = [];
            $elegiveis = [];
            foreach ($this->alunos as $alunoId => $aluno) {
                $rec = $this->planoNotas[$alunoId][$materiaId]['rec_final'] ?? null;
                if ($rec === null) {
                    continue;
                }
                $elegiveis[] = (int) $alunoId;
                $linhas[] = [
                    'turma_id' => (int) $aluno['turma_id'],
                    'aluno_id' => (int) $alunoId,
                    'nota' => $rec,
                ];
            }
            if ($linhas !== []) {
                $this->upsertNotasEmLote($blocoId, $professorId, $materiaId, $linhas);
            }
            $this->limparNotasNaoElegiveis($blocoId, $materiaId, $elegiveis);
        }
    }

    /**
     * @param list<array{turma_id:int,aluno_id:int,nota:float}> $linhas
     */
    private function upsertNotasEmLote(int $blocoId, int $professorId, int $materiaId, array $linhas): void
    {
        if ($blocoId <= 0 || $professorId <= 0 || $materiaId <= 0 || $linhas === []) {
            return;
        }

        foreach (array_chunk($linhas, 250) as $parte) {
            $values = [];
            $params = [];
            foreach (array_values($parte) as $i => $linha) {
                $values[] = '(:bloco_' . $i . ', :prof_' . $i . ', :materia_' . $i . ', :turma_' . $i . ', :aluno_' . $i . ', :nota_' . $i . ', NULL)';
                $params['bloco_' . $i] = $blocoId;
                $params['prof_' . $i] = $professorId;
                $params['materia_' . $i] = $materiaId;
                $params['turma_' . $i] = (int) $linha['turma_id'];
                $params['aluno_' . $i] = (int) $linha['aluno_id'];
                $params['nota_' . $i] = (float) $linha['nota'];
            }

            $this->db->query(
                'INSERT INTO provas_blocos_notas_lancadas
                    (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota, observacao)
                 VALUES ' . implode(', ', $values) . '
                 ON DUPLICATE KEY UPDATE
                    nota = VALUES(nota),
                    observacao = VALUES(observacao),
                    updated_at = CURRENT_TIMESTAMP',
                $params
            );
        }
    }

    /**
     * @param list<int> $alunosElegiveis
     */
    private function limparNotasNaoElegiveis(int $blocoId, int $materiaId, array $alunosElegiveis): void
    {
        $todos = array_values(array_map('intval', array_keys($this->alunos)));
        $remover = array_values(array_diff($todos, $alunosElegiveis));
        if ($remover === []) {
            return;
        }
        $in = $this->placeholders('aluno_', $remover);
        $params = [
            'bloco' => $blocoId,
            'materia' => $materiaId,
        ] + $in['params'];
        $this->db->query(
            'DELETE FROM provas_blocos_notas_lancadas
              WHERE bloco_id = :bloco
                AND materia_id = :materia
                AND aluno_id IN (' . $in['sql'] . ')',
            $params
        );
    }

    private function salvarRegrasBoletim(): void
    {
        $materiasJson = $this->json(array_values(array_map('intval', $this->materiaIdPorCodigo)));
        $seriesJson = $this->json([5, 6, 7]);
        $turmasJson = $this->json(array_values(array_map('intval', $this->turmaIdPorNome)));

        foreach ([1, 2, 3] as $tri) {
            $codigo = 'em23-notas-t' . $tri . '-2023';
            $existente = $this->boletim->getRuleByCode($codigo);
            $componentes = [];
            foreach (array_keys(EVENTOS_BASE) as $sigla) {
                $blocoId = (int) ($this->blocoIdPorChave[$this->chaveEvento($tri, $sigla)] ?? 0);
                if ($blocoId <= 0) {
                    continue;
                }
                $componentes[] = [
                    'codigo' => $sigla,
                    'nome' => EVENTOS_BASE[$sigla]['nome'],
                    'source_type' => 'provas_sistema',
                    'calc_type' => 'ultima',
                    'peso' => EVENTOS_BASE[$sigla]['peso'],
                    'bloco_id' => $blocoId,
                    'blocos_ids' => (string) $blocoId,
                    'obrigatorio' => 1,
                    'escala_max' => 100,
                ];
            }
            $blocoRec = (int) ($this->blocoIdPorChave[$this->chaveEvento(RecuperacaoConfig::CODIGO_CICLO_BASE + $tri, 'REC')] ?? 0);
            if ($blocoRec > 0) {
                $componentes[] = [
                    'codigo' => 'REC',
                    'nome' => 'Recuperação',
                    'source_type' => 'provas_sistema',
                    'calc_type' => 'ultima',
                    'peso' => 0,
                    'bloco_id' => $blocoRec,
                    'blocos_ids' => (string) $blocoRec,
                    'obrigatorio' => 0,
                    'escala_max' => 100,
                ];
            }

            $extras = $this->json([
                'linhas_layout' => [[
                    'id' => 'em23t' . $tri,
                    'nome' => 'Avaliações',
                    'colunas' => ['PP1', 'PP2', 'PP3', 'PP4', 'TR', 'SIM', 'PT', 'REC'],
                ]],
                'rotulos_colunas' => [
                    'PP1' => 'PP1',
                    'PP2' => 'PP2',
                    'PP3' => 'PP3',
                    'PP4' => 'PP4',
                    'TR' => 'TR',
                    'SIM' => 'SIM',
                    'PT' => 'PT',
                    'REC' => 'REC',
                ],
            ]);

            if ($this->dryRun) {
                println('  [dry-run] salvaria regra de notas ' . $codigo);
                continue;
            }

            $regraId = $this->boletim->saveRule(
                'EM23 Demonstrativo ' . $tri . 'º Trimestre 2023',
                '((PP1 + PP2 + PP3 + PP4 + TR) + 2*SIM + 3*PT) / 10',
                $componentes,
                $existente ? (int) $existente['id'] : null,
                'Demonstrativo EM23 do ' . $tri . 'º trimestre com REC fora da fórmula base.',
                $materiasJson,
                null,
                $codigo,
                $seriesJson,
                $turmasJson,
                'notas',
                ANO,
                $tri,
                1,
                1,
                1,
                'none',
                1,
                DATAS_TRIMESTRES[$tri]['PP1'],
                DATAS_TRIMESTRES[$tri]['REC'],
                60.0,
                1,
                $extras,
                'oficial'
            );
            $this->regrasBoletim[$codigo] = $regraId;
            $this->stats['regras_salvas']++;
        }

        foreach ([1, 2, 3] as $tri) {
            $codigoPeriodo = RecuperacaoConfig::CODIGO_CICLO_BASE + $tri;
            $codigo = 'em23-notas-rec-t' . $tri . '-2023';
            $existente = $this->boletim->getRuleByCode($codigo);
            $blocoId = (int) ($this->blocoIdPorChave[$this->chaveEvento($codigoPeriodo, 'REC')] ?? 0);
            if ($blocoId <= 0) {
                continue;
            }
            if ($this->dryRun) {
                println('  [dry-run] salvaria regra de recuperação ' . $codigo);
                continue;
            }
            $regraId = $this->boletim->saveRule(
                'EM23 Recuperação ' . $tri . 'º Trimestre 2023',
                '',
                [[
                    'codigo' => 'REC',
                    'nome' => 'Recuperação',
                    'source_type' => 'provas_sistema',
                    'calc_type' => 'ultima',
                    'peso' => 1,
                    'bloco_id' => $blocoId,
                    'blocos_ids' => (string) $blocoId,
                    'obrigatorio' => 0,
                    'escala_max' => 100,
                ]],
                $existente ? (int) $existente['id'] : null,
                'Coluna da recuperação do ' . $tri . 'º trimestre.',
                $materiasJson,
                null,
                $codigo,
                $seriesJson,
                $turmasJson,
                'notas',
                ANO,
                $codigoPeriodo,
                1,
                1,
                1,
                'none',
                1,
                DATAS_TRIMESTRES[$tri]['REC'],
                DATAS_TRIMESTRES[$tri]['REC'],
                60.0,
                0,
                $this->json([
                    'linhas_layout' => [[
                        'id' => 'em23rec' . $tri,
                        'nome' => 'Recuperação',
                        'colunas' => ['REC'],
                    ]],
                    'rotulos_colunas' => ['REC' => 'REC'],
                ]),
                'oficial'
            );
            $this->regrasBoletim[$codigo] = $regraId;
            $this->stats['regras_salvas']++;
        }

        $codigoFinal = 'em23-notas-rf-2023';
        $existenteFinal = $this->boletim->getRuleByCode($codigoFinal);
        $blocoRecFinal = (int) ($this->blocoIdPorChave[$this->chaveEvento(RecuperacaoConfig::CODIGO_FINAL, 'REC')] ?? 0);
        if ($blocoRecFinal > 0) {
            if ($this->dryRun) {
                println('  [dry-run] salvaria regra de recuperação final ' . $codigoFinal);
            } else {
                $regraId = $this->boletim->saveRule(
                    'EM23 Recuperação Final 2023',
                    '',
                    [[
                        'codigo' => 'REC',
                        'nome' => 'Rec Final',
                        'source_type' => 'provas_sistema',
                        'calc_type' => 'ultima',
                        'peso' => 1,
                        'bloco_id' => $blocoRecFinal,
                        'blocos_ids' => (string) $blocoRecFinal,
                        'obrigatorio' => 0,
                        'escala_max' => 100,
                    ]],
                    $existenteFinal ? (int) $existenteFinal['id'] : null,
                    'Coluna da recuperação final anual EM23.',
                    $materiasJson,
                    null,
                    $codigoFinal,
                    $seriesJson,
                    $turmasJson,
                    'notas',
                    ANO,
                    RecuperacaoConfig::CODIGO_FINAL,
                    1,
                    1,
                    1,
                    'none',
                    1,
                    DATA_REC_FINAL,
                    DATA_REC_FINAL,
                    60.0,
                    0,
                    $this->json([
                        'linhas_layout' => [[
                            'id' => 'em23recfinal',
                            'nome' => 'Recuperação Final',
                            'colunas' => ['REC'],
                        ]],
                        'rotulos_colunas' => ['REC' => 'REC'],
                    ]),
                    'oficial'
                );
                $this->regrasBoletim[$codigoFinal] = $regraId;
                $this->stats['regras_salvas']++;
            }
        }
    }

    private function configurarModeloELayout(): void
    {
        if ($this->dryRun) {
            println('  [dry-run] sincronizaria modelo/layout oficial EM23');
            return;
        }

        $materiasIds = array_values(array_map('intval', $this->materiaIdPorCodigo));
        $seriesIds = [5, 6, 7];
        $turmasIds = array_values(array_map('intval', $this->turmaIdPorNome));

        if ($this->boletimCadastro !== null) {
            $nomeModelo = 'EM23 — Ensino Médio 2023';
            $exist = $this->db->fetch(
                "SELECT id
                   FROM boletins
                  WHERE ano_letivo = :ano
                    AND nome = :nome
                    AND excluido_em IS NULL
                  ORDER BY id DESC
                  LIMIT 1",
                ['ano' => ANO, 'nome' => $nomeModelo]
            );

            $res = $this->boletimCadastro->salvar([
                'nome' => $nomeModelo,
                'finalidade' => 'oficial',
                'ano_letivo' => ANO,
                'regra_academica_id' => $this->regraAcademicaId,
                'materias_ids' => $materiasIds,
                'series_ids' => $seriesIds,
                'turmas_ids' => $turmasIds,
                'vis_aluno' => 1,
                'vis_pais' => 1,
                'vis_coordenacao' => 1,
                'ativo' => 1,
            ], $exist ? (int) $exist['id'] : null);
            if (!empty($res['success'])) {
                $this->modeloBoletimId = (int) ($res['id'] ?? ($exist['id'] ?? 0));
                if (!$exist) {
                    $this->stats['modelo_criado'] = 1;
                }
                $this->db->query(
                    'UPDATE boletins
                        SET nome = :nome,
                            turmas_ids = :turmas,
                            series_ids = :series,
                            materias_ids = :materias,
                            ativo = 1
                      WHERE id = :id',
                    [
                        'nome' => $nomeModelo,
                        'turmas' => $this->json($turmasIds),
                        'series' => $this->json($seriesIds),
                        'materias' => $this->json($materiasIds),
                        'id' => $this->modeloBoletimId,
                    ]
                );
                foreach ($this->regrasBoletim as $regraId) {
                    $this->boletim->setBoletimId((int) $regraId, $this->modeloBoletimId);
                }
            } else {
                println('  aviso modelo boletim: ' . (string) ($res['error'] ?? 'falha'));
            }
        }

        if ($this->boletimLayout !== null && $this->boletimLayout->identidadePronta()) {
            $nomeLayout = 'EM23 — Ensino Médio 2023';
            $exist = $this->db->fetch(
                "SELECT id
                   FROM boletim_layouts
                  WHERE ano_letivo = :ano
                    AND (nome = :nome OR regra_academica_id = :regra)
                  ORDER BY id DESC
                  LIMIT 1",
                [
                    'ano' => ANO,
                    'nome' => $nomeLayout,
                    'regra' => $this->regraAcademicaId,
                ]
            );
            if ($exist) {
                $this->layoutBoletimId = (int) $exist['id'];
                $upd = $this->boletimLayout->atualizarCadastro($this->layoutBoletimId, [
                    'nome' => $nomeLayout,
                    'ano_letivo' => ANO,
                    'finalidade' => 'oficial',
                    'vis_aluno' => 1,
                    'vis_pais' => 1,
                    'ativo' => 1,
                    'regra_academica_id' => $this->regraAcademicaId,
                    'materias_ids' => $materiasIds,
                    'series_ids' => $seriesIds,
                    'turmas_ids' => $turmasIds,
                ]);
                if (!$upd['ok']) {
                    println('  aviso layout boletim: ' . $upd['erro']);
                    return;
                }
            } else {
                $criado = $this->boletimLayout->criar([
                    'nome' => $nomeLayout,
                    'ano_letivo' => ANO,
                    'finalidade' => 'oficial',
                    'vis_aluno' => 1,
                    'vis_pais' => 1,
                    'ativo' => 1,
                    'regra_academica_id' => $this->regraAcademicaId,
                    'materias_ids' => $materiasIds,
                    'series_ids' => $seriesIds,
                    'turmas_ids' => $turmasIds,
                ]);
                if (!$criado['ok']) {
                    println('  aviso layout boletim: ' . $criado['erro']);
                    return;
                }
                $this->layoutBoletimId = (int) ($criado['id'] ?? 0);
                $this->stats['layout_criado'] = 1;
            }

            if ($this->layoutBoletimId > 0) {
                $this->db->query(
                    'UPDATE boletim_layouts
                        SET nome = :nome,
                            turmas_ids = :turmas,
                            series_ids = :series,
                            materias_ids = :materias,
                            ativo = 1
                      WHERE id = :id',
                    [
                        'nome' => $nomeLayout,
                        'turmas' => $this->json($turmasIds),
                        'series' => $this->json($seriesIds),
                        'materias' => $this->json($materiasIds),
                        'id' => $this->layoutBoletimId,
                    ]
                );

                $linhas = [
                    ['nome' => '1º Trimestre', 'colunas' => ['1_media', '1_faltas', '1_situacao']],
                    ['nome' => '1º Recuperação', 'colunas' => ['61_rec']],
                    ['nome' => '2º Trimestre', 'colunas' => ['2_media', '2_faltas', '2_situacao']],
                    ['nome' => '2º Recuperação', 'colunas' => ['62_rec']],
                    ['nome' => '3º Trimestre', 'colunas' => ['3_media', '3_faltas', '3_situacao']],
                    ['nome' => '3º Recuperação', 'colunas' => ['63_rec']],
                    ['nome' => 'Resultado Final', 'colunas' => ['90_rec', 'final_media', 'final_situacao']],
                ];
                $filtradas = $this->boletimLayout->filtrar(ANO, $linhas);
                if ($filtradas !== [] && !$this->boletimLayout->salvarDoBoletim($this->layoutBoletimId, $filtradas, '(m1 + m2 + m3) / 3')) {
                    println('  aviso layout boletim: não gravou as linhas.');
                }
            }
        }
    }

    private function gerarResultadosBoletim(): void
    {
        if ($this->dryRun) {
            println('  [dry-run] geraria resultados de demonstrativo para ' . count($this->alunos) . ' alunos');
            return;
        }

        $this->stats['resultados_gerados'] = 0;

        foreach ([1, 2, 3] as $tri) {
            $codigo = 'em23-notas-t' . $tri . '-2023';
            $regraId = (int) ($this->regrasBoletim[$codigo] ?? 0);
            $regra = $regraId > 0 ? $this->boletim->getRuleById($regraId) : null;
            if (!$regra) {
                continue;
            }
            $periodoRef = ANO . '-T' . $tri;
            foreach (array_keys($this->alunos) as $alunoId) {
                $this->gerarResultadoAluno($regraId, $regra, (int) $alunoId, $periodoRef, DATAS_TRIMESTRES[$tri]['PP1'], DATAS_TRIMESTRES[$tri]['REC']);
            }
        }

        foreach ([1, 2, 3] as $tri) {
            $codigo = 'em23-notas-rec-t' . $tri . '-2023';
            $regraId = (int) ($this->regrasBoletim[$codigo] ?? 0);
            $regra = $regraId > 0 ? $this->boletim->getRuleById($regraId) : null;
            if (!$regra) {
                continue;
            }
            $periodoRef = ANO . '-REC-T' . $tri;
            foreach (array_keys($this->alunos) as $alunoId) {
                $this->gerarResultadoAluno($regraId, $regra, (int) $alunoId, $periodoRef, DATAS_TRIMESTRES[$tri]['REC'], DATAS_TRIMESTRES[$tri]['REC']);
            }
        }

        $codigoFinal = 'em23-notas-rf-2023';
        $regraIdFinal = (int) ($this->regrasBoletim[$codigoFinal] ?? 0);
        $regraFinal = $regraIdFinal > 0 ? $this->boletim->getRuleById($regraIdFinal) : null;
        if ($regraFinal) {
            foreach (array_keys($this->alunos) as $alunoId) {
                $this->gerarResultadoAluno($regraIdFinal, $regraFinal, (int) $alunoId, ANO . '-RF', DATA_REC_FINAL, DATA_REC_FINAL);
            }
        }

        println('  resultados gerados: ' . $this->stats['resultados_gerados']);
    }

    /**
     * @param array<string,mixed> $regra
     */
    private function gerarResultadoAluno(int $regraId, array $regra, int $alunoId, string $periodoRef, string $dataInicio, string $dataFim): void
    {
        try {
            $sim = $this->boletimCtrl->simularRegraAluno($regra, $alunoId, $periodoRef, $dataInicio, $dataFim);
            $matriz = $sim['matriz_materias'] ?? null;
            $colunas = is_array($matriz) && is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
            $linhas = is_array($matriz) && is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
            $this->boletim->replaceGeneratedResultsForAluno(
                $regraId,
                $alunoId,
                $periodoRef,
                $dataInicio,
                $dataFim,
                $colunas,
                $linhas,
                false
            );
            $this->stats['resultados_gerados']++;
        } catch (Throwable $e) {
            $this->stats['erros_boletim'] = (int) ($this->stats['erros_boletim'] ?? 0) + 1;
            println('  aviso boletim aluno #' . $alunoId . ' / regra #' . $regraId . ': ' . $e->getMessage());
        }
    }

    private function resumo(): void
    {
        println('');
        println('Pronto.');
        println('  Ano letivo: 2023 (#' . $this->anoId . ', trimestre)');
        println('  Turmas EM23: ' . count($this->turmaIdPorNome));
        println('  Alunos EM23: ' . count($this->alunos));
        println('  Matérias EM23: ' . count($this->materiaIdPorCodigo));
        println('  Professores EM23: ' . count($this->professorIdPorCodigo));
        println('  Eventos EM23: ' . count(array_filter($this->eventoIdPorChave)));
        if ((int) ($this->stats['eventos_pp_antigos_desativados'] ?? 0) > 0) {
            println('  PP antigos desativados: ' . (int) $this->stats['eventos_pp_antigos_desativados'] . ' evento(s) / ' . (int) ($this->stats['blocos_pp_antigos_desativados'] ?? 0) . ' bloco(s)');
        }
        println('  Regra acadêmica: #' . $this->regraAcademicaId);
        if ($this->modeloBoletimId > 0) {
            println('  Modelo de boletim: #' . $this->modeloBoletimId);
        }
        if ($this->layoutBoletimId > 0) {
            println('  Layout oficial: #' . $this->layoutBoletimId);
        }
        if (!$this->dryRun) {
            println('  Senha padrão de alunos/professores: ' . SENHA_PADRAO);
            println('  Eventos:       http://educa.localhost/admin/eventos-avaliativos?ano=' . ANO);
            println('  Demonstrativo: http://educa.localhost/admin/boletim?exibir_em=notas&ano=' . ANO);
            println('  Modelos:       http://educa.localhost/admin/boletins');
            if ($this->layoutBoletimId > 0) {
                println('  Layout:        http://educa.localhost/admin/eventos-avaliativos/boletim/' . $this->layoutBoletimId);
            }
        }
    }

    private function nomeAluno(int $indice): string
    {
        $nome = NOMES[$indice % count(NOMES)];
        $sob1 = SOBRENOMES_1[($indice * 3) % count(SOBRENOMES_1)];
        $sob2 = SOBRENOMES_2[($indice * 5) % count(SOBRENOMES_2)];
        return $nome . ' ' . $sob1 . ' ' . $sob2;
    }

    private function dataNascimentoAluno(int $serieId, int $slot): string
    {
        $anoBase = match ($serieId) {
            5 => 2008,
            6 => 2007,
            default => 2006,
        };
        $mes = (($slot - 1) % 12) + 1;
        $dia = (($slot * 3) % 28) + 1;
        return sprintf('%04d-%02d-%02d', $anoBase, $mes, $dia);
    }

    private function notaDeterministica(string $nickname, string $materiaCodigo, int $tri, string $tipo): float
    {
        $perfil = $this->perfilAluno($nickname);
        $fraca = $this->materiaFracaDoAluno($nickname);
        $materiaFraca = $fraca === $materiaCodigo;
        $u = $this->unit($nickname, $materiaCodigo, (string) $tri, $tipo);
        $base = match ($perfil) {
            'excelente' => $this->faixa(88, 98, $u),
            'bom' => $this->faixa(78, 90, $u),
            'regular' => $this->faixa(68, 82, $u),
            'dificuldade_especifica' => $materiaFraca ? $this->faixa(48, 62, $u) : $this->faixa(76, 88, $u),
            'dificuldade_generalizada' => $this->faixa(52, 68, $u),
            'em_evolucao' => $this->faixa(58 + (($tri - 1) * 6), 70 + (($tri - 1) * 7), $u),
            'em_queda' => $this->faixa(84 - (($tri - 1) * 8), 92 - (($tri - 1) * 6), $u),
            default => $this->faixa(68, 82, $u),
        };

        $ajusteTipo = match ($tipo) {
            'PP1' => $this->faixa(-4.0, 1.5, $this->unit($nickname, $materiaCodigo, (string) $tri, $tipo, 'aj')),
            'PP2' => $this->faixa(-3.0, 2.0, $this->unit($nickname, $materiaCodigo, (string) $tri, $tipo, 'aj')),
            'PP3' => $this->faixa(-2.0, 2.5, $this->unit($nickname, $materiaCodigo, (string) $tri, $tipo, 'aj')),
            'PP4' => $this->faixa(-1.5, 3.0, $this->unit($nickname, $materiaCodigo, (string) $tri, $tipo, 'aj')),
            'TR' => $this->faixa(0.5, 4.0, $this->unit($nickname, $materiaCodigo, (string) $tri, $tipo, 'aj')),
            'SIM' => $this->faixa(-2.5, 3.0, $this->unit($nickname, $materiaCodigo, (string) $tri, $tipo, 'aj')),
            'PT' => $this->faixa(-3.0, 2.5, $this->unit($nickname, $materiaCodigo, (string) $tri, $tipo, 'aj')),
            default => 0.0,
        };

        $pontual = $this->unit($nickname, $materiaCodigo, (string) $tri, $tipo, 'surpresa');
        if ($perfil !== 'excelente' && $pontual < 0.02) {
            $base -= 8.0;
        } elseif ($perfil === 'excelente' && $pontual > 0.96) {
            $base += 2.5;
        }

        return round($this->clamp($base + $ajusteTipo, 0.0, 100.0), 1);
    }

    /**
     * @param array<string,float> $eventos
     */
    private function mediaTrimestral(array $eventos): float
    {
        $soma = 0.0;
        $pesos = 0;
        foreach (EVENTOS_BASE as $sigla => $meta) {
            $nota = (float) ($eventos[$sigla] ?? 0.0);
            $peso = (int) $meta['peso'];
            $soma += $nota * $peso;
            $pesos += $peso;
        }
        return round($soma / max(1, $pesos), 1);
    }

    private function notaRecuperacaoTrimestral(string $nickname, string $materiaCodigo, int $tri, float $media): float
    {
        $perfil = $this->perfilAluno($nickname);
        $chanceRecupera = match ($perfil) {
            'excelente', 'bom' => 0.96,
            'regular', 'em_evolucao' => 0.88,
            'dificuldade_especifica' => 0.76,
            'dificuldade_generalizada' => 0.57,
            'em_queda' => 0.62,
            default => 0.8,
        };
        $u = $this->unit($nickname, $materiaCodigo, (string) $tri, 'REC');
        if ($u < $chanceRecupera) {
            $min = max(62.0, min(78.0, $media + 5.0));
            return round($this->faixa($min, 85.0, $this->unit($nickname, $materiaCodigo, (string) $tri, 'REC', 'ok')), 1);
        }
        return round($this->faixa(40.0, 58.0, $this->unit($nickname, $materiaCodigo, (string) $tri, 'REC', 'ko')), 1);
    }

    private function notaRecuperacaoFinal(string $nickname, string $materiaCodigo, float $mediaAnual): float
    {
        $perfil = $this->perfilAluno($nickname);
        $chanceRecupera = match ($perfil) {
            'excelente', 'bom' => 0.94,
            'regular', 'em_evolucao' => 0.82,
            'dificuldade_especifica' => 0.68,
            'dificuldade_generalizada' => 0.5,
            'em_queda' => 0.58,
            default => 0.75,
        };
        $u = $this->unit($nickname, $materiaCodigo, 'FINAL', 'REC');
        if ($u < $chanceRecupera) {
            $min = max(63.0, min(79.0, $mediaAnual + 6.0));
            return round($this->faixa($min, 86.0, $this->unit($nickname, $materiaCodigo, 'FINAL', 'REC', 'ok')), 1);
        }
        return round($this->faixa(38.0, 58.0, $this->unit($nickname, $materiaCodigo, 'FINAL', 'REC', 'ko')), 1);
    }

    /**
     * @return 'excelente'|'bom'|'regular'|'dificuldade_especifica'|'dificuldade_generalizada'|'em_evolucao'|'em_queda'
     */
    private function perfilAluno(string $nickname): string
    {
        $faixa = (int) floor($this->unit($nickname, 'perfil') * 100);
        if ($faixa < 12) {
            return 'excelente';
        }
        if ($faixa < 40) {
            return 'bom';
        }
        if ($faixa < 70) {
            return 'regular';
        }
        if ($faixa < 85) {
            return 'dificuldade_especifica';
        }
        if ($faixa < 93) {
            return 'dificuldade_generalizada';
        }
        if ($faixa < 98) {
            return 'em_evolucao';
        }
        return 'em_queda';
    }

    private function materiaFracaDoAluno(string $nickname): string
    {
        $codigos = array_keys(MATERIAS_EM23);
        $idx = (int) floor($this->unit($nickname, 'materia_fraca') * count($codigos));
        $idx = max(0, min(count($codigos) - 1, $idx));
        return MATERIAS_EM23[$codigos[$idx]]['codigo'];
    }

    private function chaveEvento(int $periodo, string $sigla): string
    {
        return $periodo . ':' . strtoupper($sigla);
    }

    /**
     * @return list<array{titulo:string,data:string}>
     */
    private function subeventosProvaParcial(int $tri): array
    {
        return [
            ['titulo' => 'Prova Parcial 1', 'data' => DATAS_TRIMESTRES[$tri]['PP1']],
            ['titulo' => 'Prova Parcial 2', 'data' => DATAS_TRIMESTRES[$tri]['PP2']],
            ['titulo' => 'Prova Parcial 3', 'data' => DATAS_TRIMESTRES[$tri]['PP3']],
            ['titulo' => 'Prova Parcial 4', 'data' => DATAS_TRIMESTRES[$tri]['PP4']],
        ];
    }

    /**
     * @param list<array{titulo:string,data:string}> $subeventos
     * @return list<array{id?:int,titulo:string,data:string}>
     */
    private function anexarIdsDosBlocosParciais(int $eventoId, array $subeventos): array
    {
        $existentes = $this->db->fetchAll(
            'SELECT id, titulo
               FROM provas_blocos
              WHERE evento_avaliativo_id = :evento
                AND deleted_at IS NULL
              ORDER BY semana ASC, data_prova ASC, id ASC',
            ['evento' => $eventoId]
        ) ?: [];

        $idsPorSigla = [];
        $idsPorOrdem = [];
        foreach ($existentes as $row) {
            $blocoId = (int) ($row['id'] ?? 0);
            if ($blocoId <= 0) {
                continue;
            }
            $idsPorOrdem[] = $blocoId;
            $sigla = $this->resolverSiglaParcialPorTitulo((string) ($row['titulo'] ?? ''));
            if ($sigla !== null && !isset($idsPorSigla[$sigla])) {
                $idsPorSigla[$sigla] = $blocoId;
            }
        }

        $resultado = [];
        foreach (array_values($subeventos) as $i => $subevento) {
            $sigla = 'PP' . ($i + 1);
            $blocoId = (int) ($idsPorSigla[$sigla] ?? 0);
            if ($blocoId <= 0 && count($idsPorOrdem) === count($subeventos)) {
                $blocoId = (int) ($idsPorOrdem[$i] ?? 0);
            }

            $item = $subevento;
            if ($blocoId > 0) {
                $item['id'] = $blocoId;
            }
            $resultado[] = $item;
        }

        return $resultado;
    }

    private function resolverSiglaParcialPorTitulo(string $titulo): ?string
    {
        if (preg_match('/parcial\s+([1-4])/i', $titulo, $matches) !== 1) {
            return null;
        }

        return 'PP' . (string) $matches[1];
    }

    /**
     * @return list<string>
     */
    private function nomesEventosParciaisAntigos(int $tri): array
    {
        return [
            PREFIXO . ' T' . $tri . ' Prova Parcial 1',
            PREFIXO . ' T' . $tri . ' Prova Parcial 2',
            PREFIXO . ' T' . $tri . ' Prova Parcial 3',
            PREFIXO . ' T' . $tri . ' Prova Parcial 4',
        ];
    }

    private function faixa(float $min, float $max, float $u): float
    {
        return $min + (($max - $min) * $u);
    }

    private function clamp(float $valor, float $min, float $max): float
    {
        return max($min, min($max, $valor));
    }

    private function unit(string ...$partes): float
    {
        $hash = crc32(implode('|', $partes));
        return (($hash & 0x7fffffff) / 2147483647.0);
    }

    /**
     * @param list<mixed> $dados
     */
    private function json(array $dados): string
    {
        $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            fail('Falha ao gerar JSON do seed EM23.');
        }
        return $json;
    }

    private function temTabela(string $tabela): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $tabela)) {
            return false;
        }
        try {
            if (method_exists($this->db, 'tableExists')) {
                return (bool) $this->db->tableExists($tabela);
            }
            $row = $this->db->fetch(
                'SELECT 1 AS ok
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = :tabela
                  LIMIT 1',
                ['tabela' => $tabela]
            );
            return !empty($row['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $tabela) || !preg_match('/^[a-z0-9_]+$/i', $coluna)) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = :tabela
                    AND COLUMN_NAME = :coluna
                  LIMIT 1',
                ['tabela' => $tabela, 'coluna' => $coluna]
            );
            return !empty($row['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param list<int> $ids
     * @return array{sql:string,params:array<string,int>}
     */
    private function placeholders(string $prefixo, array $ids): array
    {
        $sql = [];
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $chave = $prefixo . $i;
            $sql[] = ':' . $chave;
            $params[$chave] = (int) $id;
        }
        return ['sql' => implode(', ', $sql), 'params' => $params];
    }
}

$opcoes = lerOpcoes($argv ?? []);

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
} catch (Throwable $e) {
    fail('Não conectou em ' . TENANT_DB . '. ' . $e->getMessage());
}

exit((new SeedEmTrimestral2023Educa(Database::getInstance(), $opcoes['dry_run']))->executar());
