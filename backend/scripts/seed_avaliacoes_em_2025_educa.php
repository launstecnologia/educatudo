<?php
/**
 * Colégio Educa — provas do Ensino Médio 2025 (turmas 1A–3B).
 *
 * Por bimestre: 4 semanais online (S1–S4) + 1 trabalho + 1 prova bimestral
 * + grade horária + diário de classe (aulas atribuídas) + frequência
 * + faltas + entrada/saída na portaria.
 * Idempotente. Só o tenant educatudo_educa, em ambiente local.
 * Não gera boletim: depois de cada seed, monte o evento/quadro e clique em Gerar boletins.
 *
 * Uso (container PHP):
 *   php scripts/seed_avaliacoes_em_2025_educa.php --ajuda
 *   php scripts/seed_avaliacoes_em_2025_educa.php --bimestre=1
 *   php scripts/seed_avaliacoes_em_2025_educa.php --bimestre=1 --quadro="Notas Semanais"
 *   php scripts/seed_avaliacoes_em_2025_educa.php --bimestre=1 --quadro-id=7
 *   php scripts/seed_avaliacoes_em_2025_educa.php --todos
 *
 * Atalhos por bimestre:
 *   php scripts/seed_provas_1_bimestre_educa.php
 *   php scripts/seed_provas_2_bimestre_educa.php
 *   php scripts/seed_provas_3_bimestre_educa.php
 *   php scripts/seed_provas_4_bimestre_educa.php
 *
 * Apagar configuração (boletim/quadro/tipos) para refazer o ano:
 *   php scripts/reset_config_notas_educa.php --confirmar
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

ini_set('memory_limit', '768M');
set_time_limit(0);

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Models/Exams/ExamBlock.php';
require_once $basePath . '/app/Models/Exams/Exam.php';
require_once $basePath . '/app/Models/Exams/ExamBlockManualGrade.php';
require_once $basePath . '/app/Models/Exams/ExamEvaluationType.php';
require_once $basePath . '/app/Models/Education/ClassDiary.php';
require_once $basePath . '/app/Models/Education/SchoolAbsence.php';
require_once $basePath . '/app/Modulos/grupos-regras-notas/Models/GrupoRegrasNotas.php';
require_once $basePath . '/scripts/lib/SimulacaoAcademicaEduca.php';

const TENANT_DB = 'educatudo_educa';
const ANO = 2025;
const QUADRO_NOME = 'Semanais';
const QUESTOES = 5;
const VALOR_QUESTAO = 2.0;

/** @var array<int, array{prefixo:string,datas:array<string,string>}> */
const CONFIG_BIMESTRE = [
    1 => [
        'prefixo' => 'EM25',
        'datas' => [
            's1' => '2025-02-10',
            's2' => '2025-02-24',
            's3' => '2025-03-10',
            's4' => '2025-03-24',
            'trab' => '2025-03-17',
            'bim' => '2025-04-04',
        ],
    ],
    2 => [
        'prefixo' => 'EM25B2',
        'datas' => [
            's1' => '2025-05-05',
            's2' => '2025-05-19',
            's3' => '2025-06-02',
            's4' => '2025-06-16',
            'trab' => '2025-06-09',
            'bim' => '2025-06-30',
        ],
    ],
    3 => [
        'prefixo' => 'EM25B3',
        'datas' => [
            's1' => '2025-08-04',
            's2' => '2025-08-18',
            's3' => '2025-09-01',
            's4' => '2025-09-15',
            'trab' => '2025-09-08',
            'bim' => '2025-10-10',
        ],
    ],
    4 => [
        'prefixo' => 'EM25B4',
        'datas' => [
            's1' => '2025-10-20',
            's2' => '2025-11-03',
            's3' => '2025-11-17',
            's4' => '2025-12-01',
            'trab' => '2025-11-24',
            'bim' => '2025-12-12',
        ],
    ],
];

/** 5 aulas/dia × 5 dias = 25 períodos, alinhado à carga EM (LP desdobrada). */
const SLOTS_AULA = [
    ['07:30:00', '08:20:00'],
    ['08:20:00', '09:10:00'],
    ['09:30:00', '10:20:00'],
    ['10:20:00', '11:10:00'],
    ['11:10:00', '12:00:00'],
];
const GRADE_EM = [
    1 => ['Gramática', 'Gramática', 'Matemática', 'Matemática', 'História'],
    2 => ['Literatura', 'Matemática', 'Física', 'Química', 'Geografia'],
    3 => ['Interpretação de texto', 'Matemática', 'Biologia', 'Língua Inglesa', 'Filosofia'],
    4 => ['Língua Inglesa', 'História', 'Geografia', 'Física', 'Sociologia'],
    5 => ['Biologia', 'Química', 'Arte', 'Educação Física', 'Educação Física'],
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
Seed de provas do Colégio Educa (EM 2025, turmas 1A–3B).

Cada bimestre cria:
  • 4 provas semanais online (S1–S4), com respostas dos alunos
  • 1 Trabalho (nota lançada, sem questões)
  • 1 Prova Bimestral (nota lançada, sem questões)
  • Grade horária (aulas atribuídas a professor × componente)
  • Diário de classe com aulas finalizadas no período
  • Registro de frequência (presente / falta / justificativa / atraso)
  • Entrada e saída na portaria (Frequência → Presença)
  • Evento de faltas do bimestre, consolidado a partir do diário

Não gera boletim. Depois de cada seed, monte tipos/quadro/evento e clique em Gerar boletins.

Base limpa (apaga operacional e recria turmas/alunos — só se quiser recomeçar):
  docker exec php_app_educatudo php /var/www/html/scripts/preparar_base_teste_em_2025_educa.php

Uso (no container PHP):
  docker exec php_app_educatudo php /var/www/html/scripts/seed_avaliacoes_em_2025_educa.php --bimestre=1
  docker exec php_app_educatudo php /var/www/html/scripts/seed_avaliacoes_em_2025_educa.php --bimestre=1 --quadro="Notas Semanais"
  docker exec php_app_educatudo php /var/www/html/scripts/seed_avaliacoes_em_2025_educa.php --bimestre=1 --quadro-id=7
  docker exec php_app_educatudo php /var/www/html/scripts/seed_avaliacoes_em_2025_educa.php --todos

Quadro: se omitir, usa o nome "Semanais" ou o único quadro ativo cujo nome contém "semanal".
Atalhos: 1  2  3  4  (mesmo que --bimestre=N)
Idempotente: rodar de novo o mesmo bimestre não duplica.

Apagar boletim/quadro/tipos e as provas do seed para refazer os bimestres:
  docker exec php_app_educatudo php /var/www/html/scripts/reset_config_notas_educa.php --confirmar

TXT;
}

/**
 * @param list<string> $argv
 * @return array{bimestres:list<int>,quadro:string,quadro_id:int}
 */
function opcoesSeed(array $argv): array
{
    $args = array_slice($argv, 1);
    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (in_array($arg, ['--ajuda', '--help', '-h'], true)) {
            mostrarUso();
            exit(0);
        }
    }
    if ($args === []) {
        mostrarUso();
        exit(0);
    }

    $bims = [];
    $quadro = '';
    $quadroId = 0;
    $todos = false;
    foreach ($args as $arg) {
        $arg = (string) $arg;
        if ($arg === '--todos') {
            $todos = true;
            continue;
        }
        if (preg_match('/^--quadro-id=(\d+)$/', $arg, $m)) {
            $quadroId = (int) $m[1];
            continue;
        }
        if (preg_match('/^--quadro=(.*)$/', $arg, $m)) {
            $quadro = trim((string) $m[1], " \t\"'");
            continue;
        }
        if (preg_match('/^--bimestre=([1-4](?:,[1-4])*)$/', $arg, $m)) {
            foreach (explode(',', $m[1]) as $n) {
                $bims[] = (int) $n;
            }
            continue;
        }
        if (preg_match('/^[1-4]$/', $arg)) {
            $bims[] = (int) $arg;
        }
    }
    if ($todos) {
        $bims = [1, 2, 3, 4];
    }
    $bims = array_values(array_unique($bims));
    if ($bims === []) {
        fail('Informe o bimestre. Ex.: --bimestre=3  ou  --ajuda');
    }
    foreach ($bims as $bim) {
        if (!isset(CONFIG_BIMESTRE[$bim])) {
            fail('Bimestre inválido: ' . $bim . '. Use 1, 2, 3 ou 4.');
        }
    }
    sort($bims);

    return [
        'bimestres' => $bims,
        'quadro' => $quadro,
        'quadro_id' => $quadroId,
    ];
}

