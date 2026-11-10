<?php
/**
 * Seed da Matriz Curricular no Colégio Educa (local).
 *
 * Uma matriz por série ativa de Anos Iniciais, Anos Finais e Ensino Médio.
 * Língua Portuguesa não entra como rótulo: a grade guarda os desdobramentos
 * (Gramática, Interpretação e, quando a etapa pede, Literatura) e a carga
 * oficial da área é a soma deles.
 *
 * Uso (container PHP):
 *   php scripts/seed_matriz_curricular_educa.php
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
use App\Services\MatrizCurricularService;

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

/**
 * Aulas/semana por componente folha. A ordem do array é a ordem do boletim.
 *
 * @return array<string, array<string, int>>
 */
function cargasPorPerfil(): array
{
    return [
        'iniciais' => [
            'Interpretação de texto' => 3,
            'Gramática' => 2,
            'Matemática' => 5,
            'História' => 2,
            'Geografia' => 2,
            'Arte' => 1,
            'Educação Física' => 2,
            'Língua Inglesa' => 2,
        ],
        'finais' => [
            'Interpretação de texto' => 2,
            'Gramática' => 2,
            'Literatura' => 1,
            'Matemática' => 4,
            'Língua Inglesa' => 2,
            'História' => 2,
            'Geografia' => 2,
            'Arte' => 1,
            'Educação Física' => 2,
        ],
        'medio' => [
            'Gramática' => 2,
            'Interpretação de texto' => 1,
            'Literatura' => 1,
            'Matemática' => 4,
            'Língua Inglesa' => 2,
            'Arte' => 1,
            'Educação Física' => 2,
            'Biologia' => 2,
            'Física' => 2,
            'Química' => 2,
            'História' => 2,
            'Geografia' => 2,
            'Filosofia' => 1,
            'Sociologia' => 1,
        ],
    ];
}

final class SeedMatrizCurricularEduca
{
    private $db;
    private MatrizCurricularService $matrizes;
    /** @var array<string, array<string, mixed>> */
    private array $materias = [];

    public function __construct($db)
    {
        $this->db = $db;
        $this->matrizes = new MatrizCurricularService();
    }

    public function executar(): int
    {
        println('== Seed Matriz Curricular — Colégio Educa ==');
        $this->carregarMaterias();

        $series = $this->db->fetchAll(
            'SELECT s.id, s.nome, s.curso_id, c.nome AS curso_nome
             FROM serie s
             INNER JOIN curso c ON c.id = s.curso_id
             WHERE s.ativo = 1 AND c.ativo = 1
             ORDER BY c.ordem ASC, s.ordem ASC, s.id ASC'
        ) ?: [];

        if ($series === []) {
            fail('Nenhuma série ativa em educatudo_educa.');
        }

        $cargas = cargasPorPerfil();
        $criadas = 0;
        $atualizadas = 0;

        foreach ($series as $serie) {
            $perfil = $this->perfil((string) $serie['curso_nome']);
            if ($perfil === null || !isset($cargas[$perfil])) {
                println('  ignorada: ' . $serie['curso_nome'] . ' / ' . $serie['nome']);
                continue;
            }

            $numero = $this->numeroSerie((string) $serie['nome']);
            $codigo = $this->codigo($perfil, $numero);
            $componentes = $this->montarComponentes($cargas[$perfil]);
            $aulas = 0;
            foreach ($componentes as $comp) {
                $aulas += (int) $comp['aulas_semana'];
            }
            $cargaAnual = round($aulas * 50 * 40 / 60, 2);

            $payload = [
                'nome' => 'Matriz ' . $serie['nome'],
                'codigo' => $codigo,
                'curso_id' => (int) $serie['curso_id'],
                'serie_id' => (int) $serie['id'],
                'modalidade' => 'presencial',
                'turno' => 'manha',
                'carga_horaria_anual_prevista' => (string) $cargaAnual,
                'dias_letivos_previstos' => '200',
                'duracao_padrao_aula_minutos' => 50,
                'base_legal' => 'BNCC / LDB 9.394/96',
                'observacoes' => 'Língua Portuguesa entra pelos desdobramentos. A carga oficial da área é a soma das aulas.',
                'ativo' => 1,
                'componentes' => $componentes,
            ];

            $exist = $this->db->fetch(
                'SELECT id FROM matrizes_curriculares WHERE codigo = :codigo LIMIT 1',
                ['codigo' => $codigo]
            );
            if ($exist) {
                $res = $this->matrizes->atualizar((int) $exist['id'], $payload);
                if (empty($res['success'])) {
                    fail($codigo . ': ' . ($res['error'] ?? 'erro ao atualizar'));
                }
                $matrizId = (int) $exist['id'];
                $atualizadas++;
                println('  atualizada ' . $codigo . ' — ' . $serie['nome'] . ' (#' . $matrizId . ', ' . count($componentes) . ' componentes)');
            } else {
                $res = $this->matrizes->criar($payload);
                if (empty($res['success'])) {
                    fail($codigo . ': ' . ($res['error'] ?? 'erro ao criar'));
                }
                $matrizId = (int) $res['id'];
                $criadas++;
                println('  criada ' . $codigo . ' — ' . $serie['nome'] . ' (#' . $matrizId . ', ' . count($componentes) . ' componentes)');
            }

            $this->vincularTurmas($matrizId, (int) $serie['id'], (string) $serie['nome']);
        }

        println('Pronto. Criadas: ' . $criadas . '. Atualizadas: ' . $atualizadas . '.');
        println('http://educa.localhost/admin/matrizes-curriculares');
        return 0;
    }

