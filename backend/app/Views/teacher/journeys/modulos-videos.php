<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
.quill-descricao-wrap .ql-toolbar.ql-snow { border: 1px solid #e5e7eb; border-bottom: none; border-radius: 8px 8px 0 0; background: #f9fafb; padding: 6px 8px; }
.quill-descricao-wrap .ql-container.ql-snow { border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; }
.quill-descricao-wrap .ql-editor { min-height: 120px; }
#preview-youtube, #preview-video-upload, #preview-documento { max-width: 100%; }
</style>
<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                <?= (isset($is_dica_professor) && $is_dica_professor) ? '💡 Gerenciar Dica do Professor' : 'Gerenciar' ?> - <?= htmlspecialchars($modulo['titulo'] ?? 'Módulo') ?>
            </h2>
            <p class="text-gray-600">
                Jornada: <?= htmlspecialchars($jornada['titulo']) ?> • 
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/professor/jornadas/<?= $modulo['jornada_id'] ?>/modulos" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<?php
$textos = $textos ?? [];
$videos = $videos ?? [];
$documentos = $documentos ?? [];
$isDica = isset($is_dica_professor) && $is_dica_professor;
$temUmVideo = count($videos) === 1 && empty($documentos) && empty($textos);
$temUmDoc = count($documentos) === 1 && empty($videos) && empty($textos);
$temUmTexto = count($textos) === 1 && empty($videos) && empty($documentos) && $isDica;
$temConteudo = $temUmVideo || $temUmDoc || $temUmTexto;
$conteudoAtual = $temUmVideo ? $videos[0] : ($temUmDoc ? $documentos[0] : ($temUmTexto ? $textos[0] : null));
$tipoAtual = $temUmVideo ? ($videos[0]['tipo'] ?? 'youtube') : ($temUmDoc ? 'documento' : ($temUmTexto ? 'texto' : null));
$tipoConteudoOption = $temUmVideo ? (($videos[0]['tipo'] ?? 'youtube') === 'link_externo' ? 'link_externo' : (($videos[0]['tipo'] ?? '') === 'upload' ? 'upload_video' : 'youtube')) : ($temUmDoc ? 'upload_documento' : ($temUmTexto ? 'nenhum' : 'nenhum'));
?>
<!-- Um conteúdo por bloco: card com conteúdo atual ou formulário único -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-blue-200">
    <?php if ($temConteudo && $conteudoAtual): ?>
        <!-- Card do conteúdo existente -->
        <div id="card-conteudo-atual" class="border border-gray-200 rounded-lg p-4 bg-gray-50">
            <div class="flex justify-between items-start gap-4">
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 text-lg"><?= htmlspecialchars($conteudoAtual['titulo'] ?? 'Sem título') ?></h3>
                    <?php if ($temUmVideo): ?>
                        <p class="text-sm text-gray-600 mt-1">
                            <?= $tipoAtual === 'youtube' ? 'Vídeo por link (YouTube)' : ($tipoAtual === 'link_externo' ? 'Link externo' : 'Upload de vídeo') ?>
                        </p>
                    <?php elseif ($temUmDoc): ?>
                        <p class="text-sm text-gray-600 mt-1">Documento/Imagem</p>
                        <?php if (!empty($conteudoAtual['arquivo_nome'])): ?>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($conteudoAtual['arquivo_nome']) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-sm text-gray-600 mt-1">Texto</p>
                    <?php endif; ?>
                    <?php if (!empty($conteudoAtual['descricao']) || (!empty($conteudoAtual['conteudo']) && $temUmTexto)): ?>
                        <div class="text-sm text-gray-600 mt-2 prose prose-sm max-w-none">
                            <?= strlen(strip_tags($conteudoAtual['descricao'] ?? $conteudoAtual['conteudo'] ?? '')) > 120 ? substr(strip_tags($conteudoAtual['descricao'] ?? $conteudoAtual['conteudo'] ?? ''), 0, 120) . '...' : strip_tags($conteudoAtual['descricao'] ?? $conteudoAtual['conteudo'] ?? '') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex gap-2 shrink-0">
                    <button type="button" onclick="mostrarFormConteudo()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Editar</button>
                    <?php if ($temUmVideo): ?>
                        <button type="button" onclick="removerVideo(<?= (int)$conteudoAtual['id'] ?>)" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600">Remover</button>
                    <?php elseif ($temUmDoc): ?>
                        <button type="button" onclick="removerDocumento(<?= (int)$conteudoAtual['id'] ?>)" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600">Remover</button>
                    <?php else: ?>
                        <button type="button" onclick="removerTexto(<?= (int)$conteudoAtual['id'] ?>)" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600">Remover</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div id="form-conteudo-wrap" class="hidden mt-6">
    <?php else: ?>
        <div id="form-conteudo-wrap">
    <?php endif; ?>
    
    <!-- Formulário único: Título, Descrição, Tipo, URL ou arquivo -->
    <form id="formConteudoUnico" class="space-y-4" enctype="multipart/form-data">
        <input type="hidden" name="modulo_id" value="<?= (int)$modulo['id'] ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
            <input type="text" name="titulo" id="conteudo-titulo" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Ex: Aula sobre Equações">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Descrição / Notas</label>
            <input type="hidden" name="descricao" id="descricao-hidden" value="">
            <div id="quill-descricao-wrapper" class="quill-descricao-wrap border border-gray-300 rounded-lg bg-white">
                <div id="quill-descricao-toolbar" class="ql-toolbar ql-snow">
                    <span class="ql-formats">
                        <button type="button" class="ql-bold" title="Negrito"></button>
                        <button type="button" class="ql-italic" title="Itálico"></button>
                        <button type="button" class="ql-underline" title="Sublinhado"></button>
                    </span>
                    <span class="ql-formats">
                        <button type="button" class="ql-list" value="ordered" title="Lista numerada"></button>
                        <button type="button" class="ql-list" value="bullet" title="Lista com marcadores"></button>
                    </span>
                    <span class="ql-formats">
                        <button type="button" class="ql-link" title="Link"></button>
                    </span>
                </div>
                <div id="quill-descricao-editor" style="min-height: 140px;"></div>
            </div>
            <p class="text-xs text-gray-500 mt-1">Use a barra de ferramentas para formatar. Para tipo "Nenhum", só esta descrição será exibida ao aluno.</p>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de documento *</label>
            <select name="tipo_conteudo" id="tipo-conteudo" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="nenhum">Nenhum (só descrição acima)</option>
                <option value="youtube">Vídeo por link (YouTube)</option>
                <option value="upload_video">Upload de vídeo</option>
                <option value="upload_documento">Documento/Imagem</option>
                <option value="link_externo">Link externo</option>
                <?php if ($isDica): ?>
                <option value="texto">Texto (área de conteúdo)</option>
                <?php endif; ?>
            </select>
        </div>
        
        <div id="container-url" class="space-y-2 hidden">
            <label class="block text-sm font-medium text-gray-700" id="label-url">URL</label>
            <input type="url" name="url_youtube" id="conteudo-url"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="https://...">
            <p class="text-xs text-gray-500" id="hint-url">Cole o link do YouTube ou do link externo.</p>
            <div id="preview-youtube" class="mt-2 hidden"></div>
        </div>
        
        <div id="container-upload-video" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo de vídeo *</label>
            <div id="dropzone-video" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors cursor-pointer">
                <input type="file" name="arquivo_video" id="input-video" accept="video/*" class="hidden">
                <p class="text-gray-500" id="dropzone-video-text">Arraste o vídeo aqui ou clique para selecionar</p>
            </div>
            <div id="preview-video-upload" class="mt-2 hidden"></div>
        </div>
        
        <div id="container-upload-documento" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo de documento *</label>
            <div id="dropzone-documento" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors cursor-pointer">
                <input type="file" name="arquivo" id="input-documento" accept=".pdf,.doc,.docx,.ppt,.pptx,.odt,.ods,image/*" class="hidden">
                <p class="text-gray-500" id="dropzone-documento-text">Arraste documento, imagem ou PDF aqui ou clique para selecionar</p>
            </div>
            <div id="preview-documento" class="mt-2 hidden"></div>
        </div>
        
        <div id="container-texto" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">Conteúdo em texto</label>
            <textarea name="conteudo" id="conteudo-texto" rows="6"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Escreva o conteúdo ou dica..."></textarea>
        </div>

        <!-- Título repetido perto do botão Salvar (evita salvar sem ver o campo do topo) -->
        <div class="rounded-xl border border-amber-200 bg-amber-50/90 p-4 space-y-2 mt-6" id="bloco-titulo-rodape">
            <p class="text-sm font-semibold text-amber-900">Antes de salvar — confira o título</p>
            <p class="text-xs text-amber-800">O mesmo título aparece no topo da página; os dois campos ficam sincronizados.</p>
            <label for="conteudo-titulo-rodape" class="block text-sm font-medium text-gray-800">Título do conteúdo *</label>
            <input type="text" id="conteudo-titulo-rodape" autocomplete="off"
                   class="w-full px-3 py-2 border border-amber-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white"
                   placeholder="Ex: Aula sobre MRUV">
            <p class="text-xs text-amber-900/80"><strong>Vídeos grandes:</strong> se o envio falhar, o servidor pode ter limite baixo (PHP). Comprima o MP4 ou peça à escola para aumentar <code class="text-xs bg-white/80 px-1 rounded">upload_max_filesize</code> e <code class="text-xs bg-white/80 px-1 rounded">post_max_size</code>.</p>
        </div>
        
        <button type="submit" class="mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            Salvar conteúdo
        </button>
    </form>
    </div>
</div>
<?php if ($temConteudo && $conteudoAtual): ?>
<script>
window.conteudoAtualEdicao = <?= json_encode($conteudoAtual) ?>;
window.tipoConteudoOptionEdicao = '<?= htmlspecialchars($tipoConteudoOption) ?>';
</script>
<?php endif; ?>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var URL_BASE = '<?= URL ?>';
var CSRF = '<?= htmlspecialchars($csrf_token) ?>';
var JORNADA_ID = <?= (int)($modulo['jornada_id'] ?? 0) ?>;
var quillDescricao = null;
function redirecionarParaBlocos() {
    if (JORNADA_ID) window.location.href = URL_BASE + '/professor/jornadas/' + JORNADA_ID + '/modulos';
    else location.reload();
}

if (typeof Quill !== 'undefined') {
    quillDescricao = new Quill('#quill-descricao-editor', {
        theme: 'snow',
        modules: {
            toolbar: '#quill-descricao-toolbar'
        }
    });
    var descricaoHidden = document.getElementById('descricao-hidden');
    if (descricaoHidden) {
        quillDescricao.on('text-change', function() { descricaoHidden.value = quillDescricao.root.innerHTML; });
    }
}
window.quillDescricao = quillDescricao;

function syncDescricaoQuill() {
    if (quillDescricao && document.getElementById('descricao-hidden')) {
        document.getElementById('descricao-hidden').value = quillDescricao.root.innerHTML || '';
    }
}

function hasBase64Image(html) {
    if (!html) return false;
    return /<img[^>]+src\s*=\s*["']\s*data:image\//i.test(html);
}

function wireTituloSync() {
    var t = document.getElementById('conteudo-titulo');
    var f = document.getElementById('conteudo-titulo-rodape');
    if (!t || !f || f.dataset.wired === '1') return;
    f.dataset.wired = '1';
    var syncing = false;
    function sync(from, to) {
        if (syncing) return;
        syncing = true;
        to.value = from.value;
        syncing = false;
    }
    t.addEventListener('input', function() { sync(t, f); });
    f.addEventListener('input', function() { sync(f, t); });
}

function focarTituloConteudo() {
    var f = document.getElementById('conteudo-titulo-rodape');
    var t = document.getElementById('conteudo-titulo');
    var el = f || t;
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.focus();
    }
}

function mostrarFormConteudo() {
    var card = document.getElementById('card-conteudo-atual');
    var wrap = document.getElementById('form-conteudo-wrap');
    if (card) card.classList.add('hidden');
    if (wrap) wrap.classList.remove('hidden');
    if (window.conteudoAtualEdicao && window.tipoConteudoOptionEdicao) {
        var c = window.conteudoAtualEdicao;
        document.getElementById('conteudo-titulo').value = c.titulo || '';
        var rod = document.getElementById('conteudo-titulo-rodape');
        if (rod) rod.value = c.titulo || '';
        var descricaoConteudo = (c.descricao || c.conteudo || '');
        if (quillDescricao) quillDescricao.root.innerHTML = descricaoConteudo;
        document.getElementById('descricao-hidden').value = descricaoConteudo;
        document.getElementById('tipo-conteudo').value = window.tipoConteudoOptionEdicao;
        document.getElementById('conteudo-url').value = (c.url_youtube || '');
        document.getElementById('conteudo-texto').value = (c.conteudo || '');
        toggleTipoConteudo();
    }
}

function toggleTipoConteudo() {
    var tipo = document.getElementById('tipo-conteudo').value;
    document.getElementById('container-url').classList.add('hidden');
    document.getElementById('container-upload-video').classList.add('hidden');
    document.getElementById('container-upload-documento').classList.add('hidden');
    document.getElementById('container-texto').classList.add('hidden');
    document.getElementById('conteudo-url').required = false;
    document.getElementById('input-video').required = false;
    document.getElementById('input-documento').required = false;
    document.getElementById('preview-youtube').classList.add('hidden');
    document.getElementById('preview-youtube').innerHTML = '';
    document.getElementById('preview-video-upload').classList.add('hidden');
    document.getElementById('preview-video-upload').innerHTML = '';
    document.getElementById('preview-documento').classList.add('hidden');
    document.getElementById('preview-documento').innerHTML = '';
    var labelUrl = document.getElementById('label-url');
    var hintUrl = document.getElementById('hint-url');
    if (tipo === 'nenhum') {
        // Só descrição (Quill) será exibida ao aluno
    } else if (tipo === 'youtube') {
        document.getElementById('container-url').classList.remove('hidden');
        document.getElementById('conteudo-url').placeholder = 'https://www.youtube.com/watch?v=...';
        labelUrl.textContent = 'URL do YouTube';
        hintUrl.textContent = 'Cole o link completo do vídeo do YouTube.';
        document.getElementById('conteudo-url').required = true;
        atualizarPreviewYoutube();
    } else if (tipo === 'link_externo') {
        document.getElementById('container-url').classList.remove('hidden');
        document.getElementById('conteudo-url').placeholder = 'https://...';
        labelUrl.textContent = 'Link externo';
        hintUrl.textContent = 'O aluno verá um botão para abrir este link.';
        document.getElementById('conteudo-url').required = true;
    } else if (tipo === 'upload_video') {
        document.getElementById('container-upload-video').classList.remove('hidden');
        document.getElementById('input-video').required = true;
        atualizarPreviewVideo();
    } else if (tipo === 'upload_documento') {
        document.getElementById('container-upload-documento').classList.remove('hidden');
        var temArquivoAtual = window.conteudoAtualEdicao && window.conteudoAtualEdicao.arquivo_nome;
        document.getElementById('input-documento').required = !temArquivoAtual;
        var txtDrop = document.getElementById('dropzone-documento-text');
        if (temArquivoAtual) {
            txtDrop.textContent = 'Arquivo atual: ' + (window.conteudoAtualEdicao.arquivo_nome || '') + ' (ou selecione outro para substituir)';
        } else {
            txtDrop.textContent = 'Arraste documento, imagem ou PDF aqui ou clique para selecionar';
        }
        atualizarPreviewDocumento();
    } else if (tipo === 'texto') {
        document.getElementById('container-texto').classList.remove('hidden');
    }
}
function atualizarPreviewYoutube() {
    var url = (document.getElementById('conteudo-url').value || '').trim();
    var pre = document.getElementById('preview-youtube');
    var m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/);
    if (m && m[1]) {
        pre.innerHTML = '<p class="text-xs text-gray-600 mb-1">Preview:</p><div class="aspect-video max-w-md rounded overflow-hidden bg-gray-200"><iframe class="w-full h-full" src="https://www.youtube.com/embed/' + m[1] + '" frameborder="0" allowfullscreen></iframe></div>';
        pre.classList.remove('hidden');
    } else {
        pre.innerHTML = '';
        pre.classList.add('hidden');
    }
}
function atualizarPreviewVideo() {
    var input = document.getElementById('input-video');
    var pre = document.getElementById('preview-video-upload');
    if (input.files && input.files[0]) {
        var url = URL.createObjectURL(input.files[0]);
        pre.innerHTML = '<p class="text-xs text-gray-600 mb-1">Preview:</p><video src="' + url + '" controls class="max-w-md rounded border border-gray-200"></video>';
        pre.classList.remove('hidden');
    } else {
        pre.innerHTML = '';
        pre.classList.add('hidden');
    }
}
function atualizarPreviewDocumento() {
    var input = document.getElementById('input-documento');
    var pre = document.getElementById('preview-documento');
    if (input.files && input.files[0]) {
        var f = input.files[0];
        var isPdf = f.type === 'application/pdf';
        var isImg = (f.type || '').indexOf('image/') === 0;
        var html = '<p class="text-xs text-gray-600 mb-1">Arquivo: <strong>' + (f.name || '') + '</strong></p>';
        if (isPdf) {
            var url = URL.createObjectURL(f);
            html += '<iframe src="' + url + '" class="w-full rounded border border-gray-200" style="height: 320px;"></iframe>';
        } else if (isImg) {
            var url = URL.createObjectURL(f);
            html += '<img src="' + url + '" alt="Preview" class="max-w-md rounded border border-gray-200">';
        }
        pre.innerHTML = html;
        pre.classList.remove('hidden');
    } else if (window.conteudoAtualEdicao && window.conteudoAtualEdicao.arquivo_nome) {
        var c = window.conteudoAtualEdicao;
        var urlExistente = (c.url_arquivo || '').trim();
        var tipoArquivo = (c.tipo_arquivo || '').toLowerCase();
        var isImgExistente = tipoArquivo.indexOf('image/') === 0;
        var isPdfExistente = tipoArquivo === 'application/pdf' || (c.arquivo_nome || '').toLowerCase().endsWith('.pdf');
        var html = '<p class="text-xs text-gray-600 mb-1">Arquivo atual: <strong>' + (c.arquivo_nome || '') + '</strong></p>';
        if (urlExistente && isImgExistente) {
            html += '<img src="' + urlExistente.replace(/"/g, '&quot;') + '" alt="Documento atual" class="max-w-md rounded border border-gray-200">';
        } else if (urlExistente && isPdfExistente) {
            html += '<iframe src="' + urlExistente.replace(/"/g, '&quot;') + '" class="w-full rounded border border-gray-200" style="height: 320px;"></iframe>';
        } else if (urlExistente) {
            html += '<a href="' + urlExistente.replace(/"/g, '&quot;') + '" target="_blank" class="text-blue-600 hover:underline">Abrir arquivo</a>';
        }
        pre.innerHTML = html;
        pre.classList.remove('hidden');
    } else {
        pre.innerHTML = '';
        pre.classList.add('hidden');
    }
}

document.getElementById('tipo-conteudo').addEventListener('change', toggleTipoConteudo);
document.getElementById('conteudo-url').addEventListener('input', function() { if (document.getElementById('tipo-conteudo').value === 'youtube') atualizarPreviewYoutube(); });
document.getElementById('conteudo-url').addEventListener('paste', function() { setTimeout(function() { if (document.getElementById('tipo-conteudo').value === 'youtube') atualizarPreviewYoutube(); }, 50); });

var inputVideo = document.getElementById('input-video');
var inputDoc = document.getElementById('input-documento');

function setInputFilesFromDrop(inputEl, files) {
    if (!inputEl || !files || !files.length) return;
    try {
        var dt = new DataTransfer();
        dt.items.add(files[0]);
        inputEl.files = dt.files;
    } catch (err) {
        try { inputEl.files = files; } catch (e2) {}
    }
}

var dropzoneVideo = document.getElementById('dropzone-video');
if (dropzoneVideo && inputVideo) {
    dropzoneVideo.addEventListener('click', function() { inputVideo.click(); });
    dropzoneVideo.addEventListener('dragover', function(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; dropzoneVideo.classList.add('border-blue-500', 'bg-blue-50'); });
    dropzoneVideo.addEventListener('dragleave', function() { dropzoneVideo.classList.remove('border-blue-500', 'bg-blue-50'); });
    dropzoneVideo.addEventListener('drop', function(e) {
        e.preventDefault();
        dropzoneVideo.classList.remove('border-blue-500', 'bg-blue-50');
        if (e.dataTransfer.files.length) {
            setInputFilesFromDrop(inputVideo, e.dataTransfer.files);
            document.getElementById('dropzone-video-text').textContent = e.dataTransfer.files[0].name;
            atualizarPreviewVideo();
        }
    });
    inputVideo.addEventListener('change', function() {
        document.getElementById('dropzone-video-text').textContent = this.files.length ? this.files[0].name : 'Arraste o vídeo aqui ou clique para selecionar';
        atualizarPreviewVideo();
    });
}

var dropzoneDoc = document.getElementById('dropzone-documento');
if (dropzoneDoc && inputDoc) {
    dropzoneDoc.addEventListener('click', function() { inputDoc.click(); });
    dropzoneDoc.addEventListener('dragover', function(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; dropzoneDoc.classList.add('border-blue-500', 'bg-blue-50'); });
    dropzoneDoc.addEventListener('dragleave', function() { dropzoneDoc.classList.remove('border-blue-500', 'bg-blue-50'); });
    dropzoneDoc.addEventListener('drop', function(e) {
        e.preventDefault();
        dropzoneDoc.classList.remove('border-blue-500', 'bg-blue-50');
        if (e.dataTransfer.files.length) {
            setInputFilesFromDrop(inputDoc, e.dataTransfer.files);
            document.getElementById('dropzone-documento-text').textContent = e.dataTransfer.files[0].name;
            atualizarPreviewDocumento();
        }
    });
    inputDoc.addEventListener('change', function() {
        document.getElementById('dropzone-documento-text').textContent = this.files.length ? this.files[0].name : 'Arraste documento, imagem ou PDF aqui ou clique para selecionar';
        atualizarPreviewDocumento();
    });
}

toggleTipoConteudo();
wireTituloSync();
// Ao abrir "Gerenciar", já exibir o formulário de edição (em vez do card)
if (window.conteudoAtualEdicao && window.tipoConteudoOptionEdicao) {
    mostrarFormConteudo();
}

document.getElementById('formConteudoUnico').addEventListener('submit', function(e) {
    e.preventDefault();
    syncDescricaoQuill();
    var tipo = document.getElementById('tipo-conteudo').value;
    var tituloTop = document.getElementById('conteudo-titulo').value.trim();
    var tituloRod = (document.getElementById('conteudo-titulo-rodape') && document.getElementById('conteudo-titulo-rodape').value.trim()) || '';
    var titulo = tituloTop || tituloRod;
    if (tituloTop && !tituloRod) document.getElementById('conteudo-titulo-rodape').value = tituloTop;
    if (tituloRod && !tituloTop) document.getElementById('conteudo-titulo').value = tituloRod;
    titulo = document.getElementById('conteudo-titulo').value.trim();
    var descricao = document.getElementById('descricao-hidden').value;
    var url = document.getElementById('conteudo-url').value.trim();

    if (!titulo) {
        alert('Título é obrigatório. Preencha o campo no topo ou no bloco amarelo acima de Salvar conteúdo.');
        focarTituloConteudo();
        return;
    }
    if ((tipo === 'nenhum' || tipo === 'texto') && hasBase64Image(descricao)) {
        alert('Imagens coladas no texto (base64) não são suportadas para salvar a dica. Remova a imagem do editor e use upload/link.');
        return;
    }
    if (['youtube', 'link_externo'].indexOf(tipo) >= 0 && !url) {
        alert('Informe a URL.');
        return;
    }
    if (tipo === 'upload_video' && (!inputVideo.files || !inputVideo.files.length)) {
        alert('Selecione um arquivo de vídeo.');
        return;
    }
    var editandoDocSemNovoArquivo = tipo === 'upload_documento' && (!inputDoc.files || !inputDoc.files.length) && window.conteudoAtualEdicao && window.conteudoAtualEdicao.id && window.conteudoAtualEdicao.arquivo_nome;
    if (tipo === 'upload_documento' && (!inputDoc.files || !inputDoc.files.length) && !editandoDocSemNovoArquivo) {
        alert('Selecione um arquivo de documento.');
        return;
    }

    if (tipo === 'nenhum') {
        var formData = new FormData();
        formData.append('modulo_id', document.querySelector('input[name="modulo_id"]').value);
        formData.append('_token', CSRF);
        formData.append('titulo', titulo);
        formData.append('conteudo', descricao || '');
        fetch(URL_BASE + '/professor/jornadas/modulos/adicionar-texto', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.success) { alert('Conteúdo salvo!'); redirecionarParaBlocos(); } else alert('Erro: ' + (data.error || '')); })
            .catch(function() { alert('Erro de conexão'); });
        return;
    }

    if (tipo === 'texto') {
        var formData = new FormData();
        formData.append('modulo_id', document.querySelector('input[name="modulo_id"]').value);
        formData.append('_token', CSRF);
        formData.append('titulo', titulo);
        formData.append('conteudo', document.getElementById('conteudo-texto').value);
        fetch(URL_BASE + '/professor/jornadas/modulos/adicionar-texto', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.success) { alert('Conteúdo salvo!'); redirecionarParaBlocos(); } else alert('Erro: ' + (data.error || '')); })
            .catch(function() { alert('Erro de conexão'); });
        return;
    }

    if (tipo === 'upload_documento') {
        var formData = new FormData();
        formData.append('modulo_id', document.querySelector('input[name="modulo_id"]').value);
        formData.append('_token', CSRF);
        formData.append('titulo', titulo);
        formData.append('descricao', descricao);
        if (editandoDocSemNovoArquivo) {
            formData.append('documento_id', window.conteudoAtualEdicao.id);
        } else {
            formData.append('arquivo', inputDoc.files[0]);
        }
        fetch(URL_BASE + '/professor/jornadas/modulos/adicionar-documento', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
.then(function(data) { if (data.success) { alert('Conteúdo salvo!'); redirecionarParaBlocos(); } else alert('Erro: ' + (data.error || '')); })
        .catch(function() { alert('Erro de conexão'); });
        return;
    }

    var formData = new FormData();
    formData.append('modulo_id', document.querySelector('input[name="modulo_id"]').value);
    formData.append('_token', CSRF);
    formData.append('titulo', titulo);
    formData.append('descricao', descricao);
    formData.append('tipo', tipo === 'upload_video' ? 'upload' : tipo);
    formData.append('url_youtube', url);
    if (tipo === 'upload_video' && inputVideo.files.length) formData.append('arquivo_video', inputVideo.files[0]);
    fetch(URL_BASE + '/professor/jornadas/modulos/adicionar-video', { method: 'POST', body: formData })
        .then(function(r) {
            return r.text().then(function(text) {
                try { return { ok: r.ok, json: JSON.parse(text), raw: text }; } catch (e) { return { ok: r.ok, json: null, raw: text }; }
            });
        })
        .then(function(res) {
            var data = res.json;
            if (data && data.success) { alert('Conteúdo salvo!'); redirecionarParaBlocos(); return; }
            var err = (data && data.error) ? data.error : (res.raw || 'Resposta inválida do servidor.');
            if (res.ok === false && res.raw && res.raw.indexOf('<!') === 0) {
                err = 'O servidor recusou o envio (possível limite de tamanho ou erro 413). Tente um vídeo menor.';
            }
            alert('Erro: ' + err);
        })
        .catch(function() { alert('Erro de conexão'); });
});