final class SeedAvaliacoesEm2025Educa
{
    private ExamBlock $blocos;
    private Exam $provas;
    private ExamBlockManualGrade $notas;
    private ExamEvaluationType $tipos;
    private GrupoRegrasNotas $quadroModel;
    private ClassDiary $diario;
    private SchoolAbsence $faltas;
    private int $bimestre;
    private string $prefixo;
    /** @var array<string,string> */
    private array $datas;
    private string $rotuloBim;
    private int $adminId = 0;
    private int $quadroId = 0;
    /** @var array<string,int> */
    private array $tipoIds = [];
    /** @var array<string,int> */
    private array $colunaIds = [];
    /** @var array<string,int> */
    private array $materiaPorNome = [];
    /** @var array<int,string> */
    private array $nomePorMateria = [];
    /** @var array<int,true> */
    private array $rotulos = [];
    private int $blocosNovos = 0;
    private int $blocosReusados = 0;
    private int $provasNovas = 0;
    private int $realizacoes = 0;
    private int $notasLancadas = 0;
    private int $aulasDiario = 0;
    private int $frequencias = 0;
    private int $faltasLancadas = 0;
    private int $presencas = 0;

    /** @var array{quadro?:string,quadro_id?:int} */
    private array $opts = [];

    /**
     * @param array{quadro?:string,quadro_id?:int} $opts
     */
    public function __construct(private $db, int $bimestre, array $opts = [])
    {
        $this->opts = $opts;
        if (!isset(CONFIG_BIMESTRE[$bimestre])) {
            fail('Bimestre inválido. Use 1, 2, 3 ou 4.');
        }
        $this->bimestre = $bimestre;
        $this->prefixo = CONFIG_BIMESTRE[$bimestre]['prefixo'];
        $this->datas = CONFIG_BIMESTRE[$bimestre]['datas'];
        $this->rotuloBim = $bimestre . 'º bimestre';
        $this->blocos = new ExamBlock();
        $this->provas = new Exam();
        $this->notas = new ExamBlockManualGrade();
        $this->tipos = new ExamEvaluationType();
        $this->quadroModel = new GrupoRegrasNotas();
        $this->diario = new ClassDiary();
        $this->diario->ensureSchema();
        $this->faltas = new SchoolAbsence();
        $this->faltas->ensureSchema();
    }

    public function executar(): int
    {
        $inicioSeed = microtime(true);
        println('== Seed avaliações EM 2025 B' . $this->bimestre . ' — Colégio Educa ==');
        $this->adminId = $this->resolverAdmin();
        $this->carregarMaterias();
        $this->garantirTipos();
        $this->garantirQuadro();

        $turmas = $this->turmasDoAno();
        if ($turmas === []) {
            fail('Nenhuma turma 2025 ativa.');
        }

        foreach ($turmas as $turma) {
            $this->processarTurma($turma);
        }

        $this->sincronizarEventoFaltas();
        $this->lancarEntradaSaida();

        println('');
        println('Blocos novos: ' . $this->blocosNovos . ' | reaproveitados: ' . $this->blocosReusados);
        println('Provas online criadas: ' . $this->provasNovas);
        println('Provas respondidas (aluno×prova): ' . $this->realizacoes);
        println('Notas lançadas (bimestral/trabalho): ' . $this->notasLancadas);
        println('Diário: ' . $this->aulasDiario . ' aulas | frequência: ' . $this->frequencias . ' | faltas lançadas: ' . $this->faltasLancadas);
        println('Portaria (entrada/saída): ' . $this->presencas);
        println('Tempo total: ' . number_format(microtime(true) - $inicioSeed, 1) . 's');
        println('Login aluno: ana.almeida.1a01@educa.local  senha: Teste@123');
        println('Minhas Provas: http://educa.localhost/aluno/provas');
        $proximo = $this->bimestre < 4 ? ($this->bimestre + 1) : 0;
        println('Agora: monte o evento/quadro do ' . $this->rotuloBim . ' e clique em Gerar boletins.');
        if ($proximo > 0) {
            println('Próximo seed: php scripts/seed_avaliacoes_em_2025_educa.php --bimestre=' . $proximo);
        }
        return 0;
    }

    /** @param array{id:int,nome:string} $turma */
    private function processarTurma(array $turma): void
    {
        $turmaId = (int) $turma['id'];
        $nomeTurma = (string) $turma['nome'];
        $alunos = $this->alunosDaTurma($turmaId);
        $grade = $this->gradeDaTurma($turmaId);
        if ($alunos === [] || $grade === []) {
            println('  · ' . $nomeTurma . ' — pulada (alunos=' . count($alunos) . ' grade=' . count($grade) . ')');
            return;
        }
        $t0 = microtime(true);
        println('  · ' . $nomeTurma . ' — ' . count($alunos) . ' alunos, ' . count($grade) . ' componentes');

        $this->garantirGradeHoraria($turmaId, $grade);
        $this->lancarDiarioFrequencia($turmaId);

        foreach ([1, 2, 3, 4] as $semana) {
            $this->garantirSemanal($turmaId, $nomeTurma, $semana, $grade, $alunos);
        }
        $this->garantirLancamento(
            $turmaId,
            $nomeTurma,
            'Prova Bimestral',
            $this->datas['bim'],
            $this->tipoIds['bim'],
            $this->colunaIds['bim'] ?? 0,
            0,
            'p1',
            $grade,
            $alunos
        );
        $this->garantirLancamento(
            $turmaId,
            $nomeTurma,
            'Trabalho',
            $this->datas['trab'],
            $this->tipoIds['trab'],
            $this->colunaIds['trab'] ?? 0,
            0,
            'trab',
            $grade,
            $alunos
        );
        println('      ' . $nomeTurma . ' em ' . number_format(microtime(true) - $t0, 1) . 's');
    }

    /**
     * @param list<array{professor_id:int,materia_id:int}> $grade
     * @param list<int> $alunos
     */
    private function garantirSemanal(int $turmaId, string $nomeTurma, int $semana, array $grade, array $alunos): void
    {
        $data = $this->datas['s' . $semana];
        $titulo = $this->prefixo . ' · ' . $nomeTurma . ' · S' . $semana . ' · Prova Semanal';
        $colunaId = (int) ($this->colunaIds['s' . $semana] ?? 0);
        $blocoId = $this->garantirBloco(
            $titulo,
            'Prova semanal S' . $semana . ' do ' . $this->rotuloBim . ' — o aluno faz no sistema.',
            $data,
            'online_questoes',
            'professor_por_questao',
            $this->tipoIds['semanal'],
            $semana,
            $colunaId,
            $turmaId,
            $grade,
            5
        );
        $this->db->query(
            'UPDATE provas_blocos
                SET gabarito_liberado = 1, liberado = 1, ativo = 1, status = :st
              WHERE id = :id',
            ['st' => 'liberado', 'id' => $blocoId]
        );

        $provaPorMateria = $this->provasDoBloco($blocoId);
        $novas = [];
        $ordem = 1;
        foreach ($grade as $item) {
            $mid = (int) $item['materia_id'];
            if (!isset($provaPorMateria[$mid])) {
                $novas[] = [
                    'professor_id' => (int) $item['professor_id'],
                    'materia_id' => $mid,
                    'ordem' => $ordem,
                    'nome' => $this->nomePorMateria[$mid] ?? ('Matéria #' . $mid),
                ];
            }
            $ordem++;
        }
        if ($novas !== []) {
            $this->criarProvasOnlineLote($blocoId, $turmaId, $nomeTurma, $semana, $data, $novas);
            $provaPorMateria = $this->provasDoBloco($blocoId);
        }

        $realizacoes = [];
        $respostas = [];
        $this->simularRespostasDoBloco($blocoId, $provaPorMateria, $grade, $alunos, $semana, $data, $realizacoes, $respostas);
        $this->emTransacao(function () use ($realizacoes, $respostas): void {
            $this->inserirLote(
                'provas_realizacoes',
                ['prova_id', 'aluno_id', 'iniciado_em', 'finalizado_em', 'tempo_gasto', 'nota', 'status'],
                $realizacoes,
                'ON DUPLICATE KEY UPDATE nota = VALUES(nota), status = VALUES(status), finalizado_em = VALUES(finalizado_em)'
            );
            $this->inserirLote(
                'provas_respostas',
                ['prova_id', 'aluno_id', 'questao_id', 'alternativa_id', 'correta', 'pontuacao'],
                $respostas,
                'ON DUPLICATE KEY UPDATE alternativa_id = VALUES(alternativa_id), correta = VALUES(correta), pontuacao = VALUES(pontuacao)'
            );
        });
        $this->realizacoes += count($realizacoes);
    }

