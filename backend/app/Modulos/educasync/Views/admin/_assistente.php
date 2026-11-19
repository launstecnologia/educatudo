<?php
/**
 * EducaSync — assistente em chat flutuante (padrão Tudinha / documentos).
 * Variáveis herdadas de show.php: $base, $cenarioId, $csrf_token, $assistenteDisponivel,
 * $classeFaixa, $classeCirculo.
 */
$assistenteAvatar = URL . '/static/images/avatar-assistente-documentos.webp';
$assistenteNome = 'Tudinha';
?>
<!-- 5. Assistente (atalho + chat flutuante) -->
<div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
    <div class="<?= $classeFaixa ?>">
        <div class="flex items-center gap-3 min-w-0">
            <span class="<?= $classeCirculo ?>">5</span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900">Assistente do EducaSync</p>
                <p class="text-sm text-gray-500 mt-0.5">Converse com a Tudinha sobre o cenário e peça propostas de restrição. Nada é aplicado sem confirmação.</p>
            </div>
        </div>
        <?php if ($assistenteDisponivel): ?>
        <button type="button" id="educasync-assistente-abrir-card"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 shadow-sm">
            <img src="<?= htmlspecialchars($assistenteAvatar) ?>" alt="" width="28" height="28" class="w-7 h-7 rounded-full object-cover bg-white">
            Abrir chat
        </button>
        <?php endif; ?>
    </div>
    <?php if (!$assistenteDisponivel): ?>
    <div class="p-5">
        <p class="text-sm text-amber-700">Ative os TudiCoins desta escola no Master (item "EducaSync — mensagem ao assistente") para usar o assistente.</p>
    </div>
    <?php endif; ?>
</div>

<?php if ($assistenteDisponivel): ?>
<div id="educasync-assistente-root"
     data-cenario="<?= (int) $cenarioId ?>"
     data-url-mensagem="<?= htmlspecialchars($base . '/assistente/mensagem') ?>"
     data-url-confirmar="<?= htmlspecialchars($base . '/assistente/confirmar-proposta') ?>"
     data-csrf="<?= htmlspecialchars($csrf_token) ?>"
     data-avatar="<?= htmlspecialchars($assistenteAvatar) ?>"
     data-nome="<?= htmlspecialchars($assistenteNome) ?>"
     style="position:fixed;right:16px;bottom:16px;z-index:99999;display:flex;flex-direction:column;align-items:flex-end;gap:12px;">

    <div id="educasync-assistente-painel"
         role="dialog"
         aria-label="Chat do assistente EducaSync"
         aria-hidden="true"
         hidden
         style="display:none;flex-direction:column;width:min(100vw - 24px, 420px);height:560px;max-height:calc(100vh - 24px);background:#fff;border:1px solid #e9d5ff;border-radius:16px;box-shadow:0 25px 50px -12px rgba(15,23,42,.4);overflow:hidden;">

        <header style="flex:0 0 auto;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:12px 14px;background:#5b21b6;color:#fff;">
            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                <img src="<?= htmlspecialchars($assistenteAvatar) ?>" width="36" height="36" alt=""
                     style="width:36px;height:36px;border-radius:9999px;object-fit:cover;background:#fff;flex-shrink:0;">
                <div style="min-width:0;">
                    <div style="font-size:14px;font-weight:700;line-height:1.2;"><?= htmlspecialchars($assistenteNome) ?></div>
                    <div id="educasync-assistente-status" style="font-size:11px;opacity:.85;line-height:1.2;">EducaSync · GPT-6 Astra</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                <button type="button" id="educasync-assistente-limpar"
                        style="border:0;border-radius:8px;padding:8px 10px;font-size:12px;font-weight:700;background:rgba(255,255,255,.18);color:#fff;cursor:pointer;">
                    Limpar
                </button>
                <button type="button" id="educasync-assistente-fechar" aria-label="Fechar chat" title="Fechar chat"
                        style="border:0;border-radius:8px;padding:8px 10px;font-size:12px;font-weight:700;background:#fff;color:#5b21b6;cursor:pointer;">
                    ✕ Fechar
                </button>
            </div>
        </header>

        <div id="educasync-assistente-lista" role="log" aria-live="polite"
             style="flex:1 1 auto;min-height:0;overflow-y:auto;padding:12px;background:#f8fafc;display:flex;flex-direction:column;gap:10px;">
            <div id="educasync-assistente-vazio" style="display:flex;align-items:flex-start;gap:10px;">
                <img src="<?= htmlspecialchars($assistenteAvatar) ?>" width="32" height="32" alt=""
                     style="width:32px;height:32px;border-radius:9999px;object-fit:cover;background:#fff;flex-shrink:0;">
                <div style="border:1px solid #e9d5ff;background:#faf5ff;border-radius:12px;padding:10px 12px;color:#3b0764;font-size:13px;">
                    <p style="margin:0 0 6px;font-weight:700;">Como posso ajudar?</p>
                    <ul style="margin:0;padding-left:18px;font-size:12px;line-height:1.45;color:#6b21a8;">
                        <li>“Como está o cenário?”</li>
                        <li>“Bloqueie o professor João na quarta”</li>
                        <li>“Há conflitos óbvios nas demandas?”</li>
                    </ul>
                </div>
            </div>
        </div>

        <form id="educasync-assistente-form"
              style="flex:0 0 auto;display:flex;gap:8px;align-items:flex-end;padding:12px;border-top:1px solid #e5e7eb;background:#fff;">
            <label for="educasync-assistente-campo" class="sr-only">Mensagem</label>
            <textarea id="educasync-assistente-campo" rows="2" maxlength="8000" required
                      placeholder="Escreva sua pergunta ou pedido… (Enter envia)"
                      style="flex:1 1 auto;min-width:0;font-size:14px;line-height:1.4;border:1px solid #d1d5db;border-radius:12px;padding:8px 10px;resize:none;color:#111827;background:#fff;"></textarea>
            <button type="submit" id="educasync-assistente-enviar"
                    style="flex:0 0 auto;border:0;border-radius:12px;padding:10px 14px;background:#5b21b6;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">
                Enviar
            </button>
        </form>
        <p style="flex:0 0 auto;margin:0;padding:0 12px 10px;font-size:11px;color:#6b7280;background:#fff;">
            <?php if (\App\Modulos\EducaSync\Services\EducaSyncAssistenteService::LIBERAR_SEM_TUDICOINS): ?>
            Modo local: TudiCoins liberados · modelo GPT-6 Astra.
            <?php else: ?>
            Cada mensagem consome TudiCoins da escola · modelo GPT-6 Astra.
            <?php endif; ?>
        </p>
    </div>

    <button type="button" id="educasync-assistente-abrir"
            aria-label="Abrir chat da Tudinha"
            aria-expanded="false"
            aria-controls="educasync-assistente-painel"
            style="border:0;border-radius:9999px;padding:3px;background:#5b21b6;box-shadow:0 10px 20px rgba(15,23,42,.25);cursor:pointer;">
        <img src="<?= htmlspecialchars($assistenteAvatar) ?>" width="56" height="56" alt="<?= htmlspecialchars($assistenteNome) ?>"
             style="width:56px;height:56px;border-radius:9999px;object-fit:cover;background:#fff;display:block;">
    </button>
