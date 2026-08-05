/**
 * MathEditor — Integração MathLive com campos de texto do EducaTudo
 * Uso: MathEditor.init()
 */
const MathEditor = (() => {

  // ── Modal HTML ─────────────────────────────────────────────────────────
  function criarModal() {
    if (document.getElementById('math-modal')) return;

    const modal = document.createElement('div');
    modal.id = 'math-modal';
    modal.innerHTML = `
      <div id="math-modal-backdrop"></div>
      <div id="math-modal-box">
        <div id="math-modal-header">
          <span>∑ Inserir Equação</span>
          <button id="math-modal-close" type="button">✕</button>
        </div>

        <div id="math-modal-tabs">
          <button type="button" class="math-stab active" data-panel="matematica">📐 Matemática</button>
          <button type="button" class="math-stab" data-panel="quimica">🧪 Química</button>
          <button type="button" class="math-stab" data-panel="fisica">⚡ Física</button>
          <button type="button" class="math-stab" data-panel="estatistica">📊 Estatística</button>
          <button type="button" class="math-stab" data-panel="logica">∀ Lógica</button>
        </div>

        <div id="math-modal-btns">

          <div class="math-panel active" data-panel="matematica">
            <button type="button" class="math-qbtn" data-latex="\\ " title="Espaço entre caracteres">Espaço</button>
            <!-- Blocos: montar equação elemento a elemento (Tab pula entre os espaços) -->
            <button type="button" class="math-qbtn math-qbtn-block" data-latex="#@^{#?}" title="Digite a base, Tab, depois o expoente">só em cima ⁿ</button>
            <button type="button" class="math-qbtn math-qbtn-block" data-latex="#@_{#?}" title="Digite a base, Tab, depois o índice">só em baixo ₀</button>
            <button type="button" class="math-qbtn math-qbtn-block" data-latex="#@_{#?}^{#?}" title="Base, Tab=índice, Tab=expoente">em cima e em baixo ₀ⁿ</button>
            <button type="button" class="math-qbtn" data-latex="#@^{2}">x²</button>
            <button type="button" class="math-qbtn" data-latex="=">=</button>
            <button type="button" class="math-qbtn" data-latex="\\neq" title="Diferente">≠</button>
            <button type="button" class="math-qbtn" data-latex="+">+</button>
            <button type="button" class="math-qbtn" data-latex="-">−</button>
            <button type="button" class="math-qbtn" data-latex="\\cdot" title="Multiplicação">·</button>
            <button type="button" class="math-qbtn" data-latex="\\times">×</button>
            <button type="button" class="math-qbtn" data-latex="\\div">÷</button>
            <button type="button" class="math-qbtn" data-latex="\\Delta">Δ</button>
            <button type="button" class="math-qbtn" data-latex="\\frac{#@}{#?}">a/b</button>
            <button type="button" class="math-qbtn" data-latex="\\sqrt{#@}">√</button>
            <button type="button" class="math-qbtn" data-latex="\\sqrt[#?]{#@}">ⁿ√</button>
            <button type="button" class="math-qbtn" data-latex="\\sum_{#?}^{#?}">Σ</button>
            <button type="button" class="math-qbtn" data-latex="\\int_{#?}^{#?}#?\\,d#?">∫</button>
            <button type="button" class="math-qbtn" data-latex="\\lim_{#?\\to #?}">lim</button>
            <button type="button" class="math-qbtn" data-latex="\\infty">∞</button>
            <button type="button" class="math-qbtn" data-latex="\\pi">π</button>
            <button type="button" class="math-qbtn" data-latex="\\alpha">α</button>
            <button type="button" class="math-qbtn" data-latex="\\beta">β</button>
            <button type="button" class="math-qbtn" data-latex="\\theta">θ</button>
            <button type="button" class="math-qbtn" data-latex="\\vec{#@}">v⃗</button>
            <button type="button" class="math-qbtn" data-latex="\\begin{pmatrix}#?&#?\\\\#?&#?\\end{pmatrix}">⊞2×2</button>
            <button type="button" class="math-qbtn" data-latex="\\binom{#?}{#?}">C(n,k)</button>
            <button type="button" class="math-qbtn" data-latex="\\log_{#?}{#?}">log</button>
            <button type="button" class="math-qbtn" data-latex="\\ln{#?}">ln</button>
            <button type="button" class="math-qbtn" data-latex="|#@|">|x|</button>
          </div>

          <div class="math-panel" data-panel="quimica">
            <button type="button" class="math-qbtn" data-latex="\\ " title="Espaço entre caracteres">Espaço</button>
            <button type="button" class="math-qbtn" data-latex="\\ce{H2O}">H₂O</button>
            <button type="button" class="math-qbtn" data-latex="\\ce{CO2}">CO₂</button>
            <button type="button" class="math-qbtn" data-latex="\\ce{H2SO4}">H₂SO₄</button>
            <button type="button" class="math-qbtn" data-latex="\\ce{NaCl}">NaCl</button>
            <button type="button" class="math-qbtn" data-latex="\\ce{#?->#?}">A→B</button>
            <button type="button" class="math-qbtn" data-latex="\\ce{#?<=>#?}">A⇌B</button>
            <button type="button" class="math-qbtn" data-latex="\\ce{#?+#?->#?+#?}">Reação</button>
            <button type="button" class="math-qbtn" data-latex="\\ce{^{#?}_{#?}#?}">Isótopo</button>
            <button type="button" class="math-qbtn" data-latex="\\ce{#?^{#?+}}">Cátion</button>
            <button type="button" class="math-qbtn" data-latex="\\ce{#?^{#?-}}">Ânion</button>
            <button type="button" class="math-qbtn" data-latex="pH=-\\log[H^+]">pH</button>
            <button type="button" class="math-qbtn" data-latex="PV=nRT">PV=nRT</button>
            <button type="button" class="math-qbtn" data-latex="K_{eq}=\\frac{[#?]^{#?}}{[#?]^{#?}}">Keq</button>
            <button type="button" class="math-qbtn" data-latex="\\Delta H=#?\\text{ kJ/mol}">ΔH</button>
          </div>

          <div class="math-panel" data-panel="fisica">
            <button type="button" class="math-qbtn" data-latex="\\ " title="Espaço entre caracteres">Espaço</button>
            <button type="button" class="math-qbtn" data-latex="v^2=v_0^2+2a\\Delta s" title="Equação de Torricelli">v²=v₀²+2aΔs</button>
            <button type="button" class="math-qbtn" data-latex="S=S_0+v\\cdot t" title="MRU: posição">S=S₀+v·t</button>
            <button type="button" class="math-qbtn" data-latex="F=ma">F=ma</button>
            <button type="button" class="math-qbtn" data-latex="E=mc^2">E=mc²</button>
            <button type="button" class="math-qbtn" data-latex="v=\\frac{\\Delta s}{\\Delta t}">v=Δs/Δt</button>
            <button type="button" class="math-qbtn" data-latex="a=\\frac{\\Delta v}{\\Delta t}">a=Δv/Δt</button>
            <button type="button" class="math-qbtn" data-latex="\\hbar">ℏ</button>
            <button type="button" class="math-qbtn" data-latex="\\lambda=\\frac{h}{mv}">de Broglie</button>
            <button type="button" class="math-qbtn" data-latex="\\Delta x\\cdot\\Delta p\\geq\\frac{\\hbar}{2}">Heisenberg</button>
            <button type="button" class="math-qbtn" data-latex="\\frac{1}{2}mv^2">Ec</button>
            <button type="button" class="math-qbtn" data-latex="mgh">Ep</button>
            <button type="button" class="math-qbtn" data-latex="\\omega=\\frac{2\\pi}{T}">ω=2π/T</button>
            <button type="button" class="math-qbtn" data-latex="c=\\lambda f">c=λf</button>
            <button type="button" class="math-qbtn" data-latex="Q=mc\\Delta T">Q=mcΔT</button>
            <button type="button" class="math-qbtn" data-latex="\\nabla\\cdot\\vec{E}=\\frac{\\rho}{\\varepsilon_0}">∇·E</button>
          </div>

          <div class="math-panel" data-panel="estatistica">
            <button type="button" class="math-qbtn" data-latex="\\ " title="Espaço entre caracteres">Espaço</button>
            <button type="button" class="math-qbtn" data-latex="\\bar{x}=\\frac{1}{n}\\sum x_i">x̄ média</button>
            <button type="button" class="math-qbtn" data-latex="\\sigma^2=\\frac{1}{n}\\sum(x_i-\\bar{x})^2">σ² var</button>
            <button type="button" class="math-qbtn" data-latex="P(A|B)=\\frac{P(A\\cap B)}{P(B)}">P(A|B)</button>
            <button type="button" class="math-qbtn" data-latex="\\binom{n}{k}p^k(1-p)^{n-k}">Binomial</button>
            <button type="button" class="math-qbtn" data-latex="\\mathbb{E}[X]=\\sum x_i p_i">E[X]</button>
            <button type="button" class="math-qbtn" data-latex="\\mu">μ</button>
            <button type="button" class="math-qbtn" data-latex="\\sigma">σ</button>
            <button type="button" class="math-qbtn" data-latex="n!">n!</button>
            <button type="button" class="math-qbtn" data-latex="A_n^k=\\frac{n!}{(n-k)!}">Arranjo</button>
            <button type="button" class="math-qbtn" data-latex="\\chi^2">χ²</button>
          </div>

          <div class="math-panel" data-panel="logica">
            <button type="button" class="math-qbtn" data-latex="\\ " title="Espaço entre caracteres">Espaço</button>
            <button type="button" class="math-qbtn" data-latex="\\forall">∀</button>
            <button type="button" class="math-qbtn" data-latex="\\exists">∃</button>
            <button type="button" class="math-qbtn" data-latex="\\in">∈</button>
            <button type="button" class="math-qbtn" data-latex="\\notin">∉</button>
            <button type="button" class="math-qbtn" data-latex="\\neq" title="Diferente">≠</button>
            <button type="button" class="math-qbtn" data-latex="\\subset">⊂</button>
            <button type="button" class="math-qbtn" data-latex="\\not\\subset" title="Não contido">⊄</button>
            <button type="button" class="math-qbtn" data-latex="\\cup">∪</button>
            <button type="button" class="math-qbtn" data-latex="\\cap">∩</button>
            <button type="button" class="math-qbtn" data-latex="\\emptyset">∅</button>
            <button type="button" class="math-qbtn" data-latex="\\mathbb{N}">ℕ</button>
            <button type="button" class="math-qbtn" data-latex="\\mathbb{Z}" title="Inteiros">ℤ</button>
            <button type="button" class="math-qbtn" data-latex="\\mathbb{Q}" title="Racionais">ℚ</button>
            <button type="button" class="math-qbtn" data-latex="\\mathbb{R}">ℝ</button>
            <button type="button" class="math-qbtn" data-latex="\\mathbb{C}" title="Complexos">ℂ</button>
            <button type="button" class="math-qbtn" data-latex="\\neg">¬</button>
            <button type="button" class="math-qbtn" data-latex="\\land">∧</button>
            <button type="button" class="math-qbtn" data-latex="\\lor">∨</button>
            <button type="button" class="math-qbtn" data-latex="\\Rightarrow">⇒</button>
            <button type="button" class="math-qbtn" data-latex="\\Leftrightarrow">⇔</button>
          </div>

        </div>

        <div id="math-modal-field-wrap">
          <math-field id="math-mf" virtual-keyboard-mode="onfocus" placeholder="Digite ou use os botões acima..." math-mode-space="\\ "></math-field>
        </div>
        <p class="math-modal-space-hint" style="margin: 6px 0 0 0; font-size: 12px; color: #666;">A barra de espaço do teclado insere espaço entre caracteres (ex.: 7 m).</p>

        <div id="math-modal-preview-label">Pré-visualização:</div>
        <div id="math-modal-preview"></div>

        <div id="math-modal-footer">
          <button type="button" id="math-modal-cancel">Cancelar</button>
          <button type="button" id="math-modal-insert">✓ Inserir Equação</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    _bindModal();
  }

  let _campoAlvo = null;
  let _savedRange = null;
  let _editChip = null;

  function salvarCursor() {
    var sel = window.getSelection();
    if (sel && sel.rangeCount > 0 && _campoAlvo && _campoAlvo.isContentEditable) {
      var range = sel.getRangeAt(0);
      if (_campoAlvo.contains(range.commonAncestorContainer)) {
        _savedRange = range.cloneRange();
        return;
      }
    }
    _savedRange = null;
  }

  function restaurarCursor() {
    if (_savedRange && _campoAlvo) {
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(_savedRange);
    }
  }

  function abrir(campo) {
    _campoAlvo = campo;
    _editChip = null;
    if (campo && campo.isContentEditable) salvarCursor();
    var modal = document.getElementById('math-modal');
    var mf = document.getElementById('math-mf');
    if (!modal || !mf) return;
    if (typeof mf.mathModeSpace !== 'undefined') mf.mathModeSpace = '\\ ';
    modal.classList.add('open');
    mf.setValue('');
    setTimeout(function() { mf.focus(); }, 80);
  }

  /** Abre o modal para inserir equação no editor Quill (usa o modal antigo com MathLive). */
  function abrirParaQuill(quill) {
    var range = quill.getSelection(true);
    _campoAlvo = { quill: quill, isQuill: true, range: range };
    _editChip = null;
    var modal = document.getElementById('math-modal');
    var mf = document.getElementById('math-mf');
    if (!modal || !mf) return;
    if (typeof mf.mathModeSpace !== 'undefined') mf.mathModeSpace = '\\ ';
    modal.classList.add('open');
    mf.setValue('');
    setTimeout(function() { mf.focus(); }, 80);
  }

  function abrirEditarChip(chip, campo) {
    _campoAlvo = campo;
    _editChip = chip;
    var latex = chip.getAttribute('data-latex') || '';
    var modal = document.getElementById('math-modal');
    var mf = document.getElementById('math-mf');
    if (!modal || !mf) return;
    if (typeof mf.mathModeSpace !== 'undefined') mf.mathModeSpace = '\\ ';
    modal.classList.add('open');
    mf.setValue(latex);
    setTimeout(function() { mf.focus(); }, 80);
  }

  function fechar() {
    var modal = document.getElementById('math-modal');
    if (modal) modal.classList.remove('open');
    _campoAlvo = null;
    _editChip = null;
  }

  function criarChip(latex) {
    var chip = document.createElement('span');
    chip.className = 'eq-chip';
    chip.setAttribute('contenteditable', 'false');
    chip.setAttribute('data-latex', latex);
    var inner = document.createElement('span');
    inner.className = 'eq-chip-math';
    inner.textContent = '\\( ' + latex + ' \\)';
    chip.appendChild(inner);
    if (window.MathLive && typeof MathLive.renderMathInElement === 'function') {
      MathLive.renderMathInElement(inner);
    }
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'eq-chip-del';
    btn.title = 'Remover equação';
    btn.textContent = '\u2715';
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      if (chip.parentNode) chip.parentNode.removeChild(chip);
      var par = chip.parentNode;
      if (par) par.dispatchEvent(new Event('input', { bubbles: true }));
    });
    chip.appendChild(btn);
    chip.addEventListener('dblclick', function() {
      var editor = chip.closest('[contenteditable="true"]');
      if (editor && typeof MathEditor !== 'undefined' && MathEditor.abrirEditarChip) {
        MathEditor.abrirEditarChip(chip, editor);
      }
    });
    return chip;
  }

  function getLatexFromMathField(mf) {
    if (!mf) return '';
    var s = '';
    if (typeof mf.value !== 'undefined' && mf.value != null) {
      s = String(mf.value).trim();
    }
    if (!s && mf.getValue) {
      try {
        s = (mf.getValue('latex') || mf.getValue() || '').trim();
      } catch (e) {
        try { s = (mf.getValue() || '').trim(); } catch (e2) {}
      }
    }
    return s;
  }

  function inserir() {
    if (!_campoAlvo) return;
    var mf = document.getElementById('math-mf');
    if (!mf) return;
    var latex = getLatexFromMathField(mf);
    if (!latex) {
      alert('Digite uma equação no campo antes de inserir.');
      return;
    }

    if (_campoAlvo.isQuill && _campoAlvo.quill) {
      var quill = _campoAlvo.quill;
      var index = (_campoAlvo.range && _campoAlvo.range.index != null) ? _campoAlvo.range.index : quill.getLength();
      var latexStr = typeof latex === 'string' ? latex : (latex ? String(latex) : '');
      try {
        quill.insertEmbed(index, 'mathEmbed', latexStr);
        quill.setSelection(index + 1);
      } catch (err) {
        console.warn('Inserir equação (mathEmbed):', err);
        try {
          var span = document.createElement('span');
          span.className = 'math-embed';
          span.setAttribute('contenteditable', 'false');
          span.setAttribute('data-latex', latexStr);
          var inner = document.createElement('span');
          inner.className = 'math-embed-inner';
          inner.textContent = '\\( ' + latexStr + ' \\)';
          span.appendChild(inner);
          var html = span.outerHTML;
          quill.clipboard.dangerouslyPasteHTML(index, html);
          quill.setSelection(index + 1);
          setTimeout(function() {
            var roots = quill.root.querySelectorAll ? quill.root.querySelectorAll('.math-embed-inner') : [];
            roots.forEach(function(el) {
              if (el.textContent && window.MathLive && typeof MathLive.renderMathInElement === 'function') {
                try { MathLive.renderMathInElement(el); } catch (e) {}
              }
            });
          }, 50);
        } catch (err2) {
          console.error('Inserir equação no Quill:', err2);
          alert('Não foi possível inserir a equação. Tente novamente.');
        }
      }
      fechar();
      return;
    }

    if (_campoAlvo.isContentEditable) {
      var chip = criarChip(latex);
      if (_editChip && _editChip.parentNode) {
        _editChip.parentNode.replaceChild(chip, _editChip);
      } else {
        restaurarCursor();
        _campoAlvo.focus();
        var sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
          var range = sel.getRangeAt(0);
          range.deleteContents();
          range.insertNode(chip);
          range.setStartAfter(chip);
          range.collapse(true);
          sel.removeAllRanges();
          sel.addRange(range);
        } else {
          _campoAlvo.appendChild(chip);
        }
      }
      _campoAlvo.dispatchEvent(new Event('input', { bubbles: true }));
    } else if (_campoAlvo.tagName === 'TEXTAREA' || _campoAlvo.tagName === 'INPUT') {
      var delimitado = '\\( ' + latex + ' \\)';
      var start = _campoAlvo.selectionStart;
      var end   = _campoAlvo.selectionEnd;
      var val   = _campoAlvo.value;
      _campoAlvo.value = val.slice(0, start) + ' ' + delimitado + ' ' + val.slice(end);
      _campoAlvo.dispatchEvent(new Event('input', { bubbles: true }));
    }
    fechar();
  }

  function serializarParaLaTeX(el) {
    if (!el) return '';
    var out = [];
    function walk(node) {
      if (node.nodeType === 3) {
        out.push(node.textContent);
        return;
      }
      if (node.nodeType === 1) {
        if (node.classList && node.classList.contains('eq-chip')) {
          var latex = node.getAttribute('data-latex');
          if (latex != null) out.push(' \\( ' + latex + ' \\) ');
          return;
        }
        for (var i = 0; i < node.childNodes.length; i++) walk(node.childNodes[i]);
      }
    }
    for (var i = 0; i < el.childNodes.length; i++) walk(el.childNodes[i]);
    return out.join('').replace(/\s+/g, ' ').trim();
  }

  function preencherDeLaTeX(el, str) {
    if (!el) return;
    el.innerHTML = '';
    if (!str || !str.trim()) return;
    var s = str.trim();
    var re = /\\\((.*?)\\\)/g;
    var lastIndex = 0;
    var m;
    while ((m = re.exec(s)) !== null) {
      var antes = s.slice(lastIndex, m.index);
      if (antes.length) el.appendChild(document.createTextNode(antes));
      var latex = (m[1] || '').trim();
      if (latex) {
        var chip = criarChip(latex);
        el.appendChild(chip);
      }
      lastIndex = re.lastIndex;
    }
    if (lastIndex < s.length) el.appendChild(document.createTextNode(s.slice(lastIndex)));
  }

  function _bindModal() {
    const mf = document.getElementById('math-mf');
    const preview = document.getElementById('math-modal-preview');
    if (!mf) return;

    // Permitir espaço entre caracteres: barra de espaço insere \\  (LaTeX space) no modo matemática
    if (typeof mf.mathModeSpace !== 'undefined') {
      mf.mathModeSpace = '\\ ';
    }

    document.getElementById('math-modal-backdrop').addEventListener('click', fechar);
    document.getElementById('math-modal-close').addEventListener('click', fechar);
    document.getElementById('math-modal-cancel').addEventListener('click', fechar);
    document.getElementById('math-modal-insert').addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      inserir();
    });

    mf.addEventListener('input', function() {
      var latex = getLatexFromMathField(mf);
      if (preview) {
        preview.innerHTML = latex ? ('\\( ' + latex + ' \\)') : '';
        if (window.MathLive && typeof MathLive.renderMathInElement === 'function') {
          MathLive.renderMathInElement(preview);
        }
      }
    });

    document.querySelectorAll('.math-stab').forEach(function(tab) {
      tab.addEventListener('click', function() {
        document.querySelectorAll('.math-stab').forEach(function(t) { t.classList.remove('active'); });
        document.querySelectorAll('.math-panel').forEach(function(p) { p.classList.remove('active'); });
        tab.classList.add('active');
        var panel = document.querySelector('.math-panel[data-panel="' + tab.getAttribute('data-panel') + '"]');
        if (panel) panel.classList.add('active');
      });
    });

    document.querySelectorAll('.math-qbtn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var latex = btn.getAttribute('data-latex');
        if (!latex) return;
        mf.focus();
        if (mf.executeCommand) {
          mf.executeCommand(['insert', latex, { insertionMode: 'replaceSelection', selectionMode: 'placeholder' }]);
          mf.executeCommand('moveToNextPlaceholder');
        }
      });
    });
  }

  function attach(campo, label) {
    if (!campo) return;
    if (campo.nextElementSibling && campo.nextElementSibling.classList.contains('math-eq-btn')) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'math-eq-btn';
    btn.innerHTML = '∑ Equação';
    btn.title = 'Inserir equação em: ' + (label || 'campo');
    btn.addEventListener('click', function() { abrir(campo); });
    campo.insertAdjacentElement('afterend', btn);
  }

  /** Renderiza fórmulas em elementos .eq-chip-math dentro do container (ex.: após carregar HTML com chips). */
  function renderMathInEditor(container) {
    if (!container || !window.MathLive || typeof MathLive.renderMathInElement !== 'function') return;
    var nodes = container.querySelectorAll ? container.querySelectorAll('.eq-chip-math') : [];
    for (var i = 0; i < nodes.length; i++) {
      try {
        MathLive.renderMathInElement(nodes[i]);
      } catch (e) {}
    }
  }

  function init() {
    criarModal();
    document.querySelectorAll('[data-math="true"]').forEach(function(campo) {
      if (campo.isContentEditable) return;
      attach(campo, campo.getAttribute('placeholder') || campo.getAttribute('name') || '');
    });
    document.querySelectorAll('[data-math-open]').forEach(function(btn) {
      var id = btn.getAttribute('data-math-open');
      if (!id) return;
      var editor = document.getElementById(id);
      if (editor && editor.isContentEditable) {
        btn.addEventListener('click', function() { abrir(editor); });
      }
    });
  }

  return {
    init: init,
    abrir: abrir,
    abrirParaQuill: abrirParaQuill,
    fechar: fechar,
    attach: attach,
    criarChip: criarChip,
    serializarParaLaTeX: serializarParaLaTeX,
    preencherDeLaTeX: preencherDeLaTeX,
    abrirEditarChip: abrirEditarChip,
    salvarCursor: salvarCursor,
    renderMathInEditor: renderMathInEditor
  };
})();

document.addEventListener('DOMContentLoaded', function() {
  if (typeof MathEditor !== 'undefined') MathEditor.init();
});
