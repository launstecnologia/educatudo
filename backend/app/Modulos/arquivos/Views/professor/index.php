<?php
$pastas = $pastas ?? [];
$pastaAtual = $pasta_atual ?? null;
$pastaAtualId = $pasta_atual_id ?? null;
$baseUrl = URL . '/professor/arquivos';
?>

<div class="mb-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Módulo de Arquivos 📁</h1>
            <p class="text-gray-600 mt-1">Disponibilize arquivos para suas turmas. O aluno poderá baixar os anexos.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="btn-nova-pasta" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                Nova pasta
            </button>
            <a href="<?= $baseUrl ?>/criar<?= $pastaAtualId ? '?pasta_id=' . $pastaAtualId : '' ?>" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Novo arquivo
            </a>
        </div>
    </div>
</div>

<?php if (!empty($_SESSION['flash_message'] ?? '')): ?>
    <div class="mb-4 p-4 rounded-lg <?= ($_SESSION['flash_type'] ?? '') === 'error' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
        <?= htmlspecialchars($_SESSION['flash_message']) ?>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<!-- Breadcrumb de navegação de pasta -->
<?php if ($pastaAtual): ?>
<nav class="mb-4 flex items-center gap-2 text-sm">
    <a href="<?= $baseUrl ?>" class="text-indigo-600 hover:underline flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
        Arquivos
    </a>
    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="font-medium text-gray-700"><?= htmlspecialchars($pastaAtual['nome']) ?></span>
</nav>
<?php endif; ?>

<!-- Grid de Pastas (visível apenas na raiz) -->
<?php if (!$pastaAtual && !empty($pastas)): ?>
<div class="mb-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Pastas</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3" id="grade-pastas">
        <?php foreach ($pastas as $pasta): ?>
        <div class="group relative bg-white rounded-xl border border-gray-200 hover:border-indigo-300 hover:shadow-sm transition-all cursor-pointer" data-pasta-id="<?= (int)$pasta['id'] ?>" data-pasta-nome="<?= htmlspecialchars($pasta['nome']) ?>">
            <a href="<?= $baseUrl ?>?pasta_id=<?= (int)$pasta['id'] ?>" class="flex flex-col items-center p-4 gap-2">
                <svg class="w-10 h-10" fill="<?= htmlspecialchars($pasta['cor']) ?>" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.9-2-2-2h-8l-2-2z"/></svg>
                <span class="text-xs font-medium text-gray-700 text-center leading-tight line-clamp-2"><?= htmlspecialchars($pasta['nome']) ?></span>
                <span class="text-xs text-gray-400"><?= (int)$pasta['total_arquivos'] ?> arquivo<?= (int)$pasta['total_arquivos'] !== 1 ? 's' : '' ?></span>
            </a>
            <div class="absolute top-2 right-2 hidden group-hover:flex items-center gap-1">
                <button type="button" class="btn-renomear-pasta p-1 rounded text-gray-400 hover:text-indigo-600 hover:bg-indigo-50" data-id="<?= (int)$pasta['id'] ?>" data-nome="<?= htmlspecialchars($pasta['nome']) ?>" title="Renomear">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button type="button" class="btn-excluir-pasta p-1 rounded text-gray-400 hover:text-red-600 hover:bg-red-50" data-id="<?= (int)$pasta['id'] ?>" data-nome="<?= htmlspecialchars($pasta['nome']) ?>" title="Excluir pasta">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Publicações -->
