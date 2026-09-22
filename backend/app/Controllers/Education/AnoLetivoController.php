<?php
/**
 * EducaTudo - CRUD Ano Letivo (tabela ano_letivo)
 * Estrutura escolar normalizada (migration 022).
 */

require_once __DIR__ . '/../../Services/AnoLetivoService.php';
require_once __DIR__ . '/../../Core/PeriodoLetivo.php';

if (!class_exists('AnoLetivoController')) {
class AnoLetivoController extends BaseController
{
    private $auth;
    private $db;
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->service = new AnoLetivoService($this->db);
        $user = $this->auth->getUser();
        if ($user && $user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola') {
            $this->redirect('/admin');
        }
    }

    private function tableExists()
    {
        return $this->service->tabelaExiste();
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

    public function rotina()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/rotina/index', [
            'title'        => 'Rotina - EducaTudo',
            'user'         => $user,
            'current_page' => 'rotina',
        ]);
    }

    public function paineis()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/paineis/index', [
            'title'        => 'Painéis - EducaTudo',
            'user'         => $user,
            'current_page' => 'paineis',
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
        $item = $this->service->buscarPorId((int) $id);
        if (!$item) {
            $this->json(['error' => 'Ano letivo não encontrado.'], 404);
            return;
        }
        $uso = $this->service->usoDaDivisao((int) $item['ano']);
        $this->json([
            'success' => true,
            'item' => $item,
            'divisao_bloqueada' => !empty($uso['bloqueada']),
            'divisao_motivo' => (string) ($uso['mensagem'] ?? ''),
        ]);
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
            $this->service->cadastrar($this->dadosDoPost());
            $this->json(['success' => true, 'message' => 'Ano letivo cadastrado com sucesso.']);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            error_log('AnoLetivo store: ' . $e->getMessage());
            $this->json(['error' => 'Não foi possível salvar o ano letivo. Tente novamente.'], 400);
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
            $this->service->atualizar((int) $id, $this->dadosDoPost());
            $this->json(['success' => true, 'message' => 'Ano letivo atualizado com sucesso.']);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            error_log('AnoLetivo update: ' . $e->getMessage());
            $this->json(['error' => 'Não foi possível atualizar o ano letivo. Tente novamente.'], 400);
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

    /**
     * @return array{ano:int,data_inicio:string,data_fim:string,periodo_tipo:string,ativo:int}
     */
    private function dadosDoPost(): array
    {
        return [
            'ano' => (int) ($_POST['ano'] ?? 0),
            'data_inicio' => (string) ($_POST['data_inicio'] ?? ''),
            'data_fim' => (string) ($_POST['data_fim'] ?? ''),
            'periodo_tipo' => (string) ($_POST['periodo_tipo'] ?? ''),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ];
    }
}
}
