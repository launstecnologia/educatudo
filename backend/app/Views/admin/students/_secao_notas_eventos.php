<?php
$boletim_eventos_notas = is_array($boletim_eventos_notas ?? null) ? $boletim_eventos_notas : [];
$boletins_gerados_notas_por_regra = is_array($boletins_gerados_notas_por_regra ?? null) ? $boletins_gerados_notas_por_regra : [];
?>
<div class="flex items-center justify-between mb-6">
    <h3 class="text-xl font-bold text-gray-900">Eventos de notas</h3>
    <span class="text-sm text-gray-500"><?= count($boletim_eventos_notas) ?> evento(s)</span>
</div>

<?php if (empty($boletim_eventos_notas)): ?>
    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhum evento de notas visível para coordenação.</p>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($boletim_eventos_notas as $ev): ?>
            <?php
                $ridNota = (int)($ev['id'] ?? 0);
                $geradoNota = $ridNota > 0 ? ($boletins_gerados_notas_por_regra[$ridNota] ?? null) : null;
                $updatedFmt = !empty($ev['updated_at']) ? date('d/m/Y H:i', strtotime((string)$ev['updated_at'])) : '-';
            ?>
            <div class="border border-gray-200 rounded-lg p-4 bg-white">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-base font-semibold text-gray-900"><?= safe_htmlspecialchars($ev['nome'] ?? '', 'Evento') ?></div>
                        <div class="text-sm text-gray-600">
                            <?php $bimestreNota = $ev['bimestre'] ?? null; ?>
                            <?php $anoNota = $ev['ano_letivo'] ?? null; ?>
                            Bimestre: <?= $bimestreNota ? ((int) $bimestreNota . 'º') : 'N/A' ?>
                            | Ano: <?= $anoNota ? (int) $anoNota : 'N/A' ?>
                            | Atualizado: <?= safe_htmlspecialchars($updatedFmt, '-') ?>
                        </div>
                    </div>
                    <?php if (is_array($geradoNota) && !empty($geradoNota['linhas'])): ?>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
                                data-notas-title="<?= safe_htmlspecialchars($ev['nome'] ?? '', 'Evento de Notas') ?>"
                                onclick="abrirModalNotasEvento('modal-notas-evento-<?= $ridNota ?>', this)">
                                Abrir notas
                            </button>
                            <button
                                type="button"
                                class="px-3 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700"
                                data-notas-title="<?= safe_htmlspecialchars($ev['nome'] ?? '', 'Evento de Notas') ?>"
                                onclick="imprimirNotasEvento('modal-notas-evento-<?= $ridNota ?>', this)">
                                Imprimir
                            </button>
                        </div>
                    <?php else: ?>
                        <span class="text-xs text-amber-700">Sem tabela gerada no banco para este evento.</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (is_array($geradoNota) && !empty($geradoNota['linhas'])): ?>
                <div id="modal-notas-evento-<?= $ridNota ?>" class="hidden">
                    <?php
                    $boletinsGeradosBackup = $boletins_gerados;
                    $boletimPodeExcluirBackup = $boletim_pode_excluir ?? false;
                    $boletimAlunoIdBackup = $boletim_aluno_id ?? 0;
                    $boletins_gerados = [$geradoNota];
                    $boletim_pode_excluir = false;
                    $boletim_aluno_id = 0;
                    require __DIR__ . '/../../partials/boletins_gerados.php';
                    $boletins_gerados = $boletinsGeradosBackup;
                    $boletim_pode_excluir = $boletimPodeExcluirBackup;
                    $boletim_aluno_id = $boletimAlunoIdBackup;
                    ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
