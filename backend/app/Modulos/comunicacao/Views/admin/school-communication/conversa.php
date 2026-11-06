<?php
$conversa = is_array($conversa ?? null) ? $conversa : [];
$mensagens = is_array($mensagens ?? null) ? $mensagens : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
?>
<div class="mb-6">
    <a href="<?= URL ?>/admin/comunicacao-escolar/fale-com-a-escola" class="text-sm text-gray-600 hover:text-gray-900">← Fale com a Escola</a>
</div>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900"><?= htmlspecialchars((string) ($conversa['aluno_nome'] ?? 'Aluno')) ?></h2>
    <p class="text-sm text-gray-500">Responsável: <?= htmlspecialchars((string) ($conversa['responsavel_nome'] ?? '')) ?></p>
    <div class="mt-6 space-y-3">
        <?php if ($mensagens === []): ?>
            <p class="text-gray-500">Nenhuma mensagem nesta conversa.</p>
        <?php endif; ?>
        <?php foreach ($mensagens as $mensagem): ?>
            <?php $daEscola = ($mensagem['autor_tipo'] ?? '') === 'escola'; ?>
            <div class="max-w-xl rounded-xl px-4 py-3 <?= $daEscola ? 'ml-auto bg-blue-50' : 'bg-gray-100' ?>">
                <p class="text-xs font-bold text-gray-500"><?= $daEscola ? 'Escola' : 'Família' ?></p>
                <?php if (trim((string) ($mensagem['mensagem'] ?? '')) !== ''): ?>
                    <p class="mt-1 text-gray-900"><?= nl2br(htmlspecialchars((string) $mensagem['mensagem'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($mensagem['imagem'])): ?>
                    <a href="<?= htmlspecialchars((string) $mensagem['imagem']) ?>" target="_blank" rel="noopener">
                        <img src="<?= htmlspecialchars((string) $mensagem['imagem']) ?>" alt="Imagem da conversa" class="mt-2 max-h-56 rounded-lg">
                    </a>
                <?php endif; ?>
                <p class="mt-1 text-xs text-gray-400"><?= !empty($mensagem['created_at']) ? date('d/m/Y H:i', strtotime((string) $mensagem['created_at'])) : '' ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?= URL ?>/admin/comunicacao-escolar/fale-com-a-escola/<?= (int) ($conversa['id'] ?? 0) ?>/responder" class="mt-6 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <label class="flex-1 min-w-[16rem] text-sm text-gray-700">
            Resposta
            <textarea name="mensagem" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Escreva para a família"></textarea>
        </label>
        <label class="text-sm text-gray-700">
            Imagem
            <input type="file" name="imagem" accept="image/jpeg,image/png,image/webp" class="mt-1 block text-sm">
        </label>
        <button class="btn-primary-custom px-4 py-2.5 rounded-lg text-sm font-semibold">Enviar</button>
    </form>
</div>
