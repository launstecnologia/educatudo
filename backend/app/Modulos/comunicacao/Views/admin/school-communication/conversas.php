<?php
$items = is_array($items ?? null) ? $items : [];
$conversa = is_array($conversa ?? null) ? $conversa : null;
$mensagens = is_array($mensagens ?? null) ? $mensagens : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$conversaId = (int) ($conversa['id'] ?? 0);
$baseUrl = URL . '/admin/comunicacao-escolar/fale-com-a-escola';
$escolaAvatar = trim((string) ($escola_avatar ?? ''));
$reacoesProntas = !empty($reacoes_prontas);

$iniciais = static function (string $nome): string {
    $nome = trim($nome);
    if ($nome === '') {
        return '?';
    }
    $partes = preg_split('/\s+/', $nome) ?: [];
    $a = mb_substr($partes[0] ?? '', 0, 1);
    $b = mb_substr($partes[count($partes) > 1 ? count($partes) - 1 : 0] ?? '', 0, 1);
    return mb_strtoupper($a . $b);
};

$urlMidia = static function (string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim((string) URL, '/') . '/' . ltrim($path, '/');
};
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Fale com a Escola</h2>
        <p class="text-gray-600 mt-1">Chat contínuo com cada família — texto, imagem, vídeo, PDF e curtidas.</p>
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
                        $foto = $urlMidia((string) ($item['foto_url'] ?? ''));
                        $nomeAluno = (string) ($item['aluno_nome'] ?? 'Aluno');
                        ?>
                        <a href="<?= $baseUrl ?>/<?= $id ?>"
                           class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 hover:bg-white transition-colors <?= $ativa ? 'bg-white border-l-4 border-l-teal-600' : 'border-l-4 border-l-transparent' ?>">
                            <div class="shrink-0 w-11 h-11 rounded-full overflow-hidden bg-teal-100 flex items-center justify-center text-teal-800 text-sm font-bold">
                                <?php if ($foto !== ''): ?>
                                    <img src="<?= htmlspecialchars($foto) ?>" alt="" class="w-full h-full object-cover" onerror="this.remove()">
                                <?php else: ?>
                                    <?= htmlspecialchars($iniciais($nomeAluno)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($nomeAluno) ?></p>
                                    <?php if ($naoLidas > 0): ?>
                                        <span class="shrink-0 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700"><?= $naoLidas ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars((string) ($item['responsavel_nome'] ?? 'Responsável')) ?></p>
                                <p class="text-sm text-gray-600 truncate mt-0.5"><?= htmlspecialchars((string) (($item['ultima'] ?? '') !== '' ? $item['ultima'] : 'Anexo')) ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <section class="lg:col-span-8 flex flex-col bg-white" style="min-height: 36rem;">
            <?php if ($conversa === null): ?>
                <div class="flex-1 flex flex-col items-center justify-center px-6 py-16 text-center">
                    <div class="w-14 h-14 rounded-full bg-teal-50 flex items-center justify-center mb-4">
                        <i class="fa-regular fa-comments text-teal-600 text-xl"></i>
                    </div>
                    <p class="text-lg font-semibold text-gray-900">Selecione uma conversa</p>
                    <p class="text-sm text-gray-500 mt-1 max-w-sm">Escolha uma família à esquerda para ver o histórico e responder.</p>
                </div>
            <?php else: ?>
                <?php
                $fotoConversa = $urlMidia((string) ($conversa['foto_url'] ?? ''));
                $nomeConversa = (string) ($conversa['aluno_nome'] ?? 'Aluno');
                ?>
                <div class="px-5 py-4 border-b border-gray-200 bg-gray-50 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full overflow-hidden bg-teal-100 flex items-center justify-center text-teal-800 font-bold shrink-0">
                        <?php if ($fotoConversa !== ''): ?>
                            <img src="<?= htmlspecialchars($fotoConversa) ?>" alt="" class="w-full h-full object-cover" onerror="this.remove()">
                        <?php else: ?>
                            <?= htmlspecialchars($iniciais($nomeConversa)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($nomeConversa) ?></p>
                        <p class="text-sm text-gray-500 truncate">Responsável: <?= htmlspecialchars((string) ($conversa['responsavel_nome'] ?? '')) ?></p>
                    </div>
                </div>

                <div id="fale-chat-mensagens" class="flex-1 overflow-y-auto px-4 sm:px-5 py-4 space-y-4 bg-[#efeae2]" style="max-height: 28rem;">
                    <?php if ($mensagens === []): ?>
                        <p class="text-sm text-gray-500 text-center py-8">Nenhuma mensagem nesta conversa.</p>
                    <?php endif; ?>
                    <?php foreach ($mensagens as $mensagem): ?>
                        <?php
                        $daEscola = ($mensagem['autor_tipo'] ?? '') === 'escola';
                        $msgId = (int) ($mensagem['id'] ?? 0);
                        $anexoTipo = (string) ($mensagem['anexo_tipo'] ?? (!empty($mensagem['imagem']) ? 'imagem' : ''));
                        $anexoUrl = $urlMidia((string) ($mensagem['imagem'] ?? ''));
                        $anexoNome = (string) ($mensagem['imagem_nome'] ?? 'arquivo');
                        $curtidas = (int) ($mensagem['curtidas'] ?? 0);
                        $euCurti = !empty($mensagem['eu_curti']);
                        $fotoBolha = $daEscola ? $urlMidia($escolaAvatar) : $fotoConversa;
                        $iniciaisBolha = $daEscola ? 'ES' : $iniciais($nomeConversa);
                        ?>
                        <div id="msg-<?= $msgId ?>" class="flex items-end gap-2 <?= $daEscola ? 'justify-end' : 'justify-start' ?>">
                            <?php if (!$daEscola): ?>
                                <div class="w-8 h-8 rounded-full overflow-hidden bg-white border border-gray-200 flex items-center justify-center text-[10px] font-bold text-gray-600 shrink-0">
                                    <?php if ($fotoBolha !== ''): ?>
                                        <img src="<?= htmlspecialchars($fotoBolha) ?>" alt="" class="w-full h-full object-cover" onerror="this.remove()">
                                    <?php else: ?>
                                        <?= htmlspecialchars($iniciaisBolha) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="max-w-[85%] sm:max-w-md">
                                <div class="rounded-2xl px-3.5 py-2.5 shadow-sm <?= $daEscola ? 'bg-[#dcf8c6] text-gray-900 rounded-br-md' : 'bg-white text-gray-900 border border-gray-100 rounded-bl-md' ?>">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                        <?= $daEscola ? 'Escola' : 'Família' ?>
                                    </p>
                                    <?php if (trim((string) ($mensagem['mensagem'] ?? '')) !== ''): ?>
                                        <p class="mt-1 text-sm whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) $mensagem['mensagem'])) ?></p>
                                    <?php endif; ?>

                                    <?php if ($anexoUrl !== '' && $anexoTipo === 'imagem'): ?>
                                        <a href="<?= htmlspecialchars($anexoUrl) ?>" target="_blank" rel="noopener" class="block mt-2">
                                            <img src="<?= htmlspecialchars($anexoUrl) ?>" alt="Imagem" class="max-h-56 rounded-lg">
                                        </a>
                                    <?php elseif ($anexoUrl !== '' && $anexoTipo === 'video'): ?>
                                        <video controls class="mt-2 max-h-56 w-full rounded-lg bg-black" preload="metadata">
                                            <source src="<?= htmlspecialchars($anexoUrl) ?>" type="<?= htmlspecialchars((string) ($mensagem['anexo_mime'] ?? 'video/mp4')) ?>">
                                        </video>
                                    <?php elseif ($anexoUrl !== '' && $anexoTipo === 'arquivo'): ?>
                                        <a href="<?= htmlspecialchars($anexoUrl) ?>" target="_blank" rel="noopener"
                                           class="mt-2 inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 hover:bg-gray-100">
                                            <i class="fa-regular fa-file-pdf text-red-600"></i>
                                            <?= htmlspecialchars($anexoNome) ?>
                                        </a>
                                    <?php elseif ($anexoUrl !== ''): ?>
                                        <a href="<?= htmlspecialchars($anexoUrl) ?>" target="_blank" rel="noopener" class="block mt-2 text-sm underline">Abrir anexo</a>
                                    <?php endif; ?>

                                    <div class="mt-1 flex items-center justify-between gap-3">
                                        <p class="text-[11px] text-gray-500">
                                            <?= !empty($mensagem['created_at']) ? date('d/m/Y H:i', strtotime((string) $mensagem['created_at'])) : '' ?>
                                        </p>
                                        <?php if ($curtidas > 0): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-white/90 border border-gray-200 px-1.5 py-0.5 text-[11px] text-gray-700 shadow-sm">
                                                👍 <?= $curtidas ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($reacoesProntas && $msgId > 0): ?>
                                    <form method="post"
                                          action="<?= $baseUrl ?>/<?= $conversaId ?>/mensagens/<?= $msgId ?>/curtir"
                                          class="mt-1 <?= $daEscola ? 'text-right' : 'text-left' ?>">
                                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs <?= $euCurti ? 'bg-teal-100 text-teal-800' : 'bg-white/80 text-gray-600 hover:bg-white' ?> border border-gray-200 shadow-sm"
                                                title="<?= $euCurti ? 'Remover curtida' : 'Curtir' ?>">
                                            <i class="fa-<?= $euCurti ? 'solid' : 'regular' ?> fa-thumbs-up"></i>
                                            <?= $euCurti ? 'Curtiu' : 'Curtir' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <?php if ($daEscola): ?>
                                <div class="w-8 h-8 rounded-full overflow-hidden bg-teal-600 flex items-center justify-center text-[10px] font-bold text-white shrink-0">
                                    <?php if ($fotoBolha !== ''): ?>
                                        <img src="<?= htmlspecialchars($fotoBolha) ?>" alt="" class="w-full h-full object-cover" onerror="this.remove()">
                                    <?php else: ?>
                                        <?= htmlspecialchars($iniciaisBolha) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="post"
                      enctype="multipart/form-data"
                      action="<?= $baseUrl ?>/<?= $conversaId ?>/responder"
                      class="border-t border-gray-200 px-4 py-3 bg-white">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                    <div class="flex flex-col gap-3">
                        <label class="text-sm text-gray-700">
                            <span class="sr-only">Mensagem</span>
                            <textarea name="mensagem" rows="2" class="w-full rounded-xl border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-teal-500 focus:border-transparent" placeholder="Escreva para a família…"></textarea>
                        </label>
                        <div class="flex flex-wrap items-end gap-3">
                            <label class="text-sm text-gray-600 flex-1 min-w-[12rem]">
                                <span class="block mb-1">Anexo (imagem, vídeo ou PDF)</span>
                                <input type="file" name="anexo" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf" class="block w-full text-sm">
                            </label>
                            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-xl text-sm font-semibold shrink-0">
                                Enviar
                            </button>
                        </div>
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
        var hash = window.location.hash;
        if (hash && document.querySelector(hash)) {
            document.querySelector(hash).scrollIntoView({ block: 'center' });
        } else {
            box.scrollTop = box.scrollHeight;
        }
    }
})();
</script>
