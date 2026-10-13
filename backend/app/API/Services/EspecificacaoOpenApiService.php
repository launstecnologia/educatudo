<?php

namespace App\API\Services;

/** OpenAPI 3 da API real do responsável, com caminhos em português. */
class EspecificacaoOpenApiService
{
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
                'version' => '1.0.0',
                'description' => $this->guia(),
            ],
            'servers' => [[
                'url' => $baseUrl,
                'description' => 'Escola deste endereço. As respostas saem do banco real.',
            ]],
            'tags' => [
                ['name' => '1. Autenticação', 'description' => 'Comece por aqui.'],
                ['name' => '2. Responsável', 'description' => 'Quem entrou e quais filhos pode ver.'],
                ['name' => '3. Acadêmico', 'description' => 'Use o id devolvido em /api/alunos.'],
                ['name' => '4. Comunicação', 'description' => 'Comunicados e calendário daquele aluno.'],
                ['name' => '5. Financeiro', 'description' => 'Faturas do aluno e dados para pagamento.'],
                ['name' => '6. Notificações', 'description' => 'Avisos enviados ao responsável, não a um aluno específico.'],
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'Cole só o access_token devolvido em /api/autenticacao/entrar. A documentação envia o prefixo Bearer.',
                    ],
                ],
            ],
        ];
    }

    private function guia(): string
    {
        return <<<'TXT'
Esta API lê os dados reais da escola do endereço em que você abriu a página. Não existe base de teste: use o CPF e a senha de um responsável cadastrado.

Os caminhos estão em português. Os nomes dos campos no JSON seguem o contrato já usado pelo aplicativo (por exemplo access_token, name, grade). Em cada rota, o texto explica o que o campo significa.

Passo a passo

1. Chame POST /api/autenticacao/entrar com o CPF (11 dígitos) e a senha do responsável.
2. Na resposta, copie data.access_token. Guarde também data.refresh_token.
3. Cole o access_token no campo Token, no menu lateral.
4. Chame GET /api/alunos. Cada item traz o id do filho vinculado a esse responsável.
5. Use esse id no lugar de {id} nas rotas /api/alunos/{id}/...
6. Quando o access_token expirar, chame POST /api/autenticacao/renovar com o refresh_token. A resposta traz um par novo: passe a usar o access_token novo e o refresh_token novo.
7. Para encerrar a sessão de verdade, chame POST /api/autenticacao/sair. O token deixa de valer.

Se o aluno não estiver vinculado ao responsável, a API responde 404 e não informa se o id existe. Toda rota abaixo do login exige o token, confere se o responsável ainda está ativo e se o token foi emitido para esta escola.
TXT;
    }

    /** @return list<array<string,mixed>> */
    private function operacoes(): array
    {
        $aluno = ['name' => 'id', 'description' => 'Id do aluno devolvido por GET /api/alunos.', 'example' => 1];

        return [
            $this->op('post', '/api/autenticacao/entrar', '1. Autenticação', 'Entrar', false, $this->passos(
                'Valida o CPF e a senha de um responsável ativo e abre uma sessão.',
                'Envie cpf com 11 dígitos e senha. O campo password também é aceito, no lugar de senha.',
                'Se estiver certo, data.access_token entra nas próximas chamadas e data.refresh_token serve para renovar. data.parent traz id, name, email, cpf, phone e must_change_password.',
                'CPF ou senha errados devolvem 401. Muitas tentativas seguidas devolvem 429.'
            ), [], $this->corpo([
                'cpf' => 'CPF do responsável, só números.',
                'senha' => 'Senha do portal. Pode enviar password no lugar de senha.',
            ], ['cpf', 'senha'])),
            $this->op('post', '/api/autenticacao/renovar', '1. Autenticação', 'Renovar o token', false, $this->passos(
                'Troca um refresh_token ainda válido por um access_token novo, sem pedir a senha de novo.',
                'Envie o refresh_token da última entrada ou renovação. O campo token_atualizacao também é aceito.',
                'Guarde o refresh_token novo da resposta. O anterior deixa de valer.',
                'Token expirado ou já usado devolve 401. Aí é preciso entrar de novo.'
            ), [], $this->corpo([
                'refresh_token' => 'Token longo devolvido no login. token_atualizacao também é aceito.',
            ], ['refresh_token'])),
            $this->op('post', '/api/autenticacao/sair', '1. Autenticação', 'Sair', true, $this->passos(
                'Encerra a sessão deste token. Ele deixa de ser aceito nas outras rotas.',
                'Envie o access_token em Authorize. Não há corpo.',
                'Resposta vazia com status 204.'
            )),
            $this->op('get', '/api/eu', '2. Responsável', 'Quem está autenticado', true, $this->passos(
                'Devolve o cadastro do responsável do token, relido no banco a cada chamada.',
                'Use para confirmar o nome e se a senha precisa ser trocada (must_change_password).',
                'Campos: id, name, email, cpf, phone, must_change_password.'
            )),
            $this->op('get', '/api/alunos', '2. Responsável', 'Listar filhos', true, $this->passos(
                'Lista só os alunos vinculados ao responsável do token.',
                'Guarde data[].id. É esse número que entra em {id} nas rotas seguintes.',
                'Cada item traz name, photo_url e class (id, name, grade) quando o aluno tem turma.',
                'meta.count é a quantidade de filhos.'
            )),
            $this->op('get', '/api/alunos/{id}/painel', '3. Acadêmico', 'Resumo do aluno', true, $this->passos(
                'Primeira tela depois de escolher o filho.',
                'Traz média das provas (average_grade), média das redações (essay_average), totais de provas, exercícios e redações, última atividade, próxima prova e se o último acesso foi uma entrada na escola.',
                'As médias de prova e de redação não se misturam: usam escalas diferentes.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/provas', '3. Acadêmico', 'Provas', true, $this->passos(
                'Lista as provas que esse aluno já realizou, da mais recente para a mais antiga.',
                'Cada item traz title, subject_name, grade, status, acertos, erros, accuracy_percent e completed_at.',
                'Quando a prova faz parte de um bloco, também vêm block_title, term e school_year.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/exercicios', '3. Acadêmico', 'Exercícios', true, $this->passos(
                'Resume os exercícios feitos nas jornadas desse aluno.',
                'data.total_completed é a quantidade, data.correct_count quantos tiveram pontuação, data.accuracy_percent a taxa.',
                'data.latest lista os 10 últimos, com journey_title, score e completed_at.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/jornadas', '3. Acadêmico', 'Jornadas', true, $this->passos(
                'Lista as jornadas da turma desse aluno e o quanto ele andou.',
                'Olhe progress_percent, completed_modules, total_modules, started e completed.',
                'subject_name e teacher_name identificam a matéria e o professor.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/planos-de-aula', '3. Acadêmico', 'Planos de aula', true, $this->passos(
                'Lista os planos de aula da turma desse aluno.',
                'Cada item tem id, title, objectives, teacher_name, subject_name e created_at.',
                'O id deste item é o {planId} da rota de detalhe.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/planos-de-aula/{planId}', '3. Acadêmico', 'Detalhe do plano de aula', true, $this->passos(
                'Devolve um plano, desde que ele seja da turma desse aluno.',
                'Além do resumo, traz class, class_dates e o conteúdo da aula.',
                'Plano de outra turma responde 404, sem dizer se o plano existe.'
            ), [$aluno, ['name' => 'planId', 'description' => 'Id do plano devolvido na listagem.', 'example' => 1]]),
            $this->op('get', '/api/alunos/{id}/jornadas-de-redacao', '3. Acadêmico', 'Jornadas de redação', true, $this->passos(
                'Lista os temas de redação ligados às jornadas da turma.',
                'Cada item tem theme, description, due_at, submission_status e grade quando já houver nota.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/redacoes', '3. Acadêmico', 'Redações', true, $this->passos(
                'Lista as redações enviadas por esse aluno, exceto as ocultas.',
                'Cada item tem theme, grade, feedback, is_draft, created_at e corrected_at.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/avisos', '3. Acadêmico', 'Avisos do mural', true, $this->passos(
                'Lista os recados do mural visíveis para a turma desse aluno.',
                'Cada item tem title, content, published_at, author_name e subject_name.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/boletim', '3. Acadêmico', 'Boletim', true, $this->passos(
                'Caminho reservado ao boletim desse aluno.',
                'Hoje a plataforma não libera o boletim detalhado para o responsável por esta API. A resposta vem com data vazio de propósito.',
                'O resumo de notas que o responsável pode ver está em /painel e em /provas.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/acessos', '3. Acadêmico', 'Entradas e saídas', true, $this->passos(
                'Lista os registros de entrada e saída desse aluno na escola.',
                'data.status diz se o último evento foi entrada (is_at_school) e quando.',
                'data.events traz id, kind (entrada ou saída), event_at, confidence e notified.',
                'Opcional: de e ate no formato AAAA-MM-DD. from e to também são aceitos.'
            ), [$aluno], null, [
                ['name' => 'de', 'in' => 'query', 'required' => false, 'description' => 'Data inicial, AAAA-MM-DD.', 'schema' => ['type' => 'string', 'format' => 'date']],
                ['name' => 'ate', 'in' => 'query', 'required' => false, 'description' => 'Data final, AAAA-MM-DD.', 'schema' => ['type' => 'string', 'format' => 'date']],
            ]),
            $this->op('get', '/api/alunos/{id}/financeiro', '5. Financeiro', 'Financeiro do aluno', true, $this->passos(
                'Resume o que esse aluno tem em aberto.',
                'data.summary traz pending_count, pending_amount, overdue_amount e contracts_count.',
                'data.invoices lista as faturas. Guarde id e source: source charge vira origem cobranca na rota de pagamento; contract_installment vira parcela.',
                'data.contracts lista os contratos, quando existirem.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/financeiro/faturas/{origem}/{faturaId}/pagamento', '5. Financeiro', 'Dados para pagar uma fatura', true, $this->passos(
                'Devolve linha digitável e Pix de uma fatura desse aluno.',
                'origem é cobranca ou parcela, conforme o source da listagem. faturaId é o id da fatura.',
                'A resposta marca payment.simulation quando o código é gerado para consulta. Não registra o pagamento aqui.'
            ), [
                $aluno,
                ['name' => 'origem', 'description' => 'cobranca ou parcela.', 'example' => 'cobranca', 'schema' => ['type' => 'string']],
                ['name' => 'faturaId', 'description' => 'Id da fatura em data.invoices.', 'example' => 1],
            ]),
            $this->op('get', '/api/alunos/{id}/comunicados', '4. Comunicação', 'Comunicados', true, $this->passos(
                'Lista os comunicados publicados que esse aluno pode ver.',
                'Cada item tem title, content (resumo), priority, allow_replies, is_read, read_at, published_at e reply_count.',
                'meta.unread_count diz quantos ainda não foram lidos. O id do item é o {communicationId} das rotas seguintes.'
            ), [$aluno]),
            $this->op('get', '/api/alunos/{id}/comunicados/{communicationId}', '4. Comunicação', 'Detalhe do comunicado', true, $this->passos(
                'Abre um comunicado da lista.',
                'O texto completo vem em content. Se não for desse aluno, a resposta é 404.'
            ), [$aluno, ['name' => 'communicationId', 'description' => 'Id do comunicado na listagem.', 'example' => 1]]),
            $this->op('post', '/api/alunos/{id}/comunicados/{communicationId}/lido', '4. Comunicação', 'Marcar comunicado como lido', true, $this->passos(
                'Grava a leitura desse comunicado para o responsável e esse aluno.',
                'Não envie corpo. A resposta traz is_read e read_at.'
            ), [$aluno, ['name' => 'communicationId', 'description' => 'Id do comunicado.', 'example' => 1]]),
            $this->op('post', '/api/alunos/{id}/comunicados/{communicationId}/respostas', '4. Comunicação', 'Responder comunicado', true, $this->passos(
                'Envia uma resposta, só se o comunicado permitir (allow_replies).',
                'Corpo: message ou mensagem, com até 4.000 caracteres.',
                'A resposta traz o id gravado, o texto e created_at. Status 201.'
            ), [$aluno, ['name' => 'communicationId', 'description' => 'Id do comunicado.', 'example' => 1]], $this->corpo([
                'message' => 'Texto da resposta. mensagem também é aceito.',
            ], ['message'])),
            $this->op('get', '/api/alunos/{id}/calendario', '4. Comunicação', 'Calendário', true, $this->passos(
                'Lista eventos do calendário visíveis para esse aluno.',
                'Cada item tem title, início, fim, status e is_read. O id é o {eventId} da rota de leitura.',
                'Opcional: de e ate no formato AAAA-MM-DD. Sem filtro, a janela vai de um mês atrás a seis meses à frente.'
            ), [$aluno], null, [
                ['name' => 'de', 'in' => 'query', 'required' => false, 'description' => 'Data inicial, AAAA-MM-DD.', 'schema' => ['type' => 'string', 'format' => 'date']],
                ['name' => 'ate', 'in' => 'query', 'required' => false, 'description' => 'Data final, AAAA-MM-DD.', 'schema' => ['type' => 'string', 'format' => 'date']],
            ]),
            $this->op('post', '/api/alunos/{id}/calendario/{eventId}/lido', '4. Comunicação', 'Marcar evento como lido', true, $this->passos(
                'Grava que o responsável viu esse evento para esse aluno.',
                'Não envie corpo. A resposta traz is_read e read_at.'
            ), [$aluno, ['name' => 'eventId', 'description' => 'Id do evento na listagem do calendário.', 'example' => 1]]),
            $this->op('get', '/api/notificacoes', '6. Notificações', 'Notificações do responsável', true, $this->passos(
                'Lista os avisos enviados ao responsável do token, não a um aluno.',
                'Cada item tem id, title, message, url, is_delivered, delivered_at, is_read e read_at.',
                'O id entra na rota de marcar como lida.'
            )),
            $this->op('post', '/api/notificacoes/{id}/lida', '6. Notificações', 'Marcar notificação como lida', true, $this->passos(
                'Marca como lida a notificação desse responsável.',
                'Não envie corpo. Id inexistente ou de outra pessoa responde 404.'
            ), [['name' => 'id', 'description' => 'Id da notificação na listagem.', 'example' => 1]]),
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
        string $description,
        array $pathParams = [],
        ?array $body = null,
        array $query = []
    ): array {
        return compact('method', 'path', 'tag', 'summary', 'auth', 'description') + [
            'path_params' => $pathParams,
            'body' => $body,
            'query' => $query,
        ];
    }

    private function passos(string ...$linhas): string
    {
        $texto = '';
        $n = 1;
        foreach ($linhas as $linha) {
            $texto .= $n . '. ' . $linha . "\n";
            $n++;
        }
        return trim($texto);
    }

    /** @param array<string,mixed> $op @return array<string,mixed> */
    private function operacao(array $op): array
    {
        $item = [
            'tags' => [$op['tag']],
            'summary' => $op['summary'],
            'description' => $op['description'],
            'responses' => [
                '200' => ['description' => 'Sucesso. O conteúdo vem em data. Listas também trazem meta.count.'],
                '401' => ['description' => 'Token ausente, expirado ou de outra escola. Entre de novo.'],
            ],
        ];
        if ($op['auth']) {
            $item['security'] = [['bearerAuth' => []]];
        } else {
            unset($item['responses']['401']);
            $item['responses']['401'] = ['description' => 'CPF, senha ou refresh token inválidos.'];
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
                $item['parameters'][] = $param;
            }
        }
        if (str_contains($op['path'], '{id}')) {
            $item['responses']['404'] = ['description' => 'Aluno não vinculado a este responsável, ou o recurso não é dele.'];
        }
        if ($op['path'] === '/api/autenticacao/sair') {
            $item['responses']['204'] = ['description' => 'Sessão encerrada. O corpo vem vazio.'];
            unset($item['responses']['200']);
        }
        if ($op['body'] !== null) {
            $item['requestBody'] = $op['body'];
        }
        return $item;
    }

    /**
     * @param array<string,string> $campos
     * @param list<string> $obrigatorios
     * @return array<string,mixed>
     */
    private function corpo(array $campos, array $obrigatorios): array
    {
        $properties = [];
        foreach ($campos as $nome => $descricao) {
            $properties[$nome] = ['type' => 'string', 'description' => $descricao];
        }
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => $obrigatorios,
                        'properties' => $properties,
                    ],
                ],
            ],
        ];
    }
}
