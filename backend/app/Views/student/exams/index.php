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
                        <a href="<?= URL ?>/aluno/provas/resultado/<?= $provaId ?>" class="block w-full bg-primary text-primary text-center py-3 rounded-lg hover:opacity-90 font-semibold transition-opacity">
                            Visualizar resultado
                        </a>
                    <?php elseif ($cancelada): ?>
                        <span class="block w-full bg-amber-100 text-amber-800 text-center py-3 rounded-lg font-medium cursor-not-allowed border border-amber-300">
                            Cancelada – aguarde liberação
                        </span>
                    <?php else: ?>
                        <a href="<?= URL ?>/aluno/provas/realizar/<?= $provaAcessoId ?>" data-iniciar-prova class="block w-full bg-primary text-primary text-center py-3 rounded-lg hover:opacity-90 font-semibold transition-opacity no-underline">
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
$clsCtaPrimario = 'block w-full bg-primary text-primary text-center py-3 rounded-lg hover:opacity-90 font-semibold transition-opacity no-underline';
$clsCtaInativo = 'block w-full bg-gray-200 text-gray-600 text-center py-3 rounded-lg font-medium cursor-not-allowed';
?>
<?php if ($temBlocos): ?>
    <div class="mb-8" id="secao-blocos-provas">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <h2 class="text-xl font-semibold text-gray-900">
                Blocos de Provas
                <span class="text-sm font-normal text-gray-500">(<?= count($blocosComProvas) ?> bloco(s))</span>
            </h2>
            <div class="flex items-center gap-2">
                <button type="button"
                        id="btn-mostrar-blocos"
                        class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <i class="fa-solid fa-eye text-gray-500"></i>
                    Mostrar todos
                </button>
                <button type="button"
                        id="btn-esconder-blocos"
                        class="inline-flex items-center gap-2 px-3 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">
                    <i class="fa-solid fa-eye-slash"></i>
                    Esconder todos
                </button>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($blocosComProvas as $idx => $bloco): ?>
                <?php
                if (!isset($bloco['id'])) {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG: Bloco #{$idx} sem ID: " . print_r($bloco, true));
                    }
                    continue;
                }
                $blocoId = (int) $bloco['id'];
                $qtdProvasBloco = count($bloco['provas'] ?? []);
                $todasFinalizadas = !empty($bloco['todas_finalizadas']);
                $algumaCancelada = !empty($bloco['alguma_cancelada']);
                $dentroPeriodo = !empty($bloco['dentro_periodo']);
                $disponivelEm = $bloco['disponivel_em'] ?? null;
                $blocoLiberado = !empty($bloco['liberado']) && !empty($bloco['ativo']);
                $blocoAbertoPorPadrao = true;
                $dataProvaTs = !empty($bloco['data_prova']) ? strtotime((string) $bloco['data_prova']) : false;
                $horaInicioTs = !empty($bloco['hora_inicio']) ? strtotime((string) $bloco['hora_inicio']) : false;
                $horaFimTs = !empty($bloco['hora_fim']) ? strtotime((string) $bloco['hora_fim']) : false;
                ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow flex flex-col">
                    <div class="h-1.5 bg-primary"></div>
                    <button type="button"
                            class="w-full text-left p-5 pb-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                            data-bloco-toggle="<?= $blocoId ?>"
                            aria-expanded="<?= $blocoAbertoPorPadrao ? 'true' : 'false' ?>"
                            aria-controls="bloco-corpo-<?= $blocoId ?>">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-lg font-bold text-gray-900 leading-snug min-w-0">
                                <?= htmlspecialchars((string) ($bloco['titulo'] ?? '')) ?>
                            </h3>
                            <i class="fa-solid fa-chevron-down text-accent mt-1 shrink-0 transition-transform duration-200 <?= $blocoAbertoPorPadrao ? '' : '-rotate-90' ?>"
                               data-bloco-chevron="<?= $blocoId ?>"></i>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                                <i class="fa-regular fa-calendar text-accent"></i>
                                <?= $dataProvaTs ? date('d/m/Y', $dataProvaTs) : '—' ?>
                            </span>
                            <span class="text-gray-300">·</span>
                            <span class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                                <i class="fa-regular fa-clock text-accent"></i>
                                <?= $horaInicioTs ? date('H:i', $horaInicioTs) : '--:--' ?>
                                –
                                <?= $horaFimTs ? date('H:i', $horaFimTs) : '--:--' ?>
                            </span>
                            <span class="inline-flex items-center gap-1.5 ml-auto px-2.5 py-1 rounded-lg bg-primary text-primary text-xs font-semibold">
                                <i class="fa-solid fa-file-lines"></i>
                                <?= $qtdProvasBloco ?> <?= $qtdProvasBloco === 1 ? 'prova' : 'provas' ?>
                            </span>
                        </div>
                    </button>

                    <div id="bloco-corpo-<?= $blocoId ?>"
                         data-bloco-corpo="<?= $blocoId ?>"
                         class="<?= $blocoAbertoPorPadrao ? '' : 'hidden' ?> px-5 pb-5 flex-1 flex flex-col">
                        <?php if (!empty($bloco['descricao'])): ?>
                            <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars((string) $bloco['descricao']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($bloco['provas'])): ?>
                        <div class="mb-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Provas deste bloco</p>
                            <div class="space-y-2">
                                <?php foreach ($bloco['provas'] as $prova): ?>
                                    <div class="rounded-lg p-3 border border-gray-100 bg-slate-50">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars((string) ($prova['materia_nome'] ?? $prova['titulo'] ?? 'Prova')) ?></p>
                                                <p class="text-xs text-gray-500 truncate"><?= !empty($prova['professor_nome']) ? 'Prof. ' . htmlspecialchars((string) $prova['professor_nome']) : '—' ?></p>
                                            </div>
                                            <?php if (isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'finalizado'): ?>
                                                <span class="shrink-0 inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full font-semibold">
                                                    <i class="fa-solid fa-check"></i> Finalizada
                                                </span>
                                            <?php elseif (isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'iniciado'): ?>
                                                <span class="shrink-0 inline-flex items-center text-xs text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full font-semibold">Em andamento</span>
                                            <?php elseif (isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'cancelada'): ?>
                                                <span class="shrink-0 inline-flex items-center text-xs text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full font-semibold">Cancelada</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mb-4 bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <p class="text-xs text-amber-800">Não há prova disponível para a sua turma neste bloco.</p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($bloco['provas'])): ?>
                        <div class="space-y-2 mt-auto">
                            <?php if (!$blocoLiberado && !$todasFinalizadas): ?>
                                <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-center font-medium">
                                    Aguardando liberação pelo coordenador. O bloco aparecerá aqui quando estiver disponível.
                                </p>
                                <span class="<?= $clsCtaInativo ?>">
                                    Bloco não liberado
                                </span>
                            <?php elseif (!$blocoLiberado && $todasFinalizadas): ?>
                                <?php if (!empty($bloco['gabarito_liberado'])): ?>
                                <a href="<?= URL ?>/aluno/provas/bloco/<?= $blocoId ?>/resultados"
                                   class="<?= $clsCtaPrimario ?>">
                                    Visualizar resposta
                                </a>
                                <?php else: ?>
                                <div>
                                    <span class="<?= $clsCtaInativo ?>">
                                        Gabarito aguardando liberação
                                    </span>
                                    <p class="text-xs text-gray-500 text-center mt-1">A coordenação ainda não liberou o gabarito deste bloco.</p>
                                </div>
                                <?php endif; ?>
                            <?php elseif ($blocoLiberado && !empty($disponivelEm)): ?>
                                <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-center font-medium">
                                    Aguardando liberação — Disponível em <?= date('d/m/Y', strtotime($disponivelEm)) ?> às <?= date('H:i', strtotime($disponivelEm)) ?>
                                </p>
                                <span class="<?= $clsCtaInativo ?>">
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
                                <?php $urlIniciar = URL . '/aluno/provas/bloco/' . $blocoId . '/iniciar-seguro'; ?>
                                <a href="<?= htmlspecialchars($urlIniciar) ?>" data-iniciar-prova class="<?= $clsCtaPrimario ?> cursor-pointer">
                                    Iniciar prova
                                </a>
                                <p class="text-xs text-gray-500 text-center">Tela cheia, sem sair até finalizar. Escolha a matéria na ordem que quiser.</p>
                            <?php elseif ($blocoLiberado && !$dentroPeriodo && !$todasFinalizadas): ?>
                                <p class="text-sm text-gray-600 bg-gray-100 border border-gray-200 rounded-lg px-3 py-2 text-center font-medium">
                                    Expirado — O período para realizar esta prova já encerrou.
                                </p>
                                <span class="<?= $clsCtaInativo ?>">
                                    Não foi possível realizar no prazo
                                </span>
                            <?php elseif ($blocoLiberado && $todasFinalizadas): ?>
                                <?php if (!empty($bloco['gabarito_liberado'])): ?>
                                <a href="<?= URL ?>/aluno/provas/bloco/<?= $blocoId ?>/resultados"
                                   class="<?= $clsCtaPrimario ?>">
                                    Visualizar resposta
                                </a>
                                <?php else: ?>
                                <div>
                                    <span class="<?= $clsCtaInativo ?>">
                                        Gabarito aguardando liberação
                                    </span>
                                    <p class="text-xs text-gray-500 text-center mt-1">A coordenação ainda não liberou o gabarito deste bloco.</p>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    (function() {
        try {
            var ultimo = sessionStorage.getItem('ultimo_clique_iniciar');
            if (ultimo) {
                console.log('[INICIAR_PROVA] Página Minhas Provas carregada após clique em Iniciar. Último clique:', ultimo);
                sessionStorage.removeItem('ultimo_clique_iniciar');
            }
        } catch (e) {}

        document.querySelectorAll('a[data-iniciar-prova]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var url = this.getAttribute('href');
                if (url) window.location.href = url;
            });
        });

        function definirBlocoAberto(id, aberto) {
            var corpo = document.getElementById('bloco-corpo-' + id);
            var chevron = document.querySelector('[data-bloco-chevron="' + id + '"]');
            var toggle = document.querySelector('[data-bloco-toggle="' + id + '"]');
            if (!corpo) return;
            corpo.classList.toggle('hidden', !aberto);
            if (toggle) toggle.setAttribute('aria-expanded', aberto ? 'true' : 'false');
            if (chevron) {
                chevron.classList.toggle('-rotate-90', !aberto);
            }
        }

        document.querySelectorAll('[data-bloco-toggle]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-bloco-toggle');
                var corpo = document.getElementById('bloco-corpo-' + id);
                if (!corpo) return;
                definirBlocoAberto(id, corpo.classList.contains('hidden'));
            });
        });

        var btnMostrar = document.getElementById('btn-mostrar-blocos');
        var btnEsconder = document.getElementById('btn-esconder-blocos');
        if (btnMostrar) {
            btnMostrar.addEventListener('click', function() {
                document.querySelectorAll('[data-bloco-corpo]').forEach(function(el) {
                    definirBlocoAberto(el.getAttribute('data-bloco-corpo'), true);
                });
            });
        }
        if (btnEsconder) {
            btnEsconder.addEventListener('click', function() {
                document.querySelectorAll('[data-bloco-corpo]').forEach(function(el) {
                    definirBlocoAberto(el.getAttribute('data-bloco-corpo'), false);
                });
            });
        }
    })();
    </script>
<?php endif; ?>
