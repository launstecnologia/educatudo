<div class="mb-8">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Jornada da Redação</h2>
            <p class="text-gray-600">Criar e gerenciar propostas de redação</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="<?= URL ?>/professor/redacao-configuravel/relatorio" class="bg-white text-purple-700 border border-purple-200 px-5 py-3 rounded-xl hover:bg-purple-50 transition-all duration-300 flex items-center shadow-sm">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m4 6V7m4 10v-3M5 21h14"></path></svg>
                Relatório
            </a>
            <a href="<?= URL ?>/professor/redacao-configuravel/novo" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 flex items-center shadow-lg">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Nova Proposta
            </a>
        </div>
    </div>
</div>

<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
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
                            Nenhuma proposta. <a href="<?= URL ?>/professor/redacao-configuravel/novo" class="text-purple-600 hover:underline">Criar primeira proposta</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proposals as $p): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($p['title']) ?></td>
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
                                   class="text-indigo-600 hover:text-indigo-900"
                                   title="Ver">
                                    <span class="sr-only">Ver</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="<?= URL ?>/professor/redacao-configuravel/<?= (int)$p['id'] ?>/editar"
                                   class="text-blue-600 hover:text-blue-900"
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
