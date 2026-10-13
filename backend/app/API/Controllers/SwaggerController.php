<?php

namespace App\API\Controllers;

use App\API\Services\EspecificacaoOpenApiService;
use App\Core\BaseController;

/**
 * Documentação da API única do responsável.
 * GET /api/docs — Swagger UI
 * GET /api/openapi.json — OpenAPI 3, com exemplos do sandbox
 */
class SwaggerController extends BaseController
{
    public function index()
    {
        $openApiUrl = (defined('URL') ? URL : '') . '/api/openapi.json';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API do responsável — EducaTudo</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js" charset="UTF-8"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js" charset="UTF-8"></script>
    <script>
        window.onload = function() {
            window.ui = SwaggerUIBundle({
                url: "' . htmlspecialchars($openApiUrl, ENT_QUOTES, 'UTF-8') . '",
                dom_id: "#swagger-ui",
                deepLinking: true,
                persistAuthorization: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                layout: "StandaloneLayout"
            });
        };
    </script>
</body>
</html>';
        exit;
    }

    public function openapi()
    {
        $baseUrl = rtrim(defined('URL') ? (string) URL : '', '/') . '/api/sandbox';
        $spec = (new EspecificacaoOpenApiService())->montar($baseUrl);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!class_exists('SwaggerController', false) && class_exists(__NAMESPACE__ . '\\SwaggerController', false)) {
    class_alias(__NAMESPACE__ . '\\SwaggerController', 'SwaggerController');
}
