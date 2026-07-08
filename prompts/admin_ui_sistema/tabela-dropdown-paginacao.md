# Tabela, Dropdown de Ações, Badges e Paginação

> Parte de `prompts/admin_ui_sistema.md`. Ler o índice lá antes se ainda não leu.

## 4. Tabela de listagem

Container + thead + row hover (padrão alunos):

```html
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coluna</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

**Célula com avatar + nome:**

```html
<div class="flex items-center">
    <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center flex-shrink-0">
        <span class="text-sm font-medium text-slate-600">AB</span>
    </div>
    <div class="ml-4 min-w-0">
        <div class="text-sm font-medium text-gray-900 truncate">Nome Completo</div>
    </div>
</div>
```

**Estado vazio:**

```html
<td colspan="N" class="px-6 py-12 text-center text-gray-500">
    <i class="fa-solid fa-user-graduate text-4xl text-gray-300 mb-4"></i>
    <p>Nenhum registro encontrado</p>
    <!-- CTA verde ou link "Limpar filtros" text-blue-600 -->
</td>
```

---

## 4b. Ações de linha — dropdown (sempre, qualquer quantidade)

**Toda ação de linha em listagem usa o dropdown único "Ações"** — partial `_partials/row_actions_dropdown.php`. Não empilhar botões pill lado a lado, **mesmo com só 2 ações** (ex.: Editar + Excluir). Confirmado pelo usuário em várias telas (Ano Letivo, Série, relatório de redação) mesmo com só 2 itens — não é mais "a partir de 3", é o padrão único pra qualquer contagem.

A seção "Botões" de `cores-e-botoes.md` ("Ação de linha", botão pill solto) só se aplica quando a linha tem **uma única ação** (ex.: só "Abrir" ou só "Detalhes") — nesse caso não há nada pra agrupar num dropdown.

```php
<?php ob_start(); ?>
<a href="<?= URL ?>/admin/turmas/<?= (int)$turma['id'] ?>"
   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
    <i class="fa-solid fa-circle-info text-gray-400 w-4 text-center"></i> Detalhes
</a>
<a href="<?= URL ?>/admin/turmas/<?= (int)$turma['id'] ?>/edit"
   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
    <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
</a>
<button type="button" onclick="toggleStatus(<?= (int)$turma['id'] ?>, <?= $turma['ativo'] ? 'false' : 'true' ?>)"
        class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
    <i class="fa-solid fa-power-off text-gray-400 w-4 text-center"></i> <?= $turma['ativo'] ? 'Desativar' : 'Ativar' ?>
</button>
<div class="border-t border-gray-100 my-1"></div>
<button type="button" onclick="openBulkDeleteSingle(<?= (int)$turma['id'] ?>)"
        class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
    <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
</button>
<?php $row_actions_dropdown_items = ob_get_clean(); ?>
<?php $row_actions_dropdown_id = 'row-actions-' . $turma['id']; ?>
<?php include __DIR__ . '/../_partials/row_actions_dropdown.php'; ?>
```

**Regras do dropdown:**
- Botão gatilho: branco com borda, `fa-chevron-down` à direita, texto "Ações".
- Itens: `flex items-center gap-2 px-4 py-2 text-sm` — cinza (`text-gray-700`) para ações normais, vermelho (`text-red-600`) só para destrutivas.
- Separador antes da ação destrutiva: `<div class="border-t border-gray-100 my-1"></div>`.
- JS de toggle/fechar é **global**, já carregado em `layouts/admin.php` — não duplicar `<script>` por página. Funciona por delegação de evento em `[data-dropdown-toggle]` / `[data-dropdown-menu]`.
- Largura do menu: `w-48`. Aumentar só se o texto do item não couber.

---

## 5. Badges de status

Sempre **pill** (`rounded-full`), `text-xs font-semibold`:

| Status | Classes |
|--------|---------|
| Ativo / sucesso | `bg-green-100 text-green-800` |
| Inativo / erro | `bg-red-100 text-red-800` |
| Pendente / aviso | `bg-amber-100 text-amber-800` |
| Neutro / encerrado | `bg-slate-100 text-slate-700` |
| Info / paralelo | `bg-blue-100 text-blue-800` ou `bg-indigo-100 text-indigo-800` |
| Transferido | `bg-gray-100 text-gray-600` |

```html
<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
    Ativo
</span>
```

---

## 8. Paginação

Padrão canônico (referência: `admin/exams/index.php`) — **aplicar em toda listagem com paginação**, não inventar variação:

```php
<?php
$pag = $pagination ?? [];
$total = (int)($pag['total'] ?? 0);
$perPage = (int)($pag['per_page'] ?? 10);
$page = (int)($pag['page'] ?? 1);
$totalPages = (int)($pag['total_pages'] ?? 1);
$queryParams = array_merge($_GET ?? [], []);
unset($queryParams['page']);
$baseQuery = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
$sep = $baseQuery === '' ? '?' : '&';
?>
<?php if ($total > 0): ?>
<div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
    <p class="text-sm text-gray-600">
        Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> registro(s)
    </p>
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center gap-1">
        <?php if ($page > 1): ?>
            <a href="ROTA<?= $baseQuery . $sep ?>page=<?= $page - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="ROTA<?= $baseQuery . $sep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="ROTA<?= $baseQuery . $sep ?>page=<?= $page + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
```

**Regras:**
- Localização: dentro do card da tabela, `border-t border-gray-200 px-6 py-4`, `flex justify-between` (texto à esquerda, botões à direita).
- Texto: `text-sm text-gray-600` — "Exibindo X–Y de Z registro(s)".
- Botões inativos: `px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200` (pílula cinza, **não** branco com borda).
- **Página ativa: `bg-primary text-white`** — classe utilitária já definida globalmente (`background-color: var(--primary-color)`), que usa a **mesma cor do navbar/sidebar** (configurável por escola via Layout do Sistema). **Nunca** hardcodar `bg-purple-600`/`bg-blue-600`/etc. aqui — sempre `bg-primary`, pra paginação acompanhar a identidade visual de cada escola automaticamente.
- **Itens por página: sempre 10** (`per_page = 10` no controller) — não usar 15 nem outro valor; é o padrão único do sistema pra toda listagem paginada.
- Janela de páginas: só mostra `page - 2` até `page + 2` (no máximo 5 números), não a lista inteira.
- "Anterior"/"Próxima" só aparecem quando existe página anterior/seguinte (não desabilitados visualmente — somem).
- `$queryParams` sempre preserva os filtros ativos da URL (`$_GET` menos `page`) para a paginação não resetar filtro aplicado.
- Só renderiza o bloco todo se `$total > 0`; só renderiza os números de página se `$totalPages > 1`.
