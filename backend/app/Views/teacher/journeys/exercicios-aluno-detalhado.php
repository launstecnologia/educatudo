<?php
require_once __DIR__ . '/../../../Core/TenantRelease.php';
$ocultarTituloJornada = TenantRelease::shouldUse('jornadas_ocultar_titulo_v1', true);
$ocultarTituloExercicioJornada = TenantRelease::shouldUse('jornadas_ocultar_titulo_exercicio_v1', true);
?>
<!-- Header -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Prova Detalhada - <?= htmlspecialchars($aluno['nome']) ?>
            </h2>
            <p class="text-gray-600">
                <?php if (!$ocultarTituloJornada): ?>
                Jornada: <?= htmlspecialchars($jornada['titulo']) ?> •
                <?php endif; ?>
                RA: <?= htmlspecialchars($aluno['ra']) ?> •
                Série: <?= htmlspecialchars($aluno['serie'] ?? '-') ?>
            </p>
        </div>
        <div>
            <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/exercicios-alunos" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Total de Exercícios</p>
                <p class="text-3xl font-bold text-gray-900"><?= $estatisticas['total'] ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Acertos</p>
                <p class="text-3xl font-bold text-green-600"><?= $estatisticas['acertos'] ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Erros</p>
                <p class="text-3xl font-bold text-red-600"><?= $estatisticas['erros'] ?></p>
                <?php if (($estatisticas['pendentes'] ?? 0) > 0): ?>
                    <p class="text-xs text-amber-700 mt-1"><?= (int) $estatisticas['pendentes'] ?> aguardando correção</p>
                <?php endif; ?>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Nota Total</p>
                <p class="text-3xl font-bold text-purple-600"><?= number_format($estatisticas['nota_total'], 1) ?></p>
                <p class="text-xs text-gray-500 mt-1"><?= $estatisticas['percentual'] ?>% de acerto</p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Exercícios -->
<?php if (empty($exercicios)): ?>
    <div class="bg-white rounded-xl shadow-lg p-12 border border-gray-200 text-center">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <p class="text-gray-500 text-lg mb-2">Nenhum exercício encontrado nesta jornada</p>
        <p class="text-sm text-gray-400 mb-4">Esta jornada não possui exercícios criados ainda.</p>
        <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/modulos" 
           class="inline-block px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
            Gerenciar Módulos e Exercícios
        </a>
    </div>
