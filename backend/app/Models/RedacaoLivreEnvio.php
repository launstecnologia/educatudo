<?php
/**
 * EducaTudo - Redação Livre: envio (upload do professor, sem proposta/jornada)
 */

class RedacaoLivreEnvio
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByTeacher($teacherId)
    {
        return $this->db->fetchAll(
            "SELECT e.*, a.nome as aluno_nome, t.nome as turma_nome
             FROM redacao_livre_envios e
             LEFT JOIN alunos a ON e.student_id = a.id
             LEFT JOIN turmas t ON e.turma_id = t.id
             WHERE e.teacher_id = :teacher_id
             ORDER BY e.created_at DESC",
            ['teacher_id' => (int) $teacherId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT e.*, a.nome as aluno_nome, t.nome as turma_nome
             FROM redacao_livre_envios e
             LEFT JOIN alunos a ON e.student_id = a.id
             LEFT JOIN turmas t ON e.turma_id = t.id
             WHERE e.id = :id",
            ['id' => (int) $id]
        );
    }

    public function findByIdAndTeacher($id, $teacherId)
    {
        $row = $this->findById($id);
        if (!$row || (int) $row['teacher_id'] !== (int) $teacherId) {
            return null;
        }
        return $row;
    }

    public function create($data)
    {
        return $this->db->insert(
            "INSERT INTO redacao_livre_envios (teacher_id, student_name, student_id, turma_id, original_filename, content_image_path, content_text, ocr_text) 
             VALUES (:teacher_id, :student_name, :student_id, :turma_id, :original_filename, :content_image_path, :content_text, :ocr_text)",
            [
                'teacher_id' => (int) $data['teacher_id'],
                'student_name' => $data['student_name'] ?? null,
                'student_id' => isset($data['student_id']) ? (int) $data['student_id'] : null,
                'turma_id' => isset($data['turma_id']) ? (int) $data['turma_id'] : null,
                'original_filename' => $data['original_filename'] ?? null,
                'content_image_path' => $data['content_image_path'] ?? null,
                'content_text' => $data['content_text'] ?? null,
                'ocr_text' => $data['ocr_text'] ?? null,
            ]
        );
    }

    public function update($id, $data)
    {
        $sets = [];
        $params = ['id' => (int) $id];
        foreach (['student_name', 'student_id', 'turma_id', 'content_text'] as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "`$k` = :$k";
                $params[$k] = $k === 'student_id' || $k === 'turma_id' ? (isset($data[$k]) ? (int) $data[$k] : null) : $data[$k];
            }
        }
        if (empty($sets)) {
            return true;
        }
        $this->db->update("UPDATE redacao_livre_envios SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = :id", $params);
        return true;
    }

    public function delete($id)
    {
        return $this->db->delete("DELETE FROM redacao_livre_envios WHERE id = :id", ['id' => (int) $id]);
    }
}
