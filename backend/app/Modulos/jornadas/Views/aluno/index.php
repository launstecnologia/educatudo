<!-- Header Section -->
<div class="mb-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Minhas Jornadas 📚</h1>
        <p class="text-gray-600 mt-2">Acompanhe suas trilhas de aprendizado e progresso</p>
    </div>

    <?php
    $dash = isset($jornadas_dashboard) && is_array($jornadas_dashboard) ? $jornadas_dashboard : [];
    $asyncLoad = !empty($carregamento_blocos);
    $ph = $asyncLoad ? '—' : null;
    $dTotal = $ph ?? (int) ($dash['total'] ?? 0);
    $dConcl = $ph ?? (int) ($dash['concluidas'] ?? 0);
    $dJEm = $ph ?? (int) ($dash['jornadas_em_andamento'] ?? 0);
    $dJExp = $ph ?? (int) ($dash['jornadas_expiradas'] ?? 0);
    $dQTotal = $ph ?? (int) ($dash['questoes_total'] ?? 0);
    $dQAcertos = $ph ?? (int) ($dash['questoes_acertos'] ?? 0);
    $dQErros = $ph ?? (int) ($dash['questoes_erros'] ?? 0);

    $renderDashValor = static function (string $id, $valor, string $colorClasses) use ($asyncLoad): void {
        if ($asyncLoad) {
            echo '<div class="relative mt-1 min-h-[2.25rem]">';
            echo '<span class="jornadas-dash-skel absolute inset-y-0 left-0 w-14 max-w-[45%] rounded-md bg-slate-200/90 animate-pulse" aria-hidden="true"></span>';
            echo '<p id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" class="text-3xl font-bold tabular-nums opacity-0 ' . $colorClasses . '" aria-hidden="true">0</p>';
            echo '</div>';
            return;
        }
        echo '<p id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" class="mt-1 text-3xl font-bold tabular-nums ' . $colorClasses . '">' . htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') . '</p>';
    };
    ?>
    <div id="jornadasDashPanel" class="flex flex-col gap-4"<?= $asyncLoad ? ' aria-busy="true"' : '' ?>>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-500">Total de jornadas</p>
                    <?php $renderDashValor('dashTotalJornadas', $dTotal, 'text-slate-900'); ?>
                    <p class="mt-1 text-xs text-slate-400">Na sua turma (visíveis para você)</p>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
            <div class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-200/70 text-emerald-800" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-emerald-800">Concluídas</p>
                    <?php $renderDashValor('dashConcluidas', $dConcl, 'text-emerald-700'); ?>
                    <p class="mt-1 text-xs text-emerald-700/80">Jornadas já finalizadas</p>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-5 shadow-sm">
            <div class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-sky-200/70 text-sky-800" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-sky-900">Em andamento</p>
                    <?php $renderDashValor('dashEmAndamento', $dJEm, 'text-sky-800'); ?>
                    <p class="mt-1 text-xs text-sky-800/85">Dentro do prazo, ainda sem conclusão</p>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50/50 p-5 shadow-sm">
            <div class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-red-200/60 text-red-800" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-red-900">Expiradas</p>
                    <?php $renderDashValor('dashExpiradas', $dJExp, 'text-red-800'); ?>
                    <p class="mt-1 text-xs text-red-800/85">Prazo encerrado, ainda sem conclusão</p>
                </div>
            </div>
        </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-500">Total de exercícios (questões)</p>
                    <?php $renderDashValor('dashQuestoesTotal', $dQTotal, 'text-slate-900'); ?>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
            <div class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-200/70 text-emerald-800" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-emerald-800">Acertos (total)</p>
                    <?php $renderDashValor('dashQuestoesAcertos', $dQAcertos, 'text-emerald-700'); ?>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-5 shadow-sm">
            <div class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-200/70 text-rose-800" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-rose-900">Erros (total)</p>
                    <?php $renderDashValor('dashQuestoesErros', $dQErros, 'text-rose-700'); ?>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 md:items-end md:gap-4">
                <div class="flex min-w-0 flex-col gap-1.5">
                    <label for="searchInput" class="text-xs font-semibold text-gray-600">Buscar jornada</label>
                    <div class="relative">
                        <input type="text" id="searchInput" name="q" placeholder="Título ou palavra-chave…" autocomplete="off" inputmode="search"
                               class="w-full min-h-[2.75rem] rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-base text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 sm:min-h-[3rem] sm:py-3 sm:pl-11">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400 sm:left-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <label for="materiaFilter" class="text-xs font-semibold text-gray-600">Matéria</label>
                    <div class="relative">
                        <select id="materiaFilter" class="block w-full min-h-[2.75rem] cursor-pointer appearance-none rounded-lg border border-gray-300 bg-white py-2.5 pl-3 pr-10 text-base font-medium text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 sm:text-sm">
                            <option value="">Todas as matérias</option>
                            <?php
                            $materias = array_unique(array_filter(array_column($jornadas, 'materia_nome')));
                            sort($materias);
                            foreach ($materias as $materia):
                            ?>
                                <option value="<?= htmlspecialchars($materia) ?>"><?= htmlspecialchars($materia) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-500" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </span>
                    </div>
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <label for="professorFilter" class="text-xs font-semibold text-gray-600">Professor</label>
                    <div class="relative">
                        <select id="professorFilter" class="block w-full min-h-[2.75rem] cursor-pointer appearance-none rounded-lg border border-gray-300 bg-white py-2.5 pl-3 pr-10 text-base font-medium text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 sm:text-sm">
                            <option value="">Todos os professores</option>
                            <?php
                            $professores = array_unique(array_filter(array_column($jornadas, 'professor_nome')));
                            sort($professores);
                            foreach ($professores as $prof):
                            ?>
                                <option value="<?= htmlspecialchars($prof) ?>"><?= htmlspecialchars($prof) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-500" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 md:items-end md:gap-4">
                <div class="flex min-w-0 flex-col gap-1.5">
                    <label for="statusFilter" class="text-xs font-semibold text-gray-600">Status</label>
                    <div class="relative">
                        <select id="statusFilter" class="block w-full min-h-[2.75rem] cursor-pointer appearance-none rounded-lg border border-gray-300 bg-white py-2.5 pl-3 pr-10 text-base font-medium text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 sm:text-sm">
                            <option value="" selected>Todos os status</option>
                            <option value="aguardando">Aguardando</option>
                            <option value="em_andamento">Em andamento</option>
                            <option value="concluido">Concluído</option>
                            <option value="expirado">Expirado</option>
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-500" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </span>
                    </div>
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <label for="dataDeFilter" class="text-xs font-semibold text-gray-600">Data inicial</label>
                    <input type="date" id="dataDeFilter" lang="pt-BR"
                           class="w-full min-h-[2.75rem] rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 [color-scheme:light] sm:text-sm">
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <label for="dataAteFilter" class="text-xs font-semibold text-gray-600">Data final</label>
                    <input type="date" id="dataAteFilter" lang="pt-BR"
                           class="w-full min-h-[2.75rem] rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 [color-scheme:light] sm:text-sm">
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-1 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <span class="text-sm text-gray-500">Mostrando</span>
            <span id="jornadaCount" class="inline-flex min-h-[1.25rem] items-center text-sm font-semibold text-blue-600 tabular-nums">
                <?php if ($asyncLoad): ?>
                    <span class="jornadas-count-skel inline-block h-4 w-28 rounded-md bg-blue-100 animate-pulse" aria-hidden="true"></span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- Jornadas Grid -->
