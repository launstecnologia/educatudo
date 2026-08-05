<?php
/**
 * Script compartilhado do chat apostila_ia com streaming SSE (Fase A).
 * Variáveis esperadas: $chatStreamUrl, $imagemPaginaUrlBase, $csrf_token, $iaName, $iaAvatarUrl
 * Opcional: $erroConexaoMsg (string), $sessaoIdAtiva (int)
 */
$erroConexaoMsg = $erroConexaoMsg ?? 'Falha de conexão. Verifique sua conexão e tente novamente.';
$sessaoIdAtiva = (int)($sessaoIdAtiva ?? 0);
?>
<script>
(function () {
    var csrfToken = <?= json_encode($csrf_token) ?>;
    var chatStreamUrl = <?= json_encode($chatStreamUrl) ?>;
    var imagemPaginaUrlBase = <?= json_encode($imagemPaginaUrlBase) ?>;
    var iaName = <?= json_encode($iaName) ?>;
    var iaAvatarUrl = <?= json_encode($iaAvatarUrl) ?>;
    var erroConexaoMsg = <?= json_encode($erroConexaoMsg) ?>;
    var sessaoId = <?= (int)$sessaoIdAtiva ?>;

    var messagesEl = document.getElementById('chatMessages');
    var emptyState = document.getElementById('emptyState');
    var form = document.getElementById('chatForm');
    var input = document.getElementById('perguntaInput');
    var enviarBtn = document.getElementById('enviarBtn');
    var pararBtn = document.getElementById('pararBtn');

    var abortController = null;
    var markdownTimer = null;
    var ultimaPergunta = '';
    // Perguntas amplas (ex.: "resuma esta apostila") fazem a IA citar dezenas
    // de páginas espalhadas pelo livro para justificar um resumo abrangente —
    // sem esse limite, a resposta virava uma galeria enorme de imagens que
    // ninguém pediu. Muitas páginas citadas = resposta ampla, não uma
    // pergunta específica sobre conteúdo visual; nesse caso não faz sentido
    // mostrar nenhuma imagem, só os links de página.
    var MAX_PAGINAS_COM_IMAGEM_EXIBIDAS = 3;

    function criarAvatarIa() {
        var avatar = document.createElement('div');
        avatar.className = 'w-8 h-8 rounded-full overflow-hidden flex-shrink-0 bg-indigo-100 flex items-center justify-center self-end shadow-sm';
        var img = document.createElement('img');
        img.src = iaAvatarUrl;
        img.alt = iaName;
        img.className = 'w-full h-full object-cover';
        img.onerror = function () {
            if (!img.dataset.fb) {
                img.dataset.fb = '1';
                img.src = img.src.replace('/public/', '/');
            } else {
                img.style.display = 'none';
                var e = avatar.querySelector('[data-ia-emoji]');
                if (e) { e.classList.remove('hidden'); }
            }
        };
        var emoji = document.createElement('span');
        emoji.setAttribute('data-ia-emoji', '');
        emoji.className = 'hidden text-base';
        emoji.textContent = '\uD83E\uDD16';
        avatar.appendChild(img);
        avatar.appendChild(emoji);
        return avatar;
    }

    function ajustarAlturaTextarea() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 128) + 'px';
    }

    function setEnviando(ativo) {
        if (enviarBtn) {
            enviarBtn.disabled = ativo;
            enviarBtn.classList.toggle('opacity-50', ativo);
        }
        if (pararBtn) {
            pararBtn.classList.toggle('hidden', !ativo);
        }
        input.disabled = ativo;
    }

    function appendUserMessage(text) {
        if (emptyState) {
            emptyState.remove();
            emptyState = null;
        }
        var wrapper = document.createElement('div');
        wrapper.className = 'flex justify-end';
        var bubble = document.createElement('div');
        bubble.className = 'max-w-[80%] bg-indigo-600 text-white rounded-2xl rounded-br-sm px-4 py-2 text-sm whitespace-pre-wrap';
        bubble.textContent = text;
        wrapper.appendChild(bubble);
        messagesEl.appendChild(wrapper);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function criarLinksPaginas(paginasUsadas) {
        var fontesP = document.createElement('p');
        fontesP.className = 'text-xs text-gray-500 mt-2 flex flex-wrap items-center gap-1';
        var label = document.createElement('span');
        label.textContent = 'Páginas:';
        fontesP.appendChild(label);
        paginasUsadas.forEach(function (numeroPagina, idx) {
            if (idx > 0) {
                fontesP.appendChild(document.createTextNode(','));
            }
            var link = document.createElement('a');
            link.href = imagemPaginaUrlBase + numeroPagina + '/imagem';
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'text-indigo-600 hover:text-indigo-800 underline font-medium';
            link.textContent = String(numeroPagina);
            link.title = 'Ver imagem da página ' + numeroPagina;
            fontesP.appendChild(link);
        });
        return fontesP;
    }

    function appendImagensPaginas(bubble, paginasComImagem) {
        if (!Array.isArray(paginasComImagem) || paginasComImagem.length === 0) {
            return;
        }
        // Muitas páginas citadas = resposta ampla (ex.: resumo geral), não
        // pergunta específica sobre uma imagem — nesse caso não mostra
        // nenhuma imagem, só os links de página (ver criarLinksPaginas).
        if (paginasComImagem.length > MAX_PAGINAS_COM_IMAGEM_EXIBIDAS) {
            return;
        }
        paginasComImagem.forEach(function (numeroPagina) {
            var img = document.createElement('img');
            img.src = imagemPaginaUrlBase + numeroPagina + '/imagem';
            img.alt = 'Imagem da página ' + numeroPagina;
            img.loading = 'lazy';
            img.className = 'mt-2 rounded-lg border border-gray-200 max-w-full max-h-64 object-contain cursor-pointer';
            img.title = 'Clique para abrir a página ' + numeroPagina;
            img.onclick = function () {
                window.open(imagemPaginaUrlBase + numeroPagina + '/imagem', '_blank', 'noopener,noreferrer');
            };
            img.onerror = function () { img.remove(); };
            bubble.appendChild(img);
        });
    }

    function criarBubbleIaStream() {
        var wrapper = document.createElement('div');
        wrapper.className = 'flex justify-start items-end gap-2 ia-stream-wrapper ia-resposta-wrapper';
        wrapper.appendChild(criarAvatarIa());
        var bubble = document.createElement('div');
        bubble.className = 'max-w-[80%] bg-gray-100 text-gray-900 rounded-2xl rounded-bl-sm px-4 py-2 text-sm';
        var textDiv = document.createElement('div');
        textDiv.className = 'chat-markdown ia-stream-text';
        bubble.appendChild(textDiv);
        var statusEl = document.createElement('p');
        statusEl.className = 'text-xs text-gray-400 mt-1 ia-stream-status';
        statusEl.textContent = iaName + ' está consultando a apostila...';
        bubble.appendChild(statusEl);
        wrapper.appendChild(bubble);
        messagesEl.appendChild(wrapper);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return { wrapper: wrapper, bubble: bubble, textDiv: textDiv, statusEl: statusEl, texto: '' };
    }

    function renderMarkdownThrottled(streamState) {
        if (markdownTimer) {
            return;
        }
        markdownTimer = window.setTimeout(function () {
            markdownTimer = null;
            window.renderMarkdownSafe(streamState.textDiv, streamState.texto);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }, 80);
    }

    function criarAcoesResposta(bubble, textoPlano, perguntaOrigem) {
        var acoes = document.createElement('div');
        acoes.className = 'flex flex-wrap gap-3 mt-2 pt-2 border-t border-gray-200/70';

        var btnCopiar = document.createElement('button');
        btnCopiar.type = 'button';
        btnCopiar.className = 'text-xs text-gray-500 hover:text-indigo-600 transition-colors';
        btnCopiar.textContent = 'Copiar';
        btnCopiar.addEventListener('click', function () {
            var texto = textoPlano || '';
            if (!texto) {
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(texto).then(function () {
                    btnCopiar.textContent = 'Copiado!';
                    window.setTimeout(function () { btnCopiar.textContent = 'Copiar'; }, 1500);
                }).catch(function () {
                    btnCopiar.textContent = 'Erro ao copiar';
                });
            }
        });
        acoes.appendChild(btnCopiar);

        if (perguntaOrigem) {
            var btnRegenerar = document.createElement('button');
            btnRegenerar.type = 'button';
            btnRegenerar.className = 'text-xs text-gray-500 hover:text-indigo-600 transition-colors';
            btnRegenerar.textContent = 'Regenerar';
            btnRegenerar.addEventListener('click', function () {
                if (abortController) {
                    return;
                }
                var wrapper = bubble.closest('.ia-resposta-wrapper');
                if (wrapper && wrapper.parentNode) {
                    wrapper.parentNode.removeChild(wrapper);
                }
                enviarPergunta(perguntaOrigem, { skipUserBubble: true });
            });
            acoes.appendChild(btnRegenerar);
        }

        bubble.appendChild(acoes);
    }

    function finalizarBubbleIa(streamState, paginasUsadas, paginasComImagem, perguntaOrigem) {
        if (streamState.statusEl) {
            streamState.statusEl.remove();
            streamState.statusEl = null;
        }
        if (markdownTimer) {
            clearTimeout(markdownTimer);
            markdownTimer = null;
        }
        window.renderMarkdownSafe(streamState.textDiv, streamState.texto);
        appendImagensPaginas(streamState.bubble, paginasComImagem);
        if (Array.isArray(paginasUsadas) && paginasUsadas.length > 0) {
            streamState.bubble.appendChild(criarLinksPaginas(paginasUsadas));
        }
        criarAcoesResposta(streamState.bubble, streamState.texto, perguntaOrigem || ultimaPergunta);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendErrorMessage(text) {
        var wrapper = document.createElement('div');
        wrapper.className = 'flex justify-start';
        var bubble = document.createElement('div');
        bubble.className = 'max-w-[80%] bg-red-50 text-red-700 border border-red-200 rounded-2xl px-4 py-2 text-sm';
        bubble.textContent = text;
        wrapper.appendChild(bubble);
        messagesEl.appendChild(wrapper);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function parsearEventosSse(buffer) {
        var eventos = [];
        var blocos = buffer.split('\n\n');
        var restante = blocos.pop() || '';
        blocos.forEach(function (bloco) {
            if (!bloco.trim()) {
                return;
            }
            var linhas = bloco.split('\n');
            var tipo = 'message';
            var dados = '';
            linhas.forEach(function (linha) {
                if (linha.indexOf('event:') === 0) {
                    tipo = linha.slice(6).trim();
                } else if (linha.indexOf('data:') === 0) {
                    dados = linha.slice(5).trim();
                }
            });
            if (dados) {
                eventos.push({ tipo: tipo, dados: dados });
            }
        });
        return { eventos: eventos, restante: restante };
    }

    function processarEventoSse(evento, streamState) {
        var payload;
        try {
            payload = JSON.parse(evento.dados);
        } catch (e) {
            return;
        }

        if (evento.tipo === 'status' && streamState.statusEl) {
            if (payload.status === 'consultando') {
                streamState.statusEl.textContent = iaName + ' está consultando a apostila...';
            }
            return;
        }

        if (evento.tipo === 'token' && payload.text) {
            if (streamState.statusEl) {
                streamState.statusEl.textContent = iaName + ' está respondendo...';
            }
            streamState.texto += payload.text;
            renderMarkdownThrottled(streamState);
            return;
        }

        if (evento.tipo === 'done') {
            streamState.concluido = true;
            if (payload.resposta) {
                streamState.texto = payload.resposta;
            }
            finalizarBubbleIa(
                streamState,
                payload.paginas_usadas || [],
                payload.paginas_com_imagem || [],
                streamState.perguntaOrigem
            );
            return;
        }

        if (evento.tipo === 'error') {
            if (streamState.wrapper && streamState.wrapper.parentNode) {
                streamState.wrapper.remove();
            }
            appendErrorMessage(payload.error || 'Erro ao consultar a IA.');
        }
    }

    async function enviarPergunta(pergunta, opts) {
        opts = opts || {};
        pergunta = (pergunta || '').trim();
        if (pergunta === '' || abortController) {
            return;
        }

        ultimaPergunta = pergunta;

        if (!opts.skipUserBubble) {
            appendUserMessage(pergunta);
            input.value = '';
            ajustarAlturaTextarea();
        }

        var streamState = criarBubbleIaStream();
        streamState.perguntaOrigem = pergunta;
        streamState.concluido = false;
        abortController = new AbortController();
        setEnviando(true);

        var formData = new FormData();
        formData.append('_token', csrfToken);
        formData.append('pergunta', pergunta);
        if (sessaoId > 0) {
            formData.append('sessao_id', String(sessaoId));
        }

        try {
            var res = await fetch(chatStreamUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/event-stream' },
                signal: abortController.signal
            });

            if (!res.ok || !res.body) {
                streamState.wrapper.remove();
                appendErrorMessage('Erro ' + res.status + ' ao consultar a IA.');
                return;
            }

            var reader = res.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';

            while (true) {
                var chunk = await reader.read();
                if (chunk.done) {
                    break;
                }
                buffer += decoder.decode(chunk.value, { stream: true });
                var parsed = parsearEventosSse(buffer);
                buffer = parsed.restante;
                parsed.eventos.forEach(function (ev) {
                    processarEventoSse(ev, streamState);
                });
            }

            if (buffer.trim()) {
                var finalParsed = parsearEventosSse(buffer + '\n\n');
                finalParsed.eventos.forEach(function (ev) {
                    processarEventoSse(ev, streamState);
                });
            }

            if (!streamState.concluido) {
                streamState.wrapper.remove();
                appendErrorMessage(
                    streamState.texto.trim() !== ''
                        ? 'A conexão com a IA foi interrompida antes de concluir a resposta.'
                        : 'A IA não retornou resposta. Verifique se o serviço apostila-ai está atualizado e em execução.'
                );
            } else if (streamState.texto.trim() === '' && streamState.statusEl) {
                streamState.wrapper.remove();
                appendErrorMessage('A IA retornou resposta vazia. Tente regenerar ou reformule a pergunta.');
            } else if (streamState.statusEl) {
                finalizarBubbleIa(streamState, [], [], streamState.perguntaOrigem);
            }
        } catch (err) {
            if (err && err.name === 'AbortError') {
                if (streamState.texto.trim() === '') {
                    streamState.wrapper.remove();
                } else {
                    finalizarBubbleIa(streamState, [], [], streamState.perguntaOrigem);
                }
            } else {
                streamState.wrapper.remove();
                appendErrorMessage(erroConexaoMsg);
            }
        } finally {
            abortController = null;
            setEnviando(false);
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        enviarPergunta(input.value);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    input.addEventListener('input', ajustarAlturaTextarea);

    if (pararBtn) {
        pararBtn.addEventListener('click', function () {
            if (abortController) {
                abortController.abort();
            }
        });
    }

    document.querySelectorAll('.suggestion-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.id === 'gerarSlidesBtn') {
                return;
            }
            enviarPergunta(btn.textContent);
        });
    });

    window.apostilaIaEnviarPergunta = enviarPergunta;
    ajustarAlturaTextarea();
    messagesEl.scrollTop = messagesEl.scrollHeight;
})();
</script>
