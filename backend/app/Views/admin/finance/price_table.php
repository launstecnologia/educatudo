<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

$cats = ['mensalidade'=>'Mensalidade','matricula'=>'Matrícula','material_didatico'=>'Material Didático','uniforme'=>'Uniforme','taxa'=>'Taxa','outros'=>'Outros'];

$page_header_title = 'Tabela de Preços';
ob_start(); ?>
<button onclick="document.getElementById('modal_add').classList.remove('hidden')" class="btn-primary text-sm">+ Novo Item</button>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<form method="GET" class="flex gap-3 mb-6">
    <select name="ano_letivo_id" class="border border-gray-300 rounded-xl px-3 py-2 text-sm" onchange="this.form.submit()">
        <option value="">Selecione o Ano Letivo</option>
        <?php foreach ($anos_letivos as $al): ?>
        <option value="<?= (int)$al['id'] ?>" <?= $ano_letivo_id == $al['id'] ? 'selected' : '' ?>><?= $esc($al['ano']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500">
            <tr>
                <th class="px-4 py-3 text-left font-medium">Categoria</th>
                <th class="px-4 py-3 text-left font-medium">Descrição</th>
                <th class="px-4 py-3 text-left font-medium">Série</th>
                <th class="px-4 py-3 text-right font-medium">Valor Base</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($items)): ?>
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Nenhum item cadastrado para este ano letivo.</td></tr>
            <?php else: foreach ($items as $item): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs bg-indigo-100 text-indigo-700"><?= $esc($cats[$item['categoria']] ?? $item['categoria']) ?></span>
                </td>
                <td class="px-4 py-3 text-gray-800"><?= $esc($item['descricao']) ?></td>
                <td class="px-4 py-3 text-gray-500"><?= $esc($item['serie_nome'] ?? 'Todas') ?></td>
                <td class="px-4 py-3 text-right font-bold text-gray-900"><?= $brl($item['valor_base']) ?></td>
                <td class="px-4 py-3 text-right">
                    <form method="POST" action="<?= URL ?>/admin/finance/price-table/<?= (int)$item['id'] ?>/delete" class="inline">
                        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                        <button class="text-xs text-red-400 hover:text-red-600" onclick="return confirm('Remover?')">✕</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal adicionar -->
<div id="modal_add" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Novo Item de Preço</h3>
        <form method="POST" action="<?= URL ?>/admin/finance/price-table" class="space-y-4">
            <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
            <input type="hidden" name="ano_letivo_id" value="<?= (int)$ano_letivo_id ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                <select name="categoria" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
                    <?php foreach ($cats as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                <input type="text" name="descricao" required placeholder="Ex: Mensalidade 2026" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Série (opcional)</label>
                <select name="serie_id" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
                    <option value="">Todas as séries</option>
                    <?php foreach ($series as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= $esc($s['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Valor Base (R$)</label>
                <input type="text" name="valor_base" required placeholder="0,00" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm font-mono">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn-primary flex-1 text-sm">Adicionar</button>
                <button type="button" onclick="document.getElementById('modal_add').classList.add('hidden')" class="btn-secondary flex-1 text-sm">Cancelar</button>
            </div>
        </form>
    </div>
</div>
