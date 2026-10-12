<?php
$bloco = $bloco ?? null;
$provas = $provas ?? [];
$todas_finalizadas = $todas_finalizadas ?? false;
$bloco_terminou = $bloco_terminou ?? false;
$blocoId = $bloco ? (int)$bloco['id'] : 0;
?>
<?php if ($todas_finalizadas): ?>
<div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-6">
    <p class="text-green-800 font-medium mb-4">Todas as provas deste bloco foram finalizadas.</p>
    <?php if (!$bloco_terminou && !empty($bloco['hora_fim'])): ?>
    <p class="text-amber-800 text-sm mb-4">Os resultados estarão disponíveis após o término do horário do bloco (<?= date('H:i', strtotime($bloco['hora_fim'])) ?>). Até lá, você pode sair do modo prova.</p>
    <?php endif; ?>
    <div class="flex flex-col sm:flex-row gap-3">
        <?php if ($bloco_terminou): ?>
        <a href="<?= URL ?>/aluno/provas/bloco/<?= $blocoId ?>/resultados" 
           class="flex-1 text-center bg-green-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-green-700 link-saida-permitida">
            Ver Resultados do Bloco
        </a>
        <?php endif; ?>
        <button type="button" id="btn-sair-prova-segura" 
                class="flex-1 bg-gray-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-gray-700">
            Sair do modo prova
        </button>
    </div>
</div>
<?php else: ?>
<p class="text-sm font-semibold text-gray-700 mb-4">Escolha a matéria para iniciar:</p>
<div class="space-y-3">
    <?php foreach ($provas as $prova): ?>
        <?php 
        $finalizada = isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'finalizado'; 
        $cancelada = isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'cancelada';
        ?>
        <div class="bg-white rounded-xl border-2 border-gray-200 p-4 flex items-center justify-between">
            <div>
                <span class="font-medium text-gray-900"><?= htmlspecialchars($prova['materia_nome'] ?? $prova['titulo']) ?></span>
                <p class="text-sm text-gray-500 mt-0.5">Professor: <?= !empty($prova['professor_nome']) ? htmlspecialchars($prova['professor_nome']) : '—' ?></p>
                <?php if ($finalizada): ?>
                    <span class="ml-2 text-green-600 text-sm font-semibold">✓ Finalizada</span>
                <?php elseif ($cancelada): ?>
                    <span class="ml-2 text-amber-600 text-sm font-semibold">Cancelada – aguarde liberação</span>
                <?php endif; ?>
            </div>
            <?php if ($finalizada): ?>
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-lg text-sm">Concluída</span>
            <?php elseif ($cancelada): ?>
                <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-lg text-sm">Cancelada</span>
            <?php else: ?>
                <button type="button" class="btn-iniciar-materia px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700" data-prova-id="<?= (int)$prova['id'] ?>">
                    <?= (isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'iniciado') ? 'Continuar' : 'Iniciar' ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
