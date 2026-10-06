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

    <?php if (!empty($boletins_gerados_notas) || !empty($boletins_gerados_boletim)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mt-6 space-y-8">
            <?php if (!empty($boletins_gerados_notas)): ?>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Demonstrativo de Notas</h2>
                    <?php
                    $boletins_gerados = $boletins_gerados_notas;
                    $boletim_pode_excluir = false;
                    require __DIR__ . '/../partials/boletins_gerados.php';
                    ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($boletins_gerados_boletim)): ?>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Boletim</h2>
                    <?php
                    $boletins_gerados = $boletins_gerados_boletim;
                    $boletim_pode_excluir = false;
                    require __DIR__ . '/../partials/boletins_gerados.php';
                    ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
