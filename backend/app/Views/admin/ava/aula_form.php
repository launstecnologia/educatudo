<?php
$modulo = $modulo ?? null;
$aula = $aula ?? null;
$anexos = $anexos ?? [];
$tipos = $tipos ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$base = rtrim((string) ($base_url ?? '/admin/ava'), '/');
$isEdit = $aula !== null;
$disciplinaId = (int) ($aula['disciplina_id'] ?? ($modulo['disciplina_id'] ?? 0));
$moduloId = (int) ($modulo['id'] ?? ($aula['modulo_id'] ?? 0));
$action = $isEdit ? (URL . $base . '/aulas/' . (int) $aula['id']) : (URL . $base . '/modulos/' . $moduloId . '/aulas');
$val = static fn($k, $d = '') => htmlspecialchars((string) ($aula[$k] ?? $d));
$prov = (string) ($aula['video_provider'] ?? 'none');
$tipo = (string) ($aula['tipo'] ?? 'video');
require_once __DIR__ . '/../../components/wysiwyg.php';
?>

<?php
$page_header_back_url = URL . $base . '/disciplinas/' . $disciplinaId;
$page_header_title = $isEdit ? 'Editar Aula' : 'Nova Aula';
$page_header_subtitle = 'Módulo: ' . (string) ($modulo['titulo'] ?? '');
include __DIR__ . '/../_partials/page_header_form.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form method="POST" action="<?= $action ?>" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= $csrf ?>">

        <section class="p-6 space-y-6">
            <div><h3 class="text-lg font-semibold text-gray-900">Conteúdo da aula</h3></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">Título <span class="text-red-500">*</span></label>
                    <input type="text" id="titulo" name="titulo" required value="<?= $val('titulo') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                    <select id="tipo" name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <?php foreach ($tipos as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>" <?= $tipo === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ordem" class="block text-sm font-medium text-gray-700 mb-2">Ordem</label>
                    <input type="number" id="ordem" name="ordem" value="<?= $val('ordem', '0') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="md:col-span-2"><?php wysiwyg_field(['name' => 'descricao', 'label' => 'Descrição', 'value' => $aula['descricao'] ?? '', 'rows' => 2]); ?></div>
            </div>
        </section>

        <section class="p-6 space-y-6">
            <div><h3 class="text-lg font-semibold text-gray-900">Vídeo</h3><p class="mt-1 text-sm text-gray-500">Para YouTube/Vimeo informe a URL ou o ID. Para MP4 informe a URL do arquivo.</p></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="video_provider" class="block text-sm font-medium text-gray-700 mb-2">Provedor</label>
                    <select id="video_provider" name="video_provider" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="none" <?= $prov === 'none' ? 'selected' : '' ?>>Sem vídeo</option>
                        <option value="youtube" <?= $prov === 'youtube' ? 'selected' : '' ?>>YouTube</option>
                        <option value="vimeo" <?= $prov === 'vimeo' ? 'selected' : '' ?>>Vimeo</option>
                        <option value="mp4" <?= $prov === 'mp4' ? 'selected' : '' ?>>MP4 (URL)</option>
                        <option value="panda" <?= $prov === 'panda' ? 'selected' : '' ?>>Panda</option>
                        <option value="bunny" <?= $prov === 'bunny' ? 'selected' : '' ?>>Bunny</option>
                        <option value="cloudflare" <?= $prov === 'cloudflare' ? 'selected' : '' ?>>Cloudflare</option>
                    </select>
                </div>
                <div>
                    <label for="duracao_seg" class="block text-sm font-medium text-gray-700 mb-2">Duração (segundos)</label>
                    <input type="number" min="0" id="duracao_seg" name="duracao_seg" value="<?= $val('duracao_seg', '0') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="md:col-span-2">
                    <label for="video_ref" class="block text-sm font-medium text-gray-700 mb-2">URL / ID / Embed do vídeo</label>
                    <input type="text" id="video_ref" name="video_ref" value="<?= $val('video_ref') ?>" placeholder="https://youtu.be/..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
            </div>
        </section>

        <section class="p-6 space-y-6">
            <div><h3 class="text-lg font-semibold text-gray-900">Texto / HTML</h3></div>
            <div><?php wysiwyg_field(['name' => 'conteudo_html', 'label' => 'Conteúdo', 'value' => $aula['conteudo_html'] ?? '', 'rows' => 6, 'placeholder' => 'Conteúdo em texto/HTML da aula...']); ?></div>
        </section>

        <section class="p-6 space-y-6">
            <div><h3 class="text-lg font-semibold text-gray-900">Disponibilidade</h3></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="data_liberacao" class="block text-sm font-medium text-gray-700 mb-2">Liberação (opcional)</label>
                    <input type="datetime-local" id="data_liberacao" name="data_liberacao" value="<?= $aula && !empty($aula['data_liberacao']) ? date('Y-m-d\TH:i', strtotime((string) $aula['data_liberacao'])) : '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="data_encerramento" class="block text-sm font-medium text-gray-700 mb-2">Encerramento (opcional)</label>
                    <input type="datetime-local" id="data_encerramento" name="data_encerramento" value="<?= $aula && !empty($aula['data_encerramento']) ? date('Y-m-d\TH:i', strtotime((string) $aula['data_encerramento'])) : '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="obrigatoria" value="1" <?= ($aula['obrigatoria'] ?? 1) ? 'checked' : '' ?> class="mt-0.5 rounded border-gray-300 text-green-600 focus:ring focus:ring-green-200">
                    <span class="text-sm font-medium text-gray-700">Obrigatória (conta na frequência)</span>
                </label>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="permite_comentarios" value="1" <?= ($aula['permite_comentarios'] ?? 1) ? 'checked' : '' ?> class="mt-0.5 rounded border-gray-300 text-green-600 focus:ring focus:ring-green-200">
                    <span class="text-sm font-medium text-gray-700">Permitir comentários</span>
                </label>
            </div>
        </section>

