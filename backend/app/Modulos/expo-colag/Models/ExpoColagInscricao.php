<?php
/**
 * Inscrição de aluno em projeto da Expo Colag.
 */

class ExpoColagInscricao
{
    private $db;

    public const STATUS_ATIVOS = ['Aguardando', 'Aprovada', 'Lista_espera'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM expo_colag_inscricoes WHERE id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function findByProjetoAluno(int $projetoId, int $alunoId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM expo_colag_inscricoes
             WHERE projeto_id = :projeto_id AND aluno_id = :aluno_id',
            ['projeto_id' => $projetoId, 'aluno_id' => $alunoId]
        );
        return $row ?: null;
    }

    public function listarPorProjeto(int $projetoId): array
    {
        return $this->db->fetchAll(
            'SELECT i.*, a.nome AS aluno_nome
             FROM expo_colag_inscricoes i
             LEFT JOIN alunos a ON a.id = i.aluno_id
             WHERE i.projeto_id = :projeto_id
             ORDER BY
                FIELD(i.status, \'Aguardando\', \'Lista_espera\', \'Aprovada\', \'Recusada\', \'Cancelada_aluno\', \'Removido_professor\'),
                i.inscrito_em ASC',
            ['projeto_id' => $projetoId]
        ) ?: [];
    }

    public function listarPendentesPorProfessor(int $professorId): array
    {
        return $this->db->fetchAll(
            "SELECT i.*, a.nome AS aluno_nome, p.titulo AS projeto_titulo, p.id AS projeto_id
             FROM expo_colag_inscricoes i
             INNER JOIN expo_colag_projetos p ON p.id = i.projeto_id
             LEFT JOIN alunos a ON a.id = i.aluno_id
             WHERE p.professor_id = :professor_id AND i.status = 'Aguardando'
             ORDER BY i.inscrito_em ASC",
            ['professor_id' => $professorId]
        ) ?: [];
    }

    public function listarPorAluno(int $alunoId): array
    {
        return $this->db->fetchAll(
            'SELECT i.*, p.titulo AS projeto_titulo, p.status AS projeto_status,
                    p.area, p.inscricoes_fim, p.professor_id, pr.nome AS professor_nome
             FROM expo_colag_inscricoes i
             INNER JOIN expo_colag_projetos p ON p.id = i.projeto_id
             LEFT JOIN professores pr ON pr.id = p.professor_id
             WHERE i.aluno_id = :aluno_id
             ORDER BY i.inscrito_em DESC',
            ['aluno_id' => $alunoId]
        ) ?: [];
    }

    public function contarAprovadas(int $projetoId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM expo_colag_inscricoes
             WHERE projeto_id = :projeto_id AND status = 'Aprovada'",
            ['projeto_id' => $projetoId]
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Conta aprovadas (chamar após lock do projeto na mesma transação).
     * Também trava as linhas de inscrição aprovadas para serializar concorrência.
     */
    public function contarAprovadasForUpdate(int $projetoId): int
    {
        $this->db->fetchAll(
            "SELECT id FROM expo_colag_inscricoes
             WHERE projeto_id = :projeto_id AND status = 'Aprovada'
             FOR UPDATE",
            ['projeto_id' => $projetoId]
        );
        return $this->contarAprovadas($projetoId);
    }

    public function contarPendentesProfessor(int $professorId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM expo_colag_inscricoes i
             INNER JOIN expo_colag_projetos p ON p.id = i.projeto_id
             WHERE p.professor_id = :professor_id AND i.status = 'Aguardando'",
            ['professor_id' => $professorId]
        );
        return (int) ($row['total'] ?? 0);
    }

    public function contarAtivasPorProjeto(int $projetoId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM expo_colag_inscricoes
             WHERE projeto_id = :projeto_id
               AND status IN ('Aguardando','Aprovada','Lista_espera')",
            ['projeto_id' => $projetoId]
        );
        return (int) ($row['total'] ?? 0);
    }

