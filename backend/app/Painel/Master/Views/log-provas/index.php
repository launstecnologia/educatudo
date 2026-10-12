<?php
$eventos = $eventos ?? [];
$escolas = $escolas ?? [];
$tiposEvento = $tipos_evento ?? [];
$resumo = $resumo ?? ['total' => 0];
$filtros = $filtros ?? ['escola_id' => 0, 'tipo_evento' => '', 'busca' => ''];
$paginacao = $paginacao ?? ['page' => 1, 'total_pages' => 1, 'total' => 0, 'per_page' => 30];

$tipoMap = [
    'erro_sessao' => ['label' => 'Erro de sessão', 'class' => 'bg-red-100 text-red-800'],
    'erro_salvar_resposta' => ['label' => 'Erro ao salvar resposta', 'class' => 'bg-red-100 text-red-800'],
    'erro_finalizar' => ['label' => 'Erro ao finalizar', 'class' => 'bg-red-100 text-red-800'],
    'saida_modo_seguro' => ['label' => 'Saiu do modo seguro', 'class' => 'bg-amber-100 text-amber-800'],
    'tentativa_sair_tela_cheia' => ['label' => 'Tentou sair da tela cheia', 'class' => 'bg-amber-100 text-amber-800'],
    'tentativa_atualizar_pagina' => ['label' => 'Tentou atualizar a página', 'class' => 'bg-amber-100 text-amber-800'],
    'tentativa_voltar_navegador' => ['label' => 'Tentou voltar no navegador', 'class' => 'bg-amber-100 text-amber-800'],
    'outro' => ['label' => 'Outro', 'class' => 'bg-slate-100 text-slate-600'],
];

$queryBase = array_filter([
    'escola_id' => !empty($filtros['escola_id']) ? (int) $filtros['escola_id'] : null,
    'tipo_evento' => $filtros['tipo_evento'] !== '' ? $filtros['tipo_evento'] : null,
    'busca' => $filtros['busca'] !== '' ? $filtros['busca'] : null,
], static fn($v) => $v !== null && $v !== '');
?>

<?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
<?php if (!empty($flash_msg)): ?>
<div class="mb-6 px-4 py-3 rounded-lg border <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
    <?= htmlspecialchars($flash_msg) ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-900 mb-1">Log de Provas</h2>
    <p class="text-slate-500 text-sm">Anomalias durante provas online — erro de sessão/resposta/finalização e tentativas de burlar o modo seguro. Não lista o fluxo normal (aluno respondendo, avançando, finalizando sem erro).</p>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Escola</label>
        <select name="escola_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            <option value="0">Todas</option>
            <?php foreach ($escolas as $e): ?>
            <option value="<?= (int) $e['id'] ?>" <?= (int) $filtros['escola_id'] === (int) $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['nome'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Tipo de evento</label>
        <select name="tipo_evento" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Todos</option>
            <?php foreach ($tiposEvento as $tp): ?>
            <option value="<?= htmlspecialchars($tp) ?>" <?= $filtros['tipo_evento'] === $tp ? 'selected' : '' ?>><?= htmlspecialchars($tipoMap[$tp]['label'] ?? $tp) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="sm:col-span-2 flex gap-3">
        <div class="flex-1">
            <label class="block text-xs font-medium text-slate-500 mb-1">Buscar (aluno, prova, detalhe)</label>
            <input type="text" name="busca" value="<?= htmlspecialchars($filtros['busca']) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Nome do aluno, título da prova...">
        </div>
        <button type="submit" class="self-end px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 whitespace-nowrap">Filtrar</button>
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6">
    <p class="text-xs text-slate-500">Total de eventos (com os filtros atuais)</p>
    <p class="text-2xl font-bold mt-1 text-slate-900"><?= (int) $resumo['total'] ?></p>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Evento</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Aluno / Escola</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Prova / Bloco</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Detalhe</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Quando</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($eventos)): ?>
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">Nenhum evento registrado com esses filtros.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($eventos as $ev):
                    $tp = $tipoMap[$ev['tipo_evento'] ?? ''] ?? $tipoMap['outro'];
                ?>
                <tr class="hover:bg-slate-50 align-top">
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full <?= $tp['class'] ?>"><?= $tp['label'] ?></span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <span class="block font-medium text-slate-900 truncate max-w-[16rem]"><?= htmlspecialchars($ev['aluno_nome'] ?? '') ?: '<span class="text-slate-400">não identificado</span>' ?></span>
                        <span class="block text-xs text-slate-500 mt-0.5 truncate max-w-[16rem]"><?= htmlspecialchars($ev['escola_nome'] ?? '') ?></span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <span class="block truncate max-w-[14rem]"><?= htmlspecialchars($ev['prova_titulo'] ?? '') ?: '—' ?></span>
                        <?php if (!empty($ev['bloco_titulo'])): ?>
                        <span class="block text-xs text-slate-500 mt-0.5 truncate max-w-[14rem]">Bloco: <?= htmlspecialchars($ev['bloco_titulo']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 max-w-md">
                        <span class="block whitespace-normal break-words"><?= htmlspecialchars($ev['detalhe'] ?? '') ?: '—' ?></span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-500 whitespace-nowrap">
                        <?= !empty($ev['created_at']) ? date('d/m/Y H:i', strtotime($ev['created_at'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ((int) $paginacao['total_pages'] > 1): ?>
    <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between gap-3">
        <p class="text-xs text-slate-500">
            Página <?= (int) $paginacao['page'] ?> de <?= (int) $paginacao['total_pages'] ?>
            (<?= (int) $paginacao['total'] ?> eventos)
        </p>
        <div class="flex gap-2">
            <?php if ((int) $paginacao['page'] > 1):
                $prev = http_build_query(array_merge($queryBase, ['page' => (int) $paginacao['page'] - 1]));
            ?>
            <a href="?<?= htmlspecialchars($prev) ?>" class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Anterior</a>
            <?php endif; ?>
            <?php if ((int) $paginacao['page'] < (int) $paginacao['total_pages']):
                $next = http_build_query(array_merge($queryBase, ['page' => (int) $paginacao['page'] + 1]));
            ?>
            <a href="?<?= htmlspecialchars($next) ?>" class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
