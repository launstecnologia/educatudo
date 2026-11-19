<?php
/**
 * Seed EducaSync — ano 2027, Ensino Médio (Colégio Educa).
 *
 * - Ano letivo 2027
 * - 3 matrizes (1ª/2ª/3ª) com 30 aulas/semana, aula de 50 min, ~1.000 h/ano
 * - Distribuição FGB × itinerários (referência estadual SP, simulação):
 *     1ª: 30 FGB + 0 itinerário
 *     2ª: 27 FGB + 3 itinerários
 *     3ª: 15 FGB + 15 itinerários
 * - Língua Portuguesa entra pelos desdobramentos (Gramática / Interpretação / Literatura)
 * - 6 turmas (1A–3B) vinculadas às matrizes
 * - Um professor por componente (cadastro materias/turmas) para o motor sugerir demanda
 *
 * Uso (container PHP):
 *   php scripts/seed_educasync_2027_em.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');
if (!defined('TENANT_SLUG')) {
    define('TENANT_SLUG', 'educa');
}

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';

use App\Core\Database;
use App\Models\Education\MatrizCurricular;

const TENANT_DB = 'educatudo_educa';
const SENHA = 'Teste@123';
const ANO = 2027;

/**
 * Cargas por série (30 aulas/semana).
 * LP entra pelos desdobramentos (Gramática / Interpretação / Literatura) —
 * o offcanvas da matriz trata a área como rótulo oficial.
 *
 * @var array<string, array{serie_nome:string, letra:string, codigo:string, carga:array<string,int>}>
 */
const SERIES_SEED = [
    'em1' => [
        'serie_nome' => '1ª Série EM',
        'letra' => '1',
        'codigo' => 'SYNC-EM-2027-1',
        // 30 FGB / 0 itinerário (tabela base + 5 aulas redistribuídas); LP = 4
        'carga' => [
            'Gramática' => 2,
            'Interpretação de texto' => 1,
            'Literatura' => 1,
            'Matemática' => 5,
            'Língua Inglesa' => 3,
            'Arte' => 1,
            'Educação Física' => 2,
            'Biologia' => 3,
            'Física' => 3,
            'Química' => 3,
            'História' => 2,
            'Geografia' => 2,
            'Filosofia' => 1,
            'Sociologia' => 1,
        ],
    ],
    'em2' => [
        'serie_nome' => '2ª Série EM',
        'letra' => '2',
        'codigo' => 'SYNC-EM-2027-2',
        // 27 FGB + 3 itinerários; LP = 4
        'carga' => [
            'Gramática' => 2,
            'Interpretação de texto' => 1,
            'Literatura' => 1,
            'Matemática' => 5,
            'Língua Inglesa' => 3,
            'Arte' => 1,
            'Educação Física' => 2,
            'Biologia' => 2,
            'Física' => 2,
            'Química' => 2,
            'História' => 2,
            'Geografia' => 2,
            'Filosofia' => 1,
            'Sociologia' => 1,
            'Itinerários formativos' => 3,
        ],
    ],
    'em3' => [
        'serie_nome' => '3ª Série EM',
        'letra' => '3',
        'codigo' => 'SYNC-EM-2027-3',
        // 15 FGB + 15 itinerários (Arte omitida); LP = 3
        'carga' => [
            'Gramática' => 1,
            'Interpretação de texto' => 1,
            'Literatura' => 1,
            'Matemática' => 3,
            'Língua Inglesa' => 1,
            'Educação Física' => 1,
            'Biologia' => 1,
            'Física' => 1,
            'Química' => 1,
            'História' => 1,
            'Geografia' => 1,
            'Filosofia' => 1,
            'Sociologia' => 1,
            'Itinerários formativos' => 15,
        ],
    ],
];

const LETRAS = ['A', 'B'];

/**
 * codigo_prof => [nome, matéria] ou [nome, matéria, turmasNome]
 * Itinerários da 3ª série têm 15 aulas/turma: um professor só não cabe em 30 slots/semana
 * (2 turmas × 15 = 30, + 2ª série estoura). Por isso EM-ITIN e EM-ITIN2 dividem as turmas.
 */
