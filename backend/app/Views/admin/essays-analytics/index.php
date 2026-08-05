<?php
$kpis             = $kpis ?? [];
$envios_por_mes   = $envios_por_mes ?? [];
$distribuicao_notas = $distribuicao_notas ?? [];
$top_propostas    = $top_propostas ?? [];
$turmas           = $turmas ?? [];
$filtro_turma     = $filtro_turma ?? null;
$filtro_from      = $filtro_from ?? '';
$filtro_to        = $filtro_to ?? '';

function kpiColor(float $v, float $good, float $bad, bool $higher = true): string {
    if ($higher) { return $v >= $good ? 'text-emerald-600' : ($v <= $bad ? 'text-red-600' : 'text-amber-600'); }
    return $v <= $good ? 'text-emerald-600' : ($v >= $bad ? 'text-red-600' : 'text-amber-600');
}
?>
<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<!-- Cabeçalho -->
<div class="mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Analytics — Jornada da Redação</h2>
            <p class="text-sm text-gray-500 mt-0.5">Visão geral de desempenho, evolução e participação.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= URL ?>/admin/redacao-professor/analytics/aluno" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fa-solid fa-user-graduate"></i> Por aluno
            </a>
            <a href="<?= URL ?>/admin/redacao-professor/analytics/proposta" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fa-solid fa-file-pen"></i> Por proposta
            </a>
            <a href="<?= URL ?>/admin/redacao-professor/analytics/exportar?tipo=geral&de=<?= htmlspecialchars($filtro_from) ?>&ate=<?= htmlspecialchars($filtro_to) ?>"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-emerald-400 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100">
                <i class="fa-solid fa-file-csv"></i> Exportar Excel
            </a>
            <a href="<?= URL ?>/admin/redacao-professor/relatorio" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 bg-white hover:bg-gray-50">
                <i class="fa-solid fa-arrow-left"></i> Relatório
            </a>
        </div>
    </div>
</div>

