<?php
$resumo = $resumo ?? ['matriculas' => [], 'total_disciplinas' => 0, 'concluidas' => 0, 'em_andamento' => 0, 'progresso_medio' => 0];
$matriculas = $resumo['matriculas'] ?? [];
$flash_status = (string) ($flash_type ?? '');
?>

<div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-1">Meus Cursos</h2>
        <p class="text-sm text-gray-600">Continue de onde parou nas suas disciplinas.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= URL ?>/cursos/agenda" class="inline-flex items-center px-4 py-2.5 border border-gray-300 bg-white text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50"><i class="fa-regular fa-calendar mr-2"></i> Agenda</a>
        <a href="<?= URL ?>/cursos/certificados" class="inline-flex items-center px-4 py-2.5 border border-gray-300 bg-white text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50"><i class="fa-solid fa-award mr-2"></i> Certificados</a>
    </div>
</div>

<?php if ($flash_status !== '' && !empty($flash_message)):
    $cls = $flash_status === 'success' ? 'bg-green-100 text-green-800 border-green-200' : ($flash_status === 'error' ? 'bg-red-100 text-red-800 border-red-200' : 'bg-blue-100 text-blue-800 border-blue-200'); ?>
    <div class="mb-6 p-4 rounded-lg border <?= $cls ?>"><?= htmlspecialchars((string) $flash_message) ?></div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php
    $cards = [
        ['Disciplinas', (int) $resumo['total_disciplinas'], 'fa-book', 'text-blue-600 bg-blue-50'],
        ['Em andamento', (int) $resumo['em_andamento'], 'fa-spinner', 'text-amber-600 bg-amber-50'],
        ['Concluídas', (int) $resumo['concluidas'], 'fa-circle-check', 'text-green-600 bg-green-50'],
        ['Progresso médio', ((float) $resumo['progresso_medio']) . '%', 'fa-chart-line', 'text-purple-600 bg-purple-50'],
    ];
    foreach ($cards as $c): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="w-9 h-9 rounded-lg <?= $c[3] ?> flex items-center justify-center mb-2"><i class="fa-solid <?= $c[2] ?>"></i></div>
            <div class="text-xl font-bold text-gray-900"><?= htmlspecialchars((string) $c[1]) ?></div>
            <div class="text-xs text-gray-500"><?= htmlspecialchars((string) $c[0]) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (empty($matriculas)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-gray-500">
        Você ainda não está matriculado em nenhuma disciplina do AVA.
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($matriculas as $m): $pct = (float) ($m['progresso_pct'] ?? 0); ?>
            <a href="<?= URL ?>/cursos/disciplina/<?= (int) $m['disciplina_id'] ?>" class="block bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                <div class="h-24 bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white">
                    <i class="fa-solid fa-graduation-cap text-3xl opacity-80"></i>
                </div>
                <div class="p-5">
                    <p class="text-xs text-gray-400 mb-1"><?= htmlspecialchars((string) ($m['curso_nome'] ?? '')) ?></p>
                    <h3 class="font-semibold text-gray-900 mb-3"><?= htmlspecialchars((string) ($m['disciplina_nome'] ?? '')) ?></h3>
                    <div class="w-full bg-gray-100 rounded-full h-2 mb-1">
                        <div class="bg-green-600 h-2 rounded-full" style="width: <?= max(0, min(100, $pct)) ?>%"></div>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <span><?= $pct ?>% concluído</span>
                        <?php if (($m['status'] ?? '') === 'concluida'): ?><span class="text-green-600 font-medium">Concluída</span><?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