</div>

<style>
@keyframes educasync-assistente-ponto {
    0%, 80%, 100% { opacity: .25; transform: translateY(0); }
    40% { opacity: 1; transform: translateY(-3px); }
}
.educasync-assistente-ponto {
    display: inline-block;
    width: 6px;
    height: 6px;
    margin: 0 2px;
    border-radius: 9999px;
    background: #7c3aed;
    animation: educasync-assistente-ponto 1.2s infinite ease-in-out;
}
.educasync-assistente-ponto:nth-child(2) { animation-delay: .15s; }
.educasync-assistente-ponto:nth-child(3) { animation-delay: .3s; }
</style>
<script>
(function () {
    var raiz = document.getElementById('educasync-assistente-root');
    if (!raiz) { return; }
    if (raiz.parentElement !== document.body) {
        document.body.appendChild(raiz);
    }

    var urlMensagem = raiz.getAttribute('data-url-mensagem');
    var urlConfirmar = raiz.getAttribute('data-url-confirmar');
    var csrf = raiz.getAttribute('data-csrf');
    var avatar = raiz.getAttribute('data-avatar') || '';
    var chave = 'educasync_assistente_' + raiz.getAttribute('data-cenario');
    var painel = document.getElementById('educasync-assistente-painel');
    var lista = document.getElementById('educasync-assistente-lista');
    var vazio = document.getElementById('educasync-assistente-vazio');
    var form = document.getElementById('educasync-assistente-form');
    var campo = document.getElementById('educasync-assistente-campo');
    var botao = document.getElementById('educasync-assistente-enviar');
    var botaoLimpar = document.getElementById('educasync-assistente-limpar');
    var botaoAbrir = document.getElementById('educasync-assistente-abrir');
    var botaoAbrirCard = document.getElementById('educasync-assistente-abrir-card');
    var botaoFechar = document.getElementById('educasync-assistente-fechar');
    var statusEl = document.getElementById('educasync-assistente-status');
    var STATUS_PADRAO = 'EducaSync · GPT-6 Astra';
    var MAX_ENTRADAS = 20;
    var entradas = [];
    var pensandoEl = null;
    var enviando = false;

    try { entradas = JSON.parse(sessionStorage.getItem(chave) || '[]'); } catch (e) { entradas = []; }
    if (!Array.isArray(entradas)) { entradas = []; }

    function salvar() {
        try { sessionStorage.setItem(chave, JSON.stringify(entradas.slice(-MAX_ENTRADAS))); } catch (e) {}
    }

    function abrirPainel() {
        painel.hidden = false;
        painel.style.display = 'flex';
        painel.setAttribute('aria-hidden', 'false');
        botaoAbrir.setAttribute('aria-expanded', 'true');
        botaoAbrir.style.display = 'none';
        setTimeout(function () {
            campo.focus();
            lista.scrollTop = lista.scrollHeight;
        }, 40);
    }

    function fecharPainel() {
        painel.style.display = 'none';
        painel.hidden = true;
        painel.setAttribute('aria-hidden', 'true');
        botaoAbrir.setAttribute('aria-expanded', 'false');
        botaoAbrir.style.display = 'inline-flex';
    }

    function historicoParaEnvio() {
        return entradas.filter(function (e) { return !e.erro; }).slice(-10).map(function (e) {
            return { role: e.role, content: e.content };
        });
    }

    function mostrarPensando() {
        if (pensandoEl) { return; }
        if (vazio) { vazio.remove(); vazio = null; }
        if (statusEl) { statusEl.textContent = 'Pensando…'; }
        botao.disabled = true;
        botao.textContent = 'Aguarde';
        botao.style.opacity = '0.75';
        campo.disabled = true;

        pensandoEl = document.createElement('div');
        pensandoEl.id = 'educasync-assistente-pensando';
        pensandoEl.setAttribute('aria-live', 'polite');
        pensandoEl.style.cssText = 'display:flex;align-items:flex-end;gap:8px;justify-content:flex-start;';

        if (avatar) {
            var img = document.createElement('img');
            img.src = avatar;
            img.alt = '';
            img.width = 28;
            img.height = 28;
            img.style.cssText = 'width:28px;height:28px;border-radius:9999px;object-fit:cover;background:#fff;flex-shrink:0;';
            pensandoEl.appendChild(img);
        }

        var balao = document.createElement('div');
        balao.style.cssText = 'max-width:85%;border-radius:16px 16px 16px 4px;padding:10px 14px;font-size:13px;line-height:1.45;background:#faf5ff;color:#5b21b6;border:1px solid #e9d5ff;box-shadow:0 1px 2px rgba(0,0,0,.06);';
        balao.innerHTML = '<span style="font-weight:700;margin-right:8px;">Tudinha está pensando</span>'
            + '<span class="educasync-assistente-ponto"></span>'
            + '<span class="educasync-assistente-ponto"></span>'
            + '<span class="educasync-assistente-ponto"></span>';
        pensandoEl.appendChild(balao);
        lista.appendChild(pensandoEl);
        lista.scrollTop = lista.scrollHeight;
    }

    function ocultarPensando() {
        if (pensandoEl && pensandoEl.parentNode) {
            pensandoEl.parentNode.removeChild(pensandoEl);
        }
        pensandoEl = null;
        if (statusEl) { statusEl.textContent = STATUS_PADRAO; }
        botao.disabled = false;
        botao.textContent = 'Enviar';
        botao.style.opacity = '1';
        campo.disabled = false;
    }

    function criarBolha(role, texto) {
        var el = document.createElement('div');
        var usuario = role === 'user';
        el.style.display = 'flex';
        el.style.alignItems = 'flex-end';
        el.style.gap = '8px';
        el.style.justifyContent = usuario ? 'flex-end' : 'flex-start';

        if (!usuario && avatar) {
            var img = document.createElement('img');
            img.src = avatar;
            img.alt = '';
            img.width = 28;
            img.height = 28;
            img.style.cssText = 'width:28px;height:28px;border-radius:9999px;object-fit:cover;background:#fff;flex-shrink:0;';
            el.appendChild(img);
        }

        var balao = document.createElement('div');
        balao.style.cssText = usuario
            ? 'max-width:85%;border-radius:16px 16px 4px 16px;padding:8px 12px;font-size:13px;line-height:1.45;white-space:pre-wrap;background:#5b21b6;color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.08);'
            : 'max-width:85%;border-radius:16px 16px 16px 4px;padding:8px 12px;font-size:13px;line-height:1.45;white-space:pre-wrap;background:#fff;color:#111827;border:1px solid #e5e7eb;box-shadow:0 1px 2px rgba(0,0,0,.06);';
        balao.textContent = texto;
        el.appendChild(balao);
        lista.appendChild(el);
        return balao;
    }

    function textoComAula(texto) {
        return String(texto || '')
            .replace(/\btempos\b/gi, 'aulas')
            .replace(/\btempo\b/gi, 'aula');
    }

    function partesDescricao(descricao) {
        var texto = textoComAula(descricao);
        var sep = texto.indexOf(' — ');
        if (sep === -1) {
            return { titulo: texto, detalhe: '' };
        }
        return {
            titulo: texto.slice(0, sep).trim(),
            detalhe: texto.slice(sep + 3).trim()
        };
    }

    function renderProposta(balao, entrada) {
        var proposta = entrada.proposta;
        if (!proposta) { return; }
        var caixa = document.createElement('div');
        caixa.style.cssText = 'margin-top:10px;border:1px solid #e9d5ff;background:#faf5ff;border-radius:12px;padding:12px;color:#111827;';

        var cabecalho = document.createElement('div');
        cabecalho.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;';
        var titulo = document.createElement('p');
        titulo.style.cssText = 'margin:0;font-weight:700;font-size:13px;color:#5b21b6;';
        titulo.textContent = 'Proposta (ainda não aplicada)';
        cabecalho.appendChild(titulo);
        var badge = document.createElement('span');
        badge.style.cssText = 'flex-shrink:0;font-size:11px;font-weight:700;color:#6b21a8;background:#ede9fe;border-radius:9999px;padding:3px 8px;';
        badge.textContent = (proposta.itens || []).length + ' item(ns)';
        cabecalho.appendChild(badge);
        caixa.appendChild(cabecalho);

        if (proposta.resumo) {
            var resumo = document.createElement('p');
            resumo.style.cssText = 'margin:0 0 10px;font-size:12px;line-height:1.45;color:#4b5563;';
            resumo.textContent = textoComAula(proposta.resumo);
            caixa.appendChild(resumo);
        }

        var listaItens = document.createElement('div');
        listaItens.style.cssText = 'display:flex;flex-direction:column;gap:6px;max-height:220px;overflow-y:auto;padding-right:2px;';
        (proposta.itens || []).forEach(function (item) {
            var partes = partesDescricao(item.descricao || '');
            var linha = document.createElement('div');
            linha.style.cssText = 'background:#fff;border:1px solid #e9d5ff;border-radius:10px;padding:8px 10px;';

            var nome = document.createElement('div');
            nome.style.cssText = 'font-size:12px;font-weight:700;color:#111827;line-height:1.3;';
            nome.textContent = partes.titulo || 'Alteração';
            linha.appendChild(nome);

            if (partes.detalhe) {
                var detalhe = document.createElement('div');
                detalhe.style.cssText = 'margin-top:3px;font-size:12px;line-height:1.4;color:#5b21b6;';
                detalhe.textContent = partes.detalhe;
                linha.appendChild(detalhe);
            }

            if (!item.aplicavel) {
                var avisoItem = document.createElement('div');
                avisoItem.style.cssText = 'margin-top:4px;font-size:11px;color:#b45309;';
                avisoItem.textContent = 'Não será alterado ao confirmar';
                linha.appendChild(avisoItem);
            }

            listaItens.appendChild(linha);
        });
        caixa.appendChild(listaItens);

        (proposta.avisos || []).forEach(function (aviso) {
            var p = document.createElement('p');
            p.style.cssText = 'margin:8px 0 0;font-size:12px;color:#b45309;';
            p.textContent = textoComAula(aviso);
            caixa.appendChild(p);
        });

        var status = document.createElement('p');
        status.style.cssText = 'margin:8px 0 0;font-size:12px;';
        caixa.appendChild(status);

        if (entrada.estado === 'confirmada') {
            status.style.color = '#15803d';
            status.textContent = 'Proposta confirmada e aplicada.';
        } else {
            var confirmar = document.createElement('button');
            confirmar.type = 'button';
            confirmar.textContent = 'Confirmar proposta';
            confirmar.style.cssText = 'margin-top:10px;width:100%;border:0;border-radius:10px;padding:10px 14px;background:#0d9488;color:#fff;font-size:13px;font-weight:700;cursor:pointer;';
            confirmar.addEventListener('click', function () {
                if (!window.confirm('Aplicar esta proposta ao cenário? A grade não será gerada nem publicada.')) { return; }
                confirmar.disabled = true;
                confirmar.style.opacity = '0.6';
                status.style.color = '#4b5563';
                status.textContent = 'Aplicando…';
                chamar(urlConfirmar, { comando_ia_id: proposta.comando_ia_id, confirmar: 1 })
                    .then(function (r) {
                        if (r.dados && r.dados.success) {
                            entrada.estado = 'confirmada';
                            salvar();
                            confirmar.remove();
                            status.style.color = '#15803d';
                            status.style.whiteSpace = 'pre-wrap';
                            var texto = r.dados.mensagem || 'Proposta aplicada.';
                            (r.dados.nao_aplicadas || []).forEach(function (n) {
                                texto += '\nNão aplicado: ' + n.descricao + ' (' + n.motivo + ')';
                            });
                            status.textContent = textoComAula(texto);
                            setTimeout(function () { window.location.reload(); }, 2500);
                        } else {
                            confirmar.disabled = false;
                            confirmar.style.opacity = '1';
                            status.style.color = '#b91c1c';
                            status.textContent = (r.dados && r.dados.error) || 'Não foi possível aplicar a proposta.';
                        }
                    })
                    .catch(function () {
                        confirmar.disabled = false;
                        confirmar.style.opacity = '1';
                        status.style.color = '#b91c1c';
                        status.textContent = 'Falha de conexão. Tente novamente.';
                    });
            });
            caixa.appendChild(confirmar);
        }
        balao.appendChild(caixa);
    }

    function renderEntrada(entrada) {
        if (vazio) { vazio.remove(); vazio = null; }
        var balao = criarBolha(entrada.role, entrada.content);
        if (entrada.role === 'assistant') { renderProposta(balao, entrada); }
        lista.scrollTop = lista.scrollHeight;
    }

    function chamar(url, corpo) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify(corpo)
        }).then(function (resposta) {
            return resposta.json().catch(function () { return null; }).then(function (dados) {
                return { status: resposta.status, dados: dados };
            });
        });
    }

    entradas.forEach(renderEntrada);

    botaoAbrir.addEventListener('click', abrirPainel);
    if (botaoAbrirCard) { botaoAbrirCard.addEventListener('click', abrirPainel); }
    botaoFechar.addEventListener('click', fecharPainel);
    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && !painel.hidden) { fecharPainel(); }
    });

    form.addEventListener('submit', function (evento) {
        evento.preventDefault();
        if (enviando) { return; }
        var texto = campo.value.trim();
        if (texto === '') { return; }

        var historico = historicoParaEnvio();
        var entradaUsuario = { role: 'user', content: texto };
        entradas.push(entradaUsuario);
        renderEntrada(entradaUsuario);
        salvar();
        campo.value = '';
        enviando = true;
        mostrarPensando();

        chamar(urlMensagem, { mensagem: texto, historico: historico })
            .then(function (r) {
                var dados = r.dados || {};
                var entrada = { role: 'assistant', content: '' };
                if (dados.success) {
                    entrada.content = dados.mensagem || '';
                    if (dados.proposta) { entrada.proposta = dados.proposta; }
                } else {
                    entrada.content = dados.error || 'Não foi possível obter a resposta.';
                    entrada.erro = true;
                }
                entradas.push(entrada);
                ocultarPensando();
                renderEntrada(entrada);
                salvar();
            })
            .catch(function () {
                var entrada = { role: 'assistant', content: 'Falha de conexão. Tente novamente.', erro: true };
                entradas.push(entrada);
                ocultarPensando();
                renderEntrada(entrada);
                salvar();
            })
            .then(function () {
                enviando = false;
                ocultarPensando();
                campo.focus();
            });
    });

    campo.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter' && !evento.shiftKey) {
            evento.preventDefault();
            if (enviando) { return; }
            form.requestSubmit();
        }
    });

    botaoLimpar.addEventListener('click', function () {
        if (enviando) { return; }
        if (!window.confirm('Limpar a conversa deste cenário?')) { return; }
        entradas = [];
        salvar();
        lista.innerHTML = '';
        var welcome = document.createElement('div');
        welcome.id = 'educasync-assistente-vazio';
        welcome.style.cssText = 'font-size:13px;color:#6b7280;padding:4px;';
        welcome.textContent = 'Conversa limpa. Pode perguntar de novo.';
        lista.appendChild(welcome);
        vazio = welcome;
    });
})();
</script>
<?php endif; ?>
