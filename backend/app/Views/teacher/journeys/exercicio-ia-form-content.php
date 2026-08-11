<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-r from-purple-500 to-blue-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <span class="text-white font-bold text-2xl">🤖</span>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Gerar Exercícios com IA</h1>
                <p class="text-gray-600">Configure os parâmetros para gerar exercícios automaticamente</p>
                <div class="mt-4 inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-lg">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium">Jornada: <?= htmlspecialchars($jornada['titulo']) ?></span>
                </div>
            </div>

            <?php if (isset($_GET['erro'])): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <h3 class="text-lg font-semibold text-red-800 mb-2">Erro</h3>
                    <p class="text-red-700"><?= htmlspecialchars($_GET['erro']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['job_id'])): ?>
                <div id="ai-polling-box" class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6 text-center">
                    <div class="animate-spin w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                    <p class="text-blue-800 font-semibold text-lg">Gerando exercícios com IA...</p>
                    <p class="text-blue-600 text-sm mt-1">Isso pode levar alguns segundos.</p>
                </div>
                <?php include dirname(__DIR__, 2) . '/components/ai-job-poller.php'; ?>
                <script>
                new AIJobPoller(<?= (int) $_GET['job_id'] ?>, {
                    onDone: function(result) {
                        var exercicioId = result && result.exercicio_id ? result.exercicio_id : null;
                        if (exercicioId) {
                            window.location.href = '<?= URL ?>/professor/jornadas/<?= (int) ($jornada_id ?? 0) ?>/exercicios/resultado/' + exercicioId;
                        } else {
                            document.getElementById('ai-polling-box').innerHTML = '<p class="text-green-700 font-semibold">Exercícios gerados! Recarregando...</p>';
                            window.location.reload();
                        }
                    },
                    onFailed: function(err) {
                        var box = document.getElementById('ai-polling-box');
                        box.innerHTML = '<p class="text-red-700 font-semibold">Falha ao gerar exercícios:</p><p id="ai-polling-err-msg" class="text-red-600 text-sm mt-1"></p>';
                        document.getElementById('ai-polling-err-msg').textContent = err;
                    }
                });
                </script>
            <?php endif; ?>

            <form id="gerarExercicioForm" method="POST" action="<?= URL ?>/professor/jornadas/gerar-exercicio-ia" class="space-y-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="jornada_id" value="<?= htmlspecialchars($jornada_id) ?>">
                
                <!-- Matéria -->
                <div>
                    <label for="materia" class="block text-sm font-semibold text-gray-700 mb-2">
                        Matéria *
                    </label>
                    <select id="materia" name="materia" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">Selecione a matéria</option>
                        <?php foreach ($materias as $materia): ?>
                            <option value="<?= htmlspecialchars($materia['nome']) ?>" 
                                    <?= ($materia['nome'] === $jornada['materia_nome']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($materia['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Aula (opcional) -->
                <div>
                    <label for="aula_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        Aula Específica (opcional)
                    </label>
                    <select id="aula_id" name="aula_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">Todas as aulas</option>
                        <?php foreach ($aulas as $aula): ?>
                            <option value="<?= htmlspecialchars($aula['id']) ?>">
                                <?= htmlspecialchars($aula['nome_aula']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tipo de Exercício -->
                <div>
                    <label for="tipo_exercicio" class="block text-sm font-semibold text-gray-700 mb-2">
                        Tipo de Exercício *
                    </label>
                    <select id="tipo_exercicio" name="tipo_exercicio" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="múltipla_escolha">Múltipla Escolha</option>
                        <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                        <option value="dissertativa">Dissertativa</option>
                        <option value="completar">Completar Lacunas</option>
                    </select>
                </div>

                <!-- Nível -->
                <div>
                    <label for="nivel" class="block text-sm font-semibold text-gray-700 mb-2">
                        Nível de Dificuldade *
                    </label>
                    <select id="nivel" name="nivel" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="fácil">Fácil</option>
                        <option value="médio" selected>Médio</option>
                        <option value="difícil">Difícil</option>
                    </select>
                </div>

                <!-- Quantidade -->
                <div>
                    <label for="quantidade" class="block text-sm font-semibold text-gray-700 mb-2">
                        Quantidade de Exercícios *
                    </label>
                    <select id="quantidade" name="quantidade" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="3">3 exercícios</option>
                        <option value="5" selected>5 exercícios</option>
                        <option value="10">10 exercícios</option>
                        <option value="15">15 exercícios</option>
                    </select>
                </div>

                <!-- Contexto Adicional -->
                <div>
                    <label for="contexto_aula" class="block text-sm font-semibold text-gray-700 mb-2">
                        Contexto Adicional (opcional)
                    </label>
                    <textarea id="contexto_aula" name="contexto_aula" rows="4"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                              placeholder="Descreva aspectos específicos que devem ser abordados nos exercícios..."></textarea>
                </div>

                <!-- Botões -->
                <div class="flex space-x-4">
                    <button type="button" onclick="window.history.back()" 
                            class="flex-1 bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-colors">
                        Voltar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-gradient-to-r from-purple-500 to-blue-500 text-white px-6 py-3 rounded-lg hover:from-purple-600 hover:to-blue-600 transition-all duration-200 flex items-center justify-center">
                        🤖 Gerar Exercícios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
