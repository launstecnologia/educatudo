<?php
$list = $list ?? [];
$schema_ready = $schema_ready ?? false;
$status = (string) ($status ?? '');
$message = (string) ($message ?? '');
$csrf_token = $csrf_token ?? '';
if (!class_exists('PeriodoLetivo')) {
}

$page_header_title = 'Anos Letivos';
        $page_header_subtitle = 'Cadastre os anos letivos e a divisão (bimestre, trimestre ou semestre) usada em toda a escola';
if ($schema_ready) {
    ob_start();
    ?>
    <button type="button" onclick="openAnoLetivoDrawer()"
       class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i>
        Novo Ano Letivo
    </button>
    <?php
    $page_header_actions = ob_get_clean();
} else {
    $page_header_actions = '';
}
include \App\Core\ViewPaths::adminPartial('page_header_list.php');

$flash_status = $status;
$flash_message = $message;
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<?php if (!$schema_ready): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm">
    Estrutura ainda não disponível. Execute as migrations 022 a 026 no banco do tenant.
</div>
<?php elseif (empty($list)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-500">
    <i class="fa-solid fa-calendar-days text-4xl text-gray-300 mb-4"></i>
    <p>Nenhum ano letivo cadastrado</p>
    <button type="button" onclick="openAnoLetivoDrawer()"
       class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i>
        Cadastrar o primeiro ano letivo
    </button>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Início</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fim</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Divisão do ano</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($list as $row): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?= (int) $row['ano'] ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <?= $row['data_inicio'] ? date('d/m/Y', strtotime($row['data_inicio'])) : '—' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <?= $row['data_fim'] ? date('d/m/Y', strtotime($row['data_fim'])) : '—' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php
                        if (!class_exists('PeriodoLetivo')) {
                        }
                        $tipoAno = \App\Core\PeriodoLetivo::normalizarTipo((string) ($row['periodo_tipo'] ?? 'bimestre'));
                        echo htmlspecialchars(\App\Core\PeriodoLetivo::TIPOS[$tipoAno] ?? \App\Core\PeriodoLetivo::TIPOS['bimestre']);
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $row['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $row['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openAnoLetivoDrawer(<?= (int) $row['id'] ?>)"
                           class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form action="<?= URL ?>/admin/ano-letivo/<?= (int) $row['id'] ?>/delete" method="POST"
                              onsubmit="return confirm('Excluir este ano letivo?');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                            </button>
                        </form>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-ano-letivo-' . (int) $row['id'];
                        include \App\Core\ViewPaths::adminPartial('row_actions_dropdown.php');
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $pag = $pagination ?? [];
    $pagTotal = (int)($pag['total'] ?? 0);
    $pagPerPage = (int)($pag['per_page'] ?? 10);
    $pagPage = (int)($pag['page'] ?? 1);
    $pagTotalPages = (int)($pag['total_pages'] ?? 1);
    $pagQueryParams = $_GET ?? [];
    unset($pagQueryParams['page']);
    $pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
    $pagSep = $pagBaseQuery === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> ano(s) letivo(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/ano-letivo<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/ano-letivo<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/ano-letivo<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($schema_ready): ?>
<!-- Offcanvas: Cadastrar/Editar Ano Letivo -->
<div id="anoLetivoDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeAnoLetivoDrawer()"></div>
<aside id="anoLetivoDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="anoLetivoDrawerTitle" class="text-xl font-bold text-gray-900">Novo Ano Letivo</h2>
        <button type="button" onclick="closeAnoLetivoDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="ano-letivo-form" class="flex flex-col flex-1 min-h-0 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="al_id" value="">

        <div class="flex-1 min-h-0 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados do ano letivo</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="al_ano" class="block text-sm font-medium text-gray-700 mb-1">Ano <span class="text-red-500">*</span></label>
                        <input type="number" id="al_ano" name="ano" required min="2000" max="2100"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center">
                            <input type="checkbox" id="al_ativo" name="ativo" value="1" checked
                                   class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Ano letivo ativo</span>
                        </label>
                    </div>
                    <div>
                        <label for="al_data_inicio" class="block text-sm font-medium text-gray-700 mb-1">Data início</label>
                        <input type="date" id="al_data_inicio" name="data_inicio"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="al_data_fim" class="block text-sm font-medium text-gray-700 mb-1">Data fim</label>
                        <input type="date" id="al_data_fim" name="data_fim"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="al_periodo_tipo" class="block text-sm font-medium text-gray-700 mb-1">Divisão do ano <span class="text-red-500">*</span></label>
                        <select id="al_periodo_tipo" name="periodo_tipo" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white">
                            <?php foreach (\App\Core\PeriodoLetivo::TIPOS as $cod => $lab): ?>
                                <option value="<?= htmlspecialchars($cod) ?>" <?= $cod === 'bimestre' ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Define os períodos em prova, jornada, boletim e conselho. Ex.: trimestral mostra 1º, 2º e 3º trimestre.</p>
                        <p id="al_periodo_tipo_aviso" class="hidden mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"></p>
                        <div id="al-mapeamento-fields"></div>
                    </div>
                    <div class="sm:col-span-2">
                        <h4 id="al-periodos-titulo" class="text-sm font-medium text-gray-900">Datas de cada bimestre</h4>
                        <p class="text-xs text-gray-500 mt-1 mb-3">Informe o início, o fim e os dias letivos de cada período. As datas precisam caber no ano letivo e não podem se sobrepor.</p>
                        <div id="al-periodos" class="rounded-lg border border-gray-200 divide-y divide-gray-200"></div>
                    </div>
                </div>
            </section>
        </div>

        <div class="shrink-0 px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeAnoLetivoDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="al-form-submit-label">Salvar</span>
            </button>
        </div>
    </form>
</aside>

<div id="modalMapaPeriodos" class="fixed inset-0 z-[80] hidden" role="dialog" aria-modal="true" aria-labelledby="modalMapaPeriodosTitulo">
    <div class="absolute inset-0 bg-black/50" onclick="cancelarMapaPeriodos()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none overflow-y-auto">
        <div class="pointer-events-auto bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-lg my-4 overflow-hidden flex flex-col">
            <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between gap-3">
                <div>
                    <h3 id="modalMapaPeriodosTitulo" class="text-lg font-semibold text-gray-900">Para onde vai cada período?</h3>
                    <p id="modalMapaPeriodosTexto" class="mt-1 text-sm text-gray-500"></p>
                </div>
                <button type="button" onclick="cancelarMapaPeriodos()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div id="modalMapaPeriodosLista" class="px-5 py-4 space-y-3 max-h-[60vh] overflow-y-auto"></div>
            <div class="px-5 py-4 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
                <button type="button" onclick="cancelarMapaPeriodos()"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancelar
                </button>
                <button type="button" id="mapa-periodos-confirmar" onclick="confirmarMapaPeriodos()"
                        class="btn-primary-custom px-5 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var PERIODOS_LETIVOS = <?= json_encode((function () {
    $mapa = [];
    foreach (\App\Core\PeriodoLetivo::TIPOS as $cod => $lab) {
        $mapa[$cod] = [
            'nome' => $lab,
            'campo' => \App\Core\PeriodoLetivo::rotuloCampo($cod),
            'rotulos' => \App\Core\PeriodoLetivo::rotulosDoTipo($cod),
        ];
    }
    return $mapa;
})(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

var anoLetivoEdicao = { original: 'bimestre', usados: [], mapaPronto: false, periodosSalvos: [] };
var salvarAoConfirmarMapa = false;
var periodosManuais = false;

function limparMapeamento() {
    var box = document.getElementById('al-mapeamento-fields');
    if (box) { box.innerHTML = ''; }
    anoLetivoEdicao.mapaPronto = false;
}

function soData(valor) {
    return String(valor || '').substring(0, 10);
}

function parseIso(iso) {
    var partes = String(iso || '').split('-');
    if (partes.length !== 3) { return null; }
    var ano = parseInt(partes[0], 10);
    var mes = parseInt(partes[1], 10);
    var dia = parseInt(partes[2], 10);
    if (!ano || !mes || !dia) { return null; }
    return new Date(ano, mes - 1, dia);
}

function formatIso(data) {
    var mes = data.getMonth() + 1;
    var dia = data.getDate();
    return data.getFullYear()
        + '-' + (mes < 10 ? '0' : '') + mes
        + '-' + (dia < 10 ? '0' : '') + dia;
}

function dividirIntervalo(inicio, fim, quantidade) {
    var a = parseIso(inicio);
    var b = parseIso(fim);
    if (!a || !b || b < a || quantidade < 1) { return []; }
    var total = Math.round((b.getTime() - a.getTime()) / 86400000) + 1;
    if (total < quantidade) { return []; }
    var base = Math.floor(total / quantidade);
    var extra = total % quantidade;
    var cursor = new Date(a.getTime());
    var saida = [];
    for (var i = 0; i < quantidade; i++) {
        var tamanho = base + (i < extra ? 1 : 0);
        var ini = new Date(cursor.getTime());
        var end = new Date(cursor.getTime());
        end.setDate(end.getDate() + tamanho - 1);
        if (i === quantidade - 1 || end > b) { end = new Date(b.getTime()); }
        saida.push({ data_inicio: formatIso(ini), data_fim: formatIso(end) });
        cursor = new Date(end.getTime());
        cursor.setDate(cursor.getDate() + 1);
    }
    return saida;
}

function calendarioPadrao(tipo, ano, quantidade) {
    var saida = [];
    for (var n = 1; n <= quantidade; n++) {
        var mesInicio;
        var alcance;
        if (tipo === 'etapa_unica') {
            mesInicio = 1;
            alcance = 11;
        } else if (tipo === 'semestre') {
            mesInicio = n === 1 ? 1 : 7;
            alcance = 5;
        } else if (tipo === 'trimestre') {
            mesInicio = (n - 1) * 4 + 1;
            alcance = 3;
        } else {
            mesInicio = (n - 1) * 3 + 1;
            alcance = 2;
        }
        var mesFim = mesInicio + alcance;
        var fimDia = new Date(ano, mesFim, 0).getDate();
        saida.push({
            data_inicio: formatIso(new Date(ano, mesInicio - 1, 1)),
            data_fim: formatIso(new Date(ano, mesFim - 1, fimDia))
        });
    }
    return saida;
}

function sugerirDatas(tipo) {
    var info = PERIODOS_LETIVOS[tipo] || {};
    var chaves = Object.keys(info.rotulos || {});
    var inicio = document.getElementById('al_data_inicio').value;
    var fim = document.getElementById('al_data_fim').value;
    var ano = parseInt(document.getElementById('al_ano').value, 10);
    if (inicio && fim && fim >= inicio) {
        return dividirIntervalo(inicio, fim, chaves.length);
    }
    if (ano >= 2000 && ano <= 2100) {
        return calendarioPadrao(tipo, ano, chaves.length);
    }
    return [];
}

function atualizarLimitesPeriodos() {
    var inicio = document.getElementById('al_data_inicio').value;
    var fim = document.getElementById('al_data_fim').value;
    var limitar = inicio && fim && fim >= inicio;
    document.querySelectorAll('#al-periodos input[type="date"]').forEach(function (input) {
        if (limitar) {
            input.min = inicio;
            input.max = fim;
        } else {
            input.removeAttribute('min');
            input.removeAttribute('max');
        }
    });
    document.querySelectorAll('#al-periodos input[data-dias-letivos]').forEach(function (input) {
        var bloco = input.closest('.grid');
        var datas = bloco ? bloco.querySelectorAll('input[type="date"]') : [];
        var teto = 366;
        if (datas.length >= 2 && datas[0].value && datas[1].value && datas[1].value >= datas[0].value) {
            var a = parseIso(datas[0].value);
            var b = parseIso(datas[1].value);
            if (a && b) {
                teto = Math.round((b.getTime() - a.getTime()) / 86400000) + 1;
            }
        }
        input.max = String(Math.max(1, teto));
    });
}

function renderPeriodos(tipo, salvos) {
    var box = document.getElementById('al-periodos');
    var titulo = document.getElementById('al-periodos-titulo');
    if (!box || !titulo) { return; }
    var info = PERIODOS_LETIVOS[tipo] || PERIODOS_LETIVOS.bimestre || { rotulos: {}, campo: 'Período' };
    var rotulos = info.rotulos || {};
    var chaves = Object.keys(rotulos).map(function (k) { return parseInt(k, 10); }).filter(function (n) { return n > 0; });
    chaves.sort(function (a, b) { return a - b; });
    var campo = String(info.campo || 'período').toLowerCase();
    titulo.textContent = chaves.length === 1 ? 'Datas da etapa' : ('Datas de cada ' + campo);
    var mapa = {};
    var usarSalvos = Array.isArray(salvos) && salvos.length > 0;
    if (usarSalvos) {
        salvos.forEach(function (p) { mapa[parseInt(p.numero, 10)] = p; });
    }
    var sugestao = usarSalvos ? [] : sugerirDatas(tipo);
    box.innerHTML = '';
    chaves.forEach(function (n, idx) {
        var salvo = mapa[n] || {};
        var sug = sugestao[idx] || {};
        var ini = soData(salvo.data_inicio || sug.data_inicio || '');
        var fim = soData(salvo.data_fim || sug.data_fim || '');
        var rotulo = rotulos[n] || rotulos[String(n)] || (n + 'º período');
        var dias = salvo.dias_letivos != null && salvo.dias_letivos !== '' ? String(salvo.dias_letivos) : '';
        var row = document.createElement('div');
        row.className = 'px-3 py-3';
        var nome = document.createElement('div');
        nome.className = 'text-sm font-medium text-gray-800 mb-2';
        nome.textContent = rotulo;
        var campos = document.createElement('div');
        campos.className = 'grid grid-cols-1 sm:grid-cols-3 gap-3';
        campos.appendChild(campoDataPeriodo('Data início', 'periodos[' + n + '][data_inicio]', 'al_periodo_' + n + '_inicio', ini));
        campos.appendChild(campoDataPeriodo('Data fim', 'periodos[' + n + '][data_fim]', 'al_periodo_' + n + '_fim', fim));
        campos.appendChild(campoNumeroPeriodo('Dias letivos', 'periodos[' + n + '][dias_letivos]', 'al_periodo_' + n + '_dias', dias));
        row.appendChild(nome);
        row.appendChild(campos);
        box.appendChild(row);
    });
    atualizarLimitesPeriodos();
}

function campoDataPeriodo(rotulo, name, id, valor) {
    var wrap = document.createElement('div');
    var label = document.createElement('label');
    label.htmlFor = id;
    label.className = 'block text-xs font-medium text-gray-600 mb-1';
    label.textContent = rotulo;
    var input = document.createElement('input');
    input.type = 'date';
    input.id = id;
    input.name = name;
    input.required = true;
    input.value = valor;
    input.className = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500';
    wrap.appendChild(label);
    wrap.appendChild(input);
    return wrap;
}

function campoNumeroPeriodo(rotulo, name, id, valor) {
    var wrap = document.createElement('div');
    var label = document.createElement('label');
    label.htmlFor = id;
    label.className = 'block text-xs font-medium text-gray-600 mb-1';
    label.textContent = rotulo;
    var input = document.createElement('input');
    input.type = 'number';
    input.id = id;
    input.name = name;
    input.required = true;
    input.min = '1';
    input.max = '366';
    input.setAttribute('data-dias-letivos', '1');
    input.step = '1';
    input.inputMode = 'numeric';
    input.value = valor;
    input.className = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500';
    wrap.appendChild(label);
    wrap.appendChild(input);
    return wrap;
}

function aoMudarLimitesDoAno() {
    if (periodosManuais) {
        atualizarLimitesPeriodos();
        return;
    }
    renderPeriodos(document.getElementById('al_periodo_tipo').value, null);
}

function prepararDivisao(tipo, temCadastros, usados, periodosSalvos) {
    var sel = document.getElementById('al_periodo_tipo');
    var aviso = document.getElementById('al_periodo_tipo_aviso');
    sel.disabled = false;
    sel.value = tipo || 'bimestre';
    anoLetivoEdicao.original = sel.value || 'bimestre';
    anoLetivoEdicao.usados = Array.isArray(usados) ? usados : [];
    anoLetivoEdicao.periodosSalvos = Array.isArray(periodosSalvos) ? periodosSalvos : [];
    periodosManuais = anoLetivoEdicao.periodosSalvos.length > 0;
    limparMapeamento();
    renderPeriodos(sel.value, periodosManuais ? anoLetivoEdicao.periodosSalvos : null);
    if (temCadastros && anoLetivoEdicao.usados.length) {
        var nomes = anoLetivoEdicao.usados.map(function (p) { return p.rotulo; }).join(', ');
        aviso.textContent = 'Já existem cadastros neste ano (' + nomes + '). Ao mudar a divisão, você indica para qual período novo cada um vai.';
        aviso.classList.remove('hidden');
    } else {
        aviso.textContent = '';
        aviso.classList.add('hidden');
    }
}

function rotuloTipo(cod) {
    var info = PERIODOS_LETIVOS[cod];
    return info ? info.nome : cod;
}

function abrirModalMapeamento(salvarDepois) {
    var novo = document.getElementById('al_periodo_tipo').value;
    var info = PERIODOS_LETIVOS[novo] || { rotulos: {} };
    var rotulos = info.rotulos || {};
    var chaves = Object.keys(rotulos);
    salvarAoConfirmarMapa = !!salvarDepois;
    document.getElementById('modalMapaPeriodosTexto').textContent =
        'O ano passa de ' + rotuloTipo(anoLetivoEdicao.original) + ' para ' + rotuloTipo(novo) + '. Pode juntar dois períodos no mesmo destino, como o 3º e o 4º bimestre no 3º trimestre. Onde a turma já existe nos dois, fica o cadastro do período que já ocupa esse lugar.';
    var lista = document.getElementById('modalMapaPeriodosLista');
    lista.innerHTML = '';
    anoLetivoEdicao.usados.forEach(function (p) {
        var origem = parseInt(p.numero, 10);
        var row = document.createElement('div');
        row.className = 'grid grid-cols-1 sm:grid-cols-[1fr_auto_1fr] gap-2 items-center';
        var de = document.createElement('div');
        de.className = 'text-sm font-medium text-gray-800';
        de.textContent = p.rotulo || (origem + 'º');
        var seta = document.createElement('div');
        seta.className = 'text-gray-400 text-center hidden sm:block';
        seta.textContent = '→';
        var sel = document.createElement('select');
        sel.className = 'w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm';
        sel.setAttribute('data-origem', String(origem));
        sel.required = true;
        chaves.forEach(function (k) {
            var opt = document.createElement('option');
            opt.value = k;
            opt.textContent = rotulos[k];
            if (parseInt(k, 10) === origem || (origem > chaves.length && parseInt(k, 10) === chaves.length)) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });
        row.appendChild(de);
        row.appendChild(seta);
        row.appendChild(sel);
        lista.appendChild(row);
    });
    document.getElementById('mapa-periodos-confirmar').textContent = salvarAoConfirmarMapa ? 'Confirmar e salvar' : 'Confirmar';
    document.getElementById('modalMapaPeriodos').classList.remove('hidden');
}

function fecharModalMapeamento() {
    document.getElementById('modalMapaPeriodos').classList.add('hidden');
    salvarAoConfirmarMapa = false;
}

function cancelarMapaPeriodos() {
    var salvar = salvarAoConfirmarMapa;
    fecharModalMapeamento();
    if (!salvar && !anoLetivoEdicao.mapaPronto) {
        document.getElementById('al_periodo_tipo').value = anoLetivoEdicao.original;
        limparMapeamento();
        periodosManuais = anoLetivoEdicao.periodosSalvos.length > 0;
        renderPeriodos(
            anoLetivoEdicao.original,
            periodosManuais ? anoLetivoEdicao.periodosSalvos : null
        );
    }
}

function confirmarMapaPeriodos() {
    var selects = document.querySelectorAll('#modalMapaPeriodosLista select[data-origem]');
    var box = document.getElementById('al-mapeamento-fields');
    box.innerHTML = '';
    var ok = true;
    selects.forEach(function (sel) {
        if (!sel.value) { ok = false; return; }
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'mapeamento[' + sel.getAttribute('data-origem') + ']';
        input.value = sel.value;
        box.appendChild(input);
    });
    if (!ok) {
        alert('Escolha o período novo de cada linha.');
        return;
    }
    anoLetivoEdicao.mapaPronto = true;
    var salvar = salvarAoConfirmarMapa;
    fecharModalMapeamento();
    if (salvar) { enviarAnoLetivo(); }
}

function openAnoLetivoDrawer(id) {
    var form = document.getElementById('ano-letivo-form');
    form.reset();
    document.getElementById('al_id').value = '';
    document.getElementById('al_ativo').checked = true;
    anoLetivoEdicao.usados = [];
    prepararDivisao('bimestre', false, []);

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('anoLetivoDrawerTitle').textContent = 'Novo Ano Letivo';
        document.getElementById('al-form-submit-label').textContent = 'Salvar';
        showAnoLetivoDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('anoLetivoDrawerTitle').textContent = 'Editar Ano Letivo';
    document.getElementById('al-form-submit-label').textContent = 'Salvar Alterações';
    showAnoLetivoDrawer();

    fetch('<?= URL ?>/admin/ano-letivo/' + id + '/dados')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { alert('Erro: ' + (data.error || '')); closeAnoLetivoDrawer(); return; }
            document.getElementById('al_id').value = data.item.id;
            document.getElementById('al_ano').value = data.item.ano;
            document.getElementById('al_ativo').checked = !!parseInt(data.item.ativo, 10);
            document.getElementById('al_data_inicio').value = soData(data.item.data_inicio);
            document.getElementById('al_data_fim').value = soData(data.item.data_fim);
            prepararDivisao(
                data.item.periodo_tipo || 'bimestre',
                !!data.tem_cadastros,
                data.periodos_usados || [],
                data.item.periodos || []
            );
        })
        .catch(function () { alert('Erro de conexão.'); closeAnoLetivoDrawer(); });
}

