<?php
/**
 * Cabeçalho e abas da área Demonstrativo de notas / Boletins (legado).
 * $boletins_aba: modelos | periodos | resultados | relatorios
 */
$modeloEvento = class_exists(\App\Core\LayoutHelper::class) && \App\Core\LayoutHelper::isModuleEnabled('eventos_avaliativos');
$abaBoletins = (string) ($boletins_aba ?? 'modelos');
if ($modeloEvento) {
    $abasBoletins = [
        'modelos' => ['Modelos', URL . '/admin/boletins'],
    ];
} else {
    $abasBoletins = [
        'modelos' => ['Modelos', URL . '/admin/boletins'],
        'periodos' => ['Períodos e fórmulas', URL . '/admin/boletim'],
        'resultados' => ['Resultados e versões', URL . '/admin/boletim-configuracao/gerados'],
        'relatorios' => ['Relatórios', URL . '/admin/reports/boletim-coordenacao?fonte=vida_escolar'],
    ];
}
$mostrarAbas = count($abasBoletins) > 1;

$page_header_title = $modeloEvento ? 'Demonstrativo de notas' : 'Boletins';
$page_header_subtitle = $modeloEvento
    ? 'Quadro ao vivo do período: monte no início do mês; cada prova lançada aparece sozinha.'
    : 'Configure os períodos, acompanhe os resultados e emita relatórios.';
$page_header_icon = $modeloEvento ? 'fa-solid fa-table-cells' : 'fa-solid fa-file-circle-check';
$trilhaItens = \App\Helpers\TrilhaAdmin::montar(
    '/admin/boletins',
    $page_header_title,
    [
        ['rotulo' => 'Acadêmico', 'url' => URL . '/admin/academico'],
        ['rotulo' => $page_header_title],
    ]
);
ob_start();
?>
<a href="<?= URL ?>/admin/academico"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-arrow-left mr-2 text-gray-500" aria-hidden="true"></i>
    Voltar
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/page_header_list.php';
?>
<?php if ($mostrarAbas): ?>
<nav aria-label="Seções de boletins" class="mb-6 border-b border-gray-200 overflow-x-auto">
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
<?php endif; ?>
