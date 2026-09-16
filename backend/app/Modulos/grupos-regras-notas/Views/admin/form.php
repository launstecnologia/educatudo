<?php
$item = is_array($item ?? null) ? $item : null;
$materias = is_array($materias ?? null) ? $materias : [];
$schemaPronto = !empty($schema_pronto);
$numeroMax = (int) ($numero_max ?? 20);
$ehEdicao = !empty($item['id']);
$marcasIni = is_array($item['marcas'] ?? null) ? $item['marcas'] : [];
$tiposIni = is_array($item['tipos'] ?? null) ? $item['tipos'] : [];
$tiposNota = is_array($tipos_nota ?? null) ? $tipos_nota : [];
$ui = dirname(__DIR__, 4) . '/Views/admin/_partials/ui';

$page_header_back_url = URL . '/admin/quadros-notas';
$page_header_title = $ehEdicao ? 'Editar quadro de notas' : 'Novo quadro de notas';
$page_header_subtitle = 'Molde das colunas (S1, S2…) para vincular cada semana ao lançamento de notas. Escala e fechamento ficam no tipo de nota.';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_form.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';

$steps = [
    ['label' => 'Identificação', 'sub' => 'Nome'],
    ['label' => 'Colunas', 'sub' => 'S1, AV1…'],
    ['label' => 'Blocos', 'sub' => 'Opcional'],
    ['label' => 'Revisão', 'sub' => 'Salvar'],
];
?>

<?php if (!$schemaPronto): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-6">
    Rode as migrations <code class="text-sm">2026_09_01_grupos_regras_notas.sql</code>, <code class="text-sm">2026_09_10_quadros_notas.sql</code> e <code class="text-sm">2026_09_12_quadros_colunas_papel.sql</code> no painel Master.
