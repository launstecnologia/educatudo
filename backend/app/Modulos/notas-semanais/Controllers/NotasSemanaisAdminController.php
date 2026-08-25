<?php
/**
 * Admin — configuração do quadro de notas semanais.
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Models/NotasSemanaisConfig.php';

if (!class_exists('NotasSemanaisAdminController')) {
class NotasSemanaisAdminController extends AdminBaseController
{
    /** @var NotasSemanaisConfig */
    private $configModel;

    public function __construct()
    {
        parent::__construct();
        $this->configModel = new NotasSemanaisConfig();
    }

    public function config(): void
    {
        if (!$this->enforceAdminPermissionKey('notas_semanais', 'visualizar', false)) {
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/notas-semanais/config', [
            'title' => 'Quadro de Notas Semanais — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'notas_semanais',
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
            'config' => $this->configModel->obter(),
            'materias' => $this->configModel->listarMateriasComGrupo(),
            'schema_pronto' => $this->configModel->tabelasProntas(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey('notas_semanais', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? $_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/notas-semanais');
            return;
        }
        if (!$this->configModel->tabelasProntas()) {
            $this->setFlashMessage('Rode a migration 2026_08_14_notas_semanais_quadro.sql no Master.', 'error');
            $this->redirect('/admin/notas-semanais');
            return;
        }

        $this->configModel->salvar($_POST);

        $mapa = [];
        $grupos = $_POST['materia_grupo'] ?? [];
        if (is_array($grupos)) {
            foreach ($grupos as $materiaId => $grupo) {
                $mapa[(int) $materiaId] = (string) $grupo;
            }
        }
        $this->configModel->salvarGruposMaterias($mapa);

        $this->setFlashMessage('Configuração do quadro semanal salva.', 'success');
        $this->redirect('/admin/notas-semanais');
    }
}
}
