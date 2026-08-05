<?php
$assets = $assets ?? [];
$locations = $locations ?? [];
$suppliers = $suppliers ?? [];
$movements = $movements ?? [];
$checks = $checks ?? [];
$flash = $flash ?? ['message' => null, 'type' => 'info'];
$token = htmlspecialchars($csrf_token ?? '');
$fmtMoney = static fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Patrimônio</h2>
            <p class="text-sm text-slate-500">Bens permanentes, localização, responsáveis, conferência e depreciação linear.</p>
        </div>
        <a href="<?= URL ?>/admin/almoxarifado" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i class="fa-solid fa-boxes-stacked"></i> Almoxarifado
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
        <div class="rounded-lg border border-slate-200 bg-white p-4"><div class="text-xs font-semibold uppercase text-slate-500">Bens cadastrados</div><div class="mt-1 text-2xl font-bold text-slate-900"><?= count($assets) ?></div></div>
        <div class="rounded-lg border border-slate-200 bg-white p-4"><div class="text-xs font-semibold uppercase text-slate-500">Valor aquisição</div><div class="mt-1 text-2xl font-bold text-slate-900"><?= $fmtMoney(array_sum(array_map(fn($a) => (float) $a['valor_aquisicao'], $assets))) ?></div></div>
        <div class="rounded-lg border border-slate-200 bg-white p-4"><div class="text-xs font-semibold uppercase text-slate-500">Valor contábil</div><div class="mt-1 text-2xl font-bold text-slate-900"><?= $fmtMoney(array_sum(array_map(fn($a) => (float) $a['valor_contabil'], $assets))) ?></div></div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4"><div class="text-xs font-semibold uppercase text-amber-700">Atenção</div><div class="mt-1 text-2xl font-bold text-amber-950"><?= count(array_filter($assets, fn($a) => in_array($a['status'], ['manutencao','nao_localizado'], true))) ?></div></div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <form method="post" action="<?= URL ?>/admin/patrimonio/bens" class="rounded-lg border border-slate-200 bg-white p-4 xl:col-span-2">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <h3 class="mb-4 text-base font-semibold text-slate-900">Ficha do bem</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="numero_patrimonio" required placeholder="Nº patrimônio/plaqueta" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="descricao" required placeholder="Descrição" class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
                <select name="categoria" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach (['mobiliario','informatica','projetor','climatizacao','laboratorio','veiculo','instrumento','outro'] as $cat): ?><option value="<?= $cat ?>"><?= ucfirst($cat) ?></option><?php endforeach; ?>
                </select>
                <input name="numero_serie" placeholder="Nº série" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="marca" placeholder="Marca" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="modelo" placeholder="Modelo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="data_aquisicao" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="valor_aquisicao" type="number" step="0.01" placeholder="Valor aquisição" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="nota_fiscal" placeholder="Nota fiscal" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="fornecedor_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Fornecedor</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option><?php endforeach; ?></select>
                <input name="garantia_ate" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="vida_util_meses" type="number" value="60" placeholder="Vida útil meses" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="location_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Localização</option><?php foreach ($locations as $l): ?><option value="<?= (int) $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?></select>
                <input name="responsavel_nome" placeholder="Responsável" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="origem" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="proprio">Próprio</option><option value="comodato">Comodato</option><option value="cedido">Cedido</option><option value="doado">Doado</option></select>
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="ativo">Ativo</option><option value="manutencao">Manutenção</option><option value="emprestado">Emprestado</option><option value="baixado">Baixado</option><option value="nao_localizado">Não localizado</option></select>
                <input name="observacoes" placeholder="Observações" class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
            </div>
            <button class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Salvar bem</button>
        </form>

        <form method="post" action="<?= URL ?>/admin/patrimonio/ambientes" class="rounded-lg border border-slate-200 bg-white p-4">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <h3 class="mb-4 text-base font-semibold text-slate-900">Ambiente físico</h3>
            <div class="space-y-3">
                <input name="codigo" placeholder="Código" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="nome" required placeholder="Nome do local" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="tipo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?php foreach (['sala','laboratorio','cantina','deposito','biblioteca','secretaria','quadra','outro'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?></select>
                <input name="bloco" placeholder="Bloco" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="andar" placeholder="Andar" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="responsavel_nome" placeholder="Responsável" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="mt-4 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Salvar ambiente</button>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <form method="post" action="<?= URL ?>/admin/patrimonio/movimentacoes" class="rounded-lg border border-slate-200 bg-white p-4">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <h3 class="mb-4 text-base font-semibold text-slate-900">Movimentação patrimonial</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <select name="asset_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Bem</option><?php foreach ($assets as $a): ?><option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars($a['numero_patrimonio'] . ' - ' . $a['descricao']) ?></option><?php endforeach; ?></select>
                <select name="tipo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="transferencia">Transferência</option><option value="emprestimo">Empréstimo</option><option value="manutencao_envio">Envio manutenção</option><option value="manutencao_retorno">Retorno manutenção</option><option value="baixa">Baixa</option></select>
                <select name="location_destino_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Local destino</option><?php foreach ($locations as $l): ?><option value="<?= (int) $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?></select>
                <input name="responsavel_destino" placeholder="Responsável destino" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="documento" placeholder="Documento/ato" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="motivo" required placeholder="Motivo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="mt-4 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Registrar movimentação</button>
        </form>

        <form method="post" action="<?= URL ?>/admin/patrimonio/conferencias" class="rounded-lg border border-slate-200 bg-white p-4">
            <input type="hidden" name="_token" value="<?= $token ?>">
            <h3 class="mb-4 text-base font-semibold text-slate-900">Conferência / inventário</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <select name="asset_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Bem conferido</option><?php foreach ($assets as $a): ?><option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars($a['numero_patrimonio'] . ' - ' . $a['descricao']) ?></option><?php endforeach; ?></select>
                <select name="location_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Local encontrado</option><?php foreach ($locations as $l): ?><option value="<?= (int) $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option><?php endforeach; ?></select>
                <select name="status_conferencia" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="ok">OK</option><option value="local_errado">Local errado</option><option value="nao_localizado">Não localizado</option><option value="sem_plaqueta">Sem plaqueta</option><option value="avariado">Avariado</option></select>
                <input name="observacoes" placeholder="Observações" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="mt-4 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Registrar conferência</button>
        </form>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">Bens por localização/responsável</div>
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3 text-left">Bem</th><th class="p-3 text-left">Local</th><th class="p-3 text-left">Responsável</th><th class="p-3 text-left">Origem</th><th class="p-3 text-left">Status</th><th class="p-3 text-right">Valor contábil</th></tr></thead><tbody class="divide-y divide-slate-100">
        <?php foreach ($assets as $a): ?><tr><td class="p-3"><strong><?= htmlspecialchars($a['numero_patrimonio']) ?></strong><div class="text-xs text-slate-500"><?= htmlspecialchars($a['descricao']) ?> · <?= htmlspecialchars($a['marca'] ?? '') ?> <?= htmlspecialchars($a['modelo'] ?? '') ?></div></td><td class="p-3"><?= htmlspecialchars($a['location_nome'] ?? '-') ?></td><td class="p-3"><?= htmlspecialchars($a['responsavel_nome'] ?? '-') ?></td><td class="p-3"><?= htmlspecialchars($a['origem']) ?></td><td class="p-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"><?= htmlspecialchars($a['status']) ?></span></td><td class="p-3 text-right"><?= $fmtMoney($a['valor_contabil']) ?></td></tr><?php endforeach; ?>
        <?php if (empty($assets)): ?><tr><td colspan="6" class="p-6 text-center text-slate-500">Nenhum bem cadastrado.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">Últimas movimentações</div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3 text-left">Data</th><th class="p-3 text-left">Bem</th><th class="p-3 text-left">Tipo</th><th class="p-3 text-left">Destino</th></tr></thead><tbody class="divide-y divide-slate-100">
            <?php foreach ($movements as $m): ?><tr><td class="p-3"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td><td class="p-3"><?= htmlspecialchars($m['numero_patrimonio']) ?><div class="text-xs text-slate-500"><?= htmlspecialchars($m['descricao']) ?></div></td><td class="p-3"><?= htmlspecialchars($m['tipo']) ?></td><td class="p-3"><?= htmlspecialchars($m['destino_nome'] ?? '-') ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">Conferências recentes</div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3 text-left">Data</th><th class="p-3 text-left">Bem</th><th class="p-3 text-left">Local</th><th class="p-3 text-left">Situação</th></tr></thead><tbody class="divide-y divide-slate-100">
            <?php foreach ($checks as $c): ?><tr><td class="p-3"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td><td class="p-3"><?= htmlspecialchars($c['numero_patrimonio']) ?><div class="text-xs text-slate-500"><?= htmlspecialchars($c['descricao']) ?></div></td><td class="p-3"><?= htmlspecialchars($c['location_nome'] ?? '-') ?></td><td class="p-3"><?= htmlspecialchars($c['status_conferencia']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>

    <?php endif; ?>
</div>
