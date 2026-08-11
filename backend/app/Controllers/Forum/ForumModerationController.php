<?php
/**
 * Forum EducaTudo: moderação (apenas admin).
 * Excluir tópico/resposta, listar e resolver denúncias.
 */
require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Models/Forum/ForumTopic.php';
require_once __DIR__ . '/../../Models/Forum/ForumReply.php';
require_once __DIR__ . '/../../Models/Forum/ForumReport.php';
require_once __DIR__ . '/../../Models/Forum/ForumModerationAlert.php';
require_once __DIR__ . '/../../Services/ForumSecurityService.php';

class ForumModerationController extends BaseController
{
    private $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
    }

    private function requireAdmin()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            header('Location: ' . URL . '/login');
            exit;
        }
        $role = ForumSecurityService::userRole($user['tipo']);
        if (!ForumSecurityService::canModerate($role)) {
            $_SESSION['forum_error'] = 'Acesso negado. Apenas administradores podem moderar.';
            header('Location: ' . URL . '/forum');
            exit;
        }
        return $user;
    }

    /**
     * Listar denúncias pendentes.
     */
    public function reports()
    {
        $this->requireAdmin();
        $reportModel = new ForumReport();
        $reports = $reportModel->listPending();
        $alertModel = new ForumModerationAlert();
        $alertsCount = count($alertModel->listPending());
        $this->viewWithLayout('admin', 'admin/forum/reports', [
            'title' => 'Denúncias do Fórum - EducaTudo',
            'user' => $this->auth->getUser(),
            'reports' => $reports,
            'alerts_count' => $alertsCount,
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    /**
     * Alertas da IA: conteúdo bloqueado (fora de contexto ou inadequado).
     */
    public function alerts()
    {
        $this->requireAdmin();
        $alertModel = new ForumModerationAlert();
        $alerts = $alertModel->listPending();
        $this->viewWithLayout('admin', 'admin/forum/alerts', [
            'title' => 'Alertas de moderação (IA) - Fórum EducaTudo',
            'user' => $this->auth->getUser(),
            'alerts' => $alerts,
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    /**
     * Marcar alerta como visto.
     */
    public function markAlertSeen()
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL . '/forum/moderation/alerts');
            exit;
        }
        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            header('Location: ' . URL . '/forum/moderation/alerts');
            exit;
        }
        $id = (int) ($_POST['alert_id'] ?? 0);
        if ($id > 0) {
            $alertModel = new ForumModerationAlert();
            $alertModel->markAsVisto($id);
        }
        header('Location: ' . URL . '/forum/moderation/alerts');
        exit;
    }

    /**
     * Resolver denúncia: aprovar (remove conteúdo) ou rejeitar.
     */
    public function resolveReport()
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL . '/forum/moderation/reports');
            exit;
        }
        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            $_SESSION['forum_error'] = 'Requisição inválida.';
            header('Location: ' . URL . '/forum/moderation/reports');
            exit;
        }
        $reportId = (int) ($_POST['report_id'] ?? 0);
        $status = $_POST['status'] === 'rejected' ? 'rejected' : 'approved';
        $reportModel = new ForumReport();
        $report = $reportModel->getById($reportId);
        if (!$report) {
            header('Location: ' . URL . '/forum/moderation/reports');
            exit;
        }
        $reportModel->updateStatus($reportId, $status);
        if ($status === 'approved') {
            if ($report['reply_id']) {
                $replyModel = new ForumReply();
                $replyModel->delete($report['reply_id']);
            }
            if ($report['topic_id']) {
                $topicModel = new ForumTopic();
                $topicModel->delete($report['topic_id']);
            }
        }
        $_SESSION['forum_success'] = 'Denúncia processada.';
        header('Location: ' . URL . '/forum/moderation/reports');
        exit;
    }

    /**
     * Excluir tópico (e respostas).
     */
    public function deleteTopic()
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL . '/forum');
            exit;
        }
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        if ($topicId < 1) {
            header('Location: ' . URL . '/forum');
            exit;
        }
        $topicModel = new ForumTopic();
        $topicModel->delete($topicId);
        $_SESSION['forum_success'] = 'Tópico excluído.';
        header('Location: ' . URL . '/forum');
        exit;
    }

    /**
     * Excluir resposta.
     */
    public function deleteReply()
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL . '/forum');
            exit;
        }
        $replyId = (int) ($_POST['reply_id'] ?? 0);
        if ($replyId < 1) {
            header('Location: ' . URL . '/forum');
            exit;
        }
        $replyModel = new ForumReply();
        $reply = $replyModel->getById($replyId);
        $topicId = $reply ? (int) $reply['topic_id'] : 0;
        $replyModel->delete($replyId);
        $_SESSION['forum_success'] = 'Resposta excluída.';
        header('Location: ' . URL . '/forum' . ($topicId ? '/' . $topicId : ''));
        exit;
    }
}
