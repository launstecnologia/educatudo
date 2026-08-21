<?php
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../../Core/LayoutHelper.php';
}
?>
<?php if (!empty($acessibilidade_style)): ?>
<?= $acessibilidade_style ?>
<?php endif; ?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Lexend:wght@400;500;600;700&display=swap');
    @font-face {
        font-family: 'OpenDyslexic';
        src: url('https://cdn.jsdelivr.net/npm/@fontsource/opendyslexic@5.0.0/files/opendyslexic-latin-400-normal.woff2') format('woff2');
        font-weight: 400;
        font-display: swap;
    }
    @font-face {
        font-family: 'OpenDyslexic';
        src: url('https://cdn.jsdelivr.net/npm/@fontsource/opendyslexic@5.0.0/files/opendyslexic-latin-700-normal.woff2') format('woff2');
        font-weight: 700;
        font-display: swap;
    }
    body.ei-accessible.ei-family-lexend,
    body.ei-accessible.ei-family-lexend p,
    body.ei-accessible.ei-family-lexend span,
    body.ei-accessible.ei-family-lexend label,
    body.ei-accessible.ei-family-lexend li,
    body.ei-accessible.ei-family-lexend div,
    body.ei-accessible.ei-family-lexend button {
        font-family: 'Lexend', 'Atkinson Hyperlegible', Verdana, Arial, sans-serif !important;
    }
    body.ei-accessible.ei-family-opendyslexic,
    body.ei-accessible.ei-family-opendyslexic p,
    body.ei-accessible.ei-family-opendyslexic span,
    body.ei-accessible.ei-family-opendyslexic label,
    body.ei-accessible.ei-family-opendyslexic li,
    body.ei-accessible.ei-family-opendyslexic div,
    body.ei-accessible.ei-family-opendyslexic button {
        font-family: 'OpenDyslexic', 'Comic Sans MS', Verdana, sans-serif !important;
    }
    body.ei-accessible.ei-font-small { font-size: .95rem; }
    body.ei-accessible.ei-font-medium { font-size: 1.08rem; }
    body.ei-accessible.ei-font-large { font-size: 1.22rem; }
    body.ei-accessible.ei-font-xlarge { font-size: 1.42rem; }
    body.ei-accessible.ei-font-medium .questao-container,
    body.ei-accessible.ei-font-medium .questao-container .text-sm { font-size: 1rem !important; line-height: 1.75 !important; }
    body.ei-accessible.ei-font-large .questao-container,
    body.ei-accessible.ei-font-large .questao-container .text-sm { font-size: 1.12rem !important; line-height: 1.85 !important; }
    body.ei-accessible.ei-font-xlarge .questao-container,
    body.ei-accessible.ei-font-xlarge .questao-container .text-sm { font-size: 1.28rem !important; line-height: 2 !important; }
    body.ei-accessible.ei-text-spacing-medium .questao-container { line-height: 1.85 !important; letter-spacing: .01em !important; }
    body.ei-accessible.ei-text-spacing-large .questao-container { line-height: 2.1 !important; letter-spacing: .025em !important; }
    body.ei-accessible.ei-element-spacing-medium .questao-container { padding: 2rem !important; }
    body.ei-accessible.ei-element-spacing-large .questao-container { padding: 2.5rem !important; }
</style>
<script>
(function() {
    try {
        var defaults = {
            fontSize: 'normal',
            contrast: 'default',
            fontFamily: 'default',
            textSpacing: 'normal',
            elementSpacing: 'normal',
            buttonSize: 'normal',
            highlightButtons: false,
            highlightFocus: false,
            focusMode: false,
            reduceMotion: false,
            highlightCursor: false
        };
        var prefs = Object.assign({}, defaults, JSON.parse(localStorage.getItem('educatudo_student_accessibility') || '{}'));
        var body = document.body;
        if (!body) return;
        function setClass(prefix, value) {
            Array.from(body.classList).forEach(function(cls) {
                if (cls.indexOf(prefix) === 0) body.classList.remove(cls);
            });
            if (value) body.classList.add(prefix + value);
        }
        var hasAny = JSON.stringify(Object.assign({}, defaults, prefs)) !== JSON.stringify(defaults);
        body.classList.toggle('ei-accessible', hasAny);
        setClass('ei-font-', prefs.fontSize !== 'normal' ? prefs.fontSize : '');
        setClass('ei-contrast-', prefs.contrast !== 'default' ? prefs.contrast : '');
        setClass('ei-family-', prefs.fontFamily !== 'default' ? prefs.fontFamily : '');
        setClass('ei-text-spacing-', prefs.textSpacing !== 'normal' ? prefs.textSpacing : '');
        setClass('ei-element-spacing-', prefs.elementSpacing !== 'normal' ? prefs.elementSpacing : '');
        setClass('ei-button-', prefs.buttonSize !== 'normal' ? prefs.buttonSize : '');
        body.classList.toggle('ei-highlight-buttons', !!prefs.highlightButtons);
        body.classList.toggle('ei-highlight-focus', !!prefs.highlightFocus);
        body.classList.toggle('ei-focus-mode', !!prefs.focusMode);
        body.classList.toggle('ei-reduce-motion', !!prefs.reduceMotion);
        body.classList.toggle('ei-highlight-cursor', !!prefs.highlightCursor);
    } catch (e) {}
})();
</script>
<?php if (!empty($acessibilidade_ativa)): ?>
<div class="max-w-4xl mx-auto px-6 pt-4">
    <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-2">
        Recursos de acessibilidade ativados para esta prova.
    </div>
</div>
<?php endif; ?>
<?php if (!empty($modo_seguro) && empty($modo_embed)): ?>
<div id="overlay-tela-cheia-realizar" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-gray-900 text-white p-8 text-center" style="display: none;">
    <p class="text-xl font-semibold mb-2">Você está na prova</p>
    <p class="text-gray-300 mb-4 max-w-md">Clique no botão abaixo para entrar em tela cheia e ver as questões. (O navegador exige este clique para ativar tela cheia.)</p>
    <p class="text-amber-300 text-sm max-w-lg mb-6 font-medium">Durante a prova, a tecla Esc e atalhos de teclado estão desativados. Você só poderá sair da tela cheia ao <strong>finalizar a prova</strong> e clicar em <strong>&quot;Finalizar Prova&quot;</strong>.</p>
    <button type="button" id="btn-entrar-tela-cheia-realizar" class="px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-lg">Entrar em tela cheia</button>
</div>
<?php endif; ?>

<!-- Modal de aviso (evita alert/confirm e sair do fullscreen) -->
<div id="modal-aviso-realizar" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-black/60 p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 text-center">
        <p id="modal-aviso-realizar-titulo" class="text-lg font-semibold text-gray-900 mb-2"></p>
        <p id="modal-aviso-realizar-msg" class="text-gray-600 mb-6"></p>
        <div id="modal-aviso-realizar-botoes" class="flex gap-3 justify-center">
            <button type="button" id="modal-aviso-realizar-ok" class="flex-1 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700">OK</button>
        </div>
    </div>
</div>
<!-- Modal de confirmação (substitui confirm()) -->
<div id="modal-confirm-realizar" class="fixed inset-0 z-[10001] hidden items-center justify-center bg-black/60 p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 text-center">
        <p id="modal-confirm-realizar-titulo" class="text-lg font-semibold text-gray-900 mb-2"></p>
        <p id="modal-confirm-realizar-msg" class="text-gray-600 mb-6"></p>
        <div class="flex gap-3">
            <button type="button" id="modal-confirm-realizar-cancel" class="flex-1 py-3 bg-gray-500 text-white font-semibold rounded-lg hover:bg-gray-600">Cancelar</button>
            <button type="button" id="modal-confirm-realizar-ok" class="flex-1 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700">Confirmar</button>
        </div>
    </div>
</div>

<!-- Toast único "Resposta salva" -->
<div id="toast-resposta-salva" class="fixed top-4 left-1/2 -translate-x-1/2 z-[10002] px-6 py-3 bg-green-600 text-white font-medium rounded-lg shadow-lg hidden transition-opacity duration-300" style="display: none;" role="status" aria-live="polite">Resposta salva</div>

