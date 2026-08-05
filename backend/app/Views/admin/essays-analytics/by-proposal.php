<?php
$propostas   = $propostas ?? [];
$proposta    = $proposta ?? null;
$proposta_id = $proposta_id ?? null;
$kpis        = $kpis ?? null;
$distribuicao = $distribuicao ?? [];
$alunos      = $alunos ?? [];
$filtro_from = $filtro_from ?? '';
$filtro_to   = $filtro_to ?? '';
?>
<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<!-- Cabeçalho -->
<div class="mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="<?= URL ?>/admin/redacao-professor/analytics" class="hover:text-indigo-600">Analytics</a>
                <span>/</span>
                <span>Por Proposta</span>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Relatório por Proposta / Tema</h2>
        </div>
        <?php if ($proposta): ?>
        <div class="flex flex-wrap gap-2">
            <a href="<?= URL ?>/admin/redacao-professor/analytics/proposta?proposta_id=<?= $proposta_id ?>&de=<?= htmlspecialchars($filtro_from) ?>&ate=<?= htmlspecialchars($filtro_to) ?>&print=1"
               target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-violet-400 text-sm font-medium text-violet-700 bg-violet-50 hover:bg-violet-100">
                <i class="fa-solid fa-print"></i> PDF / Imprimir
            </a>
            <a href="<?= URL ?>/admin/redacao-professor/analytics/exportar?tipo=proposta&proposta_id=<?= $proposta_id ?>&de=<?= htmlspecialchars($filtro_from) ?>&ate=<?= htmlspecialchars($filtro_to) ?>"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-emerald-400 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100">
                <i class="fa-solid fa-file-csv"></i> Exportar Excel
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<form method="GET" action="<?= URL ?>/admin/redacao-professor/analytics/proposta" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[220px]">
        <label class="block text-xs font-medium text-gray-600 mb-1">Proposta / Tema</label>
        <select name="proposta_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" onchange="this.form.submit()">
            <option value="">— Selecione uma proposta —</option>
            <?php foreach ($propostas as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $proposta_id === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)($p['titulo'] ?? '')) ?> <?= !empty($p['banca']) ? '(' . htmlspecialchars($p['banca']) . ')' : '' ?>
                </option>
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
    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:opacity-90">Aplicar</button>
</form>

<?php if (!$proposta): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center text-gray-400">
    <i class="fa-solid fa-file-pen text-4xl mb-3 block"></i>
    <p>Selecione uma proposta para ver o relatório.</p>
</div>
<?php else: ?>

<!-- Título da proposta -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-5 flex items-center gap-3">
    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0">
        <i class="fa-solid fa-file-pen"></i>
    </div>
    <div>
        <p class="font-semibold text-gray-900"><?= htmlspecialchars((string)($proposta['titulo'] ?? '')) ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars((string)($proposta['banca'] ?? '—')) ?></p>
    </div>
</div>

