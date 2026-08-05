# Documentação EducaTudo

Wiki interna do sistema. Fonte: pasta `src/doc_sistema/` (arquivos Markdown).

> **Master:** [/master/documentacao](/master/documentacao)  
> **Admin escola:** [/admin/doc-sistema](/admin/doc-sistema)  
> Última atualização: **2026-07-21**

---

## O que é

**EducaTudo** é um SaaS educacional multi-tenant em PHP puro (sem framework).  
Cada escola = **banco MySQL isolado**. O banco **master** guarda escolas, credenciais, catálogos globais (TudiCoins, faturamento, etc.).

Stack: PHP 8.2 · MySQL 8 · Redis · Tailwind · OpenAI · AWS S3 (opcional).

---

## Índice desta wiki

| Página | Conteúdo |
|---|---|
| [estrutura.md](estrutura.md) | Mapa de pastas, camadas, onde colocar código novo |
| [multi-tenant.md](multi-tenant.md) | Isolamento por PDO, TenantResolver, o que **não** fazer |
| [perfis.md](perfis.md) | URLs e papéis (aluno, professor, admin, master…) |
| [assistente.md](assistente.md) | Chat IA de coordenação + arquitetura das consultas |
| [tool.md](tool.md) | Catálogo completo das tools (Assistente / MCP) |

Docs técnicas extras no repo (fora desta wiki): `.claude/docs/`, `CLAUDE.md`, `specs/`.

---

## Como manter

1. Edite ou crie um `.md` em `src/doc_sistema/`.
2. O slug da URL = nome do arquivo (ex.: `estrutura.md` → `/master/documentacao/estrutura`).
3. Atualize este índice e a data no topo.
4. `README.md` nesta pasta **não** aparece na wiki (só para quem lê no Git).

---

## Atalhos úteis (Master)

- Escolas: `/master/escolas`
- Migrations: `/master/migrations`
- TudiCoins / catálogo: `/master/creditos-catalogo/tabelas`
- Faturamento: `/master/faturamento`