<!-- Modal de revisão antes de finalizar -->
<div id="modal-revisao-realizar" class="fixed inset-0 z-[10003] hidden items-center justify-center bg-black/60 p-4" style="display: none;">
    <div id="modal-revisao-box" class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Confira suas respostas antes de enviar</h3>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
            <div id="revisao-marcacao-final" class="bg-gray-50 rounded-lg p-4 space-y-3 text-left">
                <!-- Preenchido via JS com lista de questões e respostas -->
            </div>
        </div>
        <div class="p-4 border-t border-gray-200 flex gap-3 justify-end">
            <button type="button" id="btn-revisao-voltar" class="px-6 py-3 bg-gray-500 text-white font-semibold rounded-lg hover:bg-gray-600">Voltar para corrigir</button>
            <button type="button" id="btn-revisao-confirmar" class="px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700">Confirmar e enviar</button>
        </div>
    </div>
</div>

<div class="max-w-4xl mx-auto px-6 py-8<?= !empty($acessibilidade_wrapper_class) ? ' ' . htmlspecialchars($acessibilidade_wrapper_class) : '' ?>">
<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                <?php if (!empty($modo_bloco) && is_array($bloco ?? null) && !empty($bloco['titulo'])): ?>
                    <?= htmlspecialchars($bloco['titulo']) ?>
                <?php else: ?>
                    <?= htmlspecialchars($prova['titulo'] ?? '') ?>
                <?php endif; ?>
            </h2>
            <p class="text-gray-600">Matéria: <?= htmlspecialchars($prova['materia_nome'] ?? '—') ?></p>
            <p class="text-sm text-gray-500 mt-0.5">Professor: <?= !empty($prova['professor_nome']) ? htmlspecialchars($prova['professor_nome']) : '—' ?></p>
        </div>
        <div class="text-right"<?php if (!empty($acessibilidade_hide_timer)): ?> style="display:none" aria-hidden="true"<?php endif; ?>>
            <?php if (!empty($bloco_termino_iso)): ?>
                <div class="text-2xl font-bold text-blue-600 font-mono" id="timer">--:--</div>
                <p class="text-sm text-gray-600">Término do bloco às <?= date('H:i', strtotime($bloco_termino_iso)) ?></p>
            <?php elseif ($tempo_limite): ?>
                <div class="text-2xl font-bold text-blue-600 font-mono" id="timer">
                    <?= $tempo_restante ?>:00
                </div>
                <p class="text-sm text-gray-600">Tempo restante</p>
                <?php if (!empty($acessibilidade_pause)): ?>
                <button type="button" id="ei-pause-btn" class="mt-1 px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-medium">⏸ Pausar</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($acessibilidade_progress)): ?>
    <div class="mt-4" id="ei-progress-wrap">
        <div class="flex justify-between text-xs text-gray-500 mb-1">
            <span>Progresso</span>
            <span id="ei-progress-label">0%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2.5">
            <div id="ei-progress-bar" class="bg-emerald-500 h-2.5 rounded-full transition-all duration-300" style="width:0%"></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Instruções -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
    <p class="text-sm text-blue-800">
        <strong>Instruções:</strong> Responda todas as questões. Suas respostas são salvas automaticamente. 
        Ao finalizar, clique no botão "<?= (!empty($modo_bloco) || !empty($modo_seguro)) ? 'Finalizar Matéria' : 'Finalizar Prova' ?>".
    </p>
</div>

<?php if (empty($questoes) || !is_array($questoes)): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
        <svg class="w-16 h-16 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <h3 class="text-lg font-semibold text-red-900 mb-2">Prova sem questões</h3>
        <p class="text-red-700">Esta prova não possui questões cadastradas. Entre em contato com seu professor.</p>
        <a href="<?= URL ?>/aluno/provas" class="mt-4 inline-block bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
            Voltar
        </a>
    </div>
<?php else: ?>
<!-- Seletor de Questões (Numeração) -->
<div class="bg-white rounded-xl shadow-lg p-4 mb-6 border-2 border-gray-200">
    <p class="text-sm font-semibold text-gray-700 mb-3 text-center">Selecione a questão:</p>
    <div class="flex flex-wrap gap-2 justify-center" id="questoesSelector">
        <?php foreach ($questoes as $index => $questao): ?>
            <button type="button"
                    onclick="irParaQuestao(<?= $index ?>)"
                    class="questao-btn w-12 h-12 rounded-lg font-semibold transition-all border-2 <?= $index === 0 ? 'bg-blue-600 text-white border-blue-700' : (isset($respostas[$questao['id']]) ? 'bg-green-100 text-green-800 border-green-300' : 'bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200') ?>"
                    data-questao="<?= $index ?>"
                    id="btn-questao-<?= $index ?>">
                <?= $index + 1 ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- Formulário da Prova -->
