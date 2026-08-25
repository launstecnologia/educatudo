<?php
/**
 * Popula o Colégio Educa (2023–2026) com continuidade real:
 * matrícula → fechamento → resultado → rematrícula → nova coorte no 6º.
 *
 * Uso (container PHP, após init_colegio_educa.php):
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
require_once $basePath . '/app/Models/User/Teacher.php';
require_once $basePath . '/app/Models/User/Student.php';
require_once $basePath . '/app/Models/User/Parent.php';
require_once $basePath . '/app/Models/Education/ClassRoom.php';
require_once $basePath . '/app/Models/Education/ClassDiary.php';
require_once $basePath . '/app/Models/Education/SchoolUnit.php';
require_once $basePath . '/app/Models/Exams/ExamBlock.php';
require_once $basePath . '/app/Models/Exams/ExamBlockManualGrade.php';
require_once $basePath . '/app/Models/Exams/ExamEvaluationType.php';
require_once $basePath . '/app/Models/System/BoletimConfig.php';
require_once $basePath . '/app/Services/SalaService.php';
require_once $basePath . '/app/Services/MatrizCurricularService.php';
require_once $basePath . '/app/Services/AlunoMovimentacaoService.php';
require_once $basePath . '/app/Services/OcorrenciaService.php';
require_once $basePath . '/app/Services/ConselhoService.php';
require_once $basePath . '/app/Services/RegraAcademicaService.php';
require_once $basePath . '/app/Services/ResultadoHomologacaoService.php';
require_once $basePath . '/app/Services/HistoricoEscolarService.php';
require_once $basePath . '/app/Services/DocumentoOficialService.php';
require_once $basePath . '/app/Services/FrequencyService.php';
require_once $basePath . '/app/Controllers/Admin/BoletimConfigController.php';

const TENANT_DB = 'educatudo_educa';
const SENHA = 'Teste@123';
const PREFIXO = 'EDUCA';
const ALUNOS_POR_TURMA = 12;
const LETRAS = ['A', 'B', 'C'];

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

final class PopularColegioEduca
{
    private $db;
    private Teacher $teachers;
    private ParentModel $pais;
    private ClassRoom $turmas;
    private ClassDiary $diario;
    private ExamBlock $blocos;
    private ExamBlockManualGrade $notas;
    private ExamEvaluationType $tipos;
    private BoletimConfig $boletim;
    private BoletimConfigController $boletimCtrl;
    private SalaService $salas;
    private MatrizCurricularService $matrizes;
    private \App\Services\AlunoMovimentacaoService $movimentacao;
    private OcorrenciaService $ocorrencias;
    private ConselhoService $conselhos;
    private RegraAcademicaService $regras;
    private ResultadoHomologacaoService $homologacao;
    private \App\Services\HistoricoEscolarService $historico;
    private FrequencyService $frequencia;
    private SchoolUnit $unidadesModel;

    /** @var list<array{etapa:string,severidade:string,ok:bool,detalhe:string}> */
    private array $achados = [];

    private string $hash = '';
    private int $adminId = 0;
    private int $diretorId = 0;
    private int $coordFundId = 0;
    private int $coordEmId = 0;
    private int $secretariaId = 0;
    private int $unidadeFundId = 0;
    private int $unidadeEmId = 0;

    /** @var array<int, array{id:int,ano:int,inicio:string,fim:string}> */
    private array $anos = [];
    private int $cursoFundId = 0;
    private int $cursoEmId = 0;
    /** @var array<string, int> */
    private array $series = [];
    /** @var array<string, array<string,mixed>> */
    private array $materias = [];
    /** @var array<string, int> */
    private array $matrizPorSerie = [];
    /** @var array<string, int> */
    private array $salasIds = [];

    /** @var array<string, int> "mat:fund6:A" => professor_id */
    private array $profs = [];
    /** @var array<string, list<string>> */
    private array $profMaterias = [];

    /** @var array<string, int> "2023:fund6:A" => turma_id */
    private array $turmaIds = [];
    /** @var array<int, list<array<string,mixed>>> */
    private array $grades = [];

    /** @var array<string, int> */
    private array $alunoIds = [];
    /** @var array<int, string> */
    private array $cenarioPorAluno = [];
    /** @var array<int, string> */
    private array $letraPorAluno = [];
    /** @var array<int, string> */
    private array $seriePorAluno = [];
    /** @var list<array{aluno_id:int,serie:string,letra:string}> */
    private array $filaRematricula = [];

    private int $tipoProvaId = 0;
    private int $tipoAtivId = 0;
    private int $tipoRecId = 0;
    private int $categoriaPedagogicaId = 0;

    private const SERIES_ORDEM = ['fund6', 'fund7', 'fund8', 'fund9', 'em1', 'em2', 'em3'];

    private const NOMES = [
        'Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Felipe', 'Gabriela', 'Henrique',
        'Isabela', 'João', 'Karina', 'Lucas', 'Marina', 'Nicolas', 'Olivia', 'Pedro',
        'Rafaela', 'Samuel', 'Talita', 'Vitor', 'Yasmin', 'Caio', 'Beatriz', 'Eduardo',
    ];
    private const SOBRENOMES = [
        'Almeida', 'Barbosa', 'Cardoso', 'Dias', 'Fernandes', 'Gomes', 'Lima', 'Mendes',
        'Nogueira', 'Oliveira', 'Pereira', 'Rocha', 'Silva', 'Teixeira', 'Vieira', 'Castro',
    ];

    public function __construct($db)
    {
        $this->db = $db;
        $this->hash = password_hash(SENHA, PASSWORD_DEFAULT);
        $this->teachers = new Teacher();
        $this->pais = new ParentModel();
        $this->turmas = new ClassRoom();
        $this->diario = new ClassDiary();
        $this->diario->ensureSchema();
        $this->blocos = new ExamBlock();
        $this->notas = new ExamBlockManualGrade();
        $this->tipos = new ExamEvaluationType();
        $this->boletim = new BoletimConfig();
        $this->boletim->ensureSchema();
        $this->boletimCtrl = $this->montarBoletimController();
        $this->salas = new SalaService();
        $this->matrizes = new MatrizCurricularService();
        $this->movimentacao = new \App\Services\AlunoMovimentacaoService();
        $this->ocorrencias = new OcorrenciaService();
        $this->conselhos = new ConselhoService();
        $this->regras = new RegraAcademicaService();
        $this->homologacao = new ResultadoHomologacaoService();
        $this->historico = new \App\Services\HistoricoEscolarService();
        $this->frequencia = new FrequencyService();
        $this->unidadesModel = new SchoolUnit();
    }

    public function executar(bool $somenteFinal = false): int
    {
        println('== Colégio Educa — população 2023–2026 ==');
        $this->imprimirTabelaCoortes();
        $this->criarEquipe();
        $this->criarUnidades();
        $this->garantirColunaUnidadeAluno();
        $this->criarAnosLetivos();
        $this->criarCursosSeries();
        $this->carregarComponentes();
        $this->criarMatrizes();
        $this->criarSalas();
        $this->criarTiposAvaliacao();
        $this->criarProfessores();
        $this->criarRegrasAcademicas();
        $this->configurarFechamento();
        $this->garantirCategoriaOcorrencia();
        $this->criarCalendarios();

        if ($somenteFinal) {
            println('Modo: somente documentos finais e validação');
        } else {
            foreach ([2023, 2024, 2025, 2026] as $ano) {
                println('');
                println("---- ANO LETIVO {$ano} ----");
                $this->executarAno($ano);
            }
        }

        $this->criarResponsaveisAmostra();
        $this->gerarDocumentosFinais();
        $this->validarCenariosObrigatorios();
        $this->validarIntegridade();
        $this->imprimirRelatorio();
        $this->popularGestaoEscolar();
        $this->popularCadastroCenso();
        $falhas = array_filter(
            $this->achados,
            static fn($a) => !$a['ok'] && in_array($a['severidade'], ['BLOQUEADOR', 'CRITICO'], true)
        );
        return $falhas === [] ? 0 : 2;
    }

    private function imprimirTabelaCoortes(): void
    {
        $n = ALUNOS_POR_TURMA;
        $base = $n * 3;
        println('Densidade local: ' . $n . ' alunos/turma (A/B/C). Meta do doc: ~30.');
        println('Progressão: ~75% aprovados, 1 retido/turma, recuperação no slot 08,');
        println('transferência de saída na letra C slot 12, nova coorte no 6º todo ano.');
        println('');
        println(sprintf(
            "  %-10s %6s %6s %6s %6s %6s %6s %6s",
            'Ano', '6º', '7º', '8º', '9º', '1ª EM', '2ª EM', '3ª EM'
        ));
        println(sprintf(
            "  %-10s %6d %6d %6d %6d %6d %6d %6d",
            '2023', $base, $base, $base, $base, $base, $base, $base
        ));
        println('  2024–2026: rematrícula da coorte + 36 novos no 6º + transferências/retidos.');
        println('');
    }

    /** @return array{curso:string,ordem:int,nome:string,proxima:?string,unidade:string} */
    private function metaSerie(string $chave): array
    {
        $mapa = [
            'fund6' => ['curso' => 'fund', 'ordem' => 6, 'nome' => '6º Ano', 'proxima' => 'fund7', 'unidade' => 'fund'],
            'fund7' => ['curso' => 'fund', 'ordem' => 7, 'nome' => '7º Ano', 'proxima' => 'fund8', 'unidade' => 'fund'],
            'fund8' => ['curso' => 'fund', 'ordem' => 8, 'nome' => '8º Ano', 'proxima' => 'fund9', 'unidade' => 'fund'],
            'fund9' => ['curso' => 'fund', 'ordem' => 9, 'nome' => '9º Ano', 'proxima' => 'em1', 'unidade' => 'fund'],
            'em1' => ['curso' => 'em', 'ordem' => 1, 'nome' => '1ª Série EM', 'proxima' => 'em2', 'unidade' => 'em'],
            'em2' => ['curso' => 'em', 'ordem' => 2, 'nome' => '2ª Série EM', 'proxima' => 'em3', 'unidade' => 'em'],
            'em3' => ['curso' => 'em', 'ordem' => 3, 'nome' => '3ª Série EM', 'proxima' => null, 'unidade' => 'em'],
        ];
        if (!isset($mapa[$chave])) {
            throw new RuntimeException('Série inválida: ' . $chave);
        }
        return $mapa[$chave];
    }

    private function codigoSerie(string $chave): string
    {
        return match ($chave) {
            'fund6' => '6', 'fund7' => '7', 'fund8' => '8', 'fund9' => '9',
            'em1' => '1', 'em2' => '2', 'em3' => '3',
            default => 'x',
        };
    }

    private function nomeTurma(int $ano, string $serie, string $letra): string
    {
        $meta = $this->metaSerie($serie);
        $curto = str_replace([' Ano', ' Série EM'], ['', ' EM'], $meta['nome']);
        return trim($curto) . ' ' . $letra . ' ' . $ano;
    }

    private function chaveTurma(int $ano, string $serie, string $letra): string
    {
        return $ano . ':' . $serie . ':' . $letra;
    }

    private function cenarioDoSlot(string $letra, int $slot): string
    {
        if ($slot === 8) {
            return 'recuperacao';
        }
        if ($slot === 9) {
            return 'reprovado';
        }
        if ($slot === 10 && $letra === 'B') {
            return 'frequencia';
        }
        if ($slot === 12 && $letra === 'C') {
            return 'transferencia';
        }
        return 'regular';
    }

    private function montarBoletimController(): BoletimConfigController
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

    private function registrar(string $etapa, bool $ok, string $detalhe, string $severidade = 'ALTO'): void
    {
        $this->achados[] = compact('etapa', 'severidade', 'ok', 'detalhe');
        println('  [' . ($ok ? 'PASS' : 'FAIL') . '] ' . $etapa . ': ' . $detalhe);
    }

    private function upsertUsuario(string $nome, string $email, string $perfil): int
    {
        $row = $this->db->fetch('SELECT id FROM usuarios WHERE email = :e LIMIT 1', ['e' => $email]);
        if ($row) {
            return (int) $row['id'];
        }
        return (int) $this->db->insert(
            "INSERT INTO usuarios (tipo, perfil_admin, nome, email, senha_hash, ativo)
             VALUES ('admin_escola', :perfil, :nome, :email, :hash, 1)",
            ['perfil' => $perfil, 'nome' => $nome, 'email' => $email, 'hash' => $this->hash]
        );
    }

    private function criarEquipe(): void
    {
        $this->adminId = $this->upsertUsuario('Admin Colégio Educa', 'admin@educa.local', 'dev');
        $this->diretorId = $this->upsertUsuario('Helena Duarte', 'diretor@educa.local', 'diretor');
        $this->coordFundId = $this->upsertUsuario('Paulo Moreira', 'coordenador.fund@educa.local', 'coordenador');
        $this->coordEmId = $this->upsertUsuario('Camila Ribeiro', 'coordenador.em@educa.local', 'coordenador');
        $this->secretariaId = $this->upsertUsuario('Renata Alves', 'secretaria@educa.local', 'secretaria');
        $this->registrar(
            'equipe',
            true,
            'admin/diretor/coordenadores/secretaria'
        );
    }

    private function criarUnidades(): void
    {
        $this->unidadeFundId = $this->upsertUnidade(
            'Unidade Fundamental II',
            'matriz',
            'Helena Duarte',
            'Renata Alves'
        );
        $this->unidadeEmId = $this->upsertUnidade(
            'Unidade Ensino Médio',
            'filial',
            'Helena Duarte',
            'Renata Alves'
        );
        $this->registrar('unidades', $this->unidadeFundId > 0 && $this->unidadeEmId > 0, "fund={$this->unidadeFundId} em={$this->unidadeEmId}");
    }

    private function garantirColunaUnidadeAluno(): void
    {
        $col = $this->db->fetch("SHOW COLUMNS FROM alunos LIKE 'unidade_id'");
        if ($col) {
            return;
        }
        $this->db->query('ALTER TABLE alunos ADD COLUMN unidade_id INT(11) NULL AFTER turma_id');
        try {
            $this->db->query('ALTER TABLE alunos ADD KEY idx_alunos_unidade (unidade_id)');
        } catch (Throwable $e) {
            // índice pode já existir
        }
        $this->registrar('alunos_unidade_id', true, 'coluna adicionada no tenant');
    }

    private function upsertUnidade(string $nome, string $tipo, string $diretor, string $secretario): int
    {
        $exist = $this->db->fetch('SELECT id FROM unidades WHERE nome = :n LIMIT 1', ['n' => $nome]);
        $payload = [
            'nome' => $nome,
            'tipo' => $tipo,
            'razao_social' => 'Associação Educacional Colégio Educa',
            'cnpj' => $tipo === 'matriz' ? '12.345.678/0001-90' : '12.345.678/0002-71',
            'inep' => $tipo === 'matriz' ? '35012345' : '35012346',
            'endereco' => 'Rua das Palmeiras',
            'numero' => $tipo === 'matriz' ? '100' : '200',
            'bairro' => 'Centro',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
            'cep' => '01310-100',
            'telefone' => '(11) 3000-1000',
            'email' => $tipo === 'matriz' ? 'fund@educa.local' : 'em@educa.local',
            'diretor_nome' => $diretor,
            'secretario_nome' => $secretario,
            'ativo' => 1,
        ];
        if ($exist) {
            $this->unidadesModel->update((int) $exist['id'], $payload);
            return (int) $exist['id'];
        }
        return (int) $this->unidadesModel->create($payload);
    }

    private function criarAnosLetivos(): void
    {
        foreach ([2023, 2024, 2025, 2026] as $ano) {
            $row = $this->db->fetch('SELECT * FROM ano_letivo WHERE ano = :a LIMIT 1', ['a' => $ano]);
            if (!$row) {
                $id = (int) $this->db->insert(
                    'INSERT INTO ano_letivo (ano, data_inicio, data_fim, ativo) VALUES (:ano, :ini, :fim, :ativo)',
                    [
                        'ano' => $ano,
                        'ini' => $ano . '-02-01',
                        'fim' => $ano . '-12-15',
                        'ativo' => $ano === 2026 ? 1 : 0,
                    ]
                );
                $row = $this->db->fetch('SELECT * FROM ano_letivo WHERE id = :id', ['id' => $id]);
            }
            $this->anos[$ano] = [
                'id' => (int) $row['id'],
                'ano' => $ano,
                'inicio' => (string) ($row['data_inicio'] ?? ($ano . '-02-01')),
                'fim' => (string) ($row['data_fim'] ?? ($ano . '-12-15')),
            ];
        }
        $this->db->query('UPDATE ano_letivo SET ativo = CASE WHEN ano = 2026 THEN 1 ELSE 0 END');
        $this->registrar('ano_letivo', true, '2023–2026 (ativo=2026)');
    }

    private function criarCursosSeries(): void
    {
        $this->cursoFundId = $this->upsertCurso('Ensino Fundamental — Anos Finais', 'regular', 1);
        $this->cursoEmId = $this->upsertCurso('Ensino Médio', 'regular', 2);
        foreach (self::SERIES_ORDEM as $chave) {
            $meta = $this->metaSerie($chave);
            $cursoId = $meta['curso'] === 'fund' ? $this->cursoFundId : $this->cursoEmId;
            $row = $this->db->fetch(
                'SELECT id FROM serie WHERE curso_id = :c AND nome = :n LIMIT 1',
                ['c' => $cursoId, 'n' => $meta['nome']]
            );
            if ($row) {
                $this->series[$chave] = (int) $row['id'];
                continue;
            }
            $this->series[$chave] = (int) $this->db->insert(
                'INSERT INTO serie (curso_id, nome, ordem, ativo) VALUES (:c, :n, :o, 1)',
                ['c' => $cursoId, 'n' => $meta['nome'], 'o' => $meta['ordem']]
            );
        }
        $this->registrar('curso_serie', true, '2 cursos / 7 séries');
    }

    private function upsertCurso(string $nome, string $tipo, int $ordem): int
    {
        $row = $this->db->fetch('SELECT id FROM curso WHERE nome = :n LIMIT 1', ['n' => $nome]);
        if ($row) {
            return (int) $row['id'];
        }
        return (int) $this->db->insert(
            'INSERT INTO curso (nome, descricao, ativo, ordem, tipo, possui_serie)
             VALUES (:n, :d, 1, :o, :t, 1)',
            ['n' => $nome, 'd' => $nome . ' — Colégio Educa', 'o' => $ordem, 't' => $tipo]
        );
    }

    private function carregarComponentes(): void
    {
        $obrigatorios = [
            'Língua Portuguesa', 'Matemática', 'História', 'Geografia', 'Arte',
            'Educação Física', 'Língua Inglesa', 'Ciências', 'Biologia', 'Física',
            'Química', 'Filosofia', 'Sociologia', 'Ensino Religioso',
        ];
        $rows = $this->db->fetchAll('SELECT * FROM materias WHERE ativo = 1') ?: [];
        foreach ($rows as $row) {
            $this->materias[(string) $row['nome']] = $row;
        }
        $faltando = [];
        foreach ($obrigatorios as $nome) {
            if (!isset($this->materias[$nome])) {
                $faltando[] = $nome;
            }
        }
        $this->registrar(
            'componentes',
            $faltando === [],
            $faltando === [] ? count($obrigatorios) . ' componentes do catálogo' : 'Faltando: ' . implode(', ', $faltando),
            $faltando === [] ? 'ALTO' : 'BLOQUEADOR'
        );
        if ($faltando !== []) {
            fail('Catálogo de componentes incompleto. Rode init_colegio_educa.php --force.');
        }
    }

    private function criarMatrizes(): void
    {
        $cargaFund = [
            'Língua Portuguesa' => 4, 'Matemática' => 4, 'Língua Inglesa' => 2,
            'História' => 2, 'Geografia' => 2, 'Ciências' => 3, 'Arte' => 1,
            'Educação Física' => 2, 'Ensino Religioso' => 1,
        ];
        $cargaEm = [
            'Língua Portuguesa' => 4, 'Matemática' => 4, 'Língua Inglesa' => 2,
            'História' => 2, 'Geografia' => 2, 'Física' => 2, 'Química' => 2,
            'Biologia' => 2, 'Filosofia' => 1, 'Sociologia' => 1, 'Arte' => 1,
            'Educação Física' => 2,
        ];
        foreach (self::SERIES_ORDEM as $chave) {
            $meta = $this->metaSerie($chave);
            $carga = $meta['curso'] === 'fund' ? $cargaFund : $cargaEm;
            $codigo = 'EDUCA-' . strtoupper($chave);
            $exist = $this->db->fetch('SELECT id FROM matrizes_curriculares WHERE codigo = :c LIMIT 1', ['c' => $codigo]);
            if ($exist) {
                $this->matrizPorSerie[$chave] = (int) $exist['id'];
                continue;
            }
            $componentes = [];
            $ordem = 1;
            foreach ($carga as $nome => $aulas) {
                $componentes[] = [
                    'materia_id' => (int) $this->materias[$nome]['id'],
                    'aulas_semana' => $aulas,
                    'obrigatorio' => 1,
                    'ordem_boletim' => $ordem,
                    'ordem_historico' => $ordem,
                ];
                $ordem++;
            }
            $cursoId = $meta['curso'] === 'fund' ? $this->cursoFundId : $this->cursoEmId;
            $res = $this->matrizes->criar([
                'nome' => 'Matriz ' . $meta['nome'] . ' — Educa',
                'codigo' => $codigo,
                'curso_id' => $cursoId,
                'serie_id' => $this->series[$chave],
                'modalidade' => 'presencial',
                'turno' => 'manha',
                'carga_horaria_anual_prevista' => $meta['curso'] === 'fund' ? '1000' : '1200',
                'dias_letivos_previstos' => '200',
                'duracao_padrao_aula_minutos' => 50,
                'base_legal' => 'BNCC / LDB 9.394/96',
                'observacoes' => 'Matriz Colégio Educa',
                'ativo' => 1,
                'componentes' => $componentes,
            ]);
            if (empty($res['success'])) {
                fail('Matriz ' . $chave . ': ' . ($res['error'] ?? 'erro'));
            }
            $this->matrizPorSerie[$chave] = (int) $res['id'];
        }
        $this->registrar('matriz', true, '7 matrizes (Fund II + EM)');
    }

    private function criarSalas(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $codigo = sprintf('EDUCA-SALA-%02d', $i);
            $nome = sprintf('Sala %02d', $i);
            $res = $this->salas->criar([
                'codigo' => $codigo,
                'nome' => $nome,
                'tipo' => 'sala',
                'capacidade' => '40',
                'bloco' => $i <= 4 ? 'A' : 'B',
                'andar' => $i <= 4 ? '1' : '2',
                'responsavel_nome' => 'Coordenação',
                'ativo' => 1,
            ]);
            if (!empty($res['success'])) {
                $this->salasIds[$codigo] = (int) $res['id'];
                continue;
            }
            $exist = $this->db->fetch('SELECT id FROM school_locations WHERE codigo = :c LIMIT 1', ['c' => $codigo]);
            if (!$exist) {
                fail('Sala ' . $codigo . ': ' . ($res['error'] ?? 'erro'));
            }
            $this->salasIds[$codigo] = (int) $exist['id'];
        }
        $this->registrar('salas', true, count($this->salasIds) . ' salas');
    }

    private function salaDaTurma(string $serie, string $letra): int
    {
        $idx = (array_search($serie, self::SERIES_ORDEM, true) ?: 0) % 8;
        $letraOff = array_search($letra, LETRAS, true) ?: 0;
        $n = (($idx + $letraOff) % 8) + 1;
        return $this->salasIds[sprintf('EDUCA-SALA-%02d', $n)] ?? array_values($this->salasIds)[0];
    }

    private function criarTiposAvaliacao(): void
    {
        $this->tipoProvaId = $this->tipoPorNome('Prova Bimestral', 'Avaliação principal do bimestre.', 20, 'prova_bim');
        $this->tipoAtivId = $this->tipoPorNome('Atividade em Aula', 'Atividade realizada em aula.', 40, 'trabalho');
        $this->tipoRecId = $this->tipoPorNome('Recuperação', 'Recuperação periódica.', 50, 'recuperacao');
        $this->registrar('tipos_avaliacao', true, "prova={$this->tipoProvaId} ativ={$this->tipoAtivId} rec={$this->tipoRecId}");
    }

    private function tipoPorNome(string $nome, string $desc, int $ordem, string $chave): int
    {
        $row = $this->db->fetch(
            'SELECT id FROM provas_tipos_avaliacao WHERE LOWER(nome) = LOWER(:n) AND deleted_at IS NULL LIMIT 1',
            ['n' => $nome]
        );
        if ($row) {
            return (int) $row['id'];
        }
        return $this->tipos->create([
            'nome' => $nome,
            'descricao' => $desc,
            'ativo' => 1,
            'ordem' => $ordem,
            'chave_quadro' => $chave,
        ]);
    }

    private function criarProfessores(): void
    {
        $this->profMaterias = [
            'mat' => ['Matemática', 'Física'],
            'ling' => ['Língua Portuguesa', 'Língua Inglesa', 'Arte'],
            'hum' => ['História', 'Geografia', 'Filosofia', 'Sociologia', 'Ensino Religioso'],
            'nat' => ['Ciências', 'Biologia', 'Química'],
            'edf' => ['Educação Física'],
        ];
        $criados = 0;
        foreach (self::SERIES_ORDEM as $serie) {
            foreach (LETRAS as $letra) {
                foreach (array_keys($this->profMaterias) as $area) {
                    $chave = $this->chaveProfessor($area, $serie, $letra);
                    $email = $this->emailProfessor($area, $serie, $letra);
                    $exist = $this->db->fetch('SELECT id FROM professores WHERE email = :e LIMIT 1', ['e' => $email]);
                    if ($exist) {
                        $this->profs[$chave] = (int) $exist['id'];
                        continue;
                    }
                    $this->profs[$chave] = (int) $this->teachers->create([
                        'nome' => $this->nomeProfessor($area, $serie, $letra),
                        'email' => $email,
                        'senha' => SENHA,
                        'codigo_prof' => $this->codigoProfessor($area, $serie, $letra),
                        'materias' => $this->profMaterias[$area],
                        'turmas' => [],
                        'ativo' => 1,
                        'pagante' => 1,
                    ]);
                    $criados++;
                }
            }
        }
        $this->registrar('professores', true, count($this->profs) . ' professores (1 por turma/área)');
    }

    private function chaveProfessor(string $area, string $serie, string $letra): string
    {
        return $area . ':' . $serie . ':' . $letra;
    }

    private function emailProfessor(string $area, string $serie, string $letra): string
    {
        if ($serie === 'fund6' && $letra === 'A') {
            return $area . '@educa.local';
        }
        return $area . '.' . $this->codigoSerie($serie) . strtolower($letra) . '@educa.local';
    }

    private function codigoProfessor(string $area, string $serie, string $letra): string
    {
        return 'EDU-' . strtoupper($area) . '-' . $this->codigoSerie($serie) . $letra;
    }

    private function nomeProfessor(string $area, string $serie, string $letra): string
    {
        $fixos = [
            'mat:fund6:A' => 'Marcos Tavares',
            'ling:fund6:A' => 'Lúcia Prado',
            'hum:fund6:A' => 'Hugo Sampaio',
            'nat:fund6:A' => 'Natália Correia',
            'edf:fund6:A' => 'Érica Fontes',
        ];
        $chave = $this->chaveProfessor($area, $serie, $letra);
        if (isset($fixos[$chave])) {
            return $fixos[$chave];
        }
        $serieIdx = array_search($serie, self::SERIES_ORDEM, true);
        $letraIdx = array_search($letra, LETRAS, true);
        $areaIdx = array_search($area, array_keys($this->profMaterias), true);
        $n = ((int) $serieIdx * 15) + ((int) $letraIdx * 5) + (int) $areaIdx;
        return self::NOMES[$n % count(self::NOMES)] . ' ' . self::SOBRENOMES[($n * 3) % count(self::SOBRENOMES)];
    }

    private function areaDaMateria(string $nome): string
    {
        foreach ($this->profMaterias as $area => $lista) {
            if (in_array($nome, $lista, true)) {
                return $area;
            }
        }
        throw new RuntimeException('Sem área para ' . $nome);
    }

    private function criarRegrasAcademicas(): void
    {
        foreach (
            [
                ['Regra Fundamental Educa', 'fund-educa', $this->cursoFundId],
                ['Regra EM Educa', 'em-educa', $this->cursoEmId],
            ] as $def
        ) {
            $res = $this->regras->criar([
                'nome' => $def[0],
                'codigo' => $def[1],
                'ano_letivo' => null,
                'curso_id' => $def[2],
                'media_minima' => 6.0,
                'frequencia_minima' => 75.0,
                'usar_frequencia' => 1,
                'periodo_tipo' => 'ano',
                'recuperacao_tipo' => 'periodo',
                'recuperacao_composicao' => 'maior_nota',
                'round_mode' => 'none',
                'decimal_places' => 2,
                'ativo' => 1,
            ], $this->adminId, 'Admin Colégio Educa');
            if (empty($res['success'])) {
                $exist = $this->db->fetch('SELECT id FROM regras_academicas WHERE codigo = :c LIMIT 1', ['c' => $def[1]]);
                $this->registrar('regra_' . $def[1], (bool) $exist, $exist ? 'id=' . (int) $exist['id'] : ($res['error'] ?? 'falha'), $exist ? 'ALTO' : 'CRITICO');
            }
        }
    }

    private function configurarFechamento(): void
    {
        $this->homologacao->model()->salvarConfigFechamento([
            'exigir_conselho' => 0,
            'exigir_frequencia' => 1,
            'exigir_notas' => 1,
        ], $this->adminId);
        $this->registrar('fechamento_config', true, 'exigir notas+frequência');
    }

    private function garantirCategoriaOcorrencia(): void
    {
        $tem = $this->db->fetch("SHOW TABLES LIKE 'ocorrencias_categorias'");
        if ($tem === false) {
            $this->registrar('ocorrencias_categoria', false, 'tabela ausente', 'MEDIO');
            return;
        }
        $cat = $this->db->fetch("SELECT id FROM ocorrencias_categorias WHERE nome LIKE '%edag%' LIMIT 1")
            ?: $this->db->fetch('SELECT id FROM ocorrencias_categorias ORDER BY id ASC LIMIT 1');
        if ($cat) {
            $this->categoriaPedagogicaId = (int) $cat['id'];
        } else {
            $this->categoriaPedagogicaId = (int) $this->db->insert(
                "INSERT INTO ocorrencias_categorias (slug, nome, ordem, ativo) VALUES ('pedagogica', 'Pedagógica', 1, 1)"
            );
        }
        $this->registrar('ocorrencias_categoria', $this->categoriaPedagogicaId > 0, 'id=' . $this->categoriaPedagogicaId);
    }

    private function criarCalendarios(): void
    {
        $tem = $this->db->fetch("SHOW TABLES LIKE 'calendario_letivo'");
        if ($tem === false) {
            return;
        }
        foreach ([2023, 2024, 2025, 2026] as $ano) {
            $exist = $this->db->fetch('SELECT id FROM calendario_letivo WHERE ano = :a LIMIT 1', ['a' => $ano]);
            $calId = $exist ? (int) $exist['id'] : (int) $this->db->insert(
                'INSERT INTO calendario_letivo (ano, dias_meta, carga_horaria_meta, observacao) VALUES (:a, 200, 800, :o)',
                ['a' => $ano, 'o' => 'Calendário Colégio Educa']
            );
            $eventos = [
                [$ano . '-04-21', $ano . '-04-21', 'feriado', 'Tiradentes'],
                [$ano . '-07-10', $ano . '-07-24', 'recesso', 'Recesso de julho'],
                [$ano . '-09-07', $ano . '-09-07', 'feriado', 'Independência'],
                [$ano . '-12-10', $ano . '-12-10', 'evento', 'Conselho de classe final'],
            ];
            foreach ($eventos as $ev) {
                $ja = $this->db->fetch(
                    'SELECT id FROM calendario_letivo_eventos WHERE calendario_id = :c AND data_inicio = :d AND descricao = :desc LIMIT 1',
                    ['c' => $calId, 'd' => $ev[0], 'desc' => $ev[3]]
                );
                if ($ja) {
                    continue;
                }
                $this->db->insert(
                    'INSERT INTO calendario_letivo_eventos
                        (calendario_id, data_inicio, data_fim, tipo, descricao, visivel_aluno, visivel_professor, visivel_pais)
                     VALUES (:c, :ini, :fim, :tipo, :desc, 1, 1, 1)',
                    ['c' => $calId, 'ini' => $ev[0], 'fim' => $ev[1], 'tipo' => $ev[2], 'desc' => $ev[3]]
                );
            }
        }
        $this->registrar('calendario', true, '2023–2026');
    }

    private function executarAno(int $ano): void
    {
        foreach (self::SERIES_ORDEM as $serie) {
            foreach (LETRAS as $letra) {
                $this->criarTurma($ano, $serie, $letra);
            }
        }
        $this->atualizarTurmasDosProfessores();
        foreach (self::SERIES_ORDEM as $serie) {
            foreach (LETRAS as $letra) {
                $this->criarGrade($ano, $serie, $letra);
            }
        }
        $this->matricularAno($ano);

        $bims = $ano === 2026 ? [1, 2] : [1, 2, 3, 4];
        foreach (self::SERIES_ORDEM as $serie) {
            foreach (LETRAS as $letra) {
                $turmaId = $this->turmaIds[$this->chaveTurma($ano, $serie, $letra)];
                foreach ($bims as $bim) {
                    $this->executarBimestre($ano, $serie, $letra, $turmaId, $bim);
                }
                if ($ano < 2026) {
                    $this->fecharDiarios($ano, $turmaId, $bims);
                }
            }
        }

        if ($ano < 2026) {
            $this->registrarOcorrenciasAno($ano);
            $this->executarConselhoAmostra($ano);
            foreach (self::SERIES_ORDEM as $serie) {
                foreach (LETRAS as $letra) {
                    $turmaId = $this->turmaIds[$this->chaveTurma($ano, $serie, $letra)];
                    $this->homologarTurma($ano, $turmaId);
                }
            }
            $this->encerrarAnoEFilaRematricula($ano);
        } else {
            $this->transferirAlunoAndamento($ano);
        }

        $this->criarPlanosAmostra($ano);
    }

    private function criarTurma(int $ano, string $serie, string $letra): void
    {
        $nome = $this->nomeTurma($ano, $serie, $letra);
        $chave = $this->chaveTurma($ano, $serie, $letra);
        $exist = $this->db->fetch('SELECT id FROM turmas WHERE nome = :n LIMIT 1', ['n' => $nome]);
        if ($exist) {
            $this->turmaIds[$chave] = (int) $exist['id'];
            return;
        }
        $meta = $this->metaSerie($serie);
        $cursoId = $meta['curso'] === 'fund' ? $this->cursoFundId : $this->cursoEmId;
        $id = (int) $this->turmas->create([
            'nome' => $nome,
            'ano_letivo' => $ano,
            'ano_letivo_id' => $this->anos[$ano]['id'],
            'serie' => $meta['nome'],
            'ativo' => 1,
            'curso_novo_id' => $cursoId,
            'serie_id' => $this->series[$serie],
            'matriz_curricular_id' => $this->matrizPorSerie[$serie],
            'turno' => 'manha',
            'sala_padrao_id' => $this->salaDaTurma($serie, $letra),
            'vagas' => 40,
            'observacoes' => PREFIXO . ' ' . $nome,
        ]);
        $this->turmaIds[$chave] = $id;
    }

    private function atualizarTurmasDosProfessores(): void
    {
        foreach ($this->profs as $chave => $pid) {
            [$area, $serie, $letra] = explode(':', $chave);
            $ids = [];
            foreach ($this->turmaIds as $turmaChave => $tid) {
                [, $s, $l] = explode(':', $turmaChave);
                if ($s === $serie && $l === $letra) {
                    $ids[] = $tid;
                }
            }
            $nome = (string) ($this->db->fetch('SELECT nome FROM professores WHERE id = :id', ['id' => $pid])['nome'] ?? $chave);
            $this->teachers->update($pid, [
                'nome' => $nome,
                'email' => $this->emailProfessor($area, $serie, $letra),
                'codigo_prof' => $this->codigoProfessor($area, $serie, $letra),
                'materias' => $this->profMaterias[$area],
                'turmas' => $ids,
                'ativo' => 1,
                'pagante' => 1,
            ]);
        }
    }

    /** @return array{0:list<array{0:string,1:string}>,1:array<int,list<string>>} */
    private function gradeMapa(string $serie): array
    {
        $slots = [
            ['07:30:00', '08:20:00'],
            ['08:20:00', '09:10:00'],
            ['09:30:00', '10:20:00'],
            ['10:20:00', '11:10:00'],
            ['11:10:00', '12:00:00'],
        ];
        $meta = $this->metaSerie($serie);
        if ($meta['curso'] === 'fund') {
            $grade = [
                1 => ['Língua Portuguesa', 'Língua Portuguesa', 'Matemática', 'Matemática', 'História'],
                2 => ['Língua Portuguesa', 'Matemática', 'Ciências', 'Ciências', 'Geografia'],
                3 => ['Língua Portuguesa', 'Matemática', 'Língua Inglesa', 'Arte', 'Ensino Religioso'],
                4 => ['Língua Inglesa', 'História', 'Geografia', 'Ciências', 'Educação Física'],
                5 => ['Matemática', 'História', 'Geografia', 'Educação Física', 'Arte'],
            ];
        } else {
            $grade = [
                1 => ['Língua Portuguesa', 'Língua Portuguesa', 'Matemática', 'Matemática', 'História'],
                2 => ['Língua Portuguesa', 'Matemática', 'Física', 'Química', 'Geografia'],
                3 => ['Língua Portuguesa', 'Matemática', 'Biologia', 'Língua Inglesa', 'Filosofia'],
                4 => ['Língua Inglesa', 'História', 'Geografia', 'Física', 'Sociologia'],
                5 => ['Biologia', 'Química', 'Arte', 'Educação Física', 'Educação Física'],
            ];
        }
        return [$slots, $grade];
    }

    private function professorDaMateria(string $nome, string $serie, string $letra): int
    {
        $area = $this->areaDaMateria($nome);
        $chave = $this->chaveProfessor($area, $serie, $letra);
        if (!isset($this->profs[$chave])) {
            throw new RuntimeException('Sem professor para ' . $nome . ' em ' . $serie . ' ' . $letra);
        }
        return $this->profs[$chave];
    }

    private function criarGrade(int $ano, string $serie, string $letra): void
    {
        $turmaId = $this->turmaIds[$this->chaveTurma($ano, $serie, $letra)];
        $ja = $this->db->fetch('SELECT COUNT(*) AS c FROM grade_horaria WHERE turma_id = :t', ['t' => $turmaId]);
        if ((int) ($ja['c'] ?? 0) > 0) {
            $this->grades[$turmaId] = $this->db->fetchAll('SELECT * FROM grade_horaria WHERE turma_id = :t', ['t' => $turmaId]) ?: [];
            return;
        }
        [$slots, $mapa] = $this->gradeMapa($serie);
        foreach ($mapa as $dia => $materias) {
            foreach ($materias as $i => $nome) {
                $this->db->insert(
                    "INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
                     VALUES (:dia, :de, :ate, :turma, :prof, :mat, 'manha')",
                    [
                        'dia' => $dia,
                        'de' => $slots[$i][0],
                        'ate' => $slots[$i][1],
                        'turma' => $turmaId,
                        'prof' => $this->professorDaMateria($nome, $serie, $letra),
                        'mat' => (int) $this->materias[$nome]['id'],
                    ]
                );
            }
        }
        $this->grades[$turmaId] = $this->db->fetchAll('SELECT * FROM grade_horaria WHERE turma_id = :t', ['t' => $turmaId]) ?: [];
    }

    private function matricularAno(int $ano): void
    {
        if ($ano === 2023) {
            foreach (self::SERIES_ORDEM as $serie) {
                foreach (LETRAS as $letra) {
                    $this->criarAlunosTurma($ano, $serie, $letra, 'entrada');
                }
            }
            println('  matrículas 2023: base completa (7 séries × 3 turmas)');
            return;
        }

        $remat = 0;
        foreach ($this->filaRematricula as $item) {
            $this->matricularAlunoExistente(
                (int) $item['aluno_id'],
                $ano,
                (string) $item['serie'],
                (string) $item['letra']
            );
            $remat++;
        }
        $this->filaRematricula = [];

        foreach (LETRAS as $letra) {
            $this->criarAlunosTurma($ano, 'fund6', $letra, 'entrada');
        }

        $intermediarias = ['fund7', 'fund8', 'fund9', 'em1', 'em2', 'em3'];
        foreach ($intermediarias as $serie) {
            $this->criarAlunosTurma($ano, $serie, 'A', 'transferencia_entrada', 2);
        }
        println("  rematrículas={$remat} + nova coorte 6º + transferências de entrada");
    }

    private function criarAlunosTurma(int $ano, string $serie, string $letra, string $origem, ?int $qtd = null): void
    {
        $qtd = $qtd ?? ALUNOS_POR_TURMA;
        $turmaId = $this->turmaIds[$this->chaveTurma($ano, $serie, $letra)];
        $meta = $this->metaSerie($serie);
        $unidadeId = $meta['unidade'] === 'fund' ? $this->unidadeFundId : $this->unidadeEmId;
        $yy = substr((string) $ano, 2, 2);
        $sc = $this->codigoSerie($serie);
        $prefixoNick = $origem === 'transferencia_entrada'
            ? sprintf('edt%s%s%s', $yy, $sc, strtolower($letra))
            : sprintf('ed%s%s%s', $yy, $sc, strtolower($letra));

        for ($i = 1; $i <= $qtd; $i++) {
            $nick = $prefixoNick . sprintf('%02d', $i);
            $cenario = $origem === 'transferencia_entrada' ? 'regular' : $this->cenarioDoSlot($letra, $i);
            $nome = $this->nomePessoa($ano, $serie, $letra, $i, $origem === 'transferencia_entrada');
            $alunoId = $this->upsertAluno($nick, $nome, $turmaId, $meta['nome'], $unidadeId, $i, $ano, $serie);
            $this->alunoIds[$nick] = $alunoId;
            $this->cenarioPorAluno[$alunoId] = $cenario;
            $this->letraPorAluno[$alunoId] = $letra;
            $this->seriePorAluno[$alunoId] = $serie;
            $this->vincular($alunoId, $turmaId, $ano);
        }
    }

    private function nomePessoa(int $ano, string $serie, string $letra, int $slot, bool $transf): string
    {
        $idx = abs($ano + ord($letra) + $slot + ord($this->codigoSerie($serie)));
        $nome = self::NOMES[$idx % count(self::NOMES)];
        $sob = self::SOBRENOMES[($idx * 7) % count(self::SOBRENOMES)];
        return $nome . ' ' . $sob . ($transf ? ' (transf.)' : '');
    }

    private function upsertAluno(
        string $nick,
        string $nome,
        int $turmaId,
        string $serieNome,
        int $unidadeId,
        int $slot,
        int $ano,
        string $serie
    ): int {
        $exist = $this->db->fetch('SELECT id FROM alunos WHERE nickname = :n LIMIT 1', ['n' => $nick]);
        if ($exist) {
            $this->db->query(
                'UPDATE alunos SET turma_id = :t, serie = :s, unidade_id = :u, ativo = 1, status = :st WHERE id = :id',
                ['t' => $turmaId, 's' => $serieNome, 'u' => $unidadeId, 'st' => 'ACTIVE', 'id' => (int) $exist['id']]
            );
            return (int) $exist['id'];
        }
        $nascAno = $ano - (11 + (int) $this->codigoSerie($serie));
        return (int) $this->db->insert(
            "INSERT INTO alunos
                (nome, nickname, email, senha_hash, ra, turma_id, serie, unidade_id, data_nasc, ativo, pagante, status, password, primeiro_acesso)
             VALUES
                (:nome, :nickname, :email, :senha_hash, :ra, :turma_id, :serie, :unidade_id, :data_nasc, 1, 1, 'ACTIVE', '', 0)",
            [
                'nome' => $nome,
                'nickname' => $nick,
                'email' => $nick . '@educa.local',
                'senha_hash' => $this->hash,
                'ra' => strtoupper(str_replace('.', '', $nick)),
                'turma_id' => $turmaId,
                'serie' => $serieNome,
                'unidade_id' => $unidadeId,
                'data_nasc' => sprintf('%04d-03-%02d', $nascAno, min(28, $slot)),
            ]
        );
    }

    private function matricularAlunoExistente(int $alunoId, int $ano, string $serie, string $letra): void
    {
        $turmaId = $this->turmaIds[$this->chaveTurma($ano, $serie, $letra)];
        $meta = $this->metaSerie($serie);
        $unidadeId = $meta['unidade'] === 'fund' ? $this->unidadeFundId : $this->unidadeEmId;
        $this->db->query(
            'UPDATE alunos SET turma_id = :t, serie = :s, unidade_id = :u, status = :st WHERE id = :id',
            ['t' => $turmaId, 's' => $meta['nome'], 'u' => $unidadeId, 'st' => 'ACTIVE', 'id' => $alunoId]
        );
        $this->seriePorAluno[$alunoId] = $serie;
        $this->letraPorAluno[$alunoId] = $letra;
        $this->vincular($alunoId, $turmaId, $ano);
    }

    private function vincular(int $alunoId, int $turmaId, int $ano): void
    {
        try {
            $this->movimentacao->vincularAlunoTurma($alunoId, $turmaId, $this->anos[$ano]['id'], true, $ano . '-02-01');
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'já possui matrícula') === false) {
                $this->registrar('matricula', false, 'aluno ' . $alunoId . ': ' . $e->getMessage(), 'CRITICO');
            }
        }
    }

    private function executarBimestre(int $ano, string $serie, string $letra, int $turmaId, int $bim): void
    {
        println("  · {$ano} {$serie} {$letra} B{$bim}");
        $this->lancarDiario($ano, $turmaId, $bim);
        $eventos = $this->criarEventosBimestre($ano, $serie, $letra, $turmaId, $bim);
        $this->lancarNotasEventos($turmaId, $bim, $eventos);
        if ($bim === 2) {
            $this->criarRecuperacao($ano, $serie, $letra, $turmaId, $bim, $eventos);
        }
        $this->gerarBoletim($ano, $serie, $letra, $turmaId, $bim, $eventos);
    }

    private function lancarDiario(int $ano, int $turmaId, int $bim): void
    {
        $periodo = $this->diario->periodoDoBimestre($ano, $bim);
        $inicio = max($periodo['inicio'], $ano . '-02-01');
        $fim = min($periodo['fim'], $ano . '-12-15');
        $alunos = $this->alunosAtivosNaTurma($turmaId, $this->anos[$ano]['id']);
        if ($alunos === []) {
            $this->registrar('diario', false, "sem alunos turma={$turmaId} {$ano} B{$bim}", 'CRITICO');
            return;
        }
        $grades = $this->grades[$turmaId] ?? [];
        $porDia = [];
        foreach ($grades as $g) {
            $porDia[(int) $g['dia_semana']][] = $g;
        }
        $diasLetivos = 0;
        $start = new DateTime($inicio);
        $end = new DateTime($fim);
        for ($d = clone $start; $d <= $end; $d->modify('+1 day')) {
            $n = (int) $d->format('N');
            if ($n > 5 || !isset($porDia[$n])) {
                continue;
            }
            $diasLetivos++;
            if ($diasLetivos > 5) {
                break;
            }
            $data = $d->format('Y-m-d');
            foreach ($porDia[$n] as $grade) {
                $aula = $this->diario->getOrCreateAula($grade, $data, null);
                $aulaId = (int) ($aula['id'] ?? 0);
                if ($aulaId <= 0) {
                    continue;
                }
                $freq = [];
                foreach ($alunos as $al) {
                    $aid = (int) $al['id'];
                    $freq[$aid] = [
                        'situacao' => $this->situacaoFrequencia($aid, $bim, $n, $data),
                        'observacao' => '',
                    ];
                }
                $this->diario->salvar(
                    $aulaId,
                    'conforme_planejado',
                    'Conteúdo ' . PREFIXO . ' — ' . $data,
                    '',
                    $freq,
                    true
                );
            }
        }
    }

    private function situacaoFrequencia(int $alunoId, int $bim, int $diaSemana, string $data): string
    {
        $cenario = $this->cenarioPorAluno[$alunoId] ?? 'regular';
        if ($cenario === 'frequencia') {
            return $diaSemana >= 3 ? 'falta' : 'presente';
        }
        if ($cenario === 'regular' && $bim === 1 && $diaSemana === 1) {
            return 'atraso';
        }
        return 'presente';
    }

    private function alunosAtivosNaTurma(int $turmaId, int $anoLetivoId): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.nome, a.nickname
             FROM alunos a
             INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t AND m.ano_letivo_id = :a
             WHERE m.status = 'ativa' AND (m.data_saida IS NULL)
             ORDER BY a.nome",
            ['t' => $turmaId, 'a' => $anoLetivoId]
        ) ?: [];
    }

    private function alunosDaTurmaAno(int $turmaId, int $anoLetivoId): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.nome, a.nickname, m.status
             FROM alunos a
             INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t AND m.ano_letivo_id = :a
             WHERE m.status IN ('ativa','transferido','concluido')
             ORDER BY a.nome",
            ['t' => $turmaId, 'a' => $anoLetivoId]
        ) ?: [];
    }

    /** @return array<string,int> */
    private function criarEventosBimestre(int $ano, string $serie, string $letra, int $turmaId, int $bim): array
    {
        $periodo = $this->diario->periodoDoBimestre($ano, $bim);
        $profsEvento = $this->professoresEvento([$turmaId], $serie, $letra);
        $defs = [
            'p1' => ['Prova Bimestral 1', $this->tipoProvaId, $periodo['inicio']],
            'p2' => ['Prova Bimestral 2', $this->tipoProvaId, date('Y-m-d', strtotime($periodo['inicio'] . ' +35 days'))],
            'atv' => ['Atividade em Aula', $this->tipoAtivId, date('Y-m-d', strtotime($periodo['inicio'] . ' +20 days'))],
        ];
        $ids = [];
        foreach ($defs as $k => $def) {
            $titulo = sprintf('%s %s — %s %s %d B%d', PREFIXO, $def[0], $serie, $letra, $ano, $bim);
            $exist = $this->db->fetch(
                'SELECT id FROM provas_blocos WHERE titulo = :t AND deleted_at IS NULL LIMIT 1',
                ['t' => $titulo]
            );
            if ($exist) {
                $ids[$k] = (int) $exist['id'];
                continue;
            }
            $ids[$k] = (int) $this->blocos->create([
                'titulo' => $titulo,
                'descricao' => 'Evento de lançamento de nota — Colégio Educa',
                'data_prova' => $def[2],
                'hora_inicio' => '08:00:00',
                'hora_fim' => '09:30:00',
                'criado_por' => $this->adminId,
                'tipo_prova' => 'original',
                'configuracao_nota' => 'coordenacao_calcula',
                'formato_evento' => 'lancamento_nota',
                'ano_letivo' => $ano,
                'bimestre' => $bim,
                'tipo_avaliacao_id' => $def[1],
                'liberado' => 1,
                'ativo' => 1,
                'visivel_no_portal_aluno' => 1,
                'nota_unica_todas_materias' => 0,
                'turmas' => [$turmaId],
                'professores' => $profsEvento,
            ]);
        }
        return $ids;
    }

    /** @param list<int> $turmaIds */
    private function professoresEvento(array $turmaIds, string $serie, string $letra): array
    {
        $nomes = $this->materiasDaSerie($serie);
        $out = [];
        foreach ($this->profMaterias as $area => $lista) {
            foreach ($lista as $nomeMat) {
                if (!in_array($nomeMat, $nomes, true)) {
                    continue;
                }
                $out[] = [
                    'professor_id' => $this->professorDaMateria($nomeMat, $serie, $letra),
                    'materia_id' => (int) $this->materias[$nomeMat]['id'],
                    'quantidade_questoes' => 1,
                    'turmas' => $turmaIds,
                ];
            }
        }
        return $out;
    }

    /** @return list<string> */
    private function materiasDaSerie(string $serie): array
    {
        $meta = $this->metaSerie($serie);
        if ($meta['curso'] === 'fund') {
            return ['Língua Portuguesa', 'Matemática', 'Língua Inglesa', 'História', 'Geografia', 'Ciências', 'Arte', 'Educação Física', 'Ensino Religioso'];
        }
        return ['Língua Portuguesa', 'Matemática', 'Língua Inglesa', 'História', 'Geografia', 'Física', 'Química', 'Biologia', 'Filosofia', 'Sociologia', 'Arte', 'Educação Física'];
    }

    private function lancarNotasEventos(int $turmaId, int $bim, array $eventos): void
    {
        $alunos = $this->db->fetchAll(
            "SELECT DISTINCT a.id
             FROM alunos a
             INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t
             INNER JOIN turmas tu ON tu.id = m.turma_id
             INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = tu.ano_letivo
             WHERE m.status IN ('ativa', 'transferido', 'concluido')",
            ['t' => $turmaId]
        ) ?: [];
        $serie = $this->serieDaTurmaId($turmaId);
        $letra = $this->letraDaTurmaId($turmaId);
        foreach ($eventos as $tipoEv => $blocoId) {
            foreach ($this->profMaterias as $area => $nomes) {
                foreach ($nomes as $nomeMat) {
                    if (!in_array($nomeMat, $this->materiasDaSerie($serie), true)) {
                        continue;
                    }
                    $linhas = [];
                    foreach ($alunos as $al) {
                        $aid = (int) $al['id'];
                        $linhas[] = [
                            'turma_id' => $turmaId,
                            'aluno_id' => $aid,
                            'nota' => $this->notaDoAluno($aid, $bim, (string) $tipoEv),
                        ];
                    }
                    $this->notas->upsertLinhas(
                        $blocoId,
                        $this->professorDaMateria($nomeMat, $serie, $letra),
                        (int) $this->materias[$nomeMat]['id'],
                        $linhas
                    );
                }
            }
        }
    }

    private function notaDoAluno(int $alunoId, int $bim, string $tipoEv): float
    {
        $cenario = $this->cenarioPorAluno[$alunoId] ?? 'regular';
        $base = [
            'regular' => [8.0, 8.5, 9.0, 8.5],
            'recuperacao' => [4.0, 5.0, 7.0, 8.0],
            'frequencia' => [7.0, 7.0, 7.0, 7.0],
            'reprovado' => [3.0, 4.0, 4.0, 3.5],
            'transferencia' => [7.0, 7.5, 7.0, 7.0],
        ][$cenario] ?? [7.5, 7.5, 7.5, 7.5];
        $n = $base[$bim - 1] ?? 7.5;
        if ($tipoEv === 'p2') {
            $n = min(10, $n + 0.3);
        } elseif ($tipoEv === 'atv') {
            $n = max(0, $n - 0.4);
        } elseif ($tipoEv === 'rec') {
            $n = $cenario === 'recuperacao' ? 8.0 : $n;
        }
        return round($n, 1);
    }

    private function criarRecuperacao(int $ano, string $serie, string $letra, int $turmaId, int $bim, array &$eventos): void
    {
        $periodo = $this->diario->periodoDoBimestre($ano, $bim);
        $titulo = sprintf('%s Recuperação — %s %s %d B%d', PREFIXO, $serie, $letra, $ano, $bim);
        $exist = $this->db->fetch('SELECT id FROM provas_blocos WHERE titulo = :t AND deleted_at IS NULL LIMIT 1', ['t' => $titulo]);
        if ($exist) {
            $eventos['rec'] = (int) $exist['id'];
        } else {
            $eventos['rec'] = (int) $this->blocos->create([
                'titulo' => $titulo,
                'descricao' => 'Recuperação Colégio Educa',
                'data_prova' => $periodo['fim'],
                'hora_inicio' => '08:00:00',
                'hora_fim' => '09:30:00',
                'criado_por' => $this->adminId,
                'tipo_prova' => 'original',
                'configuracao_nota' => 'coordenacao_calcula',
                'formato_evento' => 'lancamento_nota',
                'ano_letivo' => $ano,
                'bimestre' => $bim,
                'tipo_avaliacao_id' => $this->tipoRecId,
                'liberado' => 1,
                'ativo' => 1,
                'turmas' => [$turmaId],
                'professores' => $this->professoresEvento([$turmaId], $serie, $letra),
            ]);
        }
        $this->lancarNotasEventos($turmaId, $bim, ['rec' => $eventos['rec']]);
    }

    private function gerarBoletim(int $ano, string $serie, string $letra, int $turmaId, int $bim, array $eventos): void
    {
        $codigo = sprintf('educa-%d-%s-%s-b%d', $ano, $serie, $letra, $bim);
        $periodoRef = $ano . '-B' . $bim;
        $periodo = $this->diario->periodoDoBimestre($ano, $bim);
        $componentes = [
            ['codigo' => 'P1', 'nome' => 'Prova Bimestral 1', 'source_type' => 'provas_sistema', 'calc_type' => 'media', 'peso' => 0.4, 'blocos_ids' => (string) $eventos['p1'], 'obrigatorio' => 1],
            ['codigo' => 'P2', 'nome' => 'Prova Bimestral 2', 'source_type' => 'provas_sistema', 'calc_type' => 'media', 'peso' => 0.4, 'blocos_ids' => (string) $eventos['p2'], 'obrigatorio' => 1],
            ['codigo' => 'ATV', 'nome' => 'Atividade em Aula', 'source_type' => 'provas_sistema', 'calc_type' => 'media', 'peso' => 0.2, 'blocos_ids' => (string) $eventos['atv'], 'obrigatorio' => 1],
        ];
        $formula = '(P1 * 0.4) + (P2 * 0.4) + (ATV * 0.2)';
        if (!empty($eventos['rec'])) {
            $componentes[] = ['codigo' => 'REC', 'nome' => 'Recuperação', 'source_type' => 'provas_sistema', 'calc_type' => 'media', 'peso' => 0, 'blocos_ids' => (string) $eventos['rec'], 'obrigatorio' => 0];
        }
        $existente = $this->boletim->getRuleByCode($codigo);
        $regraId = $this->boletim->saveRule(
            sprintf('Boletim %s %s %d B%d', $serie, $letra, $ano, $bim),
            $formula,
            $componentes,
            $existente ? (int) $existente['id'] : null,
            'Educa 2 provas + atividade',
            null,
            null,
            $codigo,
            json_encode([$this->series[$serie]]),
            json_encode([$turmaId]),
            'boletim',
            $ano,
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
        $regra = $this->boletim->getRuleById($regraId);
        $alunos = $this->alunosDaTurmaAno($turmaId, $this->anos[$ano]['id']);
        $gerados = 0;
        foreach ($alunos as $al) {
            try {
                $sim = $this->boletimCtrl->simularRegraAluno($regra, (int) $al['id'], $periodoRef, $periodo['inicio'], $periodo['fim']);
                $matriz = $sim['matriz_materias'] ?? null;
                $colunas = is_array($matriz) && is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
                $rows = is_array($matriz) && is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
                $this->boletim->replaceGeneratedResultsForAluno(
                    $regraId,
                    (int) $al['id'],
                    $periodoRef,
                    $periodo['inicio'],
                    $periodo['fim'],
                    $colunas,
                    $rows,
                    false
                );
                $gerados++;
            } catch (Throwable $e) {
                $this->registrar('boletim', false, ($al['nome'] ?? '') . ': ' . $e->getMessage(), 'ALTO');
            }
        }
        println("    boletim gerados={$gerados}");
    }

    private function serieDaTurmaId(int $turmaId): string
    {
        foreach ($this->turmaIds as $k => $id) {
            if ($id === $turmaId) {
                return explode(':', $k)[1] ?? 'fund6';
            }
        }
        return 'fund6';
    }

    private function letraDaTurmaId(int $turmaId): string
    {
        foreach ($this->turmaIds as $k => $id) {
            if ($id === $turmaId) {
                return explode(':', $k)[2] ?? 'A';
            }
        }
        return 'A';
    }

    private function fecharDiarios(int $ano, int $turmaId, array $bims): void
    {
        foreach ($this->grades[$turmaId] ?? [] as $g) {
            foreach ($bims as $bim) {
                $this->diario->fechar(
                    $turmaId,
                    (int) $g['materia_id'],
                    (int) $g['professor_id'],
                    $ano,
                    $bim,
                    $this->adminId
                );
            }
        }
    }

    private function registrarOcorrenciasAno(int $ano): void
    {
        if ($this->categoriaPedagogicaId <= 0) {
            return;
        }
        $yy = substr((string) $ano, 2, 2);
        $nicks = [
            sprintf('ed%s6b10', $yy) => ['Frequência abaixo do mínimo legal', 'grave'],
            sprintf('ed%s9a09', $yy) => ['Rendimento insuficiente', 'moderado'],
        ];
        foreach ($nicks as $nick => $info) {
            $id = $this->alunoIds[$nick] ?? 0;
            if ($id <= 0) {
                $row = $this->db->fetch('SELECT id FROM alunos WHERE nickname = :n LIMIT 1', ['n' => $nick]);
                $id = $row ? (int) $row['id'] : 0;
            }
            if ($id <= 0) {
                continue;
            }
            $this->ocorrencias->criar([
                'aluno_id' => $id,
                'titulo' => $info[0],
                'detalhe' => $info[0] . ' — registro automático da população Educa.',
                'nivel_gravidade' => $info[1],
                'data_ocorrencia' => $ano . '-08-15 10:00:00',
                'categoria_id' => $this->categoriaPedagogicaId,
                'encaminhamento' => 'Acompanhamento da coordenação.',
                'local' => 'Sala de aula',
            ], $this->adminId, 'admin');
        }
    }

    private function executarConselhoAmostra(int $ano): void
    {
        foreach (['fund9', 'em3'] as $serie) {
            $turmaId = $this->turmaIds[$this->chaveTurma($ano, $serie, 'A')] ?? 0;
            if ($turmaId <= 0) {
                continue;
            }
            $res = $this->conselhos->criar([
                'turma_id' => $turmaId,
                'ano_letivo' => $ano,
                'bimestre' => 4,
                'data_reuniao' => $ano . '-12-05',
                'pauta' => 'Conselho final ' . $serie . ' A ' . $ano,
            ], $this->adminId);
            if (empty($res['success'])) {
                continue;
            }
            $sessaoId = (int) $res['id'];
            $sessao = $this->db->fetch('SELECT * FROM conselho_sessoes WHERE id = :id', ['id' => $sessaoId]);
            $status = (string) ($sessao['status'] ?? '');
            if (in_array($status, ['em_preparacao', 'reaberto'], true)) {
                $this->conselhos->abrir($sessaoId, $this->adminId);
            }
            $this->conselhos->finalizar($sessaoId, $this->adminId);
        }
    }

    private function homologarTurma(int $ano, int $turmaId): void
    {
        $res = $this->homologacao->homologarTurma($turmaId, $ano, 'ano', 0, $this->adminId, [], true);
        if (empty($res['success'])) {
            $this->registrar(
                'homologacao_' . $ano . '_t' . $turmaId,
                false,
                $res['error'] ?? 'falha',
                'ALTO'
            );
        }
    }

    private function encerrarAnoEFilaRematricula(int $ano): void
    {
        $anoId = $this->anos[$ano]['id'];
        $saida = $ano . '-12-15';
        $matriculas = $this->db->fetchAll(
            "SELECT m.id, m.aluno_id, m.turma_id, t.serie_id
             FROM matricula m
             INNER JOIN turmas t ON t.id = m.turma_id
             WHERE m.ano_letivo_id = :a AND m.status = 'ativa'",
            ['a' => $anoId]
        ) ?: [];

        $this->filaRematricula = [];
        foreach ($matriculas as $m) {
            $alunoId = (int) $m['aluno_id'];
            $serie = $this->seriePorAluno[$alunoId] ?? $this->serieDaTurmaId((int) $m['turma_id']);
            $letra = $this->letraPorAluno[$alunoId] ?? 'A';
            $cenario = $this->cenarioPorAluno[$alunoId] ?? 'regular';
            $meta = $this->metaSerie($serie);

            if ($cenario === 'transferencia') {
                $this->db->query(
                    "UPDATE matricula SET status = 'transferido', data_saida = :saida WHERE id = :id",
                    ['saida' => $saida, 'id' => (int) $m['id']]
                );
                $this->db->query(
                    "UPDATE alunos SET ativo = 0, status = 'INACTIVE' WHERE id = :id",
                    ['id' => $alunoId]
                );
                continue;
            }

            $this->db->query(
                "UPDATE matricula SET status = 'concluido', data_saida = :saida WHERE id = :id",
                ['saida' => $saida, 'id' => (int) $m['id']]
            );

            $proxima = $meta['proxima'];
            if (in_array($cenario, ['reprovado', 'frequencia'], true)) {
                $proxima = $serie;
                $this->cenarioPorAluno[$alunoId] = 'regular';
            }
            if ($proxima === null) {
                $this->db->query(
                    "UPDATE alunos SET ativo = 0, status = 'GRADUATED' WHERE id = :id",
                    ['id' => $alunoId]
                );
                continue;
            }
            $this->filaRematricula[] = [
                'aluno_id' => $alunoId,
                'serie' => $proxima,
                'letra' => $letra,
            ];
        }
        println('  encerramento ' . $ano . ': fila rematrícula=' . count($this->filaRematricula));
    }

    private function transferirAlunoAndamento(int $ano): void
    {
        $nick = 'ed246a07';
        $alunoId = $this->alunoIds[$nick] ?? 0;
        if ($alunoId <= 0) {
            $row = $this->db->fetch('SELECT id FROM alunos WHERE nickname = :n LIMIT 1', ['n' => $nick]);
            $alunoId = $row ? (int) $row['id'] : 0;
        }
        $turmaId = $this->turmaIds[$this->chaveTurma($ano, 'fund8', 'A')] ?? 0;
        if ($alunoId <= 0 || $turmaId <= 0) {
            $this->registrar('transferencia_2026', false, 'aluno/turma não encontrados', 'ALTO');
            return;
        }
        try {
            $this->movimentacao->transferenciaEscolarEmLote(
                $turmaId,
                [$alunoId],
                [
                    'password' => SENHA,
                    'observation' => 'Transferência escolar no 2º bimestre de 2026.',
                    'confirm' => '1',
                    'escola_nome' => 'Escola Municipal Destino Educa',
                    'escola_cidade' => 'São Paulo',
                    'escola_uf' => 'SP',
                    'motivo' => 'Mudança de município',
                    'data_transferencia' => $ano . '-06-15',
                ],
                ['id' => $this->adminId, 'tipo' => 'admin_escola', 'perfil_admin' => 'dev']
            );
            $this->registrar('transferencia_2026', true, $nick . ' transferido em jun/2026');
        } catch (Throwable $e) {
            $this->registrar('transferencia_2026', false, $e->getMessage(), 'ALTO');
        }
    }

    private function criarPlanosAmostra(int $ano): void
    {
        $tem = $this->db->fetch("SHOW TABLES LIKE 'planos_aula'");
        if ($tem === false) {
            return;
        }
        foreach (['fund6', 'em1'] as $serie) {
            $turmaId = $this->turmaIds[$this->chaveTurma($ano, $serie, 'A')] ?? 0;
            if ($turmaId <= 0) {
                continue;
            }
            foreach ($this->materiasDaSerie($serie) as $nomeMat) {
                $mid = (int) $this->materias[$nomeMat]['id'];
                $pid = $this->professorDaMateria($nomeMat, $serie, 'A');
                $titulo = sprintf('%s %s — %s A %d', PREFIXO, $nomeMat, $serie, $ano);
                $ja = $this->db->fetch(
                    'SELECT id FROM planos_aula WHERE titulo = :t AND deleted_at IS NULL LIMIT 1',
                    ['t' => $titulo]
                );
                if ($ja) {
                    continue;
                }
                $this->db->insert(
                    'INSERT INTO planos_aula (professor_id, materia_id, turma_id, data_aula, titulo, conteudo, objetivos, status)
                     VALUES (:p, :m, :t, :d, :titulo, :c, :o, :st)',
                    [
                        'p' => $pid,
                        'm' => $mid,
                        't' => $turmaId,
                        'd' => $ano . '-03-10',
                        'titulo' => $titulo,
                        'c' => 'Plano anual de ' . $nomeMat . ' alinhado à BNCC.',
                        'o' => 'Desenvolver competências da matriz ' . $serie,
                        'st' => 'aprovado',
                    ]
                );
            }
        }
    }

    private function criarResponsaveisAmostra(): void
    {
        $nicks = ['ed239a01', 'ed236a01', 'ed246a01', 'ed256a01', 'ed266a01'];
        $criados = 0;
        foreach ($nicks as $i => $nick) {
            $aluno = $this->db->fetch('SELECT id, nome FROM alunos WHERE nickname = :n LIMIT 1', ['n' => $nick]);
            if (!$aluno) {
                continue;
            }
            $email = 'pai.' . $nick . '@educa.local';
            $exist = $this->db->fetch('SELECT id FROM responsaveis WHERE email = :e LIMIT 1', ['e' => $email]);
            if ($exist) {
                $pid = (int) $exist['id'];
            } else {
                $pid = (int) $this->db->insert(
                    'INSERT INTO responsaveis (nome, email, senha_hash, cpf, telefone, ativo, password)
                     VALUES (:nome, :email, :senha_hash, :cpf, :telefone, 1, :password)',
                    [
                        'nome' => 'Responsável de ' . $aluno['nome'],
                        'email' => $email,
                        'senha_hash' => $this->hash,
                        'cpf' => sprintf('111222333%02d', $i + 1),
                        'telefone' => '1199000' . sprintf('%04d', $i + 1),
                        'password' => '',
                    ]
                );
            }
            $ja = $this->db->fetch(
                'SELECT id FROM alunos_responsaveis WHERE aluno_id = :a AND responsavel_id = :p LIMIT 1',
                ['a' => (int) $aluno['id'], 'p' => $pid]
            );
            if (!$ja) {
                $this->db->insert(
                    'INSERT INTO alunos_responsaveis (aluno_id, responsavel_id, tipo_vinculo, is_financeiro, ativo)
                     VALUES (:a, :p, :tv, 1, 1)',
                    ['a' => (int) $aluno['id'], 'p' => $pid, 'tv' => 'pai']
                );
            }
            $criados++;
        }
        $this->registrar('responsaveis', $criados > 0, $criados . ' responsáveis dos alunos de validação');
    }

    private function gerarDocumentosFinais(): void
    {
        foreach (['ed239a01', 'ed236a01', 'ed246a01', 'ed256a01'] as $nick) {
            $row = $this->db->fetch('SELECT id FROM alunos WHERE nickname = :n LIMIT 1', ['n' => $nick]);
            if (!$row) {
                continue;
            }
            $res = $this->historico->gerarRascunho((int) $row['id'], 'Solicitacao', $this->adminId, 'Histórico Educa — trajetória 2023–2026.');
            $this->registrar('historico_' . $nick, !empty($res['success']), $res['error'] ?? ('id=' . ($res['id'] ?? 0)), empty($res['success']) ? 'ALTO' : 'ALTO');
        }
        try {
            $doc = new DocumentoOficialService();
            $aluno = $this->db->fetch("SELECT id, turma_id FROM alunos WHERE nickname = 'ed239a01' LIMIT 1");
            if ($aluno) {
                $ficha = $doc->emitirFicha((int) $aluno['id'], (int) $aluno['turma_id'], 2026, 'ano', 0, $this->adminId);
                $this->registrar('ficha', isset($ficha['html']), 'ficha ed239a01 2026');
            }
        } catch (Throwable $e) {
            $this->registrar('documentos_oficiais', false, $e->getMessage(), 'ALTO');
        }
    }

    private function validarCenariosObrigatorios(): void
    {
        $casos = [
            'ed239a01' => [
                2023 => '9º Ano',
                2024 => '1ª Série EM',
                2025 => '2ª Série EM',
                2026 => '3ª Série EM',
            ],
            'ed236a01' => [
                2023 => '6º Ano',
                2024 => '7º Ano',
                2025 => '8º Ano',
                2026 => '9º Ano',
            ],
            'ed246a01' => [
                2024 => '6º Ano',
                2025 => '7º Ano',
                2026 => '8º Ano',
            ],
            'ed256a01' => [
                2025 => '6º Ano',
                2026 => '7º Ano',
            ],
            'ed266a01' => [
                2026 => '6º Ano',
            ],
        ];
        foreach ($casos as $nick => $esperado) {
            $aluno = $this->db->fetch('SELECT id FROM alunos WHERE nickname = :n LIMIT 1', ['n' => $nick]);
            if (!$aluno) {
                $this->registrar('cenario_' . $nick, false, 'aluno não encontrado', 'CRITICO');
                continue;
            }
            $rows = $this->db->fetchAll(
                "SELECT al.ano, s.nome AS serie_nome, m.status, c.nome AS curso_nome
                 FROM matricula m
                 INNER JOIN turmas t ON t.id = m.turma_id
                 INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                 LEFT JOIN serie s ON s.id = t.serie_id
                 LEFT JOIN curso c ON c.id = t.curso_novo_id
                 WHERE m.aluno_id = :id
                 ORDER BY al.ano",
                ['id' => (int) $aluno['id']]
            ) ?: [];
            $ok = true;
            $det = [];
            foreach ($esperado as $ano => $serieNome) {
                $hit = null;
                foreach ($rows as $r) {
                    if ((int) $r['ano'] === $ano) {
                        $hit = $r;
                        break;
                    }
                }
                if (!$hit || stripos((string) $hit['serie_nome'], explode(' ', $serieNome)[0]) === false) {
                    $ok = false;
                    $det[] = $ano . ' esperado=' . $serieNome . ' obtido=' . ($hit['serie_nome'] ?? 'ausente');
                } else {
                    $det[] = $ano . '=' . $hit['serie_nome'] . '/' . $hit['status'];
                }
            }
            if ($nick === 'ed239a01') {
                $curso23 = '';
                $curso24 = '';
                foreach ($rows as $r) {
                    if ((int) $r['ano'] === 2023) {
                        $curso23 = (string) ($r['curso_nome'] ?? '');
                    }
                    if ((int) $r['ano'] === 2024) {
                        $curso24 = (string) ($r['curso_nome'] ?? '');
                    }
                }
                if ($curso23 === '' || $curso24 === '' || $curso23 === $curso24) {
                    $ok = false;
                    $det[] = 'curso 2023/2024 deveria mudar Fund II → EM';
                }
                $atual = $this->db->fetch(
                    'SELECT u.nome FROM alunos a LEFT JOIN unidades u ON u.id = a.unidade_id WHERE a.id = :id',
                    ['id' => (int) $aluno['id']]
                );
                $unidadeAtual = (string) ($atual['nome'] ?? '');
                if (stripos($unidadeAtual, 'Médio') === false && stripos($unidadeAtual, 'Medio') === false) {
                    $ok = false;
                    $det[] = 'unidade atual deveria ser Ensino Médio, obtido=' . $unidadeAtual;
                }
            }
            if ($nick === 'ed246a01' || $nick === 'ed256a01' || $nick === 'ed266a01') {
                foreach ($rows as $r) {
                    $anoMin = (int) array_key_first($esperado);
                    if ((int) $r['ano'] < $anoMin) {
                        $ok = false;
                        $det[] = 'matrícula inesperada em ' . $r['ano'];
                    }
                }
            }
            $this->registrar('cenario_' . $nick, $ok, implode('; ', $det), $ok ? 'ALTO' : 'CRITICO');
        }
    }

    private function validarIntegridade(): void
    {
        $dupMat = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS c FROM (
                SELECT aluno_id, turma_id, ano_letivo_id, COUNT(*) n
                FROM matricula WHERE status = 'ativa'
                GROUP BY aluno_id, turma_id, ano_letivo_id HAVING n > 1
             ) x"
        )['c'] ?? 0);
        $this->registrar('integridade_matricula_dup', $dupMat === 0, 'duplicatas=' . $dupMat, $dupMat ? 'CRITICO' : 'ALTO');

        $contagens = [
            'alunos' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM alunos')['c'] ?? 0),
            'professores' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM professores')['c'] ?? 0),
            'usuarios' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM usuarios')['c'] ?? 0),
            'turmas' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM turmas')['c'] ?? 0),
            'matriculas' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM matricula')['c'] ?? 0),
            'aulas' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM diario_aulas')['c'] ?? 0),
            'frequencias' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM diario_frequencias')['c'] ?? 0),
            'eventos' => (int) ($this->db->fetch("SELECT COUNT(*) c FROM provas_blocos WHERE titulo LIKE 'EDUCA%' AND deleted_at IS NULL")['c'] ?? 0),
            'notas' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM provas_blocos_notas_lancadas')['c'] ?? 0),
            'boletins' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM boletim_resultados_gerados')['c'] ?? 0),
        ];
        println('  totais: ' . json_encode($contagens, JSON_UNESCAPED_UNICODE));
        $this->registrar(
            'volume',
            $contagens['alunos'] > 200 && $contagens['matriculas'] > 400 && $contagens['aulas'] > 0,
            json_encode($contagens, JSON_UNESCAPED_UNICODE)
        );
    }

    private function imprimirRelatorio(): void
    {
        $pass = count(array_filter($this->achados, static fn($a) => $a['ok']));
        $fail = count($this->achados) - $pass;
        println('');
        println('======== COLÉGIO EDUCA ========');
        println('Passos: ' . count($this->achados) . " | PASS={$pass} | FAIL={$fail}");
        foreach ($this->achados as $a) {
            if ($a['ok']) {
                continue;
            }
            println('  FAIL [' . $a['severidade'] . '] ' . $a['etapa'] . ' — ' . $a['detalhe']);
        }
        println('');
        println('URL: http://educa.localhost');
        println('Senha padrão: ' . SENHA);
        println('Admin:              admin@educa.local');
        println('Diretor:            diretor@educa.local');
        println('Coord. Fund. II:    coordenador.fund@educa.local');
        println('Coord. EM:          coordenador.em@educa.local');
        println('Secretaria:         secretaria@educa.local');
        println('Prof. 6º A Mat.:    mat@educa.local');
        println('Prof. 7º A Mat.:    mat.7a@educa.local  (padrão: {área}.{série}{letra}@educa.local)');
        println('Aluno 9º→3ª EM:     ed239a01');
        println('Aluno 6º→9º:        ed236a01');
        println('Entrada 2024:       ed246a01');
        println('Entrada 2025:       ed256a01');
        println('Entrada 2026:       ed266a01');
        println('Pais (validação):   pai.ed239a01@educa.local');
    }

    private function popularGestaoEscolar(): void
    {
        $script = __DIR__ . '/popular_gestao_escolar_educa.php';
        if (!is_file($script)) {
            println('AVISO: script de gestão escolar não encontrado.');
            return;
        }
        println('');
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), $code);
        if ($code !== 0) {
            println('AVISO: gestão escolar retornou código ' . (int) $code);
        }
    }

    private function popularCadastroCenso(): void
    {
        $script = __DIR__ . '/popular_cadastro_censo_educa.php';
        if (!is_file($script)) {
            println('AVISO: script de cadastro/censo não encontrado.');
            return;
        }
        println('');
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), $code);
        if ($code !== 0) {
            println('AVISO: cadastro/censo retornou código ' . (int) $code);
        }
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

    $somenteFinal = in_array('--somente-final', $argv ?? [], true);
    $runner = new PopularColegioEduca(Database::getInstance());
    exit($runner->executar($somenteFinal));
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
