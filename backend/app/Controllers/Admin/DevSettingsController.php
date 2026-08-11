<?php
/**
 * Dev Settings: key-value config (e.g. flashcard_prompt).
 * Admin only. List settings, edit flashcard_prompt, save with validation and logging.
 */

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Models/System/DevSetting.php';

if (!class_exists('Logger')) {
    require_once __DIR__ . '/../../Core/Logger.php';
}

class DevSettingsController extends BaseController
{
    private $auth;

    private const ALLOWED_KEYS = [
        'flashcard_prompt',
        'panda_video_api_url',
        'panda_video_api_key',
        'panda_video_live_create_endpoint',
        'panda_video_auth_header',
        'panda_video_auth_prefix',
        'panda_video_stream_key_id',
        'jaas_app_id',
        'jaas_api_key_id',
        'jaas_private_key',
        'jaas_base_url',
        'jaas_webhook_secret',
        'jaas_webhook_signing_secret',
        'jitsi_public_base_url',
        'jitsi_webhook_token',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->redirect(URL . '/admin');
            exit;
        }
    }

    /**
     * List dev_settings and editable fields.
     */
    public function index()
    {
        $user = $this->auth->getUser();
        $model = new DevSetting();
        $settings = $model->listAll();

        $this->viewWithLayout('admin', 'admin/dev-settings/index', [
            'title' => 'Dev Settings - EducaTudo',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
            'settings' => $settings,
            'flashcard_prompt' => (string) ($model->get('flashcard_prompt') ?? ''),
            'panda_video_api_url' => (string) ($model->get('panda_video_api_url') ?? ''),
            'panda_video_api_key' => (string) ($model->get('panda_video_api_key') ?? ''),
            'panda_video_live_create_endpoint' => (string) ($model->get('panda_video_live_create_endpoint') ?? ''),
            'panda_video_auth_header' => (string) ($model->get('panda_video_auth_header') ?? ''),
            'panda_video_auth_prefix' => (string) ($model->get('panda_video_auth_prefix') ?? ''),
            'panda_video_stream_key_id' => (string) ($model->get('panda_video_stream_key_id') ?? ''),
            'jaas_app_id' => (string) ($model->get('jaas_app_id') ?? ''),
            'jaas_api_key_id' => (string) ($model->get('jaas_api_key_id') ?? ''),
            'jaas_private_key' => (string) ($model->get('jaas_private_key') ?? ''),
            'jaas_base_url' => (string) ($model->get('jaas_base_url') ?? ''),
            'jaas_webhook_secret' => (string) ($model->get('jaas_webhook_secret') ?? ''),
            'jaas_webhook_signing_secret' => (string) ($model->get('jaas_webhook_signing_secret') ?? ''),
            'jitsi_public_base_url' => (string) ($model->get('jitsi_public_base_url') ?? ''),
            'jitsi_webhook_token' => (string) ($model->get('jitsi_webhook_token') ?? ''),
            'flash_message' => $_SESSION['dev_settings_flash'] ?? '',
            'flash_type' => $_SESSION['dev_settings_flash_type'] ?? 'success',
        ]);
        unset($_SESSION['dev_settings_flash'], $_SESSION['dev_settings_flash_type']);
    }

    /**
     * POST: save allowed key.
     */
    public function save()
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->redirect(URL . '/admin');
            exit;
        }

        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            $_SESSION['dev_settings_flash'] = 'Token inválido. Tente novamente.';
            $_SESSION['dev_settings_flash_type'] = 'error';
            header('Location: ' . URL . '/admin/dev-settings');
            exit;
        }

        $key = trim((string) ($_POST['key_name'] ?? ''));
        $value = trim((string) ($_POST['value'] ?? ''));

        if ($key === '') {
            $_SESSION['dev_settings_flash'] = 'Chave é obrigatória.';
            $_SESSION['dev_settings_flash_type'] = 'error';
            header('Location: ' . URL . '/admin/dev-settings');
            exit;
        }

        if (!in_array($key, self::ALLOWED_KEYS, true)) {
            $_SESSION['dev_settings_flash'] = 'Chave não permitida para edição.';
            $_SESSION['dev_settings_flash_type'] = 'error';
            header('Location: ' . URL . '/admin/dev-settings');
            exit;
        }

        if ($key === 'flashcard_prompt' && $value === '') {
            $_SESSION['dev_settings_flash'] = 'O prompt de flashcards não pode ficar vazio.';
            $_SESSION['dev_settings_flash_type'] = 'error';
            header('Location: ' . URL . '/admin/dev-settings');
            exit;
        }

        if ($key === 'panda_video_api_url' && $value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $_SESSION['dev_settings_flash'] = 'PANDA_VIDEO_API_URL deve ser uma URL válida.';
            $_SESSION['dev_settings_flash_type'] = 'error';
            header('Location: ' . URL . '/admin/dev-settings');
            exit;
        }

        if ($key === 'panda_video_live_create_endpoint' && $value !== '' && strpos($value, '/') !== 0) {
            $_SESSION['dev_settings_flash'] = 'PANDA_VIDEO_LIVE_CREATE_ENDPOINT deve começar com /.';
            $_SESSION['dev_settings_flash_type'] = 'error';
            header('Location: ' . URL . '/admin/dev-settings');
            exit;
        }

        if ($key === 'jaas_base_url' && $value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $_SESSION['dev_settings_flash'] = 'JAAS_BASE_URL deve ser uma URL válida.';
            $_SESSION['dev_settings_flash_type'] = 'error';
            header('Location: ' . URL . '/admin/dev-settings');
            exit;
        }

        if ($key === 'jitsi_public_base_url' && $value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $_SESSION['dev_settings_flash'] = 'JITSI_PUBLIC_BASE_URL deve ser uma URL válida.';
            $_SESSION['dev_settings_flash_type'] = 'error';
            header('Location: ' . URL . '/admin/dev-settings');
            exit;
        }

        if ($key === 'jaas_private_key' && $value !== '' && strpos($value, 'PRIVATE KEY') === false) {
            $_SESSION['dev_settings_flash'] = 'JAAS_PRIVATE_KEY deve conter a chave privada RSA gerada no painel JaaS.';
            $_SESSION['dev_settings_flash_type'] = 'error';
            header('Location: ' . URL . '/admin/dev-settings');
            exit;
        }

        try {
            $model = new DevSetting();
            $model->set($key, $value);
            Logger::info('DevSettings: config updated', [
                'key' => $key,
                'updated_by' => $user['id'] ?? null,
                'user_type' => $user['tipo'] ?? null,
            ], 'dev_settings');
        } catch (Exception $e) {
            Logger::error('DevSettings: save failed', ['exception' => $e, 'key' => $key], 'dev_settings');
            $_SESSION['dev_settings_flash'] = 'Erro ao salvar: ' . $e->getMessage();
            $_SESSION['dev_settings_flash_type'] = 'error';
            header('Location: ' . URL . '/admin/dev-settings');
            exit;
        }

        $_SESSION['dev_settings_flash'] = 'Configuração salva com sucesso.';
        $_SESSION['dev_settings_flash_type'] = 'success';
        header('Location: ' . URL . '/admin/dev-settings');
        exit;
    }
}
