<!-- Header Section -->
<div class="mb-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">
            Resultado da Prova: <?= htmlspecialchars($prova['titulo']) ?>
        </h2>
        <p class="text-gray-600">
            <?= htmlspecialchars($prova['materia_nome']) ?>
        </p>
    </div>
</div>

<?php
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../../Core/LayoutHelper.php';
}
$acertos = 0;
$erros = 0;
foreach ($questoes as $questao) {
    $r = $questao['resposta'] ?? null;
    if ($r !== null) {
        if (!empty($r['correta'])) {
            $acertos++;
        } else {
            $erros++;
        }
    }
}
$totalQuestoes = $acertos + $erros;
$percentual = $totalQuestoes > 0 ? ($acertos / $totalQuestoes) * 100 : 0;
$totalQuestoesProva = is_array($questoes ?? null) ? count($questoes) : 0;
$tiposRes = ['multipla_escolha' => 'Múltipla Escolha', 'verdadeiro_falso' => 'Verdadeiro/Falso', 'dissertativa' => 'Dissertativa'];
?>
<style>
    .resultado-questoes-frame {
        overflow: hidden;
    }
    .resultado-questoes-track {
        display: flex;
        transition: transform 240ms ease;
        will-change: transform;
    }
    .resultado-questao-slide {
        flex: 0 0 100%;
        min-width: 100%;
    }
    .resultado-questao-dot {
        cursor: pointer;
    }
</style>

<!-- Resultado: acertos, erros e percentual -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="text-center">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Seu Resultado</h3>
        <div class="flex justify-center gap-8 mb-4">
            <div>
                <div class="text-sm text-gray-600">Acertos</div>
                <div class="text-3xl font-bold text-green-600"><?= $acertos ?></div>
            </div>
            <div>
                <div class="text-sm text-gray-600">Erros</div>
                <div class="text-3xl font-bold text-red-600"><?= $erros ?></div>
            </div>
        </div>
        <div class="mt-4">
            <?php $classPercentual = $percentual >= 70 ? 'bg-green-600' : ($percentual >= 50 ? 'bg-yellow-600' : 'bg-red-600'); ?>
            <span class="px-4 py-2 rounded-full text-white font-semibold <?= $classPercentual ?>">
                <?= number_format($percentual, 1) ?>%
            </span>
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
            <p class="text-sm text-gray-600">Tempo Gasto</p>
            <p class="text-lg font-semibold">
                <?= $realizacao['tempo_gasto'] ? $realizacao['tempo_gasto'] . ' minutos' : '-' ?>
            </p>
        </div>
    </div>
</div>

