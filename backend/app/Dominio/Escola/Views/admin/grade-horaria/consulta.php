<?php
$perfilConsulta = (string) ($perfil_consulta ?? 'aluno');
$tituloPagina = (string) ($titulo_pagina ?? 'Grade horária');
$descricaoPagina = (string) ($descricao_pagina ?? '');
$gradeProfessor = $perfilConsulta === 'professor';
include __DIR__ . '/_preparar.php';
?>

<style>
@page {
    size: A4 <?= !empty($pdfPaisagem) ? 'landscape' : 'portrait' ?>;
    margin: 8mm;
}
@media print {
    html, body { background: #fff !important; }
    #sidebar, #sidebar-overlay, header, .grade-no-print, .mobile-bottom-nav { display: none !important; }
    main, .flex-1, .grade-print-area { margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: 0 !important; }
    .grade-toolbar-print { display: block !important; margin-bottom: 8px; }
    .sticky { position: static !important; }
}
</style>

<div class="mb-6 grade-no-print">
    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($tituloPagina) ?></h1>
    <?php if ($descricaoPagina !== ''): ?>
        <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($descricaoPagina) ?></p>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden grade-print-area min-w-0">
    <div class="hidden grade-toolbar-print">
        <h2 class="text-base font-bold text-gray-900"><?= htmlspecialchars($tituloPagina) ?></h2>
        <p class="text-xs text-gray-600"><?= htmlspecialchars($rotuloToolbar) ?> · <?= (int) $resumo['aulas'] ?> aula(s)</p>
    </div>
    <div class="px-4 sm:px-5 py-3 border-b border-gray-200 flex flex-wrap items-center gap-x-3 gap-y-2 min-w-0">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($rotuloToolbar) ?></h2>
            <p class="text-xs text-gray-500"><?= (int) $resumo['aulas'] ?> aula<?= $resumo['aulas'] === 1 ? '' : 's' ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2 grade-no-print max-w-full ml-auto">
            <?php if ($visao === 'dia'): ?>
                <?php if ($diaAnterior > 0): ?>
                    <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'dia', 'dia' => $diaAnterior])) ?>"
                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50"
                       aria-label="Dia anterior">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </a>
                <?php endif; ?>
                <?php if ($diaProximo > 0): ?>
                    <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'dia', 'dia' => $diaProximo])) ?>"
                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50"
                       aria-label="Próximo dia">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'dia', 'dia' => $hojeN])) ?>"
               class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 whitespace-nowrap">
                Hoje
            </a>
            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-xs font-medium shrink-0">
                <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'semana', 'dia' => 0])) ?>"
                   class="px-3 py-1.5 <?= $visao === 'semana' ? 'bg-primary text-primary' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">Semana</a>
                <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'dia', 'dia' => ($diaFiltro > 0 ? $diaFiltro : $hojeN)])) ?>"
                   class="px-3 py-1.5 border-l border-gray-300 <?= $visao === 'dia' ? 'bg-primary text-primary' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">Dia</a>
                <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'lista', 'dia' => 0])) ?>"
                   class="px-3 py-1.5 border-l border-gray-300 <?= $visao === 'lista' ? 'bg-primary text-primary' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">Lista</a>
            </div>
            <a href="<?= htmlspecialchars($urlPdf) ?>" target="_blank" rel="noopener"
               class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 whitespace-nowrap">
                <i class="fa-solid fa-file-pdf mr-1.5 text-gray-500"></i>
                PDF
            </a>
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 whitespace-nowrap">
                <i class="fa-solid fa-print mr-1.5 text-gray-500"></i>
                Imprimir
            </button>
        </div>
    </div>

    <?php if ($visao === 'lista'): ?>
        <div class="overflow-x-auto min-w-0">
            <table class="w-full min-w-[40rem] divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dia</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Horário</th>
                        <?php if ($gradeProfessor): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sala</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Componente</th>
                        <?php if (!$gradeProfessor): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sala</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($itens === []): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500">Nenhuma aula nesta grade.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($itens as $item): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($diasCurtos[(int) ($item['dia_semana'] ?? 0)] ?? '') ?></td>
                                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                    <?= htmlspecialchars(substr((string) ($item['horario_de'] ?? ''), 0, 5)) ?>–<?= htmlspecialchars(substr((string) ($item['horario_ate'] ?? ''), 0, 5)) ?>
                                </td>
                                <?php if ($gradeProfessor): ?>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($item['turma_nome'] ?? '')) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) (($item['sala_nome'] ?? '') !== '' ? $item['sala_nome'] : '—')) ?></td>
                                <?php endif; ?>
                                <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars((string) ($item['materia_nome'] ?? '')) ?></td>
                                <?php if (!$gradeProfessor): ?>
                                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($item['professor_nome'] ?? '')) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) (($item['sala_nome'] ?? '') !== '' ? $item['sala_nome'] : '—')) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($linhasGrade === []): ?>
        <div class="py-16 text-center text-gray-500">
            <p class="text-sm">Nenhuma aula nesta grade.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto min-w-0">
            <table class="w-full min-w-[44rem] table-fixed border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-r border-gray-200 w-20">Horário</th>
                        <?php foreach ($diasVisiveis as $nomeDia): ?>
                            <th class="px-1.5 py-2 text-center text-xs font-semibold text-gray-800 border-b border-gray-200"><?= htmlspecialchars($nomeDia) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($linhasGrade as $linha): ?>
                        <?php if (($linha['tipo'] ?? '') === 'intervalo'): ?>
                            <tr>
                                <td colspan="<?= 1 + $colunasDias ?>" class="bg-slate-50 border-y border-gray-200 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                    Intervalo <?= htmlspecialchars($linha['de']) ?>–<?= htmlspecialchars($linha['ate']) ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr class="align-top">
                                <th class="px-2 py-1.5 text-left border-b border-r border-gray-100 bg-white">
                                    <span class="block text-[11px] font-semibold text-gray-800 leading-tight"><?= htmlspecialchars($linha['de']) ?></span>
                                    <span class="block text-[10px] text-gray-400 leading-tight"><?= htmlspecialchars($linha['ate']) ?></span>
                                </th>
                                <?php foreach (array_keys($diasVisiveis) as $diaNum): ?>
                                    <?php $aulasCelula = $linha['aulas'][$diaNum] ?? []; ?>
                                    <td class="px-1 py-1 border-b border-gray-100 align-top">
                                        <?php if ($aulasCelula === []): ?>
                                            <div class="rounded-md border border-dashed border-gray-200 bg-slate-50/60 px-1 py-2 text-center text-[10px] text-gray-400">
                                                <?= $gradeProfessor ? 'Livre' : 'Sem aula' ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="space-y-1">
                                                <?php foreach ($aulasCelula as $aula):
                                                    $cor = $coresPorMateria[(int) ($aula['materia_id'] ?? 0)] ?? $paletaCores[0];
                                                    $salaAula = trim((string) ($aula['sala_nome'] ?? ''));
                                                ?>
                                                    <div class="rounded-md border <?= htmlspecialchars($cor['border']) ?> <?= htmlspecialchars($cor['bg']) ?> <?= htmlspecialchars($cor['text']) ?> px-2 py-1.5">
                                                        <?php if ($gradeProfessor): ?>
                                                            <p class="text-[11px] font-semibold leading-snug"><?= htmlspecialchars((string) ($aula['turma_nome'] ?? '')) ?></p>
                                                            <?php if ($salaAula !== ''): ?>
                                                                <p class="text-[11px] opacity-80"><i class="fa-solid fa-location-dot mr-0.5"></i><?= htmlspecialchars($salaAula) ?></p>
                                                            <?php endif; ?>
                                                            <p class="text-[11px] opacity-70"><?= htmlspecialchars((string) ($aula['materia_nome'] ?? '')) ?></p>
                                                        <?php else: ?>
                                                            <p class="text-[11px] font-semibold leading-snug"><?= htmlspecialchars((string) ($aula['materia_nome'] ?? '')) ?></p>
                                                            <p class="mt-0.5 text-[11px] opacity-80"><?= htmlspecialchars($nomeProfessorCurto((string) ($aula['professor_nome'] ?? ''))) ?></p>
                                                            <?php if ($salaAula !== ''): ?>
                                                                <p class="text-[11px] opacity-70"><i class="fa-solid fa-location-dot mr-0.5"></i><?= htmlspecialchars($salaAula) ?></p>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
