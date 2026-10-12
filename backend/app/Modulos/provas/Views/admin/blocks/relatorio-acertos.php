<?php
/**
 * Matriz de Desempenho do Bloco – tabela cruzada alunos x questões por matéria
 */
$materias = $materias ?? [];
$alunosData = $alunos ?? [];
$totalQuestoes = $totalQuestoes ?? 0;
$stats = $estatsPorQuestao ?? [];
$questoesAlt = $questoesAlternativas ?? [];
$totalAlunosAtivos = $totalAlunosAtivos ?? 0;

$totalQuestoesRespondidas = 0;
foreach ($stats as $s) {
    $totalQuestoesRespondidas = max($totalQuestoesRespondidas, $s['total']);
}
?>

<style>
    .relatorio-table-wrap { overflow-x: auto; max-width: 100%; }
    .relatorio-table { border-collapse: collapse; font-size: 11px; min-width: 100%; }
    .relatorio-table th, .relatorio-table td { border: 1px solid #d1d5db; padding: 2px 4px; text-align: center; white-space: nowrap; }
    .relatorio-table thead th { background: #f3f4f6; position: sticky; top: 0; z-index: 2; }
    .relatorio-table .col-num { position: sticky; left: 0; z-index: 3; background: #fff; min-width: 30px; }
    .relatorio-table .col-nome { position: sticky; left: 30px; z-index: 3; background: #fff; text-align: left; min-width: 200px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; }
    .relatorio-table thead .col-num, .relatorio-table thead .col-nome { z-index: 4; background: #f3f4f6; }
    .relatorio-table .materia-header { background: #e0e7ff; font-weight: 700; color: #3730a3; }
    .relatorio-table .resumo-row td { background: #fefce8; font-weight: 600; font-size: 10px; }
    .celula-1 { background: #dcfce7; color: #166534; font-weight: 600; cursor: pointer; }
    .celula-0 { background: #fee2e2; color: #991b1b; font-weight: 600; cursor: pointer; }
    .celula-dash { background: #f3f4f6; color: #9ca3af; }
    .celula-q { cursor: pointer; }
    .celula-q:hover { background: #c7d2fe; }
    .col-acertos { background: #f0fdf4; font-weight: 700; }
    .col-nota { background: #eff6ff; font-weight: 700; }
    .col-obs { background: #fefce8; font-weight: 600; font-size: 10px; }
    .matriz-print-header { display: none; }

    @page {
        size: A4 landscape;
        margin: 5mm;
    }

    @media print {
        html, body {
            width: 100% !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            overflow: visible !important;
        }

        body.printing-matriz * {
            visibility: hidden !important;
        }

        body.printing-matriz #matriz-desempenho-root,
        body.printing-matriz #matriz-desempenho-root * {
            visibility: visible !important;
        }

        body.printing-matriz #matriz-desempenho-root {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 0;
            margin: 0;
        }

        body.printing-matriz #sidebar,
        body.printing-matriz #sidebar-overlay,
        body.printing-matriz #modalAlunosOnline,
        body.printing-matriz #modal-questao,
        body.printing-matriz #consentModal,
        body.printing-matriz header,
        body.printing-matriz footer,
        body.printing-matriz .no-print,
        #modalAlunosOnline,
        #modal-questao,
        #consentModal,
        #sidebar,
        #sidebar-overlay,
        .no-print {
            display: none !important;
            visibility: hidden !important;
        }

        .matriz-print-header {
            display: block !important;
            margin-bottom: 6px;
        }
        .matriz-print-header h2 {
            font-size: 13px;
            margin: 0 0 2px;
            color: #111;
        }
        .matriz-print-header p {
            font-size: 9px;
            color: #444;
            margin: 0;
        }

        .relatorio-table-wrap {
            overflow: visible !important;
            box-shadow: none !important;
            border: none !important;
            border-radius: 0 !important;
            background: #fff !important;
        }

        .relatorio-table {
            width: 100%;
            font-size: 6.5px;
            table-layout: fixed;
            page-break-inside: auto;
        }

        .relatorio-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .relatorio-table th,
        .relatorio-table td {
            padding: 1px 2px;
            border: 1px solid #999 !important;
        }

        .relatorio-table .col-num { width: 18px; min-width: 18px; max-width: 18px; }
        .relatorio-table .col-nome {
            width: 110px;
            min-width: 110px;
            max-width: 110px;
            text-align: left;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .relatorio-table .col-num,
        .relatorio-table .col-nome,
        .relatorio-table thead .col-num,
        .relatorio-table thead .col-nome {
            position: static;
        }

        .celula-1,
        .celula-0,
        .celula-dash,
        .materia-header,
        .resumo-row td,
        .col-acertos,
        .col-nota,
        .col-obs,
        .relatorio-table thead th {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<div id="matriz-desempenho-root">
<!-- Header visível só na impressão -->
<div class="matriz-print-header">
    <h2>Matriz de Desempenho: <?= htmlspecialchars($bloco['titulo'] ?? '') ?></h2>
    <p><?= date('d/m/Y', strtotime($bloco['data_prova'] ?? 'now')) ?>
       &nbsp;|&nbsp; <?= date('H:i', strtotime($bloco['hora_inicio'] ?? '00:00')) ?> - <?= date('H:i', strtotime($bloco['hora_fim'] ?? '00:00')) ?>
       &nbsp;|&nbsp; <?= $totalQuestoes ?> questões &nbsp;|&nbsp; <?= count($alunosData) ?> alunos</p>
</div>

<!-- Header na tela -->
<div class="mb-4 no-print">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="text-xl font-bold text-gray-900">
                Matriz de Desempenho: <?= htmlspecialchars($bloco['titulo'] ?? '') ?>
            </h2>
            <p class="text-sm text-gray-600">
                <?= date('d/m/Y', strtotime($bloco['data_prova'] ?? 'now')) ?>
                &nbsp;|&nbsp; <?= date('H:i', strtotime($bloco['hora_inicio'] ?? '00:00')) ?> - <?= date('H:i', strtotime($bloco['hora_fim'] ?? '00:00')) ?>
                &nbsp;|&nbsp; <?= $totalQuestoes ?> questões &nbsp;|&nbsp; <?= count($alunosData) ?> alunos
            </p>
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="imprimirMatriz()" class="bg-gray-600 text-white px-3 py-2 rounded-lg hover:bg-gray-700 text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Imprimir
            </button>
            <button onclick="exportarExcel()" class="bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Exportar Excel
            </button>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/resultados-novos"
               class="bg-indigo-600 text-white px-3 py-2 rounded-lg hover:bg-indigo-700 text-sm">
                Voltar aos Resultados
            </a>
        </div>
    </div>
</div>

<?php if (empty($materias)): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
        <p class="text-yellow-800">Nenhuma prova encontrada neste bloco.</p>
    </div>
<?php else: ?>

<div class="relatorio-table-wrap bg-white rounded-xl shadow-lg border border-gray-200">
    <table class="relatorio-table" id="tabelaRelatorio">
        <thead>
            <!-- Linha 1: matérias (colspan) -->
            <tr>
                <th class="col-num" rowspan="3">#</th>
                <th class="col-nome" rowspan="3">Aluno</th>
                <?php foreach ($materias as $mat): ?>
                    <th class="materia-header" colspan="<?= count($mat['questoes']) ?>">
                        <?= htmlspecialchars($mat['materia_nome']) ?>
                    </th>
                <?php endforeach; ?>
                <th rowspan="3" class="col-acertos" style="min-width:50px">ACERTOS</th>
                <th rowspan="3" class="col-nota" style="min-width:50px">NOTA</th>
                <th rowspan="3" class="col-obs" style="min-width:40px">OBS</th>
            </tr>
            <!-- Linha 2: número da questão (clicável) -->
            <tr>
                <?php foreach ($materias as $mat): ?>
                    <?php foreach ($mat['questoes'] as $qi => $q): ?>
                        <th class="celula-q" data-questao-id="<?= (int)$q['id'] ?>" title="Clique para ver a questão">
                            <?= $qi + 1 ?>
                            <?php if (!empty($q['invalidada'])): ?>
                                <span class="ml-1 text-[10px] text-amber-700 font-bold" title="Questão invalidada">INV</span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tr>
            <!-- Linha 3: % acerto por questão -->
            <tr class="resumo-row">
                <?php foreach ($materias as $mat): ?>
                    <?php foreach ($mat['questoes'] as $q): ?>
                        <?php
                        $st = $stats[$q['id']] ?? ['acertos' => 0, 'total' => 0];
                        $pct = $st['total'] > 0 ? round(100 * $st['acertos'] / $st['total']) : 0;
                        ?>
                        <td title="<?= $st['acertos'] ?>/<?= $st['total'] ?> acertos"><?= $pct ?>%</td>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($alunosData as $idx => $al): ?>
            <tr>
                <td class="col-num"><?= $idx + 1 ?></td>
                <td class="col-nome" title="<?= htmlspecialchars($al['nome']) ?>"><?= htmlspecialchars($al['nome']) ?></td>
                <?php foreach ($materias as $mat): ?>
                    <?php foreach ($mat['questoes'] as $q): ?>
                        <?php
                        $resp = $al['respostas'][$q['id']] ?? null;
                        if ($resp === null): ?>
                            <td class="celula-dash">-</td>
                        <?php elseif ($resp['correta']): ?>
                            <td class="celula-1" data-questao-id="<?= (int)$q['id'] ?>" data-aluno-id="<?= (int)$al['id'] ?>" data-alt-id="<?= (int)($resp['alternativa_id'] ?? 0) ?>">1</td>
                        <?php else: ?>
                            <td class="celula-0" data-questao-id="<?= (int)$q['id'] ?>" data-aluno-id="<?= (int)$al['id'] ?>" data-alt-id="<?= (int)($resp['alternativa_id'] ?? 0) ?>">0</td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <td class="col-acertos"><?= (int)$al['total_acertos'] ?></td>
                <td class="col-nota"><?= number_format((float)$al['nota'], 1, ',', '') ?></td>
                <td class="col-obs"><?= htmlspecialchars($al['obs']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal para ver questão -->
<div id="modal-questao" class="no-print fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 p-4" style="display:none;">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 relative">
        <button type="button" id="modal-questao-fechar" class="absolute top-3 right-3 text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
        <h3 id="modal-questao-materia" class="text-sm font-semibold text-indigo-600 mb-1"></h3>
        <p id="modal-questao-numero" class="text-xs text-gray-500 mb-3"></p>
        <div id="modal-questao-enunciado" class="text-gray-800 mb-4 leading-relaxed"></div>
        <div id="modal-questao-alternativas" class="space-y-2 mb-4"></div>
        <div id="modal-questao-resposta-aluno" class="hidden border-t border-gray-200 pt-3 mt-3">
            <p class="text-sm font-semibold text-gray-700 mb-1">Resposta do aluno:</p>
            <p id="modal-questao-resp-texto" class="text-sm text-gray-600"></p>
        </div>
    </div>
</div>

<script>
(function() {
    var questoesData = <?= json_encode($questoesAlt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var materiasMap = {};
    <?php foreach ($materias as $mat): ?>
        <?php foreach ($mat['questoes'] as $qi => $q): ?>
            materiasMap[<?= (int)$q['id'] ?>] = {
                materia: <?= json_encode($mat['materia_nome'], JSON_HEX_TAG) ?>,
                numero: <?= $qi + 1 ?>
            };
        <?php endforeach; ?>
    <?php endforeach; ?>

    function abrirModal(questaoId, altIdAluno) {
        var q = questoesData[questaoId];
        if (!q) return;
        var info = materiasMap[questaoId] || {};
        var modal = document.getElementById('modal-questao');
        document.getElementById('modal-questao-materia').textContent = info.materia || '';
        document.getElementById('modal-questao-numero').textContent = 'Questão ' + (info.numero || '') + '  |  Valor: ' + parseFloat(q.valor || 0).toFixed(2).replace('.', ',') + ' pts';
        document.getElementById('modal-questao-enunciado').innerHTML = q.enunciado || '';

        var altContainer = document.getElementById('modal-questao-alternativas');
        altContainer.innerHTML = '';
        var letras = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        if (q.alternativas && q.alternativas.length > 0) {
            q.alternativas.forEach(function(alt, i) {
                var div = document.createElement('div');
                var isCorreta = parseInt(alt.correta) === 1;
                var isEscolhida = altIdAluno && parseInt(alt.id) === parseInt(altIdAluno);
                var cls = 'p-2 rounded-lg border text-sm ';
                if (isCorreta) {
                    cls += 'border-green-400 bg-green-50 ';
                } else if (isEscolhida) {
                    cls += 'border-red-400 bg-red-50 ';
                } else {
                    cls += 'border-gray-200 bg-white ';
                }
                div.className = cls;
                var badge = '';
                if (isCorreta) badge = ' <span class="text-xs font-bold text-green-700 ml-1">(GABARITO)</span>';
                if (isEscolhida && !isCorreta) badge += ' <span class="text-xs font-bold text-red-600 ml-1">(resposta do aluno)</span>';
                div.innerHTML = '<strong>' + (letras[i] || (i+1)) + ')</strong> ' + (alt.texto || '') + badge;
                altContainer.appendChild(div);
            });
        }

        var respSection = document.getElementById('modal-questao-resposta-aluno');
        if (altIdAluno) {
            respSection.classList.remove('hidden');
            var escolhida = null;
            if (q.alternativas) {
                q.alternativas.forEach(function(alt, i) {
                    if (parseInt(alt.id) === parseInt(altIdAluno)) {
                        escolhida = (letras[i] || (i+1)) + ') ' + (alt.texto || '');
                    }
                });
            }
            document.getElementById('modal-questao-resp-texto').textContent = escolhida || 'Alternativa ID ' + altIdAluno;
        } else {
            respSection.classList.add('hidden');
        }

        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }

    function fecharModal() {
        var modal = document.getElementById('modal-questao');
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }

    document.getElementById('modal-questao-fechar').addEventListener('click', fecharModal);
    document.getElementById('modal-questao').addEventListener('click', function(e) {
        if (e.target === this) fecharModal();
    });

    document.getElementById('tabelaRelatorio').addEventListener('click', function(e) {
        var td = e.target.closest('td, th');
        if (!td) return;
        var qid = td.getAttribute('data-questao-id');
        if (!qid) return;
        var altId = td.getAttribute('data-alt-id') || null;
        abrirModal(parseInt(qid), altId ? parseInt(altId) : null);
    });
})();

function imprimirMatriz() {
    var root = document.getElementById('matriz-desempenho-root');
    if (!root) return;

    var titulo = <?= json_encode('Matriz de Desempenho: ' . ($bloco['titulo'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var clone = root.cloneNode(true);
    clone.querySelectorAll('.no-print').forEach(function(el) { el.remove(); });

    var printHeader = clone.querySelector('.matriz-print-header');
    if (printHeader) {
        printHeader.style.display = 'block';
    }

    var printCss = [
        '@page { size: A4 landscape; margin: 5mm; }',
        'html, body { margin: 0; padding: 0; background: #fff; font-family: system-ui, sans-serif; }',
        '.matriz-print-header { display: block; margin-bottom: 6px; }',
        '.matriz-print-header h2 { font-size: 13px; margin: 0 0 2px; color: #111; }',
        '.matriz-print-header p { font-size: 9px; color: #444; margin: 0; }',
        '.relatorio-table-wrap { overflow: visible; box-shadow: none; border: none; }',
        '.relatorio-table { border-collapse: collapse; width: 100%; font-size: 6.5px; table-layout: fixed; }',
        '.relatorio-table th, .relatorio-table td { border: 1px solid #999; padding: 1px 2px; text-align: center; white-space: nowrap; }',
        '.relatorio-table .col-nome { text-align: left; overflow: hidden; text-overflow: ellipsis; width: 110px; max-width: 110px; }',
        '.relatorio-table .col-num { width: 18px; }',
        '.relatorio-table thead th { background: #f3f4f6; }',
        '.relatorio-table .materia-header { background: #e0e7ff; font-weight: 700; color: #3730a3; }',
        '.relatorio-table .resumo-row td { background: #fefce8; font-weight: 600; }',
        '.celula-1 { background: #dcfce7 !important; color: #166534; font-weight: 600; }',
        '.celula-0 { background: #fee2e2 !important; color: #991b1b; font-weight: 600; }',
        '.celula-dash { background: #f3f4f6; color: #9ca3af; }',
        '.col-acertos { background: #f0fdf4; font-weight: 700; }',
        '.col-nota { background: #eff6ff; font-weight: 700; }',
        '.col-obs { background: #fefce8; font-weight: 600; }',
        '.celula-1, .celula-0, .celula-dash, .materia-header, .resumo-row td, .col-acertos, .col-nota, .col-obs, .relatorio-table thead th { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }'
    ].join('\n');

    var html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>' + titulo + '</title><style>' + printCss + '</style></head><body>';
    html += clone.innerHTML;
    html += '</body></html>';

    var iframe = document.getElementById('matriz-print-frame');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'matriz-print-frame';
        iframe.setAttribute('title', 'Impressão da matriz de desempenho');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
        document.body.appendChild(iframe);
    }

    var frameDoc = iframe.contentDocument || iframe.contentWindow.document;

    frameDoc.open();
    frameDoc.write(html);
    frameDoc.close();

    setTimeout(function() {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) {
            document.body.classList.add('printing-matriz');
            window.print();
            setTimeout(function() { document.body.classList.remove('printing-matriz'); }, 1000);
        }
    }, 400);
}

function exportarExcel() {
    var table = document.getElementById('tabelaRelatorio');
    if (!table) return;
    var rows = table.querySelectorAll('tr');
    var csv = [];
    var tituloBloco = <?= json_encode($bloco['titulo'] ?? 'Matriz de Desempenho', JSON_HEX_TAG) ?>;

    rows.forEach(function(row) {
        var cols = row.querySelectorAll('th, td');
        var rowData = [];
        cols.forEach(function(col) {
            var text = col.innerText.replace(/"/g, '""').trim();
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(';'));
    });

    var csvContent = '\uFEFF' + csv.join('\n');
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'matriz-desempenho-' + tituloBloco.replace(/[^a-zA-Z0-9]/g, '_') + '.csv';
    link.click();
}
</script>

<?php endif; ?>

</div><!-- /#matriz-desempenho-root -->