<!-- Filtros -->
<form method="GET" action="<?= URL ?>/admin/redacao-professor/analytics" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Turma</label>
        <select name="turma_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[160px]">
            <option value="">Todas as turmas</option>
            <?php foreach ($turmas as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $filtro_turma === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">De</label>
        <input type="date" name="de" value="<?= htmlspecialchars($filtro_from) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Até</label>
        <input type="date" name="ate" value="<?= htmlspecialchars($filtro_to) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:opacity-90">Filtrar</button>
    <a href="<?= URL ?>/admin/redacao-professor/analytics" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Limpar</a>
</form>

<!-- KPIs -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <?php
    $kpiCards = [
        ['label' => 'Alunos participantes', 'value' => number_format((int)($kpis['total_alunos'] ?? 0)), 'icon' => 'fa-users', 'color' => 'bg-blue-50 text-blue-600'],
        ['label' => 'Total de envios',      'value' => number_format((int)($kpis['total_envios'] ?? 0)), 'icon' => 'fa-paper-plane', 'color' => 'bg-indigo-50 text-indigo-600'],
        ['label' => 'Corrigidos',           'value' => number_format((int)($kpis['total_corrigidos'] ?? 0)), 'icon' => 'fa-check-circle', 'color' => 'bg-emerald-50 text-emerald-600'],
        ['label' => 'Pendentes',            'value' => number_format((int)($kpis['total_pendentes'] ?? 0)),  'icon' => 'fa-clock',         'color' => 'bg-amber-50 text-amber-600'],
        ['label' => 'Média geral',          'value' => ($kpis['media_geral'] !== null ? number_format((float)$kpis['media_geral'], 1, ',', '.') : '—'), 'icon' => 'fa-star-half-stroke', 'color' => 'bg-violet-50 text-violet-600'],
        ['label' => 'Maior nota',           'value' => ($kpis['nota_maxima'] !== null ? number_format((float)$kpis['nota_maxima'], 0) : '—'), 'icon' => 'fa-trophy', 'color' => 'bg-yellow-50 text-yellow-600'],
        ['label' => 'Menor nota',           'value' => ($kpis['nota_minima'] !== null ? number_format((float)$kpis['nota_minima'], 0) : '—'), 'icon' => 'fa-arrow-down', 'color' => 'bg-rose-50 text-rose-600'],
        ['label' => 'Propostas ativas',     'value' => number_format((int)($kpis['total_propostas'] ?? 0)), 'icon' => 'fa-file-lines', 'color' => 'bg-teal-50 text-teal-600'],
    ];
    ?>
    <?php foreach ($kpiCards as $card): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-full <?= $card['color'] ?> flex items-center justify-center flex-shrink-0">
            <i class="fa-solid <?= $card['icon'] ?> text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500 leading-none mb-0.5"><?= $card['label'] ?></p>
            <p class="text-xl font-bold text-gray-900"><?= $card['value'] ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Gráficos -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Envios por mês -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Envios por mês (últimos 12 meses)</h3>
        <canvas id="chartEnviosMes" height="200"></canvas>
    </div>
    <!-- Distribuição de notas -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Distribuição de notas</h3>
        <canvas id="chartDistribuicao" height="200"></canvas>
    </div>
</div>

<!-- Top propostas -->
<?php
$ordenar = $_GET['ordenar'] ?? 'envios_desc';
$validOrdens = [
    'envios_desc' => 'Mais enviados',
    'envios_asc'  => 'Menos enviados',
    'nota_desc'   => 'Maior média',
    'nota_asc'    => 'Menor média',
];
// Ordenação client-side via JS para não precisar de request extra
?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-4">
        <h3 class="text-sm font-semibold text-gray-700">Top propostas por participação</h3>
        <select id="select-ordenar-propostas" class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs text-gray-600 bg-white">
            <option value="envios_desc" <?= $ordenar === 'envios_desc' ? 'selected' : '' ?>>↓ Mais enviados</option>
            <option value="envios_asc"  <?= $ordenar === 'envios_asc'  ? 'selected' : '' ?>>↑ Menos enviados</option>
            <option value="nota_desc"   <?= $ordenar === 'nota_desc'   ? 'selected' : '' ?>>↓ Maior média</option>
            <option value="nota_asc"    <?= $ordenar === 'nota_asc'    ? 'selected' : '' ?>>↑ Menor média</option>
        </select>
    </div>
    <div class="overflow-x-auto">
        <table id="tabela-propostas" class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" onclick="ordenarTabela('titulo')">Proposta <span class="ml-1 text-gray-300" id="sort-titulo">↕</span></th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" onclick="ordenarTabela('envios')">Envios <span class="ml-1 text-gray-300" id="sort-envios">↕</span></th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" onclick="ordenarTabela('corrigidos')">Corrigidos <span class="ml-1 text-gray-300" id="sort-corrigidos">↕</span></th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" onclick="ordenarTabela('nota')">Média <span class="ml-1 text-gray-300" id="sort-nota">↕</span></th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($top_propostas)): ?>
                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">Nenhum dado encontrado.</td></tr>
                <?php else: ?>
                <?php foreach ($top_propostas as $p): ?>
                <?php $nota = $p['media_nota'] !== null ? (float)$p['media_nota'] : null; ?>
                <tr class="hover:bg-gray-50"
                    data-titulo="<?= htmlspecialchars((string)($p['titulo'] ?? '')) ?>"
                    data-envios="<?= (int)$p['total_envios'] ?>"
                    data-corrigidos="<?= (int)($p['corrigidos'] ?? 0) ?>"
                    data-nota="<?= $nota !== null ? $nota : -1 ?>">
                    <td class="px-6 py-3 text-sm text-gray-800"><?= htmlspecialchars((string)($p['titulo'] ?? '')) ?></td>
                    <td class="px-6 py-3 text-center text-sm font-medium text-gray-700"><?= (int)$p['total_envios'] ?></td>
                    <td class="px-6 py-3 text-center text-sm text-gray-600"><?= (int)($p['corrigidos'] ?? 0) ?></td>
                    <td class="px-6 py-3 text-center">
                        <?php if ($nota !== null): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $nota >= 600 ? 'bg-emerald-100 text-emerald-700' : ($nota >= 400 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') ?>">
                                <?= number_format($nota, 0) ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <a href="<?= URL ?>/admin/redacao-professor/analytics/proposta?proposta_id=<?= (int)$p['proposal_id'] ?? 0 ?>" class="text-xs text-indigo-600 hover:underline">Detalhar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function() {
    const meses = <?= json_encode(array_column($envios_por_mes, 'mes')) ?>;
    const envios = <?= json_encode(array_map(fn($r) => (int)$r['total'], $envios_por_mes)) ?>;
    const corrigidos = <?= json_encode(array_map(fn($r) => (int)$r['corrigidos'], $envios_por_mes)) ?>;

    new Chart(document.getElementById('chartEnviosMes'), {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [
                { label: 'Envios', data: envios, backgroundColor: 'rgba(99,102,241,0.7)', borderRadius: 4 },
                { label: 'Corrigidos', data: corrigidos, backgroundColor: 'rgba(16,185,129,0.7)', borderRadius: 4 },
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    const faixas = <?= json_encode(array_map(fn($r) => (int)$r['faixa'] . '–' . ((int)$r['faixa'] + 99), $distribuicao_notas)) ?>;
    const qtds   = <?= json_encode(array_map(fn($r) => (int)$r['total'], $distribuicao_notas)) ?>;

    new Chart(document.getElementById('chartDistribuicao'), {
        type: 'bar',
        data: {
            labels: faixas,
            datasets: [{ label: 'Alunos', data: qtds, backgroundColor: 'rgba(139,92,246,0.7)', borderRadius: 4 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    const tbody = document.querySelector('#tabela-propostas tbody');
    const linhas = tbody ? Array.from(tbody.querySelectorAll('tr[data-titulo]')) : [];
    const sortIcons = { titulo: document.getElementById('sort-titulo'), envios: document.getElementById('sort-envios'), corrigidos: document.getElementById('sort-corrigidos'), nota: document.getElementById('sort-nota') };
    let ordemAtual = { campo: 'envios', dir: 'desc' };

    function aplicarOrdenacao(campo, dir) {
        if (!linhas.length) return;
        const isTexto = campo === 'titulo';
        linhas.sort((a, b) => {
            let va = a.dataset[campo], vb = b.dataset[campo];
            if (isTexto) {
                return dir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
            }
            va = parseFloat(va); vb = parseFloat(vb);
            return dir === 'asc' ? va - vb : vb - va;
        });
        linhas.forEach(tr => tbody.appendChild(tr));

        Object.entries(sortIcons).forEach(([campoIcon, el]) => {
            if (!el) return;
            el.textContent = campoIcon === campo ? (dir === 'asc' ? '↑' : '↓') : '↕';
            el.classList.toggle('text-gray-300', campoIcon !== campo);
            el.classList.toggle('text-indigo-600', campoIcon === campo);
        });

        ordemAtual = { campo, dir };

        const select = document.getElementById('select-ordenar-propostas');
        if (select && (campo === 'envios' || campo === 'nota')) {
            select.value = campo + '_' + dir;
        }
    }

    window.ordenarTabela = function(campo) {
        const dir = (ordemAtual.campo === campo && ordemAtual.dir === 'desc') ? 'asc' : 'desc';
        aplicarOrdenacao(campo, dir);
    };

    const select = document.getElementById('select-ordenar-propostas');
    if (select) {
        select.addEventListener('change', function() {
            const [campo, dir] = this.value.split('_');
            aplicarOrdenacao(campo, dir);
        });
        const [campoInicial, dirInicial] = select.value.split('_');
        aplicarOrdenacao(campoInicial, dirInicial);
    }
})();
</script>
