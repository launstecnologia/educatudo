# CLAUDE.md — EducaTudo

Guia de contexto para agentes de IA e desenvolvedores novos. Leia antes de tocar em qualquer código.

## O que é este projeto

**EducaTudo** é uma plataforma educacional SaaS multi-tenant em PHP puro (sem framework).
Cada escola é um **tenant isolado com banco de dados próprio**; um banco master guarda o cadastro de escolas, credenciais de conexão e catálogos globais.

Stack: PHP 8.2 · MySQL 8 · Redis · Tailwind CSS · OpenAI API · AWS S3 (opcional).
Neste repo: `src/` = plataforma PHP (único diretório versionado no submódulo) · `app/` = app Flutter dos pais · Playwright na raiz.

## Como rodar localmente

```bash
docker-compose up -d               # containers (PHP roda no Docker, não no host)
cp src/.env.example src/.env       # editar DB_*, MULTI_TENANT, MASTER_DOMAIN
cd src && composer install
# Setup inicial do banco master: http://localhost:8000/master
```

URLs locais: aluno `/` · professor `/professor` · admin `/admin` · master `/master`.
Env vars e requisitos completos: @.claude/docs/environment.md

## Regra nº 1 — isolamento multi-tenant

O isolamento entre escolas é garantido pela **conexão PDO separada** (um banco por escola), não por filtros SQL.
**Nunca adicionar `WHERE escola_id` em query de tenant** — isso indica bug de arquitetura.

Fluxo: `bootstrap_multi_tenant.php` → `TenantResolver` (por domínio ou header `X-Tenant`) → `DatabaseManager` → `Database::setCurrentInstance()` → constantes `TENANT_ID/SLUG/DOMAIN` → App.

Diagrama completo, arquivos do Core e pontos de atenção: @.claude/docs/architecture.md

## Mapa do código (`src/`)

```
config/routes/           rotas por perfil (master, public, student, monitor, teacher, admin, parents)
app/Core/                infraestrutura — não tocar sem entender o impacto
app/Controllers/<Mod>/   coordenam: request → Service/Model → View
app/Models/<Mod>/        acesso a dados (SQL, prepared statements)
app/Services/            lógica de negócio (46 services)
app/Middleware/          Auth (sessão+perfil), ApiAuth (JWT), Audit, GameSecurity, PasswordCheck
app/Views/<perfil>/      admin, student, teacher, master, parents, monitor
database/migrations/     .sql versionados (tenant e _master)
cron/                    scripts agendados (CronMultiTenantHelper itera as escolas)
storage/                 logs/ (JSON com TENANT_ID) · uploads/ · drive/
```

## Perfis de usuário

| Perfil | URL base | Banco | Observação |
|---|---|---|---|
| Aluno | `/` | Tenant | Consome conteúdo, faz provas e redações |
| Professor | `/professor` | Tenant | Cria jornadas, provas, corrige redações |
| Admin (escola) | `/admin` | Tenant | Gestão completa da escola |
| Monitor | `/monitor` | Tenant | Supervisão em tempo real, somente leitura |
| Pais | `/pais` ou API JWT | Tenant | Acompanha filho, apenas leitura |
| Master | `/master` | **Master** | Gerencia escolas, migrações, financeiro do SaaS |

## Convenções essenciais

Detalhe completo com exemplos: @.claude/docs/coding-standards.md
Código de referência para copiar estrutura: `.claude/examples/` (Controller, Service, Model).

- **Nomenclatura em inglês** para classes/métodos/arquivos novos. Exceções: `Simulados`, `BoletimConfig`, `GradeHoraria`. Tabelas do banco ficam em PT (legado).
- **Prepared statements sempre** — parâmetros nomeados (`:id`), cast explícito de ids. Zero concatenação em SQL.
- **Controller magro**: coordena e renderiza. Mais de ~50 linhas de lógica de negócio → extrair para Service. Todo módulo novo tem Service, mesmo pequeno.
- **Módulo novo** segue Controllers/<Mod> + Models/<Mod> + Service + Views por perfil; rotas em `config/routes/<perfil>.php`; se opcional por escola, registrar no `FeatureGate`. Use o comando `/new-module`.

## Migrações

- `NNN_descricao.sql` = tenant (roda em cada escola) · `NNN_descricao_master.sql` = banco master · recentes usam `YYYY_MM_DD_`.
- **Sempre** criar o `_rollback.sql` correspondente.
- Executar via painel Master (`/master/migrations`) ou `php src/scripts/run_migrations.php` — **nunca direto no banco**.
- Use o comando `/migration` e valide com o subagent `migration-checker`.

## Segurança — regras obrigatórias

1. Prepared statements sempre — zero exceções
2. Uploads: validar MIME real com `finfo_file()`; path físico inclui `TENANT_SLUG`
3. Ownership: recurso de aluno só é servido após confirmar `aluno_id = $currentUserId`
4. Webhooks externos (Asaas, JaaS): validar HMAC antes de processar
5. `MASTER_ENCRYPTION_KEY` só via env — nunca hardcoded ou no banco
6. CSRF em toda rota POST (exceto `/api/*` com JWT)
7. Chamada de IA > 2s: sempre assíncrona (job via `AIJobService`), nunca na request

## O que NÃO fazer

- Não usar `Database::setCurrentInstance()` em contexto assíncrono/paralelo — singleton global, seguro só em PHP-FPM
- Não adicionar `WHERE escola_id` em queries de tenant
- Não depender de file cache em produção para sessão/config de tenant — Redis é obrigatório
- Não commitar `.env` com credenciais reais
- Não rodar migrations diretamente no banco
- Não replicar nomenclatura em PT em arquivos novos

## Onde encontrar informações

| Precisa saber | Onde olhar |
|---|---|
| Arquitetura, diretórios completos, crons, pontos de atenção | `.claude/docs/architecture.md` |
| Env vars e integrações externas (OpenAI, Asaas, JaaS, S3…) | `.claude/docs/environment.md` |
| Padrões de código com exemplos | `.claude/docs/coding-standards.md` |
| Rotas | `src/config/routes/*.php` (por perfil) |
| Schema master / tenant | `src/database/migrations/` |
| Permissões de admin · feature flags | `src/app/Core/AdminPermissionMatrix.php` · `FeatureGate.php` |
| Documentação técnica detalhada | `src/DOCUMENTATION.md` e `src/docs/` (30+ arquivos) |
| API de pais (JWT) | `src/docs/API_PAIS_ROTAS_E_CAMPOS.md` |

## Especificações e workflow com Claude Code

Fluxo spec-driven: atualizar a spec → Plan Mode → aprovar → implementar.

- Requisitos de produto: @specs/PRD.md
- Decisões de arquitetura (com o porquê): @specs/design.md
- Backlog atual: @specs/tasks.md

Ferramentas do projeto:
- **Subagents**: `code-reviewer` (invocar após qualquer mudança em PHP) e `migration-checker` (após criar/alterar migration)
- **Comandos**: `/new-module <nome>` · `/migration <descrição>` · `/fix-issue <problema>`
- **Skill** `testing`: rodar/escrever testes Playwright (ficam na raiz, não em `src/`)
- **Hooks** (`.claude/settings.json`): `php -l` após editar `.php` (se PHP estiver no PATH) e bloqueio de edição direta de `.env`
