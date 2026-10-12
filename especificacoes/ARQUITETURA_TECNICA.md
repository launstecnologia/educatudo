# EducaTudo — Arquitetura e guia técnico do código

> Levantamento técnico do repositório em 21 de junho de 2026. Este documento descreve o código existente; quando uma prática desejada difere do estado atual, isso é indicado explicitamente.

## 1. Visão executiva

O EducaTudo é um ecossistema educacional SaaS multi-tenant composto por quatro aplicações principais:

1. **Plataforma web PHP**: núcleo do produto, painéis e API REST.
2. **Aplicativo Flutter**: aplicativo para pais e responsáveis, consumindo a API REST do PHP.
3. **Microsserviço Python `apostila-ai`**: processamento de PDFs, extração de exercícios e RAG com OpenAI.
4. **Servidor Node.js/WebSocket**: presença on-line de alunos e professores por escola.

A plataforma web é um monólito modular em PHP 8. Não usa Laravel, Symfony ou outro framework web completo, mas possui um **framework interno** com front controller, roteador, middlewares, controllers, models, services e views. Composer é usado para autoload PSR-4 e bibliotecas externas.

O isolamento multi-tenant acontece principalmente por **um banco MySQL separado para cada escola**. Um banco master armazena escolas, domínios e credenciais de conexão. A requisição é associada a um tenant antes de o controller ser executado.

### Stack principal

| Camada | Tecnologias |
|---|---|
| Web/backend | PHP 8.2, Composer, PDO/MySQL, sessões PHP |
| Views web | PHP server-side, JavaScript e CSS; Tailwind via uso nas telas/CDN |
| App | Flutter/Dart, Riverpod, GoRouter, Dio, Firebase Messaging |
| IA de apostilas | Python 3.11, FastAPI, SQLAlchemy, PyMuPDF, OpenAI SDK |
| Tempo real | Node.js, pacote `ws` |
| Dados | MySQL 8; Redis previsto para cache/sessão em partes da plataforma |
| Arquivos | Storage local, AWS S3 opcional e integrações específicas |
| Testes | PHPUnit configurado, Flutter Test e Playwright configurado |
| Ambiente local | Docker Compose para PHP + MySQL |

## 2. Mapa do repositório

```text
educatudo/
├── src/                         aplicação web PHP (núcleo do produto)
│   ├── index.php                front controller HTTP
│   ├── app/                     código da aplicação
│   ├── config/                  configuração e rotas
│   ├── database/migrations/     evolução dos bancos master e tenant
│   ├── public/                  PWA e assets públicos
│   ├── cron/                    tarefas recorrentes
│   ├── scripts/                 deploy, migrations e manutenção
│   ├── storage/                 logs, uploads, cache e arquivos privados
│   ├── docs/                    documentação funcional/técnica
│   ├── composer.json            dependências PHP e autoload
│   └── package.json             dependência de assets, especialmente MathLive
├── app/                         aplicativo Flutter para responsáveis
├── services/apostila-ai/        microsserviço Python/FastAPI
├── ws-server/                   presença on-line via WebSocket
├── docker/                      imagem PHP e inicialização do MySQL
├── docker-compose.yml           ambiente local PHP + MySQL
├── faq/                         guias operacionais de funcionalidades
├── PLATAFORMA.md                visão funcional do produto
├── CLAUDE.md                    convenções e contexto para desenvolvimento
├── package.json                 comandos Playwright do repositório externo
└── playwright.config.ts         configuração de testes E2E
```

### Pastas que não representam código-fonte ativo

- `node_modules/`, `src/vendor/`, `src/node_modules/`, `ws-server/node_modules/`: dependências instaladas; não editar manualmente.
- `app/build/`, `app/.dart_tool/`, `app/android/.gradle/`: artefatos gerados pelo Flutter/Android.
- `playwright-report/`: relatório gerado por testes.
- `tmp/`: arquivos temporários de análise/processamento.
- `src/storage/`, `src/uploads/` e `src/public/uploads/`: conteúdo mutável em runtime; devem ser tratados como dados, não como código.
- `src/Spotify_Clone_HTML_CSS-main/`: protótipo isolado; não faz parte da arquitetura central identificada.
- `src/educatudo_oficial/`: árvore legada/auxiliar; confirmar antes de reutilizar ou remover.

## 3. Aplicação web PHP

### 3.1 Fluxo de uma requisição

