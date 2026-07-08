# Tokens visuais — cor whitelabel real

Tipografia, espaçamento, botões, badges e inputs em geral: ver
`prompts/admin_ui_sistema/cores-e-botoes.md` (§1 e §3). Aqui só a cor whitelabel, porque é o
ponto que mais gera confusão (skills genéricas de design system costumam
assumir `var(--color-primary)`, que **não é** o mecanismo real deste
projeto).

## Cor primária (whitelabel) — mecanismo real

Definido em `src/app/Core/LayoutHelper.php`, aplicado globalmente no layout
admin a partir da config de cada escola (tenant). **Nunca** usar
`bg-blue-600`/`var(--color-primary)`/qualquer cor Tailwind fixa em elemento
de destaque — sempre as classes abaixo:

| Classe / variável | Uso |
|---|---|
| `.btn-primary-custom` | Botão de ação principal (CTA "+ Novo...", "Salvar"). Aplica `background-color: var(--button-primary-color)` + `color: var(--primary-text-color)`. |
| `.btn-secondary-custom` | Botão secundário com cor do tema (raro — a maioria dos botões secundários é outline cinza neutro, ver documento principal §3). |
| `.bg-primary` / `.text-primary` | Usado por exemplo na página ativa da paginação (`bg-primary text-white`). Aplica `var(--primary-color)` / `var(--primary-text-color)`. |
| `.text-primary-custom` | Texto na cor primária de texto configurada (`var(--text-primary-color)`). |
| `.text-success-custom` / `.text-warning-custom` / `.text-error-custom` / `.text-info-custom` | Cores semânticas configuráveis (`--success-color`, `--warning-color`, `--error-color`, `--info-color`) — usar só quando o padrão fixo (`bg-green-100 text-green-800` etc., documento principal §5) não servir. |

```html
<button class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
  Salvar
</button>
<a class="bg-primary text-white px-3 py-1.5 rounded-lg text-sm font-medium">1</a>
```

Hover em `.btn-primary-custom`: **`hover:opacity-90`**, nunca uma classe de
hover fixa (`hover:bg-blue-700`) — a cor é dinâmica por escola, não existe
"tom mais escuro" pré-calculado em Tailwind.

## Select cross-browser (reset)

Já normalizado globalmente em `src/app/Views/layouts/admin.php` — não
duplicar CSS por página. Só usar as classes Tailwind padrão nos `<select>`:

```html
<select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
```

Se um select específico aparecer "torto" no Safari, o problema quase sempre é
um CSS local sobrescrevendo `appearance`/`font-size` — não adicionar reset
novo, conferir se algo está sobrescrevendo a regra global (ver documento
principal §6, "Compatibilidade Safari").
