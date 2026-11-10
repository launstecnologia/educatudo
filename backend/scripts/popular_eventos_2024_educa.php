<?php
/**
 * Popula o Colégio Educa (local) com eventos avaliativos de 2024
 * e notas lançadas, no ritmo de uma escola: semanal, bimestral,
 * simulado e recuperação só para quem ficou abaixo de 6,0.
 *
 * Uso (container PHP): php scripts/popular_eventos_2024_educa.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

ini_set('memory_limit', '512M');
set_time_limit(0);

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

use App\Core\Database;
use App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService;

const TENANT_DB = 'educatudo_educa';
const ANO = 2024;
const MEDIA_MINIMA = 6.0;

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

function unidade(int ...$partes): float
{
    $h = crc32(implode(':', $partes));
    return (abs($h) % 10000) / 10000;
}

function arredondarNota(float $nota): float
{
    $nota = max(0.0, min(10.0, $nota));
    return round($nota * 2) / 2;
}

function nivelAluno(int $alunoId, int $materiaId, int $bimestre): float
{
    $u = unidade($alunoId, 17);
    $base = match (true) {
        $u < 0.12 => 9.1,
        $u < 0.58 => 7.8,
        $u < 0.78 => 6.7,
        $u < 0.90 => 5.5,
        default => 4.4,
    };
    $afinidade = unidade($alunoId, $materiaId, 3) * 1.6 - 0.8;
    $ajusteBimestre = match ($bimestre) {
        1 => -0.2,
        2 => 0.0,
        3 => 0.15,
        default => 0.25,
    };
    return $base + $afinidade + $ajusteBimestre;
}

function notaSemanal(int $alunoId, int $materiaId, int $bimestre, int $semana): ?float
{
    if (unidade($alunoId, $materiaId, $bimestre, $semana, 8) < 0.025) {
        return null;
    }
    $oscilacao = unidade($alunoId, $materiaId, $bimestre, $semana, 1) * 1.6 - 0.8;
    return arredondarNota(nivelAluno($alunoId, $materiaId, $bimestre) + $oscilacao);
}

function notaBimestral(int $alunoId, int $materiaId, int $bimestre): float
{
    $oscilacao = unidade($alunoId, $materiaId, $bimestre, 21) * 1.4 - 0.7;
    return arredondarNota(nivelAluno($alunoId, $materiaId, $bimestre) - 0.5 + $oscilacao);
}

function notaSimulado(int $alunoId, int $materiaId, int $bimestre): float
{
    $oscilacao = unidade($alunoId, $materiaId, $bimestre, 33) * 2.4 - 1.2;
    return arredondarNota(nivelAluno($alunoId, $materiaId, $bimestre) - 1.0 + $oscilacao);
}

function mediaDoBimestre(int $alunoId, int $materiaId, int $bimestre): float
{
    $semanais = [];
    for ($semana = 1; $semana <= 4; $semana++) {
        $nota = notaSemanal($alunoId, $materiaId, $bimestre, $semana);
        if ($nota !== null) {
            $semanais[] = $nota;
        }
    }
    $bimestral = notaBimestral($alunoId, $materiaId, $bimestre);
    if ($semanais === []) {
        return $bimestral;
    }
    $mediaSemanal = array_sum($semanais) / count($semanais);
    return ($mediaSemanal * 0.4) + ($bimestral * 0.6);
}

function notaRecuperacao(int $alunoId, int $materiaId, int $bimestre): ?float
{
    if (mediaDoBimestre($alunoId, $materiaId, $bimestre) >= MEDIA_MINIMA) {
        return null;
    }
    $sorte = unidade($alunoId, $materiaId, $bimestre, 77);
    if ($sorte < 0.68) {
        return arredondarNota(6.0 + ($sorte / 0.68) * 1.5);
    }
    return arredondarNota(4.0 + (($sorte - 0.68) / 0.32) * 1.5);
}

/**
 * @param list<array{professor_id:int,materia_id:int,turmas:list<int>}> $pares
 * @param list<array{titulo:string,data:string}> $subeventos
 * @return array<string,mixed>
 */
