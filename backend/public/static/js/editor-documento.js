(function () {
  'use strict';

  var C = window.EDOC || {};

  function secoesDe(area) {
    if (!area) return [];
    if (Array.isArray(area)) return area;
    return Array.isArray(area.sections) ? area.sections : [];
  }

  function normalizarEstrutura(e) {
    e = (e && typeof e === 'object' && !Array.isArray(e)) ? e : {};
    var page = e.page && typeof e.page === 'object' ? e.page : {};
    var margin = page.margin && typeof page.margin === 'object' ? page.margin : {};
    var header = e.header && typeof e.header === 'object' ? e.header : {};
    var body = e.body && typeof e.body === 'object' ? e.body : {};
    var footer = e.footer && typeof e.footer === 'object' ? e.footer : {};
    var out = {
      version: e.version || 1,
      page: {
        size: page.size || 'A4',
        orientation: page.orientation || 'portrait',
        margin: {
          top: margin.top != null ? margin.top : 15,
          right: margin.right != null ? margin.right : 15,
          bottom: margin.bottom != null ? margin.bottom : 15,
          left: margin.left != null ? margin.left : 15
        }
      },
      header: { repeat: header.repeat !== false, sections: secoesDe(header) },
      body: { sections: secoesDe(body) },
      footer: { repeat: footer.repeat !== false, sections: secoesDe(footer) }
    };
    if (typeof page.fundo === 'string' && /^data:image\/(png|jpeg|jpg|gif|webp);base64,/i.test(page.fundo) && page.fundo.length < 1500000) {
      out.page.fundo = page.fundo;
      out.page.imprimirFundo = page.imprimirFundo === true;
    }
    if (e.grade && typeof e.grade === 'object') out.grade = e.grade;
    if (e.emissao && typeof e.emissao === 'object') out.emissao = e.emissao;
    return stripLogoDuplicado(out);
  }

  function stripLogoDuplicado(est) {
    ['header', 'body', 'footer'].forEach(function (role) {
      var secs = (est[role] && est[role].sections) || [];
      var temLogo = secs.some(function (s) {
        return (s.columns || []).some(function (c) {
          return (c.elements || []).some(function (el) { return el.type === 'logo'; });
        });
      });
      if (!temLogo) return;
      secs.forEach(function (s) {
        (s.columns || []).forEach(function (c) {
          (c.elements || []).forEach(function (el) {
            if (el.type !== 'html' && el.type !== 'texto' && el.type !== 'texto_rico') return;
            el.props = el.props || {};
            ['html', 'text'].forEach(function (campo) {
              if (typeof el.props[campo] !== 'string') return;
              el.props[campo] = el.props[campo]
                .replace(/\{\{\s*logo_html\s*\}\}/gi, '')
                .replace(/<p[^>]*>\s*(?:&nbsp;|\u00a0|\s)*<\/p>/gi, '');
            });
          });
        });
      });
    });
    return est;
  }

  var state = {
    estrutura: normalizarEstrutura(C.estrutura),
    selected: null,
    zoom: 90,
    preview: false,
    demo: false,
    history: [],
    histI: -1,
    dirty: false,
    saving: false
  };

  var paper = null;
  var editando = null;
  var saveTimer = null;
  var saveP = null;
  var saveAgain = false;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function sanitizeHtml(html) {
    var d = document.createElement('div');
    d.innerHTML = String(html || '');
    d.querySelectorAll('script,iframe,object,embed,form,svg,link,meta').forEach(function (n) { n.remove(); });
    Array.prototype.slice.call(d.querySelectorAll('*')).forEach(function (n) {
      Array.prototype.slice.call(n.attributes).forEach(function (a) {
        if (/^on/i.test(a.name) || /javascript:/i.test(a.value)) n.removeAttribute(a.name);
      });
      var st = n.getAttribute('style');
      if (st && corInvisivelNoPapel(st)) {
        n.setAttribute('style', st.replace(/color\s*:[^;]+;?/gi, '').replace(/-webkit-text-fill-color\s*:[^;]+;?/gi, ''));
      }
    });
    Array.prototype.slice.call(d.querySelectorAll('font')).forEach(function (f) {
      var span = document.createElement('span');
      if (f.style && f.style.fontSize) span.style.fontSize = f.style.fontSize;
      while (f.firstChild) span.appendChild(f.firstChild);
      f.parentNode.replaceChild(span, f);
    });
    Array.prototype.slice.call(d.querySelectorAll('img')).forEach(function (img) {
      var src = img.getAttribute('src') || '';
      if (!/^data:image\/(png|jpeg|jpg|gif|webp);base64,/i.test(src) || src.length > 400000) {
        img.remove();
      } else {
        img.setAttribute('alt', img.getAttribute('alt') || '');
        img.setAttribute('style', 'max-width:100%;height:auto;');
      }
    });
    return d.innerHTML;
  }

  function corInvisivelNoPapel(st) {
    return /(?:^|;)\s*(?:color|-webkit-text-fill-color)\s*:\s*(#fff(?:fff)?|white|rgb\(\s*255\s*,\s*255\s*,\s*255\s*\)|rgba\(\s*255\s*,\s*255\s*,\s*255\s*,\s*1(?:\.0+)?\s*\))/i.test(st);
  }

  var IMG_DATA_MAX = 350000;

  function arquivoDoClipboard(dt) {
    if (!dt) return null;
    var i;
    if (dt.files && dt.files.length) {
      for (i = 0; i < dt.files.length; i++) {
        if (/^image\//i.test(dt.files[i].type)) return dt.files[i];
      }
    }
    if (dt.items) {
      for (i = 0; i < dt.items.length; i++) {
        if (dt.items[i].kind === 'file' && /^image\//i.test(dt.items[i].type)) {
          return dt.items[i].getAsFile();
        }
      }
    }
    return null;
  }

  function arquivoParaDataUri(file, cb) {
    if (!file || !/^image\/(png|jpeg|jpg|gif|webp)$/i.test(file.type || '')) {
      cb(null, 'Use PNG, JPG, GIF ou WebP.');
      return;
    }
    var reader = new FileReader();
    reader.onerror = function () { cb(null, 'Não foi possível ler a imagem.'); };
    reader.onload = function () {
      var img = new Image();
      img.onload = function () {
        var maxW = 900;
        var w = img.naturalWidth || img.width || 1;
        var h = img.naturalHeight || img.height || 1;
        var scale = w > maxW ? maxW / w : 1;
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(w * scale));
        canvas.height = Math.max(1, Math.round(h * scale));
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        var q = 0.82;
        var out = canvas.toDataURL('image/jpeg', q);
        while (out.length > IMG_DATA_MAX && q > 0.4) {
          q -= 0.12;
          out = canvas.toDataURL('image/jpeg', q);
        }
        if (out.length > IMG_DATA_MAX) {
          canvas.width = Math.max(1, Math.round(canvas.width * 0.55));
          canvas.height = Math.max(1, Math.round(canvas.height * 0.55));
          ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          out = canvas.toDataURL('image/jpeg', 0.68);
        }
        if (out.length > IMG_DATA_MAX) {
          cb(null, 'Imagem grande demais. Use um arquivo menor.');
          return;
        }
        cb(out);
      };
      img.onerror = function () { cb(null, 'Imagem inválida.'); };
      img.src = String(reader.result || '');
    };
    reader.readAsDataURL(file);
  }

  function htmlImgData(uri) {
    return '<img src="' + String(uri).replace(/"/g, '') + '" alt="" style="max-width:100%;height:auto;">';
  }

  function selecaoCobreTudo(rte) {
    try {
      var sel = window.getSelection();
      if (!sel || !sel.rangeCount || sel.isCollapsed) return false;
      var r = sel.getRangeAt(0);
      var all = document.createRange();
      all.selectNodeContents(rte);
      return r.toString().replace(/\s+/g, '') === (rte.innerText || '').replace(/\s+/g, '')
        || (r.compareBoundaryPoints(Range.START_TO_START, all) <= 0
          && r.compareBoundaryPoints(Range.END_TO_END, all) >= 0);
    } catch (err) {
      return false;
    }
  }
  function uid(p) {
    return (p || 'n') + '_' + Math.random().toString(36).slice(2, 8) + Date.now().toString(36).slice(-4);
  }
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }

  function pushHist() {
    state.history = state.history.slice(0, state.histI + 1);
    state.history.push(clone(state.estrutura));
    if (state.history.length > 80) state.history.shift();
    state.histI = state.history.length - 1;
    state.dirty = true;
    setStatus('Alterações não salvas');
    scheduleSave();
  }
  function undo() {
    if (state.histI <= 0) return;
    state.histI--;
    state.estrutura = clone(state.history[state.histI]);
    render();
    scheduleSave();
  }
  function redo() {
    if (state.histI >= state.history.length - 1) return;
    state.histI++;
    state.estrutura = clone(state.history[state.histI]);
    render();
    scheduleSave();
  }

  function setStatus(t) {
    var el = $('#edoc-status');
    if (el) el.textContent = t;
  }

  function areaOf(role) {
    return state.estrutura[role] || { sections: [] };
  }

  function findPath(id) {
    var roles = ['header', 'body', 'footer'];
    for (var r = 0; r < roles.length; r++) {
      var secs = areaOf(roles[r]).sections || [];
      for (var s = 0; s < secs.length; s++) {
        if (secs[s].id === id) return { role: roles[r], section: secs[s], si: s };
        var cols = secs[s].columns || [];
        for (var c = 0; c < cols.length; c++) {
          if (cols[c].id === id) return { role: roles[r], section: secs[s], si: s, column: cols[c], ci: c };
          var els = cols[c].elements || [];
          for (var e = 0; e < els.length; e++) {
            if (els[e].id === id) return { role: roles[r], section: secs[s], si: s, column: cols[c], ci: c, element: els[e], ei: e };
          }
        }
      }
    }
    return null;
  }

  function mmPage() {
    var p = state.estrutura.page || {};
    var a5 = String(p.size || 'A4').toUpperCase() === 'A5';
    var land = (p.orientation || 'portrait') === 'landscape';
    var w = a5 ? (land ? 210 : 148) : (land ? 297 : 210);
    var h = a5 ? (land ? 148 : 210) : (land ? 210 : 297);
    return { w: w, h: h, margin: p.margin || { top: 15, right: 15, bottom: 15, left: 15 } };
  }

  function labelTipo(t) {
    var map = {
      titulo: 'Título', texto: 'Texto', texto_rico: 'Texto rico', logo: 'Logo', imagem: 'Imagem',
      tabela: 'Tabela', html: 'HTML', linha: 'Linha', espacador: 'Espaçador', pagina: 'Página', quebra_pagina: 'Quebra',
      dados_escola: 'Escola', dados_aluno: 'Aluno', dados_responsavel: 'Responsável', dados_turma: 'Turma',
      frequencia: 'Frequência', observacoes: 'Observações', assinaturas: 'Assinaturas',
      tabela_aluno: 'Tabela aluno', tabela_notas: 'Notas', tabela_frequencia: 'Freq. tabela',
      historico: 'Histórico', resultado_final: 'Resultado', qrcode: 'QR Code'
    };
    return map[t] || t;
  }

  function ph(html) {
    if (!html) return '';
    var usarDados = state.preview || state.demo;
    var out = String(html).replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, function (_, k) {
      if (usarDados && C.varsPreview && Object.prototype.hasOwnProperty.call(C.varsPreview, k)) {
        return String(C.varsPreview[k]);
      }
      return '<span class="edoc-ph">{{' + k + '}}</span>';
    });
    return out;
  }

  function cssBox(st) {
    st = st || {};
    var s = '';
    ['margin', 'padding'].forEach(function (k) {
      var v = st[k];
      if (!v) return;
      s += k + ':' + (v.top || 0) + 'px ' + (v.right || 0) + 'px ' + (v.bottom || 0) + 'px ' + (v.left || 0) + 'px;';
    });
    if (st.background) s += 'background:' + st.background + ';';
    if (st.textAlign) s += 'text-align:' + st.textAlign + ';';
    if (st.fontSize) s += 'font-size:' + st.fontSize + 'pt;';
    if (st.fontWeight) s += 'font-weight:' + st.fontWeight + ';';
    if (st.color) s += 'color:' + st.color + ';';
    if (st.lineHeight) s += 'line-height:' + st.lineHeight + ';';
    if (st.italic) s += 'font-style:italic;';
    if (st.underline) s += 'text-decoration:underline;';
    if (st.borderStyle && st.borderStyle !== 'none') {
      s += 'border:' + (st.borderWidth || 1) + 'px ' + st.borderStyle + ' ' + (st.borderColor || '#e5e7eb') + ';';
    }
    if (st.borderRadius) s += 'border-radius:' + st.borderRadius + 'px;';
    return s;
  }

  function posicaoCss(el) {
    var p = el.props || {};
    var h = p.align || (el.style && el.style.textAlign) || 'center';
    var v = p.vAlign || 'middle';
    if (h !== 'left' && h !== 'right' && h !== 'center') h = 'center';
    if (v !== 'top' && v !== 'bottom' && v !== 'middle') v = 'middle';
    var j = { left: 'flex-start', center: 'center', right: 'flex-end' }[h];
    var a = { top: 'flex-start', middle: 'center', bottom: 'flex-end' }[v];
    return 'display:flex;justify-content:' + j + ';align-items:' + a + ';width:100%;';
  }

  function htmlTextoInterno(el, fallback) {
    var p = el.props || {};
    var tx = p.html || p.text || fallback || '';
    if (String(tx).indexOf('<') >= 0) {
      return ph(sanitizeHtml(tx));
    }
    return ph(esc(tx).replace(/\n/g, '<br>'));
  }

  function htmlParaEditor(el) {
    var p = el.props || {};
    var tx = p.html || p.text || '';
    if (!tx) return '';
    if (String(tx).indexOf('<') >= 0) return sanitizeHtml(tx);
    return esc(tx).replace(/\n/g, '<br>');
  }

  function htmlDoEditor(node) {
    if (!node) return '';
    var clone = node.cloneNode(true);
    $all('.edoc-ph', clone).forEach(function (s) {
      s.replaceWith(document.createTextNode(s.textContent || ''));
    });
    $all('.edoc-col-marcada, .edoc-celula-sel, .edoc-celula-drop', clone).forEach(function (n) {
      n.classList.remove('edoc-col-marcada', 'edoc-celula-sel', 'edoc-celula-drop');
    });
    $all('[contenteditable]', clone).forEach(function (n) {
      n.removeAttribute('contenteditable');
      n.removeAttribute('spellcheck');
    });
    return sanitizeHtml(clone.innerHTML);
  }

  function inserirQuebraLinha() {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) {
      document.execCommand('insertLineBreak');
      return;
    }
    var range = sel.getRangeAt(0);
    range.deleteContents();
    var br = document.createElement('br');
    range.insertNode(br);
    if (br.parentNode && br === br.parentNode.lastChild) {
      br.parentNode.appendChild(document.createElement('br'));
    }
    range.setStartAfter(br);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
  }

  function estaDigitando(el) {
    if (!el) return false;
    var tag = (el.tagName || '').toUpperCase();
    if (tag === 'TEXTAREA' || tag === 'INPUT' || tag === 'SELECT') return true;
    return !!(el.closest && el.closest('[contenteditable="true"]'));
  }

  function corpoDoElemento(node) {
    if (!node) return null;
    var body = node.querySelector('.edoc-el-body');
    if (body) return body;
    var kids = node.children;
    for (var i = 0; i < kids.length; i++) {
      if (kids[i].classList.contains('edoc-el-toolbar')) continue;
      if (kids[i].classList.contains('edoc-el-handles')) continue;
      return kids[i];
    }
    return null;
  }

  function ehTextoEditavel(tipo) {
    return ['titulo', 'texto', 'texto_rico', 'html'].indexOf(tipo) >= 0;
  }

  function htmlBarraFmt(comImagem) {
    return '<button type="button" data-fmt="bold" title="Negrito (Ctrl+B)"><i class="fa-solid fa-bold"></i></button>'
      + '<button type="button" data-fmt="italic" title="Itálico (Ctrl+I)"><i class="fa-solid fa-italic"></i></button>'
      + '<button type="button" data-fmt="underline" title="Sublinhado (Ctrl+U)"><i class="fa-solid fa-underline"></i></button>'
      + '<span class="edoc-fmt-sep"></span>'
      + '<button type="button" data-fmt="justifyLeft" title="Alinhar à esquerda"><i class="fa-solid fa-align-left"></i></button>'
      + '<button type="button" data-fmt="justifyCenter" title="Centralizar"><i class="fa-solid fa-align-center"></i></button>'
      + '<button type="button" data-fmt="justifyRight" title="Alinhar à direita"><i class="fa-solid fa-align-right"></i></button>'
      + '<button type="button" data-fmt="justifyFull" title="Justificar"><i class="fa-solid fa-align-justify"></i></button>'
      + '<span class="edoc-fmt-sep"></span>'
      + '<button type="button" data-fmt="fontDec" title="Diminuir fonte">A−</button>'
      + '<span class="edoc-fmt-size" data-fmt-size>12</span>'
      + '<button type="button" data-fmt="fontInc" title="Aumentar fonte">A+</button>'
      + '<span class="edoc-fmt-sep"></span>'
      + '<button type="button" data-fmt="tableInsert" title="Inserir tabela"><i class="fa-solid fa-table"></i></button>'
      + '<button type="button" data-fmt="tableInsRow" title="Inserir linha"><i class="fa-solid fa-plus"></i></button>'
      + '<button type="button" data-fmt="tableInsCol" title="Inserir coluna"><i class="fa-solid fa-grip-lines-vertical"></i></button>'
      + '<button type="button" data-fmt="tableMerge" title="Mesclar células selecionadas"><i class="fa-solid fa-object-group"></i></button>'
      + '<button type="button" data-fmt="tableDelCol" title="Excluir coluna"><i class="fa-solid fa-table-columns"></i></button>'
      + '<button type="button" data-fmt="tableDelRow" title="Excluir linha"><i class="fa-solid fa-grip-lines"></i></button>'
      + '<button type="button" data-fmt="tableSelCol" title="Selecionar só esta coluna">Coluna</button>'
      + '<span class="edoc-fmt-sep"></span>'
      + '<button type="button" data-fmt="insertImg" title="Inserir imagem"><i class="fa-solid fa-image"></i></button>';
  }

  function cssSizeParaPt(valor) {
    if (valor == null || valor === '') return 0;
    var s = String(valor).trim().toLowerCase();
    var nome = {
      'xx-small': 8, 'x-small': 9, small: 10, medium: 12, large: 14,
      'x-large': 18, 'xx-large': 24, 'xxx-large': 32, '-webkit-xxx-large': 32
    };
    if (nome[s]) return nome[s];
    var n = parseFloat(s);
    if (!n || n <= 0) return 0;
    if (s.indexOf('pt') >= 0) return Math.round(n);
    if (s.indexOf('em') >= 0 || s.indexOf('rem') >= 0) return Math.round(n * 12);
    if (s.indexOf('%') >= 0) return Math.round(12 * n / 100);
    return Math.max(1, Math.round(n * 72 / 96));
  }

  function tamanhoFontePt(el, body) {
    var sel = window.getSelection();
    var node = sel && sel.anchorNode;
    var alvo = node ? (node.nodeType === 1 ? node : node.parentElement) : null;
    if (alvo && body && body.contains(alvo) && alvo.isConnected) {
      var ptSel = cssSizeParaPt(window.getComputedStyle(alvo).fontSize);
      if (ptSel >= 6) return Math.max(8, Math.min(72, ptSel));
    }
    if (body && body.isConnected) {
      var bruto = (body.style && body.style.fontSize) || window.getComputedStyle(body).fontSize;
      var ptBody = cssSizeParaPt(bruto);
      if (ptBody >= 6) return Math.max(8, Math.min(72, ptBody));
    }
    if (el && el.style && el.style.fontSize != null && el.style.fontSize !== '') {
      var raw = String(el.style.fontSize);
      var ptEl = cssSizeParaPt(/[a-z%]/i.test(raw) ? raw : raw + 'pt');
      if (ptEl >= 6) return Math.max(8, Math.min(72, ptEl));
    }
    return 12;
  }

  function atualizarLabelFonte(pt) {
    $all('[data-fmt-size]').forEach(function (n) { n.textContent = String(pt); });
  }

  function limparFonteInterna(root) {
    if (!root) return;
    $all('font', root).forEach(function (f) {
      var span = document.createElement('span');
      while (f.firstChild) span.appendChild(f.firstChild);
      if (f.parentNode) f.parentNode.replaceChild(span, f);
    });
    $all('*', root).forEach(function (n) {
      if (n === root || !n.style) return;
      if (n.style.fontSize) n.style.fontSize = '';
    });
  }

  function snapshotSelecao(root) {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount || !root || !root.contains(sel.anchorNode)) return null;
    return sel.getRangeAt(0).cloneRange();
  }

  function restoreSelecao(range) {
    if (!range) return;
    var sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  }

  function aplicarFonteNaSelecao(pt, body) {
    var sel = window.getSelection();
    if (!sel || sel.isCollapsed || !sel.rangeCount) return false;
    var range = sel.getRangeAt(0);
    if (!body.contains(range.commonAncestorContainer)) return false;
    if (selecaoCobreTudo(body)) {
      limparFonteInterna(body);
      body.style.fontSize = pt + 'pt';
      return true;
    }
    var span = document.createElement('span');
    span.style.fontSize = pt + 'pt';
    try {
      range.surroundContents(span);
    } catch (err) {
      span.appendChild(range.extractContents());
      range.insertNode(span);
    }
    $all('[style]', span).forEach(function (inner) {
      if (inner === span || !inner.style) return;
      if (inner.style.fontSize) inner.style.fontSize = '';
    });
    sel.removeAllRanges();
    var r2 = document.createRange();
    r2.selectNodeContents(span);
    sel.addRange(r2);
    return true;
  }

  function aplicarTamanhoFonte(pt, el, body) {
    pt = Math.max(8, Math.min(72, parseInt(pt, 10) || 12));
    el.style = el.style || {};
    if (!body) {
      el.style.fontSize = pt;
      atualizarLabelFonte(pt);
      return;
    }
    var cobriuTudo = selecaoCobreTudo(body);
    var aplicouSel = aplicarFonteNaSelecao(pt, body);
    if (!aplicouSel || cobriuTudo) {
      limparFonteInterna(body);
      body.style.fontSize = pt + 'pt';
      el.style.fontSize = pt;
      var folha = paper && el.id ? corpoDoElemento(paper.querySelector('.edoc-el[data-id="' + el.id + '"]')) : null;
      if (folha && folha !== body) folha.style.fontSize = pt + 'pt';
    }
    atualizarLabelFonte(pt);
  }

  function executarFmt(cmd, el, body) {
    if (!el || !body) return;
    var snap = snapshotSelecao(body);
    var host = body.querySelector('[contenteditable="true"]') || body;
    host.focus();
    restoreSelecao(snap);
    if (cmd === 'bold' || cmd === 'italic' || cmd === 'underline') {
      document.execCommand(cmd, false, null);
      return;
    }
    if (cmd === 'justifyLeft' || cmd === 'justifyCenter' || cmd === 'justifyRight' || cmd === 'justifyFull') {
      document.execCommand(cmd, false, null);
      var map = { justifyLeft: 'left', justifyCenter: 'center', justifyRight: 'right', justifyFull: 'justify' };
      var align = map[cmd];
      el.style = el.style || {};
      el.props = el.props || {};
      el.style.textAlign = align;
      el.props.align = align;
      body.style.textAlign = align;
      var folha = paper && el.id ? corpoDoElemento(paper.querySelector('.edoc-el[data-id="' + el.id + '"]')) : null;
      if (folha && folha !== body) folha.style.textAlign = align;
      return;
    }
    if (cmd === 'fontInc' || cmd === 'fontDec') {
      if (aplicarFonteNaColunaMarcada(cmd === 'fontInc' ? 2 : -2, el, body)) return;
      var atual = tamanhoFontePt(el, body);
      aplicarTamanhoFonte(cmd === 'fontInc' ? atual + 2 : atual - 2, el, body);
      return;
    }
    if (cmd === 'tableSelCol') {
      var tdSel = celulaDaSelecao(body);
      var tabelaSel = tdSel && tdSel.closest('table');
      if (!tabelaSel) {
        setStatus('Clique numa célula da coluna.');
        return;
      }
      var gradeSel = matrizTabela(tabelaSel);
      var posSel = indiceVisualCelula(gradeSel, tdSel);
      if (!posSel) return;
      marcarColuna(tabelaSel, posSel.col);
      setStatus('Coluna selecionada. Use A+ ou A− para o texto desta coluna.');
      return;
    }
    if (cmd === 'tableDelCol' || cmd === 'tableDelRow' || cmd === 'tableInsRow'
      || cmd === 'tableInsCol' || cmd === 'tableMerge' || cmd === 'tableInsert') {
      alterarTabela(body, cmd);
      return;
    }
    if (cmd === 'insertImg') {
      escolherArquivoImagem(function (file) {
        inserirImagemNoCorpo(el, body, file);
      });
    }
  }

  function htmlTabelaVazia(linhas, cols) {
    linhas = Math.max(1, Math.min(40, parseInt(linhas, 10) || 5));
    cols = Math.max(1, Math.min(20, parseInt(cols, 10) || 6));
    var pg = mmPage();
    var util = Math.max(20, pg.w - (pg.margin.left || 0) - (pg.margin.right || 0));
    var colMm = Math.round((util / cols) * 10) / 10;
    var soma = Math.round(colMm * cols * 10) / 10;
    var html = '<table class="dados seed-folha" style="width:' + soma.toFixed(1) + 'mm;border-collapse:collapse;table-layout:fixed;"><colgroup>';
    var r;
    var c;
    for (c = 0; c < cols; c++) html += '<col style="width:' + colMm.toFixed(1) + 'mm;">';
    html += '</colgroup>';
    for (r = 0; r < linhas; r++) {
      html += '<tr style="height:6.0mm;">';
      for (c = 0; c < cols; c++) html += '<td style="vertical-align:middle;">&nbsp;</td>';
      html += '</tr>';
    }
    return html + '</table>';
  }

  function estiloCelulaPadrao() {
    return 'border:0.4px solid #222;padding:3px;vertical-align:middle;';
  }

  function escolherArquivoImagem(cb) {
    var inp = document.createElement('input');
    inp.type = 'file';
    inp.accept = 'image/png,image/jpeg,image/gif,image/webp';
    inp.addEventListener('change', function () {
      if (inp.files && inp.files[0]) cb(inp.files[0]);
    });
    inp.click();
  }

  function inserirImagemNoCorpo(el, body, file) {
    arquivoParaDataUri(file, function (uri, err) {
      if (err || !uri) { setStatus(err || 'Não foi possível usar a imagem.'); return; }
      if (body) {
        body.focus();
        document.execCommand('insertHTML', false, htmlImgData(uri));
        sincronizarBloco(el, body);
        setStatus('Imagem inserida');
        return;
      }
      aplicarImagemColada(file);
    });
  }

  function celulaDaSelecao(body) {
    var sel = window.getSelection();
    var n = sel && sel.anchorNode;
    var el = n ? (n.nodeType === 1 ? n : n.parentElement) : null;
    if (!el || !body.contains(el)) return null;
    return el.closest('td,th');
  }

  function matrizTabela(table) {
    var grid = [];
    var r;
    for (r = 0; r < table.rows.length; r++) {
      grid[r] = grid[r] || [];
      var cursor = 0;
      var cells = table.rows[r].cells;
      var i;
      for (i = 0; i < cells.length; i++) {
        var td = cells[i];
        var cs = parseInt(td.getAttribute('colspan') || '1', 10) || 1;
        var rs = parseInt(td.getAttribute('rowspan') || '1', 10) || 1;
        while (grid[r][cursor]) cursor++;
        var rr;
        var cc;
        for (rr = 0; rr < rs; rr++) {
          grid[r + rr] = grid[r + rr] || [];
          for (cc = 0; cc < cs; cc++) {
            grid[r + rr][cursor + cc] = td;
          }
        }
        cursor += cs;
      }
    }
    return grid;
  }

  function indiceVisualCelula(grid, td) {
    var r;
    var c;
    for (r = 0; r < grid.length; r++) {
      for (c = 0; c < (grid[r] || []).length; c++) {
        if (grid[r][c] === td) return { row: r, col: c };
      }
    }
    return null;
  }

  var colunaMarcada = null;

  function limparMarcaColuna() {
    if (paper) {
      $all('.edoc-col-marcada', paper).forEach(function (n) { n.classList.remove('edoc-col-marcada'); });
    }
    colunaMarcada = null;
  }

  function celulasDaColuna(table, col) {
    var grid = matrizTabela(table);
    var vistos = [];
    var r;
    for (r = 0; r < grid.length; r++) {
      var cell = grid[r] && grid[r][col];
      if (!cell || vistos.indexOf(cell) >= 0) continue;
      var span = parseInt(cell.getAttribute('colspan') || '1', 10) || 1;
      if (span > 1) continue;
      var onde = indiceVisualCelula(grid, cell);
      if (!onde || onde.col !== col) continue;
      vistos.push(cell);
    }
    return vistos;
  }

  function marcarColuna(table, col) {
    limparMarcaColuna();
    if (!table) return;
    var vistos = celulasDaColuna(table, col);
    if (!vistos.length) return;
    vistos.forEach(function (cell) { cell.classList.add('edoc-col-marcada'); });
    colunaMarcada = { table: table, col: col };
  }

  function aplicarFonteNaColunaMarcada(delta, el, body) {
    if (!colunaMarcada || !colunaMarcada.table || !paper || !paper.contains(colunaMarcada.table)) return false;
    var vistos = celulasDaColuna(colunaMarcada.table, colunaMarcada.col);
    if (!vistos.length) return false;
    var base = fontePtDaCelula(vistos[0]);
    var pt = Math.max(6, Math.min(36, base + delta));
    vistos.forEach(function (cell) { cell.style.fontSize = pt + 'pt'; });
    if (el && body) sincronizarBloco(el, body);
    atualizarLabelFonte(pt);
    return true;
  }

  function fontePtDaCelula(cell) {
    var direto = cssSizeParaPt(cell.style && cell.style.fontSize);
    if (direto) return direto;
    return 8;
  }

  function larguraGrade(grid) {
    var largura = 0;
    grid.forEach(function (linha) {
      if (linha && linha.length > largura) largura = linha.length;
    });
    return largura;
  }

  function novaCelulaComo(ref) {
    var tag = (ref && ref.tagName) || 'TD';
    var cell = document.createElement(tag);
    cell.setAttribute('style', (ref && ref.getAttribute('style')) || estiloCelulaPadrao());
    cell.innerHTML = '&nbsp;';
    return cell;
  }

  function inserirLinhaApos(td) {
    var tr = td.parentElement;
    var table = td.closest('table');
    if (!tr || !table) return;
    var largura = Math.max(1, larguraGrade(matrizTabela(table)));
    var nova = document.createElement('tr');
    var c;
    for (c = 0; c < largura; c++) nova.appendChild(novaCelulaComo(td));
    tr.parentNode.insertBefore(nova, tr.nextSibling);
  }

  function inserirColunaApos(td) {
    var table = td.closest('table');
    if (!table) return;
    var grid = matrizTabela(table);
    var pos = indiceVisualCelula(grid, td);
    if (!pos) return;
    var colAlvo = pos.col;
    var visto = [];
    var r;
    for (r = 0; r < grid.length; r++) {
      var cell = grid[r] && grid[r][colAlvo];
      if (!cell || visto.indexOf(cell) >= 0) continue;
      visto.push(cell);
      var cs = parseInt(cell.getAttribute('colspan') || '1', 10) || 1;
      var origem = indiceVisualCelula(grid, cell);
      var last = origem ? origem.col + cs - 1 : colAlvo;
      if (last === colAlvo) {
        cell.parentNode.insertBefore(novaCelulaComo(cell), cell.nextSibling);
      } else {
        cell.setAttribute('colspan', String(cs + 1));
      }
    }
    var cg = table.querySelector('colgroup');
    if (cg) {
      var colEl = document.createElement('col');
      var ref = cg.children[colAlvo];
      if (ref && ref.nextSibling) cg.insertBefore(colEl, ref.nextSibling);
      else cg.appendChild(colEl);
    }
  }

  function mesclarComProxima(td) {
    var table = td.closest('table');
    if (!table) return;
    var grid = matrizTabela(table);
    var pos = indiceVisualCelula(grid, td);
    if (!pos) return;
    var cs = parseInt(td.getAttribute('colspan') || '1', 10) || 1;
    var rs = parseInt(td.getAttribute('rowspan') || '1', 10) || 1;
    var next = grid[pos.row] && grid[pos.row][pos.col + cs];
    if (!next || next === td) {
      setStatus('Não há célula à direita para mesclar.');
      return;
    }
    var ncs = parseInt(next.getAttribute('colspan') || '1', 10) || 1;
    var nrs = parseInt(next.getAttribute('rowspan') || '1', 10) || 1;
    if (nrs !== rs) {
      setStatus('Só é possível mesclar células com a mesma altura.');
      return;
    }
    var extra = String(next.innerHTML || '').replace(/&nbsp;|\s|<br\s*\/?>/gi, '');
    if (extra) {
      var atual = String(td.innerHTML || '').replace(/&nbsp;/g, '').trim();
      td.innerHTML = (atual ? td.innerHTML + ' ' : '') + next.innerHTML;
    }
    td.setAttribute('colspan', String(cs + ncs));
    next.remove();
  }

  function spanDe(cell, nome) {
    return parseInt(cell.getAttribute(nome) || '1', 10) || 1;
  }

  function definirSpan(cell, nome, n) {
    if (n > 1) cell.setAttribute(nome, String(n));
    else cell.removeAttribute(nome);
  }

  function htmlCelulaVazio(html) {
    return !String(html || '').replace(/&nbsp;|\s|<br\s*\/?>/gi, '');
  }

  var selecaoGrade = null;

  function limparSelecaoGrade() {
    if (paper) {
      $all('.edoc-celula-sel', paper).forEach(function (n) { n.classList.remove('edoc-celula-sel'); });
    }
    selecaoGrade = null;
  }

  function definirSelecao(table, r1, c1, r2, c2) {
    if (paper) {
      $all('.edoc-celula-sel', paper).forEach(function (n) { n.classList.remove('edoc-celula-sel'); });
    }
    limparMarcaColuna();
    selecaoGrade = { table: table, r1: r1, c1: c1, r2: r2, c2: c2 };
    var grid = matrizTabela(table);
    var ra = Math.min(r1, r2);
    var rb = Math.max(r1, r2);
    var ca = Math.min(c1, c2);
    var cb = Math.max(c1, c2);
    var vistos = [];
    var r;
    var c;
    for (r = ra; r <= rb; r++) {
      for (c = ca; c <= cb; c++) {
        var cell = grid[r] && grid[r][c];
        if (!cell || vistos.indexOf(cell) >= 0) continue;
        vistos.push(cell);
        cell.classList.add('edoc-celula-sel');
      }
    }
    var n = (rb - ra + 1) * (cb - ca + 1);
    if (n > 1) setStatus(n + ' células selecionadas. Clique em mesclar.');
  }

  function contextoTabela(table) {
    var elNode = table.closest('.edoc-el');
    if (!elNode) return null;
    var path = findPath(elNode.getAttribute('data-id'));
    var body = corpoDoElemento(elNode);
    if (!path || !path.element || !body) return null;
    var anterior = path.element.props.html || path.element.props.text || '';
    var wrap = document.createElement('div');
    wrap.innerHTML = String(anterior).indexOf('<') >= 0 ? anterior : esc(anterior);
    var vivas = body.querySelectorAll('table');
    var fontes = wrap.querySelectorAll('table');
    var ti = Array.prototype.indexOf.call(vivas, table);
    if (ti < 0 || !fontes[ti]) return null;
    return { path: path, wrap: wrap, fonte: fontes[ti], anterior: String(anterior) };
  }

  function gravarContexto(ctx) {
    if (editando) {
      var bodyEd = corpoDoElemento(editando.node);
      if (bodyEd) {
        bodyEd.oninput = null;
        bodyEd.onkeydown = null;
        bodyEd.onpaste = null;
      }
      esconderBarraInline();
      editando = null;
    }
    ctx.path.element.props = ctx.path.element.props || {};
    ctx.path.element.props.html = restaurarMedidasMm(ctx.anterior, ctx.wrap.innerHTML);
    delete ctx.path.element.props.text;
    pushHist();
    render();
  }

  function gravarMedidasDaFolha(table) {
    var elNode = table.closest('.edoc-el');
    if (!elNode) return;
    var path = findPath(elNode.getAttribute('data-id'));
    var body = corpoDoElemento(elNode);
    if (!path || !path.element || !body) return;
    path.element.props = path.element.props || {};
    var anterior = path.element.props.html || '';
    var novo = htmlDoEditor(body);
    path.element.props.html = (state.demo || state.preview) && anterior.indexOf('{{') >= 0
      ? restaurarMedidasMm(novo, anterior)
      : novo;
    delete path.element.props.text;
    pushHist();
    scheduleSave();
  }

  function separarCelula(td) {
    var table = td.closest('table');
    if (!table) return;
    var grid = matrizTabela(table);
    var pos = indiceVisualCelula(grid, td);
    if (!pos) return;
    var cs = spanDe(td, 'colspan');
    var rs = spanDe(td, 'rowspan');
    if (cs < 2 && rs < 2) return;
    var planos = [];
    var rr;
    for (rr = 0; rr < rs; rr++) {
      var tr = table.rows[pos.row + rr];
      if (!tr) continue;
      var antes = null;
      var i;
      var cells = tr.cells;
      for (i = 0; i < cells.length; i++) {
        var onde = indiceVisualCelula(grid, cells[i]);
        if (onde && onde.col > pos.col + cs - 1) {
          antes = cells[i];
          break;
        }
      }
      planos.push({ tr: tr, antes: antes, qtd: rr === 0 ? cs - 1 : cs });
    }
    definirSpan(td, 'colspan', 1);
    definirSpan(td, 'rowspan', 1);
    planos.forEach(function (plano) {
      var n;
      for (n = 0; n < plano.qtd; n++) {
        var nova = novaCelulaComo(td);
        if (plano.antes) plano.tr.insertBefore(nova, plano.antes);
        else plano.tr.appendChild(nova);
      }
    });
  }

  function mesclarRetangulo(table, r1, c1, r2, c2) {
    var ctx = contextoTabela(table);
    var alvo = ctx ? ctx.fonte : table;
    var ra = Math.min(r1, r2);
    var rb = Math.max(r1, r2);
    var ca = Math.min(c1, c2);
    var cb = Math.max(c1, c2);
    var grid = matrizTabela(alvo);
    var ancora = grid[ra] && grid[ra][ca];
    if (!ancora) return false;
    function concluir(msg) {
      if (ctx) gravarContexto(ctx);
      setStatus(msg);
    }
    if (ra === rb && ca === cb) {
      if (spanDe(ancora, 'colspan') > 1 || spanDe(ancora, 'rowspan') > 1) {
        separarCelula(ancora);
        concluir('Célula separada');
        return true;
      }
      var antes = spanDe(ancora, 'colspan');
      mesclarComProxima(ancora);
      if (spanDe(ancora, 'colspan') !== antes) concluir('Células mescladas');
      return true;
    }
    var lista = [];
    var r;
    var c;
    for (r = ra; r <= rb; r++) {
      for (c = ca; c <= cb; c++) {
        var cell = grid[r] && grid[r][c];
        if (!cell) {
          setStatus('Não foi possível mesclar essa seleção.');
          return false;
        }
        if (lista.indexOf(cell) < 0) lista.push(cell);
      }
    }
    var i;
    for (i = 0; i < lista.length; i++) {
      var onde = indiceVisualCelula(grid, lista[i]);
      var cs = spanDe(lista[i], 'colspan');
      var rs = spanDe(lista[i], 'rowspan');
      if (!onde || onde.row < ra || onde.col < ca || onde.row + rs - 1 > rb || onde.col + cs - 1 > cb) {
        setStatus('A seleção corta uma célula já mesclada.');
        return false;
      }
    }
    var html = String(ancora.innerHTML || '');
    for (i = 0; i < lista.length; i++) {
      if (lista[i] === ancora) continue;
      if (!htmlCelulaVazio(lista[i].innerHTML)) {
        html = (htmlCelulaVazio(html) ? '' : html + ' ') + lista[i].innerHTML;
      }
      lista[i].remove();
    }
    ancora.innerHTML = html;
    definirSpan(ancora, 'colspan', cb - ca + 1);
    definirSpan(ancora, 'rowspan', rb - ra + 1);
    concluir('Células mescladas');
    return true;
  }

  function alterarTabela(body, cmd) {
    if (cmd === 'tableInsert') {
      document.execCommand('insertHTML', false, htmlTabelaVazia(5, 6));
      setStatus('Tabela inserida. Clique numa célula para editar.');
      return;
    }
    if (cmd === 'tableMerge' && selecaoGrade && body.contains(selecaoGrade.table)) {
      mesclarRetangulo(selecaoGrade.table, selecaoGrade.r1, selecaoGrade.c1, selecaoGrade.r2, selecaoGrade.c2);
      return;
    }
    var td = celulaDaSelecao(body);
    if (!td) {
      if (cmd === 'tableInsRow' || cmd === 'tableInsCol') {
        document.execCommand('insertHTML', false, htmlTabelaVazia(cmd === 'tableInsRow' ? 2 : 3, cmd === 'tableInsCol' ? 2 : 4));
        return;
      }
      setStatus('Clique numa célula da tabela para usar esta ferramenta.');
      return;
    }
    var table = td.closest('table');
    if (!table) return;
    if (cmd === 'tableInsRow') {
      inserirLinhaApos(td);
      return;
    }
    if (cmd === 'tableInsCol') {
      inserirColunaApos(td);
      return;
    }
    if (cmd === 'tableMerge') {
      if (selecaoGrade && body.contains(selecaoGrade.table)) {
        mesclarRetangulo(selecaoGrade.table, selecaoGrade.r1, selecaoGrade.c1, selecaoGrade.r2, selecaoGrade.c2);
        return;
      }
      mesclarComProxima(td);
      return;
    }
    var grid = matrizTabela(table);
    var pos = indiceVisualCelula(grid, td);
    if (!pos) return;
    if (cmd === 'tableDelRow') {
      if (table.rows.length <= 1) return;
      table.rows[pos.row].remove();
      return;
    }
    var col = pos.col;
    var largura = larguraGrade(grid);
    if (largura <= 1) return;
    var visto = [];
    var r;
    for (r = 0; r < grid.length; r++) {
      var cell = grid[r] && grid[r][col];
      if (!cell || visto.indexOf(cell) >= 0) continue;
      visto.push(cell);
      var cs = parseInt(cell.getAttribute('colspan') || '1', 10) || 1;
      if (cs > 1) cell.setAttribute('colspan', String(cs - 1));
      else cell.remove();
    }
    var cols = table.querySelectorAll('colgroup col');
    if (cols[col]) cols[col].remove();
  }

  function medidasMm(html) {
    var cols = [];
    var rows = [];
    var rc = /<col\b[^>]*style="[^"]*width:\s*([0-9.]+)mm/gi;
    var rr = /<tr\b[^>]*style="[^"]*height:\s*([0-9.]+)mm/gi;
    var m;
    while ((m = rc.exec(html))) cols.push(m[1]);
    while ((m = rr.exec(html))) rows.push(m[1]);
    return { cols: cols, rows: rows };
  }

  function restaurarMedidasMm(anterior, novo) {
    if (!anterior || anterior.indexOf('seed-folha') < 0) return novo;
    var med = medidasMm(anterior);
    var cg = /<colgroup>[\s\S]*?<\/colgroup>/i.exec(anterior);
    if (cg && med.cols.length && medidasMm(novo).cols.length !== med.cols.length) {
      if (/<colgroup>/i.test(novo)) novo = novo.replace(/<colgroup>[\s\S]*?<\/colgroup>/i, cg[0]);
      else novo = novo.replace(/<table\b[^>]*>/i, function (t) { return t + cg[0]; });
    }
    var i = 0;
    novo = novo.replace(/<col\b([^>]*?)style="([^"]*)"/gi, function (full, pre, style) {
      var mm = med.cols[i++];
      if (!mm) return full;
      style = /width:\s*[0-9.]+mm/i.test(style)
        ? style.replace(/width:\s*[0-9.]+mm/i, 'width:' + mm + 'mm')
        : style + ';width:' + mm + 'mm';
      return '<col' + pre + 'style="' + style + '"';
    });
    i = 0;
    novo = novo.replace(/<tr\b([^>]*?)style="([^"]*)"/gi, function (full, pre, style) {
      var mm = med.rows[i++];
      if (!mm) return full;
      style = /height:\s*[0-9.]+mm/i.test(style)
        ? style.replace(/height:\s*[0-9.]+mm/i, 'height:' + mm + 'mm')
        : style + ';height:' + mm + 'mm';
      return '<tr' + pre + 'style="' + style + '"';
    });
    var tw = /(<table\b[^>]*\bseed-folha\b[^>]*style=")([^"]*)(")/i;
    var antW = /<table\b[^>]*\bseed-folha\b[^>]*style="[^"]*width:\s*([0-9.]+)mm/i.exec(anterior);
    if (antW) {
      novo = novo.replace(tw, function (full, a, style, c) {
        style = /width:\s*[0-9.]+mm/i.test(style)
          ? style.replace(/width:\s*[0-9.]+mm/i, 'width:' + antW[1] + 'mm')
          : style + ';width:' + antW[1] + 'mm';
        return a + style + c;
      });
    }
    return novo;
  }

  function persistirEdicaoSeHouver() {
    if (!editando) return;
    var body = corpoDoElemento(editando.node);
    var path = findPath(editando.id);
    if (path && path.element && body) {
      path.element.props = path.element.props || {};
      var anterior = path.element.props.html || '';
      path.element.props.html = restaurarMedidasMm(anterior, htmlDoEditor(body));
      delete path.element.props.text;
    }
    esconderBarraInline();
    editando = null;
  }

  function sincronizarBloco(el, body) {
    if (!el || !body) return;
    el.props = el.props || {};
    var anterior = el.props.html || '';
    el.props.html = restaurarMedidasMm(anterior, htmlDoEditor(body));
    delete el.props.text;
    state.dirty = true;
    scheduleSave();
    var rte = document.querySelector('.edoc-rte');
    if (rte && state.selected && state.selected.id === el.id) {
      rte.innerHTML = htmlParaEditor(el);
    }
  }

  function colocarCaretNoPonto(root, x, y) {
    if (!root) return;
    var r = null;
    if (document.caretRangeFromPoint) {
      r = document.caretRangeFromPoint(x, y);
    } else if (document.caretPositionFromPoint) {
      var pos = document.caretPositionFromPoint(x, y);
      if (pos) {
        r = document.createRange();
        r.setStart(pos.offsetNode, pos.offset);
        r.collapse(true);
      }
    }
    var body = corpoDoElemento(root) || root;
    if (!r || !body.contains(r.startContainer)) {
      body.focus();
      return;
    }
    var sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(r);
    body.focus();
  }

  function garantirBarraInline() {
    var bar = $('#edoc-inline-bar');
    if (bar) return bar;
    bar = document.createElement('div');
    bar.id = 'edoc-inline-bar';
    bar.className = 'edoc-inline-bar';
    bar.innerHTML = htmlBarraFmt(false);
    document.body.appendChild(bar);
    $all('[data-fmt]', bar).forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); e.stopPropagation(); });
      b.addEventListener('click', function (e) {
        e.stopPropagation();
        if (!editando) return;
        var body = corpoDoElemento(editando.node);
        var path = findPath(editando.id);
        if (!body || !path || !path.element) return;
        body.focus();
        executarFmt(b.getAttribute('data-fmt'), path.element, body);
        sincronizarBloco(path.element, body);
      });
    });
    return bar;
  }

  function mostrarBarraInline(elNode) {
    var bar = garantirBarraInline();
    bar.classList.add('is-open');
    function pos() {
      var body = corpoDoElemento(elNode);
      var r = (body || elNode).getBoundingClientRect();
      bar.style.left = Math.max(8, r.left) + 'px';
      bar.style.top = Math.max(8, r.top - 42) + 'px';
    }
    pos();
    if (bar._off) bar._off();
    var stage = $('.edoc-stage');
    if (stage) {
      stage.addEventListener('scroll', pos);
      window.addEventListener('resize', pos);
      bar._off = function () {
        stage.removeEventListener('scroll', pos);
        window.removeEventListener('resize', pos);
      };
    }
  }

  function esconderBarraInline() {
    var bar = $('#edoc-inline-bar');
    if (!bar) return;
    bar.classList.remove('is-open');
    if (bar._off) { bar._off(); bar._off = null; }
  }

  function soltarCelulasEditaveis(body) {
    if (!body) return;
    $all('[contenteditable="true"]', body).forEach(function (n) {
      if (n === body) return;
      n.removeAttribute('contenteditable');
      n.removeAttribute('spellcheck');
    });
  }

  function focarCelulaEditavel(celula) {
    if (!celula) return;
    var body = celula.closest('.edoc-el-body') || celula.closest('.edoc-html-raw');
    if (body) {
      soltarCelulasEditaveis(body);
      if (body.getAttribute('contenteditable') === 'true' && body.querySelector('table')) {
        body.removeAttribute('contenteditable');
      }
    }
    celula.contentEditable = 'true';
    celula.setAttribute('spellcheck', 'true');
    celula.focus();
  }

  function iniciarEdicaoNaFolha(node, celula) {
    if (!node || state.preview) return;
    var id = node.getAttribute('data-id');
    var path = findPath(id);
    if (!path || !path.element || !ehTextoEditavel(path.element.type)) return;
    var body = corpoDoElemento(node);
    if (!body) return;
    editando = { id: id, node: node };
    node.classList.add('is-editing');
    var temTabela = !!body.querySelector('table');
    if (temTabela) {
      body.removeAttribute('contenteditable');
      soltarCelulasEditaveis(body);
      if (celula && body.contains(celula)) focarCelulaEditavel(celula);
    } else {
      body.contentEditable = 'true';
      body.setAttribute('spellcheck', 'true');
      body.focus();
    }
    mostrarBarraInline(node);
    atualizarLabelFonte(tamanhoFontePt(path.element, body));
    body.onkeydown = function (e) {
      if (e.key === 'Enter') {
        if (celulaDaSelecao(body)) return;
        e.preventDefault();
        inserirQuebraLinha();
        sincronizarBloco(path.element, body);
        return;
      }
      if ((e.metaKey || e.ctrlKey) && (e.key === 'b' || e.key === 'B' || e.key === 'i' || e.key === 'I' || e.key === 'u' || e.key === 'U')) {
        e.preventDefault();
        var cmd = (e.key === 'b' || e.key === 'B') ? 'bold' : ((e.key === 'i' || e.key === 'I') ? 'italic' : 'underline');
        document.execCommand(cmd);
        sincronizarBloco(path.element, body);
      }
      if (e.key === 'Escape') {
        e.preventDefault();
        persistirEdicaoSeHouver();
        render();
      }
    };
    body.oninput = function () { sincronizarBloco(path.element, body); };
    body.onpaste = function (e) {
      var file = arquivoDoClipboard(e.clipboardData);
      if (file) {
        e.preventDefault();
        e.stopPropagation();
        arquivoParaDataUri(file, function (uri, err) {
          if (err || !uri) { setStatus(err || 'Não foi possível colar a imagem.'); return; }
          if (selecaoCobreTudo(body)) {
            var sel = window.getSelection();
            sel.removeAllRanges();
            var fim = document.createRange();
            fim.selectNodeContents(body);
            fim.collapse(false);
            sel.addRange(fim);
          }
          var wrap = celulaDaSelecao(body) ? '' : '<br>';
          document.execCommand('insertHTML', false, wrap + htmlImgData(uri) + wrap);
          sincronizarBloco(path.element, body);
        });
        return;
      }
      var html = (e.clipboardData && e.clipboardData.getData('text/html')) || '';
      if (!html) return;
      e.preventDefault();
      var clean = sanitizeHtml(html);
      if (!String(clean).replace(/<br\s*\/?>|&nbsp;|\s/gi, '')) return;
      document.execCommand('insertHTML', false, clean);
      sincronizarBloco(path.element, body);
    };
  }

  function htmlElemento(el) {
    var p = el.props || {};
    var st = cssBox(el.style);
    var t = el.type;
    if (t === 'titulo') {
      var tag = p.tag === 'h2' || p.tag === 'h3' ? p.tag : 'h1';
      var sz = tag === 'h1' ? '16pt' : (tag === 'h2' ? '13pt' : '11pt');
      if (el.style && el.style.fontSize) sz = parseInt(el.style.fontSize, 10) + 'pt';
      return '<' + tag + ' class="edoc-el-body" style="margin:0;font-size:' + sz + ';' + st + '">' + htmlTextoInterno(el, 'Título') + '</' + tag + '>';
    }
    if (t === 'texto' || t === 'texto_rico') {
      return '<div class="edoc-el-body" style="' + st + '">' + htmlTextoInterno(el, 'Texto') + '</div>';
    }
    if (t === 'html') return '<div class="edoc-html-raw edoc-el-body" style="' + st + '">' + htmlTextoInterno(el) + '</div>';
    if (t === 'logo') {
      var w = p.width || 120;
      var img = (C.logoPreview || '');
      var inner = img
        ? '<img src="' + img.replace(/"/g, '') + '" alt="Logo" style="max-width:' + w + 'px;width:auto;height:auto;object-fit:contain;">'
        : '<div class="edoc-logo-slot" style="max-width:' + w + 'px">LOGO</div>';
      return '<div class="edoc-media" style="' + st + posicaoCss(el) + '">' + inner + '</div>';
    }
    if (t === 'imagem') {
      var innerImg;
      if (p.src && /^data:image\/(png|jpeg|jpg|gif|webp);base64,/i.test(p.src)) {
        innerImg = '<img src="' + String(p.src).replace(/"/g, '') + '" alt="" style="max-width:' + (p.width || 180) + 'px;width:auto;height:auto;">';
      } else {
        innerImg = '<div class="edoc-logo-slot">Imagem</div>';
      }
      return '<div class="edoc-media" style="' + st + posicaoCss(el) + '">' + innerImg + '</div>';
    }
    if (t === 'linha') return '<hr style="border:none;border-top:1px solid #d1d5db;margin:8px 0;">';
    if (t === 'espacador') return '<div style="height:' + (p.height || 16) + 'px"></div>';
    if (t === 'pagina') return '<p style="' + st + 'text-align:center">Página ' + ph('{{pagina}}') + ' de ' + ph('{{total_paginas}}') + '</p>';
    if (t === 'quebra_pagina') return '<div style="border-top:2px dashed #f59e0b;margin:12px 0;color:#b45309;font-size:10px;text-align:center">Quebra de página</div>';
    if (t === 'qrcode') return '<div class="edoc-logo-slot">QR</div>';
    if (t === 'tabela_notas') {
      var quadro = ((state.preview || state.demo) && C.varsPreview && C.varsPreview.quadro_notas_html)
        ? C.varsPreview.quadro_notas_html
        : '{{quadro_notas_html}}';
      var quadroHtml = String(quadro).indexOf('{{') === 0
        ? '<span class="edoc-ph">' + esc(quadro) + '</span>'
        : sanitizeHtml(quadro);
      return '<div class="edoc-quadro-notas" style="' + st + '">' + quadroHtml + '</div>';
    }
    if (t === 'assinaturas') {
      return '<div style="' + st + ';display:flex;gap:16px;margin-top:28px">'
        + '<div style="flex:1;text-align:center">____________<br><small>Responsável</small></div>'
        + '<div style="flex:1;text-align:center">____________<br><small>Direção</small></div></div>';
    }
    var samples = {
      dados_escola: 'Escola / CNPJ / Endereço',
      dados_aluno: 'Aluno, turma, matrícula',
      dados_responsavel: 'Responsável e contato',
      dados_turma: 'Turma, série, ano letivo',
      tabela_aluno: 'Tabela do aluno',
      tabela_notas: 'Tabela de notas',
      tabela_frequencia: 'Tabela de frequência',
      historico: 'Histórico escolar',
      resultado_final: 'Resultado final',
      frequencia: 'Frequência',
      observacoes: 'Observações'
    };
    return '<div style="' + st + 'border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;font-size:12px;background:#f9fafb">'
      + '<strong>' + labelTipo(t) + '</strong><div style="color:#6b7280;margin-top:4px">' + (samples[t] || '') + '</div></div>';
  }

  function renderSection(sec, role) {
    var cols = sec.columns || [];
    var html = '';
    if (sec.pageBreakBefore) {
      html += '<div class="edoc-page-split">Quebra de página — verso</div>';
    }
    html += '<div class="edoc-section' + (state.selected && state.selected.id === sec.id ? ' is-selected' : '') + '" data-id="' + sec.id + '" data-kind="section">';
    html += '<div class="edoc-section-bar">'
      + '<button type="button" data-act="sec-up" title="Acima"><i class="fa-solid fa-arrow-up"></i></button>'
      + '<button type="button" data-act="sec-down" title="Abaixo"><i class="fa-solid fa-arrow-down"></i></button>'
      + '<button type="button" data-act="sec-dup" title="Duplicar"><i class="fa-solid fa-copy"></i></button>'
      + '<button type="button" data-act="sec-del" title="Excluir"><i class="fa-solid fa-trash"></i></button>'
      + '</div>';
    html += '<div class="edoc-cols">';
    cols.forEach(function (col, i) {
      if (i) {
        html += '<div class="edoc-gutter" data-section="' + sec.id + '" data-gutter="' + (i - 1) + '"><span class="edoc-gutter-tip"></span></div>';
      }
      html += '<div class="edoc-col' + (state.selected && state.selected.id === col.id ? ' is-selected' : '')
        + '" data-id="' + col.id + '" data-kind="column" style="width:' + (col.width || 100) + '%;flex:0 0 auto;justify-content:'
        + ({ top: 'flex-start', middle: 'center', bottom: 'flex-end' }[col.vAlign || 'top'] || 'flex-start') + '">';
      html += '<div class="edoc-col-drop">Solte o elemento aqui</div>';
      (col.elements || []).forEach(function (el) {
        var sel = state.selected && state.selected.id === el.id;
        html += '<div class="edoc-el' + (sel ? ' is-selected' : '')
          + ((el.type === 'imagem') ? ' edoc-el-stretch' : '')
          + '" data-id="' + el.id + '" data-kind="element" data-type="' + el.type + '">';
        html += '<div class="edoc-el-toolbar">'
          + '<button type="button" data-act="move" title="Mover"><i class="fa-solid fa-up-down-left-right"></i></button>'
          + '<button type="button" data-act="dup" title="Duplicar"><i class="fa-solid fa-copy"></i></button>'
          + '<button type="button" data-act="del" title="Excluir"><i class="fa-solid fa-trash"></i></button>'
          + '</div>';
        if (el.type === 'logo' || el.type === 'imagem') {
          html += '<div class="edoc-el-handles"><i class="nw"></i><i class="ne"></i><i class="sw"></i><i class="se"></i></div>';
        }
        html += htmlElemento(el);
        html += '</div>';
      });
      html += '</div>';
    });
    html += '</div></div>';
    return html;
  }

  function render() {
    persistirEdicaoSeHouver();
    var pg = mmPage();
    var z = state.zoom / 100;
    paper = $('#edoc-paper');
    var wrap = $('#edoc-paper-wrap');
    if (!paper || !wrap) return;
    paper.style.width = pg.w + 'mm';
    paper.style.minHeight = pg.h + 'mm';
    paper.style.padding = pg.margin.top + 'mm ' + pg.margin.right + 'mm ' + pg.margin.bottom + 'mm ' + pg.margin.left + 'mm';
    paper.style.transform = 'none';
    wrap.style.zoom = String(z);
    wrap.style.width = pg.w + 'mm';
    wrap.style.minHeight = pg.h + 'mm';
    paper.classList.toggle('edoc-preview', !!state.preview);
    var fiel = JSON.stringify(state.estrutura).indexOf('seed-folha') >= 0;
    paper.classList.toggle('edoc-paper-fiel', fiel);
    var fundo = (state.estrutura.page && state.estrutura.page.fundo) || '';
    if (/^data:image\/(png|jpeg|jpg|gif|webp);base64,/i.test(fundo)) {
      paper.style.backgroundImage = 'url("' + fundo + '")';
      paper.style.backgroundSize = '100% 100%';
      paper.style.backgroundRepeat = 'no-repeat';
      paper.style.backgroundOrigin = 'border-box';
      paper.style.backgroundPosition = 'center';
    } else {
      paper.style.backgroundImage = '';
    }

    var html = '';
    [['header', 'Cabeçalho'], ['body', 'Corpo'], ['footer', 'Rodapé']].forEach(function (pair) {
      var secs = areaOf(pair[0]).sections || [];
      if (fiel && !secs.length) return;
      if (!fiel) html += '<div class="edoc-area-label">' + pair[1] + '</div>';
      if (!secs.length) {
        html += '<div class="edoc-empty edoc-dropzone" data-empty="' + pair[0] + '">Clique, arraste Tabela/Imagem ou cole (Ctrl+V)</div>';
      }
      secs.forEach(function (s) { html += renderSection(s, pair[0]); });
    });
    selecaoGrade = null;
    paper.innerHTML = html;
    desenharReguas(pg);
    avisoFolha(pg);
    bindCanvas();
    renderTree();
    renderProps();
    var zl = $('#edoc-zoom-label');
    if (zl) zl.textContent = state.zoom + '%';
  }

  function desenharReguas(pg) {
    var h = document.querySelector('.edoc-ruler-h');
    var v = document.querySelector('.edoc-ruler-v');
    if (!h || !v) return;
    var ticksH = '';
    var ticksV = '';
    var mm;
    for (mm = 0; mm <= pg.w; mm += 10) ticksH += '<span style="left:' + mm + 'mm">' + mm + '</span>';
    for (mm = 0; mm <= pg.h; mm += 10) ticksV += '<span style="top:' + mm + 'mm">' + mm + '</span>';
    h.innerHTML = ticksH;
    v.innerHTML = ticksV;
  }

  function avisoFolha(pg) {
    var el = $('#edoc-folha-aviso');
    if (!el || !paper) return;
    var utilH = pg.h - (pg.margin.top || 0) - (pg.margin.bottom || 0);
    var utilW = pg.w - (pg.margin.left || 0) - (pg.margin.right || 0);
    var msg = '';
    paper.querySelectorAll('table.seed-folha').forEach(function (tabela) {
      var alt = 0;
      tabela.querySelectorAll(':scope > tbody > tr, :scope > tr').forEach(function (tr) {
        var m = /height:\s*([0-9.]+)mm/i.exec(tr.getAttribute('style') || '');
        if (m) alt += parseFloat(m[1]);
      });
      if (!alt) {
        tabela.querySelectorAll('tr').forEach(function (tr) {
          var m = /height:\s*([0-9.]+)mm/i.exec(tr.getAttribute('style') || '');
          if (m) alt += parseFloat(m[1]);
        });
      }
      var larg = 0;
      var mw = /width:\s*([0-9.]+)mm/i.exec(tabela.getAttribute('style') || '');
      if (mw) larg = parseFloat(mw[1]);
      if (alt > utilH + 0.3) msg = 'Altura da grade passa ' + (alt - utilH).toFixed(1) + ' mm da área útil. A impressão não reduz.';
      else if (larg > utilW + 0.3) msg = 'Largura da grade passa ' + (larg - utilW).toFixed(1) + ' mm da área útil. A impressão não reduz.';
    });
    el.textContent = msg;
  }

  function lerChaveDrop(dt) {
    var chave = (dt.getData('text/edoc-var') || '').trim();
    if (chave) return chave;
    var plain = dt.getData('text/plain') || '';
    if (plain.indexOf('edoc-var:') === 0) return plain.slice(9).trim();
    return '';
  }

  function marcarSelecionado(elNode, id) {
    state.selected = { id: id, kind: 'element' };
    $all('.is-selected', paper).forEach(function (n) { n.classList.remove('is-selected'); });
    if (elNode) elNode.classList.add('is-selected');
  }

  function textoMm(n) {
    return (Math.round(n * 10) / 10).toFixed(1).replace('.', ',') + ' mm';
  }

  function mmPorPxPapel() {
    if (!paper) return 1;
    var rect = paper.getBoundingClientRect();
    if (!rect.width) return 1;
    return mmPage().w / rect.width;
  }

  function mostrarGuia(eixo, px, texto) {
    if (!paper) return;
    var guia = document.getElementById('edoc-guia');
    if (!guia) {
      guia = document.createElement('div');
      guia.id = 'edoc-guia';
      guia.innerHTML = '<i class="edoc-guia-linha"></i><b class="edoc-guia-rotulo"></b>';
      document.body.appendChild(guia);
    }
    var paperRect = paper.getBoundingClientRect();
    var rulerH = document.querySelector('.edoc-ruler-h');
    var rulerV = document.querySelector('.edoc-ruler-v');
    var topo = rulerH ? rulerH.getBoundingClientRect().top : paperRect.top;
    var esquerda = rulerV ? rulerV.getBoundingClientRect().left : paperRect.left;
    var linha = guia.querySelector('.edoc-guia-linha');
    var rotulo = guia.querySelector('.edoc-guia-rotulo');
    guia.className = 'is-on ' + (eixo === 'v' ? 'is-v' : 'is-h');
    if (eixo === 'v') {
      linha.style.cssText = 'top:' + topo + 'px;left:' + px + 'px;height:' + Math.max(0, paperRect.bottom - topo) + 'px;width:1px;';
      rotulo.style.cssText = 'left:' + (px + 8) + 'px;top:' + Math.max(4, topo) + 'px;';
    } else {
      linha.style.cssText = 'left:' + esquerda + 'px;top:' + px + 'px;width:' + Math.max(0, paperRect.right - esquerda) + 'px;height:1px;';
      rotulo.style.cssText = 'left:' + (esquerda + 8) + 'px;top:' + (px + 8) + 'px;';
    }
    rotulo.textContent = texto;
  }

  function esconderGuia() {
    var guia = document.getElementById('edoc-guia');
    if (guia) guia.className = '';
  }

  function bordaDeColuna(e) {
    var cell = e.target && e.target.closest ? e.target.closest('td, th') : null;
    if (!cell || !paper || !paper.contains(cell)) return null;
    var table = cell.closest('table');
    if (!table || !table.closest('.edoc-html-raw, .edoc-el-body')) return null;
    var rect = cell.getBoundingClientRect();
    var direita = rect.right - e.clientX <= 8 && e.clientX <= rect.right + 3;
    var esquerda = e.clientX - rect.left <= 8 && e.clientX >= rect.left - 3;
    if (!direita && !esquerda) return null;
    var grid = matrizTabela(table);
    var pos = indiceVisualCelula(grid, cell);
    if (!pos) return null;
    var cs = parseInt(cell.getAttribute('colspan') || '1', 10) || 1;
    var n = larguraGrade(grid);
    var a = direita ? pos.col + cs - 1 : pos.col - 1;
    var b = a + 1;
    if (a < 0 || b >= n) return null;
    return { table: table, esquerda: a, direita: b };
  }

  function largurasPxTabela(table) {
    var grid = matrizTabela(table);
    var n = larguraGrade(grid);
    var widths = [];
    var c;
    var r;
    for (c = 0; c < n; c++) {
      var cell = null;
      for (r = 0; r < grid.length; r++) {
        var candidato = grid[r] && grid[r][c];
        if (!candidato) continue;
        var onde = indiceVisualCelula(grid, candidato);
        if (onde && onde.col === c) {
          cell = candidato;
          break;
        }
      }
      if (!cell) {
        widths.push(28);
        continue;
      }
      var cs = parseInt(cell.getAttribute('colspan') || '1', 10) || 1;
      widths.push(Math.max(16, cell.getBoundingClientRect().width / cs));
    }
    return widths;
  }

  function aplicarLargurasTabela(table, widths) {
    var soma = widths.reduce(function (acc, w) { return acc + w; }, 0) || 1;
    var cg = table.querySelector('colgroup');
    if (!cg) {
      cg = document.createElement('colgroup');
      table.insertBefore(cg, table.firstChild);
    }
    while (cg.children.length < widths.length) cg.appendChild(document.createElement('col'));
    while (cg.children.length > widths.length) cg.removeChild(cg.lastChild);
    var seed = table.classList.contains('seed-folha');
    var totalMm = 0;
    if (seed) {
      var mw = /width:\s*([0-9.]+)mm/i.exec(table.getAttribute('style') || '');
      totalMm = mw ? parseFloat(mw[1]) : 0;
    }
    widths.forEach(function (w, i) {
      if (seed && totalMm > 0) {
        cg.children[i].style.width = (Math.round((w / soma) * totalMm * 10) / 10).toFixed(1) + 'mm';
      } else {
        cg.children[i].style.width = ((w / soma) * 100).toFixed(2) + '%';
      }
    });
    table.style.tableLayout = 'fixed';
    if (!seed) table.style.width = '100%';
  }

  function tentarResizeColuna(e) {
    var info = bordaDeColuna(e);
    if (!info) return false;
    e.preventDefault();
    e.stopPropagation();
    var widths = largurasPxTabela(info.table);
    var startX = e.clientX;
    var esq0 = widths[info.esquerda];
    var dir0 = widths[info.direita];
    var somaPar = esq0 + dir0;
    function move(ev) {
      var na = esq0 + (ev.clientX - startX);
      if (na < 22) na = 22;
      if (na > somaPar - 22) na = somaPar - 22;
      widths[info.esquerda] = na;
      widths[info.direita] = somaPar - na;
      aplicarLargurasTabela(info.table, widths);
      var borda = direitaDaColuna(info.table, info.esquerda);
      if (borda == null) return;
      var escala = mmPorPxPapel();
      mostrarGuia('v', borda, textoMm((borda - paper.getBoundingClientRect().left) * escala)
        + ' · largura ' + textoMm(na * escala));
    }
    function up() {
      document.removeEventListener('mousemove', move);
      document.removeEventListener('mouseup', up);
      document.body.classList.remove('edoc-arrastando-col');
      esconderGuia();
      gravarMedidasDaFolha(info.table);
    }
    document.body.classList.add('edoc-arrastando-col');
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
    return true;
  }

  function direitaDaColuna(table, col) {
    var grid = matrizTabela(table);
    var r;
    for (r = 0; r < grid.length; r++) {
      var cell = grid[r] && grid[r][col];
      if (!cell) continue;
      var onde = indiceVisualCelula(grid, cell);
      var cs = spanDe(cell, 'colspan');
      if (onde && onde.col + cs - 1 === col) return cell.getBoundingClientRect().right;
    }
    return null;
  }

  function bordaDeLinha(e) {
    var cell = e.target && e.target.closest ? e.target.closest('td, th') : null;
    if (!cell || !paper || !paper.contains(cell)) return null;
    var table = cell.closest('table');
    if (!table || !table.closest('.edoc-html-raw, .edoc-el-body')) return null;
    var rect = cell.getBoundingClientRect();
    var baixo = rect.bottom - e.clientY <= 6 && e.clientY <= rect.bottom + 3;
    var cima = e.clientY - rect.top <= 6 && e.clientY >= rect.top - 3;
    if (!baixo && !cima) return null;
    var grid = matrizTabela(table);
    var pos = indiceVisualCelula(grid, cell);
    if (!pos) return null;
    var row = baixo ? pos.row + spanDe(cell, 'rowspan') - 1 : pos.row - 1;
    if (row < 0 || row >= table.rows.length) return null;
    return { table: table, row: row };
  }

  function tentarResizeLinha(e) {
    var info = bordaDeLinha(e);
    if (!info) return false;
    e.preventDefault();
    e.stopPropagation();
    var tr = info.table.rows[info.row];
    if (!tr) return false;
    var startY = e.clientY;
    var h0 = tr.getBoundingClientRect().height;
    function move(ev) {
      var h = Math.max(8, h0 + (ev.clientY - startY));
      var mm = Math.max(3, Math.round(h * mmPorPxPapel() * 10) / 10);
      tr.style.height = mm.toFixed(1) + 'mm';
      var fundo = tr.getBoundingClientRect().bottom;
      mostrarGuia('h', fundo, textoMm((fundo - paper.getBoundingClientRect().top) * mmPorPxPapel())
        + ' · altura ' + textoMm(mm));
    }
    function up() {
      document.removeEventListener('mousemove', move);
      document.removeEventListener('mouseup', up);
      document.body.classList.remove('edoc-arrastando-linha');
      esconderGuia();
      gravarMedidasDaFolha(info.table);
    }
    document.body.classList.add('edoc-arrastando-linha');
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
    return true;
  }

  function celulaSobPonto(table, alvoEvento, x, y) {
    var direto = alvoEvento && alvoEvento.closest ? alvoEvento.closest('td, th') : null;
    if (direto && table.contains(direto)) return direto;
    if (!table || x == null || y == null) return null;
    var cells = table.querySelectorAll('td, th');
    var melhor = null;
    var area = Infinity;
    var i;
    for (i = 0; i < cells.length; i++) {
      var rect = cells[i].getBoundingClientRect();
      if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) continue;
      var a = rect.width * rect.height;
      if (a < area) {
        area = a;
        melhor = cells[i];
      }
    }
    return melhor;
  }

  var gestoCelula = null;

  function garantirEdicaoDaTabela(table) {
    var elNode = table && table.closest('.edoc-el');
    if (!elNode) return;
    var id = elNode.getAttribute('data-id');
    if (editando && editando.id === id) return;
    persistirEdicaoSeHouver();
    marcarSelecionado(elNode, id);
    iniciarEdicaoNaFolha(elNode);
    renderProps();
  }

  function ativarCelula(celula) {
    if (!celula || !paper || !paper.contains(celula)) return;
    var tabela = celula.closest('table');
    var grade = tabela && matrizTabela(tabela);
    var pos = grade && indiceVisualCelula(grade, celula);
    if (celula.tagName === 'TH' && tabela && grade && pos) {
      var span = spanDe(celula, 'colspan');
      if (span < larguraGrade(grade)) {
        marcarColuna(tabela, pos.col);
        garantirEdicaoDaTabela(tabela);
        setStatus('Coluna selecionada. Use A+ ou A− para o texto desta coluna.');
        return;
      }
    }
    if (celula.tagName === 'TD') limparMarcaColuna();
    var elNode = celula.closest('.edoc-el');
    var tipo = elNode ? elNode.getAttribute('data-type') : '';
    if (!elNode || !ehTextoEditavel(tipo)) return;
    var id = elNode.getAttribute('data-id');
    if (editando && editando.id === id) {
      focarCelulaEditavel(celula);
      return;
    }
    persistirEdicaoSeHouver();
    marcarSelecionado(elNode, id);
    iniciarEdicaoNaFolha(elNode, celula);
    renderProps();
    focarCelulaEditavel(celula);
  }

  function iniciarGestoSelecao(celula, tabela, pos) {
    gestoCelula = { table: tabela, cell: celula, r: pos.row, c: pos.col, arrastou: false };
    definirSelecao(tabela, pos.row, pos.col, pos.row, pos.col);
    function move(ev) {
      if (!gestoCelula) return;
      var alvo = celulaSobPonto(gestoCelula.table, ev.target, ev.clientX, ev.clientY);
      if (!alvo) return;
      var grade = matrizTabela(gestoCelula.table);
      var onde = indiceVisualCelula(grade, alvo);
      if (!onde) return;
      if (onde.row === gestoCelula.r && onde.col === gestoCelula.c && !gestoCelula.arrastou) return;
      gestoCelula.arrastou = true;
      document.body.classList.add('edoc-selecionando-celulas');
      var sel = window.getSelection();
      if (sel) sel.removeAllRanges();
      definirSelecao(gestoCelula.table, gestoCelula.r, gestoCelula.c, onde.row, onde.col);
    }
    function up() {
      document.removeEventListener('mousemove', move);
      document.removeEventListener('mouseup', up);
      document.body.classList.remove('edoc-selecionando-celulas');
      var g = gestoCelula;
      gestoCelula = null;
      if (!g) return;
      if (g.arrastou) {
        var sel = window.getSelection();
        if (sel) sel.removeAllRanges();
        garantirEdicaoDaTabela(g.table);
        return;
      }
      ativarCelula(g.cell);
    }
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
  }

  function bindCanvas() {
    if (!paper) return;
    if (!paper._edocVarDrop) {
      paper._edocVarDrop = true;
      paper.addEventListener('dragover', function (e) {
        if (!arrastandoVariavel(e.dataTransfer)) return;
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        marcarCelulaDrop(celulaDoEvento(e));
      }, true);
      paper.addEventListener('drop', function (e) {
        var chave = lerChaveDrop(e.dataTransfer);
        var celula = celulaDoEvento(e);
        if (!chave || !celula) return;
        e.preventDefault();
        e.stopPropagation();
        marcarCelulaDrop(null);
        inserirVariavelNaCelula(celula, chave);
      }, true);
    }
    paper.onmousemove = function (e) {
      if (state.preview || document.body.classList.contains('edoc-arrastando-col') || document.body.classList.contains('edoc-arrastando-linha')) return;
      var naColuna = !!bordaDeColuna(e);
      paper.classList.toggle('edoc-col-resize', naColuna);
      paper.classList.toggle('edoc-row-resize', !naColuna && !!bordaDeLinha(e));
    };
    paper.onmouseleave = function () {
      paper.classList.remove('edoc-col-resize');
      paper.classList.remove('edoc-row-resize');
    };
    paper.onmousedown = function (e) {
      if (state.preview) return;
      if (e.button && e.button !== 0) return;
      if (tentarResizeColuna(e)) return;
      if (tentarResizeLinha(e)) return;
      if (e.target.closest('[data-act]') || e.target.closest('.edoc-el-toolbar') || e.target.closest('#edoc-inline-bar')) return;
      var celulaClique = e.target.closest('td, th');
      if (celulaClique && paper.contains(celulaClique)) {
        var tabelaClique = celulaClique.closest('table');
        if (tabelaClique && tabelaClique.closest('.edoc-html-raw, .edoc-el-body')) {
          var gradeClique = matrizTabela(tabelaClique);
          var posClique = indiceVisualCelula(gradeClique, celulaClique);
          if (posClique) {
            if (e.shiftKey && selecaoGrade && selecaoGrade.table === tabelaClique) {
              e.preventDefault();
              definirSelecao(tabelaClique, selecaoGrade.r1, selecaoGrade.c1, posClique.row, posClique.col);
              garantirEdicaoDaTabela(tabelaClique);
              return;
            }
            iniciarGestoSelecao(celulaClique, tabelaClique, posClique);
            return;
          }
        }
      }
      limparSelecaoGrade();
      var elNode = e.target.closest('.edoc-el');
      var tipo = elNode ? elNode.getAttribute('data-type') : '';
      if (!elNode || !ehTextoEditavel(tipo)) return;
      var id = elNode.getAttribute('data-id');
      if (editando && editando.id === id) return;
      persistirEdicaoSeHouver();
      marcarSelecionado(elNode, id);
      iniciarEdicaoNaFolha(elNode);
      renderProps();
      var body = corpoDoElemento(elNode);
      if (body) body.focus();
    };
    paper.ondblclick = function (e) {
      var th = e.target.closest('th');
      if (!th || !paper.contains(th)) return;
      limparMarcaColuna();
      var tabela = th.closest('table');
      if (tabela) garantirEdicaoDaTabela(tabela);
      focarCelulaEditavel(th);
      var range = document.createRange();
      range.selectNodeContents(th);
      range.collapse(true);
      var sel = window.getSelection();
      if (!sel) return;
      sel.removeAllRanges();
      sel.addRange(range);
    };
    paper.onclick = onCanvasClick;
    $all('.edoc-col', paper).forEach(function (col) {
      col.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        col.classList.add('is-over');
        var celula = celulaDoEvento(e);
        marcarCelulaDrop(arrastandoVariavel(e.dataTransfer) ? celula : null);
      });
      col.addEventListener('dragleave', function (e) {
        col.classList.remove('is-over');
        if (!e.relatedTarget || !col.contains(e.relatedTarget)) marcarCelulaDrop(null);
      });
      col.addEventListener('drop', function (e) {
        e.preventDefault();
        col.classList.remove('is-over');
        var celula = celulaDoEvento(e);
        marcarCelulaDrop(null);
        var tipo = e.dataTransfer.getData('text/edoc-type');
        var layout = e.dataTransfer.getData('text/edoc-layout');
        var chave = lerChaveDrop(e.dataTransfer);
        if (chave && celula && inserirVariavelNaCelula(celula, chave)) return;
        if (layout) {
          insertLayoutAt(col.getAttribute('data-id'), layout);
        } else if (chave) {
          insertVariavel(col.getAttribute('data-id'), chave);
        } else if (tipo) {
          insertElement(col.getAttribute('data-id'), tipo);
        } else if (e.dataTransfer.files && e.dataTransfer.files[0]) {
          inserirArquivoNaColuna(col.getAttribute('data-id'), e.dataTransfer.files[0]);
        }
      });
    });
    $all('.edoc-gutter', paper).forEach(bindGutter);
    $all('[data-empty]', paper).forEach(function (el) {
      el.addEventListener('dragover', function (e) { e.preventDefault(); });
      el.addEventListener('click', function (e) {
        e.stopPropagation();
        addSection(el.getAttribute('data-empty'), [100]);
      });
      el.addEventListener('drop', function (e) {
        e.preventDefault();
        var layout = e.dataTransfer.getData('text/edoc-layout');
        var tipo = e.dataTransfer.getData('text/edoc-type');
        var chave = lerChaveDrop(e.dataTransfer);
        var role = el.getAttribute('data-empty');
        if (layout) addSection(role, JSON.parse(layout));
        else if (chave || tipo) {
          addSection(role, [100]);
          var col = areaOf(role).sections.slice(-1)[0].columns[0];
          if (chave) insertVariavel(col.id, chave, true);
          else insertElement(col.id, tipo, true);
        } else if (e.dataTransfer.files && e.dataTransfer.files[0]) {
          addSection(role, [100]);
          var colArq = areaOf(role).sections.slice(-1)[0].columns[0];
          inserirArquivoNaColuna(colArq.id, e.dataTransfer.files[0]);
        }
      });
    });
  }

  function onCanvasClick(e) {
    if (e.target.closest && e.target.closest('#edoc-inline-bar')) return;
    e.stopPropagation();
    var act = e.target.closest('[data-act]');
    var node = e.target.closest('[data-id]');
    if (act && node) {
      persistirEdicaoSeHouver();
      runAct(act.getAttribute('data-act'), node.getAttribute('data-id'));
      return;
    }
    if (editando && editando.node && editando.node.contains(e.target) && e.target.closest('[contenteditable="true"]')) {
      return;
    }
    var elNode = e.target.closest('.edoc-el');
    var tipo = elNode ? elNode.getAttribute('data-type') : '';
    if (!state.preview && elNode && ehTextoEditavel(tipo) && !e.target.closest('.edoc-el-toolbar')) {
      var id = elNode.getAttribute('data-id');
      if (editando && editando.id === id) return;
      persistirEdicaoSeHouver();
      marcarSelecionado(elNode, id);
      iniciarEdicaoNaFolha(elNode);
      colocarCaretNoPonto(elNode, e.clientX, e.clientY);
      renderProps();
      return;
    }
    persistirEdicaoSeHouver();
    if (node) {
      state.selected = { id: node.getAttribute('data-id'), kind: node.getAttribute('data-kind') };
      render();
    } else {
      state.selected = null;
      render();
    }
  }

  function runAct(act, id) {
    var path = findPath(id);
    if (!path) return;
    if (act === 'del' && path.element) {
      path.column.elements.splice(path.ei, 1);
      state.selected = { id: path.column.id, kind: 'column' };
      pushHist(); render(); return;
    }
    if (act === 'dup' && path.element) {
      var copy = clone(path.element);
      copy.id = uid('e');
      path.column.elements.splice(path.ei + 1, 0, copy);
      state.selected = { id: copy.id, kind: 'element' };
      pushHist(); render(); return;
    }
    if (act === 'sec-del' && path.section && !path.element) {
      areaOf(path.role).sections.splice(path.si, 1);
      state.selected = null;
      pushHist(); render(); return;
    }
    if (act === 'sec-dup' && path.section && !path.element) {
      var sc = clone(path.section);
      sc.id = uid('s');
      (sc.columns || []).forEach(function (c) {
        c.id = uid('c');
        (c.elements || []).forEach(function (el) { el.id = uid('e'); });
      });
      areaOf(path.role).sections.splice(path.si + 1, 0, sc);
      pushHist(); render(); return;
    }
    if ((act === 'sec-up' || act === 'sec-down') && path.section && !path.element) {
      var arr = areaOf(path.role).sections;
      var j = act === 'sec-up' ? path.si - 1 : path.si + 1;
      if (j < 0 || j >= arr.length) return;
      var tmp = arr[path.si];
      arr[path.si] = arr[j];
      arr[j] = tmp;
      pushHist(); render();
    }
  }

  function bindGutter(g) {
    g.addEventListener('mousedown', function (e) {
      e.preventDefault();
      var secId = g.getAttribute('data-section');
      var gi = parseInt(g.getAttribute('data-gutter'), 10);
      var path = findPath(secId);
      if (!path || !path.section) return;
      var cols = path.section.columns;
      var a = cols[gi];
      var b = cols[gi + 1];
      if (!a || !b) return;
      var startX = e.clientX;
      var wa = a.width;
      var wb = b.width;
      var row = g.parentElement.getBoundingClientRect();
      g.classList.add('is-drag');
      function move(ev) {
        var dx = ((ev.clientX - startX) / row.width) * 100;
        var na = Math.round(Math.max(10, Math.min(90, wa + dx)));
        var nb = wa + wb - na;
        if (nb < 10) { nb = 10; na = wa + wb - 10; }
        a.width = na; b.width = nb;
        g.querySelector('.edoc-gutter-tip').textContent = na + '% | ' + nb + '%';
        a._el = null;
        $all('.edoc-col', path ? paper : document);
        var colEls = g.parentElement.querySelectorAll('.edoc-col');
        if (colEls[gi]) colEls[gi].style.flexBasis = na + '%';
        if (colEls[gi + 1]) colEls[gi + 1].style.flexBasis = nb + '%';
      }
      function up() {
        g.classList.remove('is-drag');
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', up);
        pushHist();
      }
      document.addEventListener('mousemove', move);
      document.addEventListener('mouseup', up);
    });
  }

  function defaultElement(tipo) {
    var el = { id: uid('e'), type: tipo, props: {}, style: {} };
    if (tipo === 'titulo') el.props = { text: 'Título do documento', tag: 'h1' };
    if (tipo === 'texto' || tipo === 'texto_rico') el.props = { text: 'Clique duas vezes para editar.' };
    if (tipo === 'logo') el.props = { width: 200, align: 'center', vAlign: 'middle' };
    if (tipo === 'imagem') el.props = { width: 180, align: 'center', vAlign: 'middle' };
    if (tipo === 'espacador') el.props = { height: 16 };
    if (tipo === 'assinaturas') el.props = { quantidade: 2 };
    if (tipo === 'tabela_notas') el.style = { fontSize: 8 };
    if (tipo === 'html') el.props = { html: '<p></p>' };
    if (tipo === 'tabela') {
      el.type = 'html';
      el.props = { html: htmlTabelaVazia(8, 8) };
      el.style = { fontSize: 8 };
    }
    return el;
  }

  function tokenDaChave(chave) {
    chave = String(chave || '').trim();
    if (chave === 'se_resp2') return '{{#se_resp2}}{{resp2_nome}}{{/se_resp2}}';
    if (chave === 'se_resp_fin') return '{{#se_resp_fin}}{{resp_fin_nome}}{{/se_resp_fin}}';
    return '{{' + chave + '}}';
  }

  function primeiraColuna() {
    var secs = areaOf('body').sections || [];
    if (!secs.length || !secs[0].columns || !secs[0].columns.length) return null;
    return secs[0].columns[0].id;
  }

  function colunaAlvo() {
    var sel = state.selected ? findPath(state.selected.id) : null;
    if (sel && sel.column) return sel.column.id;
    return garantirColunaCorpo();
  }

  function garantirColunaCorpo() {
    var id = primeiraColuna();
    if (id) return id;
    var sec = {
      id: uid('s'),
      type: 'section',
      role: 'body',
      columns: [{ id: uid('c'), width: 100, vAlign: 'top', elements: [] }]
    };
    areaOf('body').sections.push(sec);
    return sec.columns[0].id;
  }

  function inserirHtmlNaFolha(html) {
    if (!html) return;
    var colId = garantirColunaCorpo();
    var path = findPath(colId);
    if (!path || !path.column) return;
    var el = defaultElement('html');
    el.props.html = html;
    path.column.elements = path.column.elements || [];
    path.column.elements.push(el);
    state.selected = { id: el.id, kind: 'element' };
    pushHist();
    render();
    setStatus('Conteúdo colado na folha');
  }

  var celulaAlvoDrop = null;

  function alvoDoEvento(e) {
    var n = e && e.target;
    if (!n) return null;
    return n.nodeType === 1 ? n : n.parentElement;
  }

  function celulaDoEvento(e) {
    var alvo = alvoDoEvento(e);
    if (!alvo || !alvo.closest || !paper) return null;
    var celula = alvo.closest('td, th');
    if (!celula || !paper.contains(celula) || !celula.closest('.edoc-el')) return null;
    return celula;
  }

  function arrastandoVariavel(dt) {
    if (!dt || !dt.types) return false;
    var types = Array.prototype.slice.call(dt.types);
    return types.indexOf('text/edoc-var') >= 0 || types.indexOf('text/plain') >= 0;
  }

  function marcarCelulaDrop(celula) {
    if (celulaAlvoDrop === celula) return;
    if (celulaAlvoDrop) celulaAlvoDrop.classList.remove('edoc-celula-drop');
    celulaAlvoDrop = celula || null;
    if (celulaAlvoDrop) celulaAlvoDrop.classList.add('edoc-celula-drop');
  }

  function inserirVariavelNaCelula(celula, chave) {
    var elNode = celula.closest('.edoc-el');
    if (!elNode || !paper || !paper.contains(celula)) return false;
    var path = findPath(elNode.getAttribute('data-id'));
    if (!path || !path.element || !ehTextoEditavel(path.element.type)) return false;
    var tabelaViva = celula.closest('table');
    var body = corpoDoElemento(elNode);
    if (!tabelaViva || !body || !body.contains(tabelaViva)) return false;
    path.element.props = path.element.props || {};
    var anterior = path.element.props.html || path.element.props.text || '';
    var wrap = document.createElement('div');
    wrap.innerHTML = String(anterior).indexOf('<') >= 0 ? anterior : esc(anterior);
    var vivas = body.querySelectorAll('table');
    var fontes = wrap.querySelectorAll('table');
    var ti = Array.prototype.indexOf.call(vivas, tabelaViva);
    var tabelaFonte = fontes[ti];
    if (!tabelaFonte || !celula.parentElement) return false;
    var linha = tabelaFonte.rows[celula.parentElement.rowIndex];
    var destino = linha && linha.cells[celula.cellIndex];
    if (!destino) return false;
    var token = tokenDaChave(chave);
    var texto = (destino.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
    if (!texto || texto === '—') destino.textContent = token;
    else if (texto.indexOf(token) < 0) destino.appendChild(document.createTextNode(' ' + token));
    path.element.props.html = restaurarMedidasMm(anterior, wrap.innerHTML);
    delete path.element.props.text;
    if (editando && editando.id === path.element.id) {
      var bodyEd = corpoDoElemento(editando.node);
      if (bodyEd) {
        bodyEd.oninput = null;
        bodyEd.onkeydown = null;
        bodyEd.onpaste = null;
      }
      esconderBarraInline();
      editando = null;
    }
    state.selected = { id: path.element.id, kind: 'element' };
    pushHist();
    render();
    setStatus('Variável colocada na célula');
    return true;
  }

  function insertVariavel(colId, chave, skipHist) {
    var path = findPath(colId);
    if (!path || !path.column) return;
    var el = defaultElement('texto');
    el.props = { text: tokenDaChave(chave) };
    path.column.elements = path.column.elements || [];
    path.column.elements.push(el);
    state.selected = { id: el.id, kind: 'element' };
    if (!skipHist) pushHist();
    render();
  }

  function insertVariavelIntoSelection(chave) {
    var token = tokenDaChave(chave);
    var rte = document.querySelector('.edoc-rte');
    if (rte && state.selected) {
      if (typeof rte._edocRestore === 'function') rte._edocRestore();
      else rte.focus();
      document.execCommand('insertText', false, token);
      if (typeof rte._edocSync === 'function') rte._edocSync(true);
      return;
    }
    var sel = state.selected ? findPath(state.selected.id) : null;
    if (sel && sel.element && ['titulo', 'texto', 'texto_rico', 'html'].indexOf(sel.element.type) >= 0) {
      sel.element.props = sel.element.props || {};
      var cur = sel.element.props.html || sel.element.props.text || '';
      sel.element.props.html = cur + token;
      delete sel.element.props.text;
      pushHist();
      render();
      return;
    }
    var colId = (sel && sel.column) ? sel.column.id : primeiraColuna();
    if (!colId) {
      addSection('body', [100]);
      colId = areaOf('body').sections.slice(-1)[0].columns[0].id;
    }
    insertVariavel(colId, chave);
  }

  function insertElement(colId, tipo, skipHist) {
    var path = findPath(colId);
    if (!path || !path.column) return;
    var el = defaultElement(tipo);
    path.column.elements = path.column.elements || [];
    path.column.elements.push(el);
    state.selected = { id: el.id, kind: 'element' };
    if (!skipHist) pushHist();
    render();
    if (tipo === 'tabela') {
      var node = paper && paper.querySelector('.edoc-el[data-id="' + el.id + '"]');
      if (node) {
        marcarSelecionado(node, el.id);
        iniciarEdicaoNaFolha(node);
      }
    }
    if (tipo === 'imagem') {
      escolherArquivoImagem(function (file) {
        arquivoParaDataUri(file, function (uri, err) {
          if (err || !uri) { setStatus(err || 'Não foi possível usar a imagem.'); return; }
          var p = findPath(el.id);
          if (!p || !p.element) return;
          p.element.props = p.element.props || {};
          p.element.props.src = uri;
          pushHist();
          render();
          setStatus('Imagem adicionada');
        });
      });
    }
    return el;
  }

  function inserirArquivoNaColuna(colId, file) {
    arquivoParaDataUri(file, function (uri, err) {
      if (err || !uri) { setStatus(err || 'Falha ao carregar imagem.'); return; }
      var path = findPath(colId);
      if (!path || !path.column) return;
      var el = defaultElement('imagem');
      el.props.src = uri;
      path.column.elements = path.column.elements || [];
      path.column.elements.push(el);
      state.selected = { id: el.id, kind: 'element' };
      pushHist();
      render();
      setStatus('Imagem adicionada');
    });
  }

  function aplicarImagemColada(file) {
    var sel = state.selected ? findPath(state.selected.id) : null;
    if (sel && sel.element && sel.element.type === 'imagem') {
      arquivoParaDataUri(file, function (uri, err) {
        if (err || !uri) { setStatus(err || 'Não foi possível colar a imagem.'); return; }
        sel.element.props = sel.element.props || {};
        sel.element.props.src = uri;
        pushHist();
        render();
        setStatus('Imagem adicionada');
      });
      return;
    }
    var colId = (sel && sel.column) ? sel.column.id : primeiraColuna();
    if (!colId) colId = garantirColunaCorpo();
    inserirArquivoNaColuna(colId, file);
  }

  function addSection(role, widths) {
    var sec = {
      id: uid('s'),
      type: 'section',
      role: role,
      columns: (widths || [100]).map(function (w) {
        return { id: uid('c'), width: w, vAlign: 'top', elements: [] };
      })
    };
    areaOf(role).sections.push(sec);
    state.selected = { id: sec.id, kind: 'section' };
    pushHist();
    render();
  }

  function insertLayoutAt(colId, layoutJson) {
    var widths = JSON.parse(layoutJson);
    var path = findPath(colId);
    var role = path ? path.role : 'body';
    addSection(role, widths);
  }

  function renderTree() {
    var box = $('#edoc-tree');
    var pane = $('#pane-estrutura');
    if (!box || !pane || pane.style.display === 'none') return;
    function node(label, id, kind, extra) {
      var act = state.selected && state.selected.id === id ? ' active' : '';
      return '<div class="edoc-tree-item' + act + '" data-id="' + id + '" data-kind="' + kind + '">' + extra + label + '</div>';
    }
    var html = '';
    [['header', 'Cabeçalho'], ['body', 'Corpo'], ['footer', 'Rodapé']].forEach(function (pair) {
      html += '<div style="font-size:10px;font-weight:700;color:#9ca3af;margin:8px 0 4px">' + pair[1] + '</div>';
      (areaOf(pair[0]).sections || []).forEach(function (s, si) {
        html += node('Seção ' + (si + 1), s.id, 'section', '<i class="fa-regular fa-square"></i>');
        html += '<div class="edoc-tree-nested">';
        (s.columns || []).forEach(function (c, ci) {
          html += node('Coluna ' + (c.width || 0) + '%', c.id, 'column', '<i class="fa-solid fa-columns"></i>');
          html += '<div class="edoc-tree-nested">';
          (c.elements || []).forEach(function (el) {
            html += node(labelTipo(el.type), el.id, 'element', '<i class="fa-regular fa-file"></i>');
          });
          html += '</div>';
        });
        html += '</div>';
      });
    });
    box.innerHTML = html || '<p class="edoc-empty">Vazio</p>';
    $all('.edoc-tree-item', box).forEach(function (it) {
      it.addEventListener('click', function () {
        state.selected = { id: it.getAttribute('data-id'), kind: it.getAttribute('data-kind') };
        render();
      });
    });
  }

  function inp(name, val, extra) {
    extra = extra || '';
    return '<input ' + extra + ' data-f="' + name + '" value="' + String(val == null ? '' : val).replace(/"/g, '&quot;') + '">';
  }

  function renderProps() {
    var box = $('#edoc-props');
    if (!box) return;
    var sel = state.selected ? findPath(state.selected.id) : null;
    if (!sel) {
      box.innerHTML = '<p class="edoc-hint">Selecione um elemento, coluna ou seção na folha.</p>'
        + propsPage();
      bindProps(box, 'page');
      return;
    }
    if (sel.element) {
      box.innerHTML = propsElement(sel.element);
      bindProps(box, 'element', sel.element);
      return;
    }
    if (sel.column) {
      box.innerHTML = '<label>Largura (%)</label>' + inp('width', sel.column.width, 'type="number" min="10" max="90"')
        + '<label>Alinhamento vertical</label><select data-f="vAlign"><option value="top">Topo</option><option value="middle">Meio</option><option value="bottom">Base</option></select>';
      box.querySelector('[data-f="vAlign"]').value = sel.column.vAlign || 'top';
      bindProps(box, 'column', sel.column);
      return;
    }
    box.innerHTML = '<p class="edoc-empty">Seção — use a barra para mover ou excluir.</p>'
      + '<label class="edoc-chk"><input type="checkbox" data-f="pageBreakBefore"' + (sel.section.pageBreakBefore ? ' checked' : '') + '> Iniciar em nova página</label>'
      + '<label class="edoc-chk"><input type="checkbox" data-f="avoidBreak"' + (sel.section.avoidBreak ? ' checked' : '') + '> Evitar quebra interna</label>';
    bindProps(box, 'section', sel.section);
  }

  function propsPage() {
    var p = state.estrutura.page || {};
    var m = p.margin || {};
    return '<div class="edoc-sec-label">PÁGINA</div>'
      + '<label>Papel</label><select data-f="size"><option>A4</option><option>A5</option></select>'
      + '<label>Orientação</label><select data-f="orientation"><option value="portrait">Retrato</option><option value="landscape">Paisagem</option></select>'
      + '<div class="edoc-sec-label">MARGEM (mm)</div><div class="edoc-box4">'
      + '<div><label>Topo</label>' + inp('mt', m.top != null ? m.top : 15, 'type="number" min="0" max="40" step="0.1"') + '</div>'
      + '<div><label>Direita</label>' + inp('mr', m.right != null ? m.right : 15, 'type="number" min="0" max="40" step="0.1"') + '</div>'
      + '<div><label>Baixo</label>' + inp('mb', m.bottom != null ? m.bottom : 15, 'type="number" min="0" max="40" step="0.1"') + '</div>'
      + '<div><label>Esquerda</label>' + inp('ml', m.left != null ? m.left : 15, 'type="number" min="0" max="40" step="0.1"') + '</div></div>'
      + '<div class="edoc-sec-label">IMAGEM DE REFERÊNCIA</div>'
      + '<p class="edoc-hint">Fundo da folha para copiar o layout da escola. A impressão só inclui a imagem se você marcar abaixo.</p>'
      + '<input type="file" id="edoc-fundo" accept="image/png,image/jpeg,image/webp,image/gif">'
      + (p.fundo ? '<button type="button" class="edoc-btn" id="edoc-fundo-limpar" style="margin-top:6px">Remover imagem</button>' : '')
      + '<label class="edoc-chk"><input type="checkbox" id="edoc-imprimir-fundo"' + (p.imprimirFundo ? ' checked' : '') + '> Imprimir a imagem no PDF</label>';
  }

  function posBtns(field, current, items) {
    return items.map(function (it) {
      return '<button type="button" class="edoc-btn edoc-btn-icon' + (current === it[0] ? ' active' : '')
        + '" data-pos-field="' + field + '" data-pos-val="' + it[0] + '" title="' + it[2] + '">'
        + '<i class="fa-solid ' + it[1] + '"></i></button>';
    }).join('');
  }

  function painelMedidasFolha(html) {
    if (!html || html.indexOf('seed-folha') < 0) return '';
    var med = medidasMm(html);
    if (!med.cols.length && !med.rows.length) return '';
    var out = '<div class="edoc-sec-label">GRADE (mm)</div>'
      + '<p class="edoc-hint">Passo de 0,1 mm. A impressão usa estes números, sem reduzir para caber.</p>'
      + '<div class="edoc-grade-mm">';
    med.cols.forEach(function (mm, i) {
      out += '<label>Coluna ' + (i + 1) + '</label><input type="number" min="1" max="80" step="0.1" data-mm-col="' + i + '" value="' + mm + '">';
    });
    med.rows.forEach(function (mm, i) {
      out += '<label>Linha ' + (i + 1) + '</label><input type="number" min="0.2" max="40" step="0.1" data-mm-row="' + i + '" value="' + mm + '">';
    });
    return out + '</div>';
  }

  function aplicarMedidaFolha(el, input) {
    persistirEdicaoSeHouver();
    el.props = el.props || {};
    var html = el.props.html || '';
    var med = medidasMm(html);
    var n = parseFloat(String(input.value).replace(',', '.'));
    if (!isFinite(n)) return;
    n = Math.round(n * 10) / 10;
    var col = input.getAttribute('data-mm-col');
    var row = input.getAttribute('data-mm-row');
    if (col !== null && col !== '') med.cols[parseInt(col, 10)] = Math.max(1, Math.min(80, n)).toFixed(1);
    if (row !== null && row !== '') med.rows[parseInt(row, 10)] = Math.max(0.2, Math.min(40, n)).toFixed(1);
    var i = 0;
    html = html.replace(/<col\b([^>]*?)style="([^"]*)"/gi, function (full, pre, style) {
      var mm = med.cols[i++];
      if (!mm) return full;
      style = /width:\s*[0-9.]+mm/i.test(style)
        ? style.replace(/width:\s*[0-9.]+mm/i, 'width:' + mm + 'mm')
        : style + ';width:' + mm + 'mm';
      return '<col' + pre + 'style="' + style + '"';
    });
    var soma = 0;
    med.cols.forEach(function (c) { soma += parseFloat(c) || 0; });
    html = html.replace(/(<table\b[^>]*\bseed-folha\b[^>]*style=")([^"]*)(")/i, function (full, a, style, c) {
      var w = soma.toFixed(1);
      style = /width:\s*[0-9.]+mm/i.test(style)
        ? style.replace(/width:\s*[0-9.]+mm/i, 'width:' + w + 'mm')
        : style + ';width:' + w + 'mm';
      return a + style + c;
    });
    i = 0;
    html = html.replace(/<tr\b([^>]*?)style="([^"]*)"/gi, function (full, pre, style) {
      var mm = med.rows[i++];
      if (!mm) return full;
      style = /height:\s*[0-9.]+mm/i.test(style)
        ? style.replace(/height:\s*[0-9.]+mm/i, 'height:' + mm + 'mm')
        : style + ';height:' + mm + 'mm';
      return '<tr' + pre + 'style="' + style + '"';
    });
    el.props.html = html;
    pushHist();
    render();
  }

  function propsElement(el) {
    var p = el.props || {};
    var st = el.style || {};
    var html = '<div class="edoc-sec-label">' + labelTipo(el.type).toUpperCase() + '</div>';
    if (el.type === 'titulo' || el.type === 'texto' || el.type === 'texto_rico' || el.type === 'html') {
      html += '<label>Conteúdo</label>';
      html += '<div class="edoc-rte-wrap">'
        + '<div class="edoc-rte-bar">' + htmlBarraFmt(true) + '</div>'
        + '<div class="edoc-rte" contenteditable="true" role="textbox" aria-multiline="true" spellcheck="true"></div>'
        + '</div>';
      html += '<button type="button" class="edoc-btn" id="edoc-insert-var" style="margin-top:6px">{ } Variáveis</button>';
      html += '<p class="edoc-hint" style="margin-top:8px">Clique no texto da folha para digitar. Use a barra para centralizar e A+ / A− para o tamanho da fonte.</p>';
      html += painelMedidasFolha(p.html || '');
    }
    if (el.type === 'tabela_notas') {
      html += '<p class="edoc-hint">Quadro com 1º ao 4º bimestre e final (nota e falta). O tamanho do texto abaixo vale para a tabela inteira — diminua para caber em uma folha.</p>';
    }
    if (el.type === 'titulo') {
      html += '<label>Nível</label><select data-f="tag"><option value="h1">Título</option><option value="h2">Subtítulo</option><option value="h3">Seção</option></select>';
    }
    if (el.type === 'imagem') {
      html += '<label>Arquivo</label><input type="file" id="edoc-img-file" accept="image/png,image/jpeg,image/gif,image/webp">';
      html += '<p class="edoc-hint">Cole com Ctrl+V, escolha um arquivo ou arraste a imagem para a folha.</p>';
    }
    if (el.type === 'logo' || el.type === 'imagem') {
      html += '<label>Largura (px)</label>' + inp('width', p.width || 120, 'type="number" min="24" max="400"');
      html += '<div class="edoc-sec-label">POSIÇÃO NO BLOCO</div>';
      html += '<label>Horizontal</label><div class="edoc-align">'
        + posBtns('align', p.align || 'center', [
          ['left', 'fa-align-left', 'Esquerda'],
          ['center', 'fa-align-center', 'Centro'],
          ['right', 'fa-align-right', 'Direita']
        ]) + '</div>';
      html += '<label>Vertical</label><div class="edoc-align">'
        + posBtns('vAlign', p.vAlign || 'middle', [
          ['top', 'fa-arrow-up', 'Topo'],
          ['middle', 'fa-grip-lines', 'Meio'],
          ['bottom', 'fa-arrow-down', 'Base']
        ]) + '</div>';
    } else {
      html += '<div class="edoc-sec-label">ALINHAMENTO</div><div class="edoc-align">'
        + ['left', 'center', 'right', 'justify'].map(function (a) {
          return '<button type="button" class="edoc-btn edoc-btn-icon' + ((st.textAlign || p.align) === a ? ' active' : '') + '" data-align="' + a + '" title="' + a + '"><i class="fa-solid fa-align-' + (a === 'justify' ? 'justify' : a) + '"></i></button>';
        }).join('') + '</div>';
    }
    if (el.type !== 'logo' && el.type !== 'imagem') {
      html += '<div class="edoc-sec-label">TIPOGRAFIA</div><div class="edoc-prop-row">'
        + '<div><label>Tamanho (pt)</label>' + inp('fontSize', st.fontSize || '', 'type="number" min="8" max="72"') + '</div>'
        + '<div><label>Peso do bloco</label><select data-f="fontWeight"><option value="">Normal</option><option value="bold">Negrito</option></select></div></div>'
        + '<label>Cor</label>' + inp('color', st.color || '#111111', 'type="color"');
    }
    html += '<div class="edoc-sec-label">MARGEM</div><div class="edoc-box4">'
      + box4('m', st.margin) + '</div>';
    html += '<div class="edoc-sec-label">PREENCHIMENTO</div><div class="edoc-box4">'
      + box4('p', st.padding) + '</div>';
    html += '<div class="edoc-sec-label">BORDA</div>'
      + '<select data-f="borderStyle"><option value="none">Nenhuma</option><option value="solid">Sólida</option><option value="dashed">Tracejada</option><option value="dotted">Pontilhada</option></select>'
      + '<div class="edoc-prop-row"><div><label>Espessura</label>' + inp('borderWidth', st.borderWidth || 1, 'type="number"') + '</div>'
      + '<div><label>Cor</label>' + inp('borderColor', st.borderColor || '#e5e7eb', 'type="color"') + '</div></div>';
    html += '<div class="edoc-sec-label">AVANÇADO</div>'
      + '<label class="edoc-chk"><input type="checkbox" data-f="hideIfEmpty"' + (el.hideIfEmpty ? ' checked' : '') + '> Ocultar quando vazio</label>';
    return html;
  }

  function box4(pref, v) {
    v = v || {};
    return ['top', 'right', 'bottom', 'left'].map(function (k) {
      var lab = { top: 'Topo', right: 'Dir.', bottom: 'Baixo', left: 'Esq.' }[k];
      return '<div><label>' + lab + '</label>' + inp(pref + '_' + k, v[k] || 0, 'type="number"') + '</div>';
    }).join('');
  }

  function bindProps(box, kind, target) {
    if (kind === 'page') {
      var p = state.estrutura.page;
      var size = box.querySelector('[data-f="size"]');
      var ori = box.querySelector('[data-f="orientation"]');
      if (size) { size.value = p.size || 'A4'; size.onchange = function () { p.size = size.value; pushHist(); render(); }; }
      if (ori) { ori.value = p.orientation || 'portrait'; ori.onchange = function () { p.orientation = ori.value; pushHist(); render(); }; }
      $all('[data-f]', box).forEach(function (f) {
        if (f.getAttribute('data-f').indexOf('m') === 0 && f.getAttribute('data-f').length === 2) {
          f.onchange = function () {
            p.margin = p.margin || {};
            var map = { mt: 'top', mr: 'right', mb: 'bottom', ml: 'left' };
            var n = parseFloat(String(f.value).replace(',', '.'));
            p.margin[map[f.getAttribute('data-f')]] = isFinite(n) ? Math.round(n * 10) / 10 : 0;
            pushHist(); render();
          };
        }
      });
      var fundo = box.querySelector('#edoc-fundo');
      if (fundo) {
        fundo.addEventListener('change', function () {
          if (!fundo.files || !fundo.files[0]) return;
          arquivoParaDataUri(fundo.files[0], function (uri, err) {
            fundo.value = '';
            if (err || !uri) { setStatus(err || 'Falha ao carregar imagem.'); return; }
            p.fundo = uri;
            pushHist(); render();
            setStatus('Imagem de referência na folha');
          });
        });
      }
      var limpar = box.querySelector('#edoc-fundo-limpar');
      if (limpar) {
        limpar.addEventListener('click', function () {
          delete p.fundo;
          p.imprimirFundo = false;
          pushHist(); render();
        });
      }
      var imp = box.querySelector('#edoc-imprimir-fundo');
      if (imp) {
        imp.addEventListener('change', function () {
          p.imprimirFundo = !!imp.checked;
          pushHist(); render();
        });
      }
      return;
    }
    $all('[data-f]', box).forEach(function (f) {
      f.addEventListener('change', function () { applyField(kind, target, f); });
      if (f.tagName === 'TEXTAREA' || f.type === 'text' || f.type === 'number') {
        f.addEventListener('input', function () { applyField(kind, target, f, true); });
      }
    });
    $all('[data-align]', box).forEach(function (b) {
      b.addEventListener('click', function () {
        target.style = target.style || {};
        target.props = target.props || {};
        target.style.textAlign = b.getAttribute('data-align');
        target.props.align = b.getAttribute('data-align');
        pushHist(); render();
      });
    });
    $all('[data-pos-field]', box).forEach(function (b) {
      b.addEventListener('click', function () {
        target.props = target.props || {};
        target.props[b.getAttribute('data-pos-field')] = b.getAttribute('data-pos-val');
        pushHist(); render();
      });
    });
    var varBtn = $('#edoc-insert-var', box);
    if (varBtn) {
      varBtn.addEventListener('mousedown', function (e) { e.preventDefault(); });
      varBtn.addEventListener('click', function () { openVars(target, box.querySelector('.edoc-rte')); });
    }
    if (kind === 'element' && target.type === 'titulo') {
      var tag = box.querySelector('[data-f="tag"]');
      if (tag) tag.value = (target.props || {}).tag || 'h1';
    }
    if (kind === 'element') {
      var bs = box.querySelector('[data-f="borderStyle"]');
      if (bs) bs.value = (target.style || {}).borderStyle || 'none';
      var fw = box.querySelector('[data-f="fontWeight"]');
      if (fw) fw.value = (target.style || {}).fontWeight || '';
    }
    if (kind === 'element') bindRte(box, target);
    if (kind === 'element') {
      $all('[data-mm-col], [data-mm-row]', box).forEach(function (f) {
        f.addEventListener('change', function () { aplicarMedidaFolha(target, f); });
      });
    }
    if (kind === 'element' && target.type === 'imagem') {
      var imgFile = $('#edoc-img-file', box);
      if (imgFile) {
        imgFile.addEventListener('change', function () {
          if (!imgFile.files || !imgFile.files[0]) return;
          arquivoParaDataUri(imgFile.files[0], function (uri, err) {
            imgFile.value = '';
            if (err || !uri) { setStatus(err || 'Falha ao carregar imagem.'); return; }
            target.props = target.props || {};
            target.props.src = uri;
            pushHist();
            render();
            setStatus('Imagem adicionada');
          });
        });
      }
    }
  }

  function atualizarBotoesRte(box) {
    $all('[data-fmt]', box).forEach(function (b) {
      var cmd = b.getAttribute('data-fmt');
      if (cmd === 'fontInc' || cmd === 'fontDec' || cmd === 'justifyLeft' || cmd === 'justifyCenter'
        || cmd === 'justifyRight' || cmd === 'justifyFull' || cmd === 'tableDelCol' || cmd === 'tableDelRow'
        || cmd === 'tableInsRow' || cmd === 'tableInsCol' || cmd === 'tableMerge' || cmd === 'tableInsert'
        || cmd === 'tableSelCol' || cmd === 'insertImg') return;
      var on = false;
      try { on = document.queryCommandState(cmd); } catch (err) { on = false; }
      b.classList.toggle('active', !!on);
    });
  }

  function bindRte(box, target) {
    var rte = box.querySelector('.edoc-rte');
    if (!rte) return;
    rte.innerHTML = htmlParaEditor(target);
    atualizarLabelFonte(tamanhoFontePt(target, rte));
    var savedRange = null;
    function saveRange() {
      var sel = window.getSelection();
      if (sel && sel.rangeCount && rte.contains(sel.anchorNode)) {
        savedRange = sel.getRangeAt(0).cloneRange();
      }
    }
    function restoreRange() {
      rte.focus();
      if (!savedRange) return;
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(savedRange);
    }
    function sync(silent) {
      target.props = target.props || {};
      target.props.html = htmlDoEditor(rte);
      delete target.props.text;
      if (!silent) { pushHist(); render(); return; }
      state.dirty = true;
      scheduleSave();
      var node = paper && paper.querySelector('[data-id="' + target.id + '"]');
      var inner = corpoDoElemento(node);
      if (inner) inner.innerHTML = htmlTextoInterno(target);
    }
    rte._edocRestore = restoreRange;
    rte._edocSync = sync;
    rte.addEventListener('keyup', saveRange);
    rte.addEventListener('mouseup', saveRange);
    rte.addEventListener('input', function () { saveRange(); sync(true); });
    rte.addEventListener('paste', function (e) {
      var file = arquivoDoClipboard(e.clipboardData);
      if (file) {
        e.preventDefault();
        inserirArquivoNoRte(file);
        return;
      }
      var html = (e.clipboardData && e.clipboardData.getData('text/html')) || '';
      if (!html) return;
      e.preventDefault();
      var clean = sanitizeHtml(html);
      if (!String(clean).replace(/<br\s*\/?>|&nbsp;|\s/gi, '')) {
        setStatus('O conteúdo colado estava vazio ou a imagem não é suportada.');
        return;
      }
      document.execCommand('insertHTML', false, clean);
      saveRange();
      sync(true);
    });
    function inserirArquivoNoRte(file) {
      arquivoParaDataUri(file, function (uri, err) {
        if (err || !uri) {
          setStatus(err || 'Não foi possível colar a imagem.');
          return;
        }
        rte.focus();
        if (selecaoCobreTudo(rte)) {
          var sel = window.getSelection();
          sel.removeAllRanges();
          var fim = document.createRange();
          fim.selectNodeContents(rte);
          fim.collapse(false);
          sel.addRange(fim);
        }
        document.execCommand('insertHTML', false, '<br>' + htmlImgData(uri) + '<br>');
        saveRange();
        sync(true);
        setStatus('Imagem inserida no texto');
      });
    }
    rte.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        if (celulaDaSelecao(rte)) return;
        e.preventDefault();
        inserirQuebraLinha();
        saveRange();
        sync(true);
        return;
      }
      if ((e.metaKey || e.ctrlKey) && (e.key === 'b' || e.key === 'B' || e.key === 'i' || e.key === 'I' || e.key === 'u' || e.key === 'U')) {
        e.preventDefault();
        var cmd = (e.key === 'b' || e.key === 'B') ? 'bold' : ((e.key === 'i' || e.key === 'I') ? 'italic' : 'underline');
        document.execCommand(cmd);
        saveRange();
        sync(true);
        atualizarBotoesRte(box);
      }
    });
    $all('[data-fmt]', box).forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function () {
        restoreRange();
        executarFmt(b.getAttribute('data-fmt'), target, rte);
        saveRange();
        sync(true);
        atualizarBotoesRte(box);
      });
    });
    function onSelChange() {
      if (!rte.isConnected) {
        document.removeEventListener('selectionchange', onSelChange);
        return;
      }
      if (rte.contains(document.activeElement) || (window.getSelection() && rte.contains(window.getSelection().anchorNode))) {
        saveRange();
        atualizarBotoesRte(box);
      }
    }
    document.addEventListener('selectionchange', onSelChange);
  }

  function applyField(kind, target, f, silent) {
    var name = f.getAttribute('data-f');
    var val = f.type === 'checkbox' ? f.checked : f.value;
    if (kind === 'column' && name === 'width') {
      target.width = Math.max(10, Math.min(90, parseInt(val, 10) || 10));
    } else if (kind === 'column' && name === 'vAlign') {
      target.vAlign = val;
    } else if (kind === 'section') {
      target[name] = val;
    } else if (kind === 'element') {
      target.props = target.props || {};
      target.style = target.style || {};
      if (name === 'text' || name === 'html') {
        if (target.type === 'html' || (String(val).indexOf('<') >= 0)) {
          target.props.html = val;
          delete target.props.text;
        } else {
          target.props.text = val;
          delete target.props.html;
        }
      } else if (name === 'width' || name === 'height' || name === 'tag') {
        target.props[name] = name === 'tag' ? val : (parseInt(val, 10) || 0);
      } else if (name.indexOf('m_') === 0 || name.indexOf('p_') === 0) {
        var key = name.charAt(0) === 'm' ? 'margin' : 'padding';
        var side = name.split('_')[1];
        target.style[key] = target.style[key] || {};
        target.style[key][side] = parseInt(val, 10) || 0;
      } else if (['fontSize', 'borderWidth', 'borderRadius'].indexOf(name) >= 0) {
        target.style[name] = parseInt(val, 10) || 0;
      } else if (['fontWeight', 'color', 'borderStyle', 'borderColor'].indexOf(name) >= 0) {
        target.style[name] = val;
      } else if (name === 'hideIfEmpty') {
        target.hideIfEmpty = !!val;
      } else {
        target.props[name] = val;
      }
    }
    if (!silent) { pushHist(); render(); }
    else {
      state.dirty = true;
      scheduleSave();
      var node = paper && paper.querySelector('[data-id="' + target.id + '"]');
      if (node && (name === 'text' || name === 'html')) {
        var inner = corpoDoElemento(node);
        if (inner) inner.innerHTML = htmlTextoInterno(target);
      }
    }
  }

  function openVars(target, rte) {
    var modal = $('#edoc-vars');
    modal.classList.add('open');
    modal.onclick = function (e) { if (e.target === modal) modal.classList.remove('open'); };
    $all('[data-var]', modal).forEach(function (b) {
      b.onclick = function () {
        var token = tokenDaChave(b.getAttribute('data-var'));
        modal.classList.remove('open');
        if (rte) {
          if (typeof rte._edocRestore === 'function') rte._edocRestore();
          else rte.focus();
          document.execCommand('insertText', false, token);
          if (typeof rte._edocSync === 'function') rte._edocSync(true);
          return;
        }
        target.props = target.props || {};
        var cur = target.props.html || target.props.text || '';
        target.props.html = cur + token;
        delete target.props.text;
        pushHist(); render();
      };
    });
  }

  function aplicarLayoutSugerido() {
    if (!C.layoutSugerido) return;
    if (!window.confirm('Montar o layout organizado do boletim? O conteúdo atual do cabeçalho, do corpo e do rodapé será substituído. O tamanho da folha permanece o mesmo.')) {
      return;
    }
    var page = clone(state.estrutura.page || {});
    state.estrutura = normalizarEstrutura(clone(C.layoutSugerido));
    state.estrutura.page = page;
    state.selected = null;
    pushHist();
    render();
    scheduleSave();
    setStatus('Layout do boletim aplicado — confira e salve');
  }

  function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, 1600);
  }

  function payload() {
    var pg = state.estrutura.page || {};
    return {
      csrf_token: C.csrf,
      nome: ($('#edoc-nome') || {}).value || C.modelo.nome,
      codigo: ($('#edoc-codigo-visivel') || $('#edoc-codigo') || {}).value || C.modelo.codigo,
      descricao: C.modelo.descricao || '',
      ativo: 1,
      orientacao: (pg.orientation === 'landscape') ? 'paisagem' : 'retrato',
      formato_papel: String(pg.size || 'A4').toLowerCase() === 'a5' ? 'a5' : 'a4',
      margem_mm: (pg.margin && pg.margin.top) || 15,
      usar_layout_padrao: $('#edoc-layout-padrao') && $('#edoc-layout-padrao').checked ? 1 : 0,
      estrutura: state.estrutura
    };
  }

  function save() {
    if (state.saving && saveP) {
      saveAgain = true;
      return saveP;
    }
    state.saving = true;
    saveAgain = false;
    setStatus('Salvando...');
    saveP = fetch(C.saveUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': C.csrf },
      body: JSON.stringify(payload())
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j || !j.ok) {
        setStatus(j && j.error ? j.error : 'Falha ao salvar');
        return j;
      }
      state.dirty = false;
      setStatus('Salvo automaticamente');
      if (j.id && !C.modelo.id) {
        C.modelo.id = j.id;
        C.saveUrl = C.urlBase + '/admin/modelos-documentos/' + j.id + '/estrutura';
        C.previewUrl = C.urlBase + '/admin/modelos-documentos/' + j.id + '/preview';
        history.replaceState({}, '', C.urlBase + '/admin/modelos-documentos/' + j.id + '/editor');
      }
      if (j.id) {
        C.previewUrl = C.urlBase + '/admin/modelos-documentos/' + j.id + '/preview';
      }
      return j;
    }).catch(function () {
      setStatus('Falha ao salvar');
      return { ok: false };
    }).then(function (j) {
      state.saving = false;
      saveP = null;
      if (saveAgain) {
        return save();
      }
      return j;
    });
    return saveP;
  }

  function chaveGrade() {
    var cod = String((C.modelo && C.modelo.codigo) || '');
    if (/1127\s*-?\s*a/i.test(cod) || /1127a/i.test(cod)) return '1127a';
    if (/1127\s*-?\s*b/i.test(cod) || /1127b/i.test(cod)) return '1127b';
    if (/1128/i.test(cod)) return '1128';
    return '1127';
  }

  function inserirBlocoHtml(html) {
    var colId = colunaAlvo();
    if (!colId) {
      addSection('body', [100]);
      var secs = areaOf('body').sections || [];
      colId = secs.length && secs[secs.length - 1].columns ? secs[secs.length - 1].columns[0].id : null;
    }
    if (!colId) return;
    var el = defaultElement('html');
    el.props.html = html;
    var path = findPath(colId);
    if (!path || !path.column) return;
    path.column.elements = path.column.elements || [];
    path.column.elements.push(el);
    state.selected = { id: el.id, kind: 'element' };
    pushHist();
    render();
  }

  function bindDemonstracao() {
    var box = $('#edoc-demo');
    var turma = $('#edoc-demo-turma');
    var aluno = $('#edoc-demo-aluno');
    var real = $('#edoc-demo-real');
    var status = $('#edoc-demo-status');
    var comps = $('#edoc-demo-comps');
    if (!box || !turma || !aluno || !C.demoUrl) return;
    var req = 0;
    var blocos = {
      serie: '<p>Série: {{serie}}<br>Turma: {{turma_nome}}<br>Curso: {{curso_nome}}<br>Ano letivo: {{ano_letivo}}</p>',
      componentes: '{{componentes_serie_html}}',
      notas: '{{quadro_notas_html}}'
    };
    function pintarTurmas(lista) {
      var atual = turma.value;
      var html = '<option value="">Selecione a turma</option>';
      (lista || []).forEach(function (t) {
        html += '<option value="' + parseInt(t.id, 10) + '">' + esc(t.rotulo || t.nome || '') + '</option>';
      });
      turma.innerHTML = html;
      if (atual) turma.value = atual;
    }
    function pintarAlunos(lista) {
      var atual = aluno.value;
      var html = '<option value="">Selecione o aluno</option>';
      (lista || []).forEach(function (a) {
        html += '<option value="' + parseInt(a.id, 10) + '">' + esc(a.nome || '') + '</option>';
      });
      aluno.innerHTML = html;
      if (atual) aluno.value = atual;
    }
    function pintarComponentes(lista) {
      var drop = $('#edoc-demo-comps-drop');
      var contagem = $('#edoc-demo-comps-count');
      if (!comps) return;
      if (!lista || !lista.length) {
        comps.innerHTML = '';
        if (drop) drop.hidden = true;
        return;
      }
      var html = '';
      lista.forEach(function (c) {
        var nome = esc(c.nome || '');
        html += '<button type="button" class="edoc-var-chip" data-drag-var="' + esc(c.token || '') + '" title="Insere a nota de ' + nome + '">'
          + '<span class="edoc-var-nome">' + nome + '</span>'
          + '<span class="edoc-var-token">{{' + esc(c.token || '') + '}}</span></button>';
      });
      comps.innerHTML = html;
      $all('[data-drag-var]', comps).forEach(ligarArrasteVariavel);
      if (contagem) contagem.textContent = String(lista.length);
      if (drop) drop.hidden = false;
    }
    function aplicarDemo(data) {
      if (!data || data.ok === false) {
        if (status) status.textContent = (data && data.error) || 'Não foi possível carregar a demonstração.';
        return;
      }
      if (!turma.dataset.pronto) {
        pintarTurmas(data.turmas || []);
        turma.dataset.pronto = '1';
        if (!turma.value && data.turmas && data.turmas.length) {
          turma.value = String(data.turmas[0].id);
          carregar();
          return;
        }
      }
      pintarAlunos(data.alunos || []);
      if (turma.value && !aluno.value && data.alunos && data.alunos.length && !data.vars) {
        aluno.value = String(data.alunos[0].id);
        carregar();
        return;
      }
      pintarComponentes(data.componentes || []);
      if (data.vars) {
        persistirEdicaoSeHouver();
        C.varsPreview = data.vars;
        state.demo = !!(real && real.checked);
        if (status) status.textContent = data.resumo || 'Dados reais na folha.';
        render();
        return;
      }
      if (status) {
        if (data.resumo) status.textContent = data.resumo;
        else if (turma.value && (!data.alunos || !data.alunos.length)) status.textContent = 'Esta turma não tem alunos matriculados.';
        else if (turma.value) status.textContent = 'Escolha o aluno para ver a folha com os dados reais.';
        else status.textContent = 'Nenhuma turma ativa encontrada.';
      }
    }
    function carregar() {
      var n = ++req;
      var url = C.demoUrl
        + '?turma_id=' + encodeURIComponent(turma.value || '0')
        + '&aluno_id=' + encodeURIComponent(aluno.value || '0')
        + '&chave=' + encodeURIComponent(chaveGrade());
      fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (n !== req) return;
          aplicarDemo(data);
        })
        .catch(function () {
          if (n !== req) return;
          if (status) status.textContent = 'Não foi possível carregar a demonstração.';
        });
    }
    turma.addEventListener('change', function () {
      aluno.value = '';
      pintarAlunos([]);
      pintarComponentes([]);
      carregar();
    });
    aluno.addEventListener('change', carregar);
    if (real) {
      real.addEventListener('change', function () {
        state.demo = !!real.checked;
        render();
      });
    }
    box.addEventListener('click', function (e) {
      var atalho = e.target.closest('[data-atalho]');
      if (atalho && blocos[atalho.getAttribute('data-atalho')]) {
        inserirBlocoHtml(blocos[atalho.getAttribute('data-atalho')]);
        return;
      }
    });
    carregar();
  }

  function bindPalette() {
    $all('[data-drag-type]').forEach(function (el) {
      el.setAttribute('draggable', 'true');
      var dragging = false;
      el.addEventListener('dragstart', function (e) {
        dragging = true;
        e.dataTransfer.setData('text/edoc-type', el.getAttribute('data-drag-type'));
      });
      el.addEventListener('dragend', function () {
        setTimeout(function () { dragging = false; }, 0);
      });
      el.addEventListener('click', function () {
        if (dragging) return;
        insertElement(colunaAlvo(), el.getAttribute('data-drag-type'));
      });
    });
    $all('[data-drag-layout]').forEach(function (el) {
      el.setAttribute('draggable', 'true');
      el.addEventListener('dragstart', function (e) {
        e.dataTransfer.setData('text/edoc-layout', el.getAttribute('data-drag-layout'));
      });
      el.addEventListener('click', function () {
        addSection('body', JSON.parse(el.getAttribute('data-drag-layout')));
      });
    });
    $all('[data-drag-var]').forEach(ligarArrasteVariavel);
  }

  function ligarArrasteVariavel(el) {
    if (!el || el._edocDrag) return;
    el._edocDrag = true;
    el.setAttribute('draggable', 'true');
    var dragging = false;
    el.addEventListener('dragstart', function (e) {
      dragging = true;
      var chave = el.getAttribute('data-drag-var');
      e.dataTransfer.effectAllowed = 'copy';
      e.dataTransfer.setData('text/edoc-var', chave);
      e.dataTransfer.setData('text/plain', 'edoc-var:' + chave);
    });
    el.addEventListener('dragend', function () {
      marcarCelulaDrop(null);
      setTimeout(function () { dragging = false; }, 0);
    });
    el.addEventListener('click', function () {
      if (dragging) return;
      insertVariavelIntoSelection(el.getAttribute('data-drag-var'));
    });
  }

  function bindVarSearch() {
    var input = $('#edoc-var-search');
    if (!input) return;
    input.addEventListener('input', function () {
      var q = input.value.toLowerCase().trim();
      $all('.edoc-var-group-side').forEach(function (g) {
        var any = false;
        $all('[data-drag-var]', g).forEach(function (b) {
          var key = (b.getAttribute('data-drag-var') || '').toLowerCase();
          var lab = ((b.getAttribute('data-var-nome') || '') + ' ' + (b.getAttribute('title') || '')).toLowerCase();
          var ok = !q || key.indexOf(q) >= 0 || lab.indexOf(q) >= 0;
          b.style.display = ok ? '' : 'none';
          if (ok) any = true;
        });
        g.style.display = any ? '' : 'none';
        if (q && any) g.open = true;
        if (!q) g.open = false;
      });
    });
  }

  function bindEmissao() {
    var tipo = $('#edoc-emissao-tipo');
    var curso = $('#edoc-emissao-curso');
    var serie = $('#edoc-emissao-serie');
    if (!tipo || !curso || !serie) return;
    var series = Array.isArray(C.series) ? C.series : [];
    function preencherSeries() {
      var cursoId = parseInt(curso.value, 10) || 0;
      var atual = parseInt(serie.value, 10) || 0;
      var html = '<option value="0">Todas as séries</option>';
      series.forEach(function (s) {
        if (cursoId > 0 && parseInt(s.curso_id, 10) !== cursoId) return;
        html += '<option value="' + parseInt(s.id, 10) + '">' + esc(s.nome) + '</option>';
      });
      serie.innerHTML = html;
      serie.value = String(atual);
      if (serie.value !== String(atual)) serie.value = '0';
    }
    var em = state.estrutura.emissao || {};
    tipo.value = em.tipo || '';
    curso.value = String(em.curso_id || 0);
    preencherSeries();
    serie.value = String(em.serie_id || 0);
    if (!serie.value) serie.value = '0';
    function gravar() {
      var t = tipo.value;
      if (!t) {
        delete state.estrutura.emissao;
      } else {
        state.estrutura.emissao = {
          tipo: t,
          curso_id: parseInt(curso.value, 10) || 0,
          serie_id: parseInt(serie.value, 10) || 0
        };
      }
      scheduleSave();
    }
    tipo.addEventListener('change', gravar);
    curso.addEventListener('change', function () { preencherSeries(); gravar(); });
    serie.addEventListener('change', gravar);
  }

  function aplicarEstruturaIa(estrutura) {
    state.estrutura = normalizarEstrutura(estrutura);
    if (state.estrutura.page) {
      delete state.estrutura.page.fundo;
      delete state.estrutura.page.imprimirFundo;
    }
    state.selected = null;
    pushHist();
    render();
    scheduleSave();
    setStatus('Layout montado pela IA — confira os campos e salve');
  }

  function consultarJobIa(jobId, btn, file, tentativa) {
    if (tentativa > 45) {
      btn.disabled = false;
      setStatus('A IA demorou demais. Tente de novo.');
      return;
    }
    fetch(C.urlBase + '/admin/ai-job/' + jobId + '/status')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.status === 'done') {
          btn.disabled = false;
          var est = data.result && data.result.estrutura;
          if (!est) {
            setStatus('A IA não devolveu um layout.');
            return;
          }
          aplicarEstruturaIa(est);
          return;
        }
        if (data.status === 'failed' || data.status === 'error' || data.status === 'not_found') {
          btn.disabled = false;
          setStatus(data.error || 'Não foi possível reproduzir a imagem.');
          return;
        }
        setStatus(tentativa < 3 ? 'Lendo a imagem…' : 'Montando cabeçalho, corpo e rodapé…');
        setTimeout(function () { consultarJobIa(jobId, btn, file, tentativa + 1); }, 2000);
      })
      .catch(function () {
        setTimeout(function () { consultarJobIa(jobId, btn, file, tentativa + 1); }, 2500);
      });
  }

  function imagemAceitaIa(file) {
    return !!(file && /^image\/(png|jpeg|jpg|webp)$/i.test(file.type || ''));
  }

  function enviarImagemIa(file) {
    var btn = $('#edoc-ia');
    if (!btn || !C.iaUrl || !imagemAceitaIa(file)) {
      setStatus('Use PNG, JPG ou WebP.');
      return;
    }
    if (!window.confirm('A IA vai montar o documento a partir desta imagem. O conteúdo atual da folha será substituído.')) {
      return;
    }
    btn.disabled = true;
    setStatus('Enviando imagem…');
    var fd = new FormData();
    fd.append('_token', C.csrf);
    fd.append('imagem', file, file.name || 'documento.png');
    fetch(C.iaUrl, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.success && data.job_id) {
          setStatus('Imagem enviada. A IA está lendo o documento…');
          consultarJobIa(data.job_id, btn, file, 1);
          return;
        }
        btn.disabled = false;
        setStatus((data && data.error) || 'Não foi possível enviar a imagem.');
      })
      .catch(function () {
        btn.disabled = false;
        setStatus('Erro de conexão. Tente novamente.');
      });
  }

  function bindReproduzirImagem() {
    var btn = $('#edoc-ia');
    var input = $('#edoc-ia-file');
    var modal = $('#edoc-ia-modal');
    var zona = $('#edoc-ia-cola');
    if (!btn || !input || !modal || !C.iaUrl) return;
    function fechar() { modal.classList.remove('open'); }
    btn.addEventListener('click', function () {
      modal.classList.add('open');
      if (zona) zona.focus();
    });
    var escolher = $('#edoc-ia-escolher');
    if (escolher) escolher.addEventListener('click', function () { input.click(); });
    var fecharBtn = $('#edoc-ia-fechar');
    if (fecharBtn) fecharBtn.addEventListener('click', fechar);
    modal.addEventListener('click', function (e) { if (e.target === modal) fechar(); });
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      input.value = '';
      if (!file) return;
      fechar();
      enviarImagemIa(file);
    });
    document.addEventListener('paste', function (e) {
      if (!modal.classList.contains('open')) return;
      var file = arquivoDoClipboard(e.clipboardData);
      e.preventDefault();
      e.stopPropagation();
      if (!imagemAceitaIa(file)) {
        setStatus('Cole uma imagem PNG, JPG ou WebP.');
        return;
      }
      fechar();
      enviarImagemIa(file);
    }, true);
  }

  function bindChrome() {
    function onClick(sel, fn) {
      var el = $(sel);
      if (el) el.addEventListener('click', fn);
    }
    onClick('#edoc-undo', undo);
    onClick('#edoc-redo', redo);
    onClick('#edoc-zoom-out', function () { state.zoom = Math.max(50, state.zoom - 10); render(); });
    onClick('#edoc-zoom-in', function () { state.zoom = Math.min(150, state.zoom + 10); render(); });
    onClick('#edoc-zoom-fit', function () { state.zoom = 90; render(); });
    onClick('#edoc-layout-sugerido', aplicarLayoutSugerido);
    bindReproduzirImagem();
    onClick('#edoc-preview-mode', function () {
      state.preview = !state.preview;
      this.classList.toggle('active', state.preview);
      render();
    });
    onClick('#edoc-save', save);
    bindEmissao();
    onClick('#edoc-pdf', function (e) {
      e.preventDefault();
      save().then(function (j) {
        if (!j || !j.ok || !C.previewUrl) return;
        var sep = C.previewUrl.indexOf('?') >= 0 ? '&' : '?';
        window.open(C.previewUrl + sep + 't=' + Date.now(), '_blank');
      });
    });
    document.addEventListener('edoc-tree', renderTree);
    document.addEventListener('paste', function (e) {
      if (estaDigitando(e.target)) return;
      var file = arquivoDoClipboard(e.clipboardData);
      if (file) {
        e.preventDefault();
        aplicarImagemColada(file);
        return;
      }
      var html = (e.clipboardData && e.clipboardData.getData('text/html')) || '';
      if (html && /<table/i.test(html)) {
        e.preventDefault();
        inserirHtmlNaFolha(sanitizeHtml(html));
      }
    });
    document.addEventListener('keydown', function (e) {
      var meta = e.metaKey || e.ctrlKey;
      var typing = estaDigitando(e.target);
      if (meta && e.key === 's') { e.preventDefault(); save(); return; }
      if (typing) return;
      if (meta && e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
      if (meta && (e.key === 'Z' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); redo(); }
      if (meta && e.key === 'd' && state.selected) {
        e.preventDefault();
        runAct('dup', state.selected.id);
      }
      if ((e.key === 'Delete' || e.key === 'Backspace') && state.selected && document.activeElement === document.body) {
        runAct('del', state.selected.id);
        runAct('sec-del', state.selected.id);
      }
    });
    var stage = $('.edoc-stage');
    if (stage) {
      stage.addEventListener('click', function (e) {
        if (e.target !== stage) return;
        if (!editando) return;
        persistirEdicaoSeHouver();
        render();
      });
    }
  }

  try {
    state.history = [clone(state.estrutura)];
    state.histI = 0;
    bindPalette();
    bindVarSearch();
    bindDemonstracao();
    bindChrome();
    render();
  } catch (err) {
    console.error('[editor-documento]', err);
    setStatus('Erro ao carregar o editor');
  }
})();
