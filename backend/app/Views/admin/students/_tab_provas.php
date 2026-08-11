<?php
/**
 * Conteúdo da aba "Provas" do Detalhe do Aluno (admin/students/show.php).
 * Extraído para partial porque agora é carregado sob demanda via AJAX
 * (StudentAdminController::provasTabFragment) em vez de ir junto na carga
 * inicial da página — ver app/Services/AdminStudentProfileService::getProvasTabData().
 *
 * Espera: $provas_realizadas, $provas_matriz_blocos
 */
$provas_realizadas = $provas_realizadas ?? [];
$provas_matriz_blocos = $provas_matriz_blocos ?? ['tabelas' => [], 'tem_dados' => false];

// safe_htmlspecialchars() normalmente é definida em admin/students/show.php; este
// partial também é renderizado isoladamente (fragmento AJAX da aba), por isso
// garante a própria definição aqui.
if (!function_exists('safe_htmlspecialchars')) {
    function safe_htmlspecialchars($value, $default = '') {
        if (is_array($value)) {
            return htmlspecialchars($default);
        }
        if ($value === null) {
            return htmlspecialchars($default);
        }
        return htmlspecialchars((string) $value);
    }
}
?>
<div class="flex items-center justify-between mb-4">
    <div>
        <h3 class="text-xl font-bold text-gray-900">Provas realizadas</h3>
    </div>
    <span class="text-sm text-gray-500 whitespace-nowrap"><?= count($provas_realizadas ?? []) ?> lançamentos</span>
</div>
<?php
$pm = $provas_matriz_blocos;
$pmTabelas = is_array($pm['tabelas'] ?? null) ? $pm['tabelas'] : [];
$pmTem = !empty($pm['tem_dados']) && $pmTabelas !== [];
?>
<?php if (!$pmTem): ?>
    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhuma prova realizada.</p>
    </div>
<?php else: ?>
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
            $pmTotalMaterias = count($pmMaterias);
        ?>
            <div class="border border-gray-200 rounded-lg p-4 bg-white">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-base font-semibold text-gray-900"><?= safe_htmlspecialchars($pmTituloTab, 'Provas') ?></div>
                        <div class="text-sm text-gray-600">
                            Bimestre: <?= $pmBimestreTab > 0 ? ($pmBimestreTab . 'º') : 'N/A' ?>
                            | Ano: <?= $pmAnoTab > 0 ? $pmAnoTab : 'N/A' ?>
                            | Matérias: <?= $pmTotalMaterias ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
                            data-notas-title="<?= safe_htmlspecialchars($pmTituloTab, 'Provas') ?>"
                            onclick="abrirModalNotasEvento('content-provas-tabela-<?= $pmIdx ?>', this)">
                            Abrir
                        </button>
                        <button
                            type="button"
                            class="px-3 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700"
                            data-notas-title="<?= safe_htmlspecialchars($pmTituloTab, 'Provas') ?>"
                            onclick="imprimirNotasEvento('content-provas-tabela-<?= $pmIdx ?>', this)">
                            Imprimir
                        </button>
                    </div>
                </div>
            </div>
            <div id="content-provas-tabela-<?= $pmIdx ?>" class="hidden">
                <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white">
                    <table class="min-w-full border-collapse text-sm text-center">
                        <thead>
                            <tr class="bg-[#e9ecef] text-gray-800">
                                <th rowspan="2" class="border border-gray-300 px-3 py-2 text-left font-semibold align-middle">
                                    Matérias
                                </th>
                                <?php foreach ($pmColunas as $col): ?>
                                    <th colspan="3" class="border border-gray-300 px-2 py-2 font-semibold">
                                        <div><?= safe_htmlspecialchars($col['titulo'] ?? '', '') ?></div>
                                        <div class="text-xs font-normal text-gray-600">
                                            <?= safe_htmlspecialchars($col['data_label'] ?? '', '') ?>
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
                                        <?= safe_htmlspecialchars($linha['materia'] ?? '', '—') ?>
                                    </td>
                                    <?php foreach ($pmColunas as $col):
                                        $ck = $col['key'] ?? '';
                                        $c = ($linha['celdas'][$ck] ?? []) + ['q' => 0, 'acertos' => 0, 'erros' => 0];
                                        $vazio = ((int)($c['q'] ?? 0)) <= 0 && ((int)($c['acertos'] ?? 0)) <= 0 && ((int)($c['erros'] ?? 0)) <= 0;
                                    ?>
                                        <td class="border border-gray-300 px-1 py-1 text-green-700 font-medium"><?= $vazio ? '—' : (int)$c['acertos'] ?></td>
                                        <td class="border border-gray-300 px-1 py-1 text-red-700 font-medium"><?= $vazio ? '—' : (int)$c['erros'] ?></td>
                                        <td class="border border-gray-300 px-1 py-1 text-gray-800"><?= $vazio ? '—' : (int)$c['q'] ?></td>
                                    <?php endforeach; ?>
                                    <?php
                                        $t = ($linha['totais'] ?? []) + ['q' => 0, 'acertos' => 0, 'erros' => 0];
                                        $tVazio = ((int)$t['q']) <= 0 && ((int)$t['acertos']) <= 0 && ((int)$t['erros']) <= 0;
                                    ?>
                                    <td class="border border-gray-300 px-1 py-1 text-green-700 font-semibold"><?= $tVazio ? '—' : (int)$t['acertos'] ?></td>
                                    <td class="border border-gray-300 px-1 py-1 text-red-700 font-semibold"><?= $tVazio ? '—' : (int)$t['erros'] ?></td>
                                    <td class="border border-gray-300 px-1 py-1 text-gray-900 font-semibold"><?= $tVazio ? '—' : (int)$t['q'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
