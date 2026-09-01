<?php $base = $base_url ?? (URL . '/admin/mural-recados'); ?>
<?php
$filtrosAtivosCount = 0;
foreach ([$filtro_materia ?? 0, $filtro_professor ?? 0, $filtro_turma ?? 0, $filtro_assunto ?? '', $filtro_data_de ?? '', $filtro_data_ate ?? ''] as $fv) {
    if (!empty($fv)) {
        $filtrosAtivosCount++;
    }
}
?>
<div id="mural-excluir-feedback" class="hidden mb-4 p-4 rounded-lg bg-green-100 text-green-800 text-sm" role="status"></div>
<!-- Header -->
<div class="mb-8">
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Mural de Recados</h2>
            <p class="text-gray-600">Recados para turmas ou todos os alunos.</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>
            <button type="button" onclick="openMuralDrawer()"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Recado
            </button>
        </div>
    </div>
</div>

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

<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar recados</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="get" action="<?= $base ?>" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro-assunto-mural" class="block text-sm font-medium text-gray-700 mb-1.5">Assunto</label>
                <input type="text" id="filtro-assunto-mural" name="assunto" value="<?= htmlspecialchars($filtro_assunto ?? '') ?>"
                       placeholder="Título ou conteúdo..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro-materia-mural" class="block text-sm font-medium text-gray-700 mb-1.5">Matéria</label>
                <select id="filtro-materia-mural" name="materia_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($materias_opcoes ?? [] as $mat): ?>
                    <option value="<?= (int)$mat['id'] ?>" <?= (int)($filtro_materia ?? 0) === (int)$mat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro-professor-mural" class="block text-sm font-medium text-gray-700 mb-1.5">Professor</label>
                <select id="filtro-professor-mural" name="professor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($professores_opcoes ?? [] as $prof): ?>
                    <option value="<?= (int)$prof['id'] ?>" <?= (int)($filtro_professor ?? 0) === (int)$prof['id'] ? 'selected' : '' ?>><?= htmlspecialchars($prof['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro-turma-mural" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="filtro-turma-mural" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($turmas_opcoes ?? [] as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)($filtro_turma ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="filtro-data-de-mural" class="block text-sm font-medium text-gray-700 mb-1.5">Data de</label>
                    <input type="date" id="filtro-data-de-mural" name="data_de" value="<?= htmlspecialchars($filtro_data_de ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="filtro-data-ate-mural" class="block text-sm font-medium text-gray-700 mb-1.5">Data até</label>
                    <input type="date" id="filtro-data-ate-mural" name="data_ate" value="<?= htmlspecialchars($filtro_data_ate ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <a href="<?= $base ?>"
               class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors text-center">
                Limpar
            </a>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<div id="muralDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeMuralDrawer()"></div>
<aside id="muralDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="muralDrawerTitle" class="text-xl font-bold text-gray-900">Novo Recado</h2>
        <button type="button" onclick="closeMuralDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="mural-form" method="post" action="<?= htmlspecialchars($base) ?>/salvar" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <input type="hidden" name="id" id="mural_id" value="" disabled>
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Recado</h3>
                <div class="grid grid-cols-1 gap-y-5">
                    <div>
                        <label for="mural_titulo" class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                        <input type="text" id="mural_titulo" name="titulo" required maxlength="255"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Conteúdo</label>
                        <textarea name="conteudo" id="mural_conteudo" class="hidden"></textarea>
                        <div id="editor-conteudo-mural" class="quill-editor-wrapper border border-gray-300 rounded-lg overflow-hidden bg-white" style="min-height: 180px;"></div>
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Destinatários</h3>
                <div class="flex flex-wrap gap-5 mb-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="enviar_para_todos" value="1" checked class="text-green-600 border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Enviar para todos</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="enviar_para_todos" value="0" id="mural_radio_turmas" class="text-green-600 border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Selecionar turmas</span>
                    </label>
                </div>
                <div id="mural_turmas_container" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Turmas</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-3">
                        <?php foreach ($turmas_opcoes ?? [] as $t): ?>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="turmas[]" value="<?= (int)$t['id'] ?>" class="mural-turma-check rounded border-gray-300 text-green-600">
                            <?= htmlspecialchars($t['nome']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeMuralDrawer()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="mural-form-submit-label">Publicar</span>
            </button>
        </div>
    </form>
</aside>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
.quill-editor-wrapper .ql-container { font-size: 1rem; }
.quill-editor-wrapper .ql-editor { min-height: 160px; }
.quill-editor-wrapper .ql-toolbar.ql-snow { border: none; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
.quill-editor-wrapper .ql-container.ql-snow { border: none; }
</style>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Autor</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destinatários</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($recados)): ?>
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhum recado ainda.</td>
            </tr>
            <?php else: ?>
            <?php
            foreach ($recados as $r):
                $autor_nome = htmlspecialchars($r['autor_nome'] ?? ($r['autor_tipo'] === 'professor' ? 'Professor' : 'Admin'));
                $data_pub = date('d/m/Y H:i', strtotime($r['data_publicacao']));
                $conteudo_esc = htmlspecialchars($r['conteudo'] ?? '');
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars($r['titulo']) ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= $autor_nome ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= !empty($r['enviar_para_todos']) ? 'Todos' : (htmlspecialchars($r['turmas_nomes'] ?? '-')) ?></td>
                <td class="px-6 py-4 text-sm text-gray-500"><?= $data_pub ?></td>
                <td class="px-6 py-4 text-right">
                    <?php ob_start(); ?>
                    <button type="button" class="btn-visualizar-recado flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" data-titulo="<?= htmlspecialchars($r['titulo'], ENT_QUOTES, 'UTF-8') ?>" data-data-pub="<?= htmlspecialchars($data_pub, ENT_QUOTES, 'UTF-8') ?>" data-conteudo="<?= htmlspecialchars($r['conteudo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-autor="<?= htmlspecialchars($autor_nome, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Visualizar
                    </button>
                    <button type="button" onclick="openMuralDrawer(<?= (int)$r['id'] ?>)" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                    </button>
                    <div class="border-t border-gray-100 my-1"></div>
                    <button type="button"
                            class="btn-abrir-excluir-mural flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                            data-id="<?= (int)$r['id'] ?>"
                            data-titulo="<?= htmlspecialchars($r['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                    </button>
                    <?php
                    $row_actions_dropdown_items = ob_get_clean();
                    $row_actions_dropdown_id = 'row-actions-recado-' . (int)$r['id'];
                    include __DIR__ . '/../_partials/row_actions_dropdown.php';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    $pag = $pagination ?? [];
    $pagTotal = (int)($pag['total'] ?? 0);
    $pagPerPage = (int)($pag['per_page'] ?? 10);
    $pagPage = (int)($pag['page'] ?? 1);
    $pagTotalPages = (int)($pag['total_pages'] ?? 1);
    $pagQueryParams = array_filter([
        'professor_id' => $filtro_professor ?? '',
        'materia_id' => $filtro_materia ?? '',
        'turma_id' => $filtro_turma ?? '',
        'assunto' => $filtro_assunto ?? '',
        'data_de' => $filtro_data_de ?? '',
        'data_ate' => $filtro_data_ate ?? '',
    ]);
    $pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
    $pagSep = $pagBaseQuery === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> recado(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= $base . $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= $base . $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= $base . $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: excluir recado (senha + checagem de vínculos no servidor) -->
<div id="modalExcluirMural" class="fixed inset-0 bg-black/60 z-[70] hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col" role="dialog" aria-labelledby="modalExcluirMuralTitulo">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 id="modalExcluirMuralTitulo" class="text-lg font-bold text-gray-900">Excluir recado</h2>
            <button type="button" class="modal-excluir-mural-fechar p-2 rounded-lg text-gray-500 hover:bg-gray-100" title="Fechar" aria-label="Fechar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-sm text-gray-600">Para excluir permanentemente o recado <strong id="modalExcluirMuralNomeRecado" class="text-gray-900"></strong>, digite a <strong>senha da sua conta</strong> de administrador.</p>
            <p class="text-xs text-gray-500">A exclusão só é permitida se o recado não estiver vinculado a outros módulos (boletim, notas, etc.).</p>
            <div id="modalExcluirMuralErro" class="hidden text-sm text-red-700 bg-red-50 border border-red-100 rounded-lg px-3 py-2"></div>
            <div>
                <label for="muralSenhaExcluir" class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                <input type="password" id="muralSenhaExcluir" autocomplete="current-password" class="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500" placeholder="Sua senha de login">
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2">
            <button type="button" class="modal-excluir-mural-fechar px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">Cancelar</button>
            <button type="button" id="btnConfirmarExcluirMural" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50" title="Confirmar exclusão com a senha informada">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Excluir definitivamente
            </button>
        </div>
    </div>
</div>

<!-- Modal Visualizar Recado (mesmo conteúdo que o aluno vê) -->
<div id="modalRecadoAdmin" class="fixed inset-0 bg-black bg-opacity-60 z-[60] hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 text-white flex items-center justify-between bg-blue-600">
            <h2 class="text-xl font-bold flex items-center"><span class="mr-2">📌</span> Recado no Mural</h2>
            <button type="button" id="btnFecharModalRecadoAdmin" class="text-white hover:bg-white/20 p-2 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1">
            <h3 id="modalRecadoTitulo" class="font-semibold text-gray-900 text-lg mb-2"></h3>
            <p id="modalRecadoMeta" class="text-sm text-gray-500 mb-3"></p>
            <div id="modalRecadoConteudo" class="text-gray-700 prose prose-sm max-w-none"></div>
        </div>
        <div class="p-4 border-t bg-gray-50 flex justify-end">
            <button type="button" id="btnFecharModalRecadoAdmin2" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">Fechar</button>
        </div>
    </div>
</div>
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
(function() {
    var baseExcluir = <?= json_encode($base ?? (URL . '/admin/mural-recados'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
    var csrfToken = <?= json_encode($csrf_token ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
    var modalEx = document.getElementById('modalExcluirMural');
    var muralSenha = document.getElementById('muralSenhaExcluir');
    var modalExErro = document.getElementById('modalExcluirMuralErro');
    var modalExNome = document.getElementById('modalExcluirMuralNomeRecado');
    var btnConfirmEx = document.getElementById('btnConfirmarExcluirMural');
    var excluirRecadoId = null;
    function fecharModalExcluir() {
        if (!modalEx) return;
        modalEx.style.display = 'none';
        modalEx.classList.add('hidden');
        excluirRecadoId = null;
        if (muralSenha) muralSenha.value = '';
        if (modalExErro) { modalExErro.textContent = ''; modalExErro.classList.add('hidden'); }
        if (btnConfirmEx) btnConfirmEx.disabled = false;
    }
    function abrirModalExcluir(id, titulo) {
        excluirRecadoId = id;
        if (modalExNome) modalExNome.textContent = titulo || '';
        if (muralSenha) muralSenha.value = '';
        if (modalExErro) { modalExErro.textContent = ''; modalExErro.classList.add('hidden'); }
        if (modalEx) { modalEx.style.display = 'flex'; modalEx.classList.remove('hidden'); }
        if (muralSenha) setTimeout(function() { muralSenha.focus(); }, 100);
    }
    document.querySelectorAll('.btn-abrir-excluir-mural').forEach(function(btn) {
        btn.addEventListener('click', function() {
            abrirModalExcluir(parseInt(this.getAttribute('data-id'), 10), this.getAttribute('data-titulo') || '');
        });
    });
    document.querySelectorAll('.modal-excluir-mural-fechar').forEach(function(b) {
        b.addEventListener('click', fecharModalExcluir);
    });
    if (modalEx) {
        modalEx.addEventListener('click', function(e) { if (e.target === modalEx) fecharModalExcluir(); });
    }
    if (btnConfirmEx) {
        btnConfirmEx.addEventListener('click', function() {
            if (!excluirRecadoId) return;
            var senha = muralSenha ? muralSenha.value : '';
            if (!senha) {
                if (modalExErro) { modalExErro.textContent = 'Digite sua senha.'; modalExErro.classList.remove('hidden'); }
                return;
            }
            btnConfirmEx.disabled = true;
            if (modalExErro) { modalExErro.textContent = ''; modalExErro.classList.add('hidden'); }
            var body = new URLSearchParams();
            body.set('_token', csrfToken);
            body.set('id', String(excluirRecadoId));
            body.set('senha', senha);
            fetch(baseExcluir + '/excluir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: body.toString(),
                credentials: 'same-origin'
            }).then(function(r) {
                return r.text().then(function(text) {
                    var j = null;
                    try { j = JSON.parse(text); } catch (e) { j = null; }
                    return { ok: r.ok, status: r.status, json: j };
                });
            }).then(function(res) {
                if (res.json && res.json.success) {
                    fecharModalExcluir();
                    var fb = document.getElementById('mural-excluir-feedback');
                    if (fb) { fb.textContent = res.json.message || 'Recado excluído.'; fb.classList.remove('hidden'); }
                    setTimeout(function() { window.location.reload(); }, 900);
                    return;
                }
                var msg = (res.json && res.json.error) ? res.json.error : 'Não foi possível excluir (resposta inválida do servidor).';
                if (res.json && res.json.detalhe) msg += ' ' + res.json.detalhe;
                if (modalExErro) { modalExErro.textContent = msg; modalExErro.classList.remove('hidden'); }
                btnConfirmEx.disabled = false;
            }).catch(function() {
                if (modalExErro) { modalExErro.textContent = 'Erro de rede. Tente novamente.'; modalExErro.classList.remove('hidden'); }
                btnConfirmEx.disabled = false;
            });
        });
    }
    if (muralSenha && btnConfirmEx) {
        muralSenha.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); btnConfirmEx.click(); }
        });
    }

    var modal = document.getElementById('modalRecadoAdmin');
    var tituloEl = document.getElementById('modalRecadoTitulo');
    var metaEl = document.getElementById('modalRecadoMeta');
    var conteudoEl = document.getElementById('modalRecadoConteudo');
    function fechar() {
        if (modal) { modal.style.display = 'none'; modal.classList.add('hidden'); }
    }
    function decodeHtml(str) {
        if (!str) return '';
        var t = document.createElement('textarea');
        t.innerHTML = str;
        return t.value;
    }
    function abrir(titulo, dataPub, autor, conteudo) {
        tituloEl.textContent = titulo || '';
        metaEl.textContent = 'Por ' + (autor || '') + ' · Publicado em ' + (dataPub || '');
        var decoded = decodeHtml(conteudo || '');
        if (decoded.indexOf('<') !== -1) {
            conteudoEl.innerHTML = decoded;
        } else {
            conteudoEl.innerHTML = decoded.replace(/\n/g, '<br>');
        }
        if (modal) { modal.style.display = 'flex'; modal.classList.remove('hidden'); }
    }
    document.querySelectorAll('.btn-visualizar-recado').forEach(function(btn) {
        btn.addEventListener('click', function() {
            abrir(
                this.getAttribute('data-titulo'),
                this.getAttribute('data-data-pub'),
                this.getAttribute('data-autor'),
                this.getAttribute('data-conteudo')
            );
        });
    });
    document.getElementById('btnFecharModalRecadoAdmin') && document.getElementById('btnFecharModalRecadoAdmin').addEventListener('click', fechar);
    document.getElementById('btnFecharModalRecadoAdmin2') && document.getElementById('btnFecharModalRecadoAdmin2').addEventListener('click', fechar);
    modal && modal.addEventListener('click', function(e) { if (e.target === modal) fechar(); });
})();

function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
        closeMuralDrawer();
    }
});

