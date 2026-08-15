<?php
$aluno = $aluno ?? [];
$notas_lancamento_eventos = $notas_lancamento_eventos ?? [];
$boletins_gerados = $boletins_gerados ?? [];
$boletins_gerados_notas = $boletins_gerados_notas ?? [];
$boletins_gerados_boletim = $boletins_gerados_boletim ?? [];
$boletim_observacao = is_array($boletim_observacao ?? null) ? $boletim_observacao : ['conteudo' => '', 'updated_at' => null];
$secaoNotas = $secao_notas ?? 'boletim';

$baseUrlNotas = URL . '/notas-boletins';
$buildSecaoUrl = static function (string $secao) use ($baseUrlNotas): string {
    return $baseUrlNotas . '?secao=' . $secao;
};
?>

<div class="max-w-7xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Notas/Boletins</h1>
        <p class="text-gray-600">
            Acompanhe <strong>provas realizadas</strong>, <strong>notas lançadas</strong> e o <strong>boletim</strong> de <?= htmlspecialchars((string) ($aluno['nome'] ?? 'o aluno'), ENT_QUOTES, 'UTF-8') ?>.
        </p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <nav class="flex flex-wrap gap-2 mb-6">
            <a href="<?= htmlspecialchars($buildSecaoUrl('boletim'), ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?= $secaoNotas === 'boletim' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Boletim</a>
            <a href="<?= htmlspecialchars($buildSecaoUrl('notas'), ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?= $secaoNotas === 'notas' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Notas</a>
            <a href="<?= htmlspecialchars($buildSecaoUrl('provas'), ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?= $secaoNotas === 'provas' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Provas</a>
        </nav>

        <section class="space-y-5">
                <?php if ($secaoNotas === 'boletim'): ?>
                    <?php $boletins_gerados = $boletins_gerados_boletim; ?>
                    <?php if (!empty($boletins_gerados)): ?>
                        <?php require __DIR__ . '/../partials/boletins_gerados.php'; ?>
                    <?php else: ?>
                        <div class="text-center py-10 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="text-gray-500">Nenhum boletim gerado ainda.</p>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($boletim_observacao['conteudo'] ?? '')) !== ''): ?>
                        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5">
                            <h3 class="text-base font-semibold text-gray-900 mb-2">Observação</h3>
                            <p class="text-sm text-gray-800 whitespace-pre-wrap break-words"><?= htmlspecialchars((string) $boletim_observacao['conteudo'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php endif; ?>
                <?php elseif ($secaoNotas === 'notas'): ?>
                    <?php
                    $quadro = $quadro_notas_semanais ?? [];
                    $quadroFile = dirname(__DIR__, 2) . '/Modulos/notas-semanais/Views/aluno/quadro.php';
                    $usarQuadroSemanal = !empty($quadro['modulo_ativo']) && !empty($quadro['tem_dados']) && is_file($quadroFile);
                    ?>
                    <?php if ($usarQuadroSemanal): ?>
                        <?php require $quadroFile; ?>
                    <?php endif; ?>
                    <?php if (!$usarQuadroSemanal && !empty($notas_lancamento_eventos)): ?>
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
                        <?php require __DIR__ . '/../partials/boletim_eventos_notas_cards.php'; ?>
                    <?php elseif (!$usarQuadroSemanal && empty($notas_lancamento_eventos)): ?>
                        <div class="text-center py-10 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="text-gray-500">Nenhuma nota encontrada.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php require __DIR__ . '/../partials/provas_matriz_blocos.php'; ?>
                <?php endif; ?>
        </section>
    </div>
</div>
