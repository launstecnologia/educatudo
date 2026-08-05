<?php
$provas_realizadas = is_array($provas_realizadas ?? null) ? $provas_realizadas : [];
$notas_lancamento_eventos = is_array($notas_lancamento_eventos ?? null) ? $notas_lancamento_eventos : [];
$provas_matriz_blocos = is_array($provas_matriz_blocos ?? null) ? $provas_matriz_blocos : [];
$pm = $provas_matriz_blocos;
$pmTabelas = is_array($pm['tabelas'] ?? null) ? $pm['tabelas'] : [];
$pmTem = !empty($pm['tem_dados']) && $pmTabelas !== [];
$temNotasLancamento = $notas_lancamento_eventos !== [];
$totalRegistrosNotas = count($provas_realizadas) + count($notas_lancamento_eventos);
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h3 class="text-xl font-bold text-gray-900">Provas realizadas</h3>
        <p class="text-sm text-gray-500 mt-1">
            <strong>Provas online:</strong> tabelas por bloco com <strong>✓</strong> acertos, <strong>✗</strong> erros e <strong>Q</strong> questões.
            <strong>Eventos só com nota:</strong> quando a escola usa lançamento de nota (sem questões no sistema), a nota (0 a 10) aparece na seção abaixo.
        </p>
    </div>
    <span class="text-sm text-gray-500 whitespace-nowrap"><?= (int) $totalRegistrosNotas ?> lançamentos</span>
</div>

