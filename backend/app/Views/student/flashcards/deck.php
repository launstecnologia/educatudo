<?php
$deck = $deck ?? [];
$cards = $cards ?? [];
$deckId = (int)($deck['id'] ?? 0);
$totalCards = count($cards);
$isRevisar = !empty($_GET['revisar']);
$iaName = LayoutHelper::getIaName();
?>
<div class="min-h-[80vh] flex flex-col">
    <div class="flex-shrink-0 px-4 py-3 flex items-center justify-between border-b border-gray-200 bg-white">
        <div>
            <a href="<?= URL ?>/flashcards" class="text-indigo-600 hover:text-indigo-700 font-medium text-sm flex items-center gap-1 mb-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                Meus baralhos
            </a>
            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($deck['topic'] ?? 'Flashcards') ?></h1>
            <p class="text-sm text-gray-500"><?= $totalCards ?> cartão(ões) <?= $isRevisar ? '· Revisando não entendidos' : '' ?></p>
        </div>
    </div>

    <?php if (empty($cards)): ?>
        <div class="flex-1 flex items-center justify-center p-8">
            <div class="text-center">
                <p class="text-gray-500 mb-4">Nenhum cartão neste baralho.</p>
                <a href="<?= URL ?>/flashcards" class="text-indigo-600 hover:text-indigo-700 font-medium">Voltar aos baralhos</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Área central: um card por vez -->
        <div class="flex-1 flex items-center justify-center px-4 py-6 min-h-0">
            <div class="w-full max-w-2xl flex items-center justify-center gap-3">
                <button type="button" id="flashcard-prev" class="flashcard-nav flex-shrink-0 w-12 h-12 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center shadow-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Anterior">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>

                <div class="flex-1 min-w-0 flex justify-center perspective-1000">
                    <div id="flashcard-container" class="flashcard-3d w-full max-w-xl aspect-[4/3] max-h-[420px] cursor-pointer" style="perspective: 1000px;">
                        <div id="flashcard-inner" class="relative w-full h-full preserve-3d transition-transform duration-500 transform-style-3d">
                            <!-- Face pergunta -->
                            <div id="flashcard-front" class="flashcard-face absolute inset-0 rounded-2xl bg-gray-800 text-white shadow-xl flex flex-col items-center justify-center p-8 backface-hidden">
                                <p id="flashcard-question" class="text-lg md:text-xl text-center leading-relaxed"></p>
                                <button type="button" id="flashcard-ver-resposta" class="mt-6 text-indigo-300 hover:text-white underline text-sm font-medium transition-colors">Ver resposta</button>
                            </div>
                            <!-- Face resposta -->
                            <div id="flashcard-back" class="flashcard-face absolute inset-0 rounded-2xl bg-gray-700 text-white shadow-xl flex flex-col items-center justify-center p-8 backface-hidden transform-rotate-y-180">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Resposta</p>
                                <p id="flashcard-answer" class="text-base md:text-lg text-center leading-relaxed"></p>
                                <button type="button" id="flashcard-voltar-pergunta" class="mt-6 text-indigo-300 hover:text-white underline text-sm font-medium transition-colors">Voltar à pergunta</button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" id="flashcard-next" class="flashcard-nav flex-shrink-0 w-12 h-12 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center shadow-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Próximo">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </div>

        <!-- Entendi / Não entendi -->
        <div class="flex-shrink-0 px-4 py-4 flex flex-wrap items-center justify-center gap-4">
            <div class="flex items-center gap-2">
                <span id="count-nao-entendi" class="text-red-600 font-semibold text-sm">0</span>
                <button type="button" id="flashcard-nao-entendi" class="px-5 py-2.5 border-2 border-red-500 text-red-600 hover:bg-red-50 rounded-xl font-medium transition-colors">
                    Não entendi
                </button>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="flashcard-entendi" class="px-5 py-2.5 border-2 border-green-500 text-green-600 hover:bg-green-50 rounded-xl font-medium transition-colors">
                    Entendi
                </button>
                <span id="count-entendi" class="text-green-600 font-semibold text-sm">0</span>
            </div>
        </div>

        <!-- Timeline / Progresso -->
        <div class="flex-shrink-0 px-4 pb-6 pt-2">
            <div class="max-w-2xl mx-auto flex items-center gap-3">
                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div id="flashcard-progress-bar" class="h-full bg-indigo-600 rounded-full transition-all duration-300" style="width: <?= $totalCards ? (100 / $totalCards) : 0 ?>%;"></div>
                </div>
                <span id="flashcard-counter" class="text-sm font-medium text-gray-700 whitespace-nowrap">1 / <?= $totalCards ?> cartões</span>
                <a id="deck-explicar-tudinha-link" href="#" class="hidden text-amber-600 hover:text-amber-700 p-1.5 rounded-lg transition-colors inline-flex items-center gap-1" title="Explicar com a <?= htmlspecialchars($iaName) ?> os cartões que não entendi">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    <span class="text-xs font-medium">Explicar não entendidos</span>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($cards)): ?>
