<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
    <?php if ($alunosOnline > 0): ?>
    <a href="<?= URL ?>/admin/alunos-online" class="<?= $card ?> hover:shadow-md transition-shadow">
        <p class="text-xs text-slate-500">Alunos online</p>
        <p id="alunos-online-count" class="mt-1 text-2xl font-bold text-gray-900"><?= $alunosOnline ?></p>
    </a>
    <?php endif; ?>
    <?php if ((int) $alertasNovos > 0): ?>
    <a href="<?= URL ?>/admin/monitoramento/alertas" class="<?= $card ?> hover:shadow-md transition-shadow">
        <p class="text-xs text-slate-500">Alertas sensíveis</p>
        <p class="mt-1 text-2xl font-bold text-rose-600"><?= (int) $alertasNovos ?></p>
    </a>
    <?php endif; ?>
</div>
