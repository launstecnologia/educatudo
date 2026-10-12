<?php
$reports_filter_tab = 'redacao';
$reports_filter_jornadas_extended = false;
require __DIR__ . '/_reports_filters_form.php';
?>
<!-- Estatísticas de Redações -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-red-50 rounded-lg p-4">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Total de Redações</p>
                <p class="text-3xl font-bold text-red-600"><?= number_format($essays_stats['total_redacoes']) ?></p>
            </div>
            <div class="bg-red-100 rounded-full p-3">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="bg-yellow-50 rounded-lg p-4">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Corrigidas</p>
                <p class="text-3xl font-bold text-yellow-600"><?= number_format($essays_stats['redacoes_corrigidas']) ?></p>
            </div>
            <div class="bg-yellow-100 rounded-full p-3">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="bg-indigo-50 rounded-lg p-4">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Média de Notas</p>
                <p class="text-3xl font-bold text-indigo-600"><?= number_format($essays_stats['media_notas'] ?? 0, 1) ?></p>
            </div>
            <div class="bg-indigo-100 rounded-full p-3">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Redações com Correção -->
<?php if (!empty($redacoes_com_correcao)): ?>
<div class="space-y-4">
    <?php foreach ($redacoes_com_correcao as $redacao): ?>
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="p-4 bg-gray-50 border-b border-gray-200 cursor-pointer" onclick="toggleRedacao(<?= $redacao['id'] ?>)">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($redacao['aluno_nome']) ?> - RA: <?= htmlspecialchars($redacao['ra']) ?></h4>
                    <p class="text-sm text-gray-600">Turma: <?= htmlspecialchars($redacao['turma_nome'] ?? 'Sem turma') ?> | 
                        Data: <?= date('d/m/Y H:i', strtotime($redacao['created_at'])) ?>
                        <?php if ($redacao['tema']): ?>
                            | Tema: <?= htmlspecialchars($redacao['tema']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="px-3 py-1 rounded-full text-sm font-medium <?= $redacao['status_descricao'] === 'Corrigida' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                        <?= $redacao['status_descricao'] ?>
                    </span>
                    <?php if ($redacao['nota'] || $redacao['nota_final']): ?>
                        <span class="text-lg font-bold text-indigo-600">
                            Nota: <?= number_format($redacao['nota_final'] ?? $redacao['nota'], 1) ?>
                        </span>
                    <?php endif; ?>
                    <svg id="arrow-<?= $redacao['id'] ?>" class="w-5 h-5 text-gray-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div id="content-<?= $redacao['id'] ?>" class="hidden">
            <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Redação (col-8 equivalente = 2/3) -->
                <div class="lg:col-span-2">
                    <h5 class="font-semibold text-gray-900 mb-3">Redação</h5>
                    <div class="bg-gray-50 rounded-lg p-4 whitespace-pre-wrap text-gray-800">
                        <?= htmlspecialchars($redacao['texto'] ?? '') ?>
                    </div>
                    <?php if ($redacao['texto']): ?>
                        <p class="text-sm text-gray-500 mt-2">
                            Palavras: <?= str_word_count($redacao['texto']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Correção (col-4 equivalente = 1/3) -->
                <div class="lg:col-span-1">
                    <?php if ($redacao['status_descricao'] === 'Corrigida'): ?>
                        <h5 class="font-semibold text-gray-900 mb-3">Correção</h5>
                        
                        <?php if ($redacao['correcao']): ?>
                            <div class="bg-blue-50 rounded-lg p-4 mb-4">
                                <h6 class="font-medium text-blue-900 mb-2">Correção Manual</h6>
                                <div class="text-sm text-gray-800 whitespace-pre-wrap">
                                    <?= htmlspecialchars($redacao['correcao']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($redacao['feedback_ia']): ?>
                            <div class="bg-purple-50 rounded-lg p-4 mb-4">
                                <h6 class="font-medium text-purple-900 mb-2">Feedback da IA</h6>
                                <div class="text-sm text-gray-800 whitespace-pre-wrap">
                                    <?= htmlspecialchars($redacao['feedback_ia']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($redacao['nota'] || $redacao['nota_final']): ?>
                            <div class="bg-indigo-50 rounded-lg p-4 mb-4">
                                <h6 class="font-medium text-indigo-900 mb-2">Nota</h6>
                                <p class="text-2xl font-bold text-indigo-600">
                                    <?= number_format($redacao['nota_final'] ?? $redacao['nota'], 1) ?>/100
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (($redacao['competencia1'] ?? null) || ($redacao['competencia2'] ?? null) || ($redacao['competencia3'] ?? null) || ($redacao['competencia4'] ?? null) || ($redacao['competencia5'] ?? null)): ?>
                            <div class="bg-green-50 rounded-lg p-4">
                                <h6 class="font-medium text-green-900 mb-2">Competências</h6>
                                <div class="space-y-2 text-sm">
                                    <?php if ($redacao['competencia1'] ?? null): ?>
                                        <p><strong>C1:</strong> <?= number_format($redacao['competencia1']) ?>/200</p>
                                    <?php endif; ?>
                                    <?php if ($redacao['competencia2'] ?? null): ?>
                                        <p><strong>C2:</strong> <?= number_format($redacao['competencia2']) ?>/200</p>
                                    <?php endif; ?>
                                    <?php if ($redacao['competencia3'] ?? null): ?>
                                        <p><strong>C3:</strong> <?= number_format($redacao['competencia3']) ?>/200</p>
                                    <?php endif; ?>
                                    <?php if ($redacao['competencia4'] ?? null): ?>
                                        <p><strong>C4:</strong> <?= number_format($redacao['competencia4']) ?>/200</p>
                                    <?php endif; ?>
                                    <?php if ($redacao['competencia5'] ?? null): ?>
                                        <p><strong>C5:</strong> <?= number_format($redacao['competencia5']) ?>/200</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-yellow-50 rounded-lg p-4">
                            <p class="text-sm text-yellow-800">Redação ainda não foi corrigida.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-12">
    <p class="text-gray-500">Nenhuma redação encontrada para os filtros selecionados.</p>
</div>
<?php endif; ?>

<script>
function toggleRedacao(id) {
    const content = document.getElementById('content-' + id);
    const arrow = document.getElementById('arrow-' + id);
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        arrow.classList.add('rotate-180');
    } else {
        content.classList.add('hidden');
        arrow.classList.remove('rotate-180');
    }
}
</script>

