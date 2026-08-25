<?php

namespace App\Modulos\Diario\Services;

require_once __DIR__ . '/../Models/ClassDiary.php';
require_once __DIR__ . '/../../../Models/Education/SchoolAbsence.php';

use App\Modulos\Diario\Models\ClassDiary;
use Database;
use RuntimeException;
use SchoolAbsence;

/**
 * EducaTudo - ClassDiaryService
 *
 * Fachada de negócio do Diário de Classe usada pelo acompanhamento e pelos
 * relatórios da coordenação/secretaria. Consolida acompanhamento, pendências,
 * indicadores (semáforo) e o detalhe de uma aula a partir do Model ClassDiary.
 */
class ClassDiaryService
{
    /** @var ClassDiary */
    private $diary;

    /** @var SchoolAbsence */
    private $absence;

    /** Rótulos legíveis de situação dos indicadores. */
    public const SITUACAO_LABELS = [
        'em_dia' => 'Em dia',
        'atencao' => 'Atenção',
        'atraso' => 'Em atraso',
    ];

    public function __construct(?ClassDiary $diary = null)
    {
        $this->diary = $diary ?? new ClassDiary();
        $this->diary->ensureSchema();
        $this->absence = new SchoolAbsence();
    }

    /**
     * Abre (e cria se necessário) uma aula para lançamento de chamada pela coordenação,
     * a partir de uma linha pendente da grade horária (grade_id + data).
     *
     * @return array{aula:array<string,mixed>,alunos:list<array<string,mixed>>,frequencias:array<int,array<string,mixed>>}
     */
    public function abrirParaLancamento(int $gradeId, string $data): array
    {
        $grade = $this->diary->getGrade($gradeId);
        if (!$grade || (int) date('N', strtotime($data)) !== (int) $grade['dia_semana']) {
            throw new RuntimeException('Aula não encontrada na grade para essa data.');
        }
        $plano = $this->diary->findPlanoParaAula((int) $grade['professor_id'], (int) $grade['turma_id'], (int) $grade['materia_id'], $data);
        $aula = $this->diary->getOrCreateAula($grade, $data, $plano ? (int) $plano['id'] : null);
        return $this->montarLancamento((int) $aula['id']);
    }

    /**
     * Abre uma aula já existente (registrada ou em rascunho) para edição pela coordenação.
     *
     * @return array{aula:array<string,mixed>,alunos:list<array<string,mixed>>,frequencias:array<int,array<string,mixed>>}
     */
    public function abrirAulaExistente(int $aulaId): array
    {
        return $this->montarLancamento($aulaId);
    }

    /**
     * @return array{aula:array<string,mixed>,alunos:list<array<string,mixed>>,frequencias:array<int,array<string,mixed>>,planos:list<array<string,mixed>>,eventos:list<array<string,mixed>>}
     */
    private function montarLancamento(int $aulaId): array
    {
        $aula = $this->diary->getAula($aulaId);
        if (!$aula) {
            throw new RuntimeException('Aula não encontrada.');
        }
        $ano = (int) ($aula['ano_letivo'] ?? date('Y', strtotime($aula['data_aula'])));
        $alunos = $this->absence->listAlunosByTurmas([(int) $aula['turma_id']], $ano);
        $professorId = (int) $aula['professor_id'];
        $turmaId = (int) $aula['turma_id'];
        $materiaId = (int) $aula['materia_id'];
        return [
            'aula' => $aula,
            'alunos' => $alunos,
            'frequencias' => $this->diary->frequenciasMap($aulaId),
            'planos' => $this->diary->planosDoDiario($professorId, $turmaId, $materiaId),
            'eventos' => $this->eventosNotaDoPeriodo($turmaId, $materiaId, $professorId, $ano . '-01-01', $ano . '-12-31'),
        ];
    }

