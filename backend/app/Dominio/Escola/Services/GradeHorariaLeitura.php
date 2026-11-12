<?php

namespace App\Dominio\Escola\Services;

use App\Core\Database;

/**
 * Leitura da grade horária no banco do tenant.
 */
class GradeHorariaLeitura
{
    /** @var Database */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array{tipo_ensino?:string,serie?:string,turma_id?:int,periodo?:string,professor_id?:int,materia_id?:int,dia_semana?:int} $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros): array
    {
        $selectSala = ', NULL AS sala_nome';
        $joinSala = '';
        if ($this->colunaExiste('turmas', 'sala_padrao_id') && $this->tabelaExiste('school_locations')) {
            $selectSala = ', sl.nome AS sala_nome';
            $joinSala = ' LEFT JOIN school_locations sl ON sl.id = t.sala_padrao_id';
        }

        $where = [];
        $params = [];

        if ((int) ($filtros['turma_id'] ?? 0) > 0) {
            $where[] = 'g.turma_id = :turma_id';
            $params['turma_id'] = (int) $filtros['turma_id'];
        }
        if (($filtros['periodo'] ?? '') !== '') {
            $where[] = 'g.periodo = :periodo';
            $params['periodo'] = (string) $filtros['periodo'];
        }
        if ((int) ($filtros['professor_id'] ?? 0) > 0) {
            $where[] = 'g.professor_id = :professor_id';
            $params['professor_id'] = (int) $filtros['professor_id'];
        }
        if ((int) ($filtros['materia_id'] ?? 0) > 0) {
            $where[] = 'g.materia_id = :materia_id';
            $params['materia_id'] = (int) $filtros['materia_id'];
        }
        if ((int) ($filtros['dia_semana'] ?? 0) > 0) {
            $where[] = 'g.dia_semana = :dia_semana';
            $params['dia_semana'] = (int) $filtros['dia_semana'];
        }
        if (($filtros['tipo_ensino'] ?? '') !== '') {
            $where[] = 't.tipo_ensino = :tipo_ensino';
            $params['tipo_ensino'] = (string) $filtros['tipo_ensino'];
        }
        if (($filtros['serie'] ?? '') !== '') {
            $where[] = 't.serie = :serie';
            $params['serie'] = (string) $filtros['serie'];
        }

        $sql = "SELECT g.*, t.nome AS turma_nome, t.serie AS turma_serie, t.tipo_ensino,
                       p.nome AS professor_nome, m.nome AS materia_nome
                       {$selectSala}
                FROM grade_horaria g
                JOIN turmas t ON g.turma_id = t.id
                JOIN professores p ON g.professor_id = p.id
                JOIN materias m ON g.materia_id = m.id
                {$joinSala}";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY g.horario_de ASC, g.horario_ate ASC, g.dia_semana ASC, t.nome ASC';

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        if ($tabela !== 'turmas' || $coluna !== 'sala_padrao_id') {
            return false;
        }
        try {
            return $this->db->fetch('SHOW COLUMNS FROM turmas LIKE :c', ['c' => $coluna]) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function tabelaExiste(string $tabela): bool
    {
        if ($tabela !== 'school_locations') {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tabela LIMIT 1',
                ['tabela' => $tabela]
            );
            return !empty($row);
        } catch (\Exception $e) {
            return false;
        }
    }
}
