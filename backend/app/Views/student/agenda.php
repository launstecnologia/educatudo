<?php
$eventos = $eventos ?? [];
$mes = (int) ($mes ?? date('n'));
$ano = (int) ($ano ?? date('Y'));

$tipoLabels = [
    'prova' => 'Prova',
    'jornada' => 'Jornada do Aluno',
    'redacao' => 'Jornada da Redação',
    'escola' => 'Evento da escola',
    'pessoal' => 'Meu item',
];

$mesesNomes = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

// Agrupa eventos por dia (data => lista de eventos)
$eventosPorDia = [];
foreach ($eventos as $ev) {
    $eventosPorDia[$ev['data']][] = $ev;
}

$mesAnterior = $mes - 1;
$anoAnterior = $ano;
if ($mesAnterior < 1) {
    $mesAnterior = 12;
    $anoAnterior--;
}
$mesProximo = $mes + 1;
$anoProximo = $ano;
if ($mesProximo > 12) {
    $mesProximo = 1;
    $anoProximo++;
}

$primeiroDia = mktime(0, 0, 0, $mes, 1, $ano);
$diasNoMes = (int) date('t', $primeiroDia);
$offset = (int) date('N', $primeiroDia) % 7;
$hoje = date('Y-m-d');
$csrfToken = $csrf_token ?? $this->generateCsrfToken();
?>

<div class="mb-6">
    <div class="flex flex-wrap items-center gap-2">
        <h1 class="text-2xl font-bold text-gray-900 flex-1">Agenda</h1>
        <button type="button" onclick="abrirFormItem()" class="btn-ai-primary px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Adicionar
        </button>
    </div>
    <p class="text-sm text-gray-500 mt-1">Provas, jornadas, redação, eventos da escola e seus itens pessoais, tudo num só lugar.</p>
</div>

