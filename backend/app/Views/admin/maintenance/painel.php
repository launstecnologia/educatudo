<div class="max-w-3xl mx-auto">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Painel de Manutenção</h2>
        <p class="text-gray-600 mb-6">
            Use este controle para ativar/desativar o modo manutenção global da escola.
        </p>

        <?php if (isset($_GET['ok'])): ?>
            <div class="mb-5 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-emerald-800 text-sm">
                Atualização realizada com sucesso.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['erro']) && $_GET['erro'] === 'token'): ?>
            <div class="mb-5 rounded-lg bg-red-50 border border-red-200 p-3 text-red-800 text-sm">
                Token CSRF inválido. Atualize a página e tente novamente.
            </div>
        <?php endif; ?>

        <div class="rounded-xl border <?= !empty($maintenance_mode) ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50' ?> p-4 mb-6">
            <p class="text-sm font-medium text-gray-700">Status atual</p>
            <p class="text-lg font-bold <?= !empty($maintenance_mode) ? 'text-red-700' : 'text-emerald-700' ?>">
                <?= !empty($maintenance_mode) ? 'Ativo' : 'Inativo' ?>
            </p>
        </div>

        <form method="POST" action="<?= URL ?>/admin/maintenance/toggle" class="flex flex-wrap gap-3">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="enabled" value="<?= !empty($maintenance_mode) ? '0' : '1' ?>">
            <button
                type="submit"
                class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white <?= !empty($maintenance_mode) ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-500 hover:bg-amber-600' ?> transition-colors"
            >
                <?= !empty($maintenance_mode) ? 'Desligar modo manutenção' : 'Ativar modo manutenção' ?>
            </button>
            <a href="<?= URL ?>/admin/dashboard" class="px-5 py-2.5 rounded-lg text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 transition-colors">
                Voltar ao dashboard
            </a>
        </form>
    </div>
</div>
