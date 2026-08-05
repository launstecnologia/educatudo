<?php

require_once 'app/Core/BaseController.php';
require_once 'app/Models/Education/OnlineClass.php';
require_once 'app/Services/PandaVideoLiveService.php';
require_once 'app/Services/JaasMeetService.php';

if (!class_exists('StudentOnlineClassController')) {
class StudentOnlineClassController extends BaseController
{
    private $auth;
    private $db;
    private $onlineClass;
    private $pandaLive;
    private $jaasMeet;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->onlineClass = new OnlineClass();
        $this->pandaLive = new PandaVideoLiveService();
        $this->jaasMeet = new JaasMeetService();

        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }

        $user = $this->auth->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
    }

    public function index(): void
    {
        $aluno = $this->getAlunoFromSession();
        if (!$aluno) {
            $this->setFlashMessage('Aluno não encontrado.', 'error');
            $this->redirect('/dashboard');
            return;
        }

        if (empty($aluno['turma_id'])) {
            $this->setFlashMessage('Seu cadastro não possui turma vinculada.', 'error');
            $this->redirect('/dashboard');
            return;
        }

        $allTurmaIds = $this->getAllTurmaIdsForAluno((int) $aluno['id'], (int) $aluno['turma_id']);
        $aulas = $this->onlineClass->listForAlunoAllTurmas((int) $aluno['id'], $allTurmaIds);

        $this->viewWithLayout('student', 'student/aulas-online/index', [
            'title' => 'Aulas Online',
            'current_page' => 'aulas_online',
            'user' => $this->auth->getUser(),
            'aulas' => $aulas,
            'aluno' => $aluno,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function show(int $id): void
    {
        $aluno = $this->getAlunoFromSession();
        if (!$aluno || empty($aluno['turma_id'])) {
            $this->setFlashMessage('Aluno inválido ou sem turma vinculada.', 'error');
            $this->redirect('/aluno/aulas-online');
            return;
        }

        $allTurmaIds = $this->getAllTurmaIdsForAluno((int) $aluno['id'], (int) $aluno['turma_id']);
        if (!$this->onlineClass->alunoCanAccessAulaInAnyTurma($id, $allTurmaIds)) {
            $this->setFlashMessage('Você não tem acesso a esta aula.', 'error');
            $this->redirect('/aluno/aulas-online');
            return;
        }

        $aula = $this->onlineClass->getById($id);
        if (!$aula) {
            $this->setFlashMessage('Aula não encontrada.', 'error');
            $this->redirect('/aluno/aulas-online');
            return;
        }

        $aula = $this->syncPandaRecordingIfNeeded($aula);

        $this->viewWithLayout('student', 'student/aulas-online/show', [
            'title' => 'Aula Online',
            'current_page' => 'aulas_online',
            'user' => $this->auth->getUser(),
            'aula' => $aula,
            'aluno' => $aluno,
            'jaas_meeting_url' => $this->buildAlunoJaasMeetingUrl($aula, $aluno),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function mensagens(): void
    {
        $aluno = $this->getAlunoFromSession();
        if (!$aluno || empty($aluno['turma_id'])) {
            $this->json(['success' => false, 'error' => 'Aluno inválido'], 403);
            return;
        }

        $aulaId = (int) ($_GET['aula_id'] ?? 0);
        $afterId = (int) ($_GET['after_id'] ?? 0);
        if ($aulaId <= 0) {
            $this->json(['success' => false, 'error' => 'Aula inválida'], 400);
            return;
        }

        $allTurmaIds = $this->getAllTurmaIdsForAluno((int) $aluno['id'], (int) $aluno['turma_id']);
        if (!$this->onlineClass->alunoCanAccessAulaInAnyTurma($aulaId, $allTurmaIds)) {
            $this->json(['success' => false, 'error' => 'Sem acesso à aula'], 403);
            return;
        }

        $rows = $this->onlineClass->listChatMessages($aulaId, $afterId, 120);
        $this->json(['success' => true, 'messages' => $rows]);
    }

    public function enviarMensagem(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
            return;
        }

        $aluno = $this->getAlunoFromSession();
        if (!$aluno || empty($aluno['turma_id'])) {
            $this->json(['success' => false, 'error' => 'Aluno inválido'], 403);
            return;
        }

        $aulaId = (int) ($_POST['aula_id'] ?? 0);
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if ($aulaId <= 0 || $mensagem === '') {
            $this->json(['success' => false, 'error' => 'Dados inválidos'], 400);
            return;
        }

        $allTurmaIds = $this->getAllTurmaIdsForAluno((int) $aluno['id'], (int) $aluno['turma_id']);
        if (!$this->onlineClass->alunoCanAccessAulaInAnyTurma($aulaId, $allTurmaIds)) {
            $this->json(['success' => false, 'error' => 'Sem acesso à aula'], 403);
            return;
        }

        $id = $this->onlineClass->addChatMessage(
            $aulaId,
            (int) $aluno['id'],
            'aluno',
            (string) ($aluno['nome'] ?? 'Aluno'),
            $mensagem
        );

        $this->json(['success' => true, 'id' => $id]);
    }

    public function gravacao(int $id): void
    {
        $aluno = $this->getAlunoFromSession();
        if (!$aluno || empty($aluno['turma_id'])) {
            http_response_code(403);
            exit;
        }

        $allTurmaIds = $this->getAllTurmaIdsForAluno((int) $aluno['id'], (int) $aluno['turma_id']);
        if (!$this->onlineClass->alunoCanAccessAulaInAnyTurma($id, $allTurmaIds)) {
            http_response_code(403);
            exit;
        }

        $aula = $this->onlineClass->getById($id);
        $relativePath = trim((string) ($aula['jaas_recording_path'] ?? ''));
        if ($relativePath === '' || strpos($relativePath, '..') !== false) {
            http_response_code(404);
            exit;
        }

        $path = __DIR__ . '/../../../storage/' . trim(str_replace('\\', '/', $relativePath), '/');
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: video/mp4');
        header('Content-Disposition: inline; filename="' . addslashes(basename($path)) . '"');
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=3600');
        $size = filesize($path);
        $start = 0;
        $end = $size > 0 ? $size - 1 : 0;
        if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] !== '') {
                $start = max(0, (int) $m[1]);
            }
            if ($m[2] !== '') {
                $end = min($end, (int) $m[2]);
            }
            if ($start <= $end) {
                http_response_code(206);
                header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            }
        }
        header('Content-Length: ' . (($end - $start) + 1));

        $fp = fopen($path, 'rb');
        if ($fp === false) {
            http_response_code(500);
            exit;
        }
        fseek($fp, $start);
        $remaining = ($end - $start) + 1;
        while ($remaining > 0 && !feof($fp)) {
            $chunkSize = min(8192, $remaining);
            echo fread($fp, $chunkSize);
            $remaining -= $chunkSize;
            flush();
        }
        fclose($fp);
        exit;
    }

    private function syncPandaRecordingIfNeeded(array $aula): array
    {
        $fimTs = !empty($aula['fim_em']) ? strtotime((string) $aula['fim_em']) : false;
        $ended = $fimTs !== false && time() > $fimTs;
        $liveId = trim((string) ($aula['panda_live_id'] ?? ''));
        $hasRecording = trim((string) ($aula['panda_recording_player'] ?? '')) !== ''
            || trim((string) ($aula['panda_recording_hls'] ?? '')) !== '';

        if (!$ended || $liveId === '' || $hasRecording || !$this->pandaLive->isConfigured()) {
            return $aula;
        }

        try {
            $recording = $this->pandaLive->fetchLiveRecording($liveId);
            if ($recording !== []) {
                $this->onlineClass->updatePandaRecording((int) ($aula['id'] ?? 0), $recording);
                $updated = $this->onlineClass->getById((int) ($aula['id'] ?? 0));
                return $updated ?: $aula;
            }
        } catch (Throwable $e) {
            return $aula;
        }

        return $aula;
    }

    private function buildAlunoJaasMeetingUrl(array $aula, array $aluno): string
    {
        if (mb_strtolower((string) ($aula['plataforma'] ?? ''), 'UTF-8') !== 'jitsi meet') {
            return '';
        }

        try {
            return $this->jaasMeet->meetingUrl(
                (int) ($aula['id'] ?? 0),
                (string) ($aula['titulo'] ?? ''),
                $aluno,
                false
            );
        } catch (Throwable $e) {
            return '';
        }
    }

    private function getAlunoFromSession(): ?array
    {
        $user = $this->auth->getUser();
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $row = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id",
            ['id' => $userId]
        );

        return $row ?: null;
    }

    /**
     * Retorna todos os IDs de turma em que o aluno está com matrícula ativa.
     * Inclui sempre o turma_id principal do aluno como fallback.
     * @return int[]
     */
    private function getAllTurmaIdsForAluno(int $alunoId, int $primaryTurmaId): array
    {
        $ids = [];
        try {
            $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
            if ($hasMatricula) {
                $rows = $this->db->fetchAll(
                    "SELECT m.turma_id FROM matricula m WHERE m.aluno_id = :aluno_id AND m.status = 'ativa'",
                    ['aluno_id' => $alunoId]
                );
                foreach ($rows as $r) {
                    $tid = (int) $r['turma_id'];
                    if ($tid > 0) {
                        $ids[] = $tid;
                    }
                }
            }
        } catch (Throwable $e) {
            // fallback ao primary abaixo
        }
        if ($primaryTurmaId > 0 && !in_array($primaryTurmaId, $ids, true)) {
            $ids[] = $primaryTurmaId;
        }
        return array_unique($ids);
    }
}
}
