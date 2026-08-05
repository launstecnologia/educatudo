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
                Professor: <?= htmlspecialchars($prova['professor_nome']) ?>
            </p>
        </div>
        <?php if (isset($bloco_id) && $bloco_id): ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco_id ?>/gerenciar" 
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                Voltar
            </a>
        <?php else: ?>
            <a href="<?= URL ?>/admin/provas" 
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                Voltar
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Informações da Prova -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Informações da Prova</h3>
    </div>
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
        <div>
            <p class="text-sm text-gray-600">Período</p>
            <p class="text-lg font-semibold">
                <?= date('d/m/Y H:i', strtotime($prova['data_inicio'])) ?> - 
                <?= date('d/m/Y H:i', strtotime($prova['data_fim'])) ?>
            </p>
        </div>
    </div>
    
    <?php if (!empty($prova['observacao_coordenacao'])): ?>
        <div class="mt-4 p-4 bg-orange-50 border-l-4 border-orange-400 rounded">
            <p class="text-sm font-medium text-orange-800 mb-1">Observação da Coordenação:</p>
            <p class="text-sm text-orange-700"><?= nl2br(htmlspecialchars($prova['observacao_coordenacao'])) ?></p>
            <?php if (!empty($prova['observacao_coordenacao_data'])): ?>
                <p class="text-xs text-orange-600 mt-2">
                    Retornada em: <?= date('d/m/Y H:i', strtotime($prova['observacao_coordenacao_data'])) ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Questões -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Questões (<?= count($questoes) ?>)</h3>
    
    <?php if (empty($questoes)): ?>
        <p class="text-gray-500 text-center py-8">Nenhuma questão adicionada.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($questoes as $index => $questao): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start justify-between gap-4 mb-2">
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
                            <?php if (!empty($questao['invalidada'])): ?>
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Invalidada</span>
                            <?php endif; ?>
                        </h4>
                        <?php if (!empty($isAdmin)): ?>
                            <button type="button"
                                    class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-semibold <?= !empty($questao['invalidada']) ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-amber-600 hover:bg-amber-700 text-white' ?>"
                                    title="<?= !empty($questao['invalidada']) ? 'Revalidar questão' : 'Invalidar questão' ?>"
                                    onclick="abrirModalInvalidacaoQuestao(<?= (int)$questao['id'] ?>, <?= !empty($questao['invalidada']) ? 'true' : 'false' ?>, <?= json_encode((string)($questao['observacao_invalidacao'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 3c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z"></path>
                                </svg>
                                <?= !empty($questao['invalidada']) ? 'Revalidar' : 'Invalidar' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="text-gray-700 mb-3"><?= isset($questao['enunciado']) ? LayoutHelper::renderEnunciadoProva($questao['enunciado']) : '' ?></div>
                    
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
<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Alunos que Realizaram (<?= count($alunosRealizacao) ?>)</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($alunosRealizacao as $realizacao): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($realizacao['aluno_nome']) ?></div>
                            <?php $raAluno = trim((string)($realizacao['aluno_ra'] ?? '')); ?>
                            <div class="text-sm text-gray-500">RA: <?= $raAluno !== '' ? htmlspecialchars($raAluno) : '-' ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                <?= $realizacao['status'] === 'finalizado' ? 'bg-green-100 text-green-800' : ($realizacao['status'] === 'cancelada' ? 'bg-amber-100 text-amber-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                <?= $realizacao['status'] === 'cancelada' ? 'Cancelada' : ucfirst($realizacao['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($realizacao['nota'] !== null): ?>
                                <div class="text-sm font-semibold text-gray-900">
                                    <?= number_format($realizacao['nota'], 2, ',', '.') ?> / 
                                    <?= number_format($prova['valor_total'], 2, ',', '.') ?>
                                </div>
                            <?php else: ?>
                                <span class="text-sm text-gray-500">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <?php if ($realizacao['status'] === 'finalizado'): ?>
                                <a href="<?= URL ?>/admin/provas/resultado-aluno/<?= $prova['id'] ?>/<?= $realizacao['aluno_id'] ?>" 
                                   class="text-blue-600 hover:text-blue-900">
                                    Ver Resultado
                                </a>
                            <?php elseif ($realizacao['status'] === 'cancelada'): ?>
                                <form method="post" action="<?= URL ?>/admin/provas/liberar-tentativa/<?= (int)$prova['id'] ?>/<?= (int)$realizacao['aluno_id'] ?>" class="inline" onsubmit="return confirm('Liberar nova tentativa para este aluno? Ele poderá realizar a prova novamente.');">
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

<!-- Modal Invalidação de Questão (Passo 1: Observação) -->
<div id="modalInvalidarQuestao" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
        <div class="mt-1">
            <h3 id="tituloModalInvalidar" class="text-lg font-medium text-gray-900 mb-2">Invalidar questão</h3>
            <p id="textoModalInvalidar" class="text-sm text-gray-600 mb-4">Informe o motivo da invalidação da questão.</p>
            <div class="mb-4">
                <label for="motivoInvalidacaoQuestao" class="block text-sm font-medium text-gray-700 mb-2">
                    Motivo da invalidação *
                </label>
                <textarea id="motivoInvalidacaoQuestao"
                          rows="4"
                          maxlength="1000"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                          placeholder="Descreva o motivo da invalidação..."></textarea>
            </div>
            <div class="mb-4">
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                    Ao invalidar, esta questão será marcada como <strong>certa para todos os alunos</strong>, e as notas serão recalculadas.
                </div>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button"
                        onclick="fecharModalInvalidacaoQuestao()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button id="btnConfirmarInvalidacaoQuestao"
                        type="button"
                        onclick="avancarSenhaInvalidacaoQuestao()"
                        class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-semibold">
                    Continuar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Invalidação de Questão (Passo 2: Senha) -->
<div id="modalSenhaInvalidarQuestao" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-[60]">
    <div class="relative top-24 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-1">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Confirmar com senha</h3>
            <p class="text-sm text-gray-600 mb-4">Digite sua senha de admin para confirmar esta ação.</p>
            <div class="mb-4">
                <label for="senhaInvalidacaoQuestao" class="block text-sm font-medium text-gray-700 mb-2">
                    Sua senha *
                </label>
                <input type="password"
                       id="senhaInvalidacaoQuestao"
                       class="w-full px-3 py-2 border border-amber-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                       placeholder="Digite sua senha para confirmar"
                       autocomplete="current-password">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button"
                        onclick="voltarModalInvalidacaoQuestao()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Voltar
                </button>
                <button id="btnConfirmarSenhaInvalidacaoQuestao"
                        type="button"
                        onclick="confirmarInvalidacaoQuestao()"
                        class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-semibold">
                    Confirmar invalidação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Ações da Coordenação -->
<?php if (isset($isAdmin) && $isAdmin): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mt-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Ações da Coordenação</h3>
    <div class="flex flex-wrap gap-4 items-center">
        <a href="<?= URL ?>/admin/provas/editar/<?= (int)$prova['id'] ?>"
           class="btn-primary-custom px-6 py-2 rounded-lg transition-colors flex items-center inline-flex hover:opacity-90">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            Editar prova (questões e dados)
        </a>
        <a href="<?= URL ?>/admin/provas/<?= (int)$prova['id'] ?>/imprimir" target="_blank"
           class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700 transition-colors flex items-center inline-flex">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z"></path>
            </svg>
            Imprimir / Salvar em PDF
        </a>
        <?php 
        $statusProva = $prova['status'] ?? '';
        $estaEnviada = in_array($statusProva, ['enviada', 'aguardando_aprovacao']);
        $podeAprovarQualquer = in_array($statusProva, ['enviada', 'aguardando_aprovacao', 'pendente', 'agendada']);
        $jaAprovada = $statusProva === 'aprovada';
        $podeAprovar = !$jaAprovada && $podeAprovarQualquer;
        $podeRetornar = $estaEnviada;
        ?>
        <?php if ($podeAprovar): ?>
            <button id="btnAprovarProva" onclick="aprovarProva(<?= $prova['id'] ?>)"
                    class="btn-primary-custom px-6 py-2 rounded-lg transition-colors flex items-center hover:opacity-90">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Aprovar Prova
            </button>
            <button onclick="abrirModalRemover()"
                    class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Remover Prova
            </button>
        <?php endif; ?>
        <?php if ($podeRetornar): ?>
            <button onclick="abrirModalRetornar(<?= $prova['id'] ?>)"
                    class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                </svg>
                Retornar Prova ao Professor
            </button>
        <?php endif; ?>
        <?php if (!isset($questoes) || empty($questoes)): ?>
            <p class="text-sm text-gray-500">Adicione questões pela edição da prova (botão acima).</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Modal Remover Prova -->
<div id="modalRemover" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-red-700 mb-4">Remover Prova</h3>
            <p class="text-sm text-gray-600 mb-4">
                A prova será retirada do bloco (alunos não a verão mais) e removida para o professor. Esta ação exige sua senha.
            </p>
            <div class="mb-4">
                <label for="motivoRemocao" class="block text-sm font-medium text-gray-700 mb-2">
                    Motivo (opcional)
                </label>
                <textarea id="motivoRemocao"
                          rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                          placeholder="Ex: Prova duplicada, não será mais aplicada..."></textarea>
            </div>
            <div class="mb-4">
                <label for="senhaRemocao" class="block text-sm font-medium text-gray-700 mb-2">
                    Sua senha *
                </label>
                <input type="password"
                       id="senhaRemocao"
                       class="w-full px-3 py-2 border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                       placeholder="Digite sua senha para confirmar"
                       autocomplete="current-password">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button"
                        onclick="fecharModalRemover()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button id="btnConfirmarRemocao"
                        type="button"
                        onclick="confirmarRemocao()"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">
                    Confirmar remoção
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Retornar Prova -->
<div id="modalRetornar" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Retornar Prova ao Professor</h3>
            <p class="text-sm text-gray-600 mb-4">
                Informe ao professor o que precisa ser corrigido ou refeito na prova:
            </p>
            <form id="formRetornar" onsubmit="retornarProva(event)">
                <input type="hidden" id="prova_id_retornar" name="prova_id">
                <div class="mb-4">
                    <label for="observacao_retorno" class="block text-sm font-medium text-gray-700 mb-2">
                        Observações para o Professor *
                    </label>
                    <textarea id="observacao_retorno" 
                              name="observacao_retorno" 
                              rows="5"
                              required
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                              placeholder="Ex: Por favor, revise a questão 3 e adicione mais uma alternativa na questão 5..."></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" 
                            onclick="fecharModalRetornar()"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                        Retornar Prova
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function aprovarProva(provaId) {
    if (!confirm('Deseja aprovar esta avaliação? Após aprovar, o professor não poderá mais editar. A prova só será liberada para os alunos quando você fizer a aprovação final do bloco.')) {
        return;
    }
    
    fetch(`<?= URL ?>/admin/provas/liberar/${provaId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(async response => {
        if (!response.ok) {
            const text = await response.text();
            throw new Error(`Erro ${response.status}: ${text.substring(0, 200)}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const btn = document.getElementById('btnAprovarProva');
            if (btn) btn.style.display = 'none';
            alert('Prova aprovada com sucesso! O professor não poderá mais editar. Libere para os alunos na aprovação final do bloco.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao aprovar prova'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}

function abrirModalRetornar(provaId) {
    document.getElementById('prova_id_retornar').value = provaId;
    document.getElementById('observacao_retorno').value = '';
    document.getElementById('modalRetornar').classList.remove('hidden');
}

function fecharModalRetornar() {
    document.getElementById('modalRetornar').classList.add('hidden');
    document.getElementById('formRetornar').reset();
}

function retornarProva(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const observacao = formData.get('observacao_retorno');
    const provaId = formData.get('prova_id');
    
    if (!observacao || observacao.trim() === '') {
        alert('Por favor, informe as observações para o professor.');
        return;
    }
    
    fetch(`<?= URL ?>/admin/provas/${provaId}/retornar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            observacao: observacao,
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(async response => {
        if (!response.ok) {
            const text = await response.text();
            throw new Error(`Erro ${response.status}: ${text.substring(0, 200)}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Prova retornada ao professor com sucesso!');
            fecharModalRetornar();
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao retornar prova'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}

// Fecha modal ao clicar fora
var modalRetornarEl = document.getElementById('modalRetornar');
if (modalRetornarEl) {
    modalRetornarEl.addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalRetornar();
        }
    });
}

function abrirModalRemover() {
    document.getElementById('motivoRemocao').value = '';
    document.getElementById('senhaRemocao').value = '';
    document.getElementById('modalRemover').classList.remove('hidden');
}
function fecharModalRemover() {
    document.getElementById('modalRemover').classList.add('hidden');
    document.getElementById('motivoRemocao').value = '';
    document.getElementById('senhaRemocao').value = '';
}
function confirmarRemocao() {
    const senha = document.getElementById('senhaRemocao').value;
    if (!senha || senha.trim() === '') {
        alert('Digite sua senha para confirmar a remoção.');
        return;
    }
    const motivo = document.getElementById('motivoRemocao').value.trim();
    const btn = document.getElementById('btnConfirmarRemocao');
    btn.disabled = true;
    btn.textContent = 'Removendo...';

    fetch(`<?= URL ?>/admin/provas/<?= $prova['id'] ?>/remover`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            senha: senha,
            motivo: motivo,
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(async r => {
        const text = await r.text();
        if (!r.ok) {
            throw new Error(text.substring(0, 200));
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error(text.substring(0, 200) || 'Resposta inválida');
        }
    })
    .then(data => {
        if (data.success) {
            fecharModalRemover();
            alert(data.message || 'Prova removida.');
            <?php if (!empty($bloco_id)): ?>
            window.location.href = '<?= URL ?>/admin/provas/blocos/<?= (int)$bloco_id ?>/gerenciar';
            <?php else: ?>
            window.location.href = '<?= URL ?>/admin/provas';
            <?php endif; ?>
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível remover.'));
            btn.disabled = false;
            btn.textContent = 'Confirmar remoção';
        }
    })
    .catch(err => {
        alert('Erro: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Confirmar remoção';
    });
}

var modalRemoverEl = document.getElementById('modalRemover');
if (modalRemoverEl) {
    modalRemoverEl.addEventListener('click', function(e) {
        if (e.target === this) fecharModalRemover();
    });
}

let questaoInvalidacaoAtual = { id: 0, invalidada: false };

function abrirModalInvalidacaoQuestao(questaoId, invalidada, observacaoAtual) {
    questaoInvalidacaoAtual = { id: parseInt(questaoId || 0, 10), invalidada: !!invalidada };
    document.getElementById('motivoInvalidacaoQuestao').value = (observacaoAtual || '');
    document.getElementById('senhaInvalidacaoQuestao').value = '';
    document.getElementById('tituloModalInvalidar').textContent = invalidada ? 'Revalidar questão' : 'Invalidar questão';
    document.getElementById('textoModalInvalidar').textContent = invalidada
        ? 'Informe o motivo da revalidação e confirme com sua senha.'
        : 'Ao invalidar, a questão será marcada como certa para todos os alunos.';
    const btn = document.getElementById('btnConfirmarInvalidacaoQuestao');
    btn.textContent = invalidada ? 'Continuar revalidação' : 'Continuar';
    btn.className = invalidada
        ? 'px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-semibold'
        : 'px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-semibold';
    const modalPasso1 = document.getElementById('modalInvalidarQuestao');
    const modalPasso2 = document.getElementById('modalSenhaInvalidarQuestao');
    modalPasso2.classList.add('hidden');
    modalPasso1.classList.remove('hidden');
    modalPasso1.style.display = 'block';
}

function avancarSenhaInvalidacaoQuestao() {
    const observacao = (document.getElementById('motivoInvalidacaoQuestao').value || '').trim();
    if (observacao === '') {
        alert('Informe o motivo da invalidação.');
        return;
    }
    document.getElementById('modalInvalidarQuestao').classList.add('hidden');
    document.getElementById('modalInvalidarQuestao').style.display = 'none';
    const modalPasso2 = document.getElementById('modalSenhaInvalidarQuestao');
    modalPasso2.classList.remove('hidden');
    modalPasso2.style.display = 'block';
}

function voltarModalInvalidacaoQuestao() {
    document.getElementById('modalSenhaInvalidarQuestao').classList.add('hidden');
    document.getElementById('modalSenhaInvalidarQuestao').style.display = 'none';
    const modalPasso1 = document.getElementById('modalInvalidarQuestao');
    modalPasso1.classList.remove('hidden');
    modalPasso1.style.display = 'block';
}

function fecharModalInvalidacaoQuestao() {
    document.getElementById('modalInvalidarQuestao').classList.add('hidden');
    document.getElementById('modalInvalidarQuestao').style.display = 'none';
    document.getElementById('modalSenhaInvalidarQuestao').classList.add('hidden');
    document.getElementById('modalSenhaInvalidarQuestao').style.display = 'none';
    document.getElementById('motivoInvalidacaoQuestao').value = '';
    document.getElementById('senhaInvalidacaoQuestao').value = '';
}

function confirmarInvalidacaoQuestao() {
    const questaoId = questaoInvalidacaoAtual.id;
    if (!questaoId) return;

    const observacao = (document.getElementById('motivoInvalidacaoQuestao').value || '').trim();
    const senha = document.getElementById('senhaInvalidacaoQuestao').value || '';
    if (observacao === '') {
        alert('Informe o motivo da invalidação.');
        return;
    }
    if (senha.trim() === '') {
        alert('Digite sua senha para confirmar.');
        return;
    }

    const invalidar = !questaoInvalidacaoAtual.invalidada;
    const msgConfirm = invalidar
        ? 'Confirmar invalidação desta questão? Ela contará como certa para todos os alunos.'
        : 'Confirmar revalidação desta questão? As notas serão recalculadas.';
    if (!confirm(msgConfirm)) {
        return;
    }

    const btn = document.getElementById('btnConfirmarSenhaInvalidacaoQuestao');
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    fetch(`<?= URL ?>/admin/provas/questoes/${questaoId}/invalidar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            invalidar: invalidar ? 1 : 0,
            observacao: observacao,
            senha: senha,
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(async r => {
        const text = await r.text();
        try {
            return { ok: r.ok, data: JSON.parse(text) };
        } catch (_) {
            throw new Error(text.substring(0, 200) || 'Resposta inválida');
        }
    })
    .then(res => {
        if (!res.ok || !res.data.success) {
            alert('Erro: ' + (res.data.error || 'Não foi possível salvar.'));
            btn.disabled = false;
            btn.textContent = questaoInvalidacaoAtual.invalidada ? 'Confirmar revalidação' : 'Confirmar invalidação';
            return;
        }
        alert(res.data.message || 'Questão atualizada com sucesso.');
        fecharModalInvalidacaoQuestao();
        location.reload();
    })
    .catch(err => {
        alert('Erro: ' + err.message);
        btn.disabled = false;
        btn.textContent = questaoInvalidacaoAtual.invalidada ? 'Confirmar revalidação' : 'Confirmar invalidação';
    });
}

var modalInvalidarQuestaoEl = document.getElementById('modalInvalidarQuestao');
if (modalInvalidarQuestaoEl) {
    modalInvalidarQuestaoEl.addEventListener('click', function(e) {
        if (e.target === this) fecharModalInvalidacaoQuestao();
    });
}
var modalSenhaInvalidarQuestaoEl = document.getElementById('modalSenhaInvalidarQuestao');
if (modalSenhaInvalidarQuestaoEl) {
    modalSenhaInvalidarQuestaoEl.addEventListener('click', function(e) {
        if (e.target === this) fecharModalInvalidacaoQuestao();
    });
}
</script>