    /**
     * Salva a chamada/conteúdo de uma aula lançada pela coordenação, restringindo
     * as frequências aos alunos efetivamente matriculados na turma da aula.
     *
     * @param array<int|string,mixed> $frequenciasPost
     * @param array{tipo_aula?:string,plano_aula_id?:int|null,evento_bloco_id?:int|null} $extras
     */
    public function salvarLancamento(int $aulaId, string $execucao, string $conteudo, string $observacoes, array $frequenciasPost, bool $finalizar, array $extras = []): void
    {
        $aula = $this->diary->getAula($aulaId);
        if (!$aula) {
            throw new RuntimeException('Aula não encontrada.');
        }
        $alunos = $this->absence->listAlunosByTurmas([(int) $aula['turma_id']], (int) date('Y', strtotime($aula['data_aula'])));
        $permitidos = array_fill_keys(array_map(static fn($a) => (int) $a['id'], $alunos), true);
        $frequencias = [];
        foreach ($frequenciasPost as $alunoId => $payload) {
            if (isset($permitidos[(int) $alunoId])) {
                $frequencias[(int) $alunoId] = $payload;
            }
        }
        $this->diary->salvar($aulaId, $execucao, $conteudo, $observacoes, $frequencias, $finalizar, $this->validarExtrasDaAula($aula, $extras));
    }

    /**
     * Extrai tipo/plano/evento do POST sem quebrar formulários antigos (campos ausentes = não altera).
     *
     * @param array<string,mixed> $post
     * @return array{tipo_aula?:string,plano_aula_id?:int,evento_bloco_id?:int}
     */
    public static function extrasDoPost(array $post): array
    {
        $extras = [];
        if (array_key_exists('tipo_aula', $post)) {
            $extras['tipo_aula'] = (string) $post['tipo_aula'];
        }
        if (array_key_exists('plano_aula_id', $post)) {
            $extras['plano_aula_id'] = (int) $post['plano_aula_id'];
        }
        if (array_key_exists('evento_bloco_id', $post)) {
            $extras['evento_bloco_id'] = (int) $post['evento_bloco_id'];
        }
        return $extras;
    }

    public static function tipoAulaLabel(string $tipo): string
    {
        return ClassDiary::TIPOS_AULA[$tipo] ?? ClassDiary::TIPOS_AULA['regular'];
    }

    /**
     * Aulas registradas no período (com totais de faltas), mescladas com as
     * pendentes (previstas na grade e ainda sem registro), conforme o filtro.
     *
     * @return list<array<string,mixed>>
     */
    public function acompanhamento(string $inicio, string $fim, int $professorId = 0, string $status = '', int $turmaId = 0): array
    {
        $registradas = $status === 'pendente' ? [] : $this->diary->acompanhamento($inicio, $fim, $professorId, $status, $turmaId);
        $pendentes = ($status === '' || $status === 'pendente') ? $this->diary->aulasPendentes($inicio, $fim, $professorId, $turmaId) : [];
        $aulas = array_merge($pendentes, $registradas);
        usort($aulas, static fn($a, $b) => strcmp(
            ($b['data_aula'] ?? '') . ($b['horario_de'] ?? ''),
            ($a['data_aula'] ?? '') . ($a['horario_de'] ?? '')
        ));
        return $aulas;
    }

    /**
     * Indicadores por professor × turma × matéria com semáforo de situação.
     *
     * @return list<array<string,mixed>>
     */
    public function indicadores(string $inicio, string $fim, int $professorId = 0): array
    {
        return $this->diary->indicadores($inicio, $fim, $professorId);
    }

    /**
     * Resumo dos indicadores para cartões (contagem por situação + cobertura média
     * e horas ministradas vs previstas).
     *
     * @param list<array<string,mixed>> $indicadores
     * @return array<string,mixed>
     */
    public function resumoIndicadores(array $indicadores): array
    {
        $resumo = [
            'total' => count($indicadores),
            'em_dia' => 0,
            'atencao' => 0,
            'atraso' => 0,
            'minutos_previstos' => 0,
            'minutos_ministrados' => 0,
        ];
        $somaPct = 0.0;
        $countPct = 0;
        foreach ($indicadores as $i) {
            $sit = (string) ($i['situacao'] ?? 'em_dia');
            if (isset($resumo[$sit])) {
                $resumo[$sit]++;
            }
            $resumo['minutos_previstos'] += (int) ($i['minutos_previstos'] ?? 0);
            $resumo['minutos_ministrados'] += (int) ($i['minutos_ministrados'] ?? 0);
            if ($i['percentual'] !== null) {
                $somaPct += (float) $i['percentual'];
                $countPct++;
            }
        }
        $resumo['cobertura_media'] = $countPct > 0 ? round($somaPct / $countPct, 1) : null;
        $resumo['horas_previstas'] = round($resumo['minutos_previstos'] / 60, 1);
        $resumo['horas_ministradas'] = round($resumo['minutos_ministrados'] / 60, 1);
        return $resumo;
    }

