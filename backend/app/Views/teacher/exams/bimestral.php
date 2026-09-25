<?php
$ui = __DIR__ . '/../../admin/_partials/ui';
$filters = is_array($filters ?? null) ? $filters : [];

$filtrosAtivos = 0;
if (trim((string) ($filters['busca'] ?? '')) !== '') {
    $filtrosAtivos++;
}
if ((int) ($filters['turma_id'] ?? 0) > 0) {
    $filtrosAtivos++;
}
if ((int) ($filters['ano'] ?? 0) > 0) {
    $filtrosAtivos++;
}

$opcoesTurma = ['0' => 'Todas as turmas'];
foreach (($turmas ?? []) as $turma) {
    $opcoesTurma[(string) (int) ($turma['id'] ?? 0)] = (string) ($turma['nome'] ?? '');
}
$opcoesAno = ['0' => 'Todos os anos'];
foreach (($anos ?? []) as $ano) {
    $valorAno = (int) ($ano['ano'] ?? 0);
    if ($valorAno > 0) {
        $opcoesAno[(string) $valorAno] = (string) $valorAno;
    }
}

ob_start();
$ui_btn_variant = 'complementar';
$ui_btn_label = 'Voltar para Minhas Provas';
$ui_btn_href = URL . '/professor/provas';
include $ui . '/btn.php';

$ui_btn_variant = 'filtro';
$ui_btn_label = 'Filtros';
$ui_btn_icon = 'fa-solid fa-filter';
$ui_btn_onclick = 'openFiltroDrawer()';
$ui_btn_filter_count = $filtrosAtivos;
$ui_btn_href = '';
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();

$page_header_title = 'Provas Bimestrais';
$page_header_subtitle = 'Filtre, selecione e baixe em um único documento as provas que você criou.';
include __DIR__ . '/../../admin/_partials/page_header_list.php';
?>

<form method="POST" action="<?= URL ?>/professor/provas-bimestral/baixar" id="form-provas-bimestral" class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap items-center justify-between gap-3">
        <label class="inline-flex items-center gap-3 text-sm text-gray-700">
            <input type="checkbox" id="selecionar-todas" class="rounded border-gray-300 w-4 h-4">
            Selecionar todas as provas visíveis
        </label>
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500"><?= count($provas ?? []) ?> prova(s)</span>
            <?php
            $ui_btn_variant = 'confirm';
            $ui_btn_label = 'Baixar documento';
            $ui_btn_type = 'submit';
            $ui_btn_icon = '';
            $ui_btn_onclick = '';
            $ui_btn_href = '';
            $ui_btn_attrs = '';
            include $ui . '/btn.php';
            ?>
        </div>
    </div>

    <?php
    ob_start();
    if (empty($provas)):
        $ui_tabela_vazia_colspan = 7;
        $ui_tabela_vazia_icone = 'fa-solid fa-file-lines';
        $ui_tabela_vazia_mensagem = 'Nenhuma prova encontrada com os filtros atuais.';
        include $ui . '/tabela_vazia.php';
    else:
        foreach ($provas as $prova):
            $inicio = !empty($prova['data_inicio']) ? date('d/m/Y H:i', strtotime($prova['data_inicio'])) : '—';
            $fim = !empty($prova['data_fim']) ? date('d/m/Y H:i', strtotime($prova['data_fim'])) : '—';
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <input type="checkbox" name="provas[]" value="<?= (int) $prova['id'] ?>" class="checkbox-prova rounded border-gray-300 w-4 h-4">
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($prova['titulo'] ?? '')) ?></div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) ($prova['materia_nome'] ?? '—')) ?></td>
                <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) ($prova['turmas_exibicao'] ?? 'Todas as turmas')) ?></td>
                <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap"><?= (int) ($prova['ano_referencia'] ?? 0) ?: '—' ?></td>
                <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap">
                    <?= htmlspecialchars($inicio) ?>
                    <div class="text-xs text-gray-500">até <?= htmlspecialchars($fim) ?></div>
                </td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <?php
                    $ui_btn_variant = 'detalhes';
                    $ui_btn_label = 'Ver prova';
                    $ui_btn_href = URL . '/professor/provas/visualizar/' . (int) $prova['id'];
                    $ui_btn_type = 'button';
                    include $ui . '/btn.php';
                    ?>
                </td>
            </tr>
            <?php
        endforeach;
    endif;
    $ui_tabela_body = ob_get_clean();
    $ui_tabela_colunas = [
        ['label' => '', 'class' => 'px-6 py-3 w-12'],
        'Título',
        'Matéria',
        'Turma(s)',
        'Ano',
        'Período',
        ['label' => 'Ações', 'class' => 'px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider'],
    ];
    include $ui . '/tabela.php';
    ?>
</form>

<?php
ob_start();
?>
<form method="get" action="<?= URL ?>/professor/provas-bimestral" class="flex flex-col flex-1 overflow-hidden">
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6">
        <?php
        $ui_form_campo_label = 'Buscar';
        $ui_form_campo_name = 'busca';
        $ui_form_campo_tipo = 'text';
        $ui_form_campo_value = (string) ($filters['busca'] ?? '');
        $ui_form_campo_placeholder = 'Título, matéria ou turma';
        $ui_form_campo_span = 'full';
        $ui_form_campo_mb = 'mb-4';
        $ui_form_campo_obrigatorio = false;
        $ui_form_campo_opcoes = [];
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Turma';
        $ui_form_campo_name = 'turma_id';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesTurma;
        $ui_form_campo_value = (string) (int) ($filters['turma_id'] ?? 0);
        $ui_form_campo_placeholder = '';
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Ano';
        $ui_form_campo_name = 'ano';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesAno;
        $ui_form_campo_value = (string) (int) ($filters['ano'] ?? 0);
        include $ui . '/form_campo.php';
        ?>
    </div>
    <div class="px-6 sm:px-8 py-4 border-t border-gray-200 flex gap-3">
        <?php
        $ui_btn_variant = 'complementar';
        $ui_btn_label = 'Limpar';
        $ui_btn_href = URL . '/professor/provas-bimestral';
        $ui_btn_class = 'flex-1 justify-center';
        $ui_btn_type = 'button';
        $ui_btn_onclick = '';
        $ui_btn_attrs = '';
        include $ui . '/btn.php';

        $ui_btn_variant = 'confirm';
        $ui_btn_label = 'Aplicar filtros';
        $ui_btn_type = 'submit';
        $ui_btn_href = '';
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
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selecionarTodas = document.getElementById('selecionar-todas');
    const checkboxes = Array.from(document.querySelectorAll('.checkbox-prova'));
    const form = document.getElementById('form-provas-bimestral');

    if (selecionarTodas) {
        selecionarTodas.addEventListener('change', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = selecionarTodas.checked;
            });
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            const selecionadas = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            });
            if (selecionadas.length === 0) {
                event.preventDefault();
                alert('Selecione ao menos uma prova para baixar.');
            }
        });
    }
});
</script>
