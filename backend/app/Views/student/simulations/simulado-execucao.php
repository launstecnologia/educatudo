<?php
/**
 * View: Execução do Simulado ENEM
 */
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header com Cronômetro -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    📝 Simulado ENEM <?= $simulado['ano'] ?>
                </h1>
                <p class="text-gray-600">
                    <?= $simulado['disciplina'] ?: 'Todas as disciplinas' ?> • 
                    <?= $simulado['quantidade_questoes'] ?> questões
                </p>
            </div>
            
            <!-- Cronômetro -->
            <div class="text-right">
                <div id="cronometro" class="text-3xl font-bold text-blue-600 mb-2">
                    00:00:00
                </div>
                <div class="text-sm text-gray-600">
                    <?php if ($simulado['tempo_limite'] > 0): ?>
                        Limite: <?= gmdate('H:i:s', $simulado['tempo_limite']) ?>
                    <?php else: ?>
                        Sem limite de tempo
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Progresso -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700">Progresso</span>
            <span id="progressoTexto" class="text-sm font-medium text-gray-700">0 de <?= count($questoes) ?></span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div id="progressoBarra" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
    </div>

    <!-- Formulário do Simulado -->
    <form id="simuladoForm" class="space-y-8">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="simulado_id" value="<?= $simulado['id'] ?>">
        <input type="hidden" id="tempo_inicio" name="tempo_inicio" value="<?= time() ?>">

        <?php foreach ($questoes as $index => $questao): ?>
            <div class="questao-container bg-white rounded-lg shadow-md border border-gray-200 p-6" 
                 data-questao="<?= $questao['questao_index'] ?>" 
                 style="<?= $index > 0 ? 'display: none;' : '' ?>">
                
                <!-- Cabeçalho da Questão -->
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Questão <?= $questao['questao_index'] ?> de <?= count($questoes) ?>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">Matéria:</span>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                            <?= $questao['materia'] ?: 'Geral' ?>
                        </span>
                    </div>
                </div>

                <!-- Enunciado -->
                <div class="mb-6">
                    <div class="prose max-w-none">
                        <?php
                        require_once __DIR__ . '/../../../Helpers/MarkdownHelper.php';
                        $enunciado = MarkdownHelper::processMarkdown($questao['enunciado']);
                        echo $enunciado;
                        ?>
                    </div>
                    
                    <!-- Exibir imagens se houver -->
                    <?php if (!empty($questao['imagens'])): ?>
                        <div class="mt-4 space-y-4">
                            <?php foreach ($questao['imagens'] as $imagem): ?>
                                <?php if (!empty($imagem)): ?>
                                    <div class="text-center">
                                        <img src="<?= htmlspecialchars($imagem) ?>" 
                                             alt="Imagem da questão <?= $questao['questao_index'] ?>" 
                                             class="max-w-full h-auto mx-auto rounded-lg shadow-md border border-gray-200"
                                             style="max-height: 400px;"
                                             onerror="this.style.display='none'; console.log('Erro ao carregar imagem: <?= htmlspecialchars($imagem) ?>');">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Alternativas -->
                <div class="space-y-3 mb-6">
                    <?php 
                    $alternativas = [
                        'A' => $questao['alternativa_a'],
                        'B' => $questao['alternativa_b'],
                        'C' => $questao['alternativa_c'],
                        'D' => $questao['alternativa_d'],
                        'E' => $questao['alternativa_e']
                    ];
                    ?>
                    
                    <?php foreach ($alternativas as $letra => $texto): ?>
                        <?php 
                        // Verificar se existe arquivo para esta alternativa
                        $arquivo_key = 'alternativa_' . strtolower($letra) . '_file';
                        $arquivo_url = $questao[$arquivo_key] ?? null;
                        ?>
                        <label class="flex items-start p-4 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                            <input type="radio" 
                                   name="questao_<?= $questao['questao_index'] ?>" 
                                   value="<?= $letra ?>"
                                   class="mt-1 mr-3 text-blue-600 focus:ring-blue-500"
                                   <?= $questao['resposta_aluno'] === $letra ? 'checked' : '' ?>>
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 mr-2"><?= $letra ?>)</span>
                                
                                <?php if ($arquivo_url && empty($texto)): ?>
                                    <!-- Exibir imagem quando text é NULL mas file existe -->
                                    <div class="mt-2">
                                        <img src="<?= htmlspecialchars($arquivo_url) ?>" 
                                             alt="Alternativa <?= $letra ?>" 
                                             class="max-w-full h-auto mx-auto rounded-lg shadow-md border border-gray-200"
                                             style="max-height: 400px;"
                                             onerror="this.style.display='none'; console.log('Erro ao carregar imagem da alternativa <?= $letra ?>');">
                                    </div>
                                <?php elseif ($arquivo_url && !empty($texto)): ?>
                                    <!-- Exibir ambos texto e imagem -->
                                    <span class="text-gray-700">
                                        <?php
                                        $texto_processado = MarkdownHelper::processMarkdown($texto);
                                        echo $texto_processado;
                                        ?>
                                    </span>
                                    <div class="mt-2">
                                        <img src="<?= htmlspecialchars($arquivo_url) ?>" 
                                             alt="Alternativa <?= $letra ?>" 
                                             class="max-w-full h-auto mx-auto rounded-lg shadow-md border border-gray-200"
                                             style="max-height: 400px;"
                                             onerror="this.style.display='none';">
                                    </div>
                                <?php else: ?>
                                    <!-- Exibir apenas texto -->
                                    <span class="text-gray-700">
                                        <?php
                                        $texto_processado = MarkdownHelper::processMarkdown($texto);
                                        echo $texto_processado;
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- Navegação -->
                <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                    <button type="button" 
                            class="btn-anterior px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors <?= $index === 0 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                            <?= $index === 0 ? 'disabled' : '' ?>>
                        ← Anterior
                    </button>
                    
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">Questão:</span>
                        <select id="navegacaoQuestao" class="px-3 py-1 border border-gray-300 rounded text-sm">
                            <?php foreach ($questoes as $qIndex => $q): ?>
                                <option value="<?= $qIndex ?>" <?= $qIndex === $index ? 'selected' : '' ?>>
                                    <?= $q['questao_index'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="button" 
                            class="btn-proximo px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Próxima →
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Botões de Ação -->
        <div class="flex justify-between items-center pt-6 border-t border-gray-200">
            <button type="button" id="btnPausar" 
                    class="px-6 py-3 border border-yellow-300 text-yellow-700 rounded-lg hover:bg-yellow-50 transition-colors">
                ⏸️ Pausar Simulado
            </button>
            
            <div class="text-center">
                <p class="text-sm text-gray-600 mb-2">Tempo decorrido:</p>
                <p id="tempoDecorrido" class="text-lg font-semibold text-gray-900">00:00:00</p>
            </div>
            
            <button type="button" id="btnFinalizar" 
                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                ✅ Finalizar Simulado
            </button>
        </div>
    </form>
</div>

<!-- Modal de Confirmação -->
<div id="modalConfirmacao" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirmar Finalização</h3>
            <p class="text-gray-600 mb-6">
                Tem certeza que deseja finalizar o simulado? Você não poderá alterar as respostas após a finalização.
            </p>
            <div class="flex justify-end space-x-3">
                <button type="button" id="btnCancelarFinalizar" 
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancelar
                </button>
                <button type="button" id="btnConfirmarFinalizar" 
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    Sim, Finalizar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const questoes = <?= json_encode($questoes) ?>;
    const tempoLimite = <?= $simulado['tempo_limite'] ?>;
    const simuladoId = <?= $simulado['id'] ?>;
    
    let questaoAtual = 0;
    let tempoInicio = Date.now();
    let tempoDecorrido = 0;
    let cronometroInterval;
    let respostasSalvas = {};
    
    // Inicializar cronômetro
    function iniciarCronometro() {
        cronometroInterval = setInterval(function() {
            tempoDecorrido = Math.floor((Date.now() - tempoInicio) / 1000);
            
            const horas = Math.floor(tempoDecorrido / 3600);
            const minutos = Math.floor((tempoDecorrido % 3600) / 60);
            const segundos = tempoDecorrido % 60;
            
            const tempoFormatado = 
                String(horas).padStart(2, '0') + ':' +
                String(minutos).padStart(2, '0') + ':' +
                String(segundos).padStart(2, '0');
            
            document.getElementById('cronometro').textContent = tempoFormatado;
            document.getElementById('tempoDecorrido').textContent = tempoFormatado;
            
            // Verificar limite de tempo
            if (tempoLimite > 0 && tempoDecorrido >= tempoLimite) {
                finalizarSimulado();
            }
        }, 1000);
    }
    
    // Atualizar progresso
    function atualizarProgresso() {
        const totalQuestoes = questoes.length;
        const questoesRespondidas = Object.keys(respostasSalvas).length;
        
        const percentual = (questoesRespondidas / totalQuestoes) * 100;
        
        document.getElementById('progressoBarra').style.width = percentual + '%';
        document.getElementById('progressoTexto').textContent = 
            `${questoesRespondidas} de ${totalQuestoes}`;
    }
    
    // Mostrar questão específica
    function mostrarQuestao(index) {
        console.log('mostrarQuestao chamada com index:', index);
        console.log('Total de questões:', questoes.length);
        
        if (index < 0 || index >= questoes.length) {
            console.error('Índice inválido:', index);
            return;
        }
        
        // Atualizar questaoAtual primeiro
        questaoAtual = index;
        console.log('questaoAtual atualizada para:', questaoAtual);
        
        // Esconder todas as questões
        document.querySelectorAll('.questao-container').forEach(q => {
            q.style.display = 'none';
        });
        
        // Mostrar questão atual
        const questaoElements = document.querySelectorAll('.questao-container');
        if (questaoElements[index]) {
            questaoElements[index].style.display = 'block';
            console.log('Questão exibida:', questoes[index].questao_index);
        } else {
            console.error('Elemento da questão não encontrado no índice:', index);
            console.log('Total de elementos:', questaoElements.length);
            console.log('Total de questões:', questoes.length);
        }
        
        // Atualizar navegação
        const navegacaoSelect = document.getElementById('navegacaoQuestao');
        if (navegacaoSelect) {
            navegacaoSelect.value = index;
        }
        
        // Atualizar botões
        const btnAnterior = document.querySelector('.btn-anterior');
        const btnProximo = document.querySelector('.btn-proximo');
        
        if (btnAnterior) {
            btnAnterior.disabled = index === 0;
            btnAnterior.classList.toggle('opacity-50', index === 0);
            btnAnterior.classList.toggle('cursor-not-allowed', index === 0);
        }
        
        if (btnProximo) {
            if (index === questoes.length - 1) {
                btnProximo.textContent = 'Finalizar →';
                btnProximo.classList.add('bg-green-600', 'hover:bg-green-700');
                btnProximo.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            } else {
                btnProximo.textContent = 'Próxima →';
                btnProximo.classList.add('bg-blue-600', 'hover:bg-blue-700');
                btnProximo.classList.remove('bg-green-600', 'hover:bg-green-700');
            }
        }
        
        // Atualizar progresso
        atualizarProgresso();
    }
    
    // Salvar resposta
    async function salvarResposta(questaoIndex, resposta) {
        // Evitar salvar se já foi salva recentemente
        if (respostasSalvas[questaoIndex] === resposta) {
            console.log('Resposta já salva:', questaoIndex, resposta);
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            formData.append('simulado_id', simuladoId);
            formData.append('questao_index', questaoIndex);
            formData.append('resposta_escolhida', resposta);
            formData.append('tempo_gasto', tempoDecorrido);
            
            const response = await fetch('<?= URL ?>/simulados/responder', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (response.ok) {
                respostasSalvas[questaoIndex] = resposta;
                atualizarProgresso();
                console.log('Resposta salva com sucesso:', questaoIndex, resposta);
            } else {
                console.error('Erro ao salvar resposta:', result.error);
            }
        } catch (error) {
            console.error('Erro de conexão:', error);
        }
    }
    
    // Finalizar simulado
    async function finalizarSimulado() {
        try {
            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            formData.append('simulado_id', simuladoId);
            formData.append('tempo_total', tempoDecorrido);
            
            const response = await fetch('<?= URL ?>/simulados/finalizar', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (response.ok) {
                // Redirecionar para resultado
                window.location.href = '<?= URL ?>/simulados/resultado?id=' + simuladoId;
            } else {
                alert('Erro ao finalizar simulado: ' + (result.error || 'Erro desconhecido'));
            }
        } catch (error) {
            alert('Erro de conexão. Tente novamente.');
        }
    }
    
    // Event listeners usando event delegation
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-proximo')) {
            console.log('Botão Próxima clicado');
            console.log('questaoAtual:', questaoAtual);
            console.log('Total de questões:', questoes.length);
            
            if (questaoAtual < questoes.length - 1) {
                console.log('Indo para próxima questão:', questaoAtual + 1);
                mostrarQuestao(questaoAtual + 1);
            } else {
                console.log('Última questão - mostrando modal de confirmação');
                document.getElementById('modalConfirmacao').classList.remove('hidden');
            }
        }
        
        if (e.target.classList.contains('btn-anterior')) {
            console.log('Botão Anterior clicado');
            if (questaoAtual > 0) {
                console.log('Indo para questão anterior:', questaoAtual - 1);
                mostrarQuestao(questaoAtual - 1);
            }
        }
    });
    
    document.getElementById('navegacaoQuestao').addEventListener('change', function() {
        mostrarQuestao(parseInt(this.value));
    });
    
    // Salvar resposta quando selecionada
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const questaoIndex = this.name.replace('questao_', '');
            salvarResposta(questaoIndex, this.value);
        });
    });
    
    document.getElementById('btnFinalizar').addEventListener('click', function() {
        document.getElementById('modalConfirmacao').classList.remove('hidden');
    });
    
    document.getElementById('btnCancelarFinalizar').addEventListener('click', function() {
        document.getElementById('modalConfirmacao').classList.add('hidden');
    });
    
    document.getElementById('btnConfirmarFinalizar').addEventListener('click', function() {
        document.getElementById('modalConfirmacao').classList.add('hidden');
        finalizarSimulado();
    });
    
    document.getElementById('btnPausar').addEventListener('click', function() {
        if (confirm('Deseja pausar o simulado? Você poderá retomá-lo mais tarde.')) {
            window.location.href = '<?= URL ?>/simulados';
        }
    });
    
    // Inicializar
    iniciarCronometro();
    atualizarProgresso();
    
    // Salvar respostas já existentes
    <?php foreach ($questoes as $questao): ?>
        <?php if ($questao['resposta_aluno']): ?>
            respostasSalvas[<?= $questao['questao_index'] ?>] = '<?= $questao['resposta_aluno'] ?>';
        <?php endif; ?>
    <?php endforeach; ?>
    
    atualizarProgresso();
});
</script>
