<?php
$primaryColor = LayoutHelper::get('primary_color', '#6366f1');
?>
<style>
    .filters-panel {
        background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid #e2e8f0;
        box-shadow: 0 18px 45px -28px rgba(15, 23, 42, 0.3);
    }
    .proposal-card {
        transition: all 0.2s ease;
        border-left: 4px solid <?= htmlspecialchars($primaryColor) ?>;
    }
    .proposal-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .btn-ver-proposta {
        background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
    }
    .btn-ver-proposta:hover {
        filter: brightness(1.1);
    }
    .badge-disponivel {
        background-color: #e0e7ff;
        color: #4338ca;
    }
    .badge-corrigida {
        background-color: #dcfce7;
        color: #166534;
        box-shadow: inset 0 0 0 1px #86efac;
    }
    .badge-enviada {
        background-color: #dbeafe;
        color: #1d4ed8;
        box-shadow: inset 0 0 0 1px #93c5fd;
    }
    .score-pill {
        background: linear-gradient(135deg, #166534 0%, #22c55e 100%);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.25);
        box-shadow: 0 14px 30px -20px rgba(22, 101, 52, 0.85);
    }
    .proposal-card--corrected {
        border-left-width: 6px;
        box-shadow: 0 20px 40px -28px rgba(22, 101, 52, 0.45);
        background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%);
    }
    .proposal-card--submitted {
        border-left-color: #3b82f6;
    }
    .essay-filter-input {
        border: 1px solid #dbe2ea;
        border-radius: 0.9rem;
        padding: 0.9rem 1rem;
        width: 100%;
        color: #0f172a;
        background: #fff;
    }
    .essay-filter-input:focus {
        outline: none;
        border-color: <?= htmlspecialchars($primaryColor) ?>;
        box-shadow: 0 0 0 4px <?= htmlspecialchars($primaryColor) ?>20;
    }
    .filter-empty-state {
        display: none;
    }
</style>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-1">Redações Disponíveis</h2>
    <p class="text-gray-500 text-sm">Escolha uma proposta e escreva sua redação</p>
</div>

