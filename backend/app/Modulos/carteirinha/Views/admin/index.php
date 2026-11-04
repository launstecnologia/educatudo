<?php
$schemaPronto = !empty($schema_pronto);
$habilitada = !empty($habilitada);
$podeAlterar = !empty($pode_alterar);
$turmas = is_array($turmas ?? null) ? $turmas : [];
$alunos = is_array($alunos ?? null) ? $alunos : [];
$csrf = (string) ($csrf_token ?? '');
$turmaId = (int) ($turma_id ?? 0);
$busca = (string) ($busca ?? '');
$filtrar = !empty($filtrar);
$pagina = (int) ($pagina ?? 1);
$totalPaginas = (int) ($total_paginas ?? 1);
$total = (int) ($total ?? 0);
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ui = __DIR__ . '/../../../../Views/admin/_partials/ui';
$rotulos = [
    'valida' => 'Válida',
    'vencida' => 'Vencida',
    'inativa' => 'Aluno inativo',
    'sem_emissao' => 'Sem emissão',
];
$cores = [
    'valida' => 'bg-emerald-50 text-emerald-800',
    'vencida' => 'bg-amber-50 text-amber-800',
    'inativa' => 'bg-amber-50 text-amber-800',
    'sem_emissao' => 'bg-gray-100 text-gray-600',
];
$queryBase = [];
if ($turmaId > 0) {
    $queryBase['turma_id'] = $turmaId;
}
if ($busca !== '') {
    $queryBase['busca'] = $busca;
}
$filtrosAtivos = ($turmaId > 0 ? 1 : 0) + ($busca !== '' ? 1 : 0);
$opcoesTurma = ['0' => 'Todas'];
foreach ($turmas as $turma) {
    $opcoesTurma[(string) (int) $turma['id']] = trim((string) $turma['nome'] . ' · ' . (string) ($turma['ano_letivo'] ?? ''));
}

ob_start();
if ($schemaPronto && $habilitada):
    $ui_btn_variant = 'filtro';
    $ui_btn_label = 'Filtros';
    $ui_btn_icon = 'fa-solid fa-filter';
    $ui_btn_onclick = 'openFiltroDrawer()';
    $ui_btn_filter_count = $filtrosAtivos;
    include $ui . '/btn.php';
endif;
if ($schemaPronto && $podeAlterar):
?>
<button type="button" onclick="openUsoDrawer()" class="inline-flex items-center justify-center w-10 h-10 border border-gray-300 rounded-lg text-gray-600 bg-white hover:bg-gray-50" aria-label="Habilitar ou desabilitar a carteirinha" title="Habilitar ou desabilitar">
    <i class="fa-solid fa-gear"></i>
</button>
<?php
endif;
$page_header_actions = ob_get_clean();
$page_header_title = 'Carteirinha';
$page_header_subtitle = 'A escola decide se usa. Ligada, a secretaria emite e o QR confirma na hora.';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
?>

<?php if (!empty($flash_message)): ?>
    <div class="mb-6 p-4 rounded-lg border <?= ($flash_type ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
        <?= $e($flash_message) ?>
    </div>
<?php endif; ?>

<?php if (!$schemaPronto): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-6">
        Rode a migration <code class="text-sm">2026_11_03_carteirinha.sql</code> no painel Master.
    </div>
<?php else: ?>

<?php if (!$habilitada): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-gray-600">
        A carteirinha está desabilitada nesta escola.
        <?php if ($podeAlterar): ?>
            Use a engrenagem para habilitar.
        <?php endif; ?>
    </div>
<?php else: ?>

<?php if ($podeAlterar && $turmaId > 0): ?>
<form method="POST" action="<?= URL ?>/admin/carteirinha/emitir-turma" class="mb-6 flex flex-wrap items-center gap-3">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
    <input type="hidden" name="busca" value="<?= $e($busca) ?>">
    <button type="submit" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-800 hover:bg-gray-50">Emitir quem ainda não tem</button>
    <a href="<?= URL ?>/admin/carteirinha/imprimir?turma_id=<?= $turmaId ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700">Imprimir a turma</a>
</form>
<?php endif; ?>

