<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="<?= URL ?>/exercicios-personalizados/minhas-listas" class="text-gray-600 hover:text-gray-900 inline-flex items-center mb-4">
            <i class="fa-solid fa-arrow-left mr-2"></i>
            Voltar
        </a>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Histórico</h1>
        <p class="text-gray-600 break-words line-clamp-2" title="<?= htmlspecialchars(trim($lista['titulo'] . (!empty($lista['materia']) ? ' • ' . $lista['materia'] : ''))) ?>">
            <?= htmlspecialchars($lista['titulo']) ?>
            <?php if (!empty($lista['materia'])): ?>
                <span class="text-gray-400"> • <?= htmlspecialchars($lista['materia']) ?></span>
            <?php endif; ?>
        </p>
    </div>

    <?php if (empty($sessoes)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <i class="fa-regular fa-folder-open text-5xl text-gray-300"></i>
            <h3 class="mt-4 text-xl font-semibold text-gray-900">Nenhuma sessão finalizada ainda</h3>
            <p class="mt-2 text-gray-600">Finalize uma execução para ver o histórico</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-1 min-w-0">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Execuções realizadas</h2>
                    <div class="space-y-2">
                        <?php foreach ($sessoes as $sessao_item):
                            $totalSessao = (int) ($sessao_item['total_respostas'] ?? 0);
                            $corretasSessao = (int) ($sessao_item['corretas'] ?? 0);
                            $percentualSessao = (int) ($sessao_item['percentual'] ?? 0);
                            $ativa = (int) $sessao_item['id'] === (int) ($sessao_id ?? 0);
                        ?>
                            <a href="<?= URL ?>/exercicios-personalizados/historico?lista_id=<?= (int) $lista['id'] ?>&sessao_id=<?= (int) $sessao_item['id'] ?>"
                               class="block p-4 rounded-lg border-2 <?= $ativa ? 'border-gray-900 bg-slate-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' ?> transition-colors">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= date('d/m/Y', strtotime($sessao_item['started_at'])) ?>
                                    </span>
                                    <span class="flex-shrink-0 text-xs px-2 py-1 rounded-full <?= $percentualSessao >= 70 ? 'bg-green-100 text-green-800' : ($percentualSessao >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                        <?= $percentualSessao ?>%
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600">
                                    <?= date('H:i', strtotime($sessao_item['started_at'])) ?> • <?= $corretasSessao ?>/<?= $totalSessao ?> acertos
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 min-w-0">
                <?php if ($sessao_selecionada && !empty($questoes)): ?>
                    <?php
                    $total_q = count($questoes);
                    $corretas_q = count(array_filter($questoes, static fn($q) => (int) ($q['is_correct'] ?? 0) === 1));
                    $erradas_q = $total_q - $corretas_q;
                    $percentual_q = $total_q > 0 ? round(($corretas_q / $total_q) * 100, 1) : 0;
                    ?>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Resumo da sessão</h2>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-gray-900"><?= $total_q ?></div>
                                <div class="text-sm text-gray-600">Total</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-green-600"><?= $corretas_q ?></div>
                                <div class="text-sm text-gray-600">Corretas</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-red-600"><?= $erradas_q ?></div>
                                <div class="text-sm text-gray-600">Incorretas</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold <?= $percentual_q >= 70 ? 'text-green-600' : ($percentual_q >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                                    <?= $percentual_q ?>%
                                </div>
                                <div class="text-sm text-gray-600">Acertos</div>
                            </div>
                        </div>
                        <p class="mt-4 text-sm text-gray-600">
                            Data: <?= date('d/m/Y H:i', strtotime($sessao_selecionada['started_at'])) ?>
                        </p>
                    </div>

                    <div class="space-y-4">
                        <?php foreach ($questoes as $index => $questao): ?>
                            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 border-l-4 <?= (int) ($questao['is_correct'] ?? 0) === 1 ? 'border-l-green-500' : 'border-l-red-500' ?>">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <h3 class="text-lg font-semibold text-gray-900">Questão <?= $index + 1 ?></h3>
                                    <span class="flex-shrink-0 px-3 py-1 text-xs font-semibold rounded-full <?= (int) ($questao['is_correct'] ?? 0) === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?php if ((int) ($questao['is_correct'] ?? 0) === 1): ?>
                                            <i class="fa-solid fa-check mr-1"></i>Correta
                                        <?php else: ?>
                                            <i class="fa-solid fa-xmark mr-1"></i>Incorreta
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <p class="text-gray-700 mb-4 break-words"><?= nl2br(htmlspecialchars($questao['pergunta'])) ?></p>

                                <?php include __DIR__ . '/_questao_revisao.php'; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <p class="text-gray-600">Selecione uma execução ao lado para ver os detalhes</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
