<?php
$filtros = is_array($filtros ?? null) ? $filtros : [];
$anos_letivo = is_array($anos_letivo ?? null) ? $anos_letivo : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$kpis = is_array($kpis ?? null) ? $kpis : [];
$linhas = is_array($linhas ?? null) ? $linhas : [];
$tipos_alerta = is_array($tipos_alerta ?? null) ? $tipos_alerta : [];
$executar = !empty($filtros['executar']);
$anoSel = (int) ($filtros['ano_letivo_id'] ?? 0);
$turmaSel = (int) ($filtros['turma_id'] ?? 0);
$tipoSel = (string) ($filtros['tipo'] ?? '');

$queryBase = static function (array $extra = []) use ($filtros): string {
    $params = array_merge([
        'ano_letivo_id' => (int) ($filtros['ano_letivo_id'] ?? 0),
        'turma_id' => (int) ($filtros['turma_id'] ?? 0),
        'tipo' => (string) ($filtros['tipo'] ?? ''),
        'executar' => 1,
    ], $extra);
    $parts = [];
    foreach ($params as $k => $v) {
        if ($k === 'tipo' && $v === '') {
            continue;
        }
        if ($k === 'turma_id' && (int) $v === 0) {
            continue;
        }
        $parts[] = urlencode($k) . '=' . urlencode((string) $v);
    }

    return URL . '/admin/saude-academica?' . implode('&', $parts);
};
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Saúde Acadêmica</h2>
            <p class="text-gray-600 text-sm">Diagnóstico read-only de vínculos aluno, matrícula e lista de chamada.</p>
        </div>
    </div>
</div>

<div class="mb-6 border-b border-gray-200">
    <nav class="flex flex-wrap gap-2" aria-label="Áreas da Saúde Acadêmica">
        <a href="<?= URL ?>/admin/saude-academica"
           class="px-4 py-3 text-sm font-semibold border-b-2 border-blue-600 text-blue-700">
            <i class="fa-solid fa-link mr-2"></i>Saúde cadastral
        </a>
        <a href="<?= URL ?>/admin/saude-academica?aba=aprendizagem"
           class="px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
            <i class="fa-solid fa-chart-line mr-2"></i>Saúde da aprendizagem
        </a>
    </nav>
</div>

<?php if (empty($schema_matricula)): ?>
<div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
    A tabela <strong>matricula</strong> não está disponível. Alguns alertas ficarão indisponíveis até aplicar as migrations 022–027.
</div>
<?php endif; ?>

