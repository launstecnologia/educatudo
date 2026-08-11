<?php
require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Middleware/MobileApiAuthMiddleware.php';
require_once __DIR__ . '/../../../Services/MobileDeviceService.php';

class MobileDeviceController extends BaseController
{
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new MobileDeviceService();
    }

    public function upsert($deviceId): void
    {
        $deviceId = trim((string) $deviceId);
        $input = $this->input();
        $token = trim((string) ($input['fcm_token'] ?? ''));
        $platform = strtolower(trim((string) ($input['platform'] ?? 'android')));
        $appVersion = trim((string) ($input['app_version'] ?? ''));

        if (!$this->validDeviceId($deviceId) || strlen($token) < 20 || strlen($token) > 4096
            || $platform !== 'android' || strlen($appVersion) > 50) {
            $this->json(['error' => [
                'code' => 'validation_error',
                'message' => 'Dados do dispositivo inválidos.',
            ]], 422);
        }

        $data = $this->service->upsert((int) MobileApiAuthMiddleware::$parentId, $deviceId, [
            'fcm_token' => $token, 'platform' => $platform,
            'app_version' => $appVersion !== '' ? $appVersion : null,
        ]);
        $this->json(['data' => $data]);
    }

    public function delete($deviceId): void
    {
        $deviceId = trim((string) $deviceId);
        if (!$this->validDeviceId($deviceId)) {
            $this->json(['error' => ['code' => 'validation_error', 'message' => 'Dispositivo inválido.']], 422);
        }
        $this->service->disable((int) MobileApiAuthMiddleware::$parentId, $deviceId);
        http_response_code(204);
        exit;
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    private function validDeviceId(string $deviceId): bool
    {
        return strlen($deviceId) >= 8 && strlen($deviceId) <= 128
            && preg_match('/^[A-Za-z0-9._:-]+$/', $deviceId) === 1;
    }
}
