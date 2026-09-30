<?php
$tituloBloco = (string) ($bloco['titulo'] ?? 'Evento');
$blocoId = (int) ($bloco['id'] ?? 0);
$filtros = is_array($filtros ?? null) ? $filtros : [];
$opcoes = is_array($opcoes_filtro ?? null) ? $opcoes_filtro : [];
$totais = is_array($totais ?? null) ? $totais : [
    'total' => count($notas_linhas ?? []),
    'com_nota' => 0,
    'sem_nota' => 0,
    'transferidos' => 0,
];
$professores = is_array($opcoes['professores'] ?? null) ? $opcoes['professores'] : [];
$materias = is_array($opcoes['materias'] ?? null) ? $opcoes['materias'] : [];
$turmas = is_array($opcoes['turmas'] ?? null) ? $opcoes['turmas'] : [];

$fProfessor = (int) ($filtros['professor_id'] ?? 0);
$fProfessorRaw = (string) ($filtros['professor_filtro'] ?? '');
$fMateria = (int) ($filtros['materia_id'] ?? 0);
$fTurma = (int) ($filtros['turma_id'] ?? 0);
$fAluno = (string) ($filtros['aluno'] ?? '');
$fSituacao = (string) ($filtros['situacao_nota'] ?? '');
$fTransferido = (string) ($filtros['transferido'] ?? '');
$temCoordenacao = !empty($opcoes['tem_coordenacao']);

$urlRelatorio = URL . '/admin/provas/blocos/' . $blocoId . '/resultados';
$urlLimpar = $urlRelatorio;

$exportLinhas = [];
foreach ($notas_linhas ?? [] as $ln) {
    $notaRaw = $ln['nota'] ?? null;
    $notaFmt = ($notaRaw === null || $notaRaw === '')
        ? ''
        : number_format((float) $notaRaw, 2, ',', '.');
    $exportLinhas[] = [
        'materia' => (string) ($ln['materia_nome'] ?? ''),
        'professor' => (string) ($ln['professor_nome'] ?? ''),
        'turma' => (string) ($ln['turma_nome'] ?? ''),
        'aluno' => (string) ($ln['aluno_nome'] ?? ''),
        'transferido' => !empty($ln['transferido']) ? 'Sim' : 'Não',
        'nota' => $notaFmt,
        'atualizado' => !empty($ln['updated_at']) ? date('d/m/Y H:i', strtotime((string) $ln['updated_at'])) : '',
    ];
}
$slugArquivo = preg_replace('/[^a-zA-Z0-9_-]+/', '-', 'relatorio-notas-' . $tituloBloco);
$slugArquivo = trim((string) $slugArquivo, '-');
if ($slugArquivo === '') {
    $slugArquivo = 'relatorio-notas';
}
?>

<style>
    .relatorio-notas-print-header { display: none; }

    @media print {
        body.printing-relatorio-notas #sidebar,
        body.printing-relatorio-notas #sidebar-overlay,
        body.printing-relatorio-notas main > header,
        body.printing-relatorio-notas .relatorio-notas-actions,
        body.printing-relatorio-notas .relatorio-notas-filtros,
        body.printing-relatorio-notas .relatorio-notas-banner,
        body.printing-relatorio-notas #relatorio-notas-page > .mb-8 {
            display: none !important;
        }

        body.printing-relatorio-notas main {
            margin: 0 !important;
            padding: 0 !important;
            max-width: 100% !important;
        }

        body.printing-relatorio-notas .relatorio-notas-print-header {
            display: block !important;
            margin-bottom: 16px;
        }

        body.printing-relatorio-notas #relatorio-notas-export-root {
            box-shadow: none !important;
            border: none !important;
        }

        body.printing-relatorio-notas #tabelaRelatorioNotas {
            font-size: 10px;
        }

        body.printing-relatorio-notas #tabelaRelatorioNotas th,
        body.printing-relatorio-notas #tabelaRelatorioNotas td {
            border: 1px solid #ccc !important;
            padding: 4px 6px !important;
        }
    }
