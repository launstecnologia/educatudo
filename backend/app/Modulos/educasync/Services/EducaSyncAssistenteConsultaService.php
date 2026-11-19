<?php

namespace App\Modulos\EducaSync\Services;

use App\Modulos\EducaSync\Models\Alocacao;
use App\Modulos\EducaSync\Models\Cenario;
use App\Modulos\EducaSync\Models\Demanda;
use App\Modulos\EducaSync\Models\Disponibilidade;
use App\Modulos\EducaSync\Models\Execucao;
use App\Modulos\EducaSync\Models\Slot;
use App\Modulos\EducaSync\Models\Versao;

/**
 * EducaSync — consultas somente leitura das tools do assistente.
 *
 * Só lê pelos Models do módulo (sem SQL vindo do LLM) e devolve o mínimo de dados pessoais:
 * ids e nomes de professor/turma/matéria. Nunca altera o cenário nem publica a grade.
 */
class EducaSyncAssistenteConsultaService
{
    private const LIMITE_LISTA = 20;

    private Cenario $cenarios;
    private Slot $slots;
    private Demanda $demandas;
    private Versao $versoes;
    private Alocacao $alocacoes;
    private Execucao $execucoes;
    private Disponibilidade $disponibilidades;
    private EducaSyncContextoService $contexto;
    private EducaSyncPropostaService $propostas;

    public function __construct(
        ?Cenario $cenarios = null,
        ?Slot $slots = null,
        ?Demanda $demandas = null,
        ?Versao $versoes = null,
        ?Alocacao $alocacoes = null,
        ?Execucao $execucoes = null,
        ?Disponibilidade $disponibilidades = null,
        ?EducaSyncContextoService $contexto = null,
        ?EducaSyncPropostaService $propostas = null
    ) {
        $this->cenarios = $cenarios ?? new Cenario();
        $this->slots = $slots ?? new Slot();
        $this->demandas = $demandas ?? new Demanda();
        $this->versoes = $versoes ?? new Versao();
        $this->alocacoes = $alocacoes ?? new Alocacao();
        $this->execucoes = $execucoes ?? new Execucao();
        $this->disponibilidades = $disponibilidades ?? new Disponibilidade();
        $this->contexto = $contexto ?? new EducaSyncContextoService();
        $this->propostas = $propostas ?? new EducaSyncPropostaService();
    }

