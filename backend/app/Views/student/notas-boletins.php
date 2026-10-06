<?php
$aluno = $aluno ?? [];
?>

<div class="max-w-7xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Provas</h1>
        <p class="text-gray-600">
            Acompanhe as provas realizadas de <?= htmlspecialchars((string) ($aluno['nome'] ?? 'o aluno'), ENT_QUOTES, 'UTF-8') ?>.
        </p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <?php require __DIR__ . '/../partials/provas_matriz_blocos.php'; ?>
    </div>

    <div class="mt-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-1">Notas</h2>
        <p class="text-gray-600 mb-4">O mesmo demonstrativo visto pela coordenação. O boletim fica só com a coordenação.</p>
        <?php require __DIR__ . '/../partials/notas_portal_coordenacao.php'; ?>
    </div>
</div>
