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

$filtrosAtivosChips = [];
$filtrosAtivosCount = 0;
if ($fMateria > 0) {
    $filtrosAtivosCount++;
    $filtrosAtivosChips[] = 'Matéria: ' . ($materias[$fMateria] ?? ('#' . $fMateria));
}
if ($fProfessorRaw === 'coordenacao') {
    $filtrosAtivosCount++;
    $filtrosAtivosChips[] = 'Professor: Coordenação';
} elseif ($fProfessor > 0) {
    $filtrosAtivosCount++;
    $filtrosAtivosChips[] = 'Professor: ' . ($professores[$fProfessor] ?? ('#' . $fProfessor));
}
if ($fTurma > 0) {
    $filtrosAtivosCount++;
    $filtrosAtivosChips[] = 'Turma: ' . ($turmas[$fTurma] ?? ('#' . $fTurma));
}
if ($fAluno !== '') {
    $filtrosAtivosCount++;
    $filtrosAtivosChips[] = 'Aluno: ' . $fAluno;
}
if ($fSituacao === 'com_nota') {
    $filtrosAtivosCount++;
    $filtrosAtivosChips[] = 'Com nota';
} elseif ($fSituacao === 'sem_nota') {
    $filtrosAtivosCount++;
    $filtrosAtivosChips[] = 'Sem nota';
}
if ($fTransferido === '1') {
    $filtrosAtivosCount++;
    $filtrosAtivosChips[] = 'Somente transferidos';
} elseif ($fTransferido === '0') {
    $filtrosAtivosCount++;
    $filtrosAtivosChips[] = 'Somente ativos';
}

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
        body.printing-relatorio-notas .relatorio-notas-chips,
        body.printing-relatorio-notas #filterDrawer,
        body.printing-relatorio-notas #filterDrawerBackdrop,
        body.printing-relatorio-notas #relatorio-notas-page > .mb-6 {
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
    <div class="mb-6 flex flex-wrap justify-between items-start gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Relatório de notas lançadas</h2>
            <p class="text-gray-600 mt-1"><?= htmlspecialchars($tituloBloco) ?></p>
        </div>
        <div class="flex flex-wrap gap-2 relatorio-notas-actions">
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= (int) $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>
            <button type="button" onclick="exportarRelatorioExcel()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">
                <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                Exportar Excel
            </button>
            <button type="button" onclick="exportarRelatorioPdf()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                Exportar PDF
            </button>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
                ← Painel do evento
            </a>
            <a href="<?= URL ?>/admin/provas"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
                Lista de eventos
            </a>
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

    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-600 relatorio-notas-chips">
        <?php foreach ($filtrosAtivosChips as $chip): ?>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium"><?= htmlspecialchars($chip) ?></span>
        <?php endforeach; ?>
        <span class="text-xs text-gray-500">
            <?= (int) ($totais['total'] ?? 0) ?> registro(s)
            · <?= (int) ($totais['com_nota'] ?? 0) ?> com nota
            · <?= (int) ($totais['sem_nota'] ?? 0) ?> sem nota
            · <?= (int) ($totais['transferidos'] ?? 0) ?> transferido(s)
        </span>
    </div>

    <p class="text-xs text-gray-500 mb-4">
        Grade completa do evento: alunos com e sem nota, incluindo transferidos (badge TR).
    </p>

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
                            Nenhum registro encontrado<?= $filtrosAtivosCount > 0 ? ' com os filtros atuais' : '' ?>.
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

<!-- Offcanvas filtros -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white shadow-2xl transform translate-x-full transition-transform duration-200 ease-out flex flex-col"
       aria-hidden="true">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Filtros</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
        </button>
    </div>
    <form method="get" action="<?= htmlspecialchars($urlRelatorio) ?>" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_materia" class="block text-sm font-medium text-gray-700 mb-1">Matéria</label>
                <select id="filtro_materia" name="materia_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">Todas</option>
                    <?php foreach ($materias as $id => $nome): ?>
                        <option value="<?= (int) $id ?>" <?= $fMateria === (int) $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $nome) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_professor" class="block text-sm font-medium text-gray-700 mb-1">Professor</label>
                <select id="filtro_professor" name="professor_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
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
                <label for="filtro_turma" class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
                <select id="filtro_turma" name="turma_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">Todas</option>
                    <?php foreach ($turmas as $id => $nome): ?>
                        <option value="<?= (int) $id ?>" <?= $fTurma === (int) $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $nome) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_aluno" class="block text-sm font-medium text-gray-700 mb-1">Aluno</label>
                <input id="filtro_aluno" type="text" name="aluno" value="<?= htmlspecialchars($fAluno) ?>"
                       placeholder="Buscar por nome"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label for="filtro_situacao" class="block text-sm font-medium text-gray-700 mb-1">Situação da nota</label>
                <select id="filtro_situacao" name="situacao_nota"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="" <?= $fSituacao === '' ? 'selected' : '' ?>>Todas</option>
                    <option value="com_nota" <?= $fSituacao === 'com_nota' ? 'selected' : '' ?>>Com nota</option>
                    <option value="sem_nota" <?= $fSituacao === 'sem_nota' ? 'selected' : '' ?>>Sem nota</option>
                </select>
            </div>
            <div>
                <label for="filtro_transferido" class="block text-sm font-medium text-gray-700 mb-1">Transferido</label>
                <select id="filtro_transferido" name="transferido"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="" <?= $fTransferido === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="1" <?= $fTransferido === '1' ? 'selected' : '' ?>>Somente transferidos</option>
                    <option value="0" <?= $fTransferido === '0' ? 'selected' : '' ?>>Somente ativos</option>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3">
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

    window.openFilterDrawer = function () {
        document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
        var drawer = document.getElementById('filterDrawer');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    window.closeFilterDrawer = function () {
        document.getElementById('filterDrawerBackdrop').classList.add('hidden');
        var drawer = document.getElementById('filterDrawer');
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

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
