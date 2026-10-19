<!-- Header Section -->
<div class="mb-8">
    <div class="space-y-3">
        <div>
            <h2 class="text-lg font-bold uppercase tracking-wide text-gray-900 mb-1">
                <?= htmlspecialchars($jornada['titulo']) ?>
            </h2>
            <p class="text-sm text-gray-600">
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'N/A') ?> • <?= !empty($turmas_nomes) ? implode(', ', array_map('htmlspecialchars', $turmas_nomes)) : htmlspecialchars($jornada['turma_nome'] ?? 'N/A') ?>
            </p>
        </div>
        <?php
        $mostrarGerenciarBlocos = $mostrarGerenciarBlocos ?? false;
        $ui = \App\Core\ViewPaths::adminPartial('ui');
        ?>
        <div class="flex flex-wrap gap-2">
            <?php
            $ui_btn_variant = 'complementar';
            $ui_btn_label = 'Voltar';
            $ui_btn_icon = 'fa-solid fa-arrow-left';
            $ui_btn_href = URL . '/professor/jornadas';
            $ui_btn_type = 'button';
            $ui_btn_onclick = '';
            $ui_btn_id = '';
            $ui_btn_class = '';
            $ui_btn_attrs = 'title="Voltar"';
            $ui_btn_filter_count = 0;
            include $ui . '/btn.php';

            $ui_btn_variant = 'complementar';
            $ui_btn_label = 'Editar';
            $ui_btn_icon = 'fa-solid fa-pen';
            $ui_btn_href = URL . '/professor/jornadas/' . (int) $jornada['id'] . '/editar';
            $ui_btn_attrs = 'title="Editar"';
            include $ui . '/btn.php';

            $ui_btn_variant = 'complementar';
            $ui_btn_label = 'Resultados';
            $ui_btn_icon = 'fa-solid fa-file-lines';
            $ui_btn_href = URL . '/professor/jornadas/' . (int) $jornada['id'] . '/exercicios-alunos';
            $ui_btn_attrs = 'title="Resultados"';
            include $ui . '/btn.php';

            $ui_btn_variant = 'complementar';
            $ui_btn_label = 'Preview';
            $ui_btn_icon = 'fa-solid fa-eye';
            $ui_btn_href = URL . '/jornadas/' . (int) $jornada['id'] . '?preview=1';
            $ui_btn_attrs = 'target="_blank" rel="noopener noreferrer" title="Preview da jornada (como o aluno vê)"';
            include $ui . '/btn.php';

            if ($mostrarGerenciarBlocos):
                $ui_btn_variant = 'primary';
                $ui_btn_label = 'Blocos da Jornada';
                $ui_btn_icon = 'fa-solid fa-layer-group';
                $ui_btn_href = URL . '/professor/jornadas/' . (int) $jornada['id'] . '/modulos';
                $ui_btn_attrs = 'title="Blocos da Jornada"';
                include $ui . '/btn.php';
            endif;
            ?>
        </div>
    </div>
</div>

<!-- Status Badge -->
<div class="mb-6">
    <span class="px-4 py-2 text-sm font-semibold rounded-full
        <?php 
        $statusExibir = $jornada['status_jornada'] ?? 'em_andamento';
        switch($statusExibir) {
            case 'aguardando': echo 'bg-yellow-100 text-yellow-800'; break;
            case 'em_andamento': echo 'bg-blue-100 text-blue-800'; break;
            case 'concluido': echo 'bg-green-100 text-green-800'; break;
            default: echo 'bg-blue-100 text-blue-800';
        }
        ?>">
        <?php
        $labels = [
            'aguardando' => 'Aguardando',
            'em_andamento' => 'Em Andamento',
            'concluido' => 'Concluído'
        ];
        echo $labels[$statusExibir] ?? 'Em Andamento';
        ?>
    </span>
</div>

