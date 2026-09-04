<?php
$totalProvas = is_array($resultados ?? null) ? count($resultados) : 0;
$totalAcertos = (int) ($totalAcertos ?? 0);
$totalErros = (int) ($totalErros ?? 0);
$totalQuestoes = $totalAcertos + $totalErros;
$percentualGeral = $totalQuestoes > 0 ? ($totalAcertos / $totalQuestoes) * 100 : 0;
$percentualGeralFmt = number_format($percentualGeral, 2, ',', '.');
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-blue-700 mb-2">Resultado do bloco</p>
            <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars((string) ($bloco['titulo'] ?? 'Bloco de provas')) ?></h1>
            <p class="text-gray-600 mt-2">Confira seu desempenho consolidado nas provas finalizadas.</p>
        </div>
        <a href="<?= URL ?>/aluno/provas"
           class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left text-gray-500"></i>
            Voltar para Provas
        </a>
    </div>
</div>

<!-- Resumo Geral -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Resumo Geral</h2>
            <p class="text-sm text-gray-500 mt-1"><?= $totalQuestoes ?> questão(ões) corrigida(s) neste bloco.</p>
        </div>
        <div class="min-w-[220px]">
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="font-medium text-gray-600">Aproveitamento</span>
                <span class="font-bold text-blue-600"><?= $percentualGeralFmt ?>%</span>
            </div>
            <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full bg-blue-600" style="width: <?= min(100, max(0, $percentualGeral)) ?>%;"></div>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-600">Total de Provas</div>
                    <div class="text-2xl font-bold text-gray-900"><?= $totalProvas ?></div>
                </div>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white text-gray-500 border border-gray-200">
                    <i class="fa-solid fa-file-lines"></i>
                </span>
            </div>
        </div>
        <div class="rounded-lg border border-green-100 bg-green-50 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-green-800">Acertos</div>
                    <div class="text-2xl font-bold text-green-700"><?= $totalAcertos ?></div>
                </div>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white text-green-600 border border-green-200">
                    <i class="fa-solid fa-check"></i>
                </span>
            </div>
        </div>
        <div class="rounded-lg border border-red-100 bg-red-50 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-red-800">Erros</div>
                    <div class="text-2xl font-bold text-red-700"><?= $totalErros ?></div>
                </div>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white text-red-600 border border-red-200">
                    <i class="fa-solid fa-xmark"></i>
                </span>
            </div>
        </div>
        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-blue-800">Percentual</div>
                    <div class="text-2xl font-bold text-blue-700"><?= $percentualGeralFmt ?>%</div>
                </div>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white text-blue-600 border border-blue-200">
                    <i class="fa-solid fa-chart-line"></i>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Resultados por Prova -->
<div class="space-y-3">
    <?php if (empty($resultados)): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100 text-gray-500">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">Nenhum resultado disponível</h3>
            <p class="mt-2 text-sm text-gray-500">Quando as provas finalizadas deste bloco forem corrigidas, o desempenho aparecerá aqui.</p>
        </div>
    <?php endif; ?>
    <?php foreach ($resultados as $resultado): ?>
        <?php $prova = $resultado['prova']; ?>
        <?php
        $acertos = (int)($resultado['acertos'] ?? 0);
        $erros = (int)($resultado['erros'] ?? 0);
        $totalQ = $acertos + $erros;
        $percentual = $totalQ > 0 ? ($acertos / $totalQ) * 100 : 0;
        ?>
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <?= htmlspecialchars((string) ($prova['titulo'] ?? 'Prova')) ?>
                        </h3>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                            <?= $totalQ ?> questão(ões)
                        </span>
                    </div>
                    <p class="text-sm text-gray-600">
                        <?= htmlspecialchars((string) ($prova['materia_nome'] ?? '')) ?>
                    </p>
                </div>

                <div class="w-full lg:w-[360px]">
                    <div class="grid grid-cols-3 gap-2 text-center mb-3">
                        <div class="rounded-lg bg-green-50 border border-green-100 px-3 py-2">
                            <div class="text-xs text-green-800">Acertos</div>
                            <div class="text-lg font-bold text-green-700"><?= $acertos ?></div>
                        </div>
                        <div class="rounded-lg bg-red-50 border border-red-100 px-3 py-2">
                            <div class="text-xs text-red-800">Erros</div>
                            <div class="text-lg font-bold text-red-700"><?= $erros ?></div>
                        </div>
                        <div class="rounded-lg bg-blue-50 border border-blue-100 px-3 py-2">
                            <div class="text-xs text-blue-800">Percentual</div>
                            <div class="text-lg font-bold text-blue-700"><?= number_format($percentual, 2, ',', '.') ?>%</div>
                        </div>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-blue-600" style="width: <?= min(100, max(0, $percentual)) ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
