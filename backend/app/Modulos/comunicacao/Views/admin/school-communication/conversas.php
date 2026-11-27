<?php
$items = is_array($items ?? null) ? $items : [];
$conversa = is_array($conversa ?? null) ? $conversa : null;
$mensagens = is_array($mensagens ?? null) ? $mensagens : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$conversaId = (int) ($conversa['id'] ?? 0);
$baseUrl = URL . '/admin/comunicacao-escolar/fale-com-a-escola';
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Fale com a Escola</h2>
        <p class="text-gray-600 mt-1">Chat contínuo com cada família. Clique na conversa para responder.</p>
    </div>
    <a href="<?= URL ?>/admin/comunicacao-escolar" class="text-gray-600 hover:text-gray-900">← Comunicação escolar</a>
</div>

<?php if (!empty($flash_message)): ?>
    <div class="mb-4 rounded-lg px-4 py-3 text-sm <?= ($flash_type ?? '') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars((string) $flash_message) ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden w-full" style="min-height: 36rem;">
    <div class="grid grid-cols-1 lg:grid-cols-12 h-full" style="min-height: 36rem;">
        <!-- Lista de conversas -->
        <aside class="lg:col-span-4 border-b lg:border-b-0 lg:border-r border-gray-200 flex flex-col bg-gray-50/60 max-h-[40vh] lg:max-h-none">
            <div class="px-4 py-3 border-b border-gray-200 bg-white">
                <p class="text-sm font-semibold text-gray-900">Conversas</p>
                <p class="text-xs text-gray-500"><?= count($items) ?> família(s)</p>
            </div>
            <div class="overflow-y-auto flex-1">
                <?php if ($items === []): ?>
                    <p class="px-4 py-8 text-sm text-gray-500">Nenhuma família falou com a escola ainda.</p>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $id = (int) ($item['id'] ?? 0);
                        $ativa = $id === $conversaId;
                        $naoLidas = (int) ($item['nao_lidas'] ?? 0);
                        ?>
                        <a href="<?= $baseUrl ?>/<?= $id ?>"
                           class="block px-4 py-3 border-b border-gray-100 hover:bg-white transition-colors <?= $ativa ? 'bg-white border-l-4 border-l-teal-600' : 'border-l-4 border-l-transparent' ?>">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 truncate"><?= htmlspecialchars((string) ($item['aluno_nome'] ?? 'Aluno')) ?></p>
                                    <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars((string) ($item['responsavel_nome'] ?? 'Responsável')) ?></p>
                                    <p class="text-sm text-gray-600 truncate mt-0.5"><?= htmlspecialchars((string) (($item['ultima'] ?? '') !== '' ? $item['ultima'] : 'Imagem')) ?></p>
                                </div>
                                <?php if ($naoLidas > 0): ?>
                                    <span class="shrink-0 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700"><?= $naoLidas ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($item['updated_at'])): ?>
                                <p class="text-xs text-gray-400 mt-1"><?= date('d/m H:i', strtotime((string) $item['updated_at'])) ?></p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Thread -->
        <section class="lg:col-span-8 flex flex-col bg-white" style="min-height: 36rem;">
            <?php if ($conversa === null): ?>
                <div class="flex-1 flex flex-col items-center justify-center px-6 py-16 text-center">
                    <div class="w-14 h-14 rounded-full bg-teal-50 flex items-center justify-center mb-4">
                        <i class="fa-regular fa-comments text-teal-600 text-xl"></i>
                    </div>
                    <p class="text-lg font-semibold text-gray-900">Selecione uma conversa</p>
                    <p class="text-sm text-gray-500 mt-1 max-w-sm">Escolha uma família à esquerda para ver o histórico e responder no mesmo lugar.</p>
                </div>
            <?php else: ?>
                <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
                    <p class="font-semibold text-gray-900"><?= htmlspecialchars((string) ($conversa['aluno_nome'] ?? 'Aluno')) ?></p>
                    <p class="text-sm text-gray-500">Responsável: <?= htmlspecialchars((string) ($conversa['responsavel_nome'] ?? '')) ?></p>
                </div>

                <div id="fale-chat-mensagens" class="flex-1 overflow-y-auto px-5 py-4 space-y-3 bg-[#f7f8fa]" style="max-height: 28rem;">
                    <?php if ($mensagens === []): ?>
                        <p class="text-sm text-gray-500 text-center py-8">Nenhuma mensagem nesta conversa.</p>
                    <?php endif; ?>
                    <?php foreach ($mensagens as $mensagem): ?>
                        <?php $daEscola = ($mensagem['autor_tipo'] ?? '') === 'escola'; ?>
                        <div class="flex <?= $daEscola ? 'justify-end' : 'justify-start' ?>">
                            <div class="max-w-[85%] sm:max-w-md rounded-2xl px-4 py-3 shadow-sm <?= $daEscola ? 'bg-teal-600 text-white rounded-br-md' : 'bg-white text-gray-900 border border-gray-200 rounded-bl-md' ?>">
                                <p class="text-[11px] font-semibold uppercase tracking-wide <?= $daEscola ? 'text-teal-100' : 'text-gray-400' ?>">
                                    <?= $daEscola ? 'Escola' : 'Família' ?>
                                </p>
                                <?php if (trim((string) ($mensagem['mensagem'] ?? '')) !== ''): ?>
                                    <p class="mt-1 text-sm whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $mensagem['mensagem'])) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($mensagem['imagem'])): ?>
                                    <a href="<?= htmlspecialchars((string) $mensagem['imagem']) ?>" target="_blank" rel="noopener" class="block mt-2">
                                        <img src="<?= htmlspecialchars((string) $mensagem['imagem']) ?>" alt="Imagem da conversa" class="max-h-56 rounded-lg">
                                    </a>
                                <?php endif; ?>
                                <p class="mt-1 text-[11px] <?= $daEscola ? 'text-teal-100' : 'text-gray-400' ?>">
                                    <?= !empty($mensagem['created_at']) ? date('d/m/Y H:i', strtotime((string) $mensagem['created_at'])) : '' ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="post"
                      enctype="multipart/form-data"
                      action="<?= $baseUrl ?>/<?= $conversaId ?>/responder"
                      class="border-t border-gray-200 px-4 py-3 bg-white">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">
                        <label class="flex-1 text-sm text-gray-700">
                            <span class="sr-only">Mensagem</span>
                            <textarea name="mensagem" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-teal-500 focus:border-transparent" placeholder="Escreva para a família…"></textarea>
                        </label>
                        <label class="text-sm text-gray-600 shrink-0">
                            <span class="block mb-1">Imagem</span>
                            <input type="file" name="imagem" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
                        </label>
                        <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg text-sm font-semibold shrink-0 self-end">
                            Enviar
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>
(function () {
    var box = document.getElementById('fale-chat-mensagens');
    if (box) {
        box.scrollTop = box.scrollHeight;
    }
})();
</script>
