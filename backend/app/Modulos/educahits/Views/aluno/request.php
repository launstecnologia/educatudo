<?php
$flash = $flash ?? [];
$csrf_token = $csrf_token ?? '';
$recent_requests = $recent_requests ?? [];
$subject_options = $subject_options ?? [];
$status_labels = [
    'pending' => 'Pendente',
    'approved' => 'Aprovado',
    'rejected' => 'Recusado',
    'processing' => 'Em processamento',
    'completed' => 'Concluído',
    'cancelled' => 'Cancelado',
];
?>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<div class="max-w-4xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Pedir músicas</h1>
    <p class="text-sm text-gray-600 mb-6">Preencha os dados abaixo para solicitar uma música educativa personalizada.</p>

    <div class="bg-white rounded-xl shadow border border-gray-200 p-6 mb-6">
        <form method="post" action="<?= URL ?>/hits/request" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Matéria *</label>
                    <select name="subject" required class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">Selecione a matéria</option>
                        <?php foreach ($subject_options as $subject): ?>
                        <option value="<?= htmlspecialchars((string) $subject) ?>"><?= htmlspecialchars((string) $subject) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tema *</label>
                    <input type="text" name="topic" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Ex.: Frações">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estilo musical</label>
                <input type="text" name="music_style" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Ex.: Rap, Funk, Sertanejo, Pop...">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Como você quer a música?</label>
                <textarea name="description" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Descreva o que você quer aprender e como gostaria da música."></textarea>
            </div>

            <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                Enviar pedido
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-slate-50">
            <h2 class="text-base font-semibold text-gray-900">Meus últimos pedidos</h2>
        </div>
        <?php if (empty($recent_requests)): ?>
        <p class="px-6 py-6 text-sm text-gray-600">Você ainda não fez pedidos de música.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tema</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estilo</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($recent_requests as $req): ?>
                    <?php
                    $rawStatus = strtolower(trim((string) ($req['status'] ?? 'pending')));
                    $translatedStatus = $status_labels[$rawStatus] ?? ucfirst($rawStatus);
                    ?>
                    <tr>
                        <td class="px-4 py-2 text-gray-800"><?= htmlspecialchars((string) ($req['subject'] ?? '')) ?></td>
                        <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars((string) ($req['topic'] ?? '')) ?></td>
                        <td class="px-4 py-2 text-gray-600"><?= htmlspecialchars((string) ($req['music_style'] ?? '—')) ?></td>
                        <td class="px-4 py-2 text-gray-600"><?= htmlspecialchars($translatedStatus) ?></td>
                        <td class="px-4 py-2 text-gray-500 whitespace-nowrap"><?= htmlspecialchars((string) ($req['created_at'] ?? '')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
