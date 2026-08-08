<?php
/**
 * Hub UI Modelos — design system admin (demo visual).
 * Código canônico dos componentes: Views/admin/_partials/ui/
 */
$demos = [
    [
        'titulo' => 'Botões',
        'descricao' => 'Complementares outline + CTA primary. Padrão de cabeçalho canônico.',
        'url' => URL . '/admin/configuracao/ui-modelos/botoes',
        'icone' => 'fa-solid fa-hand-pointer',
    ],
    [
        'titulo' => 'Tabela',
        'descricao' => 'Listagem com avatar, badge, dropdown de ações e estado vazio.',
        'url' => URL . '/admin/configuracao/ui-modelos/tabela',
        'icone' => 'fa-solid fa-table',
    ],
    [
        'titulo' => 'Formulário',
        'descricao' => 'Página própria em largura total: seções, campos e ações.',
        'url' => URL . '/admin/configuracao/ui-modelos/formulario',
        'icone' => 'fa-solid fa-wpforms',
    ],
    [
        'titulo' => 'Offcanvas',
        'descricao' => 'Drawer lateral para cadastro rápido e filtros.',
        'url' => URL . '/admin/configuracao/ui-modelos/offcanvas',
        'icone' => 'fa-solid fa-table-columns',
    ],
    [
        'titulo' => 'Badges',
        'descricao' => 'Pills de status (ativo, inativo, pendente, info…).',
        'url' => URL . '/admin/configuracao/ui-modelos/badges',
        'icone' => 'fa-solid fa-tags',
    ],
    [
        'titulo' => 'Wizard',
        'descricao' => 'Fluxo em várias etapas dentro de página própria: nav de passos + avançar/voltar + revisão.',
        'url' => URL . '/admin/configuracao/ui-modelos/wizard',
        'icone' => 'fa-solid fa-shoe-prints',
    ],
];

$page_header_title = 'UI Modelos';
$page_header_subtitle = 'Componentes do design system admin — use estas páginas como referência visual. Partials em _partials/ui/.';
$page_header_actions = '';
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">
    <i class="fa-solid fa-circle-info mr-2"></i>
    Estas páginas são só para visualização. Telas de produção ainda não foram migradas — isso será feito por etapa.
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($demos as $demo): ?>
    <a href="<?= htmlspecialchars($demo['url']) ?>"
       class="block bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:border-gray-300 hover:shadow-md transition-all">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                <i class="<?= htmlspecialchars($demo['icone']) ?> text-slate-600"></i>
            </div>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-gray-900 mb-1"><?= htmlspecialchars($demo['titulo']) ?></h3>
                <p class="text-sm text-gray-600"><?= htmlspecialchars($demo['descricao']) ?></p>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
