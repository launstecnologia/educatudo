<!-- Header Section -->
<?php $dashboardAsync = !empty($dashboard_async); ?>
<div id="dashboard-async-root"
     class="hidden"
     data-async="<?= $dashboardAsync ? '1' : '0' ?>"
     data-montar-url="<?= $dashboardAsync ? htmlspecialchars(URL . '/dashboard/api/montar') : '' ?>"
     data-mural-url="<?= htmlspecialchars(URL . '/mural-recados') ?>"
     data-primary-color="<?= htmlspecialchars($primary_color ?? '#3b82f6') ?>"></div>
<div class="mb-6 md:mb-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center gap-2">
                <span aria-hidden="true">🎓</span>
                Dashboard
            </h1>
            <p class="text-sm sm:text-base text-gray-600 mt-1">Acompanhe seu progresso e continue aprendendo.</p>
        </div>
        <?php if (!empty($turmas_cursos_select) && is_array($turmas_cursos_select)): ?>
        <div class="flex flex-col items-stretch sm:items-end gap-1 w-full sm:w-auto max-w-full">
            <label for="dashboard-turma-curso" class="text-xs font-medium text-gray-500 uppercase tracking-wide">Curso / turma</label>
            <form method="post" action="<?= htmlspecialchars(URL . '/dashboard/trocar-turma') ?>" class="w-full sm:w-auto min-w-0">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <select id="dashboard-turma-curso" name="turma_id"
                        class="w-full sm:min-w-[220px] sm:max-w-md text-sm sm:text-base font-semibold text-green-700 bg-white border border-green-200 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        onchange="this.form.submit()"
                        title="Selecione o curso ou turma em que está matriculado">
                    <?php
                    $tidAtual = (int) ($aluno['turma_id'] ?? 0);
                    foreach ($turmas_cursos_select as $opt):
                        $tid = (int) ($opt['turma_id'] ?? 0);
                        $lab = (string) ($opt['label'] ?? '');
                    ?>
                        <option value="<?= $tid ?>" <?= $tid === $tidAtual ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php elseif (!empty($aluno['turma_nome'])): ?>
        <div class="flex items-center gap-2 sm:text-right">
            <span class="text-base sm:text-lg font-semibold text-green-600"><?= htmlspecialchars($aluno['turma_nome']) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $primaryHex = $primary_color ?? '#3b82f6'; ?>
<?php if (!empty($dashboard_sliders) && is_array($dashboard_sliders)): ?>
<?php
$sliderModuleRoutes = [
    'chat_tudinha' => '/chat',
    'chat_tudinha_2' => '/chat?ui=v2',
    'educahits' => '/educa-hits',
    'redacoes' => '/redacoes',
    'exercicios' => '/exercicios',
    'jornadas' => '/jornadas',
    'simulados' => '/simulados',
    'notas' => '/notas',
    'boletim' => '/boletim',
];
?>
<div class="mb-6 md:mb-8">
    <div id="dashboard-slider" class="dashboard-slider-loading relative w-full overflow-hidden rounded-xl bg-gradient-to-r from-gray-100 via-gray-50 to-gray-100" style="aspect-ratio: 21 / 9;">
        <?php foreach ($dashboard_sliders as $idx => $slide): ?>
            <?php
            $link = trim((string) ($slide['link_url'] ?? ''));
            $actionType = trim((string) ($slide['action_type'] ?? 'external'));
            $moduleKey = trim((string) ($slide['module_key'] ?? ''));
            if ($actionType === 'module' && $moduleKey !== '' && isset($sliderModuleRoutes[$moduleKey])) {
                $link = URL . $sliderModuleRoutes[$moduleKey];
            }
            $imgUrl = trim((string) ($slide['image_url'] ?? ''));
            $isFirstSlide = $idx === 0;
            $imgAttrs = 'class="dashboard-slide-img w-full h-full object-cover object-center block opacity-0 transition-opacity duration-300" alt="' . htmlspecialchars((string) ($slide['title'] ?? 'Slide')) . '" decoding="async"';
            if ($isFirstSlide) {
                $imgAttrs .= ' fetchpriority="high" src="' . htmlspecialchars($imgUrl) . '"';
            } else {
                $imgAttrs .= ' loading="lazy" data-src="' . htmlspecialchars($imgUrl) . '" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"';
            }
            $imgTag = '<img ' . $imgAttrs . ' onload="this.classList.add(\'opacity-100\');var s=document.getElementById(\'dashboard-slider\');if(s){s.classList.remove(\'dashboard-slider-loading\');var k=s.querySelector(\'.dashboard-slider-skeleton\');if(k){k.style.display=\'none\';}if(this.naturalWidth&&this.naturalHeight){s.style.aspectRatio=this.naturalWidth+\' / \'+this.naturalHeight;}}">';
            ?>
            <div class="dashboard-slide transition-opacity duration-500 absolute inset-0 <?= $idx === 0 ? 'block opacity-100 z-10' : 'hidden opacity-0 z-0' ?>" data-slide-index="<?= (int) $idx ?>">
                <?php if ($link !== ''): ?>
                    <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener noreferrer" class="block w-full h-full">
                        <?= $imgTag ?>
                    </a>
                <?php else: ?>
                    <?= $imgTag ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="dashboard-slider-skeleton pointer-events-none absolute inset-0 flex items-center justify-center bg-gradient-to-r from-gray-100 via-gray-50 to-gray-100">
            <svg class="w-10 h-10 text-gray-300 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
        <?php if (count($dashboard_sliders) > 1): ?>
        <button type="button" id="slider-prev" class="absolute left-3 top-1/2 -translate-y-1/2 z-20 bg-black/40 text-white w-9 h-9 rounded-full hover:bg-black/55">‹</button>
        <button type="button" id="slider-next" class="absolute right-3 top-1/2 -translate-y-1/2 z-20 bg-black/40 text-white w-9 h-9 rounded-full hover:bg-black/55">›</button>
        <div class="absolute bottom-3 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2">
            <?php foreach ($dashboard_sliders as $idx => $slide): ?>
            <button type="button" class="slider-dot w-2.5 h-2.5 rounded-full <?= $idx === 0 ? 'bg-white' : 'bg-white/55' ?>" data-dot="<?= (int) $idx ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php elseif (empty($dashboard_acao_cards)): ?>