    /**
     * @param list<array{professor_id:int,materia_id:int}> $grade
     * @param list<int> $alunos
     */
    private function garantirLancamento(
        int $turmaId,
        string $nomeTurma,
        string $rotulo,
        string $data,
        int $tipoId,
        int $colunaId,
        int $semana,
        string $tipoEv,
        array $grade,
        array $alunos
    ): void {
        $titulo = $this->prefixo . ' · ' . $nomeTurma . ' · ' . $rotulo;
        $blocoId = $this->garantirBloco(
            $titulo,
            $rotulo . ' do ' . $this->rotuloBim . ' — nota lançada pela escola (sem questões).',
            $data,
            'lancamento_nota',
            'coordenacao_calcula',
            $tipoId,
            $semana,
            $colunaId,
            $turmaId,
            $grade,
            0
        );
        $lancadas = [];
        $finais = [];
        foreach ($grade as $item) {
            $pid = (int) $item['professor_id'];
            $mid = (int) $item['materia_id'];
            foreach ($alunos as $aid) {
                $nota = SimulacaoAcademicaEduca::nota($aid, $mid, $this->bimestre, $tipoEv);
                $lancadas[] = [$blocoId, $pid, $mid, $turmaId, $aid, $nota, null];
                if ($tipoId > 0) {
                    $finais[] = [$tipoId, $aid, $mid, $turmaId, ANO, $this->bimestre, $nota, 0, 0, 1];
                }
            }
        }
        $this->emTransacao(function () use ($lancadas, $finais): void {
            $this->inserirLote(
                'provas_blocos_notas_lancadas',
                ['bloco_id', 'professor_id', 'materia_id', 'turma_id', 'aluno_id', 'nota', 'observacao'],
                $lancadas,
                'ON DUPLICATE KEY UPDATE nota = VALUES(nota), observacao = VALUES(observacao), updated_at = CURRENT_TIMESTAMP'
            );
            if ($finais !== [] && $this->temTabela('notas_tipo_finais')) {
                $this->inserirLote(
                    'notas_tipo_finais',
                    ['tipo_avaliacao_id', 'aluno_id', 'materia_id', 'turma_id', 'ano_letivo', 'periodo', 'nota_final', 'acertos_soma', 'questoes_soma', 'eventos_qtd'],
                    $finais,
                    'ON DUPLICATE KEY UPDATE nota_final = VALUES(nota_final), eventos_qtd = VALUES(eventos_qtd), calculado_em = NOW()'
                );
            }
        });
        $this->notasLancadas += count($lancadas);
    }

    /**
     * @param list<array{professor_id:int,materia_id:int}> $grade
     */
    private function garantirBloco(
        string $titulo,
        string $descricao,
        string $data,
        string $formato,
        string $configNota,
        int $tipoId,
        int $semana,
        int $colunaId,
        int $turmaId,
        array $grade,
        int $qtdQuestoes
    ): int {
        $exist = $this->db->fetch(
            'SELECT id FROM provas_blocos WHERE titulo = :t AND deleted_at IS NULL LIMIT 1',
            ['t' => $titulo]
        );
        if ($exist) {
            $id = (int) $exist['id'];
            $this->vincularProfessoresBloco($id, $turmaId, $grade, $qtdQuestoes);
            $this->blocosReusados++;
            return $id;
        }

        $payload = [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'data_prova' => $data,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '09:30:00',
            'criado_por' => $this->adminId,
            'tipo_prova' => 'original',
            'formato_evento' => $formato,
            'configuracao_nota' => $configNota,
            'liberar_gabarito' => 'imediatamente',
            'ano_letivo' => ANO,
            'bimestre' => $this->bimestre,
            'tipo_avaliacao_id' => $tipoId > 0 ? $tipoId : null,
            'semana' => $semana >= 1 && $semana <= 20 ? $semana : null,
            'grupo_regras_notas_id' => $this->quadroId > 0 ? $this->quadroId : null,
            'grupo_regras_marca_id' => $colunaId > 0 ? $colunaId : null,
            'grupos_regras_vinculos' => $this->quadroId > 0 && $colunaId > 0
                ? [['grupo_id' => $this->quadroId, 'marca_id' => $colunaId]]
                : [],
            'liberado' => 1,
            'ativo' => 1,
            'visivel_no_portal_aluno' => 1,
            'nota_unica_todas_materias' => 0,
            'turma_id' => $turmaId,
            'turmas' => [$turmaId],
            'professores' => [],
            'professor_id' => (int) ($grade[0]['professor_id'] ?? 0),
        ];
        $id = (int) $this->blocos->create($payload);
        if ($id <= 0) {
            fail('Não criou o bloco: ' . $titulo);
        }
        $this->vincularProfessoresBloco($id, $turmaId, $grade, $qtdQuestoes);
        $this->blocosNovos++;
        return $id;
    }

    /**
     * @param list<array{professor_id:int,materia_id:int}> $grade
     */
    private function vincularProfessoresBloco(int $blocoId, int $turmaId, array $grade, int $qtdQuestoes): void
    {
        $linhas = [];
        foreach ($grade as $item) {
            $linhas[] = [$blocoId, (int) $item['professor_id'], (int) $item['materia_id'], $qtdQuestoes];
        }
        $this->inserirLote(
            'provas_blocos_professores',
            ['bloco_id', 'professor_id', 'materia_id', 'quantidade_questoes'],
            $linhas,
            'ON DUPLICATE KEY UPDATE quantidade_questoes = VALUES(quantidade_questoes)'
        );
        $bps = $this->db->fetchAll(
            'SELECT id FROM provas_blocos_professores WHERE bloco_id = :b',
            ['b' => $blocoId]
        ) ?: [];
        $turmas = [];
        foreach ($bps as $bp) {
            $turmas[] = [(int) $bp['id'], $turmaId];
        }
        $this->inserirLote(
            'provas_blocos_professores_turmas',
            ['bloco_professor_id', 'turma_id'],
            $turmas,
            'ON DUPLICATE KEY UPDATE turma_id = VALUES(turma_id)'
        );
    }

    /** @return array<int,int> materia_id => prova_id */
    private function provasDoBloco(int $blocoId): array
    {
        $map = [];
        foreach ($this->db->fetchAll(
            'SELECT p.id, p.materia_id
               FROM provas p
               INNER JOIN provas_blocos_vinculo v ON v.prova_id = p.id
              WHERE v.bloco_id = :b AND p.deleted_at IS NULL',
            ['b' => $blocoId]
        ) ?: [] as $row) {
            $map[(int) $row['materia_id']] = (int) $row['id'];
        }
        return $map;
    }

    /**
     * @param list<array{professor_id:int,materia_id:int,ordem:int,nome:string}> $novas
     */
    private function criarProvasOnlineLote(
        int $blocoId,
        int $turmaId,
        string $nomeTurma,
        int $semana,
        string $data,
        array $novas
    ): void {
        $inicio = $data . ' 07:00:00';
        $fim = date('Y-m-d 23:59:59', strtotime($data . ' +6 days'));
        $cols = [
            'professor_id', 'materia_id', 'turma_id', 'titulo', 'descricao',
            'data_inicio', 'data_fim', 'tempo_limite', 'valor_total',
            'mostrar_resultado', 'permite_correcao', 'liberar_resultado',
            'ativo', 'liberada', 'status',
        ];
        $comDataProva = $this->temColuna('provas', 'data_prova');
        if ($comDataProva) {
            $cols[] = 'data_prova';
        }
        $linhas = [];
        $titulos = [];
        foreach ($novas as $n) {
            $titulo = $this->prefixo . ' · ' . $nomeTurma . ' · S' . $semana . ' · ' . $n['nome'];
            $titulos[] = $titulo;
            $row = [
                $n['professor_id'],
                $n['materia_id'],
                $turmaId,
                $titulo,
                'Prova semanal de ' . $n['nome'] . ' (5 questões objetivas).',
                $inicio,
                $fim,
                60,
                10.00,
                1,
                0,
                'imediatamente',
                1,
                1,
                'aprovada',
            ];
            if ($comDataProva) {
                $row[] = $data;
            }
            $linhas[] = $row;
        }
        $this->emTransacao(function () use ($blocoId, $turmaId, $cols, $linhas, $titulos, $novas): void {
            $this->inserirLote('provas', $cols, $linhas);
            $primeiroId = (int) $this->db->lastInsertId();
            [$inSql, $inParams] = $this->placeholdersIn('t', $titulos);
            $inParams['turma'] = $turmaId;
            $sql = 'SELECT id, materia_id
                      FROM provas
                     WHERE turma_id = :turma
                       AND titulo IN (' . $inSql . ')
                       AND deleted_at IS NULL';
            if ($primeiroId > 0) {
                $sql .= ' AND id >= :min_id';
                $inParams['min_id'] = $primeiroId;
            }
            $sql .= ' ORDER BY id ASC';
            $criadas = $this->db->fetchAll($sql, $inParams) ?: [];
            $idPorMateria = [];
            foreach ($criadas as $r) {
                $mid = (int) $r['materia_id'];
                if (!isset($idPorMateria[$mid])) {
                    $idPorMateria[$mid] = (int) $r['id'];
                }
            }

            $vinculos = [];
            $turmas = [];
            $provaIds = [];
            foreach ($novas as $n) {
                $pid = (int) ($idPorMateria[$n['materia_id']] ?? 0);
                if ($pid <= 0) {
                    fail('Não criou a prova: ' . $n['nome']);
                }
                $vinculos[] = [$blocoId, $pid, $n['ordem']];
                $turmas[] = [$pid, $turmaId];
                $provaIds[] = $pid;
            }
            $this->inserirLote(
                'provas_blocos_vinculo',
                ['bloco_id', 'prova_id', 'ordem'],
                $vinculos,
                'ON DUPLICATE KEY UPDATE ordem = VALUES(ordem)'
            );
            $this->inserirLote(
                'provas_turmas',
                ['prova_id', 'turma_id'],
                $turmas,
                'ON DUPLICATE KEY UPDATE turma_id = VALUES(turma_id)'
            );
            $this->inserirQuestoesLote($provaIds);
        });
        $this->provasNovas += count($novas);
    }

