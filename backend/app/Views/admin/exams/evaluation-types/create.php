<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Novo Tipo de Avaliação</h2>
            <p class="text-gray-600">Exemplos: Prova Semanal, Prova Bimestral, Simulado.</p>
        </div>
        <a href="<?= URL ?>/admin/provas/tipos-avaliacao" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" action="<?= URL ?>/admin/provas/tipos-avaliacao">
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Nome <span class="text-red-500">*</span></label>
            <input type="text" name="nome" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Ex: Prova Bimestral">
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
            <textarea name="descricao" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Descrição opcional"></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Ordem</label>
                <input type="number" name="ordem" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div class="flex items-center pt-8">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" checked class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <span class="ml-2 text-sm text-gray-700">Ativo</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/provas/tipos-avaliacao" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg hover:opacity-90">Salvar</button>
        </div>
    </form>
</div>

