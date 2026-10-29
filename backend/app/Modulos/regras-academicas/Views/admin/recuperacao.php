<?php
$itens = is_array($itens ?? null) ? $itens : [];
$anos = is_array($anos ?? null) ? $anos : [];
$ano = (int) ($ano ?? 0);
$quandos = is_array($quandos ?? null) ? $quandos : [];
$rotulosQuando = is_array($rotulos_quando ?? null) ? $rotulos_quando : $quandos;
$ondeLanca = is_array($onde_lanca ?? null) ? $onde_lanca : [];
$aplicaOpcoes = is_array($aplica ?? null) ? $aplica : [];
$aplicaAjuda = (string) ($aplica_ajuda ?? '');
$ajudaRecuperacao = (string) ($ajuda_recuperacao ?? '');
$efeitos = is_array($efeitos ?? null) ? $efeitos : [];
$schemaPronto = !empty($schema_pronto);
$podeCadastrar = !empty($pode_cadastrar);
$podeAlterar = !empty($pode_alterar);
$podeExcluir = !empty($pode_excluir);
$csrf_token = (string) ($csrf_token ?? '');

$page_header_title = 'Recuperação';
$page_header_subtitle = 'Cada linha é uma forma de recuperação deste ano. Os cards de Eventos Avaliativos seguem esta lista.';
ob_start();
?>
<?php if ($podeCadastrar && $schemaPronto): ?>
<button type="button" onclick="abrirRecuperacao()"
        class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova recuperação
</button>
<?php endif; ?>
<?php
$page_header_actions = ob_get_clean();
include \App\Core\ViewPaths::adminPartial('page_header_list.php');
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code class="text-sm">2026_10_27_recuperacoes.sql</code> no painel Master antes de cadastrar.
</div>
<?php endif; ?>

