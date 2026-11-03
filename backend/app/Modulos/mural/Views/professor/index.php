<?php
$ui = \App\Core\ViewPaths::adminPartial('ui');
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
$ui_btn_href = '';
$ui_btn_type = 'button';
$ui_btn_onclick = 'abrirRecado()';
$ui_btn_filter_count = 0;
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();
$page_header_title = 'Mural de Recados';
$page_header_subtitle = 'Recados para todos, para turmas ou para um aluno.';
include \App\Core\ViewPaths::adminPartial('page_header_list.php');
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

<?php if (!empty($recebidos)): ?>
<div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Recados da coordenação</h3>
        <p class="text-sm text-gray-500">Também aparecem no sino. Marque como lido para confirmar a leitura.</p>
    </div>
    <div class="divide-y divide-gray-100">
        <?php foreach ($recebidos as $recebido): ?>
        <div class="px-6 py-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-medium text-gray-900"><?= htmlspecialchars((string) $recebido['titulo']) ?></p>
                <p class="text-sm text-gray-600 mt-1"><?= rich_text_render($recebido['conteudo'] ?? '') ?></p>
            </div>
            <?php if (empty($recebido['ja_visto'])): ?>
            <button type="button" class="marcar-visto-professor px-4 py-2 bg-green-600 text-white text-sm rounded-lg" data-id="<?= (int) $recebido['id'] ?>">Marcar como lido</button>
            <?php else: ?>
            <span class="text-sm text-green-700">Lido</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
