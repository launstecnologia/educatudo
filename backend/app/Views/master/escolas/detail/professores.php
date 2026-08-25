<?php
$professores = $professores ?? [];
$escola_id = $escola_id ?? 0;
$total = $total ?? count($professores);
$page = $page ?? 1;
$total_pages = $total_pages ?? 1;
$filtro_busca = $filtro_busca ?? '';
$filtro_status = $filtro_status ?? '';
$creditos_disponiveis = $creditos_disponiveis ?? false;
$csrf_token = $csrf_token ?? '';
$layout_config = $layout_config ?? [];
$modoPoolEscola = (($layout_config['creditos_modo_pool_escola'] ?? '0') === '1');
$base_url = URL . '/master/escolas/' . (int) $escola_id . '/professores';
$entrar_como_base = URL . '/master/entrar-como?escola_id=' . (int) $escola_id . '&tipo=professor';
$has_filtros = $filtro_busca !== '' || $filtro_status !== '';
if (!class_exists('CreditosDecimalHelper')) {
    require_once __DIR__ . '/../../../../Core/CreditosDecimalHelper.php';
}

$query_params = [];
if ($filtro_busca !== '') {
    $query_params['busca'] = $filtro_busca;
}
if ($filtro_status !== '') {
    $query_params['status'] = $filtro_status;
}

$renderAcoesProfessor = static function (array $p, string $suffix) use ($entrar_como_base, $escola_id, $creditos_disponiveis): void {
    $id = (int) ($p['id'] ?? 0);
    $nome = (string) ($p['nome'] ?? '');
    ob_start();
    ?>
    <?php if ($creditos_disponiveis): ?>
    <button type="button"
            class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
            data-abrir-tudicoins-professor
            data-professor-id="<?= $id ?>"
            data-professor-nome="<?= htmlspecialchars($nome, ENT_QUOTES) ?>"
            data-action="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/professores/<?= $id ?>/tudicoins">
        <i class="fa-solid fa-coins text-amber-500 w-4 text-center"></i> Dar TudiCoins
    </button>
    <?php endif; ?>
    <a href="<?= $entrar_como_base ?>&id=<?= $id ?>" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
        <i class="fa-solid fa-right-to-bracket text-gray-400 w-4 text-center"></i> Entrar como
    </a>
    <?php
    $row_actions_dropdown_items = ob_get_clean();
    $row_actions_dropdown_id = 'row-actions-professor-' . $suffix . $id;
    include __DIR__ . '/../../../admin/_partials/row_actions_dropdown.php';
};
?>

<div class="bg-white rounded-xl shadow border border-slate-200 p-4 sm:p-6">
    <div class="mb-5">
        <h3 class="text-lg font-semibold text-slate-800">
            Professores
            <span class="text-sm font-normal text-slate-500">(<?= (int) $total ?>)</span>
        </h3>
        <?php if ($creditos_disponiveis): ?>
        <p class="text-xs text-slate-500 mt-1">Para creditar um professor, use <strong>Ações → Dar TudiCoins</strong>.</p>
        <?php endif; ?>
    </div>

    <form method="GET" action="<?= $base_url ?>" class="mb-5 space-y-3">
        <div>
            <label for="filtro-busca-professores" class="block text-xs font-medium text-slate-600 mb-1">Busca</label>
            <input type="text" id="filtro-busca-professores" name="busca" value="<?= htmlspecialchars($filtro_busca) ?>"
                   placeholder="Buscar por nome ou e-mail..."
                   class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            <div class="min-w-0 sm:col-span-2">
                <label for="filtro-status-professores" class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <div class="relative">
                    <select id="filtro-status-professores" name="status"
                            class="w-full appearance-none px-3 py-2.5 pr-10 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="ativo" <?= $filtro_status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                        <option value="inativo" <?= $filtro_status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 sm:justify-end">
                <button type="submit" class="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i> Filtrar
                </button>
                <?php if ($has_filtros): ?>
                <a href="<?= $base_url ?>" class="inline-flex flex-1 sm:flex-none items-center justify-center px-4 py-2.5 border border-slate-300 text-sm font-medium text-slate-600 rounded-lg hover:bg-slate-50 transition-colors">Limpar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if (empty($professores)): ?>
    <p class="text-slate-500 text-sm">Nenhum professor encontrado.</p>
    <?php else: ?>

    <div class="hidden md:block">
        <table class="w-full divide-y divide-gray-200 table-fixed">
            <thead>
                <tr class="bg-slate-50">
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase w-[calc(100%-7rem)]">Nome</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-600 uppercase w-28">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($professores as $p):
                    $id = (int) ($p['id'] ?? 0);
                    $ativo = !empty($p['ativo']);
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-3 align-top">
                        <div class="font-medium text-sm text-slate-900 break-words"><?= htmlspecialchars($p['nome'] ?? '') ?></div>
                        <div class="text-xs text-slate-500 mt-0.5 break-all"><?= htmlspecialchars($p['email'] ?? '') ?></div>
                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                            <?php if ($ativo): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                            <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inativo</span>
                            <?php endif; ?>
                            <?php if ($creditos_disponiveis): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-800">
                                <?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay(\CreditosDecimalHelper::fromSignedScalar($p['saldo_creditos'] ?? 0, 0.0))) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-3 py-3 align-top text-right whitespace-nowrap">
                        <?php $renderAcoesProfessor($p, ''); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        <?php foreach ($professores as $p):
            $id = (int) ($p['id'] ?? 0);
            $ativo = !empty($p['ativo']);
        ?>
        <div class="border border-slate-200 rounded-lg p-3 bg-slate-50/50">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="font-medium text-sm text-slate-900 break-words"><?= htmlspecialchars($p['nome'] ?? '') ?></div>
                    <div class="text-xs text-slate-500 mt-0.5 break-all"><?= htmlspecialchars($p['email'] ?? '') ?></div>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <?php if ($ativo): ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                        <?php else: ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inativo</span>
                        <?php endif; ?>
                        <?php if ($creditos_disponiveis): ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-800">
                            <?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay(\CreditosDecimalHelper::fromSignedScalar($p['saldo_creditos'] ?? 0, 0.0))) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="shrink-0">
                    <?php $renderAcoesProfessor($p, 'm-'); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-200">
        <p class="text-sm text-slate-600">Página <?= (int) $page ?> de <?= (int) $total_pages ?></p>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
            <a href="<?= $base_url ?>?<?= http_build_query(array_merge($query_params, ['page' => $page - 1])) ?>"
               class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Anterior</a>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
            <a href="<?= $base_url ?>?<?= http_build_query(array_merge($query_params, ['page' => $page + 1])) ?>"
               class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php if ($creditos_disponiveis): ?>
