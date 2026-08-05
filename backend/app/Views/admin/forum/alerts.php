<?php
$baseUrl = URL . '/forum';
$modUrl = URL . '/forum/moderation';
$alerts = $alerts ?? [];
?>
<div class="mobile-content flex-1 overflow-y-auto w-full min-h-0 p-4 md:p-6">
    <div class="w-full max-w-5xl xl:max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Alertas da IA (moderação)</h1>
                <p class="text-gray-600 mt-1">Conteúdo bloqueado por estar fora de contexto ou inadequado. Nenhum conteúdo foi publicado.</p>
            </div>
            <a href="<?= $modUrl ?>/reports" class="text-indigo-600 hover:text-indigo-700 hover:underline font-medium">← Denúncias</a>
        </div>
        <?php if (empty($alerts)): ?>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-8 text-center text-gray-500">Nenhum alerta pendente.</div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($alerts as $a): ?>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <p class="text-sm font-medium text-amber-900"><?= $a['tipo'] === 'reply' ? 'Resposta' : 'Pergunta' ?> bloqueada · <?= !empty($a['created_at']) ? date('d/m/Y H:i', strtotime($a['created_at'])) : '' ?></p>
                        <p class="text-sm text-amber-800 mt-1">Autor: <?= htmlspecialchars($a['author_role']) ?> ID <?= (int)$a['author_id'] ?></p>
                        <p class="text-sm text-gray-700 mt-2 font-medium">Motivo IA:</p>
                        <p class="text-sm text-gray-700 mt-0.5"><?= nl2br(htmlspecialchars($a['motivo_ia'] ?? '')) ?></p>
                        <p class="text-sm text-gray-600 mt-2 truncate" title="<?= htmlspecialchars($a['content_preview'] ?? '') ?>"><?= htmlspecialchars($a['content_preview'] ?? '') ?></p>
                        <form action="<?= $modUrl ?>/alerts/mark-seen" method="post" class="mt-3 inline">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="px-3 py-1.5 border border-amber-600 text-amber-700 hover:bg-amber-100 rounded-lg text-sm font-medium transition-colors">Marcar como visto</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
