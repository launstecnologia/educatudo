<?php
/**
 * EducaTudo - Essay Correction Model (Correção IA + ajuste professor)
 * Redação Configurável - logs de correção
 */

class EssayCorrection
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Garante coluna de áudio de feedback do professor (compatível com bancos antigos).
     */
    public function ensureTeacherFeedbackAudioColumn(): void
    {
        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return;
            }
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'redacoes_orientadas_correcoes'
                   AND COLUMN_NAME = 'teacher_feedback_audio_key'"
            );
            if ($stmt && (int) $stmt->fetchColumn() === 0) {
                $pdo->exec(
                    'ALTER TABLE redacoes_orientadas_correcoes ADD COLUMN teacher_feedback_audio_key VARCHAR(512) NULL DEFAULT NULL'
                );
            }
        } catch (\Throwable $e) {
            error_log('EssayCorrection::ensureTeacherFeedbackAudioColumn: ' . $e->getMessage());
        }
    }

    public function ensureTeacherAnnotationsColumn(): void
    {
        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return;
            }
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'redacoes_orientadas_correcoes'
                   AND COLUMN_NAME = 'teacher_annotations_json'"
            );
            if ($stmt && (int) $stmt->fetchColumn() === 0) {
                $pdo->exec(
                    'ALTER TABLE redacoes_orientadas_correcoes ADD COLUMN teacher_annotations_json LONGTEXT NULL DEFAULT NULL'
                );
            }
        } catch (\Throwable $e) {
            error_log('EssayCorrection::ensureTeacherAnnotationsColumn: ' . $e->getMessage());
        }
    }

    public function ensureImageAnnotationColumns(): void
    {
        try {
            $pdo = $this->db->getPdo();
            if (!$pdo) {
                return;
            }
            $columns = [
                'image_annotations_json' => 'LONGTEXT NULL DEFAULT NULL',
                'annotated_image_key' => 'VARCHAR(512) NULL DEFAULT NULL',
            ];
            foreach ($columns as $column => $definition) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'redacoes_orientadas_correcoes'
                       AND COLUMN_NAME = :column"
                );
                $stmt->execute(['column' => $column]);
                if ((int) $stmt->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE redacoes_orientadas_correcoes ADD COLUMN {$column} {$definition}");
                }
            }
        } catch (\Throwable $e) {
            error_log('EssayCorrection::ensureImageAnnotationColumns: ' . $e->getMessage());
        }
    }

    public function setImageAnnotations(int $correctionId, ?array $data): void
    {
        $json = ($data === null || $data === [])
            ? null
            : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->db->update(
            'UPDATE redacoes_orientadas_correcoes SET image_annotations_json = :json, updated_at = NOW() WHERE id = :id',
            ['json' => $json, 'id' => $correctionId]
        );
    }

    public function setAnnotatedImageKey(int $correctionId, ?string $key): void
    {
        $k = ($key === null || $key === '') ? null : $key;
        $this->db->update(
            'UPDATE redacoes_orientadas_correcoes SET annotated_image_key = :k, updated_at = NOW() WHERE id = :id',
            ['k' => $k, 'id' => $correctionId]
        );
    }

    public function setTeacherFeedbackAudioKey(int $correctionId, ?string $key): void
    {
        $k = $key === null || $key === '' ? null : $key;
        $this->db->update(
            'UPDATE redacoes_orientadas_correcoes SET teacher_feedback_audio_key = :k, updated_at = NOW() WHERE id = :id',
            [
                'k' => $k,
                'id' => $correctionId,
            ]
        );
    }

    public function findBySubmission($submissionId)
    {
        return $this->db->fetch(
            "SELECT c.*, p.version as prompt_version
             FROM redacoes_orientadas_correcoes c
             LEFT JOIN redacoes_orientadas_prompts p ON c.prompt_id = p.id
             WHERE c.submission_id = :submission_id",
            ['submission_id' => (int) $submissionId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT c.*, s.content_text, s.ocr_text, s.student_id, s.proposal_id, p.title as proposal_title, pr.version as prompt_version
             FROM redacoes_orientadas_correcoes c
             JOIN redacoes_orientadas_entregas s ON c.submission_id = s.id
             JOIN redacoes_orientadas_propostas p ON s.proposal_id = p.id
             LEFT JOIN redacoes_orientadas_prompts pr ON c.prompt_id = pr.id
             WHERE c.id = :id",
            ['id' => (int) $id]
        );
    }

    /**
     * Apaga a correção associada a uma entrega — usado quando o professor sobrescreve
     * uma redação já corrigida com um novo envio, para não deixar nota órfã.
     */
    public function deleteBySubmission($submissionId)
    {
        $this->db->delete(
            "DELETE FROM redacoes_orientadas_correcoes WHERE submission_id = :submission_id",
            ['submission_id' => (int) $submissionId]
        );
    }

    public function create($data)
    {
        $gradesJson = isset($data['grades_json']) ? (is_string($data['grades_json']) ? $data['grades_json'] : json_encode($data['grades_json'])) : null;
        $rawJson = isset($data['raw_response_json']) ? (is_string($data['raw_response_json']) ? $data['raw_response_json'] : json_encode($data['raw_response_json'])) : null;
        return $this->db->insert(
            "INSERT INTO redacoes_orientadas_correcoes (submission_id, prompt_id, raw_response_json, grades_json, feedback_text, total_score) VALUES (:submission_id, :prompt_id, :raw_response_json, :grades_json, :feedback_text, :total_score)",
            [
                'submission_id' => (int) $data['submission_id'],
                'prompt_id' => isset($data['prompt_id']) ? (int) $data['prompt_id'] : null,
                'raw_response_json' => $rawJson,
                'grades_json' => $gradesJson,
                'feedback_text' => $data['feedback_text'] ?? null,
                'total_score' => isset($data['total_score']) ? (float) $data['total_score'] : null
            ]
        );
    }

    /**
     * @param int $id Correction id
     * @param int $teacherId Professor id
     * @param array $data Keys: teacher_grades_json, teacher_total_score, use_average, feedback_text, suggestions_text, total_score
     */
    public function updateTeacherAdjustment($id, $teacherId, array $data = [])
    {
        $teacherGradesJson = isset($data['teacher_grades_json']) ? (is_string($data['teacher_grades_json']) ? $data['teacher_grades_json'] : json_encode($data['teacher_grades_json'])) : null;
        $teacherTotal = isset($data['teacher_total_score']) ? (float) $data['teacher_total_score'] : null;
        $useAverage = isset($data['use_average']) ? (int) (bool) $data['use_average'] : 0;
        $feedbackText = $data['feedback_text'] ?? '';
        $suggestionsText = $data['suggestions_text'] ?? null;
        $totalScore = isset($data['total_score']) ? (float) $data['total_score'] : 0;

        $this->db->update(
            "UPDATE redacoes_orientadas_correcoes SET feedback_text = :feedback_text, suggestions_text = :suggestions_text, total_score = :total_score, teacher_grades_json = :teacher_grades_json, teacher_total_score = :teacher_total_score, use_average = :use_average, corrected_by_teacher_id = :teacher_id, teacher_adjusted_at = NOW(), updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'feedback_text' => $feedbackText,
                'suggestions_text' => $suggestionsText,
                'total_score' => $totalScore,
                'teacher_grades_json' => $teacherGradesJson,
                'teacher_total_score' => $teacherTotal,
                'use_average' => $useAverage,
                'teacher_id' => (int) $teacherId
            ]
        );
    }

    /**
     * Remove dados da correção por IA; mantém apenas nota/ajuste do professor.
     */
    public function clearAiData($correctionId)
    {
        $this->db->update(
            "UPDATE redacoes_orientadas_correcoes SET prompt_id = NULL, raw_response_json = NULL, grades_json = NULL, ai_total_score = NULL, total_score = COALESCE(teacher_total_score, 0), use_average = 0, updated_at = NOW() WHERE id = :id",
            ['id' => (int) $correctionId]
        );
    }

    public function setTeacherAnnotations(int $correctionId, array $annotations): void
    {
        $json = empty($annotations)
            ? null
            : json_encode(array_values($annotations), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->db->update(
            'UPDATE redacoes_orientadas_correcoes SET teacher_annotations_json = :annotations, updated_at = NOW() WHERE id = :id',
            [
                'annotations' => $json,
                'id' => $correctionId,
            ]
        );
    }

    public function setAiAnnotations(int $correctionId, array $annotations): void
    {
        $json = empty($annotations)
            ? null
            : json_encode(array_values($annotations), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->db->update(
            'UPDATE redacoes_orientadas_correcoes SET ai_annotations_json = :annotations, updated_at = NOW() WHERE id = :id',
            [
                'annotations' => $json,
                'id' => $correctionId,
            ]
        );
    }

    public function logAction($correctionId, $action, $details = [])
    {
        $detailsJson = is_string($details) ? $details : json_encode($details);
        $this->db->insert(
            "INSERT INTO redacoes_orientadas_correcoes_logs (correction_id, action, details_json) VALUES (:correction_id, :action, :details_json)",
            [
                'correction_id' => (int) $correctionId,
                'action' => $action,
                'details_json' => $detailsJson
            ]
        );
    }
}
