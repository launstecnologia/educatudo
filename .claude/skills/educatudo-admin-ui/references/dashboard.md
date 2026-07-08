# Dashboard — Header, Cards de KPI, Alertas e Gráficos

Ler `tokens.md` primeiro (cor whitelabel real: `btn-primary-custom`/`bg-primary`, não `var(--color-primary)`). Componentes abaixo vieram de uma tela real de dashboard do sistema, mas não foram reconferidos contra o código atual do EducaTudo nesta revisão — ao usar, confira contra uma tela de dashboard real do repo (`src/app/Views/admin/**/dashboard*.php` ou similar) e ajuste este arquivo se algo divergir.

## Header do Dashboard

```html
<div class="flex items-center justify-between px-8 py-6 border-b border-gray-200 bg-white">
  <div>
    <h1 class="text-3xl font-bold text-gray-900">Dashboard Admin</h1>
    <p class="text-sm text-gray-500">Bem-vindo, {{nome_usuario}}!</p>
  </div>
  <div class="flex items-center gap-5">
    <button class="text-gray-400 hover:text-gray-600 relative">
      <svg class="w-6 h-6"><!-- sino --></svg>
      <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full"></span>
    </button>
    <div class="flex items-center gap-3">
      <img src="{{avatar_url}}" class="w-10 h-10 rounded-full object-cover">
      <span class="text-sm font-semibold text-gray-900">{{nome_usuario}}</span>
    </div>
  </div>
</div>
```

Layout geral: sidebar fixa à esquerda com barra de destaque de 3-4px na cor primária indicando item ativo (Imagem 5 da conversa anterior), conteúdo principal à direita com `bg-gray-50`.

## Cards de KPI (indicador numérico)

Grid de 4 colunas. Cada card: rótulo em cima, valor grande e bold embaixo, ícone quadrado arredondado à direita do rótulo.

**Importante — cor do ícone do KPI:** diferente dos botões/links de ação, os ícones de KPI usam uma **paleta fixa variada por métrica** (azul, verde, amarelo, roxo...) para diferenciar visualmente os indicadores entre si — isso **não é** a cor primária do whitelabel (`bg-primary`/`btn-primary-custom`, ver `tokens.md`). Só use a cor primária em elementos de ação/interação (botões, aba ativa, links), não em cards informativos como esses.

```html
<div class="grid grid-cols-4 gap-5">

  <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-start justify-between">
    <div>
      <p class="text-sm text-gray-500 mb-2">Total Solicitações</p>
      <p class="text-3xl font-bold text-gray-900">764</p>
    </div>
    <div class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
      <svg class="w-5 h-5 text-blue-600"><!-- ícone prancheta --></svg>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-start justify-between">
    <div>
      <p class="text-sm text-gray-500 mb-2">Concluídas</p>
      <p class="text-3xl font-bold text-gray-900">446</p>
    </div>
    <div class="w-11 h-11 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
      <svg class="w-5 h-5 text-green-600"><!-- ícone check --></svg>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-start justify-between">
    <div>
      <p class="text-sm text-gray-500 mb-2">Tempo Médio</p>
      <p class="text-3xl font-bold text-gray-900">85h</p>
    </div>
    <div class="w-11 h-11 rounded-xl bg-yellow-100 flex items-center justify-center shrink-0">
      <svg class="w-5 h-5 text-yellow-600"><!-- ícone relógio --></svg>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-start justify-between">
    <div>
      <p class="text-sm text-gray-500 mb-2">Satisfação</p>
      <p class="text-3xl font-bold text-gray-900">0/5</p>
    </div>
    <div class="w-11 h-11 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
      <svg class="w-5 h-5 text-purple-600"><!-- ícone estrela --></svg>
    </div>
  </div>

</div>
```

Se um KPI precisar mostrar variação (▲/▼ %), adicionar abaixo do valor: `<p class="text-xs font-semibold text-green-600 mt-1">▲ 4,2% este mês</p>` (negativo: `text-red-600` + ▼). Isso é opcional e não estava em todos os cards da referência.

## Banner de Alerta / Ação Pendente

Usado para chamar atenção para algo que precisa de ação manual (aprovação, triagem, pendência). Fundo em tom suave da cor semântica (âmbar para atenção, vermelho para urgente/erro, azul para informativo), ícone circular à esquerda, título + descrição no meio, botão de ação (na mesma cor semântica, preenchido) com contador badge à direita.

```html
<div class="flex items-center justify-between gap-6 bg-amber-50 border-l-4 border-amber-400 rounded-xl px-6 py-5">
  <div class="flex items-center gap-4">
    <div class="w-11 h-11 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
      <svg class="w-5 h-5 text-amber-600"><!-- ícone documento --></svg>
    </div>
    <div>
      <div class="flex items-center gap-2 mb-1">
        <svg class="w-4 h-4 text-amber-500"><!-- ícone alerta triângulo --></svg>
        <h3 class="text-base font-bold text-gray-900">Solicitações Manuais Aguardando Triagem</h3>
      </div>
      <p class="text-sm text-gray-600">
        Você tem <span class="font-semibold text-amber-700">2 solicitações</span> criadas por usuários não logados aguardando revisão e migração para o sistema.
      </p>
    </div>
  </div>
  <button class="shrink-0 px-5 py-2.5 rounded-lg font-semibold text-white bg-amber-500 hover:bg-amber-600 flex items-center gap-2">
    <svg class="w-4 h-4"><!-- ícone olho --></svg>
    Revisar Agora
    <span class="w-5 h-5 rounded-full bg-white/25 text-xs flex items-center justify-center">2</span>
  </button>
</div>
```

