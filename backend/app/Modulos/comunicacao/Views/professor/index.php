<!-- Header Section -->
<div class="mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Chat com Alunos</h1>
        <p class="text-gray-600 mt-2">Inicie uma conversa ou continue de onde parou</p>
    </div>
</div>

<?php
$alunosDisponiveis = $alunos_disponiveis ?? [];
$turmasFiltro = [];
foreach ($alunosDisponiveis as $alunoRow) {
    $tid = (int) ($alunoRow['turma_id'] ?? 0);
    $tnome = trim((string) ($alunoRow['turma_nome'] ?? ''));
    if ($tid > 0 && $tnome !== '' && !isset($turmasFiltro[$tid])) {
        $turmasFiltro[$tid] = $tnome;
    }
}
asort($turmasFiltro, SORT_NATURAL | SORT_FLAG_CASE);

$alunosJson = [];
foreach ($alunosDisponiveis as $alunoRow) {
    $alunosJson[] = [
        'id' => (int) ($alunoRow['id'] ?? 0),
        'nome' => (string) ($alunoRow['nome'] ?? ''),
        'ra' => (string) ($alunoRow['ra'] ?? ''),
        'turma_id' => (int) ($alunoRow['turma_id'] ?? 0),
        'turma_nome' => (string) ($alunoRow['turma_nome'] ?? ''),
    ];
}
?>

<!-- Iniciar conversa (filtros, sem cards) -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 w-full">
    <h2 class="text-xl font-semibold text-gray-900 mb-1">Nova conversa</h2>
    <p class="text-sm text-gray-500 mb-5">Filtre pela turma e escolha o aluno</p>

    <?php if (empty($alunosDisponiveis)): ?>
        <div class="text-center py-8">
            <p class="text-gray-500 font-medium">Nenhum aluno vinculado às suas turmas</p>
            <p class="text-sm text-gray-400 mt-2">Confira se suas turmas estão cadastradas no perfil do professor.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label for="filtro-turma-chat" class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
                <select id="filtro-turma-chat" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Todas as turmas</option>
                    <?php foreach ($turmasFiltro as $turmaId => $turmaNome): ?>
                        <option value="<?= (int) $turmaId ?>"><?= htmlspecialchars($turmaNome) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="filtro-nome-chat" class="block text-sm font-medium text-gray-700 mb-1">Buscar aluno</label>
                <input type="search"
                       id="filtro-nome-chat"
                       placeholder="Nome ou RA..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       autocomplete="off">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="md:col-span-2">
                <label for="select-aluno-chat" class="block text-sm font-medium text-gray-700 mb-1">Aluno</label>
                <select id="select-aluno-chat" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione um aluno</option>
                </select>
                <p id="filtro-alunos-vazio" class="hidden text-sm text-amber-600 mt-2">Nenhum aluno encontrado com esses filtros.</p>
            </div>
            <div>
                <button type="button"
                        id="btn-abrir-chat"
                        disabled
                        class="w-full px-4 py-2 rounded-lg text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                    Abrir conversa
                </button>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3"><span id="contador-alunos-filtro"><?= count($alunosDisponiveis) ?></span> aluno(s) disponível(is)</p>
    <?php endif; ?>
</div>

<!-- Conversas Existentes -->
<div class="bg-white rounded-xl shadow-lg w-full">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Conversas recentes</h2>
    </div>
    <div class="p-6">
        <?php if (empty($conversas)): ?>
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <p class="text-gray-500 text-lg font-medium">Nenhuma conversa ainda</p>
                <p class="text-sm text-gray-400 mt-2">Use os filtros acima para iniciar</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($conversas as $conversa): ?>
                    <a href="<?= URL ?>/professor/chat/<?= (int) $conversa['aluno_id'] ?>"
                       class="flex items-center justify-between py-3 px-1 hover:bg-gray-50 rounded-lg transition-colors <?= ($conversa['mensagens_nao_lidas'] ?? 0) > 0 ? 'bg-purple-50/40' : '' ?>">
                        <div class="flex items-center flex-1 min-w-0">
                            <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center mr-3 shrink-0">
                                <span class="text-white font-semibold text-sm">
                                    <?= htmlspecialchars(mb_strtoupper(mb_substr((string) ($conversa['aluno_nome'] ?? '?'), 0, 1))) ?>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-medium text-gray-900 truncate text-sm"><?= htmlspecialchars($conversa['aluno_nome'] ?? '') ?></h3>
                                    <?php if (($conversa['mensagens_nao_lidas'] ?? 0) > 0): ?>
                                        <span class="px-1.5 py-0.5 bg-red-500 text-white text-[10px] rounded-full font-medium">
                                            <?= (int) $conversa['mensagens_nao_lidas'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500 truncate">
                                    <?= htmlspecialchars($conversa['turma_nome'] ?? '') ?>
                                    <?php if (!empty($conversa['ultima_mensagem'])): ?>
                                        · <?= htmlspecialchars(mb_substr($conversa['ultima_mensagem'], 0, 50)) ?><?= mb_strlen($conversa['ultima_mensagem']) > 50 ? '…' : '' ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($alunosDisponiveis)): ?>
<script>
(function () {
    var alunos = <?= json_encode($alunosJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;
    var baseUrl = <?= json_encode(rtrim(URL, '/') . '/professor/chat/', JSON_UNESCAPED_SLASHES) ?>;
    var selTurma = document.getElementById('filtro-turma-chat');
    var inpNome = document.getElementById('filtro-nome-chat');
    var selAluno = document.getElementById('select-aluno-chat');
    var btn = document.getElementById('btn-abrir-chat');
    var vazio = document.getElementById('filtro-alunos-vazio');
    var contador = document.getElementById('contador-alunos-filtro');

    function normaliza(s) {
        return (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function filtrados() {
        var turma = selTurma.value;
        var q = normaliza(inpNome.value).trim();
        return alunos.filter(function (a) {
            if (turma && String(a.turma_id) !== turma) return false;
            if (!q) return true;
            var blob = normaliza(a.nome + ' ' + a.ra);
            return blob.indexOf(q) !== -1;
        });
    }

    function render() {
        var lista = filtrados();
        var atual = selAluno.value;
        selAluno.innerHTML = '';
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = lista.length ? 'Selecione um aluno' : 'Nenhum aluno neste filtro';
        selAluno.appendChild(opt0);

        lista.sort(function (a, b) {
            return a.nome.localeCompare(b.nome, 'pt-BR');
        }).forEach(function (a) {
            var opt = document.createElement('option');
            opt.value = String(a.id);
            opt.textContent = a.nome + (a.turma_nome ? ' (' + a.turma_nome + ')' : '') + (a.ra ? ' · ' + a.ra : '');
            selAluno.appendChild(opt);
        });

        if (atual && lista.some(function (a) { return String(a.id) === atual; })) {
            selAluno.value = atual;
        }
        btn.disabled = !selAluno.value;
        if (vazio) vazio.classList.toggle('hidden', lista.length > 0);
        if (contador) contador.textContent = String(lista.length);
    }

    selTurma.addEventListener('change', render);
    inpNome.addEventListener('input', render);
    selAluno.addEventListener('change', function () {
        btn.disabled = !selAluno.value;
    });
    btn.addEventListener('click', function () {
        if (!selAluno.value) return;
        window.location.href = baseUrl + encodeURIComponent(selAluno.value);
    });

    render();
})();
</script>
<?php endif; ?>
