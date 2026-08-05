<?php
$base = $base_url_jornadas ?? (URL . '/monitor/aluno/' . (int)$aluno['id']);
$eng = $engajamento ?? [];
$coresEng = [
    'green' => 'bg-green-50 border-green-200 text-green-800',
    'amber' => 'bg-amber-50 border-amber-200 text-amber-800',
    'gray' => 'bg-gray-50 border-gray-200 text-gray-700',
];
$engClass = $coresEng[$eng['cor'] ?? 'gray'] ?? $coresEng['gray'];
?>
<div class="mb-6">
    <a href="<?= htmlspecialchars($base) ?>" class="text-teal-600 hover:text-teal-800 text-sm font-medium">← Voltar ao aluno</a>
    <h1 class="text-2xl font-bold text-gray-900 mt-2"><?= htmlspecialchars($jornada['titulo']) ?></h1>
    <p class="text-gray-600"><?= htmlspecialchars($aluno['nome']) ?> • RA <?= htmlspecialchars($aluno['ra'] ?? '-') ?></p>
</div>

<div class="mb-6 rounded-xl border p-4 <?= $engClass ?>">
    <p class="font-semibold"><?= htmlspecialchars($eng['label'] ?? 'Status desconhecido') ?></p>
    <?php if (($eng['codigo'] ?? '') === 'fez'): ?>
        <p class="text-sm mt-1 opacity-90"><?= (int)($eng['respondidos'] ?? 0) ?> exercício(s) com resposta enviada</p>
    <?php elseif (($eng['codigo'] ?? '') === 'viu'): ?>
        <p class="text-sm mt-1 opacity-90">O aluno entrou na jornada, mas ainda não enviou respostas dos exercícios.</p>
    <?php else: ?>
        <p class="text-sm mt-1 opacity-90">Nenhum registro de que o aluno tenha aberto esta jornada.</p>
    <?php endif; ?>
</div>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500">Total</p>
        <p class="text-2xl font-bold"><?= (int)$estatisticas['total'] ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500">Respondidos</p>
        <p class="text-2xl font-bold text-blue-600"><?= (int)$estatisticas['respondidos'] ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500">Acertos</p>
        <p class="text-2xl font-bold text-green-600"><?= (int)$estatisticas['acertos'] ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500">Erros</p>
        <p class="text-2xl font-bold text-red-600"><?= (int)$estatisticas['erros'] ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500">Nota</p>
        <p class="text-2xl font-bold text-purple-600"><?= number_format((float)($estatisticas['nota_total'] ?? 0), 1) ?></p>
    </div>
</div>

<div class="space-y-4">
    <?php foreach ($exercicios as $i => $ex): ?>
        <?php
        $respostaAluno = $ex['resposta_aluno'] ?? '';
        if ($respostaAluno !== '' && (strpos($respostaAluno, '{') === 0 || strpos($respostaAluno, '[') === 0)) {
            $decoded = json_decode($respostaAluno, true);
            if (is_array($decoded)) {
                $respostaAluno = (string) ($decoded['resposta'] ?? $respostaAluno);
            }
        }
        $respondido = trim((string) $respostaAluno) !== '';
        $pontuacao = (float) ($ex['pontuacao_aluno'] ?? 0);
        $correto = $respondido && $pontuacao > 0;
        $border = $correto ? 'border-green-200' : ($respondido ? 'border-red-200' : 'border-gray-200');
        ?>
        <div class="bg-white rounded-xl shadow p-5 border-2 <?= $border ?>">
            <div class="flex flex-wrap justify-between items-start gap-2 mb-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs px-2 py-1 rounded-full bg-teal-100 text-teal-800 font-medium">Questão <?= $i + 1 ?></span>
                    <?php if (!empty($ex['modulo_titulo'])): ?>
                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600"><?= htmlspecialchars($ex['modulo_titulo']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($respondido): ?>
                    <span class="text-xs px-2 py-1 rounded-full <?= $correto ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                        <?= $correto ? 'Acertou' : 'Errou' ?>
                    </span>
                <?php else: ?>
                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">Sem resposta</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($ex['titulo'])): ?>
                <h3 class="font-semibold text-gray-900 mb-2"><?= htmlspecialchars($ex['titulo']) ?></h3>
            <?php endif; ?>

            <?php if (!empty($ex['enunciado_html'])): ?>
                <div class="text-sm text-gray-700 mb-3 prose prose-sm max-w-none monitor-enunciado"><?= $ex['enunciado_html'] ?></div>
            <?php elseif (!empty($ex['enunciado'])): ?>
                <p class="text-sm text-gray-700 mb-3 whitespace-pre-wrap"><?= htmlspecialchars($ex['enunciado']) ?></p>
            <?php endif; ?>

            <?php if (!empty($ex['imagem_url'])): ?>
                <div class="mb-4">
                    <img src="<?= htmlspecialchars($ex['imagem_url'], ENT_QUOTES, 'UTF-8') ?>"
                         alt="Imagem do enunciado"
                         class="max-w-full max-h-96 rounded-lg border border-gray-200 shadow-sm"
                         loading="lazy"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <p class="hidden text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 mt-2">
                        Não foi possível carregar a imagem deste exercício. O arquivo pode estar ausente no servidor.
                    </p>
                </div>
            <?php endif; ?>

            <?php if (($ex['tipo'] ?? '') === 'alternativas' && !empty($ex['questoes_json'])): ?>
                <?php
                $questoes = is_array($ex['questoes_json']) ? $ex['questoes_json'] : json_decode($ex['questoes_json'], true);
                $opcoes = [];
                if (is_array($questoes)) {
                    if (isset($questoes['opcoes']) && is_array($questoes['opcoes'])) {
                        $opcoes = $questoes['opcoes'];
                    } elseif (isset($questoes[0]['letra'])) {
                        $opcoes = $questoes;
                    }
                }
                ?>
                <?php if (!empty($opcoes)): ?>
                    <div class="bg-gray-50 rounded-lg p-3 mb-3 space-y-2">
                        <p class="text-xs font-semibold text-gray-600 uppercase">Alternativas</p>
                        <?php foreach ($opcoes as $opcao): ?>
                            <?php
                            $letra = strtoupper(trim($opcao['letra'] ?? ''));
                            $isCorreta = !empty($opcao['correta']);
                            $isAluno = $respondido && strtoupper(trim($respostaAluno)) === $letra;
                            ?>
                            <div class="flex items-center gap-2 p-2 rounded text-sm <?= $isCorreta ? 'bg-green-50' : ($isAluno ? 'bg-red-50' : '') ?>">
                                <span class="font-medium"><?= htmlspecialchars($letra) ?>.</span>
                                <span><?= htmlspecialchars($opcao['texto'] ?? $opcao['text'] ?? '') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($respondido): ?>
                <div class="bg-teal-50 border border-teal-100 rounded-lg p-3 text-sm">
                    <strong class="text-teal-800">Resposta do aluno:</strong>
                    <div class="mt-1 text-gray-800"><?= nl2br(htmlspecialchars($respostaAluno)) ?></div>
                    <?php if ($respondido): ?>
                        <p class="text-xs text-gray-500 mt-2">Pontuação: <?= number_format($pontuacao, 1) ?> / <?= number_format((float)($ex['pontuacao'] ?? 1), 1) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($exercicios)): ?>
        <p class="text-center text-gray-500 py-8 bg-white rounded-xl">Nenhum exercício nesta jornada.</p>
    <?php endif; ?>
</div>

<style>
.monitor-enunciado img { max-width: 100%; height: auto; border-radius: 0.5rem; margin: 0.5rem 0; }
</style>
