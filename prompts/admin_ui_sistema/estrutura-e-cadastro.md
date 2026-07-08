# Estrutura de Tela e Regras de Cadastro

> Parte de `prompts/admin_ui_sistema.md`. Ler o índice lá antes se ainda não leu.

## 1c. Uma tela, um cadastro (OBRIGATÓRIO)

**Cada tipo de cadastro tem a sua própria página.** É proibido colocar dois
formulários de cadastro de entidades diferentes na mesma tela (ex.: cadastrar
"Curso" e "Categoria" lado a lado; cadastrar "Disciplina" e "Período" na mesma
página). Isso polui a tela, mistura contextos e quebra o padrão de listagem.

**Como aplicar:**

- A tela de listagem de uma entidade mostra **somente** a lista daquela entidade
  + o CTA "+ Novo …" daquela entidade.
- Entidades auxiliares relacionadas (categorias, períodos, tipos, etc.) ganham
  **página própria** de listagem/cadastro, acessível por um **botão secundário
  outline** no cabeçalho da listagem principal (ex.: "Categorias").
- O cadastro em si fica numa página de formulário dedicada (ou, no máximo, um
  drawer/modal da própria entidade), nunca um segundo painel de outra entidade
  na mesma tela.

```
ERRADO: /admin/ava            → lista de Cursos + painel lateral "Nova Categoria"
CERTO:  /admin/ava            → lista de Cursos (CTA "+ Novo Curso" + botão "Categorias")
        /admin/ava/categorias → lista + cadastro de Categorias (página própria)
```

Exceção: sub-itens que pertencem hierarquicamente ao registro aberto numa tela
de **detalhe/gestão** (ex.: aulas dentro de um módulo, na tela daquele módulo)
não são "outro cadastro" — são o conteúdo do próprio registro. Mas dois
cadastros **irmãos** (mesmo nível) sempre se separam em páginas distintas.

---

## 1d. Cadastro em offcanvas — exceção aceita para CRUD simples

A regra padrão de criar/editar (ver `formularios-e-filtros.md` §6) é **página
própria**. Para uma categoria específica de tela — **cadastro simples de
entidade única** — é aceito usar um **offcanvas único** (painel lateral,
reaproveitado pra criar e editar) em vez de página separada. Essa variação
está documentada em detalhe na skill `educatudo-admin-ui`
(`.claude/skills/educatudo-admin-ui/`), que deve ser lida junto com este
documento quando a tela em questão se encaixar nos critérios abaixo. Este
documento continua sendo a fonte única de verdade para tudo que **não** é
específico do offcanvas (cor, tabela, badges, paginação, dropdown de ações,
campos de formulário) — a skill referencia estes arquivos em vez de
duplicá-los.

**Use offcanvas quando TODOS os critérios abaixo forem verdadeiros:**

1. A entidade é "achatada" — não tem sub-recursos complexos nem múltiplas
   etapas de preenchimento (ex.: usuário, professor, unidade — não "prova",
   que tem questões, blocos e configurações próprias).
2. O formulário cabe confortavelmente num painel lateral rolável, sem precisar
   do layout de página inteira (grid 2 colunas, poucas seções).
3. Não há necessidade de compartilhar/salvar um link direto para "continuar
   editando depois" — a edição é uma ação rápida, não uma sessão de trabalho
   longa.

**Mantêm página própria (ou fluxo de várias etapas) — offcanvas NÃO se aplica:**
Provas (`/admin/exams`), Blocos de Prova, Jornadas (`/admin/jornadas`),
Redações/Propostas, Planos de Aula, AVA/Cursos (aulas, avaliações,
certificados), e qualquer tela com conteúdo rico (editor de texto, upload
múltiplo, sub-recursos com sua própria listagem). Essas continuam seguindo
`formularios-e-filtros.md` §6 (página dedicada, `page_header_form.php`).

**Referências de implementação real (offcanvas):**

| Tela | Arquivos |
|---|---|
| Usuários | `src/app/Views/admin/usuarios/index.php`, `src/app/Controllers/User/UserController.php` |
| Professores | `src/app/Views/admin/teachers/index.php`, `src/app/Controllers/Admin/TeacherAdminController.php` |
| Monitores | `src/app/Views/admin/monitors/index.php`, `src/app/Controllers/Admin/MonitorAdminController.php` |
| Perfis de Permissão | `src/app/Views/admin/permissoes-perfis/index.php`, `src/app/Controllers/User/AdminPermissionProfileController.php` |
| Instituição/Unidades | `src/app/Views/admin/units/index.php`, `src/app/Controllers/Admin/SchoolUnitsAdminController.php` |

Padrão comum a essas 5 telas (detalhado na skill): a view vira um único
`index.php` (sem `create.php`/`edit.php` separados); o form do offcanvas tem
`data-mode="create|edit"`; o modo editar popula os campos via `fetch` a um
endpoint novo `GET .../{id}/dados` que devolve JSON (nunca renderiza página).

---

## 2. Cabeçalho de página (listagem)

Preferir `page_header_list.php` (variáveis `$page_header_title`, `$page_header_subtitle`, `$page_header_actions`).

Padrão da Lista de Alunos:

```html
<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">TÍTULO DA PÁGINA</h2>
            <p class="text-gray-600 text-sm">Subtítulo curto — o que o usuário faz aqui</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <!-- Botões de ação (filtros + CTA) -->
        </div>
    </div>
</div>
```

**Regras:**
- Um único `<h2>` por página no conteúdo (o layout já tem barra superior "Dashboard Admin").
- Subtítulo sempre `text-sm`, nunca competir com o título.
- Ações alinhadas à direita; em mobile empilhar com `flex-wrap` se necessário.
