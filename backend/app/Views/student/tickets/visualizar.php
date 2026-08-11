<?php
/**
 * View: Visualizar Ticket e Conversa
 */
if (!function_exists('ticket_message_html')) {
    require_once __DIR__ . '/../../../Helpers/RichTextHelper.php';
}
?>

<!-- Quill CSS -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
    .ticket-message-content img {
        max-width: 100%;
        border-radius: 6px;
        margin: 6px 0;
        cursor: pointer;
    }
    .ticket-message-content p {
        margin: 0;
    }
    .ticket-message-content ul,
    .ticket-message-content ol {
        padding-left: 1.5rem;
        margin: 4px 0;
    }
    .ticket-message-content a {
        color: #3b82f6;
        text-decoration: underline;
    }
    #quill-reply-container .ql-editor {
        min-height: 70px;
        max-height: 200px;
        overflow-y: auto;
        font-size: 14px;
    }
    #quill-reply-container .ql-editor img {
        max-width: 100%;
        border-radius: 6px;
        margin: 6px 0;
    }
    #quill-reply-container .ql-toolbar {
        border-color: #d1d5db;
        border-radius: 0.5rem 0.5rem 0 0;
        padding: 4px 8px;
    }
    #quill-reply-container .ql-container {
        border-color: #d1d5db;
        border-radius: 0 0 0.5rem 0.5rem;
    }
    /* Lightbox para imagens */
    .img-lightbox {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.85);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .img-lightbox.active {
        display: flex;
    }
    .img-lightbox img {
        max-width: 90vw;
        max-height: 90vh;
        border-radius: 8px;
        box-shadow: 0 4px 30px rgba(0,0,0,0.5);
    }
