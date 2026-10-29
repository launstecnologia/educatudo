<?php
$ano = (int) ($ano ?? date('Y'));
$boletimId = (int) ($boletim_id ?? 0);
$boletimNome = (string) ($boletim_nome ?? 'Boletim');
$boletimFinalidade = ((string) ($boletim_finalidade ?? 'oficial')) === 'complementar' ? 'Modelo extra' : 'Modelo oficial';
$catalogo = is_array($catalogo ?? null) ? $catalogo : [];
$linhas = is_array($linhas ?? null) ? $linhas : [];
$formulaFinal = (string) ($formula_final_media ?? '');
$variaveisMedia = is_array($variaveis_media ?? null) ? $variaveis_media : [];
$schemaPronto = !empty($schema_pronto);
$csrf_token = (string) ($csrf_token ?? '');
$linhasJs = [];
foreach ($linhas as $linha) {
    $colunasLinha = [];
    foreach ((array) ($linha['colunas'] ?? []) as $codigoLinha) {
        $codigoLinha = (string) $codigoLinha;
        if ($codigoLinha !== '') {
            $colunasLinha[] = $codigoLinha;
        }
    }
    $linhasJs[] = [
        'nome' => (string) ($linha['nome'] ?? ''),
        'colunas' => $colunasLinha,
    ];
}
$exemplos = [
    'media' => '7,5',
    'faltas' => '2',
    'rec' => '—',
    'situacao' => 'Aprovado',
];

$page_header_back_url = URL . '/admin/eventos-avaliativos/boletim?ano=' . $ano;
$page_header_back_label = 'Voltar ao boletim';
$page_header_title = 'Layout — ' . $boletimNome;
$page_header_subtitle = $boletimFinalidade . ' · ' . $ano;
$ui_wizard_steps = [
    ['label' => 'Linhas', 'sub' => 'Colunas'],
    ['label' => 'Fórmula', 'sub' => 'Média'],
    ['label' => 'Revisão', 'sub' => 'Quadro'],
];
$ui_wizard_current = 1;
$ui_wizard_nav_id = 'boletim-layout-steps';
?>
<nav aria-label="Caminho" class="mb-3 text-sm text-gray-500 flex flex-wrap items-center gap-1">
    <a href="<?= URL ?>/admin/eventos-avaliativos/boletim?ano=<?= $ano ?>" class="hover:text-gray-800">Boletim</a>
    <span aria-hidden="true">/</span>
    <span class="text-gray-900">Layout</span>
</nav>
<?php
include \App\Core\ViewPaths::adminPartial('page_header_form.php');
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code class="text-sm">2026_10_28_boletim_layouts.sql</code> no painel Master antes de salvar. O desenho abaixo já pode ser montado.
</div>
<?php endif; ?>

<div class="space-y-6">
    <div class="p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">
        <i class="fa-solid fa-circle-info mr-2"></i>
        Monte o quadro do boletim por etapas. Cada linha vira uma faixa de colunas. A fórmula entra só na média final.
    </div>
    <?php include \App\Core\ViewPaths::adminPartial('ui/wizard_steps.php'); ?>

