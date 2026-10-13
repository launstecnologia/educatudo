<?php

namespace App\API\Services;

/** Especificação OpenAPI 3 da API única do responsável, com exemplos do sandbox. */
class EspecificacaoOpenApiService
{
    private HomologacaoApiService $dados;

    public function __construct(?HomologacaoApiService $dados = null)
    {
        $this->dados = $dados ?? new HomologacaoApiService();
    }

    /** @return array<string,mixed> */
    public function montar(string $baseUrl): array
    {
        $paths = [];
        foreach ($this->operacoes() as $op) {
            $paths[$op['path']][$op['method']] = $this->operacao($op);
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'API do responsável — EducaTudo',
                'version' => '1.0.0-homologacao',
                'description' => $this->descricao(),
            ],
            'servers' => [[
                'url' => $baseUrl,
                'description' => 'Homologação com dados fictícios',
            ]],
            'tags' => [
                ['name' => 'Autenticação'],
                ['name' => 'Responsável'],
                ['name' => 'Acadêmico'],
                ['name' => 'Comunicação'],
                ['name' => 'Financeiro'],
                ['name' => 'Notificações'],
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'sandbox',
                        'description' => 'Token devolvido por POST /auth/login. No teste, use o valor homologacao.teste.',
                    ],
                ],
            ],
        ];
    }

    private function descricao(): string
    {
        $email = HomologacaoApiService::EMAIL;
        $senha = HomologacaoApiService::SENHA;

        return <<<TXT
Contrato único para o app e para sistemas que integrem o acompanhamento do responsável. O "Try it out" desta página aponta para o sandbox e não lê o banco da escola.

Usuário de teste:
- e-mail: {$email}
- senha: {$senha}

1. Execute POST /auth/login.
2. Copie access_token.
3. Clique em Authorize e cole o token.

Alunos fictícios: 101 Ana Souza (7º A) e 102 Pedro Souza (5º B). Plano 501, comunicado 801 e fatura 901 (source charge) pertencem à Ana. Plano 502 e comunicado 802 pertencem ao Pedro. Evento de calendário 701 e notificação 601 valem para os dois.

O sandbox só responde com APP_ENV=development. Em produção estas rotas devolvem 404. As APIs já em uso (/api/parents e /api/v1) continuam nos seus endereços.
TXT;
    }

    /** @return list<array<string,mixed>> */
    private function operacoes(): array
    {
        $aluno = ['name' => 'id', 'description' => 'Id do aluno vinculado. No teste: 101 ou 102.', 'example' => 101];

        return [
            $this->op('post', '/auth/login', 'Autenticação', 'Login', false, 'login', [], $this->corpoLogin()),
            $this->op('post', '/auth/refresh', 'Autenticação', 'Renovar token', false, 'refresh', [], $this->corpoRefresh()),
            $this->op('post', '/auth/logout', 'Autenticação', 'Encerrar sessão', true, 'logout'),
            $this->op('get', '/me', 'Responsável', 'Perfil do responsável', true, 'me'),
            $this->op('get', '/students', 'Responsável', 'Filhos vinculados', true, 'students'),
            $this->op('get', '/students/{id}/dashboard', 'Acadêmico', 'Resumo do aluno', true, 'dashboard', [$aluno]),
            $this->op('get', '/students/{id}/exams', 'Acadêmico', 'Provas', true, 'exams', [$aluno]),
            $this->op('get', '/students/{id}/exercises', 'Acadêmico', 'Exercícios', true, 'exercises', [$aluno]),
            $this->op('get', '/students/{id}/journeys', 'Acadêmico', 'Jornadas', true, 'journeys', [$aluno]),
            $this->op('get', '/students/{id}/lesson-plans', 'Acadêmico', 'Planos de aula', true, 'lesson_plans', [$aluno]),
            $this->op('get', '/students/{id}/lesson-plans/{planId}', 'Acadêmico', 'Detalhe do plano de aula', true, 'lesson_plan', [
                $aluno,
                ['name' => 'planId', 'description' => 'No teste: 501 para a Ana, 502 para o Pedro.', 'example' => 501],
            ]),
            $this->op('get', '/students/{id}/writing-journeys', 'Acadêmico', 'Jornadas de redação', true, 'writing_journeys', [$aluno]),
            $this->op('get', '/students/{id}/essays', 'Acadêmico', 'Redações', true, 'essays', [$aluno]),
            $this->op('get', '/students/{id}/notices', 'Acadêmico', 'Avisos do mural', true, 'notices', [$aluno]),
            $this->op('get', '/students/{id}/report-card', 'Acadêmico', 'Boletim ilustrativo', true, 'report_card', [$aluno]),
            $this->op('get', '/students/{id}/access-events', 'Acadêmico', 'Entradas e saídas', true, 'access_events', [$aluno], null, [
                ['name' => 'from', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date'], 'example' => '2026-10-01'],
                ['name' => 'to', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date'], 'example' => '2026-10-13'],
            ]),
            $this->op('get', '/students/{id}/finance', 'Financeiro', 'Financeiro do aluno', true, 'finance', [$aluno]),
            $this->op('get', '/students/{id}/finance/invoices/{source}/{invoiceId}/payment', 'Financeiro', 'Dados de pagamento da fatura', true, 'payment', [
                $aluno,
                ['name' => 'source', 'description' => 'charge ou contract_installment. No teste: charge.', 'example' => 'charge', 'schema' => ['type' => 'string']],
                ['name' => 'invoiceId', 'description' => 'No teste: 901, só da Ana.', 'example' => 901],
            ]),
            $this->op('get', '/students/{id}/school-communications', 'Comunicação', 'Comunicados', true, 'communications', [$aluno]),
            $this->op('get', '/students/{id}/school-communications/{communicationId}', 'Comunicação', 'Detalhe do comunicado', true, 'communication', [
                $aluno,
                ['name' => 'communicationId', 'description' => 'No teste: 801 para a Ana, 802 para o Pedro.', 'example' => 801],
            ]),
            $this->op('post', '/students/{id}/school-communications/{communicationId}/read', 'Comunicação', 'Marcar comunicado como lido', true, 'communication_read', [
                $aluno,
                ['name' => 'communicationId', 'example' => 801],
            ]),
            $this->op('post', '/students/{id}/school-communications/{communicationId}/replies', 'Comunicação', 'Responder comunicado', true, 'communication_reply', [
                $aluno,
                ['name' => 'communicationId', 'example' => 801],
            ], $this->corpoMensagem()),
            $this->op('get', '/students/{id}/calendar-events', 'Comunicação', 'Calendário', true, 'calendar', [$aluno]),
            $this->op('post', '/students/{id}/calendar-events/{eventId}/read', 'Comunicação', 'Marcar evento como lido', true, 'calendar_read', [
                $aluno,
                ['name' => 'eventId', 'description' => 'No teste: 701.', 'example' => 701],
            ]),
            $this->op('get', '/notifications', 'Notificações', 'Notificações do responsável', true, 'notifications'),
            $this->op('post', '/notifications/{id}/read', 'Notificações', 'Marcar notificação como lida', true, 'notification_read', [
                ['name' => 'id', 'description' => 'No teste: 601.', 'example' => 601],
            ]),
        ];
    }

    /**
     * @param list<array<string,mixed>> $pathParams
     * @param array<string,mixed>|null $body
     * @param list<array<string,mixed>> $query
     * @return array<string,mixed>
     */
    private function op(
        string $method,
        string $path,
        string $tag,
        string $summary,
        bool $auth,
        string $exemplo,
        array $pathParams = [],
        ?array $body = null,
        array $query = []
    ): array {
        return [
            'method' => $method,
            'path' => $path,
            'tag' => $tag,
            'summary' => $summary,
            'auth' => $auth,
            'exemplo' => $exemplo,
            'path_params' => $pathParams,
            'body' => $body,
            'query' => $query,
        ];
    }

    /** @param array<string,mixed> $op @return array<string,mixed> */
    private function operacao(array $op): array
    {
        $item = [
            'tags' => [$op['tag']],
            'summary' => $op['summary'],
            'responses' => [
                '200' => [
                    'description' => 'Sucesso',
                    'content' => [
                        'application/json' => [
                            'example' => $this->dados->exemplo((string) $op['exemplo']),
                        ],
                    ],
                ],
            ],
        ];

        if ($op['auth']) {
            $item['security'] = [['bearerAuth' => []]];
            $item['responses']['401'] = ['description' => 'Token ausente ou inválido'];
        }
        if ($op['path_params'] !== [] || $op['query'] !== []) {
            $item['parameters'] = [];
            foreach ($op['path_params'] as $param) {
                $item['parameters'][] = [
                    'name' => $param['name'],
                    'in' => 'path',
                    'required' => true,
                    'description' => $param['description'] ?? '',
                    'schema' => $param['schema'] ?? ['type' => is_int($param['example'] ?? 1) ? 'integer' : 'string'],
                    'example' => $param['example'] ?? null,
                ];
            }
            foreach ($op['query'] as $param) {
                $param['required'] = false;
                $item['parameters'][] = $param;
            }
        }
        if ($op['body'] !== null) {
            $item['requestBody'] = $op['body'];
        }
        if (str_contains($op['path'], '{id}')) {
            $item['responses']['404'] = ['description' => 'Aluno ou recurso não encontrado para este responsável'];
        }

        return $item;
    }

    /** @return array<string,mixed> */
    private function corpoLogin(): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['email', 'password'],
                        'properties' => [
                            'email' => ['type' => 'string', 'format' => 'email'],
                            'password' => ['type' => 'string'],
                        ],
                    ],
                    'example' => [
                        'email' => HomologacaoApiService::EMAIL,
                        'password' => HomologacaoApiService::SENHA,
                    ],
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function corpoRefresh(): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'example' => ['refresh_token' => HomologacaoApiService::REFRESH],
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function corpoMensagem(): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'example' => ['message' => 'Confirmado, Ana estará presente.'],
                ],
            ],
        ];
    }
}
