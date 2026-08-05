<?php
$filtros       = $filtros ?? [];
$pag           = $pagination ?? ['page' => 1, 'per_page' => 15, 'total' => 0, 'total_pages' => 1];
$baseUrl       = URL . '/admin/arquivos';
$pastas        = $pastas ?? [];
$todasPastas   = $todas_pastas ?? [];
$pastaAtual    = $pasta_atual ?? null;
$pastaAtualId  = $pasta_atual_id ?? null;
$breadcrumb    = $breadcrumb ?? [];
$hasParentCol  = $has_parent_col ?? false;

$temFiltroAtivo = !empty($filtros['materia_id']) || !empty($filtros['professor_id'])
                || !empty($filtros['turma_id'])  || !empty($filtros['assunto']);

function adminArquivoDescricaoVazia(?string $html): bool {
    return trim(strip_tags(html_entity_decode((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) === '';
}
?>
<?php require __DIR__ . '/../../../../Views/layouts/components/admin_filter_styles.php'; ?>

<div class="max-w-6xl mx-auto space-y-4">

    <?php if (!empty($flash['message'])): ?>
    <div class="rounded-lg px-4 py-3 <?= ($flash['type'] ?? 'info') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Arquivos da Escola</h2>
            <p class="text-sm text-gray-600 mt-1">Gerencie os arquivos publicados para as turmas.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="btn-nova-pasta-admin"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                Nova pasta
            </button>
            <a href="<?= URL ?>/admin/arquivos/criar<?= $pastaAtualId ? '?pasta_id=' . $pastaAtualId : '' ?>"
               class="btn-primary-custom inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:opacity-90">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Novo arquivo
            </a>
        </div>
    </div>

    <!-- Breadcrumb -->
    <?php if ($pastaAtual || !empty($breadcrumb)): ?>
    <nav class="flex items-center gap-2 text-sm flex-wrap">
        <a href="<?= $baseUrl ?>" class="text-indigo-600 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            Arquivos
        </a>
        <?php foreach ($breadcrumb as $anc): ?>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="<?= $baseUrl ?>?pasta_id=<?= (int)$anc['id'] ?>" class="text-indigo-600 hover:underline"><?= htmlspecialchars($anc['nome']) ?></a>
        <?php endforeach; ?>
        <?php if ($pastaAtual): ?>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="font-medium text-gray-700"><?= htmlspecialchars($pastaAtual['nome']) ?></span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <!-- Filtros (recolhidos por padrão na raiz, expandidos quando filtro ativo) -->
    <?php if ($temFiltroAtivo): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
    <?php else: ?>
    <details class="bg-white rounded-xl border border-gray-200">
        <summary class="px-4 py-3 text-sm font-medium text-gray-600 cursor-pointer select-none hover:bg-gray-50 rounded-xl">Filtros de busca</summary>
        <div class="px-4 pb-4 pt-2">
    <?php endif; ?>

        <form method="get" action="<?= $baseUrl ?>" class="admin-filter-grid">
            <?php if ($pastaAtualId): ?>
                <input type="hidden" name="pasta_id" value="<?= (int)$pastaAtualId ?>">
            <?php endif; ?>
            <div class="admin-filter-field">
                <label for="filtro-materia">Matéria</label>
                <select id="filtro-materia" name="materia_id" class="admin-filter-control admin-filter-select">
                    <option value="">Todas</option>
                    <?php foreach (($materias ?? []) as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= ((int)($filtros['materia_id'] ?? 0) === (int)$m['id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtro-professor">Professor</label>
                <select id="filtro-professor" name="professor_id" class="admin-filter-control admin-filter-select">
                    <option value="">Todos</option>
                    <?php foreach (($professores ?? []) as $prof): ?>
                        <option value="<?= (int)$prof['id'] ?>" <?= ((int)($filtros['professor_id'] ?? 0) === (int)$prof['id']) ? 'selected' : '' ?>><?= htmlspecialchars($prof['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtro-turma">Turma</label>
                <select id="filtro-turma" name="turma_id" class="admin-filter-control admin-filter-select">
                    <option value="">Todas</option>
                    <?php foreach (($turmas ?? []) as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= ((int)($filtros['turma_id'] ?? 0) === (int)$t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtro-assunto">Assunto</label>
                <input type="text" id="filtro-assunto" name="assunto" value="<?= htmlspecialchars((string)($filtros['assunto'] ?? '')) ?>" placeholder="Título ou descrição..." class="admin-filter-control">
            </div>
            <div class="admin-filter-field">
                <label for="filtro-data-de">Data de</label>
                <input type="date" id="filtro-data-de" name="data_de" value="<?= htmlspecialchars((string)($filtros['data_de'] ?? '')) ?>" class="admin-filter-control admin-filter-date">
            </div>
            <div class="admin-filter-field">
                <label for="filtro-data-ate">Data até</label>
                <input type="date" id="filtro-data-ate" name="data_ate" value="<?= htmlspecialchars((string)($filtros['data_ate'] ?? '')) ?>" class="admin-filter-control admin-filter-date">
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="admin-filter-btn admin-filter-btn-primary">Filtrar</button>
                <a href="<?= $baseUrl ?><?= $pastaAtualId ? '?pasta_id=' . $pastaAtualId : '' ?>" class="admin-filter-btn admin-filter-btn-secondary">Limpar</a>
            </div>
        </form>

    <?php if ($temFiltroAtivo): ?>
    </div>
    <?php else: ?>
        </div>
    </details>
    <?php endif; ?>

    <!-- Lista unificada: subpastas + arquivos -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        <!-- Cabeçalho da tabela -->
        <div class="grid grid-cols-[auto_1fr_auto] items-center px-4 py-2 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
            <span class="w-8"></span>
            <span class="pl-3">Nome</span>
            <span class="pr-1 text-right">Ações</span>
        </div>

        <?php $hasContent = !empty($pastas) || !empty($items); ?>

        <?php if (!$hasContent): ?>
            <p class="text-gray-500 text-center py-10">
                Nenhum item encontrado<?= $temFiltroAtivo ? ' com os filtros aplicados.' : ' ainda.' ?>
            </p>
        <?php else: ?>

            <!-- Subpastas -->
            <?php if (!$temFiltroAtivo && !empty($pastas)): ?>
                <?php foreach ($pastas as $pasta): ?>
                <div class="group grid grid-cols-[auto_1fr_auto] items-center px-4 py-3 border-b border-gray-100 hover:bg-indigo-50 transition-colors">
                    <!-- Ícone -->
                    <div class="w-8 flex items-center justify-center">
                        <a href="<?= $baseUrl ?>?pasta_id=<?= (int)$pasta['id'] ?>">
                            <svg class="w-6 h-6" fill="<?= htmlspecialchars((string)($pasta['cor'] ?? '#6366f1')) ?>" viewBox="0 0 24 24">
                                <path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.9-2-2-2h-8l-2-2z"/>
                            </svg>
                        </a>
                    </div>
                    <!-- Nome + meta -->
                    <a href="<?= $baseUrl ?>?pasta_id=<?= (int)$pasta['id'] ?>" class="pl-3 min-w-0 block">
                        <span class="font-medium text-gray-800 group-hover:text-indigo-700"><?= htmlspecialchars((string)($pasta['nome'] ?? '')) ?></span>
                        <span class="text-xs text-gray-400 ml-2">
                            <?= (int)($pasta['total_arquivos'] ?? 0) ?> arquivo(s)
                            <?php if ((int)($pasta['total_subpastas'] ?? 0) > 0): ?>
                                · <?= (int)$pasta['total_subpastas'] ?> subpasta(s)
                            <?php endif; ?>
                        </span>
                    </a>
                    <!-- Ações pasta -->
                    <div class="pl-4 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button type="button" class="btn-renomear-pasta-admin p-1.5 rounded text-gray-400 hover:text-indigo-600 hover:bg-indigo-50"
                                data-id="<?= (int)$pasta['id'] ?>" data-nome="<?= htmlspecialchars((string)($pasta['nome'] ?? '')) ?>" title="Renomear">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <button type="button" class="btn-excluir-pasta-admin p-1.5 rounded text-gray-400 hover:text-red-600 hover:bg-red-50"
                                data-id="<?= (int)$pasta['id'] ?>" data-nome="<?= htmlspecialchars((string)($pasta['nome'] ?? '')) ?>" title="Excluir">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                        <a href="<?= $baseUrl ?>?pasta_id=<?= (int)$pasta['id'] ?>" class="p-1.5 rounded text-gray-400 hover:text-indigo-600 hover:bg-indigo-50" title="Abrir">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Arquivos -->
            <?php foreach ($items as $item): ?>
            <div class="group grid grid-cols-[auto_1fr_auto] items-start px-4 py-3 border-b border-gray-100 hover:bg-gray-50 transition-colors">
                <!-- Ícone -->
                <div class="w-8 flex items-center justify-center pt-0.5">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <!-- Detalhes -->
                <div class="pl-3 min-w-0">
                    <p class="font-medium text-gray-900">
                        <?= htmlspecialchars((string)($item['titulo'] ?? '')) ?>
                        <?php if (!empty($item['recuperacao'])): ?>
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Recuperação</span>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($item['descricao']) && !adminArquivoDescricaoVazia($item['descricao'])): ?>
                        <div class="text-xs text-gray-500 mt-0.5 line-clamp-1"><?= strip_tags((string)$item['descricao']) ?></div>
                    <?php endif; ?>
                    <div class="flex flex-wrap gap-x-2 text-xs text-gray-400 mt-0.5">
                        <?php if (!empty($item['created_at'])): ?><span><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></span><?php endif; ?>
                        <?php if (!empty($item['tamanho'])): ?><span>· <?= number_format((int)$item['tamanho'] / 1024, 1, ',', '.') ?> KB</span><?php endif; ?>
                        <?php if (!empty($item['nome_original'])): ?><span class="truncate max-w-[180px]" title="<?= htmlspecialchars((string)$item['nome_original']) ?>">· <?= htmlspecialchars((string)$item['nome_original']) ?></span><?php endif; ?>
                        <?php if (!empty($item['materia_nome'])): ?><span>· <?= htmlspecialchars($item['materia_nome']) ?></span><?php endif; ?>
                        <?php if (!empty($item['professor_nome'])): ?><span>· <?= htmlspecialchars($item['professor_nome']) ?></span><?php endif; ?>
                        <?php if (!empty($item['turmas_nomes'])): ?><span>· <?= htmlspecialchars((string)$item['turmas_nomes']) ?></span><?php endif; ?>
                    </div>
                </div>
                <!-- Ações arquivo -->
                <div class="pl-4 flex items-center gap-1 flex-wrap justify-end shrink-0">
                    <?php if (!empty($todasPastas)): ?>
                    <select onchange="adminMoverArquivo(<?= (int)$item['id'] ?>, this.value, this)"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 text-gray-500 bg-white hover:border-gray-400 focus:outline-none opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Mover para pasta">
                        <option value="">📁 Mover...</option>
                        <?php if ((int)($item['pasta_id'] ?? 0) !== 0): ?>
                            <option value="0">— Sem pasta</option>
                        <?php endif; ?>
                        <?php foreach ($todasPastas as $p): ?>
                            <?php if ((int)$p['id'] !== (int)($item['pasta_id'] ?? 0)): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <?php if (!empty($item['anexo_id'])): ?>
                    <a href="<?= URL ?>/admin/arquivos/baixar/<?= (int)$item['anexo_id'] ?>"
                       class="px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100">Baixar</a>
                    <?php endif; ?>
                    <a href="<?= URL ?>/admin/arquivos/editar?id=<?= (int)$item['id'] ?>"
                       class="px-3 py-1.5 text-sm font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100">Editar</a>
                    <form action="<?= URL ?>/admin/arquivos/excluir" method="POST" onsubmit="return confirm('Deseja remover este arquivo?');">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="px-2 py-1.5 text-sm font-medium text-red-600 hover:text-red-800">Excluir</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <!-- Paginação -->
        <?php if (($pag['total_pages'] ?? 1) > 1):
            $page = (int)($pag['page'] ?? 1);
            $totalPages = (int)($pag['total_pages'] ?? 1);
            $total = (int)($pag['total'] ?? 0);
            $qp = array_filter([
                'pasta_id'    => $pastaAtualId,
                'materia_id'  => $filtros['materia_id'] ?? '',
                'professor_id'=> $filtros['professor_id'] ?? '',
                'turma_id'    => $filtros['turma_id'] ?? '',
                'assunto'     => $filtros['assunto'] ?? '',
                'data_de'     => $filtros['data_de'] ?? '',
                'data_ate'    => $filtros['data_ate'] ?? '',
            ]);
            $bq = $qp ? '?' . http_build_query($qp) : '';
            $sep = $bq === '' ? '?' : '&';
        ?>
        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
            <p class="text-sm text-gray-600">
                <?= min(($page-1)*($pag['per_page']??15)+1,$total) ?>–<?= min($page*($pag['per_page']??15),$total) ?> de <?= $total ?> arquivo(s)
            </p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="<?= $baseUrl.$bq.$sep ?>page=<?= $page-1 ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-white">Anterior</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= $baseUrl.$bq.$sep ?>page=<?= $page+1 ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-white">Próxima</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal: nova/renomear pasta -->
<div id="modal-pasta-admin" class="fixed inset-0 z-50 hidden" aria-modal="true">
    <div class="fixed inset-0 bg-black/50" id="modal-pasta-admin-backdrop"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
                <h3 id="modal-pasta-admin-titulo" class="text-base font-semibold text-gray-900">Nova pasta</h3>
                <button type="button" id="modal-pasta-admin-fechar" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome da pasta</label>
                    <input type="text" id="input-pasta-admin-nome" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Ex: Documentos Oficiais" maxlength="255">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cor</label>
                    <div class="flex items-center gap-2 flex-wrap">
                        <?php foreach (['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#64748b'] as $cor): ?>
                        <button type="button" class="btn-cor-pasta-admin w-7 h-7 rounded-full border-2 border-transparent hover:scale-110 transition-transform" data-cor="<?= $cor ?>" style="background:<?= $cor ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="input-pasta-admin-cor" value="#6366f1">
                </div>
            </div>
            <div class="px-5 py-4 border-t border-gray-200 flex justify-end gap-3">
                <button type="button" id="modal-pasta-admin-cancelar" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                <button type="button" id="modal-pasta-admin-salvar" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var CSRF    = '<?= htmlspecialchars($csrf_token ?? '') ?>';
    var BASE    = '<?= rtrim(URL ?? '', "/") ?>';
    var PARENT  = <?= json_encode($pastaAtualId) ?>;
    var modoEdicao = null;

    var modalPasta = document.getElementById('modal-pasta-admin');
    var inputNome  = document.getElementById('input-pasta-admin-nome');
    var inputCor   = document.getElementById('input-pasta-admin-cor');
    var tituloModal= document.getElementById('modal-pasta-admin-titulo');

    function abrirModal(modo, id, nomeAtual) {
        modoEdicao = modo === 'criar' ? null : id;
        tituloModal.textContent = modo === 'criar' ? (PARENT ? 'Nova subpasta' : 'Nova pasta') : 'Renomear pasta';
        inputNome.value = nomeAtual || '';
        modalPasta.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(function() { inputNome.focus(); }, 50);
    }
    function fecharModal() { modalPasta.classList.add('hidden'); document.body.style.overflow = ''; modoEdicao = null; }

    var btnNova = document.getElementById('btn-nova-pasta-admin');
    if (btnNova) btnNova.addEventListener('click', function() { abrirModal('criar'); });

    document.querySelectorAll('.btn-renomear-pasta-admin').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); e.preventDefault();
            abrirModal('renomear', this.getAttribute('data-id'), this.getAttribute('data-nome'));
        });
    });

    ['modal-pasta-admin-backdrop','modal-pasta-admin-cancelar','modal-pasta-admin-fechar'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', fecharModal);
    });

    document.querySelectorAll('.btn-cor-pasta-admin').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.btn-cor-pasta-admin').forEach(function(b) { b.style.borderColor = 'transparent'; });
            this.style.borderColor = '#1e40af';
            inputCor.value = this.getAttribute('data-cor');
        });
    });
    var primeiraCor = document.querySelector('.btn-cor-pasta-admin');
    if (primeiraCor) primeiraCor.style.borderColor = '#1e40af';

    var btnSalvar = document.getElementById('modal-pasta-admin-salvar');
    if (btnSalvar) btnSalvar.addEventListener('click', function() {
        var nome = inputNome.value.trim();
        if (!nome) { inputNome.focus(); return; }
        var url = modoEdicao ? BASE + '/admin/arquivos/pasta/renomear' : BASE + '/admin/arquivos/pasta/criar';
        var body = new FormData();
        body.append('_token', CSRF);
        body.append('nome', nome);
        if (!modoEdicao) {
            body.append('cor', inputCor.value);
            if (PARENT) body.append('parent_id', PARENT);
        }
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

    document.querySelectorAll('.btn-excluir-pasta-admin').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); e.preventDefault();
            var nome = this.getAttribute('data-nome');
            if (!confirm('Excluir a pasta "' + nome + '"?\nOs arquivos dentro dela não serão excluídos.')) return;
            var id = this.getAttribute('data-id');
            var body = new FormData();
            body.append('_token', CSRF);
            body.append('id', id);
            fetch(BASE + '/admin/arquivos/pasta/excluir', { method: 'POST', body: body })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) location.reload();
                    else alert(data.error || 'Erro ao excluir pasta.');
                });
        });
    });
})();

window.adminMoverArquivo = function(arquivoId, pastaId, selectEl) {
    if (pastaId === '') return;
    var CSRF = '<?= htmlspecialchars($csrf_token ?? '') ?>';
    var BASE = '<?= rtrim(URL ?? '', "/") ?>';
    var body = new FormData();
    body.append('_token', CSRF);
    body.append('arquivo_id', arquivoId);
    body.append('pasta_id', pastaId);
    selectEl.disabled = true;
    fetch(BASE + '/admin/arquivos/pasta/mover', { method: 'POST', body: body })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { location.reload(); }
            else { alert(data.error || 'Erro ao mover arquivo.'); selectEl.value = ''; selectEl.disabled = false; }
        })
        .catch(function() { alert('Erro de conexão.'); selectEl.value = ''; selectEl.disabled = false; });
};
</script>
