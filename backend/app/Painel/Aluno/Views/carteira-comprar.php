<?php
$pacotes = $pacotes ?? [];
$primary_color = $primary_color ?? '#3b82f6';
?>
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="<?= URL ?>/carteira" class="text-sm text-gray-600 hover:underline">&larr; Voltar à carteira</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Comprar créditos</h1>
        <p class="text-gray-600 mt-1">Escolha um pacote e prossiga ao pagamento.</p>
    </div>

    <?php if (empty($pacotes)): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-amber-800">
        Nenhum pacote disponível no momento. Entre em contato com sua escola.
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <?php foreach ($pacotes as $p):
            $valorReais = number_format(((int)($p['valor_centavos'] ?? 0)) / 100, 2, ',', '.');
            $creditos = (int) ($p['creditos'] ?? 0);
            $nome = $p['nome'] ?? "{$creditos} créditos";
        ?>
        <div class="bg-white rounded-xl shadow border border-gray-200 p-6 flex flex-col">
            <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($nome) ?></h3>
            <p class="text-2xl font-bold mt-2 text-emerald-600">R$ <?= $valorReais ?></p>
            <p class="text-sm text-gray-500 mt-1"><?= $creditos ?> créditos</p>
            <form method="post" action="<?= URL ?>/carteira/comprar" class="mt-4">
                <input type="hidden" name="pacote_id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">Comprar</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