```text
Navegador/app
    │
    ▼
src/index.php
    ├── carrega Composer e configura sessão/erros
    ├── define URL, pasta-base e timezone
    ├── serve alguns arquivos especiais do PWA
    └── executa bootstrap_multi_tenant.php
             │
             ├── rota/domínio master → conexão com banco master
             └── escola → TenantResolver → DatabaseManager → banco do tenant
    │
    ▼
App → Router → Middleware → Controller
                              │
                              ├── Service: regra de negócio/integração
                              ├── Model: SQL e persistência
                              └── View PHP ou resposta JSON
```

Arquivos centrais:

- `src/index.php`: inicialização HTTP, sessão, tratamento de erros e criação da aplicação.
- `src/app/Core/App.php`: carrega configuração e rotas e dispara o roteador.
- `src/app/Core/Router.php`: registro, matching e execução de rotas/middlewares.
- `src/app/Core/bootstrap_multi_tenant.php`: escolhe banco master ou banco da escola.
- `src/app/Core/Database.php`: acesso PDO e instância corrente.
- `src/config/routes.php`: agrega as rotas por perfil.

### 3.2 Organização de `src/app`

#### `Core/`

Infraestrutura transversal. Alterações aqui podem afetar todos os módulos.

- `App.php`, `Router.php`, `BaseController.php`: ciclo HTTP e base dos controllers.
- `Database.php`, `DatabaseManager.php`, `MasterTenantConnection.php`: conexões e seleção de banco.
- `TenantResolver.php`, `bootstrap_multi_tenant.php`: identificação e inicialização do tenant.
- `Auth.php`, `AuthManager.php`: sessão e identidade do usuário.
- `FeatureGate.php`: módulos/recursos habilitados por escola.
- `MigrationRunner.php`, `TenantRelease.php`: evolução e versão dos tenants.
- `RedisCache.php`: abstração de cache Redis.
- `Logger.php`: logs estruturados.
- `AdminPermissionMatrix.php`, `AdminSecretariaAccess.php`: autorização administrativa.
- Helpers de domínio como créditos, jogos, ENEM, jornadas e layout permanecem aqui por legado; código novo deve avaliar se pertence a `Services/`.

#### `Controllers/`

Camada de entrada. Recebe parâmetros, valida acesso, coordena services/models e devolve view ou JSON. O repositório possui cerca de **136 controllers**.

Os diretórios refletem domínios e públicos: `Admin`, `Master`, `Api`, `User`, `Education`, `Teacher`, `Exams`, `Essays`, `Apostilas`, `Notifications`, `Integrations`, `Files`, `Forum`, `Games`, entre outros.

Regra recomendada: controller fino. Regras reutilizáveis, chamadas externas, transações longas e cálculos devem ficar em `Services/`; SQL deve ficar em `Models/` ou em uma abstração de persistência claramente definida.

#### `Models/`

Mapeia entidades e concentra consultas SQL. Existem cerca de **56 models**, com agrupamentos mais maduros em `Essays`, `Exams`, `Education`, `Forum`, `Simulados`, `Study`, `User` e `Accommodations`.

Há models antigos soltos e nomes em português, como `Noticia.php`, `NotificacaoApi.php`, `RedacaoLivreEnvio.php` e `RedacaoLivreCorrecao.php`. Não usar isso como padrão para novos módulos; preferir diretório de domínio e nomenclatura consistente.

#### `Services/`

Contém aproximadamente **60 serviços** de negócio e integração. Exemplos:

- IA: `OpenAIService`, `TudinhaService`, `EssayAIService`, `EssayOCRService`, `AIJobService`, `RAGService`, `EmbeddingService`.
- Apostilas: `ApostilaIaClientService`, cliente HTTP do serviço Python.
- Pagamentos/créditos: `CreditosService`, serviços de checkout/conciliação e `Asaas/AsaasApiClient`.
- Arquivos/mídia: `DriveStorageService`, `MediaStorageService`, `DocumentProcessorService`.
- Comunicação: `EmailService`, `FirebaseMessagingService`, `OneSignalService`, `EvolutionApiService`.
- Aulas/vídeo: `JaasMeetService`, `PandaVideoLiveService`.
- Domínio escolar: chamada, saúde acadêmica, responsáveis, status e movimentação de alunos.

#### `Middleware/`

- `AuthMiddleware.php`: exige sessão e perfil web.
- `ApiAuthMiddleware.php`: autenticação de APIs existentes.
- `MobileApiAuthMiddleware.php`: autenticação específica da API mobile.
- `AuditMiddleware.php`: auditoria transversal.
- `GameSecurityMiddleware.php`: restrições de jogos.
- `PasswordCheckMiddleware.php`: regras ligadas à senha/primeiro acesso.

