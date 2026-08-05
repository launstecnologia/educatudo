<?php
$indicadores = $indicadores ?? ['ativos' => 0, 'inscricoes_pendentes' => 0];
$projetos = $projetos ?? [];
$pendentes = $pendentes ?? [];
$edicao = $edicao ?? null;
$csrf_token = $csrf_token ?? '';
?>
<div class="max-w-5xl mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Expo Colag</h1>
            <p class="text-sm text-gray-600 mt-1">Crie e acompanhe os projetos da feira.</p>
        </div>
        <a href="<?= URL ?>/professor/expo-colag/criar"
           class="btn-primary-custom inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium">
            <i class="fa-solid fa-plus"></i>
            Criar projeto
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="<?= URL ?>/professor/expo-colag/criar" class="rounded-xl border border-gray-200 bg-white p-6 hover:border-primary hover:shadow-md transition">
            <div class="flex items-center gap-3">
                <span class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                    <i class="fa-solid fa-lightbulb text-xl"></i>
                </span>
                <div>
                    <h2 class="font-semibold text-gray-900">Criar Projeto</h2>
                    <p class="text-sm text-gray-600">Wizard em 6 blocos com rascunho persistente</p>
                </div>
            </div>
        </a>
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="flex items-center gap-3">
                <span class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                    <i class="fa-solid fa-list-check text-xl"></i>
                </span>
                <div>
                    <h2 class="font-semibold text-gray-900">Acompanhar Projetos</h2>
                    <p class="text-sm text-gray-600">Tarefas, materiais, stand e QR</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Projetos ativos</p>
            <p class="text-2xl font-bold text-gray-900"><?= (int) ($indicadores['ativos'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Tarefas atrasadas</p>
            <p class="text-2xl font-bold text-gray-900"><?= (int) ($indicadores['tarefas_atrasadas'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Entregas a avaliar</p>
            <p class="text-2xl font-bold text-gray-900"><?= (int) ($indicadores['entregas_avaliar'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Inscrições pendentes</p>
            <p class="text-2xl font-bold text-gray-900"><?= (int) ($indicadores['inscricoes_pendentes'] ?? 0) ?></p>
        </div>
        <?php if (!empty($edicao['data_evento'])): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Data do evento</p>
            <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars(date('d/m/Y', strtotime($edicao['data_evento']))) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($pendentes)): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50/40 overflow-hidden">
        <div class="px-6 py-4 border-b border-amber-100">
            <h2 class="text-lg font-semibold text-amber-900">Inscrições aguardando aprovação</h2>
        </div>
        <ul class="divide-y divide-amber-100">
            <?php foreach ($pendentes as $pend): ?>
            <li class="px-6 py-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                <div>
                    <span class="font-medium text-gray-900"><?= htmlspecialchars($pend['aluno_nome'] ?? '') ?></span>
                    <span class="text-gray-500"> → <?= htmlspecialchars($pend['projeto_titulo'] ?? '') ?></span>
                </div>
                <div class="flex gap-2">
                    <form method="post" action="<?= URL ?>/professor/expo-colag/projetos/<?= (int) $pend['projeto_id'] ?>/inscricoes/decidir">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="inscricao_id" value="<?= (int) $pend['id'] ?>">
                        <input type="hidden" name="decisao" value="aprovar">
                        <input type="hidden" name="voltar" value="index">
                        <button type="submit" class="text-emerald-700 font-medium hover:underline">Aprovar</button>
                    </form>
                    <a href="<?= URL ?>/professor/expo-colag/projetos/<?= (int) $pend['projeto_id'] ?>/acompanhar" class="text-primary hover:underline">Ver</a>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Meus projetos</h2>
        </div>
        <?php if (empty($projetos)): ?>
            <p class="px-6 py-8 text-sm text-gray-500">Nenhum projeto ainda. <a href="<?= URL ?>/professor/expo-colag/criar" class="text-primary font-medium">Criar o primeiro</a>.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-6 py-3 font-medium">Título</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium">Vagas</th>
                            <th class="px-6 py-3 font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($projetos as $p): ?>
                        <tr>
                            <td class="px-6 py-3 font-medium text-gray-900"><?= htmlspecialchars($p['titulo'] ?? '') ?></td>
                            <td class="px-6 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-700">
                                    <?= htmlspecialchars(str_replace('_', ' ', $p['status'] ?? '')) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 text-gray-600"><?= (int) ($p['vagas_totais'] ?? 0) ?></td>
                            <td class="px-6 py-3">
                                <a href="<?= URL ?>/professor/expo-colag/projetos/<?= (int) $p['id'] ?>/acompanhar" class="text-primary font-medium hover:underline">Acompanhar</a>
                                <span class="text-gray-300 mx-1">·</span>
                                <a href="<?= URL ?>/professor/expo-colag/projetos/<?= (int) $p['id'] ?>/editar" class="text-gray-600 hover:underline">Editar</a>
                                <span class="text-gray-300 mx-1">·</span>
                                <a href="<?= URL ?>/professor/expo-colag/projetos/<?= (int) $p['id'] ?>/preview" class="text-gray-600 hover:underline">Preview</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
