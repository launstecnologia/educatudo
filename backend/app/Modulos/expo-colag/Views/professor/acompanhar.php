<?php
$projeto = $projeto ?? [];
$inscricoes = $inscricoes ?? [];
$relacoes = $relacoes ?? [];
$tarefas = $tarefas ?? [];
$atribuicoes = $atribuicoes ?? [];
$materiais = $materiais ?? [];
$conteudos = $conteudos ?? [];
$pedidos_materiais = $pedidos_materiais ?? [];
$mensagens = $mensagens ?? [];
$stand = $stand ?? null;
$url_qr = $url_qr ?? null;
$setores = $setores ?? [];
$csrf_token = $csrf_token ?? '';
$modoAdmin = !empty($modo_admin);
$baseUrlExpo = rtrim((string) ($base_url_expo ?? (URL . '/professor/expo-colag')), '/');
$pid = (int) ($projeto['id'] ?? 0);
$aba = in_array($aba ?? '', ['geral', 'participantes', 'grupo', 'tarefas', 'materiais', 'conteudos', 'stand'], true)
    ? $aba : 'geral';
$etapas = $relacoes['etapas'] ?? [];
$aprovados = array_values(array_filter($inscricoes, static fn($i) => ($i['status'] ?? '') === 'Aprovada'));
$listaAlmox = ExpoColagService::decodificarMateriaisNecessarios($projeto['materiais_necessarios'] ?? []);

