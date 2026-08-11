<!-- Welcome Section -->
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">
        Painel Administrativo 🏫
    </h2>
    <p class="text-gray-600">
        Gerencie usuários, turmas, matérias e acompanhe o desempenho da escola.
    </p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <a href="<?= URL ?>/admin/alunos-online" class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg p-6 border border-purple-200 hover:shadow-xl transition-all duration-300 block">
        <div class="flex items-center">
            <div class="p-2 bg-gradient-to-r from-purple-100 to-purple-200 rounded-lg shadow-md">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Alunos Online</p>
                <p id="alunos-online-count" class="text-2xl font-bold text-purple-600"><?= (int)($stats['alunos_online'] ?? 0) ?></p>
            </div>
        </div>
    </a>

    <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg p-6 border border-indigo-200 hover:shadow-xl transition-all duration-300">
        <div class="flex items-center">
            <div class="p-2 bg-gradient-to-r from-indigo-100 to-indigo-200 rounded-lg shadow-md">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total de Jornadas</p>
                <p class="text-2xl font-bold text-indigo-600"><?= (int)($stats['total_jornadas'] ?? 0) ?></p>
            </div>
        </div>
    </div>

    <?php if (isset($stats['alertas_novos'])): ?>
    <a href="<?= URL ?>/admin/monitoramento/alertas" class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg p-6 border border-red-200 hover:shadow-xl transition-all duration-300 block">
        <div class="flex items-center">
            <div class="p-2 bg-gradient-to-r from-red-100 to-red-200 rounded-lg shadow-md">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 5a7 7 0 100 14 7 7 0 000-14z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Alertas Sensíveis</p>
                <p class="text-2xl font-bold text-red-600"><?= (int)$stats['alertas_novos'] ?></p>
                <p class="text-xs text-gray-500 mt-1">Novos</p>
            </div>
        </div>
    </a>
    <?php endif; ?>

</div>

