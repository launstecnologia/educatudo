<?php

namespace App\Modulos\EducaSync\Models;

use App\Core\Database;

/**
 * EducaSync — disponibilidade de professor/sala por slot.
 *
 * Semântica gravada no banco: ausência de registro = livre; linha com livre = 0 = bloqueado.
 * O solver usa a lógica inversa (entidade com registro = só os slots livre=true),
 * então EducaSyncContextoService converte bloqueios em lista completa antes de enviar.
 */
class Disponibilidade
{
    public const TIPOS = ['professor', 'sala'];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorCenario(int $cenarioId): array
    {
        return $this->db->fetchAll(
            'SELECT id, cenario_id, tipo, ref_id, dia_semana, slot_id, livre
             FROM educasync_disponibilidades
             WHERE cenario_id = :cenario_id
             ORDER BY tipo ASC, ref_id ASC, dia_semana ASC, slot_id ASC',
            ['cenario_id' => (int) $cenarioId]
        ) ?: [];
    }

    /**
     * Slots bloqueados (livre = 0) do professor, com dia/ordem/horário para exibição.
     *
     * @return list<array<string,mixed>>
     */
    public function listarBloqueiosProfessor(int $cenarioId, int $professorId): array
    {
        return $this->db->fetchAll(
            "SELECT d.slot_id, s.dia_semana, s.ordem, s.horario_de, s.horario_ate
             FROM educasync_disponibilidades d
             INNER JOIN educasync_slots s ON s.id = d.slot_id AND s.cenario_id = d.cenario_id
             WHERE d.cenario_id = :cenario_id AND d.tipo = 'professor' AND d.ref_id = :ref_id AND d.livre = 0
             ORDER BY s.dia_semana ASC, s.ordem ASC",
            ['cenario_id' => (int) $cenarioId, 'ref_id' => (int) $professorId]
        ) ?: [];
    }

    /**
     * Professores com ao menos um bloqueio no cenário e o total de slots bloqueados de cada um.
     *
     * @return list<array<string,mixed>>
     */
    public function resumoBloqueiosProfessores(int $cenarioId): array
    {
        return $this->db->fetchAll(
            "SELECT d.ref_id AS professor_id, p.nome AS professor_nome, COUNT(*) AS total_bloqueados
             FROM educasync_disponibilidades d
             LEFT JOIN professores p ON p.id = d.ref_id
             WHERE d.cenario_id = :cenario_id AND d.tipo = 'professor' AND d.livre = 0
             GROUP BY d.ref_id, p.nome
             ORDER BY p.nome ASC",
            ['cenario_id' => (int) $cenarioId]
        ) ?: [];
    }

    /**
     * Substitui os bloqueios da entidade: apaga os registros dela no cenário e reinsere livre = 0
     * para os slots informados. Slots que não pertencem ao cenário são ignorados.
     *
     * @param list<int|string> $slotIdsBloqueados
     * @return int quantidade de slots bloqueados gravados
     */
    public function salvarBloqueios(int $cenarioId, string $tipo, int $refId, array $slotIdsBloqueados): int
    {
        $tipo = $this->tipoValido($tipo);
        $slots = $this->slotsDoCenario($cenarioId, $slotIdsBloqueados);

        $this->db->beginTransaction();
        try {
            $this->limpar($cenarioId, $tipo, $refId);
            $total = $this->inserirBloqueios($cenarioId, $tipo, $refId, $slots);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
        return $total;
    }

    /**
     * Soma bloqueios aos já existentes (idempotente). Usado pelo assistente ao confirmar uma proposta.
     * Não abre transação própria: quem chama controla.
     *
     * @param list<int|string> $slotIdsBloqueados
     */
    public function adicionarBloqueios(int $cenarioId, string $tipo, int $refId, array $slotIdsBloqueados): int
    {
        $tipo = $this->tipoValido($tipo);
        return $this->inserirBloqueios($cenarioId, $tipo, $refId, $this->slotsDoCenario($cenarioId, $slotIdsBloqueados));
    }

    public function limpar(int $cenarioId, string $tipo, int $refId): int
    {
        return (int) $this->db->delete(
            'DELETE FROM educasync_disponibilidades WHERE cenario_id = :cenario_id AND tipo = :tipo AND ref_id = :ref_id',
            ['cenario_id' => (int) $cenarioId, 'tipo' => $this->tipoValido($tipo), 'ref_id' => (int) $refId]
        );
    }

    /**
     * @param list<array{id:int, dia_semana:int}> $slots
     */
    private function inserirBloqueios(int $cenarioId, string $tipo, int $refId, array $slots): int
    {
        $total = 0;
        foreach ($slots as $slot) {
            $this->db->query(
                'INSERT INTO educasync_disponibilidades (cenario_id, tipo, ref_id, dia_semana, slot_id, livre)
                 VALUES (:cenario_id, :tipo, :ref_id, :dia_semana, :slot_id, 0)
                 ON DUPLICATE KEY UPDATE livre = 0, dia_semana = VALUES(dia_semana)',
                [
                    'cenario_id' => (int) $cenarioId,
                    'tipo' => $tipo,
                    'ref_id' => (int) $refId,
                    'dia_semana' => (int) $slot['dia_semana'],
                    'slot_id' => (int) $slot['id'],
                ]
            );
            $total++;
        }
        return $total;
    }

    /**
     * Filtra os ids informados para os que existem no cenário (isolamento por cenario_id).
     *
     * @param list<int|string> $slotIds
     * @return list<array{id:int, dia_semana:int}>
     */
    private function slotsDoCenario(int $cenarioId, array $slotIds): array
    {
        $ids = [];
        foreach ($slotIds as $slotId) {
            $id = (int) $slotId;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $params = ['cenario_id' => (int) $cenarioId];
        $marcadores = [];
        foreach (array_values($ids) as $i => $id) {
            $marcadores[] = ':slot_' . $i;
            $params['slot_' . $i] = $id;
        }

        $linhas = $this->db->fetchAll(
            'SELECT id, dia_semana FROM educasync_slots
             WHERE cenario_id = :cenario_id AND id IN (' . implode(', ', $marcadores) . ')',
            $params
        ) ?: [];

        $out = [];
        foreach ($linhas as $l) {
            $out[] = ['id' => (int) $l['id'], 'dia_semana' => (int) $l['dia_semana']];
        }
        return $out;
    }

    private function tipoValido(string $tipo): string
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de disponibilidade inválido.');
        }
        return $tipo;
    }
}
