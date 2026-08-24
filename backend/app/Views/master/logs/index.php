<?php
$arquivos = $arquivos ?? [];
$arquivo = (string) ($arquivo ?? 'error.log');
$linhas = $linhas ?? [];
$busca = (string) ($busca ?? '');
$limite = (int) ($limite ?? 100);
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-900 mb-1">Logs do sistema</h2>
    <p class="text-slate-500 text-sm">Últimas linhas de <code>backend/storage/logs</code> — compartilhado entre todas as escolas. Use para ver 500s como o do X da Questão.</p>
</div>

<form method="GET" action="<?= URL ?>/master/logs" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Arquivo</label>
        <select name="arquivo" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
            <?php foreach ($arquivos as $item): ?>
            <option value="<?= htmlspecialchars((string) $item['chave']) ?>" <?= $arquivo === $item['chave'] ? 'selected' : '' ?>>
                <?= htmlspecialchars((string) $item['rotulo']) ?><?= empty($item['existe']) ? ' (vazio)' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Quantidade</label>
        <select name="limite" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
            <?php foreach ([50, 100, 200] as $n): ?>
            <option value="<?= $n ?>" <?= $limite === $n ? 'selected' : '' ?>><?= $n ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="sm:col-span-2 flex gap-3">
        <div class="flex-1">
            <label class="block text-xs font-medium text-slate-500 mb-1">Buscar (escola, URL, erro)</label>
            <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="xdq, EXCEÇÃO, alertas_sensiveis…">
        </div>
        <button type="submit" class="self-end px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 whitespace-nowrap">Filtrar</button>
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-4">
    <p class="text-xs text-slate-500">Mostrando</p>
    <p class="text-2xl font-bold mt-1 text-slate-900"><?= count($linhas) ?> <span class="text-base font-medium text-slate-500">de <?= (int) $limite ?></span></p>
    <p class="text-xs text-slate-400 mt-1 font-mono"><?= htmlspecialchars($arquivo) ?></p>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($linhas)): ?>
        <p class="p-6 text-sm text-slate-500">Nenhuma linha neste arquivo (ou o arquivo ainda não existe neste ambiente).</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Log</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($linhas as $linha): ?>
                    <tr class="align-top">
                        <td class="px-4 py-3 text-sm font-medium text-slate-800 whitespace-nowrap">
                            <?= $linha['escola'] !== '' ? htmlspecialchars($linha['escola']) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">
                            <?= $linha['tipo'] !== '' ? htmlspecialchars($linha['tipo']) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-700 break-all whitespace-pre-wrap">
                            <?php if ($linha['url'] !== ''): ?>
                                <p class="text-slate-500 mb-1"><?= htmlspecialchars($linha['url']) ?></p>
                            <?php endif; ?>
                            <?= htmlspecialchars($linha['texto']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
