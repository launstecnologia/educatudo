<?php
/**
 * Recria o Colégio Educa (local) como um ano letivo só: 2025 fechado.
 * Quatro bimestres com prova semanal (4 datas), prova bimestral e simulado.
 * Recuperação no fim de cada semestre e uma final, só para quem ficou abaixo de 6,0.
 *
 * Uso (container PHP): php scripts/popular_escola_2025_educa.php
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
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;

const TENANT_DB = 'educatudo_educa';
const ANO = 2025;
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
        $u < 0.08 => 4.1,
        $u < 0.20 => 5.2,
        $u < 0.42 => 6.4,
        $u < 0.78 => 7.6,
        default => 8.9,
    };
    $afinidade = unidade($alunoId, $materiaId, 3) * 2.2 - 1.1;
    $ajusteBimestre = match ($bimestre) {
        1 => -0.25,
        2 => -0.05,
        3 => 0.1,
        default => 0.2,
    };
    return $base + $afinidade + $ajusteBimestre;
}

function notaSemanal(int $alunoId, int $materiaId, int $bimestre, int $semana): ?float
{
    if (unidade($alunoId, $materiaId, $bimestre, $semana, 8) < 0.03) {
        return null;
    }
    $oscilacao = unidade($alunoId, $materiaId, $bimestre, $semana, 1) * 1.6 - 0.8;
    return arredondarNota(nivelAluno($alunoId, $materiaId, $bimestre) + $oscilacao);
}

function notaBimestral(int $alunoId, int $materiaId, int $bimestre): float
{
    $oscilacao = unidade($alunoId, $materiaId, $bimestre, 21) * 1.4 - 0.7;
    return arredondarNota(nivelAluno($alunoId, $materiaId, $bimestre) - 0.4 + $oscilacao);
}

function notaSimulado(int $alunoId, int $materiaId, int $bimestre): float
{
    $oscilacao = unidade($alunoId, $materiaId, $bimestre, 33) * 2.6 - 1.3;
    return arredondarNota(nivelAluno($alunoId, $materiaId, $bimestre) - 0.8 + $oscilacao);
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

function mediaDoSemestre(int $alunoId, int $materiaId, int $semestre): float
{
    if ($semestre === 1) {
        return (mediaDoBimestre($alunoId, $materiaId, 1) + mediaDoBimestre($alunoId, $materiaId, 2)) / 2;
    }
    return (mediaDoBimestre($alunoId, $materiaId, 3) + mediaDoBimestre($alunoId, $materiaId, 4)) / 2;
}

function notaRecuperacaoSemestre(int $alunoId, int $materiaId, int $semestre): ?float
{
    if (mediaDoSemestre($alunoId, $materiaId, $semestre) >= MEDIA_MINIMA) {
        return null;
    }
    $sorte = unidade($alunoId, $materiaId, $semestre, 77);
    if ($sorte < 0.62) {
        return arredondarNota(6.0 + ($sorte / 0.62) * 2.0);
    }
    return arredondarNota(3.5 + (($sorte - 0.62) / 0.38) * 2.0);
}

function resultadoDoSemestre(int $alunoId, int $materiaId, int $semestre): float
{
    $media = mediaDoSemestre($alunoId, $materiaId, $semestre);
    $rec = notaRecuperacaoSemestre($alunoId, $materiaId, $semestre);
    if ($rec !== null && $rec > $media) {
        return $rec;
    }
    return $media;
}

function mediaDoAno(int $alunoId, int $materiaId): float
{
    return (resultadoDoSemestre($alunoId, $materiaId, 1) + resultadoDoSemestre($alunoId, $materiaId, 2)) / 2;
}

function notaRecuperacaoFinal(int $alunoId, int $materiaId): ?float
{
    if (mediaDoAno($alunoId, $materiaId) >= MEDIA_MINIMA) {
        return null;
    }
    $sorte = unidade($alunoId, $materiaId, 90, 91);
    if ($sorte < 0.55) {
        return arredondarNota(6.0 + ($sorte / 0.55) * 1.5);
    }
    return arredondarNota(3.5 + (($sorte - 0.55) / 0.45) * 2.0);
}

/**
 * @param list<int> $ids
 */
function listaIds(array $ids): string
{
    $limpos = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $limpos[$id] = $id;
        }
    }
    return $limpos === [] ? '' : implode(',', $limpos);
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
        'liberar_nota_aluno' => '1',
        'prazo_entrega_professor' => $prazo,
        'turmas' => $pares[0]['turmas'] ?? [],
        'pares_padrao' => $pares,
        'subeventos' => $subeventos,
    ];
}

/**
 * @return array<int,array{semanas:list<string>,bimestral:string,simulado:string,prazo_semanal:string,prazo_bimestral:string,prazo_simulado:string}>
 */
