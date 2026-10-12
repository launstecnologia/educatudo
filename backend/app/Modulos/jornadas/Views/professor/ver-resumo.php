<?php
/**
 * EducaTudo - Página única: ver resumo do aluno + atribuir nota e observações
 */
$r = $resumo;
$analise = json_decode($r['analise_ia'] ?? '{}', true);
$textoResumo = !empty($r['resumo_texto']) ? $r['resumo_texto'] : ($r['resumo_aluno'] ?? '');
$notaAtual = $r['nota'] !== null && $r['nota'] !== '' ? (float)$r['nota'] : '';
$observacoesAtuais = $r['observacoes_professor'] ?? '';
$voltarUrl = $voltar_url ?? (URL . '/professor/jornadas/' . (int)$jornada['id'] . '/exercicios-alunos?tab=resumos');
$urlAtribuir = $url_atribuir_nota ?? (URL . '/professor/jornadas/resumos/atribuir-nota');
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Resumo do aluno</h1>
            <a href="<?= $voltarUrl ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar aos resultados
            </a>
        </div>

        <div class="space-y-6">
            <!-- Informações do Aluno -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-3">Informações do aluno</h2>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-500">Nome:</span>
                        <span class="ml-2 text-gray-900"><?= htmlspecialchars($r['nome_aluno']) ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">Aula / Módulo:</span>
                        <span class="ml-2 text-gray-900"><?= htmlspecialchars($r['nome_aula'] ?? '-') ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">Data de entrega:</span>
                        <span class="ml-2 text-gray-900"><?= $r['created_at'] ? date('d/m/Y H:i', strtotime($r['created_at'])) : '-' ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-500">Status:</span>
                        <span class="ml-2"><?= $textoResumo !== '' ? 'Entregue' : 'Pendente' ?></span>
                    </div>
                </div>
            </div>

            <!-- Resumo do Aluno -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-3">Resumo do aluno</h2>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 prose prose-sm max-w-none text-gray-700">
                    <?php if ($textoResumo !== ''): ?>
                        <?= class_exists(\App\Utils\HtmlSanitizer::class) ? \App\Utils\HtmlSanitizer::displaySafe($textoResumo) : nl2br(htmlspecialchars($textoResumo)) ?>
                    <?php else: ?>
                        <p class="text-gray-500">Aluno ainda não entregou o resumo.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($analise['pontuacao'])): ?>
            <!-- Análise da IA -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-3">Análise da IA</h2>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-purple-700">Pontuação:</span>
                        <span class="text-lg font-bold text-purple-800"><?= (float)$analise['pontuacao'] ?>/10</span>
                    </div>
                    <?php if (!empty($analise['nivel_compreensao'])): ?>
                    <div>
                        <span class="font-medium text-purple-700">Nível de compreensão:</span>
                        <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium
                            <?= ($analise['nivel_compreensao'] ?? '') === 'avançado' ? 'bg-green-100 text-green-800' : (($analise['nivel_compreensao'] ?? '') === 'intermediário' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') ?>">
                            <?= htmlspecialchars($analise['nivel_compreensao']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($analise['pontos_acertados'])): ?>
                    <div>
                        <h3 class="font-medium text-green-700 mb-1">Pontos acertados</h3>
                        <ul class="text-sm text-green-600 space-y-1">
                            <?php foreach ($analise['pontos_acertados'] as $p): ?>
                                <li>• <?= htmlspecialchars($p) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($analise['lacunas_identificadas'])): ?>
                    <div>
                        <h3 class="font-medium text-red-700 mb-1">Lacunas identificadas</h3>
                        <ul class="text-sm text-red-600 space-y-1">
                            <?php foreach ($analise['lacunas_identificadas'] as $l): ?>
                                <li>• <?= htmlspecialchars($l) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Avaliação do Professor -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-3">Avaliação do professor</h2>
                <form id="formAvaliarResumo" class="space-y-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="resumo_id" value="<?= (int)$r['id'] ?>">
                    <div>
                        <label for="notaResumo" class="block text-sm font-medium text-gray-700 mb-1">Nota (0 a 10)</label>
                        <input type="number" id="notaResumo" name="nota" min="0" max="10" step="0.1" value="<?= $notaAtual !== '' ? htmlspecialchars((string)$notaAtual) : '' ?>"
                               class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="observacoesResumo" class="block text-sm font-medium text-gray-700 mb-1">Observações <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <textarea id="observacoesResumo" name="observacoes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500"><?= htmlspecialchars($observacoesAtuais) ?></textarea>
                        <p class="mt-1 text-xs text-gray-500">As observações podem ser exibidas ao aluno.</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Salvar nota
                        </button>
                        <a href="<?= $voltarUrl ?>" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
                <p id="msgSucesso" class="mt-2 text-sm text-green-600 hidden">Nota e observações salvas com sucesso.</p>
                <p id="msgErro" class="mt-2 text-sm text-red-600 hidden"></p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('formAvaliarResumo').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var msgSucesso = document.getElementById('msgSucesso');
    var msgErro = document.getElementById('msgErro');
    msgSucesso.classList.add('hidden');
    msgErro.classList.add('hidden');
    msgErro.textContent = '';

    fetch('<?= htmlspecialchars($urlAtribuir, ENT_QUOTES, 'UTF-8') ?>', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            msgSucesso.classList.remove('hidden');
        } else {
            msgErro.textContent = data.error || 'Erro ao salvar. Tente novamente.';
            msgErro.classList.remove('hidden');
        }
    })
    .catch(function() {
        msgErro.textContent = 'Erro ao salvar nota. Tente novamente.';
        msgErro.classList.remove('hidden');
    });
});
</script>
