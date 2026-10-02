<?php
$itens = is_array($itens ?? null) ? $itens : [];
$schemaPronto = !empty($schema_pronto);
$csrf_token = $csrf_token ?? '';

$page_header_title = 'Modelo de Boletim';
$page_header_subtitle = 'Cadastre o documento (oficial ou extra) e aponte a regra de aprovação. A Avaliação só escolhe em qual modelo entra.';
ob_start();
?>
<a href="<?= URL ?>/admin/boletim"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
    <i class="fa-solid fa-table mr-2 text-gray-500"></i>
    Avaliações
</a>
<a href="<?= URL ?>/admin/reports/boletim-coordenacao?fonte=vida_escolar"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
    <i class="fa-solid fa-file-pdf mr-2 text-gray-500"></i>
    Relatório PDF
</a>
<a href="<?= URL ?>/admin/boletins/novo"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Novo modelo
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php
if (!class_exists('PeriodoLetivo')) {
    require_once __DIR__ . '/../../../../Core/PeriodoLetivo.php';
}
?>
<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode as migrations <code class="text-sm">2026_09_02_boletins.sql</code> e <code class="text-sm">2026_09_02_boletins_regra_academica.sql</code> no painel Master antes de cadastrar.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Finalidade</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Regra de aprovação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matérias</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eventos de notas</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($itens === []): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <p>Nenhum modelo de boletim cadastrado.</p>
                        <p class="text-sm mt-1">Crie o oficial da vida escolar e, se precisar, um extra para cursos complementares.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($itens as $item): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                        <?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= (($item['finalidade'] ?? '') === 'complementar') ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                            <?= (($item['finalidade'] ?? '') === 'complementar') ? 'Extra' : 'Oficial' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= !empty($item['ano_letivo']) ? (int) $item['ano_letivo'] : '—' ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php $nomeRa = trim((string) ($item['regra_academica_nome'] ?? '')); ?>
                        <?php if ($nomeRa !== ''): ?>
                            <?= htmlspecialchars($nomeRa, ENT_QUOTES, 'UTF-8') ?>
                            <?php $minRa = (float) (($item['criterios']['nota_minima_aprovacao'] ?? 6)); ?>
                            <div class="text-xs text-gray-500">mín. <?= htmlspecialchars(number_format($minRa, 2, ',', ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <?php else: ?>
                            <span class="text-gray-400">Motor (fallback 6,0)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= count($item['materias_ids'] ?? []) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= (int) ($item['eventos_notas_qtd'] ?? 0) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php
                        $anoModelo = (int) ($item['ano_letivo'] ?? 0);
                        $rotuloPeriodo = mb_strtolower((string) (PeriodoLetivo::doAno($anoModelo > 0 ? $anoModelo : (int) date('Y'))['rotulo_campo'] ?? 'Bimestre'), 'UTF-8');
                        $eventosVinculados = is_array($item['eventos_notas'] ?? null) ? $item['eventos_notas'] : [];
                        ob_start();
                        ?>
                        <a href="<?= URL ?>/admin/boletins/<?= (int) $item['id'] ?>/editar"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center shrink-0"></i> Editar
                        </a>
                        <button type="button"
                                class="js-notas-eventos flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap">
                            <i class="fa-solid fa-file-lines text-gray-400 w-4 text-center shrink-0"></i>
                            <span class="flex-1">Notas</span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-gray-400"></i>
                        </button>
                        <div class="js-notas-eventos-lista hidden bg-gray-50 border-y border-gray-100">
                            <?php if ($eventosVinculados === []): ?>
                            <p class="px-4 py-2 text-xs text-gray-500">Nenhum evento vinculado.</p>
                            <?php else: ?>
                            <?php foreach ($eventosVinculados as $eventoNota): ?>
                            <?php $eventoNotaId = (int) ($eventoNota['id'] ?? 0); ?>
                            <?php if ($eventoNotaId <= 0) { continue; } ?>
                            <a href="<?= URL ?>/admin/boletim-configuracao/assistente?regra_id=<?= $eventoNotaId ?>&amp;boletim_id=<?= (int) $item['id'] ?>&amp;voltar=boletins"
                               class="flex items-center gap-2 pl-8 pr-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                               title="Abrir na edição">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center shrink-0"></i>
                                <span class="truncate"><?= htmlspecialchars((string) ($eventoNota['nome'] ?? ('Evento #' . $eventoNotaId)), ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="<?= URL ?>/admin/boletins/<?= (int) $item['id'] ?>/gerar-avaliacoes"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap">
                            <i class="fa-solid fa-calendar-plus text-gray-400 w-4 text-center shrink-0"></i> Novo <?= htmlspecialchars($rotuloPeriodo, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="POST" action="<?= URL ?>/admin/boletins/<?= (int) $item['id'] ?>/delete"
                              onsubmit="return confirm('Excluir este modelo de boletim? Avaliações e notas já geradas permanecem.');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 whitespace-nowrap">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center shrink-0"></i> Excluir
                            </button>
                        </form>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'boletim-cadastro-' . (int) $item['id'];
                        $row_actions_dropdown_menu_class = 'w-80';
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-notas-eventos');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var lista = btn.nextElementSibling;
    if (!lista || !lista.classList.contains('js-notas-eventos-lista')) return;
    lista.classList.toggle('hidden');
    var menu = btn.closest('[data-dropdown-menu]');
    if (!menu) return;
    var rect = menu.getBoundingClientRect();
    if (rect.bottom > window.innerHeight - 8) {
        menu.style.top = Math.max(8, window.innerHeight - rect.height - 8) + 'px';
    }
});
</script>
