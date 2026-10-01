<?php
$tituloBloco = (string) ($bloco['titulo'] ?? 'Evento');
$profNome = (string) ($professor_nome ?? '');
$matNome = (string) ($materia_nome ?? '');
$professorId = (int) ($professor_id ?? 0);
$materiaId = (int) ($materia_id ?? 0);
$blocoId = (int) ($bloco['id'] ?? 0);
$turmasFiltro = $turmas_filtro ?? [];
$seriesFiltro = $series_filtro ?? [];
$turmaIdFiltro = (int) ($turma_id_filtro ?? 0);
$serieIdFiltro = (int) ($serie_id_filtro ?? 0);
$ordenarFiltro = (string) ($ordenar_filtro ?? 'nome');
$ordensOk = ['nome', 'nome_desc', 'chamada', 'chamada_desc', 'sexo'];
if (!in_array($ordenarFiltro, $ordensOk, true)) {
    $ordenarFiltro = 'nome';
}
$pagina = max(1, (int) ($pagina ?? 1));
$porPagina = (int) ($por_pagina ?? 40);
if (!in_array($porPagina, [20, 40, 60, 100], true)) {
    $porPagina = 40;
}
$totalLinhas = (int) ($total_linhas ?? count($linhas ?? []));
$totalPaginas = max(1, (int) ($total_paginas ?? 1));
$linhasExportSrc = $linhas_export ?? ($linhas ?? []);
$filtrosRestritivos = $turmaIdFiltro > 0 || $serieIdFiltro > 0;
$actionFiltro = URL . '/admin/provas/blocos/' . $blocoId . '/notas-lancadas';
$urlLimpar = $actionFiltro . '?' . http_build_query([
    'professor_id' => $professorId,
    'materia_id' => $materiaId,
]);

$rotulosOrdenar = [
    'nome' => 'Nome (A–Z)',
    'nome_desc' => 'Nome (Z–A)',
    'chamada' => 'Nº da chamada',
    'chamada_desc' => 'Nº da chamada (decrescente)',
    'sexo' => 'Sexo',
];
$filtrosAtivos = [];
if ($serieIdFiltro > 0) {
    $filtrosAtivos[] = 'Série: ' . ($seriesFiltro[$serieIdFiltro] ?? ('#' . $serieIdFiltro));
}
if ($turmaIdFiltro > 0) {
    $filtrosAtivos[] = 'Turma: ' . ($turmasFiltro[$turmaIdFiltro] ?? ('#' . $turmaIdFiltro));
}
$filtrosAtivos[] = 'Ordem: ' . ($rotulosOrdenar[$ordenarFiltro] ?? $ordenarFiltro);

$qsBase = array_filter([
    'professor_id' => $professorId,
    'materia_id' => $materiaId,
    'turma_id' => $turmaIdFiltro > 0 ? $turmaIdFiltro : null,
    'serie_id' => $serieIdFiltro > 0 ? $serieIdFiltro : null,
    'ordenar' => $ordenarFiltro !== 'nome' ? $ordenarFiltro : null,
    'por_pagina' => $porPagina !== 40 ? $porPagina : null,
], static fn($v) => $v !== null && $v !== '');
$urlPagina = static function (int $p) use ($actionFiltro, $qsBase): string {
    $q = $qsBase;
    if ($p > 1) {
        $q['pagina'] = $p;
    }
    $qs = http_build_query($q);
    return $qs !== '' ? $actionFiltro . '?' . $qs : $actionFiltro;
};

$formatarNota = static function ($notaRaw): string {
    return ($notaRaw === null || $notaRaw === '')
        ? ''
        : number_format((float) $notaRaw, 2, ',', '.');
};

$exportLinhas = [];
foreach ($linhasExportSrc as $ln) {
    $nChamada = (int) ($ln['numero_chamada'] ?? 0);
    $exportLinhas[] = [
        'turma' => (string) ($ln['turma_nome'] ?? ''),
        'numero' => $nChamada > 0 ? (string) $nChamada : '',
        'aluno' => (string) ($ln['aluno_nome'] ?? ''),
        'nota' => $formatarNota($ln['nota'] ?? null),
        'observacao' => (string) ($ln['observacao'] ?? ''),
    ];
}
$slugArquivo = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $tituloBloco . '-' . $matNome);
$slugArquivo = trim((string) $slugArquivo, '-');
if ($slugArquivo === '') {
    $slugArquivo = 'notas-alunos';
}

