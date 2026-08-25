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
    <title>Token Inválido - Portal do Professor - <?= htmlspecialchars($system_title) ?></title>
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

        <!-- Lado Direito - Mensagem de Erro -->
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

                <!-- Mensagem de Erro -->
                <div class="bg-white rounded-lg shadow-lg p-8 text-center">
                    <div class="mb-4">
                        <svg class="w-16 h-16 mx-auto text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Token Inválido ou Expirado</h2>
                    <p class="text-gray-600 mb-6"><?= htmlspecialchars($error ?? 'O link de recuperação é inválido ou expirou. Solicite uma nova recuperação de senha.') ?></p>
                    <a href="<?= URL ?>/professor/recuperar-senha" 
                       class="inline-block bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200">
                        Solicitar Nova Recuperação
                    </a>
                </div>

                <!-- Links -->
                <div class="mt-6 text-center">
                    <a href="<?= URL ?>/professor" class="text-sm text-blue-600 hover:text-blue-500">
                        ← Voltar para login
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