<div class="bg-white rounded-xl shadow-lg">
    <div class="p-6 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-900">
            <?= $pastaAtual ? 'Arquivos em ' . htmlspecialchars($pastaAtual['nome']) : 'Publicações' ?>
        </h2>
        <?php if ($pastaAtual): ?>
        <a href="<?= $baseUrl ?>" class="text-sm text-indigo-600 hover:underline">Ver todas</a>
        <?php endif; ?>
    </div>
    <div class="p-6">
        <?php if (empty($lista)): ?>
            <div class="text-center py-12">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                <p class="text-gray-500 text-lg mb-2">Nenhuma publicação<?= $pastaAtual ? ' nesta pasta' : '' ?></p>
                <p class="text-sm text-gray-400 mb-4">Crie uma publicação com turma, disciplina, título e anexos.</p>
                <a href="<?= $baseUrl ?>/criar<?= $pastaAtualId ? '?pasta_id=' . $pastaAtualId : '' ?>" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-lg">Criar publicação</a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Disciplina</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Anexos</th>
                            <?php if (!$pastaAtual && !empty($pastas)): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pasta</th>
                            <?php endif; ?>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($lista as $row): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?= htmlspecialchars($row['titulo']) ?>
                                    <?php if (!empty($row['recuperacao'])): ?>
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Recuperação</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?php if (!empty($row['aluno_nome'])): ?>
                                        <span class="text-indigo-600 font-medium">Aluno: <?= htmlspecialchars($row['aluno_nome']) ?></span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($row['turma_nome'] ?? '') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($row['materia_nome'] ?? '') ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= (int)($row['total_anexos'] ?? 0) ?></td>
                                <?php if (!$pastaAtual && !empty($pastas)): ?>
                                <td class="px-4 py-3 text-sm">
                                    <select class="select-mover-pasta text-xs border border-gray-200 rounded px-2 py-1 text-gray-600 focus:outline-none focus:border-indigo-400" data-arquivo-id="<?= (int)$row['id'] ?>">
                                        <option value="">— sem pasta —</option>
                                        <?php foreach ($pastas as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>" <?= (int)($row['pasta_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <?php endif; ?>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2 flex-wrap">
                                        <button type="button" class="btn-visualizar-arquivo inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 border border-gray-300" data-id="<?= (int)$row['id'] ?>" title="Ver como o aluno">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Visualizar
                                        </button>
                                        <a href="<?= URL ?>/professor/arquivos/editar/<?= (int)$row['id'] ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            Editar
                                        </a>
                                        <form method="post" action="<?= URL ?>/professor/arquivos/excluir/<?= (int)$row['id'] ?>" class="inline" onsubmit="return confirm('Excluir esta publicação e todos os anexos?');">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <button type="submit" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 border border-red-200">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Excluir
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: nova/renomear pasta -->
<div id="modal-pasta" class="fixed inset-0 z-50 hidden" aria-modal="true">
    <div class="fixed inset-0 bg-black/50" id="modal-pasta-backdrop"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
                <h3 id="modal-pasta-titulo" class="text-base font-semibold text-gray-900">Nova pasta</h3>
                <button type="button" id="modal-pasta-fechar" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome da pasta</label>
                    <input type="text" id="input-pasta-nome" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Ex: Materiais de Biologia" maxlength="255">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cor</label>
                    <div class="flex items-center gap-2 flex-wrap">
                        <?php foreach (['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#64748b'] as $cor): ?>
                        <button type="button" class="btn-cor-pasta w-7 h-7 rounded-full border-2 border-transparent hover:scale-110 transition-transform" data-cor="<?= $cor ?>" style="background:<?= $cor ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="input-pasta-cor" value="#6366f1">
                </div>
            </div>
            <div class="px-5 py-4 border-t border-gray-200 flex justify-end gap-3">
                <button type="button" id="modal-pasta-cancelar" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                <button type="button" id="modal-pasta-salvar" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: visualização do aluno -->
<div id="modal-preview-arquivo" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="fixed inset-0 bg-black/50" id="modal-preview-backdrop"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Como o aluno visualiza</h3>
                <button type="button" id="modal-preview-fechar" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700" aria-label="Fechar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-hidden min-h-0">
                <iframe id="modal-preview-iframe" class="w-full h-full min-h-[400px] border-0" title="Preview da publicação"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var CSRF = '<?= htmlspecialchars($csrf_token) ?>';
    var BASE = '<?= rtrim(URL ?? '', "/") ?>';
    var modoEdicao = null; // null = criar, number = id da pasta sendo renomeada

    // ---- Modal preview ----
    var modal = document.getElementById('modal-preview-arquivo');
    var iframe = document.getElementById('modal-preview-iframe');
    document.querySelectorAll('.btn-visualizar-arquivo').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            if (id) {
                iframe.src = BASE + '/professor/arquivos/preview/' + id + '?iframe=1';
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        });
    });
    function fecharPreview() { modal.classList.add('hidden'); iframe.src = 'about:blank'; document.body.style.overflow = ''; }
    var backdropP = document.getElementById('modal-preview-backdrop');
    var btnFecharP = document.getElementById('modal-preview-fechar');
    if (backdropP) backdropP.addEventListener('click', fecharPreview);
    if (btnFecharP) btnFecharP.addEventListener('click', fecharPreview);

    // ---- Modal pasta ----
    var modalPasta = document.getElementById('modal-pasta');
    var inputNome = document.getElementById('input-pasta-nome');
    var inputCor = document.getElementById('input-pasta-cor');
    var titulo = document.getElementById('modal-pasta-titulo');

    function abrirModalPasta(modo, id, nomeAtual) {
        modoEdicao = modo === 'criar' ? null : id;
        titulo.textContent = modo === 'criar' ? 'Nova pasta' : 'Renomear pasta';
        inputNome.value = nomeAtual || '';
        modalPasta.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(function() { inputNome.focus(); }, 50);
    }
    function fecharModalPasta() { modalPasta.classList.add('hidden'); document.body.style.overflow = ''; modoEdicao = null; }

    var btnNovaPasta = document.getElementById('btn-nova-pasta');
    if (btnNovaPasta) btnNovaPasta.addEventListener('click', function() { abrirModalPasta('criar'); });

    document.querySelectorAll('.btn-renomear-pasta').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            abrirModalPasta('renomear', this.getAttribute('data-id'), this.getAttribute('data-nome'));
        });
    });

    var backdropPasta = document.getElementById('modal-pasta-backdrop');
    var btnCancelar = document.getElementById('modal-pasta-cancelar');
    var btnFecharPasta = document.getElementById('modal-pasta-fechar');
    if (backdropPasta) backdropPasta.addEventListener('click', fecharModalPasta);
    if (btnCancelar) btnCancelar.addEventListener('click', fecharModalPasta);
    if (btnFecharPasta) btnFecharPasta.addEventListener('click', fecharModalPasta);

    // Seleção de cor
    document.querySelectorAll('.btn-cor-pasta').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.btn-cor-pasta').forEach(function(b) { b.style.borderColor = 'transparent'; });
            this.style.borderColor = '#1e40af';
            inputCor.value = this.getAttribute('data-cor');
        });
    });
    // Marca a primeira cor como selecionada por padrão
    var primeiraCor = document.querySelector('.btn-cor-pasta');
    if (primeiraCor) primeiraCor.style.borderColor = '#1e40af';

    // Salvar pasta
    var btnSalvar = document.getElementById('modal-pasta-salvar');
    if (btnSalvar) btnSalvar.addEventListener('click', function() {
        var nome = inputNome.value.trim();
        if (!nome) { inputNome.focus(); return; }
        var url = modoEdicao ? BASE + '/professor/arquivos/pasta/renomear' : BASE + '/professor/arquivos/pasta/criar';
        var body = new FormData();
        body.append('_token', CSRF);
        body.append('nome', nome);
        if (!modoEdicao) body.append('cor', inputCor.value);
        if (modoEdicao) body.append('id', modoEdicao);
        btnSalvar.disabled = true;
        fetch(url, { method: 'POST', body: body })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) { location.reload(); }
                else { alert(data.error || 'Erro ao salvar pasta.'); btnSalvar.disabled = false; }
            })
            .catch(function() { alert('Erro ao salvar pasta.'); btnSalvar.disabled = false; });
    });

    inputNome.addEventListener('keydown', function(e) { if (e.key === 'Enter') btnSalvar && btnSalvar.click(); });

    // Excluir pasta
    document.querySelectorAll('.btn-excluir-pasta').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            var nome = this.getAttribute('data-nome');
            if (!confirm('Excluir a pasta "' + nome + '"?\nOs arquivos dentro dela não serão excluídos, apenas removidos da pasta.')) return;
            var id = this.getAttribute('data-id');
            var body = new FormData();
            body.append('_token', CSRF);
            body.append('id', id);
            fetch(BASE + '/professor/arquivos/pasta/excluir', { method: 'POST', body: body })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) location.reload();
                    else alert(data.error || 'Erro ao excluir pasta.');
                });
        });
    });

    // Mover arquivo para pasta via select
    document.querySelectorAll('.select-mover-pasta').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var arquivoId = this.getAttribute('data-arquivo-id');
            var pastaId = this.value;
            var body = new FormData();
            body.append('_token', CSRF);
            body.append('arquivo_id', arquivoId);
            body.append('pasta_id', pastaId);
            fetch(BASE + '/professor/arquivos/pasta/mover', { method: 'POST', body: body })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) { alert(data.error || 'Erro ao mover arquivo.'); location.reload(); }
                });
        });
    });
})();
</script>
