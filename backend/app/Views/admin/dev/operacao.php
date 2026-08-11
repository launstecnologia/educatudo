<?php require __DIR__ . '/_styles.php'; ?>

<header class="mb-8 pb-6 border-b border-gray-200">
    <a href="<?= URL ?>/admin/dev" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Configurações Avançadas</a>
    <div class="flex items-center gap-3 mt-2">
        <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-cyan-100 text-cyan-600 text-2xl">📊</span>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Operação e Infraestrutura</h1>
            <p class="text-sm text-gray-500 mt-0.5">Monitoramento, logs, migrations e acesso ao servidor</p>
        </div>
    </div>
</header>

<div class="space-y-6 max-w-5xl">

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="<?= URL ?>/admin/dev-settings/logs" class="dev-card p-4 flex items-center hover:border-indigo-300 transition-colors">
            <div>
                <h4 class="font-medium text-gray-900">Logs do Sistema</h4>
                <p class="text-sm text-gray-600">storage/logs/ (app, jornadas, push, openai, etc.)</p>
            </div>
        </a>
        <a href="<?= URL ?>/admin/dev-settings/logins" class="dev-card p-4 flex items-center hover:border-indigo-300 transition-colors">
            <div>
                <h4 class="font-medium text-gray-900">Histórico de Logins</h4>
                <p class="text-sm text-gray-600">Data/hora de login por usuário</p>
            </div>
        </a>
        <a href="<?= URL ?>/admin/dev-settings" class="dev-card p-4 flex items-center hover:border-indigo-300 transition-colors">
            <div>
                <h4 class="font-medium text-gray-900">Dev Settings (key-value)</h4>
                <p class="text-sm text-gray-600">Prompt de flashcards, Panda Video, JaaS</p>
            </div>
        </a>
        <a href="<?= URL ?>/admin/dev/migrations" class="dev-card p-4 flex items-center hover:border-indigo-300 transition-colors">
            <div>
                <h4 class="font-medium text-gray-900">Gerenciar Migrations</h4>
                <p class="text-sm text-gray-600">Execute migrations em bancos de escolas</p>
            </div>
        </a>
        <?php $isDemo = strpos($_SERVER['HTTP_HOST'] ?? '', 'demo.educatudo.com') !== false; ?>
        <?php if ($isDemo): ?>
        <a href="<?= URL ?>/admin/dev/ssh" class="dev-card p-4 flex items-center hover:border-indigo-300 transition-colors">
            <div>
                <h4 class="font-medium text-gray-900">SSH e Git</h4>
                <p class="text-sm text-gray-600">Execute comandos SSH e Git (apenas demo)</p>
            </div>
        </a>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">🤖 Métricas de IA</h3>
                <p class="text-gray-500 text-sm mt-1">Uso de OpenAI e outros serviços de IA</p>
            </div>
            <div class="p-6">
                <div id="ai-metrics" class="space-y-4">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
                        <p class="text-gray-600 mt-2">Carregando métricas...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">🗄️ Banco de Dados</h3>
                <p class="text-gray-500 text-sm mt-1">Performance e uso do banco de dados</p>
            </div>
            <div class="p-6">
                <div id="db-metrics" class="space-y-4">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mx-auto"></div>
                        <p class="text-gray-600 mt-2">Carregando métricas...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">🖥️ Sistema</h3>
            <p class="text-gray-500 text-sm mt-1">Métricas de sistema e performance</p>
        </div>
        <div class="p-6">
            <div id="system-metrics" class="space-y-4">
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600 mx-auto"></div>
                    <p class="text-gray-600 mt-2">Carregando métricas...</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">🛠️ Ações de Monitoramento</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button onclick="refreshMetrics()" class="flex items-center justify-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors border border-blue-200">
                    <div class="text-center">
                        <div class="text-2xl mb-2">🔄</div>
                        <div class="font-medium text-gray-900">Atualizar</div>
                        <div class="text-sm text-gray-600">Recarregar métricas</div>
                    </div>
                </button>

                <a href="/health.php" target="_blank" class="flex items-center justify-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors border border-green-200">
                    <div class="text-center">
                        <div class="text-2xl mb-2">❤️</div>
                        <div class="font-medium text-gray-900">Health Check</div>
                        <div class="text-sm text-gray-600">Status do sistema</div>
                    </div>
                </a>

                <button onclick="sendMetricsNow()" class="flex items-center justify-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors border border-purple-200">
                    <div class="text-center">
                        <div class="text-2xl mb-2">📤</div>
                        <div class="font-medium text-gray-900">Enviar Agora</div>
                        <div class="text-sm text-gray-600">Enviar métricas manualmente</div>
                    </div>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
