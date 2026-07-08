# Responsividade e Compatibilidade entre Navegadores

Toda tela gerada com esta skill **precisa funcionar em mobile, tablet e desktop, e renderizar igual em Chrome/Safari/Firefox** — não é opcional. Isso já causou bug real neste projeto (select nativo quebrado no Safari) e por isso virou regra explícita.

## Regras cross-browser

- **Nunca deixar um `<select>` sem reset.** Sempre usar o padrão de `tokens.md` (`select-reset` + seta em SVG própria). Nunca confiar no visual nativo do navegador para select, ele varia demais.
- **Sempre usar Tailwind + flexbox/grid padrão**, evitar CSS específico de engine (`-webkit-*` solto) exceto quando for justamente para *neutralizar* diferenças (como no reset do select).
- Testar mentalmente (ou de fato, se possível) o resultado em pelo menos Chrome e Safari antes de considerar a tela pronta — são os dois motores mais usados e mais divergentes (Blink vs WebKit).
- Ícones SVG sempre com `w-X h-X` explícito. Nunca deixar um `<svg>` sem classe de tamanho — sem Tailwind carregado/aplicado corretamente ele renderiza no tamanho intrínseco do path, o que pode ficar gigante e quebrar o layout inteiro (bug real já visto neste projeto).
- Se o HTML for standalone (sem build step, aberto direto no navegador), usar o **Tailwind Play CDN** (`<script src="https://cdn.tailwindcss.com"></script>`), nunca uma cópia estática do Tailwind vinda de um CDN genérico tipo cdnjs — essas não compilam as classes usadas no HTML e o layout quebra silenciosamente (todo mundo empilhado, sem espaçamento, sem cor).

## Regras de responsividade (mobile-first)

Todo componente novo desta skill segue este raciocínio, nessa ordem: **1 coluna no mobile → adapta em `sm:`/`lg:` para telas maiores.**

- **Grids de cards (KPI, dashboard):** `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` (nunca fixar `grid-cols-4` sem os breakpoints menores).
- **Grids de formulário:** `grid-cols-1 sm:grid-cols-2` (no mobile todo campo ocupa a linha inteira, mesmo os que ficam lado a lado no desktop).
- **Sidebar do painel:** fixa e visível em `lg:` pra cima; em telas menores vira um drawer que abre por cima do conteúdo (overlay + slide), acionado por um botão hamburguer no header. Nunca simplesmente "sumir" sem dar um jeito de acessar a navegação no mobile.
- **Tabelas:** o container da tabela sempre entra num wrapper com `overflow-x-auto` — tabelas não devem quebrar o layout da página em telas estreitas, elas ganham scroll horizontal próprio.
- **Rodapé de tabela (contagem + paginação) e header de listagem (título + botões):** `flex-col sm:flex-row` — empilham no mobile, ficam lado a lado a partir do `sm`.
- **Offcanvas (formulário e filtro):** já são `w-full max-w-*`, então no mobile ocupam a tela inteira automaticamith — não precisa de ajuste extra de largura, mas o **padding interno deve reduzir no mobile** (`px-4 sm:px-8` em vez de `px-8` fixo) e os **botões do rodapé** (Salvar/Cancelar, Limpar/Aplicar) devem empilhar em coluna no mobile (`flex-col-reverse sm:flex-row`, com o botão primário no topo) para não ficarem espremidos.
- **Texto de título grande (`text-3xl`):** reduzir no mobile (`text-xl sm:text-3xl`) para não estourar em telas pequenas.
- **Avatar + nome do usuário no header:** o nome pode esconder no mobile (`hidden sm:inline`), mantendo só o avatar, para poupar espaço.

## Checklist rápido antes de entregar uma tela

1. Cabe em ~375px de largura sem gerar scroll horizontal na página inteira (só a tabela pode ter scroll próprio)?
2. O `<select>` usa o reset padrão?
3. Toda sidebar/menu lateral tem uma forma de ser acessada no mobile?
4. Os botões de rodapé de offcanvas não ficam espremidos/cortados numa tela pequena?
5. Nenhum ícone SVG está sem `w-X h-X`?
