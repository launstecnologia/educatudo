<!-- Header Section -->
<div class="mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Minhas Provas 📝</h1>
        <p class="text-gray-600 mt-2">Visualize e realize suas provas online.</p>
    </div>
</div>

<?php if (!empty($flash_message)): ?>
<div class="mb-6 p-4 rounded-lg border-2 <?= ($flash_type ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-800' ?>">
    <p class="font-medium"><?= htmlspecialchars($flash_message) ?></p>
</div>
<?php endif; ?>

<!-- Provas Individuais -->
<?php
$temProvasIndividuais = isset($provas) && is_array($provas) && count($provas) > 0;
$temBlocos = isset($blocosComProvas) && is_array($blocosComProvas) && count($blocosComProvas) > 0;
?>
<?php if ($temProvasIndividuais): ?>
    <div class="mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">
            Provas Individuais
            <span class="text-sm font-normal text-gray-500">(<?= count($provas) ?> prova(s))</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($provas as $prova): ?>
                <?php
                $provaId = (int) ($prova['id'] ?? 0);
                $provaAcessoId = !empty($prova['ei_adapted_prova_id']) ? (int) $prova['ei_adapted_prova_id'] : $provaId;
                $status = (string) ($prova['realizacao_status'] ?? '');
                $finalizada = $status === 'finalizado';
                $iniciada = $status === 'iniciado';
                $cancelada = $status === 'cancelada';
                $dataInicio = !empty($prova['data_inicio']) ? strtotime((string) $prova['data_inicio']) : null;
                $dataFim = !empty($prova['data_fim']) ? strtotime((string) $prova['data_fim']) : null;
                ?>
                <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition-shadow">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-gray-900">
                                <?= htmlspecialchars((string) ($prova['titulo'] ?? 'Prova')) ?>
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">
                                <?= htmlspecialchars((string) ($prova['materia_nome'] ?? '')) ?>
                                <?= !empty($prova['turma_nome']) ? ' · ' . htmlspecialchars((string) $prova['turma_nome']) : '' ?>
                            </p>
                        </div>
                        <?php if (!empty($prova['ei_adapted'])): ?>
                            <span class="shrink-0 px-2 py-1 rounded-full bg-purple-50 text-purple-700 border border-purple-200 text-[11px] font-semibold">
                                EducaInclui
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($prova['descricao'])): ?>
                        <p class="text-sm text-gray-600 mb-4"><?= htmlspecialchars((string) $prova['descricao']) ?></p>
                    <?php endif; ?>

                    <div class="space-y-2 mb-5 text-sm text-gray-700">
                        <?php if ($dataInicio): ?>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <?= date('d/m/Y H:i', $dataInicio) ?>
                            <?= $dataFim ? ' até ' . date('d/m/Y H:i', $dataFim) : '' ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($prova['tempo_limite'])): ?>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <?= (int) $prova['tempo_limite'] ?> minuto(s)
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($finalizada): ?>
                        <a href="<?= URL ?>/aluno/provas/resultado/<?= $provaId ?>" class="block w-full bg-green-600 text-white text-center py-3 rounded-lg hover:bg-green-700 font-medium">
                            Visualizar resultado
                        </a>
                    <?php elseif ($cancelada): ?>
                        <span class="block w-full bg-amber-100 text-amber-800 text-center py-3 rounded-lg font-medium cursor-not-allowed border border-amber-300">
                            Cancelada – aguarde liberação
                        </span>
                    <?php else: ?>
                        <a href="<?= URL ?>/aluno/provas/realizar/<?= $provaAcessoId ?>" data-iniciar-prova class="block w-full bg-indigo-700 text-white text-center py-3 rounded-lg hover:bg-indigo-800 font-medium border-2 border-indigo-800 no-underline">
                            <?= $iniciada ? 'Continuar prova' : 'Iniciar prova' ?>
                        </a>
                        <?php if (!empty($prova['ei_adapted'])): ?>
                            <p class="text-xs text-purple-700 text-center mt-2">Você receberá a versão adaptada aprovada pelo EducaInclui.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif (!$temBlocos): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-8 text-center">
        <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
            <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M8 4h8l4 4v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Nenhuma prova disponível</h3>
        <p class="text-gray-500">Quando houver uma prova liberada para sua turma, ela aparecerá aqui.</p>
    </div>
