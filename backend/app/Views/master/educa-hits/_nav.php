<?php
$educahits_section = (string) ($educahits_section ?? 'pedidos');
$educaHitsTabs = [
    'pedidos' => ['label' => 'Pedidos', 'href' => URL . '/master/educa-hits/pedidos', 'icon' => 'fa-list'],
    'musicas' => ['label' => 'Músicas', 'href' => URL . '/master/educa-hits/musicas', 'icon' => 'fa-music'],
    'cadastro' => ['label' => 'Cadastro de músicas', 'href' => URL . '/master/educa-hits/cadastro', 'icon' => 'fa-plus'],
    'configuracao' => ['label' => 'Configuração', 'href' => URL . '/master/educa-hits/configuracao', 'icon' => 'fa-gear'],
];
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900 mb-1">EducaHits</h1>
    <p class="text-slate-500 text-sm">Gerencie pedidos, músicas, cadastro manual e endpoints da integração.</p>
</div>

<div class="mb-6 border-b border-slate-200">
    <nav class="flex flex-wrap gap-1 -mb-px" aria-label="Seções EducaHits">
        <?php foreach ($educaHitsTabs as $tabKey => $tab):
            $isActive = $educahits_section === $tabKey;
        ?>
        <a href="<?= htmlspecialchars($tab['href']) ?>"
           class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors <?= $isActive
               ? 'border-blue-600 text-blue-600'
               : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300' ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>>
            <i class="fa-solid <?= htmlspecialchars($tab['icon']) ?> text-xs opacity-70" aria-hidden="true"></i>
            <?= htmlspecialchars($tab['label']) ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>
