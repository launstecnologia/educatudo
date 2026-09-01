<?php
$reunioes = is_array($reunioes ?? null) ? $reunioes : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$relatorPadrao = htmlspecialchars((string) ($relator_padrao ?? ''));
$salvoId = (int) ($salvo_id ?? 0);
$pag = is_array($pagination ?? null) ? $pagination : [];
$pagTotal = (int) ($pag['total'] ?? 0);
$pagPerPage = (int) ($pag['per_page'] ?? 10);
$pagPage = (int) ($pag['page'] ?? 1);
$pagTotalPages = (int) ($pag['total_pages'] ?? 1);

$flash_status = (string) ($flash_type ?? '');
$flash_message = (string) ($flash_message ?? '');
include __DIR__ . '/../_partials/flash_message.php';

if ($salvoId > 0):
?>
<div class="mb-6 p-4 rounded-lg bg-green-50 border border-green-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <p class="text-sm text-green-800">Ata pronta para arquivo. O PDF reúne pauta, participantes, registro e encaminhamentos.</p>
    <a href="<?= URL ?>/admin/reunioes/geral/<?= $salvoId ?>/pdf" target="_blank" rel="noopener"
       class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
        <i class="fa-solid fa-file-pdf mr-2"></i>Baixar ata em PDF
    </a>
</div>
<?php
endif;

$page_header_title = 'Reuniões';
$page_header_subtitle = 'Atas de reuniões com turmas, séries ou toda a escola.';
ob_start();
?>
<button type="button" onclick="openReuniaoDrawer()" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
    <i class="fa-solid fa-plus mr-2"></i>Nova reunião
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
<?php if (empty($reunioes)): ?>
    <div class="px-6 py-12 text-center text-gray-500">
        <i class="fa-solid fa-users text-3xl text-gray-300 mb-3 block"></i>
        <p>Nenhuma reunião geral registrada.</p>
        <button type="button" onclick="openReuniaoDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
            <i class="fa-solid fa-plus mr-2"></i>Nova reunião
        </button>
    </div>
