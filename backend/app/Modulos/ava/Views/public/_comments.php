<?php
/**
 * Partial reutilizável: thread de comentários/dúvidas de uma aula.
 * Variáveis esperadas:
 *   $comentarios        (list) árvore [raiz => ['respostas'=>[...]]]
 *   $aulaId             (int)
 *   $csrf               (string já escapado)
 *   $coment_store_url   (string URL completa p/ POST de novo comentário)
 *   $coment_delete_base (string URL base; será concatenado /{id}/excluir)
 *   $coment_can_pin     (bool) professor dono pode fixar
 *   $coment_pin_base    (string URL base p/ fixar; opcional)
 *   $coment_user_id     (int) id do usuário atual
 *   $coment_user_tipo   (string) 'aluno'|'professor'
 *   $coment_permite     (bool) aula permite comentários
 */
$comentarios = $comentarios ?? [];
$aulaId = (int) ($aulaId ?? 0);
$csrf = (string) ($csrf ?? '');
$storeUrl = (string) ($coment_store_url ?? '');
$deleteBase = (string) ($coment_delete_base ?? '');
$canPin = !empty($coment_can_pin);
$pinBase = (string) ($coment_pin_base ?? '');
$uid = (int) ($coment_user_id ?? 0);
$utipo = (string) ($coment_user_tipo ?? '');
$permite = $coment_permite ?? true;

$renderAutor = static function (array $c): string {
    $nome = htmlspecialchars((string) ($c['autor_nome'] ?? 'Usuário'));
    $tag = ($c['autor_tipo'] ?? '') === 'professor'
        ? '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Professor</span>'
        : '';
    return $nome . $tag;
};
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4"><i class="fa-solid fa-comments mr-2 text-gray-400"></i>Dúvidas e comentários</h3>

    <?php if ($permite): ?>
    <form method="post" action="<?= htmlspecialchars($storeUrl) ?>" class="mb-6">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <textarea name="conteudo" rows="3" required placeholder="Escreva uma dúvida ou comentário..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
        <div class="mt-2 flex justify-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700"><i class="fa-solid fa-paper-plane mr-2"></i> Comentar</button>
        </div>
    </form>
    <?php else: ?>
        <p class="mb-4 text-sm text-gray-500">Os comentários estão desativados nesta aula.</p>
    <?php endif; ?>

    <div class="space-y-5">
        <?php if (empty($comentarios)): ?>
            <p class="text-sm text-gray-500">Nenhum comentário ainda. Seja o primeiro a participar!</p>
        <?php else: foreach ($comentarios as $c):
            $cid = (int) $c['id'];
            $podeExcluir = ((int) $c['autor_id'] === $uid && $c['autor_tipo'] === $utipo) || $canPin; ?>
            <div class="border border-gray-100 rounded-lg p-4 <?= !empty($c['fixado']) ? 'bg-amber-50 border-amber-200' : '' ?>">
                <div class="flex items-start justify-between gap-3">
                    <div class="text-sm font-semibold text-gray-900">
                        <?php if (!empty($c['fixado'])): ?><i class="fa-solid fa-thumbtack text-amber-500 mr-1"></i><?php endif; ?>
                        <?= $renderAutor($c) ?>
                        <span class="ml-2 text-xs font-normal text-gray-400"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($c['created_at'] ?? 'now')))) ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($canPin && $pinBase !== ''): ?>
                        <form method="post" action="<?= htmlspecialchars($pinBase . $cid . '/fixar') ?>">
                            <input type="hidden" name="_token" value="<?= $csrf ?>">
                            <button type="submit" class="text-gray-300 hover:text-amber-500" title="<?= !empty($c['fixado']) ? 'Desafixar' : 'Fixar' ?>"><i class="fa-solid fa-thumbtack"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($podeExcluir): ?>
                        <form method="post" action="<?= htmlspecialchars($deleteBase . $cid . '/excluir') ?>" onsubmit="return confirm('Remover comentário?');">
                            <input type="hidden" name="_token" value="<?= $csrf ?>">
                            <button type="submit" class="text-gray-300 hover:text-red-600" title="Remover"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="mt-2 text-sm text-gray-700 whitespace-pre-line"><?= htmlspecialchars((string) $c['conteudo']) ?></p>

                <?php if (!empty($c['respostas'])): ?>
                <div class="mt-3 pl-4 border-l-2 border-gray-100 space-y-3">
                    <?php foreach ($c['respostas'] as $r):
                        $rid = (int) $r['id'];
                        $podeExcluirR = ((int) $r['autor_id'] === $uid && $r['autor_tipo'] === $utipo) || $canPin; ?>
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <div class="text-sm font-semibold text-gray-900"><?= $renderAutor($r) ?><span class="ml-2 text-xs font-normal text-gray-400"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($r['created_at'] ?? 'now')))) ?></span></div>
                                <?php if ($podeExcluirR): ?>
                                <form method="post" action="<?= htmlspecialchars($deleteBase . $rid . '/excluir') ?>" onsubmit="return confirm('Remover resposta?');">
                                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                                    <button type="submit" class="text-gray-300 hover:text-red-600" title="Remover"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <p class="mt-1 text-sm text-gray-700 whitespace-pre-line"><?= htmlspecialchars((string) $r['conteudo']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($permite): ?>
                <details class="mt-2">
                    <summary class="text-xs text-gray-500 cursor-pointer hover:text-green-700">Responder</summary>
                    <form method="post" action="<?= htmlspecialchars($storeUrl) ?>" class="mt-2">
                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                        <input type="hidden" name="parent_id" value="<?= $cid ?>">
                        <textarea name="conteudo" rows="2" required placeholder="Sua resposta..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                        <div class="mt-1 flex justify-end">
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs font-semibold hover:bg-green-700">Responder</button>
                        </div>
                    </form>
                </details>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>
