<?php
$totais = is_array($totais ?? null) ? $totais : [
    'total' => 0,
    'com_nota' => 0,
    'sem_nota' => 0,
    'transferidos' => 0,
    'eventos' => 0,
];
$eventos = is_array($eventos ?? null) ? $eventos : [];
$avisos = is_array($avisos ?? null) ? $avisos : [];
$idsSelecionados = is_array($ids_selecionados ?? null) ? $ids_selecionados : [];
$qsIds = implode(',', array_map('intval', $idsSelecionados));
$urlExcel = URL . '/admin/provas/blocos/relatorio-notas-lote/excel?ids=' . rawurlencode($qsIds);

if (!class_exists('AlunoLancamentoNotaHelper', false)) {
}
?>

<style>
    .relatorio-lote-print-header { display: none; }

    @media print {
        @page { size: A4 landscape; margin: 10mm; }

        body.printing-relatorio-lote #sidebar,
        body.printing-relatorio-lote #sidebar-overlay,
        body.printing-relatorio-lote main > header,
        body.printing-relatorio-lote .relatorio-lote-actions,
        body.printing-relatorio-lote .relatorio-lote-no-print {
            display: none !important;
        }

        body.printing-relatorio-lote main {
            margin: 0 !important;
            padding: 0 !important;
            max-width: 100% !important;
        }

        body.printing-relatorio-lote .relatorio-lote-print-header {
            display: block !important;
            margin-bottom: 12px;
        }

        body.printing-relatorio-lote #relatorio-lote-export-root {
            box-shadow: none !important;
            border: none !important;
        }

        body.printing-relatorio-lote #tabelaRelatorioLote {
            font-size: 9px;
            width: 100%;
        }

        body.printing-relatorio-lote #tabelaRelatorioLote th,
        body.printing-relatorio-lote #tabelaRelatorioLote td {
            border: 1px solid #bbb !important;
            padding: 3px 5px !important;
            color: #111 !important;
        }

        body.printing-relatorio-lote #tabelaRelatorioLote thead {
            background: #eee !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body.printing-relatorio-lote tr {
            page-break-inside: avoid;
        }
    }
</style>

