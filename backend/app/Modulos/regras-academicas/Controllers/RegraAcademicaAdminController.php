<?php
/**
 * EducaTudo - Cadastro de Regras Acadêmicas (admin)
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Models/RegraAcademica.php';
require_once __DIR__ . '/../../../Models/Education/ClassRoom.php';
require_once __DIR__ . '/../../../Models/Education/ComponenteCurricular.php';
require_once __DIR__ . '/../../../Models/Education/MatrizCurricular.php';
require_once __DIR__ . '/../Services/RegraAcademicaService.php';
require_once __DIR__ . '/../../../Services/ResultadoAcademicoService.php';

use App\Modulos\RegrasAcademicas\Services\RegraAcademicaService;

if (!class_exists('RegraAcademicaAdminController')) {
class RegraAcademicaAdminController extends AdminBaseController
{
    private function service(): RegraAcademicaService
    {
        return new RegraAcademicaService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'visualizar', false)) {
            return;
        }

        $model = $this->service()->model();
        $filtros = [
            'ano_letivo' => (int) ($_GET['ano_letivo'] ?? 0),
            'curso_id' => (int) ($_GET['curso_id'] ?? 0),
            'serie_id' => (int) ($_GET['serie_id'] ?? 0),
        ];
        $flash = $this->getFlashMessage();
        $catalogos = $this->catalogos();

        $this->viewWithLayout('admin', 'admin/regras-academicas/index', [
            'title' => 'Regras Acadêmicas - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'regras-academicas',
            'itens' => $model->schemaPronto() ? $model->getAll(array_filter($filtros)) : [],
            'schema_pronto' => $model->schemaPronto(),
            'filtros' => $filtros,
            'cursos' => $catalogos['cursos'],
            'series' => $catalogos['series'],
            'anos_letivos' => $catalogos['anos_letivos'],
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function nova(): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'cadastrar', false)) {
            return;
        }
        $this->renderFormulario(null);
    }

    public function editar($id): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'alterar', false)) {
            return;
        }
        $item = $this->service()->model()->findById((int) $id);
        if (!$item) {
            $this->setFlashMessage('Regra acadêmica não encontrada.', 'error');
            $this->redirect('/admin/regras-academicas');
            return;
        }
        $this->renderFormulario($item);
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'cadastrar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/regras-academicas/nova');
            return;
        }
        $user = $this->auth->getUser();
        $result = $this->service()->criar($_POST, (int) ($user['id'] ?? 0), (string) ($user['nome'] ?? ''));
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível salvar.', 'error');
            $this->redirect('/admin/regras-academicas/nova');
            return;
        }
        $this->setFlashMessage('Regra acadêmica cadastrada.', 'success');
        $this->redirect('/admin/regras-academicas');
    }

    public function atualizar($id): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'alterar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/regras-academicas/' . (int) $id . '/editar');
            return;
        }
        $user = $this->auth->getUser();
        $result = $this->service()->atualizar((int) $id, $_POST, (int) ($user['id'] ?? 0), (string) ($user['nome'] ?? ''));
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível atualizar.', 'error');
            $this->redirect('/admin/regras-academicas/' . (int) $id . '/editar');
            return;
        }
        $this->setFlashMessage('Regra acadêmica atualizada (nova versão gravada).', 'success');
        $this->redirect('/admin/regras-academicas');
    }

    public function excluir($id): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'excluir', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/regras-academicas');
            return;
        }
        $result = $this->service()->excluir((int) $id);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível excluir.', 'error');
            $this->redirect('/admin/regras-academicas');
            return;
        }
        $this->setFlashMessage('Regra acadêmica excluída.', 'success');
        $this->redirect('/admin/regras-academicas');
    }

    /**
     * @param array<string,mixed>|null $item
     */
    private function renderFormulario(?array $item): void
    {
        $catalogos = $this->catalogos();
        $flash = $this->getFlashMessage();
        $historico = [];
        if ($item && !empty($item['id'])) {
            $historico = $this->service()->model()->listarHistorico((int) $item['id']);
        }
        $this->viewWithLayout('admin', 'admin/regras-academicas/form', [
            'title' => ($item ? 'Editar' : 'Nova') . ' regra acadêmica - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'regras-academicas',
            'item' => $item,
            'historico' => $historico,
            'cursos' => $catalogos['cursos'],
            'series' => $catalogos['series'],
            'anos_letivos' => $catalogos['anos_letivos'],
            'matrizes' => $catalogos['matrizes'],
            'componentes' => $catalogos['componentes'],
            'situacoes' => ResultadoAcademicoService::SITUACOES,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    /**
     * @return array{cursos:list,series:list,anos_letivos:list,matrizes:list,componentes:list}
     */
    private function catalogos(): array
    {
        $classRoom = new ClassRoom();
        $componentes = [];
        try {
            $componentes = (new ComponenteCurricular())->getAll(false) ?: [];
        } catch (Throwable $e) {
            $componentes = [];
        }
        $matrizes = [];
        try {
            $matrizModel = new MatrizCurricular();
            $matrizes = $matrizModel->getAll(['ativo' => 1]) ?: [];
        } catch (Throwable $e) {
            $matrizes = [];
        }

        $anos = [];
        try {
            foreach ($classRoom->getAnosLetivoNovo() ?: [] as $a) {
                $ano = (int) ($a['ano'] ?? 0);
                if ($ano > 0) {
                    $anos[$ano] = $ano;
                }
            }
        } catch (Throwable $e) {
            // tabela ano_letivo pode não existir
        }
        try {
            foreach ($classRoom->getAnosLetivos() ?: [] as $a) {
                $ano = (int) ($a['ano_letivo'] ?? $a['ano'] ?? 0);
                if ($ano > 0) {
                    $anos[$ano] = $ano;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
        if ($anos === []) {
            $anos[(int) date('Y')] = (int) date('Y');
        }
        krsort($anos);

        return [
            'cursos' => $classRoom->getCursosNovo() ?: [],
            'series' => $classRoom->getSeriesNovo() ?: [],
            'anos_letivos' => array_values($anos),
            'matrizes' => $matrizes,
            'componentes' => $componentes,
        ];
    }
}
}
