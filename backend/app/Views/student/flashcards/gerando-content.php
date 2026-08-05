<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-lg p-10">

        <div id="estado-gerando">
            <?php
            $aiLoadingVariant = 'inline';
            $aiLoadingId = 'flashcardLoading';
            $aiLoadingTitle = 'Gerando seus flashcards...';
            $aiLoadingMessage = 'A IA está criando as perguntas e respostas. Isso leva alguns segundos.';
            $aiLoadingStatus = 'Gerando com IA...';
            $aiLoadingClosable = false;
            include __DIR__ . '/../../components/ai-loading.php';
            ?>
        </div>

        <div id="estado-erro" class="hidden text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Falha ao gerar flashcards</h1>
            <p id="msg-erro" class="text-red-600 text-sm mb-6"></p>
            <a href="<?= URL ?>/flashcards/criar"
               class="btn-ai-primary inline-block px-6 py-3 rounded-lg transition-colors">
                Tentar novamente
            </a>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../components/ai-job-poller.php'; ?>
<script>
EducaAiLoading.show({
    id: 'flashcardLoading',
    fakeProgress: true
});

new AIJobPoller(<?= (int) $job_id ?>, {
    onDone: function(result) {
        EducaAiLoading.stopFakeProgress();
        EducaAiLoading.setProgress(100, 'flashcardLoading');
        EducaAiLoading.setStatus('Redirecionando...', 'flashcardLoading');
        if (result && result.deck_id) {
            window.location.href = '<?= URL ?>/flashcards/deck/' + result.deck_id;
        } else {
            window.location.href = '<?= URL ?>/flashcards';
        }
    },
    onFailed: function(err) {
        EducaAiLoading.hide('flashcardLoading');
        document.getElementById('estado-gerando').classList.add('hidden');
        document.getElementById('estado-erro').classList.remove('hidden');
        document.getElementById('msg-erro').textContent = err;
    }
});
</script>
