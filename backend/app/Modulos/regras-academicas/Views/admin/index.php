<?php
require_once __DIR__ . '/../../Models/RegraAcademica.php';

use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

$itens = is_array($itens ?? null) ? $itens : [];
$filtros = is_array($filtros ?? null) ? $filtros : [];
$cursos = is_array($cursos ?? null) ? $cursos : [];
$series = is_array($series ?? null) ? $series : [];
$anosLetivos = is_array($anos_letivos ?? null) ? $anos_letivos : [];
$schemaPronto = !empty($schema_pronto);
$csrf_token = $csrf_token ?? '';

$page_header_title = 'Regras Acadêmicas';
$page_header_subtitle = 'Critérios de aprovação, recuperação e frequência. O boletim consulta este motor — não recodifique média 6 na tela.';
ob_start();
?>
<a href="<?= URL ?>/admin/regras-academicas/nova"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova regra
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code class="text-sm">2026_08_22_regras_academicas.sql</code> no painel Master antes de cadastrar.
</div>
<?php endif; ?>

<form method="GET" action="<?= URL ?>/admin/regras-academicas" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Ano letivo</label>
        <select name="ano_letivo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="0">Todos</option>
            <?php foreach ($anosLetivos as $ano): ?>
                <?php $ano = (int) $ano; ?>
                <option value="<?= $ano ?>" <?= (int) ($filtros['ano_letivo'] ?? 0) === $ano ? 'selected' : '' ?>><?= $ano ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Curso</label>
        <select name="curso_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="0">Todos</option>
            <?php foreach ($cursos as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($filtros['curso_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Série</label>
        <select name="serie_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="0">Todas</option>
            <?php foreach ($series as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) ($filtros['serie_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $s['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90">Filtrar</button>
        <a href="<?= URL ?>/admin/regras-academicas" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Limpar</a>
    </div>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vigência</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Média / Freq.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recuperação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Versão</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($itens)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-scale-balanced text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhuma regra acadêmica cadastrada</p>
                        <p class="text-sm mt-1">Sem regra, o boletim continua usando a nota mínima do próprio evento.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($itens as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $row['nome']) ?></div>
                            <?php if (!empty($row['codigo'])): ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars((string) $row['codigo']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?= !empty($row['ano_letivo']) ? (int) $row['ano_letivo'] : 'Qualquer ano' ?>
                            <?php if (!empty($row['serie_nome'])): ?>
                                · <?= htmlspecialchars((string) $row['serie_nome']) ?>
                            <?php elseif (!empty($row['curso_nome'])): ?>
                                · <?= htmlspecialchars((string) $row['curso_nome']) ?>
                            <?php endif; ?>
                            <?php if (!empty($row['materia_nome'])): ?>
                                <div class="text-xs text-gray-500">Exceção: <?= htmlspecialchars((string) $row['materia_nome']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?= number_format((float) $row['media_minima'], 1, ',', '.') ?>
                            <?php if (!empty($row['usar_frequencia'])): ?>
                                <div class="text-xs text-gray-500"><?= number_format((float) $row['frequencia_minima'], 0) ?>% freq.</div>
                            <?php else: ?>
                                <div class="text-xs text-gray-400">Freq. não exige</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?= htmlspecialchars(RegraAcademica::RECUPERACAO_TIPOS[$row['recuperacao_tipo'] ?? ''] ?? (string) ($row['recuperacao_tipo'] ?? '—')) ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">v<?= (int) ($row['versao'] ?? 1) ?></td>
                        <td class="px-6 py-4">
                            <?php if (!empty($row['ativo'])): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Ativa</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">Inativa</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <?php ob_start(); ?>
                            <a href="<?= URL ?>/admin/regras-academicas/<?= (int) $row['id'] ?>/editar" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                            </a>
                            <form method="POST" action="<?= URL ?>/admin/regras-academicas/<?= (int) $row['id'] ?>/delete" onsubmit="return confirm('Excluir esta regra? O histórico de versões também será removido.');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                </button>
                            </form>
                            <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                            <?php $row_actions_dropdown_id = 'row-actions-regra-' . (int) $row['id']; ?>
                            <?php include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
