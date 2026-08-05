<?php
/**
 * EducaTudo - Redação Livre: correção (notas, feedback)
 */

class RedacaoLivreCorrecao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByEnvio($envioId)
    {
        return $this->db->fetch(
            "SELECT * FROM redacao_livre_correcoes WHERE envio_id = :envio_id",
            ['envio_id' => (int) $envioId]
        );
    }

    public function create($data)
    {
        $gradesJson = isset($data['grades_json']) ? (is_string($data['grades_json']) ? $data['grades_json'] : json_encode($data['grades_json'])) : null;
        $teacherGradesJson = isset($data['teacher_grades_json']) ? (is_string($data['teacher_grades_json']) ? $data['teacher_grades_json'] : json_encode($data['teacher_grades_json'])) : null;
        return $this->db->insert(
            "INSERT INTO redacao_livre_correcoes (envio_id, grades_json, teacher_grades_json, feedback_text, suggestions_text, total_score, ai_total_score, teacher_total_score, use_average) 
             VALUES (:envio_id, :grades_json, :teacher_grades_json, :feedback_text, :suggestions_text, :total_score, :ai_total_score, :teacher_total_score, :use_average)",
            [
                'envio_id' => (int) $data['envio_id'],
                'grades_json' => $gradesJson,
                'teacher_grades_json' => $teacherGradesJson,
                'feedback_text' => $data['feedback_text'] ?? null,
                'suggestions_text' => $data['suggestions_text'] ?? null,
                'total_score' => isset($data['total_score']) ? (float) $data['total_score'] : null,
                'ai_total_score' => isset($data['ai_total_score']) ? (float) $data['ai_total_score'] : null,
                'teacher_total_score' => isset($data['teacher_total_score']) ? (float) $data['teacher_total_score'] : null,
                'use_average' => isset($data['use_average']) ? (int) (bool) $data['use_average'] : 0,
            ]
        );
    }

    public function updateTeacherAdjustment($id, $teacherId, array $data = [])
    {
        $teacherGradesJson = isset($data['teacher_grades_json']) ? (is_string($data['teacher_grades_json']) ? $data['teacher_grades_json'] : json_encode($data['teacher_grades_json'])) : null;
        $this->db->update(
            "UPDATE redacao_livre_correcoes SET teacher_grades_json = :teacher_grades_json, feedback_text = :feedback_text, suggestions_text = :suggestions_text, 
             total_score = :total_score, teacher_total_score = :teacher_total_score, use_average = :use_average, 
             corrected_by_teacher_id = :teacher_id, teacher_adjusted_at = NOW(), updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'teacher_grades_json' => $teacherGradesJson,
                'feedback_text' => $data['feedback_text'] ?? '',
                'suggestions_text' => $data['suggestions_text'] ?? null,
                'total_score' => isset($data['total_score']) ? (float) $data['total_score'] : 0,
                'teacher_total_score' => isset($data['teacher_total_score']) ? (float) $data['teacher_total_score'] : null,
                'use_average' => isset($data['use_average']) ? (int) (bool) $data['use_average'] : 0,
                'teacher_id' => (int) $teacherId,
            ]
        );
    }

    public function deleteByEnvio($envioId)
    {
        return $this->db->delete("DELETE FROM redacao_livre_correcoes WHERE envio_id = :envio_id", ['envio_id' => (int) $envioId]);
    }

    public function updateAiCorrection($envioId, $gradesJson, $feedbackText, $suggestionsText, $totalScore)
    {
        $existing = $this->findByEnvio($envioId);
        $gradesStr = is_string($gradesJson) ? $gradesJson : json_encode($gradesJson);
        if ($existing) {
            $this->db->update(
                "UPDATE redacao_livre_correcoes SET grades_json = :grades_json, feedback_text = :feedback_text, suggestions_text = :suggestions_text, total_score = :total_score, ai_total_score = :ai_total_score, updated_at = NOW() WHERE envio_id = :envio_id",
                [
                    'envio_id' => (int) $envioId,
                    'grades_json' => $gradesStr,
                    'feedback_text' => $feedbackText,
                    'suggestions_text' => $suggestionsText,
                    'total_score' => (float) $totalScore,
                    'ai_total_score' => (float) $totalScore,
                ]
            );
        } else {
            $this->db->insert(
                "INSERT INTO redacao_livre_correcoes (envio_id, grades_json, feedback_text, suggestions_text, total_score, ai_total_score) VALUES (:envio_id, :grades_json, :feedback_text, :suggestions_text, :total_score, :ai_total_score)",
                [
                    'envio_id' => (int) $envioId,
                    'grades_json' => $gradesStr,
                    'feedback_text' => $feedbackText,
                    'suggestions_text' => $suggestionsText,
                    'total_score' => (float) $totalScore,
                    'ai_total_score' => (float) $totalScore,
                ]
            );
        }
    }
}
