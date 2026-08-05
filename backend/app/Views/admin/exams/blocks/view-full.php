<?php
/**
 * Visualizar Bloco Completo
 * Mostra todas as provas do bloco juntas
 */
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../../../Core/LayoutHelper.php';
}
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Prova Completa do Bloco: <?= htmlspecialchars($bloco['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Data: <?= date('d/m/Y', strtotime($bloco['data_prova'])) ?> - 
                Horário: <?= date('H:i', strtotime($bloco['hora_inicio'])) ?> às <?= date('H:i', strtotime($bloco['hora_fim'])) ?>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <?php
            $statusBloco = $bloco['status'] ?? 'aguardando';
            $jaLiberado = !empty($bloco['liberado']) || $statusBloco === 'liberado';
            $podeLiberar = ($statusBloco === 'aprovado');
            ?>
            <?php if ($jaLiberado): ?>
                <span class="px-4 py-2 rounded-lg bg-green-100 text-green-800 font-medium">
                    ✓ Liberada para alunos
                </span>
            <?php elseif ($podeLiberar): ?>
                <button type="button" id="btnLiberarProvaAluno" onclick="liberarProvaParaAluno(<?= (int)$bloco['id'] ?>)"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                    Liberar prova para aluno
                </button>
            <?php else: ?>
                <span class="px-4 py-2 rounded-lg bg-gray-100 text-gray-600 font-medium">
                    <?= $statusBloco === 'aguardando' ? 'Aprove o bloco em Gerenciar para depois liberar.' : ($statusBloco === 'concluido' ? 'Bloco já concluído.' : 'Status: ' . ucfirst($statusBloco)) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($provas)): ?>
                <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/prova-aluno-pdf" target="_blank"
                   class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    Prova Aluno PDF
                </a>
                <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/imprimir" target="_blank"
                   class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-lg text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Imprimir / Salvar em PDF
                </a>
            <?php endif; ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/gerenciar"
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                Voltar
            </a>
        </div>
    </div>
</div>

<script>
function liberarProvaParaAluno(blocoId) {
    if (!confirm('Liberar este bloco de provas para os alunos? A prova ficará disponível na data e no horário programados (<?= date('d/m/Y', strtotime($bloco['data_prova'])) ?> das <?= date('H:i', strtotime($bloco['hora_inicio'])) ?> às <?= date('H:i', strtotime($bloco['hora_fim'])) ?>).')) {
        return;
    }
    const btn = document.getElementById('btnLiberarProvaAluno');
    if (btn) { btn.disabled = true; btn.textContent = 'Liberando...'; }
    fetch(`<?= URL ?>/admin/provas/blocos/${blocoId}/toggle-liberado`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Bloco liberado. Os alunos poderão acessar na data e no horário programados.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível liberar.'));
            if (btn) { btn.disabled = false; btn.textContent = 'Liberar prova para aluno'; }
        }
    })
    .catch(() => {
        alert('Erro de conexão.');
        if (btn) { btn.disabled = false; btn.textContent = 'Liberar prova para aluno'; }
    });
}
</script>

<!-- Provas do Bloco -->
<?php if (empty($provas)): ?>
    <div class="bg-white rounded-xl shadow-lg p-12 text-center">
        <p class="text-gray-500 text-lg">Nenhuma prova encontrada neste bloco.</p>
    </div>
<?php else: ?>
    <div class="space-y-8">
        <?php foreach ($provas as $prova): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="border-b border-gray-200 pb-4 mb-6">
                    <h3 class="text-xl font-bold text-gray-900">
                        <?= htmlspecialchars($prova['titulo']) ?>
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        <span class="font-semibold"><?= htmlspecialchars($prova['materia_nome']) ?></span> - 
                        Prof. <?= htmlspecialchars($prova['professor_nome']) ?>
                    </p>
                </div>
                
                <?php if (empty($prova['questoes'])): ?>
                    <p class="text-gray-500">Nenhuma questão cadastrada nesta prova.</p>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($prova['questoes'] as $index => $questao): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-3">
                                    <h4 class="text-lg font-semibold text-gray-900">
                                        Questão <?= $index + 1 ?> 
                                        <span class="text-sm font-normal text-gray-600">
                                            (<?= htmlspecialchars($questao['tipo'] === 'multipla_escolha' ? 'Múltipla Escolha' : ($questao['tipo'] === 'verdadeiro_falso' ? 'Verdadeiro/Falso' : 'Dissertativa')) ?>)
                                        </span>
                                        <?php if (!empty($questao['invalidada'])): ?>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Invalidada</span>
                                        <?php endif; ?>
                                    </h4>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-gray-700 font-medium mb-2">Enunciado:</p>
                                    <div class="text-gray-900"><?= isset($questao['enunciado']) ? LayoutHelper::renderEnunciadoProva($questao['enunciado']) : '' ?></div>
                                </div>
                                
                                <?php if ($questao['imagem_url']): ?>
                                    <div class="mb-4">
                                        <img src="<?= htmlspecialchars($questao['imagem_url']) ?>" 
                                             alt="Imagem da questão" 
                                             class="max-w-full max-h-64 rounded-lg border border-gray-300">
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($questao['tipo'] === 'multipla_escolha' && !empty($questao['alternativas'])): ?>
                                    <div class="mb-4">
                                        <p class="text-gray-700 font-medium mb-2">Alternativas:</p>
                                        <div class="space-y-2">
                                            <?php foreach ($questao['alternativas'] as $alt): ?>
                                                <div class="p-3 rounded-lg border-2 <?= $alt['correta'] ? 'bg-green-50 border-green-500' : 'bg-gray-50 border-gray-300' ?>">
                                                    <div class="flex items-center">
                                                        <?php if ($alt['correta']): ?>
                                                            <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                            </svg>
                                                        <?php endif; ?>
                                                        <span class="<?= $alt['correta'] ? 'font-semibold text-green-900' : 'text-gray-700' ?>">
                                                            <?= isset($alt['texto']) ? LayoutHelper::renderEnunciadoProva($alt['texto']) : '' ?>
                                                            <?php if ($alt['correta']): ?>
                                                                <span class="ml-2 text-xs text-green-700">(Correta)</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php elseif ($questao['tipo'] === 'verdadeiro_falso'): ?>
                                    <div class="mb-4">
                                        <p class="text-gray-700 font-medium mb-2">Resposta Correta:</p>
                                        <p class="text-gray-900 font-semibold">
                                            <?= $questao['resposta_correta'] ? 'Verdadeiro' : 'Falso' ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
