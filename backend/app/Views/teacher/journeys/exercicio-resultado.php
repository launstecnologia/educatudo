<?php
/**
 * View para exibir resultado dos exercícios gerados pela IA
 */
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Exercícios Gerados pela IA</h1>
                    <p class="text-gray-600 mt-1">Jornada: <?= htmlspecialchars($jornada['titulo']) ?></p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="window.location.href='<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>'" 
                            class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        ← Voltar para Jornada
                    </button>
                </div>
            </div>
        </div>

        <!-- Status do Exercício -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <?php if ($exercicio['status'] === 'rascunho'): ?>
                        <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">
                            📝 Rascunho
                        </span>
                    <?php elseif ($exercicio['status'] === 'aprovado'): ?>
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                            ✅ Aprovado
                        </span>
                    <?php elseif ($exercicio['status'] === 'publicado'): ?>
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                            🌐 Publicado
                        </span>
                    <?php endif; ?>
                    
                    <span class="text-gray-600">
                        Gerado em <?= date('d/m/Y H:i', strtotime($exercicio['created_at'])) ?>
                    </span>
                </div>
                
                <?php if ($exercicio['status'] === 'rascunho'): ?>
                    <div class="flex space-x-2">
                        <button onclick="aprovarExercicio()" 
                                class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors">
                            ✅ Aprovar
                        </button>
                        <button onclick="rejeitarExercicio()" 
                                class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors">
                            ❌ Rejeitar
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Preview dos Exercícios -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Preview dos Exercícios</h2>
            
            <?php if (isset($exercicios['titulo'])): ?>
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900"><?= htmlspecialchars($exercicios['titulo']) ?></h3>
                    <?php if (isset($exercicios['descricao'])): ?>
                        <p class="text-gray-600 mt-2"><?= htmlspecialchars($exercicios['descricao']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($exercicios['questoes']) && is_array($exercicios['questoes'])): ?>
                <div class="space-y-6">
                    <?php foreach ($exercicios['questoes'] as $index => $questao): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Questão <?= $index + 1 ?></h4>
                                <div class="flex space-x-2">
                                    <?php if (isset($questao['dificuldade'])): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                            <?= ucfirst($questao['dificuldade']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (isset($questao['tempo_estimado'])): ?>
                                        <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs">
                                            ⏱️ <?= $questao['tempo_estimado'] ?>min
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-gray-800"><?= htmlspecialchars($questao['enunciado']) ?></p>
                            </div>
                            
                            <?php if (isset($questao['alternativas']) && is_array($questao['alternativas'])): ?>
                                <div class="space-y-2 mb-4">
                                    <?php foreach ($questao['alternativas'] as $letra => $alternativa): ?>
                                        <div class="flex items-center space-x-2">
                                            <span class="font-medium text-gray-600 w-6"><?= strtoupper($letra) ?>)</span>
                                            <span class="text-gray-800"><?= htmlspecialchars($alternativa) ?></span>
                                            <?php if (isset($questao['resposta_correta']) && $letra === $questao['resposta_correta']): ?>
                                                <span class="text-green-600 font-medium">✓</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($questao['explicacao'])): ?>
                                <div class="bg-gray-50 rounded p-3">
                                    <p class="text-sm text-gray-700">
                                        <strong>Explicação:</strong> <?= htmlspecialchars($questao['explicacao']) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($exercicios['instrucoes'])): ?>
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-medium text-blue-900 mb-2">Instruções</h4>
                    <p class="text-blue-800"><?= htmlspecialchars($exercicios['instrucoes']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($exercicios['tempo_total'])): ?>
                <div class="mt-4 text-sm text-gray-600">
                    <strong>Tempo total estimado:</strong> <?= $exercicios['tempo_total'] ?> minutos
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function aprovarExercicio() {
    if (confirm('Tem certeza que deseja aprovar estes exercícios?')) {
        const formData = new FormData();
        formData.append('_token', <?= json_encode($csrf_token) ?>);
        formData.append('exercicio_id', '<?= $exercicio['id'] ?>');
        
        fetch('<?= URL ?>/professor/jornadas/aprovar-exercicio-ia', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Exercício aprovado com sucesso!');
                location.reload();
            } else {
                alert('Erro ao aprovar: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            alert('Erro de conexão: ' + error.message);
        });
    }
}

function rejeitarExercicio() {
    if (confirm('Tem certeza que deseja rejeitar estes exercícios? Esta ação não pode ser desfeita.')) {
        // Aqui você pode implementar a lógica para rejeitar/excluir o exercício
        alert('Funcionalidade de rejeição será implementada em breve.');
    }
}
</script>

