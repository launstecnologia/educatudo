<?php
$migrationFiles = $migration_files ?? [];
$cargasFiles = $cargas_files ?? [];
$totalMigrations = $total_migrations ?? 0;
$escolasData = $escolas ?? [];
$totalEscolas = $total_escolas ?? 0;
$totalPendentes = $total_pendentes ?? 0;
$masterMigrationFiles = $master_migration_files ?? [];
$masterExecutadas = $master_executadas ?? [];
$masterPendentes = $master_pendentes ?? [];
$totalMasterFiles = count($masterMigrationFiles);
$totalMasterPendentes = count($masterPendentes);
$totalCargas = count($cargasFiles);
$schemaFilesOnly = array_values(array_filter($migrationFiles, static function ($f) {
    return stripos((string) $f, 'importar_dados_') === false;
}));
?>

<div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4">
    <p class="text-sm font-semibold text-slate-900 mb-1">Como funciona esta página</p>
    <p class="text-sm text-slate-600">
        Existem <strong>dois tipos de banco</strong>: o <strong>Master</strong> (cadastro de escolas, faturamento, catálogos da plataforma)
        e o banco de <strong>cada escola</strong> (alunos, provas, jornadas…). Cada um tem suas migrations — não misture.
    </p>
    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
        <a href="#secao-master" class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2.5 hover:bg-amber-50">
            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white">1</span>
            <span>
                <span class="font-semibold text-amber-900">Banco Master</span>
                <span class="block text-amber-800/80 text-xs mt-0.5">Arquivos <code class="bg-white/80 px-1 rounded">*_master.sql</code> · roda só 1 vez, no banco da plataforma</span>
            </span>
        </a>
        <a href="#secao-escolas" class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50/80 px-3 py-2.5 hover:bg-blue-50">
            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">2</span>
            <span>
                <span class="font-semibold text-blue-900">Bancos das escolas</span>
                <span class="block text-blue-800/80 text-xs mt-0.5">Arquivos sem <code class="bg-white/80 px-1 rounded">_master</code> · precisa rodar em cada escola</span>
            </span>
        </a>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm p-5 border border-amber-200">
        <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Master · pendentes</p>
        <p class="text-2xl font-bold mt-1 <?= $totalMasterPendentes > 0 ? 'text-amber-600' : 'text-green-600' ?>"><?= (int) $totalMasterPendentes ?></p>
        <p class="text-xs text-slate-500 mt-1"><?= (int) $totalMasterFiles ?> arquivo(s) *_master.sql</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-blue-200">
        <p class="text-xs font-medium uppercase tracking-wide text-blue-700">Escolas · schema</p>
        <p class="text-2xl font-bold text-blue-600 mt-1"><?= (int) $totalMigrations ?></p>
        <p class="text-xs text-slate-500 mt-1"><?= (int) $totalCargas ?> seed(s) à parte</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-slate-200">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Escolas com banco</p>
        <p class="text-2xl font-bold text-slate-700 mt-1"><?= (int) $totalEscolas ?></p>
        <p class="text-xs text-slate-500 mt-1">Um MySQL por escola</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border <?= $totalPendentes > 0 ? 'border-red-200' : 'border-green-200' ?>">
        <p class="text-xs font-medium uppercase tracking-wide <?= $totalPendentes > 0 ? 'text-red-700' : 'text-green-700' ?>">Pendências nas escolas</p>
        <p class="text-2xl font-bold mt-1 <?= $totalPendentes > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= (int) $totalPendentes ?></p>
        <p class="text-xs text-slate-500 mt-1">Soma de schema pendente (todas as escolas)</p>
    </div>
</div>

<div id="aviso-migration-finalizada" class="mb-6 hidden rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm font-medium text-green-900" role="status"></div>
<div id="resultado-execucao" class="mb-6 hidden"></div>

<!-- ========== 1. BANCO MASTER ========== -->
<section id="secao-master" class="mb-10 scroll-mt-6">
    <div class="flex items-center gap-2 mb-3">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-white">1</span>
        <h2 class="text-xl font-bold text-slate-900">Banco Master</h2>
        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">plataforma</span>
    </div>
    <p class="text-sm text-slate-600 mb-4 max-w-3xl">
        Roda <strong>só no banco Master</strong> — escolas, planos, créditos, fila, etc.
        Arquivos terminam com <code class="bg-slate-100 px-1 rounded text-xs">_master.sql</code>.
        Não altera o banco de nenhuma escola.
    </p>

    <div class="bg-white rounded-xl shadow-sm border-2 border-amber-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-amber-100 bg-amber-50/60 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Arquivos *_master.sql</h3>
                <p class="text-xs text-slate-600 mt-0.5">
                    <?php if ($totalMasterPendentes > 0): ?>
                        <span class="text-amber-700 font-medium"><?= (int) $totalMasterPendentes ?> pendente(s)</span>
                        · <?= (int) ($totalMasterFiles - $totalMasterPendentes) ?> já executada(s)
                    <?php else: ?>
                        <span class="text-green-700 font-medium">Em dia</span>
                        · <?= (int) $totalMasterFiles ?> arquivo(s)
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <?php if (!empty($masterPendentes)): ?>
                <button type="button" id="btn-executar-master" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 font-medium text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Executar pendentes
                </button>
                <button type="button" id="btn-escolher-master" class="bg-white border border-amber-300 text-amber-900 px-4 py-2 rounded-lg hover:bg-amber-50 font-medium text-sm" data-pendentes="<?= htmlspecialchars(json_encode($masterPendentes)) ?>">
                    Escolher arquivos
                </button>
                <?php else: ?>
                <span class="px-2.5 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Nada pendente</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="px-6 py-4">
            <?php if (empty($masterMigrationFiles)): ?>
            <p class="text-sm text-slate-500">Nenhum arquivo *_master.sql em database/migrations.</p>
            <?php else: ?>
            <details class="group">
                <summary class="cursor-pointer text-sm font-medium text-slate-700 hover:text-slate-900 list-none flex items-center gap-2">
                    <span class="text-slate-400 group-open:rotate-90 transition-transform">▸</span>
                    Ver lista (<?= (int) $totalMasterFiles ?>)
                </summary>
                <ul class="mt-3 space-y-1.5 max-h-64 overflow-y-auto">
                    <?php foreach ($masterMigrationFiles as $f): ?>
                    <li class="flex items-center gap-2 font-mono text-xs">
                        <?php if (in_array($f, $masterExecutadas, true)): ?>
                        <span class="text-green-600" title="Executada">✓</span>
                        <?php else: ?>
                        <span class="text-amber-600" title="Pendente">○</span>
                        <?php endif; ?>
                        <?= htmlspecialchars($f) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <p id="status-master" class="text-sm text-slate-500 mt-3"></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Modal: escolher migrations do master -->
<div id="modal-escolher-master" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-hidden="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" id="modal-escolher-master-backdrop"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[85vh] flex flex-col">
            <div class="px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Executar no banco Master</h3>
                <p class="text-sm text-slate-600 mt-0.5">Só arquivos *_master.sql. Não toca nos bancos das escolas.</p>
            </div>
            <form id="form-escolher-master" class="flex flex-col flex-1 min-h-0">
                <div class="px-6 py-3 overflow-y-auto flex-1">
                    <ul id="modal-escolher-master-lista" class="space-y-2">
                        <!-- preenchido via JS -->
                    </ul>
                </div>
                <div class="px-6 py-4 border-t border-slate-200 space-y-3">
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" id="modal-escolher-master-aceite" class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span>Confirmo que desejo executar no banco Master.</span>
                    </label>
                    <div>
                        <label for="modal-escolher-master-senha" class="block text-sm font-medium text-slate-700 mb-1">Senha de acesso</label>
                        <input type="password" id="modal-escolher-master-senha" autocomplete="current-password"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500" placeholder="Sua senha do Painel Master">
                    </div>
                    <div class="flex flex-wrap items-center gap-2 justify-between">
                        <div class="flex gap-2">
                            <button type="button" id="modal-escolher-master-marcar-todos" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Marcar todos</button>
                            <button type="button" id="modal-escolher-master-desmarcar-todos" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Desmarcar todos</button>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" id="modal-escolher-master-fechar" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700">Executar selecionadas</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== 2. BANCOS DAS ESCOLAS ========== -->
