<?php

use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

$itens = is_array($itens ?? null) ? $itens : [];
$filtros = is_array($filtros ?? null) ? $filtros : [];
$cursos = is_array($cursos ?? null) ? $cursos : [];
$series = is_array($series ?? null) ? $series : [];
$anosLetivos = is_array($anos_letivos ?? null) ? $anos_letivos : [];
$anoAtual = (int) ($ano_atual ?? (int) date('Y'));
$schemaPronto = !empty($schema_pronto);
$csrf_token = $csrf_token ?? '';

$page_header_title = 'Regras de Aprovação';
$page_header_subtitle = 'Critérios de aprovação, recuperação e frequência. O boletim usa esta regra para definir a situação do aluno.';
$filtrosAtivos = 0;
if ((int) ($filtros['ano_letivo'] ?? 0) !== $anoAtual) {
    $filtrosAtivos++;
}
if ((int) ($filtros['curso_id'] ?? 0) > 0) {
    $filtrosAtivos++;
}
if ((int) ($filtros['serie_id'] ?? 0) > 0) {
    $filtrosAtivos++;
}
ob_start();
?>
<button type="button" onclick="openFilterDrawer()"
        class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
    Filtros
    <?php if ($filtrosAtivos > 0): ?>
        <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
    <?php endif; ?>
</button>
<a href="<?= URL ?>/admin/regras-academicas/nova"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova regra de aprovação
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
                        <p><?= (int) ($filtros['ano_letivo'] ?? 0) > 0 ? 'Nenhuma regra de aprovação em ' . (int) $filtros['ano_letivo'] . '.' : 'Nenhuma regra de aprovação cadastrada' ?></p>
                        <p class="text-sm mt-1">Sem regra, o boletim continua usando a nota mínima do próprio evento.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($itens as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $row['nome']) ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?= !empty($row['ano_letivo']) ? (int) $row['ano_letivo'] : 'Qualquer ano' ?>
                            <?php if (!empty($row['curso_nome'])): ?>
                                · <?= htmlspecialchars((string) $row['curso_nome']) ?>
                            <?php endif; ?>
                            <?php if (!empty($row['serie_nome'])): ?>
                                · <?= htmlspecialchars((string) $row['serie_nome']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?php
                                $escalaLinha = (string) ($row['escala'] ?? '10');
                                $conceitoLinha = (string) ($row['conceito_minimo'] ?? '');
                                $rotuloEscala = \App\Modulos\RegrasAcademicas\Models\RegraAcademica::ESCALAS[$escalaLinha] ?? 'Numérica (0 a 10)';
                                $conceitosLinha = \App\Modulos\RegrasAcademicas\Models\RegraAcademica::conceitosDa($escalaLinha);
                            ?>
                            <?php if ($conceitosLinha !== null && $conceitoLinha !== ''): ?>
                                A partir de <?= htmlspecialchars($conceitosLinha[$conceitoLinha] ?? $conceitoLinha) ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($rotuloEscala) ?></div>
                            <?php else: ?>
                                <?= number_format((float) $row['media_minima'], 1, ',', '.') ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($rotuloEscala) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['usar_frequencia'])): ?>
                                <div class="text-xs text-gray-500"><?= number_format((float) $row['frequencia_minima'], 0) ?>% freq.</div>
                            <?php else: ?>
                                <div class="text-xs text-gray-400">Freq. não exige</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?= htmlspecialchars(\App\Modulos\RegrasAcademicas\Models\RegraAcademica::RECUPERACAO_TIPOS[$row['recuperacao_tipo'] ?? ''] ?? (string) ($row['recuperacao_tipo'] ?? '—')) ?>
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

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col" aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Filtrar regras</h3>
            <p class="mt-0.5 text-xs text-gray-500">Ano letivo, curso e série.</p>
        </div>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar filtros"><i class="fa-solid fa-xmark text-xl"></i></button>
    </div>
    <form method="get" action="<?= URL ?>/admin/regras-academicas" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro-ano" class="block text-sm font-medium text-gray-700 mb-1.5">Ano letivo</label>
                <select id="filtro-ano" name="ano_letivo" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Todos</option>
                    <?php foreach ($anosLetivos as $ano): ?>
                        <?php $ano = (int) $ano; ?>
                        <option value="<?= $ano ?>" <?= (int) ($filtros['ano_letivo'] ?? 0) === $ano ? 'selected' : '' ?>><?= $ano ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro-curso" class="block text-sm font-medium text-gray-700 mb-1.5">Ciclo/Etapa/Curso</label>
                <select id="filtro-curso" name="curso_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Todos</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($filtros['curso_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro-serie" class="block text-sm font-medium text-gray-700 mb-1.5">Série / Ano Escolar</label>
                <select id="filtro-serie" name="serie_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Todas as Séries / Anos</option>
                    <?php foreach ($series as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) ($filtros['serie_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $s['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <a href="<?= URL ?>/admin/regras-academicas" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">Limpar</a>
            <button type="submit" class="flex-1 px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors">Aplicar filtros</button>
        </div>
    </form>
</aside>
<script>
function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeFilterDrawer();
});
</script>
