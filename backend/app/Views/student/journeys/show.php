<?php if (!empty($preview)): ?>
<div class="mb-4 p-3 bg-amber-100 border border-amber-300 rounded-lg text-amber-800 text-sm flex items-center gap-2">
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
    <span><strong>Preview:</strong> você está vendo esta jornada como o aluno vê. Nenhum progresso é salvo.</span>
</div>
<?php endif; ?>
<?php
require_once __DIR__ . '/../../../Core/TenantRelease.php';
$ocultarTituloJornada = TenantRelease::shouldUse('jornadas_ocultar_titulo_v1', false);
?>
<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <a href="<?= !empty($preview) ? URL . '/professor/jornadas/' . (int)$jornada['id'] : URL . '/jornadas' ?>" class="mr-4 p-2 text-gray-400 hover:text-gray-600 transition-colors" title="<?= !empty($preview) ? 'Voltar ao painel da jornada' : 'Voltar' ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <?php if (!$ocultarTituloJornada): ?>
                <div class="flex items-center mb-2">
                    <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($jornada['titulo']) ?></h1>
                </div>
                <?php endif; ?>
                <p class="text-gray-600"><?= htmlspecialchars($jornada['descricao'] ?: 'Sem descrição') ?></p>
                <p class="text-sm text-gray-500 mt-1">Prof. <?= htmlspecialchars($jornada['professor_nome']) ?> • <?= htmlspecialchars($jornada['materia_nome'] ?? 'Matéria não especificada') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Tab Content -->
<div class="tab-content">
    <!-- Etapas Tab -->
    <div id="etapas-tab" class="tab-panel">
        <?php include 'etapas.php'; ?>
    </div>
</div>
