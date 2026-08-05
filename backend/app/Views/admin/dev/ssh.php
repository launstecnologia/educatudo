<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                SSH e Git 🔐
            </h2>
            <p class="text-gray-600">
                Execute comandos SSH e Git no servidor (apenas demo.educatudo.com)
            </p>
        </div>
    </div>
</div>

<?php 
// Verificar se extensão SSH2 está disponível
$ssh2Available = function_exists('ssh2_connect');
?>

<?php if (!$ssh2Available): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
        <div class="flex items-center">
            <svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <h3 class="text-lg font-semibold text-red-800">Extensão SSH2 não disponível</h3>
                <p class="text-red-700 mt-1">
                    A extensão PHP SSH2 não está instalada. Para instalar, execute: <code class="bg-red-100 px-2 py-1 rounded">sudo apt-get install php-ssh2</code> ou <code class="bg-red-100 px-2 py-1 rounded">pecl install ssh2</code>
                </p>
            </div>
        </div>
    </div>
<?php elseif (empty($escola) || empty($escola['ssh_host'])): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
        <div class="flex items-center">
            <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <h3 class="text-lg font-semibold text-yellow-800">Configuração SSH não encontrada</h3>
                <p class="text-yellow-700 mt-1">
                    Configure as credenciais SSH na página de <a href="<?= URL ?>/admin/dev/migrations" class="underline font-medium">Gerenciamento de Migrations</a>.
                </p>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Informações da Conexão -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Conexão SSH</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Escola</p>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($escola['escola_nome']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Host</p>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($escola['ssh_host']) ?>:<?= $escola['ssh_port'] ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Usuário</p>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($escola['ssh_user']) ?></p>
                </div>
                <?php if (!empty($escola['ssh_path'])): ?>
                <div class="md:col-span-3">
                    <p class="text-sm text-gray-600">Caminho do Projeto</p>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($escola['ssh_path']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Comandos Rápidos -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Comandos Rápidos</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <button onclick="executarComandoRapido('git pull')" 
                        class="p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors text-left">
                    <div class="flex items-center mb-2">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span class="font-semibold text-blue-900">Git Pull</span>
                    </div>
                    <p class="text-sm text-blue-700">Atualizar código do repositório</p>
                </button>
                
                <button onclick="executarComandoRapido('git status')" 
                        class="p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors text-left">
                    <div class="flex items-center mb-2">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-semibold text-green-900">Git Status</span>
                    </div>
                    <p class="text-sm text-green-700">Verificar status do repositório</p>
                </button>
                
                <button onclick="executarComandoRapido('git log --oneline -10')" 
                        class="p-4 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors text-left">
                    <div class="flex items-center mb-2">
                        <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="font-semibold text-purple-900">Git Log</span>
                    </div>
                    <p class="text-sm text-purple-700">Ver últimas 10 commits</p>
                </button>
                
                <button onclick="executarComandoRapido('git branch')" 
                        class="p-4 bg-orange-50 border border-orange-200 rounded-lg hover:bg-orange-100 transition-colors text-left">
                    <div class="flex items-center mb-2">
                        <svg class="w-5 h-5 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="font-semibold text-orange-900">Git Branch</span>
                    </div>
                    <p class="text-sm text-orange-700">Listar branches</p>
                </button>
            </div>
        </div>
    </div>

    <!-- Terminal de Comandos -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Terminal</h3>
            <p class="text-sm text-gray-600 mt-1">Execute comandos Git ou outros comandos permitidos</p>
        </div>
        <div class="p-6">
            <form id="formSSH" class="space-y-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="escola_id" value="<?= $escola['id'] ?>">
                
                <div>
                    <label for="comando" class="block text-sm font-medium text-gray-700 mb-2">
                        Comando
                    </label>
                    <div class="flex space-x-2">
                        <input type="text" id="comando" name="comando" required
                               placeholder="git pull, git status, ls, pwd, etc..."
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm">
                        <button type="submit" 
                                class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                            Executar
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Comandos permitidos: git (pull, status, log, branch, fetch, diff), ls, pwd, whoami, php -v, composer --version
                    </p>
                </div>
            </form>
            
            <!-- Resultado -->
            <div id="resultado" class="mt-6 hidden">
                <div class="bg-gray-900 rounded-lg p-4 font-mono text-sm text-green-400 overflow-x-auto">
                    <div class="mb-2 text-gray-400">
                        <span class="text-yellow-400">$</span> <span id="comando-executado"></span>
                    </div>
                    <div id="output" class="whitespace-pre-wrap"></div>
                    <div id="error" class="text-red-400 mt-2 hidden"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function executarComandoRapido(comando) {
    document.getElementById('comando').value = comando;
    document.getElementById('formSSH').dispatchEvent(new Event('submit'));
}

document.getElementById('formSSH').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const resultadoDiv = document.getElementById('resultado');
    const outputDiv = document.getElementById('output');
    const errorDiv = document.getElementById('error');
    const comandoExecutado = document.getElementById('comando-executado');
    
    resultadoDiv.classList.remove('hidden');
    outputDiv.textContent = 'Executando...';
    errorDiv.classList.add('hidden');
    comandoExecutado.textContent = formData.get('comando');
    
    fetch('<?= URL ?>/admin/dev/ssh/executar', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            outputDiv.textContent = data.output || '(sem saída)';
            if (data.error) {
                errorDiv.textContent = 'Erro: ' + data.error;
                errorDiv.classList.remove('hidden');
            } else {
                errorDiv.classList.add('hidden');
            }
        } else {
            outputDiv.textContent = '';
            errorDiv.textContent = 'Erro: ' + (data.error || 'Erro desconhecido');
            errorDiv.classList.remove('hidden');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        outputDiv.textContent = '';
        errorDiv.textContent = 'Erro de conexão: ' + error.message;
        errorDiv.classList.remove('hidden');
    });
});
</script>

