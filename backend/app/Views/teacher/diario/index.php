<?php
$dataAnterior = date('Y-m-d', strtotime($data_filtro . ' -1 day'));
$dataSeguinte = date('Y-m-d', strtotime($data_filtro . ' +1 day'));
$statusLabels = ['rascunho' => 'Rascunho', 'finalizada' => 'Finalizada', 'cancelada' => 'Não realizada'];
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Diário de Classe</h1>
            <p class="text-gray-600 mt-1">Abra a aula, faça a chamada e confirme o conteúdo realizado.</p>
        </div>
        <form method="get" action="<?= URL ?>/professor/diario" class="flex items-center gap-2">
            <a href="<?= URL ?>/professor/diario?data=<?= $dataAnterior ?>" class="px-3 py-2 border rounded-lg bg-white hover:bg-gray-50" aria-label="Dia anterior">←</a>
            <input type="date" name="data" value="<?= htmlspecialchars($data_filtro) ?>" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg bg-white">
            <a href="<?= URL ?>/professor/diario?data=<?= $dataSeguinte ?>" class="px-3 py-2 border rounded-lg bg-white hover:bg-gray-50" aria-label="Dia seguinte">→</a>
        </form>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-4 text-blue-900">
        <strong><?= date('d/m/Y', strtotime($data_filtro)) ?></strong>
        <span class="ml-2 text-blue-700"><?= htmlspecialchars(['Monday'=>'Segunda-feira','Tuesday'=>'Terça-feira','Wednesday'=>'Quarta-feira','Thursday'=>'Quinta-feira','Friday'=>'Sexta-feira','Saturday'=>'Sábado','Sunday'=>'Domingo'][date('l', strtotime($data_filtro))] ?? '') ?></span>
    </div>

    <?php if (empty($aulas)): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-10 text-center">
            <div class="text-4xl mb-3">📅</div>
            <h2 class="text-lg font-semibold text-gray-800">Nenhuma aula na grade para este dia</h2>
            <p class="text-gray-500 mt-1">Se deveria existir uma aula, peça à coordenação para conferir a grade horária.</p>
        </div>
    <?php else: ?>
        <div class="grid gap-4">
            <?php foreach ($aulas as $aula):
                $status = (string) ($aula['status'] ?? '');
                $badge = $status === 'finalizada' ? 'bg-green-100 text-green-800' : ($status === 'cancelada' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800');
            ?>
                <div class="bg-white border border-gray-200 rounded-xl p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4 shadow-sm">
                    <div class="flex gap-4 items-start">
                        <div class="bg-purple-100 text-purple-800 font-bold rounded-lg px-3 py-2 text-center min-w-[92px]">
                            <?= htmlspecialchars(substr((string) $aula['horario_de'], 0, 5)) ?><br>
                            <span class="text-xs font-normal">até <?= htmlspecialchars(substr((string) $aula['horario_ate'], 0, 5)) ?></span>
                        </div>
                        <div>
                            <h2 class="font-bold text-lg text-gray-900"><?= htmlspecialchars($aula['materia_nome']) ?></h2>
                            <p class="text-gray-600"><?= htmlspecialchars($aula['turma_nome']) ?></p>
                            <?php if ($status): ?><span class="inline-flex mt-2 px-2.5 py-1 rounded-full text-xs font-semibold <?= $badge ?>"><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <a href="<?= URL ?>/professor/diario/abrir?grade_id=<?= (int) $aula['grade_horaria_id'] ?>&data=<?= urlencode($data_filtro) ?>" class="inline-flex justify-center px-5 py-2.5 rounded-lg bg-purple-600 text-white font-semibold hover:bg-purple-700">
                        <?= $status ? 'Abrir diário' : 'Fazer chamada' ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