</style>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <a href="<?= URL ?>/tickets" class="text-blue-600 hover:text-blue-800 flex items-center mb-4 transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Voltar para Tickets
        </a>
        
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900">Ticket #<?= $ticket['id'] ?></h1>
                    <span class="px-3 py-1 text-sm rounded-full font-semibold <?= 
                        $ticket['status'] === 'fechado' ? 'bg-gray-100 text-gray-800' :
                        ($ticket['status'] === 'respondido' ? 'bg-green-100 text-green-800' :
                        ($ticket['status'] === 'em_andamento' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-blue-100 text-blue-800')) ?>">
                        <?= ucfirst(str_replace('_', ' ', $ticket['status'] ?? 'Aberto')) ?>
                    </span>
                </div>
                <p class="text-gray-600 mt-1"><?= htmlspecialchars($ticket['assunto'] ?? 'Sem assunto') ?></p>
                <div class="flex items-center gap-3 mt-1">
                    <span class="text-xs text-gray-500">
                        Categoria: <?= ucfirst(htmlspecialchars($ticket['categoria'] ?? 'Geral')) ?>
                    </span>
                    <?php if (!empty($ticket['modulo'])): ?>
                        <span class="px-2 py-0.5 text-xs bg-purple-100 text-purple-700 rounded-full">
                            <?= htmlspecialchars($ticket['modulo']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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

    <!-- Container do Chat -->
    <div class="bg-white rounded-lg shadow-sm border flex flex-col" style="height: calc(100vh - 320px); max-height: 800px;">
        <!-- Mensagens -->
        <div class="flex-1 overflow-y-auto p-4 bg-gray-50" id="mensagensContainer">
            <div class="space-y-4">
                <?php if (empty($mensagens)): ?>
                    <div class="text-center text-gray-500 py-8">
                        <p>Nenhuma mensagem ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($mensagens as $mensagem): ?>
                        <?php $isAdmin = ($mensagem['remetente_tipo'] ?? '') === 'admin'; ?>
                        <div class="flex <?= $isAdmin ? 'justify-start' : 'justify-end' ?>">
                            <div class="max-w-2xl">
                                <div class="flex items-center gap-2 mb-1 <?= $isAdmin ? '' : 'flex-row-reverse' ?>">
                                    <span class="text-xs font-medium text-gray-700">
                                        <?= htmlspecialchars($mensagem['remetente_nome'] ?? 'Você') ?>
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <?= date('d/m/Y H:i', strtotime($mensagem['criado_em'] ?? 'now')) ?>
                                    </span>
                                </div>
                                <div class="<?= $isAdmin ? 'bg-white border border-gray-200 text-gray-800' : 'bg-blue-500 text-white' ?> rounded-lg px-4 py-3 shadow-sm">
                                    <div class="text-sm ticket-message-content"><?= ticket_message_html($mensagem['mensagem'] ?? '') ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Envio de Mensagem -->
        <?php if ($ticket['status'] !== 'fechado'): ?>
        <div class="p-4 border-t bg-white">
            <form id="formEnviarMensagem">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                
                <div class="flex gap-2 items-end">
                    <div class="flex-1" id="quill-reply-container">
                        <div id="quill-reply"></div>
                    </div>
                    <button type="submit" id="btnEnviarMsg"
                            class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium transition-colors flex-shrink-0">
                        Enviar
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="p-4 border-t bg-gray-100 text-center text-gray-500">
            Este ticket está fechado e não aceita novas mensagens.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Lightbox para ampliar imagens -->
<div id="imgLightbox" class="img-lightbox" onclick="this.classList.remove('active')">
    <img id="imgLightboxImg" src="" alt="Imagem ampliada">
</div>

<!-- Quill JS -->
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const csrfToken = <?= json_encode($csrf_token) ?>;
const uploadUrl = '<?= URL ?>/tickets/upload-imagem';
const enviarUrl = '<?= URL ?>/tickets/enviar-mensagem';

// Scroll automático para o final
const mensagensContainer = document.getElementById('mensagensContainer');
if (mensagensContainer) {
    mensagensContainer.scrollTop = mensagensContainer.scrollHeight;
}

// Lightbox para imagens no chat
document.querySelectorAll('.ticket-message-content img').forEach(img => {
    img.addEventListener('click', function() {
        document.getElementById('imgLightboxImg').src = this.src;
        document.getElementById('imgLightbox').classList.add('active');
    });
});

<?php if ($ticket['status'] !== 'fechado'): ?>
// Upload de imagem para o Quill reply
async function uploadAndInsertImageReply(file, quill) {
    const formData = new FormData();
    formData.append('imagem', file);
    formData.append('_token', csrfToken);

    try {
        const res = await fetch(uploadUrl, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.url) {
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', data.url);
            quill.setSelection(range.index + 1);
        } else {
            alert('Erro ao enviar imagem: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (err) {
        console.error('Upload error:', err);
        alert('Erro ao enviar imagem. Tente novamente.');
    }
}

const quillReply = new Quill('#quill-reply', {
    theme: 'snow',
    placeholder: 'Digite sua mensagem...',
    modules: {
        toolbar: {
            container: [
                ['bold', 'italic'],
                ['link', 'image'],
                ['clean']
            ],
            handlers: {
                'image': function() {
                    const input = document.createElement('input');
                    input.setAttribute('type', 'file');
                    input.setAttribute('accept', 'image/jpeg,image/png,image/gif,image/webp');
                    input.click();
                    input.onchange = async () => {
                        if (input.files[0]) {
                            await uploadAndInsertImageReply(input.files[0], quillReply);
                        }
                    };
                }
            }
        },
        clipboard: { matchVisual: false }
    }
});

// Paste de imagens no reply
quillReply.root.addEventListener('paste', function(e) {
    const items = (e.clipboardData || e.originalEvent.clipboardData).items;
    for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            e.preventDefault();
            uploadAndInsertImageReply(items[i].getAsFile(), quillReply);
            return;
        }
    }
});

quillReply.root.addEventListener('drop', function(e) {
    e.preventDefault();
    const files = e.dataTransfer.files;
    for (let i = 0; i < files.length; i++) {
        if (files[i].type.indexOf('image') !== -1) {
            uploadAndInsertImageReply(files[i], quillReply);
        }
    }
});

// Enviar mensagem
document.getElementById('formEnviarMensagem').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const htmlContent = quillReply.root.innerHTML.trim();
    const textContent = quillReply.getText().trim();

    if (!textContent || textContent === '') {
        alert('Digite uma mensagem');
        return;
    }

    const btn = document.getElementById('btnEnviarMsg');
    btn.disabled = true;
    btn.textContent = 'Enviando...';

    const formData = new FormData(this);
    formData.set('mensagem', htmlContent);

    try {
        const response = await fetch(enviarUrl, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível enviar a mensagem'));
            btn.disabled = false;
            btn.textContent = 'Enviar';
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao enviar mensagem. Tente novamente.');
        btn.disabled = false;
        btn.textContent = 'Enviar';
    }
});
<?php endif; ?>
</script>