<form id="provaForm" class="space-y-6">
    <?php foreach ($questoes as $index => $questao): ?>
        <div class="questao-container bg-white rounded-xl shadow-lg p-6 border-2 border-gray-200" 
             data-questao-id="<?= $questao['id'] ?>"
             data-questao-index="<?= $index ?>"
             data-prova-id-original="<?= $questao['prova_id_original'] ?? $prova['id'] ?>"
             style="<?= $index > 0 ? 'display: none;' : '' ?>">
            
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Questão <?= $index + 1 ?>
                        <span class="text-sm font-normal text-gray-600">(<?php
                            $tipos = ['multipla_escolha' => 'Múltipla Escolha', 'verdadeiro_falso' => 'Verdadeiro/Falso', 'dissertativa' => 'Dissertativa'];
                            echo $tipos[$questao['tipo']] ?? $questao['tipo'];
                        ?>)</span>
                        <span class="text-xs font-normal text-gray-500 ml-1"><?= number_format($questao['valor'], 2, ',', '.') ?> pt(s)</span>
                    </h3>
                    <?php if (isset($questao['materia_nome']) && !empty($questao['materia_nome'])): ?>
                        <div class="mt-2">
                            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                                📚 <?= htmlspecialchars($questao['materia_nome']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-gray-700 mb-4 text-lg"><?= isset($questao['enunciado']) ? LayoutHelper::renderEnunciadoProva($questao['enunciado']) : '' ?></div>
            
            <?php if (!empty($questao['imagem_url'])): ?>
                <div class="mb-4 text-center">
                    <?php $isSvg = (strpos($questao['imagem_url'], '.svg') !== false); ?>
                    <img src="<?= htmlspecialchars($questao['imagem_url']) ?>" 
                         alt="Imagem da questão"
                         class="<?= $isSvg ? 'inline-block max-w-md h-auto' : 'max-w-full md:max-w-lg h-auto rounded-lg border border-gray-200 mx-auto' ?>"
                         loading="lazy"
                         onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='block';">
                    <p class="hidden p-3 bg-gray-50 rounded-lg text-sm text-gray-500 italic mt-2">
                        Imagem indisponível
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ($questao['tipo'] === 'multipla_escolha' && !empty($questao['alternativas'])): ?>
                <div class="space-y-3">
                    <?php foreach ($questao['alternativas'] as $alt): ?>
                        <label class="flex items-center p-4 border-2 border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer transition-all">
                            <input type="radio" 
                                   name="questao_<?= $questao['id'] ?>" 
                                   value="<?= $alt['id'] ?>"
                                   data-questao-id="<?= $questao['id'] ?>"
                                   data-alternativa-id="<?= $alt['id'] ?>"
                                   class="mr-3 w-5 h-5 flex-shrink-0"
                                   <?= isset($respostas[$questao['id']]) && $respostas[$questao['id']]['alternativa_id'] == $alt['id'] ? 'checked' : '' ?>>
                            <span class="text-gray-700 text-base"><?= isset($alt['texto']) ? LayoutHelper::renderEnunciadoProva($alt['texto']) : '' ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($questao['tipo'] === 'dissertativa'): ?>
                <textarea name="questao_<?= $questao['id'] ?>_texto"
                          data-questao-id="<?= $questao['id'] ?>"
                          rows="8"
                          class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-base"
                          placeholder="Digite sua resposta aqui..."><?= isset($respostas[$questao['id']]) ? htmlspecialchars($respostas[$questao['id']]['resposta_texto']) : '' ?></textarea>
                <?php if (!empty($acessibilidade_audio)): ?>
                <div class="mt-2 flex items-center gap-2">
                    <button type="button" class="ei-audio-btn px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium" data-questao-id="<?= $questao['id'] ?>">🎤 Responder por áudio</button>
                    <span class="ei-audio-status text-xs text-gray-500" data-questao-id="<?= $questao['id'] ?>"></span>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    
    <!-- Navegação e Botões -->
    <div class="bg-white rounded-xl shadow-lg p-6 flex justify-between items-center">
        <div class="flex gap-4">
            <button type="button" 
                    onclick="questaoAnterior()"
                    id="btn-anterior"
                    class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled>
                ← Anterior
            </button>
            <button type="button" 
                    onclick="questaoProxima()"
                    id="btn-proximo"
                    class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-semibold">
                Próximo →
            </button>
        </div>
        <button type="button" 
                id="btn-finalizar-prova"
                onclick="finalizarProva()"
                class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-semibold">
            <?= (!empty($modo_bloco) || !empty($modo_seguro)) ? 'Finalizar Matéria' : 'Finalizar Prova' ?>
        </button>
    </div>
</form>
<?php endif; ?>
</div>

<script>
// Modais (evitam alert/confirm e sair do fullscreen)
function mostrarModal(titulo, msg) {
    var el = document.getElementById('modal-aviso-realizar');
    if (!el) return;
    document.getElementById('modal-aviso-realizar-titulo').textContent = titulo || 'Aviso';
    document.getElementById('modal-aviso-realizar-msg').textContent = msg || '';
    var btns = document.getElementById('modal-aviso-realizar-botoes');
    btns.innerHTML = '<button type="button" id="modal-aviso-realizar-ok" class="flex-1 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700">OK</button>';
    el.style.display = 'flex';
    el.classList.remove('hidden');
    document.getElementById('modal-aviso-realizar-ok').onclick = function() {
        el.style.display = 'none';
        el.classList.add('hidden');
    };
}
function mostrarConfirm(titulo, msg, onConfirm, onCancel) {
    var el = document.getElementById('modal-confirm-realizar');
    if (!el) { if (onCancel) onCancel(); return; }
    document.getElementById('modal-confirm-realizar-titulo').textContent = titulo || 'Confirmar';
    document.getElementById('modal-confirm-realizar-msg').textContent = msg || '';
    el.style.display = 'flex';
    el.classList.remove('hidden');
    document.getElementById('modal-confirm-realizar-ok').onclick = function() {
        el.style.display = 'none';
        el.classList.add('hidden');
        if (onConfirm) onConfirm();
    };
    document.getElementById('modal-confirm-realizar-cancel').onclick = function() {
        el.style.display = 'none';
        el.classList.add('hidden');
        if (onCancel) onCancel();
    };
}

// Variáveis globais
let questaoAtual = 0;
const totalQuestoes = <?= !empty($questoes) && is_array($questoes) ? count($questoes) : 0 ?>;
const questoes = <?= !empty($questoes) && is_array($questoes) ? json_encode(array_map(function($q) { return $q['id']; }, $questoes)) : '[]' ?>;
<?php if (!empty($modo_seguro) && !empty($bloco['id'])): ?>
var blocoIdModoSeguro = <?= (int) $bloco['id'] ?>;
var cancelamentoBlocoEnviado = false;
function cancelarBlocoModoSeguroNoServidor() {
    if (!blocoIdModoSeguro || cancelamentoBlocoEnviado || window._finalizandoProva) {
        return Promise.resolve(false);
    }
    cancelamentoBlocoEnviado = true;
    var url = '<?= URL ?>/aluno/provas/bloco/' + blocoIdModoSeguro + '/cancelar-seguro';
    try {
        var imgPing = new Image();
        imgPing.src = url + '?_=' + Date.now();
    } catch (e) {}
    try {
        if (navigator.sendBeacon) {
            navigator.sendBeacon(url, new Blob(['{}'], { type: 'application/json' }));
        }
    } catch (e) {}
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: '{}'
    }).then(function(r) {
        if (r.ok) return true;
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r2) { return r2.ok; });
    }).catch(function() { return false; });
}
<?php endif; ?>

// Garantir que as funções sejam definidas globalmente
window.questaoProxima = function() {
    if (questaoAtual < totalQuestoes - 1) {
        irParaQuestao(questaoAtual + 1);
    }
};

window.finalizarProva = function() {
    // Verifica se todas as questões foram respondidas
    const containers = document.querySelectorAll('.questao-container');
    const naoRespondidas = [];
    containers.forEach(function(container) {
        const questaoId = container.getAttribute('data-questao-id');
        const index = parseInt(container.getAttribute('data-questao-index'), 10) + 1;
        const radio = container.querySelector('input[type="radio"][name="questao_' + questaoId + '"]:checked');
        const textarea = container.querySelector('textarea[data-questao-id="' + questaoId + '"]');
        if (radio) return;
        if (textarea) {
            if (textarea.value && textarea.value.trim() !== '') return;
        }
        naoRespondidas.push(index);
    });
    if (naoRespondidas.length > 0) {
        mostrarModal('Atenção', 'Responda todas as questões antes de finalizar. Questões não respondidas: ' + naoRespondidas.join(', '));
        irParaQuestao(naoRespondidas[0] - 1);
        return;
    }
    
    window._finalizandoProva = true;
    continuarFinalizarProva(null);
    try {
        window.parent.postMessage({ tipo: 'finalizando_materia' }, '*');
    } catch (e) {}
};

function abrirModalRevisao() {
    var container = document.getElementById('revisao-marcacao-final');
    if (!container) return;
    container.innerHTML = '';
    var containers = document.querySelectorAll('.questao-container');
    containers.forEach(function(c, idx) {
        var num = idx + 1;
        var questaoId = c.getAttribute('data-questao-id');
        var radio = c.querySelector('input[type="radio"][name="questao_' + questaoId + '"]:checked');
        var textarea = c.querySelector('textarea[data-questao-id="' + questaoId + '"]');
        var textoResposta = '';
        if (radio) {
            var label = radio.closest('label');
            if (label) {
                var span = label.querySelector('span');
                textoResposta = span ? (span.textContent || span.innerText || '').trim().substring(0, 200) : '';
            }
        } else if (textarea && textarea.value) {
            textoResposta = 'Dissertativa: ' + (textarea.value.trim().substring(0, 150) + (textarea.value.length > 150 ? '...' : ''));
        } else {
            textoResposta = '(sem resposta)';
        }
        var div = document.createElement('div');
        div.className = 'text-sm py-2 border-b border-gray-200 last:border-0';
        div.innerHTML = '<strong>Questão ' + num + '</strong>: ' + (textoResposta || '(vazia)').substring(0, 120) + (textoResposta.length > 120 ? '...' : '');
        container.appendChild(div);
    });
    var modal = document.getElementById('modal-revisao-realizar');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
    document.getElementById('btn-revisao-voltar').onclick = function() {
        modal.style.display = 'none';
        modal.classList.add('hidden');
        window._finalizandoProva = false;
    };
    var btnConfirmar = document.getElementById('btn-revisao-confirmar');
    if (btnConfirmar) {
        btnConfirmar.onclick = function(ev) {
            if (ev) { ev.preventDefault(); ev.stopPropagation(); }
            if (btnConfirmar.disabled) return;
            confirmarEnviarComRevisao();
        };
    }
}

function fecharModalRevisao() {
    var modal = document.getElementById('modal-revisao-realizar');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
    var btn = document.getElementById('btn-revisao-confirmar');
    if (btn) {
        btn.disabled = false;
        btn.textContent = 'Confirmar e enviar';
    }
}

function confirmarEnviarComRevisao() {
    var btn = document.getElementById('btn-revisao-confirmar');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Enviando...';
    }
    continuarFinalizarProva(null);
}

