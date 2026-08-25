<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$layout = is_array($layout ?? null) ? $layout : [];
$eid = (int) ($edicao['id'] ?? 0);
$page_header_title = 'Configuração da edição';
$page_header_subtitle = 'Data de referência, leiaute e ciclo de vida da coleta.';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';
include __DIR__ . '/_contexto.php';
$nav_atual = 'config';
include __DIR__ . '/_nav.php';
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <form method="POST" class="mb-8">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data de referência</label>
                <input type="date" name="data_referencia" value="<?= $esc($edicao['data_referencia'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Versão do leiaute</label>
                <input value="<?= $esc($layout['versao'] ?? $edicao['versao_layout'] ?? '') ?>" disabled class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50">
            </div>
        </div>
        <p class="text-sm text-gray-500 mb-4">
            Fonte oficial: <?= !empty($layout['fonte_oficial']) ? '<a class="text-indigo-600 underline" href="' . $esc($layout['fonte_oficial']) . '" target="_blank" rel="noopener">portal INEP</a>' : 'não registrada' ?>.
            Oficial: <strong><?= !empty($layout['oficial']) ? 'sim' : 'não' ?></strong>
        </p>
        <button class="btn-primary-custom px-4 py-2 rounded-lg font-semibold">Salvar</button>
    </form>

    <?php if (($edicao['status'] ?? '') === 'fechado'): ?>
    <form method="POST" action="<?= URL ?>/admin/censo/<?= $eid ?>/reabrir" class="border-t border-gray-100 pt-6">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
        <h3 class="text-sm font-semibold text-gray-900 mb-2">Reabrir edição</h3>
        <p class="text-sm text-gray-500 mb-3">Exige justificativa. O histórico e os arquivos anteriores são preservados.</p>
        <textarea name="motivo_reabertura" required rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-3" placeholder="Motivo da reabertura"></textarea>
        <button class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium">Reabrir</button>
    </form>
    <?php endif; ?>
</div>
