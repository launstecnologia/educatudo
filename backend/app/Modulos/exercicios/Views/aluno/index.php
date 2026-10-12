<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">Exercícios com IA</h1>
        <p class="text-lg text-gray-600">Crie listas personalizadas com a Tudinha</p>
    </div>

    <!-- Cards de Opções (sempre visíveis) -->
    <div class="grid grid-cols-1 gap-6 mb-12">
        <!-- Card Criar com Tudinha -->
        <a href="<?= URL ?>/exercicios-personalizados/minhas-listas" class="group">
            <div class="bg-white rounded-2xl shadow-xl p-10 hover:shadow-2xl transition-all duration-300 transform hover:scale-105 border-2 border-purple-300 hover:border-purple-500 h-full">
                <div class="text-center">
                    <div class="mx-auto w-20 h-20 bg-gradient-to-br from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center mb-6">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">+ Criar Exercícios com a Tudinha</h3>
                    <p class="text-gray-600 text-lg mb-4">Gere exercícios personalizados sobre qualquer tema usando Inteligência Artificial</p>
                    <span class="inline-flex items-center text-purple-600 font-semibold group-hover:text-purple-800">
                        Começar agora
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </span>
                </div>
            </div>
        </a>

    </div>

    <!-- Seção de Como Funciona -->
    <div class="grid grid-cols-1 gap-6 mb-8">
        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-2xl p-6 border border-purple-200">
            <h3 class="text-xl font-bold text-gray-900 mb-3">📚 Com a Tudinha</h3>
            <ul class="space-y-2 text-gray-700">
                <li>✓ Escolha o tema que deseja estudar</li>
                <li>✓ A IA cria exercícios personalizados</li>
                <li>✓ Quantos exercícios quiser (3 a 30)</li>
                <li>✓ Diferentes níveis de dificuldade</li>
            </ul>
        </div>
    </div>
</div>

<script>
function iniciarExercicio(listaId) {
    if (!confirm('Deseja iniciar esta lista de exercícios?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token) ?>);
    formData.append('lista_id', listaId);
    
    fetch('<?= URL ?>/exercicios-personalizados/iniciar', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?= URL ?>/exercicios-personalizados/executar?sessao_id=' + data.sessao_id;
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível iniciar os exercícios'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao iniciar exercícios');
    });
}
</script>
