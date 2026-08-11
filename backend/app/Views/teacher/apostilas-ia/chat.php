<?php
$iaName = LayoutHelper::getIaName();
$iaAvatarUrl = URL . '/public/assets/tudinha.png';
$iaAvatarHtml = '<div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 bg-indigo-100 flex items-center justify-center self-end shadow-sm">'
    . '<img src="' . htmlspecialchars($iaAvatarUrl) . '" alt="' . htmlspecialchars($iaName) . '" class="w-full h-full object-cover" '
    . 'onerror="if(!this.dataset.fb){this.dataset.fb=1;this.src=this.src.replace(\'/public/\',\'/\');}else{this.style.display=\'none\';var e=this.parentElement.querySelector(\'[data-ia-emoji]\');if(e)e.classList.remove(\'hidden\');}">'
    . '<span data-ia-emoji class="hidden text-base">&#129302;</span>'
    . '</div>';
?>
<style>
    .chat-markdown p { margin-bottom: 0.5rem; }
    .chat-markdown p:last-child { margin-bottom: 0; }
    .chat-markdown ul, .chat-markdown ol { margin: 0.5rem 0 0.5rem 1.25rem; }
    .chat-markdown ul { list-style: disc; }
    .chat-markdown ol { list-style: decimal; }
    .chat-markdown li { margin-bottom: 0.25rem; }
    .chat-markdown strong { font-weight: 600; }
    .chat-markdown h1, .chat-markdown h2, .chat-markdown h3 { font-weight: 600; margin: 0.75rem 0 0.4rem; }
    .chat-markdown h1 { font-size: 1.05rem; }
    .chat-markdown h2 { font-size: 1rem; }
    .chat-markdown h3 { font-size: 0.95rem; }
    .chat-markdown code { background: rgba(0,0,0,0.06); padding: 0.1rem 0.3rem; border-radius: 0.25rem; font-size: 0.85em; }
</style>

<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
<script>
    // Definida aqui (antes do histórico de mensagens no HTML) para já estar
    // disponível quando os scripts inline de cada mensagem do histórico
    // rodarem — eles aparecem antes do script principal no final da página.
    window.renderMarkdownSafe = function (container, text) {
        if (typeof marked === 'undefined' || typeof DOMPurify === 'undefined') {
            container.textContent = text || ''; // fallback seguro se o CDN falhar ao carregar
            return;
        }
        var rawHtml = marked.parse(text || '');
        container.innerHTML = DOMPurify.sanitize(rawHtml);
    };
</script>

<div class="mb-4 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars((string)$item['titulo']) ?></h1>
        <p class="text-sm text-gray-600">IA da Apostila · <?= (int)($item['total_paginas'] ?? 0) ?> páginas</p>
    </div>
    <a href="<?= URL ?>/professor/apostilas-ia" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Voltar</a>
</div>

