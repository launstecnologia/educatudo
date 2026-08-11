<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Recuperar Senha - EducaTudo' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Recuperar Senha</h1>
            <p class="text-gray-600">Digite seu e-mail para receber instruções de recuperação</p>
        </div>
        
        <form id="recovery-form" method="POST" action="<?= URL ?>/enviar-recuperacao">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="mb-6">
                <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">
                    Tipo de Usuário
                </label>
                <select id="tipo" name="tipo" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">Selecione...</option>
                    <option value="professor" <?= (isset($tipo_pre_selecionado) && $tipo_pre_selecionado === 'professor') ? 'selected' : '' ?>>Professor</option>
                    <option value="admin" <?= (isset($tipo_pre_selecionado) && $tipo_pre_selecionado === 'admin') ? 'selected' : '' ?>>Admin/Coordenador/Direção</option>
                </select>
            </div>
            
            <div class="mb-6">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    E-mail
                </label>
                <input type="email" id="email" name="email" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                       placeholder="seu-email@escola.com">
            </div>
            
            <div id="message" class="mb-4 hidden"></div>
            
            <button type="submit" 
                    class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                Enviar Instruções
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="<?= URL ?>/admin" class="text-purple-600 hover:text-purple-800 text-sm">
                ← Voltar para login
            </a>
        </div>
    </div>
    
    <script>
        document.getElementById('recovery-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageDiv = document.getElementById('message');
            messageDiv.classList.remove('hidden');
            messageDiv.className = 'mb-4 p-4 rounded-lg';
            
            fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    messageDiv.className = 'mb-4 p-4 rounded-lg bg-green-100 text-green-800';
                    messageDiv.textContent = data.message;
                    this.reset();
                } else if (data.error) {
                    messageDiv.className = 'mb-4 p-4 rounded-lg bg-red-100 text-red-800';
                    messageDiv.textContent = data.error;
                }
            })
            .catch(error => {
                messageDiv.className = 'mb-4 p-4 rounded-lg bg-red-100 text-red-800';
                messageDiv.textContent = 'Erro ao processar solicitação. Tente novamente.';
            });
        });
    </script>
</body>
</html>
