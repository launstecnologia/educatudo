<?php
include __DIR__ . '/_preparar.php';
?>

<style>
@page {
    size: A4 <?= !empty($pdfPaisagem) ? 'landscape' : 'portrait' ?>;
    margin: 8mm;
}
@media print {
    html, body {
        width: 100% !important;
        background: #fff !important;
        overflow: visible !important;
    }
    #sidebar, #sidebar-overlay, header, .grade-no-print, .mobile-bottom-nav, #aulaDrawer, #aulaDrawerBackdrop, #modal-imagem-ia {
        display: none !important;
    }
    main, .flex-1, .grade-print-area, .overflow-x-auto {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        box-shadow: none !important;
        border: 0 !important;
        min-width: 0 !important;
    }
    .grade-toolbar-print { display: block !important; margin-bottom: 8px; }
    table.grade-grid, table.grade-lista {
        width: 100% !important;
        min-width: 0 !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
    }
    table.grade-grid th, table.grade-grid td,
    table.grade-lista th, table.grade-lista td {
        min-width: 0 !important;
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        white-space: normal !important;
    }
    .grade-card { padding: 4px 6px !important; }
    .grade-card p { padding-right: 0 !important; }
    .sticky { position: static !important; }
}
</style>

<form method="get" action="<?= htmlspecialchars($urlGrade) ?>" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6 grade-no-print">
    <input type="hidden" name="visao" value="<?= htmlspecialchars($visao) ?>">
    <?php if ($visao === 'dia' && $diaFiltro > 0): ?>
        <input type="hidden" name="dia" value="<?= (int) $diaFiltro ?>">
    <?php endif; ?>
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        <?php if ($tipos_ensino !== []): ?>
        <div>
            <label for="filtro_tipo_ensino" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Ensino</label>
            <select id="filtro_tipo_ensino" name="tipo_ensino" onchange="this.form.submit()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <option value="">Todos</option>
                <?php foreach ($tipos_ensino as $tipo): ?>
                    <option value="<?= htmlspecialchars((string) $tipo) ?>" <?= (($filtros['tipo_ensino'] ?? '') === $tipo) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $tipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($series !== []): ?>
        <div>
            <label for="filtro_serie" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Série</label>
            <select id="filtro_serie" name="serie" onchange="this.form.submit()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <option value="">Todas</option>
                <?php foreach ($series as $serie): ?>
                    <option value="<?= htmlspecialchars((string) $serie) ?>" <?= (($filtros['serie'] ?? '') === $serie) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $serie) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label for="filtro_turma" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Turma</label>
            <select id="filtro_turma" name="turma_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <option value="">Todas</option>
                <?php foreach ($turmas_filtro as $turma): ?>
                    <option value="<?= (int) $turma['id'] ?>" <?= ((int) ($filtros['turma_id'] ?? 0) === (int) $turma['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $turma['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filtro_periodo" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Período</label>
            <select id="filtro_periodo" name="periodo" onchange="this.form.submit()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <option value="">Todos</option>
                <option value="manha" <?= (($filtros['periodo'] ?? '') === 'manha') ? 'selected' : '' ?>>Manhã</option>
                <option value="tarde" <?= (($filtros['periodo'] ?? '') === 'tarde') ? 'selected' : '' ?>>Tarde</option>
            </select>
        </div>
        <div>
            <label for="filtro_professor" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Professor</label>
            <select id="filtro_professor" name="professor_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <option value="">Todos</option>
                <?php foreach ($professores as $professor): ?>
                    <option value="<?= (int) $professor['id'] ?>" <?= ((int) ($filtros['professor_id'] ?? 0) === (int) $professor['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $professor['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filtro_materia" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Componente</label>
            <select id="filtro_materia" name="materia_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">
                <option value="">Todos</option>
                <?php foreach ($materias as $materia): ?>
                    <option value="<?= (int) $materia['id'] ?>" <?= ((int) ($filtros['materia_id'] ?? 0) === (int) $materia['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $materia['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mt-3 flex justify-end gap-3">
        <button type="submit" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90">
            Filtrar
        </button>
        <?php if ($filtrosAtivos > 0): ?>
            <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['tipo_ensino' => '', 'serie' => '', 'turma_id' => 0, 'periodo' => '', 'professor_id' => 0, 'materia_id' => 0])) ?>"
               class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">Limpar filtros</a>
        <?php endif; ?>
    </div>
</form>

<div class="flex flex-col xl:flex-row gap-6 items-start min-w-0">
    <div class="flex-1 min-w-0 w-full overflow-hidden">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden grade-print-area">
            <div class="hidden grade-toolbar-print">
                <h2 class="text-base font-bold text-gray-900">Grade Horária de Aulas — <?= htmlspecialchars($rotuloToolbar) ?></h2>
                <p class="text-xs text-gray-600"><?= htmlspecialchars($rotuloFiltrosTxt) ?> · <?= (int) $resumo['aulas'] ?> aula(s)</p>
            </div>
            <div class="px-4 sm:px-5 py-3 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <?php if ($visao === 'dia'): ?>
                        <?php if ($diaAnterior > 0): ?>
                            <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'dia', 'dia' => $diaAnterior])) ?>"
                               class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 grade-no-print"
                               aria-label="Dia anterior">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </a>
                        <?php else: ?>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-300 grade-no-print">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($rotuloToolbar) ?></h3>
                        <p class="text-xs text-gray-500"><?= (int) $resumo['aulas'] ?> aula<?= $resumo['aulas'] === 1 ? '' : 's' ?> na visão atual</p>
                    </div>
                    <?php if ($visao === 'dia'): ?>
                        <?php if ($diaProximo > 0): ?>
                            <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'dia', 'dia' => $diaProximo])) ?>"
                               class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 grade-no-print"
                               aria-label="Próximo dia">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </a>
                        <?php else: ?>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-300 grade-no-print">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'dia', 'dia' => $hojeN])) ?>"
                       class="ml-1 inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 grade-no-print">
                        Hoje
                    </a>
                </div>

                <div class="flex items-center gap-2 grade-no-print">
                    <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-xs font-medium">
                        <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'semana', 'dia' => 0])) ?>"
                           class="px-3 py-1.5 <?= $visao === 'semana' ? 'bg-primary text-primary' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">Semana</a>
                        <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'dia', 'dia' => ($diaFiltro > 0 ? $diaFiltro : $hojeN)])) ?>"
                           class="px-3 py-1.5 border-l border-gray-300 <?= $visao === 'dia' ? 'bg-primary text-primary' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">Dia</a>
                        <a href="<?= htmlspecialchars($urlGrade . $montarQuery(['visao' => 'lista', 'dia' => 0])) ?>"
                           class="px-3 py-1.5 border-l border-gray-300 <?= $visao === 'lista' ? 'bg-primary text-primary' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">Lista</a>
                    </div>
                    <a href="<?= htmlspecialchars($urlPdf) ?>" target="_blank" rel="noopener"
                       class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fa-solid fa-file-pdf mr-1.5 text-gray-500"></i>
                        Gerar PDF
                    </a>
                    <button type="button" onclick="window.print()"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fa-solid fa-print mr-1.5 text-gray-500"></i>
                        Imprimir
                    </button>
                </div>
            </div>

            <?php if ($visao === 'lista'): ?>
                <div class="overflow-x-auto min-w-0">
                    <table class="grade-lista w-full table-fixed divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dia</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horário</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Componente</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                                <?php if ($mostrarTurmaNoCard): ?>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                                <?php endif; ?>
                                <?php if ($resumo['salas'] > 0): ?>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sala</th>
                                <?php endif; ?>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider grade-no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($itens === []): ?>
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center text-gray-500">
                                        <i class="fa-regular fa-calendar text-3xl text-gray-300"></i>
                                        <p class="mt-2 text-sm">Nenhuma aula encontrada.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($itens as $item):
                                    $tarde = ($item['periodo'] ?? '') === 'tarde';
                                    $diaNum = (int) ($item['dia_semana'] ?? 0);
                                    ob_start();
                                ?>
                                    <button type="button" onclick="openAulaDrawer(<?= (int) $item['id'] ?>)" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                                    </button>
                                    <form action="<?= URL ?>/admin/grade-horaria/<?= (int) $item['id'] ?>" method="post" onsubmit="return confirm('Remover esta aula da grade?');">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                        </button>
                                    </form>
                                <?php
                                    $row_actions_dropdown_items = ob_get_clean();
                                    $row_actions_dropdown_id = 'grade-list-actions-' . (int) $item['id'];
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-900"><?= htmlspecialchars($diasCurtos[$diaNum] ?? '') ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-700">
                                        <?= htmlspecialchars(substr((string) ($item['horario_de'] ?? ''), 0, 5)) ?>–<?= htmlspecialchars(substr((string) ($item['horario_ate'] ?? ''), 0, 5)) ?>
                                    </td>
                                    <td class="px-3 py-2 text-sm font-medium text-gray-900 break-words"><?= htmlspecialchars((string) ($item['materia_nome'] ?? '')) ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-700 break-words"><?= htmlspecialchars((string) ($item['professor_nome'] ?? '')) ?></td>
                                    <?php if ($mostrarTurmaNoCard): ?>
                                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($item['turma_nome'] ?? '')) ?></td>
                                    <?php endif; ?>
                                    <?php if ($resumo['salas'] > 0): ?>
                                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($item['sala_nome'] ?? '—')) ?></td>
                                    <?php endif; ?>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-1 text-[11px] font-semibold rounded-full <?= $tarde ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800' ?>">
                                            <?= $tarde ? 'Tarde' : 'Manhã' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 grade-no-print">
                                        <?php include __DIR__ . '/../_partials/row_actions_dropdown.php'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto min-w-0">
                    <?php if ($linhasGrade === []): ?>
                        <div class="py-16 text-center text-gray-500">
                            <i class="fa-regular fa-calendar text-3xl text-gray-300"></i>
                            <p class="mt-3 text-sm">Nenhuma aula nesta visão.</p>
                            <?php if ($filtrosAtivos > 0): ?>
                                <a href="<?= htmlspecialchars($urlGrade) ?>" class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800">Limpar filtros</a>
                            <?php else: ?>
                                <button type="button" onclick="openAulaDrawer()" class="mt-4 btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                                    <i class="fa-solid fa-plus mr-2"></i> Nova aula
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="grade-grid w-full table-fixed border-collapse">
                            <colgroup>
                                <col style="width: 4.75rem">
                                <?php foreach ($diasVisiveis as $_diaNome): ?>
                                    <col>
                                <?php endforeach; ?>
                            </colgroup>
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="sticky left-0 z-10 bg-gray-50 px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-r border-gray-200">
                                        Horário
                                    </th>
                                    <?php foreach ($diasVisiveis as $nomeDia): ?>
                                        <th class="px-1.5 py-2 text-center text-xs font-semibold text-gray-800 border-b border-gray-200">
                                            <?= htmlspecialchars($nomeDia) ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($linhasGrade as $linha): ?>
                                    <?php if (($linha['tipo'] ?? '') === 'intervalo'): ?>
                                        <tr>
                                            <td colspan="<?= 1 + $colunasDias ?>" class="bg-slate-50 border-y border-gray-200 py-2">
                                                <div class="flex items-center justify-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                                    <span class="hidden sm:inline h-px w-16 bg-slate-200"></span>
                                                    <i class="fa-solid fa-mug-saucer"></i>
                                                    Intervalo
                                                    <span class="font-normal normal-case tracking-normal text-slate-400">
                                                        <?= htmlspecialchars($linha['de']) ?>–<?= htmlspecialchars($linha['ate']) ?>
                                                    </span>
                                                    <span class="hidden sm:inline h-px w-16 bg-slate-200"></span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <tr class="align-top">
                                            <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left border-b border-r border-gray-100 align-top">
                                                <span class="block text-[11px] font-semibold text-gray-800 leading-tight"><?= htmlspecialchars($linha['de']) ?></span>
                                                <span class="block text-[10px] text-gray-400 leading-tight"><?= htmlspecialchars($linha['ate']) ?></span>
                                            </th>
                                            <?php foreach (array_keys($diasVisiveis) as $diaNum): ?>
                                                <?php $aulasCelula = $linha['aulas'][$diaNum] ?? []; ?>
                                                <td class="px-1 py-1 border-b border-gray-100 align-top">
                                                    <?php if ($aulasCelula === []): ?>
                                                        <div class="rounded-md border border-dashed border-gray-200 bg-slate-50/60 px-1 py-2 text-center text-[10px] text-gray-400">
                                                            Sem aula
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="space-y-1">
                                                            <?php foreach ($aulasCelula as $aula):
                                                                $cor = $coresPorMateria[(int) ($aula['materia_id'] ?? 0)] ?? $paletaCores[0];
                                                                $salaAula = trim((string) ($aula['sala_nome'] ?? ''));
                                                                $variasNaCelula = count($aulasCelula) > 1;
                                                            ?>
                                                                <div class="grade-card relative rounded-md border <?= htmlspecialchars($cor['border']) ?> <?= htmlspecialchars($cor['bg']) ?> <?= htmlspecialchars($cor['text']) ?> px-2 py-1.5 hover:shadow-sm transition-shadow">
                                                                    <button type="button"
                                                                            onclick="openAulaDrawer(<?= (int) $aula['id'] ?>)"
                                                                            class="w-full text-left min-w-0">
                                                                        <p class="text-[11px] font-semibold leading-snug pr-5 break-words"><?= htmlspecialchars((string) ($aula['materia_nome'] ?? '')) ?></p>
                                                                        <p class="mt-0.5 text-[11px] opacity-80"><?= htmlspecialchars($nomeProfessorCurto((string) ($aula['professor_nome'] ?? ''))) ?></p>
                                                                        <?php if ($mostrarTurmaNoCard): ?>
                                                                            <p class="text-[11px] opacity-70"><?= htmlspecialchars((string) ($aula['turma_nome'] ?? '')) ?></p>
                                                                        <?php endif; ?>
                                                                        <?php if ($salaAula !== ''): ?>
                                                                            <p class="text-[11px] opacity-70"><i class="fa-solid fa-location-dot mr-0.5"></i><?= htmlspecialchars($salaAula) ?></p>
                                                                        <?php endif; ?>
                                                                        <?php if ($variasNaCelula && $turmaFiltrada): ?>
                                                                            <p class="mt-1 text-[10px] font-semibold text-amber-800">
                                                                                <i class="fa-solid fa-triangle-exclamation mr-0.5"></i>Mais de uma aula neste horário
                                                                            </p>
                                                                        <?php endif; ?>
                                                                    </button>
                                                                    <div class="absolute top-1.5 right-1 grade-no-print" data-dropdown onclick="event.stopPropagation();">
                                                                        <button type="button" data-dropdown-toggle
                                                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md text-current/60 hover:bg-white/60"
                                                                                aria-label="Ações da aula">
                                                                            <i class="fa-solid fa-ellipsis-vertical text-xs"></i>
                                                                        </button>
                                                                        <div data-dropdown-menu class="hidden fixed z-50 w-44 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 overflow-hidden">
                                                                            <div class="py-1">
                                                                                <button type="button" onclick="openAulaDrawer(<?= (int) $aula['id'] ?>)" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                                                    <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                                                                                </button>
                                                                                <form action="<?= URL ?>/admin/grade-horaria/<?= (int) $aula['id'] ?>" method="post" onsubmit="return confirm('Remover esta aula da grade?');">
                                                                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                                                    <input type="hidden" name="_method" value="DELETE">
                                                                                    <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                                                        <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                                                                    </button>
                                                                                </form>
                                                                            </div>
                                                                        </div>
                                                                    </div>
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
                        <p class="px-4 py-3 text-xs text-gray-500 border-t border-gray-100 grade-no-print">
                            Clique em uma aula para editar detalhes, professor, turma ou horário.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="w-full xl:w-72 shrink-0 space-y-4 grade-no-print">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Resumo da Grade</h3>
            <dl class="space-y-2.5">
                <div class="flex items-center justify-between text-sm">
                    <dt class="text-gray-500">Aulas por semana</dt>
                    <dd class="font-semibold text-gray-900"><?= (int) $resumo['aulas'] ?></dd>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <dt class="text-gray-500">Componentes</dt>
                    <dd class="font-semibold text-gray-900"><?= (int) $resumo['componentes'] ?></dd>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <dt class="text-gray-500">Professores</dt>
                    <dd class="font-semibold text-gray-900"><?= (int) $resumo['professores'] ?></dd>
                </div>
                <?php if ($resumo['salas'] > 0): ?>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-gray-500">Salas utilizadas</dt>
                        <dd class="font-semibold text-gray-900"><?= (int) $resumo['salas'] ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <?php if ($componentesResumo !== []): ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Componentes</h3>
            <ul class="space-y-2">
                <?php foreach ($componentesResumo as $componente): ?>
                    <li class="flex items-center justify-between gap-2 text-sm">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="h-2.5 w-2.5 rounded-full shrink-0 <?= htmlspecialchars($componente['cor']['dot']) ?>"></span>
                            <span class="text-gray-700 truncate"><?= htmlspecialchars($componente['nome']) ?></span>
                        </span>
                        <span class="font-semibold text-gray-900"><?= (int) $componente['qtd'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Ações rápidas</h3>
            <div class="space-y-2">
                <button type="button" onclick="openAulaDrawer()"
                        class="w-full inline-flex items-center px-3 py-2 rounded-lg bg-primary text-primary text-sm font-semibold hover:opacity-90">
                    <i class="fa-solid fa-plus mr-2"></i> Nova aula
                </button>
                <button type="button" onclick="document.getElementById('modal-imagem-ia').classList.remove('hidden')"
                        class="w-full inline-flex items-center px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fa-regular fa-image mr-2 text-gray-500"></i> Importar por imagem
                </button>
                <a href="<?= htmlspecialchars($urlPdf) ?>" target="_blank" rel="noopener"
                   class="w-full inline-flex items-center px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-file-pdf mr-2 text-gray-500"></i> Gerar PDF
                </a>
                <button type="button" onclick="window.print()"
                        class="w-full inline-flex items-center px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-print mr-2 text-gray-500"></i> Imprimir grade
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 mb-1">Dica</p>
            <p class="text-xs text-blue-800 leading-relaxed">Clique em uma aula para editar detalhes, trocar professor ou horário. Use os filtros para ver a grade de uma turma.</p>
        </div>
    </aside>
</div>
