<?php
/**
 * EducaTudo - Essay Submission Model (Envio de redação pelo aluno)
 * Redação Configurável
 */

class EssaySubmission
{
    private $db;
    private static $structuredColumnsEnsured = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureStructuredColumns();
    }

    public function findByProposal($proposalId)
    {
        return $this->db->fetchAll(
            "SELECT s.*, a.nome as student_name
             FROM redacoes_orientadas_entregas s
             JOIN alunos a ON s.student_id = a.id
             WHERE s.proposal_id = :proposal_id
             ORDER BY s.updated_at DESC",
            ['proposal_id' => (int) $proposalId]
        );
    }

    public function findByStudent($studentId)
    {
        return $this->db->fetchAll(
            "SELECT s.*, p.title as proposal_title, p.theme, b.name as board_name, t.name as text_type_name
             FROM redacoes_orientadas_entregas s
             JOIN redacoes_orientadas_propostas p ON s.proposal_id = p.id
             JOIN redacoes_orientadas_quadros b ON p.board_id = b.id
             JOIN redacoes_orientadas_tipos_texto t ON p.text_type_id = t.id
             WHERE s.student_id = :student_id
             ORDER BY s.updated_at DESC",
            ['student_id' => (int) $studentId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT s.*, p.title as proposal_title, p.theme, p.repertoire, p.board_id, p.text_type_id, b.name as board_name, t.name as text_type_name, a.nome as student_name
             FROM redacoes_orientadas_entregas s
             JOIN redacoes_orientadas_propostas p ON s.proposal_id = p.id
             JOIN redacoes_orientadas_quadros b ON p.board_id = b.id
             JOIN redacoes_orientadas_tipos_texto t ON p.text_type_id = t.id
             JOIN alunos a ON s.student_id = a.id
             WHERE s.id = :id",
            ['id' => (int) $id]
        );
    }

    public function findByProposalAndStudent($proposalId, $studentId)
    {
        return $this->db->fetch(
            "SELECT * FROM redacoes_orientadas_entregas WHERE proposal_id = :proposal_id AND student_id = :student_id ORDER BY id DESC LIMIT 1",
            ['proposal_id' => (int) $proposalId, 'student_id' => (int) $studentId]
        );
    }

    public function create($data)
    {
        $status = $data['status'] ?? 'draft';
        $submittedAt = ($status === 'submitted') ? date('Y-m-d H:i:s') : null;
        return $this->db->insert(
            "INSERT INTO redacoes_orientadas_entregas (proposal_id, student_id, content_text, content_image_path, ocr_text, ocr_text_structure_json, ocr_layout_json, status, submitted_at) VALUES (:proposal_id, :student_id, :content_text, :content_image_path, :ocr_text, :ocr_text_structure_json, :ocr_layout_json, :status, :submitted_at)",
            [
                'proposal_id' => (int) $data['proposal_id'],
                'student_id' => (int) $data['student_id'],
                'content_text' => $data['content_text'] ?? null,
                'content_image_path' => $data['content_image_path'] ?? null,
                'ocr_text' => $data['ocr_text'] ?? null,
                'ocr_text_structure_json' => $this->encodeNullableJson($data['ocr_text_structure_json'] ?? null),
                'ocr_layout_json' => $this->encodeNullableJson($data['ocr_layout_json'] ?? null),
                'status' => $status,
                'submitted_at' => $submittedAt
            ]
        );
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = ['id' => (int) $id];
        foreach (['content_text', 'content_image_path', 'ocr_text', 'status'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "{$key} = :{$key}";
                $params[$key] = $data[$key];
            }
        }
        foreach (['ocr_text_structure_json', 'ocr_layout_json'] as $jsonKey) {
            if (array_key_exists($jsonKey, $data)) {
                $fields[] = "{$jsonKey} = :{$jsonKey}";
                $params[$jsonKey] = $this->encodeNullableJson($data[$jsonKey]);
            }
        }
        if (isset($data['status']) && $data['status'] === 'submitted') {
            $fields[] = "submitted_at = NOW()";
        }
        if (empty($fields)) return;
        $params['id'] = (int) $id;
        $this->db->update(
            "UPDATE redacoes_orientadas_entregas SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id",
            $params
        );
    }

    public function delete($id)
    {
        return $this->db->delete(
            "DELETE FROM redacoes_orientadas_entregas WHERE id = :id",
            ['id' => (int) $id]
        );
    }

    public function getContentText($id)
    {
        $row = $this->db->fetch("SELECT content_text, ocr_text FROM redacoes_orientadas_entregas WHERE id = :id", ['id' => (int) $id]);
        if (!$row) return null;
        return !empty($row['content_text']) ? $row['content_text'] : $row['ocr_text'];
    }

    private function ensureStructuredColumns(): void
    {
        if (self::$structuredColumnsEnsured) {
            return;
        }

        self::$structuredColumnsEnsured = true;

        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return;
            }

            $missing = [];
            foreach (['ocr_text_structure_json' => 'LONGTEXT NULL AFTER ocr_text', 'ocr_layout_json' => 'LONGTEXT NULL AFTER ocr_text_structure_json'] as $column => $definition) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'redacoes_orientadas_entregas'
                       AND COLUMN_NAME = :column"
                );
                $stmt->execute(['column' => $column]);
                if ((int) $stmt->fetchColumn() === 0) {
                    $missing[$column] = $definition;
                }
            }

            foreach ($missing as $column => $definition) {
                $pdo->exec("ALTER TABLE redacoes_orientadas_entregas ADD COLUMN {$column} {$definition}");
            }
        } catch (\Throwable $e) {
            error_log('EssaySubmission::ensureStructuredColumns: ' . $e->getMessage());
        }
    }

    private function encodeNullableJson($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
