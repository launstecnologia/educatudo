<?php

namespace App\Modulos\EducaSync\Services;

use App\Core\Database;
use App\Modulos\EducaSync\Models\Alocacao;
use App\Modulos\EducaSync\Models\Cenario;
use App\Modulos\EducaSync\Models\Demanda;
use App\Modulos\EducaSync\Models\Slot;

/**
 * EducaSync — monta o payload que o solver recebe a partir de um cenário.
 *
 * Contrato (resumo): cenario_id, slots, demandas, disponibilidades,
 * restricoes_hard, restricoes_soft, pesos, locks, limites.
 */
class EducaSyncContextoService
{
    private const PESOS_PADRAO = ['janelas' => 300];
    private const MAX_TIME_MIN = 5;
    private const MAX_TIME_MAX = 600;

    private $db;
    private Cenario $cenarios;
    private Slot $slots;
    private Demanda $demandas;
    private Alocacao $alocacoes;

    public function __construct(?Cenario $cenarios = null, ?Slot $slots = null, ?Demanda $demandas = null, ?Alocacao $alocacoes = null)
    {
        $this->db = Database::getInstance();
        $this->alocacoes = $alocacoes ?? new Alocacao();
        $this->cenarios = $cenarios ?? new Cenario();
        $this->slots = $slots ?? new Slot();
        $this->demandas = $demandas ?? new Demanda();
    }

    /**
     * Problemas que impedem a geração. Lista vazia = cenário pronto.
     *
     * @return list<string>
     */
    public function validar(int $cenarioId): array
    {
        $slots = $this->slots->listarPorCenario($cenarioId);
        $demandas = $this->demandas->listarPorCenario($cenarioId);

        $erros = [];
        if ($slots === []) {
            $erros[] = 'Cadastre ao menos um horário (slot) no cenário.';
        }
        if ($demandas === []) {
            $erros[] = 'Cadastre ao menos uma demanda (turma e matéria) no cenário.';
        }
        if ($erros !== []) {
            return $erros;
        }

        $semProfessor = 0;
        $aulasPorTurma = [];
        foreach ($demandas as $d) {
            if (empty($d['professor_id'])) {
                $semProfessor++;
            }
            $turmaId = (int) $d['turma_id'];
            $aulasPorTurma[$turmaId] = ($aulasPorTurma[$turmaId] ?? 0) + (int) $d['aulas_semana'];
        }
        if ($semProfessor > 0) {
            $erros[] = $semProfessor . ' demanda(s) sem professor. Defina o professor de cada demanda antes de gerar.';
        }

        $totalSlots = count($slots);
        foreach ($demandas as $d) {
            $turmaId = (int) $d['turma_id'];
            if (($aulasPorTurma[$turmaId] ?? 0) > $totalSlots) {
                $erros[] = 'A turma ' . (string) ($d['turma_nome'] ?? $turmaId) . ' pede '
                    . $aulasPorTurma[$turmaId] . ' aulas por semana, mas o cenário tem só ' . $totalSlots . ' horários.';
                unset($aulasPorTurma[$turmaId]);
            }
        }

        return $erros;
    }

    /**
     * @param list<array{demanda_id:int, slot_id:int}> $locks aulas fixadas (demanda ocupa o slot);
     *        são filtradas para demandas/slots que ainda existem no cenário
     * @return array<string,mixed>
     */
    public function montarPayload(int $cenarioId, array $locks = []): array
    {
        $cenario = $this->cenarios->findById($cenarioId);
        if (!$cenario) {
            throw new \RuntimeException('Cenário do EducaSync não encontrado.');
        }

        $pesos = $this->pesosDoCenario($cenario);
        [$hard, $soft] = $this->restricoes($cenarioId, $pesos);

        return [
            'cenario_id' => (int) $cenario['id'],
            'slots' => $this->slotsDoSolver($cenarioId),
            'demandas' => $this->demandasDoSolver($cenarioId),
            'disponibilidades' => $this->disponibilidadesDoSolver($cenarioId),
            'restricoes_hard' => $hard,
            'restricoes_soft' => $soft,
            'pesos' => $pesos,
            'locks' => $this->locksValidos($cenarioId, $locks),
            'limites' => ['max_time_s' => $this->tempoMaximo($cenario)],
        ];
    }

    /**
     * Locks de uma versão existente: alocações cujo slot cai em $fixarDias (ou todas).
     * Só devolve pares que ainda são válidos no cenário atual.
     *
     * @param list<int> $fixarDias dias 1–7; ignorado se $fixarTodas
     * @return list<array{demanda_id:int, slot_id:int}>
     */
    public function locksDaVersao(int $cenarioId, int $versaoId, array $fixarDias, bool $fixarTodas): array
    {
        $dias = array_flip(array_map('intval', $fixarDias));
        $locks = [];
        foreach ($this->alocacoes->listarParaLocks($versaoId) as $a) {
            if ($fixarTodas || isset($dias[(int) $a['dia_semana']])) {
                $locks[] = ['demanda_id' => (int) $a['demanda_id'], 'slot_id' => (int) $a['slot_id']];
            }
        }
        return $this->locksValidos($cenarioId, $locks);
    }

