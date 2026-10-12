<div class="mb-6">
    <a href="<?= URL ?>/monitor/aluno/<?= (int)$aluno['id'] ?>" class="text-teal-600 hover:text-teal-800 text-sm font-medium">← Voltar ao aluno</a>
    <h1 class="text-2xl font-bold text-gray-900 mt-2"><?= htmlspecialchars($prova['titulo'] ?? 'Prova') ?></h1>
    <p class="text-gray-600"><?= htmlspecialchars($aluno['nome']) ?></p>
    <?php if ($realizacao): ?>
        <p class="text-sm mt-1">
            Status: <strong class="<?= $realizacao['status'] === 'cancelada' ? 'text-red-600' : 'text-teal-700' ?>">
                <?= htmlspecialchars($realizacao['status']) ?>
            </strong>
            <?php if (!empty($realizacao['iniciado_em'])): ?>
                • Início: <?= date('d/m/Y H:i', strtotime($realizacao['iniciado_em'])) ?>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <p class="text-sm text-gray-500 mt-1">Aluno ainda não iniciou esta prova.</p>
    <?php endif; ?>
</div>

<div class="space-y-4">
    <?php foreach ($questoes as $i => $q): ?>
    <?php $resp = $q['resposta'] ?? null; ?>
    <div class="bg-white rounded-xl shadow p-5 border border-gray-100">
        <div class="flex justify-between items-start mb-2">
            <h3 class="font-semibold text-gray-900">Questão <?= $i + 1 ?></h3>
            <?php if ($resp): ?>
                <span class="text-xs px-2 py-1 rounded-full <?= !empty($resp['correta']) ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                    <?= !empty($resp['correta']) ? 'Correta' : 'Respondida' ?>
                </span>
            <?php else: ?>
                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">Sem resposta</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($q['enunciado'])): ?>
            <div class="text-sm text-gray-700 mb-3"><?= $q['enunciado'] ?></div>
        <?php endif; ?>
        <?php if ($resp): ?>
            <div class="bg-teal-50 border border-teal-100 rounded-lg p-3 text-sm">
                <strong>Resposta:</strong>
                <?php if (!empty($resp['alternativa_texto'])): ?>
                    <p class="mt-1"><?= htmlspecialchars($resp['alternativa_texto']) ?></p>
                <?php elseif (!empty($resp['resposta_texto'])): ?>
                    <p class="mt-1"><?= nl2br(htmlspecialchars($resp['resposta_texto'])) ?></p>
                <?php else: ?>
                    <p class="mt-1 text-gray-500">Resposta registrada (alternativa)</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($questoes)): ?>
        <p class="text-center text-gray-500 py-8">Nenhuma questão nesta prova.</p>
    <?php endif; ?>
</div>
