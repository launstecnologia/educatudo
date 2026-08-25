<?php
/**
 * Seed de notas e frequência para uma sessão de Conselho de Classe.
 *
 * O conselho só lê boletim gerado + diário. Este script lança aulas, faltas,
 * eventos de nota e gera o boletim do bimestre da sessão — com alguns alunos
 * um pouco abaixo da média (6,0) para simular a pauta.
 *
 * Uso (container PHP):
 *   php scripts/seed_conselho_classe.php
 *   php scripts/seed_conselho_classe.php --sessao=7
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
require_once $basePath . '/app/Models/Education/ClassDiary.php';
require_once $basePath . '/app/Models/Exams/ExamBlock.php';
require_once $basePath . '/app/Models/Exams/ExamBlockManualGrade.php';
require_once $basePath . '/app/Models/Exams/ExamEvaluationType.php';
require_once $basePath . '/app/Models/System/BoletimConfig.php';
require_once $basePath . '/app/Controllers/Admin/BoletimConfigController.php';
require_once $basePath . '/app/Services/FrequencyService.php';

const TENANT_DB = 'educatudo_educa';
const PREFIXO = 'EDUCA';
const SESSAO_PADRAO = 7;
const DIAS_LETIVOS = 8;
const NOTA_MEDIA = 6.0;

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

function argInt(array $argv, string $nome, int $padrao): int
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $nome . '=')) {
            return (int) substr($arg, strlen($nome) + 1);
        }
    }
    return $padrao;
}

function semAcento(string $s): string
{
    $s = mb_strtolower($s);
    $map = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
        'é' => 'e', 'ê' => 'e', 'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
    ];
    return strtr($s, $map);
}

final class SeedConselhoClasse
{
    private $db;
    private ClassDiary $diario;
    private ExamBlock $blocos;
    private ExamBlockManualGrade $notas;
    private ExamEvaluationType $tipos;
    private BoletimConfig $boletim;
    private BoletimConfigController $boletimCtrl;
    private FrequencyService $frequencia;

    private int $adminId = 0;
    private int $tipoProvaId = 0;
    private int $tipoAtivId = 0;

    /** @var array<int, string> aluno_id => cenario */
    private array $cenarioPorAluno = [];
    /** @var array<int, list<int>> aluno_id => materia_ids abaixo da média */
    private array $materiasFracasPorAluno = [];

    public function __construct($db)
    {
        $this->db = $db;
        $this->diario = new ClassDiary();
        $this->diario->ensureSchema();
        $this->blocos = new ExamBlock();
        $this->notas = new ExamBlockManualGrade();
        $this->tipos = new ExamEvaluationType();
        $this->boletim = new BoletimConfig();
        $this->boletim->ensureSchema();
        $this->boletimCtrl = $this->montarBoletimController();
        $this->frequencia = new FrequencyService();
    }

    public function executar(int $sessaoId): int
    {
        println('== Seed Conselho de Classe — sessão #' . $sessaoId . ' ==');

        $sessao = $this->db->fetch(
            "SELECT cs.*, t.nome AS turma_nome, t.serie AS turma_serie, t.serie_id, t.ano_letivo_id
             FROM conselho_sessoes cs
             INNER JOIN turmas t ON t.id = cs.turma_id
             WHERE cs.id = :id LIMIT 1",
            ['id' => $sessaoId]
        );
        if (!$sessao) {
            fail('Sessão de conselho #' . $sessaoId . ' não encontrada em ' . TENANT_DB . '.');
        }

        $turmaId = (int) $sessao['turma_id'];
        $ano = (int) $sessao['ano_letivo'];
        $bim = (int) $sessao['bimestre'];
        $turmaNome = (string) $sessao['turma_nome'];
        println("Turma: {$turmaNome} (id={$turmaId}) · {$ano} · {$bim}º bimestre · status={$sessao['status']}");

        $this->adminId = $this->resolverAdminId();
        $this->tipoProvaId = $this->tipoPorNome('Prova Bimestral', 'Avaliação principal do bimestre.', 20, 'prova_bim');
        $this->tipoAtivId = $this->tipoPorNome('Atividade em Aula', 'Atividade realizada em aula.', 40, 'trabalho');

        $alunos = $this->alunosDaTurma($turmaId, (int) ($sessao['ano_letivo_id'] ?? 0), $ano);
        $ativos = array_values(array_filter(
            $alunos,
            static function (array $a): bool {
                if (!empty($a['transferido'])) {
                    return false;
                }
                if ((string) ($a['status_matricula'] ?? 'ativa') !== 'ativa') {
                    return false;
                }
                // Nomes já marcados como transferência no seed do colégio
                return !str_contains((string) $a['nome'], '(transf.');
            }
        ));
        if ($ativos === []) {
            fail('Nenhum aluno ativo na turma do conselho.');
        }

        $grades = $this->db->fetchAll(
            'SELECT * FROM grade_horaria WHERE turma_id = :t ORDER BY dia_semana, horario_de, id',
            ['t' => $turmaId]
        ) ?: [];
        if ($grades === []) {
            fail('Turma sem grade horária — não dá para lançar diário.');
        }

        $materias = $this->materiasDaGrade($grades);
        $this->atribuirCenarios($ativos, $materias);

        println('');
        println('Cenários:');
        foreach ($alunos as $al) {
            $aid = (int) $al['id'];
            $tag = (str_contains((string) $al['nome'], '(transf.') || !empty($al['transferido']))
                ? 'transferido'
                : ($this->cenarioPorAluno[$aid] ?? 'regular');
            $fracas = $this->materiasFracasPorAluno[$aid] ?? [];
            $nomesFracas = [];
            foreach ($fracas as $mid) {
                $nomesFracas[] = $materias[$mid]['nome'] ?? ('#' . $mid);
            }
            $extra = $nomesFracas !== [] ? ' [' . implode(', ', $nomesFracas) . ']' : '';
            println(sprintf('  · %s — %s%s', $al['nome'], $tag, $extra));
        }

        $this->reabrirDiarios($turmaId, $ano, $bim);
        $this->lancarDiario($ano, $turmaId, $bim, $ativos, $grades);
        $eventos = $this->criarEventos($ano, $turmaNome, $turmaId, $bim, $grades);
        $this->lancarNotas($turmaId, $ativos, $materias, $grades, $eventos);
        $this->gerarBoletim($ano, $turmaId, $bim, (int) ($sessao['serie_id'] ?? 0), $ativos, $materias, $eventos);
        $this->fecharDiarios($ano, $turmaId, $bim, $grades);

        $this->imprimirResumo($sessao, $ativos);
        println('');
        println('Pronto. Recarregue o Conselho #' . $sessaoId . ' no admin.');
        return 0;
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

    private function resolverAdminId(): int
    {
        $row = $this->db->fetch(
            "SELECT id FROM usuarios WHERE email IN ('admin@educa.local', 'admin@educa.localhost') LIMIT 1"
        );
        if ($row) {
            return (int) $row['id'];
        }
        $row = $this->db->fetch('SELECT id FROM usuarios ORDER BY id ASC LIMIT 1');
        return $row ? (int) $row['id'] : 1;
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

    /**
     * @return list<array<string,mixed>>
     */
    private function alunosDaTurma(int $turmaId, int $anoLetivoId, int $ano): array
    {
        $params = ['t' => $turmaId];
        $joinAno = '';
        if ($anoLetivoId > 0) {
            $joinAno = ' AND m.ano_letivo_id = :ano_id';
            $params['ano_id'] = $anoLetivoId;
        }
        $rows = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.turma_id, m.status AS status_matricula,
                    CASE WHEN m.status = 'transferido' OR a.turma_id <> :t2 THEN 1 ELSE 0 END AS transferido
             FROM alunos a
             INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t {$joinAno}
             WHERE m.status IN ('ativa','transferido','concluido')
             ORDER BY a.nome",
            $params + ['t2' => $turmaId]
        ) ?: [];

        if ($rows !== []) {
            return $rows;
        }

        return $this->db->fetchAll(
            "SELECT a.id, a.nome, a.turma_id, 'ativa' AS status_matricula, 0 AS transferido
             FROM alunos a
             WHERE a.turma_id = :t AND a.ativo = 1
             ORDER BY a.nome",
            ['t' => $turmaId]
        ) ?: [];
    }

    /**
     * @param list<array<string,mixed>> $grades
     * @return array<int, array{id:int,nome:string}>
     */
    private function materiasDaGrade(array $grades): array
    {
        $ids = [];
        foreach ($grades as $g) {
            $ids[(int) $g['materia_id']] = true;
        }
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_map('intval', array_keys($ids)));
        $rows = $this->db->fetchAll("SELECT id, nome FROM materias WHERE id IN ({$in}) ORDER BY nome") ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = ['id' => (int) $r['id'], 'nome' => (string) $r['nome']];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $ativos
     * @param array<int, array{id:int,nome:string}> $materias
     */
    private function atribuirCenarios(array $ativos, array $materias): void
    {
        $porNome = [];
        foreach ($materias as $mid => $m) {
            $porNome[semAcento((string) $m['nome'])] = $mid;
        }
        $pick = function (array $nomes) use ($porNome, $materias): array {
            $ids = [];
            foreach ($nomes as $n) {
                $k = semAcento($n);
                if (isset($porNome[$k])) {
                    $ids[] = $porNome[$k];
                    continue;
                }
                foreach ($porNome as $nome => $mid) {
                    if ($k === 'fisica' && $nome !== 'fisica') {
                        continue;
                    }
                    if ($nome === $k || str_contains($nome, $k)) {
                        $ids[] = $mid;
                        break;
                    }
                }
            }
            if ($ids === [] && $materias !== []) {
                $ids = array_slice(array_keys($materias), 0, 2);
            }
            return array_values(array_unique($ids));
        };

        $usados = [];
        foreach ($ativos as $al) {
            $aid = (int) $al['id'];
            $nome = semAcento((string) $al['nome']);
            if (str_contains($nome, 'joao')) {
                $this->cenarioPorAluno[$aid] = 'recuperacao';
                $this->materiasFracasPorAluno[$aid] = $pick(['Matemática', 'Física', 'Química']);
                $usados[$aid] = true;
            } elseif (str_contains($nome, 'karina') || str_contains($nome, 'carina')) {
                $this->cenarioPorAluno[$aid] = 'recuperacao';
                $this->materiasFracasPorAluno[$aid] = $pick(['Química', 'Língua Portuguesa', 'Portuguesa']);
                $usados[$aid] = true;
            } elseif (str_contains($nome, 'isabela') || str_contains($nome, 'izabela')) {
                $this->cenarioPorAluno[$aid] = 'regular';
                $usados[$aid] = true;
            }
        }

        $restantes = [];
        foreach ($ativos as $al) {
            $aid = (int) $al['id'];
            if (!isset($usados[$aid])) {
                $restantes[] = $aid;
            }
        }

        if (isset($restantes[0])) {
            $this->cenarioPorAluno[$restantes[0]] = 'frequencia';
        }
        if (isset($restantes[1])) {
            $this->cenarioPorAluno[$restantes[1]] = 'recuperacao_leve';
            $this->materiasFracasPorAluno[$restantes[1]] = $pick(['História', 'Geografia']);
        }
        foreach ($restantes as $i => $aid) {
            if ($i >= 2) {
                $this->cenarioPorAluno[$aid] = 'regular';
            }
        }
    }

    private function reabrirDiarios(int $turmaId, int $ano, int $bim): void
    {
        try {
            $this->db->query(
                "UPDATE diario_fechamentos
                 SET status = 'aberto'
                 WHERE turma_id = :t AND ano_letivo = :a AND bimestre = :b AND status = 'fechado'",
                ['t' => $turmaId, 'a' => $ano, 'b' => $bim]
            );
        } catch (Throwable $e) {
            println('  aviso: não foi possível reabrir diários (' . $e->getMessage() . ')');
        }
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @param list<array<string,mixed>> $grades
     */
    private function lancarDiario(int $ano, int $turmaId, int $bim, array $alunos, array $grades): void
    {
        $periodo = $this->diario->periodoDoBimestre($ano, $bim);
        $inicio = max($periodo['inicio'], $ano . '-02-01');
        $fim = min($periodo['fim'], $ano . '-12-15');
        $porDia = [];
        foreach ($grades as $g) {
            $porDia[(int) $g['dia_semana']][] = $g;
        }

        $diasLetivos = 0;
        $aulas = 0;
        $start = new DateTime($inicio);
        $end = new DateTime($fim);
        for ($d = clone $start; $d <= $end; $d->modify('+1 day')) {
            $n = (int) $d->format('N');
            if ($n > 5 || !isset($porDia[$n])) {
                continue;
            }
            $diasLetivos++;
            if ($diasLetivos > DIAS_LETIVOS) {
                break;
            }
            $data = $d->format('Y-m-d');
            foreach ($porDia[$n] as $idx => $grade) {
                $aula = $this->diario->getOrCreateAula($grade, $data, null);
                $aulaId = (int) ($aula['id'] ?? 0);
                if ($aulaId <= 0) {
                    continue;
                }
                $freq = [];
                foreach ($alunos as $al) {
                    $aid = (int) $al['id'];
                    $freq[$aid] = [
                        'situacao' => $this->situacaoFrequencia(
                            $aid,
                            (int) $grade['materia_id'],
                            $diasLetivos,
                            $n,
                            $idx
                        ),
                        'observacao' => '',
                    ];
                }
                $this->diario->salvar(
                    $aulaId,
                    'conforme_planejado',
                    'Conteúdo ' . PREFIXO . ' conselho — ' . $data,
                    '',
                    $freq,
                    true
                );
                $aulas++;
            }
        }
        println("Diário: {$aulas} aulas em {$diasLetivos} dias letivos ({$periodo['inicio']} a {$periodo['fim']}).");
    }

    private function situacaoFrequencia(int $alunoId, int $materiaId, int $diaLetivo, int $dow, int $slot): string
    {
        $cenario = $this->cenarioPorAluno[$alunoId] ?? 'regular';
        $seed = $alunoId + $materiaId + $diaLetivo + $dow + $slot;
        if ($cenario === 'frequencia') {
            // ~33% de faltas → um pouco abaixo dos 75% legais
            return ($diaLetivo % 3 === 0 || ($seed % 7) === 0) ? 'falta' : 'presente';
        }
        if (str_starts_with($cenario, 'recuperacao') && $diaLetivo === 3 && $dow >= 4) {
            return 'falta';
        }
        if ($cenario === 'regular' && $diaLetivo === 1 && $dow === 1 && $slot === 0) {
            return 'atraso';
        }
        return 'presente';
    }

    /**
     * @param list<array<string,mixed>> $grades
     * @return array<string,int>
     */
    private function criarEventos(int $ano, string $turmaNome, int $turmaId, int $bim, array $grades): array
    {
        $periodo = $this->diario->periodoDoBimestre($ano, $bim);
        $profs = $this->professoresDaGrade($grades, $turmaId);
        $defs = [
            'p1' => ['Prova Bimestral 1', $this->tipoProvaId, $periodo['inicio']],
            'p2' => ['Prova Bimestral 2', $this->tipoProvaId, date('Y-m-d', strtotime($periodo['inicio'] . ' +35 days'))],
            'atv' => ['Atividade em Aula', $this->tipoAtivId, date('Y-m-d', strtotime($periodo['inicio'] . ' +20 days'))],
        ];
        $ids = [];
        foreach ($defs as $k => $def) {
            $titulo = sprintf('%s %s — %s %d B%d', PREFIXO, $def[0], $turmaNome, $ano, $bim);
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
                'descricao' => 'Evento de lançamento de nota — seed Conselho de Classe',
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
                'professores' => $profs,
            ]);
        }
        println('Eventos: P1=' . $ids['p1'] . ' P2=' . $ids['p2'] . ' ATV=' . $ids['atv']);
        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $grades
     * @return list<array<string,mixed>>
     */
    private function professoresDaGrade(array $grades, int $turmaId): array
    {
        $vistos = [];
        $out = [];
        foreach ($grades as $g) {
            $chave = (int) $g['professor_id'] . ':' . (int) $g['materia_id'];
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            $out[] = [
                'professor_id' => (int) $g['professor_id'],
                'materia_id' => (int) $g['materia_id'],
                'quantidade_questoes' => 1,
                'turmas' => [$turmaId],
            ];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @param array<int, array{id:int,nome:string}> $materias
     * @param list<array<string,mixed>> $grades
     * @param array<string,int> $eventos
     */
    private function lancarNotas(int $turmaId, array $alunos, array $materias, array $grades, array $eventos): void
    {
        $pares = $this->professoresDaGrade($grades, $turmaId);
        foreach ($eventos as $tipoEv => $blocoId) {
            foreach ($pares as $par) {
                $linhas = [];
                foreach ($alunos as $al) {
                    $aid = (int) $al['id'];
                    $mid = (int) $par['materia_id'];
                    $linhas[] = [
                        'turma_id' => $turmaId,
                        'aluno_id' => $aid,
                        'nota' => $this->notaDoAluno($aid, $mid, (string) $tipoEv),
                    ];
                }
                $this->notas->upsertLinhas(
                    $blocoId,
                    (int) $par['professor_id'],
                    (int) $par['materia_id'],
                    $linhas
                );
            }
        }
        println('Notas lançadas em ' . count($eventos) . ' eventos × ' . count($pares) . ' componentes.');
    }

    private function notaDoAluno(int $alunoId, int $materiaId, string $tipoEv): float
    {
        $cenario = $this->cenarioPorAluno[$alunoId] ?? 'regular';
        $fracas = $this->materiasFracasPorAluno[$alunoId] ?? [];
        $abaixo = in_array($materiaId, $fracas, true);

        if ($cenario === 'frequencia') {
            $n = 7.2;
        } elseif ($cenario === 'recuperacao' && $abaixo) {
            $n = 5.2 + ($materiaId % 4) * 0.1;
        } elseif ($cenario === 'recuperacao_leve' && $abaixo) {
            $n = 5.6 + ($materiaId % 3) * 0.1;
        } elseif (str_starts_with($cenario, 'recuperacao')) {
            $n = 6.8 + ($materiaId % 5) * 0.15;
        } else {
            $n = 7.8 + ($alunoId % 7) * 0.15 + ($materiaId % 4) * 0.1;
        }

        if ($tipoEv === 'p2') {
            $n = min(10, $n + 0.2);
        } elseif ($tipoEv === 'atv') {
            $n = max(0, $n - 0.2);
        }
        return round($n, 1);
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @param array<int, array{id:int,nome:string}> $materias
     * @param array<string,int> $eventos
     */
    private function gerarBoletim(
        int $ano,
        int $turmaId,
        int $bim,
        int $serieId,
        array $alunos,
        array $materias,
        array $eventos
    ): void {
        $codigo = sprintf('educa-conselho-%d-t%d-b%d', $ano, $turmaId, $bim);
        $periodoRef = $ano . '-B' . $bim;
        $periodo = $this->diario->periodoDoBimestre($ano, $bim);
        $componentes = [
            ['codigo' => 'P1', 'nome' => 'Prova Bimestral 1', 'source_type' => 'provas_sistema', 'calc_type' => 'media', 'peso' => 0.4, 'blocos_ids' => (string) $eventos['p1'], 'obrigatorio' => 1],
            ['codigo' => 'P2', 'nome' => 'Prova Bimestral 2', 'source_type' => 'provas_sistema', 'calc_type' => 'media', 'peso' => 0.4, 'blocos_ids' => (string) $eventos['p2'], 'obrigatorio' => 1],
            ['codigo' => 'ATV', 'nome' => 'Atividade em Aula', 'source_type' => 'provas_sistema', 'calc_type' => 'media', 'peso' => 0.2, 'blocos_ids' => (string) $eventos['atv'], 'obrigatorio' => 1],
        ];
        $formula = '(P1 * 0.4) + (P2 * 0.4) + (ATV * 0.2)';
        $existente = $this->boletim->getRuleByCode($codigo);
        $seriesJson = $serieId > 0 ? json_encode([$serieId]) : null;
        $regraId = $this->boletim->saveRule(
            sprintf('Boletim Conselho %s %d B%d', 'turma ' . $turmaId, $ano, $bim),
            $formula,
            $componentes,
            $existente ? (int) $existente['id'] : null,
            'Seed conselho: 2 provas + atividade',
            null,
            null,
            $codigo,
            $seriesJson,
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
            NOTA_MEDIA,
            1
        );
        $regra = $this->boletim->getRuleById($regraId);
        $gerados = 0;
        $fallback = 0;
        foreach ($alunos as $al) {
            $aid = (int) $al['id'];
            $ok = false;
            try {
                $sim = $this->boletimCtrl->simularRegraAluno(
                    $regra,
                    $aid,
                    $periodoRef,
                    $periodo['inicio'],
                    $periodo['fim']
                );
                $matriz = $sim['matriz_materias'] ?? null;
                $colunas = is_array($matriz) && is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
                $rows = is_array($matriz) && is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
                if ($rows !== []) {
                    $this->boletim->replaceGeneratedResultsForAluno(
                        $regraId,
                        $aid,
                        $periodoRef,
                        $periodo['inicio'],
                        $periodo['fim'],
                        $colunas,
                        $rows,
                        false
                    );
                    $ok = true;
                    $gerados++;
                }
            } catch (Throwable $e) {
                println('  aviso boletim ' . ($al['nome'] ?? $aid) . ': ' . $e->getMessage());
            }
            if (!$ok) {
                $this->gravarBoletimDireto($regraId, $aid, $periodoRef, $periodo, $materias);
                $fallback++;
            }
        }
        println("Boletim: gerados={$gerados} fallback={$fallback} regra={$regraId}");
    }

    /**
     * @param array<int, array{id:int,nome:string}> $materias
     * @param array{inicio:string,fim:string} $periodo
     */
    private function gravarBoletimDireto(int $regraId, int $alunoId, string $periodoRef, array $periodo, array $materias): void
    {
        $colunas = [
            ['codigo' => 'media_final', 'nome' => 'Média'],
        ];
        $linhas = [];
        foreach ($materias as $mid => $m) {
            $media = $this->notaDoAluno($alunoId, $mid, 'p1');
            $linhas[] = [
                'materia_id' => $mid,
                'materia_nome' => $m['nome'],
                'notas' => ['media_final' => $media],
                'nota_resumo' => $media,
            ];
        }
        $this->boletim->replaceGeneratedResultsForAluno(
            $regraId,
            $alunoId,
            $periodoRef,
            $periodo['inicio'],
            $periodo['fim'],
            $colunas,
            $linhas,
            false
        );
    }

    /**
     * @param list<array<string,mixed>> $grades
     */
    private function fecharDiarios(int $ano, int $turmaId, int $bim, array $grades): void
    {
        $vistos = [];
        $n = 0;
        foreach ($grades as $g) {
            $chave = (int) $g['materia_id'] . ':' . (int) $g['professor_id'];
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            $this->diario->fechar(
                $turmaId,
                (int) $g['materia_id'],
                (int) $g['professor_id'],
                $ano,
                $bim,
                $this->adminId
            );
            $n++;
        }
        println("Diários fechados: {$n}");
    }

    /**
     * @param array<string,mixed> $sessao
     * @param list<array<string,mixed>> $ativos
     */
    private function imprimirResumo(array $sessao, array $ativos): void
    {
        $turmaId = (int) $sessao['turma_id'];
        $ano = (int) $sessao['ano_letivo'];
        $bim = (int) $sessao['bimestre'];
        $periodo = $this->diario->periodoDoBimestre($ano, $bim);
        $freqs = [];
        foreach ($this->frequencia->alunosPercentual($turmaId, $periodo['inicio'], $periodo['fim']) as $item) {
            $freqs[(int) $item['aluno_id']] = $item;
        }

        $notas = $this->db->fetchAll(
            "SELECT g.aluno_id, g.materia_nome, g.media_final
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             WHERE g.preview = 0 AND r.ano_letivo = :ano AND r.bimestre = :bim
               AND g.aluno_id IN (" . implode(',', array_map(static fn($a) => (int) $a['id'], $ativos)) . ")
             ORDER BY g.aluno_id, g.ordem_linha",
            ['ano' => $ano, 'bim' => $bim]
        ) ?: [];
        $abaixoPorAluno = [];
        foreach ($notas as $n) {
            if (is_numeric($n['media_final']) && (float) $n['media_final'] < NOTA_MEDIA) {
                $abaixoPorAluno[(int) $n['aluno_id']][] = (string) $n['materia_nome'] . '=' . $n['media_final'];
            }
        }

        println('');
        println('Resumo para o conselho:');
        foreach ($ativos as $al) {
            $aid = (int) $al['id'];
            $pct = $freqs[$aid]['percentual'] ?? null;
            $freqTxt = $pct === null ? '—' : number_format((float) $pct, 1, ',', '.') . '%';
            $abaixo = $abaixoPorAluno[$aid] ?? [];
            $sit = $abaixo !== [] ? 'recuperação' : (($pct !== null && $pct < 75) ? 'baixa frequência' : 'aprovado');
            println(sprintf(
                '  · %s | freq %s | %s%s',
                $al['nome'],
                $freqTxt,
                $sit,
                $abaixo !== [] ? ' (' . implode(', ', $abaixo) . ')' : ''
            ));
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

$sessaoId = argInt($argv ?? [], '--sessao', SESSAO_PADRAO);
if ($sessaoId <= 0) {
    fail('Informe --sessao=ID (ex.: --sessao=7).');
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

    $runner = new SeedConselhoClasse(Database::getInstance());
    exit($runner->executar($sessaoId));
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
