<div id="rec-formula-box" class="sm:col-span-2 hidden">
    <label class="block text-sm font-medium text-gray-700 mb-1">Fórmula <span class="text-red-500">*</span></label>
    <p class="text-xs text-gray-500 mb-2">Clique ou arraste os blocos para a área. O × tira o bloco. Arraste de novo para mudar a ordem.</p>
    <div id="rec-f-paleta" class="flex flex-wrap gap-2 mb-3"></div>
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <input type="number" id="rec-f-numero" min="0" max="1000" step="0.01" placeholder="Número"
               class="w-28 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
        <button type="button" id="rec-f-numero-add" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Colocar número</button>
        <button type="button" id="rec-f-limpar" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Limpar</button>
    </div>
    <div id="rec-f-canvas" class="min-h-[4.5rem] rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-2 flex flex-wrap gap-2 items-start"></div>
    <p id="rec-f-preview" class="mt-2 text-sm text-gray-600">Monte a fórmula acima.</p>
    <input type="hidden" id="rec-formula" name="formula" form="recuperacao-form" maxlength="255" value="">
</div>
<script>
(function () {
    var paleta = [
        { type: 'var', value: 'media', label: 'Média' },
        { type: 'var', value: 'rec', label: 'REC' },
        { type: 'fn', value: 'max', label: 'maior' },
        { type: 'fn', value: 'min', label: 'menor' },
        { type: 'op', value: '+', label: '+' },
        { type: 'op', value: '-', label: '−' },
        { type: 'op', value: '*', label: '×' },
        { type: 'op', value: '/', label: '÷' },
        { type: 'op', value: '(', label: '(' },
        { type: 'op', value: ')', label: ')' },
        { type: 'op', value: ',', label: ',' }
    ];
    var tokens = [];
    var arraste = null;
    var paletaEl = document.getElementById('rec-f-paleta');
    var canvas = document.getElementById('rec-f-canvas');
    var preview = document.getElementById('rec-f-preview');
    var campo = document.getElementById('rec-formula');
    if (!paletaEl || !canvas || !campo) return;

    function rotulo(tok) {
        if (tok.type === 'fn') return tok.value === 'min' ? 'menor' : 'maior';
        if (tok.type === 'var') return tok.value === 'rec' ? 'REC' : 'Média';
        if (tok.value === '*') return '×';
        if (tok.value === '/') return '÷';
        if (tok.value === '-') return '−';
        return String(tok.label || tok.value || '');
    }

    function compilar() {
        return tokens.map(function (tok) {
            if (tok.type === 'fn') return tok.value + '(';
            return String(tok.value);
        }).join(' ');
    }

    function desenhar() {
        campo.value = compilar();
        paletaEl.innerHTML = '';
        paleta.forEach(function (tok) {
            paletaEl.appendChild(chip(tok, true));
        });
        canvas.innerHTML = '';
        if (!tokens.length) {
            var vazio = document.createElement('p');
            vazio.className = 'text-sm text-gray-400 px-1 py-2';
            vazio.textContent = 'Solte Média, REC e os sinais aqui.';
            canvas.appendChild(vazio);
        } else {
            tokens.forEach(function (tok, idx) {
                canvas.appendChild(chip(tok, false, idx));
            });
        }
        preview.textContent = tokens.length ? campo.value : 'Monte a fórmula acima.';
    }

    function chip(tok, daPaleta, idx) {
        var el = document.createElement(daPaleta ? 'button' : 'span');
        if (daPaleta) el.type = 'button';
        var cor = tok.type === 'var' ? 'bg-teal-50 border-teal-200 text-teal-900' : (tok.type === 'fn' ? 'bg-indigo-50 border-indigo-200 text-indigo-900' : 'bg-white border-gray-300 text-gray-800');
        el.className = 'rec-f-chip inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-sm cursor-grab ' + cor;
        el.draggable = true;
        el.setAttribute('data-type', tok.type);
        el.setAttribute('data-value', tok.value);
        if (!daPaleta) el.setAttribute('data-idx', String(idx));
        el.appendChild(document.createTextNode(rotulo(tok)));
        if (!daPaleta) {
            var fechar = document.createElement('button');
            fechar.type = 'button';
            fechar.className = 'rec-f-x text-gray-400 hover:text-red-600 leading-none';
            fechar.setAttribute('aria-label', 'Remover');
            fechar.textContent = '×';
            fechar.addEventListener('click', function (evento) {
                evento.preventDefault();
                evento.stopPropagation();
                tokens.splice(idx, 1);
                desenhar();
            });
            el.appendChild(fechar);
        }
        return el;
    }

    function tokDoEl(el) {
        if (!el) return null;
        var type = el.getAttribute('data-type');
        var value = el.getAttribute('data-value');
        if (!type || value == null || value === '') return null;
        return { type: type, value: value };
    }

    function indiceSoltar(evento) {
        var chips = canvas.querySelectorAll('.rec-f-chip');
        for (var i = 0; i < chips.length; i++) {
            var caixa = chips[i].getBoundingClientRect();
            if (evento.clientX < caixa.left + caixa.width / 2 && evento.clientY < caixa.bottom) {
                return i;
            }
        }
        return tokens.length;
    }

    paletaEl.addEventListener('click', function (evento) {
        var alvo = evento.target.closest('.rec-f-chip');
        if (!alvo || !paletaEl.contains(alvo)) return;
        if (tokens.length >= 40) return;
        tokens.push(tokDoEl(alvo));
        desenhar();
    });
    paletaEl.addEventListener('dragstart', function (evento) {
        var alvo = evento.target.closest('.rec-f-chip');
        if (!alvo) return;
        arraste = { origem: 'paleta', token: tokDoEl(alvo) };
        evento.dataTransfer.effectAllowed = 'copy';
        evento.dataTransfer.setData('text/plain', 'paleta');
    });
    canvas.addEventListener('dragstart', function (evento) {
        var alvo = evento.target.closest('.rec-f-chip');
        if (!alvo) return;
        arraste = { origem: 'canvas', idx: parseInt(alvo.getAttribute('data-idx'), 10) };
        evento.dataTransfer.effectAllowed = 'move';
        evento.dataTransfer.setData('text/plain', 'canvas');
    });
    canvas.addEventListener('dragover', function (evento) {
        evento.preventDefault();
        canvas.classList.add('border-teal-400', 'bg-teal-50');
    });
    canvas.addEventListener('dragleave', function () {
        canvas.classList.remove('border-teal-400', 'bg-teal-50');
    });
    canvas.addEventListener('drop', function (evento) {
        evento.preventDefault();
        canvas.classList.remove('border-teal-400', 'bg-teal-50');
        if (!arraste) return;
        var em = indiceSoltar(evento);
        if (arraste.origem === 'paleta' && arraste.token) {
            if (tokens.length < 40) tokens.splice(em, 0, arraste.token);
        } else if (arraste.origem === 'canvas') {
            var item = tokens.splice(arraste.idx, 1)[0];
            if (em > arraste.idx) em -= 1;
            tokens.splice(em, 0, item);
        }
        arraste = null;
        desenhar();
    });
    document.getElementById('rec-f-limpar').addEventListener('click', function () {
        tokens = [];
        desenhar();
    });
    document.getElementById('rec-f-numero-add').addEventListener('click', function () {
        var entrada = document.getElementById('rec-f-numero');
        var valor = String((entrada && entrada.value) || '').trim().replace(',', '.');
        if (!/^\d+(\.\d+)?$/.test(valor)) return;
        tokens.push({ type: 'num', value: valor, label: valor });
        if (entrada) entrada.value = '';
        desenhar();
    });

    window.recFormulaDefinir = function (texto) {
        tokens = [];
        var fonte = String(texto || '');
        var busca = /(max|min|media|rec|\d+(?:[.,]\d+)?|[+\-*/(),])/gi;
        var achado;
        while ((achado = busca.exec(fonte))) {
            var pedaco = achado[1];
            var baixo = pedaco.toLowerCase();
            if (baixo === 'max' || baixo === 'min') {
                tokens.push({ type: 'fn', value: baixo });
                var resto = fonte.slice(busca.lastIndex);
                var paren = resto.match(/^\s*\(/);
                if (paren) busca.lastIndex += paren[0].length;
                continue;
            }
            if (baixo === 'media' || baixo === 'rec') {
                tokens.push({ type: 'var', value: baixo });
                continue;
            }
            if (/^\d/.test(pedaco)) {
                tokens.push({ type: 'num', value: pedaco.replace(',', '.'), label: pedaco.replace(',', '.') });
                continue;
            }
            tokens.push({ type: 'op', value: pedaco === '−' ? '-' : pedaco });
        }
        desenhar();
    };

    desenhar();
})();
</script>
