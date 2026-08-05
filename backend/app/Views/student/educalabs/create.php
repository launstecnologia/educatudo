<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Novo Projeto</h1>
            <p class="text-gray-600 mt-2">Crie um app com IA a partir de um objetivo.</p>
        </div>
        <a href="<?= URL ?>/educalabs" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
            Voltar
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 max-w-2xl">
    <form method="POST" action="<?= URL ?>/educalabs/salvar" class="space-y-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nome do projeto</label>
            <input type="text" id="name" name="name" required
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   placeholder="Ex: App de organização de estudos">
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
            <textarea id="description" name="description" rows="3"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Explique o que você quer criar..."></textarea>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold">
                Criar Projeto
            </button>
            <span class="text-sm text-gray-500">Você poderá conversar com a IA no próximo passo.</span>
        </div>
    </form>
</div>

