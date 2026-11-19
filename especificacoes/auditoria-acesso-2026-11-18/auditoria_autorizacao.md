# Auditoria de autorização — fase 2

**Data:** 18/11/2026  
**Escopo:** código PHP do backend. Nenhuma rota foi chamada em produção e nenhum arquivo de aplicação foi alterado.  
**Inventário:** `inventario_rotas.csv` (2.256 rotas lidas dos arquivos de rota).  
**O que esta leitura não é:** não é um teste dinâmico e não é uma revisão linha a linha dos 2.256 métodos. Todas as rotas foram classificadas pelo middleware, pelo prefixo e pelo modelo de tenant. A autorização do recurso foi lida nos fluxos citados abaixo.

## Como a autorização está montada

O isolamento entre escolas não é um `WHERE escola_id` em cada query. O host passa por `TenantResolver` e o PHP usa o PDO daquela escola. Uma rota de tenant sem `escola_id` não é, por si, uma falha. Falha de tenant é trocar de conexão, aceitar `?tenant=` de outra escola ou ler arquivo fora do diretório da escola.

O middleware `Auth` (`backend/app/Middleware/AuthMiddleware.php`, método `handle`, por volta da linha 32) só exige sessão. Ele não exige que o perfil bata com o prefixo `/admin`, `/professor`, `/pais` ou `/dashboard`. Essa segunda checagem fica no construtor de alguns controllers (`AdminBaseController`, `StudentController`, `TeacherController`, `ParentController`, `DocumentoFamiliaAdminController`). Um controller novo dentro do grupo `Auth` que não repetir essa checagem atende qualquer sessão logada.

| Grupo | Rotas | Quem entra |
| --- | ---: | --- |
| `Auth` | 1.910 | qualquer sessão; o perfil é rechecado só em parte dos controllers |
| sem middleware | 257 | público, master ou dispositivo; a sessão, quando existe, está no controller |
| `MobileApiAuth` | 82 | JWT v1 de responsável, com escola e sessão não revogada |
| `ApiAuth` | 7 | JWT legado de responsável, com escola; token v1 é recusado |

Há 1 rota duplicada no código: `GET /api/notificacoes`.

## Controles que o código já faz

Isto foi lido e não é achado aberto.

- API legada de pais: `ApiAuthMiddleware.php` linhas 67–78 recusam token de outra escola e token da API mobile.
- API mobile: `MobileApiAuthMiddleware.php` linhas 47–69 exigem a escola do host e uma linha em `mobile_auth_sessions` não revogada.
- Filho na API: `ParentStudentAccessPolicy::canAccess` e `MobileAcademicController::authorizedStudentId`.
- Filho no painel: `ParentController::getFilhoById` (linhas 63–90) filtra `responsavel_id` ou `alunos_responsaveis`.
- Monitor: `MonitorController::verAluno` chama `fetchAlunoAutorizado` antes de mostrar o aluno.
- Professor, ficha do aluno: `TeacherController::viewStudent` (linha 1408) dá `return` logo no início. O trecho que consultaria o aluno por turma não roda. Não há vazamento por esse método no estado atual; a tela também não abre.
- Arquivo de redação, chat, ticket, arquivo e mensagem: `AcessoArquivoPrivado` só libera esses tipos se achar o registro ou o dono no caminho.
- URL direta de redação, arquivo, mensagem, ticket e caderno: `pastaDireta` nega ou exige `podeLer`.
- Nginx do repositório: `location ^~ /uploads/` e `/public/uploads/` reescrevem para `index.php`. Não entregam a pasta direto.
- Webhook ZapSign: `ZapSignWebhookController::handle` confere o slug do host e `validarWebhookToken`.
- `GET /teste-exercicios` aponta para `MasterTesteExerciciosController`, que chama `requireMaster`.
- Apps externos: `consumeCredit` só segue depois de `resolveExternalCreditContext`, que exige token.
- Dispositivo facial: os métodos depois de `pair` chamam `authenticatedDevice`. `pair` exige código de 6 dígitos de uso único.

## Achados

### A1 — Alto — comprovado no código — mídia privada sem dono

**Arquivo:** `backend/app/Infra/Services/AcessoArquivoPrivado.php`, linhas 16–26, chamado por `MediaServeController::serve` linhas 82–90.  
**Rota:** `GET /media/serve`.

