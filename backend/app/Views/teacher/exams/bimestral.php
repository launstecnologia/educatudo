<div class="mb-8">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Provas Bimestrais</h1>
            <p class="text-gray-600 mt-2">Filtre, selecione e baixe em um único documento as provas que você criou.</p>
        </div>
        <a href="<?= URL ?>/professor/provas" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">
            Voltar para Minhas Provas
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg mb-8">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Filtros</h2>
    </div>
    <div class="p-6">
        <form method="GET" action="<?= URL ?>/professor/provas-bimestral" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                <input type="text" name="busca" value="<?= htmlspecialchars($filters['busca'] ?? '') ?>" placeholder="Título, matéria ou turma" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
                <select name="turma_id" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="0">Todas as turmas</option>
                    <?php foreach (($turmas ?? []) as $turma): ?>
                        <option value="<?= (int) $turma['id'] ?>" <?= (int) ($filters['turma_id'] ?? 0) === (int) $turma['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($turma['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Ano</label>
                <select name="ano" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="0">Todos os anos</option>
                    <?php foreach (($anos ?? []) as $ano): ?>
                        <?php $valorAno = (int) ($ano['ano'] ?? 0); ?>
                        <?php if ($valorAno > 0): ?>
                            <option value="<?= $valorAno ?>" <?= (int) ($filters['ano'] ?? 0) === $valorAno ? 'selected' : '' ?>>
                                <?= $valorAno ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end gap-3">
                <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700">
                    Filtrar
                </button>
                <a href="<?= URL ?>/professor/provas-bimestral" class="inline-flex items-center px-4 py-2.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
                    Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg">
    <div class="p-6 border-b border-gray-200 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Selecione as provas</h2>
            <p class="text-sm text-gray-600 mt-1"><?= count($provas ?? []) ?> prova(s) encontrada(s)</p>
        </div>
    </div>
    <div class="p-6">
        <?php if (empty($provas)): ?>
            <div class="text-center py-12 text-gray-500">
                Nenhuma prova encontrada com os filtros atuais.
            </div>
        <?php else: ?>
            <form method="POST" action="<?= URL ?>/professor/provas-bimestral/baixar" id="form-provas-bimestral">
                <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
                    <label class="inline-flex items-center gap-3 text-sm text-gray-700">
                        <input type="checkbox" id="selecionar-todas" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Selecionar todas as provas visíveis
                    </label>
                    <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700">
                        Baixar documento com selecionadas
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selecionar</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma(s)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($provas as $prova): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 align-top">
                                        <input type="checkbox" name="provas[]" value="<?= (int) $prova['id'] ?>" class="checkbox-prova rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($prova['titulo'] ?? '') ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-700"><?= htmlspecialchars($prova['materia_nome'] ?? '—') ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-700"><?= htmlspecialchars($prova['turmas_exibicao'] ?? 'Todas as turmas') ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-700"><?= (int) ($prova['ano_referencia'] ?? 0) ?: '—' ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-700">
                                            <?= !empty($prova['data_inicio']) ? date('d/m/Y H:i', strtotime($prova['data_inicio'])) : '—' ?><br>
                                            <span class="text-xs text-gray-500">até <?= !empty($prova['data_fim']) ? date('d/m/Y H:i', strtotime($prova['data_fim'])) : '—' ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="<?= URL ?>/professor/provas/visualizar/<?= (int) $prova['id'] ?>" class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                            Ver prova
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selecionarTodas = document.getElementById('selecionar-todas');
    const checkboxes = Array.from(document.querySelectorAll('.checkbox-prova'));
    const form = document.getElementById('form-provas-bimestral');

    if (selecionarTodas) {
        selecionarTodas.addEventListener('change', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = selecionarTodas.checked;
            });
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            const selecionadas = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            });
            if (selecionadas.length === 0) {
                event.preventDefault();
                alert('Selecione ao menos uma prova para baixar.');
            }
        });
    }
});
</script>
