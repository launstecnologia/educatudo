<!-- Student Info -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
    <div class="p-6 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <span class="text-2xl font-medium text-purple-600"><?= strtoupper(substr($filho['nome'] ?? '', 0, 2)) ?></span>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($filho['nome'] ?? '') ?></h2>
                    <p class="text-gray-600"><?= htmlspecialchars($filho['turma_nome'] ?? '') ?> - <?= htmlspecialchars($filho['serie'] ?? '') ?></p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">RA</p>
                <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($filho['ra'] ?? '') ?></p>
            </div>
        </div>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <p class="text-sm text-gray-500 mb-1">Email</p>
                <p class="text-gray-900"><?= htmlspecialchars($filho['email'] ?? 'Não informado') ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">Status</p>
                <span class="inline-block bg-green-100 text-green-600 text-xs px-3 py-1 rounded-full font-semibold"><?= ($filho['ativo'] ?? 0) ? 'Ativo' : 'Inativo' ?></span>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">Turma</p>
                <p class="text-gray-900"><?= htmlspecialchars($filho['turma_nome'] ?? 'Sem turma') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="border-b border-gray-200">
        <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
            <button onclick="showTab('jornadas')" id="tab-jornadas" class="tab-button active flex items-center px-6 py-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600 whitespace-nowrap">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                Jornadas
            </button>
            <button onclick="showTab('redacoes')" id="tab-redacoes" class="tab-button flex items-center px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Redações
            </button>
            <button onclick="showTab('ocorrencias')" id="tab-ocorrencias" class="tab-button flex items-center px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Ocorrências
            </button>
        </nav>
    </div>

    <div class="p-6">
        <div id="content-jornadas" class="tab-content">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Jornadas</h3>
                <span class="text-sm text-gray-500"><?= count($jornadas ?? []) ?> jornadas encontradas</span>
            </div>
            <?php if (empty($jornadas)): ?>
                <div class="text-center py-12 bg-gray-50 rounded-lg">
                    <p class="text-gray-500">Nenhuma jornada encontrada</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($jornadas as $jornada): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-gray-900 mb-2"><?= htmlspecialchars($jornada['titulo'] ?? 'Jornada') ?></h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div><span class="text-gray-500">Professor:</span> <span class="font-medium text-gray-900 ml-1"><?= htmlspecialchars($jornada['professor_nome'] ?? 'N/A') ?></span></div>
                                <div><span class="text-gray-500">Criada em:</span> <span class="font-medium text-gray-900 ml-1"><?= date('d/m/Y', strtotime($jornada['created_at'])) ?></span></div>
                            </div>
                            <?php if (!empty($jornada['descricao'])): ?>
                                <p class="text-sm text-gray-600 mt-2"><?= htmlspecialchars(substr($jornada['descricao'], 0, 200)) ?><?= strlen($jornada['descricao']) > 200 ? '...' : '' ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="content-redacoes" class="tab-content hidden">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Redações</h3>
                <span class="text-sm text-gray-500"><?= count($redacoes ?? []) ?> redações encontradas</span>
            </div>
            <?php if (empty($redacoes)): ?>
                <div class="text-center py-12 bg-gray-50 rounded-lg">
                    <p class="text-gray-500">Nenhuma redação encontrada</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($redacoes as $redacao): ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-gray-900 mb-2"><?= htmlspecialchars($redacao['tema'] ?? $redacao['titulo'] ?? 'Redação') ?></h4>
                            <div class="text-sm">
                                <span class="text-gray-500">Criada em:</span> <span class="font-medium text-gray-900 ml-1"><?= date('d/m/Y H:i', strtotime($redacao['created_at'])) ?></span>
                                <?php if (!empty($redacao['nota']) || !empty($redacao['nota_final'])): ?>
                                    <span class="ml-4 text-blue-600">Nota: <?= number_format($redacao['nota'] ?? $redacao['nota_final'], 1) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="content-ocorrencias" class="tab-content hidden">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Ocorrências</h3>
                <span class="text-sm text-gray-500"><?= count($ocorrencias ?? []) ?> registros</span>
            </div>
            <?php if (empty($ocorrencias)): ?>
                <div class="text-center py-12 bg-gray-50 rounded-lg">
                    <p class="text-gray-500">Nenhuma ocorrência disponível.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($ocorrencias as $oc): ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-base font-semibold text-gray-900"><?= htmlspecialchars($oc['titulo'] ?? '') ?></h4>
                                    <p class="text-xs text-gray-500 mt-1"><?= date('d/m/Y H:i', strtotime($oc['data_ocorrencia'])) ?></p>
                                </div>
                                <span class="text-xs font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-700"><?= ucfirst($oc['nivel_gravidade'] ?? '') ?></span>
                            </div>
                            <p class="text-sm text-gray-700 mt-3"><?= htmlspecialchars($oc['detalhe'] ?? '') ?></p>
                            <div class="text-xs text-gray-500 mt-3 flex flex-wrap gap-4">
                                <div>Atitude: <?= $oc['atitude_coordenacao'] ? ucfirst($oc['atitude_coordenacao']) : '-' ?></div>
                                <div>Retorno: <?= !empty($oc['retorno_em']) ? date('d/m/Y', strtotime($oc['retorno_em'])) : '-' ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.add('hidden'); });
    document.querySelectorAll('.tab-button').forEach(function(b) {
        b.classList.remove('active', 'border-blue-500', 'text-blue-600');
        b.classList.add('border-transparent', 'text-gray-500');
    });
    var content = document.getElementById('content-' + tabName);
    if (content) content.classList.remove('hidden');
    var button = document.getElementById('tab-' + tabName);
    if (button) {
        button.classList.add('active', 'border-blue-500', 'text-blue-600');
        button.classList.remove('border-transparent', 'text-gray-500');
    }
}
</script>
