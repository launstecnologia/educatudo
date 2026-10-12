<?php
/**
 * View: Criar Simulado Vestibular
 */
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center">
            <a href="<?= URL ?>/simulados" class="text-blue-600 hover:text-blue-800 mr-4">
                ← Voltar para Simulados
            </a>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">📝 Criar Novo Simulado</h1>
        <p class="text-gray-600">Configure seu simulado personalizado com questões reais de vestibulares</p>
    </div>

    <div class="max-w-4xl mx-auto">
        <form id="criarSimuladoForm" method="POST" action="<?= URL ?>/simulados/criar" class="space-y-8">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg mb-6 shadow-md">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                ❌ Erro ao criar simulado
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <p><?= htmlspecialchars(urldecode($_GET['error'])) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Configurações Básicas -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">⚙️ Configurações Básicas</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Ano da Prova -->
                    <div>
                        <label for="ano" class="block text-sm font-medium text-gray-700 mb-2">
                            Ano da Prova *
                        </label>
                        <select id="ano" name="ano" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Selecione o ano</option>
                            <option value="2023">2023</option>
                            <option value="2022">2022</option>
                            <option value="2021">2021</option>
                            <option value="2020">2020</option>
                            <option value="2019">2019</option>
                            <option value="2018">2018</option>
                            <option value="2017">2017</option>
                        </select>
                    </div>

                    <!-- Tipo de Vestibular -->
                    <div>
                        <label for="tipo_vestibular" class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo de Vestibular *
                        </label>
                        <select id="tipo_vestibular" name="tipo_vestibular" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Selecione</option>
                            <option value="ENEM">ENEM</option>
                        </select>
                    </div>
                    <div>
                        <label for="disciplina" class="block text-sm font-medium text-gray-700 mb-2">
                            Disciplina *
                        </label>
                        <select id="disciplina" name="disciplina" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Selecione o tipo de vestibular primeiro</option>
                        </select>
                    </div>

                    <!-- Quantidade de Questões -->
                    <div>
                        <label for="quantidade_questoes" class="block text-sm font-medium text-gray-700 mb-2">
                            Quantidade de Questões *
                        </label>
                        <select id="quantidade_questoes" name="quantidade_questoes" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="5">5 questões (Rápido - ~15 min)</option>
                            <option value="10" selected>10 questões (Padrão - ~30 min)</option>
                            <option value="20">20 questões (Completo - ~60 min)</option>
                            <option value="30">30 questões (Intensivo - ~90 min)</option>
                            <option value="45">45 questões (Simulado Completo - ~135 min)</option>
                        </select>
                    </div>

                    <!-- Tempo Limite -->
                    <div>
                        <label for="tempo_limite" class="block text-sm font-medium text-gray-700 mb-2">
                            Tempo Limite (minutos)
                        </label>
                        <input type="number" id="tempo_limite" name="tempo_limite" 
                               min="5" max="300" value="30"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Deixe em branco para tempo ilimitado</p>
                    </div>
                </div>
            </div>

            <!-- Configurações Avançadas -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">🔧 Configurações Avançadas</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Idioma -->
                    <div>
                        <label for="idioma" class="block text-sm font-medium text-gray-700 mb-2">
                            Idioma
                        </label>
                        <select id="idioma" name="idioma"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="Português" selected>Português</option>
                            <option value="Inglês">Inglês</option>
                            <option value="Espanhol">Espanhol</option>
                        </select>
                    </div>

                    <!-- Dificuldade -->
                    <div>
                        <label for="dificuldade" class="block text-sm font-medium text-gray-700 mb-2">
                            Nível de Dificuldade
                        </label>
                        <select id="dificuldade" name="dificuldade"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Misto</option>
                            <option value="facil">Fácil</option>
                            <option value="medio">Médio</option>
                            <option value="dificil">Difícil</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Resumo da Configuração -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-blue-900 mb-4">📋 Resumo da Configuração</h3>
                <div id="resumoConfiguracao" class="text-blue-800">
                    <p>Selecione as opções acima para ver o resumo do seu simulado.</p>
                </div>
            </div>

            <!-- Botões -->
            <div class="flex justify-end space-x-4">
                <a href="<?= URL ?>/simulados" 
                   class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancelar
                </a>
                <button type="submit" 
                        class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                    🚀 Criar Simulado
                </button>
            </div>
        </form>
    </div>

    <!-- Informações Importantes -->
    <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-yellow-900 mb-3">⚠️ Informações Importantes</h3>
        <div class="text-yellow-800 space-y-2">
            <p>• <strong>Questões Reais:</strong> Todas as questões são extraídas do banco oficial do ENEM</p>
            <p>• <strong>Salvamento Automático:</strong> Seu progresso é salvo automaticamente</p>
            <p>• <strong>Retomada:</strong> Você pode pausar e retomar o simulado a qualquer momento</p>
            <p>• <strong>Correção Instantânea:</strong> Veja seus resultados imediatamente após finalizar</p>
            <p>• <strong>Sem Limite de Tentativas:</strong> Crie quantos simulados quiser</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('criarSimuladoForm');
    const resumoDiv = document.getElementById('resumoConfiguracao');
    
    // Dados das provas disponíveis
    const provasDisponiveis = <?= json_encode($provas_disponiveis ?? []) ?>;
    
    // Função para atualizar disciplinas baseado no tipo de vestibular
    function atualizarDisciplinas() {
        const tipoVestibular = document.getElementById('tipo_vestibular').value;
        const disciplinaSelect = document.getElementById('disciplina');
        
        // Se nenhum tipo de vestibular selecionado, mostrar mensagem apropriada
        if (!tipoVestibular) {
            disciplinaSelect.innerHTML = '<option value="">Selecione o tipo de vestibular primeiro</option>';
            atualizarResumo();
            return;
        }
        
        // Limpar opções existentes
        disciplinaSelect.innerHTML = '<option value="">Todas as disciplinas</option>';
        
        if (provasDisponiveis[tipoVestibular]) {
            const disciplinas = provasDisponiveis[tipoVestibular].disciplinas || [];
            
            disciplinas.forEach(function(disciplina) {
                const option = document.createElement('option');
                option.value = disciplina.value;
                option.textContent = disciplina.label;
                disciplinaSelect.appendChild(option);
            });
        }
        
        // Atualizar resumo após mudança
        atualizarResumo();
    }
    
    // Atualizar resumo em tempo real
    function atualizarResumo() {
        const ano = document.getElementById('ano').value;
        const tipoVestibular = document.getElementById('tipo_vestibular').value;
        const disciplina = document.getElementById('disciplina').value;
        const quantidade = document.getElementById('quantidade_questoes').value;
        const tempo = document.getElementById('tempo_limite').value;
        const idioma = document.getElementById('idioma').value;
        const dificuldade = document.getElementById('dificuldade').value;
        
        let resumo = '';
        
        if (tipoVestibular) {
            resumo += `<p><strong>Tipo de Vestibular:</strong> ${tipoVestibular}</p>`;
        } else {
            resumo += `<p><strong>Tipo de Vestibular:</strong> <em class="text-gray-500">Não selecionado</em></p>`;
        }
        
        if (ano) {
            resumo += `<p><strong>Ano:</strong> ${ano}</p>`;
        }
        
        if (disciplina) {
            resumo += `<p><strong>Disciplina:</strong> ${disciplina}</p>`;
        } else {
            resumo += `<p><strong>Disciplina:</strong> Todas</p>`;
        }
        
        resumo += `<p><strong>Quantidade de Questões:</strong> ${quantidade}</p>`;
        
        if (tempo) {
            resumo += `<p><strong>Tempo Limite:</strong> ${tempo} minutos</p>`;
        } else {
            resumo += `<p><strong>Tempo Limite:</strong> Ilimitado</p>`;
        }
        
        resumo += `<p><strong>Idioma:</strong> ${idioma}</p>`;
        
        if (dificuldade) {
            resumo += `<p><strong>Dificuldade:</strong> ${dificuldade.charAt(0).toUpperCase() + dificuldade.slice(1)}</p>`;
        } else {
            resumo += `<p><strong>Dificuldade:</strong> Misto</p>`;
        }
        
        resumoDiv.innerHTML = resumo;
    }
    
    // Adicionar listeners para atualizar resumo
    ['tipo_vestibular', 'ano', 'disciplina', 'quantidade_questoes', 'tempo_limite', 'idioma', 'dificuldade'].forEach(id => {
        document.getElementById(id).addEventListener('change', atualizarResumo);
    });
    
    // Listener especial para tipo de vestibular (atualizar disciplinas)
    document.getElementById('tipo_vestibular').addEventListener('change', atualizarDisciplinas);
    
    // Inicializar disciplinas no carregamento da página
    atualizarDisciplinas();
    
    // Atualizar resumo inicial
    atualizarResumo();
    
    // Submit do formulário - POST tradicional (sem AJAX)
    form.addEventListener('submit', function(e) {
        console.log('Formulário sendo enviado...');
        
        // Validar campos obrigatórios
        const ano = document.getElementById('ano').value;
        const tipoVestibular = document.getElementById('tipo_vestibular').value;
        const disciplina = document.getElementById('disciplina').value;

        if (!ano || !tipoVestibular || !disciplina) {
            e.preventDefault();
            alert('Por favor, preencha todos os campos obrigatórios: Ano da Prova, Tipo de Vestibular e Disciplina.');
            return false;
        }
        
        // Não prevenir o comportamento padrão - deixar o formulário ser enviado normalmente
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '⏳ Criando...';
        submitBtn.disabled = true;
        
        console.log('Enviando dados:', {
            ano: ano,
            tipo_vestibular: tipoVestibular,
            disciplina: document.getElementById('disciplina').value,
            quantidade_questoes: document.getElementById('quantidade_questoes').value,
            tempo_limite: document.getElementById('tempo_limite').value,
            idioma: document.getElementById('idioma').value,
            dificuldade: document.getElementById('dificuldade').value
        });
        
        // O formulário será enviado via POST tradicional
        // O servidor processará e redirecionará ou mostrará erro
    });
});
</script>