#### `Views/`

Renderização PHP server-side, com cerca de **527 arquivos**. A primeira divisão é pelo público:

- `admin/`: gestão da escola.
- `master/`: administração SaaS e tenants.
- `student/`: experiência do aluno.
- `teacher/` e `professor/`: experiência docente; a duplicidade é histórica.
- `parents/`: portal web de responsáveis.
- `monitor/`: supervisão.
- `layouts/`, `components/`, `partials/`: estrutura reutilizável.

Evitar SQL e regra de negócio em views. JavaScript específico de uma tela pode existir próximo da view por legado, mas comportamentos reutilizáveis devem ir para assets versionados.

#### `Helpers/`, `Utils/` e `Policies/`

- `Helpers/`: transformações e funções sem estado.
- `Utils/`: utilitários técnicos.
- `Policies/`: autorização por recurso.

Não criar um helper genérico quando o código representa uma regra de negócio; nesse caso, usar um service.

### 3.3 Rotas e perfis

`src/config/routes.php` carrega primeiro rotas master, públicas e API; depois, dentro do middleware `Auth`, carrega as rotas web autenticadas.

| Arquivo | Responsabilidade | Rotas registradas no levantamento |
|---|---|---:|
| `routes/master.php` | painel SaaS/master | 128 |
| `routes/public.php` | login, páginas e endpoints públicos | 79 |
| `routes/api_v1.php` | API mobile versionada | 39 |
| `routes/student.php` | aluno | 281 |
| `routes/teacher.php` | professor | 269 |
| `routes/admin.php` | administração da escola | 487 |
| `routes/parents.php` | portal web de responsáveis | 43 |
| `routes/monitor.php` | monitor | 9 |

Os números são chamadas de registro encontradas no código, não garantem endpoints únicos após composição de grupos/prefixos.

Ao criar uma rota:

1. Escolher o arquivo do público correto.
2. Usar middleware de autenticação/autorização apropriado.
3. Encaminhar para um controller; evitar closures extensas no arquivo de rotas.
4. Para API mobile, manter contrato JSON e versionamento em `/api/v1`.
5. Para mutações web, validar CSRF pelo padrão do `BaseController`.

### 3.4 API HTTP

O EducaTudo possui mais de uma superfície de API. Elas não devem ser tratadas como um único contrato:

| Superfície | Prefixo | Consumidor principal | Autenticação |
|---|---|---|---|
| API mobile atual | `/api/v1` | app Flutter de responsáveis e dispositivo facial | JWT Bearer ou credencial própria do dispositivo |
| API web/legada | `/api` e endpoints públicos auxiliares | páginas PHP, integrações e consumidores antigos | sessão, JWT/API middleware ou regra específica da rota |
| API interna de apostilas | serviço Python na porta própria | backend PHP | `X-Internal-Api-Key` |

#### API mobile v1

As rotas ficam em `src/config/routes/api_v1.php` e os controllers em `src/app/Controllers/Api/V1/`. O prefixo versionado permite evoluir o app sem alterar os contratos legados.

URL final:

```text
https://<dominio-da-escola>/api/v1/<recurso>
```

O domínio é relevante porque a resolução do tenant ocorre antes da API. Um token emitido por uma escola não é aceito em outra.

##### Autenticação de responsáveis

| Método | Endpoint | Função | Proteção |
|---|---|---|---|
| `POST` | `/api/v1/auth/login` | login com CPF e senha | pública, com rate limit |
| `POST` | `/api/v1/auth/refresh` | rotaciona refresh token e emite novo access token | refresh token |
| `POST` | `/api/v1/auth/logout` | revoga a sessão mobile | Bearer JWT |
| `GET` | `/api/v1/me` | responsável autenticado | Bearer JWT |
| `GET` | `/api/v1/students` | filhos vinculados ao responsável | Bearer JWT |

Exemplo de login:

```http
POST /api/v1/auth/login
Content-Type: application/json
Accept: application/json

{"cpf":"00000000000","password":"senha"}
```

Resposta:

```json
{
  "data": {
    "access_token": "<jwt>",
    "token_type": "Bearer",
    "expires_in": 86400,
    "refresh_token": "<session-id>.<token-aleatorio>",
    "parent": {
      "id": 1,
      "name": "Nome",
      "email": "responsavel@exemplo.com",
      "cpf": "00000000000",
      "phone": null,
      "must_change_password": false
    }
  }
}
```

