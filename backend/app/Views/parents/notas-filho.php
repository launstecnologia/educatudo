<?php
$filho = $filho ?? [];
$provas_realizadas = $provas_realizadas ?? [];
$provas_matriz_blocos = $provas_matriz_blocos ?? [];
$notas_lancamento_eventos = $notas_lancamento_eventos ?? [];
$boletins_gerados = $boletins_gerados ?? [];
$boletins_gerados_notas = $boletins_gerados_notas ?? [];
$boletins_gerados_notas_extra = $boletins_gerados_notas_extra ?? [];
$boletins_gerados_boletim = $boletins_gerados_boletim ?? [];
$boletins_gerados_complementar = $boletins_gerados_complementar ?? [];
$boletim_observacao = is_array($boletim_observacao ?? null) ? $boletim_observacao : ['conteudo' => '', 'updated_at' => null];
$secaoNotas = $secao_notas ?? 'boletim';
$filtroAnoLetivo = isset($filtro_ano_letivo) ? (int) $filtro_ano_letivo : 0;
$filtroBimestre = isset($filtro_bimestre) ? (int) $filtro_bimestre : 0;
$anosDisponiveis = is_array($anos_disponiveis ?? null) ? $anos_disponiveis : [];
$paineis_notas = is_array($paineis_notas ?? null) ? $paineis_notas : [];

$baseUrlNotas = URL . '/pais/filhos/' . (int) ($filho['id'] ?? 0) . '/notas';
$queryFiltros = [];
if ($filtroAnoLetivo > 0) { $queryFiltros['ano_letivo'] = $filtroAnoLetivo; }
if ($filtroBimestre > 0) { $queryFiltros['bimestre'] = $filtroBimestre; }
$buildSecaoUrl = static function (string $secao) use ($baseUrlNotas, $queryFiltros): string {
    $q = array_merge(['secao' => $secao], $queryFiltros);
    return $baseUrlNotas . '?' . http_build_query($q);
};
?>

