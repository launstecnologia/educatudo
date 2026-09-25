<?php
$ui = __DIR__ . '/../../admin/_partials/ui';
$filtros = $filtros ?? ['titulo' => '', 'status' => '', 'banca' => ''];
$filtrosAtivos = 0;
if (trim((string) ($filtros['titulo'] ?? '')) !== '') {
    $filtrosAtivos++;
}
if (trim((string) ($filtros['status'] ?? '')) !== '') {
    $filtrosAtivos++;
}
if (trim((string) ($filtros['banca'] ?? '')) !== '') {
    $filtrosAtivos++;
}

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

$ui_btn_variant = 'complementar';
$ui_btn_label = 'Relatório';
$ui_btn_icon = 'fa-solid fa-chart-column';
$ui_btn_onclick = '';
$ui_btn_href = URL . '/professor/redacao-configuravel/relatorio';
$ui_btn_filter_count = 0;
include $ui . '/btn.php';

$ui_btn_variant = 'primary';
$ui_btn_label = 'Nova Proposta';
$ui_btn_icon = 'fa-solid fa-plus';
$ui_btn_href = URL . '/professor/redacao-configuravel/novo';
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();
$page_header_title = 'Jornada da Redação';
$page_header_subtitle = 'Criar e gerenciar propostas de redação';
include __DIR__ . '/../../admin/_partials/page_header_list.php';

if (!empty($_SESSION['flash_message'])) {
    $flash_message = (string) $_SESSION['flash_message'];
    $flash_status = (($_SESSION['flash_type'] ?? '') === 'success') ? 'success' : 'error';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    include __DIR__ . '/../../admin/_partials/flash_message.php';
}
?>

