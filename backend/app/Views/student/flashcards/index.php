<?php
$decks = $decks ?? [];
$iaName = LayoutHelper::getIaName();
?>
<div class="container mx-auto px-4 py-6 max-w-[1600px] w-full">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Meus Flashcards</h1>
            <p class="text-gray-600 mt-1">Histórico dos seus baralhos. Clique para estudar ou crie um novo.</p>
        </div>
        <a href="<?= URL ?>/flashcards/create" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Novo baralho
        </a>
    </div>

    <div id="revisar-nao-entendi-banner" class="hidden mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-amber-900">Cartões que você não entendeu</h2>
                <p class="text-sm text-amber-800 mt-0.5">Revise os cartões marcados como "Não entendi" ou peça ajuda à <?= htmlspecialchars($iaName) ?> para explicar.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a id="revisar-nao-entendi-link" href="#" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium text-sm transition-colors">Revisar cartões</a>
                <a id="explicar-tudinha-link" href="#" class="px-4 py-2 border border-amber-600 text-amber-700 hover:bg-amber-100 rounded-lg font-medium text-sm transition-colors inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    Explicar com <?= htmlspecialchars($iaName) ?>
                </a>
            </div>
        </div>
        <p id="revisar-nao-entendi-detail" class="text-xs text-amber-700 mt-2"></p>
    </div>

    <?php if (empty($decks)): ?>
        <div class="bg-white rounded-xl shadow border border-gray-200 p-12 text-center">
            <p class="text-gray-500 mb-4">Você ainda não tem baralhos.</p>
            <a href="<?= URL ?>/flashcards/create" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors">
                Criar primeiro baralho
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php foreach ($decks as $d): ?>
                <a href="<?= URL ?>/flashcards/deck/<?= (int) $d['id'] ?>" class="block bg-white rounded-xl shadow border border-gray-200 p-5 hover:border-indigo-300 hover:shadow-md transition-all">
                    <h2 class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($d['topic'] ?? 'Sem título') ?></h2>
                    <p class="text-sm text-gray-500 mt-1">Série: <?= htmlspecialchars($d['grade'] ?? '-') ?></p>
                    <p class="text-sm text-indigo-600 font-medium mt-2"><?= (int) ($d['cards_count'] ?? 0) ?> card(s)</p>
                    <p class="text-xs text-gray-400 mt-1"><?= !empty($d['created_at']) ? date('d/m/Y H:i', strtotime($d['created_at'])) : '' ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$deckListForJs = [];
foreach ($decks as $d) {
    $deckListForJs[] = ['id' => (int)($d['id'] ?? 0), 'topic' => $d['topic'] ?? ''];
}
?>
<?php if (!empty($decks)): ?>
<script>
(function() {
    var deckList = <?= json_encode($deckListForJs) ?>;
    var banner = document.getElementById('revisar-nao-entendi-banner');
    var link = document.getElementById('revisar-nao-entendi-link');
    var detail = document.getElementById('revisar-nao-entendi-detail');
    if (!banner || !link) return;
    var decksWithNotUnderstood = [];
    deckList.forEach(function(deck) {
        try {
            var key = 'flashcard_deck_' + deck.id + '_not_understood';
            var raw = localStorage.getItem(key);
            var arr = raw ? JSON.parse(raw) : [];
            if (arr.length > 0) decksWithNotUnderstood.push({ id: deck.id, topic: deck.topic, count: arr.length, cardIds: arr });
        } catch (e) {}
    });
    if (decksWithNotUnderstood.length > 0) {
        banner.classList.remove('hidden');
        var first = decksWithNotUnderstood[0];
        link.href = '<?= URL ?>/flashcards/deck/' + first.id + '?revisar=1';
        var explicarLink = document.getElementById('explicar-tudinha-link');
        if (explicarLink && first.cardIds && first.cardIds.length > 0) {
            explicarLink.href = '<?= URL ?>/flashcards/explicar?deck_id=' + first.id + '&card_ids=' + first.cardIds.join(',');
        }
        detail.textContent = decksWithNotUnderstood.length === 1
            ? 'Baralho "' + (first.topic || '') + '": ' + first.count + ' cartão(ões) para revisar.'
            : 'Você tem cartões para revisar em ' + decksWithNotUnderstood.length + ' baralho(s). O link abre o primeiro.';
    }
})();
</script>
<?php endif; ?>