<div class="max-w-7xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Notas do aluno</h1>
        <p class="text-gray-600">
            Visualize provas online e <strong>notas de eventos</strong> com lançamento direto (0 a 10) de <?= htmlspecialchars((string) ($filho['nome'] ?? 'seu filho'), ENT_QUOTES, 'UTF-8') ?>.
        </p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <nav class="flex flex-wrap gap-2 mb-6">
            <a href="<?= htmlspecialchars($buildSecaoUrl('boletim'), ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?= $secaoNotas === 'boletim' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Boletim</a>
            <a href="<?= htmlspecialchars($buildSecaoUrl('notas'), ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?= $secaoNotas === 'notas' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Notas</a>
            <a href="<?= htmlspecialchars($buildSecaoUrl('provas'), ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?= $secaoNotas === 'provas' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Provas</a>
        </nav>

        <section class="space-y-5">
                <?php if (in_array($secaoNotas, ['notas', 'provas'], true)): ?>
                    <form method="get" action="<?= htmlspecialchars($baseUrlNotas, ENT_QUOTES, 'UTF-8') ?>" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <input type="hidden" name="secao" value="<?= htmlspecialchars($secaoNotas, ENT_QUOTES, 'UTF-8') ?>">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Ano letivo</label>
                            <select name="ano_letivo" data-periodo-ano class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="">Todos</option>
                                <?php foreach ($anosDisponiveis as $anoOpt): ?>
                                    <option value="<?= (int) $anoOpt ?>" <?= $filtroAnoLetivo === (int) $anoOpt ? 'selected' : '' ?>><?= (int) $anoOpt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1" data-periodo-label>Bimestre</label>
                            <select name="bimestre" data-periodo-letivo-select data-periodo-todos="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <?php
                                if (!class_exists('PeriodoLetivo')) {
                                    require_once __DIR__ . '/../../../Core/PeriodoLetivo.php';
                                }
                                $anoFiltroPais = $filtroAnoLetivo > 0 ? $filtroAnoLetivo : (int) date('Y');
                                echo PeriodoLetivo::optionsHtml($anoFiltroPais, $filtroBimestre, ['todos' => true]);
                                ?>
                            </select>
                        </div>
                        <div class="md:col-span-2 flex items-end gap-2">
                            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">Filtrar</button>
                            <a href="<?= htmlspecialchars($baseUrlNotas . '?secao=' . $secaoNotas, ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">Limpar</a>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($secaoNotas === 'boletim'): ?>
                    <?php
                    $quadroFileOficial = dirname(__DIR__, 2) . '/Modulos/vida-escolar/Views/aluno/quadro.php';
                    if (is_file($quadroFileOficial) && !empty($quadro_oficial['grid'])) {
                        require $quadroFileOficial;
                    }
                    ?>
                    <?php $boletins_gerados = $boletins_gerados_boletim; ?>
                    <?php if (!empty($boletins_gerados)): ?>
                        <?php require __DIR__ . '/../partials/boletins_gerados.php'; ?>
                    <?php elseif (empty($quadro_oficial['grid']) && empty($boletins_gerados_complementar)): ?>
                        <div class="text-center py-10 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="text-gray-500">Nenhum boletim disponível.</p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($boletins_gerados_complementar)): ?>
                        <h2 class="text-lg font-semibold text-gray-900 mt-8 mb-1">Boletim complementar</h2>
                        <p class="text-sm text-gray-500 mb-4">Cursos extras e atividades paralelas — não entra no histórico oficial.</p>
                        <?php $boletins_gerados = $boletins_gerados_complementar; ?>
                        <?php require __DIR__ . '/../partials/boletins_gerados.php'; ?>
                    <?php endif; ?>
                    <?php if (trim((string) ($boletim_observacao['conteudo'] ?? '')) !== ''): ?>
                        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5">
                            <h3 class="text-base font-semibold text-gray-900 mb-2">Observação</h3>
                            <p class="text-sm text-gray-800 whitespace-pre-wrap break-words"><?= htmlspecialchars((string) $boletim_observacao['conteudo'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php endif; ?>
                <?php elseif ($secaoNotas === 'notas'): ?>
                    <?php require __DIR__ . '/../partials/resumo_notas_tabelas.php'; ?>
                    <?php if (!empty($notas_lancamento_eventos)): ?>
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
                                            <td class="px-4 py-2"><?= $nl['nota'] === null || $nl['nota'] === '' ? '<span class="text-amber-700">Pendente</span>' : '<span class="font-semibold text-gray-900">' . htmlspecialchars(number_format((float) $nl['nota'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') . '</span>' ?></td>
                                            <td class="px-4 py-2 text-gray-600"><?= !empty($nl['updated_at']) ? date('d/m/Y H:i', strtotime((string) $nl['updated_at'])) : '—' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($boletins_gerados_notas)): ?>
                        <?php
                        $paineis_notas_eventos = $boletins_gerados_notas;
                        $painel_notas_pode_imprimir = false;
                        require __DIR__ . '/../partials/painel_notas_eventos.php';
                        ?>
                    <?php endif; ?>
                    <?php
                    $notasExtraComLinhas = array_values(array_filter(
                        is_array($boletins_gerados_notas_extra) ? $boletins_gerados_notas_extra : [],
                        static function ($ev) {
                            return is_array($ev) && !empty($ev['linhas']);
                        }
                    ));
                    ?>
                    <?php if ($notasExtraComLinhas !== []): ?>
                        <h2 class="text-lg font-semibold text-gray-900 mt-8 mb-1">Notas extra</h2>
                        <p class="text-sm text-gray-500 mb-4">Cursos extras (música, robótica…) — não entra no histórico oficial.</p>
                        <?php
                        $boletins_gerados_notas_backup = $boletins_gerados_notas;
                        $boletins_gerados_notas = $notasExtraComLinhas;
                        $boletim_notas_cards_prefix = 'bnx';
                        require __DIR__ . '/../partials/boletim_eventos_notas_cards.php';
                        $boletins_gerados_notas = $boletins_gerados_notas_backup;
                        unset($boletim_notas_cards_prefix);
                        ?>
                    <?php endif; ?>
                    <?php if (empty($boletins_gerados_notas) && empty($boletins_gerados_notas_extra) && empty($notas_lancamento_eventos) && empty($paineis_notas) && empty($resumosNotas)): ?>
                        <div class="text-center py-10 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="text-gray-500">Nenhuma nota encontrada para o filtro selecionado.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php require __DIR__ . '/../partials/provas_matriz_blocos.php'; ?>
                <?php endif; ?>
        </section>
    </div>
</div>