const PROFESSORES = [
    'EM-POR' => ['Sofia Portela', 'Língua Portuguesa'],
    'EM-GRAM' => ['Helena Duarte', 'Gramática'],
    'EM-INT' => ['Clara Mendes', 'Interpretação de texto'],
    'EM-LIT' => ['Beatriz Nogueira', 'Literatura'],
    'EM-MAT' => ['Marcos Tavares', 'Matemática'],
    'EM-ING' => ['Camila Rocha', 'Língua Inglesa'],
    'EM-ART' => ['Lúcia Prado', 'Arte'],
    'EM-EDF' => ['Érica Fontes', 'Educação Física'],
    'EM-BIO' => ['Natália Correia', 'Biologia'],
    'EM-FIS' => ['André Pacheco', 'Física'],
    'EM-QUI' => ['Renata Blanco', 'Química'],
    'EM-HIS' => ['Hugo Sampaio', 'História'],
    'EM-GEO' => ['Paulo Vieira', 'Geografia'],
    'EM-FIL' => ['Diego Martins', 'Filosofia'],
    'EM-SOC' => ['Carla Nunes', 'Sociologia'],
    'EM-ITIN' => ['Rafael Itinerário', 'Itinerários formativos', ['2A', '3A']],
    'EM-ITIN2' => ['Bianca Itinerário', 'Itinerários formativos', ['2B', '3B']],
];

