<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$faixaColors = [
    'verde'    => ['bg' => 'bg-green-100',  'text' => 'text-green-700',  'bar' => 'bg-green-500',  'icon' => '🟢'],
    'amarelo'  => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'bar' => 'bg-yellow-500', 'icon' => '🟡'],
    'vermelho' => ['bg' => 'bg-red-100',    'text' => 'text-red-700',    'bar' => 'bg-red-500',    'icon' => '🔴'],
];

$page_header_title    = 'Score de Rematrícula';
$page_header_subtitle = 'Propensão por aluno para o ciclo ' . (int)$ciclo . '.';
ob_start(); ?>
<form method="POST" action="<?= URL ?>/admin/enrollment/score/recalcular" class="inline">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
    <input type="hidden" name="ciclo" value="<?= (int)$ciclo ?>">
    <button type="submit" class="btn-secondary text-sm" onclick="return confirm('Recalcular scores para todos os alunos ativos?')">
        <i class="fa-solid fa-rotate mr-1.5"></i> Recalcular
    </button>
</form>
<a href="<?= URL ?>/admin/enrollment" class="btn-secondary text-sm">← Matrículas</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<!-- Seletor de ciclo -->
<form method="GET" class="flex items-center gap-3 mb-6">
    <label class="text-sm text-gray-600">Ciclo (ano-alvo):</label>
    <input type="number" name="ciclo" value="<?= (int)$ciclo ?>" min="2024" max="2030"
           class="border border-gray-300 rounded-xl px-3 py-2 text-sm w-28">
    <button type="submit" class="btn-primary text-sm px-4 py-2">Ver</button>
</form>

<!-- Resumo por faixa -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <?php foreach (['verde','amarelo','vermelho'] as $f):
        $c = $faixaColors[$f];
        $n = (int)($resumo[$f] ?? 0);
    ?>
    <a href="?ciclo=<?= (int)$ciclo ?>&faixa=<?= $f ?>"
       class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 text-center hover:shadow-md transition <?= ($faixa_filtro ?? '') === $f ? 'ring-2 ring-blue-500' : '' ?>">
        <div class="text-3xl mb-1"><?= $c['icon'] ?></div>
        <div class="text-2xl font-bold text-gray-800"><?= $n ?></div>
        <div class="text-sm text-gray-500 capitalize"><?= $f ?></div>
        <?php $pct = array_sum($resumo) > 0 ? round($n / array_sum($resumo) * 100) : 0; ?>
        <div class="mt-2 h-1.5 bg-gray-100 rounded-full">
            <div class="h-1.5 rounded-full <?= $c['bar'] ?>" style="width:<?= $pct ?>%"></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<?php if (!empty($faixa_filtro)): ?>
<div class="mb-4 flex items-center gap-2 text-sm">
    <span class="text-gray-500">Filtrando por: <strong class="text-gray-800 capitalize"><?= $esc($faixa_filtro) ?></strong></span>
    <a href="?ciclo=<?= (int)$ciclo ?>" class="text-blue-600 hover:underline text-xs">limpar</a>
</div>
<?php endif; ?>

<?php if (empty($alunos)): ?>
<div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-gray-400">
    <i class="fa-solid fa-chart-bar text-3xl mb-3 block"></i>
    <?php if (empty($resumo) || array_sum($resumo) === 0): ?>
    Nenhum score calculado ainda. Clique em "Recalcular" para gerar.
    <?php else: ?>
    Nenhum aluno na faixa selecionada.
    <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Aluno</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Turma</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Score</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Faixa</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Motivos</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Calculado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($alunos as $a):
                $f  = $a['faixa'] ?? 'verde';
                $c  = $faixaColors[$f] ?? $faixaColors['verde'];
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <a href="<?= URL ?>/admin/students/<?= (int)$a['aluno_id'] ?>"
                       class="font-medium text-blue-600 hover:underline"><?= $esc($a['aluno_nome']) ?></a>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs"><?= $esc($a['turma_nome'] ?? '—') ?> <?= $a['turma_serie'] ? '· ' . $esc($a['turma_serie']) : '' ?></td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="w-20 h-2 bg-gray-100 rounded-full">
                            <div class="h-2 rounded-full <?= $c['bar'] ?>" style="width:<?= (int)$a['score'] ?>%"></div>
                        </div>
                        <span class="font-semibold text-gray-800"><?= (int)$a['score'] ?></span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $c['bg'] . ' ' . $c['text'] ?>">
                        <?= $c['icon'] ?> <?= ucfirst($f) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500"><?= $esc($a['motivos'] ?? '—') ?></td>
                <td class="px-4 py-3 text-xs text-gray-400"><?= $a['calculado_em'] ? date('d/m/Y', strtotime($a['calculado_em'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
