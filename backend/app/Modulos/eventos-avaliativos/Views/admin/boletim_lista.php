<?php
$ano = (int) ($ano ?? date('Y'));
$anos = is_array($anos ?? null) ? $anos : [$ano];
$itens = is_array($itens ?? null) ? $itens : [];
$schemaPronto = !empty($schema_pronto);
$podeAlterar = !empty($pode_alterar);
$podeExcluir = !empty($pode_excluir);
$csrf_token = (string) ($csrf_token ?? '');

include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<div class="mb-6">
    <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Boletim</h1>
            <p class="text-sm text-gray-600 mt-1">Crie o boletim oficial da vida escolar e, se a escola tiver, um boletim extra. Cada um tem o seu layout.</p>
        </div>
        <a href="<?= URL ?>/admin/academico"
           class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left mr-2 text-gray-500" aria-hidden="true"></i>
            Voltar
        </a>
    </div>
</div>

<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code class="text-sm">2026_10_28_boletim_layouts_varios.sql</code> no painel Master para criar mais de um boletim no ano.
</div>
<?php endif; ?>

<div class="mb-6 flex items-start justify-between gap-3 flex-wrap">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Modelos de Boletim</h2>
        <p class="text-sm text-gray-600 mt-1">Defina as matérias, as turmas e a regra de aprovação de cada boletim.</p>
    </div>
    <?php if ($schemaPronto && $podeAlterar): ?>
    <a href="<?= URL ?>/admin/eventos-avaliativos/boletim/novo?ano=<?= $ano ?>"
       class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>
        Novo boletim
    </a>
    <?php endif; ?>
</div>

<form method="GET" action="<?= URL ?>/admin/eventos-avaliativos/boletim" class="mb-6 flex items-end gap-3">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="boletim-ano">Ano</label>
        <select name="ano" id="boletim-ano" onchange="this.form.submit()"
                class="w-32 pl-4 py-2 border border-gray-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <?php foreach ($anos as $opcao): ?>
                <option value="<?= (int) $opcao ?>" <?= (int) $opcao === $ano ? 'selected' : '' ?>><?= (int) $opcao ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Finalidade</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Regra de aprovação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matérias</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Períodos configurados</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($itens === []): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <p>Nenhum boletim em <?= $ano ?>.</p>
                        <p class="text-sm mt-1">Crie o oficial da vida escolar e, se precisar, um extra.</p>
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($itens as $item): ?>
                    <?php
                        $id = (int) ($item['id'] ?? 0);
                        $extra = ((string) ($item['finalidade'] ?? '')) === 'complementar';
                        $nomeRegra = trim((string) ($item['regra_academica_nome'] ?? ''));
                    ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $extra ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                            <?= $extra ? 'Extra' : 'Oficial' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= (int) ($item['ano_letivo'] ?? 0) > 0 ? (int) $item['ano_letivo'] : '—' ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php if ($nomeRegra !== ''): ?>
                            <?= htmlspecialchars($nomeRegra, ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($item['media_minima'] !== null): ?>
                            <div class="text-xs text-gray-500">mín. <?= htmlspecialchars(number_format((float) $item['media_minima'], 2, ',', ''), ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-gray-400">Mínima 6,0 até vincular uma regra</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= (int) ($item['materias_qtd'] ?? 0) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= (int) ($item['periodos_qtd'] ?? 0) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <?php if ($podeAlterar && $schemaPronto): ?>
                        <a href="<?= URL ?>/admin/eventos-avaliativos/boletim/<?= $id ?>/dados"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center shrink-0" aria-hidden="true"></i>
                            Editar o boletim
                        </a>
                        <?php endif; ?>
                        <a href="<?= URL ?>/admin/eventos-avaliativos/boletim/<?= $id ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-table-cells text-gray-400 w-4 text-center shrink-0" aria-hidden="true"></i>
                            Layout do boletim
                        </a>
                        <?php if ($podeExcluir && $schemaPronto): ?>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="POST" action="<?= URL ?>/admin/eventos-avaliativos/boletim/<?= $id ?>/excluir" onsubmit="return confirm('Excluir este boletim?');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center shrink-0" aria-hidden="true"></i>
                                Excluir
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'boletim-layout-' . $id;
                        $row_actions_dropdown_menu_class = 'w-56';
                        include \App\Core\ViewPaths::adminPartial('row_actions_dropdown.php');
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
