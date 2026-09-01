<?php
$filtros = is_array($filtros ?? null) ? $filtros : [];
$filtro = $filtros['filtro'] ?? [];
$anos = $filtros['anos'] ?? [];
$cursos = $filtros['cursos'] ?? [];
$series = $filtros['series'] ?? [];
$turmas = $filtros['turmas'] ?? [];
$turnos = $filtros['turnos'] ?? [];
$widgets = $filtros['widgets'] ?? [];
$widgetUrl = (string) ($widget_url ?? (URL . '/admin/dashboard/widget'));
$filtrosUrl = (string) ($filtros_url ?? (URL . '/admin/dashboard/filtros'));
$alunosOnline = (int) ($alunos_online ?? 0);
$alertasNovos = $alertas_novos ?? null;
$aulasOnline = is_array($aulas_online ?? null) ? $aulas_online : [];

$tem = static fn (string $k): bool => in_array($k, $widgets, true);

$filtrosExtrasAtivos = ((int) ($filtro['curso_id'] ?? 0) > 0)
    || ((int) ($filtro['serie_id'] ?? 0) > 0)
    || (($filtro['turno'] ?? '') !== '');

$page_header_title = 'Dashboard';
$page_header_subtitle = 'Visão da escola neste recorte.';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
?>

<form id="dash-filtros" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6" method="get">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Ano letivo</label>
            <select name="ano_letivo_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <?php foreach ($anos as $ano): ?>
                    <option value="<?= (int) $ano['id'] ?>" <?= ((int) ($filtro['ano_letivo_id'] ?? 0) === (int) $ano['id']) ? 'selected' : '' ?>>
                        <?= (int) $ano['ano'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Bimestre</label>
            <select name="bimestre" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <?php for ($b = 1; $b <= 4; $b++): ?>
                    <option value="<?= $b ?>" <?= ((int) ($filtro['bimestre'] ?? 0) === $b) ? 'selected' : '' ?>><?= $b ?>º</option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Turma</label>
            <select name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <option value="">Todas</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= ((int) ($filtro['turma_id'] ?? 0) === (int) $t['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $t['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <button type="button" id="dash-mais-filtros" class="mt-3 text-sm font-medium text-gray-600 hover:text-gray-900">
        <?= $filtrosExtrasAtivos ? 'Menos filtros' : 'Mais filtros' ?>
    </button>
    <div id="dash-filtros-extra" class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-3 <?= $filtrosExtrasAtivos ? '' : 'hidden' ?>">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Curso</label>
            <select name="curso_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <option value="">Toda a escola</option>
                <?php foreach ($cursos as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= ((int) ($filtro['curso_id'] ?? 0) === (int) $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $c['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Série</label>
            <select name="serie_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <option value="">Todas</option>
                <?php foreach ($series as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= ((int) ($filtro['serie_id'] ?? 0) === (int) $s['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $s['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Turno</label>
            <select name="turno" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <option value="">Todos</option>
                <?php foreach ($turnos as $valor => $label): ?>
                    <option value="<?= htmlspecialchars((string) $valor) ?>" <?= (($filtro['turno'] ?? '') === $valor) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if ($alunosOnline > 0 || (int) $alertasNovos > 0): ?>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
    <?php if ($alunosOnline > 0): ?>
    <a href="<?= URL ?>/admin/alunos-online" class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Alunos online</p>
                <p id="alunos-online-count" class="mt-1 text-2xl font-bold leading-none text-gray-900"><?= $alunosOnline ?></p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                <i class="fa-solid fa-signal text-sm"></i>
            </div>
        </div>
    </a>
    <?php endif; ?>
    <?php if ((int) $alertasNovos > 0): ?>
    <a href="<?= URL ?>/admin/monitoramento/alertas" class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Alertas sensíveis</p>
                <p class="mt-1 text-2xl font-bold leading-none text-red-700"><?= (int) $alertasNovos ?></p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
                <i class="fa-solid fa-bell text-sm"></i>
            </div>
        </div>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($aulasOnline !== []): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-900">Aulas online</h3>
        <a href="<?= URL ?>/admin/aulas-online" class="text-sm text-primary-600 hover:underline">Ver todas</a>
    </div>
    <div class="space-y-2">
        <?php foreach ($aulasOnline as $aula): ?>
            <?php
                $inicioTs = !empty($aula['inicio_em']) ? strtotime((string) $aula['inicio_em']) : false;
                $fimTs = !empty($aula['fim_em']) ? strtotime((string) $aula['fim_em']) : false;
                $aoVivo = $inicioTs !== false && time() >= $inicioTs && ($fimTs === false || time() <= $fimTs);
            ?>
            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2 <?= $aoVivo ? 'bg-rose-50' : 'bg-gray-50' ?>">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars((string) ($aula['titulo'] ?? 'Aula')) ?></p>
                    <p class="text-xs text-gray-500"><?= $aoVivo ? 'Ao vivo' : ($inicioTs ? date('d/m H:i', $inicioTs) : '') ?></p>
                </div>
                <a href="<?= URL ?>/admin/aulas-online/chat?id=<?= (int) ($aula['id'] ?? 0) ?>" class="text-xs font-semibold text-primary-700 hover:underline shrink-0">Abrir</a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div id="dash-gestao-root"
     data-widget-url="<?= htmlspecialchars($widgetUrl) ?>"
     data-filtros-url="<?= htmlspecialchars($filtrosUrl) ?>"
     data-base-url="<?= htmlspecialchars(URL) ?>">

    <?php if ($tem('kpis')): ?>
    <div data-widget="kpis" class="mb-6"><?php include __DIR__ . '/_skeleton_cards.php'; ?></div>
    <?php endif; ?>

    <?php if ($tem('pendencias')): ?>
    <div class="mb-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-1">Para fazer</h3>
        <p class="text-xs text-gray-500 mb-3">Diários e ocorrências: últimos 7 dias. Notas: bimestre.</p>
        <div data-widget="pendencias"><?php include __DIR__ . '/_skeleton_cards.php'; ?></div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <?php if ($tem('frequencia_hoje')): ?>
        <div data-widget="frequencia_hoje" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 min-h-[180px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
        <?php if ($tem('desempenho')): ?>
        <div data-widget="desempenho" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 min-h-[180px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
    </div>

    <?php if ($tem('evolucao')): ?>
    <div data-widget="evolucao" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6 min-h-[260px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <?php if ($tem('atencao_pedagogica')): ?>
        <div data-widget="atencao_pedagogica" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 min-h-[220px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
        <?php if ($tem('calendario')): ?>
        <div data-widget="calendario" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 min-h-[220px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <?php if ($tem('avaliacoes')): ?>
        <div data-widget="avaliacoes" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 min-h-[200px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
        <?php if ($tem('conselho')): ?>
        <div data-widget="conselho" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 min-h-[180px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <?php if ($tem('ocorrencias')): ?>
        <div data-widget="ocorrencias" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 min-h-[180px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
        <?php if ($tem('matriculas')): ?>
        <div data-widget="matriculas" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 min-h-[180px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="<?= URL ?>/public/static/js/dashboard-gestao.js?v=2"></script>
<script>
(() => {
    const countEl = document.getElementById('alunos-online-count');
    if (!countEl) return;
    const applyWsCount = (data) => {
        const escola = window.EDUCATUDO_WS?.escola;
        if (!escola || !data || !data[escola]) return false;
        const alunos = Number(data[escola]?.alunos ?? 0);
        countEl.textContent = Number.isFinite(alunos) ? String(alunos) : '0';
        return true;
    };
    const bindWebsocketPresence = () => {
        if (!window.EDUCATUDO_WS) {
            setTimeout(bindWebsocketPresence, 500);
            return;
        }
        const prevHandler = window.EDUCATUDO_WS.onMasterUpdate;
        window.EDUCATUDO_WS.onMasterUpdate = (data) => {
            if (typeof prevHandler === 'function') prevHandler(data);
            applyWsCount(data);
        };
        if (window.EDUCATUDO_WS.lastUpdate) applyWsCount(window.EDUCATUDO_WS.lastUpdate);
    };
    bindWebsocketPresence();
    const streamUrl = '<?= URL ?>/admin/api/alunos-online/stream';
    const fallbackUrl = '<?= URL ?>/admin/api/alunos-online';
    const updateCount = (payload) => {
        const total = Number(payload?.total ?? 0);
        countEl.textContent = Number.isFinite(total) ? String(total) : '0';
    };
    if ('EventSource' in window) {
        const es = new EventSource(streamUrl);
        es.addEventListener('online', (event) => {
            try { updateCount(JSON.parse(event.data || '{}')); } catch (_) {}
        });
        es.onerror = () => {
            es.close();
            fetch(fallbackUrl, { credentials: 'same-origin' }).then((r) => r.json()).then(updateCount).catch(() => {});
        };
        return;
    }
    fetch(fallbackUrl, { credentials: 'same-origin' }).then((r) => r.json()).then(updateCount).catch(() => {});
})();
</script>
