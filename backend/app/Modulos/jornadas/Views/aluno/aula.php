<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <a href="<?= URL ?>/jornadas/<?= $aula['jornada_id'] ?>" class="mr-4 p-2 text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($aula['nome_aula']) ?></h1>
                <p class="text-gray-600 mt-2"><?= htmlspecialchars($aula['jornada_titulo']) ?></p>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right">
                <p class="text-sm text-gray-500">Aula</p>
                <p class="text-lg font-semibold text-blue-600"><?= $aula['ordem'] ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Conteúdo da Aula -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Resumo Oficial -->
        <?php if ($aula['resumo_oficial']): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">📚 Resumo da Aula</h2>
                <div class="prose max-w-none">
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($aula['resumo_oficial'])) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Pontos Principais -->
        <?php if ($aula['pontos_principais']): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">🎯 Pontos Principais</h2>
                <div class="prose max-w-none">
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($aula['pontos_principais'])) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Conteúdos Adicionais -->
        <?php if ($aula['conteudos_adicionais']): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">📖 Conteúdos Adicionais</h2>
                <div class="prose max-w-none">
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($aula['conteudos_adicionais'])) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Exercícios da Aula -->
        <?php if (!empty($exercicios_aula)): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">💪 Exercícios</h2>
                <div class="space-y-4">
                    <?php foreach ($exercicios_aula as $exercicio): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars($exercicio['titulo']) ?></h3>
                                    <?php if ($exercicio['descricao']): ?>
                                        <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($exercicio['descricao']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-4">
                                    <?php if ($exercicio['progresso_status'] === 'concluido'): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                                            Concluído
                                        </span>
                                    <?php else: ?>
                                        <button class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-medium hover:bg-green-600 transition-colors">
                                            Fazer Exercício
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Seu Resumo -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">✍️ Seu Resumo</h2>
            
            <?php if ($resumo_aluno): ?>
                <div class="mb-4">
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <h3 class="font-medium text-gray-900 mb-2">Seu resumo:</h3>
                        <p class="text-gray-700"><?= htmlspecialchars($resumo_aluno['resumo_aluno']) ?></p>
                    </div>
                    
                    <?php if ($resumo_aluno['analise_ia']): ?>
                        <div class="bg-blue-50 rounded-lg p-4 mb-4">
                            <h3 class="font-medium text-blue-900 mb-2">🤖 Análise da IA:</h3>
                            <p class="text-blue-800"><?= nl2br(htmlspecialchars($resumo_aluno['analise_ia'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($resumo_aluno['lacunas_identificadas']): ?>
                        <div class="bg-yellow-50 rounded-lg p-4 mb-4">
                            <h3 class="font-medium text-yellow-900 mb-2">⚠️ Lacunas identificadas:</h3>
                            <p class="text-yellow-800"><?= nl2br(htmlspecialchars($resumo_aluno['lacunas_identificadas'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($resumo_aluno['explicacoes_complementares']): ?>
                        <div class="bg-green-50 rounded-lg p-4">
                            <h3 class="font-medium text-green-900 mb-2">💡 Explicações complementares:</h3>
                            <p class="text-green-800"><?= nl2br(htmlspecialchars($resumo_aluno['explicacoes_complementares'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form id="resumoForm" class="space-y-4">
                <div>
                    <label for="resumo_aluno" class="block text-sm font-medium text-gray-700 mb-2">
                        Escreva seu resumo da aula:
                    </label>
                    <textarea id="resumo_aluno" name="resumo_aluno" rows="6" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Escreva aqui o que você entendeu da aula..."><?= $resumo_aluno ? htmlspecialchars($resumo_aluno['resumo_aluno']) : '' ?></textarea>
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition-colors">
                    <?= $resumo_aluno ? 'Atualizar Resumo' : 'Salvar Resumo' ?>
                </button>
            </form>
        </div>
        
        <!-- Dúvidas -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">❓ Dúvidas</h2>
            
            <?php if (!empty($duvidas_aula)): ?>
                <div class="space-y-4 mb-4">
                    <?php foreach ($duvidas_aula as $duvida): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="mb-2">
                                <p class="text-gray-700"><?= htmlspecialchars($duvida['duvida']) ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?= date('d/m/Y H:i', strtotime($duvida['created_at'])) ?></p>
                            </div>
                            
                            <?php if ($duvida['resposta']): ?>
                                <div class="bg-gray-50 rounded-lg p-3 mt-2">
                                    <h4 class="font-medium text-gray-900 mb-1">Resposta:</h4>
                                    <p class="text-gray-700 text-sm"><?= htmlspecialchars($duvida['resposta']) ?></p>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 rounded-lg p-3 mt-2">
                                    <p class="text-yellow-800 text-sm">Aguardando resposta do professor...</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form id="duvidaForm" class="space-y-4">
                <div>
                    <label for="duvida" class="block text-sm font-medium text-gray-700 mb-2">
                        Envie sua dúvida:
                    </label>
                    <textarea id="duvida" name="duvida" rows="4" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Digite sua dúvida sobre esta aula..."></textarea>
                </div>
                <button type="submit" class="w-full bg-purple-500 text-white py-2 px-4 rounded-lg hover:bg-purple-600 transition-colors">
                    Enviar Dúvida
                </button>
            </form>
        </div>
        
        <!-- Ações -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">🎯 Ações</h2>
            <div class="space-y-3">
                <button id="concluirAula" class="w-full bg-green-500 text-white py-2 px-4 rounded-lg hover:bg-green-600 transition-colors">
                    ✅ Marcar Aula como Concluída
                </button>
                <a href="<?= URL ?>/jornadas/<?= $aula['jornada_id'] ?>" class="block w-full bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600 transition-colors text-center">
                    📚 Voltar para Jornada
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Timer (hidden) -->
<div id="timer" class="hidden">
    <input type="hidden" id="tempo_inicio" value="<?= time() ?>">
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resumoForm = document.getElementById('resumoForm');
    const duvidaForm = document.getElementById('duvidaForm');
    const concluirAulaBtn = document.getElementById('concluirAula');
    const tempoInicio = document.getElementById('tempo_inicio').value;
    
    // Salvar resumo
    resumoForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('aula_id', '<?= $aula['id'] ?>');
        
        fetch('<?= URL ?>/jornadas/salvar-resumo', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Resumo salvo com sucesso! A IA está analisando seu resumo...');
                location.reload();
            } else {
                alert('Erro ao salvar resumo: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao salvar resumo');
        });
    });
    
    // Enviar dúvida
    duvidaForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('aula_id', '<?= $aula['id'] ?>');
        
        fetch('<?= URL ?>/jornadas/enviar-duvida', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Dúvida enviada com sucesso!');
                location.reload();
            } else {
                alert('Erro ao enviar dúvida: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao enviar dúvida');
        });
    });
    
    // Concluir aula
    concluirAulaBtn.addEventListener('click', function() {
        if (confirm('Tem certeza que deseja marcar esta aula como concluída?')) {
            const tempoGasto = Math.floor((Date.now() / 1000) - parseInt(tempoInicio));
            
            const formData = new FormData();
            formData.append('aula_id', '<?= $aula['id'] ?>');
            formData.append('tempo_gasto', tempoGasto);
            
            fetch('<?= URL ?>/jornadas/concluir-aula', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Aula marcada como concluída!');
                    location.reload();
                } else {
                    alert('Erro ao concluir aula: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao concluir aula');
            });
        }
    });
});
</script>
