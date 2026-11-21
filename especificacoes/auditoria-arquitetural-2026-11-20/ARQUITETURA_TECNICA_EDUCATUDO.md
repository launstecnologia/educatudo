# Arquitetura Técnica — EducaTudo

**Tipo:** ficha técnica executiva  
**Data da análise:** 2026-11-20  
**Escopo:** repositório `plataforma_educatudo` (somente leitura)  
**Audiência:** CTO / equipe de engenharia externa  

> **Legenda de confiança:**  
> **[FATO]** — comprovado no código ou configuração versionada.  
> **[INFERÊNCIA]** — conclusão razoável a partir de evidências.  
> **[NÃO VERIFICADO]** — não foi possível confirmar nesta passagem (runtime, produção, cobertura total).

Documentos complementares desta auditoria:

- `MAPA_MODULOS_EDUCATUDO.md`
- `DIAGNOSTICO_ARQUITETURAL.md`
- `PLANO_EVOLUCAO_ARQUITETURAL.md`

---

## 1. Resumo executivo

O EducaTudo é um **SaaS educacional multi-tenant** entregue como **monólito modular em PHP puro** (sem framework MVC completo), com um **banco MySQL por escola** e um **banco master** para cadastro de escolas, credenciais e catálogos globais. No mesmo repositório existe um **app Flutter dos pais** (`familia/`), com arquitetura própria, que consome a API JWT da plataforma.

A organização de código segue camadas explícitas (`Core` → `Painel` / `Dominio` / `Modulos`) com roteamento por perfil, feature flags por escola e fila de IA baseada em MySQL + cron. A modularidade de pastas e flags está avançada (~49 módulos); a modularidade de *bounded contexts* no núcleo acadêmico (provas / boletins / fechamento) é limitada por acoplamento direto e por controllers legados ainda concentrados no Painel.

---

## 2. Identificação da tecnologia

### 2.1 Runtime e linguagem

| Item | Valor | Confiança | Evidência |
|---|---|---|---|
| PHP exigido (Composer) | `>=8.0` | **[FATO]** | `backend/composer.json` → `require.php` |
| PHP da imagem Docker | `8.4-fpm` | **[FATO]** | `docker/php/Dockerfile` (`FROM php:8.4-fpm`) |
| PHP documentado internamente | 8.2 | **[FATO]** (doc) | `CLAUDE.md`, `.cursor/contexto/LEIA.md` |
| Platform pin Composer | `8.4.24` | **[FATO]** | `composer.json` → `config.platform.php` |

**Drift documentado:** a documentação operacional cita PHP 8.2; o runtime Docker e o pin do Composer apontam para 8.4. **[FATO]** de inconsistência entre fontes; versão efetiva em produção **[NÃO VERIFICADO]**.

### 2.2 Framework

| Item | Valor | Confiança |
|---|---|---|
| Framework PHP (Laravel, Symfony, etc.) | **Não utilizado** | **[FATO]** — ausência de dependências de framework no `composer.json`; router/DI/MVC próprios em `app/Core/` |
| Abordagem | PHP puro + kernel próprio (`App`, `Router`, `Database`, middlewares) | **[FATO]** |

### 2.3 Gerenciador de dependências e autoload

