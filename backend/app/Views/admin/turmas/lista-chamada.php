<?php
$turma = $turma ?? [];
$config = $config ?? [];
$alunos = $alunos ?? [];
$ano_letivo_id = (int) ($ano_letivo_id ?? 0);
$lista_schema_ready = (bool) ($lista_schema_ready ?? false);
$campos_exportacao = $campos_exportacao ?? [];
$grupos_exportacao = [
    'lista' => 'Lista de chamada',
    'aluno' => 'Dados do aluno',
    'endereco' => 'Endereço',
];
$campos_por_grupo = [];
foreach ($campos_exportacao as $key => $meta) {
    $grupo = $meta['grupo'] ?? 'aluno';
    $campos_por_grupo[$grupo][] = ['key' => $key, 'label' => $meta['label'] ?? $key];
}
$campos_padrao = ['numero_chamada', 'nome', 'ra'];
$exportUrl = URL . '/admin/turmas/' . (int) ($turma['id'] ?? 0) . '/lista-chamada/exportar';
$totais_listagem = $totais_listagem ?? ['total' => count($alunos), 'masculino' => 0, 'feminino' => 0];
?>

<div class="mb-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="<?= URL ?>/admin/turmas/<?= (int) ($turma['id'] ?? 0) ?>" class="text-sm text-gray-500 hover:text-gray-700">← Voltar à turma</a>
            <h2 class="text-2xl font-bold text-gray-900 mt-2">Lista de Chamada — <?= htmlspecialchars($turma['nome'] ?? '') ?></h2>
            <p class="text-gray-600 text-sm mt-1">Numeração e ordenação da turma para secretaria e coordenação.</p>
        </div>
    </div>
</div>

<?php if (!empty($flash_message)): ?>
    <div class="mb-6 p-4 rounded-lg <?= ($flash_type ?? '') === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
        <?= htmlspecialchars($flash_message) ?>
    </div>
<?php endif; ?>

<?php if (!$lista_schema_ready): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-amber-900">
        Execute a migration <code>059_lista_chamada.sql</code> para habilitar a lista de chamada.
    </div>
