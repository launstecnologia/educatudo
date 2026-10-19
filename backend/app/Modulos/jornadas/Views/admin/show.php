<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                <?= htmlspecialchars($jornada['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Prof. <?= htmlspecialchars($jornada['professor_nome']) ?> • 
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?> • 
                <?php 
                $turmasNomes = $jornada['turmas_selecionadas_nomes'] ?? [$jornada['turma_nome'] ?? ''];
                $turmasNomes = array_filter($turmasNomes);
                echo htmlspecialchars(!empty($turmasNomes) ? implode(', ', $turmasNomes) : ($jornada['turma_nome'] ?? '—'));
                ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/jornadas" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
            <a href="<?= URL ?>/admin/jornadas/<?= $jornada['id'] ?>/modulos" 
               class="bg-purple-600 text-white px-6 py-3 rounded-xl hover:bg-purple-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                Gerenciar Módulos
            </a>
            <a href="<?= URL ?>/admin/jornadas/<?= $jornada['id'] ?>/exercicios-alunos" 
               class="bg-orange-600 text-white px-6 py-3 rounded-xl hover:bg-orange-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Resultados da Jornada
            </a>
        </div>
    </div>
</div>

<!-- Status Badge -->
<div class="mb-6">
    <span class="px-4 py-2 text-sm font-semibold rounded-full
        <?php 
        switch($jornada['status']) {
            case 'ativa': echo 'bg-green-100 text-green-800'; break;
            case 'pausada': echo 'bg-yellow-100 text-yellow-800'; break;
            case 'finalizada': echo 'bg-gray-100 text-gray-800'; break;
            default: echo 'bg-blue-100 text-blue-800';
        }
        ?>">
        <?= ucfirst($jornada['status']) ?>
    </span>
</div>

<!-- Stats Overview -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border border-purple-200">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-600">Alunos</p>
                <p class="text-2xl font-bold text-gray-900"><?= (int)($jornada['total_alunos_jornada'] ?? (count($alunos))) ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-green-200">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <span class="w-3 h-3 bg-green-500 rounded-full"></span>
            </div>
            <div>
                <p class="text-sm text-gray-600">Realizou Jornada</p>
                <p class="text-2xl font-bold text-green-600"><?= (int)($jornada['realizou_count'] ?? 0) ?> aluno(s)</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-red-200">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                <span class="w-3 h-3 bg-red-500 rounded-full"></span>
            </div>
            <div>
                <p class="text-sm text-gray-600">Não realizou Jornada</p>
                <p class="text-2xl font-bold text-red-600"><?= (int)($jornada['nao_realizou_count'] ?? 0) ?> aluno(s)</p>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard de Exercícios (como no professor) -->
<div class="mb-8 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow-lg border border-blue-200 p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Dashboard de Exercícios
            </h3>
            <p class="text-sm text-gray-600 mt-1">Estatísticas e análise de desempenho dos alunos</p>
        </div>
        <div class="bg-white rounded-lg px-4 py-2 shadow-sm">
            <span class="text-sm text-gray-600">Total de exercícios com respostas:</span>
            <span class="text-lg font-bold text-blue-600 ml-2"><?= $stats_exercicios['total_exercicios_com_respostas'] ?? 0 ?></span>
        </div>
    </div>
    <?php if (($stats_exercicios['total_exercicios_com_respostas'] ?? 0) === 0): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-sm text-yellow-800">
                As métricas aparecerão aqui assim que os alunos começarem a fazer os exercícios.
            </p>
        </div>
    <?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Exercícios Mais Acertados -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Exercícios Mais Acertados
            </h4>
            <?php if (!empty($stats_exercicios['mais_acertados'])): ?>
                <div class="space-y-3 max-h-[400px] overflow-y-auto">
                    <?php foreach ($stats_exercicios['mais_acertados'] as $ex): 
                        if ((float)($ex['taxa_acerto'] ?? 0) <= 0) continue;
                    ?>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($ex['titulo'] ?? 'Sem título') ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($ex['modulo_nome'] ?? '') ?></p>
                                </div>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-semibold">
                                    <?= round((float)($ex['taxa_acerto'] ?? 0), 1) ?>%
                                </span>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-gray-600 mt-2">
                                <span>✓ <?= (int)($ex['total_acertos'] ?? 0) ?> acertos</span>
                                <span>✗ <?= (int)($ex['total_erros'] ?? 0) ?> erros</span>
                                <span>📊 <?= (int)($ex['total_respostas'] ?? 0) ?> respostas</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm">Nenhum exercício com respostas ainda</p>
            <?php endif; ?>
        </div>
        <!-- Exercícios Mais Errados -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Exercícios Mais Errados
            </h4>
            <?php if (!empty($stats_exercicios['mais_errados'])): ?>
                <div class="space-y-3 max-h-[400px] overflow-y-auto">
                    <?php foreach ($stats_exercicios['mais_errados'] as $ex): 
                        if ((float)($ex['taxa_erro'] ?? 0) <= 0) continue;
                        $alunosErraram = array_values($ex['alunos_erraram'] ?? []);
                        $payloadAlunosErro = htmlspecialchars(json_encode([
                            'titulo' => $ex['titulo'] ?? 'Sem título',
                            'modulo' => $ex['modulo_nome'] ?? '',
                            'alunos' => $alunosErraram,
                        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                    ?>
                        <button type="button"
                                class="w-full text-left bg-gray-50 rounded-lg p-4 border border-gray-200 hover:border-red-300 hover:bg-red-50/50 transition-colors cursor-pointer"
                                data-alunos-erro="<?= $payloadAlunosErro ?>"
                                title="Ver alunos que erraram">
                            <div class="flex items-start justify-between mb-2 gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($ex['titulo'] ?? 'Sem título') ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($ex['modulo_nome'] ?? '') ?></p>
                                </div>
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold shrink-0">
                                    <?= round((float)($ex['taxa_erro'] ?? 0), 1) ?>%
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 mt-2">
                                <span>✓ <?= (int)($ex['total_acertos'] ?? 0) ?> acertos</span>
                                <span>✗ <?= (int)($ex['total_erros'] ?? 0) ?> erros</span>
                                <span>📊 <?= (int)($ex['total_respostas'] ?? 0) ?> respostas</span>
                            </div>
                            <p class="text-xs font-medium text-red-700 mt-3">Ver alunos que erraram (<?= count($alunosErraram) ?>)</p>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm">Nenhum exercício com respostas ainda</p>
            <?php endif; ?>
        </div>
    </div>
    <!-- Alunos que Precisam de Atenção -->
    <?php if (!empty($alunos_atencao)): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-xl p-6 shadow-md">
        <h4 class="text-lg font-semibold text-yellow-900 mb-2">⚠️ Alunos que Precisam de Atenção</h4>
        <p class="text-sm text-yellow-800 mb-4">Alunos com mais de <strong>40% de taxa de erro</strong> nos exercícios da jornada:</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($alunos_atencao as $aluno):
                if ((int) ($aluno['total_exercicios'] ?? 0) <= 0) continue;
            ?>
                <div class="bg-white rounded-lg p-4 border border-yellow-200">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 text-sm truncate" title="<?= htmlspecialchars($aluno['nome'] ?? 'Sem nome') ?>"><?= htmlspecialchars($aluno['nome'] ?? 'Sem nome') ?></p>
                            <p class="text-xs text-gray-500 truncate">Cód Aluno: <?= htmlspecialchars($aluno['ra'] ?? 'N/A') ?></p>
                        </div>
                        <span class="shrink-0 px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold">
                            <?= round($aluno['taxa_erro'] ?? 0, 1) ?>% erro
                        </span>
                    </div>
                    <div class="mt-3 pt-3 border-t border-yellow-200 text-xs text-gray-600">
                        <span>✓ <?= $aluno['total_acertos'] ?? 0 ?> acertos</span>
                        <span class="mx-2">✗ <?= $aluno['total_erros'] ?? 0 ?> erros</span>
                        <span>📊 <?= $aluno['total_exercicios'] ?? 0 ?> exercícios feitos</span>
                    </div>
                    <div class="mt-3">
                        <a href="<?= URL ?>/admin/jornadas/<?= $jornada['id'] ?>/exercicios-alunos" 
                           class="inline-block w-full text-center bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 text-sm">
                            Ver Resultados da Jornada
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div id="modalAlunosErro" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="modalAlunosErroTitulo">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[80vh] flex flex-col">
        <div class="flex items-start justify-between gap-4 p-5 border-b border-gray-200">
            <div class="min-w-0">
                <h3 id="modalAlunosErroTitulo" class="text-lg font-semibold text-gray-900"></h3>
                <p id="modalAlunosErroModulo" class="text-sm text-gray-500 mt-1"></p>
            </div>
            <button type="button" id="modalAlunosErroFechar" class="text-gray-400 hover:text-gray-700 text-2xl leading-none" aria-label="Fechar">&times;</button>
        </div>
        <div id="modalAlunosErroLista" class="p-5 overflow-y-auto space-y-2"></div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('modalAlunosErro');
    if (!modal) return;
    var titulo = document.getElementById('modalAlunosErroTitulo');
    var modulo = document.getElementById('modalAlunosErroModulo');
    var lista = document.getElementById('modalAlunosErroLista');
    var base = <?= json_encode(rtrim((string) URL, '/') . '/admin/jornadas/' . (int) $jornada['id'] . '/aluno/') ?>;

    function fechar() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function abrir(dados) {
        titulo.textContent = dados.titulo || 'Exercício';
        modulo.textContent = dados.modulo || '';
        lista.replaceChildren();
        var alunos = Array.isArray(dados.alunos) ? dados.alunos : [];
        if (alunos.length === 0) {
            var vazio = document.createElement('p');
            vazio.className = 'text-sm text-gray-500';
            vazio.textContent = 'Nenhum aluno com erro neste exercício.';
            lista.appendChild(vazio);
        } else {
            alunos.forEach(function (aluno) {
                var item = document.createElement('a');
                item.className = 'flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50';
                item.href = base + encodeURIComponent(String(aluno.id || '')) + '/exercicios';
                var nome = document.createElement('span');
                nome.className = 'text-sm font-medium text-gray-900';
                nome.textContent = aluno.nome || 'Sem nome';
                var ra = document.createElement('span');
                ra.className = 'text-xs text-gray-500 shrink-0';
                ra.textContent = aluno.ra ? ('RA: ' + aluno.ra) : '';
                item.appendChild(nome);
                item.appendChild(ra);
                lista.appendChild(item);
            });
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    document.querySelectorAll('[data-alunos-erro]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try {
                abrir(JSON.parse(btn.getAttribute('data-alunos-erro') || '{}'));
            } catch (e) {}
        });
    });
    document.getElementById('modalAlunosErroFechar').addEventListener('click', fechar);
    modal.addEventListener('click', function (ev) {
        if (ev.target === modal) fechar();
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') fechar();
    });
})();
</script>

<!-- Informações -->
<div class="tab-content">
    <div>
        <?php $estrutura = json_decode($jornada['estrutura'] ?? '{}', true) ?: []; ?>
        <?php if (!empty($estrutura['criterios_avaliacao'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Critérios de Avaliação</h3>
            <p class="text-gray-600"><?= nl2br(htmlspecialchars($estrutura['criterios_avaliacao'])) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações Gerais</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Data de Criação</p>
                    <p class="font-semibold text-gray-900"><?= date('d/m/Y H:i', strtotime($jornada['created_at'])) ?></p>
                </div>
                <?php if (!empty($estrutura['data_inicio'])): ?>
                <div>
                    <p class="text-sm text-gray-600">Data de Início</p>
                    <?php 
                    $horaInicio = trim($estrutura['hora_inicio'] ?? '') ?: '00:00';
                    $datetimeInicio = $estrutura['data_inicio'] . ' ' . $horaInicio;
                    ?>
                    <p class="font-semibold text-gray-900"><?= date('d/m/Y H:i', strtotime($datetimeInicio)) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($estrutura['data_fim'])): ?>
                <div>
                    <p class="text-sm text-gray-600">Data de Fim</p>
                    <?php 
                    $horaFim = trim($estrutura['hora_fim'] ?? '') ?: '23:59';
                    $datetimeFim = $estrutura['data_fim'] . ' ' . $horaFim;
                    ?>
                    <p class="font-semibold text-gray-900"><?= date('d/m/Y H:i', strtotime($datetimeFim)) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


