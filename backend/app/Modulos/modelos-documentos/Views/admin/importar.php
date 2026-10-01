<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$catalogo = is_array($catalogo ?? null) ? $catalogo : [];

$page_header_back_url = URL . '/admin/modelos-documentos';
$page_header_title = 'Importar planilha';
$page_header_subtitle = 'Use o modelo oficial da SEED ou envie a planilha da secretaria. Os campos já entram ligados.';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<div class="bg-white rounded-xl shadow-lg p-6 w-full mb-6">
    <h2 class="text-base font-semibold text-gray-900 mb-1">Modelos oficiais SEED</h2>
    <p class="text-sm text-gray-600 mb-4">Reproduz a folha oficial (frente e verso) sem enviar arquivo. Escolha a periodicidade da escola.</p>
    <div class="grid gap-3 sm:grid-cols-2">
        <?php foreach ($catalogo as $chave => $meta): ?>
        <form method="post" action="<?= URL ?>/admin/modelos-documentos/importar/catalogo" class="rounded-lg border border-gray-200 p-4 flex flex-col gap-3">
            <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">
            <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
            <input type="hidden" name="chave" value="<?= $esc((string) $chave) ?>">
            <div>
                <div class="text-sm font-medium text-gray-900"><?= $esc((string) ($meta['nome'] ?? $chave)) ?></div>
                <div class="text-xs text-gray-500 mt-0.5"><?= $esc((string) ($meta['seed'] ?? '')) ?></div>
            </div>
            <label class="flex items-start gap-2 text-xs text-gray-600">
                <input type="checkbox" name="aplicar_emissao" value="1" checked class="mt-0.5 rounded border-gray-300 text-green-600">
                <span>Usar na emissão oficial</span>
            </label>
            <button type="submit" class="btn-primary text-sm self-start">Usar este layout</button>
        </form>
        <?php endforeach; ?>
    </div>
</div>

<form method="post" action="<?= URL ?>/admin/modelos-documentos/importar" enctype="multipart/form-data"
      class="bg-white rounded-xl shadow-lg p-6 w-full">
    <h2 class="text-base font-semibold text-gray-900 mb-1">Ou envie uma planilha</h2>
    <p class="text-sm text-gray-600 mb-4">Se a secretaria mandar outro arquivo, o layout dele substitui o modelo oficial.</p>
    <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

    <div class="mb-6">
        <label for="planilha" class="block text-sm font-medium text-gray-700 mb-2">Planilha do modelo (.xlsx ou .xlsm)</label>
        <input id="planilha" name="planilha" type="file" accept=".xlsx,.xlsm,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        <p class="text-sm text-gray-500 mt-2">A macro da planilha não é executada. Só o layout e os rótulos são lidos.</p>
    </div>

    <label class="flex items-start gap-3 mb-6 text-sm text-gray-700">
        <input type="checkbox" name="aplicar_emissao" value="1" checked class="mt-1 rounded border-gray-300 text-green-600">
        <span>Usar o primeiro modelo reconhecido na emissão oficial (ficha individual ou relatório). As outras variantes da mesma planilha ficam salvas para você escolher no editor.</span>
    </label>

    <div class="flex justify-end">
        <button type="submit" class="btn-primary">Importar</button>
    </div>
</form>
