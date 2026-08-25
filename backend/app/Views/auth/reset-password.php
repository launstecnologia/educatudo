<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Redefinir Senha - EducaTudo' ?></title>
    <?php include __DIR__ . '/../layouts/components/estilos_plataforma.php'; ?>
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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Redefinir Senha</h1>
            <p class="text-gray-600">Digite sua nova senha</p>
        </div>
        
        <form id="reset-form" method="POST" action="<?= URL ?>/recuperar-senha/reset">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            
            <div class="mb-6">
                <label for="senha" class="block text-sm font-medium text-gray-700 mb-2">
                    Nova Senha
                </label>
                <input type="password" id="senha" name="senha" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                       placeholder="Digite sua nova senha">
                <p class="mt-2 text-xs text-gray-500">
                    A senha deve conter: pelo menos 8 caracteres, 1 letra maiúscula, 1 minúscula, 1 número e 1 caractere especial (!@#$%^&*()_+-=[]{}|;:,.<>?)
                </p>
            </div>
            
            <div class="mb-6">
                <label for="confirmar_senha" class="block text-sm font-medium text-gray-700 mb-2">
                    Confirmar Nova Senha
                </label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                       placeholder="Confirme sua nova senha">
            </div>
            
            <div id="message" class="mb-4 hidden"></div>
            
            <button type="submit" 
                    class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                Redefinir Senha
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="<?= URL ?>/admin" class="text-purple-600 hover:text-purple-800 text-sm">
                ← Voltar para login
            </a>
        </div>
    </div>
    
    <script>
        document.getElementById('reset-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const senha = document.getElementById('senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            if (senha !== confirmarSenha) {
                const messageDiv = document.getElementById('message');
                messageDiv.classList.remove('hidden');
                messageDiv.className = 'mb-4 p-4 rounded-lg bg-red-100 text-red-800';
                messageDiv.textContent = 'As senhas não coincidem';
                return;
            }
            
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
                if (data.success) {
                    messageDiv.className = 'mb-4 p-4 rounded-lg bg-green-100 text-green-800';
                    messageDiv.textContent = data.message || 'Senha redefinida com sucesso!';
                    setTimeout(() => {
                        window.location.href = '<?= URL ?>/admin';
                    }, 2000);
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