const SALAS = [
    '1A' => 'EDUCA-SALA-01',
    '1B' => 'EDUCA-SALA-02',
    '2A' => 'EDUCA-SALA-03',
    '2B' => 'EDUCA-SALA-04',
    '3A' => 'EDUCA-SALA-05',
    '3B' => 'EDUCA-SALA-06',
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

final class SeedEducaSync2027Em
{
    private $db;
    private MatrizCurricular $matrizes;
    private string $hash;

    /** @var array<string, array<string, mixed>> */
    private array $materias = [];
    /** @var array<string, int> */
    private array $serieIds = [];
    /** @var array<string, int> */
    private array $matrizPorSerie = [];
    /** @var list<int> */
    private array $turmaIds = [];
    private int $cursoEmId = 0;
    private int $anoLetivoId = 0;

    public function __construct($db)
    {
        $this->db = $db;
        $this->matrizes = new MatrizCurricular();
        $this->hash = password_hash(SENHA, PASSWORD_DEFAULT);
    }

    public function executar(): int
    {
        println('== Seed EducaSync 2027 — Ensino Médio (Educa) ==');
        $this->garantirAnoLetivo();
        $this->carregarCursoSeries();
        $this->garantirMaterias();
        $this->criarMatrizes();
        $this->criarTurmas();
        $this->atribuirProfessores();
        $this->imprimirResumo();
        return 0;
    }

    private function garantirAnoLetivo(): void
    {
        $exist = $this->db->fetch('SELECT id FROM ano_letivo WHERE ano = :ano LIMIT 1', ['ano' => ANO]);
        if ($exist) {
            $this->anoLetivoId = (int) $exist['id'];
            $this->db->query(
                'UPDATE ano_letivo
                 SET data_inicio = :ini, data_fim = :fim, periodo_tipo = :periodo, ativo = 1
                 WHERE id = :id',
                [
                    'ini' => ANO . '-02-01',
                    'fim' => ANO . '-12-20',
                    'periodo' => 'bimestre',
                    'id' => $this->anoLetivoId,
                ]
            );
            println('  ano letivo ' . ANO . ' (#' . $this->anoLetivoId . ') atualizado');
            return;
        }

        $this->anoLetivoId = (int) $this->db->insert(
            'INSERT INTO ano_letivo (ano, data_inicio, data_fim, periodo_tipo, ativo)
             VALUES (:ano, :ini, :fim, :periodo, 1)',
            [
                'ano' => ANO,
                'ini' => ANO . '-02-01',
                'fim' => ANO . '-12-20',
                'periodo' => 'bimestre',
            ]
        );
        println('  ano letivo ' . ANO . ' criado (#' . $this->anoLetivoId . ')');
    }

    private function carregarCursoSeries(): void
    {
        $curso = $this->db->fetch(
            "SELECT id, nome FROM curso
             WHERE ativo = 1 AND (nome LIKE '%Médio%' OR nome LIKE '%Medio%')
             ORDER BY id ASC LIMIT 1"
        );
        if (!$curso) {
            fail('Curso Ensino Médio não encontrado.');
        }
        $this->cursoEmId = (int) $curso['id'];

        foreach (SERIES_SEED as $chave => $meta) {
            $serie = $this->db->fetch(
                'SELECT id, nome FROM serie
                 WHERE curso_id = :curso AND ativo = 1 AND nome = :nome
                 LIMIT 1',
                ['curso' => $this->cursoEmId, 'nome' => $meta['serie_nome']]
            );
            if (!$serie) {
                fail('Série ausente: ' . $meta['serie_nome']);
            }
            $this->serieIds[$chave] = (int) $serie['id'];
        }
        println('  curso EM #' . $this->cursoEmId . ' · séries ' . implode(', ', $this->serieIds));
    }

    private function garantirMaterias(): void
    {
        $nomes = [];
        foreach (SERIES_SEED as $meta) {
            foreach (array_keys($meta['carga']) as $nome) {
                $nomes[$nome] = true;
            }
        }
        $nomes['Língua Portuguesa'] = true;
        $nomes['Itinerários formativos'] = true;

        foreach (array_keys($nomes) as $nome) {
            $row = $this->resolverMateria($nome);
            if ($row !== null) {
                $this->materias[$nome] = $row;
                continue;
            }
            if ($nome !== 'Itinerários formativos') {
                fail('Componente ausente no catálogo: ' . $nome);
            }
            $id = (int) $this->db->insert(
                "INSERT INTO materias
                    (nome, codigo, sigla, area_conhecimento, tipo,
                     etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio,
                     cor, ordem, ativo)
                 VALUES
                    (:nome, :codigo, :sigla, 'interdisciplinar', 'itinerario_formativo',
                     'nao_aplica', 'nao_aplica', 'nao_aplica', 'obrigatoria',
                     '#0EA5E9', 90, 1)",
                [
                    'nome' => 'Itinerários formativos',
                    'codigo' => 'ITIN-EM',
                    'sigla' => 'ITIN',
                ]
            );
            $this->materias[$nome] = [
                'id' => $id,
                'nome' => 'Itinerários formativos',
            ];
            println('  matéria criada: Itinerários formativos (#' . $id . ')');
        }
    }

    /**
     * Prefere o id já usado nas matrizes EM atuais; senão o menor id ativo.
     *
     * @return array{id:int,nome:string}|null
     */
    private function resolverMateria(string $nome): ?array
    {
        $preferidos = $this->db->fetch(
            'SELECT m.id, m.nome
             FROM materias m
             INNER JOIN matrizes_curriculares_componentes mc ON mc.materia_id = m.id
             INNER JOIN matrizes_curriculares mx ON mx.id = mc.matriz_id
             WHERE m.nome = :nome AND m.ativo = 1 AND mx.codigo LIKE :pref
             ORDER BY m.id ASC
             LIMIT 1',
            ['nome' => $nome, 'pref' => 'EDUCA-EM-%']
        );
        if ($preferidos) {
            return ['id' => (int) $preferidos['id'], 'nome' => (string) $preferidos['nome']];
        }

        $row = $this->db->fetch(
            'SELECT id, nome FROM materias WHERE nome = :nome AND ativo = 1 ORDER BY id ASC LIMIT 1',
            ['nome' => $nome]
        );
        return $row ? ['id' => (int) $row['id'], 'nome' => (string) $row['nome']] : null;
    }

    private function criarMatrizes(): void
    {
        foreach (SERIES_SEED as $chave => $meta) {
            $componentes = $this->montarComponentes($meta['carga']);
            $aulas = array_sum($meta['carga']);
            if ($aulas !== 30) {
                fail($meta['codigo'] . ': carga semanal deve ser 30, veio ' . $aulas);
            }
            $cargaAnual = round($aulas * 50 * 40 / 60, 2); // 1.000 h

            $payload = [
                'nome' => 'EducaSync ' . $meta['serie_nome'] . ' 2027',
                'codigo' => $meta['codigo'],
                'curso_id' => $this->cursoEmId,
                'serie_id' => $this->serieIds[$chave],
                'modalidade' => 'presencial',
                'turno' => 'manha',
                'carga_horaria_anual_prevista' => $cargaAnual,
                'dias_letivos_previstos' => 200,
                'duracao_padrao_aula_minutos' => 50,
                'base_legal' => 'BNCC / LDB 9.394/96 — simulação EducaSync 2027',
                'observacoes' => 'Seed EducaSync: 6 aulas/dia, 50 min, 30 aulas/semana (~1.000 h). '
                    . 'LP pelos desdobramentos. FGB×itinerários por série (ref. estadual SP).',
                'ativo' => 1,
            ];

            $exist = $this->db->fetch(
                'SELECT id FROM matrizes_curriculares WHERE codigo = :codigo LIMIT 1',
                ['codigo' => $meta['codigo']]
            );
            if ($exist) {
                $matrizId = (int) $exist['id'];
                $this->matrizes->update($matrizId, $payload);
                println('  matriz atualizada ' . $meta['codigo'] . ' (#' . $matrizId . ', ' . $aulas . ' aulas/sem)');
            } else {
                $matrizId = (int) $this->matrizes->create($payload);
                println('  matriz criada ' . $meta['codigo'] . ' (#' . $matrizId . ', ' . $aulas . ' aulas/sem)');
            }
            $this->matrizes->salvarComponentes($matrizId, $componentes);
            $this->matrizPorSerie[$chave] = $matrizId;
        }
    }

    /**
     * @param array<string,int> $carga
     * @return list<array{materia_id:int,aulas_semana:int,obrigatorio:int,ordem_boletim:int,ordem_historico:int}>
     */
    private function montarComponentes(array $carga): array
    {
        $componentes = [];
        $ordem = 1;
        foreach ($carga as $nome => $aulas) {
            if (!isset($this->materias[$nome])) {
                fail('Componente sem id: ' . $nome);
            }
            $componentes[] = [
                'materia_id' => (int) $this->materias[$nome]['id'],
                'aulas_semana' => $aulas,
                'obrigatorio' => 1,
                'ordem_boletim' => $ordem,
                'ordem_historico' => $ordem,
            ];
            $ordem++;
        }
        return $componentes;
    }

    private function criarTurmas(): void
    {
        $this->turmaIds = [];
        foreach (SERIES_SEED as $chave => $meta) {
            foreach (LETRAS as $letra) {
                $nome = $meta['letra'] . $letra;
                $salaCodigo = SALAS[$nome] ?? null;
                $salaId = null;
                if ($salaCodigo) {
                    $sala = $this->db->fetch(
                        'SELECT id FROM school_locations WHERE codigo = :c LIMIT 1',
                        ['c' => $salaCodigo]
                    );
                    $salaId = $sala ? (int) $sala['id'] : null;
                }

                $exist = $this->db->fetch(
                    'SELECT id FROM turmas
                     WHERE nome = :nome AND ano_letivo = :ano AND serie_id = :serie
                     LIMIT 1',
                    [
                        'nome' => $nome,
                        'ano' => ANO,
                        'serie' => $this->serieIds[$chave],
                    ]
                );

                $dados = [
                    'nome' => $nome,
                    'ano_letivo' => ANO,
                    'ano_letivo_id' => $this->anoLetivoId,
                    'serie' => $meta['serie_nome'],
                    'serie_id' => $this->serieIds[$chave],
                    'curso_novo_id' => $this->cursoEmId,
                    'matriz_curricular_id' => $this->matrizPorSerie[$chave],
                    'turno' => 'manha',
                    'sala_padrao_id' => $salaId,
                    'vagas' => 40,
                    'ativo' => 1,
                    'observacoes' => 'Seed EducaSync 2027',
                ];

                if ($exist) {
                    $id = (int) $exist['id'];
                    $this->db->query(
                        'UPDATE turmas
                         SET ano_letivo_id = :ano_letivo_id,
                             serie = :serie,
                             serie_id = :serie_id,
                             curso_novo_id = :curso_novo_id,
                             matriz_curricular_id = :matriz_curricular_id,
                             turno = :turno,
                             sala_padrao_id = :sala_padrao_id,
                             vagas = :vagas,
                             ativo = 1,
                             observacoes = :observacoes
                         WHERE id = :id',
                        [
                            'ano_letivo_id' => $dados['ano_letivo_id'],
                            'serie' => $dados['serie'],
                            'serie_id' => $dados['serie_id'],
                            'curso_novo_id' => $dados['curso_novo_id'],
                            'matriz_curricular_id' => $dados['matriz_curricular_id'],
                            'turno' => $dados['turno'],
                            'sala_padrao_id' => $dados['sala_padrao_id'],
                            'vagas' => $dados['vagas'],
                            'observacoes' => $dados['observacoes'],
                            'id' => $id,
                        ]
                    );
                    println('  turma atualizada ' . $nome . ' (#' . $id . ')');
                } else {
                    $id = (int) $this->db->insert(
                        'INSERT INTO turmas
                            (nome, ano_letivo, ano_letivo_id, serie, serie_id, curso_novo_id,
                             matriz_curricular_id, turno, sala_padrao_id, vagas, ativo, observacoes)
                         VALUES
                            (:nome, :ano_letivo, :ano_letivo_id, :serie, :serie_id, :curso_novo_id,
                             :matriz_curricular_id, :turno, :sala_padrao_id, :vagas, 1, :observacoes)',
                        [
                            'nome' => $dados['nome'],
                            'ano_letivo' => $dados['ano_letivo'],
                            'ano_letivo_id' => $dados['ano_letivo_id'],
                            'serie' => $dados['serie'],
                            'serie_id' => $dados['serie_id'],
                            'curso_novo_id' => $dados['curso_novo_id'],
                            'matriz_curricular_id' => $dados['matriz_curricular_id'],
                            'turno' => $dados['turno'],
                            'sala_padrao_id' => $dados['sala_padrao_id'],
                            'vagas' => $dados['vagas'],
                            'observacoes' => $dados['observacoes'],
                        ]
                    );
                    println('  turma criada ' . $nome . ' (#' . $id . ')');
                }
                $this->turmaIds[] = $id;
            }
        }
    }

    private function atribuirProfessores(): void
    {
        $turmaIdPorNome = [];
        foreach ($this->turmaIds as $turmaId) {
            $linha = $this->db->fetch('SELECT id, nome FROM turmas WHERE id = :id', ['id' => (int) $turmaId]);
            if ($linha) {
                $turmaIdPorNome[(string) $linha['nome']] = (int) $linha['id'];
            }
        }

        foreach (PROFESSORES as $codigo => $meta) {
            $nome = (string) $meta[0];
            $materia = (string) $meta[1];
            $turmasNome = isset($meta[2]) && is_array($meta[2]) ? $meta[2] : null;
            $idsTurma = $this->turmaIds;
            if ($turmasNome !== null) {
                $idsTurma = [];
                foreach ($turmasNome as $tn) {
                    if (isset($turmaIdPorNome[$tn])) {
                        $idsTurma[] = $turmaIdPorNome[$tn];
                    }
                }
            }
            $email = 'em.' . strtolower(str_replace('_', '', substr($codigo, 3))) . '@educa.local';
            $materiasJson = json_encode([$materia], JSON_UNESCAPED_UNICODE);
            $turmasJson = json_encode(array_map('intval', $idsTurma));

            $exist = $this->db->fetch(
                'SELECT id FROM professores WHERE codigo_prof = :c LIMIT 1',
                ['c' => $codigo]
            );
            if ($exist) {
                $this->db->query(
                    'UPDATE professores
                     SET nome = :nome, email = :email, materias = :materias, turmas = :turmas,
                         ativo = 1, pagante = 1
                     WHERE id = :id',
                    [
                        'nome' => $nome,
                        'email' => $email,
                        'materias' => $materiasJson,
                        'turmas' => $turmasJson,
                        'id' => (int) $exist['id'],
                    ]
                );
                println('  professor ' . $codigo . ' → ' . $materia . ($turmasNome ? ' [' . implode(',', $turmasNome) . ']' : ''));
            } else {
                $this->db->insert(
                    'INSERT INTO professores
                        (nome, email, senha_hash, codigo_prof, materias, turmas, ativo, pagante, password)
                     VALUES
                        (:nome, :email, :senha, :codigo, :materias, :turmas, 1, 1, :password)',
                    [
                        'nome' => $nome,
                        'email' => $email,
                        'senha' => $this->hash,
                        'codigo' => $codigo,
                        'materias' => $materiasJson,
                        'turmas' => $turmasJson,
                        'password' => '',
                    ]
                );
                println('  professor criado ' . $codigo . ' → ' . $materia . ($turmasNome ? ' [' . implode(',', $turmasNome) . ']' : ''));
            }
        }
    }

    private function imprimirResumo(): void
    {
        println('');
        println('Pronto.');
        println('  Ano: ' . ANO . ' (#' . $this->anoLetivoId . ')');
        foreach ($this->matrizPorSerie as $chave => $id) {
            println('    ' . SERIES_SEED[$chave]['codigo'] . ' #' . $id . ' (' . array_sum(SERIES_SEED[$chave]['carga']) . ' aulas)');
        }
        println('  Turmas 2027: ' . implode(', ', $this->turmaIds));
        println('  Senha professores: ' . SENHA);
        println('  Admin: http://educa.localhost/admin/matrizes-curriculares');
        println('  EducaSync: criar cenário no ano 2027 e importar demandas da matriz.');
    }
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail('Abortado: DB_HOST=' . $host . ' não parece local.');
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
    exit((new SeedEducaSync2027Em(Database::getInstance()))->executar());
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
