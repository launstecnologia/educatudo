<?php

namespace App\Dominio\Escola\Controllers;

use App\Core\AdminPermissionMatrix;
use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;
use App\Dominio\Escola\Services\AnoLetivoService;
use App\Helpers\AdminPasswordHelper;
use App\Painel\Master\Services\LimparDadosEscolaEducaService;
use App\Painel\Master\Services\MasterFilaService;

/**
 * EducaTudo - CRUD Ano Letivo (tabela ano_letivo)
 * Estrutura escolar normalizada (migration 022).
 */

if (!class_exists(__NAMESPACE__ . '\\AnoLetivoController', false)) {
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

    /** @deprecated Hub movido para Modulos/comunicacao/ComunicacaoAdminController */
    public function comunicacao()
    {
        $this->redirect(URL . '/admin/comunicacao');
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
            require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
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

    /**
     * Confirma zerar e popular. Só abre se o banco desta sessão for o da escola Educa.
     */
    public function zerarPopularEduca()
    {
        $user = $this->auth->getUser();
        if (!$this->podeAbrirZerarPopular($user)) {
            $this->redirect('/admin/z-configuracao');
            return;
        }

        $pdo = $this->db->getPdo();
        $arquivo = LimparDadosEscolaEducaService::ARQUIVO_CARGA;
        $caminho = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4)) . '/database/migrations/' . $arquivo;
        $inspecao = ['ok' => false, 'erro' => 'Não foi possível ler o banco desta escola.'];
        if (!is_file($caminho)) {
            $inspecao = ['ok' => false, 'erro' => 'Arquivo da carga não encontrado: ' . $arquivo];
        } elseif ($pdo instanceof \PDO) {
            $catalogo = LimparDadosEscolaEducaService::catalogoProntoParaCarga($pdo);
            $inspecao = $catalogo !== null
                ? ['ok' => false, 'erro' => $catalogo]
                : LimparDadosEscolaEducaService::inspecionar($pdo, LimparDadosEscolaEducaService::PRESERVAR_NA_CARGA);
        }

        $this->viewWithLayout('admin', 'admin/z-configuracao/zerar-popular', [
            'title' => 'Zerar e popular - EducaTudo',
            'user' => $user,
            'current_page' => 'zerar_popular_educa',
            'arquivo_carga' => $arquivo,
            'inspecao' => $inspecao,
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * Enfileira a limpeza e a carga. O banco é o da sessão, nunca um id enviado no formulário.
     */
    public function zerarPopularEducaExecutar()
    {
        $user = $this->auth->getUser();
        $volta = '/admin/z-configuracao/zerar-popular';
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Abra a tela de novo e confirme outra vez.', 'error');
            $this->redirect($volta);
            return;
        }
        if (!$this->podeAbrirZerarPopular($user)) {
            $this->setFlashMessage('Recusado: esta escola não é o banco permitido. Nada foi alterado.', 'error');
            $this->redirect('/admin/z-configuracao');
            return;
        }

        $senha = (string) ($_POST['senha'] ?? '');
        if (trim($senha) === '') {
            $this->setFlashMessage('Digite sua senha para confirmar.', 'error');
            $this->redirect($volta);
            return;
        }
        if (!is_array($user) || !AdminPasswordHelper::verifyAdminPassword($this->db, $user, $senha)) {
            $this->setFlashMessage('Senha incorreta. Nada foi alterado.', 'error');
            $this->redirect($volta);
            return;
        }

        $arquivo = LimparDadosEscolaEducaService::ARQUIVO_CARGA;
        $caminho = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4)) . '/database/migrations/' . $arquivo;
        if (!is_file($caminho)) {
            $this->setFlashMessage('Arquivo da carga não encontrado. Nada foi alterado.', 'error');
            $this->redirect($volta);
            return;
        }

        $pdo = $this->db->getPdo();
        if (!$pdo instanceof \PDO) {
            $this->setFlashMessage('Não foi possível ler o banco desta escola. Nada foi alterado.', 'error');
            $this->redirect($volta);
            return;
        }
        $catalogo = LimparDadosEscolaEducaService::catalogoProntoParaCarga($pdo);
        if ($catalogo !== null) {
            $this->setFlashMessage($catalogo, 'error');
            $this->redirect($volta);
            return;
        }

        $master = MasterFilaService::masterPdo();
        if (!$master instanceof \PDO || !MasterFilaService::tabelaExiste($master)) {
            $this->setFlashMessage('A fila do Master não está configurada. Nada foi alterado.', 'error');
            $this->redirect($volta);
            return;
        }

        $escolaId = $this->escolaIdDoBancoPermitido($master);
        if ($escolaId === null) {
            $this->setFlashMessage('Não foi possível identificar só esta escola no cadastro master. Nada foi alterado.', 'error');
            $this->redirect($volta);
            return;
        }
        if (defined('TENANT_ID') && (int) TENANT_ID > 0 && (int) TENANT_ID !== $escolaId) {
            $this->setFlashMessage('A escola desta sessão não é a do banco permitido. Nada foi alterado.', 'error');
            $this->redirect($volta);
            return;
        }
        if (MasterFilaService::temJobAbertoDaEscola(MasterFilaService::TIPO_MIGRATION_ESCOLA, $escolaId)) {
            $this->setFlashMessage('Já existe uma carga desta escola em andamento. Nada novo foi iniciado.', 'error');
            $this->redirect($volta);
            return;
        }

        try {
            $jobId = MasterFilaService::enfileirar(
                MasterFilaService::TIPO_MIGRATION_ESCOLA,
                [
                    'escola_id' => $escolaId,
                    'migrations' => [$arquivo],
                    'zerar_antes' => true,
                ],
                null,
                $escolaId,
                null
            );
        } catch (\Throwable $e) {
            $this->setFlashMessage('Não foi possível enfileirar: ' . $e->getMessage(), 'error');
            $this->redirect($volta);
            return;
        }

        $this->setFlashMessage(
            'Limpeza e carga EM 2025 enfileiradas (job ' . $jobId . '). A carga segue em segundo plano.',
            'success'
        );
        $this->redirect('/admin/z-configuracao');
    }

    /**
     * @param array<string,mixed>|null $user
     */
    private function podeAbrirZerarPopular(?array $user): bool
    {
        $perms = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
        if (!AdminPermissionMatrix::usuarioPodeVerZConfiguracao($perms)) {
            return false;
        }
        $pdo = $this->db->getPdo();
        return $pdo instanceof \PDO && LimparDadosEscolaEducaService::conexaoAtualPermitida($pdo);
    }

    private function escolaIdDoBancoPermitido(\PDO $master): ?int
    {
        $stmt = $master->prepare(
            'SELECT e.id
               FROM escolas e
               INNER JOIN config_escolas_banco b ON b.escola_id = e.id
              WHERE b.nome_banco = :nome'
        );
        $stmt->execute(['nome' => LimparDadosEscolaEducaService::BANCO_PERMITIDO]);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (count($ids) !== 1) {
            return null;
        }
        $id = (int) $ids[0];
        return $id > 0 ? $id : null;
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
            "SELECT * FROM ano_letivo ORDER BY ano DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
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
        $periodos = $this->service->periodosEmUso((int) $item['ano']);
        $this->json([
            'success' => true,
            'item' => $item,
            'divisao_bloqueada' => false,
            'tem_cadastros' => $periodos !== [],
            'periodos_usados' => $periodos,
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
            'mapeamento' => is_array($_POST['mapeamento'] ?? null) ? $_POST['mapeamento'] : [],
        ];
    }
}
}

if (!class_exists("AnoLetivoController", false) && class_exists(__NAMESPACE__ . "\\AnoLetivoController", false)) {
    class_alias(__NAMESPACE__ . "\\AnoLetivoController", "AnoLetivoController");
}