document.querySelectorAll('.marcar-visto-professor').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var form = new FormData();
        form.append('_token', <?= json_encode($csrf_token ?? '') ?>);
        form.append('ids[]', btn.getAttribute('data-id'));
        fetch(<?= json_encode(URL . '/professor/mural-recados/marcar-visto') ?>, { method: 'POST', body: form, credentials: 'same-origin' })
            .then(function () { window.location.reload(); });
    });
});
</script>
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
                        <?php $qtdAnexos = count($anexos_por_recado[(int) $r['id']] ?? []); ?>
                        <?php if ($qtdAnexos > 0): ?>
                        <span class="block text-xs text-gray-500 mt-1"><?= $qtdAnexos === 1 ? '1 anexo' : $qtdAnexos . ' anexos' ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php if (!empty($r['aluno_id'])): ?>
                            <?= htmlspecialchars((string) ($r['aluno_nome'] ?? 'Aluno')) ?>
                        <?php elseif (!empty($r['enviar_para_todos'])): ?>
                            Todos
                        <?php else: ?>
                            <?= htmlspecialchars($r['turmas_nomes'] ?? '-') ?>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-3 flex-wrap">
                            <button type="button" class="btn-ver-recado inline-flex items-center gap-1.5 text-gray-700 hover:text-blue-600 text-sm" data-recado-id="<?= (int)$r['id'] ?>" title="Ver texto do recado">
                                <i class="fas fa-eye"></i> Ver
                            </button>
                            <button type="button" class="btn-editar-recado inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 text-sm" data-recado-id="<?= (int)$r['id'] ?>">
                                <i class="fas fa-edit"></i> Editar
                            </button>
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
    <div id="recado-conteudo-<?= (int)$r['id'] ?>" class="recado-conteudo-item"><?= rich_text_render($r['conteudo'] ?? '') ?><?php
        $anexosRecado = $anexos_por_recado[(int) $r['id']] ?? [];
        if ($anexosRecado !== []) {
            echo '<div class="not-prose mt-4 space-y-3">';
            foreach ($anexosRecado as $anexo) {
                $urlAnexo = URL . '/mural-recados/anexo/' . (int) $anexo['id'];
                $nomeAnexo = htmlspecialchars((string) ($anexo['arquivo_nome'] ?? 'Anexo'), ENT_QUOTES, 'UTF-8');
                $ehImagem = strncmp((string) ($anexo['tipo_arquivo'] ?? ''), 'image/', 6) === 0;
                if ($ehImagem) {
                    echo '<a href="' . htmlspecialchars($urlAnexo, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener"><img src="' . htmlspecialchars($urlAnexo, ENT_QUOTES, 'UTF-8') . '" alt="' . $nomeAnexo . '" class="max-w-full max-h-80 rounded-lg border border-gray-200"></a>';
                } else {
                    echo '<a href="' . htmlspecialchars($urlAnexo, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-sm text-blue-700 hover:text-blue-900">' . $nomeAnexo . '</a>';
                }
            }
            echo '</div>';
        }
    ?></div>
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

$alunosPorTurma = [];
foreach ($alunos_opcoes ?? [] as $alunoOpcao) {
    $nomeTurma = (string) ($alunoOpcao['turma_nome'] ?? 'Sem turma');
    $alunosPorTurma[$nomeTurma][] = $alunoOpcao;
}
ob_start();
?>
<form id="mural-form" method="post" action="<?= URL ?>/professor/mural-recados/salvar" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <input type="hidden" name="id" id="mural_id" value="" disabled>
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-6">
        <div>
            <label for="mural_titulo" class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
            <input type="text" id="mural_titulo" name="titulo" required maxlength="255"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Conteúdo</label>
            <textarea name="conteudo" id="mural_conteudo" class="hidden"></textarea>
            <div id="editor-conteudo-mural" class="quill-editor-wrapper border border-gray-300 rounded-lg overflow-hidden bg-white" style="min-height: 160px;"></div>
        </div>
        <div>
            <p class="block text-sm font-medium text-gray-700 mb-2">Destinatários <span class="text-red-500">*</span></p>
            <div class="flex flex-col gap-2 mb-3">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="alcance" value="todos" checked class="text-blue-600">
                    Enviar para todos
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="alcance" value="turmas" class="text-blue-600">
                    Selecionar turmas
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="alcance" value="aluno" class="text-blue-600">
                    Um aluno
                </label>
            </div>
            <div id="mural_turmas_container" class="hidden">
                <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3">
                    <?php foreach ($turmas_opcoes ?? [] as $t): ?>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="turmas[]" value="<?= (int) $t['id'] ?>" class="mural-turma-check rounded border-gray-300 text-blue-600">
                        <?= htmlspecialchars((string) $t['nome']) ?>
                    </label>
                    <?php endforeach; ?>
                    <?php if (empty($turmas_opcoes)): ?>
                    <p class="text-sm text-gray-500 col-span-2">Nenhuma turma vinculada a você.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div id="mural_aluno_box" class="hidden">
                <label for="mural_aluno_busca" class="block text-sm font-medium text-gray-700 mb-1">Aluno</label>
                <input type="search" id="mural_aluno_busca" placeholder="Buscar pelo nome" autocomplete="off"
                       class="w-full px-3 py-2 mb-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <div id="mural_aluno_lista" class="max-h-52 overflow-y-auto border border-gray-200 rounded-lg">
                    <?php if ($alunosPorTurma === []): ?>
                    <p class="px-3 py-3 text-sm text-gray-500">Nenhum aluno nas suas turmas.</p>
                    <?php else: ?>
                    <?php foreach ($alunosPorTurma as $nomeTurma => $alunosTurma): ?>
                    <div class="mural-aluno-grupo" data-turma="<?= htmlspecialchars($nomeTurma) ?>">
                        <p class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 bg-gray-50"><?= htmlspecialchars($nomeTurma) ?></p>
                        <?php foreach ($alunosTurma as $alunoOpcao): ?>
                        <label class="mural-aluno-item flex items-center gap-2 px-3 py-2 text-sm text-gray-800 hover:bg-gray-50 cursor-pointer" data-nome="<?= htmlspecialchars(mb_strtolower((string) $alunoOpcao['nome'])) ?>">
                            <input type="radio" name="aluno_id" value="<?= (int) $alunoOpcao['id'] ?>" class="mural-aluno-radio text-blue-600">
                            <?= htmlspecialchars((string) $alunoOpcao['nome']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div>
            <label for="mural_anexos" class="block text-sm font-medium text-gray-700 mb-1">Anexo ou imagem</label>
            <input type="file" id="mural_anexos" name="anexos[]" multiple
                   accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"
                   class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <p class="mt-1 text-xs text-gray-500">Até 5 arquivos, 8 MB cada. JPG, PNG, WEBP, GIF ou PDF.</p>
            <div id="mural_anexos_atuais" class="mt-3 space-y-2"></div>
        </div>
    </div>
    <div class="px-6 sm:px-8 py-4 border-t border-gray-200 flex gap-3">
        <button type="button" onclick="closeRecadoDrawer()" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancelar</button>
        <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
            <span id="mural-form-submit-label">Publicar</span>
        </button>
    </div>
</form>
<?php
$ui_offcanvas_body = ob_get_clean();
$ui_offcanvas_id = 'recado';
$ui_offcanvas_titulo = 'Novo Recado';
$ui_offcanvas_max_w = 'max-w-xl';
include $ui . '/offcanvas.php';
?>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<style>
.quill-editor-wrapper .ql-container { font-size: 1rem; }
.quill-editor-wrapper .ql-editor { min-height: 140px; }
.quill-editor-wrapper .ql-toolbar.ql-snow { border: none; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
.quill-editor-wrapper .ql-container.ql-snow { border: none; }
#recadoDrawerBackdrop { z-index: 60; }
#recadoDrawer { z-index: 70; }
</style>

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
<script>
(function () {
    var form = document.getElementById('mural-form');
    if (!form) return;
    var muralQuill = null;
    var base = <?= json_encode(URL . '/professor/mural-recados', JSON_UNESCAPED_SLASHES) ?>;
    var baseAnexo = <?= json_encode(URL . '/mural-recados/anexo', JSON_UNESCAPED_SLASHES) ?>;

    function getMuralQuill() {
        if (muralQuill) return muralQuill;
        var editor = document.getElementById('editor-conteudo-mural');
        if (!editor || typeof Quill === 'undefined') return null;
        muralQuill = new Quill('#editor-conteudo-mural', {
            theme: 'snow',
            placeholder: 'Digite o conteúdo do recado...',
            modules: { toolbar: [['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['link'], ['clean']] }
        });
        muralQuill.on('text-change', function () {
            var el = document.getElementById('mural_conteudo');
            if (el) el.value = muralQuill.root.innerHTML;
        });
        return muralQuill;
    }
    function setMuralConteudo(html) {
        var el = document.getElementById('mural_conteudo');
        if (el) el.value = html || '';
        var q = getMuralQuill();
        if (q) q.root.innerHTML = html || '';
    }
    function alcanceAtual() {
        var el = form.querySelector('[name="alcance"]:checked');
        return el ? el.value : 'todos';
    }
    function toggleDestino() {
        var alcance = alcanceAtual();
        document.getElementById('mural_turmas_container').classList.toggle('hidden', alcance !== 'turmas');
        document.getElementById('mural_aluno_box').classList.toggle('hidden', alcance !== 'aluno');
    }
    form.querySelectorAll('[name="alcance"]').forEach(function (radio) {
        radio.addEventListener('change', toggleDestino);
    });
    var busca = document.getElementById('mural_aluno_busca');
    if (busca) {
        busca.addEventListener('input', function () {
            var q = busca.value.toLocaleLowerCase().trim();
            document.querySelectorAll('.mural-aluno-grupo').forEach(function (grupo) {
                var visivel = 0;
                grupo.querySelectorAll('.mural-aluno-item').forEach(function (item) {
                    var nome = item.getAttribute('data-nome') || '';
                    var mostra = q === '' || nome.indexOf(q) !== -1;
                    item.classList.toggle('hidden', !mostra);
                    if (mostra) visivel++;
                });
                grupo.classList.toggle('hidden', visivel === 0);
            });
        });
    }
    function mostrarAnexosAtuais(lista) {
        var box = document.getElementById('mural_anexos_atuais');
        if (!box) return;
        box.innerHTML = '';
        (lista || []).forEach(function (anexo) {
            if (!anexo || !anexo.id) return;
            var linha = document.createElement('label');
            linha.className = 'flex items-start gap-2 text-sm text-gray-700';
            var check = document.createElement('input');
            check.type = 'checkbox';
            check.name = 'remover_anexos[]';
            check.value = String(anexo.id);
            check.className = 'mt-1 rounded border-gray-300 text-red-600';
            var texto = document.createElement('span');
            texto.className = 'min-w-0';
            if (anexo.imagem) {
                var img = document.createElement('img');
                img.src = baseAnexo + '/' + anexo.id;
                img.alt = anexo.nome || 'Imagem';
                img.className = 'max-h-24 rounded border border-gray-200 mb-1';
                texto.appendChild(img);
            }
            var nome = document.createElement('span');
            nome.className = 'block break-all';
            nome.textContent = anexo.nome || 'Anexo';
            var aviso = document.createElement('span');
            aviso.className = 'block text-xs text-gray-500';
            aviso.textContent = 'Marque para remover';
            texto.appendChild(nome);
            texto.appendChild(aviso);
            linha.appendChild(check);
            linha.appendChild(texto);
            box.appendChild(linha);
        });
    }
    function limparAlunos() {
        if (busca) busca.value = '';
        document.querySelectorAll('.mural-aluno-item, .mural-aluno-grupo').forEach(function (el) {
            el.classList.remove('hidden');
        });
        document.querySelectorAll('.mural-aluno-radio').forEach(function (radio) {
            radio.checked = false;
        });
    }
    window.abrirRecado = function (id) {
        var idInput = document.getElementById('mural_id');
        form.reset();
        idInput.value = '';
        idInput.disabled = true;
        limparAlunos();
        document.querySelectorAll('.mural-turma-check').forEach(function (c) { c.checked = false; });
        var todos = form.querySelector('[name="alcance"][value="todos"]');
        if (todos) todos.checked = true;
        setMuralConteudo('');
        mostrarAnexosAtuais([]);
        toggleDestino();

        if (!id) {
            form.action = base + '/salvar';
            document.getElementById('recadoDrawerTitle').textContent = 'Novo Recado';
            document.getElementById('mural-form-submit-label').textContent = 'Publicar';
            if (typeof openRecadoDrawer === 'function') openRecadoDrawer();
            return;
        }

        form.action = base + '/atualizar';
        document.getElementById('recadoDrawerTitle').textContent = 'Editar Recado';
        document.getElementById('mural-form-submit-label').textContent = 'Atualizar';
        if (typeof openRecadoDrawer === 'function') openRecadoDrawer();

        fetch(base + '/' + id + '/dados', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    alert(data.error || 'Não foi possível carregar o recado.');
                    if (typeof closeRecadoDrawer === 'function') closeRecadoDrawer();
                    return;
                }
                var item = data.item || {};
                idInput.value = item.id || id;
                idInput.disabled = false;
                document.getElementById('mural_titulo').value = item.titulo || '';
                setMuralConteudo(item.conteudo || '');
                var alcance = ['todos', 'turmas', 'aluno'].indexOf(item.alcance) !== -1 ? item.alcance : 'todos';
                var radio = form.querySelector('[name="alcance"][value="' + alcance + '"]');
                if (radio) radio.checked = true;
                var turmas = item.turmas || [];
                document.querySelectorAll('.mural-turma-check').forEach(function (c) {
                    c.checked = turmas.indexOf(parseInt(c.value, 10)) !== -1;
                });
                if (item.aluno_id) {
                    var aluno = form.querySelector('.mural-aluno-radio[value="' + item.aluno_id + '"]');
                    if (aluno) aluno.checked = true;
                }
                mostrarAnexosAtuais(item.anexos || []);
                toggleDestino();
            })
            .catch(function () {
                alert('Erro de conexão ao carregar o recado.');
                if (typeof closeRecadoDrawer === 'function') closeRecadoDrawer();
            });
    };
    document.querySelectorAll('.btn-editar-recado').forEach(function (btn) {
        btn.addEventListener('click', function () {
            abrirRecado(parseInt(btn.getAttribute('data-recado-id'), 10));
        });
    });
    form.addEventListener('submit', function () {
        var el = document.getElementById('mural_conteudo');
        var q = getMuralQuill();
        if (el && q) el.value = q.root.innerHTML;
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && typeof closeRecadoDrawer === 'function') {
            var drawer = document.getElementById('recadoDrawer');
            if (drawer && drawer.getAttribute('aria-hidden') === 'false') closeRecadoDrawer();
        }
    });
    var params = new URLSearchParams(window.location.search);
    if (params.get('novo') === '1') {
        abrirRecado();
    } else {
        var recadoId = parseInt(params.get('recado') || '0', 10);
        if (recadoId > 0) abrirRecado(recadoId);
    }
})();
</script>
