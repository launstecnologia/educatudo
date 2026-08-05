# Multi-tenant

Isolamento entre escolas no EducaTudo.

> Atualizado: **2026-07-21** · Ver [estrutura.md](estrutura.md)

---

## Regra nº 1

O isolamento é pela **conexão PDO separada** (um banco por escola), **não** por filtro SQL.

**Nunca** adicionar `WHERE escola_id` em query de tenant — isso indica bug de arquitetura.

---

## Fluxo

```
Request HTTP
    │
    ▼
bootstrap_multi_tenant.php
    │
    ├─ domínio/path master? ──► banco master
    │
    └─ senão:
         TenantResolver (HTTP_HOST ou X-Tenant)
              │ cache Redis ~60s
              ▼
         DatabaseManager (credenciais no master)
              ▼
         Database::setCurrentInstance()  ← PDO do tenant
              ▼
         TENANT_ID / TENANT_SLUG / TENANT_DOMAIN
              ▼
         App → Router → Controller…
```

---

## Bancos

| Banco | Conteúdo |
|---|---|
| **Master** | Escolas, credenciais de conexão, usuários master, catálogo TudiCoins, faturamento SaaS, migrations tracking |
| **Tenant (escola)** | Alunos, professores, provas, jornadas, boletim, diário, etc. |

Migrations:

- `NNN_descricao.sql` → roda em **cada** escola  
- `NNN_descricao_master.sql` → só no master  
- Sempre criar `_rollback.sql`  
- Executar via `/master/migrations` ou `php src/scripts/run_migrations.php` — **nunca** direto no banco

---

## Cuidados

- `Database::setCurrentInstance()` é singleton global — seguro em PHP-FPM; **não** usar em workers paralelos/assíncronos sem isolamento.
- Sessão/config de tenant em produção: **Redis** (não depender só de file cache).
- Uploads e paths físicos devem incluir `TENANT_SLUG`.
- Logs JSON levam `TENANT_ID` automaticamente (`Logger`).

---

## Crons

Scripts em `src/cron/`. Para iterar escolas:

`CronMultiTenantHelper` — conecta em cada tenant e executa o job.

---

## Master vs Admin escola

| Painel | URL | Banco |
|---|---|---|
| Master | `/master` | Master |
| Admin escola | `/admin` | Tenant da escola |

A wiki de documentação (`doc_sistema/`) é só filesystem — disponível em **ambos** os painéis sem query de tenant.
