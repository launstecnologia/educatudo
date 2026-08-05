<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($nomeEscola ?? 'EducaTudo') ?> — Matrícula</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

<header class="bg-white border-b border-gray-200 py-4 px-6 flex items-center gap-3">
    <i class="fa-solid fa-graduation-cap text-blue-600 text-2xl"></i>
    <span class="font-bold text-gray-800 text-lg"><?= htmlspecialchars($nomeEscola ?? 'EducaTudo') ?></span>
</header>

<main class="flex-1 flex items-start justify-center py-10 px-4">
    <div class="w-full max-w-2xl">
        <?php if (isset($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-8 text-center">
            <i class="fa-solid fa-circle-exclamation text-4xl mb-4 block"></i>
            <p class="font-medium text-lg"><?= htmlspecialchars($error) ?></p>
            <p class="text-sm mt-2 text-red-500">Em caso de dúvidas, entre em contato com a secretaria.</p>
        </div>
        <?php else: ?>
        <?php
        $viewPath = isset($view) ? __DIR__ . '/../' . $view . '.php' : null;
        // o conteúdo já foi incluído pelo controller via extract + include
        // então o conteúdo da view específica estará disponível aqui:
        if (isset($enrollment)) {
            // renderizar a view correta baseado no que está no contexto
            if (isset($assinado) || ($enrollment['status'] ?? '') === 'confirmada') {
                include __DIR__ . '/../public/enrollment/contrato_assinado.php';
            } elseif (!isset($error)) {
                include __DIR__ . '/../public/enrollment/contrato.php';
            }
        }
        ?>
        <?php endif; ?>
    </div>
</main>

<footer class="py-4 text-center text-xs text-gray-400 border-t border-gray-100">
    EducaTudo — Sistema de Gestão Escolar
</footer>

</body>
</html>
