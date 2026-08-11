<?php
/**
 * Forum EducaTudo: listar, criar tópico, ver tópico, responder, votar, marcar melhor resposta.
 */
require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Models/Forum/ForumTopic.php';
require_once __DIR__ . '/../../Models/Forum/ForumReply.php';
require_once __DIR__ . '/../../Models/Forum/ForumAttachment.php';
require_once __DIR__ . '/../../Models/Forum/ForumVote.php';
require_once __DIR__ . '/../../Models/Forum/ForumModerationAlert.php';
require_once __DIR__ . '/../../Services/ForumSecurityService.php';
require_once __DIR__ . '/../../Services/OpenAIService.php';

class ForumController extends BaseController
{
    private $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
    }

    private function requireAuth()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            header('Location: ' . URL . '/login');
            exit;
        }
        return $user;
    }

    private function forumRole($tipo)
    {
        return ForumSecurityService::userRole($tipo);
    }

    private function layoutAndViewFolder($tipo)
    {
        if ($tipo === 'professor') {
            return ['layout' => 'teacher', 'viewFolder' => 'teacher'];
        }
        if ($tipo === 'admin' || $tipo === 'admin_escola') {
            return ['layout' => 'admin', 'viewFolder' => 'admin'];
        }
        return ['layout' => 'student', 'viewFolder' => 'student'];
    }

    private function getAuthorName($authorId, $authorRole)
    {
        $db = Database::getInstance();
        if ($authorRole === 'student') {
            $row = $db->fetch('SELECT nome FROM alunos WHERE id = :id', ['id' => (int) $authorId]);
            return $row ? $row['nome'] : 'Aluno';
        }
        if ($authorRole === 'teacher') {
            $row = $db->fetch('SELECT nome FROM professores WHERE id = :id', ['id' => (int) $authorId]);
            return $row ? $row['nome'] : 'Professor';
        }
        if ($authorRole === 'admin') {
            $row = $db->fetch('SELECT nome FROM usuarios WHERE id = :id', ['id' => (int) $authorId]);
            return $row ? $row['nome'] : 'Admin';
        }
        return 'Usuário';
    }

    private function getTurmasList()
    {
        try {
            return Database::getInstance()->fetchAll('SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome');
        } catch (Exception $e) {
            return [];
        }
    }

    public function index()
    {
        $user = $this->requireAuth();
        $topicModel = new ForumTopic();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $turmaIds = isset($_GET['turma_ids']) && is_array($_GET['turma_ids']) ? array_filter(array_map('intval', $_GET['turma_ids'])) : [];
        if (!empty($_GET['turma_id']) && !isset($_GET['turma_ids'])) {
            $turmaIds = [(int) $_GET['turma_id']];
        }
        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'is_resolved' => isset($_GET['resolved']) ? (string) $_GET['resolved'] : '',
            'turma_id' => !empty($turmaIds) && count($turmaIds) === 1 ? $turmaIds[0] : null,
            'turma_ids' => $turmaIds,
            'order' => $_GET['order'] ?? 'recent',
        ];
        $total = $topicModel->countAll($filters);
        $topics = $topicModel->listPaginated($filters, $perPage, ($page - 1) * $perPage);
        foreach ($topics as &$t) {
            $t['author_name'] = $this->getAuthorName($t['author_id'], $t['author_role']);
        }
        unset($t);
        $lv = $this->layoutAndViewFolder($user['tipo']);
        $this->viewWithLayout($lv['layout'], $lv['viewFolder'] . '/forum/index', [
            'title' => 'Fórum - EducaTudo',
            'user' => $user,
            'topics' => $topics,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'filters' => $filters,
            'turmas' => $this->getTurmasList(),
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    public function create()
    {
        $user = $this->requireAuth();
        $lv = $this->layoutAndViewFolder($user['tipo']);
        $this->viewWithLayout($lv['layout'], $lv['viewFolder'] . '/forum/create', [
            'title' => 'Nova pergunta - Fórum EducaTudo',
            'user' => $user,
            'turmas' => $this->getTurmasList(),
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    public function store()
    {
        $user = $this->requireAuth();
        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            $_SESSION['forum_error'] = 'Requisição inválida.';
            header('Location: ' . URL . '/forum/create');
            exit;
        }
        if (!ForumSecurityService::checkTopicRateLimit()) {
            $_SESSION['forum_error'] = 'Muitas perguntas em pouco tempo. Aguarde um minuto.';
            header('Location: ' . URL . '/forum/create');
            exit;
        }
        $title = ForumSecurityService::sanitizeHtml($_POST['title'] ?? '');
        $content = ForumSecurityService::sanitizeHtml($_POST['content'] ?? '');
        $turmaIds = isset($_POST['turma_ids']) && is_array($_POST['turma_ids']) ? array_filter(array_map('intval', $_POST['turma_ids'])) : [];
        if ($title === '') {
            $_SESSION['forum_error'] = 'Título é obrigatório.';
            header('Location: ' . URL . '/forum/create');
            exit;
        }
        $textoParaModeracao = $title . "\n\n" . $content;
        try {
            $openAI = new \App\Services\OpenAIService();
            $moderacao = $openAI->moderarConteudoForum($textoParaModeracao, true);
            if (!$moderacao['apropriado']) {
                $alertModel = new ForumModerationAlert();
                $alertModel->create('topic', null, null, $user['id'], $this->forumRole($user['tipo']), $textoParaModeracao, $moderacao['motivo'] ?: 'Conteúdo fora de contexto ou inadequado.');
                $_SESSION['forum_error'] = 'Sua pergunta não foi publicada. O conteúdo foi identificado como fora do contexto educacional ou inadequado. Um administrador foi notificado.';
                header('Location: ' . URL . '/forum/create');
                exit;
            }
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                \Logger::warning('ForumController::store moderação falhou', ['exception' => $e], 'forum');
            }
        }
        $topicModel = new ForumTopic();
        $topicId = $topicModel->create($title, $content, $user['id'], $this->forumRole($user['tipo']), null, null);
        if (!empty($turmaIds)) {
            $topicModel->setTopicTurmas($topicId, $turmaIds);
        }
        header('Location: ' . URL . '/forum/' . $topicId);
        exit;
    }

    public function show($id)
    {
        $user = $this->requireAuth();
        if (isset($_GET['r'])) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
        $topicModel = new ForumTopic();
        $replyModel = new ForumReply();
        $attachModel = new ForumAttachment();
        $voteModel = new ForumVote();
        $topic = $topicModel->getById($id);
        if (!$topic) {
            $_SESSION['forum_error'] = 'Tópico não encontrado.';
            header('Location: ' . URL . '/forum');
            exit;
        }
        $replies = $replyModel->listByTopicId($id);
        $replyIds = array_column($replies, 'id');
        $scores = $voteModel->getScoresByReplyIds($replyIds);
        $topicAttachments = $attachModel->listByTopicId($id);
        $replyAttachments = $attachModel->listByReplyIds($replyIds);
        foreach ($replies as &$r) {
            $r['author_name'] = $this->getAuthorName($r['author_id'], $r['author_role']);
            $r['vote_score'] = $scores[(int) $r['id']] ?? 0;
            $r['attachments'] = $replyAttachments[(int) $r['id']] ?? [];
        }
        unset($r);
        $topic['author_name'] = $this->getAuthorName($topic['author_id'], $topic['author_role']);
        $role = $this->forumRole($user['tipo']);
        $lv = $this->layoutAndViewFolder($user['tipo']);
        $this->viewWithLayout($lv['layout'], $lv['viewFolder'] . '/forum/show', [
            'title' => $topic['title'] . ' - Fórum EducaTudo',
            'user' => $user,
            'topic' => $topic,
            'replies' => $replies,
            'topic_attachments' => $topicAttachments,
            'can_mark_best' => ForumSecurityService::canMarkBestAnswer($role),
            'can_moderate' => ForumSecurityService::canModerate($role),
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    public function reply()
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL . '/forum');
            exit;
        }
        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            $_SESSION['forum_error'] = 'Requisição inválida.';
            $topicId = (int) ($_POST['topic_id'] ?? 0);
            $redirectUrl = $topicId > 0 ? (URL . '/forum/' . $topicId) : (URL . '/forum');
            header('Location: ' . $redirectUrl);
            exit;
        }
        if (!ForumSecurityService::checkReplyRateLimit()) {
            $_SESSION['forum_error'] = 'Muitas respostas em pouco tempo. Aguarde um minuto.';
            $topicId = (int) ($_POST['topic_id'] ?? 0);
            $redirectUrl = $topicId > 0 ? (URL . '/forum/' . $topicId) : (URL . '/forum');
            header('Location: ' . $redirectUrl);
            exit;
        }
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $content = ForumSecurityService::sanitizeHtml($_POST['content'] ?? '');
        if ($topicId < 1 || $content === '') {
            $_SESSION['forum_error'] = 'Tópico e conteúdo são obrigatórios.';
            header('Location: ' . URL . '/forum' . ($topicId ? '/' . $topicId : ''));
            exit;
        }
        $topicModel = new ForumTopic();
        $topic = $topicModel->getById($topicId);
        if (!$topic) {
            $_SESSION['forum_error'] = 'Tópico não encontrado.';
            header('Location: ' . URL . '/forum');
            exit;
        }
        try {
            $openAI = new \App\Services\OpenAIService();
            $moderacao = $openAI->moderarConteudoForum($content, false);
            if (!$moderacao['apropriado']) {
                $alertModel = new ForumModerationAlert();
                $alertModel->create('reply', $topicId, null, $user['id'], $this->forumRole($user['tipo']), $content, $moderacao['motivo'] ?: 'Conteúdo fora de contexto ou inadequado.');
                $_SESSION['forum_error'] = 'Sua resposta não foi publicada. O conteúdo foi identificado como fora do contexto educacional ou inadequado. Um administrador foi notificado.';
                header('Location: ' . URL . '/forum/' . $topicId);
                exit;
            }
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                \Logger::warning('ForumController::reply moderação falhou', ['exception' => $e], 'forum');
            }
        }
        $replyModel = new ForumReply();
        $replyId = $replyModel->create($topicId, $content, $user['id'], $this->forumRole($user['tipo']));
        $_SESSION['forum_success'] = 'Resposta publicada com sucesso!';
        header('Location: ' . URL . '/forum/' . $topicId . '?r=' . $replyId . '#reply-' . $replyId);
        exit;
    }

    public function vote()
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL . '/forum');
            exit;
        }
        $replyId = (int) ($_POST['reply_id'] ?? 0);
        $voteType = ($_POST['vote_type'] ?? '') === 'downvote' ? 'downvote' : 'upvote';
        if ($replyId < 1) {
            header('Location: ' . URL . '/forum');
            exit;
        }
        $replyModel = new ForumReply();
        $reply = $replyModel->getById($replyId);
        if (!$reply) {
            header('Location: ' . URL . '/forum');
            exit;
        }
        $voteModel = new ForumVote();
        $voteModel->setVote($replyId, $user['id'], $this->forumRole($user['tipo']), $voteType);
        header('Location: ' . URL . '/forum/' . (int) $reply['topic_id']);
        exit;
    }

    public function markBestAnswer()
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL . '/forum');
            exit;
        }
        $role = $this->forumRole($user['tipo']);
        if (!ForumSecurityService::canMarkBestAnswer($role)) {
            $_SESSION['forum_error'] = 'Apenas professores ou administradores podem marcar a melhor resposta.';
            $replyId = (int) ($_POST['reply_id'] ?? 0);
            $replyModel = new ForumReply();
            $reply = $replyModel->getById($replyId);
            $topicId = $reply ? (int) $reply['topic_id'] : 0;
            $redirectUrl = $topicId > 0 ? (URL . '/forum/' . $topicId) : (URL . '/forum');
            header('Location: ' . $redirectUrl);
            exit;
        }
        $replyId = (int) ($_POST['reply_id'] ?? 0);
        $replyModel = new ForumReply();
        $reply = $replyModel->getById($replyId);
        if (!$reply) {
            header('Location: ' . URL . '/forum');
            exit;
        }
        $replyModel->setBestAnswer($replyId, $reply['topic_id']);
        $topicModel = new ForumTopic();
        $topicModel->markResolved($reply['topic_id']);
        header('Location: ' . URL . '/forum/' . (int) $reply['topic_id']);
        exit;
    }
}