(function() {
    let metricsLoaded = false;

    function loadMetrics() {
        if (metricsLoaded) return;
        metricsLoaded = true;

        fetch('<?= URL ?>/admin/dev/metrics')
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    renderAIMetrics(data.metrics);
                    renderDBMetrics(data.metrics);
                    renderSystemMetrics(data.metrics);
                } else {
                    showError('Erro ao carregar métricas');
                }
            })
            .catch(err => {
                console.error('Erro ao carregar métricas:', err);
                showError('Erro de conexão');
            });
    }

    function renderAIMetrics(metrics) {
        const container = document.getElementById('ai-metrics');
        if (!container) return;

        const ai = metrics.ai || {};

        container.innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                    <span class="text-gray-700 font-medium">Requisições Hoje</span>
                    <span class="font-semibold text-blue-600">${ai.requests || 0}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <span class="text-gray-700 font-medium">Tokens Usados</span>
                    <span class="font-semibold text-green-600">${ai.tokens || 0}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                    <span class="text-gray-700 font-medium">Custo Total</span>
                    <span class="font-semibold text-yellow-600">$${parseFloat(ai.cost || 0).toFixed(4)}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                    <span class="text-gray-700 font-medium">Erros</span>
                    <span class="font-semibold text-red-600">${ai.errors || 0}</span>
                </div>
            </div>
            <div class="mt-4">
                <h4 class="font-medium text-gray-900 mb-2">Tempo Médio de Resposta</h4>
                <div class="text-2xl font-bold text-indigo-600">${parseFloat(ai.avg_time || 0).toFixed(2)}s</div>
            </div>
        `;
    }

    function renderDBMetrics(metrics) {
        const container = document.getElementById('db-metrics');
        if (!container) return;

        const db = metrics.db || {};

        container.innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <span class="text-gray-700 font-medium">Queries Hoje</span>
                    <span class="font-semibold text-green-600">${db.queries || 0}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                    <span class="text-gray-700 font-medium">Queries Lentas</span>
                    <span class="font-semibold text-orange-600">${db.slow_queries || 0}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                    <span class="text-gray-700 font-medium">Tempo Médio Query</span>
                    <span class="font-semibold text-blue-600">${parseFloat(db.avg_time || 0).toFixed(3)}s</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                    <span class="text-gray-700 font-medium">Erros DB</span>
                    <span class="font-semibold text-red-600">${db.errors || 0}</span>
                </div>
            </div>
        `;
    }

    function renderSystemMetrics(metrics) {
        const container = document.getElementById('system-metrics');
        if (!container) return;

        const sys = metrics.system || {};
        const accessesByType = sys.accesses_by_type || {};

        container.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600 mb-1">${parseFloat(sys.memory_peak || 0).toFixed(1)} MB</div>
                    <div class="text-sm text-gray-600">Pico de Memória</div>
                </div>
                <div class="text-center p-4 bg-indigo-50 rounded-lg">
                    <div class="text-2xl font-bold text-indigo-600 mb-1">${sys.requests_minute || 0}</div>
                    <div class="text-sm text-gray-600">Requests/Minuto</div>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <div class="text-2xl font-bold text-red-600 mb-1">${sys.errors_500 || 0}</div>
                    <div class="text-sm text-gray-600">Erros 500 Hoje</div>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                <div class="text-center p-4 bg-emerald-50 rounded-lg">
                    <div class="text-xl font-bold text-emerald-600 mb-1">${sys.accesses_total || 0}</div>
                    <div class="text-sm text-gray-600">Acessos Totais</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-xl font-bold text-blue-600 mb-1">${accessesByType.aluno || 0}</div>
                    <div class="text-sm text-gray-600">Acessos Aluno</div>
                </div>
                <div class="text-center p-4 bg-violet-50 rounded-lg">
                    <div class="text-xl font-bold text-violet-600 mb-1">${accessesByType.professor || 0}</div>
                    <div class="text-sm text-gray-600">Acessos Professor</div>
                </div>
                <div class="text-center p-4 bg-slate-50 rounded-lg">
                    <div class="text-xl font-bold text-slate-600 mb-1">${accessesByType.admin || 0}</div>
                    <div class="text-sm text-gray-600">Acessos Admin</div>
                </div>
            </div>
            <div class="mt-4 text-sm text-gray-600">
                <div>Última atualização: ${new Date((sys.last_updated || 0) * 1000).toLocaleString()}</div>
            </div>
        `;
    }

    function showError(message) {
        ['ai-metrics', 'db-metrics', 'system-metrics'].forEach(id => {
            const container = document.getElementById(id);
            if (container) {
                container.innerHTML = `
                    <div class="text-center py-8 text-red-600">
                        <div class="text-4xl mb-2">❌</div>
                        <p>${message}</p>
                    </div>
                `;
            }
        });
    }

    window.refreshMetrics = function() {
        metricsLoaded = false;
        loadMetrics();
    };

    window.sendMetricsNow = function() {
        if (!confirm('Enviar métricas para a API central agora?')) return;

        fetch('<?= URL ?>/admin/dev/send-metrics', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData()
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                refreshMetrics();
            } else {
                alert('❌ Erro: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(() => alert('❌ Erro de conexão'));
    };

    loadMetrics();
})();
</script>
