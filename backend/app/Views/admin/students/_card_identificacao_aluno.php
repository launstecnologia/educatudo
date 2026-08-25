<?php
$alunoIdCard = (int) ($student['id'] ?? 0);
$alunoCardModo = $alunoCardModo ?? 'full';
$statusBadgeClass = $matriculaEncerrada
    ? 'bg-slate-100 text-slate-700'
    : ($statusAlunoAtivo ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800');
$statusBadgeLabel = $matriculaEncerrada ? 'Encerrada' : ($statusAlunoAtivo ? 'Ativa' : 'Inativa');
$metaLinhaAluno = $metaLinhaAluno ?? '';
$turmaAnoLabel = $turmaAnoLabel ?? $turmaDisplay;
?>
<?php if ($alunoCardModo === 'compact'): ?>
<div id="aluno-id-card-compact" class="hidden px-4 py-3 md:px-5 border-b border-slate-100">
    <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-full border border-violet-100 bg-violet-100 text-violet-700 flex items-center justify-center text-sm font-semibold shrink-0 overflow-hidden">
            <?php if (!empty($student['foto_display_url'])): ?>
                <img src="<?= htmlspecialchars((string) $student['foto_display_url']) ?>" alt="" class="h-full w-full object-cover">
            <?php else: ?>
                <?= safe_htmlspecialchars($student['foto_initials'] ?? '?') ?>
            <?php endif; ?>
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-base font-bold text-slate-900 truncate"><?= safe_htmlspecialchars($student['nome'] ?? '', 'Aluno') ?></h2>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusBadgeClass ?>"><?= safe_htmlspecialchars($statusBadgeLabel) ?></span>
                <?php if (!$matriculaPendente && !$matriculaEncerrada): ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-800"><?= safe_htmlspecialchars($turmaAnoLabel) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="hidden sm:flex items-center gap-2 shrink-0">
            <a href="<?= URL ?>/admin/students/<?= $alunoIdCard ?>/edit"
               data-perm-key="acao_rapida_editar_aluno" data-perm-action="visualizar"
               class="btn-primary-custom inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                <i class="fa-solid fa-pen-to-square mr-2"></i>
                Editar aluno
            </a>
            <button type="button"
                    onclick="abrirOffcanvasAcoesAluno(this)"
                    class="aluno-btn-outline"
                    aria-haspopup="dialog"
                    aria-controls="offcanvasAcoesAluno">
                Mais ações
                <i class="fa-solid fa-chevron-down ml-2 text-[10px] text-gray-500"></i>
            </button>
        </div>
    </div>
</div>
<?php else: ?>
<div id="aluno-id-card-full" class="p-5 md:p-6">
    <div class="flex flex-col lg:flex-row lg:items-center gap-5">
        <div class="flex items-start sm:items-center gap-4 min-w-0 flex-1">
            <?php
            $mode = 'compact';
            $size = 'lg';
            include __DIR__ . '/_student_photo.php';
            ?>
            <div class="min-w-0 flex-1">
                <h2 class="student-name-display text-xl md:text-[1.5rem] font-bold text-slate-900 leading-tight truncate">
                    <?= safe_htmlspecialchars($student['nome'] ?? '', 'Aluno') ?>
                </h2>
                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $statusBadgeClass ?>">
                        <?= safe_htmlspecialchars($statusBadgeLabel) ?>
                    </span>
                    <?php if ($alunoPagante): ?>
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-800">Pagante</span>
                    <?php endif; ?>
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $primeiroAcessoRealizado ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800' ?>">
                        <?= $primeiroAcessoRealizado ? '1º acesso realizado' : '1º acesso pendente' ?>
                    </span>
                    <?php if ($matriculaPendente): ?>
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Matrícula pendente</span>
                    <?php endif; ?>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    <?= safe_htmlspecialchars($metaLinhaAluno) ?>
                </p>
            </div>
        </div>
        <div class="flex flex-row flex-wrap gap-2 flex-shrink-0">
            <a href="<?= URL ?>/admin/students/<?= $alunoIdCard ?>/edit"
               data-perm-key="acao_rapida_editar_aluno" data-perm-action="visualizar"
               class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                <i class="fa-solid fa-pen-to-square mr-2"></i>
                Editar aluno
            </a>
            <button type="button"
                    onclick="abrirOffcanvasAcoesAluno(this)"
                    class="aluno-btn-outline"
                    aria-haspopup="dialog"
                    aria-controls="offcanvasAcoesAluno">
                Mais ações
                <i class="fa-solid fa-chevron-down ml-2 text-[10px] text-gray-500"></i>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>
