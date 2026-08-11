<?php

require_once __DIR__ . '/../../Services/AvaCourseService.php';
require_once __DIR__ . '/../../Services/AvaProgressService.php';
require_once __DIR__ . '/../../Services/AvaActivityService.php';
require_once __DIR__ . '/../../Services/AvaAgendaService.php';
require_once __DIR__ . '/../../Services/AvaEvaluationService.php';
require_once __DIR__ . '/../../Models/Ava/DisciplineEnrollment.php';
require_once __DIR__ . '/../../Models/Ava/LessonComment.php';

/**
 * EducaTudo - AVA: área do aluno (meus cursos, disciplina e player com progresso).
 */
if (!class_exists('AvaStudentController')) {
class AvaStudentController extends BaseController
{
    private $authManager;
    private $db;
    private AvaCourseService $service;
    private AvaProgressService $progress;
    private DisciplineEnrollment $enrollments;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirect('/');
        }
        $this->service = new AvaCourseService();
        $this->progress = new AvaProgressService();
        $this->enrollments = new DisciplineEnrollment();
    }

    private function alunoId(): int
    {
        $user = $this->authManager->getUser();
        return (int) ($user['id'] ?? 0);
    }

    public function index(): void
    {
        $resumo = $this->progress->studentSummary($this->alunoId());
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('student', 'student/ava/index', [
            'title' => 'Meus Cursos - EducaTudo',
            'current_page' => 'cursos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'resumo' => $resumo,
        ]);
    }

    public function discipline(string $id): void
    {
        $disciplinaId = (int) $id;
        if (!$this->enrollments->isEnrolled($this->alunoId(), $disciplinaId)) {
            $this->setFlashMessage('Você não está matriculado nesta disciplina.', 'error');
            $this->redirect('/cursos');
            return;
        }
        $disc = $this->service->disciplinesModel()->find($disciplinaId);
        $outline = $this->service->disciplineOutline($disciplinaId);
        $aulaIds = [];
        foreach ($outline as $m) {
            foreach ($m['aulas'] as $a) {
                $aulaIds[] = (int) $a['id'];
            }
        }
        $progressoMap = (new LessonProgress())->mapByLessons($this->alunoId(), $aulaIds);
        $matricula = $this->enrollments->find($this->alunoId(), $disciplinaId);
        $freq = $this->progress->eadFrequency($this->alunoId(), $disciplinaId);
        $avaliacoes = (new AvaEvaluationService())->studentView($this->alunoId(), $disciplinaId);
        $this->viewWithLayout('student', 'student/ava/disciplina', [
            'title' => htmlspecialchars($disc['nome']) . ' - EducaTudo',
            'current_page' => 'cursos',
            'disciplina' => $disc,
            'outline' => $outline,
            'progresso_map' => $progressoMap,
            'matricula' => $matricula,
            'frequencia' => $freq,
            'avaliacoes' => $avaliacoes,
        ]);
    }

    public function agenda(): void
    {
        $eventos = (new AvaAgendaService())->studentEvents($this->alunoId());
        $split = (new AvaAgendaService())->split($eventos);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('student', 'student/ava/agenda', [
            'title' => 'Agenda - EducaTudo',
            'current_page' => 'cursos-agenda',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'proximos' => $split['proximos'],
            'passados' => $split['passados'],
        ]);
    }

    public function player(string $id): void
    {
        $aula = $this->service->lessonsModel()->find((int) $id);
        if (!$aula) {
            $this->setFlashMessage('Aula não encontrada.', 'error');
            $this->redirect('/cursos');
            return;
        }
        $disciplinaId = (int) $aula['disciplina_id'];
        if (!$this->enrollments->isEnrolled($this->alunoId(), $disciplinaId)) {
            $this->setFlashMessage('Você não está matriculado nesta disciplina.', 'error');
            $this->redirect('/cursos');
            return;
        }
        if (!$this->aulaLiberada($aula)) {
            $this->setFlashMessage('Esta aula ainda não está disponível.', 'info');
            $this->redirect('/cursos/disciplina/' . $disciplinaId);
            return;
        }

        $flat = $this->service->flatLessons($disciplinaId);
        $prev = null;
        $next = null;
        foreach ($flat as $i => $a) {
            if ((int) $a['id'] === (int) $aula['id']) {
                $prev = $flat[$i - 1] ?? null;
                $next = $flat[$i + 1] ?? null;
                break;
            }
        }

        $progressoVideo = (new LessonProgress())->findVideo($this->alunoId(), (int) $aula['id']);
        $this->viewWithLayout('student', 'student/ava/player', [
            'title' => htmlspecialchars($aula['titulo']) . ' - EducaTudo',
            'current_page' => 'cursos',
            'aula' => $aula,
            'embed' => $this->buildEmbed((string) $aula['video_provider'], (string) ($aula['video_ref'] ?? '')),
            'anexos' => $this->service->lessonsModel()->attachments((int) $aula['id']),
            'atividades' => (new AvaActivityService())->activitiesModel()->byLesson((int) $aula['id']),
            'comentarios' => (new LessonComment())->byLesson((int) $aula['id']),
            'prev' => $prev,
            'next' => $next,
            'progresso_video' => $progressoVideo,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function complete(string $id): void
    {
        $aula = $this->service->lessonsModel()->find((int) $id);
        if (!$aula) {
            $this->redirect('/cursos');
            return;
        }
        $disciplinaId = (int) $aula['disciplina_id'];
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? '')) || !$this->enrollments->isEnrolled($this->alunoId(), $disciplinaId)) {
            $this->redirect('/cursos/disciplina/' . $disciplinaId);
            return;
        }
        $this->progress->completeLesson($this->alunoId(), (int) $id);
        $proximo = (int) ($_POST['proximo'] ?? 0);
        if ($proximo > 0) {
            $this->redirect('/cursos/aula/' . $proximo);
            return;
        }
        $this->setFlashMessage('Aula concluída!', 'success');
        $this->redirect('/cursos/disciplina/' . $disciplinaId);
    }

    public function saveVideoProgress(string $id): void
    {
        $aula = $this->service->lessonsModel()->find((int) $id);
        if (!$aula) {
            $this->json(['ok' => false, 'erro' => 'Aula não encontrada'], 404);
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->json(['ok' => false, 'erro' => 'CSRF inválido'], 403);
            return;
        }
        if (!$this->enrollments->isEnrolled($this->alunoId(), (int) $aula['disciplina_id'])) {
            $this->json(['ok' => false, 'erro' => 'Não matriculado'], 403);
            return;
        }
        $res = $this->progress->saveVideoProgress(
            $this->alunoId(),
            (int) $id,
            (int) ($_POST['segundo_atual'] ?? 0),
            (int) ($_POST['tempo_assistido'] ?? 0),
            (int) ($_POST['duracao'] ?? 0)
        );
        $this->json($res);
    }

    public function anexo(string $id): void
    {
        $anexo = $this->service->lessonsModel()->findAttachment((int) $id);
        if (!$anexo) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            return;
        }
        if (!empty($anexo['url'])) {
            $this->redirect((string) $anexo['url']);
            return;
        }
        $aula = $this->service->lessonsModel()->find((int) $anexo['aula_id']);
        if (!$aula || !$this->enrollments->isEnrolled($this->alunoId(), (int) $aula['disciplina_id'])) {
            http_response_code(403);
            echo 'Acesso negado';
            return;
        }
        if (empty($anexo['arquivo_key'])) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $conteudo = (new MediaStorageService($this->config))->getContents('arquivos', (string) $anexo['arquivo_key']);
        if ($conteudo === null) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: ' . (string) ($anexo['mime'] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) ($anexo['nome'] ?? 'material')) . '"');
            header('Content-Length: ' . strlen($conteudo));
        }
        echo $conteudo;
    }

    // ---- Helpers ----------------------------------------------------------

    private function aulaLiberada(array $aula): bool
    {
        $agora = time();
        if (!empty($aula['data_liberacao']) && strtotime((string) $aula['data_liberacao']) > $agora) {
            return false;
        }
        if (!empty($aula['data_encerramento']) && strtotime((string) $aula['data_encerramento']) < $agora) {
            return false;
        }
        return true;
    }

    /** @return array{type:string,src:string} */
    private function buildEmbed(string $provider, string $ref): array
    {
        $ref = trim($ref);
        if ($ref === '' || $provider === 'none') {
            return ['type' => 'none', 'src' => ''];
        }
        switch ($provider) {
            case 'mp4':
                return ['type' => 'video', 'src' => $ref];
            case 'youtube':
                $vid = $this->youtubeId($ref);
                return ['type' => 'iframe', 'src' => 'https://www.youtube.com/embed/' . $vid];
            case 'vimeo':
                $vid = preg_replace('/[^0-9]/', '', $ref);
                if ($vid === '' && preg_match('#vimeo\.com/(\d+)#', $ref, $m)) {
                    $vid = $m[1];
                }
                return ['type' => 'iframe', 'src' => 'https://player.vimeo.com/video/' . $vid];
            case 'bunny':
            case 'cloudflare':
            case 'panda':
            default:
                return ['type' => 'iframe', 'src' => $ref];
        }
    }

    private function youtubeId(string $ref): string
    {
        if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_\-]{6,})#', $ref, $m)) {
            return $m[1];
        }
        return preg_replace('/[^A-Za-z0-9_\-]/', '', $ref) ?: '';
    }
}
}