Nas chamadas protegidas:

```http
Authorization: Bearer <access_token>
Accept: application/json
```

O `MobileApiAuthMiddleware` valida assinatura/expiração, `role=parent`, `api_version=v1`, tenant, UUID da sessão, revogação, validade da sessão e situação ativa do responsável. `JWT_SECRET` precisa ter pelo menos 32 caracteres; sem isso a API responde `503`.

O refresh token é armazenado no banco somente como hash e é rotacionado a cada refresh. O logout marca `revoked_at`. O app guarda o token de acesso em `flutter_secure_storage`.

##### Dados acadêmicos do responsável

Todas as rotas abaixo exigem Bearer JWT e devem validar se o aluno pertence ao responsável autenticado.

| Método | Endpoint | Conteúdo |
|---|---|---|
| `GET` | `/students/{id}/dashboard` | resumo acadêmico do aluno |
| `GET` | `/students/{id}/exams` | provas/resultados disponíveis |
| `GET` | `/students/{id}/journeys` | jornadas de aprendizagem |
| `GET` | `/students/{id}/lesson-plans` | planos de aula |
| `GET` | `/students/{id}/lesson-plans/{planId}` | detalhe do plano de aula |
| `GET` | `/students/{id}/writing-journeys` | jornadas de redação |
| `GET` | `/students/{id}/essays` | redações do aluno |
| `GET` | `/students/{id}/notices` | avisos/mural visíveis ao responsável |

O prefixo completo de cada linha é `/api/v1`.

##### Comunicação e calendário escolar

| Método | Endpoint | Conteúdo |
|---|---|---|
| `GET` | `/students/{id}/school-communications` | lista comunicações da escola |
| `GET` | `/students/{id}/school-communications/{communicationId}` | detalhe e anexos |
| `POST` | `/students/{id}/school-communications/{communicationId}/read` | marca leitura |
| `POST` | `/students/{id}/school-communications/{communicationId}/replies` | envia resposta |
| `GET` | `/students/{id}/calendar-events` | eventos do calendário |
| `POST` | `/students/{id}/calendar-events/{eventId}/read` | marca evento como lido |

##### Notificações e dispositivos

| Método | Endpoint | Conteúdo |
|---|---|---|
| `GET` | `/notifications` | feed do responsável |
| `POST` | `/notifications/{id}/read` | marca notificação como lida |
| `PUT` | `/devices/{deviceId}` | cadastra/atualiza dispositivo e token push |
| `DELETE` | `/devices/{deviceId}` | remove dispositivo/token push |

O cadastro de dispositivo liga Firebase Cloud Messaging à sessão/usuário. Credenciais Firebase Admin permanecem apenas no backend.

##### API de dispositivo facial

Também vive em `/api/v1`, mas possui fluxo de credencial próprio e não usa o papel `parent`:

- pareamento: `POST /facial-devices/pair`;
- identidade/logout do dispositivo: `GET /facial-device/me`, `POST /facial-device/logout`;
- turmas e alunos: `GET /facial-device/classes`, `GET /facial-device/students` e detalhe;
- amostras/perfil facial: endpoints de cadastro, sincronização e remoção;
- operação: `POST /facial-device/recognize`, `POST /facial-device/attendance` e `GET /facial-device/events`.

Existem aliases em português/inglês para cadastro de amostras, preservando compatibilidade com clientes diferentes.

##### Contrato de resposta e erros

Sucesso normalmente usa envelope `data`:

```json
{
  "data": {},
  "meta": {}
}
```

Erro usa:

```json
{
  "error": {
    "code": "validation_error",
    "message": "Mensagem segura para o usuário.",
    "details": {}
  }
}
```

Status relevantes: `200/201` para sucesso, `204` sem corpo, `401` token/credencial inválida, `403` vínculo ou permissão negada, `404` recurso inexistente, `409` conflito de estado, `422` validação, `429` limite de tentativas e `503` API não configurada.

O middleware aceita `X-Request-ID` válido ou gera um identificador e o devolve no header `X-Request-ID`. O mesmo ID deve aparecer nos logs para rastrear uma chamada ponta a ponta.

##### CORS e configuração do Flutter

`App.php` trata CORS da API mobile conforme a configuração do ambiente. A allowlist deve ser definida por `MOBILE_API_CORS_ORIGINS`; não liberar `*` junto com credenciais.

