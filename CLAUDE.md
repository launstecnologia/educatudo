# CLAUDE.md — EducaTudo

Guia de contexto para agentes de IA e desenvolvedores novos. Leia antes de tocar em qualquer código.

---

## O que é este projeto

**EducaTudo** é uma plataforma educacional SaaS multi-tenant construída em PHP puro (sem framework).
Cada escola é um **tenant isolado com banco de dados próprio**. Um banco master centralizado guarda o cadastro de escolas, credenciais de conexão e catálogos globais.

Stack: PHP 8.2 · MySQL 8 · Redis · Tailwind CSS · OpenAI API · AWS S3 (opcional)

---

## Como rodar localmente

```bash
# 1. Subir containers
docker-compose up -d

# 2. Copiar e ajustar variáveis de ambiente
cp src/.env.example src/.env   # editar DB_*, MULTI_TENANT, MASTER_DOMAIN

# 3. Instalar dependências PHP
cd src && composer install

# 4. Criar banco master e executar migration base
# Acesse: http://localhost:8000/master → setup inicial

# 5. Acessar a plataforma
# Aluno:     http://localhost:8000/
# Professor: http://localhost:8000/professor
# Admin:     http://localhost:8000/admin
# Master:    http://localhost:8000/master  (ou MASTER_DOMAIN configurado)
```

Requisitos mínimos: PHP 8.0+, MySQL 8, Redis, extensões `pdo_mysql`, `gd`, `mbstring`, `fileinfo`.

---

## Variáveis de ambiente críticas

| Variável | Obrigatória | Descrição |
|---|---|---|
| `MULTI_TENANT` | Sim | `true` = multi-escola, `false` = instância única |
| `MASTER_DOMAIN` | Se multi-tenant | Domínio do painel master (ex: `master.educatudo.com`) |
| `DB_HOST/NAME/USER/PASS` | Sim | Banco master (ou único banco se single-tenant) |
| `MASTER_ENCRYPTION_KEY` | Sim | Chave AES-256 para encriptar senhas de banco dos tenants |
| `REDIS_URL` | Produção | Redis para cache de tenant e sessões. Obrigatório em multi-instância |
| `SESSION_DOMAIN` | Produção | `.educatudo.com` para cookies cross-subdomínio |
| `SESSION_SECURE` | Produção | `true` em HTTPS |
| `ENTRA_COMO_SECRET` | Sim | Secret para o Master entrar como admin de uma escola |
| `OPENAI_API_KEY` | Funcionalidades IA | Chat Tudinha, correção de redações, flashcards, slides |

---

## Arquitetura multi-tenant

```
Request HTTP
    │
    ▼
bootstrap_multi_tenant.php          ← carregado ANTES de App()
    │
    ├─ é domínio/path master? ──► usa banco master direto
    │
    └─ senão:
         TenantResolver               ← resolve escola por HTTP_HOST ou header X-Tenant
             │  (cache Redis 60s)
             ▼
         DatabaseManager              ← busca credenciais em config_escolas_banco (master)
             │
             ▼
         Database::setCurrentInstance()  ← PDO isolado para o tenant
             │
             ▼
         define(TENANT_ID, TENANT_SLUG, TENANT_DOMAIN)
             │
             ▼
         App → Router → Middleware → Controller
```

**Princípio fundamental:** isolamento é garantido pela **conexão PDO separada**, não por filtros `WHERE escola_id`. Nunca adicionar `WHERE escola_id` nas queries de tenant — isso indica bug de arquitetura.

Arquivos críticos:
- `src/app/Core/bootstrap_multi_tenant.php` — ponto de entrada do tenant
- `src/app/Core/TenantResolver.php` — resolve tenant por domínio/header
- `src/app/Core/DatabaseManager.php` — gerencia conexões por tenant
- `src/database/migrations/multi_tenant_master.sql` — schema do banco master

---

## Estrutura de diretórios

