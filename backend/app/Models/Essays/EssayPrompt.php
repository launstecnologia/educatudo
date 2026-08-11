<?php
/**
 * EducaTudo - Essay Prompt Model (Prompt de correção por IA)
 * Redação Configurável - versionados; apenas um ativo por banca + tipo
 */

class EssayPrompt
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByBoardAndTextType($boardId, $textTypeId)
    {
        return $this->db->fetchAll(
            "SELECT p.*, b.name as board_name, t.name as text_type_name
             FROM redacoes_orientadas_prompts p
             JOIN redacoes_orientadas_quadros b ON p.board_id = b.id
             JOIN redacoes_orientadas_tipos_texto t ON p.text_type_id = t.id
             WHERE p.board_id = :board_id AND p.text_type_id = :text_type_id
             ORDER BY p.version DESC",
            ['board_id' => (int) $boardId, 'text_type_id' => (int) $textTypeId]
        );
    }

    public function getActiveForBoardAndTextType($boardId, $textTypeId)
    {
        return $this->db->fetch(
            "SELECT * FROM redacoes_orientadas_prompts WHERE board_id = :board_id AND text_type_id = :text_type_id AND is_active = 1 LIMIT 1",
            ['board_id' => (int) $boardId, 'text_type_id' => (int) $textTypeId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT p.*, b.name as board_name, t.name as text_type_name
             FROM redacoes_orientadas_prompts p
             JOIN redacoes_orientadas_quadros b ON p.board_id = b.id
             JOIN redacoes_orientadas_tipos_texto t ON p.text_type_id = t.id
             WHERE p.id = :id",
            ['id' => (int) $id]
        );
    }

    public function getNextVersion($boardId, $textTypeId)
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(MAX(version), 0) + 1 AS next_version FROM redacoes_orientadas_prompts WHERE board_id = :board_id AND text_type_id = :text_type_id",
            ['board_id' => (int) $boardId, 'text_type_id' => (int) $textTypeId]
        );
        return (int) ($row['next_version'] ?? 1);
    }

    public function create($data)
    {
        $version = (int) ($data['version'] ?? $this->getNextVersion($data['board_id'], $data['text_type_id']));
        $isActive = isset($data['is_active']) ? (int) $data['is_active'] : 0;
        if ($isActive) {
            $this->deactivateOthers((int) $data['board_id'], (int) $data['text_type_id'], null);
        }
        return $this->db->insert(
            "INSERT INTO redacoes_orientadas_prompts (board_id, text_type_id, version, prompt_text, is_active) VALUES (:board_id, :text_type_id, :version, :prompt_text, :is_active)",
            [
                'board_id' => (int) $data['board_id'],
                'text_type_id' => (int) $data['text_type_id'],
                'version' => $version,
                'prompt_text' => $data['prompt_text'],
                'is_active' => $isActive
            ]
        );
    }

    public function update($id, $data)
    {
        if (!empty($data['is_active'])) {
            $prompt = $this->findById($id);
            if ($prompt) {
                $this->deactivateOthers((int) $prompt['board_id'], (int) $prompt['text_type_id'], (int) $id);
            }
        }
        $this->db->update(
            "UPDATE redacoes_orientadas_prompts SET prompt_text = :prompt_text, is_active = :is_active, updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'prompt_text' => $data['prompt_text'],
                'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 0
            ]
        );
    }

    public function setActive($id)
    {
        $prompt = $this->findById($id);
        if (!$prompt) return;
        $this->deactivateOthers((int) $prompt['board_id'], (int) $prompt['text_type_id'], (int) $id);
        $this->db->update("UPDATE redacoes_orientadas_prompts SET is_active = 1, updated_at = NOW() WHERE id = :id", ['id' => (int) $id]);
    }

    private function deactivateOthers($boardId, $textTypeId, $excludeId)
    {
        $sql = "UPDATE redacoes_orientadas_prompts SET is_active = 0, updated_at = NOW() WHERE board_id = :board_id AND text_type_id = :text_type_id";
        $params = ['board_id' => $boardId, 'text_type_id' => $textTypeId];
        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        $this->db->update($sql, $params);
    }

    public function delete($id)
    {
        $this->db->delete("DELETE FROM redacoes_orientadas_prompts WHERE id = :id", ['id' => (int) $id]);
    }
}
