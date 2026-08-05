<?php
$deck = $deck ?? [];
$cards = $cards ?? [];
$explicacoesSalvas = $explicacoes_salvas ?? [];
$deckId = (int)($deck['id'] ?? 0);
$iaName = LayoutHelper::getIaName();
?>
<div class="min-h-[80vh] flex flex-col max-w-4xl mx-auto">
    <div class="flex-shrink-0 px-4 py-3 flex items-center justify-between border-b border-gray-200 bg-white">
        <div>
            <a href="<?= URL ?>/flashcards/deck/<?= $deckId ?>" class="text-indigo-600 hover:text-indigo-700 font-medium text-sm flex items-center gap-1 mb-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                Voltar ao baralho
            </a>
            <h1 class="text-xl font-bold text-gray-900">Explicação com a <?= htmlspecialchars($iaName) ?></h1>
            <p class="text-sm text-gray-500"><?= count($cards) ?> cartão(ões) para entender melhor</p>
        </div>
    </div>

    <?php if (empty($cards)): ?>
        <div class="flex-1 flex items-center justify-center p-8">
            <p class="text-gray-500">Nenhum cartão para explicar.</p>
        </div>
    <?php else: ?>
        <div class="flex-1 px-4 py-6 space-y-6">
            <!-- Navegação por cartão -->
            <div class="flex items-center justify-between text-sm text-gray-600">
                <span id="explicar-card-counter">Cartão 1 de <?= count($cards) ?></span>
                <div class="flex gap-2">
                    <button type="button" id="explicar-prev" class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed" disabled>Anterior</button>
                    <button type="button" id="explicar-next" class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Próximo</button>
                </div>
            </div>

            <!-- Conteúdo do cartão atual -->
            <div class="bg-gray-800 rounded-2xl p-6 text-white">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Pergunta</p>
                <p id="explicar-pergunta" class="text-lg leading-relaxed mb-4"></p>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Resposta do cartão</p>
                <p id="explicar-resposta" class="text-base leading-relaxed"></p>
            </div>

            <!-- Explicação da IA -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-6">
                <h2 class="font-semibold text-amber-900 mb-2 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-amber-200 flex items-center justify-center text-amber-800">🤖</span>
                    Explicação da <?= htmlspecialchars($iaName) ?>
                </h2>
                <div id="explicar-area">
                    <p id="explicar-placeholder" class="text-amber-800/80 text-sm">Clique em "Gerar explicação" para a <?= htmlspecialchars($iaName) ?> explicar este conteúdo de forma mais fácil.</p>
                    <div id="explicar-loading" class="hidden mt-3 flex items-center gap-2 text-amber-800">
                        <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>Gerando explicação...</span>
                    </div>
                    <div id="explicar-conteudo" class="hidden mt-3 text-amber-900 leading-relaxed whitespace-pre-wrap"></div>
                </div>
                <div id="explicar-botoes" class="mt-4 flex flex-wrap gap-3">
                    <button type="button" id="explicar-gerar-btn" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium text-sm transition-colors">
                        Gerar explicação
                    </button>
                    <button type="button" id="explicar-ainda-nao-btn" class="hidden px-4 py-2 border-2 border-amber-600 text-amber-700 hover:bg-amber-100 rounded-lg font-medium text-sm transition-colors">
                        Ainda não entendi
                    </button>
                    <button type="button" id="explicar-entendi-btn" class="hidden px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium text-sm transition-colors">
                        Entendi
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($cards)): ?>
<script>
(function() {
    var baseUrl = '<?= URL ?>';
    var deckId = <?= $deckId ?>;
    var cards = <?= json_encode(array_values($cards)) ?>;
    var totalCards = cards.length;
    var currentIndex = 0;
    var explicacoesPorCard = <?= json_encode($explicacoesSalvas) ?>; // cardId -> array de textos (salvos no banco)

    var perguntaEl = document.getElementById('explicar-pergunta');
    var respostaEl = document.getElementById('explicar-resposta');
    var counterEl = document.getElementById('explicar-card-counter');
    var prevBtn = document.getElementById('explicar-prev');
    var nextBtn = document.getElementById('explicar-next');
    var placeholderEl = document.getElementById('explicar-placeholder');
    var loadingEl = document.getElementById('explicar-loading');
    var conteudoEl = document.getElementById('explicar-conteudo');
    var gerarBtn = document.getElementById('explicar-gerar-btn');
    var aindaNaoBtn = document.getElementById('explicar-ainda-nao-btn');
    var entendiBtn = document.getElementById('explicar-entendi-btn');

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function getCard() { return cards[currentIndex] || null; }
    function getCardId() { var c = getCard(); return c ? c.id : 0; }

    function renderCard() {
        var card = getCard();
        if (!card) return;
        perguntaEl.innerHTML = escapeHtml(card.question || '').replace(/\n/g, '<br>');
        respostaEl.innerHTML = escapeHtml(card.answer || '').replace(/\n/g, '<br>');
        counterEl.textContent = 'Cartão ' + (currentIndex + 1) + ' de ' + totalCards;
        prevBtn.disabled = currentIndex === 0;
        nextBtn.disabled = currentIndex >= totalCards - 1;
        var cid = getCardId();
        if (explicacoesPorCard[cid] === undefined) {
            explicacoesPorCard[cid] = [];
        }
        showEstadoExplicacao();
    }

    function showEstadoExplicacao() {
        var list = explicacoesPorCard[getCardId()] || [];
        if (list.length === 0) {
            placeholderEl.classList.remove('hidden');
            loadingEl.classList.add('hidden');
            conteudoEl.classList.add('hidden');
            gerarBtn.classList.remove('hidden');
            aindaNaoBtn.classList.add('hidden');
            entendiBtn.classList.add('hidden');
        } else {
            placeholderEl.classList.add('hidden');
            loadingEl.classList.add('hidden');
            conteudoEl.classList.remove('hidden');
            conteudoEl.textContent = list[list.length - 1];
            gerarBtn.classList.add('hidden');
            aindaNaoBtn.classList.remove('hidden');
            entendiBtn.classList.remove('hidden');
        }
    }

    function setLoading(loading) {
        if (loading) {
            loadingEl.classList.remove('hidden');
            gerarBtn.disabled = true;
            aindaNaoBtn.disabled = true;
        } else {
            loadingEl.classList.add('hidden');
            gerarBtn.disabled = false;
            aindaNaoBtn.disabled = false;
        }
    }

    function gerarExplicacao() {
        var card = getCard();
        if (!card) return;
        var anteriores = explicacoesPorCard[card.id] || [];
        setLoading(true);
        var formData = new FormData();
        var token = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        if (token) formData.append('_token', token);
        formData.append('deck_id', deckId);
        formData.append('card_id', card.id);
        anteriores.forEach(function(t) { formData.append('explicacoes_anteriores[]', t); });
        fetch(baseUrl + '/flashcards/explicar/gerar', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            setLoading(false);
            if (data.success && data.explicacao) {
                if (!explicacoesPorCard[card.id]) explicacoesPorCard[card.id] = [];
                explicacoesPorCard[card.id].push(data.explicacao);
                showEstadoExplicacao();
            } else {
                alert(data.error || 'Não foi possível gerar a explicação.');
            }
        })
        .catch(function() {
            setLoading(false);
            alert('Erro de conexão. Tente novamente.');
        });
    }

    prevBtn.addEventListener('click', function() {
        if (currentIndex <= 0) return;
        currentIndex--;
        renderCard();
    });
    nextBtn.addEventListener('click', function() {
        if (currentIndex >= totalCards - 1) return;
        currentIndex++;
        renderCard();
    });
    gerarBtn.addEventListener('click', gerarExplicacao);
    aindaNaoBtn.addEventListener('click', gerarExplicacao);
    entendiBtn.addEventListener('click', function() {
        if (currentIndex >= totalCards - 1) {
            window.location.href = baseUrl + '/flashcards/deck/' + deckId;
        } else {
            currentIndex++;
            renderCard();
        }
    });

    renderCard();
})();
</script>
<?php endif; ?>
