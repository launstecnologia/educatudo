<?php
/**
 * Cabeçalho e abas da área Boletim (módulo eventos avaliativos).
 * $boletim_aba: modelos | resultados
 */
$abaBoletim = (string) ($boletim_aba ?? 'modelos');
$abasBoletim = [
    'modelos' => ['Modelos', URL . '/admin/eventos-avaliativos/boletim'],
    'resultados' => ['Resultados e versões', URL . '/admin/boletim-configuracao/gerados?exibir_em=boletim'],
];

$page_header_title = 'Boletim';
$page_header_subtitle = 'Cadastre o boletim, monte o quadro e acompanhe os resultados gerados por aluno.';
$page_header_icon = 'fa-solid fa-file-circle-check';
$trilhaItens = \App\Helpers\TrilhaAdmin::montar(
    '/admin/eventos-avaliativos/boletim',
    'Boletim',
    [
        ['rotulo' => 'Acadêmico', 'url' => URL . '/admin/academico'],
        ['rotulo' => 'Boletim'],
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
<nav aria-label="Seções do boletim" class="mb-6 border-b border-gray-200 overflow-x-auto">
    <div class="flex min-w-max gap-1">
        <?php foreach ($abasBoletim as $chaveAba => [$rotuloAba, $urlAba]): ?>
        <?php $abaAtiva = $abaBoletim === $chaveAba; ?>
        <a href="<?= htmlspecialchars($urlAba, ENT_QUOTES, 'UTF-8') ?>"
           class="inline-flex items-center px-4 py-2.5 text-sm whitespace-nowrap border-b-2 -mb-px focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2 <?= $abaAtiva ? 'text-gray-900 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300' ?>"
           <?= $abaAtiva ? 'style="border-bottom-color:#111827" aria-current="page"' : '' ?>>
            <?= htmlspecialchars($rotuloAba, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <?php endforeach; ?>
    </div>
</nav>
