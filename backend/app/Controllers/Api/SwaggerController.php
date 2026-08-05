<?php
/**
 * EducaTudo - Documentação Swagger/OpenAPI da API dos Pais
 * GET /api/docs - Interface Swagger UI
 * GET /api/openapi.json - Especificação OpenAPI 3.0
 */

require_once __DIR__ . '/../../Core/BaseController.php';

class SwaggerController extends BaseController
{
    /**
     * GET /api/docs - Página HTML com Swagger UI
     */
    public function index()
    {
        $openApiUrl = (defined('URL') ? URL : '') . '/api/openapi.json';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Pais - EducaTudo</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js" charset="UTF-8"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js" charset="UTF-8"></script>
    <script>
        window.onload = function() {
            window.ui = SwaggerUIBundle({
                url: "' . htmlspecialchars($openApiUrl) . '",
                dom_id: "#swagger-ui",
                deepLinking: true,
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

    /**
     * GET /api/openapi.json - Especificação OpenAPI 3.0
     */
    public function openapi()
    {
        $baseUrl = (defined('URL') ? rtrim(URL, '/') : 'https://example.com');
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'API Pais - EducaTudo',
                'description' => 'API REST exclusiva para responsáveis (Pais). Permite visualizar informações acadêmicas apenas dos filhos vinculados. Autenticação via JWT (Bearer).',
                'version' => '1.0.0',
            ],
            'servers' => [['url' => $baseUrl]],
            'paths' => [
                '/api/auth/login' => [
                    'post' => [
                        'tags' => ['Autenticação'],
                        'summary' => 'Login (Pais)',
                        'description' => 'Valida email e senha, verifica se é responsável (parent) e retorna JWT.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email', 'password'],
                                        'properties' => [
                                            'email' => ['type' => 'string', 'format' => 'email'],
                                            'password' => ['type' => 'string', 'format' => 'password'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Sucesso',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'success' => ['type' => 'boolean', 'example' => true],
                                                'data' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'token' => ['type' => 'string', 'description' => 'JWT Bearer'],
                                                        'name' => ['type' => 'string'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '400' => ['description' => 'Email/senha ausentes'],
                            '401' => ['description' => 'Credenciais inválidas'],
                            '429' => ['description' => 'Rate limit excedido'],
                        ],
                    ],
                ],
                '/api/parents/children' => [
                    'get' => [
                        'tags' => ['Filhos'],
                        'summary' => 'Listar filhos',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => [
                                'description' => 'Lista de filhos do responsável',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'success' => ['type' => 'boolean'],
                                                'data' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'id' => ['type' => 'integer'],
                                                            'name' => ['type' => 'string'],
                                                            'class' => ['type' => 'string'],
                                                            'average_grade' => ['type' => 'number'],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '401' => ['description' => 'Token inválido ou ausente'],
                        ],
                    ],
                ],
                '/api/parents/child/{id}/dashboard' => [
                    'get' => [
                        'tags' => ['Por filho'],
                        'summary' => 'Dashboard do filho',
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Resumo (média, totais, última atividade)',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'success' => ['type' => 'boolean'],
                                                'data' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'average_grade' => ['type' => 'number'],
                                                        'total_exams' => ['type' => 'integer'],
                                                        'total_exercises' => ['type' => 'integer'],
                                                        'active_journeys' => ['type' => 'integer'],
                                                        'last_activity' => ['type' => 'string', 'format' => 'date'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '401' => ['description' => 'Não autorizado'],
                            '403' => ['description' => 'Filho não pertence ao responsável'],
                        ],
                    ],
                ],
                '/api/parents/child/{id}/exams' => [
                    'get' => [
                        'tags' => ['Por filho'],
                        'summary' => 'Provas do filho',
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Lista de provas (nota, data, status)'],
                            '401' => ['description' => 'Não autorizado'],
                            '403' => ['description' => 'Acesso negado ao aluno'],
                        ],
                    ],
                ],
                '/api/parents/child/{id}/exercises' => [
                    'get' => [
                        'tags' => ['Por filho'],
                        'summary' => 'Exercícios do filho',
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Total realizados, taxa de acerto, últimos 10'],
                            '401' => ['description' => 'Não autorizado'],
                            '403' => ['description' => 'Acesso negado ao aluno'],
                        ],
                    ],
                ],
                '/api/parents/child/{id}/journeys' => [
                    'get' => [
                        'tags' => ['Por filho'],
                        'summary' => 'Jornadas do filho',
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Jornadas ativas e percentual de progresso'],
                            '401' => ['description' => 'Não autorizado'],
                            '403' => ['description' => 'Acesso negado ao aluno'],
                        ],
                    ],
                ],
                '/api/parents/child/{id}/lesson-plans' => [
                    'get' => [
                        'tags' => ['Por filho'],
                        'summary' => 'Planos de aula do filho',
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Planos de aula vinculados à turma do aluno'],
                            '401' => ['description' => 'Não autorizado'],
                            '403' => ['description' => 'Acesso negado ao aluno'],
                        ],
                    ],
                ],
                '/api/parents/child/{id}/essays' => [
                    'get' => [
                        'tags' => ['Por filho'],
                        'summary' => 'Redações do filho',
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Tema, nota, comentário da correção, data'],
                            '401' => ['description' => 'Não autorizado'],
                            '403' => ['description' => 'Acesso negado ao aluno'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'Token retornado em POST /api/auth/login',
                    ],
                ],
            ],
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
