<?php
/** @var array $turmas @var array $anos_letivos @var array $unidades @var string $csrf_token */
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$categorias = [
    'passeio'          => 'Passeio Escolar',
    'ingresso'         => 'Ingresso / Evento',
    'uniforme'         => 'Uniforme',
    'material_didatico'=> 'Material Didático',
    'taxa'             => 'Taxa',
    'evento'           => 'Evento',
    'outros'           => 'Outros',
];
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Cobrança em Lote</h2>
            <p class="text-sm text-gray-600">Crie cobranças avulsas para uma turma inteira ou para alunos selecionados.</p>
        </div>
    </div>
</div>

<form method="POST" action="<?= URL ?>/admin/finance/charges/batch" id="batchForm">
<input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">

<div class="space-y-5">

    <!-- ── 1. DADOS DA COBRANÇA ─────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">1. Dados da Cobrança</h3>
        </div>
        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Categoria <span class="text-red-500">*</span>
                    </label>
                    <select name="categoria" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <?php foreach ($categorias as $v => $l): ?>
                        <option value="<?= $v ?>"><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Ano Letivo
                    </label>
                    <select name="ano_letivo_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">— Sem vínculo —</option>
                        <?php foreach ($anos_letivos as $al): ?>
                        <option value="<?= (int)$al['id'] ?>" <?= ($al['ativo'] ?? 0) ? 'selected' : '' ?>>
                            <?= $esc($al['ano']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Descrição <span class="text-red-500">*</span>
                </label>
                <input type="text" name="descricao" required
                       placeholder="Ex: Passeio ao Museu de Ciências — 15/08/2026"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Valor (R$) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="valor" id="valorInput" required
                           placeholder="0,00" inputmode="numeric"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Data de Vencimento <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="data_vencimento" required
                           value="<?= date('Y-m-d', strtotime('+10 days')) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Empresa Emissora (NF)</label>
                    <select name="unidade_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">— Escola principal —</option>
                        <?php foreach ($unidades as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">
                            <?= $esc($u['nome']) ?>
                            <?= $u['razao_social'] ? ' (' . $esc($u['razao_social']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                <textarea name="observacoes" rows="2" placeholder="Informações adicionais para o responsável..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
            </div>
        </div>
    </div>

    <!-- ── 2. DESTINATÁRIOS ──────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">2. Para quem cobrar?</h3>
        </div>
        <div class="p-6 space-y-4">

            <!-- Modo seleção -->
            <div class="flex gap-3">
                <label class="flex-1 cursor-pointer">
                    <input type="radio" name="modo" value="turma" id="modoTurma" class="sr-only" checked>
                    <div class="modo-btn flex items-center gap-3 px-4 py-3 rounded-lg border-2 border-green-500 bg-green-50 transition-all">
                        <i class="fa-solid fa-users text-green-600"></i>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Turma inteira</p>
                            <p class="text-xs text-gray-500">Cobra de todos os alunos de uma turma</p>
                        </div>
                    </div>
                </label>
                <label class="flex-1 cursor-pointer">
                    <input type="radio" name="modo" value="individual" id="modoIndividual" class="sr-only">
                    <div class="modo-btn flex items-center gap-3 px-4 py-3 rounded-lg border-2 border-gray-200 bg-white transition-all">
                        <i class="fa-solid fa-user text-gray-400"></i>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Alunos específicos</p>
                            <p class="text-xs text-gray-500">Seleciona alunos individualmente</p>
                        </div>
                    </div>
                </label>
            </div>

            <!-- Painel: por turma -->
            <div id="painelTurma">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Selecione a turma <span class="text-red-500">*</span>
                </label>
                <select name="turma_id" id="turmaSelect"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        onchange="carregarAlunos(this.value)">
                    <option value="">Selecione...</option>
                    <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"><?= $esc($t['nome']) ?> <?= $t['serie'] ? '— ' . $esc($t['serie']) : '' ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Preview alunos da turma -->
                <div id="previewTurma" class="hidden mt-3 border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos da turma</span>
                        <div class="flex gap-2">
                            <button type="button" onclick="toggleAll(true)"
                                    class="text-xs text-blue-600 hover:underline">Marcar todos</button>
                            <span class="text-gray-300">|</span>
                            <button type="button" onclick="toggleAll(false)"
                                    class="text-xs text-blue-600 hover:underline">Desmarcar todos</button>
                        </div>
                    </div>
                    <div id="listaAlunosTurma" class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                        <!-- preenchido via JS -->
                    </div>
                    <div class="px-4 py-2 bg-gray-50 border-t border-gray-200">
                        <span id="contadorSelecionados" class="text-xs text-gray-500">0 alunos selecionados</span>
                    </div>
                </div>
            </div>

            <!-- Painel: individual -->
            <div id="painelIndividual" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar aluno</label>
                <div class="relative">
                    <input type="text" id="buscaAluno" placeholder="Digite o nome ou RA..."
                           class="w-full px-3 py-2 pl-9 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           autocomplete="off">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                    <div id="sugestoesAluno" class="hidden absolute z-20 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto"></div>
                </div>

                <!-- Alunos selecionados individualmente -->
                <div id="alunosSelecionados" class="mt-3 space-y-2 hidden">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Selecionados</p>
                    <div id="chipsAlunos" class="flex flex-wrap gap-2"></div>
                    <input type="hidden" name="aluno_ids_raw" id="alunoIdsRaw" value="">
                </div>
            </div>

        </div>
    </div>

    <!-- ── RESUMO + SUBMIT ────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div id="resumo" class="text-sm text-gray-600">
                Preencha os campos acima para ver o resumo.
            </div>
            <div class="flex gap-3 flex-shrink-0">
                <a href="<?= URL ?>/admin/finance"
                   class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Cancelar
                </a>
                <button type="submit" id="btnSubmit"
                        class="inline-flex items-center justify-center px-5 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    <i class="fa-solid fa-paper-plane mr-2"></i>
                    Gerar Cobranças
                </button>
            </div>
        </div>
    </div>

</div>
</form>

<script>
// ── Modo de seleção ──────────────────────────────────────────────────────────
document.querySelectorAll('input[name="modo"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const isTurma = radio.value === 'turma';
        document.getElementById('painelTurma').classList.toggle('hidden', !isTurma);
        document.getElementById('painelIndividual').classList.toggle('hidden', isTurma);

        // Atualiza estilo dos botões de modo
        document.querySelectorAll('.modo-btn').forEach((btn, i) => {
            const sel = (i === 0) === isTurma;
            btn.classList.toggle('border-green-500', sel);
            btn.classList.toggle('bg-green-50', sel);
            btn.classList.toggle('border-gray-200', !sel);
            btn.classList.toggle('bg-white', !sel);
            btn.querySelector('i').classList.toggle('text-green-600', sel);
            btn.querySelector('i').classList.toggle('text-gray-400', !sel);
        });

        atualizarResumo();
    });
});