    /**
     * Descarta locks de demanda/slot que não existem mais no cenário, duplicados e o que passar
     * de `qtd` por demanda (demanda editada depois da versão base).
     *
     * @param list<array<string,mixed>> $locks
     * @return list<array{demanda_id:int, slot_id:int}>
     */
    private function locksValidos(int $cenarioId, array $locks): array
    {
        if ($locks === []) {
            return [];
        }
        $qtdPorDemanda = [];
        foreach ($this->demandas->listarPorCenario($cenarioId) as $d) {
            $qtdPorDemanda[(int) $d['id']] = (int) $d['aulas_semana'];
        }
        $slotIds = [];
        foreach ($this->slots->listarPorCenario($cenarioId) as $s) {
            $slotIds[(int) $s['id']] = true;
        }

        $vistos = [];
        $porDemanda = [];
        $out = [];
        foreach ($locks as $l) {
            $demandaId = (int) ($l['demanda_id'] ?? 0);
            $slotId = (int) ($l['slot_id'] ?? 0);
            $chave = $demandaId . ':' . $slotId;
            if (!isset($qtdPorDemanda[$demandaId]) || !isset($slotIds[$slotId]) || isset($vistos[$chave])) {
                continue;
            }
            if (($porDemanda[$demandaId] ?? 0) >= $qtdPorDemanda[$demandaId]) {
                continue;
            }
            $vistos[$chave] = true;
            $porDemanda[$demandaId] = ($porDemanda[$demandaId] ?? 0) + 1;
            $out[] = ['demanda_id' => $demandaId, 'slot_id' => $slotId];
        }
        return $out;
    }

    public function tempoMaximo(array $cenario): int
    {
        $s = (int) ($cenario['max_time_s'] ?? 60);
        return max(self::MAX_TIME_MIN, min(self::MAX_TIME_MAX, $s > 0 ? $s : 60));
    }

