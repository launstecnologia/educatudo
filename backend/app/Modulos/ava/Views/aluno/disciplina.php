<?php
$disciplina = $disciplina ?? [];
$outline = $outline ?? [];
$progresso_map = $progresso_map ?? [];
$matricula = $matricula ?? [];
$frequencia = $frequencia ?? ['percentual' => 0, 'concluidas' => 0, 'total' => 0];
$avaliacoes = $avaliacoes ?? [];
$pct = (float) ($matricula['progresso_pct'] ?? 0);
?>

<div class="mb-6">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-4">
            <a href="<?= URL ?>/cursos" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars((string) $disciplina['nome']) ?></h2>
                <p class="text-sm text-gray-600"><?= htmlspecialchars((string) ($disciplina['curso_nome'] ?? '')) ?></p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="<?= URL ?>/cursos/disciplina/<?= (int) ($disciplina['id'] ?? 0) ?>/ao-vivo" class="inline-flex items-center px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700"><i class="fa-solid fa-video mr-2"></i> Ao vivo</a>
            <a href="<?= URL ?>/cursos/disciplina/<?= (int) ($disciplina['id'] ?? 0) ?>/atividades" class="inline-flex items-center px-4 py-2.5 bg-gray-800 text-white rounded-lg text-sm font-semibold hover:bg-gray-900"><i class="fa-solid fa-clipboard-list mr-2"></i> Atividades</a>
            <?php if (!empty($disciplina['professor_id'])): ?>
            <a href="<?= URL ?>/chat-professor/<?= (int) $disciplina['professor_id'] ?>" class="inline-flex items-center px-4 py-2.5 border border-gray-300 bg-white text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50"><i class="fa-regular fa-comments mr-2"></i> Falar com o professor</a>
            <?php endif; ?>
            <?php if (($matricula['status'] ?? '') === 'concluida'): ?>
            <a href="<?= URL ?>/cursos/disciplina/<?= (int) ($disciplina['id'] ?? 0) ?>/certificado" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700"><i class="fa-solid fa-award mr-2"></i> Certificado</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-gray-700">Seu progresso</span>
        <span class="text-sm text-gray-500"><?= $pct ?>%</span>
    </div>
    <div class="w-full bg-gray-100 rounded-full h-3 mb-3">
        <div class="bg-green-600 h-3 rounded-full" style="width: <?= max(0, min(100, $pct)) ?>%"></div>
    </div>
    <p class="text-xs text-gray-500">Frequência (aulas obrigatórias concluídas): <?= (float) $frequencia['percentual'] ?>% (<?= (int) $frequencia['concluidas'] ?>/<?= (int) $frequencia['total'] ?>)</p>
</div>

<?php if (!empty($avaliacoes)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
        <i class="fa-solid fa-file-pen text-gray-500"></i>
        <h3 class="font-semibold text-gray-900">Avaliação</h3>
    </div>
    <ul class="divide-y divide-gray-100">
        <?php foreach ($avaliacoes as $av):
            $liberado = !empty($av['liberado']);
            $status = (string) ($av['status'] ?? 'pendente');
            $finalizado = $status === 'finalizado';
            $emAndamento = $status === 'em_andamento';
            $req = (float) ($av['requisito_progresso_pct'] ?? 0);
            $provaId = (int) ($av['prova_id'] ?? 0);
        ?>
        <li class="px-6 py-4 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <span class="w-10 h-10 rounded-full flex items-center justify-center <?= $finalizado ? 'bg-green-100 text-green-600' : ($liberado ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-400') ?>">
                    <i class="fa-solid <?= $finalizado ? 'fa-circle-check' : ($liberado ? 'fa-file-pen' : 'fa-lock') ?>"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars((string) $av['titulo']) ?><?php if (empty($av['obrigatoria'])): ?> <span class="text-xs font-normal text-gray-400">(opcional)</span><?php endif; ?></p>
                    <?php if ($finalizado): ?>
                        <p class="text-xs text-gray-500">Concluída · Nota: <strong class="text-gray-700"><?= $av['nota'] !== null ? number_format((float) $av['nota'], 2, ',', '.') : '—' ?></strong></p>
                    <?php elseif (!$liberado): ?>
                        <p class="text-xs text-gray-500">Disponível ao atingir <?= $req ?>% de progresso — você tem <?= $pct ?>%</p>
                    <?php else: ?>
                        <p class="text-xs text-gray-500">Liberada — você pode realizar a avaliação</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="shrink-0">
                <?php if ($finalizado): ?>
                    <a href="<?= URL ?>/aluno/provas/resultado/<?= $provaId ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50"><i class="fa-solid fa-eye mr-2"></i> Ver resultado</a>
                <?php elseif ($liberado): ?>
                    <a href="<?= URL ?>/aluno/provas/realizar/<?= $provaId ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700"><i class="fa-solid fa-pen-to-square mr-2"></i> <?= $emAndamento ? 'Continuar' : 'Fazer avaliação' ?></a>
                <?php else: ?>
                    <span class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-400 rounded-lg text-sm font-semibold cursor-not-allowed"><i class="fa-solid fa-lock mr-2"></i> Bloqueada</span>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (empty($outline)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-gray-500">Esta disciplina ainda não tem conteúdo publicado.</div>
<?php else: foreach ($outline as $m): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-4">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars((string) $m['titulo']) ?></h3>
        </div>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($m['aulas'] as $a):
                $prog = $progresso_map[(int) $a['id']] ?? null;
                $concluida = $prog && ($prog['status'] ?? '') === 'concluida';
                $emAndamento = $prog && ($prog['status'] ?? '') === 'em_andamento';
            ?>
                <li>
                    <a href="<?= URL ?>/cursos/aula/<?= (int) $a['id'] ?>" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm <?= $concluida ? 'bg-green-100 text-green-600' : ($emAndamento ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-400') ?>">
                                <i class="fa-solid <?= $concluida ? 'fa-check' : (($a['tipo'] ?? '') === 'video' ? 'fa-play' : 'fa-file-lines') ?>"></i>
                            </span>
                            <span class="text-sm text-gray-800 truncate"><?= htmlspecialchars((string) $a['titulo']) ?></span>
                        </div>
                        <i class="fa-solid fa-chevron-right text-gray-300"></i>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endforeach; endif; ?>
