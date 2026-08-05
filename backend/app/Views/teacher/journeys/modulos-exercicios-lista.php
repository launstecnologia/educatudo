<!-- Header Section -->
<div class="mb-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">
                Exercícios do Módulo - <?= htmlspecialchars($modulo['titulo'] ?? 'Módulo') ?>
            </h2>
            <p class="text-gray-600">
                Jornada: <?= htmlspecialchars($jornada['titulo']) ?> • <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= URL ?>/professor/jornadas/modulos/<?= (int)$modulo['id'] ?>/exercicios/criar"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
                Criar novo exercício
            </a>
            <a href="<?= URL ?>/professor/jornadas/<?= (int)$modulo['jornada_id'] ?>/modulos"
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors shadow-sm">
                Voltar para módulos
            </a>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 border border-blue-200">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h3 class="text-lg font-semibold text-gray-900">Lista de Exercícios</h3>
        <?php
        $totalPublicados = 0;
        $totalRascunhos = 0;
        foreach ($exercicios as $ex) {
            if (($ex['status'] ?? '') === 'publicado') $totalPublicados++;
            else $totalRascunhos++;
        }
        ?>
        <div class="flex gap-2 text-sm">
            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full"><?= $totalPublicados ?> publicado(s)</span>
            <?php if ($totalRascunhos > 0): ?>
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full"><?= $totalRascunhos ?> rascunho(s)</span>
            <?php endif; ?>
        </div>
    </div>

    <div id="exerciciosList" class="space-y-4">
        <?php if (empty($exercicios)): ?>
            <div class="text-center py-10 text-gray-500 border border-dashed border-gray-300 rounded-xl">
                <p class="mb-2">Nenhum exercício criado ainda</p>
                <a href="<?= URL ?>/professor/jornadas/modulos/<?= (int)$modulo['id'] ?>/exercicios/criar"
                   class="inline-block mt-2 text-blue-600 hover:text-blue-800 font-medium">Criar primeiro exercício</a>
            </div>
        <?php else: ?>
            <?php foreach ($exercicios as $exercicio): ?>
                <?php
                $enunciadoResumo = trim(preg_replace('/\s+/', ' ', strip_tags((string)($exercicio['enunciado'] ?? ''))));
                $enunciadoResumo = mb_substr($enunciadoResumo, 0, 220) . (mb_strlen($enunciadoResumo) > 220 ? '...' : '');
                ?>
                <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition-shadow">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded"><?= ucfirst(str_replace('_', ' ', (string)$exercicio['tipo'])) ?></span>
                                <?php if (!empty($exercicio['gerado_ia'])): ?>
                                    <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded">IA</span>
                                <?php endif; ?>
                                <span class="px-2 py-1 text-xs <?= ($exercicio['status'] ?? '') === 'publicado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?> rounded">
                                    <?= ucfirst((string)($exercicio['status'] ?? 'rascunho')) ?>
                                </span>
                            </div>
                            <p class="text-gray-800 leading-relaxed"><?= htmlspecialchars($enunciadoResumo) ?></p>
                            <p class="text-xs text-gray-500 mt-2">Pontuação: <?= htmlspecialchars((string)($exercicio['pontuacao'] ?? '1.00')) ?> pontos</p>
                        </div>

                        <div class="flex items-center gap-2 md:pl-4">
                            <a href="<?= URL ?>/professor/jornadas/modulos/<?= (int)$modulo['id'] ?>/exercicios/criar?editar=<?= (int)$exercicio['id'] ?>"
                               class="px-3 py-1.5 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors">Editar</a>
                            <button type="button" onclick="removerExercicio(<?= (int)$exercicio['id'] ?>)"
                                    class="px-3 py-1.5 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">Remover</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function removerExercicio(id) {
    if (!confirm('Tem certeza que deseja remover este exercício?')) {
        return;
    }

    const formData = new FormData();
    formData.append('exercicio_id', id);
    formData.append('_token', <?= json_encode($csrf_token) ?>);

    fetch('<?= URL ?>/professor/jornadas/modulos/remover-exercicio', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
}
</script>