<style>
.perspective-1000 { perspective: 1000px; }
.preserve-3d { transform-style: preserve-3d; }
.flashcard-face { backface-visibility: hidden; -webkit-backface-visibility: hidden; }
.transform-rotate-y-180 { transform: rotateY(180deg); }
#flashcard-inner.flipped { transform: rotateY(180deg); }
</style>
<script>
(function() {
    var deckId = <?= $deckId ?>;
    var cards = <?= json_encode(array_values($cards)) ?>;
    var totalCards = cards.length;
    var isRevisar = <?= $isRevisar ? 'true' : 'false' ?>;

    var STORAGE_UNDERSTOOD = 'flashcard_deck_' + deckId + '_understood';
    var STORAGE_NOT_UNDERSTOOD = 'flashcard_deck_' + deckId + '_not_understood';

    function loadUnderstood() {
        try {
            var u = localStorage.getItem(STORAGE_UNDERSTOOD);
            var n = localStorage.getItem(STORAGE_NOT_UNDERSTOOD);
            return {
                understood: u ? JSON.parse(u) : [],
                notUnderstood: n ? JSON.parse(n) : []
            };
        } catch (e) { return { understood: [], notUnderstood: [] }; }
    }
    function saveUnderstood(data) {
        try {
            localStorage.setItem(STORAGE_UNDERSTOOD, JSON.stringify(data.understood));
            localStorage.setItem(STORAGE_NOT_UNDERSTOOD, JSON.stringify(data.notUnderstood));
        } catch (e) {}
    }

    var currentIndex = 0;
    var inner = document.getElementById('flashcard-inner');
    var questionEl = document.getElementById('flashcard-question');
    var answerEl = document.getElementById('flashcard-answer');
    var prevBtn = document.getElementById('flashcard-prev');
    var nextBtn = document.getElementById('flashcard-next');
    var progressBar = document.getElementById('flashcard-progress-bar');
    var counterEl = document.getElementById('flashcard-counter');
    var countNaoEntendi = document.getElementById('count-nao-entendi');
    var countEntendi = document.getElementById('count-entendi');

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function getCard() { return cards[currentIndex] || null; }
    function isFlipped() { return inner && inner.classList.contains('flipped'); }
    function setFlipped(flip) {
        if (!inner) return;
        if (flip) inner.classList.add('flipped'); else inner.classList.remove('flipped');
    }
    function renderCard() {
        var card = getCard();
        if (!card) return;
        questionEl.innerHTML = escapeHtml(card.question || '').replace(/\n/g, '<br>');
        answerEl.innerHTML = escapeHtml(card.answer || '').replace(/\n/g, '<br>');
        setFlipped(false);
        updateProgress();
        updateCounts();
        prevBtn.disabled = currentIndex === 0;
        nextBtn.disabled = currentIndex >= totalCards - 1;
    }
    function updateProgress() {
        var pct = totalCards ? ((currentIndex + 1) / totalCards) * 100 : 0;
        if (progressBar) progressBar.style.width = pct + '%';
        if (counterEl) counterEl.textContent = (currentIndex + 1) + ' / ' + totalCards + ' cartões';
    }
    function updateCounts() {
        var data = loadUnderstood();
        var n = data.notUnderstood.length;
        var u = data.understood.length;
        if (countNaoEntendi) countNaoEntendi.textContent = n;
        if (countEntendi) countEntendi.textContent = u;
    }
    function markUnderstood(understood) {
        var card = getCard();
        if (!card) return;
        var data = loadUnderstood();
        var id = card.id;
        data.understood = data.understood.filter(function(x) { return x !== id; });
        data.notUnderstood = data.notUnderstood.filter(function(x) { return x !== id; });
        if (understood) data.understood.push(id); else data.notUnderstood.push(id);
        saveUnderstood(data);
        updateCounts();
    }

    if (prevBtn) prevBtn.addEventListener('click', function() {
        if (currentIndex <= 0) return;
        currentIndex--;
        renderCard();
    });
    if (nextBtn) nextBtn.addEventListener('click', function() {
        if (currentIndex >= totalCards - 1) return;
        currentIndex++;
        renderCard();
    });
    document.getElementById('flashcard-ver-resposta').addEventListener('click', function(e) { e.stopPropagation(); setFlipped(true); });
    document.getElementById('flashcard-voltar-pergunta').addEventListener('click', function(e) { e.stopPropagation(); setFlipped(false); });
    document.getElementById('flashcard-container').addEventListener('click', function(e) {
        if (e.target.closest('button')) return;
        setFlipped(!isFlipped());
    });
    document.getElementById('flashcard-nao-entendi').addEventListener('click', function() { markUnderstood(false); updateExplicarLink(); });
    document.getElementById('flashcard-entendi').addEventListener('click', function() { markUnderstood(true); updateExplicarLink(); });

    function updateExplicarLink() {
        var data = loadUnderstood();
        var ids = data.notUnderstood;
        var el = document.getElementById('deck-explicar-tudinha-link');
        if (!el) return;
        if (ids.length > 0) {
            el.classList.remove('hidden');
            el.href = '<?= URL ?>/flashcards/explicar?deck_id=' + deckId + '&card_ids=' + ids.join(',');
        } else {
            el.classList.add('hidden');
            el.href = '#';
        }
    }
    updateExplicarLink();

    if (isRevisar && totalCards > 0) {
        var data = loadUnderstood();
        var ids = data.notUnderstood;
        if (ids.length > 0) {
            cards = cards.filter(function(c) { return ids.indexOf(c.id) !== -1; });
            totalCards = cards.length;
            currentIndex = 0;
        }
    }

    updateCounts();
    if (totalCards === 0) {
        var container = document.getElementById('flashcard-container');
        if (container && container.parentElement) {
            container.parentElement.innerHTML = '<div class="text-center py-8"><p class="text-gray-500 mb-2">Nenhum cartão para revisar.</p><a href="<?= URL ?>/flashcards/deck/<?= $deckId ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Ver todos os cartões</a></div>';
        }
    } else {
        renderCard();
    }
})();
</script>
<?php endif; ?>