    /**
     * Tool get_school_schedule_context — resumo do cenário.
     *
     * @return array<string,mixed>
     */
    public function contextoCenario(int $cenarioId): array
    {
        $cenario = $this->cenarios->findById($cenarioId);
        if (!$cenario) {
            return ['ok' => false, 'error' => 'Cenário não encontrado.'];
        }

        $slots = $this->slots->listarPorCenario($cenarioId);
        $slotsPorDia = [];
        $gradePorDia = [];
        $gradeDiaria = [];
        $ordensVistas = [];
        foreach ($slots as $s) {
            $diaNum = (int) $s['dia_semana'];
            $dia = Slot::DIAS[$diaNum] ?? (string) $diaNum;
            $ordem = (int) $s['ordem'];
            $inicio = substr((string) ($s['horario_de'] ?? $s['inicio'] ?? ''), 0, 5);
            $fim = substr((string) ($s['horario_ate'] ?? $s['fim'] ?? ''), 0, 5);
            $slotsPorDia[$dia] = ($slotsPorDia[$dia] ?? 0) + 1;
            $gradePorDia[$dia][] = [
                'ordem' => $ordem,
                'inicio' => $inicio,
                'fim' => $fim,
                'slot_id' => (int) $s['id'],
            ];
            if (!isset($ordensVistas[$ordem])) {
                $ordensVistas[$ordem] = true;
                $gradeDiaria[] = [
                    'ordem' => $ordem,
                    'inicio' => $inicio,
                    'fim' => $fim,
                ];
            }
        }
        usort($gradeDiaria, static fn(array $a, array $b): int => $a['ordem'] <=> $b['ordem']);
        $maxOrdem = $gradeDiaria !== [] ? (int) end($gradeDiaria)['ordem'] : 0;

        $demandas = $this->demandas->listarPorCenario($cenarioId);
        $aulas = 0;
        $semProfessor = 0;
        $professores = [];
        $turmas = [];
        $materias = [];
        foreach ($demandas as $d) {
            $qtd = (int) $d['aulas_semana'];
            $aulas += $qtd;
            if (empty($d['professor_id'])) {
                $semProfessor++;
            } else {
                $pid = (int) $d['professor_id'];
                $professores[$pid] ??= [
                    'id' => $pid,
                    'nome' => (string) ($d['professor_nome'] ?? ''),
                    'materias' => [],
                    'aulas_semana' => 0,
                    'nas_demandas' => true,
                ];
                $professores[$pid]['aulas_semana'] += $qtd;
                $matNome = trim((string) ($d['materia_nome'] ?? ''));
                if ($matNome !== '') {
                    $professores[$pid]['materias'][$matNome] = $matNome;
                }
            }
            $turmas[(int) $d['turma_id']] = ['id' => (int) $d['turma_id'], 'nome' => (string) ($d['turma_nome'] ?? '')];
            $materias[(int) $d['materia_id']] = ['id' => (int) $d['materia_id'], 'nome' => (string) ($d['materia_nome'] ?? '')];
        }

        // Inclui docentes vinculados às turmas do ano (mesmo sem demanda agregada, ex.: filhos da LP).
        $turmaIdsAno = array_map(
            static fn ($t) => (int) ($t['id'] ?? 0),
            $this->cenarios->turmasAtivas((int) $cenario['ano_letivo'])
        );
        foreach ($this->cenarios->professoresDasTurmas($turmaIdsAno, array_keys($professores)) as $prof) {
            $pid = (int) ($prof['id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $professores[$pid] ??= [
                'id' => $pid,
                'nome' => (string) ($prof['nome'] ?? ''),
                'materias' => [],
                'aulas_semana' => 0,
                'nas_demandas' => false,
            ];
            foreach ((array) ($prof['materias_lista'] ?? []) as $matNome) {
                $matNome = trim((string) $matNome);
                if ($matNome !== '') {
                    $professores[$pid]['materias'][$matNome] = $matNome;
                }
            }
        }

        $bloqueiosPorProfessor = [];
        foreach ($this->disponibilidades->resumoBloqueiosProfessores($cenarioId) as $b) {
            $bloqueiosPorProfessor[(int) $b['professor_id']] = (int) $b['total_bloqueados'];
        }
        foreach ($professores as $pid => &$p) {
            $p['slots_bloqueados'] = $bloqueiosPorProfessor[$pid] ?? 0;
            $p['materias'] = array_values((array) ($p['materias'] ?? []));
            $p['rotulo'] = $p['materias'] !== []
                ? implode(', ', $p['materias']) . ' — ' . $p['nome']
                : $p['nome'];
        }
        unset($p);

        $versoes = [];
        foreach (array_slice($this->versoes->listarPorCenario($cenarioId), 0, 5) as $v) {
            $versoes[] = [
                'versao_id' => (int) $v['id'],
                'status' => (string) $v['status'],
                'solver_status' => $v['solver_status'],
                'metricas' => $this->decodificar($v['metricas_json'] ?? null),
                'criada_em' => (string) $v['created_at'],
            ];
        }

        $execucoes = [];
        foreach ($this->execucoes->listarPorCenario($cenarioId, 5) as $e) {
            $execucoes[] = [
                'execucao_id' => (int) $e['id'],
                'status' => (string) $e['status'],
                'solver_status' => $e['solver_status'],
                'tempo_ms' => $e['tempo_ms'] !== null ? (int) $e['tempo_ms'] : null,
                'versao_id' => $e['versao_id'] !== null ? (int) $e['versao_id'] : null,
                'resumo' => $e['log_resumo'],
                'criada_em' => (string) $e['created_at'],
            ];
        }

        return [
            'ok' => true,
            'cenario' => [
                'id' => (int) $cenario['id'],
                'nome' => (string) $cenario['nome'],
                'ano_letivo' => (int) $cenario['ano_letivo'],
                'periodo' => (string) $cenario['periodo'],
                'status' => (string) $cenario['status'],
                'tempo_maximo_solver_s' => $this->contexto->tempoMaximo($cenario),
            ],
            'horarios' => [
                'total_slots' => count($slots),
                'por_dia' => $slotsPorDia,
                'max_ordem' => $maxOrdem,
                'grade_diaria' => $gradeDiaria,
                'grade_por_dia' => $gradePorDia,
                'como_interpretar' => [
                    'todo_periodo' => 'bloquear_professor_dia',
                    'n_primeiras_aulas' => 'ordens 1..N',
                    'n_ultimas_aulas' => 'as N maiores ordens (ex.: max=6 e 2 últimas → 5,6)',
                    'ultima_aula' => 'maior ordem',
                    'apos_as_Xh' => 'slots do dia com inicio >= HH:00 (inclui o que começa em X)',
                    'vocabulario_usuario' => 'Diga sempre "aula/aulas" (nunca "tempo/tempos") ao falar com o usuário.',
                ],
            ],
            'demandas' => [
                'total' => count($demandas),
                'aulas_semana_total' => $aulas,
                'sem_professor' => $semProfessor,
            ],
            'pesos' => $this->contexto->pesosDoCenario($cenario),
            'professores' => array_slice(array_values($professores), 0, 80),
            'turmas' => array_slice(array_values($turmas), 0, 80),
            'materias' => array_slice(array_values($materias), 0, 80),
            'versoes_recentes' => $versoes,
            'execucoes_recentes' => $execucoes,
            'cenario_travado_por_versoes' => $this->versoes->listarPorCenario($cenarioId) !== [],
        ];
    }

    /**
     * Tool get_teacher_availability — slots livres e bloqueados de um professor.
     *
     * @param array<string,mixed> $args professor_id ou professor_nome
     * @return array<string,mixed>
     */
    public function disponibilidadeProfessor(int $cenarioId, array $args): array
    {
        [$professorId, $professorNome, $erro] = $this->propostas->resolverEntidade('professores', $args, 'professor_id', 'professor_nome', null);
        if ($erro !== null) {
            return ['ok' => false, 'error' => $erro];
        }

        $slots = $this->slots->listarPorCenario($cenarioId);
        $bloqueadosIds = [];
        foreach ($this->disponibilidades->listarBloqueiosProfessor($cenarioId, (int) $professorId) as $b) {
            $bloqueadosIds[(int) $b['slot_id']] = true;
        }

        $livres = [];
        $bloqueados = [];
        foreach ($slots as $s) {
            $dia = Slot::DIAS[(int) $s['dia_semana']] ?? (string) $s['dia_semana'];
            $rotulo = Slot::rotulo($s);
            if (isset($bloqueadosIds[(int) $s['id']])) {
                $bloqueados[$dia][] = $rotulo;
            } else {
                $livres[$dia][] = $rotulo;
            }
        }

        $aulas = 0;
        foreach ($this->demandas->listarPorCenario($cenarioId) as $d) {
            if ((int) ($d['professor_id'] ?? 0) === (int) $professorId) {
                $aulas += (int) $d['aulas_semana'];
            }
        }

        return [
            'ok' => true,
            'professor' => ['id' => $professorId, 'nome' => $professorNome],
            'aulas_semana_demandadas' => $aulas,
            'total_slots' => count($slots),
            'total_livres' => count($slots) - count($bloqueadosIds),
            'total_bloqueados' => count($bloqueadosIds),
            'livres_por_dia' => $livres,
            'bloqueados_por_dia' => $bloqueados,
            'observacao' => 'Sem registro de bloqueio = horário livre.',
        ];
    }

    /**
     * Tool analyze_schedule_conflicts.
     * Com versao_id: confere as alocações. Sem versão: valida o cenário e procura conflitos óbvios.
     *
     * @param array<string,mixed> $args versao_id opcional
     * @return array<string,mixed>
     */
    public function analisarConflitos(int $cenarioId, array $args): array
    {
        $versaoId = (int) ($args['versao_id'] ?? 0);
        if ($versaoId > 0) {
            $versao = $this->versaoDoCenario($cenarioId, $versaoId);
            if ($versao === null) {
                return ['ok' => false, 'error' => 'Versão não encontrada neste cenário.'];
            }
            return $this->conflitosDaVersao($cenarioId, $versaoId);
        }
        return $this->conflitosDoCenario($cenarioId);
    }

    /**
     * Tool compare_schedule_versions.
     *
     * @param array<string,mixed> $args versao_a, versao_b
     * @return array<string,mixed>
     */
    public function compararVersoes(int $cenarioId, array $args): array
    {
        $idA = (int) ($args['versao_a'] ?? 0);
        $idB = (int) ($args['versao_b'] ?? 0);
        if ($idA <= 0 || $idB <= 0 || $idA === $idB) {
            return ['ok' => false, 'error' => 'Informe duas versões diferentes (versao_a e versao_b).'];
        }
        $versaoA = $this->versaoDoCenario($cenarioId, $idA);
        $versaoB = $this->versaoDoCenario($cenarioId, $idB);
        if ($versaoA === null || $versaoB === null) {
            return ['ok' => false, 'error' => 'Uma das versões não pertence a este cenário.'];
        }

        $metricasA = $this->decodificar($versaoA['metricas_json'] ?? null);
        $metricasB = $this->decodificar($versaoB['metricas_json'] ?? null);
        $diffMetricas = [];
        foreach (array_unique(array_merge(array_keys($metricasA), array_keys($metricasB))) as $chave) {
            $a = $metricasA[$chave] ?? null;
            $b = $metricasB[$chave] ?? null;
            if (is_numeric($a) && is_numeric($b)) {
                $diffMetricas[(string) $chave] = ['a' => $a + 0, 'b' => $b + 0, 'diferenca_b_menos_a' => round(($b + 0) - ($a + 0), 3)];
            }
        }

        $alocA = $this->alocacoes->listarPorVersao($idA);
        $alocB = $this->alocacoes->listarPorVersao($idB);
        $paresA = [];
        $paresB = [];
        $porDemanda = [];
        foreach ($alocA as $a) {
            $paresA[(int) $a['demanda_id'] . ':' . (int) $a['slot_id']] = true;
            $porDemanda[(int) $a['demanda_id']]['info'] = $a;
            $porDemanda[(int) $a['demanda_id']]['a'][] = Slot::rotulo($a);
        }
        foreach ($alocB as $b) {
            $paresB[(int) $b['demanda_id'] . ':' . (int) $b['slot_id']] = true;
            $porDemanda[(int) $b['demanda_id']]['info'] ??= $b;
            $porDemanda[(int) $b['demanda_id']]['b'][] = Slot::rotulo($b);
        }

        $iguais = count(array_intersect_key($paresA, $paresB));
        $exemplos = [];
        $demandasAlteradas = 0;
        foreach ($porDemanda as $dados) {
            $listaA = $dados['a'] ?? [];
            $listaB = $dados['b'] ?? [];
            sort($listaA);
            sort($listaB);
            if ($listaA === $listaB) {
                continue;
            }
            $demandasAlteradas++;
            if (count($exemplos) < 10) {
                $exemplos[] = [
                    'turma' => (string) ($dados['info']['turma_nome'] ?? ''),
                    'materia' => (string) ($dados['info']['materia_nome'] ?? ''),
                    'professor' => (string) ($dados['info']['professor_nome'] ?? ''),
                    'horarios_versao_a' => $listaA,
                    'horarios_versao_b' => $listaB,
                ];
            }
        }

        return [
            'ok' => true,
            'versao_a' => ['id' => $idA, 'status' => (string) $versaoA['status'], 'solver_status' => $versaoA['solver_status'], 'alocacoes' => count($alocA)],
            'versao_b' => ['id' => $idB, 'status' => (string) $versaoB['status'], 'solver_status' => $versaoB['solver_status'], 'alocacoes' => count($alocB)],
            'metricas' => $diffMetricas,
            'alocacoes_iguais' => $iguais,
            'alocacoes_somente_na_a' => count($paresA) - $iguais,
            'alocacoes_somente_na_b' => count($paresB) - $iguais,
            'demandas_com_horarios_diferentes' => $demandasAlteradas,
            'exemplos_diferencas' => $exemplos,
        ];
    }

    /**
     * Tool explain_schedule_result — fatos do solver separados de sugestões genéricas.
     *
     * @param array<string,mixed> $args versao_id
     * @return array<string,mixed>
     */
    public function explicarResultado(int $cenarioId, array $args): array
    {
        $versaoId = (int) ($args['versao_id'] ?? 0);
        $versao = $versaoId > 0 ? $this->versaoDoCenario($cenarioId, $versaoId) : null;
        if ($versao === null) {
            return ['ok' => false, 'error' => 'Informe um versao_id válido deste cenário.'];
        }

        $alocacoes = $this->alocacoes->listarPorVersao($versaoId);
        $demandado = 0;
        foreach ($this->demandas->listarPorCenario($cenarioId) as $d) {
            $demandado += (int) $d['aulas_semana'];
        }
        $janelas = $this->janelasPorProfessor($alocacoes);

        $execucao = null;
        foreach ($this->execucoes->listarPorCenario($cenarioId, 50) as $e) {
            if ((int) ($e['versao_id'] ?? 0) === $versaoId) {
                $execucao = $e;
                break;
            }
        }

        $solverStatus = (string) ($versao['solver_status'] ?? '');
        $significado = [
            'OPTIMAL' => 'O solver provou que não existe solução melhor para as preferências cadastradas.',
            'FEASIBLE' => 'Solução válida, mas o solver não provou que é a melhor (tempo máximo esgotado).',
        ][$solverStatus] ?? 'Status sem interpretação padronizada.';

        $totalJanelas = array_sum(array_column($janelas, 'janelas'));
        $sugestoes = [];
        if ($solverStatus === 'FEASIBLE') {
            $sugestoes[] = 'Aumentar o tempo máximo do solver pode permitir uma solução melhor.';
        }
        if ($totalJanelas > 0) {
            $sugestoes[] = 'Aumentar o peso de janelas tende a compactar a agenda dos professores, ao custo de menos flexibilidade para outras preferências.';
            $sugestoes[] = 'Revisar bloqueios de professores com muitas janelas pode abrir espaço para aulas mais seguidas.';
        }
        if ($demandado > 0 && count($alocacoes) < $demandado) {
            $sugestoes[] = 'Há menos aulas alocadas que o demandado: revise a quantidade de horários e as demandas.';
        }

        return [
            'ok' => true,
            'fatos_do_solver' => [
                'versao_id' => $versaoId,
                'status_versao' => (string) $versao['status'],
                'solver_status' => $solverStatus,
                'significado_do_status' => $significado,
                'metricas' => $this->decodificar($versao['metricas_json'] ?? null),
                'aulas_alocadas' => count($alocacoes),
                'aulas_demandadas' => $demandado,
                'janelas_por_professor' => array_slice($janelas, 0, 10),
                'execucao' => $execucao ? [
                    'status' => (string) $execucao['status'],
                    'objetivo' => $execucao['objetivo'],
                    'melhor_limite' => $execucao['best_bound'],
                    'tempo_ms' => $execucao['tempo_ms'] !== null ? (int) $execucao['tempo_ms'] : null,
                    'resumo' => $execucao['log_resumo'],
                ] : null,
            ],
            'sugestoes_genericas' => $sugestoes,
            'aviso' => 'Os fatos vêm dos dados gravados da execução. As sugestões são genéricas e NÃO foram simuladas no solver.',
        ];
    }

    /**
     * Tool simulate_schedule — só orienta; nunca chama o solver nem publica.
     *
     * @return array<string,mixed>
     */
    public function orientarGeracao(): array
    {
        return [
            'ok' => true,
            'orientacao' => 'O assistente não gera nem publica grades. Use o botão "Gerar grade" na seção de otimização do cenário '
                . 'para criar uma nova versão e depois revise e publique manualmente.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function conflitosDoCenario(int $cenarioId): array
    {
        $problemas = $this->contexto->validar($cenarioId);
        $conflitos = [];

        $slots = $this->slots->listarPorCenario($cenarioId);
        $totalSlots = count($slots);
        $demandas = $this->demandas->listarPorCenario($cenarioId);

        $aulasProfessor = [];
        $nomeProfessor = [];
        $aulasSala = [];
        foreach ($demandas as $d) {
            $qtd = (int) $d['aulas_semana'];
            if (!empty($d['professor_id'])) {
                $pid = (int) $d['professor_id'];
                $aulasProfessor[$pid] = ($aulasProfessor[$pid] ?? 0) + $qtd;
                $nomeProfessor[$pid] = (string) ($d['professor_nome'] ?? $pid);
            }
            if (!empty($d['sala_id'])) {
                $sid = (int) $d['sala_id'];
                $aulasSala[$sid] = ($aulasSala[$sid] ?? 0) + $qtd;
            }
        }

        $bloqueados = [];
        foreach ($this->disponibilidades->resumoBloqueiosProfessores($cenarioId) as $b) {
            $bloqueados[(int) $b['professor_id']] = (int) $b['total_bloqueados'];
        }
        foreach ($aulasProfessor as $pid => $aulas) {
            $livres = $totalSlots - ($bloqueados[$pid] ?? 0);
            if ($aulas > $livres) {
                $conflitos[] = [
                    'tipo' => 'professor_sobrecarregado',
                    'professor_id' => $pid,
                    'professor' => $nomeProfessor[$pid],
                    'aulas_semana' => $aulas,
                    'horarios_livres' => max(0, $livres),
                    'detalhe' => 'Tem mais aulas que horários livres: é impossível alocar todas.',
                ];
            }
        }
        foreach ($aulasSala as $salaId => $aulas) {
            if ($aulas > $totalSlots) {
                $conflitos[] = [
                    'tipo' => 'sala_sobrecarregada',
                    'sala_id' => $salaId,
                    'aulas_semana' => $aulas,
                    'total_slots' => $totalSlots,
                    'detalhe' => 'A sala é pedida em mais aulas que o total de horários.',
                ];
            }
        }

        return [
            'ok' => true,
            'modo' => 'cenario',
            'pronto_para_gerar' => $problemas === [] && $conflitos === [],
            'problemas_de_validacao' => $problemas,
            'conflitos_obvios' => array_slice($conflitos, 0, self::LIMITE_LISTA),
            'nota' => 'Análise estática do cenário (sem rodar o solver). Não prova que existe solução.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function conflitosDaVersao(int $cenarioId, int $versaoId): array
    {
        $alocacoes = $this->alocacoes->listarPorVersao($versaoId);

        $bloqueados = [];
        foreach ($this->disponibilidades->listarPorCenario($cenarioId) as $d) {
            if ((string) $d['tipo'] === 'professor' && (int) $d['livre'] === 0) {
                $bloqueados[(int) $d['ref_id'] . ':' . (int) $d['slot_id']] = true;
            }
        }

        $turmaSlot = [];
        $profSlot = [];
        $porDemanda = [];
        $emBloqueio = [];
        foreach ($alocacoes as $a) {
            $slotId = (int) $a['slot_id'];
            $turmaSlot[(int) $a['turma_id'] . ':' . $slotId][] = $a;
            $pid = (int) ($a['professor_id'] ?? 0);
            if ($pid > 0) {
                $profSlot[$pid . ':' . $slotId][] = $a;
                if (isset($bloqueados[$pid . ':' . $slotId])) {
                    $emBloqueio[] = [
                        'tipo' => 'professor_em_horario_bloqueado',
                        'professor' => (string) ($a['professor_nome'] ?? ''),
                        'turma' => (string) ($a['turma_nome'] ?? ''),
                        'horario' => Slot::rotulo($a),
                    ];
                }
            }
            $porDemanda[(int) $a['demanda_id']] = ($porDemanda[(int) $a['demanda_id']] ?? 0) + 1;
        }

        $conflitos = $emBloqueio;
        foreach ($turmaSlot as $lista) {
            if (count($lista) > 1) {
                $conflitos[] = ['tipo' => 'turma_duas_aulas_no_mesmo_horario', 'turma' => (string) ($lista[0]['turma_nome'] ?? ''), 'horario' => Slot::rotulo($lista[0])];
            }
        }
        foreach ($profSlot as $lista) {
            if (count($lista) > 1) {
                $conflitos[] = ['tipo' => 'professor_duas_aulas_no_mesmo_horario', 'professor' => (string) ($lista[0]['professor_nome'] ?? ''), 'horario' => Slot::rotulo($lista[0])];
            }
        }
        foreach ($this->demandas->listarPorCenario($cenarioId) as $d) {
            $alocadas = $porDemanda[(int) $d['id']] ?? 0;
            if ($alocadas !== (int) $d['aulas_semana']) {
                $conflitos[] = [
                    'tipo' => 'demanda_incompleta',
                    'turma' => (string) ($d['turma_nome'] ?? ''),
                    'materia' => (string) ($d['materia_nome'] ?? ''),
                    'aulas_demandadas' => (int) $d['aulas_semana'],
                    'aulas_alocadas' => $alocadas,
                ];
            }
        }

        $publicacao = (new EducaSyncPublicacaoService())->simular($cenarioId, $versaoId);
        $conflitosPublicacao = [];
        $sugestoes = [];
        if (empty($publicacao['success'])) {
            $conflitosPublicacao = is_array($publicacao['conflitos'] ?? null) ? $publicacao['conflitos'] : [];
            $sugestoes = is_array($publicacao['sugestoes'] ?? null) ? $publicacao['sugestoes'] : [];
            if ($sugestoes === [] && !empty($publicacao['error'])) {
                $sugestoes[] = (string) $publicacao['error'];
            }
        }

        // Sobrecarga óbvia (aulas > horários livres) → reforça sugestão de contratar/redistribuir.
        $cenario = $this->conflitosDoCenario($cenarioId);
        foreach ((array) ($cenario['conflitos_obvios'] ?? []) as $c) {
            if (($c['tipo'] ?? '') !== 'professor_sobrecarregado') {
                continue;
            }
            $conflitos[] = $c;
            $sugestoes[] = ($c['professor'] ?? 'Um professor') . ' tem '
                . (int) ($c['aulas_semana'] ?? 0) . ' aulas e só '
                . (int) ($c['horarios_livres'] ?? 0)
                . ' horários livres: redistribua a carga ou contrate outro docente dessa matéria.';
        }

        return [
            'ok' => true,
            'modo' => 'versao',
            'versao_id' => $versaoId,
            'total_alocacoes' => count($alocacoes),
            'total_conflitos' => count($conflitos),
            'conflitos' => array_slice($conflitos, 0, self::LIMITE_LISTA),
            'publicacao' => [
                'pode_publicar' => !empty($publicacao['success']),
                'erro' => (string) ($publicacao['error'] ?? ''),
                'total_conflitos' => count($conflitosPublicacao),
                'conflitos' => array_slice($conflitosPublicacao, 0, self::LIMITE_LISTA),
                'sugestoes' => array_values(array_unique($sugestoes)),
            ],
            'orientacao' => 'Em Sugestões, diga se precisa contratar, redistribuir demandas entre professores, limpar grade de outras turmas do mesmo ano ou reotimizar. Não invente números fora destes dados.',
        ];
    }

    /**
     * Janelas = horários vazios entre a primeira e a última aula do professor no dia.
     *
     * @param list<array<string,mixed>> $alocacoes
     * @return list<array{professor_id:int, professor:string, janelas:int}> ordenado do pior para o melhor
     */
    private function janelasPorProfessor(array $alocacoes): array
    {
        $ordens = [];
        $nomes = [];
        foreach ($alocacoes as $a) {
            $pid = (int) ($a['professor_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $ordens[$pid][(int) $a['dia_semana']][(int) $a['ordem']] = true;
            $nomes[$pid] = (string) ($a['professor_nome'] ?? '');
        }

        $out = [];
        foreach ($ordens as $pid => $dias) {
            $janelas = 0;
            foreach ($dias as $listaOrdens) {
                $janelas += (max(array_keys($listaOrdens)) - min(array_keys($listaOrdens)) + 1) - count($listaOrdens);
            }
            $out[] = ['professor_id' => $pid, 'professor' => $nomes[$pid], 'janelas' => $janelas];
        }
        usort($out, static fn(array $x, array $y): int => $y['janelas'] <=> $x['janelas']);
        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function versaoDoCenario(int $cenarioId, int $versaoId): ?array
    {
        $versao = $this->versoes->findById($versaoId);
        return $versao && (int) $versao['cenario_id'] === $cenarioId ? $versao : null;
    }

    /**
     * @param mixed $json
     * @return array<string,mixed>
     */
    private function decodificar($json): array
    {
        $dec = json_decode((string) $json, true);
        return is_array($dec) ? $dec : [];
    }
}
