<?php
use App\Painel\Master\Services\LimparDadosEscolaEducaService;

$escola = $escola ?? [];
$escola_id = (int) ($escola_id ?? 0);
$csrf_token = (string) ($csrf_token ?? '');
$inspecao = is_array($inspecao ?? null) ? $inspecao : ['ok' => false, 'erro' => 'Sem inspeção.'];
$banco = LimparDadosEscolaEducaService::BANCO_PERMITIDO;
$frase = LimparDadosEscolaEducaService::FRASE_CONFIRMACAO;
$flash = $flash ?? ['message' => null, 'type' => 'info'];
?>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg border <?= (($flash['type'] ?? '') === 'error') ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <a href="<?= URL ?>/master/escolas/<?= $escola_id ?>/banco" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Voltar ao banco da escola
    </a>
</div>

<div class="bg-white rounded-xl border border-red-200 shadow-sm p-6 max-w-3xl">
    <h2 class="text-xl font-bold text-red-800 mb-2">Limpar dados da escola</h2>
    <p class="text-sm text-slate-700 mb-4">
        Esta ação esvazia o banco <code class="bg-red-50 px-1 rounded"><?= htmlspecialchars($banco) ?></code>
        da escola <strong><?= htmlspecialchars((string) ($escola['nome'] ?? '')) ?></strong>.
        Ela recusa qualquer outro banco, mesmo que a tela seja aberta por engano.
    </p>

    <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-900 mb-4 space-y-2">
        <p class="font-semibold">O que é apagado</p>
        <p>Alunos, professores, pais, monitores, turmas, notas, provas, jornadas e os demais cadastros. Arquivos já enviados no disco não entram nesta limpeza.</p>
        <p class="font-semibold">O que permanece</p>
        <p>A tabela <code>usuarios</code> inteira, os perfis de permissão e o histórico de migrations. Alunos, professores, pais e monitores ficam em outras tabelas e são apagados.</p>
    </div>

    <?php if (empty($inspecao['ok'])): ?>
        <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            <?= htmlspecialchars((string) ($inspecao['erro'] ?? 'Não foi possível inspecionar o banco.')) ?>
        </div>
    <?php else: ?>
        <dl class="grid grid-cols-2 gap-3 mb-6 text-sm">
            <div class="rounded-lg bg-slate-50 px-4 py-3">
                <dt class="text-xs uppercase text-slate-500">Registros em usuarios que ficam</dt>
                <dd class="text-2xl font-bold text-slate-900"><?= (int) ($inspecao['usuarios'] ?? 0) ?></dd>
            </div>
            <div class="rounded-lg bg-slate-50 px-4 py-3">
                <dt class="text-xs uppercase text-slate-500">Tabelas que serão esvaziadas</dt>
                <dd class="text-2xl font-bold text-slate-900"><?= count($inspecao['tabelas'] ?? []) ?></dd>
            </div>
        </dl>

        <form method="post" action="<?= URL ?>/master/escolas/<?= $escola_id ?>/limpar-dados" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div>
                <label for="confirmacao_banco" class="block text-sm font-medium text-slate-700 mb-1">
                    Digite o nome do banco para confirmar: <span class="font-mono"><?= htmlspecialchars($banco) ?></span>
                </label>
                <input type="text" id="confirmacao_banco" name="confirmacao_banco" autocomplete="off" required
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-red-500 focus:border-red-500"
                       placeholder="<?= htmlspecialchars($banco) ?>">
            </div>

            <div>
                <label for="frase" class="block text-sm font-medium text-slate-700 mb-1">
                    Digite a frase <span class="font-mono"><?= htmlspecialchars($frase) ?></span>
                </label>
                <input type="text" id="frase" name="frase" autocomplete="off" required
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-red-500 focus:border-red-500"
                       placeholder="<?= htmlspecialchars($frase) ?>">
            </div>

            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" name="confirmar_exclusao" value="1" required class="mt-1">
                <span>Entendo que alunos, professores e os demais cadastros deste banco serão apagados e que isso não pode ser desfeito por esta tela.</span>
            </label>

            <button type="submit" class="px-5 py-2.5 bg-red-700 text-white rounded-lg hover:bg-red-800 text-sm font-medium">
                Esvaziar este banco
            </button>
        </form>
    <?php endif; ?>
</div>
