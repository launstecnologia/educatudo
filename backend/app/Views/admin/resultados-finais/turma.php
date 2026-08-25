<?php
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';
require_once __DIR__ . '/../../../Services/ResultadoAcademicoService.php';

$preview = is_array($preview ?? null) ? $preview : [];
$turma = is_array($preview['turma'] ?? null) ? $preview['turma'] : [];
$periodo = is_array($preview['periodo'] ?? null) ? $preview['periodo'] : [];
$linhas = is_array($preview['linhas'] ?? null) ? $preview['linhas'] : [];
$resumo = is_array($preview['resumo'] ?? null) ? $preview['resumo'] : [];
$config = is_array($preview['config'] ?? null) ? $preview['config'] : [];
$especiais = is_array($especiais ?? null) ? $especiais : [];
$componentes = is_array($componentes ?? null) ? $componentes : [];
$anoLetivo = (int) ($ano_letivo ?? ($periodo['ano_letivo'] ?? date('Y')));
$periodoTipo = (string) ($periodo_tipo ?? ($periodo['tipo'] ?? 'ano'));
$periodoNumero = (int) ($periodo_numero ?? ($periodo['numero'] ?? 0));
$turmaId = (int) ($turma['id'] ?? 0);
$qs = http_build_query(['ano_letivo' => $anoLetivo, 'periodo_tipo' => $periodoTipo, 'periodo_numero' => $periodoNumero]);
$csrf_token = $csrf_token ?? '';

$statusBadge = static function (string $status): string {
    return match ($status) {
        'homologado' => 'bg-green-100 text-green-700',
        'reaberto' => 'bg-amber-100 text-amber-800',
        default => 'bg-gray-100 text-gray-600',
    };
};

$page_header_title = 'Fechamento · ' . (string) ($turma['nome'] ?? 'Turma');
$page_header_subtitle = ($periodo['label'] ?? 'Ano letivo') . ' / ' . $anoLetivo . ' — homologação gera o snapshot oficial usado por ficha, ata, histórico e relatórios.';
ob_start();
?>
<a href="<?= URL ?>/admin/resultados-finais?<?= htmlspecialchars($qs) ?>" class="text-gray-600 hover:text-gray-900 text-sm">← Voltar</a>
<a href="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>/ata?<?= htmlspecialchars($qs) ?>"
   class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
    <i class="fa-solid fa-file-lines mr-2"></i> Ata
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
include __DIR__ . '/../_partials/flash_message.php';

$filtros_mostrar_turma = false;
$filtros_action = URL . '/admin/resultados-finais/turma/' . $turmaId;
include __DIR__ . '/_filtros.php';
?>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <?php
    $cards = [
        ['Alunos', (int) ($resumo['total'] ?? 0), 'bg-slate-50 text-slate-700'],
        ['Homologados', (int) ($resumo['homologados'] ?? 0), 'bg-green-50 text-green-700'],
        ['Pendências', (int) ($resumo['pendencias'] ?? 0), 'bg-amber-50 text-amber-800'],
        ['Aprovados', (int) ($resumo['aprovados'] ?? 0), 'bg-emerald-50 text-emerald-700'],
        ['Reprovados', (int) ($resumo['reprovados'] ?? 0), 'bg-rose-50 text-rose-700'],
    ];
    foreach ($cards as [$lab, $val, $cls]):
    ?>
    <div class="rounded-xl border border-gray-200 bg-white p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide"><?= htmlspecialchars($lab) ?></div>
        <div class="mt-1 text-2xl font-semibold <?= htmlspecialchars($cls) ?>"><?= $val ?></div>
    </div>
    <?php endforeach; ?>
</div>

