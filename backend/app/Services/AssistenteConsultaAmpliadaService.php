<?php
/**
 * Consultas extras do Assistente: turma, bloco de prova, jornadas do professor,
 * boletim e faltas do aluno (somente leitura).
 */

require_once __DIR__ . '/SaudeAprendizagemService.php';
require_once __DIR__ . '/ExamBlockResultsDashboardService.php';
require_once __DIR__ . '/JornadasRelatorioService.php';
require_once __DIR__ . '/FrequencyService.php';
require_once __DIR__ . '/ProvasAlunoConsultaService.php';
require_once __DIR__ . '/ProvasProfessorConsultaService.php';
require_once __DIR__ . '/../Models/Exams/ExamBlock.php';
require_once __DIR__ . '/../Models/System/BoletimConfig.php';

use App\Services\SaudeAprendizagemService;
use App\Services\ExamBlockResultsDashboardService;

class AssistenteConsultaAmpliadaService
{
    private $db;
    private ProvasAlunoConsultaService $alunos;
    private ProvasProfessorConsultaService $professores;

    public function __construct(
        ?ProvasAlunoConsultaService $alunos = null,
        ?ProvasProfessorConsultaService $professores = null
    ) {
        $this->db = Database::getInstance();
        $this->alunos = $alunos ?? new ProvasAlunoConsultaService();
        $this->professores = $professores ?? new ProvasProfessorConsultaService();
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,turma?:array,ano_letivo?:array,kpis?:array,alunos_atencao?:list,error?:string}
     */
    public function saudeTurma(array $filtros): array
    {
        $turma = $this->resolverTurma($filtros);
        if (empty($turma['ok'])) {
            return $turma;
        }
        $turmaId = (int) $turma['turma']['id'];
        $ano = $this->anoLetivoAtual((int) ($filtros['ano_letivo_id'] ?? 0));
        if ($ano === null) {
            return ['ok' => false, 'error' => 'Ano letivo não encontrado.'];
        }
        $anoId = (int) $ano['id'];

        $nivel = trim((string) ($filtros['nivel'] ?? ''));
        if ($nivel !== '' && !in_array($nivel, SaudeAprendizagemService::NIVEIS, true)) {
            $nivel = '';
        }

        $saude = new SaudeAprendizagemService();
        $resultado = $saude->analisar($anoId, $turmaId, $nivel !== '' ? $nivel : null);
        $linhas = $resultado['linhas'] ?? [];
        $kpis = $resultado['kpis'] ?? [];
        $atencao = [];
        foreach ($linhas as $l) {
            $n = (string) ($l['nivel'] ?? '');
            if (in_array($n, [SaudeAprendizagemService::NIVEL_CRITICO, SaudeAprendizagemService::NIVEL_ATENCAO], true)) {
                $atencao[] = [
                    'aluno_id' => (int) ($l['aluno_id'] ?? 0),
                    'nome' => trim((string) ($l['nome'] ?? '')),
                    'turma_nome' => trim((string) ($l['turma_nome'] ?? $turma['turma']['nome'] ?? '')),
                    'nivel' => $n,
                    'nivel_rotulo' => $saude->rotuloNivel($n),
                    'notas_media' => $l['notas_media'] ?? null,
                    'notas_indice_pct' => $l['notas_indice_pct'] ?? null,
                    'provas_media_pct' => $l['provas_media_pct'] ?? null,
                ];
            }
        }

        return [
            'ok' => true,
            'turma' => $turma['turma'],
            'ano_letivo' => $ano,
            'kpis' => $kpis,
            'total_alunos' => count($linhas),
            'alunos_atencao' => array_slice($atencao, 0, 40),
        ];
    }

