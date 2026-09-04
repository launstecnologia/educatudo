<?php
/**
 * EducaTudo - Student Essay Controller
 * Redação Configurável: visualizar propostas, escrever, OCR, enviar e ver correção (aluno)
 */

require_once __DIR__ . '/../../Models/Essays/EssayBoard.php';
require_once __DIR__ . '/../../Models/Essays/EssayTextType.php';
require_once __DIR__ . '/../../Models/Essays/EssayProposal.php';
require_once __DIR__ . '/../../Models/Essays/EssaySubmission.php';
require_once __DIR__ . '/../../Models/Essays/EssayCorrection.php';
require_once __DIR__ . '/../../Services/EssayOCRService.php';
require_once __DIR__ . '/../../Services/EssayAIService.php';
require_once __DIR__ . '/../../Services/MediaStorageService.php';
require_once __DIR__ . '/../../Core/Logger.php';
require_once __DIR__ . '/../../Helpers/EssayFeedbackAudioHelper.php';
require_once __DIR__ . '/../../Helpers/EssayTextStructureHelper.php';

if (!class_exists('StudentEssayController')) {
class StudentEssayController extends BaseController
{
    private $authManager;
    private $db;
    private $proposalModel;
    private $submissionModel;
    private $correctionModel;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->proposalModel = new EssayProposal();
        $this->submissionModel = new EssaySubmission();
        $this->correctionModel = new EssayCorrection();

        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }

    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/dashboard');
        }
    }

    private function getStudentId()
    {
        $user = $this->authManager->getUser();
        return $user ? (int) $user['id'] : 0;
    }

    /** Redirect if Redação Configurável is disabled (global or for aluno). */
    private function ensureEssayModuleEnabled()
    {
        $global = LayoutHelper::get('module_redacao_configuravel', '1') === '1';
        $aluno = LayoutHelper::get('module_aluno_redacao_configuravel', '1') === '1';
        if (!$global || !$aluno) {
            $this->setFlashMessage('O módulo de Redações está desabilitado.', 'error');
            $this->redirect(URL . '/dashboard');
        }
    }

    /** List published proposals for student */
    public function index()
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $studentId = $this->getStudentId();

        $proposals = $this->attachMaxTotalScoresToProposals(
            $this->proposalModel->findPublishedForStudent($studentId)
        );

        $this->viewWithLayout('student', 'student/essays/index', [
            'title' => 'Jornada da Redação - EducaTudo',
            'page_title' => 'Jornada da Redação',
            'user' => $user,
            'proposals' => $proposals,
            'current_page' => 'jornada-redacao'
        ]);
    }

    /** Show proposal (theme, repertoire) and button to write */
    public function show($id)
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $studentId = $this->getStudentId();
        $proposal = $this->proposalModel->findById($id);
        if (!$proposal) {
            $this->setFlashMessage('Proposta não encontrada', 'error');
            $this->redirect(URL . '/jornada-redacao');
        }
        if ($proposal['status'] !== 'published' || !$this->proposalModel->propostaEstaAtiva($proposal)) {
            $this->setFlashMessage('Esta proposta não está disponível', 'error');
            $this->redirect(URL . '/jornada-redacao');
        }
        $submission = $this->submissionModel->findByProposalAndStudent($id, $studentId);
        $correction = $submission ? $this->correctionModel->findBySubmission((int) $submission['id']) : null;
        $this->viewWithLayout('student', 'student/essays/show', [
            'title' => $proposal['title'] . ' - Redação',
            'user' => $user,
            'proposal' => $proposal,
            'submission' => $submission,
            'correction' => $correction,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'essays'
        ]);
    }

    /** Form to write essay (typed or paste OCR result) */
    public function write($id)
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $studentId = $this->getStudentId();
        $proposal = $this->proposalModel->findById($id);
        if (!$proposal || $proposal['status'] !== 'published' || !$this->proposalModel->propostaEstaAtiva($proposal)) {
            $this->setFlashMessage('Proposta não disponível', 'error');
            $this->redirect(URL . '/jornada-redacao');
        }
        $now = date('Y-m-d H:i:s');
        $startsAt = !empty($proposal['starts_at']) ? $proposal['starts_at'] : null;
        $endsAt = !empty($proposal['ends_at']) ? $proposal['ends_at'] : null;
        if ($startsAt && $now < $startsAt) {
            $this->setFlashMessage('Esta proposta estará disponível a partir de ' . date('d/m/Y H:i', strtotime($startsAt)) . '.', 'error');
            $this->redirect(URL . '/jornada-redacao/' . $id);
        }
        if ($endsAt && $now > $endsAt) {
            $this->setFlashMessage('O prazo para enviar esta redação era até ' . date('d/m/Y H:i', strtotime($endsAt)) . '.', 'error');
            $this->redirect(URL . '/jornada-redacao/' . $id);
        }
        $submission = $this->submissionModel->findByProposalAndStudent($id, $studentId);
        $correction = $submission ? $this->correctionModel->findBySubmission((int) $submission['id']) : null;
        if ($submission && (($submission['status'] ?? '') === 'corrected' || $correction)) {
            $this->setFlashMessage('Esta redação já foi corrigida e não pode mais ser editada.', 'error');
            $this->redirect(URL . '/jornada-redacao/correcao/' . (int) $submission['id']);
        }
        if (!$submission) {
            $submission = null;
        }
        $this->viewWithLayout('student', 'student/essays/write', [
            'title' => 'Escrever redação - ' . $proposal['title'],
            'user' => $user,
            'proposal' => $proposal,
            'submission' => $submission,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /** POST: save draft or submit text */
    public function saveText($proposalId)
    {
        $this->ensureEssayModuleEnabled();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $studentId = $this->getStudentId();
        $proposal = $this->proposalModel->findById($proposalId);
        if (!$proposal || $proposal['status'] !== 'published' || !$this->proposalModel->propostaEstaAtiva($proposal)) {
            $this->json(['error' => 'Proposta não disponível'], 404);
        }
        $now = date('Y-m-d H:i:s');
        $startsAt = !empty($proposal['starts_at']) ? $proposal['starts_at'] : null;
        $endsAt = !empty($proposal['ends_at']) ? $proposal['ends_at'] : null;
        if ($startsAt && $now < $startsAt) {
            $msg = 'Fora do período de realização. Esta proposta estará disponível a partir de ' . date('d/m/Y H:i', strtotime($startsAt)) . '.';
            $this->json(['error' => $msg], 400);
        }
        if ($endsAt && $now > $endsAt) {
            $msg = 'Fora do período de realização. O prazo para enviar era até ' . date('d/m/Y H:i', strtotime($endsAt)) . '.';
            $this->json(['error' => $msg], 400);
        }
        $contentText = (string) ($_POST['content_text'] ?? '');
        $textStructureJson = trim((string) ($_POST['text_structure_json'] ?? ''));
        $layoutJson = trim((string) ($_POST['ocr_layout_json'] ?? ''));
        $submit = isset($_POST['submit']) && $_POST['submit'] === '1';
        $photoOnly = isset($_POST['photo_only']) && $_POST['photo_only'] === '1';
        $submissionMode = $this->proposalModel->normalizeSubmissionMode($proposal['submission_mode'] ?? 'texto');
        $submission = $this->submissionModel->findByProposalAndStudent($proposalId, $studentId);
        $correction = $submission ? $this->correctionModel->findBySubmission((int) $submission['id']) : null;
        if ($submission && (($submission['status'] ?? '') === 'corrected' || $correction)) {
            $this->json(['error' => 'Esta redação já foi corrigida e não pode mais ser alterada.'], 400);
        }

        $decodedStructure = EssayTextStructureHelper::decode($textStructureJson);
        $normalizedStructure = !empty($decodedStructure)
            ? EssayTextStructureHelper::normalizeStructure(['text_structure' => $decodedStructure])
            : EssayTextStructureHelper::buildFromPlainText($contentText);
        $structurePayload = !empty($normalizedStructure)
            ? json_encode($normalizedStructure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $layoutPayload = EssayTextStructureHelper::decode($layoutJson);
        $layoutPayload = !empty($layoutPayload)
            ? json_encode($layoutPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $contentImagePath = $this->persistSubmissionImageFromBase64(
            (int) $proposalId,
            (int) $studentId,
            (string) ($_POST['content_image_base64'] ?? ''),
            (string) ($_POST['content_image_mime'] ?? '')
        );

        if ($submit) {
            $hasImage = ($contentImagePath !== null && $contentImagePath !== '')
                || (!empty($submission['content_image_path']));
            if ($submissionMode === 'foto' && !$hasImage) {
                $this->json(['error' => 'Envie a foto da redação antes de concluir.'], 400);
            }
            if ($submissionMode === 'texto' && trim($contentText) === '') {
                $this->json(['error' => 'Escreva a redação antes de enviar.'], 400);
            }
            if ($submissionMode === 'texto_ou_foto') {
                if ($photoOnly && !$hasImage) {
                    $this->json(['error' => 'Envie a foto da redação.'], 400);
                }
                if (!$photoOnly && trim($contentText) === '' && !$hasImage) {
                    $this->json(['error' => 'Escreva a redação ou envie a foto.'], 400);
                }
            }
            if ($submissionMode === 'foto' || ($submissionMode === 'texto_ou_foto' && $photoOnly)) {
                $contentText = '';
                $textStructureJson = '';
                $layoutJson = '';
                $structurePayload = null;
                $layoutPayload = null;
            }
        }

        if ($submission) {
            $updatePayload = [
                'content_text' => $contentText,
                'ocr_text' => $contentText,
                'ocr_text_structure_json' => $structurePayload,
                'ocr_layout_json' => $layoutPayload,
                'status' => $submit ? 'submitted' : 'draft'
            ];
            if ($contentImagePath !== null && $contentImagePath !== '') {
                $updatePayload['content_image_path'] = $contentImagePath;
            }
            $this->submissionModel->update($submission['id'], $updatePayload);
            $submissionId = $submission['id'];
        } else {
            $createPayload = [
                'proposal_id' => $proposalId,
                'student_id' => $studentId,
                'content_text' => $contentText,
                'ocr_text' => $contentText,
                'ocr_text_structure_json' => $structurePayload,
                'ocr_layout_json' => $layoutPayload,
                'status' => $submit ? 'submitted' : 'draft'
            ];
            if ($contentImagePath !== null && $contentImagePath !== '') {
                $createPayload['content_image_path'] = $contentImagePath;
            }
            $submissionId = $this->submissionModel->create($createPayload);
        }
        $this->json([
            'success' => true,
            'message' => $submit ? 'Redação enviada com sucesso' : 'Rascunho salvo',
            'submission_id' => $submissionId
        ]);
    }

    public function deleteSubmission($proposalId)
    {
        $this->ensureEssayModuleEnabled();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect(URL . '/jornada-redacao/' . (int) $proposalId);
        }

        $studentId = $this->getStudentId();
        $proposal = $this->proposalModel->findById($proposalId);
        if (!$proposal || $proposal['status'] !== 'published' || !$this->proposalModel->propostaEstaAtiva($proposal)) {
            $this->setFlashMessage('Proposta não disponível.', 'error');
            $this->redirect(URL . '/jornada-redacao');
        }

        $submission = $this->submissionModel->findByProposalAndStudent($proposalId, $studentId);
        if (!$submission) {
            $this->setFlashMessage('Nenhuma redação encontrada para excluir.', 'error');
            $this->redirect(URL . '/jornada-redacao/' . (int) $proposalId);
        }

        $correction = $this->correctionModel->findBySubmission((int) $submission['id']);
        if (($submission['status'] ?? '') === 'corrected' || $correction) {
            $this->setFlashMessage('Esta redação já foi corrigida e não pode mais ser excluída.', 'error');
            $this->redirect(URL . '/jornada-redacao/correcao/' . (int) $submission['id']);
        }

        $this->submissionModel->delete((int) $submission['id']);
        $this->setFlashMessage('Redação excluída. Você já pode escrever novamente.', 'success');
        $this->redirect(URL . '/jornada-redacao/' . (int) $proposalId);
    }

    /** POST: OCR - extract text from image (base64) */
    public function ocr()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $imageBase64 = $_POST['image_base64'] ?? '';
        if (empty($imageBase64)) {
            $this->json(['error' => 'Nenhuma imagem enviada'], 400);
        }
        try {
            $ocrService = new EssayOCRService();
            $result = $ocrService->extractStructuredFromImage($imageBase64);
            $this->json([
                'success' => true,
                'text' => $result['raw_text'] ?? '',
                'raw_text' => $result['raw_text'] ?? '',
                'numbered_text' => $result['numbered_text'] ?? '',
                'text_html' => $result['text_html'] ?? '',
                'layout' => $result['layout'] ?? [],
                'text_structure' => $result['text_structure'] ?? [],
                'has_uncertain_words' => $result['has_uncertain_words'] ?? false,
                'total_lines' => $result['total_lines'] ?? 0,
                'total_paragraphs' => $result['total_paragraphs'] ?? 0,
            ]);
        } catch (Exception $e) {
            Logger::error('StudentEssayController OCR failed: ' . $e->getMessage(), [], 'essays');
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** View correction and history for a submission */
    public function viewCorrection($submissionId)
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $studentId = $this->getStudentId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || (int) $submission['student_id'] !== $studentId) {
            $this->setFlashMessage('Envio não encontrado', 'error');
            $this->redirect(URL . '/jornada-redacao');
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        $this->correctionModel->ensureTeacherFeedbackAudioColumn();
        $this->correctionModel->ensureTeacherAnnotationsColumn();
        $this->correctionModel->ensureImageAnnotationColumns();
        $correction = $this->correctionModel->findBySubmission($submissionId);
        $criteria = [];
        if ($proposal && !empty($proposal['text_type_id'])) {
            require_once __DIR__ . '/../../Models/Essays/EssayCriterion.php';
            $criterionModel = new EssayCriterion();
            $criteria = $criterionModel->allByTextType($proposal['text_type_id']);
        }
        $this->viewWithLayout('student', 'student/essays/correction', [
            'title' => 'Correção - ' . $submission['proposal_title'],
            'user' => $user,
            'proposal' => $proposal,
            'submission' => $submission,
            'correction' => $correction,
            'criteria' => $criteria,
            'current_page' => 'essays'
        ]);
    }

    /** GET: ouvir áudio de feedback enviado pelo professor */
    public function streamFeedbackAudioAluno($submissionId)
    {
        $this->ensureEssayModuleEnabled();
        $studentId = $this->getStudentId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || (int) $submission['student_id'] !== $studentId) {
            http_response_code(403);
            exit('Acesso negado');
        }
        $this->correctionModel->ensureTeacherFeedbackAudioColumn();
        $correction = $this->correctionModel->findBySubmission((int) $submissionId);
        $key = trim((string) ($correction['teacher_feedback_audio_key'] ?? ''));
        if ($key === '') {
            http_response_code(404);
            exit('Nenhum áudio disponível');
        }
        EssayFeedbackAudioHelper::streamKey($this->config, $key);
    }

    /** History of my submissions */
    public function history()
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $studentId = $this->getStudentId();
        $submissions = $this->submissionModel->findByStudent($studentId);
        $this->viewWithLayout('student', 'student/essays/history', [
            'title' => 'Histórico de Redações - EducaTudo',
            'user' => $user,
            'submissions' => $submissions,
            'current_page' => 'essays'
        ]);
    }

    public function viewSubmissionOriginal($submissionId)
    {
        $this->ensureEssayModuleEnabled();
        $studentId = $this->getStudentId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || (int) $submission['student_id'] !== $studentId) {
            http_response_code(403);
            exit('Acesso negado');
        }
        $this->streamSubmissionAsset((string) ($submission['content_image_path'] ?? ''), 'redacao-original');
    }

    public function viewAnnotatedImage($submissionId)
    {
        $this->ensureEssayModuleEnabled();
        $studentId = $this->getStudentId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || (int) $submission['student_id'] !== $studentId) {
            http_response_code(403);
            exit('Acesso negado');
        }
        $this->correctionModel->ensureImageAnnotationColumns();
        $correction = $this->correctionModel->findBySubmission((int) $submissionId);
        $key = trim((string) ($correction['annotated_image_key'] ?? ''));
        if ($key === '') {
            http_response_code(404);
            exit('Imagem corrigida ainda não disponível');
        }
        $this->streamSubmissionAsset($key, 'redacao-corrigida.png');
    }

    private function streamSubmissionAsset(string $pathOrKey, string $downloadName): void
    {
        $pathOrKey = trim($pathOrKey);
        if ($pathOrKey === '') {
            http_response_code(404);
            exit('Arquivo não encontrado');
        }
        $isLegacyPath = (strpos($pathOrKey, 'public/') === 0 || strpos($pathOrKey, 'uploads/') !== false);
        if ($isLegacyPath) {
            $fullPath = (strpos($pathOrKey, '/') === 0) ? $pathOrKey : (__DIR__ . '/../../../' . $pathOrKey);
            if (!is_file($fullPath) || !is_readable($fullPath)) {
                http_response_code(404);
                exit('Arquivo não encontrado');
            }
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
            header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . addslashes($downloadName) . '"');
            readfile($fullPath);
            exit;
        }
        $media = new MediaStorageService($this->config);
        $localPath = $media->getLocalPath('essays_submissions', $pathOrKey);
        if ($localPath !== null && is_file($localPath) && is_readable($localPath)) {
            $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
            $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
            header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . addslashes($downloadName) . '"');
            readfile($localPath);
            exit;
        }
        $contents = $media->getContents('essays_submissions', $pathOrKey);
        if ($contents === null || $contents === '') {
            http_response_code(404);
            exit('Arquivo não encontrado');
        }
        $ext = strtolower(pathinfo($pathOrKey, PATHINFO_EXTENSION));
        $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . addslashes($downloadName) . '"');
        echo $contents;
        exit;
    }

    private function persistSubmissionImageFromBase64(int $proposalId, int $studentId, string $base64, string $mimeType): ?string
    {
        $base64 = trim($base64);
        if ($base64 === '') {
            return null;
        }

        $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $mimeType = strtolower(trim($mimeType));
        if (!isset($allowedMime[$mimeType])) {
            $mimeType = 'image/jpeg';
        }
        $extension = $allowedMime[$mimeType];

        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'essay_img_');
        if ($tmpFile === false) {
            return null;
        }
        file_put_contents($tmpFile, $binary);

        $filename = 'env_' . $proposalId . '_' . $studentId . '_' . time() . '_' . uniqid() . '.' . $extension;
        $storageKey = 'p' . $proposalId . '/s' . $studentId . '/' . $filename;

        $media = new MediaStorageService($this->config);
        $stored = $media->put('essays_submissions', $storageKey, $tmpFile, $mimeType);
        @unlink($tmpFile);

        return $stored ? $storageKey : null;
    }

    /**
     * @param array<int, array<string, mixed>> $proposals
     * @return array<int, array<string, mixed>>
     */
    private function attachMaxTotalScoresToProposals(array $proposals): array
    {
        require_once __DIR__ . '/../../Models/Essays/EssayCriterion.php';
        require_once __DIR__ . '/../../Helpers/EssayCriteriaDisplayHelper.php';

        $criterionModel = new EssayCriterion();
        $maxByKey = [];

        foreach ($proposals as &$proposal) {
            $textTypeId = (int) ($proposal['text_type_id'] ?? 0);
            $isEnem = EssayCriteriaDisplayHelper::isEnemBoard(
                $proposal['board_name'] ?? '',
                $proposal['board_slug'] ?? ''
            );
            $cacheKey = $textTypeId . ':' . ($isEnem ? 'enem' : 'other');

            if (!isset($maxByKey[$cacheKey])) {
                $criteria = $textTypeId > 0 ? $criterionModel->allByTextType($textTypeId) : [];
                $criteriaDisplay = EssayCriteriaDisplayHelper::buildCriteriaDisplay($criteria, [], $isEnem);
                $maxByKey[$cacheKey] = EssayCriteriaDisplayHelper::calculateMaxTotal($criteriaDisplay, $isEnem);
            }

            $proposal['max_total_score'] = $maxByKey[$cacheKey];
        }
        unset($proposal);

        return $proposals;
    }
}
}