<section id="secao-escolas" class="mb-8 scroll-mt-6">
    <div class="flex items-center gap-2 mb-3">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">2</span>
        <h2 class="text-xl font-bold text-slate-900">Bancos das escolas</h2>
        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">um MySQL por escola</span>
    </div>
    <p class="text-sm text-slate-600 mb-4 max-w-3xl">
        Cada escola tem o próprio banco. A mesma migration de schema precisa ser aplicada em todas (ou nas que você escolher).
        Arquivos <strong>sem</strong> <code class="bg-slate-100 px-1 rounded text-xs">_master</code>.
        Seeds (<code class="bg-slate-100 px-1 rounded text-xs">importar_dados_*</code>) não entram em “Executar pendentes” — use Escolher.
    </p>

    <div class="bg-white rounded-xl shadow-sm border-2 border-blue-200 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-blue-100 bg-blue-50/60">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Ações em lote</h3>
                    <p class="text-xs text-slate-600 mt-0.5">Aplica schema nos bancos das escolas — nunca no Master.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <?php if ($totalPendentes > 0): ?>
                    <button type="button" id="btn-executar-todas" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Executar pendentes em todas
                    </button>
                    <?php endif; ?>
                    <button type="button" id="btn-escolher-em-escolas" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 font-medium text-sm flex items-center gap-2"
                            data-migrations="<?= htmlspecialchars(json_encode(array_values(array_reverse($migrationFiles)))) ?>"
                            data-escolas="<?= htmlspecialchars(json_encode(array_map(static function ($e) {
                                return ['id' => (int) $e['id'], 'nome' => $e['nome'], 'slug' => $e['slug'], 'ativo' => (int) $e['ativo']];
                            }, $escolasData))) ?>"
                            data-executadas="<?= htmlspecialchars(json_encode(array_reduce($escolasData, static function ($acc, $e) {
                                $acc[(string) (int) $e['id']] = array_values($e['executadas_lista'] ?? []);
                                return $acc;
                            }, []))) ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                        Escolher migrations e escolas
                    </button>
                </div>
            </div>
            <p id="status-geral" class="text-sm text-slate-500 mt-3"></p>
            <?php if ($totalPendentes === 0): ?>
            <p class="mt-2 text-sm text-green-800 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                Schema em dia em todas as escolas. Para rodar um arquivo específico (ou seed), use <strong>Escolher migrations e escolas</strong> ou o botão por linha na tabela.
            </p>
            <?php endif; ?>
        </div>

        <div class="px-6 py-4 grid grid-cols-1 lg:grid-cols-2 gap-4 border-b border-slate-100">
            <details class="rounded-lg border border-slate-200 bg-slate-50/50 px-4 py-3">
                <summary class="cursor-pointer text-sm font-medium text-slate-800">
                    Schema das escolas (<?= (int) count($schemaFilesOnly) ?> arquivos)
                </summary>
                <p class="text-xs text-slate-500 mt-2 mb-2">Sem _master, sem rollback, sem importar_dados_*.</p>
                <?php if (empty($schemaFilesOnly)): ?>
                <p class="text-sm text-slate-500">Nenhum arquivo de schema.</p>
                <?php else: ?>
                <ul class="max-h-40 overflow-y-auto space-y-1 font-mono text-xs text-slate-700">
                    <?php foreach (array_reverse($schemaFilesOnly) as $f): ?>
                    <li><?= htmlspecialchars($f) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </details>
            <details class="rounded-lg border border-amber-200 bg-amber-50/40 px-4 py-3">
                <summary class="cursor-pointer text-sm font-medium text-slate-800">
                    Seeds / cargas pontuais (<?= (int) $totalCargas ?>)
                </summary>
                <p class="text-xs text-slate-500 mt-2 mb-2">Arquivos <code class="bg-white px-1 rounded">importar_dados_*</code>. Só via Escolher — nunca no lote automático.</p>
                <?php if (empty($cargasFiles)): ?>
                <p class="text-sm text-slate-500">Nenhum seed nesta pasta.</p>
                <?php else: ?>
                <ul class="max-h-40 overflow-y-auto space-y-1 font-mono text-xs text-slate-700">
                    <?php foreach ($cargasFiles as $f): ?>
                    <li><?= htmlspecialchars($f) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </details>
        </div>

        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-base font-semibold text-slate-900">Status por escola</h3>
            <p class="text-xs text-slate-500 mt-0.5">Por linha: executar tudo pendente, escolher arquivos ou marcar como já aplicado (Ignorar).</p>
        </div>

        <?php if (empty($escolasData)): ?>
            <div class="p-6 text-slate-500 text-sm">Nenhuma escola com banco configurado.</div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Banco</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Executadas</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Pendentes</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Ação</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($escolasData as $e): ?>
                    <tr id="escola-row-<?= (int)$e['id'] ?>">
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium text-slate-900"><?= htmlspecialchars($e['nome']) ?></p>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars($e['slug']) ?></p>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600"><?= htmlspecialchars($e['nome_banco']) ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if (!$e['ativo']): ?>
                                <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-semibold">Inativa</span>
                            <?php elseif ($e['pendentes'] === 0): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Em dia</span>
                                <?php if (($e['cargas_pendentes'] ?? 0) > 0): ?>
                                <span class="ml-1 px-2 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-semibold"><?= (int) $e['cargas_pendentes'] ?> carga(s)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold"><?= $e['pendentes'] ?> pendente(s)</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center text-sm font-medium text-slate-700"><?= $e['executadas'] ?>/<?= $totalMigrations ?></td>
                        <td class="px-4 py-3 text-center text-sm font-bold <?= $e['pendentes'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= $e['pendentes'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2 flex-wrap">
                                <?php if ($e['pendentes'] > 0): ?>
                                <button type="button" class="btn-executar-escola bg-blue-500 text-white px-3 py-1.5 rounded-lg hover:bg-blue-600 text-xs font-medium" data-escola-id="<?= (int)$e['id'] ?>" data-escola-nome="<?= htmlspecialchars($e['nome']) ?>">
                                    Executar todas
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn-escolher-migrations bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 text-xs font-medium" data-escola-id="<?= (int)$e['id'] ?>" data-escola-nome="<?= htmlspecialchars($e['nome']) ?>" data-pendentes="<?= htmlspecialchars(json_encode($e['escolher_lista'] ?? $e['pendentes_lista'] ?? [])) ?>">
                                    Escolher
                                </button>
                                <?php if ($e['pendentes'] > 0): ?>
                                <button type="button" class="btn-marcar-executada bg-gray-200 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-gray-300 text-xs font-medium" data-escola-id="<?= (int)$e['id'] ?>" data-escola-nome="<?= htmlspecialchars($e['nome']) ?>" title="Marca como executada sem rodar SQL (para escolas que já tinham banco)">
                                    Ignorar
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Modal: escolher migrations + destino (todas ou escolas) -->
<div id="modal-escolher-em-escolas" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-hidden="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" id="modal-escolher-em-escolas-backdrop"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col">
            <div class="px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Executar nas escolas</h3>
                <p class="text-sm text-slate-600 mt-0.5">Só bancos das escolas (não o Master). Marque arquivos e destino: todas as ativas ou escolas escolhidas. Já executadas são ignoradas.</p>
            </div>
            <form id="form-escolher-em-escolas" class="flex flex-col flex-1 min-h-0">
                <div class="px-6 py-4 overflow-y-auto flex-1 space-y-5">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <p class="text-sm font-semibold text-slate-800">Migrations</p>
                            <div class="flex gap-2">
                                <button type="button" id="modal-em-escolas-mig-marcar" class="px-2 py-1 border border-slate-300 rounded text-xs font-medium text-slate-700 hover:bg-slate-50">Marcar todas</button>
                                <button type="button" id="modal-em-escolas-mig-desmarcar" class="px-2 py-1 border border-slate-300 rounded text-xs font-medium text-slate-700 hover:bg-slate-50">Desmarcar</button>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mb-2">Arquivos já aplicados em <strong>todas</strong> as escolas do destino não aparecem — pode fechar se não houver nada pendente.</p>
                        <ul id="modal-em-escolas-lista-migrations" class="space-y-2 max-h-48 overflow-y-auto border border-slate-200 rounded-lg p-3"></ul>
                        <p id="modal-em-escolas-vazio" class="hidden mt-2 text-sm text-green-800 bg-green-50 border border-green-200 rounded-lg px-3 py-2">Nada pendente neste destino. Pode fechar esta tela.</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800 mb-2">Destino</p>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                <input type="radio" name="escopo" value="todas" checked class="text-indigo-600 focus:ring-indigo-500 border-slate-300">
                                Todas as escolas ativas com banco
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                <input type="radio" name="escopo" value="selecionadas" class="text-indigo-600 focus:ring-indigo-500 border-slate-300">
                                Selecionar escolas
                            </label>
                        </div>
                        <div id="modal-em-escolas-bloco-escolas" class="mt-3 hidden">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <p class="text-xs text-slate-500">Marque as escolas que devem receber as migrations.</p>
                                <div class="flex gap-2">
                                    <button type="button" id="modal-em-escolas-esc-marcar" class="px-2 py-1 border border-slate-300 rounded text-xs font-medium text-slate-700 hover:bg-slate-50">Marcar todas</button>
                                    <button type="button" id="modal-em-escolas-esc-desmarcar" class="px-2 py-1 border border-slate-300 rounded text-xs font-medium text-slate-700 hover:bg-slate-50">Desmarcar</button>
                                </div>
                            </div>
                            <ul id="modal-em-escolas-lista-escolas" class="space-y-2 max-h-40 overflow-y-auto border border-slate-200 rounded-lg p-3"></ul>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-200 space-y-3">
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" id="modal-em-escolas-aceite" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Confirmo que desejo executar esta ação.</span>
                    </label>
                    <div>
                        <label for="modal-em-escolas-senha" class="block text-sm font-medium text-slate-700 mb-1">Senha de acesso</label>
                        <input type="password" id="modal-em-escolas-senha" autocomplete="current-password"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Sua senha do Painel Master">
                    </div>
                    <p id="modal-em-escolas-erro" class="hidden text-xs text-red-600"></p>
                    <div class="flex flex-wrap items-center gap-2 justify-end">
                        <button type="button" id="modal-escolher-em-escolas-fechar" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Fechar</button>
                        <button type="submit" id="modal-em-escolas-submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Executar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: escolher migrations para executar na escola -->
