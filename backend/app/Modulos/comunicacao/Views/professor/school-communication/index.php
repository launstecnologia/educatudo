<?php
$turmas = is_array($turmas ?? null) ? $turmas : [];
$itens = is_array($itens ?? null) ? $itens : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$flash_message = (string) ($flash_message ?? '');
if ($flash_message !== '') {
    include \App\Core\ViewPaths::adminPartial('flash_message.php');
}
?>
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Recados aos pais</h2>
    <p class="text-gray-600">A coordenação liberou este envio. Use para avisos da turma, como alimentação no maternal e no pré. Os pais podem responder.</p>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 w-full mb-8">
    <form method="post" action="<?= URL ?>/professor/comunicacao-pais" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <div>
            <label for="titulo" class="block text-sm font-medium text-gray-700 mb-1">Título</label>
            <input type="text" id="titulo" name="titulo" required maxlength="255" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <div>
            <label for="conteudo" class="block text-sm font-medium text-gray-700 mb-1">Mensagem</label>
            <textarea id="conteudo" name="conteudo" rows="5" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="video_url" class="block text-sm font-medium text-gray-700 mb-1">Link do YouTube</label>
                <input type="url" id="video_url" name="video_url" placeholder="https://www.youtube.com/watch?v=..." class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Imagem ou documento</label>
                <input type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>
        <div>
            <p class="block text-sm font-medium text-gray-700 mb-2">Pais das turmas</p>
            <?php if ($turmas === []): ?>
                <p class="text-sm text-gray-500">Nenhuma turma vinculada a você.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($turmas as $turma): ?>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="turmas[]" value="<?= (int) $turma['id'] ?>" class="rounded border-gray-300 text-green-600">
                        <?= htmlspecialchars((string) $turma['nome']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="enviar_push" value="1" class="rounded border-gray-300 text-green-600">
            <span class="text-sm text-gray-700">Enviar também notificação push no celular</span>
        </label>
        <div class="flex justify-end">
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold">Enviar aos pais</button>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="font-semibold text-gray-900">Enviados</h3>
    </div>
    <?php if ($itens === []): ?>
        <p class="px-6 py-8 text-sm text-gray-500">Nenhum recado enviado ainda.</p>
    <?php else: ?>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recado</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Push</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Leituras</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Respostas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($itens as $item): ?>
                <tr>
                    <td class="px-4 py-3">
                        <strong><?= htmlspecialchars((string) $item['titulo']) ?></strong>
                        <div class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime((string) $item['created_at'])) ?></div>
                    </td>
                    <td class="px-4 py-3 text-center"><?= !empty($item['enviar_push']) ? 'Sim' : 'Não' ?></td>
                    <td class="px-4 py-3 text-center"><?= (int) $item['read_count'] ?></td>
                    <td class="px-4 py-3 text-center"><?= (int) $item['reply_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
