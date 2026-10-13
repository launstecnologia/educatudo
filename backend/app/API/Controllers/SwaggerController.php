<?php

namespace App\API\Controllers;

use App\API\Services\EspecificacaoOpenApiService;
use App\Core\BaseController;

/**
 * Documentação da API real do responsável.
 * GET /api/docs — guia com menu lateral
 * GET /api/openapi.json — OpenAPI 3
 */
class SwaggerController extends BaseController
{
    public function index()
    {
        $openapiUrl = (defined('URL') ? URL : '') . '/api/openapi.json';
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/../Views/documentacao.php';
        exit;
    }

    public function openapi()
    {
        $baseUrl = rtrim(defined('URL') ? (string) URL : '', '/');
        $spec = (new EspecificacaoOpenApiService())->montar($baseUrl);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!class_exists('SwaggerController', false) && class_exists(__NAMESPACE__ . '\\SwaggerController', false)) {
    class_alias(__NAMESPACE__ . '\\SwaggerController', 'SwaggerController');
}
