<?php
$alunos      = $alunos ?? [];
$aluno       = $aluno ?? null;
$aluno_id    = $aluno_id ?? null;
$kpis        = $kpis ?? null;
$evolucao    = $evolucao ?? [];
$por_proposta = $por_proposta ?? [];
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
                <span>Por Aluno</span>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Relatório por Aluno</h2>
        </div>
        <?php if ($aluno): ?>
        <div class="flex flex-wrap gap-2">
            <a href="<?= URL ?>/admin/redacao-professor/analytics/aluno?aluno_id=<?= $aluno_id ?>&de=<?= htmlspecialchars($filtro_from) ?>&ate=<?= htmlspecialchars($filtro_to) ?>&print=1"
               target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-violet-400 text-sm font-medium text-violet-700 bg-violet-50 hover:bg-violet-100">
                <i class="fa-solid fa-print"></i> PDF / Imprimir
            </a>
            <a href="<?= URL ?>/admin/redacao-professor/analytics/exportar?tipo=aluno&aluno_id=<?= $aluno_id ?>&de=<?= htmlspecialchars($filtro_from) ?>&ate=<?= htmlspecialchars($filtro_to) ?>"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-emerald-400 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100">
                <i class="fa-solid fa-file-csv"></i> Exportar Excel
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Seletor de aluno + período -->
<form method="GET" action="<?= URL ?>/admin/redacao-professor/analytics/aluno" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-medium text-gray-600 mb-1">Aluno</label>
        <select name="aluno_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" onchange="this.form.submit()">
            <option value="">— Selecione um aluno —</option>
            <?php foreach ($alunos as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= $aluno_id === (int)$a['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)($a['nome'] ?? '')) ?> <?= !empty($a['turma_nome']) ? '(' . htmlspecialchars($a['turma_nome']) . ')' : '' ?>
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

<?php if (!$aluno): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center text-gray-400">
    <i class="fa-solid fa-user-graduate text-4xl mb-3 block"></i>
    <p>Selecione um aluno para ver o relatório.</p>
</div>
<?php else: ?>

<!-- Nome do aluno -->
<div class="flex items-center gap-3 mb-5">
    <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold text-lg">
        <?= mb_strtoupper(mb_substr((string)($aluno['nome'] ?? 'A'), 0, 1)) ?>
    </div>
    <div>
        <p class="font-semibold text-gray-900"><?= htmlspecialchars((string)($aluno['nome'] ?? '')) ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars((string)($aluno['turma_nome'] ?? '')) ?></p>
    </div>
</div>

<!-- KPIs do aluno -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <?php
    $cards = [
        ['label' => 'Envios',      'value' => (int)($kpis['total_envios'] ?? 0),   'icon' => 'fa-paper-plane', 'bg' => 'bg-blue-50 text-blue-600'],
        ['label' => 'Corrigidos',  'value' => (int)($kpis['corrigidos'] ?? 0),      'icon' => 'fa-check',        'bg' => 'bg-emerald-50 text-emerald-600'],
        ['label' => 'Pendentes',   'value' => (int)($kpis['pendentes'] ?? 0),       'icon' => 'fa-clock',        'bg' => 'bg-amber-50 text-amber-600'],
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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Gráfico evolução -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h3 class="text-sm font-semibold text-gray-700">Evolução da nota</h3>
            <?php if (!empty($evolucao)): ?>
            <select id="select-ordenar-evolucao" class="border border-gray-300 rounded-lg px-2 py-1 text-xs text-gray-600 bg-white">
                <option value="asc">↑ Primeira enviada primeiro</option>
                <option value="desc">↓ Última enviada primeiro</option>
            </select>
            <?php endif; ?>
        </div>
        <?php if (empty($evolucao)): ?>
            <p class="text-sm text-gray-400 text-center py-8">Nenhuma redação corrigida ainda.</p>
        <?php else: ?>
            <canvas id="chartEvolucao" height="220"></canvas>
        <?php endif; ?>
    </div>

    <!-- Ranking na turma (placeholder visual) -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Resumo de participação</h3>
        <?php
        $totalP = count($por_proposta);
        $feitas = count(array_filter($por_proposta, fn($p) => !empty($p['status'])));
        $naoFeitas = $totalP - $feitas;
        $pct = $totalP > 0 ? round($feitas / $totalP * 100) : 0;
        ?>
        <div class="space-y-4">
            <div>
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>Redações realizadas</span>
                    <span><?= $feitas ?>/<?= $totalP ?> (<?= $pct ?>%)</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-3">
                    <div class="h-3 rounded-full bg-emerald-500 transition-all" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="bg-emerald-50 rounded-lg p-3">
                    <p class="text-xl font-bold text-emerald-700"><?= $feitas ?></p>
                    <p class="text-xs text-gray-500">Realizadas</p>
                </div>
                <div class="bg-rose-50 rounded-lg p-3">
                    <p class="text-xl font-bold text-rose-700"><?= $naoFeitas ?></p>
                    <p class="text-xs text-gray-500">Não realizadas</p>
                </div>
                <div class="bg-violet-50 rounded-lg p-3">
                    <p class="text-xl font-bold text-violet-700"><?= $pct ?>%</p>
                    <p class="text-xs text-gray-500">Participação</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de propostas -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-4">
        <h3 class="text-sm font-semibold text-gray-700">Redações por proposta</h3>
        <select id="select-ordenar-propostas-aluno" class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs text-gray-600 bg-white">
            <option value="data_asc">↑ Mais antiga primeiro</option>
            <option value="data_desc">↓ Mais recente primeiro</option>
            <option value="nota_desc">↓ Maior nota</option>
            <option value="nota_asc">↑ Menor nota</option>
        </select>
    </div>
    <div class="overflow-x-auto">
        <table id="tabela-propostas-aluno" class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" onclick="ordenarTabelaAluno('titulo')">Proposta <span class="ml-1 text-gray-300" id="sort-aluno-titulo">↕</span></th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" onclick="ordenarTabelaAluno('banca')">Banca <span class="ml-1 text-gray-300" id="sort-aluno-banca">↕</span></th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" onclick="ordenarTabelaAluno('criacao')">Data criação <span class="ml-1 text-gray-300" id="sort-aluno-criacao">↕</span></th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" onclick="ordenarTabelaAluno('data')">Data envio <span class="ml-1 text-gray-300" id="sort-aluno-data">↕</span></th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" onclick="ordenarTabelaAluno('nota')">Nota <span class="ml-1 text-gray-300" id="sort-aluno-nota">↕</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($por_proposta as $row): ?>
                <?php
                $nota = $row['nota'] !== null ? (float)$row['nota'] : null;
                $dataTs = !empty($row['submitted_at']) ? strtotime($row['submitted_at']) : 0;
                $criacaoTs = !empty($row['proposta_criada_em']) ? strtotime($row['proposta_criada_em']) : 0;
                ?>
                <tr class="hover:bg-gray-50"
                    data-titulo="<?= htmlspecialchars((string)($row['titulo'] ?? '')) ?>"
                    data-banca="<?= htmlspecialchars((string)($row['banca'] ?? '')) ?>"
                    data-data="<?= $dataTs ?>"
                    data-criacao="<?= $criacaoTs ?>"
                    data-nota="<?= $nota !== null ? $nota : -1 ?>">
                    <td class="px-6 py-3 text-sm text-gray-800"><?= htmlspecialchars((string)($row['titulo'] ?? '')) ?></td>
                    <td class="px-6 py-3 text-sm text-gray-500"><?= htmlspecialchars((string)($row['banca'] ?? '—')) ?></td>
                    <td class="px-6 py-3 text-center">
                        <?php if (empty($row['status'])): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Não enviou</span>
                        <?php elseif ($row['status'] === 'corrected'): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Corrigido</span>
                        <?php elseif ($row['status'] === 'submitted'): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Enviado</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Rascunho</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-center text-xs text-gray-500">
                        <?= !empty($row['proposta_criada_em']) ? date('d/m/Y', strtotime($row['proposta_criada_em'])) : '—' ?>
                    </td>
                    <td class="px-6 py-3 text-center text-xs text-gray-500">
                        <?= !empty($row['submitted_at']) ? date('d/m/Y', strtotime($row['submitted_at'])) : '—' ?>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <?php if ($nota !== null): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold <?= $nota >= 600 ? 'bg-emerald-100 text-emerald-700' : ($nota >= 400 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') ?>">
                                <?= number_format($nota, 0) ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($evolucao)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function() {
    const labelsAsc = <?= json_encode(array_map(fn($r) => mb_substr((string)($r['proposta_titulo'] ?? ''), 0, 20) . (mb_strlen((string)($r['proposta_titulo'] ?? '')) > 20 ? '…' : ''), $evolucao)) ?>;
    const notasAsc  = <?= json_encode(array_map(fn($r) => $r['nota'] !== null ? (float)$r['nota'] : null, $evolucao)) ?>;

    let chartEvolucao = new Chart(document.getElementById('chartEvolucao'), {
        type: 'line',
        data: {
            labels: labelsAsc,
            datasets: [{
                label: 'Nota',
                data: notasAsc,
                borderColor: 'rgb(99,102,241)',
                backgroundColor: 'rgba(99,102,241,0.1)',
                pointBackgroundColor: 'rgb(99,102,241)',
                tension: 0.3,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: false, min: 0, max: 1000, ticks: { stepSize: 100 } }
            }
        }
    });

    const selectEvolucao = document.getElementById('select-ordenar-evolucao');
    if (selectEvolucao) {
        selectEvolucao.addEventListener('change', function() {
            const asc = this.value === 'asc';
            chartEvolucao.data.labels = asc ? labelsAsc : [...labelsAsc].reverse();
            chartEvolucao.data.datasets[0].data = asc ? notasAsc : [...notasAsc].reverse();
            chartEvolucao.update();
        });
    }
})();
</script>
<?php endif; ?>

<script>
(function() {
    const tbody = document.querySelector('#tabela-propostas-aluno tbody');
    const linhas = tbody ? Array.from(tbody.querySelectorAll('tr[data-titulo]')) : [];
    const sortIcons = {
        titulo: document.getElementById('sort-aluno-titulo'),
        banca: document.getElementById('sort-aluno-banca'),
        criacao: document.getElementById('sort-aluno-criacao'),
        data: document.getElementById('sort-aluno-data'),
        nota: document.getElementById('sort-aluno-nota'),
    };
    let ordemAtual = { campo: 'data', dir: 'asc' };

    function aplicarOrdenacao(campo, dir) {
        if (!linhas.length) return;
        const isTexto = campo === 'titulo' || campo === 'banca';
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

        const select = document.getElementById('select-ordenar-propostas-aluno');
        if (select && (campo === 'data' || campo === 'nota')) {
            select.value = campo + '_' + dir;
        }
    }

    window.ordenarTabelaAluno = function(campo) {
        const dir = (ordemAtual.campo === campo && ordemAtual.dir === 'desc') ? 'asc' : 'desc';
        aplicarOrdenacao(campo, dir);
    };

    const select = document.getElementById('select-ordenar-propostas-aluno');
    if (select) {
        select.addEventListener('change', function() {
            const [campo, dir] = this.value.split('_');
            aplicarOrdenacao(campo, dir);
        });
    }
})();
</script>
<?php endif; ?>