<div id="modal-escolher-migrations" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-hidden="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" id="modal-escolher-backdrop"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[85vh] flex flex-col">
            <div class="px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Executar migrations na escola</h3>
                <p id="modal-escolher-escola-nome" class="text-sm text-slate-600 mt-0.5"></p>
            </div>
            <form id="form-escolher-migrations" class="flex flex-col flex-1 min-h-0">
                <input type="hidden" name="escola_id" id="modal-escolher-escola-id" value="">
                <div class="px-6 py-3 overflow-y-auto flex-1">
                    <p class="text-xs text-slate-500 mb-3">Marque só o que deseja executar. Seeds <code class="bg-slate-100 px-1 rounded">importar_dados_*</code> não entram em “Executar todas”. Arquivos já executados nesta escola não aparecem.</p>
                    <ul id="modal-escolher-lista" class="space-y-2">
                        <!-- preenchido via JS -->
                    </ul>
                </div>
                <p id="modal-escolher-status" class="hidden mx-6 mb-3 rounded-lg border px-4 py-3 text-sm font-medium" role="status" aria-live="polite"></p>
                <div class="px-6 py-4 border-t border-slate-200 space-y-3">
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" id="modal-escolher-aceite" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span>Confirmo que desejo executar nesta escola.</span>
                    </label>
                    <div>
                        <label for="modal-escolher-senha" class="block text-sm font-medium text-slate-700 mb-1">Senha de acesso</label>
                        <input type="password" id="modal-escolher-senha" autocomplete="current-password"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Sua senha do Painel Master">
                    </div>
                    <p id="modal-escolher-erro" class="hidden text-xs text-red-600"></p>
                    <div class="flex flex-wrap items-center gap-2 justify-between">
                        <div class="flex gap-2">
                            <button type="button" id="modal-escolher-marcar-todos" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Marcar todos</button>
                            <button type="button" id="modal-escolher-desmarcar-todos" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Desmarcar todos</button>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" id="modal-escolher-fechar" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Fechar</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Executar selecionadas</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modal-confirmar-acao" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/50 p-4" aria-hidden="true">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md" role="dialog" aria-modal="true" aria-labelledby="modal-confirmar-acao-titulo">
        <h4 id="modal-confirmar-acao-titulo" class="text-base font-semibold text-slate-900 mb-1">Confirme a ação</h4>
        <p class="text-sm text-slate-500 mb-3">Leia o que será feito, confirme e digite a senha do seu acesso ao Painel Master.</p>
        <p id="modal-confirmar-acao-texto" class="text-sm text-slate-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-4 whitespace-pre-line max-h-40 overflow-y-auto"></p>
        <label class="flex items-start gap-2 text-sm text-slate-700 mb-4">
            <input type="checkbox" id="modal-confirmar-acao-aceite" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
            <span>Confirmo que estou executando esta ação.</span>
        </label>
        <label for="modal-confirmar-acao-senha" class="block text-sm font-medium text-slate-700 mb-1">Senha de acesso</label>
        <input type="password" id="modal-confirmar-acao-senha" autocomplete="current-password"
               class="w-full px-3 py-2 border border-slate-300 rounded-lg mb-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Sua senha do Painel Master">
        <p id="modal-confirmar-acao-erro" class="text-xs text-red-600 mb-3 hidden"></p>
        <div class="flex gap-2 justify-end mt-2">
            <button type="button" id="modal-confirmar-acao-cancelar" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 rounded-lg">Cancelar</button>
            <button type="button" id="modal-confirmar-acao-ok" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Confirmar</button>
        </div>
    </div>
</div>

