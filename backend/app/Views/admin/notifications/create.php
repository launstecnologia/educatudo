<?php
/**
 * View: Admin - Criar Notificação
 */
$title = $title ?? 'Nova Notificação';
?>

<!-- Quill.js - Editor WYSIWYG -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= $title ?></h1>
        <a href="<?= URL ?>/admin/notifications" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="mb-4 p-4 rounded-lg <?= $flash_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <form action="<?= URL ?>/admin/notifications/store" method="POST" enctype="multipart/form-data" class="space-y-8">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <!-- Informações Básicas -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Coluna Esquerda -->
                <div class="space-y-6">
                    <div>
                        <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">
                            Título *
                        </label>
                        <input type="text" id="titulo" name="titulo" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Digite o título da notificação">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipos de Conteúdo *
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" id="incluir_texto" name="tipos_conteudo[]" value="texto" checked
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                       onchange="toggleMediaOptions()">
                                <span class="ml-2 text-sm text-gray-700">Texto</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="incluir_imagem" name="tipos_conteudo[]" value="imagem"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                       onchange="toggleMediaOptions()">
                                <span class="ml-2 text-sm text-gray-700">Imagem</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="incluir_video" name="tipos_conteudo[]" value="video"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                       onchange="toggleMediaOptions()">
                                <span class="ml-2 text-sm text-gray-700">Vídeo</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="prioridade" class="block text-sm font-medium text-gray-700 mb-2">
                            Prioridade
                        </label>
                        <select id="prioridade" name="prioridade"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="baixa">Baixa</option>
                            <option value="normal" selected>Normal</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>

                    <div>
                        <label for="data_expiracao" class="block text-sm font-medium text-gray-700 mb-2">
                            Data de Expiração (opcional)
                        </label>
                        <input type="datetime-local" id="data_expiracao" name="data_expiracao"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="is_update" name="is_update" value="1"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_update" class="ml-3 text-sm text-gray-700">
                            Esta é uma notificação de atualização do sistema
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 ml-7">
                        Quando marcada, ao clicar na notificação será executado um refresh completo (Ctrl+F5) e logout/login automático
                    </p>
                </div>

                <!-- Coluna Direita -->
                <div class="space-y-6">
                    <div id="texto-content">
                        <label for="conteudo" class="block text-sm font-medium text-gray-700 mb-2">
                            Conteúdo de Texto *
                        </label>
                        <div id="editor-conteudo" class="quill-editor-wrapper"></div>
                        <textarea id="conteudo" name="conteudo" style="display: none;"></textarea>
                    </div>

                    <!-- Upload de Imagem -->
                    <div id="image-upload" style="display: none;">
                        <label for="arquivo_imagem" class="block text-sm font-medium text-gray-700 mb-2">
                            Upload de Imagem
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors">
                            <input type="file" id="arquivo_imagem" name="arquivo_imagem" accept="image/*" 
                                   class="hidden" onchange="previewImage(this)">
                            <label for="arquivo_imagem" class="cursor-pointer">
                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="text-sm text-gray-600">Clique para fazer upload de uma imagem</p>
                                <p class="text-xs text-gray-500 mt-1">PNG, JPG, GIF até 10MB</p>
                            </label>
                        </div>
                        <div id="image-preview" class="mt-4 hidden">
                            <img id="preview-img" class="max-w-full h-48 object-cover rounded-lg">
                            <button type="button" onclick="removeImage()" class="mt-2 text-red-600 hover:text-red-800 text-sm">
                                Remover imagem
                            </button>
                        </div>
                    </div>

                    <!-- URL de Vídeo -->
                    <div id="video-url" style="display: none;">
                        <label for="video_url" class="block text-sm font-medium text-gray-700 mb-2">
                            URL do Vídeo
                        </label>
                        <input type="url" id="video_url" name="video_url"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="https://www.youtube.com/watch?v=... ou https://vimeo.com/...">
                        <p class="text-xs text-gray-500 mt-1">Suporta YouTube, Vimeo e outros serviços de vídeo</p>
                    </div>

                    <!-- Upload de Arquivo de Vídeo -->
                    <div id="video-file" style="display: none;">
                        <label for="arquivo_video" class="block text-sm font-medium text-gray-700 mb-2">
                            Upload de Arquivo de Vídeo
                        </label>
                        <input type="file" id="arquivo_video" name="arquivo_video" accept="video/*"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">MP4, AVI, MOV até 50MB</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seleção de Destinatários -->
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Destinatários</h2>
            
            <div class="space-y-6">
                <!-- Todos os usuários -->
                <div class="flex items-center">
                    <input type="checkbox" id="todos_usuarios" name="todos_usuarios" value="1"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                           onchange="toggleAllUsers()">
                    <label for="todos_usuarios" class="ml-3 text-sm font-medium text-gray-700">
                        Todos os usuários
                    </label>
                </div>

                <!-- Envio por Categoria -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-800">Envio por Categoria</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:border-blue-300 cursor-pointer">
                            <input type="checkbox" id="todos_admins" name="categorias[]" value="todos_admins"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                   onchange="toggleCategorias()">
                            <span class="ml-2 text-sm text-gray-700">Todos os Admins</span>
                        </label>
                        <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:border-blue-300 cursor-pointer">
                            <input type="checkbox" id="todos_professores" name="categorias[]" value="todos_professores"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                   onchange="toggleCategorias()">
                            <span class="ml-2 text-sm text-gray-700">Todos os Professores</span>
                        </label>
                        <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:border-blue-300 cursor-pointer">
                            <input type="checkbox" id="todos_alunos" name="categorias[]" value="todos_alunos"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                   onchange="toggleCategorias()">
                            <span class="ml-2 text-sm text-gray-700">Todos os Alunos</span>
                        </label>
                        <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:border-blue-300 cursor-pointer">
                            <input type="checkbox" id="todos_pais" name="categorias[]" value="todos_pais"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                   onchange="toggleCategorias()">
                            <span class="ml-2 text-sm text-gray-700">Todos os Pais</span>
                        </label>
                    </div>
                </div>

                <!-- Usuários Específicos -->
                <div id="usuarios-especificos" class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-800">Usuários Específicos</h3>
                    
                    <!-- Linha 1: Administradores e Professores -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Administradores -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Administradores</label>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <select id="admin-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="addAdmin(this)">
                                    <option value="">Selecione um administrador...</option>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <?php if ($usuario['tipo'] === 'admin' || $usuario['tipo'] === 'admin_escola'): ?>
                                            <option value="<?= $usuario['id'] ?>" data-name="<?= htmlspecialchars($usuario['nome']) ?>">
                                                <?= htmlspecialchars($usuario['nome']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="admin-tags" class="flex flex-wrap gap-2"></div>
                        </div>

                        <!-- Professores -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Professores</label>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <select id="professor-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="addProfessor(this)">
                                    <option value="">Selecione um professor...</option>
                                    <?php foreach ($professores as $professor): ?>
                                        <option value="<?= $professor['id'] ?>" data-name="<?= htmlspecialchars($professor['nome']) ?>">
                                            <?= htmlspecialchars($professor['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="professor-tags" class="flex flex-wrap gap-2"></div>
                        </div>
                    </div>

                    <!-- Linha 2: Alunos e Pais -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Alunos -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Alunos</label>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <select id="aluno-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="addAluno(this)">
                                    <option value="">Selecione um aluno...</option>
                                    <?php foreach ($alunos as $aluno): ?>
                                        <option value="<?= $aluno['id'] ?>" data-name="<?= htmlspecialchars($aluno['nome']) ?>">
                                            <?= htmlspecialchars($aluno['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="aluno-tags" class="flex flex-wrap gap-2"></div>
                        </div>

                        <!-- Pais -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pais</label>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <select id="pai-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="addPai(this)">
                                    <option value="">Selecione um pai...</option>
                                    <?php foreach ($pais as $pai): ?>
                                        <option value="<?= $pai['id'] ?>" data-name="<?= htmlspecialchars($pai['nome']) ?>">
                                            <?= htmlspecialchars($pai['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="pai-tags" class="flex flex-wrap gap-2"></div>
                        </div>
                    </div>

                    <!-- Linha 3: Turmas -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Turmas</label>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <select id="turma-select" class="w-full md:w-1/2 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="addTurma(this)">
                                <option value="">Selecione uma turma...</option>
                                <?php foreach ($turmas as $turma): ?>
                                    <option value="<?= $turma['id'] ?>" data-name="<?= htmlspecialchars($turma['nome'] . ' - ' . $turma['serie']) ?>">
                                        <?= htmlspecialchars($turma['nome']) ?> - <?= htmlspecialchars($turma['serie']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="turma-tags" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/notifications" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </a>
            <button type="submit" class="btn-primary-custom px-6 py-3 rounded-lg transition-colors hover:opacity-90">
                Enviar Notificação
            </button>
        </div>
    </form>
</div>

<script>
var quillConteudo;

function toggleMediaOptions() {
    const incluirTexto = document.getElementById('incluir_texto').checked;
    const incluirImagem = document.getElementById('incluir_imagem').checked;
    const incluirVideo = document.getElementById('incluir_video').checked;
    
    const textoContent = document.getElementById('texto-content');
    const imageUpload = document.getElementById('image-upload');
    const videoUrl = document.getElementById('video-url');
    const videoFile = document.getElementById('video-file');
    
    // Mostrar/ocultar campos baseado nas seleções
    textoContent.style.display = incluirTexto ? 'block' : 'none';
    imageUpload.style.display = incluirImagem ? 'block' : 'none';
    videoUrl.style.display = incluirVideo ? 'block' : 'none';
    videoFile.style.display = incluirVideo ? 'block' : 'none';
    
    // Validar se pelo menos um tipo foi selecionado
    if (!incluirTexto && !incluirImagem && !incluirVideo) {
        alert('Selecione pelo menos um tipo de conteúdo!');
        document.getElementById('incluir_texto').checked = true;
        textoContent.style.display = 'block';
    }
}

function toggleAllUsers() {
    const todosUsuarios = document.getElementById('todos_usuarios').checked;
    const usuariosEspecificos = document.getElementById('usuarios-especificos');
    const categorias = document.querySelectorAll('input[name="categorias[]"]');
    
    if (todosUsuarios) {
        usuariosEspecificos.style.display = 'none';
        // Desmarcar todas as categorias e usuários específicos
        categorias.forEach(cat => cat.checked = false);
        clearAllTags();
    } else {
        usuariosEspecificos.style.display = 'block';
    }
}

function toggleCategorias() {
    const categorias = document.querySelectorAll('input[name="categorias[]"]');
    const todosUsuarios = document.getElementById('todos_usuarios');
    
    // Se alguma categoria foi marcada, desmarcar "todos os usuários"
    const algumaCategoriaMarcada = Array.from(categorias).some(cat => cat.checked);
    if (algumaCategoriaMarcada) {
        todosUsuarios.checked = false;
    }
}

function addAdmin(select) {
    if (select.value) {
        const option = select.options[select.selectedIndex];
        const id = option.value;
        const name = option.getAttribute('data-name');
        
        addTag('admin-tags', 'admin', id, name, 'Administrador');
        select.value = '';
    }
}

function addProfessor(select) {
    if (select.value) {
        const option = select.options[select.selectedIndex];
        const id = option.value;
        const name = option.getAttribute('data-name');
        
        addTag('professor-tags', 'professor', id, name, 'Professor');
        select.value = '';
    }
}

function addAluno(select) {
    if (select.value) {
        const option = select.options[select.selectedIndex];
        const id = option.value;
        const name = option.getAttribute('data-name');
        
        addTag('aluno-tags', 'aluno', id, name, 'Aluno');
        select.value = '';
    }
}

function addPai(select) {
    if (select.value) {
        const option = select.options[select.selectedIndex];
        const id = option.value;
        const name = option.getAttribute('data-name');
        
        addTag('pai-tags', 'pai', id, name, 'Pai');
        select.value = '';
    }
}

function addTurma(select) {
    if (select.value) {
        const option = select.options[select.selectedIndex];
        const id = option.value;
        const name = option.getAttribute('data-name');
        
        addTag('turma-tags', 'turma', id, name, 'Turma');
        select.value = '';
    }
}

function addTag(containerId, type, id, name, label) {
    const container = document.getElementById(containerId);
    
    // Verificar se já existe
    const existingTag = container.querySelector(`[data-id="${type}_${id}"]`);
    if (existingTag) return;
    
    const tag = document.createElement('div');
    tag.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800 border border-blue-200';
    tag.setAttribute('data-id', `${type}_${id}`);
    
    tag.innerHTML = `
        <span class="mr-2">${name}</span>
        <button type="button" onclick="removeTag('${containerId}', '${type}_${id}')" 
                class="ml-1 text-blue-600 hover:text-blue-800 focus:outline-none">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    
    // Adicionar input hidden
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'destinatarios[]';
    hiddenInput.value = `${type}_${id}`;
    tag.appendChild(hiddenInput);
    
    container.appendChild(tag);
}

function removeTag(containerId, tagId) {
    const container = document.getElementById(containerId);
    const tag = container.querySelector(`[data-id="${tagId}"]`);
    if (tag) {
        tag.remove();
    }
}

function clearAllTags() {
    const containers = ['admin-tags', 'professor-tags', 'aluno-tags', 'pai-tags', 'turma-tags'];
    containers.forEach(containerId => {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
    });
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('image-preview').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage() {
    document.getElementById('arquivo_imagem').value = '';
    document.getElementById('image-preview').classList.add('hidden');
}

// Inicializar estado
document.addEventListener('DOMContentLoaded', function() {
    toggleMediaOptions();
    toggleAllUsers();

    quillConteudo = new Quill('#editor-conteudo', {
        theme: 'snow'
    });

    quillConteudo.on('text-change', function() {
        document.getElementById('conteudo').value = quillConteudo.root.innerHTML;
    });

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const incluirTexto = document.getElementById('incluir_texto').checked;
            const textoPuro = quillConteudo.getText().trim();
            document.getElementById('conteudo').value = quillConteudo.root.innerHTML.trim();

            if (incluirTexto && textoPuro.length === 0) {
                e.preventDefault();
                alert('Por favor, preencha o conteúdo de texto da notificação.');
                return;
            }

            const todosUsuarios = document.getElementById('todos_usuarios').checked;
            const categorias = document.querySelectorAll('input[name="categorias[]"]:checked').length > 0;
            const destinatarios = document.querySelectorAll('input[name="destinatarios[]"]').length > 0;
            if (!todosUsuarios && !categorias && !destinatarios) {
                e.preventDefault();
                alert('Selecione pelo menos um destinatário.');
            }
        });
    }
});
</script>

<style>
.quill-editor-wrapper {
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    overflow: visible;
    width: 100%;
}

.quill-editor-wrapper .ql-container {
    min-height: 200px;
    font-size: 14px;
    border: none;
    border-top: 1px solid #d1d5db;
}

.quill-editor-wrapper .ql-editor {
    min-height: 200px;
    padding: 12px 15px;
    color: #1f2937;
}

.quill-editor-wrapper .ql-toolbar {
    border-top: none;
    border-left: none;
    border-right: none;
    border-bottom: 1px solid #d1d5db;
    background: #f9fafb;
    padding: 8px;
}

.quill-editor-wrapper .ql-editor.ql-blank::before {
    color: #9ca3af;
    font-style: normal;
}
</style>
