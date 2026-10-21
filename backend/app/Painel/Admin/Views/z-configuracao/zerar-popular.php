<?php
$csrf_token = (string) ($csrf_token ?? '');
$inspecao = is_array($inspecao ?? null) ? $inspecao : ['ok' => false, 'erro' => 'Sem inspeção.'];
$flash = $flash ?? ['message' => null, 'type' => 'info'];
?>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg border <?= (($flash['type'] ?? '') === 'error') ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <a href="<?= URL ?>/admin/z-configuracao" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Voltar para Z-Configuração
    </a>
</div>

<div class="bg-white rounded-xl border border-red-200 shadow-sm p-6 w-full">
    <h2 class="text-xl font-bold text-red-800 mb-2">Zerar e popular esta escola</h2>
    <p class="text-sm text-slate-700 mb-4">
        Esta tela só existe nesta escola. Esvazia os cadastros e carrega a base de teste do Ensino Médio 2025.
        A senha dos cadastros de teste é <code>Teste@123</code>.
    </p>

    <?php if (empty($inspecao['ok'])): ?>
        <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            <?= htmlspecialchars((string) ($inspecao['erro'] ?? 'Não foi possível inspecionar o banco.')) ?>
        </div>
    <?php else: ?>
        <form method="post" action="<?= URL ?>/admin/z-configuracao/zerar-popular" class="mb-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" class="px-6 py-3 bg-red-700 text-white rounded-lg hover:bg-red-800 text-base font-semibold">
                Zerar e popular
            </button>
        </form>
        <p class="text-sm text-slate-600">
            Ficam <?= (int) ($inspecao['usuarios'] ?? 0) ?> usuários.
            Serão esvaziadas <?= count($inspecao['tabelas'] ?? []) ?> tabelas.
            Permanecem a tabela usuarios, os perfis, o histórico de migrations, o catálogo de componentes e as categorias de ocorrência.
        </p>
    <?php endif; ?>
</div>
