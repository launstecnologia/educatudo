<?php
$jr = $jornadas_relatorio ?? [];
$tot = $jr['totais'] ?? ['atribuidas' => 0, 'concluidas' => 0, 'pendentes' => 0, 'taxa_pct' => 0];
$porAluno = $jr['por_aluno'] ?? [];
$jEscopo = (int) ($jr['jornadas_no_escopo'] ?? 0);
$trunc = !empty($jr['jornadas_truncadas']);
$limCel = !empty($jr['celulas_limitadas']);
$modoPorMateria = !empty($jr['modo_por_materia']);
$pg = $jr['paginacao'] ?? [
    'page' => 1,
    'limit' => 25,
    'total_items' => count($porAluno),
    'total_pages' => 1,
    'has_prev' => false,
    'has_next' => false,
    'prev_page' => 1,
    'next_page' => 1,
    'from' => empty($porAluno) ? 0 : 1,
    'to' => count($porAluno),
];
$queryBase = $_GET;
unset($queryBase['page']);
$paginationBase = $reports_filter_base_url ?? (URL . '/admin/reports');
$executar = !empty($filtros['executar']);
?>
<?php
$reports_filter_jornadas_extended = true;
require __DIR__ . '/_reports_filters_form.php';
?>
<?php if (!$executar): ?>
    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        Preencha os filtros e clique em <strong>Aplicar filtros</strong> para carregar os resultados.
    </div>
    <?php return; ?>
