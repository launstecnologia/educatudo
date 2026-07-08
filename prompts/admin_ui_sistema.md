# UI Admin EducaTudo — Design System (índice)

Prompt reutilizável para manter **consistência visual** em novas telas, formulários, tabelas e ações do painel admin.

**Referência canônica:** [`src/app/Views/admin/students/index.php`](../src/app/Views/admin/students/index.php) (listagem) + [`create.php`](../src/app/Views/admin/students/create.php) (formulário).

**Stack:** PHP views + **Tailwind CSS (CDN)** + **Font Awesome 6.5** (`fa-solid`, `fa-regular`). Layout base: [`src/app/Views/layouts/admin.php`](../src/app/Views/layouts/admin.php).

Este documento é grande, então foi dividido em arquivos por assunto dentro de
`prompts/admin_ui_sistema/`. **Leia só o(s) arquivo(s) que precisar** — não
carregue tudo de uma vez.

---

## Como usar

1. Identifique o tipo de tela e leia só o(s) arquivo(s) relevantes na tabela abaixo.
2. Se for **cadastro simples** (CRUD de entidade única, sem sub-recursos nem múltiplas etapas), leia também `prompts/admin_ui_sistema/estrutura-e-cadastro.md` §1d — pode se aplicar a exceção de offcanvas (skill `educatudo-admin-ui`) em vez de página própria.
3. Pra pedir uma tela nova, copie o modelo de prompt em `checklist-e-modelo.md`.

## Índice — preciso de X → leio Y

| Preciso de... | Arquivo |
|---|---|
| Cor whitelabel (`bg-primary`, CTA), botões (primário/secundário/filtro/destrutivo) | `prompts/admin_ui_sistema/cores-e-botoes.md` |
| Regra "uma tela, um cadastro"; quando usar offcanvas vs página própria; cabeçalho de listagem | `prompts/admin_ui_sistema/estrutura-e-cadastro.md` |
| Tabela, dropdown de ações (`row_actions_dropdown.php`), badges de status, paginação | `prompts/admin_ui_sistema/tabela-dropdown-paginacao.md` |
| Formulário em página própria (campos, select, checkbox, Safari, máscaras); filtro em drawer | `prompts/admin_ui_sistema/formularios-e-filtros.md` |
| Mensagens flash, abas/pills, ficha detalhada | `prompts/admin_ui_sistema/outros-padroes.md` |
| Checklist final; modelo de prompt pra pedir uma tela nova | `prompts/admin_ui_sistema/checklist-e-modelo.md` |
| Offcanvas de cadastro simples (criar+editar no mesmo painel, endpoint `.../dados`) | `.claude/skills/educatudo-admin-ui/` (skill separada — ver `estrutura-e-cadastro.md` §1d antes) |

## Regras que valem pra qualquer arquivo acima (não repetir, não violar)

- Cor de destaque **sempre** `bg-primary`/`btn-primary-custom` (whitelabel) — nunca `bg-blue-600`/`bg-green-600`/gradiente hardcoded.
- Dropdown de ações **sempre**, mesmo com 1-2 itens só — nunca botões pill soltos na linha (exceção: uma única ação, tipo "Detalhes").
- Badge de status sempre pill (`rounded-full`) com cor semântica fixa.
- 10 itens por página em toda listagem paginada; filtros preservados na URL ao paginar.
- CSRF em todo POST não-API; `htmlspecialchars()` em toda saída PHP.
- Font Awesome 6.5, nunca emoji no UI (exceto títulos legacy já existentes).

---

## Arquivos de referência no repo

| Padrão | Arquivo |
|--------|---------|
| Partials listagem/form/flash | `src/app/Views/admin/_partials/` |
| Listagem + tabela + filtros + paginação | `src/app/Views/admin/students/index.php` |
| Form create + seções + campos (largura total) | `src/app/Views/admin/students/create.php` |
| CRUD simples refatorado | `src/app/Views/admin/serie/` (index, create, edit) |
| CRUD curso + importação CSV | `src/app/Views/admin/curso/` (index, create, edit, importar-alunos) |
| Ano letivo + turmas | `src/app/Views/admin/ano-letivo/`, `src/app/Views/admin/turmas/` |
| Campos documento/endereço | `src/app/Views/admin/students/_student_documento_endereco_fields.php` |
| Hub com abas + form em card | `src/app/Views/admin/students/_movimentacao_form.php` |
| Ficha detalhada | `src/app/Views/admin/students/show.php` |
| Layout shell | `src/app/Views/layouts/admin.php` |
| Sidebar | `src/app/Views/layouts/components/admin_sidebar.php` |
| CRUD simples em offcanvas (exceção da seção 1d) | `.claude/skills/educatudo-admin-ui/` — ver tabela de exemplos em `estrutura-e-cadastro.md` §1d |
