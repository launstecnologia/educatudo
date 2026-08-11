<?php
$lista = $lista ?? [];
$erro_tabela = $erro_tabela ?? null;
$flash = $flash ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="mb-6 flex flex-wrap justify-between gap-4 items-start">
    <div>
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Tabelas de preço</h2>
        <p class="text-slate-600 text-sm max-w-2xl">
            Defina quanto cada ação de IA custa em TudiCoins. Vincule na escola (TudiCoins) e sincronize.
            Marque uma como <strong>Padrão</strong> para escolas novas já nascerem com essa tabela.
        </p>
    </div>
    <?php if (!$erro_tabela): ?>
    <button type="button" onclick="openTabelaDrawer()"
            class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition-colors">
        <i class="fa-solid fa-plus mr-2"></i> Nova tabela
    </button>
    <?php endif; ?>
</div>

<?php $catalogo_tab = 'tabelas'; include __DIR__ . '/_nav_tabs.php'; ?>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= ($flash['type'] ?? '') === 'error' ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<?php if ($erro_tabela): ?>
<div class="mb-6 px-4 py-3 rounded-lg bg-amber-100 border border-amber-200 text-amber-800"><?= htmlspecialchars($erro_tabela) ?></div>
<?php else: ?>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Padrão</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Atualizado</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($lista as $t): ?>
                <?php
                $tabelaId = (int) ($t['id'] ?? 0);
                $isPadrao = !empty($t['padrao']);
                $isAtivo = !empty($t['ativo']);
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900"><?= htmlspecialchars($t['nome'] ?? '') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <?php if ($isPadrao): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Padrão</span>
                        <?php else: ?>
                            <span class="text-xs text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <?php if ($isAtivo): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Ativo</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><?= htmlspecialchars($t['updated_at'] ?? '') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/master/creditos-catalogo/tabelas/<?= $tabelaId ?>/editar" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-sliders text-gray-400 w-4 text-center"></i> Editar preços
                        </a>
                        <?php if ($isPadrao): ?>
                        <button type="button" onclick="document.getElementById('form-padrao-tabela-<?= $tabelaId ?>').submit();" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-star text-gray-400 w-4 text-center"></i> Remover padrão
                        </button>
                        <?php else: ?>
                        <button type="button" onclick="document.getElementById('form-padrao-tabela-<?= $tabelaId ?>').submit();" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" <?= $isAtivo ? '' : 'disabled title="Ative a tabela antes"' ?>>
                            <i class="fa-solid fa-star text-amber-400 w-4 text-center"></i> Marcar como padrão
                        </button>
                        <?php endif; ?>
                        <button type="button" onclick="document.getElementById('form-toggle-tabela-<?= $tabelaId ?>').submit();" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-power-off text-gray-400 w-4 text-center"></i> <?= $isAtivo ? 'Desativar' : 'Ativar' ?>
                        </button>
                        <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                        <?php $row_actions_dropdown_id = 'row-actions-tabela-' . $tabelaId; ?>
                        <?php include __DIR__ . '/../../admin/_partials/row_actions_dropdown.php'; ?>
                        <form id="form-toggle-tabela-<?= $tabelaId ?>" method="post" action="<?= URL ?>/master/creditos-catalogo/tabelas/<?= $tabelaId ?>/toggle" class="hidden">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        </form>
                        <form id="form-padrao-tabela-<?= $tabelaId ?>" method="post" action="<?= URL ?>/master/creditos-catalogo/tabelas/<?= $tabelaId ?>/padrao" class="hidden">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="padrao" value="<?= $isPadrao ? '0' : '1' ?>">
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lista)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-500 text-sm">
                        Nenhuma tabela de preço.
                        <button type="button" onclick="openTabelaDrawer()" class="ml-1 text-blue-600 hover:underline font-medium">Criar a primeira</button>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="tabelaDrawerBackdrop" class="fixed inset-0 bg-black/40 z-[60] hidden" onclick="closeTabelaDrawer()"></div>
<aside id="tabelaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="tabelaDrawerTitle" class="text-xl font-bold text-gray-900">Nova tabela de preço</h2>
        <button type="button" onclick="closeTabelaDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="tabela-form" method="post" action="<?= URL ?>/master/creditos-catalogo/tabelas/criar" class="flex flex-col flex-1 overflow-hidden">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados</h3>
                <div class="space-y-5">
                    <div>
                        <label for="tabela_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" id="tabela_nome" name="nome" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ex.: Padrão Ensino Médio">
                    </div>
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <input type="checkbox" name="padrao" value="1" class="rounded border-gray-300 mt-0.5">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">Marcar como padrão</span>
                            <span class="block text-xs text-slate-500 mt-0.5">Escolas novas já entram com esta tabela vinculada (só pode haver uma padrão).</span>
                        </span>
                    </label>
                </div>
            </section>
            <p class="text-xs text-slate-500">Após criar, você define o custo de cada ação de IA na tela de edição.</p>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeTabelaDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="px-6 py-2.5 rounded-lg font-semibold bg-blue-600 text-white hover:bg-blue-700 transition-colors shadow-sm">
                Criar e editar preços
            </button>
        </div>
    </form>
</aside>

<script>
function showTabelaDrawer() {
    document.getElementById('tabelaDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('tabelaDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeTabelaDrawer() {
    document.getElementById('tabelaDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('tabelaDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
function openTabelaDrawer() {
    document.getElementById('tabela-form').reset();
    showTabelaDrawer();
    setTimeout(function () {
        document.getElementById('tabela_nome')?.focus();
    }, 200);
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeTabelaDrawer();
});
</script>
<?php endif; ?>
