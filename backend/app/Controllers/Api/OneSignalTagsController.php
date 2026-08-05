<?php
/**
 * API: retorna tags do usuário logado para o OneSignal (external_user_id + tags).
 * Rota deve estar dentro do middleware Auth.
 */
require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Core/Database.php';

class OneSignalTagsController extends BaseController
{
    private $auth;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
    }

    /**
     * GET /api/notificacoes-push/meu-tags
     * Retorna: { user_id, role, turmas, alunos_ids, escola_id } para o OneSignal.
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = $this->auth->getUser();
        if (!$user || empty($user['id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado']);
            return;
        }

        $userId = (int) $user['id'];
        $role = $user['tipo'] ?? 'aluno';
        $turmas = '';
        $alunosIds = '';
        $escolaId = 1;

        if ($role === 'aluno') {
            // AuthManager: session user_id = alunos.id
            $rows = $this->db->fetchAll(
                "SELECT t.nome FROM alunos a INNER JOIN turmas t ON t.id = a.turma_id WHERE a.id = :uid AND a.ativo = 1",
                ['uid' => $userId]
            );
            $turmas = implode(',', array_map(function ($r) { return $r['nome'] ?? ''; }, $rows));
        } elseif ($role === 'pai') {
            // AuthManager: session user_id = pais.id
            $rows = $this->db->fetchAll(
                "SELECT a.id FROM alunos a WHERE a.responsavel_id = :pid AND a.ativo = 1",
                ['pid' => $userId]
            );
            $alunosIds = implode(',', array_map(function ($r) { return (string)($r['id'] ?? ''); }, $rows));
            $turmasRows = $this->db->fetchAll(
                "SELECT DISTINCT t.nome FROM alunos a INNER JOIN turmas t ON t.id = a.turma_id WHERE a.responsavel_id = :pid AND a.ativo = 1",
                ['pid' => $userId]
            );
            $turmas = implode(',', array_map(function ($r) { return $r['nome'] ?? ''; }, $turmasRows));
        } elseif ($role === 'professor') {
            // AuthManager: session user_id = professores.id
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT t.nome FROM turmas t INNER JOIN jornadas j ON j.turma_id = t.id WHERE j.professor_id = :pid",
                ['pid' => $userId]
            );
            $turmas = implode(',', array_map(function ($r) { return $r['nome'] ?? ''; }, $rows));
        }

        echo json_encode([
            'user_id' => (string) $userId,
            'role' => $role,
            'turmas' => $turmas,
            'alunos_ids' => $alunosIds,
            'escola_id' => (string) $escolaId
        ]);
    }
}
