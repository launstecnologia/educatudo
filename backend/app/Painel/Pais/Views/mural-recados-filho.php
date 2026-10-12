<?php
$recados = is_array($recados ?? null) ? $recados : [];
?>
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Mural de Recados</h2>
    <p class="text-gray-600 mb-6">Recados publicados pela escola/professores para <?= htmlspecialchars($filho['nome'] ?? 'o aluno') ?> — mesma informação que ele vê no painel dele.</p>

    <?php if (empty($recados)): ?>
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-8 text-center">
        <p class="text-gray-500">Nenhum recado no mural no momento.</p>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($recados as $recado): ?>
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2"><?= htmlspecialchars($recado['titulo']) ?></h3>
                        <p class="text-sm text-gray-500 mb-2">
                            Por <?= htmlspecialchars($recado['autor_nome'] ?? 'Admin') ?> · Publicado em <?= date('d/m/Y H:i', strtotime($recado['data_publicacao'])) ?>
                        </p>
                        <?php
                        $c = $recado['conteudo'] ?? '';
                        if ($c !== '') {
                            if (strpos($c, '<') !== false) {
                                echo '<div class="text-gray-700 prose prose-sm max-w-none">' . rich_text_render($c) . '</div>';
                            } else {
                                echo '<div class="text-gray-700 whitespace-pre-wrap">' . nl2br(htmlspecialchars($c)) . '</div>';
                            }
                        }
                        ?>
                    </div>
                    <?php if (!empty($recado['ja_visto'])): ?>
                    <span class="flex-shrink-0 text-sm text-green-700 bg-green-50 border border-green-200 rounded-full px-3 py-1">✓ Visto pelo aluno</span>
                    <?php else: ?>
                    <span class="flex-shrink-0 text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-full px-3 py-1">Ainda não visto</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
