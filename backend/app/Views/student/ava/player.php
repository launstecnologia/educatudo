<?php
$aula = $aula ?? [];
$embed = $embed ?? ['type' => 'none', 'src' => ''];
$anexos = $anexos ?? [];
$atividades = $atividades ?? [];
$comentarios = $comentarios ?? [];
$prev = $prev ?? null;
$next = $next ?? null;
$progresso_video = $progresso_video ?? null;
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$aulaId = (int) ($aula['id'] ?? 0);
$disciplinaId = (int) ($aula['disciplina_id'] ?? 0);
$retomar = (int) ($progresso_video['segundo_atual'] ?? 0);
require_once __DIR__ . '/../../../Helpers/RichTextHelper.php';
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/cursos/disciplina/<?= $disciplinaId ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars((string) $aula['titulo']) ?></h2>
            <p class="text-sm text-gray-600"><?= htmlspecialchars((string) ($aula['disciplina_nome'] ?? '')) ?> · <?= htmlspecialchars((string) ($aula['modulo_titulo'] ?? '')) ?></p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-black rounded-xl overflow-hidden shadow-sm">
            <?php if ($embed['type'] === 'video'): ?>
                <video id="avaVideo" class="w-full aspect-video" controls preload="metadata" src="<?= htmlspecialchars((string) $embed['src']) ?>"></video>
            <?php elseif ($embed['type'] === 'iframe'): ?>
                <div class="aspect-video"><iframe class="w-full h-full" src="<?= htmlspecialchars((string) $embed['src']) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>
            <?php else: ?>
                <div class="aspect-video flex items-center justify-center text-gray-400 text-sm">Esta aula não possui vídeo.</div>
            <?php endif; ?>
        </div>

        <?php if (!empty($aula['descricao'])): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-sm">
                <?php rich_text((string) $aula['descricao']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($aula['conteudo_html'])): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-sm">
                <?php rich_text((string) $aula['conteudo_html']); ?>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between">
            <?php if ($prev): ?>
                <a href="<?= URL ?>/cursos/aula/<?= (int) $prev['id'] ?>" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-left mr-2"></i> Anterior</a>
            <?php else: ?><span></span><?php endif; ?>

            <form method="post" action="<?= URL ?>/cursos/aula/<?= $aulaId ?>/concluir">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <?php if ($next): ?><input type="hidden" name="proximo" value="<?= (int) $next['id'] ?>"><?php endif; ?>
                <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 shadow-sm">
                    <i class="fa-solid fa-check mr-2"></i> <?= $next ? 'Concluir e avançar' : 'Concluir aula' ?>
                </button>
            </form>
        </div>

        <?php if (!empty($atividades)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4"><i class="fa-solid fa-clipboard-list mr-2 text-gray-400"></i>Atividades desta aula</h3>
            <ul class="space-y-2">
                <?php foreach ($atividades as $atv): ?>
                <li>
                    <a href="<?= URL ?>/cursos/atividade/<?= (int) $atv['id'] ?>" class="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:border-green-300 hover:bg-green-50/40">
                        <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars((string) $atv['titulo']) ?></span>
                        <i class="fa-solid fa-chevron-right text-gray-300"></i>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($aula['permite_comentarios']) || !empty($comentarios)):
            $coment_store_url = URL . '/cursos/aula/' . $aulaId . '/comentario';
            $coment_delete_base = URL . '/cursos/comentario/';
            $coment_can_pin = false;
            $coment_user_tipo = 'aluno';
            $coment_permite = !empty($aula['permite_comentarios']);
            $user = (new AuthManager())->getUser();
            $coment_user_id = (int) ($user['id'] ?? 0);
            include __DIR__ . '/../../ava/_comments.php';
        endif; ?>
    </div>

    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Materiais</h3>
            <ul class="space-y-2">
                <?php if (empty($anexos)): ?>
                    <li class="text-sm text-gray-500">Nenhum material para esta aula.</li>
                <?php else: foreach ($anexos as $an): ?>
                    <li>
                        <?php $href = !empty($an['url']) ? (string) $an['url'] : (URL . '/cursos/anexo/' . (int) $an['id']); ?>
                        <a href="<?= htmlspecialchars($href) ?>" target="_blank" rel="noopener" class="flex items-center gap-2 text-sm text-gray-700 hover:text-green-700">
                            <i class="fa-solid <?= ($an['tipo'] ?? '') === 'link' ? 'fa-link' : 'fa-paperclip' ?> text-gray-400"></i>
                            <span class="truncate"><?= htmlspecialchars((string) ($an['nome'] ?? 'Material')) ?></span>
                        </a>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php if ($embed['type'] === 'video'): ?>
<script>
(function () {
    var video = document.getElementById('avaVideo');
    if (!video) return;
    var url = '<?= URL ?>/api/ava/aula/<?= $aulaId ?>/progresso-video';
    var token = '<?= $csrf ?>';
    var retomar = <?= $retomar ?>;
    var assistido = 0;
    var ultimo = 0;
    var enviando = false;

    video.addEventListener('loadedmetadata', function () {
        if (retomar > 0 && retomar < video.duration - 5) {
            try { video.currentTime = retomar; } catch (e) {}
        }
    });

    video.addEventListener('timeupdate', function () {
        if (video.currentTime - ultimo > 0 && video.currentTime - ultimo < 2) {
            assistido += (video.currentTime - ultimo);
        }
        ultimo = video.currentTime;
    });

    function enviar() {
        if (enviando || !video.duration) return;
        enviando = true;
        var body = new URLSearchParams();
        body.append('_token', token);
        body.append('segundo_atual', Math.floor(video.currentTime));
        body.append('tempo_assistido', Math.floor(assistido));
        body.append('duracao', Math.floor(video.duration));
        fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .catch(function () {})
            .finally(function () { enviando = false; });
    }

    setInterval(enviar, 15000);
    video.addEventListener('pause', enviar);
    video.addEventListener('ended', enviar);
    window.addEventListener('beforeunload', enviar);
})();
</script>
<?php endif; ?>
