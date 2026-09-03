<?php
$indicadores = $indicadores ?? ['ativos' => 0, 'inscricoes_pendentes' => 0];
$projetos = $projetos ?? [];
$pendentes = $pendentes ?? [];
$edicao = $edicao ?? null;
$csrf_token = $csrf_token ?? '';
$modoAdmin = !empty($modo_admin);
$baseUrlExpo = rtrim((string) ($base_url_expo ?? (URL . '/professor/expo-colag')), '/');
$professores = $professores ?? [];
$filtros = $filtros ?? [];
$pagination = $pagination ?? [];
$tituloLista = $modoAdmin ? 'Todos os projetos' : 'Meus projetos';
$totalProjetos = count($projetos);
$statusOpcoes = ['Rascunho', 'Publicado', 'Inscricoes_abertas', 'Em_execucao', 'Entrega', 'Avaliacao', 'Concluido', 'Cancelado'];
$filtrosAtivosCount = 0;
foreach (['q', 'status', 'professor_id'] as $fk) {
    if (!empty($filtros[$fk])) {
        $filtrosAtivosCount++;
    }
}

$badgeStatus = static function (string $st): string {
    $map = [
        'Rascunho' => 'bg-slate-100 text-slate-700',
        'Publicado' => 'bg-sky-100 text-sky-800',
        'Inscricoes_abertas' => 'bg-emerald-100 text-emerald-800',
        'Em_execucao' => 'bg-violet-100 text-violet-800',
        'Entrega' => 'bg-amber-100 text-amber-800',
        'Avaliacao' => 'bg-orange-100 text-orange-800',
        'Concluido' => 'bg-emerald-100 text-emerald-800',
        'Cancelado' => 'bg-red-100 text-red-800',
    ];
    return $map[$st] ?? 'bg-slate-100 text-slate-700';
};
?>
<?php if ($modoAdmin): ?>
<?php
$page_header_title = 'Expo Colag';
$page_header_subtitle = 'Gerencie os projetos da feira de todos os professores.';
if (!empty($edicao['data_evento'])) {
    $page_header_subtitle .= ' Evento em ' . date('d/m/Y', strtotime($edicao['data_evento'])) . '.';
}
ob_start();
?>
<?php if (!empty($pode_gerenciar)): ?>
<a href="<?= URL ?>/admin/expo-colag/programacao"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-calendar-days mr-2 text-gray-500"></i>
    Programação / stands
</a>
<a href="<?= URL ?>/admin/expo-colag/autorizacoes"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-camera mr-2 text-gray-500"></i>
    Autorizações
</a>
<a href="<?= URL ?>/admin/expo-colag/configuracao"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-sliders mr-2 text-gray-500"></i>
    Configuração
</a>
<?php endif; ?>
<button type="button" onclick="openFilterDrawer()"
        class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
    Filtros
    <?php if ($filtrosAtivosCount > 0): ?>
    <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= (int) $filtrosAtivosCount ?></span>
    <?php endif; ?>