No Flutter, `AppConfig.apiBaseUrl` acrescenta `/api/v1` automaticamente e o `AuthInterceptor` injeta o Bearer token. Repositories em `features/*/data` são os consumidores do contrato; mudanças incompatíveis exigem nova versão ou migração coordenada do app.

#### API legada

Rotas antigas em `/api` continuam existindo para compatibilidade, inclusive autenticação e recursos de pais, além de endpoints usados pelas páginas web e integrações. Elas estão distribuídas principalmente em `routes/public.php`, `routes/parents.php` e controllers `Api/`.

Ao desenvolver recurso mobile novo, preferir `/api/v1`. Não alterar formato/status de uma rota legada sem identificar seus consumidores. A documentação `app/docs/API_MOBILE_V1.md` contém itens originalmente propostos que não correspondem integralmente às rotas já implementadas; `src/config/routes/api_v1.php` é a referência atual de roteamento.

#### API interna Python

O serviço `apostila-ai` possui OpenAPI automático do FastAPI e endpoints como `/health`, `/apostilas/...` e `/chat/apostila`. Exceto `/health`, as chamadas exigem:

```http
X-Internal-Api-Key: <segredo-compartilhado>
```

Essa chave nunca deve chegar ao navegador ou ao app Flutter. O PHP, por meio de `ApostilaIaClientService`, atua como cliente/proxy autorizado. Mais detalhes estão na seção 5.

### 3.5 Multi-tenancy e bancos

Quando `MULTI_TENANT=true`:

1. O bootstrap conecta ao banco master com `DB_*`.
2. Requisições ao caminho/domínio master continuam nessa conexão.
3. `TenantResolver` identifica a escola por domínio ou header `X-Tenant`.
4. `DatabaseManager` lê no master as credenciais da escola.
5. `Database::setCurrentInstance()` troca a instância PDO para o banco do tenant.
6. São definidas `TENANT_ID`, `TENANT_SLUG` e `TENANT_DOMAIN`.

O isolamento do domínio principal acontece pela conexão separada. Portanto, uma query normal de tenant não precisa de `WHERE escola_id`. Já tabelas explicitamente compartilhadas ou integrações que conectam diretamente a um banco devem documentar o seu modelo de isolamento.

#### Migrations

`src/database/migrations/` possui aproximadamente **110 migrations SQL** no snapshot.

- Arquivo contendo `master` no nome: executado somente no banco master.
- Demais arquivos: executados em cada banco de escola.
- O runner registra execução para evitar repetição.
- A ordem é alfabética; novos nomes devem usar timestamp/data consistente.
- Há migrations de rollback para alterações recentes, mas rollback não é padronizado para todo o histórico.

Comandos:

```bash
cd src
php scripts/run_migrations.php --dry-run
php scripts/run_migrations.php
php scripts/run_migrations.php --escola-id=1
```

Antes de adicionar uma coluna usada por PHP e Python, atualizar a migration SQL e o espelho SQLAlchemy do serviço.

### 3.6 Configuração e segredos

`src/config/app.php` lê o `.env`; `src/.env.example` documenta categorias como banco, sessão, IA, e-mail, storage, pagamentos/vídeo, JWT/mobile, Firebase e reconhecimento facial.

Nunca versionar `.env`, chaves privadas, service accounts, credenciais de banco ou tokens. Variáveis críticas incluem:

- `MULTI_TENANT`, `MASTER_DOMAIN` e credenciais `DB_*`.
- chaves de criptografia/segredos master e JWT.
- `OPENAI_API_KEY` e chaves de outros provedores.
- `APOSTILA_AI_URL` e `APOSTILA_AI_INTERNAL_KEY`.
- configuração Firebase e credenciais de push.

Existe uma divergência a vigiar: `src/index.php` define `ENVIRONMENT` como `development` diretamente, enquanto o `.env.example` expõe `APP_ENV`/`APP_DEBUG`. O ambiente efetivo deve ser unificado para não exibir detalhes internos em produção.

### 3.7 Jobs, cron e processamento assíncrono

`src/cron/` contém tarefas como:

- atualização de status de jornadas;
- resumo do dashboard de jornadas;
- contabilização diária de uso/custos de LLM;
- atualização RSS;
- backup e restauração;
- envio de métricas;
- processamento de `ai_jobs`.

`src/app/Services/AIJobService.php` e `cron/process_ai_jobs.php` formam a fila assíncrona de IA no lado PHP. O agendador do servidor é parte da operação: sem crontab/worker configurado, jobs podem permanecer pendentes.

