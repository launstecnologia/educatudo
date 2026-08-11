<?php
/** Abas do catálogo TudiCoins (só tabelas e pacotes). */
$catalogo_tab = $catalogo_tab ?? '';
$tabs = [
    'tabelas' => ['label' => 'Tabelas de preço', 'href' => URL . '/master/creditos-catalogo/tabelas'],
    'pacotes' => ['label' => 'Pacotes', 'href' => URL . '/master/creditos-catalogo/pacotes'],
];
?>
<nav class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-3" aria-label="Catálogo TudiCoins">
    <?php foreach ($tabs as $key => $tab): ?>
        <?php $active = $catalogo_tab === $key; ?>
        <a href="<?= htmlspecialchars($tab['href']) ?>"
           class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $active ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
            <?= htmlspecialchars($tab['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>