<?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reunião</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Local</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turmas</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($reunioes as $r):
                $hora = '';
                if (!empty($r['hora_inicio'])) {
                    $hora = substr((string) $r['hora_inicio'], 0, 5);
                    if (!empty($r['hora_fim'])) {
                        $hora .= '–' . substr((string) $r['hora_fim'], 0, 5);
                    }
                }
            ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4">
                        <strong class="text-gray-900"><?= htmlspecialchars((string) $r['titulo']) ?></strong>
                        <?php if (!empty($r['relator_nome'])): ?>
                        <div class="text-xs text-gray-500 mt-0.5">Relator: <?= htmlspecialchars((string) $r['relator_nome']) ?></div>
                        <?php endif; ?>
                        <?php
                        $nomesAnexos = !empty($r['anexo_nomes']) ? explode('|', (string) $r['anexo_nomes']) : [];
                        $caminhosAnexos = !empty($r['anexo_caminhos']) ? explode('|', (string) $r['anexo_caminhos']) : [];
                        if (!empty($nomesAnexos)):
                        ?>
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            <?php foreach ($nomesAnexos as $idx => $nomeAnexo): ?>
                            <a href="<?= URL ?>/<?= htmlspecialchars((string) ($caminhosAnexos[$idx] ?? '#')) ?>" target="_blank" rel="noopener" class="text-xs text-blue-600 hover:underline">
                                <i class="fa-solid fa-paperclip"></i> <?= htmlspecialchars($nomeAnexo) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-gray-600">
                        <?= date('d/m/Y', strtotime((string) $r['data_reuniao'])) ?>
                        <?php if ($hora !== ''): ?><div class="text-xs text-gray-400"><?= htmlspecialchars($hora) ?></div><?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-gray-600"><?= htmlspecialchars((string) ($r['local_reuniao'] ?: '—')) ?></td>
                    <td class="px-4 py-4 text-gray-600"><?= htmlspecialchars((string) (($r['turmas_nomes'] ?? '') !== '' ? $r['turmas_nomes'] : 'Toda a escola')) ?></td>
                    <td class="px-4 py-4 text-right">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openReuniaoDrawer(<?= (int) $r['id'] ?>)" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <a href="<?= URL ?>/admin/reunioes/geral/<?= (int) $r['id'] ?>/pdf" target="_blank" rel="noopener" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-file-pdf text-gray-400 w-4 text-center"></i> Baixar PDF
                        </a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="post" action="<?= URL ?>/admin/reunioes/geral/excluir" onsubmit="return confirm('Remover esta reunião?');">
                            <input type="hidden" name="_token" value="<?= $csrf ?>">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                            </button>
                        </form>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-reuniao-' . (int) $r['id'];
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> reunião(ões)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/reunioes/geral?page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/reunioes/geral?page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/reunioes/geral?page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<div id="reuniaoDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeReuniaoDrawer()"></div>
<aside id="reuniaoDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="reuniaoDrawerTitle" class="text-xl font-bold text-gray-900">Nova reunião</h2>
        <button type="button" onclick="closeReuniaoDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="reuniao-form" method="post" action="<?= URL ?>/admin/reunioes/geral/salvar" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <input type="hidden" name="id" id="reuniao_id" value="" disabled>
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados da reunião</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="reuniao_titulo" class="block text-sm font-medium text-gray-700 mb-1">Título / pauta <span class="text-red-500">*</span></label>
                        <input type="text" id="reuniao_titulo" name="titulo" required maxlength="255" placeholder="Ex: Reunião de pais do 1º bimestre"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="reuniao_data" class="block text-sm font-medium text-gray-700 mb-1">Data <span class="text-red-500">*</span></label>
                        <input type="date" id="reuniao_data" name="data_reuniao" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="reuniao_relator" class="block text-sm font-medium text-gray-700 mb-1">Relator</label>
                        <input type="text" id="reuniao_relator" name="relator_nome" maxlength="255" value="<?= $relatorPadrao ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="reuniao_hora_inicio" class="block text-sm font-medium text-gray-700 mb-1">Início</label>
                        <input type="time" id="reuniao_hora_inicio" name="hora_inicio"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="reuniao_hora_fim" class="block text-sm font-medium text-gray-700 mb-1">Fim</label>
                        <input type="time" id="reuniao_hora_fim" name="hora_fim"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="reuniao_local" class="block text-sm font-medium text-gray-700 mb-1">Local</label>
                        <input type="text" id="reuniao_local" name="local_reuniao" maxlength="255" placeholder="Ex: Auditório, Pátio..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="reuniao_link" class="block text-sm font-medium text-gray-700 mb-1">Link (Meet / Zoom)</label>
                        <input type="text" id="reuniao_link" name="link_reuniao" maxlength="500" placeholder="https://meet.google.com/..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Participantes</h3>
                <p class="text-sm text-gray-500 mb-3">Deixe as turmas em branco para indicar reunião com toda a escola.</p>
                <?php if (!empty($turmas)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3 mb-4">
                    <?php foreach ($turmas as $t): ?>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="turma_ids[]" value="<?= (int) $t['id'] ?>" class="reuniao-turma-check rounded border-gray-300 text-green-600">
                        <?= htmlspecialchars((string) $t['nome']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <label for="reuniao_participantes" class="block text-sm font-medium text-gray-700 mb-1">Presentes</label>
                <textarea id="reuniao_participantes" name="participantes" rows="3" placeholder="Nomes de quem participou (um por linha ou separados por vírgula)"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Ata</h3>
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Registro da reunião</label>
                        <textarea name="descricao" id="reuniao_descricao" class="hidden"></textarea>
                        <div id="editor-reuniao-descricao" class="quill-editor-wrapper border border-gray-300 rounded-lg overflow-hidden bg-white" style="min-height: 160px;"></div>
                    </div>
                    <div>
                        <label for="reuniao_encaminhamentos" class="block text-sm font-medium text-gray-700 mb-1">Decisões e encaminhamentos</label>
                        <textarea id="reuniao_encaminhamentos" name="encaminhamentos" rows="3" placeholder="O que ficou combinado e os próximos passos"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                    <div>
                        <label for="reuniao_anexos" class="block text-sm font-medium text-gray-700 mb-1">Anexos</label>
                        <input type="file" id="reuniao_anexos" name="anexos[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,.doc,.docx,.xls,.xlsx"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <p class="text-xs text-gray-500 mt-1">Imagens, PDF, Word ou Excel.</p>
                        <div id="reuniao-anexos-existentes" class="hidden mt-2 text-xs text-gray-600 space-y-1"></div>
                    </div>
                </div>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeReuniaoDrawer()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="reuniao-form-submit-label">Salvar reunião</span>
            </button>
        </div>
    </form>
</aside>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
.quill-editor-wrapper .ql-container { font-size: 1rem; }
.quill-editor-wrapper .ql-editor { min-height: 140px; }
.quill-editor-wrapper .ql-toolbar.ql-snow { border: none; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
.quill-editor-wrapper .ql-container.ql-snow { border: none; }
</style>
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
var reuniaoQuill = null;
var reuniaoBase = <?= json_encode(URL . '/admin/reunioes/geral', JSON_UNESCAPED_SLASHES) ?>;
var reuniaoRelatorPadrao = <?= json_encode((string) ($relator_padrao ?? ''), JSON_UNESCAPED_UNICODE) ?>;

function escReuniaoHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
}
function dataLocalISO() {
    var d = new Date();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + m + '-' + day;
}
function getReuniaoQuill() {
    if (reuniaoQuill) return reuniaoQuill;
    if (typeof Quill === 'undefined') return null;
    reuniaoQuill = new Quill('#editor-reuniao-descricao', {
        theme: 'snow',
        placeholder: 'Pauta, o que foi discutido e o registro da reunião...',
        modules: { toolbar: [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] }
    });
    reuniaoQuill.on('text-change', function () {
        var el = document.getElementById('reuniao_descricao');
        if (el) el.value = reuniaoQuill.root.innerHTML;
    });
    return reuniaoQuill;
}
function setReuniaoDescricao(html) {
    var el = document.getElementById('reuniao_descricao');
    if (el) el.value = html || '';
    var q = getReuniaoQuill();
    if (q) q.root.innerHTML = html || '';
}
function showReuniaoDrawer() {
    document.getElementById('reuniaoDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('reuniaoDrawer');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
    document.body.style.overflow = 'hidden';
}
function closeReuniaoDrawer() {
    document.getElementById('reuniaoDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('reuniaoDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
function resetReuniaoTurmas(ids) {
    ids = ids || [];
    document.querySelectorAll('.reuniao-turma-check').forEach(function (c) {
        c.checked = ids.indexOf(parseInt(c.value, 10)) !== -1;
    });
}
function openReuniaoDrawer(id) {
    var form = document.getElementById('reuniao-form');
    var idInput = document.getElementById('reuniao_id');
    form.reset();
    idInput.value = '';
    idInput.disabled = true;
    document.getElementById('reuniao_relator').value = reuniaoRelatorPadrao;
    document.getElementById('reuniao_data').value = dataLocalISO();
    setReuniaoDescricao('');
    resetReuniaoTurmas([]);
    document.getElementById('reuniao-anexos-existentes').classList.add('hidden');
    document.getElementById('reuniao-anexos-existentes').innerHTML = '';

    if (!id) {
        form.dataset.mode = 'create';
        form.action = reuniaoBase + '/salvar';
        document.getElementById('reuniaoDrawerTitle').textContent = 'Nova reunião';
        document.getElementById('reuniao-form-submit-label').textContent = 'Salvar reunião';
        showReuniaoDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    form.action = reuniaoBase + '/atualizar';
    document.getElementById('reuniaoDrawerTitle').textContent = 'Editar reunião';
    document.getElementById('reuniao-form-submit-label').textContent = 'Salvar alterações';
    showReuniaoDrawer();

    fetch(reuniaoBase + '/' + id + '/dados', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar a reunião.'));
                closeReuniaoDrawer();
                return;
            }
            var item = data.item || {};
            idInput.value = item.id || id;
            idInput.disabled = false;
            document.getElementById('reuniao_titulo').value = item.titulo || '';
            document.getElementById('reuniao_data').value = item.data_reuniao || '';
            document.getElementById('reuniao_hora_inicio').value = item.hora_inicio || '';
            document.getElementById('reuniao_hora_fim').value = item.hora_fim || '';
            document.getElementById('reuniao_local').value = item.local_reuniao || '';
            document.getElementById('reuniao_link').value = item.link_reuniao || '';
            document.getElementById('reuniao_relator').value = item.relator_nome || reuniaoRelatorPadrao;
            document.getElementById('reuniao_participantes').value = item.participantes || '';
            document.getElementById('reuniao_encaminhamentos').value = item.encaminhamentos || '';
            setReuniaoDescricao(item.descricao || '');
            resetReuniaoTurmas(item.turmas || []);
            var anexos = item.anexos || [];
            var box = document.getElementById('reuniao-anexos-existentes');
            if (anexos.length) {
                box.classList.remove('hidden');
                box.innerHTML = anexos.map(function (a) {
                    return '<div><i class="fa-solid fa-paperclip mr-1"></i>' + escReuniaoHtml(a.nome || '') + '</div>';
                }).join('');
            }
        })
        .catch(function () {
            alert('Erro de conexão ao carregar a reunião.');
            closeReuniaoDrawer();
        });
}
document.getElementById('reuniao-form').addEventListener('submit', function () {
    var el = document.getElementById('reuniao_descricao');
    var q = getReuniaoQuill();
    if (el && q) el.value = q.root.innerHTML === '<p><br></p>' ? '' : q.root.innerHTML;
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeReuniaoDrawer();
});
(function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('novo') === '1') {
        openReuniaoDrawer();
        return;
    }
    var id = parseInt(params.get('reuniao') || '0', 10);
    if (id > 0) openReuniaoDrawer(id);
})();
</script>
