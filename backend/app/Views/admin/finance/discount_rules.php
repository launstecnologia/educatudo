<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$tipoBadge = fn($t) => match($t) {
    'bolsa'       => 'bg-blue-100 text-blue-800',
    'irmaos'      => 'bg-green-100 text-green-800',
    'convenio'    => 'bg-indigo-100 text-indigo-800',
    'funcionario' => 'bg-amber-100 text-amber-800',
    default       => 'bg-slate-100 text-slate-700',
};

$page_header_title    = 'Descontos';
$page_header_subtitle = 'Gerencie as regras de desconto disponíveis para contratos.';
ob_start(); ?>
<button type="button"
        onclick="document.getElementById('modal_add').classList.remove('hidden')"
        class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
    <i class="fa-solid fa-plus mr-2"></i> Nova Regra
</button>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
    <i class="fa-solid fa-shield-halved mr-2"></i>
    Criar, editar e remover regras de desconto requer perfil <strong>coordenação, direção ou dev</strong>.
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cálculo</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acumulável</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aprovação</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($rules)): ?>
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center">
                        <i class="fa-solid fa-tags text-4xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-500">Nenhuma regra cadastrada.</p>
                    </td>
                </tr>
                <?php else: foreach ($rules as $r): ?>
                <tr class="hover:bg-gray-50 <?= !$r['ativo'] ? 'opacity-50' : '' ?>">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $esc($r['nome']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $tipoBadge($r['tipo']) ?>">
                            <?= $esc($r['tipo']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= $r['calculo'] === 'percentual' ? 'Percentual' : 'Valor fixo' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900">
                        <?= $r['calculo'] === 'percentual' ? $r['valor'] . '%' : 'R$ ' . number_format($r['valor'], 2, ',', '.') ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                        <?= $r['acumulavel'] ? '<i class="fa-solid fa-check text-green-600"></i>' : '<span class="text-gray-300">—</span>' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <?php if ($r['requer_aprovacao']): ?>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Requer</span>
                        <?php else: ?>
                        <span class="text-gray-400 text-xs">Automática</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $r['ativo'] ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700' ?>">
                            <?= $r['ativo'] ? 'Ativa' : 'Inativa' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <form method="POST" action="<?= URL ?>/admin/finance/discount-rules/<?= (int)$r['id'] ?>/toggle" class="inline">
                            <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                            <button type="submit"
                                    class="text-xs text-gray-400 hover:text-blue-600 transition-colors">
                                <?= $r['ativo'] ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal nova regra -->
<div id="modal_add" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Nova Regra de Desconto</h3>
            <button type="button"
                    onclick="document.getElementById('modal_add').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="<?= URL ?>/admin/finance/discount-rules" class="divide-y divide-gray-200">
            <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome <span class="text-red-500">*</span></label>
                    <input type="text" name="nome" required placeholder="Ex: Bolsa Social 50%"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                        <select name="tipo"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                            <option value="bolsa">Bolsa</option>
                            <option value="irmaos">Irmãos</option>
                            <option value="convenio">Convênio</option>
                            <option value="funcionario">Funcionário</option>
                            <option value="manual">Manual</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cálculo</label>
                        <select name="calculo"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                            <option value="percentual">Percentual (%)</option>
                            <option value="fixo">Valor fixo (R$)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Valor <span class="text-red-500">*</span></label>
                    <input type="text" name="valor" required placeholder="0,00"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm font-mono">
                </div>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="acumulavel" value="1"
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        Acumulável
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="requer_aprovacao" value="1"
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        Requer aprovação
                    </label>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 flex gap-3">
                <button type="submit"
                        class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    Criar Regra
                </button>
                <button type="button"
                        onclick="document.getElementById('modal_add').classList.add('hidden')"
                        class="flex-1 inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>
