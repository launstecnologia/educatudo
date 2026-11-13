<?php

namespace App\Painel\Admin\Services;
/**
 * Consultas extras do Assistente: turma, bloco de prova, jornadas do professor,
 * boletim, faltas e ocorrências do aluno (somente leitura).
 */


use App\Services\SaudeAprendizagemService;
use App\Dominio\Escola\Services\SaudeAcademicaService;
use App\Dominio\Escola\Services\GradeHorariaLeitura;
use App\Services\ExamBlockResultsDashboardService;
use App\Modulos\Provas\Services\ProvasAlunoConsultaService;
use App\Modulos\Provas\Services\ProvasProfessorConsultaService;
use App\Modulos\Jornadas\Services\JornadasRelatorioService;
use App\Modulos\Jornadas\Services\JornadasAlunoConsultaService;
use App\Modulos\Presenca\Services\FrequencyService;
use App\Modulos\Ocorrencias\Models\Ocorrencia;
use App\Modulos\Diario\Models\ClassDiary;

use App\Models\System\BoletimConfig;
use App\Core\Database;
use App\Models\Exams\ExamBlock;

class AssistenteConsultaAmpliadaService
{
    private $db;
    private ProvasAlunoConsultaService $alunos;
    private ProvasProfessorConsultaService $professores;
    /** @var array<string,bool> */
    private array $cacheColunasEventos = [];
    private ?bool $cacheTabelaEventos = null;

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
     * Boletim anual do aluno (quadro PP/PU/CH/PE/MU…), como na tela Boletim.
     *
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,formato?:string,quadro?:array,eventos?:list,error?:string,candidatos?:list}
     */
    public function boletimAluno(array $filtros): array
    {
        $aluno = $this->resolverAluno($filtros);
        if (empty($aluno['ok'])) {
            return $aluno;
        }
        $alunoId = (int) $aluno['aluno']['id'];
        $anoPedido = (int) ($filtros['ano_letivo'] ?? $filtros['ano'] ?? 0);
        $regraId = (int) ($filtros['regra_id'] ?? 0);
        $anosTentar = [];
        if ($anoPedido > 0) {
            $anosTentar[] = $anoPedido;
        } else {
            $anoAtual = $this->anoLetivoAtual();
            $anoBase = (int) ($anoAtual['ano'] ?? date('Y'));
            // Sem ano explícito: tenta o atual e os anteriores (boletim antigo ainda conta).
            foreach ([$anoBase, $anoBase - 1, $anoBase - 2, $anoBase - 3] as $y) {
                if ($y >= 2000) {
                    $anosTentar[] = $y;
                }
            }
        }

        $ultimoErroQuadro = null;
        foreach ($anosTentar as $anoFiltro) {
            $quadro = $this->montarQuadroBoletimAluno($alunoId, $anoFiltro, $regraId);
            if (
                !empty($quadro['ok'])
                && $this->quadroBoletimTemNotas($quadro)
            ) {
                return [
                    'ok' => true,
                    'formato' => 'quadro_anual',
                    'aluno' => $aluno['aluno'],
                    'ano_letivo' => $anoFiltro,
                    'regra' => $quadro['regra'] ?? null,
                    'quadro' => [
                        'colunas' => $quadro['colunas'] ?? [],
                        'grupos' => $quadro['grupos'] ?? [],
                        'linhas' => $quadro['linhas'] ?? [],
                    ],
                    'aviso' => $quadro['aviso'] ?? null,
                ];
            }
            if (!empty($quadro['ok']) && !$this->quadroBoletimTemNotas($quadro)) {
                $ultimoErroQuadro = 'Encontrei a estrutura do boletim de ' . $anoFiltro
                    . ', mas sem notas preenchidas nessa regra. Tentando outra fonte…';
            } else {
                $ultimoErroQuadro = $quadro['error'] ?? $ultimoErroQuadro;
            }
        }

        // Fallback: eventos já gerados (demonstrativos/snapshots).
        $boletim = new BoletimConfig();
        $boletim->ensureSchema();
        $eventos = $boletim->getGeneratedBoletinsByAluno($alunoId, 'coordenacao', null) ?: [];
        if ($anoPedido > 0) {
            $eventos = array_values(array_filter(
                $eventos,
                static fn($ev): bool => (int) ($ev['ano_letivo'] ?? 0) === $anoPedido
            ));
        }

        $slim = [];
        foreach (array_slice($eventos, 0, 12) as $ev) {
            $linhas = [];
            foreach (array_slice($ev['linhas'] ?? [], 0, 40) as $linha) {
                $linhas[] = [
                    'materia' => trim((string) ($linha['materia_nome'] ?? '')),
                    'notas' => $linha['notas'] ?? [],
                ];
            }
            if ($linhas === [] || empty($ev['colunas'])) {
                continue;
            }
            if (!$this->quadroBoletimTemNotas(['colunas' => $ev['colunas'] ?? [], 'linhas' => $linhas])) {
                continue;
            }
            $slim[] = [
                'regra_nome' => trim((string) ($ev['regra_nome'] ?? $ev['nome'] ?? '')),
                'bimestre' => $ev['bimestre'] ?? null,
                'ano_letivo' => $ev['ano_letivo'] ?? null,
                'exibir_em' => $ev['exibir_em'] ?? null,
                'finalidade' => $ev['finalidade'] ?? 'oficial',
                'colunas' => $ev['colunas'] ?? [],
                'linhas' => $linhas,
            ];
        }

        if ($slim === []) {
            $anoMsg = $anoPedido > 0 ? (string) $anoPedido : implode('/', $anosTentar);
            return [
                'ok' => false,
                'error' => 'Não encontrei boletim com notas preenchidas'
                    . ($anoMsg !== '' ? ' para ' . $anoMsg : '')
                    . '. A aluna pode ter notas lançadas, mas a regra de impressão não montou o quadro.',
                'aluno' => $aluno['aluno'],
            ];
        }

        $anoUsado = (int) ($slim[0]['ano_letivo'] ?? ($anoPedido > 0 ? $anoPedido : 0));

        return [
            'ok' => true,
            'formato' => 'eventos',
            'aluno' => $aluno['aluno'],
            'ano_letivo' => $anoUsado > 0 ? $anoUsado : null,
            'eventos' => $slim,
            'total_eventos' => count($slim),
        ];
    }

    /**
     * Notas de um evento avaliativo / demonstrativo (ex.: 1º bimestre, PP/MU).
     *
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,evento?:array,quadro?:array,error?:string,candidatos?:list}
     */
    public function notasEventoAluno(array $filtros): array
    {
        $aluno = $this->resolverAluno($filtros);
        if (empty($aluno['ok'])) {
            return $aluno;
        }
        $alunoId = (int) $aluno['aluno']['id'];
        $anoFiltro = (int) ($filtros['ano_letivo'] ?? $filtros['ano'] ?? 0);
        if ($anoFiltro <= 0) {
            $anoAtual = $this->anoLetivoAtual();
            $anoFiltro = $anoAtual['ano'] ?? (int) date('Y');
        }
        $bimestre = (int) ($filtros['bimestre'] ?? $filtros['periodo'] ?? 0);
        $regraId = (int) ($filtros['regra_id'] ?? $filtros['evento_id'] ?? 0);
        $eventoNome = trim((string) ($filtros['evento_nome'] ?? $filtros['nome'] ?? ''));
        $coluna = strtoupper(trim((string) ($filtros['coluna'] ?? $filtros['campo'] ?? '')));
        $materiaFiltro = trim((string) ($filtros['materia'] ?? $filtros['materia_nome'] ?? $filtros['disciplina'] ?? ''));

        $boletim = new BoletimConfig();
        $boletim->ensureSchema();
        $regra = null;
        if ($regraId > 0) {
            $regra = $boletim->getRuleById($regraId);
            if (is_array($regra) && (int) ($regra['ano_letivo'] ?? 0) !== $anoFiltro) {
                return [
                    'ok' => false,
                    'error' => 'O evento #' . $regraId . ' não pertence ao ano letivo ' . $anoFiltro . '.',
                    'aluno' => $aluno['aluno'],
                ];
            }
        }
        if (!is_array($regra) || empty($regra['componentes'])) {
            $resolvido = $this->resolverRegraEventoNotas($anoFiltro, $bimestre, $eventoNome);
            if (!empty($resolvido['ambiguidade'])) {
                return [
                    'ok' => false,
                    'aviso' => 'Há mais de um evento. Escolha um:',
                    'candidatos_eventos' => $resolvido['candidatos_eventos'] ?? [],
                    'aluno' => $aluno['aluno'],
                ];
            }
            $regra = $resolvido['regra'] ?? null;
        }
        if (!is_array($regra) || empty($regra['componentes'])) {
            $cands = $this->listarEventosNotasAno($anoFiltro, $bimestre);
            if (count($cands) === 1) {
                $unica = $boletim->getRuleById((int) $cands[0]['id']);
                if (is_array($unica) && !empty($unica['componentes'])) {
                    $regra = $unica;
                }
            } elseif (count($cands) > 1) {
                return [
                    'ok' => false,
                    'aviso' => 'Há mais de um evento. Escolha um:',
                    'candidatos_eventos' => $cands,
                    'aluno' => $aluno['aluno'],
                ];
            }
            if (!is_array($regra) || empty($regra['componentes'])) {
                return [
                    'ok' => false,
                    'error' => 'Evento avaliativo não encontrado para o filtro informado.',
                    'aluno' => $aluno['aluno'],
                ];
            }
        }

        $matriz = $this->simularMatrizRegraAluno($regra, $alunoId, $anoFiltro);
        if ($matriz === null) {
            return ['ok' => false, 'error' => 'Não foi possível calcular as notas deste evento.', 'aluno' => $aluno['aluno']];
        }

        $colunas = $matriz['colunas'];
        $linhas = $matriz['linhas'];
        if ($coluna !== '') {
            $codigos = [];
            foreach ($colunas as $c) {
                $nomeCol = strtoupper(trim((string) ($c['nome'] ?? $c['label'] ?? '')));
                $codCol = strtoupper(trim((string) ($c['codigo'] ?? $c['chave'] ?? '')));
                $codOrig = (string) ($c['codigo'] ?? $c['chave'] ?? '');
                $match = $nomeCol === $coluna
                    || $codCol === $coluna
                    || str_ends_with($codCol, '_' . $coluna)
                    || str_starts_with($codCol, $coluna . '_');
                if ($match) {
                    $codigos[] = $codOrig;
                }
            }
            $codigos = array_values(array_filter($codigos));
            if ($codigos === []) {
                // MU/MS/MA etc. costumam existir só no quadro anual — tenta extrair dali.
                $doQuadro = $this->extrairColunaQuadroAnual($alunoId, $anoFiltro, $bimestre, $coluna);
                if (!empty($doQuadro['ok'])) {
                    return [
                        'ok' => true,
                        'formato' => 'coluna_quadro',
                        'aluno' => $aluno['aluno'],
                        'ano_letivo' => $anoFiltro,
                        'evento' => [
                            'id' => (int) ($regra['id'] ?? 0),
                            'nome' => trim((string) ($regra['nome'] ?? 'Boletim')),
                            'bimestre' => $bimestre > 0 ? $bimestre : ($regra['bimestre'] ?? null),
                        ],
                        'coluna' => $coluna,
                        'quadro' => $doQuadro['quadro'],
                        'aviso' => 'Coluna "' . $coluna . '" veio do quadro do boletim (não está no demonstrativo do evento).',
                    ];
                }
                return [
                    'ok' => false,
                    'error' => 'Coluna "' . $coluna . '" não encontrada neste evento. Colunas: '
                        . implode(', ', array_map(
                            static fn($c): string => (string) ($c['nome'] ?? $c['codigo'] ?? ''),
                            $matriz['colunas']
                        )),
                    'aluno' => $aluno['aluno'],
                    'evento' => [
                        'id' => (int) ($regra['id'] ?? 0),
                        'nome' => trim((string) ($regra['nome'] ?? '')),
                    ],
                ];
            }
            $colunas = array_values(array_filter(
                $colunas,
                static fn($c): bool => in_array((string) ($c['codigo'] ?? $c['chave'] ?? ''), $codigos, true)
            ));
            foreach ($linhas as &$linha) {
                $notas = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
                $filtradas = [];
                foreach ($codigos as $cod) {
                    if (array_key_exists($cod, $notas)) {
                        $filtradas[$cod] = $notas[$cod];
                    }
                }
                $linha['notas'] = $filtradas;
            }
            unset($linha);
        }

        if ($materiaFiltro !== '') {
            $alvo = $this->normalizarTextoBusca($materiaFiltro);
            $linhas = array_values(array_filter(
                $linhas,
                function (array $linha) use ($alvo): bool {
                    $nome = $this->normalizarTextoBusca((string) ($linha['materia'] ?? $linha['materia_nome'] ?? ''));
                    return $nome !== '' && ($nome === $alvo || str_contains($nome, $alvo) || str_contains($alvo, $nome));
                }
            ));
            if ($linhas === []) {
                return [
                    'ok' => false,
                    'error' => 'Matéria "' . $materiaFiltro . '" não encontrada neste evento para o aluno.',
                    'aluno' => $aluno['aluno'],
                ];
            }
        }

        return [
            'ok' => true,
            'formato' => 'evento',
            'aluno' => $aluno['aluno'],
            'ano_letivo' => $anoFiltro,
            'evento' => [
                'id' => (int) ($regra['id'] ?? 0),
                'nome' => trim((string) ($regra['nome'] ?? '')),
                'bimestre' => $regra['bimestre'] ?? null,
                'exibir_em' => $regra['exibir_em'] ?? null,
            ],
            'coluna' => $coluna !== '' ? $coluna : null,
            'materia' => $materiaFiltro !== '' ? $materiaFiltro : null,
            'quadro' => [
                'colunas' => $colunas,
                'grupos' => $this->agruparCabecalhosColunas($colunas),
                'linhas' => $linhas,
            ],
        ];
    }

    /**
     * Catálogo vivo dos eventos avaliativos desta escola (sigla + nome).
     * Não é lista fixa — vem do cadastro em eventos_avaliativos.
     *
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,eventos:list<array<string,mixed>>,ano_letivo:?int}
     */
    public function listarEventosAvaliativos(array $filtros = []): array
    {
        $anoFiltro = (int) ($filtros['ano_letivo'] ?? $filtros['ano'] ?? 0);
        $bimestre = (int) ($filtros['bimestre'] ?? $filtros['periodo'] ?? 0);
        $termo = trim((string) ($filtros['termo'] ?? $filtros['busca'] ?? ''));

        if (!$this->tabelaEventosAvaliativosExiste()) {
            return ['ok' => false, 'error' => 'Módulo de eventos avaliativos indisponível neste tenant.', 'eventos' => [], 'ano_letivo' => null];
        }

        $temSigla = $this->colunaEventosAvaliativosExiste('sigla');
        $params = [];
        $where = ['e.deleted_at IS NULL'];
        if ($this->colunaEventosAvaliativosExiste('rascunho')) {
            $where[] = '(e.rascunho IS NULL OR e.rascunho = 0)';
        }
        if ($anoFiltro <= 0) {
            $anoAtual = $this->anoLetivoAtual();
            $anoFiltro = (int) ($anoAtual['ano'] ?? date('Y'));
        }
        if ($anoFiltro > 0) {
            $where[] = 'e.ano_letivo = :ano';
            $params['ano'] = $anoFiltro;
        }
        if ($bimestre > 0) {
            $where[] = 'e.bimestre = :bim';
            $params['bim'] = $bimestre;
        }
        if ($termo !== '') {
            $termoUp = strtoupper(preg_replace('/\s+/u', '', $termo) ?? '');
            if ($temSigla) {
                $where[] = '(UPPER(TRIM(e.sigla)) = :termo_sigla OR e.nome LIKE :termo_nome OR UPPER(TRIM(e.sigla)) LIKE :termo_sigla_like)';
                $params['termo_sigla'] = $termoUp;
                $params['termo_sigla_like'] = '%' . $termoUp . '%';
            } else {
                $where[] = 'e.nome LIKE :termo_nome';
            }
            $params['termo_nome'] = '%' . $termo . '%';
        }

        $sql = 'SELECT e.id, e.nome, e.ano_letivo, e.bimestre'
            . ($temSigla ? ', e.sigla' : ', NULL AS sigla')
            . ' FROM eventos_avaliativos e WHERE ' . implode(' AND ', $where)
            . ' ORDER BY e.bimestre ASC, e.nome ASC LIMIT 80';
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'nome' => trim((string) ($r['nome'] ?? '')),
                'sigla' => strtoupper(trim((string) ($r['sigla'] ?? ''))),
                'ano_letivo' => isset($r['ano_letivo']) ? (int) $r['ano_letivo'] : null,
                'bimestre' => isset($r['bimestre']) ? (int) $r['bimestre'] : null,
            ];
        }
        return ['ok' => true, 'eventos' => $out, 'ano_letivo' => $anoFiltro > 0 ? $anoFiltro : null];
    }

    /**
     * Nota lançada em Evento Avaliativo comparando SIGLA ou NOME cadastrados no sistema + matéria.
     * Fonte: eventos_avaliativos → provas_blocos → provas_blocos_notas_lancadas.
     *
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function notaEventoPorSigla(array $filtros): array
    {
        $sigla = strtoupper(trim((string) ($filtros['sigla'] ?? $filtros['evento_sigla'] ?? $filtros['codigo'] ?? '')));
        $sigla = preg_replace('/\s+/u', '', $sigla) ?? '';
        $eventoNome = trim((string) ($filtros['evento_nome'] ?? $filtros['evento'] ?? $filtros['nome_evento'] ?? ''));
        $termo = trim((string) ($filtros['termo'] ?? $filtros['avaliacao'] ?? $filtros['prova'] ?? ''));
        if ($sigla === '' && $eventoNome === '' && $termo !== '') {
            $termoCompacto = strtoupper(preg_replace('/\s+/u', '', $termo) ?? '');
            if (preg_match('/^[A-Z0-9]{1,12}$/', $termoCompacto) === 1) {
                $sigla = $termoCompacto;
            } else {
                $eventoNome = $termo;
            }
        }
        // Não repassa termo/prova ao resolverAluno (evita buscar aluno "prova bimestral").
        $filtrosAluno = $filtros;
        unset(
            $filtrosAluno['termo'],
            $filtrosAluno['avaliacao'],
            $filtrosAluno['prova'],
            $filtrosAluno['evento'],
            $filtrosAluno['evento_nome'],
            $filtrosAluno['nome_evento'],
            $filtrosAluno['sigla'],
            $filtrosAluno['evento_sigla'],
            $filtrosAluno['codigo']
        );

        $aluno = $this->resolverAluno($filtrosAluno);
        if (empty($aluno['ok'])) {
            return $aluno;
        }
        $alunoId = (int) $aluno['aluno']['id'];
        $turmaId = (int) ($aluno['aluno']['turma_id'] ?? 0);

        $materiaFiltro = trim((string) ($filtros['materia'] ?? $filtros['materia_nome'] ?? $filtros['disciplina'] ?? ''));
        $anoFiltro = (int) ($filtros['ano_letivo'] ?? $filtros['ano'] ?? 0);
        $bimestre = (int) ($filtros['bimestre'] ?? $filtros['periodo'] ?? 0);
        $eventoIdFiltro = (int) ($filtros['evento_id'] ?? $filtros['evento_avaliativo_id'] ?? 0);

        if ($eventoIdFiltro <= 0 && $sigla === '' && $eventoNome === '') {
            $catalogo = $this->listarEventosAvaliativos([
                'ano_letivo' => $anoFiltro,
                'bimestre' => $bimestre,
            ]);
            return [
                'ok' => false,
                'error' => 'Informe a sigla ou o nome do evento avaliativo cadastrado nesta escola.',
                'aluno' => $aluno['aluno'],
                'eventos_disponiveis' => $catalogo['eventos'] ?? [],
            ];
        }

        if (!$this->tabelaEventosAvaliativosExiste()) {
            return [
                'ok' => false,
                'error' => 'Módulo de eventos avaliativos indisponível neste tenant.',
                'aluno' => $aluno['aluno'],
            ];
        }

        $temSigla = $this->colunaEventosAvaliativosExiste('sigla');

        $params = [];
        $where = ['e.deleted_at IS NULL'];
        if ($this->colunaEventosAvaliativosExiste('rascunho')) {
            $where[] = '(e.rascunho IS NULL OR e.rascunho = 0)';
        }
        if ($anoFiltro > 0) {
            $where[] = 'e.ano_letivo = :ano';
            $params['ano'] = $anoFiltro;
        }
        if ($bimestre > 0) {
            $where[] = 'e.bimestre = :bim';
            $params['bim'] = $bimestre;
        }
        if ($eventoIdFiltro > 0) {
            $where[] = 'e.id = :evento_id';
            $params['evento_id'] = $eventoIdFiltro;
        } else {
            // Compara com o cadastro: sigla exata OU nome (exato / parcial).
            $ors = [];
            if ($sigla !== '' && $temSigla) {
                $ors[] = 'UPPER(TRIM(e.sigla)) = :sigla';
                $params['sigla'] = $sigla;
            }
            if ($sigla !== '') {
                $ors[] = 'UPPER(TRIM(e.nome)) = :sigla_eq';
                $ors[] = 'e.nome LIKE :sigla_like';
                $params['sigla_eq'] = $sigla;
                $params['sigla_like'] = '%' . $sigla . '%';
            }
            if ($eventoNome !== '') {
                $ors[] = 'e.nome LIKE :evento_nome';
                $params['evento_nome'] = '%' . $eventoNome . '%';
                $nomeCompacto = strtoupper(preg_replace('/\s+/u', '', $eventoNome) ?? '');
                if ($temSigla && $nomeCompacto !== '' && preg_match('/^[A-Z0-9]{1,12}$/', $nomeCompacto) === 1) {
                    $ors[] = 'UPPER(TRIM(e.sigla)) = :nome_como_sigla';
                    $params['nome_como_sigla'] = $nomeCompacto;
                }
            }
            if ($ors !== []) {
                $where[] = '(' . implode(' OR ', $ors) . ')';
            }
        }

        $sqlEventos = 'SELECT e.id, e.nome, e.ano_letivo, e.bimestre'
            . ($temSigla ? ', e.sigla' : ', NULL AS sigla')
            . ' FROM eventos_avaliativos e WHERE ' . implode(' AND ', $where)
            . ' ORDER BY e.ano_letivo DESC, e.id DESC LIMIT 20';
        $eventos = $this->db->fetchAll($sqlEventos, $params) ?: [];

        // Fallback: compara o termo falado com o catálogo vivo (sigla/nome) sem lista fixa.
        if ($eventos === [] && $eventoIdFiltro <= 0) {
            $eventos = $this->resolverEventosPorCatalogo(
                $sigla !== '' ? $sigla : $eventoNome,
                $anoFiltro,
                $bimestre
            );
        }

        if ($eventos === []) {
            $catalogo = $this->listarEventosAvaliativos([
                'ano_letivo' => $anoFiltro,
                'bimestre' => $bimestre,
            ]);
            return [
                'ok' => false,
                'error' => 'Nenhum evento avaliativo cadastrado corresponde a'
                    . ($sigla !== '' ? ' sigla "' . $sigla . '"' : '')
                    . ($eventoNome !== '' ? ' nome "' . $eventoNome . '"' : '')
                    . '. Confira as siglas/nomes disponíveis nesta escola.',
                'aluno' => $aluno['aluno'],
                'eventos_disponiveis' => $catalogo['eventos'] ?? [],
            ];
        }
        if (count($eventos) > 1) {
            // Se a sigla bateu em vários anos/bimestres, prefere o da turma do aluno quando possível.
            $filtradosTurma = [];
            if ($turmaId > 0) {
                foreach ($eventos as $ev) {
                    $temTurma = $this->db->fetch(
                        'SELECT 1 AS ok
                           FROM provas_blocos pb
                           INNER JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pb.id AND pbt.turma_id = :turma
                          WHERE pb.evento_avaliativo_id = :ev AND pb.deleted_at IS NULL
                          LIMIT 1',
                        ['turma' => $turmaId, 'ev' => (int) $ev['id']]
                    );
                    if ($temTurma) {
                        $filtradosTurma[] = $ev;
                    }
                }
            }
            $listaBase = $filtradosTurma !== [] ? $filtradosTurma : $eventos;
            if (count($listaBase) > 1) {
                $cands = [];
                foreach ($listaBase as $ev) {
                    $cands[] = [
                        'evento_id' => (int) $ev['id'],
                        'nome' => trim((string) ($ev['nome'] ?? '')),
                        'sigla' => strtoupper(trim((string) ($ev['sigla'] ?? ''))),
                        'ano_letivo' => isset($ev['ano_letivo']) ? (int) $ev['ano_letivo'] : null,
                        'bimestre' => isset($ev['bimestre']) ? (int) $ev['bimestre'] : null,
                    ];
                }
                return [
                    'ok' => false,
                    'aviso' => 'Há mais de um evento com essa sigla. Escolha um:',
                    'candidatos_eventos_sigla' => $cands,
                    'aluno' => $aluno['aluno'],
                ];
            }
            $eventos = $listaBase;
        }

        $evento = $eventos[0];
        $eventoId = (int) $evento['id'];

        $paramsNota = ['aluno' => $alunoId, 'evento' => $eventoId];
        $sqlNota = 'SELECT n.nota, n.materia_id, m.nome AS materia_nome,
                           pb.id AS bloco_id, pb.titulo AS bloco_titulo, pb.data_prova,
                           e.nome AS evento_nome, e.ano_letivo, e.bimestre'
            . ($temSigla ? ', e.sigla' : ', NULL AS sigla')
            . ' FROM provas_blocos_notas_lancadas n
               INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
               INNER JOIN eventos_avaliativos e ON e.id = pb.evento_avaliativo_id AND e.id = :evento
               LEFT JOIN materias m ON m.id = n.materia_id
              WHERE n.aluno_id = :aluno AND n.nota IS NOT NULL';
        if ($materiaFiltro !== '') {
            $sqlNota .= ' AND m.nome LIKE :materia';
            $paramsNota['materia'] = '%' . $materiaFiltro . '%';
        }
        $sqlNota .= ' ORDER BY pb.data_prova DESC, n.id DESC LIMIT 30';
        $notas = $this->db->fetchAll($sqlNota, $paramsNota) ?: [];

        if ($notas === [] && $materiaFiltro !== '') {
            // Sem filtro de matéria para mostrar o que existe.
            $todas = $this->db->fetchAll(
                'SELECT n.nota, m.nome AS materia_nome
                   FROM provas_blocos_notas_lancadas n
                   INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
                   LEFT JOIN materias m ON m.id = n.materia_id
                  WHERE n.aluno_id = :aluno AND pb.evento_avaliativo_id = :evento AND n.nota IS NOT NULL
                  ORDER BY m.nome ASC LIMIT 40',
                ['aluno' => $alunoId, 'evento' => $eventoId]
            ) ?: [];
            if ($todas === []) {
                return [
                    'ok' => false,
                    'error' => 'Não há nota lançada da '
                        . ($sigla !== '' ? $sigla : trim((string) $evento['nome']))
                        . ' para este aluno'
                        . ($materiaFiltro !== '' ? ' em ' . $materiaFiltro : '') . '.',
                    'aluno' => $aluno['aluno'],
                    'evento' => [
                        'id' => $eventoId,
                        'nome' => trim((string) ($evento['nome'] ?? '')),
                        'sigla' => strtoupper(trim((string) ($evento['sigla'] ?? $sigla))),
                    ],
                ];
            }
            return [
                'ok' => false,
                'error' => 'Não achei "' . $materiaFiltro . '" neste evento. Matérias com nota:',
                'aluno' => $aluno['aluno'],
                'evento' => [
                    'id' => $eventoId,
                    'nome' => trim((string) ($evento['nome'] ?? '')),
                    'sigla' => strtoupper(trim((string) ($evento['sigla'] ?? $sigla))),
                ],
                'materias_com_nota' => array_values(array_unique(array_filter(array_map(
                    static fn($r) => trim((string) ($r['materia_nome'] ?? '')),
                    $todas
                )))),
            ];
        }

        if ($notas === []) {
            return [
                'ok' => false,
                'error' => 'Não há nota lançada da '
                    . ($sigla !== '' ? $sigla : trim((string) $evento['nome']))
                    . ' para este aluno.',
                'aluno' => $aluno['aluno'],
                'evento' => [
                    'id' => $eventoId,
                    'nome' => trim((string) ($evento['nome'] ?? '')),
                    'sigla' => strtoupper(trim((string) ($evento['sigla'] ?? $sigla))),
                ],
            ];
        }

        // Uma matéria pedida: devolve a nota principal (mais recente).
        if ($materiaFiltro !== '' && count($notas) >= 1) {
            $n = $notas[0];
            return [
                'ok' => true,
                'formato' => 'nota_unica',
                'aluno' => $aluno['aluno'],
                'evento' => [
                    'id' => $eventoId,
                    'nome' => trim((string) ($n['evento_nome'] ?? $evento['nome'] ?? '')),
                    'sigla' => strtoupper(trim((string) ($n['sigla'] ?? $evento['sigla'] ?? $sigla))),
                    'ano_letivo' => isset($n['ano_letivo']) ? (int) $n['ano_letivo'] : null,
                    'bimestre' => isset($n['bimestre']) ? (int) $n['bimestre'] : null,
                ],
                'materia' => trim((string) ($n['materia_nome'] ?? $materiaFiltro)),
                'nota' => $this->formatarNotaLancada($n['nota'] ?? null),
                'bloco_id' => (int) ($n['bloco_id'] ?? 0),
                'data_prova' => (string) ($n['data_prova'] ?? ''),
            ];
        }

        $linhas = [];
        foreach ($notas as $n) {
            $mat = trim((string) ($n['materia_nome'] ?? 'Sem matéria'));
            $linhas[] = [
                'materia' => $mat,
                'nota' => $this->formatarNotaLancada($n['nota'] ?? null),
                'bloco_titulo' => trim((string) ($n['bloco_titulo'] ?? '')),
                'data_prova' => (string) ($n['data_prova'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'formato' => 'lista_notas',
            'aluno' => $aluno['aluno'],
            'evento' => [
                'id' => $eventoId,
                'nome' => trim((string) ($evento['nome'] ?? '')),
                'sigla' => strtoupper(trim((string) ($evento['sigla'] ?? $sigla))),
                'ano_letivo' => isset($evento['ano_letivo']) ? (int) $evento['ano_letivo'] : null,
                'bimestre' => isset($evento['bimestre']) ? (int) $evento['bimestre'] : null,
            ],
            'notas' => $linhas,
        ];
    }

    /** @param mixed $nota */
    private function formatarNotaLancada($nota): ?string
    {
        if ($nota === null || $nota === '') {
            return null;
        }
        if (is_numeric($nota)) {
            $n = (float) $nota;
            return fmod($n, 1.0) === 0.0
                ? (string) (int) $n
                : rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
        }
        $s = trim((string) $nota);
        return $s !== '' ? $s : null;
    }

    private function tabelaEventosAvaliativosExiste(): bool
    {
        if ($this->cacheTabelaEventos !== null) {
            return $this->cacheTabelaEventos;
        }
        try {
            $r = $this->db->fetchAll("SHOW TABLES LIKE 'eventos_avaliativos'");
            $this->cacheTabelaEventos = !empty($r);
        } catch (\Throwable $e) {
            $this->cacheTabelaEventos = false;
        }
        return $this->cacheTabelaEventos;
    }

    private function colunaEventosAvaliativosExiste(string $coluna): bool
    {
        $coluna = trim($coluna);
        if ($coluna === '') {
            return false;
        }
        if (array_key_exists($coluna, $this->cacheColunasEventos)) {
            return $this->cacheColunasEventos[$coluna];
        }
        if (!$this->tabelaEventosAvaliativosExiste()) {
            $this->cacheColunasEventos[$coluna] = false;
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $coluna)) {
            $this->cacheColunasEventos[$coluna] = false;
            return false;
        }
        try {
            $r = $this->db->fetchAll("SHOW COLUMNS FROM eventos_avaliativos LIKE '" . $coluna . "'");
            $this->cacheColunasEventos[$coluna] = !empty($r);
        } catch (\Throwable $e) {
            $this->cacheColunasEventos[$coluna] = false;
        }
        return $this->cacheColunasEventos[$coluna];
    }

    /**
     * Resolve eventos comparando o termo falado com sigla/nome cadastrados (fuzzy).
     *
     * @return list<array<string,mixed>>
     */
    private function resolverEventosPorCatalogo(string $termo, int $anoFiltro, int $bimestre): array
    {
        $termo = trim($termo);
        if ($termo === '') {
            return [];
        }
        $catalogo = $this->listarEventosAvaliativos([
            'ano_letivo' => $anoFiltro,
            'bimestre' => $bimestre,
        ]);
        $lista = is_array($catalogo['eventos'] ?? null) ? $catalogo['eventos'] : [];
        if ($lista === []) {
            return [];
        }

        $termoNorm = $this->normalizarTermoEvento($termo);
        $termoCompacto = strtoupper(preg_replace('/\s+/u', '', $termoNorm) ?? '');
        $melhores = [];
        $melhorScore = 0;

        foreach ($lista as $ev) {
            $sigla = strtoupper(trim((string) ($ev['sigla'] ?? '')));
            $nome = trim((string) ($ev['nome'] ?? ''));
            $nomeNorm = $this->normalizarTermoEvento($nome);
            $nomeCompacto = strtoupper(preg_replace('/\s+/u', '', $nomeNorm) ?? '');
            $score = 0;

            if ($sigla !== '' && $sigla === $termoCompacto) {
                $score = 100;
            } elseif ($nomeCompacto !== '' && $nomeCompacto === $termoCompacto) {
                $score = 95;
            } elseif ($nomeNorm !== '' && $termoNorm !== '' && str_contains($nomeNorm, $termoNorm)) {
                $score = 80;
            } elseif ($nomeNorm !== '' && $termoNorm !== '' && str_contains($termoNorm, $nomeNorm)) {
                $score = 75;
            } elseif ($sigla !== '' && str_contains($termoCompacto, $sigla)) {
                $score = 70;
            }

            if ($score <= 0) {
                continue;
            }
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhores = [$ev];
            } elseif ($score === $melhorScore) {
                $melhores[] = $ev;
            }
        }

        if ($melhorScore < 70 || $melhores === []) {
            return [];
        }

        // Reconsulta no formato SQL (id/nome/sigla/ano/bimestre).
        $ids = array_values(array_unique(array_map(
            static fn($e) => (int) ($e['id'] ?? 0),
            $melhores
        )));
        $ids = array_values(array_filter($ids, static fn($id) => $id > 0));
        if ($ids === []) {
            return [];
        }

        $temSigla = $this->colunaEventosAvaliativosExiste('sigla');
        $ph = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $k = 'id' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $sql = 'SELECT e.id, e.nome, e.ano_letivo, e.bimestre'
            . ($temSigla ? ', e.sigla' : ', NULL AS sigla')
            . ' FROM eventos_avaliativos e WHERE e.id IN (' . implode(',', $ph) . ')'
            . ' ORDER BY e.ano_letivo DESC, e.id DESC';
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    private function normalizarTermoEvento(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');
        $t = $this->normalizarTextoBusca($t);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        return trim($t);
    }

    /**
     * Saúde acadêmica/cadastral da escola (não confundir com saúde de aprendizagem da turma).
     *
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function saudeAcademica(array $filtros): array
    {
        $ano = $this->resolverAnoLetivoFiltro($filtros);
        if ($ano === null) {
            return ['ok' => false, 'error' => 'Ano letivo não encontrado.'];
        }

        $turma = null;
        $turmaId = 0;
        if (!empty($filtros['turma_id']) || !empty($filtros['turma_nome']) || !empty($filtros['turma'])) {
            $turma = $this->resolverTurma($filtros);
            if (empty($turma['ok'])) {
                return $turma;
            }
            $turmaId = (int) ($turma['turma']['id'] ?? 0);
        }

        $tipo = trim((string) ($filtros['tipo_alerta'] ?? $filtros['tipo'] ?? ''));
        if ($tipo !== '' && !in_array($tipo, SaudeAcademicaService::TIPOS_ALERTA, true)) {
            return ['ok' => false, 'error' => 'Tipo de alerta inválido para saúde acadêmica.'];
        }

        $limite = max(1, min(100, (int) ($filtros['limite'] ?? 30)));
        $svc = new SaudeAcademicaService();
        $kpis = $svc->calcularKpis((int) $ano['id']);
        $rows = $svc->buscarAlertas((int) $ano['id'], $tipo !== '' ? $tipo : null, $turmaId);
        if ($turmaId > 0) {
            $kpis = array_fill_keys(SaudeAcademicaService::TIPOS_ALERTA, 0);
            foreach ($rows as $row) {
                $tipoRow = (string) ($row['tipo_alerta'] ?? '');
                if (isset($kpis[$tipoRow])) {
                    $kpis[$tipoRow]++;
                }
            }
            $kpis['total'] = array_sum($kpis);
        }

        $alertas = [];
        foreach (array_slice($rows, 0, $limite) as $r) {
            $tipoRow = (string) ($r['tipo_alerta'] ?? '');
            $alertas[] = [
                'aluno_id' => (int) ($r['aluno_id'] ?? 0),
                'nome' => trim((string) ($r['nome'] ?? '')),
                'ra' => isset($r['ra']) && $r['ra'] !== '' ? trim((string) $r['ra']) : null,
                'turma_nome' => trim((string) ($r['turma_nome'] ?? '')),
                'tipo' => $tipoRow,
                'tipo_rotulo' => $svc->rotuloTipoAlerta($tipoRow),
                'descricao' => trim((string) ($r['descricao'] ?? '')),
            ];
        }

        return [
            'ok' => true,
            'natureza' => 'governanca_cadastral',
            'ano_letivo' => $ano,
            'turma' => $turma['turma'] ?? null,
            'kpis' => $kpis,
            'alertas' => $alertas,
            'total_alertas' => count($rows),
            'tipo_alerta' => $tipo !== '' ? $tipo : null,
            'aviso' => $alertas === [] ? 'Nenhum alerta cadastral encontrado para este filtro.' : null,
        ];
    }

    /**
     * Visão 360 resumida do aluno para coordenação.
     *
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function perfilAluno360(array $filtros): array
    {
        $aluno = $this->resolverAluno($filtros);
        if (empty($aluno['ok'])) {
            return $aluno;
        }

        $a = $aluno['aluno'];
        $alunoId = (int) ($a['id'] ?? 0);
        $ano = $this->resolverAnoLetivoFiltro($filtros);

        $baseAluno = $this->db->fetch(
            "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id
             LIMIT 1",
            ['id' => $alunoId]
        ) ?: [];

        $provas = $this->alunos->resumoProvasAluno(['aluno_id' => $alunoId]);
        $jornadas = ['ok' => false];
        try {
            $jornadas = (new JornadasAlunoConsultaService($this->alunos))->resumoJornadasAluno(['aluno_id' => $alunoId]);
        } catch (\Throwable $e) {
            $jornadas = ['ok' => false, 'error' => 'Resumo de jornadas indisponível nesta base.'];
        }
        $faltas = $this->faltasAluno([
            'aluno_id' => $alunoId,
            'ano_letivo' => $ano['ano'] ?? null,
            'ano_letivo_id' => $ano['id'] ?? null,
        ]);
        $ocorrencias = $this->ocorrenciasAluno([
            'aluno_id' => $alunoId,
            'status' => 'aberta',
            'ano_letivo' => $ano['ano'] ?? null,
        ]);

        $indicadores = [
            'provas_pct' => $provas['percentual_acerto'] ?? null,
            'frequencia_pct' => $faltas['frequencia']['percentual'] ?? null,
            'jornadas_pct' => $jornadas['percentual_medio'] ?? null,
            'media_provas' => $provas['percentual_acerto'] ?? null,
        ];
        $atividade = [
            'total_provas' => (int) ($provas['total_provas'] ?? 0),
            'total_jornadas' => (int) ($jornadas['total_jornadas'] ?? 0),
            'jornadas_concluidas' => (int) ($jornadas['concluidas'] ?? 0),
            'jornadas_em_andamento' => (int) ($jornadas['em_andamento'] ?? 0),
        ];
        $pendencias = [
            'ocorrencias_abertas' => (int) ($ocorrencias['total'] ?? 0),
            'faltas' => isset($faltas['frequencia']['faltas']) ? (int) $faltas['frequencia']['faltas'] : null,
            'sem_frequencia' => empty($faltas['frequencia']),
        ];

        $destaques = [];
        if (($ocorrencias['total'] ?? 0) > 0) {
            $destaques[] = (int) $ocorrencias['total'] . ' ocorrência(s) aberta(s).';
        }
        if (!empty($faltas['aviso'])) {
            $destaques[] = (string) $faltas['aviso'];
        } elseif (isset($faltas['frequencia']['faltas'])) {
            $destaques[] = 'Faltas no período: ' . (int) $faltas['frequencia']['faltas'] . '.';
        }
        if (($provas['total_provas'] ?? 0) > 0 && isset($provas['percentual_acerto'])) {
            $destaques[] = 'Acerto médio em provas: ' . $provas['percentual_acerto'] . '%.';
        }
        if (($jornadas['total_jornadas'] ?? 0) > 0) {
            $destaques[] = 'Jornadas: ' . (int) ($jornadas['concluidas'] ?? 0) . ' concluída(s) de '
                . (int) ($jornadas['total_jornadas'] ?? 0) . '.';
        } elseif (!empty($jornadas['error'])) {
            $destaques[] = (string) $jornadas['error'];
        }

        return [
            'ok' => true,
            'aluno' => [
                'id' => $alunoId,
                'nome' => trim((string) ($baseAluno['nome'] ?? $a['nome'] ?? '')),
                'ra' => $baseAluno['ra'] ?? ($a['ra'] ?? null),
                'turma_id' => isset($baseAluno['turma_id']) ? (int) $baseAluno['turma_id'] : ($a['turma_id'] ?? null),
                'turma_nome' => trim((string) ($baseAluno['turma_nome'] ?? $a['turma_nome'] ?? '')),
            ],
            'ano_letivo' => $ano,
            'indicadores' => $indicadores,
            'atividade' => $atividade,
            'pendencias' => $pendencias,
            'destaques' => array_slice($destaques, 0, 6),
        ];
    }

    /**
     * Diário de classe: pendências por professor/turma/matéria.
     *
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function diarioPendencias(array $filtros): array
    {
        $status = trim((string) ($filtros['situacao'] ?? $filtros['status'] ?? ''));
        if ($status !== '' && !in_array($status, ['em_dia', 'atencao', 'atraso'], true)) {
            return ['ok' => false, 'error' => 'Situação inválida para pendências do diário.'];
        }

        [$inicioBase, $fimBase] = $this->periodoFiltro($filtros);
        $inicio = $this->normalizarData($filtros['data_inicio'] ?? $filtros['inicio'] ?? null) ?? $inicioBase ?? date('Y-m-d', strtotime('-45 days'));
        $fim = $this->normalizarData($filtros['data_fim'] ?? $filtros['fim'] ?? null) ?? $fimBase ?? date('Y-m-d');
        if ($inicio > $fim) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $professor = null;
        $professorId = 0;
        if (!empty($filtros['professor_id']) || !empty($filtros['professor_nome']) || !empty($filtros['nome'])) {
            $professor = $this->professores->resolverProfessor($filtros);
            if (empty($professor['ok'])) {
                return $professor;
            }
            $professorId = (int) ($professor['professor']['id'] ?? 0);
        }

        $turma = null;
        $turmaId = 0;
        if (!empty($filtros['turma_id']) || !empty($filtros['turma_nome']) || !empty($filtros['turma'])) {
            $turma = $this->resolverTurma($filtros);
            if (empty($turma['ok'])) {
                return $turma;
            }
            $turmaId = (int) ($turma['turma']['id'] ?? 0);
        }

        $rows = $this->indicadoresDiarioPeriodoCompleto($inicio, $fim, $professorId);
        if ($turmaId > 0) {
            $rows = array_values(array_filter(
                $rows,
                static fn(array $r): bool => (int) ($r['turma_id'] ?? 0) === $turmaId
            ));
        }
        if ($status !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn(array $r): bool => (string) ($r['situacao'] ?? '') === $status
            ));
        }

        usort($rows, static function (array $a, array $b): int {
            $ordem = ['atraso' => 0, 'atencao' => 1, 'em_dia' => 2];
            $sa = $ordem[(string) ($a['situacao'] ?? '')] ?? 3;
            $sb = $ordem[(string) ($b['situacao'] ?? '')] ?? 3;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            $pa = (int) ($a['pendentes_vencidas'] ?? 0);
            $pb = (int) ($b['pendentes_vencidas'] ?? 0);
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            return strcmp((string) ($a['professor_nome'] ?? ''), (string) ($b['professor_nome'] ?? ''));
        });

        $resumo = $this->resumirIndicadoresDiario($rows);
        $limite = max(1, min(100, (int) ($filtros['limite'] ?? 30)));
        $linhas = [];
        foreach (array_slice($rows, 0, $limite) as $r) {
            $linhas[] = [
                'turma_id' => (int) ($r['turma_id'] ?? 0),
                'materia_id' => (int) ($r['materia_id'] ?? 0),
                'professor_id' => (int) ($r['professor_id'] ?? 0),
                'turma_nome' => trim((string) ($r['turma_nome'] ?? '')),
                'materia_nome' => trim((string) ($r['materia_nome'] ?? '')),
                'professor_nome' => trim((string) ($r['professor_nome'] ?? '')),
                'pendentes_vencidas' => (int) ($r['pendentes_vencidas'] ?? 0),
                'percentual' => isset($r['percentual']) ? (float) $r['percentual'] : null,
                'ultima_data' => isset($r['ultima_data']) ? (string) $r['ultima_data'] : null,
                'situacao' => (string) ($r['situacao'] ?? ''),
                'situacao_label' => $this->rotuloSituacaoDiario((string) ($r['situacao'] ?? '')),
            ];
        }

        return [
            'ok' => true,
            'periodo' => ['inicio' => $inicio, 'fim' => $fim],
            'professor' => $professor['professor'] ?? null,
            'turma' => $turma['turma'] ?? null,
            'resumo' => $resumo,
            'diarios' => $linhas,
            'total' => count($rows),
            'aviso' => $linhas === [] ? 'Nenhuma pendência de diário encontrada para este filtro.' : null,
        ];
    }

    /**
     * Agenda/grade horária por turma, professor ou aluno.
     *
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function gradeHorariaConsulta(array $filtros): array
    {
        $data = $this->normalizarData($filtros['data'] ?? null) ?? date('Y-m-d');
        $diaSemana = (int) date('N', strtotime($data));
        $periodo = trim((string) ($filtros['periodo'] ?? ''));

        $escopo = null;
        $params = ['dia_semana' => $diaSemana];
        if ($periodo !== '') {
            $params['periodo'] = $periodo;
        }

        if (!empty($filtros['professor_id']) || !empty($filtros['professor_nome']) || (!empty($filtros['nome']) && empty($filtros['aluno_nome']))) {
            $professor = $this->professores->resolverProfessor($filtros);
            if (empty($professor['ok'])) {
                return $professor;
            }
            $params['professor_id'] = (int) ($professor['professor']['id'] ?? 0);
            $escopo = ['tipo' => 'professor'] + ($professor['professor'] ?? []);
        } elseif (!empty($filtros['turma_id']) || !empty($filtros['turma_nome']) || !empty($filtros['turma'])) {
            $turma = $this->resolverTurma($filtros);
            if (empty($turma['ok'])) {
                return $turma;
            }
            $params['turma_id'] = (int) ($turma['turma']['id'] ?? 0);
            $escopo = ['tipo' => 'turma'] + ($turma['turma'] ?? []);
        } elseif (!empty($filtros['aluno_id']) || !empty($filtros['ra']) || !empty($filtros['aluno_nome'])) {
            $aluno = $this->resolverAluno($filtros);
            if (empty($aluno['ok'])) {
                return $aluno;
            }
            $alunoInfo = $aluno['aluno'] ?? [];
            $turmaId = (int) ($alunoInfo['turma_id'] ?? 0);
            if ($turmaId <= 0) {
                return ['ok' => false, 'error' => 'Aluno sem turma vinculada para consultar a grade.'];
            }
            $params['turma_id'] = $turmaId;
            $escopo = [
                'tipo' => 'aluno',
                'aluno_id' => (int) ($alunoInfo['id'] ?? 0),
                'aluno_nome' => trim((string) ($alunoInfo['nome'] ?? '')),
                'turma_id' => $turmaId,
                'turma_nome' => trim((string) ($alunoInfo['turma_nome'] ?? '')),
            ];
        } else {
            return ['ok' => false, 'error' => 'Informe turma, professor ou aluno para consultar a grade.'];
        }

        $rows = (new GradeHorariaLeitura())->listar($params);
        $aulas = [];
        foreach ($rows as $r) {
            $aulas[] = [
                'dia_semana' => (int) ($r['dia_semana'] ?? 0),
                'horario_de' => (string) ($r['horario_de'] ?? ''),
                'horario_ate' => (string) ($r['horario_ate'] ?? ''),
                'periodo' => isset($r['periodo']) ? (string) $r['periodo'] : null,
                'turma_nome' => trim((string) ($r['turma_nome'] ?? '')),
                'professor_nome' => trim((string) ($r['professor_nome'] ?? '')),
                'materia_nome' => trim((string) ($r['materia_nome'] ?? '')),
                'sala_nome' => isset($r['sala_nome']) && $r['sala_nome'] !== '' ? trim((string) $r['sala_nome']) : null,
            ];
        }

        return [
            'ok' => true,
            'data' => $data,
            'dia_semana' => $diaSemana,
            'dia_semana_label' => $this->rotuloDiaSemana($diaSemana),
            'escopo' => $escopo,
            'aulas' => $aulas,
            'aviso' => $aulas === [] ? 'Nenhuma aula na grade para este dia/filtro.' : null,
        ];
    }

    /**
     * Ocorrências da turma, com foco em abertas/mais graves.
     *
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function ocorrenciasTurma(array $filtros): array
    {
        $turma = $this->resolverTurma($filtros);
        if (empty($turma['ok'])) {
            return $turma;
        }

        $model = new Ocorrencia();
        $status = trim((string) ($filtros['status'] ?? 'aberta'));
        if ($status !== '' && $status !== 'todas' && !isset(Ocorrencia::STATUS[$status])) {
            return ['ok' => false, 'error' => 'Status inválido para ocorrências da turma.'];
        }
        $gravidadeFiltro = trim((string) ($filtros['gravidade'] ?? $filtros['nivel_gravidade'] ?? ''));
        if ($gravidadeFiltro !== '' && !isset(Ocorrencia::GRAVIDADES[$gravidadeFiltro])) {
            return ['ok' => false, 'error' => 'Gravidade inválida para ocorrências da turma.'];
        }

        [$dataInicio, $dataFim] = $this->periodoFiltro($filtros);
        $filtrosLista = ['turma_id' => (int) $turma['turma']['id']];
        if ($status !== '' && $status !== 'todas') {
            $filtrosLista['status'] = $status;
        }
        if ($gravidadeFiltro !== '') {
            $filtrosLista['gravidade'] = $gravidadeFiltro;
        }
        if ($dataInicio !== null) {
            $filtrosLista['data_inicio'] = $dataInicio;
        }
        if ($dataFim !== null) {
            $filtrosLista['data_fim'] = $dataFim;
        }

        $limite = max(1, min(100, (int) ($filtros['limite'] ?? 30)));
        $rows = $model->listar($filtrosLista, $limite, 0);
        $total = $model->contar($filtrosLista);
        $porGravidade = ['leve' => 0, 'moderado' => 0, 'grave' => 0];
        $offsetResumo = 0;
        $batchResumo = 1000;
        do {
            $chunkResumo = $model->listar($filtrosLista, $batchResumo, $offsetResumo);
            foreach ($chunkResumo as $r) {
                $gravidadeLinha = (string) ($r['nivel_gravidade'] ?? '');
                if (isset($porGravidade[$gravidadeLinha])) {
                    $porGravidade[$gravidadeLinha]++;
                }
            }
            $offsetResumo += count($chunkResumo);
        } while ($chunkResumo !== [] && $offsetResumo < $total);
        $saida = [];
        foreach ($rows as $r) {
            $gravidadeLinha = (string) ($r['nivel_gravidade'] ?? '');
            $saida[] = [
                'id' => (int) ($r['id'] ?? 0),
                'data' => isset($r['data_ocorrencia']) ? (string) $r['data_ocorrencia'] : null,
                'alunos_nomes' => trim((string) ($r['alunos_nomes'] ?? '')),
                'titulo' => trim((string) ($r['titulo'] ?? '')),
                'detalhe' => trim((string) ($r['detalhe'] ?? '')),
                'gravidade' => $gravidadeLinha !== '' ? $gravidadeLinha : null,
                'gravidade_label' => $gravidadeLinha !== '' ? (Ocorrencia::GRAVIDADES[$gravidadeLinha] ?? $gravidadeLinha) : null,
                'status' => isset($r['status']) ? (string) $r['status'] : null,
                'status_label' => isset($r['status']) ? (Ocorrencia::STATUS[(string) $r['status']] ?? (string) $r['status']) : null,
                'categoria' => isset($r['categoria_nome']) ? trim((string) $r['categoria_nome']) : null,
            ];
        }

        return [
            'ok' => true,
            'turma' => $turma['turma'],
            'filtros' => [
                'status' => $status !== '' ? $status : null,
                'gravidade' => $gravidadeFiltro !== '' ? $gravidadeFiltro : null,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
            ],
            'total' => $total,
            'por_gravidade' => $porGravidade,
            'ocorrencias' => $saida,
            'aviso' => $saida === [] ? 'Nenhuma ocorrência encontrada para esta turma no filtro informado.' : null,
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
            $anoNumero = (int) ($filtros['ano_letivo'] ?? $filtros['ano'] ?? 0);
            $row = null;
            if ($anoNumero >= 2000) {
                $row = $this->db->fetch(
                    'SELECT data_inicio, data_fim, ano FROM ano_letivo WHERE ano = :ano ORDER BY id DESC LIMIT 1',
                    ['ano' => $anoNumero]
                );
            }
            if ($row === null) {
                $ano = $this->anoLetivoAtual((int) ($filtros['ano_letivo_id'] ?? 0));
                $row = $ano !== null
                    ? $this->db->fetch(
                        'SELECT data_inicio, data_fim, ano FROM ano_letivo WHERE id = :id LIMIT 1',
                        ['id' => (int) $ano['id']]
                    )
                    : null;
            }
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
     * Ocorrências disciplinares/comportamentais do aluno (não confundir com faltas).
     *
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,ocorrencias?:list,total?:int,error?:string,candidatos?:list,aviso?:string}
     */
    public function ocorrenciasAluno(array $filtros): array
    {
        $aluno = $this->resolverAluno($filtros);
        if (empty($aluno['ok'])) {
            return $aluno;
        }
        $a = $aluno['aluno'];
        $alunoId = (int) $a['id'];

        $filtrosLista = ['aluno_id' => $alunoId];
        $status = trim((string) ($filtros['status'] ?? ''));
        if ($status !== '' && isset(Ocorrencia::STATUS[$status])) {
            $filtrosLista['status'] = $status;
        }

        $inicio = $this->normalizarData($filtros['data_inicio'] ?? null);
        $fim = $this->normalizarData($filtros['data_fim'] ?? null);
        $anoFiltro = (int) ($filtros['ano_letivo'] ?? $filtros['ano'] ?? 0);
        $qualquerPeriodo = !empty($filtros['qualquer_periodo'])
            || !empty($filtros['todos_periodos'])
            || !empty($filtros['sem_filtro_ano']);
        if ($qualquerPeriodo) {
            $anoFiltro = 0;
            $inicio = null;
            $fim = null;
        }
        if ($anoFiltro > 0 && ($inicio === null || $fim === null)) {
            $inicio = $inicio ?? ($anoFiltro . '-01-01');
            $fim = $fim ?? ($anoFiltro . '-12-31');
        }
        if ($inicio !== null) {
            $filtrosLista['data_inicio'] = $inicio;
        }
        if ($fim !== null) {
            $filtrosLista['data_fim'] = $fim;
        }

        try {
            $model = new Ocorrencia();
            $rows = $model->listar($filtrosLista, 40, 0);
            $total = $model->contar($filtrosLista);
            // Se filtrou por ano e veio vazio, tenta sem período (ocorrência pode ser de outro ano).
            if ($total === 0 && ($anoFiltro > 0 || $inicio !== null || $fim !== null) && !$qualquerPeriodo) {
                $filtrosSemAno = ['aluno_id' => $alunoId];
                if ($status !== '' && isset(Ocorrencia::STATUS[$status])) {
                    $filtrosSemAno['status'] = $status;
                }
                $rowsAll = $model->listar($filtrosSemAno, 40, 0);
                $totalAll = $model->contar($filtrosSemAno);
                if ($totalAll > 0) {
                    $rows = $rowsAll;
                    $total = $totalAll;
                    $inicio = null;
                    $fim = null;
                    $anoFiltro = 0;
                }
            }
        } catch (\Throwable $e) {
            error_log('AssistenteConsultaAmpliada ocorrenciasAluno: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Não foi possível consultar as ocorrências.'];
        }

        $slim = [];
        foreach ($rows as $row) {
            $data = (string) ($row['data_ocorrencia'] ?? '');
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $data, $m)) {
                $data = $m[1];
            }
            $nivel = (string) ($row['nivel_gravidade'] ?? '');
            $statusRow = (string) ($row['status'] ?? '');
            $slim[] = [
                'id' => (int) ($row['id'] ?? 0),
                'data' => $data,
                'titulo' => trim((string) ($row['titulo'] ?? '')),
                'detalhe' => mb_substr(trim((string) ($row['detalhe'] ?? '')), 0, 400),
                'gravidade' => $nivel,
                'gravidade_label' => Ocorrencia::GRAVIDADES[$nivel] ?? $nivel,
                'status' => $statusRow,
                'status_label' => Ocorrencia::STATUS[$statusRow] ?? $statusRow,
                'categoria' => trim((string) ($row['categoria_nome'] ?? '')),
                'turma_nome' => trim((string) ($row['turma_nome'] ?? '')),
                'materia_nome' => trim((string) ($row['materia_nome'] ?? '')),
                'local' => trim((string) ($row['local'] ?? '')),
                'enviar_pais' => !empty($row['enviar_pais']),
                'criado_por' => trim((string) ($row['criado_por_nome'] ?? '')),
            ];
        }

        return [
            'ok' => true,
            'aluno' => $a,
            'periodo' => [
                'inicio' => $inicio,
                'fim' => $fim,
                'ano_letivo' => $anoFiltro > 0 ? $anoFiltro : null,
            ],
            'ocorrencias' => $slim,
            'total' => $total,
            'aviso' => $total === 0 ? 'Nenhuma ocorrência registrada para este aluno no filtro informado.' : null,
        ];
    }

    /**
     * @return array{ok:bool,colunas?:list,grupos?:list,linhas?:list,regra?:array,error?:string,aviso?:string}
     */
    private function montarQuadroBoletimAluno(int $alunoId, int $anoLetivo, int $regraIdPreferida = 0): array
    {
        $boletim = new BoletimConfig();
        $boletim->ensureSchema();
        $candidatas = [];

        if ($regraIdPreferida > 0) {
            $regraPref = $boletim->getRuleById($regraIdPreferida);
            if (is_array($regraPref) && (int) ($regraPref['ano_letivo'] ?? 0) !== $anoLetivo) {
                return [
                    'ok' => false,
                    'error' => 'A regra #' . $regraIdPreferida . ' não pertence ao ano letivo ' . $anoLetivo . '.',
                ];
            }
            if (is_array($regraPref) && !empty($regraPref['componentes'])) {
                $candidatas[] = $regraPref;
            }
        }

        foreach ($this->listarRegrasQuadroBoletim($anoLetivo) as $regraCand) {
            $idCand = (int) ($regraCand['id'] ?? 0);
            if ($idCand <= 0) {
                continue;
            }
            if ($regraIdPreferida > 0 && $idCand === $regraIdPreferida) {
                continue;
            }
            $candidatas[] = $regraCand;
        }

        if ($candidatas === []) {
            return ['ok' => false, 'error' => 'Nenhuma regra de boletim/impressão encontrada para ' . $anoLetivo . '.'];
        }

        $melhorVazio = null;
        foreach ($candidatas as $regra) {
            $matriz = $this->simularMatrizRegraAluno($regra, $alunoId, $anoLetivo);
            if ($matriz === null || empty($matriz['colunas']) || empty($matriz['linhas'])) {
                continue;
            }
            $montado = [
                'ok' => true,
                'regra' => [
                    'id' => (int) ($regra['id'] ?? 0),
                    'nome' => trim((string) ($regra['nome'] ?? '')),
                ],
                'colunas' => $matriz['colunas'],
                'grupos' => $this->agruparCabecalhosColunas($matriz['colunas']),
                'linhas' => $matriz['linhas'],
            ];
            if ($this->quadroBoletimTemNotas($montado)) {
                return $montado;
            }
            if ($melhorVazio === null) {
                $melhorVazio = $montado;
            }
        }

        if ($melhorVazio !== null) {
            $melhorVazio['aviso'] = 'Quadro montado sem notas preenchidas nesta regra.';
            return $melhorVazio;
        }

        return ['ok' => false, 'error' => 'Não foi possível montar o quadro do boletim.'];
    }

    /**
     * True se há ao menos uma nota/célula preenchida (não só "—" / Reprovado vazio).
     *
     * @param array<string,mixed> $quadro
     */
    private function quadroBoletimTemNotas(array $quadro): bool
    {
        $linhas = is_array($quadro['linhas'] ?? null) ? $quadro['linhas'] : [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $notas = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
            foreach ($notas as $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }
                if (is_string($valor)) {
                    $v = trim($valor);
                    if ($v === '' || $v === '—' || $v === '-' || strcasecmp($v, 'null') === 0) {
                        continue;
                    }
                    // Status sozinho (Reprovado/Aprovado) sem nota numérica não conta como boletim de notas.
                    if (preg_match('/^(reprovado|aprovado|recuperacao|recuperação)$/iu', $v) === 1) {
                        continue;
                    }
                    return true;
                }
                if (is_numeric($valor)) {
                    return true;
                }
                if (is_array($valor)) {
                    $inner = $valor['valor'] ?? $valor['nota'] ?? $valor['texto'] ?? null;
                    if ($inner !== null && $inner !== '' && !(is_string($inner) && trim($inner) === '—')) {
                        if (is_string($inner) && preg_match('/^(reprovado|aprovado)$/iu', trim($inner)) === 1) {
                            continue;
                        }
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Prefere regra de impressão/quadro anual (muitas colunas) do ano.
     *
     * @return array<string,mixed>|null
     */
    private function resolverRegraQuadroBoletim(int $anoLetivo): ?array
    {
        $lista = $this->listarRegrasQuadroBoletim($anoLetivo);
        return $lista[0] ?? null;
    }

    /**
     * Regras candidatas ao quadro anual, ordenadas da mais promissora à menos.
     *
     * @return list<array<string,mixed>>
     */
    private function listarRegrasQuadroBoletim(int $anoLetivo): array
    {
        if ($anoLetivo < 2000) {
            return [];
        }
        $boletim = new BoletimConfig();
        $rows = $this->db->fetchAll(
            "SELECT id, nome, codigo
             FROM boletim_regras
             WHERE ativo = 1
               AND ano_letivo = :ano
               AND (exibir_em = 'boletim' OR exibir_em IS NULL OR exibir_em = '')
             ORDER BY id DESC
             LIMIT 40",
            ['ano' => $anoLetivo]
        ) ?: [];

        $ranqueadas = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $regra = $boletim->getRuleById($id);
            if (!is_array($regra)) {
                continue;
            }
            $comps = is_array($regra['componentes'] ?? null) ? $regra['componentes'] : [];
            $n = count($comps);
            if ($n < 4) {
                continue;
            }
            $blob = mb_strtolower(
                trim((string) ($row['codigo'] ?? '')) . ' ' . trim((string) ($row['nome'] ?? '')),
                'UTF-8'
            );
            $score = $n;
            if (str_contains($blob, 'impress') || str_contains($blob, 'print') || str_contains($blob, 'quadro')) {
                $score += 80;
            }
            if ((string) ($regra['exibir_em'] ?? '') === 'boletim') {
                $score += 40;
            }
            // "Aprovação/do zero" costuma vir só com status e sem notas lançadas.
            if (str_contains($blob, 'aprovac') || str_contains($blob, 'do zero') || str_contains($blob, 'do-zero')) {
                $score -= 120;
            }
            // Prefere quadro anual da escola em vez de só um bimestre.
            if (preg_match('/\bb[1-4]\b|\bbim/u', $blob) === 1 && !str_contains($blob, 'ensino')) {
                $score -= 20;
            }
            $ranqueadas[] = ['score' => $score, 'regra' => $regra];
        }

        usort(
            $ranqueadas,
            static fn(array $a, array $b): int => ((int) $b['score']) <=> ((int) $a['score'])
        );

        $out = [];
        foreach ($ranqueadas as $item) {
            $out[] = $item['regra'];
        }
        return $out;
    }

    /**
     * @return array{regra?:?array,ambiguidade?:bool,candidatos_eventos?:list}
     */
    private function resolverRegraEventoNotas(int $anoLetivo, int $bimestre, string $eventoNome): array
    {
        $boletim = new BoletimConfig();
        $params = ['ano' => $anoLetivo];
        $sql = "SELECT id, nome, bimestre, exibir_em, codigo
                FROM boletim_regras
                WHERE ativo = 1
                  AND ano_letivo = :ano
                  AND (exibir_em = 'notas' OR exibir_em = 'demonstrativo' OR exibir_em IS NULL OR exibir_em = '')";
        if ($bimestre > 0) {
            $sql .= ' AND bimestre = :bim';
            $params['bim'] = $bimestre;
        }
        if ($eventoNome !== '') {
            $sql .= ' AND nome LIKE :nome';
            $params['nome'] = '%' . $eventoNome . '%';
        }
        $sql .= " ORDER BY
                    CASE WHEN exibir_em = 'notas' THEN 0 WHEN exibir_em = 'demonstrativo' THEN 1 ELSE 2 END,
                    id DESC
                 LIMIT 20";
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $candidatos = [];
        foreach ($rows as $row) {
            $regra = $boletim->getRuleById((int) ($row['id'] ?? 0));
            if (!is_array($regra) || empty($regra['componentes'])) {
                continue;
            }
            $candidatos[] = $regra;
        }
        if ($candidatos === []) {
            return ['regra' => null];
        }
        if (count($candidatos) === 1) {
            return ['regra' => $candidatos[0]];
        }
        // Preferência clara: se só um tem exibir_em=notas e o resto não, usa esse.
        $soNotas = array_values(array_filter(
            $candidatos,
            static fn(array $r): bool => in_array((string) ($r['exibir_em'] ?? ''), ['notas', 'demonstrativo'], true)
        ));
        if (count($soNotas) === 1) {
            return ['regra' => $soNotas[0]];
        }
        $lista = [];
        foreach (($soNotas !== [] ? $soNotas : $candidatos) as $r) {
            $lista[] = [
                'id' => (int) ($r['id'] ?? 0),
                'nome' => trim((string) ($r['nome'] ?? '')),
                'bimestre' => isset($r['bimestre']) ? (int) $r['bimestre'] : null,
                'exibir_em' => isset($r['exibir_em']) ? (string) $r['exibir_em'] : null,
            ];
        }
        return [
            'ambiguidade' => true,
            'candidatos_eventos' => $lista,
        ];
    }

    /**
     * @return list<array{id:int,nome:string,bimestre:?int,exibir_em:?string}>
     */
    private function listarEventosNotasAno(int $anoLetivo, int $bimestre = 0): array
    {
        $params = ['ano' => $anoLetivo];
        $sql = "SELECT id, nome, bimestre, exibir_em
                FROM boletim_regras
                WHERE ativo = 1
                  AND ano_letivo = :ano
                  AND (exibir_em = 'notas' OR exibir_em = 'demonstrativo' OR exibir_em IS NULL OR exibir_em = '')";
        if ($bimestre > 0) {
            $sql .= ' AND bimestre = :bim';
            $params['bim'] = $bimestre;
        }
        $sql .= " ORDER BY bimestre ASC, nome ASC LIMIT 30";
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'nome' => trim((string) ($r['nome'] ?? '')),
                'bimestre' => isset($r['bimestre']) ? (int) $r['bimestre'] : null,
                'exibir_em' => isset($r['exibir_em']) ? (string) $r['exibir_em'] : null,
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $regra
     * @return array{colunas:list,linhas:list}|null
     */
    private function simularMatrizRegraAluno(array $regra, int $alunoId, int $anoLetivo): ?array
    {
        $ini = $this->normalizarData($regra['default_data_inicio'] ?? null);
        $fim = $this->normalizarData($regra['default_data_fim'] ?? null);
        if ($ini === null || $fim === null) {
            $ini = $ini ?? sprintf('%04d-01-01', $anoLetivo);
            $fim = $fim ?? sprintf('%04d-12-31', $anoLetivo);
        }
        if ($ini > $fim) {
            [$ini, $fim] = [$fim, $ini];
        }
        try {
            $motor = new \App\Modulos\Boletins\Controllers\BoletimConfigController(true);
            $sim = $motor->simularRegraAluno(
                $regra,
                $alunoId,
                'RANGE:' . $ini . ':' . $fim,
                $ini,
                $fim,
                [],
                true,
                false
            );
            if (!is_array($sim)) {
                return null;
            }
            $matriz = is_array($sim['matriz_materias'] ?? null) ? $sim['matriz_materias'] : [];
            $colunasRaw = is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
            $linhasRaw = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
            if ($colunasRaw === [] || $linhasRaw === []) {
                return null;
            }

            $colunas = [];
            foreach ($colunasRaw as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $codigo = trim((string) ($c['codigo'] ?? $c['chave'] ?? $c['key'] ?? ''));
                if ($codigo === '') {
                    continue;
                }
                $colunas[] = [
                    'codigo' => $codigo,
                    'nome' => trim((string) ($c['nome'] ?? $c['label'] ?? $codigo)),
                    'cabecalho' => trim((string) ($c['linha_cabecalho'] ?? '')),
                    'layout_group' => trim((string) ($c['layout_group'] ?? '')),
                ];
            }

            $linhas = [];
            foreach ($linhasRaw as $linha) {
                if (!is_array($linha)) {
                    continue;
                }
                $notas = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
                $linhas[] = [
                    'materia_id' => (int) ($linha['materia_id'] ?? 0),
                    'materia' => trim((string) ($linha['materia_nome'] ?? '')),
                    'notas' => $notas,
                ];
            }

            return ['colunas' => $colunas, 'linhas' => $linhas];
        } catch (\Throwable $e) {
            error_log('AssistenteConsultaAmpliada simularMatriz: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrai uma coluna (ex.: MU) do quadro anual, opcionalmente restrita ao bimestre.
     *
     * @return array{ok:bool,quadro?:array,error?:string}
     */
    private function extrairColunaQuadroAnual(int $alunoId, int $anoLetivo, int $bimestre, string $coluna): array
    {
        $quadro = $this->montarQuadroBoletimAluno($alunoId, $anoLetivo);
        if (empty($quadro['ok'])) {
            return ['ok' => false, 'error' => $quadro['error'] ?? 'Quadro anual indisponível.'];
        }
        $coluna = strtoupper(trim($coluna));
        $prefix = $bimestre > 0 ? ('b' . $bimestre . '_') : '';
        $colunas = [];
        foreach ($quadro['colunas'] as $c) {
            $nome = strtoupper(trim((string) ($c['nome'] ?? '')));
            $cod = strtoupper(trim((string) ($c['codigo'] ?? '')));
            $grupo = strtolower(trim((string) ($c['layout_group'] ?? '')));
            $doBim = $bimestre <= 0
                || $grupo === ('b' . $bimestre)
                || ($prefix !== '' && str_starts_with(strtolower($cod), $prefix));
            if (!$doBim) {
                continue;
            }
            if ($nome === $coluna || $cod === $coluna || str_ends_with($cod, '_' . $coluna)) {
                $colunas[] = $c;
            }
        }
        if ($colunas === []) {
            return ['ok' => false, 'error' => 'Coluna não encontrada no quadro anual.'];
        }
        $codigos = array_map(static fn($c): string => (string) ($c['codigo'] ?? ''), $colunas);
        $linhas = [];
        foreach ($quadro['linhas'] as $linha) {
            $notas = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
            $filtradas = [];
            foreach ($codigos as $cod) {
                if ($cod !== '' && array_key_exists($cod, $notas)) {
                    $filtradas[$cod] = $notas[$cod];
                }
            }
            $linhas[] = [
                'materia_id' => (int) ($linha['materia_id'] ?? 0),
                'materia' => (string) ($linha['materia'] ?? ''),
                'notas' => $filtradas,
            ];
        }
        return [
            'ok' => true,
            'quadro' => [
                'colunas' => $colunas,
                'grupos' => $this->agruparCabecalhosColunas($colunas),
                'linhas' => $linhas,
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $colunas
     * @return list<array{titulo:string,colspan:int}>
     */
    private function agruparCabecalhosColunas(array $colunas): array
    {
        $mapaGrupo = [
            'b1' => 'I BIMESTRE',
            'b2' => 'II BIMESTRE',
            'b3' => 'III BIMESTRE',
            'b4' => 'IV BIMESTRE',
            's1' => '1º SEMESTRE',
            's2' => '2º SEMESTRE',
            'sem1' => '1º SEMESTRE',
            'sem2' => '2º SEMESTRE',
            'final' => 'RESULTADO FINAL',
            'rf' => 'RESULTADO FINAL',
            'faltas' => 'FALTAS',
        ];

        $grupos = [];
        $atualKey = null;
        $atualTitulo = null;
        $span = 0;
        foreach ($colunas as $c) {
            $layout = strtolower(trim((string) ($c['layout_group'] ?? '')));
            $cab = trim((string) ($c['cabecalho'] ?? ''));
            $key = $layout !== '' ? $layout : ('cab:' . ($cab !== '' ? $cab : '_'));
            $titulo = $cab;
            if ($titulo === '' && $layout !== '' && isset($mapaGrupo[$layout])) {
                $titulo = $mapaGrupo[$layout];
            }
            if ($titulo === '') {
                $titulo = $layout !== '' ? strtoupper($layout) : ' ';
            }

            if ($atualKey === null) {
                $atualKey = $key;
                $atualTitulo = $titulo;
                $span = 1;
                continue;
            }
            if ($key === $atualKey) {
                $span++;
                // Prefere título não vazio / mais descritivo dentro do mesmo grupo.
                if (($atualTitulo === ' ' || $atualTitulo === strtoupper($layout)) && $titulo !== ' ') {
                    $atualTitulo = $titulo;
                }
                if ($cab !== '' && isset($mapaGrupo[$layout]) === false) {
                    $atualTitulo = $cab;
                }
                continue;
            }
            $grupos[] = ['titulo' => $atualTitulo ?? ' ', 'colspan' => $span];
            $atualKey = $key;
            $atualTitulo = $titulo;
            $span = 1;
        }
        if ($atualKey !== null) {
            $grupos[] = ['titulo' => $atualTitulo ?? ' ', 'colspan' => $span];
        }
        return $grupos;
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

        $turma = trim((string) ($filtros['turma_nome'] ?? $filtros['turma'] ?? ''));
        $ra = trim((string) ($filtros['ra'] ?? $filtros['aluno_ra'] ?? ''));
        $nome = trim((string) ($filtros['aluno_nome'] ?? $filtros['nome'] ?? $filtros['termo'] ?? ''));
        if ($ra === '' && $nome !== '') {
            $ra = (string) ($this->alunos->extrairCodigoRa($nome) ?? '');
        }
        if ($ra === '' && $nome === '') {
            return ['ok' => false, 'error' => 'Informe aluno_id, ra ou aluno_nome.'];
        }

        $termoBusca = $ra !== '' ? ('ra ' . $ra) : $nome;
        $lista = $this->alunos->buscarAlunos($termoBusca, 15, $turma !== '' ? $turma : null);
        if ($lista === []) {
            return ['ok' => false, 'error' => $ra !== '' ? ('Nenhum aluno encontrado com RA ' . $ra . '.') : 'Nenhum aluno encontrado.'];
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
        } catch (\Throwable $e) {
            // coluna ativo pode não existir
        }
        $r = $this->db->fetch('SELECT id, ano FROM ano_letivo ORDER BY ano DESC LIMIT 1');
        if (!$r) {
            return null;
        }
        return ['id' => (int) $r['id'], 'ano' => (int) $r['ano']];
    }

    /** @return array{id:int,ano:int,data_inicio?:string,data_fim?:string}|null */
    private function resolverAnoLetivoFiltro(array $filtros): ?array
    {
        $anoId = (int) ($filtros['ano_letivo_id'] ?? 0);
        if ($anoId > 0) {
            $r = $this->db->fetch(
                'SELECT id, ano, data_inicio, data_fim FROM ano_letivo WHERE id = :id LIMIT 1',
                ['id' => $anoId]
            );
            if ($r) {
                return [
                    'id' => (int) $r['id'],
                    'ano' => (int) $r['ano'],
                    'data_inicio' => isset($r['data_inicio']) ? (string) $r['data_inicio'] : null,
                    'data_fim' => isset($r['data_fim']) ? (string) $r['data_fim'] : null,
                ];
            }
        }

        $anoNumero = (int) ($filtros['ano_letivo'] ?? $filtros['ano'] ?? 0);
        if ($anoNumero >= 2000) {
            $r = $this->db->fetch(
                'SELECT id, ano, data_inicio, data_fim FROM ano_letivo WHERE ano = :ano ORDER BY id DESC LIMIT 1',
                ['ano' => $anoNumero]
            );
            if ($r) {
                return [
                    'id' => (int) $r['id'],
                    'ano' => (int) $r['ano'],
                    'data_inicio' => isset($r['data_inicio']) ? (string) $r['data_inicio'] : null,
                    'data_fim' => isset($r['data_fim']) ? (string) $r['data_fim'] : null,
                ];
            }
        }

        $atual = $this->anoLetivoAtual();
        if ($atual === null) {
            return null;
        }

        $r = $this->db->fetch(
            'SELECT id, ano, data_inicio, data_fim FROM ano_letivo WHERE id = :id LIMIT 1',
            ['id' => (int) $atual['id']]
        );
        if ($r) {
            return [
                'id' => (int) $r['id'],
                'ano' => (int) $r['ano'],
                'data_inicio' => isset($r['data_inicio']) ? (string) $r['data_inicio'] : null,
                'data_fim' => isset($r['data_fim']) ? (string) $r['data_fim'] : null,
            ];
        }

        return $atual;
    }

    /** @return array{0:?string,1:?string} */
    private function periodoFiltro(array $filtros): array
    {
        $inicio = $this->normalizarData($filtros['data_inicio'] ?? $filtros['inicio'] ?? null);
        $fim = $this->normalizarData($filtros['data_fim'] ?? $filtros['fim'] ?? null);
        if ($inicio !== null || $fim !== null) {
            return [$inicio, $fim];
        }

        $ano = $this->resolverAnoLetivoFiltro($filtros);
        if ($ano !== null) {
            return [
                isset($ano['data_inicio']) && $ano['data_inicio'] !== '' ? (string) $ano['data_inicio'] : ((int) $ano['ano'] >= 2000 ? sprintf('%04d-01-01', (int) $ano['ano']) : null),
                isset($ano['data_fim']) && $ano['data_fim'] !== '' ? (string) $ano['data_fim'] : ((int) $ano['ano'] >= 2000 ? sprintf('%04d-12-31', (int) $ano['ano']) : null),
            ];
        }

        return [null, null];
    }

    /** @param list<array<string,mixed>> $indicadores */
    private function resumirIndicadoresDiario(array $indicadores): array
    {
        $resumo = [
            'total' => count($indicadores),
            'em_dia' => 0,
            'atencao' => 0,
            'atraso' => 0,
            'pendentes_vencidas' => 0,
            'horas_previstas' => 0.0,
            'horas_ministradas' => 0.0,
            'cobertura_media' => null,
        ];
        $somaPct = 0.0;
        $countPct = 0;
        foreach ($indicadores as $i) {
            $situacao = (string) ($i['situacao'] ?? 'em_dia');
            if (isset($resumo[$situacao])) {
                $resumo[$situacao]++;
            }
            $resumo['pendentes_vencidas'] += (int) ($i['pendentes_vencidas'] ?? 0);
            $resumo['horas_previstas'] += ((int) ($i['minutos_previstos'] ?? 0)) / 60;
            $resumo['horas_ministradas'] += ((int) ($i['minutos_ministrados'] ?? 0)) / 60;
            if (isset($i['percentual']) && $i['percentual'] !== null) {
                $somaPct += (float) $i['percentual'];
                $countPct++;
            }
        }
        $resumo['horas_previstas'] = round((float) $resumo['horas_previstas'], 1);
        $resumo['horas_ministradas'] = round((float) $resumo['horas_ministradas'], 1);
        $resumo['cobertura_media'] = $countPct > 0 ? round($somaPct / $countPct, 1) : null;
        return $resumo;
    }

    private function rotuloSituacaoDiario(string $situacao): string
    {
        return match ($situacao) {
            'em_dia' => 'Em dia',
            'atencao' => 'Atenção',
            'atraso' => 'Em atraso',
            default => $situacao,
        };
    }

    private function rotuloDiaSemana(int $diaSemana): string
    {
        return match ($diaSemana) {
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
            7 => 'Domingo',
            default => 'Dia não informado',
        };
    }

    /** @return list<array<string,mixed>> */
    private function indicadoresDiarioPeriodoCompleto(string $inicio, string $fim, int $professorId = 0): array
    {
        $start = new \DateTime($inicio);
        $end = new \DateTime($fim);
        if ($end < $start) {
            return [];
        }

        $diario = new ClassDiary();
        $dias = (int) $start->diff($end)->days;
        if ($dias <= 200) {
            return $diario->indicadores($inicio, $fim, $professorId);
        }

        $merge = [];
        $cursor = clone $start;
        while ($cursor <= $end) {
            $fimBloco = (clone $cursor)->modify('+200 days');
            if ($fimBloco > $end) {
                $fimBloco = clone $end;
            }
            $chunk = $diario->indicadores($cursor->format('Y-m-d'), $fimBloco->format('Y-m-d'), $professorId);
            foreach ($chunk as $row) {
                $key = (int) ($row['grade_horaria_id'] ?? 0);
                if ($key <= 0) {
                    $key = crc32(
                        (string) ($row['professor_id'] ?? 0) . '|'
                        . (string) ($row['turma_id'] ?? 0) . '|'
                        . (string) ($row['materia_id'] ?? 0) . '|'
                        . (string) ($row['professor_nome'] ?? '') . '|'
                        . (string) ($row['turma_nome'] ?? '') . '|'
                        . (string) ($row['materia_nome'] ?? '')
                    );
                }
                if (!isset($merge[$key])) {
                    $merge[$key] = $row;
                    continue;
                }
                $merge[$key]['aulas_previstas'] = (int) ($merge[$key]['aulas_previstas'] ?? 0) + (int) ($row['aulas_previstas'] ?? 0);
                $merge[$key]['aulas_previstas_ate_hoje'] = (int) ($merge[$key]['aulas_previstas_ate_hoje'] ?? 0) + (int) ($row['aulas_previstas_ate_hoje'] ?? 0);
                $merge[$key]['aulas_ministradas'] = (int) ($merge[$key]['aulas_ministradas'] ?? 0) + (int) ($row['aulas_ministradas'] ?? 0);
                $merge[$key]['aulas_registradas'] = (int) ($merge[$key]['aulas_registradas'] ?? 0) + (int) ($row['aulas_registradas'] ?? 0);
                $merge[$key]['minutos_previstos'] = (int) ($merge[$key]['minutos_previstos'] ?? 0) + (int) ($row['minutos_previstos'] ?? 0);
                $merge[$key]['minutos_ministrados'] = (int) ($merge[$key]['minutos_ministrados'] ?? 0) + (int) ($row['minutos_ministrados'] ?? 0);
                $ultimaA = (string) ($merge[$key]['ultima_data'] ?? '');
                $ultimaB = (string) ($row['ultima_data'] ?? '');
                if ($ultimaB !== '' && ($ultimaA === '' || $ultimaB > $ultimaA)) {
                    $merge[$key]['ultima_data'] = $ultimaB;
                }
            }
            $cursor = (clone $fimBloco)->modify('+1 day');
        }

        foreach ($merge as &$row) {
            $previstasHoje = (int) ($row['aulas_previstas_ate_hoje'] ?? 0);
            $registradas = (int) ($row['aulas_registradas'] ?? 0);
            $pendentes = max(0, $previstasHoje - $registradas);
            $row['pendentes_vencidas'] = $pendentes;
            $previstasTotal = (int) ($row['aulas_previstas'] ?? 0);
            $ministradas = (int) ($row['aulas_ministradas'] ?? 0);
            $row['percentual'] = $previstasTotal > 0 ? min(100.0, round(($ministradas / $previstasTotal) * 100, 1)) : null;
            if ($previstasHoje === 0 && $registradas === 0) {
                $row['situacao'] = 'em_dia';
            } elseif ($pendentes <= 0) {
                $row['situacao'] = 'em_dia';
            } elseif ($pendentes <= 2) {
                $row['situacao'] = 'atencao';
            } else {
                $row['situacao'] = 'atraso';
            }
        }
        unset($row);

        $out = array_values($merge);
        usort($out, static function (array $a, array $b): int {
            $ordem = ['atraso' => 0, 'atencao' => 1, 'em_dia' => 2];
            $sa = $ordem[(string) ($a['situacao'] ?? '')] ?? 3;
            $sb = $ordem[(string) ($b['situacao'] ?? '')] ?? 3;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            return strcmp((string) ($a['professor_nome'] ?? ''), (string) ($b['professor_nome'] ?? ''));
        });
        return $out;
    }

    private function normalizarTextoBusca(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ];
        return strtr($texto, $map);
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

if (!class_exists('AssistenteConsultaAmpliadaService', false) && class_exists(__NAMESPACE__ . '\\AssistenteConsultaAmpliadaService', false)) {
    class_alias(__NAMESPACE__ . '\\AssistenteConsultaAmpliadaService', 'AssistenteConsultaAmpliadaService');
}
