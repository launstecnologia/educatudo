<?php
/**
 * View: Lista de Tickets do Aluno
 */
?>

<!-- Quill CSS -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
    #quill-editor-container .ql-editor {
        min-height: 160px;
        max-height: 300px;
        overflow-y: auto;
        font-size: 14px;
    }
    #quill-editor-container .ql-editor img {
        max-width: 100%;
        border-radius: 6px;
        margin: 8px 0;
    }
    #quill-editor-container {
        border-radius: 0 0 0.5rem 0.5rem;
    }
    #quill-editor-container .ql-toolbar {
        border-radius: 0.5rem 0.5rem 0 0;
        border-color: #d1d5db;
    }
    #quill-editor-container .ql-container {
        border-radius: 0 0 0.5rem 0.5rem;
        border-color: #d1d5db;
    }
    #quill-reply-container .ql-editor {
        min-height: 80px;
        max-height: 200px;
        overflow-y: auto;
        font-size: 14px;
    }
    #quill-reply-container .ql-editor img {
        max-width: 100%;
        border-radius: 6px;
        margin: 8px 0;
    }
    .ticket-dropzone-active {
        border-color: #2563eb !important;
        background: rgba(219, 234, 254, 0.9) !important;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
    }
    .ticket-attachment-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 0.875rem;
        border: 1px solid #dbeafe;
        border-radius: 0.75rem;
        background: rgba(255, 255, 255, 0.92);
    }
    .ticket-attachment-item + .ticket-attachment-item {
        margin-top: 0.5rem;
    }
    .ticket-attachment-meta {
        min-width: 0;
    }
    .ticket-attachment-name {
        color: #1e3a8a;
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.25rem;
        word-break: break-word;
    }
    .ticket-attachment-type {
        color: #64748b;
        font-size: 0.75rem;
        line-height: 1rem;
    }
