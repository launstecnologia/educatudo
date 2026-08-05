<?php

require_once __DIR__ . '/../../Services/EducaProfApiService.php';

if (!class_exists('TeacherAiAgentsController')) {
class TeacherAiAgentsController extends BaseController
{
    private $auth;
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->service = new EducaProfApiService();

        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/professor');
        }
        $user = $this->auth->getUser();
        if ($user && ($user['tipo'] ?? '') !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
    }

    public function index()
    {
        $user = $this->auth->getUser();
        $agents = [];
        $error = null;

        try {
            if ($this->service->isConfigured()) {
                $result = $this->service->listAgents();
                $agents = is_array($result['agents'] ?? null) ? $result['agents'] : [];
            } else {
                $error = 'EducaProf ainda não está configurado para esta escola.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        $this->viewWithLayout('professor', 'professor/ai-agents/index', [
            'title' => 'EducaProf - Agente IA do Professor',
            'page_title' => 'EducaProf',
            'current_page' => 'ai-agents',
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'configured' => $this->service->isConfigured(),
            'school_slug' => $this->service->getSchoolSlug(),
            'agents' => $agents,
            'error' => $error,
        ]);
    }

    public function createOrUpdateAgent()
    {
        $data = $_POST;
        if (!$this->verifyCsrfToken($data['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido.'], 403);
            return;
        }

        $user = $this->auth->getUser();
        $agentId = trim((string)($data['agent_id'] ?? ''));
        if ($agentId === '') {
            $agentId = 'educaprof-prof-' . (int)($user['id'] ?? 0);
        }

        $payload = [
            'agent_id' => $agentId,
            'professor_id' => (string)($user['id'] ?? ''),
            'name' => trim((string)($data['name'] ?? '')),
            'system_prompt' => trim((string)($data['system_prompt'] ?? '')),
            'model' => trim((string)($data['model'] ?? 'gpt-4o')),
            'temperature' => (float)($data['temperature'] ?? 0.3),
            'max_tokens' => (int)($data['max_tokens'] ?? 2048),
        ];

        if ($payload['name'] === '') {
            $this->json(['success' => false, 'error' => 'Nome do agente é obrigatório.'], 422);
            return;
        }

        try {
            $action = trim((string)($data['action'] ?? 'create'));
            $result = $action === 'update'
                ? $this->service->updateAgent($payload)
                : $this->service->createAgent($payload);
            $this->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function ingestContent()
    {
        $data = $_POST;
        if (!$this->verifyCsrfToken($data['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido.'], 403);
            return;
        }

        $agentId = trim((string)($data['agent_id'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        if ($agentId === '' || $content === '') {
            $this->json(['success' => false, 'error' => 'agent_id e content são obrigatórios.'], 422);
            return;
        }
        if (mb_strlen($content) > 120000) {
            $this->json(['success' => false, 'error' => 'Conteúdo muito grande para ingestão (máx. 120000 caracteres).'], 422);
            return;
        }

        $user = $this->auth->getUser();
        $payload = [
            'agent_id' => $agentId,
            'content' => $content,
            'file_type' => 'text',
            'professor_id' => (string)($user['id'] ?? ''),
        ];

        try {
            $result = $this->service->ingest($payload);
            $this->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function chat()
    {
        $data = $_POST;
        if (!$this->verifyCsrfToken($data['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido.'], 403);
            return;
        }

        $agentId = trim((string)($data['agent_id'] ?? ''));
        $chatId = trim((string)($data['chat_id'] ?? ''));
        $question = trim((string)($data['question'] ?? ''));
        if ($agentId === '' || $chatId === '' || $question === '') {
            $this->json(['success' => false, 'error' => 'agent_id, chat_id e question são obrigatórios.'], 422);
            return;
        }

        $user = $this->auth->getUser();
        $payload = [
            'agent_id' => $agentId,
            'chat_id' => $chatId,
            'question' => $question,
            'tipo' => 'professor',
            'user_id' => (string)($user['id'] ?? ''),
            'match_count' => (int)($data['match_count'] ?? 5),
        ];

        try {
            $result = $this->service->chat($payload);
            $this->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function usage()
    {
        $limit = (int)($_GET['limit'] ?? 50);
        try {
            $result = $this->service->usage($limit);
            $this->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
}

