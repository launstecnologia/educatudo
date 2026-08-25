<?php
require_once __DIR__ . '/../../Core/LayoutHelper.php';

// Buscar configurações de layout
$logo_url = LayoutHelper::getLogoUrl();
$logo_horizontal_url = LayoutHelper::getLogoHorizontalUrl();
$login_cover_url = LayoutHelper::getLoginCoverUrl();
$system_title = LayoutHelper::getSystemTitle();
$system_subtitle = LayoutHelper::getSystemSubtitle();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Portal do Professor - <?= htmlspecialchars($system_title) ?></title>
    <?php include __DIR__ . '/../layouts/components/estilos_plataforma.php'; ?>
</head>
<body class="min-h-screen flex flex-col">
    <div class="flex flex-1">
        <!-- Lado Esquerdo - Imagem de Fundo -->
        <div class="flex-1 relative bg-cover bg-center bg-no-repeat" 
             style="background-image: url('<?= !empty($login_cover_url) ? htmlspecialchars($login_cover_url) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iIzM3NDE1MSIvPjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkNhcGE8L3RleHQ+PC9zdmc+' ?>');">
            
            <!-- Overlay azul -->
            <div class="absolute inset-0 bg-blue-900 bg-opacity-60"></div>
            
        </div>

        <!-- Lado Direito - Formulário de Recuperação -->
        <div class="flex-1 bg-gray-50 flex flex-col items-center justify-center p-8">
            <div class="w-full max-w-md flex flex-col">
                <!-- Logo pequena no topo -->
                <div class="text-center mb-8">
                    <?php if (!empty($logo_horizontal_url)): ?>
                        <img src="<?= htmlspecialchars($logo_horizontal_url) ?>" alt="Logo" class="h-16 mx-auto mb-4">
                    <?php else: ?>
                        <div class="w-16 h-16 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <span class="text-white font-bold text-xl"><?= substr($system_title, 0, 1) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Título -->
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">Recuperar Senha</h2>
                    <p class="text-gray-600">Digite seu e-mail para receber instruções de recuperação</p>
                </div>

                <!-- Formulário -->
                <form id="recoveryForm" class="space-y-6">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <!-- Email do Professor -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            E-mail
                        </label>
                        <input id="email" name="email" type="email" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               placeholder="seu-email@escola.com">
                    </div>

                    <!-- Mensagem -->
                    <div id="message" class="hidden"></div>

                    <!-- Botão Enviar -->
                    <div>
                        <button type="submit" 
                                class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Enviar Instruções
                        </button>
                    </div>
                </form>

                <!-- Links -->
                <div class="mt-6 text-center space-y-2">
                    <a href="<?= URL ?>/professor" class="text-sm text-blue-600 hover:text-blue-500">
                        ← Voltar para login
                    </a>
                </div>
                
                <!-- Footer -->
                <footer class="mt-8 pt-4 border-t border-gray-200">
                    <div class="text-center">
                        <p class="text-xs text-gray-600 mb-1">Todos os direitos reservados Educatudo</p>
                        <p class="text-xs text-gray-600">
                            Feito com carinho por <a href="https://www.launs.com.br" target="_blank" rel="noopener noreferrer" class="text-gray-600 hover:text-gray-800 underline">Launs</a>
                        </p>
                        <p class="text-xs text-gray-600 mt-2">
                            <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a> •
                            <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a> •
                            <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
                        </p>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('recoveryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageDiv = document.getElementById('message');
            const submitBtn = this.querySelector('button[type="submit"]');
            
            // Desabilita botão
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';
            
            messageDiv.classList.remove('hidden');
            messageDiv.className = 'p-4 rounded-lg';
            
            fetch('<?= URL ?>/professor/enviar-recuperacao', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    messageDiv.className = 'p-4 rounded-lg bg-green-100 text-green-800 border border-green-400';
                    messageDiv.textContent = data.message;
                    this.reset();
                    
                    // Redireciona após 3 segundos
                    setTimeout(() => {
                        window.location.href = '<?= URL ?>/professor';
                    }, 3000);
                } else if (data.error) {
                    messageDiv.className = 'p-4 rounded-lg bg-red-100 text-red-800 border border-red-400';
                    messageDiv.textContent = data.error;
                }
            })
            .catch(error => {
                messageDiv.className = 'p-4 rounded-lg bg-red-100 text-red-800 border border-red-400';
                messageDiv.textContent = 'Erro ao processar solicitação. Tente novamente.';
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enviar Instruções';
            });
        });
    </script>
</body>
</html>