    /** Projetos em que o aluno está ativo (conta para limite simultâneo). */
    public function contarAtivasAluno(int $alunoId, ?int $excetoProjetoId = null): int
    {
        $sql = "SELECT COUNT(*) AS total FROM expo_colag_inscricoes
                WHERE aluno_id = :aluno_id
                  AND status IN ('Aguardando','Aprovada','Lista_espera')";
        $params = ['aluno_id' => $alunoId];
        if ($excetoProjetoId !== null && $excetoProjetoId > 0) {
            $sql .= ' AND projeto_id <> :exceto';
            $params['exceto'] = $excetoProjetoId;
        }
        $row = $this->db->fetch($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Trava linhas ativas do aluno (chamar dentro de transação) para serializar o teto de projetos.
     */
    public function travarAtivasAluno(int $alunoId): void
    {
        $this->db->fetchAll(
            "SELECT id FROM expo_colag_inscricoes
             WHERE aluno_id = :aluno_id
               AND status IN ('Aguardando','Aprovada','Lista_espera')
             FOR UPDATE",
            ['aluno_id' => $alunoId]
        );
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO expo_colag_inscricoes
                (projeto_id, aluno_id, papel_id, justificativa, status)
             VALUES
                (:projeto_id, :aluno_id, :papel_id, :justificativa, :status)',
            [
                'projeto_id' => (int) $data['projeto_id'],
                'aluno_id' => (int) $data['aluno_id'],
                'papel_id' => $data['papel_id'] ?? null,
                'justificativa' => $data['justificativa'] ?? null,
                'status' => $data['status'] ?? 'Aguardando',
            ]
        );
    }

    /**
     * @param list<string>|null $statusAtualPermitidos Se informado, UPDATE só aplica se status atual ∈ lista.
     * @return bool true se alguma linha foi alterada
     */
    public function atualizarStatus(
        int $id,
        string $status,
        ?int $decididoPor = null,
        ?string $motivoRecusa = null,
        ?array $statusAtualPermitidos = null,
        bool $registrarDecisao = true
    ): bool {
        $params = [
            'id' => $id,
            'status' => $status,
            'motivo' => $motivoRecusa,
        ];
        if ($registrarDecisao) {
            $params['decidido_por'] = $decididoPor;
            $sql = 'UPDATE expo_colag_inscricoes
                    SET status = :status,
                        motivo_recusa = :motivo,
                        decidido_em = NOW(),
                        decidido_por = :decidido_por
                    WHERE id = :id';
        } else {
            $sql = 'UPDATE expo_colag_inscricoes
                    SET status = :status,
                        motivo_recusa = :motivo
                    WHERE id = :id';
        }
        if ($statusAtualPermitidos !== null && $statusAtualPermitidos !== []) {
            $placeholders = [];
            foreach (array_values($statusAtualPermitidos) as $i => $st) {
                $key = 'st_' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $st;
            }
            $sql .= ' AND status IN (' . implode(', ', $placeholders) . ')';
        }
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /** Próximo da lista de espera (FIFO). */
    public function proximoListaEspera(int $projetoId): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM expo_colag_inscricoes
             WHERE projeto_id = :projeto_id AND status = 'Lista_espera'
             ORDER BY inscrito_em ASC
             LIMIT 1
             FOR UPDATE",
            ['projeto_id' => $projetoId]
        );
        return $row ?: null;
    }

    /** IDs de projetos com inscrição ativa do aluno. */
    public function projetosAtivosIds(int $alunoId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT projeto_id FROM expo_colag_inscricoes
             WHERE aluno_id = :aluno_id
               AND status IN ('Aguardando','Aprovada','Lista_espera')",
            ['aluno_id' => $alunoId]
        ) ?: [];
        return array_map(static fn($r) => (int) $r['projeto_id'], $rows);
    }
}