    /**
     * Detalhe completo de uma aula (dados + plano + chamada com nomes).
     *
     * @return array<string,mixed>|null
     */
    public function detalheAula(int $aulaId): ?array
    {
        $aula = $this->diary->getAula($aulaId);
        if (!$aula) {
            return null;
        }
        $aula['frequencias'] = $this->diary->frequenciasDetalhadas($aulaId, (int) $aula['turma_id']);
        $aula['resumo_frequencia'] = $this->contarFrequencias($aula['frequencias']);
        return $aula;
    }

    /**
     * @param list<array<string,mixed>> $frequencias
     * @return array<string,int>
     */
    private function contarFrequencias(array $frequencias): array
    {
        $cont = ['presente' => 0, 'falta' => 0, 'falta_justificada' => 0, 'atraso' => 0, 'saida_antecipada' => 0, 'sem_registro' => 0];
        foreach ($frequencias as $f) {
            $s = (string) ($f['situacao'] ?? '');
            if ($s === '' || !isset($cont[$s])) {
                $cont['sem_registro']++;
                continue;
            }
            $cont[$s]++;
        }
        return $cont;
    }

    /**
     * Lista de professores ativos (para filtros das telas).
     *
     * @return list<array<string,mixed>>
     */
    public function professoresAtivos(): array
    {
        return Database::getInstance()->fetchAll("SELECT id, nome FROM professores WHERE ativo = 1 ORDER BY nome") ?: [];
    }

    public function turmasAtivas(): array
    {
        return Database::getInstance()->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome") ?: [];
    }

    public static function situacaoLabel(string $situacao): string
    {
        return self::SITUACAO_LABELS[$situacao] ?? $situacao;
    }

    // ── Diários de Classe (visão agregada, Fase 1 da reestruturação) ───────

    /**
     * Diários de Classe de um professor (agrupados por Turma+Componente),
     * com indicadores agregados, para a tela "Diários de Classe".
     *
     * @param array{professor_id:int,inicio:string,fim:string,ano_letivo?:int,turma_id?:int,materia_id?:int,situacao?:string} $filtros
     * @return list<array<string,mixed>>
     */
    public function diarios(array $filtros): array
    {
        return $this->diary->diarios($filtros);
    }

    /** @return list<array{id:int,nome:string}> */
    public function turmasDoProfessor(int $professorId): array
    {
        return $this->diary->turmasDoProfessor($professorId);
    }

    /** @return list<array{id:int,nome:string}> */
    public function materiasDoProfessor(int $professorId): array
    {
        return $this->diary->materiasDoProfessor($professorId);
    }

    /** @return list<int> */
    public function anosLetivosDoProfessor(int $professorId): array
    {
        return $this->diary->anosLetivosDoProfessor($professorId);
    }

    /**
     * Confere se o professor realmente leciona essa combinação de
     * turma+matéria (ownership do diário, via grade_horaria).
     */
    public function professorLecionaDiario(int $turmaId, int $materiaId, int $professorId): bool
    {
        return !empty($this->diary->gradeIdsDoDiario($turmaId, $materiaId, $professorId));
    }

    /**
     * Aulas de um diário no período, para a aba "Aulas".
     *
     * @return list<array<string,mixed>>
     */
    public function aulasDoDiario(int $turmaId, int $materiaId, int $professorId, string $inicio, string $fim): array
    {
        return $this->diary->aulasDoDiario($turmaId, $materiaId, $professorId, $inicio, $fim);
    }

