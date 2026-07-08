# Tabela de listagem, dropdown de ações e paginação

Esse padrão **não é específico de offcanvas** — é o mesmo pra qualquer tela
admin, com ou sem offcanvas. Fonte única de verdade:
`prompts/admin_ui_sistema/tabela-dropdown-paginacao.md`, seções:

- **§4** — estrutura da tabela, célula com avatar+nome, estado vazio.
- **§4b** — dropdown de ações (`_partials/row_actions_dropdown.php`), sempre
  usado mesmo com 1-2 ações só, ordem fixa (Detalhes → Editar → Desativar →
  Excluir, destrutivo sempre por último e em vermelho).
- **§5** — badges de status (pill, cores semânticas fixas).
- **§8** — paginação canônica (10/página, preserva filtros da URL).

Não recrie essas seções aqui — se notar alguma divergência entre o que está
implementado numa tela nova e o que o documento principal descreve, corrija a
tela (ou abra a discussão), não documente uma variação nova sem necessidade.

## O que muda quando a tela tem offcanvas de cadastro (esta skill)

Só o botão "Editar" do dropdown: em vez de `<a href="…/edit">`, vira
`<button onclick="openXDrawer(id)">` (abre o offcanvas em vez de navegar).
Todo o resto do dropdown/tabela/paginação segue exatamente igual ao padrão do
documento principal.

```php
<?php ob_start(); ?>
<button type="button" onclick="openXDrawer(<?= (int)$row['id'] ?>)"
        class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
    <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
</button>
<div class="border-t border-gray-100 my-1"></div>
<button type="button" onclick="deleteX(<?= (int)$row['id'] ?>)"
        class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
    <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
</button>
<?php
$row_actions_dropdown_items = ob_get_clean();
$row_actions_dropdown_id = 'row-actions-x-' . (int)$row['id'];
include __DIR__ . '/../_partials/row_actions_dropdown.php';
?>
```
