<?php
$csrf_token = (string) ($csrf_token ?? '');
$periodosHomologados = (int) ($periodos_homologados ?? 0);
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

<div class="bg-white rounded-xl border border-amber-200 shadow-sm p-6 w-full">
    <h2 class="text-xl font-bold text-amber-900 mb-2">Reiniciar homologações</h2>
    <p class="text-sm text-slate-700 mb-4">
        Esta tela só existe na escola <strong>colegio-educa</strong>.
        Reabre os períodos que estão homologados para você homologar de novo no Fechamento.
        O resultado anterior fica no livro de registros.
    </p>
    <p class="text-sm text-slate-700 mb-4">
        Períodos homologados agora: <strong><?= $periodosHomologados ?></strong>.
    </p>
    <form method="post" action="<?= URL ?>/admin/z-configuracao/reiniciar-homologacoes" class="mb-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <label for="senha" class="block text-sm font-medium text-slate-700 mb-1">Sua senha</label>
        <input type="password" id="senha" name="senha" autocomplete="current-password" required
               class="w-full max-w-md px-3 py-2 mb-4 border border-slate-300 rounded-lg text-sm"
               placeholder="Digite sua senha para confirmar">
        <button type="submit" class="inline-flex items-center px-6 py-3 bg-amber-700 text-white rounded-lg text-base font-semibold" style="background-color:#b45309;color:#fff;">
            Reiniciar homologações
        </button>
    </form>
    <p class="text-sm text-slate-600">
        Depois abra o <a href="<?= URL ?>/admin/fechamento" class="text-blue-600 hover:underline">Fechamento</a>,
        homologue a turma e gere os documentos na impressão em lote.
    </p>
</div>
