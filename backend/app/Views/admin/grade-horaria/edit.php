<?php
$title = $title ?? 'Editar Aula na Grade - EducaTudo';
$user = $user ?? null;
$current_page = $current_page ?? 'grade_horaria';
$item = $item ?? [];
$dias_semana = $dias_semana ?? [];
$turmas = $turmas ?? [];
$professores = $professores ?? [];
$materias = $materias ?? [];
$csrf_token = $csrf_token ?? '';
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

$horarioDe = $item['horario_de'] ?? '07:00';
$horarioAte = $item['horario_ate'] ?? '08:00';
if (strlen($horarioDe) > 5) $horarioDe = substr($horarioDe, 0, 5);
if (strlen($horarioAte) > 5) $horarioAte = substr($horarioAte, 0, 5);
?>

<div class="min-h-screen bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50">
    <div class="bg-white/80 backdrop-blur-sm border-b border-purple-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Editar Aula na Grade</h1>
                    <p class="mt-2 text-gray-600">Altere dia, horário, turma, professor, matéria ou período</p>
                </div>
                <div>
                    <a href="<?= URL ?>/admin/grade-horaria"
                       class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($error_message): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Dados da Aula</h3>
            </div>

            <form action="<?= URL ?>/admin/grade-horaria/<?= (int)($item['id'] ?? 0) ?>" method="post" class="p-6 space-y-5">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="dia_semana" class="block text-sm font-semibold text-gray-700 mb-2">Dia da Semana <span class="text-red-500">*</span></label>
                        <select id="dia_semana" name="dia_semana" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <?php foreach ($dias_semana as $num => $nome): ?>
                                <option value="<?= (int)$num ?>" <?= ((int)($item['dia_semana'] ?? 0)) === (int)$num ? 'selected' : '' ?>><?= htmlspecialchars($nome) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="periodo" class="block text-sm font-semibold text-gray-700 mb-2">Período <span class="text-red-500">*</span></label>
                        <select id="periodo" name="periodo" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="manha" <?= ($item['periodo'] ?? '') !== 'tarde' ? 'selected' : '' ?>>Manhã</option>
                            <option value="tarde" <?= ($item['periodo'] ?? '') === 'tarde' ? 'selected' : '' ?>>Tarde</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="horario_de" class="block text-sm font-semibold text-gray-700 mb-2">Horário de <span class="text-red-500">*</span></label>
                        <input type="time" id="horario_de" name="horario_de" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?= htmlspecialchars($horarioDe) ?>">
                    </div>
                    <div>
                        <label for="horario_ate" class="block text-sm font-semibold text-gray-700 mb-2">Horário até <span class="text-red-500">*</span></label>
                        <input type="time" id="horario_ate" name="horario_ate" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?= htmlspecialchars($horarioAte) ?>">
                    </div>
                </div>

                <div>
                    <label for="turma_id" class="block text-sm font-semibold text-gray-700 mb-2">Turma <span class="text-red-500">*</span></label>
                    <select id="turma_id" name="turma_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= ((int)($item['turma_id'] ?? 0)) === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="professor_id" class="block text-sm font-semibold text-gray-700 mb-2">Professor <span class="text-red-500">*</span></label>
                    <select id="professor_id" name="professor_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <?php foreach ($professores as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= ((int)($item['professor_id'] ?? 0)) === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="materia_id" class="block text-sm font-semibold text-gray-700 mb-2">Matéria <span class="text-red-500">*</span></label>
                    <select id="materia_id" name="materia_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <?php foreach ($materias as $m): ?>
                            <option value="<?= (int)$m['id'] ?>" <?= ((int)($item['materia_id'] ?? 0)) === (int)$m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <a href="<?= URL ?>/admin/grade-horaria"
                       class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">Cancelar</a>
                    <button type="submit" class="btn-primary-custom px-6 py-3 rounded-lg transition-colors flex items-center hover:opacity-90">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Atualizar Aula
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