function removerVideo(id) {
    if (!confirm('Tem certeza que deseja remover este vídeo?')) return;
    var formData = new FormData();
    formData.append('video_id', id);
    formData.append('_token', CSRF);
    fetch(URL_BASE + '/professor/jornadas/modulos/remover-video', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) { if (data.success) { alert('Vídeo removido com sucesso!'); redirecionarParaBlocos(); } else alert('Erro: ' + (data.error || '')); })
        .catch(function() { alert('Erro de conexão'); });
}

function removerDocumento(id) {
    if (!confirm('Tem certeza que deseja remover este documento?')) return;
    var formData = new FormData();
    formData.append('documento_id', id);
    formData.append('_token', CSRF);
    fetch(URL_BASE + '/professor/jornadas/modulos/remover-documento', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) { if (data.success) { alert('Documento removido com sucesso!'); redirecionarParaBlocos(); } else alert('Erro: ' + (data.error || '')); })
        .catch(function() { alert('Erro de conexão'); });
}

function removerTexto(id) {
    if (!confirm('Tem certeza que deseja remover este texto?')) return;
    var formData = new FormData();
    formData.append('texto_id', id);
    formData.append('_token', CSRF);
    fetch(URL_BASE + '/professor/jornadas/modulos/remover-texto', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) { if (data.success) { alert('Texto removido com sucesso!'); redirecionarParaBlocos(); } else alert('Erro: ' + (data.error || '')); })
        .catch(function() { alert('Erro de conexão'); });
}
</script>

