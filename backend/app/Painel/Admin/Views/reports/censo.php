<?php
$alunos = is_array($alunos ?? null) ? $alunos : [];
$unidades = is_array($unidades ?? null) ? $unidades : [];
$stats = is_array($stats ?? null) ? $stats : ['total' => 0, 'com_inep' => 0, 'sem_inep' => 0, 'sem_mae' => 0, 'sem_cpf' => 0];
$unidadeId = (int) ($unidade_id ?? 0);
$temUnidades = !empty($tem_unidades);
$schemaPronto = !empty($schema_pronto);

$queryExport = [];
if ($temUnidades && $unidadeId > 0) {
    $queryExport['unidade_id'] = $unidadeId;
}

$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtData = static function ($v) {
    $v = trim((string) $v);
    if ($v === '' || $v === '0000-00-00') {
        return '';
    }
    $ts = strtotime($v);
    return $ts ? date('d/m/Y', $ts) : '';
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Censo / INEP</h1>
    <p class="text-gray-600 mt-1">Acompanhe o preenchimento dos dados exigidos pelo Educacenso e exporte o arquivo para o Censo Escolar.</p>
</div>

<?php if (!$schemaPronto): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6">
    <p class="font-semibold"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Migração do Censo pendente</p>
    <p class="text-sm mt-1">Os campos de Censo (código INEP do aluno, filiação) ainda não existem neste banco. Execute a migração <code>2026_06_25_censo_inep.sql</code> em <strong>Master &rarr; Migrações</strong> para habilitar o preenchimento e a exportação completa.</p>
</div>
<?php endif; ?>

<!-- Cards de resumo -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center"><i class="fa-solid fa-users text-indigo-500"></i></div>
            <div>
                <p class="text-2xl font-bold text-gray-900"><?= (int) $stats['total'] ?></p>
                <p class="text-sm text-gray-500">Alunos ativos</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center"><i class="fa-solid fa-circle-check text-green-500"></i></div>
            <div>
                <p class="text-2xl font-bold text-gray-900"><?= (int) $stats['com_inep'] ?></p>
                <p class="text-sm text-gray-500">Com código INEP</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center"><i class="fa-solid fa-circle-exclamation text-amber-500"></i></div>
            <div>
                <p class="text-2xl font-bold text-gray-900"><?= (int) $stats['sem_inep'] ?></p>
                <p class="text-sm text-gray-500">Sem código INEP</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center"><i class="fa-solid fa-id-card text-rose-500"></i></div>
            <div>
                <p class="text-2xl font-bold text-gray-900"><?= (int) $stats['sem_mae'] ?> / <?= (int) $stats['sem_cpf'] ?></p>
                <p class="text-sm text-gray-500">Sem filiação / sem CPF</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtros + exportação -->
<form method="GET" action="<?= URL ?>/admin/reports/censo" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 md:p-6 mb-6">
    <div class="flex flex-wrap items-end gap-4">
        <?php if ($temUnidades): ?>
        <label class="block min-w-[220px]">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Unidade</span>
            <span class="relative block">
            <select name="unidade_id" class="appearance-none w-full h-11 rounded-xl border border-gray-300 bg-white px-3 pr-10 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100">
                <option value="0">Todas as unidades</option>
                <?php foreach ($unidades as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= $unidadeId === (int) $u['id'] ? 'selected' : '' ?>><?= $esc($u['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
            </span>
        </label>
        <?php endif; ?>
        <div class="flex gap-3">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-xl font-semibold shadow-sm hover:opacity-90 transition-opacity"><i class="fa-solid fa-filter mr-2"></i>Filtrar</button>
            <a href="<?= URL ?>/admin/students/export-censo<?= !empty($queryExport) ? '?' . $esc(http_build_query($queryExport)) : '' ?>"
               class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition-colors">
                <i class="fa-solid fa-file-csv mr-2"></i>Exportar Censo (CSV)
            </a>
        </div>
    </div>
</form>

<!-- Tabela -->
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-gray-400 border-b border-gray-100 bg-gray-50">
                    <th class="py-3 px-4 font-semibold">Aluno</th>
                    <th class="py-3 px-4 font-semibold">Turma</th>
                    <?php if ($temUnidades): ?><th class="py-3 px-4 font-semibold">Unidade</th><?php endif; ?>
                    <th class="py-3 px-4 font-semibold">Nascimento</th>
                    <th class="py-3 px-4 font-semibold">Código INEP</th>
                    <th class="py-3 px-4 font-semibold">Pendências</th>
                    <th class="py-3 px-4 font-semibold text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($alunos)): ?>
                <tr><td colspan="7" class="py-8 text-center text-gray-400">Nenhum aluno encontrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($alunos as $a):
                    $temInep = trim((string) ($a['codigo_inep'] ?? '')) !== '';
                    $pendencias = [];
                    if (trim((string) ($a['nome_mae'] ?? '')) === '') { $pendencias[] = 'Filiação'; }
                    if (trim((string) ($a['cpf'] ?? '')) === '') { $pendencias[] = 'CPF'; }
                    if (trim((string) ($a['data_nasc'] ?? '')) === '' || (string) ($a['data_nasc'] ?? '') === '0000-00-00') { $pendencias[] = 'Nascimento'; }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="py-3 px-4 font-medium text-gray-800"><?= $esc($a['nome']) ?></td>
                    <td class="py-3 px-4 text-gray-600"><?= $esc($a['turma_nome'] ?? '') ?: '<span class="text-gray-300">—</span>' ?></td>
                    <?php if ($temUnidades): ?><td class="py-3 px-4 text-gray-600"><?= $esc($a['unidade_nome'] ?? '') ?: '<span class="text-gray-300">—</span>' ?></td><?php endif; ?>
                    <td class="py-3 px-4 text-gray-600"><?= $esc($fmtData($a['data_nasc'] ?? '')) ?: '<span class="text-gray-300">—</span>' ?></td>
                    <td class="py-3 px-4">
                        <?php if ($temInep): ?>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800"><i class="fa-solid fa-check text-[10px]"></i><?= $esc($a['codigo_inep']) ?></span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Pendente</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4">
                        <?php if (empty($pendencias)): ?>
                            <span class="text-xs text-green-600 font-medium">Completo</span>
                        <?php else: ?>
                            <?php foreach ($pendencias as $p): ?>
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-rose-50 text-rose-700 mr-1"><?= $esc($p) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-right whitespace-nowrap">
                        <a href="<?= URL ?>/admin/students/<?= (int) $a['id'] ?>/edit" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                            <i class="fa-solid fa-pen-to-square mr-1"></i>Editar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
