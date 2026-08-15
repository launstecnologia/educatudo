<?php
$aluno = $aluno ?? [];
$notas_lancamento_eventos = $notas_lancamento_eventos ?? [];
$boletins_gerados = $boletins_gerados ?? [];
$boletins_gerados_notas = $boletins_gerados_notas ?? [];
$boletins_gerados_boletim = $boletins_gerados_boletim ?? [];
$isPaginaBoletim = (($current_page ?? '') === 'boletim');
?>

<div class="max-w-7xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= $isPaginaBoletim ? 'Boletim' : 'Notas' ?></h1>
        <p class="text-gray-600">
            Acompanhe as <strong>notas lançadas</strong> e o <strong>boletim por matéria</strong> de <?= htmlspecialchars((string) ($aluno['nome'] ?? 'o aluno'), ENT_QUOTES, 'UTF-8') ?>.
        </p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <?php if (!$isPaginaBoletim): ?>
            <?php
            $quadro = $quadro_notas_semanais ?? [];
            $quadroFile = dirname(__DIR__, 2) . '/Modulos/notas-semanais/Views/aluno/quadro.php';
            $usarQuadroSemanal = !empty($quadro['modulo_ativo']) && !empty($quadro['tem_dados']) && is_file($quadroFile);
            ?>
            <?php if ($usarQuadroSemanal): ?>
                <?php
                $baseUrlNotas = URL . '/notas';
                $secaoNotas = 'notas';
                require $quadroFile;
                ?>
            <?php elseif (!empty($notas_lancamento_eventos)): ?>
                <div class="overflow-x-auto border border-gray-200 rounded-lg bg-white">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="px-4 py-2 font-semibold">Evento</th>
                                <th class="px-4 py-2 font-semibold">Data</th>
                                <th class="px-4 py-2 font-semibold">Matéria</th>
                                <th class="px-4 py-2 font-semibold">Nota</th>
                                <th class="px-4 py-2 font-semibold">Atualizado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($notas_lancamento_eventos as $nl): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-gray-900"><?= htmlspecialchars((string) ($nl['bloco_titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?= !empty($nl['bloco_data_prova']) ? date('d/m/Y', strtotime((string) $nl['bloco_data_prova'])) : '—' ?></td>
                                    <td class="px-4 py-2 text-gray-800"><?= htmlspecialchars((string) ($nl['materia_nome'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-2">
                                        <?php if ($nl['nota'] === null || $nl['nota'] === ''): ?>
                                            <span class="text-amber-700">Pendente</span>
                                        <?php else: ?>
                                            <span class="font-semibold text-gray-900"><?= htmlspecialchars(number_format((float) $nl['nota'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-600"><?= !empty($nl['updated_at']) ? date('d/m/Y H:i', strtotime((string) $nl['updated_at'])) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($boletins_gerados_notas)): ?>
                <div class="mt-6">
                    <?php
                    $boletinsGeradosBackup = $boletins_gerados;
                    $boletins_gerados = $boletins_gerados_notas;
                    require __DIR__ . '/../partials/boletins_gerados.php';
                    $boletins_gerados = $boletinsGeradosBackup;
                    ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php
            $boletinsGeradosBackup = $boletins_gerados;
            $boletins_gerados = $boletins_gerados_boletim;
            require __DIR__ . '/../partials/boletins_gerados.php';
            $boletins_gerados = $boletinsGeradosBackup;
            ?>
            <?php if (empty($boletins_gerados_boletim)): ?>
                <div class="text-center py-10 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-gray-500">Nenhum boletim gerado ainda.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
