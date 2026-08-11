<?php
/**
 * EducaTudo - CRUD Ano Letivo (tabela ano_letivo)
 * Estrutura escolar normalizada (migration 022).
 */

if (!class_exists('AnoLetivoController')) {
class AnoLetivoController extends BaseController
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

    private function tableExists()
    {
        try {
            return $this->db->fetch("SHOW TABLES LIKE 'ano_letivo'") !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function avaliacoes()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/avaliacoes/index', [
            'title'        => 'Avaliações - EducaTudo',
            'user'         => $user,
            'current_page' => 'avaliacoes',
        ]);
    }

    public function pedagogico()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/pedagogico/index', [
            'title'        => 'Pedagógico - EducaTudo',
            'user'         => $user,
            'current_page' => 'pedagogico',
        ]);
    }

    public function academico()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/academico/index', [
            'title'        => 'Acadêmico - EducaTudo',
            'user'         => $user,
            'current_page' => 'academico',
        ]);
    }

    public function index()
    {
        $user = $this->auth->getUser();
        if (!$this->tableExists()) {
            $data = [
                'title' => 'Anos Letivos - EducaTudo',
                'user' => $user,
                'current_page' => 'ano_letivo',
                'schema_ready' => false,
                'list' => []
            ];
            $this->viewWithLayout('admin', 'admin/ano-letivo/index', $data);
            return;
        }

        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $totalGeral = (int)($this->db->fetch("SELECT COUNT(*) AS total FROM ano_letivo")['total'] ?? 0);
        $list = $this->db->fetchAll(
            "SELECT * FROM ano_letivo ORDER BY ano DESC LIMIT $perPage OFFSET $offset"
        );

        $pagination = [
            'total' => $totalGeral,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $perPage > 0 ? (int)ceil($totalGeral / $perPage) : 1,
        ];

        $data = [
            'title' => 'Anos Letivos - EducaTudo',
            'user' => $user,
            'current_page' => 'ano_letivo',
            'schema_ready' => true,
            'list' => $list,
            'pagination' => $pagination,
            'csrf_token' => $this->generateCsrfToken(),
            'status' => $_GET['status'] ?? '',
            'message' => $_GET['message'] ?? ''
        ];
        $this->viewWithLayout('admin', 'admin/ano-letivo/index', $data);
    }

    public function create()
    {
        $user = $this->auth->getUser();
        if (!$this->tableExists()) {
            $this->redirect('/admin/ano-letivo?status=error&message=' . rawurlencode('Execute as migrations 022-026 para habilitar anos letivos.'));
            return;
        }
        $data = [
            'title' => 'Cadastrar Ano Letivo - EducaTudo',
            'user' => $user,
            'current_page' => 'ano_letivo',
            'csrf_token' => $this->generateCsrfToken()
        ];
        $this->viewWithLayout('admin', 'admin/ano-letivo/create', $data);
    }

    public function store()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/ano-letivo?status=error&message=' . rawurlencode('Token inválido.'));
            return;
        }
        if (!$this->tableExists()) {
            $this->redirect('/admin/ano-letivo?status=error&message=' . rawurlencode('Tabela não disponível.'));
            return;
        }
        try {
            $ano = (int)($_POST['ano'] ?? 0);
            $dataInicio = trim($_POST['data_inicio'] ?? '') ?: null;
            $dataFim = trim($_POST['data_fim'] ?? '') ?: null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            if ($ano < 2000 || $ano > 2100) {
                throw new Exception('Ano inválido.');
            }
            $existe = $this->db->fetch("SELECT id FROM ano_letivo WHERE ano = :ano", ['ano' => $ano]);
            if ($existe) {
                throw new Exception('Já existe ano letivo para este ano.');
            }
            $this->db->insert(
                "INSERT INTO ano_letivo (ano, data_inicio, data_fim, ativo) VALUES (:ano, :data_inicio, :data_fim, :ativo)",
                [
                    'ano' => $ano,
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'ativo' => $ativo
                ]
            );
            $this->redirect('/admin/ano-letivo?status=success&message=' . rawurlencode('Ano letivo cadastrado com sucesso.'));
        } catch (Exception $e) {
            $this->redirect('/admin/ano-letivo?status=error&message=' . rawurlencode($e->getMessage()));
        }
    }

    public function edit($id)
    {
        $user = $this->auth->getUser();
        if (!$this->tableExists()) {
            $this->redirect('/admin/ano-letivo');
            return;
        }
        $item = $this->db->fetch("SELECT * FROM ano_letivo WHERE id = :id", ['id' => $id]);
        if (!$item) {
            $this->redirect('/admin/ano-letivo');
            return;
        }
        $data = [
            'title' => 'Editar Ano Letivo - EducaTudo',
            'user' => $user,
            'current_page' => 'ano_letivo',
            'item' => $item,
            'csrf_token' => $this->generateCsrfToken()
        ];
        $this->viewWithLayout('admin', 'admin/ano-letivo/edit', $data);
    }

    public function update($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/ano-letivo?status=error&message=' . rawurlencode('Token inválido.'));
            return;
        }
        if (!$this->tableExists()) {
            $this->redirect('/admin/ano-letivo');
            return;
        }
        try {
            $ano = (int)($_POST['ano'] ?? 0);
            $dataInicio = trim($_POST['data_inicio'] ?? '') ?: null;
            $dataFim = trim($_POST['data_fim'] ?? '') ?: null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            if ($ano < 2000 || $ano > 2100) {
                throw new Exception('Ano inválido.');
            }
            $existe = $this->db->fetch("SELECT id FROM ano_letivo WHERE ano = :ano AND id != :id", ['ano' => $ano, 'id' => $id]);
            if ($existe) {
                throw new Exception('Já existe outro ano letivo com este ano.');
            }
            $this->db->update(
                "UPDATE ano_letivo SET ano = :ano, data_inicio = :data_inicio, data_fim = :data_fim, ativo = :ativo WHERE id = :id",
                ['ano' => $ano, 'data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'ativo' => $ativo, 'id' => $id]
            );
            $this->redirect('/admin/ano-letivo?status=success&message=' . rawurlencode('Ano letivo atualizado com sucesso.'));
        } catch (Exception $e) {
            $this->redirect('/admin/ano-letivo?status=error&message=' . rawurlencode($e->getMessage()));
        }
    }

    public function destroy($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/ano-letivo?status=error&message=' . rawurlencode('Token inválido.'));
            return;
        }
        if (!$this->tableExists()) {
            $this->redirect('/admin/ano-letivo');
            return;
        }
        try {
            $item = $this->db->fetch("SELECT id FROM ano_letivo WHERE id = :id", ['id' => $id]);
            if (!$item) {
                $this->redirect('/admin/ano-letivo?status=error&message=' . rawurlencode('Ano letivo não encontrado.'));
                return;
            }
            $this->db->delete("DELETE FROM ano_letivo WHERE id = :id", ['id' => $id]);
            $this->redirect('/admin/ano-letivo?status=success&message=' . rawurlencode('Ano letivo excluído.'));
        } catch (Exception $e) {
            $this->redirect('/admin/ano-letivo?status=error&message=' . rawurlencode($e->getMessage()));
        }
    }
}
}
