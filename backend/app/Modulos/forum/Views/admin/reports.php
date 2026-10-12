<?php
$error = $_SESSION['forum_error'] ?? '';
$success = $_SESSION['forum_success'] ?? '';
if (isset($_SESSION['forum_error'])) unset($_SESSION['forum_error']);
if (isset($_SESSION['forum_success'])) unset($_SESSION['forum_success']);
$baseUrl = URL . '/forum';
$modUrl = URL . '/forum/moderation';
?>
<div class="mobile-content flex-1 overflow-y-auto w-full min-h-0 p-4 md:p-6">
    <div class="w-full max-w-5xl xl:max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Denúncias do Fórum</h1>
                <p class="text-gray-600 mt-1">Aprove ou rejeite denúncias de tópicos e respostas.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= $modUrl ?>/alerts" class="inline-flex items-center px-4 py-2 <?= !empty($alerts_count) ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?> rounded-lg font-medium transition-colors">
                    Alertas IA<?= !empty($alerts_count) ? ' (' . (int)$alerts_count . ')' : '' ?>
                </a>
                <a href="<?= $baseUrl ?>" class="text-indigo-600 hover:text-indigo-700 hover:underline font-medium">← Voltar ao fórum</a>
            </div>
        </div>
    <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (empty($reports)): ?>
        <div class="bg-white rounded-xl shadow border border-gray-200 p-8 text-center text-gray-500">Nenhuma denúncia pendente.</div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($reports as $r): ?>
                <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">#<?= (int)$r['id'] ?> · <?= htmlspecialchars($r['reason']) ?> · <?= !empty($r['created_at']) ? date('d/m/Y H:i', strtotime($r['created_at'])) : '' ?></p>
                    <p class="mt-1 text-gray-700"><?= $r['topic_id'] ? 'Tópico ID ' . (int)$r['topic_id'] : '' ?> <?= $r['reply_id'] ? 'Resposta ID ' . (int)$r['reply_id'] : '' ?></p>
                    <form action="<?= $modUrl ?>/resolve-report" method="post" class="mt-3 inline-flex gap-2">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" class="px-3 py-1.5 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm font-medium transition-colors">Aprovar (remover)</button>
                    </form>
                    <form action="<?= $modUrl ?>/resolve-report" method="post" class="inline">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit" class="px-3 py-1.5 border border-indigo-300 bg-white text-indigo-700 hover:bg-indigo-50 rounded-lg text-sm font-medium transition-colors">Rejeitar</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>
</div>
