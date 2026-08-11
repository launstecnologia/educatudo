<?php
$professor = $professor ?? null;
$checklist = $checklist ?? [];
$docs = $docs ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$statusOpcoes = ['pendente' => 'Pendente', 'entregue' => 'Entregue', 'dispensado' => 'Dispensado'];
?>
<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/teachers-documentos"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors" aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Documentação — <?= htmlspecialchars((string) ($professor['nome'] ?? 'Professor')) ?></h2>
            <p class="text-sm text-gray-600">Marque o status de cada documento do professor.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<?php if (!$professor): ?>
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">Professor não encontrado.</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <form method="post" action="<?= URL ?>/admin/teachers-documentos/salvar">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="professor_id" value="<?= (int) $professor['id'] ?>">
        <div class="divide-y divide-gray-100">
            <?php foreach ($checklist as $tipo => $label):
                $doc = $docs[$tipo] ?? [];
                $status = (string) ($doc['status'] ?? 'pendente');
                $obs = (string) ($doc['observacao'] ?? ''); ?>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($label) ?></div>
                    <div>
                        <select name="status[<?= htmlspecialchars($tipo) ?>]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <?php foreach ($statusOpcoes as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <input type="text" name="observacao[<?= htmlspecialchars($tipo) ?>]" value="<?= htmlspecialchars($obs) ?>" placeholder="Observação" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-200 flex justify-end">
            <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors">
                <i class="fa-solid fa-check mr-2"></i> Salvar documentação
            </button>
        </div>
    </form>
</div>
<?php endif; ?>
