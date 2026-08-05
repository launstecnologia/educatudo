<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Token Inválido - EducaTudo' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 text-center">
        <div class="mb-6">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Token Inválido</h1>
            <p class="text-gray-600 mb-6"><?= htmlspecialchars($error ?? 'Token inválido ou expirado.') ?></p>
        </div>
        
        <div class="space-y-4">
            <a href="<?= URL ?>/recuperar-senha" 
               class="block w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                Solicitar Nova Recuperação
            </a>
            <a href="<?= URL ?>/admin" 
               class="block w-full text-purple-600 hover:text-purple-800 text-sm">
                ← Voltar para login
            </a>
        </div>
    </div>
</body>
</html>
