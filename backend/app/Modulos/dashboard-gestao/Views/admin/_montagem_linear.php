<?php
$itensMontagem = \App\Dominio\Escola\Services\MontagemDashboardService::ler('admin');
foreach ($itensMontagem as $itemMontagem):
    if (empty($itemMontagem['visivel'])) {
        continue;
    }
    $chaveBloco = (string) ($itemMontagem['chave'] ?? '');
    $blocoLivre = in_array($chaveBloco, ['avisos', 'aulas_online'], true);
    if (!$blocoLivre && !in_array($chaveBloco, $widgets, true)) {
        continue;
    }
    if ($chaveBloco === 'avisos' && $alunosOnline < 1 && (int) $alertasNovos < 1) {
        continue;
    }
    if ($chaveBloco === 'aulas_online' && $aulasOnline === []) {
        continue;
    }
    $orderStyle = 'order:' . (int) \App\Dominio\Escola\Services\MontagemDashboardService::posicao('admin', $chaveBloco);
?>
<div style="<?= htmlspecialchars($orderStyle) ?>">
<?php
    switch ($chaveBloco) {
        case 'avisos':
            include __DIR__ . '/_bloco_avisos.php';
            break;
        case 'aulas_online':
            include __DIR__ . '/_bloco_aulas.php';
            break;
        case 'kpis':
            echo '<div data-widget="kpis" class="mb-4">';
            include __DIR__ . '/_skeleton_cards.php';
            echo '</div>';
            break;
        case 'pendencias':
            echo '<div class="mb-4"><h3 class="text-sm font-semibold text-gray-900 mb-1">Para fazer</h3>';
            echo '<p class="text-xs text-gray-500 mb-3">Diários e ocorrências: últimos 7 dias. Notas: período selecionado.</p>';
            echo '<div data-widget="pendencias">';
            include __DIR__ . '/_skeleton_cards.php';
            echo '</div></div>';
            break;
        case 'desempenho':
            echo '<div class="space-y-4"><div id="dash-slot-disciplinas" class="' . $card . ' min-h-[320px]">';
            include __DIR__ . '/_skeleton_bloco.php';
            echo '</div><div class="grid grid-cols-1 lg:grid-cols-2 gap-4"><div id="dash-slot-distribuicao" class="' . $card . ' min-h-[280px]">';
            include __DIR__ . '/_skeleton_bloco.php';
            echo '</div><div id="dash-slot-situacao" class="' . $card . ' self-start">';
            include __DIR__ . '/_skeleton_bloco.php';
            echo '</div></div><div data-widget="desempenho" class="hidden" aria-hidden="true"></div></div>';
            break;
        default:
            echo '<div data-widget="' . htmlspecialchars($chaveBloco) . '" class="' . $card . ' min-h-[180px]">';
            include __DIR__ . '/_skeleton_bloco.php';
            echo '</div>';
            break;
    }
?>
</div>
<?php endforeach; ?>