// ── Carrega alunos da turma ──────────────────────────────────────────────────
async function carregarAlunos(turmaId) {
    if (!turmaId) {
        document.getElementById('previewTurma').classList.add('hidden');
        return;
    }
    const res = await fetch(`<?= URL ?>/admin/alunos/search?turma_id=${turmaId}&limit=200`);
    const alunos = await res.json();
    const lista = document.getElementById('listaAlunosTurma');
    lista.innerHTML = alunos.length === 0
        ? '<p class="px-4 py-3 text-sm text-gray-400">Nenhum aluno nesta turma.</p>'
        : alunos.map(a => `
            <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                <input type="checkbox" name="aluno_ids[]" value="${a.id}" checked
                       class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                       onchange="atualizarContador()">
                <span class="text-sm text-gray-800">${esc(a.nome)}</span>
                <span class="text-xs text-gray-400 ml-auto">${esc(a.ra || '')}</span>
            </label>`).join('');
    document.getElementById('previewTurma').classList.remove('hidden');
    atualizarContador();
    atualizarResumo();
}

function toggleAll(checked) {
    document.querySelectorAll('#listaAlunosTurma input[type="checkbox"]').forEach(cb => cb.checked = checked);
    atualizarContador();
    atualizarResumo();
}

function atualizarContador() {
    const n = document.querySelectorAll('#listaAlunosTurma input:checked').length;
    document.getElementById('contadorSelecionados').textContent = `${n} aluno(s) selecionado(s)`;
    atualizarResumo();
}