<?php else: ?>
<div class="space-y-6">
    <?php foreach ($exercicios as $index => $exercicio): ?>
        <?php 
        $respostaAlunoRaw = $exercicio['resposta_aluno'] ?? '';
        $pontuacao = $exercicio['pontuacao_aluno'] ?? null;
        $respondido = !empty($respostaAlunoRaw);
        $resultadoEx = JornadaExercicioAvaliacao::classificar(
            $exercicio['tipo'] ?? '',
            $pontuacao,
            $respostaAlunoRaw,
            $respondido
        );
        $pendente = $resultadoEx === JornadaExercicioAvaliacao::STATUS_PENDENTE;
        $correto = $resultadoEx === JornadaExercicioAvaliacao::STATUS_ACERTO;
        $respostaAluno = $respostaAlunoRaw;
        
        // Decodifica resposta se for JSON
        if ($respondido && (strpos($respostaAluno, '{') === 0 || strpos($respostaAluno, '[') === 0)) {
            $respostaDecodificada = json_decode($respostaAluno, true);
            if (is_array($respostaDecodificada)) {
                $respostaAluno = $respostaDecodificada['resposta'] ?? $respostaAluno;
            }
        }
        ?>
        <div class="bg-white rounded-xl shadow-lg p-6 border-2 <?= $correto ? 'border-green-200' : ($pendente ? 'border-amber-200' : ($respondido ? 'border-red-200' : 'border-gray-200')) ?>">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <div class="flex items-center space-x-3 mb-2">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-semibold rounded-full">
                            Questão <?= $index + 1 ?>
                        </span>
                        <span class="px-3 py-1 bg-gray-100 text-gray-800 text-sm rounded-full">
                            <?= htmlspecialchars($exercicio['modulo_titulo']) ?>
                        </span>
                        <?php if ($respondido): ?>
                            <?php if ($pendente): ?>
                                <span class="px-3 py-1 bg-amber-100 text-amber-800 text-sm font-semibold rounded-full">
                                    Aguardando correção
                                </span>
                            <?php elseif ($correto): ?>
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-semibold rounded-full">
                                    ✓ Correto
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-red-100 text-red-800 text-sm font-semibold rounded-full">
                                    ✗ Errado
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-sm font-semibold rounded-full">
                                Não respondido
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$ocultarTituloExercicioJornada): ?>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                        <?= htmlspecialchars($exercicio['titulo']) ?>
                    </h3>
                    <?php endif; ?>
                    <p class="text-gray-700 mb-4">
                        <?= htmlspecialchars($exercicio['enunciado']) ?>
                    </p>
                    <?php if (!empty($exercicio['imagem_url'])): ?>
                    <?php
                        $img_url = $exercicio['imagem_url'];
                        $img_url = preg_replace('#/public/uploads/#', '/uploads/', $img_url);
                    ?>
                    <div class="mb-4">
                        <img src="<?= htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8') ?>" alt="Imagem do enunciado" class="max-w-full rounded-lg border border-gray-200 shadow-sm">
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($respondido): ?>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Pontuação</p>
                        <?php if ($pendente): ?>
                            <p class="text-2xl font-bold text-amber-600">—</p>
                            <p class="text-xs text-amber-700">Aguardando nota</p>
                        <?php else: ?>
                            <p class="text-2xl font-bold <?= $correto ? 'text-green-600' : 'text-red-600' ?>">
                                <?= number_format((float) $pontuacao, 1) ?> / <?= number_format($exercicio['pontuacao'] ?? 1, 1) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Alternativas -->
            <?php if ($exercicio['tipo'] === 'alternativas'): ?>
                <?php 
                $questoes = null;
                $opcoes = [];
                if (!empty($exercicio['questoes_json'])) {
                    $questoes = is_array($exercicio['questoes_json']) 
                        ? $exercicio['questoes_json'] 
                        : json_decode($exercicio['questoes_json'], true);
                    // Se foi salvo duplamente codificado (exercício manual antigo), decodifica de novo
                    if (is_string($questoes)) {
                        $questoes = json_decode($questoes, true);
                    }
                    if (is_array($questoes)) {
                        if (isset($questoes['opcoes']) && is_array($questoes['opcoes'])) {
                            $opcoes = $questoes['opcoes'];
                        } elseif (isset($questoes[0]) && is_array($questoes[0]) && isset($questoes[0]['letra'])) {
                            $opcoes = $questoes;
                        }
                    }
                }
                if (!empty($opcoes)): 
                ?>
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <p class="text-sm font-semibold text-gray-700 mb-3">Alternativas:</p>
                        <div class="space-y-2">
                            <?php foreach ($opcoes as $opcao): ?>
                                <?php 
                                $isCorreta = $opcao['correta'] ?? false;
                                $isRespostaAluno = $respondido && strtoupper(trim($respostaAluno)) === strtoupper(trim($opcao['letra']));
                                $bgColor = '';
                                if ($isCorreta) {
                                    $bgColor = 'bg-green-50 border-l-4 border-green-500';
                                } elseif ($isRespostaAluno && !$isCorreta) {
                                    $bgColor = 'bg-red-50 border-l-4 border-red-500';
                                }
                                ?>
                                <?php $textoOpcao = $opcao['texto'] ?? $opcao['text'] ?? ''; ?>
                                <div class="flex items-center space-x-2 p-3 rounded <?= $bgColor ?>">
                                    <span class="font-medium <?= $isCorreta ? 'text-green-700' : ($isRespostaAluno ? 'text-red-700' : 'text-gray-700') ?>">
                                        <?= htmlspecialchars($opcao['letra'] ?? '') ?>.
                                    </span>
                                    <span class="<?= $isCorreta ? 'text-green-700 font-semibold' : ($isRespostaAluno ? 'text-red-700 font-semibold' : 'text-gray-700') ?>">
                                        <?= htmlspecialchars($textoOpcao) ?>
                                    </span>
                                    <?php if ($isCorreta): ?>
                                        <span class="ml-auto px-2 py-1 bg-green-200 text-green-800 text-xs rounded">Correta</span>
                                    <?php elseif ($isRespostaAluno): ?>
                                        <span class="ml-auto px-2 py-1 bg-red-200 text-red-800 text-xs rounded">Sua resposta</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($exercicio['tipo'] === 'alternativas' && $respondido): ?>
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <p class="text-sm font-semibold text-gray-700 mb-2">Resposta do aluno:</p>
                        <p class="text-gray-800"><?= htmlspecialchars($respostaAluno) ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Resposta do Aluno (para questões dissertativas) -->
            <?php if ($exercicio['tipo'] === 'dissertativa' && $respondido): ?>
                <?php 
                $pontuacaoMax = (float) ($exercicio['pontuacao'] ?? 1);
                $notaAtual = ($pontuacao !== null && $pontuacao !== '' && !$pendente) ? (float) $pontuacao : '';
                $respostaDisplay = class_exists(\App\Utils\HtmlSanitizer::class) ? \App\Utils\HtmlSanitizer::displaySafe($respostaAluno) : nl2br(htmlspecialchars($respostaAluno));
                ?>
                <div class="bg-blue-50 rounded-lg p-4 mb-4 border-l-4 border-blue-500">
                    <p class="text-sm font-semibold text-blue-700 mb-2">Resposta do Aluno:</p>
                    <div class="text-gray-800 prose prose-sm max-w-none"><?= $respostaDisplay ?></div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200" data-exercicio-dissertativa="<?= $exercicio['id'] ?>">
                    <p class="text-sm font-semibold text-gray-700 mb-2">Atribuir nota ao aluno</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="text-sm text-gray-600">Nota (0 a <?= number_format($pontuacaoMax, 0) ?>):</label>
                        <input type="number" 
                               step="0.1" min="0" max="<?= htmlspecialchars($pontuacaoMax) ?>" 
                               value="<?= $notaAtual === '' ? '' : htmlspecialchars((string) $notaAtual) ?>"
                               class="nota-dissertativa-input w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               data-exercicio-id="<?= $exercicio['id'] ?>"
                               data-pontuacao-max="<?= htmlspecialchars($pontuacaoMax) ?>">
                        <button type="button" 
                                class="salvar-nota-dissertativa px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
                                data-exercicio-id="<?= $exercicio['id'] ?>"
                                data-aluno-id="<?= $aluno['id'] ?>"
                                data-jornada-id="<?= $jornada['id'] ?>">
                            Salvar nota
                        </button>
                        <span class="msg-nota-dissertativa text-sm hidden"></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Gabarito (se houver) -->
            <?php if (!empty($exercicio['gabarito'])): ?>
                <div class="bg-gray-50 rounded-lg p-4 border-t border-gray-200 mt-4">
                    <p class="text-sm font-semibold text-gray-700 mb-2">Gabarito:</p>
                    <p class="text-gray-800"><?= htmlspecialchars($exercicio['gabarito']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<script>
(function() {
    var baseUrl = '<?= rtrim(URL, "/") ?>';
    var csrfToken = '<?= htmlspecialchars($csrf_token ?? $_SESSION["csrf_token"] ?? "", ENT_QUOTES, "UTF-8") ?>';
    document.querySelectorAll('.salvar-nota-dissertativa').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var exercicioId = this.getAttribute('data-exercicio-id');
            var alunoId = this.getAttribute('data-aluno-id');
            var jornadaId = this.getAttribute('data-jornada-id');
            var card = this.closest('[data-exercicio-dissertativa]');
            var input = card ? card.querySelector('.nota-dissertativa-input') : null;
            var msgEl = card ? card.querySelector('.msg-nota-dissertativa') : null;
            if (!input) return;
            var pontuacao = parseFloat(input.value);
            if (isNaN(pontuacao) || pontuacao < 0) {
                if (msgEl) { msgEl.textContent = 'Informe uma nota válida.'; msgEl.classList.remove('hidden', 'text-green-600'); msgEl.classList.add('text-red-600'); }
                return;
            }
            var max = parseFloat(input.getAttribute('data-pontuacao-max') || '1');
            if (pontuacao > max) pontuacao = max;
            btn.disabled = true;
            if (msgEl) { msgEl.textContent = 'Salvando...'; msgEl.classList.remove('hidden', 'text-green-600', 'text-red-600'); }
            var formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('exercicio_id', exercicioId);
            formData.append('aluno_id', alunoId);
            formData.append('jornada_id', jornadaId);
            formData.append('pontuacao', pontuacao);
            fetch(baseUrl + '/professor/jornadas/exercicios/atribuir-nota-dissertativa', {
                method: 'POST',
                body: formData
            }).then(function(r) { return r.json(); }).then(function(data) {
                btn.disabled = false;
                if (msgEl) {
                    msgEl.classList.remove('text-red-600');
                    msgEl.classList.add('text-green-600');
                    msgEl.textContent = data.success ? 'Nota salva com sucesso.' : (data.error || 'Erro ao salvar.');
                }
                if (data.success) {
                    setTimeout(function() { window.location.reload(); }, 800);
                }
            }).catch(function() {
                btn.disabled = false;
                if (msgEl) { msgEl.textContent = 'Erro de conexão.'; msgEl.classList.remove('hidden', 'text-green-600'); msgEl.classList.add('text-red-600'); }
            });
        });
    });
})();
</script>
<?php endif; ?>