<?php endif; ?>

<?php if (!empty($bloco_diag) && is_array($bloco_diag)): ?>
<div class="mb-6 p-4 rounded-lg border-2 border-slate-300 bg-slate-50 text-slate-800 text-sm">
    <p class="font-bold mb-2">Diagnóstico de blocos (?debug=1)</p>
    <?php $tp = $bloco_diag['turma_principal'] ?? []; ?>
    <p>Aluno #<?= (int) $bloco_diag['aluno_id'] ?> — turma principal:
        <strong><?= htmlspecialchars((string) ($tp['turma_nome'] ?? '—')) ?></strong>
        (id <?= (int) ($tp['turma_id'] ?? 0) ?>)
    </p>
    <p>Matrículas ativas:
        <?php if (!empty($bloco_diag['matriculas'])): ?>
            <?php foreach ($bloco_diag['matriculas'] as $m): ?>
                <span class="inline-block bg-white border border-slate-300 rounded px-2 py-0.5 mr-1">
                    <?= htmlspecialchars((string) ($m['turma_nome'] ?? '')) ?> (id <?= (int) ($m['turma_id'] ?? 0) ?>)
                </span>
            <?php endforeach; ?>
        <?php else: ?> nenhuma <?php endif; ?>
    </p>
    <p class="mt-1">Turmas consideradas do aluno (IDs): <strong><?= htmlspecialchars(implode(', ', array_map('strval', $bloco_diag['turma_ids_aluno'] ?? []))) ?></strong></p>
    <p class="mt-1">Coluna <code>visivel_no_portal_aluno</code> existe: <strong><?= !empty($bloco_diag['has_visivel_col']) ? 'sim' : 'não' ?></strong></p>
    <table class="mt-3 w-full text-xs border-collapse">
        <thead>
            <tr class="text-left border-b border-slate-300">
                <th class="py-1 pr-2">Bloco</th>
                <th class="py-1 pr-2">liberado</th>
                <th class="py-1 pr-2">ativo</th>
                <th class="py-1 pr-2">visível</th>
                <th class="py-1 pr-2">Turmas do bloco (IDs)</th>
                <th class="py-1 pr-2">Match</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bloco_diag['blocos'] as $b): ?>
            <tr class="border-b border-slate-200 align-top">
                <td class="py-1 pr-2">#<?= (int) $b['id'] ?> <?= htmlspecialchars((string) $b['titulo']) ?></td>
                <td class="py-1 pr-2"><?= (int) $b['liberado'] ?></td>
                <td class="py-1 pr-2"><?= (int) $b['ativo'] ?></td>
                <td class="py-1 pr-2"><?= $b['visivel'] === null ? '—' : (int) $b['visivel'] ?></td>
                <td class="py-1 pr-2">
                    <?php foreach ($b['turmas'] as $t): ?>
                        <?= htmlspecialchars((string) ($t['turma_nome'] ?? '')) ?> (<?= (int) ($t['turma_id'] ?? 0) ?>);
                    <?php endforeach; ?>
                    <?php if (empty($b['turmas'])): ?> (sem turmas demarcadas) <?php endif; ?>
                </td>
                <td class="py-1 pr-2 font-bold <?= !empty($b['match']) ? 'text-green-700' : 'text-red-700' ?>">
                    <?= !empty($b['match']) ? ('SIM (' . implode(',', $b['match']) . ')') : 'NÃO' ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Blocos de Provas -->
