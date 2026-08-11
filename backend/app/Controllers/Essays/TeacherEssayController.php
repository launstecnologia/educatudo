<?php
/**
 * EducaTudo - Teacher Essay Controller
 * Redação Configurável: propostas de redação e correções (professor)
 */

require_once __DIR__ . '/../../Models/Essays/EssayBoard.php';
require_once __DIR__ . '/../../Models/Essays/EssayTextType.php';
require_once __DIR__ . '/../../Models/Essays/EssayCriterion.php';
require_once __DIR__ . '/../../Models/Essays/EssayPrompt.php';
require_once __DIR__ . '/../../Models/Essays/EssayProposal.php';
require_once __DIR__ . '/../../Models/Essays/EssaySubmission.php';
require_once __DIR__ . '/../../Models/Essays/EssayCorrection.php';
require_once __DIR__ . '/../../Services/EssayAIService.php';
require_once __DIR__ . '/../../Services/EssayOCRService.php';
require_once __DIR__ . '/../../Core/Logger.php';
require_once __DIR__ . '/../../Core/LayoutHelper.php';
require_once __DIR__ . '/../../Utils/HtmlSanitizer.php';
require_once __DIR__ . '/../../Services/MediaStorageService.php';
require_once __DIR__ . '/../../Helpers/EssayFeedbackAudioHelper.php';
require_once __DIR__ . '/../../Helpers/EssayTextStructureHelper.php';

