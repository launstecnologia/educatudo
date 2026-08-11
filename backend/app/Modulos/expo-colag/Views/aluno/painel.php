<?php
$projeto = $projeto ?? [];
$tarefas = $tarefas ?? [];
$materiais = $materiais ?? [];
$progresso = $progresso ?? ['total' => 0, 'concluidas' => 0];
$stand = $stand ?? null;
$url_qr = $url_qr ?? null;
$csrf_token = $csrf_token ?? '';
$pid = (int) ($projeto['id'] ?? 0);
$aba = in_array($aba ?? '', ['painel', 'tarefas', 'materiais', 'stand'], true) ? $aba : 'painel';
$total = max(0, (int) ($progresso['total'] ?? 0));
$ok = (int) ($progresso['concluidas'] ?? 0);
$pct = $total > 0 ? (int) round(($ok / $total) * 100) : 0;

$badge = static function (string $st): string {
    $map = [
        'Pendente' => 'bg-slate-100 text-slate-700',
        'Em_andamento' => 'bg-sky-100 text-sky-800',
        'Entregue' => 'bg-violet-100 text-violet-800',
        'Concluida' => 'bg-emerald-100 text-emerald-800',
        'Atrasada' => 'bg-red-100 text-red-800',
        'Devolvida' => 'bg-amber-100 text-amber-800',
    ];
    return $map[$st] ?? 'bg-slate-100 text-slate-700';
};
$tabs = ['painel' => 'Meu painel', 'tarefas' => 'Minhas tarefas', 'materiais' => 'Materiais', 'stand' => 'Meu stand'];
?>
<div class="max-w-3xl mx-auto px-4 py-6 space-y-6 pb-16">
    <div>
        <a href="<?= URL ?>/expo-colag/projeto/<?= $pid ?>" class="text-sm text-primary hover:underline">← Voltar ao projeto</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1"><?= htmlspecialchars($projeto['titulo'] ?? '') ?></h1>
        <p class="text-sm text-gray-600">Área do participante</p>
    </div>

    <nav class="flex flex-wrap gap-1 border-b border-gray-200">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="?aba=<?= $key ?>"
               class="px-3 py-2 text-sm font-medium rounded-t-lg <?= $aba === $key ? 'bg-white border border-b-white border-gray-200 text-primary -mb-px' : 'text-gray-600' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($aba === 'painel'): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="font-semibold text-gray-900">Progresso</h2>
            <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-primary rounded-full" style="width: <?= $pct ?>%"></div>
            </div>
            <p class="text-sm text-gray-600"><?= $ok ?> de <?= $total ?> tarefas concluídas (<?= $pct ?>%)</p>
            <div class="flex flex-wrap gap-2 pt-2">
                <a href="?aba=tarefas" class="px-3 py-2 rounded-lg text-sm border border-gray-300 bg-white hover:bg-gray-50">Ver tarefas</a>
                <a href="<?= URL ?>/expo-colag/programacao" class="px-3 py-2 rounded-lg text-sm border border-gray-300 bg-white hover:bg-gray-50">Programação</a>
            </div>
        </div>

    <?php elseif ($aba === 'tarefas'): ?>
        <?php if (empty($tarefas)): ?>
            <p class="text-sm text-gray-500">Nenhuma tarefa atribuída ainda.</p>
        <?php else: foreach ($tarefas as $t): ?>
            <?php $st = (string) ($t['status'] ?? ''); $podeEntregar = in_array($st, ['Pendente', 'Em_andamento', 'Atrasada', 'Devolvida'], true); ?>
            <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3 text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($t['tarefa_titulo'] ?? '') ?></h3>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($t['etapa_titulo'] ?? 'Geral') ?>
                            <?php if (!empty($t['data_limite'])): ?>
                                · prazo <?= htmlspecialchars(date('d/m/Y H:i', strtotime($t['data_limite']))) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs h-fit <?= $badge($st) ?>"><?= htmlspecialchars(str_replace('_', ' ', $st)) ?></span>
                </div>
                <?php if (!empty($t['tarefa_descricao'])): ?>
                    <p class="text-gray-700 whitespace-pre-line"><?= htmlspecialchars($t['tarefa_descricao']) ?></p>
                <?php endif; ?>
                <?php if (!empty($t['comentario_professor'])): ?>
                    <p class="text-amber-800 bg-amber-50 rounded-lg px-3 py-2 text-xs">Feedback: <?= htmlspecialchars($t['comentario_professor']) ?></p>
                <?php endif; ?>
                <?php if ($podeEntregar && ($t['tipo_entregavel'] ?? 'Nenhum') !== 'Nenhum'): ?>
                    <form method="post" action="<?= URL ?>/expo-colag/projeto/<?= $pid ?>/entregar-tarefa" class="space-y-2 border-t border-gray-100 pt-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="atribuicao_id" value="<?= (int) $t['id'] ?>">
                        <?php if (($t['tipo_entregavel'] ?? '') === 'Texto'): ?>
                            <textarea name="entrega_conteudo" rows="3" required placeholder="Sua entrega..." class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"></textarea>
                        <?php elseif (($t['tipo_entregavel'] ?? '') === 'Link'): ?>
                            <input type="url" name="entrega_arquivo_url" required placeholder="https://..." class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <?php else: ?>
                            <input type="url" name="entrega_arquivo_url" required placeholder="URL do arquivo" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                            <textarea name="entrega_conteudo" rows="2" placeholder="Observação (opcional)" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"></textarea>
                        <?php endif; ?>
                        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Enviar entrega</button>
                    </form>
                <?php elseif ($podeEntregar): ?>
                    <form method="post" action="<?= URL ?>/expo-colag/projeto/<?= $pid ?>/entregar-tarefa">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="atribuicao_id" value="<?= (int) $t['id'] ?>">
                        <input type="hidden" name="entrega_conteudo" value="Concluído">
                        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Marcar como feita</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>

    <?php elseif ($aba === 'materiais'): ?>
        <div class="rounded-xl border border-gray-200 bg-white divide-y divide-gray-100">
            <?php if (empty($materiais)): ?>
                <p class="px-5 py-6 text-sm text-gray-500">Nenhum material disponível.</p>
            <?php else: foreach ($materiais as $m): ?>
                <div class="px-5 py-3 text-sm">
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($m['titulo'] ?? '') ?></p>
                    <?php $link = $m['link_externo'] ?: ($m['arquivo_url'] ?? ''); if ($link): ?>
                        <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="text-primary hover:underline text-xs">Abrir</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </div>

    <?php else: ?>
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-2 text-sm">
            <?php if (!$stand): ?>
                <p class="text-gray-500">O stand ainda não foi gerado pelo professor.</p>
            <?php else: ?>
                <p><span class="text-gray-500">Número:</span> <?= htmlspecialchars($stand['numero'] ?? '—') ?></p>
                <?php if (!empty($stand['horario_apresentacao'])): ?>
                    <p><span class="text-gray-500">Apresentação:</span> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($stand['horario_apresentacao']))) ?></p>
                <?php endif; ?>
                <?php if ($url_qr): ?>
                    <a href="<?= htmlspecialchars($url_qr) ?>" target="_blank" rel="noopener" class="text-primary hover:underline break-all">Página pública do stand</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
