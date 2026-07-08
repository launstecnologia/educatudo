# Cores e Botões

> Parte de `prompts/admin_ui_sistema.md`. Ler o índice lá antes se ainda não leu.

## 1. Fundamentos visuais

| Token | Valor / classe |
|-------|----------------|
| Fundo da página | `#f8fafc` (`bg-slate-50` / `page-background`) |
| Card / painel | `bg-white rounded-xl shadow-sm border border-gray-200` |
| Card formulário | `bg-white rounded-lg shadow-sm border border-gray-200` (pode usar `divide-y divide-gray-200` entre seções) |
| Texto principal | `text-gray-900` |
| Texto secundário | `text-gray-600 text-sm` |
| Labels de campo | `text-sm font-medium text-gray-700` |
| Bordas | `border-gray-200` / inputs `border-gray-300` |
| Ícones | Font Awesome; tamanho comum `mr-2` no botão, `text-xl` no drawer |

**CTA principal — SEMPRE `bg-primary` (OBRIGATÓRIO):**

O botão de ação principal de uma listagem (criar / registrar / "+ Novo …") **deve usar a cor do sistema** com as classes `bg-primary text-primary` — a **mesma cor do navbar/sidebar**, configurável por escola via Layout do Sistema (variável global `--primary-color`). **Nunca** hardcodar `bg-green-600`, `bg-blue-600`, `bg-gray-800` ou gradiente no CTA principal. Assim cada escola vê seus botões na própria identidade visual, exatamente como a paginação ativa (`bg-primary`).

```html
<a href="..." class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
    <i class="fa-solid fa-plus mr-2"></i>
    Registrar novo ...
</a>
```

- Hover do CTA: `hover:opacity-90` (não existe `hover:bg-primary-700`; a cor é dinâmica).
- `bg-primary` aplica `background-color: var(--primary-color)` e `text-primary` aplica `color: var(--primary-text-color)` (utilitários globais já definidos em `LayoutHelper`). Classe equivalente pra botão: `.btn-primary-custom`.

**Demais acentos (não misturar, nunca uma cor por ação):**

- **Filtros / ações secundárias / gatilho do dropdown "Ações"** — outline cinza (branco com borda `border-gray-300`). Só muda o ícone, nunca a cor.
- **Submit de filtro (drawer) / submit de confirmação** — pode usar `bg-primary` (acompanha o CTA) ou azul; manter um único padrão na tela.
- **Ação de linha "Detalhes" (única)** — pill azul suave (`bg-blue-50 text-blue-700`).
- **Destrutivo** — vermelho, sempre dentro de dropdown ou modal.

Sidebar é do layout global (`sidebar-custom`); **não** repetir a cor da sidebar nos cards do conteúdo.

---

## 1b. Grupo de botões no cabeçalho (listagem com múltiplas ações)

Quando o cabeçalho de uma listagem tem **mais de um botão de ação** (filtro +
navegações secundárias + CTA principal), **não inventar uma cor por botão**
(gradientes coloridos por ação é o erro mais comum — gera poluição visual e
nenhuma hierarquia clara de qual ação é a importante).

**Ordem fixa, da esquerda pra direita:**

1. **Filtros** (se a listagem tiver) — botão outline cinza, sempre primeiro.
2. **Ações secundárias** (navegação pra outra tela relacionada, ex.: "Tipo de
   Avaliação", "Bloco Professor") — **mesmo estilo outline cinza do Filtros**,
   só troca o ícone. Nunca gradiente, nunca cor própria por botão.
3. **CTA principal** (criar/cadastrar novo registro) — **sempre o último**
   (mais à direita), **sempre `bg-primary`** (cor do sistema/navbar), nunca verde,
   azul ou outra cor hardcoded.

```html
<div class="flex items-center gap-3 flex-wrap">
    <!-- 1. Filtros -->
    <button type="button" onclick="openFilterDrawer()"
            class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
        Filtros
        <?php if ($filtrosAtivosCount > 0): ?>
        <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
        <?php endif; ?>
    </button>

    <!-- 2. Ações secundárias (quantas precisar, mesmo estilo) -->
    <a href="..." class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-layer-group mr-2 text-gray-500"></i>
        Tipo de Avaliação
    </a>
    <a href="..." class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-table-cells mr-2 text-gray-500"></i>
        Bloco Professor
    </a>

    <!-- 3. CTA principal — sempre bg-primary (cor do sistema), sempre por último -->
    <a href="..." class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
        <i class="fa-solid fa-plus mr-2"></i>
        Nova Prova
    </a>
</div>
```

**Regras:**
- Tamanho único: `px-4 py-2.5 text-sm`, `rounded-lg` — nunca `px-6 py-3` nem `rounded-xl` (era o tamanho "inflado" do padrão antigo, descontinuado).
- Sem gradiente em nenhum botão de cabeçalho, em nenhuma situação.
- CTA principal **sempre `bg-primary text-primary`** (cor do sistema), nunca verde/azul/cinza hardcoded.
- Badge de contagem (ex.: filtros ativos) usa `bg-blue-600`, não roxo nem outra cor — é o único acento de "contador" do sistema.
- Se houver mais de ~3 ações secundárias, considerar mover algumas pra dentro de um dropdown (ver `tabela-dropdown-paginacao.md` §4b) em vez de continuar adicionando botões na régua do cabeçalho.

---

## 3. Botões

### Primário — criar / registrar / salvar (`bg-primary`, cor do sistema)

CTA principal de listagem usa **sempre** a cor do sistema (`bg-primary text-primary`), igual ao navbar/sidebar — configurável por escola. Nunca hardcodar verde/azul.

```html
<a href="..." class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
    <i class="fa-solid fa-plus mr-2"></i>
    Registrar novo ...
</a>
```

Formulário (submit) — também `bg-primary`:

```html
<button type="submit" class="px-6 py-2 bg-primary text-primary rounded-lg hover:opacity-90 transition-colors">
    Salvar
</button>
```

### Secundário — cancelar / limpar

```html
<a href="..." class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
    Cancelar
</a>
```

### Filtros / utilitários (branco com borda)

```html
<button type="button" class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
    Filtros
    <!-- badge contagem: ml-2 min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold -->
</button>
```

### Ação de linha — detalhes / info (azul suave)

```html
<a href="..." class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-sm font-medium hover:bg-blue-100 hover:border-blue-300 transition-colors">
    <i class="fa-solid fa-circle-info text-blue-600"></i>
    Detalhes
</a>
```

### Submit de filtro / confirmação (azul)

```html
<button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
    Aplicar filtros
</button>
```

### Destrutivo (usar com modals)

```html
<button type="button" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors">
    Excluir
</button>
```
