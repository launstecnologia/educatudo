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
                Visualizar Prova: <?= htmlspecialchars($prova['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                <?= htmlspecialchars($prova['materia_nome']) ?> - 
                <?= date('d/m/Y H:i', strtotime($prova['data_inicio'])) ?> até 
                <?= date('d/m/Y H:i', strtotime($prova['data_fim'])) ?>
            </p>
        </div>
        <div class="flex space-x-2">
            <?php if (in_array($prova['status'] ?? 'rascunho', ['rascunho', 'reprovada'])): ?>
                <a href="<?= URL ?>/professor/provas/editar/<?= $prova['id'] ?>"
                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Editar
                </a>
            <?php elseif ($prova['status'] === 'enviada'): ?>
                <span class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium bg-yellow-100 text-yellow-800">
                    Aguardando aprovação da coordenação
                </span>
            <?php elseif ($prova['status'] === 'aprovada'): ?>
                <span class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium bg-green-100 text-green-800">
                    Prova aprovada
                </span>
            <?php endif; ?>
            <a href="<?= URL ?>/professor/provas"
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Aviso de Reprovação -->
<?php if (($prova['status'] ?? '') === 'reprovada' && !empty($prova['motivo_reprovacao'])): ?>
<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
    <p class="text-sm font-semibold text-red-700">Prova reprovada pela coordenação:</p>
    <p class="text-sm text-red-600 mt-1"><?= htmlspecialchars($prova['motivo_reprovacao']) ?></p>
</div>
<?php endif; ?>

<!-- Informações da Prova -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações da Prova</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-600">Status</p>
            <p class="text-lg font-semibold">
                <?php 
                $statusFormatado = $prova['status_formatado'] ?? [
                    'texto' => 'Em Andamento',
                    'classe' => 'bg-blue-100 text-blue-800'
                ];
                ?>
                <span class="px-3 py-1 text-sm font-semibold rounded-full <?= $statusFormatado['classe'] ?>">
                    <?= $statusFormatado['texto'] ?>
                </span>
            </p>
        </div>
        <?php if (!empty($evento_coordenador_nome ?? null)): ?>
        <div>
            <p class="text-sm text-gray-600">Coordenador(a)</p>
            <p class="text-lg font-semibold"><?= htmlspecialchars($evento_coordenador_nome) ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($prova['descricao']): ?>
        <div class="mt-4">
            <p class="text-sm text-gray-600">Descrição</p>
            <p class="text-gray-900"><?= nl2br(htmlspecialchars($prova['descricao'])) ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Questões -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Questões (<?= count($questoes) ?>)</h3>
        <?php if (($prova['status'] ?? '') !== 'aprovada'): ?>
            <a href="<?= URL ?>/professor/provas/editar/<?= $prova['id'] ?>" 
               class="text-blue-600 hover:text-blue-800">
                Gerenciar Questões
            </a>
        <?php else: ?>
            <span class="text-sm text-gray-500">Bloqueada pela coordenação</span>
        <?php endif; ?>
    </div>
    
    <?php if (empty($questoes)): ?>
        <p class="text-gray-500 text-center py-8">Nenhuma questão adicionada ainda.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($questoes as $index => $questao): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-semibold text-gray-900">
                            Questão <?= $index + 1 ?> -
                            <?php
                            $tipos = [
                                'multipla_escolha' => 'Múltipla Escolha',
                                'verdadeiro_falso' => 'Verdadeiro/Falso',
                                'dissertativa' => 'Dissertativa'
                            ];
                            echo $tipos[$questao['tipo']] ?? $questao['tipo'];
                            ?>
                        </h4>
                    </div>
                    <p class="text-gray-700 mb-3"><?= isset($questao['enunciado']) ? LayoutHelper::renderEnunciadoProva($questao['enunciado']) : '' ?></p>
                    
                    <?php if (!empty($questao['imagem_url'])): ?>
                        <div class="mb-3">
                            <img src="<?= htmlspecialchars($questao['imagem_url']) ?>" alt="Imagem da questão" 
                                 class="max-w-md rounded-lg border border-gray-300">
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($questao['tipo'] === 'multipla_escolha' && !empty($questao['alternativas'])): ?>
                        <div class="ml-4 space-y-2">
                            <?php foreach ($questao['alternativas'] as $alt): ?>
                                <div class="flex items-center">
                                    <span class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center mr-2 <?= $alt['correta'] ? 'bg-green-100 border-green-500' : '' ?>">
                                        <?php if ($alt['correta']): ?>
                                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-gray-700"><?= isset($alt['texto']) ? LayoutHelper::renderEnunciadoProva($alt['texto']) : '' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Alunos que Realizaram -->
<?php if (!empty($alunosRealizacao)): ?>
<div id="resultados-alunos" class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Alunos que Realizaram (<?= count($alunosRealizacao) ?>)</h3>
    <?php
        $totalFinalizados = 0;
        $totalAcertos = 0;
        $totalErros = 0;
        foreach ($alunosRealizacao as $rKpi) {
            if (($rKpi['status'] ?? '') === 'finalizado') {
                $totalFinalizados++;
            }
            $totalAcertos += (int)($rKpi['acertos'] ?? 0);
            $totalErros += (int)($rKpi['erros'] ?? 0);
        }
    ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <p class="text-xs text-green-700 uppercase font-semibold">Finalizadas</p>
            <p class="text-2xl font-bold text-green-800"><?= $totalFinalizados ?></p>
        </div>
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
            <p class="text-xs text-emerald-700 uppercase font-semibold">Total de Acertos</p>
            <p class="text-2xl font-bold text-emerald-800"><?= $totalAcertos ?></p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <p class="text-xs text-red-700 uppercase font-semibold">Total de Erros</p>
            <p class="text-2xl font-bold text-red-800"><?= $totalErros ?></p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acertos / Erros</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($alunosRealizacao as $realizacao): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($realizacao['aluno_nome']) ?></div>
                            <div class="text-sm text-gray-500">RA: <?= htmlspecialchars($realizacao['aluno_ra']) ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                <?= $realizacao['status'] === 'finalizado' ? 'bg-green-100 text-green-800' : ($realizacao['status'] === 'cancelada' ? 'bg-amber-100 text-amber-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                <?= $realizacao['status'] === 'cancelada' ? 'Cancelada' : ucfirst($realizacao['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold text-gray-900">
                                <?= (int)($realizacao['acertos'] ?? 0) ?> acertos
                                <span class="text-gray-400 mx-1">/</span>
                                <span class="text-red-600"><?= (int)($realizacao['erros'] ?? 0) ?> erros</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <?php if ($realizacao['status'] === 'finalizado'): ?>
                                <a href="<?= URL ?>/professor/provas/resultado-aluno/<?= $prova['id'] ?>/<?= $realizacao['aluno_id'] ?>" 
                                   class="text-blue-600 hover:text-blue-900">
                                    Ver Resultado
                                </a>
                            <?php elseif ($realizacao['status'] === 'cancelada'): ?>
                                <form method="post" action="<?= URL ?>/professor/provas/liberar-tentativa/<?= (int)$prova['id'] ?>/<?= (int)$realizacao['aluno_id'] ?>" class="inline" onsubmit="return confirm('Liberar nova tentativa para este aluno? Ele poderá realizar a prova novamente.');">
                                    <button type="submit" class="text-amber-700 hover:text-amber-900 font-medium">Liberar nova tentativa</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