## 4. Aplicativo Flutter (`app/`)

O app se identifica como `educatudo_pais` e atende pais/responsáveis. Ele não é a mesma coisa que o PWA em `src/public/`.

### Organização

```text
app/lib/
├── main.dart                    entrada do Flutter
├── bootstrap.dart               inicialização de Firebase/push
├── app/
│   ├── educatudo_app.dart       MaterialApp
│   ├── router.dart              rotas e redirects de sessão
│   └── theme/                   tema visual
├── core/
│   ├── config/                  ambiente e URL da API
│   ├── network/                 Dio, interceptador e erros
│   ├── storage/                 token em armazenamento seguro
│   ├── push/                    Firebase/local notifications
│   └── widgets/                 componentes transversais
└── features/
    └── <feature>/
        ├── data/                repository e chamadas HTTP
        ├── domain/              modelos Dart
        └── presentation/        páginas e controllers Riverpod
```

Features presentes: autenticação, seleção de filhos, início, comunicados, notificações, calendário escolar, provas, jornadas, planos de aula e jornadas de redação.

### Fluxo do app

1. `main.dart` chama `bootstrap()` e monta `ProviderScope`.
2. `SessionController` procura o token no `flutter_secure_storage`.
3. Se houver token, chama `/me`; se inválido, limpa sessão e aluno selecionado.
4. `GoRouter` redireciona entre splash, login, seleção do aluno e features.
5. Repositories usam Dio; `AuthInterceptor` inclui `Authorization: Bearer <token>`.
6. Push usa Firebase Messaging e notificações locais.

Configuração de build via `--dart-define`:

```bash
flutter run \
  --dart-define=APP_ENV=dev \
  --dart-define=API_BASE_URL=http://localhost:8000 \
  --dart-define=ENABLE_PUSH=false
```

Se `API_BASE_URL` não for definido, o código aponta atualmente para `https://colag.educatudo.com` e acrescenta `/api/v1`.

Padrão para feature nova: `data/domain/presentation`, repository isolando HTTP, modelos tipados e estado em Riverpod. A UI não deve chamar Dio diretamente.

## 5. Microsserviço Python (`services/apostila-ai`)

É um serviço FastAPI independente para apostilas. O PHP permanece responsável por autorização do usuário, upload e interface; o Python executa processamento especializado.

### Estrutura

```text
services/apostila-ai/
├── app/main.py                  FastAPI e routers
├── app/config.py                settings por ambiente
├── app/auth.py                  valida X-Internal-Api-Key
├── app/database.py              engine e sessões SQLAlchemy
├── app/routers/                 HTTP: health, apostilas e chat
├── app/models/db_models.py      espelho ORM das tabelas compartilhadas
├── app/models/schemas.py        contratos Pydantic
├── app/services/
│   ├── pdf_service.py           valida e extrai PDF/imagens
│   ├── chunk_service.py         divide conteúdo em chunks
│   ├── exercise_service.py      detecta exercícios
│   ├── openai_service.py        OpenAI e vector stores
│   └── rag_service.py           perguntas com recuperação de contexto
├── app/storage/                 uploads e páginas processadas
├── requirements.txt
└── Dockerfile
```

### Fluxo de processamento

```text
PHP salva PDF
  → POST /apostilas/{id}/processar + X-Internal-Api-Key
  → FastAPI responde "processando"
  → BackgroundTasks valida o PDF
  → PyMuPDF extrai texto/imagens por página
  → serviço gera chunks
  → OpenAI identifica exercícios
  → páginas são enviadas ao Vector Store
  → SQLAlchemy grava status, páginas, chunks, exercícios e vector_store_id
  → PHP consulta o status e apresenta o resultado
```

O serviço também oferece chat/RAG, listagem de exercícios, imagens/capa e geração de prova/conteúdo, conforme routers e schemas.

As tabelas `apostilas_ia` e `apostila_ia_*` são compartilhadas com o PHP. A migration PHP é a fonte de verdade operacional; `db_models.py` deve espelhá-la.

O processamento usa `BackgroundTasks` do processo FastAPI. Isso libera a resposta HTTP, mas **não é uma fila durável**: reinício/crash do container pode interromper a tarefa. Para escala e garantia de entrega, migrar para worker/fila persistente.

OCR de página totalmente escaneada ainda é uma limitação conhecida: PyMuPDF extrai texto nativo; páginas sem camada de texto podem exigir OCR adicional.

## 6. WebSocket (`ws-server/`)

