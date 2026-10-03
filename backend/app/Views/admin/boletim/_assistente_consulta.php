<?php
/**
 * Janela inferior da tela Evento de Notas.
 * Variáveis: $csrfToken, $boletimAssistenteDisponivel
 */
$boletimAssistenteDisponivel = !empty($boletimAssistenteDisponivel);
?>
<style>
#bw-consulta-panel:not(.hidden) {
    display: flex;
}
#bw-consulta-panel {
    position: relative;
    flex-direction: column;
    width: min(24rem, calc(100vw - 2rem));
    height: min(32rem, calc(100vh - 7rem));
    max-width: calc(100vw - 2rem);
    max-height: calc(100vh - 7rem);
    min-width: 18rem;
    min-height: 16rem;
    overflow: hidden;
}
#bw-consulta-panel .bw-consulta-cabeca,
#bw-consulta-panel .bw-consulta-atalhos,
#bw-consulta-form {
    flex: 0 0 auto;
}
#bw-consulta-panel .bw-consulta-atalhos {
    max-height: 6rem;
    overflow-y: auto;
}
#bw-consulta-msgs {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
}
#bw-consulta-msgs img {
    display: block;
    max-width: 100%;
    max-height: 9rem;
    object-fit: contain;
}
#bw-consulta-msgs .whitespace-pre-wrap {
    overflow-wrap: anywhere;
}
#bw-consulta-resize {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 2;
    width: 1.25rem;
    height: 1.25rem;
    padding: 0;
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: nwse-resize;
}
#bw-consulta-resize:hover { color: #334155; }
#bw-consulta-panel.bw-redimensionando { user-select: none; }
</style>
<div id="bw-consulta-root" class="fixed bottom-4 right-4 z-50 flex flex-col items-end pointer-events-none"
     data-url="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/consulta', ENT_QUOTES, 'UTF-8') ?>"
     data-csrf="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>"
     data-disponivel="<?= $boletimAssistenteDisponivel ? '1' : '0' ?>">
    <div class="pointer-events-auto flex flex-col items-end gap-3">
        <div id="bw-consulta-panel" class="hidden bg-white border border-slate-200 shadow-2xl rounded-2xl">
            <button type="button" id="bw-consulta-resize" aria-label="Redimensionar" title="Arraste para redimensionar">
                <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.5" d="M5 3H3v2M3 9v4h4M9 13h4"/></svg>
            </button>
            <div class="bw-consulta-cabeca px-4 py-3 pl-6 border-b border-slate-100 bg-slate-50 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Assistente do evento de notas</h3>
                    <p class="text-xs text-slate-500">Pergunte, ou cole um print para conferir o que está errado ou não marcado.</p>
                </div>
                <div class="flex items-center gap-0.5 shrink-0">
                    <button type="button" id="bw-consulta-minimizar" class="text-slate-400 hover:text-slate-700 p-1" aria-label="Minimizar" title="Minimizar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                    </button>
                    <button type="button" id="bw-consulta-fechar" class="text-slate-400 hover:text-slate-700 p-1" aria-label="Fechar" title="Fechar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <div id="bw-consulta-msgs" class="flex-1 overflow-y-auto p-3 space-y-2 text-sm bg-white">
                <div class="bg-indigo-50 border border-indigo-100 text-slate-700 rounded-lg px-3 py-2">
                    Pergunte como montar a média, peça um dado real, ou cole um print (Ctrl+V). O print é comparado com o que está marcado neste evento.
                </div>
            </div>
            <div class="bw-consulta-atalhos px-3 pt-2 pb-2 flex flex-wrap gap-1.5 border-t border-slate-100 bg-white max-h-24 overflow-y-auto">
                <button type="button" class="bw-consulta-atalho px-2.5 py-1 text-xs rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50" data-pergunta="Como ficar com a média e só trocar se o ENAC for maior?">ENAC maior que a média</button>
                <button type="button" class="bw-consulta-atalho px-2.5 py-1 text-xs rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50" data-pergunta="Como calcular a média das peças?">Como calcular a média</button>
                <button type="button" class="bw-consulta-atalho px-2.5 py-1 text-xs rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50" data-pergunta="Quantas jornadas a Alice Cardoso Mariano fez no 1º bimestre?">Jornadas de um aluno</button>
                <button type="button" class="bw-consulta-atalho px-2.5 py-1 text-xs rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50" data-pergunta="Qual a nota da avaliação bimestral de Matemática da Alice Cardoso Mariano?">Nota bimestral</button>
                <button type="button" class="bw-consulta-atalho px-2.5 py-1 text-xs rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50" data-pergunta="Por que a jornada está vazia?">Por que está vazio</button>
                <button type="button" class="bw-consulta-atalho px-2.5 py-1 text-xs rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50" data-pergunta="O que falta antes de salvar?">O que falta</button>
            </div>
            <form id="bw-consulta-form" class="p-3 flex flex-col gap-2 bg-slate-50">
                <div id="bw-consulta-print" class="hidden items-center gap-2 rounded-lg border border-indigo-100 bg-white px-2 py-1.5">
                    <img id="bw-consulta-print-img" alt="Print colado" class="h-10 w-16 object-cover rounded">
                    <span class="text-xs text-slate-600 flex-1">Print pronto para conferir</span>
                    <button type="button" id="bw-consulta-print-limpar" class="text-xs text-slate-500 hover:text-slate-800">Tirar</button>
                </div>
                <div class="flex gap-2">
                    <textarea id="bw-consulta-input" rows="2" class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" placeholder="Pergunte ou cole um print com Ctrl+V"></textarea>
                    <button type="submit" id="bw-consulta-enviar" class="self-end px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">Enviar</button>
                </div>
            </form>
        </div>
        <button type="button" id="bw-consulta-barra" class="hidden inline-flex items-center gap-2 max-w-[min(100vw-2rem,20rem)] pl-3 pr-2 py-2 rounded-full shadow-lg bg-indigo-600 text-white hover:bg-indigo-700" aria-label="Restaurar assistente" title="Clique para abrir de novo">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <span class="text-sm font-medium truncate">Assistente</span>
            <span id="bw-consulta-barra-badge" class="hidden min-w-[1.25rem] h-5 px-1.5 rounded-full bg-white text-indigo-700 text-xs font-semibold leading-5 text-center">0</span>
            <span class="shrink-0 text-indigo-100 pl-1" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
            </span>
        </button>
        <button type="button" id="bw-consulta-toggle" class="inline-flex items-center justify-center w-14 h-14 rounded-full shadow-lg bg-indigo-600 text-white hover:bg-indigo-700" aria-label="Abrir assistente" title="Perguntar sobre boletim e notas">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        </button>
    </div>
