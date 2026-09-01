<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$alunos = is_array($alunos ?? null) ? $alunos : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$turmaId = (int) ($turma_id ?? 0);
$qsBase = 'ano_letivo=' . $anoLetivo . ($turmaId > 0 ? '&turma_id=' . $turmaId : '');

$page_header_title = 'Vida Escolar';
$page_header_subtitle = 'Documentos oficiais da secretaria: histórico, boletim, ficha individual, prontuário e ofícios.';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-8">
    <?php
    $docsHub = [
        [
            'href' => '#documentos-alunos',
            'title' => 'Histórico',
            'description' => 'Histórico escolar oficial (rascunho, conferência, emissão e QR).',
            'icon' => 'fa-scroll',
        ],
        [
            'href' => '#documentos-alunos',
            'title' => 'Boletim escolar',
            'description' => 'Boletim do ano em curso, no papel timbrado da escola.',
            'icon' => 'fa-table',
        ],
        [
            'href' => '#documentos-alunos',
            'title' => 'Ficha individual',
            'description' => 'Ficha do aluno com bimestres, carga horária e resultado.',
            'icon' => 'fa-id-card',
        ],
        [
            'href' => '#documentos-alunos',
            'title' => 'Prontuário do aluno',
            'description' => 'Identidade, trajetória, documentos e conferência SED/INEP.',
            'icon' => 'fa-folder-open',
        ],
        [
            'href' => URL . '/admin/vida-escolar/oficios',
            'title' => 'Ofícios',
            'description' => 'Correspondência oficial numerada da secretaria.',
            'icon' => 'fa-envelope-open-text',
        ],
    ];
    foreach ($docsHub as $card):
    ?>
    <a href="<?= $esc($card['href']) ?>" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:border-gray-300 hover:shadow-md transition-all text-inherit no-underline">
        <div class="flex items-center gap-3 mb-2">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                <i class="fa-solid <?= $esc($card['icon']) ?>"></i>
            </div>
            <h3 class="text-sm font-semibold text-gray-900"><?= $esc($card['title']) ?></h3>
        </div>
        <p class="text-xs text-gray-500 leading-relaxed"><?= $esc($card['description']) ?></p>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($schema_pronto)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-amber-900 text-sm mb-6">
    Aplique a migration <code>2026_08_25_vida_escolar</code> no painel Master para gravar a ficha oficial de boletim. Histórico, ficha individual, prontuário e ofícios já podem ser usados.
</div>
<?php endif; ?>

