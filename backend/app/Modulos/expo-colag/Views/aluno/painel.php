<?php
$projeto = $projeto ?? [];
$user = $user ?? [];
$tarefas = $tarefas ?? [];
$materiais = $materiais ?? [];
$conteudos = $conteudos ?? [];
$pedidos = $pedidos ?? [];
$mensagens = $mensagens ?? [];
$progresso = $progresso ?? ['total' => 0, 'concluidas' => 0];
$csrf_token = $csrf_token ?? '';
$pid = (int) ($projeto['id'] ?? 0);
$abaRaw = (string) ($aba ?? 'progresso');
if ($abaRaw === 'painel' || $abaRaw === 'stand') {
    $abaRaw = 'progresso';
}
$aba = in_array($abaRaw, ['progresso', 'tarefas', 'grupo', 'conteudos', 'materiais'], true) ? $abaRaw : 'progresso';
$total = max(0, (int) ($progresso['total'] ?? 0));
$ok = (int) ($progresso['concluidas'] ?? 0);
$pct = $total > 0 ? (int) round(($ok / $total) * 100) : 0;
$podeSolicitar = !empty($pode_solicitar_materiais);
$motivoSolicitar = (string) ($motivo_solicitacao ?? '');

$badge = static function (string $st): string {
    $map = [
        'Pendente' => 'bg-slate-100 text-slate-700',
        'Em_andamento' => 'bg-sky-100 text-sky-800',
        'Entregue' => 'bg-violet-100 text-violet-800',
        'Concluida' => 'bg-emerald-100 text-emerald-800',
        'Atrasada' => 'bg-red-100 text-red-800',
        'Devolvida' => 'bg-amber-100 text-amber-800',
        'Aprovado' => 'bg-emerald-100 text-emerald-800',
        'Recusado' => 'bg-red-100 text-red-800',
    ];
    return $map[$st] ?? 'bg-slate-100 text-slate-700';
};
$tabs = ['progresso' => 'Progresso', 'tarefas' => 'Minhas tarefas', 'grupo' => 'Grupo', 'conteudos' => 'Conteúdo', 'materiais' => 'Materiais'];
$concluida = static function (string $st): bool {
    return in_array($st, ['Concluida', 'Entregue'], true);
};
?>
<div class="w-full space-y-6 pb-16">
    <div>
        <a href="<?= URL ?>/expo-colag/projeto/<?= $pid ?>" class="text-sm text-accent hover:underline">← Voltar ao projeto</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1"><?= htmlspecialchars($projeto['titulo'] ?? '') ?></h1>
        <p class="text-sm text-gray-600">Área do participante</p>
    </div>

    <nav class="flex flex-wrap gap-1 border-b border-gray-200">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="?aba=<?= $key ?>"
               class="px-3 py-2 text-sm font-medium rounded-t-lg <?= $aba === $key ? 'bg-white border border-b-white border-gray-200 text-accent -mb-px' : 'text-gray-600 hover:text-gray-900' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($aba === 'progresso'): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="font-semibold text-gray-900">Progresso</h2>
            <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-primary rounded-full" style="width: <?= $pct ?>%"></div>
            </div>
            <p class="text-sm text-gray-600"><?= $ok ?> de <?= $total ?> tarefas concluídas (<?= $pct ?>%)</p>
            <div class="flex flex-wrap gap-2 pt-2">
                <a href="?aba=tarefas" class="btn-primary-custom px-3 py-2 rounded-lg text-sm font-medium hover:opacity-90">Ver tarefas</a>
                <a href="<?= URL ?>/expo-colag/programacao" class="px-3 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-800 hover:bg-gray-50">Programação</a>
            </div>
        </div>

    <?php elseif ($aba === 'tarefas'): ?>
        <?php if (empty($tarefas)): ?>
            <p class="text-sm text-gray-500">Nenhuma tarefa atribuída ainda.</p>
        <?php else: foreach ($tarefas as $t): ?>
            <?php
            $st = (string) ($t['status'] ?? '');
            $feita = $concluida($st);
            $podeEntregar = in_array($st, ['Pendente', 'Em_andamento', 'Atrasada', 'Devolvida'], true);
            $tipoEntrega = (string) ($t['tipo_entregavel'] ?? 'Nenhum');
            $marcarSimples = $podeEntregar && $tipoEntrega === 'Nenhum';
            ?>
            <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3 text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <div class="flex items-start gap-3 min-w-0">
                        <?php if ($marcarSimples): ?>
                            <form method="post" action="<?= URL ?>/expo-colag/projeto/<?= $pid ?>/entregar-tarefa" class="flex items-center gap-2 pt-0.5">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="atribuicao_id" value="<?= (int) $t['id'] ?>">
                                <input type="hidden" name="entrega_conteudo" value="Concluído">
                                <input type="checkbox" name="ok" value="1" class="w-5 h-5 rounded border-gray-300 cursor-pointer"
                                       title="Marcar como concluída" onchange="this.form.submit()" aria-label="Marcar tarefa como concluída">
                                <button type="submit" class="btn-primary-custom px-2.5 py-1 rounded-md text-xs font-medium hover:opacity-90">OK</button>
                            </form>
                        <?php else: ?>
                            <input type="checkbox" class="w-5 h-5 rounded border-gray-300 mt-0.5" <?= $feita ? 'checked' : '' ?> disabled
                                   aria-label="<?= $feita ? 'Tarefa concluída' : 'Tarefa pendente' ?>">
                        <?php endif; ?>
                        <div>
                            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($t['tarefa_titulo'] ?? '') ?></h3>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($t['etapa_titulo'] ?? 'Geral') ?>
                                <?php if (!empty($t['data_limite'])): ?>
                                    · prazo <?= htmlspecialchars(date('d/m/Y H:i', strtotime($t['data_limite']))) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs h-fit <?= $badge($st) ?>"><?= htmlspecialchars(str_replace('_', ' ', $st)) ?></span>
                </div>
                <?php if (!empty($t['tarefa_descricao'])): ?>
                    <p class="text-gray-700 whitespace-pre-line"><?= htmlspecialchars($t['tarefa_descricao']) ?></p>
                <?php endif; ?>
                <?php if (!empty($t['comentario_professor'])): ?>
                    <p class="text-amber-800 bg-amber-50 rounded-lg px-3 py-2 text-xs">Feedback: <?= htmlspecialchars($t['comentario_professor']) ?></p>
                <?php endif; ?>
                <?php if ($podeEntregar && $tipoEntrega !== 'Nenhum'): ?>
                    <form method="post" action="<?= URL ?>/expo-colag/projeto/<?= $pid ?>/entregar-tarefa" class="space-y-2 border-t border-gray-100 pt-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="atribuicao_id" value="<?= (int) $t['id'] ?>">
                        <?php if ($tipoEntrega === 'Texto'): ?>
                            <textarea name="entrega_conteudo" rows="3" required placeholder="Sua entrega..." class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"></textarea>
                        <?php elseif ($tipoEntrega === 'Link'): ?>
                            <input type="url" name="entrega_arquivo_url" required placeholder="https://..." class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <?php else: ?>
                            <input type="url" name="entrega_arquivo_url" required placeholder="URL do arquivo" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                            <textarea name="entrega_conteudo" rows="2" placeholder="Observação (opcional)" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"></textarea>
                        <?php endif; ?>
                        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">Enviar entrega</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>

    <?php elseif ($aba === 'grupo'): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-4">
            <h2 class="font-semibold text-gray-900">Conversa do grupo</h2>
            <div class="max-h-80 overflow-y-auto space-y-3 border border-gray-100 rounded-lg p-3 bg-slate-50">
                <?php if (empty($mensagens)): ?>
                    <p class="text-sm text-gray-500">Nenhuma mensagem ainda.</p>
                <?php else: foreach ($mensagens as $msg): ?>
                    <?php $ehAluno = ($msg['autor_tipo'] ?? '') === 'aluno'; ?>
                    <div class="text-sm <?= $ehAluno && (int) ($msg['autor_id'] ?? 0) === (int) ($user['id'] ?? 0) ? 'text-right' : '' ?>">
                        <p class="text-xs text-gray-500 mb-0.5">
                            <?= htmlspecialchars($msg['autor_nome'] ?? ($ehAluno ? 'Aluno' : 'Professor')) ?>
                            <?php if (!empty($msg['created_at'])): ?>
                                · <?= htmlspecialchars(date('d/m H:i', strtotime($msg['created_at']))) ?>
                            <?php endif; ?>
                        </p>
                        <p class="inline-block max-w-[90%] text-left rounded-lg px-3 py-2 <?= !$ehAluno ? 'bg-indigo-50 text-indigo-950' : 'bg-white border border-gray-200 text-gray-800' ?>">
                            <?= nl2br(htmlspecialchars($msg['mensagem'] ?? '')) ?>
                        </p>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <form method="post" action="<?= URL ?>/expo-colag/projeto/<?= $pid ?>/mensagens" class="space-y-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <textarea name="mensagem" required maxlength="2000" rows="3" placeholder="Escreva para o professor e o grupo…"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm"></textarea>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">Enviar</button>
            </form>
        </div>

    <?php elseif ($aba === 'conteudos'): ?>
        <div class="rounded-xl border border-gray-200 bg-white divide-y divide-gray-100">
            <?php if (empty($conteudos)): ?>
                <p class="px-5 py-6 text-sm text-gray-500">Nenhum conteúdo disponível ainda.</p>
            <?php else: foreach ($conteudos as $m): ?>
                <?php $meta = is_array($m['meta'] ?? null) ? $m['meta'] : (json_decode((string) ($m['visibilidade'] ?? ''), true) ?: []); ?>
                <div class="px-5 py-4 text-sm space-y-2">
                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($m['titulo'] ?? '') ?></p>
                    <?php if (!empty($meta['descricao_html'])): ?>
                        <div class="text-gray-700 leading-relaxed"><?= $meta['descricao_html'] ?></div>
                    <?php endif; ?>
                    <div class="flex flex-wrap gap-3 text-xs">
                        <?php if (!empty($m['link_externo'])): ?>
                            <a href="<?= htmlspecialchars($m['link_externo']) ?>" target="_blank" rel="noopener" class="text-accent hover:underline">Abrir link</a>
                        <?php endif; ?>
                        <?php if (!empty($meta['youtube_url'])): ?>
                            <a href="<?= htmlspecialchars($meta['youtube_url']) ?>" target="_blank" rel="noopener" class="text-accent hover:underline">Abrir YouTube</a>
                        <?php endif; ?>
                        <?php if (!empty($m['arquivo_url'])): ?>
                            <a href="<?= htmlspecialchars($m['arquivo_url']) ?>" target="_blank" rel="noopener" class="text-accent hover:underline">
                                <?= htmlspecialchars($meta['arquivo_nome'] ?? 'Abrir anexo') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

    <?php else: ?>
        <?php if ($podeSolicitar): ?>
            <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="font-semibold text-gray-900">Solicitar material ao professor</h2>
                <p class="text-sm text-gray-600">Descreva o que o grupo precisa. O professor avalia o pedido.</p>
                <form method="post" action="<?= URL ?>/expo-colag/projeto/<?= $pid ?>/solicitar-material" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Material</label>
                        <input type="text" name="titulo" required maxlength="255" placeholder="Ex.: papel cartão, fita, planta..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Quantidade</label>
                        <input type="text" name="quantidade" maxlength="60" placeholder="Ex.: 10 unidades"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Observação (opcional)</label>
                        <textarea name="observacao" rows="2" maxlength="500" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">Enviar pedido</button>
                    </div>
                </form>
            </div>
        <?php elseif ($motivoSolicitar !== ''): ?>
            <p class="text-sm text-gray-500"><?= htmlspecialchars($motivoSolicitar) ?></p>
        <?php endif; ?>

        <?php if (!empty($pedidos)): ?>
            <div class="rounded-xl border border-gray-200 bg-white divide-y divide-gray-100">
                <p class="px-5 py-3 text-sm font-semibold text-gray-900">Meus pedidos</p>
                <?php foreach ($pedidos as $pedido): ?>
                    <?php $stPedido = (string) ($pedido['status'] ?? 'Pendente'); ?>
                    <div class="px-5 py-3 text-sm flex flex-wrap justify-between gap-2">
                        <div>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($pedido['titulo'] ?? '') ?>
                                <?php if (!empty($pedido['quantidade'])): ?>
                                    <span class="text-gray-500 font-normal"> · <?= htmlspecialchars($pedido['quantidade']) ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($pedido['observacao'])): ?>
                                <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($pedido['observacao']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($pedido['resposta_professor'])): ?>
                                <p class="text-xs text-gray-600 mt-1">Resposta: <?= htmlspecialchars($pedido['resposta_professor']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs h-fit <?= $badge($stPedido) ?>"><?= htmlspecialchars($stPedido) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