<!-- Stats Cards: 2 por linha no mobile, 4 no desktop -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-6 md:mb-8">
    <div class="bg-white rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border-l-4 overflow-hidden flex flex-col" style="border-left-color: <?= htmlspecialchars($primaryHex) ?>">
        <div class="flex flex-row items-start justify-between gap-2 flex-shrink-0">
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wide leading-tight">Exercícios</p>
                <p class="text-xl sm:text-3xl font-bold text-gray-900 mt-0.5 sm:mt-1"><?= $stats['total_exercicios_realizados'] ?></p>
            </div>
            <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0" style="background-color: <?= htmlspecialchars($primaryHex) ?>20">
                <svg class="w-4 h-4 sm:w-6 sm:h-6" style="color: <?= htmlspecialchars($primaryHex) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <?php $exerc_usado = $limites_diarios['exercicios']['usado'] ?? 0; $exerc_limite = $limites_diarios['exercicios']['limite'] ?? 10; $exerc_percentual = min(($exerc_usado / max($exerc_limite, 1)) * 100, 100); ?>
        <div class="mt-3 sm:mt-4 w-full flex-shrink-0">
            <div class="flex justify-between text-xs mb-1">
                <span class="text-gray-500">Hoje</span>
                <span class="font-semibold <?= $exerc_usado >= $exerc_limite ? 'text-red-600' : 'text-gray-600' ?>"><?= $exerc_usado ?>/<?= $exerc_limite ?></span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1.5 sm:h-2 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500" style="width: <?= $exerc_percentual ?>%; background-color: <?= htmlspecialchars($primaryHex) ?>"></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border-l-4 border-green-500 overflow-hidden flex flex-col">
        <div class="flex flex-row items-start justify-between gap-2 flex-shrink-0">
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wide leading-tight">Redações</p>
                <p class="text-xl sm:text-3xl font-bold text-gray-900 mt-0.5 sm:mt-1"><?= $stats['total_redacoes'] ?></p>
            </div>
            <div class="w-9 h-9 sm:w-12 sm:h-12 bg-green-100 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
            </div>
        </div>
        <?php $red_usado = ($limites_diarios['tema']['usado'] ?? 0) + ($limites_diarios['correcao']['usado'] ?? 0); $red_limite = ($limites_diarios['tema']['limite'] ?? 3) + ($limites_diarios['correcao']['limite'] ?? 5); $red_percentual = min(($red_usado / max($red_limite, 1)) * 100, 100); ?>
        <div class="mt-3 sm:mt-4 w-full flex-shrink-0">
            <div class="flex justify-between text-xs mb-1">
                <span class="text-gray-500">Hoje</span>
                <span class="font-semibold <?= $red_usado >= $red_limite ? 'text-red-600' : 'text-gray-600' ?>"><?= $red_usado ?>/<?= $red_limite ?></span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1.5 sm:h-2 overflow-hidden">
                <div class="bg-green-500 h-full rounded-full transition-all duration-500" style="width: <?= $red_percentual ?>%"></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border-l-4 border-purple-500 overflow-hidden flex flex-col">
        <div class="flex flex-row items-start justify-between gap-2 flex-shrink-0">
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wide leading-tight break-words">Chat (<?= htmlspecialchars(LayoutHelper::getIaName()) ?>)</p>
                <p class="text-xl sm:text-3xl font-bold text-gray-900 mt-0.5 sm:mt-1"><?= $stats['total_interacoes_chat'] ?></p>
            </div>
            <div class="w-9 h-9 sm:w-12 sm:h-12 bg-purple-100 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
            </div>
        </div>
        <?php $chat_usado = $limites_diarios['chat']['usado'] ?? 0; $chat_limite = $limites_diarios['chat']['limite'] ?? 10; $chat_percentual = min(($chat_usado / max($chat_limite, 1)) * 100, 100); ?>
        <div class="mt-3 sm:mt-4 w-full flex-shrink-0">
            <div class="flex justify-between text-xs mb-1">
                <span class="text-gray-500">Hoje</span>
                <span class="font-semibold <?= $chat_usado >= $chat_limite ? 'text-red-600' : 'text-gray-600' ?>"><?= $chat_usado ?>/<?= $chat_limite ?></span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1.5 sm:h-2 overflow-hidden">
                <div class="bg-purple-500 h-full rounded-full transition-all duration-500" style="width: <?= $chat_percentual ?>%"></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border-l-4 border-amber-500 overflow-hidden flex flex-col">
        <div class="flex flex-row items-start justify-between gap-2 flex-shrink-0">
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wide leading-tight">Média de acertos</p>
                <p class="text-xl sm:text-3xl font-bold text-gray-900 mt-0.5 sm:mt-1"><?= $stats['media_acertos'] ?>%</p>
            </div>
            <div class="w-9 h-9 sm:w-12 sm:h-12 bg-amber-100 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
        </div>
        <div class="mt-3 sm:mt-4 w-full flex-shrink-0">
            <div class="w-full bg-gray-100 rounded-full h-1.5 sm:h-2 overflow-hidden">
                <div class="bg-amber-500 h-full rounded-full transition-all duration-500" style="width: <?= min((float)$stats['media_acertos'], 100) ?>%"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Cards: Jornadas abertas + Mural + Provas (+ Jornada de redação na X da Questão) -->
