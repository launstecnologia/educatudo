<?php
require_once __DIR__ . '/../../../Core/CreditosDecimalHelper.php';

$alunos = $alunos ?? [];
$escolas = $escolas ?? [];
$totais = $totais ?? ['alunos' => 0, 'escolas_consultadas' => 0, 'tudicoins' => 0];
$pagination = $pagination ?? ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1];
$csrf_token = $csrf_token ?? '';
$filtro_escola = (int) ($filtro_escola ?? 0);
$filtro_nome = (string) ($filtro_nome ?? '');
$filtro_turma = (string) ($filtro_turma ?? '');
$filtro_creditos_ordem = (string) ($filtro_creditos_ordem ?? 'nome');
$baseUrl = URL . '/master/creditos/alunos';

$filtrosAtivos = 0;
if ($filtro_escola > 0) {
    $filtrosAtivos++;
}
if ($filtro_nome !== '') {
    $filtrosAtivos++;
}
if ($filtro_turma !== '') {
    $filtrosAtivos++;
}
if ($filtro_creditos_ordem !== 'nome') {
    $filtrosAtivos++;
}

$queryParams = [];
if ($filtro_escola > 0) {
    $queryParams['escola_id'] = $filtro_escola;
}
if ($filtro_nome !== '') {
    $queryParams['nome'] = $filtro_nome;
}
if ($filtro_turma !== '') {
    $queryParams['turma'] = $filtro_turma;
}
if ($filtro_creditos_ordem !== 'nome') {
    $queryParams['creditos_ordem'] = $filtro_creditos_ordem;
}
?>

<div class="mb-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-1">Alunos TudiCoins</h2>
            <p class="text-slate-600 text-sm">Relação de alunos por escola, turma e saldo disponível.</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Alunos no filtro</p>
        <p class="text-2xl font-bold text-blue-600 mt-1"><?= (int) ($totais['alunos'] ?? 0) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= (int) ($totais['escolas_consultadas'] ?? 0) ?> escola(s) consultada(s)</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">TudiCoins no filtro</p>
        <p class="text-2xl font-bold text-amber-600 mt-1"><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay((float) ($totais['tudicoins'] ?? 0))) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars(CreditosDecimalHelper::formatReaisFromTudicoins((float) ($totais['tudicoins'] ?? 0))) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Filtros ativos</p>
        <p class="text-2xl font-bold text-slate-800 mt-1"><?= (int) $filtrosAtivos ?></p>
        <p class="text-xs text-slate-400 mt-1">Escola, nome, turma e saldo</p>
    </div>
</div>

