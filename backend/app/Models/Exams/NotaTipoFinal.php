<?php
/**
 * Consolidado da nota final de um Tipo de Nota (aluno × componente × período).
 * O boletim lê só este valor — não recalcula eventos.
 */

class NotaTipoFinal
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelaPronta(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'notas_tipo_finais'
                 LIMIT 1"
            );
            $ok = !empty($row);
        } catch (Exception $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buscar(int $tipoId, int $alunoId, int $materiaId, int $turmaId, int $anoLetivo, int $periodo): ?array
    {
        if (!$this->tabelaPronta() || $tipoId <= 0 || $alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT *
             FROM notas_tipo_finais
             WHERE tipo_avaliacao_id = :tipo
               AND aluno_id = :aluno
               AND materia_id = :materia
               AND turma_id = :turma
               AND ano_letivo = :ano
               AND periodo = :periodo
             LIMIT 1",
            [
                'tipo' => $tipoId,
                'aluno' => $alunoId,
                'materia' => $materiaId,
                'turma' => $turmaId,
                'ano' => $anoLetivo,
                'periodo' => $periodo,
            ]
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarDoAluno(int $tipoId, int $alunoId, int $turmaId, int $anoLetivo, int $periodo): array
    {
        if (!$this->tabelaPronta() || $tipoId <= 0 || $alunoId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT *
             FROM notas_tipo_finais
             WHERE tipo_avaliacao_id = :tipo
               AND aluno_id = :aluno
               AND turma_id = :turma
               AND ano_letivo = :ano
               AND periodo = :periodo",
            [
                'tipo' => $tipoId,
                'aluno' => $alunoId,
                'turma' => $turmaId,
                'ano' => $anoLetivo,
                'periodo' => $periodo,
            ]
        ) ?: [];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function upsert(array $data): void
    {
        if (!$this->tabelaPronta()) {
            return;
        }
        $this->db->query(
            "INSERT INTO notas_tipo_finais
                (tipo_avaliacao_id, aluno_id, materia_id, turma_id, ano_letivo, periodo,
                 nota_final, acertos_soma, questoes_soma, eventos_qtd, calculado_em)
             VALUES
                (:tipo, :aluno, :materia, :turma, :ano, :periodo,
                 :nota, :acertos, :questoes, :eventos, NOW())
             ON DUPLICATE KEY UPDATE
                nota_final = VALUES(nota_final),
                acertos_soma = VALUES(acertos_soma),
                questoes_soma = VALUES(questoes_soma),
                eventos_qtd = VALUES(eventos_qtd),
                calculado_em = NOW()",
            [
                'tipo' => (int) ($data['tipo_avaliacao_id'] ?? 0),
                'aluno' => (int) ($data['aluno_id'] ?? 0),
                'materia' => (int) ($data['materia_id'] ?? 0),
                'turma' => (int) ($data['turma_id'] ?? 0),
                'ano' => (int) ($data['ano_letivo'] ?? 0),
                'periodo' => (int) ($data['periodo'] ?? 0),
                'nota' => round((float) ($data['nota_final'] ?? 0), 2),
                'acertos' => (int) ($data['acertos_soma'] ?? 0),
                'questoes' => (int) ($data['questoes_soma'] ?? 0),
                'eventos' => (int) ($data['eventos_qtd'] ?? 0),
            ]
        );
    }
}
