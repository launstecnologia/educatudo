<?php
$exibir = $somentePortal;
$relatorioPortal = $portalColag['relatorio'];
$boletimAteBimestre = isset($portalColag['boletim_ate_bimestre']) && is_numeric($portalColag['boletim_ate_bimestre'])
    ? (int) $portalColag['boletim_ate_bimestre']
    : null;
if ($exibir === 'notas') {
    $relatorioPortal['demonstrativos'] = array_filter($relatorioPortal['demonstrativos'],
        static fn (array $demo): bool => in_array($demo['bimestre'], $portalColag['notas'], true));
}
if (!$relatorioPortal['demonstrativos'] || ($exibir === 'boletim' && !$portalColag['boletim'])) {
    echo '<p class="text-gray-600">Nenhuma publicação disponível nesta seção.</p>';
    return;
}
$painel = ['bimestres' => $exibir === 'notas' ? $portalColag['notas'] : []];
if ($exibir === 'notas' && count($portalColag['notas']) > 1): ?>
<div class="mb-4 flex flex-wrap gap-2" aria-label="Bimestres publicados">
    <?php foreach ($portalColag['notas'] as $indice => $numero): ?>
    <button type="button" data-periodo-colag="<?= (int) $numero ?>" aria-pressed="<?= $indice === 0 ? 'true' : 'false' ?>"
            class="rounded-full px-4 py-2 text-sm <?= $indice === 0 ? 'bg-blue-900 text-white' : 'bg-gray-100' ?>">
        <?= (int) $numero ?>º bimestre
    </button>
    <?php endforeach; ?>
</div>
<script>
document.querySelectorAll('[data-periodo-colag]').forEach(function (botao) {
    botao.addEventListener('click', function () {
        var periodo = botao.dataset.periodoColag;
        botao.parentElement.querySelectorAll('button').forEach(function (item) {
            var ativo = item === botao;
            item.setAttribute('aria-pressed', String(ativo));
            item.classList.toggle('bg-blue-900', ativo);
            item.classList.toggle('text-white', ativo);
            item.classList.toggle('bg-gray-100', !ativo);
        });
        botao.closest('[data-painel-portal]').querySelectorAll('[data-boletim-painel]').forEach(function (painel) {
            painel.classList.toggle('hidden', painel.dataset.boletimPainel !== periodo);
        });
    });
});
</script>
<?php endif;

$relatorios = [$relatorioPortal];
require __DIR__ . '/admin/_fragmento.php';