    private function carregarMaterias(): void
    {
        $rows = $this->db->fetchAll('SELECT id, nome FROM materias WHERE ativo = 1') ?: [];
        foreach ($rows as $row) {
            $this->materias[(string) $row['nome']] = $row;
        }
    }

    private function perfil(string $cursoNome): ?string
    {
        $nome = mb_strtolower($cursoNome);
        if (str_contains($nome, 'iniciais')) {
            return 'iniciais';
        }
        if (str_contains($nome, 'finais')) {
            return 'finais';
        }
        if (str_contains($nome, 'médio') || str_contains($nome, 'medio')) {
            return 'medio';
        }
        return null;
    }

    private function numeroSerie(string $nome): int
    {
        if (preg_match('/(\d+)/', $nome, $m) === 1) {
            return (int) $m[1];
        }
        fail('Série sem número: ' . $nome);
    }

    private function codigo(string $perfil, int $numero): string
    {
        $prefixo = match ($perfil) {
            'iniciais' => 'EDUCA-AI-',
            'finais' => 'EDUCA-AF-',
            'medio' => 'EDUCA-EM-',
            default => 'EDUCA-',
        };
        return $prefixo . $numero;
    }

    /**
     * @param array<string, int> $carga
     * @return list<array{materia_id:int, aulas_semana:int, obrigatorio:int, ordem_boletim:int, ordem_historico:int}>
     */
    private function montarComponentes(array $carga): array
    {
        $componentes = [];
        $ordem = 1;
        foreach ($carga as $nome => $aulas) {
            if (!isset($this->materias[$nome])) {
                fail('Componente ausente no catálogo da Educa: ' . $nome);
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

    private function vincularTurmas(int $matrizId, int $serieId, string $serieNome): void
    {
        $turmas = $this->db->fetchAll(
            'SELECT id, nome, matriz_curricular_id
             FROM turmas
             WHERE serie_id = :serie_id AND ativo = 1',
            ['serie_id' => $serieId]
        ) ?: [];

        $ligadas = 0;
        foreach ($turmas as $turma) {
            $atual = (int) ($turma['matriz_curricular_id'] ?? 0);
            if ($atual === $matrizId) {
                continue;
            }
            if ($atual > 0) {
                println('    turma ' . $turma['nome'] . ' já tem outra matriz (#' . $atual . ') — mantida');
                continue;
            }
            $this->db->query(
                'UPDATE turmas SET matriz_curricular_id = :matriz_id WHERE id = :id',
                ['matriz_id' => $matrizId, 'id' => (int) $turma['id']]
            );
            $ligadas++;
        }
        if ($ligadas > 0) {
            println('    ' . $ligadas . ' turma(s) de ' . $serieNome . ' vinculadas');
        }
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
    exit((new SeedMatrizCurricularEduca(Database::getInstance()))->executar());
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