<?php if (empty($schema_chamada)): ?>
<div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
    A migration <strong>059_lista_chamada</strong> não foi aplicada. Alertas de lista de chamada ficarão indisponíveis.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
    <form method="get" action="<?= URL ?>/admin/saude-academica" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label for="ano_letivo_id" class="block text-sm font-medium text-gray-700 mb-1">Ano letivo</label>
            <select id="ano_letivo_id" name="ano_letivo_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <?php if (empty($anos_letivo)): ?>
                <option value="">Nenhum ano letivo ativo</option>
                <?php else: ?>
                <?php foreach ($anos_letivo as $ano): ?>
                <option value="<?= (int) ($ano['id'] ?? 0) ?>" <?= $anoSel === (int) ($ano['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= (int) ($ano['ano'] ?? 0) ?>
                </option>
                <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div>
            <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1">Turma (opcional)</label>
            <select id="turma_id" name="turma_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todas</option>
                <?php foreach ($turmas as $t): ?>
                <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= $turmaSel === (int) ($t['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($t['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de alerta</label>
            <select id="tipo" name="tipo"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos</option>
                <?php foreach ($tipos_alerta as $key => $label): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $tipoSel === $key ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <input type="hidden" name="executar" value="1">
            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                <i class="fa-solid fa-magnifying-glass mr-2"></i>
                Analisar
            </button>
        </div>
    </form>
</div>

<?php if ($executar && !empty($kpis)): ?>
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <p class="text-xs font-medium text-gray-500 uppercase">Total</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($kpis['total'] ?? 0) ?></p>
    </div>
    <?php foreach ($tipos_alerta as $key => $label): ?>
    <a href="<?= htmlspecialchars($queryBase(['tipo' => $key]), ENT_QUOTES, 'UTF-8') ?>"
       class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-indigo-300 transition-colors">
        <p class="text-xs font-medium text-gray-500 uppercase leading-tight"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="text-2xl font-bold <?= $key === 'extra_apenas_matricula' ? 'text-indigo-700' : 'text-amber-700' ?> mt-1">
            <?= (int) ($kpis[$key] ?? 0) ?>
        </p>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-100">
        <h3 class="text-base font-semibold text-gray-900">Resultados</h3>
        <?php if (!$executar): ?>
        <p class="text-sm text-gray-500 mt-1">Selecione o ano letivo e clique em <strong>Analisar</strong>.</p>
        <?php else: ?>
        <p class="text-sm text-gray-500 mt-1"><?= count($linhas) ?> registro(s) encontrado(s). Limite: 500 por tipo.</p>
        <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RA</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alerta</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalhe</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <?php if (!$executar): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 text-sm">
                        <i class="fa-solid fa-heart-pulse text-4xl text-gray-300 mb-3 block"></i>
                        Nenhuma análise executada ainda. Selecione o ano letivo e clique em <strong>Analisar</strong>.
                    </td>
                </tr>
                <?php elseif (empty($linhas)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-green-700 text-sm font-medium">Nenhum alerta encontrado para os filtros selecionados.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($linhas as $ln): ?>
                <?php
                $aid = (int) ($ln['aluno_id'] ?? 0);
                $tipoAlerta = (string) ($ln['tipo_alerta'] ?? '');
                $turmaLinkId = (int) ($ln['turma_id'] ?? 0);
                if ($tipoAlerta === 'extra_apenas_matricula' && !empty($ln['turma_extra_nome'])) {
                    // link para turma extra quando disponível no row — fallback principal
                }
                ?>
                <tr class="hover:bg-gray-50/80">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($ln['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars((string) ($ln['ra'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $tipoAlerta === 'extra_apenas_matricula' ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-900' ?>">
                            <?= htmlspecialchars($tipos_alerta[$tipoAlerta] ?? $tipoAlerta, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 max-w-md">
                        <?= htmlspecialchars((string) ($ln['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($ln['turma_nome'])): ?>
                        <span class="block text-xs text-gray-500 mt-0.5">Principal: <?= htmlspecialchars((string) $ln['turma_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($ln['turma_matricula_nome'])): ?>
                        <span class="block text-xs text-gray-500">Matrícula ativa: <?= htmlspecialchars((string) $ln['turma_matricula_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($ln['turma_extra_nome'])): ?>
                        <span class="block text-xs text-gray-500">Extra: <?= htmlspecialchars((string) $ln['turma_extra_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm whitespace-nowrap">
                        <div class="flex flex-wrap items-center gap-2">
                        <?php if ($aid > 0): ?>
                        <a href="<?= URL ?>/admin/students/<?= $aid ?>#section-matriculas-aluno"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-xs font-medium hover:bg-blue-100 hover:border-blue-300 transition-colors">
                            <i class="fa-solid fa-circle-info text-blue-600"></i>
                            Ficha
                        </a>
                        <?php endif; ?>
                        <?php if ($turmaLinkId > 0 && $tipoAlerta === 'sem_chamada'): ?>
                        <a href="<?= URL ?>/admin/turmas/<?= $turmaLinkId ?>/lista-chamada"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-700 text-xs font-medium hover:bg-gray-100 transition-colors">
                            Lista chamada
                        </a>
                        <?php endif; ?>
                        <?php if ($tipoAlerta === 'pending_sem_turma' && $aid > 0): ?>
                        <a href="<?= URL ?>/admin/students/<?= $aid ?>/edit"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-green-200 bg-green-50 text-green-700 text-xs font-medium hover:bg-green-100 transition-colors">
                            Editar
                        </a>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
