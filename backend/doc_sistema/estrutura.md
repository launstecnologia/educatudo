# Estrutura do sistema

Mapa do código versionado em `src/` (plataforma PHP).

> Atualizado: **2026-07-21** · Ver também [multi-tenant.md](multi-tenant.md) e [perfis.md](perfis.md)

---

## Visão em camadas

```
Request HTTP
    → bootstrap_multi_tenant (resolve escola / master)
    → Router (config/routes/<perfil>.php)
    → Middleware (Auth, CSRF, permissões…)
    → Controller (magro: coordena)
    → Service (regra de negócio)
    → Model (SQL prepared)
    → View (PHP + Tailwind) ou JSON
```

**Regra:** Controller > ~50 linhas de lógica → extrair Service. Módulo novo sempre tem Service.

---

## Árvore principal (`src/`)

```
src/
├── index.php                 ← front controller
├── config/
│   ├── app.php               ← env / config global
│   ├── routes.php            ← orquestra includes
│   └── routes/               ← master, admin, teacher, student…
├── app/
│   ├── Core/                 ← infra (Database, Tenant, Auth, FeatureGate…)
│   ├── Controllers/          ← por módulo / perfil (Admin/, Master/, …)
│   ├── Models/               ← SQL
│   ├── Services/             ← negócio
│   ├── Middleware/
│   └── Views/                ← admin/, master/, teacher/, student/…
├── database/migrations/      ← tenant + *_master.sql (+ rollback)
├── cron/                     ← jobs (CronMultiTenantHelper)
├── mcp/                      ← servers MCP (Node) opcionais
├── doc_sistema/              ← esta wiki (Markdown)
└── storage/                  ← logs, uploads, drive
```

---

## Core (não mexer sem necessidade)

| Arquivo | Papel |
|---|---|
| `bootstrap_multi_tenant.php` | Resolve tenant antes do App |
| `TenantResolver.php` | Domínio ou header `X-Tenant` |
| `DatabaseManager.php` | Credenciais + PDO por escola |
| `Database.php` | Singleton PDO atual |
| `AuthManager.php` / `Auth.php` | Sessão e perfil |
| `FeatureGate.php` | Features por escola |
| `AdminPermissionMatrix.php` | Permissões do admin |
| `CreditosModuleRegistry.php` | Catálogo TudiCoins / IA |
| `MigrationRunner.php` | Roda migrations por escola |

---

## Onde colocar código novo

| Tipo | Onde |
|---|---|
| Rota admin | `config/routes/admin.php` |
| Rota master | `config/routes/master.php` |
| Controller | `app/Controllers/<Mod>/` |
| Service | `app/Services/` |
| Model | `app/Models/<Mod>/` |
| View admin | `app/Views/admin/...` |
| Migration tenant | `database/migrations/YYYY_MM_DD_*.sql` + `_rollback.sql` |
| Migration master | `*_master.sql` + rollback |
| Doc desta wiki | `doc_sistema/<slug>.md` |
| MCP (opcional) | `mcp/<nome>/` |

Nomenclatura nova em **português** (ver `.claude/docs/nomenclatura.md`). Sufixos arquiteturais em EN: `Controller`, `Service`, `Model`.

---

## Segurança (checklist)

1. Prepared statements (`:id`) + cast de ids  
2. Ownership: recurso de aluno só se `aluno_id = usuário atual`  
3. CSRF em POST (exceto `/api/*` com JWT)  
4. Upload: `finfo_file()` + path com `TENANT_SLUG`  
5. Webhooks: validar HMAC  
6. IA > 2s: job assíncrono (`AIJobService`)  
7. Segredos só via env  

---

## Ambientes / URLs locais

```bash
docker-compose up -d
cp src/.env.example src/.env   # nunca commitar .env real
cd src && composer install
```

- Aluno `/` · Professor `/professor` · Admin `/admin` · Master `/master`  
- Setup master inicial: `http://localhost:8000/master`

---

## Referências no repositório

| Tema | Arquivo |
|---|---|
| Guia agentes | `CLAUDE.md`, `AGENTS.md` |
| Arquitetura | `.claude/docs/architecture.md` |
| Env | `.claude/docs/environment.md` |
| TudiCoins | `.claude/docs/tudicoins.md` |
| UI admin | `prompts/admin_ui_sistema.md` |
| Spec / backlog | `specs/PRD.md`, `specs/tasks.md` |
