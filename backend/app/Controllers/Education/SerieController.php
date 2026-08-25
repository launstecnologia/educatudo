<?php
/**
 * EducaTudo - CRUD Série (tabela serie, depende de curso - migration 024)
 */

require_once __DIR__ . '/../../Helpers/AdminPasswordHelper.php';

if (!class_exists('SerieController')) {
class SerieController extends BaseController
{
    private $auth;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $user = $this->auth->getUser();
        if ($user && $user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola') {
            $this->redirect('/admin');
        }
    }

    private function tableExists(): bool
    {
        try {
            return $this->db->fetch("SHOW TABLES LIKE 'serie'") !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    private function cursoTableExists(): bool
    {
        try {
            return $this->db->fetch("SHOW TABLES LIKE 'curso'") !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    private function schemaReady(): bool
    {
        return $this->tableExists() && $this->cursoTableExists();
    }

    public function index()
    {
        $user = $this->auth->getUser();
        if (!$this->schemaReady()) {
            $this->viewWithLayout('admin', 'admin/serie/index', [
                'title' => 'Séries - EducaTudo',
                'user' => $user,
                'current_page' => 'serie',
                'schema_ready' => false,
                'list' => [],
            ]);
            return;
        }

        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $totalGeral = (int)($this->db->fetch(
            "SELECT COUNT(*) AS total FROM serie s INNER JOIN curso c ON c.id = s.curso_id"
        )['total'] ?? 0);
        $list = $this->db->fetchAll(
            "SELECT s.*, c.nome AS curso_nome
             FROM serie s
             INNER JOIN curso c ON c.id = s.curso_id
             ORDER BY c.ordem ASC, c.nome ASC, s.ordem ASC, s.nome ASC
             LIMIT $perPage OFFSET $offset"
        );

        $pagination = [
            'total' => $totalGeral,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $perPage > 0 ? (int)ceil($totalGeral / $perPage) : 1,
        ];

        $cursos = $this->db->fetchAll("SELECT id, nome FROM curso WHERE ativo = 1 ORDER BY ordem ASC, nome ASC");
        $this->viewWithLayout('admin', 'admin/serie/index', [
            'title' => 'Séries - EducaTudo',
            'user' => $user,
            'current_page' => 'serie',
            'schema_ready' => true,
            'list' => $list,
            'pagination' => $pagination,
            'csrf_token' => $this->generateCsrfToken(),
            'status' => $_GET['status'] ?? '',
            'message' => $_GET['message'] ?? '',
            'cursos' => $cursos,
        ]);
    }

    /**
     * Dados de uma série (JSON) para popular o offcanvas de edição
     */
    public function dados($id)
    {
        if (!$this->schemaReady()) {
            $this->json(['error' => 'Estrutura não disponível.'], 400);
            return;
        }
        $item = $this->db->fetch("SELECT * FROM serie WHERE id = :id", ['id' => $id]);
        if (!$item) {
            $this->json(['error' => 'Série não encontrada.'], 404);
            return;
        }
        $this->json(['success' => true, 'item' => $item]);
    }

    public function store()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }
        if (!$this->schemaReady()) {
            $this->json(['error' => 'Estrutura não disponível.'], 400);
            return;
        }

        try {
            $cursoId = (int) ($_POST['curso_id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $ordem = (int) ($_POST['ordem'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($cursoId <= 0) {
                throw new Exception('Selecione o curso.');
            }
            if ($nome === '') {
                throw new Exception('Nome é obrigatório.');
            }

            $curso = $this->db->fetch("SELECT id FROM curso WHERE id = :id", ['id' => $cursoId]);
            if (!$curso) {
                throw new Exception('Curso inválido.');
            }

            $existe = $this->db->fetch(
                "SELECT id FROM serie WHERE curso_id = :curso_id AND nome = :nome",
                ['curso_id' => $cursoId, 'nome' => $nome]
            );
            if ($existe) {
                throw new Exception('Já existe uma série com este nome neste curso.');
            }

            $this->db->insert(
                "INSERT INTO serie (curso_id, nome, ordem, ativo) VALUES (:curso_id, :nome, :ordem, :ativo)",
                ['curso_id' => $cursoId, 'nome' => $nome, 'ordem' => $ordem, 'ativo' => $ativo]
            );

            $this->json(['success' => true, 'message' => 'Série cadastrada com sucesso.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }
        if (!$this->schemaReady()) {
            $this->json(['error' => 'Estrutura não disponível.'], 400);
            return;
        }

        try {
            $cursoId = (int) ($_POST['curso_id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $ordem = (int) ($_POST['ordem'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($cursoId <= 0) {
                throw new Exception('Selecione o curso.');
            }
            if ($nome === '') {
                throw new Exception('Nome é obrigatório.');
            }

            $item = $this->db->fetch("SELECT id FROM serie WHERE id = :id", ['id' => $id]);
            if (!$item) {
                throw new Exception('Série não encontrada.');
            }

            $existe = $this->db->fetch(
                "SELECT id FROM serie WHERE curso_id = :curso_id AND nome = :nome AND id != :id",
                ['curso_id' => $cursoId, 'nome' => $nome, 'id' => $id]
            );
            if ($existe) {
                throw new Exception('Já existe outra série com este nome neste curso.');
            }

            $this->db->update(
                "UPDATE serie SET curso_id = :curso_id, nome = :nome, ordem = :ordem, ativo = :ativo WHERE id = :id",
                ['curso_id' => $cursoId, 'nome' => $nome, 'ordem' => $ordem, 'ativo' => $ativo, 'id' => $id]
            );

            $this->json(['success' => true, 'message' => 'Série atualizada com sucesso.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $user = $this->auth->getUser();
        $senha = trim((string) ($_POST['senha'] ?? ''));
        if ($senha === '') {
            $this->json(['error' => 'Digite sua senha para confirmar.'], 400);
            return;
        }
        if (!AdminPasswordHelper::verifyAdminPassword($this->db, $user, $senha)) {
            $this->json(['error' => 'Senha incorreta.'], 400);
            return;
        }

        $result = $this->deleteSerieById((int) $id);
        if ($result['deleted']) {
            $this->json(['success' => true, 'message' => 'Série excluída.']);
            return;
        }

        $this->json(['error' => $result['error']], 400);
    }

    public function bulkDestroy()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $user = $this->auth->getUser();
        $senha = trim((string) ($_POST['senha'] ?? ''));
        if ($senha === '') {
            $this->json(['error' => 'Digite sua senha para confirmar.'], 400);
            return;
        }
        if (!AdminPasswordHelper::verifyAdminPassword($this->db, $user, $senha)) {
            $this->json(['error' => 'Senha incorreta.'], 400);
            return;
        }

        $ids = AdminPasswordHelper::parseIds($_POST['serie_ids'] ?? []);
        if ($ids === []) {
            $this->json(['error' => 'Selecione ao menos uma série.'], 400);
            return;
        }

        $deleted = 0;
        $errors = [];
        foreach ($ids as $id) {
            $result = $this->deleteSerieById($id);
            if ($result['deleted']) {
                $deleted++;
            } else {
                $errors[] = $result['error'];
            }
        }

        if ($deleted === 0) {
            $this->json(['error' => implode(' ', $errors)], 400);
            return;
        }

        $message = $deleted . ' série(s) excluída(s).';
        if ($errors !== []) {
            $message .= ' ' . count($errors) . ' não excluída(s): ' . implode(' ', array_slice($errors, 0, 3));
        }

        $this->json(['success' => true, 'message' => $message, 'deleted' => $deleted, 'errors' => $errors]);
    }

    /**
     * @return array{deleted: bool, error: string}
     */
    private function deleteSerieById(int $id): array
    {
        if (!$this->schemaReady()) {
            return ['deleted' => false, 'error' => 'Estrutura não disponível.'];
        }

        $item = $this->db->fetch("SELECT id, nome FROM serie WHERE id = :id", ['id' => $id]);
        if (!$item) {
            return ['deleted' => false, 'error' => 'Série #' . $id . ' não encontrada.'];
        }

        $temTurma = false;
        try {
            $hasCol = $this->db->fetch("SHOW COLUMNS FROM turmas LIKE 'serie_id'");
            if ($hasCol !== false) {
                $temTurma = $this->db->fetch("SELECT id FROM turmas WHERE serie_id = :id LIMIT 1", ['id' => $id]) !== false;
            }
        } catch (Exception $e) {
            $temTurma = false;
        }
        if ($temTurma) {
            return ['deleted' => false, 'error' => '"' . $item['nome'] . '": possui turmas vinculadas.'];
        }

        $this->db->delete("DELETE FROM serie WHERE id = :id", ['id' => $id]);

        return ['deleted' => true, 'error' => ''];
    }
}
}