    /**
     * @return array<string,int|float>
     */
    public function pesosDoCenario(array $cenario): array
    {
        $decodificado = json_decode((string) ($cenario['pesos_json'] ?? ''), true);
        if (!is_array($decodificado) || $decodificado === []) {
            return self::PESOS_PADRAO;
        }
        $pesos = [];
        foreach ($decodificado as $chave => $valor) {
            if (is_numeric($valor)) {
                $pesos[(string) $chave] = $valor + 0;
            }
        }
        return $pesos !== [] ? $pesos : self::PESOS_PADRAO;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function slotsDoSolver(int $cenarioId): array
    {
        $out = [];
        foreach ($this->slots->listarPorCenario($cenarioId) as $s) {
            $out[] = [
                'id' => (int) $s['id'],
                'dia' => (int) $s['dia_semana'],
                'ordem' => (int) $s['ordem'],
                'horario_de' => substr((string) $s['horario_de'], 0, 5),
                'horario_ate' => substr((string) $s['horario_ate'], 0, 5),
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function demandasDoSolver(int $cenarioId): array
    {
        $out = [];
        foreach ($this->demandas->listarPorCenario($cenarioId) as $d) {
            $out[] = [
                'id' => (int) $d['id'],
                'turma_id' => (int) $d['turma_id'],
                'materia_id' => (int) $d['materia_id'],
                'qtd' => (int) $d['aulas_semana'],
                'professor_id' => !empty($d['professor_id']) ? (int) $d['professor_id'] : null,
                'sala_id' => !empty($d['sala_id']) ? (int) $d['sala_id'] : null,
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function disponibilidadesDoSolver(int $cenarioId): array
    {
        $linhas = $this->db->fetchAll(
            'SELECT tipo, ref_id, slot_id, livre FROM educasync_disponibilidades WHERE cenario_id = :cenario_id',
            ['cenario_id' => (int) $cenarioId]
        ) ?: [];

        // No banco: ausência de registro = livre; livre = 0 = bloqueado.
        // No solver: entidade com qualquer registro só pode usar os slots livre = true.
        // Por isso cada entidade com bloqueio vira lista completa (todos os slots do cenário).
        $bloqueados = [];
        foreach ($linhas as $l) {
            if ((int) $l['livre'] === 0) {
                $bloqueados[(string) $l['tipo']][(int) $l['ref_id']][(int) $l['slot_id']] = true;
            }
        }
        if ($bloqueados === []) {
            return [];
        }

        $slotIds = [];
        foreach ($this->slots->listarPorCenario($cenarioId) as $s) {
            $slotIds[] = (int) $s['id'];
        }

        $out = [];
        foreach ($bloqueados as $tipo => $porRef) {
            foreach ($porRef as $refId => $slotsBloqueados) {
                foreach ($slotIds as $slotId) {
                    $out[] = [
                        'tipo' => (string) $tipo,
                        'ref_id' => (int) $refId,
                        'slot_id' => $slotId,
                        'livre' => !isset($slotsBloqueados[$slotId]),
                    ];
                }
            }
        }
        return $out;
    }

    /**
     * @param array<string,int|float> $pesos
     * @return array{0:list<array<string,mixed>>, 1:list<array<string,mixed>>} [hard, soft]
     */
    private function restricoes(int $cenarioId, array $pesos): array
    {
        $linhas = $this->db->fetchAll(
            'SELECT tipo, escopo, parametros_json, hard, peso
             FROM educasync_restricoes
             WHERE cenario_id = :cenario_id AND ativa = 1
             ORDER BY id ASC',
            ['cenario_id' => (int) $cenarioId]
        ) ?: [];

        $slotsPorPosicao = [];
        $slotIds = [];
        foreach ($this->slots->listarPorCenario($cenarioId) as $s) {
            $slotsPorPosicao[(int) $s['dia_semana'] . ':' . (int) $s['ordem']] = (int) $s['id'];
            $slotIds[(int) $s['id']] = true;
        }

        $hard = [];
        $soft = [];
        $temJanelas = false;
        foreach ($linhas as $l) {
            $parametros = json_decode((string) ($l['parametros_json'] ?? ''), true);
            $item = $this->achatarRestricao(
                (string) $l['tipo'],
                (string) $l['escopo'],
                is_array($parametros) ? $parametros : [],
                $slotsPorPosicao,
                $slotIds
            );
            if ($item === null) {
                continue;
            }
            if ((int) $l['hard'] === 1) {
                $hard[] = $item;
                continue;
            }
            $item['peso'] = (int) $l['peso'];
            $soft[] = $item;
            if ($item['tipo'] === 'minimizar_janelas') {
                $temJanelas = true;
            }
        }

        if (!$temJanelas && !empty($pesos['janelas'])) {
            $soft[] = ['tipo' => 'minimizar_janelas', 'peso' => (int) $pesos['janelas']];
        }

        return [$hard, $soft];
    }

    /**
     * Formato plano que o solver espera (RestricaoHard/RestricaoSoft com campos extras):
     * `{tipo, escopo?, <parametros mesclados no mesmo nível>, peso?}`. Chaves reservadas
     * (tipo, escopo, peso) nunca são sobrescritas por parametros_json.
     *
     * - evitar_disciplina_slot → materia_id, slot_id, dia, ordem. O slot é reresolvido por
     *   dia+ordem no cenário atual (os horários podem ter sido regerados); sem slot válido,
     *   a restrição é omitida em vez de enviar referência quebrada.
     * - concentrar_disciplina → materia_id e, se houver, turma_id.
     *
     * @param array<string,mixed> $parametros
     * @param array<string,int> $slotsPorPosicao "dia:ordem" => slot_id
     * @param array<int,true> $slotIds
     * @return array<string,mixed>|null
     */
    private function achatarRestricao(string $tipo, string $escopo, array $parametros, array $slotsPorPosicao, array $slotIds): ?array
    {
        $item = ['tipo' => $tipo];
        if ($escopo !== '' && $escopo !== 'global') {
            $item['escopo'] = $escopo;
        }

        if ($tipo === 'evitar_disciplina_slot') {
            $dia = (int) ($parametros['dia'] ?? $parametros['dia_semana'] ?? 0);
            $ordem = (int) ($parametros['ordem'] ?? 0);
            $slotId = $slotsPorPosicao[$dia . ':' . $ordem] ?? 0;
            if ($slotId <= 0) {
                $candidato = (int) ($parametros['slot_id'] ?? 0);
                $slotId = isset($slotIds[$candidato]) ? $candidato : 0;
            }
            $materiaId = (int) ($parametros['materia_id'] ?? 0);
            if ($materiaId <= 0 || $slotId <= 0) {
                return null;
            }
            $parametros = ['materia_id' => $materiaId, 'slot_id' => $slotId];
            if ($dia > 0) {
                $parametros['dia'] = $dia;
            }
            if ($ordem > 0) {
                $parametros['ordem'] = $ordem;
            }
        } elseif ($tipo === 'concentrar_disciplina') {
            $materiaId = (int) ($parametros['materia_id'] ?? 0);
            if ($materiaId <= 0) {
                return null;
            }
            $turmaId = (int) ($parametros['turma_id'] ?? 0);
            $parametros = ['materia_id' => $materiaId];
            if ($turmaId > 0) {
                $parametros['turma_id'] = $turmaId;
            }
        }

        foreach ($parametros as $chave => $valor) {
            if (in_array($chave, ['tipo', 'escopo', 'peso'], true) || !is_string($chave)) {
                continue;
            }
            $item[$chave] = $valor;
        }
        return $item;
    }
}