<div id="documentos-alunos" class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Alunos da turma</h3>
    <p class="text-sm text-gray-500 mb-4">Escolha o ano e a turma. Em cada aluno, emita histórico, boletim, ficha individual ou abra o prontuário.</p>
    <form method="get" action="<?= URL ?>/admin/vida-escolar" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Ano letivo</label>
            <select name="ano_letivo" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white" onchange="this.form.submit()">
                <?php foreach ($anos as $a): ?>
                    <option value="<?= (int) $a ?>" <?= $anoLetivo === (int) $a ? 'selected' : '' ?>><?= (int) $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
            <select name="turma_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white" onchange="this.form.submit()">
                <option value="0">Selecione a turma</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= $turmaId === (int) $t['id'] ? 'selected' : '' ?>><?= $esc($t['nome'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ficha / boletim</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Histórico</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php if ($turmaId <= 0): ?>
            <tr>
                <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                    <i class="fa-solid fa-users text-4xl text-gray-300 mb-4"></i>
                    <p>Escolha uma turma para ver os alunos e emitir os documentos.</p>
                </td>
            </tr>
            <?php elseif ($alunos === []): ?>
            <tr>
                <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                    <i class="fa-solid fa-user-graduate text-4xl text-gray-300 mb-4"></i>
                    <p>Nenhum aluno vinculado a esta turma.</p>
                    <a href="<?= URL ?>/admin/students" class="text-blue-600 text-sm mt-2 inline-block">Abrir cadastro de alunos</a>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($alunos as $al): ?>
                <?php
                $aid = (int) ($al['id'] ?? 0);
                $fichaId = (int) ($al['ficha_id'] ?? 0);
                $fichaSt = (string) ($al['ficha_status'] ?? '');
                $histSt = (string) ($al['historico_status'] ?? '');
                $histId = (int) ($al['historico_id'] ?? 0);
                $fichaLabel = $fichaId > 0
                    ? ($fichaSt === 'homologada' ? 'Homologada' : ($fichaSt === 'fechada' ? 'Fechada' : 'Em curso'))
                    : 'Sem ficha';
                $fichaVar = $fichaSt === 'homologada' ? 'ativo' : ($fichaId > 0 ? 'pendente' : 'neutro');
                $histLabel = $histSt !== '' ? $histSt : 'Não gerado';
                $histVar = in_array($histSt, ['Emitido', 'Assinado', 'Entregue'], true) ? 'ativo' : ($histSt !== '' ? 'pendente' : 'neutro');
                $qsFicha = $fichaId > 0 ? '&ficha_id=' . $fichaId : '';
                $qsTurma = '?turma_id=' . $turmaId . '&ano_letivo=' . $anoLetivo;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900"><?= $esc($al['nome'] ?? '') ?></div>
                        <?php if (!empty($al['ra'])): ?>
                        <div class="text-xs text-gray-500">RA <?= $esc($al['ra']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php $ui_badge_variant = $fichaVar; $ui_badge_label = $fichaLabel; include dirname(__DIR__, 4) . '/Views/admin/_partials/ui/badge.php'; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php $ui_badge_variant = $histVar; $ui_badge_label = $histLabel; include dirname(__DIR__, 4) . '/Views/admin/_partials/ui/badge.php'; ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/students/<?= $aid ?>?tab=vida-escolar&amp;ve_aba=boletim<?= $qsFicha ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-folder-open text-gray-400 w-4 text-center"></i> Prontuário
                        </a>
                        <a href="<?= URL ?>/admin/students/<?= $aid ?>/historico-escolar"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-scroll text-gray-400 w-4 text-center"></i> Histórico
                        </a>
                        <?php if ($fichaId > 0): ?>
                        <a href="<?= URL ?>/admin/students/<?= $aid ?>/vida-escolar/pdf?ficha_id=<?= $fichaId ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-table text-gray-400 w-4 text-center"></i> Boletim (PDF)
                        </a>
                        <?php endif; ?>
                        <a href="<?= URL ?>/admin/resultados-finais/aluno/<?= $aid ?>/ficha<?= $qsTurma ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-id-card text-gray-400 w-4 text-center"></i> Ficha individual
                        </a>
                        <a href="<?= URL ?>/admin/vida-escolar/oficios/novo?aluno_id=<?= $aid ?>&amp;turma_id=<?= $turmaId ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-envelope-open-text text-gray-400 w-4 text-center"></i> Novo ofício
                        </a>
                        <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                        <?php $row_actions_dropdown_id = 'row-actions-aluno-' . $aid; ?>
                        <?php include dirname(__DIR__, 4) . '/Views/admin/_partials/row_actions_dropdown.php'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ((int) ($pagination['total'] ?? 0) > 0 && (int) ($pagination['total_pages'] ?? 1) > 1): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-600">Exibindo <?= count($alunos) ?> de <?= (int) $pagination['total'] ?></p>
        <div class="flex gap-1">
            <?php
            $cur = (int) ($pagination['page'] ?? 1);
            $tp = (int) $pagination['total_pages'];
            ?>
            <?php if ($cur > 1): ?>
            <a href="<?= URL ?>/admin/vida-escolar?<?= $qsBase ?>&amp;page=<?= $cur - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $cur - 2); $i <= min($tp, $cur + 2); $i++): ?>
            <a href="<?= URL ?>/admin/vida-escolar?<?= $qsBase ?>&amp;page=<?= $i ?>"
               class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $cur ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($cur < $tp): ?>
            <a href="<?= URL ?>/admin/vida-escolar?<?= $qsBase ?>&amp;page=<?= $cur + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
