<?php
/**
 * Cabeçalho e abas da área Boletins.
 * $boletins_aba: modelos | periodos | resultados | relatorios (relatorios só no modelo antigo)
 */
$modeloEvento = class_exists(\App\Core\LayoutHelper::class) && \App\Core\LayoutHelper::isModuleEnabled('eventos_avaliativos');
$abaBoletins = (string) ($boletins_aba ?? 'modelos');
$abasBoletins = [
    'modelos' => ['Modelos', URL . '/admin/boletins'],
    'periodos' => ['Períodos e fórmulas', URL . '/admin/boletim'],
    'resultados' => ['Resultados e versões', URL . '/admin/boletim-configuracao/gerados'],
];
if (!$modeloEvento) {
    $abasBoletins['relatorios'] = ['Relatórios', URL . '/admin/reports/boletim-coordenacao?fonte=vida_escolar'];
}
?>
<div class="mb-6">
    <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= $modeloEvento ? 'Demonstrativo de notas' : 'Boletins' ?></h1>
            <p class="text-sm text-gray-600 mt-1"><?= $modeloEvento ? 'Monte o quadro de notas: períodos, colunas e fórmulas. O boletim oficial tem o layout separado.' : 'Configure os períodos, acompanhe os resultados e emita relatórios.' ?></p>
        </div>
        <a href="<?= URL ?>/admin/academico"
           class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left mr-2 text-gray-500" aria-hidden="true"></i>
            Voltar
        </a>
    </div>
    <nav aria-label="Seções de boletins" class="border-b border-gray-200 overflow-x-auto">
        <div class="flex min-w-max gap-1">
            <?php foreach ($abasBoletins as $chaveAba => [$rotuloAba, $urlAba]): ?>
            <?php $abaAtiva = $abaBoletins === $chaveAba; ?>
            <a href="<?= htmlspecialchars($urlAba, ENT_QUOTES, 'UTF-8') ?>"
               class="inline-flex items-center px-4 py-2.5 text-sm whitespace-nowrap border-b-2 -mb-px focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2 <?= $abaAtiva ? 'text-gray-900 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300' ?>"
               <?= $abaAtiva ? 'style="border-bottom-color:#111827" aria-current="page"' : '' ?>>
                <?= htmlspecialchars($rotuloAba, ENT_QUOTES, 'UTF-8') ?>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>
</div>
