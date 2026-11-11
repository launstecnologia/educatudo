<?php

namespace App\Painel\Admin\Services;
/**
 * Consultas extras do Assistente: turma, bloco de prova, jornadas do professor,
 * boletim, faltas e ocorrências do aluno (somente leitura).
 */


use App\Services\SaudeAprendizagemService;
use App\Services\ExamBlockResultsDashboardService;
use App\Modulos\Provas\Services\ProvasAlunoConsultaService;
use App\Modulos\Provas\Services\ProvasProfessorConsultaService;
use App\Modulos\Jornadas\Services\JornadasRelatorioService;
use App\Modulos\Presenca\Services\FrequencyService;
use App\Modulos\Ocorrencias\Models\Ocorrencia;

use App\Models\System\BoletimConfig;
use App\Core\Database;
use App\Models\Exams\ExamBlock;

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
        $anoFiltro = (int) ($filtros['ano_letivo'] ?? $filtros['ano'] ?? 0);
        if ($anoFiltro <= 0) {
            $anoAtual = $this->anoLetivoAtual();
            $anoFiltro = $anoAtual['ano'] ?? (int) date('Y');
        }

        $quadro = $this->montarQuadroBoletimAluno($alunoId, $anoFiltro, (int) ($filtros['regra_id'] ?? 0));
        if (!empty($quadro['ok'])) {
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

        // Fallback: eventos já gerados (demonstrativos/snapshots).
        $boletim = new BoletimConfig();
        $boletim->ensureSchema();
        $eventos = $boletim->getGeneratedBoletinsByAluno($alunoId, 'coordenacao', null) ?: [];
        if ($anoFiltro > 0) {
            $eventos = array_values(array_filter(
                $eventos,
                static fn($ev): bool => (int) ($ev['ano_letivo'] ?? 0) === $anoFiltro
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
            return [
                'ok' => false,
                'error' => $quadro['error'] ?? ('Não encontrei boletim montado para ' . $anoFiltro . '.'),
                'aluno' => $aluno['aluno'],
            ];
        }

        return [
            'ok' => true,
            'formato' => 'eventos',
            'aluno' => $aluno['aluno'],
            'ano_letivo' => $anoFiltro,
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
            'quadro' => [
                'colunas' => $colunas,
                'grupos' => $this->agruparCabecalhosColunas($colunas),
                'linhas' => $linhas,
            ],
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
        $regra = null;
        if ($regraIdPreferida > 0) {
            $regra = $boletim->getRuleById($regraIdPreferida);
            if (is_array($regra) && (int) ($regra['ano_letivo'] ?? 0) !== $anoLetivo) {
                return [
                    'ok' => false,
                    'error' => 'A regra #' . $regraIdPreferida . ' não pertence ao ano letivo ' . $anoLetivo . '.',
                ];
            }
        }
        if (!is_array($regra) || empty($regra['componentes'])) {
            $regra = $this->resolverRegraQuadroBoletim($anoLetivo);
        }
        if (!is_array($regra) || empty($regra['componentes'])) {
            return ['ok' => false, 'error' => 'Nenhuma regra de boletim/impressão encontrada para ' . $anoLetivo . '.'];
        }

        $matriz = $this->simularMatrizRegraAluno($regra, $alunoId, $anoLetivo);
        if ($matriz === null || empty($matriz['colunas']) || empty($matriz['linhas'])) {
            return ['ok' => false, 'error' => 'Não foi possível montar o quadro do boletim.'];
        }

        return [
            'ok' => true,
            'regra' => [
                'id' => (int) ($regra['id'] ?? 0),
                'nome' => trim((string) ($regra['nome'] ?? '')),
            ],
            'colunas' => $matriz['colunas'],
            'grupos' => $this->agruparCabecalhosColunas($matriz['colunas']),
            'linhas' => $matriz['linhas'],
        ];
    }

    /**
     * Prefere regra de impressão/quadro anual (muitas colunas) do ano.
     *
     * @return array<string,mixed>|null
     */
    private function resolverRegraQuadroBoletim(int $anoLetivo): ?array
    {
        if ($anoLetivo < 2000) {
            return null;
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

        $melhor = null;
        $melhorScore = -1;
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
            if ($n < 8) {
                continue;
            }
            $blob = mb_strtolower(
                trim((string) ($row['codigo'] ?? '')) . ' ' . trim((string) ($row['nome'] ?? '')),
                'UTF-8'
            );
            $score = $n;
            if (str_contains($blob, 'impress') || str_contains($blob, 'print') || str_contains($blob, 'quadro')) {
                $score += 100;
            }
            if ((string) ($regra['exibir_em'] ?? '') === 'boletim') {
                $score += 40;
            }
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $regra;
            }
        }
        return $melhor;
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