<?php if (!$filtrar): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-gray-500">
        <p>Escolha uma turma ou busque um aluno para emitir.</p>
        <button type="button" onclick="openFiltroDrawer()" class="mt-4 inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fa-solid fa-filter mr-2 text-gray-500"></i> Filtros
        </button>
    </div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Validade</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <?php if ($alunos === []): ?>
                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhum aluno encontrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($alunos as $aluno): ?>
                <?php $situacao = (string) ($aluno['situacao'] ?? 'sem_emissao'); ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-slate-100 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0">
                                <?php if (($aluno['foto'] ?? '') !== ''): ?>
                                    <img src="<?= $e($aluno['foto']) ?>" alt="" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="text-sm font-medium text-slate-600"><?= $e(mb_strtoupper(mb_substr((string) $aluno['nome'], 0, 1))) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate"><?= $e($aluno['nome']) ?></div>
                                <div class="text-xs text-gray-500">RA <?= $e($aluno['ra'] !== '' ? $aluno['ra'] : '—') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= $e($aluno['turma'] !== '' ? $aluno['turma'] : '—') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold <?= $e($cores[$situacao] ?? $cores['sem_emissao']) ?>">
                            <?= $e($rotulos[$situacao] ?? 'Sem emissão') ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= $e($aluno['validade'] !== '' ? $aluno['validade'] : '—') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                            <?php if ($podeAlterar && $situacao !== 'valida'): ?>
                            <form method="POST" action="<?= URL ?>/admin/carteirinha/emitir">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="aluno_id" value="<?= (int) $aluno['id'] ?>">
                                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                                <input type="hidden" name="busca" value="<?= $e($busca) ?>">
                                <input type="hidden" name="pagina" value="<?= $pagina ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-id-card text-gray-400 w-4 text-center"></i> Emitir
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($podeAlterar && $situacao === 'valida'): ?>
                            <form method="POST" action="<?= URL ?>/admin/carteirinha/emitir">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="aluno_id" value="<?= (int) $aluno['id'] ?>">
                                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                                <input type="hidden" name="busca" value="<?= $e($busca) ?>">
                                <input type="hidden" name="pagina" value="<?= $pagina ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-rotate text-gray-400 w-4 text-center"></i> Reemitir
                                </button>
                            </form>
                            <form method="POST" action="<?= URL ?>/admin/carteirinha/cancelar" onsubmit="return confirm('Cancelar esta carteirinha? O QR deixa de valer.');">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int) $aluno['carteirinha_id'] ?>">
                                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                                <input type="hidden" name="busca" value="<?= $e($busca) ?>">
                                <input type="hidden" name="pagina" value="<?= $pagina ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fa-solid fa-ban text-red-400 w-4 text-center"></i> Cancelar
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ((int) $aluno['carteirinha_id'] > 0): ?>
                            <button type="button" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" onclick="abrirCarteirinha(<?= (int) $aluno['id'] ?>)">
                                <i class="fa-solid fa-print text-gray-400 w-4 text-center"></i> Imprimir
                            </button>
                            <?php endif; ?>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-carteirinha-' . (int) $aluno['id'];
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($totalPaginas > 1): ?>
    <div class="mt-4 flex items-center justify-between text-sm text-gray-600">
        <span><?= $total ?> aluno(s)</span>
        <div class="flex gap-2">
            <?php if ($pagina > 1): ?>
                <a class="px-3 py-1 rounded border border-gray-300" href="<?= URL ?>/admin/carteirinha?<?= $e(http_build_query($queryBase + ['pagina' => $pagina - 1])) ?>">Anterior</a>
            <?php endif; ?>
            <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
            <?php if ($pagina < $totalPaginas): ?>
                <a class="px-3 py-1 rounded border border-gray-300" href="<?= URL ?>/admin/carteirinha?<?= $e(http_build_query($queryBase + ['pagina' => $pagina + 1])) ?>">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php endif; ?>

<?php
ob_start();
?>
<form method="GET" action="<?= URL ?>/admin/carteirinha" class="flex flex-col flex-1 overflow-hidden">
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6">
        <?php
        $ui_form_campo_label = 'Turma';
        $ui_form_campo_name = 'turma_id';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesTurma;
        $ui_form_campo_value = (string) $turmaId;
        $ui_form_campo_placeholder = '';
        $ui_form_campo_span = 'full';
        $ui_form_campo_mb = 'mb-4';
        $ui_form_campo_obrigatorio = false;
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Aluno';
        $ui_form_campo_name = 'busca';
        $ui_form_campo_tipo = 'text';
        $ui_form_campo_value = $busca;
        $ui_form_campo_placeholder = 'Nome ou RA';
        $ui_form_campo_opcoes = [];
        include $ui . '/form_campo.php';
        ?>
    </div>
    <div class="px-6 sm:px-8 py-4 border-t border-gray-200 flex gap-3">
        <?php
        $ui_btn_variant = 'complementar';
        $ui_btn_label = 'Limpar';
        $ui_btn_href = URL . '/admin/carteirinha';
        $ui_btn_class = 'flex-1 justify-center';
        $ui_btn_icon = '';
        $ui_btn_onclick = '';
        $ui_btn_type = 'button';
        $ui_btn_filter_count = 0;
        include $ui . '/btn.php';

        $ui_btn_variant = 'primary';
        $ui_btn_label = 'Filtrar';
        $ui_btn_href = '';
        $ui_btn_type = 'submit';
        $ui_btn_class = 'flex-1 justify-center';
        include $ui . '/btn.php';
        ?>
    </div>
