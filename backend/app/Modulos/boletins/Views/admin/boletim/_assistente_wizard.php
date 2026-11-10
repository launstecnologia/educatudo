<?php
/**
 * Assistente guiado (wizard) — Configuração de Boletins.
 * Variáveis: $csrfToken, $boletimAssistenteDisponivel, $selected_regra_id
 */
$boletimAssistenteDisponivel = !empty($boletimAssistenteDisponivel);
$assistenteRegraId = (int) ($selected_regra_id ?? $selectedRegraId ?? 0);
$boletimAssistentePageMode = !empty($boletimAssistentePageMode);
if (empty($boletimAssistenteReturnUrl)) {
    $boletimAssistenteReturnUrl = URL . '/admin/boletim-configuracao' . ($assistenteRegraId > 0 ? ('?regra_id=' . $assistenteRegraId) : '?novo=1');
}
$boletimAssistenteDadosIniciais = [
    'estado' => is_array($boletim_assistente_estado_inicial ?? null) ? $boletim_assistente_estado_inicial : null,
    'catalogo' => is_array($boletim_assistente_catalogo_inicial ?? null) ? $boletim_assistente_catalogo_inicial : null,
    'rascunho' => is_array($boletim_assistente_rascunho_inicial ?? null) ? $boletim_assistente_rascunho_inicial : null,
    'resumo' => isset($boletim_assistente_resumo_inicial) ? (string) $boletim_assistente_resumo_inicial : null,
    'erros' => is_array($boletim_assistente_erros_iniciais ?? null) ? $boletim_assistente_erros_iniciais : [],
    'formulas_disponiveis' => is_array($boletim_assistente_formulas_iniciais ?? null) ? $boletim_assistente_formulas_iniciais : [],
    'preview' => $boletim_assistente_preview_inicial ?? null,
    'aviso' => isset($boletim_assistente_aviso_inicial) ? (string) $boletim_assistente_aviso_inicial : null,
];
$ui = \App\Core\ViewPaths::adminPartial('ui');
$boletimConfigVersoes = is_array($boletim_config_versoes ?? null) ? $boletim_config_versoes : [];
$boletimWizardSteps = [
    ['label' => 'Começar', 'sub' => 'Origem'],
    ['label' => 'Identidade', 'sub' => 'Evento'],
    ['label' => 'Tipo de Notas', 'sub' => 'Peças'],
    ['label' => 'Agrupar', 'sub' => 'Linha'],
    ['label' => 'Fórmula', 'sub' => 'Colunas'],
    ['label' => 'Revisão', 'sub' => 'Confirmar'],
];
?>
<div id="boletim-assistente-root" class="<?= $boletimAssistentePageMode ? 'w-full' : 'fixed bottom-4 right-4 z-[10000] flex flex-col items-end gap-2' ?>"
     data-disponivel="<?= $boletimAssistenteDisponivel ? '1' : '0' ?>"
     data-page-mode="<?= $boletimAssistentePageMode ? '1' : '0' ?>"
     data-return-url="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>"
     data-url-mensagem="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/mensagem-stream', ENT_QUOTES, 'UTF-8') ?>"
     data-url-ferramenta="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/ferramenta', ENT_QUOTES, 'UTF-8') ?>"
     data-url-wizard-inicio="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/wizard/inicio', ENT_QUOTES, 'UTF-8') ?>"
     data-url-wizard-montar="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/wizard/montar', ENT_QUOTES, 'UTF-8') ?>"
     data-url-salvar="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/salvar', ENT_QUOTES, 'UTF-8') ?>"
     data-csrf="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>"
     data-regra-id="<?= $assistenteRegraId ?>"
     data-boletim-id="<?= (int) ($boletimId ?? $boletim_id ?? 0) ?>"
     data-ano-letivo="<?= (int) ($anoAssistente ?? $ano_letivo_assistente ?? 0) ?>"
     data-bimestre="<?= (int) ($bimestreAssistente ?? $bimestre_assistente ?? 0) ?>"
     data-retorno-fluxo="<?= htmlspecialchars(!empty($voltarGerarAvaliacoes) ? 'gerar-avaliacoes' : (!empty($voltarPeriodos) ? 'periodos' : (!empty($voltarEvento) ? 'evento' : (!empty($voltarBoletimLayout) || !empty($quadroImpressao) || !empty($quadroComposto) || !empty($quadroSantaMaria) ? 'boletim-layout' : (!empty($voltarBoletins) ? 'boletins' : '')))), ENT_QUOTES, 'UTF-8') ?>"
     data-layout-boletim-id="<?= (int) ($layout_boletim_id ?? 0) ?>"
     data-lista="<?= htmlspecialchars(json_encode(is_array($navegacaoLista ?? null) ? $navegacaoLista : [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
     data-continuar="<?= !empty($continuarRascunhoAssistente) ? '1' : '0' ?>"
     data-url-quadros="<?= htmlspecialchars(URL . '/admin/quadros-notas', ENT_QUOTES, 'UTF-8') ?>"
     data-pode-ver-quadro="<?= !empty($podeVerQuadro) ? '1' : '0' ?>"
     data-pode-cadastrar-quadro="<?= !empty($podeCadastrarQuadro) ? '1' : '0' ?>"
     data-config-versoes="<?= htmlspecialchars(json_encode($boletimConfigVersoes, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">

    <!-- Overlay wizard -->
    <div id="boletim-wizard-overlay" class="<?= $boletimAssistentePageMode ? 'block' : 'hidden fixed inset-0 z-[10000] bg-slate-900/50 backdrop-blur-[1px] p-2 sm:p-5' ?>">
        <div class="<?= $boletimAssistentePageMode ? 'w-full space-y-6' : 'mx-auto w-full max-w-[min(1180px,calc(100vw-1rem))] h-[min(94vh,900px)] bg-white rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden' ?>">
            <?php if ($boletimAssistentePageMode): ?>
            <?php if (!empty($boletimAssistenteDadosIniciais['aviso'])): ?>
            <div class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                <?= htmlspecialchars((string) $boletimAssistenteDadosIniciais['aviso'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            <?php
            $ui_wizard_steps = $boletimWizardSteps;
            $ui_wizard_current = 1;
            $ui_wizard_nav_id = 'boletim-wizard-steps';
            include $ui . '/wizard_steps.php';
            ?>
            <div class="bg-white rounded-xl shadow-lg p-6 w-full min-h-[calc(100vh-20rem)] flex flex-col">
                <pre id="boletim-wizard-resumo" class="hidden"></pre>
                <div id="boletim-wizard-erros" class="hidden mb-4 p-4 rounded-lg border border-red-200 bg-red-50 text-red-800 text-sm"></div>
                <div id="boletim-wizard-body" class="flex-1 min-h-[24rem] space-y-4"></div>
                <div class="flex items-center justify-between pt-6 mt-6 border-t border-gray-100">
                    <button type="button" id="boletim-wizard-voltar" class="wizard-step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
                    </button>
                    <div class="flex gap-2">
                        <button type="button" id="boletim-wizard-aplicar" class="px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-semibold transition-colors">Aplicar na configuração</button>
                        <button type="button" id="boletim-wizard-gerar-aluno" class="hidden px-4 py-2.5 border border-indigo-200 rounded-lg text-indigo-700 hover:bg-indigo-50 text-sm font-semibold transition-colors">Gerar só este aluno</button>
                        <button type="button" id="boletim-wizard-avancar" class="wizard-step-next btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                            Continuar <i class="fa-solid fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="px-4 sm:px-5 py-3 border-b border-gray-100 bg-slate-50 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">Evento de Notas</h2>
                    <p id="boletim-wizard-subtitulo" class="text-xs text-gray-500 truncate">Adicione blocos de cálculo, dê um nome, arraste a ordem e salve</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="boletim-wizard-copiar-receita" class="text-xs font-medium text-indigo-700 hover:bg-indigo-50 px-2 py-1.5 rounded-md">Copiar receita</button>
                    <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>" id="boletim-wizard-fechar" class="text-gray-400 hover:text-gray-700 p-1.5" aria-label="Fechar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                </div>
            </div>

            <div class="flex-1 min-h-0 flex flex-col">
                <div class="flex flex-col min-h-0 flex-1">
                    <nav id="boletim-wizard-steps" class="px-4 pt-3 pb-2 flex flex-wrap gap-1.5 border-b border-gray-50 bg-white"></nav>
                    <pre id="boletim-wizard-resumo" class="hidden"></pre>
                    <div id="boletim-wizard-erros" class="hidden mx-4 mt-2 p-3 rounded-lg border border-red-200 bg-red-50 text-red-800 text-sm"></div>
                    <div id="boletim-wizard-body" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4"></div>
                    <div class="px-4 py-3 border-t border-gray-100 bg-white flex flex-wrap items-center justify-between gap-2">
                        <button type="button" id="boletim-wizard-voltar" class="px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-40">Voltar</button>
                        <div class="flex gap-2">
                            <button type="button" id="boletim-wizard-aplicar" class="px-3 py-2 text-sm rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50"><?= $boletimAssistentePageMode ? 'Aplicar na configuração' : 'Aplicar no formulário' ?></button>
                            <button type="button" id="boletim-wizard-gerar-aluno" class="hidden px-3 py-2 text-sm font-semibold rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50">Gerar só este aluno</button>
                            <button type="button" id="boletim-wizard-avancar" class="px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Continuar</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <form id="form-restaurar-config-versao" method="POST" action="<?= URL ?>/admin/boletim-configuracao/restaurar-config-versao" class="hidden" aria-hidden="true">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="regra_id" value="<?= $assistenteRegraId ?>">
        <input type="hidden" name="versao_id" id="restaurar-config-versao-id" value="">
    </form>

    <?php if (!$boletimAssistentePageMode): ?>
    <button type="button" id="boletim-assistente-toggle"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full shadow-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        Configurar Notas
    </button>
    <?php endif; ?>
</div>

<script type="application/json" id="boletim-assistente-dados-iniciais"><?= json_encode($boletimAssistenteDadosIniciais, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<style>
@keyframes boletim-assistente-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
    40% { transform: translateY(-4px); opacity: 1; }
}
.boletim-assistente-dot {
    width: 6px; height: 6px; border-radius: 9999px; background: #6366f1; display: inline-block;
    animation: boletim-assistente-bounce 1.2s infinite ease-in-out;
}
.boletim-assistente-dot:nth-child(2) { animation-delay: 0.15s; }
.boletim-assistente-dot:nth-child(3) { animation-delay: 0.3s; }
#boletim-wizard-steps .step-nav-btn { flex: 0 0 auto; }
#boletim-wizard-steps .step-nav-btn > span { min-width: max-content; }
#boletim-wizard-steps .step-nav-btn .font-semibold,
#boletim-wizard-steps .step-nav-btn .text-xs { white-space: nowrap; }
.bw-step-btn { font-size: 11px; padding: 4px 10px; border-radius: 9999px; border: 1px solid #e5e7eb; color: #6b7280; background: #fff; }
.bw-step-btn.active { border-color: #6366f1; background: #eef2ff; color: #4338ca; font-weight: 600; }
.bw-step-btn.done { border-color: #a7f3d0; background: #ecfdf5; color: #047857; }
.bw-card { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 0.875rem; cursor: pointer; background: #fff; transition: border-color .15s, box-shadow .15s; }
.bw-card:hover { border-color: #a5b4fc; }
.bw-card.selected { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,.15); background: #f8fafc; }
.bw-chip { display: inline-flex; align-items: center; gap: 4px; padding: 6px 10px; border-radius: 9999px; border: 1px solid; font-size: 13px; line-height: 1.2; user-select: none; cursor: grab; }
.bw-chip:active { cursor: grabbing; }
.bw-chip-peca { background: #eef2ff; border-color: #c7d2fe; color: #3730a3; font-weight: 600; }
.bw-chip-calc { background: #fef3c7; border-color: #fcd34d; color: #92400e; font-weight: 700; }
.bw-chip-op { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; min-width: 2rem; justify-content: center; }
.bw-chip-fn { background: #fef3c7; border-color: #fcd34d; color: #92400e; font-weight: 600; }
.bw-chip-num { background: #ecfdf5; border-color: #a7f3d0; color: #047857; font-weight: 600; }
.bw-palette .bw-chip { cursor: pointer; }
.bw-palette-grupos { display: flex; flex-direction: column; gap: 0.55rem; }
.bw-palette-grupo { border: 1px solid #e2e8f0; border-radius: 0.65rem; background: #fff; padding: 0.4rem 0.55rem 0.5rem; }
.bw-palette-grupo.calc { border-color: #fcd34d; background: #fffbeb; }
.bw-palette-grupo-titulo { font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #64748b; margin-bottom: 0.35rem; }
.bw-palette-grupo.calc .bw-palette-grupo-titulo { color: #92400e; }
.bw-palette-grupo-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
/* Editor de fórmula: peças à esquerda, montagem fixa à direita */
.bw-formula-workspace {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
    gap: 0.85rem;
    align-items: start;
}
@media (max-width: 900px) {
    .bw-formula-workspace { grid-template-columns: 1fr; }
    .bw-formula-montagem { order: -1; position: static !important; top: auto !important; }
}
.bw-formula-pecas-col,
.bw-formula-montagem {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    background: #fff;
    padding: 0.7rem 0.75rem 0.85rem;
    min-width: 0;
}
.bw-formula-montagem {
    position: sticky;
    top: 0.5rem;
    border-color: #fcd34d;
    background: #fffbeb;
    box-shadow: 0 2px 10px rgba(245, 158, 11, 0.08);
    z-index: 2;
}
.bw-palette-shell { display: flex; flex-direction: column; gap: 0.55rem; }
.bw-palette-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    padding-bottom: 0.15rem;
}
.bw-palette-tab {
    appearance: none;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.2;
    padding: 0.35rem 0.6rem;
    border-radius: 999px;
    cursor: pointer;
    transition: background .12s, border-color .12s, color .12s;
}
.bw-palette-tab:hover { border-color: #a5b4fc; background: #eef2ff; color: #3730a3; }
.bw-palette-tab.ativo { border-color: #4f46e5; background: #4f46e5; color: #fff; }
.bw-palette-tab.calc { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
.bw-palette-tab.calc.ativo { border-color: #d97706; background: #d97706; color: #fff; }
.bw-palette-painel {
    max-height: 18rem;
    overflow: auto;
    padding-right: 0.15rem;
}
.bw-palette-painel .bw-palette-grupos { gap: 0.45rem; }
.bw-palette .bw-pal-chip { cursor: pointer; }
.bw-canvas { min-height: 96px; border: 2px dashed #c7d2fe; border-radius: 0.75rem; padding: 10px; display: flex; flex-wrap: wrap; align-content: flex-start; gap: 6px; background: #f8fafc; }
.bw-canvas.drag-over { border-color: #6366f1; background: #eef2ff; }
.bw-chip-x { margin-left: 2px; color: #64748b; font-size: 14px; line-height: 1; padding: 0 2px; }
.bw-chip-x:hover { color: #b91c1c; }
.bw-canvas-ph { width: 100%; font-size: 12px; color: #94a3b8; padding: 18px 8px; text-align: center; }
.bw-preview-table { border-collapse: collapse; width: 100%; font-size: 11px; text-align: center; }
.bw-preview-table thead th { position: sticky; top: 0; z-index: 2; }
.bw-preview-table thead tr:nth-child(2) th { top: 28px; z-index: 1; }
.bw-preview-table th { background: var(--sidebar-bg-color, #1e3a5f); color: var(--sidebar-text-color, #fff); border: 1px solid color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 75%, #000); padding: 4px 6px; font-weight: 600; }
.bw-preview-table th.sub { background: color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 82%, #fff); color: var(--sidebar-text-color, #fff); }
.bw-preview-table td { border: 1px solid #d1d5db; padding: 3px 5px; }
.bw-preview-table td.mat { text-align: left; font-weight: 600; color: #111827; white-space: nowrap; }
.bw-preview-table tr:nth-child(even) td { background: #f3f4f6; }
.bw-preview-table.boletim th { background: var(--sidebar-bg-color, #1e3a5f); color: var(--sidebar-text-color, #fff); border-color: color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 75%, #000); text-transform: uppercase; letter-spacing: .02em; }
.bw-preview-table.boletim th.sub { background: color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 18%, #fff); color: var(--sidebar-bg-color, #1e3a5f); text-transform: none; font-weight: 700; }
.bw-preview-table.boletim th.mat { text-transform: none; letter-spacing: 0; }
.bw-preview-table.boletim tr:nth-child(even) td { background: #f8fafc; }
.bw-preview-table.boletim td.ok { color: #047857; font-weight: 700; }
.bw-preview-table.boletim td.nok { color: #b91c1c; font-weight: 700; }
/* Separação visual entre títulos de período (I BIM / II BIM / semestre…) */
.bw-preview-table.boletim th.grupo-sep,
.bw-preview-table.boletim th.sub.grupo-sep,
.bw-preview-table.boletim td.grupo-sep {
    border-left: 3px solid color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 55%, #64748b);
}
.bw-preview-table.boletim th.grupo-sep {
    border-left-color: color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 40%, #fff);
}
.bw-builder { display: grid; grid-template-columns: minmax(14rem, 17rem) minmax(0, 1fr); gap: 0.75rem; align-items: stretch; }
@media (max-width: 900px) { .bw-builder { grid-template-columns: 1fr; } }
.bw-catalogo { border: 1px solid #e2e8f0; border-radius: 0.75rem; background: #fff; min-height: 16rem; max-height: 28rem; overflow: auto; display: flex; flex-direction: column; }
.bw-catalogo-topo { position: sticky; top: 0; z-index: 1; padding: 0.65rem 0.75rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
.bw-catalogo-corpo { padding: 0.5rem 0.65rem 0.75rem; display: flex; flex-direction: column; gap: 0.5rem; }
.bw-catalogo-grupo { border: 1px solid #e2e8f0; border-radius: 0.65rem; background: #f8fafc; overflow: hidden; }
.bw-catalogo-grupo > summary { list-style: none; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.55rem 0.65rem; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #334155; background: #f1f5f9; user-select: none; }
.bw-catalogo-grupo > summary::-webkit-details-marker { display: none; }
.bw-catalogo-grupo > summary:hover { background: #e2e8f0; }
.bw-catalogo-grupo > summary .bw-cat-abrir { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 0.25rem; font-size: 10px; font-weight: 800; letter-spacing: .02em; text-transform: none; color: #3730a3; background: #e0e7ff; border: 1px solid #a5b4fc; border-radius: 999px; padding: 0.2rem 0.55rem; }
.bw-catalogo-grupo > summary .bw-cat-abrir::after { content: '▼'; font-size: 8px; line-height: 1; }
.bw-catalogo-grupo[open] > summary { background: #eef2ff; border-bottom: 1px solid #e2e8f0; }
.bw-catalogo-grupo[open] > summary .bw-cat-abrir { background: #4338ca; border-color: #4338ca; color: #fff; }
.bw-catalogo-grupo[open] > summary .bw-cat-abrir::after { content: '▲'; }
.bw-catalogo-grupo[open] > summary .bw-cat-abrir-txt::before { content: 'Fechar'; }
.bw-catalogo-grupo:not([open]) > summary .bw-cat-abrir-txt::before { content: 'Abrir'; }
.bw-catalogo-add-todos { width: 100%; font-size: 10px; font-weight: 700; line-height: 1.2; padding: 0.35rem 0.55rem; border-radius: 0.45rem; border: 1px dashed #a5b4fc; background: #eef2ff; color: #3730a3; cursor: pointer; text-align: center; }
.bw-catalogo-add-todos:hover { background: #e0e7ff; border-style: solid; }
.bw-catalogo-add-todos:disabled { opacity: .55; cursor: default; border-style: solid; background: #f8fafc; color: #64748b; }
.bw-catalogo-itens { display: flex; flex-direction: column; gap: 0.35rem; padding: 0.45rem; }
.bw-catalogo-item { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; width: 100%; text-align: left; padding: 0.45rem 0.55rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: #fff; color: #0f172a; font-size: 12px; cursor: grab; transition: border-color .12s, background .12s, box-shadow .12s; touch-action: none; }
.bw-catalogo-item:hover { border-color: #a5b4fc; background: #eef2ff; box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.12); }
.bw-catalogo-item.no-quadro { border-color: #c7d2fe; background: #eef2ff; }
.bw-catalogo-item.dragging { opacity: 0.45; cursor: grabbing; }
.bw-linha-caixa.soltar-catalogo { border-color: #4f46e5 !important; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25); background: #eef2ff; }
.bw-linha-caixa.soltar-catalogo .bw-linha-cols { border-color: #6366f1; background: #e0e7ff; }
.bw-linha-caixa.selecionado { border-color: #4f46e5 !important; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.28); background: #eef2ff; }
.bw-linha-caixa.selecionado .bw-linha-cols { border-color: #6366f1; background: #e0e7ff; }
.bw-linha-dica { margin: 0; font-size: 10px; line-height: 1.3; color: #64748b; font-weight: 600; }
.bw-catalogo-item .bw-cat-nome { flex: 1; min-width: 0; font-weight: 600; line-height: 1.25; }
.bw-catalogo-item .bw-cat-sigla { flex: 0 0 auto; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #3730a3; background: #e0e7ff; border-radius: 999px; padding: 0.15rem 0.45rem; }
.bw-quadro { border: 1px solid #c7d2fe; border-radius: 0.75rem; background: #f8fafc; min-height: 16rem; display: flex; flex-direction: column; }
.bw-quadro-topo { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.65rem 0.75rem; border-bottom: 1px solid #e2e8f0; background: #fff; border-radius: 0.75rem 0.75rem 0 0; }
.bw-quadro-acoes { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.bw-quadro-corpo { padding: 0.65rem 0.75rem 0.85rem; flex: 1; }
.bw-quadro-vazio { display: flex; align-items: center; justify-content: center; min-height: 8rem; padding: 1.25rem; border: 1px dashed #c7d2fe; border-radius: 0.65rem; background: #fff; color: #64748b; font-size: 13px; text-align: center; }
.bw-col-chip { display: inline-flex; align-items: center; gap: 3px; padding: 4px 8px; border-radius: 0.45rem; border: 1px solid #c7d2fe; background: #eef2ff; color: #3730a3; font-size: 11px; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; cursor: grab; user-select: none; touch-action: none; }
.bw-col-chip.travada, .bw-col-chip.fixa { cursor: default; touch-action: auto; }
.bw-col-chip.travada { background: #f1f5f9; border-color: #cbd5e1; color: #475569; }
.bw-col-chip.calc { background: #fef3c7; border-color: #fcd34d; color: #92400e; text-transform: none; letter-spacing: 0; }
.bw-col-chip.aberta { box-shadow: 0 0 0 2px #f59e0b; }
.bw-col-chip.marcada { box-shadow: 0 0 0 2px #4f46e5; background: #e0e7ff; }
.bw-linha-caixa { display: flex; flex-direction: column; gap: 8px; padding: 10px 12px 12px; border: 2.5px solid #818cf8; border-radius: 0.85rem; background: #fff; min-width: 9.5rem; box-shadow: 0 2px 8px rgba(79, 70, 229, 0.10); transition: border-color .2s ease, box-shadow .2s ease, background .2s ease; }
.bw-linha-caixa.confirmado { border-color: #22c55e; border-width: 2.5px; background: #f0fdf4; box-shadow: 0 2px 8px rgba(22, 163, 74, 0.12); }
.bw-linha-cols { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; align-content: flex-start; min-height: 3.25rem; min-width: 5rem; padding: 0.55rem; border: 1.5px dashed #a5b4fc; border-radius: 0.55rem; background: #f8fafc; }
.bw-linha-caixa.confirmado .bw-linha-cols { border-color: #86efac; border-style: solid; background: #ecfdf5; }
.bw-linha-cols:empty::before { content: 'Arraste eventos da esquerda para cá'; color: #94a3b8; font-size: 11px; font-weight: 600; }
.bw-linha-topo { display: flex; align-items: center; gap: 4px; min-width: 0; }
.bw-linha-grip { cursor: grab; opacity: 0.55; letter-spacing: -1px; font-size: 13px; line-height: 1; padding: 2px 2px 2px 0; color: #3730a3; touch-action: none; user-select: none; }
.bw-linha-rotulo { font-size: 12px; font-weight: 700; color: #312e81; line-height: 1.2; outline: none; border-radius: 0.35rem; padding: 2px 6px; min-width: 3rem; flex: 1; cursor: text; }
.bw-linha-caixa.confirmado .bw-linha-rotulo { color: #166534; }
.bw-linha-rotulo:hover, .bw-linha-rotulo:focus { background: #eef2ff; box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.35); }
.bw-linha-ok { margin-left: 2px; flex-shrink: 0; font-size: 11px; font-weight: 700; line-height: 1.2; padding: 3px 8px; border-radius: 999px; border: 1px solid #86efac; background: #fff; color: #15803d; cursor: pointer; transition: background .15s ease, color .15s ease, border-color .15s ease; }
.bw-linha-ok:hover { background: #dcfce7; }
.bw-linha-ok.ativo { background: #16a34a; border-color: #16a34a; color: #fff; cursor: default; }
.bw-linha-clonar { margin-left: 2px; flex-shrink: 0; font-size: 11px; font-weight: 700; line-height: 1.2; padding: 3px 8px; border-radius: 999px; border: 1px solid #a5b4fc; background: #fff; color: #3730a3; cursor: pointer; }
.bw-linha-clonar:hover { background: #eef2ff; }
.bw-linha-x { margin-left: 2px; color: #4338ca; font-size: 14px; line-height: 1; padding: 0 3px; opacity: 0.55; cursor: pointer; border: 0; background: transparent; }
.bw-linha-x:hover { opacity: 1; color: #b91c1c; }
.bw-clone-painel { display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.35rem; padding: 0.55rem 0.6rem; border: 1px solid #c7d2fe; border-radius: 0.55rem; background: #eef2ff; }
.bw-clone-painel label { display: flex; flex-direction: column; gap: 0.2rem; font-size: 10px; font-weight: 700; color: #4338ca; text-transform: uppercase; letter-spacing: .03em; }
.bw-clone-painel input, .bw-clone-painel select { font-size: 12px; font-weight: 600; color: #0f172a; text-transform: none; letter-spacing: 0; border: 1px solid #c7d2fe; border-radius: 0.4rem; padding: 0.35rem 0.5rem; background: #fff; }
.bw-clone-acoes { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.bw-clone-acoes button { font-size: 11px; font-weight: 700; padding: 0.3rem 0.65rem; border-radius: 0.4rem; cursor: pointer; }
.bw-clone-ok { border: 0; background: #4f46e5; color: #fff; }
.bw-clone-ok:hover { background: #4338ca; }
.bw-clone-cancel { border: 1px solid #cbd5e1; background: #fff; color: #475569; }
.bw-clone-cancel:hover { background: #f8fafc; }
.bw-linha-caixa.dragging { opacity: 0.4; box-shadow: 0 10px 22px rgba(15, 23, 42, 0.18); }
#bw-criar-linha.bw-btn-ok-flash { background: #16a34a !important; border-color: #16a34a !important; }
.bw-col-chip.dragging { opacity: 0.35; cursor: grabbing; position: relative; z-index: 5; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.18); }
#bw-colunas-lista.arrastando { cursor: grabbing; }
.bw-col-marca { width: 3px; align-self: stretch; min-height: 2rem; border-radius: 999px; background: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.22); pointer-events: none; flex: 0 0 3px; }
.bw-col-grip { cursor: grab; opacity: 0.45; letter-spacing: -1px; font-size: 12px; line-height: 1; padding-right: 1px; }
#bw-colunas-lista.arrastando .bw-col-grip,
#bw-colunas-lista.arrastando .bw-linha-grip { cursor: grabbing; }
.bw-col-x { margin-left: 1px; color: #64748b; font-size: 13px; line-height: 1; padding: 0 2px; opacity: 0.55; cursor: pointer; text-transform: none; font-weight: 700; }
.bw-col-chip.calc .bw-col-x { color: #92400e; }
.bw-col-x:hover { opacity: 1; color: #b91c1c; }
#bw-formula-editor.bw-editor-excecao { border-color: #4f46e5; background: #eef2ff; box-shadow: 0 0 0 3px rgba(79,70,229,.18); }
#bw-formula-editor.bw-editor-excecao .bw-canvas { border-color: #4f46e5; border-style: solid; background: #fff; }
#bw-formula-editor.bw-editor-excecao #bw-formula-canvas-label { color: #312e81; }
.bw-excecao-pill { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 9999px; background: #fff; color: #3730a3; font-weight: 800; font-size: 15px; line-height: 1.4; }
</style>

<script>
(function () {
    var root = document.getElementById('boletim-assistente-root');
    if (!root) return;

    var disponivelIa = root.getAttribute('data-disponivel') === '1';
    var pageMode = root.getAttribute('data-page-mode') === '1';
    var returnUrl = root.getAttribute('data-return-url') || (URL_BASE + '/admin/boletim-configuracao?novo=1');
    var urlMensagem = root.getAttribute('data-url-mensagem');
    var urlFerramenta = root.getAttribute('data-url-ferramenta');
    var urlWizardInicio = root.getAttribute('data-url-wizard-inicio');
    var urlWizardMontar = root.getAttribute('data-url-wizard-montar');
    var urlSalvar = root.getAttribute('data-url-salvar');
    var csrf = root.getAttribute('data-csrf');
    var regraId = parseInt(root.getAttribute('data-regra-id') || '0', 10) || 0;
    var retornoFluxo = root.getAttribute('data-retorno-fluxo') || '';
    var urlQuadros = root.getAttribute('data-url-quadros') || '';
    var podeVerQuadro = root.getAttribute('data-pode-ver-quadro') === '1';
    var podeCadastrarQuadro = root.getAttribute('data-pode-cadastrar-quadro') === '1';

    var overlay = document.getElementById('boletim-wizard-overlay');
    var toggle = document.getElementById('boletim-assistente-toggle');
    var btnFechar = document.getElementById('boletim-wizard-fechar');
    var btnVoltar = document.getElementById('boletim-wizard-voltar');
    var btnAvancar = document.getElementById('boletim-wizard-avancar');
    var btnAplicar = document.getElementById('boletim-wizard-aplicar');
    var btnGerarAluno = document.getElementById('boletim-wizard-gerar-aluno');
    var btnCopiar = document.getElementById('boletim-wizard-copiar-receita');
    var stepsEl = document.getElementById('boletim-wizard-steps');
    var bodyEl = document.getElementById('boletim-wizard-body');
    var resumoEl = document.getElementById('boletim-wizard-resumo');
    var errosEl = document.getElementById('boletim-wizard-erros');
    var msgsEl = null;
    var chatForm = null;
    var chatInput = null;
    var chatEnviar = null;
    var chatStatusEl = null;

    var PASSOS = [
        { id: 'inicio', label: '1. Começar', sub: 'Origem' },
        { id: 'identidade', label: '2. Identidade', sub: 'Evento' },
        { id: 'pecas', label: '3. Tipo de Notas', sub: 'Peças' },
        { id: 'publico', label: '4. Agrupar', sub: 'Linha' },
        { id: 'formula', label: '5. Fórmula', sub: 'Colunas' },
        { id: 'revisar', label: '6. Revisão', sub: 'Confirmar' }
    ];

    var dadosIniciaisEl = document.getElementById('boletim-assistente-dados-iniciais');
    var dadosIniciais = {};
    if (dadosIniciaisEl && dadosIniciaisEl.textContent) {
        try { dadosIniciais = JSON.parse(dadosIniciaisEl.textContent) || {}; } catch (e) { dadosIniciais = {}; }
    }
    var catalogo = dadosIniciais.catalogo || { modelos: [], formulas: [], regras: [], series: [], pecas: [], papeis: [] };
    var estado = dadosIniciais.estado || null;
    var rascunhoAtual = dadosIniciais.rascunho || ((estado && estado.rascunho_preservado) ? estado.rascunho_preservado : null);
    var formulasDisp = dadosIniciais.formulas_disponiveis || [];
    var historico = [];
    var enviando = false;
    var iniciado = false;
    var montarTimer = null;
    var montarSeq = 0;
    var formulaVersao = 0;
    var fecharFormulaAposSalvar = true;
    try {
        var prefFecha = window.localStorage && localStorage.getItem('bw_fechar_formula_apos_salvar');
        if (prefFecha === '0') fecharFormulaAposSalvar = false;
        if (prefFecha === '1') fecharFormulaAposSalvar = true;
    } catch (ePrefFecha) { /* ignore */ }
    var vistaPreview = 'quadro';
    var filaChat = [];
    var statusFilaEl = null;
    var formulaDrag = null;
    var paletteCicloAtivo = 'todos';
    var previewAtual = dadosIniciais.preview || null;
    var colunasDrag = null;
    var colunasDragou = false;
    var arrasteColuna = null;
    var arrasteGrupo = null;
    var colunasMarcadas = {};
    var nomeLinhaRascunho = '';
    var avisoLinha = null;
    var editandoCabecalho = false;
    var tituloPeriodoAtivo = { id: '', nome: '' };
    var clonePainelLinhaId = '';
    var clonePainelNome = '';
    var clonePainelChave = '';

    function coletarEstadoFormulario() {
        if (!document.getElementById('form-regra-boletim')) {
            return null;
        }
        var estadoForm = {
            nome: (document.getElementById('regra-nome') || {}).value || '',
            codigo: (document.getElementById('regra-codigo') || {}).value || '',
            descricao_curta: (document.getElementById('regra-descricao-curta') || {}).value || '',
            formula_final: '',
            exibir_em: 'notas',
            finalidade: '',
            boletim_id: 0,
            grupo_regras_notas_id: 0,
            ano_letivo: '',
            bimestre: '',
            round_mode: '',
            nota_minima_aprovacao: '',
            turmas_ids: [],
            materias_ids: [],
            series_ids: [],
            componentes: []
        };
        var formulaEl = document.querySelector('[name="formula_final"]');
        if (formulaEl) estadoForm.formula_final = formulaEl.value || '';
        var exibirEl = document.querySelector('[name="exibir_em"]');
        if (exibirEl) estadoForm.exibir_em = exibirEl.value || 'notas';
        var finEl = document.querySelector('[name="finalidade"]');
        if (finEl) estadoForm.finalidade = finEl.value || '';
        var bolEl = document.querySelector('[name="boletim_id"]') || document.getElementById('regra-boletim-id');
        if (bolEl) estadoForm.boletim_id = parseInt(bolEl.value, 10) || 0;
        var quadroEl = document.querySelector('[name="grupo_regras_notas_id"]') || document.getElementById('grupo-regras-notas-id');
        if (quadroEl) estadoForm.grupo_regras_notas_id = parseInt(quadroEl.value, 10) || 0;
        var anoEl = document.querySelector('[name="ano_letivo"]') || document.getElementById('regra-ano-letivo');
        if (anoEl) estadoForm.ano_letivo = anoEl.value || '';
        var bimEl = document.querySelector('[name="bimestre"]') || document.getElementById('regra-bimestre');
        if (bimEl) estadoForm.bimestre = bimEl.value || '';
        var roundEl = document.querySelector('[name="round_mode"]');
        if (roundEl) estadoForm.round_mode = roundEl.value || '';
        var notaEl = document.querySelector('[name="nota_minima_aprovacao"]');
        if (notaEl) estadoForm.nota_minima_aprovacao = notaEl.value || '';
        document.querySelectorAll('input[name="turmas_ids[]"]:checked').forEach(function (el) {
            estadoForm.turmas_ids.push(parseInt(el.value, 10));
        });
        document.querySelectorAll('input[name="materias_ids[]"]:checked').forEach(function (el) {
            estadoForm.materias_ids.push(parseInt(el.value, 10));
        });
        document.querySelectorAll('input[name="series_ids[]"]:checked').forEach(function (el) {
            estadoForm.series_ids.push(parseInt(el.value, 10));
        });
        var jsonEl = document.getElementById('componentes-json');
        if (jsonEl && jsonEl.value) {
            try { estadoForm.componentes = JSON.parse(jsonEl.value); } catch (e) {}
        }
        return estadoForm;
    }

    function estadoFormularioParaPost() {
        var form = coletarEstadoFormulario();
        return form ? JSON.stringify(enxugarRascunhoParaChat(form)) : '';
    }

    function enxugarRascunhoParaChat(r) {
        if (!r || typeof r !== 'object') return r;
        var out = {};
        Object.keys(r).forEach(function (k) { out[k] = r[k]; });
        if (Array.isArray(out.componentes)) {
            out.componentes = out.componentes.map(function (c) {
                if (!c || typeof c !== 'object') return c;
                var cc = {};
                Object.keys(c).forEach(function (k) { cc[k] = c[k]; });
                cc.blocos_ids = [];
                return cc;
            });
        }
        return out;
    }

    function wizardEstadoParaPost() {
        if (!estado) return '';
        var copy = {};
        Object.keys(estado).forEach(function (k) {
            if (k === 'rascunho_preservado') return;
            copy[k] = estado[k];
        });
        if (ehDemonstrativoNotas()) {
            copy.formulas_blocos = {};
            copy.formulas_materias_blocos = {};
            copy.blocos_calc = [];
            copy.bloco_calc = '';
            copy.formula_final = '';
            copy.formula_tokens = [];
        }
        return JSON.stringify(copy);
    }

    function mostrarStatusFila(texto) {
        if (!msgsEl) return;
        if (statusFilaEl && statusFilaEl.parentNode) {
            statusFilaEl.textContent = texto;
            return;
        }
        statusFilaEl = document.createElement('div');
        statusFilaEl.className = 'mr-2 text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2';
        statusFilaEl.textContent = texto;
        msgsEl.appendChild(statusFilaEl);
        msgsEl.scrollTop = msgsEl.scrollHeight;
    }

    function removerStatusFila() {
        if (statusFilaEl && statusFilaEl.parentNode) {
            statusFilaEl.parentNode.removeChild(statusFilaEl);
        }
        statusFilaEl = null;
    }

    function setChatStatus(texto) {
        if (!chatStatusEl) return;
        if (!texto) {
            chatStatusEl.classList.add('hidden');
            chatStatusEl.textContent = '';
            return;
        }
        chatStatusEl.classList.remove('hidden');
        chatStatusEl.textContent = texto;
    }

    function grupoLinhaPadrao() {
        return { ativo: false, nome: '', modo: 'media', modos: {}, materias_ids: [], aplicar_em: 'boletim', agrupamento_id: 0, arredondamento: 'todos', exemplo_sem_filhas: false };
    }

    function rotuloPecaGrupo(key) {
        var found = (catalogo.pecas || []).filter(function (p) { return p.key === key; })[0];
        return (found && found.label) ? found.label : key;
    }

    function modosGrupoComoObjeto(raw) {
        var out = {};
        if (!raw || typeof raw !== 'object') return out;
        Object.keys(raw).forEach(function (k) {
            if (k === 'length') return;
            if (raw[k] === 'soma' || raw[k] === 'media') out[k] = raw[k];
        });
        return out;
    }

    function modoEfetivoPecaGrupo(gl, peca) {
        var modos = modosGrupoComoObjeto(gl && gl.modos);
        if (modos[peca] === 'soma' || modos[peca] === 'media') return modos[peca];
        return gl && gl.modo === 'soma' ? 'soma' : 'media';
    }

    function gravarModoPadraoNasPecas(modo) {
        if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
        estado.grupo_linha.modos = {};
        (estado.pecas || []).forEach(function (p) {
            if (p) estado.grupo_linha.modos[p] = modo;
        });
    }

    /** Copia média/soma, peças e arredondamento do wizard para o rascunho que será gravado. */
    function gravarEscolhasGrupoNoRascunho(r) {
        if (!r || !estado) return r;
        garantirEstado();
        var gl = estado.grupo_linha || grupoLinhaPadrao();
        var modoPadrao = gl.modo === 'soma' ? 'soma' : 'media';
        var modos = modosGrupoComoObjeto(gl.modos);
        var ar = (gl.arredondamento === 'filhas' || gl.arredondamento === 'mae') ? gl.arredondamento : 'todos';
        var modoIguais = estado.materia_unica_modo === 'media' ? 'media' : 'soma';
        var flagIguais = estado.materia_unica ? 1 : 0;
        (r.componentes || []).forEach(function (c) {
            if (!c || typeof c !== 'object') return;
            if ((c.source_type || '') !== 'calculado') {
                c.materia_unica = flagIguais;
                c.materia_unica_modo = modoIguais;
            }
            if (!gl.ativo) return;
            if (!c.config || typeof c.config !== 'object') c.config = {};
            var linha = (c.config.group_line && typeof c.config.group_line === 'object') ? c.config.group_line : {};
            if (!linha.enabled && !linha.key && !(gl.materias_ids || []).length) return;
            var peca = '';
            var cod = String(c.codigo || '').toLowerCase();
            if (cod === 'media_sem' || cod === 'semanal' || /^s[1-8]$/.test(cod)) peca = 'semanal';
            else if (cod === 'prova_bim' || cod === 'bimestral') peca = 'bimestral';
            else if (cod === 'rec') peca = 'recuperacao';
            else if (modos[cod]) peca = cod;
            linha.enabled = true;
            linha.mode = (peca && modos[peca]) ? modos[peca] : modoPadrao;
            linha.modo_padrao = modoPadrao;
            linha.modos = modos;
            linha.arredondamento = ar;
            if ((gl.materias_ids || []).length >= 2) {
                linha.materias_ids = (gl.materias_ids || []).map(Number).filter(function (n) { return n > 0; });
            }
            if (gl.nome) linha.label = gl.nome;
            if (gl.aplicar_em === 'ambos' || gl.aplicar_em === 'boletim') linha.aplicar_em = gl.aplicar_em;
            linha.ocultar_filhas = !!gl.exemplo_sem_filhas;
            c.config.group_line = linha;
        });
        return r;
    }

    /** Garante que o rascunho aplicado/salvo leve o arredondamento da área escolhido no wizard. */
    function garantirArredondamentoGrupoNoRascunho(r) {
        if (!r || !estado || !estado.grupo_linha) return r;
        var ar = estado.grupo_linha.arredondamento;
        if (ar !== 'filhas' && ar !== 'mae' && ar !== 'todos') ar = 'todos';
        if (!estado.grupo_linha.ativo) return r;
        (r.componentes || []).forEach(function (c) {
            if (!c || typeof c !== 'object') return;
            if (!c.config || typeof c.config !== 'object') c.config = {};
            if (!c.config.group_line || typeof c.config.group_line !== 'object') return;
            if (!c.config.group_line.enabled) return;
            c.config.group_line.arredondamento = ar;
        });
        return r;
    }

    function agrupamentoCadastroPorId(id) {
        id = Number(id || 0);
        var found = null;
        (catalogo.agrupamentos || []).forEach(function (ag) {
            if (Number(ag.id) === id) found = ag;
        });
        return found;
    }

    function aplicarAgrupamentoCadastro(id) {
        if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
        id = Number(id || 0);
        estado.grupo_linha.agrupamento_id = id > 0 ? id : 0;
        var ag = agrupamentoCadastroPorId(id);
        if (!ag) return;
        estado.grupo_linha.ativo = true;
        estado.grupo_linha.nome = ag.nome || '';
        estado.grupo_linha.modo = ag.modo === 'soma' ? 'soma' : 'media';
        estado.grupo_linha.modos = {};
        estado.grupo_linha.aplicar_em = ag.aplicar_em === 'ambos' ? 'ambos' : 'boletim';
        estado.grupo_linha.materias_ids = (ag.materias_ids || []).map(Number).filter(function (n) { return n > 0; });
    }

    function turmasVisiveis() {
        var seriesSel = (estado.series_ids || []).map(Number);
        var ano = Number(estado.ano_letivo || 0);
        return (catalogo.turmas || []).filter(function (t) {
            if (seriesSel.length && t.serie_id && seriesSel.indexOf(Number(t.serie_id)) < 0) return false;
            if (ano && t.ano_letivo && Number(t.ano_letivo) !== ano) return false;
            return true;
        });
    }

    function boletimEscolhido() {
        var id = Number(estado && estado.boletim_id ? estado.boletim_id : 0);
        var layoutId = Number(estado && estado.layout_boletim_id ? estado.layout_boletim_id : 0)
            || Number(root.getAttribute('data-layout-boletim-id') || 0);
        // layout_boletim_id nunca é modelo de cadastro — não usa como id do catálogo.
        if (id > 0 && layoutId > 0 && id === layoutId) id = 0;
        var impressao = !!(layoutId > 0
            || retornoFluxo === 'boletim-layout'
            || (estado && (estado.exibir_em === 'boletim' || estado.quadro_livre || estado.origem === 'quadro-impressao')));
        if (!id) {
            if (!impressao) return null;
            return {
                id: 0,
                nome: (estado && estado.nome) || 'Quadro de impressão',
                materias_ids: (estado && estado.materias_ids) || []
            };
        }
        var noCatalogo = (catalogo.boletins || []).filter(function (b) { return Number(b.id) === id; })[0] || null;
        if (noCatalogo) return noCatalogo;
        if (impressao) {
            return { id: 0, nome: (estado && estado.nome) || 'Quadro de impressão', materias_ids: (estado && estado.materias_ids) || [] };
        }
        return null;
    }

    function materiasCatalogoDoEscopo() {
        var bol = boletimEscolhido();
        var todas = catalogo.materias || [];
        if (!bol) return todas;
        var ids = (bol.materias_ids || []).map(Number).filter(function (n) { return n > 0; });
        if (!ids.length) return todas;
        var set = {};
        ids.forEach(function (id) { set[id] = true; });
        // Regra/modelo podem guardar só o oficial (pai); o catálogo lista os desdobramentos.
        (catalogo.familias_componentes || []).forEach(function (f) {
            var pai = Number(f && f.pai_id ? f.pai_id : 0);
            if (!pai || !set[pai]) return;
            (f.filhos || []).forEach(function (ch) {
                var cid = Number(ch && ch.id ? ch.id : 0);
                if (cid > 0) set[cid] = true;
            });
        });
        return todas.filter(function (m) { return set[Number(m.id)]; });
    }

    function materiasDoQuadroSelecionado() {
        var id = Number(estado && estado.grupo_regras_notas_id ? estado.grupo_regras_notas_id : 0);
        if (!id) return [];
        var quadro = (catalogo.quadros_notas || []).filter(function (q) { return Number(q.id) === id; })[0];
        return (quadro && quadro.materias) || [];
    }

    function materiasParaExcecao() {
        var bol = boletimEscolhido();
        var doBoletim = (bol && (bol.materias_ids || []).length) ? materiasCatalogoDoEscopo() : [];
        if (doBoletim.length) return doBoletim;
        return materiasDoQuadroSelecionado();
    }

    function familiasDoEscopo() {
        var bol = boletimEscolhido();
        var idsFiltro = [];
        if (bol && (bol.materias_ids || []).length) {
            idsFiltro = (bol.materias_ids || []).map(Number);
        } else if (estado && (estado.materias_ids || []).length) {
            idsFiltro = (estado.materias_ids || []).map(Number);
        }
        var set = null;
        idsFiltro = idsFiltro.filter(function (n) { return n > 0; });
        if (idsFiltro.length) {
            set = {};
            idsFiltro.forEach(function (id) { set[id] = true; });
        }
        return (catalogo.familias_componentes || []).map(function (f) {
            var filhos = (f.filhos || []).filter(function (ch) {
                return !set || set[Number(ch.id)];
            });
            if (filhos.length < 2 && set && set[Number(f.pai_id)]) {
                filhos = (f.filhos || []).slice();
            }
            if (filhos.length < 2) return null;
            return {
                pai_id: Number(f.pai_id),
                nome: f.nome || '',
                filhos: filhos,
                materias_ids: filhos.map(function (ch) { return Number(ch.id); })
            };
        }).filter(Boolean);
    }

    function idsGrupoIguais(a, b) {
        var x = (a || []).map(Number).filter(function (n) { return n > 0; }).sort(function (p, q) { return p - q; });
        var y = (b || []).map(Number).filter(function (n) { return n > 0; }).sort(function (p, q) { return p - q; });
        if (x.length !== y.length) return false;
        for (var i = 0; i < x.length; i++) {
            if (x[i] !== y[i]) return false;
        }
        return true;
    }

    function aplicarFamiliaComponente(f) {
        if (!f) return;
        if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
        estado.grupo_linha.ativo = true;
        estado.grupo_linha.nome = f.nome || '';
        estado.grupo_linha.materias_ids = (f.materias_ids || []).slice();
        estado.grupo_linha.agrupamento_id = 0;
    }

    function garantirFamiliaPadrao() {
        if (!estado.grupo_linha || !estado.grupo_linha.ativo) return;
        if ((estado.grupo_linha.materias_ids || []).length >= 2) return;
        var fams = familiasDoEscopo();
        if (fams.length === 1) aplicarFamiliaComponente(fams[0]);
    }

    function aplicarMateriaUnicaNasPecas() {
        if (!estado) return;
        if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
        var flag = estado.materia_unica ? 1 : 0;
        var modo = estado.materia_unica_modo === 'media' ? 'media' : 'soma';
        (estado.pecas || []).forEach(function (k) {
            if (!estado.pecas_opcoes[k]) {
                estado.pecas_opcoes[k] = pecasOpcoesPadraoJs(k);
            } else {
                estado.pecas_opcoes[k].materia_unica = flag;
                estado.pecas_opcoes[k].materia_unica_modo = modo;
                if (!estado.pecas_opcoes[k].papel) estado.pecas_opcoes[k].papel = papelPadrao(k);
            }
        });
    }

    function pecaMeta(key) {
        var found = null;
        (catalogo.pecas || []).forEach(function (p) { if (p.key === key) found = p; });
        return found;
    }

    function papelPadrao(key) {
        return key === 'recuperacao' ? 'substitui' : 'media';
    }

    function calcTypePadrao(key) {
        if (key === 'bimestral' || key === 'enac' || key === 'trabalho' || key === 'participacao' || key === 'recuperacao') {
            return 'ultima';
        }
        return 'media';
    }

    function usarPercentualPadrao(key) {
        return key === 'semanal' ? 1 : 0;
    }

    function pecaMostraNotaEvento(key) {
        return key !== 'semanal' && key !== 'jornada';
    }

    function pecaPermiteAcertos(key) {
        return key === 'semanal' || key === 'bimestral' || key === 'enac' || String(key).indexOf('tipo_') === 0;
    }

    function pecasOpcoesPadraoJs(key) {
        var meta = pecaMeta(key);
        return {
            calc_type: (meta && meta.calc_type) ? meta.calc_type : calcTypePadrao(key),
            materia_unica: estado && estado.materia_unica ? 1 : 0,
            materia_unica_modo: (estado && estado.materia_unica_modo === 'media') ? 'media' : 'soma',
            usar_percentual: (meta && typeof meta.usar_percentual !== 'undefined') ? (meta.usar_percentual ? 1 : 0) : usarPercentualPadrao(key),
            tipo_avaliacao_id: meta && meta.tipo_avaliacao_id ? Number(meta.tipo_avaliacao_id) : 0,
            papel: papelPadrao(key),
            bimestres: bimestresPadraoPeca(),
            blocos_ids: [],
            blocos_ids_manual: 0
        };
    }

    function periodoInfo(ano) {
        var mapa = (catalogo && catalogo.periodos_por_ano) || {};
        var chave = String(Number(ano == null ? (estado && estado.ano_letivo) : ano) || 0);
        var info = mapa[chave] || null;
        if (info && Number(info.quantidade) > 0) return info;
        return {
            tipo: 'bimestre',
            quantidade: 4,
            rotulo_campo: 'Bimestre',
            rotulo_campo_plural: 'Bimestres',
            rotulos: { '1': '1º Bimestre', '2': '2º Bimestre', '3': '3º Bimestre', '4': '4º Bimestre' }
        };
    }

    function nomePadraoEventoJs(ano, bimestre) {
        var info = periodoInfo(ano);
        var tipo = String(info.tipo || 'bimestre');
        var adjetivo = tipo === 'trimestre' ? 'Trimestrais'
            : (tipo === 'semestre' ? 'Semestrais' : (tipo === 'etapa_unica' ? '' : 'Bimestrais'));
        var anoNum = Number(ano) || 0;
        var bim = Number(bimestre) || 0;
        if (bim <= 0 || adjetivo === '') return 'Notas — ' + anoNum;
        var campo = String(info.rotulo_campo || 'Bimestre').toLocaleLowerCase('pt-BR');
        return 'Notas ' + adjetivo + ' — ' + bim + 'º ' + campo + ' ' + anoNum;
    }

    function nomePareceSugestao(nome) {
        return /^Notas(?: Bimestrais| Trimestrais| Semestrais)? — /.test(String(nome || '').trim());
    }

    var anoAntesPendente = null;
    var bimAntesPendente = null;

    function nomeSegueSugestao(anoAntes, bimAntes) {
        if (!estado) return false;
        if (estado.nome_automatico === false) return false;
        if (estado.nome_automatico === true) return true;
        var nome = String(estado.nome || '').trim();
        if (nome === '') return true;
        if (nome === nomePadraoEventoJs(anoAntes, bimAntes)) return true;
        return false;
    }

    function sincronizarNomeSugerido(anoAntes, bimAntes) {
        if (!estado) return;
        var anoAgora = Number(estado.ano_letivo || 0);
        var bimAgora = Number(estado.bimestre || 0);
        anoAntes = Number(anoAntes || 0);
        bimAntes = Number(bimAntes || 0);
        if (anoAgora === anoAntes && bimAgora === bimAntes) return;
        if (anoAgora < 2000 || anoAgora > 2100) return;
        if (!nomeSegueSugestao(anoAntes, bimAntes)) return;
        if (!periodoNumeroValido(bimAgora, anoAgora)) {
            bimAgora = Math.max(1, maxPeriodo(anoAgora));
            estado.bimestre = bimAgora;
        }
        estado.nome = nomePadraoEventoJs(anoAgora, bimAgora);
        estado.nome_automatico = true;
        estado._ajustandoNome = true;
        var nomeAuto = document.getElementById('bw-nome');
        if (nomeAuto) nomeAuto.value = estado.nome;
        estado._ajustandoNome = false;
        atualizarCabecalhoEvento();
    }

    function maxPeriodo(ano) {
        return Number(periodoInfo(ano).quantidade) || 4;
    }

    function periodoNumeroValido(n, ano) {
        n = Number(n);
        return n >= 1 && n <= maxPeriodo(ano);
    }

    function rotuloCampoPeriodo(ano) {
        return String(periodoInfo(ano).rotulo_campo || 'Bimestre');
    }

    function rotuloPeriodoNumero(n, ano) {
        var rotulos = periodoInfo(ano).rotulos || {};
        return rotulos[String(n)] || rotulos[n] || (n + 'º ' + rotuloCampoPeriodo(ano));
    }

    function htmlOpcoesPeriodo(ano, selecionado) {
        var html = '';
        var i;
        for (i = 1; i <= maxPeriodo(ano); i++) {
            html += '<option value="' + i + '"' + (Number(selecionado) === i ? ' selected' : '') + '>' + esc(rotuloPeriodoNumero(i, ano)) + '</option>';
        }
        return html;
    }

    function bimestresPadraoPeca() {
        var b = Number((estado && estado.bimestre) || 0);
        return periodoNumeroValido(b) ? [b] : [];
    }

    function bimestresUiPeca() {
        var lista = [];
        var i;
        for (i = 1; i <= maxPeriodo(); i++) lista.push(i);
        return lista;
    }

    function htmlBimestresPeca(key, opts) {
        var bimsPeca = normalizarBimestresPeca(opts && opts.bimestres);
        if (!bimsPeca.length && periodoNumeroValido(estado && estado.bimestre)) {
            bimsPeca = [Number(estado.bimestre)];
        }
        var campo = rotuloCampoPeriodo();
        var campoMin = campo.toLocaleLowerCase('pt-BR');
        var html = '<div><span class="text-xs font-medium text-gray-600">' + esc(campo) + ' dos eventos</span>';
        html += '<p class="text-xs text-gray-500 mt-0.5">Marque o(s) ' + esc(campoMin) + '(s). Nenhum = usa o ' + esc(campoMin) + ' escolhido na Identidade.</p>';
        html += '<div class="grid grid-cols-3 gap-2 mt-2">';
        bimestresUiPeca().forEach(function (b) {
            var n = key === 'jornada'
                ? jornadasDoBimestre(b).length
                : eventosDaPecaNoBimestre(key, b).length;
            n = Number(n) || 0;
            var on = bimsPeca.some(function (x) { return Number(x) === b; });
            html += '<label class="inline-flex items-center gap-2 text-sm border rounded-lg px-3 py-2 bg-slate-50 cursor-pointer">';
            html += '<input type="checkbox" class="bw-peca-bim rounded border-gray-300 text-indigo-600" data-peca="' + key + '" value="' + b + '"' + (on ? ' checked' : '') + '>';
            html += '<span>' + esc(rotuloPeriodoNumero(b)) + ' <span class="text-xs text-gray-500">(' + n + ')</span></span></label>';
        });
        html += '</div></div>';
        return html;
    }

    function eventosSelecionaveisPeca(key, opts) {
        var bims = normalizarBimestresPeca(opts && opts.bimestres);
        if (!bims.length && periodoNumeroValido(estado.bimestre)) bims = [Number(estado.bimestre)];
        var byId = {};
        bims.forEach(function (b) {
            eventosDaPecaNoBimestre(key, b).forEach(function (ev) {
                byId[Number(ev.id)] = ev;
            });
        });
        return Object.keys(byId).map(function (id) { return byId[id]; }).sort(function (a, b) {
            var sa = Number(a.semana || 0);
            var sb = Number(b.semana || 0);
            if (sa !== sb) return sa - sb;
            return String(a.titulo || '').localeCompare(String(b.titulo || ''));
        });
    }

    function dataProvaBr(iso) {
        var s = String(iso || '').slice(0, 10);
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return m ? (m[3] + '/' + m[2] + '/' + m[1]) : '';
    }

    function tituloEventoLimpo(titulo) {
        return String(titulo || '').replace(/\s+-\s+Bloco\s+[A-Za-z0-9]+(?:\s+-\s+.*)?$/i, '').trim();
    }

    function labelEventoProva(ev) {
        var titulo = tituloEventoLimpo(ev.titulo) || ('Evento #' + ev.id);
        var parts = [titulo];
        var data = dataProvaBr(ev.data_prova);
        if (data) parts.push(data);
        var bloco = String(ev.bloco || '').trim();
        if (!bloco) {
            var mb = String(ev.titulo || '').match(/Bloco\s+([A-Za-z0-9]+)/i);
            if (mb) bloco = 'Bloco ' + mb[1].toUpperCase();
        }
        if (bloco) parts.push(bloco);
        var sem = String(ev.semana_rotulo || '').trim();
        if (!sem && ev.semana) sem = 'S' + ev.semana;
        if (!sem) {
            var ms = String(ev.titulo || '').match(/\b(S\d+)\b/i);
            if (ms) sem = ms[1].toUpperCase();
        }
        if (sem) parts.push(sem);
        if (ev.bimestre && periodoNumeroValido(ev.bimestre, ev.ano_letivo || (estado && estado.ano_letivo))) {
            parts.push(rotuloPeriodoNumero(ev.bimestre, ev.ano_letivo || (estado && estado.ano_letivo)));
        }
        return parts.join(' · ');
    }

    function normalizarIdsJs(raw) {
        var out = [];
        (Array.isArray(raw) ? raw : []).forEach(function (x) {
            var n = parseInt(x, 10);
            if (n > 0 && out.indexOf(n) < 0) out.push(n);
        });
        return out;
    }

    function htmlAvisoAnoEventos(key, opts, eventos) {
        var bims = normalizarBimestresPeca(opts && opts.bimestres);
        if (!bims.length && periodoNumeroValido(estado.bimestre)) bims = [Number(estado.bimestre)];
        var noAno = [];
        bims.forEach(function (b) {
            eventosDaPecaFiltrados(key, b, true).forEach(function (ev) { noAno.push(ev); });
        });
        if (noAno.length || !(eventos || []).length) return '';
        var porAno = {};
        (eventos || []).forEach(function (ev) {
            var a = Number(ev.ano_letivo || 0);
            if (a > 0) porAno[a] = (porAno[a] || 0) + 1;
        });
        var anos = Object.keys(porAno).map(Number).sort();
        if (!anos.length) return '';
        var anoId = Number(estado.ano_letivo || 0);
        var anoEv = anos[0];
        var html = '<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 mb-2">';
        html += 'O ano da identidade está em <strong>' + esc(String(anoId || '—')) + '</strong>, mas os eventos desta peça são de <strong>' + esc(anos.join(', ')) + '</strong>.';
        html += ' A lista abaixo usa esses eventos.';
        html += ' <button type="button" class="bw-usar-ano-eventos underline font-semibold" data-ano="' + anoEv + '">Usar o ano ' + anoEv + '</button>.';
        html += '</div>';
        return html;
    }

    function usaEventosAvaliativosNaNota() {
        return !!(catalogo && catalogo.modulo_eventos_avaliativos && estado && estado.exibir_em === 'notas' && !ehBoletimComposto());
    }

    function codigosPeriodoNota() {
        var bim = Number((estado && estado.bimestre) || 0);
        var ids = {};
        if (bim >= 1) ids[bim] = true;
        if (bim >= 1 && bim <= 12) ids[60 + bim] = true;
        if (bim >= 1 && bim <= 4) {
            ids[80 + Math.ceil(bim / 2)] = true;
            ids[90] = true;
        }
        return ids;
    }

    function eventosAvaliativosDoPeriodo() {
        var ano = Number((estado && estado.ano_letivo) || 0);
        var aceitos = codigosPeriodoNota();
        return (catalogo.eventos_avaliativos || []).filter(function (ev) {
            if (ano > 0 && Number(ev.ano_letivo || 0) !== ano) return false;
            return !!aceitos[Number(ev.bimestre || 0)];
        });
    }

    function componentePorCodigo(codigo) {
        codigo = String(codigo || '').toLowerCase();
        if (!codigo) return null;
        var achado = null;
        [rascunhoAtual, estado && estado.rascunho_preservado].forEach(function (r) {
            if (!r || !Array.isArray(r.componentes)) return;
            r.componentes.forEach(function (c) {
                if (c && String(c.codigo || '').toLowerCase() === codigo) achado = c;
            });
        });
        return achado;
    }

    function nomeDaColunaDoSubevento(ev, sub) {
        var subs = (ev && ev.subeventos) || [];
        var recuperacao = Number(ev && ev.bimestre || 0) >= 60;
        var nomeEvento = String(ev && ev.nome || '').trim();
        if (recuperacao && subs.length <= 1 && nomeEvento) return nomeEvento;
        var titulo = String(sub && sub.titulo || '').trim();
        return titulo || nomeEvento;
    }

    function tituloSubeventoDaColuna(codigo) {
        var comp = componentePorCodigo(codigo);
        if (!comp) return '';
        var bruto = comp.blocos_ids;
        if (typeof bruto === 'string') bruto = bruto.split(',');
        var ids = normalizarIdsJs(bruto);
        if (!ids.length && Number(comp.bloco_id || 0) > 0) ids = [Number(comp.bloco_id)];
        var info = null;
        if (ids.length === 1) {
            eventosAvaliativosDoPeriodo().forEach(function (ev) {
                (ev.subeventos || []).forEach(function (sub) {
                    if (Number(sub && sub.id) === ids[0]) info = { ev: ev, sub: sub };
                });
            });
        }
        if (!info) {
            var cfg = comp.config || {};
            if (typeof cfg === 'string') {
                try { cfg = JSON.parse(cfg) || {}; } catch (e) { cfg = {}; }
            }
            if (!cfg || typeof cfg !== 'object') {
                cfg = {};
                if (typeof comp.config_json === 'string' && comp.config_json) {
                    try { cfg = JSON.parse(comp.config_json) || {}; } catch (e2) { cfg = {}; }
                }
            }
            var tipo = Number(cfg.tipo_avaliacao_id || comp.tipo_avaliacao_id || 0);
            if (tipo > 0) {
                var achados = [];
                eventosAvaliativosDoPeriodo().forEach(function (ev) {
                    (ev.subeventos || []).forEach(function (sub) {
                        if (Number(sub && sub.tipo_avaliacao_id) === tipo) achados.push({ ev: ev, sub: sub });
                    });
                });
                if (achados.length === 1) info = achados[0];
            }
        }
        return info ? nomeDaColunaDoSubevento(info.ev, info.sub) : '';
    }

    function colunaDoRascunhoVemDeEvento(r, codigo) {
        codigo = String(codigo || '').toLowerCase();
        var achou = false;
        ((r && r.componentes) || []).forEach(function (c) {
            if (!c || String(c.codigo || '').toLowerCase() !== codigo) return;
            var src = String(c.source_type || '');
            if (src === 'provas_sistema' || src === 'provas') achou = true;
        });
        return achou;
    }

    function aplicarTitulosDosSubeventos() {
        if (!estado) return;
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object' || Array.isArray(estado.nomes_blocos)) {
            estado.nomes_blocos = {};
        }
        var codigos = {};
        [rascunhoAtual, estado.rascunho_preservado].forEach(function (r) {
            if (!r || !Array.isArray(r.componentes)) return;
            r.componentes.forEach(function (c) {
                if (c && c.codigo) codigos[String(c.codigo)] = true;
            });
        });
        Object.keys(codigos).forEach(function (codigo) {
            var titulo = tituloSubeventoDaColuna(codigo);
            if (!titulo) return;
            estado.nomes_blocos[String(codigo).toLowerCase()] = titulo;
            pintarTituloNasColunas(codigo, titulo);
        });
    }

    function pecaDoTipoAvaliacao(tipoId) {
        var id = Number(tipoId || 0);
        if (id <= 0) return null;
        var achou = null;
        (catalogo.pecas || []).forEach(function (p) {
            if (!p || p.key === 'jornada') return;
            if (Number(p.tipo_avaliacao_id || 0) === id) achou = p;
        });
        return achou;
    }

    function pecasDoEventoAvaliativo(ev) {
        var direta = pecaDoTipoAvaliacao(ev && ev.tipo_avaliacao_id);
        if (direta) return [direta];
        var vistas = {};
        var lista = [];
        ((ev && ev.subeventos) || []).forEach(function (sub) {
            var peca = pecaDoTipoAvaliacao(sub && sub.tipo_avaliacao_id);
            if (!peca || vistas[peca.key]) return;
            vistas[peca.key] = true;
            lista.push(peca);
        });
        return lista;
    }

    function eventoAvaliativoMarcado(ev) {
        if (Number(estado && estado.eventos_ids_manual || 0) === 1 && Array.isArray(estado.eventos_ids)) {
            return estado.eventos_ids.map(Number).indexOf(Number(ev && ev.id)) >= 0;
        }
        var bim = Number(ev && ev.bimestre || 0);
        if (bim >= 60) return true;
        var peca = pecaDoTipoAvaliacao(ev && ev.tipo_avaliacao_id);
        return !!(peca && (estado.pecas || []).indexOf(peca.key) >= 0);
    }

    function subeventoAvaliativoMarcado(ev, sub) {
        var id = Number(sub && sub.id || 0);
        if (Number(estado && estado.subeventos_ids_manual || 0) === 1 && Array.isArray(estado.subeventos_ids)) {
            return estado.subeventos_ids.map(Number).indexOf(id) >= 0;
        }
        var pecaPai = pecaDoTipoAvaliacao(ev && ev.tipo_avaliacao_id);
        var peca = pecaPai || pecaDoTipoAvaliacao(sub && sub.tipo_avaliacao_id);
        if (!peca) return true;
        if ((estado.pecas || []).indexOf(peca.key) < 0) return false;
        var opts = (estado.pecas_opcoes && estado.pecas_opcoes[peca.key]) || {};
        if (Number(opts.blocos_ids_manual || 0) !== 1) return true;
        var id = Number(sub && sub.id || 0);
        return normalizarIdsJs(opts.blocos_ids).indexOf(id) >= 0;
    }

    function htmlSubeventosAvaliativos(ev) {
        var html = '<div class="mt-3 pl-7 space-y-2 border-t border-gray-100 pt-3">';
        html += '<p class="text-xs font-medium text-gray-600">Sub-eventos que entram na nota</p>';
        html += '<div class="grid sm:grid-cols-2 gap-2">';
        (ev.subeventos || []).forEach(function (sub) {
            var id = Number(sub.id || 0);
            if (id <= 0) return;
            var on = subeventoAvaliativoMarcado(ev, sub);
            var data = dataProvaBr(sub.data_prova);
            html += '<label class="inline-flex items-start gap-2 text-sm border rounded-lg px-3 py-2 bg-white cursor-pointer">';
            html += '<input type="checkbox" class="bw-evento-sub mt-0.5 rounded border-gray-300 text-indigo-600" data-evento="' + Number(ev.id) + '" value="' + id + '"' + (on ? ' checked' : '') + '>';
            html += '<span>' + esc(sub.titulo || ('Sub-evento #' + id)) + (data ? ' · ' + esc(data) : '') + '</span></label>';
        });
        html += '</div>';
        html += '<p class="text-xs text-gray-500">As notas lançadas nesses sub-eventos entram neste boletim.</p>';
        html += '</div>';
        return html;
    }

    function htmlPecasEventosAvaliativos() {
        var periodo = '';
        if (Number(estado.ano_letivo || 0) > 0) periodo += String(estado.ano_letivo);
        if (periodoNumeroValido(estado.bimestre)) {
            periodo += (periodo ? ' · ' : '') + rotuloPeriodoNumero(estado.bimestre, estado.ano_letivo);
        }
        var html = '<p class="text-sm text-gray-700">Quais eventos avaliativos entram neste boletim?</p>';
        html += '<p class="text-xs text-gray-500 mt-1">Do período da identidade' + (periodo ? ' (' + esc(periodo) + ')' : '') + '. O cálculo continua no passo <strong>Fórmula</strong>.</p>';
        var lista = eventosAvaliativosDoPeriodo();
        html += '<div class="grid gap-3 mt-3">';
        if (!lista.length) {
            html += '<div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-sm text-gray-600">Nenhum evento avaliativo neste período. Cadastre em <a class="text-indigo-700 underline" href="<?= URL ?>/admin/eventos-avaliativos">Eventos Avaliativos</a>.</div>';
        }
        lista.forEach(function (ev) {
            var on = eventoAvaliativoMarcado(ev);
            var detalhe = [];
            var nSub = Number(ev.total_subeventos || (ev.subeventos || []).length || 0);
            detalhe.push(nSub + (nSub === 1 ? ' sub-evento' : ' sub-eventos'));
            if (ev.turmas_nomes) detalhe.push(ev.turmas_nomes);
            html += '<div class="bw-card' + (on ? ' selected' : '') + '">';
            html += '<label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" class="mt-1 bw-evento-avaliativo rounded border-gray-300 text-indigo-600" value="' + Number(ev.id) + '"' + (on ? ' checked' : '') + '>'
                + '<span><span class="text-sm font-semibold text-gray-900">' + esc(ev.nome || ('Evento #' + ev.id)) + '</span>'
                + (ev.descricao ? '<span class="block text-xs text-gray-500">' + esc(ev.descricao) + '</span>' : '')
                + '<span class="block text-xs text-gray-500 mt-1">' + esc(detalhe.join(' · ')) + '</span></span></label>';
            if (on) html += htmlSubeventosAvaliativos(ev);
            html += '</div>';
        });
        var jornada = null;
        (catalogo.pecas || []).forEach(function (p) { if (p.key === 'jornada') jornada = p; });
        if (jornada && catalogo.modulo_jornadas) {
            var onJ = (estado.pecas || []).indexOf('jornada') >= 0;
            var optsJ = (estado.pecas_opcoes && estado.pecas_opcoes.jornada) ? estado.pecas_opcoes.jornada : pecasOpcoesPadraoJs('jornada');
            html += '<div class="bw-card' + (onJ ? ' selected' : '') + '">';
            html += '<label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" class="mt-1 bw-evento-jornada rounded border-gray-300 text-indigo-600"' + (onJ ? ' checked' : '') + '>'
                + '<span><span class="text-sm font-semibold text-gray-900">' + esc(jornada.label) + '</span><span class="block text-xs text-gray-500">' + esc(jornada.hint || '') + '</span></span></label>';
            if (onJ) {
                html += '<div class="mt-3 pl-7 space-y-3 border-t border-gray-100 pt-3">';
                html += htmlBimestresPeca('jornada', optsJ);
                html += htmlJornadaPeca(optsJ);
                html += '</div>';
            }
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    function aplicarSelecaoEventosAvaliativos() {
        var keys = [];
        var jornadaOn = (estado.pecas || []).indexOf('jornada') >= 0;
        if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
        var pecasGravadas = {};
        var idsEvento = [];
        var idsSub = [];
        function guardarPecaEvento(peca, ids, ev) {
            if (!peca) return;
            if (keys.indexOf(peca.key) < 0) keys.push(peca.key);
            if (!estado.pecas_opcoes[peca.key]) estado.pecas_opcoes[peca.key] = pecasOpcoesPadraoJs(peca.key);
            var atuais = pecasGravadas[peca.key] ? normalizarIdsJs(estado.pecas_opcoes[peca.key].blocos_ids) : [];
            ids.forEach(function (sid) {
                if (atuais.indexOf(sid) < 0) atuais.push(sid);
            });
            pecasGravadas[peca.key] = true;
            var bimEvento = Number(ev.bimestre || 0);
            var bim = bimEvento >= 60 ? Number(estado.bimestre || 0) : (bimEvento || Number(estado.bimestre || 0));
            estado.pecas_opcoes[peca.key].bimestres = bim > 0 ? [bim] : [];
            estado.pecas_opcoes[peca.key].blocos_ids = atuais;
            estado.pecas_opcoes[peca.key].blocos_ids_manual = 1;
            estado.pecas_opcoes[peca.key].tipo_avaliacao_id = Number(peca.tipo_avaliacao_id || ev.tipo_avaliacao_id || 0);
        }
        bodyEl.querySelectorAll('.bw-evento-avaliativo:checked').forEach(function (el) {
            var id = Number(el.value);
            var ev = (catalogo.eventos_avaliativos || []).find(function (x) { return Number(x.id) === id; });
            if (!ev) return;
            if (idsEvento.indexOf(id) < 0) idsEvento.push(id);
            var marcados = [];
            bodyEl.querySelectorAll('.bw-evento-sub[data-evento="' + id + '"]:checked').forEach(function (sub) {
                var sid = Number(sub.value);
                if (sid > 0) marcados.push(sid);
            });
            if (!marcados.length) {
                (ev.subeventos || []).forEach(function (sub) {
                    var sid = Number(sub.id || 0);
                    if (sid > 0) marcados.push(sid);
                });
            }
            marcados.forEach(function (sid) {
                if (idsSub.indexOf(sid) < 0) idsSub.push(sid);
            });
            var pecaPai = pecaDoTipoAvaliacao(ev.tipo_avaliacao_id);
            if (pecaPai) {
                guardarPecaEvento(pecaPai, marcados, ev);
                return;
            }
            var porTipo = {};
            (ev.subeventos || []).forEach(function (sub) {
                var sid = Number(sub.id || 0);
                if (sid <= 0 || marcados.indexOf(sid) < 0) return;
                var peca = pecaDoTipoAvaliacao(sub.tipo_avaliacao_id);
                if (!peca) return;
                if (!porTipo[peca.key]) porTipo[peca.key] = { peca: peca, ids: [] };
                porTipo[peca.key].ids.push(sid);
            });
            Object.keys(porTipo).forEach(function (k) {
                guardarPecaEvento(porTipo[k].peca, porTipo[k].ids, ev);
            });
        });
        estado.pecas = keys;
        estado.eventos_ids = idsEvento;
        estado.eventos_ids_manual = 1;
        estado.subeventos_ids = idsSub;
        estado.subeventos_ids_manual = 1;
        if (jornadaOn && estado.pecas.indexOf('jornada') < 0) estado.pecas.push('jornada');
        aplicarJuntarProfessoresDoEvento();
        marcarEdicaoManual();
    }

    function criterioProfessoresDosEventosMarcados() {
        var vistos = [];
        (estado.pecas || []).forEach(function (key) {
            if (key === 'jornada') return;
            var peca = null;
            (catalogo.pecas || []).forEach(function (p) { if (p.key === key) peca = p; });
            if (!peca) return;
            (catalogo.eventos_avaliativos || []).forEach(function (ev) {
                if (Number(ev.tipo_avaliacao_id || 0) !== Number(peca.tipo_avaliacao_id || 0)) return;
                var c = String(ev.criterio_professores || 'nenhum');
                if (vistos.indexOf(c) < 0) vistos.push(c);
            });
        });
        if (!vistos.length) return 'nenhum';
        if (vistos.indexOf('soma') >= 0 && vistos.indexOf('media') >= 0) return vistos[0] === 'nenhum' ? vistos[1] : vistos[0];
        if (vistos.indexOf('soma') >= 0) return 'soma';
        if (vistos.indexOf('media') >= 0) return 'media';
        return 'nenhum';
    }

    function aplicarJuntarProfessoresDoEvento() {
        if (!usaEventosAvaliativosNaNota()) return;
        var criterio = criterioProfessoresDosEventosMarcados();
        estado.materia_unica = (criterio === 'soma' || criterio === 'media') ? 1 : 0;
        estado.materia_unica_modo = criterio === 'media' ? 'media' : 'soma';
        estado.materia_unica_tocada = true;
        if (typeof aplicarMateriaUnicaNasPecas === 'function') aplicarMateriaUnicaNasPecas();
    }

    function htmlEventosPeca(key, opts) {
        if (key === 'jornada') return '';
        var eventos = eventosSelecionaveisPeca(key, opts || {});
        if (!eventos.length) {
            var anoTxt = Number(estado.ano_letivo || 0) > 0 ? (' de ' + Number(estado.ano_letivo)) : '';
            var campoMin = rotuloCampoPeriodo().toLocaleLowerCase('pt-BR');
            return '<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">Nenhum evento encontrado para esta peça no ' + esc(campoMin) + ' marcado' + anoTxt + '.</div>';
        }
        var manual = Number((opts && opts.blocos_ids_manual) || 0) === 1;
        var ids = normalizarIdsJs(opts && opts.blocos_ids);
        var set = {};
        ids.forEach(function (id) { set[id] = true; });
        var html = htmlAvisoAnoEventos(key, opts || {}, eventos);
        html += '<div><span class="text-xs font-medium text-gray-600">Eventos que entram nesta peça</span>';
        html += '<p class="text-xs text-gray-500 mt-0.5">Desmarque algum evento se ele não deve entrar neste boletim.</p>';
        html += '<div class="grid sm:grid-cols-2 gap-2 mt-2 max-h-44 overflow-y-auto">';
        eventos.forEach(function (ev) {
            var id = Number(ev.id);
            var on = manual ? !!set[id] : true;
            html += '<label class="inline-flex items-start gap-2 text-sm border rounded-lg px-3 py-2 bg-white cursor-pointer">';
            html += '<input type="checkbox" class="bw-peca-evento mt-0.5 rounded border-gray-300 text-indigo-600" data-peca="' + key + '" value="' + id + '"' + (on ? ' checked' : '') + '>';
            html += '<span>' + esc(labelEventoProva(ev)) + '</span></label>';
        });
        html += '</div></div>';
        return html;
    }

    function htmlVersoesConfiguracao() {
        var lista = [];
        try {
            lista = JSON.parse(root.getAttribute('data-config-versoes') || '[]');
        } catch (e) {
            lista = [];
        }
        if (!Array.isArray(lista) || !lista.length) {
            return '<p class="text-xs text-gray-500">Ainda não há versão salva desta configuração.</p>';
        }
        var html = '<div class="space-y-1">';
        html += '<p class="text-xs font-medium text-gray-700">Versões da configuração</p>';
        lista.forEach(function (v) {
            var id = Number(v.id || 0);
            if (id <= 0) return;
            html += '<div class="flex items-center justify-between gap-2 text-xs text-gray-700">';
            html += '<span>ID ' + id + ' · Versão ' + Number(v.versao || 0) + (v.criado_em ? ' · ' + esc(v.criado_em) : '') + (v.usuario_nome ? ' · ' + esc(v.usuario_nome) : '') + '</span>';
            html += '<button type="button" class="js-restaurar-config text-indigo-700 hover:underline" data-versao-id="' + id + '">Recuperar</button>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    function htmlEscopoPecasSelecionadas() {
        var pecas = estado.pecas || [];
        if (!pecas.length) return '';
        var html = '<div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-3 text-xs text-slate-700">';
        html += '<p class="font-semibold text-indigo-950 uppercase tracking-wide mb-2">Eventos usados por peça</p>';
        html += '<div class="space-y-1.5">';
        pecas.forEach(function (key) {
            var opts = (estado.pecas_opcoes && estado.pecas_opcoes[key]) || {};
            var bims = normalizarBimestresPeca(opts.bimestres);
            if (!bims.length && periodoNumeroValido(estado.bimestre)) bims = [Number(estado.bimestre)];
            var btxt = bims.length ? bims.map(function (b) { return b + 'º'; }).join(', ') : 'bimestre da identidade';
            if (key === 'jornada') {
                var jornadas = [];
                bims.forEach(function (b) { jornadas = jornadas.concat(jornadasDoBimestre(b)); });
                var totalJ = estado.jornada_modo === 'selecionadas' ? (estado.jornada_ids || []).length : jornadas.length;
                html += '<p><strong>' + esc(pecaLabel(key)) + ':</strong> ' + esc(btxt) + ' bimestre · ' + totalJ + ' jornada(s)' + (estado.jornada_modo === 'selecionadas' ? ' marcada(s)' : ' encontrada(s)') + '.</p>';
                return;
            }
            var eventos = eventosSelecionaveisPeca(key, opts);
            var total = Number(opts.blocos_ids_manual || 0) === 1 ? normalizarIdsJs(opts.blocos_ids).length : eventos.length;
            html += '<p><strong>' + esc(pecaLabel(key)) + ':</strong> ' + esc(btxt) + ' bimestre · ' + total + ' evento(s)' + (Number(opts.blocos_ids_manual || 0) === 1 ? ' marcado(s)' : ' encontrado(s)') + '.</p>';
        });
        html += '</div></div>';
        return html;
    }

    function htmlJornadaPeca(opts) {
        var bims = normalizarBimestresPeca(opts && opts.bimestres);
        if (!bims.length && periodoNumeroValido(estado.bimestre)) bims = [Number(estado.bimestre)];
        if (estado.jornada_modo !== 'selecionadas') estado.jornada_modo = 'bimestre';
        estado.jornada_bimestres = bims.slice();
        var total = 0;
        bims.forEach(function (b) { total += jornadasDoBimestre(b).length; });
        var rotulo = bims.length ? bims.map(function (b) { return b + 'º'; }).join(', ') : 'bimestre da regra';
        var html = '<div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3">';
        html += '<div><span class="text-xs font-medium text-gray-600">Jornadas vinculadas</span>';
        html += '<p class="text-xs text-gray-500 mt-0.5">Serão usadas as jornadas do ' + esc(rotulo) + ' bimestre, conforme o bimestre da peça. Encontradas: ' + total + '.</p></div>';
        var jornadas = [];
        bims.forEach(function (b) { jornadas = jornadas.concat(jornadasDoBimestre(b)); });
        var byJ = {};
        jornadas.forEach(function (j) { byJ[Number(j.id)] = j; });
        jornadas = Object.keys(byJ).map(function (id) { return byJ[id]; });
        if (jornadas.length) {
            var selJ = {};
            (estado.jornada_ids || []).forEach(function (id) { selJ[Number(id)] = true; });
            var manualJ = estado.jornada_modo === 'selecionadas';
            html += '<div><span class="text-xs font-medium text-gray-600">Jornadas que entram</span>';
            html += '<p class="text-xs text-gray-500 mt-0.5">Desmarque a jornada que não deve contar para esta peça.</p>';
            html += '<div class="grid sm:grid-cols-2 gap-2 mt-2 max-h-44 overflow-y-auto">';
            jornadas.forEach(function (j) {
                var jid = Number(j.id);
                var on = manualJ ? !!selJ[jid] : true;
                var parts = [j.nome || ('Jornada #' + jid)];
                if (j.materia_nome) parts.push(j.materia_nome);
                if (j.bimestre && periodoNumeroValido(j.bimestre)) parts.push(rotuloPeriodoNumero(j.bimestre));
                html += '<label class="inline-flex items-start gap-2 text-sm border rounded-lg px-3 py-2 bg-white cursor-pointer">';
                html += '<input type="checkbox" class="bw-jornada-id mt-0.5 rounded border-gray-300 text-indigo-600" value="' + jid + '"' + (on ? ' checked' : '') + '>';
                html += '<span>' + esc(parts.join(' · ')) + '</span></label>';
            });
            html += '</div></div>';
        }
        html += '<div><span class="text-xs font-medium text-gray-600">Como lançar a nota</span>';
        html += '<p class="text-xs text-gray-500 mt-0.5">Por matéria usa a jornada de cada disciplina. Média única calcula o % geral de conclusão e repete a mesma nota nas matérias marcadas, mesmo nas que não tiveram jornada.</p>';
        html += '<div class="mt-2 flex flex-wrap gap-3 text-sm">';
        var dist = (estado.jornada_distribuicao_notas === 'nota_unica_todas_linhas') ? 'nota_unica_todas_linhas' : 'por_materia';
        html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-jornada-dist" class="bw-jornada-dist" value="por_materia"' + (dist === 'por_materia' ? ' checked' : '') + '> Por matéria</label>';
        html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-jornada-dist" class="bw-jornada-dist" value="nota_unica_todas_linhas"' + (dist === 'nota_unica_todas_linhas' ? ' checked' : '') + '> Média única (mesma nota em todas)</label>';
        html += '</div></div>';
        if (dist === 'nota_unica_todas_linhas') html += htmlMateriasJornadaNotaUnica();
        html += '<div><span class="text-xs font-medium text-gray-600">Pontuação por conclusão</span>';
        html += '<p class="text-xs text-gray-500 mt-0.5">A nota depende de quantas jornadas do bimestre o aluno concluiu.</p>';
        html += '<div class="mt-2 flex flex-wrap gap-3 text-sm">';
        html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-jornada-nota" class="bw-jornada-nota-modo" value="linear"' + ((estado.jornada_nota_modo || 'linear') === 'linear' ? ' checked' : '') + '> Linear (100% = 10)</label>';
        html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-jornada-nota" class="bw-jornada-nota-modo" value="faixas"' + (estado.jornada_nota_modo === 'faixas' ? ' checked' : '') + '> Tabela por faixas</label>';
        html += '</div></div>';
        if (estado.jornada_nota_modo === 'faixas') {
            estado.jornada_faixas = normalizarFaixasJornadaUi(estado.jornada_faixas);
            html += '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">';
            estado.jornada_faixas.forEach(function (f, idx) {
                html += '<label class="flex items-center gap-2 text-sm bg-white border rounded-lg px-2 py-1.5">';
                html += '<span class="text-xs font-medium text-amber-900 min-w-[3.5rem]">' + f.percentual_min + '%</span>';
                var notaFaixa = f.nota;
                if (typeof notaFaixa === 'string') {
                    notaFaixa = String(notaFaixa).trim().replace(',', '.');
                }
                if (notaFaixa !== '' && notaFaixa != null && isFinite(Number(notaFaixa))) {
                    notaFaixa = Number(notaFaixa);
                }
                html += '<input type="number" min="0" step="0.01" class="bw-jornada-faixa-nota flex-1 h-9 border rounded-md px-2 text-sm" data-idx="' + idx + '" value="' + (notaFaixa != null && notaFaixa !== '' ? notaFaixa : '') + '">';
                html += '</label>';
            });
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    function materiasDaJornadaNoEvento() {
        var lista = materiasParaExcecao();
        if (!lista.length) lista = materiasCatalogoDoEscopo();
        var porId = {};
        (lista || []).forEach(function (m) {
            var id = Number(m && m.id);
            if (id > 0) porId[id] = { id: id, nome: m.nome || '' };
        });
        var paisComFilhas = {};
        familiasDoEscopo().forEach(function (f) {
            (f.filhos || []).forEach(function (ch) {
                var id = Number(ch.id);
                if (id > 0) {
                    porId[id] = { id: id, nome: ch.nome || (porId[id] && porId[id].nome) || '' };
                }
            });
            if ((f.filhos || []).length >= 2 && Number(f.pai_id) > 0) {
                paisComFilhas[Number(f.pai_id)] = true;
            }
        });
        return Object.keys(porId).map(function (k) { return porId[k]; }).filter(function (m) {
            return m.id > 0 && m.nome && !paisComFilhas[m.id];
        });
    }

    function selecaoMateriasJornada() {
        var lista = materiasDaJornadaNoEvento();
        var sel = {};
        if (!lista.length) return { lista: [], sel: sel };
        if (estado.jornada_materias_tocada && Array.isArray(estado.jornada_materias_ids)) {
            estado.jornada_materias_ids.forEach(function (id) { sel[Number(id)] = true; });
            return { lista: lista, sel: sel };
        }
        var omit = {};
        (estado.jornada_materias_omitir || []).forEach(function (id) { omit[Number(id)] = true; });
        var ids = [];
        lista.forEach(function (m) {
            var id = Number(m.id);
            if (!omit[id]) {
                sel[id] = true;
                ids.push(id);
            }
        });
        estado.jornada_materias_ids = ids;
        estado.jornada_materias_tocada = true;
        return { lista: lista, sel: sel };
    }

    function htmlMateriasJornadaNotaUnica() {
        var pacote = selecaoMateriasJornada();
        var lista = pacote.lista.slice().sort(function (a, b) {
            return String(a.nome || '').localeCompare(String(b.nome || ''), 'pt-BR');
        });
        var html = '<div><span class="text-xs font-medium text-gray-600">Matérias que recebem a nota</span>';
        html += '<p class="text-xs text-gray-500 mt-0.5">Marque quem entra nesta nota. Uma matéria sem jornada própria também recebe a média única se estiver marcada.</p>';
        if (!lista.length) {
            html += '<p class="text-xs text-gray-500 mt-2">Nenhuma matéria no escopo deste evento.</p></div>';
            return html;
        }
        html += '<div class="flex flex-wrap gap-2 mt-2">';
        html += '<button type="button" id="bw-jornada-materias-todas" class="text-xs px-2 py-1 rounded-md bg-white border border-gray-300 text-gray-700">Marcar todas</button>';
        html += '<button type="button" id="bw-jornada-materias-nenhuma" class="text-xs px-2 py-1 rounded-md bg-white border border-gray-300 text-gray-700">Desmarcar todas</button>';
        html += '</div>';
        html += '<div class="grid sm:grid-cols-2 gap-2 mt-2 max-h-52 overflow-y-auto">';
        lista.forEach(function (m) {
            var id = Number(m.id);
            html += '<label class="inline-flex items-start gap-2 text-sm border rounded-lg px-3 py-2 bg-white cursor-pointer">';
            html += '<input type="checkbox" class="bw-jornada-materia mt-0.5 rounded border-gray-300 text-indigo-600" value="' + id + '"' + (pacote.sel[id] ? ' checked' : '') + '>';
            html += '<span>' + esc(m.nome || ('Matéria #' + id)) + '</span></label>';
        });
        html += '</div></div>';
        return html;
    }

    function lerMateriasJornadaMarcadas() {
        var ids = [];
        document.querySelectorAll('.bw-jornada-materia:checked').forEach(function (c) {
            var id = parseInt(c.value, 10);
            if (id > 0) ids.push(id);
        });
        estado.jornada_materias_ids = ids;
        estado.jornada_materias_tocada = true;
    }

    function tipoNomeFixoPeca(key) {
        var meta = pecaMeta(key);
        var opts = (estado.pecas_opcoes && estado.pecas_opcoes[key]) || {};
        var tipoId = Number(opts.tipo_avaliacao_id || (meta && meta.tipo_avaliacao_id) || 0);
        var nome = '';
        (catalogo.tipos_avaliacao || []).forEach(function (t) {
            if (Number(t.id) === tipoId) nome = t.nome || '';
        });
        if (nome) return nome;
        return (meta && meta.label) ? meta.label : String(key || '');
    }

    function normalizarBimestresPeca(raw) {
        var out = [];
        (Array.isArray(raw) ? raw : []).forEach(function (x) {
            var n = parseInt(x, 10);
            if (periodoNumeroValido(n) && out.indexOf(n) < 0) out.push(n);
        });
        return out;
    }

    function anoLetivoPadraoJs() {
        var ativo = Number((catalogo && catalogo.ano_letivo_ativo) || 0);
        var counts = {};
        (catalogo.eventos_prova || []).forEach(function (ev) {
            var a = Number(ev.ano_letivo || 0);
            if (a >= 2000) counts[a] = (counts[a] || 0) + 1;
        });
        if (ativo > 0 && (counts[ativo] || 0) > 0) return ativo;
        var best = 0;
        Object.keys(counts).forEach(function (a) {
            var n = Number(a);
            if (n > best) best = n;
        });
        if (best > 0) return best;
        if (ativo > 0) return ativo;
        return (new Date()).getFullYear();
    }

    function papelDaPeca(key) {
        var meta = pecaMeta(key);
        var nome = meta ? String(meta.label || '').toLowerCase() : '';
        var ch = meta && meta.chave_quadro ? String(meta.chave_quadro).toLowerCase() : String(key || '');
        if (ch === 'bimestral' || ch === 'prova_bim' || nome.indexOf('bimestral') >= 0 || key === 'bimestral') return 'bimestral';
        if (ch === 'semanal' || nome.indexOf('semanal') >= 0 || key === 'semanal') return 'semanal';
        if (ch === 'enac' || nome.indexOf('enac') >= 0 || key === 'enac') return 'enac';
        return String(key || '');
    }

    function tiposIdsDoPapel(papel) {
        var ids = [];
        (catalogo.tipos_avaliacao || []).forEach(function (t) {
            var nome = String(t.nome || '').toLowerCase();
            var ch = String(t.chave_quadro || '').toLowerCase();
            var ok = false;
            if (papel === 'bimestral') ok = ch === 'bimestral' || ch === 'prova_bim' || nome.indexOf('bimestral') >= 0;
            else if (papel === 'semanal') ok = ch === 'semanal' || nome.indexOf('semanal') >= 0;
            else if (papel === 'enac') ok = ch === 'enac' || nome.indexOf('enac') >= 0;
            if (ok) ids.push(Number(t.id));
        });
        return ids;
    }

    function eventoCasaPeca(ev, key, tipoId) {
        var papel = papelDaPeca(key);
        var tipoEv = Number(ev.tipo_avaliacao_id || 0);
        if (papel === 'semanal') {
            if (tipoId > 0 && tipoEv === tipoId) return true;
            return tipoEv > 0 && tiposIdsDoPapel('semanal').indexOf(tipoEv) >= 0;
        }
        if (papel === 'bimestral' || papel === 'enac') {
            if (tipoId > 0 && tipoEv === tipoId) return true;
            if (tipoEv > 0 && tiposIdsDoPapel(papel).indexOf(tipoEv) >= 0) return true;
            var blobFam = (String(ev.tipo_avaliacao_nome || '') + ' ' + String(ev.chave_quadro || '')).toLowerCase();
            if (papel === 'bimestral' && blobFam.indexOf('bimestral') >= 0) return true;
            if (papel === 'enac' && blobFam.indexOf('enac') >= 0) return true;
            return false;
        }
        // Recuperação Final e Semestral são tipos diferentes: só o id do tipo entra.
        if (tipoId > 0) return tipoEv === tipoId;
        var ch = String(ev.chave_quadro || '').toLowerCase();
        return ch !== '' && ch === papel;
    }

    function eventosDaPecaFiltrados(key, bim, exigirAno) {
        var opts = (estado.pecas_opcoes && estado.pecas_opcoes[key]) || {};
        var meta = pecaMeta(key);
        var tipoMeta = meta ? Number(meta.tipo_avaliacao_id || 0) : 0;
        var tipoId = tipoMeta > 0 ? tipoMeta : Number(opts.tipo_avaliacao_id || 0);
        var ano = Number((estado && estado.ano_letivo) || 0);
        var papel = papelDaPeca(key);
        var bimsMarcados = normalizarBimestresPeca(opts.bimestres);
        if (!bimsMarcados.length && periodoNumeroValido(estado.bimestre)) bimsMarcados = [Number(estado.bimestre)];
        var bimSemCampo = bimsMarcados.length ? Number(bimsMarcados[0]) : Number(bim);
        return (catalogo.eventos_prova || []).filter(function (ev) {
            var bimEv = Number(ev.bimestre) || 0;
            if (papel === 'semanal') {
                if (bimEv !== Number(bim)) return false;
            } else {
                if (bimEv > 0 && bimEv !== Number(bim)) return false;
                if (bimEv <= 0 && Number(bim) !== bimSemCampo) return false;
            }
            var ea = Number(ev.ano_letivo || 0);
            if (exigirAno && ano > 0 && ea > 0 && ea !== ano) {
                var dy = parseInt(String(ev.data_prova || '').slice(0, 4), 10);
                if (dy !== ano) return false;
            }
            return eventoCasaPeca(ev, key, tipoId);
        });
    }

    function eventosDaPecaNoBimestre(key, bim) {
        var noAno = eventosDaPecaFiltrados(key, bim, true);
        if (noAno.length) return noAno;
        return eventosDaPecaFiltrados(key, bim, false);
    }

    function papeisCatalogo() {
        if (catalogo.papeis && catalogo.papeis.length) return catalogo.papeis;
        return [
            { key: 'media', label: 'Entra na média', hint: 'Compõe a nota base com as outras peças neste papel.' },
            { key: 'depois', label: 'Entra depois (média final)', hint: 'Entra numa segunda média, depois da média parcial.' },
            { key: 'so_melhora', label: 'Só melhora', hint: 'Se for menor que a média, a média permanece.' },
            { key: 'substitui', label: 'Substitui se for maior', hint: 'Usa esta nota quando ela for maior que a média (recuperação).' },
            { key: 'exibe', label: 'Só mostra', hint: 'Aparece no boletim, mas não entra no cálculo da média.' }
        ];
    }

    function chavePecaDeComponenteJs(c) {
        if (!c || c.source_type === 'calculado') return null;
        var src = c.source_type || '';
        var cod = String(c.codigo || '').toLowerCase();
        var nome = String(c.nome || '').toLowerCase();
        var blob = cod + ' ' + nome + ' ' + String(c.filtro_titulo || '').toLowerCase();
        var cfg = c.config && typeof c.config === 'object' ? c.config : {};
        var layoutObj = cfg.layout && typeof cfg.layout === 'object' ? cfg.layout : {};
        var layout = String(cfg.layout_type || layoutObj.type || '').toLowerCase();
        var layoutGroup = String(cfg.layout_group || layoutObj.group || '').toLowerCase();
        if (src === 'jornadas' || blob.indexOf('jornada') >= 0) return 'jornada';
        if (/^s[1-8]$/.test(cod)) return 'semanal';
        var tipoIdPeca = Number(cfg.tipo_avaliacao_id || c.tipo_avaliacao_id || 0);
        if (tipoIdPeca > 0) {
            var fromTipo = '';
            (catalogo.pecas || []).forEach(function (p) {
                if (Number(p.tipo_avaliacao_id || 0) === tipoIdPeca) fromTipo = p.key;
            });
            if (fromTipo) return fromTipo;
        }
        var chaveQuadro = String(cfg.chave_quadro || c.chave_quadro || '').toLowerCase().trim();
        if (chaveQuadro === 'semanal') return 'semanal';
        if (chaveQuadro === 'prova_bim' || chaveQuadro === 'bimestral') return 'bimestral';
        if (chaveQuadro === 'enac') return 'enac';
        if (chaveQuadro === 'trabalho' || chaveQuadro === 'trab') return 'trabalho';
        if (chaveQuadro === 'participacao' || chaveQuadro === 'participa' || chaveQuadro === 'part') return 'participacao';
        if (chaveQuadro === 'recuperacao' || chaveQuadro === 'recupera' || chaveQuadro === 'rec') return 'recuperacao';
        if (cod === 'enac' || blob.indexOf('enac') >= 0) return 'enac';
        if (/^s[1-8]$/.test(cod) || blob.indexOf('semanal') >= 0 || layout === 'semana_nq' || layoutGroup === 'quadro_a' || layoutGroup === 'quadro_b') return 'semanal';
        if (cod === 'trab' || blob.indexOf('trabalho') >= 0) return 'trabalho';
        if (cod === 'prova_bim' || blob.indexOf('bimestral') >= 0) return 'bimestral';
        if (cod === 'part' || blob.indexOf('participa') >= 0) return 'participacao';
        if (cod === 'rec' || blob.indexOf('recupera') >= 0) return 'recuperacao';
        var tipoId = Number(cfg.tipo_avaliacao_id || c.tipo_avaliacao_id || 0);
        if (tipoId > 0) {
            var fromCat = '';
            (catalogo.pecas || []).forEach(function (p) {
                if (Number(p.tipo_avaliacao_id || 0) === tipoId) fromCat = p.key;
            });
            return fromCat || ('tipo_' + tipoId);
        }
        return null;
    }

    function inferirPecasDoRascunho(r) {
        var pecas = [];
        var opcoes = {};
        (r && Array.isArray(r.componentes) ? r.componentes : []).forEach(function (c) {
            var key = chavePecaDeComponenteJs(c);
            if (!key) return;
            if (pecas.indexOf(key) < 0) pecas.push(key);
            if (!opcoes[key]) opcoes[key] = pecasOpcoesPadraoJs(key);
            opcoes[key].calc_type = c.calc_type || opcoes[key].calc_type;
            opcoes[key].materia_unica = c.materia_unica ? 1 : 0;
            opcoes[key].materia_unica_modo = c.materia_unica_modo === 'media' ? 'media' : 'soma';
            opcoes[key].usar_percentual = c.usar_percentual ? 1 : 0;
            var cfg = c.config && typeof c.config === 'object' ? c.config : {};
            if (cfg.tipo_avaliacao_id) opcoes[key].tipo_avaliacao_id = Number(cfg.tipo_avaliacao_id);
            if (cfg.papel_wizard) opcoes[key].papel = cfg.papel_wizard;
            if (cfg.prova_bimestres) opcoes[key].bimestres = normalizarBimestresPeca(cfg.prova_bimestres);
            if (Array.isArray(c.blocos_ids) && (c.blocos_ids.length || cfg.blocos_ids_manual)) {
                opcoes[key].blocos_ids = normalizarIdsJs(c.blocos_ids);
                opcoes[key].blocos_ids_manual = 1;
            }
        });
        return { pecas: pecas, pecas_opcoes: opcoes };
    }

    function aplicarPresetNosPapeis(preset) {
        if (!estado || !estado.pecas_opcoes) return;
        var pecas = estado.pecas || [];
        var alvoDepois = pecas.filter(function (k) {
            var p = (estado.pecas_opcoes[k] || {}).papel;
            return p === 'depois' || p === 'so_melhora';
        });
        if (!alvoDepois.length && pecas.indexOf('enac') >= 0) alvoDepois = ['enac'];
        pecas.forEach(function (k) {
            if (!estado.pecas_opcoes[k]) estado.pecas_opcoes[k] = pecasOpcoesPadraoJs(k);
            var atual = estado.pecas_opcoes[k].papel;
            if (atual === 'substitui' || atual === 'exibe') return;
            if (preset === 'media_simples') {
                if (atual === 'depois' || atual === 'so_melhora') estado.pecas_opcoes[k].papel = 'media';
            } else if (preset === 'parcial_depois_final') {
                estado.pecas_opcoes[k].papel = alvoDepois.indexOf(k) >= 0 ? 'depois' : 'media';
            } else if (preset === 'enac_so_melhora') {
                estado.pecas_opcoes[k].papel = alvoDepois.indexOf(k) >= 0 ? 'so_melhora' : 'media';
            }
        });
    }

    function syncPresetDosPapeis() {
        if (!estado) return;
        var papeis = (estado.pecas || []).map(function (k) {
            return ((estado.pecas_opcoes && estado.pecas_opcoes[k]) || {}).papel || 'media';
        });
        var temDepois = papeis.indexOf('depois') >= 0;
        var temMelhora = papeis.indexOf('so_melhora') >= 0;
        if (temMelhora && !temDepois) estado.formula_preset = 'enac_so_melhora';
        else if (temDepois) estado.formula_preset = 'parcial_depois_final';
        else estado.formula_preset = 'media_simples';
    }

    function marcarEdicaoManual() {
        if (!estado) return;
        if (estado.origem === 'formulario' || estado.origem === 'chat' || estado.origem === 'modelo' || estado.origem === 'clonar') {
            estado.origem = 'zero';
            estado.rascunho_preservado = null;
        }
    }

    function materiaUnicaDoRascunho(r) {
        return (r && Array.isArray(r.componentes) ? r.componentes : []).some(function (c) {
            return c && c.source_type !== 'calculado' && !!c.materia_unica;
        }) ? 1 : 0;
    }

    function materiaUnicaModoDoRascunho(r) {
        var comps = r && Array.isArray(r.componentes) ? r.componentes : [];
        for (var i = 0; i < comps.length; i++) {
            var c = comps[i];
            if (c && c.source_type !== 'calculado' && c.materia_unica) {
                return c.materia_unica_modo === 'media' ? 'media' : 'soma';
            }
        }
        return 'soma';
    }

    function faixasJornadaPadrao() {
        return [
            { percentual_min: 100, nota: 10 },
            { percentual_min: 90, nota: 9.5 },
            { percentual_min: 80, nota: 9 },
            { percentual_min: 70, nota: 8.5 },
            { percentual_min: 60, nota: 8 },
            { percentual_min: 50, nota: 7.5 },
            { percentual_min: 40, nota: 7 },
            { percentual_min: 30, nota: 6.5 },
            { percentual_min: 20, nota: 6 },
            { percentual_min: 10, nota: 5.5 }
        ];
    }

    /** Garante 100%…10% mesmo em regras antigas sem a faixa de 100. */
    function normalizarFaixasJornadaUi(raw) {
        var base = faixasJornadaPadrao();
        if (!Array.isArray(raw) || !raw.length) {
            return base;
        }
        var map = {};
        raw.forEach(function (it) {
            if (!it || typeof it !== 'object') {
                return;
            }
            var p = Number(it.percentual_min);
            if (!Number.isFinite(p)) {
                return;
            }
            p = Math.round(p);
            if (p < 0 || p > 100) {
                return;
            }
            if (it.nota === '' || it.nota === null || typeof it.nota === 'undefined') {
                return;
            }
            var n = typeof it.nota === 'number' ? it.nota : parseFloat(String(it.nota).replace(',', '.'));
            if (!Number.isFinite(n)) {
                return;
            }
            map[p] = n;
        });
        return base.map(function (b) {
            return {
                percentual_min: b.percentual_min,
                nota: Object.prototype.hasOwnProperty.call(map, b.percentual_min) ? map[b.percentual_min] : b.nota
            };
        });
    }

    function jornadasDoBimestre(bim) {
        var ano = Number((estado && estado.ano_letivo) || 0);
        var todas = (catalogo.jornadas || []).filter(function (j) {
            return Number(j.bimestre) === Number(bim);
        });
        if (!(ano > 0)) return todas;
        var noAno = todas.filter(function (j) {
            var ja = Number(j.ano_letivo || 0);
            return !(ja > 0 && ja !== ano);
        });
        return noAno.length ? noAno : todas;
    }

    function estadoVazio() {
        var ano = anoLetivoPadraoJs();
        return {
            passo: 'inicio',
            origem: 'zero',
            modelo_key: '',
            clonar_regra_id: null,
            modo: 'criar',
            regra_id: regraId > 0 ? regraId : null,
            nome: '',
            codigo: '',
            ano_letivo: ano,
            bimestre: 1,
            exibir_em: 'notas',
            finalidade: 'oficial',
            boletim_id: 0,
            grupo_regras_notas_id: 0,
            nota_minima_aprovacao: 6,
            nota_minima_origem: 'padrao',
            nota_minima_regra_nome: '',
            round_mode: 'none',
            decimal_places: 2,
            pecas: [],
            pecas_opcoes: {},
            materia_unica: 0,
            materia_unica_modo: 'soma',
            materia_unica_tocada: false,
            formula_preset: 'media_simples',
            formula_custom: '',
            formula_tokens: [],
            formulas_blocos: {},
            formulas_materias_blocos: {},
            nomes_blocos: {},
            escalas_blocos: {},
            nomes_limpos: {},
            bloco_calc: '',
            materia_calc: 0,
            blocos_calc: [],
            colunas_ordem: [],
            colunas_ocultas: [],
            linhas_layout: [],
            fontes_bimestres: { 1: 0, 2: 0, 3: 0, 4: 0 },
            fontes_faltas: { 1: 0, 2: 0, 3: 0, 4: 0 },
            demonstrativos_ids: [],
            eventos_avaliativos_ids: [],
            eventos_avaliativos_subs: {},
            cabecalhos_manuais: 0,
            titulos_periodo_ok: {},
            quadro_chips_manuais: 0,
            data_inicio: '',
            data_fim: '',
            jornada_modo: 'bimestre',
            jornada_ids: [],
            jornada_bimestres: [],
            jornada_nota_modo: 'linear',
            jornada_distribuicao_notas: 'por_materia',
            jornada_materias_ids: [],
            jornada_materias_tocada: false,
            jornada_faixas: faixasJornadaPadrao(),
            series_ids: [],
            turmas_ids: [],
            materias_ids: [],
            aluno_preview_id: 0,
            grupo_linha: grupoLinhaPadrao(),
            rascunho_preservado: null
        };
    }

    function garantirEstado() {
        if (!estado) estado = estadoVazio();
        if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
        if (!estado.grupo_linha.aplicar_em) estado.grupo_linha.aplicar_em = 'boletim';
        if (estado.grupo_linha.agrupamento_id === undefined || estado.grupo_linha.agrupamento_id === null) {
            estado.grupo_linha.agrupamento_id = 0;
        }
        if (estado.grupo_linha.arredondamento !== 'filhas' && estado.grupo_linha.arredondamento !== 'mae') {
            estado.grupo_linha.arredondamento = 'todos';
        }
        estado.grupo_linha.modos = modosGrupoComoObjeto(estado.grupo_linha.modos);
        if (estado.grupo_linha.modo !== 'soma') estado.grupo_linha.modo = 'media';
        if (!estado.finalidade) estado.finalidade = 'oficial';
        if (estado.boletim_id == null) estado.boletim_id = 0;
        if (estado.grupo_regras_notas_id == null) estado.grupo_regras_notas_id = 0;
        // Não zerar visão de impressão do boletim (Montar quadro / quadro livre / SM).
        var layoutBoletimCtx = Number(root.getAttribute('data-layout-boletim-id') || 0);
        var impressaoCtx = retornoFluxo === 'boletim-layout'
            || layoutBoletimCtx > 0
            || estado.origem === 'quadro-impressao'
            || estado.origem === 'santa-maria'
            || estado.origem === 'composto'
            || !!estado.quadro_livre
            || !!estado.modelo_santa_maria
            || Number(estado.layout_boletim_id || 0) > 0
            || estado.exibir_em === 'boletim';
        if (impressaoCtx) {
            estado.exibir_em = 'boletim';
            if (layoutBoletimCtx > 0 && !Number(estado.layout_boletim_id || 0)) {
                estado.layout_boletim_id = layoutBoletimCtx;
            }
            var layoutAtual = Number(estado.layout_boletim_id || layoutBoletimCtx || 0);
            // layout_boletim_id ≠ modelo de boletim (cadastro)
            if (layoutAtual > 0 && Number(estado.boletim_id || 0) === layoutAtual) {
                estado.boletim_id = 0;
            }
        } else {
            estado.exibir_em = 'notas';
        }
        if (estado.materia_unica == null) estado.materia_unica = 0;
        if (estado.materia_unica_modo !== 'media') estado.materia_unica_modo = 'soma';
        if (estado.materia_unica_tocada == null) estado.materia_unica_tocada = false;
        if (!estado.jornada_nota_modo) estado.jornada_nota_modo = 'linear';
        if (estado.jornada_distribuicao_notas !== 'nota_unica_todas_linhas') {
            estado.jornada_distribuicao_notas = 'por_materia';
        }
        if (!Array.isArray(estado.jornada_materias_ids)) estado.jornada_materias_ids = [];
        if (estado.jornada_materias_tocada == null) estado.jornada_materias_tocada = false;
        if ((estado.pecas || []).indexOf('jornada') >= 0 && estado.jornada_modo === 'todas') estado.jornada_modo = 'bimestre';
        if (!Array.isArray(estado.formula_tokens)) estado.formula_tokens = [];
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        if (!estado.formulas_materias_blocos || typeof estado.formulas_materias_blocos !== 'object' || Array.isArray(estado.formulas_materias_blocos)) {
            estado.formulas_materias_blocos = {};
        } else {
            Object.keys(estado.formulas_materias_blocos).forEach(function (cod) {
                mapaExcecoesBloco(cod, false);
            });
        }
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!estado.escalas_blocos || typeof estado.escalas_blocos !== 'object' || Array.isArray(estado.escalas_blocos)) {
            estado.escalas_blocos = {};
        }
        if (!Array.isArray(estado.blocos_calc)) estado.blocos_calc = [];
        if (estado.bloco_calc == null) estado.bloco_calc = '';
        if (estado.materia_calc == null) estado.materia_calc = 0;
        estado.aluno_preview_id = Number(estado.aluno_preview_id || 0) || 0;
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        if (!Array.isArray(estado.colunas_ocultas)) estado.colunas_ocultas = [];
        if (!Array.isArray(estado.linhas_layout)) estado.linhas_layout = [];
        if (!Array.isArray(estado.demonstrativos_ids)) estado.demonstrativos_ids = [];
        if (!Array.isArray(estado.eventos_avaliativos_ids)) estado.eventos_avaliativos_ids = [];
        if (!estado.eventos_avaliativos_subs || typeof estado.eventos_avaliativos_subs !== 'object' || Array.isArray(estado.eventos_avaliativos_subs)) {
            estado.eventos_avaliativos_subs = {};
        }
        if (estado.cabecalhos_manuais == null) estado.cabecalhos_manuais = 0;
        if (!estado.titulos_periodo_ok || typeof estado.titulos_periodo_ok !== 'object' || Array.isArray(estado.titulos_periodo_ok)) {
            estado.titulos_periodo_ok = {};
        }
        if (estado.quadro_chips_manuais == null) estado.quadro_chips_manuais = 0;
        // Reabrir layout já montado: não zera chips nem títulos do período.
        if (Array.isArray(estado.linhas_layout) && estado.linhas_layout.length) {
            estado.cabecalhos_manuais = 1;
            estado.quadro_chips_manuais = 1;
        }
        if (!estado.quadro_chips_manuais && Number(estado.regra_id || 0) > 0
            && ((estado.colunas_ordem && estado.colunas_ordem.length) || (estado.linhas_layout && estado.linhas_layout.length))) {
            estado.quadro_chips_manuais = 1;
        }
        if (!estado.fontes_bimestres || typeof estado.fontes_bimestres !== 'object') {
            estado.fontes_bimestres = { 1: 0, 2: 0, 3: 0, 4: 0 };
        } else {
            [1, 2, 3, 4].forEach(function (b) {
                estado.fontes_bimestres[b] = Number(estado.fontes_bimestres[b] || estado.fontes_bimestres[String(b)] || 0) || 0;
            });
        }
        if (!estado.fontes_faltas || typeof estado.fontes_faltas !== 'object') {
            estado.fontes_faltas = { 1: 0, 2: 0, 3: 0, 4: 0 };
        } else {
            [1, 2, 3, 4].forEach(function (b) {
                estado.fontes_faltas[b] = Number(estado.fontes_faltas[b] || estado.fontes_faltas[String(b)] || 0) || 0;
            });
        }
        if (estado.formula_custom == null) estado.formula_custom = '';
        if (!estado.pecas_opcoes || typeof estado.pecas_opcoes !== 'object') estado.pecas_opcoes = {};
        (estado.pecas || []).forEach(function (k) {
            if (!estado.pecas_opcoes[k]) estado.pecas_opcoes[k] = pecasOpcoesPadraoJs(k);
            if (!Array.isArray(estado.pecas_opcoes[k].bimestres)) {
                estado.pecas_opcoes[k].bimestres = [];
            } else {
                estado.pecas_opcoes[k].bimestres = normalizarBimestresPeca(estado.pecas_opcoes[k].bimestres);
            }
            estado.pecas_opcoes[k].blocos_ids = normalizarIdsJs(estado.pecas_opcoes[k].blocos_ids);
            estado.pecas_opcoes[k].blocos_ids_manual = Number(estado.pecas_opcoes[k].blocos_ids_manual || 0) === 1 ? 1 : 0;
        });
        var anoOrigem = Number(root.getAttribute('data-ano-letivo') || 0);
        var bimOrigem = Number(root.getAttribute('data-bimestre') || 0);
        var bolOrigem = Number(root.getAttribute('data-boletim-id') || 0);
        var layoutOrigem = Number(root.getAttribute('data-layout-boletim-id') || 0);
        // data-boletim-id ≠ layout de impressão
        if (bolOrigem > 0 && layoutOrigem > 0 && bolOrigem === layoutOrigem) bolOrigem = 0;
        var origemExplicita = anoOrigem >= 2000 && anoOrigem <= 2100 && bolOrigem > 0 && estado.modo !== 'editar';
        if (origemExplicita && !estado.contexto_origem_aplicado) {
            estado.contexto_origem_aplicado = true;
            estado.ano_alinhado_eventos = true;
            estado.ano_letivo = anoOrigem;
            estado.boletim_id = bolOrigem;
            if (bimOrigem > 0) estado.bimestre = bimOrigem;
            if (estado.nome_automatico !== false) {
                var nomeCtx = nomePadraoEventoJs(estado.ano_letivo, estado.bimestre);
                var nomeAtual = String(estado.nome || '').trim();
                if (nomeAtual === '' || nomeAtual === nomeCtx || nomePareceSugestao(nomeAtual)) {
                    estado.nome = nomeCtx;
                    estado.nome_automatico = true;
                }
            }
        } else if (!estado.ano_alinhado_eventos && estado.modo !== 'editar') {
            estado.ano_alinhado_eventos = true;
            var anoAtual = Number(estado.ano_letivo || 0);
            var nAtual = 0;
            (catalogo.eventos_prova || []).forEach(function (ev) {
                if (Number(ev.ano_letivo || 0) === anoAtual) nAtual++;
            });
            var padrao = anoLetivoPadraoJs();
            if (anoAtual > 0 && nAtual === 0 && padrao > 0 && padrao !== anoAtual && anoOrigem <= 0) {
                var nomeAntesAlinhar = String(estado.nome || '').trim();
                var sugestaoAntes = nomePadraoEventoJs(anoAtual, estado.bimestre);
                estado.ano_letivo = padrao;
                if (estado.nome_automatico !== false && (nomeAntesAlinhar === '' || nomeAntesAlinhar === sugestaoAntes || nomePareceSugestao(nomeAntesAlinhar))) {
                    estado.nome = nomePadraoEventoJs(estado.ano_letivo, estado.bimestre);
                    estado.nome_automatico = true;
                }
            }
        }
        if (!estado.boletim_id && catalogo.boletins && catalogo.boletins.length === 1
            && !(estado.exibir_em === 'boletim' || estado.origem === 'quadro-impressao' || estado.origem === 'santa-maria' || estado.quadro_livre || estado.modelo_santa_maria)) {
            estado.boletim_id = Number(catalogo.boletins[0].id || 0) || 0;
        }
        if (estado.nome_automatico !== true && estado.nome_automatico !== false) {
            var editandoSalvo = estado.modo === 'editar' || Number(estado.regra_id || regraId || 0) > 0;
            var sugeridoIni = nomePadraoEventoJs(estado.ano_letivo, estado.bimestre);
            var nomeIni = String(estado.nome || '').trim();
            if (nomeIni === '') {
                if (!editandoSalvo) {
                    estado.nome = sugeridoIni;
                    estado.nome_automatico = true;
                }
            } else if (nomeIni === sugeridoIni) {
                estado.nome_automatico = true;
            } else if (!editandoSalvo && nomePareceSugestao(nomeIni)) {
                estado.nome = sugeridoIni;
                estado.nome_automatico = true;
            } else if (editandoSalvo) {
                estado.nome_automatico = false;
            }
        }
        removerMuAutomaticaDoEstado();
        zerarFormulasDemonstrativoNotas();
        window.boletimWizardEstadoAtual = function () { return estado; };
        return estado;
    }

    function pecaLabel(key) {
        var nomes = {
            media_sem: 'Média Sem',
            media_bim: 'Média Bim',
            media_final: 'Resultado Final',
            prova_bim: 'Prova Bim',
            trab: 'Trab',
            part: 'Part',
            rec: 'Rec'
        };
        if (nomes[key]) return nomes[key];
        if (key === 'jornada') return 'Jornada do aluno';
        var m = pecaMeta(key);
        return (m && m.label) ? m.label : String(key || '');
    }

    function eventosFormulaDoPeriodo() {
        var ano = Number((estado && estado.ano_letivo) || 0);
        var bim = Number((estado && estado.bimestre) || 0);
        return (catalogo.eventos_avaliativos || []).filter(function (ev) {
            if (!ev || !String(ev.sigla || '').trim()) return false;
            if (ano > 0 && Number(ev.ano_letivo || 0) !== ano) return false;
            var eb = Number(ev.bimestre || 0);
            if (bim <= 0 || eb === bim) return true;
            if (bim <= 2 && (eb === 81 || eb === 90)) return true;
            if (bim >= 3 && bim <= 4 && (eb === 82 || eb === 90)) return true;
            return false;
        });
    }

    function siglaDoEvento(ev) {
        return String((ev && ev.sigla) || '').trim();
    }

    function abrevSubevento(ev, indice) {
        var sigla = siglaDoEvento(ev);
        if (!sigla) return '';
        var subs = (ev && ev.subeventos) || [];
        if (subs.length <= 1) return sigla;
        return sigla + String(indice + 1);
    }

    function prioridadeEventoSigla(ev) {
        var ano = Number((estado && estado.ano_letivo) || 0);
        var bim = Number((estado && estado.bimestre) || 0);
        var p = 0;
        if (ano > 0 && Number(ev.ano_letivo || 0) === ano) p += 2;
        if (bim > 0 && Number(ev.bimestre || 0) === bim) p += 2;
        return p;
    }

    function siglaPeloTexto(rotulo) {
        var alvo = normalizarExprFormula(rotulo);
        if (!alvo) return '';
        var melhor = '';
        var peso = -1;
        (catalogo.eventos_avaliativos || []).forEach(function (ev) {
            if (!siglaDoEvento(ev)) return;
            var p = prioridadeEventoSigla(ev);
            if (normalizarExprFormula(ev.nome) === alvo && p >= peso) {
                melhor = siglaDoEvento(ev);
                peso = p + 1;
            }
            ((ev && ev.subeventos) || []).forEach(function (sub, i) {
                if (normalizarExprFormula(sub && sub.titulo) !== alvo || p < peso) return;
                if (normalizarExprFormula(ev.nome) === alvo) return;
                melhor = abrevSubevento(ev, i);
                peso = p;
            });
        });
        return melhor;
    }

    function siglaPeloCodigoColuna(value) {
        var m = String(value || '').match(/^(?:sev|rcv)_?(\d+)$/i);
        if (!m) return '';
        var blocoId = Number(m[1]);
        var melhor = '';
        var peso = -1;
        (catalogo.eventos_avaliativos || []).forEach(function (ev) {
            if (!siglaDoEvento(ev)) return;
            var p = prioridadeEventoSigla(ev);
            ((ev && ev.subeventos) || []).forEach(function (sub, i) {
                if (Number(sub && sub.id) !== blocoId || p < peso) return;
                melhor = abrevSubevento(ev, i);
                peso = p;
            });
        });
        return melhor;
    }

    function siglaFormula(value, label) {
        return siglaPeloCodigoColuna(value) || siglaPeloTexto(label) || siglaPeloTexto(pecaLabel(value));
    }

    function labelToken(tok) {
        if (!tok) return '';
        if (tok.type === 'fn') {
            if (tok.value === 'min') return 'menor (';
            if (tok.value === 'chamada' || tok.value === 'substituta') return 'nota substituta (';
            if (tok.value === 'composicao') return 'média c/ rec (';
            return 'maior (';
        }
        if (tok.type === 'op' && tok.value === '*') return '×';
        if (tok.type === 'op' && tok.value === '/') return '÷';
        if (tok.type === 'op' && tok.value === '-') return '−';
        if (tok.type === 'peca') {
            var doSub = tituloSubeventoDaColuna(tok.value);
            if (doSub) return doSub;
            var sigla = siglaFormula(tok.value, tok.label);
            if (sigla) return sigla;
            return tituloColuna(tok.value) || tok.label || pecaLabel(tok.value);
        }
        return tok.label || tok.value;
    }

    function formulaTextoLegivel(tokens) {
        var parts = [];
        (tokens || []).forEach(function (t) {
            if (!t) return;
            parts.push(labelToken(t));
        });
        return parts.join(' ').replace(/\( /g, '(').replace(/ \)/g, ')').replace(/ ,/g, ',').trim();
    }

    function operadoresFormula() {
        return [
            { type: 'op', value: '+', label: '+' },
            { type: 'op', value: '-', label: '−' },
            { type: 'op', value: '*', label: '×' },
            { type: 'op', value: '/', label: '÷' },
            { type: 'op', value: '(', label: '(' },
            { type: 'op', value: ')', label: ')' },
            { type: 'op', value: ',', label: ',' },
            { type: 'fn', value: 'max', label: 'maior (' },
            { type: 'fn', value: 'min', label: 'menor (' },
            { type: 'fn', value: 'chamada', label: 'nota substituta (' },
            { type: 'fn', value: 'composicao', label: 'média c/ rec (' }
        ];
    }

    function codigosColunaFormula() {
        var set = {};
        function add(v) {
            v = String(v || '');
            if (!v || v === '_semanal') return;
            set[v] = true;
            var quadro = pecaCodigoQuadro(v);
            if (quadro) set[quadro] = true;
        }
        if (!estado) return set;
        (estado.pecas || []).forEach(add);
        (estado.blocos_calc || []).forEach(add);
        Object.keys(estado.formulas_blocos || {}).forEach(add);
        Object.keys(estado.nomes_blocos || {}).forEach(add);
        ['media_sem', 'media_bim', 'media_final', 'prova_bim', 'enac', 'jornada', 'rec', 'faltas'].forEach(add);
        var pv = (typeof previewEfetivo === 'function') ? previewEfetivo() : null;
        ((pv && pv.colunas) || []).forEach(function (c) { if (c) add(c.codigo); });
        ((pv && pv.tabelas) || []).forEach(function (t) {
            (t.outras || []).concat(t.semanas || []).forEach(function (c) { if (c) add(c.codigo); });
        });
        ((typeof rascunhoAtual !== 'undefined' && rascunhoAtual && rascunhoAtual.componentes) || []).forEach(function (c) {
            if (c) add(c.codigo);
        });
        return set;
    }

    function filtrarTokensOrfaos() {
        if (!estado) return;
        var conhecidos = codigosColunaFormula();
        function pecaTokenOk(v) {
            v = String(v || '');
            return !!(conhecidos[v] || conhecidos[pecaCodigoQuadro(v)]);
        }
        function filtrarLista(toks) {
            var out = (toks || []).filter(function (t) {
                if (!t || t.type !== 'peca') return true;
                return pecaTokenOk(t.value);
            });
            var temPeca = out.some(function (t) { return t && t.type === 'peca'; });
            return temPeca ? envolverSomaAntesDeDivisao(out) : [];
        }
        if (Array.isArray(estado.formula_tokens)) {
            estado.formula_tokens = filtrarLista(estado.formula_tokens);
        }
        if (estado.formulas_blocos && typeof estado.formulas_blocos === 'object') {
            Object.keys(estado.formulas_blocos).forEach(function (cod) {
                estado.formulas_blocos[cod] = filtrarLista(estado.formulas_blocos[cod]);
            });
        }
        if (estado.formulas_materias_blocos && typeof estado.formulas_materias_blocos === 'object') {
            Object.keys(estado.formulas_materias_blocos).forEach(function (cod) {
                var por = estado.formulas_materias_blocos[cod];
                if (!por || typeof por !== 'object') return;
                Object.keys(por).forEach(function (mid) {
                    por[mid] = filtrarLista(por[mid]);
                });
            });
        }
        estado.formula_custom = compilarTokensPreview(estado.formula_tokens || []);
    }

    function compilarTokensPreview(tokens) {
        var parts = [];
        (tokens || []).forEach(function (t) {
            if (!t) return;
            if (t.type === 'fn') {
                var fn = String(t.value || '').toLowerCase();
                if (fn === 'min') parts.push('min(');
                else if (fn === 'chamada' || fn === 'substituta') parts.push('chamada(');
                else if (fn === 'composicao') parts.push('composicao(');
                else parts.push('max(');
            } else if (t.type === 'peca') parts.push(t.value);
            else parts.push(t.value);
        });
        var exp = parts.join(' ').replace(/\( /g, '(').replace(/ \)/g, ')').replace(/ ,/g, ',');
        return exp.replace(/\b(max|min|chamada|substituta|composicao)\(\s+/g, '$1(').trim();
    }

    function envolverSomaAntesDeDivisao(tokens) {
        tokens = (tokens || []).slice();
        var n = tokens.length;
        if (n < 5) return tokens;
        var ultimo = tokens[n - 1];
        var div = tokens[n - 2];
        if (!ultimo || ultimo.type !== 'num' || !div || div.type !== 'op') return tokens;
        if (div.value === '+') {
            div = { type: 'op', value: '/', label: '÷' };
            tokens[n - 2] = div;
        }
        if (div.value !== '/') return tokens;
        var left = tokens.slice(0, n - 2);
        if (tokensJaEnvolvidosPorParenteses(left) || !tokensSaoSomaSimples(left)) return tokens;
        return [{ type: 'op', value: '(', label: '(' }].concat(left, [{ type: 'op', value: ')', label: ')' }, div, ultimo]);
    }

    function tokensJaEnvolvidosPorParenteses(left) {
        var len = (left || []).length;
        if (len < 3) return false;
        if (!left[0] || left[0].type !== 'op' || left[0].value !== '(') return false;
        if (!left[len - 1] || left[len - 1].type !== 'op' || left[len - 1].value !== ')') return false;
        var depth = 0;
        for (var i = 0; i < len; i++) {
            var t = left[i];
            if (!t || t.type !== 'op') continue;
            if (t.value === '(') depth++;
            else if (t.value === ')') {
                depth--;
                if (depth < 0) return false;
                if (depth === 0 && i < len - 1) return false;
            }
        }
        return depth === 0;
    }

    function tokensSaoSomaSimples(left) {
        var depth = 0;
        var somas = 0;
        var esperandoTermo = true;
        for (var i = 0; i < (left || []).length; i++) {
            var t = left[i];
            if (!t) return false;
            var type = t.type;
            var v = String(t.value || '');
            if (type === 'fn') {
                if (depth === 0 && !esperandoTermo) return false;
                depth++;
                esperandoTermo = false;
                continue;
            }
            if (type === 'op' && v === '(') {
                if (depth === 0 && !esperandoTermo) return false;
                depth++;
                esperandoTermo = true;
                continue;
            }
            if (type === 'op' && v === ')') {
                depth--;
                if (depth < 0) return false;
                esperandoTermo = false;
                continue;
            }
            if (depth > 0) continue;
            if (type === 'op' && v === '+') {
                if (esperandoTermo) return false;
                somas++;
                esperandoTermo = true;
                continue;
            }
            if (type === 'op') return false;
            if (type === 'peca' || type === 'num') {
                if (!esperandoTermo) return false;
                esperandoTermo = false;
                continue;
            }
            return false;
        }
        return !esperandoTermo && somas >= 1 && depth === 0;
    }

    function tokensMediaDasPecas() {
        var pecas = (estado && estado.pecas) || [];
        if (!pecas.length) return [];
        var toks = [{ type: 'op', value: '(', label: '(' }];
        pecas.forEach(function (k, i) {
            if (i > 0) toks.push({ type: 'op', value: '+', label: '+' });
            toks.push({ type: 'peca', value: k, label: pecaLabel(k) });
        });
        toks.push({ type: 'op', value: ')', label: ')' });
        if (pecas.length > 1) {
            toks.push({ type: 'op', value: '/', label: '÷' });
            toks.push({ type: 'num', value: String(pecas.length), label: String(pecas.length) });
        }
        return toks;
    }

    function tokensMaiorDuas() {
        var pecas = (estado && estado.pecas) || [];
        if (pecas.length < 2) return tokensMediaDasPecas();
        return [
            { type: 'fn', value: 'max', label: 'maior (' },
            { type: 'peca', value: pecas[0], label: pecaLabel(pecas[0]) },
            { type: 'op', value: ',', label: ',' },
            { type: 'peca', value: pecas[1], label: pecaLabel(pecas[1]) },
            { type: 'op', value: ')', label: ')' }
        ];
    }

    function sufixoColunaSm(cod) {
        var c = String(cod || '').toLowerCase();
        var m = c.match(/_(pp|pu|pe|ch|mu|fal)$/);
        if (m) return m[1];
        if (/^(pp|pu|pe|ch|mu|fal)$/.test(c)) return c;
        return '';
    }

    /** Modelo: nota substituta(nota1, nota2, substituta) — média das bases; substituta preenche a 1ª ausente. */
    function tokensMediaComSubstituta() {
        var cols = [];
        function pushCol(c) {
            c = String(c || '').toLowerCase();
            if (!c || c === '_semanal') return;
            if (ehCodigoBlocoCalc(c)) return;
            if (cols.indexOf(c) < 0) cols.push(c);
        }
        (estado && estado.colunas_ordem || []).forEach(pushCol);
        if (cols.length < 2) {
            (((rascunhoAtual || {}).componentes) || []).forEach(function (comp) {
                pushCol(comp && comp.codigo);
            });
        }
        if (cols.length < 2) {
            ((estado && estado.pecas) || []).forEach(pushCol);
        }
        if (cols.length < 2) return tokensMediaDasPecas();

        var porSuf = {};
        cols.forEach(function (c) {
            var s = sufixoColunaSm(c);
            if (s && !porSuf[s]) porSuf[s] = c;
        });
        var bases = [];
        var subst = '';
        if (porSuf.pp || porSuf.pu) {
            if (porSuf.pp) bases.push(porSuf.pp);
            if (porSuf.pu) bases.push(porSuf.pu);
            subst = porSuf.ch || '';
        }
        if (!subst) {
            cols.forEach(function (c) {
                if (sufixoColunaSm(c) === 'ch' || /(^|_)ch$/.test(c) || /segunda.?chamada|2a.?chamada/.test(c)) {
                    if (!subst) subst = c;
                }
            });
        }
        if (!bases.length || !subst) {
            // Fallback: últimas colunas — bases = todas menos a última, substituta = última.
            subst = cols[cols.length - 1];
            bases = cols.slice(0, cols.length - 1).filter(function (c) {
                return c !== subst && sufixoColunaSm(c) !== 'pe' && sufixoColunaSm(c) !== 'fal';
            });
            if (!bases.length) bases = cols.slice(0, Math.max(1, cols.length - 1));
        }
        bases = bases.filter(function (c) { return c && c !== subst; });
        if (!bases.length) return tokensMediaDasPecas();

        var toks = [{ type: 'fn', value: 'chamada', label: 'nota substituta (' }];
        bases.forEach(function (cod, idx) {
            if (idx > 0) toks.push({ type: 'op', value: ',', label: ',' });
            toks.push({ type: 'peca', value: cod, label: tituloColuna(cod) || pecaLabel(cod) || cod });
        });
        toks.push({ type: 'op', value: ',', label: ',' });
        toks.push({ type: 'peca', value: subst, label: tituloColuna(subst) || pecaLabel(subst) || subst });
        toks.push({ type: 'op', value: ')', label: ')' });
        return toks;
    }

    /**
     * Modelo genérico: composicao((média1+média2)÷2, recuperação).
     * Sem recuperação → só a média; com recuperação → (média+rec)÷2.
     */
    function tokensMediaComRec() {
        var bases = [];
        var rec = '';
        function pushBase(c) {
            c = String(c || '').toLowerCase();
            if (!c || bases.indexOf(c) >= 0) return;
            bases.push(c);
        }
        var blocos = (estado && estado.blocos_calc) || [];
        var preferidos = ['media_bim', 'media_final', 'media_1', 'media_2'];
        preferidos.forEach(function (cod) {
            if (blocos.indexOf(cod) >= 0 || (estado.formulas_blocos && estado.formulas_blocos[cod])) {
                pushBase(cod);
            }
        });
        if (bases.length < 2) {
            blocos.forEach(function (cod) {
                if (bases.length >= 2) return;
                pushBase(cod);
            });
        }
        var cols = [];
        function pushCol(c) {
            c = String(c || '').toLowerCase();
            if (!c || c === '_semanal' || ehCodigoBlocoCalc(c)) return;
            if (cols.indexOf(c) < 0) cols.push(c);
        }
        (estado && estado.colunas_ordem || []).forEach(pushCol);
        ((estado && estado.pecas) || []).forEach(pushCol);
        cols.forEach(function (c) {
            if (rec) return;
            if (/(^|_)rec(_|$)/.test(c) || /recuperacao/.test(c) || sufixoColunaSm(c) === 'rec') rec = c;
        });
        if (!rec) {
            Object.keys((estado && estado.titulos_coluna) || {}).forEach(function (c) {
                if (rec) return;
                var tit = String((estado.titulos_coluna || {})[c] || '').toLowerCase();
                if (/rec(uperacao)?/.test(tit)) rec = String(c).toLowerCase();
            });
        }
        if (bases.length < 2 || !rec) {
            // Fallback: só abre a função para o usuário completar.
            return [
                { type: 'fn', value: 'composicao', label: 'média c/ rec (' },
                { type: 'op', value: '(', label: '(' },
                { type: 'op', value: ')', label: ')' },
                { type: 'op', value: ',', label: ',' },
                { type: 'op', value: ')', label: ')' }
            ];
        }
        return [
            { type: 'fn', value: 'composicao', label: 'média c/ rec (' },
            { type: 'op', value: '(', label: '(' },
            { type: 'peca', value: bases[0], label: nomeBlocoCalc(bases[0]) || bases[0], calc: true },
            { type: 'op', value: '+', label: '+' },
            { type: 'peca', value: bases[1], label: nomeBlocoCalc(bases[1]) || bases[1], calc: true },
            { type: 'op', value: ')', label: ')' },
            { type: 'op', value: '/', label: '÷' },
            { type: 'num', value: '2', label: '2' },
            { type: 'op', value: ',', label: ',' },
            { type: 'peca', value: rec, label: tituloColuna(rec) || pecaLabel(rec) || rec },
            { type: 'op', value: ')', label: ')' }
        ];
    }

    function ehCodigoBlocoCalc(codigo) {
        var cod = String(codigo || '');
        var low = cod.toLowerCase();
        if (!cod) return false;
        if ((estado.blocos_calc || []).indexOf(cod) >= 0 || (estado.blocos_calc || []).indexOf(low) >= 0) return true;
        if (estado.formulas_blocos && (estado.formulas_blocos[cod] || estado.formulas_blocos[low])) return true;
        if (/^media_(\d+|bim|final|sem)$/i.test(low)) return true;
        return false;
    }

    function chipHtml(tok, idx, paleta) {
        var kind = tok.type === 'fn' ? 'fn' : tok.type;
        if (tok && tok.type === 'peca' && (tok.calc || ehCodigoBlocoCalc(tok.value))) kind = 'calc';
        var mostrado = (tok && tok.label != null && String(tok.label) !== '')
            ? String(tok.label)
            : labelToken(tok);
        var completo = tok && (tok.label || tok.value) ? String(tok.label || tok.value) : '';
        var dica = '';
        if (tok && tok.type === 'peca' && ehCodigoBlocoCalc(tok.value)) {
            dica = ' title="' + esc('Bloco de cálculo: ' + (completo || tok.value)) + '"';
        } else if (tok && tok.type === 'peca' && mostrado !== completo) {
            dica = ' title="' + esc(completo) + '"';
        }
        var html = '<span class="bw-chip bw-chip-' + kind + (paleta ? ' bw-pal-chip' : '') + '" draggable="true"'
            + ' data-type="' + esc(tok.type) + '" data-value="' + esc(tok.value) + '" data-label="' + esc(mostrado) + '"' + dica;
        if (!paleta) html += ' data-idx="' + idx + '"';
        html += '><span>' + esc(mostrado) + '</span>';
        if (!paleta) html += '<button type="button" class="bw-chip-x" draggable="false" data-idx="' + idx + '" aria-label="Remover">&times;</button>';
        html += '</span>';
        return html;
    }

    function htmlCanvasTokens() {
        var toks = (estado && estado.formula_tokens) || [];
        if (!toks.length) {
            var midPh = Number((estado && estado.materia_calc) || 0);
            if (midPh > 0) {
                return '<p class="bw-canvas-ph">Fórmula só de ' + esc(materiaNome(midPh)) + '. Clique ou arraste as peças que esta matéria tem.</p>';
            }
            return '<p class="bw-canvas-ph">Clique nas siglas à esquerda (ou nos sinais). Ex.: PP , PU , CH ) + PE</p>';
        }
        var html = '';
        toks.forEach(function (t, i) { html += chipHtml(t, i, false); });
        return html;
    }

    function renderFormulaCanvas() {
        var canvas = document.getElementById('bw-formula-canvas');
        if (canvas) canvas.innerHTML = htmlCanvasTokens();
        atualizarFormulaPreview();
    }

    function atualizarFormulaPreview() {
        if (!estado) return;
        estado.formula_custom = compilarTokensPreview(estado.formula_tokens || []);
        var el = document.getElementById('bw-formula-preview');
        if (!el) return;
        el.textContent = '';
        var midPrev = Number(estado.materia_calc || 0);
        var expr = formulaTextoLegivel(estado.formula_tokens) || estado.formula_custom || 'Monte os blocos acima.';
        if (midPrev > 0) {
            var prefix = document.createElement('span');
            prefix.className = 'font-sans font-semibold text-indigo-800 mr-1';
            prefix.textContent = materiaNome(midPrev) + ':';
            el.appendChild(prefix);
        }
        el.appendChild(document.createTextNode(expr));
    }

    function tokenFromEl(el) {
        if (!el) return null;
        var type = el.getAttribute('data-type');
        var value = el.getAttribute('data-value');
        if (!type || value == null || value === '') return null;
        return { type: type, value: value, label: el.getAttribute('data-label') || value };
    }

    function pushToken(tok, at) {
        if (!estado || !tok) return;
        if (tok.type === 'peca') garantirPecaNaFormula(tok.value);
        if (!Array.isArray(estado.formula_tokens)) estado.formula_tokens = [];
        if (estado.formula_tokens.length >= 80) return;
        if (typeof at === 'number' && at >= 0 && at < estado.formula_tokens.length) {
            estado.formula_tokens.splice(at, 0, tok);
        } else {
            estado.formula_tokens.push(tok);
        }
        afterFormulaChange();
    }

    function removeToken(idx) {
        if (!estado || !Array.isArray(estado.formula_tokens)) return;
        if (idx < 0 || idx >= estado.formula_tokens.length) return;
        estado.formula_tokens.splice(idx, 1);
        afterFormulaChange();
    }

    function moveToken(from, to) {
        if (!estado || !Array.isArray(estado.formula_tokens)) return;
        var n = estado.formula_tokens.length;
        if (from < 0 || from >= n) return;
        if (to < 0) to = 0;
        if (to > n) to = n;
        if (from === to || from + 1 === to) return;
        var item = estado.formula_tokens.splice(from, 1)[0];
        if (to > from) to -= 1;
        estado.formula_tokens.splice(to, 0, item);
        afterFormulaChange();
    }

    function afterFormulaChange() {
        formulaVersao++;
        marcarEdicaoManual();
        estado.formula_tokens = envolverSomaAntesDeDivisao(estado.formula_tokens || []);
        (estado.formula_tokens || []).forEach(function (t) {
            if (t && t.type === 'peca') garantirColunaFormulaSemQuadro(t.value);
        });
        if (estado.bloco_calc) {
            gravarTokensBlocoAberto();
        }
        renderFormulaCanvas();
        renderExcecoesMateria();
        var avisoSalvo = document.getElementById('bw-formula-salva');
        if (avisoSalvo) avisoSalvo.classList.add('hidden');
        agendarMontar();
    }

    function gravarTokensBlocoAberto() {
        if (!estado || !estado.bloco_calc) return;
        var toks = (estado.formula_tokens || []).slice();
        var mid = Number(estado.materia_calc || 0);
        if (mid > 0) {
            mapaExcecoesBloco(estado.bloco_calc, true)[mid] = toks;
            return;
        }
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        estado.formulas_blocos[estado.bloco_calc] = toks;
    }

    function materiaNome(id) {
        var n = Number(id || 0);
        var lista = (catalogo && catalogo.materias) || [];
        for (var i = 0; i < lista.length; i++) {
            if (Number(lista[i].id) === n) return lista[i].nome || ('Matéria #' + n);
        }
        return 'Matéria #' + n;
    }

    function rotuloSemestreColuna(codigo, rotulo) {
        codigo = String(codigo || '').toLowerCase();
        var r = String(rotulo || '').trim();
        var mapa = {
            s1_rec: 'REC 1º',
            s2_rec: 'REC 2º',
            s1_ms: 'MS 1º',
            s2_ms: 'MS 2º'
        };
        if (!mapa[codigo]) return r;
        if (!r || /^(rec|ms|recuperacao)$/i.test(r)) return mapa[codigo];
        // Já distingue semestre (1º/2º, s1/s2, etc.) — mantém.
        if (/[12]\s*º|semestr|[_\s-]s[12]\b/i.test(r)) return r;
        return r + (codigo.indexOf('s1_') === 0 ? ' 1º' : ' 2º');
    }

    function siglaTituloColunaJs(codigo, nome) {
        var raw = String(codigo || '').toLowerCase();
        if (raw === 's1_rec') return 'REC 1º';
        if (raw === 's2_rec') return 'REC 2º';
        if (raw === 's1_ms') return 'MS 1º';
        if (raw === 's2_ms') return 'MS 2º';
        var leaf = raw;
        var m = leaf.match(/^b[1-4]_(.+)$/);
        if (m) leaf = m[1];
        m = leaf.match(/^s[12]_(.+)$/);
        if (m) leaf = m[1];
        var conhecidos = {
            pp: 'PP', pu: 'PU', pe: 'PE', ch: 'CH', mu: 'MU', fal: 'FAL', faltas: 'FAL',
            rec: 'REC', recuperacao: 'REC', pf: 'PF', mf: 'MF', ms: 'MS', ma: 'MA'
        };
        if (conhecidos[leaf]) return conhecidos[leaf];
        if (leaf && leaf.length <= 5 && /^[a-z0-9]+$/.test(leaf)) return leaf.toUpperCase();
        var nomeNorm = String(nome || '').toLocaleLowerCase('pt-BR');
        nomeNorm = nomeNorm.normalize ? nomeNorm.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : nomeNorm;
        nomeNorm = nomeNorm.replace(/[^a-z0-9]+/g, ' ').replace(/\s+/g, ' ').trim();
        var porNome = {
            'prova parcial': 'PP',
            'prova de unidade': 'PU',
            'ponto extra': 'PE',
            '2a chamada': 'CH',
            'segunda chamada': 'CH',
            'media da unidade': 'MU',
            'faltas': 'FAL',
            'recuperacao': 'REC',
            'prova final': 'PF',
            'media semestral': 'MS',
            'media anual': 'MA',
            'media final': 'MF'
        };
        if (porNome[nomeNorm]) return porNome[nomeNorm];
        return '';
    }

    function tituloColuna(codigo) {
        codigo = String(codigo || '');
        if (!codigo) return '';
        var sigCod = siglaTituloColunaJs(codigo, '');
        // No Montar quadro / impressão preferimos sempre a sigla (PP), não "Prova Parcial".
        if (typeof ehVisaoImpressaoEditavel === 'function' && ehVisaoImpressaoEditavel() && sigCod) {
            return rotuloSemestreColuna(codigo, sigCod);
        }
        if (estado && estado.nomes_blocos) {
            var custom = '';
            if (estado.nomes_blocos[codigo]) custom = estado.nomes_blocos[codigo];
            var low = codigo.toLowerCase();
            if (!custom && estado.nomes_blocos[low]) custom = estado.nomes_blocos[low];
            var quadro = pecaCodigoQuadro(codigo);
            if (!custom && quadro && quadro !== codigo && estado.nomes_blocos[quadro]) custom = estado.nomes_blocos[quadro];
            var aliases = {
                media_sem: 'semanal', semanal: 'media_sem',
                prova_bim: 'bimestral', bimestral: 'prova_bim',
                rec: 'recuperacao', recuperacao: 'rec',
                trab: 'trabalho', trabalho: 'trab',
                part: 'participacao', participacao: 'part'
            };
            var outro = aliases[codigo] || aliases[low] || '';
            if (!custom && outro && estado.nomes_blocos[outro]) custom = estado.nomes_blocos[outro];
            if (custom) {
                var sigCustom = siglaTituloColunaJs(codigo, custom);
                // Se o custom ainda for nome longo conhecido, troca pela sigla.
                if (sigCustom && String(custom).length > 6) {
                    return rotuloSemestreColuna(codigo, sigCustom);
                }
                return rotuloSemestreColuna(codigo, custom);
            }
        }
        if (sigCod) return rotuloSemestreColuna(codigo, sigCod);
        var sigEv = siglaColunaEventoAvaliativo(codigo);
        if (sigEv) return rotuloSemestreColuna(codigo, sigEv);
        return '';
    }

    function siglaColunaEventoAvaliativo(codigo) {
        codigo = String(codigo || '');
        if (!codigo) return '';
        var peloCodigo = siglaPeloCodigoColuna(codigo);
        if (peloCodigo) return peloCodigo;
        var low = codigo.toLowerCase();
        var lista = (catalogo && catalogo.eventos_avaliativos) || [];
        for (var i = 0; i < lista.length; i++) {
            var ev = lista[i];
            var sig = siglaDoEvento(ev);
            if (!sig) continue;
            if (sig.toLowerCase() === low) return sig.toUpperCase();
            if (('eva' + Number(ev.id || 0)) === low || ('eva_' + Number(ev.id || 0)) === low) {
                return sig.toUpperCase();
            }
        }
        var comp = componentePorCodigo(codigo);
        if (comp) {
            var cfg = comp.config || {};
            if (typeof cfg === 'string') {
                try { cfg = JSON.parse(cfg) || {}; } catch (e) { cfg = {}; }
            }
            var evId = Number((cfg && cfg.evento_avaliativo_id) || 0);
            if (evId > 0) {
                for (var j = 0; j < lista.length; j++) {
                    if (Number(lista[j].id) === evId && siglaDoEvento(lista[j])) {
                        return String(siglaDoEvento(lista[j])).toUpperCase();
                    }
                }
            }
        }
        return '';
    }

    function aplicarSiglasEventosAvulsosNosTitulos() {
        if (!estado) return;
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        var lista = (catalogo && catalogo.eventos_avaliativos) || [];
        eventosAvaliativosMarcados().forEach(function (id) {
            var ev = null;
            for (var i = 0; i < lista.length; i++) {
                if (Number(lista[i].id) === id) { ev = lista[i]; break; }
            }
            if (!ev) return;
            var sig = String(siglaDoEvento(ev) || '').toUpperCase();
            if (!sig) return;
            var codSigla = sig.toLowerCase().replace(/[^a-z0-9_]+/g, '');
            if (codSigla) estado.nomes_blocos[codSigla] = sig;
            estado.nomes_blocos['eva' + id] = sig;
            estado.nomes_blocos['eva_' + id] = sig;
            var bim = Number(ev.bimestre || 0);
            if (bim >= 1 && bim <= 4 && codSigla) {
                var leaf = codSigla === 'faltas' ? 'fal' : codSigla;
                if (['pp', 'pu', 'ch', 'pe', 'fal'].indexOf(leaf) >= 0) {
                    estado.nomes_blocos['b' + bim + '_' + leaf] = sig;
                }
            }
            if (bim === 81 || bim === 61 || bim === 62) {
                estado.nomes_blocos.s1_rec = (sig === 'REC') ? 'REC 1º' : sig;
            }
            if (bim === 82 || bim === 63 || bim === 64) {
                estado.nomes_blocos.s2_rec = (sig === 'REC') ? 'REC 2º' : sig;
            }
            if (bim === 90 || sig === 'PF') {
                estado.nomes_blocos.pf = 'PF';
            }
            ((ev.subeventos) || []).forEach(function (sub, idx) {
                var sid = Number(sub && sub.id || 0);
                if (sid <= 0) return;
                var rotulo = ((ev.subeventos || []).length <= 1) ? sig : (sig + String(idx + 1));
                estado.nomes_blocos['sev' + sid] = rotulo;
                estado.nomes_blocos['sev_' + sid] = rotulo;
            });
        });
    }

    function nomeTituloParaCodigo(codigo) {
        codigo = String(codigo || '').toLowerCase();
        if (!codigo || codigo === '_semanal' || /^s[1-8]$/.test(codigo)) return '';
        return String(tituloColuna(codigo) || '').trim().slice(0, 60);
    }

    function tituloFoiLimpo(codigo) {
        if (!estado || !estado.nomes_limpos) return false;
        codigo = String(codigo || '').toLowerCase();
        if (!codigo) return false;
        if (estado.nomes_limpos[codigo]) return true;
        var aliases = {
            media_sem: 'semanal', semanal: 'media_sem',
            prova_bim: 'bimestral', bimestral: 'prova_bim',
            rec: 'recuperacao', recuperacao: 'rec',
            trab: 'trabalho', trabalho: 'trab',
            part: 'participacao', participacao: 'part'
        };
        var outro = aliases[codigo] || '';
        return !!(outro && estado.nomes_limpos[outro]);
    }

    function aplicarNomesNoRascunho(r) {
        if (!r || !Array.isArray(r.componentes) || !estado) return r;
        aplicarTitulosDosSubeventos();
        r.componentes.forEach(function (c) {
            if (!c) return;
            var cod = String(c.codigo || '');
            if (/^s[1-8]$/.test(cod) || cod === '_semanal') return;
            var doEvento = tituloSubeventoDaColuna(cod);
            if (doEvento) {
                c.nome = doEvento;
                return;
            }
            if (tituloFoiLimpo(cod)) {
                var padrao = nomeBlocoCalc(cod);
                if (padrao) c.nome = padrao;
            } else {
                var nome = nomeTituloParaCodigo(cod);
                if (nome) c.nome = nome;
            }
            if (estado.escalas_blocos && estado.escalas_blocos[cod] != null) {
                c.escala_max = escalaBlocoCalc(cod);
            }
        });
        return r;
    }

    function flushTituloAberto() {
        if (!estado || !estado.bloco_calc) return;
        var inp = document.getElementById('bw-formula-bloco-nome-input');
        if (!inp) return;
        aplicarTituloBloco(estado.bloco_calc, String(inp.value || ''));
        flushEscalaAberta();
    }

    function escalaBlocoCalc(codigo) {
        codigo = String(codigo || '');
        if (!codigo || !estado || !estado.escalas_blocos) return 10;
        var v = Number(estado.escalas_blocos[codigo]);
        if (!isFinite(v) || v < 0.01) return 10;
        return Math.min(999, Math.round(v * 100) / 100);
    }

    function aplicarEscalaBloco(codigo, escala) {
        codigo = String(codigo || '');
        if (!estado || !codigo || codigo === '_semanal' || /^s[1-8]$/.test(codigo)) return;
        if (!estado.escalas_blocos || typeof estado.escalas_blocos !== 'object' || Array.isArray(estado.escalas_blocos)) {
            estado.escalas_blocos = {};
        }
        var v = Number(escala);
        if (!isFinite(v) || v < 0.01) v = 10;
        if (v > 999) v = 999;
        v = Math.round(v * 100) / 100;
        estado.escalas_blocos[codigo] = v;
        var inp = document.getElementById('bw-formula-bloco-escala-input');
        if (inp && estado.bloco_calc === codigo) inp.value = String(v);
        marcarEdicaoManual();
    }

    function flushEscalaAberta() {
        if (!estado || !estado.bloco_calc) return;
        var inp = document.getElementById('bw-formula-bloco-escala-input');
        if (!inp) return;
        aplicarEscalaBloco(estado.bloco_calc, inp.value);
    }

    function nomeBlocoCalc(codigo) {
        if (codigo === 'media_final' && !tituloColuna(codigo)) return 'Resultado Final';
        if (tituloColuna(codigo)) return tituloColuna(codigo);
        if (codigo === 'media_bim') return 'Média Bim';
        if (codigo === 'media_sem') return 'Média Sem';
        return pecaLabel(codigo);
    }

    function normalizarExprFormula(src) {
        var s = String(src || '').toLocaleLowerCase('pt-BR');
        if (s.normalize) s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        s = s.replace(/÷/g, '/').replace(/×/g, '*').replace(/[−–—]/g, '-');
        s = s.replace(/[^a-z0-9+\-*/(),.\s_]/g, ' ');
        return s.replace(/\s+/g, ' ').trim();
    }

    function indiceNomesFormula() {
        var itens = [];
        function add(nome, codigo, prio) {
            var n = normalizarExprFormula(nome);
            var c = String(codigo || '').trim();
            if (!n || !c || c === '_semanal' || /^s[1-8]$/.test(c)) return;
            if (/^(menor|maior|min|max)$/.test(n)) return;
            itens.push({ n: n, codigo: c, label: String(nome || c), prio: prio || 0 });
        }
        add('Média Bim', 'media_bim', 1);
        add('Resultado Final', 'media_final', 1);
        add('Média Final', 'media_final', 1);
        add('Média Bim Final', 'media_final', 1);
        add('Média Sem', 'media_sem', 1);
        add('Média Semanal', 'media_sem', 1);
        add('Prova Semanal', 'semanal', 1);
        add('Avaliação Bimestral', 'bimestral', 1);
        add('Prova Bimestral', 'bimestral', 1);
        add('Prova Bim', 'bimestral', 1);
        add('ENAC', 'enac', 1);
        add('Jornada do aluno', 'jornada', 1);
        add('Jornada', 'jornada', 1);
        add('Recuperação Final', 'recuperacao', 1);
        add('Recuperação', 'recuperacao', 1);
        add('Trabalho', 'trabalho', 1);
        add('Participação', 'participacao', 1);
        add('Faltas', 'faltas', 1);
        (catalogo.pecas || []).forEach(function (p) {
            if (p && p.key) add(p.label || p.key, p.key, 2);
        });
        function addColuna(c) {
            if (!c || !c.codigo) return;
            add(c.nome || c.codigo, c.codigo, 3);
            add(c.codigo, c.codigo, 0);
        }
        var pv = (typeof previewEfetivo === 'function') ? previewEfetivo() : null;
        ((pv && pv.colunas) || []).forEach(addColuna);
        ((pv && pv.tabelas) || []).forEach(function (t) {
            (t.outras || []).concat(t.semanas || []).forEach(addColuna);
        });
        ((rascunhoAtual && rascunhoAtual.componentes) || []).forEach(function (c) {
            if (!c) return;
            add(c.nome || c.codigo, c.codigo, 3);
            add(c.codigo, c.codigo, 0);
        });
        if (estado && estado.nomes_blocos) {
            Object.keys(estado.nomes_blocos).forEach(function (cod) {
                add(estado.nomes_blocos[cod], cod, 4);
                add(nomeBlocoCalc(cod), cod, 3);
            });
        }
        (estado && estado.blocos_calc || []).forEach(function (cod) {
            add(nomeBlocoCalc(cod), cod, 3);
            add(cod, cod, 0);
        });
        itens.sort(function (a, b) {
            if (b.n.length !== a.n.length) return b.n.length - a.n.length;
            return b.prio - a.prio;
        });
        return itens;
    }

    function codigoPeloNomeFormula(nome, indice) {
        var n = normalizarExprFormula(nome);
        var melhor = null;
        (indice || []).forEach(function (item) {
            if (item.n !== n) return;
            if (!melhor || item.prio > melhor.prio) melhor = item;
        });
        return melhor ? melhor.codigo : '';
    }

    function tokenizarFormulaTexto(src, indice) {
        var t = normalizarExprFormula(src);
        if (!t) return { ok: false, erro: 'A resposta não trouxe uma fórmula.', tokens: [] };
        var i = 0;
        var flat = [];
        var temColuna = false;
        while (i < t.length) {
            if (t.charAt(i) === ' ') { i++; continue; }
            var ch = t.charAt(i);
            if ('+-*/(),'.indexOf(ch) >= 0) {
                flat.push({ k: 'op', v: ch });
                i++;
                continue;
            }
            if (/[0-9]/.test(ch)) {
                var j = i;
                while (j < t.length && /[0-9.]/.test(t.charAt(j))) j++;
                var num = t.slice(i, j);
                if (!/^\d+(\.\d+)?$/.test(num)) return { ok: false, erro: 'Número inválido na fórmula.', tokens: [] };
                flat.push({ k: 'num', v: num });
                i = j;
                continue;
            }
            var rest = t.slice(i);
            var fn = rest.match(/^(menor|maior|min|max|chamada|substituta|composicao)(?![a-z0-9_])/);
            if (fn) {
                var qual = fn[1];
                var posFn = i + qual.length;
                while (t.charAt(posFn) === ' ') posFn++;
                if (t.charAt(posFn) !== '(') {
                    return { ok: false, erro: 'A função ' + qual + ' precisa abrir com parêntese.', tokens: [] };
                }
                var fnVal = 'max';
                if (qual === 'menor' || qual === 'min') fnVal = 'min';
                else if (qual === 'chamada' || qual === 'substituta') fnVal = 'chamada';
                else if (qual === 'composicao') fnVal = 'composicao';
                flat.push({ k: 'fn', v: fnVal });
                i = posFn + 1;
                continue;
            }
            var achou = null;
            for (var k = 0; k < indice.length; k++) {
                var nome = indice[k].n;
                if (rest.slice(0, nome.length) !== nome) continue;
                var depois = rest.charAt(nome.length);
                if (depois && /[a-z0-9_]/.test(depois)) continue;
                achou = indice[k];
                break;
            }
            if (!achou) {
                var id = rest.match(/^[a-z][a-z0-9_]*/);
                var codigos = codigosColunaFormula();
                if (id && codigos[id[0]]) {
                    flat.push({ k: 'peca', v: id[0], label: nomeBlocoCalc(id[0]) });
                    temColuna = true;
                    i += id[0].length;
                    continue;
                }
                var trecho = (id && id[0]) || rest.slice(0, 24).trim();
                return { ok: false, erro: 'Não achei a coluna "' + trecho + '" neste evento.', tokens: [] };
            }
            flat.push({ k: 'peca', v: achou.codigo, label: achou.label });
            temColuna = true;
            i += achou.n.length;
        }
        if (!flat.length || !temColuna) return { ok: false, erro: 'A fórmula não cita nenhuma coluna.', tokens: [] };
        var profundidade = 0;
        for (var p = 0; p < flat.length; p++) {
            if (flat[p].k === 'fn' || (flat[p].k === 'op' && flat[p].v === '(')) profundidade++;
            else if (flat[p].k === 'op' && flat[p].v === ')') profundidade--;
            if (profundidade < 0) return { ok: false, erro: 'Os parênteses da fórmula não fecham.', tokens: [] };
        }
        if (profundidade !== 0) return { ok: false, erro: 'Os parênteses da fórmula não fecham.', tokens: [] };
        var toks = [];
        flat.forEach(function (item) {
            if (item.k === 'fn') {
                var labFn = item.v === 'min'
                    ? 'menor ('
                    : (item.v === 'chamada' || item.v === 'substituta'
                        ? 'nota substituta ('
                        : (item.v === 'composicao' ? 'média c/ rec (' : 'maior ('));
                toks.push({ type: 'fn', value: item.v, label: labFn });
            }
            else if (item.k === 'num') toks.push({ type: 'num', value: item.v, label: item.v });
            else if (item.k === 'peca') toks.push({ type: 'peca', value: item.v, label: item.label || nomeBlocoCalc(item.v) });
            else if (item.k === 'op') {
                var lab = item.v === '/' ? '÷' : (item.v === '*' ? '×' : (item.v === '-' ? '−' : item.v));
                toks.push({ type: 'op', value: item.v, label: lab });
            }
        });
        if (toks.length > 80) return { ok: false, erro: 'A fórmula ficou longa demais.', tokens: [] };
        return { ok: true, erro: '', tokens: toks, temColuna: true };
    }

    function extrairFormulasDoTexto(texto) {
        var indice = indiceNomesFormula();
        var linhas = String(texto || '').split(/\n/);
        var lista = [];
        var ultimaColuna = '';
        linhas.forEach(function (linha) {
            var limpa = String(linha || '').replace(/```/g, ' ').replace(/`/g, '').replace(/\*\*/g, '').replace(/^[\s\d\.\)•\-]+/, '').trim();
            var mcol = limpa.match(/\bcoluna\s+([^\n:,]{2,40})/i);
            if (mcol && codigoPeloNomeFormula(mcol[1].replace(/[:.]+$/g, '').trim(), indice)) {
                ultimaColuna = mcol[1].replace(/[:.]+$/g, '').trim();
            }
            limpa = limpa.replace(/^(monte|use|fórmula|formula)\s*:\s*/i, '').trim();
            if (!limpa) return;
            var eq = limpa.indexOf('=');
            var destino = '';
            var expr = limpa;
            if (eq > 0) {
                destino = limpa.slice(0, eq).trim();
                expr = limpa.slice(eq + 1).trim();
            } else if (ultimaColuna) {
                destino = ultimaColuna;
            }
            if (!/(?:menor|maior|min|max)\s*\(|[+\-*/÷×]|[()]/.test(expr)) return;
            if (!/[a-zA-ZÀ-ÿ]/.test(expr)) return;
            lista.push({ destino: destino, expr: expr.replace(/[."']+$/g, '').trim() });
        });
        var formulas = [];
        var vistos = {};
        var erro = '';
        lista.forEach(function (c) {
            var lido = tokenizarFormulaTexto(c.expr, indice);
            if (!lido.ok) { erro = lido.erro; return; }
            var dest = c.destino ? codigoPeloNomeFormula(c.destino, indice) : '';
            var chave = (dest || '-') + '|' + c.expr;
            if (vistos[chave]) return;
            vistos[chave] = true;
            formulas.push({
                destino: dest,
                nome: dest ? nomeBlocoCalc(dest) : '',
                tokens: lido.tokens
            });
        });
        var comDestino = formulas.filter(function (f) { return !!f.destino; });
        if (comDestino.length) formulas = comDestino;
        else if (formulas.length > 1) {
            formulas.sort(function (a, b) { return b.tokens.length - a.tokens.length; });
            formulas = [formulas[0]];
        }
        return { formulas: formulas, erro: formulas.length ? '' : (erro || 'Não achei uma fórmula para usar.') };
    }

    function colunaAceitaFormula(codigo) {
        codigo = String(codigo || '');
        return !!codigo && codigo !== 'media_sem' && codigo !== '_semanal' && !/^s[1-8]$/.test(codigo);
    }

    function aplicarFormulaNoBloco(codigo, tokens) {
        if (!estado || !colunaAceitaFormula(codigo) || !tokens || !tokens.length) return false;
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object' || Array.isArray(estado.formulas_blocos)) {
            estado.formulas_blocos = {};
        }
        if ((estado.blocos_calc || []).indexOf(codigo) < 0 && codigo !== 'media_sem') {
            if (!Array.isArray(estado.blocos_calc)) estado.blocos_calc = [];
            estado.blocos_calc.push(codigo);
        }
        estado.materia_calc = 0;
        estado.formulas_blocos[codigo] = tokens.slice();
        estado.titulo_codigo = codigo;
        if (estado.passo !== 'formula') {
            estado.passo = 'formula';
            estado.bloco_calc = codigo;
            renderAll();
        }
        abrirFormulaBloco(codigo);
        if (estado.bloco_calc !== codigo) return false;
        estado.formula_tokens = tokens.slice();
        afterFormulaChange();
        var aviso = document.getElementById('bw-formula-salva');
        if (aviso) {
            aviso.textContent = 'Fórmula aplicada.';
            aviso.classList.remove('hidden');
        }
        var ed = document.getElementById('bw-formula-editor');
        if (ed && ed.scrollIntoView) ed.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return true;
    }

    window.boletimWizardLerFormulaTexto = function (texto) {
        if (!estado) return { formulas: [], erro: 'Abra o evento de notas para usar a fórmula.' };
        return extrairFormulasDoTexto(texto);
    };

    window.boletimWizardAplicarFormulaTexto = function (texto) {
        if (!estado) return { ok: false, erro: 'Abra o evento de notas para usar a fórmula.', nomes: [] };
        var lido = extrairFormulasDoTexto(texto);
        if (!lido.formulas.length) return { ok: false, erro: lido.erro, nomes: [] };
        var nomes = [];
        var ultima = '';
        var tokensUltima = [];
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object' || Array.isArray(estado.formulas_blocos)) {
            estado.formulas_blocos = {};
        }
        lido.formulas.forEach(function (f) {
            var codigo = f.destino || (estado.bloco_calc || '');
            if (!colunaAceitaFormula(codigo) || !f.tokens || !f.tokens.length) return;
            if ((estado.blocos_calc || []).indexOf(codigo) < 0 && codigo !== 'media_sem') {
                if (!Array.isArray(estado.blocos_calc)) estado.blocos_calc = [];
                estado.blocos_calc.push(codigo);
            }
            estado.formulas_blocos[codigo] = f.tokens.slice();
            ultima = codigo;
            tokensUltima = f.tokens;
            nomes.push(nomeBlocoCalc(codigo));
        });
        if (!nomes.length || !aplicarFormulaNoBloco(ultima, tokensUltima)) {
            return { ok: false, erro: 'Abra a coluna amarela (Resultado Final, Média Bim…) e use a fórmula de novo.', nomes: [] };
        }
        return { ok: true, erro: '', nomes: nomes };
    };

    function codigoBlocoReservado(cod) {
        var reserved = { _semanal: 1, media_sem: 1, prova_bim: 1, enac: 1, trab: 1, part: 1, rec: 1, jornada: 1 };
        (estado.pecas || []).forEach(function (k) { reserved[k] = 1; reserved[pecaCodigoQuadro(k)] = 1; });
        return !!reserved[cod];
    }

    function novoCodigoBloco() {
        if ((estado.blocos_calc || []).indexOf('media_bim') < 0 && !(estado.formulas_blocos || {}).media_bim) {
            return 'media_bim';
        }
        if ((estado.blocos_calc || []).indexOf('media_final') < 0 && !(estado.formulas_blocos || {}).media_final) {
            return 'media_final';
        }
        var n = 3;
        var c = 'media_' + n;
        while (codigoBlocoReservado(c) || (estado.formulas_blocos || {})[c] || (estado.blocos_calc || []).indexOf(c) >= 0) {
            n += 1;
            c = 'media_' + n;
        }
        return c;
    }

    function mapaExcecoesBloco(codigo, criar) {
        if (!estado.formulas_materias_blocos || typeof estado.formulas_materias_blocos !== 'object' || Array.isArray(estado.formulas_materias_blocos)) {
            estado.formulas_materias_blocos = {};
        }
        var por = estado.formulas_materias_blocos[codigo];
        if (Array.isArray(por)) {
            var obj = {};
            Object.keys(por).forEach(function (k) {
                var id = Number(k);
                if (id > 0) obj[id] = por[k];
            });
            por = obj;
            estado.formulas_materias_blocos[codigo] = por;
            return por;
        }
        if (por && typeof por === 'object') return por;
        if (!criar) return {};
        por = {};
        estado.formulas_materias_blocos[codigo] = por;
        return por;
    }

    function removerMuAutomaticaDoEstado() {
        if (!estado) return;
        // Print Santa Maria: MU faz parte do modelo oficial — não remove.
        if (estado.modelo_santa_maria || estado.origem === 'santa-maria') return;
        function ehMuAuto(cod) { return /^b[1-4]_mu$/i.test(String(cod || '')); }
        estado.blocos_calc = (estado.blocos_calc || []).filter(function (c) { return !ehMuAuto(c); });
        estado.colunas_ordem = (estado.colunas_ordem || []).filter(function (c) { return !ehMuAuto(c); });
        ['formulas_blocos', 'nomes_blocos', 'escalas_blocos', 'formulas_materias_blocos'].forEach(function (chave) {
            if (!estado[chave] || typeof estado[chave] !== 'object') return;
            Object.keys(estado[chave]).forEach(function (cod) {
                if (ehMuAuto(cod)) delete estado[chave][cod];
            });
        });
        if (Array.isArray(estado.linhas_layout)) {
            estado.linhas_layout = estado.linhas_layout.map(function (lin) {
                if (!lin) return lin;
                lin.colunas = (lin.colunas || []).filter(function (c) { return !ehMuAuto(c); });
                return lin;
            }).filter(function (lin) { return lin && String(lin.nome || '').trim(); });
        }
        if (estado.bloco_calc && ehMuAuto(estado.bloco_calc)) {
            estado.bloco_calc = '';
            estado.formula_tokens = [];
        }
        [rascunhoAtual, estado.rascunho_preservado].forEach(function (r) {
            if (!r || !Array.isArray(r.componentes)) return;
            r.componentes = r.componentes.filter(function (c) {
                return !(c && ehMuAuto(c.codigo));
            });
        });
    }

    function abrirFormulaBloco(codigo) {
        if (!codigo || codigo === 'media_sem' || codigo === '_semanal') return;
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        var mesmoBloco = estado.bloco_calc === codigo;
        estado.bloco_calc = codigo;
        if (!mesmoBloco) estado.materia_calc = 0;
        var mid = Number(estado.materia_calc || 0);
        var porEx = mapaExcecoesBloco(codigo, false);
        if (mid > 0 && Object.prototype.hasOwnProperty.call(porEx, String(mid))) {
            estado.formula_tokens = Array.isArray(porEx[mid]) ? porEx[mid].slice() : [];
        } else {
            estado.materia_calc = 0;
            estado.formula_tokens = Array.isArray(estado.formulas_blocos[codigo])
                ? estado.formulas_blocos[codigo].slice()
                : [];
        }
        var ed = document.getElementById('bw-formula-editor');
        if (ed) ed.classList.remove('hidden');
        var t = document.getElementById('bw-formula-bloco-nome');
        if (t) t.textContent = nomeBlocoCalc(codigo);
        var inp = document.getElementById('bw-formula-bloco-nome-input');
        if (inp) {
            inp.value = nomeBlocoCalc(codigo);
            if (Number(estado.materia_calc || 0) <= 0) {
                setTimeout(function () { inp.focus(); inp.select(); }, 30);
            }
        }
        var inpEsc = document.getElementById('bw-formula-bloco-escala-input');
        if (inpEsc) inpEsc.value = String(escalaBlocoCalc(codigo));
        renderFormulaCanvas();
        var lista = document.getElementById('bw-colunas-lista');
        if (lista) {
            lista.querySelectorAll('.bw-col-chip').forEach(function (el) {
                if (el.getAttribute('data-codigo') === codigo) el.classList.add('aberta');
                else el.classList.remove('aberta');
            });
        }
        // Abre no período do bloco (ou em Cálculo) para não forçar rolagem pela lista inteira.
        if (!mesmoBloco) {
            var cicloBloco = chaveCicloPalette(codigo);
            paletteCicloAtivo = (cicloBloco && cicloBloco !== 'outros') ? cicloBloco : 'calc';
        }
        var pal = document.getElementById('bw-formula-pecas-pal');
        if (pal) pal.innerHTML = htmlPalettePecasExibir();
        atualizarBannerExcecao();
        renderExcecoesMateria();
    }

    function atualizarBannerExcecao() {
        var banner = document.getElementById('bw-excecao-banner');
        var ed = document.getElementById('bw-formula-editor');
        var titulo = document.getElementById('bw-formula-bloco-titulo');
        var ajuda = document.getElementById('bw-formula-bloco-ajuda');
        var label = document.getElementById('bw-formula-canvas-label');
        var mid = Number((estado && estado.materia_calc) || 0);
        var nomeMat = mid > 0 ? materiaNome(mid) : '';
        var nomeBloco = nomeBlocoCalc((estado && estado.bloco_calc) || 'média');
        if (ed) {
            if (mid > 0) ed.classList.add('bw-editor-excecao');
            else ed.classList.remove('bw-editor-excecao');
        }
        if (titulo) {
            titulo.className = mid > 0
                ? 'text-base font-bold text-indigo-950'
                : 'text-sm font-semibold text-amber-950';
            titulo.innerHTML = mid > 0
                ? 'Cálculo de ' + esc(nomeBloco) + ' <span class="text-indigo-800">só em ' + esc(nomeMat) + '</span>'
                : 'Cálculo de <span id="bw-formula-bloco-nome">' + esc(nomeBloco) + '</span>';
        }
        if (ajuda) {
            ajuda.textContent = mid > 0
                ? 'Esta fórmula vale somente para ' + nomeMat + '. As outras matérias seguem a fórmula geral da coluna.'
                : 'A média é a soma entre parênteses, depois ÷ quantidade. Ex.: (Prova + Trabalho + Participação) ÷ 3.';
        }
        if (label) {
            label.textContent = mid > 0 ? 'Fórmula só de ' + nomeMat : 'Fórmula desta coluna';
        }
        if (!banner) return;
        if (mid > 0) {
            banner.classList.remove('hidden');
            banner.innerHTML = '<div class="flex flex-wrap items-center justify-between gap-2">'
                + '<div class="min-w-0">'
                + '<p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-100">Você está montando a fórmula de uma matéria</p>'
                + '<p class="mt-1 text-lg font-bold leading-tight">Só <span class="bw-excecao-pill">' + esc(nomeMat) + '</span></p>'
                + '<p class="text-xs text-indigo-100 mt-1">As outras matérias continuam com a fórmula geral de ' + esc(nomeBloco) + '.</p>'
                + '</div>'
                + '<button type="button" id="bw-excecao-voltar" class="h-9 px-3 text-xs font-semibold rounded-lg bg-white text-indigo-800 hover:bg-indigo-50">Voltar à fórmula geral</button>'
                + '</div>';
        } else {
            banner.classList.add('hidden');
            banner.innerHTML = '';
        }
        atualizarFormulaPreview();
    }

    function renderExcecoesMateria() {
        var wrap = document.getElementById('bw-excecoes-lista');
        if (!wrap || !estado || !estado.bloco_calc) return;
        var por = mapaExcecoesBloco(estado.bloco_calc, false);
        var mids = Object.keys(por).map(Number).filter(function (id) { return id > 0 && (por[id] || []).length; });
        var html = '';
        if (mids.length) {
            mids.forEach(function (mid) {
                var aberta = Number(estado.materia_calc || 0) === mid;
                html += '<div class="flex flex-wrap items-center justify-between gap-2 text-xs bg-white border border-indigo-100 rounded-lg px-2 py-1.5' + (aberta ? ' ring-2 ring-indigo-500 bg-indigo-50' : '') + '">';
                html += '<span class="font-medium text-indigo-950">' + esc(materiaNome(mid)) + (aberta ? ' <span class="text-[10px] uppercase tracking-wide text-indigo-600">editando agora</span>' : '') + '</span>';
                html += '<span class="font-mono text-slate-600 truncate max-w-[14rem]">' + esc(compilarTokensPreview(por[mid]) || '—') + '</span>';
                html += '<span class="flex gap-1"><button type="button" class="bw-excecao-editar px-2 py-0.5 rounded border border-indigo-200 text-indigo-700" data-mid="' + mid + '">Editar</button>';
                html += '<button type="button" class="bw-excecao-remover px-2 py-0.5 rounded border border-gray-200 text-gray-600" data-mid="' + mid + '">Remover</button></span></div>';
            });
        } else {
            html += '<p class="text-xs text-gray-500">Nenhuma exceção. Use quando uma matéria não tem um tipo de prova (ex.: sem semanal → só bimestral + ENAC).</p>';
        }
        wrap.innerHTML = html;
        var sel = document.getElementById('bw-excecao-materia');
        if (sel) {
            var ja = {};
            mids.forEach(function (id) { ja[id] = true; });
            var opts = '<option value="0">Escolher matéria…</option>';
            var listaEx = materiasParaExcecao().slice().sort(function (a, b) {
                return String(a.nome || '').localeCompare(String(b.nome || ''), 'pt');
            });
            listaEx.forEach(function (m) {
                var id = Number(m.id);
                if (!id || ja[id]) return;
                opts += '<option value="' + id + '">' + esc(m.nome) + '</option>';
            });
            sel.innerHTML = opts;
        }
    }

    function abrirExcecaoMateria(mid) {
        mid = Number(mid || 0);
        if (!estado || !estado.bloco_calc || mid <= 0) return;
        gravarTokensBlocoAberto();
        estado.materia_calc = mid;
        var por = mapaExcecoesBloco(estado.bloco_calc, true);
        var atuais = por[mid];
        if (!Array.isArray(atuais) || !atuais.length) {
            atuais = Array.isArray(estado.formulas_blocos[estado.bloco_calc])
                ? estado.formulas_blocos[estado.bloco_calc].slice()
                : [];
            por[mid] = atuais;
        }
        estado.formula_tokens = atuais.slice();
        renderFormulaCanvas();
        atualizarBannerExcecao();
        renderExcecoesMateria();
        var pal = document.getElementById('bw-formula-pecas-pal');
        if (pal) pal.innerHTML = htmlPalettePecasExibir();
        marcarEdicaoManual();
        agendarMontar();
    }

    function voltarFormulaGeral() {
        if (!estado || !estado.bloco_calc) return;
        gravarTokensBlocoAberto();
        estado.materia_calc = 0;
        estado.formula_tokens = Array.isArray(estado.formulas_blocos[estado.bloco_calc])
            ? estado.formulas_blocos[estado.bloco_calc].slice()
            : [];
        renderFormulaCanvas();
        atualizarBannerExcecao();
        renderExcecoesMateria();
    }

    function removerExcecaoMateria(mid) {
        mid = Number(mid || 0);
        if (!estado || !estado.bloco_calc || mid <= 0) return;
        var por = mapaExcecoesBloco(estado.bloco_calc, true);
        delete por[mid];
        if (Number(estado.materia_calc || 0) === mid) voltarFormulaGeral();
        else renderExcecoesMateria();
        marcarEdicaoManual();
        agendarMontar();
    }

    function garantirPecaNaFormula(key) {
        if (!estado || !key) return;
        if (key === 'media_sem' || key === 'media_bim' || key === 'media_final') return;
        if (ehCodigoBlocoCalc(key)) return;
        // Peça genérica (legado): mantém em estado.pecas.
        var pecasConhecidas = {};
        ((catalogo && catalogo.pecas) || []).forEach(function (p) {
            if (p && p.key) pecasConhecidas[String(p.key)] = true;
        });
        if (pecasConhecidas[key]) {
            if (!Array.isArray(estado.pecas)) estado.pecas = [];
            if (estado.pecas.indexOf(key) < 0) estado.pecas.push(key);
            if (!estado.pecas_opcoes || typeof estado.pecas_opcoes !== 'object') estado.pecas_opcoes = {};
            if (!estado.pecas_opcoes[key]) estado.pecas_opcoes[key] = pecasOpcoesPadraoJs(key);
        }
        // Evento/coluna: liga ao cálculo mesmo sem chip no quadro do boletim.
        garantirColunaFormulaSemQuadro(key);
    }

    /**
     * Garante evento/coluna na fórmula sem obrigar chip no quadro.
     * Fica oculta na impressão, mas com componente + evento no cálculo.
     */
    function garantirColunaFormulaSemQuadro(codigo) {
        codigo = String(codigo || '');
        if (!codigo || codigo === '_semanal' || ehCodigoBlocoCalc(codigo)) return;
        var ev = encontrarEventoPorCodigoColuna(codigo);
        if (ev) {
            var id = Number(ev.id || 0);
            if (id > 0) {
                if (!Array.isArray(estado.eventos_avaliativos_ids)) estado.eventos_avaliativos_ids = [];
                if (estado.eventos_avaliativos_ids.map(Number).indexOf(id) < 0) {
                    estado.eventos_avaliativos_ids.push(id);
                }
            }
        }
        var nome = tituloColuna(codigo)
            || (ev ? (String(siglaDoEvento(ev) || '').trim() || String(ev.nome || '')) : '')
            || codigo;
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!estado.nomes_blocos[codigo]) estado.nomes_blocos[codigo] = nome;
        garantirComponenteManualNoRascunho(codigo, nome);
        anexarEventoAoComponenteFormula(codigo, ev);
        if (!colunaEventoJaVisivelNoQuadro(codigo)) {
            ocultarColuna(codigo);
        }
    }

    function colunaEventoJaVisivelNoQuadro(codigo) {
        var low = String(codigo || '').toLowerCase();
        if (!low || colunaOculta(low)) return false;
        if ((estado.colunas_ordem || []).some(function (c) {
            return String(c || '').toLowerCase() === low;
        })) return true;
        return (estado.linhas_layout || []).some(function (lin) {
            return (lin && lin.colunas || []).some(function (c) {
                return String(c || '').toLowerCase() === low;
            });
        });
    }

    function encontrarEventoPorCodigoColuna(codigo) {
        var low = String(codigo || '').toLowerCase();
        if (!low) return null;
        var lista = eventosAvaliativosDoAnoQuadro() || [];
        var i;
        for (i = 0; i < lista.length; i++) {
            var ev = lista[i];
            var pref = String(codigoPreferidoEvento(ev) || '').toLowerCase();
            if (pref && pref === low) return ev;
            var id = Number(ev && ev.id || 0);
            if (id > 0 && (low === ('eva' + id) || low === ('eva_' + id))) return ev;
            var sig = String(siglaDoEvento(ev) || '').toLowerCase().replace(/[^a-z0-9_]+/g, '');
            if (sig && sig === low) return ev;
        }
        return null;
    }

    function anexarEventoAoComponenteFormula(codigo, ev) {
        if (!ev) return;
        var id = Number(ev.id || 0);
        var blocos = [];
        ((ev.subeventos) || []).forEach(function (sub) {
            var sid = Number(sub && sub.id || 0);
            if (sid > 0) blocos.push(sid);
        });
        [rascunhoAtual, estado && estado.rascunho_preservado].forEach(function (r) {
            if (!r || !Array.isArray(r.componentes)) return;
            r.componentes.forEach(function (c) {
                if (!c || String(c.codigo || '') !== String(codigo)) return;
                if (!c.config || typeof c.config !== 'object') c.config = {};
                if (id > 0) {
                    c.config.evento_avaliativo_id = id;
                    c.evento_avaliativo_id = id;
                }
                if (blocos.length) {
                    c.blocos_ids = blocos.slice();
                    c.config.blocos_ids = blocos.slice();
                    if (c.source_type === 'nenhuma' || !c.source_type) {
                        c.source_type = 'provas_sistema';
                    }
                }
            });
        });
    }

    function chaveCicloDeGrupoLayout(g) {
        g = String(g || '').toLowerCase();
        var m = g.match(/^b([1-4])$/);
        if (m) return 'p-' + m[1];
        if (g === 's1') return 's1';
        if (g === 's2') return 's2';
        if (g === 'final' || g === 'ano' || g === 'rec-ano' || g === 'resultado') return 'final';
        return '';
    }

    function chaveCicloDeNomeCabecalho(nome) {
        var n = String(nome || '').toUpperCase();
        if (!n) return '';
        if (/RECUPERA|.?\s*FINAL|RESULTADO\s*FINAL|PROVA\s*FINAL|\bPF\b/.test(n)
            && !/1\s*[ºO]?\s*SEM|2\s*[ºO]?\s*SEM/.test(n)) {
            return 'final';
        }
        if (/\bIV\b|4\s*[ºO]|QUARTO/.test(n)) return 'p-4';
        if (/\bIII\b|3\s*[ºO]|TERCEIRO/.test(n)) return 'p-3';
        if (/\bII\b|2\s*[ºO]|SEGUNDO/.test(n) && !/\bIII\b/.test(n)) return 'p-2';
        if (/\bI\b|1\s*[ºO]|PRIMEIRO/.test(n)) return 'p-1';
        if (/1\s*[ºO]?\s*SEM/.test(n)) return 's1';
        if (/2\s*[ºO]?\s*SEM/.test(n)) return 's2';
        return '';
    }

    function chaveCicloPalette(codigo) {
        var cod = String(codigo || '').toLowerCase();
        var m = cod.match(/^b([1-4])_/);
        if (m) return 'p-' + m[1];
        if (cod.indexOf('s1_') === 0) return 's1';
        if (cod.indexOf('s2_') === 0) return 's2';
        if (cod === 'pf' || cod === 'ma' || cod === 'mf' || cod === 'situacao'
            || cod === 'rec' || cod === 'recuperacao' || cod === 'rec_final') {
            return 'final';
        }

        // Blocos de cálculo (MU/MS…): ciclo = cabeçalho onde o chip está no quadro.
        var linhas = (estado && estado.linhas_layout) || [];
        var i;
        for (i = 0; i < linhas.length; i++) {
            var lin = linhas[i];
            if (!lin || !Array.isArray(lin.colunas)) continue;
            var cols = lin.colunas.map(function (c) { return String(c || '').toLowerCase(); });
            if (cols.indexOf(cod) < 0) continue;
            var j;
            for (j = 0; j < cols.length; j++) {
                var mm = cols[j].match(/^b([1-4])_/);
                if (mm) return 'p-' + mm[1];
                if (cols[j].indexOf('s1_') === 0) return 's1';
                if (cols[j].indexOf('s2_') === 0) return 's2';
                if (cols[j] === 'pf' || cols[j] === 'ma' || cols[j] === 'mf') return 'final';
            }
            var porNome = chaveCicloDeNomeCabecalho(lin.nome);
            if (porNome) return porNome;
        }

        // Fallback: layout_group / linha_cabecalho do componente no rascunho.
        var achou = '';
        (((rascunhoAtual || {}).componentes) || []).some(function (c) {
            if (!c || String(c.codigo || '').toLowerCase() !== cod) return false;
            var cfg = configDoComp(c);
            achou = chaveCicloDeGrupoLayout(cfg.layout_group)
                || chaveCicloDeNomeCabecalho(cfg.linha_cabecalho || c.linha_cabecalho);
            return !!achou;
        });
        if (achou) return achou;

        return 'outros';
    }

    function rotuloCicloPalette(chave) {
        if (chave.indexOf('p-') === 0) {
            var n = Number(chave.slice(2)) || 0;
            return rotuloPeriodoNumero(n) || (n + 'º período');
        }
        if (chave === 's1') return '1º semestre';
        if (chave === 's2') return '2º semestre';
        if (chave === 'final') return 'Recuperação Final';
        return 'Geral / outros';
    }

    function ordemCicloPalette(chave) {
        if (chave === 'calc') return -10;
        if (chave.indexOf('p-') === 0) return Number(chave.slice(2)) || 0;
        if (chave === 's1') return 100;
        if (chave === 's2') return 101;
        if (chave === 'final') return 200;
        return 900;
    }

    function ordemLeafPalette(codigo) {
        var cod = String(codigo || '').toLowerCase();
        var m = cod.match(/^b[1-4]_(.+)$/);
        var leaf = m ? m[1] : cod;
        var pref = {
            pp: 1, pu: 2, ch: 3, pe: 4, mu: 5, fal: 6, faltas: 6,
            rec: 7, ms: 8, ma: 9, pf: 10, mf: 11, situacao: 12
        };
        if (pref[leaf] != null) return pref[leaf];
        var nome = String(nomeBlocoCalc(codigo) || tituloColuna(codigo) || '').toUpperCase().trim();
        if (nome === 'MU' || /^MU\b/.test(nome)) return 5;
        if (nome === 'MS' || /^MS\b/.test(nome)) return 8;
        if (nome === 'PF' || /PROVA\s*FINAL|RECUPERA/.test(nome)) return 10;
        if (leaf.indexOf('rec') === 0 || nome.indexOf('REC') === 0) return 7;
        if (leaf.indexOf('ms') === 0) return 8;
        if (ehCodigoBlocoCalc(codigo)) return 5;
        return 50;
    }

    function rotuloPaletteItem(codigo, labelBase) {
        var nome = String(labelBase || nomeBlocoCalc(codigo) || tituloColuna(codigo) || pecaLabel(codigo) || codigo).trim();
        if (!ehCodigoBlocoCalc(codigo)) {
            var sig = siglaTituloColunaJs(codigo, nome) || nome;
            if (/^b[1-4]_/.test(String(codigo)) || /^s[12]_/.test(String(codigo))) return sig;
            return nome;
        }
        // MU · I BIMESTRE — distingue blocos com o mesmo nome em ciclos diferentes.
        var ciclo = chaveCicloPalette(codigo);
        if (ciclo && ciclo !== 'outros' && ciclo !== 'calc') {
            var rotCiclo = rotuloCicloPalette(ciclo);
            // Encurta "1º Bimestre" → "1º"
            var curto = rotCiclo.replace(/\s*bimestre\s*/i, '').replace(/\s*semestre\s*/i, ' sem').trim();
            return nome + ' · ' + curto;
        }
        return nome;
    }

    function listaCodigosBlocosCalcPalette() {
        var out = [];
        var visto = {};
        function push(cod) {
            cod = String(cod || '');
            var low = cod.toLowerCase();
            if (!cod || visto[low] || cod === '_semanal') return;
            if (estado.bloco_calc && (cod === estado.bloco_calc || low === String(estado.bloco_calc).toLowerCase())) return;
            if (!ehCodigoBlocoCalc(cod)) return;
            visto[low] = true;
            out.push(cod);
        }
        (estado.blocos_calc || []).forEach(push);
        Object.keys(estado.formulas_blocos || {}).forEach(push);
        Object.keys(estado.nomes_blocos || {}).forEach(function (cod) {
            if (ehCodigoBlocoCalc(cod)) push(cod);
        });
        (estado.linhas_layout || []).forEach(function (lin) {
            (lin && lin.colunas || []).forEach(push);
        });
        (((rascunhoAtual || {}).componentes) || []).forEach(function (c) {
            if (c && (c.source_type === 'calculado' || ehCodigoBlocoCalc(c.codigo))) push(c.codigo);
        });
        return out;
    }

    function htmlPalettePecasExibir() {
        var visto = {};
        var itens = [];
        function add(value, label, forcarChave) {
            value = String(value || '');
            var low = value.toLowerCase();
            if (!value || visto[low] || value === '_semanal') return;
            if (estado.bloco_calc) {
                var aberto = String(estado.bloco_calc);
                if (value === aberto || low === aberto.toLowerCase()) return;
                if (value === pecaCodigoQuadro(aberto) || low === String(pecaCodigoQuadro(aberto)).toLowerCase()) return;
            }
            visto[low] = true;
            // Não marca pecaCodigoQuadro para blocos calc — evita sumir MU duplicado.
            if (!ehCodigoBlocoCalc(value)) {
                var quadro = String(pecaCodigoQuadro(value) || '').toLowerCase();
                if (quadro) visto[quadro] = true;
            }
            var rotulo = rotuloPaletteItem(value, label);
            itens.push({
                value: value,
                label: rotulo,
                calc: ehCodigoBlocoCalc(value),
                chave: forcarChave || chaveCicloPalette(value)
            });
        }

        // 1) Blocos de cálculo criados antes — seção própria no topo.
        listaCodigosBlocosCalcPalette().forEach(function (cod) {
            add(cod, nomeBlocoCalc(cod) || tituloColuna(cod), 'calc');
        });

        (estado.pecas || []).forEach(function (k) {
            if (!k || ehCodigoBlocoCalc(k)) return;
            add(k, pecaLabel(k));
        });
        if ((estado.pecas || []).indexOf('semanal') >= 0) add('media_sem', nomeBlocoCalc('media_sem'));

        var pv = previewEfetivo();
        ((pv && pv.colunas) || []).forEach(function (c) {
            if (!c || c.travada || c.codigo === '_semanal' || c.tipo === 'semana_grupo') return;
            if (ehCodigoBlocoCalc(c.codigo)) return; // já no grupo calc
            add(c.codigo, c.nome || nomeBlocoCalc(c.codigo));
        });
        ((pv && pv.tabelas) || []).forEach(function (t) {
            (t.outras || []).concat(t.semanas || []).forEach(function (c) {
                if (!c || !c.codigo || ehCodigoBlocoCalc(c.codigo)) return;
                add(c.codigo, c.nome || nomeBlocoCalc(c.codigo));
            });
        });
        ((rascunhoAtual && rascunhoAtual.componentes) || []).forEach(function (c) {
            if (!c || !c.codigo || c.codigo === '_semanal' || ehCodigoBlocoCalc(c.codigo)) return;
            add(c.codigo, c.nome || nomeBlocoCalc(c.codigo));
        });
        // Também espelha calc nos ciclos (PP…MU…FAL), além da seção amarela.
        listaCodigosBlocosCalcPalette().forEach(function (cod) {
            var ciclo = chaveCicloPalette(cod);
            if (!ciclo || ciclo === 'outros') return;
            // Permite o mesmo código em "calc" e no bimestre (outra entrada visual).
            var chaveDup = ciclo;
            var jaNoCiclo = (itens || []).some(function (it) {
                return it.chave === chaveDup && String(it.value).toLowerCase() === String(cod).toLowerCase();
            });
            if (jaNoCiclo) return;
            itens.push({
                value: cod,
                label: nomeBlocoCalc(cod) || tituloColuna(cod) || cod,
                calc: true,
                chave: chaveDup
            });
        });

        // Todos os eventos do ano — mesmo sem chip no quadro (só entram no cálculo).
        (eventosAvaliativosDoAnoQuadro() || []).forEach(function (ev) {
            var cod = codigoPreferidoEvento(ev);
            if (!cod) cod = codigoColunaDoEventoJs(ev, {});
            if (!cod) return;
            var label = String(siglaDoEvento(ev) || '').trim() || String(ev.nome || cod).trim();
            add(cod, label);
        });

        if (!itens.length) {
            return '<p class="text-xs text-gray-500">Volte em Tipo de Notas e marque ao menos uma.</p>';
        }

        var grupos = {};
        itens.forEach(function (it) {
            if (!grupos[it.chave]) grupos[it.chave] = [];
            grupos[it.chave].push(it);
        });
        var chaves = Object.keys(grupos).sort(function (a, b) {
            return ordemCicloPalette(a) - ordemCicloPalette(b);
        });

        if (paletteCicloAtivo !== 'todos' && chaves.indexOf(paletteCicloAtivo) < 0) {
            paletteCicloAtivo = 'todos';
        }
        var chavesVisiveis = paletteCicloAtivo === 'todos'
            ? chaves
            : chaves.filter(function (c) { return c === paletteCicloAtivo; });

        var html = '<div class="bw-palette-shell">';
        html += '<div class="bw-palette-tabs" role="tablist" aria-label="Filtrar por período">';
        html += '<button type="button" class="bw-palette-tab' + (paletteCicloAtivo === 'todos' ? ' ativo' : '')
            + '" data-ciclo="todos" role="tab" aria-selected="' + (paletteCicloAtivo === 'todos' ? 'true' : 'false') + '">Todos</button>';
        chaves.forEach(function (chave) {
            var ehCalc = chave === 'calc';
            var ativo = paletteCicloAtivo === chave;
            var rotulo = ehCalc ? 'Cálculo' : rotuloCicloPalette(chave);
            html += '<button type="button" class="bw-palette-tab' + (ehCalc ? ' calc' : '') + (ativo ? ' ativo' : '')
                + '" data-ciclo="' + esc(chave) + '" role="tab" aria-selected="' + (ativo ? 'true' : 'false') + '">'
                + esc(rotulo) + '</button>';
        });
        html += '</div>';
        html += '<div class="bw-palette-painel">';
        html += '<div class="bw-palette-grupos">';
        chavesVisiveis.forEach(function (chave) {
            var lista = grupos[chave].slice().sort(function (a, b) {
                var oa = ordemLeafPalette(a.value);
                var ob = ordemLeafPalette(b.value);
                if (oa !== ob) return oa - ob;
                return String(a.label || '').localeCompare(String(b.label || ''), 'pt-BR');
            });
            var ehCalcGrupo = chave === 'calc';
            html += '<div class="bw-palette-grupo' + (ehCalcGrupo ? ' calc' : '') + '">';
            html += '<p class="bw-palette-grupo-titulo">'
                + esc(ehCalcGrupo ? 'Blocos de cálculo (MU, MS…)' : rotuloCicloPalette(chave))
                + '</p>';
            if (ehCalcGrupo) {
                html += '<p class="text-[10px] text-amber-900/80 mb-1.5">Clique para usar no MS/fórmula. Amarelo = bloco que você criou.</p>';
            }
            html += '<div class="bw-palette-grupo-chips">';
            lista.forEach(function (it) {
                html += chipHtml({ type: 'peca', value: it.value, label: it.label, calc: !!it.calc }, -1, true);
            });
            html += '</div></div>';
        });
        html += '</div></div></div>';
        return html;
    }

    function adicionarColunaManual() {
        if (!estado) return;
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        var codigo = novoCodigoColunaManual();
        if (estado.colunas_ordem.indexOf(codigo) < 0) estado.colunas_ordem.push(codigo);
        estado.nomes_blocos[codigo] = 'Nova coluna';
        garantirComponenteManualNoRascunho(codigo, 'Nova coluna');
        marcarEdicaoManual();
        previewAtual = null;
        if (estado.passo !== 'formula' && estado.passo !== 'revisar') {
            estado.passo = 'formula';
            renderAll();
        } else {
            renderRevisarDinamico();
        }
        agendarMontar();
    }

    function novoCodigoColunaManual() {
        var base = 'col';
        var n = 1;
        var usados = {};
        (estado.colunas_ordem || []).forEach(function (c) { usados[String(c)] = true; });
        (((rascunhoAtual || {}).componentes) || []).forEach(function (c) {
            if (c && c.codigo) usados[String(c.codigo)] = true;
        });
        Object.keys(estado.nomes_blocos || {}).forEach(function (c) { usados[c] = true; });
        while (usados[base + n]) n += 1;
        return base + n;
    }

    function garantirComponenteManualNoRascunho(codigo, nome) {
        codigo = String(codigo || '');
        if (!codigo) return;
        [rascunhoAtual, estado && estado.rascunho_preservado].forEach(function (r) {
            if (!r) return;
            if (!Array.isArray(r.componentes)) r.componentes = [];
            var achou = false;
            r.componentes.forEach(function (c) {
                if (!c || String(c.codigo || '') !== codigo) return;
                achou = true;
                c.nome = nome || c.nome || codigo;
                c.source_type = c.source_type || 'nenhuma';
            });
            if (!achou) {
                r.componentes.push({
                    codigo: codigo,
                    nome: nome || codigo,
                    source_type: 'nenhuma',
                    calc_type: 'media',
                    peso: 1,
                    escala_max: 10,
                    obrigatorio: 0,
                    config: {}
                });
            }
        });
    }

    function adicionarBlocoMedia() {
        if (estado.bloco_calc) gravarTokensBlocoAberto();
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!Array.isArray(estado.blocos_calc)) estado.blocos_calc = [];
        var codigo = novoCodigoBloco();
        mostrarColuna(codigo);
        if (estado.blocos_calc.indexOf(codigo) < 0) estado.blocos_calc.push(codigo);
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        if (estado.colunas_ordem.indexOf(codigo) < 0) {
            // Se há coluna marcada (borda azul), encaixa o bloco logo depois dela.
            var marcadas = codigosMarcados().map(function (c) { return String(c || '').toLowerCase(); });
            var pos = -1;
            if (marcadas.length === 1) {
                pos = estado.colunas_ordem.map(function (c) {
                    return String(c || '').toLowerCase();
                }).indexOf(marcadas[0]);
            }
            if (pos >= 0) estado.colunas_ordem.splice(pos + 1, 0, codigo);
            else estado.colunas_ordem.push(codigo);
        }
        // Bloco novo abre sem fórmula — o usuário monta (atalhos "Média das peças" etc. continuam).
        if (!Array.isArray(estado.formulas_blocos[codigo])) {
            estado.formulas_blocos[codigo] = [];
        }
        if (!estado.nomes_blocos[codigo]) {
            estado.nomes_blocos[codigo] = codigo === 'media_bim' ? 'Média Bim'
                : (codigo === 'media_final' ? 'Resultado Final' : 'Nova média');
        }
        if (!estado.escalas_blocos || typeof estado.escalas_blocos !== 'object') estado.escalas_blocos = {};
        if (estado.escalas_blocos[codigo] == null) estado.escalas_blocos[codigo] = 10;
        estado.quadro_chips_manuais = 1;
        marcarEdicaoManual();
        previewAtual = null;
        if (estado.passo !== 'formula' && estado.passo !== 'revisar') {
            estado.passo = 'formula';
            renderAll();
            abrirFormulaBloco(codigo);
        } else {
            renderRevisarDinamico();
            abrirFormulaBloco(codigo);
        }
        agendarMontar();
    }

    function codigosEquivalentesColuna(codigo) {
        var set = {};
        codigo = String(codigo || '');
        if (!codigo) return set;
        set[codigo] = true;
        var quadro = pecaCodigoQuadro(codigo);
        if (quadro) set[quadro] = true;
        var aliases = {
            media_sem: 'semanal', semanal: 'media_sem',
            prova_bim: 'bimestral', bimestral: 'prova_bim',
            rec: 'recuperacao', recuperacao: 'rec',
            trab: 'trabalho', trabalho: 'trab',
            part: 'participacao', participacao: 'part'
        };
        if (aliases[codigo]) set[aliases[codigo]] = true;
        return set;
    }

    function pintarTituloNasColunas(codigo, nome) {
        codigo = String(codigo || '');
        nome = String(nome || '').trim();
        if (!codigo || !nome) return;
        var equivalentes = codigosEquivalentesColuna(codigo);
        function paint(c) {
            if (!c) return;
            if (equivalentes[String(c.codigo || '')]) c.nome = nome;
        }
        if (previewAtual) {
            (previewAtual.colunas || []).forEach(paint);
            (previewAtual.tabelas || []).forEach(function (t) {
                (t.outras || []).forEach(paint);
                (t.semanas || []).forEach(paint);
                (t.colunas || []).forEach(paint);
            });
        }
        [rascunhoAtual, estado && estado.rascunho_preservado].forEach(function (r) {
            if (!r || !Array.isArray(r.componentes)) return;
            r.componentes.forEach(paint);
        });
    }

    function aplicarTituloBloco(codigo, nome) {
        codigo = String(codigo || '');
        nome = String(nome || '').trim().slice(0, 60);
        if (!estado || !codigo || codigo === '_semanal' || /^s[1-8]$/.test(codigo)) return;
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object' || Array.isArray(estado.nomes_blocos)) {
            estado.nomes_blocos = {};
        }
        if (!estado.nomes_limpos || typeof estado.nomes_limpos !== 'object' || Array.isArray(estado.nomes_limpos)) {
            estado.nomes_limpos = {};
        }
        var equivalentes = codigosEquivalentesColuna(codigo);
        if (nome) {
            Object.keys(equivalentes).forEach(function (cod) {
                delete estado.nomes_blocos[cod];
                delete estado.nomes_limpos[cod];
            });
            estado.nomes_blocos[codigo] = nome;
        } else {
            Object.keys(equivalentes).forEach(function (cod) {
                delete estado.nomes_blocos[cod];
                if (cod !== '_semanal' && !/^s[1-8]$/.test(cod)) estado.nomes_limpos[cod] = 1;
            });
        }
        var visivel = nome || nomeBlocoCalc(codigo);
        document.querySelectorAll('#bw-colunas-lista .bw-col-chip').forEach(function (chip) {
            if (chip.getAttribute('data-codigo') !== codigo) return;
            var el = chip.querySelector('.bw-col-nome');
            if (el) el.textContent = visivel;
        });
        var rotulo = document.getElementById('bw-formula-bloco-nome');
        if (rotulo && estado.bloco_calc === codigo) rotulo.textContent = visivel;
        if (visivel) pintarTituloNasColunas(codigo, visivel);
        var pvEl = document.getElementById('bw-preview-wrap');
        if (pvEl && previewAtual) pvEl.innerHTML = htmlPreview(previewAtual);
        var pal = document.getElementById('bw-formula-pecas-pal');
        if (pal) pal.innerHTML = htmlPalettePecasExibir();
        renderFormulaCanvas();
    }

    function fecharFormulaBloco() {
        if (!estado) return;
        estado.bloco_calc = '';
        estado.materia_calc = 0;
        estado.formula_tokens = [];
        estado.titulo_codigo = '';
        var ed = document.getElementById('bw-formula-editor');
        if (ed) ed.classList.add('hidden');
        var lista = document.getElementById('bw-colunas-lista');
        if (lista) {
            lista.querySelectorAll('.bw-col-chip.aberta').forEach(function (el) {
                el.classList.remove('aberta');
            });
        }
    }

    function gravarFlagFecharAposSalvar(on) {
        fecharFormulaAposSalvar = !!on;
        try {
            if (window.localStorage) {
                localStorage.setItem('bw_fechar_formula_apos_salvar', fecharFormulaAposSalvar ? '1' : '0');
            }
        } catch (eGravaFecha) { /* ignore */ }
    }

    function lerFlagFecharAposSalvar() {
        var el = document.getElementById('bw-formula-fechar-apos');
        if (el) gravarFlagFecharAposSalvar(!!el.checked);
        return !!fecharFormulaAposSalvar;
    }

    function salvarBlocoCalc() {
        if (!estado || !estado.bloco_calc) {
            var edVazio = document.getElementById('bw-formula-editor');
            if (edVazio) edVazio.classList.add('hidden');
            return;
        }
        var inp = document.getElementById('bw-formula-bloco-nome-input');
        var nome = String((inp && inp.value) || '').trim().slice(0, 60);
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!Array.isArray(estado.blocos_calc)) estado.blocos_calc = [];
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        aplicarTituloBloco(estado.bloco_calc, nome);
        flushEscalaAberta();
        var codigoSalvo = estado.bloco_calc;
        if (codigoSalvo) mostrarColuna(codigoSalvo);
        if (codigoSalvo && estado.blocos_calc.indexOf(codigoSalvo) < 0) {
            estado.blocos_calc.push(codigoSalvo);
        }
        if (codigoSalvo && estado.colunas_ordem.indexOf(codigoSalvo) < 0) {
            estado.colunas_ordem.push(codigoSalvo);
        }
        formulaVersao++;
        gravarTokensBlocoAberto();
        marcarEdicaoManual();
        var fecharAgora = lerFlagFecharAposSalvar();
        if (fecharAgora) {
            fecharFormulaBloco();
        } else {
            abrirFormulaBloco(codigoSalvo);
        }
        var avisoSalvo = document.getElementById('bw-formula-salva');
        if (avisoSalvo) {
            avisoSalvo.textContent = 'Salvo.';
            avisoSalvo.classList.remove('hidden');
        }
        // Atualiza a ordem das colunas na hora — não espera o remount apagar o chip amarelo.
        if (estado.passo === 'formula' || estado.passo === 'revisar') {
            renderRevisarDinamico();
            if (fecharAgora) {
                fecharFormulaBloco();
            } else {
                abrirFormulaBloco(codigoSalvo);
                avisoSalvo = document.getElementById('bw-formula-salva');
                if (avisoSalvo) {
                    avisoSalvo.textContent = 'Salvo.';
                    avisoSalvo.classList.remove('hidden');
                }
            }
        }
        agendarMontar();
    }

    function pecaDaColuna(codigo) {
        var mapa = {
            _semanal: 'semanal',
            media_sem: 'semanal',
            prova_bim: 'bimestral',
            enac: 'enac',
            trab: 'trabalho',
            part: 'participacao',
            rec: 'recuperacao',
            jornada: 'jornada'
        };
        return mapa[codigo] || '';
    }

    function ocultarColuna(codigo) {
        if (!estado || !codigo) return;
        if (!Array.isArray(estado.colunas_ocultas)) estado.colunas_ocultas = [];
        var low = String(codigo).toLowerCase();
        var ja = (estado.colunas_ocultas || []).some(function (c) {
            return String(c || '').toLowerCase() === low;
        });
        if (!ja) estado.colunas_ocultas.push(codigo);
    }

    function colunaOculta(codigo) {
        if (!estado || !codigo) return false;
        var low = String(codigo).toLowerCase();
        return (estado.colunas_ocultas || []).some(function (c) {
            return String(c || '').toLowerCase() === low;
        });
    }

    function mostrarColuna(codigo) {
        if (!estado || !codigo) return;
        var low = String(codigo).toLowerCase();
        estado.colunas_ocultas = (estado.colunas_ocultas || []).filter(function (c) {
            return String(c || '').toLowerCase() !== low;
        });
    }

    function tirarColunaDoRascunho(codigo) {
        var low = String(codigo || '').toLowerCase();
        if (!low) return;
        function filtrarComps(lista) {
            return (lista || []).filter(function (c) {
                if (!c) return false;
                return String(c.codigo || '').toLowerCase() !== low;
            });
        }
        if (rascunhoAtual && Array.isArray(rascunhoAtual.componentes)) {
            rascunhoAtual.componentes = filtrarComps(rascunhoAtual.componentes);
        }
        if (estado && estado.rascunho_preservado && Array.isArray(estado.rascunho_preservado.componentes)) {
            estado.rascunho_preservado.componentes = filtrarComps(estado.rascunho_preservado.componentes);
        }
        if (previewAtual && Array.isArray(previewAtual.colunas)) {
            previewAtual.colunas = previewAtual.colunas.filter(function (c) {
                return c && String(c.codigo || '').toLowerCase() !== low;
            });
        }
    }

    function removerBlocoCalc(codigo) {
        if (!codigo) return;
        var low = String(codigo).toLowerCase();
        var ehCalc = ehCodigoBlocoCalc(codigo);
        ocultarColuna(codigo);
        if (codigo === '_semanal' || codigo === 'media_sem' || low === '_semanal' || low === 'media_sem') {
            ocultarColuna('_semanal');
            ocultarColuna('media_sem');
            ['s1', 's2', 's3', 's4', 's5', 's6', 's7', 's8'].forEach(ocultarColuna);
        }
        estado.colunas_ordem = (estado.colunas_ordem || []).filter(function (c) {
            return String(c || '').toLowerCase() !== low;
        });
        if (Array.isArray(estado.linhas_layout)) {
            estado.linhas_layout = estado.linhas_layout.map(function (lin) {
                if (!lin) return lin;
                lin.colunas = (lin.colunas || []).filter(function (c) {
                    return String(c || '').toLowerCase() !== low;
                });
                return lin;
            }).filter(function (lin) { return lin && String(lin.nome || '').trim(); });
        }
        if (colunasMarcadas && colunasMarcadas[codigo]) delete colunasMarcadas[codigo];
        if (colunasMarcadas && colunasMarcadas[low]) delete colunasMarcadas[low];

        // Evento/nota: some do quadro do boletim, mas permanece na fórmula.
        if (!ehCalc) {
            estado.quadro_chips_manuais = 1;
            aplicarOrdemNoPreview(previewAtual);
            renderRevisarDinamico();
            agendarMontar();
            return;
        }

        var peca = pecaDaColuna(codigo) || pecaDaColuna(low);
        if (peca) {
            estado.pecas = (estado.pecas || []).filter(function (p) { return p !== peca; });
        }
        estado.blocos_calc = (estado.blocos_calc || []).filter(function (c) {
            return String(c || '').toLowerCase() !== low;
        });
        if (estado.formulas_blocos) {
            Object.keys(estado.formulas_blocos).forEach(function (k) {
                if (String(k).toLowerCase() === low) delete estado.formulas_blocos[k];
            });
        }
        if (estado.formulas_materias_blocos) {
            Object.keys(estado.formulas_materias_blocos).forEach(function (k) {
                if (String(k).toLowerCase() === low) delete estado.formulas_materias_blocos[k];
            });
        }
        if (estado.nomes_blocos) {
            Object.keys(estado.nomes_blocos).forEach(function (k) {
                if (String(k).toLowerCase() === low) delete estado.nomes_blocos[k];
            });
        }
        if (estado.escalas_blocos) {
            Object.keys(estado.escalas_blocos).forEach(function (k) {
                if (String(k).toLowerCase() === low) delete estado.escalas_blocos[k];
            });
        }
        if (String(estado.bloco_calc || '').toLowerCase() === low) {
            estado.bloco_calc = '';
            estado.materia_calc = 0;
            estado.formula_tokens = [];
            var ed = document.getElementById('bw-formula-editor');
            if (ed) ed.classList.add('hidden');
        }
        tirarColunaDoRascunho(codigo);
        estado.quadro_chips_manuais = 1;
        aplicarOrdemNoPreview(previewAtual);
        renderRevisarDinamico();
        agendarMontar();
    }

    function limparTodosChipsQuadro() {
        if (!estado) return;
        var codigos = {};
        function marcar(cod) {
            cod = String(cod || '');
            if (!cod || cod === '_semanal') return;
            codigos[cod] = true;
        }
        (estado.colunas_ordem || []).forEach(marcar);
        (estado.blocos_calc || []).forEach(marcar);
        Object.keys(estado.formulas_blocos || {}).forEach(marcar);
        (estado.linhas_layout || []).forEach(function (lin) {
            (lin && lin.colunas || []).forEach(marcar);
        });
        (((rascunhoAtual || {}).componentes) || []).forEach(function (c) {
            if (c && c.codigo) marcar(c.codigo);
        });
        ((previewAtual && previewAtual.colunas) || []).forEach(function (c) {
            if (c && c.codigo) marcar(c.codigo);
        });
        var lista = Object.keys(codigos);
        if (!lista.length) {
            avisoLinha = { tipo: 'ok', texto: 'Não há chips no quadro para excluir.' };
            renderRevisarDinamico();
            return;
        }
        // Remove em lote sem remount a cada item.
        lista.forEach(function (codigo) {
            var low = String(codigo).toLowerCase();
            ocultarColuna(codigo);
            if (codigo === '_semanal' || codigo === 'media_sem' || low === '_semanal' || low === 'media_sem') {
                ocultarColuna('_semanal');
                ocultarColuna('media_sem');
                ['s1', 's2', 's3', 's4', 's5', 's6', 's7', 's8'].forEach(ocultarColuna);
            }
            var peca = pecaDaColuna(codigo) || pecaDaColuna(low);
            if (peca) {
                estado.pecas = (estado.pecas || []).filter(function (p) { return p !== peca; });
            }
            tirarColunaDoRascunho(codigo);
        });
        estado.blocos_calc = [];
        estado.formulas_blocos = {};
        estado.formulas_materias_blocos = {};
        estado.nomes_blocos = {};
        estado.escalas_blocos = {};
        estado.colunas_ordem = [];
        estado.linhas_layout = [];
        estado.cabecalhos_manuais = 1;
        estado.titulos_periodo_ok = {};
        estado.limpar_titulos_periodo = 1;
        estado.bloco_calc = '';
        estado.materia_calc = 0;
        estado.formula_tokens = [];
        estado.quadro_chips_manuais = 1;
        colunasMarcadas = {};
        var edAll = document.getElementById('bw-formula-editor');
        if (edAll) edAll.classList.add('hidden');
        if (previewAtual) previewAtual.colunas = [];
        avisoLinha = { tipo: 'ok', texto: 'Todos os chips foram excluídos. Clique nos eventos à esquerda para montar de novo.' };
        aplicarOrdemNoPreview(previewAtual);
        renderRevisarDinamico();
        agendarMontar();
    }

    function dropIndexFormula(e, canvas) {
        var chips = canvas.querySelectorAll('.bw-chip[data-idx]');
        var at = chips.length;
        for (var i = 0; i < chips.length; i++) {
            var rect = chips[i].getBoundingClientRect();
            if (e.clientX < rect.left + rect.width / 2) {
                at = i;
                break;
            }
        }
        return at;
    }

    function bindFormulaBuilderOnce() {
        if (bodyEl._bwFormulaBound) return;
        bodyEl._bwFormulaBound = true;
        var formulaArrastando = false;
        bodyEl.addEventListener('change', function (e) {
            if (!e.target || e.target.id !== 'bw-formula-fechar-apos') return;
            gravarFlagFecharAposSalvar(!!e.target.checked);
        });
        bodyEl.addEventListener('click', function (e) {
            if (e.target.closest('#bw-formula-salvar')) {
                e.preventDefault();
                salvarBlocoCalc();
                return;
            }
            var tabCiclo = e.target.closest('.bw-palette-tab');
            if (tabCiclo && bodyEl.contains(tabCiclo)) {
                e.preventDefault();
                var ciclo = String(tabCiclo.getAttribute('data-ciclo') || 'todos');
                if (ciclo === paletteCicloAtivo) return;
                paletteCicloAtivo = ciclo || 'todos';
                var palTab = document.getElementById('bw-formula-pecas-pal');
                if (palTab) palTab.innerHTML = htmlPalettePecasExibir();
                return;
            }
            var atalhoEsc = e.target.closest('.bw-escala-atalho');
            if (atalhoEsc) {
                e.preventDefault();
                if (!estado || !estado.bloco_calc) return;
                aplicarEscalaBloco(estado.bloco_calc, atalhoEsc.getAttribute('data-escala'));
                agendarMontar();
                return;
            }
            if (e.target.closest('#bw-excecao-add')) {
                var selEx = document.getElementById('bw-excecao-materia');
                var midAdd = selEx ? Number(selEx.value || 0) : 0;
                if (midAdd > 0) abrirExcecaoMateria(midAdd);
                return;
            }
            if (e.target.closest('#bw-excecao-voltar')) {
                voltarFormulaGeral();
                return;
            }
            var edEx = e.target.closest('.bw-excecao-editar');
            if (edEx) {
                abrirExcecaoMateria(edEx.getAttribute('data-mid'));
                return;
            }
            var rmEx = e.target.closest('.bw-excecao-remover');
            if (rmEx) {
                removerExcecaoMateria(rmEx.getAttribute('data-mid'));
                return;
            }
            if (e.target.closest('#bw-formula-media')) {
                estado.formula_tokens = tokensMediaDasPecas();
                afterFormulaChange();
                return;
            }
            if (e.target.closest('#bw-formula-max')) {
                estado.formula_tokens = tokensMaiorDuas();
                afterFormulaChange();
                return;
            }
            if (e.target.closest('#bw-formula-substituta')) {
                estado.formula_tokens = tokensMediaComSubstituta();
                afterFormulaChange();
                return;
            }
            if (e.target.closest('#bw-formula-composicao')) {
                estado.formula_tokens = tokensMediaComRec();
                afterFormulaChange();
                return;
            }
            if (e.target.closest('#bw-formula-limpar')) {
                estado.formula_tokens = [];
                afterFormulaChange();
                return;
            }
            if (e.target.closest('#bw-formula-num-add')) {
                var inp = document.getElementById('bw-formula-num');
                var v = String((inp && inp.value) || '').trim().replace(',', '.');
                if (!/^\d+(\.\d+)?$/.test(v)) return;
                var toksNum = (estado.formula_tokens || []);
                var lastTok = toksNum[toksNum.length - 1];
                if (lastTok && (lastTok.type === 'peca' || lastTok.type === 'num' || (lastTok.type === 'op' && lastTok.value === ')'))) {
                    pushToken({ type: 'op', value: '/', label: '÷' });
                }
                pushToken({ type: 'num', value: v, label: v });
                if (inp) inp.value = '';
                return;
            }
            var x = e.target.closest('.bw-chip-x');
            if (x && bodyEl.contains(x)) {
                e.preventDefault();
                removeToken(parseInt(x.getAttribute('data-idx'), 10) || 0);
                return;
            }
            var pal = e.target.closest('.bw-pal-chip');
            if (pal && bodyEl.contains(pal)) {
                if (formulaArrastando) return;
                pushToken(tokenFromEl(pal));
            }
        });
        bodyEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target && (e.target.id === 'bw-formula-bloco-nome-input' || e.target.id === 'bw-formula-bloco-escala-input')) {
                e.preventDefault();
                salvarBlocoCalc();
                return;
            }
            if (e.key !== 'Enter' || !e.target || e.target.id !== 'bw-formula-num') return;
            e.preventDefault();
            var btn = document.getElementById('bw-formula-num-add');
            if (btn) btn.click();
        });
        bodyEl.addEventListener('input', function (e) {
            if (!e.target || !estado || !estado.bloco_calc) return;
            if (e.target.id === 'bw-formula-bloco-nome-input') {
                if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
                aplicarTituloBloco(estado.bloco_calc, String(e.target.value || ''));
                return;
            }
            if (e.target.id === 'bw-formula-bloco-escala-input') {
                aplicarEscalaBloco(estado.bloco_calc, e.target.value);
            }
        });
        bodyEl.addEventListener('change', function (e) {
            if (!e.target || e.target.id !== 'bw-formula-bloco-escala-input' || !estado || !estado.bloco_calc) return;
            aplicarEscalaBloco(estado.bloco_calc, e.target.value);
            agendarMontar();
        });
        bodyEl.addEventListener('dragstart', function (e) {
            var pal = e.target.closest('.bw-pal-chip');
            if (pal) {
                formulaArrastando = true;
                formulaDrag = { kind: 'palette', token: tokenFromEl(pal) };
                e.dataTransfer.setData('text/plain', 'palette');
                e.dataTransfer.effectAllowed = 'copy';
                return;
            }
            var chip = e.target.closest('#bw-formula-canvas .bw-chip');
            if (chip) {
                formulaArrastando = true;
                formulaDrag = { kind: 'canvas', idx: parseInt(chip.getAttribute('data-idx'), 10) };
                e.dataTransfer.setData('text/plain', 'canvas');
                e.dataTransfer.effectAllowed = 'move';
                chip.classList.add('opacity-50');
            }
        });
        bodyEl.addEventListener('dragend', function (e) {
            var chip = e.target.closest('.bw-chip');
            if (chip) chip.classList.remove('opacity-50');
            setTimeout(function () { formulaArrastando = false; }, 0);
        });
        bodyEl.addEventListener('dragover', function (e) {
            var canvas = e.target.closest('#bw-formula-canvas');
            if (!canvas) return;
            e.preventDefault();
            canvas.classList.add('drag-over');
        });
        bodyEl.addEventListener('dragleave', function (e) {
            var canvas = document.getElementById('bw-formula-canvas');
            if (!canvas) return;
            if (e.relatedTarget && canvas.contains(e.relatedTarget)) return;
            canvas.classList.remove('drag-over');
        });
        bodyEl.addEventListener('drop', function (e) {
            var canvas = e.target.closest('#bw-formula-canvas');
            if (!canvas) return;
            e.preventDefault();
            canvas.classList.remove('drag-over');
            var at = dropIndexFormula(e, canvas);
            if (formulaDrag && formulaDrag.kind === 'palette' && formulaDrag.token) {
                pushToken(formulaDrag.token, at);
            } else if (formulaDrag && formulaDrag.kind === 'canvas') {
                moveToken(formulaDrag.idx, at);
            }
            formulaDrag = null;
        });
    }

    function casasDoBoletimVinculado() {
        var bolId = estado && Number(estado.boletim_id);
        if (!(bolId > 0) || !catalogo || !Array.isArray(catalogo.boletins)) return null;
        for (var i = 0; i < catalogo.boletins.length; i++) {
            var b = catalogo.boletins[i];
            if (Number(b.id) === bolId && b.decimal_places != null && b.decimal_places !== '') {
                return Number(b.decimal_places) === 1 ? 1 : 2;
            }
        }
        return null;
    }

    function casasDecimaisPreview() {
        var doBoletim = casasDoBoletimVinculado();
        if (doBoletim != null) {
            if (estado) estado.decimal_places = doBoletim;
            return doBoletim;
        }
        return (estado && Number(estado.decimal_places) === 1) ? 1 : 2;
    }

    function fmtPreviewNota(v) {
        if (v == null || v === '' || v === '—') return '—';
        if (typeof v === 'string' && !/^-?\d/.test(v)) return esc(v);
        var n = Number(v);
        if (!isFinite(n)) return '—';
        var casas = casasDecimaisPreview();
        var fator = Math.pow(10, casas);
        n = Math.round(n * fator) / fator;
        return n.toFixed(casas).replace('.', ',');
    }

    function notaDaLinhaPreview(notas, codigo) {
        if (!notas || typeof notas !== 'object') return null;
        if (Object.prototype.hasOwnProperty.call(notas, codigo) && notas[codigo] != null && notas[codigo] !== '') {
            return notas[codigo];
        }
        var alvo = String(codigo || '').toLowerCase();
        var keys = Object.keys(notas);
        for (var i = 0; i < keys.length; i++) {
            if (String(keys[i]).toLowerCase() === alvo) {
                return notas[keys[i]];
            }
        }
        return null;
    }

    function roundPreviewValor(v) {
        var n = Number(v);
        if (!isFinite(n)) return v;
        if (!estado || estado.round_mode !== 'half') {
            var fatorCasas = Math.pow(10, casasDecimaisPreview());
            return Math.round(n * fatorCasas) / fatorCasas;
        }
        var base = Math.floor(n);
        var dec = n - base;
        if (dec < 0.25) return base;
        if (dec < 0.75) return base + 0.5;
        return base + 1;
    }

    function arredondamentoGrupoAtual() {
        var gl = (estado && estado.grupo_linha) || {};
        if (gl.arredondamento === 'filhas' || gl.arredondamento === 'mae') return gl.arredondamento;
        return 'todos';
    }

    /** quem: mae = linha da área; filha = matéria de dentro. */
    function roundNotaGrupo(v, quem) {
        var n = Number(v);
        if (!isFinite(n)) return v;
        var modo = arredondamentoGrupoAtual();
        var aplica = quem === 'mae'
            ? (modo === 'mae' || modo === 'todos')
            : (modo === 'filhas' || modo === 'todos');
        if (!aplica) {
            var fatorCasas = Math.pow(10, casasDecimaisPreview());
            return Math.round(n * fatorCasas) / fatorCasas;
        }
        return roundPreviewValor(n);
    }

    function pecaDoCodigoGrupo(codigo) {
        var cod = String(codigo || '').toLowerCase();
        if (!cod || /__(?:n|q)$/.test(cod)) return '';
        if (cod === 'media_sem' || cod === 'semanal' || /^s[1-8]$/.test(cod)) return 'semanal';
        if (cod === 'media_bim' || cod === 'media_final' || cod === 'media_parcial' || cod === 'media_etapa' || cod === 'faltas' || cod === 'resultado') return '';
        if (/^media_[ms]\d+$/.test(cod)) return '';
        if (cod === 'prova_bim' || cod === 'bimestral') return 'bimestral';
        if (cod === 'jornada' || cod.indexOf('jornada') >= 0) return 'jornada';
        if (cod === 'enac' || cod.indexOf('enac') >= 0) return 'enac';
        if (cod === 'trab' || cod === 'trabalho') return 'trabalho';
        if (cod === 'part' || cod === 'participacao') return 'participacao';
        if (cod === 'rec' || cod === 'recuperacao' || cod.indexOf('recup') >= 0) return 'recuperacao';
        if (/^[a-z][a-z0-9_]{0,40}$/.test(cod)) return cod;
        return '';
    }

    function pecaCodigoQuadro(key) {
        var m = {
            semanal: 'media_sem',
            bimestral: 'prova_bim',
            enac: 'enac',
            trabalho: 'trab',
            participacao: 'part',
            recuperacao: 'rec',
            jornada: 'jornada'
        };
        return m[key] || key;
    }

    function pecaNomeQuadro(key) {
        var meta = pecaMeta(key);
        if (meta && meta.label) return meta.label;
        var m = { bimestral: 'Prova Bim', enac: 'ENAC', trabalho: 'Trab', participacao: 'Part', recuperacao: 'Rec', jornada: 'Jornada', semanal: 'Média Sem' };
        return m[key] || key;
    }

    function hashNome(nome) {
        var h = 0;
        String(nome || '').split('').forEach(function (ch) { h = ((h * 31) + ch.charCodeAt(0)) | 0; });
        return Math.abs(h);
    }

    function colunaEhCalculadaFormula(col) {
        if (!col || colunaEhFaltas(col)) return false;
        var cod = String(col.codigo || '').toLowerCase();
        if (!cod || cod === 'media_sem') return false;
        if (String(col.source_type || '') === 'calculado' || String(col.tipo || '') === 'calculado') return true;
        return cod === 'media_bim' || cod === 'media_final' || cod === 'media_parcial' || cod === 'media_etapa' || /^media_[ms]\d+$/.test(cod);
    }

    function colunaEhFaltas(col) {
        if (!col) return false;
        if ((col.layout_type || '') === 'faltas') return true;
        if ((col.source_type || '') === 'faltas_evento') return true;
        var cod = String(col.codigo || '').toLowerCase();
        var nome = String(col.nome || '').toLowerCase();
        if (/^b[1-4]_fal(tas)?$/.test(cod) || /(^|_)fal$/.test(cod)) return true;
        return cod.indexOf('falta') >= 0 || nome.indexOf('falta') >= 0 || nome === 'fal';
    }

    function fmtPreviewCelula(v, col) {
        var lt = (col && col.layout_type) || '';
        if (lt === 'resultado' && typeof v === 'string') return esc(v);
        if (colunaEhFaltas(col)) {
            if (v == null || v === '' || v === '—') return '—';
            var nf = Number(v);
            if (!isFinite(nf)) return '—';
            return String(Math.round(nf));
        }
        return fmtPreviewNota(v);
    }

    function classePreviewCelula(v, col) {
        if ((col && col.layout_type) === 'resultado') {
            if (v === 'Aprovado') return ' ok';
            if (v === 'Reprovado') return ' nok';
        }
        return '';
    }

    function eventosFaltasDoAno() {
        var ano = Number((estado && estado.ano_letivo) || 0);
        return ((catalogo && catalogo.faltas_eventos) || []).filter(function (ev) {
            var ja = Number(ev.ano_letivo || 0);
            if (ano > 0 && ja > 0 && ja !== ano) return false;
            return true;
        });
    }

    function materiasPreviewLocal(fallback) {
        // Escopo do modelo/regra (já expandido no catálogo) tem prioridade sobre estado cru.
        var doEscopo = [];
        try {
            doEscopo = materiasCatalogoDoEscopo() || [];
        } catch (eEscopo) {
            doEscopo = [];
        }
        if (doEscopo.length) {
            var nomesEscopo = [];
            doEscopo.forEach(function (m) {
                var nome = String((m && m.nome) || '').trim();
                if (nome && nomesEscopo.indexOf(nome) < 0) nomesEscopo.push(nome);
            });
            if (nomesEscopo.length) return nomesEscopo;
        }

        var selecionadas = {};
        (estado && Array.isArray(estado.materias_ids) ? estado.materias_ids : []).forEach(function (id) {
            id = Number(id || 0);
            if (id > 0) selecionadas[id] = true;
        });
        // Oficiais (pai) → incluir desdobramentos na prévia.
        (catalogo.familias_componentes || []).forEach(function (f) {
            var pai = Number(f && f.pai_id ? f.pai_id : 0);
            if (!pai || !selecionadas[pai]) return;
            (f.filhos || []).forEach(function (ch) {
                var cid = Number(ch && ch.id ? ch.id : 0);
                if (cid > 0) selecionadas[cid] = true;
            });
        });
        var filtrar = Object.keys(selecionadas).length > 0;
        var lista = ((catalogo && catalogo.materias) || []).slice().sort(function (a, b) {
            var ia = Number((a && a.id) || 0);
            var ib = Number((b && b.id) || 0);
            var oa = (a && a.ordem != null && a.ordem !== '') ? Number(a.ordem) : (100000 + ia);
            var ob = (b && b.ordem != null && b.ordem !== '') ? Number(b.ordem) : (100000 + ib);
            if (oa !== ob) return oa - ob;
            return ia - ib;
        });
        var nomes = [];
        lista.forEach(function (m) {
            var id = Number(m && m.id || 0);
            if (id <= 0) return;
            if (filtrar && !selecionadas[id]) return;
            var nome = String((m && m.nome) || '').trim();
            if (nome && nomes.indexOf(nome) < 0) nomes.push(nome);
        });
        return nomes.length ? nomes : fallback;
    }

    function alunoPreviewSelecionado() {
        var id = Number((estado && estado.aluno_preview_id) || 0);
        if (id <= 0) return null;
        var alunos = (catalogo && catalogo.alunos) || [];
        for (var i = 0; i < alunos.length; i++) {
            if (Number(alunos[i].id || 0) === id) return alunos[i];
        }
        return null;
    }

    function textoNormalizadoBusca(s) {
        var out = String(s || '').toLowerCase();
        return out.normalize ? out.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : out;
    }

    function labelAlunoPreview(a) {
        var label = String((a && a.nome) || ('Aluno #' + ((a && a.id) || '')));
        if (a && a.turma_nome) label += ' · ' + a.turma_nome;
        if (a && a.ra) label += ' · RA ' + a.ra;
        return label;
    }

    function abrirListaAlunosPreview(mostrarTodos) {
        var box = document.getElementById('bw-preview-aluno-lista');
        var input = document.getElementById('bw-preview-aluno-busca');
        if (!box || !input) return;
        var q = mostrarTodos ? '' : textoNormalizadoBusca(input.value || '');
        var alunos = (catalogo && catalogo.alunos) || [];
        var html = '';
        var n = 0;
        alunos.forEach(function (a) {
            var label = labelAlunoPreview(a);
            if (q && textoNormalizadoBusca(label).indexOf(q) < 0) return;
            n += 1;
            html += '<button type="button" class="bw-preview-aluno-item block w-full text-left px-3 py-1.5 text-sm text-gray-900 hover:bg-indigo-50" data-preview-aluno-id="' + Number(a.id || 0) + '">' + esc(label) + '</button>';
        });
        if (!n) {
            html = '<p class="px-3 py-2 text-xs text-gray-500">Nenhum aluno encontrado.</p>';
        }
        box.innerHTML = html;
        box.classList.remove('hidden');
    }

    function hashPreviewAluno() {
        var aluno = alunoPreviewSelecionado();
        return aluno ? hashNome((aluno.nome || '') + '#' + aluno.id) : 0;
    }

    function htmlAlunoPreviewSelect() {
        var alunos = (catalogo && catalogo.alunos) || [];
        if (!alunos.length) return '';
        var atual = Number((estado && estado.aluno_preview_id) || 0);
        var atualObj = alunoPreviewSelecionado();
        var atualLabel = atualObj ? labelAlunoPreview(atualObj) : '';
        var html = '<div class="mb-3 flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">';
        html += '<label class="text-xs font-medium text-slate-700 flex-1 min-w-[16rem] relative">Simular notas com aluno';
        html += '<input id="bw-preview-aluno-busca" autocomplete="off" class="mt-1 block w-full h-9 rounded-lg border border-gray-300 bg-white px-2 text-sm" placeholder="Pesquisar aluno pelo nome" value="' + esc(atualLabel) + '">';
        html += '<div id="bw-preview-aluno-lista" class="hidden absolute left-0 right-0 top-full z-40 mt-1 max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"></div>';
        html += '<input type="hidden" id="bw-preview-aluno" value="' + (atual > 0 ? atual : 0) + '">';
        html += '</label>';
        html += '<button type="button" id="bw-preview-aluno-limpar" class="h-9 px-3 text-xs rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Sem aluno</button>';
        html += '<p class="text-xs text-slate-500 max-w-md">' + alunos.length + ' aluno(s) ativos. Clique no campo para ver a lista inteira, ou digite para achar pelo nome.</p>';
        html += '</div>';
        return html;
    }

    function ehModeloSantaMaria() {
        return !!(estado && (estado.modelo_santa_maria || estado.origem === 'santa-maria'));
    }

    function linhasLayoutSantaMariaPadrao() {
        return [
            { id: 'sm_b1', nome: 'I BIMESTRE', colunas: ['b1_pp', 'b1_pu', 'b1_ch', 'b1_pe', 'b1_mu', 'b1_fal'] },
            { id: 'sm_b2', nome: 'II BIMESTRE', colunas: ['b2_pp', 'b2_pu', 'b2_ch', 'b2_pe', 'b2_mu', 'b2_fal'] },
            { id: 'sm_s1', nome: '1º SEMESTRE', colunas: ['s1_rec', 's1_ms'] },
            { id: 'sm_b3', nome: 'III BIMESTRE', colunas: ['b3_pp', 'b3_pu', 'b3_ch', 'b3_pe', 'b3_mu', 'b3_fal'] },
            { id: 'sm_b4', nome: 'IV BIMESTRE', colunas: ['b4_pp', 'b4_pu', 'b4_ch', 'b4_pe', 'b4_mu', 'b4_fal'] },
            { id: 'sm_s2', nome: '2º SEMESTRE', colunas: ['s2_rec', 's2_ms'] },
            { id: 'sm_fin', nome: 'RESULTADO FINAL', colunas: ['ma', 'pf', 'mf', 'situacao'] }
        ];
    }

    /** Injeta bN_mu entre PE e FAL em layouts SM legados sem a coluna. */
    function garantirMuNasLinhasSantaMariaJs() {
        if (!ehModeloSantaMaria() || !estado || !Array.isArray(estado.linhas_layout)) return;
        estado.linhas_layout = estado.linhas_layout.map(function (lin) {
            if (!lin) return lin;
            var cols = (lin.colunas || []).map(function (c) { return String(c || '').toLowerCase(); }).filter(Boolean);
            var out = [];
            cols.forEach(function (c) {
                var m = /^b([1-4])_fal$/.exec(c);
                if (m) {
                    var mu = 'b' + m[1] + '_mu';
                    if (out.indexOf(mu) < 0) out.push(mu);
                }
                if (out.indexOf(c) < 0) out.push(c);
            });
            [1, 2, 3, 4].forEach(function (b) {
                var pe = 'b' + b + '_pe';
                var mu = 'b' + b + '_mu';
                if (out.indexOf(pe) >= 0 && out.indexOf(mu) < 0) {
                    out.splice(out.indexOf(pe) + 1, 0, mu);
                }
            });
            lin.colunas = out;
            return lin;
        });
    }

    function linhasLayoutTemSemestresSantaMaria(linhas) {
        var temS1 = false;
        var temS2 = false;
        (linhas || []).forEach(function (lin) {
            var cols = (lin && lin.colunas) || [];
            var nome = String((lin && lin.nome) || '').toLowerCase();
            if (nome.indexOf('1º semestre') >= 0 || nome.indexOf('1o semestre') >= 0 || cols.indexOf('s1_ms') >= 0 || cols.indexOf('s1_rec') >= 0) temS1 = true;
            if (nome.indexOf('2º semestre') >= 0 || nome.indexOf('2o semestre') >= 0 || cols.indexOf('s2_ms') >= 0 || cols.indexOf('s2_rec') >= 0) temS2 = true;
        });
        return temS1 && temS2;
    }

    function garantirLinhasLayoutSantaMaria() {
        if (!ehModeloSantaMaria() || !estado) return;
        // Usuário limpou/editou cabeçalhos para montar do zero — não reinsere o padrão.
        if (Number(estado.cabecalhos_manuais || 0) === 1) {
            garantirMuNasLinhasSantaMariaJs();
            return;
        }
        if (Array.isArray(estado.linhas_layout) && estado.linhas_layout.length && linhasLayoutTemSemestresSantaMaria(estado.linhas_layout)) {
            garantirMuNasLinhasSantaMariaJs();
            return;
        }
        estado.linhas_layout = linhasLayoutSantaMariaPadrao();
    }

    function colunasPadraoSantaMariaMapa() {
        var mapa = {};
        function add(codigo, nome, layoutType, sourceType) {
            mapa[codigo] = {
                codigo: codigo,
                nome: nome,
                layout_type: layoutType || 'media',
                source_type: sourceType || 'nenhuma'
            };
        }
        [1, 2, 3, 4].forEach(function (b) {
            add('b' + b + '_pp', 'PP');
            add('b' + b + '_pu', 'PU');
            add('b' + b + '_ch', 'CH');
            add('b' + b + '_pe', 'PE');
            add('b' + b + '_mu', 'MU', 'media', 'calculado');
            add('b' + b + '_fal', 'FAL', 'faltas');
        });
        add('s1_rec', 'REC 1º', 'rec');
        add('s1_ms', 'MS 1º', 'media', 'calculado');
        add('s2_rec', 'REC 2º', 'rec');
        add('s2_ms', 'MS 2º', 'media', 'calculado');
        add('ma', 'MA', 'media', 'calculado');
        add('pf', 'PF');
        add('mf', 'MF', 'media', 'calculado');
        add('situacao', 'Situação', 'resultado');
        return mapa;
    }

    function montarGruposDeFaixas(flat) {
        return faixasCabecalhoPreview(flat || []).map(function (f, idx) {
            if (f.tipo === 'grupo') {
                return { key: 'cab' + idx, label: f.nome, cols: f.cols || [] };
            }
            var c = f.col || {};
            return { key: 'col' + idx, label: c.nome || c.codigo || '', cols: [c] };
        });
    }

    function flatColsDeGrupos(grupos) {
        var flat = [];
        (grupos || []).forEach(function (g) {
            (g.cols || []).forEach(function (c) { if (c) flat.push(c); });
        });
        return flat;
    }

    function previewLocalBoletimSantaMaria() {
        garantirLinhasLayoutSantaMaria();
        var mapa = colunasPadraoSantaMariaMapa();
        Object.keys(estado.nomes_blocos || {}).forEach(function (cod) {
            if (!mapa[cod]) {
                mapa[cod] = {
                    codigo: cod,
                    nome: estado.nomes_blocos[cod] || tituloColuna(cod) || cod,
                    layout_type: 'media',
                    source_type: (estado.formulas_blocos && estado.formulas_blocos[cod]) ? 'calculado' : 'nenhuma'
                };
            } else if (estado.nomes_blocos[cod]) {
                mapa[cod].nome = estado.nomes_blocos[cod];
            }
        });
        (estado.blocos_calc || []).forEach(function (cod) {
            if (!cod || mapa[cod]) return;
            mapa[cod] = {
                codigo: cod,
                nome: nomeBlocoCalc(cod),
                layout_type: 'media',
                source_type: 'calculado'
            };
        });
        var ordem = [];
        var visto = {};
        function pushCod(cod) {
            cod = String(cod || '').toLowerCase();
            if (!cod || visto[cod]) return;
            visto[cod] = true;
            ordem.push(cod);
        }
        (estado.colunas_ordem || []).forEach(pushCod);
        (estado.linhas_layout || []).forEach(function (lin) {
            (lin.colunas || []).forEach(pushCod);
        });
        Object.keys(mapa).forEach(pushCod);
        var flat = ordem.map(function (cod) {
            var base = mapa[cod] || {
                codigo: cod,
                nome: tituloColuna(cod) || cod,
                layout_type: 'media',
                source_type: (estado.formulas_blocos && estado.formulas_blocos[cod]) ? 'calculado' : 'nenhuma'
            };
            return Object.assign({}, base, {
                nome: tituloColuna(cod) || base.nome || cod,
                tipo: base.source_type === 'calculado' ? 'calculado' : 'peca',
                travada: false
            });
        });
        flat = ordenarColunasLista(flat);
        var grupos = montarGruposDeFaixas(flat);
        function notasLinha(nome) {
            var h = hashNome(nome) + hashPreviewAluno();
            var notas = {};
            var mus = {};
            [1, 2, 3, 4].forEach(function (b) {
                // PP/PU/PE/MU: sem half (nota lançada / MU do manual). Half só em MS/MA/MF.
                var pp = Math.round(10 * (5 + Math.abs(h + b * 3) % 51) / 10) / 10;
                var pu = Math.round(10 * (5 + Math.abs(h + b * 7) % 51) / 10) / 10;
                var pe = Math.round(10 * (Math.abs(h + b) % 16) / 10) / 10;
                var mu = Math.round((((pp + pu) / 2) + pe) * 100) / 100;
                notas['b' + b + '_pp'] = pp;
                notas['b' + b + '_pu'] = pu;
                notas['b' + b + '_ch'] = '—';
                notas['b' + b + '_pe'] = pe;
                notas['b' + b + '_mu'] = mu;
                notas['b' + b + '_fal'] = Math.abs(h + b * 5) % 9;
                mus[b] = mu;
            });
            notas.s1_rec = '—';
            notas.s1_ms = roundPreviewValor((mus[1] + mus[2]) / 2);
            notas.s2_rec = '—';
            notas.s2_ms = roundPreviewValor((mus[3] + mus[4]) / 2);
            notas.ma = roundPreviewValor((notas.s1_ms + notas.s2_ms) / 2);
            notas.pf = '—';
            notas.mf = notas.ma;
            notas.situacao = 'Cursando';
            return notas;
        }
        var mats = materiasPreviewLocal(['Língua Portuguesa', 'Matemática', 'História', 'Geografia', 'Ciências']);
        var colunas = flat.map(function (c) {
            return {
                codigo: c.codigo,
                nome: c.nome || c.codigo,
                tipo: c.source_type === 'calculado' || c.tipo === 'calculado' ? 'calculado' : 'peca',
                travada: false
            };
        });
        var linhasSm = mats.map(function (n) { return { materia_nome: n, notas: notasLinha(n) }; });
        linhasSm = linhasComTituloBoletim(linhasSm, flat.slice(), []);
        return {
            modo: 'boletim',
            modelo_santa_maria: 1,
            aviso: 'Preview no formato do print Santa Maria. Arraste colunas/blocos e edite os títulos do período — a tabela abaixo acompanha. Dados fictícios.',
            grupos: grupos,
            colunas: colunas,
            pecas_disponiveis: [],
            tabelas: [{
                key: 'u',
                titulo: 'COMPONENTES CURRICULARES',
                subtitulo: '',
                semanas: [],
                outras: flat.slice(),
                grupos: grupos,
                linhas: linhasSm
            }]
        };
    }

    function previewLocalBoletim() {
        if (ehModeloSantaMaria()) return previewLocalBoletimSantaMaria();
        var fontes = (estado && estado.fontes_bimestres) || {};
        var faltas = (estado && estado.fontes_faltas) || {};
        var bims = [1, 2, 3, 4].filter(function (b) {
            return Number(fontes[b] || 0) > 0 || Number(faltas[b] || 0) > 0;
        });
        if (!bims.length) bims = [1, 2, 3, 4];
        var labels = { 1: '1º BIMESTRE', 2: '2º BIMESTRE', 3: '3º BIMESTRE', 4: '4º BIMESTRE' };
        var grupos = bims.map(function (b) {
            return {
                key: 'b' + b,
                label: labels[b],
                cols: [
                    { codigo: 'b' + b + '_media', nome: 'Média', layout_type: 'media', source_type: 'evento_boletim' },
                    { codigo: 'b' + b + '_faltas', nome: 'Faltas', layout_type: 'faltas', source_type: 'faltas_evento' }
                ]
            };
        });
        grupos.push({
            key: 'final',
            label: 'FINAL',
            cols: [
                { codigo: 'media_final', nome: 'Média', layout_type: 'media', source_type: 'calculado' },
                { codigo: 'rec_final', nome: 'Rec.', layout_type: 'rec', source_type: 'nenhuma' },
                { codigo: 'faltas_final', nome: 'Faltas', layout_type: 'faltas', source_type: 'calculado' },
                { codigo: 'resultado', nome: 'Resultado', layout_type: 'resultado', source_type: 'nenhuma' }
            ]
        });
        var notaMin = Number(minimaExibida().valor);
        function notasLinha(nome) {
            var h = hashNome(nome) + hashPreviewAluno();
            var notas = {};
            var acc = 0;
            var nMed = 0;
            var accF = 0;
            bims.forEach(function (b) {
                var media = roundPreviewValor(Math.round(10 * (5 + Math.abs(h + b * 7) % 51) / 10) / 10);
                var falt = Math.abs(h + b * 5) % 9;
                notas['b' + b + '_media'] = media;
                notas['b' + b + '_faltas'] = falt;
                acc += media;
                nMed += 1;
                accF += falt;
            });
            notas.media_final = nMed ? roundPreviewValor(acc / nMed) : 0;
            notas.rec_final = (h % 3) === 0 ? roundPreviewValor(Math.round(10 * (5 + Math.abs(h + 11) % 40) / 10) / 10) : '—';
            notas.faltas_final = accF;
            notas.resultado = notas.media_final >= notaMin ? 'Aprovado' : 'Reprovado';
            return notas;
        }
        var mats = materiasPreviewLocal(['Língua Portuguesa', 'Matemática', 'História', 'Geografia', 'Ciências']);
        var colunas = [];
        grupos.forEach(function (g) {
            (g.cols || []).forEach(function (c) {
                colunas.push({
                    codigo: c.codigo,
                    nome: c.nome || c.codigo,
                    tipo: c.source_type === 'calculado' ? 'calculado' : 'peca',
                    travada: false
                });
            });
        });
        return {
            modo: 'boletim',
            aviso: 'Exemplo com dados fictícios — não são notas reais.',
            grupos: grupos,
            colunas: colunas,
            pecas_disponiveis: [],
            tabelas: [{
                key: 'u',
                titulo: 'Matérias',
                subtitulo: '',
                semanas: [],
                outras: colunas.slice(),
                grupos: grupos,
                linhas: mats.map(function (n) { return { materia_nome: n, notas: notasLinha(n) }; })
            }]
        };
    }

    function previewLocal() {
        if (ehBoletimComposto()) return previewLocalBoletim();
        var pecas = (estado && estado.pecas) ? estado.pecas.slice() : [];
        var temSemanal = pecas.indexOf('semanal') >= 0 && !colunaOculta('_semanal') && !colunaOculta('media_sem');
        var semanasA = temSemanal ? [1, 3, 5, 7].map(function (s) { return { codigo: 's' + s, nome: 'S' + s }; }) : [];
        var semanasB = temSemanal ? [2, 4, 6, 8].map(function (s) { return { codigo: 's' + s, nome: 'S' + s }; }) : [];
        var outras = temSemanal ? [{ codigo: 'media_sem', nome: 'Média Sem', layout_type: 'media_sem', source_type: 'calculado' }] : [];
        var colunas = temSemanal ? [{ codigo: '_semanal', nome: 'Prova semanal (S1-S8 · N e Q)', tipo: 'semana_grupo', travada: false }] : [];
        var partesMedia = temSemanal ? ['media_sem'] : [];
        pecas.forEach(function (k) {
            if (k === 'semanal') return;
            var cod = pecaCodigoQuadro(k);
            if (colunaOculta(cod)) return;
            var nome = k === 'bimestral' ? 'Prova Bim' : pecaNomeQuadro(k);
            outras.push({ codigo: cod, nome: nome, layout_type: k === 'recuperacao' ? 'rec' : 'media', source_type: 'provas_sistema' });
            colunas.push({ codigo: cod, nome: nome, tipo: 'peca', travada: false });
            if (k !== 'recuperacao') partesMedia.push(cod);
        });
        if (pecas.indexOf('bimestral') < 0 && pecas.length === 0) {
            outras.push({ codigo: 'prova_bim', nome: 'Prova Bim', layout_type: 'media', source_type: 'provas_sistema' });
            colunas.push({ codigo: 'prova_bim', nome: 'Prova Bim', tipo: 'peca', travada: false });
            partesMedia.push('prova_bim');
        }
        if (!colunaOculta('media_bim')) {
            outras.push({ codigo: 'media_bim', nome: nomeBlocoCalc('media_bim'), layout_type: 'media', source_type: 'calculado' });
            colunas.push({ codigo: 'media_bim', nome: nomeBlocoCalc('media_bim'), tipo: 'calculado', travada: false });
        }
        var vistosCalc = { media_bim: true, media_sem: true };
        (estado.blocos_calc || []).concat(Object.keys(estado.formulas_blocos || {})).forEach(function (cod) {
            if (!cod || vistosCalc[cod] || cod === 'media_sem' || colunaOculta(cod)) return;
            vistosCalc[cod] = true;
            var nm = nomeBlocoCalc(cod);
            outras.push({ codigo: cod, nome: nm, layout_type: cod === 'media_final' ? 'resultado' : 'media', source_type: 'calculado' });
            colunas.push({ codigo: cod, nome: nm, tipo: 'calculado', travada: false });
        });
        var temRec = pecas.indexOf('recuperacao') >= 0;
        var temMelhora = pecas.some(function (k) {
            return ((estado.pecas_opcoes && estado.pecas_opcoes[k]) || {}).papel === 'so_melhora';
        });
        var querFinal = temRec || temMelhora
            || (estado.blocos_calc || []).indexOf('media_final') >= 0
            || (estado.formulas_blocos && estado.formulas_blocos.media_final)
            || estado.bloco_calc === 'media_final';
        if (querFinal && !vistosCalc.media_final && !colunaOculta('media_final')) {
            outras.push({ codigo: 'media_final', nome: nomeBlocoCalc('media_final'), layout_type: 'resultado', source_type: 'calculado' });
            colunas.push({ codigo: 'media_final', nome: nomeBlocoCalc('media_final'), tipo: 'calculado', travada: false });
        }
        colunas = ordenarColunasLista(colunas);
        outras = ordenarColunasLista(outras);
        function notasLinha(nome, semanas) {
            var h = hashNome(nome) + hashPreviewAluno();
            var notas = {};
            var sumN = 0;
            var sumQ = 0;
            semanas.forEach(function (s) {
                var n = 5 + Math.abs(h + parseInt(String(s.codigo).slice(1), 10) * 3) % 6;
                notas[s.codigo + '__n'] = n;
                notas[s.codigo + '__q'] = 10;
                sumN += n;
                sumQ += 10;
            });
            if (temSemanal) {
                notas.media_sem = sumQ ? roundPreviewValor(10 * sumN / sumQ) : 0;
            }
            outras.forEach(function (o) {
                if (o.codigo === 'media_sem') return;
                if (o.source_type === 'calculado' && o.codigo === 'media_bim') {
                    var acc = temSemanal ? notas.media_sem : 0;
                    var nPart = temSemanal ? 1 : 0;
                    partesMedia.forEach(function (c) {
                        if (c === 'media_sem') return;
                        if (typeof notas[c] === 'number') { acc += notas[c]; nPart += 1; }
                    });
                    notas.media_bim = nPart ? roundPreviewValor(acc / nPart) : '—';
                    return;
                }
                if (o.source_type === 'calculado' && o.codigo === 'media_final') {
                    return;
                }
                if (o.source_type === 'calculado') {
                    return;
                }
                if (o.codigo === 'rec' && (h % 3) === 0) {
                    notas[o.codigo] = '—';
                    return;
                }
                notas[o.codigo] = roundPreviewValor(Math.round(10 * (5 + Math.abs(h + o.codigo.length * 7) % 51) / 10) / 10);
            });
            return aplicarFormulasNaLinhaGrupo(notas);
        }
        var mats = materiasPreviewLocal(['Física', 'Matemática', 'Geografia', 'Biologia', 'Educação Física']);
        var metade = Math.ceil(mats.length / 2);
        var matsA = mats.slice(0, metade);
        var matsB = mats.slice(metade);
        if (!matsB.length) matsB = matsA.slice();
        var sel = pecas;
        var disp = (catalogo.pecas || []).filter(function (p) { return sel.indexOf(p.key) < 0; }).map(function (p) {
            return { key: p.key, label: p.label };
        });
        var tabelas = temSemanal ? [
            { key: 'a', titulo: 'Matérias Bloco A', subtitulo: '', semanas: semanasA, outras: outras, linhas: matsA.map(function (n) { return { materia_nome: n, notas: notasLinha(n, semanasA) }; }) },
            { key: 'b', titulo: 'Matérias Bloco B', subtitulo: '', semanas: semanasB, outras: outras, linhas: matsB.map(function (n) { return { materia_nome: n, notas: notasLinha(n, semanasB) }; }) }
        ] : [
            { key: 'u', titulo: 'Matérias', subtitulo: 'Exemplo', semanas: [], outras: outras, linhas: mats.map(function (n) { return { materia_nome: n, notas: notasLinha(n, []) }; }) }
        ];
        return {
            modo: temSemanal ? 'quadro' : 'simples',
            aviso: 'Exemplo com dados fictícios — não são notas reais.',
            colunas: colunas,
            pecas_disponiveis: disp,
            tabelas: tabelas
        };
    }

    function previewEfetivo() {
        var alunoId = Number((estado && estado.aluno_preview_id) || 0);
        if (alunoId > 0) {
            if (previewAtual && Number(previewAtual.aluno_id || 0) === alunoId) return previewAtual;
            return {
                modo: 'vazio',
                aluno_id: alunoId,
                aviso: 'Buscando as notas reais do aluno…',
                tabelas: [],
                colunas: [],
                pecas_disponiveis: []
            };
        }
        // Montar quadro / impressão: Exemplo acompanha arraste e cabeçalhos na hora.
        if (typeof ehVisaoImpressaoEditavel === 'function' && ehVisaoImpressaoEditavel()) {
            if (ehModeloSantaMaria()) return previewLocalBoletimSantaMaria();
            return previewLocalQuadroImpressao();
        }
        if (ehBoletimComposto()) {
            if (ehModeloSantaMaria()) {
                var gruposSm = (previewAtual && previewAtual.grupos) || [];
                if (!(previewAtual && previewAtual.modo === 'boletim' && gruposTemSemestresSantaMaria(gruposSm))) {
                    return previewLocalBoletimSantaMaria();
                }
            }
            if (previewAtual && previewAtual.modo === 'boletim' && (previewAtual.tabelas || []).length) {
                return previewAtual;
            }
            return previewLocalBoletim();
        }
        if (previewAtual && (previewAtual.tabelas || []).length) return previewAtual;
        return previewLocal();
    }

    /** Preview local do Montar quadro: todas as colunas + cabeçalhos na ordem do arraste. */
    function quadroManualDoZero() {
        return !!(estado && (estado.quadro_livre || estado.origem === 'quadro-impressao' || Number(estado.cabecalhos_manuais || 0) === 1));
    }

    function garantirChPorBimestreLocal(mapa) {
        mapa = mapa || {};
        // Do zero: CH só entra se o admin clicou no catálogo (já está em colunas_ordem).
        if (quadroManualDoZero()) return mapa;
        var bims = {};
        Object.keys(mapa).forEach(function (cod) {
            var m = String(cod || '').toLowerCase().match(/^b([1-4])_/);
            if (m) bims[Number(m[1])] = true;
        });
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        [1, 2, 3, 4].forEach(function (bim) {
            if (!bims[bim]) return;
            var cod = 'b' + bim + '_ch';
            var jaTemCh = !!mapa[cod];
            if (!jaTemCh) {
                Object.keys(mapa).forEach(function (c) {
                    if (!String(c).match(new RegExp('^b' + bim + '_'))) return;
                    if (String(siglaTituloColunaJs(c, (mapa[c] && mapa[c].nome) || '') || '').toUpperCase() === 'CH') {
                        jaTemCh = true;
                    }
                });
            }
            if (jaTemCh) return;
            mapa[cod] = {
                codigo: cod,
                nome: 'CH',
                layout_type: 'media',
                source_type: 'nenhuma',
                tipo: 'peca',
                travada: false
            };
            estado.nomes_blocos[cod] = 'CH';
            if (estado.colunas_ordem.indexOf(cod) < 0) {
                var pu = 'b' + bim + '_pu';
                var idxPu = estado.colunas_ordem.indexOf(pu);
                if (idxPu >= 0) estado.colunas_ordem.splice(idxPu + 1, 0, cod);
                else estado.colunas_ordem.push(cod);
            }
            if (Array.isArray(estado.linhas_layout)) {
                estado.linhas_layout.forEach(function (lin) {
                    if (!lin || !Array.isArray(lin.colunas)) return;
                    var cols = lin.colunas.map(function (c) { return String(c || '').toLowerCase(); });
                    var temBim = cols.some(function (c) { return c.indexOf('b' + bim + '_') === 0; });
                    if (!temBim || cols.indexOf(cod) >= 0) return;
                    var iPu = cols.indexOf('b' + bim + '_pu');
                    if (iPu >= 0) cols.splice(iPu + 1, 0, cod);
                    else {
                        var iFirst = -1;
                        cols.forEach(function (c, i) {
                            if (iFirst < 0 && c.indexOf('b' + bim + '_') === 0) iFirst = i;
                        });
                        if (iFirst >= 0) cols.splice(iFirst + 1, 0, cod);
                        else cols.push(cod);
                    }
                    lin.colunas = cols;
                });
            }
        });
        return mapa;
    }

    function garantirFalPorBimestreLocal(mapa) {
        mapa = mapa || {};
        // Do zero: FAL só entra se o admin clicou no catálogo.
        if (quadroManualDoZero()) return mapa;
        var bims = {};
        Object.keys(mapa).forEach(function (cod) {
            var m = String(cod || '').toLowerCase().match(/^b([1-4])_/);
            if (m) bims[Number(m[1])] = true;
        });
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        if (!estado.fontes_faltas || typeof estado.fontes_faltas !== 'object') {
            estado.fontes_faltas = { 1: 0, 2: 0, 3: 0, 4: 0 };
        }
        var ano = Number((estado && estado.ano_letivo) || 0);
        [1, 2, 3, 4].forEach(function (bim) {
            if (!bims[bim]) return;
            var cod = 'b' + bim + '_fal';
            var jaTemFal = !!mapa[cod] || !!mapa['b' + bim + '_faltas'];
            if (!jaTemFal) {
                Object.keys(mapa).forEach(function (c) {
                    if (!String(c).match(new RegExp('^b' + bim + '_'))) return;
                    var col = mapa[c] || {};
                    if (colunaEhFaltas(col) || String(siglaTituloColunaJs(c, col.nome || '') || '').toUpperCase() === 'FAL') {
                        jaTemFal = true;
                    }
                });
            }
            if (jaTemFal) {
                if (mapa['b' + bim + '_faltas'] && !mapa[cod]) {
                    mapa[cod] = Object.assign({}, mapa['b' + bim + '_faltas'], {
                        codigo: cod,
                        nome: 'FAL',
                        layout_type: 'faltas'
                    });
                    delete mapa['b' + bim + '_faltas'];
                }
                return;
            }
            if (!Number(estado.fontes_faltas[bim] || 0)) {
                ((catalogo && catalogo.faltas_eventos) || []).some(function (fe) {
                    if (!fe) return false;
                    if (ano > 0 && Number(fe.ano_letivo || 0) !== ano) return false;
                    if (Number(fe.bimestre || 0) !== bim) return false;
                    var fid = Number(fe.id || 0);
                    if (fid <= 0) return false;
                    estado.fontes_faltas[bim] = fid;
                    return true;
                });
            }
            var idFal = Number(estado.fontes_faltas[bim] || 0);
            mapa[cod] = {
                codigo: cod,
                nome: 'FAL',
                layout_type: 'faltas',
                source_type: idFal > 0 ? 'faltas_evento' : 'nenhuma',
                tipo: 'peca',
                travada: false
            };
            estado.nomes_blocos[cod] = 'FAL';
            if (estado.colunas_ordem.indexOf(cod) < 0) {
                var pe = 'b' + bim + '_pe';
                var idxPe = estado.colunas_ordem.indexOf(pe);
                if (idxPe >= 0) estado.colunas_ordem.splice(idxPe + 1, 0, cod);
                else estado.colunas_ordem.push(cod);
            }
            if (Array.isArray(estado.linhas_layout)) {
                estado.linhas_layout.forEach(function (lin) {
                    if (!lin || !Array.isArray(lin.colunas)) return;
                    var cols = lin.colunas.map(function (c) { return String(c || '').toLowerCase(); });
                    var temBim = cols.some(function (c) { return c.indexOf('b' + bim + '_') === 0; });
                    if (!temBim || cols.indexOf(cod) >= 0) return;
                    var iPe = cols.indexOf('b' + bim + '_pe');
                    if (iPe >= 0) cols.splice(iPe + 1, 0, cod);
                    else {
                        var iLast = -1;
                        cols.forEach(function (c, i) {
                            if (c.indexOf('b' + bim + '_') === 0) iLast = i;
                        });
                        if (iLast >= 0) cols.splice(iLast + 1, 0, cod);
                        else cols.push(cod);
                    }
                    lin.colunas = cols;
                });
            }
        });
        return mapa;
    }

    function previewLocalQuadroImpressao() {
        var mapa = {};
        function upsert(cod, nome, sourceType, layoutType) {
            cod = String(cod || '').toLowerCase();
            if (!cod || colunaOculta(cod)) return;
            if (!mapa[cod]) {
                mapa[cod] = {
                    codigo: cod,
                    nome: nome || tituloColuna(cod) || siglaTituloColunaJs(cod, nome) || cod,
                    layout_type: layoutType || 'media',
                    source_type: sourceType || 'nenhuma',
                    tipo: sourceType === 'calculado' ? 'calculado' : 'peca',
                    travada: false
                };
            }
            var rotulo = tituloColuna(cod) || siglaTituloColunaJs(cod, mapa[cod].nome) || mapa[cod].nome;
            if (rotulo) mapa[cod].nome = rotulo;
        }
        // Fonte da verdade: componentes do rascunho atual (Demonstrativos + eventos marcados).
        ((rascunhoAtual && rascunhoAtual.componentes) || []).forEach(function (c) {
            if (!c) return;
            var cfg = configDoComp(c);
            upsert(c.codigo, c.nome, c.source_type, cfg.layout_type || '');
        });
        (estado.blocos_calc || []).forEach(function (cod) {
            upsert(cod, nomeBlocoCalc(cod), 'calculado', 'media');
        });
        Object.keys(estado.formulas_blocos || {}).forEach(function (cod) {
            upsert(cod, nomeBlocoCalc(cod), 'calculado', 'media');
        });
        Object.keys(estado.nomes_blocos || {}).forEach(function (cod) {
            var low = String(cod || '').toLowerCase();
            var ehCalc = (estado.blocos_calc || []).indexOf(cod) >= 0
                || (estado.blocos_calc || []).indexOf(low) >= 0
                || !!(estado.formulas_blocos && (estado.formulas_blocos[cod] || estado.formulas_blocos[low]));
            if (!mapa[low] && !ehCalc) return;
            upsert(cod, estado.nomes_blocos[cod], ehCalc ? 'calculado' : 'nenhuma', 'media');
        });
        // Garante códigos só presentes em linhas_layout (ex.: MU no I BIMESTRE).
        (estado.linhas_layout || []).forEach(function (lin) {
            (lin && lin.colunas || []).forEach(function (cod) {
                var low = String(cod || '').toLowerCase();
                if (!low || mapa[low]) return;
                var ehCalc = (estado.blocos_calc || []).indexOf(cod) >= 0
                    || (estado.blocos_calc || []).indexOf(low) >= 0
                    || !!(estado.formulas_blocos && (estado.formulas_blocos[cod] || estado.formulas_blocos[low]));
                upsert(low, nomeBlocoCalc(low) || tituloColuna(low) || low, ehCalc ? 'calculado' : 'nenhuma', 'media');
            });
        });
        mapa = garantirChPorBimestreLocal(mapa);
        mapa = garantirFalPorBimestreLocal(mapa);

        var ordem = [];
        var visto = {};
        function pushCod(cod) {
            cod = String(cod || '').toLowerCase();
            if (!cod || !mapa[cod] || visto[cod] || colunaOculta(cod)) return;
            visto[cod] = true;
            ordem.push(cod);
        }
        (estado.colunas_ordem || []).forEach(pushCod);
        (estado.linhas_layout || []).forEach(function (lin) {
            (lin.colunas || []).forEach(pushCod);
        });
        Object.keys(mapa).forEach(pushCod);

        var flat = ordem.map(function (cod) {
            var base = mapa[cod];
            return Object.assign({}, base, {
                nome: tituloColuna(cod) || siglaTituloColunaJs(cod, base.nome) || base.nome || cod
            });
        });
        flat = ordenarColunasLista(flat);
        var grupos = montarGruposDeFaixas(flat);
        function notasLinha(nome) {
            var h = hashNome(nome) + hashPreviewAluno();
            var notas = {};
            flat.forEach(function (o, idx) {
                if (!o || !o.codigo) return;
                if (o.layout_type === 'resultado') {
                    notas[o.codigo] = 'Cursando';
                    return;
                }
                if (o.layout_type === 'faltas') {
                    notas[o.codigo] = Math.abs(h + idx * 5) % 9;
                    return;
                }
                if (String(o.codigo).indexOf('rec') >= 0 || o.layout_type === 'rec' || String(o.nome || '').toUpperCase() === 'REC' || String(o.nome || '').toUpperCase() === 'PF') {
                    notas[o.codigo] = '—';
                    return;
                }
                if (o.source_type === 'calculado' || o.tipo === 'calculado') {
                    notas[o.codigo] = roundPreviewValor(Math.round(10 * (5 + Math.abs(h + idx * 7) % 51) / 10) / 10);
                    return;
                }
                notas[o.codigo] = roundPreviewValor(Math.round(10 * (5 + Math.abs(h + o.codigo.length * 7 + idx) % 51) / 10) / 10);
            });
            return notas;
        }
        var mats = materiasPreviewLocal(['Literatura', 'Geografia', 'Língua Inglesa', 'Matemática', 'História']);
        var colunas = flat.map(function (c) {
            return {
                codigo: c.codigo,
                nome: c.nome || c.codigo,
                tipo: c.source_type === 'calculado' || c.tipo === 'calculado' ? 'calculado' : 'peca',
                travada: false,
                layout_type: c.layout_type || ''
            };
        });
        return {
            modo: 'boletim',
            aviso: 'Exemplo com dados fictícios — arraste títulos do período e colunas para ver o quadro atualizar.',
            grupos: grupos,
            colunas: colunas,
            pecas_disponiveis: [],
            tabelas: [{
                key: 'u',
                titulo: 'Matérias',
                subtitulo: 'Exemplo',
                semanas: [],
                outras: colunas.slice(),
                grupos: grupos,
                linhas: mats.map(function (n) { return { materia_nome: n, notas: notasLinha(n) }; })
            }]
        };
    }

    function gruposTemSemestresSantaMaria(grupos) {
        var temS1 = false;
        var temS2 = false;
        (grupos || []).forEach(function (g) {
            var key = String((g && g.key) || '').toLowerCase();
            var label = String((g && g.label) || '').toLowerCase();
            if (key === 's1' || label.indexOf('1º semestre') >= 0 || label.indexOf('1o semestre') >= 0) temS1 = true;
            if (key === 's2' || label.indexOf('2º semestre') >= 0 || label.indexOf('2o semestre') >= 0) temS2 = true;
        });
        return temS1 && temS2;
    }

    function gruposBoletimComCabecalhos(tab) {
        if (ehModeloSantaMaria()) garantirLinhasLayoutSantaMaria();
        var grupos = (tab && tab.grupos) || [];
        var flat = flatColsDeGrupos(grupos);
        if (!flat.length && tab && (tab.outras || []).length) {
            flat = (tab.outras || []).slice();
        }
        if (!flat.length) return grupos;
        flat = ordenarColunasLista(flat);
        var montados = montarGruposDeFaixas(flat);
        if (!montados.length) return grupos;
        return montados;
    }

    function htmlTabelaPreviewBoletim(tab) {
        var grupos = gruposBoletimComCabecalhos(tab);
        var html = '<div class="overflow-x-auto max-h-[28rem] border border-gray-300 rounded-lg bg-white mb-4">';
        html += '<table class="bw-preview-table boletim"><thead><tr>';
        html += '<th rowspan="2" class="mat text-left">' + esc(tab.titulo || 'Matéria') + '</th>';
        grupos.forEach(function (g, gi) {
            // Borda também entre Matérias e o 1º período (gi === 0).
            html += '<th colspan="' + Math.max(1, (g.cols || []).length) + '" class="grupo-sep">' + esc(g.label || g.key) + '</th>';
        });
        html += '</tr><tr>';
        grupos.forEach(function (g, gi) {
            (g.cols || []).forEach(function (c, ci) {
                var clsSub = 'sub' + (ci === 0 ? ' grupo-sep' : '');
                html += '<th class="' + clsSub + '">' + esc(c.nome || c.codigo) + '</th>';
            });
        });
        html += '</tr></thead><tbody>';
        (tab.linhas || []).forEach(function (lin) {
            var notas = lin.notas || {};
            var clsMat = 'mat';
            if (lin.eh_grupo_pai) clsMat += ' font-bold';
            if (lin.eh_grupo_filho) clsMat += ' pl-7 text-gray-800';
            var nomeMat = esc(lin.materia_nome);
            if (lin.eh_grupo_filho) nomeMat = '<span class="text-gray-400 mr-1" aria-hidden="true">↳</span>' + nomeMat;
            html += '<tr' + (lin.eh_grupo_pai ? ' class="font-semibold"' : '') + '><td class="' + clsMat + '">' + nomeMat + '</td>';
            grupos.forEach(function (g, gi) {
                (g.cols || []).forEach(function (c, ci) {
                    var v = notaDaLinhaPreview(notas, c.codigo);
                    var clsCel = classePreviewCelula(v, c);
                    if (ci === 0) clsCel = (clsCel ? clsCel + ' ' : '') + 'grupo-sep';
                    html += '<td class="' + clsCel + '">' + fmtPreviewCelula(v, c) + '</td>';
                });
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function notaNqPreview(notas, codigo) {
        var c = String(codigo || '');
        var n = notas[c + '__n'];
        var q = notas[c + '__q'];
        if (n == null && q == null) {
            var low = c.toLowerCase();
            if (low !== c) {
                n = notas[low + '__n'];
                q = notas[low + '__q'];
            }
        }
        return { n: n == null ? null : n, q: q == null ? null : q };
    }

    function extraValorColuna(o) {
        return (colunaEhFaltas(o) || o.layout_type === 'rec') ? '' : '<div class="text-[9px] font-normal opacity-80">Valor 10</div>';
    }

    function linhaLayoutDaColuna(codigo, coluna) {
        codigo = String(codigo || '').toLowerCase();
        var linhas = (estado && estado.linhas_layout) || [];
        for (var i = 0; i < linhas.length; i++) {
            var lin = linhas[i];
            if (!lin || !Array.isArray(lin.colunas)) continue;
            if (lin.colunas.indexOf(codigo) >= 0) return lin;
        }
        // Com cabeçalhos manuais (limpou / montou do zero), não usa "Avaliações" vindo do Demonstrativo.
        if (Number(estado && estado.cabecalhos_manuais || 0) === 1) return null;
        var nomeCab = coluna ? String(coluna.linha_cabecalho || '').trim() : '';
        if (nomeCab) return { id: 'cab:' + nomeCab.toLowerCase(), nome: nomeCab, colunas: [codigo] };
        return null;
    }

    function ordenarColsPeloLayout(cols, lin) {
        cols = cols || [];
        if (!lin || !Array.isArray(lin.colunas) || !lin.colunas.length || cols.length < 2) return cols;
        var map = {};
        cols.forEach(function (c) {
            if (!c || !c.codigo) return;
            map[String(c.codigo).toLowerCase()] = c;
        });
        var out = [];
        var visto = {};
        lin.colunas.forEach(function (cod) {
            cod = String(cod || '').toLowerCase();
            if (!cod || !map[cod] || visto[cod]) return;
            visto[cod] = true;
            out.push(map[cod]);
        });
        cols.forEach(function (c) {
            var cod = String((c && c.codigo) || '').toLowerCase();
            if (!cod || visto[cod]) return;
            visto[cod] = true;
            out.push(c);
        });
        return out;
    }

    /** Ordem flat: cabeçalhos (na ordem de linhas_layout) + colunas soltas. */
    function ordemColunasApartirDoLayout() {
        var ordem = [];
        var visto = {};
        function push(cod) {
            cod = String(cod || '').toLowerCase();
            if (!cod || visto[cod]) return;
            visto[cod] = true;
            ordem.push(cod);
        }
        (estado && estado.linhas_layout || []).forEach(function (lin) {
            (lin && lin.colunas || []).forEach(push);
        });
        (estado && estado.colunas_ordem || []).forEach(push);
        return ordem;
    }

    function sincronizarOrdemComLinhasLayout() {
        if (!estado) return;
        var nova = ordemColunasApartirDoLayout();
        if (nova.length) estado.colunas_ordem = nova;
    }

    function faixasCabecalhoPreview(outras) {
        outras = outras || [];
        var grupos = {};
        var ordemGrupos = [];
        outras.forEach(function (o, idx) {
            if (!o) return;
            var lin = linhaLayoutDaColuna(o.codigo, o);
            if (!lin || !String(lin.nome || '').trim()) return;
            var gid = String(lin.id || ('cab:' + String(lin.nome).toLowerCase()));
            if (!grupos[gid]) {
                grupos[gid] = { nome: lin.nome, lin: lin, cols: [], firstIdx: idx };
                ordemGrupos.push(gid);
            }
            grupos[gid].cols.push(o);
        });
        // Dentro do cabeçalho, respeita a ordem dos chips (linhas_layout), não a flat antiga.
        Object.keys(grupos).forEach(function (gid) {
            grupos[gid].cols = ordenarColsPeloLayout(grupos[gid].cols, grupos[gid].lin);
        });
        // Emite o grupo inteiro na 1ª aparição de qualquer coluna dele —
        // assim MU/bloco de cálculo entra no cabeçalho mesmo se a ordem flat
        // ainda não estiver contígua após o remount.
        var faixas = [];
        var emitidos = {};
        // Preferência: ordem dos cabeçalhos em linhas_layout (como o admin montou).
        // Inclui título vazio (sem chips) para arrastar eventos depois.
        (estado && estado.linhas_layout || []).forEach(function (lin) {
            if (!lin || !String(lin.nome || '').trim()) return;
            var gid = String(lin.id || ('cab:' + String(lin.nome).toLowerCase()));
            if (emitidos[gid]) return;
            emitidos[gid] = true;
            if (grupos[gid]) {
                faixas.push({ tipo: 'grupo', nome: grupos[gid].nome, cols: grupos[gid].cols, id: gid });
            } else {
                faixas.push({ tipo: 'grupo', nome: String(lin.nome), cols: [], id: gid });
            }
        });
        outras.forEach(function (o) {
            if (!o) return;
            var lin = linhaLayoutDaColuna(o.codigo, o);
            if (lin && String(lin.nome || '').trim()) {
                var gid = String(lin.id || ('cab:' + String(lin.nome).toLowerCase()));
                if (emitidos[gid]) return;
                emitidos[gid] = true;
                faixas.push({ tipo: 'grupo', nome: grupos[gid].nome, cols: grupos[gid].cols });
                return;
            }
            faixas.push({ tipo: 'sozinha', col: o });
        });
        return faixas;
    }

    function aplicarOrdemNoPreview(pv) {
        if (!pv) return pv;
        var forcarGrupos = (pv.modo === 'boletim')
            || ehModeloSantaMaria()
            || (typeof ehVisaoImpressaoEditavel === 'function' && ehVisaoImpressaoEditavel());
        // Inclui blocos de cálculo (MU) do estado — o servidor às vezes ainda não devolveu no preview.
        pv.colunas = ordenarColunasLista(mesclarColunasExtrasDoEstado(pv.colunas || []));
        if ((pv.grupos || []).length || forcarGrupos) {
            var flatTop = ordenarColunasLista(flatColsDeGrupos(pv.grupos));
            if (!flatTop.length) flatTop = (pv.colunas || []).slice();
            flatTop = ordenarColunasLista(mesclarColunasExtrasDoEstado(flatTop));
            pv.grupos = montarGruposDeFaixas(flatTop);
        }
        (pv.tabelas || []).forEach(function (t) {
            if (!t) return;
            var flat = flatColsDeGrupos(t.grupos || []);
            if (!flat.length) flat = (t.outras || []).slice();
            if (!flat.length && (pv.grupos || []).length) flat = flatColsDeGrupos(pv.grupos);
            if (!flat.length) flat = (pv.colunas || []).slice();
            flat = ordenarColunasLista(mesclarColunasExtrasDoEstado(flat.length ? flat : (t.outras || [])));
            t.outras = flat;
            if ((t.grupos || []).length || forcarGrupos) {
                t.grupos = montarGruposDeFaixas(flat);
            }
        });
        return pv;
    }

    /** Junta as colunas de um cabeçalho na ordem do arraste (faixa fecha no Exemplo). */
    function compactarColunasOrdem(codigosJuntos) {
        if (!estado) return;
        var bloco = (codigosJuntos || []).map(function (c) { return String(c || '').toLowerCase(); })
            .filter(function (c, i, arr) { return c && arr.indexOf(c) === i; });
        if (bloco.length < 2) return;
        var ordem = (estado.colunas_ordem || []).map(function (c) { return String(c || '').toLowerCase(); });
        var setBloco = {};
        bloco.forEach(function (c) { setBloco[c] = true; });
        var semBloco = ordem.filter(function (c) { return !setBloco[c]; });
        var idxs = bloco.map(function (c) { return ordem.indexOf(c); }).filter(function (i) { return i >= 0; });
        var insertAt = idxs.length ? Math.min.apply(null, idxs) : semBloco.length;
        if (insertAt < 0) insertAt = 0;
        if (insertAt > semBloco.length) insertAt = semBloco.length;
        var nova = semBloco.slice(0, insertAt).concat(bloco).concat(semBloco.slice(insertAt));
        bloco.forEach(function (c) {
            if (nova.indexOf(c) < 0) nova.push(c);
        });
        estado.colunas_ordem = nova;
    }

    function codigosMarcados() {
        return Object.keys(colunasMarcadas).filter(function (cod) { return colunasMarcadas[cod]; });
    }

    function htmlTabelaPreview(tab) {
        if (tab && (tab.grupos || []).length) return htmlTabelaPreviewBoletim(tab);
        var semanas = tab.semanas || [];
        var outras = tab.outras || [];
        var faixas = faixasCabecalhoPreview(outras);
        var html = '<div class="overflow-x-auto max-h-[28rem] border border-gray-300 rounded-lg bg-white mb-4">';
        if (tab.subtitulo) {
            html += '<div class="px-3 py-1.5 text-sm font-semibold text-gray-800 bg-gray-50 border-b">' + esc(tab.subtitulo) + '</div>';
        }
        html += '<table class="bw-preview-table"><thead><tr>';
        html += '<th rowspan="2" class="text-left">' + esc(tab.titulo || 'Matérias') + '</th>';
        semanas.forEach(function (s) {
            html += '<th colspan="2">' + esc(s.nome || s.codigo) + '</th>';
        });
        if (semanas.length) html += '<th colspan="2">Total</th>';
        faixas.forEach(function (faixa) {
            if (faixa.tipo === 'grupo') {
                html += '<th colspan="' + faixa.cols.length + '">' + esc(faixa.nome) + '</th>';
                return;
            }
            html += '<th rowspan="2">' + esc(rotuloColunaPreview(faixa.col)) + extraValorColuna(faixa.col) + '</th>';
        });
        html += '</tr><tr>';
        semanas.forEach(function () { html += '<th class="sub">N</th><th class="sub">Q</th>'; });
        if (semanas.length) html += '<th class="sub">N</th><th class="sub">Q</th>';
        faixas.forEach(function (faixa) {
            if (faixa.tipo !== 'grupo') return;
            faixa.cols.forEach(function (o) {
                html += '<th class="sub">' + esc(rotuloColunaPreview(o)) + extraValorColuna(o) + '</th>';
            });
        });
        html += '</tr></thead><tbody>';
        (tab.linhas || []).forEach(function (lin) {
            var notas = lin.notas || {};
            var totN = 0;
            var totQ = 0;
            var clsMat = 'mat';
            if (lin.eh_grupo_pai) clsMat += ' font-bold';
            if (lin.eh_grupo_filho) clsMat += ' pl-7 text-gray-800';
            var nomeMat = esc(lin.materia_nome);
            if (lin.eh_grupo_filho) nomeMat = '<span class="text-gray-400 mr-1" aria-hidden="true">↳</span>' + nomeMat;
            html += '<tr' + (lin.eh_grupo_pai ? ' class="font-semibold"' : '') + '><td class="' + clsMat + '">' + nomeMat + '</td>';
            semanas.forEach(function (s) {
                var nq = notaNqPreview(notas, s.codigo);
                var n = nq.n;
                var q = nq.q;
                if (n != null) totN += Number(n) || 0;
                if (q != null) totQ += Number(q) || 0;
                html += '<td>' + (n != null ? n : '—') + '</td><td>' + (q != null ? q : '—') + '</td>';
            });
            if (semanas.length) html += '<td><strong>' + totN + '</strong></td><td><strong>' + totQ + '</strong></td>';
            outras.forEach(function (o) {
                html += htmlCelulaOficial(notaDaLinhaPreview(notas, o.codigo), o);
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function previewTemQuadro(pv) {
        return !!((pv && pv.tabelas) || []).some(function (t) { return t && (t.semanas || []).length; });
    }

    function chaveNomeMateria(nome) {
        return String(nome || '').toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ').trim();
    }

    function agregarNotasLinhas(membros, outras, modo, semanas, gl) {
        var notas = {};
        function agregarCodigo(codigo, forcarSoma) {
            if (!codigo) return;
            var vals = [];
            (membros || []).forEach(function (lin) {
                var v = notaDaLinhaPreview((lin && lin.notas) || {}, codigo);
                if (v == null || v === '' || v === '—' || v === '-') return;
                var n = Number(v);
                if (!isFinite(n)) return;
                vals.push(n);
            });
            if (!vals.length) {
                notas[codigo] = null;
                return;
            }
            var total = vals.reduce(function (a, b) { return a + b; }, 0);
            var modoCol = modo === 'soma' ? 'soma' : 'media';
            if (!forcarSoma && gl) {
                var peca = pecaDoCodigoGrupo(codigo);
                if (peca) modoCol = modoEfetivoPecaGrupo(gl, peca);
            }
            if (forcarSoma) {
                notas[codigo] = Math.round(total * 100) / 100;
            } else if (modoCol === 'soma') {
                notas[codigo] = roundNotaGrupo(total, 'mae');
            } else {
                notas[codigo] = roundNotaGrupo(total / vals.length, 'mae');
            }
        }
        var codigosAgregados = {};
        (outras || []).forEach(function (col) {
            if (!col || !col.codigo) return;
            // Média Bim e as colunas seguintes saem da fórmula, não da soma das filhas.
            if (colunaEhCalculadaFormula(col)) return;
            agregarCodigo(col.codigo, colunaEhFaltas(col));
            codigosAgregados[String(col.codigo).toLowerCase()] = true;
        });
        // Peças ocultas (ex.: PP/PU com só MU na grade) ainda sobem para a mãe calcular.
        (membros || []).forEach(function (lin) {
            Object.keys((lin && lin.notas) || {}).forEach(function (cod) {
                var low = String(cod || '').toLowerCase();
                if (!low || codigosAgregados[low] || /__(n|q)$/.test(low)) return;
                if (ehCodigoBlocoCalc(cod)) return;
                agregarCodigo(cod, /fal/.test(low));
                codigosAgregados[low] = true;
            });
        });
        (semanas || []).forEach(function (s) {
            if (!s || !s.codigo) return;
            var cn = s.codigo + '__n';
            var cq = s.codigo + '__q';
            var sumN = 0;
            var sumQ = 0;
            var tem = false;
            (membros || []).forEach(function (lin) {
                var nq = notaNqPreview((lin && lin.notas) || {}, s.codigo);
                if (nq.n != null) { sumN += Number(nq.n) || 0; tem = true; }
                if (nq.q != null) { sumQ += Number(nq.q) || 0; tem = true; }
            });
            if (tem) {
                notas[cn] = sumN;
                notas[cq] = sumQ;
            }
        });
        return notas;
    }

    function expressoesCalculadasDoGrupo() {
        var lista = [];
        var visto = {};
        function push(cod, exp) {
            cod = String(cod || '').toLowerCase();
            exp = String(exp || '').trim();
            if (!cod || !exp || cod === 'media_sem' || visto[cod]) return;
            visto[cod] = true;
            lista.push({ codigo: cod, expressao: exp });
        }
        Object.keys((estado && estado.formulas_blocos) || {}).forEach(function (cod) {
            var toks = estado.formulas_blocos[cod];
            if (!Array.isArray(toks) || !toks.length) return;
            push(cod, compilarTokensPreview(toks.map(function (t) {
                if (!t || t.type !== 'peca') return t;
                return { type: 'peca', value: pecaCodigoQuadro(t.value), label: t.label || '' };
            })));
        });
        ((rascunhoAtual && rascunhoAtual.componentes) || []).forEach(function (c) {
            if (!c || String(c.source_type || '') !== 'calculado') return;
            var cfg = c.config || {};
            if (typeof cfg === 'string') {
                try { cfg = JSON.parse(cfg); } catch (e) { cfg = {}; }
            }
            if (cfg.agregar_nq && cfg.agregar_nq.length) return;
            push(c.codigo, cfg.expressao || c.expressao);
        });
        return lista;
    }

    function notaFormulaGrupo(notas, codigo) {
        var aliases = {
            semanal: 'media_sem',
            media_sem: 'semanal',
            bimestral: 'prova_bim',
            prova_bim: 'bimestral',
            recuperacao: 'rec',
            rec: 'recuperacao',
            trabalho: 'trab',
            trab: 'trabalho',
            participacao: 'part',
            part: 'participacao'
        };
        var direto = notaDaLinhaPreview(notas, codigo);
        if (direto == null || direto === '' || !isFinite(Number(direto))) {
            var outro = aliases[String(codigo || '').toLowerCase()];
            if (outro) direto = notaDaLinhaPreview(notas, outro);
        }
        if (direto == null || direto === '' || !isFinite(Number(direto))) return null;
        return Number(direto);
    }

    function extrairArgsFuncaoJs(src, openIdx) {
        var n = src.length;
        var i = openIdx + 1;
        var depth = 1;
        var args = [];
        var inicio = i;
        for (; i < n; i++) {
            var c = src.charAt(i);
            if (c === '(') depth++;
            else if (c === ')') {
                depth--;
                if (depth === 0) {
                    args.push(src.slice(inicio, i));
                    return { args: args, close: i };
                }
            } else if (c === ',' && depth === 1) {
                args.push(src.slice(inicio, i));
                inicio = i + 1;
            }
        }
        return null;
    }

    /** Expande chamada/substituta(bases…, subst) → média das bases (1ª ausente recebe a subst). */
    function expandirChamadaJs(src, notas) {
        var out = String(src || '');
        var guard = 0;
        while (/\b(?:chamada|substituta)\s*\(/i.test(out) && guard++ < 40) {
            var m = out.match(/\b(?:chamada|substituta)\s*\(/i);
            if (!m) break;
            var startFn = m.index;
            var openParen = startFn + m[0].length - 1;
            var parsed = extrairArgsFuncaoJs(out, openParen);
            if (!parsed || parsed.args.length < 2) return null;
            var args = parsed.args.slice();
            var subst = args.pop();
            var preencheu = false;
            var termos = args.map(function (base) {
                var b = String(base || '').trim();
                var ehIdent = /^[A-Za-z_][A-Za-z0-9_]*$/.test(b);
                if (ehIdent && notaFormulaGrupo(notas, b) == null && !preencheu) {
                    preencheu = true;
                    return '(' + subst + ')';
                }
                return '(' + b + ')';
            });
            var media = termos.length === 1
                ? termos[0]
                : '((' + termos.join('+') + ')/' + termos.length + ')';
            out = out.slice(0, startFn) + media + out.slice(parsed.close + 1);
            // substituta já é média — tira ÷ N redundante (ex.: …/2 → nota pela metade).
            if (termos.length > 1) {
                var cauda = out.slice(startFn + media.length);
                var divRedundante = cauda.match(/^\s*\/\s*(\d+(?:[.,]\d+)?)/);
                if (divRedundante && Number(String(divRedundante[1]).replace(',', '.')) === termos.length) {
                    out = out.slice(0, startFn + media.length) + cauda.slice(divRedundante[0].length);
                }
            }
        }
        return out;
    }

    /** Expande composicao(média, REC): sem REC → média; com REC → (média+REC)/2. */
    function expandirComposicaoJs(src, notas) {
        var out = String(src || '');
        var guard = 0;
        while (/\bcomposicao\s*\(/i.test(out) && guard++ < 40) {
            var m = out.match(/\bcomposicao\s*\(/i);
            if (!m) break;
            var startFn = m.index;
            var openParen = startFn + m[0].length - 1;
            var parsed = extrairArgsFuncaoJs(out, openParen);
            if (!parsed || parsed.args.length !== 2) return null;
            var media = String(parsed.args[0] || '').trim();
            var rec = String(parsed.args[1] || '').trim();
            var ehIdent = /^[A-Za-z_][A-Za-z0-9_]*$/.test(rec);
            var subst = (ehIdent && notaFormulaGrupo(notas, rec) == null)
                ? '(' + media + ')'
                : '((' + media + ')+(' + rec + '))/2';
            out = out.slice(0, startFn) + subst + out.slice(parsed.close + 1);
        }
        return out;
    }

    function avaliarExpressaoGrupo(exp, notas) {
        var src = String(exp || '').trim();
        if (!src) return null;
        src = src.replace(/\bsubstituta\s*\(/ig, 'chamada(');
        if (/\bchamada\s*\(/i.test(src)) {
            src = expandirChamadaJs(src, notas);
            if (src == null) return null;
        }
        if (/\bcomposicao\s*\(/i.test(src)) {
            src = expandirComposicaoJs(src, notas);
            if (src == null) return null;
        }
        src = src.replace(/[A-Za-z_][A-Za-z0-9_]*/g, function (nome) {
            var baixo = nome.toLowerCase();
            if (baixo === 'max' || baixo === 'min' || baixo === 'chamada' || baixo === 'substituta' || baixo === 'composicao') return baixo;
            var v = notaFormulaGrupo(notas, nome);
            if (v == null || !isFinite(v)) return '0';
            return String(v);
        });
        if (!/^[\d\s+\-*/().,]+$/.test(src.replace(/\b(?:max|min|chamada|substituta|composicao)\b/g, ''))) return null;
        src = src.replace(/\bmax\b/g, 'Math.max').replace(/\bmin\b/g, 'Math.min');
        try {
            var n = Function('"use strict"; return (' + src + ');')();
            if (!isFinite(n)) return null;
            return roundNotaGrupo(n, 'mae');
        } catch (e) {
            return null;
        }
    }

    function formulaGrupoFaltouPecaObrigatoria(codigo, exp, notas) {
        var cod = String(codigo || '').toLowerCase();
        var src = String(exp || '');
        var temChamada = /\b(?:chamada|substituta)\s*\(/i.test(src);
        var refs = src.match(/\b[a-zA-Z_][a-zA-Z0-9_]*\b/g) || [];
        var i;
        var algumaPeca = false;
        for (i = 0; i < refs.length; i++) {
            var ref = String(refs[i] || '');
            var baixo = ref.toLowerCase();
            if (baixo === 'max' || baixo === 'min' || baixo === 'chamada' || baixo === 'substituta' || baixo === 'composicao') {
                continue;
            }
            var tem = notaFormulaGrupo(notas, ref) != null;
            if (tem) algumaPeca = true;
            // Sem nota substituta: MU exige PP e PU. Com substituta, CH pode cobrir.
            if (!temChamada && /^b[1-4]_mu$/.test(cod) && /_(pp|pu)$/.test(baixo) && !tem) return true;
            if (/^s[12]_ms$/.test(cod) && /_mu$/.test(baixo) && !tem) return true;
            if (cod === 'ma' && /^s[12]_ms$/.test(baixo) && !tem) return true;
        }
        return !algumaPeca && refs.length > 0;
    }

    function aplicarFormulasNaLinhaGrupo(notas) {
        var exprs = expressoesCalculadasDoGrupo();
        if (!exprs.length) return notas;
        var i;
        var j;
        for (i = 0; i < exprs.length; i++) {
            for (j = 0; j < exprs.length; j++) {
                if (formulaGrupoFaltouPecaObrigatoria(exprs[j].codigo, exprs[j].expressao, notas)) continue;
                var valor = avaliarExpressaoGrupo(exprs[j].expressao, notas);
                if (valor == null) continue;
                notas[exprs[j].codigo] = valor;
            }
        }
        return notas;
    }

    /**
     * Demonstrativo: mãe (médias) + filhos aninhados. Se o servidor já mandou eh_grupo_pai, mantém.
     */
    function materiasDaAreaAtiva() {
        var gl = (estado && estado.grupo_linha) || grupoLinhaPadrao();
        var fams = familiasDoEscopo();
        if (!fams.length) return materiasCatalogoDoEscopo();
        var escolhida = null;
        fams.forEach(function (f) {
            if (idsGrupoIguais(gl.materias_ids, f.materias_ids)) escolhida = f;
        });
        if (!escolhida) {
            fams.forEach(function (f) {
                var hit = (f.materias_ids || []).some(function (id) {
                    return (gl.materias_ids || []).some(function (x) { return Number(x) === Number(id); });
                });
                if (hit) escolhida = f;
            });
        }
        if (!escolhida && fams.length === 1) escolhida = fams[0];
        if (!escolhida) return materiasCatalogoDoEscopo();
        return (escolhida.filhos || []).map(function (ch) {
            return { id: Number(ch.id), nome: ch.nome || '' };
        }).filter(function (m) { return m.id > 0 && m.nome; });
    }

    /** Áreas com título no boletim (mãe + filhos). No SM, todas as famílias do escopo. */
    function gruposTituloParaPreview() {
        var out = [];
        var visto = {};
        function pushGrupo(g) {
            if (!g || !g.ativo) return;
            var ids = (g.materias_ids || []).map(Number).filter(function (n) { return n > 0; });
            if (ids.length < 2) return;
            var nome = String(g.nome || '').trim() || 'Grupo';
            var key = chaveNomeMateria(nome) + '|' + ids.slice().sort(function (a, b) { return a - b; }).join(',');
            if (visto[key]) return;
            visto[key] = true;
            out.push({
                ativo: true,
                nome: nome,
                materias_ids: ids,
                modo: g.modo === 'soma' ? 'soma' : 'media',
                modos: g.modos || {},
                exemplo_sem_filhas: !!g.exemplo_sem_filhas,
                arredondamento: g.arredondamento || 'todos'
            });
        }
        // Print SM / boletim: cada área com desdobramento vira linha-mãe (História → 1/2).
        if (ehModeloSantaMaria() || (estado && estado.exibir_em === 'boletim')) {
            familiasDoEscopo().forEach(function (f) {
                pushGrupo({
                    ativo: true,
                    nome: f.nome,
                    materias_ids: (f.materias_ids || []).slice(),
                    modo: 'soma',
                    exemplo_sem_filhas: false
                });
            });
        }
        var gl = (estado && estado.grupo_linha) || {};
        if (gl.ativo) pushGrupo(gl);
        return out;
    }

    function aplicarUmGrupoLinha(listaIn, gl, outras, semanas) {
        var lista = (listaIn || []).slice();
        if (!gl || !gl.ativo || !(gl.materias_ids || []).length) return lista;
        var idsGrupo = {};
        (gl.materias_ids || []).forEach(function (id) {
            id = Number(id || 0);
            if (id > 0) idsGrupo[id] = true;
        });
        var nomesGrupo = {};
        var nomePorId = {};
        (catalogo.materias || []).forEach(function (m) {
            var id = Number(m.id);
            if (!idsGrupo[id]) return;
            nomesGrupo[chaveNomeMateria(m.nome)] = true;
            nomePorId[id] = m.nome;
        });
        var filhosNaLista = 0;
        lista.forEach(function (lin) {
            if (!lin || lin.eh_grupo_pai) return;
            if (idsGrupo[Number(lin.materia_id)] || nomesGrupo[chaveNomeMateria(lin.materia_nome)]) filhosNaLista++;
        });
        // Sem filha neste bloco, não inventa outra área vazia.
        if (filhosNaLista === 0) return lista;
        Object.keys(idsGrupo).forEach(function (id) {
            id = Number(id);
            var nome = nomePorId[id] || '';
            var kn = chaveNomeMateria(nome);
            var ja = lista.some(function (lin) {
                if (!lin || lin.eh_grupo_pai) return false;
                return Number(lin.materia_id) === id || (kn && chaveNomeMateria(lin.materia_nome) === kn);
            });
            if (!ja && nome) {
                lista.push({ materia_id: id, materia_nome: nome, notas: {} });
            }
        });
        var notasMae = null;
        var nomeMae = chaveNomeMateria(gl.nome);
        lista.forEach(function (lin) {
            if (!lin || !lin.eh_grupo_pai) return;
            if (chaveNomeMateria(lin.materia_nome) === nomeMae && lin.notas) notasMae = lin.notas;
        });
        var membros = [];
        var out = [];
        var pos = -1;
        var nomePai = String(gl.nome || 'Grupo').trim() || 'Grupo';
        lista.forEach(function (lin) {
            if (!lin) return;
            // Mantém outras mães já montadas; a desta área some (recria abaixo).
            if (lin.eh_grupo_pai) {
                if (chaveNomeMateria(lin.materia_nome) === nomeMae) return;
                out.push(lin);
                return;
            }
            var entra = idsGrupo[Number(lin.materia_id)] || nomesGrupo[chaveNomeMateria(lin.materia_nome)];
            if (!entra) {
                if (nomeMae && chaveNomeMateria(lin.materia_nome) === nomeMae && !idsGrupo[Number(lin.materia_id)]) {
                    if (!notasMae) notasMae = lin.notas || null;
                    return;
                }
                out.push(lin);
                return;
            }
            if (pos < 0) {
                pos = out.length;
                out.push(null);
            }
            membros.push(Object.assign({}, lin, {
                eh_grupo_filho: 1,
                eh_grupo_pai: 0,
                grupo_pai_nome: nomePai,
                grupo_key: chaveNomeMateria(nomePai)
            }));
        });
        if (membros.length < 2 || pos < 0) return (listaIn || []).slice();
        membros.sort(function (a, b) {
            return chaveNomeMateria(a.materia_nome).localeCompare(chaveNomeMateria(b.materia_nome));
        });
        var notasPai = agregarNotasLinhas(membros, outras, gl.modo === 'soma' ? 'soma' : 'media', semanas, gl);
        if (notasMae) {
            Object.keys(notasMae).forEach(function (cod) {
                var atual = notasPai[cod];
                if ((atual == null || atual === '') && notasMae[cod] != null && notasMae[cod] !== '') {
                    notasPai[cod] = notasMae[cod];
                }
            });
        }
        aplicarFormulasNaLinhaGrupo(notasPai);
        out[pos] = {
            materia_id: 0,
            materia_nome: nomePai,
            notas: notasPai,
            eh_grupo_pai: 1,
            eh_grupo_filho: 0,
            grupo_key: chaveNomeMateria(nomePai)
        };
        if (!gl.exemplo_sem_filhas) {
            Array.prototype.splice.apply(out, [pos + 1, 0].concat(membros));
        }
        return out.filter(Boolean);
    }

    function linhasDoDemonstrativo(brutas, outras, semanas) {
        return linhasComTituloBoletim(brutas, outras, semanas);
    }

    function linhasComTituloBoletim(brutas, outras, semanas) {
        var lista = (brutas || []).slice();
        // Servidor já mandou mãe + filhos (Título no boletim herdado).
        if (lista.some(function (l) { return l && l.eh_grupo_pai; })) {
            return lista;
        }
        var grupos = gruposTituloParaPreview();
        if (!grupos.length) return lista;
        grupos.forEach(function (gl) {
            lista = aplicarUmGrupoLinha(lista, gl, outras, semanas);
        });
        return lista;
    }

    function ordenarLinhasPorNome(lista) {
        return (lista || []).slice().sort(function (a, b) {
            var ka = chaveNomeMateria(a && a.materia_nome);
            var kb = chaveNomeMateria(b && b.materia_nome);
            if (ka < kb) return -1;
            if (ka > kb) return 1;
            return (Number(a && a.materia_id) || 0) - (Number(b && b.materia_id) || 0);
        });
    }

    function linhasDoBoletim(pv, outras) {
        // Preferir hierarquia completa (mãe + filhos) quando o servidor já montou
        // o "Título no boletim" — não usar a matriz colapsada só com a mãe.
        var brutas = (pv && Array.isArray(pv.linhas_completas) && pv.linhas_completas.length)
            ? pv.linhas_completas.slice()
            : [];
        if (!brutas.length) {
            (pv.tabelas || []).forEach(function (t) {
                (t.linhas || []).forEach(function (lin) { brutas.push(lin); });
            });
        }
        if (brutas.some(function (l) { return l && l.eh_grupo_pai; })) {
            // Mantém HISTÓRIA → História 1 / História 2 na ordem do servidor.
            return brutas.filter(Boolean);
        }
        if (pv && Array.isArray(pv.linhas_boletim) && pv.linhas_boletim.length) {
            return ordenarLinhasPorNome(pv.linhas_boletim.slice());
        }
        var porId = {};
        var porNome = {};
        var linhas = [];
        // "Juntar matérias iguais": soma ou média conforme materia_unica_modo.
        var modoIguais = (estado && estado.materia_unica)
            ? (estado.materia_unica_modo === 'media' ? 'media' : 'soma')
            : '';
        brutas.forEach(function (lin) {
            if (!lin) return;
            var id = Number(lin.materia_id || 0);
            var nome = String(lin.materia_nome || '').trim();
            var kn = chaveNomeMateria(nome);
            if (!nome) return;
            if (id && porId[id]) return;
            var registro = { materia_id: id, materia_nome: nome, notas: lin.notas || {} };
            if (kn && porNome[kn]) {
                if (modoIguais) {
                    porNome[kn].notas = agregarNotasLinhas([porNome[kn], registro], outras, modoIguais);
                }
                return;
            }
            if (id) porId[id] = true;
            linhas.push(registro);
            if (kn) porNome[kn] = registro;
        });

        var idsEscopo = {};
        materiasCatalogoDoEscopo().forEach(function (m) { idsEscopo[Number(m.id)] = true; });
        linhas.forEach(function (lin) { if (lin.materia_id) idsEscopo[lin.materia_id] = true; });
        var paisOcultos = {};
        (catalogo.familias_componentes || []).forEach(function (f) {
            var temFilho = (f.filhos || []).some(function (ch) { return idsEscopo[Number(ch.id)]; });
            if (temFilho) paisOcultos[Number(f.pai_id)] = true;
        });

        materiasCatalogoDoEscopo().forEach(function (m) {
            var id = Number(m.id || 0);
            var nome = String(m.nome || '').trim();
            var kn = chaveNomeMateria(nome);
            if (!id || !nome || paisOcultos[id]) return;
            if (porId[id] || (kn && porNome[kn])) return;
            porId[id] = true;
            if (kn) porNome[kn] = true;
            linhas.push({ materia_id: id, materia_nome: nome, notas: {} });
        });

        var gl = (estado && estado.grupo_linha) || {};
        var onde = gl.aplicar_em || 'boletim';
        if (!gl.ativo || (onde !== 'boletim' && onde !== 'ambos')) return ordenarLinhasPorNome(linhas);
        var idsGrupo = {};
        (gl.materias_ids || []).forEach(function (id) {
            id = Number(id || 0);
            if (id > 0) idsGrupo[id] = true;
        });
        var nomesGrupo = {};
        (catalogo.materias || []).forEach(function (m) {
            if (idsGrupo[Number(m.id)]) nomesGrupo[chaveNomeMateria(m.nome)] = true;
        });
        if (!Object.keys(idsGrupo).length) return ordenarLinhasPorNome(linhas);

        var membros = [];
        var out = [];
        var pos = -1;
        linhas.forEach(function (lin) {
            var entra = idsGrupo[Number(lin.materia_id)] || nomesGrupo[chaveNomeMateria(lin.materia_nome)];
            if (!entra) {
                out.push(lin);
                return;
            }
            if (pos < 0) {
                pos = out.length;
                out.push(null);
            }
            membros.push(lin);
        });
        if (membros.length < 2 || pos < 0) return ordenarLinhasPorNome(linhas);
        out[pos] = {
            materia_id: 0,
            materia_nome: String(gl.nome || 'Grupo').trim() || 'Grupo',
            notas: aplicarFormulasNaLinhaGrupo(agregarNotasLinhas(membros, outras, gl.modo === 'soma' ? 'soma' : 'media', [], gl))
        };
        return ordenarLinhasPorNome(out);
    }

    function rotuloColunaPreview(o) {
        if (!o) return '';
        var custom = tituloColuna(o.codigo);
        if (custom) return custom;
        var sig = siglaTituloColunaJs(o.codigo, o.nome);
        if (sig) return sig;
        var cod = String(o.codigo || '').toLowerCase();
        var nome = String(o.nome || '');
        var nomeNorm = nome.toLocaleLowerCase('pt-BR');
        nomeNorm = nomeNorm.normalize ? nomeNorm.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : nomeNorm;
        nomeNorm = nomeNorm.replace(/[^a-z0-9]+/g, ' ').trim();
        if (nomeNorm.indexOf('media bim final') >= 0 || nomeNorm.indexOf('resultado final') >= 0) return 'Resultado Final';
        if (cod === 'media_bim_final' || cod === 'resultado_final' || cod === 'media_final') {
            if (cod !== 'media_final' || nomeNorm === '' || nomeNorm === 'media final' || nomeNorm === 'media bim final' || nomeNorm === 'resultado final') {
                return 'Resultado Final';
            }
        }
        return nome || o.codigo || '';
    }

    function colunaEhResultadoResumo(o) {
        if (!o) return false;
        var lt = String(o.layout_type || '').toLowerCase();
        if (lt === 'resultado') return true;
        var cod = String(o.codigo || '').toLowerCase();
        if (cod === 'resultado' || cod === 'resultado_final' || cod === 'media_final' || cod === 'media_bim_final') return true;
        if (/(?:^|_)media_(?:bim_)?final$/.test(cod)) return true;
        var nome = String(o.nome || '').toLocaleLowerCase('pt-BR');
        if (nome.indexOf('resultado') >= 0) return true;
        var nomeNorm = nome.normalize ? nome.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : nome;
        return nomeNorm.indexOf('media bim final') >= 0 || nomeNorm.indexOf('media final') >= 0;
    }

    function htmlTabelaSomenteNotas(pv) {
        var outras = [];
        var visto = {};
        (pv.tabelas || []).forEach(function (t) {
            (t.outras || []).forEach(function (o) {
                if (!o || !o.codigo || visto[o.codigo]) return;
                if (!colunaEhFaltas(o) && !colunaEhResultadoResumo(o)) return;
                visto[o.codigo] = true;
                outras.push(o);
            });
        });
        outras.sort(function (a, b) {
            var af = colunaEhFaltas(a) ? 1 : 0;
            var bf = colunaEhFaltas(b) ? 1 : 0;
            return af - bf;
        });
        var linhas = linhasDoBoletim(pv, outras);
        var html = '<div class="overflow-x-auto max-h-[28rem] border border-gray-300 rounded-lg bg-white mb-4">';
        html += '<div class="px-3 py-1.5 text-sm font-semibold border-b" style="background:var(--sidebar-bg-color,#1e3a5f);color:var(--sidebar-text-color,#fff);border-color:color-mix(in srgb,var(--sidebar-bg-color,#1e3a5f) 75%,#000)">Boletim (resultado e faltas)</div>';
        if (!outras.length) {
            html += '<p class="px-3 py-3 text-sm text-gray-500">Não há colunas de resultado ou faltas neste evento.</p></div>';
            return html;
        }
        html += '<table class="bw-preview-table"><thead><tr>';
        html += '<th class="text-left">Matéria</th>';
        outras.forEach(function (o) {
            var extra = colunaEhFaltas(o) ? '' : '<div class="text-[9px] font-normal opacity-80">Valor 10</div>';
            html += '<th>' + esc(rotuloColunaPreview(o)) + extra + '</th>';
        });
        html += '</tr></thead><tbody>';
        linhas.forEach(function (lin) {
            var notas = lin.notas || {};
            var clsMat = 'mat';
            if (lin.eh_grupo_pai) clsMat += ' font-bold';
            if (lin.eh_grupo_filho) clsMat += ' pl-7 text-gray-800';
            var nomeMat = esc(lin.materia_nome);
            if (lin.eh_grupo_filho) nomeMat = '<span class="text-gray-400 mr-1" aria-hidden="true">↳</span>' + nomeMat;
            html += '<tr' + (lin.eh_grupo_pai ? ' class="font-semibold"' : '') + '><td class="' + clsMat + '">' + nomeMat + '</td>';
            outras.forEach(function (o) {
                html += '<td>' + fmtPreviewCelula(notaDaLinhaPreview(notas, o.codigo), o) + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function ehEventoNotasPreview() {
        return !!(estado && estado.exibir_em === 'notas' && !ehBoletimComposto());
    }

    function ehDemonstrativoNotas() {
        return !!(estado && estado.exibir_em === 'notas' && !ehBoletimComposto() && !ehVisaoImpressaoEditavel() && !ehModeloSantaMaria());
    }

    function zerarFormulasDemonstrativoNotas() {
        if (!ehDemonstrativoNotas()) return;
        estado.formulas_blocos = {};
        estado.formulas_materias_blocos = {};
        estado.blocos_calc = [];
        estado.bloco_calc = '';
        estado.formula_final = '';
        estado.formula_tokens = [];
    }

    function htmlCelulaOficial(v, col) {
        var texto = fmtPreviewCelula(v, col);
        var numerica = v != null && v !== '' && v !== '—' && v !== '-' && isFinite(Number(v));
        var cls = numerica ? ' class="text-emerald-800 font-semibold"' : '';
        return '<td' + cls + '>' + texto + '</td>';
    }

    function htmlBlocoVistaOficial(titulo, texto, corpo) {
        return '<section class="mb-6">'
            + '<h4 class="text-sm font-semibold text-gray-900">' + esc(titulo) + '</h4>'
            + '<p class="text-xs text-gray-500 mt-0.5 mb-2">' + texto + '</p>'
            + corpo
            + '</section>';
    }

    function htmlPreview(pv) {
        var html = htmlAlunoPreviewSelect();
        if (!pv || !(pv.tabelas || []).length) {
            return html + '<p class="text-xs text-gray-600 mt-2">' + esc((pv && pv.aviso) || 'Monte as peças para ver o exemplo.') + '</p>';
        }
        var avisoCls = pv.dados_reais
            ? 'text-[11px] font-semibold text-emerald-800 bg-emerald-50 border border-emerald-100 rounded-md px-2 py-1 mb-3'
            : 'text-[11px] font-semibold text-amber-800 bg-amber-50 border border-amber-100 rounded-md px-2 py-1 mb-3';
        html += '<p class="' + avisoCls + '">' + esc(pv.aviso || 'Exemplo com dados fictícios.') + '</p>';
        if (ehEventoNotasPreview()) {
            var quadro = '';
            (pv.tabelas || []).forEach(function (t) {
                var tab = Object.assign({}, t, {
                    subtitulo: t.subtitulo === 'Exemplo' ? '' : (t.subtitulo || ''),
                    linhas: linhasDoDemonstrativo(t.linhas || [], t.outras || [], t.semanas || [])
                });
                quadro += htmlTabelaPreview(tab);
            });
            html += htmlBlocoVistaOficial(
                ehModeloSantaMaria() ? 'Boletim (print Santa Maria)' : 'Demonstrativo de Notas',
                ehModeloSantaMaria() ? 'PP/PU/CH/PE/MU/FAL · semestres · final.' : 'Blocos, semanas e médias.',
                quadro
            );
            return html;
        }
        if (previewTemQuadro(pv)) {
            html += '<div class="flex flex-wrap gap-2 mb-3">';
            html += '<button type="button" data-vista-preview="quadro" class="px-3 py-1.5 text-xs font-medium rounded-lg border ' + (vistaPreview === 'quadro' ? 'text-white' : 'bg-white text-gray-700 border-gray-300') + '"' + (vistaPreview === 'quadro' ? ' style="background:var(--sidebar-bg-color,#1e3a5f);border-color:var(--sidebar-bg-color,#1e3a5f);color:var(--sidebar-text-color,#fff)"' : '') + '>Demonstrativo de Notas</button>';
            html += '<button type="button" data-vista-preview="boletim" class="px-3 py-1.5 text-xs font-medium rounded-lg border ' + (vistaPreview === 'boletim' ? 'text-white' : 'bg-white text-gray-700 border-gray-300') + '"' + (vistaPreview === 'boletim' ? ' style="background:var(--sidebar-bg-color,#1e3a5f);border-color:var(--sidebar-bg-color,#1e3a5f);color:var(--sidebar-text-color,#fff)"' : '') + '>Boletim</button>';
            html += '</div>';
            if (vistaPreview === 'boletim') {
                return html + htmlTabelaSomenteNotas(pv);
            }
        }
        var corpoBoletim = '';
        (pv.tabelas || []).forEach(function (t) {
            if (pv.modo === 'boletim' && !(t.grupos || []).length && (pv.grupos || []).length) {
                t = Object.assign({}, t, { grupos: pv.grupos });
            }
            // Sempre monta mãe + filhos (ex.: História → 1/2), inclusive no print SM com grupos.
            t = Object.assign({}, t, {
                linhas: linhasComTituloBoletim(t.linhas || [], t.outras || [], t.semanas || [])
            });
            corpoBoletim += htmlTabelaPreview(t);
        });
        if (pv.modo === 'boletim' || ehModeloSantaMaria() || pv.modelo_santa_maria) {
            html += htmlBlocoVistaOficial(
                (ehModeloSantaMaria() || pv.modelo_santa_maria) ? 'Boletim (print Santa Maria)' : 'Boletim',
                (ehModeloSantaMaria() || pv.modelo_santa_maria)
                    ? 'I–IV bimestre · PP/PU/CH/PE/MU/FAL · semestres · resultado final.'
                    : 'Médias e faltas por bimestre.',
                corpoBoletim
            );
            return html;
        }
        return html + corpoBoletim;
    }

    function colunaSegueBlocoSemanal(c) {
        return !!(c && (c.codigo === 'media_sem' || c.layout_type === 'media_sem'));
    }

    function ordenarColunasLista(cols) {
        // Preferência: ordem dos chips nos cabeçalhos (linhas_layout) + soltas.
        var ordem = ordemColunasApartirDoLayout();
        if (!ordem.length) ordem = (estado && estado.colunas_ordem) || [];
        if (!cols || !cols.length) return cols || [];
        var map = {};
        var travadas = [];
        var pinadas = [];
        var moveis = [];
        cols.forEach(function (c) {
            if (!c) return;
            if (c.travada || c.codigo === '_semanal') travadas.push(c);
            else if (colunaSegueBlocoSemanal(c)) pinadas.push(c);
            else {
                var key = String(c.codigo || '').toLowerCase();
                map[key] = c;
                moveis.push(c);
            }
        });
        var base = travadas.concat(pinadas);
        if (!ordem.length) return base.concat(moveis);
        var out = base.slice();
        var visto = {};
        ordem.forEach(function (cod) {
            var key = String(cod || '').toLowerCase();
            if (map[key] && !visto[key]) {
                out.push(map[key]);
                visto[key] = true;
            }
        });
        moveis.forEach(function (c) {
            var key = String(c.codigo || '').toLowerCase();
            if (!visto[key]) out.push(c);
        });
        return out;
    }

    function codigosVivosDoRascunho() {
        var set = {};
        [rascunhoAtual, estado && estado.rascunho_preservado].forEach(function (r) {
            if (!r || !Array.isArray(r.componentes)) return;
            r.componentes.forEach(function (c) {
                var cod = String((c && c.codigo) || '').toLowerCase();
                if (cod) set[cod] = true;
            });
        });
        return set;
    }

    function limparOrdemColunasFantasma() {
        if (!estado) return;
        var manualDoZero = quadroManualDoZero();
        var vivos = codigosVivosDoRascunho();
        // Blocos de cálculo (MU amarelo) vivem em blocos_calc/formulas — não são fantasma.
        (estado.blocos_calc || []).forEach(function (cod) {
            var c = String(cod || '').toLowerCase();
            if (c) vivos[c] = true;
        });
        Object.keys(estado.formulas_blocos || {}).forEach(function (cod) {
            var c = String(cod || '').toLowerCase();
            if (c) vivos[c] = true;
        });
        // Do zero: nomes_blocos (siglas de eventos marcados) NÃO entram sozinhas no quadro.
        if (!manualDoZero) {
            Object.keys(estado.nomes_blocos || {}).forEach(function (cod) {
                var c = String(cod || '').toLowerCase();
                if (c && !/^(eva|sev|rcv)_?\d+$/.test(c)) vivos[c] = true;
            });
        }
        var temVivos = Object.keys(vivos).length > 0;
        estado.colunas_ordem = (estado.colunas_ordem || []).map(function (c) {
            return String(c || '').toLowerCase();
        }).filter(function (c, i, arr) {
            if (!c || arr.indexOf(c) !== i) return false;
            // Manual: mantém o que o admin adicionou (mesmo sem rascunho ainda).
            if (manualDoZero) return true;
            return !temVivos || !!vivos[c];
        });
        // Nunca espalha todos os vivos no board no quadro do zero.
        if (!manualDoZero && temVivos) {
            Object.keys(vivos).forEach(function (c) {
                if (estado.colunas_ordem.indexOf(c) < 0) estado.colunas_ordem.push(c);
            });
        }
        if (Array.isArray(estado.linhas_layout)) {
            estado.linhas_layout = estado.linhas_layout.map(function (lin) {
                if (!lin) return lin;
                lin.colunas = (lin.colunas || []).map(function (c) {
                    return String(c || '').toLowerCase();
                }).filter(function (c) {
                    if (!c) return false;
                    if (manualDoZero) return true;
                    return !temVivos || !!vivos[c];
                });
                return lin;
            }).filter(function (lin) { return lin && String(lin.nome || '').trim(); });
        }
        if (estado.nomes_blocos && typeof estado.nomes_blocos === 'object') {
            Object.keys(estado.nomes_blocos).forEach(function (cod) {
                var low = String(cod || '').toLowerCase();
                if (low && !vivos[low] && /^(eva|sev|rcv)_?\d+$/.test(low)) {
                    delete estado.nomes_blocos[cod];
                }
            });
        }
        removerMuAutomaticaDoEstado();
        // Cabeçalhos manuais: ordem do demonstrativo = ordem dos chips no cabeçalho.
        sincronizarOrdemComLinhasLayout();
        (estado.linhas_layout || []).forEach(function (lin) {
            if (lin && (lin.colunas || []).length >= 1) compactarColunasOrdem(lin.colunas);
        });
        sincronizarOrdemComLinhasLayout();
    }

    /** Quadro do zero: zera chips automáticos (só entram pelo clique no catálogo). */
    function zerarQuadroColunasSeAutomatico() {
        if (!estado || !quadroManualDoZero()) return;
        if (estado.quadro_chips_manuais) return;
        // Títulos do período manuais não podem ser apagados só porque o flag de chips falhou.
        if (Number(estado.cabecalhos_manuais || 0) === 1
            && Array.isArray(estado.linhas_layout) && estado.linhas_layout.length) {
            estado.quadro_chips_manuais = 1;
            return;
        }
        estado.colunas_ordem = [];
        estado.linhas_layout = [];
        if (rascunhoAtual && Array.isArray(rascunhoAtual.componentes)) {
            rascunhoAtual.componentes = (rascunhoAtual.componentes || []).filter(function (c) {
                if (!c) return false;
                var cod = String(c.codigo || '').toLowerCase();
                if (!cod || cod === '_semanal') return false;
                return ehCodigoBlocoCalc(cod) || !!(estado.formulas_blocos && estado.formulas_blocos[cod]);
            });
        }
        if (estado.rascunho_preservado && Array.isArray(estado.rascunho_preservado.componentes)) {
            estado.rascunho_preservado.componentes = (estado.rascunho_preservado.componentes || []).filter(function (c) {
                if (!c) return false;
                var cod = String(c.codigo || '').toLowerCase();
                if (!cod || cod === '_semanal') return false;
                return ehCodigoBlocoCalc(cod) || !!(estado.formulas_blocos && estado.formulas_blocos[cod]);
            });
        }
    }

    function mesclarColunasExtrasDoEstado(cols) {
        cols = (cols || []).slice().filter(function (c) {
            return c && c.codigo && !colunaOculta(c.codigo);
        });
        var vistos = {};
        var vivos = codigosVivosDoRascunho();
        // Blocos de cálculo (MU amarelo) e fórmulas vivem no estado — não filtrar como fantasma.
        (estado.blocos_calc || []).forEach(function (cod) {
            var c = String(cod || '').toLowerCase();
            if (c) vivos[c] = true;
        });
        Object.keys(estado.formulas_blocos || {}).forEach(function (cod) {
            var c = String(cod || '').toLowerCase();
            if (c) vivos[c] = true;
        });
        var soVivos = Object.keys(vivos).length > 0;
        var manualDoZero = quadroManualDoZero();
        // Do zero: preview não pode trazer chips que o admin não adicionou.
        if (manualDoZero) {
            var permitidos = {};
            (estado.colunas_ordem || []).forEach(function (cod) {
                var low = String(cod || '').toLowerCase();
                if (low) permitidos[low] = true;
            });
            (estado.blocos_calc || []).forEach(function (cod) {
                var low = String(cod || '').toLowerCase();
                if (low) permitidos[low] = true;
            });
            Object.keys(estado.formulas_blocos || {}).forEach(function (cod) {
                var low = String(cod || '').toLowerCase();
                if (low) permitidos[low] = true;
            });
            (estado.linhas_layout || []).forEach(function (lin) {
                (lin && lin.colunas || []).forEach(function (cod) {
                    var low = String(cod || '').toLowerCase();
                    if (low) permitidos[low] = true;
                });
            });
            cols = cols.filter(function (c) {
                if (!c || !c.codigo) return false;
                return !!permitidos[String(c.codigo).toLowerCase()];
            });
        }
        cols.forEach(function (c) {
            if (c && c.codigo) vistos[String(c.codigo)] = true;
        });
        function add(codigo, nome, tipo) {
            codigo = String(codigo || '');
            if (!codigo || vistos[codigo] || colunaOculta(codigo)) return;
            var low = String(codigo).toLowerCase();
            var ehCalc = (estado.blocos_calc || []).indexOf(codigo) >= 0
                || (estado.blocos_calc || []).indexOf(low) >= 0
                || !!(estado.formulas_blocos && (estado.formulas_blocos[codigo] || estado.formulas_blocos[low]));
            // No Montar quadro não reinsere EVA/SEV fantasma da ordem antiga.
            if (soVivos && !vivos[low] && !ehCalc) return;
            vistos[codigo] = true;
            cols.push({
                codigo: codigo,
                nome: nome || tituloColuna(codigo) || codigo,
                tipo: tipo || (ehCalc ? 'calculado' : 'peca'),
                travada: false
            });
        }
        (estado.colunas_ordem || []).forEach(function (cod) { add(cod, tituloColuna(cod)); });
        (estado.blocos_calc || []).forEach(function (cod) { add(cod, nomeBlocoCalc(cod), 'calculado'); });
        // Do zero: não espalha nomes_blocos (siglas de todos os eventos) como chips.
        if (!manualDoZero) {
            Object.keys(estado.nomes_blocos || {}).forEach(function (cod) { add(cod, estado.nomes_blocos[cod]); });
        }
        Object.keys(estado.formulas_blocos || {}).forEach(function (cod) { add(cod, nomeBlocoCalc(cod), 'calculado'); });
        (((rascunhoAtual || {}).componentes) || []).forEach(function (c) {
            if (!c || !c.codigo || c.codigo === '_semanal') return;
            if (manualDoZero) {
                var lowC = String(c.codigo).toLowerCase();
                var naOrdem = (estado.colunas_ordem || []).some(function (x) {
                    return String(x || '').toLowerCase() === lowC;
                });
                var ehCalcC = (estado.blocos_calc || []).indexOf(c.codigo) >= 0
                    || (estado.blocos_calc || []).indexOf(lowC) >= 0
                    || !!(estado.formulas_blocos && (estado.formulas_blocos[c.codigo] || estado.formulas_blocos[lowC]));
                if (!naOrdem && !ehCalcC) return;
            }
            add(c.codigo, c.nome || c.codigo, c.source_type === 'calculado' ? 'calculado' : 'peca');
        });
        return cols;
    }

    function eventosCatalogoBuilder() {
        var todos = eventosAvaliativosDoAnoQuadro();
        var marcados = eventosAvaliativosMarcados();
        if (!marcados.length) return todos;
        var set = {};
        marcados.forEach(function (id) { set[Number(id)] = true; });
        var filtrados = todos.filter(function (ev) { return !!set[Number(ev && ev.id || 0)]; });
        return filtrados.length ? filtrados : todos;
    }

    function codigoPreferidoEvento(ev) {
        if (!ev) return '';
        var sigla = String(ev.sigla || '').toLowerCase().replace(/[^a-z0-9_]+/g, '');
        var bim = Number(ev.bimestre || 0);
        var id = Number(ev.id || 0);
        if (bim >= 1 && bim <= 4 && ['pp', 'pu', 'ch', 'pe', 'fal', 'faltas'].indexOf(sigla) >= 0) {
            return 'b' + bim + '_' + (sigla === 'faltas' ? 'fal' : sigla);
        }
        if (bim === 81 || bim === 61 || bim === 62) return 's1_rec';
        if (bim === 82 || bim === 63 || bim === 64) return 's2_rec';
        // Recuperação final / Prova Final (ano).
        if (bim === 90 || sigla === 'pf' || sigla === 'prova_final' || sigla === 'provafinal') return 'pf';
        if (sigla) return sigla;
        if (bim >= 60) return 'rec';
        return id > 0 ? ('eva' + id) : '';
    }

    function codigoColunaDoEventoJs(ev, vistos) {
        vistos = vistos || {};
        var pref = codigoPreferidoEvento(ev);
        if (pref && !vistos[pref]) return pref;
        var bim = Number((ev && ev.bimestre) || 0);
        if (bim >= 60) {
            if (!vistos.rec) return 'rec';
            if (!vistos.recuperacao) return 'recuperacao';
        }
        var id = Number((ev && ev.id) || 0);
        var base = 'eva' + id;
        if (id > 0 && !vistos[base]) return base;
        return id > 0 ? ('eva_' + id) : '';
    }

    function colunaEventoJaNoQuadro(ev) {
        if (!ev || !estado) return false;
        var pref = String(codigoPreferidoEvento(ev) || '').toLowerCase();
        var id = Number(ev.id || 0);
        var ordem = {};
        function marcar(cod) {
            var low = String(cod || '').toLowerCase();
            if (low) ordem[low] = true;
        }
        (estado.colunas_ordem || []).forEach(marcar);
        (((rascunhoAtual || {}).componentes) || []).forEach(function (c) { marcar(c && c.codigo); });
        (estado.linhas_layout || []).forEach(function (lin) {
            (lin && lin.colunas || []).forEach(marcar);
        });
        if (pref && ordem[pref]) return true;
        if (id > 0 && (ordem['eva' + id] || ordem['eva_' + id])) return true;
        return false;
    }

    /** Resolve o código da coluna do evento (mesmo se já estiver no quadro). */
    function codigoColunaExistenteDoEvento(ev) {
        if (!ev || !estado) return '';
        var pref = String(codigoPreferidoEvento(ev) || '').toLowerCase();
        var id = Number(ev.id || 0);
        var ordem = {};
        function marcar(cod) {
            var low = String(cod || '').toLowerCase();
            if (low) ordem[low] = true;
        }
        (estado.colunas_ordem || []).forEach(marcar);
        (((rascunhoAtual || {}).componentes) || []).forEach(function (c) { marcar(c && c.codigo); });
        (estado.linhas_layout || []).forEach(function (lin) {
            (lin && lin.colunas || []).forEach(marcar);
        });
        if (pref && ordem[pref]) return pref;
        if (id > 0 && ordem['eva' + id]) return 'eva' + id;
        if (id > 0 && ordem['eva_' + id]) return 'eva_' + id;
        return pref || (id > 0 ? ('eva' + id) : '');
    }

    function adicionarColunaDoEvento(ev, opcoes) {
        if (!estado || !ev) return false;
        opcoes = opcoes || {};
        var silencioso = !!opcoes.silencioso;
        var id = Number(ev.id || 0);
        if (id <= 0) return false;
        if (!Array.isArray(estado.eventos_avaliativos_ids)) estado.eventos_avaliativos_ids = [];
        var idsNum = estado.eventos_avaliativos_ids.map(Number);
        if (idsNum.indexOf(id) < 0) estado.eventos_avaliativos_ids.push(id);
        if (colunaEventoJaNoQuadro(ev)) {
            if (opcoes.linhaId || opcoes.nomeCab) {
                var codJa = codigoColunaExistenteDoEvento(ev);
                if (codJa) {
                    moverCodigoParaCabecalho(codJa, opcoes.nomeCab || '', opcoes.linhaId || '');
                    if (!silencioso) {
                        avisoLinha = { tipo: 'ok', texto: 'Coluna “' + (siglaDoEvento(ev) || codJa) + '” movida para o título do período.' };
                        renderRevisarDinamico();
                        agendarMontar();
                    }
                    return true;
                }
            }
            if (!silencioso) {
                avisoLinha = { tipo: 'ok', texto: 'Essa coluna já está no quadro (só a sigla). Arraste para um título do período se quiser.' };
                renderRevisarDinamico();
            }
            return false;
        }
        var vistos = {};
        (estado.colunas_ordem || []).forEach(function (c) {
            var low = String(c || '').toLowerCase();
            if (low) vistos[low] = true;
        });
        (((rascunhoAtual || {}).componentes) || []).forEach(function (c) {
            var low = String((c && c.codigo) || '').toLowerCase();
            if (low) vistos[low] = true;
        });
        (estado.linhas_layout || []).forEach(function (lin) {
            (lin && lin.colunas || []).forEach(function (c) {
                var low = String(c || '').toLowerCase();
                if (low) vistos[low] = true;
            });
        });
        var codigo = codigoColunaDoEventoJs(ev, vistos);
        if (!codigo) return false;
        mostrarColuna(codigo);
        estado.quadro_chips_manuais = 1;
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        if (estado.colunas_ordem.map(function (c) { return String(c || '').toLowerCase(); }).indexOf(codigo) < 0) {
            estado.colunas_ordem.push(codigo);
        }
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        var sigla = String(siglaDoEvento(ev) || '').toUpperCase();
        var titulo = sigla || String(ev.nome || codigo);
        if (!estado.nomes_blocos[codigo]) estado.nomes_blocos[codigo] = titulo;
        // Garante chip imediato no quadro mesmo com rascunho já carregado (evita filtro de fantasma).
        garantirComponenteManualNoRascunho(codigo, titulo);
        if (opcoes.linhaId || opcoes.nomeCab) {
            moverCodigoParaCabecalho(codigo, opcoes.nomeCab || '', opcoes.linhaId || '');
        }
        if (!silencioso) {
            aplicarSiglasEventosAvulsosNosTitulos();
            marcarEdicaoManual();
            avisoLinha = {
                tipo: 'ok',
                texto: (opcoes.linhaId || opcoes.nomeCab)
                    ? ('Coluna “' + (sigla || codigo) + '” colocada no título do período.')
                    : ('Coluna “' + (sigla || codigo) + '” adicionada. Arraste para um título do período se quiser.')
            };
            previewAtual = null;
            renderRevisarDinamico();
            agendarMontar();
        }
        return true;
    }

    function bimestreDaChavePeriodo(chave) {
        var m = String(chave || '').match(/^p-(\d+)$/);
        if (!m) return 0;
        var bim = Number(m[1] || 0);
        return (bim >= 1 && bim <= 4) ? bim : 0;
    }

    function colunaFalJaNoQuadro(bim) {
        bim = Number(bim || 0);
        if (bim < 1 || bim > 4 || !estado) return false;
        var cod = 'b' + bim + '_fal';
        var codAlt = 'b' + bim + '_faltas';
        var ordem = {};
        function marcar(c) {
            var low = String(c || '').toLowerCase();
            if (low) ordem[low] = true;
        }
        (estado.colunas_ordem || []).forEach(marcar);
        (((rascunhoAtual || {}).componentes) || []).forEach(function (c) { marcar(c && c.codigo); });
        (estado.linhas_layout || []).forEach(function (lin) {
            (lin && lin.colunas || []).forEach(marcar);
        });
        if (ordem[cod] || ordem[codAlt]) return true;
        return Object.keys(ordem).some(function (c) {
            if (!c.match(new RegExp('^b' + bim + '_'))) return false;
            if (/(^|_)fal(tas)?$/.test(c)) return true;
            return String(siglaTituloColunaJs(c, (estado.nomes_blocos && estado.nomes_blocos[c]) || '') || '').toUpperCase() === 'FAL';
        });
    }

    function resolverFonteFaltasBim(bim) {
        bim = Number(bim || 0);
        if (bim < 1 || bim > 4 || !estado) return 0;
        if (!estado.fontes_faltas || typeof estado.fontes_faltas !== 'object') {
            estado.fontes_faltas = { 1: 0, 2: 0, 3: 0, 4: 0 };
        }
        var atual = Number(estado.fontes_faltas[bim] || 0);
        if (atual > 0) return atual;
        var ano = Number((estado && estado.ano_letivo) || 0);
        var achou = 0;
        (eventosFaltasDoAno() || []).some(function (fe) {
            if (!fe) return false;
            if (ano > 0 && Number(fe.ano_letivo || 0) > 0 && Number(fe.ano_letivo || 0) !== ano) return false;
            if (Number(fe.bimestre || 0) !== bim) return false;
            var fid = Number(fe.id || 0);
            if (fid <= 0) return false;
            achou = fid;
            estado.fontes_faltas[bim] = fid;
            return true;
        });
        return achou;
    }

    /** Chip FAL do bimestre (não vem de Evento Avaliativo — precisa de botão próprio no catálogo). */
    function adicionarColunaFal(bim, opcoes) {
        if (!estado) return false;
        bim = Number(bim || 0);
        opcoes = opcoes || {};
        var silencioso = !!opcoes.silencioso;
        if (bim < 1 || bim > 4) return false;
        var codigo = 'b' + bim + '_fal';
        if (colunaFalJaNoQuadro(bim)) {
            if (opcoes.linhaId || opcoes.nomeCab) {
                moverCodigoParaCabecalho(codigo, opcoes.nomeCab || '', opcoes.linhaId || '');
                if (!silencioso) {
                    avisoLinha = { tipo: 'ok', texto: 'Coluna FAL movida para o título do período.' };
                    renderRevisarDinamico();
                    agendarMontar();
                }
                return true;
            }
            if (!silencioso) {
                avisoLinha = { tipo: 'ok', texto: 'A coluna FAL deste bimestre já está no quadro.' };
                renderRevisarDinamico();
            }
            return false;
        }
        var idFal = resolverFonteFaltasBim(bim);
        // Se a FAL já tinha sido excluída (X / limpar todos), precisa sair de colunas_ocultas.
        mostrarColuna(codigo);
        mostrarColuna('b' + bim + '_faltas');
        estado.quadro_chips_manuais = 1;
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        if (estado.colunas_ordem.map(function (c) { return String(c || '').toLowerCase(); }).indexOf(codigo) < 0) {
            var pe = 'b' + bim + '_pe';
            var idxPe = estado.colunas_ordem.map(function (c) { return String(c || '').toLowerCase(); }).indexOf(pe);
            if (idxPe >= 0) estado.colunas_ordem.splice(idxPe + 1, 0, codigo);
            else estado.colunas_ordem.push(codigo);
        }
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        estado.nomes_blocos[codigo] = 'FAL';
        garantirComponenteManualNoRascunho(codigo, 'FAL');
        [rascunhoAtual, estado.rascunho_preservado].forEach(function (r) {
            if (!r || !Array.isArray(r.componentes)) return;
            r.componentes.forEach(function (c) {
                if (!c || String(c.codigo || '').toLowerCase() !== codigo) return;
                c.nome = 'FAL';
                c.source_type = idFal > 0 ? 'faltas_evento' : 'nenhuma';
                c.layout_type = 'faltas';
                if (!c.config || typeof c.config !== 'object') c.config = {};
                c.config.layout_type = 'faltas';
                if (idFal > 0) c.config.evento_id = idFal;
            });
        });
        if (opcoes.linhaId || opcoes.nomeCab) {
            moverCodigoParaCabecalho(codigo, opcoes.nomeCab || '', opcoes.linhaId || '');
        }
        if (!silencioso) {
            aplicarSiglasEventosAvulsosNosTitulos();
            marcarEdicaoManual();
            avisoLinha = {
                tipo: 'ok',
                texto: (opcoes.linhaId || opcoes.nomeCab)
                    ? ('Coluna FAL do ' + bim + 'º bimestre colocada no título do período.')
                    : (idFal > 0
                        ? ('Coluna FAL do ' + bim + 'º bimestre adicionada.')
                        : ('Coluna FAL do ' + bim + 'º bimestre adicionada (sem evento de faltas vinculado — escolha a fonte em Eventos, se houver).'))
            };
            previewAtual = null;
            renderRevisarDinamico();
            agendarMontar();
        }
        return true;
    }

    function soltarCatalogoNoTitulo(payload, linhaId, nomeCab) {
        payload = String(payload || '');
        linhaId = String(linhaId || '');
        nomeCab = String(nomeCab || '').trim();
        if (!payload || (!linhaId && !nomeCab)) return false;
        var mEv = payload.match(/^ev:(\d+)$/);
        if (mEv) {
            var evId = Number(mEv[1] || 0);
            var ev = ((catalogo && catalogo.eventos_avaliativos) || []).filter(function (x) {
                return Number(x && x.id || 0) === evId;
            })[0];
            if (!ev) return false;
            return adicionarColunaDoEvento(ev, { linhaId: linhaId, nomeCab: nomeCab });
        }
        var mFal = payload.match(/^fal:(\d+)$/);
        if (mFal) {
            return adicionarColunaFal(Number(mFal[1] || 0), { linhaId: linhaId, nomeCab: nomeCab });
        }
        return false;
    }

    function destinoTituloPeriodoAtivo() {
        var id = String((tituloPeriodoAtivo && tituloPeriodoAtivo.id) || '').trim();
        var nome = String((tituloPeriodoAtivo && tituloPeriodoAtivo.nome) || '').trim();
        if (!id && !nome) return null;
        if (Array.isArray(estado && estado.linhas_layout)) {
            var achou = null;
            estado.linhas_layout.forEach(function (lin) {
                if (!lin || achou) return;
                var lid = String(lin.id || '');
                var lnome = String(lin.nome || '').trim();
                if ((id && (lid === id || ('cab:' + lnome.toLowerCase()) === id || lnome === id))
                    || (nome && lnome === nome)) {
                    achou = { linhaId: lid || id, nomeCab: lnome || nome };
                }
            });
            if (achou) return achou;
        }
        return id || nome ? { linhaId: id, nomeCab: nome } : null;
    }

    function codigoEstaNoTitulo(codigo, linhaId, nomeCab) {
        codigo = String(codigo || '').toLowerCase();
        if (!codigo || !estado || !Array.isArray(estado.linhas_layout)) return false;
        linhaId = String(linhaId || '').trim();
        nomeCab = String(nomeCab || '').trim();
        var achou = false;
        estado.linhas_layout.forEach(function (lin) {
            if (!lin || achou) return;
            var lid = String(lin.id || '');
            var lnome = String(lin.nome || '').trim();
            var bate = (linhaId && (lid === linhaId || ('cab:' + lnome.toLowerCase()) === linhaId || lnome === linhaId))
                || (nomeCab && lnome === nomeCab);
            if (!bate) return;
            achou = (lin.colunas || []).some(function (c) {
                return String(c || '').toLowerCase() === codigo;
            });
        });
        return achou;
    }

    function selecionarTituloPeriodo(linhaId, nomeCab) {
        linhaId = String(linhaId || '').trim();
        nomeCab = String(nomeCab || '').trim();
        tituloPeriodoAtivo = { id: linhaId, nome: nomeCab };
        document.querySelectorAll('#bw-colunas-lista .bw-linha-caixa').forEach(function (cx) {
            var cid = String(cx.getAttribute('data-linha-id') || '');
            var cnome = String(cx.getAttribute('data-linha-nome') || '').trim();
            var ativo = !!(linhaId && (cid === linhaId || cnome === linhaId))
                || !!(nomeCab && cnome === nomeCab);
            cx.classList.toggle('selecionado', ativo);
        });
    }

    function adicionarTodasColunasDoPeriodo(chavePeriodo) {
        if (!estado) return;
        chavePeriodo = String(chavePeriodo || '');
        var eventos = eventosCatalogoBuilder().filter(function (ev) {
            return chavePeriodoItem(ev) === chavePeriodo;
        });
        var bimFal = bimestreDaChavePeriodo(chavePeriodo);
        if (!eventos.length && bimFal < 1) return;
        var destino = destinoTituloPeriodoAtivo();
        if (!destino) {
            avisoLinha = {
                tipo: 'erro',
                texto: 'Selecione um título do período no quadro e depois clique em Adicionar todos.'
            };
            renderRevisarDinamico();
            return;
        }
        var opcoes = { silencioso: true, linhaId: destino.linhaId, nomeCab: destino.nomeCab };
        var adicionados = 0;
        eventos.forEach(function (ev) {
            if (adicionarColunaDoEvento(ev, opcoes)) adicionados += 1;
        });
        if (bimFal >= 1 && adicionarColunaFal(bimFal, opcoes)) {
            adicionados += 1;
        }
        aplicarSiglasEventosAvulsosNosTitulos();
        marcarEdicaoManual();
        previewAtual = null;
        var rotulo = '';
        agruparItensPorPeriodo(eventos).forEach(function (g) {
            if (String(g.chave) === chavePeriodo) rotulo = g.rotulo || '';
        });
        if (!rotulo && bimFal >= 1) rotulo = bimFal + 'º bimestre';
        if (!rotulo) rotulo = 'período';
        var nomeDest = destino.nomeCab || 'título do período';
        if (adicionados > 0) {
            avisoLinha = {
                tipo: 'ok',
                texto: adicionados === 1
                    ? ('1 coluna do ' + rotulo + ' vinculada em “' + nomeDest + '”.')
                    : (adicionados + ' colunas do ' + rotulo + ' vinculadas em “' + nomeDest + '”.')
            };
        } else {
            avisoLinha = {
                tipo: 'ok',
                texto: 'Todas as colunas do ' + rotulo + ' já estão em “' + nomeDest + '”.'
            };
        }
        renderRevisarDinamico();
        if (adicionados > 0) agendarMontar();
    }

    function htmlCatalogoEventosBuilder() {
        var eventos = eventosCatalogoBuilder();
        var html = '<aside class="bw-catalogo" aria-label="Catálogo de eventos">';
        html += '<div class="bw-catalogo-topo">';
        html += '<p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide">Eventos por bimestre</p>';
        html += '<p class="text-[11px] text-slate-500 mt-0.5">Clique em <strong>Abrir</strong> no bimestre. Depois selecione um título no quadro e use <strong>Adicionar todos</strong>.</p>';
        html += '</div><div class="bw-catalogo-corpo">';
        if (!eventos.length) {
            html += '<p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-2">Nenhum evento neste ano. Marque no passo anterior ou cadastre Eventos Avaliativos.</p>';
            html += '</div></aside>';
            return html;
        }
        agruparItensPorPeriodo(eventos).forEach(function (grupo, idx) {
            var bimFal = bimestreDaChavePeriodo(grupo.chave);
            var destinoAtivo = destinoTituloPeriodoAtivo();
            var faltam = (grupo.itens || []).filter(function (ev) {
                if (Number(ev && ev.id || 0) <= 0) return false;
                if (!colunaEventoJaNoQuadro(ev)) return true;
                if (!destinoAtivo) return false;
                var codEv = codigoColunaExistenteDoEvento(ev);
                return !codigoEstaNoTitulo(codEv, destinoAtivo.linhaId, destinoAtivo.nomeCab);
            }).length;
            if (bimFal >= 1) {
                if (!colunaFalJaNoQuadro(bimFal)) faltam += 1;
                else if (destinoAtivo && !codigoEstaNoTitulo('b' + bimFal + '_fal', destinoAtivo.linhaId, destinoAtivo.nomeCab)) {
                    faltam += 1;
                }
            }
            var totalGrupo = (grupo.itens || []).filter(function (ev) {
                return Number(ev && ev.id || 0) > 0;
            }).length + (bimFal >= 1 ? 1 : 0);
            html += '<details class="bw-catalogo-grupo">';
            html += '<summary title="Clique para abrir ou fechar os eventos">'
                + '<span>' + esc(grupo.rotulo || 'Período') + '</span>'
                + '<span class="bw-cat-abrir"><span class="bw-cat-abrir-txt"></span></span>'
                + '</summary>';
            html += '<div class="bw-catalogo-itens">';
            if (totalGrupo) {
                html += '<button type="button" class="bw-catalogo-add-todos" data-catalogo-periodo="' + esc(String(grupo.chave || '')) + '"'
                    + ' title="Selecione um título do período e vincule todas as siglas nele (inclui FAL)"'
                    + (faltam ? '' : ' disabled') + '>'
                    + (faltam
                        ? ('Adicionar todos (' + faltam + ')')
                        : (destinoAtivo ? 'Todos já neste título' : 'Todos já no quadro')) + '</button>';
            }
            (grupo.itens || []).forEach(function (ev) {
                var id = Number(ev.id || 0);
                if (id <= 0) return;
                var nome = String(ev.nome || ('Evento #' + id));
                var sigla = String(siglaDoEvento(ev) || '').toUpperCase() || ('#' + id);
                var noQuadro = colunaEventoJaNoQuadro(ev);
                html += '<button type="button" draggable="true" class="bw-catalogo-item' + (noQuadro ? ' no-quadro' : '') + '" data-catalogo-evento="' + id + '" title="' + esc(nome + ' → arraste para o título do período') + '">';
                html += '<span class="bw-cat-nome">' + esc(nome) + '</span>';
                html += '<span class="bw-cat-sigla">' + esc(sigla) + '</span>';
                html += '</button>';
            });
            if (bimFal >= 1) {
                var falNoQuadro = colunaFalJaNoQuadro(bimFal);
                html += '<button type="button" draggable="true" class="bw-catalogo-item' + (falNoQuadro ? ' no-quadro' : '') + '" data-catalogo-fal="' + bimFal + '" title="Faltas → arraste para o título do período">';
                html += '<span class="bw-cat-nome">Faltas</span>';
                html += '<span class="bw-cat-sigla">FAL</span>';
                html += '</button>';
            }
            html += '</div></details>';
        });
        html += '</div></aside>';
        return html;
    }

    function htmlColunasOrdem(pv) {
        zerarQuadroColunasSeAutomatico();
        removerMuAutomaticaDoEstado();
        var cols = ordenarColunasLista(mesclarColunasExtrasDoEstado((pv && pv.colunas) || []));

        var html = '<div class="bw-builder">';
        html += htmlCatalogoEventosBuilder();
        html += '<section class="bw-quadro" aria-label="Quadro de montagem">';
        html += '<div class="bw-quadro-topo">';
        html += '<div>';
        html += '<p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide">Quadro de colunas</p>';
        html += '</div>';
        html += '<div class="bw-quadro-acoes">';
        if (!ehDemonstrativoNotas()) {
            html += '<button type="button" id="bw-add-media" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-amber-300 text-amber-950 bg-amber-50 hover:bg-amber-100">Bloco de cálculo</button>';
        }
        html += '<button type="button" id="bw-limpar-cabecalhos" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-rose-300 text-rose-800 bg-rose-50 hover:bg-rose-100">Limpar títulos</button>';
        html += '<button type="button" id="bw-excluir-todos-chips" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-rose-400 text-rose-900 bg-white hover:bg-rose-50" title="Remove todos os chips do quadro">Excluir todos os chips</button>';
        html += '</div></div>';
        html += '<div class="bw-quadro-corpo">';
        if (avisoLinha && avisoLinha.texto) {
            var clsDica = avisoLinha.tipo === 'ok' ? 'text-emerald-700' : 'text-amber-800';
            html += '<p id="bw-linha-aviso" class="text-xs font-medium mb-2 ' + clsDica + '">' + esc(avisoLinha.texto) + '</p>';
        }
        html += '<div class="flex flex-wrap items-center gap-2 mb-3">';
        html += '<input type="text" id="bw-linha-nome" maxlength="40" value="' + esc(nomeLinhaRascunho) + '" placeholder="Ex.: I BIMESTRE" class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg w-56 bg-white">';
        html += '<button type="button" id="bw-criar-linha" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Novo título do período</button>';
        html += '<button type="button" id="bw-tirar-linha" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Tirar título</button>';
        html += '</div>';
        html += '<div id="bw-colunas-lista" class="flex flex-wrap items-start gap-4 min-h-[8rem]">';
        var visiveis = [];
        cols.forEach(function (c) {
            if (!colunaSegueBlocoSemanal(c)) visiveis.push(c);
        });
        var faixas = faixasCabecalhoPreview(visiveis);
        if (!faixas.length) {
            html += '<div class="bw-quadro-vazio w-full">Digite o título (ex.: IV BIMESTRE) e clique em Novo título do período — depois arraste os eventos da esquerda</div>';
        } else {
            faixas.forEach(function (faixa) {
                if (faixa.tipo === 'grupo') {
                    var linRef = (faixa.cols[0] && linhaLayoutDaColuna(faixa.cols[0].codigo, faixa.cols[0])) || {};
                    var linId = String(faixa.id || linRef.id || ('cab:' + String(faixa.nome || '').toLowerCase()));
                    var confirmado = !!(estado && estado.titulos_periodo_ok && estado.titulos_periodo_ok[linId]);
                    var selecionado = !!(tituloPeriodoAtivo && (
                        String(tituloPeriodoAtivo.id || '') === linId
                        || String(tituloPeriodoAtivo.nome || '').trim() === String(faixa.nome || '').trim()
                    ));
                    html += '<div class="bw-linha-caixa' + (confirmado ? ' confirmado' : '') + (selecionado ? ' selecionado' : '') + '" data-linha-id="' + esc(linId) + '" data-linha-nome="' + esc(faixa.nome) + '" title="Clique para selecionar este título">';
                    html += '<div class="bw-linha-topo">';
                    html += '<span class="bw-linha-grip" title="Arrastar bloco" aria-hidden="true">⋮⋮</span>';
                    html += '<span class="bw-linha-rotulo" contenteditable="true" spellcheck="false" title="Clique para editar o título do período" data-linha-id="' + esc(linId) + '">' + esc(faixa.nome) + '</span>';
                    if (confirmado) {
                        html += '<button type="button" class="bw-linha-ok ativo" data-linha-id="' + esc(linId) + '" title="Título do período concluído" aria-label="Concluído">Concluído ✓</button>';
                    } else {
                        html += '<button type="button" class="bw-linha-ok" data-linha-id="' + esc(linId) + '" title="Confirmar este título do período" aria-label="Confirmar título do período">Confirmar</button>';
                    }
                    html += '<button type="button" class="bw-linha-clonar" data-linha-id="' + esc(linId) + '" title="Clonar este título para outro bimestre" aria-label="Clonar título do período">Clonar</button>';
                    html += '<button type="button" class="bw-linha-x" data-linha-id="' + esc(linId) + '" title="Remover este título do período" aria-label="Remover título do período">×</button>';
                    html += '</div><span class="bw-linha-cols">';
                    (faixa.cols || []).forEach(function (c) { html += htmlChipColuna(c); });
                    html += '</span>';
                    if (String(clonePainelLinhaId || '') === linId) {
                        html += htmlPainelClonarTitulo(linId, faixa.nome);
                    }
                    html += '<p class="bw-linha-dica">Vincule os eventos do menu da lateral.</p>';
                    html += '</div>';
                    return;
                }
                html += htmlChipColuna(faixa.col);
            });
        }
        html += '</div></div></section></div>';
        return html;
    }

    function htmlChipColuna(c) {
        if (!c) return '';
        var fixa = c.codigo === '_semanal' || c.codigo === 'media_sem';
        var ehCalc = c.tipo === 'calculado'
            || String(c.source_type || '') === 'calculado'
            || ehCodigoBlocoCalc(c.codigo);
        var cls = 'bw-col-chip' + (c.travada ? ' travada' : '') + (fixa ? ' fixa' : '') + (ehCalc ? ' calc' : '');
        if (colunasMarcadas[c.codigo]) cls += ' marcada';
        if (estado && ((estado.titulo_codigo && c.codigo === estado.titulo_codigo) || (estado.bloco_calc && c.codigo === estado.bloco_calc))) cls += ' aberta';
        var rotulo = tituloColuna(c.codigo) || siglaColunaEventoAvaliativo(c.codigo) || siglaTituloColunaJs(c.codigo, c.nome) || c.nome;
        rotulo = rotuloSemestreColuna(c.codigo, rotulo);
        var dica = '';
        var codLow = String(c.codigo || '').toLowerCase();
        if (codLow === 's1_rec' || codLow === 's1_ms') dica = '1º semestre';
        if (codLow === 's2_rec' || codLow === 's2_ms') dica = '2º semestre';
        if (ehCalc) dica = 'Clique para editar a fórmula · arraste pelo ⋮⋮';
        var html = '<span class="' + cls + '" data-codigo="' + esc(c.codigo) + '" data-tipo="' + esc(ehCalc ? 'calculado' : (c.tipo || '')) + '"'
            + (dica ? ' title="' + esc(dica) + '"' : '') + '>';
        if (!c.travada && !fixa) html += '<span class="bw-col-grip" title="Arrastar" aria-hidden="true">⋮⋮</span>';
        html += '<span class="bw-col-nome">' + esc(rotulo) + '</span>';
        html += '<button type="button" class="bw-col-x" data-codigo="' + esc(c.codigo) + '" aria-label="Remover">×</button>';
        html += '</span>';
        return html;
    }

    function abrirEdicaoBlocoCalc(codigo) {
        if (ehDemonstrativoNotas()) return false;
        codigo = String(codigo || '');
        if (!codigo || codigo === 'media_sem' || codigo === '_semanal') return false;
        if (!ehCodigoBlocoCalc(codigo) && !(estado && (estado.formulas_blocos || {})[codigo])) {
            // Ainda assim abre se o chip veio como calculado no preview.
        }
        estado.titulo_codigo = codigo;
        if (!Array.isArray(estado.blocos_calc)) estado.blocos_calc = [];
        if (estado.blocos_calc.indexOf(codigo) < 0) estado.blocos_calc.push(codigo);
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!estado.nomes_blocos[codigo]) {
            estado.nomes_blocos[codigo] = tituloColuna(codigo) || (codigo === 'media_final' ? 'Resultado Final' : nomeBlocoCalc(codigo));
        }
        if (estado.passo !== 'formula' && estado.passo !== 'revisar') {
            estado.passo = 'formula';
            estado.bloco_calc = codigo;
            renderAll();
            agendarMontar();
            return true;
        }
        renderRevisarDinamico();
        abrirFormulaBloco(codigo);
        var ed = document.getElementById('bw-formula-editor');
        if (ed && ed.scrollIntoView) ed.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return true;
    }

    function configDoComp(c) {
        if (!c) return {};
        var cfg = c.config;
        if (typeof cfg === 'string' && cfg) {
            try { cfg = JSON.parse(cfg); } catch (e) { cfg = {}; }
        }
        if (!cfg || typeof cfg !== 'object' || Array.isArray(cfg)) cfg = {};
        c.config = cfg;
        return cfg;
    }

    function idLinhaPorNome(nome) {
        var h = 0;
        var s = String(nome || '');
        for (var i = 0; i < s.length; i++) h = ((h << 5) - h + s.charCodeAt(i)) | 0;
        return ('lin' + Math.abs(h).toString(36)).slice(0, 23);
    }

    function gravarLinhaNosComponentes(codigosAlvo) {
        if (!estado) return;
        var alvo = {};
        (codigosAlvo || []).forEach(function (cod) {
            var c = String(cod || '').toLowerCase();
            if (c) alvo[c] = true;
        });
        if (!Object.keys(alvo).length) return;
        var mapa = {};
        (estado.linhas_layout || []).forEach(function (lin) {
            if (!lin) return;
            var nome = String(lin.nome || '').trim().slice(0, 40);
            if (!nome) return;
            (lin.colunas || []).forEach(function (cod) {
                var c = String(cod || '').toLowerCase();
                if (alvo[c]) mapa[c] = nome;
            });
        });
        [rascunhoAtual, estado.rascunho_preservado].forEach(function (r) {
            if (!r || !Array.isArray(r.componentes)) return;
            r.componentes.forEach(function (c) {
                if (!c) return;
                var cod = String(c.codigo || '').toLowerCase();
                if (!alvo[cod]) return;
                var cfg = configDoComp(c);
                if (mapa[cod]) cfg.linha_cabecalho = mapa[cod];
                else delete cfg.linha_cabecalho;
            });
        });
    }

    function realinharLinhasPelosComponentes() {
        if (!estado) return;
        // Admin limpou/editou: não recria cabeçalhos a partir do Demonstrativo.
        if (Number(estado.cabecalhos_manuais || 0) === 1) {
            if (typeof ehVisaoImpressaoEditavel === 'function' && ehVisaoImpressaoEditavel()
                && Array.isArray(estado.linhas_layout) && estado.linhas_layout.length) {
                var ordemManual = (estado.colunas_ordem || []).map(function (c) { return String(c || '').toLowerCase(); });
                estado.linhas_layout = estado.linhas_layout.map(function (lin) {
                    if (!lin) return lin;
                    var cols = (lin.colunas || []).map(function (c) { return String(c || '').toLowerCase(); });
                    if (ordemManual.length) {
                        cols.sort(function (a, b) {
                            var ia = ordemManual.indexOf(a);
                            var ib = ordemManual.indexOf(b);
                            if (ia < 0) ia = 9999;
                            if (ib < 0) ib = 9999;
                            return ia - ib;
                        });
                    }
                    lin.colunas = cols;
                    return lin;
                }).filter(function (lin) { return lin && String(lin.nome || '').trim(); });
            }
            return;
        }
        // No print/boletim editável a ordem vem do arraste (colunas_ordem + linhas_layout).
        if (typeof ehVisaoImpressaoEditavel === 'function' && ehVisaoImpressaoEditavel()
            && Array.isArray(estado.linhas_layout) && estado.linhas_layout.length) {
            var ordemLocal = (estado.colunas_ordem || []).map(function (c) { return String(c || '').toLowerCase(); });
            estado.linhas_layout = estado.linhas_layout.map(function (lin) {
                if (!lin) return lin;
                var cols = (lin.colunas || []).map(function (c) { return String(c || '').toLowerCase(); });
                if (ordemLocal.length) {
                    cols.sort(function (a, b) {
                        var ia = ordemLocal.indexOf(a);
                        var ib = ordemLocal.indexOf(b);
                        if (ia < 0) ia = 9999;
                        if (ib < 0) ib = 9999;
                        return ia - ib;
                    });
                }
                lin.colunas = cols;
                return lin;
            }).filter(function (lin) { return lin && String(lin.nome || '').trim(); });
            return;
        }
        var comps = (rascunhoAtual && rascunhoAtual.componentes) || [];
        var porNome = {};
        var ordem = [];
        comps.forEach(function (c) {
            if (!c) return;
            var nome = String(configDoComp(c).linha_cabecalho || '').trim();
            var cod = String(c.codigo || '').toLowerCase();
            if (!nome || !cod) return;
            if (!porNome[nome]) {
                porNome[nome] = [];
                ordem.push(nome);
            }
            if (porNome[nome].indexOf(cod) < 0) porNome[nome].push(cod);
        });
        if (!ordem.length) return;
        var vistas = {};
        var linhas = (estado.linhas_layout || []).map(function (lin) {
            if (!lin || !lin.nome || !porNome[lin.nome]) return lin;
            vistas[lin.nome] = true;
            if (porNome[lin.nome].length < 2) return null;
            lin.colunas = porNome[lin.nome].slice();
            return lin;
        }).filter(function (lin) { return !!lin; });
        ordem.forEach(function (nome) {
            if (vistas[nome] || porNome[nome].length < 2) return;
            linhas.push({ id: idLinhaPorNome(nome), nome: nome, colunas: porNome[nome].slice() });
        });
        estado.linhas_layout = linhas;
    }

    function renderRevisarDinamico() {
        removerMuAutomaticaDoEstado();
        realinharLinhasPelosComponentes();
        aplicarTitulosDosSubeventos();
        var col = document.getElementById('bw-colunas-wrap');
        var pvEl = document.getElementById('bw-preview-wrap');
        var pv = aplicarOrdemNoPreview(previewEfetivo());
        // Quadro editável só no passo Fórmula/Colunas. Na revisão, só a prévia.
        var editaQuadro = !!(estado && estado.passo === 'formula' && col);
        if (editaQuadro && !editandoCabecalho) col.innerHTML = htmlColunasOrdem(pv);
        if (pvEl) pvEl.innerHTML = htmlPreview(pv);
        if (editaQuadro) bindRevisarOnce();
    }

    function atualizarPreviewCabecalhosAoVivo() {
        if (!estado || !editandoCabecalho) return;
        var rot = document.activeElement;
        if (!rot || !rot.classList || !rot.classList.contains('bw-linha-rotulo')) return;
        var id = String(rot.getAttribute('data-linha-id') || '');
        var nome = String(rot.textContent || '').trim().slice(0, 40);
        if (!nome) return;
        var caixa = rot.closest('.bw-linha-caixa');
        var nomeAntigo = caixa ? String(caixa.getAttribute('data-linha-nome') || '') : '';
        (estado.linhas_layout || []).forEach(function (lin) {
            if (!lin) return;
            if ((id && String(lin.id || '') === id) || (nomeAntigo && String(lin.nome || '') === nomeAntigo)) {
                lin.nome = nome;
            }
        });
        if (caixa) caixa.setAttribute('data-linha-nome', nome);
        var pvEl = document.getElementById('bw-preview-wrap');
        if (!pvEl) return;
        pvEl.innerHTML = htmlPreview(aplicarOrdemNoPreview(previewEfetivo()));
    }

    function coletarOrdemChipsDoDom() {
        var lista = document.getElementById('bw-colunas-lista');
        if (!lista) return (estado && estado.colunas_ordem) || [];
        var ordem = [];
        var visto = {};
        lista.querySelectorAll('.bw-col-chip').forEach(function (el) {
            var c = String(el.getAttribute('data-codigo') || '').toLowerCase();
            if (!c || c === '_semanal' || c === 'media_sem' || visto[c]) return;
            visto[c] = true;
            ordem.push(c);
        });
        return ordem;
    }

    function sincronizarLinhasLayoutComDom() {
        if (!estado) return;
        var lista = document.getElementById('bw-colunas-lista');
        if (!lista) return;
        var linhas = [];
        lista.querySelectorAll('.bw-linha-caixa').forEach(function (caixa) {
            var nomeEl = caixa.querySelector('.bw-linha-rotulo');
            var nome = String((nomeEl && nomeEl.textContent) || caixa.getAttribute('data-linha-nome') || '').trim().slice(0, 40);
            if (!nome) return;
            var cols = [];
            caixa.querySelectorAll('.bw-col-chip').forEach(function (chip) {
                var c = String(chip.getAttribute('data-codigo') || '').toLowerCase();
                if (c && cols.indexOf(c) < 0) cols.push(c);
            });
            var id = String(caixa.getAttribute('data-linha-id') || '').trim() || idLinhaPorNome(nome);
            linhas.push({ id: id, nome: nome, colunas: cols });
        });
        estado.linhas_layout = linhas;
        var todos = [];
        linhas.forEach(function (lin) { (lin.colunas || []).forEach(function (c) { todos.push(c); }); });
        if (todos.length) gravarLinhaNosComponentes(todos);
    }

    function aplicarNomeCabecalho(linhaId, nomeNovo) {
        if (!estado) return;
        nomeNovo = String(nomeNovo || '').trim().slice(0, 40);
        if (!nomeNovo) return;
        var alvo = String(linhaId || '');
        var cols = [];
        estado.linhas_layout = (estado.linhas_layout || []).map(function (lin) {
            if (!lin) return lin;
            var id = String(lin.id || '');
            var nomeAnt = String(lin.nome || '');
            if (id !== alvo && ('cab:' + nomeAnt.toLowerCase()) !== alvo && nomeAnt !== alvo) return lin;
            lin.nome = nomeNovo;
            cols = (lin.colunas || []).slice();
            return lin;
        });
        if (cols.length) gravarLinhaNosComponentes(cols);
        estado.cabecalhos_manuais = 1;
        if (estado.titulos_periodo_ok && typeof estado.titulos_periodo_ok === 'object') {
            delete estado.titulos_periodo_ok[alvo];
            delete estado.titulos_periodo_ok['cab:' + String(nomeNovo || '').toLowerCase()];
        }
        avisoLinha = { tipo: 'ok', texto: 'Título do período renomeado para “' + nomeNovo + '”. Confirme de novo quando terminar.' };
        nomeLinhaRascunho = '';
        renderRevisarDinamico();
        agendarMontar();
    }

    function confirmarTituloPeriodo(linhaId) {
        if (!estado) return;
        linhaId = String(linhaId || '');
        if (!linhaId) return;
        if (!estado.titulos_periodo_ok || typeof estado.titulos_periodo_ok !== 'object') {
            estado.titulos_periodo_ok = {};
        }
        if (estado.titulos_periodo_ok[linhaId]) return;
        estado.titulos_periodo_ok[linhaId] = 1;
        var nome = '';
        (estado.linhas_layout || []).forEach(function (lin) {
            if (!lin) return;
            var id = String(lin.id || '');
            var n = String(lin.nome || '');
            if (id === linhaId || ('cab:' + n.toLowerCase()) === linhaId || n === linhaId) {
                nome = n;
            }
        });
        avisoLinha = {
            tipo: 'ok',
            texto: nome
                ? ('Título do período “' + nome + '” concluído ✓')
                : 'Título do período concluído ✓'
        };
        renderRevisarDinamico();
    }

    function normalizarSiglaClone(sigla) {
        return String(sigla || '').toLowerCase().replace(/[^a-z0-9_]+/g, '');
    }

    function opcoesPeriodoClone() {
        return agruparItensPorPeriodo(eventosCatalogoBuilder()).map(function (g) {
            return { chave: String(g.chave || ''), rotulo: String(g.rotulo || g.chave || '') };
        }).filter(function (o) { return !!o.chave; });
    }

    function sugestaoTituloClone(chavePeriodo) {
        var bim = bimestreDaChavePeriodo(chavePeriodo);
        if (bim >= 1 && bim <= 4) {
            return ['', 'I', 'II', 'III', 'IV'][bim] + ' BIMESTRE';
        }
        var op = opcoesPeriodoClone().filter(function (o) { return o.chave === chavePeriodo; })[0];
        return op ? String(op.rotulo || '').toUpperCase() : '';
    }

    function htmlPainelClonarTitulo(linhaId, nomeOrigem) {
        var opcoes = opcoesPeriodoClone();
        var chave = String(clonePainelChave || (opcoes[0] && opcoes[0].chave) || 'p-2');
        var nome = String(clonePainelNome || '').trim();
        if (!nome) nome = sugestaoTituloClone(chave) || ('Cópia de ' + String(nomeOrigem || 'período'));
        var html = '<div class="bw-clone-painel" data-clone-linha="' + esc(linhaId) + '">';
        html += '<label>Novo título<input type="text" id="bw-clone-nome" maxlength="40" value="' + esc(nome) + '" placeholder="Ex.: II BIMESTRE"></label>';
        html += '<label>Bimestre destino<select id="bw-clone-periodo">';
        if (!opcoes.length) {
            html += '<option value="p-1">1º bimestre</option>';
            html += '<option value="p-2">2º bimestre</option>';
            html += '<option value="p-3">3º bimestre</option>';
            html += '<option value="p-4">4º bimestre</option>';
        } else {
            opcoes.forEach(function (o) {
                html += '<option value="' + esc(o.chave) + '"' + (o.chave === chave ? ' selected' : '') + '>' + esc(o.rotulo) + '</option>';
            });
        }
        html += '</select></label>';
        html += '<div class="bw-clone-acoes">';
        html += '<button type="button" class="bw-clone-ok" data-clone-aplicar="' + esc(linhaId) + '">Criar cópia</button>';
        html += '<button type="button" class="bw-clone-cancel" data-clone-cancelar="1">Cancelar</button>';
        html += '</div></div>';
        return html;
    }

    function abrirPainelClonarTitulo(linhaId) {
        linhaId = String(linhaId || '');
        if (!linhaId) return;
        var nomeOrigem = '';
        (estado && estado.linhas_layout || []).forEach(function (lin) {
            if (!lin) return;
            var id = String(lin.id || '');
            var n = String(lin.nome || '');
            if (id === linhaId || ('cab:' + n.toLowerCase()) === linhaId || n === linhaId) {
                nomeOrigem = n;
            }
        });
        var opcoes = opcoesPeriodoClone();
        var chavePadrao = 'p-2';
        if (opcoes.length) {
            var achou = opcoes.filter(function (o) { return o.chave === 'p-2'; })[0];
            chavePadrao = achou ? achou.chave : opcoes[0].chave;
        }
        clonePainelLinhaId = linhaId;
        clonePainelChave = chavePadrao;
        clonePainelNome = sugestaoTituloClone(chavePadrao) || ('Cópia de ' + (nomeOrigem || 'período'));
        selecionarTituloPeriodo(linhaId, nomeOrigem);
        renderRevisarDinamico();
        var campo = document.getElementById('bw-clone-nome');
        if (campo && campo.focus) {
            campo.focus();
            campo.select();
        }
    }

    function fecharPainelClonarTitulo() {
        clonePainelLinhaId = '';
        clonePainelNome = '';
        clonePainelChave = '';
        renderRevisarDinamico();
    }

    function eventoParNoPeriodoClone(evOrigem, chaveDestino) {
        if (!evOrigem) return null;
        var sigla = normalizarSiglaClone(siglaDoEvento(evOrigem));
        if (!sigla) return null;
        return eventosCatalogoBuilder().filter(function (ev) {
            if (!ev || chavePeriodoItem(ev) !== chaveDestino) return false;
            return normalizarSiglaClone(siglaDoEvento(ev)) === sigla;
        })[0] || null;
    }

    function eventoPorCodigoNoCatalogo(codigo) {
        codigo = String(codigo || '').toLowerCase();
        if (!codigo) return null;
        var mB = codigo.match(/^b([1-4])_(pp|pu|ch|pe|fal|faltas)$/);
        if (mB) {
            var leaf = mB[2] === 'faltas' ? 'fal' : mB[2];
            var bim = Number(mB[1]);
            return eventosCatalogoBuilder().filter(function (ev) {
                if (Number(ev && ev.bimestre || 0) !== bim) return false;
                return normalizarSiglaClone(siglaDoEvento(ev)) === leaf;
            })[0] || null;
        }
        var mEva = codigo.match(/^eva_?(\d+)$/);
        if (mEva) {
            var id = Number(mEva[1] || 0);
            return ((catalogo && catalogo.eventos_avaliativos) || []).filter(function (x) {
                return Number(x && x.id || 0) === id;
            })[0] || null;
        }
        var siglaNome = normalizarSiglaClone((estado && estado.nomes_blocos && estado.nomes_blocos[codigo]) || '');
        if (!siglaNome) return null;
        return eventosCatalogoBuilder().filter(function (ev) {
            return normalizarSiglaClone(siglaDoEvento(ev)) === siglaNome;
        })[0] || null;
    }

    function remapearTokensFormulaClone(tokens, mapa) {
        return (tokens || []).map(function (tok) {
            if (!tok || typeof tok !== 'object') return tok;
            var copia = JSON.parse(JSON.stringify(tok));
            if (copia.type === 'peca') {
                var v = String(copia.value || '').toLowerCase();
                if (mapa[v]) {
                    copia.value = mapa[v];
                    if (copia.label) {
                        var lab = String(copia.label);
                        if (/1[ºo]/i.test(lab) && /s2_/.test(String(mapa[v] || ''))) {
                            copia.label = lab.replace(/1[ºo]/gi, '2º');
                        } else if (/2[ºo]/i.test(lab) && /s1_/.test(String(mapa[v] || ''))) {
                            copia.label = lab.replace(/2[ºo]/gi, '1º');
                        }
                    }
                }
            }
            return copia;
        });
    }

    function semestreDestinoClone(chavePeriodo, nomeNovo) {
        var chave = String(chavePeriodo || '');
        var mRec = chave.match(/^rec-sem-(\d+)$/);
        if (mRec) {
            var nRec = Number(mRec[1] || 0);
            return (nRec === 1 || nRec === 2) ? nRec : 0;
        }
        var bim = bimestreDaChavePeriodo(chave);
        if (bim === 1 || bim === 2) return 1;
        if (bim === 3 || bim === 4) return 2;
        var nome = String(nomeNovo || '').toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        if (/sem/.test(nome)) {
            if (/(^|[^a-z0-9])(2|ii|segundo)([^a-z0-9]|$)/.test(nome)) return 2;
            if (/(^|[^a-z0-9])(1|i|primeiro)([^a-z0-9]|$)/.test(nome)) return 1;
        }
        return 0;
    }

    function semestreOrigemClone(cols) {
        cols = cols || [];
        if (cols.indexOf('s1_rec') >= 0 || cols.indexOf('s1_ms') >= 0) return 1;
        if (cols.indexOf('s2_rec') >= 0 || cols.indexOf('s2_ms') >= 0) return 2;
        return 0;
    }

    function garantirColunaRecSemestreClone(sem) {
        sem = Number(sem || 0) === 2 ? 2 : 1;
        var codigo = 's' + sem + '_rec';
        var nome = sem === 2 ? 'REC 2º' : 'REC 1º';
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        mostrarColuna(codigo);
        estado.nomes_blocos[codigo] = nome;
        garantirComponenteManualNoRascunho(codigo, nome);
        [rascunhoAtual, estado.rascunho_preservado].forEach(function (r) {
            if (!r || !Array.isArray(r.componentes)) return;
            r.componentes.forEach(function (c) {
                if (!c || String(c.codigo || '').toLowerCase() !== codigo) return;
                c.nome = nome;
                c.source_type = c.source_type || 'nenhuma';
                c.layout_type = 'rec';
                if (!c.config || typeof c.config !== 'object') c.config = {};
                c.config.layout_type = 'rec';
                c.config.layout_group = 's' + sem;
            });
        });
        if (estado.colunas_ordem.map(function (c) { return String(c || '').toLowerCase(); }).indexOf(codigo) < 0) {
            estado.colunas_ordem.push(codigo);
        }
        return codigo;
    }

    function garantirColunaMsSemestreClone(sem) {
        sem = Number(sem || 0) === 2 ? 2 : 1;
        var codigo = 's' + sem + '_ms';
        var nome = sem === 2 ? 'MS 2º' : 'MS 1º';
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        if (!Array.isArray(estado.blocos_calc)) estado.blocos_calc = [];
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        mostrarColuna(codigo);
        estado.nomes_blocos[codigo] = nome;
        if (estado.blocos_calc.indexOf(codigo) < 0) estado.blocos_calc.push(codigo);
        garantirComponenteManualNoRascunho(codigo, nome);
        [rascunhoAtual, estado.rascunho_preservado].forEach(function (r) {
            if (!r || !Array.isArray(r.componentes)) return;
            r.componentes.forEach(function (c) {
                if (!c || String(c.codigo || '').toLowerCase() !== codigo) return;
                c.nome = nome;
                c.source_type = 'calculado';
                c.layout_type = 'media';
                if (!c.config || typeof c.config !== 'object') c.config = {};
                c.config.layout_type = 'media';
                c.config.layout_group = 's' + sem;
            });
        });
        if (estado.colunas_ordem.map(function (c) { return String(c || '').toLowerCase(); }).indexOf(codigo) < 0) {
            estado.colunas_ordem.push(codigo);
        }
        return codigo;
    }

    function mapaMuEntreSemestresClone(semOrig, semDest) {
        var mapa = {};
        if (semOrig === 1 && semDest === 2) {
            mapa.b1_mu = 'b3_mu';
            mapa.b2_mu = 'b4_mu';
        } else if (semOrig === 2 && semDest === 1) {
            mapa.b3_mu = 'b1_mu';
            mapa.b4_mu = 'b2_mu';
        }
        var bimsOrig = semOrig === 1 ? [1, 2] : (semOrig === 2 ? [3, 4] : []);
        var bimsDest = semDest === 1 ? [1, 2] : (semDest === 2 ? [3, 4] : []);
        if (bimsOrig.length !== 2 || bimsDest.length !== 2) return mapa;

        function muDoBimestre(bim) {
            var achou = '';
            (estado.linhas_layout || []).forEach(function (lin) {
                if (!lin || achou) return;
                var cols = (lin.colunas || []).map(function (c) { return String(c || '').toLowerCase(); });
                var temBim = cols.some(function (c) {
                    return c.indexOf('b' + bim + '_') === 0;
                });
                if (!temBim) {
                    var nome = String(lin.nome || '').toLowerCase();
                    var romanos = { 1: 'i', 2: 'ii', 3: 'iii', 4: 'iv' };
                    if (!(nome.indexOf(bim + '') >= 0 || nome.indexOf(romanos[bim] + ' ') >= 0 || nome.indexOf(romanos[bim] + 'b') >= 0)) {
                        return;
                    }
                }
                cols.forEach(function (c) {
                    if (achou) return;
                    if (c === ('b' + bim + '_mu')) achou = c;
                    else if (ehCodigoBlocoCalc(c) && /mu/i.test(String((estado.nomes_blocos && estado.nomes_blocos[c]) || c))) {
                        achou = c;
                    }
                });
            });
            return achou;
        }

        var i;
        for (i = 0; i < 2; i += 1) {
            var de = muDoBimestre(bimsOrig[i]);
            var para = muDoBimestre(bimsDest[i]) || ('b' + bimsDest[i] + '_mu');
            if (de && para) mapa[de] = para;
        }
        return mapa;
    }

    function titulosComPeriodoDestino(chavePeriodo, linhaOrigemId) {
        chavePeriodo = String(chavePeriodo || '');
        linhaOrigemId = String(linhaOrigemId || '');
        if (!chavePeriodo || !estado) return [];
        var bim = bimestreDaChavePeriodo(chavePeriodo);
        var prefixoBim = (bim >= 1 && bim <= 4) ? ('b' + bim + '_') : '';
        var codigosDestino = {};
        eventosCatalogoBuilder().forEach(function (ev) {
            if (!ev || chavePeriodoItem(ev) !== chavePeriodo) return;
            var cod = String(codigoColunaExistenteDoEvento(ev) || codigoPreferidoEvento(ev) || '').toLowerCase();
            if (cod) codigosDestino[cod] = true;
        });
        if (prefixoBim) {
            ['pp', 'pu', 'ch', 'pe', 'fal', 'mu'].forEach(function (leaf) {
                codigosDestino[prefixoBim + leaf] = true;
            });
        }
        var semDestChk = semestreDestinoClone(chavePeriodo, '');
        if (semDestChk === 1) {
            codigosDestino.s1_rec = true;
            codigosDestino.s1_ms = true;
        } else if (semDestChk === 2) {
            codigosDestino.s2_rec = true;
            codigosDestino.s2_ms = true;
        }
        var nomes = [];
        (estado.linhas_layout || []).forEach(function (lin) {
            if (!lin) return;
            var id = String(lin.id || '');
            var nome = String(lin.nome || '').trim();
            if (!nome) return;
            if (id === linhaOrigemId
                || ('cab:' + nome.toLowerCase()) === linhaOrigemId
                || nome === linhaOrigemId) {
                return;
            }
            var tem = (lin.colunas || []).some(function (c) {
                var low = String(c || '').toLowerCase();
                if (!low) return false;
                if (codigosDestino[low]) return true;
                if (prefixoBim && low.indexOf(prefixoBim) === 0) return true;
                return false;
            });
            if (tem) nomes.push(nome);
        });
        return nomes;
    }

    function clonarTituloPeriodo(linhaId, nomeNovo, chavePeriodoDestino) {
        if (!estado) return false;
        linhaId = String(linhaId || '');
        nomeNovo = String(nomeNovo || '').trim().slice(0, 40);
        chavePeriodoDestino = String(chavePeriodoDestino || '');
        if (!linhaId) return false;
        if (!nomeNovo) {
            avisoLinha = { tipo: 'erro', texto: 'Informe o novo título do período.' };
            renderRevisarDinamico();
            return false;
        }
        if (!chavePeriodoDestino) {
            avisoLinha = { tipo: 'erro', texto: 'Escolha o bimestre destino.' };
            renderRevisarDinamico();
            return false;
        }
        var origem = null;
        (estado.linhas_layout || []).forEach(function (lin) {
            if (!lin || origem) return;
            var id = String(lin.id || '');
            var n = String(lin.nome || '');
            if (id === linhaId || ('cab:' + n.toLowerCase()) === linhaId || n === linhaId) origem = lin;
        });
        if (!origem) {
            avisoLinha = { tipo: 'erro', texto: 'Não encontrei o título de origem para clonar.' };
            renderRevisarDinamico();
            return false;
        }
        var colsOrig = (origem.colunas || []).map(function (c) {
            return String(c || '').toLowerCase();
        }).filter(Boolean);
        if (!colsOrig.length) {
            avisoLinha = { tipo: 'erro', texto: 'O título de origem não tem colunas para clonar.' };
            renderRevisarDinamico();
            return false;
        }
        var jaTem = titulosComPeriodoDestino(chavePeriodoDestino, linhaId);
        if (jaTem.length) {
            var rotuloDest = '';
            opcoesPeriodoClone().forEach(function (o) {
                if (o.chave === chavePeriodoDestino) rotuloDest = o.rotulo;
            });
            if (!rotuloDest) rotuloDest = chavePeriodoDestino;
            var msgJa = 'Esse bimestre já está adicionado'
                + (rotuloDest ? (' (“' + rotuloDest + '”)') : '')
                + ' no título “' + jaTem.join('”, “') + '”. '
                + 'Troque o bimestre destino antes de criar a cópia.';
            avisoLinha = { tipo: 'erro', texto: msgJa };
            try { window.alert(msgJa); } catch (errAlert) { /* ignore */ }
            renderRevisarDinamico();
            return false;
        }
        var nomeDuplicado = (estado.linhas_layout || []).some(function (lin) {
            if (!lin) return false;
            var id = String(lin.id || '');
            var n = String(lin.nome || '').trim().toLowerCase();
            if (id === linhaId || ('cab:' + n) === linhaId) return false;
            return n === nomeNovo.toLowerCase();
        });
        if (nomeDuplicado) {
            var msgNome = 'Já existe um título “' + nomeNovo + '” no quadro. Altere o novo título antes de clonar.';
            avisoLinha = { tipo: 'erro', texto: msgNome };
            try { window.alert(msgNome); } catch (errNome) { /* ignore */ }
            renderRevisarDinamico();
            return false;
        }
        var bimDest = bimestreDaChavePeriodo(chavePeriodoDestino);
        var semOrig = semestreOrigemClone(colsOrig);
        var semDest = semestreDestinoClone(chavePeriodoDestino, nomeNovo);
        if (!semDest && semOrig) semDest = semOrig === 1 ? 2 : 1;
        var mapa = {};
        var pulados = [];
        var ehSm = !!(estado.modelo_santa_maria || estado.origem === 'santa-maria');
        if (semOrig && semDest && semOrig !== semDest) {
            var mapaMuSem = mapaMuEntreSemestresClone(semOrig, semDest);
            Object.keys(mapaMuSem).forEach(function (k) { mapa[k] = mapaMuSem[k]; });
        }

        function ehCalcClone(cod) {
            return ehCodigoBlocoCalc(cod) || /^b[1-4]_mu$/i.test(cod) || /^s[12]_ms$/i.test(cod);
        }

        colsOrig.forEach(function (cod) {
            if (ehCalcClone(cod)) return;
            if (/^s[12]_rec$/i.test(cod)) {
                var semRec = semDest || (/^s2_/.test(cod) ? 1 : 2);
                mapa[cod] = garantirColunaRecSemestreClone(semRec);
                return;
            }
            var mB = cod.match(/^b([1-4])_(pp|pu|ch|pe|fal|faltas)$/);
            if (mB && bimDest >= 1 && bimDest <= 4) {
                var leaf = mB[2] === 'faltas' ? 'fal' : mB[2];
                if (leaf === 'fal') {
                    adicionarColunaFal(bimDest, { silencioso: true });
                    mapa[cod] = 'b' + bimDest + '_fal';
                    return;
                }
                var evLeaf = eventosCatalogoBuilder().filter(function (ev) {
                    if (chavePeriodoItem(ev) !== chavePeriodoDestino) return false;
                    return normalizarSiglaClone(siglaDoEvento(ev)) === leaf;
                })[0];
                if (!evLeaf) {
                    pulados.push(leaf.toUpperCase());
                    return;
                }
                adicionarColunaDoEvento(evLeaf, { silencioso: true });
                mapa[cod] = String(codigoColunaExistenteDoEvento(evLeaf) || codigoPreferidoEvento(evLeaf) || '').toLowerCase();
                if (!mapa[cod]) pulados.push(leaf.toUpperCase());
                return;
            }
            var evOrig = eventoPorCodigoNoCatalogo(cod);
            if (evOrig) {
                var bimEv = Number(evOrig.bimestre || 0);
                var ehRecOrig = (bimEv === 81 || bimEv === 61 || bimEv === 62 || bimEv === 82 || bimEv === 63 || bimEv === 64
                    || normalizarSiglaClone(siglaDoEvento(evOrig)) === 'rec');
                if (ehRecOrig && semDest) {
                    mapa[cod] = garantirColunaRecSemestreClone(semDest);
                    return;
                }
                var chaveBusca = chavePeriodoDestino;
                if (ehRecOrig && semDest) chaveBusca = 'rec-sem-' + semDest;
                var par = eventoParNoPeriodoClone(evOrig, chaveBusca)
                    || eventoParNoPeriodoClone(evOrig, chavePeriodoDestino);
                if (!par) {
                    if (ehRecOrig && semDest) {
                        mapa[cod] = garantirColunaRecSemestreClone(semDest);
                        return;
                    }
                    pulados.push(String(siglaDoEvento(evOrig) || cod).toUpperCase());
                    return;
                }
                adicionarColunaDoEvento(par, { silencioso: true });
                mapa[cod] = String(codigoColunaExistenteDoEvento(par) || codigoPreferidoEvento(par) || '').toLowerCase();
                if (!mapa[cod]) pulados.push(String(siglaDoEvento(evOrig) || cod).toUpperCase());
                return;
            }
            if (/^b[1-4]_fal(tas)?$/.test(cod) && bimDest >= 1 && bimDest <= 4) {
                adicionarColunaFal(bimDest, { silencioso: true });
                mapa[cod] = 'b' + bimDest + '_fal';
                return;
            }
            pulados.push(String((estado.nomes_blocos && estado.nomes_blocos[cod]) || cod).toUpperCase());
        });

        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!estado.escalas_blocos || typeof estado.escalas_blocos !== 'object') estado.escalas_blocos = {};
        if (!Array.isArray(estado.blocos_calc)) estado.blocos_calc = [];
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];

        colsOrig.forEach(function (cod) {
            if (!ehCalcClone(cod)) return;
            var ehMuSm = /^b[1-4]_mu$/i.test(cod);
            var ehMsSem = /^s[12]_ms$/i.test(cod);
            var novoCod = '';
            if (ehMsSem) {
                novoCod = garantirColunaMsSemestreClone(semDest || (/^s2_/.test(cod) ? 1 : 2));
            } else if (ehMuSm && ehSm && bimDest >= 1 && bimDest <= 4) {
                novoCod = 'b' + bimDest + '_mu';
            } else {
                novoCod = novoCodigoBloco();
            }
            mapa[cod] = novoCod;
            var toksOrig = estado.formulas_blocos[cod] || estado.formulas_blocos[String(cod).toLowerCase()] || [];
            estado.formulas_blocos[novoCod] = remapearTokensFormulaClone(toksOrig, mapa);
            if (ehMsSem) {
                var semMsNome = semDest || (/^s2_/.test(cod) ? 1 : 2);
                estado.nomes_blocos[novoCod] = semMsNome === 2 ? 'MS 2º' : 'MS 1º';
            } else {
                estado.nomes_blocos[novoCod] = estado.nomes_blocos[cod]
                    || estado.nomes_blocos[String(cod).toLowerCase()]
                    || 'MU';
            }
            if (estado.escalas_blocos[cod] != null) {
                estado.escalas_blocos[novoCod] = estado.escalas_blocos[cod];
            } else if (estado.escalas_blocos[novoCod] == null) {
                estado.escalas_blocos[novoCod] = 10;
            }
            if (estado.blocos_calc.indexOf(novoCod) < 0) estado.blocos_calc.push(novoCod);
            mostrarColuna(novoCod);
            garantirComponenteManualNoRascunho(novoCod, estado.nomes_blocos[novoCod]);
            if (estado.colunas_ordem.map(function (c) { return String(c || '').toLowerCase(); }).indexOf(novoCod) < 0) {
                estado.colunas_ordem.push(novoCod);
            }
            var excecoes = mapaExcecoesBloco(cod, false);
            if (excecoes && typeof excecoes === 'object' && Object.keys(excecoes).length) {
                var destinoExc = mapaExcecoesBloco(novoCod, true);
                Object.keys(excecoes).forEach(function (mid) {
                    destinoExc[mid] = remapearTokensFormulaClone(excecoes[mid], mapa);
                });
            }
        });

        // Segunda passagem: tokens de cálculo que referenciam outros códigos mapeados depois.
        Object.keys(mapa).forEach(function (codAntigo) {
            var novo = mapa[codAntigo];
            if (!novo || !ehCalcClone(codAntigo)) return;
            if (!Array.isArray(estado.formulas_blocos[novo])) return;
            estado.formulas_blocos[novo] = remapearTokensFormulaClone(estado.formulas_blocos[novo], mapa);
        });

        var novasCols = [];
        colsOrig.forEach(function (cod) {
            var dest = mapa[cod];
            if (dest && novasCols.indexOf(dest) < 0) novasCols.push(dest);
        });
        if (!novasCols.length) {
            avisoLinha = {
                tipo: 'erro',
                texto: 'Não foi possível clonar: nenhum evento correspondente no bimestre destino.'
                    + (pulados.length ? (' Faltaram: ' + pulados.join(', ') + '.') : '')
            };
            renderRevisarDinamico();
            return false;
        }

        var novoId = 'lin' + Date.now().toString(36);
        if (!Array.isArray(estado.linhas_layout)) estado.linhas_layout = [];
        estado.linhas_layout = estado.linhas_layout.map(function (lin) {
            if (!lin) return lin;
            lin.colunas = (lin.colunas || []).filter(function (c) {
                return novasCols.indexOf(String(c || '').toLowerCase()) < 0;
            });
            return lin;
        }).filter(function (lin) { return lin && String(lin.nome || '').trim(); });
        estado.linhas_layout.push({ id: novoId, nome: nomeNovo, colunas: novasCols.slice() });
        estado.cabecalhos_manuais = 1;
        estado.quadro_chips_manuais = 1;
        estado.limpar_titulos_periodo = 0;
        if (!estado.titulos_periodo_ok || typeof estado.titulos_periodo_ok !== 'object') {
            estado.titulos_periodo_ok = {};
        }
        delete estado.titulos_periodo_ok[novoId];
        compactarColunasOrdem(novasCols);
        sincronizarOrdemComLinhasLayout();
        gravarLinhaNosComponentes(novasCols);
        clonePainelLinhaId = '';
        clonePainelNome = '';
        clonePainelChave = '';
        selecionarTituloPeriodo(novoId, nomeNovo);
        var textoOk = 'Cópia “' + nomeNovo + '” criada com ' + novasCols.length + ' coluna'
            + (novasCols.length === 1 ? '' : 's') + ' e cálculos remapeados.';
        if (pulados.length) {
            textoOk += ' Não achei no destino: ' + pulados.join(', ') + '.';
        }
        avisoLinha = { tipo: 'ok', texto: textoOk };
        renderRevisarDinamico();
        agendarMontar();
        return true;
    }

    function moverCodigoParaCabecalho(codigo, nomeCab, linhaId) {
        codigo = String(codigo || '').toLowerCase();
        if (!codigo || !estado) return;
        if (!Array.isArray(estado.linhas_layout)) estado.linhas_layout = [];
        estado.linhas_layout = estado.linhas_layout.map(function (lin) {
            if (!lin) return lin;
            lin.colunas = (lin.colunas || []).filter(function (c) { return String(c).toLowerCase() !== codigo; });
            return lin;
        }).filter(function (lin) { return lin && String(lin.nome || '').trim(); });
        nomeCab = String(nomeCab || '').trim().slice(0, 40);
        if (!nomeCab) {
            gravarLinhaNosComponentes([codigo]);
            return;
        }
        var destino = null;
        estado.linhas_layout.forEach(function (lin) {
            if (!lin) return;
            if (linhaId && String(lin.id) === String(linhaId)) destino = lin;
            else if (!destino && String(lin.nome || '') === nomeCab) destino = lin;
        });
        if (!destino) {
            destino = { id: String(linhaId || idLinhaPorNome(nomeCab)), nome: nomeCab, colunas: [] };
            estado.linhas_layout.push(destino);
        }
        if (destino.colunas.indexOf(codigo) < 0) destino.colunas.push(codigo);
        // Trava o layout manual para o montar do servidor não apagar MU/bloco de cálculo do cabeçalho.
        estado.cabecalhos_manuais = 1;
        estado.quadro_chips_manuais = 1;
        // Garante MU contíguo às demais colunas do cabeçalho na ordem do arraste.
        compactarColunasOrdem(destino.colunas.slice());
        gravarLinhaNosComponentes([codigo]);
    }

    function bindRevisarOnce() {
        if (bodyEl._bwRevisarBound) return;
        bodyEl._bwRevisarBound = true;
        var catalogoDragPayload = '';
        var catalogoDragou = false;
        bodyEl.addEventListener('dragstart', function (e) {
            var item = e.target.closest('[data-catalogo-evento], [data-catalogo-fal]');
            if (!item || !e.dataTransfer) return;
            var evId = item.getAttribute('data-catalogo-evento');
            var falBim = item.getAttribute('data-catalogo-fal');
            catalogoDragPayload = evId ? ('ev:' + evId) : ('fal:' + falBim);
            catalogoDragou = false;
            try {
                e.dataTransfer.setData('text/bw-catalogo', catalogoDragPayload);
                e.dataTransfer.setData('text/plain', catalogoDragPayload);
                e.dataTransfer.effectAllowed = 'copy';
            } catch (errCat) { /* ignore */ }
            item.classList.add('dragging');
        });
        bodyEl.addEventListener('dragend', function (e) {
            var item = e.target.closest('.bw-catalogo-item');
            if (item) item.classList.remove('dragging');
            document.querySelectorAll('.bw-linha-caixa.soltar-catalogo').forEach(function (cx) {
                cx.classList.remove('soltar-catalogo');
            });
            setTimeout(function () {
                catalogoDragPayload = '';
                catalogoDragou = false;
            }, 80);
        });
        bodyEl.addEventListener('dragover', function (e) {
            if (!catalogoDragPayload) return;
            var caixa = e.target.closest('#bw-colunas-lista .bw-linha-caixa');
            if (!caixa) return;
            e.preventDefault();
            try { e.dataTransfer.dropEffect = 'copy'; } catch (errOv) { /* ignore */ }
            document.querySelectorAll('.bw-linha-caixa.soltar-catalogo').forEach(function (cx) {
                if (cx !== caixa) cx.classList.remove('soltar-catalogo');
            });
            caixa.classList.add('soltar-catalogo');
            catalogoDragou = true;
        });
        bodyEl.addEventListener('dragleave', function (e) {
            if (!catalogoDragPayload) return;
            var caixa = e.target.closest('#bw-colunas-lista .bw-linha-caixa');
            if (!caixa) return;
            if (e.relatedTarget && caixa.contains(e.relatedTarget)) return;
            caixa.classList.remove('soltar-catalogo');
        });
        bodyEl.addEventListener('drop', function (e) {
            if (!catalogoDragPayload) return;
            var caixa = e.target.closest('#bw-colunas-lista .bw-linha-caixa');
            if (!caixa) return;
            e.preventDefault();
            e.stopPropagation();
            caixa.classList.remove('soltar-catalogo');
            var payload = catalogoDragPayload;
            try {
                payload = e.dataTransfer.getData('text/bw-catalogo')
                    || e.dataTransfer.getData('text/plain')
                    || payload;
            } catch (errDrop) { /* ignore */ }
            var linhaId = String(caixa.getAttribute('data-linha-id') || '');
            var rot = caixa.querySelector('.bw-linha-rotulo');
            var nomeCab = String((rot && rot.textContent) || caixa.getAttribute('data-linha-nome') || '').trim();
            catalogoDragou = true;
            soltarCatalogoNoTitulo(payload, linhaId, nomeCab);
        });
        bodyEl.addEventListener('click', function (e) {
            var vistaBtn = e.target.closest('[data-vista-preview]');
            if (vistaBtn) {
                vistaPreview = vistaBtn.getAttribute('data-vista-preview') === 'boletim' ? 'boletim' : 'quadro';
                renderRevisarDinamico();
                return;
            }
            var catTodos = e.target.closest('[data-catalogo-periodo]');
            if (catTodos) {
                e.preventDefault();
                e.stopPropagation();
                adicionarTodasColunasDoPeriodo(catTodos.getAttribute('data-catalogo-periodo') || '');
                return;
            }
            var catFal = e.target.closest('[data-catalogo-fal]');
            if (catFal) {
                e.preventDefault();
                if (catalogoDragou) return;
                var destFal = destinoTituloPeriodoAtivo() || {};
                adicionarColunaFal(Number(catFal.getAttribute('data-catalogo-fal') || 0), {
                    linhaId: destFal.linhaId || '',
                    nomeCab: destFal.nomeCab || ''
                });
                return;
            }
            var catItem = e.target.closest('[data-catalogo-evento]');
            if (catItem) {
                e.preventDefault();
                if (catalogoDragou) return;
                var evIdCat = Number(catItem.getAttribute('data-catalogo-evento') || 0);
                var evCat = ((catalogo && catalogo.eventos_avaliativos) || []).filter(function (x) {
                    return Number(x && x.id || 0) === evIdCat;
                })[0];
                if (evCat) {
                    var destEv = destinoTituloPeriodoAtivo() || {};
                    adicionarColunaDoEvento(evCat, {
                        linhaId: destEv.linhaId || '',
                        nomeCab: destEv.nomeCab || ''
                    });
                }
                return;
            }
            var caixaSel = e.target.closest('#bw-colunas-lista .bw-linha-caixa');
            if (caixaSel && !e.target.closest('.bw-linha-ok, .bw-linha-clonar, .bw-linha-x, .bw-col-x, .bw-linha-grip, .bw-col-grip, .bw-col-chip, .bw-clone-painel')) {
                selecionarTituloPeriodo(
                    caixaSel.getAttribute('data-linha-id') || '',
                    caixaSel.getAttribute('data-linha-nome') || ''
                );
                return;
            }
            if (e.target.closest('#bw-add-coluna')) {
                adicionarColunaManual();
                return;
            }
            if (e.target.closest('#bw-add-media')) {
                if (!ehDemonstrativoNotas()) adicionarBlocoMedia();
                return;
            }
            if (e.target.closest('#bw-criar-linha')) {
                e.preventDefault();
                var campoLinha = document.getElementById('bw-linha-nome');
                nomeLinhaRascunho = campoLinha ? String(campoLinha.value || '') : nomeLinhaRascunho;
                if (!String(nomeLinhaRascunho || '').trim()) {
                    avisoLinha = { tipo: 'erro', texto: 'Escreva o título do período. Ex.: I BIMESTRE.' };
                    renderRevisarDinamico();
                    campoLinha = document.getElementById('bw-linha-nome');
                    if (campoLinha) campoLinha.focus();
                    return;
                }
                criarLinhaLayout();
                return;
            }
            if (e.target.closest('#bw-tirar-linha')) {
                e.preventDefault();
                tirarLinhaLayout();
                return;
            }
            if (e.target.closest('#bw-limpar-cabecalhos')) {
                e.preventDefault();
                limparTodosCabecalhos();
                return;
            }
            if (e.target.closest('#bw-excluir-todos-chips')) {
                e.preventDefault();
                if (!window.confirm('Excluir todos os chips do quadro?')) return;
                limparTodosChipsQuadro();
                return;
            }
            var okCab = e.target.closest('#bw-colunas-lista .bw-linha-ok');
            if (okCab) {
                e.preventDefault();
                if (!okCab.classList.contains('ativo')) {
                    confirmarTituloPeriodo(okCab.getAttribute('data-linha-id') || '');
                }
                return;
            }
            var clonarCab = e.target.closest('#bw-colunas-lista .bw-linha-clonar');
            if (clonarCab) {
                e.preventDefault();
                e.stopPropagation();
                abrirPainelClonarTitulo(clonarCab.getAttribute('data-linha-id') || '');
                return;
            }
            var cloneAplicar = e.target.closest('[data-clone-aplicar]');
            if (cloneAplicar) {
                e.preventDefault();
                e.stopPropagation();
                var idClone = cloneAplicar.getAttribute('data-clone-aplicar') || '';
                var nomeCloneEl = document.getElementById('bw-clone-nome');
                var periodoCloneEl = document.getElementById('bw-clone-periodo');
                clonePainelNome = nomeCloneEl ? String(nomeCloneEl.value || '') : clonePainelNome;
                clonePainelChave = periodoCloneEl ? String(periodoCloneEl.value || '') : clonePainelChave;
                clonarTituloPeriodo(idClone, clonePainelNome, clonePainelChave);
                return;
            }
            if (e.target.closest('[data-clone-cancelar]')) {
                e.preventDefault();
                e.stopPropagation();
                fecharPainelClonarTitulo();
                return;
            }
            var xCab = e.target.closest('#bw-colunas-lista .bw-linha-x');
            if (xCab) {
                e.preventDefault();
                removerCabecalhoPorId(xCab.getAttribute('data-linha-id') || '');
                return;
            }
            var xcol = e.target.closest('#bw-colunas-lista .bw-col-x');
            if (xcol) {
                e.preventDefault();
                e.stopPropagation();
                removerBlocoCalc(xcol.getAttribute('data-codigo'));
                return;
            }
            var chipCalc = e.target.closest('#bw-colunas-lista .bw-col-chip.calc');
            if (chipCalc) {
                if (colunasDragou || e.target.closest('.bw-col-grip') || e.target.closest('.bw-col-x')) {
                    colunasDragou = false;
                    return;
                }
                e.preventDefault();
                var codCalc = chipCalc.getAttribute('data-codigo');
                if (codCalc) abrirEdicaoBlocoCalc(codCalc);
                return;
            }
        });
        bodyEl.addEventListener('input', function (e) {
            if (!e.target || e.target.id !== 'bw-clone-nome') return;
            clonePainelNome = String(e.target.value || '');
        });
        bodyEl.addEventListener('change', function (e) {
            if (!e.target || e.target.id !== 'bw-clone-periodo') return;
            clonePainelChave = String(e.target.value || '');
            var sug = sugestaoTituloClone(clonePainelChave);
            var nomeEl = document.getElementById('bw-clone-nome');
            if (sug && nomeEl) {
                var atual = String(nomeEl.value || '').trim();
                var eraSugestao = !atual
                    || /^[IVX]+ BIMESTRE$/i.test(atual)
                    || /^Cópia de /i.test(atual);
                if (eraSugestao) {
                    nomeEl.value = sug;
                    clonePainelNome = sug;
                }
            }
        });
        bodyEl.addEventListener('focusin', function (e) {
            if (!e.target || e.target.id !== 'bw-preview-aluno-busca') return;
            if (typeof e.target.select === 'function') e.target.select();
            abrirListaAlunosPreview(true);
        });
        bodyEl.addEventListener('click', function (e) {
            var item = e.target.closest('[data-preview-aluno-id]');
            if (!item || !estado) return;
            var idItem = Number(item.getAttribute('data-preview-aluno-id') || 0);
            if (idItem <= 0) return;
            estado.aluno_preview_id = idItem;
            previewAtual = null;
            atualizarBotaoGerarAluno();
            renderRevisarDinamico();
            agendarMontar();
        });
        document.addEventListener('click', function (e) {
            var box = document.getElementById('bw-preview-aluno-lista');
            if (!box || box.classList.contains('hidden')) return;
            if (e.target.closest('#bw-preview-aluno-busca') || e.target.closest('#bw-preview-aluno-lista')) return;
            box.classList.add('hidden');
        });
        bodyEl.addEventListener('input', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('bw-linha-rotulo')) {
                nomeLinhaRascunho = String(e.target.textContent || '').trim();
                var campoSync = document.getElementById('bw-linha-nome');
                if (campoSync) campoSync.value = nomeLinhaRascunho;
                atualizarPreviewCabecalhosAoVivo();
                return;
            }
            if (e.target && e.target.id === 'bw-linha-nome') {
                nomeLinhaRascunho = String(e.target.value || '');
                return;
            }
            if (!e.target || e.target.id !== 'bw-preview-aluno-busca' || !estado) return;
            if (String(e.target.value || '').trim() === '' && Number(estado.aluno_preview_id || 0) > 0) {
                estado.aluno_preview_id = 0;
                previewAtual = null;
                atualizarBotaoGerarAluno();
                renderRevisarDinamico();
                agendarMontar();
                var campo = document.getElementById('bw-preview-aluno-busca');
                if (campo) campo.focus();
                return;
            }
            abrirListaAlunosPreview(false);
        });
        bodyEl.addEventListener('click', function (e) {
            if (!e.target || e.target.id !== 'bw-preview-aluno-limpar' || !estado) return;
            estado.aluno_preview_id = 0;
            previewAtual = null;
            atualizarBotaoGerarAluno();
            renderRevisarDinamico();
            agendarMontar();
        });
        bodyEl.addEventListener('pointerdown', function (e) {
            if (e.button > 0) return;
            if (e.target.closest('.bw-linha-rotulo') || e.target.closest('.bw-linha-ok') || e.target.closest('.bw-linha-clonar') || e.target.closest('.bw-linha-x') || e.target.closest('.bw-col-x') || e.target.closest('.bw-clone-painel')) return;
            var gripGrupo = e.target.closest('#bw-colunas-lista .bw-linha-grip');
            var caixaGrupo = gripGrupo ? gripGrupo.closest('.bw-linha-caixa') : null;
            if (caixaGrupo) {
                arrasteGrupo = {
                    id: String(caixaGrupo.getAttribute('data-linha-id') || ''),
                    caixa: caixaGrupo,
                    x: e.clientX,
                    y: e.clientY,
                    ativo: false,
                    pointerId: e.pointerId
                };
                return;
            }
            var chip = e.target.closest('#bw-colunas-lista .bw-col-chip');
            if (!chip) return;
            var cod = chip.getAttribute('data-codigo') || '';
            if (!cod || cod === '_semanal' || cod === 'media_sem' || chip.classList.contains('travada')) return;
            arrasteColuna = {
                codigo: cod,
                chip: chip,
                x: e.clientX,
                y: e.clientY,
                ativo: false,
                pointerId: e.pointerId
            };
        });
        bodyEl.addEventListener('pointermove', function (e) {
            if (arrasteGrupo && e.pointerId === arrasteGrupo.pointerId) {
                var gdx = e.clientX - arrasteGrupo.x;
                var gdy = e.clientY - arrasteGrupo.y;
                if (!arrasteGrupo.ativo) {
                    if ((gdx * gdx) + (gdy * gdy) < 16) return;
                    arrasteGrupo.ativo = true;
                    colunasDragou = true;
                    arrasteGrupo.caixa.classList.add('dragging');
                    var listaG = document.getElementById('bw-colunas-lista');
                    if (listaG) listaG.classList.add('arrastando');
                    try { arrasteGrupo.caixa.setPointerCapture(e.pointerId); } catch (errG) { /* ignore */ }
                }
                e.preventDefault();
                posicionarMarcaGrupo(e.clientX, e.clientY);
                return;
            }
            if (!arrasteColuna || e.pointerId !== arrasteColuna.pointerId) return;
            var dx = e.clientX - arrasteColuna.x;
            var dy = e.clientY - arrasteColuna.y;
            if (!arrasteColuna.ativo) {
                if ((dx * dx) + (dy * dy) < 16) return;
                arrasteColuna.ativo = true;
                colunasDragou = true;
                colunasDrag = arrasteColuna.codigo;
                arrasteColuna.chip.classList.add('dragging');
                var listaAtiva = document.getElementById('bw-colunas-lista');
                if (listaAtiva) listaAtiva.classList.add('arrastando');
                try { arrasteColuna.chip.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
            }
            e.preventDefault();
            posicionarMarcaColuna(e.clientX, e.clientY);
        });
        bodyEl.addEventListener('pointerup', function (e) {
            if (arrasteGrupo && e.pointerId === arrasteGrupo.pointerId) {
                var ativoG = arrasteGrupo.ativo;
                var idG = arrasteGrupo.id;
                if (arrasteGrupo.caixa) arrasteGrupo.caixa.classList.remove('dragging');
                var listaG2 = document.getElementById('bw-colunas-lista');
                if (listaG2) listaG2.classList.remove('arrastando');
                arrasteGrupo = null;
                if (!ativoG) {
                    colunasDragou = false;
                    return;
                }
                e.preventDefault();
                soltarGrupoNaMarca(idG);
                setTimeout(function () { colunasDragou = false; }, 80);
                return;
            }
            if (!arrasteColuna || e.pointerId !== arrasteColuna.pointerId) return;
            var ativo = arrasteColuna.ativo;
            var codigo = arrasteColuna.codigo;
            if (arrasteColuna.chip) arrasteColuna.chip.classList.remove('dragging');
            var listaAtiva = document.getElementById('bw-colunas-lista');
            if (listaAtiva) listaAtiva.classList.remove('arrastando');
            arrasteColuna = null;
            colunasDrag = null;
            if (!ativo) {
                colunasDragou = false;
                if (!e.target.closest('.bw-col-x')) {
                    var chipClique = e.target.closest('#bw-colunas-lista .bw-col-chip');
                    if (chipClique && chipClique.getAttribute('data-codigo') === codigo) {
                        var caixaChip = chipClique.closest('.bw-linha-caixa');
                        if (caixaChip) {
                            selecionarTituloPeriodo(
                                caixaChip.getAttribute('data-linha-id') || '',
                                caixaChip.getAttribute('data-linha-nome') || ''
                            );
                        }
                        // Bloco de cálculo (MU / Resultado Final): clique abre o editor.
                        if (chipClique.classList.contains('calc') || ehCodigoBlocoCalc(codigo)) {
                            abrirEdicaoBlocoCalc(codigo);
                            return;
                        }
                        if (colunasMarcadas[codigo]) delete colunasMarcadas[codigo];
                        else colunasMarcadas[codigo] = true;
                        avisoLinha = null;
                        renderRevisarDinamico();
                    }
                }
                return;
            }
            e.preventDefault();
            soltarColunaNaMarca(codigo);
            setTimeout(function () { colunasDragou = false; }, 80);
        });
        bodyEl.addEventListener('pointercancel', function (e) {
            if (arrasteGrupo && e.pointerId === arrasteGrupo.pointerId) {
                limparMarcaColuna();
                if (arrasteGrupo.caixa) arrasteGrupo.caixa.classList.remove('dragging');
                var listaGC = document.getElementById('bw-colunas-lista');
                if (listaGC) listaGC.classList.remove('arrastando');
                arrasteGrupo = null;
                setTimeout(function () { colunasDragou = false; }, 80);
                return;
            }
            if (!arrasteColuna || e.pointerId !== arrasteColuna.pointerId) return;
            limparMarcaColuna();
            if (arrasteColuna.chip) arrasteColuna.chip.classList.remove('dragging');
            var listaAtiva = document.getElementById('bw-colunas-lista');
            if (listaAtiva) listaAtiva.classList.remove('arrastando');
            arrasteColuna = null;
            colunasDrag = null;
            setTimeout(function () { colunasDragou = false; }, 80);
        });
        bodyEl.addEventListener('focusin', function (e) {
            if (!e.target || !e.target.classList || !e.target.classList.contains('bw-linha-rotulo')) return;
            editandoCabecalho = true;
            var caixa = e.target.closest('.bw-linha-caixa');
            if (caixa) {
                selecionarTituloPeriodo(
                    caixa.getAttribute('data-linha-id') || '',
                    caixa.getAttribute('data-linha-nome') || String(e.target.textContent || '').trim()
                );
                colunasMarcadas = {};
                caixa.querySelectorAll('.bw-col-chip').forEach(function (chip) {
                    var c = chip.getAttribute('data-codigo');
                    if (c) colunasMarcadas[c] = true;
                });
                nomeLinhaRascunho = String(e.target.textContent || '').trim();
                var campo = document.getElementById('bw-linha-nome');
                if (campo) campo.value = nomeLinhaRascunho;
            }
        });
        bodyEl.addEventListener('focusout', function (e) {
            if (!e.target || !e.target.classList || !e.target.classList.contains('bw-linha-rotulo')) return;
            var nome = String(e.target.textContent || '').trim().slice(0, 40);
            var id = e.target.getAttribute('data-linha-id') || '';
            var nomeAntigo = '';
            var caixa = e.target.closest('.bw-linha-caixa');
            if (caixa) nomeAntigo = String(caixa.getAttribute('data-linha-nome') || '').trim();
            editandoCabecalho = false;
            if (!nome) {
                e.target.textContent = nomeAntigo || 'Título do período';
                return;
            }
            if (nome === nomeAntigo) return;
            aplicarNomeCabecalho(id || nomeAntigo, nome);
        });
        bodyEl.addEventListener('keydown', function (e) {
            if (!e.target || !e.target.classList || !e.target.classList.contains('bw-linha-rotulo')) return;
            if (e.key === 'Enter') {
                e.preventDefault();
                e.target.blur();
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                var caixaEsc = e.target.closest('.bw-linha-caixa');
                e.target.textContent = caixaEsc ? String(caixaEsc.getAttribute('data-linha-nome') || '') : e.target.textContent;
                editandoCabecalho = false;
                e.target.blur();
            }
        });
    }

    function posicionarMarcaGrupo(x, y) {
        var lista = document.getElementById('bw-colunas-lista');
        if (!lista || !arrasteGrupo) return;
        var marca = document.getElementById('bw-col-marca');
        if (!marca) {
            marca = document.createElement('span');
            marca.id = 'bw-col-marca';
            marca.className = 'bw-col-marca';
            marca.setAttribute('aria-hidden', 'true');
        }
        var destino = null;
        var melhor = Infinity;
        lista.querySelectorAll('.bw-linha-caixa').forEach(function (el) {
            if (el === arrasteGrupo.caixa) return;
            var r = el.getBoundingClientRect();
            var d = Math.abs(y - (r.top + r.height / 2)) * 2 + Math.abs(x - (r.left + r.width / 2));
            if (d < melhor) {
                melhor = d;
                destino = el;
            }
        });
        if (!destino) {
            lista.appendChild(marca);
            return;
        }
        var box = destino.getBoundingClientRect();
        var antes = x < box.left + box.width / 2;
        if (antes) {
            if (marca.nextSibling !== destino) lista.insertBefore(marca, destino);
        } else if (destino.nextSibling !== marca) {
            lista.insertBefore(marca, destino.nextSibling);
        }
    }

    function limparMarcaColuna() {
        var marca = document.getElementById('bw-col-marca');
        if (marca && marca.parentNode) marca.parentNode.removeChild(marca);
    }

    function posicionarMarcaColuna(x, y) {
        var lista = document.getElementById('bw-colunas-lista');
        if (!lista || !arrasteColuna) return;
        var marca = document.getElementById('bw-col-marca');
        if (!marca) {
            marca = document.createElement('span');
            marca.id = 'bw-col-marca';
            marca.className = 'bw-col-marca';
            marca.setAttribute('aria-hidden', 'true');
        }
        var destino = null;
        var melhor = Infinity;
        lista.querySelectorAll('.bw-col-chip').forEach(function (el) {
            if (el === arrasteColuna.chip) return;
            var cod = el.getAttribute('data-codigo') || '';
            if (!cod || cod === '_semanal' || cod === 'media_sem') return;
            var r = el.getBoundingClientRect();
            var d = Math.abs(y - (r.top + r.height / 2)) * 3 + Math.abs(x - (r.left + r.width / 2));
            if (d < melhor) {
                melhor = d;
                destino = el;
            }
        });
        // Preferir soltar dentro do cabeçalho sob o ponteiro (cálculo / chip entra na faixa).
        // Hit-area generosa: padding extra e prioridade à zona .bw-linha-cols.
        var caixaSob = null;
        var melhorCaixa = Infinity;
        lista.querySelectorAll('.bw-linha-caixa').forEach(function (cx) {
            var zona = cx.querySelector('.bw-linha-cols') || cx;
            var rZona = zona.getBoundingClientRect();
            var rCx = cx.getBoundingClientRect();
            var pad = 18;
            var dentroZona = x >= rZona.left - pad && x <= rZona.right + pad && y >= rZona.top - pad && y <= rZona.bottom + pad;
            var dentroCx = x >= rCx.left - 10 && x <= rCx.right + 10 && y >= rCx.top - 10 && y <= rCx.bottom + 10;
            if (!dentroZona && !dentroCx) return;
            var alvo = dentroZona ? rZona : rCx;
            var d = Math.abs(y - (alvo.top + alvo.height / 2)) + Math.abs(x - (alvo.left + alvo.width / 2)) * 0.2;
            if (dentroZona) d -= 40;
            if (d < melhorCaixa) {
                melhorCaixa = d;
                caixaSob = cx;
            }
        });
        if (caixaSob) {
            var colsBox = caixaSob.querySelector('.bw-linha-cols') || caixaSob;
            if (destino && colsBox.contains(destino)) {
                var boxChip = destino.getBoundingClientRect();
                var antesChip = x < boxChip.left + boxChip.width / 2;
                if (antesChip) {
                    if (marca.nextSibling !== destino) colsBox.insertBefore(marca, destino);
                } else if (destino.nextSibling !== marca) {
                    colsBox.insertBefore(marca, destino.nextSibling);
                }
                return;
            }
            // Cabeçalho vazio ou soltou na área do título — coloca no fim das colunas do grupo.
            colsBox.appendChild(marca);
            return;
        }
        if (!destino) {
            lista.appendChild(marca);
            return;
        }
        var pai = destino.parentNode || lista;
        var box = destino.getBoundingClientRect();
        var antes = x < box.left + box.width / 2;
        if (antes) {
            if (marca.nextSibling !== destino) pai.insertBefore(marca, destino);
        } else if (destino.nextSibling !== marca) {
            pai.insertBefore(marca, destino.nextSibling);
        }
    }

    function soltarColunaNaMarca(codigo) {
        var lista = document.getElementById('bw-colunas-lista');
        var marca = document.getElementById('bw-col-marca');
        if (!lista || !codigo || !estado) {
            limparMarcaColuna();
            return;
        }
        codigo = String(codigo || '').toLowerCase();
        var caixaDestino = marca ? marca.closest('.bw-linha-caixa') : null;
        var nomeCab = '';
        var linhaId = '';
        var colsNoCab = [];
        if (caixaDestino) {
            var rot = caixaDestino.querySelector('.bw-linha-rotulo');
            nomeCab = String((rot && rot.textContent) || caixaDestino.getAttribute('data-linha-nome') || '').trim();
            linhaId = String(caixaDestino.getAttribute('data-linha-id') || '');
            // Ordem real do drop dentro do título (não jogar a coluna pro fim).
            var zona = caixaDestino.querySelector('.bw-linha-cols') || caixaDestino;
            zona.querySelectorAll('.bw-col-chip, #bw-col-marca').forEach(function (el) {
                if (el === marca || el.id === 'bw-col-marca') {
                    if (colsNoCab.indexOf(codigo) < 0) colsNoCab.push(codigo);
                    return;
                }
                var cZ = String(el.getAttribute('data-codigo') || '').toLowerCase();
                if (!cZ || cZ === codigo || cZ === '_semanal' || cZ === 'media_sem') return;
                if (colsNoCab.indexOf(cZ) < 0) colsNoCab.push(cZ);
            });
        }
        var ordem = [];
        var visto = {};
        lista.querySelectorAll('.bw-col-chip, #bw-col-marca').forEach(function (el) {
            if (el === marca || el.id === 'bw-col-marca') {
                if (!visto[codigo]) {
                    ordem.push(codigo);
                    visto[codigo] = true;
                }
                return;
            }
            var c = String(el.getAttribute('data-codigo') || '').toLowerCase();
            if (!c || c === codigo || c === '_semanal' || c === 'media_sem' || visto[c]) return;
            visto[c] = true;
            ordem.push(c);
        });
        limparMarcaColuna();
        if (!visto[codigo]) ordem.push(codigo);
        estado.colunas_ordem = ordem;

        if (!Array.isArray(estado.linhas_layout)) estado.linhas_layout = [];
        estado.linhas_layout = estado.linhas_layout.map(function (lin) {
            if (!lin) return lin;
            lin.colunas = (lin.colunas || []).filter(function (c) {
                return String(c).toLowerCase() !== codigo;
            });
            return lin;
        }).filter(function (lin) { return lin && String(lin.nome || '').trim(); });

        if (nomeCab) {
            var destino = null;
            estado.linhas_layout.forEach(function (lin) {
                if (!lin) return;
                if (linhaId && String(lin.id) === String(linhaId)) destino = lin;
                else if (!destino && String(lin.nome || '') === nomeCab) destino = lin;
            });
            if (!destino) {
                destino = { id: String(linhaId || idLinhaPorNome(nomeCab)), nome: nomeCab, colunas: [] };
                estado.linhas_layout.push(destino);
            }
            destino.colunas = colsNoCab.length ? colsNoCab.slice() : destino.colunas.concat([codigo]);
            if (destino.colunas.indexOf(codigo) < 0) destino.colunas.push(codigo);
            estado.cabecalhos_manuais = 1;
            estado.quadro_chips_manuais = 1;
            compactarColunasOrdem(destino.colunas.slice());
            gravarLinhaNosComponentes(destino.colunas.slice());
        } else {
            gravarLinhaNosComponentes([codigo]);
        }

        aplicarOrdemNoPreview(previewAtual);
        renderRevisarDinamico();
        agendarMontar();
    }

    function soltarGrupoNaMarca(linhaId) {
        var lista = document.getElementById('bw-colunas-lista');
        var marca = document.getElementById('bw-col-marca');
        if (!lista || !linhaId || !estado) {
            limparMarcaColuna();
            return;
        }
        var caixas = Array.prototype.slice.call(lista.querySelectorAll('.bw-linha-caixa'));
        var movida = null;
        caixas.forEach(function (cx) {
            if (String(cx.getAttribute('data-linha-id') || '') === String(linhaId)) movida = cx;
        });
        if (!movida || !marca) {
            limparMarcaColuna();
            return;
        }
        if (marca.parentNode === lista) {
            lista.insertBefore(movida, marca);
        } else {
            var caixaMarca = marca.closest('.bw-linha-caixa');
            if (caixaMarca && caixaMarca !== movida) {
                var antes = marca.compareDocumentPosition(caixaMarca) & Node.DOCUMENT_POSITION_FOLLOWING;
                if (antes) lista.insertBefore(movida, caixaMarca);
                else lista.insertBefore(movida, caixaMarca.nextSibling);
            }
        }
        limparMarcaColuna();
        estado.colunas_ordem = coletarOrdemChipsDoDom();
        sincronizarLinhasLayoutComDom();
        estado.cabecalhos_manuais = 1;
        // Mantém colunas de cada cabeçalho juntas — o Exemplo só fecha a faixa se estiverem lado a lado.
        (estado.linhas_layout || []).forEach(function (lin) {
            if (lin && (lin.colunas || []).length >= 1) compactarColunasOrdem(lin.colunas);
        });
        renderRevisarDinamico();
        agendarMontar();
    }

    function criarLinhaLayout() {
        if (!estado) return;
        var nome = String(nomeLinhaRascunho || '').trim();
        if (!nome) return;
        var ordem = (estado.colunas_ordem || []).map(function (c) { return String(c || '').toLowerCase(); });
        var cols = codigosMarcados().map(function (c) { return String(c).toLowerCase(); });
        cols.sort(function (a, b) {
            var ia = ordem.indexOf(a);
            var ib = ordem.indexOf(b);
            if (ia < 0) ia = 999;
            if (ib < 0) ib = 999;
            return ia - ib;
        });
        if (!Array.isArray(estado.linhas_layout)) estado.linhas_layout = [];
        if (cols.length) {
            estado.linhas_layout = estado.linhas_layout.map(function (lin) {
                if (!lin) return lin;
                lin.colunas = (lin.colunas || []).filter(function (c) { return cols.indexOf(c) < 0; });
                return lin;
            }).filter(function (lin) { return lin && String(lin.nome || '').trim(); });
        }
        var novoId = 'lin' + Date.now().toString(36);
        estado.linhas_layout.push({ id: novoId, nome: nome.slice(0, 40), colunas: cols.slice() });
        estado.cabecalhos_manuais = 1;
        estado.quadro_chips_manuais = 1;
        estado.limpar_titulos_periodo = 0;
        if (!estado.titulos_periodo_ok || typeof estado.titulos_periodo_ok !== 'object') {
            estado.titulos_periodo_ok = {};
        }
        delete estado.titulos_periodo_ok[novoId];
        if (cols.length) {
            compactarColunasOrdem(cols);
            sincronizarOrdemComLinhasLayout();
            gravarLinhaNosComponentes(cols);
        }
        selecionarTituloPeriodo(novoId, nome.slice(0, 40));
        if (cols.length) {
            var pvNomes = {};
            (((previewEfetivo() || {}).colunas) || []).forEach(function (c) {
                if (c && c.codigo) pvNomes[String(c.codigo).toLowerCase()] = c.nome || '';
            });
            var nomesCols = cols.map(function (c) { return tituloColuna(c) || pvNomes[c] || c; }).join(', ');
            avisoLinha = {
                tipo: 'ok',
                texto: 'Título do período “' + nome + '” em cima de ' + nomesCols + '. Clique em Confirmar quando terminar.'
            };
        } else {
            avisoLinha = {
                tipo: 'ok',
                texto: 'Título “' + nome + '” selecionado. Use Adicionar todos na lateral ou arraste os eventos para dentro dele.'
            };
        }
        colunasMarcadas = {};
        nomeLinhaRascunho = '';
        renderRevisarDinamico();
        var btnCriar = document.getElementById('bw-criar-linha');
        if (btnCriar) {
            btnCriar.classList.add('bw-btn-ok-flash');
            btnCriar.textContent = 'Criado ✓';
            setTimeout(function () {
                if (!btnCriar.isConnected) return;
                btnCriar.classList.remove('bw-btn-ok-flash');
                btnCriar.textContent = 'Novo título do período';
            }, 1200);
        }
        var listaTitulos = document.getElementById('bw-colunas-lista');
        if (listaTitulos && listaTitulos.scrollIntoView) {
            listaTitulos.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        var campoNome = document.getElementById('bw-linha-nome');
        if (campoNome && campoNome.focus) campoNome.focus();
        agendarMontar();
    }

    function tirarLinhaLayout() {
        if (!estado || !Array.isArray(estado.linhas_layout)) return;
        var cols = codigosMarcados().map(function (c) { return String(c).toLowerCase(); });
        if (!cols.length) {
            limparTodosCabecalhos();
            return;
        }
        estado.linhas_layout = estado.linhas_layout.map(function (lin) {
            if (!lin) return lin;
            lin.colunas = (lin.colunas || []).filter(function (c) { return cols.indexOf(c) < 0; });
            return lin;
        }).filter(function (lin) { return lin && String(lin.nome || '').trim(); });
        estado.cabecalhos_manuais = 1;
        avisoLinha = { tipo: 'ok', texto: 'Essas colunas saíram do título do período e voltam a aparecer sozinhas.' };
        colunasMarcadas = {};
        gravarLinhaNosComponentes(cols);
        renderRevisarDinamico();
        agendarMontar();
    }

    function limparTodosCabecalhos() {
        if (!estado) return;
        var todos = [];
        (estado.linhas_layout || []).forEach(function (lin) {
            (lin.colunas || []).forEach(function (c) {
                c = String(c || '').toLowerCase();
                if (c && todos.indexOf(c) < 0) todos.push(c);
            });
        });
        estado.linhas_layout = [];
        estado.cabecalhos_manuais = 1;
        estado.titulos_periodo_ok = {};
        estado.limpar_titulos_periodo = 1;
        colunasMarcadas = {};
        tituloPeriodoAtivo = { id: '', nome: '' };
        clonePainelLinhaId = '';
        clonePainelNome = '';
        clonePainelChave = '';
        if (todos.length) gravarLinhaNosComponentes(todos);
        avisoLinha = { tipo: 'ok', texto: 'Todos os títulos do período foram removidos. Monte do zero: marque colunas e use Novo título do período.' };
        renderRevisarDinamico();
        agendarMontar();
    }

    function removerCabecalhoPorId(linhaId) {
        if (!estado || !Array.isArray(estado.linhas_layout)) return;
        linhaId = String(linhaId || '');
        var cols = [];
        estado.linhas_layout = estado.linhas_layout.filter(function (lin) {
            if (!lin) return false;
            var id = String(lin.id || '');
            var nome = String(lin.nome || '');
            var bate = id === linhaId || ('cab:' + nome.toLowerCase()) === linhaId || nome === linhaId;
            if (!bate) return true;
            (lin.colunas || []).forEach(function (c) {
                c = String(c || '').toLowerCase();
                if (c && cols.indexOf(c) < 0) cols.push(c);
            });
            return false;
        });
        estado.cabecalhos_manuais = 1;
        if (estado.titulos_periodo_ok && typeof estado.titulos_periodo_ok === 'object') {
            delete estado.titulos_periodo_ok[linhaId];
        }
        if (tituloPeriodoAtivo && (
            String(tituloPeriodoAtivo.id || '') === String(linhaId || '')
            || String(tituloPeriodoAtivo.nome || '') === String(linhaId || '')
        )) {
            tituloPeriodoAtivo = { id: '', nome: '' };
        }
        if (String(clonePainelLinhaId || '') === String(linhaId || '')) {
            clonePainelLinhaId = '';
            clonePainelNome = '';
            clonePainelChave = '';
        }
        if (cols.length) gravarLinhaNosComponentes(cols);
        avisoLinha = { tipo: 'ok', texto: 'Título do período removido. As colunas ficaram soltas.' };
        renderRevisarDinamico();
        agendarMontar();
    }

    function aplicarRascunhoNoWizard(r) {
        if (!r || typeof r !== 'object') return;
        garantirEstado();
        estado.origem = 'chat';
        estado.rascunho_preservado = r;
        estado.passo = 'revisar';
        estado.materia_unica = materiaUnicaDoRascunho(r);
        estado.materia_unica_modo = materiaUnicaModoDoRascunho(r);
        estado.materia_unica_tocada = false;
        aplicarMateriaUnicaNasPecas();
        if (r.nome) {
            estado.nome = r.nome;
            estado.nome_automatico = false;
        }
        if (r.bimestre != null) estado.bimestre = r.bimestre;
        if (r.ano_letivo) estado.ano_letivo = r.ano_letivo;
        if (Number(r.boletim_id || 0) > 0) estado.boletim_id = Number(r.boletim_id);
        if (Number(r.boletim_id || 0) > 0) {
            var bolR = (catalogo.boletins || []).filter(function (b) { return Number(b.id) === Number(r.boletim_id); })[0];
            if (bolR) {
                estado.nota_minima_aprovacao = bolR.nota_minima_aprovacao != null ? bolR.nota_minima_aprovacao : 6;
                estado.nota_minima_origem = bolR.minima_origem === 'regra' ? 'regra' : 'padrao';
                estado.nota_minima_regra_nome = estado.nota_minima_origem === 'regra' ? (bolR.regra_nome || '') : '';
            }
        } else if (!(Number(r.regra_id || 0) > 0)) {
            estado.nota_minima_aprovacao = 6;
            estado.nota_minima_origem = 'padrao';
            estado.nota_minima_regra_nome = '';
        } else if (r.nota_minima_aprovacao != null && r.nota_minima_aprovacao !== '') {
            estado.nota_minima_aprovacao = Number(r.nota_minima_aprovacao);
            estado.nota_minima_origem = r.nota_minima_origem || 'evento';
            estado.nota_minima_regra_nome = r.nota_minima_regra_nome || '';
        }
        r.nota_minima_aprovacao = estado.nota_minima_aprovacao;
        r.nota_minima_origem = estado.nota_minima_origem;
        r.nota_minima_regra_nome = estado.nota_minima_regra_nome;
        if (Number(r.grupo_regras_notas_id || 0) > 0) estado.grupo_regras_notas_id = Number(r.grupo_regras_notas_id);
        if (Number(r.regra_id || 0) > 0) {
            estado.regra_id = Number(r.regra_id);
            estado.modo = 'editar';
        }
        if (Array.isArray(r.series_ids)) {
            estado.series_ids = r.series_ids.map(Number).filter(function (id) { return id > 0; });
        }
        if (Array.isArray(r.turmas_ids)) {
            estado.turmas_ids = r.turmas_ids.map(Number).filter(function (id) { return id > 0; });
        }
        if (Array.isArray(r.materias_ids)) {
            estado.materias_ids = r.materias_ids.map(Number).filter(function (id) { return id > 0; });
        }
        if (r.default_data_inicio) estado.data_inicio = String(r.default_data_inicio).slice(0, 10);
        if (r.default_data_fim) estado.data_fim = String(r.default_data_fim).slice(0, 10);
        var inf = inferirPecasDoRascunho(r);
        if (inf.pecas.length) {
            estado.pecas = inf.pecas;
            estado.pecas_opcoes = inf.pecas_opcoes;
        }
        rascunhoAtual = r;
        aplicarRascunho(r);
        renderAll();
        atualizarCabecalhoEvento();
        renderResumo(
            'Ajuste do chat aplicado no formulário.\n\nRevise e salve o evento quando estiver ok.',
            []
        );
    }

    function setCheckboxes(name, ids) {
        var set = {};
        (ids || []).forEach(function (id) { set[String(id)] = true; });
        document.querySelectorAll('input[name="' + name + '"]').forEach(function (el) {
            el.checked = !!set[el.value];
        });
    }

    function normalizarComponentesRascunho(compsNovos) {
        compsNovos = Array.isArray(compsNovos) ? compsNovos : [];
        return compsNovos.map(function (c, idx) {
            var blocos = Array.isArray(c.blocos_ids) ? c.blocos_ids : [];
            var cfg = (c.config && typeof c.config === 'object') ? Object.assign({}, c.config) : {};
            if (c.expressao && !cfg.expressao) cfg.expressao = c.expressao;
            if ((c.source_type || '') === 'calculado' && !cfg.formula_mode) {
                var fm = cfg.formula_materias;
                cfg.formula_mode = (fm && typeof fm === 'object' && Object.keys(fm).length) ? 'per_materia' : 'single';
            }
            return {
                codigo: c.codigo || ('comp' + (idx + 1)),
                nome: c.nome || c.codigo || ('Componente ' + (idx + 1)),
                source_type: c.source_type || 'provas_sistema',
                calc_type: c.calc_type || 'media',
                peso: c.peso != null ? c.peso : 1,
                filtro_titulo: c.filtro_titulo || '',
                bloco_id: blocos[0] || c.bloco_id || null,
                blocos_ids: blocos,
                materias_ids: Array.isArray(c.materias_ids) ? c.materias_ids : [],
                materia_unica: c.materia_unica ? 1 : 0,
                materia_unica_modo: c.materia_unica_modo === 'media' ? 'media' : 'soma',
                usar_percentual: c.usar_percentual ? 1 : 0,
                escala_max: c.escala_max != null ? c.escala_max : 10,
                obrigatorio: c.obrigatorio ? 1 : 0,
                config: cfg
            };
        });
    }

    function aplicarRascunho(r) {
        if (!r || typeof r !== 'object') return;
        var nomeEl = document.getElementById('regra-nome');
        if (nomeEl && r.nome) nomeEl.value = r.nome;
        var codEl = document.getElementById('regra-codigo');
        if (codEl && r.codigo) {
            codEl.readOnly = false;
            codEl.value = r.codigo;
        }
        var descEl = document.getElementById('regra-descricao-curta');
        if (descEl && r.descricao_curta != null) descEl.value = r.descricao_curta;
        var formulaEl = document.querySelector('[name="formula_final"]');
        if (formulaEl && r.formula_final != null) formulaEl.value = r.formula_final;
        var roundEl = document.querySelector('[name="round_mode"]');
        if (roundEl && r.round_mode) roundEl.value = r.round_mode;
        if (r.decimal_places != null) {
            estado.decimal_places = Number(r.decimal_places) === 1 ? 1 : 2;
        }
        var decEl = document.querySelector('[name="decimal_places"]');
        if (decEl && estado.decimal_places) {
            decEl.value = String(estado.decimal_places);
        }
        var exibirEl = document.querySelector('[name="exibir_em"]');
        if (exibirEl && r.exibir_em) {
            if (exibirEl.tagName === 'SELECT' || exibirEl.type === 'text' || exibirEl.type === 'hidden') {
                exibirEl.value = r.exibir_em;
            } else {
                document.querySelectorAll('input[name="exibir_em"]').forEach(function (el) {
                    el.checked = el.value === r.exibir_em;
                });
            }
        }
        var bolEl = document.getElementById('regra-boletim-id') || document.querySelector('[name="boletim_id"]');
        if (bolEl && r.boletim_id) bolEl.value = String(r.boletim_id);
        var quadroEl = document.getElementById('grupo-regras-notas-id') || document.querySelector('[name="grupo_regras_notas_id"]');
        if (quadroEl && r.grupo_regras_notas_id) quadroEl.value = String(r.grupo_regras_notas_id);
        var finEl = document.querySelector('[name="finalidade"]');
        if (finEl && r.finalidade) {
            if (finEl.tagName === 'SELECT' || finEl.type === 'hidden' || finEl.type === 'text') {
                finEl.value = r.finalidade === 'complementar' ? 'complementar' : 'oficial';
            }
        }
        var anoEl = document.querySelector('[name="ano_letivo"]') || document.getElementById('regra-ano-letivo');
        if (anoEl && r.ano_letivo) anoEl.value = r.ano_letivo;
        var bimEl = document.querySelector('[name="bimestre"]') || document.getElementById('regra-bimestre');
        if (bimEl) {
            if (r.bimestre != null && r.bimestre !== '') bimEl.value = r.bimestre;
            else if (r.exibir_em === 'boletim') bimEl.value = '';
        }
        var diEl = document.querySelector('[name="default_data_inicio"]');
        if (diEl && r.default_data_inicio) diEl.value = r.default_data_inicio;
        var dfEl = document.querySelector('[name="default_data_fim"]');
        if (dfEl && r.default_data_fim) dfEl.value = r.default_data_fim;
        var notaEl = document.querySelector('[name="nota_minima_aprovacao"]');
        if (notaEl && r.nota_minima_aprovacao != null) notaEl.value = r.nota_minima_aprovacao;
        var usarRes = document.querySelector('[name="usar_resultado_aprovacao"]');
        if (usarRes && r.usar_resultado_aprovacao != null) usarRes.checked = !!Number(r.usar_resultado_aprovacao);
        if (Array.isArray(r.turmas_ids)) setCheckboxes('turmas_ids[]', r.turmas_ids);
        if (Array.isArray(r.materias_ids)) setCheckboxes('materias_ids[]', r.materias_ids);
        if (Array.isArray(r.series_ids)) setCheckboxes('series_ids[]', r.series_ids);

        var regraIdInput = document.querySelector('#form-regra-boletim input[name="regra_id"]');
        if (regraIdInput) {
            if (r.modo === 'editar' && r.regra_id) regraIdInput.value = String(r.regra_id);
            else if (r.modo === 'criar') regraIdInput.value = '0';
        }

        var normalized = normalizarComponentesRascunho(r.componentes);
        var jsonEl = document.getElementById('componentes-json');
        if (jsonEl) jsonEl.value = JSON.stringify(normalized);
        if (typeof window.boletimRenderComponentes === 'function') {
            window.boletimRenderComponentes(normalized);
        } else if (typeof window.renderComponentesLista === 'function') {
            window.renderComponentesLista(normalized);
        } else {
            document.dispatchEvent(new CustomEvent('boletim:assistente-rascunho', { detail: { componentes: normalized, rascunho: r } }));
        }
    }
    window.boletimAplicarRascunhoAssistente = aplicarRascunho;

    function copiarTexto(texto) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(texto);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = texto;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); resolve(); } catch (e) { reject(e); }
            document.body.removeChild(ta);
        });
    }

    function appendBubble(role, text) {
        if (!msgsEl) {
            if (role === 'assistant') {
                renderResumo(text || 'Pronto.', []);
            }
            return null;
        }
        var wrap = document.createElement('div');
        wrap.className = role === 'user'
            ? 'ml-6 bg-indigo-600 text-white rounded-lg px-3 py-2 whitespace-pre-wrap'
            : 'mr-2 bg-white border border-gray-200 text-gray-800 rounded-lg px-3 py-2 whitespace-pre-wrap';
        wrap.textContent = text || '';
        msgsEl.appendChild(wrap);
        msgsEl.scrollTop = msgsEl.scrollHeight;
        return wrap;
    }

    function ehBoletimComposto() {
        if (!estado) return false;
        if (estado.origem === 'quadro-impressao' || estado.origem === 'clonar' || estado.quadro_livre) {
            return false;
        }
        return estado.exibir_em === 'boletim';
    }

    /** Visão de impressão do boletim: builder completo (colunas, arraste, fórmulas, cabeçalhos). */
    function forcarContextoImpressaoBoletim() {
        if (!estado) return;
        var layoutId = Number(root.getAttribute('data-layout-boletim-id') || 0);
        var veioDoBoletim = retornoFluxo === 'boletim-layout' || layoutId > 0;
        if (!veioDoBoletim) return;
        estado.exibir_em = 'boletim';
        estado.bimestre = 0;
        if (layoutId > 0) {
            estado.layout_boletim_id = layoutId;
            if (Number(estado.boletim_id || 0) === layoutId) estado.boletim_id = 0;
        }
        if (!estado.modelo_santa_maria) {
            estado.quadro_livre = 1;
            estado.origem = 'quadro-impressao';
            estado.cabecalhos_manuais = 1;
            zerarQuadroColunasSeAutomatico();
        } else {
            estado.origem = 'santa-maria';
            estado.quadro_livre = 0;
        }
        if (!Array.isArray(estado.demonstrativos_ids)) estado.demonstrativos_ids = [];
        if (!Array.isArray(estado.eventos_avaliativos_ids)) estado.eventos_avaliativos_ids = [];
        garantirMapaSubsEventosAvulsos();
        // Boletim impressão não usa mais Quadro de Notas.
        estado.grupo_regras_notas_id = 0;
    }

    function ehVisaoImpressaoEditavel() {
        if (!estado) return false;
        forcarContextoImpressaoBoletim();
        if (ehQuadroImpressaoLivre() || ehModeloSantaMaria()) return true;
        if (Number(estado.layout_boletim_id || 0) > 0 && estado.exibir_em === 'boletim') return true;
        if (retornoFluxo === 'boletim-layout' && estado.exibir_em === 'boletim') return true;
        if (retornoFluxo === 'boletim-layout') return true;
        return false;
    }

    function passosVisiveis() {
        var vis;
        if (ehVisaoImpressaoEditavel()) {
            vis = PASSOS.filter(function (p) {
                return ['identidade', 'pecas', 'agrupar', 'formula', 'revisar'].indexOf(p.id) >= 0;
            });
        } else if (ehBoletimComposto()) {
            vis = PASSOS.filter(function (p) {
                return ['inicio', 'identidade', 'publico', 'revisar'].indexOf(p.id) >= 0;
            });
        } else {
            vis = PASSOS.slice();
        }
        if (estado && estado.modo === 'editar' && estado.origem === 'formulario' && !ehVisaoImpressaoEditavel()) {
            vis = vis.filter(function (p) {
                return p.id !== 'inicio';
            });
        }
        if (ehVisaoImpressaoEditavel()) {
            vis = vis.map(function (p) {
                if (p.id === 'pecas') {
                    return {
                        id: p.id,
                        label: '2. Eventos',
                        sub: 'por ' + String(rotuloCampoPeriodo() || 'período').toLocaleLowerCase('pt-BR')
                    };
                }
                if (p.id === 'agrupar') return { id: p.id, label: '3. Agrupar', sub: 'Linha' };
                if (p.id === 'formula') return { id: p.id, label: '4. Fórmula', sub: 'Colunas' };
                if (p.id === 'revisar') return { id: p.id, label: '5. Revisão', sub: 'Confirmar' };
                if (p.id === 'identidade') return { id: p.id, label: '1. Identidade', sub: 'Evento' };
                return p;
            });
        } else if (usaEventosAvaliativosNaNota()) {
            vis = vis.map(function (p) {
                if (p.id !== 'pecas') return p;
                return { id: p.id, label: '3. Eventos', sub: 'Avaliativos' };
            });
        }
        if (ehDemonstrativoNotas()) {
            vis = vis.map(function (p, idx) {
                if (p.id !== 'formula') return p;
                return {
                    id: p.id,
                    label: (idx + 1) + '. Colunas',
                    sub: usaEventosAvaliativosNaNota() ? 'Eventos' : 'Montagem'
                };
            });
        }
        return vis;
    }

    function demonstrativosMarcados() {
        var ids = (estado && estado.demonstrativos_ids) || [];
        return ids.map(Number).filter(function (id) { return id > 0; });
    }

    function eventosAvaliativosMarcados() {
        var ids = (estado && estado.eventos_avaliativos_ids) || [];
        return ids.map(Number).filter(function (id) { return id > 0; });
    }

    function chavePeriodoItem(item) {
        var bim = Number((item && item.bimestre) || 0);
        if (bim >= 60 && bim <= 71) return 'rec-bim-' + (bim - 60);
        if (bim >= 80 && bim <= 82) return 'rec-sem-' + (bim - 80);
        if (bim === 90) return 'rec-ano';
        if (periodoNumeroValido(bim)) return 'p-' + bim;
        if (bim > 0) return 'outro-' + bim;
        return 'ano';
    }

    function rotuloPeriodoEventoAvaliativo(ev) {
        var bim = Number((ev && ev.bimestre) || 0);
        var ano = Number((ev && ev.ano_letivo) || (estado && estado.ano_letivo) || 0);
        if (bim >= 60 && bim <= 71) {
            var nBim = bim - 60;
            return 'Recuperação · ' + (periodoNumeroValido(nBim, ano) ? rotuloPeriodoNumero(nBim, ano) : (nBim + 'º ' + rotuloCampoPeriodo(ano)));
        }
        if (bim >= 80 && bim <= 82) return 'Recuperação · ' + (bim - 80) + 'º semestre';
        if (bim === 90) return 'Recuperação Final';
        if (periodoNumeroValido(bim, ano)) return rotuloPeriodoNumero(bim, ano);
        if (bim > 0) return 'Período ' + bim;
        return 'Ano letivo';
    }

    function ordemChavePeriodo(chave) {
        if (chave === 'ano') return 9000;
        if (chave.indexOf('p-') === 0) return Number(chave.slice(2)) || 0;
        if (chave.indexOf('rec-bim-') === 0) return 1000 + (Number(chave.slice(8)) || 0);
        if (chave.indexOf('rec-sem-') === 0) return 2000 + (Number(chave.slice(8)) || 0);
        if (chave === 'rec-ano') return 3000;
        if (chave.indexOf('outro-') === 0) return 8000 + (Number(chave.slice(6)) || 0);
        return 9500;
    }

    function agruparItensPorPeriodo(itens) {
        var grupos = {};
        (itens || []).forEach(function (item) {
            if (!item) return;
            var chave = chavePeriodoItem(item);
            if (!grupos[chave]) {
                grupos[chave] = {
                    chave: chave,
                    rotulo: rotuloPeriodoEventoAvaliativo(item),
                    itens: []
                };
            }
            grupos[chave].itens.push(item);
        });
        return Object.keys(grupos).map(function (k) { return grupos[k]; }).sort(function (a, b) {
            return ordemChavePeriodo(a.chave) - ordemChavePeriodo(b.chave);
        });
    }

    function eventosAvaliativosDoAnoQuadro() {
        var ano = Number((estado && estado.ano_letivo) || 0);
        return ((catalogo && catalogo.eventos_avaliativos) || []).filter(function (ev) {
            if (!ev) return false;
            if (ano > 0 && Number(ev.ano_letivo || 0) !== ano) return false;
            return Number(ev.id || 0) > 0;
        }).slice().sort(function (a, b) {
            var oa = ordemChavePeriodo(chavePeriodoItem(a));
            var ob = ordemChavePeriodo(chavePeriodoItem(b));
            if (oa !== ob) return oa - ob;
            return String(a.nome || '').localeCompare(String(b.nome || ''), 'pt-BR');
        });
    }

    function sincronizarFontesComDemonstrativos() {
        if (!estado) return;
        if (!estado.fontes_bimestres || typeof estado.fontes_bimestres !== 'object') {
            estado.fontes_bimestres = { 1: 0, 2: 0, 3: 0, 4: 0 };
        }
        var porBim = {};
        var i;
        for (i = 1; i <= maxPeriodo(); i++) porBim[i] = 0;
        demonstrativosMarcados().forEach(function (id) {
            var r = ((catalogo && catalogo.regras) || []).filter(function (x) { return Number(x.id) === id; })[0];
            if (!r) return;
            var b = Number(r.bimestre || 0);
            if (periodoNumeroValido(b) && !porBim[b]) porBim[b] = id;
        });
        for (i = 1; i <= maxPeriodo(); i++) {
            if (porBim[i]) estado.fontes_bimestres[i] = porBim[i];
        }
    }

    function htmlPuxarDemonstrativosQuadro() {
        // No boletim / Montar quadro não usamos mais Quadro de Notas:
        // a fonte são os Eventos Avaliativos, separados por bimestre/trimestre.
        return htmlPuxarEventosAvaliativosQuadro(true);
    }

    function garantirMapaSubsEventosAvulsos() {
        if (!estado) return;
        if (!estado.eventos_avaliativos_subs || typeof estado.eventos_avaliativos_subs !== 'object' || Array.isArray(estado.eventos_avaliativos_subs)) {
            estado.eventos_avaliativos_subs = {};
        }
    }

    function idsSubeventosDoEvento(ev) {
        var ids = [];
        ((ev && ev.subeventos) || []).forEach(function (sub) {
            var sid = Number(sub && sub.id || 0);
            if (sid > 0) ids.push(sid);
        });
        return ids;
    }

    function subeventosAvulsosMarcados(eventoId) {
        garantirMapaSubsEventosAvulsos();
        var raw = estado.eventos_avaliativos_subs[eventoId] || estado.eventos_avaliativos_subs[String(eventoId)] || [];
        if (!Array.isArray(raw)) return [];
        return raw.map(Number).filter(function (id) { return id > 0; });
    }

    function subeventoAvulsoMarcado(eventoId, subId) {
        return subeventosAvulsosMarcados(eventoId).indexOf(Number(subId)) >= 0;
    }

    function htmlSubeventosAvulsosQuadro(ev) {
        var subs = (ev && ev.subeventos) || [];
        if (subs.length < 2) return '';
        var eventoId = Number(ev.id || 0);
        var marcados = subeventosAvulsosMarcados(eventoId);
        // Sem seleção explícita: todos entram (mesmo comportamento do backend).
        var todosOn = !marcados.length;
        var html = '<div class="mt-3 pl-7 space-y-2 border-t border-gray-100 pt-3">';
        html += '<div class="flex flex-wrap items-center justify-between gap-2">';
        html += '<p class="text-xs font-medium text-gray-600">Sub-eventos</p>';
        html += '<label class="inline-flex items-center gap-1.5 text-xs text-indigo-800 cursor-pointer">'
            + '<input type="checkbox" class="bw-evento-avulso-subs-todos rounded border-gray-300 text-indigo-600" data-evento="' + eventoId + '"'
            + (todosOn || marcados.length === subs.length ? ' checked' : '') + '> Selecionar todos</label>';
        html += '</div>';
        html += '<div class="grid sm:grid-cols-2 gap-2">';
        subs.forEach(function (sub) {
            var sid = Number(sub.id || 0);
            if (sid <= 0) return;
            var on = todosOn || subeventoAvulsoMarcado(eventoId, sid);
            var data = typeof dataProvaBr === 'function' ? dataProvaBr(sub.data_prova) : '';
            html += '<label class="inline-flex items-start gap-2 text-sm border rounded-lg px-3 py-2 bg-white cursor-pointer">';
            html += '<input type="checkbox" class="bw-evento-avulso-sub mt-0.5 rounded border-gray-300 text-indigo-600" data-evento="' + eventoId + '" value="' + sid + '"' + (on ? ' checked' : '') + '>';
            html += '<span>' + esc(sub.titulo || ('Sub-evento #' + sid)) + (data ? ' · ' + esc(data) : '') + '</span></label>';
        });
        html += '</div></div>';
        return html;
    }

    function htmlCardEventoAvaliativoQuadro(ev, marcados) {
        var id = Number(ev.id || 0);
        if (id <= 0) return '';
        var on = !!marcados[id];
        var lab = ev.nome || ('Evento #' + id);
        if (ev.sigla) lab = String(ev.sigla).toUpperCase() + ' — ' + lab;
        var nSub = Number(ev.total_subeventos || (ev.subeventos || []).length || 0);
        var html = '<div class="bw-card' + (on ? ' selected' : '') + '">';
        html += '<label class="flex items-start gap-3 cursor-pointer">';
        html += '<input type="checkbox" class="mt-1 bw-evento-avulso" value="' + id + '"' + (on ? ' checked' : '') + '>';
        html += '<span><span class="text-sm font-semibold text-gray-900">' + esc(lab) + '</span>';
        html += '<span class="block text-xs text-gray-500">' + esc(rotuloPeriodoEventoAvaliativo(ev));
        if (nSub) html += ' · ' + nSub + (nSub === 1 ? ' subevento' : ' subeventos');
        if (ev.turmas_nomes) html += ' · ' + esc(ev.turmas_nomes);
        html += '</span></span></label>';
        if (on) html += htmlSubeventosAvulsosQuadro(ev);
        html += '</div>';
        return html;
    }

    /** Garante IDs dos eventos já no quadro (evita erro no Aplicar quando a lista se perde). */
    function sincronizarEventosIdsDoQuadro() {
        if (!estado) return;
        if (!Array.isArray(estado.eventos_avaliativos_ids)) estado.eventos_avaliativos_ids = [];
        var ids = estado.eventos_avaliativos_ids.map(Number).filter(function (id) { return id > 0; });
        var set = {};
        ids.forEach(function (id) { set[id] = true; });
        eventosCatalogoBuilder().forEach(function (ev) {
            var id = Number(ev && ev.id || 0);
            if (id <= 0 || set[id]) return;
            if (!colunaEventoJaNoQuadro(ev)) return;
            ids.push(id);
            set[id] = true;
        });
        estado.eventos_avaliativos_ids = ids;
    }

    /**
     * Lê checkboxes de eventos do DOM.
     * @param {{preservarLayout?: boolean}} opcoes preservarLayout=true no Aplicar
     *   (não zera títulos/ordem do quadro).
     */
    function sincronizarSelecaoEventosAvulsosDoDom(opcoes) {
        if (!estado || !bodyEl) return;
        opcoes = opcoes || {};
        var preservarLayout = !!opcoes.preservarLayout;
        garantirMapaSubsEventosAvulsos();
        // Fora do passo Eventos não há checkboxes — não zera a seleção já feita.
        var checks = bodyEl.querySelectorAll('.bw-evento-avulso');
        if (!checks.length) {
            sincronizarEventosIdsDoQuadro();
            return;
        }
        estado.eventos_avaliativos_ids = [];
        bodyEl.querySelectorAll('.bw-evento-avulso:checked').forEach(function (c) {
            var id = parseInt(c.value, 10) || 0;
            if (id > 0) estado.eventos_avaliativos_ids.push(id);
        });
        sincronizarEventosIdsDoQuadro();
        var novoSubs = {};
        estado.eventos_avaliativos_ids.forEach(function (id) {
            var marcados = [];
            bodyEl.querySelectorAll('.bw-evento-avulso-sub[data-evento="' + id + '"]:checked').forEach(function (sub) {
                var sid = parseInt(sub.value, 10) || 0;
                if (sid > 0) marcados.push(sid);
            });
            var ev = ((catalogo && catalogo.eventos_avaliativos) || []).filter(function (x) {
                return Number(x.id) === id;
            })[0];
            var todos = idsSubeventosDoEvento(ev);
            // Só grava lista quando o usuário restringiu; vazio = todos.
            if (todos.length >= 2 && marcados.length && marcados.length < todos.length) {
                novoSubs[id] = marcados;
            }
        });
        estado.eventos_avaliativos_subs = novoSubs;
        // Ao marcar novos períodos, libera cabeçalhos automáticos para remontar I/II/III…
        // No Aplicar preserva o layout já montado.
        if (!preservarLayout && !estado.cabecalhos_manuais) {
            estado.linhas_layout = [];
            estado.colunas_ordem = [];
        }
        aplicarSiglasEventosAvulsosNosTitulos();
    }

    function htmlPuxarEventosAvaliativosQuadro(comoPassoPrincipal) {
        if (!(catalogo && catalogo.modulo_eventos_avaliativos)) {
            return '<p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Módulo de <strong>Eventos Avaliativos</strong> desativado nesta escola.</p>';
        }
        garantirMapaSubsEventosAvulsos();
        var eventos = eventosAvaliativosDoAnoQuadro();
        var marcados = {};
        eventosAvaliativosMarcados().forEach(function (id) { marcados[id] = true; });
        var campo = String(rotuloCampoPeriodo() || 'período').toLocaleLowerCase('pt-BR');
        var html = comoPassoPrincipal ? '' : '<div class="mt-6 pt-4 border-t border-gray-200">';
        html += '<p class="text-sm text-gray-700">Quais <strong>Eventos avaliativos</strong> entram neste boletim?</p>';
        html += '<p class="text-xs text-gray-500 mt-1">Separados por <strong>' + esc(campo) + '</strong>. Use <strong>Selecionar todos</strong> no período (e nos sub-eventos, se houver).</p>';
        if (!eventos.length) {
            html += '<p class="mt-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Nenhum Evento avaliativo neste ano. Cadastre em <strong>Eventos Avaliativos</strong> e volte.</p>';
            if (!comoPassoPrincipal) html += '</div>';
            return html;
        }
        var grupos = agruparItensPorPeriodo(eventos);
        html += '<div class="mt-3 space-y-4">';
        grupos.forEach(function (grupo) {
            var idsGrupo = grupo.itens.map(function (ev) { return Number(ev.id || 0); }).filter(function (id) { return id > 0; });
            var todosMarcados = idsGrupo.length > 0 && idsGrupo.every(function (id) { return !!marcados[id]; });
            html += '<div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3" data-periodo-chave="' + esc(grupo.chave) + '">';
            html += '<div class="flex flex-wrap items-center justify-between gap-2 mb-2">';
            html += '<p class="text-xs font-semibold uppercase tracking-wide text-gray-700">' + esc(grupo.rotulo) + '</p>';
            html += '<label class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-800 cursor-pointer">'
                + '<input type="checkbox" class="bw-evento-avulso-periodo-todos rounded border-gray-300 text-indigo-600" data-periodo="' + esc(grupo.chave) + '"'
                + (todosMarcados ? ' checked' : '') + '> Selecionar todos</label>';
            html += '</div>';
            html += '<div class="grid gap-3">';
            grupo.itens.forEach(function (ev) {
                html += htmlCardEventoAvaliativoQuadro(ev, marcados);
            });
            html += '</div></div>';
        });
        html += '</div>';
        if (!comoPassoPrincipal) html += '</div>';
        return html;
    }

    function finalidadeAtual() {
        return (estado && estado.finalidade === 'complementar') ? 'complementar' : 'oficial';
    }

    function regrasNotasDoAno() {
        var ano = Number((estado && estado.ano_letivo) || 0);
        var selfId = Number((estado && estado.regra_id) || 0);
        var fin = finalidadeAtual();
        return ((catalogo && catalogo.regras) || []).filter(function (r) {
            if ((r.exibir_em || 'boletim') !== 'notas') return false;
            if (selfId > 0 && Number(r.id) === selfId) return false;
            var ja = Number(r.ano_letivo || 0);
            if (ano > 0 && ja > 0 && ja !== ano) return false;
            var rFin = (r.finalidade || 'oficial') === 'complementar' ? 'complementar' : 'oficial';
            if (rFin !== fin) return false;
            return true;
        });
    }

    function sugerirFontesBimestres() {
        var i;
        if (!estado.fontes_bimestres || typeof estado.fontes_bimestres !== 'object') {
            estado.fontes_bimestres = {};
        }
        for (i = 1; i <= maxPeriodo(); i++) {
            estado.fontes_bimestres[i] = Number(estado.fontes_bimestres[i] || 0) || 0;
        }
        regrasNotasDoAno().forEach(function (r) {
            var b = Number(r.bimestre || 0);
            if (!periodoNumeroValido(b)) return;
            if (!Number(estado.fontes_bimestres[b] || 0)) {
                estado.fontes_bimestres[b] = Number(r.id);
            }
        });
        if (!estado.fontes_faltas || typeof estado.fontes_faltas !== 'object') {
            estado.fontes_faltas = {};
        }
        for (i = 1; i <= maxPeriodo(); i++) {
            estado.fontes_faltas[i] = Number(estado.fontes_faltas[i] || 0) || 0;
        }
        eventosFaltasDoAno().forEach(function (ev) {
            var b = Number(ev.bimestre || 0);
            if (!periodoNumeroValido(b)) return;
            if (!Number(estado.fontes_faltas[b] || 0)) {
                estado.fontes_faltas[b] = Number(ev.id);
            }
        });
    }

    function ehQuadroImpressaoLivre() {
        return !!(estado && (estado.quadro_livre || estado.origem === 'quadro-impressao'));
    }

    function aplicarExibirEm(valor, reRender) {
        if (ehBoletimComposto() || ehQuadroImpressaoLivre() || ehModeloSantaMaria()) {
            estado.exibir_em = 'boletim';
            estado.bimestre = 0;
            if (reRender) renderAll();
            return;
        }
        var v = String(valor || 'notas');
        estado.exibir_em = v === 'boletim' ? 'boletim' : 'notas';
        if (estado.exibir_em === 'notas' && !estado.bimestre) {
            estado.bimestre = 1;
        }
        if (estado.exibir_em === 'boletim') {
            estado.bimestre = 0;
        }
        if (reRender) renderAll();
    }

    function aplicarBoletimCadastro(id, reRender) {
        estado.boletim_id = Number(id) || 0;
        // Visão de impressão do boletim NÃO vira Demonstrativo de notas.
        if (!(ehBoletimComposto() || ehQuadroImpressaoLivre() || ehModeloSantaMaria() || estado.exibir_em === 'boletim')) {
            estado.exibir_em = 'notas';
        } else {
            estado.exibir_em = 'boletim';
            estado.bimestre = 0;
        }
        var bol = (catalogo.boletins || []).filter(function (b) { return Number(b.id) === Number(id); })[0];
        if (bol) {
            estado.finalidade = bol.finalidade === 'complementar' ? 'complementar' : 'oficial';
            estado.materias_ids = (bol.materias_ids || []).slice();
            estado.series_ids = (bol.series_ids || []).slice();
            estado.turmas_ids = (bol.turmas_ids || []).slice();
            estado.nota_minima_aprovacao = bol.nota_minima_aprovacao != null ? bol.nota_minima_aprovacao : 6;
            estado.nota_minima_origem = bol.minima_origem === 'regra' ? 'regra' : 'padrao';
            estado.nota_minima_regra_nome = estado.nota_minima_origem === 'regra' ? (bol.regra_nome || '') : '';
            estado.round_mode = bol.round_mode || 'none';
            estado.decimal_places = Number(bol.decimal_places) === 1 ? 1 : 2;
        } else {
            estado.nota_minima_aprovacao = 6;
            estado.nota_minima_origem = 'padrao';
            estado.nota_minima_regra_nome = '';
        }
        if (rascunhoAtual) {
            rascunhoAtual.nota_minima_aprovacao = estado.nota_minima_aprovacao;
            rascunhoAtual.nota_minima_origem = estado.nota_minima_origem;
            rascunhoAtual.nota_minima_regra_nome = estado.nota_minima_regra_nome;
            rascunhoAtual.boletim_id = estado.boletim_id;
            rascunhoAtual.round_mode = estado.round_mode || 'none';
            rascunhoAtual.decimal_places = Number(estado.decimal_places) === 1 ? 1 : 2;
        }
        var roundEl = document.querySelector('[name="round_mode"]');
        if (roundEl && estado.round_mode) roundEl.value = estado.round_mode;
        var decEl = document.querySelector('[name="decimal_places"]');
        if (decEl && estado.decimal_places != null) decEl.value = String(estado.decimal_places);
        if (reRender) renderAll();
    }

    function minimaExibida() {
        var origem = (estado && estado.nota_minima_origem) || 'padrao';
        var nome = (estado && estado.nota_minima_regra_nome) || '';
        var valor = (estado && estado.nota_minima_aprovacao != null && estado.nota_minima_aprovacao !== '')
            ? Number(estado.nota_minima_aprovacao)
            : 6;
        if (!isFinite(valor)) valor = 6;
        if (origem !== 'regra' && origem !== 'evento') origem = 'padrao';
        return { valor: valor, origem: origem, nome: nome };
    }

    function formatarMinima(valor) {
        var n = Number(valor);
        if (!isFinite(n)) n = 6;
        var umaCasa = Math.abs(n - Math.round(n * 10) / 10) < 0.001;
        return n.toFixed(umaCasa ? 1 : 2).replace('.', ',');
    }

    function rotuloMinima(info) {
        if (info.origem === 'regra') {
            return info.nome ? ('regra vinculada: ' + info.nome) : 'regra vinculada';
        }
        if (info.origem === 'evento') return 'valor deste evento';
        return 'padrão';
    }

    function htmlMinimaAprovacao() {
        var info = minimaExibida();
        return '<p class="text-sm text-gray-800">Média mínima: <strong>' + esc(formatarMinima(info.valor)) + '</strong>'
            + ' <span class="text-gray-500">(' + esc(rotuloMinima(info)) + ')</span></p>';
    }

    function htmlFontesBimestres() {
        // Mantido para fluxos legados; no Montar quadro do boletim a UI lista só Eventos Avaliativos por período.
        var faltas = eventosFaltasDoAno();
        var html = '<div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50/70 p-3 space-y-3">';
        html += '<p class="text-sm font-semibold text-indigo-950">Faltas por ' + esc(String(rotuloCampoPeriodo() || 'período').toLocaleLowerCase('pt-BR')) + '</p>';
        html += '<p class="text-xs text-indigo-900/80">Opcional. Escolha o evento de faltas de cada período para a coluna no boletim.</p>';
        var i;
        for (i = 1; i <= maxPeriodo(); i++) {
            (function (b) {
                var selF = Number((estado.fontes_faltas && estado.fontes_faltas[b]) || 0);
                var faltasBim = faltas.filter(function (ev) { return Number(ev.bimestre || 0) === b; });
                var faltasOut = faltas.filter(function (ev) { return Number(ev.bimestre || 0) !== b; });
                html += '<div class="rounded-lg border border-indigo-100 bg-white/80 p-2.5 space-y-2">';
                html += '<p class="text-xs font-semibold text-gray-800">' + esc(rotuloPeriodoNumero(b)) + '</p>';
                html += '<label class="block text-xs font-medium text-gray-700">Faltas (evento de Faltas)';
                html += '<select class="bw-fonte-faltas mt-1 w-full h-10 border border-indigo-200 rounded-lg px-3 text-sm bg-white" data-bim="' + b + '">';
                html += '<option value="0">Sem faltas neste período</option>';
                faltasBim.concat(faltasOut).forEach(function (ev) {
                    var labF = ev.nome || ('Faltas #' + ev.id);
                    if (ev.bimestre_rotulo) labF += ' · ' + ev.bimestre_rotulo;
                    else if (ev.bimestre) labF += ' · ' + rotuloPeriodoEventoAvaliativo(ev);
                    html += '<option value="' + Number(ev.id) + '"' + (selF === Number(ev.id) ? ' selected' : '') + '>' + esc(labF) + '</option>';
                });
                html += '</select></label></div>';
            })(i);
        }
        if (!faltas.length) {
            html += '<p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5">Nenhum evento de <strong>Faltas</strong> neste ano. Cadastre em Faltas (período + turmas) para aparecer a coluna no boletim.</p>';
        }
        html += '</div>';
        return html;
    }

    function idxPasso(id) {
        for (var i = 0; i < PASSOS.length; i++) if (PASSOS[i].id === id) return i;
        return 0;
    }

    var WIZARD_CLASS_MAP = {
        ativo: ['border-accent', 'bg-primary', 'text-primary', 'shadow-md'],
        completo: ['border-green-500', 'bg-green-50', 'text-green-700'],
        erro: ['border-red-400', 'bg-red-50', 'text-red-700'],
        pendente: ['border-gray-200', 'bg-white', 'text-gray-600', 'hover:border-gray-300', 'hover:bg-gray-50']
    };
    var WIZARD_ALL_STATE_CLASSES = Object.keys(WIZARD_CLASS_MAP).reduce(function (acc, k) {
        return acc.concat(WIZARD_CLASS_MAP[k]);
    }, []);

    function renderWizardBadge(btn, estado) {
        var circle = btn.querySelector('.wizard-step-circle');
        if (!circle) return;
        var existing = circle.querySelector('.wizard-step-corner');
        if (existing) existing.remove();
        if (estado !== 'completo') return;
        var span = document.createElement('span');
        span.className = 'wizard-step-corner absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full text-white text-[9px] bg-green-500';
        var icon = document.createElement('i');
        icon.className = 'fa-solid fa-check';
        span.appendChild(icon);
        circle.appendChild(span);
    }

    function atualizarStepsPageMode(vis, curIdx) {
        if (!stepsEl) return;
        stepsEl.querySelectorAll('.step-nav-btn').forEach(function (btn) {
            var n = parseInt(btn.getAttribute('data-step-target') || '0', 10);
            var idx = n - 1;
            var p = vis[idx];
            var estadoStep = idx === curIdx ? 'ativo' : (idx < curIdx ? 'completo' : 'pendente');
            btn.setAttribute('data-active', estadoStep === 'ativo' ? 'true' : 'false');
            btn.setAttribute('data-step-state', estadoStep);
            WIZARD_ALL_STATE_CLASSES.forEach(function (c) { btn.classList.remove(c); });
            WIZARD_CLASS_MAP[estadoStep].forEach(function (c) { btn.classList.add(c); });
            renderWizardBadge(btn, estadoStep);
            if (!btn.dataset.boletimBound) {
                btn.dataset.boletimBound = '1';
                btn.addEventListener('click', function () {
                    var alvo = parseInt(btn.getAttribute('data-step-target') || '0', 10) - 1;
                    var passo = passosVisiveis()[alvo];
                    if (!estado || !passo) return;
                    estado.passo = passo.id;
                    renderAll();
                });
            }
            if (p) {
                var labelEl = btn.querySelector('.block.font-semibold');
                if (labelEl) labelEl.textContent = String(p.label || '').replace(/^\d+\.\s*/, '');
                var subEl = btn.querySelector('.block.text-xs');
                if (subEl) subEl.textContent = p.sub || '';
            }
            btn.classList.toggle('hidden', !p);
        });
        stepsEl.querySelectorAll('[data-connector-after]').forEach(function (el) {
            var n = parseInt(el.getAttribute('data-connector-after') || '0', 10);
            var done = n <= curIdx;
            el.style.display = n >= vis.length ? 'none' : '';
            el.classList.toggle('bg-green-400', done);
            el.classList.toggle('bg-gray-200', !done);
        });
    }

    function renderSteps() {
        var vis = passosVisiveis();
        var cur = estado ? estado.passo : 'inicio';
        if (!vis.some(function (p) { return p.id === cur; })) {
            cur = vis.length ? vis[vis.length - 1].id : 'inicio';
            if (estado) estado.passo = cur;
        }
        var curIdx = 0;
        vis.forEach(function (p, i) { if (p.id === cur) curIdx = i; });
        if (pageMode) {
            atualizarStepsPageMode(vis, curIdx);
        } else if (stepsEl) {
            stepsEl.innerHTML = '';
            vis.forEach(function (p, i) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'bw-step-btn' + (p.id === cur ? ' active' : (i < curIdx ? ' done' : ''));
                b.textContent = (i + 1) + '. ' + String(p.label || '').replace(/^\d+\.\s*/, '');
                b.addEventListener('click', function () {
                    if (!estado) return;
                    estado.passo = p.id;
                    renderAll();
                });
                stepsEl.appendChild(b);
            });
        }
        btnVoltar.disabled = curIdx <= 0;
        // Demonstrativo: só salva o quadro (ao vivo). Boletim: concluir gera o oficial.
        var label;
        if (cur === 'revisar') {
            label = ehEventoNotasPreview() ? 'Salvar demonstrativo' : 'Concluir e aplicar';
        } else if (cur === 'formula') {
            label = 'Salvar e continuar';
        } else {
            label = 'Continuar';
        }
        if (btnAplicar && pageMode) {
            if (ehEventoNotasPreview()) {
                btnAplicar.classList.add('hidden');
            } else {
                btnAplicar.classList.remove('hidden');
                btnAplicar.textContent = cur === 'revisar' ? 'Salvar sem gerar' : 'Aplicar na configuração';
            }
        }
        atualizarBotaoGerarAluno();
        if (pageMode) {
            btnAvancar.innerHTML = esc(label) + (cur === 'revisar' ? ' <i class="fa-solid fa-check ml-2"></i>' : ' <i class="fa-solid fa-arrow-right ml-2"></i>');
        } else {
            btnAvancar.textContent = label;
        }
        var sub = document.getElementById('boletim-wizard-subtitulo');
        if (sub) {
            sub.textContent = ehBoletimComposto()
                ? 'Escolha as médias e as faltas de cada bimestre (eventos já criados)'
                : 'Adicione blocos de cálculo, dê um nome, arraste a ordem e salve';
        }
    }

    function cardHtml(selected, title, desc) {
        return '<div class="bw-card' + (selected ? ' selected' : '') + '"><div class="text-sm font-semibold text-gray-900">' + title + '</div>'
            + (desc ? '<p class="text-xs text-gray-500 mt-1">' + desc + '</p>' : '') + '</div>';
    }

    function pecasUsamQuadro() {
        return (estado && estado.pecas || []).indexOf('semanal') >= 0;
    }

    function urlCriarQuadro() {
        var retorno = window.location.pathname + window.location.search;
        return String(urlQuadros || '').replace(/\/$/, '') + '/novo?retorno=' + encodeURIComponent(retorno);
    }

    function htmlAcoesQuadro() {
        if (!urlQuadros || (!podeVerQuadro && !podeCadastrarQuadro)) {
            return ' Peça a quem administra o Quadro de Notas para cadastrar o molde. O quadro é o molde das colunas; o Lançamento de Notas é onde a nota é digitada.';
        }
        var partes = [];
        if (podeCadastrarQuadro) partes.push('<a class="underline font-medium" href="' + esc(urlCriarQuadro()) + '">Criar quadro</a>');
        if (podeVerQuadro) {
            var retornoGestao = window.location.pathname + window.location.search;
            partes.push('<a class="underline font-medium" href="' + esc(urlQuadros) + '?retorno=' + encodeURIComponent(retornoGestao) + '">Gerenciar quadros</a>');
        }
        return ' ' + partes.join(' · ') + '. O quadro é o molde das colunas; o Lançamento de Notas é onde a nota é digitada. Sair para cadastrar não apaga esta configuração nem gera notas.';
    }

    function rotuloOpcaoQuadro() {
        var situacao = catalogo && catalogo.quadros_notas_situacao ? catalogo.quadros_notas_situacao : '';
        if ((catalogo.quadros_notas || []).length) return 'Sem quadro';
        if (situacao === 'sem_permissao') return 'Sem permissão para consultar quadros';
        if (situacao === 'sem_elegivel') return 'Nenhum quadro elegível para esta avaliação';
        if (situacao === 'desabilitado') return 'Quadro de Notas indisponível nesta escola';
        return 'Nenhum quadro cadastrado';
    }

    function htmlAvisoQuadro() {
        if (!catalogo || !Array.isArray(catalogo.quadros_notas)) return '';
        var quadros = catalogo.quadros_notas;
        var situacao = catalogo.quadros_notas_situacao || '';
        var temQuadro = Number(estado && estado.grupo_regras_notas_id || 0) > 0;
        var usaMolde = pecasUsamQuadro();
        if (situacao === 'sem_permissao') {
            return '<p class="text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 mt-2">Você não tem permissão para consultar os quadros desta escola. A fórmula, a simulação e a geração seguem sem quadro.</p>';
        }
        if (!quadros.length && situacao === 'sem_elegivel') {
            return '<p class="text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 mt-2">Há quadros cadastrados, mas nenhum está ativo para esta avaliação. Você pode configurar fórmulas e gerar notas e boletins sem quadro.' + htmlAcoesQuadro() + '</p>';
        }
        if (!quadros.length) {
            return '<p class="text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 mt-2">Nenhum quadro cadastrado. Você pode configurar fórmulas e gerar notas e boletins sem quadro.' + htmlAcoesQuadro() + '</p>';
        }
        if (usaMolde && !temQuadro) {
            return '<p class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2">Esta fórmula cita colunas do Quadro de Notas (S1, AV1). Sem um quadro escolhido, essas colunas não são resolvidas por um molde. As demais peças da configuração continuam valendo na simulação e na geração.' + htmlAcoesQuadro() + '</p>';
        }
        if (!temQuadro) {
            return '<p class="text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 mt-2">Selecione um quadro se quiser usar suas colunas nesta avaliação. Você também pode configurar fórmulas e gerar notas e boletins sem quadro.</p>';
        }
        return '<p class="text-xs text-gray-500 mt-1">O quadro escolhido é o molde das colunas deste evento. O Lançamento de Notas continua sendo o lugar onde a nota é digitada.' + htmlAcoesQuadro() + '</p>';
    }

    function aplicarQuadroRecemCriado() {
        if (!estado) return;
        var id = Number(new URLSearchParams(window.location.search).get('quadro_id') || 0);
        if (id <= 0) return;
        var existe = (catalogo.quadros_notas || []).some(function (q) { return Number(q.id) === id; });
        if (!existe) return;
        estado.grupo_regras_notas_id = id;
    }

    function contextoNavegacaoCompativel(salvo) {
        if (!salvo || typeof salvo !== 'object') return false;
        var bol = Number(root.getAttribute('data-boletim-id') || 0);
        var ano = Number(root.getAttribute('data-ano-letivo') || 0);
        var bim = Number(root.getAttribute('data-bimestre') || 0);
        if (bol > 0 && Number(salvo.boletim_id || 0) !== bol) return false;
        if (ano > 0 && Number(salvo.ano_letivo || 0) !== ano) return false;
        if (bim > 0 && Number(salvo.bimestre || 0) !== bim) return false;
        if (regraId > 0 && Number(salvo.regra_id || 0) > 0 && Number(salvo.regra_id) !== regraId) return false;
        return true;
    }

    function guardarEstadoParaQuadro() {
        if (!estado) return;
        try { syncIdentidadeFromDom(); } catch (e) { /* campos ainda não montados */ }
        try {
            sessionStorage.setItem('boletim_assistente_estado_navegacao', JSON.stringify(estado));
        } catch (e2) { /* navegação segue mesmo sem rascunho local */ }
    }

    function restaurarEstadoNavegacao() {
        try {
            var bruto = sessionStorage.getItem('boletim_assistente_estado_navegacao');
            if (!bruto) return false;
            sessionStorage.removeItem('boletim_assistente_estado_navegacao');
            var salvo = JSON.parse(bruto);
            if (!contextoNavegacaoCompativel(salvo)) return false;
            estado = salvo;
            garantirEstado();
            return true;
        } catch (e) {
            return false;
        }
    }

    function rascunhoSessaoCompativel() {
        try {
            var bruto = sessionStorage.getItem('boletim_assistente_rascunho');
            if (!bruto) return null;
            var r = JSON.parse(bruto);
            if (!r || typeof r !== 'object' || !Array.isArray(r.componentes) || !r.componentes.length) return null;
            var bol = Number(root.getAttribute('data-boletim-id') || 0);
            var ano = Number(root.getAttribute('data-ano-letivo') || 0);
            var bim = Number(root.getAttribute('data-bimestre') || 0);
            if (bol > 0 && Number(r.boletim_id || 0) !== bol) return null;
            if (ano > 0 && Number(r.ano_letivo || 0) !== ano) return null;
            if (bim > 0 && Number(r.bimestre || 0) !== bim) return null;
            if (regraId > 0 && Number(r.regra_id || 0) > 0 && Number(r.regra_id) !== regraId) return null;
            return r;
        } catch (e) {
            return null;
        }
    }

    function renderBody() {
        if (!estado) {
            bodyEl.innerHTML = '<p class="text-sm text-gray-500">Carregando…</p>';
            return;
        }
        var html = '';
        var passo = estado.passo;

        if (passo === 'inicio') {
            html += '<p class="text-sm text-gray-700">Como deseja começar?</p><div class="grid gap-3 mt-2">';
            html += '<div data-origem="zero">' + cardHtml(estado.origem === 'zero', 'Do zero', 'Você escolhe as peças e como cada uma entra na média.') + '</div>';
            html += '<div data-origem="modelo">' + cardHtml(estado.origem === 'modelo', 'Modelo pronto', 'Começa com uma combinação comum da escola.') + '</div>';
            html += '<div data-origem="clonar">' + cardHtml(estado.origem === 'clonar', 'Clonar outro evento', 'Copia a regra de um bimestre/evento já existente e só ajusta o período.') + '</div>';
            html += '</div>';

            if (estado.origem === 'modelo') {
                html += '<p class="text-sm font-medium text-gray-800 mt-4">Escolha o modelo</p><div class="grid gap-2 mt-2">';
                (catalogo.modelos || []).forEach(function (m) {
                    html += '<div data-modelo="' + m.key + '">' + cardHtml(estado.modelo_key === m.key, m.titulo, m.descricao) + '</div>';
                });
                html += '</div>';
            }
            if (estado.origem === 'clonar') {
                html += '<p class="text-sm font-medium text-gray-800 mt-4">Evento de origem</p><select id="bw-clonar-regra" class="mt-2 w-full h-10 border border-gray-300 rounded-lg px-3 text-sm">';
                html += '<option value="">Selecione…</option>';
                (catalogo.regras || []).forEach(function (r) {
                    if ((r.exibir_em || 'notas') === 'boletim') return;
                    var label = r.nome || ('Evento #' + r.id);
                    if (r.bimestre) label += ' · ' + r.bimestre + 'º bim';
                    if (r.ano_letivo) label += ' · ' + r.ano_letivo;
                    html += '<option value="' + Number(r.id) + '"' + (String(estado.clonar_regra_id || '') === String(r.id) ? ' selected' : '') + '>' + esc(label) + '</option>';
                });
                html += '</select>';
                html += '<div class="grid grid-cols-2 gap-3 mt-3"><div><label class="text-xs text-gray-600">Novo ' + esc(rotuloCampoPeriodo().toLocaleLowerCase('pt-BR')) + '</label>'
                    + '<input id="bw-clonar-bim" type="number" min="1" max="' + maxPeriodo() + '" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + (periodoNumeroValido(estado.bimestre) ? estado.bimestre : 1) + '"></div>'
                    + '<div><label class="text-xs text-gray-600">Ano letivo</label>'
                    + '<input id="bw-clonar-ano" type="number" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + (estado.ano_letivo || '') + '"></div></div>';
            }
        }

        if (passo === 'identidade') {
            var ocultarModeloBoletim = ehVisaoImpressaoEditavel() || ehBoletimComposto() || ehQuadroImpressaoLivre() || ehModeloSantaMaria()
                || estado.exibir_em === 'boletim';
            if (estado.boletim_id && estado.modo !== 'editar' && !ocultarModeloBoletim) {
                aplicarBoletimCadastro(estado.boletim_id, false);
            }
            // No Montar quadro o layout já é o boletim — não zera nem pede modelo de novo.
            if (ocultarModeloBoletim) {
                var layoutBol = Number(estado.layout_boletim_id || root.getAttribute('data-layout-boletim-id') || 0);
                if (layoutBol > 0) {
                    estado.layout_boletim_id = layoutBol;
                    // Não usa o ID do layout como modelo de boletim (cadastro).
                    if (Number(estado.boletim_id || 0) === layoutBol) estado.boletim_id = 0;
                }
            }
            html += '<div class="grid gap-3">';
            html += '<div><label class="text-sm font-medium text-gray-700">Nome do evento</label>'
                + '<input id="bw-nome" type="text" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + esc(estado.nome) + '"></div>';
            html += '<div class="grid grid-cols-2 gap-3"><div><label class="text-sm font-medium text-gray-700">Ano letivo</label>'
                + '<input id="bw-ano" type="number" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + (estado.ano_letivo || '') + '"></div>';
            if (ehBoletimComposto() || ehQuadroImpressaoLivre() || ehModeloSantaMaria()) {
                html += '<div><label class="text-sm font-medium text-gray-700">' + esc(rotuloCampoPeriodo()) + '</label>'
                    + '<p class="mt-1 h-10 flex items-center text-sm text-gray-600">Ano todo (1º ao ' + maxPeriodo() + 'º)</p></div></div>';
            } else {
                html += '<div><label class="text-sm font-medium text-gray-700">' + esc(rotuloCampoPeriodo()) + '</label>'
                    + '<select id="bw-bim" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm">'
                    + htmlOpcoesPeriodo(estado.ano_letivo, estado.bimestre)
                    + '</select></div></div>';
            }
            if (!ocultarModeloBoletim) {
                var boletimOpcional = estado.exibir_em === 'notas';
                html += '<div><label class="text-sm font-medium text-gray-700">Boletim'
                    + (boletimOpcional ? ' <span class="font-normal text-gray-400">(opcional)</span>' : '')
                    + '</label>'
                    + '<select id="bw-boletim" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm">';
                html += '<option value="">' + (boletimOpcional ? 'Nenhum' : 'Selecione um Boletim') + '</option>';
                (catalogo.boletins || []).forEach(function (b) {
                    var label = b.nome || ('Boletim #' + b.id);
                    label += b.finalidade === 'complementar' ? ' · Extra' : ' · Oficial';
                    if (b.ano_letivo) label += ' · ' + b.ano_letivo;
                    html += '<option value="' + Number(b.id) + '"' + (Number(estado.boletim_id) === Number(b.id) ? ' selected' : '') + '>' + esc(label) + '</option>';
                });
                html += '</select>';
                html += htmlMinimaAprovacao();
                if (!(catalogo.boletins || []).length) {
                    html += '<p class="text-xs text-amber-800 mt-1">Nenhum modelo apareceu. Cadastre em <a href="<?= URL ?>/admin/boletins" class="underline">Acadêmico → Modelo de Boletim</a>. Sem vínculo, a mínima sugerida fica 6 e o público é o que você marcar nesta identidade — não o da turma do modelo.</p>';
                } else if (boletimOpcional) {
                    html += '<p class="text-xs text-gray-500 mt-1">Nenhum deixa este evento fora do boletim oficial. Escolha o modelo para levar a mínima, as matérias e as turmas dele para a vida escolar e para a geração dos períodos.</p>';
                }
                html += '</div>';
            } else {
                html += '<div>' + htmlMinimaAprovacao() + '</div>';
            }
            // Boletim / Montar quadro: sem Quadro de Notas — fontes são Eventos Avaliativos por período.
            html += '</div>';
        }

        if (passo === 'pecas' && ehVisaoImpressaoEditavel()) {
            html += htmlPuxarDemonstrativosQuadro();
            sugerirFontesBimestres();
            html += htmlFontesBimestres();
        } else if (passo === 'pecas' && usaEventosAvaliativosNaNota()) {
            html += htmlPecasEventosAvaliativos();
        } else if (passo === 'pecas' && !usaEventosAvaliativosNaNota()) {
            html += '<p class="text-sm text-gray-700">Quais notas entram neste boletim?</p>';
            html += '<p class="text-xs text-gray-500 mt-1">Marque o que entra e o ' + esc(rotuloCampoPeriodo().toLocaleLowerCase('pt-BR')) + ' de cada tipo. Ordem das colunas e o cálculo ficam no passo <strong>Fórmula</strong>.</p>';
            html += '<div class="grid gap-3 mt-3">';
            (catalogo.pecas || []).forEach(function (p) {
                if (p.key === 'jornada' && !catalogo.modulo_jornadas) return;
                var on = (estado.pecas || []).indexOf(p.key) >= 0;
                var opts = (estado.pecas_opcoes && estado.pecas_opcoes[p.key]) ? estado.pecas_opcoes[p.key] : pecasOpcoesPadraoJs(p.key);
                html += '<div class="bw-card' + (on ? ' selected' : '') + '">';
                html += '<label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" class="mt-1 bw-peca" value="' + p.key + '"' + (on ? ' checked' : '') + '>'
                    + '<span><span class="text-sm font-semibold text-gray-900">' + p.label + '</span><span class="block text-xs text-gray-500">' + (p.hint || '') + '</span></span></label>';
                if (on) {
                    html += '<div class="mt-3 pl-7 space-y-3 border-t border-gray-100 pt-3">';
                    html += htmlBimestresPeca(p.key, opts);
                    html += htmlEventosPeca(p.key, opts);
                    if (p.key === 'jornada') html += htmlJornadaPeca(opts);
                    if (p.key !== 'jornada') {
                        var rotuloFecha = p.fechamento_rotulo || '';
                        html += '<p class="text-xs text-gray-500">Fechamento vem do tipo de nota'
                            + (rotuloFecha ? ' (' + rotuloFecha + ')' : '')
                            + '. Para mudar, edite em Avaliações → Tipos de nota.</p>';
                    }
                    html += '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        if (passo === 'formula') {
            if (estado.origem === 'clonar') {
                html += '<p class="text-sm text-gray-600">No modo clonar, a ordem e as fórmulas do evento de origem são mantidas. Avance para revisar.</p>';
            } else if (ehDemonstrativoNotas()) {
                html += '<p class="text-sm text-gray-700">Como o demonstrativo <strong>aparece</strong></p>';
                html += '<p class="text-xs text-gray-500 mt-1">Catálogo à esquerda: clique para adicionar a sigla de cada coluna. À direita, monte os títulos do período, arraste as siglas e clique em <strong>Confirmar</strong> quando o período estiver pronto. Não é necessário montar fórmula de cálculo.</p>';
                html += '<div class="mt-3">' + htmlEscopoPecasSelecionadas() + '</div>';
                html += '<div id="bw-colunas-wrap" class="mt-3"></div>';
                html += '<div id="bw-preview-wrap" class="mt-4"></div>';
            } else {
                html += '<p class="text-sm text-gray-700">Como o boletim <strong>aparece</strong></p>';
                html += '<p class="text-xs text-gray-500 mt-1">' + (ehVisaoImpressaoEditavel()
                    ? 'À esquerda: catálogo por bimestre (clique adiciona a sigla). À direita: arraste chips para títulos do período e use <strong>Bloco de cálculo</strong> para média com nome livre (ex. MU). Clique em <strong>Confirmar</strong> quando o período estiver pronto.'
                    : 'Catálogo à esquerda adiciona a sigla. Monte títulos do período e blocos de cálculo à direita. Ao terminar, clique em <strong>Salvar bloco</strong> e depois em <strong>Salvar e continuar</strong>.') + '</p>';
                html += '<div class="mt-3">' + htmlEscopoPecasSelecionadas() + '</div>';
                html += '<div id="bw-colunas-wrap" class="mt-3"></div>';
                html += '<div id="bw-formula-editor" class="' + (estado.bloco_calc ? '' : 'hidden') + ' mt-4 rounded-xl border border-amber-200 bg-amber-50/40 p-3">';
                html += '<div id="bw-excecao-banner" class="hidden mb-3 rounded-xl border-2 border-indigo-400 bg-indigo-600 text-white px-3 py-2.5"></div>';
                html += '<div class="flex flex-wrap items-end justify-between gap-2 mb-2">';
                html += '<label class="flex-1 min-w-[12rem]"><span class="text-xs font-medium text-amber-950">Título da coluna</span>';
                html += '<input id="bw-formula-bloco-nome-input" type="text" maxlength="60" class="mt-1 w-full h-10 border border-amber-200 rounded-lg px-3 text-sm bg-white" value="' + esc(nomeBlocoCalc(estado.bloco_calc || '')) + '" placeholder="Ex.: Média Bim">';
                html += '</label>';
                html += '<label class="w-36"><span class="text-xs font-medium text-amber-950">Valor máximo</span>';
                html += '<input id="bw-formula-bloco-escala-input" type="number" min="0.01" max="999" step="0.01" class="mt-1 w-full h-10 border border-amber-200 rounded-lg px-3 text-sm bg-white" value="' + esc(String(escalaBlocoCalc(estado.bloco_calc || ''))) + '" title="Teto da coluna. Em Resultado Final × 2 use 20.">';
                html += '</label>';
                html += '<div class="flex flex-wrap gap-1 pb-0.5">';
                [10, 20, 100].forEach(function (v) {
                    html += '<button type="button" class="bw-escala-atalho h-8 px-2 text-xs rounded-md border border-amber-200 text-amber-900 hover:bg-amber-100" data-escala="' + v + '">' + v + '</button>';
                });
                html += '</div>';
                html += '<div class="flex flex-col items-end gap-1">';
                html += '<button type="button" id="bw-formula-salvar" class="h-10 px-4 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700">Salvar bloco</button>';
                html += '<label class="inline-flex items-center gap-1.5 text-[11px] text-amber-950/80 cursor-pointer select-none">'
                    + '<input type="checkbox" id="bw-formula-fechar-apos" class="rounded border-amber-300 text-amber-600"'
                    + (fecharFormulaAposSalvar ? ' checked' : '') + '> Fechar ao salvar</label>';
                html += '<p id="bw-formula-salva" class="hidden text-sm font-semibold text-emerald-700">Salvo.</p>';
                html += '</div>';
                html += '</div>';
                html += '<p class="text-[11px] text-amber-900/80 mb-2">Valor máximo corta o resultado da fórmula. Em <strong>Resultado Final × 2</strong>, use <strong>20</strong> (senão fica 10).</p>';
                html += '<p id="bw-formula-bloco-titulo" class="text-sm font-semibold text-amber-950">Cálculo de <span id="bw-formula-bloco-nome">' + esc(nomeBlocoCalc(estado.bloco_calc || 'média')) + '</span></p>';
                html += '<p id="bw-formula-bloco-ajuda" class="text-xs text-amber-900/80 mt-0.5 mb-2">A média é a soma entre parênteses, depois ÷ quantidade. Ex.: (Prova + Trabalho + Participação) ÷ 3.</p>';
                html += '<div class="bw-formula-workspace mt-1">';
                html += '<div class="bw-formula-pecas-col">';
                html += '<p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide mb-1">Usar no cálculo</p>';
                html += '<p class="text-[11px] text-slate-500 mb-2"><strong>Clique</strong> na sigla para inserir (não precisa arrastar). Filtre pelo período nas abas. Eventos sem chip no quadro também aparecem aqui.</p>';
                html += '<div id="bw-formula-pecas-pal" class="bw-palette"></div>';
                html += '</div>';
                html += '<div class="bw-formula-montagem">';
                html += '<p id="bw-formula-canvas-label" class="text-[11px] font-semibold text-amber-950 uppercase tracking-wide mb-1.5">Fórmula desta coluna</p>';
                html += '<div id="bw-formula-canvas" class="bw-canvas">' + htmlCanvasTokens() + '</div>';
                html += '<p id="bw-formula-preview" class="mt-2 text-sm font-mono text-slate-800 break-all">' + esc(compilarTokensPreview(estado.formula_tokens || []) || 'Clique nas siglas à esquerda.') + '</p>';
                html += '<p class="text-[11px] font-semibold text-amber-950 uppercase tracking-wide mt-3 mb-1.5">Sinais</p>';
                html += '<div class="bw-palette flex flex-wrap gap-2 items-center">';
                operadoresFormula().forEach(function (op) { html += chipHtml(op, -1, true); });
                html += '<label class="inline-flex items-center gap-1.5 text-xs text-amber-950/80 ml-1">Número';
                html += '<input id="bw-formula-num" type="text" inputmode="decimal" class="h-8 w-16 border border-amber-200 rounded-md px-2 text-sm bg-white" placeholder="2">';
                html += '<button type="button" id="bw-formula-num-add" class="h-8 px-2 text-xs rounded-md border border-amber-300 text-amber-950 bg-white hover:bg-amber-50">Inserir</button></label></div>';
                html += '<div class="mt-2 flex flex-wrap gap-2">';
                html += '<button type="button" id="bw-formula-media" class="px-2.5 py-1.5 text-xs rounded-lg border border-indigo-200 text-indigo-700 bg-white hover:bg-indigo-50">Média das peças</button>';
                html += '<button type="button" id="bw-formula-max" class="px-2.5 py-1.5 text-xs rounded-lg border border-amber-300 text-amber-900 bg-white hover:bg-amber-100">Maior entre as duas primeiras</button>';
                html += '<button type="button" id="bw-formula-substituta" class="px-2.5 py-1.5 text-xs rounded-lg border border-emerald-200 text-emerald-800 bg-white hover:bg-emerald-50" title="Média das notas principais; a substituta entra no lugar da primeira nota ausente">Média com nota substituta</button>';
                html += '<button type="button" id="bw-formula-composicao" class="px-2.5 py-1.5 text-xs rounded-lg border border-violet-200 text-violet-800 bg-white hover:bg-violet-50" title="Sem recuperação: fica a média. Com recuperação: (média + recuperação) ÷ 2">Média com recuperação</button>';
                html += '<button type="button" id="bw-formula-limpar" class="px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 text-gray-600 bg-white hover:bg-gray-50">Limpar</button></div>';
                html += '<details class="mt-2 rounded-lg border border-amber-200/80 bg-white/70 px-2.5 py-2">';
                html += '<summary class="text-[11px] font-semibold text-amber-950 cursor-pointer select-none">Manual rápido das fórmulas</summary>';
                html += '<div class="mt-2 space-y-1.5 text-[11px] text-amber-950/80 leading-snug">';
                html += '<p><strong>Média das peças</strong> — (nota1 + nota2 + …) ÷ quantidade. Todas com o mesmo peso.</p>';
                html += '<p><strong>Maior entre as duas primeiras</strong> — fica a maior nota entre as duas primeiras peças.</p>';
                html += '<p><strong>Média com nota substituta</strong> — média das bases; a substituta (ex.: CH) entra só no lugar da 1ª ausente. Já divide sozinha: não coloque <code class="bg-amber-50 px-1 rounded">÷ 2</code> de novo. Na MU do Santa Maria, some o PE depois: <code class="bg-amber-50 px-1 rounded">nota substituta (PP, PU, CH) + PE</code>.</p>';
                html += '<p><strong>Média com recuperação</strong> — sem REC fica a média; com REC vira (média + REC) ÷ 2.</p>';
                html += '<p><strong>Média com peso</strong> — use × e ÷: <code class="bg-amber-50 px-1 rounded">((PP × 2) + PU) ÷ 3</code> (PP vale o dobro). Sempre divida pela soma dos pesos.</p>';
                html += '<p><strong>maior (</strong> / <strong>menor (</strong> — maior ou menor valor entre as colunas listadas.</p>';
                html += '<p class="text-amber-900/70">Manual completo: <code class="bg-amber-50 px-1 rounded">especificacoes/manual-formulas-boletim.md</code></p>';
                html += '</div></details>';
                html += '</div></div>';
                html += '<div class="mt-4 pt-3 border-t border-amber-200/80">';
                html += '<p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide mb-1">Exceção só nesta matéria</p>';
                html += '<p class="text-xs text-gray-500 mb-2">Se uma matéria não tem um tipo de prova (ex.: sem semanal), some só o que ela tem — a fórmula geral continua nas outras.</p>';
                html += '<div class="flex flex-wrap items-end gap-2 mb-2">';
                html += '<label class="text-xs text-gray-600">Matéria<select id="bw-excecao-materia" class="mt-1 block h-9 border rounded-md px-2 text-sm min-w-[12rem]"><option value="0">Escolher matéria…</option></select></label>';
                html += '<button type="button" id="bw-excecao-add" class="h-9 px-3 text-xs font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Usar outra fórmula nesta matéria</button>';
                html += '</div>';
                html += '<div id="bw-excecoes-lista" class="space-y-1.5"></div>';
                html += '</div>';
                html += '</div>';
                html += '<div id="bw-preview-wrap" class="mt-4"></div>';
            }
        }

        if (passo === 'publico') {
            if (estado.boletim_id && estado.modo !== 'editar') {
                aplicarBoletimCadastro(estado.boletim_id, false);
            }
            html += '<p class="text-sm font-medium text-gray-800">Agrupar matérias na linha do boletim</p>';
            html += '<p class="text-xs text-gray-500 mb-2">O grupo (pai e desdobramentos) já vem de <strong>Componentes Curriculares</strong>. O padrão vale para todas as peças. Em <strong>Ajuste por peça</strong> dá para a semanal e a jornada fazerem média e a prova bimestral somar.</p>';

            var gl = estado.grupo_linha || grupoLinhaPadrao();
            if (gl.agrupamento_id === undefined || gl.agrupamento_id === null) gl.agrupamento_id = 0;
            html += '<select id="bw-grupo-cadastro" class="hidden"><option value="0">Montar na hora</option>';
            (catalogo.agrupamentos || []).forEach(function (ag) {
                var on = Number(gl.agrupamento_id || 0) === Number(ag.id);
                html += '<option value="' + ag.id + '"' + (on ? ' selected' : '') + '>' + esc(ag.nome || ('Agrupamento #' + ag.id)) + '</option>';
            });
            html += '</select>';
            html += '<div class="mt-4 border rounded-lg p-3 bg-indigo-50/60 space-y-3">';
            if (usaEventosAvaliativosNaNota()) {
                aplicarJuntarProfessoresDoEvento();
                var criterioEv = criterioProfessoresDosEventosMarcados();
                var textoEv = 'Os eventos marcados não juntam professores da mesma disciplina.';
                if (criterioEv === 'media') textoEv = 'Professores da mesma disciplina entram pela média, como está no evento avaliativo.';
                if (criterioEv === 'soma') textoEv = 'Professores da mesma disciplina entram pela soma, como está no evento avaliativo.';
                html += '<p class="text-sm text-gray-800"><strong>Mesma disciplina, professores diferentes.</strong> <span class="block text-xs text-gray-600 font-normal mt-0.5">' + textoEv + '</span></p>';
            } else {
            html += '<label class="inline-flex items-start gap-2 text-sm text-gray-800">';
            html += '<input type="checkbox" id="bw-materia-unica" class="mt-0.5 rounded border-gray-300 text-indigo-600"' + (estado.materia_unica ? ' checked' : '') + '>';
            html += '<span><strong>Juntar matérias iguais</strong><span class="block text-xs text-gray-600 font-normal">Mesma matéria, professores diferentes (ex.: dois Português). Vale para todas as peças. Não junta Português com Literatura.</span></span></label>';
            }
            if (!usaEventosAvaliativosNaNota() && estado.materia_unica) {
                var modoIguaisUi = (estado.materia_unica_modo === 'media') ? 'media' : 'soma';
                html += '<div class="ml-6 pl-1 border-l-2 border-indigo-200 space-y-2">';
                html += '<span class="text-xs font-medium text-gray-600">Como juntar as notas dos professores</span>';
                html += '<div class="flex flex-wrap gap-3 text-sm">';
                html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-materia-unica-modo" class="bw-materia-unica-modo" value="soma"' + (modoIguaisUi === 'soma' ? ' checked' : '') + '> Somar</label>';
                html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-materia-unica-modo" class="bw-materia-unica-modo" value="media"' + (modoIguaisUi === 'media' ? ' checked' : '') + '> Média</label>';
                html += '</div>';
                html += '<p class="text-xs text-gray-500">Somar: cada professor contribui (ex.: 3+4+3 = 10). Média: 10+10+10 ÷ 3 = 10. Independente do Tipo de Nota.</p>';
                html += '</div>';
            }
            html += '<label class="inline-flex items-start gap-2 text-sm text-gray-800">';
            html += '<input type="checkbox" id="bw-grupo-ativo" class="mt-0.5 rounded border-gray-300 text-indigo-600"' + (gl.ativo ? ' checked' : '') + '>';
            html += '<span><strong>Linha única no boletim</strong><span class="block text-xs text-gray-600 font-normal">Junta os desdobramentos numa linha só. Ex.: Gramática + Interpretação + Literatura → “Língua Portuguesa”.</span></span></label>';
            if (gl.ativo) {
                garantirFamiliaPadrao();
                gl = estado.grupo_linha || gl;
                var nomeExemploArea = String(gl.nome || '').trim() || 'Língua Portuguesa';
                html += '<label class="ml-6 inline-flex items-start gap-2 text-sm text-gray-800">';
                html += '<input type="checkbox" id="bw-grupo-exemplo-sem-filhas" class="mt-0.5 rounded border-gray-300 text-indigo-600"' + (gl.exemplo_sem_filhas ? ' checked' : '') + '>';
                html += '<span>Exibir só <strong>' + esc(nomeExemploArea) + '</strong>, sem as matérias filhas. Vale no detalhe do aluno, no PDF, nas notas geradas e no acesso de pais e alunos.</span></label>';
                if (gl.exemplo_sem_filhas) {
                    html += '<div id="bw-preview-wrap" class="ml-6"></div>';
                }
                var fams = familiasDoEscopo();
                if (fams.length) {
                    html += '<div><span class="text-xs font-medium text-gray-600">Área (já cadastrada em Componentes)</span>';
                    html += '<div class="mt-2 space-y-2">';
                    fams.forEach(function (f) {
                        var on = idsGrupoIguais(gl.materias_ids, f.materias_ids);
                        var nomesFilhos = f.filhos.map(function (ch) { return ch.nome; }).join(', ');
                        html += '<label class="flex items-start gap-2 text-sm text-gray-800 border rounded-lg p-3 bg-white">';
                        html += '<input type="radio" name="bw-grupo-familia" class="bw-grupo-familia mt-0.5" value="' + f.pai_id + '"' + (on ? ' checked' : '') + '>';
                        html += '<span><strong>' + esc(f.nome) + '</strong><span class="block text-xs text-gray-600 font-normal">' + esc(nomesFilhos) + '</span></span></label>';
                    });
                    html += '</div></div>';
                }
                html += '<div><span class="text-xs font-medium text-gray-600">Matérias nesta área (neste evento)</span>';
                html += '<p class="text-xs text-gray-500 mt-0.5 mb-2">Altera só este Evento de Notas (este bimestre). Os outros bimestres não mudam. Marque ou desmarque para incluir/excluir filhas.</p>';
                html += '<div class="grid sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto border rounded-lg p-3 bg-white">';
                materiasDaAreaAtiva().forEach(function (m) {
                    var on = (gl.materias_ids || []).some(function (id) { return Number(id) === Number(m.id); });
                    html += '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="bw-grupo-materia rounded border-gray-300 text-indigo-600" value="' + m.id + '"' + (on ? ' checked' : '') + '> ' + esc(m.nome) + '</label>';
                });
                if (!materiasDaAreaAtiva().length) {
                    html += '<p class="text-xs text-gray-500">Nenhuma matéria neste boletim.</p>';
                }
                html += '</div></div>';
                html += '<div><span class="text-xs font-medium text-gray-600">Como juntar as notas dessas matérias</span>';
                html += '<p class="text-xs text-gray-500 mt-0.5">Padrão das peças que não tiverem ajuste próprio.</p>';
                html += '<div class="mt-1 flex flex-wrap gap-3 text-sm">';
                html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-grupo-modo" class="bw-grupo-modo" value="media"' + (gl.modo !== 'soma' ? ' checked' : '') + '> Média</label>';
                html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-grupo-modo" class="bw-grupo-modo" value="soma"' + (gl.modo === 'soma' ? ' checked' : '') + '> Soma</label>';
                html += '</div></div>';
                var pecasGrupo = (estado.pecas || []).map(function (p) { return String(p || ''); }).filter(function (p) { return p !== ''; });
                if (pecasGrupo.length) {
                    if (!gl.modos || typeof gl.modos !== 'object') gl.modos = {};
                    html += '<div class="border rounded-lg bg-white p-3 space-y-2">';
                    html += '<span class="text-xs font-medium text-gray-600">Ajuste por peça</span>';
                    html += '<p class="text-xs text-gray-500">Prova semanal inclui as semanas e a média semanal. Ex.: semanal e jornada em média, prova bimestral em soma.</p>';
                    pecasGrupo.forEach(function (peca) {
                        var modoPeca = modoEfetivoPecaGrupo(gl, peca);
                        var jornadaNotaUnica = peca === 'jornada' && estado.jornada_distribuicao_notas === 'nota_unica_todas_linhas';
                        var hintPeca = peca === 'semanal'
                            ? '<span class="block text-[11px] text-gray-500 font-normal">Semanas e média semanal.</span>'
                            : (jornadaNotaUnica ? '<span class="block text-[11px] text-gray-500 font-normal">Mesma nota em todas as matérias: a área repete essa nota.</span>' : '');
                        html += '<div class="flex flex-wrap items-center justify-between gap-2">';
                        html += '<span class="text-sm text-gray-800">' + esc(rotuloPecaGrupo(peca)) + hintPeca + '</span>';
                        html += '<span class="flex flex-wrap gap-3 text-sm">';
                        html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-grupo-modo-peca-' + esc(peca) + '" class="bw-grupo-modo-peca" data-peca="' + esc(peca) + '" value="media"' + (modoPeca !== 'soma' ? ' checked' : '') + '> Média</label>';
                        html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-grupo-modo-peca-' + esc(peca) + '" class="bw-grupo-modo-peca" data-peca="' + esc(peca) + '" value="soma"' + (modoPeca === 'soma' ? ' checked' : '') + '> Soma</label>';
                        html += '</span></div>';
                    });
                    html += '</div>';
                }
                var arredG = (gl.arredondamento === 'filhas' || gl.arredondamento === 'mae') ? gl.arredondamento : 'todos';
                html += '<div><span class="text-xs font-medium text-gray-600">Arredondamento da área</span>';
                html += '<p class="text-xs text-gray-500 mt-0.5 mb-1">Usa o arredondamento do evento (ex.: .00 / .50). Escolha quem recebe.</p>';
                html += '<div class="mt-1 space-y-1.5 text-sm">';
                html += '<label class="flex items-start gap-2"><input type="radio" name="bw-grupo-arred" class="bw-grupo-arred mt-0.5" value="todos"' + (arredG === 'todos' ? ' checked' : '') + '>';
                html += '<span><strong>Todos</strong><span class="block text-xs text-gray-600 font-normal">Filhas e linha-mãe.</span></span></label>';
                html += '<label class="flex items-start gap-2"><input type="radio" name="bw-grupo-arred" class="bw-grupo-arred mt-0.5" value="filhas"' + (arredG === 'filhas' ? ' checked' : '') + '>';
                html += '<span><strong>Só filhas</strong><span class="block text-xs text-gray-600 font-normal">Arredonda as matérias; a mãe fica com a média/soma exata.</span></span></label>';
                html += '<label class="flex items-start gap-2"><input type="radio" name="bw-grupo-arred" class="bw-grupo-arred mt-0.5" value="mae"' + (arredG === 'mae' ? ' checked' : '') + '>';
                html += '<span><strong>Só mãe</strong><span class="block text-xs text-gray-600 font-normal">Filhas sem arredondamento especial; só a linha-mãe arredonda.</span></span></label>';
                html += '</div></div>';
                if (estado.exibir_em === 'notas') {
                    var aplicar = gl.aplicar_em === 'ambos' ? 'ambos' : 'boletim';
                    html += '<div><span class="text-xs font-medium text-gray-600">Onde essa linha única aparece</span>';
                    html += '<div class="mt-2 space-y-2">';
                    html += '<label class="flex items-start gap-2 text-sm text-gray-800"><input type="radio" name="bw-grupo-aplicar" class="bw-grupo-aplicar mt-0.5" value="boletim"' + (aplicar === 'boletim' ? ' checked' : '') + '>';
                    html += '<span><strong>Só no boletim</strong><span class="block text-xs text-gray-600 font-normal">Nas notas, as matérias continuam em linhas separadas.</span></span></label>';
                    html += '<label class="flex items-start gap-2 text-sm text-gray-800"><input type="radio" name="bw-grupo-aplicar" class="bw-grupo-aplicar mt-0.5" value="ambos"' + (aplicar === 'ambos' ? ' checked' : '') + '>';
                    html += '<span><strong>No boletim e nas notas</strong><span class="block text-xs text-gray-600 font-normal">Junta as matérias nos dois lugares.</span></span></label>';
                    html += '</div></div>';
                }
                html += '<details class="text-xs text-gray-600"><summary class="cursor-pointer">Nome da linha (avançado)</summary>';
                html += '<div class="mt-2 space-y-2">';
                html += '<div><label class="text-xs text-gray-600">Nome da linha</label>';
                html += '<input id="bw-grupo-nome" type="text" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" placeholder="Língua Portuguesa" value="' + esc(gl.nome || '') + '"></div>';
                html += '<p class="text-xs text-gray-500">Use se quiser um rótulo diferente do nome da área em Componentes.</p>';
                html += '</div></details>';
            }
            html += '</div>';
        }

        if (passo === 'revisar') {
            html += '<p class="text-sm text-gray-700">' + (ehEventoNotasPreview()
                ? 'Confira o <strong>demonstrativo de notas</strong>'
                : 'Confira a <strong>prévia</strong> do boletim') + '</p>';
            if (!Number(estado.boletim_id) && !(ehVisaoImpressaoEditavel() || ehBoletimComposto() || estado.exibir_em === 'boletim')) {
                html += '<p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5 mt-2">Sem boletim vinculado, os números abaixo são exemplo e o público ainda não é o de uma turma específica.</p>';
            }
            html += '<p class="text-xs text-gray-500 mt-1">' + (ehVisaoImpressaoEditavel()
                ? 'Só visualização. Para mudar colunas, títulos ou fórmulas, volte em <strong>Fórmula / Colunas</strong>.'
                : (ehBoletimComposto()
                    ? 'Layout oficial: 1º–4º bimestre (Média e Faltas) e FINAL (Média, Rec., Faltas, Resultado). Dados fictícios.'
                    : (ehDemonstrativoNotas()
                        ? 'É o quadro com blocos e semanas. Escolha um aluno para usar as notas lançadas. Para mudar ordem ou colunas, volte em <strong>Colunas</strong>.'
                        : (ehEventoNotasPreview()
                            ? 'É o quadro com blocos e semanas. Escolha um aluno para usar as notas lançadas. Para mudar ordem ou fórmula, volte em <strong>Fórmula</strong>.'
                            : 'Dados fictícios. Para mudar ordem ou fórmula, volte em <strong>Fórmula</strong>.')))) + '</p>';
            html += '<div class="mt-3">' + htmlEscopoPecasSelecionadas() + '</div>';
            // Revisão: só a prévia. Quadro/catálogo/fórmula ficam no passo Fórmula.
            html += '<div id="bw-preview-wrap" class="mt-4"></div>';
            if (!ehVisaoImpressaoEditavel()) {
                html += htmlAvisoQuadro();
            }
            if (retornoFluxo === 'gerar-avaliacoes' && returnUrl) {
                html += '<p class="text-sm text-gray-700 mt-3"><a class="font-semibold text-indigo-700 underline" href="' + esc(returnUrl) + '">Voltar e configurar os demais períodos</a></p>';
            }
            html += '<div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">';
            if (ehEventoNotasPreview()) {
                html += '<p class="text-sm text-gray-800"><strong>Demonstrativo de notas</strong><span class="block text-xs text-gray-600 font-normal mt-1">É o quadro do período. Monte no início do mês; cada prova lançada aparece sozinha para aluno, pais e painel. Não gera snapshot — não precisa “concluir” toda hora.</span></p>';
                html += '<p class="text-sm text-gray-800"><strong>Salvar demonstrativo</strong><span class="block text-xs text-gray-600 font-normal mt-1">Grava só colunas, ordem e fórmula do quadro. O boletim oficial é outro fluxo (menu Boletim).</span></p>';
            } else {
                html += '<p class="text-sm text-gray-800"><strong>Boletim oficial</strong><span class="block text-xs text-gray-600 font-normal mt-1">Documento do período (print / ficha). Separado do demonstrativo ao vivo.</span></p>';
                html += '<p class="text-sm text-gray-800"><strong>Salvar sem gerar</strong><span class="block text-xs text-gray-600 font-normal mt-1">Grava só o quadro (colunas, fórmulas, REC/PF). Ainda não publica no aluno.</span></p>';
                html += '<p class="text-sm text-gray-800"><strong>Concluir e aplicar</strong><span class="block text-xs text-gray-600 font-normal mt-1">Grava o quadro e calcula o boletim de todos os alunos no escopo. O oficial só muda quando a geração terminar.</span></p>';
            }
            html += '<p class="text-xs text-gray-600">Para usar outra configuração, recupere uma versão antiga aqui.</p>';
            html += htmlVersoesConfiguracao();
            html += '</div>';
            html += '<p class="text-xs text-indigo-700 mt-4">' + (ehEventoNotasPreview()
                ? 'Clique em <strong>Salvar demonstrativo</strong>. As notas entram no quadro conforme o lançamento.'
                : 'Para só gravar o quadro, <strong>Salvar sem gerar</strong>. Para calcular e publicar o boletim, <strong>Concluir e aplicar</strong>.') + '</p>';
        }

        bodyEl.innerHTML = html;
        bindBodyEvents();
        if (passo === 'revisar' || passo === 'formula' || (passo === 'publico' && estado.grupo_linha && estado.grupo_linha.exemplo_sem_filhas)) renderRevisarDinamico();
        if (passo === 'formula' && estado.bloco_calc && !ehDemonstrativoNotas()) abrirFormulaBloco(estado.bloco_calc);
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function bindBodyEvents() {
        bodyEl.querySelectorAll('.js-restaurar-config').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-versao-id');
                if (!confirm('Recuperar esta versão da configuração? A configuração atual continua no histórico.')) {
                    return;
                }
                var input = document.getElementById('restaurar-config-versao-id');
                var form = document.getElementById('form-restaurar-config-versao');
                if (!input || !form) return;
                input.value = id;
                form.submit();
            });
        });
        bodyEl.querySelectorAll('[data-origem]').forEach(function (el) {
            el.addEventListener('click', function () {
                estado.origem = el.getAttribute('data-origem');
                if (estado.origem !== 'modelo') estado.modelo_key = '';
                if (estado.origem !== 'clonar') estado.clonar_regra_id = null;
                if (estado.origem === 'zero' || estado.origem === 'modelo') {
                    estado.rascunho_preservado = null;
                }
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('[data-modelo]').forEach(function (el) {
            el.addEventListener('click', function () {
                estado.modelo_key = el.getAttribute('data-modelo');
                var m = (catalogo.modelos || []).find(function (x) { return x.key === estado.modelo_key; });
                if (m) {
                    estado.pecas = (m.pecas || []).slice();
                    estado.formula_preset = m.formula_preset || 'media_simples';
                    estado.pecas_opcoes = {};
                    estado.materia_unica = estado.pecas.indexOf('semanal') >= 0 ? 1 : 0;
                    estado.pecas.forEach(function (k) {
                        estado.pecas_opcoes[k] = pecasOpcoesPadraoJs(k);
                        if (m.papeis && m.papeis[k]) estado.pecas_opcoes[k].papel = m.papeis[k];
                        else if (m.formula_preset === 'enac_so_melhora' && k === 'enac') estado.pecas_opcoes[k].papel = 'so_melhora';
                        else if (m.formula_preset === 'parcial_depois_final' && k === 'enac') estado.pecas_opcoes[k].papel = 'depois';
                    });
                    estado.rascunho_preservado = null;
                }
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-evento-avaliativo, .bw-evento-sub').forEach(function (el) {
            el.addEventListener('change', function () {
                aplicarSelecaoEventosAvaliativos();
                setTimeout(function () {
                    renderAll();
                    agendarMontar();
                }, 0);
            });
        });
        bodyEl.querySelectorAll('.bw-evento-jornada').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.pecas = (estado.pecas || []).filter(function (k) { return k !== 'jornada'; });
                if (el.checked) estado.pecas.push('jornada');
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes.jornada) estado.pecas_opcoes.jornada = pecasOpcoesPadraoJs('jornada');
                marcarEdicaoManual();
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-demonstrativo').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.demonstrativos_ids = [];
                bodyEl.querySelectorAll('.bw-demonstrativo:checked').forEach(function (c) {
                    var id = parseInt(c.value, 10) || 0;
                    if (id > 0) estado.demonstrativos_ids.push(id);
                });
                sincronizarFontesComDemonstrativos();
                marcarEdicaoManual();
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-evento-avulso-periodo-todos').forEach(function (el) {
            el.addEventListener('change', function () {
                var box = el.closest('[data-periodo-chave]');
                if (!box) return;
                box.querySelectorAll('.bw-evento-avulso').forEach(function (cb) {
                    cb.checked = el.checked;
                });
                if (el.checked) {
                    box.querySelectorAll('.bw-evento-avulso-sub, .bw-evento-avulso-subs-todos').forEach(function (cb) {
                        cb.checked = true;
                    });
                }
                sincronizarSelecaoEventosAvulsosDoDom();
                marcarEdicaoManual();
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-evento-avulso').forEach(function (el) {
            el.addEventListener('change', function () {
                var id = parseInt(el.value, 10) || 0;
                if (el.checked && id > 0) {
                    // Ao marcar o evento, marca todos os sub-eventos visíveis.
                    bodyEl.querySelectorAll('.bw-evento-avulso-sub[data-evento="' + id + '"], .bw-evento-avulso-subs-todos[data-evento="' + id + '"]').forEach(function (cb) {
                        cb.checked = true;
                    });
                }
                sincronizarSelecaoEventosAvulsosDoDom();
                marcarEdicaoManual();
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-evento-avulso-subs-todos').forEach(function (el) {
            el.addEventListener('change', function () {
                var id = parseInt(el.getAttribute('data-evento') || '0', 10) || 0;
                bodyEl.querySelectorAll('.bw-evento-avulso-sub[data-evento="' + id + '"]').forEach(function (cb) {
                    cb.checked = el.checked;
                });
                if (el.checked) {
                    var pai = bodyEl.querySelector('.bw-evento-avulso[value="' + id + '"]');
                    if (pai) pai.checked = true;
                }
                sincronizarSelecaoEventosAvulsosDoDom();
                marcarEdicaoManual();
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-evento-avulso-sub').forEach(function (el) {
            el.addEventListener('change', function () {
                var id = parseInt(el.getAttribute('data-evento') || '0', 10) || 0;
                var subs = bodyEl.querySelectorAll('.bw-evento-avulso-sub[data-evento="' + id + '"]');
                var marcados = 0;
                subs.forEach(function (cb) { if (cb.checked) marcados++; });
                var todos = bodyEl.querySelector('.bw-evento-avulso-subs-todos[data-evento="' + id + '"]');
                if (todos) todos.checked = marcados > 0 && marcados === subs.length;
                if (marcados > 0) {
                    var pai = bodyEl.querySelector('.bw-evento-avulso[value="' + id + '"]');
                    if (pai) pai.checked = true;
                }
                sincronizarSelecaoEventosAvulsosDoDom();
                marcarEdicaoManual();
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca').forEach(function (el) {
            el.addEventListener('change', function () {
                var tinhaSemanal = (estado.pecas || []).indexOf('semanal') >= 0;
                var tinhaJornada = (estado.pecas || []).indexOf('jornada') >= 0;
                var cardJornada = bodyEl.querySelector('.bw-peca[value="jornada"]');
                estado.pecas = [];
                bodyEl.querySelectorAll('.bw-peca:checked').forEach(function (c) { estado.pecas.push(c.value); });
                if (tinhaJornada && !cardJornada && estado.pecas.indexOf('jornada') < 0) {
                    estado.pecas.push('jornada');
                }
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!tinhaSemanal && estado.pecas.indexOf('semanal') >= 0) {
                    estado.materia_unica = 1;
                }
                estado.pecas.forEach(function (k) {
                    if (!estado.pecas_opcoes[k]) {
                        estado.pecas_opcoes[k] = pecasOpcoesPadraoJs(k);
                    }
                    var codLib = k === 'semanal' ? '_semanal' : pecaCodigoQuadro(k);
                    estado.colunas_ocultas = (estado.colunas_ocultas || []).filter(function (c) {
                        return c !== codLib && c !== pecaCodigoQuadro(k) && !(k === 'semanal' && (c === 'media_sem' || /^s[1-8]$/.test(c)));
                    });
                });
                aplicarMateriaUnicaNasPecas();
                filtrarTokensOrfaos();
                marcarEdicaoManual();
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-calc').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                estado.pecas_opcoes[peca].calc_type = el.value;
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-perc').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                estado.pecas_opcoes[peca].usar_percentual = el.checked ? 1 : 0;
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-papel').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                estado.pecas_opcoes[peca].papel = el.value;
                syncPresetDosPapeis();
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('[data-formula]').forEach(function (el) {
            el.addEventListener('click', function () {
                estado.formula_preset = el.getAttribute('data-formula');
                aplicarPresetNosPapeis(estado.formula_preset);
                marcarEdicaoManual();
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-depois').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                if (el.checked) {
                    estado.pecas_opcoes[peca].papel = estado.formula_preset === 'enac_so_melhora' ? 'so_melhora' : 'depois';
                } else {
                    estado.pecas_opcoes[peca].papel = 'media';
                }
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        var clonarSel = document.getElementById('bw-clonar-regra');
        if (clonarSel) {
            clonarSel.addEventListener('change', function () {
                estado.clonar_regra_id = parseInt(clonarSel.value, 10) || null;
                estado.materia_unica_tocada = false;
                agendarMontar();
            });
        }
        ['bw-clonar-bim', 'bw-clonar-ano', 'bw-nome', 'bw-ano', 'bw-bim', 'bw-boletim', 'bw-quadro', 'bw-nota-min', 'bw-round'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', function () {
                var anoAntes = anoAntesPendente != null ? Number(anoAntesPendente) : Number(estado.ano_letivo || 0);
                var bimAntes = bimAntesPendente != null ? Number(bimAntesPendente) : Number(estado.bimestre || 0);
                anoAntesPendente = null;
                bimAntesPendente = null;
                syncIdentidadeFromDom();
                if (id === 'bw-boletim') {
                    aplicarBoletimCadastro(estado.boletim_id, true);
                    agendarMontar();
                    return;
                }
                var anoMudou = (id === 'bw-ano' || id === 'bw-clonar-ano') && anoAntes !== Number(estado.ano_letivo || 0);
                var bimMudou = (id === 'bw-bim' || id === 'bw-clonar-bim') && bimAntes !== Number(estado.bimestre || 0);
                if (anoMudou || bimMudou) {
                    sincronizarNomeSugerido(anoAntes, bimAntes);
                }
                if (anoMudou) {
                    var idsOk = {};
                    turmasVisiveis().forEach(function (t) { idsOk[Number(t.id)] = true; });
                    estado.turmas_ids = (estado.turmas_ids || []).filter(function (idTurma) { return idsOk[Number(idTurma)]; });
                    if (ehBoletimComposto()) {
                        estado.fontes_bimestres = { 1: 0, 2: 0, 3: 0, 4: 0 };
                        estado.fontes_faltas = { 1: 0, 2: 0, 3: 0, 4: 0 };
                        sugerirFontesBimestres();
                    }
                    renderAll();
                }
                agendarMontar();
            });
            el.addEventListener('input', function () {
                if (id === 'bw-nome' && !estado._ajustandoNome) estado.nome_automatico = false;
                if ((id === 'bw-ano' || id === 'bw-clonar-ano') && anoAntesPendente == null) {
                    anoAntesPendente = Number(estado.ano_letivo || 0);
                }
                if ((id === 'bw-bim' || id === 'bw-clonar-bim') && bimAntesPendente == null) {
                    bimAntesPendente = Number(estado.bimestre || 0);
                }
                var anoAntes = anoAntesPendente != null ? Number(anoAntesPendente) : Number(estado.ano_letivo || 0);
                var bimAntes = bimAntesPendente != null ? Number(bimAntesPendente) : Number(estado.bimestre || 0);
                syncIdentidadeFromDom();
                if (id === 'bw-ano' || id === 'bw-clonar-ano' || id === 'bw-bim' || id === 'bw-clonar-bim') {
                    sincronizarNomeSugerido(anoAntes, bimAntes);
                }
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-bim').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                estado.pecas_opcoes[peca].bimestres = [];
                bodyEl.querySelectorAll('.bw-peca-bim[data-peca="' + peca + '"]:checked').forEach(function (c) {
                    estado.pecas_opcoes[peca].bimestres.push(parseInt(c.value, 10));
                });
                if (peca === 'jornada') {
                    estado.jornada_modo = 'bimestre';
                    estado.jornada_bimestres = estado.pecas_opcoes[peca].bimestres.slice();
                    estado.jornada_ids = [];
                    if (!estado.jornada_bimestres.length && periodoNumeroValido(estado.bimestre)) {
                        estado.jornada_bimestres = [Number(estado.bimestre)];
                    }
                    renderAll();
                } else {
                    estado.pecas_opcoes[peca].blocos_ids = [];
                    estado.pecas_opcoes[peca].blocos_ids_manual = 0;
                    renderAll();
                }
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-evento').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                estado.pecas_opcoes[peca].blocos_ids = [];
                estado.pecas_opcoes[peca].blocos_ids_manual = 1;
                bodyEl.querySelectorAll('.bw-peca-evento[data-peca="' + peca + '"]:checked').forEach(function (c) {
                    estado.pecas_opcoes[peca].blocos_ids.push(parseInt(c.value, 10));
                });
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-usar-ano-eventos').forEach(function (el) {
            el.addEventListener('click', function () {
                var ano = parseInt(el.getAttribute('data-ano'), 10) || 0;
                if (ano < 2000) return;
                estado.ano_letivo = ano;
                estado.ano_alinhado_eventos = true;
                var anoEl = document.getElementById('bw-ano');
                if (anoEl) anoEl.value = String(ano);
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-jornada-nota-modo').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.jornada_nota_modo = el.value;
                if (estado.jornada_nota_modo === 'faixas') {
                    estado.jornada_faixas = normalizarFaixasJornadaUi(estado.jornada_faixas);
                }
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-jornada-dist').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.jornada_distribuicao_notas = el.value === 'nota_unica_todas_linhas'
                    ? 'nota_unica_todas_linhas'
                    : 'por_materia';
                if (estado.jornada_distribuicao_notas === 'nota_unica_todas_linhas' && !estado.jornada_materias_tocada) {
                    selecaoMateriasJornada();
                }
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-jornada-faixa-nota').forEach(function (el) {
            el.addEventListener('input', function () {
                var idx = parseInt(el.getAttribute('data-idx'), 10);
                if (!estado.jornada_faixas || !estado.jornada_faixas[idx]) return;
                var n = parseFloat(String(el.value).replace(',', '.'));
                estado.jornada_faixas[idx].nota = Number.isFinite(n) ? n : el.value;
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-jornada-materia').forEach(function (el) {
            el.addEventListener('change', function () {
                lerMateriasJornadaMarcadas();
                agendarMontar();
            });
        });
        var btnJornadaTodas = document.getElementById('bw-jornada-materias-todas');
        if (btnJornadaTodas) {
            btnJornadaTodas.addEventListener('click', function () {
                estado.jornada_materias_ids = materiasDaJornadaNoEvento().map(function (m) { return Number(m.id); }).filter(function (id) { return id > 0; });
                estado.jornada_materias_tocada = true;
                renderAll();
                agendarMontar();
            });
        }
        var btnJornadaNenhuma = document.getElementById('bw-jornada-materias-nenhuma');
        if (btnJornadaNenhuma) {
            btnJornadaNenhuma.addEventListener('click', function () {
                estado.jornada_materias_ids = [];
                estado.jornada_materias_tocada = true;
                renderAll();
                agendarMontar();
            });
        }
        bodyEl.querySelectorAll('.bw-jornada-id').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.jornada_modo = 'selecionadas';
                estado.jornada_ids = [];
                bodyEl.querySelectorAll('.bw-jornada-id:checked').forEach(function (c) {
                    estado.jornada_ids.push(parseInt(c.value, 10));
                });
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-materia').forEach(function (el) {
            el.addEventListener('change', function () {
                if (boletimEscolhido()) return;
                estado.materias_ids = [];
                bodyEl.querySelectorAll('.bw-materia:checked').forEach(function (c) {
                    estado.materias_ids.push(parseInt(c.value, 10));
                });
                agendarMontar();
            });
        });
        var materiaUnicaEl = document.getElementById('bw-materia-unica');
        if (materiaUnicaEl) {
            materiaUnicaEl.addEventListener('change', function () {
                estado.materia_unica = materiaUnicaEl.checked ? 1 : 0;
                estado.materia_unica_tocada = true;
                if (!estado.materia_unica_modo) estado.materia_unica_modo = 'soma';
                aplicarMateriaUnicaNasPecas();
                renderAll();
                agendarMontar();
            });
        }
        bodyEl.querySelectorAll('.bw-materia-unica-modo').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.materia_unica_modo = el.value === 'media' ? 'media' : 'soma';
                estado.materia_unica_tocada = true;
                aplicarMateriaUnicaNasPecas();
                agendarMontar();
            });
        });
        var exemploSemFilhasEl = document.getElementById('bw-grupo-exemplo-sem-filhas');
        if (exemploSemFilhasEl) {
            exemploSemFilhasEl.addEventListener('change', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.exemplo_sem_filhas = !!exemploSemFilhasEl.checked;
                renderAll();
            });
        }
        var grupoAtivoEl = document.getElementById('bw-grupo-ativo');
        if (grupoAtivoEl) {
            grupoAtivoEl.addEventListener('change', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.ativo = !!grupoAtivoEl.checked;
                if (estado.grupo_linha.ativo) garantirFamiliaPadrao();
                renderAll();
                agendarMontar();
            });
        }
        bodyEl.querySelectorAll('.bw-grupo-familia').forEach(function (el) {
            el.addEventListener('change', function () {
                var paiId = Number(el.value || 0);
                var f = familiasDoEscopo().filter(function (x) { return Number(x.pai_id) === paiId; })[0];
                aplicarFamiliaComponente(f);
                renderAll();
                agendarMontar();
            });
        });
        var grupoCadastroEl = document.getElementById('bw-grupo-cadastro');
        if (grupoCadastroEl) {
            grupoCadastroEl.addEventListener('change', function () {
                aplicarAgrupamentoCadastro(grupoCadastroEl.value);
                renderAll();
                agendarMontar();
            });
        }
        var grupoNomeEl = document.getElementById('bw-grupo-nome');
        if (grupoNomeEl) {
            grupoNomeEl.addEventListener('input', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.nome = grupoNomeEl.value;
                agendarMontar();
            });
        }
        bodyEl.querySelectorAll('.bw-grupo-modo').forEach(function (el) {
            el.addEventListener('change', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.modo = el.value === 'soma' ? 'soma' : 'media';
                gravarModoPadraoNasPecas(estado.grupo_linha.modo);
                bodyEl.querySelectorAll('.bw-grupo-modo-peca').forEach(function (r) {
                    r.checked = r.value === estado.grupo_linha.modo;
                });
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-grupo-modo-peca').forEach(function (el) {
            el.addEventListener('change', function () {
                if (!el.checked) return;
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.modos = modosGrupoComoObjeto(estado.grupo_linha.modos);
                var peca = el.getAttribute('data-peca') || '';
                if (!peca) return;
                estado.grupo_linha.modos[peca] = el.value === 'soma' ? 'soma' : 'media';
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-grupo-arred').forEach(function (el) {
            el.addEventListener('change', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                var v = el.value;
                estado.grupo_linha.arredondamento = (v === 'filhas' || v === 'mae') ? v : 'todos';
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-grupo-aplicar').forEach(function (el) {
            el.addEventListener('change', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.aplicar_em = el.value === 'ambos' ? 'ambos' : 'boletim';
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-finalidade').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.finalidade = el.value === 'complementar' ? 'complementar' : 'oficial';
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-grupo-materia').forEach(function (el) {
            el.addEventListener('change', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.materias_ids = [];
                bodyEl.querySelectorAll('.bw-grupo-materia:checked').forEach(function (c) {
                    estado.grupo_linha.materias_ids.push(parseInt(c.value, 10));
                });
                // Lista deste evento prevalece sobre o cadastro de agrupamento.
                estado.grupo_linha.agrupamento_id = 0;
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-serie').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.series_ids = [];
                bodyEl.querySelectorAll('.bw-serie:checked').forEach(function (c) {
                    estado.series_ids.push(parseInt(c.value, 10));
                });
                var idsOk = {};
                turmasVisiveis().forEach(function (t) { idsOk[Number(t.id)] = true; });
                estado.turmas_ids = (estado.turmas_ids || []).filter(function (id) { return idsOk[Number(id)]; });
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-turma').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.turmas_ids = [];
                bodyEl.querySelectorAll('.bw-turma:checked').forEach(function (c) {
                    estado.turmas_ids.push(parseInt(c.value, 10));
                });
                agendarMontar();
            });
        });
        bindFormulaBuilderOnce();
        bodyEl.querySelectorAll('.bw-fonte-bim').forEach(function (el) {
            el.addEventListener('change', function () {
                var b = Number(el.getAttribute('data-bim') || 0);
                if (b < 1 || b > 4) return;
                if (!estado.fontes_bimestres || typeof estado.fontes_bimestres !== 'object') {
                    estado.fontes_bimestres = { 1: 0, 2: 0, 3: 0, 4: 0 };
                }
                estado.fontes_bimestres[b] = parseInt(el.value, 10) || 0;
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-fonte-faltas').forEach(function (el) {
            el.addEventListener('change', function () {
                var b = Number(el.getAttribute('data-bim') || 0);
                if (b < 1 || b > 4) return;
                if (!estado.fontes_faltas || typeof estado.fontes_faltas !== 'object') {
                    estado.fontes_faltas = { 1: 0, 2: 0, 3: 0, 4: 0 };
                }
                estado.fontes_faltas[b] = parseInt(el.value, 10) || 0;
                agendarMontar();
            });
        });
    }

    function syncIdentidadeFromDom() {
        var map = {
            'bw-clonar-bim': 'bimestre',
            'bw-clonar-ano': 'ano_letivo',
            'bw-nome': 'nome',
            'bw-ano': 'ano_letivo',
            'bw-bim': 'bimestre',
            'bw-boletim': 'boletim_id',
            'bw-quadro': 'grupo_regras_notas_id',
            'bw-nota-min': 'nota_minima_aprovacao',
            'bw-round': 'round_mode',
        };
        Object.keys(map).forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            var key = map[id];
            if (key === 'bimestre' || key === 'ano_letivo' || key === 'boletim_id' || key === 'grupo_regras_notas_id') estado[key] = parseInt(el.value, 10) || 0;
            else if (key === 'nota_minima_aprovacao') estado[key] = parseFloat(el.value);
            else estado[key] = el.value;
        });
        atualizarCabecalhoEvento();
    }

    function atualizarCabecalhoEvento() {
        var nome = String((estado && estado.nome) || '').trim();
        var ref = Number((estado && estado.regra_id) || regraId || 0);
        var titulo = nome || 'Evento de Notas';
        var h2 = document.getElementById('evento-notas-titulo');
        var sub = document.getElementById('evento-notas-ref');
        if (h2) h2.textContent = titulo;
        if (sub) sub.textContent = textoContextoEvento(ref);
        if (nome || ref > 0) document.title = titulo + ' - EducaTudo';
    }

    function textoContextoEvento(ref) {
        var ano = Number((estado && estado.ano_letivo) || 0);
        var bim = Number((estado && estado.bimestre) || 0);
        var bolId = Number((estado && estado.boletim_id) || 0);
        var nomeBol = 'Sem modelo';
        var lista = (catalogo && catalogo.boletins) || [];
        for (var i = 0; i < lista.length; i++) {
            if (Number(lista[i].id) === bolId && bolId > 0) {
                nomeBol = String(lista[i].nome || '').trim() || nomeBol;
                break;
            }
        }
        var periodo = 'Período não informado';
        if (bim > 0) {
            var info = periodoInfo(ano);
            var rotulos = info.rotulos || {};
            periodo = String(rotulos[String(bim)] || (bim + 'º ' + String(info.rotulo_campo || 'período').toLocaleLowerCase('pt-BR')));
        }
        var texto = nomeBol + ' · ' + (ano > 0 ? String(ano) : 'Ano não informado') + ' · ' + periodo;
        if (Number(ref) > 0) texto += ' · Ref ' + ref;
        return texto;
    }

    function renderResumo(resumo, erros) {
        if (resumoEl) {
            resumoEl.textContent = resumo || '';
        }
        if (!errosEl) return;
        if (erros && erros.length) {
            errosEl.classList.remove('hidden');
            errosEl.textContent = (resumo ? (resumo + ' ') : '') + erros.join(' ');
        } else {
            errosEl.classList.add('hidden');
            errosEl.textContent = '';
        }
    }

    function capturarScroll() {
        var lista = [];
        var el = bodyEl ? bodyEl.parentElement : null;
        while (el && el !== document.documentElement) {
            if (el.scrollHeight > el.clientHeight + 1) {
                lista.push({ el: el, top: el.scrollTop, left: el.scrollLeft });
            }
            el = el.parentElement;
        }
        lista.push({
            el: null,
            top: window.scrollY || document.documentElement.scrollTop || 0,
            left: window.scrollX || document.documentElement.scrollLeft || 0
        });
        return lista;
    }

    function aplicarScroll(lista) {
        (lista || []).forEach(function (item) {
            if (!item.el) {
                window.scrollTo(item.left, item.top);
                return;
            }
            item.el.scrollTop = item.top;
            item.el.scrollLeft = item.left;
        });
    }

    function renderAll() {
        var scroll = capturarScroll();
        garantirEstado();
        if (ehBoletimComposto() && !ehVisaoImpressaoEditavel() && ['pecas', 'formula'].indexOf(estado.passo) >= 0) {
            estado.passo = 'identidade';
        }
        if (ehVisaoImpressaoEditavel() && estado.passo === 'inicio') {
            estado.passo = 'identidade';
        }
        if (estado.modo === 'editar' && estado.origem === 'formulario' && estado.passo === 'inicio') {
            estado.passo = 'revisar';
        }
        renderSteps();
        renderBody();
        atualizarCabecalhoEvento();
        aplicarScroll(scroll);
        requestAnimationFrame(function () { aplicarScroll(scroll); });
        setTimeout(function () { aplicarScroll(scroll); }, 0);
    }

    function agendarMontar() {
        if (montarTimer) clearTimeout(montarTimer);
        montarTimer = setTimeout(montarAgora, 280);
    }

    function fundirFormulasLocais(local, remoto) {
        var out = {};
        Object.keys(remoto || {}).forEach(function (cod) {
            out[cod] = Array.isArray(remoto[cod]) ? remoto[cod].slice() : remoto[cod];
        });
        // Local manda para cada chave presente — inclusive lista vazia (botão Limpar).
        Object.keys(local || {}).forEach(function (cod) {
            out[cod] = Array.isArray(local[cod]) ? local[cod].slice() : local[cod];
        });
        return out;
    }

    function montarAgora() {
        garantirEstado();
        flushTituloAberto();
        sincronizarEventosIdsDoQuadro();
        aplicarNomesNoRascunho(estado && estado.rascunho_preservado);
        aplicarMateriaUnicaNasPecas();
        zerarFormulasDemonstrativoNotas();
        if (estado.bloco_calc && !ehDemonstrativoNotas()) gravarTokensBlocoAberto();
        var versaoEnvio = formulaVersao;
        var nomesLocal = JSON.parse(JSON.stringify(estado.nomes_blocos || {}));
        var escalasLocal = JSON.parse(JSON.stringify(estado.escalas_blocos || {}));
        var formulaLocal = JSON.parse(JSON.stringify(estado.formulas_blocos || {}));
        var excecoesLocal = JSON.parse(JSON.stringify(estado.formulas_materias_blocos || {}));
        var tokensLocal = (estado.formula_tokens || []).slice();
        var blocoLocal = estado.bloco_calc || '';
        var materiaLocal = Number(estado.materia_calc || 0);
        var eventosIdsLocal = Array.isArray(estado.eventos_avaliativos_ids)
            ? estado.eventos_avaliativos_ids.map(Number).filter(function (id) { return id > 0; })
            : [];
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('wizard_estado', JSON.stringify(estado));
        var alunoPedido = Number(estado.aluno_preview_id || 0);
        var seq = ++montarSeq;
        return fetch(urlWizardMontar, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json();         }).then(function (j) {
            if (seq !== montarSeq) return j || null;
            if (!j || !j.success) {
                renderResumo((j && j.resumo) || 'Não deu para montar o rascunho.', (j && j.erros) || [(j && j.error) || 'Falha ao montar.']);
                if (estado && (estado.passo === 'formula' || estado.passo === 'revisar')) renderRevisarDinamico();
                return j || null;
            }
            if (Number((estado && estado.aluno_preview_id) || 0) !== alunoPedido) return j;
            if (j.estado) {
                var arredLocal = (estado && estado.grupo_linha && estado.grupo_linha.arredondamento) || '';
                var remoto = j.estado;
                var nomesAgora = {};
                var nomesServidor = (remoto.nomes_blocos && typeof remoto.nomes_blocos === 'object' && !Array.isArray(remoto.nomes_blocos))
                    ? remoto.nomes_blocos
                    : {};
                var fonteNomes = (estado && estado.nomes_blocos && typeof estado.nomes_blocos === 'object' && !Array.isArray(estado.nomes_blocos))
                    ? estado.nomes_blocos
                    : (nomesLocal || {});
                Object.keys(fonteNomes).forEach(function (cod) {
                    if (!cod || cod === '_semanal' || /^s[1-8]$/.test(cod)) return;
                    if (colunaDoRascunhoVemDeEvento(j.rascunho, cod)) return;
                    var nomeVivo = String(fonteNomes[cod] || '').trim().slice(0, 60);
                    if (nomeVivo) nomesAgora[cod] = nomeVivo;
                });
                Object.keys(nomesServidor).forEach(function (cod) {
                    if (!colunaDoRascunhoVemDeEvento(j.rascunho, cod)) return;
                    var nomeSrv = String(nomesServidor[cod] || '').trim().slice(0, 60);
                    if (nomeSrv) nomesAgora[cod] = nomeSrv;
                });
                remoto.nomes_blocos = nomesAgora;
                var escalasAgora = {};
                var fonteEscalas = (estado && estado.escalas_blocos && typeof estado.escalas_blocos === 'object' && !Array.isArray(estado.escalas_blocos))
                    ? estado.escalas_blocos
                    : (escalasLocal || {});
                Object.keys(fonteEscalas).forEach(function (cod) {
                    if (!cod || cod === '_semanal' || /^s[1-8]$/.test(cod)) return;
                    var escV = Number(fonteEscalas[cod]);
                    if (!isFinite(escV) || escV < 0.01) return;
                    escalasAgora[cod] = Math.min(999, Math.round(escV * 100) / 100);
                });
                // Se o remoto trouxe escala reidratada e o local não tem, mantém a remota.
                Object.keys(remoto.escalas_blocos || {}).forEach(function (cod) {
                    if (escalasAgora[cod] != null) return;
                    var escR = Number((remoto.escalas_blocos || {})[cod]);
                    if (!isFinite(escR) || escR < 0.01) return;
                    escalasAgora[cod] = Math.min(999, Math.round(escR * 100) / 100);
                });
                remoto.escalas_blocos = escalasAgora;
                var limposAgora = {};
                var fonteLimpos = (estado && estado.nomes_limpos && typeof estado.nomes_limpos === 'object' && !Array.isArray(estado.nomes_limpos))
                    ? estado.nomes_limpos : {};
                Object.keys(fonteLimpos).forEach(function (cod) {
                    if (!cod || cod === '_semanal' || /^s[1-8]$/.test(cod) || nomesAgora[cod]) return;
                    if (fonteLimpos[cod]) limposAgora[cod] = 1;
                });
                remoto.nomes_limpos = limposAgora;
                if (versaoEnvio !== formulaVersao) {
                    remoto.formulas_blocos = estado.formulas_blocos || {};
                    remoto.formulas_materias_blocos = estado.formulas_materias_blocos || {};
                    remoto.formula_tokens = (estado.formula_tokens || []).slice();
                    remoto.bloco_calc = estado.bloco_calc || '';
                    remoto.materia_calc = Number(estado.materia_calc || 0);
                } else {
                remoto.formulas_blocos = fundirFormulasLocais(formulaLocal, remoto.formulas_blocos);
                remoto.formulas_materias_blocos = excecoesLocal;
                if (blocoLocal && materiaLocal > 0) {
                    remoto.formula_tokens = tokensLocal.slice();
                    remoto.bloco_calc = blocoLocal;
                    remoto.materia_calc = materiaLocal;
                } else if (blocoLocal) {
                    // Inclusive tokens vazios: Limpar não pode ser sobrescrito pelo remoto.
                    remoto.formula_tokens = Array.isArray(tokensLocal) ? tokensLocal.slice() : [];
                    remoto.bloco_calc = blocoLocal;
                    remoto.materia_calc = 0;
                    if (!remoto.formulas_blocos || typeof remoto.formulas_blocos !== 'object') {
                        remoto.formulas_blocos = {};
                    }
                    remoto.formulas_blocos[blocoLocal] = remoto.formula_tokens.slice();
                }
                }
                var modosLocal = modosGrupoComoObjeto(estado && estado.grupo_linha ? estado.grupo_linha.modos : null);
                var modoLocal = (estado && estado.grupo_linha && estado.grupo_linha.modo === 'soma') ? 'soma' : 'media';
                if (!remoto.grupo_linha) remoto.grupo_linha = grupoLinhaPadrao();
                if (Object.keys(modosLocal).length) remoto.grupo_linha.modos = modosLocal;
                if (estado && estado.grupo_linha && estado.grupo_linha.ativo) {
                    remoto.grupo_linha.modo = modoLocal;
                    remoto.grupo_linha.ativo = true;
                }
                // Não perde escolha local de arredondamento da área se o remoto voltar sem ela.
                if ((arredLocal === 'filhas' || arredLocal === 'mae')
                    && (!remoto.grupo_linha || (remoto.grupo_linha.arredondamento !== 'filhas'
                        && remoto.grupo_linha.arredondamento !== 'mae'))) {
                    if (!remoto.grupo_linha) remoto.grupo_linha = grupoLinhaPadrao();
                    remoto.grupo_linha.arredondamento = arredLocal;
                }
                var nomeManual = estado && estado.nome_automatico === false ? String(estado.nome || '') : null;
                var linhasLocais = Array.isArray(estado && estado.linhas_layout)
                    ? JSON.parse(JSON.stringify(estado.linhas_layout))
                    : [];
                var ordemLocal = Array.isArray(estado && estado.colunas_ordem) ? estado.colunas_ordem.slice() : [];
                var blocosCalcLocal = Array.isArray(estado && estado.blocos_calc) ? estado.blocos_calc.slice() : [];
                var ocultasLocal = Array.isArray(estado && estado.colunas_ocultas) ? estado.colunas_ocultas.slice() : [];
                var cabecalhosManuaisLocal = Number(estado && estado.cabecalhos_manuais || 0) === 1 ? 1 : 0;
                var chipsManuaisLocal = Number(estado && estado.quadro_chips_manuais || 0) === 1 ? 1 : 0;
                var titulosOkLocal = (estado && estado.titulos_periodo_ok && typeof estado.titulos_periodo_ok === 'object')
                    ? JSON.parse(JSON.stringify(estado.titulos_periodo_ok))
                    : {};
                // IDs marcados enquanto o fetch estava no ar (ex.: REC/PF) — não podem sumir.
                var eventosDuranteFetch = Array.isArray(estado && estado.eventos_avaliativos_ids)
                    ? estado.eventos_avaliativos_ids.map(Number).filter(function (id) { return id > 0; })
                    : [];
                estado = remoto;
                estado.linhas_layout = linhasLocais;
                estado.titulos_periodo_ok = titulosOkLocal;
                // Chips excluídos com X não podem voltar no remount do servidor.
                var ocultasRemotas = Array.isArray(estado.colunas_ocultas) ? estado.colunas_ocultas : [];
                var ocultasFundidas = [];
                var vistoOculta = {};
                ocultasLocal.concat(ocultasRemotas).forEach(function (cod) {
                    var low = String(cod || '').toLowerCase();
                    if (!low || vistoOculta[low]) return;
                    vistoOculta[low] = true;
                    ocultasFundidas.push(cod);
                });
                estado.colunas_ocultas = ocultasFundidas;
                // Não perde os eventos já no quadro após remount.
                var eventosRemotos = Array.isArray(estado.eventos_avaliativos_ids)
                    ? estado.eventos_avaliativos_ids.map(Number).filter(function (id) { return id > 0; })
                    : [];
                if (eventosIdsLocal.length || eventosDuranteFetch.length) {
                    var setEv = {};
                    var fundido = [];
                    eventosIdsLocal.concat(eventosDuranteFetch).concat(eventosRemotos).forEach(function (id) {
                        if (!id || setEv[id]) return;
                        setEv[id] = true;
                        fundido.push(id);
                    });
                    estado.eventos_avaliativos_ids = fundido;
                }
                if (cabecalhosManuaisLocal || (linhasLocais && linhasLocais.length)) {
                    estado.cabecalhos_manuais = 1;
                    cabecalhosManuaisLocal = 1;
                }
                if (chipsManuaisLocal || cabecalhosManuaisLocal || (ordemLocal && ordemLocal.length)) {
                    estado.quadro_chips_manuais = 1;
                    chipsManuaisLocal = 1;
                } else if (!chipsManuaisLocal && (estado.quadro_livre || estado.origem === 'quadro-impressao')) {
                    // Só zera o quadro vazio do zero — nunca apaga títulos já montados.
                    if (!(linhasLocais && linhasLocais.length) && !(ordemLocal && ordemLocal.length)) {
                        estado.quadro_chips_manuais = 0;
                        zerarQuadroColunasSeAutomatico();
                        ordemLocal = [];
                        linhasLocais = [];
                        estado.linhas_layout = [];
                    } else {
                        estado.quadro_chips_manuais = 1;
                        chipsManuaisLocal = 1;
                    }
                }
                var blocosRemotos = Array.isArray(estado.blocos_calc) ? estado.blocos_calc.slice() : [];
                blocosCalcLocal.forEach(function (cod) {
                    cod = String(cod || '');
                    if (!cod || blocosRemotos.indexOf(cod) >= 0 || colunaOculta(cod)) return;
                    blocosRemotos.push(cod);
                });
                blocosRemotos = blocosRemotos.filter(function (cod) { return !colunaOculta(cod); });
                estado.blocos_calc = blocosRemotos;
                if (estado.formulas_blocos && typeof estado.formulas_blocos === 'object') {
                    Object.keys(estado.formulas_blocos).forEach(function (cod) {
                        if (colunaOculta(cod)) delete estado.formulas_blocos[cod];
                    });
                }
                var ordemRemota = Array.isArray(estado.colunas_ordem) ? estado.colunas_ordem : [];
                // Quadro do zero: não importa colunas remotas que o admin não adicionou.
                if (!(cabecalhosManuaisLocal || (estado.quadro_livre || estado.origem === 'quadro-impressao'))) {
                    ordemRemota.forEach(function (cod, idx) {
                        if (!cod || colunaOculta(cod)) return;
                        if (ordemLocal.indexOf(cod) >= 0) return;
                        var anterior = idx > 0 ? ordemRemota[idx - 1] : '';
                        var pos = anterior ? ordemLocal.indexOf(anterior) : -1;
                        if (pos >= 0) ordemLocal.splice(pos + 1, 0, cod);
                        else ordemLocal.push(cod);
                    });
                }
                blocosRemotos.forEach(function (cod) {
                    if (cod && !colunaOculta(cod) && ordemLocal.indexOf(cod) < 0) ordemLocal.push(cod);
                });
                estado.colunas_ordem = ordemLocal.filter(function (cod) { return !colunaOculta(cod); });
                // Não remove componentes ocultos do rascunho: eles ficam para a fórmula.
                if (nomeManual !== null) {
                    estado.nome = nomeManual;
                    estado.nome_automatico = false;
                }
                garantirEstado();
                atualizarCabecalhoEvento();
                var campoNome = document.getElementById('bw-nome');
                if (campoNome && document.activeElement !== campoNome) {
                    campoNome.value = String(estado.nome || '');
                }
            }
            rascunhoAtual = j.rascunho || null;
            garantirArredondamentoGrupoNoRascunho(rascunhoAtual);
            gravarEscolhasGrupoNoRascunho(rascunhoAtual);
            limparOrdemColunasFantasma();
            previewAtual = j.preview || previewAtual;
            if (previewAtual && Array.isArray(previewAtual.colunas)) {
                previewAtual.colunas = previewAtual.colunas.filter(function (c) {
                    return c && c.codigo && !colunaOculta(c.codigo);
                });
            }
            aplicarOrdemNoPreview(previewAtual);
            aplicarTitulosDosSubeventos();
            if (previewAtual && estado && estado.nomes_blocos) {
                Object.keys(estado.nomes_blocos).forEach(function (cod) {
                    if (tituloSubeventoDaColuna(cod)) return;
                    pintarTituloNasColunas(cod, estado.nomes_blocos[cod]);
                });
            }
            formulasDisp = j.formulas_disponiveis || formulasDisp;
            renderResumo(j.resumo, j.erros || []);
            var materiaUnicaEl = document.getElementById('bw-materia-unica');
            if (materiaUnicaEl) materiaUnicaEl.checked = !!estado.materia_unica;
            if (estado && (estado.passo === 'formula' || estado.passo === 'revisar')) {
                renderRevisarDinamico();
            }
            if (estado && estado.passo === 'formula' && estado.bloco_calc && !ehDemonstrativoNotas()) {
                renderFormulaCanvas();
                atualizarBannerExcecao();
                renderExcecoesMateria();
                var pal = document.getElementById('bw-formula-pecas-pal');
                if (pal) pal.innerHTML = htmlPalettePecasExibir();
            }
            return j;
        }).catch(function () {
            renderResumo('Não deu para falar com o servidor.', ['Recarregue a página (Cmd+Shift+R) e tente de novo.']);
            if (estado && (estado.passo === 'formula' || estado.passo === 'revisar')) renderRevisarDinamico();
            return null;
        });
    }

    function abrirWizard() {
        if (!pageMode) {
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        // Ao abrir pelo Boletim (Montar quadro), descarta sessão antiga de Evento de Notas antes de restaurar.
        var veioDoBoletimLayout = retornoFluxo === 'boletim-layout'
            || Number(root.getAttribute('data-layout-boletim-id') || 0) > 0;
        if (veioDoBoletimLayout) {
            try {
                sessionStorage.removeItem('boletim_assistente_estado_navegacao');
                sessionStorage.removeItem('boletim_assistente_rascunho');
            } catch (eSess) { /* ignore */ }
        }
        garantirEstado();
        forcarContextoImpressaoBoletim();
        if (!veioDoBoletimLayout && root.getAttribute('data-continuar') === '1') {
            restaurarEstadoNavegacao();
            forcarContextoImpressaoBoletim();
        }
        if (ehVisaoImpressaoEditavel() && estado && (!estado.passo || estado.passo === 'inicio' || estado.passo === 'pecas' && usaEventosAvaliativosNaNota())) {
            // Se veio preso no fluxo de notas, manda para Demonstrativos.
            if (estado.passo === 'inicio' || (estado.exibir_em === 'notas')) {
                estado.passo = 'identidade';
            }
            if (['inicio'].indexOf(estado.passo) >= 0) estado.passo = 'identidade';
        }
        renderAll();
        atualizarCabecalhoEvento();
        if (iniciado) return;
        iniciado = true;
        if (dadosIniciais.estado) {
            if (root.getAttribute('data-continuar') === '1' && retornoFluxo !== 'boletim-layout') {
                var rascunhoLocal = rascunhoSessaoCompativel();
                if (rascunhoLocal) {
                    aplicarRascunhoNoWizard(rascunhoLocal);
                    aplicarQuadroRecemCriado();
                    forcarContextoImpressaoBoletim();
                    renderAll();
                    renderResumo('Esta é a configuração já iniciada neste navegador. Confira e salve. Salvar atualiza este evento e não cria outro.', []);
                    return;
                }
            }
            aplicarQuadroRecemCriado();
            forcarContextoImpressaoBoletim();
            if (ehVisaoImpressaoEditavel() && estado && (estado.passo === 'inicio' || !estado.passo)) {
                estado.passo = 'identidade';
            }
            renderAll();
            renderResumo(dadosIniciais.resumo, dadosIniciais.erros || []);
            return;
        }
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('regra_id', String(regraId));
        var estadoFormularioPost = estadoFormularioParaPost();
        if (estadoFormularioPost) fd.append('estado_formulario', estadoFormularioPost);
        fetch(urlWizardInicio, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.text().then(function (t) {
                var j = null;
                try { j = JSON.parse(t); } catch (e) { j = null; }
                if (!j) {
                    return { success: false, error: 'Catálogo indisponível (HTTP ' + r.status + ').' };
                }
                return j;
            });
        }).then(function (j) {
            if (!j || !j.success) {
                renderResumo((j && j.error) || 'Catálogo parcial. Recarregue a página se algo não aparecer.', []);
                return;
            }
            catalogo = j.catalogo || catalogo;
            var rascunhoChat = (estado && estado.origem === 'chat') ? rascunhoAtual : null;
            estado = j.estado || garantirEstado();
            if (rascunhoChat) {
                estado.origem = 'chat';
                estado.rascunho_preservado = rascunhoChat;
                estado.passo = 'revisar';
                rascunhoAtual = rascunhoChat;
            } else if (!rascunhoAtual) {
                rascunhoAtual = j.rascunho;
            }
            formulasDisp = j.formulas_disponiveis || formulasDisp;
            if (j.preview) previewAtual = j.preview;
            if (j.disponivel_ia === false) disponivelIa = false;
            renderAll();
            if (!rascunhoChat) {
                renderResumo(j.resumo, j.erros || []);
            }
        }).catch(function () {
            renderResumo('Catálogo não carregou.', ['Recarregue a página e tente de novo.']);
        });
    }

    function fecharWizard() {
        if (pageMode) {
            window.location.href = returnUrl;
            return;
        }
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function guardarRascunhoAssistente(r) {
        try {
            sessionStorage.setItem('boletim_assistente_rascunho', JSON.stringify(r));
            return true;
        } catch (e) {
            return false;
        }
    }

    function aplicarRascunhoNaConfiguracao(r, gerarNotas) {
        if (!r || typeof r !== 'object') return false;
        var infoMinSalvar = minimaExibida();
        r.nota_minima_aprovacao = infoMinSalvar.valor;
        r.nota_minima_origem = infoMinSalvar.origem;
        r.nota_minima_regra_nome = infoMinSalvar.nome || '';
        var comps = normalizarComponentesRascunho(r.componentes);
        if (!comps.length) {
            renderResumo('O assistente montou o evento, mas sem blocos.', [ehDemonstrativoNotas()
                ? 'Volte em Tipo de Notas, marque as colunas e monte a ordem em Colunas. Depois clique de novo em Concluir e aplicar.'
                : 'Volte em Tipo de Notas, marque as colunas e monte a média em Fórmula. Depois clique de novo em Concluir e aplicar.']);
            return false;
        }
        r.componentes = comps;
        guardarRascunhoAssistente(r);
        if (document.getElementById('form-regra-boletim')) {
            aplicarRascunho(r);
            return 'form';
        }
        if (pageMode && urlSalvar) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = urlSalvar;
            form.style.display = 'none';
            function add(name, value) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value == null ? '' : String(value);
                form.appendChild(input);
            }
            function addArray(name, values) {
                (Array.isArray(values) ? values : []).forEach(function (value) {
                    if (Number(value) > 0) add(name, value);
                });
            }
            add('_token', csrf);
            // Sempre regrava a regra aberta (layout SM). Senão cria órfã e a tela reabre a antiga sem REC.
            var regraIdSalvar = Number(r.regra_id || (estado && estado.regra_id) || regraId || 0) || 0;
            add('regra_id', regraIdSalvar > 0 ? regraIdSalvar : 0);
            add('regra_nome', r.nome || 'Evento padrão da escola');
            add('regra_codigo', r.codigo || '');
            add('regra_descricao_curta', r.descricao_curta || '');
            add('formula_final', r.formula_final || '');
            add('formula_materias_json', r.formula_materias_json || '');
            var exibirPost = (retornoFluxo === 'boletim-layout' || r.exibir_em === 'boletim' || r.quadro_livre || r.origem === 'quadro-impressao')
                ? 'boletim'
                : 'notas';
            add('exibir_em', exibirPost);
            add('finalidade', r.finalidade === 'complementar' ? 'complementar' : 'oficial');
            var layoutPostJs = parseInt(root.getAttribute('data-layout-boletim-id') || '0', 10)
                || Number((estado && estado.layout_boletim_id) || (r.layout_boletim_id) || 0)
                || 0;
            var boletimPostJs = Number(r.boletim_id || (estado && estado.boletim_id) || 0) || 0;
            // Quadro de impressão: não envia o ID do layout como modelo de boletim.
            if (layoutPostJs > 0 && boletimPostJs === layoutPostJs) boletimPostJs = 0;
            if (exibirPost === 'boletim' && layoutPostJs > 0 && !boletimPostJs) boletimPostJs = 0;
            add('boletim_id', boletimPostJs > 0 ? boletimPostJs : '');
            add('grupo_regras_notas_id', r.grupo_regras_notas_id || (estado && estado.grupo_regras_notas_id) || '');
            add('ano_letivo', r.ano_letivo || anoLetivoPadraoJs());
            add('bimestre', r.bimestre || '');
            add('default_data_inicio', r.default_data_inicio || '');
            add('default_data_fim', r.default_data_fim || '');
            add('round_mode', r.round_mode || 'none');
            add('decimal_places', (Number(r.decimal_places) === 1 ? 1 : 2));
            var infoMin = minimaExibida();
            r.nota_minima_aprovacao = infoMin.valor;
            r.nota_minima_origem = infoMin.origem;
            r.nota_minima_regra_nome = infoMin.nome || '';
            add('nota_minima_aprovacao', infoMin.valor);
            if (r.usar_resultado_aprovacao == null || Number(r.usar_resultado_aprovacao) === 1) {
                add('usar_resultado_aprovacao', '1');
            }
            if (r.vis_aluno == null || Number(r.vis_aluno) === 1) add('vis_aluno', '1');
            if (r.vis_pais == null || Number(r.vis_pais) === 1) add('vis_pais', '1');
            if (r.vis_coordenacao == null || Number(r.vis_coordenacao) === 1) add('vis_coordenacao', '1');
            addArray('materias_ids[]', r.materias_ids);
            addArray('series_ids[]', r.series_ids);
            addArray('turmas_ids[]', r.turmas_ids);
            add('componentes_json', JSON.stringify(comps));
            var alunoSomenteId = Number(r._gerar_somente_aluno_id || 0);
            delete r._gerar_somente_aluno;
            delete r._gerar_somente_aluno_id;
            var layoutBoletimIdPost = layoutPostJs
                || parseInt(root.getAttribute('data-layout-boletim-id') || '0', 10)
                || 0;
            if (layoutBoletimIdPost > 0) {
                add('layout_boletim_id', String(layoutBoletimIdPost));
            }
            // Enriquecer o rascunho ANTES do JSON (eventos/layout não podem sumir no POST).
            if (exibirPost === 'boletim') {
                r.exibir_em = 'boletim';
                r.layout_boletim_id = layoutBoletimIdPost;
                if (Number(r.boletim_id || 0) === layoutBoletimIdPost) r.boletim_id = 0;
                if (estado) {
                    if (estado.quadro_livre) r.quadro_livre = 1;
                    if (estado.origem) r.origem = estado.origem;
                    if (estado.modelo_santa_maria) r.modelo_santa_maria = 1;
                    if (Array.isArray(estado.eventos_avaliativos_ids)) {
                        r.eventos_avaliativos_ids = estado.eventos_avaliativos_ids.slice();
                    }
                    if (estado.eventos_avaliativos_subs && typeof estado.eventos_avaliativos_subs === 'object') {
                        r.eventos_avaliativos_subs = estado.eventos_avaliativos_subs;
                    }
                    if (Array.isArray(estado.linhas_layout)) r.linhas_layout = estado.linhas_layout;
                    if (Array.isArray(estado.colunas_ordem)) r.colunas_ordem = estado.colunas_ordem;
                    if (estado.formulas_blocos) r.formulas_blocos = estado.formulas_blocos;
                    if (estado.blocos_calc) r.blocos_calc = estado.blocos_calc;
                    if (estado.nomes_blocos) r.nomes_blocos = estado.nomes_blocos;
                    // Flags do quadro: sem isso, ao reabrir some o título do período.
                    r.cabecalhos_manuais = Number(estado.cabecalhos_manuais || 0) === 1
                        || (Array.isArray(estado.linhas_layout) && estado.linhas_layout.length)
                        ? 1 : 0;
                    r.quadro_chips_manuais = Number(estado.quadro_chips_manuais || 0) === 1
                        || (Array.isArray(estado.colunas_ordem) && estado.colunas_ordem.length)
                        || r.cabecalhos_manuais
                        ? 1 : 0;
                    if (estado.titulos_periodo_ok && typeof estado.titulos_periodo_ok === 'object') {
                        r.titulos_periodo_ok = estado.titulos_periodo_ok;
                    }
                }
            }
            // Campos dedicados: o servidor prioriza estes sobre o JSON do rascunho.
            var linhasPost = Array.isArray(r.linhas_layout) ? r.linhas_layout
                : (estado && Array.isArray(estado.linhas_layout) ? estado.linhas_layout : []);
            var ordemPost = Array.isArray(r.colunas_ordem) ? r.colunas_ordem
                : (estado && Array.isArray(estado.colunas_ordem) ? estado.colunas_ordem : []);
            var titulosOkPost = (r.titulos_periodo_ok && typeof r.titulos_periodo_ok === 'object')
                ? r.titulos_periodo_ok
                : ((estado && estado.titulos_periodo_ok && typeof estado.titulos_periodo_ok === 'object')
                    ? estado.titulos_periodo_ok : {});
            add('linhas_layout_json', JSON.stringify(linhasPost || []));
            add('colunas_ordem_json', JSON.stringify(ordemPost || []));
            add('titulos_periodo_ok_json', JSON.stringify(titulosOkPost || {}));
            var ocultasPost = Array.isArray(estado && estado.colunas_ocultas) ? estado.colunas_ocultas
                : (Array.isArray(r.colunas_ocultas) ? r.colunas_ocultas : []);
            add('colunas_ocultas_json', JSON.stringify(ocultasPost || []));
            r.colunas_ocultas = ocultasPost.slice();
            // Dom/estado no clique do Aplicar — nunca confiar só no rascunho remoto do montar.
            try { sincronizarSelecaoEventosAvulsosDoDom({ preservarLayout: true }); } catch (eEvSave) { /* ignore */ }
            var eventosPost = [];
            var setEvPost = {};
            function pushEvPost(id) {
                id = Number(id) || 0;
                if (id <= 0 || setEvPost[id]) return;
                setEvPost[id] = true;
                eventosPost.push(id);
            }
            (Array.isArray(estado && estado.eventos_avaliativos_ids) ? estado.eventos_avaliativos_ids : []).forEach(pushEvPost);
            (Array.isArray(r.eventos_avaliativos_ids) ? r.eventos_avaliativos_ids : []).forEach(pushEvPost);
            r.eventos_avaliativos_ids = eventosPost.slice();
            if (estado) estado.eventos_avaliativos_ids = eventosPost.slice();
            add('eventos_avaliativos_ids_json', JSON.stringify(eventosPost));
            if (Number(r.cabecalhos_manuais || 0) === 1 || (linhasPost && linhasPost.length)) {
                add('cabecalhos_manuais', '1');
            }
            if (Number(r.quadro_chips_manuais || 0) === 1 || (ordemPost && ordemPost.length) || (linhasPost && linhasPost.length)) {
                add('quadro_chips_manuais', '1');
            }
            if ((!linhasPost || !linhasPost.length) && Number((estado && estado.limpar_titulos_periodo) || 0) === 1) {
                add('limpar_titulos_periodo', '1');
            }
            add('assistente_rascunho', JSON.stringify(r));
            add('origem_assistente', '1');
            if (retornoFluxo === 'gerar-avaliacoes' || retornoFluxo === 'boletins' || retornoFluxo === 'periodos' || retornoFluxo === 'evento' || retornoFluxo === 'boletim-layout') {
                add('retorno_fluxo', retornoFluxo);
                add('retorno_boletim_id', root.getAttribute('data-boletim-id') || (r.boletim_id || ''));
                add('retorno_ano_letivo', r.ano_letivo || root.getAttribute('data-ano-letivo') || '');
            }
            if (retornoFluxo === 'periodos' || retornoFluxo === 'evento') {
                var listaRetorno = {};
                try { listaRetorno = JSON.parse(root.getAttribute('data-lista') || '{}'); } catch (eLista) { listaRetorno = {}; }
                if (listaRetorno && typeof listaRetorno === 'object') {
                    Object.keys(listaRetorno).forEach(function (chaveLista) {
                        add(chaveLista, listaRetorno[chaveLista]);
                    });
                }
            }
            // Só boletim oficial dispara geração. Demonstrativo = quadro ao vivo (só salva config).
            if (gerarNotas === true && exibirPost === 'boletim') {
                add('gerar_nova_versao', '1');
                if (alunoSomenteId > 0) {
                    add('gerar_somente_aluno_id', alunoSomenteId);
                }
            }
            document.body.appendChild(form);
            form.submit();
            return 'submit';
        }
        try {
            window.location.href = returnUrl;
            return 'redirect';
        } catch (e) {
            renderResumo('Não consegui levar o rascunho para a configuração.', ['Copie a receita e tente de novo.']);
            return false;
        }
    }

    function atualizarBotaoGerarAluno() {
        if (!btnGerarAluno) return;
        // Geração individual só no boletim oficial — demonstrativo é ao vivo.
        var mostrar = pageMode && !ehEventoNotasPreview() && estado
            && estado.exibir_em === 'boletim'
            && estado.passo === 'revisar'
            && Number(estado.aluno_preview_id || 0) > 0;
        btnGerarAluno.classList.toggle('hidden', !mostrar);
    }

    function aplicarRascunhoSeOk(j, msgOk, gerarNotas) {
        if (!j || !j.rascunho) {
            renderResumo((j && (j.error || (j.erros && j.erros[0]))) || 'Não consegui montar o evento.', ['Volte nas etapas anteriores, confira as peças/fontes e tente de novo.']);
            return false;
        }
        flushTituloAberto();
        // Dom é a fonte da verdade dos títulos visíveis no quadro.
        try { sincronizarLinhasLayoutComDom(); } catch (eSyncLin) { /* ignore */ }
        aplicarNomesNoRascunho(j.rascunho);
        aplicarNomesNoRascunho(estado && estado.rascunho_preservado);
        garantirArredondamentoGrupoNoRascunho(j.rascunho);
        gravarEscolhasGrupoNoRascunho(j.rascunho);
        var compsRasc = (j.rascunho && Array.isArray(j.rascunho.componentes)) ? j.rascunho.componentes : [];
        if (j.ok !== true && !compsRasc.length) {
            renderResumo(j.resumo || 'Ainda falta ajustar o evento.', j.erros && j.erros.length ? j.erros : ['O assistente ainda não conseguiu montar um evento válido.']);
            return false;
        }
        if (estado && Array.isArray(estado.linhas_layout)) {
            j.rascunho.linhas_layout = JSON.parse(JSON.stringify(estado.linhas_layout));
            if (estado.linhas_layout.length) {
                j.rascunho.cabecalhos_manuais = 1;
                j.rascunho.quadro_chips_manuais = 1;
            }
        }
        if (estado) {
            if (Number(estado.cabecalhos_manuais || 0) === 1) j.rascunho.cabecalhos_manuais = 1;
            if (Number(estado.quadro_chips_manuais || 0) === 1) j.rascunho.quadro_chips_manuais = 1;
            if (Array.isArray(estado.colunas_ordem)) j.rascunho.colunas_ordem = estado.colunas_ordem.slice();
            if (estado.titulos_periodo_ok && typeof estado.titulos_periodo_ok === 'object') {
                j.rascunho.titulos_periodo_ok = JSON.parse(JSON.stringify(estado.titulos_periodo_ok));
            }
            if (Array.isArray(estado.eventos_avaliativos_ids) && estado.eventos_avaliativos_ids.length) {
                j.rascunho.eventos_avaliativos_ids = estado.eventos_avaliativos_ids.map(Number).filter(function (id) {
                    return id > 0;
                });
            }
            if (estado.eventos_avaliativos_subs && typeof estado.eventos_avaliativos_subs === 'object') {
                j.rascunho.eventos_avaliativos_subs = estado.eventos_avaliativos_subs;
            }
        }
        if (!Number(j.rascunho.boletim_id || 0) && estado && Number(estado.boletim_id || 0)) {
            j.rascunho.boletim_id = Number(estado.boletim_id);
        }
        if (!Number(j.rascunho.grupo_regras_notas_id || 0) && estado && Number(estado.grupo_regras_notas_id || 0)) {
            j.rascunho.grupo_regras_notas_id = Number(estado.grupo_regras_notas_id);
        }
        var dispensaModeloBoletim = !!(estado && (
            estado.exibir_em === 'boletim'
            || estado.origem === 'quadro-impressao'
            || estado.origem === 'santa-maria'
            || estado.quadro_livre
            || estado.modelo_santa_maria
            || Number(estado.layout_boletim_id || 0) > 0
            || retornoFluxo === 'boletim-layout'
        ));
        if (!Number(j.rascunho.boletim_id || 0) && !(estado && estado.exibir_em === 'notas') && !dispensaModeloBoletim) {
            renderResumo('Falta o modelo de boletim.', ['Volte em Identidade e selecione o Boletim.']);
            return false;
        }
        if (gerarNotas === true && Number(estado && estado.aluno_preview_id || 0) > 0 && j.rascunho._gerar_somente_aluno === true) {
            j.rascunho._gerar_somente_aluno_id = Number(estado.aluno_preview_id);
        } else {
            delete j.rascunho._gerar_somente_aluno_id;
        }
        var aplicado = aplicarRascunhoNaConfiguracao(j.rascunho, gerarNotas === true);
        if (!aplicado) {
            return false;
        }
        if (aplicado !== 'submit') {
            renderResumo(msgOk, []);
        }
        return aplicado;
    }

    function avancar() {
        garantirEstado();
        syncIdentidadeFromDom();
        var vis = passosVisiveis();
        var i = 0;
        vis.forEach(function (p, idx) { if (p.id === estado.passo) i = idx; });
        if (estado.passo === 'revisar') {
            if (btnAvancar) btnAvancar.disabled = true;
            if (montarTimer) { clearTimeout(montarTimer); montarTimer = null; }
            try { sincronizarSelecaoEventosAvulsosDoDom({ preservarLayout: true }); } catch (eDomEvAv) { /* ignore */ }
            sincronizarEventosIdsDoQuadro();
            try { sincronizarLinhasLayoutComDom(); } catch (eDomAv) { /* ignore */ }
            var snapLinhasAv = Array.isArray(estado.linhas_layout)
                ? JSON.parse(JSON.stringify(estado.linhas_layout))
                : [];
            var snapOrdemAv = Array.isArray(estado.colunas_ordem) ? estado.colunas_ordem.slice() : [];
            var snapEventosAv = Array.isArray(estado.eventos_avaliativos_ids)
                ? estado.eventos_avaliativos_ids.map(Number).filter(function (id) { return id > 0; })
                : [];
            var snapTitulosAv = (estado.titulos_periodo_ok && typeof estado.titulos_periodo_ok === 'object')
                ? JSON.parse(JSON.stringify(estado.titulos_periodo_ok))
                : {};
            montarAgora().then(function (j) {
                if (estado) {
                    if (snapLinhasAv.length) {
                        estado.linhas_layout = snapLinhasAv;
                        estado.cabecalhos_manuais = 1;
                        estado.quadro_chips_manuais = 1;
                    }
                    if (snapOrdemAv.length) estado.colunas_ordem = snapOrdemAv;
                    if (snapEventosAv.length) estado.eventos_avaliativos_ids = snapEventosAv;
                    estado.titulos_periodo_ok = snapTitulosAv;
                }
                if (j && j.rascunho) {
                    if (snapLinhasAv.length) {
                        j.rascunho.linhas_layout = snapLinhasAv;
                        j.rascunho.cabecalhos_manuais = 1;
                        j.rascunho.quadro_chips_manuais = 1;
                    }
                    if (snapOrdemAv.length) j.rascunho.colunas_ordem = snapOrdemAv;
                    if (snapEventosAv.length) j.rascunho.eventos_avaliativos_ids = snapEventosAv;
                    j.rascunho.titulos_periodo_ok = snapTitulosAv;
                }
                var aplicado = aplicarRascunhoSeOk(j, pageMode ? 'Aplicado. Salvando o evento…' : 'Aplicado no formulário. Revise os blocos e clique em Salvar evento.', true);
                if (!aplicado) {
                    if (btnAvancar) btnAvancar.disabled = false;
                    return;
                }
                // Em página própria o POST de salvar já navega. Não chamar fecharWizard(),
                // senão window.location cancela o submit e o evento não grava.
                if (aplicado !== 'submit') {
                    fecharWizard();
                }
            }).catch(function () {
                if (btnAvancar) btnAvancar.disabled = false;
                renderResumo('Não deu para concluir agora.', ['Recarregue a página e tente de novo.']);
            });
            return;
        }
        if (estado.passo === 'identidade' && estado.exibir_em !== 'notas' && !boletimEscolhido()) {
            var dispensaBoletimAvancar = ehVisaoImpressaoEditavel()
                || ehBoletimComposto()
                || ehQuadroImpressaoLivre()
                || ehModeloSantaMaria()
                || Number(estado.layout_boletim_id || 0) > 0
                || retornoFluxo === 'boletim-layout';
            if (!dispensaBoletimAvancar) {
                renderResumo('', ['Selecione um boletim.']);
                return;
            }
            var layoutAvancar = Number(estado.layout_boletim_id || root.getAttribute('data-layout-boletim-id') || 0);
            if (layoutAvancar > 0) {
                estado.layout_boletim_id = layoutAvancar;
                if (Number(estado.boletim_id || 0) === layoutAvancar) estado.boletim_id = 0;
            }
        }
        if (estado.passo === 'formula' && estado.bloco_calc && !ehDemonstrativoNotas()) {
            var inpNome = document.getElementById('bw-formula-bloco-nome-input');
            var nomeAv = String((inpNome && inpNome.value) || '').trim().slice(0, 60);
            if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
            if (nomeAv) estado.nomes_blocos[estado.bloco_calc] = nomeAv;
            flushEscalaAberta();
            gravarTokensBlocoAberto();
        }
        if (ehDemonstrativoNotas()) zerarFormulasDemonstrativoNotas();
        var next = vis[Math.min(i + 1, vis.length - 1)].id;
        if (estado.origem === 'clonar') {
            if (estado.passo === 'inicio') next = 'identidade';
            else if (estado.passo === 'identidade') next = 'publico';
            else if (estado.passo === 'publico') next = 'revisar';
        }
        estado.passo = next;
        renderAll();
        agendarMontar();
    }

    function voltar() {
        if (!garantirEstado()) return;
        var vis = passosVisiveis();
        var i = 0;
        vis.forEach(function (p, idx) { if (p.id === estado.passo) i = idx; });
        if (i <= 0) return;
        var prev = vis[i - 1].id;
        if (estado.origem === 'clonar') {
            if (estado.passo === 'revisar') prev = 'publico';
            else if (estado.passo === 'publico') prev = 'identidade';
            else if (estado.passo === 'identidade') prev = 'inicio';
        }
        estado.passo = prev;
        renderAll();
    }

    function enviarChat() {
        if (!chatInput) return;
        var texto = (chatInput.value || '').trim();
        if (!texto) return;

        if (!disponivelIa) {
            var pareceReceita = /#\s*RECEITA_BOLETIM\b/i.test(texto) || /\[\[componente\]\]/i.test(texto);
            if (!pareceReceita) {
                appendBubble('assistant', 'Sem TudiCoins, o chat só aplica receita colada. Use os passos à esquerda ou ative TudiCoins no Master.');
                return;
            }
        }

        chatInput.value = '';
        appendBubble('user', texto);

        if (enviando) {
            filaChat.push(texto);
            mostrarStatusFila('Ainda estou fechando o quadro. Já anotei o ajuste e aplico em seguida.');
            return;
        }
        historico.push({ role: 'user', content: texto });
        setChatStatus('Enviando… a IA pode levar até 1 minuto no quadro completo.');
        dispararChat(texto);
    }

    function dispararChat(texto) {
        enviando = true;
        removerStatusFila();
        setChatStatus('Montando o quadro… pode levar um minuto.');

        var loading = document.createElement('div');
        loading.className = 'mr-2 bg-white border border-gray-200 rounded-lg px-3 py-3 inline-flex gap-1.5';
        loading.innerHTML = '<span class="boletim-assistente-dot"></span><span class="boletim-assistente-dot"></span><span class="boletim-assistente-dot"></span>';
        msgsEl.appendChild(loading);
        msgsEl.scrollTop = msgsEl.scrollHeight;

        var fd = new FormData();
        try {
            fd.append('_token', csrf);
            fd.append('mensagem', texto);
            fd.append('regra_id', String(regraId));
            fd.append('historico', JSON.stringify(historico.slice(0, -1)));
            var baseFormulario = rascunhoAtual ? JSON.stringify(enxugarRascunhoParaChat(rascunhoAtual)) : estadoFormularioParaPost();
            if (baseFormulario) fd.append('estado_formulario', baseFormulario);
            var wizardPost = wizardEstadoParaPost();
            if (wizardPost) fd.append('wizard_estado', wizardPost);
        } catch (e) {
            if (loading.parentNode) loading.parentNode.removeChild(loading);
            enviando = false;
            setChatStatus('');
            appendBubble('assistant', 'Não consegui enviar o estado do formulário. Recarregue a página e tente de novo.');
            return;
        }

        var bubble = null;
        var textoStream = '';
        var ac = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timeoutId = setTimeout(function () {
            if (ac) ac.abort();
        }, 150000);

        function processarEvento(ev) {
            var payload = {};
            try { payload = JSON.parse(ev.data || '{}'); } catch (e) {}
            if (ev.event === 'chunk') {
                if (loading.parentNode) loading.parentNode.removeChild(loading);
                if (!bubble) bubble = appendBubble('assistant', '');
                textoStream += (payload.text || '');
                bubble.textContent = textoStream;
                msgsEl.scrollTop = msgsEl.scrollHeight;
            }
            if (ev.event === 'fase' && payload.fase === 'texto_pronto') {
                setChatStatus('Ainda gerando as colunas S1–S8. Espere o passo 7 (Revisar) à esquerda.');
            }
            if (ev.event === 'fase' && payload.fase === 'consultando') {
                setChatStatus('Consultando o catálogo da escola…');
            }
            if (ev.event === 'error') {
                if (loading.parentNode) loading.parentNode.removeChild(loading);
                setChatStatus('');
                appendBubble('assistant', payload.error || 'Não foi possível processar.');
            }
            if (ev.event === 'done') {
                if (loading.parentNode) loading.parentNode.removeChild(loading);
                removerStatusFila();
                setChatStatus('');
                var msg = (payload.mensagem || textoStream || 'Pronto.').trim();
                if (!bubble) bubble = appendBubble('assistant', msg);
                else bubble.textContent = msg;
                historico.push({ role: 'assistant', content: msg });
                if (payload.rascunho && payload.acao === 'rascunho') {
                    aplicarRascunhoNoWizard(payload.rascunho);
                    renderResumo(
                        'Ajuste do chat aplicado no formulário.\n\nRevise e salve o evento quando estiver ok.',
                        payload.erros || []
                    );
                }
            }
        }

        function parsearSse(buffer) {
            var eventos = [];
            var parts = buffer.split('\n\n');
            var restante = parts.pop() || '';
            parts.forEach(function (block) {
                var lines = block.split('\n');
                var eventName = 'message';
                var dataLines = [];
                lines.forEach(function (line) {
                    if (line.indexOf('event:') === 0) eventName = line.slice(6).trim();
                    else if (line.indexOf('data:') === 0) dataLines.push(line.slice(5).trim());
                });
                if (dataLines.length) eventos.push({ event: eventName, data: dataLines.join('\n') });
            });
            return { eventos: eventos, restante: restante };
        }

        var fetchOpts = {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/event-stream' },
            credentials: 'same-origin'
        };
        if (ac) fetchOpts.signal = ac.signal;

        fetch(urlMensagem, fetchOpts).then(function (res) {
            if (!res.ok || !res.body) {
                return res.text().then(function (t) {
                    var j = {};
                    try { j = JSON.parse(t); } catch (e) {}
                    if (loading.parentNode) loading.parentNode.removeChild(loading);
                    setChatStatus('');
                    appendBubble('assistant', (j && j.error) || ('Não consegui falar com a IA (HTTP ' + res.status + '). Tente de novo.'));
                });
            }
            var reader = res.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';
            var recebeuAlgo = false;
            function ler() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        if (buffer.trim()) parsearSse(buffer + '\n\n').eventos.forEach(processarEvento);
                        if (!bubble && !textoStream) {
                            if (loading.parentNode) loading.parentNode.removeChild(loading);
                            setChatStatus('');
                            appendBubble('assistant', recebeuAlgo
                                ? 'A resposta veio incompleta. Envie de novo em uma frase curta (ex.: monte o quadro semanal S1 a S8).'
                                : 'A IA não retornou resposta. Envie de novo — se a mensagem for muito longa, resuma o pedido.');
                        }
                        return;
                    }
                    recebeuAlgo = true;
                    buffer += decoder.decode(result.value, { stream: true });
                    var parsed = parsearSse(buffer);
                    buffer = parsed.restante;
                    parsed.eventos.forEach(processarEvento);
                    return ler();
                });
            }
            return ler();
        }).catch(function (err) {
            if (loading.parentNode) loading.parentNode.removeChild(loading);
            setChatStatus('');
            var abortou = err && (err.name === 'AbortError' || err.message === 'The user aborted a request.');
            appendBubble('assistant', abortou
                ? 'A IA demorou demais para fechar o quadro. Envie de novo o ajuste (ex.: tirar trabalho e participação).'
                : 'Falha de conexão. Recarregue a página e tente de novo.');
        }).finally(function () {
            clearTimeout(timeoutId);
            enviando = false;
            if (chatEnviar) chatEnviar.disabled = false;
            if (filaChat.length) {
                var proximo = filaChat.shift();
                historico.push({ role: 'user', content: proximo });
                if (filaChat.length) {
                    mostrarStatusFila('Aplicando o próximo ajuste…');
                } else {
                    removerStatusFila();
                }
                dispararChat(proximo);
                return;
            }
            removerStatusFila();
            if (chatInput) chatInput.focus();
        });
    }

    document.addEventListener('click', function (ev) {
        var link = ev.target && ev.target.closest ? ev.target.closest('a') : null;
        if (!link || !root.contains(link)) return;
        var href = link.getAttribute('href') || '';
        if (href.indexOf('/admin/quadros-notas') === -1) return;
        guardarEstadoParaQuadro();
    }, true);
    if (toggle) toggle.addEventListener('click', abrirWizard);
    if (btnFechar) btnFechar.addEventListener('click', fecharWizard);
    if (btnVoltar) btnVoltar.addEventListener('click', voltar);
    if (btnAvancar) btnAvancar.addEventListener('click', avancar);
    if (btnGerarAluno) {
        btnGerarAluno.addEventListener('click', function () {
            garantirEstado();
            var aluno = alunoPreviewSelecionado();
            if (!aluno || Number(aluno.id || 0) <= 0) {
                renderResumo('Escolha o aluno no demonstrativo.', ['Use o campo Simular notas com aluno.']);
                return;
            }
            var nome = labelAlunoPreview(aluno);
            if (!confirm('Gerar notas e boletim só de ' + nome + '? Os outros alunos não serão recalculados.')) {
                return;
            }
            btnGerarAluno.disabled = true;
            renderResumo('Gerando só este aluno...', []);
            montarAgora().then(function (j) {
                if (j && j.rascunho) j.rascunho._gerar_somente_aluno = true;
                var aplicado = aplicarRascunhoSeOk(j, 'Gerando só este aluno…', true);
                if (!aplicado) btnGerarAluno.disabled = false;
            }).catch(function () {
                btnGerarAluno.disabled = false;
                renderResumo('Não deu para gerar só este aluno.', ['Recarregue a página e tente de novo.']);
            });
        });
    }
    if (btnAplicar) {
        btnAplicar.addEventListener('click', function () {
            garantirEstado();
            // Cancela montar agendado (checkbox REC) — senão a race descarta o Aplicar.
            if (montarTimer) { clearTimeout(montarTimer); montarTimer = null; }
            // Dom dos checkboxes de REC/PF/eventos — senão o Aplicar grava sem a marcação.
            try { sincronizarSelecaoEventosAvulsosDoDom({ preservarLayout: true }); } catch (eDomEv) { /* ignore */ }
            sincronizarEventosIdsDoQuadro();
            // Dom → estado antes do snapshot (títulos visíveis não podem depender só da memória).
            try { sincronizarLinhasLayoutComDom(); } catch (eDomSnap) { /* ignore */ }
            // Congela o quadro visual ANTES do montar (senão o remount perde título do período).
            var snapLinhas = Array.isArray(estado.linhas_layout)
                ? JSON.parse(JSON.stringify(estado.linhas_layout))
                : [];
            var snapOrdem = Array.isArray(estado.colunas_ordem) ? estado.colunas_ordem.slice() : [];
            var snapCab = Number(estado.cabecalhos_manuais || 0) === 1 || snapLinhas.length ? 1 : 0;
            var snapChips = Number(estado.quadro_chips_manuais || 0) === 1 || snapOrdem.length || snapLinhas.length ? 1 : 0;
            var snapTitulosOk = (estado.titulos_periodo_ok && typeof estado.titulos_periodo_ok === 'object')
                ? JSON.parse(JSON.stringify(estado.titulos_periodo_ok))
                : {};
            var snapEventos = Array.isArray(estado.eventos_avaliativos_ids)
                ? estado.eventos_avaliativos_ids.map(Number).filter(function (id) { return id > 0; })
                : [];
            renderResumo('Montando o quadro...', []);
            montarAgora().then(function (j) {
                if (estado) {
                    if (snapLinhas.length) {
                        estado.linhas_layout = snapLinhas;
                        estado.cabecalhos_manuais = 1;
                        estado.quadro_chips_manuais = 1;
                    } else if (snapCab) {
                        estado.cabecalhos_manuais = 1;
                    }
                    if (snapOrdem.length) {
                        estado.colunas_ordem = snapOrdem;
                        estado.quadro_chips_manuais = 1;
                    } else if (snapChips) {
                        estado.quadro_chips_manuais = 1;
                    }
                    estado.titulos_periodo_ok = snapTitulosOk;
                    if (snapEventos.length) estado.eventos_avaliativos_ids = snapEventos;
                }
                if (j && j.rascunho) {
                    if (snapLinhas.length) {
                        j.rascunho.linhas_layout = snapLinhas;
                        j.rascunho.cabecalhos_manuais = 1;
                        j.rascunho.quadro_chips_manuais = 1;
                    }
                    if (snapOrdem.length) j.rascunho.colunas_ordem = snapOrdem;
                    j.rascunho.titulos_periodo_ok = snapTitulosOk;
                    if (snapEventos.length) j.rascunho.eventos_avaliativos_ids = snapEventos;
                }
                aplicarRascunhoSeOk(j, pageMode ? 'Configuração salva. As notas não foram geradas.' : 'Aplicado no formulário (ainda não salvo). Salve o evento para valer na simulação.', false);
            });
        });
    }
    if (btnCopiar) {
        btnCopiar.addEventListener('click', function () {
            var base = rascunhoAtual || coletarEstadoFormulario();
            var fd = new FormData();
            fd.append('_token', csrf);
            fd.append('tool', 'formatar_receita');
            fd.append('estado_formulario', JSON.stringify(base));
            fetch(urlFerramenta, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    var rec = j && j.data && j.data.receita;
                    if (!rec) {
                        renderResumo((j && j.error) || 'Não foi possível gerar a receita.', []);
                        return;
                    }
                    return copiarTexto(rec).then(function () {
                        renderResumo('Receita copiada.', []);
                    });
                });
        });
    }
    overlay.addEventListener('click', function (ev) {
        if (pageMode) return;
        if (ev.target === overlay) fecharWizard();
    });
    if (pageMode) abrirWizard();
})();
</script>
