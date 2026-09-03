<?php
$projeto = $projeto ?? [];
$relacoes = $relacoes ?? [];
$status = $status_inscricao ?? [];
$csrf_token = $csrf_token ?? '';
$objetivos = $relacoes['objetivos'] ?? [];
$etapas = $relacoes['etapas'] ?? [];
$materiais = $relacoes['materiais'] ?? [];
$papeis = $relacoes['papeis'] ?? [];
$insc = $status['inscricao'] ?? null;
$conflitos = $status['conflitos'] ?? ['bloqueios' => [], 'alertas' => [], 'infos' => []];
$vagasRest = (int) ($status['vagas_restantes'] ?? 0);
?>
<div class="max-w-3xl mx-auto px-4 py-6 space-y-6 pb-28">
    <a href="<?= URL ?>/expo-colag" class="text-sm text-accent hover:underline">← Voltar ao mural</a>

    <div class="rounded-xl border border-gray-200 bg-white p-6 space-y-4">
        <?php
        $capaSrc = (string) ($projeto['capa_src'] ?? ExpoColagService::resolverUrlCapa((string) ($projeto['capa_url'] ?? ''), (int) ($projeto['id'] ?? 0)));
        if ($capaSrc !== ''): ?>
            <img src="<?= htmlspecialchars($capaSrc) ?>" alt="" class="w-full aspect-[3/1] object-cover rounded-lg">
        <?php endif; ?>
        <div>
            <?php if (!empty($projeto['area'])): ?>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500"><?= htmlspecialchars($projeto['area']) ?></p>
            <?php endif; ?>
            <h1 class="text-2xl font-bold text-gray-900 mt-1"><?= htmlspecialchars($projeto['titulo'] ?? '') ?></h1>
            <?php if (!empty($projeto['subtitulo'])): ?>
                <p class="text-gray-600 mt-1"><?= htmlspecialchars($projeto['subtitulo']) ?></p>
            <?php endif; ?>
        </div>

        <p class="text-sm text-gray-600">Orientador: <?= htmlspecialchars($projeto['professor_nome'] ?? '—') ?></p>

        <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Modalidade</p>
                <p class="font-medium text-gray-800"><?= htmlspecialchars(str_replace('_', ' ', $projeto['modalidade'] ?? 'Grupo')) ?></p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Vagas restantes</p>
                <p class="font-medium text-gray-800"><?= !empty($status['lotado']) ? 'LOTADO' : $vagasRest ?></p>
            </div>
        </div>

        <?php if (!empty($projeto['descricao'])): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-1">Proposta</h2>
                <div class="text-gray-700 whitespace-pre-wrap text-sm"><?= htmlspecialchars($projeto['descricao']) ?></div>
            </div>
        <?php endif; ?>

        <?php
        $tiposAluno = array_column($relacoes['tipos_trabalho'] ?? [], 'tipo');
        if ($tiposAluno || !empty($projeto['produto_esperado'])): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-1">O que apresentaremos</h2>
                <?php if ($tiposAluno): ?>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <?php foreach ($tiposAluno as $tipo): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-800 text-xs font-medium"><?= htmlspecialchars((string) $tipo) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($projeto['produto_esperado'])): ?>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($projeto['produto_esperado']) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($objetivos): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-1">Objetivos</h2>
                <ul class="list-disc pl-5 text-sm text-gray-700 space-y-1">
                    <?php foreach ($objetivos as $o): ?>
                        <li><?= htmlspecialchars($o['texto'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($etapas): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-2">Cronograma</h2>
                <ol class="space-y-2">
                    <?php foreach ($etapas as $et): ?>
                        <li class="text-sm border border-gray-100 rounded-lg px-3 py-2 space-y-1">
                            <div>
                                <span class="font-medium"><?= htmlspecialchars($et['titulo'] ?? '') ?></span>
                                <?php if (!empty($et['data_limite'])): ?>
                                    <span class="text-gray-500"> · até <?= htmlspecialchars(date('d/m/Y', strtotime($et['data_limite']))) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($et['descricao'])): ?>
                                <p class="text-gray-600 whitespace-pre-wrap"><?= htmlspecialchars($et['descricao']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($et['entregavel_esperado'])): ?>
                                <p class="text-gray-700"><span class="font-medium">Entregável:</span> <?= htmlspecialchars($et['entregavel_esperado']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>

        <?php if (!empty($projeto['briefing_entrega'])): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-1">Anotações do grupo</h2>
                <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($projeto['briefing_entrega']) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($projeto['educalabs_ativa']) || !empty($projeto['tudinha_ativa'])): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-2">Pesquisa e ideias</h2>
                <div class="flex flex-wrap gap-2">
                    <?php if (!empty($projeto['educalabs_ativa'])): ?>
                        <a href="<?= URL ?>/educalabs/access" class="btn-primary-custom inline-flex px-3 py-1.5 rounded-lg text-sm font-semibold hover:opacity-90">EducaLabs</a>
                    <?php endif; ?>
                    <?php if (!empty($projeto['tudinha_ativa'])): ?>
                        <a href="<?= URL ?>/chat" class="btn-primary-custom inline-flex px-3 py-1.5 rounded-lg text-sm font-semibold hover:opacity-90">Tudinha</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($materiais): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-2">Materiais</h2>
                <ul class="text-sm space-y-1">
                    <?php foreach ($materiais as $m): ?>
                        <li>
                            <?php $href = $m['link_externo'] ?? $m['arquivo_url'] ?? ''; ?>
                            <?php if ($href): ?>
                                <a href="<?= htmlspecialchars($href) ?>" target="_blank" rel="noopener" class="text-accent hover:underline"><?= htmlspecialchars($m['titulo'] ?? 'Material') ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($m['titulo'] ?? 'Material') ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($conflitos['infos'])): ?>
            <div class="rounded-lg bg-sky-50 border border-sky-100 text-sky-800 text-sm px-3 py-2">
                <?= htmlspecialchars(implode(' ', $conflitos['infos'])) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- CTA fixo -->