Este banner usa cor semântica fixa (âmbar = pendência, vermelho = crítico, azul = informativo) — **não** é whitelabel, porque comunica um estado de sistema, igual aos badges de status.

## Filtro de Período (abas segmentadas)

Grupo de botões tipo pill, aba ativa preenchida na cor primária (aqui sim é whitelabel — é uma ação/seleção do usuário), inativas em cinza claro neutro.

```html
<div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center justify-between">
  <h3 class="text-lg font-bold text-gray-900">Período de Análise</h3>
  <div class="flex items-center gap-2 bg-gray-100 rounded-lg p-1">
    <button class="px-4 py-2 rounded-md text-sm font-semibold text-gray-600 hover:bg-white">7 dias</button>
    <button class="px-4 py-2 rounded-md text-sm font-semibold text-white bg-primary">30 dias</button>
    <button class="px-4 py-2 rounded-md text-sm font-semibold text-gray-600 hover:bg-white">90 dias</button>
  </div>
</div>
```

## Card de Gráfico

Container padrão com título à esquerda e botão "Ver maior" (expandir) à direita, no mesmo estilo outline dos outros botões secundários do sistema.

```html
<div class="bg-white rounded-xl border border-gray-200 p-6">
  <div class="flex items-center justify-between mb-6">
    <h3 class="text-lg font-bold text-gray-900">Solicitações por Status</h3>
    <button class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-1.5">
      <svg class="w-4 h-4"><!-- ícone expandir --></svg> Ver maior
    </button>
  </div>

  <div class="h-72">
    <!-- área do gráfico (Chart.js/similar) -->
  </div>
</div>
```

**Gráfico de rosca (donut)** — usado para distribuição por status/categoria. Cores das fatias seguem paleta fixa de status (verde=concluído, roxo=em andamento, cinza=cancelado, azul=outro), não a cor whitelabel — porque cada fatia representa um status semântico diferente, igual aos badges.

```js
new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: ['Concluídas', 'Em andamento', 'Canceladas', 'Outras'],
    datasets: [{
      data: [58, 30, 10, 2],
      backgroundColor: ['#10b981', '#8b5cf6', '#6b7280', '#3b82f6'],
      borderWidth: 0,
    }]
  },
  options: { cutout: '65%', plugins: { legend: { display: false } } }
});
```

**Gráfico de barras horizontais** — usado para ranking (ex: solicitações por imobiliária). Aqui a série é única (não múltiplos status), então **usa a cor primária do whitelabel** (`--primary-color`, mesma variável de `bg-primary` — ver `tokens.md`) — é o caso em que o gráfico reflete a cor do tema do cliente.

```js
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ['França Imóveis', 'Pedro Granado Imóveis', 'Lago Imóveis', '...'],
    datasets: [{
      data: [92, 84, 71, 68],
      backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim(),
      borderRadius: 4,
    }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } } }
});
```

**Regra geral de cor em gráficos:** se o gráfico representa uma única série medindo "quantidade de algo" (ranking, evolução no tempo) → cor primária do whitelabel. Se o gráfico compara categorias/status diferentes entre si (donut de status, etc.) → paleta fixa semântica, para não ficar tudo com um tom só e ilegível.

### Grade sugerida de dashboard completo

```html
<div class="p-8 space-y-6">
  <!-- Linha 1: 4 cards de KPI -->
  <div class="grid grid-cols-4 gap-5"> ... </div>

  <!-- Linha 2: banner de alerta (só aparece se houver pendência) -->
  <div> ... banner de alerta ... </div>

  <!-- Linha 3: filtro de período -->
  <div> ... abas de período ... </div>

  <!-- Linha 4: dois cards de gráfico lado a lado -->
  <div class="grid grid-cols-2 gap-5">
    <div><!-- card de gráfico donut --></div>
    <div><!-- card de gráfico de barras --></div>
  </div>
</div>
```

## Regras fixas

- Todo card de dashboard usa o mesmo container base: `bg-white rounded-xl border border-gray-200`, sem exceção.
- Ícone de KPI: container `w-11 h-11 rounded-xl` com fundo `-100` e ícone `-600` da mesma família de cor (ex: `bg-blue-100` + `text-blue-600`) — paleta fixa variada por métrica, não whitelabel.
- Banner de alerta: cor semântica fixa conforme severidade (âmbar/vermelho/azul), borda esquerda de 4px na mesma cor, botão de ação preenchido na mesma cor semântica com contador em badge translúcido.
- Filtro de período / seleção do usuário: cor primária whitelabel na opção ativa.
- Botão "Ver maior" em card de gráfico: sempre outline neutro, igual botão secundário padrão do sistema.
- Gráfico de série única (ranking, evolução) → cor primária do whitelabel (`--primary-color`). Gráfico de múltiplas categorias/status → paleta fixa semântica.
