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
    });
    return d.innerHTML;
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

  function htmlElemento(el) {
    var p = el.props || {};
    var st = cssBox(el.style);
    var t = el.type;
    if (t === 'titulo') {
      var tag = p.tag === 'h2' || p.tag === 'h3' ? p.tag : 'h1';
      var sz = tag === 'h1' ? '16pt' : (tag === 'h2' ? '13pt' : '11pt');
      return '<' + tag + ' style="margin:0;font-size:' + sz + ';' + st + '">' + ph(esc(p.text || 'Título')) + '</' + tag + '>';
    }
    if (t === 'texto' || t === 'texto_rico') {
      var tx = p.html || p.text || 'Texto';
      return '<div style="' + st + '">' + ph(String(tx).indexOf('<') >= 0 ? sanitizeHtml(tx) : esc(tx)) + '</div>';
    }
    if (t === 'html') return '<div class="edoc-html-raw" style="' + st + '">' + ph(sanitizeHtml(p.html || '')) + '</div>';
    if (t === 'logo') {
      var w = p.width || 120;
      var img = (C.logoPreview || '');
      var inner = img
        ? '<img src="' + img.replace(/"/g, '') + '" alt="Logo" style="max-width:' + w + 'px;max-height:70px;height:auto;">'
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
          + ((el.type === 'logo' || el.type === 'imagem') ? ' edoc-el-stretch' : '')
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
    var pg = mmPage();
    var z = state.zoom / 100;
    paper = $('#edoc-paper');
    var wrap = $('#edoc-paper-wrap');
    if (!paper || !wrap) return;
    paper.style.width = pg.w + 'mm';
    paper.style.minHeight = pg.h + 'mm';
    paper.style.padding = pg.margin.top + 'mm ' + pg.margin.right + 'mm ' + pg.margin.bottom + 'mm ' + pg.margin.left + 'mm';
    paper.style.transform = 'scale(' + z + ')';
    wrap.style.width = (pg.w * z) + 'mm';
    wrap.style.minHeight = (pg.h * z) + 'mm';
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

  function bindCanvas() {
    if (!paper) return;
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
    var act = e.target.closest('[data-act]');
    var node = e.target.closest('[data-id]');
    if (act && node) {
      e.stopPropagation();
      runAct(act.getAttribute('data-act'), node.getAttribute('data-id'));
      return;
    }
    if (node) {
      state.selected = { id: node.getAttribute('data-id'), kind: node.getAttribute('data-kind') };
      render();
    } else {
      state.selected = null;
      renderProps();
      $all('.is-selected', paper).forEach(function (n) { n.classList.remove('is-selected'); });
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
    if (tipo === 'logo') el.props = { width: 120, align: 'center', vAlign: 'middle' };
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
    var sel = state.selected ? findPath(state.selected.id) : null;
    if (sel && sel.element && ['titulo', 'texto', 'texto_rico', 'html'].indexOf(sel.element.type) >= 0) {
      sel.element.props = sel.element.props || {};
      var cur = sel.element.props.html || sel.element.props.text || '';
      if (sel.element.type === 'html' || (sel.element.props.html != null && String(sel.element.props.html).indexOf('<') >= 0)) {
        sel.element.props.html = cur + token;
      } else {
        sel.element.props.text = cur + token;
      }
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
      html += '<label>Conteúdo</label><textarea data-f="text" rows="5">' + String(p.text || p.html || '').replace(/</g, '&lt;') + '</textarea>';
      html += '<button type="button" class="edoc-btn" id="edoc-insert-var" style="margin-top:6px">{ } Variáveis</button>';
    }
    if (el.type === 'tabela_notas') {
      html += '<p class="edoc-hint">Quadro com 1º ao 4º bimestre e final (nota e falta). O tamanho do texto abaixo vale para a tabela inteira — diminua para caber em uma folha.</p>';
    }
    if (el.type === 'titulo') {
      html += '<label>Nível</label><select data-f="tag"><option value="h1">Título</option><option value="h2">Subtítulo</option><option value="h3">Seção</option></select>';
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
        + '<div><label>Peso</label><select data-f="fontWeight"><option value="">Normal</option><option value="bold">Negrito</option></select></div></div>'
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
    if (varBtn) varBtn.addEventListener('click', function () { openVars(target); });
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
        if (target.type === 'html' || (String(val).indexOf('<') >= 0)) target.props.html = val;
        else target.props.text = val;
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
        var inner = node.querySelector('h1,h2,h3,div,p');
        if (inner) inner.innerHTML = ph(val);
      }
    }
  }

  function openVars(target) {
    var modal = $('#edoc-vars');
    modal.classList.add('open');
    modal.onclick = function (e) { if (e.target === modal) modal.classList.remove('open'); };
    $all('[data-var]', modal).forEach(function (b) {
      b.onclick = function () {
        var token = tokenDaChave(b.getAttribute('data-var'));
        target.props = target.props || {};
        var cur = target.props.text || target.props.html || '';
        target.props.text = cur + token;
        modal.classList.remove('open');
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
    document.addEventListener('keydown', function (e) {
      var meta = e.metaKey || e.ctrlKey;
      if (meta && e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
      if (meta && (e.key === 'Z' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); redo(); }
      if (meta && e.key === 's') { e.preventDefault(); save(); }
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
      stage.addEventListener('dblclick', function (e) {
        var node = e.target.closest('.edoc-el');
        if (!node) return;
        var path = findPath(node.getAttribute('data-id'));
        if (!path || !path.element) return;
        if (['titulo', 'texto', 'texto_rico', 'html'].indexOf(path.element.type) < 0) return;
        var editable = node.querySelector('h1,h2,h3,div,p');
        if (!editable) return;
        editable.contentEditable = 'true';
        editable.focus();
        editable.onblur = function () {
          editable.contentEditable = 'false';
          var txt = editable.innerHTML;
          path.element.props = path.element.props || {};
          if (path.element.type === 'html' || txt.indexOf('<') >= 0) path.element.props.html = txt;
          else path.element.props.text = editable.innerText;
          pushHist();
        };
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