<?php
$jornadasAbertas = $jornadas_abertas_count ?? null;
$muralNaoLidos = $mural_recados_nao_lidos_count ?? null;
$provasDisponiveisAgora = $provas_disponiveis_agora_count ?? null;
$dashboardAcaoCards = !empty($dashboard_acao_cards);
$jornadaRedacaoPendentes = $jornada_redacao_pendentes_count ?? null;
$acaoCardsGridClass = $dashboardAcaoCards ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4' : 'grid-cols-1 sm:grid-cols-3';
$countClass = $dashboardAsync ? ' animate-pulse text-gray-300' : '';
$fmtContagem = static function ($valor): string {
    return $valor === null ? '—' : (string) (int) $valor;
};
?>
<div class="grid <?= $acaoCardsGridClass ?> gap-3 sm:gap-4 mb-6 md:mb-8">
    <a href="<?= URL ?>/jornadas?status=em_andamento" class="group bg-white rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border-l-4 border-cyan-500 overflow-hidden flex flex-col">
        <div class="flex flex-row items-start justify-between gap-2 flex-shrink-0">
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wide leading-tight">Jornadas em aberto</p>
                <p class="text-xl sm:text-3xl font-bold text-gray-900 mt-0.5 sm:mt-1<?= $countClass ?>" id="dash-card-jornadas"><?= htmlspecialchars($fmtContagem($jornadasAbertas)) ?></p>
            </div>
            <div class="w-9 h-9 sm:w-12 sm:h-12 bg-cyan-100 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">Jornadas abertas para realizar agora</p>
        <span class="mt-2 text-xs font-medium text-cyan-600 group-hover:text-cyan-700 transition-colors self-end">Abrir jornadas &rarr;</span>
    </a>

    <a href="<?= URL ?>/mural-recados" class="group bg-white rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border-l-4 border-amber-500 overflow-hidden flex flex-col">
        <div class="flex flex-row items-start justify-between gap-2 flex-shrink-0">
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wide leading-tight">Mural de recados</p>
                <p class="text-xl sm:text-3xl font-bold text-gray-900 mt-0.5 sm:mt-1<?= $countClass ?>" id="dash-card-mural"><?= htmlspecialchars($fmtContagem($muralNaoLidos)) ?></p>
            </div>
            <div class="w-9 h-9 sm:w-12 sm:h-12 bg-amber-100 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-8 8h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">Recados que você ainda não leu</p>
        <span class="mt-2 text-xs font-medium text-amber-600 group-hover:text-amber-700 transition-colors self-end">Abrir mural &rarr;</span>
    </a>

    <a href="<?= URL ?>/aluno/provas" class="group bg-white rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border-l-4 border-emerald-500 overflow-hidden flex flex-col">
        <div class="flex flex-row items-start justify-between gap-2 flex-shrink-0">
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wide leading-tight"><?= $dashboardAcaoCards ? 'Provas online' : 'Provas agora' ?></p>
                <p class="text-xl sm:text-3xl font-bold text-gray-900 mt-0.5 sm:mt-1<?= $countClass ?>" id="dash-card-provas"><?= htmlspecialchars($fmtContagem($provasDisponiveisAgora)) ?></p>
            </div>
            <div class="w-9 h-9 sm:w-12 sm:h-12 bg-emerald-100 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">Provas disponíveis neste momento</p>
        <span class="mt-2 text-xs font-medium text-emerald-600 group-hover:text-emerald-700 transition-colors self-end">Ir para provas &rarr;</span>
    </a>

    <?php if ($dashboardAcaoCards): ?>
    <a href="<?= URL ?>/jornada-redacao" class="group bg-white rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-shadow p-4 sm:p-6 border-l-4 border-rose-500 overflow-hidden flex flex-col">
        <div class="flex flex-row items-start justify-between gap-2 flex-shrink-0">
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500 uppercase tracking-wide leading-tight">Jornada de redação</p>
                <p class="text-xl sm:text-3xl font-bold text-gray-900 mt-0.5 sm:mt-1<?= $countClass ?>" id="dash-card-redacao"><?= htmlspecialchars($fmtContagem($jornadaRedacaoPendentes)) ?></p>
            </div>
            <div class="w-9 h-9 sm:w-12 sm:h-12 bg-rose-100 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">Redações disponíveis para escrever agora</p>
        <span class="mt-2 text-xs font-medium text-rose-600 group-hover:text-rose-700 transition-colors self-end">Abrir jornada &rarr;</span>
    </a>
    <?php endif; ?>
</div>

