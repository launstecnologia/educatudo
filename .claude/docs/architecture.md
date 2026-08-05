# Arquitetura — detalhes técnicos

> Referência completa. O resumo essencial está no CLAUDE.md.

## Fluxo de resolução de tenant

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

Arquivos críticos (`backend/app/Core/`):
- `bootstrap_multi_tenant.php` — ponto de entrada do tenant
- `TenantResolver.php` — resolve tenant por domínio/header
- `DatabaseManager.php` — gerencia conexões por tenant
- `Database.php` — wrapper PDO singleton
- `Auth.php` / `AuthManager.php` — autenticação e sessão
- `FeatureGate.php` — feature flags por escola
- `AdminPermissionMatrix.php` — permissões por perfil de admin
- `Logger.php` — logs JSON com TENANT_ID automático
- `MigrationRunner.php` — execução de migrations por escola
- Schema do master: `backend/database/migrations/multi_tenant_master.sql`

## Estrutura completa de diretórios

```
backend/
├── index.php                        ← front controller
├── config/
│   ├── app.php                      ← configuração global (lê .env)
│   ├── routes.php                   ← orquestrador (~20 linhas)
│   └── routes/                      ← rotas por perfil
│       master.php · public.php · student.php · monitor.php
│       teacher.php · admin.php · parents.php
├── app/
│   ├── Core/                        ← infraestrutura (ver lista acima)
│   ├── Controllers/                 ← coordenam request → Service/Model → View
│   │   Master/ Admin/ Education/ User/ Exams/ Essays/ Teacher/ Api/ ...
│   ├── Models/                      ← acesso a dados (SQL)
│   │   Essays/ Exams/ Education/ Forum/ Study/ Drive/ User/ Simulados/ Notifications/
│   │   Noticia.php, RedacaoLivre*.php  ← soltos, mover para subpastas futuramente
│   ├── Services/                    ← lógica de negócio (46 services)
│   │   Asaas/ EssayAIService CreditosService JWTService AIJobService ...
│   ├── Middleware/
│   │   AuthMiddleware (sessão+perfil) · ApiAuthMiddleware (JWT /api/*)
│   │   AuditMiddleware · GameSecurityMiddleware · PasswordCheckMiddleware
│   ├── Helpers/ Utils/
│   └── Views/
│       admin/ (175) · student/ (101) · teacher/ (86) · master/ (53)
│       parents/ (14) · monitor/ (5) · layouts/ components/ partials/
├── database/migrations/             ← .sql versionados (tenant e _master)
├── cron/                            ← scripts agendados
└── storage/
    logs/ (JSON) · uploads/ (públicos) · drive/ (privado dos alunos)
```

## Crons

Scripts em `backend/cron/`, agendados via crontab. Usar `CronMultiTenantHelper` para iterar sobre todas as escolas:

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

## Pontos de atenção técnica

- `Database::setCurrentInstance()` é estático global — seguro em PHP-FPM (isolamento por processo), não usar em Swoole/RoadRunner/ReactPHP sem refatorar
- `ATTR_EMULATE_PREPARES=true` em alguns casos — necessário para evitar erro HY093 em queries com parâmetros repetidos
- Redis opcional com fallback para file cache — em produção multi-servidor, Redis deve ser obrigatório e o fallback desabilitado
- Módulos sem Model/Service (Games, Exercises, Apostilas, Minicursos, EducaHits) — toda lógica está no Controller; ao tocar nesses módulos, extrair para Service antes de adicionar features
