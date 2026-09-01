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
      html: 'HTML', linha: 'Linha', espacador: 'Espaçador', pagina: 'Página', quebra_pagina: 'Quebra',
      dados_escola: 'Escola', dados_aluno: 'Aluno', dados_responsavel: 'Responsável', dados_turma: 'Turma',
      frequencia: 'Frequência', observacoes: 'Observações', assinaturas: 'Assinaturas',
      tabela_aluno: 'Tabela aluno', tabela_notas: 'Notas', tabela_frequencia: 'Freq. tabela',
      historico: 'Histórico', resultado_final: 'Resultado', qrcode: 'QR Code'
    };
    return map[t] || t;
  }

  function ph(html) {
    if (!html) return '';
    var out = String(html).replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, function (_, k) {
      if (state.preview && C.varsPreview && C.varsPreview[k] != null) {
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
      + (comImagem ? '<span class="edoc-fmt-sep"></span><button type="button" id="edoc-rte-img" title="Inserir imagem"><i class="fa-solid fa-image"></i></button>' : '');
  }

  function tamanhoFontePt(el, body) {
    var n = el && el.style ? parseInt(el.style.fontSize, 10) : 0;
    if (n >= 8) return n;
    if (body && body.style && body.style.fontSize) {
      n = parseInt(body.style.fontSize, 10);
      if (n >= 8) return n;
    }
    if (body && body.isConnected) {
      var px = parseFloat(window.getComputedStyle(body).fontSize) || 16;
      return Math.max(8, Math.round(px * 72 / 96));
    }
    return 12;
  }

  function atualizarLabelFonte(pt) {
    $all('[data-fmt-size]').forEach(function (n) { n.textContent = String(pt); });
  }

  function aplicarTamanhoFonte(pt, el, body) {
    pt = Math.max(8, Math.min(48, parseInt(pt, 10) || 12));
    var sel = window.getSelection();
    var temSel = sel && !sel.isCollapsed && sel.rangeCount && body && body.contains(sel.anchorNode);
    if (temSel) {
      try { document.execCommand('styleWithCSS', false, true); } catch (err) {}
      document.execCommand('fontSize', false, '7');
      $all('font', body).forEach(function (f) {
        var span = document.createElement('span');
        span.style.fontSize = pt + 'pt';
        while (f.firstChild) span.appendChild(f.firstChild);
        f.parentNode.replaceChild(span, f);
      });
      $all('span', body).forEach(function (s) {
        var fs = (s.style && s.style.fontSize) || '';
        if (fs === 'xxx-large' || fs === 'xx-large' || fs === '-webkit-xxx-large') {
          s.style.fontSize = pt + 'pt';
        }
      });
    } else {
      el.style = el.style || {};
      el.style.fontSize = pt;
      if (body) body.style.fontSize = pt + 'pt';
      var folha = paper && el.id ? corpoDoElemento(paper.querySelector('.edoc-el[data-id="' + el.id + '"]')) : null;
      if (folha && folha !== body) folha.style.fontSize = pt + 'pt';
    }
    atualizarLabelFonte(pt);
  }

  function executarFmt(cmd, el, body) {
    if (!el || !body) return;
    body.focus();
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
      var atual = tamanhoFontePt(el, body);
      aplicarTamanhoFonte(cmd === 'fontInc' ? atual + 2 : atual - 2, el, body);
    }
  }

  function persistirEdicaoSeHouver() {
    if (!editando) return;
    var body = corpoDoElemento(editando.node);
    var path = findPath(editando.id);
    if (path && path.element && body) {
      path.element.props = path.element.props || {};
      path.element.props.html = htmlDoEditor(body);
      delete path.element.props.text;
    }
    esconderBarraInline();
    editando = null;
  }

  function sincronizarBloco(el, body) {
    if (!el || !body) return;
    el.props = el.props || {};
    el.props.html = htmlDoEditor(body);
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

  function iniciarEdicaoNaFolha(node) {
    if (!node || state.preview) return;
    var id = node.getAttribute('data-id');
    var path = findPath(id);
    if (!path || !path.element || !ehTextoEditavel(path.element.type)) return;
    var body = corpoDoElemento(node);
    if (!body) return;
    editando = { id: id, node: node };
    node.classList.add('is-editing');
    body.contentEditable = 'true';
    body.setAttribute('spellcheck', 'true');
    mostrarBarraInline(node);
    atualizarLabelFonte(tamanhoFontePt(path.element, body));
    body.focus();
    body.onkeydown = function (e) {
      if (e.key === 'Enter') {
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
          document.execCommand('insertHTML', false, '<br>' + htmlImgData(uri) + '<br>');
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
        ? '<img src="' + img.replace(/"/g, '') + '" alt="Logo" style="max-width:' + w + 'px;max-height:64px;width:auto;height:auto;object-fit:contain;">'
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
      var quadro = (C.varsPreview && C.varsPreview.quadro_notas_html)
        ? C.varsPreview.quadro_notas_html
        : '{{quadro_notas_html}}';
      return '<div class="edoc-quadro-notas" style="' + st + '">' + sanitizeHtml(quadro) + '</div>';
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
    var html = '<div class="edoc-section' + (state.selected && state.selected.id === sec.id ? ' is-selected' : '') + '" data-id="' + sec.id + '" data-kind="section">';
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
        + '" data-id="' + col.id + '" data-kind="column" style="flex:0 0 ' + (col.width || 100) + '%;justify-content:'
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

    var html = '';
    [['header', 'Cabeçalho'], ['body', 'Corpo'], ['footer', 'Rodapé']].forEach(function (pair) {
      html += '<div class="edoc-area-label">' + pair[1] + '</div>';
      var secs = areaOf(pair[0]).sections || [];
      if (!secs.length) {
        html += '<div class="edoc-empty edoc-dropzone" data-empty="' + pair[0] + '">Arraste um layout para começar</div>';
      }
      secs.forEach(function (s) { html += renderSection(s, pair[0]); });
    });
    paper.innerHTML = html;
    bindCanvas();
    renderTree();
    renderProps();
    var zl = $('#edoc-zoom-label');
    if (zl) zl.textContent = state.zoom + '%';
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

  function bindCanvas() {
    if (!paper) return;
    paper.onmousedown = function (e) {
      if (state.preview) return;
      if (e.button && e.button !== 0) return;
      if (e.target.closest('[data-act]') || e.target.closest('.edoc-el-toolbar') || e.target.closest('#edoc-inline-bar')) return;
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
    paper.onclick = onCanvasClick;
    $all('.edoc-col', paper).forEach(function (col) {
      col.addEventListener('dragover', function (e) {
        e.preventDefault();
        col.classList.add('is-over');
      });
      col.addEventListener('dragleave', function () { col.classList.remove('is-over'); });
      col.addEventListener('drop', function (e) {
        e.preventDefault();
        col.classList.remove('is-over');
        var tipo = e.dataTransfer.getData('text/edoc-type');
        var layout = e.dataTransfer.getData('text/edoc-layout');
        var chave = lerChaveDrop(e.dataTransfer);
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
    if (!colId) {
      addSection('body', [100]);
      colId = areaOf('body').sections.slice(-1)[0].columns[0].id;
    }
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
      + '<div><label>Topo</label>' + inp('mt', m.top || 15, 'type="number"') + '</div>'
      + '<div><label>Direita</label>' + inp('mr', m.right || 15, 'type="number"') + '</div>'
      + '<div><label>Baixo</label>' + inp('mb', m.bottom || 15, 'type="number"') + '</div>'
      + '<div><label>Esquerda</label>' + inp('ml', m.left || 15, 'type="number"') + '</div></div>';
  }

  function posBtns(field, current, items) {
    return items.map(function (it) {
      return '<button type="button" class="edoc-btn edoc-btn-icon' + (current === it[0] ? ' active' : '')
        + '" data-pos-field="' + field + '" data-pos-val="' + it[0] + '" title="' + it[2] + '">'
        + '<i class="fa-solid ' + it[1] + '"></i></button>';
    }).join('');
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
        + '<div><label>Tamanho (pt)</label>' + inp('fontSize', st.fontSize || '', 'type="number" min="8" max="48"') + '</div>'
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
            p.margin[map[f.getAttribute('data-f')]] = parseInt(f.value, 10) || 0;
            pushHist(); render();
          };
        }
      });
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
      if (cmd === 'fontInc' || cmd === 'fontDec' || cmd === 'justifyLeft' || cmd === 'justifyCenter' || cmd === 'justifyRight' || cmd === 'justifyFull') return;
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
    var imgBtn = $('#edoc-rte-img', box);
    if (imgBtn) {
      var fileInp = document.createElement('input');
      fileInp.type = 'file';
      fileInp.accept = 'image/png,image/jpeg,image/gif,image/webp';
      fileInp.hidden = true;
      box.appendChild(fileInp);
      imgBtn.addEventListener('mousedown', function (e) { e.preventDefault(); });
      imgBtn.addEventListener('click', function () { fileInp.click(); });
      fileInp.addEventListener('change', function () {
        if (fileInp.files && fileInp.files[0]) inserirArquivoNoRte(fileInp.files[0]);
        fileInp.value = '';
      });
    }
    rte.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
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

  function bindPalette() {
    $all('[data-drag-type]').forEach(function (el) {
      el.setAttribute('draggable', 'true');
      el.addEventListener('dragstart', function (e) {
        e.dataTransfer.setData('text/edoc-type', el.getAttribute('data-drag-type'));
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
    var draggingVar = false;
    $all('[data-drag-var]').forEach(function (el) {
      el.setAttribute('draggable', 'true');
      el.addEventListener('dragstart', function (e) {
        draggingVar = true;
        var chave = el.getAttribute('data-drag-var');
        e.dataTransfer.setData('text/edoc-var', chave);
        e.dataTransfer.setData('text/plain', 'edoc-var:' + chave);
      });
      el.addEventListener('dragend', function () {
        setTimeout(function () { draggingVar = false; }, 0);
      });
      el.addEventListener('click', function () {
        if (draggingVar) return;
        insertVariavelIntoSelection(el.getAttribute('data-drag-var'));
      });
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
          var lab = (b.getAttribute('title') || '').toLowerCase();
          var ok = !q || key.indexOf(q) >= 0 || lab.indexOf(q) >= 0;
          b.style.display = ok ? '' : 'none';
          if (ok) any = true;
        });
        g.style.display = any ? '' : 'none';
      });
    });
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
    onClick('#edoc-preview-mode', function () {
      state.preview = !state.preview;
      this.classList.toggle('active', state.preview);
      render();
    });
    onClick('#edoc-save', save);
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
      if (!file) return;
      e.preventDefault();
      aplicarImagemColada(file);
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
    bindChrome();
    render();
  } catch (err) {
    console.error('[editor-documento]', err);
    setStatus('Erro ao carregar o editor');
  }
})();