<?php if ($temNotasLancamento): ?>
    <div class="mb-8 rounded-xl border border-purple-200 bg-purple-50/60 p-5">
        <h4 class="text-lg font-semibold text-gray-900 mb-1">Notas de eventos (lançamento pela escola)</h4>
        <p class="text-sm text-gray-600 mb-4">Nota cheia por matéria/evento, informada pelo professor conforme calendário da coordenação.</p>
        <div class="overflow-x-auto border border-purple-100 rounded-lg bg-white">
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
                        <td class="px-4 py-2 text-gray-700">
                            <?= !empty($nl['bloco_data_prova']) ? date('d/m/Y', strtotime((string) $nl['bloco_data_prova'])) : '—' ?>
                        </td>
                        <td class="px-4 py-2 text-gray-800"><?= htmlspecialchars((string) ($nl['materia_nome'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-2">
                            <?php if ($nl['nota'] === null || $nl['nota'] === ''): ?>
                                <span class="text-amber-700">Pendente</span>
                            <?php else: ?>
                                <span class="font-semibold text-gray-900"><?= htmlspecialchars(number_format((float) $nl['nota'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2 text-gray-600">
                            <?= !empty($nl['updated_at']) ? date('d/m/Y H:i', strtotime((string) $nl['updated_at'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (!$pmTem && !$temNotasLancamento): ?>
    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhuma prova realizada.</p>
    </div>
<?php elseif ($pmTem): ?>
    <h4 class="text-lg font-semibold text-gray-800 mb-3">Provas online (questões no sistema)</h4>
    <div class="space-y-3">
        <?php $pmIdx = 0; ?>
        <?php foreach ($pmTabelas as $tab):
            $pmColunas = is_array($tab['colunas'] ?? null) ? $tab['colunas'] : [];
            $pmMaterias = is_array($tab['materias'] ?? null) ? $tab['materias'] : [];
            if ($pmColunas === [] || $pmMaterias === []) {
                continue;
            }
            $pmIdx++;
            $pmTituloTab = (string) ($tab['titulo_secao'] ?? 'Provas');
            $pmBimestreTab = (int) ($tab['bimestre'] ?? 0);
            $pmAnoTab = (int) ($tab['ano_letivo'] ?? 0);
        ?>
            <div class="border border-gray-200 rounded-lg p-4 bg-white flex items-center justify-between gap-3">
                <div>
                    <div class="text-base font-semibold text-gray-900"><?= htmlspecialchars($pmTituloTab, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-sm text-gray-600">
                        Bimestre: <?= $pmBimestreTab > 0 ? ($pmBimestreTab . 'º') : 'N/A' ?>
                        | Ano: <?= $pmAnoTab > 0 ? $pmAnoTab : 'N/A' ?>
                        | Matérias: <?= count($pmMaterias) ?>
                    </div>
                </div>
                <button type="button"
                        class="px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 shrink-0"
                        onclick="<?= htmlspecialchars("abrirModalConteudoVisualizar('content-pm-tabela-{$pmIdx}', " . json_encode($pmTituloTab, JSON_UNESCAPED_UNICODE) . ')', ENT_QUOTES, 'UTF-8') ?>">
                    Abrir
                </button>
            </div>
            <div id="content-pm-tabela-<?= $pmIdx ?>" class="hidden">
                <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white">
                    <table class="min-w-full border-collapse text-sm text-center">
                        <thead>
                            <tr class="bg-[#e9ecef] text-gray-800">
                                <th rowspan="2" class="border border-gray-300 px-3 py-2 text-left font-semibold align-middle">
                                    Matérias
                                </th>
                                <?php foreach ($pmColunas as $col): ?>
                                    <th colspan="3" class="border border-gray-300 px-2 py-2 font-semibold">
                                        <div><?= htmlspecialchars((string) ($col['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs font-normal text-gray-600">
                                            <?= htmlspecialchars((string) ($col['data_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                                <th colspan="3" class="border border-gray-300 px-2 py-2 font-semibold">Total</th>
                            </tr>
                            <tr class="bg-[#e9ecef] text-gray-800">
                                <?php foreach ($pmColunas as $_): ?>
                                    <th class="border border-gray-300 px-1 py-1 text-xs font-semibold text-green-700" title="Acertos">✓</th>
                                    <th class="border border-gray-300 px-1 py-1 text-xs font-semibold text-red-700" title="Erros">✗</th>
                                    <th class="border border-gray-300 px-1 py-1 text-xs font-semibold text-gray-700" title="Questões">Q</th>
                                <?php endforeach; ?>
                                <th class="border border-gray-300 px-1 py-1 text-xs font-semibold text-green-700" title="Acertos">✓</th>
                                <th class="border border-gray-300 px-1 py-1 text-xs font-semibold text-red-700" title="Erros">✗</th>
                                <th class="border border-gray-300 px-1 py-1 text-xs font-semibold text-gray-700" title="Questões">Q</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pmMaterias as $linha): ?>
                                <tr class="bg-white hover:bg-gray-50">
                                    <td class="border border-gray-300 px-3 py-2 text-left font-medium text-gray-900">
                                        <?= htmlspecialchars((string) ($linha['materia'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <?php foreach ($pmColunas as $col):
                                        $ck = $col['key'] ?? '';
                                        $c = ($linha['celdas'][$ck] ?? []) + ['q' => 0, 'acertos' => 0, 'erros' => 0];
                                        $vazio = ((int) ($c['q'] ?? 0)) <= 0 && ((int) ($c['acertos'] ?? 0)) <= 0 && ((int) ($c['erros'] ?? 0)) <= 0;
                                    ?>
                                        <td class="border border-gray-300 px-1 py-1 text-green-700 font-medium"><?= $vazio ? '—' : (int) $c['acertos'] ?></td>
                                        <td class="border border-gray-300 px-1 py-1 text-red-700 font-medium"><?= $vazio ? '—' : (int) $c['erros'] ?></td>
                                        <td class="border border-gray-300 px-1 py-1 text-gray-800"><?= $vazio ? '—' : (int) $c['q'] ?></td>
                                    <?php endforeach; ?>
                                    <?php
                                    $t = ($linha['totais'] ?? []) + ['q' => 0, 'acertos' => 0, 'erros' => 0];
                                    $tVazio = ((int) $t['q']) <= 0 && ((int) $t['acertos']) <= 0 && ((int) $t['erros']) <= 0;
                                    ?>
                                    <td class="border border-gray-300 px-1 py-1 text-green-700 font-semibold"><?= $tVazio ? '—' : (int) $t['acertos'] ?></td>
                                    <td class="border border-gray-300 px-1 py-1 text-red-700 font-semibold"><?= $tVazio ? '—' : (int) $t['erros'] ?></td>
                                    <td class="border border-gray-300 px-1 py-1 text-gray-900 font-semibold"><?= $tVazio ? '—' : (int) $t['q'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/modal_abrir_conteudo_visualizar.php'; ?>
