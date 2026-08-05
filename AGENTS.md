# AGENTS.md — EducaTudo (Cursor)

Guia para agentes de IA no Cursor. O contexto completo do projeto está em `CLAUDE.md` — leia antes de tocar em código.

Documentação detalhada: `.claude/docs/` · Exemplos de código: `.claude/examples/` · Specs: `specs/`

## Projeto em uma frase

SaaS educacional multi-tenant em PHP puro (sem framework). Cada escola = banco PDO isolado. Stack: PHP 8.2 · MySQL 8 · Redis · Tailwind · OpenAI.

## Regra nº 1 — multi-tenant

Isolamento é pela **conexão PDO separada**, não por `WHERE escola_id` em queries de tenant. Isso indica bug de arquitetura.

## Workflow spec-driven

1. Entender o pedido → estruturar (objetivo, perfis, dados, escopo)
2. Registrar em `specs/tasks.md` se for feature relevante
3. Planejar se tocar 3+ arquivos
4. Implementar seguindo `.cursor/rules/` e `CLAUDE.md`
5. Revisar: `code-reviewer` após PHP · `migration-checker` após migrations
6. Resumir o que mudou

## Comandos de workflow (`.cursor/commands/`)

| Comando | Quando usar |
|---|---|
| `task` | Feature, tela, ajuste, relatório |
| `new-module` | Módulo novo do zero |
| `migration` | Tabela/coluna nova |
| `fix-issue` | Bug com causa raiz |

Digite o conteúdo do arquivo correspondente como instrução, ou descreva o pedido referenciando o comando.

## Regras automáticas (`.cursor/rules/`)

| Regra | Escopo |
|---|---|
| `educatudo-core` | Sempre ativa — arquitetura, segurança, mapa |
| `php-padroes` | `backend/**/*.php` — camadas, SQL, nomenclatura |
| `migrations` | `backend/database/migrations/**` |
| `admin-ui` | `backend/app/Views/admin/**` |
| `form-control-safari` | `backend/app/Views/**/*.php` — select/date Safari via partial global |
| `playwright` | `tests/**`, `e2e/**` |

## Skills do projeto (`.claude/skills/`)

- `educatudo-admin-ui` — offcanvas para CRUD simples do admin
- `testing` — testes E2E Playwright (raiz do repo, não em `src/`)

## Telas admin

Antes de criar/alterar tela admin: ler `prompts/admin_ui_sistema.md` (índice do design system). CRUD simples → skill `educatudo-admin-ui`.

## Hooks (`.cursor/hooks.json`)

- `php -l` após editar `.php`
- Bloqueio de edição direta de `.env`

## O que NÃO fazer

- `WHERE escola_id` em tenant · migrations direto no banco · commitar `.env`
- `Database::setCurrentInstance()` em contexto assíncrono
- Nomenclatura em inglês em arquivos novos (regra vigente: português — ver `.claude/docs/nomenclatura.md`)
- Renomear tabelas em lote ou pares ambíguos sem decisão de produto

## Referências rápidas

| Precisa saber | Onde |
|---|---|
| Arquitetura | `.claude/docs/architecture.md` |
| Padrões de código | `.claude/docs/coding-standards.md` |
| Nomenclatura PT/EN | `.claude/docs/nomenclatura.md` |
| TudiCoins (IA / créditos) | `.claude/docs/tudicoins.md` |
| Env vars | `.claude/docs/environment.md` |
| Como pedir coisas à IA | `.claude/docs/working-with-ai.md` |
| Backlog | `specs/tasks.md` |
| Decisões técnicas | `specs/design.md` |
| Modularização (piloto Arquivos) | `.claude/docs/modularizacao.md` |
