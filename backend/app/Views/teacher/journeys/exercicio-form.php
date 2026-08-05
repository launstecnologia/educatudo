<?php
/**
 * EducaTudo - View para Criar/Editar Exercícios
 * Interface para professores criarem e editarem exercícios das jornadas
 */
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        <?= isset($exercicio) ? 'Editar Exercício' : 'Criar Exercício' ?>
                    </h1>
                    <p class="mt-2 text-gray-600">
                        <?= isset($exercicio) ? 'Modifique os dados do exercício' : 'Crie um novo exercício para a jornada' ?>
                    </p>
                </div>
                <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Voltar
                </a>
            </div>
        </div>

        <!-- Formulário -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-8">
                <form id="exercicioForm" method="POST" action="<?= URL ?>/professor/jornadas/<?= isset($exercicio) ? 'atualizar-exercicio' : 'criar-exercicio' ?>">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="jornada_id" value="<?= $jornada['id'] ?>">
                    <input type="hidden" name="aula_id" value="<?= $aula['id'] ?? '' ?>">
                    <?php if (isset($exercicio)): ?>
                        <input type="hidden" name="exercicio_id" value="<?= $exercicio['id'] ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 gap-6">
                        <!-- Informações da Aula -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h3 class="text-lg font-medium text-blue-900 mb-2">Informações da Aula</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-blue-700">Jornada</label>
                                    <p class="mt-1 text-sm text-blue-600"><?= htmlspecialchars($jornada['titulo']) ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-blue-700">Aula</label>
                                    <p class="mt-1 text-sm text-blue-600"><?= htmlspecialchars($aula['titulo'] ?? 'Geral') ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Tipo de Exercício -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Exercício</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="relative flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="tipo_exercicio" value="manual" 
                                           <?= (!isset($exercicio) || $exercicio['tipo'] === 'manual') ? 'checked' : '' ?>
                                           class="sr-only">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900">Exercício Manual</p>
                                            <p class="text-sm text-gray-500">Criar exercício personalizado</p>
                                        </div>
                                    </div>
                                </label>
                                
                                <?php
                                if (!class_exists('CreditosModuleRegistry', false)) {
                                    require_once __DIR__ . '/../../../Core/CreditosModuleRegistry.php';
                                }
                                $tudicoinsExercicioFormIa = \CreditosModuleRegistry::acaoIaDisponivel('gerar_exercicio_ia_professor');
                                if ($tudicoinsExercicioFormIa):
                                ?>
                                <label class="relative flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="tipo_exercicio" value="ia" 
                                           <?= (isset($exercicio) && $exercicio['tipo'] === 'ia') ? 'checked' : '' ?>
                                           class="sr-only">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900">Gerado por IA</p>
                                            <p class="text-sm text-gray-500">Usar inteligência artificial</p>
                                        </div>
                                    </div>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Seção IA -->
                        <?php if ($tudicoinsExercicioFormIa): ?>
                        <div id="secaoIA" class="hidden">
                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
                                <h3 class="text-lg font-medium text-purple-900 mb-4">Gerar Exercício com IA</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-purple-700 mb-2">Conteúdo da Aula</label>
                                        <textarea name="conteudo_aula" rows="4" 
                                                  class="w-full px-3 py-2 border border-purple-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                                                  placeholder="Cole aqui o conteúdo da aula para a IA gerar exercícios baseados nele..."><?= htmlspecialchars($aula['conteudo'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-purple-700 mb-2">Matéria</label>
                                            <select name="materia" class="w-full px-3 py-2 border border-purple-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                                <option value="">Selecione a matéria</option>
                                                <?php foreach ($materias as $materia): ?>
                                                    <option value="<?= htmlspecialchars($materia['nome']) ?>" 
                                                            <?= (isset($exercicio) && $exercicio['materia'] === $materia['nome']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($materia['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-purple-700 mb-2">Dificuldade</label>
                                            <select name="dificuldade" class="w-full px-3 py-2 border border-purple-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                                <option value="fácil" <?= (isset($exercicio) && $exercicio['dificuldade'] === 'fácil') ? 'selected' : '' ?>>Fácil</option>
                                                <option value="médio" <?= (!isset($exercicio) || $exercicio['dificuldade'] === 'médio') ? 'selected' : '' ?>>Médio</option>
                                                <option value="difícil" <?= (isset($exercicio) && $exercicio['dificuldade'] === 'difícil') ? 'selected' : '' ?>>Difícil</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <button type="button" id="gerarExercicioIA" 
                                            class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                        </svg>
                                        Gerar Exercício com IA
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Seção Manual -->
                        <div id="secaoManual">
                            <div class="space-y-6">
                                <!-- Enunciado -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Enunciado</label>
                                    <textarea name="enunciado" rows="4" required
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                              placeholder="Digite o enunciado da questão..."><?= htmlspecialchars($exercicio['enunciado'] ?? '') ?></textarea>
                                </div>

                                <!-- Alternativas -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Alternativas</label>
                                    <div class="space-y-3">
                                        <?php 
                                        $alternativas = isset($exercicio) ? json_decode($exercicio['alternativas'], true) : ['', '', '', ''];
                                        for ($i = 0; $i < 4; $i++): 
                                        ?>
                                            <div class="flex items-center space-x-3">
                                                <span class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-medium text-gray-600">
                                                    <?= chr(65 + $i) ?>
                                                </span>
                                                <input type="text" name="alternativas[]" 
                                                       value="<?= htmlspecialchars($alternativas[$i] ?? '') ?>"
                                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                       placeholder="Digite a alternativa <?= chr(65 + $i) ?>...">
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <!-- Resposta Correta -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Resposta Correta</label>
                                    <select name="resposta_correta" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Selecione a resposta correta</option>
                                        <option value="A" <?= (isset($exercicio) && $exercicio['resposta_correta'] === 'A') ? 'selected' : '' ?>>A</option>
                                        <option value="B" <?= (isset($exercicio) && $exercicio['resposta_correta'] === 'B') ? 'selected' : '' ?>>B</option>
                                        <option value="C" <?= (isset($exercicio) && $exercicio['resposta_correta'] === 'C') ? 'selected' : '' ?>>C</option>
                                        <option value="D" <?= (isset($exercicio) && $exercicio['resposta_correta'] === 'D') ? 'selected' : '' ?>>D</option>
                                    </select>
                                </div>

                                <!-- Explicação -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Explicação</label>
                                    <textarea name="explicacao" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                              placeholder="Explique por que esta é a resposta correta..."><?= htmlspecialchars($exercicio['explicacao'] ?? '') ?></textarea>
                                </div>

                                <!-- Dificuldade -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Dificuldade</label>
                                    <select name="dificuldade" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="fácil" <?= (isset($exercicio) && $exercicio['dificuldade'] === 'fácil') ? 'selected' : '' ?>>Fácil</option>
                                        <option value="médio" <?= (!isset($exercicio) || $exercicio['dificuldade'] === 'médio') ? 'selected' : '' ?>>Médio</option>
                                        <option value="difícil" <?= (isset($exercicio) && $exercicio['dificuldade'] === 'difícil') ? 'selected' : '' ?>>Difícil</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Botões -->
                        <div class="flex justify-end space-x-4 pt-6 border-t">
                            <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>" 
                               class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </a>
                            <button type="submit" id="salvarExercicio"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?= isset($exercicio) ? 'Atualizar Exercício' : 'Criar Exercício' ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Exercício Gerado pela IA -->
<div id="modalExercicioIA" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Exercício Gerado pela IA</h3>
            </div>
            <div class="px-6 py-4">
                <div id="conteudoExercicioIA">
                    <!-- Conteúdo será inserido aqui via JavaScript -->
                </div>
            </div>
            <div class="px-6 py-4 border-t flex justify-end space-x-3">
                <button type="button" id="fecharModalIA" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Fechar
                </button>
                <button type="button" id="aprovarExercicioIA" 
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    Aprovar e Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoExercicio = document.querySelectorAll('input[name="tipo_exercicio"]');
    const secaoIA = document.getElementById('secaoIA');
    const secaoManual = document.getElementById('secaoManual');
    const gerarExercicioIA = document.getElementById('gerarExercicioIA');
    const modalExercicioIA = document.getElementById('modalExercicioIA');
    const fecharModalIA = document.getElementById('fecharModalIA');
    const aprovarExercicioIA = document.getElementById('aprovarExercicioIA');
    const formExercicio = document.getElementById('exercicioForm');
    
    let exercicioGerado = null;
    
    // Controla exibição das seções
    tipoExercicio.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'ia') {
                secaoIA.classList.remove('hidden');
                secaoManual.classList.add('hidden');
            } else {
                secaoIA.classList.add('hidden');
                secaoManual.classList.remove('hidden');
            }
        });
    });
    
    // Gera exercício com IA
    gerarExercicioIA.addEventListener('click', function() {
        const conteudoAula = document.querySelector('textarea[name="conteudo_aula"]').value;
        const materia = document.querySelector('select[name="materia"]').value;
        const dificuldade = document.querySelector('select[name="dificuldade"]').value;
        
        if (!conteudoAula.trim()) {
            alert('Por favor, insira o conteúdo da aula para gerar o exercício.');
            return;
        }
        
        if (!materia) {
            alert('Por favor, selecione a matéria.');
            return;
        }
        
        this.disabled = true;
        this.innerHTML = '<svg class="animate-spin w-5 h-5 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Gerando...';
        
        var controller = new AbortController();
        var timeoutId = setTimeout(function() { controller.abort(); }, 120000);
        fetch('<?= URL ?>/professor/jornadas/gerar-exercicio-ia', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                '_token': <?= json_encode($csrf_token) ?>,
                'jornada_id': '<?= $jornada['id'] ?>',
                'aula_id': '<?= $aula['id'] ?? '' ?>',
                'conteudo_aula': conteudoAula,
                'materia': materia,
                'dificuldade': dificuldade
            }),
            signal: controller.signal
        })
        .then(function(response) {
            clearTimeout(timeoutId);
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                exercicioGerado = data.exercicio;
                mostrarExercicioIA(data.exercicio);
                modalExercicioIA.classList.remove('hidden');
            } else {
                alert('Erro ao gerar exercício: ' + data.error);
            }
        })
        .catch(function(error) {
            clearTimeout(timeoutId);
            console.error('Erro:', error);
            if (error.name === 'AbortError') {
                alert('A requisição demorou muito. A IA pode levar até 2 minutos. Tente novamente ou recarregue a página.');
            } else {
                alert('Erro ao conectar com a IA. A geração pode levar até 2 minutos. Se persistir, recarregue a página e tente novamente.');
            }
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>Gerar Exercício com IA';
        });
    });
    
    // Mostra exercício gerado pela IA
    function mostrarExercicioIA(exercicio) {
        const conteudo = document.getElementById('conteudoExercicioIA');
        conteudo.innerHTML = `
            <div class="space-y-4">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Enunciado:</h4>
                    <p class="text-gray-700 bg-gray-50 p-3 rounded">${exercicio.enunciado}</p>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Alternativas:</h4>
                    <div class="space-y-2">
                        ${exercicio.alternativas.map((alt, index) => `
                            <div class="flex items-center space-x-2">
                                <span class="w-6 h-6 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center text-sm font-medium">
                                    ${String.fromCharCode(65 + index)}
                                </span>
                                <span class="text-gray-700">${alt}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Resposta Correta:</h4>
                    <p class="text-green-600 font-medium">${exercicio.resposta_correta}</p>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Explicação:</h4>
                    <p class="text-gray-700 bg-gray-50 p-3 rounded">${exercicio.explicacao}</p>
                </div>
                
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-900">Dificuldade:</span>
                        <span class="text-gray-700">${exercicio.dificuldade}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-900">Competência:</span>
                        <span class="text-gray-700">${exercicio.competencia || 'N/A'}</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Fecha modal
    fecharModalIA.addEventListener('click', function() {
        modalExercicioIA.classList.add('hidden');
    });
    
    // Aprova exercício da IA
    aprovarExercicioIA.addEventListener('click', function() {
        if (!exercicioGerado) return;
        
        this.disabled = true;
        this.innerHTML = '<svg class="animate-spin w-4 h-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Salvando...';
        
        fetch('<?= URL ?>/professor/jornadas/aprovar-exercicio-ia', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                '_token': <?= json_encode($csrf_token) ?>,
                'exercicio_id': exercicioGerado.id || ''
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Exercício aprovado e salvo com sucesso!');
                window.location.href = '<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>';
            } else {
                alert('Erro ao salvar exercício: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao salvar exercício. Tente novamente.');
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = 'Aprovar e Salvar';
        });
    });
    
    // Submissão do formulário manual
    formExercicio.addEventListener('submit', function(e) {
        const tipoSelecionado = document.querySelector('input[name="tipo_exercicio"]:checked').value;
        
        if (tipoSelecionado === 'manual') {
            const enunciado = document.querySelector('textarea[name="enunciado"]').value;
            const alternativas = document.querySelectorAll('input[name="alternativas[]"]');
            const respostaCorreta = document.querySelector('select[name="resposta_correta"]').value;
            
            if (!enunciado.trim()) {
                e.preventDefault();
                alert('Por favor, preencha o enunciado.');
                return;
            }
            
            let alternativasPreenchidas = 0;
            alternativas.forEach(alt => {
                if (alt.value.trim()) alternativasPreenchidas++;
            });
            
            if (alternativasPreenchidas < 2) {
                e.preventDefault();
                alert('Por favor, preencha pelo menos 2 alternativas.');
                return;
            }
            
            if (!respostaCorreta) {
                e.preventDefault();
                alert('Por favor, selecione a resposta correta.');
                return;
            }
        }
    });
});
</script>
