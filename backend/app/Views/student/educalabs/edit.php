<!-- Header Section -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">EducaLabs: <?= htmlspecialchars($project['name'] ?? '') ?></h1>
            <p class="text-gray-600 mt-1">Converse com a IA para criar seu app.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= URL ?>/educalabs" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Voltar
            </a>
            <button type="button" id="shareButton"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Compartilhar
            </button>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Chat -->
    <div class="bg-white rounded-xl shadow-lg flex flex-col">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Conversa</h2>
            <p class="text-sm text-gray-500">Descreva o que deseja criar e clique em gerar.</p>
        </div>
        <div id="messages" class="flex-1 p-4 overflow-y-auto space-y-3" style="max-height: 520px;">
            <?php foreach (($project['messages'] ?? []) as $message): ?>
                <div class="flex <?= ($message['role'] ?? '') === 'user' ? 'justify-end' : 'justify-start' ?>">
                    <div class="<?= ($message['role'] ?? '') === 'user' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-800' ?> px-4 py-2 rounded-lg max-w-[80%] text-sm">
                        <?= htmlspecialchars($message['content'] ?? '') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="p-4 border-t border-gray-200">
            <form id="messageForm" class="flex items-center gap-3">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <textarea name="message" id="messageInput" rows="2"
                          class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                          placeholder="Ex: Crie um app de agendamento para pets"></textarea>
                <button type="submit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Enviar
                </button>
            </form>
            <button type="button" id="generateButton"
                    class="mt-3 w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold">
                Gerar app com IA
            </button>
        </div>
    </div>

    <!-- Preview -->
    <div class="bg-white rounded-xl shadow-lg flex flex-col">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Preview</h2>
            <a href="<?= htmlspecialchars($preview_url) ?>" target="_blank" rel="noopener noreferrer"
               class="text-sm text-blue-600 hover:text-blue-700">
                Abrir em nova aba
            </a>
        </div>
        <div class="flex-1 p-2">
            <iframe id="previewFrame" src="<?= htmlspecialchars($preview_url) ?>" class="w-full h-[520px] border border-gray-200 rounded-lg"></iframe>
        </div>
    </div>
</div>

<script>
const shareUrl = '<?= htmlspecialchars($share_url) ?>';
const projectId = '<?= htmlspecialchars($project['id']) ?>';
const csrfToken = '<?= htmlspecialchars($csrf_token) ?>';

function appendMessage(role, content) {
    const container = document.getElementById('messages');
    const wrapper = document.createElement('div');
    wrapper.className = 'flex ' + (role === 'user' ? 'justify-end' : 'justify-start');

    const bubble = document.createElement('div');
    bubble.className = (role === 'user' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-800') + ' px-4 py-2 rounded-lg max-w-[80%] text-sm';
    bubble.textContent = content;

    wrapper.appendChild(bubble);
    container.appendChild(wrapper);
    container.scrollTop = container.scrollHeight;
}

document.getElementById('shareButton').addEventListener('click', function() {
    navigator.clipboard.writeText(shareUrl).then(() => {
        const original = this.textContent;
        this.textContent = 'Copiado!';
        setTimeout(() => { this.textContent = original; }, 1500);
    });
});

document.getElementById('messageForm').addEventListener('submit', function(event) {
    event.preventDefault();
    const input = document.getElementById('messageInput');
    const text = input.value.trim();
    if (!text) {
        return;
    }
    appendMessage('user', text);
    input.value = '';

    const formData = new FormData();
    formData.append('_token', csrfToken);
    formData.append('message', text);

    fetch(`<?= URL ?>/educalabs/projetos/${projectId}/mensagem`, {
        method: 'POST',
        body: formData
    }).then(response => response.json()).then(() => {});
});

document.getElementById('generateButton').addEventListener('click', function() {
    const button = this;
    button.disabled = true;
    button.textContent = 'Gerando...';
    appendMessage('assistant', 'Estou fazendo os ajustes no seu app...');

    const formData = new FormData();
    formData.append('_token', csrfToken);

    fetch(`<?= URL ?>/educalabs/projetos/${projectId}/gerar`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.summary) {
                appendMessage('assistant', data.summary);
            }
            const iframe = document.getElementById('previewFrame');
            const url = data.preview_url + '?t=' + Date.now();
            iframe.src = url;
        } else {
            appendMessage('assistant', data.error || 'Erro ao gerar.');
        }
    })
    .catch(() => {
        appendMessage('assistant', 'Erro ao gerar app.');
    })
    .finally(() => {
        button.disabled = false;
        button.textContent = 'Gerar app com IA';
    });
});
</script>

