<?php
$cobertura = $cobertura ?? ['previstas' => 0, 'trabalhadas' => 0, 'percentual' => null];
$coberturaComp = $cobertura_componente ?? [];
$pendentes = $pendentes ?? [];
$habilidades = $habilidades ?? [];
$componentes = $componentes ?? [];
$pct = $cobertura['percentual'];
$corPct = $pct === null ? 'text-gray-400' : ($pct > 95 ? 'text-green-600' : ($pct >= 80 ? 'text-amber-600' : 'text-red-600'));

ob_start();
?>
<a href="<?= URL ?>/admin/plano-curso"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-clipboard-list mr-2 text-gray-500"></i> Planos de Curso
</a>
<?php
$page_header_actions = ob_get_clean();
$page_header_title = 'BNCC';
$page_header_subtitle = 'Catálogo de habilidades, importação e cobertura curricular por componente.';
include __DIR__ . '/../_partials/page_header_list.php';

if (!($schema_pronto ?? false)): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 mb-6">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i> A migration da BNCC ainda não foi executada neste tenant. Rode <code>2026_06_25_bncc_curriculo.sql</code> no painel Master.
    </div>
<?php endif; ?>

<?php if ($flash_message ?? false): ?>
    <div class="rounded-lg border px-4 py-3 text-sm mb-6 <?= ($flash_type ?? '') === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700' ?>">
        <?= htmlspecialchars((string) $flash_message) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Cobertura Geral</p>
        <p class="text-4xl font-bold <?= $corPct ?>"><?= $pct !== null ? number_format((float) $pct, 1, ',', '') . '%' : '—' ?></p>
        <p class="mt-2 text-xs text-gray-500"><?= (int) $cobertura['trabalhadas'] ?> de <?= (int) $cobertura['previstas'] ?> habilidades previstas trabalhadas</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 lg:col-span-2">
        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">Cobertura por componente</p>
        <?php if (empty($coberturaComp)): ?>
            <p class="text-sm text-gray-500">Vincule habilidades aos planos de curso para acompanhar a cobertura.</p>
        <?php else: foreach ($coberturaComp as $c):
            $cp = $c['percentual']; ?>
            <div class="mb-3">
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-700"><?= htmlspecialchars((string) $c['componente']) ?></span>
                    <span class="text-gray-500"><?= (int) $c['trabalhadas'] ?>/<?= (int) $c['previstas'] ?> · <?= $cp !== null ? number_format((float) $cp, 0) . '%' : '—' ?></span>
                </div>
                <div class="w-full h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full <?= $cp === null ? 'bg-gray-300' : ($cp > 95 ? 'bg-green-500' : ($cp >= 80 ? 'bg-amber-500' : 'bg-red-500')) ?>" style="width: <?= $cp !== null ? (float) $cp : 0 ?>%"></div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php if (!empty($pendentes)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">Habilidades pendentes</h3>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($pendentes as $p): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-50 text-red-700 text-xs font-medium" title="<?= htmlspecialchars((string) $p['descricao']) ?>">
                <?= htmlspecialchars((string) $p['codigo']) ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
    <form method="post" action="<?= URL ?>/admin/bncc/importar" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
        <div class="flex-1 min-w-[240px]">
            <label class="block text-sm font-medium text-gray-700 mb-2">Importar habilidades (JSON ou CSV)</label>
            <input type="file" name="arquivo" accept=".json,.csv,.txt" required
                   class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-green-50 file:text-green-700 file:font-medium hover:file:bg-green-100">
            <p class="mt-1 text-xs text-gray-500">CSV: codigo;descricao;etapa;componente;ano_serie;unidade_tematica;objeto_conhecimento</p>
        </div>
        <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors">
            <i class="fa-solid fa-upload mr-2"></i> Importar
        </button>
    </form>
</div>

<form method="get" action="<?= URL ?>/admin/bncc" class="flex flex-wrap items-end gap-3 mb-4">
    <div class="flex-1 min-w-[200px]">
        <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
        <input type="text" name="busca" value="<?= htmlspecialchars((string) ($busca ?? '')) ?>" placeholder="Código ou descrição"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
    </div>
    <div class="min-w-[200px]">
        <label class="block text-sm font-medium text-gray-700 mb-2">Componente</label>
        <select name="componente" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
            <option value="">Todos</option>
            <?php foreach ($componentes as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= ($componente ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors">Filtrar</button>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Código', 'Descrição', 'Componente', 'Ano/Série'] as $h): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($habilidades)): ?>
                    <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">Nenhuma habilidade encontrada.</td></tr>
                <?php else: foreach ($habilidades as $h): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm font-semibold text-gray-900 whitespace-nowrap"><?= htmlspecialchars((string) $h['codigo']) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) $h['descricao']) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-600 whitespace-nowrap"><?= htmlspecialchars((string) ($h['componente'] ?? '')) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-600 whitespace-nowrap"><?= htmlspecialchars((string) ($h['ano_serie'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