<form method="POST" action="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>/homologar" class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
    <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
    <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3"><input type="checkbox" id="rf-check-all" class="rounded border-gray-300"></th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notas</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frequência</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conselho</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resultado</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pendência</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($linhas === []): ?>
                <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">Nenhum aluno nesta turma.</td></tr>
                <?php else: foreach ($linhas as $linha):
                    $aluno = $linha['aluno'] ?? [];
                    $aid = (int) ($aluno['id'] ?? 0);
                    $status = (string) ($linha['status'] ?? 'em_andamento');
                    $criticas = !empty($linha['pendencias_criticas']);
                    $freq = $linha['frequencia']['percentual'] ?? null;
                    $freqTxt = is_numeric($freq) ? number_format((float) $freq, 1, ',', '.') . '%' : '—';
                    $qsAluno = $qs . '&turma_id=' . $turmaId;
                ?>
                <tr class="hover:bg-gray-50 <?= !empty($aluno['transferido']) ? 'opacity-70' : '' ?>">
                    <td class="px-3 py-3">
                        <?php if ($status !== 'homologado' && !$criticas): ?>
                        <input type="checkbox" name="aluno_ids[]" value="<?= $aid ?>" class="rf-check rounded border-gray-300">
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($aluno['nome'] ?? '')) ?></div>
                        <div class="text-xs text-gray-500">
                            <?= htmlspecialchars((string) ($aluno['ra'] ?? '')) ?>
                            <?php if (!empty($aluno['transferido'])): ?> · transferido<?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if (!empty($linha['notas_completas'])): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">OK</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Incompletas</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($freqTxt) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($linha['conselho']['resultado'] ?? '—')) ?></td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($linha['rotulo'] ?? '—')) ?></div>
                        <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs font-medium <?= $statusBadge($status) ?>">
                            <?= htmlspecialchars(ResultadoAcademico::STATUS[$status] ?? $status) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600">
                        <?= $linha['pendencias'] ? htmlspecialchars(implode(', ', $linha['pendencias'])) : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/resultados-finais/aluno/<?= $aid ?>/ficha?<?= htmlspecialchars($qsAluno) ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-id-card text-gray-400 w-4 text-center"></i> Ficha
                        </a>
                        <a href="<?= URL ?>/admin/resultados-finais/aluno/<?= $aid ?>/ficha/pdf?<?= htmlspecialchars($qsAluno) ?>"
                           target="_blank" rel="noopener"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-file-pdf text-gray-400 w-4 text-center"></i> PDF ficha
                        </a>
                        <a href="<?= URL ?>/admin/resultados-finais/aluno/<?= $aid ?>/boletim/pdf?<?= htmlspecialchars($qsAluno) ?>"
                           target="_blank" rel="noopener"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-table text-gray-400 w-4 text-center"></i> Boletim oficial
                        </a>
                        <?php if ($status === 'homologado' && (int) ($linha['resultado_id'] ?? 0) > 0): ?>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-amber-700 hover:bg-amber-50"
                                onclick="document.getElementById('rf-reabrir-<?= (int) $linha['resultado_id'] ?>').classList.remove('hidden')">
                            <i class="fa-solid fa-rotate-left text-amber-500 w-4 text-center"></i> Reabrir
                        </button>
                        <?php endif; ?>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-rf-al-' . $aid;
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500">
            Pendências críticas configuradas:
            <?= !empty($config['exigir_notas']) ? 'notas' : 'notas opcionais' ?>
            · <?= !empty($config['exigir_frequencia']) ? 'frequência' : 'frequência opcional' ?>
            · <?= !empty($config['exigir_conselho']) ? 'conselho' : 'conselho opcional' ?>.
            <a href="<?= URL ?>/admin/resultados-finais/layouts" class="text-primary underline">Alterar</a>
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:opacity-90">
                Homologar selecionados
            </button>
            <button type="submit" name="homologar_todos" value="1"
                    class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                Homologar todos os elegíveis
            </button>
        </div>
    </div>
</form>

<?php foreach ($linhas as $linha):
    $rid = (int) ($linha['resultado_id'] ?? 0);
    if ($rid <= 0 || ($linha['status'] ?? '') !== 'homologado') {
        continue;
    }
?>
<form id="rf-reabrir-<?= $rid ?>" method="POST" action="<?= URL ?>/admin/resultados-finais/resultado/<?= $rid ?>/reabrir"
      class="hidden mb-4 bg-amber-50 border border-amber-200 rounded-xl p-4">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <p class="text-sm font-medium text-amber-900 mb-2">Reabrir <?= htmlspecialchars((string) ($linha['aluno']['nome'] ?? '')) ?> — a versão homologada será preservada.</p>
    <textarea name="motivo" required rows="2" class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm mb-2" placeholder="Motivo da correção"></textarea>
    <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium">Confirmar reabertura</button>
</form>
<?php endforeach; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Dispensa, aproveitamento e dependência</h3>
    <p class="text-sm text-gray-500 mb-4">Situações que não são nota. O fechamento reconhece o tipo e não inventa média para componente dispensado.</p>

    <?php if ($especiais !== []): ?>
    <div class="overflow-x-auto mb-6">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Componente</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Obs.</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($especiais as $esp): ?>
                <tr>
                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($esp['aluno_nome'] ?? '')) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars(ResultadoAcademico::ESPECIAIS[$esp['tipo'] ?? ''] ?? (string) ($esp['tipo'] ?? '')) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($esp['materia_nome'] ?? 'Geral')) ?></td>
                    <td class="px-4 py-2 text-gray-500"><?= htmlspecialchars((string) ($esp['observacao'] ?? '')) ?></td>
                    <td class="px-4 py-2 text-right">
                        <form method="POST" action="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>/especial/<?= (int) $esp['id'] ?>/excluir"
                              onsubmit="return confirm('Remover esta situação?')">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <button type="submit" class="text-sm text-red-600 hover:underline">Excluir</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>/especial" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Aluno</label>
            <select name="aluno_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="">Selecione</option>
                <?php foreach ($linhas as $linha): ?>
                    <option value="<?= (int) ($linha['aluno']['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($linha['aluno']['nome'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
            <select name="tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <?php foreach (ResultadoAcademico::ESPECIAIS as $cod => $lab): ?>
                    <option value="<?= htmlspecialchars($cod) ?>"><?= htmlspecialchars($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Componente (opcional)</label>
            <select name="materia_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="0">Geral (aluno)</option>
                <?php foreach ($componentes as $c): ?>
                    <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($c['nome'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Observação</label>
            <input type="text" name="observacao" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" maxlength="255">
        </div>
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg text-sm font-semibold">Registrar</button>
        </div>
    </form>
</div>

<script>
document.getElementById('rf-check-all')?.addEventListener('change', function () {
    document.querySelectorAll('.rf-check').forEach(function (el) { el.checked = this.checked; }, this);
});
</script>