`podeLer` só consulta o registro quando o tipo está em `chat`, `redacoes`, `tickets`, `arquivos` ou `mensagens`. Para qualquer outro tipo privado, devolve verdadeiro para toda sessão com `user_id` e `user_type`.

Tipos privados que caem nessa volta, em `MediaServeController` linhas 60–64 e 82–86: `provas_questoes`, `jornadas_exercicios`, `jornadas_documentos`, `jornadas_videos`, `apostilas`, `notifications`.

**Fluxo:** sessão de um aluno da escola A, `GET /media/serve?type=jornadas_documentos&key=<chave de outro aluno da mesma escola>`. O middleware não existe nessa rota. O controller exige login porque o tipo não é público. `podeLer` não olha o dono e devolve o arquivo.

**Limite:** `?tenant=` de outra escola é recusado para esses tipos (`MediaServeController.php` linhas 102–107). Não é, por este trecho, leitura da escola B a partir do host da escola A.

**Correção:** para esses tipos, resolver o registro na conexão da escola e negar quando o usuário não for o dono, o responsável vinculado, o professor da turma ou o admin. Na falta de registro, negar.

**Teste dinâmico ainda necessário:** confirmar com duas contas fictícias da mesma escola e uma chave real de homologação. O código já toma a decisão de liberar.

### A2 — Alto — comprovado no código, efeito depende do arquivo — pasta de upload aberta por omissão

**Arquivo:** `AcessoArquivoPrivado::pastaDireta`, linhas 47–82. Quem chama é `backend/bootstrap/app.php`, por volta das linhas 754–779.  
**Não é uma rota nomeada.** O Nginx manda `/uploads/` e `/public/uploads/` para o `index.php`.

A função lista os prefixos privados e, no final, devolve `'publico'`. Qualquer arquivo em `public/uploads/` cujo caminho não seja caderno, redação, arquivo, mensagem, ticket ou o prefixo de exercício/questão sai sem sessão, com `Cache-Control: public`.

**Fluxo:** `GET /uploads/<pasta-nao-listada>/<arquivo>` no host da escola. Se o arquivo existe no disco desse tenant, o bootstrap envia o conteúdo.

**Entre escolas:** só ocorre se o arquivo da escola B estiver no disco servido pelo host da escola A. Isso não foi comprovado daqui. Dentro da mesma escola, o default é permitir.

**Correção:** inverter o padrão. O que não estiver numa lista pública explícita deve ser `'negado'`.

### A3 — Médio — comprovado no código — imagem de exercício sem login

**Arquivo:** `backend/app/Dominio/Escola/Controllers/ServeImagemExercicioController.php`, método `serve`, linhas 19–51.  
**Rota:** `GET /uploads/jornadas/exercicios/{filename}`.

Não há sessão. O nome do arquivo, se existir no storage da escola do host, é lido e pode ir para cache público (`max-age=86400`). O nome aceita barra, então um caminho interno do tipo, sem `..`, também passa. `pastaDireta` devolve `'rota'` para esse prefixo e não aplica `podeLer`.

**Correção:** exigir sessão e a mesma regra de dono/turma de `jornadas_exercicios`, ou servir só enunciado marcado como público no registro.

### A4 — Médio — comprovado no código — foto e avatar sem login

**Arquivo:** `MediaServeController.php`, `PUBLIC_TYPES`, linhas 41–47.  
**Rota:** `GET /media/serve?type=avatars|professores|essays_proposals|layout|dashboard_sliders`.

`layout` e slider de capa são material de marca. `avatars` e `professores` são imagem de pessoa e saem sem sessão para quem souber a chave. `essays_proposals` é proposta de redação, não a redação do aluno; o aluno continua no tipo `redacoes`, que passa por `podeLer`.

**Correção:** tirar `avatars` e `professores` dos tipos públicos, ou exigir sessão da mesma escola.

### A5 — Médio — comprovado no código — descoberta de aluno no primeiro acesso

**Arquivo:** `AuthController::validarPrimeiroAcessoResult`, por volta das linhas 283–346, view `backend/app/Views/auth/primeiro-acesso.php`.  
**Rotas:** `POST /primeiro-acesso` e `POST /primeiro-acesso/validar`.