    /**
     * Totais de acertos/erros das provas dos alunos da turma.
     *
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,turma?:array,total_acertos?:int,total_erros?:int,por_materia?:list,error?:string}
     */
    public function resumoProvasTurma(array $filtros): array
    {
        $turma = $this->resolverTurma($filtros);
        if (empty($turma['ok'])) {
            return $turma;
        }
        $turmaId = (int) $turma['turma']['id'];
        $dataInicio = $this->normalizarData($filtros['data_inicio'] ?? null);
        $dataFim = $this->normalizarData($filtros['data_fim'] ?? null);

        $sql = "SELECT m.nome AS materia_nome,
                       COALESCE(SUM(CASE WHEN r.correta = 1 THEN 1 ELSE 0 END), 0) AS acertos,
                       COALESCE(SUM(CASE WHEN r.correta = 0 THEN 1 ELSE 0 END), 0) AS erros,
                       COUNT(DISTINCT pr.prova_id) AS provas,
                       COUNT(DISTINCT pr.aluno_id) AS alunos
                FROM alunos a
                INNER JOIN provas_realizacoes pr ON pr.aluno_id = a.id AND pr.status = 'finalizado'
                INNER JOIN provas p ON p.id = pr.prova_id AND p.deleted_at IS NULL
                LEFT JOIN materias m ON m.id = p.materia_id
                LEFT JOIN provas_respostas r ON r.prova_id = pr.prova_id AND r.aluno_id = pr.aluno_id
                WHERE a.turma_id = :tid AND a.ativo = 1";
        $params = ['tid' => $turmaId];
        if ($dataInicio !== null) {
            $sql .= ' AND COALESCE(DATE(pr.finalizado_em), DATE(p.data_inicio), DATE(p.created_at)) >= :di';
            $params['di'] = $dataInicio;
        }
        if ($dataFim !== null) {
            $sql .= ' AND COALESCE(DATE(pr.finalizado_em), DATE(p.data_inicio), DATE(p.created_at)) <= :df';
            $params['df'] = $dataFim;
        }
        $sql .= ' GROUP BY m.nome ORDER BY erros DESC, m.nome ASC';

        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $porMateria = [];
        $totalAcertos = 0;
        $totalErros = 0;
        $totalProvas = 0;
        foreach ($rows as $r) {
            $a = (int) ($r['acertos'] ?? 0);
            $e = (int) ($r['erros'] ?? 0);
            $totalAcertos += $a;
            $totalErros += $e;
            $totalProvas += (int) ($r['provas'] ?? 0);
            $porMateria[] = [
                'materia' => trim((string) ($r['materia_nome'] ?? 'Sem matéria')) ?: 'Sem matéria',
                'provas' => (int) ($r['provas'] ?? 0),
                'alunos' => (int) ($r['alunos'] ?? 0),
                'acertos' => $a,
                'erros' => $e,
            ];
        }
        $tot = $totalAcertos + $totalErros;

        return [
            'ok' => true,
            'turma' => $turma['turma'],
            'total_provas' => $totalProvas,
            'total_acertos' => $totalAcertos,
            'total_erros' => $totalErros,
            'percentual_acerto' => $tot > 0 ? round(($totalAcertos / $tot) * 100, 1) : null,
            'por_materia' => $porMateria,
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,blocos?:list,error?:string}
     */
    public function buscarBlocos(array $filtros): array
    {
        $titulo = trim((string) ($filtros['titulo'] ?? $filtros['termo'] ?? ''));
        $limite = max(1, min(30, (int) ($filtros['limite'] ?? 15)));
        $filters = [];
        if ($titulo !== '') {
            $filters['titulo'] = $titulo;
        }
        $turmaId = (int) ($filtros['turma_id'] ?? 0);
        if ($turmaId <= 0) {
            $tn = trim((string) ($filtros['turma_nome'] ?? $filtros['turma'] ?? ''));
            if ($tn !== '') {
                $res = $this->resolverTurma(['turma_nome' => $tn]);
                if (!empty($res['ok'])) {
                    $turmaId = (int) $res['turma']['id'];
                }
            }
        }
        if ($turmaId > 0) {
            $filters['turma_id'] = $turmaId;
        }

        $blockModel = new ExamBlock();
        $rows = $blockModel->getAllFiltered($filters, $limite, 0) ?: [];
        $blocos = [];
        foreach ($rows as $r) {
            $blocos[] = [
                'bloco_id' => (int) ($r['id'] ?? 0),
                'titulo' => trim((string) ($r['titulo'] ?? '')),
                'data_prova' => isset($r['data_prova']) ? (string) $r['data_prova'] : null,
                'bimestre' => isset($r['bimestre']) ? (int) $r['bimestre'] : null,
                'ano_letivo' => isset($r['ano_letivo']) ? (int) $r['ano_letivo'] : null,
                'status' => isset($r['status']) ? (string) $r['status'] : null,
            ];
        }
        return ['ok' => true, 'blocos' => $blocos, 'total' => count($blocos)];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,bloco?:array,indicadores?:array,por_turma?:list,questoes_mais_erradas?:list,alunos_atencao?:list,error?:string,candidatos?:list}
     */
    public function resultadosBloco(array $filtros): array
    {
        $blocoId = (int) ($filtros['bloco_id'] ?? 0);
        if ($blocoId <= 0) {
            $titulo = trim((string) ($filtros['titulo'] ?? $filtros['bloco_titulo'] ?? ''));
            if ($titulo === '') {
                return ['ok' => false, 'error' => 'Informe bloco_id ou titulo.'];
            }
            $busca = $this->buscarBlocos(['titulo' => $titulo, 'limite' => 10]);
            $blocos = $busca['blocos'] ?? [];
            if ($blocos === []) {
                return ['ok' => false, 'error' => 'Nenhum bloco encontrado com esse título.'];
            }
            if (count($blocos) > 1) {
                return [
                    'ok' => false,
                    'aviso' => 'Há mais de um bloco. Escolha um:',
                    'candidatos' => $blocos,
                ];
            }
            $blocoId = (int) $blocos[0]['bloco_id'];
        }

        $blockModel = new ExamBlock();
        $bloco = $blockModel->findById($blocoId);
        if (!$bloco) {
            return ['ok' => false, 'error' => 'Bloco não encontrado.'];
        }
        if (($bloco['formato_evento'] ?? '') === 'lancamento_nota') {
            return ['ok' => false, 'error' => 'Este bloco é lançamento de nota; use o relatório de notas do admin.'];
        }

        $dash = new ExamBlockResultsDashboardService($this->db);
        $payload = $dash->build($blocoId, $bloco);

        $questoes = [];
        foreach (array_slice($payload['questoes_mais_erradas'] ?? [], 0, 12) as $q) {
            $enunciado = html_entity_decode(strip_tags((string) ($q['enunciado'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $enunciado = preg_replace('/\s+/u', ' ', trim($enunciado)) ?? '';
            if (mb_strlen($enunciado) > 160) {
                $enunciado = mb_substr($enunciado, 0, 159) . '…';
            }
            $questoes[] = [
                'questao_id' => (int) ($q['questao_id'] ?? 0),
                'enunciado' => $enunciado !== '' ? $enunciado : (string) ($q['enunciado_curto'] ?? ''),
                'erros' => (int) ($q['total_erros'] ?? 0),
                'acertos' => (int) ($q['total_acertos'] ?? 0),
                'percentual_erro' => $q['taxa_erro'] ?? null,
                'materia_nome' => trim((string) ($q['materia_nome'] ?? '')),
            ];
        }

        $atencao = [];
        foreach (array_slice($payload['alunos_atencao'] ?? [], 0, 30) as $a) {
            $atencao[] = [
                'aluno_id' => (int) ($a['aluno_id'] ?? 0),
                'nome' => trim((string) ($a['nome'] ?? '')),
                'turma_nome' => trim((string) ($a['turma_nome'] ?? '')),
                'percentual' => $a['percentual'] ?? null,
                'status_label' => $a['status_label'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'bloco' => [
                'bloco_id' => $blocoId,
                'titulo' => trim((string) ($bloco['titulo'] ?? '')),
                'data_prova' => isset($bloco['data_prova']) ? (string) $bloco['data_prova'] : null,
                'bimestre' => isset($bloco['bimestre']) ? (int) $bloco['bimestre'] : null,
            ],
            'indicadores' => $payload['indicadores'] ?? [],
            'por_turma' => array_slice($payload['por_turma'] ?? [], 0, 20),
            'por_disciplina' => array_slice($payload['por_disciplina'] ?? [], 0, 20),
            'questoes_mais_erradas' => $questoes,
            'alunos_atencao' => $atencao,
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,professor?:array,totais?:array,alunos_atencao?:list,error?:string,candidatos?:list}
     */
    public function resumoJornadasProfessor(array $filtros): array
    {
        $resolvido = $this->professores->resolverProfessor($filtros);
        if (!empty($resolvido['candidatos'])) {
            return $resolvido;
        }
        if (empty($resolvido['ok'])) {
            return $resolvido;
        }
        $professor = $resolvido['professor'];
        $jr = new JornadasRelatorioService($this->db);
        $relFiltros = [
            'jr_professor_id' => (int) $professor['id'],
            'executar' => 1,
            'limit' => 80,
            'page' => 1,
            'jr_somente_atencao' => !empty($filtros['somente_atencao']) ? 1 : 0,
        ];
        $anoJr = (int) ($filtros['ano_letivo'] ?? $filtros['jr_ano_letivo'] ?? 0);
        if ($anoJr > 0) {
            $relFiltros['jr_ano_letivo'] = $anoJr;
        }
        $turmaId = (int) ($filtros['turma_id'] ?? 0);
        if ($turmaId <= 0) {
            $tn = trim((string) ($filtros['turma_nome'] ?? $filtros['turma'] ?? ''));
            if ($tn !== '') {
                $tr = $this->resolverTurma(['turma_nome' => $tn]);
                if (!empty($tr['ok'])) {
                    $turmaId = (int) $tr['turma']['id'];
                }
            }
        }
        if ($turmaId > 0) {
            $relFiltros['tipo'] = 'turma';
            $relFiltros['turma_id'] = $turmaId;
        }
        $rel = $jr->relatorio($relFiltros);

        $atencao = [];
        foreach ($rel['por_aluno_completo'] ?? $rel['por_aluno'] ?? [] as $a) {
            if (!empty($a['precisa_atencao'])) {
                $atencao[] = [
                    'aluno_id' => (int) ($a['aluno_id'] ?? 0),
                    'nome' => trim((string) ($a['nome'] ?? '')),
                    'turma_nome' => trim((string) ($a['turma_nome'] ?? '')),
                    'taxa_pct' => $a['taxa_pct'] ?? null,
                    'concluidas' => $a['concluidas'] ?? null,
                    'pendentes' => $a['pendentes'] ?? null,
                ];
            }
            if (count($atencao) >= 30) {
                break;
            }
        }

        return [
            'ok' => true,
            'professor' => $professor,
            'totais' => $rel['totais'] ?? [],
            'jornadas_no_escopo' => $rel['jornadas_no_escopo'] ?? null,
            'alunos_atencao' => $atencao,
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,eventos?:list,error?:string,candidatos?:list}
     */
    public function boletimAluno(array $filtros): array
    {
        $aluno = $this->resolverAluno($filtros);
        if (empty($aluno['ok'])) {
            return $aluno;
        }
        $alunoId = (int) $aluno['aluno']['id'];
        $exibirEm = trim((string) ($filtros['exibir_em'] ?? ''));
        if ($exibirEm !== '' && !in_array($exibirEm, ['boletim', 'notas'], true)) {
            $exibirEm = '';
        }

        $boletim = new BoletimConfig();
        $eventos = $boletim->getGeneratedBoletinsByAluno(
            $alunoId,
            'coordenacao',
            $exibirEm !== '' ? $exibirEm : null
        );

        $slim = [];
        $totalEventos = count($eventos ?: []);
        foreach (array_slice($eventos ?: [], 0, 12) as $ev) {
            $linhas = [];
            foreach (array_slice($ev['linhas'] ?? [], 0, 40) as $linha) {
                $linhas[] = [
                    'materia' => trim((string) ($linha['materia_nome'] ?? '')),
                    'notas' => $linha['notas'] ?? [],
                ];
            }
            $slim[] = [
                'regra_nome' => trim((string) ($ev['regra_nome'] ?? $ev['nome'] ?? '')),
                'bimestre' => $ev['bimestre'] ?? null,
                'ano_letivo' => $ev['ano_letivo'] ?? null,
                'exibir_em' => $ev['exibir_em'] ?? null,
                'colunas' => $ev['colunas'] ?? [],
                'linhas' => $linhas,
            ];
        }

        return [
            'ok' => true,
            'aluno' => $aluno['aluno'],
            'eventos' => $slim,
            'total_eventos' => $totalEventos,
        ];
    }

    /**
     * Faltas/frequência do aluno na turma (período ou visão recente do ano).
     *
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,frequencia?:array,error?:string,candidatos?:list}
     */
    public function faltasAluno(array $filtros): array
    {
        $aluno = $this->resolverAluno($filtros);
        if (empty($aluno['ok'])) {
            return $aluno;
        }
        $a = $aluno['aluno'];
        $alunoId = (int) $a['id'];
        $turmaId = (int) ($a['turma_id'] ?? 0);
        if ($turmaId <= 0) {
            return ['ok' => false, 'error' => 'Aluno sem turma vinculada.'];
        }

        $inicio = $this->normalizarData($filtros['data_inicio'] ?? null);
        $fim = $this->normalizarData($filtros['data_fim'] ?? null);
        if ($inicio === null || $fim === null) {
            $ano = $this->anoLetivoAtual((int) ($filtros['ano_letivo_id'] ?? 0));
            $row = $ano !== null
                ? $this->db->fetch(
                    'SELECT data_inicio, data_fim, ano FROM ano_letivo WHERE id = :id LIMIT 1',
                    ['id' => (int) $ano['id']]
                )
                : null;
            if ($row) {
                $inicio = $inicio ?? (string) (($row['data_inicio'] ?? '') ?: ($row['ano'] . '-01-01'));
                $fim = $fim ?? (string) (($row['data_fim'] ?? '') ?: ($row['ano'] . '-12-31'));
            } else {
                $inicio = $inicio ?? date('Y-01-01');
                $fim = $fim ?? date('Y-m-d');
            }
        }

        $freq = new FrequencyService($this->db);
        $lista = $freq->alunosPercentual($turmaId, $inicio, $fim);
        $meu = null;
        foreach ($lista as $item) {
            if ((int) ($item['aluno_id'] ?? 0) === $alunoId) {
                $meu = $item;
                break;
            }
        }

        $turmaPct = $freq->turmaPercentual($turmaId, $inicio, $fim);

        return [
            'ok' => true,
            'aluno' => $a,
            'periodo' => ['inicio' => $inicio, 'fim' => $fim],
            'frequencia' => $meu ? [
                'total_aulas' => $meu['total_aulas'] ?? null,
                'presencas' => $meu['presencas'] ?? null,
                'faltas' => $meu['faltas'] ?? null,
                'faltas_justificadas' => $meu['faltas_justificadas'] ?? null,
                'percentual' => $meu['percentual'] ?? null,
            ] : null,
            'turma_percentual' => $turmaPct,
            'aviso' => $meu === null ? 'Sem lançamentos de frequência no período.' : null,
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,turma?:array{id:int,nome:string},error?:string,candidatos?:list}
     */
    private function resolverTurma(array $filtros): array
    {
        $id = (int) ($filtros['turma_id'] ?? 0);
        if ($id > 0) {
            $r = $this->db->fetch('SELECT id, nome FROM turmas WHERE id = :id LIMIT 1', ['id' => $id]);
            if (!$r) {
                return ['ok' => false, 'error' => 'Turma não encontrada.'];
            }
            return ['ok' => true, 'turma' => ['id' => (int) $r['id'], 'nome' => trim((string) $r['nome'])]];
        }
        $nome = trim((string) ($filtros['turma_nome'] ?? $filtros['turma'] ?? ''));
        if ($nome === '') {
            return ['ok' => false, 'error' => 'Informe turma_id ou turma_nome.'];
        }
        $rows = $this->alunos->buscarTurmasPorNome($nome, 20);
        if ($rows === []) {
            return ['ok' => false, 'error' => 'Nenhuma turma encontrada.'];
        }
        if (count($rows) === 1) {
            return [
                'ok' => true,
                'turma' => ['id' => (int) $rows[0]['id'], 'nome' => trim((string) $rows[0]['nome'])],
            ];
        }
        $candidatos = [];
        foreach ($rows as $r) {
            $candidatos[] = ['id' => (int) $r['id'], 'nome' => trim((string) $r['nome'])];
        }
        return [
            'ok' => false,
            'aviso' => 'Há mais de uma turma. Escolha uma:',
            'candidatos' => $candidatos,
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,error?:string,candidatos?:list,aviso?:string}
     */
    private function resolverAluno(array $filtros): array
    {
        $id = (int) ($filtros['aluno_id'] ?? 0);
        if ($id > 0) {
            $a = $this->alunos->obterAluno($id);
            if ($a === null) {
                return ['ok' => false, 'error' => 'Aluno não encontrado.'];
            }
            return ['ok' => true, 'aluno' => $a];
        }
        $nome = trim((string) ($filtros['aluno_nome'] ?? $filtros['nome'] ?? ''));
        $turma = trim((string) ($filtros['turma_nome'] ?? $filtros['turma'] ?? ''));
        if ($nome === '') {
            return ['ok' => false, 'error' => 'Informe aluno_id ou aluno_nome.'];
        }
        $lista = $this->alunos->buscarAlunos($nome, 15, $turma !== '' ? $turma : null);
        if ($lista === []) {
            return ['ok' => false, 'error' => 'Nenhum aluno encontrado.'];
        }
        if (count($lista) > 1) {
            return [
                'ok' => false,
                'aviso' => 'Há mais de um aluno. Escolha um:',
                'candidatos' => $lista,
            ];
        }
        return ['ok' => true, 'aluno' => $lista[0]];
    }

    /** @return array{id:int,ano:int}|null */
    private function anoLetivoAtual(int $preferido = 0): ?array
    {
        if ($preferido > 0) {
            $r = $this->db->fetch(
                'SELECT id, ano FROM ano_letivo WHERE id = :id LIMIT 1',
                ['id' => $preferido]
            );
            if ($r) {
                return ['id' => (int) $r['id'], 'ano' => (int) $r['ano']];
            }
        }
        try {
            $r = $this->db->fetch(
                'SELECT id, ano FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC LIMIT 1'
            );
            if ($r) {
                return ['id' => (int) $r['id'], 'ano' => (int) $r['ano']];
            }
        } catch (Throwable $e) {
            // coluna ativo pode não existir
        }
        $r = $this->db->fetch('SELECT id, ano FROM ano_letivo ORDER BY ano DESC LIMIT 1');
        if (!$r) {
            return null;
        }
        return ['id' => (int) $r['id'], 'ano' => (int) $r['ano']];
    }

    private function normalizarData($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = trim((string) $raw);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return substr($s, 0, 10);
        }
        return null;
    }
}