<?php if (empty($proposals)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
    <div class="text-5xl mb-4">📝</div>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">Nenhuma proposta disponível</h3>
    <p class="text-gray-500 text-sm">Quando seu professor publicar uma proposta, ela aparecerá aqui.</p>
</div>
<?php else: ?>
<div class="filters-panel rounded-2xl p-5 mb-6">
    <div class="flex flex-col md:flex-row md:items-end gap-4">
        <div class="flex-1">
            <label for="essay-filter-theme" class="block text-sm font-semibold text-gray-700 mb-2">Tema</label>
            <input id="essay-filter-theme" type="text" class="essay-filter-input" placeholder="Buscar por tema da redação">
        </div>
        <div class="w-full md:w-64">
            <label for="essay-filter-board" class="block text-sm font-semibold text-gray-700 mb-2">Tipo</label>
            <select id="essay-filter-board" class="essay-filter-input">
                <option value="">Todos os tipos</option>
                <?php
                $boardOptions = [];
                foreach ($proposals as $proposalOption) {
                    $boardOptions[(string) ($proposalOption['board_name'] ?? '')] = true;
                }
                ksort($boardOptions);
                foreach (array_keys($boardOptions) as $boardName):
                    if ($boardName === '') continue;
                ?>
                <option value="<?= htmlspecialchars($boardName) ?>"><?= htmlspecialchars($boardName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-full md:w-64">
            <label for="essay-filter-status" class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
            <select id="essay-filter-status" class="essay-filter-input">
                <option value="">Todos os status</option>
                <option value="corrigida">Corrigida</option>
                <option value="enviada">Enviada</option>
                <option value="disponivel">Disponível</option>
                <option value="encerrada">Encerrada</option>
            </select>
        </div>
    </div>
</div>

<div id="essay-cards-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($proposals as $p): 
        $startsAt = !empty($p['starts_at']) ? $p['starts_at'] : null;
        $endsAt = !empty($p['ends_at']) ? $p['ends_at'] : null;
        $now = date('Y-m-d H:i:s');
        $isAvailable = true;
        $statusText = '';
        $submissionStatus = (string) ($p['submission_status'] ?? '');
        $submissionId = isset($p['submission_id']) ? (int) $p['submission_id'] : 0;
        $correctionScore = isset($p['correction_total_score']) && $p['correction_total_score'] !== null
            ? (int) round((float) $p['correction_total_score'])
            : null;
        $maxTotalScore = isset($p['max_total_score']) ? (float) $p['max_total_score'] : 1000.0;
        $isCorrected = $submissionId > 0 && ($submissionStatus === 'corrected' || $correctionScore !== null);
        $isSubmitted = !$isCorrected && $submissionId > 0 && $submissionStatus === 'submitted';
        $buttonHref = URL . '/jornada-redacao/' . (int) $p['id'];
        $buttonText = 'Ver proposta';
        $cardStatus = 'disponivel';
        
        if ($startsAt && $now < $startsAt) {
            $isAvailable = false;
            $statusText = 'Disponível em ' . date('d/m às H:i', strtotime($startsAt));
        } elseif ($endsAt && $now > $endsAt) {
            $isAvailable = false;
            $statusText = 'Encerrada';
            $cardStatus = 'encerrada';
        } elseif ($endsAt) {
            $statusText = 'Até ' . date('d/m às H:i', strtotime($endsAt));
        }

        if ($isCorrected) {
            $statusText = 'Corrigida';
            $buttonHref = URL . '/jornada-redacao/correcao/' . $submissionId;
            $buttonText = 'Ver correção';
            $cardStatus = 'corrigida';
        } elseif ($isSubmitted) {
            $statusText = 'Enviada';
            $cardStatus = 'enviada';
        }
    ?>
    <div
        class="proposal-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden <?= (!$isAvailable && !$isCorrected) ? 'opacity-60' : '' ?> <?= $isCorrected ? 'proposal-card--corrected' : '' ?> <?= $isSubmitted ? 'proposal-card--submitted' : '' ?>"
        data-essay-card
        data-theme="<?= htmlspecialchars((string) ($p['title'] ?? '')) ?>"
        data-board="<?= htmlspecialchars((string) ($p['board_name'] ?? '')) ?>"
        data-status="<?= htmlspecialchars($cardStatus) ?>"
    >
        <div class="p-5">
            <!-- Header -->
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-bold text-gray-900 truncate"><?= htmlspecialchars($p['title']) ?></h3>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($p['board_name']) ?> — <?= htmlspecialchars($p['text_type_name']) ?></p>
                </div>
                <?php if ($isCorrected): ?>
                <span class="badge-corrigida px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap">
                    Corrigida
                </span>
                <?php elseif ($isSubmitted): ?>
                <span class="badge-enviada px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap">
                    Enviada
                </span>
                <?php elseif ($isAvailable): ?>
                <span class="badge-disponivel px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap">
                    Disponível
                </span>
                <?php else: ?>
                <span class="bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap">
                    <?= $statusText ?>
                </span>
                <?php endif; ?>
            </div>

            <?php if ($isCorrected && $correctionScore !== null): ?>
            <div class="mb-4">
                <span class="score-pill inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold">
                    <?= number_format($correctionScore, 0, ',', '.') ?>/<?= number_format($maxTotalScore, 0, ',', '.') ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Info -->
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gray-500 mb-4">
                <?php if (!empty($p['teacher_name'])): ?>
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span><?= htmlspecialchars($p['teacher_name']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($startsAt || $endsAt): ?>
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <?php if ($startsAt && $endsAt): ?>
                    <span><?= date('d/m H:i', strtotime($startsAt)) ?> - <?= date('d/m H:i', strtotime($endsAt)) ?></span>
                    <?php elseif ($startsAt): ?>
                    <span>A partir de <?= date('d/m H:i', strtotime($startsAt)) ?></span>
                    <?php else: ?>
                    <span>Até <?= date('d/m H:i', strtotime($endsAt)) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Botão -->
            <a href="<?= htmlspecialchars($buttonHref) ?>" 
               class="btn-ver-proposta w-full block text-center text-white font-semibold py-2.5 rounded-lg transition <?= (!$isAvailable && !$isCorrected) ? 'pointer-events-none' : '' ?>">
                <?= htmlspecialchars($buttonText) ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div id="essay-filter-empty" class="filter-empty-state bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center mt-4">
    <div class="text-4xl mb-4">🔎</div>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">Nenhuma redação encontrada</h3>
    <p class="text-gray-500 text-sm">Tente ajustar os filtros para encontrar a proposta desejada.</p>
</div>
<?php endif; ?>

<!-- Link histórico -->
<div class="mt-6 text-center">
    <a href="<?= URL ?>/jornada-redacao/historico" class="inline-flex items-center gap-2 text-sm font-medium hover:underline" style="color: <?= htmlspecialchars($primaryColor) ?>;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Ver meu histórico de redações
    </a>
</div>

<?php if (!empty($proposals)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const themeInput = document.getElementById('essay-filter-theme');
    const boardSelect = document.getElementById('essay-filter-board');
    const statusSelect = document.getElementById('essay-filter-status');
    const cards = Array.from(document.querySelectorAll('[data-essay-card]'));
    const emptyState = document.getElementById('essay-filter-empty');

    function normalize(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function applyFilters() {
        const themeValue = normalize(themeInput ? themeInput.value : '');
        const boardValue = normalize(boardSelect ? boardSelect.value : '');
        const statusValue = normalize(statusSelect ? statusSelect.value : '');
        let visibleCount = 0;

        cards.forEach(function (card) {
            const theme = normalize(card.dataset.theme || '');
            const board = normalize(card.dataset.board || '');
            const status = normalize(card.dataset.status || '');

            const matchesTheme = !themeValue || theme.indexOf(themeValue) !== -1;
            const matchesBoard = !boardValue || board === boardValue;
            const matchesStatus = !statusValue || status === statusValue;
            const shouldShow = matchesTheme && matchesBoard && matchesStatus;

            card.style.display = shouldShow ? '' : 'none';
            if (shouldShow) {
                visibleCount += 1;
            }
        });

        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    [themeInput, boardSelect, statusSelect].forEach(function (element) {
        if (!element) return;
        element.addEventListener('input', applyFilters);
        element.addEventListener('change', applyFilters);
    });

    applyFilters();
});
</script>
<?php endif; ?>
