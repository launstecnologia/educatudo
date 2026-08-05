<?php
$tituloBloco = (string) ($bloco['titulo'] ?? 'Evento');
$profNome = (string) ($professor_nome ?? '');
$matNome = (string) ($materia_nome ?? '');
$exportLinhas = [];
foreach ($linhas ?? [] as $ln) {
    $notaRaw = $ln['nota'] ?? null;
    $notaFmt = ($notaRaw === null || $notaRaw === '')
        ? ''
        : number_format((float) $notaRaw, 2, ',', '.');
    $exportLinhas[] = [
        'turma' => (string) ($ln['turma_nome'] ?? ''),
        'aluno' => (string) ($ln['aluno_nome'] ?? ''),
        'nota' => $notaFmt,
        'observacao' => (string) ($ln['observacao'] ?? ''),
    ];
}
$slugArquivo = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $tituloBloco . '-' . $matNome);
$slugArquivo = trim((string) $slugArquivo, '-');
if ($slugArquivo === '') {
    $slugArquivo = 'notas-alunos';
}
?>

<style>
    .notas-export-print-header { display: none; }

    @media print {
        body.printing-notas-alunos #sidebar,
        body.printing-notas-alunos #sidebar-overlay,
        body.printing-notas-alunos main > header,
        body.printing-notas-alunos .notas-export-actions,
        body.printing-notas-alunos .notas-export-back,
        body.printing-notas-alunos #notas-alunos-page > .mb-8 {
            display: none !important;
        }

        body.printing-notas-alunos main {
            margin: 0 !important;
            padding: 0 !important;
            max-width: 100% !important;
        }

        body.printing-notas-alunos .notas-export-print-header {
            display: block !important;
            margin-bottom: 16px;
        }

        body.printing-notas-alunos #notas-alunos-export-root {
            box-shadow: none !important;
            border: none !important;
        }

        body.printing-notas-alunos #tabelaNotasAlunos {
            font-size: 11px;
        }

        body.printing-notas-alunos #tabelaNotasAlunos th,
        body.printing-notas-alunos #tabelaNotasAlunos td {
            border: 1px solid #ccc !important;
            padding: 6px 8px !important;
        }
    }
</style>

<div id="notas-alunos-page">
    <div class="mb-8 flex flex-wrap justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Notas dos alunos</h2>
            <p class="text-gray-600 mt-1">
                <?= htmlspecialchars($tituloBloco) ?> —
                <span class="font-medium"><?= htmlspecialchars($profNome) ?></span>
                · <span class="font-medium"><?= htmlspecialchars($matNome) ?></span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 notas-export-actions">
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)($bloco['id'] ?? 0) ?>/gerenciar#importacao-notas-internas"
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
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)($bloco['id'] ?? 0) ?>/gerenciar"
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 notas-export-back">← Voltar ao painel</a>
        </div>
    </div>

    <div class="notas-export-print-header">
        <h1 style="font-size: 18px; font-weight: 700; margin: 0 0 6px;">Notas dos alunos</h1>
        <p style="font-size: 12px; margin: 0; color: #444;">
            <?= htmlspecialchars($tituloBloco) ?> —
            <?= htmlspecialchars($profNome) ?> · <?= htmlspecialchars($matNome) ?>
        </p>
        <p style="font-size: 11px; margin: 8px 0 0; color: #666;">
            Gerado em <?= date('d/m/Y H:i') ?>
        </p>
    </div>

    <div id="notas-alunos-export-root" class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tabelaNotasAlunos" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($linhas)): ?>
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">Nenhum registro ainda. O professor pode lançar notas na área de provas.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($linhas as $ln): ?>
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($ln['turma_nome'] ?? '') ?></td>
                            <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($ln['aluno_nome'] ?? '') ?></td>
                            <td class="px-4 py-3 text-sm">
                                <?php if ($ln['nota'] === null || $ln['nota'] === ''): ?>
                                    <span class="text-amber-700">—</span>
                                <?php else: ?>
                                    <span class="font-semibold"><?= htmlspecialchars(number_format((float)$ln['nota'], 2, ',', '.')) ?></span>
                                    <?php if ((float)$ln['nota'] < 6): ?>
                                        <span class="ml-2 text-xs text-red-600">abaixo de 6</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($ln['observacao'] ?? '') ?></td>
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
    var EXPORT_LINHAS = <?= json_encode($exportLinhas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var ARQUIVO_BASE = <?= json_encode($slugArquivo, JSON_UNESCAPED_UNICODE) ?>;

    function csvCell(value) {
        var text = String(value == null ? '' : value).replace(/"/g, '""');
        return '"' + text + '"';
    }

    window.exportarNotasExcel = function () {
        var linhas = [['Turma', 'Aluno', 'Nota', 'Observação']];
        EXPORT_LINHAS.forEach(function (row) {
            linhas.push([row.turma, row.aluno, row.nota, row.observacao]);
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
})();
</script>
