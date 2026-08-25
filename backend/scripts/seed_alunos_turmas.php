<?php
/**
 * Seed local: preenche vagas das turmas criando alunos e vinculando (matrícula ativa).
 *
 * O popular_colegio_educa.php encerra a matrícula no fim de cada ano, então turmas
 * históricas (ex.: 2023) ficam com 0/40 na tela mesmo com capacidade cadastrada.
 * Este script completa o que faltar — idempotente.
 *
 * Uso (container PHP):
 *   php scripts/seed_alunos_turmas.php
 *   php scripts/seed_alunos_turmas.php --alvo=30
 *   php scripts/seed_alunos_turmas.php --ano=2023
 *   php scripts/seed_alunos_turmas.php --db=educatudo_educa --dry-run
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
require_once $basePath . '/app/Services/AlunoMovimentacaoService.php';

const SENHA = 'Teste@123';
const ALVO_PADRAO = 25;
const PREFIXO_NICK = 'vt';
const BANCOS_LOCAIS = ['educatudo_educa', 'educatudo_colag'];

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

final class SeedAlunosTurmas
{
    private const NOMES = [
        'Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Felipe', 'Gabriela', 'Henrique',
        'Isabela', 'João', 'Karina', 'Lucas', 'Marina', 'Nicolas', 'Olivia', 'Pedro',
        'Rafaela', 'Samuel', 'Talita', 'Vitor', 'Yasmin', 'Caio', 'Beatriz', 'Eduardo',
    ];
    private const SOBRENOMES = [
        'Almeida', 'Barbosa', 'Cardoso', 'Dias', 'Fernandes', 'Gomes', 'Lima', 'Mendes',
        'Nogueira', 'Oliveira', 'Pereira', 'Rocha', 'Silva', 'Teixeira', 'Vieira', 'Castro',
    ];

    private $db;
    private \App\Services\AlunoMovimentacaoService $movimentacao;
    private string $hash;
    private int $alvo;
    private ?int $anoFiltro;
    private bool $dryRun;
    private bool $temUnidade;
    private bool $temStatus;
    private int $unidadeFundId = 0;
    private int $unidadeEmId = 0;
    /** @var array<int,int> */
    private array $anoLetivoPorAno = [];

    private int $vinculados = 0;
    private int $criados = 0;
    private int $jaOk = 0;
    private int $erros = 0;

    public function __construct($db, int $alvo, ?int $anoFiltro, bool $dryRun)
    {
        $this->db = $db;
        $this->alvo = $alvo;
        $this->anoFiltro = $anoFiltro;
        $this->dryRun = $dryRun;
        $this->hash = password_hash(SENHA, PASSWORD_DEFAULT);
        $this->movimentacao = new \App\Services\AlunoMovimentacaoService();
        $this->temUnidade = $this->temColuna('alunos', 'unidade_id');
        $this->temStatus = $this->temColuna('alunos', 'status');
    }

    public function executar(): int
    {
        println('== Seed alunos nas turmas ==');
        if ($this->dryRun) {
            println('Modo: dry-run (nenhuma gravação)');
        }
        println('Alvo por turma: ' . $this->alvo . ' alunos (limitado à capacidade, se houver)');
        if ($this->anoFiltro !== null) {
            println('Filtro: ano letivo ' . $this->anoFiltro);
        }

        $this->carregarAnosLetivos();
        $this->carregarUnidades();

        $turmas = $this->listarTurmas();
        if ($turmas === []) {
            fail('Nenhuma turma ativa encontrada.');
        }
        println('Turmas: ' . count($turmas));
        println('');

        foreach ($turmas as $turma) {
            $this->preencherTurma($turma);
        }

        println('');
        println('======== RESUMO ========');
        println('Vinculados (já existiam na turma): ' . $this->vinculados);
        println('Alunos criados:                   ' . $this->criados);
        println('Turmas já no alvo:                ' . $this->jaOk);
        println('Erros:                            ' . $this->erros);
        println('Senha padrão: ' . SENHA);
        println('Login: nickname do aluno (prefixo ' . PREFIXO_NICK . ')');

        return $this->erros > 0 ? 2 : 0;
    }

    private function carregarAnosLetivos(): void
    {
        $rows = $this->db->fetchAll('SELECT id, ano FROM ano_letivo') ?: [];
        foreach ($rows as $row) {
            $this->anoLetivoPorAno[(int) $row['ano']] = (int) $row['id'];
        }
        if ($this->anoLetivoPorAno === []) {
            fail('Nenhum ano letivo cadastrado. Cadastre em Admin > Ano Letivo.');
        }
    }

    private function carregarUnidades(): void
    {
        if (!$this->temUnidade) {
            return;
        }
        $temTabela = $this->db->fetch("SHOW TABLES LIKE 'unidades'");
        if ($temTabela === false) {
            return;
        }
        $em = $this->db->fetch(
            "SELECT id FROM unidades WHERE nome LIKE :q ORDER BY id ASC LIMIT 1",
            ['q' => '%Médio%']
        );
        $fund = $this->db->fetch(
            "SELECT id FROM unidades WHERE nome LIKE :q ORDER BY id ASC LIMIT 1",
            ['q' => '%Fundamental%']
        );
        $qualquer = $this->db->fetch('SELECT id FROM unidades ORDER BY id ASC LIMIT 1');
        $this->unidadeEmId = (int) ($em['id'] ?? ($qualquer['id'] ?? 0));
        $this->unidadeFundId = (int) ($fund['id'] ?? ($qualquer['id'] ?? 0));
    }

    /** @return list<array<string,mixed>> */
    private function listarTurmas(): array
    {
        $sql = 'SELECT id, nome, serie, ano_letivo, vagas, ativo';
        if ($this->temColuna('turmas', 'ano_letivo_id')) {
            $sql .= ', ano_letivo_id';
        }
        $sql .= ' FROM turmas WHERE ativo = 1';
        $params = [];
        if ($this->anoFiltro !== null) {
            $sql .= ' AND ano_letivo = :ano';
            $params['ano'] = $this->anoFiltro;
        }
        $sql .= ' ORDER BY ano_letivo ASC, serie ASC, nome ASC';

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /** @param array<string,mixed> $turma */
    private function preencherTurma(array $turma): void
    {
        $turmaId = (int) $turma['id'];
        $nome = (string) ($turma['nome'] ?? ('#' . $turmaId));
        $serie = trim((string) ($turma['serie'] ?? ''));
        $ano = (int) ($turma['ano_letivo'] ?? 0);
        $anoLetivoId = $this->resolverAnoLetivoId($turma);
        if ($anoLetivoId <= 0) {
            println('  SKIP ' . $nome . ' — ano letivo não encontrado');
            $this->erros++;
            return;
        }

        $capacidade = isset($turma['vagas']) && $turma['vagas'] !== null && (int) $turma['vagas'] > 0
            ? (int) $turma['vagas']
            : null;
        $alvo = $capacidade !== null ? min($this->alvo, $capacidade) : $this->alvo;

        $vinculadosAgora = $this->vincularAlunosDaTurma($turmaId, $anoLetivoId, $ano);
        $ocupadas = $this->contarOcupadas($turmaId);
        $faltam = max(0, $alvo - $ocupadas);

        if ($faltam === 0) {
            $this->jaOk++;
            println(sprintf(
                '  OK   %s — %d/%s (alvo %d)%s',
                $nome,
                $ocupadas,
                $capacidade !== null ? (string) $capacidade : '∞',
                $alvo,
                $vinculadosAgora > 0 ? " · vinculados={$vinculadosAgora}" : ''
            ));
            return;
        }

        $criadosTurma = 0;
        for ($slot = 1; $slot <= $faltam; $slot++) {
            $nick = $this->nickname($turmaId, $ocupadas + $slot);
            try {
                $alunoId = $this->upsertAluno($nick, $turmaId, $serie, $ano, $ocupadas + $slot);
                $this->vincular($alunoId, $turmaId, $anoLetivoId, $ano);
                $criadosTurma++;
            } catch (Throwable $e) {
                $this->erros++;
                println('    ERR ' . $nome . ' slot ' . $slot . ': ' . $e->getMessage());
            }
        }

        $ocupadasFim = $this->dryRun ? ($ocupadas + $faltam) : $this->contarOcupadas($turmaId);
        println(sprintf(
            '  FILL %s — %d/%s (criados=%d vinculados=%d)',
            $nome,
            $ocupadasFim,
            $capacidade !== null ? (string) $capacidade : '∞',
            $criadosTurma,
            $vinculadosAgora
        ));
    }

    private function vincularAlunosDaTurma(int $turmaId, int $anoLetivoId, int $ano): int
    {
        $sql = 'SELECT id FROM alunos WHERE turma_id = :tid AND ativo = 1';
        $params = ['tid' => $turmaId];
        if ($this->temStatus) {
            $sql .= " AND status = 'ACTIVE'";
        }
        $alunos = $this->db->fetchAll($sql, $params) ?: [];
        $n = 0;
        foreach ($alunos as $aluno) {
            $alunoId = (int) $aluno['id'];
            $ja = $this->db->fetch(
                "SELECT id FROM matricula
                 WHERE aluno_id = :aid AND turma_id = :tid AND ano_letivo_id = :ano
                   AND status = 'ativa' AND data_saida IS NULL",
                ['aid' => $alunoId, 'tid' => $turmaId, 'ano' => $anoLetivoId]
            );
            if ($ja) {
                continue;
            }
            try {
                $this->vincular($alunoId, $turmaId, $anoLetivoId, $ano);
                $n++;
                $this->vinculados++;
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'já possui matrícula') === false) {
                    $this->erros++;
                    println('    ERR vincular aluno ' . $alunoId . ': ' . $e->getMessage());
                }
            }
        }

        return $n;
    }

    private function upsertAluno(string $nick, int $turmaId, string $serie, int $ano, int $slot): int
    {
        $exist = $this->db->fetch('SELECT id FROM alunos WHERE nickname = :n LIMIT 1', ['n' => $nick]);
        if ($exist) {
            if (!$this->dryRun) {
                $params = [
                    't' => $turmaId,
                    's' => $serie !== '' ? $serie : 'Série',
                    'id' => (int) $exist['id'],
                ];
                $sql = 'UPDATE alunos SET turma_id = :t, serie = :s, ativo = 1';
                if ($this->temStatus) {
                    $sql .= ", status = 'ACTIVE'";
                }
                $sql .= ' WHERE id = :id';
                $this->db->query($sql, $params);
            }
            return (int) $exist['id'];
        }

        if ($this->dryRun) {
            $this->criados++;
            return 0;
        }

        $nome = $this->nomePessoa($turmaId, $slot);
        $idade = $this->idadeDaSerie($serie);
        $nascAno = max(1995, $ano - $idade);
        $cols = [
            'nome', 'nickname', 'email', 'senha_hash', 'ra', 'turma_id', 'serie',
            'data_nasc', 'ativo', 'pagante',
        ];
        $params = [
            'nome' => $nome,
            'nickname' => $nick,
            'email' => $nick . '@educa.local',
            'senha_hash' => $this->hash,
            'ra' => strtoupper($nick),
            'turma_id' => $turmaId,
            'serie' => $serie !== '' ? $serie : 'Série',
            'data_nasc' => sprintf('%04d-03-%02d', $nascAno, min(28, max(1, $slot % 28))),
            'ativo' => 1,
            'pagante' => 1,
        ];
        if ($this->temStatus) {
            $cols[] = 'status';
            $params['status'] = 'ACTIVE';
        }
        if ($this->temColuna('alunos', 'password')) {
            $cols[] = 'password';
            $params['password'] = '';
        }
        if ($this->temColuna('alunos', 'primeiro_acesso')) {
            $cols[] = 'primeiro_acesso';
            $params['primeiro_acesso'] = 0;
        }
        if ($this->temUnidade) {
            $unidadeId = $this->unidadeDaSerie($serie);
            if ($unidadeId > 0) {
                $cols[] = 'unidade_id';
                $params['unidade_id'] = $unidadeId;
            }
        }

        $placeholders = implode(', ', array_map(static fn ($c) => ':' . $c, $cols));
        $id = (int) $this->db->insert(
            'INSERT INTO alunos (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')',
            $params
        );
        $this->criados++;

        return $id;
    }

    private function vincular(int $alunoId, int $turmaId, int $anoLetivoId, int $ano): void
    {
        if ($this->dryRun || $alunoId <= 0) {
            return;
        }
        $dataEntrada = ($ano > 0 ? (string) $ano : date('Y')) . '-02-01';
        $this->movimentacao->vincularAlunoTurma($alunoId, $turmaId, $anoLetivoId, true, $dataEntrada);
    }

    private function contarOcupadas(int $turmaId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM matricula
             WHERE turma_id = :tid AND status = 'ativa' AND data_saida IS NULL",
            ['tid' => $turmaId]
        );

        return (int) ($row['total'] ?? 0);
    }

    /** @param array<string,mixed> $turma */
    private function resolverAnoLetivoId(array $turma): int
    {
        $direto = (int) ($turma['ano_letivo_id'] ?? 0);
        if ($direto > 0) {
            return $direto;
        }
        $ano = (int) ($turma['ano_letivo'] ?? 0);

        return $this->anoLetivoPorAno[$ano] ?? 0;
    }

    private function nickname(int $turmaId, int $slot): string
    {
        return PREFIXO_NICK . $turmaId . 's' . sprintf('%02d', $slot);
    }

    private function nomePessoa(int $turmaId, int $slot): string
    {
        $idx = abs($turmaId * 13 + $slot * 7);
        $nome = self::NOMES[$idx % count(self::NOMES)];
        $sob = self::SOBRENOMES[($idx * 3) % count(self::SOBRENOMES)];

        return $nome . ' ' . $sob;
    }

    private function idadeDaSerie(string $serie): int
    {
        $em = stripos($serie, 'EM') !== false || stripos($serie, 'Médio') !== false || stripos($serie, 'Medio') !== false;
        if ($em) {
            if (preg_match('/1/', $serie)) {
                return 15;
            }
            if (preg_match('/2/', $serie)) {
                return 16;
            }
            return 17;
        }
        if (preg_match('/6/', $serie)) {
            return 11;
        }
        if (preg_match('/7/', $serie)) {
            return 12;
        }
        if (preg_match('/8/', $serie)) {
            return 13;
        }
        if (preg_match('/9/', $serie)) {
            return 14;
        }

        return 12;
    }

    private function unidadeDaSerie(string $serie): int
    {
        $em = stripos($serie, 'EM') !== false || stripos($serie, 'Médio') !== false || stripos($serie, 'Medio') !== false;

        return $em ? $this->unidadeEmId : $this->unidadeFundId;
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $tabela) || !preg_match('/^[a-z0-9_]+$/i', $coluna)) {
            return false;
        }
        try {
            return $this->db->fetch('SHOW COLUMNS FROM `' . $tabela . '` LIKE ?', [$coluna]) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

$alvo = ALVO_PADRAO;
$dbName = 'educatudo_educa';
$anoFiltro = null;
$dryRun = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if (str_starts_with($arg, '--alvo=')) {
        $alvo = max(1, (int) substr($arg, 7));
        continue;
    }
    if (str_starts_with($arg, '--db=')) {
        $dbName = substr($arg, 5);
        continue;
    }
    if (str_starts_with($arg, '--ano=')) {
        $anoFiltro = (int) substr($arg, 6);
        continue;
    }
    fail('Argumento inválido: ' . $arg . PHP_EOL . 'Uso: php scripts/seed_alunos_turmas.php [--alvo=25] [--ano=2023] [--db=educatudo_educa] [--dry-run]');
}

if ($dbName === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $dbName) || strlen($dbName) > 64) {
    fail('Nome de banco inválido.');
}
if (!in_array($dbName, BANCOS_LOCAIS, true)) {
    fail('Abortado: --db deve ser educatudo_educa ou educatudo_colag.');
}

$appEnv = strtolower((string) env('APP_ENV', 'production'));
if (!in_array($appEnv, ['development', 'dev', 'local'], true)) {
    fail('Abortado: APP_ENV não é development/local.');
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

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

    $runner = new SeedAlunosTurmas(Database::getInstance(), $alvo, $anoFiltro, $dryRun);
    exit($runner->executar());
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
