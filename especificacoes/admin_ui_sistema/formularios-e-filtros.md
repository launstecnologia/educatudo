# Formulários (página própria) e Filtros (drawer)

> Parte de `especificacoes/admin_ui_sistema.md`. Ler o índice lá antes se ainda não leu.
> Para o padrão de offcanvas de cadastro (exceção da seção 1d), ver a skill `educatudo-admin-ui`, não este arquivo.

## 6. Formulários e campos

### Largura e layout

**Referência canônica:** `backend/app/Views/admin/exams/blocks/create.php`

- **Card sempre largura total** (`w-full`) dentro da área `main` — **não** usar `max-w-2xl`, `max-w-3xl` nem `mx-auto` em CRUD padrão.
- Cabeçalho: título + subtítulo à esquerda, link `← Voltar` à direita (`flex justify-between`).
- Card: `bg-white rounded-xl shadow-lg p-6` (ou `rounded-lg shadow-sm border` nos CRUDs mais novos com `form_actions.php`).
- Campos: `mb-6`; grid 2 colunas no desktop (`grid grid-cols-1 md:grid-cols-2 gap-6`); campos longos usam linha inteira / `md:col-span-2`.
- Inputs: `w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent`.
- Rodapé de ações: `flex justify-end space-x-4` ou partial `form_actions.php`.

```html
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Título do formulário</h2>
            <p class="text-gray-600">Instrução curta</p>
        </div>
        <a href="URL_LISTAGEM" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" action="...">
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Campo <span class="text-red-500">*</span></label>
            <input class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- dois campos lado a lado -->
        </div>
        <div class="flex justify-end space-x-4">
            <a href="..." class="px-6 py-2 border border-gray-300 rounded-lg">Cancelar</a>
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg">Salvar</button>
        </div>
    </form>
</div>
```

### 6c. Vários cards de seção

Página com mais de um bloco (identificação, critérios, lista) usa um card por seção, com a faixa de título no topo. Referência: `backend/app/Modulos/regras-academicas/Views/admin/form.php`. Regra do agente: `.cursor/rules/admin-card-secao.mdc`.

- Cards empilhados, `w-full`, sem `max-w-*`.
- Faixa: `bg-gray-50`, `border-b`, número teal (1, 2, 3), título, uma frase e a ação da seção à direita.
- Corpo branco em `p-5`. O título não fica solto no miolo.
- Formulário de um bloco só permanece no card único acima. Offcanvas, wizard e dashboard ficam de fora.

**Partials reutilizáveis** (`backend/app/Views/admin/_partials/`):

| Partial | Uso |
|---------|-----|
| `page_header_list.php` | Título + subtítulo + ações à direita (listagens) |
| `page_header_form.php` | Voltar (ícone) + título + subtítulo (alternativa ao header acima) |
| `form_actions.php` | Cancelar + submit no rodapé do form |
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

## 6b. Wizard (fluxo em várias etapas)

Quando o cadastro tem **múltiplas etapas ou conteúdo rico** (não é CRUD simples —
ver `estrutura-e-cadastro.md` §1d para a exceção de offcanvas), a página própria
usa o padrão de wizard: nav de passos numerados no topo + um `step-panel` por
etapa (só um visível por vez) + rodapé com Voltar/Próximo, e uma etapa final de
revisão antes de salvar.

**Demo navegável:** `/admin/configuracao/ui-modelos/wizard` (perfil dev).
**Referência real completa** (com upload, IA, aprovação): `backend/app/Views/admin/inclusao/manage.php`.

**Partials** (`backend/app/Views/admin/_partials/ui/`):

| Partial | Uso |
|---|---|
| `wizard_steps.php` | Nav de passos (número + label + conector), com 4 estados: `ativo` (cor do sistema), `completo` (verde + check), `erro` (vermelho + exclamação), `pendente` (cinza). Vars: `$ui_wizard_steps` (array de `['label', 'sub']`), `$ui_wizard_current`, `$ui_wizard_completed` (array\<int\>, opcional), `$ui_wizard_error_steps` (array\<int\>, opcional). |
| `wizard_actions.php` | Rodapé Voltar/Próximo de uma etapa. Vars: `$ui_wizard_actions_back_step` (int\|null), `$ui_wizard_actions_next_label`, `$ui_wizard_actions_next_id`, `$ui_wizard_actions_next_type`. |

**Regras:**

- Etapa **ativa** usa cor do sistema (`border-accent bg-primary text-primary`) —
  **nunca hardcodar verde/azul** para o estado ativo (era o padrão antigo em
  `inclusao/manage.php`, não replicar em wizard novo). Os estados **completo**
  (verde) e **erro** (vermelho) já são cor semântica fixa, igual às variantes
  `ativo`/`erro` de `badge.php` — esses dois não seguem whitelabel.
- Precedência de estado por etapa: **erro > ativo > completo > pendente**
  (uma etapa com erro aparece vermelha mesmo se for a etapa atual).
- Validação que decide `completo`/`erro` é responsabilidade do JS da própria
  página (ex.: `wizardBtnStep1Next` em `ui-modelos/wizard.php` só marca a
  etapa como concluída se os campos obrigatórios estiverem preenchidos) — os
  partials só desenham o estado, não validam nada sozinhos.
- Cada `step-panel` fica dentro de um `card.php` variant `form` (mesmo card
  branco largura total dos formulários simples).
- Troca de etapa é 100% client-side (`classList.toggle('hidden', ...)`), sem
  reload — copiar o `<script>` inline de `ui-modelos/wizard.php` como base
  (não existe um controller JS compartilhado; cada wizard tem o seu, pequeno).
- Etapa de revisão final monta o resumo com `document.createElement` +
  `textContent` (nunca concatenar `innerHTML` com valor de input do usuário).
- Só a última etapa tem `type="submit"`; as etapas anteriores usam
  `type="button"` e avançam via JS (permitindo salvar parcial/rascunho quando
  o fluxo real precisar, como em `inclusao/manage.php`).

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