<div class="flex gap-4 h-[70vh]">
<?php
$nova_sessao_url = ($chat_base_url ?? '') . '/sessao/nova';
include __DIR__ . '/../../components/apostila_ia_chat_sessoes_sidebar.php';
?>
<div class="flex-1 min-w-0 bg-white rounded-xl shadow border border-gray-200 flex flex-col">
    <div id="chatMessages" class="flex-1 overflow-y-auto p-4 space-y-4">
        <?php if (empty($historico)): ?>
            <div class="text-center text-gray-400 text-sm py-8" id="emptyState">
                Faça uma pergunta sobre a apostila ou use uma das sugestões abaixo.
            </div>
        <?php else: ?>
            <?php foreach ($historico as $msg): ?>
                <div class="flex justify-end">
                    <div class="max-w-[80%] bg-indigo-600 text-white rounded-2xl rounded-br-sm px-4 py-2 text-sm chat-bubble-user"></div>
                </div>
                <div class="flex justify-start items-end gap-2">
                    <?= $iaAvatarHtml ?>
                    <div class="max-w-[80%] bg-gray-100 text-gray-900 rounded-2xl rounded-bl-sm px-4 py-2 text-sm chat-bubble-ai">
                        <div class="chat-bubble-ai-text chat-markdown"></div>
                        <?php
                        $paginasUsadas = [];
                        if (!empty($msg['paginas_usadas'])) {
                            $decoded = json_decode((string)$msg['paginas_usadas'], true);
                            if (is_array($decoded)) {
                                $paginasUsadas = $decoded;
                            }
                        }
                        ?>
                        <?php if (!empty($paginasUsadas)): ?>
                            <p class="text-xs text-gray-500 mt-2 flex flex-wrap items-center gap-1">
                                <span>Páginas:</span>
                                <?php foreach ($paginasUsadas as $idx => $pag): ?>
                                    <?php if ($idx > 0): ?><span>,</span><?php endif; ?>
                                    <a href="<?= URL ?>/professor/apostilas-ia/<?= (int)$item['id'] ?>/pagina/<?= (int)$pag ?>/imagem"
                                       target="_blank" rel="noopener noreferrer"
                                       class="text-indigo-600 hover:text-indigo-800 underline font-medium"
                                       title="Ver imagem da página <?= (int)$pag ?>"><?= (int)$pag ?></a>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <script>
                    (function () {
                        var scripts = document.querySelectorAll('.chat-bubble-user, .chat-bubble-ai-text');
                        var lastUser = document.querySelectorAll('.chat-bubble-user');
                        var lastAi = document.querySelectorAll('.chat-bubble-ai-text');
                        lastUser[lastUser.length - 1].textContent = <?= json_encode((string)$msg['pergunta']) ?>;
                        window.renderMarkdownSafe(lastAi[lastAi.length - 1], <?= json_encode((string)$msg['resposta']) ?>);
                    })();
                </script>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="border-t border-gray-100 p-3 flex flex-wrap gap-2" id="suggestions">
        <?php foreach (($sugestoes_chat ?? []) as $sugestao): ?>
            <button type="button" class="suggestion-btn text-xs px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700"><?= htmlspecialchars((string)$sugestao) ?></button>
        <?php endforeach; ?>
        <button type="button" id="gerarSlidesBtn" class="text-xs px-3 py-1.5 rounded-full bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium">📊 Gerar slides de um capítulo</button>
    </div>

    <form id="chatForm" class="border-t border-gray-100 p-3 flex gap-2 items-end">
        <textarea id="perguntaInput" rows="1" placeholder="Pergunte algo sobre a apostila... (Enter envia, Shift+Enter nova linha)"
                  class="flex-1 text-sm border border-gray-300 rounded-lg p-2 resize-none min-h-[42px] max-h-32 leading-relaxed"></textarea>
        <div class="flex flex-col gap-2 shrink-0">
            <button type="button" id="pararBtn" class="hidden px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">Parar</button>
            <button type="submit" id="enviarBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Enviar</button>
        </div>
    </form>
</div>
</div>

<?php
$chatStreamUrl = URL . '/professor/apostilas-ia/' . (int)$item['id'] . '/chat-stream';
$imagemPaginaUrlBase = URL . '/professor/apostilas-ia/' . (int)$item['id'] . '/pagina/';
$sessaoIdAtiva = (int)($sessao_ativa['id'] ?? 0);
$erroConexaoMsg = 'Falha de conexão ao consultar a IA da Apostila. Verifique sua conexão e tente novamente.';
include __DIR__ . '/../../components/apostila_ia_chat_script.php';
?>

