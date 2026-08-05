<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Cadastrar Nova Matéria 📚
            </h2>
            <p class="text-gray-600">
                Preencha os dados para cadastrar uma nova matéria
            </p>
        </div>
        <a href="<?= URL ?>/admin/subjects" 
           class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>
</div>

<!-- Form Section -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Dados da Matéria</h3>
    </div>
    
    <form id="subjectForm" class="p-6 space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <!-- Nome da Matéria -->
        <div>
            <label for="nome" class="block text-sm font-semibold text-gray-700 mb-2">
                Nome da Matéria <span class="text-red-500">*</span>
            </label>
            <input type="text" id="nome" name="nome" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                   placeholder="Ex: Matemática, Português, História...">
            <p class="mt-1 text-sm text-gray-500">Digite o nome completo da matéria</p>
        </div>

        <!-- Error Message -->
        <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        </div>

        <!-- Success Message -->
        <div id="successMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/subjects" 
               class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                <span id="submitText">Cadastrar Matéria</span>
                <span id="loadingText" class="hidden">Cadastrando...</span>
            </button>
        </div>
    </form>
</div>

<script>
    document.getElementById('subjectForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const errorDiv = document.getElementById('errorMessage');
        const successDiv = document.getElementById('successMessage');
        const submitBtn = this.querySelector('button[type="submit"]');
        const submitText = document.getElementById('submitText');
        const loadingText = document.getElementById('loadingText');
        
        // Limpa mensagens anteriores
        errorDiv.classList.add('hidden');
        successDiv.classList.add('hidden');
        
        // Mostra loading
        submitBtn.disabled = true;
        submitText.classList.add('hidden');
        loadingText.classList.remove('hidden');
        
        try {
            const response = await fetch('<?= URL ?>/admin/subjects', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (response.ok) {
                successDiv.textContent = result.message;
                successDiv.classList.remove('hidden');
                
                // Redireciona após 2 segundos
                setTimeout(() => {
                    window.location.href = '<?= URL ?>/admin/subjects';
                }, 2000);
            } else {
                errorDiv.textContent = result.error || 'Erro no cadastro';
                errorDiv.classList.remove('hidden');
            }
        } catch (error) {
            errorDiv.textContent = 'Erro de conexão. Tente novamente.';
            errorDiv.classList.remove('hidden');
        } finally {
            // Remove loading
            submitBtn.disabled = false;
            submitText.classList.remove('hidden');
            loadingText.classList.add('hidden');
        }
    });
</script>