<!-- Quick links: Flashcards + Mural -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-6 md:mb-8">
    <a href="<?= URL ?>/flashcards" class="group flex items-center gap-3 sm:gap-4 p-4 sm:p-5 bg-white rounded-xl sm:rounded-2xl shadow-md border border-gray-100 hover:shadow-lg hover:border-gray-200 transition-all duration-200 text-left">
        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl sm:rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-xl sm:text-2xl group-hover:scale-105 transition-transform flex-shrink-0">🃏</div>
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors text-sm sm:text-base">Flashcards</p>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Histórico e criar baralhos com a <?= htmlspecialchars(LayoutHelper::getIaName()) ?></p>
        </div>
        <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 group-hover:translate-x-1 transition-all flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
    </a>
    <?php if (!empty($mural_recados_count)): ?>
    <a href="<?= URL ?>/mural-recados" class="group flex items-center gap-3 sm:gap-4 p-4 sm:p-5 bg-white rounded-xl sm:rounded-2xl shadow-md border border-gray-100 hover:shadow-lg transition-all duration-200 text-left" style="border-left: 4px solid <?= htmlspecialchars($primaryHex) ?>">
        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl sm:rounded-2xl flex items-center justify-center text-xl sm:text-2xl flex-shrink-0 group-hover:scale-105 transition-transform" style="background-color: <?= htmlspecialchars($primaryHex) ?>20; color: <?= htmlspecialchars($primaryHex) ?>">📌</div>
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm sm:text-base text-gray-900 group-hover:opacity-90 transition-opacity" style="color: <?= htmlspecialchars($primaryHex) ?>">Mural de Recados</p>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5" id="dashboard-mural-total-label"><?= (int)$mural_recados_count === 1 ? '1 recado' : (int)$mural_recados_count . ' recados' ?></p>
        </div>
        <svg class="w-5 h-5 flex-shrink-0 group-hover:translate-x-1 transition-transform" style="color: <?= htmlspecialchars($primaryHex) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
    </a>
    <?php elseif ($dashboardAsync): ?>
    <a href="<?= URL ?>/mural-recados" id="dashboard-mural-quick-link" class="hidden group flex items-center gap-3 sm:gap-4 p-4 sm:p-5 bg-white rounded-xl sm:rounded-2xl shadow-md border border-gray-100 hover:shadow-lg transition-all duration-200 text-left" style="border-left: 4px solid <?= htmlspecialchars($primaryHex) ?>">
        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl sm:rounded-2xl flex items-center justify-center text-xl sm:text-2xl flex-shrink-0 group-hover:scale-105 transition-transform" style="background-color: <?= htmlspecialchars($primaryHex) ?>20; color: <?= htmlspecialchars($primaryHex) ?>">📌</div>
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm sm:text-base text-gray-900 group-hover:opacity-90 transition-opacity" style="color: <?= htmlspecialchars($primaryHex) ?>">Mural de Recados</p>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5" id="dashboard-mural-total-label">—</p>
        </div>
        <svg class="w-5 h-5 flex-shrink-0 group-hover:translate-x-1 transition-transform" style="color: <?= htmlspecialchars($primaryHex) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
    </a>
    <?php endif; ?>
</div>

