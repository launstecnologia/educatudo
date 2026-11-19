<?php

namespace App\Modulos\EducaSync\Models;

use App\Core\Database;

/**
 * EducaSync — alocação (demanda x slot) dentro de uma versão.
 */
class Alocacao
{
    private const LOTE = 200;

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param list<array{demanda_id:int, slot_id:int, professor_id:?int, sala_id:?int}> $alocacoes
     */
    public function inserirLote(int $versaoId, array $alocacoes): int
    {
        $total = 0;
        foreach (array_chunk($alocacoes, self::LOTE) as $lote) {
            $valores = [];
            $params = [];
            foreach (array_values($lote) as $i => $a) {
                $valores[] = "(:versao_id_{$i}, :demanda_id_{$i}, :slot_id_{$i}, :professor_id_{$i}, :sala_id_{$i})";
                $params["versao_id_{$i}"] = (int) $versaoId;
                $params["demanda_id_{$i}"] = (int) $a['demanda_id'];
                $params["slot_id_{$i}"] = (int) $a['slot_id'];
                $params["professor_id_{$i}"] = !empty($a['professor_id']) ? (int) $a['professor_id'] : null;
                $params["sala_id_{$i}"] = !empty($a['sala_id']) ? (int) $a['sala_id'] : null;
            }
            if ($valores === []) {
                continue;
            }
            $this->db->query(
                'INSERT INTO educasync_alocacoes (versao_id, demanda_id, slot_id, professor_id, sala_id) VALUES '
                . implode(', ', $valores),
                $params
            );
            $total += count($valores);
        }
        return $total;
    }

    /**
     * Pares demanda x slot de uma versão com o dia do slot (base dos locks da reotimização).
     *
     * @return list<array{demanda_id:int|string, slot_id:int|string, dia_semana:int|string}>
     */
    public function listarParaLocks(int $versaoId): array
    {
        return $this->db->fetchAll(
            'SELECT a.demanda_id, a.slot_id, s.dia_semana
             FROM educasync_alocacoes a
             INNER JOIN educasync_slots s ON s.id = a.slot_id
             WHERE a.versao_id = :versao_id
             ORDER BY a.id ASC',
            ['versao_id' => (int) $versaoId]
        ) ?: [];
    }

    /**
     * Alocações com dados de exibição e de publicação (turma, matéria, dia, horário).
     *
     * @return list<array<string,mixed>>
     */
    public function listarPorVersao(int $versaoId): array
    {
        $joinSala = $this->db->tableExists('school_locations')
            ? 'LEFT JOIN school_locations l ON l.id = a.sala_id'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS nome) l ON 1 = 0';

        return $this->db->fetchAll(
            'SELECT a.id, a.demanda_id, a.slot_id, a.sala_id,
                    COALESCE(a.professor_id, d.professor_id) AS professor_id,
                    d.turma_id, d.materia_id,
                    s.dia_semana, s.ordem, s.horario_de, s.horario_ate,
                    t.nome AS turma_nome, m.nome AS materia_nome, p.nome AS professor_nome, l.nome AS sala_nome
             FROM educasync_alocacoes a
             INNER JOIN educasync_demandas d ON d.id = a.demanda_id
             INNER JOIN educasync_slots s ON s.id = a.slot_id
             LEFT JOIN turmas t ON t.id = d.turma_id
             LEFT JOIN materias m ON m.id = d.materia_id
             LEFT JOIN professores p ON p.id = COALESCE(a.professor_id, d.professor_id)
             ' . $joinSala . '
             WHERE a.versao_id = :versao_id
             ORDER BY t.nome ASC, s.dia_semana ASC, s.ordem ASC',
            ['versao_id' => (int) $versaoId]
        ) ?: [];
    }
}