<div class="bg-white rounded-xl shadow-lg border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Minhas Propostas</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Banca / Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qtd. alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qtd. enviados</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qtd. corrigidos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($proposals)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <?= $filtrosAtivos > 0 ? 'Nenhuma proposta com esse filtro.' : 'Nenhuma proposta.' ?>
                            <?php if ($filtrosAtivos === 0): ?>
                            <a href="<?= URL ?>/professor/redacao-configuravel/novo" class="text-gray-900 font-semibold hover:underline">Criar primeira proposta</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proposals as $p): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                            <a href="<?= URL ?>/professor/redacao-configuravel/<?= (int)$p['id'] ?>" class="hover:underline"><?= htmlspecialchars($p['title']) ?></a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($p['board_name']) ?> — <?= htmlspecialchars($p['text_type_name']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= (int)($p['qtd_alunos'] ?? 0) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= (int)($p['qtd_enviados'] ?? 0) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= (int)($p['qtd_corrigidos'] ?? 0) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-col gap-1">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $p['status'] === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= $p['status'] === 'published' ? 'Publicada' : 'Rascunho' ?>
                                </span>
                                <?php if (!(bool) ($p['ativo'] ?? 1)): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inativa</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center gap-3">
                                <a href="<?= URL ?>/professor/redacao-configuravel/<?= (int)$p['id'] ?>"
                                   class="text-gray-700 hover:text-gray-900"
                                   title="Ver">
                                    <span class="sr-only">Ver</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="<?= URL ?>/professor/redacao-configuravel/<?= (int)$p['id'] ?>/editar"
                                   class="text-gray-600 hover:text-gray-900"
                                   title="Editar">
                                    <span class="sr-only">Editar</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L12 15l-4 1 1-4 8.586-8.586z"/>
                                    </svg>
                                </a>
                                <?php if ((bool) ($p['ativo'] ?? 1)): ?>
                                <button type="button"
                                        onclick="openToggleAtivoModal(<?= (int)$p['id'] ?>, <?= htmlspecialchars(json_encode((string) ($p['title'] ?? '')), ENT_QUOTES) ?>, true)"
                                        class="text-red-600 hover:text-red-900"
                                        title="Desativar">
                                    <span class="sr-only">Desativar</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                </button>
                                <?php else: ?>
                                <button type="button"
                                        onclick="openToggleAtivoModal(<?= (int)$p['id'] ?>, <?= htmlspecialchars(json_encode((string) ($p['title'] ?? '')), ENT_QUOTES) ?>, false)"
                                        class="text-green-600 hover:text-green-900"
                                        title="Reativar">
                                    <span class="sr-only">Reativar</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                </button>
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

<!-- Modal: confirmar senha para desativar/reativar proposta -->
<div id="toggle-ativo-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-24 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium text-gray-900 mb-2" id="toggle-ativo-modal-title">Confirmar</h3>
        <p class="text-sm text-gray-600 mb-4" id="toggle-ativo-modal-desc"></p>
        <div class="mb-4">
            <label for="toggle-ativo-senha" class="block text-sm font-medium text-gray-700 mb-2">Sua senha *</label>
            <input type="password" id="toggle-ativo-senha"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                   placeholder="Digite sua senha para confirmar" autocomplete="current-password">
            <p id="toggle-ativo-error" class="mt-2 text-sm text-red-600 hidden"></p>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" id="toggle-ativo-cancel" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" id="toggle-ativo-confirm" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">Confirmar</button>
        </div>
    </div>
</div>

<?php
$opcoesStatus = [
    '' => 'Todos',
    'published' => 'Publicada',
    'draft' => 'Rascunho',
];
$opcoesBanca = ['' => 'Todas'];
foreach ($bancas ?? [] as $banca) {
    $opcoesBanca[(string) $banca] = (string) $banca;
}
ob_start();
?>
<form method="get" action="<?= URL ?>/professor/redacao-configuravel" class="flex flex-col flex-1 overflow-hidden">
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6">
        <?php
        $ui_form_campo_label = 'Título';
        $ui_form_campo_name = 'titulo';
        $ui_form_campo_tipo = 'text';
        $ui_form_campo_value = (string) ($filtros['titulo'] ?? '');
        $ui_form_campo_placeholder = 'Buscar pelo título';
        $ui_form_campo_span = 'full';
        $ui_form_campo_mb = 'mb-4';
        $ui_form_campo_obrigatorio = false;
        $ui_form_campo_opcoes = [];
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Banca';
        $ui_form_campo_name = 'banca';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesBanca;
        $ui_form_campo_value = (string) ($filtros['banca'] ?? '');
        $ui_form_campo_placeholder = '';
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Status';
        $ui_form_campo_name = 'status';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesStatus;
        $ui_form_campo_value = (string) ($filtros['status'] ?? '');
        include $ui . '/form_campo.php';
        ?>
    </div>
    <div class="px-6 sm:px-8 py-4 border-t border-gray-200 flex gap-3">
        <?php
        $ui_btn_variant = 'complementar';
        $ui_btn_label = 'Limpar';
        $ui_btn_href = URL . '/professor/redacao-configuravel';
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
    (function () {
        var csrfToken = <?= json_encode($csrf_token ?? '') ?>;
        var modal = document.getElementById('toggle-ativo-modal');
        var senhaInput = document.getElementById('toggle-ativo-senha');
        var errorEl = document.getElementById('toggle-ativo-error');
        var titleEl = document.getElementById('toggle-ativo-modal-title');
        var descEl = document.getElementById('toggle-ativo-modal-desc');
        var confirmBtn = document.getElementById('toggle-ativo-confirm');
        var pendingId = null;

        window.openToggleAtivoModal = function (id, titulo, desativar) {
            pendingId = id;
            senhaInput.value = '';
            errorEl.classList.add('hidden');
            errorEl.textContent = '';
            if (desativar) {
                titleEl.textContent = 'Desativar proposta';
                descEl.textContent = '"' + titulo + '" deixará de aparecer para você, os alunos e relatórios. Nada é apagado — você pode reativar quando quiser. Digite sua senha para confirmar.';
            } else {
                titleEl.textContent = 'Reativar proposta';
                descEl.textContent = '"' + titulo + '" voltará a aparecer normalmente. Digite sua senha para confirmar.';
            }
            modal.classList.remove('hidden');
            senhaInput.focus();
        };

        function closeModal() {
            modal.classList.add('hidden');
            pendingId = null;
        }

        document.getElementById('toggle-ativo-cancel').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        confirmBtn.addEventListener('click', function () {
            var senha = senhaInput.value.trim();
            if (!senha) {
                errorEl.textContent = 'Digite sua senha para confirmar.';
                errorEl.classList.remove('hidden');
                return;
            }
            confirmBtn.disabled = true;

            var body = new URLSearchParams();
            body.append('_token', csrfToken);
            body.append('senha', senha);

            fetch(<?= json_encode(URL . '/professor/redacao-configuravel/') ?> + pendingId + '/toggle-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                        return;
                    }
                    errorEl.textContent = data.error || 'Falha ao atualizar a proposta.';
                    errorEl.classList.remove('hidden');
                })
                .catch(function () {
                    errorEl.textContent = 'Erro de conexão. Tente novamente.';
                    errorEl.classList.remove('hidden');
                })
                .finally(function () { confirmBtn.disabled = false; });
        });

        senhaInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmBtn.click();
            }
        });
    })();
</script>
