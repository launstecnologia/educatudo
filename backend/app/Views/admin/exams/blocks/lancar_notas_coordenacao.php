<?php
$bloco = $bloco ?? [];
$linhas = $linhas ?? [];
$materiasFiltro = $materias_filtro ?? [];
$turmasFiltro = $turmas_filtro ?? [];
$seriesFiltro = $series_filtro ?? [];
$materiaIdFiltro = (int) ($materia_id_filtro ?? 0);
$turmaIdFiltro = (int) ($turma_id_filtro ?? 0);
$serieIdFiltro = (int) ($serie_id_filtro ?? 0);
$ordenarFiltro = (string) ($ordenar_filtro ?? 'nome');
if (!in_array($ordenarFiltro, ['nome', 'chamada', 'sexo'], true)) {
    $ordenarFiltro = 'nome';
}
$csrfToken = $csrf_token ?? '';
$flash = $flash ?? [];
$notaUnicaTodasMaterias = !empty($bloco['nota_unica_todas_materias']);
$colunasTabela = ($notaUnicaTodasMaterias ? 4 : 6) + 1;
$blocoId = (int) ($bloco['id'] ?? 0);
$actionFiltro = URL . '/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao';
?>

<div class="mb-8 flex flex-wrap justify-between items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Lançamento de notas (Coordenação)</h2>
        <p class="text-gray-600 mt-1"><?= htmlspecialchars((string) ($bloco['titulo'] ?? '')) ?></p>
    </div>
    <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar"
       class="text-gray-600 hover:text-gray-900">← Voltar ao painel</a>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= (!empty($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg p-5 mb-6">
    <form method="get" action="<?= htmlspecialchars($actionFiltro) ?>" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 items-end">
        <?php if (!$notaUnicaTodasMaterias): ?>
        <div>
            <label for="materia_id" class="block text-sm font-medium text-gray-700 mb-1">Matéria</label>
            <select id="materia_id" name="materia_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="0" <?= $materiaIdFiltro === 0 ? 'selected' : '' ?>>Todas as matérias</option>
                <?php foreach ($materiasFiltro as $mid => $mnome): ?>
                    <option value="<?= (int) $mid ?>" <?= $materiaIdFiltro === (int) $mid ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $mnome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label for="serie_id" class="block text-sm font-medium text-gray-700 mb-1">Série</label>
            <select id="serie_id" name="serie_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="0" <?= $serieIdFiltro === 0 ? 'selected' : '' ?>>Todas as séries</option>
                <?php foreach ($seriesFiltro as $sid => $snome): ?>
                    <option value="<?= (int) $sid ?>" <?= $serieIdFiltro === (int) $sid ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $snome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
            <select id="turma_id" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="0" <?= $turmaIdFiltro === 0 ? 'selected' : '' ?>>Todas as turmas</option>
                <?php foreach ($turmasFiltro as $tid => $tnome): ?>
                    <option value="<?= (int) $tid ?>" <?= $turmaIdFiltro === (int) $tid ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $tnome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="ordenar" class="block text-sm font-medium text-gray-700 mb-1">Ordenar por</label>
            <select id="ordenar" name="ordenar" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="nome" <?= $ordenarFiltro === 'nome' ? 'selected' : '' ?>>Nome (A–Z)</option>
                <option value="chamada" <?= $ordenarFiltro === 'chamada' ? 'selected' : '' ?>>Número da chamada</option>
                <option value="sexo" <?= $ordenarFiltro === 'sexo' ? 'selected' : '' ?>>Sexo</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-primary-custom w-full inline-flex items-center justify-center px-4 py-2 rounded-lg font-semibold hover:opacity-90">
                Aplicar filtro
            </button>
        </div>
    </form>
    <p class="text-xs text-gray-500 mt-3">Informe a nota de 0 a 10. Deixe em branco para manter sem nota.</p>
    <?php if ($notaUnicaTodasMaterias): ?>
    <p class="text-xs text-violet-800 mt-2">Configuração ativa: mesma nota para todas as matérias do evento. Ao salvar, a nota informada para o aluno é replicada nas demais matérias.</p>
    <?php endif; ?>
</div>

<form method="post" action="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/lancar-notas-coordenacao" class="space-y-4">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
    <input type="hidden" name="materia_id_filtro" value="<?= $materiaIdFiltro ?>">
    <input type="hidden" name="turma_id_filtro" value="<?= $turmaIdFiltro ?>">
    <input type="hidden" name="serie_id_filtro" value="<?= $serieIdFiltro ?>">
    <input type="hidden" name="ordenar_filtro" value="<?= htmlspecialchars($ordenarFiltro) ?>">

    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if (!$notaUnicaTodasMaterias): ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-16">Nº</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-36">Nota</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($linhas)): ?>
                    <tr>
                        <td colspan="<?= (int) $colunasTabela ?>" class="px-4 py-8 text-center text-gray-500">Nenhum aluno encontrado para o filtro selecionado.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($linhas as $ln): ?>
                            <?php
                            $pid = (int) ($ln['professor_id'] ?? 0);
                            $mid = (int) ($ln['materia_id'] ?? 0);
                            $tid = (int) ($ln['turma_id'] ?? 0);
                            $aid = (int) ($ln['aluno_id'] ?? 0);
                            $notaStr = ($ln['nota'] !== null && $ln['nota'] !== '')
                                ? number_format((float) $ln['nota'], 2, '.', '')
                                : '';
                            $nChamada = (int) ($ln['numero_chamada'] ?? 0);
                            ?>
                            <tr class="hover:bg-gray-50">
                                <?php if (!$notaUnicaTodasMaterias): ?>
                                <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars((string) ($ln['materia_nome'] ?? '')) ?></td>
                                <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars((string) ($ln['professor_nome'] ?? '')) ?></td>
                                <?php endif; ?>
                                <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars((string) ($ln['turma_nome'] ?? '')) ?></td>
                                <td class="px-4 py-3 text-center text-gray-700 font-semibold"><?= $nChamada > 0 ? $nChamada : '—' ?></td>
                                <td class="px-4 py-3 text-gray-900 font-medium"><?= htmlspecialchars((string) ($ln['aluno_nome'] ?? '')) ?></td>
                                <td class="px-4 py-3">
                                    <input type="text"
                                           inputmode="decimal"
                                           name="notas[<?= $pid ?>][<?= $mid ?>][<?= $tid ?>][<?= $aid ?>]"
                                           value="<?= htmlspecialchars($notaStr) ?>"
                                           class="w-full px-2 py-1.5 border border-gray-300 rounded-lg"
                                           placeholder="—"
                                           autocomplete="off">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text"
                                           name="observacoes[<?= $pid ?>][<?= $mid ?>][<?= $tid ?>][<?= $aid ?>]"
                                           value="<?= htmlspecialchars((string) ($ln['observacao'] ?? '')) ?>"
                                           class="w-full px-2 py-1.5 border border-gray-300 rounded-lg"
                                           maxlength="500"
                                           placeholder="Opcional">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($linhas)): ?>
    <div class="flex gap-3">
        <button type="submit" class="btn-primary-custom inline-flex items-center px-6 py-3 rounded-lg font-semibold hover:opacity-90">
            Salvar notas
        </button>
        <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar"
           class="inline-flex items-center px-6 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50">
            Cancelar
        </a>
    </div>
    <?php endif; ?>
</form>