function showAnoLetivoDrawer() {
    document.getElementById('anoLetivoDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('anoLetivoDrawer');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
}

function closeAnoLetivoDrawer() {
    fecharModalMapeamento();
    var drawer = document.getElementById('anoLetivoDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    setTimeout(function () { document.getElementById('anoLetivoDrawerBackdrop').classList.add('hidden'); }, 300);
}

function enviarAnoLetivo() {
    var form = document.getElementById('ano-letivo-form');
    var mode = form.dataset.mode;
    var id = document.getElementById('al_id').value;
    var url = mode === 'create' ? '<?= URL ?>/admin/ano-letivo' : '<?= URL ?>/admin/ano-letivo/' + id + '/update';
    var fd = new FormData(form);
    fd.set('periodo_tipo', document.getElementById('al_periodo_tipo').value);
    fetch(url, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) { window.location.reload(); }
            else { alert('Erro: ' + (result.error || 'Não foi possível salvar.')); }
        })
        .catch(function () { alert('Erro de conexão. Tente novamente.'); });
}

document.getElementById('al_periodo_tipo').addEventListener('change', function () {
    var form = document.getElementById('ano-letivo-form');
    limparMapeamento();
    if (this.value === anoLetivoEdicao.original && anoLetivoEdicao.periodosSalvos.length) {
        periodosManuais = true;
        renderPeriodos(this.value, anoLetivoEdicao.periodosSalvos);
    } else {
        periodosManuais = false;
        renderPeriodos(this.value, null);
    }
    if (form.dataset.mode !== 'edit') { return; }
    if (this.value === anoLetivoEdicao.original || !anoLetivoEdicao.usados.length) { return; }
    abrirModalMapeamento(false);
});

['al_ano', 'al_data_inicio', 'al_data_fim'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', aoMudarLimitesDoAno);
});

document.getElementById('al-periodos').addEventListener('input', function () {
    periodosManuais = true;
});
document.getElementById('al-periodos').addEventListener('change', function () {
    atualizarLimitesPeriodos();
});

document.getElementById('ano-letivo-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var sel = document.getElementById('al_periodo_tipo');
    if (this.dataset.mode === 'edit' && sel.value !== anoLetivoEdicao.original && anoLetivoEdicao.usados.length && !anoLetivoEdicao.mapaPronto) {
        abrirModalMapeamento(true);
        return;
    }
    enviarAnoLetivo();
});

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var modal = document.getElementById('modalMapaPeriodos');
    if (modal && !modal.classList.contains('hidden')) {
        cancelarMapaPeriodos();
        return;
    }
    closeAnoLetivoDrawer();
});
</script>
<?php endif; ?>
