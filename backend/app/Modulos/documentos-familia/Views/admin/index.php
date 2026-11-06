<?php
$arquivos = is_array($arquivos ?? null) ? $arquivos : [];
$fichas = is_array($fichas ?? null) ? $fichas : [];
$declaracoes = is_array($declaracoes ?? null) ? $declaracoes : [];
$aceites = is_array($aceites ?? null) ? $aceites : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
if (!empty($flash_message)) {
    include \App\Core\ViewPaths::adminPartial('flash_message.php');
}
?>
<div class="mb-6">
    <h2 class="text-lg font-bold text-gray-900">Documentos da família</h2>
    <p class="text-sm text-gray-500">Arquivos enviados, ficha de saúde e declarações aceitas no aplicativo.</p>
</div>
<?php if (empty($pronto)): ?>
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Rode a migração de documentos da família para liberar esta tela.</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="font-bold text-gray-900 mb-3">Nova declaração</h3>
    <form method="post" action="<?= URL ?>/admin/documentos-familia/declaracoes" class="space-y-3">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <input name="titulo" required maxlength="180" placeholder="Título" class="w-full rounded-lg border border-gray-300 px-3 py-2">
        <textarea name="texto" required rows="5" maxlength="8000" placeholder="Texto que a família vai ler e aceitar" class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
        <button class="btn-primary-custom px-4 py-2.5 rounded-lg text-sm font-semibold">Publicar</button>
    </form>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-bold mb-3">Arquivos</h3>
        <?php if ($arquivos === []): ?><p class="text-sm text-gray-500">Nenhum arquivo enviado.</p><?php endif; ?>
        <?php foreach ($arquivos as $item): ?>
            <div class="py-2 border-b border-gray-100">
                <a class="font-semibold text-blue-700" href="<?= URL ?>/admin/documentos-familia/arquivos/<?= (int) ($item['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($item['titulo'] ?? '')) ?></a>
                <p class="text-xs text-gray-500"><?= htmlspecialchars((string) ($item['aluno_nome'] ?? '')) ?> · <?= htmlspecialchars((string) ($item['responsavel_nome'] ?? '')) ?> · <?= !empty($item['created_at']) ? date('d/m/Y H:i', strtotime((string) $item['created_at'])) : '' ?></p>
            </div>
        <?php endforeach; ?>
    </section>
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-bold mb-3">Fichas de saúde</h3>
        <?php if ($fichas === []): ?><p class="text-sm text-gray-500">Nenhuma ficha preenchida.</p><?php endif; ?>
        <?php foreach ($fichas as $item): ?>
            <div class="py-2 border-b border-gray-100">
                <p class="font-semibold text-gray-900"><?= htmlspecialchars((string) ($item['aluno_nome'] ?? '')) ?></p>
                <p class="text-xs text-gray-500">Tipo sanguíneo: <?= htmlspecialchars((string) (($item['tipo_sanguineo'] ?? '') !== '' ? $item['tipo_sanguineo'] : '—')) ?> · <?= !empty($item['atualizado_em']) ? date('d/m/Y H:i', strtotime((string) $item['atualizado_em'])) : '' ?></p>
                <p class="text-xs text-gray-400 break-all">Hash <?= htmlspecialchars((string) ($item['hash_registro'] ?? '')) ?></p>
            </div>
        <?php endforeach; ?>
    </section>
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-bold mb-3">Declarações</h3>
        <?php if ($declaracoes === []): ?><p class="text-sm text-gray-500">Nenhuma declaração publicada.</p><?php endif; ?>
        <?php foreach ($declaracoes as $item): ?>
            <div class="py-2 border-b border-gray-100">
                <p class="font-semibold text-gray-900"><?= htmlspecialchars((string) ($item['titulo'] ?? '')) ?></p>
                <p class="text-xs text-gray-500">Versão <?= (int) ($item['versao'] ?? 1) ?></p>
            </div>
        <?php endforeach; ?>
    </section>
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-bold mb-3">Aceites</h3>
        <?php if ($aceites === []): ?><p class="text-sm text-gray-500">Nenhum aceite registrado.</p><?php endif; ?>
        <?php foreach ($aceites as $item): ?>
            <div class="py-2 border-b border-gray-100">
                <p class="font-semibold text-gray-900"><?= htmlspecialchars((string) ($item['titulo'] ?? '')) ?></p>
                <p class="text-xs text-gray-500"><?= htmlspecialchars((string) ($item['aluno_nome'] ?? '')) ?> · <?= htmlspecialchars((string) ($item['responsavel_nome'] ?? '')) ?> · <?= !empty($item['aceito_em']) ? date('d/m/Y H:i', strtotime((string) $item['aceito_em'])) : '' ?></p>
                <p class="text-xs text-gray-400 break-all">Hash <?= htmlspecialchars((string) ($item['texto_hash'] ?? '')) ?></p>
            </div>
        <?php endforeach; ?>
    </section>
</div>
