<?php
/** Extraído de show.php — conteúdo existente, sem alteração de regra de negócio. */
?>
        <?php if ($matriculas_schema_ready): ?>
        <div id="section-matriculas-aluno" class="student-card min-w-0" data-perm-key="matriculas_aluno" data-perm-action="visualizar">
            <div class="student-card-header">
                <h3 class="text-base font-semibold text-slate-900">Matrículas</h3>
                <p class="text-sm text-slate-500 mt-0.5">Turmas e anos de estudo do aluno</p>
            </div>
            <div class="student-card-body">
                <?php if ($flash_message !== ''): ?>
                <div class="mb-4 p-4 rounded-lg <?= $flash_type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
                    <?= safe_htmlspecialchars($flash_message) ?>
                </div>
                <?php endif; ?>
                <?php if ($matricula_divergente_cadastro): ?>
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <p class="font-medium">Matrícula ativa diferente da turma do cadastro</p>
                    <p class="mt-1 text-amber-900/90">A turma principal no topo da página é <strong><?= safe_htmlspecialchars($turmaDisplay) ?></strong>, mas existe matrícula <strong>ativa</strong> em outra turma. Isso afeta provas e listagens. Confirme se o topo está correto; em seguida use o botão para encerrar a(s) matrícula(s) errada(s) e abrir na turma do cadastro.</p>
                    <button type="button" id="btnSyncMatriculaCadastro" class="mt-3 inline-flex items-center px-4 py-2 rounded-lg bg-amber-700 text-white text-sm font-semibold hover:bg-amber-800">
                        Alinhar matrícula com turma do cadastro
                    </button>
                    <p id="syncMatriculaMsg" class="mt-2 text-sm hidden"></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($matriculas_paralelas)): ?>
                <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50/80 px-4 py-3">
                    <p class="text-sm font-semibold text-indigo-950">Também matriculado em</p>
                    <p class="text-xs text-indigo-900/80 mt-1">Faltas, provas e jornadas usam a turma principal<?= !empty($turmaDisplay) ? ': <strong>' . safe_htmlspecialchars($turmaDisplay) . '</strong>' : '' ?>.</p>
                    <ul class="mt-3 space-y-2">
                        <?php foreach ($matriculas_paralelas as $mp): ?>
                        <li class="flex flex-wrap items-center gap-2 text-sm text-indigo-950">
                            <span class="font-medium"><?= safe_htmlspecialchars($mp['turma_nome'] ?? '') ?></span>
                            <?php if (($mp['curso_tipo'] ?? '') === 'extra'): ?>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-200 text-indigo-900">Curso extra</span>
                            <?php else: ?>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">Paralela</span>
                            <?php endif; ?>
                            <span class="text-xs text-indigo-800/70">Ano <?= (int)($mp['ano_letivo_ano'] ?? 0) ?> · entrada <?= format_data_br($mp['data_entrada'] ?? null) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if (empty($matriculas)): ?>
                    <p class="text-gray-500 text-sm">Nenhuma matrícula cadastrada. Use <strong>Matrícula</strong> em Ações Rápidas para adicionar.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4">Turma</th>
                                    <th class="py-2 pr-4">Vínculo</th>
                                    <th class="py-2 pr-4">Ano letivo</th>
                                    <th class="py-2 pr-4">Entrada</th>
                                    <th class="py-2 pr-4">Saída</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 pr-4">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($matriculas as $mat): ?>
                                    <tr>
                                        <td class="py-2 pr-4 text-gray-900"><?= safe_htmlspecialchars($mat['turma_nome'] ?? '') ?></td>
                                        <td class="py-2 pr-4">
                                            <?php
                                            $vr = $mat['vinculo_rotulo'] ?? '';
                                            $vrClass = $vr === 'Principal' ? 'bg-blue-100 text-blue-800' : ($vr === 'Extra' ? 'bg-indigo-100 text-indigo-800' : 'bg-purple-100 text-purple-800');
                                            if ($vr !== '' && ($mat['status'] ?? '') === 'ativa'):
                                            ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $vrClass ?>"><?= safe_htmlspecialchars($vr) ?></span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-700"><?= (int)($mat['ano_letivo_ano'] ?? 0) ?></td>
                                        <td class="py-2 pr-4 text-gray-700"><?= format_data_br($mat['data_entrada'] ?? null) ?></td>
                                        <td class="py-2 pr-4 text-gray-700"><?= format_data_br($mat['data_saida'] ?? null) ?></td>
                                        <td class="py-2 pr-4">
                                            <?php
                                            $stMat = (string)($mat['status'] ?? '');
                                            if ($stMat === 'ativa') {
                                                $stLabel = 'Ativa';
                                                $stClass = 'bg-green-100 text-green-800';
                                            } elseif ($stMat === 'concluido') {
                                                $stLabel = 'Encerrada';
                                                $stClass = 'bg-slate-100 text-slate-700';
                                            } elseif ($stMat === 'transferido') {
                                                $stLabel = 'Transferida';
                                                $stClass = 'bg-gray-100 text-gray-600';
                                            } else {
                                                $stLabel = $stMat !== '' ? $stMat : '—';
                                                $stClass = 'bg-gray-100 text-gray-600';
                                            }
                                            ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $stClass ?>"><?= safe_htmlspecialchars($stLabel) ?></span>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <?php if (($mat['status'] ?? '') === 'ativa'): ?>
                                            <form action="<?= URL ?>/admin/students/<?= (int)$student['id'] ?>/matricula/<?= (int)$mat['id'] ?>/encerrar" method="POST" class="inline" onsubmit="return confirm('Encerrar esta matrícula?');">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="data_saida" value="<?= date('Y-m-d') ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-semibold">Encerrar</button>
                                            </form>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <button type="button" onclick="toggleHistoricoTurmas()" class="mt-4 text-sm font-semibold text-blue-600 hover:text-blue-800 inline-flex items-center">
                    Ver histórico de turmas
                    <i id="icon-historico-chevron" class="fa-solid fa-chevron-down ml-1.5 text-xs transition-transform"></i>
                </button>
                <div id="bloco-historico-turmas" class="hidden mt-4 pt-4 border-t border-slate-200">
                    <?php if (empty($historico_turmas)): ?>
                        <p class="text-slate-500 text-sm">Nenhum histórico de turma registrado.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                                        <th class="py-2 pr-4">Turma</th>
                                        <th class="py-2 pr-4">Ano letivo</th>
                                        <th class="py-2 pr-4">Tipo</th>
                                        <th class="py-2 pr-4">Início</th>
                                        <th class="py-2 pr-4">Fim</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($historico_turmas as $hist): ?>
                                        <tr>
                                            <td class="py-2 pr-4 text-slate-900 font-medium"><?= safe_htmlspecialchars($hist['turma_nome'] ?? '', '') ?></td>
                                            <td class="py-2 pr-4 text-slate-600"><?= safe_htmlspecialchars($hist['ano_letivo'] ?? '', '') ?></td>
                                            <td class="py-2 pr-4 text-slate-600"><?= safe_htmlspecialchars($hist['tipo_ensino'] ?? '', '') ?></td>
                                            <td class="py-2 pr-4 text-slate-600"><?= format_data_br($hist['data_inicio'] ?? null, '') ?></td>
                                            <td class="py-2 pr-4 text-slate-600"><?= format_data_br($hist['data_fim'] ?? null, 'Atual') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="student-card min-w-0" data-perm-key="matriculas_aluno" data-perm-action="visualizar">
            <div class="student-card-header flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center">
                    <i class="fa-solid fa-clock-rotate-left text-slate-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Histórico de Turmas</h3>
                    <p class="text-sm text-slate-500">Registros por ano letivo</p>
                </div>
            </div>
            <div class="student-card-body">
                <?php if (empty($historico_turmas)): ?>
                    <p class="text-slate-500 text-sm">Nenhum histórico registrado.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                                    <th class="py-2 pr-4">Turma</th>
                                    <th class="py-2 pr-4">Ano</th>
                                    <th class="py-2 pr-4">Início</th>
                                    <th class="py-2 pr-4">Fim</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($historico_turmas as $hist): ?>
                                <tr>
                                    <td class="py-2 pr-4 font-medium text-slate-900"><?= safe_htmlspecialchars($hist['turma_nome'] ?? '') ?></td>
                                    <td class="py-2 pr-4 text-slate-600"><?= safe_htmlspecialchars($hist['ano_letivo'] ?? '') ?></td>
                                    <td class="py-2 pr-4 text-slate-600"><?= format_data_br($hist['data_inicio'] ?? null, '') ?></td>
                                    <td class="py-2 pr-4 text-slate-600"><?= format_data_br($hist['data_fim'] ?? null, 'Atual') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