<?php endif; ?>
<div class="space-y-6">
    <p class="text-sm text-gray-600">
        Contagem por <strong>aluno × jornada</strong> elegível (turmas da jornada, alunos selecionados quando aplicável).
        <strong>Concluída</strong> = registro de conclusão da jornada em <code class="text-xs bg-gray-100 px-1 rounded">jornadas_progresso_alunos</code>, alinhado ao painel da jornada no admin.
        <strong>Tempo total</strong> = soma de <code class="text-xs bg-gray-100 px-1 rounded">tempo_gasto</code> (segundos) de todas as atividades registradas nessas jornadas para o aluno.
    </p>

    <?php if ($trunc || $limCel): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <?php if ($trunc): ?>
                Foram consideradas no máximo as <strong>350</strong> jornadas mais recentes do escopo. Refine filtros (datas, professor ou jornada específica) se precisar de outro recorte.
            <?php endif; ?>
            <?php if ($limCel): ?>
                Limite de processamento de pares aluno×jornada atingido; totais podem estar parciais.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-purple-50 to-white p-4 shadow-sm">
            <p class="text-xs font-medium text-purple-800 uppercase tracking-wide">Pares atribuídos</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?= number_format((int) ($tot['atribuidas'] ?? 0), 0, ',', '.') ?></p>
            <p class="text-xs text-gray-500 mt-1">Jornadas no escopo: <?= $jEscopo ?></p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-emerald-50 to-white p-4 shadow-sm">
            <p class="text-xs font-medium text-emerald-800 uppercase tracking-wide">Concluídos</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?= number_format((int) ($tot['concluidas'] ?? 0), 0, ',', '.') ?></p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-rose-50 to-white p-4 shadow-sm">
            <p class="text-xs font-medium text-rose-800 uppercase tracking-wide">Pendentes</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?= number_format((int) ($tot['pendentes'] ?? 0), 0, ',', '.') ?></p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm">
            <p class="text-xs font-medium text-slate-800 uppercase tracking-wide">Taxa conclusão</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?= htmlspecialchars((string) ($tot['taxa_pct'] ?? 0)) ?>%</p>
        </div>
    </div>

    <div>
        <h3 class="text-lg font-semibold text-gray-900 mb-3">
            <?php
            $ordT = (string) ($filtros['jr_tempo_ordem'] ?? '');
            if (!empty($filtros['jr_somente_atencao'])) {
                echo 'Alunos em atenção';
            } else {
                echo 'Alunos';
            }
            if ($ordT === 'rapido') {
                echo ' (mais rápido → mais lento no tempo)';
            } elseif ($ordT === 'lento') {
                echo ' (mais lento → mais rápido no tempo)';
            } else {
                echo ' (pior → melhor na taxa)';
            }
            ?>
        </h3>
        <p class="text-xs text-gray-500 mb-2">
            <?php if ($ordT === 'rapido' || $ordT === 'lento'): ?>
                Ordenação por <strong>tempo total</strong> nas jornadas do escopo; alunos sem tempo registrado (0) vão ao fim da lista. Em empate, usa taxa de conclusão e nome.
            <?php else: ?>
                Atenção: ≥2 jornadas no escopo e (0 concluídas <em>ou</em> taxa &lt; 50%). Ordenação: menor taxa primeiro, depois mais pendentes.
            <?php endif; ?>
        </p>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3 text-sm text-gray-600">
            <p>
                Exibindo <strong><?= (int) ($pg['from'] ?? 0) ?></strong>–<strong><?= (int) ($pg['to'] ?? 0) ?></strong> de <strong><?= (int) ($pg['total_items'] ?? 0) ?></strong> alunos.
            </p>
            <?php if ((int) ($pg['total_pages'] ?? 1) > 1): ?>
                <p>Página <strong><?= (int) ($pg['page'] ?? 1) ?></strong> de <strong><?= (int) ($pg['total_pages'] ?? 1) ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Aluno</th>
                        <?php if ($modoPorMateria): ?>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Matéria</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Turma</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Atribuídas</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Concluídas</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Pendentes</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">%</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Tempo total</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Última atividade</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-700">Atenção</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <?php if (empty($porAluno)): ?>
                        <tr>
                            <td colspan="<?= $modoPorMateria ? '10' : '9' ?>" class="px-4 py-8 text-center text-gray-500">Nenhum dado para os filtros atuais. Ajuste período, turma, aluno ou jornada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($porAluno as $row): ?>
                            <tr class="<?= !empty($row['precisa_atencao']) ? 'bg-amber-50/80' : '' ?>">
                                <td class="px-4 py-2 font-medium text-gray-900">
                                    <?= htmlspecialchars($row['nome'] ?? '') ?>
                                    <span class="block text-xs text-gray-500 font-normal">RA <?= htmlspecialchars($row['ra'] ?? '') ?></span>
                                </td>
                                <?php if ($modoPorMateria): ?>
                                    <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($row['materia_nome'] ?? 'Sem matéria') ?></td>
                                <?php endif; ?>
                                <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($row['turma_nome'] ?? '') ?></td>
                                <td class="px-4 py-2 text-right tabular-nums"><?= (int) ($row['assigned'] ?? 0) ?></td>
                                <td class="px-4 py-2 text-right tabular-nums text-emerald-700"><?= (int) ($row['concluidas'] ?? 0) ?></td>
                                <td class="px-4 py-2 text-right tabular-nums text-rose-700"><?= (int) ($row['pendentes'] ?? 0) ?></td>
                                <td class="px-4 py-2 text-right tabular-nums font-medium"><?= htmlspecialchars((string) ($row['taxa_pct'] ?? 0)) ?>%</td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-800 text-xs"><?= htmlspecialchars($row['tempo_total_label'] ?? '—') ?></td>
                                <td class="px-4 py-2 text-gray-600 text-xs">
                                    <?php
                                    $u = $row['ultima_atividade'] ?? null;
                                    echo $u ? date('d/m/Y H:i', strtotime((string) $u)) : '—';
                                    ?>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <?php if (!empty($row['precisa_atencao'])): ?>
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">Sim</span>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ((int) ($pg['total_pages'] ?? 1) > 1): ?>
            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <?php
                $prevQuery = array_merge($queryBase, ['page' => (int) ($pg['prev_page'] ?? 1)]);
                $nextQuery = array_merge($queryBase, ['page' => (int) ($pg['next_page'] ?? 1)]);
                ?>
                <?php if (!empty($pg['has_prev'])): ?>
                    <a href="<?= htmlspecialchars($paginationBase) ?>?<?= htmlspecialchars(http_build_query($prevQuery)) ?>" class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Anterior</a>
                <?php else: ?>
                    <span class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-400 cursor-not-allowed">Anterior</span>
                <?php endif; ?>

                <?php if (!empty($pg['has_next'])): ?>
                    <a href="<?= htmlspecialchars($paginationBase) ?>?<?= htmlspecialchars(http_build_query($nextQuery)) ?>" class="inline-flex items-center rounded-lg bg-purple-600 px-3 py-2 text-sm text-white hover:bg-purple-700">Próxima</a>
                <?php else: ?>
                    <span class="inline-flex items-center rounded-lg bg-gray-200 px-3 py-2 text-sm text-gray-500 cursor-not-allowed">Próxima</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
