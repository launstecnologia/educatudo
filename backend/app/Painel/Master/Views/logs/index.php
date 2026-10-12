<?php
$arquivos = $arquivos ?? [];
$arquivo = (string) ($arquivo ?? 'todos');
$linhas = $linhas ?? [];
$busca = (string) ($busca ?? '');
$limite = (int) ($limite ?? 500);
$diagnostico = $diagnostico ?? ['diretorio' => '', 'gravavel' => false, 'php_error_log' => '', 'arquivos' => []];
$erros = $erros_distintos ?? [];
if ($erros === []) {
    $erros = array_values(array_filter($linhas, static fn ($l) => !empty($l['eh_erro']) || ($l['mensagem'] ?? '') !== ''));
}
$flash = $flash ?? ['message' => null, 'type' => 'info'];
$csrfToken = (string) ($csrf_token ?? ($_SESSION['csrf_token'] ?? ''));
$arquivosDisco = $diagnostico['arquivos'] ?? [];
?>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg border <?php
    $tipoFlash = (string) ($flash['type'] ?? 'info');
    echo $tipoFlash === 'error' ? 'bg-red-50 border-red-200 text-red-800'
        : ($tipoFlash === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-blue-50 border-blue-200 text-blue-800');
?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-900 mb-1">Logs do sistema</h2>
    <p class="text-slate-500 text-sm">Todos os registros de <code>storage/logs</code> (incluindo <code>app.data.log</code> e <code>app_*.log</code>), com horário. Atualize depois de reproduzir o 500.</p>
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
            <?php foreach ([200, 500, 1000, 2000] as $n): ?>
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

<?php if (!empty($arquivosDisco)): ?>
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <h3 class="text-sm font-semibold text-slate-800">Arquivos no disco</h3>
        <form method="POST" action="<?= URL ?>/master/logs/excluir" onsubmit="return confirm('Excluir TODOS os <?= count($arquivosDisco) ?> arquivos de log desta pasta?');">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="arquivo" value="todos">
            <button type="submit" class="px-3 py-1.5 border border-red-300 text-red-700 bg-white rounded-lg text-xs font-medium hover:bg-red-50">Excluir todos</button>
        </form>
    </div>
    <ul class="divide-y divide-slate-100">
        <?php foreach ($arquivosDisco as $arq): ?>
        <li class="flex items-center justify-between gap-3 py-2">
            <div class="min-w-0">
                <p class="text-sm font-mono text-slate-800 truncate"><?= htmlspecialchars((string) $arq['nome']) ?></p>
                <p class="text-xs text-slate-400"><?= number_format((int) ($arq['bytes'] ?? 0) / 1024, 1, ',', '.') ?> KB</p>
            </div>
            <form method="POST" action="<?= URL ?>/master/logs/excluir" class="shrink-0" onsubmit="return confirm('Excluir <?= htmlspecialchars((string) $arq['nome'], ENT_QUOTES) ?>?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="arquivo" value="<?= htmlspecialchars((string) $arq['nome']) ?>">
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 rounded-lg">Excluir</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($erros)): ?>
<div class="mb-6 space-y-3">
    <h3 class="text-sm font-semibold text-red-700">Erros distintos (admin primeiro)</h3>
    <?php foreach ($erros as $erro): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex flex-wrap gap-2 text-xs mb-2">
            <?php if (($erro['horario'] ?? '') !== ''): ?>
                <span class="px-2 py-0.5 rounded-full bg-white border border-red-200 text-slate-800 font-mono"><?= htmlspecialchars($erro['horario']) ?></span>
            <?php endif; ?>
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
    <?php if (count($linhas) >= $limite): ?>
    <p class="text-xs text-amber-700 mt-2">Mostrando os <?= (int) $limite ?> registros mais recentes. Aumente a quantidade ou filtre por arquivo/busca.</p>
    <?php endif; ?>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Horário</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Arquivo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">URL</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Log</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($linhas as $linha): ?>
                    <tr class="align-top <?= !empty($linha['eh_erro']) ? 'bg-red-50/60' : '' ?>">
                        <td class="px-4 py-3 text-xs font-mono text-slate-800 whitespace-nowrap">
                            <?= ($linha['horario'] ?? '') !== '' ? htmlspecialchars($linha['horario']) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-800 whitespace-nowrap">
                            <?= ($linha['escola'] ?? '') !== '' ? htmlspecialchars($linha['escola']) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-500 whitespace-nowrap">
                            <?= htmlspecialchars((string) ($linha['arquivo'] ?? '')) ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600 break-all max-w-xs">
                            <?= ($linha['url'] ?? '') !== '' ? htmlspecialchars($linha['url']) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-700 break-all whitespace-pre-wrap">
                            <?= htmlspecialchars((string) ($linha['texto'] ?? '')) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