function montarPost(string $nome, string $descricao, int $bimestre, array $pares, array $subeventos, string $prazo, bool $comMedia): array
{
    return [
        'nome' => $nome,
        'descricao' => $descricao,
        'ano_letivo' => ANO,
        'bimestre' => $bimestre,
        'ativo' => '1',
        'canal_origem' => 'offline',
        'modo_offline' => 'lancamento_direto',
        'configuracao_nota' => 'professor_por_questao',
        'tipo_prova' => 'original',
        'escala_max' => '10',
        'incluir_media' => $comMedia ? '1' : '0',
        'visivel_no_portal_aluno' => '1',
        'prazo_entrega_professor' => $prazo,
        'turmas' => $pares[0]['turmas'] ?? [],
        'pares_padrao' => $pares,
        'subeventos' => $subeventos,
    ];
}

/**
 * @return array<int,array{semanas:list<string>,bimestral:string,simulado:string,recuperacao:string,prazo_semanal:string,prazo_bimestral:string,prazo_simulado:string,prazo_recuperacao:string}>
 */
function calendario(): array
{
    return [
        1 => [
            'semanas' => ['2024-02-19', '2024-03-04', '2024-03-18', '2024-04-01'],
            'bimestral' => '2024-04-15',
            'simulado' => '2024-03-23',
            'recuperacao' => '2024-04-29',
            'prazo_semanal' => '2024-04-03 18:00',
            'prazo_bimestral' => '2024-04-17 18:00',
            'prazo_simulado' => '2024-03-26 18:00',
            'prazo_recuperacao' => '2024-04-30 18:00',
        ],
        2 => [
            'semanas' => ['2024-05-06', '2024-05-20', '2024-06-03', '2024-06-17'],
            'bimestral' => '2024-06-24',
            'simulado' => '2024-06-08',
            'recuperacao' => '2024-07-05',
            'prazo_semanal' => '2024-06-19 18:00',
            'prazo_bimestral' => '2024-06-26 18:00',
            'prazo_simulado' => '2024-06-11 18:00',
            'prazo_recuperacao' => '2024-07-08 18:00',
        ],
        3 => [
            'semanas' => ['2024-08-05', '2024-08-19', '2024-09-02', '2024-09-16'],
            'bimestral' => '2024-09-23',
            'simulado' => '2024-09-07',
            'recuperacao' => '2024-10-04',
            'prazo_semanal' => '2024-09-18 18:00',
            'prazo_bimestral' => '2024-09-25 18:00',
            'prazo_simulado' => '2024-09-10 18:00',
            'prazo_recuperacao' => '2024-10-07 18:00',
        ],
        4 => [
            'semanas' => ['2024-10-07', '2024-10-21', '2024-11-04', '2024-11-18'],
            'bimestral' => '2024-11-25',
            'simulado' => '2024-11-09',
            'recuperacao' => '2024-12-06',
            'prazo_semanal' => '2024-11-20 18:00',
            'prazo_bimestral' => '2024-11-27 18:00',
            'prazo_simulado' => '2024-11-12 18:00',
            'prazo_recuperacao' => '2024-12-09 18:00',
        ],
    ];
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

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

$db = Database::getInstance();
$service = new EventoAvaliativoService();
$catalogo = $service->catalogoFormulario(ANO);

$turmaIds = [];
foreach ($catalogo['turmas'] as $turma) {
    $id = (int) ($turma['id'] ?? 0);
    if ($id > 0) {
        $turmaIds[] = $id;
    }
}
if ($turmaIds === []) {
    fail('Nenhuma turma ativa.');
}

$professorPorMateria = [];
foreach ($catalogo['professores'] as $professor) {
    $professorId = (int) ($professor['id'] ?? 0);
    $nomes = json_decode((string) ($professor['materias'] ?? '[]'), true);
    if ($professorId <= 0 || !is_array($nomes)) {
        continue;
    }
    foreach ($nomes as $nomeMateria) {
        $chave = mb_strtolower(trim((string) $nomeMateria));
        if ($chave !== '' && !isset($professorPorMateria[$chave])) {
            $professorPorMateria[$chave] = $professorId;
        }
    }
}

$pares = [];
foreach ($catalogo['materias'] as $materia) {
    $materiaId = (int) ($materia['id'] ?? 0);
    $nome = trim((string) ($materia['nome'] ?? ''));
    if ($materiaId <= 0 || $nome === '' || stripos($nome, 'Tecnologia Educacional') !== false) {
        continue;
    }
    $professorId = $professorPorMateria[mb_strtolower($nome)] ?? 0;
    if ($professorId <= 0) {
        println('Sem professor para ' . $nome . '. Matéria ignorada.');
        continue;
    }
    $pares[] = [
        'professor_id' => $professorId,
        'materia_id' => $materiaId,
        'turmas' => $turmaIds,
    ];
}
if ($pares === []) {
    fail('Nenhum par professor/matéria.');
}

$admin = $db->fetch('SELECT id FROM usuarios ORDER BY id ASC LIMIT 1');
$adminId = (int) ($admin['id'] ?? 1);

$alunos = $db->fetchAll(
    'SELECT a.id, a.turma_id, a.nome
       FROM alunos a
       INNER JOIN turmas t ON t.id = a.turma_id AND t.ativo = 1
      WHERE a.ativo = 1
      ORDER BY a.turma_id, a.nome'
) ?: [];
$alunos = array_values(array_filter($alunos, static function (array $aluno): bool {
    $nome = mb_strtoupper(trim((string) ($aluno['nome'] ?? '')));
    return $nome !== '' && !preg_match('/^JOAO SILVA(?:\s+TESTE\b|\s+\d+\s*ANO\b)/u', $nome);
}));
if ($alunos === []) {
    fail('Nenhum aluno ativo nas turmas.');
}

println(count($pares) . ' matérias, ' . count($turmaIds) . ' turmas, ' . count($alunos) . ' alunos.');

$calendario = calendario();
foreach ($calendario as $bimestre => $datas) {
    $rotulo = $bimestre . 'º bimestre';
    $existentes = $db->fetchAll(
        'SELECT id, nome FROM eventos_avaliativos
          WHERE deleted_at IS NULL AND ano_letivo = :ano AND bimestre = :bimestre',
        ['ano' => ANO, 'bimestre' => $bimestre]
    ) ?: [];
    $porNome = [];
    foreach ($existentes as $row) {
        $porNome[(string) $row['nome']] = (int) $row['id'];
    }

    $planos = [
        [
            'nome' => 'Prova Semanal',
            'descricao' => 'Quatro avaliações semanais do ' . $rotulo . ' de ' . ANO . '.',
            'prazo' => $datas['prazo_semanal'],
            'media' => true,
            'subeventos' => array_map(static function (string $data, int $i): array {
                return ['titulo' => 'Semana ' . ($i + 1), 'data' => $data];
            }, $datas['semanas'], array_keys($datas['semanas'])),
        ],
        [
            'nome' => 'Prova Bimestral',
            'descricao' => 'Avaliação principal do ' . $rotulo . ' de ' . ANO . '.',
            'prazo' => $datas['prazo_bimestral'],
            'media' => false,
            'subeventos' => [['titulo' => 'Prova Bimestral', 'data' => $datas['bimestral']]],
        ],
        [
            'nome' => 'Simulado',
            'descricao' => 'Simulado do ' . $rotulo . ' de ' . ANO . ', com todas as turmas.',
            'prazo' => $datas['prazo_simulado'],
            'media' => false,
            'subeventos' => [['titulo' => 'Simulado', 'data' => $datas['simulado']]],
        ],
        [
            'nome' => 'Recuperação',
            'descricao' => 'Recuperação do ' . $rotulo . ' para quem ficou abaixo de 6,0.',
            'prazo' => $datas['prazo_recuperacao'],
            'media' => false,
            'subeventos' => [['titulo' => 'Recuperação', 'data' => $datas['recuperacao']]],
        ],
    ];

    foreach ($planos as $plano) {
        if (isset($porNome[$plano['nome']])) {
            println($rotulo . ' · ' . $plano['nome'] . ' já existe (#' . $porNome[$plano['nome']] . ').');
            continue;
        }
        $res = $service->criar(
            montarPost($plano['nome'], $plano['descricao'], $bimestre, $pares, $plano['subeventos'], $plano['prazo'], $plano['media']),
            $adminId
        );
        if (empty($res['success'])) {
            fail($rotulo . ' · ' . $plano['nome'] . ': ' . (string) ($res['error'] ?? 'falha ao criar'));
        }
        println($rotulo . ' · ' . $plano['nome'] . ' criado (#' . (int) $res['id'] . ').');
    }
}

$blocos = $db->fetchAll(
    'SELECT e.bimestre, e.nome AS evento, pb.id AS bloco_id, pb.titulo, pb.semana
       FROM eventos_avaliativos e
       INNER JOIN provas_blocos pb ON pb.evento_avaliativo_id = e.id AND pb.deleted_at IS NULL
      WHERE e.deleted_at IS NULL AND e.ano_letivo = :ano
        AND e.nome IN (\'Prova Semanal\', \'Prova Bimestral\', \'Simulado\', \'Recuperação\')
      ORDER BY e.bimestre, e.id, pb.semana, pb.id',
    ['ano' => ANO]
) ?: [];

$vinculos = $db->fetchAll(
    'SELECT pbp.bloco_id, pbp.professor_id, pbp.materia_id
       FROM provas_blocos_professores pbp
       INNER JOIN provas_blocos pb ON pb.id = pbp.bloco_id
       INNER JOIN eventos_avaliativos e ON e.id = pb.evento_avaliativo_id
      WHERE e.deleted_at IS NULL AND e.ano_letivo = :ano
        AND e.nome IN (\'Prova Semanal\', \'Prova Bimestral\', \'Simulado\', \'Recuperação\')',
    ['ano' => ANO]
) ?: [];
$materiasDoBloco = [];
foreach ($vinculos as $vinculo) {
    $materiasDoBloco[(int) $vinculo['bloco_id']][] = [
        'professor_id' => (int) $vinculo['professor_id'],
        'materia_id' => (int) $vinculo['materia_id'],
    ];
}

$inseridas = 0;
$recuperacaoAlunos = [];
foreach ($blocos as $bloco) {
    $blocoId = (int) $bloco['bloco_id'];
    $jaTem = (int) ($db->fetch(
        'SELECT COUNT(*) AS c FROM provas_blocos_notas_lancadas WHERE bloco_id = :id',
        ['id' => $blocoId]
    )['c'] ?? 0);
    if ($jaTem > 0) {
        println('Bloco #' . $blocoId . ' já tem ' . $jaTem . ' notas. Pulando.');
        continue;
    }
    $bimestre = (int) $bloco['bimestre'];
    $evento = (string) $bloco['evento'];
    $semana = (int) ($bloco['semana'] ?? 0);
    $linhas = [];
    foreach ($materiasDoBloco[$blocoId] ?? [] as $par) {
        foreach ($alunos as $aluno) {
            $alunoId = (int) $aluno['id'];
            $turmaId = (int) $aluno['turma_id'];
            $materiaId = $par['materia_id'];
            $nota = null;
            $observacao = null;
            if ($evento === 'Prova Semanal') {
                $nota = notaSemanal($alunoId, $materiaId, $bimestre, max(1, $semana));
            } elseif ($evento === 'Prova Bimestral') {
                $nota = notaBimestral($alunoId, $materiaId, $bimestre);
            } elseif ($evento === 'Simulado') {
                $nota = notaSimulado($alunoId, $materiaId, $bimestre);
            } else {
                $nota = notaRecuperacao($alunoId, $materiaId, $bimestre);
                if ($nota !== null) {
                    $observacao = 'Abaixo da média 6,0 no ' . $bimestre . 'º bimestre.';
                    $recuperacaoAlunos[$bimestre . ':' . $alunoId] = true;
                }
            }
            if ($nota === null) {
                continue;
            }
            $linhas[] = [$blocoId, $par['professor_id'], $materiaId, $turmaId, $alunoId, $nota, $observacao];
        }
    }
    foreach (array_chunk($linhas, 400) as $lote) {
        $marcas = [];
        $params = [];
        foreach ($lote as $i => $linha) {
            $marcas[] = "(:b{$i}, :p{$i}, :m{$i}, :t{$i}, :a{$i}, :n{$i}, :o{$i})";
            $params["b{$i}"] = $linha[0];
            $params["p{$i}"] = $linha[1];
            $params["m{$i}"] = $linha[2];
            $params["t{$i}"] = $linha[3];
            $params["a{$i}"] = $linha[4];
            $params["n{$i}"] = $linha[5];
            $params["o{$i}"] = $linha[6];
        }
        $db->query(
            'INSERT INTO provas_blocos_notas_lancadas
                (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota, observacao)
             VALUES ' . implode(', ', $marcas),
            $params
        );
        $inseridas += count($lote);
    }
    println($bimestre . 'º · ' . $evento . ' · ' . $bloco['titulo'] . ': ' . count($linhas) . ' notas.');
}

println('Notas inseridas nesta execução: ' . $inseridas . '.');
println('Alunos com alguma recuperação: ' . count($recuperacaoAlunos) . ' (somando os quatro bimestres).');