<?php if (LayoutHelper::isModuleEnabled('aulas_online') && !empty($aulas_online) && is_array($aulas_online)): ?>
<!-- Aulas Online -->
<div class="mb-6 sm:mb-8">
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xl sm:text-2xl" aria-hidden="true">🎥</span>
                <div>
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Aulas online</h2>
                    <p class="text-xs sm:text-sm text-gray-500">Aulas ao vivo agora ou agendadas para você.</p>
                </div>
            </div>
            <a href="<?= URL ?>/aluno/aulas-online" class="text-sm font-medium text-rose-600 hover:underline">Ver todas</a>
        </div>
        <div class="p-5 sm:p-6 space-y-3">
            <?php foreach ($aulas_online as $aula): ?>
                <?php
                    $inicioTs = !empty($aula['inicio_em']) ? strtotime((string) $aula['inicio_em']) : false;
                    $fimTs = !empty($aula['fim_em']) ? strtotime((string) $aula['fim_em']) : false;
                    $nowTs = time();
                    $iniciou = $inicioTs !== false && $nowTs >= $inicioTs;
                    $encerrou = $fimTs !== false && $nowTs > $fimTs;
                    $aoVivo = $iniciou && !$encerrou;
                ?>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border rounded-xl p-4 <?= $aoVivo ? 'bg-rose-50 border-rose-200' : 'bg-gray-50 border-gray-100' ?>">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <?php if ($aoVivo): ?>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full">
                                    <span class="w-2 h-2 bg-rose-600 rounded-full animate-pulse"></span> Ao vivo
                                </span>
                            <?php else: ?>
                                <span class="text-xs font-semibold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">Agendada</span>
                            <?php endif; ?>
                            <?php if (!empty($aula['plataforma'])): ?>
                                <span class="text-xs text-gray-500"><?= htmlspecialchars((string) $aula['plataforma']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="font-medium text-gray-900 truncate mt-1"><?= htmlspecialchars((string) ($aula['titulo'] ?? 'Aula online')) ?></p>
                        <?php if ($inicioTs !== false): ?>
                            <p class="text-xs text-gray-500"><?= $aoVivo ? 'Começou' : 'Início' ?>: <?= date('d/m/Y H:i', $inicioTs) ?></p>
                        <?php endif; ?>
                    </div>
                    <a href="<?= URL ?>/aluno/aulas-online/<?= (int) ($aula['id'] ?? 0) ?>"
                       class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white shadow-sm shrink-0 <?= $aoVivo ? 'bg-rose-600 hover:bg-rose-700' : 'bg-gray-700 hover:bg-gray-800' ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                        <?= $aoVivo ? 'Entrar agora' : 'Ver aula' ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 <?= $jornadas_habilitadas ? 'lg:grid-cols-2' : 'lg:grid-cols-1' ?> gap-8">
    <!-- Jornadas Ativas (apenas se módulo estiver habilitado) -->
    <?php if ($jornadas_habilitadas): ?>
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Jornadas ativas</h2>
                <a href="<?= URL ?>/jornadas" class="text-sm font-medium hover:underline transition-opacity" style="color: <?= htmlspecialchars($primaryHex ?? '#3b82f6') ?>">Ver todas</a>
            </div>
        </div>
        <div class="p-6">
            <?php if (empty($jornadas_ativas)): ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <p class="text-gray-500">Nenhuma jornada ativa no momento</p>
                    <p class="text-sm text-gray-400">Seus professores ainda não criaram jornadas para sua turma</p>
                </div>
            <?php else:
                $layoutJornadas = $jornadas_ativas_layout ?? 'default';
            ?>
                <div class="space-y-3 sm:space-y-4">
                    <?php foreach ($jornadas_ativas as $jornada): ?>
                        <?php
                        $podeIniciar = true;
                        $mensagemBloqueio = '';
                        $statusJornada = 'Aguardando';
                        $corStatus = 'bg-amber-100 text-amber-800';
                        $dataAtual = date('Y-m-d');
                        $agoraTs = time();
                        if (!empty($jornada['jornada_concluida'])) {
                            $statusJornada = 'Concluído';
                            $corStatus = 'bg-blue-100 text-blue-800';
                        } elseif (!empty($jornada['data_fim'])) {
                            $horaFim = trim((string)($jornada['hora_fim'] ?? '')) ?: '23:59:59';
                            $tsFim = strtotime($jornada['data_fim'] . ' ' . $horaFim);
                            if ($agoraTs > $tsFim) {
                                $podeIniciar = false;
                                $statusJornada = 'Expirado';
                                $corStatus = 'bg-red-100 text-red-700';
                                $mensagemBloqueio = 'Expirado';
                            }
                        }
                        if ($podeIniciar && !empty($jornada['data_inicio']) && empty($jornada['jornada_concluida'])) {
                            $dataInicioFormatada = date('Y-m-d', strtotime($jornada['data_inicio']));
                            if ($dataAtual < $dataInicioFormatada) {
                                $podeIniciar = false;
                                $statusJornada = 'Aguardando';
                                $corStatus = 'bg-orange-100 text-orange-800';
                                $mensagemBloqueio = 'Disponível em ' . date('d/m/Y', strtotime($jornada['data_inicio']));
                            }
                        }
                        $tituloJornada = $jornada['titulo'] ?? '';
                        $metaLine = trim(implode(' • ', array_filter([$jornada['materia_nome'] ?? '', !empty($jornada['professor_nome']) ? 'Prof. ' . $jornada['professor_nome'] : ''])));
                        ?>
                        <?php if ($layoutJornadas === 'compact'): ?>
                        <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50/80 transition-colors flex flex-wrap items-center gap-2 sm:gap-3">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 text-sm line-clamp-1 truncate" title="<?= htmlspecialchars($tituloJornada) ?>"><?= htmlspecialchars($tituloJornada) ?></h3>
                                <?php if ($metaLine !== ''): ?><p class="text-xs text-gray-500 mt-0.5 truncate"><?= htmlspecialchars($metaLine) ?></p><?php endif; ?>
                            </div>
                            <span class="px-2 py-0.5 <?= $corStatus ?> text-xs font-medium rounded-full flex-shrink-0"><?= $statusJornada ?></span>
                            <?php if ($podeIniciar): ?>
                                <a href="<?= URL ?>/jornadas/<?= $jornada['id'] ?>" class="inline-flex items-center px-3 py-2 bg-blue-500 text-white text-sm font-medium rounded-lg hover:bg-blue-600 transition-colors flex-shrink-0">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <?= !empty($jornada['jornada_concluida']) ? 'Ver' : 'Iniciar' ?>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-2 <?= ($statusJornada === 'Expirado') ? 'bg-red-100 text-red-700' : 'bg-gray-200 text-gray-600' ?> text-sm font-medium rounded-lg cursor-not-allowed flex-shrink-0" title="<?= htmlspecialchars($mensagemBloqueio) ?>"><?= htmlspecialchars($mensagemBloqueio) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($layoutJornadas === 'list'): ?>
                        <div class="border border-gray-200 rounded-xl p-4 hover:bg-gray-50/80 transition-colors">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-900 text-sm sm:text-base line-clamp-2 break-words" title="<?= htmlspecialchars($tituloJornada) ?>"><?= htmlspecialchars($tituloJornada) ?></h3>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1 text-sm text-gray-600">
                                        <?php if (!empty($jornada['materia_nome'])): ?><span><?= htmlspecialchars($jornada['materia_nome']) ?></span><?php endif; ?>
                                        <?php if (!empty($jornada['professor_nome'])): ?><span>Prof. <?= htmlspecialchars($jornada['professor_nome']) ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="px-2.5 py-1 <?= $corStatus ?> text-xs font-medium rounded-full"><?= $statusJornada ?></span>
                                    <?php if ($podeIniciar): ?>
                                        <a href="<?= URL ?>/jornadas/<?= $jornada['id'] ?>" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white text-sm font-medium rounded-lg hover:bg-blue-600 transition-colors">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <?= !empty($jornada['jornada_concluida']) ? 'Ver Jornada' : 'Iniciar Jornada' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-4 py-2 <?= ($statusJornada === 'Expirado') ? 'bg-red-100 text-red-700' : 'bg-gray-200 text-gray-600' ?> text-sm font-medium rounded-lg cursor-not-allowed" title="<?= htmlspecialchars($mensagemBloqueio) ?>"><?= htmlspecialchars($mensagemBloqueio) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="border border-gray-200 rounded-xl p-4 sm:p-5 hover:bg-gray-50/80 transition-colors">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
                                        <h3 class="font-semibold text-gray-900 text-sm sm:text-base line-clamp-2 break-words flex-1 min-w-0" title="<?= htmlspecialchars($tituloJornada) ?>"><?= htmlspecialchars($tituloJornada) ?></h3>
                                        <span class="px-2.5 py-1 <?= $corStatus ?> text-xs font-medium rounded-full flex-shrink-0"><?= $statusJornada ?></span>
                                    </div>
                                    <div class="flex flex-col gap-1 text-sm text-gray-600">
                                        <?php if (!empty($jornada['materia_nome'])): ?>
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13"></path></svg>
                                                <?= htmlspecialchars($jornada['materia_nome']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($jornada['professor_nome'])): ?>
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                Prof. <?= htmlspecialchars($jornada['professor_nome']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($jornada['data_inicio']) || !empty($jornada['data_fim'])): ?>
                                        <div class="mt-2 pt-2 border-t border-gray-100 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500">
                                            <?php if (!empty($jornada['data_inicio'])): ?>
                                                <span>Início: <?= date('d/m/Y', strtotime($jornada['data_inicio'])) ?><?= !empty($jornada['hora_inicio']) ? ' às ' . date('H:i', strtotime($jornada['hora_inicio'])) : '' ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($jornada['data_fim'])): ?>
                                                <span>Término: <?= date('d/m/Y', strtotime($jornada['data_fim'])) ?><?= !empty($jornada['hora_fim']) ? ' às ' . date('H:i', strtotime($jornada['hora_fim'])) : '' ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-shrink-0 sm:ml-4">
                                    <?php if ($podeIniciar): ?>
                                        <a href="<?= URL ?>/jornadas/<?= $jornada['id'] ?>" 
                                           class="inline-flex items-center justify-center px-4 py-2.5 bg-blue-500 text-white text-sm font-medium rounded-lg hover:bg-blue-600 transition-colors w-full sm:w-auto">
                                            <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <?= !empty($jornada['jornada_concluida']) ? 'Ver Jornada' : 'Iniciar Jornada' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex items-center justify-center px-4 py-2.5 <?= ($statusJornada === 'Expirado') ? 'bg-red-100 text-red-700' : 'bg-gray-200 text-gray-600' ?> text-sm font-medium rounded-lg cursor-not-allowed w-full sm:w-auto" title="<?= htmlspecialchars($mensagemBloqueio) ?>">
                                            <?= htmlspecialchars($mensagemBloqueio) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Agenda do mês -->
    <?php include __DIR__ . '/partials/dashboard-calendario-agenda.php'; ?>