    /** @param list<int> $provaIds */
    private function inserirQuestoesLote(array $provaIds): void
    {
        $provaIds = array_values(array_unique(array_filter(array_map('intval', $provaIds))));
        if ($provaIds === []) {
            return;
        }
        [$inSql, $inParams] = $this->placeholdersIn('p', $provaIds);
        $jaTemQuestao = [];
        foreach ($this->db->fetchAll(
            'SELECT DISTINCT prova_id FROM provas_questoes WHERE prova_id IN (' . $inSql . ')',
            $inParams
        ) ?: [] as $row) {
            $jaTemQuestao[(int) $row['prova_id']] = true;
        }
        $jaTemAlt = [];
        foreach ($this->db->fetchAll(
            'SELECT DISTINCT q.prova_id
               FROM provas_alternativas a
               INNER JOIN provas_questoes q ON q.id = a.questao_id
              WHERE q.prova_id IN (' . $inSql . ')',
            $inParams
        ) ?: [] as $row) {
            $jaTemAlt[(int) $row['prova_id']] = true;
        }
        $infos = $this->db->fetchAll(
            'SELECT id, materia_id FROM provas WHERE id IN (' . $inSql . ')',
            $inParams
        ) ?: [];
        $bancoPorProva = [];
        $linhasQ = [];
        foreach ($infos as $p) {
            $provaId = (int) $p['id'];
            $nome = $this->nomePorMateria[(int) $p['materia_id']] ?? ('Matéria #' . (int) $p['materia_id']);
            $banco = bancoQuestoesDaMateria($nome);
            if (!isset($jaTemQuestao[$provaId])) {
                $bancoPorProva[$provaId] = $banco;
                foreach ($banco as $i => $q) {
                    $linhasQ[] = [$provaId, $q[0], 'multipla_escolha', VALOR_QUESTAO, $i + 1, 'Gabarito da prova semanal.'];
                }
            } elseif (!isset($jaTemAlt[$provaId])) {
                $bancoPorProva[$provaId] = $banco;
            }
        }
        $this->inserirLote(
            'provas_questoes',
            ['prova_id', 'enunciado', 'tipo', 'valor', 'ordem', 'explicacao'],
            $linhasQ
        );
        if ($bancoPorProva === []) {
            return;
        }
        $criadas = $this->db->fetchAll(
            'SELECT id, prova_id, ordem FROM provas_questoes WHERE prova_id IN (' . $inSql . ') ORDER BY prova_id ASC, ordem ASC, id ASC',
            $inParams
        ) ?: [];
        $alts = [];
        foreach ($criadas as $row) {
            $provaId = (int) $row['prova_id'];
            if (!isset($bancoPorProva[$provaId])) {
                continue;
            }
            $i = (int) ($row['ordem'] ?? 0) - 1;
            $banco = $bancoPorProva[$provaId];
            if (!isset($banco[$i])) {
                continue;
            }
            $q = $banco[$i];
            $qid = (int) $row['id'];
            foreach ($q[1] as $j => $texto) {
                $alts[] = [$qid, $texto, $j === (int) $q[2] ? 1 : 0, $j + 1];
            }
        }
        $this->inserirLote(
            'provas_alternativas',
            ['questao_id', 'texto', 'correta', 'ordem'],
            $alts
        );
    }

    /**
     * @param array<int,int> $provaPorMateria
     * @param list<array{professor_id:int,materia_id:int}> $grade
     * @param list<int> $alunos
     * @param list<list<mixed>> $realizacoes
     * @param list<list<mixed>> $respostas
     */
    private function simularRespostasDoBloco(
        int $blocoId,
        array $provaPorMateria,
        array $grade,
        array $alunos,
        int $semana,
        string $data,
        array &$realizacoes,
        array &$respostas
    ): void {
        $questoesPorProva = [];
        foreach ($this->db->fetchAll(
            'SELECT q.id, q.prova_id, q.valor, q.ordem
               FROM provas_questoes q
               INNER JOIN provas_blocos_vinculo v ON v.prova_id = q.prova_id
              WHERE v.bloco_id = :b
              ORDER BY q.prova_id ASC, q.ordem ASC, q.id ASC',
            ['b' => $blocoId]
        ) ?: [] as $q) {
            $questoesPorProva[(int) $q['prova_id']][] = $q;
        }
        $altsPorQ = [];
        foreach ($this->db->fetchAll(
            'SELECT a.questao_id, a.id, a.correta
               FROM provas_alternativas a
               INNER JOIN provas_questoes q ON q.id = a.questao_id
               INNER JOIN provas_blocos_vinculo v ON v.prova_id = q.prova_id
              WHERE v.bloco_id = :b
              ORDER BY a.questao_id ASC, a.ordem ASC, a.id ASC',
            ['b' => $blocoId]
        ) ?: [] as $a) {
            $qid = (int) $a['questao_id'];
            if (!isset($altsPorQ[$qid])) {
                $altsPorQ[$qid] = ['correta' => 0, 'errada' => 0];
            }
            if ((int) $a['correta'] === 1) {
                $altsPorQ[$qid]['correta'] = (int) $a['id'];
            } elseif ($altsPorQ[$qid]['errada'] === 0) {
                $altsPorQ[$qid]['errada'] = (int) $a['id'];
            }
        }
        $jaFez = [];
        foreach ($this->db->fetchAll(
            'SELECT r.prova_id, r.aluno_id
               FROM provas_realizacoes r
               INNER JOIN provas_blocos_vinculo v ON v.prova_id = r.prova_id
              WHERE v.bloco_id = :b',
            ['b' => $blocoId]
        ) ?: [] as $row) {
            $jaFez[(int) $row['prova_id']][(int) $row['aluno_id']] = true;
        }

        foreach ($grade as $item) {
            $mid = (int) $item['materia_id'];
            $provaId = (int) ($provaPorMateria[$mid] ?? 0);
            $questoes = $questoesPorProva[$provaId] ?? [];
            if ($provaId <= 0 || $questoes === []) {
                continue;
            }
            foreach ($questoes as $q) {
                $qid = (int) $q['id'];
                $altsPorQ[$qid]['valor'] = (float) $q['valor'];
            }
            foreach ($alunos as $alunoId) {
                if (isset($jaFez[$provaId][$alunoId])) {
                    continue;
                }
                if (SimulacaoAcademicaEduca::perfil($alunoId) === 'faltoso' && $semana === 4) {
                    continue;
                }
                $notaAlvo = SimulacaoAcademicaEduca::nota($alunoId, $mid, $this->bimestre, $semana === 2 ? 'atv' : 'p1');
                $nota = 0.0;
                $minIni = 8 + ($alunoId % 40);
                $iniciado = date('Y-m-d H:i:s', strtotime($data . ' 08:' . str_pad((string) $minIni, 2, '0', STR_PAD_LEFT) . ':00'));
                $finalizado = date('Y-m-d H:i:s', strtotime($iniciado . ' +22 minutes'));
                $idx = 0;
                foreach ($questoes as $q) {
                    $qid = (int) $q['id'];
                    $meta = $altsPorQ[$qid];
                    $acertou = $this->acertouQuestao($alunoId, $mid, $semana, $idx, $notaAlvo);
                    $alt = $acertou ? (int) $meta['correta'] : (int) $meta['errada'];
                    $pts = $acertou ? (float) ($meta['valor'] ?? 0) : 0.0;
                    if ($acertou) {
                        $nota += $pts;
                    }
                    $respostas[] = [$provaId, $alunoId, $qid, $alt > 0 ? $alt : null, $acertou ? 1 : 0, $pts];
                    $idx++;
                }
                $realizacoes[] = [$provaId, $alunoId, $iniciado, $finalizado, 22, round($nota, 2), 'finalizado'];
            }
        }
    }