O nome informado volta para o formulário escapado (`htmlspecialchars`). Não é XSS. A resposta muda se o nome existe na escola: redireciona para criar acesso, pede desambiguação ou devolve "não encontrado". `GET /primeiro-acesso/alunos` responde 404 e não lista a turma.

**Correção:** mesma resposta para nome existente e inexistente, com limite de tentativas. O vínculo segue num segundo passo, depois de um dado que o aluno já tenha.

### A6 — Baixo — comprovado no código — token "entrar como" se o Redis cair

**Arquivo:** `AuthController::entrarComo`, linhas 1221–1238.  
**Rota:** `GET /auth/entrar-como?token=`.

O token é HMAC, de uma escola (`escola_id` tem de ser o `TENANT_ID`) e com prazo. Se o `jti` já foi usado, o Redis recusa. O comentário e o fluxo dizem que, sem Redis, a recusa de reuso não ocorre. Quem tiver o token ainda válido consegue usar de novo até expirar.

**Correção:** se o Redis não responder, recusar o token em vez de seguir.

### A7 — Médio — estrutural, sem rota explorada nesta leitura — perfil não está no middleware

**Arquivo:** `AuthMiddleware::handle` e `backend/config/routes.php` linhas 18–30.

As 1.910 rotas com `Auth` aceitam aluno, professor, pai, monitor ou admin. A amostra lida (`AdminBaseController`, `StudentController`, `TeacherController`, `ParentController`, `DocumentoFamiliaAdminController`) devolve o perfil errado para o próprio painel. Não foi aberto um controller de `/admin` que sirva dado sem essa checagem. O risco é real para o próximo controller que esquecer o `tipo`.

**Correção:** no `Auth`, recusar a rota quando o prefixo não for o perfil da sessão. Admin com permissão fina continua na matriz que o middleware já aplica.

### A8 — Suspeita — precisa de teste em homologação — S3 sem prefixo da escola

**Arquivo:** `MediaServeController.php`, linhas 145–158.

Depois de falhar a chave com prefixo do tenant, o mesmo tipo e a mesma chave são buscados com `tenant_prefix` falso. Se o bucket for compartilhado e a chave existir sem pasta da escola, o arquivo sai para quem já passou na checagem anterior. Para tipo público, essa checagem é nenhuma.

Não dá para afirmar daqui se o bucket de produção é compartilhado nem se ainda existem chaves antigas.

**Correção:** desligar o fallback para objeto novo. Manter só uma lista fechada de chaves legadas, se ainda forem necessárias.

### A9 — Informativo — alias público de imagem gerada

**Arquivo:** `docker/nginx/vps-prod-http.conf`, `location ^~ /storage/images/`.

Esse alias entrega arquivo sem PHP. O comentário no bootstrap trata a pasta como imagem gerada pela API, não como redação ou chat. Chat em `/storage/chat/` não tem alias e o PHP exige `podeLer`. Não há evidência, nesta leitura, de arquivo de aluno nessa pasta.

## O que não foi promovido a falha

- Ausência de `escola_id` nas queries de tenant. O isolamento previsto é a conexão.
- `GET /api/parents/child/{id}/*` e `GET /api/v1/students/{id}/*`. O código consulta o vínculo antes de responder. Falta o teste com dois responsáveis fictícios para fechar.
- Cookie e headers HTTP. Fora desta fase.
- `TeacherController::viewStudent`. O método retorna antes da consulta.

## Contagem

| Medida | Quantidade |
| --- | ---: |
| Rotas identificadas | 2.256 |
| Rotas classificadas por middleware, perfil e tenant | 2.256 |
| Rotas de área sensível (caminho com aluno, nota, arquivo, prova, documento ou turma) | 850 |
| Métodos lidos até a decisão de autorização | os citados nos achados e nos controles; não os 2.256 |
| Achados de autorização | 9 (2 altos comprovados no código, 3 médios comprovados, 1 baixo comprovado, 1 estrutural, 1 suspeita de storage, 1 informativo) |
| Rotas nomeadas ligadas a esses achados | `GET /media/serve`, `GET /uploads/jornadas/exercicios/{filename}`, `POST /primeiro-acesso`, `POST /primeiro-acesso/validar`, `GET /auth/entrar-como`, mais o acesso direto a `/uploads/` e `/public/uploads/` |
