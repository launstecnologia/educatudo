<?php
$aluno = $aluno ?? [];
$notas_lancamento_eventos = $notas_lancamento_eventos ?? [];
$boletins_gerados = $boletins_gerados ?? [];
$boletins_gerados_notas = $boletins_gerados_notas ?? [];
$boletins_gerados_notas_extra = $boletins_gerados_notas_extra ?? [];
$boletins_gerados_boletim = $boletins_gerados_boletim ?? [];
$boletins_gerados_complementar = $boletins_gerados_complementar ?? [];
$paineis_notas = is_array($paineis_notas ?? null) ? $paineis_notas : [];
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
                    $paineis_notas_eventos = $boletins_gerados_notas;
                    $painel_notas_pode_imprimir = false;
                    require __DIR__ . '/../partials/painel_notas_eventos.php';
                    ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($boletins_gerados_notas_extra)): ?>
                <h2 class="text-lg font-semibold text-gray-900 mt-8 mb-1">Notas extra</h2>
                <p class="text-sm text-gray-500 mb-4">Cursos extras (música, robótica…) — não entra no histórico oficial.</p>
                <div class="mt-2">
                    <?php
                    $boletinsGeradosBackup = $boletins_gerados;
                    $boletins_gerados = $boletins_gerados_notas_extra;
                    require __DIR__ . '/../partials/boletins_gerados.php';
                    $boletins_gerados = $boletinsGeradosBackup;
                    ?>
                </div>
            <?php endif; ?>
            <?php if (empty($boletins_gerados_notas) && empty($boletins_gerados_notas_extra) && empty($notas_lancamento_eventos) && empty($paineis_notas) && empty($resumosNotas)): ?>
                <div class="text-center py-10 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-gray-500">Nenhuma nota encontrada.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php
            $quadroFileOficial = dirname(__DIR__, 2) . '/Modulos/vida-escolar/Views/aluno/quadro.php';
            if (is_file($quadroFileOficial) && !empty($quadro_oficial['grid'])) {
                $quadro_oficial = $quadro_oficial;
                require $quadroFileOficial;
            }
            $boletinsGeradosBackup = $boletins_gerados;
            $boletins_gerados = $boletins_gerados_boletim;
            require __DIR__ . '/../partials/boletins_gerados.php';
            $boletins_gerados = $boletinsGeradosBackup;
            ?>
            <?php if (!empty($boletins_gerados_complementar)): ?>
                <h2 class="text-lg font-semibold text-gray-900 mt-8 mb-1">Boletim complementar</h2>
                <p class="text-sm text-gray-500 mb-4">Cursos extras e atividades paralelas — não entra no histórico oficial.</p>
                <?php
                $boletinsGeradosBackup = $boletins_gerados;
                $boletins_gerados = $boletins_gerados_complementar;
                require __DIR__ . '/../partials/boletins_gerados.php';
                $boletins_gerados = $boletinsGeradosBackup;
                ?>
            <?php endif; ?>
            <?php if (empty($boletins_gerados_boletim) && empty($boletins_gerados_complementar) && empty($quadro_oficial['grid'])): ?>
                <div class="text-center py-10 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-gray-500">Nenhum boletim gerado ainda.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