    /**
     * Indicadores + lista de pendências de um diário, para a aba "Resumo".
     *
     * @return array{resumo:array<string,mixed>,pendencias:list<array<string,mixed>>,eventos_proximos:list<array<string,mixed>>}
     */
    public function resumoDiario(int $turmaId, int $materiaId, int $professorId, string $inicio, string $fim): array
    {
        $aulas = $this->diary->aulasDoDiario($turmaId, $materiaId, $professorId, $inicio, $fim);
        $resumo = $this->contarResumoDasAulas($aulas);
        $pendencias = [];
        foreach ($aulas as $aula) {
            if (($aula['situacao'] ?? '') === 'pendente') {
                $aula['tipo_pendencia'] = 'chamada';
                $pendencias[] = $aula;
            } elseif (($aula['situacao'] ?? '') === 'finalizada'
                && trim(strip_tags((string) ($aula['conteudo_realizado'] ?? ''))) === '') {
                $aula['tipo_pendencia'] = 'conteudo';
                $pendencias[] = $aula;
            }
        }

        $planejamento = $this->planejamentoDoDiario($turmaId, $materiaId, $professorId, $inicio, $fim, $aulas);
        $resumo['planos_previstos'] = count($planejamento['planos']);
        $resumo['planos_relacionados'] = count(array_filter(
            $planejamento['planos'],
            static fn($p) => !empty($p['relacionado'])
        ));

        $eventos = $this->eventosNotaDoPeriodo($turmaId, $materiaId, $professorId, $inicio, $fim);
        $hoje = date('Y-m-d');
        $limiteProximos = date('Y-m-d', strtotime('+14 days'));
        $encerradas = 0;
        $proximos = [];
        foreach ($eventos as $evento) {
            $dataProva = (string) ($evento['data_prova'] ?? '');
            if ($dataProva !== '' && $dataProva < $hoje) {
                $encerradas++;
            } elseif ($dataProva >= $hoje && $dataProva <= $limiteProximos) {
                $proximos[] = $evento;
            }
        }
        $resumo['avaliacoes'] = count($eventos);
        $resumo['avaliacoes_encerradas'] = $encerradas;

        return ['resumo' => $resumo, 'pendencias' => $pendencias, 'eventos_proximos' => $proximos];
    }

    /**
     * @param list<array<string,mixed>> $aulas
     * @return array{aulas_previstas:int,aulas_previstas_ate_hoje:int,aulas_finalizadas:int,aulas_registradas:int,chamadas_pendentes:int,situacao:string}
     */
    private function contarResumoDasAulas(array $aulas): array
    {
        $hoje = date('Y-m-d');
        $previstasAteHoje = 0;
        $finalizadas = 0;
        $registradas = 0;
        $pendentes = 0;
        foreach ($aulas as $aula) {
            if (($aula['data_aula'] ?? '') <= $hoje) {
                $previstasAteHoje++;
            }
            $situacao = (string) ($aula['situacao'] ?? '');
            if ($situacao === 'finalizada') {
                $finalizadas++;
                $registradas++;
            } elseif (in_array($situacao, ['rascunho', 'nao_realizada'], true)) {
                $registradas++;
            } elseif ($situacao === 'pendente') {
                $pendentes++;
            }
        }
        if ($previstasAteHoje === 0 && $registradas === 0) {
            $situacaoGeral = 'em_dia';
        } elseif ($pendentes <= 0) {
            $situacaoGeral = 'em_dia';
        } elseif ($pendentes <= 2) {
            $situacaoGeral = 'atencao';
        } else {
            $situacaoGeral = 'atraso';
        }
        return [
            'aulas_previstas' => count($aulas),
            'aulas_previstas_ate_hoje' => $previstasAteHoje,
            'aulas_finalizadas' => $finalizadas,
            'aulas_registradas' => $registradas,
            'chamadas_pendentes' => $pendentes,
            'situacao' => $situacaoGeral,
        ];
    }

    /**
     * Planos do módulo existente + aulas do período para vincular (aba Planejamento).
     *
     * @param list<array<string,mixed>>|null $aulas
     * @return array{planos:list<array<string,mixed>>,aulas:list<array<string,mixed>>}
     */
    public function planejamentoDoDiario(int $turmaId, int $materiaId, int $professorId, string $inicio, string $fim, ?array $aulas = null): array
    {
        $vinculos = $this->diary->aulasVinculadasPorPlano($turmaId, $materiaId, $professorId);
        $aulas = $aulas ?? $this->diary->aulasDoDiario($turmaId, $materiaId, $professorId, $inicio, $fim);
        $planos = [];
        foreach ($this->diary->planosDoDiario($professorId, $turmaId, $materiaId) as $plano) {
            $datas = $this->diary->datasDoPlano($plano);
            $noPeriodo = $datas === [];
            foreach ($datas as $data) {
                if ($data >= $inicio && $data <= $fim) {
                    $noPeriodo = true;
                    break;
                }
            }
            if (!$noPeriodo) {
                continue;
            }
            $aulasPlano = $vinculos[(int) $plano['id']] ?? [];
            $planos[] = [
                'id' => (int) $plano['id'],
                'titulo' => (string) $plano['titulo'],
                'status' => (string) ($plano['status'] ?? ''),
                'datas' => $datas,
                'aulas_vinculadas' => $aulasPlano,
                'relacionado' => $aulasPlano !== [],
            ];
        }
        return ['planos' => $planos, 'aulas' => $aulas];
    }