</div>

<!-- Modal: Aviso de recados no Mural (só informa quantidade; ao clicar em Visualizar vai para a tela do Mural) -->
<?php if (!empty($mural_recados_nao_vistos)): $qtd_recados = count($mural_recados_nao_vistos); $modalPrimary = $primary_color ?? '#3b82f6'; ?>
<div id="modalMuralRecado" class="fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4" style="display: flex;">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
        <div class="px-6 py-4 text-white flex items-center justify-between" style="background-color: <?= htmlspecialchars($modalPrimary) ?>">
            <h2 class="text-xl font-bold flex items-center"><span class="mr-2">📌</span> Mural de Recados</h2>
            <button type="button" onclick="fecharModalMuralRecado()" class="text-white hover:bg-white/20 p-2 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-6">
            <p class="text-gray-700 mb-6"><?php
                if ($qtd_recados === 1) {
                    echo 'Existe <strong>1 recado novo</strong> para você no mural.';
                } else {
                    echo 'Existem <strong>' . (int)$qtd_recados . ' recados novos</strong> para você no mural.';
                }
            ?></p>
        </div>
        <div class="p-4 border-t bg-gray-50 flex justify-end gap-2">
            <button type="button" onclick="fecharModalMuralRecado()" class="px-4 py-2 text-gray-700 hover:bg-gray-200 rounded-lg">Fechar</button>
            <a href="<?= URL ?>/mural-recados" class="px-4 py-2 text-white rounded-lg hover:opacity-90 transition-opacity inline-block" style="background-color: <?= htmlspecialchars($modalPrimary) ?>">Visualizar</a>
        </div>
    </div>
</div>
<script>
(function() {
    var modal = document.getElementById('modalMuralRecado');
    window.fecharModalMuralRecado = function() { if (modal) modal.style.display = 'none'; };
})();
</script>
<?php endif; ?>