Servidor Node simples para presença por escola:

- cliente conecta e envia `login` com escola, usuário, nome e tipo;
- estado fica em memória agrupado por escola;
- o servidor transmite um resumo de alunos/professores on-line;
- fechar conexão remove o usuário do resumo.

Limitações arquiteturais atuais:

- estado é perdido ao reiniciar;
- múltiplas réplicas não compartilham presença;
- não há autenticação/assinatura visível no protocolo;
- o broadcast envia o resumo global a todos os clientes conectados.

Antes de usar isso como fonte sensível ou escalar horizontalmente, autenticar clientes, restringir visibilidade por papel/tenant e mover presença para Redis/pub-sub.

## 7. Front-end web, PWA e assets

- `src/public/manifest.json` e `service-worker.js`: instalação/cache do PWA.
- `src/public/static/js/`: componentes reutilizáveis como MathLive, editor de prova, presença e monitoramento.
- `src/public/static/css/` e `src/assets/css/`: estilos especializados.
- `src/public/vendor/face-api/`: biblioteca/modelos de reconhecimento facial servidos localmente.
- `src/public/static/materias_simulados.json`: catálogo estático consumido pelo front-end.

O front web é predominantemente renderizado no servidor. JavaScript complementa páginas específicas; não há SPA JavaScript central identificada.

## 8. Integrações externas identificadas

- OpenAI: chat, correções, geração, extração e RAG.
- Google: API client, Vision e possíveis recursos Drive/Books.
- AWS S3: storage opcional.
- Asaas: pagamentos/créditos.
- Firebase e OneSignal: push notifications.
- ElevenLabs: voz.
- Evolution API: mensageria.
- Jitsi/JaaS e Panda Video: aulas ao vivo/gravações.
- Serviço de reconhecimento facial configurável.
- Gamma e integrações educacionais descritas nos módulos.

Toda integração deve ficar atrás de um service, ter timeouts, tratamento de falha, logs sem segredos e configuração por ambiente.

## 9. Como programar um módulo novo

Estrutura sugerida:

```text
src/app/Controllers/MeuDominio/
    AdminMeuRecursoController.php
    StudentMeuRecursoController.php
src/app/Models/MeuDominio/
    MeuRecurso.php
src/app/Services/
    MeuRecursoService.php
src/app/Views/admin/meu-recurso/
src/app/Views/student/meu-recurso/
src/config/routes/admin.php
src/config/routes/student.php
src/database/migrations/YYYY_MM_DD_meu_recurso.sql
```

Sequência:

1. Definir público, regra de acesso e se o dado vive no master ou tenant.
2. Criar migration idempotente e compatível com o runner.
3. Criar model com prepared statements.
4. Colocar regra/transação no service.
5. Criar controller fino e validar entrada/CSRF/autorização.
6. Registrar rotas no perfil correto.
7. Criar views/componentes ou contrato JSON.
8. Se opcional, integrar ao `FeatureGate` e menus.
9. Adicionar logs e métricas úteis, sem dados sensíveis.
10. Cobrir fluxo feliz, autorização, validação e falha externa com testes.

### Convenções práticas