    /**
     * Associa uma aula (criada sob demanda a partir da grade) a um plano já existente.
     */
    public function vincularPlanoAula(int $professorId, int $turmaId, int $materiaId, int $planoId, int $gradeId, string $data): void
    {
        if (!$this->professorLecionaDiario($turmaId, $materiaId, $professorId)) {
            throw new RuntimeException('Diário não encontrado ou sem permissão.');
        }
        $grade = $this->diary->getGradeDoProfessor($gradeId, $professorId);
        if (!$grade
            || (int) $grade['turma_id'] !== $turmaId
            || (int) $grade['materia_id'] !== $materiaId
        ) {
            throw new RuntimeException('Aula não encontrada na grade deste diário.');
        }
        if ((int) date('N', strtotime($data)) !== (int) $grade['dia_semana']) {
            throw new RuntimeException('A data não corresponde ao dia da grade.');
        }
        if (!$this->diary->getPlanoDoDiario($planoId, $professorId, $turmaId, $materiaId)) {
            throw new RuntimeException('Plano de aula inválido para este diário.');
        }
        $aula = $this->diary->getOrCreateAula($grade, $data, $planoId);
        $this->diary->atualizarVinculos((int) $aula['id'], ['plano_aula_id' => $planoId]);
    }

    /**
     * @param array<string,mixed> $aula
     * @param array{tipo_aula?:string,plano_aula_id?:int|null,evento_bloco_id?:int|null} $extras
     * @return array{tipo_aula?:string,plano_aula_id?:int|null,evento_bloco_id?:int|null}
     */
    public function validarExtrasDaAula(array $aula, array $extras): array
    {
        $professorId = (int) ($aula['professor_id'] ?? 0);
        $turmaId = (int) ($aula['turma_id'] ?? 0);
        $materiaId = (int) ($aula['materia_id'] ?? 0);
        if (array_key_exists('plano_aula_id', $extras) && (int) $extras['plano_aula_id'] > 0) {
            if (!$this->diary->getPlanoDoDiario((int) $extras['plano_aula_id'], $professorId, $turmaId, $materiaId)) {
                throw new RuntimeException('Plano de aula inválido para este diário.');
            }
        }
        if (array_key_exists('evento_bloco_id', $extras) && (int) $extras['evento_bloco_id'] > 0) {
            if (!$this->eventoPertenceAoDiario((int) $extras['evento_bloco_id'], $turmaId, $materiaId, $professorId)) {
                throw new RuntimeException('Evento de prova/nota inválido para este diário.');
            }
        }
        return $extras;
    }

    public function eventoPertenceAoDiario(int $eventoId, int $turmaId, int $materiaId, int $professorId): bool
    {
        if ($eventoId <= 0) {
            return false;
        }
        $row = Database::getInstance()->fetch(
            "SELECT pb.id
             FROM provas_blocos pb
             INNER JOIN provas_blocos_professores pbp ON pbp.bloco_id = pb.id
                  AND pbp.professor_id = :professor_id AND pbp.materia_id = :materia_id
             INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
                  AND pbpt.turma_id = :turma_id
             WHERE pb.id = :id AND pb.deleted_at IS NULL
             LIMIT 1",
            ['id' => $eventoId, 'professor_id' => $professorId, 'materia_id' => $materiaId, 'turma_id' => $turmaId]
        );
        return (bool) $row;
    }

