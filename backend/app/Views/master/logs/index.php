<?php
$arquivos = $arquivos ?? [];
$arquivo = (string) ($arquivo ?? 'todos');
$linhas = $linhas ?? [];
$busca = (string) ($busca ?? '');
$limite = (int) ($limite ?? 100);
$diagnostico = $diagnostico ?? ['diretorio' => '', 'gravavel' => false, 'php_error_log' => '', 'arquivos' => []];
$erros = $erros_distintos ?? [];
if ($erros === []) {
    $erros = array_values(array_filter($linhas, static fn ($l) => !empty($l['eh_erro']) || ($l['mensagem'] ?? '') !== ''));
}
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-900 mb-1">Logs do sistema</h2>
    <p class="text-slate-500 text-sm">Últimas linhas de <code>storage/logs</code> (php-error.log, error.log, app_*.log). Atualize depois de reproduzir o 500.</p>
    <p class="text-xs text-slate-400 mt-2 font-mono break-all">
        <?= htmlspecialchars((string) ($diagnostico['diretorio'] ?? '')) ?>
        · <?= !empty($diagnostico['gravavel']) ? 'gravável' : 'sem escrita' ?>
        · PHP error_log=<?= htmlspecialchars((string) ($diagnostico['php_error_log'] ?? '(vazio)')) ?>
        · <?= count($diagnostico['arquivos'] ?? []) ?> arquivo(s)
    </p>
</div>

<form method="GET" action="<?= URL ?>/master/logs" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Arquivo</label>
        <select name="arquivo" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
            <?php foreach ($arquivos as $item): ?>
            <option value="<?= htmlspecialchars((string) $item['chave']) ?>" <?= $arquivo === $item['chave'] ? 'selected' : '' ?>>
                <?= htmlspecialchars((string) $item['rotulo']) ?>
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
        <button type="submit" class="self-end px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 whitespace-nowrap">Atualizar</button>
    </div>
    <div class="sm:col-span-4 flex flex-wrap gap-2">
        <?php
        $qsBase = ['arquivo' => $arquivo, 'limite' => $limite];
        $atalhos = [
            '/admin' => 'Admin',
            '/admin/arquivos' => 'Arquivos',
            'xdq' => 'xdq',
            '/aluno' => 'Aluno',
            'SQLSTATE' => 'SQLSTATE',
        ];
        foreach ($atalhos as $valor => $rotulo):
            $href = URL . '/master/logs?' . http_build_query($qsBase + ['busca' => $valor]);
        ?>
        <a href="<?= htmlspecialchars($href) ?>"
           class="px-2.5 py-1 rounded-full text-xs font-medium border <?= $busca === $valor ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300' ?>">
            <?= htmlspecialchars($rotulo) ?>
        </a>
        <?php endforeach; ?>
        <?php if ($busca !== ''): ?>
        <a href="<?= htmlspecialchars(URL . '/master/logs?' . http_build_query($qsBase)) ?>" class="px-2.5 py-1 rounded-full text-xs font-medium text-slate-500 hover:text-slate-800">Limpar busca</a>
        <?php endif; ?>
    </div>
</form>

<?php if (!empty($erros)): ?>
<div class="mb-6 space-y-3">
    <h3 class="text-sm font-semibold text-red-700">Erros distintos (admin primeiro)</h3>
    <?php foreach (array_slice($erros, 0, 15) as $erro): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex flex-wrap gap-2 text-xs mb-2">
            <?php if (($erro['escola'] ?? '') !== ''): ?>
                <span class="px-2 py-0.5 rounded-full bg-white border border-red-200 text-red-800 font-medium"><?= htmlspecialchars($erro['escola']) ?></span>
            <?php endif; ?>
            <?php if (($erro['tipo'] ?? '') !== ''): ?>
                <span class="px-2 py-0.5 rounded-full bg-white border border-red-200 text-slate-700"><?= htmlspecialchars($erro['tipo']) ?></span>
            <?php endif; ?>
            <?php if (($erro['arquivo'] ?? '') !== ''): ?>
                <span class="px-2 py-0.5 rounded-full bg-white border border-red-200 font-mono text-slate-600"><?= htmlspecialchars($erro['arquivo']) ?></span>
            <?php endif; ?>
        </div>
        <?php if (($erro['url'] ?? '') !== ''): ?>
            <p class="text-xs text-slate-600 mb-2 break-all"><?= htmlspecialchars($erro['url']) ?></p>
        <?php endif; ?>
        <p class="text-sm text-red-900 font-medium whitespace-pre-wrap break-all"><?= htmlspecialchars((string) (($erro['mensagem'] ?? '') !== '' ? $erro['mensagem'] : ($erro['texto'] ?? ''))) ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-4">
    <p class="text-xs text-slate-500">Linhas lidas</p>
    <p class="text-2xl font-bold mt-1 text-slate-900"><?= count($linhas) ?> <span class="text-base font-medium text-slate-500">/ <?= (int) $limite ?></span></p>
    <p class="text-xs text-slate-400 mt-1 font-mono"><?= htmlspecialchars($arquivo) ?></p>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
    <?php if (empty($linhas)): ?>
        <div class="p-6 text-sm text-slate-600 space-y-2">
            <p class="font-medium text-slate-800">Nenhum log encontrado para exibir.</p>
            <p>Pasta: <code class="text-xs"><?= htmlspecialchars((string) ($diagnostico['diretorio'] ?? '')) ?></code>
                <?= !empty($diagnostico['gravavel']) ? '(gravável)' : '(sem permissão de escrita)' ?></p>
            <p>PHP <code>error_log</code>: <code class="text-xs"><?= htmlspecialchars((string) ($diagnostico['php_error_log'] ?? '')) ?></code></p>
            <?php if (empty($diagnostico['arquivos'])): ?>
                <p>Não há arquivos <code>.log</code> nessa pasta. O PHP pode estar gravando só no log do container (<code>docker logs php_app_educatudo</code>).</p>
            <?php else: ?>
                <p>Arquivos no disco:</p>
                <ul class="list-disc pl-5 font-mono text-xs">
                    <?php foreach ($diagnostico['arquivos'] as $arq): ?>
                    <li><?= htmlspecialchars($arq['nome']) ?> — <?= (int) $arq['bytes'] ?> bytes</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Arquivo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Log</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($linhas as $linha): ?>
                    <tr class="align-top <?= !empty($linha['eh_erro']) ? 'bg-red-50/60' : '' ?>">
                        <td class="px-4 py-3 text-sm font-medium text-slate-800 whitespace-nowrap">
                            <?= ($linha['escola'] ?? '') !== '' ? htmlspecialchars($linha['escola']) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-500 whitespace-nowrap">
                            <?= htmlspecialchars((string) ($linha['arquivo'] ?? '')) ?>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-700 break-all whitespace-pre-wrap">
                            <?php if (($linha['url'] ?? '') !== ''): ?>
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