```
src/
├── index.php                        ← front controller
├── .env                             ← variáveis de ambiente (não commitado)
├── config/
│   ├── app.php                      ← configuração global (lê .env)
│   ├── routes/                      ← rotas por perfil
│   │   ├── master.php               ← painel SaaS (sem auth)
│   │   ├── public.php               ← rotas públicas + ApiAuth JWT
│   │   ├── student.php              ← aluno (dentro do Auth middleware)
│   │   ├── monitor.php              ← monitor de sala
│   │   ├── teacher.php              ← professor
│   │   ├── admin.php                ← coordenação/direção
│   │   └── parents.php              ← pais + notificações
│   └── routes.php                   ← orquestrador (~20 linhas)
├── app/
│   ├── Core/                        ← infraestrutura (não tocar sem entender o impacto)
│   │   ├── bootstrap_multi_tenant.php
│   │   ├── TenantResolver.php
│   │   ├── DatabaseManager.php
│   │   ├── Database.php             ← wrapper PDO singleton
│   │   ├── Auth.php / AuthManager.php
│   │   ├── MigrationRunner.php
│   │   ├── FeatureGate.php          ← feature flags por escola
│   │   ├── RedisCache.php
│   │   ├── Logger.php               ← logs JSON com TENANT_ID automático
│   │   └── cron_multi_tenant_helper.php
│   ├── Controllers/                 ← recebem request, chamam Services/Models, renderizam View
│   │   ├── Master/                  ← painel SaaS (gestão de escolas)
│   │   ├── Admin/                   ← coordenação/direção da escola
│   │   ├── Education/               ← estrutura escolar (turmas, séries, jornadas)
│   │   ├── User/                    ← aluno, professor, pais, monitor, admin
│   │   ├── Exams/                   ← provas e blocos de prova
│   │   ├── Essays/                  ← redações configuráveis
│   │   ├── Teacher/                 ← funcionalidades específicas do professor
│   │   ├── Api/                     ← endpoints REST (JWT, para app mobile pais)
│   │   └── [outros módulos]/
│   ├── Models/                      ← acesso a dados (queries SQL)
│   │   ├── Essays/                  ← mais completo: EssayBoard, EssaySubmission, etc.
│   │   ├── Exams/                   ← Exam, ExamBlock, TeacherExam, etc.
│   │   ├── Education/               ← ClassRoom, Subject, LessonPlan, etc.
│   │   ├── Forum/ Study/ Drive/ User/ Simulados/ Notifications/
│   │   ├── Noticia.php              ← solto (mover para Models/Noticias/ futuramente)
│   │   ├── RedacaoLivreEnvio.php    ← solto (mover para Models/Essays/ futuramente)
│   │   └── RedacaoLivreCorrecao.php ← solto (mover para Models/Essays/ futuramente)
│   ├── Services/                    ← lógica de negócio (46 services)
│   │   ├── Asaas/                   ← integração pagamentos
│   │   ├── EssayAIService.php       ← correção de redação por IA
│   │   ├── CreditosService.php      ← carteira de créditos
│   │   ├── JWTService.php           ← tokens para API de pais
│   │   ├── TenantCreditsCheckoutService.php
│   │   └── [outros services]/
│   ├── Middleware/
│   │   ├── AuthMiddleware.php       ← valida sessão + perfil
│   │   ├── ApiAuthMiddleware.php    ← valida JWT para /api/*
│   │   ├── AuditMiddleware.php
│   │   ├── GameSecurityMiddleware.php
│   │   └── PasswordCheckMiddleware.php
│   ├── Helpers/                     ← funções utilitárias sem estado
│   ├── Utils/
│   └── Views/
│       ├── admin/                   ← 175 views para coordenação/direção
│       ├── master/                  ← 53 views do painel SaaS
│       ├── student/                 ← 101 views do aluno
│       ├── teacher/                 ← 86 views do professor
│       ├── parents/                 ← 14 views dos pais
│       ├── monitor/                 ← 5 views do monitor de sala
│       └── layouts/ components/ partials/
├── database/
│   └── migrations/                  ← arquivos .sql versionados
│       ├── multi_tenant_master.sql  ← schema inicial do banco master
│       └── NNN_nome.sql             ← migrations de tenant (executadas por escola)
├── cron/                            ← scripts agendados (rodar via crontab)
└── storage/
    ├── logs/                        ← logs JSON estruturados
    ├── uploads/                     ← uploads públicos
    └── drive/                       ← drive privado dos alunos
```

---

## Perfis de usuário e onde acessam