<div class="grid grid-cols-1 items-stretch gap-6 md:grid-cols-2 lg:grid-cols-3" id="jornadasGrid">
    <?php if ($asyncLoad): ?>
        <?php include __DIR__ . '/_cards_skeleton.php'; ?>
    <?php elseif (empty($jornadas)): ?>
        <?php $jornadas = []; include __DIR__ . '/_cards.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/_cards.php'; ?>
    <?php endif; ?>
</div>

<?php if ($asyncLoad): ?>
<script>
window.jornadasIndexConfig = {
    asyncLoad: true,
    apiMontar: <?= json_encode(URL . '/jornadas/api/montar', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="<?= URL ?>/public/static/js/jornadas-index-loader.js?v=20260805"></script>
<script>
if (typeof window.jornadasIndexBoot === 'function') {
    window.jornadasIndexBoot();
}
</script>
<?php else: ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dashBaseline = <?= json_encode([
        'total' => (int) $dTotal,
        'concluidas' => (int) $dConcl,
        'emAndamento' => (int) $dJEm,
        'expiradas' => (int) $dJExp,
        'questoesTotal' => (int) $dQTotal,
        'questoesAcertos' => (int) $dQAcertos,
        'questoesErros' => (int) $dQErros,
    ], JSON_UNESCAPED_UNICODE) ?>;

    const searchInput = document.getElementById('searchInput');
    const materiaFilter = document.getElementById('materiaFilter');
    const statusFilter = document.getElementById('statusFilter');
    const professorFilter = document.getElementById('professorFilter');
    const dataDeFilter = document.getElementById('dataDeFilter');
    const dataAteFilter = document.getElementById('dataAteFilter');
    const jornadaCount = document.getElementById('jornadaCount');
    const jornadaCards = document.querySelectorAll('.jornada-card');
    const urlParams = new URLSearchParams(window.location.search);

    function setDash(id, n) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = String(n);
        }
    }

    function isFiltroJornadasAtivo() {
        const t = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
        if (t.length > 0) {
            return true;
        }
        if (materiaFilter && materiaFilter.value) {
            return true;
        }
        if (professorFilter && professorFilter.value) {
            return true;
        }
        if (dataDeFilter && dataDeFilter.value) {
            return true;
        }
        if (dataAteFilter && dataAteFilter.value) {
            return true;
        }
        const st = (statusFilter && statusFilter.value) ? String(statusFilter.value).trim() : '';
        return st !== '';
    }

    function restoreDashboardBaseline() {
        if (!dashBaseline || typeof dashBaseline !== 'object') {
            return;
        }
        setDash('dashTotalJornadas', dashBaseline.total);
        setDash('dashConcluidas', dashBaseline.concluidas);
        setDash('dashEmAndamento', dashBaseline.emAndamento);
        setDash('dashExpiradas', dashBaseline.expiradas);
        setDash('dashQuestoesTotal', dashBaseline.questoesTotal);
        setDash('dashQuestoesAcertos', dashBaseline.questoesAcertos);
        setDash('dashQuestoesErros', dashBaseline.questoesErros);
    }

    function updateDashboardFromVisibleJornadas() {
        let total = 0;
        let concl = 0;
        let em = 0;
        let exp = 0;
        let qTot = 0;
        let qAc = 0;
        let qEr = 0;
        jornadaCards.forEach(function (card) {
            if (card.style.display === 'none') {
                return;
            }
            total++;
            const st = card.dataset.status || '';
            if (st === 'concluido') {
                concl++;
            } else if (st === 'em_andamento') {
                em++;
            } else if (st === 'expirado') {
                exp++;
            }
            qTot += parseInt(card.getAttribute('data-questoes-total') || '0', 10) || 0;
            qAc += parseInt(card.getAttribute('data-questoes-acertos') || '0', 10) || 0;
            qEr += parseInt(card.getAttribute('data-questoes-erros') || '0', 10) || 0;
        });
        setDash('dashTotalJornadas', total);
        setDash('dashConcluidas', concl);
        setDash('dashEmAndamento', em);
        setDash('dashExpiradas', exp);
        setDash('dashQuestoesTotal', qTot);
        setDash('dashQuestoesAcertos', qAc);
        setDash('dashQuestoesErros', qEr);
    }
    
    function filterJornadas() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedMateria = materiaFilter.value;
        const selectedStatus = statusFilter.value;
        const selectedProfessor = professorFilter.value;
        const dataDe = dataDeFilter.value;
        const dataAte = dataAteFilter.value;
        let visibleCount = 0;
        
        jornadaCards.forEach(card => {
            const titulo = (card.dataset.titulo || '').toLowerCase();
            const materia = card.dataset.materia || '';
            const status = card.dataset.status || '';
            const professor = card.dataset.professor || '';
            const dataInicio = card.dataset.dataInicio || '';
            const dataFim = card.dataset.dataFim || '';
            
            const matchesSearch = !searchTerm || titulo.includes(searchTerm);
            const matchesMateria = !selectedMateria || materia === selectedMateria;
            const matchesStatus = !selectedStatus || status === selectedStatus;
            const matchesProfessor = !selectedProfessor || professor === selectedProfessor;
            
            let matchesDataDe = true;
            if (dataDe && dataFim) matchesDataDe = dataFim >= dataDe;
            else if (dataDe && dataInicio) matchesDataDe = dataInicio >= dataDe;
            let matchesDataAte = true;
            if (dataAte && dataInicio) matchesDataAte = dataInicio <= dataAte;
            else if (dataAte && dataFim) matchesDataAte = dataFim <= dataAte;
            
            if (matchesSearch && matchesMateria && matchesStatus && matchesProfessor && matchesDataDe && matchesDataAte) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        jornadaCount.textContent = visibleCount + ' jornada' + (visibleCount !== 1 ? 's' : '');
        if (isFiltroJornadasAtivo()) {
            updateDashboardFromVisibleJornadas();
        } else {
            restoreDashboardBaseline();
        }
    }
    
    searchInput.addEventListener('input', filterJornadas);
    materiaFilter.addEventListener('change', filterJornadas);
    statusFilter.addEventListener('change', filterJornadas);
    professorFilter.addEventListener('change', filterJornadas);
    dataDeFilter.addEventListener('change', filterJornadas);
    dataAteFilter.addEventListener('change', filterJornadas);

    // Permite abrir a tela com filtros via query string (ex.: ?status=em_andamento)
    const statusQuery = (urlParams.get('status') || '').trim().toLowerCase();
    if (statusQuery && ['aguardando', 'em_andamento', 'concluido', 'expirado'].includes(statusQuery)) {
        statusFilter.value = statusQuery;
    }
    const materiaQuery = (urlParams.get('materia') || '').trim();
    if (materiaQuery) {
        materiaFilter.value = materiaQuery;
    }
    const professorQuery = (urlParams.get('professor') || '').trim();
    if (professorQuery) {
        professorFilter.value = professorQuery;
    }
    const dataDeQuery = (urlParams.get('data_de') || '').trim();
    if (dataDeQuery) {
        dataDeFilter.value = dataDeQuery;
    }
    const dataAteQuery = (urlParams.get('data_ate') || '').trim();
    if (dataAteQuery) {
        dataAteFilter.value = dataAteQuery;
    }

    // Aplica filtro na carga
    filterJornadas();
});
</script>
<?php endif; ?>