| Item | Valor | Evidência |
|---|---|---|
| PHP | Composer | `backend/composer.json` |
| Assets CSS | npm (Tailwind) | `backend/package.json` |
| E2E | npm (Playwright) | `package.json` (raiz) |
| Autoload principal | PSR-4 `App\` → `app/` | `composer.json` → `autoload.psr-4` |
| Autoload de módulos | Mapeamentos PSR-4 explícitos `App\Modulos\<Nome>\` → pastas kebab-case | idem |
| Compat legado | `autoload.files` com `app/Compat/*_autoload.php` (aliases) | idem |
| Autoload secundário | `spl_autoload_register` em bootstrap | `backend/bootstrap/app.php` |

### 2.4 Dependências principais (Composer)

**[FATO]** em `backend/composer.json`:

| Pacote | Uso aparente |
|---|---|
| `aws/aws-sdk-php` | Storage S3 / mídia |
| `dompdf/dompdf` | Geração de PDF |
| `ezyang/htmlpurifier` | Sanitização HTML |
| `phpmailer/phpmailer` | E-mail |
| `smalot/pdfparser` | Leitura de PDF |
| `chillerlan/php-qrcode` | QR Code |
| `google/apiclient` | APIs Google |
| `google/cloud-vision` | Vision (ex.: OCR / visão) |
| `phpunit/phpunit` (^9) | Testes (dev) |

Não há SDK oficial OpenAI no Composer; a integração é HTTP própria (`OpenAIService`). **[FATO]**

> Nota: a `description` do `composer.json` ainda diz *“Plataforma Educacional Single-Tenant com IA”*, enquanto o código implementa multi-tenant. **[FATO]** de texto desatualizado.

### 2.5 Bootstrap e entrada HTTP

```
Navegador
  → Nginx (docroot backend/public/)
  → public/index.php
  → bootstrap/app.php  (Composer, sessão, autoload legado)
  → app/Core/bootstrap_multi_tenant.php  (resolve escola / master)
  → App::run() → Router::dispatch()
```

| Arquivo | Papel |
|---|---|
| `backend/public/index.php` | Front controller |
| `backend/bootstrap/app.php` | Sessão, autoload, handlers |
| `backend/app/Core/bootstrap_multi_tenant.php` | Isolamento por tenant |
| `backend/app/Core/App.php` | Config + router |
| `backend/config/routes.php` | Agrega rotas por perfil e módulos |

### 2.6 Roteamento

- Router próprio: `backend/app/Core/Router.php`
- Rotas por perfil: `backend/config/routes/{master,public,student,teacher,admin,parents,monitor,api_v1}.php`
- Módulos: `glob` de `app/Modulos/*/routes.public.php` (antes do Auth) e `*/routes.php` (dentro do Auth)
- Feature gate **antes** do match de rota: `FeatureGate::getRedirectIfBlocked` no `Router::dispatch`

### 2.7 Sessões

| Aspecto | Evidência |
|---|---|
| Preferência Redis | `session.save_handler=redis` se extensão Redis + ping OK — `bootstrap/app.php` |
| Fallback | Handler padrão (arquivo) se Redis indisponível |
| Cookie | Nome `EDUCATUDO_SESSION`, HttpOnly, SameSite=Lax, Secure se HTTPS |
| Sessão efetiva em Redis neste host | **[NÃO VERIFICADO]** (depende do runtime) |

### 2.8 Autenticação e autorização

| Camada | Mecanismo | Evidência |
|---|---|---|
| Web (sessão) | `AuthMiddleware` — perfil + login | `app/Middleware/AuthMiddleware.php` |
| API pais (legado) | JWT Bearer, role parent | `ApiAuthMiddleware` + `config/routes/public.php` |
| API mobile v1 | JWT | `MobileApiAuthMiddleware` + `config/routes/api_v1.php` |
| Feature por escola | `FeatureGate` + `LayoutHelper::isModuleEnabled` | `app/Core/FeatureGate.php` |
| ACL admin | `AdminPermissionMatrix` + JSON de permissões | `app/Core/AdminPermissionMatrix.php` |
| Ownership pontual | Policies (2 arquivos) | `app/Policies/ParentStudentAccessPolicy.php`, `PoliticaAcessoProfessorAluno.php` |
| CSRF | Token de sessão em controllers (`BaseController::validateCsrf`); **sem** middleware CSRF global no Router | **[FATO]** |

### 2.9 Frontend web

| Tecnologia | Evidência |
|---|---|
| Views PHP server-rendered | `app/Views/`, `app/Modulos/*/Views/`, `app/Painel/*/Views/` |
| Tailwind CSS 3.4 | `backend/package.json`, `backend/tailwind.config.js` |
| Build CSS | `npm run build:css` → `public/static/css/tailwind.css` |
| MathLive | dependência npm |
| Vite / Webpack | **Ausentes** no app web |

### 2.10 App mobile / segunda aplicação

| Item | Valor |
|---|---|
| Pasta | `familia/` |
| Stack documentada | Flutter, Riverpod, GoRouter, Dio |
| Contrato | API JWT da plataforma (`/api/*`, mobile v1) |
| Arquitetura compartilhada com PHP | **Não** — trilha separada (`.cursor/contexto/LEIA.md`) |

### 2.11 Integrações externas identificadas

| Integração | Onde | Config típica |
|---|---|---|
| OpenAI | `app/Infra/Services/OpenAIService.php` | env / chave no banco |
| AWS S3 | `MediaStorageService` + SDK | `AWS_*`, `MEDIA_STORAGE`, `DRIVE_STORAGE` |
| Asaas | `Infra/Services/Asaas/*`, crons, Master | config no banco master |
| JaaS / Jitsi | `Modulos/aulas-online`, `Webhooks/` | `JAAS_*` / `JITSI_*` |
| ZapSign | `Modulos/matricula` + webhook | settings do tenant |
| Firebase Cloud Messaging | `FirebaseMessagingService` | service account path |
| Google Vision / APIs | Composer + serviços | JSON / client |
| EducaSync solver | `services/educasync-solver/` | serviço Docker auxiliar |
| Apostila AI | `services/apostila-ai/` | serviço Docker auxiliar |
| WebSocket | `ws-server/` (Node) | serviço separado |

Valores de secrets **não** foram lidos nem reproduzidos nesta auditoria.

---

## 3. Estrutura de diretórios (visão executiva)

```
plataforma_educatudo/
├── backend/                 ← plataforma PHP (código próprio)
│   ├── public/              ← docroot HTTP
│   ├── bootstrap/           ← boot da aplicação
│   ├── config/              ← app + routes
│   ├── app/                 ← Core, Painel, Dominio, Modulos, API, …
│   ├── database/migrations/ ← schema versionado (~538 arquivos)
│   ├── cron/                ← jobs agendados / fila IA
│   ├── mcp/                 ← tools MCP para assistentes
│   ├── storage/             ← logs, cache, arquivos gerados
│   ├── vendor/              ← dependências Composer (terceiros)
│   └── node_modules/        ← deps npm de assets (terceiros)
├── familia/                 ← app Flutter dos pais (código próprio)
├── docker/                  ← Nginx, PHP-FPM, MySQL init
├── docker-compose*.yml      ← ambientes local / VPS
├── services/                ← microserviços auxiliares (solver, apostila-ai)
├── ws-server/               ← WebSocket Node
├── tests/ + qa/             ← Playwright E2E
├── especificacoes/          ← specs / auditorias / PRD
├── .claude/ + .cursor/      ← docs e regras para IA/dev
└── .github/workflows/       ← deploy (sem pipeline de testes CI)
```

### Classificação por tipo

| Tipo | Pastas |
|---|---|
| Código próprio | `backend/app/`, `backend/cron/`, `backend/config/`, `familia/lib/`, `services/*/`, `ws-server/` |
| Terceiros | `backend/vendor/`, `*/node_modules/` |
| Gerados / runtime | `backend/storage/`, `backend/uploads/`, `playwright-report/`, `test-results/` |
| Configuração | `docker/`, `docker-compose*.yml`, `.env.example`, `backend/config/` |
| Público HTTP | `backend/public/` |
| Documentação | `backend/doc_sistema/`, `especificacoes/`, `.claude/docs/`, `familia/docs/` |

Árvore anotada completa: `backend/doc_sistema/estrutura.md` (fonte interna, atualizada 2026-11-09).

---

## 4. Classificação da arquitetura

### Veredito

**Arquitetura híbrida: monolito modular + MVC pragmático + camadas**, com isolamento multi-tenant por conexão PDO.

| Estilo | Aplica? | Evidência |
|---|---|---|
| Monolito tradicional | Parcial | Um deploy PHP principal; Controllers legados grandes no Painel |
| Monolito modular | **Sim (predominante)** | 49 pastas em `Modulos/` com `manifest.php`, rotas próprias, FeatureGate |
| MVC | **Sim (pragmático)** | Controllers + Models + Views; Services como extensão |
| Camadas | **Sim** | Core / Middleware / Painel / Dominio / Modulos / Infra |
| Hexagonal / Ports & Adapters | **Não de forma sistemática** | Integrações existem, mas sem ports/interfaces consistentes |
| Clean Architecture | **Não** | Sem regras de dependência estritas nem entidades de domínio puras |
| Microsserviços | **Não** (exceto auxiliares) | Solver EducaSync e Apostila AI são serviços satélite; o núcleo é monolítico |

### Por que “modular” não é só “pastas”

Há evidências além de pastas:

1. Registro via `ModuloRegistry` + `manifest.php`
2. Feature flags por escola (`FeatureGate` / Master)
3. Rotas encapsuladas por módulo (`routes.php` / `routes.public.php`)
4. Namespace PSR-4 por módulo

Há também evidências de que **não** é modularidade forte de bounded context:

1. Cluster acadêmico com `use` cruzado entre provas/boletins/fechamento/regras
2. God-controllers no Painel ainda concentram regras de vários módulos
3. Sem DI container; serviços instanciados diretamente
4. Models/SQL frequentemente acessados de Controllers

**[INFERÊNCIA]:** a classificação mais fiel é *monolito modular em transição*, com núcleo acadêmico ainda acoplado.

---

## 5. Multi-tenant (característica estrutural)

| Aspecto | Implementação | Confiança |
|---|---|---|
| Modelo | 1 MySQL por escola + 1 MySQL master | **[FATO]** |
| Isolamento | PDO separado via `Database::setCurrentInstance()` | **[FATO]** |
| Resolução | Host → Redis fast-path → `TenantResolver` + MASTER | **[FATO]** |
| Credenciais tenant | Master `config_escolas_banco` + `MasterSecretVault` | **[FATO]** |
| Regra de projeto | Não usar `WHERE escola_id` em query de tenant | **[FATO]** (docs/regras) |
| Proteção de sessão cross-tenant | `AuthManager::sessaoConfereComEscolaAtual()` | **[FATO]** código; validação local citada em auditoria SEC-10 |

---

## 6. Processamento assíncrono e jobs

| Mecanismo | Detalhe | Evidência |
|---|---|---|
| Fila de IA | Tabela tenant `ai_jobs` | `AIJobService` |
| Worker | Cron `backend/cron/process_ai_jobs.php` + spawn CLI | idem |
| Outros crons | Financeiro, Asaas, TudiCoins, jornadas, backup, gabarito… | `backend/cron/` |
| Worker Docker dedicado de fila | **Ausente** no compose principal | **[FATO]** |

---

## 7. Observabilidade e operação (no repositório)

| Tema | Situação |
|---|---|
| Logs | `Logger` → `backend/storage/logs` (JSON / categorias); audit helper |
| CI de testes | **Ausente** — só `.github/workflows/deploy-main.yml` (deploy self-hosted) |
| Testes PHPUnit | `backend/tests/` (~20+ classes) + `phpunit.xml` |
| E2E | Playwright em `tests/` e `qa/` (cobertura pequena vs produto) |
| APM / tracing | **Não identificado** nesta passagem |

---

## 8. Adequação ao domínio multi-instituição

**[INFERÊNCIA sustentada por fatos]:** o desenho *um banco por escola + master SaaS* é adequado para SaaS educacional multi-instituição em termos de isolamento de dados e customização por escola (feature flags, créditos/TudiCoins, branding).

Limitações estruturais que um CTO deve declarar:

1. Deploy ainda monolítico (escala vertical/horizontal do PHP-FPM como unidade).
2. Fila IA em MySQL (simples, mas com teto de throughput).
3. Dívida de Controllers gigantes e migração incompleta Painel → Módulos.
4. CSRF e ownership nem sempre centralizados (dependem de disciplina por endpoint).

Detalhamento crítico: `DIAGNOSTICO_ARQUITETURAL.md`.  
Mapa de módulos: `MAPA_MODULOS_EDUCATUDO.md`.  
Roadmap: `PLANO_EVOLUCAO_ARQUITETURAL.md`.

---

## 9. Fontes internas consultadas

- `backend/doc_sistema/mapa.md`, `estrutura.md`
- `CLAUDE.md`, `AGENTS.md`, `.cursor/contexto/LEIA.md`
- `backend/composer.json`, `backend/package.json`, `docker-compose.yml`
- `backend/app/Core/*` (bootstrap, Router, Database, FeatureGate, AuthManager)
- `backend/config/routes.php` e rotas por perfil
- `especificacoes/auditoria-sec-10-blackphoenix-2026-11-20.md` (contexto de segurança já auditado)

---

*Documento gerado por auditoria arquitetural somente leitura. Não altera o código nem afirma comportamento de produção sem evidência no repositório.*
