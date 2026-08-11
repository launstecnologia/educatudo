<?php
$disciplina = $disciplina ?? [];
$atividades = $atividades ?? [];
$rubricas = $rubricas ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$base = rtrim((string) ($base_url ?? '/professor/ava'), '/');
$disciplinaId = (int) ($disciplina['id'] ?? 0);

$badge = static function (string $status): string {
    $map = [
        'publicada' => 'bg-green-100 text-green-700',
        'rascunho' => 'bg-gray-100 text-gray-600',
        'encerrada' => 'bg-red-100 text-red-700',
    ];
    return $map[$status] ?? 'bg-gray-100 text-gray-600';
};
?>

<div class="mb-6">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-4">
            <a href="<?= URL . $base ?>/disciplinas/<?= $disciplinaId ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-1">Atividades</h2>
                <p class="text-sm text-gray-600"><?= htmlspecialchars((string) ($disciplina['nome'] ?? '')) ?></p>
            </div>
        </div>
        <a href="<?= URL . $base ?>/disciplinas/<?= $disciplinaId ?>/atividades/nova" class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm"><i class="fa-solid fa-plus mr-2"></i> Nova Atividade</a>
    </div>
</div>

<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
    <?php if (empty($atividades)): ?>
        <div class="p-10 text-center text-gray-500"><i class="fa-solid fa-clipboard-list text-3xl mb-3 text-gray-300"></i><p>Nenhuma atividade criada nesta disciplina.</p></div>
    <?php else: ?>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atividade</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prazo</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Entregas</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($atividades as $a): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) $a['titulo']) ?></div>
                        <?php if (!empty($a['modulo_titulo'])): ?><div class="text-xs text-gray-500"><?= htmlspecialchars((string) $a['modulo_titulo']) ?></div><?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= !empty($a['data_entrega']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $a['data_entrega']))) : '—' ?></td>
                    <td class="px-6 py-4 text-center text-sm text-gray-700"><span class="font-semibold"><?= (int) ($a['total_avaliadas'] ?? 0) ?></span>/<?= (int) ($a['total_entregas'] ?? 0) ?></td>
                    <td class="px-6 py-4 text-center"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badge((string) $a['status']) ?>"><?= htmlspecialchars(ucfirst((string) $a['status'])) ?></span></td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <?php ob_start(); ?>
                        <a href="<?= URL . $base ?>/atividades/<?= (int) $a['id'] ?>/entregas" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-inbox text-gray-400 w-4 text-center"></i> Entregas
                        </a>
                        <a href="<?= URL . $base ?>/atividades/<?= (int) $a['id'] ?>/editar" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="post" action="<?= URL . $base ?>/atividades/<?= (int) $a['id'] ?>/excluir" onsubmit="return confirm('Excluir esta atividade e todas as entregas?');">
                            <input type="hidden" name="_token" value="<?= $csrf ?>">
                            <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                            </button>
                        </form>
                        <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                        <?php $row_actions_dropdown_id = 'row-actions-atv-' . (int) $a['id']; ?>
                        <?php include __DIR__ . '/../_partials/row_actions_dropdown.php'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Rubricas -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900"><i class="fa-solid fa-list-check mr-2 text-gray-400"></i>Rubricas de correção</h3>
        <button type="button" onclick="document.getElementById('rubricaForm').classList.toggle('hidden')" class="text-sm font-medium text-green-700 hover:text-green-800"><i class="fa-solid fa-plus mr-1"></i> Nova rubrica</button>
    </div>

    <?php if (empty($rubricas)): ?>
        <p class="text-sm text-gray-500 mb-4">Nenhuma rubrica criada. Crie uma para padronizar a correção por critérios ponderados.</p>
    <?php else: ?>
        <ul class="divide-y divide-gray-100 mb-4">
            <?php foreach ($rubricas as $r): ?>
            <li class="py-3 flex items-center justify-between">
                <span class="text-sm text-gray-800"><?= htmlspecialchars((string) $r['titulo']) ?> <span class="text-xs text-gray-400">(<?= (int) ($r['total_criterios'] ?? 0) ?> critérios)</span></span>
                <form method="post" action="<?= URL . $base ?>/rubricas/<?= (int) $r['id'] ?>/excluir" onsubmit="return confirm('Remover rubrica?');">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                    <button type="submit" class="text-gray-300 hover:text-red-600"><i class="fa-solid fa-trash"></i></button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form id="rubricaForm" method="post" action="<?= URL . $base ?>/disciplinas/<?= $disciplinaId ?>/rubricas" class="hidden border-t border-gray-100 pt-4 space-y-4">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Título da rubrica <span class="text-red-500">*</span></label>
                <input type="text" name="titulo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                <input type="text" name="descricao" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
        </div>
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-gray-700">Critérios</label>
                <button type="button" onclick="addCriterio()" class="text-xs font-medium text-green-700 hover:text-green-800"><i class="fa-solid fa-plus mr-1"></i> Adicionar critério</button>
            </div>
            <div id="criterios" class="space-y-2"></div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors"><i class="fa-solid fa-check mr-2"></i> Salvar rubrica</button>
        </div>
    </form>
</div>

<script>
function addCriterio() {
    var wrap = document.getElementById('criterios');
    var row = document.createElement('div');
    row.className = 'grid grid-cols-12 gap-2 items-center';
    row.innerHTML = '<input type="text" name="criterio_titulo[]" placeholder="Critério" class="col-span-5 px-3 py-2 border border-gray-300 rounded-lg text-sm">' +
        '<input type="text" name="criterio_descricao[]" placeholder="Descrição" class="col-span-3 px-3 py-2 border border-gray-300 rounded-lg text-sm">' +
        '<input type="number" step="0.01" min="0" name="criterio_peso[]" value="1" title="Peso" class="col-span-2 px-3 py-2 border border-gray-300 rounded-lg text-sm">' +
        '<input type="number" step="0.01" min="0" name="criterio_pmax[]" value="10" title="Pontuação máxima" class="col-span-2 px-3 py-2 border border-gray-300 rounded-lg text-sm">';
    wrap.appendChild(row);
}
addCriterio();
</script>
