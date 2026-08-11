<?php
/**
 * EducaTudo - Admin Essay Controller
 * Redação Configurável: CRUD de Bancas, Tipos Textuais, Critérios e Prompts (admin)
 */

require_once __DIR__ . '/../../Models/Essays/EssayBoard.php';
require_once __DIR__ . '/../../Models/Essays/EssayTextType.php';
require_once __DIR__ . '/../../Models/Essays/EssayCriterion.php';
require_once __DIR__ . '/../../Models/Essays/EssayPrompt.php';
require_once __DIR__ . '/../../Models/Essays/EssayProposal.php';
require_once __DIR__ . '/../../Models/Essays/EssaySubmission.php';
require_once __DIR__ . '/../../Models/Essays/EssayCorrection.php';
require_once __DIR__ . '/../../Helpers/AdminPasswordHelper.php';

if (!class_exists('AdminEssayController')) {
class AdminEssayController extends BaseController
{
    private $authManager;
    private $boardModel;
    private $textTypeModel;
    private $criterionModel;
    private $promptModel;
    private $proposalModel;
    private $submissionModel;
    private $correctionModel;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->boardModel = new EssayBoard();
        $this->textTypeModel = new EssayTextType();
        $this->criterionModel = new EssayCriterion();
        $this->promptModel = new EssayPrompt();
        $this->proposalModel = new EssayProposal();
        $this->submissionModel = new EssaySubmission();
        $this->correctionModel = new EssayCorrection();
        $this->db = Database::getInstance();

        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }

    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/admin/dashboard');
        }
    }

    // ---------- Boards (Bancas) ----------
    public function index()
    {
        $user = $this->authManager->getUser();
        $boards = $this->boardModel->all(false);
        $message = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/essays/index', [
            'title' => 'Redação Configurável - EducaTudo',
            'user' => $user,
            'boards' => $boards,
            'message' => $message,
            'current_page' => 'essays_config'
        ]);
    }

    public function proposalsIndex()
    {
        $user = $this->authManager->getUser();
        $message = $this->getFlashMessage();
        $db = Database::getInstance();

        $filtros = [
            'titulo' => trim((string) ($_GET['titulo'] ?? '')),
            'professor_id' => trim((string) ($_GET['professor_id'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        $where = [];
        $params = [];
        if ($filtros['titulo'] !== '') {
            $where[] = 'p.title LIKE :titulo';
            $params['titulo'] = '%' . $filtros['titulo'] . '%';
        }
        if ($filtros['professor_id'] !== '') {
            $where[] = 'p.teacher_id = :professor_id';
            $params['professor_id'] = $filtros['professor_id'];
        }
        if ($filtros['status'] !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $filtros['status'];
        }
        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Busca tudo filtrado e ordena pela mesma regra numérica de título antes
        // de paginar em PHP — sortProposalsByTitleNumeric() extrai o número do
        // título via regex, não é traduzível pra SQL diretamente.
        $proposalsFiltradas = $db->fetchAll(
            "SELECT p.id, p.title, p.status, p.ativo, p.created_at, p.updated_at,
                    b.name AS board_name, t.name AS text_type_name,
                    prof.nome AS professor_nome
             FROM redacoes_orientadas_propostas p
             LEFT JOIN redacoes_orientadas_quadros b ON b.id = p.board_id
             LEFT JOIN redacoes_orientadas_tipos_texto t ON t.id = p.text_type_id
             LEFT JOIN professores prof ON prof.id = p.teacher_id
             $whereSql
             ORDER BY p.id ASC",
            $params
        );
        $proposalsFiltradas = $this->proposalModel->sortProposalsByTitleNumeric($proposalsFiltradas);

        $perPage = 10;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = count($proposalsFiltradas);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, max(1, $totalPages));
        $proposals = array_slice($proposalsFiltradas, ($page - 1) * $perPage, $perPage);

        $professores = $db->fetchAll("SELECT id, nome FROM professores WHERE ativo = 1 ORDER BY nome ASC");

        $this->viewWithLayout('admin', 'admin/essays/proposals', [
            'title' => 'Redação do Professor - EducaTudo',
            'user' => $user,
            'proposals' => $proposals,
            'professores' => $professores,
            'filtros' => $filtros,
            'csrf_token' => $this->generateCsrfToken(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'message' => $message,
            'current_page' => 'essays_teacher'
        ]);
    }

    private function buildProposalReportRows(): array
    {
        $proposals = $this->db->fetchAll(
            "SELECT p.id, p.title, p.teacher_id,
                    b.name AS board_name,
                    prof.nome AS teacher_name
             FROM redacoes_orientadas_propostas p
             LEFT JOIN redacoes_orientadas_quadros b ON b.id = p.board_id
             LEFT JOIN professores prof ON prof.id = p.teacher_id
             WHERE p.ativo = 1
             ORDER BY p.id ASC"
        );
        $proposals = $this->proposalModel->sortProposalsByTitleNumeric($proposals);

        $rows = [];

        foreach ($proposals as $proposal) {
            $proposalId = (int) ($proposal['id'] ?? 0);
            if ($proposalId <= 0) {
                continue;
            }

            $studentsWithAccess = $this->proposalModel->getStudentsWithAccess($proposalId);
            $submissions = $this->submissionModel->findByProposal($proposalId);
            $submissionByStudent = [];
            $submissionIds = [];

            foreach ($submissions as $submission) {
                $submissionByStudent[(int) $submission['student_id']] = $submission;
                $submissionIds[] = (int) $submission['id'];
            }

            $correctionBySubmission = [];
            if (!empty($submissionIds)) {
                $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
                $corrections = $this->db->fetchAll(
                    "SELECT submission_id, teacher_adjusted_at, updated_at
                     FROM redacoes_orientadas_correcoes
                     WHERE submission_id IN ($placeholders)",
                    $submissionIds
                );
                foreach ($corrections as $correction) {
                    $correctionBySubmission[(int) $correction['submission_id']] = $correction;
                }
            }

            foreach ($studentsWithAccess ?: [] as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                $submission = $submissionByStudent[$studentId] ?? null;
                $submissionId = $submission ? (int) $submission['id'] : 0;
                $correction = $submissionId > 0 ? ($correctionBySubmission[$submissionId] ?? null) : null;
                $submissionStatus = (string) ($submission['status'] ?? '');

                if (!$submission) {
                    $status = 'nao_enviado';
                    $statusLabel = 'Não enviado';
                } elseif ($submissionStatus === 'corrected' || $correction) {
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
                    'teacher_id' => (int) ($proposal['teacher_id'] ?? 0),
                    'teacher_name' => $proposal['teacher_name'] ?? '',
                    'student_id' => $studentId,
                    'student_name' => $student['nome'] ?? '',
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'submitted_at' => $submission['submitted_at'] ?? null,
                    'corrected_at' => $correctedAt,
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

    public function report()
    {
        $user = $this->authManager->getUser();
        $allRows = $this->buildProposalReportRows();

        $proposalTitle = trim((string) ($_GET['proposal_title'] ?? ''));
        $boardName = trim((string) ($_GET['board_name'] ?? ''));
        $teacherName = trim((string) ($_GET['teacher_name'] ?? ''));
        $studentName = trim((string) ($_GET['student_name'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $submittedFrom = trim((string) ($_GET['submitted_from'] ?? ''));
        $submittedTo = trim((string) ($_GET['submitted_to'] ?? ''));
        $correctedFrom = trim((string) ($_GET['corrected_from'] ?? ''));
        $correctedTo = trim((string) ($_GET['corrected_to'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['per_page'] ?? 10);
        $allowedPerPage = [10, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $filteredRows = array_values(array_filter($allRows, function (array $row) use (
            $proposalTitle,
            $boardName,
            $teacherName,
            $studentName,
            $status,
            $submittedFrom,
            $submittedTo,
            $correctedFrom,
            $correctedTo
        ): bool {
            if ($proposalTitle !== '' && stripos((string) $row['proposal_title'], $proposalTitle) === false) {
                return false;
            }
            if ($boardName !== '' && (string) $row['board_name'] !== $boardName) {
                return false;
            }
            if ($teacherName !== '' && stripos((string) $row['teacher_name'], $teacherName) === false) {
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

            return true;
        }));

        $total = count($filteredRows);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = array_slice($filteredRows, $offset, $perPage);

        $boardOptions = [];
        $teacherOptions = [];
        foreach ($allRows as $row) {
            $board = trim((string) ($row['board_name'] ?? ''));
            if ($board !== '') {
                $boardOptions[$board] = true;
            }
            $teacher = trim((string) ($row['teacher_name'] ?? ''));
            if ($teacher !== '') {
                $teacherOptions[$teacher] = true;
            }
        }
        ksort($boardOptions);
        ksort($teacherOptions);

        $this->viewWithLayout('admin', 'admin/essays/report', [
            'title' => 'Relatório de Redações - EducaTudo',
            'user' => $user,
            'rows' => $rows,
            'total' => $total,
            'filters' => [
                'proposal_title' => $proposalTitle,
                'board_name' => $boardName,
                'teacher_name' => $teacherName,
                'student_name' => $studentName,
                'status' => $status,
                'submitted_from' => $submittedFrom,
                'submitted_to' => $submittedTo,
                'corrected_from' => $correctedFrom,
                'corrected_to' => $correctedTo,
                'per_page' => $perPage,
            ],
            'board_options' => array_keys($boardOptions),
            'teacher_options' => array_keys($teacherOptions),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'current_page' => 'essays_teacher_report'
        ]);
    }

    public function submissionDetail($proposalId, $submissionId)
    {
        $user = $this->authManager->getUser();
        $proposalId = (int) $proposalId;
        $submissionId = (int) $submissionId;

        $proposal = $this->proposalModel->findById($proposalId);
        if (!$proposal) {
            $this->setFlashMessage('Proposta não encontrada', 'error');
            $this->redirect('/admin/redacao-professor/relatorio');
        }

        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || (int) ($submission['proposal_id'] ?? 0) !== $proposalId) {
            $this->setFlashMessage('Envio não encontrado para esta proposta', 'error');
            $this->redirect('/admin/redacao-professor/relatorio');
        }

        $student = null;
        $studentId = (int) ($submission['student_id'] ?? 0);
        if ($studentId > 0) {
            $student = $this->db->fetch("SELECT id, nome FROM alunos WHERE id = :id LIMIT 1", ['id' => $studentId]);
        }

        $correction = $this->correctionModel->findBySubmission($submissionId);
        $criteria = $this->criterionModel->allByTextType((int) ($proposal['text_type_id'] ?? 0));

        $this->viewWithLayout('admin', 'admin/essays/submission_detail', [
            'title' => 'Detalhes da Redação - EducaTudo',
            'user' => $user,
            'proposal' => $proposal,
            'submission' => $submission,
            'student' => $student,
            'correction' => $correction,
            'criteria' => $criteria,
            'current_page' => 'essays_teacher_report'
        ]);
    }

    public function proposalCreate()
    {
        $user = $this->authManager->getUser();
        $boards = $this->boardModel->all(true);
        $turmas = Database::getInstance()->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome");
        $allProfessors = Database::getInstance()->fetchAll("SELECT id, nome, email FROM professores WHERE ativo = 1 ORDER BY nome");
        $this->viewWithLayout('admin', 'teacher/essays/form', [
            'title' => 'Redação do Professor - Nova proposta',
            'user' => $user,
            'proposal' => null,
            'boards' => $boards,
            'textTypes' => [],
            'turmas' => $turmas,
            'proposalTurmas' => [],
            'proposalStudents' => [],
            'csrf_token' => $this->generateCsrfToken(),
            'base_prefix' => '/admin/redacao-professor',
            'is_admin_form' => true,
            'all_professors' => $allProfessors,
            'selected_teacher_id' => null,
            'selected_professor_ids' => [],
            'current_page' => 'essays_teacher'
        ]);
    }

    public function proposalStore()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            $teacherId = (int) ($_POST['teacher_id'] ?? 0);
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
            $selectedProfessorIds = isset($_POST['allowed_professor_ids']) && is_array($_POST['allowed_professor_ids']) ? array_filter(array_map('intval', $_POST['allowed_professor_ids'])) : [];

            if ($teacherId <= 0) {
                $this->json(['error' => 'Selecione o professor responsável pela proposta.'], 400);
            }
            $teacher = Database::getInstance()->fetch("SELECT id FROM professores WHERE id = :id AND ativo = 1", ['id' => $teacherId]);
            if (!$teacher) {
                $this->json(['error' => 'Professor responsável inválido.'], 400);
            }
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

            $adminUser = $this->authManager->getUser();
            $grantedBy = isset($adminUser['id']) ? (int) $adminUser['id'] : null;
            $selectedProfessorIds = array_values(array_unique($selectedProfessorIds));
            foreach ($selectedProfessorIds as $professorId) {
                if ((int) $professorId <= 0 || (int) $professorId === $teacherId) {
                    continue;
                }
                $this->proposalModel->grantProfessorAccess((int) $proposalId, (int) $professorId, $grantedBy);
            }

            $this->json(['success' => true, 'message' => 'Proposta cadastrada com sucesso']);
        } catch (Exception $e) {
            error_log('AdminEssayController::proposalStore: ' . $e->getMessage());
            $this->json(['error' => 'Erro ao salvar proposta: ' . $e->getMessage()], 500);
        }
    }

    public function apiTextTypes($boardId)
    {
        $board = $this->boardModel->findById($boardId);
        if (!$board) {
            $this->json([], 200);
            return;
        }
        $this->json($this->textTypeModel->allByBoard($boardId));
    }

    public function apiAlunosByTurmas()
    {
        $turmaIds = isset($_GET['turma_ids']) ? (array) $_GET['turma_ids'] : (isset($_POST['turma_ids']) ? (array) $_POST['turma_ids'] : []);
        $turmaIds = array_filter(array_map('intval', $turmaIds));
        if (empty($turmaIds)) {
            $this->json([]);
            return;
        }
        require_once __DIR__ . '/../../Models/Education/ClassRoom.php';
        $classRoom = new ClassRoom();
        $this->json($classRoom->getAlunosByTurmasIds($turmaIds));
    }

    public function uploadImage()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload da imagem');
            }
            $file = $_FILES['imagem'];
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes, true)) {
                throw new Exception('Tipo não permitido. Use JPG, PNG, GIF ou WebP.');
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande. Máximo 10MB.');
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'prop_admin_' . time() . '_' . uniqid() . '.' . $ext;
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $adminUser = $this->authManager->getUser();
            $key = MediaStorageService::userKey('admin', (int) ($adminUser['id'] ?? 0), $filename);
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

    public function uploadTemaPronto()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            if (!isset($_FILES['tema_pronto']) || $_FILES['tema_pronto']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload. Selecione um arquivo PDF ou imagem.');
            }
            $file = $_FILES['tema_pronto'];
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            if (!in_array($file['type'], $allowedTypes, true)) {
                throw new Exception('Tipo não permitido. Use PDF, JPG, PNG, GIF ou WebP.');
            }
            if ($file['size'] > 15 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande. Máximo 15MB.');
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'bin';
            if ($file['type'] === 'application/pdf') $ext = 'pdf';
            $filename = 'tema_pronto_admin_' . time() . '_' . uniqid() . '.' . $ext;
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $adminUser = $this->authManager->getUser();
            $key = MediaStorageService::userKey('admin', (int) ($adminUser['id'] ?? 0), $filename);
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

    public function gerarRepertorioIA()
    {
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
            $promptConfig = Database::getInstance()->fetch(
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
            $this->json(['success' => true, 'text' => is_string($text) ? trim($text) : '']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Desativa/reativa proposta (soft delete) — nunca apaga do banco, só
     * deixa de aparecer pra professor/aluno/relatórios/dashboards. Exige
     * confirmação de senha por ser mais sensível que boardToggleStatus
     * (que não pede senha) — some de dados de desempenho do aluno.
     */
    public function proposalToggleStatus($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $proposal = $this->proposalModel->findById($id);
        if (!$proposal) {
            $this->json(['error' => 'Proposta não encontrada.'], 404);
            return;
        }

        $user = $this->authManager->getUser();
        $senha = trim((string) ($_POST['senha'] ?? ''));
        if ($senha === '') {
            $this->json(['error' => 'Digite sua senha para confirmar.'], 400);
            return;
        }
        if (!AdminPasswordHelper::verifyAdminPassword($this->db, $user, $senha)) {
            $this->json(['error' => 'Senha incorreta.'], 400);
            return;
        }

        $novoAtivo = !((bool) $proposal['ativo']);
        $this->proposalModel->toggleAtivo((int) $id, $novoAtivo);

        $this->json([
            'success' => true,
            'ativo' => $novoAtivo,
            'message' => $novoAtivo ? 'Proposta reativada.' : 'Proposta desativada.',
        ]);
    }

    public function proposalShow($id)
    {
        $user = $this->authManager->getUser();
        $isMasterAdmin = ($user['tipo'] ?? '') === 'admin';
        $proposal = $this->proposalModel->findById($id);
        if (!$proposal) {
            $this->setFlashMessage('Proposta não encontrada', 'error');
            $this->redirect('/admin/redacao-configuravel');
        }
        $submissions = $this->submissionModel->findByProposal($id);
        $studentsWithAccess = $this->proposalModel->getStudentsWithAccess($id);
        $submissionByStudent = [];
        $submissionIds = [];
        foreach ($submissions as $s) {
            $submissionByStudent[(int) $s['student_id']] = $s;
            $submissionIds[] = (int) $s['id'];
        }
        $correctionBySubmission = [];
        if (!empty($submissionIds)) {
            $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
            $corrections = Database::getInstance()->fetchAll(
                "SELECT submission_id, teacher_adjusted_at
                 FROM redacoes_orientadas_correcoes
                 WHERE submission_id IN ($placeholders)",
                $submissionIds
            );
            foreach ($corrections as $c) {
                $correctionBySubmission[(int) $c['submission_id']] = $c;
            }
        }
        $enviosList = [];
        foreach ($studentsWithAccess ?: [] as $a) {
            $sid = (int) $a['id'];
            $sub = $submissionByStudent[$sid] ?? null;
            $subId = $sub ? (int) $sub['id'] : 0;
            $correction = $subId > 0 ? ($correctionBySubmission[$subId] ?? null) : null;
            $submissionStatus = (string) ($sub['status'] ?? '');
            if (!$sub) {
                $statusList = 'nao_enviado';
            } elseif ($submissionStatus === 'corrected') {
                $statusList = 'corrigido';
            } elseif ($submissionStatus === 'submitted') {
                $statusList = 'enviado';
            } else {
                $statusList = 'visualizado';
            }

            $enviosList[] = [
                'student_id' => $sid,
                'nome' => $a['nome'],
                'status' => $statusList,
                'submitted_at' => $sub ? ($sub['submitted_at'] ?? null) : null,
                'corrected_at' => $correction['teacher_adjusted_at'] ?? null,
                'submission_id' => $subId > 0 ? $subId : null,
                'has_submitted' => $sub && ($sub['status'] ?? '') === 'submitted',
            ];
        }
        $allowedProfessors = $this->proposalModel->getAllowedProfessors($id);
        $allProfessors = Database::getInstance()->fetchAll("SELECT id, nome, email FROM professores WHERE ativo = 1 ORDER BY nome");

        $this->viewWithLayout('admin', 'teacher/essays/show', [
            'title' => $proposal['title'] . ' - Jornada da Redação',
            'user' => $user,
            'proposal' => $proposal,
            'submissions' => $submissions,
            'studentsWithAccess' => $studentsWithAccess,
            'enviosList' => $enviosList,
            'allowedProfessors' => $allowedProfessors,
            'allProfessors' => $allProfessors,
            // Permissões de professores: apenas admin master (não admin_escola).
            'can_manage_permissions' => $isMasterAdmin,
            'is_admin_view' => true,
            'base_prefix' => '/admin/redacao-configuravel',
            'permission_base_prefix' => '/admin/redacao-configuravel/propostas',
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'essays_teacher'
        ]);
    }

    public function grantProfessorPermission($proposalId)
    {
        try {
            $user = $this->authManager->getUser();
            if (($user['tipo'] ?? '') !== 'admin') {
                $this->json(['error' => 'Apenas admin master pode conceder acesso de professores.'], 403);
            }
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
            }
            $proposal = $this->proposalModel->findById($proposalId);
            if (!$proposal) {
                $this->json(['error' => 'Proposta não encontrada'], 404);
            }
            $professorId = (int) ($_POST['professor_id'] ?? 0);
            if ($professorId <= 0 || $professorId === (int) $proposal['teacher_id']) {
                $this->json(['error' => 'Professor inválido para concessão.'], 400);
            }
            $this->proposalModel->grantProfessorAccess((int) $proposalId, $professorId, (int) $user['id']);
            $this->json(['success' => true, 'message' => 'Permissão concedida com sucesso.']);
        } catch (\Throwable $e) {
            error_log('AdminEssayController::grantProfessorPermission: ' . $e->getMessage());
            $this->json(['error' => 'Erro ao conceder acesso de professor: ' . $e->getMessage()], 500);
        }
    }

    public function revokeProfessorPermission($proposalId)
    {
        try {
            $user = $this->authManager->getUser();
            if (($user['tipo'] ?? '') !== 'admin') {
                $this->json(['error' => 'Apenas admin master pode remover acesso de professores.'], 403);
            }
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
            }
            $proposal = $this->proposalModel->findById($proposalId);
            if (!$proposal) {
                $this->json(['error' => 'Proposta não encontrada'], 404);
            }
            $professorId = (int) ($_POST['professor_id'] ?? 0);
            if ($professorId <= 0) {
                $this->json(['error' => 'Professor inválido.'], 400);
            }
            $this->proposalModel->revokeProfessorAccess((int) $proposalId, $professorId);
            $this->json(['success' => true, 'message' => 'Permissão removida com sucesso.']);
        } catch (\Throwable $e) {
            error_log('AdminEssayController::revokeProfessorPermission: ' . $e->getMessage());
            $this->json(['error' => 'Erro ao remover acesso de professor: ' . $e->getMessage()], 500);
        }
    }

    public function boardsIndex()
    {
        $this->index();
    }

    public function boardCreate()
    {
        $user = $this->authManager->getUser();
        $this->viewWithLayout('admin', 'admin/essays/board_form', [
            'title' => 'Nova Banca - Redação Configurável',
            'user' => $user,
            'board' => null,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function boardStore()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if (empty($name)) {
            $this->json(['error' => 'Nome da banca é obrigatório'], 400);
        }
        if (empty($slug)) {
            $slug = $this->slugify($name);
        }
        if ($this->boardModel->slugExists($slug)) {
            $this->json(['error' => 'Já existe uma banca com este identificador'], 400);
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $this->boardModel->create(['name' => $name, 'slug' => $slug, 'is_active' => $isActive]);
        $this->json(['success' => true, 'message' => 'Banca cadastrada com sucesso']);
    }

    public function boardEdit($id)
    {
        $user = $this->authManager->getUser();
        $board = $this->boardModel->findById($id);
        if (!$board) {
            $this->setFlashMessage('Banca não encontrada', 'error');
            $this->redirect('/admin/redacao-configuravel');
        }
        $this->viewWithLayout('admin', 'admin/essays/board_form', [
            'title' => 'Editar Banca - Redação Configurável',
            'user' => $user,
            'board' => $board,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function boardUpdate($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $board = $this->boardModel->findById($id);
        if (!$board) {
            $this->json(['error' => 'Banca não encontrada'], 404);
        }
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if (empty($name)) {
            $this->json(['error' => 'Nome da banca é obrigatório'], 400);
        }
        if (empty($slug)) {
            $slug = $this->slugify($name);
        }
        if ($this->boardModel->slugExists($slug, (int) $id)) {
            $this->json(['error' => 'Já existe outra banca com este identificador'], 400);
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $this->boardModel->update($id, ['name' => $name, 'slug' => $slug, 'is_active' => $isActive]);
        $this->json(['success' => true, 'message' => 'Banca atualizada com sucesso']);
    }

    public function boardDelete($id)
    {
        $board = $this->boardModel->findById($id);
        if (!$board) {
            $this->json(['error' => 'Banca não encontrada'], 404);
        }
        $this->boardModel->delete($id);
        $this->json(['success' => true, 'message' => 'Banca excluída com sucesso']);
    }

    public function boardToggleStatus($id)
    {
        $board = $this->boardModel->findById($id);
        if (!$board) {
            $this->json(['error' => 'Banca não encontrada'], 404);
        }
        $newStatus = $board['is_active'] ? 0 : 1;
        $this->boardModel->update($id, ['name' => $board['name'], 'slug' => $board['slug'], 'is_active' => $newStatus]);
        $this->json(['success' => true, 'is_active' => (bool) $newStatus, 'message' => $newStatus ? 'Banca ativada' : 'Banca desativada']);
    }

    // ---------- Text Types (por Banca) ----------
    public function textTypesIndex($boardId)
    {
        $user = $this->authManager->getUser();
        $board = $this->boardModel->findById($boardId);
        if (!$board) {
            $this->setFlashMessage('Banca não encontrada', 'error');
            $this->redirect('/admin/redacao-configuravel');
        }
        $items = $this->textTypeModel->allByBoard($boardId);
        $this->viewWithLayout('admin', 'admin/essays/text_types_index', [
            'title' => 'Tipos Textuais - ' . $board['name'],
            'user' => $user,
            'board' => $board,
            'items' => $items,
            'current_page' => 'essays_config'
        ]);
    }

    public function textTypeCreate($boardId)
    {
        $user = $this->authManager->getUser();
        $board = $this->boardModel->findById($boardId);
        if (!$board) {
            $this->setFlashMessage('Banca não encontrada', 'error');
            $this->redirect('/admin/redacao-configuravel');
        }
        $this->viewWithLayout('admin', 'admin/essays/text_type_form', [
            'title' => 'Novo Tipo Textual - ' . $board['name'],
            'user' => $user,
            'board' => $board,
            'item' => null,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function textTypeStore($boardId)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $board = $this->boardModel->findById($boardId);
        if (!$board) {
            $this->json(['error' => 'Banca não encontrada'], 404);
        }
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (empty($name)) {
            $this->json(['error' => 'Nome do tipo textual é obrigatório'], 400);
        }
        if (empty($slug)) {
            $slug = $this->slugify($name);
        }
        if ($this->textTypeModel->slugExistsForBoard($boardId, $slug)) {
            $this->json(['error' => 'Já existe um tipo textual com este identificador nesta banca'], 400);
        }
        $this->textTypeModel->create(['board_id' => $boardId, 'name' => $name, 'slug' => $slug, 'description' => $description ?: null]);
        $this->json(['success' => true, 'message' => 'Tipo textual cadastrado com sucesso']);
    }

    public function textTypeEdit($boardId, $id)
    {
        $user = $this->authManager->getUser();
        $board = $this->boardModel->findById($boardId);
        $item = $this->textTypeModel->findById($id);
        if (!$board || !$item || (int) $item['board_id'] !== (int) $boardId) {
            $this->setFlashMessage('Tipo textual não encontrado', 'error');
            $this->redirect('/admin/redacao-configuravel/bancas/' . $boardId . '/tipos');
        }
        $this->viewWithLayout('admin', 'admin/essays/text_type_form', [
            'title' => 'Editar Tipo Textual - ' . $board['name'],
            'user' => $user,
            'board' => $board,
            'item' => $item,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function textTypeUpdate($boardId, $id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $item = $this->textTypeModel->findById($id);
        if (!$item || (int) $item['board_id'] !== (int) $boardId) {
            $this->json(['error' => 'Tipo textual não encontrado'], 404);
        }
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (empty($name)) {
            $this->json(['error' => 'Nome do tipo textual é obrigatório'], 400);
        }
        if (empty($slug)) {
            $slug = $this->slugify($name);
        }
        if ($this->textTypeModel->slugExistsForBoard($boardId, $slug, (int) $id)) {
            $this->json(['error' => 'Já existe outro tipo textual com este identificador'], 400);
        }
        $this->textTypeModel->update($id, ['board_id' => $boardId, 'name' => $name, 'slug' => $slug, 'description' => $description ?: null]);
        $this->json(['success' => true, 'message' => 'Tipo textual atualizado com sucesso']);
    }

    public function textTypeDelete($boardId, $id)
    {
        $item = $this->textTypeModel->findById($id);
        if (!$item || (int) $item['board_id'] !== (int) $boardId) {
            $this->json(['error' => 'Tipo textual não encontrado'], 404);
        }
        $this->textTypeModel->delete($id);
        $this->json(['success' => true, 'message' => 'Tipo textual excluído com sucesso']);
    }

    // ---------- Criteria (por Tipo Textual) ----------
    public function criteriaIndex($boardId, $textTypeId)
    {
        $user = $this->authManager->getUser();
        $board = $this->boardModel->findById($boardId);
        $textType = $this->textTypeModel->findById($textTypeId);
        if (!$board || !$textType || (int) $textType['board_id'] !== (int) $boardId) {
            $this->setFlashMessage('Tipo textual não encontrado', 'error');
            $this->redirect('/admin/redacao-configuravel');
        }
        $items = $this->criterionModel->allByTextType($textTypeId);
        $this->viewWithLayout('admin', 'admin/essays/criteria_index', [
            'title' => 'Critérios - ' . $textType['name'],
            'user' => $user,
            'board' => $board,
            'textType' => $textType,
            'items' => $items,
            'current_page' => 'essays_config'
        ]);
    }

    public function criterionCreate($boardId, $textTypeId)
    {
        $user = $this->authManager->getUser();
        $board = $this->boardModel->findById($boardId);
        $textType = $this->textTypeModel->findById($textTypeId);
        if (!$board || !$textType || (int) $textType['board_id'] !== (int) $boardId) {
            $this->setFlashMessage('Tipo textual não encontrado', 'error');
            $this->redirect('/admin/redacao-configuravel');
        }
        $this->viewWithLayout('admin', 'admin/essays/criterion_form', [
            'title' => 'Novo Critério - ' . $textType['name'],
            'user' => $user,
            'board' => $board,
            'textType' => $textType,
            'item' => null,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function criterionStore($boardId, $textTypeId)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $textType = $this->textTypeModel->findById($textTypeId);
        if (!$textType || (int) $textType['board_id'] !== (int) $boardId) {
            $this->json(['error' => 'Tipo textual não encontrado'], 404);
        }
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $maxScore = isset($_POST['max_score']) ? (float) $_POST['max_score'] : 200.0;
        $orderPosition = isset($_POST['order_position']) ? (int) $_POST['order_position'] : 0;
        if (empty($name)) {
            $this->json(['error' => 'Nome do critério é obrigatório'], 400);
        }
        if (empty($slug)) {
            $slug = $this->slugify($name);
        }
        $this->criterionModel->create([
            'text_type_id' => $textTypeId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description ?: null,
            'max_score' => $maxScore,
            'order_position' => $orderPosition
        ]);
        $this->json(['success' => true, 'message' => 'Critério cadastrado com sucesso']);
    }

    public function criterionEdit($boardId, $textTypeId, $id)
    {
        $user = $this->authManager->getUser();
        $board = $this->boardModel->findById($boardId);
        $textType = $this->textTypeModel->findById($textTypeId);
        $item = $this->criterionModel->findById($id);
        if (!$board || !$textType || !$item || (int) $textType['board_id'] !== (int) $boardId || (int) $item['text_type_id'] !== (int) $textTypeId) {
            $this->setFlashMessage('Critério não encontrado', 'error');
            $this->redirect('/admin/redacao-configuravel');
        }
        $this->viewWithLayout('admin', 'admin/essays/criterion_form', [
            'title' => 'Editar Critério - ' . $textType['name'],
            'user' => $user,
            'board' => $board,
            'textType' => $textType,
            'item' => $item,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function criterionUpdate($boardId, $textTypeId, $id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $item = $this->criterionModel->findById($id);
        if (!$item || (int) $item['text_type_id'] !== (int) $textTypeId) {
            $this->json(['error' => 'Critério não encontrado'], 404);
        }
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $maxScore = isset($_POST['max_score']) ? (float) $_POST['max_score'] : 200.0;
        $orderPosition = isset($_POST['order_position']) ? (int) $_POST['order_position'] : 0;
        if (empty($name)) {
            $this->json(['error' => 'Nome do critério é obrigatório'], 400);
        }
        if (empty($slug)) {
            $slug = $this->slugify($name);
        }
        $this->criterionModel->update($id, [
            'text_type_id' => $textTypeId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description ?: null,
            'max_score' => $maxScore,
            'order_position' => $orderPosition
        ]);
        $this->json(['success' => true, 'message' => 'Critério atualizado com sucesso']);
    }

    public function criterionDelete($boardId, $textTypeId, $id)
    {
        $item = $this->criterionModel->findById($id);
        if (!$item || (int) $item['text_type_id'] !== (int) $textTypeId) {
            $this->json(['error' => 'Critério não encontrado'], 404);
        }
        $this->criterionModel->delete($id);
        $this->json(['success' => true, 'message' => 'Critério excluído com sucesso']);
    }

    // ---------- Prompts (por Banca + Tipo Textual) ----------
    public function promptsIndex($boardId, $textTypeId)
    {
        $user = $this->authManager->getUser();
        $board = $this->boardModel->findById($boardId);
        $textType = $this->textTypeModel->findById($textTypeId);
        if (!$board || !$textType || (int) $textType['board_id'] !== (int) $boardId) {
            $this->setFlashMessage('Tipo textual não encontrado', 'error');
            $this->redirect('/admin/redacao-configuravel');
        }
        $items = $this->promptModel->allByBoardAndTextType($boardId, $textTypeId);
        $activePrompt = $this->promptModel->getActiveForBoardAndTextType($boardId, $textTypeId);
        $this->viewWithLayout('admin', 'admin/essays/prompts_index', [
            'title' => 'Prompts de Correção - ' . $textType['name'],
            'user' => $user,
            'board' => $board,
            'textType' => $textType,
            'items' => $items,
            'activePrompt' => $activePrompt,
            'current_page' => 'essays_config'
        ]);
    }

    public function promptCreate($boardId, $textTypeId)
    {
        $user = $this->authManager->getUser();
        $board = $this->boardModel->findById($boardId);
        $textType = $this->textTypeModel->findById($textTypeId);
        if (!$board || !$textType || (int) $textType['board_id'] !== (int) $boardId) {
            $this->setFlashMessage('Tipo textual não encontrado', 'error');
            $this->redirect('/admin/redacao-configuravel');
        }
        $nextVersion = $this->promptModel->getNextVersion($boardId, $textTypeId);
        $this->viewWithLayout('admin', 'admin/essays/prompt_form', [
            'title' => 'Novo Prompt de Correção - ' . $textType['name'],
            'user' => $user,
            'board' => $board,
            'textType' => $textType,
            'item' => null,
            'nextVersion' => $nextVersion,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function promptStore($boardId, $textTypeId)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $textType = $this->textTypeModel->findById($textTypeId);
        if (!$textType || (int) $textType['board_id'] !== (int) $boardId) {
            $this->json(['error' => 'Tipo textual não encontrado'], 404);
        }
        $promptText = trim($_POST['prompt_text'] ?? '');
        if (empty($promptText)) {
            $this->json(['error' => 'Texto do prompt é obrigatório'], 400);
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $this->promptModel->create([
            'board_id' => $boardId,
            'text_type_id' => $textTypeId,
            'prompt_text' => $promptText,
            'is_active' => $isActive
        ]);
        $this->json(['success' => true, 'message' => 'Prompt cadastrado com sucesso']);
    }

    public function promptEdit($boardId, $textTypeId, $id)
    {
        $user = $this->authManager->getUser();
        $board = $this->boardModel->findById($boardId);
        $textType = $this->textTypeModel->findById($textTypeId);
        $item = $this->promptModel->findById($id);
        if (!$board || !$textType || !$item || (int) $item['board_id'] !== (int) $boardId || (int) $item['text_type_id'] !== (int) $textTypeId) {
            $this->setFlashMessage('Prompt não encontrado', 'error');
            $this->redirect('/admin/redacao-configuravel');
        }
        $this->viewWithLayout('admin', 'admin/essays/prompt_form', [
            'title' => 'Editar Prompt - ' . $textType['name'],
            'user' => $user,
            'board' => $board,
            'textType' => $textType,
            'item' => $item,
            'nextVersion' => (int) $item['version'],
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    public function promptUpdate($boardId, $textTypeId, $id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $item = $this->promptModel->findById($id);
        if (!$item || (int) $item['board_id'] !== (int) $boardId || (int) $item['text_type_id'] !== (int) $textTypeId) {
            $this->json(['error' => 'Prompt não encontrado'], 404);
        }
        $promptText = trim($_POST['prompt_text'] ?? '');
        if (empty($promptText)) {
            $this->json(['error' => 'Texto do prompt é obrigatório'], 400);
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $this->promptModel->update($id, ['prompt_text' => $promptText, 'is_active' => $isActive]);
        $this->json(['success' => true, 'message' => 'Prompt atualizado com sucesso']);
    }

    public function promptDelete($boardId, $textTypeId, $id)
    {
        $item = $this->promptModel->findById($id);
        if (!$item || (int) $item['board_id'] !== (int) $boardId || (int) $item['text_type_id'] !== (int) $textTypeId) {
            $this->json(['error' => 'Prompt não encontrado'], 404);
        }
        $this->promptModel->delete($id);
        $this->json(['success' => true, 'message' => 'Prompt excluído com sucesso']);
    }

    public function promptSetActive($boardId, $textTypeId, $id)
    {
        $item = $this->promptModel->findById($id);
        if (!$item || (int) $item['board_id'] !== (int) $boardId || (int) $item['text_type_id'] !== (int) $textTypeId) {
            $this->json(['error' => 'Prompt não encontrado'], 404);
        }
        $this->promptModel->setActive($id);
        $this->json(['success' => true, 'message' => 'Prompt definido como ativo']);
    }

    private function slugify($text)
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = strtolower($text);
        return $text ?: 'item';
    }
}
}