function continuarFinalizarProva(comprovanteBase64) {
    const urlParams = new URLSearchParams(window.location.search);
    const modoBloco = urlParams.get('modo_bloco') === '1';
    const modoSeguro = urlParams.get('modo_seguro') === '1';
    let urlFinalizar = '<?= URL ?>/aluno/provas/finalizar/<?= $prova['id'] ?>';
    if (modoBloco) urlFinalizar += '?modo_bloco=1'; if (modoSeguro) urlFinalizar += (modoBloco ? '&' : '?') + 'modo_seguro=1';

    var body = comprovanteBase64 ? JSON.stringify({ comprovante_base64: comprovanteBase64 }) : '{}';
    var fetchOptions = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: body,
        credentials: 'same-origin',
        keepalive: true
    };
    function parseRespostaFinalizar(texto) {
        var trimmed = (texto || '').trim();
        if (!trimmed) return null;
        try { return JSON.parse(trimmed); } catch (e1) {}
        var idx = trimmed.indexOf('{');
        if (idx >= 0) {
            try { return JSON.parse(trimmed.slice(idx)); } catch (e2) {}
        }
        return null;
    }
    var fetchTimeout = 45000;
    var fetchPromise = fetch(urlFinalizar, fetchOptions).then(function(r) {
        return r.text().then(function(t) {
            var data = parseRespostaFinalizar(t);
            if (data !== null) {
                if (!r.ok && !data.success) {
                    throw new Error(data.error || data.message || ('Erro ' + r.status));
                }
                return data;
            }
            var trimmed = (t || '').trim();
            if (!r.ok) throw new Error(trimmed && trimmed.length < 300 ? trimmed : ('Erro ' + r.status));
            throw new Error('Resposta inválida do servidor');
        });
    });
    var timeoutPromise = new Promise(function(_, reject) {
        setTimeout(function() { reject(new Error('timeout')); }, fetchTimeout);
    });
    Promise.race([fetchPromise, timeoutPromise])
    .then(function(data) {
        fecharModalRevisao();
        if (!data) { window._finalizandoProva = false; mostrarModal('Erro', 'Erro ao finalizar prova'); return; }
        if (data.success) {
            if (typeof window.removeEventListener === 'function') {
                window.removeEventListener('beforeunload', bloquearSaida);
                window.removeEventListener('keydown', bloquearTeclas);
            }
            if (data.voltar_escolher_materia && data.bloco_id) {
                <?php if (!empty($modo_embed)): ?>
                window._finalizandoProva = true;
                try {
                    window.parent.postMessage({ tipo: 'prova_finalizada', bloco_id: data.bloco_id }, '*');
                } catch (e) {}
                return;
                <?php else: ?>
                window._finalizandoProva = true;
                mostrarModal('Prova finalizada', 'Escolha a próxima matéria.');
                document.getElementById('modal-aviso-realizar-ok').onclick = function() {
                    document.getElementById('modal-aviso-realizar').style.display = 'none';
                    document.getElementById('modal-aviso-realizar').classList.add('hidden');
                    window.location.href = '<?= URL ?>/aluno/provas/bloco/' + data.bloco_id + '/iniciar-seguro?materia_ok=1';
                };
                return;
                <?php endif; ?>
            }
            window._finalizandoProva = false;
            if (data.modo_bloco && data.bloco_id) {
                mostrarModal('Sucesso', 'Bloco de provas finalizado com sucesso. Aguarde a coordenação liberar o gabarito.');
                document.getElementById('modal-aviso-realizar-ok').onclick = function() {
                    document.getElementById('modal-aviso-realizar').style.display = 'none';
                    document.getElementById('modal-aviso-realizar').classList.add('hidden');
                    window.location.href = '<?= URL ?>/aluno/provas';
                };
                return;
            }
            if (data.bloco_id && data.proxima_prova) {
                mostrarModal('Prova finalizada', 'Iniciando próxima prova: ' + data.proxima_prova.materia_nome);
                document.getElementById('modal-aviso-realizar-ok').onclick = function() {
                    document.getElementById('modal-aviso-realizar').style.display = 'none';
                    document.getElementById('modal-aviso-realizar').classList.add('hidden');
                    window.location.href = '<?= URL ?>/aluno/provas/realizar/' + data.proxima_prova.id + '?bloco_id=' + data.bloco_id;
                };
                return;
            }
            if (data.bloco_id && !data.proxima_prova) {
                mostrarModal('Concluído', 'Todas as provas do bloco foram finalizadas. Aguarde a coordenação liberar o gabarito.');
                document.getElementById('modal-aviso-realizar-ok').onclick = function() {
                    document.getElementById('modal-aviso-realizar').style.display = 'none';
                    document.getElementById('modal-aviso-realizar').classList.add('hidden');
                    window.location.href = '<?= URL ?>/aluno/provas';
                };
                return;
            }
            if (data.pode_mostrar) {
                window.location.href = '<?= URL ?>/aluno/provas/resultado/<?= $prova['id'] ?>';
            } else {
                mostrarModal('Prova finalizada', 'O resultado será liberado ' + (data.liberar_resultado === 'apos_todos' ? 'após todos terminarem' : 'em breve') + '.');
                document.getElementById('modal-aviso-realizar-ok').onclick = function() {
                    document.getElementById('modal-aviso-realizar').style.display = 'none';
                    document.getElementById('modal-aviso-realizar').classList.add('hidden');
                    window.location.href = '<?= URL ?>/aluno/provas';
                };
            }
        } else {
            window._finalizandoProva = false;
            mostrarModal('Erro', (data.error || 'Erro ao finalizar prova'));
        }
    })
    .catch(function(error) {
        fecharModalRevisao();
        window._finalizandoProva = false;
        console.error('Erro ao finalizar:', error);
        var msg = (error && error.message) ? error.message : 'Erro ao finalizar prova';
        if (msg === 'timeout') msg = 'A requisição demorou muito. Verifique sua conexão e tente novamente.';
        else if (msg === 'Failed to fetch' || msg.indexOf('fetch') !== -1) msg = 'Falha de conexão. Verifique a internet e tente novamente.';
        else if (msg.indexOf('Resposta inválida') !== -1) msg = 'O servidor retornou um erro. Tente novamente ou contate o suporte.';
        else if (msg.indexOf('Sessão expirada') !== -1) msg = 'Sua sessão expirou. Faça login novamente e entre na prova.';
        // Erro que não chegou a virar resposta do servidor (rede caiu, timeout) — só dá pra
        // saber disso aqui no cliente, por isso registra o log daqui em vez do backend.
        registrarLogProvaEvento('erro_finalizar', 'Falha ao finalizar (cliente): ' + (error && error.message ? error.message : String(error)));
        mostrarModal('Erro', msg);
    });
};

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    atualizarNavegacao();
    aplicarBloqueiosSeguranca();
    <?php if (!empty($modo_embed)): ?>
    window.addEventListener('message', function(e) {
        if (e.data && e.data.tipo === 'prova_cancelada_bloco') {
            window._provaCancelada = true;
            if (typeof cancelarBlocoModoSeguroNoServidor === 'function') {
                cancelarBlocoModoSeguroNoServidor();
            }
            document.querySelectorAll('input, textarea, button, select').forEach(function(el) {
                el.disabled = true;
            });
            mostrarModal('Prova cancelada', 'Sua prova foi cancelada por saída do modo seguro. Aguarde liberação do coordenador.');
            document.getElementById('modal-aviso-realizar-ok').onclick = function() {
                try { window.parent.location.href = '<?= URL ?>/aluno/provas'; } catch (err) {
                    window.location.href = '<?= URL ?>/aluno/provas';
                }
            };
        }
    });
    <?php endif; ?>
});

// Navegação entre questões
function irParaQuestao(index) {
    if (index < 0 || index >= totalQuestoes) return;
    
    // Esconde questão atual
    document.querySelectorAll('.questao-container').forEach(function(container) {
        container.style.display = 'none';
    });
    
    // Mostra questão selecionada
    const questaoContainer = document.querySelector(`[data-questao-index="${index}"]`);
    if (questaoContainer) {
        questaoContainer.style.display = 'block';
        questaoAtual = index;
        atualizarNavegacao();
        atualizarSeletorQuestoes();

        // Scroll para o topo
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Re-renderizar fórmulas matemáticas (LaTeX) na questão agora visível
        if (typeof window.renderMathProva === 'function') {
            setTimeout(window.renderMathProva, 100);
        }
        document.dispatchEvent(new CustomEvent('ei:question-visible', { detail: { index: index } }));
    }
}