<form method="POST" action="<?= URL ?>/admin/eventos-avaliativos/boletim/<?= $boletimId ?>" id="boletim-layout-form">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="ano" value="<?= $ano ?>">
    <div id="boletim-campos"></div>

    <div class="bg-white rounded-xl shadow-lg p-6 w-full min-h-[24rem] flex flex-col">
        <div data-layout-passo="1" class="flex-1 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Linhas do quadro</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Clique na coluna para colocá-la na faixa marcada. O × devolve a coluna para a lista.</p>
                </div>
                <button type="button" id="boletim-nova-linha" class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                    + Criar linha
                </button>
            </div>
            <div class="grid items-start gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Colunas</p>
                    <div id="boletim-paleta" class="space-y-2"></div>
                </div>
                <div id="boletim-linhas" class="space-y-3 md:col-span-2"></div>
            </div>
        </div>

        <div data-layout-passo="2" class="hidden flex-1 space-y-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Cálculo da média final</h3>
                <p class="text-sm text-gray-500 mt-0.5">Em branco, a média final é a média simples dos períodos. Arraste as médias e os sinais.</p>
            </div>
            <div id="boletim-f-paleta" class="bw-palette flex flex-wrap gap-2"></div>
            <div class="flex flex-wrap items-center gap-2">
                <input type="number" id="boletim-f-numero" min="0" max="1000" step="0.01" placeholder="Número"
                       class="w-28 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <button type="button" id="boletim-f-numero-add" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Colocar número</button>
                <button type="button" id="boletim-f-limpar" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Limpar</button>
            </div>
            <div id="boletim-f-canvas" class="bw-canvas"></div>
            <p id="boletim-f-preview" class="text-sm text-gray-600">Monte a fórmula acima. Ex.: (m1 + m2 + m3 + m4) / 4.</p>
            <input type="hidden" name="formula_final_media" id="formula-final-media" maxlength="255" value="<?= htmlspecialchars($formulaFinal, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div data-layout-passo="3" class="hidden flex-1 space-y-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Confira o quadro de notas</h3>
                <p class="text-sm text-gray-500 mt-0.5">Números de exemplo. A faixa de cima é a linha que você nomeou. Para mudar ordem ou fórmula, volte no passo.</p>
            </div>
            <div class="overflow-x-auto" id="boletim-preview"></div>
        </div>

        <div class="flex items-center justify-between pt-6 mt-6 border-t border-gray-100">
            <button type="button" id="boletim-layout-voltar" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">
                <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
            </button>
            <div class="flex gap-2">
                <button type="button" id="boletim-layout-avancar" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    Continuar <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
                <button type="submit" id="boletim-layout-salvar" class="hidden btn-primary-custom px-6 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 shadow-sm">Salvar layout</button>
            </div>
        </div>
    </div>
</form>
</div>