<?php else: ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="lg:col-span-1 space-y-6">
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Configuração</h3>
        <form method="POST" action="<?= URL ?>/admin/turmas/<?= (int) ($turma['id'] ?? 0) ?>/lista-chamada/config" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="ano_letivo_id" value="<?= $ano_letivo_id ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Critério de ordem</label>
                <select name="criterio_ordem" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="alfabetica" <?= ($config['criterio_ordem'] ?? '') === 'alfabetica' ? 'selected' : '' ?>>Alfabética</option>
                    <option value="meninas_primeiro" <?= ($config['criterio_ordem'] ?? '') === 'meninas_primeiro' ? 'selected' : '' ?>>Meninas primeiro</option>
                    <option value="meninos_primeiro" <?= ($config['criterio_ordem'] ?? '') === 'meninos_primeiro' ? 'selected' : '' ?>>Meninos primeiro</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data limite (entrada tardia vai ao final)</label>
                <input type="date" name="data_corte" value="<?= htmlspecialchars($config['data_corte'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>

            <button type="submit" class="btn-primary-custom w-full px-4 py-2 rounded-lg hover:opacity-90">Salvar configuração</button>
        </form>

        <form method="POST" action="<?= URL ?>/admin/turmas/<?= (int) ($turma['id'] ?? 0) ?>/lista-chamada/recalcular" class="mt-4"
              onsubmit="return confirm('Recalcular números de chamada conforme o critério atual?');">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="ano_letivo_id" value="<?= $ano_letivo_id ?>">
            <button type="submit" class="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">Recalcular lista</button>
        </form>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Exportar lista</h3>
        <p class="text-xs text-gray-500 mb-4">Escolha os campos e o formato do documento.</p>

        <form id="form-exportar-lista-chamada" method="GET" action="<?= htmlspecialchars($exportUrl) ?>" class="space-y-4">
            <input type="hidden" name="formato" id="export-formato" value="pdf">

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Campos a exibir</label>
                    <button type="button" id="btn-toggle-todos-campos"
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                        Selecionar todos
                    </button>
                </div>
                <div class="max-h-56 overflow-y-auto border border-gray-200 rounded-lg p-3 space-y-3 bg-gray-50">
                    <?php foreach ($grupos_exportacao as $grupoKey => $grupoLabel): ?>
                        <?php if (empty($campos_por_grupo[$grupoKey])) continue; ?>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1"><?= htmlspecialchars($grupoLabel) ?></p>
                            <div class="space-y-1">
                                <?php foreach ($campos_por_grupo[$grupoKey] as $campo): ?>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                        <input type="checkbox" name="campos[]" value="<?= htmlspecialchars($campo['key']) ?>"
                                               class="campo-exportacao rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                               <?= in_array($campo['key'], $campos_padrao, true) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($campo['label']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="space-y-2 border-t border-gray-100 pt-3">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="assinatura" value="1"
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span>Espaço para assinatura na última coluna</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="logo" value="1" checked
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span>Incluir logo no topo</span>
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Orientação da página</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="radio" name="orientacao" value="vertical" checked
                               class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Vertical</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="radio" name="orientacao" value="horizontal"
                               class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Horizontal</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-col gap-2 pt-1">
                <button type="submit" onclick="document.getElementById('export-formato').value='pdf';"
                        class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-semibold">
                    Exportar PDF
                </button>
                <button type="submit" onclick="document.getElementById('export-formato').value='excel';"
                        class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-semibold">
                    Exportar Excel
                </button>
            </div>
        </form>
    </div>
    </div>

    <div class="lg:col-span-2 bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">Alunos (<?= count($alunos) ?>)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nº</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">RA</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Obs.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($alunos)): ?>
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Nenhum aluno na lista ainda. Clique em <strong>"Recalcular lista"</strong> para trazer os alunos já vinculados à turma.</td></tr>
                    <?php else: ?>
                        <?php foreach ($alunos as $a): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900"><?= (int) ($a['numero_chamada'] ?? 0) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?= htmlspecialchars($a['nome'] ?? '') ?>
                                    <?php if (!empty($a['marcado_tr'])): ?><span class="ml-1 text-xs font-bold text-red-600">TR</span><?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($a['ra'] ?? '') ?></td>
                                <td class="px-4 py-3 text-sm">
                                    <?php if (!empty($a['entrada_tardia'])): ?>
                                        <span class="text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full">Entrada tardia</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($alunos)): ?>
        <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 text-sm text-gray-700 flex flex-wrap gap-x-6 gap-y-1">
            <span><strong>Total de alunos:</strong> <?= (int) ($totais_listagem['total'] ?? 0) ?></span>
            <span><strong>Total masculino:</strong> <?= (int) ($totais_listagem['masculino'] ?? 0) ?></span>
            <span><strong>Total feminino:</strong> <?= (int) ($totais_listagem['feminino'] ?? 0) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('form-exportar-lista-chamada');
    var toggleBtn = document.getElementById('btn-toggle-todos-campos');
    if (!form || !toggleBtn) return;

    form.addEventListener('submit', function (e) {
        var checked = form.querySelectorAll('.campo-exportacao:checked');
        if (checked.length === 0) {
            e.preventDefault();
            alert('Selecione pelo menos um campo para exportar.');
        }
    });

    toggleBtn.addEventListener('click', function () {
        var boxes = form.querySelectorAll('.campo-exportacao');
        var allChecked = Array.prototype.every.call(boxes, function (b) { return b.checked; });
        boxes.forEach(function (b) { b.checked = !allChecked; });
        toggleBtn.textContent = allChecked ? 'Selecionar todos' : 'Desmarcar todos';
    });
})();
</script>

<?php endif; ?>
