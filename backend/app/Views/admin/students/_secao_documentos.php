<?php
/** Extraído de show.php — conteúdo existente, sem alteração de regra de negócio. */
?>
    <?php
    require_once __DIR__ . '/../../../Models/User/StudentDocument.php';
    $docChecklist = \StudentDocument::checklist();
    $docsAluno = is_array($documentos_aluno ?? null) ? $documentos_aluno : [];
    $docsPorTipo = [];
    $docsOutros = [];
    foreach ($docsAluno as $docRow) {
        if (($docRow['tipo'] ?? '') === 'outros') {
            $docsOutros[] = $docRow;
        } else {
            $docsPorTipo[$docRow['tipo']] = $docRow;
        }
    }
    $docStatusBadge = static function ($status) {
        switch ((string) $status) {
            case 'entregue': return ['Entregue', 'bg-green-100 text-green-800'];
            case 'dispensado': return ['Dispensado', 'bg-slate-100 text-slate-600'];
            default: return ['Pendente', 'bg-amber-100 text-amber-800'];
        }
    };
    $docCanEdit = !empty($admin_permissions['documentos_aluno']['cadastrar']) || !empty($admin_permissions['documentos_aluno']['alterar']);
    $docCanDelete = !empty($admin_permissions['documentos_aluno']['excluir']);
    $totalEntregues = 0;
    foreach ($docChecklist as $ck => $lbl) {
        if ($ck === 'outros') { continue; }
        if (($docsPorTipo[$ck]['status'] ?? '') === 'entregue') { $totalEntregues++; }
    }
    $totalChecklist = count($docChecklist) - 1;
    ?>
    <div id="section-documentos-aluno" class="student-card min-w-0 mb-6" data-perm-key="documentos_aluno" data-perm-action="visualizar">
        <div class="student-card-header flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-folder-open text-indigo-500"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Documentos</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Checklist de entrega · <?= (int) $totalEntregues ?>/<?= (int) $totalChecklist ?> entregues</p>
                </div>
            </div>
            <?php if ($docCanEdit): ?>
            <button type="button" onclick="abrirModalDocumento()" class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
                <i class="fa-solid fa-plus mr-2"></i> Documento
            </button>
            <?php endif; ?>
        </div>
        <div class="student-card-body">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                            <th class="py-2 pr-4 font-semibold">Documento</th>
                            <th class="py-2 pr-4 font-semibold">Status</th>
                            <th class="py-2 pr-4 font-semibold">Arquivo</th>
                            <th class="py-2 font-semibold text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $linhasDoc = [];
                        foreach ($docChecklist as $ckTipo => $ckLabel) {
                            if ($ckTipo === 'outros') { continue; }
                            $linhasDoc[] = ['tipo' => $ckTipo, 'label' => $ckLabel, 'row' => $docsPorTipo[$ckTipo] ?? null];
                        }
                        foreach ($docsOutros as $outroRow) {
                            $linhasDoc[] = ['tipo' => 'outros', 'label' => \StudentDocument::tipoLabel('outros', $outroRow['titulo'] ?? null), 'row' => $outroRow];
                        }
                        foreach ($linhasDoc as $linha):
                            $row = $linha['row'];
                            [$badgeLabel, $badgeClass] = $docStatusBadge($row['status'] ?? 'pendente');
                            $temArquivo = !empty($row['arquivo_key']);
                            $docId = (int) ($row['id'] ?? 0);
                            $dataAttr = htmlspecialchars(json_encode([
                                'doc_id' => $docId,
                                'tipo' => $linha['tipo'],
                                'titulo' => (string) ($row['titulo'] ?? ''),
                                'status' => (string) ($row['status'] ?? 'pendente'),
                                'observacao' => (string) ($row['observacao'] ?? ''),
                                'label' => $linha['label'],
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td class="py-3 pr-4">
                                <span class="font-medium text-slate-800"><?= safe_htmlspecialchars($linha['label']) ?></span>
                                <?php if (!empty($row['observacao'])): ?>
                                    <p class="text-xs text-slate-500 mt-0.5"><?= safe_htmlspecialchars($row['observacao']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 pr-4">
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                            </td>
                            <td class="py-3 pr-4">
                                <?php if ($temArquivo): ?>
                                    <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/documentos/<?= $docId ?>/baixar" target="_blank" rel="noopener"
                                       class="inline-flex items-center text-indigo-600 hover:text-indigo-800 text-sm">
                                        <i class="fa-solid fa-paperclip mr-1"></i> <?= safe_htmlspecialchars($row['arquivo_nome'] ?? 'Ver arquivo') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Sem arquivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-right whitespace-nowrap">
                                <?php if ($docCanEdit): ?>
                                <button type="button" class="px-2 py-1 text-xs font-medium text-blue-600 hover:text-blue-800"
                                        data-documento="<?= $dataAttr ?>" onclick="abrirModalDocumento(JSON.parse(this.dataset.documento))">
                                    Gerenciar
                                </button>
                                <?php endif; ?>
                                <?php if ($docCanDelete && $docId > 0 && ($temArquivo || $linha['tipo'] === 'outros')): ?>
                                <button type="button" class="px-2 py-1 text-xs font-medium text-red-600 hover:text-red-800"
                                        onclick="removerDocumento(<?= $docId ?>)">
                                    Remover
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
