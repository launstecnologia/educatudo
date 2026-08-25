<?php
/**
 * EducaTudo - SalaAdminController
 * CRUD de Salas/Ambientes da escola (sala de aula, laboratório, quadra,
 * biblioteca etc.) — usadas como Sala Padrão da Turma e pelo Patrimônio.
 */

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Models/Education/Sala.php';
require_once __DIR__ . '/../../Services/SalaService.php';

if (!class_exists('SalaAdminController')) {
class SalaAdminController extends AdminBaseController
{
    private function model(): Sala
    {
        return new Sala();
    }

    private function service(): SalaService
    {
        return new SalaService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('salas', 'visualizar', false)) {
            return;
        }

        $data = [
            'title' => 'Salas / Ambientes - EducaTudo',
            'items' => $this->model()->getAll(),
            'tipos' => Sala::TIPOS,
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'salas',
        ];

        $this->viewWithLayout('admin', 'admin/salas/index', $data);
    }

    public function dados($id): void
    {
        if (!$this->enforceAdminPermissionKey('salas', 'visualizar', true)) {
            return;
        }

        $item = $this->model()->findById((int) $id);
        if (!$item) {
            $this->json(['error' => 'Sala/Ambiente não encontrada'], 404);
            return;
        }

        $this->json(['success' => true, 'item' => $item]);
    }

    public function store(): void
    {
        if (!$this->enforceAdminPermissionKey('salas', 'cadastrar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $result = $this->service()->criar($_POST);
        if (!$result['success']) {
            $this->json(['error' => $result['error']], 400);
            return;
        }

        $this->json(['success' => true, 'message' => 'Sala/Ambiente cadastrada com sucesso', 'id' => $result['id']]);
    }

    public function update($id): void
    {
        if (!$this->enforceAdminPermissionKey('salas', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $result = $this->service()->atualizar((int) $id, $_POST);
        if (!$result['success']) {
            $this->json(['error' => $result['error']], 400);
            return;
        }

        $this->json(['success' => true, 'message' => 'Sala/Ambiente atualizada com sucesso']);
    }

    public function delete($id): void
    {
        if (!$this->enforceAdminPermissionKey('salas', 'excluir', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $result = $this->service()->excluir((int) $id);
        if (!$result['success']) {
            $this->json(['error' => $result['error']], 400);
            return;
        }

        $this->json(['success' => true, 'message' => 'Sala/Ambiente excluída com sucesso']);
    }
}
}