| Perfil | URL base | Banco | Observação |
|---|---|---|---|
| Aluno | `/` | Tenant | Consome conteúdo, faz provas e redações |
| Professor | `/professor` | Tenant | Cria jornadas, provas, corrige redações |
| Admin (escola) | `/admin` | Tenant | Gestão completa da escola |
| Monitor | `/monitor` | Tenant | Supervisão em tempo real, somente leitura |
| Pais | `/pais` ou API JWT | Tenant | Acompanha filho, apenas leitura |
| Master | `/master` | **Master** | Gerencia escolas/tenants, migrações, financeiro |

---

## Convenções de código

### Nomenclatura — padrão: inglês (EN)

Classes, métodos e nomes de arquivo usam **inglês**. Exceções aceitas: termos sem tradução natural em contexto educacional brasileiro (`Simulados`, `BoletimConfig`, `GradeHoraria`).

**Conflitos conhecidos a corrigir** (não replicar o padrão antigo):

| Errado (PT) | Correto (EN) |
|---|---|
| `AlunoMovimentacaoService` | `StudentMovementService` |
| `JornadasRelatorioService` | `JourneyReportService` |
| `RedacaoLivreEnvio` | `EssayFreeSubmission` |
| `NotificacaoApi` | `ApiNotification` |

### Estrutura de um módulo novo

Ao criar um módulo novo, seguir esta checklist:

```
Controllers/MeuModulo/
    AdminMeuModuloController.php     ← se tiver tela de admin
    StudentMeuModuloController.php   ← se aluno acessa
    TeacherMeuModuloController.php   ← se professor acessa

Models/MeuModulo/
    MeuModulo.php                    ← entidade principal
    MeuModuloItem.php                ← entidades relacionadas

Services/
    MeuModuloService.php             ← lógica de negócio (se existir)

Views/
    admin/meu-modulo/                ← views de admin
    student/meu-modulo/              ← views de aluno
    teacher/meu-modulo/              ← views de professor
```

Adicionar rotas no arquivo `config/routes/<perfil>.php` correspondente (student, teacher, admin, master, public).

Se o módulo for opcional por escola, registrar em `FeatureGate.php`.

### Queries SQL

**Sempre** usar prepared statements. Nunca concatenar variáveis em SQL.

```php
// CORRETO
$db->fetch("SELECT * FROM alunos WHERE id = ?", [$alunoId]);

// ERRADO — nunca fazer
$db->query("SELECT * FROM alunos WHERE id = $alunoId");
```

Nunca adicionar `WHERE escola_id = X` em queries de tenant — o isolamento é feito pela conexão PDO, não por filtro.

### Controllers

Controllers são coordenadores, não executores. Regra: se o método tem mais de ~50 linhas de lógica de negócio, extrair para um Service.

```php
// CORRETO
public function store(Request $request): void {
    $this->essayService->createSubmission($request->data());
    $this->render('student/essays/success');
}

// EVITAR — lógica de negócio no controller
public function store(Request $request): void {
    $db = Database::getInstance();
    // 80 linhas de queries e regras aqui...
}
```

---

## Migrações

Migrations ficam em `src/database/migrations/` como arquivos `.sql`.

Nomenclatura:
- `NNN_descricao.sql` — migration de tenant (executada em cada escola)
- `NNN_descricao_master.sql` — migration do banco master (executada uma vez)
- `YYYY_MM_DD_descricao.sql` — migrations recentes usam data

**Executar via painel Master:** `/master/migrations` → selecionar escola → executar.

**Executar via CLI:**
```bash
php src/scripts/run_migrations.php
```

**Regra importante:** sempre criar um `NNN_descricao_rollback.sql` correspondente para poder reverter em caso de erro.

---

## Crons

Scripts em `src/cron/` para tarefas agendadas. Usar `CronMultiTenantHelper` para iterar sobre todas as escolas:

```php
require_once __DIR__ . '/../app/Core/cron_multi_tenant_helper.php';

CronMultiTenantHelper::run(function (?int $escolaId) {
    $db = Database::getInstance();
    // lógica que roda para cada escola individualmente
});
```

O helper skipa bancos iguais ao master e trata erros por escola sem interromper as demais.

Crons existentes:
- `atualizar_status_jornadas.php` — atualiza status de jornadas vencidas
- `backup.php` — backup dos bancos
- `llm_usage_daily.php` — consolida uso de tokens de IA
- `recarga_mensal_creditos.php` — renova créditos mensais dos planos
- `rss_update.php` — atualiza feed de notícias

---