<!-- Questões e Respostas -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Questões e Respostas</h3>
            <p class="text-sm text-gray-500 mt-1">Veja uma questão por vez e avance pela revisão.</p>
        </div>
        <?php if ($totalQuestoesProva > 0): ?>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-600">
                    <span id="resultadoQuestaoAtual">1</span> de <?= $totalQuestoesProva ?>
                </span>
                <div class="w-32 h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div id="resultadoQuestoesProgresso" class="h-full rounded-full bg-blue-600" style="width: <?= $totalQuestoesProva > 0 ? (100 / $totalQuestoesProva) : 0 ?>%;"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalQuestoesProva === 0): ?>
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-8 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-white text-gray-500 border border-gray-200">
                <i class="fa-solid fa-clipboard-question"></i>
            </div>
            <h4 class="text-lg font-semibold text-gray-900">Nenhuma questão disponível</h4>
            <p class="mt-2 text-sm text-gray-500">As questões desta prova aparecerão aqui quando estiverem disponíveis para revisão.</p>
        </div>
    <?php else: ?>
    <div class="resultado-questoes-frame">
        <div id="resultadoQuestoesTrack" class="resultado-questoes-track">
        <?php foreach ($questoes as $index => $questao): ?>
            <?php $resposta = $questao['resposta'] ?? null; ?>
            <div class="resultado-questao-slide pr-0">
                <div class="border rounded-xl p-5 <?= $resposta && $resposta['correta'] ? 'bg-green-50 border-green-300' : ($resposta && !$resposta['correta'] ? 'bg-red-50 border-red-300' : 'border-gray-200 bg-white') ?>">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-sm font-semibold text-gray-800 border border-gray-200">
                                    Questão <?= $index + 1 ?>
                                </span>
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                    <?= htmlspecialchars((string) ($tiposRes[$questao['tipo']] ?? $questao['tipo'])) ?>
                                </span>
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 border border-blue-100">
                                    <?= number_format((float) $questao['valor'], 2, ',', '.') ?> pt(s)
                                </span>
                            </div>
                        </div>
                        <?php if ($resposta): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold <?= $resposta['correta'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <i class="fa-solid <?= $resposta['correta'] ? 'fa-check' : 'fa-xmark' ?>"></i>
                            <?= $resposta['correta'] ? 'Correta' : 'Incorreta' ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="text-gray-700 mb-5 text-lg prose prose-sm max-w-none"><?= isset($questao['enunciado']) ? LayoutHelper::renderEnunciadoProva($questao['enunciado']) : '' ?></div>

                    <?php if (!empty($questao['imagem_url'])): ?>
                        <div class="mb-5">
                            <img src="<?= htmlspecialchars($questao['imagem_url']) ?>" alt="Imagem da questão"
                                 class="max-w-md rounded-lg border border-gray-300">
                        </div>
                    <?php endif; ?>

                    <?php if ($questao['tipo'] === 'multipla_escolha' && !empty($questao['alternativas'])): ?>
                        <?php $alternativaIdAluno = isset($resposta['alternativa_id']) ? (int)$resposta['alternativa_id'] : null; ?>
                        <div class="space-y-3">
                            <?php foreach ($questao['alternativas'] as $alt): ?>
                                <?php
                                $ehCorreta = !empty($alt['correta']);
                                $ehAssinaladaPeloAluno = ($alternativaIdAluno !== null && (int)$alt['id'] === $alternativaIdAluno);
                                $mostrarSuaResposta = $ehAssinaladaPeloAluno && $resposta && empty($resposta['correta']);
                                $classeCaixa = $ehCorreta ? 'bg-green-100 border-green-400' : ($mostrarSuaResposta ? 'bg-red-100 border-red-400' : 'border-gray-200 bg-white');
                                ?>
                                <div class="flex items-center p-4 border-2 rounded-xl <?= $classeCaixa ?>">
                                    <?php if ($ehCorreta): ?>
                                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-green-500 flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                        </span>
                                    <?php elseif ($mostrarSuaResposta): ?>
                                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-red-500 flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                        </span>
                                    <?php else: ?>
                                        <span class="flex-shrink-0 w-6 h-6 rounded-full border-2 border-gray-300 mr-3"></span>
                                    <?php endif; ?>
                                    <span class="text-gray-700 flex-1 text-base"><?= isset($alt['texto']) ? LayoutHelper::renderEnunciadoProva($alt['texto']) : '' ?></span>
                                    <?php if ($ehCorreta): ?>
                                        <span class="text-green-700 font-semibold ml-2 flex-shrink-0">Correta</span>
                                    <?php elseif ($mostrarSuaResposta): ?>
                                        <span class="text-red-700 font-semibold ml-2 flex-shrink-0">Sua resposta</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($resposta): ?>
                            <div class="mt-4 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm border border-gray-200">
                                <span class="font-semibold text-gray-700">Pontuação obtida:</span>
                                <span class="<?= $resposta['correta'] ? 'text-green-600' : 'text-red-600' ?> font-semibold">
                                    <?= number_format((float) $resposta['pontuacao'], 2, ',', '.') ?> pontos
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($questao['tipo'] === 'dissertativa' && $resposta): ?>
                        <div class="bg-gray-50 rounded-lg p-4 mb-3">
                            <p class="text-sm font-semibold text-gray-700 mb-2">Sua Resposta:</p>
                            <p class="text-gray-700"><?= nl2br(htmlspecialchars($resposta['resposta_texto'])) ?></p>
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold">Pontuação obtida: </span>
                            <span class="<?= $resposta['correta'] ? 'text-green-600' : 'text-red-600' ?>">
                                <?= number_format((float) $resposta['pontuacao'], 2, ',', '.') ?> pontos
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <div class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <button type="button"
                id="resultadoQuestaoAnterior"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
            <i class="fa-solid fa-arrow-left"></i>
            Anterior
        </button>
        <div class="flex justify-center gap-1.5">
            <?php foreach ($questoes as $index => $_questao): ?>
                <button type="button"
                        class="resultado-questao-dot h-2.5 w-2.5 rounded-full bg-gray-300"
                        data-dot-index="<?= $index ?>"
                        aria-label="Ir para questão <?= $index + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <button type="button"
                id="resultadoQuestaoProxima"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
            Próxima
            <i class="fa-solid fa-arrow-right"></i>
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if ($totalQuestoesProva > 0): ?>
<script>
(function() {
    var total = <?= (int) $totalQuestoesProva ?>;
    var atual = 0;
    var track = document.getElementById('resultadoQuestoesTrack');
    var btnAnterior = document.getElementById('resultadoQuestaoAnterior');
    var btnProxima = document.getElementById('resultadoQuestaoProxima');
    var indicadorAtual = document.getElementById('resultadoQuestaoAtual');
    var progresso = document.getElementById('resultadoQuestoesProgresso');
    var dots = document.querySelectorAll('.resultado-questao-dot');

    function atualizar() {
        if (!track) return;
        track.style.transform = 'translateX(-' + (atual * 100) + '%)';
        if (indicadorAtual) indicadorAtual.textContent = String(atual + 1);
        if (progresso) progresso.style.width = (((atual + 1) / total) * 100) + '%';
        if (btnAnterior) btnAnterior.disabled = atual === 0;
        if (btnProxima) btnProxima.disabled = atual >= total - 1;
        dots.forEach(function(dot, index) {
            dot.classList.toggle('bg-blue-600', index === atual);
            dot.classList.toggle('bg-gray-300', index !== atual);
            if (index === atual) {
                dot.setAttribute('aria-current', 'step');
            } else {
                dot.removeAttribute('aria-current');
            }
        });
        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise().catch(function(e) {
                console.warn('MathJax:', e);
            });
        }
    }

    if (btnAnterior) {
        btnAnterior.addEventListener('click', function() {
            atual = Math.max(0, atual - 1);
            atualizar();
        });
    }
    if (btnProxima) {
        btnProxima.addEventListener('click', function() {
            atual = Math.min(total - 1, atual + 1);
            atualizar();
        });
    }
    dots.forEach(function(dot) {
        dot.addEventListener('click', function() {
            var index = parseInt(this.getAttribute('data-dot-index') || '0', 10);
            if (!isNaN(index)) {
                atual = Math.max(0, Math.min(total - 1, index));
                atualizar();
            }
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            atual = Math.max(0, atual - 1);
            atualizar();
        } else if (e.key === 'ArrowRight') {
            atual = Math.min(total - 1, atual + 1);
            atualizar();
        }
    });
    atualizar();
})();
</script>
<?php endif; ?>

<!-- Botão Voltar -->
<div class="mt-6 text-center">
    <a href="<?= URL ?>/aluno/provas"
       class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
        Voltar para Provas
    </a>
</div>