<script>
(function() {
    var baseUrl = '<?= rtrim(URL, '/') ?>';
    try {
        var avisoMigration = sessionStorage.getItem('educatudo_migration_aviso');
        if (avisoMigration) {
            sessionStorage.removeItem('educatudo_migration_aviso');
            var avisoEl = document.getElementById('aviso-migration-finalizada');
            if (avisoEl) {
                avisoEl.textContent = avisoMigration;
                avisoEl.classList.remove('hidden');
            }
        }
    } catch (e) {}
    var statusGeralEl = document.getElementById('status-geral');
    var resultadoExecucaoEl = document.getElementById('resultado-execucao');
    var confirmacaoResolver = null;

    function abrirConfirmacao(descricao) {
        return new Promise(function(resolve) {
            if (confirmacaoResolver) {
                var anterior = confirmacaoResolver;
                confirmacaoResolver = null;
                anterior(null);
            }
            confirmacaoResolver = resolve;
            var modal = document.getElementById('modal-confirmar-acao');
            var texto = document.getElementById('modal-confirmar-acao-texto');
            var aceite = document.getElementById('modal-confirmar-acao-aceite');
            var senha = document.getElementById('modal-confirmar-acao-senha');
            var erro = document.getElementById('modal-confirmar-acao-erro');
            texto.textContent = descricao;
            aceite.checked = false;
            senha.value = '';
            erro.classList.add('hidden');
            erro.textContent = '';
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
            setTimeout(function() { senha.focus(); }, 50);
        });
    }

    function fecharConfirmacao(valor) {
        var modal = document.getElementById('modal-confirmar-acao');
        var senha = document.getElementById('modal-confirmar-acao-senha');
        if (senha) senha.value = '';
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.setAttribute('aria-hidden', 'true');
        }
        var resolver = confirmacaoResolver;
        confirmacaoResolver = null;
        if (resolver) resolver(valor || null);
    }

    function validarConfirmacao() {
        var aceite = document.getElementById('modal-confirmar-acao-aceite');
        var senha = document.getElementById('modal-confirmar-acao-senha');
        var erro = document.getElementById('modal-confirmar-acao-erro');
        if (!aceite.checked) {
            erro.textContent = 'Marque que você confirma esta ação.';
            erro.classList.remove('hidden');
            return;
        }
        if (senha.value === '') {
            erro.textContent = 'Digite sua senha de acesso.';
            erro.classList.remove('hidden');
            senha.focus();
            return;
        }
        var valor = senha.value;
        fecharConfirmacao(valor);
    }

    document.getElementById('modal-confirmar-acao-cancelar').addEventListener('click', function() {
        fecharConfirmacao(null);
    });
    document.getElementById('modal-confirmar-acao-ok').addEventListener('click', validarConfirmacao);
    document.getElementById('modal-confirmar-acao').addEventListener('click', function(e) {
        if (e.target === this) fecharConfirmacao(null);
    });
    document.getElementById('modal-confirmar-acao-senha').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            validarConfirmacao();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && confirmacaoResolver) {
            fecharConfirmacao(null);
        }
    });

    function formComConfirmacao(senha, extras) {
        var fd = new FormData();
        fd.append('_token', getCsrfToken());
        fd.append('confirm_senha', senha);
        fd.append('confirmar_acao', '1');
        if (!extras) return fd;
        Object.keys(extras).forEach(function(chave) {
            var valor = extras[chave];
            if (Array.isArray(valor)) {
                valor.forEach(function(item) { fd.append(chave, item); });
            } else {
                fd.append(chave, valor);
            }
        });
        return fd;
    }

    function nomesMarcados(checkboxes) {
        var nomes = [];
        checkboxes.forEach(function(cb) { nomes.push(cb.value); });
        return nomes.join('\n');
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function(ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function renderErroDetalhado(r) {
        var message = escapeHtml(r.message || 'Erro desconhecido');
        var dbInfo = [r.db_name || '', r.db_host || '', r.db_port || ''].filter(Boolean).join(' | ');
        var errorType = escapeHtml(r.error_type || '');
        var extra = '';
        if (dbInfo || errorType) {
            extra = '<div class="text-[11px] text-red-700 mt-1">'
                + (errorType ? ('<strong>Tipo:</strong> ' + errorType + ' ') : '')
                + (dbInfo ? ('<strong>Banco:</strong> ' + escapeHtml(dbInfo)) : '')
                + '</div>';
        }
        return '<div class="mt-2 p-2 rounded bg-red-100 border border-red-200">'
            + '<div class="text-xs text-red-800 break-words"><strong>Detalhes:</strong> ' + message + '</div>'
            + extra
            + '</div>';
    }

    function mostrarResultado(resultados) {
        var container = resultadoExecucaoEl;
        container.classList.remove('hidden');
        var html = '<div class="space-y-2">';
        var hasErrors = false;
        var hasBackground = false;
        resultados.forEach(function(r) {
            var isOk = r.status === 'ok';
            var isBg = r.status === 'background';
            if (!isOk && !isBg) hasErrors = true;
            if (isBg) hasBackground = true;
            var boxClass = isOk
                ? 'bg-green-50 border border-green-200'
                : (isBg ? 'bg-blue-50 border border-blue-200' : 'bg-red-50 border border-red-200');
            var nameClass = isOk ? 'text-green-800' : (isBg ? 'text-blue-800' : 'text-red-800');
            var msgClass = isOk ? 'text-green-600' : (isBg ? 'text-blue-600' : 'text-red-600');
            html += '<div class="flex items-center gap-3 px-4 py-2 rounded-lg ' + boxClass + '">';
            html += '<span class="font-medium text-sm ' + nameClass + '">' + escapeHtml(r.nome || 'Escola #' + r.escola_id) + '</span>';
            html += '<span class="text-xs ' + msgClass + '">' + escapeHtml(r.message || '') + '</span>';
            html += '</div>';
            if (!isOk && !isBg) {
                html += renderErroDetalhado(r);
            }
        });
        html += '</div>';
        container.innerHTML = html;
        if (statusGeralEl) {
            if (hasErrors) {
                statusGeralEl.textContent = 'Uma ou mais migrations falharam. Veja os detalhes abaixo.';
            } else if (hasBackground) {
                statusGeralEl.textContent = 'Cargas enfileiradas. Acompanhe em Fila do Master.';
            } else {
                statusGeralEl.textContent = 'Migrations executadas com sucesso.';
            }
        }
        return hasErrors;
    }

    function mostrarErroUnico(r) {
        if (!resultadoExecucaoEl) return;
        resultadoExecucaoEl.classList.remove('hidden');
        var html = '<div class="space-y-2">';
        html += '<div class="flex items-center gap-3 px-4 py-2 rounded-lg bg-red-50 border border-red-200">';
        html += '<span class="font-medium text-sm text-red-800">' + escapeHtml(r.nome || ('Escola #' + (r.escola_id || ''))) + '</span>';
        html += '<span class="text-xs text-red-600">' + escapeHtml(r.message || 'Erro desconhecido') + '</span>';
        html += '</div>';
        html += renderErroDetalhado(r);
        html += '</div>';
        resultadoExecucaoEl.innerHTML = html;
        if (statusGeralEl) {
            statusGeralEl.textContent = 'Falha ao executar migration. Veja os detalhes abaixo.';
        }
    }

    var btnExecutarMaster = document.getElementById('btn-executar-master');
    var statusMasterEl = document.getElementById('status-master');
    if (btnExecutarMaster) {
        btnExecutarMaster.addEventListener('click', function() {
            abrirConfirmacao('Executar todas as migrations pendentes no banco master.').then(function(senha) {
            if (!senha) return;
            btnExecutarMaster.disabled = true;
            btnExecutarMaster.textContent = 'Executando...';
            if (statusMasterEl) statusMasterEl.textContent = 'Aguarde...';
            fetch(baseUrl + '/master/migrations/executar-master', { method: 'POST', body: formComConfirmacao(senha) })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        if (statusMasterEl) statusMasterEl.textContent = data.message || 'OK.';
                        setTimeout(function() { location.reload(); }, 1200);
                    } else {
                        if (statusMasterEl) statusMasterEl.textContent = 'Erro: ' + (data.error || 'desconhecido');
                        btnExecutarMaster.disabled = false;
                        btnExecutarMaster.textContent = 'Executar pendentes';
                    }
                })
                .catch(function(err) {
                    if (statusMasterEl) statusMasterEl.textContent = 'Erro de rede: ' + err.message;
                    btnExecutarMaster.disabled = false;
                    btnExecutarMaster.textContent = 'Executar pendentes';
                });
            });
        });
    }

    function marcarCheckboxesMigration(container, checked) {
        if (!container) return;
        container.querySelectorAll('input[name="migrations[]"]').forEach(function(cb) {
            cb.checked = !!checked;
        });
    }

    var btnEscolherMaster = document.getElementById('btn-escolher-master');
    var modalEscolherMaster = document.getElementById('modal-escolher-master');
    var modalEscolherMasterBackdrop = document.getElementById('modal-escolher-master-backdrop');
    var modalEscolherMasterLista = document.getElementById('modal-escolher-master-lista');
    var formEscolherMaster = document.getElementById('form-escolher-master');
    var modalEscolherMasterFechar = document.getElementById('modal-escolher-master-fechar');
    if (btnEscolherMaster) {
        btnEscolherMaster.addEventListener('click', function() {
            var pendentes = [];
            try {
                pendentes = JSON.parse(btnEscolherMaster.getAttribute('data-pendentes') || '[]');
            } catch (e) {}
            modalEscolherMasterLista.innerHTML = '';
            pendentes.forEach(function(file) {
                var li = document.createElement('li');
                li.className = 'flex items-center gap-2';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'migrations[]';
                cb.value = file;
                cb.id = 'mig-master-' + file.replace(/[^a-z0-9_]/gi, '-');
                cb.checked = true;
                cb.className = 'rounded border-slate-300 text-amber-600 focus:ring-amber-500';
                var label = document.createElement('label');
                label.htmlFor = cb.id;
                label.className = 'text-sm text-slate-700 font-mono cursor-pointer';
                label.textContent = file;
                li.appendChild(cb);
                li.appendChild(label);
                modalEscolherMasterLista.appendChild(li);
            });
            if (pendentes.length === 0) {
                modalEscolherMasterLista.innerHTML = '<li class="text-sm text-slate-500">Nenhuma migration pendente.</li>';
            }
            var senhaMaster = document.getElementById('modal-escolher-master-senha');
            var aceiteMaster = document.getElementById('modal-escolher-master-aceite');
            if (senhaMaster) senhaMaster.value = '';
            if (aceiteMaster) aceiteMaster.checked = false;
            modalEscolherMaster.classList.remove('hidden');
        });
    }
    function fecharModalMaster() {
        if (modalEscolherMaster) modalEscolherMaster.classList.add('hidden');
        var senhaMaster = document.getElementById('modal-escolher-master-senha');
        var aceiteMaster = document.getElementById('modal-escolher-master-aceite');
        if (senhaMaster) senhaMaster.value = '';
        if (aceiteMaster) aceiteMaster.checked = false;
    }
    if (modalEscolherMasterBackdrop) modalEscolherMasterBackdrop.addEventListener('click', fecharModalMaster);
    if (modalEscolherMasterFechar) modalEscolherMasterFechar.addEventListener('click', fecharModalMaster);
    var btnMasterMarcarTodos = document.getElementById('modal-escolher-master-marcar-todos');
    var btnMasterDesmarcarTodos = document.getElementById('modal-escolher-master-desmarcar-todos');
    if (btnMasterMarcarTodos) {
        btnMasterMarcarTodos.addEventListener('click', function() {
            marcarCheckboxesMigration(formEscolherMaster, true);
        });
    }
    if (btnMasterDesmarcarTodos) {
        btnMasterDesmarcarTodos.addEventListener('click', function() {
            marcarCheckboxesMigration(formEscolherMaster, false);
        });
    }
    if (formEscolherMaster) {
        formEscolherMaster.addEventListener('submit', function(e) {
            e.preventDefault();
            var checkboxes = formEscolherMaster.querySelectorAll('input[name="migrations[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Selecione ao menos uma migration.');
                return;
            }
            var senha = validarSenhaInline(
                document.getElementById('modal-escolher-master-aceite'),
                document.getElementById('modal-escolher-master-senha'),
                null
            );
            if (!senha) return;
            var arquivos = [];
            checkboxes.forEach(function(cb) { arquivos.push(cb.value); });
            var formData = formComConfirmacao(senha, { 'migrations[]': arquivos });
            var btnSubmit = formEscolherMaster.querySelector('button[type="submit"]');
            var origText = btnSubmit.textContent;
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Executando...';
            fetch(baseUrl + '/master/migrations/executar-master-selecionadas', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        fecharModalMaster();
                        if (statusMasterEl) statusMasterEl.textContent = data.message || 'OK.';
                        setTimeout(function() { location.reload(); }, 800);
                    } else {
                        alert('Erro: ' + (data.error || 'desconhecido'));
                    }
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                })
                .catch(function(err) {
                    alert('Erro de rede: ' + err.message);
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                });
        });
    }

    var btnTodas = document.getElementById('btn-executar-todas');
    var labelBtnTodas = 'Executar pendentes em todas';
    if (btnTodas) {
        btnTodas.addEventListener('click', function() {
            abrirConfirmacao('Executar todas as migrations pendentes em todas as escolas ativas.').then(function(senha) {
            if (!senha) return;
            btnTodas.disabled = true;
            btnTodas.textContent = 'Executando...';
            if (statusGeralEl) statusGeralEl.textContent = 'Aguarde, pode levar alguns segundos...';
            fetch(baseUrl + '/master/migrations/executar-todas', { method: 'POST', body: formComConfirmacao(senha) })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.resultados) {
                        var hasErrors = mostrarResultado(data.resultados);
                        if (!hasErrors) {
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            btnTodas.disabled = false;
                            btnTodas.textContent = labelBtnTodas;
                        }
                    } else {
                        alert('Erro: ' + (data.error || 'desconhecido'));
                        btnTodas.disabled = false;
                        btnTodas.textContent = labelBtnTodas;
                    }
                })
                .catch(function(err) {
                    alert('Erro de rede: ' + err.message);
                    btnTodas.disabled = false;
                    btnTodas.textContent = labelBtnTodas;
                    if (statusGeralEl) statusGeralEl.textContent = '';
                });
            });
        });
    }

    function validarSenhaInline(aceiteEl, senhaEl, erroEl) {
        if (erroEl) {
            erroEl.classList.add('hidden');
            erroEl.textContent = '';
        }
        if (!aceiteEl || !aceiteEl.checked) {
            if (erroEl) {
                erroEl.textContent = 'Marque que você confirma esta ação.';
                erroEl.classList.remove('hidden');
            } else {
                alert('Marque que você confirma esta ação.');
            }
            return null;
        }
        var senha = senhaEl ? String(senhaEl.value || '') : '';
        if (senha === '') {
            if (erroEl) {
                erroEl.textContent = 'Digite sua senha de acesso.';
                erroEl.classList.remove('hidden');
            } else {
                alert('Digite sua senha de acesso.');
            }
            if (senhaEl) senhaEl.focus();
            return null;
        }
        return senha;
    }

    // Modal: escolher migrations e destino (todas / escolas)
    var btnEscolherEmEscolas = document.getElementById('btn-escolher-em-escolas');
    var modalEmEscolas = document.getElementById('modal-escolher-em-escolas');
    var formEmEscolas = document.getElementById('form-escolher-em-escolas');
    var listaMigEmEscolas = document.getElementById('modal-em-escolas-lista-migrations');
    var listaEscEmEscolas = document.getElementById('modal-em-escolas-lista-escolas');
    var blocoEscEmEscolas = document.getElementById('modal-em-escolas-bloco-escolas');
    var avisoVazioEmEscolas = document.getElementById('modal-em-escolas-vazio');
    var btnSubmitEmEscolas = document.getElementById('modal-em-escolas-submit');
    var emEscolasCache = { migrations: [], escolas: [], executadas: {} };

    function fecharModalEmEscolas() {
        if (modalEmEscolas) modalEmEscolas.classList.add('hidden');
        var senhaEl = document.getElementById('modal-em-escolas-senha');
        var aceiteEl = document.getElementById('modal-em-escolas-aceite');
        if (senhaEl) senhaEl.value = '';
        if (aceiteEl) aceiteEl.checked = false;
    }

    function idsDestinoEmEscolas() {
        if (!formEmEscolas) return [];
        var escopo = (formEmEscolas.querySelector('input[name="escopo"]:checked') || {}).value || 'todas';
        if (escopo === 'selecionadas') {
            var ids = [];
            formEmEscolas.querySelectorAll('input[name="escola_ids[]"]:checked').forEach(function(cb) {
                ids.push(String(cb.value));
            });
            return ids;
        }
        return (emEscolasCache.escolas || [])
            .filter(function(esc) { return !!esc.ativo; })
            .map(function(esc) { return String(esc.id); });
    }

    function migrationPendenteNoDestino(file, destinoIds) {
        if (!destinoIds || destinoIds.length === 0) return false;
        var isCarga = String(file).indexOf('importar_dados_') !== -1;
        if (isCarga) return true;
        for (var i = 0; i < destinoIds.length; i++) {
            var lista = emEscolasCache.executadas[destinoIds[i]] || [];
            if (lista.indexOf(file) === -1) return true;
        }
        return false;
    }

    function renderListaMigrationsEmEscolas() {
        if (!listaMigEmEscolas) return;
        var destinoIds = idsDestinoEmEscolas();
        var marcados = {};
        listaMigEmEscolas.querySelectorAll('input[name="migrations[]"]:checked').forEach(function(cb) {
            marcados[cb.value] = true;
        });
        listaMigEmEscolas.innerHTML = '';
        var visiveis = 0;
        (emEscolasCache.migrations || []).forEach(function(file) {
            if (!migrationPendenteNoDestino(file, destinoIds)) return;
            visiveis++;
            var isCarga = String(file).indexOf('importar_dados_') !== -1;
            var li = document.createElement('li');
            li.className = 'flex items-center gap-2';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'migrations[]';
            cb.value = file;
            cb.id = 'mig-em-esc-' + file.replace(/[^a-z0-9_]/gi, '-');
            cb.checked = !!marcados[file];
            cb.className = 'rounded border-slate-300 text-indigo-600 focus:ring-indigo-500';
            var label = document.createElement('label');
            label.htmlFor = cb.id;
            label.className = 'text-sm text-slate-700 font-mono cursor-pointer';
            label.textContent = file + (isCarga ? '  (carga)' : '');
            li.appendChild(cb);
            li.appendChild(label);
            listaMigEmEscolas.appendChild(li);
        });
        if (destinoIds.length === 0) {
            listaMigEmEscolas.innerHTML = '<li class="text-sm text-slate-500">Selecione ao menos uma escola no destino.</li>';
            visiveis = 0;
        } else if (visiveis === 0) {
            listaMigEmEscolas.innerHTML = '';
        }
        if (avisoVazioEmEscolas) {
            if (destinoIds.length > 0 && visiveis === 0) {
                avisoVazioEmEscolas.classList.remove('hidden');
            } else {
                avisoVazioEmEscolas.classList.add('hidden');
            }
        }
        if (btnSubmitEmEscolas) {
            btnSubmitEmEscolas.disabled = visiveis === 0;
            btnSubmitEmEscolas.classList.toggle('opacity-50', visiveis === 0);
            btnSubmitEmEscolas.classList.toggle('cursor-not-allowed', visiveis === 0);
        }
    }

    function sincronizarBlocoEscolas() {
        if (!formEmEscolas || !blocoEscEmEscolas) return;
        var escopo = (formEmEscolas.querySelector('input[name="escopo"]:checked') || {}).value || 'todas';
        if (escopo === 'selecionadas') {
            blocoEscEmEscolas.classList.remove('hidden');
        } else {
            blocoEscEmEscolas.classList.add('hidden');
        }
        renderListaMigrationsEmEscolas();
    }

    function abrirModalEmEscolas() {
        if (!btnEscolherEmEscolas || !listaMigEmEscolas || !listaEscEmEscolas) return;
        try { emEscolasCache.migrations = JSON.parse(btnEscolherEmEscolas.getAttribute('data-migrations') || '[]'); } catch (e) { emEscolasCache.migrations = []; }
        try { emEscolasCache.escolas = JSON.parse(btnEscolherEmEscolas.getAttribute('data-escolas') || '[]'); } catch (e) { emEscolasCache.escolas = []; }
        try { emEscolasCache.executadas = JSON.parse(btnEscolherEmEscolas.getAttribute('data-executadas') || '{}'); } catch (e) { emEscolasCache.executadas = {}; }

        listaEscEmEscolas.innerHTML = '';
        emEscolasCache.escolas.forEach(function(esc) {
            var li = document.createElement('li');
            li.className = 'flex items-center gap-2';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'escola_ids[]';
            cb.value = String(esc.id);
            cb.id = 'esc-em-esc-' + esc.id;
            cb.checked = !!esc.ativo;
            cb.className = 'rounded border-slate-300 text-indigo-600 focus:ring-indigo-500';
            cb.addEventListener('change', renderListaMigrationsEmEscolas);
            var label = document.createElement('label');
            label.htmlFor = cb.id;
            label.className = 'text-sm text-slate-700 cursor-pointer';
            label.textContent = esc.nome + (esc.ativo ? '' : ' (inativa)') + ' — ' + (esc.slug || '');
            li.appendChild(cb);
            li.appendChild(label);
            listaEscEmEscolas.appendChild(li);
        });
        if (emEscolasCache.escolas.length === 0) {
            listaEscEmEscolas.innerHTML = '<li class="text-sm text-slate-500">Nenhuma escola com banco.</li>';
        }

        if (formEmEscolas) {
            var radioTodas = formEmEscolas.querySelector('input[name="escopo"][value="todas"]');
            if (radioTodas) radioTodas.checked = true;
            var senhaEl = document.getElementById('modal-em-escolas-senha');
            var aceiteEl = document.getElementById('modal-em-escolas-aceite');
            var erroEl = document.getElementById('modal-em-escolas-erro');
            if (senhaEl) senhaEl.value = '';
            if (aceiteEl) aceiteEl.checked = false;
            if (erroEl) {
                erroEl.classList.add('hidden');
                erroEl.textContent = '';
            }
        }
        sincronizarBlocoEscolas();
        modalEmEscolas.classList.remove('hidden');
    }

    if (btnEscolherEmEscolas) {
        btnEscolherEmEscolas.addEventListener('click', abrirModalEmEscolas);
    }
    var backdropEmEscolas = document.getElementById('modal-escolher-em-escolas-backdrop');
    var fecharEmEscolas = document.getElementById('modal-escolher-em-escolas-fechar');
    if (backdropEmEscolas) backdropEmEscolas.addEventListener('click', fecharModalEmEscolas);
    if (fecharEmEscolas) fecharEmEscolas.addEventListener('click', fecharModalEmEscolas);
    if (formEmEscolas) {
        formEmEscolas.querySelectorAll('input[name="escopo"]').forEach(function(radio) {
            radio.addEventListener('change', sincronizarBlocoEscolas);
        });
    }
    var btnMigMarcar = document.getElementById('modal-em-escolas-mig-marcar');
    var btnMigDesmarcar = document.getElementById('modal-em-escolas-mig-desmarcar');
    var btnEscMarcar = document.getElementById('modal-em-escolas-esc-marcar');
    var btnEscDesmarcar = document.getElementById('modal-em-escolas-esc-desmarcar');
    if (btnMigMarcar) btnMigMarcar.addEventListener('click', function() { marcarCheckboxesMigration(formEmEscolas, true); });
    if (btnMigDesmarcar) btnMigDesmarcar.addEventListener('click', function() { marcarCheckboxesMigration(formEmEscolas, false); });
    if (btnEscMarcar) {
        btnEscMarcar.addEventListener('click', function() {
            if (!formEmEscolas) return;
            formEmEscolas.querySelectorAll('input[name="escola_ids[]"]').forEach(function(cb) { cb.checked = true; });
            renderListaMigrationsEmEscolas();
        });
    }
    if (btnEscDesmarcar) {
        btnEscDesmarcar.addEventListener('click', function() {
            if (!formEmEscolas) return;
            formEmEscolas.querySelectorAll('input[name="escola_ids[]"]').forEach(function(cb) { cb.checked = false; });
            renderListaMigrationsEmEscolas();
        });
    }
    if (formEmEscolas) {
        formEmEscolas.addEventListener('submit', function(e) {
            e.preventDefault();
            var migCbs = formEmEscolas.querySelectorAll('input[name="migrations[]"]:checked');
            if (migCbs.length === 0) {
                alert('Selecione ao menos uma migration.');
                return;
            }
            var escopo = (formEmEscolas.querySelector('input[name="escopo"]:checked') || {}).value || 'todas';
            var escolaIds = [];
            if (escopo === 'selecionadas') {
                formEmEscolas.querySelectorAll('input[name="escola_ids[]"]:checked').forEach(function(cb) {
                    escolaIds.push(cb.value);
                });
                if (escolaIds.length === 0) {
                    alert('Selecione ao menos uma escola.');
                    return;
                }
            }
            var senha = validarSenhaInline(
                document.getElementById('modal-em-escolas-aceite'),
                document.getElementById('modal-em-escolas-senha'),
                document.getElementById('modal-em-escolas-erro')
            );
            if (!senha) return;

            var arquivos = [];
            migCbs.forEach(function(cb) { arquivos.push(cb.value); });
            var extras = {
                escopo: escopo,
                'migrations[]': arquivos
            };
            if (escopo === 'selecionadas') {
                extras['escola_ids[]'] = escolaIds;
            }
            var formData = formComConfirmacao(senha, extras);
            var btnSubmit = formEmEscolas.querySelector('button[type="submit"]');
            var origText = btnSubmit.textContent;
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Executando...';
            if (statusGeralEl) statusGeralEl.textContent = 'Aguarde, pode levar alguns segundos...';
            fetch(baseUrl + '/master/migrations/executar-selecionadas-escolas', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.resultados) {
                        fecharModalEmEscolas();
                        var hasErrors = mostrarResultado(data.resultados);
                        if (!hasErrors && !data.background) {
                            setTimeout(function() { location.reload(); }, 1500);
                        } else if (data.background && !hasErrors) {
                            setTimeout(function() { location.reload(); }, 2500);
                        }
                    } else {
                        alert('Erro: ' + (data.error || 'desconhecido'));
                    }
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                })
                .catch(function(err) {
                    alert('Erro de rede: ' + err.message);
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                    if (statusGeralEl) statusGeralEl.textContent = '';
                });
        });
    }

    document.querySelectorAll('.btn-executar-escola').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var escolaId = this.getAttribute('data-escola-id');
            var escolaNome = this.getAttribute('data-escola-nome') || ('Escola #' + escolaId);
            var btnEl = this;
            abrirConfirmacao('Executar todas as migrations pendentes na escola ' + escolaNome + '.').then(function(senha) {
            if (!senha) return;
            btnEl.disabled = true;
            btnEl.textContent = '...';
            fetch(baseUrl + '/master/migrations/executar-escola', { method: 'POST', body: formComConfirmacao(senha, { escola_id: escolaId }) })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.resultado) {
                        var r = data.resultado;
                        var isOk = r.status === 'ok';
                        btnEl.textContent = isOk ? 'OK' : 'Erro';
                        btnEl.className = isOk
                            ? 'bg-green-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium cursor-default'
                            : 'bg-red-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium cursor-default';
                        if (!isOk) {
                            mostrarErroUnico(r);
                            btnEl.disabled = false;
                            btnEl.textContent = 'Executar';
                            btnEl.className = 'btn-executar-escola bg-blue-500 text-white px-3 py-1.5 rounded-lg hover:bg-blue-600 text-xs font-medium';
                        } else {
                            setTimeout(function() { location.reload(); }, 1000);
                        }
                    } else {
                        alert('Erro: ' + (data.error || 'desconhecido'));
                        btnEl.disabled = false;
                        btnEl.textContent = 'Executar';
                    }
                })
                .catch(function(err) {
                    alert('Erro de rede: ' + err.message);
                    btnEl.disabled = false;
                    btnEl.textContent = 'Executar';
                });
            });
        });
    });
    // Modal: escolher quais migrations executar
    var modalEscolher = document.getElementById('modal-escolher-migrations');
    var modalBackdrop = document.getElementById('modal-escolher-backdrop');
    var modalNome = document.getElementById('modal-escolher-escola-nome');
    var modalEscolaId = document.getElementById('modal-escolher-escola-id');
    var modalLista = document.getElementById('modal-escolher-lista');
    var formEscolher = document.getElementById('form-escolher-migrations');

    function openModalEscolher(escolaId, escolaNome, pendentes) {
        modalEscolaId.value = escolaId;
        modalNome.textContent = escolaNome;
        modalLista.innerHTML = '';
        if (!(migrationAndamento && migrationAndamento.ativo)) {
            var statusAberto = document.getElementById('modal-escolher-status');
            if (statusAberto) {
                statusAberto.textContent = '';
                statusAberto.className = 'hidden mx-6 mb-3 rounded-lg border px-4 py-3 text-sm font-medium';
            }
        }
        var list = Array.isArray(pendentes) ? pendentes : [];
        var soCargas = list.length > 0 && list.every(function(f) { return String(f).indexOf('importar_dados_') !== -1; });
        list.forEach(function(file) {
            var isCarga = String(file).indexOf('importar_dados_') !== -1;
            var li = document.createElement('li');
            li.className = 'flex items-center gap-2';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'migrations[]';
            cb.value = file;
            cb.id = 'mig-' + file.replace(/[^a-z0-9_]/gi, '-');
            var ehApagar = String(file).indexOf('_apagar') !== -1;
            cb.checked = !ehApagar && (soCargas || !isCarga);
            cb.className = 'rounded border-slate-300 text-blue-600 focus:ring-blue-500';
            var label = document.createElement('label');
            label.htmlFor = cb.id;
            label.className = 'text-sm text-slate-700 font-mono cursor-pointer';
            label.textContent = file + (ehApagar ? '  (apagar seed)' : (isCarga ? '  (carga)' : ''));
            li.appendChild(cb);
            li.appendChild(label);
            modalLista.appendChild(li);
        });
        if (list.length === 0) {
            modalLista.innerHTML = '<li class="text-sm text-slate-500">Nada pendente nesta escola. Se o seed não aparece, o arquivo ainda não está na pasta de migrations deste servidor. Pode fechar.</li>';
        }
        var senhaEsc = document.getElementById('modal-escolher-senha');
        var aceiteEsc = document.getElementById('modal-escolher-aceite');
        var erroEsc = document.getElementById('modal-escolher-erro');
        if (senhaEsc) senhaEsc.value = '';
        if (aceiteEsc) aceiteEsc.checked = false;
        if (erroEsc) {
            erroEsc.classList.add('hidden');
            erroEsc.textContent = '';
        }
        modalEscolher.classList.remove('hidden');
    }

    var migrationAndamento = null;

    function tempoCorrido(inicio) {
        var seg = Math.max(0, Math.floor((Date.now() - inicio) / 1000));
        var m = Math.floor(seg / 60);
        var s = seg % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function pintarStatusMigration(tipo) {
        var el = document.getElementById('modal-escolher-status');
        if (!el) return null;
        el.classList.remove('hidden');
        el.className = 'mx-6 mb-3 rounded-lg border px-4 py-3 text-sm font-medium';
        if (tipo === 'ok') {
            el.className += ' border-green-300 bg-green-50 text-green-900';
        } else if (tipo === 'erro') {
            el.className += ' border-red-300 bg-red-50 text-red-900';
        } else {
            el.className += ' border-blue-300 bg-blue-50 text-blue-900';
        }
        return el;
    }

    function atualizarTextoAndamento() {
        if (!migrationAndamento || !migrationAndamento.ativo) return;
        var el = pintarStatusMigration('rodando');
        if (!el) return;
        el.textContent = 'Migration em andamento há ' + tempoCorrido(migrationAndamento.inicio) + '. ' + migrationAndamento.detalhe;
    }

    function iniciarAndamento(detalhe) {
        if (migrationAndamento && migrationAndamento.timer) clearInterval(migrationAndamento.timer);
        migrationAndamento = {
            inicio: Date.now(),
            detalhe: detalhe || 'A migration está em execução.',
            timer: null,
            ativo: true
        };
        atualizarTextoAndamento();
        migrationAndamento.timer = setInterval(atualizarTextoAndamento, 1000);
        var fechar = document.getElementById('modal-escolher-fechar');
        if (fechar) {
            fechar.disabled = true;
            fechar.textContent = 'Aguarde';
        }
    }

    function pararAndamento() {
        if (migrationAndamento && migrationAndamento.timer) clearInterval(migrationAndamento.timer);
        if (migrationAndamento) migrationAndamento.ativo = false;
        var fechar = document.getElementById('modal-escolher-fechar');
        if (fechar) {
            fechar.disabled = false;
            fechar.textContent = 'Cancelar';
        }
    }

    function guardarAvisoMigration(texto) {
        try { sessionStorage.setItem('educatudo_migration_aviso', texto); } catch (e) {}
    }

    function concluirMigration(texto) {
        pararAndamento();
        var el = pintarStatusMigration('ok');
        if (el) el.textContent = texto;
        guardarAvisoMigration(texto);
        setTimeout(function() { location.reload(); }, 2500);
    }

    function closeModalEscolher() {
        if (migrationAndamento && migrationAndamento.ativo) return;
        modalEscolher.classList.add('hidden');
        var senhaEsc = document.getElementById('modal-escolher-senha');
        var aceiteEsc = document.getElementById('modal-escolher-aceite');
        if (senhaEsc) senhaEsc.value = '';
        if (aceiteEsc) aceiteEsc.checked = false;
    }

    if (modalBackdrop) modalBackdrop.addEventListener('click', closeModalEscolher);
    if (document.getElementById('modal-escolher-fechar')) {
        document.getElementById('modal-escolher-fechar').addEventListener('click', closeModalEscolher);
    }
    var btnMarcarTodos = document.getElementById('modal-escolher-marcar-todos');
    var btnDesmarcarTodos = document.getElementById('modal-escolher-desmarcar-todos');
    if (btnMarcarTodos) {
        btnMarcarTodos.addEventListener('click', function() {
            if (!formEscolher) return;
            formEscolher.querySelectorAll('input[name="migrations[]"]').forEach(function(cb) {
                cb.checked = String(cb.value).indexOf('_apagar') === -1;
            });
        });
    }
    if (btnDesmarcarTodos) {
        btnDesmarcarTodos.addEventListener('click', function() {
            marcarCheckboxesMigration(formEscolher, false);
        });
    }

    document.querySelectorAll('.btn-escolher-migrations').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var escolaId = this.getAttribute('data-escola-id');
            var escolaNome = this.getAttribute('data-escola-nome') || ('Escola #' + escolaId);
            var pendentes = [];
            try {
                pendentes = JSON.parse(this.getAttribute('data-pendentes') || '[]');
            } catch (e) {}
            openModalEscolher(escolaId, escolaNome, pendentes);
        });
    });

    function acompanharCarga(jobId, btnSubmit, origText) {
        var tentativas = 0;
        var timer = setInterval(function() {
            tentativas++;
            fetch(baseUrl + '/master/migrations/job?id=' + encodeURIComponent(jobId))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        if (tentativas < 5) return;
                        clearInterval(timer);
                        pararAndamento();
                        var sumiu = (data && data.error) || 'Não foi possível acompanhar a migration.';
                        var elSumiu = pintarStatusMigration('erro');
                        if (elSumiu) elSumiu.textContent = sumiu;
                        btnSubmit.disabled = false;
                        btnSubmit.textContent = origText;
                        return;
                    }
                    var job = data.job || {};
                    var msg = (job.resultado_decoded && job.resultado_decoded.mensagem) || '';
                    if (job.status === 'done') {
                        clearInterval(timer);
                        btnSubmit.textContent = 'Finalizada';
                        var finalMsg = msg ? ('Migration finalizada. ' + msg) : 'Migration finalizada com sucesso.';
                        concluirMigration(finalMsg);
                        return;
                    }
                    if (job.status === 'failed') {
                        clearInterval(timer);
                        pararAndamento();
                        var erro = job.mensagem_erro || 'A migration falhou.';
                        var elErro = pintarStatusMigration('erro');
                        if (elErro) elErro.textContent = erro;
                        alert('Erro: ' + erro);
                        btnSubmit.disabled = false;
                        btnSubmit.textContent = origText;
                        return;
                    }
                    if (migrationAndamento) {
                        migrationAndamento.detalhe = msg || (job.status === 'pending'
                            ? 'Aguardando a fila do servidor.'
                            : 'A migration está em execução no servidor. Pode levar vários minutos.');
                    }
                    btnSubmit.textContent = job.status === 'pending' ? 'Na fila...' : 'Rodando...';
                })
                .catch(function() {
                    if (tentativas <= 90) return;
                    clearInterval(timer);
                    pararAndamento();
                    var elRede = pintarStatusMigration('erro');
                    if (elRede) elRede.textContent = 'Sem resposta do status. A migration pode continuar no servidor.';
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                });
        }, 2000);
    }

    if (formEscolher) {
        formEscolher.addEventListener('submit', function(e) {
            e.preventDefault();
            var checkboxes = formEscolher.querySelectorAll('input[name="migrations[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Selecione ao menos uma migration.');
                return;
            }
            var senha = validarSenhaInline(
                document.getElementById('modal-escolher-aceite'),
                document.getElementById('modal-escolher-senha'),
                document.getElementById('modal-escolher-erro')
            );
            if (!senha) return;
            var arquivos = [];
            checkboxes.forEach(function(cb) { arquivos.push(cb.value); });
            var formData = formComConfirmacao(senha, {
                escola_id: modalEscolaId.value,
                'migrations[]': arquivos
            });
            var btnSubmit = formEscolher.querySelector('button[type="submit"]');
            var origText = btnSubmit.textContent;
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Rodando...';
            iniciarAndamento('Enviando a migration para o servidor.');
            fetch(baseUrl + '/master/migrations/executar-escola-selecionadas', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.background && data.job_id) {
                        if (migrationAndamento) {
                            migrationAndamento.detalhe = 'A migration está em execução no servidor. Pode levar vários minutos.';
                        }
                        btnSubmit.textContent = 'Rodando...';
                        acompanharCarga(data.job_id, btnSubmit, origText);
                        return;
                    }
                    if (data.success && data.resultado && data.resultado.status === 'ok') {
                        btnSubmit.textContent = 'Finalizada';
                        concluirMigration('Migration finalizada. ' + (data.resultado.message || 'Executada com sucesso.'));
                        return;
                    }
                    pararAndamento();
                    var erro = (data && data.error) || 'desconhecido';
                    var elErro = pintarStatusMigration('erro');
                    if (elErro) elErro.textContent = erro;
                    alert('Erro: ' + erro);
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                })
                .catch(function(err) {
                    pararAndamento();
                    var elErro = pintarStatusMigration('erro');
                    if (elErro) elErro.textContent = 'Erro de rede: ' + err.message;
                    alert('Erro de rede: ' + err.message);
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                });
        });
    }

    document.querySelectorAll('.btn-marcar-executada').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var escolaId = this.getAttribute('data-escola-id');
            var escolaNome = this.getAttribute('data-escola-nome') || ('Escola #' + escolaId);
            var btnEl = this;
            abrirConfirmacao('Marcar todas as migrations pendentes da escola ' + escolaNome + ' como executadas, sem rodar o SQL.').then(function(senha) {
            if (!senha) return;
            btnEl.disabled = true;
            btnEl.textContent = '...';
            fetch(baseUrl + '/master/migrations/marcar-executadas', { method: 'POST', body: formComConfirmacao(senha, { escola_id: escolaId }) })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        btnEl.textContent = 'OK';
                        btnEl.className = 'bg-green-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium cursor-default';
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        alert('Erro: ' + (data.error || 'desconhecido'));
                        btnEl.disabled = false;
                        btnEl.textContent = 'Ignorar';
                    }
                })
                .catch(function(err) {
                    alert('Erro de rede: ' + err.message);
                    btnEl.disabled = false;
                    btnEl.textContent = 'Ignorar';
                });
            });
        });
    });
})();
</script>
