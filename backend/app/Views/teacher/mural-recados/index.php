<?php
$ui = __DIR__ . '/../../admin/_partials/ui';
$filtroMateria = (int) ($filtro_materia ?? 0);
$filtroDataDe = (string) ($filtro_data_de ?? '');
$filtroDataAte = (string) ($filtro_data_ate ?? '');
$filtrosAtivos = ($filtroMateria > 0 ? 1 : 0) + ($filtroDataDe !== '' ? 1 : 0) + ($filtroDataAte !== '' ? 1 : 0);

ob_start();
$ui_btn_variant = 'filtro';
$ui_btn_label = 'Filtros';
$ui_btn_icon = 'fa-solid fa-filter';
$ui_btn_onclick = 'openFiltroDrawer()';
$ui_btn_filter_count = $filtrosAtivos;
$ui_btn_href = '';
$ui_btn_type = 'button';
$ui_btn_id = '';
$ui_btn_class = '';
$ui_btn_attrs = '';
include $ui . '/btn.php';

$ui_btn_variant = 'primary';
$ui_btn_label = 'Novo Recado';
$ui_btn_icon = 'fa-solid fa-plus';
$ui_btn_href = URL . '/professor/mural-recados/criar';
$ui_btn_onclick = '';
$ui_btn_filter_count = 0;
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();
$page_header_title = 'Mural de Recados';
$page_header_subtitle = 'Recados para turmas ou todos os alunos.';
include __DIR__ . '/../../admin/_partials/page_header_list.php';
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
<?php
$msg = $_SESSION['flash_message'];
$typ = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<div class="mb-4 p-4 rounded-lg <?= $typ === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destinatários</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($recados)): ?>
                <tr>
                    <td colspan="3" class="px-6 py-8 text-center text-gray-500">Nenhum recado ainda. Clique em "Novo Recado" para publicar.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($recados as $r): ?>
                <tr class="hover:bg-gray-50" data-recado-titulo="<?= htmlspecialchars($r['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <td class="px-6 py-4">
                        <span class="font-medium text-gray-900"><?= htmlspecialchars($r['titulo']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?= !empty($r['enviar_para_todos']) ? 'Todos' : (htmlspecialchars($r['turmas_nomes'] ?? '-')) ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-3 flex-wrap">
                            <button type="button" class="btn-ver-recado inline-flex items-center gap-1.5 text-gray-700 hover:text-blue-600 text-sm" data-recado-id="<?= (int)$r['id'] ?>" title="Ver texto do recado">
                                <i class="fas fa-eye"></i> Ver
                            </button>
                            <a href="<?= URL ?>/professor/mural-recados/editar?id=<?= (int)$r['id'] ?>" class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <form action="<?= URL ?>/professor/mural-recados/excluir" method="post" class="inline" onsubmit="return confirm('Excluir este recado?');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="inline-flex items-center gap-1.5 text-red-600 hover:text-red-800 text-sm">
                                    <i class="fas fa-trash-alt"></i> Excluir
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Conteúdos dos recados (oculto, para o modal Ver) -->
<?php if (!empty($recados)): ?>
<div id="recados-conteudos-holder" class="hidden" aria-hidden="true">
    <?php foreach ($recados as $r): ?>
    <div id="recado-conteudo-<?= (int)$r['id'] ?>" class="recado-conteudo-item"><?= rich_text_render($r['conteudo'] ?? '') ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Ver recado -->
<div id="modalVerRecado" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modalVerRecadoTitle" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" id="modalVerRecadoBackdrop"></div>
        <div class="relative inline-block w-full max-w-2xl p-0 my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-xl shadow-xl">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 id="modalVerRecadoTitle" class="text-lg font-semibold text-gray-900">Recado</h3>
                <button type="button" id="modalVerRecadoFechar" class="text-gray-400 hover:text-gray-600 p-1 rounded" aria-label="Fechar">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="modalVerRecadoBody" class="px-6 py-4 max-h-[70vh] overflow-y-auto prose prose-sm max-w-none text-gray-700">
                <!-- conteúdo preenchido via JS -->
            </div>
        </div>
    </div>
</div>

<?php
$opcoesMateria = ['' => 'Todas'];
foreach ($materias_opcoes ?? [] as $mat) {
    $opcoesMateria[(string) (int) ($mat['id'] ?? 0)] = (string) ($mat['nome'] ?? '');
}
ob_start();
?>
<form method="get" action="<?= URL ?>/professor/mural-recados" class="flex flex-col flex-1 overflow-hidden">
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6">
        <?php
        $ui_form_campo_label = 'Matéria';
        $ui_form_campo_name = 'materia_id';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesMateria;
        $ui_form_campo_value = $filtroMateria > 0 ? (string) $filtroMateria : '';
        $ui_form_campo_placeholder = '';
        $ui_form_campo_span = 'full';
        $ui_form_campo_mb = 'mb-4';
        $ui_form_campo_obrigatorio = false;
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Data de';
        $ui_form_campo_name = 'data_de';
        $ui_form_campo_tipo = 'date';
        $ui_form_campo_value = $filtroDataDe;
        $ui_form_campo_opcoes = [];
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Data até';
        $ui_form_campo_name = 'data_ate';
        $ui_form_campo_tipo = 'date';
        $ui_form_campo_value = $filtroDataAte;
        include $ui . '/form_campo.php';
        ?>
    </div>
    <div class="px-6 sm:px-8 py-4 border-t border-gray-200 flex gap-3">
        <?php
        $ui_btn_variant = 'complementar';
        $ui_btn_label = 'Limpar';
        $ui_btn_href = URL . '/professor/mural-recados';
        $ui_btn_class = 'flex-1 justify-center';
        $ui_btn_type = 'button';
        $ui_btn_onclick = '';
        $ui_btn_icon = '';
        $ui_btn_filter_count = 0;
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
(function() {
    var modal = document.getElementById('modalVerRecado');
    var modalTitle = document.getElementById('modalVerRecadoTitle');
    var modalBody = document.getElementById('modalVerRecadoBody');
    var modalBackdrop = document.getElementById('modalVerRecadoBackdrop');
    var btnFechar = document.getElementById('modalVerRecadoFechar');
    if (!modal || !modalBody) return;
    function abrirModal(titulo, conteudoHtml) {
        modalTitle.textContent = titulo || 'Recado';
        modalBody.innerHTML = conteudoHtml || '<p class="text-gray-500">Sem conteúdo.</p>';
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function fecharModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
    document.querySelectorAll('.btn-ver-recado').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-recado-id');
            var row = this.closest('tr');
            var titulo = row ? row.getAttribute('data-recado-titulo') : '';
            var elConteudo = document.getElementById('recado-conteudo-' + id);
            var conteudoHtml = elConteudo ? elConteudo.innerHTML : '';
            abrirModal(titulo, conteudoHtml);
        });
    });
    if (modalBackdrop) modalBackdrop.addEventListener('click', fecharModal);
    if (btnFechar) btnFechar.addEventListener('click', fecharModal);
    modal.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') fecharModal();
    });
})();
</script>
