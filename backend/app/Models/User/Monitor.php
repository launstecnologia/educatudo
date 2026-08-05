<?php
/**
 * Modelo de Monitores de sala
 */

class Monitor
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT * FROM monitores ORDER BY nome ASC"
        );
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT * FROM monitores WHERE id = :id",
            ['id' => $id]
        );
    }

    public function findByEmail($email)
    {
        return $this->db->fetch(
            "SELECT * FROM monitores WHERE email = :email",
            ['email' => $email]
        );
    }

    public function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT id FROM monitores WHERE email = :email";
        $params = ['email' => $email];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        return (bool) $this->db->fetch($sql, $params);
    }

    public function create(array $data)
    {
        return $this->db->insert(
            "INSERT INTO monitores (nome, email, senha_hash, turmas, ativo)
             VALUES (:nome, :email, :senha_hash, :turmas, :ativo)",
            [
                'nome' => $data['nome'],
                'email' => $data['email'],
                'senha_hash' => password_hash($data['senha'], PASSWORD_DEFAULT),
                'turmas' => !empty($data['turmas']) ? json_encode(array_values($data['turmas'])) : null,
                'ativo' => $data['ativo'] ?? 1,
            ]
        );
    }

    public function update($id, array $data)
    {
        $sql = "UPDATE monitores SET nome = :nome, email = :email, turmas = :turmas, ativo = :ativo";
        $params = [
            'nome' => $data['nome'],
            'email' => $data['email'],
            'turmas' => !empty($data['turmas']) ? json_encode(array_values($data['turmas'])) : null,
            'ativo' => $data['ativo'] ?? 1,
            'id' => $id,
        ];
        if (!empty($data['senha'])) {
            $sql .= ", senha_hash = :senha_hash";
            $params['senha_hash'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id = :id";
        return $this->db->update($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->delete("DELETE FROM monitores WHERE id = :id", ['id' => $id]);
    }

    public function getTurmasIds(array $monitor): array
    {
        $turmas = json_decode($monitor['turmas'] ?? '[]', true);
        if (!is_array($turmas)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $turmas)));
    }
}