// ── Busca individual ─────────────────────────────────────────────────────────
const alunosSel = {};

document.getElementById('buscaAluno').addEventListener('input', async function() {
    const q = this.value.trim();
    const box = document.getElementById('sugestoesAluno');
    if (q.length < 2) { box.classList.add('hidden'); return; }
    const res = await fetch(`<?= URL ?>/admin/alunos/search?q=${encodeURIComponent(q)}`);
    const alunos = await res.json();
    box.innerHTML = alunos.length === 0
        ? '<p class="px-4 py-3 text-sm text-gray-400">Nenhum resultado.</p>'
        : alunos.map(a => `
            <button type="button" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-sm flex justify-between"
                    onclick="adicionarAluno(${a.id}, '${esc(a.nome)}', '${esc(a.ra || '')}')">
                <span>${esc(a.nome)}</span>
                <span class="text-gray-400 text-xs">${esc(a.ra || '')}</span>
            </button>`).join('');
    box.classList.remove('hidden');
});

document.addEventListener('click', e => {
    if (!e.target.closest('#buscaAluno') && !e.target.closest('#sugestoesAluno'))
        document.getElementById('sugestoesAluno').classList.add('hidden');
});

function adicionarAluno(id, nome, ra) {
    if (alunosSel[id]) return;
    alunosSel[id] = {nome, ra};
    document.getElementById('buscaAluno').value = '';
    document.getElementById('sugestoesAluno').classList.add('hidden');
    renderChips();
    atualizarResumo();
}

function removerAluno(id) {
    delete alunosSel[id];
    renderChips();
    atualizarResumo();
}

function renderChips() {
    const ids = Object.keys(alunosSel);
    const wrap = document.getElementById('alunosSelecionados');
    const chips = document.getElementById('chipsAlunos');
    wrap.classList.toggle('hidden', ids.length === 0);
    chips.innerHTML = ids.map(id => `
        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 border border-blue-200 text-blue-800 text-xs font-medium rounded-full">
            ${esc(alunosSel[id].nome)}
            <button type="button" onclick="removerAluno(${id})" class="text-blue-400 hover:text-blue-700 leading-none">&times;</button>
        </span>`).join('');
    document.getElementById('alunoIdsRaw').value = ids.join(',');
}

// ── Máscara BRL ──────────────────────────────────────────────────────────────
document.getElementById('valorInput').addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '');
    if (!v) { this.value = ''; return; }
    v = (parseInt(v) / 100).toFixed(2);
    this.value = parseFloat(v).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    atualizarResumo();
});

// ── Resumo ───────────────────────────────────────────────────────────────────
function atualizarResumo() {
    const modo = document.querySelector('input[name="modo"]:checked').value;
    let qtd = 0;
    if (modo === 'turma') {
        qtd = document.querySelectorAll('#listaAlunosTurma input:checked').length;
    } else {
        qtd = Object.keys(alunosSel).length;
    }
    const valor = parseFloat(document.getElementById('valorInput').value.replace(/\./g, '').replace(',', '.')) || 0;
    const total = (qtd * valor).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const resumo = document.getElementById('resumo');
    if (qtd > 0 && valor > 0) {
        resumo.innerHTML = `<span class="font-semibold text-gray-900">${qtd} aluno(s)</span> &times; <span class="font-semibold text-gray-900">R$ ${document.getElementById('valorInput').value}</span> = <span class="font-semibold text-green-700">R$ ${total} total</span>`;
    } else {
        resumo.textContent = 'Preencha os campos acima para ver o resumo.';
    }
}

// ── Validação antes do submit ────────────────────────────────────────────────
document.getElementById('batchForm').addEventListener('submit', function(e) {
    const modo = document.querySelector('input[name="modo"]:checked').value;
    if (modo === 'individual') {
        const ids = Object.keys(alunosSel);
        if (ids.length === 0) {
            e.preventDefault();
            alert('Selecione ao menos um aluno.');
            return;
        }
        document.getElementById('alunoIdsRaw').value = ids.join(',');
    } else {
        const checados = document.querySelectorAll('#listaAlunosTurma input:checked');
        if (checados.length === 0) {
            e.preventDefault();
            alert('Selecione ao menos um aluno da turma.');
            return;
        }
    }
});

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
