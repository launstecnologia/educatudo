<?php
/**
 * Admin — cadastro de agrupamentos de componentes.
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/AgrupamentoComponenteService.php';
require_once __DIR__ . '/../../../Models/Education/ComponenteCurricular.php';

use App\Modulos\AgrupamentosComponentes\Services\AgrupamentoComponenteService;

if (!class_exists('AgrupamentoComponenteAdminController')) {
class AgrupamentoComponenteAdminController extends AdminBaseController
{
    /** @var AgrupamentoComponenteService */
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AgrupamentoComponenteService();
    }

    public function index(): void
    {
        $this->redirecionarParaComponentes();
    }

    public function novo(): void
    {
        $this->redirecionarParaComponentes();
    }

    public function editar($id): void
    {
        $this->redirecionarParaComponentes();
    }

    public function dadosJson($id): void
    {
        if (!$this->enforceAdminPermissionKey('materias', 'visualizar', true)) {
            return;
        }
        $item = $this->service->model()->carregarCompleto((int) $id);
        if ($item === null) {
            $this->json(['error' => 'Agrupamento não encontrado'], 404);
            return;
        }
        $this->json(['success' => true, 'item' => $item]);
    }

    public function salvar(): void
    {
        $this->redirecionarParaComponentes();
    }

    public function atualizar($id): void
    {
        $this->redirecionarParaComponentes();
    }

    public function excluir($id): void
    {
        $this->redirecionarParaComponentes();
    }

    private function redirecionarParaComponentes(): void
    {
        $this->redirect('/admin/componentes-curriculares');
    }

    /**
     * @param array<string,mixed>|null $item
     */
    private function renderFormulario(?array $item): void
    {
        $pais = [];
        $filhosPorPai = [];
        try {
            $model = new ComponenteCurricular();
            foreach ($model->getOficiaisParaMatriz(true) as $c) {
                if (empty($c['eh_rotulo'])) {
                    continue;
                }
                $pais[] = [
                    'id' => (int) ($c['id'] ?? 0),
                    'nome' => (string) ($c['nome'] ?? ''),
                ];
            }
            $filhosPorPai = $model->mapaFilhosPorPai();
        } catch (Throwable $e) {
            $pais = [];
        }

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/agrupamentos-componentes/form', [
            'title' => ($item ? 'Editar' : 'Nova') . ' regra de nota da área — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'agrupamentos-componentes',
            'item' => $item,
            'pais' => $pais,
            'filhos_por_pai' => $filhosPorPai,
            'schema_pronto' => $this->service->model()->tabelasProntas(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }
}
}
