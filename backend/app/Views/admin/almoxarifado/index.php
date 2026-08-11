<?php
$items = $items ?? [];
$warehouses = $warehouses ?? [];
$suppliers = $suppliers ?? [];
$lots = $lots ?? [];
$movements = $movements ?? [];
$requisitions = $requisitions ?? [];
$alerts = $alerts ?? ['low_stock' => [], 'expiring' => []];
$flash = $flash ?? ['message' => null, 'type' => 'info'];
$token = htmlspecialchars($csrf_token ?? '');
$fmtQty = static fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
$fmtMoney = static fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Almoxarifado</h2>
            <p class="text-sm text-slate-500">Estoque de consumo, lotes, validade, requisições e movimentações por depósito.</p>
        </div>
        <a href="<?= URL ?>/admin/patrimonio" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i class="fa-solid fa-barcode"></i> Patrimônio
        </a>
    </div>

    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-lg border <?= ($flash['type'] ?? '') === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?> px-4 py-3 text-sm">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($schema_ready)): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            Execute a migration <code>2026_07_02_inventory_patrimony.sql</code> para habilitar o módulo.
        </div>
    <?php else: ?>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs font-semibold uppercase text-slate-500">Itens ativos</div>
            <div class="mt-1 text-2xl font-bold text-slate-900"><?= count(array_filter($items, fn($i) => (int) $i['ativo'] === 1)) ?></div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs font-semibold uppercase text-slate-500">Depósitos</div>
            <div class="mt-1 text-2xl font-bold text-slate-900"><?= count($warehouses) ?></div>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
            <div class="text-xs font-semibold uppercase text-red-600">Abaixo do mínimo</div>
            <div class="mt-1 text-2xl font-bold text-red-900"><?= count($alerts['low_stock'] ?? []) ?></div>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
            <div class="text-xs font-semibold uppercase text-amber-700">Vencendo em 30 dias</div>
            <div class="mt-1 text-2xl font-bold text-amber-950"><?= count($alerts['expiring'] ?? []) ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <form method="post" action="<?= URL ?>/admin/almoxarifado/itens" class="rounded-lg border border-slate-200 bg-white p-4">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <h3 class="mb-4 text-base font-semibold text-slate-900">Catálogo de itens</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <input name="codigo" required placeholder="Código interno" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="descricao" required placeholder="Descrição" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="unidade_medida" value="un" placeholder="Unidade" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="categoria" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach (['limpeza','escritorio','didatico','merenda','higiene','laboratorio','outro'] as $cat): ?>
                    <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                    <?php endforeach; ?>
                </select>
                <input name="estoque_minimo" type="number" step="0.001" placeholder="Estoque mínimo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="estoque_maximo" type="number" step="0.001" placeholder="Estoque máximo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="ponto_reposicao" type="number" step="0.001" placeholder="Ponto reposição" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="custo_medio" type="number" step="0.0001" placeholder="Custo inicial" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <label class="mt-3 flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="ativo" value="1" checked> Ativo</label>
            <button class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Salvar item</button>
        </form>

        <form method="post" action="<?= URL ?>/admin/almoxarifado/movimentacoes" class="rounded-lg border border-slate-200 bg-white p-4 xl:col-span-2">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <h3 class="mb-4 text-base font-semibold text-slate-900">Movimentação</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <select name="tipo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                    <option value="transferencia">Transferência</option>
                    <option value="ajuste">Ajuste/Inventário</option>
                    <option value="baixa">Baixa por perda/vencimento</option>
                </select>
                <select name="item_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Item</option>
                    <?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>"><?= htmlspecialchars($item['codigo'] . ' - ' . $item['descricao']) ?></option><?php endforeach; ?>
                </select>
                <select name="warehouse_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Depósito origem</option>
                    <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['id'] ?>"><?= htmlspecialchars($w['nome']) ?></option><?php endforeach; ?>
                </select>
                <select name="warehouse_destino_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Destino se transferência</option>
                    <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['id'] ?>"><?= htmlspecialchars($w['nome']) ?></option><?php endforeach; ?>
                </select>
                <input name="quantidade" required type="number" step="0.001" placeholder="Quantidade" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="custo_unitario" type="number" step="0.0001" placeholder="Custo unitário" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="lote" placeholder="Lote" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="validade" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="documento" placeholder="NF/documento" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="fornecedor_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Fornecedor</option>
                    <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option><?php endforeach; ?>
                </select>
                <input name="setor" placeholder="Setor/turma/projeto" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="motivo" required placeholder="Motivo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="mt-4 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Registrar movimento</button>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <form method="post" action="<?= URL ?>/admin/almoxarifado/requisicoes" class="rounded-lg border border-slate-200 bg-white p-4">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <h3 class="mb-4 text-base font-semibold text-slate-900">Nova requisição interna</h3>
            <div class="space-y-3">
                <select name="item_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Item</option><?php foreach ($items as $item): ?><option value="<?= (int) $item['id'] ?>"><?= htmlspecialchars($item['descricao']) ?></option><?php endforeach; ?></select>
                <select name="warehouse_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Depósito</option><?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['id'] ?>"><?= htmlspecialchars($w['nome']) ?></option><?php endforeach; ?></select>
                <input name="quantidade" required type="number" step="0.001" placeholder="Quantidade" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="solicitante_nome" required placeholder="Solicitante" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="setor" placeholder="Setor, professor, turma ou projeto" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="justificativa" required placeholder="Justificativa" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="mt-4 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Criar requisição</button>
        </form>

        <div class="rounded-lg border border-slate-200 bg-white xl:col-span-2">
            <div class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">Requisições</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3 text-left">Pedido</th><th class="p-3 text-left">Status</th><th class="p-3 text-right">Ações</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($requisitions as $r): ?>
                        <tr>
                            <td class="p-3"><strong><?= htmlspecialchars($r['item_descricao']) ?></strong><div class="text-xs text-slate-500"><?= $fmtQty($r['quantidade']) ?> <?= htmlspecialchars($r['unidade_medida']) ?> · <?= htmlspecialchars($r['solicitante_nome']) ?> · <?= htmlspecialchars($r['setor'] ?? '') ?></div></td>
                            <td class="p-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"><?= htmlspecialchars($r['status']) ?></span></td>
                            <td class="p-3 text-right">
                                <?php if ($r['status'] === 'pendente'): ?>
                                <form class="inline" method="post" action="<?= URL ?>/admin/almoxarifado/requisicoes/<?= (int) $r['id'] ?>/aprovar"><input type="hidden" name="_token" value="<?= $token ?>"><button class="text-sm font-semibold text-emerald-700">Aprovar</button></form>
                                <form class="ml-3 inline" method="post" action="<?= URL ?>/admin/almoxarifado/requisicoes/<?= (int) $r['id'] ?>/rejeitar"><input type="hidden" name="_token" value="<?= $token ?>"><button class="text-sm font-semibold text-red-700">Rejeitar</button></form>
                                <?php endif; ?>
                                <?php if (in_array($r['status'], ['pendente','aprovada'], true)): ?>
                                <form class="ml-3 inline" method="post" action="<?= URL ?>/admin/almoxarifado/requisicoes/<?= (int) $r['id'] ?>/atender"><input type="hidden" name="_token" value="<?= $token ?>"><button class="text-sm font-semibold text-blue-700">Atender</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($requisitions)): ?><tr><td colspan="3" class="p-6 text-center text-slate-500">Nenhuma requisição registrada.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">Posição de estoque</div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3 text-left">Item</th><th class="p-3 text-right">Estoque</th><th class="p-3 text-right">Mín.</th><th class="p-3 text-right">Valor</th></tr></thead><tbody class="divide-y divide-slate-100">
            <?php foreach ($items as $item): ?><tr><td class="p-3"><strong><?= htmlspecialchars($item['descricao']) ?></strong><div class="text-xs text-slate-500"><?= htmlspecialchars($item['codigo']) ?> · <?= htmlspecialchars($item['categoria']) ?></div></td><td class="p-3 text-right"><?= $fmtQty($item['estoque_atual']) ?> <?= htmlspecialchars($item['unidade_medida']) ?></td><td class="p-3 text-right"><?= $fmtQty($item['estoque_minimo']) ?></td><td class="p-3 text-right"><?= $fmtMoney((float) $item['estoque_atual'] * (float) $item['custo_medio']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">Lotes e validade</div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3 text-left">Lote</th><th class="p-3 text-left">Depósito</th><th class="p-3 text-right">Qtd.</th><th class="p-3 text-left">Validade</th></tr></thead><tbody class="divide-y divide-slate-100">
            <?php foreach ($lots as $lot): ?><tr><td class="p-3"><strong><?= htmlspecialchars($lot['item_descricao']) ?></strong><div class="text-xs text-slate-500"><?= htmlspecialchars($lot['lote'] ?: 'Sem lote') ?></div></td><td class="p-3"><?= htmlspecialchars($lot['deposito_nome']) ?></td><td class="p-3 text-right"><?= $fmtQty($lot['quantidade_atual']) ?> <?= htmlspecialchars($lot['unidade_medida']) ?></td><td class="p-3"><?= $lot['validade'] ? date('d/m/Y', strtotime($lot['validade'])) : '-' ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <form method="post" action="<?= URL ?>/admin/almoxarifado/depositos" class="rounded-lg border border-slate-200 bg-white p-4">
            <input type="hidden" name="_token" value="<?= $token ?>"><h3 class="mb-3 font-semibold text-slate-900">Depósito</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3"><input name="nome" required placeholder="Nome" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><select name="tipo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><?php foreach (['central','cantina','laboratorio','limpeza','secretaria','outro'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?></select><input name="responsavel_nome" placeholder="Responsável" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <button class="mt-3 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Salvar depósito</button>
        </form>
        <form method="post" action="<?= URL ?>/admin/almoxarifado/fornecedores" class="rounded-lg border border-slate-200 bg-white p-4">
            <input type="hidden" name="_token" value="<?= $token ?>"><h3 class="mb-3 font-semibold text-slate-900">Fornecedor</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3"><input name="nome" required placeholder="Nome" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="cnpj" placeholder="CNPJ" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="contato" placeholder="Contato" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="telefone" placeholder="Telefone" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="email" placeholder="E-mail" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="observacoes" placeholder="Histórico/observações" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <button class="mt-3 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Salvar fornecedor</button>
        </form>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">Últimas movimentações</div>
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3 text-left">Data</th><th class="p-3 text-left">Tipo</th><th class="p-3 text-left">Item</th><th class="p-3 text-left">Depósito</th><th class="p-3 text-right">Qtd.</th><th class="p-3 text-left">Motivo</th></tr></thead><tbody class="divide-y divide-slate-100">
        <?php foreach ($movements as $m): ?><tr><td class="p-3"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td><td class="p-3"><?= htmlspecialchars($m['tipo']) ?></td><td class="p-3"><?= htmlspecialchars($m['item_descricao']) ?></td><td class="p-3"><?= htmlspecialchars($m['deposito_nome']) ?><?= $m['deposito_destino_nome'] ? ' -> ' . htmlspecialchars($m['deposito_destino_nome']) : '' ?></td><td class="p-3 text-right"><?= $fmtQty($m['quantidade']) ?> <?= htmlspecialchars($m['unidade_medida']) ?></td><td class="p-3"><?= htmlspecialchars($m['motivo']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>

    <?php endif; ?>
</div>
