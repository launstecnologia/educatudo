<?php
$anos = is_array($anos ?? null) ? $anos : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$bimestre = (int) ($bimestre ?? 1);
$turmaId = (int) ($turma_id ?? 0);
$csrf_token = $csrf_token ?? '';
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Iniciar Conselho de Classe</h2>
            <p class="text-gray-600">Uma sessão por turma, ano letivo e bimestre. O Conselho consulta o boletim já gerado — não cria nota nova.</p>
        </div>
        <a href="<?= URL ?>/admin/conselhos" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" action="<?= URL ?>/admin/conselhos">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Turma <span class="text-red-500">*</span></label>
                <select name="turma_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione</option>
                    <?php foreach ($turmas as $turma): ?>
                        <option value="<?= (int) $turma['id'] ?>" <?= $turmaId === (int) $turma['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $turma['nome']) ?>
                            <?php if (!empty($turma['ano_letivo'])): ?> (<?= (int) $turma['ano_letivo'] ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Ano letivo <span class="text-red-500">*</span></label>
                <select name="ano_letivo" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php foreach ($anos as $ano): ?>
                        <option value="<?= (int) $ano ?>" <?= $anoLetivo === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Período <span class="text-red-500">*</span></label>
                <select name="bimestre" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php for ($b = 1; $b <= 4; $b++): ?>
                        <option value="<?= $b ?>" <?= $bimestre === $b ? 'selected' : '' ?>><?= $b ?>º Bimestre</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data da reunião</label>
                <input type="date" name="data_reuniao" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Pauta</label>
            <textarea name="pauta" rows="4" placeholder="Pontos a tratar: recuperação, frequência, encaminhamentos…"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/conselhos" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg text-sm font-semibold">Iniciar preparação</button>
        </div>
    </form>
</div>