function calendarioBimestres(): array
{
    return [
        1 => [
            'semanas' => ['2025-02-17', '2025-03-03', '2025-03-17', '2025-03-31'],
            'bimestral' => '2025-04-14',
            'simulado' => '2025-03-24',
            'prazo_semanal' => '2025-04-02 18:00',
            'prazo_bimestral' => '2025-04-16 18:00',
            'prazo_simulado' => '2025-03-26 18:00',
        ],
        2 => [
            'semanas' => ['2025-05-05', '2025-05-19', '2025-06-02', '2025-06-16'],
            'bimestral' => '2025-06-23',
            'simulado' => '2025-06-09',
            'prazo_semanal' => '2025-06-18 18:00',
            'prazo_bimestral' => '2025-06-25 18:00',
            'prazo_simulado' => '2025-06-11 18:00',
        ],
        3 => [
            'semanas' => ['2025-08-04', '2025-08-18', '2025-09-01', '2025-09-15'],
            'bimestral' => '2025-09-22',
            'simulado' => '2025-09-08',
            'prazo_semanal' => '2025-09-17 18:00',
            'prazo_bimestral' => '2025-09-24 18:00',
            'prazo_simulado' => '2025-09-10 18:00',
        ],
        4 => [
            'semanas' => ['2025-10-06', '2025-10-20', '2025-11-03', '2025-11-17'],
            'bimestral' => '2025-11-24',
            'simulado' => '2025-11-10',
            'prazo_semanal' => '2025-11-19 18:00',
            'prazo_bimestral' => '2025-11-26 18:00',
            'prazo_simulado' => '2025-11-12 18:00',
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

$eventos = $db->fetchAll('SELECT id, tipo_avaliacao_id, grupo_regras_notas_id FROM eventos_avaliativos') ?: [];
$eventoIds = [];
$tipoIds = [];
$grupoIds = [];
foreach ($eventos as $evento) {
    $eventoIds[] = (int) $evento['id'];
    $tipoIds[] = (int) ($evento['tipo_avaliacao_id'] ?? 0);
    $grupoIds[] = (int) ($evento['grupo_regras_notas_id'] ?? 0);
}
$blocos = $db->fetchAll('SELECT id, tipo_avaliacao_id, grupo_regras_notas_id FROM provas_blocos') ?: [];
$blocoIds = [];
foreach ($blocos as $bloco) {
    $blocoIds[] = (int) $bloco['id'];
    $tipoIds[] = (int) ($bloco['tipo_avaliacao_id'] ?? 0);
    $grupoIds[] = (int) ($bloco['grupo_regras_notas_id'] ?? 0);
}
$blocoSql = listaIds($blocoIds);

if ($blocoSql !== '') {
    $db->query(
        'DELETE pt FROM provas_blocos_professores_turmas pt
          INNER JOIN provas_blocos_professores p ON p.id = pt.bloco_professor_id
          WHERE p.bloco_id IN (' . $blocoSql . ')'
    );
    foreach ([
        'provas_blocos_notas_lancadas',
        'provas_blocos_professores',
        'provas_blocos_quadros_notas',
        'provas_blocos_turmas',
        'provas_blocos_vinculo',
        'provas_blocos_grupos_regras',
        'provas_final',
        'provas_professores',
        'diario_aulas',
    ] as $tabela) {
        $coluna = $tabela === 'diario_aulas' ? 'evento_bloco_id' : 'bloco_id';
        $existe = $db->fetch(
            'SELECT 1 AS ok FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1',
            ['t' => $tabela, 'c' => $coluna]
        );
        if (empty($existe['ok'])) {
            continue;
        }
        $db->query('DELETE FROM `' . $tabela . '` WHERE `' . $coluna . '` IN (' . $blocoSql . ')');
    }
    $db->query('DELETE FROM provas_blocos WHERE id IN (' . $blocoSql . ')');
    println('Provas e notas removidas: ' . count($blocoIds) . ' blocos.');
}

if ($eventoIds !== []) {
    $db->query('DELETE FROM eventos_avaliativos WHERE id IN (' . listaIds($eventoIds) . ')');
    println('Eventos removidos: ' . count($eventoIds) . '.');
}

$grupoSql = listaIds($grupoIds);
if ($grupoSql !== '') {
    $tiposGrupo = $db->fetchAll(
        'SELECT id FROM grupos_regras_notas_tipos WHERE grupo_id IN (' . $grupoSql . ')'
    ) ?: [];
    $tipoGrupoSql = listaIds(array_map(static fn (array $row): int => (int) $row['id'], $tiposGrupo));
    if ($tipoGrupoSql !== '') {
        $db->query('DELETE FROM grupos_regras_notas_tipo_materias WHERE tipo_id IN (' . $tipoGrupoSql . ')');
        $db->query('DELETE FROM grupos_regras_notas_tipo_marcas WHERE tipo_id IN (' . $tipoGrupoSql . ')');
    }
    $db->query('DELETE FROM grupos_regras_notas_tipos WHERE grupo_id IN (' . $grupoSql . ')');
    $db->query('DELETE FROM grupos_regras_notas_marcas WHERE grupo_id IN (' . $grupoSql . ')');
    $db->query(
        'DELETE g FROM grupos_regras_notas g
          WHERE g.id IN (' . $grupoSql . ')
            AND NOT EXISTS (SELECT 1 FROM provas_blocos pb WHERE pb.grupo_regras_notas_id = g.id)'
    );
}

$tipoSql = listaIds($tipoIds);
if ($tipoSql !== '') {
    $db->query(
        'DELETE t FROM provas_tipos_avaliacao t
          WHERE t.id IN (' . $tipoSql . ')
            AND NOT EXISTS (SELECT 1 FROM provas_blocos pb WHERE pb.tipo_avaliacao_id = t.id)'
    );
}

$anoMantido = $db->fetch('SELECT id FROM ano_letivo WHERE ano = :ano LIMIT 1', ['ano' => ANO]);
$anoId = (int) ($anoMantido['id'] ?? 0);
if ($anoId <= 0) {
    fail('O ano letivo 2025 não existe. As matrículas dependem dele.');
}
$outrosAnos = $db->fetchAll('SELECT id, ano FROM ano_letivo WHERE id <> :id', ['id' => $anoId]) ?: [];
foreach ($outrosAnos as $outro) {
    $outroId = (int) $outro['id'];
    $usoMatricula = (int) ($db->fetch(
        'SELECT COUNT(*) AS c FROM matricula WHERE ano_letivo_id = :id',
        ['id' => $outroId]
    )['c'] ?? 0);
    $usoChamada = (int) ($db->fetch(
        'SELECT COUNT(*) AS c FROM alunos_turma_chamada WHERE ano_letivo_id = :id',
        ['id' => $outroId]
    )['c'] ?? 0);
    if ($usoMatricula > 0 || $usoChamada > 0) {
        println('Ano ' . $outro['ano'] . ' ficou, porque há matrícula ou chamada ligada a ele.');
        continue;
    }
    $db->query('DELETE FROM ano_letivo WHERE id = :id', ['id' => $outroId]);
    println('Ano letivo ' . $outro['ano'] . ' removido.');
}
$db->query(
    'UPDATE ano_letivo
        SET data_inicio = :inicio, data_fim = :fim, periodo_tipo = :tipo, ativo = 1
      WHERE id = :id',
    [
        'id' => $anoId,
        'inicio' => '2025-02-03',
        'fim' => '2025-12-19',
        'tipo' => 'bimestre',
    ]
);
$db->query(
    'UPDATE regras_academicas
        SET nome = :nome, ano_letivo = :ano
      WHERE id = :id',
    ['id' => 4, 'nome' => 'Ensino Médio 2025', 'ano' => ANO]
);
$db->query('DELETE FROM recuperacoes');
if ($db->fetch(
    'SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1',
    ['t' => 'boletim_resultados_gerados']
)) {
    $db->query('DELETE FROM boletim_resultados_gerados');
    println('Snapshots de boletim removidos.');
}

$recuperacao = new RecuperacaoConfig();
$recuperacao->criar([
    'ano_letivo' => ANO,
    'nome' => 'Recuperação semestral',
    'quando' => 'par',
    'nota_minima' => 6,
    'nota_maxima' => 10,
    'efeito' => 'maior_nota',
]);
$recuperacao->criar([
    'ano_letivo' => ANO,
    'nome' => 'Recuperação final',
    'quando' => 'final',
    'nota_minima' => 6,
    'nota_maxima' => 10,
    'efeito' => 'maior_nota',
]);
println('Recuperação semestral e final configuradas para 2025.');

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

foreach (calendarioBimestres() as $bimestre => $datas) {
    $rotulo = $bimestre . 'º bimestre';
    $planos = [
        [
            'nome' => 'Prova Semanal',
            'descricao' => 'Quatro avaliações semanais do ' . $rotulo . ' de ' . ANO . '.',
            'prazo' => $datas['prazo_semanal'],
            'media' => true,
            'subeventos' => array_map(static function (string $data, int $i): array {
                return ['titulo' => 'S' . ($i + 1), 'data' => $data];
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
            'descricao' => 'Simulado do ' . $rotulo . ' de ' . ANO . '. Não entra na média.',
            'prazo' => $datas['prazo_simulado'],
            'media' => false,
            'subeventos' => [['titulo' => 'Simulado', 'data' => $datas['simulado']]],
        ],
    ];
    foreach ($planos as $plano) {
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

$recuperacoesEvento = [
    [
        'bimestre' => 81,
        'nome' => 'Recuperação semestral',
        'descricao' => 'Recuperação de quem ficou abaixo de 6,0 na média do 1º e do 2º bimestre.',
        'data' => '2025-07-07',
        'prazo' => '2025-07-09 18:00',
        'titulo' => 'Recuperação 1º semestre',
    ],
    [
        'bimestre' => 82,
        'nome' => 'Recuperação semestral',
        'descricao' => 'Recuperação de quem ficou abaixo de 6,0 na média do 3º e do 4º bimestre.',
        'data' => '2025-12-01',
        'prazo' => '2025-12-03 18:00',
        'titulo' => 'Recuperação 2º semestre',
    ],
    [
        'bimestre' => 90,
        'nome' => 'Recuperação final',
        'descricao' => 'Recuperação final de quem ficou abaixo de 6,0 na média do ano, já aplicada a recuperação do semestre.',
        'data' => '2025-12-10',
        'prazo' => '2025-12-12 18:00',
        'titulo' => 'Recuperação final',
    ],
];
foreach ($recuperacoesEvento as $plano) {
    $res = $service->criar(
        montarPost(
            $plano['nome'],
            $plano['descricao'],
            $plano['bimestre'],
            $pares,
            [['titulo' => $plano['titulo'], 'data' => $plano['data']]],
            $plano['prazo'],
            false
        ),
        $adminId
    );
    if (empty($res['success'])) {
        fail($plano['titulo'] . ': ' . (string) ($res['error'] ?? 'falha ao criar'));
    }
    println($plano['titulo'] . ' criado (#' . (int) $res['id'] . ').');
}

$blocosNota = $db->fetchAll(
    'SELECT e.bimestre, e.nome AS evento, pb.id AS bloco_id, pb.titulo, pb.semana
       FROM eventos_avaliativos e
       INNER JOIN provas_blocos pb ON pb.evento_avaliativo_id = e.id AND pb.deleted_at IS NULL
      WHERE e.deleted_at IS NULL AND e.ano_letivo = :ano
      ORDER BY e.bimestre, e.id, pb.semana, pb.id',
    ['ano' => ANO]
) ?: [];
$vinculos = $db->fetchAll(
    'SELECT pbp.bloco_id, pbp.professor_id, pbp.materia_id
       FROM provas_blocos_professores pbp
       INNER JOIN provas_blocos pb ON pb.id = pbp.bloco_id
       INNER JOIN eventos_avaliativos e ON e.id = pb.evento_avaliativo_id
      WHERE e.deleted_at IS NULL AND e.ano_letivo = :ano',
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
$alunosSemestre = [1 => [], 2 => []];
$alunosFinal = [];
$alunosSemestreEFinal = [];
foreach ($blocosNota as $bloco) {
    $blocoId = (int) $bloco['bloco_id'];
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
            } elseif ($bimestre === 81) {
                $nota = notaRecuperacaoSemestre($alunoId, $materiaId, 1);
                $observacao = 'Abaixo de 6,0 na média do 1º semestre.';
            } elseif ($bimestre === 82) {
                $nota = notaRecuperacaoSemestre($alunoId, $materiaId, 2);
                $observacao = 'Abaixo de 6,0 na média do 2º semestre.';
            } else {
                $nota = notaRecuperacaoFinal($alunoId, $materiaId);
                $observacao = 'Abaixo de 6,0 na média do ano.';
            }
            if ($nota === null) {
                continue;
            }
            if ($bimestre === 81) {
                $alunosSemestre[1][$alunoId] = true;
            } elseif ($bimestre === 82) {
                $alunosSemestre[2][$alunoId] = true;
            } elseif ($bimestre === 90) {
                $alunosFinal[$alunoId] = true;
                if (isset($alunosSemestre[1][$alunoId]) || isset($alunosSemestre[2][$alunoId])) {
                    $alunosSemestreEFinal[$alunoId] = true;
                }
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
    println($evento . ' · ' . $bloco['titulo'] . ': ' . count($linhas) . ' notas.');
}

println('Notas inseridas: ' . $inseridas . '.');
println('Alunos na recuperação do 1º semestre: ' . count($alunosSemestre[1]) . '.');
println('Alunos na recuperação do 2º semestre: ' . count($alunosSemestre[2]) . '.');
println('Alunos na recuperação final: ' . count($alunosFinal) . '.');
println('Alunos que fizeram semestre e final: ' . count($alunosSemestreEFinal) . '.');
