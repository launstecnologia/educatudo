<?php
require_once __DIR__ . '/AdminBaseController.php';

class FacialDeviceAdminController extends AdminBaseController
{
    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('reconhecimento_facial', 'visualizar', false)) return;
        $ready = $this->schemaReady();
        $codes = $ready ? $this->db->fetchAll(
            "SELECT id, expires_at, used_at, created_at FROM facial_device_pairing_codes ORDER BY id DESC LIMIT 10"
        ) : [];
        $devices = $ready ? $this->db->fetchAll(
            "SELECT id, device_uid, name, enabled, paired_at, last_seen_at FROM facial_devices ORDER BY paired_at DESC"
        ) : [];
        $this->viewWithLayout('admin', 'admin/settings/facial-devices', [
            'title' => 'Dispositivos Faciais',
            'page_title' => 'Dispositivos Faciais',
            'current_page' => 'facial_devices',
            'user' => $this->auth->getUser(),
            'schema_ready' => $ready,
            'codes' => $codes,
            'devices' => $devices,
            'pairing_code' => $_SESSION['facial_pairing_code'] ?? null,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
        unset($_SESSION['facial_pairing_code']);
    }

    public function generate(): void
    {
        if (!$this->enforceAdminPermissionKey('reconhecimento_facial', 'cadastrar', false)) return;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Atualize a página.', 'error');
            $this->redirect('/admin/settings/facial-devices');
        }
        if (!$this->schemaReady()) {
            $this->setFlashMessage('Execute a migration 067_facial_devices.sql.', 'error');
            $this->redirect('/admin/settings/facial-devices');
        }
        $this->db->update('UPDATE facial_device_pairing_codes SET expires_at = NOW() WHERE used_at IS NULL AND expires_at > NOW()');
        $code = (string) random_int(100000, 999999);
        $user = $this->auth->getUser();
        $this->db->insert(
            "INSERT INTO facial_device_pairing_codes (code_hash, expires_at, created_by_user_id)
             VALUES (:hash, DATE_ADD(NOW(), INTERVAL 10 MINUTE), :user_id)",
            ['hash' => hash('sha256', $code), 'user_id' => $user['id'] ?? null]
        );
        $_SESSION['facial_pairing_code'] = $code;
        $this->setFlashMessage('Código gerado. Ele é válido por 10 minutos e pode ser usado uma vez.', 'success');
        $this->redirect('/admin/settings/facial-devices');
    }

    public function disable($id): void
    {
        if (!$this->enforceAdminPermissionKey('reconhecimento_facial', 'alterar', false)) return;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/settings/facial-devices');
        }
        if ($this->schemaReady()) {
            $this->db->update('UPDATE facial_devices SET enabled = 0, token_hash = SHA2(CONCAT(token_hash, UUID()), 256) WHERE id = :id', ['id' => (int) $id]);
        }
        $this->setFlashMessage('Dispositivo desvinculado.', 'success');
        $this->redirect('/admin/settings/facial-devices');
    }

    private function schemaReady(): bool
    {
        return $this->db->tableExists('facial_device_pairing_codes')
            && $this->db->tableExists('facial_devices');
    }
}