</style>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                Tickets de Suporte
            </h1>
            <p class="text-gray-600">
                Gerencie seus chamados de suporte e tire suas dúvidas
            </p>
        </div>
        <button onclick="document.getElementById('modalNovoTicket').classList.remove('hidden')"
                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Novo Ticket
        </button>
    </div>

    <?php if (isset($_GET['error'])): ?>
    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg border border-red-300">
        <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
    <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg border border-green-300">
        <?= htmlspecialchars($_GET['success']) ?>
    </div>
    <?php endif; ?>

    <!-- Lista de Tickets -->
    <div class="bg-white rounded-lg shadow-sm border">
        <?php if (empty($tickets)): ?>
            <div class="p-12 text-center">
                <div class="mb-4">
                    <svg class="w-16 h-16 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Nenhum ticket criado</h3>
                <p class="text-gray-600 mb-4">Você ainda não abriu nenhum ticket de suporte.</p>
                <button onclick="document.getElementById('modalNovoTicket').classList.remove('hidden')"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                    Criar Primeiro Ticket
                </button>
            </div>
        <?php else: ?>
            <div class="divide-y">
                <?php foreach ($tickets as $ticket): ?>
                    <a href="<?= URL ?>/tickets/visualizar?id=<?= $ticket['id'] ?>" 
                       class="block p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-gray-800">#<?= $ticket['id'] ?></span>
                                    <span class="px-2 py-1 text-xs rounded-full font-semibold <?= 
                                        $ticket['status'] === 'fechado' ? 'bg-gray-100 text-gray-800' :
                                        ($ticket['status'] === 'respondido' ? 'bg-green-100 text-green-800' :
                                        ($ticket['status'] === 'em_andamento' ? 'bg-yellow-100 text-yellow-800' :
                                        'bg-blue-100 text-blue-800')) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $ticket['status'] ?? 'aberto')) ?>
                                    </span>
                                    <?php if (($ticket['respostas_nao_lidas'] ?? 0) > 0): ?>
                                        <span class="px-2 py-1 text-xs bg-red-500 text-white rounded-full font-semibold">
                                            <?= $ticket['respostas_nao_lidas'] ?> nova(s)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-gray-800 font-medium"><?= htmlspecialchars($ticket['assunto'] ?? 'Sem assunto') ?></p>
                                <div class="flex items-center gap-3 mt-1">
                                    <p class="text-sm text-gray-500">
                                        <?= $ticket['total_mensagens'] ?? 0 ?> mensagens &bull;
                                        Criado em <?= date('d/m/Y H:i', strtotime($ticket['criado_em'] ?? 'now')) ?>
                                    </p>
                                    <?php if (!empty($ticket['modulo'])): ?>
                                        <span class="px-2 py-0.5 text-xs bg-purple-100 text-purple-700 rounded-full">
                                            <?= htmlspecialchars($ticket['modulo']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Novo Ticket -->
<div id="modalNovoTicket" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-900">Novo Ticket</h3>
                <button onclick="fecharModalTicket()"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        <form id="formNovoTicket" class="p-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Assunto *</label>
                    <input type="text" name="assunto" required 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           placeholder="Ex: Problema com exercício">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Categoria</label>
                        <select name="categoria" id="selectCategoria"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <option value="geral">Geral</option>
                            <option value="problema">Problema</option>
                            <option value="tecnico">Técnico</option>
                            <option value="conteudo">Conteúdo</option>
                            <option value="conta">Conta</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>

                    <div id="moduloContainer" class="hidden">
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Módulo</label>
                        <select name="modulo" id="selectModulo"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <option value="">Selecione o módulo...</option>
                            <optgroup label="Plataforma">
                                <option value="Dashboard">Dashboard</option>
                                <option value="Perfil / Conta">Perfil / Conta</option>
                                <option value="Login / Acesso">Login / Acesso</option>
                            </optgroup>
                            <optgroup label="EducaTudo">
                                <option value="Tudinha (Chat IA)">Tudinha (Chat IA)</option>
                                <option value="Educa Livros">Educa Livros</option>
                                <option value="EducaLabs">EducaLabs</option>
                                <option value="Flash Cards">Flash Cards</option>
                                <option value="Exercícios">Exercícios</option>
                                <option value="Inglês">Inglês</option>
                                <option value="Redações">Redações</option>
                                <option value="Simulados">Simulados</option>
                                <option value="Jogos">Jogos</option>
                                <option value="Drive">Drive</option>
                                <option value="Meu Caderno">Meu Caderno</option>
                                <option value="Fórum">Fórum</option>
                            </optgroup>
                            <optgroup label="Escola (Colab)">
                                <option value="Plano de Aula">Plano de Aula</option>
                                <option value="Arquivos">Arquivos</option>
                                <option value="Apostilas">Apostilas</option>
                                <option value="Jornada do Aluno">Jornada do Aluno</option>
                                <option value="Prova Online">Prova Online</option>
                                <option value="Chat com Professor">Chat com Professor</option>
                                <option value="Redação Orientada">Redação Orientada</option>
                                <option value="Minicursos">Minicursos</option>
                                <option value="Mural de Recados">Mural de Recados</option>
                            </optgroup>
                            <optgroup label="Outros">
                                <option value="Carteira de Créditos">Carteira de Créditos</option>
                                <option value="Outro">Outro</option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Mensagem *</label>
                    <p class="text-xs text-gray-500 mb-2">Você pode anexar imagens e documentos. Também é possível colar imagens ou arrastar vários arquivos.</p>
                    <div id="quill-editor-container">
                        <div id="quill-editor"></div>
                    </div>
                    <div id="ticketAttachmentDropzone" class="mt-3 rounded-xl border border-dashed border-blue-200 bg-blue-50/60 p-4 transition-all">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-blue-900">Anexos do ticket</p>
                                <p class="text-xs text-blue-700">Clique para escolher, arraste para esta área ou cole imagens. Você pode adicionar vários arquivos.</p>
                            </div>
                            <button type="button"
                                    id="btnSelecionarArquivosTicket"
                                    class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm ring-1 ring-blue-200 transition hover:bg-blue-100">
                                Adicionar arquivos
                            </button>
                        </div>
                        <input type="file"
                               id="ticketFileInput"
                               class="hidden"
                               multiple
                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,image/jpeg,image/png,image/gif,image/webp,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,text/plain,text/csv,application/zip,application/x-zip-compressed,application/x-rar-compressed">
                        <div id="ticketAttachmentsStatus" class="mt-3 hidden rounded-lg border border-blue-200 bg-white/80 px-3 py-2 text-xs text-blue-700"></div>
                        <div id="ticketAttachmentsList" class="mt-3 hidden"></div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex gap-3 justify-end">
                <button type="button" onclick="fecharModalTicket()"
                        class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium transition-colors">
                    Cancelar
                </button>
                <button type="submit" id="btnCriarTicket"
                        class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Criar Ticket
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Quill JS -->
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const csrfToken = <?= json_encode($csrf_token) ?>;
const uploadUrl = '<?= URL ?>/tickets/upload-arquivo';
const criarUrl = '<?= URL ?>/tickets/processar-criar';
const visualizarUrl = '<?= URL ?>/tickets/visualizar';
const ticketAttachmentStatus = document.getElementById('ticketAttachmentsStatus');
const ticketFileInput = document.getElementById('ticketFileInput');
const btnSelecionarArquivosTicket = document.getElementById('btnSelecionarArquivosTicket');
const ticketAttachmentDropzone = document.getElementById('ticketAttachmentDropzone');
const ticketAttachmentsList = document.getElementById('ticketAttachmentsList');
const ticketUploadedAttachments = [];

// Seletor de módulo - aparece só quando categoria = "problema"
const selectCategoria = document.getElementById('selectCategoria');
const moduloContainer = document.getElementById('moduloContainer');
selectCategoria.addEventListener('change', function() {
    if (this.value === 'problema') {
        moduloContainer.classList.remove('hidden');
    } else {
        moduloContainer.classList.add('hidden');
        document.getElementById('selectModulo').value = '';
    }
});

function fecharModalTicket() {
    document.getElementById('modalNovoTicket').classList.add('hidden');
    setTicketDropzoneActive(false);
    if (ticketFileInput) ticketFileInput.value = '';
}

function setTicketAttachmentStatus(message, isError = false) {
    if (!ticketAttachmentStatus) return;
    ticketAttachmentStatus.classList.remove('hidden', 'border-red-200', 'bg-red-50', 'text-red-700', 'border-blue-200', 'bg-white/80', 'text-blue-700');
    ticketAttachmentStatus.classList.add(isError ? 'border-red-200' : 'border-blue-200');
    ticketAttachmentStatus.classList.add(isError ? 'bg-red-50' : 'bg-white/80');
    ticketAttachmentStatus.classList.add(isError ? 'text-red-700' : 'text-blue-700');
    ticketAttachmentStatus.innerHTML = message;
}

function getAttachmentKindLabel(data) {
    if (data.is_image) return 'Imagem';

    const type = (data.type || '').toLowerCase();
    const name = (data.name || '').toLowerCase();

    if (type.includes('pdf') || name.endsWith('.pdf')) return 'PDF';
    if (type.includes('word') || name.endsWith('.doc') || name.endsWith('.docx')) return 'Documento Word';
    if (type.includes('excel') || name.endsWith('.xls') || name.endsWith('.xlsx')) return 'Planilha';
    if (type.includes('powerpoint') || name.endsWith('.ppt') || name.endsWith('.pptx')) return 'Apresentação';
    if (type.includes('zip') || type.includes('rar') || name.endsWith('.zip') || name.endsWith('.rar')) return 'Arquivo compactado';
    if (type.includes('csv') || name.endsWith('.csv')) return 'CSV';
    if (type.includes('text') || name.endsWith('.txt')) return 'Texto';

    return 'Arquivo';
}

function renderTicketAttachmentsList() {
    if (!ticketAttachmentsList) return;

    if (!ticketUploadedAttachments.length) {
        ticketAttachmentsList.classList.add('hidden');
        ticketAttachmentsList.innerHTML = '';
        return;
    }

    ticketAttachmentsList.classList.remove('hidden');
    ticketAttachmentsList.innerHTML = ticketUploadedAttachments.map(function(file, index) {
        const safeName = (file.name || 'arquivo').replace(/[&<>"]/g, function(char) {
            return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'})[char];
        });
        const safeUrl = String(file.url || '').replace(/"/g, '&quot;');
        const kind = getAttachmentKindLabel(file);

        return `
            <div class="ticket-attachment-item">
                <div class="ticket-attachment-meta">
                    <div class="ticket-attachment-name">${safeName}</div>
                    <div class="ticket-attachment-type">${kind}</div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="${safeUrl}" target="_blank" rel="noopener noreferrer"
                       class="rounded-lg border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">
                        Abrir
                    </a>
                    <button type="button"
                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                            data-ticket-remove-index="${index}">
                        Remover
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function setTicketDropzoneActive(active) {
    if (!ticketAttachmentDropzone) return;
    ticketAttachmentDropzone.classList.toggle('ticket-dropzone-active', !!active);
}

function buildAttachmentHtml(data) {
    const safeName = (data.name || 'arquivo').replace(/[&<>"]/g, function(char) {
        return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'})[char];
    });

    if (data.is_image) {
        return `<p><img src="${data.url}" alt="${safeName}" style="max-width: 100%; height: auto; border-radius: 8px;"></p>`;
    }

    return `<p><a href="${data.url}" target="_blank" rel="noopener noreferrer">📎 ${safeName}</a></p>`;
}

function insertAttachmentIntoQuill(data, quill) {
    const range = quill.getSelection(true) || { index: quill.getLength(), length: 0 };
    quill.clipboard.dangerouslyPasteHTML(range.index, buildAttachmentHtml(data));
    quill.setSelection(range.index + 1, 0, 'silent');
}

async function uploadAndInsertAttachment(file, quill) {
    const formData = new FormData();
    formData.append('arquivo', file);
    formData.append('_token', csrfToken);

    try {
        const res = await fetch(uploadUrl, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.url) {
            insertAttachmentIntoQuill(data, quill);
            return data;
        }

        throw new Error(data.error || 'Erro desconhecido');
    } catch (err) {
        console.error('Upload error:', err);
        throw err;
    }
}

async function handleTicketFiles(files, quill) {
    const validFiles = Array.from(files || []).filter(Boolean);
    if (!validFiles.length) {
        return;
    }

    setTicketAttachmentStatus(`Enviando ${validFiles.length} arquivo(s)...`);
    const uploaded = [];
    const failed = [];

    for (const file of validFiles) {
        try {
            const data = await uploadAndInsertAttachment(file, quill);
            ticketUploadedAttachments.push(data);
            uploaded.push(data.name || file.name);
        } catch (error) {
            failed.push(`${file.name}: ${error.message}`);
        }
    }

    const parts = [];
    if (uploaded.length) {
        parts.push(`<div><strong>Enviado(s):</strong> ${uploaded.join(', ')}</div>`);
    }
    if (failed.length) {
        parts.push(`<div><strong>Falha(s):</strong> ${failed.join(' | ')}</div>`);
    }

    setTicketAttachmentStatus(parts.join(''), failed.length > 0 && uploaded.length === 0);
    renderTicketAttachmentsList();
}

// Quill editor com upload de imagem/arquivo
function imageHandler() {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/jpeg,image/png,image/gif,image/webp');
    input.click();
    input.onchange = async () => {
        const file = input.files[0];
        if (file) {
            await handleTicketFiles([file], quillEditor);
        }
    };
}

const quillEditor = new Quill('#quill-editor', {
    theme: 'snow',
    placeholder: 'Descreva seu problema ou dúvida...',
    modules: {
        toolbar: {
            container: [
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                ['link', 'image'],
                ['clean']
            ],
            handlers: {
                'image': imageHandler
            }
        },
        clipboard: {
            matchVisual: false
        }
    }
});

// Interceptar paste de imagens
quillEditor.root.addEventListener('paste', function(e) {
    const clipboard = e.clipboardData || (e.originalEvent ? e.originalEvent.clipboardData : null);
    const items = clipboard ? clipboard.items : [];
    const files = [];
    for (let i = 0; i < items.length; i++) {
        const file = items[i].getAsFile ? items[i].getAsFile() : null;
        if (file) {
            files.push(file);
        }
    }
    if (files.length) {
        e.preventDefault();
        handleTicketFiles(files, quillEditor);
    }
});

// Interceptar drag-and-drop de arquivos
quillEditor.root.addEventListener('drop', function(e) {
    e.preventDefault();
    setTicketDropzoneActive(false);
    const files = e.dataTransfer.files;
    handleTicketFiles(files, quillEditor);
});

['dragenter', 'dragover'].forEach(function(eventName) {
    ticketAttachmentDropzone.addEventListener(eventName, function(e) {
        e.preventDefault();
        e.stopPropagation();
        setTicketDropzoneActive(true);
    });
    quillEditor.root.addEventListener(eventName, function(e) {
        e.preventDefault();
        e.stopPropagation();
        setTicketDropzoneActive(true);
    });
});

['dragleave', 'dragend', 'drop'].forEach(function(eventName) {
    ticketAttachmentDropzone.addEventListener(eventName, function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (eventName === 'drop') {
            const files = e.dataTransfer ? e.dataTransfer.files : [];
            handleTicketFiles(files, quillEditor);
        }
        setTicketDropzoneActive(false);
    });
    quillEditor.root.addEventListener(eventName, function(e) {
        if (eventName !== 'drop') {
            e.preventDefault();
            e.stopPropagation();
        }
        if (eventName !== 'drop') {
            setTicketDropzoneActive(false);
        }
    });
});

btnSelecionarArquivosTicket.addEventListener('click', function() {
    ticketFileInput.click();
});

ticketFileInput.addEventListener('change', function() {
    handleTicketFiles(this.files, quillEditor);
    this.value = '';
});

if (ticketAttachmentsList) {
    ticketAttachmentsList.addEventListener('click', function(e) {
        const button = e.target.closest('[data-ticket-remove-index]');
        if (!button) return;

        const index = Number(button.getAttribute('data-ticket-remove-index'));
        if (Number.isNaN(index) || index < 0 || index >= ticketUploadedAttachments.length) return;

        const removed = ticketUploadedAttachments.splice(index, 1)[0];
        renderTicketAttachmentsList();
        setTicketAttachmentStatus(`Anexo removido da lista: <strong>${(removed.name || 'arquivo').replace(/[&<>"]/g, function(char) {
            return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'})[char];
        })}</strong>.<br><span class="text-[11px]">Se quiser remover também do conteúdo do ticket, apague o link ou imagem no editor.</span>`);
    });
}

// Submit do formulário
document.getElementById('formNovoTicket').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const htmlContent = quillEditor.root.innerHTML.trim();
    const textContent = quillEditor.getText().trim();

    if (!textContent || textContent === '') {
        alert('A mensagem não pode estar vazia');
        return;
    }

    const btn = document.getElementById('btnCriarTicket');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Enviando...';

    const formData = new FormData(this);
    formData.set('mensagem', htmlContent);

    try {
        const response = await fetch(criarUrl, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = visualizarUrl + '?id=' + data.ticket_id + '&success=Ticket criado com sucesso!';
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível criar o ticket'));
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg> Criar Ticket';
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao criar ticket. Tente novamente.');
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg> Criar Ticket';
    }
});
</script>
