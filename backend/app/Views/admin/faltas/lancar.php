<?php
$eventoAtual = $evento_atual ?? null;
$turmas = $turmas ?? [];
$turmasById = $turmas_by_id ?? [];
$linhasFaltas = $linhas_faltas ?? [];
$lancMap = $lancamentos_map ?? [];
$turmaFiltroIds = array_map('intval', (array) ($turma_filtro_ids ?? []));
$turmaFiltroSet = array_fill_keys($turmaFiltroIds, true);
$materiaFiltroIds = array_map('intval', (array) ($materia_filtro_ids ?? []));
$materiaFiltroSet = array_fill_keys($materiaFiltroIds, true);
$matrizMateriasTodas = $matriz_materias_todas ?? [];
$materiasFiltroOpcoes = $materias_filtro_opcoes ?? [];
$csrfToken = (string) ($csrf_token ?? '');
$flashMessage = (string) ($flash_message ?? '');
$flashType = (string) ($flash_type ?? 'success');
$faltasMatriz = !empty($faltas_matriz);
$matrizColunas = $matriz_colunas_materias ?? [];
$matrizLinhas = $matriz_linhas_alunos ?? [];
$eventoAtualId = $eventoAtual ? (int) ($eventoAtual['id'] ?? 0) : 0;
$materiasOpcoesFiltroUi = $faltasMatriz ? $matrizMateriasTodas : $materiasFiltroOpcoes;
$urlListaFaltas = URL . '/admin/faltas';
$urlLancarFaltas = URL . '/admin/faltas/lancar';
$urlExportarLancamentoExcel = URL . '/admin/faltas/lancar/exportar-excel';
$exibirNumeroChamada = false;
foreach (array_merge($matrizLinhas, $linhasFaltas) as $_lnChk) {
    if (!empty($_lnChk['numero_chamada'])) {
        $exibirNumeroChamada = true;
        break;
    }
}
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex flex-col gap-2">
            <a href="<?= $urlListaFaltas ?>" class="inline-flex items-center gap-2 text-sm font-medium text-indigo-700 hover:text-indigo-900 w-fit">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Voltar à lista de eventos
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Lançar faltas</h1>
            <p class="text-sm text-gray-600">
                <span class="font-medium text-gray-800"><?= htmlspecialchars((string) ($eventoAtual['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-gray-400 mx-1">·</span>
                <?= htmlspecialchars((string) ($eventoAtual['bimestre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                <span class="text-gray-400 mx-1">·</span>
                Ano <?= (int) ($eventoAtual['ano_letivo'] ?? 0) ?>
            </p>
        </div>
    </div>

    <?php if ($flashMessage !== ''): ?>
        <?php $bg = $flashType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>
        <div class="p-4 rounded-lg border <?= $bg ?>"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <?php if (!empty($faltas_migracao_legado)): ?>
            <div class="mb-4 p-3 rounded-lg border border-amber-200 bg-amber-50 text-sm text-amber-900">
                Este evento ainda tem faltas lançadas no formato antigo (sem matéria). Depois que você passar a lançar por matéria, o boletim usará só esses lançamentos por matéria. O valor consolidado antigo deixa de entrar na soma automaticamente.
            </div>
        <?php endif; ?>

        <?php if ($exibirNumeroChamada): ?>
            <div class="mb-4 p-3 rounded-lg border border-indigo-200 bg-indigo-50 text-sm text-indigo-900">
                A ordem dos alunos segue a <strong>lista de chamada</strong> da turma (nº na primeira coluna). Para alterar critério ou renumerar, use
                <strong>Turmas → Lista de chamada</strong>.
            </div>
        <?php endif; ?>

        <form method="GET" action="<?= htmlspecialchars($urlLancarFaltas, ENT_QUOTES, 'UTF-8') ?>" class="mb-6 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <input type="hidden" name="evento_id" value="<?= $eventoAtualId ?>">
            <div class="px-5 py-4 border-b border-gray-100 bg-slate-50/80">
                <h3 class="text-sm font-semibold text-gray-900">Filtros de exibição</h3>
                <p class="text-xs text-gray-500 mt-1">Sem turmas marcadas = todos os alunos do evento. Sem matérias marcadas = todas as colunas de matérias.</p>
            </div>
            <div>
                <div class="p-5 space-y-3">
                    <div class="text-sm font-medium text-gray-800">Turmas</div>
                    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-2.5">
                        <?php foreach ((array) ($eventoAtual['turmas_ids'] ?? []) as $tidOpt): ?>
                            <?php
                            $tidOpt = (int) $tidOpt;
                            if ($tidOpt <= 0 || !isset($turmasById[$tidOpt])) {
                                continue;
                            }
                            $to = $turmasById[$tidOpt];
                            $lblTurma = trim((string) (($to['serie_nome'] ?? '') . ' — ' . ($to['nome'] ?? ('Turma #' . $tidOpt))));
                            $chkTurma = $turmaFiltroIds !== [] && isset($turmaFiltroSet[$tidOpt]);
                            ?>
                            <label class="flex items-start gap-2.5 text-sm text-gray-800 cursor-pointer select-none rounded-lg border border-transparent hover:border-gray-200 hover:bg-gray-50/80 px-2 py-1.5 -mx-2 -my-0.5">
                                <input type="checkbox" name="turma_ids[]" value="<?= $tidOpt ?>" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 shrink-0" <?= $chkTurma ? 'checked' : '' ?>>
                                <span class="leading-snug"><?= htmlspecialchars($lblTurma, ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="p-5 space-y-3 border-t border-gray-200">
                    <?php if ($materiasOpcoesFiltroUi !== []): ?>
                        <div class="text-sm font-medium text-gray-800">Matérias</div>
                        <p class="text-xs text-gray-500">Marque só as matérias cujas colunas deseja ver na tabela abaixo.</p>
                        <div class="grid sm:grid-cols-2 gap-x-4 gap-y-2.5">
                            <?php foreach ($materiasOpcoesFiltroUi as $mopt): ?>
                                <?php
                                $midOpt = (int) ($mopt['id'] ?? 0);
                                if ($midOpt <= 0) {
                                    continue;
                                }
                                $lblMat = (string) ($mopt['nome'] ?? ('#' . $midOpt));
                                $chkMat = $materiaFiltroIds !== [] && isset($materiaFiltroSet[$midOpt]);
                                ?>
                                <label class="flex items-start gap-2.5 text-sm text-gray-800 cursor-pointer select-none rounded-lg border border-transparent hover:border-gray-200 hover:bg-gray-50/80 px-2 py-1.5 -mx-2 -my-0.5">
                                    <input type="checkbox" name="materia_ids[]" value="<?= $midOpt ?>" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 shrink-0" <?= $chkMat ? 'checked' : '' ?>>
                                    <span class="leading-snug"><?= htmlspecialchars($lblMat, ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-sm text-gray-500 h-full min-h-[4rem] flex items-center">Não há matérias para filtrar neste modo (evento sem matérias fixas e sem linhas por matéria).</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/90 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-gray-500">As alterações do filtro são aplicadas ao clicar em Filtrar.</p>
                <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm" title="Aplicar filtros de turma e matéria na visualização">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filtrar
                </button>
            </div>
        </form>

        <form method="POST" action="<?= URL ?>/admin/faltas/salvar" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="evento_id" value="<?= $eventoAtualId ?>">
            <?php foreach ($turmaFiltroIds as $tfid): ?>
                <?php if ($tfid > 0): ?>
                    <input type="hidden" name="turma_ids[]" value="<?= $tfid ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <?php foreach ($materiaFiltroIds as $mfid): ?>
                <?php if ($mfid > 0): ?>
                    <input type="hidden" name="materia_ids[]" value="<?= $mfid ?>">
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary-custom inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg shadow-sm hover:opacity-90" title="Salvar faltas e observações da tabela">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Salvar
                </button>
            </div>

            <?php if ($linhasFaltas === [] && !$faltasMatriz): ?>
                <p class="text-sm text-gray-500">Nenhum aluno nas turmas deste evento (ou filtro de turma sem correspondência).</p>
            <?php elseif ($faltasMatriz && $matrizLinhas === []): ?>
                <p class="text-sm text-gray-500">Nenhum aluno nas turmas deste evento (ou filtro de turma sem correspondência).</p>
            <?php elseif ($faltasMatriz && $matrizColunas === [] && $matrizMateriasTodas === []): ?>
                <p class="text-sm text-amber-700">Este evento tem matérias configuradas, mas nenhuma foi encontrada no cadastro. Verifique os IDs ou recrie o evento.</p>
            <?php elseif ($faltasMatriz && $matrizColunas === [] && $matrizMateriasTodas !== [] && $materiaFiltroIds !== []): ?>
                <p class="text-sm text-amber-700">Nenhuma matéria ficou selecionada no filtro. Marque ao menos uma matéria nos filtros acima e clique em Filtrar.</p>
            <?php elseif ($faltasMatriz): ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <?php if ($exibirNumeroChamada): ?>
                            <th class="px-3 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wide w-14">Nº</th>
                            <?php endif; ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Aluno</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Turma</th>
                            <?php foreach ($matrizColunas as $col): ?>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap min-w-[8rem]"><?= htmlspecialchars((string) ($col['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide min-w-[12rem]">Observação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php foreach ($matrizLinhas as $ln): ?>
                            <?php
                            $aid = (int) ($ln['aluno_id'] ?? 0);
                            $obsRow = isset($lancMap[$aid . '_0']) ? (string) ($lancMap[$aid . '_0']['observacao'] ?? '') : '';
                            ?>
                            <tr class="hover:bg-gray-50/80">
                                <?php if ($exibirNumeroChamada): ?>
                                <td class="px-3 py-3 text-center text-sm font-semibold text-gray-700 align-middle"><?= !empty($ln['numero_chamada']) ? (int) $ln['numero_chamada'] : '—' ?></td>
                                <?php endif; ?>
                                <td class="px-4 py-3 font-medium text-gray-900 align-middle"><?= htmlspecialchars((string) ($ln['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-gray-700 whitespace-nowrap align-middle"><?= htmlspecialchars((string) ($ln['turma_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php foreach ($matrizColunas as $col): ?>
                                    <?php
                                    $mid = (int) ($col['id'] ?? 0);
                                    $mapKey = $aid . '_' . $mid;
                                    $lm = $lancMap[$mapKey] ?? ['faltas' => 0, 'observacao' => ''];
                                    ?>
                                    <td class="px-3 py-3 text-center align-middle">
                                        <input type="number" name="faltas[<?= $aid ?>][<?= $mid ?>]" min="0" step="0.5" value="<?= htmlspecialchars((string) ($lm['faltas'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" class="w-full min-w-[6.5rem] max-w-[8rem] mx-auto px-2 py-2 border border-gray-300 rounded-lg text-center text-sm" title="<?= htmlspecialchars((string) ($col['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    </td>
                                <?php endforeach; ?>
                                <td class="px-4 py-3 align-middle">
                                    <input type="text" name="observacao[<?= $aid ?>][0]" value="<?= htmlspecialchars((string) $obsRow, ENT_QUOTES, 'UTF-8') ?>" class="w-full min-w-[12rem] px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Opcional (linha)">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <?php if ($exibirNumeroChamada): ?>
                            <th class="px-3 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wide w-14">Nº</th>
                            <?php endif; ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Aluno</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Turma</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Matéria</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Faltas</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide min-w-[12rem]">Observação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php foreach ($linhasFaltas as $ln): ?>
                            <?php
                            $aid = (int) ($ln['aluno_id'] ?? 0);
                            $mid = (int) ($ln['materia_id'] ?? 0);
                            $mapKey = $aid . '_' . $mid;
                            $lm = $lancMap[$mapKey] ?? ['faltas' => 0, 'observacao' => ''];
                            ?>
                            <tr class="hover:bg-gray-50/80">
                                <?php if ($exibirNumeroChamada): ?>
                                <td class="px-3 py-3 text-center text-sm font-semibold text-gray-700"><?= !empty($ln['numero_chamada']) ? (int) $ln['numero_chamada'] : '—' ?></td>
                                <?php endif; ?>
                                <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars((string) ($ln['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-gray-700 whitespace-nowrap"><?= htmlspecialchars((string) ($ln['turma_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars((string) ($ln['materia_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3">
                                    <input type="number" name="faltas[<?= $aid ?>][<?= $mid ?>]" min="0" step="0.5" value="<?= htmlspecialchars((string) ($lm['faltas'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="observacao[<?= $aid ?>][<?= $mid ?>]" value="<?= htmlspecialchars((string) ($lm['observacao'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full min-w-[12rem] px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Opcional">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div>
                <div class="flex flex-wrap justify-end gap-2">
                    <a href="<?= htmlspecialchars($urlExportarLancamentoExcel . '?' . http_build_query($_GET), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-gray-700 bg-gray-100 border border-gray-200 rounded-lg hover:bg-gray-200" title="Exportar as faltas desta tela para Excel" aria-label="Exportar lançamento em Excel">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v6m0 0l-3-3m3 3l3-3M4 7h16M5 7l1-2h12l1 2"/></svg>
                        Exportar Excel
                    </a>
                    <button type="submit" class="btn-primary-custom inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg shadow-sm hover:opacity-90" title="Salvar faltas e observações da tabela">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Salvar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
