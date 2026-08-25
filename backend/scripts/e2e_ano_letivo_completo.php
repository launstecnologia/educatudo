<?php
/**
 * E2E Colag — ciclo acadêmico 2024 → 2025 → 2026 (Ensino Médio).
 *
 * Uso (container PHP):
 *   php scripts/init_local_multitenant.php --force-school
 *   php scripts/seed_local_usuarios_teste.php
 *   php scripts/e2e_ano_letivo_completo.php
 *   php scripts/e2e_ano_letivo_completo.php --somente-fechamento
 *
 * Não usa jornada como fonte de nota. Eventos = provas_blocos (lancamento_nota).
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Core/BaseController.php';
require_once $basePath . '/app/Models/User/Teacher.php';
require_once $basePath . '/app/Models/User/Student.php';
require_once $basePath . '/app/Models/Education/ClassRoom.php';
require_once $basePath . '/app/Models/Education/ClassDiary.php';
require_once $basePath . '/app/Models/Education/ComponenteCurricular.php';
require_once $basePath . '/app/Models/Education/MatrizCurricular.php';
require_once $basePath . '/app/Models/Education/Sala.php';
require_once $basePath . '/app/Models/Exams/ExamBlock.php';
require_once $basePath . '/app/Models/Exams/ExamBlockManualGrade.php';
require_once $basePath . '/app/Models/Exams/ExamEvaluationType.php';
require_once $basePath . '/app/Models/System/BoletimConfig.php';
require_once $basePath . '/app/Services/SalaService.php';
require_once $basePath . '/app/Services/MatrizCurricularService.php';
require_once $basePath . '/app/Services/AlunoMovimentacaoService.php';
require_once $basePath . '/app/Services/ClassDiaryService.php';
require_once $basePath . '/app/Services/OcorrenciaService.php';
require_once $basePath . '/app/Services/ConselhoService.php';
require_once $basePath . '/app/Services/RegraAcademicaService.php';
require_once $basePath . '/app/Services/ResultadoHomologacaoService.php';
require_once $basePath . '/app/Services/HistoricoEscolarService.php';
require_once $basePath . '/app/Services/DocumentoOficialService.php';
require_once $basePath . '/app/Services/FrequencyService.php';
require_once $basePath . '/app/Controllers/Admin/BoletimConfigController.php';

const TENANT_DB = 'educatudo_colag';
const SENHA = 'Teste@123';
const PREFIXO = 'E2E';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

final class E2eAnoLetivoCompleto
{
    private $db;
    private Teacher $teachers;
    private Student $students;
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

    /** @var list<array{etapa:string,severidade:string,ok:bool,detalhe:string}> */
    private array $achados = [];

    private int $adminId = 0;
    private int $coordenadorId = 0;
    private int $secretariaId = 0;

    /** @var array<int, array{id:int,ano:int,inicio:string,fim:string}> */
    private array $anos = [];
    private int $cursoId = 0;
    /** @var array<int, int> serie ordem => id */
    private array $series = [];
    /** @var array<string, array<string,mixed>> nome => materia */
    private array $materias = [];
    /** @var array<int, int> serieOrdem => matrizId */
    private array $matrizPorSerie = [];
    private int $salaId = 0;

    /** @var array<string, int> chave => professor_id */
    private array $profs = [];
    /** @var array<string, list<string>> chave => nomes de materias */
    private array $profMaterias = [];

    /** @var array<string, int> "2024:1" => turma_id */
    private array $turmaIds = [];
    /** @var array<string, int> nickname => aluno_id */
    private array $alunoIds = [];
    /** @var array<int, string> aluno_id => cenario */
    private array $cenarioPorAluno = [];

    private int $tipoProvaId = 0;
    private int $tipoAtivId = 0;
    private int $tipoRecId = 0;
    private int $categoriaPedagogicaId = 0;

    /** @var array<int, list<array<string,mixed>>> turma_id => linhas da grade */
    private array $grades = [];

    public function __construct($db)
    {
        $this->db = $db;
        $this->teachers = new Teacher();
        $this->students = new Student();
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
    }

    public function executar(bool $somenteFechamento = false): int
    {
        println('== E2E Colag — ciclo 2024 / 2025 / 2026 ==');
        $this->criarUsuariosAdmin();
        $this->criarAnosLetivos();
        $this->criarCursoSeries();
        $this->carregarComponentes();
        $this->criarMatrizes();
        $this->criarSala();
        $this->criarTiposAvaliacao();
        $this->criarProfessores();
        $this->criarRegraAcademica();
        $this->configurarFechamento();
        $this->garantirCategoriaOcorrencia();

        if ($somenteFechamento) {
            println('Modo: somente fechamento (boletim + conselho + homologação)');
            $this->executarSomenteFechamento();
        } else {
            foreach ([2024, 2025, 2026] as $ano) {
                println('');
                println("---- ANO LETIVO {$ano} ----");
                $this->executarAno($ano);
            }
        }

        $this->validarIntegridade();
        $this->imprimirRelatorio();
        $falhas = array_filter($this->achados, static fn($a) => !$a['ok'] && in_array($a['severidade'], ['BLOQUEADOR', 'CRITICO'], true));
        return $falhas === [] ? 0 : 2;
    }

    /** @return list<int> */
    private function seriesDoAno(int $ano): array
    {
        return $ano === 2024 ? [1] : ($ano === 2025 ? [1, 2] : [1, 2, 3]);
    }

    private function executarSomenteFechamento(): void
    {
        foreach ([2024, 2025, 2026] as $ano) {
            foreach ($this->seriesDoAno($ano) as $serieOrdem) {
                $this->criarTurma($ano, $serieOrdem);
            }
        }
        $this->hidratarAlunos();
        foreach ([2024, 2025, 2026] as $ano) {
            println('');
            println("---- FECHAMENTO {$ano} ----");
            foreach ($this->seriesDoAno($ano) as $serieOrdem) {
                $turmaId = $this->turmaIds[$ano . ':' . $serieOrdem];
                for ($bim = 1; $bim <= 4; $bim++) {
                    $eventos = $this->criarEventosBimestre($ano, $serieOrdem, $turmaId, $bim);
                    if ($bim === 2) {
                        $tituloRec = sprintf('%s Recuperação — %sº A %d B%d', PREFIXO, $serieOrdem, $ano, $bim);
                        $existRec = $this->db->fetch(
                            'SELECT id FROM provas_blocos WHERE titulo = :t AND deleted_at IS NULL LIMIT 1',
                            ['t' => $tituloRec]
                        );
                        if ($existRec) {
                            $eventos['rec'] = (int) $existRec['id'];
                        }
                    }
                    $this->gerarBoletim($ano, $turmaId, $bim, $eventos);
                }
                if ($ano === 2026 && $serieOrdem === 3) {
                    $this->executarConselho($ano, $turmaId);
                }
                $this->reabrirHomologacoes($ano, $turmaId);
                $this->homologarTurma($ano, $turmaId);
            }
            $this->gerarHistoricos($ano);
        }
    }

    private function hidratarAlunos(): void
    {
        $rows = $this->db->fetchAll("SELECT id, nickname FROM alunos WHERE nickname LIKE 'e2e.%'") ?: [];
        foreach ($rows as $row) {
            $nick = (string) ($row['nickname'] ?? '');
            if ($nick === '') {
                continue;
            }
            $this->alunoIds[$nick] = (int) $row['id'];
        }
        foreach ($this->cenariosCohorteA() as $nick => $cenario) {
            if (isset($this->alunoIds[$nick])) {
                $this->cenarioPorAluno[$this->alunoIds[$nick]] = $cenario;
            }
        }
        $this->registrar('hidratar_alunos', $this->alunoIds !== [], count($this->alunoIds) . ' alunos E2E');
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
        $marca = $ok ? 'PASS' : 'FAIL';
        println("  [{$marca}] {$etapa}: {$detalhe}");
    }

    private function criarUsuariosAdmin(): void
    {
        $admin = $this->db->fetch("SELECT id FROM usuarios WHERE email = 'admin@colag.local' LIMIT 1");
        if (!$admin) {
            fail('Admin Colag não encontrado. Rode seed_local_usuarios_teste.php antes.');
        }
        $this->adminId = (int) $admin['id'];

        $this->coordenadorId = $this->upsertUsuario(
            'Coordenação E2E',
            'coordenador@colag.local',
            'coordenador'
        );
        $this->secretariaId = $this->upsertUsuario(
            'Secretaria E2E',
            'secretaria@colag.local',
            'secretaria'
        );
        $this->registrar('usuarios', true, 'admin=' . $this->adminId . ' coordenador=' . $this->coordenadorId . ' secretaria=' . $this->secretariaId);
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
            [
                'perfil' => $perfil,
                'nome' => $nome,
                'email' => $email,
                'hash' => password_hash(SENHA, PASSWORD_DEFAULT),
            ]
        );
    }

    private function criarAnosLetivos(): void
    {
        foreach ([2024, 2025, 2026] as $ano) {
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
        $this->registrar('ano_letivo', true, '2024, 2025 e 2026 criados (ativo=2026)');
    }

    private function criarCursoSeries(): void
    {
        $curso = $this->db->fetch("SELECT id FROM curso WHERE nome = 'Ensino Médio' LIMIT 1");
        if ($curso) {
            $this->cursoId = (int) $curso['id'];
        } else {
            $cols = $this->db->fetch("SHOW COLUMNS FROM curso LIKE 'tipo'");
            if ($cols) {
                $this->cursoId = (int) $this->db->insert(
                    "INSERT INTO curso (nome, descricao, ativo, ordem, tipo, possui_serie)
                     VALUES ('Ensino Médio', 'Ensino Médio Regular E2E', 1, 1, 'regular', 1)"
                );
            } else {
                $this->cursoId = (int) $this->db->insert(
                    "INSERT INTO curso (nome, descricao, ativo, ordem)
                     VALUES ('Ensino Médio', 'Ensino Médio Regular E2E', 1, 1)"
                );
            }
        }
        $nomes = [1 => '1º Ano EM', 2 => '2º Ano EM', 3 => '3º Ano EM'];
        foreach ($nomes as $ordem => $nome) {
            $row = $this->db->fetch(
                'SELECT id FROM serie WHERE curso_id = :c AND nome = :n LIMIT 1',
                ['c' => $this->cursoId, 'n' => $nome]
            );
            if ($row) {
                $this->series[$ordem] = (int) $row['id'];
                continue;
            }
            $this->series[$ordem] = (int) $this->db->insert(
                'INSERT INTO serie (curso_id, nome, ordem, ativo) VALUES (:c, :n, :o, 1)',
                ['c' => $this->cursoId, 'n' => $nome, 'o' => $ordem]
            );
        }
        $this->registrar('curso_serie', true, 'curso=' . $this->cursoId . ' series=' . implode(',', $this->series));
    }

    private function carregarComponentes(): void
    {
        $obrigatorios = [
            'Língua Portuguesa', 'Matemática', 'História', 'Geografia', 'Arte',
            'Educação Física', 'Língua Inglesa', 'Biologia', 'Física', 'Química',
            'Filosofia', 'Sociologia',
        ];
        $rows = $this->db->fetchAll('SELECT * FROM materias WHERE ativo = 1') ?: [];
        foreach ($rows as $row) {
            $this->materias[(string) $row['nome']] = $row;
            if (!empty($row['codigo'])) {
                $this->materias[(string) $row['codigo']] = $row;
            }
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
            $faltando === []
                ? count($obrigatorios) . ' componentes EM carregados do catálogo'
                : 'Faltando: ' . implode(', ', $faltando),
            $faltando === [] ? 'ALTO' : 'BLOQUEADOR'
        );
        if ($faltando !== []) {
            fail('Catálogo de componentes incompleto. Rode as migrations tenant.');
        }
    }

    private function criarMatrizes(): void
    {
        $carga = [
            'Língua Portuguesa' => 4,
            'Matemática' => 4,
            'Língua Inglesa' => 2,
            'História' => 2,
            'Geografia' => 2,
            'Física' => 2,
            'Química' => 2,
            'Biologia' => 2,
            'Filosofia' => 1,
            'Sociologia' => 1,
            'Arte' => 1,
            'Educação Física' => 2,
        ];
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
        foreach ([1, 2, 3] as $serieOrdem) {
            $nome = 'Matriz EM ' . $serieOrdem . 'º — E2E';
            $codigo = 'EM' . $serieOrdem . 'E2E';
            $exist = $this->db->fetch('SELECT id FROM matrizes_curriculares WHERE codigo = :c LIMIT 1', ['c' => $codigo]);
            if ($exist) {
                $this->matrizPorSerie[$serieOrdem] = (int) $exist['id'];
                continue;
            }
            $res = $this->matrizes->criar([
                'nome' => $nome,
                'codigo' => $codigo,
                'curso_id' => $this->cursoId,
                'serie_id' => $this->series[$serieOrdem],
                'modalidade' => 'presencial',
                'turno' => 'manha',
                'carga_horaria_anual_prevista' => '1000',
                'dias_letivos_previstos' => '200',
                'duracao_padrao_aula_minutos' => 50,
                'base_legal' => 'BNCC / LDB 9.394/96',
                'observacoes' => 'Matriz E2E Ensino Médio',
                'ativo' => 1,
                'componentes' => $componentes,
            ]);
            if (empty($res['success'])) {
                fail('Matriz ' . $nome . ': ' . ($res['error'] ?? 'erro'));
            }
            $this->matrizPorSerie[$serieOrdem] = (int) $res['id'];
        }
        $this->registrar('matriz', true, '3 matrizes EM com 12 componentes');
    }

    private function criarSala(): void
    {
        $res = $this->salas->criar([
            'codigo' => 'E2E-SALA-01',
            'nome' => 'Sala E2E 01',
            'tipo' => 'sala',
            'capacidade' => '40',
            'bloco' => 'A',
            'andar' => '1',
            'responsavel_nome' => 'Coordenação E2E',
            'ativo' => 1,
        ]);
        if (empty($res['success'])) {
            $exist = $this->db->fetch("SELECT id FROM school_locations WHERE codigo = 'E2E-SALA-01' LIMIT 1")
                ?: $this->db->fetch("SELECT id FROM school_locations WHERE nome = 'Sala E2E 01' LIMIT 1");
            if (!$exist) {
                fail('Sala: ' . ($res['error'] ?? 'erro'));
            }
            $this->salaId = (int) $exist['id'];
        } else {
            $this->salaId = (int) $res['id'];
        }
        $this->registrar('sala', true, 'sala_id=' . $this->salaId);
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
            'hum' => ['História', 'Geografia', 'Filosofia', 'Sociologia'],
            'nat' => ['Biologia', 'Química'],
            'edf' => ['Educação Física'],
        ];
        $nomes = [
            'mat' => 'Professor Matemática E2E',
            'ling' => 'Professor Linguagens E2E',
            'hum' => 'Professor Humanas E2E',
            'nat' => 'Professor Natureza E2E',
            'edf' => 'Professor Educação Física E2E',
        ];
        foreach ($nomes as $chave => $nome) {
            $email = $chave . '.e2e@colag.local';
            $exist = $this->db->fetch('SELECT id FROM professores WHERE email = :e LIMIT 1', ['e' => $email]);
            if ($exist) {
                $this->profs[$chave] = (int) $exist['id'];
                continue;
            }
            $this->profs[$chave] = (int) $this->teachers->create([
                'nome' => $nome,
                'email' => $email,
                'senha' => SENHA,
                'codigo_prof' => 'E2E-' . strtoupper($chave),
                'materias' => $this->profMaterias[$chave],
                'turmas' => [],
                'ativo' => 1,
                'pagante' => 1,
            ]);
        }
        $this->registrar('professores', true, implode(',', array_map('strval', $this->profs)));
    }

    private function criarRegraAcademica(): void
    {
        $res = $this->regras->criar([
            'nome' => 'Regra EM E2E',
            'codigo' => 'em-e2e',
            'ano_letivo' => null,
            'curso_id' => $this->cursoId,
            'media_minima' => 6.0,
            'frequencia_minima' => 75.0,
            'usar_frequencia' => 1,
            'periodo_tipo' => 'ano',
            'recuperacao_tipo' => 'periodo',
            'recuperacao_composicao' => 'maior_nota',
            'round_mode' => 'none',
            'decimal_places' => 2,
            'ativo' => 1,
        ], $this->adminId, 'Admin Colag');
        if (empty($res['success'])) {
            $exist = $this->db->fetch("SELECT id FROM regras_academicas WHERE codigo = 'em-e2e' LIMIT 1");
            if ($exist) {
                $this->regras->atualizar((int) $exist['id'], [
                    'nome' => 'Regra EM E2E',
                    'codigo' => 'em-e2e',
                    'curso_id' => $this->cursoId,
                    'media_minima' => 6.0,
                    'frequencia_minima' => 75.0,
                    'usar_frequencia' => 1,
                    'periodo_tipo' => 'bimestre',
                    'recuperacao_tipo' => 'periodo',
                    'recuperacao_composicao' => 'maior_nota',
                    'round_mode' => 'none',
                    'decimal_places' => 2,
                    'ativo' => 1,
                ], $this->adminId, 'Admin Colag');
            }
            $this->registrar(
                'regra_academica',
                (bool) $exist,
                $exist ? 'id=' . (int) $exist['id'] . ' reativada' : ($res['error'] ?? 'falha'),
                $exist ? 'ALTO' : 'CRITICO'
            );
            return;
        }
        $this->registrar('regra_academica', true, 'id=' . ($res['id'] ?? 0));
    }

    private function configurarFechamento(): void
    {
        $this->homologacao->model()->salvarConfigFechamento([
            'exigir_conselho' => 0,
            'exigir_frequencia' => 1,
            'exigir_notas' => 1,
        ], $this->adminId);
        $this->registrar('fechamento_config', true, 'exigir_notas+frequencia, conselho opcional');
    }

    private function garantirCategoriaOcorrencia(): void
    {
        $row = $this->db->fetch("SHOW TABLES LIKE 'ocorrencias_categorias'");
        if ($row === false) {
            $this->registrar('ocorrencias_categoria', false, 'tabela ocorrencias_categorias ausente', 'MEDIO');
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

    private function executarAno(int $ano): void
    {
        $seriesNoAno = $this->seriesDoAno($ano);
        foreach ($seriesNoAno as $serieOrdem) {
            $this->criarTurma($ano, $serieOrdem);
        }
        $this->atualizarTurmasDosProfessores();
        foreach ($seriesNoAno as $serieOrdem) {
            $this->criarGrade($ano, $serieOrdem);
        }
        $this->matricularCoortes($ano);
        foreach ($seriesNoAno as $serieOrdem) {
            $chave = $ano . ':' . $serieOrdem;
            $turmaId = $this->turmaIds[$chave];
            for ($bim = 1; $bim <= 4; $bim++) {
                if ($ano === 2026 && $serieOrdem === 3 && $bim === 3) {
                    $this->transferirAluno06($turmaId);
                    $this->registrarOcorrencias($ano, $turmaId);
                }
                $this->executarBimestre($ano, $serieOrdem, $turmaId, $bim);
            }
            $this->fecharDiarios($ano, $turmaId);
            if ($ano === 2026 && $serieOrdem === 3) {
                $this->executarConselho($ano, $turmaId);
            }
            $this->homologarTurma($ano, $turmaId);
        }
        if ($ano < 2026) {
            $this->virarAno($ano);
        }
        $this->gerarHistoricos($ano);
    }

    private function criarTurma(int $ano, int $serieOrdem): void
    {
        $nome = $serieOrdem . 'º A ' . $ano;
        $exist = $this->db->fetch('SELECT id FROM turmas WHERE nome = :n LIMIT 1', ['n' => $nome]);
        if ($exist) {
            $this->turmaIds[$ano . ':' . $serieOrdem] = (int) $exist['id'];
            return;
        }
        $id = (int) $this->turmas->create([
            'nome' => $nome,
            'ano_letivo' => $ano,
            'ano_letivo_id' => $this->anos[$ano]['id'],
            'serie' => $serieOrdem . 'º Ano EM',
            'ativo' => 1,
            'curso_novo_id' => $this->cursoId,
            'serie_id' => $this->series[$serieOrdem],
            'matriz_curricular_id' => $this->matrizPorSerie[$serieOrdem],
            'turno' => 'manha',
            'sala_padrao_id' => $this->salaId,
            'vagas' => 40,
            'observacoes' => PREFIXO . ' ' . $nome,
        ]);
        $this->turmaIds[$ano . ':' . $serieOrdem] = $id;
        println("  turma {$nome} id={$id}");
    }

    private function atualizarTurmasDosProfessores(): void
    {
        $ids = array_values($this->turmaIds);
        foreach ($this->profs as $chave => $pid) {
            $this->teachers->update($pid, [
                'nome' => $this->db->fetch('SELECT nome FROM professores WHERE id = :id', ['id' => $pid])['nome'],
                'email' => $chave . '.e2e@colag.local',
                'codigo_prof' => 'E2E-' . strtoupper($chave),
                'materias' => $this->profMaterias[$chave],
                'turmas' => $ids,
                'ativo' => 1,
                'pagante' => 1,
            ]);
        }
    }

    private function gradeMapa(): array
    {
        $slots = [
            ['07:30:00', '08:20:00'],
            ['08:20:00', '09:10:00'],
            ['09:30:00', '10:20:00'],
            ['10:20:00', '11:10:00'],
            ['11:10:00', '12:00:00'],
        ];
        $grade = [
            1 => ['Língua Portuguesa', 'Língua Portuguesa', 'Matemática', 'Matemática', 'História'],
            2 => ['Língua Portuguesa', 'Matemática', 'Física', 'Química', 'Geografia'],
            3 => ['Língua Portuguesa', 'Matemática', 'Biologia', 'Língua Inglesa', 'Filosofia'],
            4 => ['Língua Inglesa', 'História', 'Geografia', 'Física', 'Sociologia'],
            5 => ['Biologia', 'Química', 'Arte', 'Educação Física', 'Educação Física'],
        ];
        return [$slots, $grade];
    }

    private function professorDaMateria(string $nome): int
    {
        foreach ($this->profMaterias as $chave => $lista) {
            if (in_array($nome, $lista, true)) {
                return $this->profs[$chave];
            }
        }
        throw new RuntimeException('Sem professor para ' . $nome);
    }

    private function criarGrade(int $ano, int $serieOrdem): void
    {
        $turmaId = $this->turmaIds[$ano . ':' . $serieOrdem];
        $ja = $this->db->fetch('SELECT COUNT(*) AS c FROM grade_horaria WHERE turma_id = :t', ['t' => $turmaId]);
        if ((int) ($ja['c'] ?? 0) > 0) {
            $this->grades[$turmaId] = $this->db->fetchAll('SELECT * FROM grade_horaria WHERE turma_id = :t', ['t' => $turmaId]) ?: [];
            return;
        }
        [$slots, $mapa] = $this->gradeMapa();
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
                        'prof' => $this->professorDaMateria($nome),
                        'mat' => (int) $this->materias[$nome]['id'],
                    ]
                );
            }
        }
        $this->grades[$turmaId] = $this->db->fetchAll('SELECT * FROM grade_horaria WHERE turma_id = :t', ['t' => $turmaId]) ?: [];
        println('  grade ' . count($this->grades[$turmaId]) . ' aulas/semana turma=' . $turmaId);
    }

    private function matricularCoortes(int $ano): void
    {
        if ($ano === 2024) {
            $this->criarCoorte('A', 2024, 1, $this->cenariosCohorteA());
        } elseif ($ano === 2025) {
            $this->criarCoorte('B', 2025, 1, $this->cenariosControle(8));
            $this->rematricularCoorte('A', 2025, 2);
        } else {
            $this->criarCoorte('C', 2026, 1, $this->cenariosControle(8));
            $this->rematricularCoorte('B', 2026, 2);
            $this->rematricularCoorte('A', 2026, 3);
        }
    }

    /** @return array<string, string> nick => cenario */
    private function cenariosCohorteA(): array
    {
        return [
            'e2e.a01' => 'regular',
            'e2e.a02' => 'recuperacao',
            'e2e.a03' => 'frequencia',
            'e2e.a04' => 'conselho',
            'e2e.a05' => 'baixo',
            'e2e.a06' => 'transferencia',
            'e2e.a07' => 'controle',
            'e2e.a08' => 'justificado',
            'e2e.a09' => 'ocorrencia',
            'e2e.a10' => 'extra',
        ];
    }

    private function cenariosControle(int $qtd): array
    {
        $out = [];
        for ($i = 1; $i <= $qtd; $i++) {
            $out[sprintf('e2e.x%02d', $i)] = $i === 1 ? 'regular' : 'controle';
        }
        return $out;
    }

    private function criarCoorte(string $letra, int $ano, int $serieOrdem, array $cenarios): void
    {
        $turmaId = $this->turmaIds[$ano . ':' . $serieOrdem];
        $anoId = $this->anos[$ano]['id'];
        $i = 0;
        foreach ($cenarios as $nick => $cenario) {
            $i++;
            if ($letra !== 'A' && str_starts_with($nick, 'e2e.x')) {
                $nick = sprintf('e2e.%s%02d', strtolower($letra), $i);
            }
            $nome = sprintf('Aluno E2E %s%02d', $letra, $i);
            $exist = $this->db->fetch('SELECT id FROM alunos WHERE nickname = :n LIMIT 1', ['n' => $nick]);
            if ($exist) {
                $alunoId = (int) $exist['id'];
            } else {
                $alunoId = (int) $this->db->insert(
                    "INSERT INTO alunos (nome, nickname, email, senha_hash, ra, turma_id, serie, data_nasc, ativo, pagante, status, password, primeiro_acesso)
                     VALUES (:nome, :nickname, :email, :senha_hash, :ra, :turma_id, :serie, :data_nasc, 1, 1, 'ACTIVE', '', 0)",
                    [
                        'nome' => $nome,
                        'nickname' => $nick,
                        'email' => $nick . '@colag.local',
                        'senha_hash' => password_hash(SENHA, PASSWORD_DEFAULT),
                        'ra' => strtoupper(str_replace('.', '', $nick)),
                        'turma_id' => $turmaId,
                        'serie' => $serieOrdem . 'º Ano EM',
                        'data_nasc' => sprintf('20%02d-03-%02d', 10 - $serieOrdem, min(28, $i)),
                    ]
                );
            }
            $this->alunoIds[$nick] = $alunoId;
            $this->cenarioPorAluno[$alunoId] = $cenario;
            try {
                $this->movimentacao->vincularAlunoTurma($alunoId, $turmaId, $anoId, true, $ano . '-02-01');
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'já possui matrícula') === false) {
                    $this->registrar('matricula', false, $nick . ': ' . $e->getMessage(), 'CRITICO');
                }
            }
        }
        println('  coorte ' . $letra . ' matriculada na turma ' . $turmaId . ' (' . count($cenarios) . ' alunos)');
    }

    private function rematricularCoorte(string $letra, int $ano, int $serieOrdem): void
    {
        $turmaId = $this->turmaIds[$ano . ':' . $serieOrdem];
        $anoId = $this->anos[$ano]['id'];
        foreach ($this->alunoIds as $nick => $alunoId) {
            if (!str_starts_with($nick, 'e2e.' . strtolower($letra))) {
                continue;
            }
            $this->db->query(
                "UPDATE alunos SET turma_id = :t, serie = :s WHERE id = :id",
                ['t' => $turmaId, 's' => $serieOrdem . 'º Ano EM', 'id' => $alunoId]
            );
            try {
                $this->movimentacao->vincularAlunoTurma($alunoId, $turmaId, $anoId, true, $ano . '-02-01');
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'já possui matrícula') === false) {
                    $this->registrar('rematricula', false, $nick . ': ' . $e->getMessage(), 'CRITICO');
                }
            }
        }
        println('  rematrícula coorte ' . $letra . ' → ' . $serieOrdem . 'º A ' . $ano);
    }

    private function virarAno(int $anoConcluido): void
    {
        $anoId = $this->anos[$anoConcluido]['id'];
        $this->db->query(
            "UPDATE matricula SET status = 'concluido', data_saida = :saida
             WHERE ano_letivo_id = :ano AND status = 'ativa'",
            ['saida' => $anoConcluido . '-12-15', 'ano' => $anoId]
        );
        $this->registrar('virada_' . $anoConcluido, true, 'matrículas do ano encerradas como concluido');
    }

    private function periodoBimestre(int $ano, int $bim): array
    {
        return $this->diario->periodoDoBimestre($ano, $bim);
    }

    private function executarBimestre(int $ano, int $serieOrdem, int $turmaId, int $bim): void
    {
        println("  · {$ano} {$serieOrdem}º A bimestre {$bim}");
        $this->lancarDiario($ano, $turmaId, $bim);
        $eventos = $this->criarEventosBimestre($ano, $serieOrdem, $turmaId, $bim);
        $this->lancarNotasEventos($turmaId, $bim, $eventos);
        if ($bim === 2) {
            $this->criarRecuperacao($ano, $serieOrdem, $turmaId, $bim, $eventos);
        }
        $this->gerarBoletim($ano, $turmaId, $bim, $eventos);
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

    private function lancarDiario(int $ano, int $turmaId, int $bim): void
    {
        $periodo = $this->periodoBimestre($ano, $bim);
        $inicio = max($periodo['inicio'], $ano . '-02-01');
        $fim = min($periodo['fim'], $ano . '-12-15');
        $alunos = $this->alunosAtivosNaTurma($turmaId, $this->anos[$ano]['id']);
        if ($alunos === []) {
            $this->registrar('diario', false, "sem alunos ativos turma={$turmaId} {$ano} B{$bim}", 'CRITICO');
            return;
        }
        $grades = $this->grades[$turmaId] ?? [];
        $porDia = [];
        foreach ($grades as $g) {
            $porDia[(int) $g['dia_semana']][] = $g;
        }
        $aulas = 0;
        $start = new DateTime($inicio);
        $end = new DateTime($fim);
        for ($d = clone $start; $d <= $end; $d->modify('+1 day')) {
            $n = (int) $d->format('N');
            if ($n > 5 || !isset($porDia[$n])) {
                continue;
            }
            $semanaDoBim = (int) floor($start->diff($d)->days / 7);
            if ($semanaDoBim % 2 === 1) {
                continue;
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
                        'situacao' => $this->situacaoFrequencia($aid, $bim, $semanaDoBim, $data),
                        'observacao' => '',
                    ];
                }
                $this->diario->salvar(
                    $aulaId,
                    'conforme_planejado',
                    'Conteúdo E2E ' . ($grade['materia_id'] ?? '') . ' — ' . $data,
                    '',
                    $freq,
                    true
                );
                $aulas++;
            }
        }
        println("    diário: {$aulas} aulas finalizadas");
    }

    private function situacaoFrequencia(int $alunoId, int $bim, int $semana, string $data): string
    {
        $cenario = $this->cenarioPorAluno[$alunoId] ?? 'controle';
        $dia = (int) date('N', strtotime($data));
        if ($cenario === 'frequencia') {
            return $dia >= 4 ? 'falta' : 'presente';
        }
        if ($cenario === 'justificado') {
            return ($bim >= 2 && $dia === 5 && $semana === 0) ? 'falta_justificada' : 'presente';
        }
        if ($cenario === 'regular') {
            return ($bim === 1 && $dia === 1 && $semana === 0) ? 'atraso' : 'presente';
        }
        return 'presente';
    }

    private function criarEventosBimestre(int $ano, int $serieOrdem, int $turmaId, int $bim): array
    {
        $periodo = $this->periodoBimestre($ano, $bim);
        $datas = [
            1 => [substr($periodo['inicio'], 0, 8) . '10', substr($periodo['inicio'], 0, 8) . '20', substr($periodo['fim'], 0, 8) . '05'],
            2 => [substr($periodo['inicio'], 0, 8) . '10', substr($periodo['inicio'], 0, 8) . '20', substr($periodo['fim'], 0, 8) . '05'],
        ];
        $profsEvento = [];
        foreach ($this->profMaterias as $chave => $nomes) {
            foreach ($nomes as $nomeMat) {
                $profsEvento[] = [
                    'professor_id' => $this->profs[$chave],
                    'materia_id' => (int) $this->materias[$nomeMat]['id'],
                    'quantidade_questoes' => 1,
                    'turmas' => [$turmaId],
                ];
            }
        }
        $defs = [
            'p1' => ['Prova Bimestral 1', $this->tipoProvaId, $periodo['inicio']],
            'p2' => ['Prova Bimestral 2', $this->tipoProvaId, date('Y-m-d', strtotime($periodo['inicio'] . ' +35 days'))],
            'atv' => ['Atividade em Aula', $this->tipoAtivId, date('Y-m-d', strtotime($periodo['inicio'] . ' +20 days'))],
        ];
        $ids = [];
        foreach ($defs as $k => $def) {
            $titulo = sprintf('%s %s — %sº A %d B%d', PREFIXO, $def[0], $serieOrdem, $ano, $bim);
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
                'descricao' => 'Evento E2E de lançamento de nota (sem jornada)',
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

    private function lancarNotasEventos(int $turmaId, int $bim, array $eventos): void
    {
        $alunos = $this->db->fetchAll(
            'SELECT id FROM alunos WHERE turma_id = :t AND ativo = 1',
            ['t' => $turmaId]
        ) ?: [];
        foreach ($eventos as $tipoEv => $blocoId) {
            foreach ($this->profMaterias as $chave => $nomes) {
                foreach ($nomes as $nomeMat) {
                    $mid = (int) $this->materias[$nomeMat]['id'];
                    $pid = $this->profs[$chave];
                    $linhas = [];
                    foreach ($alunos as $al) {
                        $aid = (int) $al['id'];
                        $linhas[] = [
                            'turma_id' => $turmaId,
                            'aluno_id' => $aid,
                            'nota' => $this->notaDoAluno($aid, $bim, $tipoEv),
                        ];
                    }
                    $this->notas->upsertLinhas($blocoId, $pid, $mid, $linhas);
                }
            }
        }
    }

    private function notaDoAluno(int $alunoId, int $bim, string $tipoEv): float
    {
        $cenario = $this->cenarioPorAluno[$alunoId] ?? 'controle';
        $base = [
            'regular' => [8.0, 8.5, 9.0, 8.5],
            'recuperacao' => [4.0, 5.0, 7.0, 8.0],
            'frequencia' => [7.0, 7.0, 7.0, 7.0],
            'conselho' => [5.5, 6.0, 5.5, 6.0],
            'baixo' => [3.0, 4.0, 4.0, 3.5],
            'transferencia' => [7.0, 7.5, 7.0, 7.0],
            'controle' => [7.5, 7.5, 7.5, 7.5],
            'justificado' => [7.5, 8.0, 7.5, 8.0],
            'ocorrencia' => [6.5, 6.0, 6.5, 7.0],
            'extra' => [8.0, 8.0, 8.0, 8.0],
        ][$cenario] ?? [7.0, 7.0, 7.0, 7.0];
        $n = $base[$bim - 1];
        if ($tipoEv === 'p2') {
            $n = min(10, $n + 0.3);
        } elseif ($tipoEv === 'atv') {
            $n = max(0, $n - 0.4);
        } elseif ($tipoEv === 'rec') {
            $n = $cenario === 'recuperacao' ? 8.0 : $n;
        }
        return round($n, 1);
    }

    private function criarRecuperacao(int $ano, int $serieOrdem, int $turmaId, int $bim, array &$eventos): void
    {
        $periodo = $this->periodoBimestre($ano, $bim);
        $titulo = sprintf('%s Recuperação — %sº A %d B%d', PREFIXO, $serieOrdem, $ano, $bim);
        $exist = $this->db->fetch('SELECT id FROM provas_blocos WHERE titulo = :t AND deleted_at IS NULL LIMIT 1', ['t' => $titulo]);
        $profsEvento = [];
        foreach ($this->profMaterias as $chave => $nomes) {
            foreach ($nomes as $nomeMat) {
                $profsEvento[] = [
                    'professor_id' => $this->profs[$chave],
                    'materia_id' => (int) $this->materias[$nomeMat]['id'],
                    'quantidade_questoes' => 1,
                    'turmas' => [$turmaId],
                ];
            }
        }
        if ($exist) {
            $eventos['rec'] = (int) $exist['id'];
        } else {
            $eventos['rec'] = (int) $this->blocos->create([
                'titulo' => $titulo,
                'descricao' => 'Recuperação E2E',
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
                'professores' => $profsEvento,
            ]);
        }
        $this->lancarNotasEventos($turmaId, $bim, ['rec' => $eventos['rec']]);
    }

    private function gerarBoletim(int $ano, int $turmaId, int $bim, array $eventos): void
    {
        $codigo = sprintf('e2e-%d-t%d-b%d', $ano, $turmaId, $bim);
        $periodoRef = $ano . '-B' . $bim;
        $periodo = $this->periodoBimestre($ano, $bim);
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
            sprintf('Boletim %sº A %d B%d', $this->serieDaTurma($turmaId), $ano, $bim),
            $formula,
            $componentes,
            $existente ? (int) $existente['id'] : null,
            'E2E 2 provas + atividade',
            null,
            null,
            $codigo,
            json_encode([(int) ($this->db->fetch('SELECT serie_id FROM turmas WHERE id = :id', ['id' => $turmaId])['serie_id'] ?? 0)]),
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
        $erros = 0;
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
                $erros++;
                if ($erros <= 2) {
                    $this->registrar('boletim', false, ($al['nome'] ?? '') . ': ' . $e->getMessage(), 'CRITICO');
                }
            }
        }
        println("    boletim regra={$regraId} gerados={$gerados} erros={$erros}");
    }

    private function serieDaTurma(int $turmaId): int
    {
        foreach ($this->turmaIds as $k => $id) {
            if ($id === $turmaId) {
                return (int) explode(':', $k)[1];
            }
        }
        return 1;
    }

    private function fecharDiarios(int $ano, int $turmaId): void
    {
        $grades = $this->grades[$turmaId] ?? [];
        foreach ($grades as $g) {
            for ($bim = 1; $bim <= 4; $bim++) {
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
        println('    diários fechados nos 4 bimestres');
    }

    private function transferirAluno06(int $turmaId): void
    {
        $alunoId = $this->alunoIds['e2e.a06'] ?? 0;
        if ($alunoId <= 0) {
            $this->registrar('transferencia', false, 'aluno e2e.a06 não encontrado', 'ALTO');
            return;
        }
        try {
            $this->movimentacao->transferenciaEscolarEmLote(
                $turmaId,
                [$alunoId],
                [
                    'password' => SENHA,
                    'observation' => 'Transferência escolar E2E no 3º bimestre de 2026.',
                    'confirm' => '1',
                    'escola_nome' => 'Escola Municipal Destino E2E',
                    'escola_cidade' => 'São Paulo',
                    'escola_uf' => 'SP',
                    'motivo' => 'Mudança de município',
                    'data_transferencia' => '2026-08-01',
                ],
                ['id' => $this->adminId, 'tipo' => 'admin_escola', 'perfil_admin' => 'dev']
            );
            $this->registrar('transferencia', true, 'e2e.a06 transferido no 3º bimestre 2026');
        } catch (Throwable $e) {
            $this->registrar('transferencia', false, $e->getMessage(), 'CRITICO');
        }
    }

    private function registrarOcorrencias(int $ano, int $turmaId): void
    {
        $alunoId = $this->alunoIds['e2e.a09'] ?? 0;
        if ($alunoId <= 0 || $this->categoriaPedagogicaId <= 0) {
            return;
        }
        $res = $this->ocorrencias->criar([
            'aluno_id' => $alunoId,
            'titulo' => 'Não entrega recorrente de atividades',
            'detalhe' => 'Aluno E2E 09 deixou de entregar atividades em Língua Portuguesa durante o 3º bimestre.',
            'nivel_gravidade' => 'moderado',
            'data_ocorrencia' => $ano . '-08-15 10:00:00',
            'categoria_id' => $this->categoriaPedagogicaId,
            'encaminhamento' => 'Atendimento com responsável.',
            'local' => 'Sala de aula',
        ], $this->adminId, 'admin');
        $this->registrar('ocorrencia', !empty($res['success']), $res['error'] ?? ('id=' . ($res['id'] ?? 0)), empty($res['success']) ? 'ALTO' : 'ALTO');

        $aluno03 = $this->alunoIds['e2e.a03'] ?? 0;
        if ($aluno03 > 0) {
            $this->ocorrencias->criar([
                'aluno_id' => $aluno03,
                'titulo' => 'Frequência abaixo do mínimo legal',
                'detalhe' => 'Aluno com faltas reiteradas. Risco de reprovação por frequência (LDB 75%).',
                'nivel_gravidade' => 'grave',
                'data_ocorrencia' => $ano . '-08-20 09:00:00',
                'categoria_id' => $this->categoriaPedagogicaId,
                'encaminhamento' => 'Convocar responsável e acompanhar frequência.',
            ], $this->adminId, 'admin');
        }
    }

    private function executarConselho(int $ano, int $turmaId): void
    {
        $res = $this->conselhos->criar([
            'turma_id' => $turmaId,
            'ano_letivo' => $ano,
            'bimestre' => 4,
            'data_reuniao' => $ano . '-12-05',
            'pauta' => 'Conselho final E2E — análise do Aluno 04 (limítrofe) e frequência do Aluno 03.',
        ], $this->adminId);
        if (empty($res['success'])) {
            $this->registrar('conselho', false, $res['error'] ?? 'falha ao criar', 'CRITICO');
            return;
        }
        $sessaoId = (int) $res['id'];
        $sessao = $this->db->fetch('SELECT * FROM conselho_sessoes WHERE id = :id', ['id' => $sessaoId]);
        $status = (string) ($sessao['status'] ?? '');
        if ($status === 'finalizado') {
            $this->registrar('conselho', true, 'sessao=' . $sessaoId . ' já finalizada');
            return;
        }
        if (in_array($status, ['em_preparacao', 'reaberto'], true)) {
            $abrir = $this->conselhos->abrir($sessaoId, $this->adminId);
            if (empty($abrir['success'])) {
                $this->registrar('conselho', false, $abrir['error'] ?? 'falha ao abrir', 'CRITICO');
                return;
            }
        }
        $aluno04 = $this->alunoIds['e2e.a04'] ?? 0;
        if ($aluno04 > 0) {
            $del = $this->conselhos->deliberar($sessaoId, [
                'aluno_id' => $aluno04,
                'resultado_decisao' => 'aprovado_conselho',
                'justificativa' => 'Aluno limítrofe. Conselho delibera aprovação considerando evolução e comprometimento.',
            ], $this->adminId);
            $this->registrar('conselho_deliberacao', !empty($del['success']), $del['error'] ?? 'aluno 04 aprovado_conselho', empty($del['success']) ? 'ALTO' : 'ALTO');
        }
        $fin = $this->conselhos->finalizar($sessaoId, $this->adminId);
        $this->registrar('conselho', !empty($fin['success']), $fin['error'] ?? ('sessao=' . $sessaoId), empty($fin['success']) ? 'ALTO' : 'ALTO');
    }

    private function reabrirHomologacoes(int $ano, int $turmaId): void
    {
        $docs = $this->homologacao->model()->listarDaTurma($turmaId, $ano, 'ano', 0);
        $n = 0;
        foreach ($docs as $doc) {
            if ((string) ($doc['status'] ?? '') !== 'homologado') {
                continue;
            }
            $res = $this->homologacao->reabrir((int) $doc['id'], $this->adminId, 'Reabertura E2E para recálculo após correção de média/frequência.');
            if (!empty($res['success'])) {
                $n++;
            }
        }
        if ($n > 0) {
            println("    reabertos {$n} resultados homologados");
        }
    }

    private function homologarTurma(int $ano, int $turmaId): void
    {
        $preview = $this->homologacao->previewTurma($turmaId, $ano, 'ano', 0);
        $pend = (int) ($preview['resumo']['pendencias'] ?? 0);
        $total = (int) ($preview['resumo']['total'] ?? 0);
        println("    preview homologação: total={$total} pendencias={$pend} pode=" . (!empty($preview['pode_homologar']) ? 'sim' : 'nao'));
        foreach (array_slice($preview['linhas'] ?? [], 0, 12) as $linha) {
            $nome = $linha['aluno']['nome'] ?? '?';
            $sit = $linha['situacao'] ?? '';
            $pendencias = $linha['pendencias_criticas'] ?? [];
            if ($pendencias) {
                println('      · ' . $nome . ' sit=' . $sit . ' PEND: ' . json_encode($pendencias, JSON_UNESCAPED_UNICODE));
            } else {
                println('      · ' . $nome . ' sit=' . $sit);
            }
        }
        $res = $this->homologacao->homologarTurma($turmaId, $ano, 'ano', 0, $this->adminId, [], true);
        $this->registrar(
            'homologacao_' . $ano . '_t' . $turmaId,
            !empty($res['success']),
            $res['error'] ?? ('homologados=' . ($res['homologados'] ?? 0) . ' ignorados=' . ($res['ignorados'] ?? 0)),
            empty($res['success']) ? 'CRITICO' : 'ALTO'
        );
        $this->assertCenariosHomologacao($ano, $turmaId, $preview);
    }

    private function assertCenariosHomologacao(int $ano, int $turmaId, array $preview): void
    {
        if ($ano !== 2026) {
            return;
        }
        $serie = $this->serieDaTurma($turmaId);
        $porNick = [];
        foreach ($preview['linhas'] ?? [] as $linha) {
            $id = (int) ($linha['aluno']['id'] ?? 0);
            $nick = array_search($id, $this->alunoIds, true);
            if (is_string($nick)) {
                $porNick[$nick] = $linha;
            }
        }
        if ($serie === 3) {
            $this->assertSit('e2e.a01', $porNick, ['aprovado', 'aprovado_recuperacao', 'aprovado_conselho']);
            $this->assertSit('e2e.a03', $porNick, ['reprovado_frequencia']);
            $this->assertSit('e2e.a05', $porNick, ['reprovado_rendimento', 'recuperacao', 'exame_final']);
            $this->assertSit('e2e.a06', $porNick, ['transferido']);
        }
        if ($serie === 3 || $serie === 1) {
            $freqAlvo = $serie === 3 ? ($this->alunoIds['e2e.a03'] ?? 0) : 0;
            if ($freqAlvo > 0) {
                $pct = $this->frequencia->alunoPercentual($freqAlvo, $turmaId, $ano . '-02-01', $ano . '-12-15');
                $this->registrar('frequencia_a03', $pct !== null && $pct < 75.0, 'percentual=' . ($pct ?? 'null'), 'CRITICO');
            }
        }
    }

    private function assertSit(string $nick, array $porNick, array $esperados): void
    {
        if (!isset($porNick[$nick])) {
            $this->registrar('cenario_' . $nick, false, 'aluno ausente no preview', 'ALTO');
            return;
        }
        $sit = (string) ($porNick[$nick]['situacao'] ?? '');
        $ok = in_array($sit, $esperados, true);
        $this->registrar('cenario_' . $nick, $ok, 'obtido=' . $sit . ' esperado=' . implode('|', $esperados), $ok ? 'ALTO' : 'CRITICO');
    }

    private function gerarHistoricos(int $ano): void
    {
        if ($ano !== 2026) {
            return;
        }
        foreach (['e2e.a01', 'e2e.a02', 'e2e.a03', 'e2e.a06'] as $nick) {
            $id = $this->alunoIds[$nick] ?? 0;
            if ($id <= 0) {
                continue;
            }
            $finalidade = $nick === 'e2e.a06' ? 'Transferencia' : 'Solicitacao';
            $res = $this->historico->gerarRascunho($id, $finalidade, $this->adminId, 'Histórico E2E gerado após 3 anos.');
            $this->registrar('historico_' . $nick, !empty($res['success']), $res['error'] ?? ('id=' . ($res['id'] ?? 0)), empty($res['success']) ? 'ALTO' : 'ALTO');
        }
        try {
            $doc = new DocumentoOficialService();
            $turma3 = $this->turmaIds['2026:3'] ?? 0;
            $a01 = $this->alunoIds['e2e.a01'] ?? 0;
            if ($turma3 > 0 && $a01 > 0) {
                $ficha = $doc->emitirFicha($a01, $turma3, 2026, 'ano', 0, $this->adminId);
                $this->registrar('ficha', isset($ficha['html']), 'ficha aluno 01 gerada');
                $ata = $doc->emitirAta($turma3, 2026, 'ano', 0, $this->adminId);
                $this->registrar('ata', isset($ata['html']), 'ata 3º A 2026 gerada');
            }
        } catch (Throwable $e) {
            $this->registrar('documentos_oficiais', false, $e->getMessage(), 'ALTO');
        }
    }

    private function validarIntegridade(): void
    {
        $orfaoNota = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS c FROM provas_blocos_notas_lancadas n
             LEFT JOIN provas_blocos b ON b.id = n.bloco_id
             WHERE b.id IS NULL"
        )['c'] ?? 0);
        $this->registrar('integridade_nota_orfao', $orfaoNota === 0, 'orfãos=' . $orfaoNota, $orfaoNota ? 'CRITICO' : 'ALTO');

        $dupMat = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS c FROM (
                SELECT aluno_id, turma_id, ano_letivo_id, COUNT(*) n
                FROM matricula WHERE status = 'ativa'
                GROUP BY aluno_id, turma_id, ano_letivo_id HAVING n > 1
             ) x"
        )['c'] ?? 0);
        $this->registrar('integridade_matricula_dup', $dupMat === 0, 'duplicatas=' . $dupMat, $dupMat ? 'CRITICO' : 'ALTO');

        $freqSemAula = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS c FROM diario_frequencias df
             LEFT JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE da.id IS NULL"
        )['c'] ?? 0);
        $this->registrar('integridade_freq', $freqSemAula === 0, 'freq sem aula=' . $freqSemAula, $freqSemAula ? 'CRITICO' : 'ALTO');

        $contagens = [
            'alunos' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM alunos')['c'] ?? 0),
            'professores' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM professores')['c'] ?? 0),
            'turmas' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM turmas')['c'] ?? 0),
            'matriculas' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM matricula')['c'] ?? 0),
            'aulas' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM diario_aulas')['c'] ?? 0),
            'frequencias' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM diario_frequencias')['c'] ?? 0),
            'eventos' => (int) ($this->db->fetch("SELECT COUNT(*) c FROM provas_blocos WHERE titulo LIKE 'E2E%' AND deleted_at IS NULL")['c'] ?? 0),
            'notas' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM provas_blocos_notas_lancadas')['c'] ?? 0),
            'boletins' => (int) ($this->db->fetch('SELECT COUNT(*) c FROM boletim_resultados_gerados')['c'] ?? 0),
        ];
        println('  totais: ' . json_encode($contagens, JSON_UNESCAPED_UNICODE));
        $this->registrar(
            'volume',
            $contagens['aulas'] > 0 && $contagens['eventos'] > 0 && $contagens['notas'] > 0,
            json_encode($contagens, JSON_UNESCAPED_UNICODE)
        );
    }

    private function imprimirRelatorio(): void
    {
        $pass = count(array_filter($this->achados, static fn($a) => $a['ok']));
        $fail = count($this->achados) - $pass;
        println('');
        println('======== RELATÓRIO E2E ========');
        println('Passos: ' . count($this->achados) . " | PASS={$pass} | FAIL={$fail}");
        foreach ($this->achados as $a) {
            if ($a['ok']) {
                continue;
            }
            println('  FAIL [' . $a['severidade'] . '] ' . $a['etapa'] . ' — ' . $a['detalhe']);
        }
        println('Login admin: admin@colag.local / ' . SENHA);
        println('Login coordenador: coordenador@colag.local / ' . SENHA);
        println('Aluno regular: e2e.a01 / ' . SENHA);
        println('http://colag.localhost/admin');
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

    $somenteFechamento = in_array('--somente-fechamento', $argv ?? [], true);
    $runner = new E2eAnoLetivoCompleto(Database::getInstance());
    exit($runner->executar($somenteFechamento));
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