<script>
(function () {
    var csrfToken = <?= json_encode($csrf_token) ?>;
    var messagesEl = document.getElementById('chatMessages');
    var emptyState = document.getElementById('emptyState');

    function appendUserMessageLocal(text) {
        if (emptyState) {
            emptyState.remove();
            emptyState = null;
        }
        var wrapper = document.createElement('div');
        wrapper.className = 'flex justify-end';
        var bubble = document.createElement('div');
        bubble.className = 'max-w-[80%] bg-indigo-600 text-white rounded-2xl rounded-br-sm px-4 py-2 text-sm';
        bubble.textContent = text;
        wrapper.appendChild(bubble);
        messagesEl.appendChild(wrapper);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendErrorMessageLocal(text) {
        var wrapper = document.createElement('div');
        wrapper.className = 'flex justify-start';
        var bubble = document.createElement('div');
        bubble.className = 'max-w-[80%] bg-red-50 text-red-700 border border-red-200 rounded-2xl px-4 py-2 text-sm';
        bubble.textContent = text;
        wrapper.appendChild(bubble);
        messagesEl.appendChild(wrapper);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // Gerar slides de um capítulo/tema, usando conteúdo real da apostila (RAG)
    // como base, e reaproveitando o gerador de slides (Gamma) já existente em
    // /professor/gerar-slides/api. O slide final de fonte é sempre incluído.
    var gerarSlidesBtn = document.getElementById('gerarSlidesBtn');
    var prepararSlidesUrl = <?= json_encode(URL . '/professor/apostilas-ia/' . (int)$item['id'] . '/preparar-slides') ?>;
    var gerarSlidesApiUrl = <?= json_encode(URL . '/professor/gerar-slides/api') ?>;

    gerarSlidesBtn.addEventListener('click', function () {
        var capituloOuTema = window.prompt('Sobre qual capítulo ou tema você quer gerar os slides?');
        if (!capituloOuTema || !capituloOuTema.trim()) {
            return;
        }
        capituloOuTema = capituloOuTema.trim();

        if (emptyState) {
            emptyState.remove();
            emptyState = null;
        }
        appendUserMessageLocal('Gerar slides sobre: ' + capituloOuTema);

        var statusBubble = document.createElement('div');
        statusBubble.className = 'flex justify-start';
        var statusInner = document.createElement('div');
        statusInner.className = 'max-w-[80%] bg-gray-100 text-gray-500 rounded-2xl rounded-bl-sm px-4 py-2 text-sm';
        statusInner.textContent = 'Buscando conteúdo da apostila e gerando slides — isso pode levar até 1 minuto...';
        statusBubble.appendChild(statusInner);
        messagesEl.appendChild(statusBubble);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        var prepararFormData = new FormData();
        prepararFormData.append('_token', csrfToken);
        prepararFormData.append('capitulo_ou_tema', capituloOuTema);
        prepararFormData.append('num_slides', '8');

        fetch(prepararSlidesUrl, {
            method: 'POST',
            body: prepararFormData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) { return res.json(); })
            .then(function (preparado) {
                if (preparado.error) {
                    throw new Error(preparado.error);
                }

                return fetch(gerarSlidesApiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conteudo: preparado.conteudo,
                        slides: 8,
                        tema: preparado.tema || capituloOuTema,
                        nivelDetalhamento: 'medium',
                        estiloImagem: '',
                        salvarSlide: true
                    })
                });
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                statusInner.remove();
                statusBubble.remove();

                if (data.error) {
                    appendErrorMessageLocal('Não foi possível gerar os slides: ' + data.error);
                    return;
                }

                var wrapper = document.createElement('div');
                wrapper.className = 'flex justify-start';
                var bubble = document.createElement('div');
                bubble.className = 'max-w-[80%] bg-purple-50 text-purple-900 border border-purple-200 rounded-2xl rounded-bl-sm px-4 py-2 text-sm';

                var msg = document.createElement('p');
                msg.textContent = 'Slides gerados com sucesso!';
                bubble.appendChild(msg);

                var link = document.createElement('a');
                link.href = data.url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.className = 'inline-block mt-2 text-purple-700 underline font-medium';
                link.textContent = 'Abrir apresentação no Gamma →';
                bubble.appendChild(link);

                wrapper.appendChild(bubble);
                messagesEl.appendChild(wrapper);
                messagesEl.scrollTop = messagesEl.scrollHeight;
            })
            .catch(function (err) {
                statusInner.remove();
                statusBubble.remove();
                appendErrorMessageLocal('Falha ao gerar slides: ' + (err && err.message ? err.message : 'erro desconhecido'));
            });
    });

})();
</script>