<div class="fixed bottom-0 inset-x-0 bg-white/95 border-t border-gray-200 backdrop-blur z-40">
    <div class="max-w-3xl mx-auto px-4 py-3">
        <?php if ($insc && in_array($insc['status'] ?? '', ['Aguardando', 'Aprovada', 'Lista_espera'], true)): ?>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-700">
                    Status:
                    <span class="font-semibold"><?= htmlspecialchars(str_replace('_', ' ', $insc['status'])) ?></span>
                </p>
                <div class="flex flex-wrap gap-2">
                    <?php if (($insc['status'] ?? '') === 'Aprovada'): ?>
                        <a href="<?= URL ?>/expo-colag/projeto/<?= (int) $projeto['id'] ?>/painel"
                           class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Meu painel</a>
                    <?php endif; ?>
                    <form method="post" action="<?= URL ?>/expo-colag/projeto/<?= (int) $projeto['id'] ?>/cancelar-inscricao"
                          onsubmit="return confirm('Deseja realmente cancelar sua inscrição?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="inscricao_id" value="<?= (int) $insc['id'] ?>">
                        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar inscrição</button>
                    </form>
                </div>
            </div>
        <?php elseif (!empty($status['pode_inscrever'])): ?>
            <form method="post" action="<?= URL ?>/expo-colag/projeto/<?= (int) $projeto['id'] ?>/inscrever" class="space-y-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php if (!empty($status['exige_justificativa'])): ?>
                    <textarea name="justificativa" required rows="2" placeholder="Por que você quer participar?"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm"></textarea>
                <?php endif; ?>
                <?php if ($papeis): ?>
                    <select name="papel_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">
                        <option value="">Papel (opcional)</option>
                        <?php foreach ($papeis as $papel): ?>
                            <option value="<?= (int) $papel['id'] ?>"><?= htmlspecialchars($papel['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?php if (!empty($conflitos['alertas'])): ?>
                    <label class="flex items-start gap-2 text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                        <input type="checkbox" name="confirmar_apresentacao" value="1" required class="mt-1">
                        <span><?= htmlspecialchars($conflitos['alertas'][0]) ?></span>
                    </label>
                <?php endif; ?>
                <?php if (!empty($status['lotado'])): ?>
                    <p class="text-xs text-amber-700">Projeto lotado — você entrará na lista de espera.</p>
                <?php endif; ?>
                <button type="submit" class="w-full btn-primary-custom px-4 py-3 rounded-lg text-sm font-semibold">
                    Quero participar
                </button>
            </form>
        <?php else: ?>
            <p class="text-sm text-gray-600 text-center py-1">
                <?= htmlspecialchars($status['motivo'] ?? 'Inscrições indisponíveis no momento.') ?>
            </p>
        <?php endif; ?>
    </div>
</div>
