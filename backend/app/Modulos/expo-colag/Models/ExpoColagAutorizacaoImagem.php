<?php
/**
 * Autorização de uso de imagem do aluno (Expo Colag).
 */

class ExpoColagAutorizacaoImagem
{
    private $db;

    public const STATUS = ['Autorizado_total', 'Autorizado_interno', 'Nao_autorizado'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByAluno(int $alunoId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM expo_colag_alunos_autorizacao_imagem WHERE aluno_id = :aluno_id',
            ['aluno_id' => $alunoId]
        );
        return $row ?: null;
    }

    public function listarResumo(): array
    {
        return $this->db->fetchAll(
            "SELECT a.id AS aluno_id, a.nome AS aluno_nome, t.nome AS turma_nome,
                    COALESCE(aut.status, 'Nao_autorizado') AS status,
                    aut.registrado_em, aut.revogado_em
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             LEFT JOIN expo_colag_alunos_autorizacao_imagem aut ON aut.aluno_id = a.id
             WHERE a.ativo = 1
             ORDER BY
                CASE COALESCE(aut.status, 'Nao_autorizado')
                    WHEN 'Nao_autorizado' THEN 0
                    WHEN 'Autorizado_interno' THEN 1
                    ELSE 2
                END,
                a.nome ASC
             LIMIT 500"
        ) ?: [];
    }

    public function contarPorStatus(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT COALESCE(aut.status, 'Nao_autorizado') AS status, COUNT(*) AS total
             FROM alunos a
             LEFT JOIN expo_colag_alunos_autorizacao_imagem aut ON aut.aluno_id = a.id
             WHERE a.ativo = 1
             GROUP BY COALESCE(aut.status, 'Nao_autorizado')"
        ) ?: [];
        $out = [
            'Autorizado_total' => 0,
            'Autorizado_interno' => 0,
            'Nao_autorizado' => 0,
        ];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['total'];
        }
        return $out;
    }

    /**
     * Upsert do status de autorização.
     *
     * @return array{success:bool,error?:string}
     */
    public function registrar(int $alunoId, string $status, ?int $registradoPor = null, ?int $responsavelId = null, ?string $observacao = null): array
    {
        if (!in_array($status, self::STATUS, true)) {
            return ['success' => false, 'error' => 'Status de autorização inválido.'];
        }

        $aluno = $this->db->fetch('SELECT id FROM alunos WHERE id = :id', ['id' => $alunoId]);
        if (!$aluno) {
            return ['success' => false, 'error' => 'Aluno não encontrado.'];
        }

        $atual = $this->findByAluno($alunoId);
        $revogadoEm = $status === 'Nao_autorizado' ? date('Y-m-d H:i:s') : null;

        if ($atual) {
            $this->db->query(
                'UPDATE expo_colag_alunos_autorizacao_imagem
                 SET status = :status,
                     autorizado_por_responsavel_id = :resp,
                     registrado_por = :reg_por,
                     registrado_em = NOW(),
                     revogado_em = :revogado,
                     observacao = :obs
                 WHERE aluno_id = :aluno_id',
                [
                    'status' => $status,
                    'resp' => $responsavelId,
                    'reg_por' => $registradoPor,
                    'revogado' => $revogadoEm,
                    'obs' => $observacao,
                    'aluno_id' => $alunoId,
                ]
            );
        } else {
            $this->db->insert(
                'INSERT INTO expo_colag_alunos_autorizacao_imagem
                    (aluno_id, status, autorizado_por_responsavel_id, registrado_por, revogado_em, observacao)
                 VALUES
                    (:aluno_id, :status, :resp, :reg_por, :revogado, :obs)',
                [
                    'aluno_id' => $alunoId,
                    'status' => $status,
                    'resp' => $responsavelId,
                    'reg_por' => $registradoPor,
                    'revogado' => $revogadoEm,
                    'obs' => $observacao,
                ]
            );
        }

        return ['success' => true];
    }

    public function alunoPodePublicarExterno(int $alunoId): bool
    {
        $row = $this->findByAluno($alunoId);
        return ($row['status'] ?? '') === 'Autorizado_total';
    }

    public function alunoPodePublicarInterno(int $alunoId): bool
    {
        $row = $this->findByAluno($alunoId);
        $status = $row['status'] ?? 'Nao_autorizado';
        return in_array($status, ['Autorizado_total', 'Autorizado_interno'], true);
    }
}
