<?php
$disciplina = $disciplina ?? [];
$outline = $outline ?? [];
$resumo = $resumo ?? ['alunos' => [], 'total_alunos' => 0, 'progresso_medio' => 0];
$tipos_aula = $tipos_aula ?? [];
$professores = $professores ?? [];
$turmas = $turmas ?? [];
$semestres = $semestres ?? [];
$status_opcoes = $status_opcoes ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$base = rtrim((string) ($base_url ?? '/admin/ava'), '/');
$isAdmin = !empty($is_admin);
$voltar = $isAdmin ? (URL . '/admin/ava/cursos/' . (int) ($disciplina['curso_id'] ?? 0)) : (URL . '/professor/ava');
$dId = (int) ($disciplina['id'] ?? 0);
?>

<div class="mb-6">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-4">
            <a href="<?= $voltar ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars((string) $disciplina['nome']) ?></h2>
                <p class="text-sm text-gray-600"><?= htmlspecialchars((string) ($disciplina['curso_nome'] ?? '')) ?> · Conteúdo e acompanhamento</p>
            </div>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <?php if (strpos($base, 'professor') !== false): ?>
            <a href="<?= URL . $base ?>/disciplinas/<?= $dId ?>/ao-vivo" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-video mr-2 text-gray-500"></i> Ao vivo</a>
            <a href="<?= URL . $base ?>/disciplinas/<?= $dId ?>/atividades" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-clipboard-list mr-2 text-gray-500"></i> Atividades</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <a href="<?= URL ?>/admin/ava/disciplinas/<?= $dId ?>/avaliacoes" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-file-pen mr-2 text-gray-500"></i> Avaliações</a>
            <a href="<?= URL ?>/admin/ava/disciplinas/<?= $dId ?>/editar" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-pen mr-2 text-gray-500"></i> Editar dados</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Conteúdo (módulos e aulas) -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Módulos e aulas</h3>
                <button type="button" onclick="document.getElementById('novoModulo').classList.toggle('hidden')" class="inline-flex items-center px-3 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors"><i class="fa-solid fa-plus mr-2"></i> Módulo</button>
            </div>

            <div id="novoModulo" class="hidden p-6 bg-gray-50 border-b border-gray-200">
                <form method="post" action="<?= URL . $base ?>/modulos" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                    <input type="hidden" name="disciplina_id" value="<?= $dId ?>">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Título do módulo <span class="text-red-500">*</span></label>
                        <input type="text" name="titulo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ordem</label>
                        <input type="number" name="ordem" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors"><i class="fa-solid fa-check mr-2"></i> Adicionar módulo</button>
                    </div>
                </form>
            </div>

            <div class="divide-y divide-gray-100">
                <?php if (empty($outline)): ?>
                    <div class="px-6 py-10 text-center text-gray-500">Nenhum módulo ainda. Crie o primeiro módulo para adicionar aulas.</div>
                <?php else: foreach ($outline as $m): ?>
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <h4 class="font-semibold text-gray-900"><?= htmlspecialchars((string) $m['titulo']) ?></h4>
                                <?php if (!empty($m['descricao'])): ?><p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars((string) $m['descricao']) ?></p><?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <a href="<?= URL . $base ?>/modulos/<?= (int) $m['id'] ?>/aulas/nova" class="inline-flex items-center px-3 py-1.5 bg-primary text-primary rounded-lg text-xs font-semibold hover:opacity-90 transition-colors"><i class="fa-solid fa-plus mr-1"></i> Aula</a>
                                <form method="post" action="<?= URL . $base ?>/modulos/<?= (int) $m['id'] ?>/excluir" onsubmit="return confirm('Excluir módulo e suas aulas?');">
                                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                                    <button type="submit" class="text-gray-400 hover:text-red-600 px-2"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <ul class="space-y-2">
                            <?php if (empty($m['aulas'])): ?>
                                <li class="text-sm text-gray-400 italic">Sem aulas neste módulo.</li>
                            <?php else: foreach ($m['aulas'] as $a): ?>
                                <li class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <i class="fa-solid <?= ($a['tipo'] ?? '') === 'video' ? 'fa-play' : (($a['tipo'] ?? '') === 'quiz' ? 'fa-list-check' : 'fa-file-lines') ?> text-gray-400"></i>
                                        <span class="text-sm text-gray-700 truncate"><?= htmlspecialchars((string) $a['titulo']) ?></span>
                                        <span class="text-xs text-gray-400"><?= htmlspecialchars($tipos_aula[$a['tipo']] ?? (string) $a['tipo']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <?php ob_start(); ?>
                                        <a href="<?= URL . $base ?>/aulas/<?= (int) $a['id'] ?>/editar" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                                        </a>
                                        <div class="border-t border-gray-100 my-1"></div>
                                        <form method="post" action="<?= URL . $base ?>/aulas/<?= (int) $a['id'] ?>/excluir" onsubmit="return confirm('Excluir aula?');">
                                            <input type="hidden" name="_token" value="<?= $csrf ?>">
                                            <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                            </button>
                                        </form>
                                        <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                                        <?php $row_actions_dropdown_id = 'row-actions-aula-' . (int) $a['id']; ?>
                                        <?php include __DIR__ . '/../_partials/row_actions_dropdown.php'; ?>
                                    </div>
                                </li>
                            <?php endforeach; endif; ?>
                        </ul>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Lateral: alunos -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Alunos</h3>
                <span class="text-sm text-gray-500"><?= (int) $resumo['total_alunos'] ?> · média <?= (float) $resumo['progresso_medio'] ?>%</span>
            </div>

            <?php if ($isAdmin): ?>
            <form method="post" action="<?= URL ?>/admin/ava/disciplinas/<?= $dId ?>/sincronizar-turma" class="flex gap-2 mb-4">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <select name="turma_id" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <option value="">Turma vinculada</option>
                    <?php foreach ($turmas as $t): ?><option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars((string) $t['nome']) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700" title="Matricular alunos da turma"><i class="fa-solid fa-user-plus"></i></button>
            </form>
            <?php endif; ?>

            <ul class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                <?php if (empty($resumo['alunos'])): ?>
                    <li class="py-3 text-sm text-gray-500">Nenhum aluno matriculado.</li>
                <?php else: foreach ($resumo['alunos'] as $al): ?>
                    <li class="py-2 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-sm text-gray-700 truncate"><?= htmlspecialchars((string) ($al['aluno_nome'] ?? '')) ?></div>
                            <div class="text-xs text-gray-400"><?= (float) ($al['progresso_pct'] ?? 0) ?>% · <?= htmlspecialchars((string) ($al['status'] ?? '')) ?></div>
                        </div>
                        <?php if ($isAdmin): ?>
                        <form method="post" action="<?= URL ?>/admin/ava/disciplinas/<?= $dId ?>/cancelar-matricula" onsubmit="return confirm('Cancelar matrícula?');">
                            <input type="hidden" name="_token" value="<?= $csrf ?>">
                            <input type="hidden" name="aluno_id" value="<?= (int) ($al['aluno_id'] ?? 0) ?>">
                            <button type="submit" class="text-gray-400 hover:text-red-600"><i class="fa-solid fa-xmark"></i></button>
                        </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>
    </div>
</div>