$badge = static function (string $st): string {
    $map = [
        'Aguardando' => 'bg-amber-100 text-amber-800',
        'Aprovada' => 'bg-emerald-100 text-emerald-800',
        'Lista_espera' => 'bg-sky-100 text-sky-800',
        'Recusada' => 'bg-red-100 text-red-800',
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

$tabs = [
    'geral' => 'Visão geral',
    'participantes' => 'Alunos do grupo',
    'grupo' => 'Conversa',
    'tarefas' => 'Tarefas',
    'materiais' => 'Materiais',
    'conteudos' => 'Conteúdo',
    'stand' => 'Stand / QR',
];
$atrPorTarefa = [];
foreach ($atribuicoes as $a) {
    $atrPorTarefa[(int) $a['tarefa_id']][] = $a;
}
?>
<div class="mb-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="<?= htmlspecialchars($baseUrlExpo) ?>" class="text-sm text-accent hover:underline">← Expo Colag</a>
            <h2 class="text-2xl font-bold text-gray-900 mt-1"><?= htmlspecialchars($projeto['titulo'] ?? '') ?></h2>
            <p class="text-sm text-gray-600">
                <?= $modoAdmin ? 'Painel administrativo' : 'Meu painel' ?> · <?= htmlspecialchars(str_replace('_', ' ', $projeto['status'] ?? '')) ?>
                · <?= count($aprovados) ?> aluno(s) no grupo
                <?php if ($modoAdmin): ?>
                    · Professor: <?= htmlspecialchars($projeto['professor_nome'] ?? '—') ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/materiais-pdf"
               target="_blank" rel="noopener"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">PDF materiais</a>
            <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/editar" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Editar</a>
            <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/preview" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Preview</a>
        </div>
    </div>

    <nav class="flex flex-wrap gap-1 border-b border-gray-200">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="?aba=<?= $key ?>"
               class="px-3 py-2 text-sm font-medium rounded-t-lg <?= $aba === $key ? 'bg-white border border-b-white border-gray-200 text-accent -mb-px' : 'text-gray-600 hover:text-gray-900' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($aba === 'geral'): ?>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">Inscritos</p>
                <p class="text-2xl font-bold"><?= count($inscricoes) ?></p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">Aprovados</p>
                <p class="text-2xl font-bold"><?= count($aprovados) ?></p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">Tarefas</p>
                <p class="text-2xl font-bold"><?= count($tarefas) ?></p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">Materiais</p>
                <p class="text-2xl font-bold"><?= count($listaAlmox) ?></p>
            </div>
        </div>
        <?php if (!empty($projeto['descricao'])): ?>
            <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-700 whitespace-pre-line"><?= htmlspecialchars($projeto['descricao']) ?></div>
        <?php endif; ?>
        <?php
        if ($listaAlmox): ?>
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="font-semibold text-gray-900">Materiais do almoxarifado</h2>
                    <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/materiais-pdf"
                       target="_blank" rel="noopener"
                       class="text-sm font-semibold text-accent hover:underline">Exportar PDF</a>
                </div>
                <ul class="text-sm space-y-1">
                    <?php foreach ($listaAlmox as $item): ?>
                        <li>
                            <?= htmlspecialchars($item['nome']) ?>
                            <?php if ($item['quantidade'] !== ''): ?>
                                <span class="text-gray-500"> · <?= htmlspecialchars($item['quantidade']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($etapas): ?>
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Etapas</h2>
                <ol class="space-y-2 text-sm">
                    <?php foreach ($etapas as $et): ?>
                        <li class="flex justify-between gap-2">
                            <span><?= (int) ($et['ordem'] ?? 0) ?>. <?= htmlspecialchars($et['titulo'] ?? '') ?></span>
                            <span class="text-gray-500"><?= htmlspecialchars(str_replace('_', ' ', $et['status'] ?? '')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>

    <?php elseif ($aba === 'participantes'): ?>
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <?php if (empty($inscricoes)): ?>
                <p class="px-6 py-8 text-sm text-gray-500">Nenhum aluno no grupo ainda. Edite o projeto e marque alunos específicos, ou aprove inscrições.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">Aluno</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Inscrito em</th>
                                <th class="px-4 py-3 font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($inscricoes as $i): ?>
                                <?php $st = (string) ($i['status'] ?? ''); ?>
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($i['aluno_nome'] ?? '') ?></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= $badge($st) ?>">
                                            <?= htmlspecialchars(str_replace('_', ' ', $st)) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        <?= !empty($i['inscrito_em']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($i['inscrito_em']))) : '—' ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (in_array($st, ['Aguardando', 'Lista_espera'], true)): ?>
                                            <div class="flex flex-wrap gap-2">
                                                <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/inscricoes/decidir">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                    <input type="hidden" name="inscricao_id" value="<?= (int) $i['id'] ?>">
                                                    <input type="hidden" name="decisao" value="aprovar">
                                                    <button type="submit" class="text-xs font-medium text-emerald-700 hover:underline">Aprovar</button>
                                                </form>
                                                <?php if ($st === 'Aguardando'): ?>
                                                <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/inscricoes/decidir" class="flex items-center gap-1"
                                                      onsubmit="var m=this.querySelector('[name=motivo_recusa]'); if(!m.value.trim()){alert('Informe o motivo'); return false;}">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                    <input type="hidden" name="inscricao_id" value="<?= (int) $i['id'] ?>">
                                                    <input type="hidden" name="decisao" value="recusar">
                                                    <input type="text" name="motivo_recusa" placeholder="Motivo" class="border border-gray-300 rounded px-2 py-1 text-xs w-28 bg-white">
                                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Recusar</button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($aba === 'grupo'): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-4">
            <h2 class="font-semibold text-gray-900">Conversa com o grupo</h2>
            <p class="text-sm text-gray-600">Mensagens para os alunos já incluídos neste projeto — mesmo com as inscrições encerradas.</p>
            <div class="max-h-80 overflow-y-auto space-y-3 border border-gray-100 rounded-lg p-3 bg-slate-50">
                <?php if (empty($mensagens)): ?>
                    <p class="text-sm text-gray-500">Nenhuma mensagem ainda. Envie o primeiro recado ao grupo.</p>
                <?php else: foreach ($mensagens as $msg): ?>
                    <?php $ehProf = ($msg['autor_tipo'] ?? '') === 'professor'; ?>
                    <div class="text-sm <?= $ehProf ? 'text-right' : '' ?>">
                        <p class="text-xs text-gray-500 mb-0.5">
                            <?= htmlspecialchars($msg['autor_nome'] ?? ($ehProf ? 'Professor' : 'Aluno')) ?>
                            <?php if (!empty($msg['created_at'])): ?>
                                · <?= htmlspecialchars(date('d/m H:i', strtotime($msg['created_at']))) ?>
                            <?php endif; ?>
                        </p>
                        <p class="inline-block max-w-[90%] text-left rounded-lg px-3 py-2 <?= $ehProf ? 'bg-indigo-50 text-indigo-950' : 'bg-white border border-gray-200 text-gray-800' ?>">
                            <?= nl2br(htmlspecialchars($msg['mensagem'] ?? '')) ?>
                        </p>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/mensagens" class="space-y-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <textarea name="mensagem" required maxlength="2000" rows="3" placeholder="Escreva para o grupo…"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm"></textarea>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">Enviar</button>
            </form>
        </div>

    <?php elseif ($aba === 'tarefas'): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-4">
            <h2 class="font-semibold text-gray-900">Nova tarefa</h2>
            <p class="text-sm text-gray-600">Cria e atribui ao grupo já formado, mesmo com as inscrições encerradas.</p>
            <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/tarefas" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="atribuir" value="<?= $aprovados ? 'selecionados' : 'todos' ?>">
                <div class="sm:col-span-2">
                    <label class="block text-gray-600 mb-1">Título</label>
                    <input type="text" name="titulo" required class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-gray-600 mb-1">Etapa</label>
                    <select name="etapa_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">Sem etapa</option>
                        <?php foreach ($etapas as $et): ?>
                            <option value="<?= (int) $et['id'] ?>"><?= htmlspecialchars($et['titulo'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-600 mb-1">Entregável</label>
                    <select name="tipo_entregavel" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="Nenhum">Nenhum</option>
                        <option value="Texto">Texto</option>
                        <option value="Link">Link</option>
                        <option value="Arquivo">Arquivo (URL)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-600 mb-1">Prazo</label>
                    <input type="datetime-local" name="data_limite" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div class="flex items-end gap-2">
                    <label class="inline-flex items-center gap-2 text-gray-700">
                        <input type="checkbox" name="obrigatoria" value="1" checked class="rounded border-gray-300">
                        Obrigatória
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-gray-600 mb-1">Descrição</label>
                    <textarea name="descricao" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-gray-600 mb-1">Alunos do grupo</label>
                    <?php if (empty($aprovados)): ?>
                        <p class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">Nenhum aluno aprovado ainda. Inclua alunos na edição do projeto ou aprove inscrições.</p>
                    <?php else: ?>
                        <div class="flex flex-wrap gap-x-4 gap-y-2 rounded-lg border border-gray-200 px-3 py-2">
                            <?php foreach ($aprovados as $ap): ?>
                                <label class="inline-flex items-center gap-2 text-gray-800">
                                    <input type="checkbox" name="inscricao_ids[]" value="<?= (int) $ap['id'] ?>" checked class="rounded border-gray-300">
                                    <?= htmlspecialchars($ap['aluno_nome'] ?? 'Aluno') ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90" <?= empty($aprovados) ? 'disabled' : '' ?>>Criar tarefa</button>
                    <p class="text-xs text-gray-500 mt-1">Desmarque quem não deve receber esta tarefa.</p>
                </div>
            </form>
        </div>

        <?php if (empty($tarefas)): ?>
            <p class="text-sm text-gray-500">Nenhuma tarefa ainda.</p>
        <?php else: foreach ($tarefas as $t): ?>
            <?php $tid = (int) $t['id']; $lista = $atrPorTarefa[$tid] ?? []; ?>
            <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($t['titulo'] ?? '') ?></h3>
                        <p class="text-xs text-gray-500">
                            <?= htmlspecialchars($t['etapa_titulo'] ?? 'Sem etapa') ?>
                            · <?= htmlspecialchars($t['tipo_entregavel'] ?? '') ?>
                            <?php if (!empty($t['data_limite'])): ?>
                                · prazo <?= htmlspecialchars(date('d/m/Y H:i', strtotime($t['data_limite']))) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/tarefas/excluir" onsubmit="return confirm('Remover tarefa?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="tarefa_id" value="<?= $tid ?>">
                        <button type="submit" class="text-xs text-red-600 hover:underline">Excluir</button>
                    </form>
                </div>
                <?php if (empty($lista)): ?>
                    <p class="px-5 py-4 text-sm text-gray-500">Sem atribuições.</p>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <?php foreach ($lista as $a): ?>
                            <li class="px-5 py-3 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($a['aluno_nome'] ?? '') ?></p>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= $badge((string) ($a['status'] ?? '')) ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $a['status'] ?? '')) ?>
                                    </span>
                                    <?php if (!empty($a['entrega_conteudo'])): ?>
                                        <p class="mt-1 text-gray-600 whitespace-pre-line"><?= htmlspecialchars($a['entrega_conteudo']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($a['entrega_arquivo_url'])): ?>
                                        <a href="<?= htmlspecialchars($a['entrega_arquivo_url']) ?>" target="_blank" rel="noopener" class="text-accent hover:underline text-xs">Abrir entrega</a>
                                    <?php endif; ?>
                                    <?php if (!empty($a['comentario_professor'])): ?>
                                        <p class="mt-1 text-xs text-amber-800">Feedback: <?= htmlspecialchars($a['comentario_professor']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if (($a['status'] ?? '') !== 'Concluida'): ?>
                                    <div class="flex flex-col gap-2 min-w-[180px]">
                                        <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/tarefas/decidir">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="atribuicao_id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="acao" value="concluir">
                                            <button type="submit" class="btn-primary-custom px-3 py-1.5 rounded-lg text-xs font-medium hover:opacity-90">Concluir</button>
                                        </form>
                                        <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/tarefas/decidir" class="space-y-1"
                                              onsubmit="var c=this.querySelector('[name=comentario]'); if(!c.value.trim()){alert('Comentário obrigatório'); return false;}">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="atribuicao_id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="acao" value="devolver">
                                            <input type="text" name="comentario" placeholder="Motivo devolução" class="w-full border border-gray-300 rounded px-2 py-1 text-xs bg-white">
                                            <button type="submit" class="text-xs font-medium text-amber-700 hover:underline">Devolver</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>

    <?php elseif ($aba === 'materiais'): ?>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">Itens para autorização da coordenação e retirada no almoxarifado.</p>
            <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/materiais-pdf"
               target="_blank" rel="noopener"
               class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">Gerar PDF</a>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-4">
            <div>
                <h2 class="font-semibold text-gray-900">Lista de materiais</h2>
                <p class="text-sm text-gray-500 mt-1">Adicione item e quantidade. Esses dados entram no PDF de solicitação.</p>
            </div>
            <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/materiais/salvar-itens" class="space-y-3" id="expoMateriaisForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="space-y-2" id="expoMateriaisRows">
                    <?php $itensMateriais = $listaAlmox ?: [['nome' => '', 'quantidade' => '', 'observacao' => '']]; ?>
                    <?php foreach ($itensMateriais as $item): ?>
                        <div class="expo-material-row grid grid-cols-1 md:grid-cols-12 gap-2 text-sm">
                            <input type="text" name="item_nome[]" value="<?= htmlspecialchars($item['nome'] ?? '') ?>" placeholder="Item"
                                   class="md:col-span-5 border border-gray-300 rounded-lg px-3 py-2 bg-white">
                            <input type="text" name="item_quantidade[]" value="<?= htmlspecialchars($item['quantidade'] ?? '') ?>" placeholder="Qtd."
                                   class="md:col-span-2 border border-gray-300 rounded-lg px-3 py-2 bg-white">
                            <input type="text" name="item_observacao[]" value="<?= htmlspecialchars($item['observacao'] ?? '') ?>" placeholder="Observação"
                                   class="md:col-span-4 border border-gray-300 rounded-lg px-3 py-2 bg-white">
                            <button type="button" class="expo-remove-material md:col-span-1 px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Remover</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="expoAddMaterialRow" class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">+ Adicionar item</button>
                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Salvar materiais</button>
                </div>
            </form>
        </div>
        <?php if (!empty($pedidos_materiais)): ?>
        <div class="rounded-xl border border-gray-200 bg-white divide-y divide-gray-100">
            <div class="px-5 py-3">
                <h2 class="font-semibold text-gray-900">Pedidos dos alunos</h2>
            </div>
            <?php foreach ($pedidos_materiais as $pedido): ?>
                <?php $stPedido = (string) ($pedido['status'] ?? 'Pendente'); ?>
                <div class="px-5 py-3 text-sm space-y-2">
                    <div class="flex flex-wrap justify-between gap-2">
                        <div>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($pedido['titulo'] ?? '') ?>
                                <?php if (!empty($pedido['quantidade'])): ?>
                                    <span class="text-gray-500 font-normal"> · <?= htmlspecialchars($pedido['quantidade']) ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($pedido['aluno_nome'] ?? 'Aluno') ?>
                                <?php if (!empty($pedido['observacao'])): ?>
                                    · <?= htmlspecialchars($pedido['observacao']) ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($pedido['resposta_professor'])): ?>
                                <p class="text-xs text-gray-600 mt-1">Resposta: <?= htmlspecialchars($pedido['resposta_professor']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs h-fit <?= $badge($stPedido) ?>"><?= htmlspecialchars($stPedido) ?></span>
                    </div>
                    <?php if ($stPedido === 'Pendente'): ?>
                        <div class="flex flex-wrap gap-2">
                            <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/pedidos-materiais/decidir">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                                <input type="hidden" name="acao" value="aprovar">
                                <button type="submit" class="btn-primary-custom px-3 py-1.5 rounded-lg text-xs font-medium hover:opacity-90">Aprovar</button>
                            </form>
                            <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/pedidos-materiais/decidir" class="flex flex-wrap gap-2 items-center"
                                  onsubmit="var c=this.querySelector('[name=resposta]'); if(!c.value.trim()){alert('Informe o motivo da recusa'); return false;}">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                                <input type="hidden" name="acao" value="recusar">
                                <input type="text" name="resposta" placeholder="Motivo da recusa" class="border border-gray-300 rounded-lg px-2 py-1 text-xs bg-white">
                                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-300 text-gray-700 hover:bg-gray-50">Recusar</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php elseif ($aba === 'conteudos'): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-4">
            <div>
                <h2 class="font-semibold text-gray-900">Adicionar conteúdo</h2>
                <p class="text-sm text-gray-500 mt-1">Referências para os alunos: texto, anexo, link externo ou YouTube. Anexos até 20 MB.</p>
            </div>
            <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/conteudos" enctype="multipart/form-data" class="space-y-3 text-sm" id="expoConteudoForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div>
                    <label class="block text-gray-600 mb-1">Título</label>
                    <input type="text" name="titulo" required maxlength="255" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-gray-600 mb-1">Texto de referência</label>
                    <div class="flex flex-wrap gap-1 mb-2">
                        <button type="button" class="expo-editor-btn px-2 py-1 rounded border border-gray-300 text-xs" data-cmd="bold">B</button>
                        <button type="button" class="expo-editor-btn px-2 py-1 rounded border border-gray-300 text-xs italic" data-cmd="italic">I</button>
                        <button type="button" class="expo-editor-btn px-2 py-1 rounded border border-gray-300 text-xs" data-cmd="insertUnorderedList">Lista</button>
                    </div>
                    <div id="expoConteudoEditor" contenteditable="true" class="min-h-32 rounded-lg border border-gray-300 bg-white px-3 py-2 prose prose-sm max-w-none focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                    <textarea name="descricao_html" id="expoConteudoDescricao" class="hidden"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-600 mb-1">Anexo</label>
                        <input type="file" name="anexo" accept=".pdf,.doc,.docx,image/*,video/mp4,video/webm,video/quicktime" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <p class="text-xs text-gray-500 mt-1">PDF, Word, imagem ou vídeo até 20 MB.</p>
                    </div>
                    <div>
                        <label class="block text-gray-600 mb-1">Link externo</label>
                        <input type="url" name="link_externo" placeholder="https://..." class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    </div>
                    <div>
                        <label class="block text-gray-600 mb-1">Link do YouTube</label>
                        <input type="url" name="youtube_url" placeholder="https://youtube.com/..." class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    </div>
                </div>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Salvar conteúdo</button>
            </form>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white divide-y divide-gray-100">
            <?php if (empty($conteudos)): ?>
                <p class="px-5 py-6 text-sm text-gray-500">Nenhum conteúdo disponível ainda.</p>
            <?php else: foreach ($conteudos as $m): ?>
                <?php $meta = is_array($m['meta'] ?? null) ? $m['meta'] : (json_decode((string) ($m['visibilidade'] ?? ''), true) ?: []); ?>
                <div class="px-5 py-4 flex flex-wrap justify-between gap-3 text-sm">
                    <div class="min-w-0 space-y-1">
                        <p class="font-medium text-gray-900"><?= htmlspecialchars($m['titulo'] ?? '') ?></p>
                        <?php if (!empty($meta['descricao_html'])): ?>
                            <div class="text-gray-700 text-sm leading-relaxed"><?= $meta['descricao_html'] ?></div>
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
                    <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/conteudos/remover" onsubmit="return confirm('Remover conteúdo?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="conteudo_id" value="<?= (int) $m['id'] ?>">
                        <button type="submit" class="text-xs text-red-600 hover:underline">Remover</button>
                    </form>
                </div>
            <?php endforeach; endif; ?>
        </div>

    <?php else: /* stand */ ?>
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-4">
            <h2 class="font-semibold text-gray-900">Stand e QR público</h2>
            <p class="text-sm text-gray-600">Visitantes escaneiam o QR e veem só o resumo público (sem sobrenome, turma ou foto dos alunos).</p>
            <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= $pid ?>/stand" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div>
                    <label class="block text-gray-600 mb-1">Número do stand</label>
                    <input type="text" name="numero" value="<?= htmlspecialchars($stand['numero'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white" placeholder="A12">
                </div>
                <div>
                    <label class="block text-gray-600 mb-1">Setor</label>
                    <select name="setor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">—</option>
                        <?php foreach ($setores as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= ((int) ($stand['setor_id'] ?? 0) === (int) $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nome'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-600 mb-1">Horário da apresentação</label>
                    <input type="datetime-local" name="horario_apresentacao"
                           value="<?= !empty($stand['horario_apresentacao']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($stand['horario_apresentacao']))) : '' ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-gray-600 mb-1">Resumo público</label>
                    <textarea name="resumo_publico" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"><?= htmlspecialchars($stand['resumo_publico'] ?? ($projeto['produto_esperado'] ?? '')) ?></textarea>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">
                        <?= $stand ? 'Atualizar stand' : 'Gerar stand e QR' ?>
                    </button>
                </div>
            </form>
            <?php if ($url_qr): ?>
                <?php $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode((string) $url_qr); ?>
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 text-sm">
                    <div class="grid grid-cols-1 md:grid-cols-[160px,1fr] gap-4 items-center">
                        <div class="mx-auto md:mx-0 rounded-xl border border-slate-200 bg-white p-3">
                            <a href="<?= htmlspecialchars($url_qr) ?>" target="_blank" rel="noopener" title="Abrir página pública do QR">
                                <img src="<?= htmlspecialchars($qrImageUrl) ?>" alt="QR Code do stand" class="w-32 h-32 object-contain">
                            </a>
                        </div>
                        <div class="space-y-2 min-w-0">
                            <p class="font-medium text-gray-900">URL do QR</p>
                            <a href="<?= htmlspecialchars($url_qr) ?>" target="_blank" rel="noopener" class="text-accent break-all hover:underline"><?= htmlspecialchars($url_qr) ?></a>
                            <p class="text-xs text-gray-500">Escaneie o QR Code ou imprima para o dia do evento. Token: <code><?= htmlspecialchars($stand['qr_token'] ?? '') ?></code></p>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <a href="<?= htmlspecialchars($url_qr) ?>" target="_blank" rel="noopener"
                                   class="btn-primary-custom inline-flex items-center px-3 py-2 rounded-lg text-xs font-semibold hover:opacity-90">Abrir link</a>
                                <a href="<?= htmlspecialchars($qrImageUrl) ?>" target="_blank" rel="noopener"
                                   class="inline-flex items-center px-3 py-2 rounded-lg text-xs font-semibold border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Abrir QR Code</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var rows = document.getElementById('expoMateriaisRows');
    var add = document.getElementById('expoAddMaterialRow');

    function bindRemoveMaterialRows() {
        if (!rows) return;
        rows.querySelectorAll('.expo-remove-material').forEach(function (btn) {
            btn.onclick = function () {
                var allRows = rows.querySelectorAll('.expo-material-row');
                if (allRows.length <= 1) {
                    var row = btn.closest('.expo-material-row');
                    row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                    return;
                }
                btn.closest('.expo-material-row').remove();
            };
        });
    }

    if (add && rows) {
        add.addEventListener('click', function () {
            var row = document.createElement('div');
            row.className = 'expo-material-row grid grid-cols-1 md:grid-cols-12 gap-2 text-sm';
            row.innerHTML = '<input type="text" name="item_nome[]" placeholder="Item" class="md:col-span-5 border border-gray-300 rounded-lg px-3 py-2 bg-white">' +
                '<input type="text" name="item_quantidade[]" placeholder="Qtd." class="md:col-span-2 border border-gray-300 rounded-lg px-3 py-2 bg-white">' +
                '<input type="text" name="item_observacao[]" placeholder="Observação" class="md:col-span-4 border border-gray-300 rounded-lg px-3 py-2 bg-white">' +
                '<button type="button" class="expo-remove-material md:col-span-1 px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Remover</button>';
            rows.appendChild(row);
            bindRemoveMaterialRows();
        });
        bindRemoveMaterialRows();
    }

    var formConteudo = document.getElementById('expoConteudoForm');
    var editor = document.getElementById('expoConteudoEditor');
    var hidden = document.getElementById('expoConteudoDescricao');
    document.querySelectorAll('.expo-editor-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.execCommand(btn.dataset.cmd, false, null);
            if (editor) editor.focus();
        });
    });
    if (formConteudo && editor && hidden) {
        formConteudo.addEventListener('submit', function () {
            hidden.value = editor.innerHTML;
        });
    }
});
</script>
