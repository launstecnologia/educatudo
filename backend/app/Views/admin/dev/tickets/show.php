<?php
if (!function_exists('ticket_message_html')) {
    require_once __DIR__ . '/../../../../Helpers/RichTextHelper.php';
}
?>
<style>
.ticket-admin-message img { max-width: 320px; max-height: 320px; border-radius: 8px; margin: 4px 4px 4px 0; display: inline-block; }
.ticket-admin-message p { margin: 0 0 .5rem; }
.ticket-admin-message strong, .ticket-admin-message b { font-weight: 700; }
</style>

<!-- Header Section -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= URL ?>/admin/dev/tickets" class="text-sm text-purple-600 hover:text-purple-700">← Voltar para tickets</a>
            <h2 class="text-2xl font-bold text-gray-900 mt-2">Ticket #<?= (int) $ticket['id'] ?></h2>
            <p class="text-gray-600"><?= htmlspecialchars($ticket['assunto'] ?? '') ?></p>
        </div>
        <div class="text-right">
            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full <?= ($ticket['status'] ?? '') === 'fechado' ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800' ?>">
                <?= ($ticket['status'] ?? '') === 'fechado' ? 'Fechado' : 'Aberto' ?>
            </span>
            <?php if (($ticket['status'] ?? '') !== 'fechado'): ?>
                <form method="POST" action="<?= URL ?>/admin/dev/tickets/<?= (int) $ticket['id'] ?>/close" class="mt-2">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <button type="submit" class="px-3 py-1 rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors text-sm">
                        Fechar ticket
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($flash_message)): ?>
    <div class="mb-6 p-4 rounded-lg <?= $flash_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
        <?= htmlspecialchars($flash_message) ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Dados do Aluno</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-700">
        <div>
            <span class="text-gray-500">Nome:</span>
            <div class="font-medium"><?= htmlspecialchars($ticket['aluno_nome'] ?? '') ?></div>
        </div>
        <div>
            <span class="text-gray-500">Nickname:</span>
            <div class="font-medium"><?= htmlspecialchars($ticket['nickname'] ?? '-') ?></div>
        </div>
        <div>
            <span class="text-gray-500">Categoria:</span>
            <div class="font-medium"><?= htmlspecialchars(ucfirst($ticket['categoria'] ?? 'geral')) ?></div>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Mensagens</h3>
    </div>
    <div class="p-6 space-y-4">
        <?php if (empty($mensagens)): ?>
            <p class="text-gray-500">Nenhuma mensagem ainda.</p>
        <?php else: ?>
            <?php foreach ($mensagens as $mensagem): ?>
                <div class="rounded-lg border <?= $mensagem['remetente_tipo'] === 'admin' ? 'border-purple-200 bg-purple-50' : 'border-gray-200 bg-gray-50' ?> p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-800">
                            <?= htmlspecialchars($mensagem['remetente_nome'] ?? '') ?>
                        </span>
                        <span class="text-xs text-gray-500">
                            <?= date('d/m/Y H:i', strtotime($mensagem['criado_em'])) ?>
                        </span>
                    </div>
                    <div class="text-sm text-gray-700 ticket-admin-message"><?= ticket_message_html($mensagem['mensagem'] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (($ticket['status'] ?? '') !== 'fechado'): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Responder</h3>
        </div>
        <form method="POST" action="<?= URL ?>/admin/dev/tickets/<?= (int) $ticket['id'] ?>/reply" class="p-6 space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div>
                <label for="mensagem" class="block text-sm font-medium text-gray-700 mb-2">Mensagem</label>
                <textarea id="mensagem" name="mensagem" rows="5" required
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                    Enviar resposta
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