<form method="get" action="<?= $baseUrl ?>" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3 items-end">
        <div>
            <label for="filtro_escola" class="block text-xs font-medium text-slate-600 mb-1">Escola</label>
            <select id="filtro_escola" name="escola_id" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="0">Todas as escolas</option>
                <?php foreach ($escolas as $escola): ?>
                <option value="<?= (int) ($escola['id'] ?? 0) ?>" <?= $filtro_escola === (int) ($escola['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($escola['nome'] ?? '')) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filtro_nome" class="block text-xs font-medium text-slate-600 mb-1">Nome</label>
            <input type="text" id="filtro_nome" name="nome" value="<?= htmlspecialchars($filtro_nome) ?>"
                   placeholder="Nome, e-mail ou RA"
                   class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label for="filtro_turma" class="block text-xs font-medium text-slate-600 mb-1">Turma</label>
            <input type="text" id="filtro_turma" name="turma" value="<?= htmlspecialchars($filtro_turma) ?>"
                   placeholder="Ex.: 2º B"
                   class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label for="filtro_creditos_ordem" class="block text-xs font-medium text-slate-600 mb-1">Ordenação</label>
            <select id="filtro_creditos_ordem" name="creditos_ordem" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="nome" <?= $filtro_creditos_ordem === 'nome' ? 'selected' : '' ?>>Escola e nome</option>
                <option value="maior" <?= $filtro_creditos_ordem === 'maior' ? 'selected' : '' ?>>Maior saldo</option>
                <option value="menor" <?= $filtro_creditos_ordem === 'menor' ? 'selected' : '' ?>>Menor saldo</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-2 xl:justify-end">
            <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fa-solid fa-magnifying-glass text-xs"></i> Filtrar
            </button>
            <?php if ($filtrosAtivos > 0): ?>
            <a href="<?= $baseUrl ?>" class="inline-flex flex-1 items-center justify-center px-4 py-2.5 border border-slate-300 text-sm font-medium text-slate-600 rounded-lg hover:bg-slate-50 transition-colors">Limpar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($alunos)): ?>
    <div class="px-6 py-10 text-center text-sm text-slate-500">
        Nenhum aluno encontrado com os filtros atuais.
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Nome do aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">TudiCoins</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php foreach ($alunos as $aluno): ?>
                <?php
                    $alunoId = (int) ($aluno['aluno_id'] ?? 0);
                    $escolaId = (int) ($aluno['escola_id'] ?? 0);
                    $saldo = CreditosDecimalHelper::fromSignedScalar($aluno['saldo_creditos'] ?? 0, 0.0);
                    $creditosDisponiveis = !empty($aluno['creditos_disponiveis']);
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 text-sm text-slate-700">
                        <span class="block font-medium text-slate-900"><?= htmlspecialchars((string) ($aluno['aluno_nome'] ?? '')) ?></span>
                        <?php if (!empty($aluno['email'])): ?>
                        <span class="block text-xs text-slate-500 mt-0.5"><?= htmlspecialchars((string) $aluno['email']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($aluno['ra'])): ?>
                        <span class="inline-flex mt-2 px-2 py-0.5 rounded-full bg-slate-100 text-xs text-slate-600">RA: <?= htmlspecialchars((string) $aluno['ra']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-700 whitespace-nowrap"><?= htmlspecialchars((string) ($aluno['turma_nome'] ?? 'Sem turma')) ?></td>
                    <td class="px-6 py-4 text-sm text-slate-700">
                        <span class="block font-medium"><?= htmlspecialchars((string) ($aluno['escola_nome'] ?? '')) ?></span>
                        <span class="block text-xs text-slate-500 mt-0.5"><?= htmlspecialchars((string) ($aluno['escola_slug'] ?? '')) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-right whitespace-nowrap">
                        <span class="block font-semibold text-amber-600 tabular-nums"><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay($saldo)) ?></span>
                        <span class="block text-xs text-slate-400 mt-0.5 tabular-nums"><?= htmlspecialchars(CreditosDecimalHelper::formatReaisFromTudicoins($saldo)) ?></span>
                    </td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <?php if ($creditosDisponiveis): ?>
                        <button type="button"
                                class="inline-flex items-center justify-center gap-2 px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                data-abrir-tudicoins-aluno
                                data-aluno-id="<?= $alunoId ?>"
                                data-escola-id="<?= $escolaId ?>"
                                data-aluno-nome="<?= htmlspecialchars((string) ($aluno['aluno_nome'] ?? ''), ENT_QUOTES) ?>"
                                data-escola-nome="<?= htmlspecialchars((string) ($aluno['escola_nome'] ?? ''), ENT_QUOTES) ?>">
                            <i class="fa-solid fa-coins text-xs"></i> Creditar
                        </button>
                        <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-100 text-xs font-medium text-slate-500">Carteira indisponível</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $total = (int) ($pagination['total'] ?? 0);
    $perPage = (int) ($pagination['per_page'] ?? 20);
    $page = (int) ($pagination['page'] ?? 1);
    $totalPages = (int) ($pagination['total_pages'] ?? 1);
    $baseQuery = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
    $sep = $baseQuery === '' ? '?' : '&';
    ?>
    <div class="px-6 py-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-slate-600">
            Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> aluno(s)
        </p>
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
            <a href="<?= $baseUrl . $baseQuery . $sep ?>page=<?= $page - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="<?= $baseUrl . $baseQuery . $sep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-blue-600 text-white' : 'text-slate-700 bg-slate-100 hover:bg-slate-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="<?= $baseUrl . $baseQuery . $sep ?>page=<?= $page + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div id="modal-tudicoins-aluno" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/50 p-4" aria-hidden="true">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md" role="dialog" aria-modal="true" aria-labelledby="modal-tudicoins-aluno-titulo">
        <form method="post" action="<?= URL ?>/master/creditos/alunos/creditar" id="form-tudicoins-aluno" class="px-5 py-4 space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="escola_id" id="modal-tudicoins-aluno-escola-id" value="">
            <input type="hidden" name="aluno_id" id="modal-tudicoins-aluno-id" value="">
            <input type="hidden" name="filtro_escola_id" value="<?= (int) $filtro_escola ?>">
            <input type="hidden" name="nome" value="<?= htmlspecialchars($filtro_nome) ?>">
            <input type="hidden" name="turma" value="<?= htmlspecialchars($filtro_turma) ?>">
            <input type="hidden" name="creditos_ordem" value="<?= htmlspecialchars($filtro_creditos_ordem) ?>">
            <input type="hidden" name="page" value="<?= (int) ($pagination['page'] ?? 1) ?>">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h4 id="modal-tudicoins-aluno-titulo" class="text-lg font-semibold text-slate-900">Creditar TudiCoins</h4>
                    <p class="text-sm text-slate-500 mt-0.5" id="modal-tudicoins-aluno-nome"></p>
                </div>
                <button type="button" id="modal-tudicoins-aluno-fechar" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Fechar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div>
                <label for="modal-tudicoins-aluno-valor" class="block text-sm font-medium text-slate-700 mb-1">Quantidade</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-sm font-medium text-slate-500 pointer-events-none"><?= CreditosDecimalHelper::PREFIXO ?></span>
                    <input type="text" id="modal-tudicoins-aluno-valor" name="valor" value="100,00"
                           inputmode="decimal" autocomplete="off" required
                           class="w-full min-w-0 px-3 py-2 pl-12 border border-slate-300 rounded-lg text-sm bg-white tabular-nums focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" id="modal-tudicoins-aluno-cancelar" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Creditar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('modal-tudicoins-aluno');
    if (!modal) return;
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    var alunoId = document.getElementById('modal-tudicoins-aluno-id');
    var escolaId = document.getElementById('modal-tudicoins-aluno-escola-id');
    var nomeEl = document.getElementById('modal-tudicoins-aluno-nome');
    var valorEl = document.getElementById('modal-tudicoins-aluno-valor');

    function abrir(btn) {
        alunoId.value = btn.getAttribute('data-aluno-id') || '';
        escolaId.value = btn.getAttribute('data-escola-id') || '';
        var nome = btn.getAttribute('data-aluno-nome') || '';
        var escola = btn.getAttribute('data-escola-nome') || '';
        nomeEl.textContent = escola ? nome + ' · ' + escola : nome;
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

    document.querySelectorAll('[data-abrir-tudicoins-aluno]').forEach(function(btn) {
        btn.addEventListener('click', function() { abrir(btn); });
    });
    document.getElementById('modal-tudicoins-aluno-fechar')?.addEventListener('click', fechar);
    document.getElementById('modal-tudicoins-aluno-cancelar')?.addEventListener('click', fechar);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) fechar();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) fechar();
    });
})();
</script>
