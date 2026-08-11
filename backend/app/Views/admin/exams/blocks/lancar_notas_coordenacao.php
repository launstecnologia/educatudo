<?php
$bloco = $bloco ?? [];
$linhas = $linhas ?? [];
$materiasFiltro = $materias_filtro ?? [];
$materiaIdFiltro = (int) ($materia_id_filtro ?? 0);
$csrfToken = $csrf_token ?? '';
$flash = $flash ?? [];
$notaUnicaTodasMaterias = !empty($bloco['nota_unica_todas_materias']);
$fontesImportacao = is_array($fontes_importacao_notas ?? null) ? $fontes_importacao_notas : [];
?>

<div class="mb-8 flex flex-wrap justify-between items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Lançamento de notas (Coordenação)</h2>
        <p class="text-gray-600 mt-1"><?= htmlspecialchars((string) ($bloco['titulo'] ?? '')) ?></p>
    </div>
    <a href="<?= URL ?>/admin/provas/blocos/<?= (int) ($bloco['id'] ?? 0) ?>/gerenciar"
       class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">← Voltar ao painel</a>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= (!empty($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg p-5 mb-6">
    <?php if (!$notaUnicaTodasMaterias): ?>
    <form method="get" action="<?= URL ?>/admin/provas/blocos/<?= (int) ($bloco['id'] ?? 0) ?>/lancar-notas-coordenacao" class="flex flex-wrap items-end gap-3">
        <div>
            <label for="materia_id" class="block text-sm font-medium text-gray-700 mb-1">Matéria</label>
            <select id="materia_id" name="materia_id" class="px-3 py-2 border border-gray-300 rounded-lg" style="min-width:260px;">
                <option value="0" <?= $materiaIdFiltro === 0 ? 'selected' : '' ?>>Todas as matérias</option>
                <?php foreach ($materiasFiltro as $mid => $mnome): ?>
                    <option value="<?= (int) $mid ?>" <?= $materiaIdFiltro === (int) $mid ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $mnome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Aplicar filtro</button>
    </form>
    <?php endif; ?>
    <p class="text-xs text-gray-500 mt-2">Informe a nota de 0 a 10. Deixe em branco para manter sem nota.</p>
    <?php if ($notaUnicaTodasMaterias): ?>
    <p class="text-xs text-violet-800 mt-2">Configuração ativa: mesma nota para todas as matérias do evento. Ao salvar, a nota informada para o aluno é replicada nas demais matérias.</p>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-lg p-5 mb-6 border-l-4 border-indigo-500">
    <div class="mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Importar notas de outro evento</h3>
        <p class="text-sm text-gray-600 mt-1">
            A nota será gravada na <strong>mesma matéria e no mesmo professor</strong> exibidos na opção escolhida.
            <?php if ($materiaIdFiltro > 0 && isset($materiasFiltro[$materiaIdFiltro])): ?>
                Destino atual: <strong><?= htmlspecialchars((string) $materiasFiltro[$materiaIdFiltro]) ?></strong>.
            <?php endif; ?>
        </p>
    </div>

    <?php if (empty($fontesImportacao)): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Nenhum outro evento possui notas compatíveis para o professor e a matéria deste filtro.
        </div>
    <?php else: ?>
        <form method="post"
              action="<?= URL ?>/admin/provas/blocos/<?= (int) ($bloco['id'] ?? 0) ?>/importar-notas-internas"
              class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 items-end"
              id="formImportacaoNotasLancamento">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
            <input type="hidden" name="retornar_lancamento" value="1">
            <input type="hidden" name="materia_id_filtro" value="<?= (int) $materiaIdFiltro ?>">
            <input type="hidden" name="fonte_bloco_id" id="importFonteBlocoId" value="">
            <input type="hidden" name="fonte_professor_id" id="importFonteProfessorId" value="">
            <input type="hidden" name="fonte_materia_id" id="importFonteMateriaId" value="">

            <div>
                <label for="importFonteCompleta" class="block text-sm font-medium text-gray-700 mb-1">
                    Evento de origem → destino neste lançamento
                </label>
                <select id="importFonteCompleta" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Selecione o evento, professor e matéria</option>
                    <?php foreach ($fontesImportacao as $fonte): ?>
                        <?php
                        $fonteBlocoId = (int) ($fonte['bloco_id'] ?? 0);
                        $fonteProfessorId = (int) ($fonte['professor_id'] ?? 0);
                        $fonteMateriaId = (int) ($fonte['materia_id'] ?? 0);
                        $fonteBimestre = (int) ($fonte['bimestre'] ?? 0);
                        $fonteData = !empty($fonte['data_prova']) ? date('d/m/Y', strtotime((string) $fonte['data_prova'])) : '';
                        ?>
                        <option value="<?= $fonteBlocoId ?>_<?= $fonteProfessorId ?>_<?= $fonteMateriaId ?>"
                                data-bloco="<?= $fonteBlocoId ?>"
                                data-professor="<?= $fonteProfessorId ?>"
                                data-materia="<?= $fonteMateriaId ?>">
                            <?= htmlspecialchars((string) ($fonte['bloco_titulo'] ?? ('Evento #' . $fonteBlocoId))) ?>
                            <?= $fonteBimestre > 0 ? ' · ' . $fonteBimestre . 'º bimestre' : '' ?>
                            <?= $fonteData !== '' ? ' · ' . htmlspecialchars($fonteData) : '' ?>
                            → <?= htmlspecialchars((string) ($fonte['materia_nome'] ?? 'Matéria')) ?>
                            · <?= htmlspecialchars((string) ($fonte['professor_nome'] ?? 'Professor')) ?>
                            (<?= (int) ($fonte['total_notas'] ?? 0) ?> notas)
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="inline-flex items-start gap-2 text-sm text-gray-700 mt-3 cursor-pointer">
                    <input type="checkbox" name="sobrescrever" value="1"
                           class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span>Sobrescrever notas que já existem neste lançamento</span>
                </label>
            </div>

            <button type="submit" id="btnImportarNotasLancamento" disabled
                    class="btn-primary-custom inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                Importar para esta matéria
            </button>
        </form>

        <script>
        (function () {
            var select = document.getElementById('importFonteCompleta');
            var bloco = document.getElementById('importFonteBlocoId');
            var professor = document.getElementById('importFonteProfessorId');
            var materia = document.getElementById('importFonteMateriaId');
            var botao = document.getElementById('btnImportarNotasLancamento');
            if (!select || !bloco || !professor || !materia || !botao) return;
            select.addEventListener('change', function () {
                var option = select.options[select.selectedIndex];
                bloco.value = option && option.dataset.bloco ? option.dataset.bloco : '';
                professor.value = option && option.dataset.professor ? option.dataset.professor : '';
                materia.value = option && option.dataset.materia ? option.dataset.materia : '';
                botao.disabled = !bloco.value || !professor.value || !materia.value;
            });
        })();
        </script>
    <?php endif; ?>