function questaoAnterior() {
    if (questaoAtual > 0) {
        irParaQuestao(questaoAtual - 1);
    }
}

function atualizarNavegacao() {
    const btnAnterior = document.getElementById('btn-anterior');
    const btnProximo = document.getElementById('btn-proximo');
    
    btnAnterior.disabled = (questaoAtual === 0);
    btnProximo.disabled = (questaoAtual === totalQuestoes - 1);
    
    if (questaoAtual === totalQuestoes - 1) {
        btnProximo.style.display = 'none';
    } else {
        btnProximo.style.display = 'block';
    }
}

function atualizarSeletorQuestoes() {
    document.querySelectorAll('.questao-btn').forEach(function(btn, index) {
        const questaoId = questoes[index];
        const temResposta = document.querySelector(`input[name="questao_${questaoId}"][checked]`) || 
                           document.querySelector(`textarea[name="questao_${questaoId}_texto"]`)?.value;
        
        btn.classList.remove('bg-blue-600', 'text-white', 'border-blue-700', 
                             'bg-green-100', 'text-green-800', 'border-green-300',
                             'bg-gray-100', 'text-gray-700', 'border-gray-300');
        
        if (index === questaoAtual) {
            btn.classList.add('bg-blue-600', 'text-white', 'border-blue-700');
        } else if (temResposta) {
            btn.classList.add('bg-green-100', 'text-green-800', 'border-green-300');
        } else {
            btn.classList.add('bg-gray-100', 'text-gray-700', 'border-gray-300');
        }
    });
}

// Salvar respostas automaticamente
document.querySelectorAll('input[type="radio"], textarea').forEach(function(element) {
    element.addEventListener('change', function() {
        salvarResposta(this);
        atualizarSeletorQuestoes();
    });
});

function salvarResposta(element) {
    const questaoContainer = element.closest('.questao-container');
    if (!questaoContainer) return;
    
    const questaoId = questaoContainer.getAttribute('data-questao-id');
    const alternativaId = element.getAttribute('data-alternativa-id');
    const respostaTexto = element.type === 'textarea' ? element.value : null;
    const provaIdOriginal = questaoContainer.getAttribute('data-prova-id-original');
    
    const data = {
        questao_id: questaoId,
        alternativa_id: alternativaId || null,
        resposta_texto: respostaTexto,
        prova_id_original: provaIdOriginal || null
    };
    
    fetch('<?= URL ?>/aluno/provas/salvar-resposta/<?= $prova['id'] ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            var toast = document.getElementById('toast-resposta-salva');
            if (toast) {
                toast.style.display = 'block';
                toast.classList.remove('hidden');
                clearTimeout(window._toastRespostaTimeout);
                window._toastRespostaTimeout = setTimeout(function() {
                    toast.style.display = 'none';
                    toast.classList.add('hidden');
                }, 2000);
            }
        } else if (data.error && String(data.error).toLowerCase().indexOf('cancelada') !== -1) {
            mostrarModal('Prova cancelada', data.error);
        }
    })
    .catch(error => {
        console.error('Erro ao salvar resposta:', error);
    });
}

<?php if (!empty($bloco_termino_iso)): ?>
// Cronômetro do bloco (não zera ao trocar de matéria). Ao zerar, finaliza automaticamente (sem depender do OK).
(function() {
    var blocoTermino = new Date('<?= date('Y-m-d\TH:i:s', strtotime($bloco_termino_iso)) ?>');
    var timerElement = document.getElementById('timer');
    if (!timerElement) return;
    var intervalId = null;
    function atualizarTimer() {
        var agora = new Date();
        if (agora >= blocoTermino) {
            if (intervalId) clearInterval(intervalId);
            intervalId = null;
            timerElement.textContent = '00:00';
            if (window._tempoEsgotadoFinalizado) return;
            window._tempoEsgotadoFinalizado = true;
            mostrarModal('Tempo esgotado', 'O tempo do bloco acabou. Finalizando prova automaticamente...');
            window._finalizandoProva = true;
            continuarFinalizarProva(null);
            document.getElementById('modal-aviso-realizar-ok').onclick = function() {
                document.getElementById('modal-aviso-realizar').style.display = 'none';
                document.getElementById('modal-aviso-realizar').classList.add('hidden');
            };
            return;
        }
        var seg = Math.floor((blocoTermino - agora) / 1000);
        var m = Math.floor(seg / 60);
        var s = seg % 60;
        timerElement.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }
    atualizarTimer();
    intervalId = setInterval(atualizarTimer, 1000);
})();
<?php elseif ($tempo_limite): ?>
// Timer por prova (quando não é bloco). Ao zerar, finaliza automaticamente (sem depender do OK).
let tempoRestante = <?= $tempo_restante ?> * 60; // em segundos
const timerElement = document.getElementById('timer');
let timerIntervalId = null;

function atualizarTimer() {
    const minutos = Math.floor(tempoRestante / 60);
    const segundos = tempoRestante % 60;
    timerElement.textContent = 
        String(minutos).padStart(2, '0') + ':' + 
        String(segundos).padStart(2, '0');
    
    if (tempoRestante <= 0) {
        if (timerIntervalId) clearInterval(timerIntervalId);
        timerIntervalId = null;
        if (window._tempoEsgotadoFinalizado) return;
        window._tempoEsgotadoFinalizado = true;
        mostrarModal('Tempo esgotado', 'O tempo acabou. Finalizando prova automaticamente...');
        window._finalizandoProva = true;
        continuarFinalizarProva(null);
        document.getElementById('modal-aviso-realizar-ok').onclick = function() {
            document.getElementById('modal-aviso-realizar').style.display = 'none';
            document.getElementById('modal-aviso-realizar').classList.add('hidden');
        };
        return;
    }
    
    if (window._eiPaused) return; // EducaInclui: pausa do cronômetro (acessibilidade)
    tempoRestante--;
}

timerIntervalId = setInterval(atualizarTimer, 1000);

<?php if (!empty($acessibilidade_pause)): ?>
// EducaInclui — pausar/retomar o cronômetro (acessibilidade)
(function(){
    var btn = document.getElementById('ei-pause-btn');
    if (!btn) return;
    window._eiPaused = false;
    btn.addEventListener('click', function(){
        window._eiPaused = !window._eiPaused;
        btn.textContent = window._eiPaused ? '▶ Retomar' : '⏸ Pausar';
        btn.classList.toggle('bg-amber-500', !window._eiPaused);
        btn.classList.toggle('bg-emerald-600', window._eiPaused);
        if (timerElement) timerElement.classList.toggle('opacity-40', window._eiPaused);
    });
})();
<?php endif; ?>
<?php endif; ?>

// ============================================
// BLOQUEIOS DE SEGURANÇA
// ============================================

// Log de Provas (Master): reporta tentativa de burlar o modo seguro/tela cheia.
// Usa sendBeacon (não bloqueia nem atrasa a prova, funciona até no beforeunload) com
// fallback pra fetch keepalive em navegador sem sendBeacon. Não depende de sessão
// válida — o endpoint aceita mesmo com sessão expirada (ver ExamController::logEvento).
// O servidor NUNCA confia em aluno_id vindo daqui: com sessão válida usa a sessão, sem
// sessão exige o token abaixo (emitido nesta mesma página enquanto a sessão era válida)
// — por isso não mandamos aluno_id no payload, só o token.
var LOG_PROVA_TOKEN = <?= json_encode((string) ($log_prova_token ?? ''), JSON_UNESCAPED_UNICODE) ?>;
var LOG_PROVA_PROVA_ID = <?= (int)($prova['id'] ?? 0) ?>;
var LOG_PROVA_BLOCO_ID = <?= (int)($bloco['id'] ?? 0) ?>;
function registrarLogProvaEvento(tipoEvento, detalhe) {
    try {
        var payload = JSON.stringify({
            tipo_evento: tipoEvento,
            token: LOG_PROVA_TOKEN || undefined,
            prova_id: LOG_PROVA_PROVA_ID || undefined,
            bloco_id: LOG_PROVA_BLOCO_ID || undefined,
            detalhe: detalhe || ''
        });
        var url = '<?= URL ?>/aluno/provas/log-evento';
        if (navigator.sendBeacon) {
            navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' }));
        } else {
            fetch(url, { method: 'POST', body: payload, headers: { 'Content-Type': 'application/json' }, keepalive: true, credentials: 'same-origin' }).catch(function() {});
        }
    } catch (e) {
        // log não pode quebrar a prova
    }
}