    /**
     * INSERT INTO t (...) VALUES (1,'a'),(2,'b'),... — um round-trip por lote.
     *
     * @param list<string> $colunas
     * @param list<list<mixed>> $linhas
     */
    private function inserirLote(string $tabela, array $colunas, array $linhas, string $onDup = ''): void
    {
        if ($linhas === [] || $colunas === []) {
            return;
        }
        if (!preg_match('/^[a-z0-9_]+$/', $tabela)) {
            fail('Tabela inválida no lote: ' . $tabela);
        }
        foreach ($colunas as $col) {
            if (!preg_match('/^[a-z0-9_]+$/', $col)) {
                fail('Coluna inválida no lote: ' . $col);
            }
        }
        $pdo = $this->db->getPdo();
        foreach (array_chunk($linhas, 1000) as $parte) {
            $valores = [];
            foreach ($parte as $row) {
                $cells = [];
                foreach (array_values($row) as $val) {
                    $cells[] = $this->sqlLiteral($val);
                }
                $valores[] = '(' . implode(',', $cells) . ')';
            }
            $sql = 'INSERT INTO `' . $tabela . '` (`' . implode('`,`', $colunas) . '`) VALUES ' . implode(',', $valores);
            if ($onDup !== '') {
                $sql .= ' ' . $onDup;
            }
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                fail('Falha no INSERT em lote de ' . $tabela . ': ' . $e->getMessage());
            }
        }
    }

    private function sqlLiteral(mixed $val): string
    {
        if ($val === null) {
            return 'NULL';
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        if (is_int($val)) {
            return (string) $val;
        }
        if (is_float($val)) {
            if (!is_finite($val)) {
                fail('Número inválido no lote.');
            }
            return (string) $val;
        }
        $quoted = $this->db->getPdo()->quote((string) $val);
        if ($quoted === false) {
            fail('Falha ao escapar valor no lote.');
        }
        return $quoted;
    }

    private function emTransacao(callable $fn): void
    {
        $this->db->beginTransaction();
        try {
            $fn();
            if ($this->db->inTransaction()) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    /**
     * @param list<mixed> $valores
     * @return array{0:string,1:array<string,mixed>}
     */
    private function placeholdersIn(string $prefixo, array $valores): array
    {
        $ph = [];
        $params = [];
        foreach (array_values($valores) as $i => $v) {
            $k = $prefixo . $i;
            $ph[] = ':' . $k;
            $params[$k] = $v;
        }
        return [implode(',', $ph), $params];
    }

    private function acertouQuestao(int $alunoId, int $materiaId, int $semana, int $idx, float $notaAlvo): bool
    {
        $chance = max(0.12, min(0.97, $notaAlvo / 10));
        $u = (($alunoId * 31) + ($materiaId * 17) + ($semana * 13) + ($idx * 7) + ($this->bimestre * 19)) % 1000;
        return ($u / 1000) < $chance;
    }

    private function resolverAdmin(): int
    {
        $row = $this->db->fetch(
            "SELECT id FROM usuarios
              WHERE tipo = 'admin_escola' AND ativo = 1
              ORDER BY FIELD(perfil_admin, 'dev', 'diretor', 'coordenador') ASC, id ASC
              LIMIT 1"
        );
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            fail('Nenhum admin ativo.');
        }
        return $id;
    }

    private function carregarMaterias(): void
    {
        $rows = $this->db->fetchAll('SELECT id, nome, pai_id FROM materias WHERE ativo = 1') ?: [];
        $filhosPorPai = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $nome = (string) $r['nome'];
            $this->materiaPorNome[mb_strtolower($nome)] = $id;
            $this->nomePorMateria[$id] = $nome;
            $pai = (int) ($r['pai_id'] ?? 0);
            if ($pai > 0) {
                $filhosPorPai[$pai] = true;
            }
        }
        $this->rotulos = $filhosPorPai;
    }

    private function garantirTipos(): void
    {
        $defs = [
            'semanal' => [
                'nome' => 'Prova Semanal',
                'descricao' => 'Prova semanal feita no sistema (questões, acertos e erros).',
                'chave_quadro' => 'semanal',
                'origem' => 'prova_online',
                'registro_evento' => 'acertos_questoes',
                'criterio_fechamento' => 'aproveitamento_nq',
                'quantidade_eventos_esperada' => 4,
            ],
            'bim' => [
                'nome' => 'Prova Bimestral',
                'descricao' => 'Avaliação principal do bimestre (nota lançada).',
                'chave_quadro' => 'prova_bim',
                'origem' => 'lancamento_direto',
                'registro_evento' => 'nota',
                'criterio_fechamento' => 'ultima',
                'quantidade_eventos_esperada' => 1,
            ],
            'trab' => [
                'nome' => 'Trabalho',
                'descricao' => 'Trabalho do bimestre (nota lançada).',
                'chave_quadro' => null,
                'origem' => 'lancamento_direto',
                'registro_evento' => 'nota',
                'criterio_fechamento' => 'ultima',
                'quantidade_eventos_esperada' => 1,
            ],
        ];
        foreach ($defs as $slug => $def) {
            $row = $this->db->fetch(
                'SELECT * FROM provas_tipos_avaliacao WHERE LOWER(nome) = LOWER(:n) AND deleted_at IS NULL LIMIT 1',
                ['n' => $def['nome']]
            );
            $payload = $def + ['ativo' => 1, 'escala_max' => 10];
            if ($row) {
                $id = (int) $row['id'];
                $this->tipos->update($id, $payload);
                $this->tipoIds[$slug] = $id;
            } else {
                $this->tipoIds[$slug] = $this->tipos->create($payload);
            }
        }
        println('  tipos: semanal=' . $this->tipoIds['semanal'] . ' bim=' . $this->tipoIds['bim'] . ' trab=' . $this->tipoIds['trab']);
    }

    private function garantirQuadro(): void
    {
        if (!$this->quadroModel->tabelasProntas()) {
            fail('Rode as migrations do Quadro de Notas no Master.');
        }
        $escolhido = $this->resolverQuadro();
        $this->quadroId = (int) $escolhido['id'];
        $cols = $this->db->fetchAll(
            'SELECT id, codigo, numero, nome FROM quadros_notas_colunas WHERE grupo_id = :g ORDER BY ordem, numero, id',
            ['g' => $this->quadroId]
        ) ?: [];
        foreach ($cols as $c) {
            $cod = strtolower((string) $c['codigo']);
            $num = (int) $c['numero'];
            if (preg_match('/^s([1-8])$/', $cod, $m)) {
                $this->colunaIds['s' . $m[1]] = (int) $c['id'];
            } elseif ($num >= 1 && $num <= 8 && !isset($this->colunaIds['s' . $num])) {
                $this->colunaIds['s' . $num] = (int) $c['id'];
            }
            if (in_array($cod, ['bim', 'prova_bim', 'bimestral'], true) || stripos((string) $c['nome'], 'bimestral') !== false) {
                $this->colunaIds['bim'] = (int) $c['id'];
            }
            if (in_array($cod, ['trab', 'trabalho'], true) || strcasecmp((string) $c['nome'], 'Trabalho') === 0) {
                $this->colunaIds['trab'] = (int) $c['id'];
            }
        }
        $ordem = count($cols);
        if (empty($this->colunaIds['bim'])) {
            $this->colunaIds['bim'] = $this->quadroModel->inserirMarca(
                $this->quadroId,
                'bim',
                'Prova Bimestral',
                9,
                $ordem++,
                ['papel' => 'lancamento', 'tipo_nota_id' => $this->tipoIds['bim'], 'vai_para_boletim' => 1]
            );
        }
        if (empty($this->colunaIds['trab'])) {
            $this->colunaIds['trab'] = $this->quadroModel->inserirMarca(
                $this->quadroId,
                'trab',
                'Trabalho',
                10,
                $ordem,
                ['papel' => 'lancamento', 'tipo_nota_id' => $this->tipoIds['trab'], 'vai_para_boletim' => 1]
            );
        }
        println('  quadro #' . $this->quadroId . ' "' . $escolhido['nome'] . '" colunas S1–S4 + bimestral + trabalho');
    }

    /**
     * @return array{id:int,nome:string}
     */
    private function resolverQuadro(): array
    {
        $ativos = $this->db->fetchAll(
            'SELECT id, nome FROM quadros_notas WHERE ativo = 1 ORDER BY id'
        ) ?: [];
        if ($ativos === []) {
            fail('Nenhum quadro ativo. Cadastre em /admin/quadros-notas.');
        }

        $idOpt = (int) ($this->opts['quadro_id'] ?? 0);
        $nomeOpt = trim((string) ($this->opts['quadro'] ?? ''));

        if ($idOpt > 0) {
            foreach ($ativos as $q) {
                if ((int) $q['id'] === $idOpt) {
                    return ['id' => $idOpt, 'nome' => (string) $q['nome']];
                }
            }
            fail('Quadro #' . $idOpt . ' não encontrado ou inativo.' . PHP_EOL . $this->dicaQuadros($ativos));
        }

        $busca = $nomeOpt !== '' ? $nomeOpt : QUADRO_NOME;
        $hit = $this->filtrarQuadrosPorNome($ativos, $busca);
        if (count($hit) === 1) {
            return ['id' => (int) $hit[0]['id'], 'nome' => (string) $hit[0]['nome']];
        }
        if (count($hit) > 1) {
            fail('Há mais de um quadro com "' . $busca . '". Use --quadro-id=N.' . PHP_EOL . $this->dicaQuadros($hit));
        }

        if ($nomeOpt === '') {
            $semanal = $this->filtrarQuadrosPorNome($ativos, 'semanal');
            if (count($semanal) === 1) {
                return ['id' => (int) $semanal[0]['id'], 'nome' => (string) $semanal[0]['nome']];
            }
            if (count($ativos) === 1) {
                return ['id' => (int) $ativos[0]['id'], 'nome' => (string) $ativos[0]['nome']];
            }
            $comS = $this->quadrosComSemanas($ativos);
            if (count($comS) === 1) {
                return ['id' => (int) $comS[0]['id'], 'nome' => (string) $comS[0]['nome']];
            }
            fail('Quadro "' . QUADRO_NOME . '" não encontrado. Use --quadro="Nome" ou --quadro-id=N.' . PHP_EOL . $this->dicaQuadros($ativos));
        }

        fail('Quadro "' . $nomeOpt . '" não encontrado. Use --quadro-id=N.' . PHP_EOL . $this->dicaQuadros($ativos));
    }

    /**
     * @param list<array{id:int|string,nome:string}> $lista
     * @return list<array{id:int|string,nome:string}>
     */
    private function filtrarQuadrosPorNome(array $lista, string $busca): array
    {
        $busca = mb_strtolower(trim($busca), 'UTF-8');
        if ($busca === '') {
            return [];
        }
        $exatos = [];
        $parciais = [];
        foreach ($lista as $q) {
            $nome = mb_strtolower((string) ($q['nome'] ?? ''), 'UTF-8');
            if ($nome === $busca) {
                $exatos[] = $q;
            } elseif (str_contains($nome, $busca)) {
                $parciais[] = $q;
            }
        }
        return $exatos !== [] ? $exatos : $parciais;
    }

    /**
     * @param list<array{id:int|string,nome:string}> $lista
     * @return list<array{id:int|string,nome:string}>
     */
    private function quadrosComSemanas(array $lista): array
    {
        $out = [];
        foreach ($lista as $q) {
            $id = (int) ($q['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $cols = $this->db->fetchAll(
                'SELECT codigo, numero FROM quadros_notas_colunas WHERE grupo_id = :g',
                ['g' => $id]
            ) ?: [];
            $ok = [];
            foreach ($cols as $c) {
                $cod = strtolower((string) ($c['codigo'] ?? ''));
                $num = (int) ($c['numero'] ?? 0);
                if (preg_match('/^s([1-4])$/', $cod, $m)) {
                    $ok[(int) $m[1]] = true;
                } elseif ($num >= 1 && $num <= 4) {
                    $ok[$num] = true;
                }
            }
            if (isset($ok[1], $ok[2], $ok[3], $ok[4])) {
                $out[] = $q;
            }
        }
        return $out;
    }

    /**
     * @param list<array{id?:int|string,nome?:string}> $lista
     */
    private function dicaQuadros(array $lista): string
    {
        $linhas = ['Quadros ativos:'];
        foreach ($lista as $q) {
            $linhas[] = '  #' . (int) ($q['id'] ?? 0) . '  ' . (string) ($q['nome'] ?? '');
        }
        $linhas[] = 'Ex.: --quadro="Notas Semanais"  ou  --quadro-id=7';
        return implode(PHP_EOL, $linhas);
    }

    /** @return list<array{id:int,nome:string}> */
    private function turmasDoAno(): array
    {
        return $this->db->fetchAll(
            'SELECT id, nome FROM turmas WHERE ano_letivo = :a AND ativo = 1 ORDER BY nome',
            ['a' => ANO]
        ) ?: [];
    }

    /**
     * @param list<array{professor_id:int,materia_id:int}> $grade
     */
    private function garantirGradeHoraria(int $turmaId, array $grade): void
    {
        $ja = (int) ($this->db->fetch(
            'SELECT COUNT(*) AS c FROM grade_horaria WHERE turma_id = :t',
            ['t' => $turmaId]
        )['c'] ?? 0);
        if ($ja >= 25) {
            return;
        }
        if ($ja > 0) {
            $this->db->query('DELETE FROM grade_horaria WHERE turma_id = :t', ['t' => $turmaId]);
        }

        $profPorMateria = [];
        foreach ($grade as $linha) {
            $mid = (int) ($linha['materia_id'] ?? 0);
            $pid = (int) ($linha['professor_id'] ?? 0);
            if ($mid > 0 && $pid > 0) {
                $profPorMateria[$mid] = $pid;
            }
        }

        $faltou = [];
        $criados = 0;
        $this->db->beginTransaction();
        try {
            foreach (GRADE_EM as $dia => $materias) {
                foreach ($materias as $i => $nome) {
                    $mid = $this->resolverMateriaId($nome);
                    $pid = $profPorMateria[$mid] ?? 0;
                    if ($mid <= 0 || $pid <= 0 || !isset(SLOTS_AULA[$i])) {
                        $faltou[] = $nome;
                        continue;
                    }
                    $this->db->insert(
                        "INSERT INTO grade_horaria
                            (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
                         VALUES
                            (:dia, :de, :ate, :turma, :prof, :mat, 'manha')",
                        [
                            'dia' => (int) $dia,
                            'de' => SLOTS_AULA[$i][0],
                            'ate' => SLOTS_AULA[$i][1],
                            'turma' => $turmaId,
                            'prof' => $pid,
                            'mat' => $mid,
                        ]
                    );
                    $criados++;
                }
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        println('      grade horária: ' . $criados . ' aulas atribuídas');
        if ($faltou !== []) {
            println('      grade: sem professor/componente para ' . implode(', ', array_unique($faltou)));
        }
    }

    private function lancarDiarioFrequencia(int $turmaId): void
    {
        $periodo = $this->periodoDoAno();
        $criadas = $this->diario->completarSlotsVencidos($turmaId, $periodo['inicio'], $periodo['fim']);
        $this->aulasDiario += $criadas;

        $params = [
            'ano' => ANO,
            'turma' => $turmaId,
            'ini' => $periodo['inicio'],
            'fim' => $periodo['fim'],
        ];
        if ($this->temColuna('diario_frequencias', 'origem')) {
            $stmt = $this->db->query(
                "INSERT IGNORE INTO diario_frequencias (diario_aula_id, aluno_id, situacao, origem)
                 SELECT da.id, m.aluno_id,
                   CASE
                     WHEN (CRC32(CONCAT(m.aluno_id, '|', da.id)) % 100) < 5 THEN 'falta'
                     WHEN (CRC32(CONCAT(m.aluno_id, '|', da.id)) % 100) < 7 THEN 'falta_justificada'
                     WHEN (CRC32(CONCAT(m.aluno_id, '|', da.id)) % 100) < 10 THEN 'atraso'
                     ELSE 'presente'
                   END,
                   'ajuste_gestao'
                 FROM diario_aulas da
                 INNER JOIN matricula m ON m.turma_id = da.turma_id
                 INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
                 WHERE da.turma_id = :turma
                   AND da.data_aula BETWEEN :ini AND :fim
                   AND da.status = 'finalizada'
                   AND m.status IN ('ativa', 'concluido')",
                $params
            );
        } else {
            $stmt = $this->db->query(
                "INSERT IGNORE INTO diario_frequencias (diario_aula_id, aluno_id, situacao)
                 SELECT da.id, m.aluno_id,
                   CASE
                     WHEN (CRC32(CONCAT(m.aluno_id, '|', da.id)) % 100) < 5 THEN 'falta'
                     WHEN (CRC32(CONCAT(m.aluno_id, '|', da.id)) % 100) < 7 THEN 'falta_justificada'
                     WHEN (CRC32(CONCAT(m.aluno_id, '|', da.id)) % 100) < 10 THEN 'atraso'
                     ELSE 'presente'
                   END
                 FROM diario_aulas da
                 INNER JOIN matricula m ON m.turma_id = da.turma_id
                 INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
                 WHERE da.turma_id = :turma
                   AND da.data_aula BETWEEN :ini AND :fim
                   AND da.status = 'finalizada'
                   AND m.status IN ('ativa', 'concluido')",
                $params
            );
        }
        $this->frequencias += $stmt ? (int) $stmt->rowCount() : 0;

        $pares = $this->db->fetchAll(
            'SELECT DISTINCT professor_id, materia_id FROM grade_horaria WHERE turma_id = :t',
            ['t' => $turmaId]
        ) ?: [];
        foreach ($pares as $p) {
            for ($bim = 1; $bim <= 4; $bim++) {
                $this->diario->fechar(
                    $turmaId,
                    (int) $p['materia_id'],
                    (int) $p['professor_id'],
                    ANO,
                    $bim,
                    $this->adminId
                );
            }
        }
    }

    /** @return array{inicio:string,fim:string} */
    private function periodoDoAno(): array
    {
        return ['inicio' => ANO . '-01-01', 'fim' => ANO . '-12-31'];
    }

    private function sincronizarEventoFaltas(): void
    {
        $turmaIds = [];
        foreach ($this->turmasDoAno() as $turma) {
            $id = (int) ($turma['id'] ?? 0);
            if ($id > 0) {
                $turmaIds[] = $id;
            }
        }
        if ($turmaIds === []) {
            return;
        }

        $periodo = $this->diario->periodoDoBimestre(ANO, $this->bimestre);
        $nome = sprintf('Faltas %dº bimestre %d', $this->bimestre, ANO);
        $exist = $this->db->fetch(
            'SELECT id FROM faltas_eventos WHERE nome = :n AND ano_letivo = :ano AND ativo = 1 LIMIT 1',
            ['n' => $nome, 'ano' => ANO]
        );
        $eventoId = $exist
            ? (int) $exist['id']
            : $this->faltas->createEvento(
                $nome,
                (string) $this->bimestre,
                ANO,
                $turmaIds,
                $this->adminId > 0 ? $this->adminId : null,
                [],
                'diario'
            );
        if ($eventoId <= 0) {
            return;
        }

        $rows = $this->db->fetchAll(
            "SELECT df.aluno_id, da.materia_id,
                    SUM(CASE WHEN df.situacao IN ('falta','falta_justificada') THEN 1 ELSE 0 END) AS faltas
               FROM diario_frequencias df
               INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
               INNER JOIN turmas t ON t.id = da.turma_id
              WHERE t.ano_letivo = :ano
                AND da.data_aula BETWEEN :ini AND :fim
              GROUP BY df.aluno_id, da.materia_id
             HAVING faltas > 0",
            [
                'ano' => ANO,
                'ini' => $periodo['inicio'],
                'fim' => $periodo['fim'],
            ]
        ) ?: [];

        $porAluno = [];
        foreach ($rows as $row) {
            $aid = (int) ($row['aluno_id'] ?? 0);
            $mid = (int) ($row['materia_id'] ?? 0);
            if ($aid <= 0 || $mid <= 0) {
                continue;
            }
            $porAluno[$aid][$mid] = (string) $row['faltas'];
        }
        if ($porAluno !== []) {
            $this->faltas->upsertLancamentos($eventoId, $porAluno, [], $this->adminId > 0 ? $this->adminId : null);
        }
        $this->faltasLancadas = count($rows);
    }

    private function lancarEntradaSaida(): void
    {
        if (!$this->temTabela('presenca_eventos')) {
            println('  presença: tabela presenca_eventos ausente, pulada.');
            return;
        }

        $periodo = $this->periodoDoAno();
        $prefixo = 'em25-';
        $params = [
            'ano' => ANO,
            'ini' => $periodo['inicio'],
            'fim' => $periodo['fim'],
            'prefixo' => $prefixo,
            'admin' => $this->adminId > 0 ? $this->adminId : null,
        ];

        $stmtEnt = $this->db->query(
            "INSERT IGNORE INTO presenca_eventos
                (aluno_id, tipo, ocorrido_em, origem, id_externo, identificador_bruto, registrado_por, processado_em)
             SELECT m.aluno_id, 'entrada',
                    TIMESTAMP(
                        d.data_aula,
                        IF(EXISTS (
                             SELECT 1
                               FROM diario_frequencias df2
                               INNER JOIN diario_aulas da3 ON da3.id = df2.diario_aula_id
                              WHERE da3.turma_id = d.turma_id
                                AND da3.data_aula = d.data_aula
                                AND df2.aluno_id = m.aluno_id
                                AND df2.situacao = 'atraso'
                           ),
                           '07:42:00',
                           CONCAT('07:', LPAD(18 + (m.aluno_id % 10), 2, '0'), ':00'))
                    ),
                    IF(m.aluno_id % 5 = 0, 'manual_secretaria', 'facial'),
                    CONCAT(:prefixo, m.aluno_id, '-', d.data_aula, '-entrada'),
                    CAST(m.aluno_id AS CHAR),
                    IF(m.aluno_id % 5 = 0, :admin, NULL),
                    NOW()
               FROM (
                    SELECT DISTINCT da.turma_id, da.data_aula
                      FROM diario_aulas da
                     WHERE da.data_aula BETWEEN :ini AND :fim
                       AND da.status = 'finalizada'
               ) d
               INNER JOIN matricula m ON m.turma_id = d.turma_id AND m.status IN ('ativa', 'concluido')
               INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
              WHERE EXISTS (
                    SELECT 1
                      FROM diario_frequencias df
                      INNER JOIN diario_aulas da2 ON da2.id = df.diario_aula_id
                     WHERE da2.turma_id = d.turma_id
                       AND da2.data_aula = d.data_aula
                       AND df.aluno_id = m.aluno_id
                       AND df.situacao IN ('presente', 'atraso', 'saida_antecipada')
              )",
            $params
        );
        $this->presencas += $stmtEnt ? (int) $stmtEnt->rowCount() : 0;

        $stmtSai = $this->db->query(
            "INSERT IGNORE INTO presenca_eventos
                (aluno_id, tipo, ocorrido_em, origem, id_externo, identificador_bruto, registrado_por, processado_em)
             SELECT m.aluno_id, 'saida',
                    TIMESTAMP(d.data_aula, CONCAT('12:', LPAD(2 + (m.aluno_id % 8), 2, '0'), ':00')),
                    IF(m.aluno_id % 5 = 0, 'manual_secretaria', 'facial'),
                    CONCAT(:prefixo, m.aluno_id, '-', d.data_aula, '-saida'),
                    CAST(m.aluno_id AS CHAR),
                    IF(m.aluno_id % 5 = 0, :admin, NULL),
                    NOW()
               FROM (
                    SELECT DISTINCT da.turma_id, da.data_aula
                      FROM diario_aulas da
                     WHERE da.data_aula BETWEEN :ini AND :fim
                       AND da.status = 'finalizada'
               ) d
               INNER JOIN matricula m ON m.turma_id = d.turma_id AND m.status IN ('ativa', 'concluido')
               INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
              WHERE EXISTS (
                    SELECT 1 FROM presenca_eventos pe
                     WHERE pe.id_externo = CONCAT(:prefixo2, m.aluno_id, '-', d.data_aula, '-entrada')
              )",
            [
                'ano' => ANO,
                'ini' => $periodo['inicio'],
                'fim' => $periodo['fim'],
                'prefixo' => $prefixo,
                'prefixo2' => $prefixo,
                'admin' => $this->adminId > 0 ? $this->adminId : null,
            ]
        );
        $this->presencas += $stmtSai ? (int) $stmtSai->rowCount() : 0;
    }

    /** @return list<int> */
    private function alunosDaTurma(int $turmaId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT a.id
               FROM alunos a
               INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t
               INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = :ano
              WHERE m.status IN ('ativa', 'concluido')
              GROUP BY a.id
              ORDER BY MIN(a.id)",
            ['t' => $turmaId, 'ano' => ANO]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if ($id > 0) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * @return list<array{professor_id:int,materia_id:int}>
     */
    private function gradeDaTurma(int $turmaId): array
    {
        $profs = $this->db->fetchAll(
            'SELECT id, materias, turmas FROM professores WHERE ativo = 1'
        ) ?: [];
        $out = [];
        $visto = [];
        foreach ($profs as $p) {
            $pid = (int) $p['id'];
            $turmas = $this->decodeLista($p['turmas'] ?? null);
            $temTurma = false;
            foreach ($turmas as $t) {
                if ((int) $t === $turmaId) {
                    $temTurma = true;
                    break;
                }
            }
            if (!$temTurma) {
                continue;
            }
            foreach ($this->decodeLista($p['materias'] ?? null) as $item) {
                $mid = $this->resolverMateriaId($item);
                if ($mid <= 0 || isset($this->rotulos[$mid]) || isset($visto[$mid])) {
                    continue;
                }
                $visto[$mid] = true;
                $out[] = ['professor_id' => $pid, 'materia_id' => $mid];
            }
        }
        usort($out, static fn ($a, $b) => $a['materia_id'] <=> $b['materia_id']);
        return $out;
    }

    /** @return list<mixed> */
    private function decodeLista($raw): array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }
        $decoded = json_decode($s, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function resolverMateriaId($item): int
    {
        if (is_numeric($item)) {
            return (int) $item;
        }
        $nome = mb_strtolower(trim((string) $item));
        return (int) ($this->materiaPorNome[$nome] ?? 0);
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $tabela) || !preg_match('/^[a-z0-9_]+$/i', $coluna)) {
            return false;
        }
        return $this->db->fetch('SHOW COLUMNS FROM `' . $tabela . '` LIKE ?', [$coluna]) !== false;
    }

    private function temTabela(string $tabela): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $tabela)) {
            return false;
        }
        return $this->db->fetch("SHOW TABLES LIKE '" . $tabela . "'") !== false;
    }
}

