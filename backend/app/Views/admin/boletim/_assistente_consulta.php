<?php
/**
 * Janela inferior da tela Evento de Notas.
 * Variáveis: $csrfToken, $boletimAssistenteDisponivel
 */
$boletimAssistenteDisponivel = !empty($boletimAssistenteDisponivel);
?>
<div id="bw-consulta-root" class="fixed bottom-4 right-4 z-50 flex flex-col items-end pointer-events-none"
     data-url="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/consulta', ENT_QUOTES, 'UTF-8') ?>"
     data-csrf="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>"
     data-disponivel="<?= $boletimAssistenteDisponivel ? '1' : '0' ?>">
    <div class="pointer-events-auto flex flex-col items-end gap-3">
        <div id="bw-consulta-panel" class="hidden w-[min(100vw-2rem,24rem)] h-[min(70vh,32rem)] bg-white border border-slate-200 shadow-2xl rounded-2xl flex flex-col overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 bg-slate-50 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Assistente do evento de notas</h3>
                    <p class="text-xs text-slate-500">Como montar o cálculo, e a nota, o lançamento ou a jornada de um aluno.</p>
                </div>
                <button type="button" id="bw-consulta-fechar" class="text-slate-400 hover:text-slate-700 p-1" aria-label="Fechar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="bw-consulta-msgs" class="flex-1 overflow-y-auto p-3 space-y-2 text-sm bg-white">
                <div class="bg-indigo-50 border border-indigo-100 text-slate-700 rounded-lg px-3 py-2">
                    Pergunte como montar a média ou peça um dado real. Nota, jornada e lançamento só entram se o sistema encontrar. Sem chute.
                </div>
            </div>
            <div class="px-3 pt-2 flex flex-wrap gap-1.5 border-t border-slate-100 bg-white">
                <button type="button" class="bw-consulta-atalho px-2.5 py-1 text-xs rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50" data-pergunta="Como ficar com a média e só trocar se o ENAC for maior?">ENAC maior que a média</button>
                <button type="button" class="bw-consulta-atalho px-2.5 py-1 text-xs rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50" data-pergunta="Como calcular a média das peças?">Como calcular a média</button>
                <button type="button" class="bw-consulta-atalho px-2.5 py-1 text-xs rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50" data-pergunta="Quantas jornadas a Alice Cardoso Mariano fez no 1º bimestre?">Jornadas de um aluno</button>
                <button type="button" class="bw-consulta-atalho px-2.5 py-1 text-xs rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50" data-pergunta="Qual a nota da avaliação bimestral de Matemática da Alice Cardoso Mariano?">Nota bimestral</button>
            </div>
            <form id="bw-consulta-form" class="p-3 flex gap-2 bg-slate-50">
                <textarea id="bw-consulta-input" rows="2" class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" placeholder="Ex.: quantas jornadas o aluno fez, ou a nota bimestral de Matemática"></textarea>
                <button type="submit" id="bw-consulta-enviar" class="self-end px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">Enviar</button>
            </form>
        </div>
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
    var fechar = document.getElementById('bw-consulta-fechar');
    var form = document.getElementById('bw-consulta-form');
    var input = document.getElementById('bw-consulta-input');
    var msgs = document.getElementById('bw-consulta-msgs');
    var btn = document.getElementById('bw-consulta-enviar');
    var historico = [];
    var enviando = false;

    function abrir(foco) {
        panel.classList.remove('hidden');
        toggle.classList.add('hidden');
        if (foco && input) input.focus();
    }
    function fecharPainel() {
        panel.classList.add('hidden');
        toggle.classList.remove('hidden');
    }
    function bolha(role, texto) {
        var el = document.createElement('div');
        el.className = role === 'user'
            ? 'ml-10 bg-indigo-600 text-white rounded-lg px-3 py-2 whitespace-pre-wrap'
            : 'mr-6 bg-slate-100 text-slate-800 rounded-lg px-3 py-2 whitespace-pre-wrap';
        el.textContent = texto || '';
        msgs.appendChild(el);
        msgs.scrollTop = msgs.scrollHeight;
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
        if (!texto || enviando) return;
        enviando = true;
        if (btn) btn.disabled = true;
        bolha('user', texto);
        if (input) input.value = '';
        var espera = document.createElement('div');
        espera.className = 'mr-6 text-xs text-slate-500 px-3 py-2';
        espera.textContent = 'Consultando…';
        msgs.appendChild(espera);
        msgs.scrollTop = msgs.scrollHeight;
        var fd = new FormData();
        fd.append('_token', root.getAttribute('data-csrf') || '');
        fd.append('mensagem', texto);
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
            historico.push({ role: 'user', content: texto });
            historico.push({ role: 'assistant', content: resp });
        }).catch(function () {
            if (espera.parentNode) espera.parentNode.removeChild(espera);
            bolha('assistant', 'Não deu para falar com o servidor.');
        }).then(function () {
            enviando = false;
            if (btn) btn.disabled = false;
        });
    }

    toggle.addEventListener('click', function () { abrir(true); });
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
    root.querySelectorAll('.bw-consulta-atalho').forEach(function (b) {
        b.addEventListener('click', function () {
            abrir(false);
            enviar(b.getAttribute('data-pergunta') || '');
        });
    });
})();
</script>