function aplicarBloqueiosSeguranca() {
    <?php if (!empty($modo_embed) && !empty($modo_seguro)): ?>
    // Captura Esc e F11 no iframe para não sair do fullscreen do pai
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.key === 'F11') {
            e.preventDefault();
            e.stopPropagation();
            registrarLogProvaEvento('tentativa_sair_tela_cheia', 'Tecla ' + e.key + ' durante prova em modo seguro/embed.');
            mostrarModal('Atenção', 'Para sair da prova, finalize todas as matérias e use o botão "Sair do modo prova" na tela principal.');
        }
    }, true);
    <?php endif; ?>
    // Bloquear TODO o teclado exceto em campos de resposta e botões permitidos (navegação/finalizar)
    document.addEventListener('keydown', function(e) {
        var el = document.activeElement;
        if (!el) return;
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        var allowKey = false;
        if (tag === 'input' || tag === 'textarea' || tag === 'select') allowKey = true;
        if (el.isContentEditable) allowKey = true;
        if (!allowKey && (tag === 'button' || tag === 'a')) {
            var id = el.id || '';
            var onclick = el.getAttribute('onclick') || '';
            if (id.indexOf('btn-questao-') !== -1 || id === 'btn-anterior' || id === 'btn-proximo' ||
                id === 'btn-finalizar-prova' ||
                id === 'modal-confirm-realizar-ok' || id === 'modal-confirm-realizar-cancel' || id === 'modal-aviso-realizar-ok' ||
                (el.closest && (el.closest('#modal-confirm-realizar') || el.closest('#modal-aviso-realizar'))) ||
                (onclick.indexOf('irParaQuestao') !== -1 || onclick.indexOf('questaoAnterior') !== -1 || onclick.indexOf('questaoProxima') !== -1 || onclick.indexOf('finalizarProva') !== -1)) {
                if (e.key === 'Enter' || e.key === ' ') allowKey = true;
            }
        }
        if (!allowKey) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, true);
    // Bloquear F5 e Ctrl+R
    window.addEventListener('keydown', bloquearTeclas);
    
    // Bloquear saída da página
    window.addEventListener('beforeunload', function(e) {
        if (window._finalizandoProva || window._provaCancelada) {
            return;
        }
        <?php if (empty($modo_embed)): ?>
        var urlParamsUnload = new URLSearchParams(window.location.search);
        if (urlParamsUnload.get('modo_seguro') === '1' && typeof cancelarBlocoModoSeguroNoServidor === 'function') {
            cancelarBlocoModoSeguroNoServidor();
        }
        <?php endif; ?>
        return bloquearSaida(e);
    });
    
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });
    document.addEventListener('copy', function(e) { e.preventDefault(); });
    document.addEventListener('cut', function(e) { e.preventDefault(); });
    document.addEventListener('paste', function(e) { e.preventDefault(); });
    
    // Bloquear seleção de texto (opcional - pode remover se necessário)
    // document.addEventListener('selectstart', function(e) {
    //     e.preventDefault();
    //     return false;
    // });
    
    var mod = function(e) { return e.ctrlKey || e.metaKey; };
    document.addEventListener('keydown', function(e) {
        if (mod(e) && (e.key === 'w' || e.key === 'W' || e.key === 't' || e.key === 'T' || e.key === 'n' || e.key === 'N')) {
            e.preventDefault();
            return false;
        }
        if (mod(e) && e.shiftKey && (e.key === 'n' || e.key === 'N')) {
            e.preventDefault();
            return false;
        }
        if (e.key === 'F12') {
            e.preventDefault();
            return false;
        }
        if (mod(e) && e.shiftKey && (e.key === 'i' || e.key === 'I' || e.key === 'j' || e.key === 'J' || e.key === 'c' || e.key === 'C')) {
            e.preventDefault();
            return false;
        }
    }, true);
    
    // Bloquear arrastar e soltar
    document.addEventListener('dragstart', function(e) {
        e.preventDefault();
        return false;
    });
    
    document.addEventListener('drop', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Desabilitar todos os links e botões exceto os permitidos
    document.addEventListener('click', function(e) {
        const target = e.target;
        const tagName = target.tagName.toLowerCase();
        
        // Permitir apenas botões de navegação e finalizar
        if (tagName === 'button' || tagName === 'a') {
            const id = target.id || '';
            const onclick = target.getAttribute('onclick') || '';
            const classe = target.className || '';
            
            // Permitir botões de navegação e botões dos modais (Confirmar/Cancelar/OK)
            if (id.includes('btn-questao-') || 
                id === 'btn-anterior' || 
                id === 'btn-proximo' ||
                id === 'btn-finalizar-prova' ||
                id === 'modal-confirm-realizar-ok' ||
                id === 'modal-confirm-realizar-cancel' ||
                id === 'modal-aviso-realizar-ok' ||
                (target.closest && (target.closest('#modal-confirm-realizar') || target.closest('#modal-aviso-realizar'))) ||
                onclick.includes('irParaQuestao') ||
                onclick.includes('questaoAnterior') ||
                onclick.includes('questaoProxima') ||
                onclick.includes('finalizarProva')) {
                return true;
            }
            
            // Bloquear todos os outros botões e links
            e.preventDefault();
            e.stopPropagation();
            mostrarModal('Atenção', 'Ação não permitida durante a prova.');
            return false;
        }
        
        // Bloquear cliques em inputs e textareas que não sejam da questão atual
        if ((tagName === 'input' || tagName === 'textarea' || tagName === 'label') && 
            !target.closest(`[data-questao-index="${questaoAtual}"]`)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, true); // Use capture phase para interceptar antes
    
    // Bloquear navegação do histórico
    window.history.pushState(null, null, window.location.href);
    window.addEventListener('popstate', function() {
        window.history.pushState(null, null, window.location.href);
        registrarLogProvaEvento('tentativa_voltar_navegador', 'Aluno tentou usar o botão Voltar do navegador durante a prova.');
        mostrarModal('Atenção', 'Não é permitido navegar para trás durante a prova.');
    });

    document.addEventListener('visibilitychange', function() {
        if (window._finalizandoProva || window._provaCancelada) return;
        if (document.hidden) {
            <?php if (!empty($modo_embed)): ?>
            return;
            <?php endif; ?>
            var urlParamsVis = new URLSearchParams(window.location.search);
            if (urlParamsVis.get('modo_seguro') === '1' && typeof cancelarBlocoModoSeguroNoServidor === 'function') {
                cancelarBlocoModoSeguroNoServidor();
            }
            <?php if (!empty($modo_seguro) && empty($modo_embed)): ?>
            if (typeof window.encerrarProvaPorSaida === 'function') {
                window.encerrarProvaPorSaida();
                return;
            }
            <?php endif; ?>
            registrarLogProvaEvento('tentativa_sair_tela_cheia', 'Aluno trocou de aba/minimizou a janela durante a prova.');
            mostrarModal('Atenção', 'Você não deve sair desta página durante a prova.');
        }
    });
}

function bloquearTeclas(e) {
    // Bloquear F5
    if (e.key === 'F5') {
        e.preventDefault();
        registrarLogProvaEvento('tentativa_atualizar_pagina', 'Aluno apertou F5 durante a prova.');
        mostrarModal('Atenção', 'Atualizar a página não é permitido durante a prova.');
        return false;
    }

    // Bloquear Ctrl+R
    if (e.ctrlKey && (e.key === 'r' || e.key === 'R')) {
        e.preventDefault();
        registrarLogProvaEvento('tentativa_atualizar_pagina', 'Aluno apertou Ctrl+R durante a prova.');
        mostrarModal('Atenção', 'Atualizar a página não é permitido durante a prova.');
        return false;
    }

    // Bloquear Ctrl+F5
    if (e.ctrlKey && e.key === 'F5') {
        e.preventDefault();
        registrarLogProvaEvento('tentativa_atualizar_pagina', 'Aluno apertou Ctrl+F5 durante a prova.');
        return false;
    }
}

function bloquearSaida(e) {
    e.preventDefault();
    e.returnValue = 'Você está realizando uma prova. Tem certeza que deseja sair? Suas respostas podem não ser salvas.';
    return e.returnValue;
}

// Bloquear console (tentativa - pode ser contornado)
(function() {
    const noop = function() {};
    const methods = ['log', 'debug', 'info', 'warn', 'error', 'assert', 'dir', 'dirxml', 
                     'group', 'groupEnd', 'time', 'timeEnd', 'count', 'trace', 'profile', 'profileEnd'];
    methods.forEach(function(method) {
        console[method] = noop;
    });
})();

<?php if (!empty($modo_seguro) && empty($modo_embed)): ?>
(function() {
    var provaId = <?= (int)($prova['id'] ?? 0) ?>;
    var blocoId = new URLSearchParams(window.location.search).get('bloco_id') || '<?= (int)($bloco['id'] ?? 0) ?>';
    var blocoIdValido = blocoId && blocoId !== '0';
    var urlCancelarBloco = '<?= URL ?>/aluno/provas/bloco/' + (blocoId || '0') + '/cancelar-seguro';
    var urlIniciarSeguro = '<?= URL ?>/aluno/provas/bloco/' + (blocoId || '0') + '/iniciar-seguro?encerrado=1';
    // Fallback garantido: Minhas Provas cancela no PHP via ?cancelar_bloco=ID
    var urlMinhasProvasCancelar = blocoIdValido
        ? '<?= URL ?>/aluno/provas?cancelar_bloco=' + blocoId
        : '<?= URL ?>/aluno/provas';

    window.encerrarProvaPorSaida = function() {
        if (window._encerrandoPorSaida || window._finalizandoProva) return;
        window._encerrandoPorSaida = true;
        var irPara = blocoIdValido ? urlIniciarSeguro : urlMinhasProvasCancelar;
        var promessa = (typeof cancelarBlocoModoSeguroNoServidor === 'function')
            ? cancelarBlocoModoSeguroNoServidor()
            : fetch(urlCancelarBloco, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: '{}'
            }).then(function(r) { return r.ok; }).catch(function() { return false; });
        promessa.then(function(ok) {
            // Se a chamada AJAX falhou, vai direto para Minhas Provas, que cancela no PHP
            window.location.href = ok ? irPara : urlMinhasProvasCancelar;
        }).catch(function() {
            window.location.href = urlMinhasProvasCancelar;
        });
    };

    function estaEmFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
    }
    function entrarFullscreen() {
        var el = document.documentElement;
        if (el.requestFullscreen) el.requestFullscreen();
        else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
        else if (el.msRequestFullscreen) el.msRequestFullscreen();
    }
    function esconderOverlay() {
        var el = document.getElementById('overlay-tela-cheia-realizar');
        if (el) el.style.display = 'none';
    }
    function mostrarOverlay() {
        var el = document.getElementById('overlay-tela-cheia-realizar');
        if (el) el.style.display = 'flex';
    }
    // Bloqueia Esc e F11 para não sair do fullscreen; só sai ao finalizar a prova e clicar em Finalizar
    document.addEventListener('keydown', function(e) {
        if (!estaEmFullscreen()) return;
        if (window._finalizandoProva || window._encerrandoPorSaida) return;
        if (e.key === 'Escape' || e.key === 'F11') {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, true);
    document.addEventListener('DOMContentLoaded', function() {
        // Tenta tela cheia ao carregar (em alguns navegadores o gesto do link ainda vale). Se não estiver em tela cheia após um momento, mostra overlay.
        if (estaEmFullscreen()) {
            esconderOverlay();
        } else {
            entrarFullscreen();
            setTimeout(function() {
                if (!estaEmFullscreen()) {
                    mostrarOverlay();
                } else {
                    esconderOverlay();
                }
            }, 150);
        }
        document.getElementById('btn-entrar-tela-cheia-realizar') && document.getElementById('btn-entrar-tela-cheia-realizar').addEventListener('click', function() {
            entrarFullscreen();
        });
        document.addEventListener('fullscreenchange', function() {
            if (estaEmFullscreen()) {
                esconderOverlay();
            } else if (!window._finalizandoProva && !window._encerrandoPorSaida) {
                // Saiu da tela cheia sem finalizar: força voltar ao fullscreen (aluno só sai ao clicar em Finalizar Prova)
                setTimeout(entrarFullscreen, 50);
            }
        });
        document.addEventListener('webkitfullscreenchange', function() {
            if (estaEmFullscreen()) {
                esconderOverlay();
            } else if (!window._finalizandoProva && !window._encerrandoPorSaida) {
                setTimeout(entrarFullscreen, 50);
            }
        });
        window.addEventListener('beforeunload', function() {
            if (window._encerrandoPorSaida || window._finalizandoProva) return;
            if (typeof cancelarBlocoModoSeguroNoServidor === 'function') {
                cancelarBlocoModoSeguroNoServidor();
            } else if (navigator.sendBeacon) {
                navigator.sendBeacon(urlCancelarBloco, new Blob(['{}'], { type: 'application/json' }));
            }
        });
    });
})();
<?php endif; ?>
</script>

<?php if (!empty($acessibilidade_tts) || !empty($acessibilidade_highlight)): ?>
<script>
(function(){
    function init(){
        <?php if (!empty($acessibilidade_highlight)): ?>
        // EducaInclui — destaque de comandos da questão
        try {
            var comandos = ['assinale','marque','calcule','resolva','explique','justifique','identifique','indique','determine','classifique','analise','compare','descreva','relacione','complete','leia','observe','considere','verdadeiro','falso','correta','incorreta','exceto','apenas','nao','não'];
            var re = new RegExp('\\b(' + comandos.join('|') + ')\\b','gi');
            function esc(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
            function walk(node){
                for(var i=node.childNodes.length-1;i>=0;i--){
                    var c=node.childNodes[i];
                    if(c.nodeType===3){
                        var t=c.nodeValue;
                        var e=esc(t);
                        re.lastIndex=0;
                        if(re.test(e)){
                            re.lastIndex=0;
                            var span=document.createElement('span');
                            span.innerHTML=e.replace(re,'<mark class="ei-kw">$1</mark>');
                            c.parentNode.replaceChild(span,c);
                        }
                    } else if(c.nodeType===1 && c.tagName!=='MARK' && c.tagName.indexOf('MJX')!==0 && !/SCRIPT|STYLE/.test(c.tagName)){
                        walk(c);
                    }
                }
            }
            document.querySelectorAll('.questao-container .text-gray-700').forEach(function(node){
                if(node.dataset.eiKw) return;
                node.dataset.eiKw='1';
                walk(node);
            });
        } catch(e){ console.warn('EducaInclui highlight:', e); }
        <?php endif; ?>

        <?php if (!empty($acessibilidade_tts)): ?>
        // EducaInclui — leitura em voz alta (TTS) por questão
        try {
            if('speechSynthesis' in window){
                var falando=null;
                var autoRead=<?= !empty($acessibilidade_auto_read) ? 'true' : 'false' ?>;
                var speedKey=<?= json_encode((string) ($acessibilidade_read_speed ?? 'normal')) ?>;
                var speedMap={lenta:0.75,normal:0.95,rapida:1.15};
                var readRate=speedMap[speedKey] || speedMap.normal;
                function atualizar(){
                    document.querySelectorAll('.ei-tts-btn').forEach(function(b){
                        b.textContent=(falando===b)?'⏹ Parar':'🔊 Ouvir';
                    });
                }
                function ler(txt,btn){
                    window.speechSynthesis.cancel();
                    if(falando===btn){falando=null;atualizar();return;}
                    var u=new SpeechSynthesisUtterance(txt);
                    u.lang='pt-BR';u.rate=readRate;
                    u.onend=function(){falando=null;atualizar();};
                    falando=btn;atualizar();
                    window.speechSynthesis.speak(u);
                }
                function textoQuestao(c){
                    var partes=[];
                    var en=c.querySelector('.text-gray-700.text-lg');
                    if(en) partes.push(en.textContent.trim());
                    c.querySelectorAll('label .text-gray-700').forEach(function(a,i){
                        partes.push('Alternativa '+String.fromCharCode(65+i)+': '+a.textContent.trim());
                    });
                    return partes.join('. ');
                }
                document.querySelectorAll('.questao-container').forEach(function(c){
                    var header=c.querySelector('.flex.justify-between');
                    if(!header || header.querySelector('.ei-tts-btn')) return;
                    var btn=document.createElement('button');
                    btn.type='button';
                    btn.className='ei-tts-btn shrink-0 px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700';
                    btn.textContent='🔊 Ouvir';
                    btn.addEventListener('click',function(){
                        ler(textoQuestao(c),btn);
                    });
                    header.appendChild(btn);
                });
                if(autoRead){
                    document.addEventListener('ei:question-visible',function(e){
                        setTimeout(function(){
                            var idx=e && e.detail ? e.detail.index : null;
                            var selector=idx===null ? '.questao-container .ei-tts-btn' : '.questao-container[data-questao-index="'+idx+'"] .ei-tts-btn';
                            var btn=document.querySelector(selector);
                            if(btn) btn.click();
                        },250);
                    });
                    setTimeout(function(){
                        var atual=document.querySelector('.questao-container:not([style*="display: none"]) .ei-tts-btn') || document.querySelector('.questao-container .ei-tts-btn');
                        if(atual) atual.click();
                    },500);
                }
                window.addEventListener('beforeunload',function(){ try{window.speechSynthesis.cancel();}catch(e){} });
            }
        } catch(e){ console.warn('EducaInclui TTS:', e); }
        <?php endif; ?>
    }
    if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
</script>
<?php endif; ?>

<?php if (!empty($acessibilidade_progress)): ?>
<script>
// EducaInclui — barra de progresso (respondidas / total)
(function(){
    function calc(){
        var conts = document.querySelectorAll('.questao-container');
        if(!conts.length) return;
        var done = 0;
        conts.forEach(function(c){
            var radios = c.querySelectorAll('input[type="radio"]');
            var ta = c.querySelector('textarea');
            var ok = false;
            radios.forEach(function(r){ if(r.checked) ok = true; });
            if(ta && ta.value.trim() !== '') ok = true;
            if(ok) done++;
        });
        var pct = Math.round((done / conts.length) * 100);
        var bar = document.getElementById('ei-progress-bar');
        var lbl = document.getElementById('ei-progress-label');
        if(bar) bar.style.width = pct + '%';
        if(lbl) lbl.textContent = pct + '% (' + done + '/' + conts.length + ')';
    }
    function init(){
        document.addEventListener('change', calc, true);
        document.addEventListener('input', calc, true);
        calc();
    }
    if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
</script>
<?php endif; ?>

<?php if (!empty($acessibilidade_audio)): ?>
<script>
// EducaInclui — resposta por áudio em questões dissertativas (transcrição automática)
(function(){
    var CSRF = '<?= htmlspecialchars((string) ($csrf_token ?? ($_SESSION['csrf_token'] ?? ''))) ?>';
    function setStatus(qid, txt){
        var el = document.querySelector('.ei-audio-status[data-questao-id="' + qid + '"]');
        if(el) el.textContent = txt;
    }
    function bind(btn){
        var qid = btn.getAttribute('data-questao-id');
        var rec = null, chunks = [], gravando = false;
        btn.addEventListener('click', function(){
            if(gravando){ if(rec && rec.state !== 'inactive') rec.stop(); return; }
            if(!navigator.mediaDevices || !window.MediaRecorder){ setStatus(qid, 'Gravação não suportada neste navegador.'); return; }
            navigator.mediaDevices.getUserMedia({audio:true}).then(function(stream){
                chunks = [];
                rec = new MediaRecorder(stream);
                rec.ondataavailable = function(e){ if(e.data.size) chunks.push(e.data); };
                rec.onstop = function(){
                    stream.getTracks().forEach(function(t){ t.stop(); });
                    var blob = new Blob(chunks, {type:'audio/webm'});
                    enviar(qid, blob);
                };
                rec.start();
                gravando = true;
                btn.textContent = '⏹ Parar gravação';
                btn.classList.add('bg-red-600');
                setStatus(qid, 'Gravando... fale sua resposta.');
            }).catch(function(){ setStatus(qid, 'Permissão de microfone negada.'); });
        });
        function enviar(qid, blob){
            gravando = false;
            btn.textContent = '🎤 Responder por áudio';
            btn.classList.remove('bg-red-600');
            setStatus(qid, 'Transcrevendo...');
            var fd = new FormData();
            fd.append('audio', blob, 'resposta.webm');
            fd.append('_token', CSRF);
            fetch('<?= URL ?>/aluno/provas/voz-para-texto', {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(j){
                    if(j && j.success && j.texto){
                        var ta = document.querySelector('textarea[data-questao-id="' + qid + '"]');
                        if(ta){
                            ta.value = (ta.value ? ta.value.trim() + ' ' : '') + j.texto.trim();
                            ta.dispatchEvent(new Event('input', {bubbles:true}));
                            ta.dispatchEvent(new Event('change', {bubbles:true}));
                        }
                        setStatus(qid, 'Transcrição adicionada. Revise o texto.');
                    } else {
                        setStatus(qid, (j && j.error) ? j.error : 'Não foi possível transcrever.');
                    }
                })
                .catch(function(){ setStatus(qid, 'Erro ao enviar o áudio.'); });
        }
    }
    function init(){ document.querySelectorAll('.ei-audio-btn').forEach(bind); }
    if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
</script>
<?php endif; ?>

<?php if (!empty($acessibilidade_glossary)): ?>
<script>
// EducaInclui — glossário: clique duplo numa palavra do enunciado para ver o significado
(function(){
    var pop = null;
    function fecharPop(){ if(pop){ pop.remove(); pop = null; } }
    function mostrarPop(x, y, palavra, texto){
        fecharPop();
        pop = document.createElement('div');
        pop.className = 'fixed z-[10050] max-w-xs bg-white border border-gray-300 rounded-lg shadow-xl p-3 text-sm';
        pop.style.left = Math.min(x, window.innerWidth - 280) + 'px';
        pop.style.top = (y + 12) + 'px';
        pop.innerHTML = '<div class="flex justify-between items-center mb-1"><strong class="text-indigo-700">' + palavra + '</strong>'
            + '<button type="button" class="text-gray-400 hover:text-gray-700 text-xs" aria-label="Fechar">✕</button></div>'
            + '<div class="text-gray-700">' + texto + '</div>';
        pop.querySelector('button').addEventListener('click', fecharPop);
        document.body.appendChild(pop);
    }
    function definir(palavra, x, y){
        mostrarPop(x, y, palavra, 'Buscando significado...');
        fetch('https://pt.wiktionary.org/api/rest_v1/page/definition/' + encodeURIComponent(palavra.toLowerCase()))
            .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
            .then(function(j){
                var defs = (j && j.pt) ? j.pt : null;
                if(!defs || !defs.length){ mostrarPop(x, y, palavra, 'Significado não encontrado.'); return; }
                var lista = defs.slice(0,3).map(function(d){
                    var t = (d.definitions && d.definitions[0] && d.definitions[0].definition) || '';
                    return t.replace(/<[^>]+>/g,'');
                }).filter(Boolean);
                mostrarPop(x, y, palavra, lista.length ? '• ' + lista.join('<br>• ') : 'Significado não encontrado.');
            })
            .catch(function(){ mostrarPop(x, y, palavra, 'Não foi possível buscar agora.'); });
    }
    function init(){
        document.querySelectorAll('.questao-container').forEach(function(c){
            c.addEventListener('dblclick', function(e){
                var sel = (window.getSelection ? window.getSelection().toString() : '').trim();
                if(!sel || sel.length < 2 || /\s/.test(sel)) return;
                var palavra = sel.replace(/[^\p{L}-]/gu,'');
                if(palavra.length < 2) return;
                definir(palavra, e.clientX, e.clientY);
            });
        });
        document.addEventListener('click', function(e){ if(pop && !pop.contains(e.target)) fecharPop(); });
    }
    if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
</script>
<?php endif; ?>