</button>
<a href="<?= htmlspecialchars($baseUrlExpo) ?>/criar"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Criar projeto
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
?>
<?php else: ?>
<div class="mb-6">
    <div class="flex flex-wrap justify-between items-start gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Expo Colag</h2>
            <p class="text-gray-600 text-sm">
                <?= $modoAdmin ? 'Gerencie os projetos da feira de todos os professores.' : 'Crie e acompanhe os projetos da feira.' ?>
                <?php if (!empty($edicao['data_evento'])): ?>
                    Evento em <?= htmlspecialchars(date('d/m/Y', strtotime($edicao['data_evento']))) ?>.
                <?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($modoAdmin && !empty($pode_gerenciar)): ?>
            <a href="<?= URL ?>/admin/expo-colag/programacao"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-calendar-days"></i>
                Programação / stands
            </a>
            <a href="<?= URL ?>/admin/expo-colag/autorizacoes"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-camera"></i>
                Autorizações
            </a>
            <a href="<?= URL ?>/admin/expo-colag/configuracao"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-sliders"></i>
                Configuração
            </a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($baseUrlExpo) ?>/criar"
               class="inline-flex items-center px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Criar projeto
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$authC = $autorizacao_contagens ?? [];
if ($modoAdmin && $authC):
?>
<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-6">
    Autorização de imagem:
    <?= (int) ($authC['Autorizado_total'] ?? 0) ?> total ·
    <?= (int) ($authC['Autorizado_interno'] ?? 0) ?> interno ·
    <?= (int) ($authC['Nao_autorizado'] ?? 0) ?> pendente.
    <a href="<?= URL ?>/admin/expo-colag/autorizacoes" class="font-semibold underline ml-1">Gerenciar</a>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-medium text-gray-500">Projetos ativos</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($indicadores['ativos'] ?? 0) ?></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-100 text-green-700 flex items-center justify-center">
                <i class="fa-solid fa-flask-vial"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-medium text-gray-500">Inscrições pendentes</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($indicadores['inscricoes_pendentes'] ?? 0) ?></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                <i class="fa-solid fa-user-clock"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-medium text-gray-500">Tarefas atrasadas</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($indicadores['tarefas_atrasadas'] ?? 0) ?></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-red-100 text-red-700 flex items-center justify-center">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-medium text-gray-500">Entregas a avaliar</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($indicadores['entregas_avaliar'] ?? 0) ?></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($pendentes)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl shadow-sm mb-6 overflow-hidden">
    <div class="px-5 py-3 border-b border-amber-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-amber-900">Inscrições aguardando aprovação</h3>
        <span class="text-xs text-amber-700"><?= count($pendentes) ?></span>
    </div>
    <ul class="divide-y divide-amber-100">
        <?php foreach ($pendentes as $pend): ?>
        <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-3 text-sm">
            <div>
                <span class="font-medium text-gray-900"><?= htmlspecialchars($pend['aluno_nome'] ?? '') ?></span>
                <span class="text-gray-500"> → <?= htmlspecialchars($pend['projeto_titulo'] ?? '') ?></span>
            </div>
            <div class="flex items-center gap-3">
                <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $pend['projeto_id'] ?>/inscricoes/decidir">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="inscricao_id" value="<?= (int) $pend['id'] ?>">
                    <input type="hidden" name="decisao" value="aprovar">
                    <input type="hidden" name="voltar" value="index">
                    <button type="submit" class="text-emerald-700 font-medium hover:underline">Aprovar</button>
                </form>
                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $pend['projeto_id'] ?>/acompanhar?aba=participantes" class="text-accent font-medium hover:underline">Ver</a>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-2">
        <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($tituloLista) ?></h3>
        <span class="text-sm text-gray-500"><?= (int) $totalProjetos ?> itens</span>
    </div>

    <?php if (empty($projetos)): ?>
        <div class="text-center py-12 px-6 text-gray-500">
            <i class="fa-solid fa-flask-vial text-4xl text-gray-300 mb-4"></i>
            <p class="text-gray-500 mb-3">Nenhum projeto ainda.</p>
            <?php if ($modoAdmin && $filtrosAtivosCount > 0): ?>
            <button type="button" onclick="clearFilters()" class="mt-1 text-sm text-blue-600 hover:text-blue-800">Limpar filtros</button>
            <?php else: ?>
            <a href="<?= htmlspecialchars($baseUrlExpo) ?>/criar" class="btn-primary-custom mt-2 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                Criar o primeiro projeto
            </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Projeto</th>
                        <?php if ($modoAdmin): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                        <?php endif; ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vagas</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($projetos as $p): ?>
                        <?php $st = (string) ($p['status'] ?? ''); ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="font-medium"><?= htmlspecialchars($p['titulo'] ?? '') ?></div>
                                <?php if (!empty($p['area'])): ?>
                                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($p['area']) ?></div>
                                <?php endif; ?>
                            </td>
                            <?php if ($modoAdmin): ?>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($p['professor_nome'] ?? '—') ?></td>
                            <?php endif; ?>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold <?= $badgeStatus($st) ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $st)) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= (int) ($p['vagas_totais'] ?? 0) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <?php if ($modoAdmin): ?>
                                <?php ob_start(); ?>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/acompanhar"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-chart-line text-gray-400 w-4 text-center"></i> <?= $modoAdmin ? 'Painel' : 'Meu painel' ?>
                                </a>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/editar"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                                </a>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/preview"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Preview
                                </a>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/materiais-pdf"
                                   target="_blank" rel="noopener"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-file-pdf text-gray-400 w-4 text-center"></i> PDF materiais
                                </a>
                                <?php if ($st !== 'Concluido'): ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/excluir"
                                      class="js-expo-delete-form"
                                      data-projeto-titulo="<?= htmlspecialchars($p['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="senha" value="" class="js-expo-delete-senha">
                                    <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php
                                $row_actions_dropdown_items = ob_get_clean();
                                $row_actions_dropdown_id = 'row-actions-expo-' . (int) $p['id'];
                                include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                                ?>
                                <?php else: ?>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/acompanhar"
                                   class="btn-primary-custom inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold hover:opacity-90">Meu painel</a>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/materiais-pdf"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-300 text-gray-800 bg-white hover:bg-gray-50 ml-1">PDF materiais</a>
                                <span class="text-gray-300 mx-1">·</span>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/editar" class="text-accent hover:underline">Editar</a>
                                <span class="text-gray-300 mx-1">·</span>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/preview" class="text-gray-600 hover:underline">Preview</a>
                                <?php if ($st !== 'Concluido'): ?>
                                <span class="text-gray-300 mx-1">·</span>
                                <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/excluir"
                                      class="js-expo-delete-form inline"
                                      data-projeto-titulo="<?= htmlspecialchars($p['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="senha" value="" class="js-expo-delete-senha">
                                    <button type="submit" class="text-red-600 font-medium hover:underline">Excluir</button>
                                </form>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($modoAdmin && !empty($pagination) && (int) ($pagination['total'] ?? 0) > 0): ?>
        <?php
        $pagTotal = (int) ($pagination['total'] ?? 0);
        $pagPerPage = (int) ($pagination['per_page'] ?? 10);
        $pagPage = (int) ($pagination['page'] ?? 1);
        $pagTotalPages = (int) ($pagination['total_pages'] ?? 1);
        $queryBase = array_filter([
            'q' => $filtros['q'] ?? '',
            'status' => $filtros['status'] ?? '',
            'professor_id' => (int) ($filtros['professor_id'] ?? 0) ?: '',
        ], static fn ($v) => $v !== '' && $v !== null);
        $makePageUrl = static function (int $pageNumber) use ($baseUrlExpo, $queryBase): string {
            $params = array_merge($queryBase, ['page' => $pageNumber]);
            return $baseUrlExpo . '?' . http_build_query($params);
        };
        ?>
        <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">
                Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>-<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> projeto(s)
            </p>
            <?php if ($pagTotalPages > 1): ?>
            <div class="flex items-center gap-1">
                <?php if ($pagPage > 1): ?>
                    <a href="<?= htmlspecialchars($makePageUrl($pagPage - 1)) ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
                <?php endif; ?>
                <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                    <a href="<?= htmlspecialchars($makePageUrl($i)) ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($pagPage < $pagTotalPages): ?>
                    <a href="<?= htmlspecialchars($makePageUrl($pagPage + 1)) ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div id="expoDeleteModal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40" data-expo-delete-close></div>
    <div class="relative min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-3">
                <h3 class="text-lg font-semibold text-gray-900">Confirmar exclusão</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 p-1" data-expo-delete-close aria-label="Fechar">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="px-6 py-5">
                <p id="expoDeleteModalText" class="text-sm text-gray-600 mb-4">
                    Este projeto será removido das telas da Expo Colag, mas permanecerá registrado no banco. Digite sua senha para confirmar.
                </p>
                <label for="expoDeletePassword" class="block text-sm font-medium text-gray-700 mb-1.5">Senha</label>
                <input type="password" id="expoDeletePassword" autocomplete="current-password"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Digite sua senha">
                <p id="expoDeleteError" class="hidden mt-2 text-sm text-red-600">Digite sua senha para confirmar.</p>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex gap-3 justify-end">
                <button type="button" data-expo-delete-close
                        class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Cancelar
                </button>
                <button type="button" id="expoDeleteConfirm"
                        class="px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors">
                    Confirmar exclusão
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var pendingForm = null;
    var modal = document.getElementById('expoDeleteModal');
    var passwordInput = document.getElementById('expoDeletePassword');
    var errorEl = document.getElementById('expoDeleteError');
    var textEl = document.getElementById('expoDeleteModalText');
    var confirmBtn = document.getElementById('expoDeleteConfirm');

    function closeDeleteModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        if (passwordInput) passwordInput.value = '';
        if (errorEl) errorEl.classList.add('hidden');
        pendingForm = null;
    }

    function openDeleteModal(form) {
        if (!modal) return;
        pendingForm = form;
        var titulo = form.getAttribute('data-projeto-titulo') || 'este projeto';
        if (textEl) {
            textEl.textContent = '"' + titulo + '" será removido das telas da Expo Colag, mas permanecerá registrado no banco. Digite sua senha para confirmar.';
        }
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () {
            if (passwordInput) passwordInput.focus();
        }, 50);
    }

    document.querySelectorAll('.js-expo-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var senhaField = form.querySelector('.js-expo-delete-senha');
            if (senhaField && senhaField.value !== '') {
                return;
            }
            event.preventDefault();
            openDeleteModal(form);
        });
    });

    document.querySelectorAll('[data-expo-delete-close]').forEach(function (btn) {
        btn.addEventListener('click', closeDeleteModal);
    });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (!pendingForm || !passwordInput) return;
            var senha = passwordInput.value.trim();
            if (senha === '') {
                if (errorEl) errorEl.classList.remove('hidden');
                passwordInput.focus();
                return;
            }
            var senhaField = pendingForm.querySelector('.js-expo-delete-senha');
            if (senhaField) {
                senhaField.value = senha;
            }
            pendingForm.submit();
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeDeleteModal();
        }
        if (event.key === 'Enter' && modal && !modal.classList.contains('hidden') && document.activeElement === passwordInput) {
            event.preventDefault();
            if (confirmBtn) confirmBtn.click();
        }
    });
})();
</script>

