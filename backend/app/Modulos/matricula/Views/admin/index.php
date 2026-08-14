<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$statusColors = [
    'rascunho'              => 'bg-gray-100 text-gray-700',
    'aguardando_contrato'   => 'bg-yellow-100 text-yellow-700',
    'aguardando_assinatura' => 'bg-blue-100 text-blue-700',
    'confirmada'            => 'bg-green-100 text-green-700',
    'enturmada'             => 'bg-emerald-100 text-emerald-700',
    'abandonada'            => 'bg-orange-100 text-orange-700',
    'cancelada'             => 'bg-red-100 text-red-700',
    'lista_espera'          => 'bg-purple-100 text-purple-700',
];
$statusLabels = [
    'rascunho'              => 'Rascunho',
    'aguardando_contrato'   => 'Aguard. Contrato',
    'aguardando_assinatura' => 'Aguard. Assinatura',
    'confirmada'            => 'Confirmada',
    'enturmada'             => 'Enturmada',
    'abandonada'            => 'Abandonada',
    'cancelada'             => 'Cancelada',
    'lista_espera'          => 'Lista de Espera',
];

$page_header_title    = 'Matrículas';
$page_header_subtitle = 'Gerencie matrículas, rematrículas e contratos.';
ob_start(); ?>
<a href="<?= URL ?>/admin/enrollment/config" class="btn-secondary text-sm">
    <i class="fa-solid fa-gear mr-1.5"></i> Configuração
</a>
<a href="<?= URL ?>/admin/enrollment/rematricula-lote" class="btn-secondary text-sm">
    <i class="fa-solid fa-users mr-1.5"></i> Rematrícula em lote
</a>
<a href="<?= URL ?>/admin/enrollment/score" class="btn-secondary text-sm">
    <i class="fa-solid fa-chart-line mr-1.5"></i> Score
</a>
<a href="<?= URL ?>/admin/enrollment/create" class="btn-primary text-sm">
    <i class="fa-solid fa-plus mr-1.5"></i> Nova Matrícula
</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<div class="mb-4 p-4 rounded-xl border border-blue-100 bg-blue-50/80 flex flex-wrap items-center justify-between gap-3">
    <div class="text-sm text-blue-900">
        <p class="font-medium"><i class="fa-solid fa-link mr-1.5"></i> Captação pública de interesse</p>
        <p class="text-blue-700/90 mt-0.5">Compartilhe este link para famílias enviarem interesse de matrícula (origem: site).</p>
    </div>
    <a href="<?= URL ?>/matricula/interesse" target="_blank" rel="noopener"
       class="inline-flex items-center gap-2 text-sm font-medium text-blue-700 bg-white border border-blue-200 rounded-lg px-3 py-2 hover:bg-blue-50">
        <span class="truncate max-w-xs"><?= $esc(rtrim((string) URL, '/') . '/matricula/interesse') ?></span>
        <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
    </a>
</div>

<!-- Contadores por status -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <?php foreach (['rascunho','aguardando_assinatura','confirmada','enturmada'] as $st): ?>
    <a href="?status=<?= $esc($st) ?>"
       class="bg-white rounded-xl border border-gray-200 p-4 text-center hover:shadow-sm transition <?= ($filters['status'] ?? '') === $st ? 'ring-2 ring-blue-500' : '' ?>">
        <div class="text-2xl font-bold text-gray-800"><?= (int)($counts[$st] ?? 0) ?></div>
        <div class="text-xs text-gray-500 mt-0.5"><?= $esc($statusLabels[$st] ?? $st) ?></div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-48">
        <label class="block text-xs text-gray-500 mb-1">Buscar</label>
        <input type="text" name="q" value="<?= $esc($filters['q'] ?? '') ?>"
               placeholder="Nome do aluno ou responsável"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Status</label>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Todos</option>
            <?php foreach ($statusLabels as $v => $l): ?>
            <option value="<?= $esc($v) ?>" <?= ($filters['status'] ?? '') === $v ? 'selected' : '' ?>><?= $esc($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Tipo</label>
        <select name="tipo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Todos</option>
            <option value="nova" <?= ($filters['tipo'] ?? '') === 'nova' ? 'selected' : '' ?>>Matrícula Nova</option>
            <option value="rematricula" <?= ($filters['tipo'] ?? '') === 'rematricula' ? 'selected' : '' ?>>Rematrícula</option>
            <option value="transferencia" <?= ($filters['tipo'] ?? '') === 'transferencia' ? 'selected' : '' ?>>Transferência</option>
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Ano Letivo</label>
        <select name="ano_letivo_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Todos</option>
            <?php foreach ($anos_letivos as $al): ?>
            <option value="<?= (int)$al['id'] ?>" <?= (int)($filters['ano_letivo_id'] ?? 0) === (int)$al['id'] ? 'selected' : '' ?>>
                <?= $esc($al['ano']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn-primary text-sm px-4 py-2">Filtrar</button>
    <a href="<?= URL ?>/admin/enrollment" class="btn-secondary text-sm px-4 py-2">Limpar</a>
</form>

<!-- Tabela -->
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <?php if (empty($list)): ?>
    <div class="p-10 text-center text-gray-400">
        <i class="fa-regular fa-folder-open text-3xl mb-3 block"></i>
        Nenhuma matrícula encontrada.
    </div>
    <?php else: ?>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">#</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Aluno</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Responsável</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Tipo</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Turma</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Status</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Criado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($list as $e): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-400 text-xs">#<?= (int)$e['id'] ?></td>
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-800"><?= $esc($e['aluno_nome']) ?></div>
                </td>
                <td class="px-4 py-3 text-gray-600"><?= $esc($e['resp_nome']) ?></td>
                <td class="px-4 py-3">
                    <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                        <?= $esc(['nova'=>'Nova','rematricula'=>'Rematrícula','transferencia'=>'Transferência'][$e['tipo']] ?? $e['tipo']) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-600 text-xs"><?= $esc($e['turma_nome'] ?? '—') ?></td>
                <td class="px-4 py-3">
                    <span class="text-xs px-2 py-0.5 rounded-full <?= $statusColors[$e['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                        <?= $esc($statusLabels[$e['status']] ?? $e['status']) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-400 text-xs">
                    <?= date('d/m/Y', strtotime($e['created_at'])) ?>
                </td>
                <td class="px-4 py-3">
                    <a href="<?= URL ?>/admin/enrollment/<?= (int)$e['id'] ?>"
                       class="text-blue-600 hover:text-blue-800 text-xs font-medium">Ver →</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-3 border-t border-gray-100 flex justify-between items-center text-sm">
        <span class="text-gray-500"><?= (int)$total ?> registros</span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&<?= http_build_query(array_filter($filters)) ?>" class="btn-secondary px-3 py-1 text-xs">← Anterior</a><?php endif; ?>
            <span class="px-3 py-1 text-xs text-gray-600">Página <?= $page ?> de <?= $total_pages ?></span>
            <?php if ($page < $total_pages): ?><a href="?page=<?= $page+1 ?>&<?= http_build_query(array_filter($filters)) ?>" class="btn-secondary px-3 py-1 text-xs">Próxima →</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