<!-- KPIs da proposta -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <?php
    $cards = [
        ['label' => 'Envios',      'value' => (int)($kpis['total_envios'] ?? 0),   'icon' => 'fa-paper-plane',       'bg' => 'bg-blue-50 text-blue-600'],
        ['label' => 'Corrigidos',  'value' => (int)($kpis['corrigidos'] ?? 0),      'icon' => 'fa-check',             'bg' => 'bg-emerald-50 text-emerald-600'],
        ['label' => 'Pendentes',   'value' => (int)($kpis['pendentes'] ?? 0),       'icon' => 'fa-clock',             'bg' => 'bg-amber-50 text-amber-600'],
        ['label' => 'Média',       'value' => $kpis['media_nota'] !== null ? number_format((float)$kpis['media_nota'], 1, ',', '.') : '—', 'icon' => 'fa-star-half-stroke', 'bg' => 'bg-violet-50 text-violet-600'],
        ['label' => 'Maior nota',  'value' => $kpis['nota_maxima'] !== null ? number_format((float)$kpis['nota_maxima'], 0) : '—', 'icon' => 'fa-trophy', 'bg' => 'bg-yellow-50 text-yellow-600'],
        ['label' => 'Menor nota',  'value' => $kpis['nota_minima'] !== null ? number_format((float)$kpis['nota_minima'], 0) : '—', 'icon' => 'fa-arrow-trend-down', 'bg' => 'bg-rose-50 text-rose-600'],
    ];
    ?>
    <?php foreach ($cards as $c): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-center">
        <div class="w-8 h-8 rounded-full <?= $c['bg'] ?> flex items-center justify-center mx-auto mb-2">
            <i class="fa-solid <?= $c['icon'] ?> text-xs"></i>
        </div>
        <p class="text-xl font-bold text-gray-900"><?= $c['value'] ?></p>
        <p class="text-xs text-gray-500 mt-0.5"><?= $c['label'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Gráfico distribuição -->
<?php if (!empty($distribuicao)): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Distribuição de notas nesta proposta</h3>
    <canvas id="chartDist" height="120"></canvas>
</div>
<?php endif; ?>

<!-- Nível de dificuldade (interpretação automática) -->
<?php
$media = $kpis['media_nota'] !== null ? (float)$kpis['media_nota'] : null;
if ($media !== null):
    if ($media >= 700)      { $dificTag = 'Fácil';  $dificColor = 'bg-emerald-100 text-emerald-700'; $dificIcon = '😊'; }
    elseif ($media >= 500)  { $dificTag = 'Médio';  $dificColor = 'bg-amber-100 text-amber-700'; $dificIcon = '😐'; }
    else                    { $dificTag = 'Difícil'; $dificColor = 'bg-red-100 text-red-700'; $dificIcon = '😓'; }
?>
<div class="flex items-center gap-3 mb-6">
    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold <?= $dificColor ?>">
        <?= $dificIcon ?> Dificuldade percebida: <?= $dificTag ?>
    </span>
    <span class="text-xs text-gray-500">(baseado na média de <?= number_format($media, 0) ?> pontos)</span>
</div>
<?php endif; ?>

<!-- Tabela de alunos -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700">Alunos — notas nesta proposta</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Data envio</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Nota</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($alunos)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">Nenhum envio encontrado.</td></tr>
                <?php else: ?>
                <?php foreach ($alunos as $i => $row): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-xs text-gray-400"><?= $i + 1 ?></td>
                    <td class="px-6 py-3 text-sm font-medium text-gray-800"><?= htmlspecialchars((string)($row['aluno_nome'] ?? '')) ?></td>
                    <td class="px-6 py-3 text-sm text-gray-500"><?= htmlspecialchars((string)($row['turma_nome'] ?? '—')) ?></td>
                    <td class="px-6 py-3 text-center">
                        <?php if ($row['status'] === 'corrected'): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Corrigido</span>
                        <?php elseif ($row['status'] === 'submitted'): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Enviado</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Rascunho</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-center text-xs text-gray-500">
                        <?= !empty($row['submitted_at']) ? date('d/m/Y', strtotime($row['submitted_at'])) : '—' ?>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <?php $nota = $row['nota'] !== null ? (float)$row['nota'] : null; ?>
                        <?php if ($nota !== null): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold <?= $nota >= 600 ? 'bg-emerald-100 text-emerald-700' : ($nota >= 400 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') ?>">
                                <?= number_format($nota, 0) ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($distribuicao)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function() {
    const faixas = <?= json_encode(array_map(fn($r) => (int)$r['faixa'] . '–' . ((int)$r['faixa'] + 99), $distribuicao)) ?>;
    const qtds   = <?= json_encode(array_map(fn($r) => (int)$r['total'], $distribuicao)) ?>;
    new Chart(document.getElementById('chartDist'), {
        type: 'bar',
        data: {
            labels: faixas,
            datasets: [{ label: 'Alunos', data: qtds, backgroundColor: 'rgba(99,102,241,0.7)', borderRadius: 4 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
})();
</script>
<?php endif; ?>
<?php endif; ?>
