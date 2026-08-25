<?php
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$filho = $filho ?? [];
$campanha = $campanha ?? null;
$processo = $processo ?? null;
$produtos = $produtos ?? [];
$no_prazo = !empty($no_prazo);
$corRaca = [
    '' => 'Não informado',
    'branca' => 'Branca',
    'preta' => 'Preta',
    'parda' => 'Parda',
    'amarela' => 'Amarela',
    'indigena' => 'Indígena',
    'nao_declarada' => 'Não declarada',
];
?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-2xl font-bold text-gray-900">Rematrícula</h2>
        <p class="text-gray-600 mt-1"><?= $esc($filho['nome'] ?? '') ?><?= !empty($filho['turma_nome']) ? ' — ' . $esc($filho['turma_nome']) : '' ?></p>
    </div>
    <div class="p-6 space-y-6">
        <?php if (!$campanha || !$processo): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Não há campanha de rematrícula aberta para este aluno. Procure a secretaria da escola.
        </div>
        <?php elseif (!$no_prazo): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            A campanha “<?= $esc($campanha['nome'] ?? '') ?>” está fora do prazo.
            <?= !empty($campanha['inicio']) ? 'Período: ' . date('d/m/Y', strtotime((string) $campanha['inicio'])) . ' a ' . date('d/m/Y', strtotime((string) $campanha['fim'])) . '.' : '' ?>
        </div>
        <?php elseif (($processo['status'] ?? '') === 'lista_espera'): ?>
        <div class="rounded-xl border border-purple-200 bg-purple-50 p-4 text-sm text-purple-900">
            A turma está lotada. <?= $esc($filho['nome'] ?? 'Seu filho') ?> está na lista de espera
            <?= !empty($processo['fila_posicao']) ? '(posição ' . (int) $processo['fila_posicao'] . ')' : '' ?>.
            A secretaria avisará quando houver vaga.
        </div>
        <?php else: ?>
        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
            <p class="font-semibold"><?= $esc($campanha['nome'] ?? 'Rematrícula') ?></p>
            <p class="mt-1">Confirme os dados e avance para o contrato até <?= date('d/m/Y', strtotime((string) $campanha['fim'])) ?>.</p>
        </div>

        <?php if (!empty($produtos)): ?>
        <div>
            <h3 class="font-semibold text-gray-800 mb-2">Valores previstos</h3>
            <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg">
                <?php foreach ($produtos as $prod): ?>
                <li class="flex justify-between px-4 py-2.5 text-sm">
                    <span class="text-gray-700"><?= $esc($prod['descricao'] ?? 'Item') ?></span>
                    <span class="font-medium text-gray-900">R$ <?= number_format((float) ($prod['valor_base'] ?? 0), 2, ',', '.') ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= URL ?>/pais/filhos/<?= (int) $filho['id'] ?>/rematricula" class="space-y-4">
            <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
            <h3 class="font-semibold text-gray-800">Dados do Censo Escolar</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome da mãe <?= !empty($campanha['exige_censo']) ? '<span class="text-red-500">*</span>' : '' ?></label>
                    <input type="text" name="aluno_nome_mae" value="<?= $esc($processo['aluno_nome_mae'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm"
                           <?= !empty($campanha['exige_censo']) ? 'required' : '' ?>>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome do pai</label>
                    <input type="text" name="aluno_nome_pai" value="<?= $esc($processo['aluno_nome_pai'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cor / raça</label>
                    <select name="aluno_cor_raca" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <?php foreach ($corRaca as $k => $label): ?>
                        <option value="<?= $esc($k) ?>" <?= ($processo['aluno_cor_raca'] ?? '') === $k ? 'selected' : '' ?>><?= $esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nacionalidade</label>
                    <input type="text" name="aluno_nacionalidade" value="<?= $esc($processo['aluno_nacionalidade'] ?? 'Brasileira') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código INEP (se houver)</label>
                    <input type="text" name="aluno_codigo_inep" value="<?= $esc($processo['aluno_codigo_inep'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm" maxlength="20">
                </div>
            </div>
            <button type="submit" class="btn-primary inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold">
                Confirmar e ir para o contrato
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
