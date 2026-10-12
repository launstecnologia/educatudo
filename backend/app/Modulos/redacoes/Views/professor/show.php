<?php
$basePrefix = $base_prefix ?? '/professor/redacao-configuravel';
$permissionBasePrefix = $permission_base_prefix ?? $basePrefix;
$isAdminView = !empty($is_admin_view);
$canManagePermissions = !empty($can_manage_permissions);
$repertoriosList = [];
if (!empty($proposal['repertoire'])) {
    $rawRep = trim((string)$proposal['repertoire']);
    if (preg_match('/^\s*\[/', $rawRep)) {
        $decRep = json_decode($rawRep, true);
        $repertoriosList = is_array($decRep) ? $decRep : [];
    }
}
$imagesList = [];
if (!empty($proposal['images_json'])) {
    $rawImg = (string)$proposal['images_json'];
    $decImg = json_decode($rawImg, true);
    if (is_array($decImg)) {
        $imagesList = $decImg;
    }
}
$hasColetanea = !empty($proposal['theme']) || !empty($proposal['contexto']) || !empty($proposal['repertoire']) || !empty($proposal['tema_pronto_file']) || !empty($imagesList);
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($proposal['title']) ?></h2>
            <p class="text-gray-600"><?= htmlspecialchars($proposal['board_name']) ?> — <?= htmlspecialchars($proposal['text_type_name']) ?></p>
        </div>
        <div class="flex space-x-2">
            <?php if (!$isAdminView): ?>
            <a href="<?= URL . $basePrefix ?>/<?= (int)$proposal['id'] ?>/editar" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Editar</a>
            <?php endif; ?>
            <?php if ($hasColetanea): ?>
            <button type="button" id="btnColetaneaAbrir" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">Coletânea</button>
            <?php endif; ?>
            <a href="<?= URL . ($isAdminView ? '/admin/redacao-configuravel' : '/professor/redacao-configuravel') ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">Voltar</a>
        </div>
    </div>
</div>