<!-- Dashboard de Exercícios -->
<div class="mb-8 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow-lg border border-blue-200 p-6">
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Dashboard de Exercícios
            </h3>
            <p class="text-sm text-gray-600 mt-1">Estatísticas e análise de desempenho dos alunos</p>
        </div>
        <div class="bg-white rounded-lg px-4 py-3 shadow-sm border border-blue-100 shrink-0">
            <p class="text-xs text-gray-500">Total de exercícios com respostas</p>
            <p class="text-2xl font-bold text-blue-600 leading-tight"><?= (int) ($stats_exercicios['total_exercicios_com_respostas'] ?? 0) ?></p>
        </div>
    </div>
    <?php if (($stats_exercicios['total_exercicios_com_respostas'] ?? 0) === 0): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 flex items-start gap-3">
            <svg class="w-5 h-5 text-yellow-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-sm text-yellow-800">
                As métricas aparecerão aqui assim que os alunos começarem a fazer os exercícios.
            </p>
        </div>
    <?php endif; ?>
    
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
                    <?php foreach ($stats_exercicios['mais_acertados'] as $index => $ex): 
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
                    <?php foreach ($stats_exercicios['mais_errados'] as $index => $ex): 
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
    <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-xl p-6 shadow-md mb-6">
        <div class="flex items-start gap-3 mb-4">
            <svg class="w-6 h-6 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div class="flex-1">
                <h4 class="text-lg font-semibold text-yellow-900 mb-2">⚠️ Alunos que Precisam de Atenção</h4>
                <p class="text-sm text-yellow-800 mb-4">
                    Alunos com mais de <strong>40% de taxa de erro</strong> nos exercícios da jornada:
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($alunos_atencao as $aluno):
                        if ((int) ($aluno['total_exercicios'] ?? 0) <= 0) continue;
                    ?>
                        <div class="bg-white rounded-lg p-4 border border-yellow-200">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($aluno['nome'] ?? 'Sem nome') ?></p>
                                    <p class="text-xs text-gray-500">RA: <?= htmlspecialchars($aluno['ra'] ?? 'N/A') ?></p>
                                </div>
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold">
                                    <?= round($aluno['taxa_erro'] ?? 0, 1) ?>% erro
                                </span>
                            </div>
                            <div class="mt-3 pt-3 border-t border-yellow-200">
                                <div class="flex items-center gap-4 text-xs text-gray-600">
                                    <span>✓ <?= $aluno['total_acertos'] ?? 0 ?> acertos</span>
                                    <span>✗ <?= $aluno['total_erros'] ?? 0 ?> erros</span>
                                </div>
                                <div class="mt-2 text-xs text-gray-500">
                                    <span>📊 <?= $aluno['total_exercicios'] ?? 0 ?> exercícios feitos</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    <?php 
                                    $alunoId = (int)($aluno['id'] ?? 0);
                                    if ($alunoId > 0): 
                                    ?>
                                    <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/aluno/<?= $alunoId ?>/exercicios" 
                                       class="w-full bg-green-500 text-white px-3 py-2 rounded-lg hover:bg-green-600 transition-all duration-200 flex items-center justify-center gap-2 text-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                        Ver Resultados
                                    </a>
                                    <?php else: ?>
                                    <span class="w-full bg-gray-300 text-gray-600 px-3 py-2 rounded-lg flex items-center justify-center gap-2 text-sm cursor-not-allowed">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                        </svg>
                                        ID não disponível
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
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
    var base = <?= json_encode(rtrim((string) URL, '/') . '/professor/jornadas/' . (int) $jornada['id'] . '/aluno/') ?>;

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

<!-- Main Content -->
<div class="space-y-6">
        <!-- Descrição -->
        <?php if (!empty($jornada['descricao'])): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Descrição</h3>
                <p class="text-gray-600"><?= nl2br(htmlspecialchars($jornada['descricao'] ?? '')) ?></p>
            </div>
        <?php endif; ?>

        <!-- Objetivos -->
        <?php if (!empty($jornada['objetivos'])): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Objetivos de Aprendizagem</h3>
                <p class="text-gray-600"><?= nl2br(htmlspecialchars($jornada['objetivos'] ?? '')) ?></p>
            </div>
        <?php endif; ?>

        <!-- Critérios de Avaliação -->
        <?php if (!empty($jornada['criterios_avaliacao'])): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Critérios de Avaliação</h3>
                <p class="text-gray-600"><?= nl2br(htmlspecialchars($jornada['criterios_avaliacao'] ?? '')) ?></p>
            </div>
        <?php endif; ?>

    </div>
</div>

