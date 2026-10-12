<?php

namespace App\API\Controllers\V1;

use App\Core\BaseController;
use App\Services\FacialRecognitionProviderService;
use App\Core\LayoutHelper;

/** Endpoint público e sem dados sensíveis para monitorar a API do tenant. */
class ApiStatusController extends BaseController
{
    public function index(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store, max-age=0');

        $app = require __DIR__ . '/../../../../config/app.php';
        $school = is_array($app['school'] ?? null) ? $app['school'] : [];
        $tenantSlug = defined('TENANT_SLUG') && trim((string) TENANT_SLUG) !== ''
            ? strtolower(trim((string) TENANT_SLUG))
            : strtolower(trim((string) ($school['code'] ?? '')));
        $logoUrl = $this->absoluteUrl((string) LayoutHelper::get('logo_1x1_url', ''))
            ?: $this->absoluteUrl((string) LayoutHelper::get('logo_url', ''));
        $primaryColor = (string) LayoutHelper::get('primary_color', '#075CE5');

        $this->json([
            'status' => 'ok',
            'data' => [
                'service' => 'EducaTudo API',
                'version' => 'v1',
                'tenant' => [
                    'slug' => $tenantSlug !== '' ? $tenantSlug : null,
                    'name' => (string) ($school['name'] ?? 'EducaTudo'),
                ],
                'branding' => [
                    'name' => (string) ($school['name'] ?? 'EducaTudo'),
                    'logo_url' => $logoUrl,
                    'primary_color' => $primaryColor !== '' ? $primaryColor : '#075CE5',
                ],
                'facial_api' => (new FacialRecognitionProviderService())->isConfigured()
                    ? 'configured'
                    : 'not_configured',
                'timestamp' => date(DATE_ATOM),
            ],
        ]);
    }

    private function absoluteUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $base = rtrim(defined('URL') ? URL : '', '/');
        if ($base === '') {
            return $url;
        }
        return $base . '/' . ltrim($url, '/');
    }
}

if (!class_exists('ApiStatusController', false) && class_exists(__NAMESPACE__ . '\\ApiStatusController', false)) {
    class_alias(__NAMESPACE__ . '\\ApiStatusController', 'ApiStatusController');
}
