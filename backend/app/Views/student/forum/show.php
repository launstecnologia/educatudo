<?php
$error = $_SESSION['forum_error'] ?? '';
$success = $_SESSION['forum_success'] ?? '';
if (isset($_SESSION['forum_error'])) unset($_SESSION['forum_error']);
if (isset($_SESSION['forum_success'])) unset($_SESSION['forum_success']);
$baseUrl = URL . '/forum';
$uploadBase = rtrim(URL, '/') . '/public/uploads/';
?>
<div class="mobile-content flex-1 overflow-y-auto w-full min-h-0 p-4 md:p-6">
    <div class="w-full max-w-5xl xl:max-w-6xl mx-auto">
        <div class="mb-4">
            <a href="<?= $baseUrl ?>" class="text-indigo-600 hover:text-indigo-700 hover:underline text-sm font-medium">← Voltar ao fórum</a>
        </div>
        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <article class="bg-white rounded-xl shadow border border-gray-200 p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($topic['title']) ?></h1>
            <p class="text-sm text-gray-500 mt-2">
                <?= htmlspecialchars($topic['author_name']) ?>
                · <time datetime="<?= htmlspecialchars($topic['created_at'] ?? '') ?>"><?= !empty($topic['created_at']) ? date('d/m/Y H:i', strtotime($topic['created_at'])) : '' ?></time>
                <?php if (!empty($topic['is_resolved'])): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 ml-2">Resolvido</span>
                <?php endif; ?>
            </p>
            <div class="mt-4 text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($topic['content'] ?? '')) ?></div>
            <?php if (!empty($can_moderate)): ?>
                <form action="<?= $baseUrl ?>/moderation/delete-topic" method="post" class="mt-4 inline" onsubmit="return confirm('Excluir este tópico e todas as respostas?');">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                    <button type="submit" class="text-sm text-red-600 hover:underline">Excluir tópico (moderação)</button>
                </form>
            <?php endif; ?>
            <?php if (!empty($topic_attachments)): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($topic_attachments as $att): ?>
                        <?php $url = $uploadBase . $att['file_path']; $img = in_array(strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']); ?>
                        <?php if ($img): ?>
                            <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener" class="inline-block"><img src="<?= htmlspecialchars($url) ?>" alt="<?= htmlspecialchars($att['file_name']) ?>" class="max-h-32 rounded border border-gray-200"></a>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener" class="inline-flex items-center px-3 py-1 bg-gray-100 rounded-lg text-sm text-gray-700 hover:bg-gray-200"><?= htmlspecialchars($att['file_name']) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <section class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Respostas (<?= count($replies) ?>)</h2>
            <div class="space-y-4">
                <?php foreach ($replies as $r): ?>
                    <div id="reply-<?= (int)$r['id'] ?>" class="bg-white rounded-xl shadow border <?= !empty($r['is_best_answer']) ? 'border-green-400 ring-2 ring-green-100' : 'border-gray-200' ?> p-4">
                        <?php if (!empty($r['is_best_answer'])): ?>
                            <p class="text-sm font-medium text-green-700 mb-2">✓ Melhor resposta</p>
                        <?php endif; ?>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="text-sm text-gray-500">
                                <?= htmlspecialchars($r['author_name']) ?>
                                · <time datetime="<?= htmlspecialchars($r['created_at'] ?? '') ?>"><?= !empty($r['created_at']) ? date('d/m/Y H:i', strtotime($r['created_at'])) : '' ?></time>
                            </p>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-500"><?= (int)($r['vote_score'] ?? 0) ?> voto(s)</span>
                                <form action="<?= $baseUrl ?>/vote" method="post" class="inline">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <input type="hidden" name="reply_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="vote_type" value="upvote">
                                    <button type="submit" class="text-gray-400 hover:text-green-600" title="Votar positivamente">↑</button>
                                </form>
                                <form action="<?= $baseUrl ?>/vote" method="post" class="inline">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <input type="hidden" name="reply_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="vote_type" value="downvote">
                                    <button type="submit" class="text-gray-400 hover:text-red-600" title="Votar negativamente">↓</button>
                                </form>
                                <?php if (!empty($can_mark_best) && empty($r['is_best_answer'])): ?>
                                    <form action="<?= $baseUrl ?>/mark-best" method="post" class="inline">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                        <input type="hidden" name="reply_id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="text-xs text-indigo-600 hover:underline">Marcar como melhor</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!empty($can_moderate)): ?>
                                    <form action="<?= $baseUrl ?>/moderation/delete-reply" method="post" class="inline" onsubmit="return confirm('Excluir esta resposta?');">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                        <input type="hidden" name="reply_id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="text-xs text-red-600 hover:underline">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-2 text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($r['content'] ?? '')) ?></div>
                        <?php if (!empty($r['attachments'])): ?>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <?php foreach ($r['attachments'] as $att): ?>
                                    <?php $url = $uploadBase . $att['file_path']; $img = in_array(strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']); ?>
                                    <?php if ($img): ?>
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars($url) ?>" alt="<?= htmlspecialchars($att['file_name']) ?>" class="max-h-24 rounded border border-gray-200"></a>
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener" class="text-sm text-indigo-600 hover:underline"><?= htmlspecialchars($att['file_name']) ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Sua resposta</h2>
            <form action="<?= $baseUrl ?>/reply" method="post">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                <div class="mb-4">
                    <textarea name="content" required rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500" placeholder="Escreva sua resposta..."></textarea>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm">Enviar resposta</button>
            </form>
        </section>
    </div>
</div>
<script>
(function() {
    var hash = window.location.hash;
    if (hash && hash.indexOf('reply-') !== -1) {
        var id = hash.replace('#reply-', '');
        var el = document.getElementById('reply-' + id);
        if (el) {
            setTimeout(function() { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100);
        }
    }
})();
</script>
