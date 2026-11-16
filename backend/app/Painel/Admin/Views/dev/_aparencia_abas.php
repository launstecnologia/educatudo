<?php
$abaAparencia = (string) ($aba_aparencia ?? '');
$abasAparencia = [
    ['id' => 'logos', 'label' => 'Logos', 'href' => URL . '/admin/dev/aparencia/logos'],
    ['id' => 'cores', 'label' => 'Cores', 'href' => URL . '/admin/dev/aparencia/cores'],
    ['id' => 'titulo', 'label' => 'Título', 'href' => URL . '/admin/dev/aparencia/titulo'],
    ['id' => 'dash-admin', 'label' => 'Dashboard admin', 'href' => URL . '/admin/dev/aparencia/dashboard/admin'],
    ['id' => 'dash-aluno', 'label' => 'Dashboard aluno', 'href' => URL . '/admin/dev/aparencia/dashboard/aluno'],
    ['id' => 'dash-professor', 'label' => 'Dashboard professor', 'href' => URL . '/admin/dev/aparencia/dashboard/professor'],
    ['id' => 'pwa', 'label' => 'PWA', 'href' => URL . '/admin/dev-settings/pwa'],
    ['id' => 'mobile', 'label' => 'Layout mobile', 'href' => URL . '/admin/dev-settings/layout-mobile'],
    ['id' => 'menu', 'label' => 'Menu do aluno', 'href' => URL . '/admin/dev/aparencia?aba=menu'],
    ['id' => 'links', 'label' => 'Links', 'href' => URL . '/admin/dev/aparencia?aba=links'],
];
?>
<header class="mb-6">
    <a href="<?= URL ?>/admin/dev" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Configurações avançadas</a>
    <h1 class="text-2xl font-bold text-gray-900 tracking-tight mt-2">Aparência e Menu</h1>
</header>
<div class="mb-6 border-b border-gray-200 overflow-x-auto">
    <nav class="flex gap-1 min-w-max" aria-label="Seções de aparência">
        <?php foreach ($abasAparencia as $abaItem): ?>
            <?php $abaAtiva = $abaAparencia === $abaItem['id']; ?>
            <a href="<?= htmlspecialchars($abaItem['href']) ?>"
               class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px whitespace-nowrap <?= $abaAtiva ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' ?>"
               <?= $abaAtiva ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars($abaItem['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
