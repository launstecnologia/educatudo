<?php

namespace App\Modulos\Matricula\Controllers;
/**
 * Webhook ZapSign (assinatura eletrônica) — rota pública.
 */


use App\Modulos\Matricula\Services\ZapSignService;

if (!class_exists(__NAMESPACE__ . '\\ZapSignWebhookController', false)) {
class ZapSignWebhookController extends \App\Core\BaseController
{
    public function handle(?string $tenantSlug = null): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // URL /webhooks/zapsign/{slug} deve bater com o tenant já resolvido (via Host ou X-Tenant).
        // ZapSign deve enviar header X-Tenant (registrado em ZapSignService::registrarWebhookNaZapSign).
        $slugUrl = strtolower(trim((string) $tenantSlug));
        if ($slugUrl !== '' && defined('TENANT_SLUG')) {
            $slugAtual = strtolower(trim((string) TENANT_SLUG));
            if ($slugAtual !== '' && !hash_equals($slugAtual, $slugUrl)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'tenant_slug_mismatch']);
                return;
            }
        }

        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'invalid_json']);
            return;
        }

        $zs = new ZapSignService();
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            $token = trim($m[1]);
        }
        if ($token === '') {
            $token = (string) ($_SERVER['HTTP_X_ZAPSIGN_TOKEN'] ?? '');
        }

        if (!$zs->validarWebhookToken($token)) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'unauthorized']);
            return;
        }

        $result = $zs->processarWebhookAssinatura($payload);
        echo json_encode($result);
    }
}
}

if (!class_exists('ZapSignWebhookController', false) && class_exists(__NAMESPACE__ . '\\ZapSignWebhookController', false)) {
    class_alias(__NAMESPACE__ . '\\ZapSignWebhookController', 'ZapSignWebhookController');
}
