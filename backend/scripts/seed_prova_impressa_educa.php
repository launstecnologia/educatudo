<?php
/**
 * Cria um evento de avaliação impressa já com a prova do professor
 * enviada para a coordenação imprimir.
 *
 * Uso (container PHP): php scripts/seed_prova_impressa_educa.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

use App\Core\Database;
use App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService;

const TENANT_DB = 'educatudo_educa';
const ANO = 2026;
const BIMESTRE = 3;
const NOME_EVENTO = 'Prova Impressa — Matemática';

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
 * @return list<array{0:string,1:list<string>,2:int}>
 */
function questoesMatematica(): array
{
    return [
        ['Quanto é 15 × 8?', ['110', '120', '125', '130', '140'], 1],
        ['Qual é a raiz quadrada de 81?', ['7', '8', '9', '10', '11'], 2],
        ['Simplifique a fração 18/24.', ['2/3', '3/4', '4/5', '5/6', '6/8'], 1],
        ['Quanto vale 2³ + 4²?', ['20', '24', '18', '16', '32'], 1],
        ['Qual é 25% de 80?', ['15', '20', '25', '30', '40'], 1],
    ];
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail('Abortado: DB_HOST=' . $host . ' não parece local.');
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
$turmas = is_array($catalogo['turmas'] ?? null) ? $catalogo['turmas'] : [];
$materias = is_array($catalogo['materias'] ?? null) ? $catalogo['materias'] : [];
$professores = is_array($catalogo['professores'] ?? null) ? $catalogo['professores'] : [];
if ($turmas === [] || $materias === []) {
    fail('Sem turma ou matéria ativa em ' . ANO . '.');
}

$turma = $turmas[0];
$turmaId = (int) ($turma['id'] ?? 0);
$turmaNome = (string) ($turma['nome'] ?? '');
if ($turmaId <= 0) {
    fail('Turma inválida.');
}

$materiaEscolhida = null;
foreach ($materias as $materia) {
    if (mb_strtolower(trim((string) ($materia['nome'] ?? ''))) === 'matemática') {
        $materiaEscolhida = $materia;
        break;
    }
}
if ($materiaEscolhida === null) {
    $materiaEscolhida = $materias[0];
}
$materiaId = (int) ($materiaEscolhida['id'] ?? 0);
$materiaNome = trim((string) ($materiaEscolhida['nome'] ?? ''));
if ($materiaId <= 0 || $materiaNome === '') {
    fail('Matéria inválida.');
}

$professorId = 0;
$professorNome = '';
$chaveMateria = mb_strtolower($materiaNome);
foreach ($professores as $professor) {
    $id = (int) ($professor['id'] ?? 0);
    $nomes = json_decode((string) ($professor['materias'] ?? '[]'), true);
    if ($id <= 0 || !is_array($nomes)) {
        continue;
    }
    foreach ($nomes as $nome) {
        if (mb_strtolower(trim((string) $nome)) === $chaveMateria) {
            $professorId = $id;
            $professorNome = (string) ($professor['nome'] ?? '');
            break 2;
        }
    }
}
if ($professorId <= 0) {
    fail('Nenhum professor ativo leciona ' . $materiaNome . '.');
}

$admin = $db->fetch('SELECT id FROM usuarios ORDER BY id ASC LIMIT 1');
$adminId = (int) ($admin['id'] ?? 1);

$existente = $db->fetch(
    'SELECT id FROM eventos_avaliativos
      WHERE deleted_at IS NULL AND ano_letivo = :ano AND bimestre = :bimestre AND nome = :nome
      LIMIT 1',
    ['ano' => ANO, 'bimestre' => BIMESTRE, 'nome' => NOME_EVENTO]
);
$eventoId = (int) ($existente['id'] ?? 0);
if ($eventoId > 0) {
    println('Evento já existe (#' . $eventoId . ').');
} else {
    $res = $service->criar([
        'nome' => NOME_EVENTO,
        'descricao' => 'Prova de ' . $materiaNome . ' elaborada pelo professor e enviada para a coordenação imprimir.',
        'ano_letivo' => ANO,
        'bimestre' => BIMESTRE,
        'sigla' => 'PIM',
        'ativo' => '1',
        'canal_origem' => 'impressa',
        'modo_offline' => 'lancamento_direto',
        'configuracao_nota' => 'professor_por_questao',
        'tipo_prova' => 'original',
        'registro_evento' => 'acertos_questoes',
        'criterio_fechamento' => 'aproveitamento_nq',
        'escala_max' => '10',
        'visivel_no_portal_aluno' => '0',
        'prazo_entrega_professor' => '2026-11-08 18:00',
        'hora_inicio' => '08:00',
        'hora_fim' => '09:30',
        'turmas' => [$turmaId],
        'pares_padrao' => [[
            'professor_id' => $professorId,
            'materia_id' => $materiaId,
            'turmas' => [$turmaId],
            'quantidade_questoes' => 5,
        ]],
        'subeventos' => [[
            'titulo' => 'Prova impressa',
            'data' => '2026-11-10',
            'hora_inicio' => '08:00',
            'hora_fim' => '09:30',
        ]],
    ], $adminId);
    if (empty($res['success'])) {
        fail('Evento: ' . (string) ($res['error'] ?? 'falha ao criar'));
    }
    $eventoId = (int) ($res['id'] ?? 0);
    println('Evento criado (#' . $eventoId . ').');
}

$bloco = $db->fetch(
    'SELECT id, formato_evento, configuracao_nota
       FROM provas_blocos
      WHERE evento_avaliativo_id = :id AND deleted_at IS NULL
      ORDER BY id ASC
      LIMIT 1',
    ['id' => $eventoId]
);
$blocoId = (int) ($bloco['id'] ?? 0);
if ($blocoId <= 0) {
    fail('O evento não gerou subevento.');
}
if ((string) ($bloco['formato_evento'] ?? '') !== 'prova_impressa') {
    $db->query(
        "UPDATE provas_blocos
            SET formato_evento = 'prova_impressa', visivel_no_portal_aluno = 0
          WHERE id = :id",
        ['id' => $blocoId]
    );
    $db->query(
        'UPDATE provas_blocos_professores
            SET quantidade_questoes = 5
          WHERE bloco_id = :bloco AND professor_id = :professor AND materia_id = :materia',
        ['bloco' => $blocoId, 'professor' => $professorId, 'materia' => $materiaId]
    );
    println('Subevento #' . $blocoId . ' ajustado para prova impressa.');
}

$prova = $db->fetch(
    'SELECT p.id, p.status
       FROM provas p
       INNER JOIN provas_blocos_vinculo v ON v.prova_id = p.id
      WHERE v.bloco_id = :bloco AND p.materia_id = :materia AND p.deleted_at IS NULL
      LIMIT 1',
    ['bloco' => $blocoId, 'materia' => $materiaId]
);
$provaId = (int) ($prova['id'] ?? 0);
if ($provaId > 0) {
    println('Prova já vinculada (#' . $provaId . ', status ' . (string) ($prova['status'] ?? '') . ').');
} else {
    $provaId = (int) $db->insert(
        'INSERT INTO provas (
            professor_id, materia_id, turma_id, titulo, descricao,
            data_inicio, data_fim, tempo_limite, valor_total,
            mostrar_resultado, permite_correcao, liberar_resultado,
            ativo, liberada, status, data_envio
         ) VALUES (
            :professor_id, :materia_id, :turma_id, :titulo, :descricao,
            :data_inicio, :data_fim, 90, 10.00,
            0, 0, :liberar_resultado,
            1, 0, :status, NOW()
         )',
        [
            'professor_id' => $professorId,
            'materia_id' => $materiaId,
            'turma_id' => $turmaId,
            'titulo' => 'Prova impressa de ' . $materiaNome,
            'descricao' => 'Enviada pelo professor para a coordenação imprimir.',
            'data_inicio' => '2026-11-10 08:00:00',
            'data_fim' => '2026-11-10 09:30:00',
            'liberar_resultado' => 'nao_liberar',
            'status' => 'enviada',
        ]
    );
    $db->insert(
        'INSERT INTO provas_turmas (prova_id, turma_id) VALUES (:prova_id, :turma_id)',
        ['prova_id' => $provaId, 'turma_id' => $turmaId]
    );
    $db->insert(
        'INSERT INTO provas_blocos_vinculo (bloco_id, prova_id, ordem) VALUES (:bloco_id, :prova_id, 0)',
        ['bloco_id' => $blocoId, 'prova_id' => $provaId]
    );

    $ordem = 1;
    foreach (questoesMatematica() as $questao) {
        $questaoId = (int) $db->insert(
            'INSERT INTO provas_questoes (prova_id, enunciado, tipo, valor, ordem)
             VALUES (:prova_id, :enunciado, :tipo, 2.00, :ordem)',
            [
                'prova_id' => $provaId,
                'enunciado' => $questao[0],
                'tipo' => 'multipla_escolha',
                'ordem' => $ordem,
            ]
        );
        foreach ($questao[1] as $indice => $texto) {
            $db->insert(
                'INSERT INTO provas_alternativas (questao_id, texto, correta, ordem)
                 VALUES (:questao_id, :texto, :correta, :ordem)',
                [
                    'questao_id' => $questaoId,
                    'texto' => $texto,
                    'correta' => $indice === $questao[2] ? 1 : 0,
                    'ordem' => $indice + 1,
                ]
            );
        }
        $ordem++;
    }
    println('Prova enviada (#' . $provaId . ') com 5 questões.');
}