/**
 * @return list<array{0:string,1:list<string>,2:int}>
 */
function bancoQuestoesDaMateria(string $nome): array
{
    $n = mb_strtolower($nome);
    $bancos = [
        'matemática' => [
            ['Quanto é 3x + 5 = 20?', ['x = 3', 'x = 5', 'x = 15', 'x = 25'], 1],
            ['A raiz quadrada de 144 é:', ['10', '11', '12', '14'], 2],
            ['O seno de 90° vale:', ['0', '1', '1/2', '√2/2'], 1],
            ['Uma função do 1º grau é representada por:', ['parábola', 'reta', 'hipérbole', 'circunferência'], 1],
            ['2³ × 2² é igual a:', ['2⁵', '2⁶', '4⁵', '8'], 0],
        ],
        'gramática' => [
            ['Em “Os alunos chegaram cedo”, o sujeito é:', ['chegaram', 'Os alunos', 'cedo', 'oculto'], 1],
            ['Assinale o verbo no pretérito perfeito:', ['estuda', 'estudará', 'estudou', 'estudando'], 2],
            ['“A menina, que lia, sorriu.” A oração destacada é:', ['adverbial', 'adjetiva', 'substantiva', 'coordenada'], 1],
            ['Qual palavra está acentuada corretamente?', ['ideia', 'heroí', 'útil', 'assembléia'], 2],
            ['O antônimo de “escasso” é:', ['raro', 'abundante', 'pouco', 'mínimo'], 1],
        ],
        'literatura' => [
            ['Machado de Assis pertence ao:', ['Romantismo', 'Realismo', 'Modernismo', 'Barroco'], 1],
            ['“Iracema” é obra de:', ['José de Alencar', 'Castro Alves', 'Álvares de Azevedo', 'Gonçalves Dias'], 0],
            ['O eu-lírico é típico da:', ['epopeia', 'crônica', 'poesia lírica', 'conto'], 2],
            ['O Modernismo brasileiro inicia-se em:', ['1888', '1922', '1930', '1964'], 1],
            ['Quem escreveu “Capitães da Areia”?', ['Jorge Amado', 'Graciliano Ramos', 'Rachel de Queiroz', 'Érico Veríssimo'], 0],
        ],
        'interpretação de texto' => [
            ['Inferir, em leitura, é:', ['copiar o texto', 'concluir o não dito', 'decorar parágrafos', 'contar linhas'], 1],
            ['O tema de um texto é:', ['o assunto geral', 'o último parágrafo', 'a pontuação', 'o título gráfico'], 0],
            ['Ironia ocorre quando:', ['há rima', 'o dito opõe-se ao sentido', 'faltam vírgulas', 'o texto é curto'], 1],
            ['Um texto dissertativo prioriza:', ['narrar fatos', 'defender ideia', 'descrever paisagem', 'dialogar personagens'], 1],
            ['Coesão textual depende principalmente de:', ['conectivos', 'adjetivos', 'tamanho da letra', 'número de páginas'], 0],
        ],
        'história' => [
            ['A Independência do Brasil foi em:', ['1808', '1822', '1889', '1500'], 1],
            ['Quem proclamou a República?', ['Dom Pedro II', 'Deodoro da Fonseca', 'Getúlio Vargas', 'Tiradentes'], 1],
            ['A Lei Áurea foi assinada em:', ['1822', '1888', '1889', '1850'], 1],
            ['A Revolução Francesa começou em:', ['1776', '1789', '1815', '1848'], 1],
            ['O Estado Novo está ligado a:', ['JK', 'Getúlio Vargas', 'Jânio Quadros', 'Tancredo'], 1],
        ],
        'geografia' => [
            ['O maior bioma brasileiro em área é:', ['Cerrado', 'Amazônia', 'Mata Atlântica', 'Caatinga'], 1],
            ['O Equador atravessa qual região do Brasil?', ['Sul', 'Sudeste', 'Norte', 'Centro-Oeste'], 2],
            ['Capital do Amazonas:', ['Belém', 'Manaus', 'Macapá', 'Boa Vista'], 1],
            ['Planalto e planície são formas de:', ['clima', 'relevo', 'vegetação', 'hidrografia'], 1],
            ['Clima semiárido predomina na:', ['Amazônia', 'Caatinga', 'Pampa', 'Pantanal'], 1],
        ],
        'biologia' => [
            ['Organela que produz energia:', ['núcleo', 'mitocôndria', 'vacúolo', 'ribossomo'], 1],
            ['Fotossíntese ocorre principalmente no:', ['caule', 'cloroplasto', 'raiz', 'fruto'], 1],
            ['A água é composta por:', ['H2O', 'CO2', 'O2', 'NaCl'], 0],
            ['Mamíferos se caracterizam por:', ['ovos com casca', 'glândulas mamárias', 'brânquias', 'exoesqueleto'], 1],
            ['Unidade básica da hereditariedade:', ['célula', 'gene', 'tecido', 'órgão'], 1],
        ],
        'física' => [
            ['Unidade de força no SI:', ['joule', 'newton', 'watt', 'pascal'], 1],
            ['v = d/t calcula:', ['aceleração', 'velocidade média', 'força', 'energia'], 1],
            ['A luz visível é uma onda:', ['sonora', 'eletromagnética', 'mecânica', 'sísmica'], 1],
            ['Energia cinética depende de:', ['só da massa', 'massa e velocidade', 'só da altura', 'temperatura'], 1],
            ['A 1ª lei de Newton trata da:', ['ação e reação', 'inércia', 'gravitação', 'energia'], 1],
        ],
        'química' => [
            ['Número atômico indica:', ['nêutrons', 'prótons', 'elétrons da última camada', 'massa molar'], 1],
            ['H2O é uma:', ['mistura', 'substância composta', 'substância simples', 'liga'], 1],
            ['pH 3 indica meio:', ['neutro', 'ácido', 'básico', 'salino'], 1],
            ['Ligação entre Na e Cl é:', ['covalente', 'iônica', 'metálica', 'de hidrogênio'], 1],
            ['O ouro é um:', ['ametálico', 'metal', 'gás nobre', 'halogênio'], 1],
        ],
        'filosofia' => [
            ['“Conhece-te a ti mesmo” associa-se a:', ['Platão', 'Sócrates', 'Aristóteles', 'Kant'], 1],
            ['Ética estuda:', ['os astros', 'o agir moral', 'os números', 'o clima'], 1],
            ['Racionalismo moderno é ligado a:', ['Descartes', 'Hume', 'Marx', 'Nietzsche'], 0],
            ['Maiêutica é método de:', ['Aristóteles', 'Sócrates', 'Epicuro', 'Tales'], 1],
            ['Alegoria da caverna é de:', ['Sócrates', 'Platão', 'Aristóteles', 'Heráclito'], 1],
        ],
        'sociologia' => [
            ['Fato social é conceito de:', ['Marx', 'Durkheim', 'Weber', 'Comte'], 1],
            ['Luta de classes é central em:', ['Durkheim', 'Marx', 'Weber', 'Spencer'], 1],
            ['Ação social é conceito de:', ['Marx', 'Durkheim', 'Weber', 'Comte'], 2],
            ['Cidadania envolve:', ['só voto', 'direitos e deveres', 'só trabalho', 'só família'], 1],
            ['Instituição social exemplo:', ['escola', 'pedra', 'nuvem', 'rio'], 0],
        ],
        'língua inglesa' => [
            ['Simple past of “go”:', ['goed', 'went', 'gone', 'going'], 1],
            ['“She ___ a student.”', ['am', 'is', 'are', 'be'], 1],
            ['Opposite of “cheap”:', ['poor', 'expensive', 'small', 'late'], 1],
            ['Present continuous: they ___ now.', ['study', 'studied', 'are studying', 'studies'], 2],
            ['“How ___ you?”', ['is', 'are', 'am', 'be'], 1],
        ],
        'arte' => [
            ['Cubismo está ligado a:', ['Monet', 'Picasso', 'Van Gogh', 'Portinari'], 1],
            ['A Semana de 22 ocorreu em:', ['Rio', 'São Paulo', 'Recife', 'BH'], 1],
            ['Cores primárias:', ['verde, laranja, roxo', 'vermelho, azul, amarelo', 'preto, branco, cinza', 'rosa, marrom, dourado'], 1],
            ['Tarsila do Amaral é do:', ['Barroco', 'Modernismo', 'Arcádia', 'Realismo'], 1],
            ['Perspectiva na pintura cria:', ['som', 'profundidade', 'cheiro', 'ritmo musical'], 1],
        ],
        'educação física' => [
            ['Alongamento antes do exercício visa:', ['hipertrofia imediata', 'preparar músculos', 'perder peso na hora', 'aumentar calçado'], 1],
            ['Esporte coletivo:', ['tênis', 'natação', 'basquete', 'atletismo 100m'], 2],
            ['Frequência cardíaca mede:', ['força', 'batimentos do coração', 'flexibilidade', 'equilíbrio'], 1],
            ['Fair play significa:', ['ganhar a qualquer custo', 'jogo limpo', 'só ataque', 'expulsão'], 1],
            ['Aquecimento reduz risco de:', ['vitória', 'lesão', 'hidratação', 'concentração'], 1],
        ],
    ];
    foreach ($bancos as $chave => $qs) {
        if ($n === $chave || str_contains($n, $chave)) {
            return $qs;
        }
    }
    return [
        ['Qual alternativa completa o conteúdo de ' . $nome . '?', ['A', 'B', 'C', 'D'], 1],
        ['Assinale a afirmativa correta sobre ' . $nome . '.', ['I', 'II', 'III', 'IV'], 2],
        ['O conceito central de ' . $nome . ' nesta semana é:', ['X', 'Y', 'Z', 'W'], 0],
        ['Relacione a prática de ' . $nome . ' ao cotidiano.', ['sempre', 'nunca', 'às vezes', 'raramente'], 0],
        ['Qual procedimento é adequado em ' . $nome . '?', ['1', '2', '3', '4'], 1],
    ];
}

$opcoes = opcoesSeed($argv ?? []);
$bims = $opcoes['bimestres'];

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
    $codigo = 0;
    foreach ($bims as $bimestre) {
        $runner = new SeedAvaliacoesEm2025Educa(Database::getInstance(), $bimestre, [
            'quadro' => $opcoes['quadro'],
            'quadro_id' => $opcoes['quadro_id'],
        ]);
        $codigo = $runner->executar();
        if ($codigo !== 0) {
            exit($codigo);
        }
    }
    exit($codigo);
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
