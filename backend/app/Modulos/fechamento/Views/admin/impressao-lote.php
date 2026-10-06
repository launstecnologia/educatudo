<?php
$resumo = is_array($impressao_lote ?? null) ? $impressao_lote : [];
$pode = !empty($resumo['pode']);
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);
$turmaId = (int) ($turma_id ?? 0);
$documentos = is_array($documentos ?? null) ? $documentos : [];
$qs = http_build_query(array_filter([
    'ano_letivo' => $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => $periodoNumero,
    'turma_id' => $turmaId > 0 ? $turmaId : null,
], static fn ($v) => $v !== null && $v !== ''));
$painelQs = http_build_query([
    'ano_letivo' => $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => $periodoNumero,
    'turma_id' => $turmaId,
]);
$pacote = is_array($pacote ?? null) ? $pacote : [];
$descricoes = [
    'boletim' => 'Um boletim por aluno, na ordem das turmas homologadas. Paisagem.',
    'ficha' => 'Uma ficha individual por aluno. Retrato.',
    'historico' => 'Um histórico por aluno. Entra a versão emitida, assinada, entregue ou o rascunho. Quem ainda não tem histórico fica de fora até você preparar os rascunhos.',
    'resultado' => 'A ata de resultados finais, uma por turma. É o resultado final da turma. Paisagem.',
    'relatorio' => 'O relatório de fechamento, um por turma. Paisagem.',
];
$urlsPacote = [];
foreach ($pacote as $chavePacote) {
    if (!isset($documentos[$chavePacote])) {
        continue;
    }
    $urlsPacote[] = URL . '/admin/fechamento/impressao-lote?' . $qs . '&documento=' . rawurlencode((string) $chavePacote);
}

$page_header_title = 'Impressão em lote';
$page_header_subtitle = 'Boletim, ficha individual, histórico e resultado final da turma, depois da homologação.';
ob_start();
?>
<a href="<?= URL ?>/admin/fechamento?<?= htmlspecialchars($painelQs) ?>" class="text-gray-600 hover:text-gray-900 text-sm">← Fechamento</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if (!$pode): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    <?php if ((int) ($resumo['total_turmas'] ?? 0) === 0): ?>
        Nenhuma turma neste filtro.
    <?php else: ?>
        Homologue todas as turmas deste filtro para imprimir.
        <?= (int) ($resumo['homologadas'] ?? 0) ?> de <?= (int) ($resumo['total_turmas'] ?? 0) ?> já homologadas.
        <?php if (!empty($resumo['pendentes'])): ?>
            Faltam: <?= htmlspecialchars(implode(', ', $resumo['pendentes']), ENT_QUOTES, 'UTF-8') ?>.
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php else: ?>
<p class="text-sm text-gray-600 mb-6">
    <?= (int) ($resumo['total_turmas'] ?? 0) ?> turma(s) homologada(s),
    <?= (int) ($resumo['total_alunos'] ?? 0) ?> aluno(s).
    Cada documento abre numa aba para imprimir. Boletim, histórico, ata e relatório saem em paisagem; a ficha, em retrato.
</p>
<?php if ($urlsPacote !== []): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 flex flex-col gap-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Gerar os quatro de uma vez</h3>
            <p class="text-sm text-gray-500 mt-1">Abre boletim, ficha individual, histórico e resultado final da turma. O navegador pode pedir permissão para as abas.</p>
        </div>
        <button type="button" id="gerar-pacote-fechamento"
                data-urls="<?= htmlspecialchars(json_encode($urlsPacote, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
                class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold shrink-0">
            <i class="fa-solid fa-print mr-2"></i> Gerar tudo
        </button>
    </div>
    <?php if (!empty($pode_preparar_historico)): ?>
    <form method="post" action="<?= URL ?>/admin/fechamento/impressao-lote/preparar-historicos" class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pt-4 border-t border-gray-100">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
        <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
        <?php if ($turmaId > 0): ?>
        <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
        <?php endif; ?>
        <p class="text-sm text-gray-500">O histórico só entra na impressão se o aluno já tiver rascunho ou versão emitida. Este passo cria o rascunho de conclusão de quem ainda não tem.</p>
        <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 shrink-0">
            Preparar históricos
        </button>
    </form>
    <?php endif; ?>
</div>
<script>
(function () {
    var botao = document.getElementById('gerar-pacote-fechamento');
    if (!botao) return;
    botao.addEventListener('click', function () {
        var urls = [];
        try { urls = JSON.parse(botao.getAttribute('data-urls') || '[]'); } catch (e) { urls = []; }
        urls.forEach(function (url) { window.open(url, '_blank'); });
    });
})();
</script>
<?php endif; ?>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($documentos as $chave => $rotulo): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col">
        <h3 class="text-lg font-semibold text-gray-900 mb-2"><?= htmlspecialchars((string) $rotulo) ?></h3>
        <p class="text-sm text-gray-500 mb-6 flex-1"><?= htmlspecialchars($descricoes[$chave] ?? '') ?></p>
        <?php
        $ui_btn_variant = 'primary';
        $ui_btn_label = 'Abrir para imprimir';
        $ui_btn_icon = 'fa-solid fa-print';
        $ui_btn_href = URL . '/admin/fechamento/impressao-lote?' . $qs . '&documento=' . rawurlencode((string) $chave);
        $ui_btn_attrs = 'target="_blank" rel="noopener"';
        include __DIR__ . '/../../../../Views/admin/_partials/ui/btn.php';
        ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
