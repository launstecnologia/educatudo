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
$card = 'bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5';
$selectPill = 'bg-transparent border-0 text-sm font-semibold text-gray-900 py-1 pl-1 pr-7 focus:outline-none focus:ring-0 cursor-pointer';

$filtrosExtrasAtivos = ((int) ($filtro['curso_id'] ?? 0) > 0)
    || ((int) ($filtro['serie_id'] ?? 0) > 0)
    || (($filtro['turno'] ?? '') !== '');

$anoDash = (int) date('Y');
foreach ($anos as $anoRow) {
    if ((int) ($filtro['ano_letivo_id'] ?? 0) === (int) ($anoRow['id'] ?? 0)) {
        $anoDash = (int) ($anoRow['ano'] ?? $anoDash);
        break;
    }
}
$rotuloPeriodo = (string) (\App\Core\PeriodoLetivo::doAno($anoDash)['rotulo_campo'] ?? 'Bimestre');
?>

<div id="dash-filtros-anchor" class="mb-4 xl:mb-0">
<form id="dash-filtros" class="relative flex min-w-0 flex-wrap items-center gap-2" method="get">
    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-1">
        <span class="text-xs text-slate-500 whitespace-nowrap">Ano letivo</span>
        <select name="ano_letivo_id" class="<?= $selectPill ?>">
            <?php foreach ($anos as $ano): ?>
                <option value="<?= (int) $ano['id'] ?>" <?= ((int) ($filtro['ano_letivo_id'] ?? 0) === (int) $ano['id']) ? 'selected' : '' ?>>
                    <?= (int) $ano['ano'] ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-1">
        <span class="text-xs text-slate-500 whitespace-nowrap" data-periodo-label><?= htmlspecialchars($rotuloPeriodo) ?></span>
        <select name="bimestre" class="<?= $selectPill ?>">
            <?php echo \App\Core\PeriodoLetivo::optionsHtml($anoDash, (int) ($filtro['bimestre'] ?? 0)); ?>
        </select>
    </label>
    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-1">
        <span class="text-xs text-slate-500 whitespace-nowrap">Turma</span>
        <select name="turma_id" class="<?= $selectPill ?>">
            <option value="">Todas</option>
            <?php foreach ($turmas as $t): ?>
                <option value="<?= (int) $t['id'] ?>" <?= ((int) ($filtro['turma_id'] ?? 0) === (int) $t['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $t['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="btn-primary-custom inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold">Aplicar</button>
    <button type="button" id="dash-mais-filtros" class="text-xs font-medium text-slate-500 hover:text-slate-800 px-1 py-2">
        <?= $filtrosExtrasAtivos ? 'Menos filtros' : 'Mais filtros' ?>
    </button>
    <div id="dash-filtros-extra" class="<?= $filtrosExtrasAtivos ? '' : 'hidden' ?> absolute right-0 top-full z-40 mt-2 w-[32rem] max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white p-4 shadow-lg">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
    </div>
</form>
</div>

<?php if ($alunosOnline > 0 || (int) $alertasNovos > 0): ?>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
    <?php if ($alunosOnline > 0): ?>
    <a href="<?= URL ?>/admin/alunos-online" class="<?= $card ?> hover:shadow-md transition-shadow">
        <p class="text-xs text-slate-500">Alunos online</p>
        <p id="alunos-online-count" class="mt-1 text-2xl font-bold text-gray-900"><?= $alunosOnline ?></p>
    </a>
    <?php endif; ?>
    <?php if ((int) $alertasNovos > 0): ?>
    <a href="<?= URL ?>/admin/monitoramento/alertas" class="<?= $card ?> hover:shadow-md transition-shadow">
        <p class="text-xs text-slate-500">Alertas sensíveis</p>
        <p class="mt-1 text-2xl font-bold text-rose-600"><?= (int) $alertasNovos ?></p>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($aulasOnline !== []): ?>
<div class="<?= $card ?> mb-4">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-900">Aulas online</h3>
        <a href="<?= URL ?>/admin/aulas-online" class="text-xs font-medium text-accent hover:underline">Ver todas</a>
    </div>
    <div class="space-y-2">
        <?php foreach ($aulasOnline as $aula): ?>
            <?php
                $inicioTs = !empty($aula['inicio_em']) ? strtotime((string) $aula['inicio_em']) : false;
                $fimTs = !empty($aula['fim_em']) ? strtotime((string) $aula['fim_em']) : false;
                $aoVivo = $inicioTs !== false && time() >= $inicioTs && ($fimTs === false || time() <= $fimTs);
            ?>
            <div class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 <?= $aoVivo ? 'bg-rose-50' : 'bg-slate-50' ?>">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars((string) ($aula['titulo'] ?? 'Aula')) ?></p>
                    <p class="text-xs text-gray-500"><?= $aoVivo ? 'Ao vivo' : ($inicioTs ? date('d/m H:i', $inicioTs) : '') ?></p>
                </div>
                <a href="<?= URL ?>/admin/aulas-online/chat?id=<?= (int) ($aula['id'] ?? 0) ?>" class="text-xs font-semibold text-accent hover:underline shrink-0">Abrir</a>
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
    <div data-widget="kpis" class="mb-4"><?php include __DIR__ . '/_skeleton_cards.php'; ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 mb-4">
        <?php if ($tem('evolucao')): ?>
        <div data-widget="evolucao" class="<?= $card ?> <?= $tem('desempenho') ? 'xl:col-span-3' : 'xl:col-span-5' ?> min-h-[320px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
        <?php if ($tem('desempenho')): ?>
        <div id="dash-slot-disciplinas" class="<?= $card ?> xl:col-span-2 min-h-[320px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
    </div>

    <?php if ($tem('desempenho') || $tem('frequencia_hoje')): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        <?php if ($tem('desempenho')): ?>
        <div id="dash-slot-distribuicao" class="<?= $card ?> min-h-[280px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <div id="dash-slot-situacao" class="<?= $card ?> min-h-[280px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
        <?php if ($tem('frequencia_hoje')): ?>
        <div data-widget="frequencia_hoje" class="<?= $card ?> min-h-[280px] <?= $tem('desempenho') ? '' : 'lg:col-span-3' ?>"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $blocoInferior = ($tem('atencao_pedagogica') ? 1 : 0) + ($tem('calendario') ? 1 : 0) + ($tem('ocorrencias') ? 1 : 0);
    ?>
    <?php if ($blocoInferior > 0): ?>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
        <?php if ($tem('atencao_pedagogica')): ?>
        <div data-widget="atencao_pedagogica" class="<?= $card ?> min-h-[280px] <?= $blocoInferior === 1 ? 'xl:col-span-3' : '' ?>"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
        <?php if ($tem('calendario')): ?>
        <div data-widget="calendario" class="<?= $card ?> min-h-[280px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
        <?php if ($tem('ocorrencias')): ?>
        <div data-widget="ocorrencias" class="<?= $card ?> min-h-[280px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tem('desempenho')): ?>
    <div data-widget="desempenho" class="hidden" aria-hidden="true"></div>
    <?php endif; ?>

    <?php if ($tem('pendencias')): ?>
    <div class="mb-4">
        <h3 class="text-sm font-semibold text-gray-900 mb-1">Para fazer</h3>
        <p class="text-xs text-gray-500 mb-3">Diários e ocorrências: últimos 7 dias. Notas: período selecionado.</p>
        <div data-widget="pendencias"><?php include __DIR__ . '/_skeleton_cards.php'; ?></div>
    </div>
    <?php endif; ?>

    <?php if ($tem('avaliacoes') || $tem('conselho') || $tem('matriculas')): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        <?php if ($tem('avaliacoes')): ?>
        <div data-widget="avaliacoes" class="<?= $card ?> min-h-[180px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
        <?php if ($tem('conselho')): ?>
        <div data-widget="conselho" class="<?= $card ?> min-h-[180px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
        <?php if ($tem('matriculas')): ?>
        <div data-widget="matriculas" class="<?= $card ?> min-h-[180px]"><?php include __DIR__ . '/_skeleton_bloco.php'; ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="<?= URL ?>/public/static/js/dashboard-gestao.js?v=6"></script>
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
