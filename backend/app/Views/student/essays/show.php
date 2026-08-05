<?php
$primaryColor = LayoutHelper::get('primary_color', '#6366f1');
$startsAt = !empty($proposal['starts_at']) ? $proposal['starts_at'] : null;
?>
<style>
    .proposal-view .card-border { border-color: <?= htmlspecialchars($primaryColor) ?>40; }
</style>
<?php
$endsAt = !empty($proposal['ends_at']) ? $proposal['ends_at'] : null;
$themeMode = $proposal['theme_mode'] ?? 'configurar';
$hasCorrection = !empty($correction);
$submissionStatus = $submission['status'] ?? null;
$canDeleteSubmission = $submission && !$hasCorrection && $submissionStatus !== 'corrected';
$temaProntoFile = !empty($proposal['tema_pronto_file']) ? $proposal['tema_pronto_file'] : null;
$proposalImages = [];
if (!empty($proposal['images_json'])) {
    $proposalImages = is_string($proposal['images_json']) ? json_decode($proposal['images_json'], true) : $proposal['images_json'];
    if (!is_array($proposalImages)) $proposalImages = [];
}
$repertoriosList = [];
$hasRepertoire = false;
if (!empty($proposal['repertoire'])) {
    $raw = trim($proposal['repertoire']);
    if ($raw !== '' && $raw !== '[]' && $raw !== 'null') {
        if (preg_match('/^\s*\[/', $raw)) {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                $repertoriosList = array_filter($dec, fn($v) => is_string($v) && trim($v) !== '');
                $hasRepertoire = !empty($repertoriosList);
            }
        } else {
            $hasRepertoire = true;
        }
    }
}
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($proposal['title']) ?></h2>
    <p class="text-gray-600"><?= htmlspecialchars($proposal['board_name']) ?> — <?= htmlspecialchars($proposal['text_type_name']) ?></p>
    <?php if ($startsAt || $endsAt): ?>
    <p class="text-gray-600 text-sm mt-1">
        Disponível <?php
        if ($startsAt && $endsAt) {
            echo 'de ' . date('d/m/Y H:i', strtotime($startsAt)) . ' a ' . date('d/m/Y H:i', strtotime($endsAt));
        } elseif ($startsAt) {
            echo 'a partir de ' . date('d/m/Y H:i', strtotime($startsAt));
        } else {
            echo 'até ' . date('d/m/Y H:i', strtotime($endsAt));
        }
        ?>.
    </p>
    <?php endif; ?>
</div>