</div>
<script>
(function () {
    var root = document.getElementById('bw-consulta-root');
    if (!root) return;
    var panel = document.getElementById('bw-consulta-panel');
    var toggle = document.getElementById('bw-consulta-toggle');
    var barra = document.getElementById('bw-consulta-barra');
    var barraBadge = document.getElementById('bw-consulta-barra-badge');
    var minimizar = document.getElementById('bw-consulta-minimizar');
    var fechar = document.getElementById('bw-consulta-fechar');
    var form = document.getElementById('bw-consulta-form');
    var input = document.getElementById('bw-consulta-input');
    var msgs = document.getElementById('bw-consulta-msgs');
    var btn = document.getElementById('bw-consulta-enviar');
    var printBox = document.getElementById('bw-consulta-print');
    var printImg = document.getElementById('bw-consulta-print-img');
    var printLimpar = document.getElementById('bw-consulta-print-limpar');
    var historico = [];
    var enviando = false;
    var imagemPendente = null;

    function atualizarBadge() {
        if (!barraBadge) return;
        var n = historico.length;
        if (n > 0) {
            barraBadge.textContent = String(Math.min(n, 99));
            barraBadge.classList.remove('hidden');
        } else {
            barraBadge.classList.add('hidden');
        }
    }
    function abrir(foco) {
        panel.classList.remove('hidden');
        toggle.classList.add('hidden');
        if (barra) barra.classList.add('hidden');
        if (foco && input) input.focus();
    }
    function minimizarPainel() {
        panel.classList.add('hidden');
        toggle.classList.add('hidden');
        if (barra) {
            atualizarBadge();
            barra.classList.remove('hidden');
        } else {
            toggle.classList.remove('hidden');
        }
    }
    function fecharPainel() {
        panel.classList.add('hidden');
        if (barra) barra.classList.add('hidden');
        toggle.classList.remove('hidden');
    }
    function bolha(role, texto, imagem) {
        var el = document.createElement('div');
        el.className = role === 'user'
            ? 'ml-10 bg-indigo-600 text-white rounded-lg px-3 py-2 whitespace-pre-wrap'
            : 'mr-6 bg-slate-100 text-slate-800 rounded-lg px-3 py-2 whitespace-pre-wrap';
        if (imagem) {
            var img = document.createElement('img');
            img.src = imagem;
            img.alt = 'Print enviado';
            img.className = 'mb-2 max-h-28 rounded border border-white/30';
            el.appendChild(img);
        }
        el.appendChild(document.createTextNode(texto || ''));
        if (role !== 'user') anexarBotaoFormula(el, texto || '');
        msgs.appendChild(el);
        msgs.scrollTop = msgs.scrollHeight;
    }
    function anexarBotaoFormula(el, texto) {
        if (typeof window.boletimWizardLerFormulaTexto !== 'function') return;
        var lido = null;
        try { lido = window.boletimWizardLerFormulaTexto(texto); } catch (e) { return; }
        if (!lido || !lido.formulas || !lido.formulas.length) return;
        var nomes = [];
        lido.formulas.forEach(function (f) { if (f && f.nome && nomes.indexOf(f.nome) < 0) nomes.push(f.nome); });
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mt-2 inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-60';
        btn.textContent = nomes.length > 1 ? 'Utilizar fórmulas' : 'Utilizar fórmula';
        var aviso = document.createElement('p');
        aviso.className = 'mt-1 text-xs text-slate-500';
        if (nomes.length) aviso.textContent = 'Entra em ' + nomes.join(', ') + '.';
        btn.addEventListener('click', function () {
            if (typeof window.boletimWizardAplicarFormulaTexto !== 'function') return;
            var r = null;
            try { r = window.boletimWizardAplicarFormulaTexto(texto); } catch (e) { r = null; }
            if (r && r.ok) {
                btn.disabled = true;
                btn.textContent = 'Fórmula no cálculo';
                aviso.className = 'mt-1 text-xs font-medium text-emerald-700';
                aviso.textContent = 'Aplicada em ' + ((r.nomes && r.nomes.length) ? r.nomes.join(', ') : (nomes.join(', ') || 'a coluna aberta')) + '.';
            } else {
                aviso.className = 'mt-1 text-xs text-red-700';
                aviso.textContent = (r && r.erro) || 'Não deu para montar essa fórmula.';
            }
        });
        el.appendChild(btn);
        el.appendChild(aviso);
    }
    function mostrarPrint(dataUrl) {
        imagemPendente = dataUrl;
        if (printImg) printImg.src = dataUrl;
        if (printBox) {
            printBox.classList.remove('hidden');
            printBox.classList.add('flex');
        }
    }
    function limparPrint() {
        imagemPendente = null;
        if (printImg) printImg.removeAttribute('src');
        if (printBox) {
            printBox.classList.add('hidden');
            printBox.classList.remove('flex');
        }
    }
    function lerArquivoImagem(file) {
        if (!file || String(file.type || '').indexOf('image/') !== 0) return;
        var reader = new FileReader();
        reader.onload = function () {
            var img = new Image();
            img.onload = function () {
                var max = 1400;
                var escala = Math.min(1, max / Math.max(img.width, img.height));
                var canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(img.width * escala));
                canvas.height = Math.max(1, Math.round(img.height * escala));
                var ctx = canvas.getContext('2d');
                if (!ctx) return;
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                mostrarPrint(canvas.toDataURL('image/jpeg', 0.72));
            };
            img.src = String(reader.result || '');
        };
        reader.readAsDataURL(file);
    }
    function estadoAtual() {
        try {
            if (typeof window.boletimWizardEstadoAtual === 'function') {
                return window.boletimWizardEstadoAtual() || null;
            }
        } catch (e) {}
        return null;
    }
    function enviar(texto) {
        texto = String(texto || '').trim();
        var imagem = imagemPendente;
        if ((!texto && !imagem) || enviando) return;
        enviando = true;
        if (btn) btn.disabled = true;
        var textoBolha = texto || 'Conferir este print com a configuração do evento.';
        bolha('user', textoBolha, imagem);
        if (input) input.value = '';
        limparPrint();
        var espera = document.createElement('div');
        espera.className = 'mr-6 text-xs text-slate-500 px-3 py-2';
        espera.textContent = 'Consultando…';
        msgs.appendChild(espera);
        msgs.scrollTop = msgs.scrollHeight;
        var fd = new FormData();
        fd.append('_token', root.getAttribute('data-csrf') || '');
        fd.append('mensagem', texto);
        if (imagem) fd.append('imagem', imagem);
        fd.append('historico', JSON.stringify(historico.slice(-8)));
        var est = estadoAtual();
        if (est) fd.append('wizard_estado', JSON.stringify(est));
        fetch(root.getAttribute('data-url'), {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (espera.parentNode) espera.parentNode.removeChild(espera);
            var resp = (j && j.success && j.mensagem) ? j.mensagem : ((j && (j.error || j.mensagem)) || 'Não deu para responder agora.');
            bolha('assistant', resp);
            historico.push({ role: 'user', content: textoBolha });
            historico.push({ role: 'assistant', content: resp });
            atualizarBadge();
        }).catch(function () {
            if (espera.parentNode) espera.parentNode.removeChild(espera);
            bolha('assistant', 'Não deu para falar com o servidor.');
        }).then(function () {
            enviando = false;
            if (btn) btn.disabled = false;
        });
    }

    toggle.addEventListener('click', function () { abrir(true); });
    if (barra) barra.addEventListener('click', function () { abrir(true); });
    if (minimizar) minimizar.addEventListener('click', minimizarPainel);
    fechar.addEventListener('click', fecharPainel);
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        enviar(input ? input.value : '');
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            enviar(input.value);
        }
    });
    panel.addEventListener('paste', function (e) {
        var items = (e.clipboardData && e.clipboardData.items) || [];
        for (var i = 0; i < items.length; i++) {
            if (items[i].type && items[i].type.indexOf('image/') === 0) {
                e.preventDefault();
                lerArquivoImagem(items[i].getAsFile());
                return;
            }
        }
    });
    if (printLimpar) printLimpar.addEventListener('click', limparPrint);
    (function () {
        var alca = document.getElementById('bw-consulta-resize');
        if (!alca || !panel) return;
        var chave = 'bw-consulta-tamanho';
        try {
            var salvo = JSON.parse(localStorage.getItem(chave) || 'null');
            if (salvo && salvo.w && salvo.h) {
                panel.style.width = Math.round(salvo.w) + 'px';
                panel.style.height = Math.round(salvo.h) + 'px';
            }
        } catch (e) {}
        function limitar(px, min, max) {
            return Math.max(min, Math.min(max, px));
        }
        alca.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) return;
            e.preventDefault();
            var rect = panel.getBoundingClientRect();
            var inicioX = e.clientX;
            var inicioY = e.clientY;
            var largura = rect.width;
            var altura = rect.height;
            panel.classList.add('bw-redimensionando');
            function mover(ev) {
                var maxW = Math.max(280, window.innerWidth - 32);
                var maxH = Math.max(260, window.innerHeight - 112);
                panel.style.width = limitar(largura + (inicioX - ev.clientX), 280, maxW) + 'px';
                panel.style.height = limitar(altura + (inicioY - ev.clientY), 260, maxH) + 'px';
            }
            function soltar() {
                panel.classList.remove('bw-redimensionando');
                document.removeEventListener('pointermove', mover);
                document.removeEventListener('pointerup', soltar);
                try {
                    var atual = panel.getBoundingClientRect();
                    localStorage.setItem(chave, JSON.stringify({
                        w: Math.round(atual.width),
                        h: Math.round(atual.height)
                    }));
                } catch (err) {}
            }
            document.addEventListener('pointermove', mover);
            document.addEventListener('pointerup', soltar);
        });
    })();
    root.querySelectorAll('.bw-consulta-atalho').forEach(function (b) {
        b.addEventListener('click', function () {
            abrir(false);
            enviar(b.getAttribute('data-pergunta') || '');
        });
    });
})();
</script>
