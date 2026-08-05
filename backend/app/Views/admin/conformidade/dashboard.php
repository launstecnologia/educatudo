<?php
$painel = $painel ?? [];
$escola = $painel['escola'] ?? [];
$indicadores = $painel['indicadores'] ?? [];
$geral = $painel['conformidade_geral'] ?? null;

$cores = [
    'verde' => ['card' => 'border-green-200', 'text' => 'text-green-600', 'bar' => 'bg-green-500', 'badge' => 'bg-green-100 text-green-800'],
    'amarelo' => ['card' => 'border-amber-200', 'text' => 'text-amber-600', 'bar' => 'bg-amber-500', 'badge' => 'bg-amber-100 text-amber-800'],
    'vermelho' => ['card' => 'border-red-200', 'text' => 'text-red-600', 'bar' => 'bg-red-500', 'badge' => 'bg-red-100 text-red-800'],
    'indisponivel' => ['card' => 'border-gray-200', 'text' => 'text-gray-400', 'bar' => 'bg-gray-300', 'badge' => 'bg-gray-100 text-gray-500'],
];
$corGeral = $painel['conformidade_cor'] ?? 'indisponivel';
$gc = $cores[$corGeral] ?? $cores['indisponivel'];

ob_start();
?>
<a href="<?= URL ?>/admin/conformidade/pendencias"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-triangle-exclamation mr-2 text-amber-500"></i> Pendências
</a>
<a href="<?= URL ?>/admin/conformidade/auditoria"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-clipboard-check mr-2 text-gray-500"></i> Modo Auditoria
</a>
<a href="<?= URL ?>/admin/conformidade/ia"
   class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors shadow-sm">
    <i class="fa-solid fa-robot mr-2"></i> IA Auditora
</a>
<?php
$page_header_actions = ob_get_clean();
$page_header_title = 'Painel de Conformidade';
$page_header_subtitle = 'Indicadores consolidados de conformidade pedagógica, BNCC, Censo e documentação em tempo real.';
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 lg:col-span-1 flex flex-col items-center justify-center text-center">
        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Conformidade Geral</p>
        <p class="text-5xl font-bold <?= $gc['text'] ?>"><?= $geral !== null ? number_format((float) $geral, 1, ',', '') . '%' : '—' ?></p>
        <div class="w-full mt-4 h-2 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full <?= $gc['bar'] ?>" style="width: <?= $geral !== null ? (float) $geral : 0 ?>%"></div>
        </div>
        <p class="mt-3 text-xs text-gray-500">Média dos indicadores disponíveis. Verde &gt; 95%, amarelo 80–95%, vermelho &lt; 80%.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 lg:col-span-2">
        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">Dados da escola — <?= (int) ($escola['ano_letivo'] ?? date('Y')) ?></p>
        <div class="grid grid-cols-3 gap-4">
            <div class="text-center">
                <p class="text-3xl font-bold text-gray-900"><?= (int) ($escola['turmas'] ?? 0) ?></p>
                <p class="text-sm text-gray-500">Turmas</p>
            </div>
            <div class="text-center">
                <p class="text-3xl font-bold text-gray-900"><?= (int) ($escola['alunos'] ?? 0) ?></p>
                <p class="text-sm text-gray-500">Alunos ativos</p>
            </div>
            <div class="text-center">
                <p class="text-3xl font-bold text-gray-900"><?= (int) ($escola['professores'] ?? 0) ?></p>
                <p class="text-sm text-gray-500">Professores</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($indicadores as $ind):
        $cor = $cores[$ind['cor'] ?? 'indisponivel'] ?? $cores['indisponivel'];
        $pct = $ind['percentual'];
        $link = $ind['link'] ?? null;
        $tag = $link ? 'a' : 'div';
        $href = $link ? ' href="' . URL . htmlspecialchars($link) . '"' : '';
    ?>
        <<?= $tag ?><?= $href ?> class="block bg-white rounded-xl shadow-sm border <?= $cor['card'] ?> p-5 <?= $link ? 'hover:shadow-md transition-shadow' : '' ?>">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-50 text-gray-500">
                        <i class="fa-solid <?= htmlspecialchars($ind['icone'] ?? 'fa-circle') ?>"></i>
                    </span>
                    <span class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($ind['label'] ?? '') ?></span>
                </div>
                <span class="text-2xl font-bold <?= $cor['text'] ?>"><?= $pct !== null ? number_format((float) $pct, 1, ',', '') . '%' : '—' ?></span>
            </div>
            <div class="w-full h-2 rounded-full bg-gray-100 overflow-hidden mb-3">
                <div class="h-full <?= $cor['bar'] ?>" style="width: <?= $pct !== null ? (float) $pct : 0 ?>%"></div>
            </div>
            <p class="text-xs text-gray-500"><?= htmlspecialchars($ind['detalhe'] ?? '') ?></p>
        </<?= $tag ?>>
    <?php endforeach; ?>
</div>