    /**
     * Eventos de Nota (provas_blocos) do período em que o professor é
     * responsável por essa matéria nessa turma — somente consulta, a fonte
     * de verdade continua sendo o módulo de Eventos de Nota/Prova.
     *
     * @return list<array<string,mixed>>
     */
    public function eventosNotaDoPeriodo(int $turmaId, int $materiaId, int $professorId, string $inicio, string $fim): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT DISTINCT pb.id, pb.titulo, pb.data_prova, pb.hora_inicio, pb.hora_fim,
                    pb.liberado, pb.gabarito_liberado, pb.configuracao_nota
             FROM provas_blocos pb
             INNER JOIN provas_blocos_professores pbp ON pbp.bloco_id = pb.id
                  AND pbp.professor_id = :professor_id AND pbp.materia_id = :materia_id
             INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
                  AND pbpt.turma_id = :turma_id
             WHERE pb.deleted_at IS NULL AND pb.data_prova BETWEEN :inicio AND :fim
             ORDER BY pb.data_prova ASC",
            ['professor_id' => $professorId, 'materia_id' => $materiaId, 'turma_id' => $turmaId,
             'inicio' => $inicio, 'fim' => $fim]
        ) ?: [];
    }

    /**
     * Eventos do período com as aulas do diário já vinculadas (só consulta).
     *
     * @return list<array<string,mixed>>
     */
    public function eventosNotaComVinculos(int $turmaId, int $materiaId, int $professorId, string $inicio, string $fim): array
    {
        $eventos = $this->eventosNotaDoPeriodo($turmaId, $materiaId, $professorId, $inicio, $fim);
        $vinculos = $this->diary->aulasVinculadasPorEvento($turmaId, $materiaId, $professorId);
        foreach ($eventos as &$evento) {
            $evento['aulas_vinculadas'] = $vinculos[(int) $evento['id']] ?? [];
        }
        unset($evento);
        return $eventos;
    }

    /**
     * Fechamento de um bimestre. O período do bimestre é sempre calculado no
     * servidor (`ClassDiary::periodoDoBimestre()`, trimestre do calendário
     * civil) — nunca a partir de datas vindas do cliente, pra não permitir
     * contornar a checagem de pendências enviando um intervalo estreito.
     * Bloqueia se houver chamada pendente no período. Notas/avaliações
     * pendentes são só informativas nesta fase — a validação de fechamento
     * cobre a parte que o próprio Diário controla (chamadas).
     */
    public function fechar(int $turmaId, int $materiaId, int $professorId, int $anoLetivo, int $bimestre, int $usuarioId): void
    {
        $periodo = $this->diary->periodoDoBimestre($anoLetivo, $bimestre);
        $resumo = $this->diary->resumoDiario($turmaId, $materiaId, $professorId, $periodo['inicio'], $periodo['fim']);
        if ((int) $resumo['chamadas_pendentes'] > 0) {
            throw new RuntimeException('Não é possível fechar: há ' . $resumo['chamadas_pendentes'] . ' chamada(s) pendente(s) no período.');
        }
        $this->diary->fechar($turmaId, $materiaId, $professorId, $anoLetivo, $bimestre, $usuarioId);
    }

    public function getFechamento(int $turmaId, int $materiaId, int $professorId, int $anoLetivo, int $bimestre): ?array
    {
        return $this->diary->getFechamento($turmaId, $materiaId, $professorId, $anoLetivo, $bimestre);
    }

    public function reabrir(int $fechamentoId, int $usuarioId): void
    {
        $fechamento = $this->diary->getFechamentoById($fechamentoId);
        if (!$fechamento) {
            throw new RuntimeException('Fechamento não encontrado.');
        }
        if ((string) $fechamento['status'] !== 'fechado') {
            throw new RuntimeException('Este diário já está aberto.');
        }
        $this->diary->reabrir($fechamentoId, $usuarioId);
    }

    /**
     * Diários fechados, para a coordenação visualizar ou reabrir.
     *
     * @return list<array<string,mixed>>
     */
    public function fechamentosFechados(int $professorId = 0, int $turmaId = 0): array
    {
        return $this->diary->fechamentosFechados($professorId, $turmaId);
    }

    /**
     * Dados de um bimestre fechado para visualização somente leitura.
     *
     * @return array{fechamento:array<string,mixed>,info:array<string,mixed>,periodo:array{inicio:string,fim:string}}|null
     */
    public function detalheFechamento(int $fechamentoId): ?array
    {
        $row = $this->diary->getFechamentoById($fechamentoId);
        if (!$row || (string) $row['status'] !== 'fechado') {
            return null;
        }
        $turmaId = (int) $row['turma_id'];
        $materiaId = (int) $row['materia_id'];
        $professorId = (int) $row['professor_id'];
        $info = $this->diary->infoDiario($turmaId, $materiaId, $professorId);
        if (!$info) {
            $info = [
                'turma_id' => $turmaId,
                'turma_nome' => (string) ($row['turma_nome'] ?? ''),
                'materia_id' => $materiaId,
                'materia_nome' => (string) ($row['materia_nome'] ?? ''),
                'professor_id' => $professorId,
                'professor_nome' => (string) ($row['professor_nome'] ?? ''),
                'ano_letivo' => (int) $row['ano_letivo'],
            ];
        }
        return [
            'fechamento' => $row,
            'info' => $info,
            'periodo' => $this->diary->periodoDoBimestre((int) $row['ano_letivo'], (int) $row['bimestre']),
        ];
    }
}