$materiaHistoria = null;
foreach ($materias as $materia) {
    if (mb_strtolower(trim((string) ($materia['nome'] ?? ''))) === 'história') {
        $materiaHistoria = $materia;
        break;
    }
}
$professorHistoria = 0;
$nomeProfessorHistoria = '';
if (is_array($materiaHistoria)) {
    $chaveHistoria = 'história';
    foreach ($professores as $professor) {
        $id = (int) ($professor['id'] ?? 0);
        $nomes = json_decode((string) ($professor['materias'] ?? '[]'), true);
        if ($id <= 0 || !is_array($nomes)) {
            continue;
        }
        foreach ($nomes as $nomeMateria) {
            if (mb_strtolower(trim((string) $nomeMateria)) === $chaveHistoria) {
                $professorHistoria = $id;
                $nomeProfessorHistoria = (string) ($professor['nome'] ?? '');
                break 2;
            }
        }
    }
}
if ($professorHistoria > 0 && is_array($materiaHistoria)) {
    $materiaHistoriaId = (int) $materiaHistoria['id'];
    $jaHistoria = $db->fetch(
        'SELECT p.id
           FROM provas p
           INNER JOIN provas_blocos_vinculo v ON v.prova_id = p.id
          WHERE v.bloco_id = :bloco AND p.materia_id = :materia AND p.deleted_at IS NULL
          LIMIT 1',
        ['bloco' => $blocoId, 'materia' => $materiaHistoriaId]
    );
    if (is_array($jaHistoria)) {
        println('História já vinculada (#' . (int) $jaHistoria['id'] . ').');
    } else {
        $provaHistoriaId = (int) $db->insert(
            'INSERT INTO provas (
                professor_id, materia_id, turma_id, titulo, descricao,
                data_inicio, data_fim, tempo_limite, valor_total,
                mostrar_resultado, permite_correcao, liberar_resultado,
                ativo, liberada, status, data_envio
             ) VALUES (
                :professor_id, :materia_id, :turma_id, :titulo, :descricao,
                :data_inicio, :data_fim, 50, 8.00,
                0, 0, :liberar_resultado,
                1, 0, :status, NOW()
             )',
            [
                'professor_id' => $professorHistoria,
                'materia_id' => $materiaHistoriaId,
                'turma_id' => $turmaId,
                'titulo' => 'Prova impressa de História',
                'descricao' => 'Segunda matéria do mesmo bloco, enviada para a coordenação imprimir.',
                'data_inicio' => '2026-11-10 08:00:00',
                'data_fim' => '2026-11-10 09:30:00',
                'liberar_resultado' => 'nao_liberar',
                'status' => 'enviada',
            ]
        );
        $db->insert(
            'INSERT INTO provas_turmas (prova_id, turma_id) VALUES (:prova_id, :turma_id)',
            ['prova_id' => $provaHistoriaId, 'turma_id' => $turmaId]
        );
        $db->insert(
            'INSERT INTO provas_blocos_vinculo (bloco_id, prova_id, ordem) VALUES (:bloco_id, :prova_id, 1)',
            ['bloco_id' => $blocoId, 'prova_id' => $provaHistoriaId]
        );
        $par = $db->fetch(
            'SELECT id FROM provas_blocos_professores
              WHERE bloco_id = :bloco AND professor_id = :professor AND materia_id = :materia
              LIMIT 1',
            ['bloco' => $blocoId, 'professor' => $professorHistoria, 'materia' => $materiaHistoriaId]
        );
        $parId = (int) ($par['id'] ?? 0);
        if ($parId <= 0) {
            $parId = (int) $db->insert(
                'INSERT INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
                 VALUES (:bloco_id, :professor_id, :materia_id, 4)',
                [
                    'bloco_id' => $blocoId,
                    'professor_id' => $professorHistoria,
                    'materia_id' => $materiaHistoriaId,
                ]
            );
        }
        $db->query(
            'INSERT IGNORE INTO provas_blocos_professores_turmas (bloco_professor_id, turma_id) VALUES (:bp_id, :turma_id)',
            ['bp_id' => $parId, 'turma_id' => $turmaId]
        );
        $questoesHistoria = [
            ['Em que ano o Brasil se tornou independente?', ['1808', '1822', '1889', '1891', '1930'], 1],
            ['Quem proclamou a República no Brasil?', ['Dom Pedro I', 'Marechal Deodoro', 'Getúlio Vargas', 'Tiradentes', 'Duque de Caxias'], 1],
            ['A Inconfidência Mineira ocorreu em qual século?', ['XVI', 'XVII', 'XVIII', 'XIX', 'XX'], 2],
            ['Qual cidade foi a capital do Brasil antes de Brasília?', ['Salvador', 'Recife', 'Rio de Janeiro', 'São Paulo', 'Ouro Preto'], 2],
        ];
        $ordemHistoria = 1;
        foreach ($questoesHistoria as $questao) {
            $questaoId = (int) $db->insert(
                'INSERT INTO provas_questoes (prova_id, enunciado, tipo, valor, ordem)
                 VALUES (:prova_id, :enunciado, :tipo, 2.00, :ordem)',
                [
                    'prova_id' => $provaHistoriaId,
                    'enunciado' => $questao[0],
                    'tipo' => 'multipla_escolha',
                    'ordem' => $ordemHistoria,
                ]
            );
            foreach ($questao[1] as $indice => $texto) {
                $db->insert(
                    'INSERT INTO provas_alternativas (questao_id, texto, correta, ordem)
                     VALUES (:questao_id, :texto, :correta, :ordem)',
                    [
                        'questao_id' => $questaoId,
                        'texto' => $texto,
                        'correta' => $indice === $questao[2] ? 1 : 0,
                        'ordem' => $indice + 1,
                    ]
                );
            }
            $ordemHistoria++;
        }
        println('História enviada (#' . $provaHistoriaId . ') por ' . $nomeProfessorHistoria . '.');
    }
}

println('Pronto.');
println('Evento #' . $eventoId . ' · subevento #' . $blocoId . ' · prova #' . $provaId);
println($materiaNome . ' · ' . $professorNome . ' · turma ' . $turmaNome);
println('Coordenação: 3º trimestre de 2026 → ' . NOME_EVENTO . ' → Imprimir prova.');
