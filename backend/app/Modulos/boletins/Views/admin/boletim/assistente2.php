<?php
/**
 * Protótipo visual — redesign do construtor (etapa Fórmulas e Colunas).
 * Rota: /admin/boletim-configuracao/assistente2
 * Não conecta ao motor do assistente atual; só layout HTML + interações de UI.
 */
$urlVoltar = URL . '/admin/boletim-configuracao/assistente';
$trilhaItens = \App\Helpers\TrilhaAdmin::montar(
    '/admin/boletim-configuracao/assistente2',
    'Construtor de Fórmula (layout)',
    [
        ['rotulo' => 'Acadêmico', 'url' => URL . '/admin/academico'],
        ['rotulo' => 'Boletim', 'url' => URL . '/admin/eventos-avaliativos/boletim'],
        ['rotulo' => 'Assistente atual', 'url' => $urlVoltar],
        ['rotulo' => 'Layout novo'],
    ]
);
?>
<style>
    .bc2-root {
        --bc2-bg: #F8FAFC;
        --bc2-card: #FFFFFF;
        --bc2-primary: #5B35E6;
        --bc2-primary-soft: #EDE9FE;
        --bc2-border: #E5E7EB;
        --bc2-radius: 12px;
        --bc2-ok: #16A34A;
        --bc2-warn: #D97706;
        --bc2-err: #DC2626;
        background: var(--bc2-bg);
        margin: -1.5rem -1.5rem 0;
        padding: 1.25rem 1.5rem 6rem;
        min-height: calc(100vh - 4rem);
        color: #0f172a;
    }
    .bc2-card {
        background: var(--bc2-card);
        border: 1px solid var(--bc2-border);
        border-radius: var(--bc2-radius);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .bc2-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 9999px;
        padding: 0.4rem 0.75rem;
        font-size: 0.8125rem;
        font-weight: 600;
        border: 1px solid #E2E8F0;
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
        background: #F8FAFC;
        color: #334155;
    }
    .bc2-chip.is-nota { background: #FFF7ED; color: #9A3412; border-color: #FED7AA; }
    .bc2-chip.is-rec { background: #F5F3FF; color: #5B21B6; border-color: #DDD6FE; }
    .bc2-chip.is-media { background: #FFFBEB; color: #92400E; border-color: #FDE68A; }
    .bc2-chip.is-evento { background: #EFF6FF; color: #1E40AF; border-color: #BFDBFE; }
    .bc2-chip.is-op { background: #F8FAFC; color: #475569; border-color: #E2E8F0; font-family: ui-monospace, monospace; }
    .bc2-chip.is-fn { background: #FFF7ED; color: #9A3412; border-color: #FED7AA; }
    .bc2-chip.is-selected {
        border-color: var(--bc2-primary);
        box-shadow: 0 0 0 2px rgba(91, 53, 230, 0.18);
        background: #F5F3FF;
    }
    .bc2-scroll-y { overflow-y: auto; overscroll-behavior: contain; }
    .bc2-scroll-x { overflow-x: auto; overscroll-behavior: contain; scrollbar-width: thin; }
    .bc2-period-card {
        min-width: 300px;
        width: 300px;
        flex: 0 0 300px;
        border: 1px solid var(--bc2-border);
        border-radius: var(--bc2-radius);
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }
    .bc2-period-card.is-done { border-color: #E2E8F0; }
    .bc2-period-card.is-active {
        border-color: var(--bc2-primary);
        box-shadow: 0 0 0 3px rgba(91, 53, 230, 0.12);
    }
    .bc2-period-card.is-warn { border-color: #F59E0B; }
    .bc2-period-card .bc2-period-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.25;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .bc2-aluno.is-selected {
        background: var(--bc2-primary-soft);
        color: #4C1D95;
        font-weight: 600;
    }
    .bc2-aluno-vazio {
        display: none;
        padding: 1rem 0.75rem;
        text-align: center;
        font-size: 0.8125rem;
        color: #94a3b8;
    }
    .bc2-aluno-vazio.is-visible { display: block; }
    .bc2-btn-primary {
        background: var(--bc2-primary);
        color: #fff;
        border-radius: 0.65rem;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 0.55rem 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .bc2-btn-primary:hover { filter: brightness(1.05); }
    .bc2-btn-ghost {
        background: #fff;
        color: #334155;
        border: 1px solid var(--bc2-border);
        border-radius: 0.65rem;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 0.55rem 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .bc2-btn-ghost:hover { background: #F8FAFC; }
    .bc2-tab {
        border-radius: 9999px;
        padding: 0.4rem 0.9rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #64748b;
        border: 1px solid transparent;
        background: transparent;
    }
    .bc2-tab.is-active {
        background: var(--bc2-primary);
        color: #fff;
    }
    .bc2-step {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: #94a3b8;
    }
    .bc2-step.is-done { color: var(--bc2-ok); }
    .bc2-step.is-current { color: var(--bc2-primary); }
    .bc2-step-dot {
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        border: 2px solid currentColor;
        background: #fff;
    }
    .bc2-step.is-done .bc2-step-dot { background: var(--bc2-ok); color: #fff; border-color: var(--bc2-ok); }
    .bc2-step.is-current .bc2-step-dot { background: var(--bc2-primary); color: #fff; border-color: var(--bc2-primary); }
    .bc2-cell-ok { background: #F0FDF4; color: #166534; font-weight: 600; }
    .bc2-cell-warn { background: #FFFBEB; color: #92400E; font-weight: 600; }
    .bc2-cell-err { background: #FEF2F2; color: #991B1B; font-weight: 600; }
    .bc2-sticky-footer {
        position: sticky;
        bottom: 0;
        z-index: 30;
        margin: 1.5rem -1.5rem -6rem;
        padding: 0.85rem 1.5rem;
        background: rgba(255,255,255,0.96);
        border-top: 1px solid var(--bc2-border);
        backdrop-filter: blur(8px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }
    .bc2-table-wrap {
        max-height: 360px;
        overflow: auto;
        overscroll-behavior: contain;
        border: 1px solid var(--bc2-border);
        border-radius: 0.75rem;
    }
    .bc2-table-wrap table { border-collapse: separate; border-spacing: 0; min-width: 900px; }
    .bc2-table-wrap th, .bc2-table-wrap td {
        border-bottom: 1px solid #F1F5F9;
        border-right: 1px solid #F1F5F9;
        padding: 0.55rem 0.75rem;
        font-size: 0.8125rem;
        text-align: center;
        white-space: nowrap;
    }
    .bc2-table-wrap thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #F8FAFC;
        font-weight: 700;
        color: #475569;
    }
    .bc2-table-wrap .bc2-sticky-col {
        position: sticky;
        left: 0;
        z-index: 3;
        background: #fff;
        text-align: left;
        font-weight: 600;
        box-shadow: 2px 0 0 #F1F5F9;
    }
    .bc2-table-wrap thead .bc2-sticky-col { z-index: 4; background: #F8FAFC; }
    .bc2-op-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.4rem;
    }
    .bc2-op-grid button {
        border: 1px solid var(--bc2-border);
        border-radius: 0.5rem;
        background: #fff;
        padding: 0.45rem 0;
        font-weight: 700;
        color: #334155;
    }
    .bc2-op-grid button:hover { background: var(--bc2-primary-soft); color: var(--bc2-primary); }
    .bc2-accordion-body { display: none; }
    .bc2-accordion.is-open .bc2-accordion-body { display: block; }
    .bc2-accordion.is-open .bc2-acc-chevron { transform: rotate(180deg); }
    @media (max-width: 1100px) {
        .bc2-formula-grid { grid-template-columns: 1fr !important; }
        .bc2-quadro-grid { grid-template-columns: 1fr !important; }
        .bc2-sim-grid { grid-template-columns: 1fr !important; }
    }
</style>

<div class="bc2-root" id="bc2-root">
    <?php include \App\Core\ViewPaths::layoutComponent('trilha.php'); ?>

    <div class="mt-4 mb-5 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white" style="background:var(--bc2-primary)">
                    <i class="fa-solid fa-flask text-lg" aria-hidden="true"></i>
                </span>
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Construtor de Fórmula</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Protótipo visual · etapa 3 — Fórmulas e Colunas</p>
                </div>
            </div>
            <div class="mt-3 inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium text-white" style="background:var(--bc2-primary)">
                <i class="fa-solid fa-print text-xs" aria-hidden="true"></i>
                3EM AHÚ – impressão (do zero)
                <span class="opacity-80">· Sem modelo · 2027</span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= htmlspecialchars($urlVoltar, ENT_QUOTES, 'UTF-8') ?>" class="bc2-btn-ghost">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Voltar ao boletim
            </a>
            <span class="rounded-full bg-amber-50 text-amber-800 border border-amber-200 px-3 py-1 text-xs font-semibold">
                Só layout — sem salvar no motor atual
            </span>
        </div>
    </div>

    <nav class="bc2-card mb-5 px-4 py-3 flex flex-wrap items-center gap-4 sm:gap-6" aria-label="Etapas do assistente">
        <div class="bc2-step is-done"><span class="bc2-step-dot"><i class="fa-solid fa-check" aria-hidden="true"></i></span> Identidade</div>
        <div class="hidden sm:block h-px flex-1 bg-slate-200 max-w-[3rem]"></div>
        <div class="bc2-step is-done"><span class="bc2-step-dot"><i class="fa-solid fa-check" aria-hidden="true"></i></span> Eventos</div>
        <div class="hidden sm:block h-px flex-1 bg-slate-200 max-w-[3rem]"></div>
        <div class="bc2-step is-current"><span class="bc2-step-dot">3</span> Fórmula Colunas</div>
        <div class="hidden sm:block h-px flex-1 bg-slate-200 max-w-[3rem]"></div>
        <div class="bc2-step"><span class="bc2-step-dot">4</span> Revisão</div>
    </nav>

    <!-- ========== 1. QUADRO DE COLUNAS ========== -->
    <section class="bc2-card mb-5 overflow-hidden" aria-labelledby="bc2-quadro-titulo">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 id="bc2-quadro-titulo" class="text-lg font-bold text-slate-900">Quadro de Colunas</h2>
                <p class="text-sm text-slate-500 mt-0.5">Organize as colunas que serão exibidas no boletim e vincule os eventos de cada período.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="bc2-btn-ghost text-xs py-2"><i class="fa-solid fa-calculator" aria-hidden="true"></i> Bloco de cálculo</button>
                <button type="button" class="bc2-btn-ghost text-xs py-2 text-red-600 border-red-200"><i class="fa-solid fa-eraser" aria-hidden="true"></i> Limpar títulos</button>
                <button type="button" class="bc2-btn-ghost text-xs py-2 text-red-700 border-red-300 bg-red-50"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Excluir todos os chips</button>
            </div>
        </header>

        <div class="bc2-quadro-grid grid gap-0" style="grid-template-columns: 280px 1fr;">
            <aside class="border-r border-slate-100 bg-slate-50/60 flex flex-col min-h-[320px] max-h-[420px]">
                <div class="px-4 py-3 border-b border-slate-100 sticky top-0 bg-slate-50 z-10">
                    <p class="text-sm font-bold text-slate-800">Eventos por Trimestre</p>
                    <p class="text-xs text-slate-500 mt-0.5">Abra o período, selecione os eventos e adicione ao quadro.</p>
                </div>
                <div class="bc2-scroll-y flex-1 px-2 py-2 space-y-1" id="bc2-eventos-periodos">
                    <?php
                    $periodosEventos = [
                        '1º Trimestre' => [
                            ['sigla' => 'AP', 'nome' => 'Avaliação Parcial'],
                            ['sigla' => 'FAZ', 'nome' => 'Folha AZ'],
                            ['sigla' => 'PO', 'nome' => 'Prova Objetiva'],
                        ],
                        '2º Trimestre' => [
                            ['sigla' => 'FAZ', 'nome' => 'Folha AZ'],
                            ['sigla' => 'PO', 'nome' => 'Prova Objetiva'],
                            ['sigla' => 'SIMUFPR', 'nome' => 'Simulado'],
                            ['sigla' => 'TESTEAZ', 'nome' => 'Teste AZ'],
                        ],
                        '3º Trimestre' => [
                            ['sigla' => 'AP', 'nome' => 'Avaliação Parcial'],
                            ['sigla' => 'PO', 'nome' => 'Prova Objetiva'],
                        ],
                        'Recuperação — 1º Trim.' => [],
                        'Recuperação — 2º Trim.' => [],
                        'Recuperação — 3º Trim.' => [],
                    ];
                    $i = 0;
                    foreach ($periodosEventos as $periodo => $eventos):
                        $open = $i === 1 ? ' is-open' : '';
                        $i++;
                    ?>
                    <div class="bc2-accordion<?= $open ?> rounded-lg border border-transparent hover:border-slate-200 bg-white/80">
                        <button type="button" class="bc2-acc-toggle w-full flex items-center justify-between gap-2 px-3 py-2.5 text-left">
                            <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="flex items-center gap-2 text-xs text-slate-400">
                                <span class="rounded-md border border-slate-200 px-2 py-0.5 text-slate-600">Abrir</span>
                                <i class="fa-solid fa-chevron-down bc2-acc-chevron transition-transform" aria-hidden="true"></i>
                            </span>
                        </button>
                        <div class="bc2-accordion-body px-3 pb-3 space-y-1.5">
                            <?php if ($eventos === []): ?>
                                <p class="text-xs text-slate-400 px-1">Nenhum evento neste período (mock).</p>
                            <?php else: ?>
                                <?php foreach ($eventos as $ev): ?>
                                <label class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-violet-50 cursor-pointer">
                                    <input type="checkbox" class="rounded border-slate-300 text-violet-600 focus:ring-violet-500" <?= $periodo === '2º Trimestre' ? 'checked' : '' ?>>
                                    <span class="bc2-chip is-evento text-[0.7rem] py-0.5 px-2"><?= htmlspecialchars($ev['sigla'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-xs text-slate-600 truncate"><?= htmlspecialchars($ev['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                                <?php endforeach; ?>
                                <button type="button" class="mt-1 w-full bc2-btn-primary text-xs justify-center py-1.5">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Adicionar ao quadro
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </aside>

            <div class="flex flex-col min-w-0 p-4">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <input type="text" class="form-control flex-1 min-w-[10rem] rounded-lg border-slate-200 text-sm" placeholder="Ex.: 1º Trimestre" value="">
                    <button type="button" class="bc2-btn-primary text-xs"><i class="fa-solid fa-plus" aria-hidden="true"></i> Novo título do período</button>
                    <button type="button" class="bc2-btn-ghost text-xs"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Tirar título</button>
                    <div class="ml-auto flex gap-1">
                        <button type="button" class="bc2-btn-ghost text-xs py-2 px-2.5" id="bc2-quadro-prev" aria-label="Rolar períodos para a esquerda"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" class="bc2-btn-ghost text-xs py-2 px-2.5" id="bc2-quadro-next" aria-label="Rolar períodos para a direita"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                    </div>
                </div>

                <div class="bc2-scroll-x flex gap-3 pb-2" id="bc2-quadro-scroll">
                    <?php
                    $cards = [
                        ['titulo' => '1º Trimestre', 'estado' => 'done', 'chips' => [['Nota','nota'], ['REC1','rec'], ['Média','media']]],
                        ['titulo' => '2º Trimestre', 'estado' => 'active', 'chips' => [['Nota','nota'], ['REC2','rec'], ['Média','media']]],
                        ['titulo' => '3º Trimestre', 'estado' => 'done', 'chips' => [['Nota','nota'], ['REC3','rec'], ['Média','media']]],
                    ];
                    foreach ($cards as $card):
                        $cls = $card['estado'] === 'active' ? 'is-active' : ($card['estado'] === 'done' ? 'is-done' : 'is-warn');
                    ?>
                    <article class="bc2-period-card <?= $cls ?>">
                        <header class="border-b border-slate-100 px-3.5 py-3">
                            <div class="flex items-start gap-2">
                                <i class="fa-solid fa-grip-vertical text-slate-300 cursor-grab mt-1" aria-hidden="true"></i>
                                <div class="min-w-0 flex-1">
                                    <h3 class="bc2-period-title" title="<?= htmlspecialchars($card['titulo'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($card['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <span class="rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5 text-[0.65rem] font-bold">Concluído</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0 pt-0.5">
                                    <button type="button" class="text-xs text-slate-500 hover:text-violet-600 font-semibold">Clonar</button>
                                    <button type="button" class="text-slate-400 hover:text-red-500" aria-label="Excluir período"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                </div>
                            </div>
                        </header>
                        <div class="p-3.5 flex flex-wrap gap-2 min-h-[5rem] content-start">
                            <?php foreach ($card['chips'] as [$label, $tipo]):
                                $sel = $card['estado'] === 'active' && $label === 'Nota' ? ' is-selected' : '';
                            ?>
                            <button type="button" class="bc2-chip is-<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?><?= $sel ?> bc2-col-chip" data-coluna="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" data-periodo="<?= htmlspecialchars($card['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                <i class="fa-solid fa-xmark text-[0.65rem] opacity-40" aria-hidden="true"></i>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <footer class="px-3.5 pb-3.5 text-xs text-slate-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-link" aria-hidden="true"></i>
                            Vincule os eventos do menu da lateral
                        </footer>
                    </article>
                    <?php endforeach; ?>

                    <button type="button" class="bc2-period-card border-dashed flex flex-col items-center justify-center gap-2 text-violet-600 hover:bg-violet-50 min-h-[168px]" style="border-style:dashed">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-violet-100 text-lg font-bold">+</span>
                        <span class="text-sm font-bold">Adicionar período</span>
                        <span class="text-xs text-slate-400 px-4 text-center">Novo bimestre, trimestre ou recuperação</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== 2. CONSTRUTOR DE FÓRMULAS ========== -->
    <section class="bc2-card mb-5 overflow-hidden" aria-labelledby="bc2-formula-titulo">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 id="bc2-formula-titulo" class="text-lg font-bold text-slate-900">Cálculo da Nota</h2>
                <p class="text-sm text-slate-500 mt-0.5">
                    Monte a fórmula da coluna selecionada utilizando avaliações, operadores e funções.
                </p>
                <p class="mt-2 text-sm font-semibold text-violet-700" id="bc2-coluna-contexto">
                    Coluna: <span id="bc2-coluna-nome">Nota</span> — <span id="bc2-coluna-periodo">2º Trimestre</span>
                    <span class="ml-2 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 text-[0.65rem] font-bold">Alterações não salvas</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex gap-1 rounded-full bg-slate-100 p-1" role="tablist" aria-label="Modo do editor">
                    <button type="button" class="bc2-tab is-active bc2-formula-tab" data-tab="editor">Editor de Fórmula</button>
                    <button type="button" class="bc2-tab bc2-formula-tab" data-tab="modelos">Modelos Prontos</button>
                    <button type="button" class="bc2-tab bc2-formula-tab opacity-60" data-tab="ia" title="Integração futura">Assistente IA</button>
                </div>
                <button type="button" class="bc2-btn-ghost text-xs"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Ajuda</button>
            </div>
        </header>

        <div class="grid gap-0 border-b border-slate-100 lg:grid-cols-[240px_1fr]" style="grid-template-columns: 240px 1fr;">
            <!-- Config coluna -->
            <aside class="border-r border-slate-100 p-4 bg-slate-50/50 space-y-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Configuração da coluna</p>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Título da coluna</label>
                    <input type="text" class="form-control w-full rounded-lg border-slate-200 text-sm" value="Nota">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Valor máximo</label>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ([100, 10, 20, 50, 200] as $v): ?>
                        <button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-bold border <?= $v === 100 ? 'text-white border-transparent' : 'bg-white text-slate-600 border-slate-200' ?>" <?= $v === 100 ? 'style="background:var(--bc2-primary)"' : '' ?>><?= $v ?></button>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-1.5 text-[0.7rem] text-slate-500">Regra de Aprovação: escala 0 a 100. Em Resultado Final ≥ 70, use o dobro.</p>
                </div>
                <div class="rounded-xl border border-violet-200 bg-violet-50/60 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-bold text-slate-800">Média do Ciclo</p>
                            <p class="text-[0.7rem] text-slate-500">Define quem entra na recuperação</p>
                        </div>
                        <button type="button" class="relative h-6 w-11 rounded-full" style="background:var(--bc2-primary)" aria-pressed="true" aria-label="Média do Ciclo ligada">
                            <span class="absolute top-0.5 right-0.5 h-5 w-5 rounded-full bg-white shadow"></span>
                        </button>
                    </div>
                    <label class="block text-xs font-semibold text-slate-600 mt-3 mb-1">Esta média vale para qual REC?</label>
                    <select class="form-control w-full rounded-lg border-slate-200 text-sm">
                        <option>REC – 2º Trimestre</option>
                        <option>REC – 1º Trimestre</option>
                        <option>REC – 3º Trimestre</option>
                        <option>REC Final</option>
                    </select>
                </div>
            </aside>

            <div class="p-4 space-y-4 min-w-0">
                <div class="bc2-formula-grid grid gap-3" style="grid-template-columns: 220px 1fr 200px;" data-panel="editor">
                    <!-- Paleta eventos/funções -->
                    <div class="rounded-xl border border-slate-200 p-3 max-h-[340px] bc2-scroll-y">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Cálculo da Nota</p>
                        <div class="flex flex-wrap gap-1 mb-3">
                            <button type="button" class="bc2-tab is-active text-[0.7rem] px-2 py-1">Todos</button>
                            <button type="button" class="bc2-tab text-[0.7rem] px-2 py-1">Trimestres</button>
                            <button type="button" class="bc2-tab text-[0.7rem] px-2 py-1">REC</button>
                        </div>
                        <p class="text-[0.7rem] font-bold text-slate-500 mb-1.5">2º Trimestre</p>
                        <div class="flex flex-wrap gap-1.5 mb-4">
                            <?php foreach (['Média','AP','FAZ','PO','R','SIMUFPR','TESTEAZ'] as $sig): ?>
                            <button type="button" class="bc2-chip is-evento bc2-insert-token" data-token="<?= htmlspecialchars($sig, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sig, ENT_QUOTES, 'UTF-8') ?></button>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-[0.7rem] font-bold text-slate-500 mb-1.5">Funções</p>
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach (['média()','média ponderada()','maior()','menor()','nota substituta()','média c/ rec()','se()'] as $fn): ?>
                            <button type="button" class="bc2-chip is-fn text-[0.7rem] bc2-insert-token" data-token="<?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Editor visual -->
                    <div class="min-w-0 space-y-3">
                        <div class="rounded-xl border-2 border-violet-200 bg-violet-50/30 p-4 min-h-[140px]">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-bold uppercase tracking-wide text-violet-700">Fórmula desta coluna</p>
                                <button type="button" class="text-xs font-semibold text-red-600 hover:underline"><i class="fa-solid fa-trash-can mr-1" aria-hidden="true"></i>Limpar</button>
                            </div>
                            <div class="flex flex-wrap gap-1.5 items-center" id="bc2-formula-chips">
                                <span class="bc2-chip is-op">(</span>
                                <span class="bc2-chip is-evento">FAZ <i class="fa-solid fa-xmark text-[0.65rem] opacity-50" aria-hidden="true"></i></span>
                                <span class="bc2-chip is-op">+</span>
                                <span class="bc2-chip is-evento">PO <i class="fa-solid fa-xmark text-[0.65rem] opacity-50" aria-hidden="true"></i></span>
                                <span class="bc2-chip is-op">+</span>
                                <span class="bc2-chip is-evento">SIMUFPR <i class="fa-solid fa-xmark text-[0.65rem] opacity-50" aria-hidden="true"></i></span>
                                <span class="bc2-chip is-op">+</span>
                                <span class="bc2-chip is-evento">TESTEAZ <i class="fa-solid fa-xmark text-[0.65rem] opacity-50" aria-hidden="true"></i></span>
                                <span class="bc2-chip is-op">)</span>
                                <span class="bc2-chip is-op">÷</span>
                                <span class="bc2-chip is-op">2</span>
                            </div>
                            <p class="mt-3 font-mono text-sm text-slate-700" id="bc2-formula-text">(FAZ + PO + SIMUFPR + TESTEAZ) ÷ 2</p>
                        </div>

                        <div class="flex flex-wrap gap-2 items-center">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 text-xs font-bold">
                                <i class="fa-solid fa-check" aria-hidden="true"></i> Fórmula válida
                            </span>
                            <span class="text-xs text-slate-500"><i class="fa-solid fa-circle-info mr-1" aria-hidden="true"></i> Resultado final: 0 a 100</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 px-2.5 py-1 text-xs font-semibold">
                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                Atenção: você somou 4 avaliações e dividiu por 2. Confirme se é intencional.
                            </span>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-xs font-bold text-slate-600 mb-2">Fórmulas prontas</p>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach (['Média das peças','Maior entre duas','Média c/ substituta','Média c/ recuperação','Provas + substituta + extra','Condição (A / B)'] as $modelo): ?>
                                <button type="button" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-violet-300 hover:text-violet-700"><?= htmlspecialchars($modelo, ENT_QUOTES, 'UTF-8') ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-sm font-bold text-slate-800 mb-2">Prévia do Cálculo</p>
                            <div class="grid sm:grid-cols-2 gap-2 text-sm">
                                <div class="space-y-1 text-slate-600">
                                    <p>FAZ = <strong>4,0</strong></p>
                                    <p>PO = <strong>5,0</strong></p>
                                    <p>SIMUFPR = <strong>6,0</strong></p>
                                    <p>TESTEAZ = <strong>4,0</strong></p>
                                </div>
                                <div class="rounded-lg bg-white border border-slate-200 p-3">
                                    <p class="font-mono text-xs text-slate-500 mb-1">(4 + 5 + 6 + 4) ÷ 2</p>
                                    <p class="text-2xl font-bold text-violet-700">9,5</p>
                                    <p class="text-[0.7rem] text-slate-400 mt-1">Mock — motor real entra depois</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Operadores -->
                    <div class="rounded-xl border border-slate-200 p-3 space-y-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sinais</p>
                        <div class="bc2-op-grid">
                            <?php foreach (['+','−','×','÷','(',')','>','<','≥','≤','=','≠'] as $op): ?>
                            <button type="button" class="bc2-insert-token" data-token="<?= htmlspecialchars($op, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($op, ENT_QUOTES, 'UTF-8') ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Número</label>
                            <div class="flex gap-1.5">
                                <input type="number" class="form-control flex-1 rounded-lg border-slate-200 text-sm" value="2" id="bc2-num-input">
                                <button type="button" class="bc2-btn-primary text-xs px-3" id="bc2-num-insert">Inserir</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hidden rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center" data-panel="modelos">
                    <p class="text-sm font-semibold text-slate-700">Modelos prontos</p>
                    <p class="text-xs text-slate-500 mt-1">Neste protótipo, os atalhos já aparecem no editor. A aba completa reutilizará as fórmulas do assistente atual.</p>
                </div>
                <div class="hidden rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center" data-panel="ia">
                    <p class="text-sm font-semibold text-slate-700">Assistente IA</p>
                    <p class="text-xs text-slate-500 mt-1">Reservado para integração futura — não operacional neste layout.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== 3. EXCEÇÃO POR MATÉRIA ========== -->
    <section class="bc2-card mb-5 px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-700">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold text-slate-900">Exceção na Matéria</h2>
                <p class="text-xs text-slate-500">Configure uma fórmula diferente para disciplinas específicas.</p>
            </div>
            <select class="form-control rounded-lg border-slate-200 text-sm min-w-[12rem]">
                <option value="">Escolher matéria…</option>
                <option>Matemática</option>
                <option>Geografia</option>
                <option>História</option>
            </select>
            <button type="button" class="bc2-btn-primary text-xs"><i class="fa-solid fa-plus" aria-hidden="true"></i> Usar outra fórmula nesta matéria</button>
        </div>
    </section>

    <!-- ========== 4. SIMULAÇÃO ========== -->
    <section class="bc2-card mb-5 overflow-hidden" aria-labelledby="bc2-sim-titulo">
        <header class="border-b border-slate-100 px-5 py-4">
            <h2 id="bc2-sim-titulo" class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-clipboard-list text-violet-600" aria-hidden="true"></i>
                Simulação e Boletim (Prévia)
            </h2>
            <p class="text-sm text-slate-500 mt-0.5">Teste as fórmulas com dados de alunos e visualize como o boletim será apresentado.</p>
        </header>

        <div class="bc2-sim-grid grid gap-0" style="grid-template-columns: 260px 1fr;">
            <aside class="border-r border-slate-100 flex flex-col max-h-[420px]">
                <div class="p-3 border-b border-slate-100">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Simular com aluno</p>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none" aria-hidden="true"></i>
                        <input type="search" id="bc2-busca-aluno" class="form-control w-full rounded-lg border-slate-200 text-sm pl-8 pr-8" placeholder="Buscar por nome…" autocomplete="off" aria-label="Buscar aluno">
                        <button type="button" id="bc2-limpar-busca" class="absolute right-2 top-1/2 -translate-y-1/2 hidden text-slate-400 hover:text-slate-600 px-1" aria-label="Limpar busca">
                            <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="bc2-scroll-y flex-1 p-2 space-y-1" id="bc2-alunos">
                    <?php
                    $alunos = [
                        'Aline Marina',
                        'Amanda Rosa',
                        'Ana Souza',
                        'Bruno Lima',
                        'Carlos Mendes',
                        'João Ferreira',
                        'Maria Oliveira',
                        'Pedro Santos',
                    ];
                    foreach ($alunos as $idx => $nome):
                    ?>
                    <button type="button" class="bc2-aluno w-full text-left rounded-lg px-3 py-2.5 text-sm <?= $idx === 1 ? 'is-selected' : 'text-slate-700 hover:bg-slate-50' ?>" data-aluno="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php endforeach; ?>
                    <p class="bc2-aluno-vazio" id="bc2-aluno-vazio" role="status">Nenhum aluno encontrado para essa busca.</p>
                    <button type="button" class="bc2-aluno bc2-aluno-ficticio w-full text-left rounded-lg px-3 py-2.5 text-sm font-medium text-slate-500 border border-dashed border-slate-200 mt-2" data-aluno="">
                        Simulação sem aluno (dados fictícios)
                    </button>
                </div>
            </aside>

            <div class="p-4 min-w-0 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex gap-1 rounded-full bg-slate-100 p-1" role="tablist">
                        <button type="button" class="bc2-tab is-active bc2-sim-tab" data-sim="boletim">Boletim (Simulação)</button>
                        <button type="button" class="bc2-tab bc2-sim-tab" data-sim="detalhe">Cálculo Detalhado</button>
                        <button type="button" class="bc2-tab bc2-sim-tab" data-sim="eventos">Eventos do Aluno</button>
                    </div>
                    <label class="ml-auto inline-flex items-center gap-2 text-xs text-slate-600 font-medium">
                        <input type="checkbox" class="rounded border-slate-300 text-violet-600"> Mostrar apenas médias
                    </label>
                    <div class="flex items-center gap-1 text-xs font-semibold text-slate-500">
                        <button type="button" class="bc2-btn-ghost py-1 px-2">−</button>
                        <span>100%</span>
                        <button type="button" class="bc2-btn-ghost py-1 px-2">+</button>
                        <button type="button" class="bc2-btn-ghost py-1 px-2" aria-label="Tela cheia"><i class="fa-solid fa-expand" aria-hidden="true"></i></button>
                    </div>
                </div>

                <div data-sim-panel="boletim">
                    <div class="bc2-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th class="bc2-sticky-col" rowspan="2">Matérias</th>
                                    <th colspan="3">1º TRIMESTRE</th>
                                    <th colspan="3">2º TRIMESTRE</th>
                                    <th colspan="3">3º TRIMESTRE</th>
                                    <th rowspan="2" style="background:#EDE9FE;color:#5B35E6">Resultado</th>
                                </tr>
                                <tr>
                                    <?php for ($t = 0; $t < 3; $t++): ?>
                                    <th>Nota</th><th>REC</th><th>Média</th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $linhas = [
                                    ['Geografia', '3,0', '—', '5,0', '5,0', '—', '8,0', '7,0', '—', '7,5', '7,3', 'ok'],
                                    ['Biologia', '4,0', '—', '6,0', '6,0', '—', '9,0', '8,0', '—', '8,5', '8,0', 'ok'],
                                    ['História', '2,0', '—', '4,0', '4,0', '—', '5,0', '3,0', '—', '4,0', '4,0', 'err'],
                                    ['Ed. Física', '8,0', '—', '9,0', '9,0', '—', '10,0', '9,0', '—', '9,5', '9,0', 'ok'],
                                    ['Matemática', '4,0', '—', '5,0', '6,0', '—', '9,0', '5,0', '—', '6,7', '6,7', 'warn'],
                                ];
                                foreach ($linhas as $row):
                                    $resCls = $row[11] === 'ok' ? 'bc2-cell-ok' : ($row[11] === 'warn' ? 'bc2-cell-warn' : 'bc2-cell-err');
                                ?>
                                <tr>
                                    <td class="bc2-sticky-col"><?= htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php for ($c = 1; $c <= 9; $c++):
                                        $isMedia = in_array($c, [3, 6, 9], true);
                                        $val = $row[$c];
                                        $cls = '';
                                        if ($isMedia && is_numeric(str_replace(',', '.', $val))) {
                                            $n = (float) str_replace(',', '.', $val);
                                            $cls = $n >= 7 ? 'bc2-cell-ok' : ($n >= 4 ? 'bc2-cell-warn' : 'bc2-cell-err');
                                        }
                                    ?>
                                    <td class="<?= $cls ?>"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php endfor; ?>
                                    <td class="<?= $resCls ?>"><?= htmlspecialchars($row[10], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-500">
                        <span><span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 mr-1"></span> Aprovado (faixas da regra da escola)</span>
                        <span><span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-400 mr-1"></span> Recuperação / atenção</span>
                        <span><span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500 mr-1"></span> Não aprovado</span>
                        <span class="text-slate-400">Cores do mock — limiares virão da regra acadêmica.</span>
                    </div>
                </div>

                <div class="hidden rounded-xl border border-slate-200 p-4 text-sm text-slate-600 space-y-2" data-sim-panel="detalhe">
                    <p class="font-bold text-slate-800">Cálculo detalhado — Amanda Rosa · Geografia · 2º Trimestre</p>
                    <p class="font-mono text-xs bg-slate-50 rounded-lg p-3">(FAZ 4,0 + PO 5,0 + SIMUFPR 6,0 + TESTEAZ 4,0) ÷ 2 = <strong>9,5</strong></p>
                    <p class="text-xs text-slate-400">Placeholder — depois usa o mesmo motor do boletim.</p>
                </div>
                <div class="hidden rounded-xl border border-slate-200 p-4 text-sm text-slate-600" data-sim-panel="eventos">
                    <p class="font-bold text-slate-800 mb-2">Eventos do aluno (mock)</p>
                    <ul class="space-y-1 text-sm">
                        <li>FAZ — 4,0 · 2º Trimestre</li>
                        <li>PO — 5,0 · 2º Trimestre</li>
                        <li>SIMUFPR — 6,0 · 2º Trimestre</li>
                        <li>TESTEAZ — 4,0 · 2º Trimestre</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <div class="bc2-sticky-footer">
        <a href="<?= htmlspecialchars($urlVoltar, ENT_QUOTES, 'UTF-8') ?>" class="bc2-btn-ghost">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar
        </a>
        <div class="flex flex-wrap gap-2 justify-end">
            <button type="button" class="bc2-btn-ghost" disabled title="Protótipo — sem persistência">Aplicar na configuração</button>
            <button type="button" class="bc2-btn-primary" disabled title="Protótipo — sem persistência">
                Salvar e continuar <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('bc2-root');
    if (!root) return;

    root.querySelectorAll('.bc2-acc-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var acc = btn.closest('.bc2-accordion');
            if (!acc) return;
            acc.classList.toggle('is-open');
        });
    });

    var scrollEl = document.getElementById('bc2-quadro-scroll');
    var prev = document.getElementById('bc2-quadro-prev');
    var next = document.getElementById('bc2-quadro-next');
    if (scrollEl && prev && next) {
        prev.addEventListener('click', function () { scrollEl.scrollBy({ left: -320, behavior: 'smooth' }); });
        next.addEventListener('click', function () { scrollEl.scrollBy({ left: 320, behavior: 'smooth' }); });
    }

    var nomeCol = document.getElementById('bc2-coluna-nome');
    var periodoCol = document.getElementById('bc2-coluna-periodo');
    root.querySelectorAll('.bc2-col-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            root.querySelectorAll('.bc2-col-chip').forEach(function (c) { c.classList.remove('is-selected'); });
            chip.classList.add('is-selected');
            root.querySelectorAll('.bc2-period-card').forEach(function (card) { card.classList.remove('is-active'); });
            var card = chip.closest('.bc2-period-card');
            if (card) card.classList.add('is-active');
            if (nomeCol) nomeCol.textContent = chip.getAttribute('data-coluna') || 'Nota';
            if (periodoCol) periodoCol.textContent = chip.getAttribute('data-periodo') || '';
        });
    });

    root.querySelectorAll('.bc2-formula-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var key = tab.getAttribute('data-tab');
            root.querySelectorAll('.bc2-formula-tab').forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            ['editor', 'modelos', 'ia'].forEach(function (nome) {
                var panel = root.querySelector('[data-panel="' + nome + '"]');
                if (panel) panel.classList.toggle('hidden', nome !== key);
            });
        });
    });

    root.querySelectorAll('.bc2-sim-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var key = tab.getAttribute('data-sim');
            root.querySelectorAll('.bc2-sim-tab').forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            root.querySelectorAll('[data-sim-panel]').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-sim-panel') !== key);
            });
        });
    });

    function marcarAluno(btn) {
        root.querySelectorAll('.bc2-aluno').forEach(function (b) {
            b.classList.remove('is-selected');
            if (!b.classList.contains('bc2-aluno-ficticio')) {
                b.classList.add('text-slate-700');
            }
        });
        btn.classList.add('is-selected');
        btn.classList.remove('text-slate-700');
        var detalhe = root.querySelector('[data-sim-panel="detalhe"] .font-bold');
        var nome = (btn.getAttribute('data-aluno') || '').trim();
        if (detalhe) {
            detalhe.textContent = nome
                ? ('Cálculo detalhado — ' + nome + ' · Geografia · 2º Trimestre')
                : 'Cálculo detalhado — dados fictícios · Geografia · 2º Trimestre';
        }
    }

    root.querySelectorAll('.bc2-aluno').forEach(function (btn) {
        btn.addEventListener('click', function () { marcarAluno(btn); });
    });

    var busca = document.getElementById('bc2-busca-aluno');
    var limparBusca = document.getElementById('bc2-limpar-busca');
    var vazioAluno = document.getElementById('bc2-aluno-vazio');

    function normalizarTexto(t) {
        return String(t || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function filtrarAlunos() {
        var q = normalizarTexto(busca ? busca.value : '');
        if (limparBusca) limparBusca.classList.toggle('hidden', q === '');
        var visiveis = 0;
        root.querySelectorAll('.bc2-aluno:not(.bc2-aluno-ficticio)').forEach(function (btn) {
            var nome = normalizarTexto(btn.getAttribute('data-aluno') || btn.textContent);
            var ok = q === '' || nome.indexOf(q) >= 0;
            btn.classList.toggle('hidden', !ok);
            if (ok) visiveis += 1;
        });
        if (vazioAluno) vazioAluno.classList.toggle('is-visible', visiveis === 0);
    }

    if (busca) {
        busca.addEventListener('input', filtrarAlunos);
        busca.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                busca.value = '';
                filtrarAlunos();
                busca.blur();
            }
        });
    }
    if (limparBusca && busca) {
        limparBusca.addEventListener('click', function () {
            busca.value = '';
            filtrarAlunos();
            busca.focus();
        });
    }
})();
</script>