<?php if ($modoAdmin): ?>
<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar projetos</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar filtros">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="get" action="<?= htmlspecialchars($baseUrlExpo) ?>" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_q" class="block text-sm font-medium text-gray-700 mb-1.5">Buscar</label>
                <input type="search" id="filtro_q" name="q"
                       value="<?= htmlspecialchars($filtros['q'] ?? '') ?>"
                       placeholder="Título, área ou professor"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select id="filtro_status" name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos os status</option>
                    <?php foreach ($statusOpcoes as $statusFiltro): ?>
                        <option value="<?= htmlspecialchars($statusFiltro) ?>" <?= ($filtros['status'] ?? '') === $statusFiltro ? 'selected' : '' ?>>
                            <?= htmlspecialchars(str_replace('_', ' ', $statusFiltro)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_professor" class="block text-sm font-medium text-gray-700 mb-1.5">Professor responsável</label>
                <select id="filtro_professor" name="professor_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos os professores</option>
                    <?php foreach ($professores as $prof): ?>
                        <option value="<?= (int) $prof['id'] ?>" <?= (int) ($filtros['professor_id'] ?? 0) === (int) $prof['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prof['nome'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <button type="button" onclick="clearFilters()"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Limpar
            </button>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<script>
function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function clearFilters() {
    window.location.href = <?= json_encode($baseUrlExpo) ?>;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
    }
});
</script>
<?php endif; ?>