<div class="proposal-view grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Coluna esquerda: Tema (PDF/Imagem) -->
    <div class="space-y-4">
        <?php if ($themeMode === 'arquivo' && $temaProntoFile): ?>
        <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border card-border p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">📄 Tema da Proposta</h3>
            <?php if (preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', $temaProntoFile)): ?>
                <img src="<?= htmlspecialchars($temaProntoFile) ?>" alt="Tema" class="w-full max-h-[70vh] object-contain rounded border border-gray-200">
            <?php elseif (preg_match('/\.pdf(\?|$)/i', $temaProntoFile)): ?>
                <iframe src="<?= htmlspecialchars($temaProntoFile) ?>#toolbar=0" class="w-full h-[70vh] rounded border border-gray-200" frameborder="0"></iframe>
            <?php else: ?>
                <a href="<?= htmlspecialchars($temaProntoFile) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 hover:underline font-medium" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Abrir documento do tema
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($proposalImages)): ?>
        <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border card-border p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">🖼️ Imagens da Proposta</h3>
            <div class="space-y-3">
                <?php foreach ($proposalImages as $imgUrl): ?>
                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Imagem da proposta" class="w-full max-h-80 object-contain rounded border border-gray-200">
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($themeMode === 'configurar' && !empty($proposal['theme'])): ?>
        <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border card-border p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">📝 Tema</h3>
            <p class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($proposal['theme']) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($hasRepertoire): ?>
        <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border card-border p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">📚 Repertório / Instruções</h3>
            <?php if (!empty($repertoriosList)): ?>
            <div class="space-y-3">
                <?php foreach ($repertoriosList as $txt): ?>
                <div class="text-gray-700 border-l-2 pl-3 prose prose-sm max-w-none" style="border-color: <?= htmlspecialchars($primaryColor) ?>40;"><?= (is_string($txt) && strpos($txt, '<') !== false ? $txt : nl2br(htmlspecialchars((string) $txt))) ?></div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-gray-700 prose prose-sm max-w-none"><?= (strpos($proposal['repertoire'], '<') !== false ? $proposal['repertoire'] : nl2br(htmlspecialchars($proposal['repertoire']))) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($temaProntoFile) && empty($proposalImages) && empty($proposal['theme']) && !$hasRepertoire): ?>
        <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border card-border p-6 text-center text-gray-500">
            <p>Nenhum documento de tema disponível.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Coluna direita: Botão iniciar + Dicas -->
    <div class="space-y-4">
        <!-- Botão Iniciar -->
        <div class="rounded-xl shadow-lg p-6 text-white" style="background: linear-gradient(135deg, <?= htmlspecialchars($primaryColor) ?> 0%, <?= htmlspecialchars($primaryColor) ?>dd 100%);">
            <h3 class="text-xl font-bold mb-3">Pronto para escrever?</h3>
            <p class="mb-4 text-sm opacity-90">Leia atentamente o tema e as instruções antes de começar.</p>
            <div class="flex flex-col gap-3">
                <?php if ($submission && ($hasCorrection || $submissionStatus === 'corrected')): ?>
                    <?php if ($hasCorrection): ?>
                    <a href="<?= URL ?>/jornada-redacao/correcao/<?= (int)$submission['id'] ?>" class="block w-full bg-green-500 hover:bg-green-600 text-white text-center font-semibold px-6 py-3 rounded-lg transition">
                        ✅ Ver correção
                    </a>
                    <?php else: ?>
                    <div class="bg-white/15 border border-white/25 rounded-lg px-4 py-3 text-center">
                        <p class="font-semibold">Redação em correção final</p>
                        <p class="text-sm mt-1 opacity-90">Sua professora já avançou na correção, então a redação não pode mais ser editada nem excluída.</p>
                    </div>
                    <?php endif; ?>
                <?php elseif ($submission && $submissionStatus === 'submitted'): ?>
                    <div class="bg-white/15 border border-white/25 rounded-lg px-4 py-3 text-center">
                        <p class="font-semibold">Redação enviada para correção</p>
                        <p class="text-sm mt-1 opacity-90">Enquanto a professora ainda não corrigiu, você pode editar a redação e reenviar ou excluir e começar novamente.</p>
                    </div>
                    <a href="<?= URL ?>/jornada-redacao/<?= (int)$proposal['id'] ?>/escrever" class="block w-full bg-white text-center font-semibold px-6 py-3 rounded-lg transition hover:opacity-90" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                        ✏️ Editar redação enviada
                    </a>
                    <?php if ($canDeleteSubmission): ?>
                    <form action="<?= URL ?>/jornada-redacao/<?= (int)$proposal['id'] ?>/excluir-redacao" method="POST" onsubmit="return confirm('Deseja excluir sua redação enviada? Você poderá escrever novamente.');">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <button type="submit" class="block w-full bg-red-500 hover:bg-red-600 text-white text-center font-semibold px-6 py-3 rounded-lg transition">
                            🗑️ Excluir redação enviada
                        </button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?= URL ?>/jornada-redacao/<?= (int)$proposal['id'] ?>/escrever" class="block w-full bg-white text-center font-semibold px-6 py-3 rounded-lg transition hover:opacity-90" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                        ✍️ <?= $submission ? 'Continuar redação' : 'Escrever redação' ?>
                    </a>
                    <?php if ($canDeleteSubmission): ?>
                    <form action="<?= URL ?>/jornada-redacao/<?= (int)$proposal['id'] ?>/excluir-redacao" method="POST" onsubmit="return confirm('Deseja excluir sua redação atual?');">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <button type="submit" class="block w-full bg-red-500 hover:bg-red-600 text-white text-center font-semibold px-6 py-3 rounded-lg transition">
                            🗑️ Excluir redação
                        </button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="<?= URL ?>/jornada-redacao" class="block w-full text-white text-center px-6 py-3 rounded-lg transition hover:opacity-80" style="background-color: rgba(255,255,255,0.2);">
                    ← Voltar
                </a>
            </div>
        </div>

        <!-- Dicas de Redação -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border card-border p-5">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <span class="text-2xl">💡</span> Dicas de Redação (ENEM)
            </h3>
            
            <div class="space-y-4 text-sm text-gray-700">
                <!-- Dica 1 -->
                <details class="group">
                    <summary class="font-semibold cursor-pointer flex items-center gap-2" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                        🧠 1. Entenda o tema antes de escrever
                    </summary>
                    <div class="mt-2 pl-6 text-gray-600 space-y-1">
                        <p>• Leia o tema com atenção (2 a 3 vezes)</p>
                        <p>• Identifique: problema central e recorte (Brasil, sociedade, jovens, etc.)</p>
                        <p>• <strong>Evite fugir do tema</strong> (erro mais comum no ENEM)</p>
                    </div>
                </details>

                <!-- Dica 2 -->
                <details class="group">
                    <summary class="font-semibold cursor-pointer flex items-center gap-2" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                        🧱 2. Estrutura padrão (modelo seguro)
                    </summary>
                    <div class="mt-2 pl-6 text-gray-600 space-y-2">
                        <p><strong>Introdução (5–6 linhas):</strong> Contextualização + Apresentação do tema + Tese (sua opinião)</p>
                        <p><strong>Desenvolvimento 1:</strong> Primeiro argumento + Explicação + Exemplo (dados, fatos ou repertório)</p>
                        <p><strong>Desenvolvimento 2:</strong> Segundo argumento + Explicação + Exemplo</p>
                        <p><strong>Conclusão:</strong> Retoma o problema + Apresenta solução (intervenção completa)</p>
                    </div>
                </details>

                <!-- Dica 3 -->
                <details class="group">
                    <summary class="font-semibold cursor-pointer flex items-center gap-2" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                        🧩 3. Modelo de introdução
                    </summary>
                    <div class="mt-2 pl-6 text-gray-600">
                        <p class="italic border-l-2 pl-3" style="border-color: <?= htmlspecialchars($primaryColor) ?>40;">"Diante do contexto [social/histórico], observa-se que [tema]. Nesse sentido, é fundamental analisar [argumento 1] e [argumento 2], que contribuem para a persistência desse problema no Brasil."</p>
                    </div>
                </details>

                <!-- Dica 4 -->
                <details class="group">
                    <summary class="font-semibold cursor-pointer flex items-center gap-2" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                        📖 4. Use repertório sociocultural
                    </summary>
                    <div class="mt-2 pl-6 text-gray-600 space-y-1">
                        <p>• Filosofia (Aristóteles, Bauman)</p>
                        <p>• História (Revolução Industrial)</p>
                        <p>• Leis (Constituição de 1988)</p>
                        <p>• Atualidades</p>
                        <p class="italic mt-2" style="color: <?= htmlspecialchars($primaryColor) ?>;">"Segundo a Constituição Federal de 1988, todos têm direito à igualdade…"</p>
                    </div>
                </details>

                <!-- Dica 5 -->
                <details class="group">
                    <summary class="font-semibold cursor-pointer flex items-center gap-2" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                        ⚠️ 5. Erros que fazem perder pontos
                    </summary>
                    <div class="mt-2 pl-6 text-gray-600 space-y-1">
                        <p>• Fugir do tema</p>
                        <p>• Não apresentar proposta de intervenção</p>
                        <p>• Texto muito curto</p>
                        <p>• Linguagem informal</p>
                        <p>• Falta de parágrafos</p>
                    </div>
                </details>

                <!-- Dica 6 -->
                <details class="group">
                    <summary class="font-semibold cursor-pointer flex items-center gap-2" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                        🛠️ 6. Modelo de conclusão (nota alta)
                    </summary>
                    <div class="mt-2 pl-6 text-gray-600">
                        <p class="italic border-l-2 pl-3" style="border-color: <?= htmlspecialchars($primaryColor) ?>40;">"Portanto, é necessário que o [agente: governo/escola/mídia] promova [ação], por meio de [meio], com o objetivo de [finalidade], a fim de combater [problema]."</p>
                        <p class="mt-2 font-medium">Estrutura da intervenção:</p>
                        <p>• <strong>Agente</strong> (quem faz) • <strong>Ação</strong> (o que faz) • <strong>Meio</strong> (como faz) • <strong>Finalidade</strong> (para quê)</p>
                    </div>
                </details>

                <!-- Dica 7 -->
                <details class="group">
                    <summary class="font-semibold cursor-pointer flex items-center gap-2" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                        🔗 7. Conectivos que aumentam sua nota
                    </summary>
                    <div class="mt-2 pl-6 text-gray-600">
                        <p><strong>Introdução:</strong> Diante disso, Nesse contexto</p>
                        <p><strong>Desenvolvimento:</strong> Além disso, Ademais</p>
                        <p><strong>Contraste:</strong> Entretanto, Porém</p>
                        <p><strong>Conclusão:</strong> Portanto, Dessa forma</p>
                    </div>
                </details>

                <!-- Dica 8 -->
                <details class="group">
                    <summary class="font-semibold cursor-pointer flex items-center gap-2" style="color: <?= htmlspecialchars($primaryColor) ?>;">
                        🎯 8. Regra para tirar +900
                    </summary>
                    <div class="mt-2 pl-6 text-gray-600">
                        <p>Se você fizer:</p>
                        <p>✅ Estrutura correta</p>
                        <p>✅ Dois argumentos bem desenvolvidos</p>
                        <p>✅ Conclusão completa</p>
                        <p>✅ Poucos erros gramaticais</p>
                        <p class="mt-2 font-semibold" style="color: <?= htmlspecialchars($primaryColor) ?>;">👉 Você já entra na faixa de 800–900+</p>
                    </div>
                </details>
            </div>
        </div>
    </div>
</div>
