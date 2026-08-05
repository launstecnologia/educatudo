<?php
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../../Core/LayoutHelper.php';
}
?>
<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Resultado da Prova: <?= htmlspecialchars($prova['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                <?= htmlspecialchars($prova['materia_nome']) ?>
            </p>
            <p class="text-sm text-gray-500 mt-1">
                Aluno: <strong><?= htmlspecialchars($aluno['nome']) ?></strong>
            </p>
        </div>
        <div>
            <a href="<?= URL ?>/professor/provas" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                Voltar
            </a>
        </div>
    </div>
</div>

<?php if (!empty($versao_adaptada) || !empty($ei_no_spelling_penalty)): ?>
<div class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
    <p class="font-semibold mb-1"><i class="fa-solid fa-universal-access mr-1"></i> EducaInclui — observações pedagógicas</p>
    <ul class="list-disc pl-5 space-y-0.5">
        <?php if (!empty($versao_adaptada)): ?>
            <li>Este aluno respondeu uma <strong>versão adaptada</strong> da prova. As questões e respostas abaixo são as da versão entregue; a nota está na mesma escala da prova original.</li>
        <?php endif; ?>
        <?php if (!empty($ei_no_spelling_penalty)): ?>
            <li><strong>Não penalizar ortografia</strong> nas questões dissertativas deste aluno.</li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<?php
    $totalAcertos = 0;
    $totalErros = 0;
    $totalRespondidas = 0;
    foreach ($questoes as $qKpi) {
        $rKpi = $qKpi['resposta'] ?? null;
        if ($rKpi) {
            $totalRespondidas++;
            if (!empty($rKpi['correta'])) {
                $totalAcertos++;
            } else {
                $totalErros++;
            }
        }
    }
?>
<!-- KPIs de resultado -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-4">Resumo de desempenho</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
            <p class="text-xs text-emerald-700 uppercase font-semibold">Acertos</p>
            <p class="text-3xl font-bold text-emerald-800"><?= $totalAcertos ?></p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <p class="text-xs text-red-700 uppercase font-semibold">Erros</p>
            <p class="text-3xl font-bold text-red-800"><?= $totalErros ?></p>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-xs text-blue-700 uppercase font-semibold">Respondidas</p>
            <p class="text-3xl font-bold text-blue-800"><?= $totalRespondidas ?></p>
        </div>
    </div>
</div>

<!-- Detalhes da Realização -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Detalhes</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <p class="text-sm text-gray-600">Data de Início</p>
            <p class="text-lg font-semibold">
                <?= date('d/m/Y H:i', strtotime($realizacao['iniciado_em'])) ?>
            </p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Data de Finalização</p>
            <p class="text-lg font-semibold">
                <?= $realizacao['finalizado_em'] ? date('d/m/Y H:i', strtotime($realizacao['finalizado_em'])) : '-' ?>
            </p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Status</p>
            <p class="text-lg font-semibold">
                <?php if ($realizacao['status'] === 'finalizado'): ?>
                    <span class="text-green-600">Finalizado</span>
                <?php elseif ($realizacao['status'] === 'iniciado'): ?>
                    <span class="text-yellow-600">Em Andamento</span>
                <?php else: ?>
                    <span class="text-gray-600"><?= ucfirst($realizacao['status']) ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<!-- Questões e Respostas -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Questões e Respostas</h3>
    
    <div class="space-y-6">
        <?php foreach ($questoes as $index => $questao): ?>
            <?php 
            $resposta = $questao['resposta'] ?? null;
            $correta = $resposta && isset($resposta['correta']) ? $resposta['correta'] : false;
            ?>
            <div class="border-2 rounded-lg p-4 <?= $correta ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50' ?>">
                <div class="flex justify-between items-start mb-3">
                    <h4 class="text-lg font-semibold text-gray-900">
                        Questão <?= $index + 1 ?> 
                    </h4>
                    <div class="flex items-center gap-2">
                        <?php if ($resposta): ?>
                            <span class="px-3 py-1 rounded-full text-sm font-semibold <?= $correta ? 'bg-green-600 text-white' : 'bg-red-600 text-white' ?>">
                                <?= $correta ? '✓ Correta' : '✗ Incorreta' ?>
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-gray-400 text-white">
                                Não respondida
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mb-4">
                    <p class="text-gray-700 font-medium mb-2">Enunciado:</p>
                    <div class="text-gray-900"><?= isset($questao['enunciado']) ? LayoutHelper::renderEnunciadoProva($questao['enunciado']) : '' ?></div>
                </div>
                
                <?php if ($questao['tipo'] === 'multipla_escolha' && !empty($questao['alternativas'])): ?>
                    <div class="mb-4">
                        <p class="text-gray-700 font-medium mb-2">Alternativas:</p>
                        <div class="space-y-2">
                            <?php foreach ($questao['alternativas'] as $alt): ?>
                                <?php 
                                $isCorreta = $alt['correta'] == 1;
                                $isSelecionada = $resposta && $resposta['alternativa_id'] == $alt['id'];
                                $classe = '';
                                if ($isCorreta) {
                                    $classe = 'bg-green-100 border-green-500';
                                } elseif ($isSelecionada && !$isCorreta) {
                                    $classe = 'bg-red-100 border-red-500';
                                } else {
                                    $classe = 'bg-gray-50 border-gray-300';
                                }
                                ?>
                                <div class="p-3 rounded-lg border-2 <?= $classe ?>">
                                    <div class="flex items-center">
                                        <?php if ($isCorreta): ?>
                                            <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                        <?php endif; ?>
                                        <?php if ($isSelecionada && !$isCorreta): ?>
                                            <svg class="w-5 h-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                            </svg>
                                        <?php endif; ?>
                                        <span class="<?= $isCorreta ? 'font-semibold text-green-900' : ($isSelecionada ? 'font-semibold text-red-900' : 'text-gray-700') ?>">
                                            <?= isset($alt['texto']) ? LayoutHelper::renderEnunciadoProva($alt['texto']) : '' ?>
                                        </span>
                                        <?php if ($isCorreta): ?>
                                            <span class="ml-auto text-xs text-green-700 font-semibold">(Correta)</span>
                                        <?php endif; ?>
                                        <?php if ($isSelecionada && !$isCorreta): ?>
                                            <span class="ml-auto text-xs text-red-700 font-semibold">(Selecionada pelo aluno)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($questao['tipo'] === 'dissertativa' && $resposta): ?>
                    <div class="mb-4">
                        <p class="text-gray-700 font-medium mb-2">Resposta do Aluno:</p>
                        <div class="bg-gray-50 border border-gray-300 rounded-lg p-4">
                            <p class="text-gray-900 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($resposta['resposta_texto'] ?? '')) ?></p>
                        </div>
                    </div>
                <?php elseif ($questao['tipo'] === 'verdadeiro_falso' && $resposta): ?>
                    <div class="mb-4">
                        <p class="text-gray-700 font-medium mb-2">Resposta do Aluno:</p>
                        <div class="bg-gray-50 border border-gray-300 rounded-lg p-4">
                            <p class="text-gray-900 font-semibold">
                                <?= $resposta['resposta_texto'] === 'Verdadeiro' || $resposta['resposta_texto'] === 'V' ? 'Verdadeiro' : 'Falso' ?>
                            </p>
                            <?php if (isset($questao['resposta_correta'])): ?>
                                <p class="text-sm text-gray-600 mt-2">
                                    Resposta correta: <strong><?= $questao['resposta_correta'] ? 'Verdadeiro' : 'Falso' ?></strong>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