$renderLinha = static function (array $ln): void {
    $transferido = !empty($ln['transferido']);
    $nChamada = (int) ($ln['numero_chamada'] ?? 0);
    ?>
    <tr class="<?= $transferido ? 'bg-gray-100 text-gray-500' : '' ?>">
        <td class="px-4 py-3 text-sm <?= $transferido ? 'text-gray-500' : 'text-gray-900' ?>"><?= htmlspecialchars((string) ($ln['turma_nome'] ?? '')) ?></td>
        <td class="px-4 py-3 text-sm text-center font-semibold <?= $transferido ? 'text-gray-500' : 'text-gray-700' ?>"><?= $nChamada > 0 ? $nChamada : '—' ?></td>
        <td class="px-4 py-3 text-sm <?= $transferido ? 'text-gray-500' : 'text-gray-900' ?>">
            <?php if ($transferido): ?>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide bg-gray-300 text-gray-700">TR</span>
                    <span><?= htmlspecialchars((string) ($ln['aluno_nome'] ?? '')) ?></span>
                </span>
            <?php else: ?>
                <?= htmlspecialchars((string) ($ln['aluno_nome'] ?? '')) ?>
            <?php endif; ?>
        </td>
        <td class="px-4 py-3 text-sm">
            <?php if (($ln['nota'] ?? null) === null || ($ln['nota'] ?? '') === ''): ?>
                <span class="text-amber-700">—</span>
            <?php else: ?>
                <span class="font-semibold"><?= htmlspecialchars(number_format((float) $ln['nota'], 2, ',', '.')) ?></span>
                <?php if ((float) $ln['nota'] < 6): ?>
                    <span class="ml-2 text-xs text-red-600">abaixo de 6</span>
                <?php endif; ?>
            <?php endif; ?>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars((string) ($ln['observacao'] ?? '')) ?></td>
    </tr>
    <?php
};
?>

<style>
    .notas-export-print-header,
    .notas-export-print-only { display: none; }

    @media print {
        body.printing-notas-alunos #sidebar,
        body.printing-notas-alunos #sidebar-overlay,
        body.printing-notas-alunos main > header,
        body.printing-notas-alunos .notas-export-actions,
        body.printing-notas-alunos .notas-export-back,
        body.printing-notas-alunos .notas-filtros-ui,
        body.printing-notas-alunos .notas-paginacao,
        body.printing-notas-alunos #filterDrawer,
        body.printing-notas-alunos #filterDrawerBackdrop,
        body.printing-notas-alunos #notas-alunos-page > .mb-8,
        body.printing-notas-alunos #tabelaNotasAlunos {
            display: none !important;
        }

        body.printing-notas-alunos main {
            margin: 0 !important;
            padding: 0 !important;
            max-width: 100% !important;
        }

        body.printing-notas-alunos .notas-export-print-header,
        body.printing-notas-alunos .notas-export-print-only {
            display: block !important;
            margin-bottom: 16px;
        }

        body.printing-notas-alunos #notas-alunos-export-root {
            box-shadow: none !important;
            border: none !important;
        }

        body.printing-notas-alunos #tabelaNotasAlunosPrint {
            font-size: 11px;
        }

        body.printing-notas-alunos #tabelaNotasAlunosPrint th,
        body.printing-notas-alunos #tabelaNotasAlunosPrint td {
            border: 1px solid #ccc !important;
            padding: 6px 8px !important;
        }
    }
</style>

<div id="notas-alunos-page">
    <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Notas dos alunos</h2>
            <p class="text-gray-600 mt-1">
                <?= htmlspecialchars($tituloBloco) ?> —
                <span class="font-medium"><?= htmlspecialchars($profNome) ?></span>
                · <span class="font-medium"><?= htmlspecialchars($matNome) ?></span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 notas-export-actions">
            <button type="button" onclick="openFilterDrawer()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
                Filtros
            </button>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar#importacao-notas-internas"
               class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 text-sm inline-flex items-center gap-2">
                <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                Importar notas internas
            </a>
            <button type="button" onclick="exportarNotasExcel()"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Exportar Excel
            </button>
            <button type="button" onclick="exportarNotasPdf()"
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                Exportar PDF
            </button>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 notas-export-back">← Voltar</a>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-600 notas-filtros-ui">
        <?php foreach ($filtrosAtivos as $chip): ?>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium"><?= htmlspecialchars($chip) ?></span>
        <?php endforeach; ?>
        <span class="text-xs text-gray-500"><?= (int) $totalLinhas ?> aluno(s) · página <?= (int) $pagina ?>/<?= (int) $totalPaginas ?></span>
    </div>

    <div class="notas-export-print-header">
        <h1 style="font-size: 18px; font-weight: 700; margin: 0 0 6px;">Notas dos alunos</h1>
        <p style="font-size: 12px; margin: 0; color: #444;">
            <?= htmlspecialchars($tituloBloco) ?> —
            <?= htmlspecialchars($profNome) ?> · <?= htmlspecialchars($matNome) ?>
        </p>
        <p style="font-size: 11px; margin: 8px 0 0; color: #666;">
            Gerado em <?= date('d/m/Y H:i') ?>
            <?php if ($filtrosRestritivos): ?>
                · <?= htmlspecialchars(implode(' · ', $filtrosAtivos)) ?>
            <?php endif; ?>
        </p>
    </div>

    <div id="notas-alunos-export-root" class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tabelaNotasAlunos" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-16">Nº</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($linhas)): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                            <?= $filtrosRestritivos
                                ? 'Nenhum aluno encontrado para o filtro selecionado.'
                                : 'Nenhum registro ainda. O professor pode lançar notas na área de provas.' ?>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($linhas as $ln) {
                            $renderLinha($ln);
                        } ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm notas-paginacao">
        <p class="text-gray-600">
            Mostrando <?= count($linhas ?? []) ?> de <?= (int) $totalLinhas ?>
        </p>
        <div class="flex flex-wrap gap-1">
            <?php if ($pagina > 1): ?>
                <a href="<?= htmlspecialchars($urlPagina($pagina - 1)) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Anterior</a>
            <?php endif; ?>
            <?php
            $ini = max(1, $pagina - 2);
            $fim = min($totalPaginas, $pagina + 2);
            for ($p = $ini; $p <= $fim; $p++):
            ?>
                <a href="<?= htmlspecialchars($urlPagina($p)) ?>"
                   class="px-3 py-1.5 rounded-lg border <?= $p === $pagina ? 'border-indigo-500 bg-indigo-50 text-indigo-800 font-semibold' : 'border-gray-300 bg-white hover:bg-gray-50' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
            <?php if ($pagina < $totalPaginas): ?>
                <a href="<?= htmlspecialchars($urlPagina($pagina + 1)) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="notas-export-print-only">
        <table id="tabelaNotasAlunosPrint" class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Nº</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($linhasExportSrc as $ln) {
                    $renderLinha($ln);
                } ?>
            </tbody>
        </table>
    </div>