<div id="modal-tudicoins-professor" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/50 p-4" aria-hidden="true">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md" role="dialog" aria-modal="true" aria-labelledby="modal-tudicoins-professor-titulo">
        <form method="post" id="form-tudicoins-professor" class="px-5 py-4 space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h4 id="modal-tudicoins-professor-titulo" class="text-lg font-semibold text-slate-900">Dar TudiCoins</h4>
                    <p class="text-sm text-slate-500 mt-0.5" id="modal-tudicoins-professor-nome"></p>
                </div>
                <button type="button" id="modal-tudicoins-professor-fechar" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Fechar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <?php if ($modoPoolEscola): ?>
            <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                Esta escola está com “Escola paga o consumo”. O gasto de IA sai da carteira da escola; o saldo pessoal só vale se essa opção estiver desligada.
            </p>
            <?php endif; ?>
            <div>
                <label for="modal-tudicoins-professor-valor" class="block text-sm font-medium text-slate-700 mb-1">Quantidade</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-sm font-medium text-slate-500 pointer-events-none"><?= \CreditosDecimalHelper::PREFIXO ?></span>
                    <input type="text" id="modal-tudicoins-professor-valor" name="valor" value="100,00"
                           inputmode="decimal" autocomplete="off" required
                           class="w-full min-w-0 px-3 py-2 pl-12 border border-slate-300 rounded-lg text-sm bg-white tabular-nums">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" id="modal-tudicoins-professor-cancelar" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Creditar</button>
            </div>
        </form>
    </div>
</div>
<script>
(function() {
    var modal = document.getElementById('modal-tudicoins-professor');
    if (!modal) return;
    document.querySelectorAll('body > #modal-tudicoins-professor').forEach(function(el) {
        if (el !== modal) el.remove();
    });
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    var form = document.getElementById('form-tudicoins-professor');
    var nomeEl = document.getElementById('modal-tudicoins-professor-nome');
    var valorEl = document.getElementById('modal-tudicoins-professor-valor');

    function abrir(btn) {
        form.action = btn.getAttribute('data-action') || '';
        nomeEl.textContent = btn.getAttribute('data-professor-nome') || '';
        valorEl.value = '100,00';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        setTimeout(function() { valorEl.focus(); valorEl.select(); }, 50);
    }
    function fechar() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    document.querySelectorAll('[data-abrir-tudicoins-professor]').forEach(function(btn) {
        btn.addEventListener('click', function() { abrir(btn); });
    });
    document.getElementById('modal-tudicoins-professor-fechar')?.addEventListener('click', fechar);
    document.getElementById('modal-tudicoins-professor-cancelar')?.addEventListener('click', fechar);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) fechar();
    });
})();
</script>
<?php endif; ?>
