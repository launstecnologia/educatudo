<?php
$tenant_ok = !empty($tenant_ok);
$blocos = $blocos ?? [];
$bloco = $bloco ?? null;
$materias = $materias ?? [];
$resumo = $resumo ?? [];
$alunos = $alunos ?? [];
$erro = trim((string) ($erro ?? ''));
$atualizado_em = $atualizado_em ?? date('H:i:s');
$escolaId = (int) ($escola['id'] ?? $escola_id ?? 0);
$blocoIdAtual = (int) ($bloco['id'] ?? 0);
$filtro = preg_replace('/[^a-z_]/', '', (string) ($_GET['filtro'] ?? 'todos')) ?: 'todos';
$totalMaterias = count($materias);

$fmtHora = static function (?string $dt): string {
    if ($dt === null || $dt === '' || $dt === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('H:i', $ts) : '';
};

$alunosFiltrados = $alunos;
if ($filtro === 'em_prova') {
    $alunosFiltrados = array_values(array_filter($alunos, static fn($a) => !empty($a['em_prova'])));
} elseif ($filtro === 'canceladas') {
    $alunosFiltrados = array_values(array_filter($alunos, static fn($a) => !empty($a['tem_cancelada'])));
} elseif ($filtro === 'nao_comecou') {
    $alunosFiltrados = array_values(array_filter($alunos, static fn($a) => ($a['por_materia'] ?? []) === []));
} elseif ($filtro === 'concluiu_todas') {
    $alunosFiltrados = array_values(array_filter($alunos, static fn($a) => $totalMaterias > 0 && (int) ($a['materias_ok'] ?? 0) >= $totalMaterias));
}

$cards = [
    ['key' => 'em_prova', 'label' => 'Em prova agora', 'value' => (int) ($resumo['em_prova'] ?? 0), 'class' => 'border-cyan-200', 'num' => 'text-cyan-700'],
    ['key' => 'concluiu_alguma', 'label' => 'Já fez alguma matéria', 'value' => (int) ($resumo['concluiu_alguma'] ?? 0), 'class' => 'border-blue-200', 'num' => 'text-blue-700'],
    ['key' => 'concluiu_todas', 'label' => 'Concluiu todas', 'value' => (int) ($resumo['concluiu_todas'] ?? 0), 'class' => 'border-green-200', 'num' => 'text-green-700'],
    ['key' => 'canceladas', 'label' => 'Canceladas', 'value' => (int) ($resumo['canceladas'] ?? 0), 'class' => 'border-red-200', 'num' => 'text-red-700'],
    ['key' => 'nao_comecou', 'label' => 'Ainda não começou', 'value' => (int) ($resumo['nao_comecou'] ?? 0), 'class' => 'border-slate-200', 'num' => 'text-slate-700'],
];
$ehFragmento = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fragment');
?>

<div id="provas-ao-vivo-painel">
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Provas ao vivo</h3>
            <p class="text-sm text-slate-500 mt-1">Somente leitura. Atualiza sozinho a cada 20 segundos, sem recarregar a página.</p>
            <a href="<?= URL ?>/master/escolas/<?= $escolaId ?>/detalhes" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 mt-2">Voltar à ficha da escola</a>
        </div>
        <p class="text-sm text-slate-500 shrink-0">Atualizado às <?= htmlspecialchars($atualizado_em) ?></p>
    </div>

    <?php if (!$tenant_ok): ?>
    <p class="mt-4 px-4 py-3 rounded-lg border bg-red-50 border-red-200 text-red-800 text-sm">Não foi possível conectar ao banco da escola.</p>
    <?php endif; ?>
    <?php if ($erro !== ''): ?>
    <p class="mt-4 px-4 py-3 rounded-lg border bg-red-50 border-red-200 text-red-800 text-sm"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>
    <?php if ($tenant_ok && $erro === '' && $blocos === []): ?>
    <p class="mt-4 px-4 py-3 rounded-lg border bg-amber-50 border-amber-200 text-amber-800 text-sm">Nenhum bloco de hoje, dos últimos 7 dias ou com prova em andamento.</p>
    <?php elseif ($tenant_ok && $blocos !== []): ?>
    <form method="get" action="<?= URL ?>/master/escolas/<?= $escolaId ?>/provas-ao-vivo" class="mt-4 flex flex-col sm:flex-row gap-3">
        <label class="flex-1 min-w-0">
            <span class="block text-sm font-medium text-slate-700 mb-1">Bloco</span>
            <select name="bloco_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 bg-white text-sm" onchange="this.form.submit()">
                <?php foreach ($blocos as $b):
                    $dataFmt = !empty($b['data_prova']) ? date('d/m/Y', strtotime((string) $b['data_prova'])) : '';
                    $horaFmt = !empty($b['hora_inicio']) ? substr((string) $b['hora_inicio'], 0, 5) : '';
                    $labelBloco = trim(($b['titulo'] ?? 'Bloco') . ($dataFmt ? ' — ' . $dataFmt : '') . ($horaFmt ? ' ' . $horaFmt : ''));
                    if (!empty($b['liberado'])) {
                        $labelBloco .= ' [liberado]';
                    }
                ?>
                <option value="<?= (int) $b['id'] ?>" <?= $blocoIdAtual === (int) $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($labelBloco) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="sm:w-56">
            <span class="block text-sm font-medium text-slate-700 mb-1">Filtrar alunos</span>
            <select name="filtro" class="w-full border border-slate-300 rounded-lg px-3 py-2 bg-white text-sm" onchange="this.form.submit()">
                <option value="todos" <?= $filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="em_prova" <?= $filtro === 'em_prova' ? 'selected' : '' ?>>Em prova agora</option>
                <option value="concluiu_todas" <?= $filtro === 'concluiu_todas' ? 'selected' : '' ?>>Concluiu todas</option>
                <option value="canceladas" <?= $filtro === 'canceladas' ? 'selected' : '' ?>>Canceladas</option>
                <option value="nao_comecou" <?= $filtro === 'nao_comecou' ? 'selected' : '' ?>>Ainda não começou</option>
            </select>
        </label>
    </form>
    <?php endif; ?>
</div>

<?php if ($tenant_ok && $bloco): ?>
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <?php foreach ($cards as $card): ?>
    <div class="bg-white rounded-xl border shadow-sm p-4 <?= $card['class'] ?>">
        <p class="text-sm text-slate-500"><?= htmlspecialchars($card['label']) ?></p>
        <p class="text-3xl font-bold mt-1 <?= $card['num'] ?>"><?= number_format($card['value'], 0, ',', '.') ?></p>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h4 class="text-base font-semibold text-slate-900"><?= htmlspecialchars((string) ($bloco['titulo'] ?? 'Bloco')) ?></h4>
        <p class="text-sm text-slate-500 mt-1">
            <?= $totalMaterias ?> matéria(s) · <?= count($alunosFiltrados) ?> aluno(s) nesta lista
            <?php if ($filtro !== 'todos'): ?> · filtro ativo<?php endif; ?>
        </p>
    </div>
    <?php if ($alunosFiltrados === []): ?>
    <p class="px-6 py-10 text-sm text-slate-500 text-center">Nenhum aluno neste recorte.</p>
    <?php else: ?>
    <div id="provas-ao-vivo-tabela" class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="text-left font-medium px-4 py-3 whitespace-nowrap sticky left-0 bg-slate-50 z-10">Aluno</th>
                    <th class="text-left font-medium px-4 py-3 whitespace-nowrap">Turma</th>
                    <th class="text-left font-medium px-4 py-3 whitespace-nowrap">Progresso</th>
                    <?php foreach ($materias as $mat): ?>
                    <th class="text-left font-medium px-4 py-3 whitespace-nowrap"><?= htmlspecialchars((string) ($mat['materia_nome'] ?: $mat['titulo'] ?? 'Matéria')) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($alunosFiltrados as $aluno):
                    $ok = (int) ($aluno['materias_ok'] ?? 0);
                    $bgLinha = !empty($aluno['em_prova']) ? 'bg-cyan-50' : (!empty($aluno['tem_cancelada']) ? 'bg-red-50' : 'bg-white');
                ?>
                <tr class="<?= $bgLinha ?>">
                    <td class="px-4 py-3 whitespace-nowrap sticky left-0 z-10 <?= $bgLinha ?>">
                        <span class="font-medium text-slate-900"><?= htmlspecialchars((string) $aluno['aluno_nome']) ?></span>
                        <?php if (!empty($aluno['aluno_ra'])): ?>
                        <span class="block text-xs text-slate-400">RA <?= htmlspecialchars((string) $aluno['aluno_ra']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600"><?= htmlspecialchars((string) ($aluno['turma_nome'] ?: '—')) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-700"><?= $ok ?>/<?= $totalMaterias ?></td>
                    <?php foreach ($materias as $mat):
                        $cel = $aluno['por_materia'][(int) $mat['id']] ?? null;
                        $status = (string) ($cel['status'] ?? '');
                    ?>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <?php if ($status === 'iniciado'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-cyan-50 text-cyan-800 border border-cyan-200">Em prova<?= $fmtHora($cel['iniciado_em'] ?? null) ? ' · ' . $fmtHora($cel['iniciado_em']) : '' ?></span>
                        <?php elseif ($status === 'finalizado'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-800 border border-green-200">Concluiu<?= $fmtHora($cel['finalizado_em'] ?? null) ? ' · ' . $fmtHora($cel['finalizado_em']) : '' ?></span>
                        <?php elseif ($status === 'cancelada'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-800 border border-red-200">Cancelada</span>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>

<?php if (!$ehFragmento): ?>
<script>
(function() {
    if (window.__provasAoVivoPoller) {
        return;
    }
    window.__provasAoVivoPoller = true;

    function atualizarPainel() {
        if (document.hidden) {
            return;
        }
        var atual = document.getElementById('provas-ao-vivo-painel');
        if (!atual) {
            return;
        }
        var scrollY = window.scrollY;
        var tabela = document.getElementById('provas-ao-vivo-tabela');
        var scrollX = tabela ? tabela.scrollLeft : 0;
        fetch(window.location.pathname + window.location.search, {
            headers: { 'X-Requested-With': 'fragment' },
            cache: 'no-store',
            credentials: 'same-origin'
        })
            .then(function(res) {
                if (!res.ok) {
                    throw new Error('refresh failed');
                }
                return res.text();
            })
            .then(function(html) {
                var wrap = document.getElementById('provas-ao-vivo-painel');
                if (!wrap) {
                    return;
                }
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                var novo = tmp.querySelector('#provas-ao-vivo-painel');
                if (!novo) {
                    return;
                }
                wrap.replaceWith(novo);
                window.scrollTo(0, scrollY);
                var tabelaNova = document.getElementById('provas-ao-vivo-tabela');
                if (tabelaNova) {
                    tabelaNova.scrollLeft = scrollX;
                }
            })
            .catch(function() {});
    }

    setInterval(atualizarPainel, 20000);
})();
</script>
<?php endif; ?>
