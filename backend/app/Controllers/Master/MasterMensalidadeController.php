<?php

if (!class_exists('MasterMensalidadeController')) {

class MasterMensalidadeController extends BaseController
{
    public function index()
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Core/Database.php';
        require_once __DIR__ . '/../../Services/MasterMensalidadeService.php';

        $db = Database::getInstance();
        $escolas = $this->listarEscolasComBanco($db);

        $filtroEscola = (int) ($_GET['escola_id'] ?? 0);
        $busca = trim((string) ($_GET['q'] ?? ''));

        $service = new MasterMensalidadeService();
        $linhas = $service->coletarResumos($escolas, $filtroEscola, $busca);
        usort($linhas, static function (array $a, array $b): int {
            $cmp = ((float) ($b['valor_total'] ?? 0)) <=> ((float) ($a['valor_total'] ?? 0));
            return $cmp !== 0 ? $cmp : strcasecmp((string) ($a['escola_nome'] ?? ''), (string) ($b['escola_nome'] ?? ''));
        });
        $totais = $service->agregarTotais($linhas);

        $perPage = 20;
        $total = count($linhas);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $this->viewWithLayout('master', 'master/mensalidade/index', [
            'title' => 'Mensalidade - EducaTudo',
            'page_title' => 'Mensalidade',
            'current_page' => 'mensalidade',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'flash' => $this->getFlashMessage(),
            'csrf_token' => $this->generateCsrfToken(),
            'escolas' => $escolas,
            'linhas' => $total > 0 ? array_slice($linhas, $offset, $perPage) : [],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'totais' => $totais,
            'filtro_escola' => $filtroEscola,
            'filtro_q' => $busca,
        ]);
    }

    public function escola($id)
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Core/Database.php';
        require_once __DIR__ . '/../../Core/MasterTenantConnection.php';
        require_once __DIR__ . '/../../Services/MasterMensalidadeService.php';

        $escolaId = (int) $id;
        if ($escolaId <= 0) {
            $this->setFlashMessage('Escola inválida.', 'error');
            header('Location: ' . URL . '/master/mensalidade');
            exit;
        }

        $db = Database::getInstance();
        $escola = $db->fetch(
            'SELECT e.id, e.nome, e.slug
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.id = :id',
            ['id' => $escolaId]
        );
        if (!$escola) {
            $this->setFlashMessage('Escola não encontrada.', 'error');
            header('Location: ' . URL . '/master/mensalidade');
            exit;
        }

        $conn = MasterTenantConnection::getPdoAndEscola($escolaId);
        if (!$conn || empty($conn['pdo'])) {
            $this->setFlashMessage('Não foi possível conectar ao banco desta escola.', 'error');
            header('Location: ' . URL . '/master/mensalidade');
            exit;
        }

        $service = new MasterMensalidadeService();
        try {
            $resumo = $service->resumoDaEscola($conn['pdo'], $escola);
            $historico = $service->historicoDaEscola($conn['pdo']);
        } catch (Throwable $e) {
            error_log('[MasterMensalidade] escola ' . $escolaId . ': ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível ler a cobrança desta escola.', 'error');
            header('Location: ' . URL . '/master/mensalidade');
            exit;
        }

        $this->viewWithLayout('master', 'master/mensalidade/escola', [
            'title' => 'Mensalidade — ' . (string) ($escola['nome'] ?? '') . ' - EducaTudo',
            'page_title' => 'Mensalidade',
            'current_page' => 'mensalidade',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'flash' => $this->getFlashMessage(),
            'csrf_token' => $this->generateCsrfToken(),
            'escola' => $escola,
            'resumo' => $resumo,
            'historico' => $historico,
        ]);
    }

    public function salvar()
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Core/MasterTenantConnection.php';
        require_once __DIR__ . '/../../Services/MasterMensalidadeService.php';

        $escolaId = (int) ($_POST['escola_id'] ?? 0);
        $voltar = $this->urlVoltar($escolaId);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . $voltar);
            exit;
        }

        $valor = round((float) ($_POST['valor_por_usuario'] ?? 0), 2);

        if ($escolaId <= 0) {
            $this->setFlashMessage('Escola inválida.', 'error');
            header('Location: ' . URL . '/master/mensalidade');
            exit;
        }

        $conn = MasterTenantConnection::getPdoAndEscola($escolaId);
        if (!$conn || empty($conn['pdo'])) {
            $this->setFlashMessage('Não foi possível conectar ao banco desta escola.', 'error');
            header('Location: ' . $voltar);
            exit;
        }

        try {
            (new MasterMensalidadeService())->salvarValorPorUsuario($conn['pdo'], $valor);
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
            LayoutHelper::invalidateCacheDaEscola($escolaId);
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            header('Location: ' . $voltar);
            exit;
        } catch (Throwable $e) {
            error_log('[MasterMensalidade] salvar escola ' . $escolaId . ': ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível salvar o valor por usuário.', 'error');
            header('Location: ' . $voltar);
            exit;
        }

        $nome = (string) ($conn['escola']['nome'] ?? 'escola');
        $this->setFlashMessage(
            sprintf('Valor por usuário de %s atualizado para R$ %s.', $nome, number_format($valor, 2, ',', '.')),
            'success'
        );
        header('Location: ' . $voltar);
        exit;
    }

    private function requireMaster(): void
    {
        if (!empty($_SESSION['master_user_id']) || !empty($_SESSION['master_user_email']) || !empty($_SESSION['master_user_nome'])) {
            return;
        }
        header('Location: ' . URL . '/master');
        exit;
    }

    private function listarEscolasComBanco(Database $db): array
    {
        return $db->query(
            'SELECT e.id, e.nome, e.slug
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.ativo = 1
             ORDER BY e.nome'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function urlVoltar(int $escolaId): string
    {
        $origem = trim((string) ($_POST['origem'] ?? ''));
        if ($origem === 'escola' && $escolaId > 0) {
            return URL . '/master/mensalidade/escola/' . $escolaId;
        }
        return URL . '/master/mensalidade';
    }
}

}