<!-- Modal de Onboarding -->
<?php if (!$onboarding_completado): ?>
<div id="onboardingModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4" style="display: flex; backdrop-filter: blur(2px);">
    <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[95vh] overflow-hidden flex flex-col mx-auto">
        <!-- Header do Modal -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-5 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold flex items-center">
                        <span class="mr-2">🎯</span>
                        Meu Perfil de Onboarding
                    </h2>
                    <p class="text-sm text-purple-100 mt-1">Complete seu perfil para personalizar sua experiência de aprendizado</p>
                </div>
                <button onclick="fecharModalOnboarding()" 
                        class="text-white hover:bg-white/20 p-2 rounded-lg transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Conteúdo do Modal (Scrollável) -->
        <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
        
        <form id="onboardingForm">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Meu Sonho -->
                <div class="bg-white rounded-xl p-5 border-2 border-yellow-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">☀️</span>
                        <span class="font-bold text-gray-800 text-lg">Meu Sonho</span>
                    </label>
                    <input type="text" 
                           name="meu_sonho" 
                           placeholder="Ex: sonhar"
                           class="w-full px-4 py-3 border-2 border-yellow-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 bg-white transition-all"
                           value="<?= htmlspecialchars($onboarding['meu_sonho'] ?? '') ?>">
                </div>
                
                <!-- Objetivo Principal -->
                <div class="bg-white rounded-xl p-5 border-2 border-pink-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">🎯</span>
                        <span class="font-bold text-gray-800 text-lg">Objetivo Principal</span>
                    </label>
                    <select name="objetivo_principal" 
                            class="w-full px-4 py-3 border-2 border-pink-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-pink-400 bg-white transition-all">
                        <option value="">Selecione...</option>
                        <option value="Concurso Público" <?= ($onboarding['objetivo_principal'] ?? '') === 'Concurso Público' ? 'selected' : '' ?>>Concurso Público</option>
                        <option value="Vestibular" <?= ($onboarding['objetivo_principal'] ?? '') === 'Vestibular' ? 'selected' : '' ?>>Vestibular</option>
                        <option value="ENEM" <?= ($onboarding['objetivo_principal'] ?? '') === 'ENEM' ? 'selected' : '' ?>>ENEM</option>
                        <option value="Melhorar Notas" <?= ($onboarding['objetivo_principal'] ?? '') === 'Melhorar Notas' ? 'selected' : '' ?>>Melhorar Notas</option>
                        <option value="Aprender Mais" <?= ($onboarding['objetivo_principal'] ?? '') === 'Aprender Mais' ? 'selected' : '' ?>>Aprender Mais</option>
                        <option value="Outro" <?= ($onboarding['objetivo_principal'] ?? '') === 'Outro' ? 'selected' : '' ?>>Outro</option>
                    </select>
                </div>
                
                <!-- Nível de Comprometimento -->
                <div class="bg-white rounded-xl p-5 border-2 border-orange-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">💪</span>
                        <span class="font-bold text-gray-800 text-lg">Nível de Comprometimento</span>
                    </label>
                    <select name="nivel_comprometimento" 
                            class="w-full px-4 py-3 border-2 border-orange-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 bg-white transition-all">
                        <option value="">Selecione...</option>
                        <option value="Dedicado" <?= ($onboarding['nivel_comprometimento'] ?? '') === 'Dedicado' ? 'selected' : '' ?>>Dedicado</option>
                        <option value="Moderado" <?= ($onboarding['nivel_comprometimento'] ?? '') === 'Moderado' ? 'selected' : '' ?>>Moderado</option>
                        <option value="Iniciante" <?= ($onboarding['nivel_comprometimento'] ?? '') === 'Iniciante' ? 'selected' : '' ?>>Iniciante</option>
                    </select>
                </div>
                
                <!-- Pontos de Dificuldade -->
                <div class="bg-white rounded-xl p-5 border-2 border-red-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">⚠️</span>
                        <span class="font-bold text-gray-800 text-lg">Pontos de Dificuldade</span>
                    </label>
                    <input type="text" 
                           name="pontos_dificuldade" 
                           placeholder="Ex: Redação, Matemática..."
                           class="w-full px-4 py-3 border-2 border-red-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-red-400 bg-white transition-all"
                           value="<?= htmlspecialchars($onboarding['pontos_dificuldade'] ?? '') ?>">
                </div>
                
                <!-- Tempo de Estudo por Dia -->
                <div class="bg-white rounded-xl p-5 border-2 border-green-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">⏰</span>
                        <span class="font-bold text-gray-800 text-lg">Tempo de Estudo por Dia</span>
                    </label>
                    <select name="tempo_estudo_dia" 
                            class="w-full px-4 py-3 border-2 border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-green-400 bg-white transition-all">
                        <option value="">Selecione...</option>
                        <option value="1 h" <?= ($onboarding['tempo_estudo_dia'] ?? '') === '1 h' ? 'selected' : '' ?>>1 hora</option>
                        <option value="2 h" <?= ($onboarding['tempo_estudo_dia'] ?? '') === '2 h' ? 'selected' : '' ?>>2 horas</option>
                        <option value="3 h" <?= ($onboarding['tempo_estudo_dia'] ?? '') === '3 h' ? 'selected' : '' ?>>3 horas</option>
                        <option value="4 h" <?= ($onboarding['tempo_estudo_dia'] ?? '') === '4 h' ? 'selected' : '' ?>>4 horas</option>
                        <option value="5+ h" <?= ($onboarding['tempo_estudo_dia'] ?? '') === '5+ h' ? 'selected' : '' ?>>5+ horas</option>
                    </select>
                </div>
                
                <!-- Pontos Fortes -->
                <div class="bg-white rounded-xl p-5 border-2 border-green-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">✅</span>
                        <span class="font-bold text-gray-800 text-lg">Pontos Fortes</span>
                    </label>
                    <input type="text" 
                           name="pontos_fortes" 
                           placeholder="Ex: Humanas, Exatas..."
                           class="w-full px-4 py-3 border-2 border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-green-400 bg-white transition-all"
                           value="<?= htmlspecialchars($onboarding['pontos_fortes'] ?? '') ?>">
                </div>
                
                <!-- Estilo de Aprendizado -->
                <div class="bg-white rounded-xl p-5 border-2 border-blue-300 shadow-md hover:shadow-lg transition-shadow md:col-span-2">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">📚</span>
                        <span class="font-bold text-gray-800 text-lg">Estilo de Aprendizado</span>
                    </label>
                    <select name="estilo_aprendizado" 
                            class="w-full px-4 py-3 border-2 border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 bg-white transition-all">
                        <option value="">Selecione...</option>
                        <option value="Lendo (Textos/Resumos)" <?= ($onboarding['estilo_aprendizado'] ?? '') === 'Lendo (Textos/Resumos)' ? 'selected' : '' ?>>Lendo (Textos/Resumos)</option>
                        <option value="Assistindo (Vídeos/Aulas)" <?= ($onboarding['estilo_aprendizado'] ?? '') === 'Assistindo (Vídeos/Aulas)' ? 'selected' : '' ?>>Assistindo (Vídeos/Aulas)</option>
                        <option value="Praticando (Exercícios)" <?= ($onboarding['estilo_aprendizado'] ?? '') === 'Praticando (Exercícios)' ? 'selected' : '' ?>>Praticando (Exercícios)</option>
                        <option value="Ouvindo (Áudios/Podcasts)" <?= ($onboarding['estilo_aprendizado'] ?? '') === 'Ouvindo (Áudios/Podcasts)' ? 'selected' : '' ?>>Ouvindo (Áudios/Podcasts)</option>
                        <option value="Misto" <?= ($onboarding['estilo_aprendizado'] ?? '') === 'Misto' ? 'selected' : '' ?>>Misto</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Footer do Modal -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
            <button type="button" 
                    onclick="fecharModalOnboarding()"
                    class="px-6 py-2.5 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors font-semibold">
                Cancelar
            </button>
            <button type="submit" 
                    form="onboardingForm"
                    class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all font-semibold shadow-md hover:shadow-lg flex items-center">
                <span class="mr-2">💾</span>
                Salvar Perfil
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const slider = document.getElementById('dashboard-slider');
    if (!slider) return;

    const slides = Array.from(slider.querySelectorAll('.dashboard-slide'));
    const dots = Array.from(slider.querySelectorAll('.slider-dot'));
    const prev = document.getElementById('slider-prev');
    const next = document.getElementById('slider-next');
    if (slides.length <= 1) return;

    let idx = 0;
    let timer = null;

    function syncAspect(slideEl) {
        const img = slideEl && slideEl.querySelector('img.dashboard-slide-img');
        if (img && img.naturalWidth > 0 && img.naturalHeight > 0) {
            slider.style.aspectRatio = img.naturalWidth + ' / ' + img.naturalHeight;
        }
    }

    function show(i) {
        idx = (i + slides.length) % slides.length;
        slides.forEach((el, n) => {
            el.classList.toggle('block', n === idx);
            el.classList.toggle('opacity-100', n === idx);
            el.classList.toggle('hidden', n !== idx);
            el.classList.toggle('opacity-0', n !== idx);
            el.classList.toggle('z-10', n === idx);
            el.classList.toggle('z-0', n !== idx);
            if (n === idx) {
                const lazyImg = el.querySelector('img[data-src]');
                if (lazyImg && lazyImg.dataset.src) {
                    const cur = lazyImg.getAttribute('src') || '';
                    if (cur.indexOf('data:image') === 0) {
                        lazyImg.src = lazyImg.dataset.src;
                    }
                }
                syncAspect(el);
            }
        });
        dots.forEach((dot, n) => {
            dot.classList.toggle('bg-white', n === idx);
            dot.classList.toggle('bg-white/55', n !== idx);
        });
        const sk = slider.querySelector('.dashboard-slider-skeleton');
        if (sk && slider.querySelector('.dashboard-slide-img.opacity-100')) {
            sk.style.display = 'none';
        }
    }

    function start() {
        stop();
        timer = setInterval(() => show(idx + 1), 5000);
    }
    function stop() {
        if (timer) clearInterval(timer);
        timer = null;
    }

    prev?.addEventListener('click', () => { show(idx - 1); start(); });
    next?.addEventListener('click', () => { show(idx + 1); start(); });
    dots.forEach((dot, n) => dot.addEventListener('click', () => { show(n); start(); }));
    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', start);
    show(0);
    start();
});

