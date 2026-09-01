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

    public function gestaoEscolar()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/gestao-escolar/index', [
            'title'        => 'Gestão Escolar - EducaTudo',
            'user'         => $user,
            'current_page' => 'gestao_escolar',
        ]);
    }

    public function comunicacao()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/comunicacao/index', [
            'title'        => 'Comunicação - EducaTudo',
            'user'         => $user,
            'current_page' => 'comunicacao',
        ]);
    }

    public function conteudo()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/conteudo/index', [
            'title'        => 'Conteúdo - EducaTudo',
            'user'         => $user,
            'current_page' => 'conteudo',
        ]);
    }

    public function financeiroEscolar()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/financeiro-escolar/index', [
            'title'        => 'Financeiro - EducaTudo',
            'user'         => $user,
            'current_page' => 'financeiro',
        ]);
    }

    public function monitoramento()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/monitoramento/index', [
            'title'        => 'Monitoramento - EducaTudo',
            'user'         => $user,
            'current_page' => 'monitoramento',
        ]);
    }

    public function relatorios()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/relatorios/index', [
            'title'        => 'Relatórios - EducaTudo',
            'user'         => $user,
            'current_page' => 'relatorios',
        ]);
    }

    public function sistema()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/sistema/index', [
            'title'        => 'Sistema - EducaTudo',
            'user'         => $user,
            'current_page' => 'sistema',
        ]);
    }

    public function gestaoUsuarios()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/gestao-usuarios/index', [
            'title'        => 'Usuários - EducaTudo',
            'user'         => $user,
            'current_page' => 'gestao_usuarios',
        ]);
    }

    public function zConfiguracao()
    {
        $user = $this->auth->getUser();
        if (!class_exists('AdminPermissionMatrix')) {
            require_once dirname(__DIR__, 2) . '/Core/AdminPermissionMatrix.php';
        }
        $perms = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
        if (!AdminPermissionMatrix::usuarioPodeVerZConfiguracao($perms)) {
            $this->redirect('/admin/dashboard');
            return;
        }
        $this->viewWithLayout('admin', 'admin/z-configuracao/index', [
            'title'        => 'Z-Configuração - EducaTudo',
            'user'         => $user,
            'current_page' => 'z_configuracao',
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

    /**
     * Dados de um ano letivo (JSON) para popular o offcanvas de edição
     */
    public function dados($id)
    {
        if (!$this->tableExists()) {
            $this->json(['error' => 'Tabela não disponível.'], 400);
            return;
        }
        $item = $this->db->fetch("SELECT * FROM ano_letivo WHERE id = :id", ['id' => $id]);
        if (!$item) {
            $this->json(['error' => 'Ano letivo não encontrado.'], 404);
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
        if (!$this->tableExists()) {
            $this->json(['error' => 'Tabela não disponível.'], 400);
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
            $this->json(['success' => true, 'message' => 'Ano letivo cadastrado com sucesso.']);
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
        if (!$this->tableExists()) {
            $this->json(['error' => 'Tabela não disponível.'], 400);
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
            $this->json(['success' => true, 'message' => 'Ano letivo atualizado com sucesso.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
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