</form>
<?php
$ui_offcanvas_body = ob_get_clean();
$ui_offcanvas_id = 'filtro';
$ui_offcanvas_titulo = 'Filtros';
$ui_offcanvas_max_w = 'max-w-md';
include $ui . '/offcanvas.php';

ob_start();
?>
<div id="carteirinhaCartaoSlot" class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 bg-gray-50"></div>
<?php
$ui_offcanvas_body = ob_get_clean();
$ui_offcanvas_id = 'cartao';
$ui_offcanvas_titulo = 'Carteirinha';
$ui_offcanvas_max_w = 'max-w-2xl';
include $ui . '/offcanvas.php';
?>
<script>
function abrirCarteirinha(alunoId) {
    var slot = document.getElementById('carteirinhaCartaoSlot');
    if (!slot || typeof openCartaoDrawer !== 'function') {
        return;
    }
    slot.innerHTML = '<p class="text-sm text-gray-500">Carregando…</p>';
    openCartaoDrawer();
    fetch(<?= json_encode(rtrim((string) URL, '/')) ?> + '/admin/carteirinha/cartao?aluno_id=' + encodeURIComponent(alunoId), {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (resposta) {
        if (!resposta.ok) {
            throw new Error('falha');
        }
        return resposta.text();
    }).then(function (html) {
        slot.innerHTML = html;
    }).catch(function () {
        slot.innerHTML = '<p class="text-sm text-red-700">Não foi possível abrir a carteirinha.</p>';
    });
}
</script>
<?php endif; ?>

<?php if ($podeAlterar): ?>
<?php ob_start(); ?>
<form method="POST" action="<?= URL ?>/admin/carteirinha/uso" class="flex flex-col flex-1 overflow-hidden"
      onsubmit="var marcado = this.querySelector('input[name=habilitada]:checked'); if (marcado && marcado.value === '0' && <?= $habilitada ? 'true' : 'false' ?>) { return confirm('Desabilitar a carteirinha? Os QR deixam de valer até você ligar de novo.'); } return true;">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-3">
        <p class="text-sm text-gray-600">A escola decide se a carteirinha fica disponível para o aluno e para a leitura do QR.</p>
        <label class="flex items-start gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer">
            <input type="radio" name="habilitada" value="1" class="mt-1" <?= $habilitada ? 'checked' : '' ?>>
            <span>
                <span class="block text-sm font-semibold text-gray-900">Habilitada</span>
                <span class="block text-sm text-gray-500 mt-1">O aluno vê o cartão e o QR pode ser conferido sem login.</span>
            </span>
        </label>
        <label class="flex items-start gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer">
            <input type="radio" name="habilitada" value="0" class="mt-1" <?= $habilitada ? '' : 'checked' ?>>
            <span>
                <span class="block text-sm font-semibold text-gray-900">Desabilitada</span>
                <span class="block text-sm text-gray-500 mt-1">O menu do aluno some e a página pública avisa que a escola não usa.</span>
            </span>
        </label>
    </div>
    <div class="px-6 sm:px-8 py-4 border-t border-gray-200">
        <?php
        $ui_btn_variant = 'primary';
        $ui_btn_label = 'Salvar';
        $ui_btn_type = 'submit';
        $ui_btn_href = '';
        $ui_btn_class = 'w-full justify-center';
        $ui_btn_icon = '';
        $ui_btn_onclick = '';
        include $ui . '/btn.php';
        ?>
    </div>
</form>
<?php
$ui_offcanvas_body = ob_get_clean();
$ui_offcanvas_id = 'uso';
$ui_offcanvas_titulo = 'Uso da carteirinha';
$ui_offcanvas_max_w = 'max-w-md';
include $ui . '/offcanvas.php';
?>
<?php endif; ?>
<?php endif; ?>