</div>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white shadow-2xl transform translate-x-full transition-transform duration-200 ease-out flex flex-col"
       aria-hidden="true">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Filtros e ordenação</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
        </button>
    </div>
    <form method="get" action="<?= htmlspecialchars($actionFiltro) ?>" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
        <input type="hidden" name="professor_id" value="<?= $professorId ?>">
        <input type="hidden" name="materia_id" value="<?= $materiaId ?>">
        <div>
            <label for="serie_id" class="block text-sm font-medium text-gray-700 mb-1">Série</label>
            <select id="serie_id" name="serie_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="0" <?= $serieIdFiltro === 0 ? 'selected' : '' ?>>Todas as séries</option>
                <?php foreach ($seriesFiltro as $sid => $snome): ?>
                    <option value="<?= (int) $sid ?>" <?= $serieIdFiltro === (int) $sid ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $snome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
            <select id="turma_id" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="0" <?= $turmaIdFiltro === 0 ? 'selected' : '' ?>>Todas as turmas</option>
                <?php foreach ($turmasFiltro as $tid => $tnome): ?>
                    <option value="<?= (int) $tid ?>" <?= $turmaIdFiltro === (int) $tid ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $tnome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="ordenar" class="block text-sm font-medium text-gray-700 mb-1">Ordenar por</label>
            <select id="ordenar" name="ordenar" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <?php foreach ($rotulosOrdenar as $val => $lab): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $ordenarFiltro === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="por_pagina" class="block text-sm font-medium text-gray-700 mb-1">Por página</label>
            <select id="por_pagina" name="por_pagina" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <?php foreach ([20, 40, 60, 100] as $n): ?>
                    <option value="<?= $n ?>" <?= $porPagina === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="pt-2 flex gap-3">
            <a href="<?= htmlspecialchars($urlLimpar) ?>"
               class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-center text-gray-700 bg-white hover:bg-gray-50">
                Limpar
            </a>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 btn-primary-custom rounded-lg text-sm font-semibold hover:opacity-90">
                Aplicar
            </button>
        </div>
    </form>
</aside>

<script>
(function () {
    var EXPORT_LINHAS = <?= json_encode($exportLinhas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var ARQUIVO_BASE = <?= json_encode($slugArquivo, JSON_UNESCAPED_UNICODE) ?>;

    function csvCell(value) {
        var text = String(value == null ? '' : value).replace(/"/g, '""');
        return '"' + text + '"';
    }

    window.exportarNotasExcel = function () {
        var linhas = [['Turma', 'Nº', 'Aluno', 'Nota', 'Observação']];
        EXPORT_LINHAS.forEach(function (row) {
            linhas.push([row.turma, row.numero, row.aluno, row.nota, row.observacao]);
        });
        var csv = '\uFEFF' + linhas.map(function (row) {
            return row.map(csvCell).join(';');
        }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = ARQUIVO_BASE + '.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    };

    window.exportarNotasPdf = function () {
        document.body.classList.add('printing-notas-alunos');
        window.addEventListener('afterprint', function () {
            document.body.classList.remove('printing-notas-alunos');
        }, { once: true });
        setTimeout(function () {
            window.print();
        }, 150);
    };

    window.openFilterDrawer = function () {
        document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
        var d = document.getElementById('filterDrawer');
        d.classList.remove('translate-x-full');
        d.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };
    window.closeFilterDrawer = function () {
        document.getElementById('filterDrawerBackdrop').classList.add('hidden');
        var d = document.getElementById('filterDrawer');
        d.classList.add('translate-x-full');
        d.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeFilterDrawer();
        }
    });
})();
</script>
