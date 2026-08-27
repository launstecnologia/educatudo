<?php
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$links = is_array($links ?? null) ? $links : [];
$capa = is_array($capa ?? null) ? $capa : [];
?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Dossiê deste aluno</h3>
    <p class="text-sm text-gray-500 mb-4">Gera PDF no papel timbrado da escola. Para mudar cabeçalho, logo e texto, edite os modelos em <a href="<?= URL ?>/admin/modelos-documentos?categoria=oficial" class="text-primary underline">Layout de documentos</a>.</p>
    <ul class="text-sm text-gray-700 space-y-1 mb-6 list-disc pl-5">
        <li>Identidade, filiação e matrícula</li>
        <li>Trajetória (anos internos e de outras escolas)</li>
        <li>Boletim oficial do ano em curso</li>
        <li>Checklist de documentos e anexos recebidos</li>
        <li>Campos para digitação na SED e situação no Educacenso</li>
    </ul>
    <div class="flex flex-wrap gap-2">
        <a href="<?= URL . ($links['dossie'] ?? '#') ?>" download class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Baixar dossiê (PDF)</a>
        <a href="<?= URL . ($links['pacote'] ?? '#') ?>" download class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Baixar pacote de transferência</a>
        <a href="<?= URL . ($links['sed'] ?? '#') ?>" download class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Planilha SED (PDF)</a>
        <a href="<?= URL . ($links['historico'] ?? '#') ?>" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Emitir histórico oficial</a>
        <a href="<?= URL . ($links['ficha_individual'] ?? '#') ?>" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Ficha individual</a>
        <a href="<?= URL . ($links['boletim_oficial'] ?? '#') ?>" target="_blank" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Boletim de fechamento (PDF)</a>
    </div>
</div>
<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Resumo para a secretaria</h3>
    <p class="text-sm text-gray-600">
        Situação <strong><?= $esc($capa['situacao'] ?? '') ?></strong>
        · ficha <?= $esc($capa['status_ficha_label'] ?? '') ?>
        · documentos <?= $esc($capa['docs_txt'] ?? '') ?>
        · SED <?= $esc($capa['sed_txt'] ?? '') ?>
        · <?= $esc($capa['inep_txt'] ?? '') ?>.
    </p>
    <p class="text-sm text-gray-500 mt-3">O dossiê anual da escola (todas as turmas) continua em Resultados Finais e Censo Escolar. Aqui está só este aluno.</p>
</div>