- Classes e arquivos novos preferencialmente em inglês; manter termos brasileiros quando forem conceitos de domínio claros.
- PSR-4: namespace `App\` aponta para `src/app/`.
- SQL sempre parametrizado.
- IDs e permissões vindos do cliente nunca são suficientes: revalidar no backend.
- Controller não deve conhecer detalhes de SDK externo.
- Não acessar diretamente o banco master a partir de um fluxo tenant sem uma abstração explícita.
- Upload: validar tamanho, MIME/magic bytes, nome/caminho e autorização de leitura.
- Não colocar segredo em JavaScript, Dart compilado ou resposta HTTP.

## 10. Desenvolvimento local e operação

### Web PHP

```bash
cp src/.env.example src/.env
docker compose up -d --build
cd src
composer install
php scripts/run_migrations.php --dry-run
```

O Compose raiz sobe apenas PHP/Apache e MySQL. Redis, WebSocket e `apostila-ai` não fazem parte desse arquivo e precisam ser iniciados/configurados separadamente quando o recurso depender deles.

### Python

```bash
cd services/apostila-ai
python3.11 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
uvicorn app.main:app --host 0.0.0.0 --port 8088 --reload
```

### WebSocket

```bash
cd ws-server
npm install
npm start
```

### Flutter

```bash
cd app
flutter pub get
flutter run --dart-define=API_BASE_URL=http://localhost:8000 --dart-define=ENABLE_PUSH=false
```

### Deploy

`src/scripts/deploy.sh` atualmente executa migrations e encerra. Ele não instala dependências, não compila assets, não reinicia workers e não executa smoke tests. A rotina real de produção deve documentar essas etapas externas.

## 11. Testes e qualidade

Estado observado:

- Composer configura PHPUnit, mas não foi encontrada uma suíte PHP em `src/tests/` neste snapshot.
- Flutter possui um teste em `app/test/widget_test.dart`.
- Playwright está configurado na raiz, mas a pasta `tests/` está ignorada pelo Git externo e não apareceu no levantamento atual.
- Não foi identificada CI versionada no mapa principal.

Prioridade de cobertura:

1. resolução de tenant e impossibilidade de cruzamento entre escolas;
2. autenticação/autorização por perfil;
3. provas, notas, redações e movimentação de alunos;
4. créditos/pagamentos e idempotência de webhooks;
5. migrations master versus tenant;
6. contratos `/api/v1` usados pelo Flutter;
7. falhas/retries/timeout das integrações de IA;
8. upload e autorização de arquivos.

Comandos previstos:

```bash
cd src && composer test
cd app && flutter test
npm test
```

## 12. Pontos de atenção técnicos

### Alta prioridade

1. **Ambiente/debug**: remover definição hardcoded de `development` no front controller e usar uma única fonte (`APP_ENV`/`APP_DEBUG`). Detalhes de exceção não podem chegar ao usuário em produção.
2. **WebSocket**: autenticar, isolar dados por tenant/perfil e planejar estado compartilhado.
3. **Testes automatizados**: a superfície é grande (mais de mil registros de rota) e a cobertura encontrada é pequena.
4. **Fila Python**: `BackgroundTasks` não garante retomada; usar fila durável para PDFs longos.
5. **Segredos e storage**: auditar arquivos mutáveis e chaves sob `storage/`; garantir que não sejam publicados pelo servidor web ou versionados.

### Média prioridade

1. **Bootstrap extenso**: `src/index.php` acumula sessão, assets especiais, métricas, handlers e inicialização. Extrair responsabilidades para classes testáveis.
2. **Autoload híbrido**: Composer/PSR-4 convive com autoloader manual e classes globais. Padronizar gradualmente namespaces.
3. **Rotas muito grandes**: especialmente admin, student e teacher. Dividir por domínio mantendo um agregador por perfil.
4. **Nomenclatura duplicada**: `teacher`/`professor` e classes PT/EN aumentam o custo de descoberta.
5. **Compose incompleto**: criar um ambiente integrado opcional com Redis, WebSocket e Python, healthchecks e redes explícitas.
6. **Documentação de cron**: centralizar agenda completa, concorrência, locks, retries e alertas.

### Higiene do repositório

O checkout contém dependências, builds, uploads, logs e até um Git interno em `src/`. A raiz trata `src` como repositório aninhado/gitlink e ignora várias pastas de desenvolvimento. Isso torna versionamento e onboarding menos previsíveis. Convém decidir formalmente entre monorepo, submódulo ou repositórios separados e documentar o fluxo.

## 13. Checklist antes de publicar código

- A alteração respeita master versus tenant?
- A rota possui autenticação, autorização e CSRF quando aplicável?
- A entrada foi validada e o SQL é parametrizado?
- Logs evitam senha, token, documento e conteúdo privado?
- Migration é idempotente e foi testada em banco compatível?
- Contratos usados pelo Flutter continuam compatíveis?
- Integração externa tem timeout e erro controlado?
- Upload não permite path traversal ou acesso entre usuários?
- Cron/job pode rodar duas vezes sem duplicar efeitos?
- Testes relevantes e smoke test foram executados?

## 14. Fontes internas para aprofundamento

- `CLAUDE.md`: regras gerais e contexto de desenvolvimento.
- `PLATAFORMA.md`: mapa funcional do produto.
- `src/docs/MAPA_GERAL_DOS_MODULOS.md`: módulos existentes.
- `src/database/migrations/README.md`: runner e regras de migrations.
- `src/cron/README.md`: agendamentos documentados.
- `services/apostila-ai/README.md`: contrato e operação do serviço Python.
- `ws-server/README.md`: protocolo de presença.
- `app/docs/`: documentação específica do aplicativo.

Este documento deve ser atualizado sempre que um novo runtime, banco, perfil, serviço assíncrono ou convenção estrutural for introduzido.