<?php 
$temBlocos = isset($blocosComProvas) && is_array($blocosComProvas) && count($blocosComProvas) > 0;
?>
<?php if ($temBlocos): ?>
    <div class="mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">
            Blocos de Provas 
            <span class="text-sm font-normal text-gray-500">(<?= count($blocosComProvas) ?> bloco(s))</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($blocosComProvas as $idx => $bloco): ?>
                <?php 
                // Debug: verificar estrutura
                if (!isset($bloco['id'])) {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG: Bloco #{$idx} sem ID: " . print_r($bloco, true));
                    }
                    continue;
                }
                ?>
                <?php 
                // Debug individual do bloco
                if (defined('DEBUG') && DEBUG) {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG View: Renderizando bloco ID " . ($bloco['id'] ?? 'N/A') . " - " . ($bloco['titulo'] ?? 'Sem título'));
                    }
                }
                ?>
                <div class="bg-gradient-to-br from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-xl p-6 hover:shadow-lg transition-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">
                            <?= htmlspecialchars($bloco['titulo']) ?>
                        </h3>
                        <span class="px-3 py-1 bg-purple-600 text-white text-xs font-semibold rounded-full">
                            <?= count($bloco['provas'] ?? []) ?> prova(s)
                        </span>
                    </div>
                    
                    <?php if ($bloco['descricao']): ?>
                        <p class="text-sm text-gray-600 mb-4"><?= htmlspecialchars($bloco['descricao']) ?></p>
                    <?php endif; ?>
                    
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center text-sm text-gray-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <?= date('d/m/Y', strtotime($bloco['data_prova'])) ?>
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <?= date('H:i', strtotime($bloco['hora_inicio'])) ?> - <?= date('H:i', strtotime($bloco['hora_fim'])) ?>
                        </div>
                    </div>
                    
                    <!-- Lista de Provas do Bloco -->
                    <?php if (!empty($bloco['provas'])): ?>
                    <div class="mb-4">
                        <p class="text-xs font-semibold text-gray-700 mb-2">Provas deste bloco:</p>
                        <div class="space-y-2">
                            <?php foreach ($bloco['provas'] as $prova): ?>
                                <div class="bg-white rounded-lg p-3 border border-purple-100">
                                    <div class="flex items-center justify-between gap-3 flex-wrap">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($prova['materia_nome'] ?? $prova['titulo']) ?></p>
                                            <p class="text-xs text-gray-500"><?= !empty($prova['professor_nome']) ? 'Prof. ' . htmlspecialchars($prova['professor_nome']) : '—' ?></p>
                                        </div>
                                        <?php if (isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'finalizado'): ?>
                                            <span class="text-xs text-green-600 font-semibold">✓ Finalizada</span>
                                        <?php elseif (isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'iniciado'): ?>
                                            <span class="text-xs text-yellow-600 font-semibold">Em andamento</span>
                                        <?php elseif (isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'cancelada'): ?>
                                            <span class="text-xs text-amber-600 font-semibold">Cancelada – aguarde liberação</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <p class="text-xs text-yellow-800">Não há prova disponível para a sua turma neste bloco.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($bloco['provas'])): ?>
                    <?php 
                    $todasFinalizadas = !empty($bloco['todas_finalizadas']);
                    $algumaCancelada = !empty($bloco['alguma_cancelada']);
                    $dentroPeriodo = !empty($bloco['dentro_periodo']);
                    $disponivelEm = $bloco['disponivel_em'] ?? null;
                    $fimBloco = (!empty($bloco['data_prova']) && !empty($bloco['hora_fim'])) ? strtotime($bloco['data_prova'] . ' ' . $bloco['hora_fim']) : 0;
                    $blocoTerminou = $fimBloco > 0 && time() >= $fimBloco;
                    $blocoLiberado = !empty($bloco['liberado']) && !empty($bloco['ativo']);
                    ?>
                    <div class="space-y-2">
                        <?php if (!$blocoLiberado && !$todasFinalizadas): ?>
                            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-center font-medium">
                                Aguardando liberação pelo coordenador. O bloco aparecerá aqui quando estiver disponível.
                            </p>
                            <span class="block w-full bg-gray-400 text-white text-center py-3 rounded-lg font-medium cursor-not-allowed opacity-75">
                                Bloco não liberado
                            </span>
                        <?php elseif (!$blocoLiberado && $todasFinalizadas): ?>
                            <?php if (!empty($bloco['gabarito_liberado'])): ?>
                            <a href="<?= URL ?>/aluno/provas/bloco/<?= $bloco['id'] ?>/resultados" 
                               class="block w-full bg-green-600 text-white text-center py-3 rounded-lg hover:bg-green-700 font-medium">
                                Visualizar resposta
                            </a>
                            <?php else: ?>
                            <div>
                                <span class="block w-full bg-gray-400 text-white text-center py-3 rounded-lg font-medium cursor-not-allowed opacity-90">
                                    Gabarito aguardando liberação
                                </span>
                                <p class="text-xs text-gray-500 text-center mt-1">A coordenação ainda não liberou o gabarito deste bloco.</p>
                            </div>
                            <?php endif; ?>
                        <?php elseif ($blocoLiberado && !empty($disponivelEm)): ?>
                            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-center font-medium">
                                ⏳ Aguardando liberação — Disponível em <?= date('d/m/Y', strtotime($disponivelEm)) ?> às <?= date('H:i', strtotime($disponivelEm)) ?>
                            </p>
                            <span class="block w-full bg-gray-400 text-white text-center py-3 rounded-lg font-medium cursor-not-allowed opacity-75">
                                Iniciar prova (aguarde o horário)
                            </span>
                        <?php elseif ($blocoLiberado && $algumaCancelada): ?>
                            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-center font-medium">
                                Cancelada – aguarde liberação do coordenador
                            </p>
                            <span class="block w-full bg-amber-100 text-amber-800 text-center py-3 rounded-lg font-medium cursor-not-allowed border border-amber-300 pointer-events-none">
                                Cancelada
                            </span>
                        <?php elseif ($blocoLiberado && $dentroPeriodo && !$todasFinalizadas): ?>
                            <?php $urlIniciar = URL . '/aluno/provas/bloco/' . (int)$bloco['id'] . '/iniciar-seguro'; ?>
                            <a href="<?= htmlspecialchars($urlIniciar) ?>" data-iniciar-prova class="block w-full bg-indigo-700 text-white text-center py-3 rounded-lg hover:bg-indigo-800 font-medium border-2 border-indigo-800 cursor-pointer no-underline">
                                Iniciar prova
                            </a>
                            <p class="text-xs text-gray-500 text-center">Tela cheia, sem sair até finalizar. Escolha a matéria na ordem que quiser.</p>
                        <?php elseif ($blocoLiberado && !$dentroPeriodo && !$todasFinalizadas): ?>
                            <p class="text-sm text-gray-600 bg-gray-100 border border-gray-300 rounded-lg px-3 py-2 text-center font-medium">
                                ⏹ Expirado — O período para realizar esta prova já encerrou.
                            </p>
                            <span class="block w-full bg-gray-400 text-white text-center py-3 rounded-lg font-medium cursor-not-allowed opacity-75">
                                Não foi possível realizar no prazo
                            </span>
                        <?php elseif ($blocoLiberado && $todasFinalizadas): ?>
                            <?php if (!empty($bloco['gabarito_liberado'])): ?>
                            <a href="<?= URL ?>/aluno/provas/bloco/<?= $bloco['id'] ?>/resultados" 
                               class="block w-full bg-green-600 text-white text-center py-3 rounded-lg hover:bg-green-700 font-medium">
                                Visualizar resposta
                            </a>
                            <?php else: ?>
                            <div>
                                <span class="block w-full bg-gray-400 text-white text-center py-3 rounded-lg font-medium cursor-not-allowed opacity-90">
                                    Gabarito aguardando liberação
                                </span>
                                <p class="text-xs text-gray-500 text-center mt-1">A coordenação ainda não liberou o gabarito deste bloco.</p>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    (function() {
        var baseUrl = '<?= addslashes(URL) ?>';
        try {
            var ultimo = sessionStorage.getItem('ultimo_clique_iniciar');
            if (ultimo) {
                console.log('[INICIAR_PROVA] Página Minhas Provas carregada após clique em Iniciar. Último clique:', ultimo);
                sessionStorage.removeItem('ultimo_clique_iniciar');
            }
        } catch (e) {}
        // Força navegação ao clicar em Iniciar prova
        document.querySelectorAll('a[data-iniciar-prova]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var url = this.getAttribute('href');
                if (url) window.location.href = url;
            });
        });
    })();
    </script>
<?php endif; ?>
