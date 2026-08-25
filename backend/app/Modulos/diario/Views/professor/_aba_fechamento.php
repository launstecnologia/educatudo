<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Bimestres — <?= (int) $ano_letivo ?></h2>
            <p class="text-sm text-gray-500 mt-0.5">Cada bimestre só pode ser fechado quando todas as chamadas do próprio período estiverem registradas.</p>
        </div>
        <ul class="divide-y divide-gray-100">
            <?php for ($b = 1; $b <= 4; $b++):
                $info = $bimestres[$b] ?? null;
                if (!$info) continue;
                $periodo = $info['periodo'];
                $resumoB = $info['resumo'];
                $pendentesB = (int) ($resumoB['chamadas_pendentes'] ?? 0);
                $fechamento = $info['fechamento'];
                $fechado = $fechamento && (string) $fechamento['status'] === 'fechado';
            ?>
                <li class="px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900"><?= $b ?>º Bimestre</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <?= date('d/m', strtotime($periodo['inicio'])) ?> a <?= date('d/m/Y', strtotime($periodo['fim'])) ?>
                            · <?= (int) ($resumoB['aulas_finalizadas'] ?? 0) ?>/<?= (int) ($resumoB['aulas_previstas'] ?? 0) ?> aulas realizadas
                        </p>
                        <?php if ($fechado): ?>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Fechado em <?= !empty($fechamento['fechado_em']) ? date('d/m/Y H:i', strtotime((string) $fechamento['fechado_em'])) : '—' ?>
                                — peça à coordenação para reabrir se precisar editar.
                            </p>
                        <?php elseif ($pendentesB > 0): ?>
                            <p class="text-xs text-red-700 mt-0.5"><?= $pendentesB ?> chamada(s) pendente(s) neste bimestre.</p>
                        <?php else: ?>
                            <p class="text-xs text-green-700 mt-0.5">Sem pendências — apto para fechamento.</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($fechado): ?>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-600 w-fit">
                            <i class="fa-solid fa-lock mr-1.5"></i>Fechado
                        </span>
                    <?php else: ?>
                        <form method="post" action="<?= URL ?>/professor/diarios/fechar"
                              onsubmit="return <?= $pendentesB > 0 ? 'false' : 'confirm(\'Fechar o ' . $b . 'º bimestre? Só a coordenação poderá reabrir.\')' ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                            <input type="hidden" name="materia_id" value="<?= $materiaId ?>">
                            <input type="hidden" name="ano_letivo" value="<?= (int) $ano_letivo ?>">
                            <input type="hidden" name="bimestre" value="<?= $b ?>">
                            <button type="submit" <?= $pendentesB > 0 ? 'disabled' : '' ?>
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold <?= $pendentesB > 0 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-purple-600 text-white hover:bg-purple-700' ?>">
                                Fechar bimestre
                            </button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endfor; ?>
        </ul>
    </div>
</div>