</div>
<?php else: ?>
<div id="quadroWizard" class="space-y-6">
    <?php
    $ui_wizard_steps = $steps;
    $ui_wizard_current = 1;
    include $ui . '/wizard_steps.php';
    ?>

    <form method="POST"
          action="<?= URL ?>/admin/grupos-regras-notas<?= $ehEdicao ? '/' . (int) $item['id'] . '/update' : '' ?>"
          id="form-grupo-regras">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <div class="step-panel" data-step-panel="1">
            <?php ob_start(); ?>
            <div class="space-y-6">
                <?php $ui_form_secao_titulo = 'Identificação'; include $ui . '/form_secao.php'; ?>
                <p class="text-sm text-gray-500 -mt-2">Só o nome do molde. Semanas e o tipo de nota (Prova Semanal…) ficam no passo Colunas. Escala e como fecha a nota ficam no tipo de nota, não aqui.</p>
                <div id="wizardStep1Erro" class="hidden rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                    Informe o nome do quadro para avançar.
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nome <span class="text-red-500">*</span></label>
                        <input type="text" name="nome" required id="quadro-nome"
                               value="<?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                               placeholder="Ex.: Notas semanais, Bimestre 1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                        <input type="text" name="descricao"
                               value="<?= htmlspecialchars((string) ($item['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                               placeholder="Opcional">
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="ativo" value="1" class="rounded border-gray-300" <?= !isset($item['ativo']) || (int) $item['ativo'] === 1 ? 'checked' : '' ?>>
                    Ativo
                </label>
                <?php
                $ui_wizard_actions_back_step = null;
                $ui_wizard_actions_next_label = 'Próximo';
                $ui_wizard_actions_next_id = 'wizardBtnStep1Next';
                include $ui . '/wizard_actions.php';
                ?>
            </div>
            <?php
            $ui_card_body = ob_get_clean();
            $ui_card_variant = 'form';
            include $ui . '/card.php';
            ?>
        </div>

        <div class="step-panel hidden" data-step-panel="2">
            <?php ob_start(); ?>
            <div class="space-y-6">
                <?php $ui_form_secao_titulo = 'Colunas'; include $ui . '/form_secao.php'; ?>
                <p class="text-sm text-gray-500 -mt-2">Cada coluna é uma semana (ou AV1, Trabalho…). Vincule o tipo de nota do lançamento. Só coluna de lançamento entra no Lançamento de Notas. Coluna calculada é opcional (média / nota final).</p>
                <div id="wizardStep2Erro" class="hidden rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm px-4 py-3">
                    <i class="fa-solid fa-circle-info mr-2"></i>
                    Sem colunas, o lançamento de notas não tem destino no quadro. Você pode avançar e cadastrar só blocos de disciplinas, se for o caso.
                </div>
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="btn-sugerir-s1s8" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">
                            Sugerir S1 a S8
                        </button>
                        <button type="button" id="btn-add-calculada" class="px-4 py-2 border border-violet-200 text-violet-800 rounded-lg text-sm bg-white hover:bg-violet-50">
                            + Nota final (calculada)
                        </button>
                    </div>
                    <button type="button" id="btn-add-coluna" class="btn-primary-custom h-10 px-3 rounded-lg text-sm font-medium">+ Coluna</button>
                </div>
                <div id="lista-colunas" class="space-y-3"></div>
                <p id="vazio-colunas" class="text-sm text-gray-500 border border-dashed border-gray-200 rounded-lg px-4 py-6 text-center">Nenhuma coluna. Use “Sugerir S1 a S8” ou cadastre AV1, Simulado 1…</p>
                <?php
                $ui_wizard_actions_back_step = 1;
                $ui_wizard_actions_next_label = 'Próximo';
                $ui_wizard_actions_next_id = 'wizardBtnStep2Next';
                include $ui . '/wizard_actions.php';
                ?>
            </div>
            <?php
            $ui_card_body = ob_get_clean();
            $ui_card_variant = 'form';
            include $ui . '/card.php';
            ?>
        </div>

        <div class="step-panel hidden" data-step-panel="3">
            <?php ob_start(); ?>
            <div class="space-y-6">
                <?php $ui_form_secao_titulo = 'Blocos de disciplinas'; include $ui . '/form_secao.php'; ?>
                <p class="text-sm text-gray-500 -mt-2">Opcional. Agrupa matérias (Bloco A nas semanas ímpares, Bloco B nas pares, Humanas…). Isto <strong>não</strong> é a prova — a prova é criada em Lançamento de Notas.</p>
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <button type="button" id="btn-sugerir-ab" title="Preenche só se ainda não houver blocos"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">
                        Sugerir Bloco A e Bloco B
                    </button>
                    <button type="button" id="btn-add-tipo" class="btn-primary-custom h-10 px-3 rounded-lg text-sm font-medium">+ Bloco de disciplinas</button>
                </div>
                <div id="lista-tipos" class="space-y-4"></div>
                <p id="vazio-tipos" class="text-sm text-gray-500 border border-dashed border-gray-200 rounded-lg px-4 py-6 text-center">Nenhum bloco. Pule esta etapa se todas as matérias usam as mesmas colunas.</p>
                <?php
                $ui_wizard_actions_back_step = 2;
                $ui_wizard_actions_next_label = 'Revisar';
                $ui_wizard_actions_next_id = 'wizardBtnStep3Next';
                include $ui . '/wizard_actions.php';
                ?>
            </div>
            <?php
            $ui_card_body = ob_get_clean();
            $ui_card_variant = 'form';
            include $ui . '/card.php';
            ?>
        </div>

        <div class="step-panel hidden" data-step-panel="4">
            <?php ob_start(); ?>
            <div class="space-y-6">
                <?php $ui_form_secao_titulo = 'Revisão'; include $ui . '/form_secao.php'; ?>
                <p class="text-sm text-gray-500 -mt-2">Confira o molde. Depois cadastre as provas em Lançamento de Notas, vinculadas a estas colunas.</p>
                <div id="wizardResumo" class="rounded-lg border border-gray-200 bg-gray-50/50 p-5"></div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2">
                    <button type="button" class="wizard-step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="3">
                        <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
                    </button>
                    <button type="submit" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                        Salvar quadro
                    </button>
                </div>
            </div>
            <?php
            $ui_card_body = ob_get_clean();
            $ui_card_variant = 'form';
            include $ui . '/card.php';
            ?>
        </div>
    </form>
</div>