<?php if (LayoutHelper::isModuleEnabled('aulas_online') && !empty($aulas_online) && is_array($aulas_online)): ?>
<!-- Aulas Online -->
<div class="mb-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <span class="p-2 bg-gradient-to-r from-rose-100 to-rose-200 rounded-lg shadow-md">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </span>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Aulas Online</h3>
                    <p class="text-sm text-gray-500">Aulas ao vivo agora ou agendadas.</p>
                </div>
            </div>
            <a href="<?= URL ?>/admin/aulas-online" class="text-sm text-rose-600 hover:underline">Ver todas</a>
        </div>

        <div class="space-y-3">
            <?php foreach ($aulas_online as $aula): ?>
                <?php
                    $inicioTs = !empty($aula['inicio_em']) ? strtotime((string) $aula['inicio_em']) : false;
                    $fimTs = !empty($aula['fim_em']) ? strtotime((string) $aula['fim_em']) : false;
                    $nowTs = time();
                    $iniciou = $inicioTs !== false && $nowTs >= $inicioTs;
                    $encerrou = $fimTs !== false && $nowTs > $fimTs;
                    $aoVivo = $iniciou && !$encerrou;
                ?>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border border-gray-100 rounded-xl p-4 <?= $aoVivo ? 'bg-rose-50 border-rose-200' : 'bg-gray-50' ?>">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <?php if ($aoVivo): ?>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full">
                                    <span class="w-2 h-2 bg-rose-600 rounded-full animate-pulse"></span> Ao vivo
                                </span>
                            <?php else: ?>
                                <span class="text-xs font-semibold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">Agendada</span>
                            <?php endif; ?>
                            <?php if (!empty($aula['plataforma'])): ?>
                                <span class="text-xs text-gray-500"><?= htmlspecialchars((string) $aula['plataforma']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="font-medium text-gray-900 truncate mt-1"><?= htmlspecialchars((string) ($aula['titulo'] ?? 'Aula online')) ?></p>
                        <?php if ($inicioTs !== false): ?>
                            <p class="text-xs text-gray-500"><?= $aoVivo ? 'Começou' : 'Início' ?>: <?= date('d/m/Y H:i', $inicioTs) ?></p>
                        <?php endif; ?>
                    </div>
                    <a href="<?= URL ?>/admin/aulas-online/chat?id=<?= (int) ($aula['id'] ?? 0) ?>"
                       class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white shadow-sm shrink-0 <?= $aoVivo ? 'bg-rose-600 hover:bg-rose-700' : 'bg-gray-700 hover:bg-gray-800' ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                        <?= $aoVivo ? 'Entrar agora' : 'Abrir sala' ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Panorama Geral -->
<div class="mb-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Panorama Geral da Jornada</h3>
        <p class="text-sm text-gray-500 mb-6">Jornadas no escopo por etapa.</p>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="border border-blue-100 bg-gradient-to-br from-white to-blue-50 rounded-xl p-5 shadow-sm">
                <h4 class="font-semibold text-gray-900 mb-4">Ensino Médio</h4>
                <div class="bg-white rounded-lg border border-blue-100 p-4">
                    <p class="text-xs uppercase tracking-wide text-blue-700 font-semibold">Jornadas</p>
                    <p class="mt-1 text-4xl font-extrabold text-blue-700 leading-none"><?= number_format((int)($panorama_jornada['ensino_medio']['jornadas_escopo'] ?? 0), 0, ',', '.') ?></p>
                </div>
            </div>
            <div class="border border-indigo-100 bg-gradient-to-br from-white to-indigo-50 rounded-xl p-5 shadow-sm">
                <h4 class="font-semibold text-gray-900 mb-4">Ensino Fundamental II</h4>
                <div class="bg-white rounded-lg border border-indigo-100 p-4">
                    <p class="text-xs uppercase tracking-wide text-indigo-700 font-semibold">Jornadas</p>
                    <p class="mt-1 text-4xl font-extrabold text-indigo-700 leading-none"><?= number_format((int)($panorama_jornada['fundamental_ii']['jornadas_escopo'] ?? 0), 0, ',', '.') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Management Menu -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <a href="<?= URL ?>/admin/students" class="bg-white rounded-lg shadow-sm p-6 text-center hover:shadow-md transition-shadow border border-gray-200">
        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
            </svg>
        </div>
        <h3 class="font-medium text-gray-900">Alunos</h3>
        <p class="text-sm text-gray-500">Gerenciar alunos</p>
    </a>

    <a href="<?= URL ?>/admin/teachers" class="bg-white rounded-lg shadow-sm p-6 text-center hover:shadow-md transition-shadow border border-gray-200">
        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
        </div>
        <h3 class="font-medium text-gray-900">Professores</h3>
        <p class="text-sm text-gray-500">Gerenciar professores</p>
    </a>

    <a href="<?= URL ?>/admin/classes" class="bg-white rounded-lg shadow-sm p-6 text-center hover:shadow-md transition-shadow border border-gray-200">
        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
        </div>
        <h3 class="font-medium text-gray-900">Turmas</h3>
        <p class="text-sm text-gray-500">Gerenciar turmas</p>
    </a>

    <a href="<?= URL ?>/admin/journeys" class="bg-white rounded-lg shadow-sm p-6 text-center hover:shadow-md transition-shadow border border-gray-200">
        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
        </div>
        <h3 class="font-medium text-gray-900">Jornadas</h3>
        <p class="text-sm text-gray-500">Gerenciar jornadas</p>
    </a>
</div>

<script>
(() => {
    const countEl = document.getElementById('alunos-online-count');
    if (!countEl) return;

    const applyWsCount = (data) => {
        const escola = window.EDUCATUDO_WS?.escola;
        if (!escola || !data || !data[escola]) return false;
        const alunos = Number(data[escola]?.alunos ?? 0);
        countEl.textContent = Number.isFinite(alunos) ? String(alunos) : '0';
        return true;
    };

    const bindWebsocketPresence = () => {
        if (!window.EDUCATUDO_WS) {
            setTimeout(bindWebsocketPresence, 500);
            return;
        }
        const prevHandler = window.EDUCATUDO_WS.onMasterUpdate;
        window.EDUCATUDO_WS.onMasterUpdate = (data) => {
            if (typeof prevHandler === 'function') prevHandler(data);
            applyWsCount(data);
        };
        if (window.EDUCATUDO_WS.lastUpdate) {
            applyWsCount(window.EDUCATUDO_WS.lastUpdate);
        }
    };
    bindWebsocketPresence();

    const streamUrl = '<?= URL ?>/admin/api/alunos-online/stream';
    const fallbackUrl = '<?= URL ?>/admin/api/alunos-online';

    const updateCount = (payload) => {
        const total = Number(payload?.total ?? 0);
        countEl.textContent = Number.isFinite(total) ? String(total) : '0';
    };

    if ('EventSource' in window) {
        const es = new EventSource(streamUrl);
        es.addEventListener('online', (event) => {
            try { updateCount(JSON.parse(event.data || '{}')); } catch (_) {}
        });
        es.onerror = () => {
            es.close();
            fetch(fallbackUrl, { credentials: 'same-origin' })
                .then((r) => r.json())
                .then((data) => updateCount(data))
                .catch(() => {});
        };
        return;
    }

    fetch(fallbackUrl, { credentials: 'same-origin' })
        .then((r) => r.json())
        .then((data) => updateCount(data))
        .catch(() => {});
})();
</script>
