<?php
$totalPages = max(1, (int) ceil(($total ?? 0) / ($limit ?? 30)));
$page = max(1, (int) ($page ?? 1));
?>

<div class="max-w-7xl mx-auto space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <h2 class="text-xl font-semibold text-gray-800">Banco de Questões</h2>
        <p class="text-sm text-gray-500 mt-1">Importe questões da API, selecione e monte listas para usar com os alunos.</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <form method="post" action="<?= URL ?>/professor/questoes/importar" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-sm text-gray-700 mb-1">Matéria (opcional)</label>
                <select name="materia" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todas</option>
                    <?php foreach (($materias ?? []) as $row): ?>
                        <?php $m = (string) ($row['materia'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= (($filtro_materia ?? '') === $m) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m) ?><?= isset($row['total']) ? ' (' . (int) $row['total'] . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Tipo (opcional)</label>
                <select name="tipo" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todos</option>
                    <?php foreach (($tipos ?? []) as $row): ?>
                        <?php $t = (string) ($row['tipo'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= (($filtro_tipo ?? '') === $t) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?><?= isset($row['total']) ? ' (' . (int) $row['total'] . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Ano (opcional)</label>
                <select name="ano" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todos</option>
                    <?php foreach (($anos ?? []) as $row): ?>
                        <?php $v = (string) ($row['valor'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= (($filtro_ano ?? '') === $v) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v) ?> (<?= (int) ($row['total'] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2 flex items-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 font-medium">
                    Importar da API para o banco
                </button>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Dificuldade (opcional)</label>
                <select name="dificuldade" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todas</option>
                    <?php foreach (($dificuldades ?? []) as $row): ?>
                        <?php $v = (string) ($row['valor'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= (($filtro_dificuldade ?? '') === $v) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v) ?> (<?= (int) ($row['total'] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Tópico (opcional)</label>
                <select name="topico" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todos</option>
                    <?php foreach (($topicos ?? []) as $row): ?>
                        <?php $v = (string) ($row['valor'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= (($filtro_topico ?? '') === $v) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v) ?> (<?= (int) ($row['total'] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Tag (opcional)</label>
                <select name="tag" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todas</option>
                    <?php foreach (($tags ?? []) as $row): ?>
                        <?php $v = (string) ($row['valor'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= (($filtro_tag ?? '') === $v) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v) ?> (<?= (int) ($row['total'] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Origem/Título (opcional)</label>
                <select name="origem_titulo" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todas</option>
                    <?php foreach (($origens_titulo ?? []) as $row): ?>
                        <?php $v = (string) ($row['valor'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= (($filtro_origem_titulo ?? '') === $v) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v) ?> (<?= (int) ($row['total'] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-4">
                <label class="block text-sm text-gray-700 mb-1">Busca API (opcional)</label>
                <input type="text" name="q" value="<?= htmlspecialchars((string) ($filtro_q ?? '')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Termo livre (q)">
            </div>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <form method="get" action="<?= URL ?>/professor/questoes" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-sm text-gray-700 mb-1">Filtro matéria</label>
                <select name="materia" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todas</option>
                    <?php foreach (($materias ?? []) as $row): ?>
                        <?php $m = (string) ($row['materia'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= (($filtro_materia ?? '') === $m) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m) ?><?= isset($row['total']) ? ' (' . (int) $row['total'] . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Filtro tipo</label>
                <select name="tipo" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todos</option>
                    <?php foreach (($tipos ?? []) as $row): ?>
                        <?php $t = (string) ($row['tipo'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= (($filtro_tipo ?? '') === $t) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?><?= isset($row['total']) ? ' (' . (int) $row['total'] . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Busca</label>
                <input type="text" name="q" value="<?= htmlspecialchars((string) ($filtro_q ?? '')) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="ID ou texto do enunciado">
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 font-medium">Filtrar</button>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Ano</label>
                <select name="ano" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todos</option>
                    <?php foreach (($anos ?? []) as $row): ?>
                        <?php $v = (string) ($row['valor'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= (($filtro_ano ?? '') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?> (<?= (int) ($row['total'] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Dificuldade</label>
                <select name="dificuldade" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todas</option>
                    <?php foreach (($dificuldades ?? []) as $row): ?>
                        <?php $v = (string) ($row['valor'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= (($filtro_dificuldade ?? '') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?> (<?= (int) ($row['total'] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Tópico</label>
                <select name="topico" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todos</option>
                    <?php foreach (($topicos ?? []) as $row): ?>
                        <?php $v = (string) ($row['valor'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= (($filtro_topico ?? '') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?> (<?= (int) ($row['total'] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Tag</label>
                <select name="tag" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todas</option>
                    <?php foreach (($tags ?? []) as $row): ?>
                        <?php $v = (string) ($row['valor'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= (($filtro_tag ?? '') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?> (<?= (int) ($row['total'] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-4">
                <label class="block text-sm text-gray-700 mb-1">Origem/Título</label>
                <select name="origem_titulo" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Todas</option>
                    <?php foreach (($origens_titulo ?? []) as $row): ?>
                        <?php $v = (string) ($row['valor'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= (($filtro_origem_titulo ?? '') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($v) ?> (<?= (int) ($row['total'] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <?php if (isset($facets_total_filtrado) && $facets_total_filtrado !== null): ?>
            <div class="mt-3 text-sm text-gray-600">
                Total filtrado na API (facets): <strong><?= (int) $facets_total_filtrado ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <form id="form-questoes" method="post" class="space-y-4">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 flex flex-wrap items-center gap-3">
            <input type="text" name="titulo" class="border border-gray-300 rounded-lg px-3 py-2" placeholder="Título da lista">
            <button type="button" id="btn-marcar-todas" class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Marcar página</button>
            <button type="button" id="btn-desmarcar-todas" class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Desmarcar</button>
            <button type="submit" formaction="<?= URL ?>/professor/questoes/montagens/salvar" formmethod="post" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 font-medium">Salvar lista selecionada</button>
            <button type="submit" formaction="<?= URL ?>/professor/questoes/pdf/selecionadas" formmethod="post" class="px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-700 font-medium">Baixar PDF selecionadas</button>
            <span class="text-sm text-gray-500">Total no banco: <strong><?= (int) ($total ?? 0) ?></strong></span>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-2 text-left"></th>
                        <th class="px-3 py-2 text-left">ID externo</th>
                        <th class="px-3 py-2 text-left">Matéria</th>
                        <th class="px-3 py-2 text-left">Tipo</th>
                        <th class="px-3 py-2 text-left">Nível</th>
                        <th class="px-3 py-2 text-left">Enunciado</th>
                        <th class="px-3 py-2 text-left">Gabarito</th>
                        <th class="px-3 py-2 text-left">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($questoes)): ?>
                        <tr><td colspan="8" class="px-3 py-8 text-center text-gray-500">Nenhuma questão encontrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($questoes as $q): ?>
                            <?php
                            $nivel = (string)($q['nivel_dificuldade'] ?? '');
                            $nivelLabel = ['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil'][$nivel] ?? $nivel;
                            $nivelClass = ['facil' => 'bg-emerald-100 text-emerald-800', 'medio' => 'bg-amber-100 text-amber-800', 'dificil' => 'bg-red-100 text-red-800'][$nivel] ?? 'bg-gray-100 text-gray-700';
                            ?>
                            <tr class="border-b border-gray-100 align-top">
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="questao_ids[]" value="<?= (int) $q['id'] ?>" class="checkbox-questao w-4 h-4">
                                </td>
                                <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars((string) ($q['external_id'] ?? '')) ?></td>
                                <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars((string) ($q['materia'] ?? '')) ?></td>
                                <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars((string) ($q['tipo'] ?? '')) ?></td>
                                <td class="px-3 py-2">
                                    <?php if ($nivelLabel !== ''): ?>
                                        <span class="inline-flex rounded px-2 py-1 text-xs font-medium <?= $nivelClass ?>"><?= htmlspecialchars($nivelLabel) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-gray-800">
                                    <?php if (!empty($q['titulo']) || !empty($q['assunto']) || !empty($q['origem'])): ?>
                                        <div class="mb-2 space-y-1">
                                            <?php if (!empty($q['titulo'])): ?>
                                                <div class="font-semibold text-gray-900"><?= htmlspecialchars((string)$q['titulo']) ?></div>
                                            <?php endif; ?>
                                            <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                                                <?php if (!empty($q['assunto'])): ?><span>Assunto: <?= htmlspecialchars((string)$q['assunto']) ?></span><?php endif; ?>
                                                <?php if (!empty($q['origem'])): ?><span>Origem: <?= htmlspecialchars((string)$q['origem']) ?></span><?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="max-h-24 overflow-auto prose prose-sm max-w-none">
                                        <?= (string) ($q['enunciado_html'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-emerald-700 font-semibold"><?= htmlspecialchars((string) ($q['gabarito'] ?? '')) ?></td>
                                <td class="px-3 py-2">
                                    <?php
                                    $alternativasRaw = (string) ($q['alternativas_json'] ?? '');
                                    $alternativasDecoded = json_decode($alternativasRaw, true);
                                    $alternativasSafe = is_array($alternativasDecoded) ? json_encode($alternativasDecoded, JSON_UNESCAPED_UNICODE) : '{}';
                                    ?>
                                    <button type="button"
                                            class="btn-visualizar px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs"
                                            data-id="<?= (int) ($q['id'] ?? 0) ?>"
                                            data-external-id="<?= htmlspecialchars((string) ($q['external_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-materia="<?= htmlspecialchars((string) ($q['materia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-tipo="<?= htmlspecialchars((string) ($q['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-enunciado="<?= htmlspecialchars((string) ($q['enunciado_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-gabarito="<?= htmlspecialchars((string) ($q['gabarito'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-alternativas="<?= htmlspecialchars((string) $alternativasSafe, ENT_QUOTES, 'UTF-8') ?>">
                                        Visualizar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <div class="flex items-center justify-between">
        <div class="text-sm text-gray-500">Página <?= $page ?> de <?= $totalPages ?></div>
        <div class="flex gap-2">
            <?php
            $base = URL . '/professor/questoes?materia=' . urlencode((string) ($filtro_materia ?? ''))
                . '&tipo=' . urlencode((string) ($filtro_tipo ?? ''))
                . '&ano=' . urlencode((string) ($filtro_ano ?? ''))
                . '&origem_titulo=' . urlencode((string) ($filtro_origem_titulo ?? ''))
                . '&dificuldade=' . urlencode((string) ($filtro_dificuldade ?? ''))
                . '&topico=' . urlencode((string) ($filtro_topico ?? ''))
                . '&tag=' . urlencode((string) ($filtro_tag ?? ''))
                . '&q=' . urlencode((string) ($filtro_q ?? ''));
            ?>
            <a class="px-3 py-2 border rounded-lg <?= $page <= 1 ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50' ?>" href="<?= $base ?>&page=<?= max(1, $page - 1) ?>">Anterior</a>
            <a class="px-3 py-2 border rounded-lg <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : 'hover:bg-gray-50' ?>" href="<?= $base ?>&page=<?= min($totalPages, $page + 1) ?>">Próxima</a>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Listas montadas</h3>
        <?php if (empty($montagens)): ?>
            <p class="text-sm text-gray-500">Nenhuma lista montada ainda.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($montagens as $m): ?>
                    <div class="flex items-center justify-between border border-gray-200 rounded-lg p-3">
                        <div>
                            <div class="font-medium text-gray-800"><?= htmlspecialchars((string) ($m['titulo'] ?? '')) ?></div>
                            <div class="text-xs text-gray-500"><?= (int) ($m['total_itens'] ?? 0) ?> questão(ões) • <?= htmlspecialchars((string) ($m['created_at'] ?? '')) ?></div>
                        </div>
                        <a href="<?= URL ?>/professor/questoes/montagens/<?= (int) $m['id'] ?>/pdf" class="px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">Baixar PDF</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="modal-questao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 px-4">
    <div class="bg-white w-full max-w-3xl rounded-xl shadow-xl border border-gray-200">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Visualizar questão</h3>
            <button type="button" id="modal-fechar" class="text-gray-500 hover:text-gray-700">Fechar</button>
        </div>
        <div class="p-5 space-y-4 max-h-[80vh] overflow-y-auto">
            <div class="text-sm text-gray-600">
                <span><strong>ID:</strong> <span id="m-id"></span></span>
                <span class="mx-2">|</span>
                <span><strong>Matéria:</strong> <span id="m-materia"></span></span>
                <span class="mx-2">|</span>
                <span><strong>Tipo:</strong> <span id="m-tipo"></span></span>
            </div>
            <div>
                <h4 class="font-semibold text-gray-800 mb-2">Enunciado</h4>
                <div id="m-enunciado" class="prose prose-sm max-w-none text-gray-800 border border-gray-200 rounded-lg p-3"></div>
            </div>
            <div>
                <h4 class="font-semibold text-gray-800 mb-2">Alternativas</h4>
                <ul id="m-alternativas" class="space-y-2 text-sm text-gray-800"></ul>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 text-emerald-700">
                <strong>Gabarito:</strong> <span id="m-gabarito"></span>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-marcar-todas')?.addEventListener('click', function () {
    document.querySelectorAll('.checkbox-questao').forEach(function (el) { el.checked = true; });
});
document.getElementById('btn-desmarcar-todas')?.addEventListener('click', function () {
    document.querySelectorAll('.checkbox-questao').forEach(function (el) { el.checked = false; });
});

const modal = document.getElementById('modal-questao');
const modalFechar = document.getElementById('modal-fechar');
const mId = document.getElementById('m-id');
const mMateria = document.getElementById('m-materia');
const mTipo = document.getElementById('m-tipo');
const mEnunciado = document.getElementById('m-enunciado');
const mAlternativas = document.getElementById('m-alternativas');
const mGabarito = document.getElementById('m-gabarito');

document.querySelectorAll('.btn-visualizar').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const externalId = btn.dataset.externalId || '';
        const materia = btn.dataset.materia || '';
        const tipo = btn.dataset.tipo || '';
        const enunciado = btn.dataset.enunciado || '';
        const gabarito = btn.dataset.gabarito || '';
        const alternativasRaw = btn.dataset.alternativas || '{}';

        mId.textContent = externalId;
        mMateria.textContent = materia;
        mTipo.textContent = tipo;
        mEnunciado.innerHTML = enunciado;
        mGabarito.textContent = gabarito || '-';

        mAlternativas.innerHTML = '';
        let alternativas = {};
        try {
            alternativas = JSON.parse(alternativasRaw);
        } catch (e) {
            alternativas = {};
        }
        const keys = Object.keys(alternativas);
        if (keys.length === 0) {
            const li = document.createElement('li');
            li.className = 'text-gray-500';
            li.textContent = 'Sem alternativas cadastradas.';
            mAlternativas.appendChild(li);
        } else {
            keys.forEach(function (k) {
                const li = document.createElement('li');
                const val = (alternativas[k] ?? '').toString();
                li.className = 'border border-gray-200 rounded-lg p-2';
                li.innerHTML = '<strong>' + k + ')</strong> ' + val;
                mAlternativas.appendChild(li);
            });
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    });
});

function fecharModalQuestao() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

modalFechar?.addEventListener('click', fecharModalQuestao);
modal?.addEventListener('click', function (e) {
    if (e.target === modal) fecharModalQuestao();
});
</script>