<?php
        $form_cancel_url = URL . $base . '/disciplinas/' . $disciplinaId;
        $form_submit_label = $isEdit ? 'Salvar Alterações' : 'Criar Aula';
        include __DIR__ . '/../_partials/form_actions.php';
        ?>
    </form>
</div>

<?php if ($isEdit): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Materiais e anexos</h3>
    <form method="post" action="<?= URL . $base ?>/aulas/<?= (int) $aula['id'] ?>/anexos" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo</label>
            <input type="file" name="arquivo" class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-green-50 file:text-green-700 file:font-medium hover:file:bg-green-100">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ou Link (URL)</label>
            <input type="text" name="url" placeholder="https://..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>
        <div class="flex items-end">
            <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors"><i class="fa-solid fa-upload mr-2"></i> Adicionar</button>
        </div>
    </form>
    <ul class="divide-y divide-gray-100">
        <?php if (empty($anexos)): ?>
            <li class="py-3 text-sm text-gray-500">Nenhum anexo.</li>
        <?php else: foreach ($anexos as $an): ?>
            <li class="py-2 flex items-center justify-between">
                <span class="text-sm text-gray-700"><i class="fa-solid <?= ($an['tipo'] ?? '') === 'link' ? 'fa-link' : 'fa-paperclip' ?> mr-2 text-gray-400"></i><?= htmlspecialchars((string) ($an['nome'] ?? 'Anexo')) ?></span>
                <form method="post" action="<?= URL . $base ?>/anexos/<?= (int) $an['id'] ?>/excluir" onsubmit="return confirm('Remover anexo?');">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                    <button type="submit" class="text-gray-400 hover:text-red-600"><i class="fa-solid fa-xmark"></i></button>
                </form>
            </li>
        <?php endforeach; endif; ?>
    </ul>
</div>
<?php endif; ?>

<?php
// Moderação de comentários (apenas contexto do professor, que recebe $comentarios).
if ($isEdit && isset($comentarios) && strpos($base, 'professor') !== false):
    $aulaId = (int) $aula['id'];
    $coment_store_url = URL . $base . '/aulas/' . $aulaId . '/comentario';
    $coment_delete_base = URL . $base . '/comentario/';
    $coment_pin_base = URL . $base . '/comentario/';
    $coment_can_pin = true;
    $coment_user_id = (int) ($comentarios_user_id ?? 0);
    $coment_user_tipo = 'professor';
    $coment_permite = !empty($aula['permite_comentarios']);
?>
<div class="mt-6">
    <?php include __DIR__ . '/../../ava/_comments.php'; ?>
</div>
<?php endif; ?>
