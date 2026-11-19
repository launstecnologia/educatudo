<?php

namespace App\Modulos\EducaSync\Models;

use App\Core\Database;

/**
 * EducaSync — slot (dia da semana + ordem + faixa de horário).
 */
class Slot
{
    public const DIAS = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Rótulo curto para exibição: "Seg · aula 2 (07:50)".
     *
     * @param array<string,mixed> $slot dia_semana, ordem, horario_de
     */
    public static function rotulo(array $slot): string
    {
        $dia = self::DIAS[(int) ($slot['dia_semana'] ?? 0)] ?? '?';
        $ordem = (int) ($slot['ordem'] ?? 0);
        $hora = substr((string) ($slot['horario_de'] ?? ''), 0, 5);

        return mb_substr($dia, 0, 3) . ' · aula ' . $ordem . ($hora !== '' ? ' (' . $hora . ')' : '');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorCenario(int $cenarioId): array
    {
        return $this->db->fetchAll(
            'SELECT id, cenario_id, dia_semana, ordem, horario_de, horario_ate
             FROM educasync_slots
             WHERE cenario_id = :cenario_id
             ORDER BY dia_semana ASC, ordem ASC',
            ['cenario_id' => (int) $cenarioId]
        ) ?: [];
    }

    public function contar(int $cenarioId): int
    {
        $linha = $this->db->fetch(
            'SELECT COUNT(*) AS total FROM educasync_slots WHERE cenario_id = :cenario_id',
            ['cenario_id' => (int) $cenarioId]
        );
        return (int) ($linha['total'] ?? 0);
    }

    public function salvar(int $cenarioId, int $dia, int $ordem, string $de, string $ate): void
    {
        $this->db->query(
            'INSERT INTO educasync_slots (cenario_id, dia_semana, ordem, horario_de, horario_ate)
             VALUES (:cenario_id, :dia_semana, :ordem, :horario_de, :horario_ate)
             ON DUPLICATE KEY UPDATE horario_de = VALUES(horario_de), horario_ate = VALUES(horario_ate)',
            [
                'cenario_id' => (int) $cenarioId,
                'dia_semana' => (int) $dia,
                'ordem' => (int) $ordem,
                'horario_de' => $de,
                'horario_ate' => $ate,
            ]
        );
    }

    public function excluir(int $cenarioId, int $slotId): int
    {
        return (int) $this->db->delete(
            'DELETE FROM educasync_slots WHERE id = :id AND cenario_id = :cenario_id',
            ['id' => (int) $slotId, 'cenario_id' => (int) $cenarioId]
        );
    }
}