<?php if (!$onboarding_completado): ?>
function fecharModalOnboarding() {
    const modal = document.getElementById('onboardingModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Fechar modal ao clicar fora dele
document.getElementById('onboardingModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalOnboarding();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalOnboarding();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('onboardingForm');
    const modal = document.getElementById('onboardingModal');
    const submitBtn = form?.querySelector('button[type="submit"]');
    
    if (!form || !modal) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="mr-2">⏳</span> Salvando...';
        }
        
        const formData = new FormData(form);
        
        fetch('<?= URL ?>/onboarding/salvar', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar mensagem de sucesso
                const successMsg = document.createElement('div');
                successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                successMsg.innerHTML = '✅ Perfil salvo com sucesso!';
                document.body.appendChild(successMsg);
                
                setTimeout(() => {
                    modal.style.display = 'none';
                    location.reload();
                }, 1000);
            } else {
                alert('Erro: ' + (data.error || 'Erro ao salvar perfil'));
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span class="mr-2">💾</span> Salvar Perfil';
                }
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao salvar perfil. Tente novamente.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span class="mr-2">💾</span> Salvar Perfil';
            }
        });
    });
});
<?php endif; ?>
</script>
<?php if (!empty($dashboard_async)): ?>
<script src="<?= URL ?>/public/static/js/dashboard-index-loader.js?v=20260803a"></script>
<?php endif; ?>