if (!class_exists('TeacherEssayController')) {
class TeacherEssayController extends BaseController
{
    private $authManager;
    private $db;
    private $boardModel;
    private $textTypeModel;
    private $criterionModel;
    private $promptModel;
    private $proposalModel;
    private $submissionModel;
    private $correctionModel;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->boardModel = new EssayBoard();
        $this->textTypeModel = new EssayTextType();
        $this->criterionModel = new EssayCriterion();
        $this->promptModel = new EssayPrompt();
        $this->proposalModel = new EssayProposal();
        $this->submissionModel = new EssaySubmission();
        $this->correctionModel = new EssayCorrection();

        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }

    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/professor/dashboard');
        }
    }

    private function getTeacherId()
    {
        $user = $this->authManager->getUser();
        return $user ? (int) $user['id'] : 0;
    }

    private function canAccessProposal(array $proposal, int $teacherId): bool
    {
        return ((int) $proposal['teacher_id'] === $teacherId) || $this->proposalModel->hasProfessorAccess((int) $proposal['id'], $teacherId);
    }

    private function canManageProposalPermissions(array $proposal, int $teacherId): bool
    {
        return (int) $proposal['teacher_id'] === $teacherId;
    }

    private function buildProposalReportRows(int $teacherId): array
    {
        $proposals = $this->proposalModel->findByTeacherOrShared($teacherId);
        $rows = [];

        foreach ($proposals as $proposal) {
            $proposalId = (int) ($proposal['id'] ?? 0);
            if ($proposalId <= 0) {
                continue;
            }

            $studentsWithAccess = $this->proposalModel->getStudentsWithAccess($proposalId);
            $submissions = $this->submissionModel->findByProposal($proposalId);
            $studentsWithAccess = $this->mergeStudentsWithSubmissionHolders($studentsWithAccess ?: [], $submissions ?: []);
            $turmaByStudent = [];
            $studentIds = array_values(array_filter(array_map(static function ($student) {
                return (int) ($student['id'] ?? 0);
            }, $studentsWithAccess ?: [])));
            if (!empty($studentIds)) {
                $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
                $studentRows = $this->db->fetchAll(
                    "SELECT a.id, COALESCE(t.nome, '') AS turma_nome
                     FROM alunos a
                     LEFT JOIN turmas t ON t.id = a.turma_id
                     WHERE a.id IN ($studentPlaceholders)",
                    $studentIds
                );
                foreach ($studentRows as $studentRow) {
                    $turmaByStudent[(int) ($studentRow['id'] ?? 0)] = (string) ($studentRow['turma_nome'] ?? '');
                }
            }
            $submissionByStudent = [];
            $submissionIds = [];

            foreach ($submissions as $submission) {
                $submissionByStudent[(int) $submission['student_id']] = $submission;
                $submissionIds[] = (int) $submission['id'];
            }

            $correctionBySubmission = $this->getCorrectionBySubmissionMap($submissionIds);

            foreach ($studentsWithAccess ?: [] as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                $submission = $submissionByStudent[$studentId] ?? null;
                $submissionId = $submission ? (int) $submission['id'] : 0;
                $correction = $submissionId > 0 ? ($correctionBySubmission[$submissionId] ?? null) : null;
                $submissionStatus = (string) ($submission['status'] ?? '');

                if (!$submission) {
                    $status = 'nao_enviado';
                    $statusLabel = 'Não enviado';
                } elseif ($this->isSubmissionCorrected($submission, $correction)) {
                    $status = 'corrigido';
                    $statusLabel = 'Corrigido';
                } elseif ($submissionStatus === 'submitted') {
                    $status = 'enviado';
                    $statusLabel = 'Enviado';
                } else {
                    $status = 'visualizado';
                    $statusLabel = 'Visualizado';
                }

                $correctedAt = null;
                if ($correction) {
                    $correctedAt = $correction['teacher_adjusted_at'] ?? $correction['updated_at'] ?? null;
                }

                $rows[] = [
                    'proposal_id' => $proposalId,
                    'proposal_title' => $proposal['title'] ?? '',
                    'board_name' => $proposal['board_name'] ?? '',
                    'text_type_name' => $proposal['text_type_name'] ?? '',
                    'student_id' => $studentId,
                    'student_name' => $student['nome'] ?? '',
                    'turma_name' => $turmaByStudent[$studentId] ?? '',
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'submitted_at' => $submission['submitted_at'] ?? null,
                    'corrected_at' => $correctedAt,
                    'nota_final' => ($correction && isset($correction['nota_final']) && $correction['nota_final'] !== '') ? (float) $correction['nota_final'] : null,
                    'submission_id' => $submissionId > 0 ? $submissionId : null,
                ];
            }
        }

        usort($rows, function (array $a, array $b): int {
            $dateA = $a['corrected_at'] ?: ($a['submitted_at'] ?: '0000-00-00 00:00:00');
            $dateB = $b['corrected_at'] ?: ($b['submitted_at'] ?: '0000-00-00 00:00:00');
            if ($dateA === $dateB) {
                return strcmp((string) $a['proposal_title'], (string) $b['proposal_title']);
            }
            return strcmp($dateB, $dateA);
        });

        return $rows;
    }

    private function getCorrectionBySubmissionMap(array $submissionIds): array
    {
        if (empty($submissionIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
        $corrections = $this->db->fetchAll(
            "SELECT submission_id, teacher_adjusted_at, updated_at,
                    COALESCE(teacher_total_score, total_score, ai_total_score) AS nota_final
             FROM redacoes_orientadas_correcoes
             WHERE submission_id IN ($placeholders)",
            $submissionIds
        );

        $correctionBySubmission = [];
        foreach ($corrections as $correction) {
            $correctionBySubmission[(int) $correction['submission_id']] = $correction;
        }

        return $correctionBySubmission;
    }

    private function mergeStudentsWithSubmissionHolders(array $studentsWithAccess, array $submissions): array
    {
        $studentsMap = [];
        foreach ($studentsWithAccess as $student) {
            $sid = (int) ($student['id'] ?? 0);
            if ($sid > 0) {
                $studentsMap[$sid] = [
                    'id' => $sid,
                    'nome' => (string) ($student['nome'] ?? ''),
                ];
            }
        }

        $missingIds = [];
        foreach ($submissions as $submission) {
            $sid = (int) ($submission['student_id'] ?? 0);
            if ($sid > 0 && !isset($studentsMap[$sid])) {
                $missingIds[] = $sid;
            }
        }
        $missingIds = array_values(array_unique($missingIds));

        if (!empty($missingIds)) {
            $placeholders = implode(',', array_fill(0, count($missingIds), '?'));
            $rows = $this->db->fetchAll(
                "SELECT id, nome FROM alunos WHERE id IN ($placeholders)",
                $missingIds
            );
            foreach ($rows as $row) {
                $sid = (int) ($row['id'] ?? 0);
                if ($sid > 0) {
                    $studentsMap[$sid] = [
                        'id' => $sid,
                        'nome' => (string) ($row['nome'] ?? ''),
                    ];
                }
            }
        }

        $students = array_values($studentsMap);
        usort($students, function (array $a, array $b): int {
            return strcmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
        });
        return $students;
    }

    private function isSubmissionCorrected(?array $submission, ?array $correction): bool
    {
        if (!$submission) {
            return false;
        }

        return (string) ($submission['status'] ?? '') === 'corrected' || !empty($correction);
    }

    /** Normaliza nome para comparação: minúsculas, espaços simples, sem acentos. */
    private function normalizeNameForMatch($s)
    {
        $s = mb_strtolower(trim((string) $s), 'UTF-8');
        $s = preg_replace('/\s+/', ' ', $s);
        $map = ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c'];
        foreach ($map as $from => $to) {
            $s = str_replace($from, $to, $s);
        }
        return $s;
    }

    /** Extrai nome a partir do nome do arquivo (sem extensão, _ e - viram espaço). */
    private function nameFromFilename($filename)
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/[\_\-]+/', ' ', $name);
        return $this->normalizeNameForMatch($name);
    }

    /** Verifica se nome candidato combina com nome do aluno (exato, contém, ou sobrenome + pelo menos um primeiro nome). */
    private function nameMatchesStudent($candidateNorm, $studentNome)
    {
        if ($candidateNorm === '' || trim($studentNome) === '') return false;
        $studentNorm = $this->normalizeNameForMatch($studentNome);
        if ($candidateNorm === $studentNorm) return true;
        if (strpos($candidateNorm, $studentNorm) !== false || strpos($studentNorm, $candidateNorm) !== false) return true;
        $words = array_filter(explode(' ', $studentNorm));
        if (count($words) < 2) return $candidateNorm === $studentNorm;
        $surname = end($words);
        $firstNames = array_slice($words, 0, -1);
        $hasSurname = strpos($candidateNorm, $surname) !== false;
        $hasFirst = false;
        foreach ($firstNames as $w) {
            if (strlen($w) <= 2) continue;
            if (strpos($candidateNorm, $w) !== false) { $hasFirst = true; break; }
        }
        return $hasSurname && $hasFirst;
    }

    private function extractStructuredSubmissionPayload(string $tmpFile, string $mimeType): array
    {
        $payload = [
            'content_text' => '',
            'ocr_text' => '',
            'ocr_text_structure_json' => null,
            'ocr_layout_json' => null,
        ];

        try {
            $imageData = base64_encode((string) file_get_contents($tmpFile));
            $ocrService = new EssayOCRService();
            $ocrResult = $ocrService->extractStructuredFromImage($imageData, $mimeType);
            $payload['content_text'] = trim((string) ($ocrResult['raw_text'] ?? ''));
            $payload['ocr_text'] = $payload['content_text'];
            $normalizedStructure = [];
            if (!empty($ocrResult['text_structure']) && is_array($ocrResult['text_structure'])) {
                $normalizedStructure = EssayTextStructureHelper::normalizeStructure([
                    'text_structure' => $ocrResult['text_structure'],
                ]);
            }
            if (empty($normalizedStructure)) {
                $normalizedStructure = EssayTextStructureHelper::buildFromPlainText($payload['content_text']);
            }
            $payload['ocr_text_structure_json'] = !empty($normalizedStructure)
                ? json_encode($normalizedStructure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;
            $payload['ocr_layout_json'] = !empty($ocrResult['layout']) && is_array($ocrResult['layout'])
                ? json_encode($ocrResult['layout'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;
        } catch (\Throwable $e) {
            Logger::warning('TeacherEssayController OCR estruturado falhou: ' . $e->getMessage(), [], 'essays');
        }

        return $payload;
    }

    /**
     * Identifica o aluno pelo nome do arquivo e/ou pelo texto do OCR.
     * Retorna ['id' => studentId] ou null. Tenta primeiro pelo nome do arquivo, depois pelo texto.
     */
    private function findStudentMatchForBatch($nameFromFile, $ocrText, array $studentNames)
    {
        $textNorm = $ocrText !== '' ? $this->normalizeNameForMatch($ocrText) : '';
        $textNorm = preg_replace('/\s+/', ' ', $textNorm);

        if ($nameFromFile !== '') {
            foreach ($studentNames as $sid => $info) {
                if ($this->nameMatchesStudent($nameFromFile, $info['nome'])) {
                    return ['id' => $sid];
                }
            }
        }

        if ($textNorm !== '') {
            $firstPos = null;
            $matchedId = null;
            foreach ($studentNames as $sid => $info) {
                $nomeNorm = $this->normalizeNameForMatch($info['nome']);
                if ($nomeNorm === '') continue;
                $pos = mb_strpos($textNorm, $nomeNorm);
                if ($pos !== false && ($firstPos === null || $pos < $firstPos)) {
                    $firstPos = $pos;
                    $matchedId = $sid;
                }
            }
            if ($matchedId !== null) return ['id' => $matchedId];
            foreach ($studentNames as $sid => $info) {
                if ($this->nameMatchesStudent($textNorm, $info['nome'])) {
                    return ['id' => $sid];
                }
            }
        }

        return null;
    }

    /** Redirect or JSON error if Redação Configurável is disabled (global or for professor). */
    private function ensureEssayModuleEnabled($json = false)
    {
        $global = LayoutHelper::get('module_redacao_configuravel', '1') === '1';
        $prof = LayoutHelper::get('module_professor_redacao_configuravel', '1') === '1';
        if (!$global || !$prof) {
            if ($json) {
                $this->json(['error' => 'O módulo de Redações está desabilitado.'], 403);
            }
            $this->setFlashMessage('O módulo Jornada da Redação está desabilitado.', 'error');
            $this->redirect(URL . '/professor/dashboard');
        }
    }

    public function index()
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $teacherId = $this->getTeacherId();
        $proposals = $this->proposalModel->findByTeacherOrShared($teacherId, null, true);
        foreach ($proposals as &$proposal) {
            $studentsWithAccess = $this->proposalModel->getStudentsWithAccess((int) $proposal['id']);
            $submissions = $this->submissionModel->findByProposal((int) $proposal['id']);
            $submissionIds = array_map(static function ($submission): int {
                return (int) ($submission['id'] ?? 0);
            }, $submissions);
            $correctionBySubmission = $this->getCorrectionBySubmissionMap(array_values(array_filter($submissionIds)));

            $proposal['qtd_alunos'] = is_array($studentsWithAccess) ? count($studentsWithAccess) : 0;
            $proposal['qtd_enviados'] = 0;
            $proposal['qtd_corrigidos'] = 0;

            foreach ($submissions as $submission) {
                $status = (string) ($submission['status'] ?? '');
                $submissionId = (int) ($submission['id'] ?? 0);
                $correction = $submissionId > 0 ? ($correctionBySubmission[$submissionId] ?? null) : null;
                if (in_array($status, ['submitted', 'corrected'], true)) {
                    $proposal['qtd_enviados']++;
                }
                if ($this->isSubmissionCorrected($submission, $correction)) {
                    $proposal['qtd_corrigidos']++;
                }
            }
        }
        unset($proposal);
        $this->viewWithLayout('professor', 'teacher/essays/index', [
            'title' => 'Jornada da Redação - EducaTudo',
            'user' => $user,
            'proposals' => $proposals,
            'current_page' => 'essays',
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function report()
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $teacherId = $this->getTeacherId();
        $allRows = $this->buildProposalReportRows($teacherId);

        $proposalTitle = trim((string) ($_GET['proposal_title'] ?? ''));
        $boardName = trim((string) ($_GET['board_name'] ?? ''));
        $turmaName = trim((string) ($_GET['turma_name'] ?? ''));
        $studentName = trim((string) ($_GET['student_name'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $submittedFrom = trim((string) ($_GET['submitted_from'] ?? ''));
        $submittedTo = trim((string) ($_GET['submitted_to'] ?? ''));
        $correctedFrom = trim((string) ($_GET['corrected_from'] ?? ''));
        $correctedTo = trim((string) ($_GET['corrected_to'] ?? ''));
        $scoreRange = trim((string) ($_GET['score_range'] ?? ''));
        $allowedScoreRanges = ['lt500', '501_600', '601_700', '701_800', '801_900', '901_1000'];
        if (!in_array($scoreRange, $allowedScoreRanges, true)) {
            $scoreRange = '';
        }
        $dateOrder = trim((string) ($_GET['date_order'] ?? 'desc'));
        if (!in_array($dateOrder, ['asc', 'desc'], true)) {
            $dateOrder = 'desc';
        }
        $hasDateOrderParam = isset($_GET['date_order']);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['per_page'] ?? 10);
        $allowedPerPage = [10, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $filteredRows = array_values(array_filter($allRows, function (array $row) use (
            $proposalTitle,
            $boardName,
            $turmaName,
            $studentName,
            $status,
            $submittedFrom,
            $submittedTo,
            $correctedFrom,
            $correctedTo,
            $scoreRange
        ): bool {
            if ($proposalTitle !== '' && stripos((string) $row['proposal_title'], $proposalTitle) === false) {
                return false;
            }
            if ($boardName !== '' && (string) $row['board_name'] !== $boardName) {
                return false;
            }
            if ($turmaName !== '' && (string) ($row['turma_name'] ?? '') !== $turmaName) {
                return false;
            }
            if ($studentName !== '' && stripos((string) $row['student_name'], $studentName) === false) {
                return false;
            }
            if ($status !== '' && (string) $row['status'] !== $status) {
                return false;
            }

            $submittedDate = !empty($row['submitted_at']) ? substr((string) $row['submitted_at'], 0, 10) : null;
            if ($submittedFrom !== '' && (!$submittedDate || $submittedDate < $submittedFrom)) {
                return false;
            }
            if ($submittedTo !== '' && (!$submittedDate || $submittedDate > $submittedTo)) {
                return false;
            }

            $correctedDate = !empty($row['corrected_at']) ? substr((string) $row['corrected_at'], 0, 10) : null;
            if ($correctedFrom !== '' && (!$correctedDate || $correctedDate < $correctedFrom)) {
                return false;
            }
            if ($correctedTo !== '' && (!$correctedDate || $correctedDate > $correctedTo)) {
                return false;
            }

            if ($scoreRange !== '') {
                $score = isset($row['nota_final']) && $row['nota_final'] !== null ? (float) $row['nota_final'] : null;
                if ($score === null) {
                    return false;
                }
                if ($scoreRange === 'lt500' && !($score < 500)) return false;
                if ($scoreRange === '501_600' && !($score >= 501 && $score <= 600)) return false;
                if ($scoreRange === '601_700' && !($score >= 601 && $score <= 700)) return false;
                if ($scoreRange === '701_800' && !($score >= 701 && $score <= 800)) return false;
                if ($scoreRange === '801_900' && !($score >= 801 && $score <= 900)) return false;
                if ($scoreRange === '901_1000' && !($score >= 901 && $score <= 1000)) return false;
            }

            return true;
        }));

        $isDefaultAccess = !$hasDateOrderParam
            && $proposalTitle === ''
            && $boardName === ''
            && $turmaName === ''
            && $studentName === ''
            && $status === ''
            && $submittedFrom === ''
            && $submittedTo === ''
            && $correctedFrom === ''
            && $correctedTo === ''
            && $scoreRange === '';

        usort($filteredRows, function (array $a, array $b) use ($dateOrder, $isDefaultAccess): int {
            $timeA = !empty($a['submitted_at']) ? strtotime((string) $a['submitted_at']) : 0;
            $timeB = !empty($b['submitted_at']) ? strtotime((string) $b['submitted_at']) : 0;

            $pendingA = !empty($a['submitted_at']) && empty($a['corrected_at']) && (string) ($a['status'] ?? '') !== 'corrigido';
            $pendingB = !empty($b['submitted_at']) && empty($b['corrected_at']) && (string) ($b['status'] ?? '') !== 'corrigido';

            if ($isDefaultAccess && $pendingA !== $pendingB) {
                return $pendingA ? -1 : 1;
            }

            if ($isDefaultAccess && $pendingA && $pendingB) {
                if ($timeA === $timeB) {
                    return 0;
                }
                return $timeA <=> $timeB;
            }

            if ($timeA === $timeB) {
                return 0;
            }
            if ($dateOrder === 'asc') {
                return $timeA <=> $timeB;
            }
            return $timeB <=> $timeA;
        });

        $total = count($filteredRows);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = array_slice($filteredRows, $offset, $perPage);

        $boardOptions = [];
        $turmaOptions = [];
        foreach ($allRows as $row) {
            $name = trim((string) ($row['board_name'] ?? ''));
            if ($name !== '') {
                $boardOptions[$name] = true;
            }
            $turma = trim((string) ($row['turma_name'] ?? ''));
            if ($turma !== '') {
                $turmaOptions[$turma] = true;
            }
        }
        ksort($boardOptions);
        ksort($turmaOptions);

        $this->viewWithLayout('professor', 'teacher/essays/report', [
            'title' => 'Relatório de Redações - EducaTudo',
            'user' => $user,
            'rows' => $rows,
            'total' => $total,
            'filters' => [
                'proposal_title' => $proposalTitle,
                'board_name' => $boardName,
                'turma_name' => $turmaName,
                'student_name' => $studentName,
                'status' => $status,
                'submitted_from' => $submittedFrom,
                'submitted_to' => $submittedTo,
                'corrected_from' => $correctedFrom,
                'corrected_to' => $correctedTo,
                'score_range' => $scoreRange,
                'date_order' => $dateOrder,
                'per_page' => $perPage,
            ],
            'board_options' => array_keys($boardOptions),
            'turma_options' => array_keys($turmaOptions),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'current_page' => 'essays-report'
        ]);
    }

    public function create()
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $boards = $this->boardModel->all(true);
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :id", ['id' => $this->getTeacherId()]);
        $turmasIds = json_decode($professor['turmas'] ?? '[]', true) ?: [];
        $turmas = [];
        if (!empty($turmasIds)) {
            $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
            $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome", $turmasIds);
        }
        $this->viewWithLayout('professor', 'teacher/essays/form', [
            'title' => 'Jornada da Redação - Nova proposta',
            'user' => $user,
            'proposal' => null,
            'boards' => $boards,
            'textTypes' => [],
            'turmas' => $turmas,
            'proposalTurmas' => [],
            'proposalStudents' => [],
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function store()
    {
        try {
            $this->ensureEssayModuleEnabled(true);
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
            }
            $teacherId = $this->getTeacherId();
        $boardId = (int) ($_POST['board_id'] ?? 0);
        $textTypeId = (int) ($_POST['text_type_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $theme = trim($_POST['theme'] ?? '');
        $contextoRaw = trim($_POST['contexto'] ?? '');
        $contexto = $contextoRaw !== '' ? \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($contextoRaw) : '';
        $repertoriosRaw = isset($_POST['repertorios']) && is_array($_POST['repertorios']) ? $_POST['repertorios'] : [];
        $repertorios = array_values(array_filter(array_map(function ($r) {
            $t = trim((string) $r);
            return $t !== '' ? \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($t) : null;
        }, $repertoriosRaw), function ($r) { return $r !== null; }));
        $repertoire = !empty($repertorios) ? json_encode($repertorios) : null;
        $status = trim($_POST['status'] ?? 'draft');
        $themeMode = isset($_POST['theme_mode']) && $_POST['theme_mode'] === 'arquivo' ? 'arquivo' : 'configurar';
        $showTitleField = isset($_POST['show_title_field']) ? (int) (bool) $_POST['show_title_field'] : 1;
        $submissionMode = $this->proposalModel->normalizeSubmissionMode($_POST['submission_mode'] ?? 'texto');
        $startsAt = !empty(trim($_POST['starts_at'] ?? '')) ? trim($_POST['starts_at']) : null;
        $endsAt = !empty(trim($_POST['ends_at'] ?? '')) ? trim($_POST['ends_at']) : null;
        $temaProntoFile = !empty(trim($_POST['tema_pronto_file'] ?? '')) ? trim($_POST['tema_pronto_file']) : null;
        $turmaIds = isset($_POST['turma_ids']) && is_array($_POST['turma_ids']) ? array_filter(array_map('intval', $_POST['turma_ids'])) : [];
        if (empty($title)) {
            $this->json(['error' => 'Título da proposta é obrigatório'], 400);
        }
        if (!$boardId || !$textTypeId) {
            $this->json(['error' => 'Selecione banca e tipo textual'], 400);
        }
        if (in_array($status, ['published'], true) && empty($turmaIds)) {
            $this->json(['error' => 'Ao publicar, selecione pelo menos uma turma'], 400);
        }
        if ($themeMode === 'arquivo' && empty($temaProntoFile)) {
            $this->json(['error' => 'No modo "Tema pronto", é necessário enviar um arquivo (PDF ou imagem).'], 400);
        }
        $textType = $this->textTypeModel->findById($textTypeId);
        if (!$textType || (int) $textType['board_id'] !== $boardId) {
            $this->json(['error' => 'Tipo textual inválido para a banca'], 400);
        }
        $imagesJson = null;
        if (!empty($_POST['images_json'])) {
            $imagesJson = is_string($_POST['images_json']) ? $_POST['images_json'] : json_encode($_POST['images_json']);
        }
        $proposalId = $this->proposalModel->create([
            'teacher_id' => $teacherId,
            'board_id' => $boardId,
            'text_type_id' => $textTypeId,
            'theme_mode' => $themeMode,
            'title' => $title,
            'theme' => $theme,
            'contexto' => $contexto !== '' ? $contexto : null,
            'repertoire' => $repertoire,
            'tema_pronto_file' => $themeMode === 'arquivo' ? $temaProntoFile : null,
            'images_json' => $imagesJson,
            'show_title_field' => $showTitleField,
            'submission_mode' => $submissionMode,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => in_array($status, ['draft', 'published'], true) ? $status : 'draft'
        ]);
        $studentIds = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? array_filter(array_map('intval', $_POST['student_ids'])) : [];
        $this->proposalModel->setTurmas($proposalId, $turmaIds);
        $this->proposalModel->setStudents($proposalId, $studentIds);
        $this->json(['success' => true, 'message' => 'Proposta cadastrada com sucesso']);
        } catch (Exception $e) {
            error_log('TeacherEssayController::store: ' . $e->getMessage());
            $this->json(['error' => 'Erro ao salvar proposta: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $user = $this->authManager->getUser();
        $teacherId = $this->getTeacherId();
        $proposal = $this->proposalModel->findById($id);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->setFlashMessage('Proposta não encontrada', 'error');
            $this->redirect(URL . '/professor/redacao-configuravel');
        }
        $submissions = $this->submissionModel->findByProposal($id);
        $studentsWithAccess = $this->proposalModel->getStudentsWithAccess($id);
        $studentsWithAccess = $this->mergeStudentsWithSubmissionHolders($studentsWithAccess ?: [], $submissions ?: []);
        $submissionByStudent = [];
        $submissionIds = [];
        foreach ($submissions as $s) {
            $submissionByStudent[(int) $s['student_id']] = $s;
            $submissionIds[] = (int) $s['id'];
        }
        $correctionBySubmission = $this->getCorrectionBySubmissionMap($submissionIds);
        $enviosList = [];
        foreach ($studentsWithAccess ?: [] as $a) {
            $sid = (int) $a['id'];
            $sub = $submissionByStudent[$sid] ?? null;
            $subId = $sub ? (int) $sub['id'] : 0;
            $correction = $subId > 0 ? ($correctionBySubmission[$subId] ?? null) : null;
            $submissionStatus = (string) ($sub['status'] ?? '');
            if (!$sub) {
                $statusList = 'nao_enviado';
            } elseif ($this->isSubmissionCorrected($sub, $correction)) {
                $statusList = 'corrigido';
            } elseif ($submissionStatus === 'submitted') {
                $statusList = 'enviado';
            } else {
                $statusList = 'visualizado';
            }

            $correctedAt = null;
            if ($correction) {
                $correctedAt = $correction['teacher_adjusted_at'] ?? $correction['updated_at'] ?? null;
            }
            $notaFinal = '';
            if ($correction && isset($correction['nota_final']) && $correction['nota_final'] !== null && $correction['nota_final'] !== '') {
                $notaFinal = number_format((float) $correction['nota_final'], 0, ',', '.');
            }
            $enviosList[] = [
                'student_id' => $sid,
                'nome' => $a['nome'],
                'status' => $statusList,
                'submitted_at' => $sub ? ($sub['submitted_at'] ?? null) : null,
                'corrected_at' => $correctedAt,
                'nota_final' => $notaFinal,
                'submission_id' => $subId > 0 ? $subId : null,
                'has_submitted' => $sub && ($sub['status'] ?? '') === 'submitted'
            ];
        }
        $allowedProfessors = $this->proposalModel->getAllowedProfessors($id);
        $allProfessors = $this->db->fetchAll("SELECT id, nome, email FROM professores WHERE ativo = 1 ORDER BY nome");
        $this->viewWithLayout('professor', 'teacher/essays/show', [
            'title' => $proposal['title'] . ' - Jornada da Redação',
            'user' => $user,
            'proposal' => $proposal,
            'submissions' => $submissions,
            'studentsWithAccess' => $studentsWithAccess,
            'enviosList' => $enviosList,
            'allowedProfessors' => $allowedProfessors,
            'allProfessors' => $allProfessors,
            // Gestão de permissões é exclusiva do admin/coordenacao.
            'can_manage_permissions' => false,
            'is_admin_view' => false,
            'base_prefix' => '/professor/redacao-configuravel',
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'essays'
        ]);
    }

    public function exportExcel($id)
    {
        $teacherId = $this->getTeacherId();
        $proposal = $this->proposalModel->findById((int) $id);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->setFlashMessage('Proposta não encontrada', 'error');
            $this->redirect(URL . '/professor/redacao-configuravel');
        }

        $rows = $this->buildProposalExportRows((int) $id);
        $filenameBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($proposal['title'] ?? 'redacao'));
        $filenameBase = trim((string) $filenameBase, '-');
        if ($filenameBase === '') {
            $filenameBase = 'redacao';
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '-envios.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Nome do aluno', 'Turma', 'Data de envio', 'Data de correção', 'Nota'], ';');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['aluno_nome'],
                $row['turma_nome'],
                $this->formatDateTimePtBr($row['data_envio']),
                $this->formatDateTimePtBr($row['data_correcao']),
                $row['nota'],
            ], ';');
        }

        fclose($out);
        exit;
    }

    private function buildProposalExportRows(int $proposalId): array
    {
        $submissions = $this->submissionModel->findByProposal($proposalId);
        $studentsWithAccess = $this->proposalModel->getStudentsWithAccess($proposalId);
        $studentsWithAccess = $this->mergeStudentsWithSubmissionHolders($studentsWithAccess ?: [], $submissions ?: []);

        $submissionByStudent = [];
        $submissionIds = [];
        foreach ($submissions as $submission) {
            $studentId = (int) ($submission['student_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }
            $submissionByStudent[$studentId] = $submission;
            $submissionIds[] = (int) ($submission['id'] ?? 0);
        }

        $correctionBySubmission = [];
        if (!empty($submissionIds)) {
            $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
            $corrections = $this->db->fetchAll(
                "SELECT submission_id,
                        teacher_adjusted_at,
                        updated_at,
                        COALESCE(teacher_total_score, total_score, ai_total_score) AS nota_final
                 FROM redacoes_orientadas_correcoes
                 WHERE submission_id IN ($placeholders)",
                $submissionIds
            );
            foreach ($corrections as $correction) {
                $correctionBySubmission[(int) ($correction['submission_id'] ?? 0)] = $correction;
            }
        }

        $studentIds = array_values(array_filter(array_map(static function ($student) {
            return (int) ($student['id'] ?? 0);
        }, $studentsWithAccess ?: [])));

        $turmaByStudent = [];
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $studentRows = $this->db->fetchAll(
                "SELECT a.id,
                        COALESCE(t.nome, '') AS turma_nome
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.id IN ($placeholders)",
                $studentIds
            );
            foreach ($studentRows as $studentRow) {
                $turmaByStudent[(int) ($studentRow['id'] ?? 0)] = (string) ($studentRow['turma_nome'] ?? '');
            }
        }

        $rows = [];
        foreach ($studentsWithAccess ?: [] as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $submission = $submissionByStudent[$studentId] ?? null;
            $submissionId = (int) ($submission['id'] ?? 0);
            $correction = $submissionId > 0 ? ($correctionBySubmission[$submissionId] ?? null) : null;
            $correctedAt = $correction ? ($correction['teacher_adjusted_at'] ?? $correction['updated_at'] ?? null) : null;
            $nota = '';
            if ($correction && $correction['nota_final'] !== null && $correction['nota_final'] !== '') {
                $nota = number_format((float) $correction['nota_final'], 2, ',', '.');
            }

            $rows[] = [
                'aluno_nome' => (string) ($student['nome'] ?? ''),
                'turma_nome' => (string) ($turmaByStudent[$studentId] ?? ''),
                'data_envio' => $submission['submitted_at'] ?? null,
                'data_correcao' => $correctedAt,
                'nota' => $nota,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) $a['aluno_nome'], (string) $b['aluno_nome']);
        });

        return $rows;
    }

    private function formatDateTimePtBr($value): string
    {
        if (empty($value)) {
            return '';
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return (string) $value;
        }

        return date('d/m/Y H:i', $timestamp);
    }

    public function edit($id)
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $teacherId = $this->getTeacherId();
        $proposal = $this->proposalModel->findById($id);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId) || !$proposal['ativo']) {
            $this->setFlashMessage('Proposta não encontrada ou desativada pela coordenação', 'error');
            $this->redirect(URL . '/professor/redacao-configuravel');
        }
        $boards = $this->boardModel->all(true);
        $textTypes = $this->textTypeModel->allByBoard($proposal['board_id']);
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :id", ['id' => $teacherId]);
        $turmasIds = json_decode($professor['turmas'] ?? '[]', true) ?: [];
        $turmas = [];
        if (!empty($turmasIds)) {
            $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
            $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome", $turmasIds);
        }
        $proposalTurmas = array_column($this->proposalModel->getTurmas($id), 'turma_id');
        $proposalStudents = $this->proposalModel->getStudentIds($id);
        $this->viewWithLayout('professor', 'teacher/essays/form', [
            'title' => 'Jornada da Redação - Editar proposta',
            'user' => $user,
            'proposal' => $proposal,
            'boards' => $boards,
            'textTypes' => $textTypes,
            'turmas' => $turmas,
            'proposalTurmas' => $proposalTurmas,
            'proposalStudents' => $proposalStudents,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function update($id)
    {
        try {
            $this->ensureEssayModuleEnabled(true);
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
            }
            $teacherId = $this->getTeacherId();
        $proposal = $this->proposalModel->findById($id);
        // Bug pré-existente encontrado ao investigar 404 real em produção:
        // esta checagem era só do dono (teacher_id), mas edit() já permite
        // professor com acesso compartilhado (canAccessProposal) abrir o
        // formulário — ele preenchia e salvava, e só aqui era barrado.
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId) || !$proposal['ativo']) {
            $this->json(['error' => 'Proposta não encontrada ou desativada pela coordenação'], 404);
        }
        $boardId = (int) ($_POST['board_id'] ?? 0);
        $textTypeId = (int) ($_POST['text_type_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $theme = trim($_POST['theme'] ?? '');
        $contextoRaw = trim($_POST['contexto'] ?? '');
        $contexto = $contextoRaw !== '' ? \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($contextoRaw) : '';
        $repertoriosRaw = isset($_POST['repertorios']) && is_array($_POST['repertorios']) ? $_POST['repertorios'] : [];
        $repertorios = array_values(array_filter(array_map(function ($r) {
            $t = trim((string) $r);
            return $t !== '' ? \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($t) : null;
        }, $repertoriosRaw), function ($r) { return $r !== null; }));
        $repertoire = !empty($repertorios) ? json_encode($repertorios) : null;
        $status = trim($_POST['status'] ?? 'draft');
        $themeMode = isset($_POST['theme_mode']) && $_POST['theme_mode'] === 'arquivo' ? 'arquivo' : 'configurar';
        $showTitleField = isset($_POST['show_title_field']) ? (int) (bool) $_POST['show_title_field'] : 1;
        $submissionMode = $this->proposalModel->normalizeSubmissionMode($_POST['submission_mode'] ?? ($proposal['submission_mode'] ?? 'texto'));
        $startsAt = !empty(trim($_POST['starts_at'] ?? '')) ? trim($_POST['starts_at']) : null;
        $endsAt = !empty(trim($_POST['ends_at'] ?? '')) ? trim($_POST['ends_at']) : null;
        $temaProntoFile = !empty(trim($_POST['tema_pronto_file'] ?? '')) ? trim($_POST['tema_pronto_file']) : null;
        $turmaIds = isset($_POST['turma_ids']) && is_array($_POST['turma_ids']) ? array_filter(array_map('intval', $_POST['turma_ids'])) : [];
        if (empty($title)) {
            $this->json(['error' => 'Título da proposta é obrigatório'], 400);
        }
        if (!$boardId || !$textTypeId) {
            $this->json(['error' => 'Selecione banca e tipo textual'], 400);
        }
        if (in_array($status, ['published'], true) && empty($turmaIds)) {
            $this->json(['error' => 'Ao publicar, selecione pelo menos uma turma'], 400);
        }
        if ($themeMode === 'arquivo' && empty($temaProntoFile)) {
            $this->json(['error' => 'No modo "Tema pronto", é necessário enviar um arquivo (PDF ou imagem).'], 400);
        }
        $textType = $this->textTypeModel->findById($textTypeId);
        if (!$textType || (int) $textType['board_id'] !== $boardId) {
            $this->json(['error' => 'Tipo textual inválido para a banca'], 400);
        }
        $imagesJson = $proposal['images_json'] ?? null;
        if (array_key_exists('images_json', $_POST) && $_POST['images_json'] !== '') {
            $imagesJson = is_string($_POST['images_json']) ? $_POST['images_json'] : json_encode($_POST['images_json']);
        }
        $this->proposalModel->update($id, [
            'board_id' => $boardId,
            'text_type_id' => $textTypeId,
            'theme_mode' => $themeMode,
            'title' => $title,
            'theme' => $theme,
            'contexto' => $contexto !== '' ? $contexto : null,
            'repertoire' => $repertoire,
            'tema_pronto_file' => $themeMode === 'arquivo' ? $temaProntoFile : null,
            'images_json' => $imagesJson,
            'show_title_field' => $showTitleField,
            'submission_mode' => $submissionMode,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => in_array($status, ['draft', 'published'], true) ? $status : 'draft'
        ]);
        $studentIds = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? array_filter(array_map('intval', $_POST['student_ids'])) : [];
        $this->proposalModel->setTurmas($id, $turmaIds);
        $this->proposalModel->setStudents($id, $studentIds);
        $this->json(['success' => true, 'message' => 'Proposta atualizada com sucesso']);
        } catch (Exception $e) {
            error_log('TeacherEssayController::update: ' . $e->getMessage());
            $this->json(['error' => 'Erro ao salvar proposta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Desativa/reativa proposta (soft delete) — nunca apaga do banco, só deixa
     * de aparecer para aluno/relatórios. Exige confirmação de senha do
     * professor, mesmo padrão de AdminEssayController::proposalToggleStatus().
     */
    public function toggleStatus($id)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
        }
        $teacherId = $this->getTeacherId();
        $proposal = $this->proposalModel->findById($id);
        if (!$proposal || (int) $proposal['teacher_id'] !== $teacherId) {
            $this->json(['error' => 'Proposta não encontrada'], 404);
        }
        $senha = trim((string) ($_POST['senha'] ?? ''));
        if ($senha === '') {
            $this->json(['error' => 'Digite sua senha para confirmar.'], 400);
        }
        $professor = $this->db->fetch("SELECT senha_hash FROM professores WHERE id = :id", ['id' => $teacherId]);
        if (!$professor || !password_verify($senha, $professor['senha_hash'] ?? '')) {
            $this->json(['error' => 'Senha incorreta.'], 400);
        }
        $novoAtivo = !((bool) $proposal['ativo']);
        $this->proposalModel->toggleAtivo((int) $id, $novoAtivo);
        $this->json([
            'success' => true,
            'ativo' => $novoAtivo,
            'message' => $novoAtivo ? 'Proposta reativada.' : 'Proposta desativada.',
        ]);
    }

    /** POST: professor envia redação do aluno anexando imagem e transcrevendo com OCR */
    public function submitForStudent($proposalId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $teacherId = $this->getTeacherId();
        $proposal = $this->proposalModel->findById($proposalId);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId) || !$proposal['ativo']) {
            $this->json(['error' => 'Proposta não encontrada ou desativada pela coordenação'], 403);
        }
        $studentId = (int) ($_POST['student_id'] ?? 0);
        if ($studentId <= 0) {
            $this->json(['error' => 'Selecione o aluno'], 400);
        }
        $studentsWithAccess = $this->proposalModel->getStudentsWithAccess($proposalId);
        $allowedIds = array_column($studentsWithAccess, 'id');
        if (!in_array($studentId, $allowedIds)) {
            $this->json(['error' => 'Aluno não está entre os disponibilizados para esta proposta'], 400);
        }
        if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Envie uma imagem ou PDF da redação'], 400);
        }
        $file = $_FILES['imagem'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])) {
            $this->json(['error' => 'Formato inválido. Use JPG, PNG, GIF, WEBP ou PDF.'], 400);
        }
        $filename = 'env_' . $proposalId . '_' . $studentId . '_' . time() . '_' . uniqid() . '.' . $ext;
        $storageKey = 'p' . $proposalId . '/s' . $studentId . '/' . $filename;
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $mime = $file['type'] ?: (strtolower($ext) === 'pdf' ? 'application/pdf' : 'image/jpeg');
        $media = new MediaStorageService($this->config);
        if (!$media->put('essays_submissions', $storageKey, $file['tmp_name'], $mime)) {
            $this->json(['error' => 'Erro ao salvar o arquivo no storage'], 500);
        }
        $ocrPayload = [
            'content_text' => '',
            'ocr_text' => '',
            'ocr_text_structure_json' => null,
            'ocr_layout_json' => null,
        ];
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if ($isImage) {
            $ocrPayload = $this->extractStructuredSubmissionPayload($file['tmp_name'], $mime);
        }
        $existing = $this->submissionModel->findByProposalAndStudent($proposalId, $studentId);
        $existingStatus = $existing['status'] ?? '';
        if ($existing && in_array($existingStatus, ['submitted', 'corrected'], true)) {
            $senha = (string) ($_POST['professor_password'] ?? '');
            if ($senha === '') {
                $mensagem = $existingStatus === 'corrected'
                    ? 'Este aluno já teve a redação corrigida. Confirme sua senha para sobrescrever e apagar a correção existente.'
                    : 'Este aluno já enviou redação. Confirme sua senha para sobrescrever.';
                $this->json(['error' => $mensagem], 400);
            }
            $professor = $this->db->fetch("SELECT senha_hash FROM professores WHERE id = :id", ['id' => $teacherId]);
            if (!$professor || !password_verify($senha, $professor['senha_hash'] ?? '')) {
                $this->json(['error' => 'Senha do professor inválida.'], 403);
            }
        }
        if ($existing) {
            if ($existingStatus === 'corrected') {
                $this->db->beginTransaction();
                try {
                    $this->correctionModel->deleteBySubmission($existing['id']);
                    $this->submissionModel->update($existing['id'], [
                        'content_text' => $ocrPayload['content_text'],
                        'content_image_path' => $storageKey,
                        'ocr_text' => $ocrPayload['ocr_text'],
                        'ocr_text_structure_json' => $ocrPayload['ocr_text_structure_json'],
                        'ocr_layout_json' => $ocrPayload['ocr_layout_json'],
                        'status' => 'submitted'
                    ]);
                    $this->db->commit();
                } catch (\Throwable $e) {
                    $this->db->rollback();
                    throw $e;
                }
            } else {
                $this->submissionModel->update($existing['id'], [
                    'content_text' => $ocrPayload['content_text'],
                    'content_image_path' => $storageKey,
                    'ocr_text' => $ocrPayload['ocr_text'],
                    'ocr_text_structure_json' => $ocrPayload['ocr_text_structure_json'],
                    'ocr_layout_json' => $ocrPayload['ocr_layout_json'],
                    'status' => 'submitted'
                ]);
            }
        } else {
            $this->submissionModel->create([
                'proposal_id' => $proposalId,
                'student_id' => $studentId,
                'content_text' => $ocrPayload['content_text'],
                'content_image_path' => $storageKey,
                'ocr_text' => $ocrPayload['ocr_text'],
                'ocr_text_structure_json' => $ocrPayload['ocr_text_structure_json'],
                'ocr_layout_json' => $ocrPayload['ocr_layout_json'],
                'status' => 'submitted'
            ]);
        }
        $this->json([
            'success' => true,
            'message' => $ocrPayload['content_text'] !== '' ? 'Redação enviada e texto transcrito com sucesso.' : 'Redação enviada. A transcrição por OCR não retornou texto; você pode corrigir o envio e editar o texto.'
        ]);
    }

    /** POST: enviar várias redações em bloco (imagens); OCR em cada e tenta identificar aluno pelo nome no texto */
    public function submitBatch($proposalId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $teacherId = $this->getTeacherId();
        $proposal = $this->proposalModel->findById($proposalId);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId) || !$proposal['ativo']) {
            $this->json(['error' => 'Proposta não encontrada ou desativada pela coordenação'], 403);
        }
        $studentsWithAccess = $this->proposalModel->getStudentsWithAccess($proposalId);
        $allowedIds = array_column($studentsWithAccess ?: [], 'id');
        $studentNames = [];
        foreach ($studentsWithAccess ?: [] as $a) {
            $studentNames[(int) $a['id']] = ['id' => (int) $a['id'], 'nome' => $a['nome']];
        }
        $files = [];
        if (isset($_FILES['imagens']) && is_array($_FILES['imagens']['name'])) {
            for ($i = 0; $i < count($_FILES['imagens']['name']); $i++) {
                if ($_FILES['imagens']['error'][$i] === UPLOAD_ERR_OK && $_FILES['imagens']['size'][$i] > 0) {
                    $files[] = [
                        'name' => $_FILES['imagens']['name'][$i],
                        'tmp_name' => $_FILES['imagens']['tmp_name'][$i],
                        'size' => $_FILES['imagens']['size'][$i],
                        'type' => $_FILES['imagens']['type'][$i]
                    ];
                }
            }
        }
        if (empty($files)) {
            $this->json(['error' => 'Envie ao menos uma imagem (JPG, PNG, etc.)'], 400);
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $results = [];
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        foreach ($files as $index => $file) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt)) {
                $results[] = ['file' => $file['name'], 'ok' => false, 'student_name' => null, 'reason' => 'Formato não suportado'];
                continue;
            }
            $filename = 'env_' . $proposalId . '_lote_' . uniqid() . '_' . $index . '.' . $ext;
            $storageKey = 'p' . $proposalId . '/lote/' . $filename;
            $mime = $file['type'] ?: (strtolower($ext) === 'pdf' ? 'application/pdf' : 'image/jpeg');
            if (!$media->put('essays_submissions', $storageKey, $file['tmp_name'], $mime)) {
                $results[] = ['file' => $file['name'], 'ok' => false, 'student_name' => null, 'reason' => 'Erro ao salvar'];
                continue;
            }
            $ocrPayload = [
                'content_text' => '',
                'ocr_text' => '',
                'ocr_text_structure_json' => null,
                'ocr_layout_json' => null,
            ];
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($isImage) {
                $ocrPayload = $this->extractStructuredSubmissionPayload($file['tmp_name'], $mime);
            }
            $nameFromFile = $this->nameFromFilename($file['name']);
            $match = $this->findStudentMatchForBatch($nameFromFile, $ocrPayload['content_text'], $studentNames);
            $matchedStudentId = $match ? (int) $match['id'] : null;
            if ($matchedStudentId === null) {
                $results[] = ['file' => $file['name'], 'ok' => false, 'student_name' => null, 'reason' => 'Aluno não identificado (nome no arquivo ou no texto da redação)'];
                continue;
            }
            $existing = $this->submissionModel->findByProposalAndStudent($proposalId, $matchedStudentId);
            if ($existing && ($existing['status'] ?? '') === 'corrected') {
                $results[] = ['file' => $file['name'], 'ok' => false, 'student_name' => $studentNames[$matchedStudentId]['nome'] ?? null, 'reason' => 'Redação já corrigida — não sobrescrita. Reenvie individualmente para confirmar a substituição.'];
                continue;
            }
            if ($existing) {
                $this->submissionModel->update($existing['id'], [
                    'content_text' => $ocrPayload['content_text'],
                    'content_image_path' => $storageKey,
                    'ocr_text' => $ocrPayload['ocr_text'],
                    'ocr_text_structure_json' => $ocrPayload['ocr_text_structure_json'],
                    'ocr_layout_json' => $ocrPayload['ocr_layout_json'],
                    'status' => 'submitted'
                ]);
            } else {
                $this->submissionModel->create([
                    'proposal_id' => $proposalId,
                    'student_id' => $matchedStudentId,
                    'content_text' => $ocrPayload['content_text'],
                    'content_image_path' => $storageKey,
                    'ocr_text' => $ocrPayload['ocr_text'],
                    'ocr_text_structure_json' => $ocrPayload['ocr_text_structure_json'],
                    'ocr_layout_json' => $ocrPayload['ocr_layout_json'],
                    'status' => 'submitted'
                ]);
            }
            $results[] = ['file' => $file['name'], 'ok' => true, 'student_name' => $studentNames[$matchedStudentId]['nome']];
        }
        $this->json(['success' => true, 'message' => 'Lote processado.', 'results' => $results]);
    }

    /** List submissions for a proposal and open correction form for one submission */
    public function correctForm($proposalId, $submissionId)
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $teacherId = $this->getTeacherId();
        $proposal = $this->proposalModel->findById($proposalId);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->setFlashMessage('Proposta não encontrada', 'error');
            $this->redirect(URL . '/professor/redacao-configuravel');
        }
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || (int) $submission['proposal_id'] !== (int) $proposalId) {
            $this->setFlashMessage('Envio não encontrado', 'error');
            $this->redirect(URL . '/professor/redacao-configuravel/propostas/' . $proposalId);
        }
        $this->correctionModel->ensureTeacherFeedbackAudioColumn();
        $this->correctionModel->ensureTeacherAnnotationsColumn();
        $this->correctionModel->ensureImageAnnotationColumns();
        $correction = $this->correctionModel->findBySubmission($submissionId);
        $criteria = $this->criterionModel->allByTextType($proposal['text_type_id']);
        $activePrompt = $this->promptModel->getActiveForBoardAndTextType(
            (int) $proposal['board_id'],
            (int) $proposal['text_type_id']
        );
        $hasActivePrompt = $activePrompt && trim((string) ($activePrompt['prompt_text'] ?? '')) !== '';
        $this->viewWithLayout('professor', 'teacher/essays/correct', [
            'title' => 'Corrigir redação - ' . $submission['student_name'],
            'user' => $user,
            'proposal' => $proposal,
            'submission' => $submission,
            'correction' => $correction,
            'criteria' => $criteria,
            'has_active_prompt' => $hasActivePrompt,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /** GET: ouvir áudio de feedback do professor (prévia na tela de correção) */
    public function streamTeacherFeedbackAudio($submissionId)
    {
        $this->ensureEssayModuleEnabled();
        $teacherId = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            http_response_code(404);
            exit('Envio não encontrado');
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
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

    /** POST: enviar áudio de feedback ao aluno (upload ou arquivo gravado no navegador) */
    public function uploadTeacherFeedbackAudio($submissionId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $teacherId = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            $this->json(['error' => 'Envio não encontrado'], 404);
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->json(['error' => 'Proposta não encontrada'], 403);
        }
        $file = $_FILES['audio_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            $msg = $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE
                ? 'Arquivo muito grande. Tente um áudio menor (máx. 20 MB).'
                : 'Nenhum áudio enviado ou erro no upload.';
            $this->json(['error' => $msg], 400);
        }
        $maxBytes = 20 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            $this->json(['error' => 'Arquivo muito grande (máx. 20 MB).'], 400);
        }
        $origName = (string) ($file['name'] ?? 'audio');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExt = ['webm', 'mp3', 'm4a', 'mp4', 'wav', 'ogg', 'opus'];
        if (!in_array($ext, $allowedExt, true)) {
            $this->json(['error' => 'Formato não suportado. Use webm, mp3, m4a, wav ou ogg.'], 400);
        }
        $this->correctionModel->ensureTeacherFeedbackAudioColumn();
        $media = new MediaStorageService($this->config);
        $storageKey = 'teacher_feedback/' . (int) $submissionId . '_' . uniqid('', true) . '.' . $ext;
        $mime = $this->guessAudioMimeForExtension($ext);
        $correction = $this->correctionModel->findBySubmission((int) $submissionId);
        if (!$correction) {
            $this->correctionModel->create([
                'submission_id' => (int) $submissionId,
                'prompt_id' => null,
                'raw_response_json' => null,
                'grades_json' => null,
                'feedback_text' => '',
                'total_score' => 0,
            ]);
            $correction = $this->correctionModel->findBySubmission((int) $submissionId);
        }
        if (!$correction) {
            $this->json(['error' => 'Erro ao preparar correção'], 500);
        }
        $oldKey = trim((string) ($correction['teacher_feedback_audio_key'] ?? ''));
        if ($oldKey !== '') {
            $media->delete('essays_feedback_audio', $oldKey);
        }
        if (!$media->put('essays_feedback_audio', $storageKey, $file['tmp_name'], $mime)) {
            $this->json(['error' => 'Falha ao salvar o áudio no armazenamento.'], 500);
        }
        $this->correctionModel->setTeacherFeedbackAudioKey((int) $correction['id'], $storageKey);
        $this->correctionModel->logAction((int) $correction['id'], 'teacher_feedback_audio', ['submission_id' => (int) $submissionId]);
        $this->json([
            'success' => true,
            'message' => 'Áudio enviado ao aluno com sucesso.',
            'listen_url' => URL . '/professor/redacao-configuravel/envios/' . (int) $submissionId . '/audio-feedback',
        ]);
    }

    /** POST: remover áudio de feedback */
    public function removeTeacherFeedbackAudio($submissionId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $teacherId = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            $this->json(['error' => 'Envio não encontrado'], 404);
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->json(['error' => 'Proposta não encontrada'], 403);
        }
        $this->correctionModel->ensureTeacherFeedbackAudioColumn();
        $correction = $this->correctionModel->findBySubmission((int) $submissionId);
        if (!$correction) {
            $this->json(['error' => 'Nenhuma correção encontrada'], 400);
        }
        $key = trim((string) ($correction['teacher_feedback_audio_key'] ?? ''));
        if ($key === '') {
            $this->json(['error' => 'Não há áudio para remover'], 400);
        }
        $media = new MediaStorageService($this->config);
        $media->delete('essays_feedback_audio', $key);
        $this->correctionModel->setTeacherFeedbackAudioKey((int) $correction['id'], null);
        $this->correctionModel->logAction((int) $correction['id'], 'teacher_feedback_audio_removed', ['submission_id' => (int) $submissionId]);
        $this->json(['success' => true, 'message' => 'Áudio removido.']);
    }

    private function guessAudioMimeForExtension(string $ext): string
    {
        $map = [
            'webm' => 'audio/webm',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'mp4' => 'audio/mp4',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/opus',
        ];
        return $map[strtolower($ext)] ?? 'application/octet-stream';
    }

    /** GET: visualizar imagem ou PDF original do envio (para professor comparar com o texto transcrito) */
    public function viewOriginal($submissionId)
    {
        $this->ensureEssayModuleEnabled();
        $teacherId = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            http_response_code(404);
            exit('Envio não encontrado');
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            http_response_code(403);
            exit('Acesso negado');
        }
        $pathOrKey = trim((string) ($submission['content_image_path'] ?? ''));
        if ($pathOrKey === '') {
            http_response_code(404);
            exit('Nenhum arquivo original disponível para este envio');
        }
        $this->streamSubmissionFile($pathOrKey, basename($pathOrKey));
    }

    /** POST: atualizar texto da redação (professor edita transcrição) */
    public function updateSubmissionText($submissionId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $teacherId = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            $this->json(['error' => 'Envio não encontrado'], 404);
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->json(['error' => 'Proposta não encontrada'], 403);
        }
        $contentText = isset($_POST['content_text']) ? trim((string) $_POST['content_text']) : '';
        $textStructure = EssayTextStructureHelper::buildFromPlainText($contentText);
        $structurePayload = !empty($textStructure)
            ? json_encode($textStructure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $this->submissionModel->update($submissionId, [
            'content_text' => $contentText,
            'ocr_text' => $contentText,
            'ocr_text_structure_json' => $structurePayload,
            'ocr_layout_json' => null
        ]);
        $this->json(['success' => true, 'message' => 'Texto da redação atualizado.']);
    }

    /** POST: enfileira correção por IA para uma submission (assíncrono) */
    public function runAICorrection($submissionId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $teacherId  = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            $this->json(['error' => 'Envio não encontrado'], 404);
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->json(['error' => 'Proposta não encontrada'], 403);
        }
        $text = $this->submissionModel->getContentText($submissionId);
        if (empty(trim($text ?? ''))) {
            $this->json(['error' => 'Redação sem texto para corrigir'], 400);
        }

        require_once __DIR__ . '/../../Services/AIJobService.php';
        $jobId = \App\Services\AIJobService::enqueue(
            'corrigir_essay',
            ['submission_id' => (int) $submissionId],
            $teacherId,
            'professor'
        );

        $this->json(['job_id' => $jobId]);
    }

    public function saveTeacherAnnotation($submissionId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $teacherId = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            $this->json(['error' => 'Envio não encontrado'], 404);
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->json(['error' => 'Proposta não encontrada'], 403);
        }

        $lineId = trim((string) ($_POST['line_id'] ?? ''));
        $selectedText = trim((string) ($_POST['selected_text'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $replacement = trim((string) ($_POST['replacement'] ?? ''));
        $color = trim((string) ($_POST['color'] ?? 'yellow'));
        $allowedColors = ['yellow', 'blue', 'pink', 'green'];
        if (!in_array($color, $allowedColors, true)) {
            $color = 'yellow';
        }
        $start = isset($_POST['start']) ? (int) $_POST['start'] : -1;
        $end = isset($_POST['end']) ? (int) $_POST['end'] : -1;

        if ($lineId === '' || $start < 0 || $end <= $start) {
            $this->json(['error' => 'Trecho inválido para anotação.'], 400);
        }
        if ($selectedText === '') {
            $this->json(['error' => 'Selecione um trecho do texto para anotar.'], 400);
        }
        if ($comment === '') {
            $this->json(['error' => 'Escreva um comentário para salvar a anotação.'], 400);
        }

        $this->correctionModel->ensureTeacherAnnotationsColumn();
        $correction = $this->correctionModel->findBySubmission($submissionId);
        if (!$correction) {
            $correctionId = $this->correctionModel->create([
                'submission_id' => (int) $submissionId,
                'prompt_id' => null,
                'raw_response_json' => null,
                'grades_json' => null,
                'feedback_text' => '',
                'total_score' => 0
            ]);
            $correction = $this->correctionModel->findById($correctionId);
        }
        if (!$correction) {
            $this->json(['error' => 'Não foi possível preparar a correção para receber anotações.'], 500);
        }

        $existing = json_decode((string) ($correction['teacher_annotations_json'] ?? ''), true);
        $existing = is_array($existing) ? $existing : [];

        $annotation = [
            'id' => 'teacher-' . bin2hex(random_bytes(6)),
            'source' => 'teacher',
            'type' => 'comentario_professor',
            'line_id' => $lineId,
            'start' => $start,
            'end' => $end,
            'selected_text' => $selectedText,
            'replacement' => $replacement,
            'comment' => $comment,
            'color' => $color,
            'teacher_id' => (int) $teacherId,
            'created_at' => date('c'),
        ];

        $existing[] = $annotation;
        $this->correctionModel->setTeacherAnnotations((int) $correction['id'], $existing);
        $this->correctionModel->logAction((int) $correction['id'], 'teacher_annotation_added', [
            'submission_id' => (int) $submissionId,
            'teacher_id' => (int) $teacherId,
            'line_id' => $lineId,
        ]);

        $this->json([
            'success' => true,
            'message' => 'Comentário salvo no texto.',
            'annotation' => $annotation,
        ]);
    }

    public function removeTeacherAnnotation($submissionId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $teacherId = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            $this->json(['error' => 'Envio não encontrado'], 404);
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->json(['error' => 'Proposta não encontrada'], 403);
        }

        $annotationId = trim((string) ($_POST['annotation_id'] ?? ''));
        if ($annotationId === '') {
            $this->json(['error' => 'Anotação inválida.'], 400);
        }

        $this->correctionModel->ensureTeacherAnnotationsColumn();
        $correction = $this->correctionModel->findBySubmission($submissionId);
        if (!$correction) {
            $this->json(['error' => 'Nenhuma anotação encontrada para remover.'], 404);
        }

        $existingTeacher = json_decode((string) ($correction['teacher_annotations_json'] ?? ''), true);
        $existingTeacher = is_array($existingTeacher) ? $existingTeacher : [];
        $filteredTeacher = array_values(array_filter($existingTeacher, function ($annotation) use ($annotationId) {
            return (string) ($annotation['id'] ?? '') !== $annotationId;
        }));
        $removedFromTeacher = count($filteredTeacher) !== count($existingTeacher);

        $existingAi = json_decode((string) ($correction['ai_annotations_json'] ?? ''), true);
        $existingAi = is_array($existingAi) ? $existingAi : [];
        $filteredAi = array_values(array_filter($existingAi, function ($annotation) use ($annotationId) {
            return (string) ($annotation['id'] ?? '') !== $annotationId;
        }));
        $removedFromAi = count($filteredAi) !== count($existingAi);

        if (!$removedFromTeacher && !$removedFromAi) {
            $this->json(['error' => 'Anotação não encontrada.'], 404);
        }

        if ($removedFromTeacher) {
            $this->correctionModel->setTeacherAnnotations((int) $correction['id'], $filteredTeacher);
        }
        if ($removedFromAi) {
            $this->correctionModel->setAiAnnotations((int) $correction['id'], $filteredAi);
        }

        $this->correctionModel->logAction((int) $correction['id'], $removedFromAi ? 'ai_annotation_removed_by_teacher' : 'teacher_annotation_removed', [
            'submission_id' => (int) $submissionId,
            'teacher_id' => (int) $teacherId,
            'annotation_id' => $annotationId,
        ]);

        $this->json([
            'success' => true,
            'message' => 'Anotação removida.',
        ]);
    }

    /** POST: salvar rabiscos/desenhos sobre a foto da redação */
    public function saveImageAnnotations($submissionId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $teacherId = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            $this->json(['error' => 'Envio não encontrado'], 404);
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        if (empty(trim((string) ($submission['content_image_path'] ?? '')))) {
            $this->json(['error' => 'Este envio não possui foto para anotar.'], 400);
        }

        $annotationsRaw = trim((string) ($_POST['annotations_json'] ?? ''));
        $annotations = json_decode($annotationsRaw, true);
        if (!is_array($annotations)) {
            $this->json(['error' => 'Dados de anotação inválidos.'], 400);
        }

        $this->correctionModel->ensureImageAnnotationColumns();
        $correction = $this->correctionModel->findBySubmission($submissionId);
        if (!$correction) {
            $correctionId = $this->correctionModel->create([
                'submission_id' => (int) $submissionId,
                'prompt_id' => null,
                'raw_response_json' => null,
                'grades_json' => null,
                'feedback_text' => '',
                'total_score' => 0,
            ]);
            $correction = $this->correctionModel->findById($correctionId);
        }

        $this->correctionModel->setImageAnnotations((int) $correction['id'], $annotations);

        $annotatedKey = null;
        $base64 = trim((string) ($_POST['annotated_image_base64'] ?? ''));
        if ($base64 !== '') {
            $annotatedKey = $this->persistAnnotatedImageFromBase64(
                (int) $submission['proposal_id'],
                (int) $submission['student_id'],
                (int) $submissionId,
                $base64
            );
            if ($annotatedKey) {
                $this->correctionModel->setAnnotatedImageKey((int) $correction['id'], $annotatedKey);
            }
        }

        $this->correctionModel->logAction((int) $correction['id'], 'image_annotations_saved', [
            'submission_id' => (int) $submissionId,
            'teacher_id' => (int) $teacherId,
            'has_flattened_image' => $annotatedKey !== null,
        ]);

        $this->json([
            'success' => true,
            'message' => 'Rabiscos salvos na redação.',
        ]);
    }

    /** GET: imagem corrigida (flatten) para o aluno ou professor */
    public function viewAnnotated($submissionId)
    {
        $this->ensureEssayModuleEnabled();
        $user = $this->authManager->getUser();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            http_response_code(404);
            exit('Envio não encontrado');
        }

        $allowed = false;
        if ($user && $user['tipo'] === 'aluno' && (int) $submission['student_id'] === (int) $user['id']) {
            $allowed = true;
        } elseif ($user && in_array($user['tipo'], ['professor', 'admin', 'admin_escola'], true)) {
            if (in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
                $allowed = true;
            } else {
                $teacherId = $this->getTeacherId();
                $proposal = $this->proposalModel->findById($submission['proposal_id']);
                $allowed = $proposal && $this->canAccessProposal($proposal, $teacherId);
            }
        }
        if (!$allowed) {
            http_response_code(403);
            exit('Acesso negado');
        }

        $this->correctionModel->ensureImageAnnotationColumns();
        $correction = $this->correctionModel->findBySubmission((int) $submissionId);
        $pathOrKey = trim((string) ($correction['annotated_image_key'] ?? ''));
        if ($pathOrKey === '') {
            http_response_code(404);
            exit('Nenhuma imagem corrigida disponível');
        }

        $this->streamSubmissionFile($pathOrKey, 'redacao-corrigida.png');
    }

    private function persistAnnotatedImageFromBase64(int $proposalId, int $studentId, int $submissionId, string $base64): ?string
    {
        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            return null;
        }
        $tmpFile = tempnam(sys_get_temp_dir(), 'essay_ann_');
        if ($tmpFile === false) {
            return null;
        }
        file_put_contents($tmpFile, $binary);
        $filename = 'annotated_' . $submissionId . '_' . time() . '.png';
        $storageKey = 'p' . $proposalId . '/s' . $studentId . '/annotations/' . $filename;
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $stored = $media->put('essays_submissions', $storageKey, $tmpFile, 'image/png');
        @unlink($tmpFile);
        return $stored ? $storageKey : null;
    }

    private function streamSubmissionFile(string $pathOrKey, string $downloadName): void
    {
        $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        $ext = strtolower(pathinfo($pathOrKey, PATHINFO_EXTENSION));

        $isLegacyPath = (strpos($pathOrKey, 'public/') === 0 || strpos($pathOrKey, 'uploads/') !== false);
        if ($isLegacyPath) {
            $fullPath = (strpos($pathOrKey, '/') === 0) ? $pathOrKey : (__DIR__ . '/../../../' . $pathOrKey);
            if (!is_file($fullPath) || !is_readable($fullPath)) {
                http_response_code(404);
                exit('Arquivo não encontrado');
            }
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . addslashes($downloadName) . '"');
            header('Cache-Control: private, max-age=3600');
            readfile($fullPath);
            exit;
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $localPath = $media->getLocalPath('essays_submissions', $pathOrKey);
        if ($localPath !== null && is_file($localPath) && is_readable($localPath)) {
            header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . addslashes($downloadName) . '"');
            header('Cache-Control: private, max-age=3600');
            readfile($localPath);
            exit;
        }
        $contents = $media->getContents('essays_submissions', $pathOrKey);
        if ($contents === null || $contents === '') {
            http_response_code(404);
            exit('Arquivo não encontrado');
        }
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . addslashes($downloadName) . '"');
        header('Cache-Control: private, max-age=3600');
        echo $contents;
        exit;
    }

    /** POST: save teacher adjustment (grades + feedback per criterion, optional average with AI) */
    public function saveCorrection($submissionId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $teacherId = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            $this->json(['error' => 'Envio não encontrado'], 404);
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->json(['error' => 'Proposta não encontrada'], 403);
        }
        $correction = $this->correctionModel->findBySubmission($submissionId);
        if (!$correction) {
            // Professor pode corrigir só com suas notas (sem IA): criar registro de correção vazio
            $correctionId = $this->correctionModel->create([
                'submission_id' => (int) $submissionId,
                'prompt_id' => null,
                'raw_response_json' => null,
                'grades_json' => null,
                'feedback_text' => '',
                'total_score' => 0
            ]);
            $correction = $this->correctionModel->findById($correctionId);
            if (!$correction) {
                $this->json(['error' => 'Erro ao criar registro de correção'], 500);
                return;
            }
        }
        $feedbackText = trim($_POST['feedback_text'] ?? '');
        $suggestionsText = trim($_POST['suggestions_text'] ?? '');
        $useAverage = isset($_POST['use_average']) && (string) $_POST['use_average'] === '1';
        $teacherGradesJson = [];
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'grade_') === 0) {
                $slug = substr($k, 6);
                $score = is_numeric($v) ? (float) $v : null;
                $fb = trim($_POST['feedback_' . $slug] ?? '');
                $teacherGradesJson[$slug] = ['score' => $score, 'feedback' => $fb];
            }
        }
        $teacherTotal = null;
        if (!empty($teacherGradesJson)) {
            $teacherTotal = 0;
            foreach ($teacherGradesJson as $item) {
                if (isset($item['score']) && $item['score'] !== null) {
                    $teacherTotal += (float) $item['score'];
                }
            }
        }
        $aiTotal = isset($correction['ai_total_score']) ? (float) $correction['ai_total_score'] : (float) ($correction['total_score'] ?? 0);
        if ($useAverage && $teacherTotal !== null) {
            $totalScore = ($aiTotal + $teacherTotal) / 2;
        } elseif ($teacherTotal !== null) {
            $totalScore = $teacherTotal;
        } else {
            $totalScore = $aiTotal;
        }
        $this->correctionModel->updateTeacherAdjustment($correction['id'], $teacherId, [
            'teacher_grades_json' => $teacherGradesJson,
            'teacher_total_score' => $teacherTotal,
            'use_average' => $useAverage,
            'feedback_text' => $feedbackText,
            'suggestions_text' => $suggestionsText,
            'total_score' => $totalScore
        ]);
        $this->submissionModel->update($submissionId, [
            'status' => 'corrected'
        ]);
        $this->correctionModel->logAction($correction['id'], 'teacher_adjustment', ['teacher_id' => $teacherId]);
        $this->json(['success' => true, 'message' => 'Correção salva com sucesso']);
    }

    /** POST: remover correção por IA; mantém apenas nota do professor */
    public function removeAiCorrection($submissionId)
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $teacherId = $this->getTeacherId();
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            $this->json(['error' => 'Envio não encontrado'], 404);
        }
        $proposal = $this->proposalModel->findById($submission['proposal_id']);
        if (!$proposal || !$this->canAccessProposal($proposal, $teacherId)) {
            $this->json(['error' => 'Proposta não encontrada'], 403);
        }
        $correction = $this->correctionModel->findBySubmission($submissionId);
        if (!$correction) {
            $this->json(['error' => 'Nenhuma correção encontrada'], 400);
        }
        if (empty($correction['grades_json']) || trim($correction['grades_json']) === '' || trim($correction['grades_json']) === '[]') {
            $this->json(['error' => 'Não há correção por IA para remover'], 400);
        }
        $this->correctionModel->clearAiData($correction['id']);
        $this->correctionModel->logAction($correction['id'], 'remove_ai_correction', ['teacher_id' => $teacherId]);
        $this->json(['success' => true, 'message' => 'Correção por IA removida. Fica apenas a sua nota.']);
    }

    public function grantProfessorPermission($proposalId)
    {
        $this->json(['error' => 'Apenas o admin/coordenacao pode gerenciar acessos de professores nesta proposta.'], 403);
    }

    public function revokeProfessorPermission($proposalId)
    {
        $this->json(['error' => 'Apenas o admin/coordenacao pode gerenciar acessos de professores nesta proposta.'], 403);
    }

    /** API: return text types for a board (AJAX) */
    public function apiTextTypes($boardId)
    {
        $this->ensureEssayModuleEnabled(true);
        $board = $this->boardModel->findById($boardId);
        if (!$board) {
            $this->json([], 200);
            return;
        }
        $items = $this->textTypeModel->allByBoard($boardId);
        $this->json($items);
    }

    /** API: return alunos by turma_ids (AJAX) */
    public function apiAlunosByTurmas()
    {
        $this->ensureEssayModuleEnabled(true);
        $teacherId = $this->getTeacherId();
        $professor = $this->db->fetch("SELECT turmas FROM professores WHERE id = :id", ['id' => $teacherId]);
        $allowedTurmas = json_decode($professor['turmas'] ?? '[]', true) ?: [];
        $turmaIds = isset($_GET['turma_ids']) ? (array) $_GET['turma_ids'] : (isset($_POST['turma_ids']) ? (array) $_POST['turma_ids'] : []);
        $turmaIds = array_filter(array_map('intval', $turmaIds));
        $turmaIds = array_values(array_intersect($turmaIds, $allowedTurmas));
        if (empty($turmaIds)) {
            $this->json([]);
            return;
        }
        require_once __DIR__ . '/../../Models/Education/ClassRoom.php';
        $classRoom = new ClassRoom();
        $alunos = $classRoom->getAlunosByTurmasIds($turmaIds);
        $this->json($alunos);
    }

    /** POST: upload image for proposal (contexto/repertório). Salva em S3 quando configurado (MediaStorageService). */
    public function uploadImage()
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload da imagem');
            }
            $file = $_FILES['imagem'];
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Tipo não permitido. Use JPG, PNG, GIF ou WebP.');
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande. Máximo 10MB.');
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'prop_' . $this->getTeacherId() . '_' . time() . '_' . uniqid() . '.' . $ext;
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $teacherId = $this->getTeacherId();
            $key = MediaStorageService::userKey('teacher', $teacherId, $filename);
            $media = new MediaStorageService($this->config);
            if (!$media->put('essays_proposals', $key, $file['tmp_name'], $file['type'])) {
                throw new Exception('Erro ao salvar arquivo');
            }
            $imageUrl = rtrim(defined('URL') ? URL : '', '/') . '/media/serve?type=essays_proposals&key=' . rawurlencode($key);
            $this->json(['success' => true, 'image_url' => $imageUrl]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** POST: upload tema pronto (PDF ou imagem) para proposta */
    public function uploadTemaPronto()
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            $field = 'tema_pronto';
            if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload. Selecione um arquivo PDF ou imagem.');
            }
            $file = $_FILES[$field];
            $allowedTypes = [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf'
            ];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Tipo não permitido. Use PDF, JPG, PNG, GIF ou WebP.');
            }
            if ($file['size'] > 15 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande. Máximo 15MB.');
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'bin';
            if ($file['type'] === 'application/pdf') {
                $ext = 'pdf';
            }
            $filename = 'tema_pronto_' . $this->getTeacherId() . '_' . time() . '_' . uniqid() . '.' . $ext;
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $teacherId = $this->getTeacherId();
            $key = MediaStorageService::userKey('teacher', $teacherId, $filename);
            $media = new MediaStorageService($this->config);
            if (!$media->put('essays_proposals', $key, $file['tmp_name'], $file['type'])) {
                throw new Exception('Erro ao salvar arquivo');
            }
            $fileUrl = rtrim(defined('URL') ? URL : '', '/') . '/media/serve?type=essays_proposals&key=' . rawurlencode($key);
            $this->json(['success' => true, 'file_url' => $fileUrl]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** POST: gerar repertório/instruções com IA a partir do tema da proposta */
    public function gerarRepertorioIA()
    {
        $this->ensureEssayModuleEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $theme = trim($_POST['theme'] ?? '');
        if ($theme === '') {
            $this->json(['error' => 'Informe o tema da redação para gerar o repertório'], 400);
        }
        try {
            if (!class_exists('App\Services\OpenAIService')) {
                require_once __DIR__ . '/../../Services/OpenAIService.php';
            }
            $promptConfig = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_gerar_repertorio_proposta']
            );
            $promptBase = null;
            if ($promptConfig && !empty(trim($promptConfig['config_value'] ?? ''))) {
                $promptBase = str_replace(['{tema}', '{themeRequest}'], [$theme, $theme], $promptConfig['config_value']);
            }
            if ($promptBase === null || $promptBase === '') {
                $promptBase = "Você é um especialista em educação brasileira e criação de temas de redação para o ENEM. \n\n"
                    . "O aluno solicitou um tema sobre: {themeRequest}\n\n"
                    . "Crie um tema de redação completo seguindo o formato do ENEM com:\n"
                    . "1. Um título claro e específico\n"
                    . "2. Uma descrição detalhada do tema\n"
                    . "3. Uma proposta de intervenção sugerida\n"
                    . "4. Contexto atual relevante\n\n"
                    . "Responda em formato JSON com as seguintes chaves:\n"
                    . "- titulo: título do tema\n"
                    . "- descricao: descrição detalhada do tema\n"
                    . "- proposta_intervencao: sugestão de proposta de intervenção\n"
                    . "- contexto: contexto atual do tema\n\n"
                    . "Seja específico e atual, considerando a realidade brasileira.";
                $promptBase = str_replace('{themeRequest}', $theme, $promptBase);
            }
            $openai = new \App\Services\OpenAIService();
            $text = $openai->generateText($promptBase);
            $text = is_string($text) ? trim($text) : '';
            $this->json(['success' => true, 'text' => $text]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
}