## Segurança — regras obrigatórias

1. **Prepared statements sempre** — zero exceções
2. **Validar MIME real de uploads** — usar `finfo_file()`, nunca confiar na extensão
3. **Prefixo de tenant em uploads físicos** — path deve incluir `TENANT_SLUG` antes do nome do arquivo
4. **Validar ownership** — ao retornar recurso de um aluno específico, confirmar que `aluno_id = $currentUserId`
5. **Webhooks externos** — validar HMAC da assinatura antes de processar (Asaas, JaaS)
6. **`MASTER_ENCRYPTION_KEY`** — deve ser variável de ambiente, nunca hardcoded ou salva no banco
7. **CSRF** — todas as rotas POST (exceto `/api/*` com JWT) devem validar token CSRF

---

## Integrações externas

| Serviço | Variável env | Usado em |
|---|---|---|
| OpenAI | `OPENAI_API_KEY` | Chat Tudinha, correção redação, flashcards, slides, exercícios IA |
| ElevenLabs | `ELEVENLABS_API_KEY` | Áudio de feedback de redação |
| Google Vision / Supabase | `SUPABASE_TRANSCRIBE_URL` | OCR de redação manuscrita |
| OneSignal | `ONESIGNAL_APP_ID` | Push notifications |
| Asaas | `ASAAS_API_KEY` | Pagamentos (créditos/planos) |
| JaaS (Jitsi) | `JAAS_API_KEY` | Aulas online com gravação |
| AWS S3 | `AWS_*` | Storage de mídia (opcional; padrão = local) |
| Google Books | sem autenticação | Busca de livros |
| Evolution API | `EVOLUTION_API_*` | WhatsApp (notificações) |

Todas as chamadas externas com IA que demoram mais de 2s **devem ser assíncronas** (job em fila). Não bloquear a request HTTP.

---

## Onde encontrar informações

| Precisa saber | Onde olhar |
|---|---|
| Rotas disponíveis | `src/config/routes.php` (orquestrador) + `src/config/routes/*.php` (por perfil) |
| Schema do banco master | `src/database/migrations/multi_tenant_master.sql` |
| Schema de tenant | migrations numeradas em `src/database/migrations/` |
| Como o tenant é resolvido | `src/app/Core/TenantResolver.php` |
| Como conectar ao banco do tenant | `src/app/Core/DatabaseManager.php` |
| Permissões por perfil de admin | `src/app/Core/AdminPermissionMatrix.php` |
| Feature flags por escola | `src/app/Core/FeatureGate.php` |
| Documentação técnica detalhada | `src/DOCUMENTATION.md` |
| Docs de integrações específicas | `src/docs/` (30+ arquivos) |
| Config de deploy (nginx) | `src/docs/NGINX_CONFIG_EXAMPLE.md` |
| API de pais (JWT) | `src/docs/API_PAIS_ROTAS_E_CAMPOS.md` |

---

## O que NÃO fazer

- Não usar `Database::setCurrentInstance()` em contexto assíncrono/paralelo — é singleton global, funciona apenas em PHP síncrono (FPM)
- Não criar fallback de file cache em produção para sessão ou config de tenant — Redis é obrigatório
- Não adicionar `WHERE escola_id` em queries de tenant — quebra a arquitetura
- Não fazer chamada de IA dentro de request síncrona sem timeout explícito
- Não commitar `.env` com credenciais reais
- Não rodar migrations diretamente no banco — sempre via painel Master ou `run_migrations.php`
- Não replicar nomenclatura em PT para arquivos novos — usar inglês

---

## Pontos de atenção técnica

- `Database::setCurrentInstance()` é estático global — seguro em PHP-FPM (isolamento por processo), não usar em Swoole/RoadRunner/ReactPHP sem refatorar
- `ATTR_EMULATE_PREPARES=true` em alguns casos — necessário para evitar erro HY093 em queries com parâmetros repetidos
- Redis opcional com fallback para file cache — em produção multi-servidor, Redis deve ser obrigatório e o fallback desabilitado
- `routes.php` é agora um orquestrador (~20 linhas) que carrega `config/routes/*.php` divididos por perfil
- Módulos sem Model/Service (Games, Exercises, Apostilas, Minicursos, EducaHits) — toda lógica está no Controller; ao tocar nesses módulos, extrair para Service antes de adicionar features
