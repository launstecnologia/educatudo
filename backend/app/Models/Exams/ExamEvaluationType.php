<?php
/**
 * EducaTudo - Tipos de Avaliação de Provas Online
 */

class ExamEvaluationType
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT *
             FROM provas_tipos_avaliacao
             WHERE deleted_at IS NULL
             ORDER BY ativo DESC, ordem ASC, nome ASC"
        ) ?: [];
    }

    public function getAllActive(): array
    {
        return $this->db->fetchAll(
            "SELECT *
             FROM provas_tipos_avaliacao
             WHERE deleted_at IS NULL AND ativo = 1
             ORDER BY ordem ASC, nome ASC"
        ) ?: [];
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT *
             FROM provas_tipos_avaliacao
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function existsByName(string $nome, ?int $ignoreId = null): bool
    {
        $sql = "SELECT id FROM provas_tipos_avaliacao WHERE deleted_at IS NULL AND LOWER(nome) = LOWER(:nome)";
        $params = ['nome' => trim($nome)];
        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= " AND id != :id";
            $params['id'] = $ignoreId;
        }
        $row = $this->db->fetch($sql . " LIMIT 1", $params);
        return !empty($row);
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem)
             VALUES (:nome, :descricao, :ativo, :ordem)",
            [
                'nome' => trim((string) ($data['nome'] ?? '')),
                'descricao' => !empty($data['descricao']) ? trim((string) $data['descricao']) : null,
                'ativo' => !empty($data['ativo']) ? 1 : 0,
                'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            ]
        );
    }

    public function update(int $id, array $data): bool
    {
        return (bool) $this->db->query(
            "UPDATE provas_tipos_avaliacao
             SET nome = :nome,
                 descricao = :descricao,
                 ativo = :ativo,
                 ordem = :ordem
             WHERE id = :id AND deleted_at IS NULL",
            [
                'id' => $id,
                'nome' => trim((string) ($data['nome'] ?? '')),
                'descricao' => !empty($data['descricao']) ? trim((string) $data['descricao']) : null,
                'ativo' => !empty($data['ativo']) ? 1 : 0,
                'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            ]
        );
    }

    public function softDelete(int $id): bool
    {
        return (bool) $this->db->query(
            "UPDATE provas_tipos_avaliacao
             SET deleted_at = NOW()
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
    }
}

