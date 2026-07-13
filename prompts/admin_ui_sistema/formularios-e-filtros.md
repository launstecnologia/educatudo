# Formulários (página própria) e Filtros (drawer)

> Parte de `prompts/admin_ui_sistema.md`. Ler o índice lá antes se ainda não leu.
> Para o padrão de offcanvas de cadastro (exceção da seção 1d), ver a skill `educatudo-admin-ui`, não este arquivo.

## 6. Formulários e campos

### Largura e layout

- **Card sempre largura total** (`w-full`) dentro da área `main` — **não** usar `max-w-2xl`, `max-w-3xl` nem `mx-auto` em CRUD padrão.
- Campos distribuídos em **grid 2 colunas no desktop** (`grid grid-cols-1 md:grid-cols-2 gap-6`); campos longos usam `md:col-span-2`.
- Rodapé de ações com fundo sutil (`bg-gray-50/50 border-t`) — partial `form_actions.php`.

**Partials reutilizáveis** (`src/app/Views/admin/_partials/`):

| Partial | Uso |
|---------|-----|
| `page_header_list.php` | Título + subtítulo + ações à direita (listagens) |
| `page_header_form.php` | Voltar + título + subtítulo (create/edit) |
| `form_actions.php` | Cancelar + submit verde no rodapé do form |
| `flash_message.php` | Alerta sucesso/erro inline |

### Estrutura do form (cadastro/edição)

```html
<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form class="divide-y divide-gray-200">
        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Nome da seção</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- campos -->
            </div>
        </div>
        <!-- include form_actions.php -->
    </form>
</div>
```

### Campo texto / email / senha

```html
<div>
    <label for="campo_id" class="block text-sm font-medium text-gray-700 mb-2">
        Label do campo <span class="text-red-500">*</span>
    </label>
    <input type="text" id="campo_id" name="campo_id" required
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
           placeholder="Texto de exemplo">
    <p class="mt-1 text-xs text-gray-500">Ajuda opcional abaixo do campo</p>
</div>
```

### Select

```html
<select id="campo_id" name="campo_id"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
    <option value="">Selecione...</option>
</select>
```

### Checkbox

```html
<label class="flex items-center">
    <input type="checkbox" name="ativo" value="1"
           class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
    <span class="ml-2 text-sm text-gray-700">Descrição da opção</span>
</label>
```

### Textarea

```html
<textarea name="observacao" rows="3"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
          placeholder="..."></textarea>
```

### Compatibilidade Safari (obrigatório em todo filtro/formulário)

Safari renderiza `input[type="date"]` e `select` de forma diferente do Chrome
(fonte maior, seta dupla nativa no select, alinhamento estranho na data) —
quebra a consistência visual do filtro/form se não for normalizado.

A normalização já está aplicada **globalmente** via
`layouts/components/form_control_safari.php`, incluído nos layouts
(`admin`, `master`, `professor`, `student`, `parent`, `monitor`, `teacher`).
(`-webkit-appearance: none` + tamanho de fonte fixo em date/time + seta
customizada SVG no select). **Não é necessário repetir CSS por página** —
só usar as classes Tailwind padrão do design system (`border border-gray-300
rounded-lg px-3 py-2 ...`) normalmente nos campos `<input type="date">` e
`<select>`. Se um filtro/form novo parecer "torto" no Safari, o problema é
quase sempre um override local de `appearance`/`font-size` no próprio
componente — não adicionar CSS novo, conferir se algo está sobrescrevendo a
regra global.

**Sempre testar visualmente (ou pedir confirmação) campos de data e select em
qualquer tela de filtro nova/alterada** — esse é o ponto mais comum de
quebra entre Chrome e Safari neste design system.

### Regras de formulário

- Grid **1 coluna mobile**, **2 colunas desktop** (`md:grid-cols-2 gap-6`).
- Campos largos (endereço, observação): `md:col-span-2`.
- **Focus em forms de cadastro:** `ring-green-500` (alinha ao botão Salvar).
- **Focus em filtros/drawer:** `ring-blue-500` (alinha ao Aplicar filtros).
- Campos obrigatórios: asterisco vermelho no label + `required` no HTML.
- Máscaras: classes `js-mask-cpf`, `js-mask-cep`, etc. (ver `_student_form_masks.php`).
- Sempre `htmlspecialchars()` na saída PHP; `_token` CSRF em forms POST.

### Cabeçalho de form com voltar

Preferir `page_header_form.php` (variáveis `$page_header_back_url`, `$page_header_title`, `$page_header_subtitle`).

```html
<!-- ou manualmente: -->
<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="URL_LISTAGEM" class="text-gray-500 hover:text-gray-700 flex-shrink-0" aria-label="Voltar">
            <!-- svg seta -->
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Título do formulário</h2>
            <p class="text-gray-600 text-sm">Instrução curta</p>
        </div>
    </div>
</div>
```

---

## 7. Filtros (drawer lateral)

Padrão da listagem de alunos — painel deslizante à **direita**:

- Backdrop: `fixed inset-0 bg-black/40 z-40`
- Drawer: `fixed top-0 right-0 h-full max-w-md bg-white shadow-2xl z-50`
- Header drawer: `px-6 py-4 border-b` + botão fechar `fa-xmark`
- Corpo: `flex-1 overflow-y-auto px-6 py-5 space-y-4`
- Rodapé fixo: `px-6 py-4 border-t bg-gray-50 flex gap-3` — **Limpar** (secundário) + **Aplicar** (azul)
- Fechar com Escape e clique no backdrop

Labels no drawer usam `mb-1.5` (ligeiramente mais compacto que forms).