</div>

<form method="post" action="<?= URL ?>/admin/provas/blocos/<?= (int) ($bloco['id'] ?? 0) ?>/lancar-notas-coordenacao" class="space-y-4">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
    <input type="hidden" name="materia_id_filtro" value="<?= (int) $materiaIdFiltro ?>">

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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-36">Nota</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($linhas)): ?>
                    <tr>
                        <td colspan="<?= $notaUnicaTodasMaterias ? '4' : '6' ?>" class="px-4 py-8 text-center text-gray-500">Nenhum aluno encontrado para o filtro selecionado.</td>
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
                            ?>
                            <tr class="hover:bg-gray-50">
                                <?php if (!$notaUnicaTodasMaterias): ?>
                                <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars((string) ($ln['materia_nome'] ?? '')) ?></td>
                                <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars((string) ($ln['professor_nome'] ?? '')) ?></td>
                                <?php endif; ?>
                                <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars((string) ($ln['turma_nome'] ?? '')) ?></td>
                                <td class="px-4 py-3 text-gray-900 font-medium"><?= htmlspecialchars((string) ($ln['aluno_nome'] ?? '')) ?></td>
                                <td class="px-4 py-3">
                                    <input type="text"
                                           inputmode="decimal"
                                           name="notas[<?= $pid ?>][<?= $mid ?>][<?= $tid ?>][<?= $aid ?>]"
                                           value="<?= htmlspecialchars($notaStr) ?>"
                                           class="w-full border border-gray-300 rounded-lg px-2 py-1.5"
                                           placeholder="—"
                                           autocomplete="off">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text"
                                           name="observacoes[<?= $pid ?>][<?= $mid ?>][<?= $tid ?>][<?= $aid ?>]"
                                           value="<?= htmlspecialchars((string) ($ln['observacao'] ?? '')) ?>"
                                           class="w-full border border-gray-300 rounded-lg px-2 py-1.5"
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
        <a href="<?= URL ?>/admin/provas/blocos/<?= (int) ($bloco['id'] ?? 0) ?>/gerenciar"
           class="inline-flex items-center px-6 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50">
            Cancelar
        </a>
    </div>
    <?php endif; ?>
</form>
