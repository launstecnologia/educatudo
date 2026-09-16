<?php
/**
 * Seed Colégio Educa: lança notas reais nos alunos (1º, 2º e 3º bimestres de 2026)
 * para Prova Bimestral, Trabalho e Atividade em Aula — base para gerar boletim.
 *
 * Idempotente: reaproveita eventos EDUCA já existentes e só preenche aluno/matéria
 * que ainda não tem nota.
 *
 * Uso (container PHP):
 *   php scripts/seed_lancamento_notas_b123_educa.php
 *   php scripts/seed_lancamento_notas_b123_educa.php --dry-run
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
require_once $basePath . '/app/Models/Exams/ExamBlock.php';
require_once $basePath . '/app/Models/Exams/ExamBlockManualGrade.php';
require_once $basePath . '/app/Models/Exams/ExamEvaluationType.php';
require_once $basePath . '/scripts/lib/SimulacaoAcademicaEduca.php';

const TENANT_DB = 'educatudo_educa';
const PREFIXO = 'EDUCA';
const ANO = 2026;
const BIMESTRES = [1, 2, 3];

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

final class SeedLancamentoNotasB123Educa
{
    /** @var array<int, array<string, string>> */
    private const DATAS = [
        1 => ['prova' => '2026-03-20', 'trab' => '2026-03-10', 'atv' => '2026-03-05'],
        2 => ['prova' => '2026-05-22', 'trab' => '2026-05-12', 'atv' => '2026-05-08'],
        3 => ['prova' => '2026-08-28', 'trab' => '2026-08-18', 'atv' => '2026-08-14'],
    ];

    /** @var list<array{nome:string,descricao:string,ordem:int,chave:?string,slug:string,tipo_ev:string}> */
    private const TIPOS = [
        [
            'nome' => 'Prova Bimestral',
            'descricao' => 'Avaliação principal do bimestre.',
            'ordem' => 20,
            'chave' => 'prova_bim',
            'slug' => 'prova',
            'tipo_ev' => 'p1',
        ],
        [
            'nome' => 'Trabalho',
            'descricao' => 'Trabalho / atividade do bimestre.',
            'ordem' => 25,
            'chave' => null,
            'slug' => 'trab',
            'tipo_ev' => 'trab',
        ],
        [
            'nome' => 'Atividade em Aula',
            'descricao' => 'Atividade realizada em aula.',
            'ordem' => 40,
            'chave' => 'trabalho',
            'slug' => 'atv',
            'tipo_ev' => 'atv',
        ],
    ];

    private int $adminId = 0;
    /** @var array<string, int> */
    private array $tipoIds = [];
    private int $eventosNovos = 0;
    private int $eventosReusados = 0;
    private int $notasNovas = 0;
    private int $turmasSemAluno = 0;
    private int $turmasSemGrade = 0;
    private int $falhas = 0;

    public function __construct(
        private $db,
        private ExamBlock $blocos,
        private ExamBlockManualGrade $notas,
        private ExamEvaluationType $tipos,
        private bool $dryRun
    ) {
    }

    public function executar(): int
    {
        println('== Seed lançamento de notas 2026 B1/B2/B3 (Prova Bimestral, Trabalho, Atividade em Aula) ==');
        if ($this->dryRun) {
            println('Modo: dry-run (não grava)');
        }

        $this->adminId = $this->adminId();
        $this->garantirTipos();

        $turmas = $this->turmasDoAno();
        if ($turmas === []) {
            fail('Nenhuma turma ativa em ' . ANO . '.');
        }
        println('Turmas: ' . count($turmas));

        foreach ($turmas as $turma) {
            $this->processarTurma($turma);
        }

        println('');
        println('Eventos novos: ' . $this->eventosNovos . ' | reaproveitados: ' . $this->eventosReusados);
        println('Notas lançadas: ' . $this->notasNovas);
        if ($this->turmasSemAluno > 0) {
            println('Turmas sem aluno ativo: ' . $this->turmasSemAluno);
        }
        if ($this->turmasSemGrade > 0) {
            println('Turmas sem grade: ' . $this->turmasSemGrade);
        }
        if ($this->falhas > 0) {
            println('Falhas: ' . $this->falhas);
        }
        println('Próximo passo: Admin → Boletins → gerar com Prova Bimestral, Trabalho e Atividade em Aula.');

        return $this->falhas > 0 ? 1 : 0;
    }

    /** @param array{id:int,nome:string} $turma */
    private function processarTurma(array $turma): void
    {
        $turmaId = (int) $turma['id'];
        $alunos = $this->alunosDaTurma($turmaId);
        $profsGrade = $this->professoresDaGrade($turmaId);
        if ($alunos === []) {
            $this->turmasSemAluno++;
            println('  skip ' . $turma['nome'] . ' — sem alunos ativos');
            return;
        }
        if ($profsGrade === []) {
            $this->turmasSemGrade++;
            println('  skip ' . $turma['nome'] . ' — sem grade horária');
            return;
        }

        println('  ' . $turma['nome'] . ' (' . count($alunos) . ' alunos, ' . count($profsGrade) . ' matérias)');

        foreach (BIMESTRES as $bim) {
            foreach (self::TIPOS as $def) {
                $tipoId = $this->tipoIds[$def['slug']] ?? 0;
                if ($tipoId <= 0) {
                    continue;
                }
                $blocoIds = $this->blocosDaTurmaTipo($turmaId, $bim, $tipoId);
                if ($blocoIds === []) {
                    if ($this->dryRun) {
                        println('    [dry] criaria ' . $def['nome'] . ' B' . $bim);
                        $this->eventosNovos++;
                        continue;
                    }
                    $blocoId = $this->criarEvento($turma, $bim, $def, $tipoId, $profsGrade);
                    if ($blocoId <= 0) {
                        $this->falhas++;
                        continue;
                    }
                    $blocoIds = [$blocoId];
                    $this->eventosNovos++;
                } else {
                    $this->eventosReusados += count($blocoIds);
                }
                if ($this->dryRun) {
                    continue;
                }
                foreach ($blocoIds as $blocoId) {
                    $tipoEv = $this->tipoEvDoBloco($blocoId, $def['tipo_ev']);
                    $this->lancarNotas($blocoId, $turmaId, $bim, $tipoEv, $alunos);
                }
            }
        }
    }

    /**
     * @param array{id:int,nome:string} $turma
     * @param array<string,mixed> $def
     * @param list<array{professor_id:int,materia_id:int,quantidade_questoes:int,turmas:list<int>}> $profsGrade
     */
    private function criarEvento(array $turma, int $bim, array $def, int $tipoId, array $profsGrade): int
    {
        $titulo = sprintf('%s %s — %s B%d', PREFIXO, $def['nome'], $turma['nome'], $bim);
        $exist = $this->db->fetch(
            'SELECT id FROM provas_blocos WHERE titulo = :t AND deleted_at IS NULL LIMIT 1',
            ['t' => $titulo]
        );
        if ($exist) {
            return (int) $exist['id'];
        }

        $data = self::DATAS[$bim][$def['slug']] ?? (ANO . '-03-15');
        try {
            return (int) $this->blocos->create([
                'titulo' => $titulo,
                'descricao' => 'Lançamento de nota para boletim — Colégio Educa',
                'data_prova' => $data,
                'hora_inicio' => '08:00:00',
                'hora_fim' => '09:30:00',
                'criado_por' => $this->adminId,
                'tipo_prova' => 'original',
                'configuracao_nota' => 'coordenacao_calcula',
                'formato_evento' => 'lancamento_nota',
                'ano_letivo' => ANO,
                'bimestre' => $bim,
                'tipo_avaliacao_id' => $tipoId,
                'liberado' => 1,
                'ativo' => 1,
                'visivel_no_portal_aluno' => 1,
                'nota_unica_todas_materias' => 0,
                'turmas' => [(int) $turma['id']],
                'professores' => $profsGrade,
            ]);
        } catch (Throwable $e) {
            println('    ERRO criando ' . $titulo . ': ' . $e->getMessage());
            return 0;
        }
    }

    private function tipoEvDoBloco(int $blocoId, string $fallback): string
    {
        $row = $this->db->fetch('SELECT titulo FROM provas_blocos WHERE id = :id LIMIT 1', ['id' => $blocoId]);
        $titulo = (string) ($row['titulo'] ?? '');
        if ($titulo === '') {
            return $fallback;
        }
        return SimulacaoAcademicaEduca::tipoDoTitulo($titulo);
    }

    /** @param list<int> $alunos */
    private function lancarNotas(int $blocoId, int $turmaId, int $bim, string $tipoEv, array $alunos): void
    {
        $profs = $this->db->fetchAll(
            'SELECT professor_id, materia_id FROM provas_blocos_professores WHERE bloco_id = :id',
            ['id' => $blocoId]
        ) ?: [];
        if ($profs === []) {
            return;
        }
        foreach ($profs as $p) {
            $pid = (int) ($p['professor_id'] ?? 0);
            $mid = (int) ($p['materia_id'] ?? 0);
            if ($pid <= 0 || $mid <= 0) {
                continue;
            }
            $jaTem = $this->alunosComNota($blocoId, $pid, $mid, $turmaId);
            $linhas = [];
            foreach ($alunos as $aid) {
                if (isset($jaTem[$aid])) {
                    continue;
                }
                $linhas[] = [
                    'turma_id' => $turmaId,
                    'aluno_id' => $aid,
                    'nota' => SimulacaoAcademicaEduca::nota($aid, $mid, $bim, $tipoEv),
                ];
            }
            if ($linhas === []) {
                continue;
            }
            try {
                $this->notas->upsertLinhas($blocoId, $pid, $mid, $linhas);
                $this->notasNovas += count($linhas);
            } catch (Throwable $e) {
                $this->falhas++;
                println('    ERRO notas bloco=' . $blocoId . ' turma=' . $turmaId . ': ' . $e->getMessage());
            }
        }
    }

    /** @return list<int> */
    private function blocosDaTurmaTipo(int $turmaId, int $bim, int $tipoId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT pb.id, pb.titulo
             FROM provas_blocos pb
             INNER JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pb.id
             WHERE pb.deleted_at IS NULL
               AND pb.ano_letivo = :ano
               AND pb.bimestre = :bim
               AND pb.tipo_avaliacao_id = :tipo
               AND pbt.turma_id = :turma
               AND pb.formato_evento = 'lancamento_nota'
             ORDER BY pb.id ASC",
            [
                'ano' => ANO,
                'bim' => $bim,
                'tipo' => $tipoId,
                'turma' => $turmaId,
            ]
        ) ?: [];
        if ($rows === []) {
            return [];
        }

        $educa = [];
        foreach ($rows as $row) {
            $titulo = (string) ($row['titulo'] ?? '');
            if (str_starts_with($titulo, PREFIXO)) {
                $educa[] = (int) $row['id'];
            }
        }
        if ($educa !== []) {
            return array_values(array_unique($educa));
        }

        $exclusivos = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $qtd = $this->db->fetch(
                'SELECT COUNT(*) AS c FROM provas_blocos_turmas WHERE bloco_id = :id',
                ['id' => $id]
            );
            if ((int) ($qtd['c'] ?? 0) === 1) {
                $exclusivos[] = $id;
            }
        }

        return $exclusivos;
    }

    private function garantirTipos(): void
    {
        foreach (self::TIPOS as $def) {
            $row = $this->db->fetch(
                'SELECT id FROM provas_tipos_avaliacao WHERE LOWER(nome) = LOWER(:n) AND deleted_at IS NULL LIMIT 1',
                ['n' => $def['nome']]
            );
            if ($row) {
                $this->tipoIds[$def['slug']] = (int) $row['id'];
                continue;
            }
            if ($this->dryRun) {
                println('  [dry] criaria tipo ' . $def['nome']);
                $this->tipoIds[$def['slug']] = 0;
                continue;
            }
            $payload = [
                'nome' => $def['nome'],
                'descricao' => $def['descricao'],
                'ativo' => 1,
                'ordem' => $def['ordem'],
            ];
            if ($def['chave'] !== null) {
                $payload['chave_quadro'] = $def['chave'];
            }
            $this->tipoIds[$def['slug']] = (int) $this->tipos->create($payload);
        }
        println('Tipos: prova=' . ($this->tipoIds['prova'] ?? 0)
            . ' trab=' . ($this->tipoIds['trab'] ?? 0)
            . ' atv=' . ($this->tipoIds['atv'] ?? 0));
    }

    /** @return list<array{id:int,nome:string}> */
    private function turmasDoAno(): array
    {
        return $this->db->fetchAll(
            'SELECT id, nome FROM turmas WHERE ano_letivo = :ano AND ativo = 1 ORDER BY nome',
            ['ano' => ANO]
        ) ?: [];
    }

    /** @return list<int> */
    private function alunosDaTurma(int $turmaId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT a.id
             FROM alunos a
             INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t
             INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
             WHERE m.status IN ('ativa', 'concluido', 'transferido')
             GROUP BY a.id
             ORDER BY MIN(a.nome)",
            ['t' => $turmaId, 'ano' => ANO]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * @return list<array{professor_id:int,materia_id:int,quantidade_questoes:int,turmas:list<int>}>
     */
    private function professoresDaGrade(int $turmaId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT professor_id, materia_id
             FROM grade_horaria
             WHERE turma_id = :t
             GROUP BY professor_id, materia_id
             ORDER BY materia_id',
            ['t' => $turmaId]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['professor_id'] ?? 0);
            $mid = (int) ($row['materia_id'] ?? 0);
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
        foreach ($rows as $row) {
            $out[(int) $row['aluno_id']] = true;
        }
        return $out;
    }

    private function adminId(): int
    {
        $admin = $this->db->fetch('SELECT id FROM usuarios WHERE email = :e LIMIT 1', ['e' => 'admin@educa.local']);
        $id = (int) ($admin['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $admin = $this->db->fetch('SELECT id FROM usuarios WHERE id = 1 LIMIT 1');
        $id = (int) ($admin['id'] ?? 0);
        if ($id <= 0) {
            fail('Usuário admin não encontrado.');
        }
        return $id;
    }
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

$dryRun = in_array('--dry-run', $argv ?? [], true);

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
    $seed = new SeedLancamentoNotasB123Educa(
        Database::getInstance(),
        new ExamBlock(),
        new ExamBlockManualGrade(),
        new ExamEvaluationType(),
        $dryRun
    );
    exit($seed->executar());
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
