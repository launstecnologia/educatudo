<?php
$areas = $areas ?? [];
$pendencias = $pendencias ?? [];
$areaAtual = $area ?? '';
$sevClasses = [
    'alta' => 'bg-red-100 text-red-800',
    'media' => 'bg-amber-100 text-amber-800',
    'baixa' => 'bg-blue-100 text-blue-800',
];
$sevLabel = ['alta' => 'Alta', 'media' => 'Média', 'baixa' => 'Baixa'];

$page_header_title = 'Central de Pendências';
$page_header_subtitle = 'Tudo que está em desacordo nos módulos, em uma lista única, para corrigir antes de auditorias.';
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="mb-6 flex flex-wrap gap-2">
    <a href="<?= URL ?>/admin/conformidade/pendencias"
       class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $areaAtual === '' ? 'bg-green-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
        Todas
    </a>
    <?php foreach ($areas as $key => $label): ?>
        <a href="<?= URL ?>/admin/conformidade/pendencias?area=<?= urlencode($key) ?>"
           class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $areaAtual === $key ? 'bg-green-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
            <?= htmlspecialchars($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <p class="text-sm text-gray-600"><span class="font-semibold text-gray-900"><?= count($pendencias) ?></span> pendência(s) encontrada(s)</p>
    </div>
    <?php if (empty($pendencias)): ?>
        <div class="px-6 py-16 text-center text-gray-500">
            <i class="fa-regular fa-circle-check text-4xl text-green-300 mb-4"></i>
            <p>Nenhuma pendência encontrada nesta área.</p>
        </div>
    <?php else: ?>
        <ul class="divide-y divide-gray-200">
            <?php foreach ($pendencias as $p):
                $sev = (string) ($p['severidade'] ?? 'media'); ?>
                <li class="px-6 py-4 flex items-start justify-between gap-4 hover:bg-gray-50 transition-colors">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-medium text-gray-400 uppercase tracking-wider"><?= htmlspecialchars((string) ($p['area_label'] ?? '')) ?></span>
                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= $sevClasses[$sev] ?? 'bg-slate-100 text-slate-700' ?>"><?= htmlspecialchars($sevLabel[$sev] ?? $sev) ?></span>
                        </div>
                        <p class="mt-1 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($p['titulo'] ?? '')) ?></p>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars((string) ($p['descricao'] ?? '')) ?></p>
                    </div>
                    <?php if (!empty($p['link'])): ?>
                        <a href="<?= URL . htmlspecialchars((string) $p['link']) ?>" class="flex-shrink-0 inline-flex items-center px-3 py-1.5 text-sm font-medium text-green-700 hover:text-green-900">
                            Resolver <i class="fa-solid fa-arrow-right ml-1.5 text-xs"></i>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