</style>

<div id="relatorio-notas-page">
    <div class="mb-8 flex flex-wrap justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Relatório de notas lançadas</h2>
            <p class="text-gray-600 mt-1"><?= htmlspecialchars($tituloBloco) ?></p>
        </div>
        <div class="flex flex-wrap gap-2 relatorio-notas-actions">
            <button type="button" onclick="exportarRelatorioExcel()"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Exportar Excel
            </button>
            <button type="button" onclick="exportarRelatorioPdf()"
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                Exportar PDF
            </button>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar"
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">← Painel do evento</a>
            <a href="<?= URL ?>/admin/provas" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">Lista de eventos</a>
        </div>
    </div>

    <div class="relatorio-notas-print-header">
        <h1 style="font-size: 18px; font-weight: 700; margin: 0 0 6px;">Relatório de notas lançadas</h1>
        <p style="font-size: 12px; margin: 0; color: #444;"><?= htmlspecialchars($tituloBloco) ?></p>
        <p style="font-size: 11px; margin: 8px 0 0; color: #666;">
            Gerado em <?= date('d/m/Y H:i') ?> —
            <?= (int) ($totais['total'] ?? 0) ?> registro(s)
            (<?= (int) ($totais['com_nota'] ?? 0) ?> com nota,
            <?= (int) ($totais['sem_nota'] ?? 0) ?> sem nota,
            <?= (int) ($totais['transferidos'] ?? 0) ?> transferido(s))
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6 relatorio-notas-filtros">
        <div class="px-4 py-3 border-b border-gray-200 bg-purple-50 relatorio-notas-banner">
            <p class="text-sm text-purple-900">
                Grade completa do evento: alunos com e sem nota, incluindo transferidos (badge TR).
                Use os filtros abaixo e exporte o resultado em Excel ou PDF.
            </p>
        </div>
        <form method="get" action="<?= htmlspecialchars($urlRelatorio) ?>" class="p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                <div>
                    <label for="filtro_materia" class="block text-xs font-medium text-gray-600 mb-1">Matéria</label>
                    <select id="filtro_materia" name="materia_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <?php foreach ($materias as $id => $nome): ?>
                            <option value="<?= (int) $id ?>" <?= $fMateria === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $nome) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filtro_professor" class="block text-xs font-medium text-gray-600 mb-1">Professor</label>
                    <select id="filtro_professor" name="professor_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        <?php if ($temCoordenacao): ?>
                            <option value="coordenacao" <?= $fProfessorRaw === 'coordenacao' ? 'selected' : '' ?>>
                                Coordenação (sem professor)
                            </option>
                        <?php endif; ?>
                        <?php foreach ($professores as $id => $nome): ?>
                            <?php if ((int) $id <= 0) { continue; } ?>
                            <option value="<?= (int) $id ?>" <?= $fProfessorRaw !== 'coordenacao' && $fProfessor === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $nome) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filtro_turma" class="block text-xs font-medium text-gray-600 mb-1">Turma</label>
                    <select id="filtro_turma" name="turma_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <?php foreach ($turmas as $id => $nome): ?>
                            <option value="<?= (int) $id ?>" <?= $fTurma === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $nome) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filtro_aluno" class="block text-xs font-medium text-gray-600 mb-1">Aluno</label>
                    <input id="filtro_aluno" type="text" name="aluno" value="<?= htmlspecialchars($fAluno) ?>"
                           placeholder="Buscar por nome"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="filtro_situacao" class="block text-xs font-medium text-gray-600 mb-1">Situação da nota</label>
                    <select id="filtro_situacao" name="situacao_nota"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="" <?= $fSituacao === '' ? 'selected' : '' ?>>Todas</option>
                        <option value="com_nota" <?= $fSituacao === 'com_nota' ? 'selected' : '' ?>>Com nota</option>
                        <option value="sem_nota" <?= $fSituacao === 'sem_nota' ? 'selected' : '' ?>>Sem nota</option>
                    </select>
                </div>
                <div>
                    <label for="filtro_transferido" class="block text-xs font-medium text-gray-600 mb-1">Transferido</label>
                    <select id="filtro_transferido" name="transferido"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="" <?= $fTransferido === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="1" <?= $fTransferido === '1' ? 'selected' : '' ?>>Somente transferidos</option>
                        <option value="0" <?= $fTransferido === '0' ? 'selected' : '' ?>>Somente ativos</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit"
                        class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm">
                    Aplicar filtros
                </button>
                <a href="<?= htmlspecialchars($urlLimpar) ?>"
                   class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm">
                    Limpar
                </a>
                <span class="text-sm text-gray-600 ml-1">
                    <?= (int) ($totais['total'] ?? 0) ?> registro(s)
                    · <?= (int) ($totais['com_nota'] ?? 0) ?> com nota
                    · <?= (int) ($totais['sem_nota'] ?? 0) ?> sem nota
                    · <?= (int) ($totais['transferidos'] ?? 0) ?> transferido(s)
                </span>
            </div>
        </form>
    </div>

    <div id="relatorio-notas-export-root" class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table id="tabelaRelatorioNotas" class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
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
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                            Nenhum registro encontrado com os filtros atuais.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($notas_linhas as $ln): ?>
                        <?php
                        $transferido = !empty($ln['transferido']);
                        $nomeAluno = (string) ($ln['aluno_nome'] ?? '');
                        $temNota = ($ln['nota'] !== null && $ln['nota'] !== '');
                        $classeLinha = $transferido
                            ? 'bg-gray-100 text-gray-500'
                            : ($temNota && (float) $ln['nota'] < 6 ? 'bg-red-50' : '');
                        ?>
                        <tr class="<?= $classeLinha ?>">
                            <td class="px-3 py-2"><?= htmlspecialchars($ln['materia_nome'] ?? '') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($ln['professor_nome'] ?? '') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($ln['turma_nome'] ?? '') ?></td>
                            <td class="px-3 py-2 font-medium">
                                <?php if ($transferido): ?>
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide bg-gray-300 text-gray-700">TR</span>
                                        <span><?= htmlspecialchars($nomeAluno) ?></span>
                                    </span>
                                <?php else: ?>
                                    <?= htmlspecialchars($nomeAluno) ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2">
                                <?php if (!$temNota): ?>
                                    <span class="text-amber-700 font-medium">Sem nota</span>
                                <?php else: ?>
                                    <?= htmlspecialchars(number_format((float) $ln['nota'], 2, ',', '.')) ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-gray-500">
                                <?= !empty($ln['updated_at']) ? date('d/m/Y H:i', strtotime((string) $ln['updated_at'])) : '—' ?>
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
    var EXPORT_LINHAS = <?= json_encode($exportLinhas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var ARQUIVO_BASE = <?= json_encode($slugArquivo, JSON_UNESCAPED_UNICODE) ?>;

    function csvCell(value) {
        var text = String(value == null ? '' : value).replace(/"/g, '""');
        return '"' + text + '"';
    }

    window.exportarRelatorioExcel = function () {
        var linhas = [['Matéria', 'Professor', 'Turma', 'Aluno', 'Transferido', 'Nota', 'Atualizado']];
        EXPORT_LINHAS.forEach(function (row) {
            linhas.push([
                row.materia,
                row.professor,
                row.turma,
                row.aluno,
                row.transferido,
                row.nota,
                row.atualizado
            ]);
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

    window.exportarRelatorioPdf = function () {
        document.body.classList.add('printing-relatorio-notas');
        window.addEventListener('afterprint', function () {
            document.body.classList.remove('printing-relatorio-notas');
        }, { once: true });
        setTimeout(function () {
            window.print();
        }, 150);
    };
})();
</script>