var muralQuill = null;
function getMuralQuill() {
    if (muralQuill) return muralQuill;
    var editor = document.getElementById('editor-conteudo-mural');
    if (!editor || typeof Quill === 'undefined') return null;
    muralQuill = new Quill('#editor-conteudo-mural', {
        theme: 'snow',
        placeholder: 'Digite o conteúdo do recado...',
        modules: { toolbar: [['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['link'], ['clean']] }
    });
    muralQuill.on('text-change', function() {
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
function toggleMuralTurmas() {
    var todos = document.querySelector('#mural-form [name="enviar_para_todos"]:checked');
    var paraTodos = !todos || todos.value === '1';
    document.getElementById('mural_turmas_container').classList.toggle('hidden', paraTodos);
}
document.querySelectorAll('#mural-form [name="enviar_para_todos"]').forEach(function(r) {
    r.addEventListener('change', toggleMuralTurmas);
});
function showMuralDrawer() {
    document.getElementById('muralDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('muralDrawer');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function() { drawer.classList.remove('translate-x-full'); });
    document.body.style.overflow = 'hidden';
}
function closeMuralDrawer() {
    document.getElementById('muralDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('muralDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
function openMuralDrawer(id) {
    var form = document.getElementById('mural-form');
    var idInput = document.getElementById('mural_id');
    form.reset();
    idInput.value = '';
    idInput.disabled = true;
    setMuralConteudo('');
    document.querySelectorAll('.mural-turma-check').forEach(function(c) { c.checked = false; });
    document.querySelector('#mural-form [name="enviar_para_todos"][value="1"]').checked = true;
    toggleMuralTurmas();

    if (!id) {
        form.dataset.mode = 'create';
        form.action = <?= json_encode(($base ?? (URL . '/admin/mural-recados')) . '/salvar', JSON_UNESCAPED_SLASHES) ?>;
        document.getElementById('muralDrawerTitle').textContent = 'Novo Recado';
        document.getElementById('mural-form-submit-label').textContent = 'Publicar';
        showMuralDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    form.action = <?= json_encode(($base ?? (URL . '/admin/mural-recados')) . '/atualizar', JSON_UNESCAPED_SLASHES) ?>;
    document.getElementById('muralDrawerTitle').textContent = 'Editar Recado';
    document.getElementById('mural-form-submit-label').textContent = 'Atualizar';
    showMuralDrawer();

    fetch(<?= json_encode($base ?? (URL . '/admin/mural-recados'), JSON_UNESCAPED_SLASHES) ?> + '/' + id + '/dados', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) {
            alert('Erro: ' + (data.error || 'Não foi possível carregar o recado.'));
            closeMuralDrawer();
            return;
        }
        var item = data.item || {};
        idInput.value = item.id || id;
        idInput.disabled = false;
        document.getElementById('mural_titulo').value = item.titulo || '';
        setMuralConteudo(item.conteudo || '');
        var paraTodos = Number(item.enviar_para_todos) === 1;
        document.querySelector('#mural-form [name="enviar_para_todos"][value="' + (paraTodos ? '1' : '0') + '"]').checked = true;
        var turmas = item.turmas || [];
        document.querySelectorAll('.mural-turma-check').forEach(function(c) {
            c.checked = turmas.indexOf(parseInt(c.value, 10)) !== -1;
        });
        toggleMuralTurmas();
    }).catch(function() {
        alert('Erro de conexão ao carregar o recado.');
        closeMuralDrawer();
    });
}
document.getElementById('mural-form').addEventListener('submit', function() {
    var el = document.getElementById('mural_conteudo');
    var q = getMuralQuill();
    if (el && q) el.value = q.root.innerHTML;
});
(function() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('novo') === '1') {
        openMuralDrawer();
        return;
    }
    var recadoId = parseInt(params.get('recado') || '0', 10);
    if (recadoId > 0) openMuralDrawer(recadoId);
})();
</script>