<div id="relatorio-lote-page">
    <div class="mb-6 flex flex-wrap justify-between items-start gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Relatório de notas — eventos selecionados</h2>
            <p class="text-gray-600 mt-1">
                <?= (int) ($totais['eventos'] ?? 0) ?> evento(s) ·
                <?= (int) ($totais['total'] ?? 0) ?> registro(s) ·
                matéria, professor, turma e aluno
            </p>
        </div>
        <div class="flex flex-wrap gap-2 relatorio-lote-actions">
            <a href="<?= htmlspecialchars($urlExcel) ?>"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">
                <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                Exportar Excel
            </a>
            <button type="button" onclick="exportarRelatorioLotePdf()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                Exportar PDF
            </button>
            <a href="<?= URL ?>/admin/provas"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
                ← Lista de eventos
            </a>
        </div>
    </div>

    <div class="relatorio-lote-print-header">
        <h1 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Relatório de notas — eventos selecionados</h1>
        <p style="font-size: 11px; margin: 0; color: #444;">
            Gerado em <?= date('d/m/Y H:i') ?> —
            <?= (int) ($totais['eventos'] ?? 0) ?> evento(s),
            <?= (int) ($totais['total'] ?? 0) ?> registro(s)
            (<?= (int) ($totais['com_nota'] ?? 0) ?> com nota,
            <?= (int) ($totais['sem_nota'] ?? 0) ?> sem nota,
            <?= (int) ($totais['transferidos'] ?? 0) ?> transferido(s))
        </p>
    </div>

    <?php foreach ($avisos as $aviso): ?>
    <div class="relatorio-lote-no-print mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <?= htmlspecialchars((string) $aviso) ?>
    </div>
    <?php endforeach; ?>

    <div class="relatorio-lote-no-print mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-600">
        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium">
            <?= (int) ($totais['eventos'] ?? 0) ?> evento(s)
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-green-50 text-green-800 text-xs font-medium">
            <?= (int) ($totais['com_nota'] ?? 0) ?> com nota
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 text-xs font-medium">
            <?= (int) ($totais['sem_nota'] ?? 0) ?> sem nota
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-violet-50 text-violet-800 text-xs font-medium">
            <?= (int) ($totais['transferidos'] ?? 0) ?> transferido(s)
        </span>
    </div>

    <?php if ($eventos !== []): ?>
    <div class="relatorio-lote-no-print bg-white rounded-xl shadow-lg overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Eventos incluídos</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Evento</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo de nota</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Registros</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Com nota</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($eventos as $ev): ?>
                    <tr>
                        <td class="px-3 py-2 text-gray-900"><?= htmlspecialchars((string) ($ev['titulo'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-gray-600 whitespace-nowrap"><?= htmlspecialchars((string) ($ev['data'] ?? '—')) ?></td>
                        <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars((string) (($ev['tipo_nota'] ?? '') !== '' ? $ev['tipo_nota'] : '—')) ?></td>
                        <td class="px-3 py-2 text-gray-600"><?= (int) ($ev['total'] ?? 0) ?></td>
                        <td class="px-3 py-2 text-gray-600"><?= (int) ($ev['com_nota'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div id="relatorio-lote-export-root" class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table id="tabelaRelatorioLote" class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Evento</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Atualizado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($notas_linhas)): ?>
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">Nenhuma nota encontrada nos eventos selecionados.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($notas_linhas as $ln): ?>
                        <?php
                        $temNota = ($ln['nota'] ?? null) !== null && $ln['nota'] !== '';
                        $tr = !empty($ln['transferido']);
                        ?>
                        <tr class="<?= $tr ? 'bg-violet-50/40' : ($temNota ? '' : 'bg-amber-50/30') ?>">
                            <td class="px-3 py-2 text-gray-800 max-w-[12rem] truncate" title="<?= htmlspecialchars((string) ($ln['evento_titulo'] ?? '')) ?>">
                                <?= htmlspecialchars((string) ($ln['evento_titulo'] ?? '')) ?>
                            </td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap"><?= htmlspecialchars((string) (($ln['evento_data'] ?? '') !== '' ? $ln['evento_data'] : '—')) ?></td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap"><?= htmlspecialchars((string) (($ln['evento_tipo_nota'] ?? '') !== '' ? $ln['evento_tipo_nota'] : '—')) ?></td>
                            <td class="px-3 py-2 text-gray-900 font-medium"><?= htmlspecialchars((string) ($ln['materia_nome'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars((string) ($ln['professor_nome'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-gray-700 whitespace-nowrap"><?= htmlspecialchars((string) ($ln['turma_nome'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-gray-900">
                                <?= htmlspecialchars(\App\Helpers\AlunoLancamentoNotaHelper::rotuloNomeComTransferencia(
                                    (string) ($ln['aluno_nome'] ?? ''),
                                    $tr
                                )) ?>
                            </td>
                            <td class="px-3 py-2 font-semibold <?= $temNota ? 'text-gray-900' : 'text-amber-700' ?>">
                                <?= $temNota ? htmlspecialchars(number_format((float) $ln['nota'], 2, ',', '.')) : '—' ?>
                            </td>
                            <td class="px-3 py-2 text-gray-500 whitespace-nowrap text-xs">
                                <?= !empty($ln['updated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $ln['updated_at']))) : '—' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    window.exportarRelatorioLotePdf = function () {
        document.body.classList.add('printing-relatorio-lote');
        window.addEventListener('afterprint', function () {
            document.body.classList.remove('printing-relatorio-lote');
        }, { once: true });
        setTimeout(function () {
            window.print();
        }, 150);
    };

    if (new URLSearchParams(window.location.search).get('print') === '1') {
        setTimeout(function () {
            window.exportarRelatorioLotePdf();
        }, 400);
    }
})();
</script>