<form method="GET" action="<?= URL ?>/admin/regras-academicas/recuperacao" class="mb-6 flex items-end gap-3">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="filtro-ano">Ano letivo</label>
        <select name="ano" id="filtro-ano" onchange="this.form.submit()"
                class="w-32 pl-4 py-2 border border-gray-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <?php foreach ($anos as $opcao): ?>
                <option value="<?= (int) $opcao ?>" <?= (int) $opcao === $ano ? 'selected' : '' ?>><?= (int) $opcao ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quando</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quem faz</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nota máxima</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Efeito na nota</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if ($itens === []): ?>
            <tr>
                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Nenhuma recuperação neste ano.</td>
            </tr>
            <?php else: ?>
                <?php foreach ($itens as $item): ?>
                    <?php
                        $id = (int) ($item['id'] ?? 0);
                        $quando = (string) ($item['quando'] ?? '');
                        $efeito = (string) ($item['efeito'] ?? '');
                        $minima = $item['nota_minima'] !== null ? number_format((float) $item['nota_minima'], 2, ',', '.') : '—';
                        $maxima = $item['nota_maxima'] !== null ? number_format((float) $item['nota_maxima'], 2, ',', '.') : '—';
                    ?>
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($item['nome'] ?? '')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?= htmlspecialchars((string) ($rotulosQuando[$quando] ?? $quando)) ?>
                            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars((string) ($ondeLanca[$quando] ?? '')) ?></p>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            Abaixo de <?= htmlspecialchars($minima) ?>
                            <?php if ((int) ($item['permite_aumentar'] ?? 0) === 1): ?>
                                <p class="text-xs text-gray-500 mt-1">Também quem quiser aumentar a nota</p>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($maxima) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?= htmlspecialchars((string) ($efeitos[$efeito] ?? $efeito)) ?>
                            <?php $aplica = (string) ($item['aplica_em'] ?? ''); ?>
                            <?php if ($aplica !== '' && isset($aplicaOpcoes[$aplica])): ?>
                                <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars((string) $aplicaOpcoes[$aplica]) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <?php ob_start(); ?>
                            <?php if ($podeAlterar): ?>
                            <button type="button" onclick="abrirRecuperacao(<?= $id ?>)" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                            </button>
                            <?php endif; ?>
                            <?php if ($podeExcluir): ?>
                            <form method="POST" action="<?= URL ?>/admin/regras-academicas/recuperacao/<?= $id ?>/excluir"
                                  onsubmit="return confirm('Excluir esta recuperação?');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                            <?php $row_actions_dropdown_id = 'row-actions-recuperacao-' . $id; ?>
                            <?php include \App\Core\ViewPaths::adminPartial('row_actions_dropdown.php'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="recuperacao-backdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="fecharRecuperacao()"></div>
<aside id="recuperacao-drawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="recuperacao-titulo" class="text-xl font-bold text-gray-900">Nova recuperação</h2>
        <button type="button" onclick="fecharRecuperacao()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="recuperacao-form" method="POST" action="<?= URL ?>/admin/regras-academicas/recuperacao" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="ano_letivo" value="<?= $ano ?>">
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Como funciona</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="rec-nome" class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" id="rec-nome" name="nome" maxlength="120" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="rec-quando" class="block text-sm font-medium text-gray-700 mb-1">Quando <span class="text-red-500">*</span></label>
                        <select id="rec-quando" name="quando" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <?php foreach ($quandos as $chave => $rotulo): ?>
                                <option value="<?= htmlspecialchars((string) $chave) ?>"><?= htmlspecialchars((string) $rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="rec-efeito" class="block text-sm font-medium text-gray-700 mb-1">O que a nota faz <span class="text-red-500">*</span></label>
                        <select id="rec-efeito" name="efeito" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <?php foreach ($efeitos as $chave => $rotulo): ?>
                                <option value="<?= htmlspecialchars((string) $chave) ?>"><?= htmlspecialchars((string) $rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="rec-aplica-box" class="sm:col-span-2 hidden">
                        <label for="rec-aplica" class="block text-sm font-medium text-gray-700 mb-1">A nota substitui <span class="text-red-500">*</span></label>
                        <select id="rec-aplica" name="aplica_em"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <?php foreach ($aplicaOpcoes as $chave => $rotulo): ?>
                                <option value="<?= htmlspecialchars((string) $chave) ?>"><?= htmlspecialchars((string) $rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($aplicaAjuda) ?></p>
                    </div>
                    <?php include __DIR__ . '/_formula_rec.php'; ?>
                    <div>
                        <label for="rec-minima" class="block text-sm font-medium text-gray-700 mb-1">Faz quem ficou abaixo de <span class="text-red-500">*</span></label>
                        <input type="number" id="rec-minima" name="nota_minima" min="0" max="1000" step="0.01" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="rec-maxima" class="block text-sm font-medium text-gray-700 mb-1">Nota máxima da REC <span class="text-red-500">*</span></label>
                        <input type="number" id="rec-maxima" name="nota_maxima" min="0" max="1000" step="0.01" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <label class="sm:col-span-2 flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" id="rec-permite" name="permite_aumentar" value="1"
                               class="mt-0.5 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-900">Quem não ficou de recuperação também pode fazer</span>
                            <span class="block text-xs text-gray-500 mt-0.5">O aluno faz se quiser, para aumentar a nota.</span>
                        </span>
                    </label>
                </div>
                <p class="text-xs text-gray-500 mt-4"><?= htmlspecialchars($ajudaRecuperacao) ?></p>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="fecharRecuperacao()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="recuperacao-salvar">Salvar</span>
            </button>
        </div>
    </form>
</aside>

<script>
function mostrarRecuperacao() {
    document.getElementById('recuperacao-backdrop').classList.remove('hidden');
    const drawer = document.getElementById('recuperacao-drawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
}
function fecharRecuperacao() {
    document.getElementById('recuperacao-backdrop').classList.add('hidden');
    const drawer = document.getElementById('recuperacao-drawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
}
let recuperacaoPedido = 0;
function abrirRecuperacao(id) {
    const pedido = ++recuperacaoPedido;
    const form = document.getElementById('recuperacao-form');
    form.reset();
    form.action = '<?= URL ?>/admin/regras-academicas/recuperacao';
    document.getElementById('recuperacao-titulo').textContent = 'Nova recuperação';
    document.getElementById('recuperacao-salvar').textContent = 'Salvar';
    form.dataset.mode = 'create';
    atualizarRecCampos();
    if (!id) {
        mostrarRecuperacao();
        return;
    }
    fetch('<?= URL ?>/admin/regras-academicas/recuperacao/' + id + '/dados')
        .then(function (resposta) { return resposta.json(); })
        .then(function (dados) {
            if (pedido !== recuperacaoPedido) {
                return;
            }
            if (!dados || dados.error) {
                alert(dados && dados.error ? dados.error : 'Não foi possível carregar.');
                return;
            }
            form.dataset.mode = 'edit';
            form.action = '<?= URL ?>/admin/regras-academicas/recuperacao/' + id;
            document.getElementById('recuperacao-titulo').textContent = 'Editar recuperação';
            document.getElementById('recuperacao-salvar').textContent = 'Salvar alterações';
            document.getElementById('rec-nome').value = dados.nome || '';
            document.getElementById('rec-quando').value = dados.quando || '';
            document.getElementById('rec-efeito').value = dados.efeito || '';
            document.getElementById('rec-aplica').value = dados.aplica_em || 'par';
            if (window.recFormulaDefinir) window.recFormulaDefinir(dados.formula || '');
            document.getElementById('rec-permite').checked = String(dados.permite_aumentar) === '1';
            document.getElementById('rec-minima').value = dados.nota_minima || '';
            document.getElementById('rec-maxima').value = dados.nota_maxima || '';
            atualizarRecCampos();
            mostrarRecuperacao();
        })
        .catch(function () {
            if (pedido === recuperacaoPedido) {
                alert('Não foi possível carregar.');
            }
        });
}
function atualizarRecCampos() {
    var quando = document.getElementById('rec-quando').value;
    var efeito = document.getElementById('rec-efeito').value;
    var aplica = document.getElementById('rec-aplica');
    var formula = document.getElementById('rec-formula');
    var mostraAplica = quando === 'par';
    var mostraFormula = efeito === 'formula';
    document.getElementById('rec-aplica-box').classList.toggle('hidden', !mostraAplica);
    document.getElementById('rec-formula-box').classList.toggle('hidden', !mostraFormula);
    aplica.required = mostraAplica;
    formula.required = mostraFormula;
    if (!mostraAplica) aplica.value = 'par';
    if (!mostraFormula && window.recFormulaDefinir) window.recFormulaDefinir('');
}
document.getElementById('rec-quando').addEventListener('change', atualizarRecCampos);
document.getElementById('rec-efeito').addEventListener('change', atualizarRecCampos);
atualizarRecCampos();
</script>
