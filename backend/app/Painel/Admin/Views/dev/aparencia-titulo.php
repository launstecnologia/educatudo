<?php $aba_aparencia = 'titulo'; include __DIR__ . '/_aparencia_abas.php'; ?>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form id="layout-form">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2" for="system-title">Título do sistema</label>
                <input type="text" id="system-title" name="config[system_title]"
                       value="<?= htmlspecialchars($config_layout['system_title'] ?? 'EducaTudo') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="EducaTudo">
                <p class="text-sm text-gray-500 mt-1">Nome que aparece no sistema.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2" for="system-subtitle">Subtítulo do sistema</label>
                <input type="text" id="system-subtitle" name="config[system_subtitle]"
                       value="<?= htmlspecialchars($config_layout['system_subtitle'] ?? 'Sistema Educacional') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Sistema Educacional">
                <p class="text-sm text-gray-500 mt-1">Subtítulo das páginas de login.</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2" for="menu-colag-nome">Nome do grupo no menu do aluno</label>
                <input type="text" id="menu-colag-nome" name="config[menu_colag_nome]"
                       value="<?= htmlspecialchars($config_layout['menu_colag_nome'] ?? 'Colag') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Colag">
                <p class="text-sm text-gray-500 mt-1">Nome do grupo na navbar do aluno. Padrão: Colag.</p>
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">Salvar</button>
        </div>
    </form>
</div>
<?php include __DIR__ . '/_salvar_layout_js.php'; ?>
