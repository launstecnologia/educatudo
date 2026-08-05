<?php
/**
 * EducaTudo - AdminBaseController
 * Classe base compartilhada por todos os sub-controllers de Admin/*.
 * Centraliza autenticacao do admin, helpers de permissao e utilitarios
 * usados por mais de um dominio (Student, Teacher, Dev, Finance, etc.).
 * Extraido de app/Controllers/User/AdminController.php.
 */

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Core/LayoutHelper.php';

if (!class_exists('AdminBaseController')) {
abstract class AdminBaseController extends BaseController
{
    protected $auth;
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        
        // Verifica se é admin (o middleware Auth já verificou se está logado)
        $user = $this->auth->getUser();
        if ($user && $user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }

    }

    protected function enforceAdminPermissionKey(string $permissionKey, string $action = 'visualizar', bool $jsonResponse = true): bool
    {
        $user = $this->auth->getUser();
        if (!class_exists('AdminPermissionMatrix')) {
            require_once __DIR__ . '/../../Core/AdminPermissionMatrix.php';
        }
        $permissions = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
        if (AdminPermissionMatrix::can($permissions, $permissionKey, $action)) {
            return true;
        }

        if ($jsonResponse) {
            $this->json(['error' => 'Sem permissão para esta ação.'], 403);
            return false;
        }

        $_SESSION['error_message'] = 'Sem permissão para esta ação.';
        $this->redirect('/admin/students');
        return false;
    }

    protected function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    protected function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'monitor':
                $this->redirect('/monitor/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/admin');
        }
    }

    protected function podeVerAlertasSensiveis($user)
    {
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            return false;
        }
        $perfil = $user['perfil_admin'] ?? '';
        if ($perfil === 'financeiro' || $perfil === 'secretaria') {
            return false;
        }
        if ($perfil === '') {
            return true;
        }
        return in_array($perfil, ['dev', 'diretor', 'coordenador'], true);
    }

    protected function isMaintenanceEnabled(): bool
    {
        return LayoutHelper::get('maintenance_mode', '0') === '1';
    }

    protected function setMaintenanceEnabled(bool $enabled): void
    {
        $value = $enabled ? '1' : '0';
        $existing = $this->db->fetch(
            "SELECT id FROM config_layout WHERE config_key = :config_key LIMIT 1",
            ['config_key' => 'maintenance_mode']
        );

        if ($existing) {
            $this->db->update(
                "UPDATE config_layout SET config_value = :config_value, updated_at = NOW() WHERE id = :id",
                ['config_value' => $value, 'id' => (int) $existing['id']]
            );
        } else {
            $this->db->insert(
                "INSERT INTO config_layout (config_key, config_value, created_at, updated_at) VALUES (:config_key, :config_value, NOW(), NOW())",
                ['config_key' => 'maintenance_mode', 'config_value' => $value]
            );
        }
    }

    protected function isDemoEducatudo()
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return strpos($host, 'demo.educatudo.com') !== false;
    }
}
}