<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4">
        <h3 class="text-lg font-semibold text-gray-900">Envios dos alunos</h3>
        <div class="flex items-center gap-2">
            <?php if (!$isAdminView): ?>
            <a href="<?= URL . $basePrefix . '/' . (int)$proposal['id'] . '/exportar-excel' ?>"
               class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">
                Exportar Excel
            </a>
            <?php endif; ?>
            <?php if (!empty($studentsWithAccess)): ?>
            <button type="button" id="btnEnviarLote" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium">Enviar redações em bloco</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/70">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
                <label for="filtroNomeAluno" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Filtrar por nome</label>
                <input type="text" id="filtroNomeAluno" placeholder="Digite o nome do aluno..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label for="filtroStatusAluno" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Filtrar por status</label>
                <select id="filtroStatusAluno"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">Todos</option>
                    <option value="nao_enviado">Não enviado</option>
                    <option value="visualizado">Visualizado</option>
                    <option value="enviado">Enviado</option>
                    <option value="corrigido">Corrigido</option>
                </select>
            </div>
        </div>
        <p id="filtroResumoTabela" class="text-xs text-gray-500 mt-2"></p>
    </div>
    <?php $enviosList = $enviosList ?? []; ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data envio</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data correção</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nota</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($enviosList)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">Nenhum aluno disponibilizado para esta proposta.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enviosList as $row): ?>
                    <tr class="hover:bg-gray-50 linha-envio-aluno"
                        data-aluno-nome="<?= htmlspecialchars(mb_strtolower((string)$row['nome'], 'UTF-8')) ?>"
                        data-status="<?= htmlspecialchars((string)$row['status']) ?>">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['nome']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                                $statusLabel = $row['status'] === 'corrigido'
                                    ? 'Corrigido'
                                    : ($row['status'] === 'enviado' ? 'Enviado' : ($row['status'] === 'visualizado' ? 'Visualizado' : 'Não enviado'));
                                $statusClass = $row['status'] === 'corrigido'
                                    ? 'bg-blue-100 text-blue-800'
                                    : ($row['status'] === 'enviado'
                                        ? 'bg-green-100 text-green-800'
                                        : ($row['status'] === 'visualizado' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800'));
                            ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $statusClass ?>">
                                <?= $statusLabel ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= $row['submitted_at'] ? date('d/m/Y H:i', strtotime($row['submitted_at'])) : '—' ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= !empty($row['corrected_at']) ? date('d/m/Y H:i', strtotime($row['corrected_at'])) : '—' ?></td>
                        <td class="px-6 py-4 text-sm font-semibold text-gray-800"><?= ($row['nota_final'] ?? '') !== '' ? htmlspecialchars((string)$row['nota_final']) : '—' ?></td>
                        <td class="px-6 py-4 text-sm font-medium">
                            <div class="flex items-center gap-3">
                            <?php if (!$isAdminView): ?>
                            <button type="button"
                                    class="btn-transcrever text-purple-600 hover:text-purple-900"
                                    title="Transcrever"
                                    data-student-id="<?= (int)$row['student_id'] ?>"
                                    data-student-name="<?= htmlspecialchars($row['nome']) ?>"
                                    data-has-submitted="<?= !empty($row['has_submitted']) ? '1' : '0' ?>">
                                <span class="sr-only">Transcrever</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h10"/>
                                </svg>
                            </button>
                            <?php endif; ?>
                            <?php if ($row['submission_id'] && !$isAdminView): ?>
                            <a href="<?= URL ?>/professor/redacao-configuravel/propostas/<?= (int)$proposal['id'] ?>/envios/<?= (int)$row['submission_id'] ?>/corrigir"
                               class="text-indigo-600 hover:text-indigo-900"
                               title="Visualizar">
                                <span class="sr-only">Visualizar</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($hasColetanea): ?>
<div id="modalColetanea" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" id="modalColetaneaBackdrop"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-4xl w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold text-gray-900">Coletânea da proposta</h3>
                <button type="button" id="modalColetaneaFecharTop" class="text-gray-500 hover:text-gray-700">✕</button>
            </div>

            <div class="space-y-5 max-h-[70vh] overflow-y-auto pr-1">
                <?php if (!empty($proposal['theme'])): ?>
                <section>
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Tema da Redação</h4>
                    <div class="text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($proposal['theme']) ?></div>
                </section>
                <?php endif; ?>

                <?php if (!empty($proposal['contexto'])): ?>
                <section>
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Contexto / Descrição</h4>
                    <div class="text-gray-800 prose prose-sm max-w-none"><?= (strpos($proposal['contexto'], '<') !== false ? $proposal['contexto'] : nl2br(htmlspecialchars($proposal['contexto']))) ?></div>
                </section>
                <?php endif; ?>

                <?php if (!empty($repertoriosList)): ?>
                <section>
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Repertório</h4>
                    <div class="space-y-3">
                        <?php foreach ($repertoriosList as $txt): ?>
                        <div class="text-gray-800 border-l-2 border-purple-200 pl-3 prose prose-sm max-w-none"><?= (strpos($txt, '<') !== false ? $txt : nl2br(htmlspecialchars($txt))) ?></div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php elseif (!empty($proposal['repertoire'])): ?>
                <section>
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Repertório / Instruções</h4>
                    <div class="text-gray-800 prose prose-sm max-w-none"><?= $proposal['repertoire'] ?></div>
                </section>
                <?php endif; ?>

                <?php if (!empty($proposal['tema_pronto_file'])): ?>
                <section>
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Arquivo base (PDF/Imagem)</h4>
                    <a href="<?= htmlspecialchars((string)$proposal['tema_pronto_file']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-purple-700 hover:text-purple-900 underline">
                        Abrir arquivo da proposta
                    </a>
                </section>
                <?php endif; ?>

                <?php if (!empty($imagesList)): ?>
                <section>
                    <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Imagens utilizadas</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach ($imagesList as $img): ?>
                            <?php $imgUrl = is_array($img) ? ($img['url'] ?? $img['src'] ?? '') : (string)$img; ?>
                            <?php if ($imgUrl === '') continue; ?>
                            <a href="<?= htmlspecialchars($imgUrl) ?>" target="_blank" rel="noopener noreferrer" class="block border rounded-lg overflow-hidden hover:shadow">
                                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Imagem da coletânea" class="w-full h-44 object-cover bg-gray-100">
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <div class="mt-5 text-right">
                <button type="button" id="modalColetaneaFechar" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Fechar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($canManagePermissions): ?>
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6 mt-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Professores com acesso a esta redação</h3>
    <form id="formPermissaoAdd" class="flex flex-wrap gap-2 items-end mb-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <div class="min-w-[260px]">
            <label class="block text-sm text-gray-700 mb-1">Professor</label>
            <select name="professor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">Selecione</option>
                <?php foreach (($allProfessors ?? []) as $p): ?>
                    <?php if ((int)$p['id'] === (int)$proposal['teacher_id']) continue; ?>
                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nome']) ?> (<?= htmlspecialchars($p['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Conceder acesso</button>
    </form>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs uppercase text-gray-500">Professor</th>
                    <th class="px-4 py-2 text-left text-xs uppercase text-gray-500">Email</th>
                    <th class="px-4 py-2 text-left text-xs uppercase text-gray-500">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach (($allowedProfessors ?? []) as $ap): ?>
                <tr>
                    <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($ap['nome']) ?></td>
                    <td class="px-4 py-2 text-sm text-gray-600"><?= htmlspecialchars($ap['email']) ?></td>
                    <td class="px-4 py-2 text-sm">
                        <button type="button" class="btnPermissaoRemover text-red-600 hover:text-red-800" data-professor-id="<?= (int)$ap['id'] ?>">Remover</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($studentsWithAccess)): ?>
<!-- Modal Transcrever (um aluno) -->
<div id="modalTranscrever" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" id="modalTranscreverBackdrop"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 id="modalTranscreverTitle" class="text-lg font-semibold text-gray-900 mb-4">Transcrever redação</h3>
            <form id="formModalTranscrever" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="student_id" id="modalTranscreverStudentId" value="">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Imagem da redação</label>
                    <input type="file" name="imagem" accept="image/jpeg,image/png,image/gif,image/webp" required class="block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-purple-50 file:text-purple-700">
                </div>
                <div id="wrapSenhaProfessor" class="mb-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirme sua senha para sobrescrever envio existente</label>
                    <input type="password" name="professor_password" id="professorPassword" class="w-full border border-gray-300 rounded-lg px-3 py-2" autocomplete="current-password">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="modalTranscreverFechar" class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit" class="px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Enviar e transcrever</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Enviar em bloco -->
<div id="modalLote" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" id="modalLoteBackdrop"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Enviar redações em bloco</h3>
            <p class="text-sm text-gray-600 mb-4">Envie várias imagens. O sistema identifica o aluno pelo <strong>nome do arquivo</strong> (ex.: VICTOR MARCIO DA COSTA BARBOSA.jpeg) ou pelo <strong>nome no texto da redação</strong>. Aceita nomes iguais ou parecidos (mesmo sobrenome, abreviações).</p>
            <form id="formEnviarLote" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Imagens (várias)</label>
                    <input type="file" name="imagens[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required class="block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-purple-50 file:text-purple-700">
                </div>
                <div id="loteResultados" class="hidden mb-4 text-sm max-h-40 overflow-y-auto"></div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="modalLoteFechar" class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Fechar</button>
                    <button type="submit" id="btnSubmitLote" class="px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Enviar e processar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var proposalId = <?= (int)$proposal['id'] ?>;
    var baseUrl = '<?= URL ?>';
    var basePrefix = '<?= $basePrefix ?>';
    var filtroNome = document.getElementById('filtroNomeAluno');
    var filtroStatus = document.getElementById('filtroStatusAluno');
    var filtroResumo = document.getElementById('filtroResumoTabela');
    var linhasEnvio = Array.prototype.slice.call(document.querySelectorAll('.linha-envio-aluno'));

    function normalizeText(v) {
        return (v || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function aplicarFiltrosTabela() {
        if (!linhasEnvio.length) return;
        var nome = normalizeText(filtroNome ? filtroNome.value : '');
        var status = (filtroStatus ? filtroStatus.value : '').trim();
        var visiveis = 0;

        linhasEnvio.forEach(function(tr) {
            var nomeAluno = normalizeText(tr.getAttribute('data-aluno-nome') || '');
            var st = (tr.getAttribute('data-status') || '').trim();
            var okNome = !nome || nomeAluno.indexOf(nome) !== -1;
            var okStatus = !status || st === status;
            var show = okNome && okStatus;
            tr.style.display = show ? '' : 'none';
            if (show) visiveis++;
        });

        if (filtroResumo) {
            filtroResumo.textContent = visiveis + ' aluno(s) exibido(s) de ' + linhasEnvio.length + '.';
        }
    }

    if (filtroNome) filtroNome.addEventListener('input', aplicarFiltrosTabela);
    if (filtroStatus) filtroStatus.addEventListener('change', aplicarFiltrosTabela);
    aplicarFiltrosTabela();

    <?php if ($hasColetanea): ?>
    var modalColetanea = document.getElementById('modalColetanea');
    var btnColetaneaAbrir = document.getElementById('btnColetaneaAbrir');
    function openModalColetanea() { if (modalColetanea) modalColetanea.classList.remove('hidden'); }
    function closeModalColetanea() { if (modalColetanea) modalColetanea.classList.add('hidden'); }
    if (btnColetaneaAbrir) btnColetaneaAbrir.addEventListener('click', openModalColetanea);
    var backdropCol = document.getElementById('modalColetaneaBackdrop');
    var fecharCol = document.getElementById('modalColetaneaFechar');
    var fecharColTop = document.getElementById('modalColetaneaFecharTop');
    if (backdropCol) backdropCol.addEventListener('click', closeModalColetanea);
    if (fecharCol) fecharCol.addEventListener('click', closeModalColetanea);
    if (fecharColTop) fecharColTop.addEventListener('click', closeModalColetanea);
    <?php endif; ?>

    var modalTranscrever = document.getElementById('modalTranscrever');
    var modalTranscreverTitle = document.getElementById('modalTranscreverTitle');
    var modalTranscreverStudentId = document.getElementById('modalTranscreverStudentId');
    var formModalTranscrever = document.getElementById('formModalTranscrever');

    function openModalTranscrever(studentId, studentName) {
        if (!modalTranscrever) return;
        modalTranscreverStudentId.value = studentId;
        modalTranscreverTitle.textContent = 'Transcrever redação – ' + (studentName || '');
        formModalTranscrever.querySelector('input[type="file"]').value = '';
        var wrapSenha = document.getElementById('wrapSenhaProfessor');
        var inputSenha = document.getElementById('professorPassword');
        if (wrapSenha && inputSenha) {
            wrapSenha.classList.add('hidden');
            inputSenha.value = '';
        }
        modalTranscrever.classList.remove('hidden');
    }
    function closeModalTranscrever() {
        if (modalTranscrever) modalTranscrever.classList.add('hidden');
    }
    document.getElementById('modalTranscreverBackdrop').addEventListener('click', closeModalTranscrever);
    document.getElementById('modalTranscreverFechar').addEventListener('click', closeModalTranscrever);

    document.querySelectorAll('.btn-transcrever').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var studentId = this.getAttribute('data-student-id');
            var studentName = this.getAttribute('data-student-name') || '';
            var hasSubmitted = this.getAttribute('data-has-submitted') === '1';
            openModalTranscrever(studentId, studentName);
            if (hasSubmitted) {
                var wrapSenha = document.getElementById('wrapSenhaProfessor');
                if (wrapSenha) wrapSenha.classList.remove('hidden');
            }
        });
    });

    if (formModalTranscrever) {
        formModalTranscrever.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = formModalTranscrever.querySelector('button[type="submit"]');
            var fd = new FormData(formModalTranscrever);
            btn.disabled = true;
            btn.textContent = 'Enviando...';
            fetch(baseUrl + '/professor/redacao-configuravel/propostas/' + proposalId + '/enviar-aluno', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) {
                        alert(d.message || 'Redação enviada.');
                        closeModalTranscrever();
                        window.location.reload();
                    } else {
                        alert(d.error || 'Erro ao enviar');
                        btn.disabled = false;
                        btn.textContent = 'Enviar e transcrever';
                    }
                })
                .catch(function() {
                    alert('Erro de conexão');
                    btn.disabled = false;
                    btn.textContent = 'Enviar e transcrever';
                });
        });
    }

    var modalLote = document.getElementById('modalLote');
    var formEnviarLote = document.getElementById('formEnviarLote');
    var loteResultados = document.getElementById('loteResultados');

    document.getElementById('btnEnviarLote').addEventListener('click', function() {
        if (modalLote) {
            formEnviarLote.reset();
            loteResultados.classList.add('hidden');
            modalLote.classList.remove('hidden');
        }
    });
    document.getElementById('modalLoteBackdrop').addEventListener('click', function() { modalLote.classList.add('hidden'); });
    document.getElementById('modalLoteFechar').addEventListener('click', function() { modalLote.classList.add('hidden'); });

    formEnviarLote.addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = document.getElementById('btnSubmitLote');
        var fd = new FormData(formEnviarLote);
        btn.disabled = true;
        btn.textContent = 'Processando...';
        loteResultados.classList.add('hidden');
        fetch(baseUrl + '/professor/redacao-configuravel/propostas/' + proposalId + '/enviar-lote', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                btn.textContent = 'Enviar e processar';
                if (d.success && d.results && d.results.length) {
                    var html = '';
                    d.results.forEach(function(r) {
                        if (r.ok) {
                            html += '<p class="text-green-700">✓ ' + (r.file || '') + ' → ' + (r.student_name || '') + '</p>';
                        } else {
                            html += '<p class="text-amber-700">✗ ' + (r.file || '') + ': ' + (r.reason || '') + '</p>';
                        }
                    });
                    loteResultados.innerHTML = html;
                    loteResultados.classList.remove('hidden');
                    alert(d.message || 'Lote processado. Veja o resumo abaixo.');
                    var okCount = d.results.filter(function(x) { return x.ok; }).length;
                    if (okCount > 0) window.location.reload();
                } else if (d.error) {
                    alert(d.error);
                } else {
                    alert(d.message || 'Nenhum arquivo processado.');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = 'Enviar e processar';
                alert('Erro de conexão');
            });
    });
})();