<!-- Legenda -->
<div class="flex flex-wrap gap-3 mb-5">
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-red-50 text-red-700"><span class="w-2 h-2 rounded-full" style="background:#ef4444"></span>Prova</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-blue-50 text-blue-700"><span class="w-2 h-2 rounded-full" style="background:#3b82f6"></span>Jornada do Aluno</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-purple-50 text-purple-700"><span class="w-2 h-2 rounded-full" style="background:#a855f7"></span>Jornada da Redação</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-green-50 text-green-700"><span class="w-2 h-2 rounded-full" style="background:#22c55e"></span>Evento da escola</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-yellow-50 text-yellow-700"><span class="w-2 h-2 rounded-full" style="background:#eab308"></span>Meu item</span>
</div>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
        <a href="?mes=<?= $mesAnterior ?>&ano=<?= $anoAnterior ?>" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <span class="text-base font-bold text-gray-900"><?= $mesesNomes[$mes - 1] ?> de <?= $ano ?></span>
        <a href="?mes=<?= $mesProximo ?>&ano=<?= $anoProximo ?>" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </a>
    </div>
    <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50">
        <?php foreach (['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $d): ?>
        <div class="py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= $d ?></div>
        <?php endforeach; ?>
    </div>
    <div class="grid grid-cols-7 divide-x divide-y divide-gray-100">
        <?php for ($i = 0; $i < $offset; $i++): ?>
        <div class="min-h-[90px] bg-gray-50/50"></div>
        <?php endfor; ?>
        <?php for ($dia = 1; $dia <= $diasNoMes; $dia++):
            $dataKey = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
            $evsDia = $eventosPorDia[$dataKey] ?? [];
            $isHoje = ($dataKey === $hoje);
        ?>
        <div class="min-h-[90px] p-1.5 flex flex-col bg-white" <?= $isHoje ? 'style="box-shadow: inset 0 0 0 2px var(--primary-color, #a855f7);"' : '' ?>>
            <span class="text-xs font-semibold inline-flex items-center justify-center w-6 h-6 rounded-full self-end <?= $isHoje ? 'text-white' : 'text-gray-600' ?>" <?= $isHoje ? 'style="background-color: var(--primary-color, #a855f7);"' : '' ?>><?= $dia ?></span>
            <?php foreach (array_slice($evsDia, 0, 3) as $ev): ?>
                <button type="button" onclick="abrirDetalheEvento(<?= htmlspecialchars(json_encode($ev), ENT_QUOTES) ?>)" class="text-left text-[11px] rounded px-1 py-0.5 mt-0.5 truncate font-medium hover:opacity-80" style="background-color:<?= $ev['cor'] ?>22; color:<?= $ev['cor'] ?>;">
                    <?= htmlspecialchars($ev['titulo']) ?>
                </button>
            <?php endforeach; ?>
            <?php if (count($evsDia) > 3): ?>
                <span class="text-[10px] text-gray-400 mt-0.5">+<?= count($evsDia) - 3 ?> mais</span>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Modal: detalhe do evento -->
<div id="modalEvento" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900" id="modalEventoTitulo"></h3>
            <button type="button" onclick="fecharModalEvento()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="px-6 py-4 space-y-2">
            <p class="text-sm text-gray-500" id="modalEventoTipo"></p>
            <div class="text-sm text-gray-600 space-y-1" id="modalEventoDetalhes"></div>
            <p class="text-sm text-gray-700" id="modalEventoDescricao"></p>
        </div>
        <div class="px-6 pb-4 flex items-center justify-between gap-3">
            <a id="modalEventoLink" href="#" target="_blank" rel="noopener" class="text-accent text-sm font-medium hover:underline hidden">Abrir link</a>
            <div class="flex items-center gap-4 ml-auto">
                <button type="button" id="modalEventoEditar" onclick="editarItemPessoalAtual()" class="text-accent text-sm font-medium hover:underline hidden">Editar</button>
                <button type="button" id="modalEventoExcluir" onclick="excluirItemPessoalAtual()" class="text-red-500 text-sm font-medium hover:underline hidden">Excluir item</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: novo item pessoal -->
<div id="modalNovoItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden overflow-y-auto py-8">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900" id="modalNovoItemTitulo">Adicionar à agenda</h3>
            <button type="button" onclick="fecharFormItem()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form id="formNovoItem" class="px-6 py-4 space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título *</label>
                <input type="text" id="itemTitulo" required maxlength="255" placeholder="Ex: Simulado ENEM, Prova de Matemática..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-400 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                <select id="itemTipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-400 focus:border-transparent">
                    <?php foreach (\AgendaController::tiposItemPessoal() as $key => $t): ?>
                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($t['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data *</label>
                    <input type="date" id="itemData" required value="<?= sprintf('%04d-%02d-%02d', $ano, $mes, min((int) date('j'), $diasNoMes)) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-400 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hora</label>
                    <input type="time" id="itemHora" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-400 focus:border-transparent">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Banca / Instituição</label>
                <input type="text" id="itemBanca" maxlength="255" placeholder="Ex: ENEM, Fuvest, escola..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-400 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Local</label>
                <input type="text" id="itemLocal" maxlength="255" placeholder="Endereço, sala..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-400 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Link</label>
                <input type="url" id="itemLink" maxlength="500" placeholder="https://..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-400 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                <textarea id="itemDescricao" rows="3" maxlength="2000" placeholder="Detalhes, conteúdo cobrado..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-400 focus:border-transparent"></textarea>
            </div>
            <button type="submit" class="btn-ai-primary w-full py-2.5 rounded-lg font-medium transition-colors">Salvar</button>
        </form>
    </div>
</div>

<script>
var TIPO_LABELS = <?= json_encode($tipoLabels) ?>;
var CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
var itemPessoalAtualId = null;
var itemPessoalAtualDados = null;
var itemPessoalEmEdicaoId = null;

function abrirDetalheEvento(ev) {
    document.getElementById('modalEventoTitulo').textContent = ev.titulo;
    var rotuloTipo = ev.tipo === 'pessoal' && ev.tipo_pessoal_label ? ev.tipo_pessoal_label : (TIPO_LABELS[ev.tipo] || ev.tipo);
    document.getElementById('modalEventoTipo').textContent = rotuloTipo;
    document.getElementById('modalEventoDescricao').textContent = ev.descricao || '';

    // Monta a lista de detalhes (hora/banca/local) via DOM, não innerHTML —
    // esses campos vêm de texto que o próprio aluno digitou.
    var detalhesEl = document.getElementById('modalEventoDetalhes');
    detalhesEl.innerHTML = '';
    var linhas = [];
    if (ev.hora) linhas.push('Horário: ' + ev.hora);
    if (ev.banca) linhas.push('Banca/Instituição: ' + ev.banca);
    if (ev.local) linhas.push('Local: ' + ev.local);
    linhas.forEach(function (texto) {
        var p = document.createElement('p');
        p.textContent = texto;
        detalhesEl.appendChild(p);
    });

    var linkEl = document.getElementById('modalEventoLink');
    var urlEfetiva = ev.url || ev.link;
    if (urlEfetiva) {
        linkEl.href = urlEfetiva;
        linkEl.textContent = ev.url ? 'Ver detalhes' : 'Abrir link';
        linkEl.classList.remove('hidden');
    } else {
        linkEl.classList.add('hidden');
    }

    var excluirBtn = document.getElementById('modalEventoExcluir');
    var editarBtn = document.getElementById('modalEventoEditar');
    if (ev.tipo === 'pessoal' && ev.id) {
        itemPessoalAtualId = ev.id;
        itemPessoalAtualDados = ev;
        excluirBtn.classList.remove('hidden');
        editarBtn.classList.remove('hidden');
    } else {
        itemPessoalAtualId = null;
        itemPessoalAtualDados = null;
        excluirBtn.classList.add('hidden');
        editarBtn.classList.add('hidden');
    }

    document.getElementById('modalEvento').classList.remove('hidden');
}

function fecharModalEvento() {
    document.getElementById('modalEvento').classList.add('hidden');
}

function excluirItemPessoalAtual() {
    if (!itemPessoalAtualId) return;
    if (!confirm('Excluir este item da agenda?')) return;

    var fd = new FormData();
    fd.append('_token', CSRF_TOKEN);
    fetch('<?= URL ?>/agenda/item/' + itemPessoalAtualId + '/excluir', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Erro ao excluir item');
            }
        })
        .catch(function () { alert('Erro de conexão'); });
}

function abrirFormItem() {
    itemPessoalEmEdicaoId = null;
    document.getElementById('modalNovoItemTitulo').textContent = 'Adicionar à agenda';
    document.getElementById('formNovoItem').reset();
    document.getElementById('modalNovoItem').classList.remove('hidden');
}

function editarItemPessoalAtual() {
    if (!itemPessoalAtualId || !itemPessoalAtualDados) return;
    var ev = itemPessoalAtualDados;
    itemPessoalEmEdicaoId = itemPessoalAtualId;
    document.getElementById('modalNovoItemTitulo').textContent = 'Editar item';
    document.getElementById('itemTitulo').value = ev.titulo || '';
    document.getElementById('itemTipo').value = ev.tipo_pessoal || 'pessoal';
    document.getElementById('itemData').value = ev.data || '';
    document.getElementById('itemHora').value = ev.hora || '';
    document.getElementById('itemBanca').value = ev.banca || '';
    document.getElementById('itemLocal').value = ev.local || '';
    document.getElementById('itemLink').value = ev.link || '';
    document.getElementById('itemDescricao').value = ev.descricao || '';
    fecharModalEvento();
    document.getElementById('modalNovoItem').classList.remove('hidden');
}

function fecharFormItem() {
    itemPessoalEmEdicaoId = null;
    document.getElementById('modalNovoItem').classList.add('hidden');
}

document.getElementById('formNovoItem').addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData();
    fd.append('_token', CSRF_TOKEN);
    fd.append('titulo', document.getElementById('itemTitulo').value.trim());
    fd.append('tipo', document.getElementById('itemTipo').value);
    fd.append('data', document.getElementById('itemData').value);
    fd.append('hora', document.getElementById('itemHora').value);
    fd.append('banca', document.getElementById('itemBanca').value.trim());
    fd.append('local', document.getElementById('itemLocal').value.trim());
    fd.append('link', document.getElementById('itemLink').value.trim());
    fd.append('descricao', document.getElementById('itemDescricao').value.trim());

    var url = itemPessoalEmEdicaoId
        ? '<?= URL ?>/agenda/item/' + itemPessoalEmEdicaoId + '/editar'
        : '<?= URL ?>/agenda/item';

    fetch(url, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Erro ao salvar item');
            }
        })
        .catch(function () { alert('Erro de conexão'); });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        fecharModalEvento();
        fecharFormItem();
    }
});
document.getElementById('modalEvento').addEventListener('click', function (e) { if (e.target === this) fecharModalEvento(); });
document.getElementById('modalNovoItem').addEventListener('click', function (e) { if (e.target === this) fecharFormItem(); });
</script>
