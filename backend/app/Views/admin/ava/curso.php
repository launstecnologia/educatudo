<?php
$curso = $curso ?? [];
$semestres = $semestres ?? [];
$disciplinas = $disciplinas ?? [];
$professores = $professores ?? [];
$turmas = $turmas ?? [];
$materias = $materias ?? [];
$status_opcoes = $status_opcoes ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$cursoId = (int) ($curso['id'] ?? 0);
?>

<div class="mb-6">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-4">
            <a href="<?= URL ?>/admin/ava" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars((string) $curso['nome']) ?></h2>
                <p class="text-sm text-gray-600">Gestão de períodos e disciplinas do curso.</p>
            </div>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <a href="<?= URL ?>/admin/ava/cursos/<?= $cursoId ?>/periodos" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-calendar-days mr-2 text-gray-500"></i> Períodos</a>
            <a href="<?= URL ?>/admin/ava/cursos/<?= $cursoId ?>/editar" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-pen mr-2 text-gray-500"></i> Editar dados</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<div class="space-y-6">
    <!-- Disciplinas -->
    <div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Disciplinas</h3>
                <button type="button" onclick="document.getElementById('novaDisciplina').classList.toggle('hidden')" class="inline-flex items-center px-3 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors"><i class="fa-solid fa-plus mr-2"></i> Nova</button>
            </div>

            <div id="novaDisciplina" class="hidden p-6 bg-gray-50 border-b border-gray-200">
                <form method="post" action="<?= URL ?>/admin/ava/disciplinas" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                    <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nome da disciplina <span class="text-red-500">*</span></label>
                        <input type="text" name="nome" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Período</label>
                        <select name="semestre_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">—</option>
                            <?php foreach ($semestres as $s): ?><option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars((string) $s['nome']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Professor responsável</label>
                        <select name="professor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">—</option>
                            <?php foreach ($professores as $p): ?><option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars((string) $p['nome']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Turma vinculada (ERP)</label>
                        <select name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">—</option>
                            <?php foreach ($turmas as $t): ?><option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars((string) $t['nome']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Carga horária (h)</label>
                        <input type="number" min="0" name="carga_horaria" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors"><i class="fa-solid fa-check mr-2"></i> Criar disciplina</button>
                    </div>
                </form>
            </div>

            <div class="divide-y divide-gray-100">
                <?php if (empty($disciplinas)): ?>
                    <div class="px-6 py-10 text-center text-gray-500">Nenhuma disciplina cadastrada.</div>
                <?php else: foreach ($disciplinas as $d): ?>
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <a href="<?= URL ?>/admin/ava/disciplinas/<?= (int) $d['id'] ?>" class="font-medium text-gray-900 hover:text-green-700"><?= htmlspecialchars((string) $d['nome']) ?></a>
                            <div class="text-xs text-gray-500">
                                <?= htmlspecialchars((string) ($d['professor_nome'] ?? 'Sem professor')) ?>
                                · <?= (int) ($d['total_modulos'] ?? 0) ?> módulo(s)
                                <?php if (!empty($d['semestre_nome'])): ?> · <?= htmlspecialchars((string) $d['semestre_nome']) ?><?php endif; ?>
                            </div>
                        </div>
                        <a href="<?= URL ?>/admin/ava/disciplinas/<?= (int) $d['id'] ?>" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-sm font-medium hover:bg-blue-100 hover:border-blue-300 transition-colors"><i class="fa-solid fa-gear text-blue-600"></i> Gerenciar</a>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>