<?php if ($canManagePermissions): ?>
(function() {
    var form = document.getElementById('formPermissaoAdd');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(form);
        fetch('<?= URL . $permissionBasePrefix ?>/<?= (int)$proposal['id'] ?>/permissoes/adicionar', { method: 'POST', body: fd })
            .then(function(r){
                return r.text().then(function(text){
                    try {
                        return text ? JSON.parse(text) : {};
                    } catch (e) {
                        return { error: 'Resposta inválida do servidor (HTTP ' + r.status + '). ' + (text || '').substring(0, 200) };
                    }
                });
            })
            .then(function(d){ if (d.success) window.location.reload(); else alert(d.error || 'Erro ao conceder acesso'); })
            .catch(function(){ alert('Erro de conexão'); });
    });
    document.querySelectorAll('.btnPermissaoRemover').forEach(function(btn){
        btn.addEventListener('click', function() {
            if (!confirm('Remover acesso deste professor?')) return;
            var fd = new FormData();
            fd.append('_token', '<?= htmlspecialchars($csrf_token ?? '') ?>');
            fd.append('professor_id', this.getAttribute('data-professor-id'));
            fetch('<?= URL . $permissionBasePrefix ?>/<?= (int)$proposal['id'] ?>/permissoes/remover', { method: 'POST', body: fd })
                .then(function(r){
                    return r.text().then(function(text){
                        try {
                            return text ? JSON.parse(text) : {};
                        } catch (e) {
                            return { error: 'Resposta inválida do servidor (HTTP ' + r.status + '). ' + (text || '').substring(0, 200) };
                        }
                    });
                })
                .then(function(d){ if (d.success) window.location.reload(); else alert(d.error || 'Erro ao remover acesso'); })
                .catch(function(){ alert('Erro de conexão'); });
        });
    });
})();
<?php endif; ?>
</script>
<?php endif; ?>