<style>
.bw-chip { display: inline-flex; align-items: center; gap: 4px; padding: 6px 10px; border-radius: 9999px; border: 1px solid; font-size: 13px; line-height: 1.2; user-select: none; cursor: grab; }
.bw-chip:active { cursor: grabbing; }
.bw-chip-peca { background: #eef2ff; border-color: #c7d2fe; color: #3730a3; font-weight: 600; }
.bw-chip-op { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; min-width: 2rem; justify-content: center; }
.bw-chip-num { background: #ecfdf5; border-color: #a7f3d0; color: #047857; font-weight: 600; }
.bw-chip-x { margin-left: 2px; color: #64748b; font-size: 14px; line-height: 1; padding: 0 2px; }
.bw-chip-x:hover { color: #b91c1c; }
.bw-palette .bw-chip { cursor: pointer; }
.bw-canvas { min-height: 96px; border: 2px dashed #c7d2fe; border-radius: 0.75rem; padding: 10px; display: flex; flex-wrap: wrap; align-content: flex-start; gap: 6px; background: #f8fafc; }
.bw-preview-table { border-collapse: collapse; width: 100%; font-size: 11px; text-align: center; }
.bw-preview-table th { background: var(--sidebar-bg-color, #1e3a5f); color: var(--sidebar-text-color, #fff); border: 1px solid color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 75%, #000); padding: 4px 6px; font-weight: 600; }
.bw-preview-table.boletim th { text-transform: uppercase; letter-spacing: .02em; }
.bw-preview-table.boletim th.sub { background: color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 18%, #fff); color: var(--sidebar-bg-color, #1e3a5f); text-transform: none; font-weight: 700; }
.bw-preview-table.boletim th.mat { text-transform: none; letter-spacing: 0; }
.bw-preview-table td { border: 1px solid #d1d5db; padding: 3px 5px; }
.bw-preview-table td.mat { text-align: left; font-weight: 600; color: #111827; white-space: nowrap; }
.bw-preview-table.boletim tr:nth-child(even) td { background: #f8fafc; }
.bw-linha-faixa { background: var(--sidebar-bg-color, #1e3a5f); color: var(--sidebar-text-color, #fff); }
.bw-linha-faixa input { background: transparent; color: inherit; }
.bw-linha-faixa input::placeholder { color: color-mix(in srgb, var(--sidebar-text-color, #fff) 65%, transparent); }
.bw-linha-ativa { box-shadow: 0 0 0 2px #0d9488; }
</style>

<script>
(function () {
    var catalogo = <?= json_encode(array_values($catalogo), JSON_UNESCAPED_UNICODE) ?>;
    var linhas = <?= json_encode($linhasJs, JSON_UNESCAPED_UNICODE) ?>;
    var variaveis = <?= json_encode(array_values($variaveisMedia), JSON_UNESCAPED_UNICODE) ?>;
    var exemplos = <?= json_encode($exemplos, JSON_UNESCAPED_UNICODE) ?>;
    var formulaInicial = <?= json_encode($formulaFinal, JSON_UNESCAPED_UNICODE) ?>;
    var porCodigo = {};
    catalogo.forEach(function (coluna) { porCodigo[coluna.codigo] = coluna; });
    var linhaAtiva = 0;
    var arraste = null;
    var tokens = lerFormula(formulaInicial);
    var paleta = document.getElementById('boletim-paleta');
    var lista = document.getElementById('boletim-linhas');
    var campos = document.getElementById('boletim-campos');
    var campoFormula = document.getElementById('formula-final-media');
    var paletaFormula = document.getElementById('boletim-f-paleta');
    var canvasFormula = document.getElementById('boletim-f-canvas');
    var previewFormula = document.getElementById('boletim-f-preview');

    function peca(codigo) {
        var partes = String(codigo || '').split('_');
        return partes[partes.length - 1] || '';
    }

    function escapar(texto) {
        return String(texto).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function usada(codigo) {
        return linhas.some(function (linha) { return linha.colunas.indexOf(codigo) >= 0; });
    }

    function sincronizarCampos() {
        campos.innerHTML = '';
        linhas.forEach(function (linha, indice) {
            var nome = document.createElement('input');
            nome.type = 'hidden';
            nome.name = 'linhas[' + indice + '][nome]';
            nome.value = linha.nome || '';
            campos.appendChild(nome);
            linha.colunas.forEach(function (codigo) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'linhas[' + indice + '][colunas][]';
                input.value = codigo;
                campos.appendChild(input);
            });
        });
    }

    function desenharPreview() {
        var grupos = [];
        linhas.forEach(function (linha) {
            var cols = [];
            linha.colunas.forEach(function (codigo) {
                if (!porCodigo[codigo]) return;
                cols.push(porCodigo[codigo]);
            });
            if (!String(linha.nome || '').trim() || !cols.length) return;
            grupos.push({ nome: String(linha.nome).trim(), cols: cols });
        });
        var caixa = document.getElementById('boletim-preview');
        if (!grupos.length) {
            caixa.innerHTML = '<p class="text-sm text-gray-500">Crie uma linha e solte as colunas para ver o boletim.</p>';
            return;
        }
        var mediaFinal = valorMediaFinal();
        var materias = ['Língua Portuguesa', 'Matemática', 'História'];
        var html = '<div class="overflow-x-auto border border-gray-300 rounded-lg bg-white">';
        html += '<table class="bw-preview-table boletim"><thead><tr>';
        html += '<th class="mat text-left" rowspan="2">Matérias</th>';
        grupos.forEach(function (grupo) {
            html += '<th colspan="' + grupo.cols.length + '">' + escapar(grupo.nome) + '</th>';
        });
        html += '</tr><tr>';
        grupos.forEach(function (grupo) {
            grupo.cols.forEach(function (coluna) {
                html += '<th class="sub">' + escapar(coluna.nome) + '</th>';
            });
        });
        html += '</tr></thead><tbody>';
        materias.forEach(function (materia) {
            html += '<tr><td class="mat">' + escapar(materia) + '</td>';
            grupos.forEach(function (grupo) {
                grupo.cols.forEach(function (coluna) {
                    var texto = exemplos[peca(coluna.codigo)] || '—';
                    if (coluna.codigo === 'final_media') texto = mediaFinal || '—';
                    html += '<td>' + escapar(texto) + '</td>';
                });
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        caixa.innerHTML = html;
    }

    function desenharPaleta() {
        paleta.innerHTML = '';
        var grupos = [];
        var porGrupo = {};
        catalogo.forEach(function (coluna) {
            if (usada(coluna.codigo)) return;
            if (!porGrupo[coluna.grupo]) {
                porGrupo[coluna.grupo] = [];
                grupos.push(coluna.grupo);
            }
            porGrupo[coluna.grupo].push(coluna);
        });
        if (!grupos.length) {
            var fim = document.createElement('p');
            fim.className = 'text-sm text-gray-500';
            fim.textContent = 'Todas as colunas já estão em uma faixa.';
            paleta.appendChild(fim);
            return;
        }
        grupos.forEach(function (grupo) {
            var caixa = document.createElement('div');
            caixa.className = 'rounded-lg border border-gray-200 bg-white px-3 py-2';
            var titulo = document.createElement('p');
            titulo.className = 'mb-1.5 text-xs font-semibold text-gray-700';
            titulo.textContent = grupo;
            caixa.appendChild(titulo);
            var faixa = document.createElement('div');
            faixa.className = 'flex flex-wrap gap-1.5';
            porGrupo[grupo].forEach(function (coluna) {
                var botao = document.createElement('button');
                botao.type = 'button';
                botao.draggable = true;
                botao.className = 'bw-chip bw-chip-peca';
                botao.textContent = coluna.nome;
                botao.title = 'Colocar em ' + (linhas[linhaAtiva] && linhas[linhaAtiva].nome ? linhas[linhaAtiva].nome : 'a faixa marcada');
                botao.addEventListener('dragstart', function (evento) {
                    arraste = { origem: 'paleta', codigo: coluna.codigo };
                    evento.dataTransfer.setData('text/plain', coluna.codigo);
                });
                botao.addEventListener('click', function () {
                    if (linhas[linhaAtiva] && !String(linhas[linhaAtiva].nome || '').trim()) {
                        linhas[linhaAtiva].nome = coluna.grupo;
                    }
                    colocar(coluna.codigo, linhaAtiva);
                });
                faixa.appendChild(botao);
            });
            caixa.appendChild(faixa);
            paleta.appendChild(caixa);
        });
    }

    function desenharLinhas() {
        lista.innerHTML = '';
        if (!linhas.length) {
            var vazio = document.createElement('p');
            vazio.className = 'text-sm text-gray-500';
            vazio.textContent = 'Nenhuma linha ainda. Clique em Criar linha.';
            lista.appendChild(vazio);
            return;
        }
        linhas.forEach(function (linha, indice) {
            var bloco = document.createElement('div');
            bloco.className = 'overflow-hidden rounded-xl border border-gray-200 bg-white' + (indice === linhaAtiva ? ' bw-linha-ativa' : '');
            var topo = document.createElement('div');
            topo.className = 'bw-linha-faixa flex items-center gap-2 px-3 py-2';
            var input = document.createElement('input');
            input.type = 'text';
            input.maxLength = 40;
            input.value = linha.nome || '';
            input.placeholder = 'Nome da faixa';
            input.setAttribute('aria-label', 'Nome da faixa');
            input.className = 'min-w-0 flex-1 border-0 px-1 py-1 text-sm font-semibold focus:outline-none focus:ring-0';
            input.addEventListener('focus', function () {
                if (linhaAtiva === indice) return;
                linhaAtiva = indice;
                desenharPaleta();
                lista.querySelectorAll('.bw-linha-ativa').forEach(function (el) { el.classList.remove('bw-linha-ativa'); });
                bloco.classList.add('bw-linha-ativa');
            });
            input.addEventListener('input', function () {
                linhas[indice].nome = input.value;
                sincronizarCampos();
                desenharPreview();
            });
            var remover = document.createElement('button');
            remover.type = 'button';
            remover.className = 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white/80 hover:bg-white/10 hover:text-white';
            remover.title = 'Remover faixa';
            remover.innerHTML = '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>';
            remover.addEventListener('click', function () {
                linhas.splice(indice, 1);
                if (linhaAtiva >= linhas.length) linhaAtiva = Math.max(0, linhas.length - 1);
                desenhar();
            });
            topo.appendChild(input);
            topo.appendChild(remover);
            var canvas = document.createElement('div');
            canvas.className = 'flex min-h-[3.25rem] flex-wrap';
            canvas.addEventListener('dragover', function (evento) { evento.preventDefault(); });
            canvas.addEventListener('drop', function (evento) {
                evento.preventDefault();
                if (!arraste || !arraste.codigo) return;
                if (!String(linhas[indice].nome || '').trim() && porCodigo[arraste.codigo]) {
                    linhas[indice].nome = porCodigo[arraste.codigo].grupo;
                }
                colocar(arraste.codigo, indice, arraste.posicao);
                arraste = null;
            });
            canvas.addEventListener('click', function () {
                linhaAtiva = indice;
                desenhar();
            });
            if (!linha.colunas.length) {
                var dica = document.createElement('p');
                dica.className = 'px-3 py-3 text-sm text-gray-400';
                dica.textContent = 'Clique em Média, Faltas, Recuperação ou Situação.';
                canvas.appendChild(dica);
            }
            linha.colunas.forEach(function (codigo, posicao) {
                var coluna = porCodigo[codigo];
                if (!coluna) return;
                var celula = document.createElement('div');
                celula.draggable = true;
                celula.className = 'flex min-w-[5.5rem] flex-1 items-center justify-center gap-1 px-2 py-3 text-sm font-medium text-gray-800' + (posicao > 0 ? ' border-l border-gray-200' : '');
                celula.addEventListener('dragstart', function () {
                    arraste = { origem: 'linha', codigo: codigo, linha: indice, posicao: posicao };
                });
                var nome = document.createElement('span');
                nome.textContent = coluna.nome;
                var fechar = document.createElement('button');
                fechar.type = 'button';
                fechar.className = 'text-gray-400 hover:text-red-600';
                fechar.textContent = '×';
                fechar.title = 'Tirar da faixa';
                fechar.addEventListener('click', function (evento) {
                    evento.stopPropagation();
                    linha.colunas.splice(posicao, 1);
                    desenhar();
                });
                celula.appendChild(nome);
                celula.appendChild(fechar);
                canvas.appendChild(celula);
            });
            bloco.appendChild(topo);
            bloco.appendChild(canvas);
            lista.appendChild(bloco);
        });
    }

    function colocar(codigo, indiceLinha, posicaoOrigem) {
        if (!porCodigo[codigo]) return;
        if (!linhas.length) {
            linhas.push({ nome: '', colunas: [] });
            indiceLinha = 0;
        }
        if (indiceLinha < 0 || indiceLinha >= linhas.length) indiceLinha = linhas.length - 1;
        linhas.forEach(function (linha) {
            linha.colunas = linha.colunas.filter(function (item) { return item !== codigo; });
        });
        var destino = linhas[indiceLinha].colunas;
        var posicao = typeof posicaoOrigem === 'number' ? posicaoOrigem : destino.length;
        if (posicao > destino.length) posicao = destino.length;
        destino.splice(posicao, 0, codigo);
        linhaAtiva = indiceLinha;
        desenhar();
    }

    function desenhar() {
        sincronizarCampos();
        desenharPaleta();
        desenharLinhas();
        desenharPreview();
        desenharFormula();
    }

    function lerFormula(texto) {
        var saida = [];
        var exp = /m\d+|\d+(?:[.,]\d+)?|[()+*/,-]/g;
        var achado;
        var bruto = String(texto || '');
        while ((achado = exp.exec(bruto))) saida.push(achado[0]);
        var limpo = bruto.replace(/\s/g, '');
        var consumido = saida.join('');
        if (limpo !== '' && consumido !== limpo) return [];
        return saida;
    }

    function compilarFormula() {
        return tokens.join(' ');
    }

    function rotuloFormula(token) {
        var variavel = variaveis.filter(function (item) { return item.token === token; })[0];
        if (variavel) return variavel.token + ' · ' + variavel.rotulo;
        if (token === '*') return '×';
        if (token === '/') return '÷';
        if (token === '-') return '−';
        return token;
    }

    function chipFormula(token, daPaleta, indice) {
        var el = document.createElement(daPaleta ? 'button' : 'span');
        if (daPaleta) el.type = 'button';
        var cor = /^m\d+$/.test(token) ? 'bw-chip-peca' : (/^\d/.test(token) ? 'bw-chip-num' : 'bw-chip-op');
        el.className = 'bw-chip ' + cor;
        el.draggable = true;
        el.textContent = rotuloFormula(token);
        el.addEventListener('dragstart', function (evento) {
            arraste = daPaleta ? { origem: 'formula-paleta', token: token } : { origem: 'formula', indice: indice, token: token };
            evento.dataTransfer.setData('text/plain', token);
        });
        if (daPaleta) {
            el.addEventListener('click', function () { tokens.push(token); desenharFormula(); desenharPreview(); });
            return el;
        }
        var fechar = document.createElement('button');
        fechar.type = 'button';
        fechar.className = 'bw-chip-x';
        fechar.textContent = '×';
        fechar.addEventListener('click', function () {
            tokens.splice(indice, 1);
            desenharFormula();
            desenharPreview();
        });
        el.appendChild(fechar);
        return el;
    }

    function desenharFormula() {
        campoFormula.value = compilarFormula();
        paletaFormula.innerHTML = '';
        variaveis.forEach(function (variavel) {
            paletaFormula.appendChild(chipFormula(variavel.token, true));
        });
        ['(', ')', '+', '-', '*', '/'].forEach(function (sinal) {
            paletaFormula.appendChild(chipFormula(sinal, true));
        });
        canvasFormula.innerHTML = '';
        if (!tokens.length) {
            var vazio = document.createElement('p');
            vazio.className = 'text-sm text-gray-400 px-1 py-2';
            vazio.textContent = 'Solte as médias e os sinais aqui.';
            canvasFormula.appendChild(vazio);
            previewFormula.textContent = 'Monte a fórmula acima. Ex.: (m1 + m2 + m3 + m4) / 4.';
            return;
        }
        tokens.forEach(function (token, indice) {
            canvasFormula.appendChild(chipFormula(token, false, indice));
        });
        previewFormula.textContent = campoFormula.value;
    }

    function valorMediaFinal() {
        var formula = compilarFormula();
        var notaExemplo = 7.5;
        if (!formula) return notaExemplo.toFixed(1).replace('.', ',');
        var expr = formula;
        variaveis.slice().sort(function (a, b) {
            return String(b.token).length - String(a.token).length;
        }).forEach(function (variavel) {
            expr = expr.replace(new RegExp('\\b' + variavel.token + '\\b', 'g'), String(notaExemplo));
        });
        expr = expr.replace(/,/g, '.');
        if (!/^[0-9+\-*/().\s]+$/.test(expr)) return null;
        var atual = '';
        try {
            atual = Function('"use strict"; return (' + expr + ');')();
        } catch (erro) {
            return null;
        }
        if (typeof atual !== 'number' || !isFinite(atual)) return null;
        return atual.toFixed(1).replace('.', ',');
    }

    canvasFormula.addEventListener('dragover', function (evento) { evento.preventDefault(); });
    canvasFormula.addEventListener('drop', function (evento) {
        evento.preventDefault();
        if (!arraste) return;
        if (arraste.origem === 'formula') tokens.splice(arraste.indice, 1);
        if (arraste.token) tokens.push(arraste.token);
        arraste = null;
        desenharFormula();
        desenharPreview();
    });
    document.getElementById('boletim-f-numero-add').addEventListener('click', function () {
        var bruto = String(document.getElementById('boletim-f-numero').value || '').replace('.', ',');
        if (!/^\d+(?:,\d+)?$/.test(bruto)) return;
        tokens.push(bruto);
        desenharFormula();
        desenharPreview();
    });
    document.getElementById('boletim-f-limpar').addEventListener('click', function () {
        tokens = [];
        desenharFormula();
        desenharPreview();
    });
    document.getElementById('boletim-nova-linha').addEventListener('click', function () {
        linhas.push({ nome: '', colunas: [] });
        linhaAtiva = linhas.length - 1;
        desenhar();
    });
    var passoAtual = 1;
    var mapaPasso = {
        ativo: ['border-accent', 'bg-primary', 'text-primary', 'shadow-md'],
        completo: ['border-green-500', 'bg-green-50', 'text-green-700'],
        pendente: ['border-gray-200', 'bg-white', 'text-gray-600', 'hover:border-gray-300', 'hover:bg-gray-50']
    };
    var classesPasso = mapaPasso.ativo.concat(mapaPasso.completo, mapaPasso.pendente);

    function mostrarPasso(numero) {
        passoAtual = numero < 1 ? 1 : (numero > 3 ? 3 : numero);
        document.querySelectorAll('[data-layout-passo]').forEach(function (painel) {
            painel.classList.toggle('hidden', Number(painel.getAttribute('data-layout-passo')) !== passoAtual);
        });
        var voltar = document.getElementById('boletim-layout-voltar');
        var avancar = document.getElementById('boletim-layout-avancar');
        var salvar = document.getElementById('boletim-layout-salvar');
        voltar.disabled = passoAtual === 1;
        avancar.classList.toggle('hidden', passoAtual === 3);
        salvar.classList.toggle('hidden', passoAtual !== 3);
        var nav = document.getElementById('boletim-layout-steps');
        if (!nav) return;
        nav.querySelectorAll('.step-nav-btn').forEach(function (botao) {
            var n = parseInt(botao.getAttribute('data-step-target') || '0', 10);
            var estado = n === passoAtual ? 'ativo' : (n < passoAtual ? 'completo' : 'pendente');
            classesPasso.forEach(function (classe) { botao.classList.remove(classe); });
            mapaPasso[estado].forEach(function (classe) { botao.classList.add(classe); });
            botao.setAttribute('data-step-state', estado);
            var circulo = botao.querySelector('.wizard-step-circle');
            if (!circulo) return;
            var canto = circulo.querySelector('.wizard-step-corner');
            if (canto) canto.remove();
            if (estado !== 'completo') return;
            var selo = document.createElement('span');
            selo.className = 'wizard-step-corner absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full text-white text-[9px] bg-green-500';
            selo.innerHTML = '<i class="fa-solid fa-check"></i>';
            circulo.appendChild(selo);
        });
        nav.querySelectorAll('[data-connector-after]').forEach(function (linha) {
            var n = parseInt(linha.getAttribute('data-connector-after') || '0', 10);
            linha.classList.toggle('bg-green-400', n < passoAtual);
            linha.classList.toggle('bg-gray-200', n >= passoAtual);
        });
    }

    document.getElementById('boletim-layout-voltar').addEventListener('click', function () {
        mostrarPasso(passoAtual - 1);
    });
    document.getElementById('boletim-layout-avancar').addEventListener('click', function () {
        mostrarPasso(passoAtual + 1);
    });
    var navPassos = document.getElementById('boletim-layout-steps');
    if (navPassos) {
        navPassos.querySelectorAll('.step-nav-btn').forEach(function (botao) {
            botao.addEventListener('click', function () {
                mostrarPasso(parseInt(botao.getAttribute('data-step-target') || '1', 10));
            });
        });
    }
    document.getElementById('boletim-layout-form').addEventListener('submit', sincronizarCampos);
    desenhar();
    mostrarPasso(1);
})();
</script>