<script>
(function () {
    var NUMERO_MAX = <?= (int) $numeroMax ?>;
    var materias = <?= json_encode($materias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?> || [];
    var marcasIni = <?= json_encode($marcasIni, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?> || [];
    var tiposIni = <?= json_encode($tiposIni, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?> || [];
    var tiposNota = <?= json_encode($tiposNota, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?> || [];
    var listaMarcas = document.getElementById('lista-colunas');
    var listaTipos = document.getElementById('lista-tipos');
    var vazioMarcas = document.getElementById('vazio-colunas');
    var vazioTipos = document.getElementById('vazio-tipos');
    var seq = 0;
    var current = 1;
    var completedSteps = {};
    var errorSteps = {};

    var CLASS_MAP = {
        ativo: ['border-accent', 'bg-primary', 'text-primary', 'shadow-md'],
        completo: ['border-green-500', 'bg-green-50', 'text-green-700'],
        erro: ['border-red-400', 'bg-red-50', 'text-red-700'],
        pendente: ['border-gray-200', 'bg-white', 'text-gray-600', 'hover:border-gray-300', 'hover:bg-gray-50']
    };
    var ALL_STATE_CLASSES = Object.keys(CLASS_MAP).reduce(function (acc, k) { return acc.concat(CLASS_MAP[k]); }, []);
    var BADGE_MAP = {
        completo: ['bg-green-500', 'fa-solid fa-check'],
        erro: ['bg-red-500', 'fa-solid fa-exclamation']
    };

    function chaveNova() {
        seq += 1;
        return 'n' + Date.now() + '_' + seq;
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function slug(texto) {
        var t = String(texto || '').toLowerCase().trim();
        t = t.replace(/[áàãâ]/g, 'a').replace(/[éê]/g, 'e').replace(/í/g, 'i')
            .replace(/[óôõ]/g, 'o').replace(/[úü]/g, 'u').replace(/ç/g, 'c');
        t = t.replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
        return t.slice(0, 40);
    }

    function syncVazios() {
        if (vazioMarcas) vazioMarcas.classList.toggle('hidden', listaMarcas.querySelectorAll('.marca-row').length > 0);
        if (vazioTipos) vazioTipos.classList.toggle('hidden', listaTipos.querySelectorAll('.tipo-row').length > 0);
    }

    function proximoNumero() {
        var usados = {};
        listaMarcas.querySelectorAll('.marca-row').forEach(function (row) {
            if ((row.querySelector('.marca-papel') || {}).value === 'calculada') return;
            var el = row.querySelector('.marca-numero');
            usados[parseInt(el && el.value, 10) || 0] = true;
        });
        for (var n = 1; n <= NUMERO_MAX; n++) {
            if (!usados[n]) return n;
        }
        return NUMERO_MAX;
    }

    function htmlTipoNota(sel) {
        var html = '<select class="marca-tipo-nota w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">';
        html += '<option value="">Tipo de nota (opcional)</option>';
        tiposNota.forEach(function (t) {
            var id = String(t.id);
            html += '<option value="' + esc(id) + '"' + (String(sel || '') === id ? ' selected' : '') + '>' + esc(t.nome) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function formulaSelecionada(data) {
        if (data && Array.isArray(data.formula_colunas)) return data.formula_colunas.map(String);
        if (data && data.formula_json) {
            try {
                var j = typeof data.formula_json === 'string' ? JSON.parse(data.formula_json) : data.formula_json;
                return (j && j.colunas ? j.colunas : []).map(String);
            } catch (e) { return []; }
        }
        return [];
    }

    function renderFormulaChecks(wrap, selecionadas) {
        var box = wrap.querySelector('.wrap-formula');
        if (!box) return;
        selecionadas = (selecionadas || []).map(String);
        var html = '<p class="text-xs text-gray-500 mb-2">Média destas colunas de lançamento</p><div class="flex flex-wrap gap-3">';
        var lanc = marcasAtuais().filter(function (m) { return m.papel !== 'calculada'; });
        if (!lanc.length) {
            html += '<span class="text-xs text-gray-400">Cadastre colunas de lançamento primeiro.</span>';
        }
        lanc.forEach(function (m) {
            var chk = selecionadas.indexOf(String(m.codigo || m.chave)) >= 0 || selecionadas.indexOf(String(m.chave)) >= 0 ? ' checked' : '';
            html += '<label class="inline-flex items-center gap-2 text-sm">' +
                '<input type="checkbox" class="marca-formula rounded border-gray-300" value="' + esc(m.codigo || m.chave) + '"' + chk + '>' +
                esc(m.nome) + '</label>';
        });
        html += '</div>';
        box.innerHTML = html;
    }

    function syncPapelUi(wrap) {
        var papel = (wrap.querySelector('.marca-papel') || {}).value || 'lancamento';
        var ehCalc = papel === 'calculada';
        wrap.querySelectorAll('.js-so-lancamento').forEach(function (el) { el.classList.toggle('hidden', ehCalc); });
        wrap.querySelectorAll('.js-so-calculada').forEach(function (el) { el.classList.toggle('hidden', !ehCalc); });
        if (ehCalc) renderFormulaChecks(wrap, formulaCodigosDoWrap(wrap));
    }

    function formulaCodigosDoWrap(wrap) {
        var out = [];
        wrap.querySelectorAll('.marca-formula:checked').forEach(function (el) { out.push(el.value); });
        return out;
    }

    function addMarca(data) {
        data = data || {};
        var chave = data.chave || data.codigo || chaveNova();
        var papel = data.papel === 'calculada' ? 'calculada' : 'lancamento';
        var wrap = document.createElement('div');
        wrap.className = 'border border-gray-200 rounded-lg p-3 space-y-3 marca-row';
        wrap.innerHTML =
            '<input type="hidden" class="marca-id" value="' + esc(data.id || '') + '">' +
            '<input type="hidden" class="marca-chave" value="' + esc(chave) + '">' +
            '<input type="hidden" class="marca-codigo" value="' + esc(data.codigo || '') + '">' +
            '<div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">' +
            '<div class="md:col-span-4"><label class="block text-xs font-medium text-gray-500 mb-1">Nome</label>' +
            '<input type="text" class="marca-nome w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="S1" value="' + esc(data.nome || '') + '"></div>' +
            '<div class="md:col-span-3"><label class="block text-xs font-medium text-gray-500 mb-1">Papel</label>' +
            '<select class="marca-papel w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">' +
            '<option value="lancamento"' + (papel === 'lancamento' ? ' selected' : '') + '>Lançamento</option>' +
            '<option value="calculada"' + (papel === 'calculada' ? ' selected' : '') + '>Calculada</option>' +
            '</select></div>' +
            '<div class="md:col-span-3 js-so-lancamento"><label class="block text-xs font-medium text-gray-500 mb-1">Número (semana)</label>' +
            '<input type="number" min="1" max="' + NUMERO_MAX + '" class="marca-numero w-full px-3 py-2 border border-gray-300 rounded-lg" value="' + esc(papel === 'calculada' ? (data.numero || '') : (data.numero || proximoNumero())) + '"></div>' +
            '<div class="md:col-span-2"><button type="button" class="btn-rm-marca w-full px-3 py-2 border border-red-200 text-red-700 rounded-lg text-sm hover:bg-red-50">Remover</button></div></div>' +
            '<div class="js-so-lancamento">' + htmlTipoNota(data.tipo_nota_id || '') + '</div>' +
            '<div class="js-so-calculada hidden">' +
            '<label class="inline-flex items-center gap-2 text-sm text-gray-800 mb-2">' +
            '<input type="checkbox" class="marca-boletim rounded border-gray-300"' + (data.vai_para_boletim ? ' checked' : '') + '> Vai para o boletim oficial</label>' +
            '<div class="wrap-formula"></div></div>';
        wrap.querySelector('.btn-rm-marca').addEventListener('click', function () {
            wrap.remove();
            renderCheckboxesMarcas();
            refreshFormulas();
            syncVazios();
        });
        wrap.querySelector('.marca-nome').addEventListener('input', function () {
            var codEl = wrap.querySelector('.marca-codigo');
            if (codEl && !data.id) {
                var n = parseInt((wrap.querySelector('.marca-numero') || {}).value, 10) || 0;
                var s = slug(wrap.querySelector('.marca-nome').value);
                var p = (wrap.querySelector('.marca-papel') || {}).value;
                codEl.value = p === 'calculada' ? (s || 'nota_final') : (/^s[1-9]\d?$/.test(s) ? s : (n ? ('s' + n) : s));
            }
            renderCheckboxesMarcas();
            refreshFormulas();
        });
        wrap.querySelector('.marca-papel').addEventListener('change', function () {
            syncPapelUi(wrap);
            renderCheckboxesMarcas();
            refreshFormulas();
        });
        listaMarcas.appendChild(wrap);
        if (papel === 'calculada') {
            wrap.dataset.formulaIni = JSON.stringify(formulaSelecionada(data));
        }
        syncPapelUi(wrap);
        if (papel === 'calculada') {
            renderFormulaChecks(wrap, formulaSelecionada(data));
        }
        renderCheckboxesMarcas();
        syncVazios();
    }

    function refreshFormulas() {
        listaMarcas.querySelectorAll('.marca-row').forEach(function (row) {
            if ((row.querySelector('.marca-papel') || {}).value !== 'calculada') return;
            var sel = formulaCodigosDoWrap(row);
            if (!sel.length && row.dataset.formulaIni) {
                try { sel = JSON.parse(row.dataset.formulaIni); } catch (e) { sel = []; }
            }
            renderFormulaChecks(row, sel);
        });
    }

    function marcasAtuais() {
        var out = [];
        listaMarcas.querySelectorAll('.marca-row').forEach(function (row) {
            out.push({
                chave: row.querySelector('.marca-chave').value,
                codigo: (row.querySelector('.marca-codigo') || {}).value || '',
                nome: row.querySelector('.marca-nome').value || row.querySelector('.marca-chave').value,
                numero: parseInt((row.querySelector('.marca-numero') || {}).value, 10) || 0,
                papel: (row.querySelector('.marca-papel') || {}).value || 'lancamento'
            });
        });
        return out;
    }

    function htmlMaterias(selecionadas) {
        selecionadas = (selecionadas || []).map(Number);
        var html = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-48 overflow-y-auto border border-gray-100 rounded-lg p-2">';
        if (!materias.length) {
            html += '<p class="text-xs text-gray-500 col-span-full">Nenhuma matéria cadastrada.</p>';
        }
        materias.forEach(function (m) {
            var id = Number(m.id);
            var chk = selecionadas.indexOf(id) >= 0 ? ' checked' : '';
            html += '<label class="inline-flex items-center gap-2 text-sm text-gray-800">' +
                '<input type="checkbox" class="tipo-materia rounded border-gray-300" value="' + id + '"' + chk + '>' +
                esc(m.nome) + '</label>';
        });
        html += '</div>';
        return html;
    }

    function htmlMarcasChecks(selecionadas, alvo) {
        selecionadas = (selecionadas || []).map(String);
        var marcas = marcasAtuais();
        var html = '<div class="flex flex-wrap gap-3 marcas-checks">';
        if (!marcas.length) {
            html += '<p class="text-xs text-gray-500">Cadastre colunas na etapa anterior para escolher quais entram neste bloco.</p>';
        }
        marcas.forEach(function (m) {
            if (m.papel === 'calculada') return;
            var chk = selecionadas.indexOf(String(m.chave)) >= 0 ? ' checked' : '';
            html += '<label class="inline-flex items-center gap-2 text-sm">' +
                '<input type="checkbox" class="tipo-marca rounded border-gray-300" value="' + esc(m.chave) + '"' + chk + '>' +
                esc(m.nome) + '</label>';
        });
        html += '</div>';
        if (alvo) alvo.innerHTML = html;
        return html;
    }

    function renderCheckboxesMarcas() {
        listaTipos.querySelectorAll('.tipo-row').forEach(function (row) {
            var box = row.querySelector('.wrap-marcas-tipo');
            var selecionadas = [];
            row.querySelectorAll('.tipo-marca:checked').forEach(function (el) { selecionadas.push(el.value); });
            htmlMarcasChecks(selecionadas, box);
        });
    }

    function addTipo(data) {
        data = data || {};
        var wrap = document.createElement('div');
        wrap.className = 'border border-gray-200 rounded-lg p-4 space-y-4 tipo-row';
        var marcasIds = (data.marcas_ids || []).map(String);
        var marcasChaves = data.marcas || data.marcas_chaves || [];
        if (!marcasChaves.length && marcasIds.length && marcasIni.length) {
            marcasIni.forEach(function (m) {
                if (marcasIds.indexOf(String(m.id)) >= 0) marcasChaves.push(m.codigo || ('m' + m.id));
            });
        }
        wrap.innerHTML =
            '<input type="hidden" class="tipo-id" value="' + esc(data.id || '') + '">' +
            '<input type="hidden" class="tipo-codigo" value="' + esc(data.codigo || '') + '">' +
            '<div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">' +
            '<div class="md:col-span-10"><label class="block text-xs font-medium text-gray-500 mb-1">Nome do bloco de disciplinas</label>' +
            '<input type="text" class="tipo-nome w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Bloco A / Humanas" value="' + esc(data.nome || '') + '"></div>' +
            '<div class="md:col-span-2"><button type="button" class="btn-rm-tipo w-full px-3 py-2 border border-red-200 text-red-700 rounded-lg text-sm hover:bg-red-50">Remover</button></div></div>' +
            '<div><p class="text-xs font-medium text-gray-500 mb-2">Colunas deste bloco (opcional)</p>' +
            '<p class="text-xs text-gray-400 mb-2">Se não marcar nenhuma, o bloco usa todas as colunas.</p>' +
            '<div class="wrap-marcas-tipo"></div></div>' +
            '<div><p class="text-xs font-medium text-gray-500 mb-2">Matérias (opcional)</p>' + htmlMaterias(data.materias_ids || []) + '</div>';
        wrap.querySelector('.btn-rm-tipo').addEventListener('click', function () { wrap.remove(); syncVazios(); });
        wrap.querySelector('.tipo-nome').addEventListener('input', function () {
            var codEl = wrap.querySelector('.tipo-codigo');
            if (codEl && !data.id && !data.codigo) {
                codEl.value = slug(wrap.querySelector('.tipo-nome').value);
            }
        });
        listaTipos.appendChild(wrap);
        htmlMarcasChecks(marcasChaves.map(String), wrap.querySelector('.wrap-marcas-tipo'));
        syncVazios();
    }

    document.getElementById('btn-add-coluna').addEventListener('click', function () { addMarca({}); });
    var btnCalc = document.getElementById('btn-add-calculada');
    if (btnCalc) {
        btnCalc.addEventListener('click', function () {
            addMarca({ nome: 'Nota final', codigo: 'nota_final', papel: 'calculada', vai_para_boletim: 1 });
        });
    }
    document.getElementById('btn-add-tipo').addEventListener('click', function () { addTipo({}); });

    document.getElementById('btn-sugerir-s1s8').addEventListener('click', function () {
        var usados = {};
        listaMarcas.querySelectorAll('.marca-row').forEach(function (row) {
            if ((row.querySelector('.marca-papel') || {}).value === 'calculada') return;
            var el = row.querySelector('.marca-numero');
            usados[parseInt(el && el.value, 10) || 0] = true;
        });
        for (var n = 1; n <= 8; n++) {
            if (usados[n]) continue;
            addMarca({ nome: 'S' + n, codigo: 's' + n, numero: n });
        }
    });

    document.getElementById('btn-sugerir-ab').addEventListener('click', function () {
        if (listaTipos.querySelectorAll('.tipo-row').length > 0) {
            return;
        }
        var marcas = marcasAtuais();
        if (!marcas.length) {
            for (var n = 1; n <= 8; n++) {
                addMarca({ nome: 'S' + n, codigo: 's' + n, numero: n });
            }
            marcas = marcasAtuais();
        }
        var impares = [];
        var pares = [];
        marcas.forEach(function (m) {
            if (m.papel === 'calculada') return;
            if (m.numero % 2 === 1) impares.push(m.chave);
            else pares.push(m.chave);
        });
        addTipo({ nome: 'Bloco A', codigo: 'a', marcas: impares });
        addTipo({ nome: 'Bloco B', codigo: 'b', marcas: pares });
    });

    document.getElementById('form-grupo-regras').addEventListener('submit', function () {
        listaMarcas.querySelectorAll('.marca-row').forEach(function (row, i) {
            function hid(name, val) {
                var el = document.createElement('input');
                el.type = 'hidden';
                el.name = name;
                el.value = val;
                row.appendChild(el);
            }
            var nome = row.querySelector('.marca-nome').value;
            var numero = row.querySelector('.marca-numero').value;
            var codigo = row.querySelector('.marca-codigo').value;
            if (!codigo) {
                var s = slug(nome);
                codigo = /^s[1-9]\d?$/.test(s) ? s : ('s' + (parseInt(numero, 10) || i + 1));
            }
            hid('colunas[' + i + '][id]', row.querySelector('.marca-id').value);
            hid('colunas[' + i + '][chave]', row.querySelector('.marca-chave').value);
            hid('colunas[' + i + '][nome]', nome);
            hid('colunas[' + i + '][codigo]', codigo);
            hid('colunas[' + i + '][numero]', numero);
            hid('colunas[' + i + '][papel]', (row.querySelector('.marca-papel') || {}).value || 'lancamento');
            hid('colunas[' + i + '][tipo_nota_id]', (row.querySelector('.marca-tipo-nota') || {}).value || '');
            if ((row.querySelector('.marca-boletim') || {}).checked) {
                hid('colunas[' + i + '][vai_para_boletim]', '1');
            }
            row.querySelectorAll('.marca-formula:checked').forEach(function (el) {
                hid('colunas[' + i + '][formula_colunas][]', el.value);
            });
        });
        listaTipos.querySelectorAll('.tipo-row').forEach(function (row, i) {
            function hid(name, val) {
                var el = document.createElement('input');
                el.type = 'hidden';
                el.name = name;
                el.value = val;
                row.appendChild(el);
            }
            var nome = row.querySelector('.tipo-nome').value;
            var codigo = row.querySelector('.tipo-codigo').value || slug(nome);
            hid('tipos[' + i + '][id]', row.querySelector('.tipo-id').value);
            hid('tipos[' + i + '][nome]', nome);
            hid('tipos[' + i + '][codigo]', codigo);
            row.querySelectorAll('.tipo-marca:checked').forEach(function (el) {
                hid('tipos[' + i + '][colunas][]', el.value);
            });
            row.querySelectorAll('.tipo-materia:checked').forEach(function (el) {
                hid('tipos[' + i + '][materias][]', el.value);
            });
        });
    });

    function stepState(n) {
        if (errorSteps[n]) return 'erro';
        if (n === current) return 'ativo';
        if (completedSteps[n]) return 'completo';
        return 'pendente';
    }

    function renderStepBadge(btn, estado) {
        var circle = btn.querySelector('.wizard-step-circle');
        if (!circle) return;
        var existing = circle.querySelector('.wizard-step-corner');
        if (existing) existing.remove();
        var badge = BADGE_MAP[estado];
        if (!badge) return;
        var span = document.createElement('span');
        span.className = 'wizard-step-corner absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full text-white text-[9px] ' + badge[0];
        var icon = document.createElement('i');
        icon.className = badge[1];
        span.appendChild(icon);
        circle.appendChild(span);
    }

    function setActiveNav() {
        document.querySelectorAll('#wizardStepsNav .step-nav-btn').forEach(function (btn) {
            var n = parseInt(btn.getAttribute('data-step-target'), 10);
            var estado = stepState(n);
            btn.setAttribute('data-active', estado === 'ativo' ? 'true' : 'false');
            btn.setAttribute('data-step-state', estado);
            ALL_STATE_CLASSES.forEach(function (c) { btn.classList.remove(c); });
            CLASS_MAP[estado].forEach(function (c) { btn.classList.add(c); });
            renderStepBadge(btn, estado);
        });
        document.querySelectorAll('#wizardStepsNav [data-connector-after]').forEach(function (el) {
            var n = parseInt(el.getAttribute('data-connector-after'), 10);
            var done = !!completedSteps[n] && !errorSteps[n];
            el.classList.toggle('bg-green-400', done);
            el.classList.toggle('bg-gray-200', !done);
        });
    }

    window.setWizardStep = function (n) {
        current = n;
        document.querySelectorAll('[data-step-panel]').forEach(function (el) {
            var sn = parseInt(el.getAttribute('data-step-panel'), 10);
            el.classList.toggle('hidden', sn !== n);
        });
        setActiveNav();
        if (n === 3) renderCheckboxesMarcas();
        if (n === 4) buildWizardResumo();
        var wiz = document.getElementById('quadroWizard');
        if (wiz) window.scrollTo({ top: wiz.offsetTop - 16, behavior: 'smooth' });
    };

    function validarPasso1() {
        var nomeEl = document.getElementById('quadro-nome');
        var valido = !!(nomeEl && nomeEl.value.trim() !== '');
        var erroBox = document.getElementById('wizardStep1Erro');
        if (!valido) {
            errorSteps[1] = true;
            delete completedSteps[1];
            if (erroBox) erroBox.classList.remove('hidden');
            setActiveNav();
            return false;
        }
        delete errorSteps[1];
        completedSteps[1] = true;
        if (erroBox) erroBox.classList.add('hidden');
        return true;
    }

    document.querySelectorAll('#wizardStepsNav .step-nav-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var alvo = parseInt(btn.getAttribute('data-step-target'), 10);
            if (alvo > 1 && !validarPasso1()) {
                window.setWizardStep(1);
                return;
            }
            window.setWizardStep(alvo);
        });
    });
    document.querySelectorAll('.wizard-step-back').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.setWizardStep(parseInt(btn.getAttribute('data-go-step'), 10));
        });
    });

    var btnStep1 = document.getElementById('wizardBtnStep1Next');
    if (btnStep1) {
        btnStep1.addEventListener('click', function () {
            if (!validarPasso1()) return;
            window.setWizardStep(2);
        });
    }
    var btnStep2 = document.getElementById('wizardBtnStep2Next');
    if (btnStep2) {
        btnStep2.addEventListener('click', function () {
            var aviso = document.getElementById('wizardStep2Erro');
            if (aviso) aviso.classList.toggle('hidden', listaMarcas.querySelectorAll('.marca-row').length > 0);
            completedSteps[2] = true;
            window.setWizardStep(3);
        });
    }
    var btnStep3 = document.getElementById('wizardBtnStep3Next');
    if (btnStep3) {
        btnStep3.addEventListener('click', function () {
            completedSteps[3] = true;
            window.setWizardStep(4);
        });
    }

    function addRow(dl, label, value) {
        var dt = document.createElement('dt');
        dt.className = 'text-xs font-semibold text-gray-500 uppercase tracking-wide';
        dt.textContent = label;
        var dd = document.createElement('dd');
        dd.className = 'text-sm text-gray-800 mb-3';
        dd.textContent = value;
        dl.appendChild(dt);
        dl.appendChild(dd);
    }

    function buildWizardResumo() {
        var out = document.getElementById('wizardResumo');
        if (!out) return;
        out.innerHTML = '';
        var nomeEl = document.getElementById('quadro-nome');
        var cols = marcasAtuais();
        var blocos = [];
        listaTipos.querySelectorAll('.tipo-row').forEach(function (row) {
            var n = (row.querySelector('.tipo-nome') || {}).value || '';
            if (n) blocos.push(n);
        });
        var dl = document.createElement('dl');
        dl.className = 'grid grid-cols-1 md:grid-cols-2 gap-x-6';
        addRow(dl, 'Nome', (nomeEl && nomeEl.value) || '(sem nome)');
        addRow(dl, 'Colunas', cols.length ? cols.map(function (c) {
            return c.nome + (c.papel === 'calculada' ? ' (calculada)' : '');
        }).join(', ') : 'Nenhuma');
        addRow(dl, 'Blocos de disciplinas', blocos.length ? blocos.join(', ') : 'Nenhum (todas as matérias nas mesmas colunas)');
        addRow(dl, 'Próximo passo', 'Criar as provas em Lançamento de Notas, vinculadas a cada coluna');
        out.appendChild(dl);
    }

    if (marcasIni.length) {
        marcasIni.forEach(function (m) {
            addMarca({
                id: m.id,
                chave: m.codigo || ('m' + m.id),
                nome: m.nome,
                codigo: m.codigo,
                numero: m.numero,
                papel: m.papel,
                tipo_nota_id: m.tipo_nota_id,
                formula_json: m.formula_json,
                vai_para_boletim: m.vai_para_boletim
            });
        });
        refreshFormulas();
    }
    if (tiposIni.length) {
        tiposIni.forEach(function (t) { addTipo(t); });
    }
    syncVazios();
    window.setWizardStep(1);
})();
</script>
<?php endif; ?>
