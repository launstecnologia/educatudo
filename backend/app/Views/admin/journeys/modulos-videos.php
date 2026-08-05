<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Gerenciar - <?= htmlspecialchars($modulo['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Jornada: <?= htmlspecialchars($modulo['jornada_titulo']) ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/jornadas/<?= $modulo['jornada_id'] ?>/modulos" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Tabs: Vídeos / Documentos -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-purple-200">
    <div class="flex space-x-4 mb-6">
        <button onclick="mostrarTabVideos()" id="tab-videos" class="tab-video active px-6 py-3 bg-purple-600 text-white rounded-lg font-medium">
            🎥 Vídeos
        </button>
        <button onclick="mostrarTabDocumentos()" id="tab-documentos" class="tab-video px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300">
            📄 Documentos
        </button>
    </div>
    
    <!-- Form Adicionar Vídeo -->
    <div id="form-videos" class="video-form">
        <form id="adicionarVideoForm" class="space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Vídeo *</label>
                <select name="tipo" id="tipo-video" required 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option value="youtube">YouTube (Link)</option>
                    <option value="upload">Upload de Arquivo</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
                <input type="text" name="titulo" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                       placeholder="Ex: Aula sobre Equações">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Descrição / Notas</label>
                <div id="editor-video-container">
                    <textarea id="editor-video" name="descricao" rows="6"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                              placeholder="Descrição do vídeo ou notas sobre o conteúdo..."></textarea>
                </div>
            </div>
            
            <div id="youtube-container">
                <label class="block text-sm font-medium text-gray-700 mb-2">URL do YouTube *</label>
                <input type="url" name="url_youtube" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                       placeholder="https://www.youtube.com/watch?v=...">
                <p class="text-xs text-gray-500 mt-1">Cole o link completo do vídeo do YouTube</p>
            </div>
            
            <div id="upload-container" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo de Vídeo *</label>
                <input type="file" name="arquivo_video" accept="video/*"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <p class="text-xs text-gray-500 mt-1">Formatos aceitos: MP4, AVI, MOV, etc.</p>
            </div>
            
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                Adicionar Vídeo
            </button>
        </form>
    </div>
    
    <!-- Form Adicionar Documento -->
    <div id="form-documentos" class="video-form hidden">
        <form id="adicionarDocumentoForm" class="space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
                <input type="text" name="titulo" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                       placeholder="Ex: Material de Apoio">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Descrição / Notas</label>
                <div id="editor-documento-container">
                    <textarea id="editor-documento" name="descricao" rows="6"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                              placeholder="Descrição do documento ou notas sobre o conteúdo..."></textarea>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo *</label>
                <input type="file" name="arquivo" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <p class="text-xs text-gray-500 mt-1">PDF, DOC, DOCX, PPT, PPTX, etc.</p>
            </div>
            
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                Adicionar Documento
            </button>
        </form>
    </div>
</div>

<!-- Lista de Vídeos -->
<div id="videos-section" class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-purple-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Vídeos do Conteúdo</h3>
    
    <div id="videosList" class="space-y-4">
        <?php if (empty($videos)): ?>
            <div class="text-center py-8 text-gray-500">
                <p>Nenhum vídeo adicionado ainda</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($videos as $video): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="mb-3">
                            <?php if ($video['tipo'] === 'youtube' && $video['url_youtube']): ?>
                                <?php
                                // Extrai ID do vídeo do YouTube
                                preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $video['url_youtube'], $matches);
                                $videoId = $matches[1] ?? null;
                                ?>
                                <?php if ($videoId): ?>
                                    <div class="aspect-video bg-gray-200 rounded-lg overflow-hidden">
                                        <img src="https://img.youtube.com/vi/<?= $videoId ?>/mqdefault.jpg" 
                                             alt="<?= htmlspecialchars($video['titulo']) ?>"
                                             class="w-full h-full object-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="aspect-video bg-gray-200 rounded-lg flex items-center justify-center">
                                        <span class="text-gray-400">🎥</span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="aspect-video bg-gray-200 rounded-lg flex items-center justify-center">
                                    <span class="text-gray-400">🎥</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($video['titulo']) ?></h4>
                        <?php if ($video['descricao']): ?>
                            <div class="text-sm text-gray-600 mb-2 prose prose-sm max-w-none">
                                <?= strip_tags($video['descricao']) ? substr(strip_tags($video['descricao']), 0, 100) . '...' : '' ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center justify-between mt-3">
                            <span class="text-xs text-gray-500">
                                <?= $video['tipo'] === 'youtube' ? '📺 YouTube' : '📁 Upload' ?>
                            </span>
                            <button onclick="removerVideo(<?= $video['id'] ?>)" 
                                    class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">
                                Remover
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Lista de Documentos -->
<div id="documentos-section" class="bg-white rounded-xl shadow-lg p-6 border border-purple-200 hidden">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Documentos do Módulo</h3>
    
    <div id="documentosList" class="space-y-3">
        <?php if (empty($documentos)): ?>
            <div class="text-center py-8 text-gray-500">
                <p>Nenhum documento adicionado ainda</p>
            </div>
        <?php else: ?>
            <?php foreach ($documentos as $doc): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center space-x-3 flex-1">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">📄</span>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($doc['titulo']) ?></h4>
                                <?php if ($doc['descricao']): ?>
                                    <div class="text-sm text-gray-600 mt-1 prose prose-sm max-w-none">
                                        <?= strip_tags($doc['descricao']) ? substr(strip_tags($doc['descricao']), 0, 100) . '...' : '' ?>
                                    </div>
                                <?php endif; ?>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?= htmlspecialchars($doc['arquivo_nome']) ?> 
                                    (<?= number_format($doc['arquivo_tamanho'] / 1024, 2) ?> KB)
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2 ml-4">
                            <a href="<?= URL ?>/<?= $doc['arquivo'] ?>" target="_blank"
                               class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors">
                                Ver
                            </a>
                            <button onclick="removerDocumento(<?= $doc['id'] ?>)" 
                                    class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">
                                Remover
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- CKEditor 5 -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.2.1/classic/ckeditor.js"></script>
<style>
.ck-editor__editable {
    min-height: 400px !important;
}
</style>
<script>
let editorVideo = null;
let editorDocumento = null;

// Inicializa editor para vídeos
ClassicEditor
    .create(document.querySelector('#editor-video'), {
        toolbar: [
            'heading', '|',
            'bold', 'italic', 'underline', '|',
            'link', 'bulletedList', 'numberedList', '|',
            'alignment', '|',
            'undo', 'redo'
        ],
        alignment: {
            options: ['left', 'center', 'right', 'justify']
        },
        language: 'pt-br'
    })
    .then(editor => {
        editorVideo = editor;
        console.log('Editor de vídeo pronto!', editor);
    })
    .catch(error => {
        console.error('Erro ao inicializar editor de vídeo:', error);
    });

// Inicializa editor para documentos
ClassicEditor
    .create(document.querySelector('#editor-documento'), {
        toolbar: [
            'heading', '|',
            'bold', 'italic', 'underline', '|',
            'link', 'bulletedList', 'numberedList', '|',
            'alignment', '|',
            'undo', 'redo'
        ],
        alignment: {
            options: ['left', 'center', 'right', 'justify']
        },
        language: 'pt-br'
    })
    .then(editor => {
        editorDocumento = editor;
        console.log('Editor de documento pronto!', editor);
    })
    .catch(error => {
        console.error('Erro ao inicializar editor de documento:', error);
    });

function mostrarTabVideos() {
    document.getElementById('form-videos').classList.remove('hidden');
    document.getElementById('form-documentos').classList.add('hidden');
    document.getElementById('videos-section').classList.remove('hidden');
    document.getElementById('documentos-section').classList.add('hidden');
    
    document.getElementById('tab-videos').classList.add('active', 'bg-purple-600', 'text-white');
    document.getElementById('tab-videos').classList.remove('bg-gray-200', 'text-gray-700');
    document.getElementById('tab-documentos').classList.remove('active', 'bg-purple-600', 'text-white');
    document.getElementById('tab-documentos').classList.add('bg-gray-200', 'text-gray-700');
    
    // Garante que o editor de vídeo está visível
    if (editorVideo) {
        setTimeout(() => {
            editorVideo.ui.focus();
        }, 100);
    }
}

function mostrarTabDocumentos() {
    document.getElementById('form-videos').classList.add('hidden');
    document.getElementById('form-documentos').classList.remove('hidden');
    document.getElementById('videos-section').classList.add('hidden');
    document.getElementById('documentos-section').classList.remove('hidden');
    
    document.getElementById('tab-documentos').classList.add('active', 'bg-purple-600', 'text-white');
    document.getElementById('tab-documentos').classList.remove('bg-gray-200', 'text-gray-700');
    document.getElementById('tab-videos').classList.remove('active', 'bg-purple-600', 'text-white');
    document.getElementById('tab-videos').classList.add('bg-gray-200', 'text-gray-700');
    
    // Garante que o editor de documento está visível
    if (editorDocumento) {
        setTimeout(() => {
            editorDocumento.ui.focus();
        }, 100);
    }
}

// Alterna entre YouTube e Upload
document.getElementById('tipo-video').addEventListener('change', function() {
    const youtubeContainer = document.getElementById('youtube-container');
    const uploadContainer = document.getElementById('upload-container');
    
    if (this.value === 'youtube') {
        youtubeContainer.classList.remove('hidden');
        uploadContainer.classList.add('hidden');
        document.querySelector('input[name="url_youtube"]').required = true;
        document.querySelector('input[name="arquivo_video"]').required = false;
    } else {
        youtubeContainer.classList.add('hidden');
        uploadContainer.classList.remove('hidden');
        document.querySelector('input[name="url_youtube"]').required = false;
        document.querySelector('input[name="arquivo_video"]').required = true;
    }
});

document.getElementById('adicionarVideoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('<?= URL ?>/admin/jornadas/modulos/adicionar-video', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Vídeo adicionado com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
});

document.getElementById('adicionarDocumentoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Atualiza o textarea com o conteúdo do editor antes de enviar
    if (editorDocumento) {
        const editorData = editorDocumento.getData();
        document.querySelector('#editor-documento').value = editorData;
    }
    
    const formData = new FormData(this);
    
    fetch('<?= URL ?>/admin/jornadas/modulos/adicionar-documento', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Documento adicionado com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
});

function removerVideo(id) {
    if (!confirm('Tem certeza que deseja remover este vídeo?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('video_id', id);
    
    fetch('<?= URL ?>/admin/jornadas/modulos/remover-video', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Vídeo removido com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
}

function removerDocumento(id) {
    if (!confirm('Tem certeza que deseja remover este documento?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('documento_id', id);
    
    fetch('<?= URL ?>/admin/jornadas/modulos/remover-documento', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Documento removido com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
}
</script>

