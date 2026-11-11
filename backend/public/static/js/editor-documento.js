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
        },
        rodapeNoFim: page.rodapeNoFim === true,
        ocultarCabecalho: page.ocultarCabecalho === true,
        ocultarRodape: page.ocultarRodape === true
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
    var glossario = glossarioValido(e.glossario);
    if (glossario.length) out.glossario = glossario;
    if (e.emissao && typeof e.emissao === 'object') out.emissao = e.emissao;
    var campos = camposVariaveisValidos(e.campos_variaveis);
    if (campos.length) out.campos_variaveis = campos;
    var fonteBoletimId = parseInt(e.fonte_boletim_id, 10) || 0;
    if (fonteBoletimId > 0) out.fonte_boletim_id = fonteBoletimId;
    var fonteDemonstrativoId = parseInt(e.fonte_demonstrativo_id, 10) || 0;
    if (fonteDemonstrativoId > 0) out.fonte_demonstrativo_id = fonteDemonstrativoId;
    var fonteRegraId = parseInt(e.fonte_regra_academica_id, 10) || 0;
    if (fonteRegraId > 0) out.fonte_regra_academica_id = fonteRegraId;
    return stripLogoDuplicado(out);
  }

  function glossarioValido(lista) {
    if (!Array.isArray(lista)) return [];
    var out = [];
    var vistas = {};
    for (var i = 0; i < lista.length && out.length < 40; i++) {
      var item = lista[i];
      if (!item || typeof item !== 'object') continue;
      var slug = String(item.slug || '').toLowerCase();
      var nome = String(item.nome || '').replace(/[<>]/g, '').replace(/\{\{|\}\}/g, '').replace(/\s+/g, ' ').trim().slice(0, 80);
      var sigla = String(item.sigla || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 12);
      if (!/^[a-z0-9_]{1,40}$/.test(slug) || !nome || !/^[A-Z0-9]{1,12}$/.test(sigla) || vistas[slug]) continue;
      vistas[slug] = true;
      out.push({ slug: slug, nome: nome, sigla: sigla });
    }
    return out;
  }

  function camposVariaveisValidos(lista) {
    if (!Array.isArray(lista)) return [];
    var mascaras = (C.mascaras && typeof C.mascaras === 'object') ? C.mascaras : {};
    var out = [];
    var usadas = {};
    for (var i = 0; i < lista.length && out.length < 24; i++) {
      var item = lista[i];
      if (!item || typeof item !== 'object') continue;
      var chave = String(item.chave || '').toLowerCase();
      if (!/^cv_[a-z0-9_]{1,40}$/.test(chave) || usadas[chave]) continue;
      var rotulo = String(item.rotulo || '').replace(/\s+/g, ' ').trim().slice(0, 80);
      if (rotulo.length < 2) continue;
      var mascara = String(item.mascara || 'texto');
      if (!mascaras[mascara]) mascara = 'texto';
      usadas[chave] = true;
      out.push({
        chave: chave,
        rotulo: rotulo,
        mascara: mascara,
        obrigatorio: !!item.obrigatorio
      });
    }
    return out;
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
    ajustarZoom: true,
    preview: false,
    demo: true,
    history: [],
    histI: -1,
    dirty: false,
    saving: false
  };

  var paper = null;
  var editando = null;
  var ultimaCelulaEditavel = null;
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

  function marcarReproducaoIa(ativo, texto) {
    var overlay = $('#edoc-ia-overlay');
    var msg = $('#edoc-ia-overlay-msg');
    var status = $('#edoc-status');
    if (overlay) overlay.classList.toggle('is-on', !!ativo);
    if (status) status.classList.toggle('is-ia', !!ativo);
    if (msg && texto) msg.textContent = texto;
    if (texto) setStatus(texto);
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

  /** Quadro PP/PU/CH… só cabe como no PDF de referência: A4 paisagem + tipografia densa. */
  function garantirPaisagemBoletim() {
    state.estrutura = state.estrutura || {};
    state.estrutura.page = state.estrutura.page || {};
    var page = state.estrutura.page;
    var mudou = false;
    if ((page.orientation || 'portrait') !== 'landscape') {
      page.orientation = 'landscape';
      mudou = true;
    }
    if (String(page.size || 'A4').toUpperCase() !== 'A4') {
      page.size = 'A4';
      mudou = true;
    }
    page.margin = page.margin || { top: 10, right: 8, bottom: 8, left: 8 };
    ['top', 'right', 'bottom', 'left'].forEach(function (lado) {
      var atual = parseFloat(page.margin[lado]);
      var alvo = lado === 'top' ? 10 : 8;
      if (!isFinite(atual) || atual > alvo) {
        page.margin[lado] = alvo;
        mudou = true;
      }
    });
    return mudou;
  }

  function densificarElementosBoletim() {
    var mudou = false;
    ['header', 'body', 'footer'].forEach(function (role) {
      var secs = (areaOf(role).sections || []);
      secs.forEach(function (sec) {
        (sec.columns || []).forEach(function (col) {
          (col.elements || []).forEach(function (el) {
            if (!elementoEhBoletim(el)) return;
            el.style = el.style || {};
            var pt = parseFloat(el.style.fontSize);
            if (!isFinite(pt) || pt <= 0) {
              el.style.fontSize = 7.5;
              mudou = true;
            }
            if (!String((el.props || {}).html || '').trim()) {
              garantirHtmlBoletimPadrao(el);
              mudou = true;
            }
          });
        });
      });
    });
    return mudou;
  }

  function fonteBoletimPt(el) {
    var pt = parseFloat(el && el.style && el.style.fontSize);
    return isFinite(pt) && pt > 0 ? pt : 7.5;
  }

  function cssCaixaBoletim(st) {
    st = st || {};
    var m = st.margin || {};
    var p = st.padding || {};
    var pt = fonteBoletimPt({ style: st });
    var out = 'font-size:' + pt + 'pt;width:100%;max-width:100%;';
    if ((m.top || m.right || m.bottom || m.left)) {
      out += 'margin:' + (parseInt(m.top, 10) || 0) + 'px '
        + (parseInt(m.right, 10) || 0) + 'px '
        + (parseInt(m.bottom, 10) || 0) + 'px '
        + (parseInt(m.left, 10) || 0) + 'px;';
    }
    if ((p.top || p.right || p.bottom || p.left)) {
      out += 'padding:' + (parseInt(p.top, 10) || 0) + 'px '
        + (parseInt(p.right, 10) || 0) + 'px '
        + (parseInt(p.bottom, 10) || 0) + 'px '
        + (parseInt(p.left, 10) || 0) + 'px;';
    }
    return out;
  }

  function garantirHtmlBoletimPadrao(el) {
    if (!el) return;
    el.props = el.props || {};
    el.style = el.style || {};
    if (!el.style.fontSize) el.style.fontSize = 7.5;
    var chave = chaveQuadroDoElemento(el);
    el.props.html = '<div class="quadro-notas-wrap doc-quadro-fit" style="'
      + cssCaixaBoletim(el.style) + '">{{' + chave + '}}</div>';
  }

  function chaveEhQuadroNotas(chave) {
    chave = String(chave || '').trim();
    return chave === 'quadro_notas_html'
      || chave === 'boletim_html'
      || /^quadro_notas_b[1-4]_html$/.test(chave);
  }

  function boletimHtmlSoDinamico(html) {
    var h = String(html || '').trim();
    if (!h) return true;
    if (h.indexOf('{{boletim_celula_') >= 0) return false;
    return /\{\{\s*(?:quadro_notas_html|boletim_html|quadro_notas_b[1-4]_html)\s*\}\}/i.test(h);
  }

  /** Texto/HTML com só {{quadro_notas_html}} vira bloco Demonstrativo (não Boletim). */
  function promoverParaBoletimSeQuadro(el) {
    if (!el || el.type === 'boletim' || el.type === 'demonstrativo_notas' || el.type === 'tabela_notas') return false;
    var raw = String((el.props || {}).html || (el.props || {}).text || '').trim();
    if (!/\{\{\s*quadro_notas_html\s*\}\}/i.test(raw)) return false;
    var resto = raw.replace(/\{\{\s*quadro_notas_html\s*\}\}/gi, '').replace(/<[^>]+>/g, '').trim();
    if (resto !== '') return false;
    el.type = 'demonstrativo_notas';
    el.props = el.props || {};
    delete el.props.text;
    if (!String(el.props.html || '').trim() || !/quadro_notas_html/i.test(el.props.html)) {
      garantirHtmlBoletimPadrao(el);
    }
    el.style = el.style || {};
    if (!el.style.fontSize) el.style.fontSize = 7.5;
    garantirPaisagemBoletim();
    return true;
  }

  /** Troca o wrapper dinâmico pela tabela editável (células {{boletim_celula_*}}).
   *  Não usa em Boletim nem em Demonstrativo por bimestre — cada um tem HTML próprio. */
  function garantirHtmlBoletimEditavel(el) {
    if (!el) return false;
    if (el.type === 'boletim') return false;
    if (el.type === 'demonstrativo_notas' || el.type === 'tabela_notas') return false;
    el.props = el.props || {};
    el.style = el.style || {};
    if (!boletimHtmlSoDinamico(el.props.html)) return false;
    var modelo = String(C.modeloQuadroHtml || '').trim();
    if (!modelo || modelo.indexOf('{{boletim_celula_') < 0) return false;
    if (!el.style.fontSize) el.style.fontSize = 7.5;
    el.props.html = '<div class="quadro-notas-wrap doc-quadro-fit" style="'
      + cssCaixaBoletim(el.style) + '">' + modelo + '</div>';
    sincronizarHtmlBoletimComEstilo(el);
    return true;
  }

  function chaveDemonstrativoPeriodo(bimestre, chaveProp) {
    var chave = String(chaveProp || '').trim();
    if (chave) return chave;
    var bim = parseInt(bimestre || 0, 10) || 0;
    if (bim >= 1 && bim <= 4) return 'quadro_notas_b' + bim + '_html';
    if (bim > 0) return 'quadro_notas_p' + bim + '_html';
    return 'quadro_notas_html';
  }

  /** Bloco Demonstrativo sempre aponta para o token do período (nunca HTML de Boletim). */
  function restaurarPlaceholderDemonstrativo(el) {
    if (!el || (el.type !== 'demonstrativo_notas' && el.type !== 'tabela_notas')) return false;
    el.props = el.props || {};
    var chave = chaveDemonstrativoPeriodo(el.props.bimestre, el.props.chave);
    el.props.chave = chave;
    var html = String(el.props.html || '');
    var tokenOk = boletimHtmlSoDinamico(html) && html.indexOf('{{' + chave + '}}') >= 0
      && html.indexOf('{{boletim_html}}') < 0;
    if (tokenOk) return false;
    el.props.html = '<div class="quadro-notas-wrap doc-quadro-fit" style="font-size:7.5pt;width:100%;max-width:100%;">{{' + chave + '}}</div>';
    return true;
  }

  /** Espelha margem/tamanho do painel dentro do HTML do boletim. */
  function sincronizarHtmlBoletimComEstilo(el) {
    if (!el || !elementoEhBoletim(el)) return;
    el.props = el.props || {};
    el.style = el.style || {};
    var html = String(el.props.html || '').trim();
    var box = cssCaixaBoletim(el.style);
    var pt = fonteBoletimPt(el);
    if (!html) {
      garantirHtmlBoletimPadrao(el);
      return;
    }
    if (/<(div)\b[^>]*class="[^"]*(?:quadro-notas-wrap|doc-quadro-fit)[^"]*"/i.test(html)) {
      html = html.replace(/<(div)(\b[^>]*class="[^"]*(?:quadro-notas-wrap|doc-quadro-fit)[^"]*"[^>]*)>/i, function (full, tag, attrs) {
        if (/style\s*=\s*"/i.test(attrs)) {
          attrs = attrs.replace(/style\s*=\s*"([^"]*)"/i, function (_, s) {
            s = String(s)
              .replace(/font-size\s*:\s*[^;]+;?/ig, '')
              .replace(/margin\s*:\s*[^;]+;?/ig, '')
              .replace(/padding\s*:\s*[^;]+;?/ig, '')
              .replace(/width\s*:\s*[^;]+;?/ig, '')
              .replace(/max-width\s*:\s*[^;]+;?/ig, '')
              .replace(/;{2,}/g, ';')
              .replace(/^;|;$/g, '');
            return 'style="' + box + (s ? s + ';' : '') + '"';
          });
        } else {
          attrs += ' style="' + box + '"';
        }
        return '<' + tag + attrs + '>';
      });
    } else if (html.indexOf('{{quadro_notas_html}}') >= 0) {
      html = '<div class="quadro-notas-wrap doc-quadro-fit" style="' + box + '">' + html + '</div>';
    } else {
      html = '<div class="quadro-notas-wrap doc-quadro-fit" style="' + box + '">' + html + '</div>';
    }
    html = html.replace(/<table\b([^>]*)>/gi, function (full, attrs) {
      if (/style\s*=\s*"/i.test(attrs)) {
        attrs = attrs.replace(/style\s*=\s*"([^"]*)"/i, function (_, s) {
          s = String(s).replace(/font-size\s*:\s*[^;]+;?/ig, '').replace(/;{2,}/g, ';').replace(/^;|;$/g, '');
          return 'style="font-size:' + pt + 'pt;' + (s ? s + ';' : '') + '"';
        });
        return '<table' + attrs + '>';
      }
      return '<table style="font-size:' + pt + 'pt;"' + attrs + '>';
    });
    el.props.html = html;
  }

  function htmlBoletimDaPrevia() {
    var bag = varsDaFolha() || {};
    return String(bag.boletim_html || '').trim();
  }

  function atualizarCanvasBoletim(el) {
    if (!paper || !el) return;
    var node = paper.querySelector('.edoc-el[data-id="' + el.id + '"]');
    if (!node) return;
    var inner = corpoDoElemento(node);
    if (!inner) return;
    var custom = String((el.props || {}).html || '').trim();
    if (custom) {
      inner.innerHTML = sanitizeHtml(ph(custom));
    }
    inner.setAttribute('style', cssBox(el.style));
    requestAnimationFrame(function () {
      ajustarQuadroNotasNaFolha();
    });
  }

  function larguraUtilFolhaPx() {
    if (!paper) return 0;
    var pg = mmPage();
    var largura = paper.offsetWidth;
    if (!largura || !pg.w) return 0;
    var pxPorMm = largura / pg.w;
    var utilMm = pg.w - (pg.margin.left || 0) - (pg.margin.right || 0);
    return Math.max(0, utilMm * pxPorMm);
  }

  function ajustarQuadroNotasNaFolha() {
    if (!paper) return;
    var wraps = paper.querySelectorAll('.edoc-quadro-notas');
    if (!wraps.length) return;
    var avail = larguraUtilFolhaPx();
    if (avail <= 0) return;
    var alvo = Math.floor(avail);

    wraps.forEach(function (wrap) {
      var tables = wrap.querySelectorAll('table');
      if (!tables.length) return;

      var host = wrap.closest('.edoc-el');
      if (host) {
        host.style.maxWidth = '100%';
        host.style.width = '100%';
        host.style.overflow = 'hidden';
      }
      wrap.style.maxWidth = '100%';
      wrap.style.width = '100%';
      wrap.style.overflow = 'hidden';
      // clientWidth não inclui o zoom e respeita colunas e padding do bloco.
      alvo = Math.floor(wrap.clientWidth || avail);

      // Nunca usar max-content aqui: com width:100% na tabela vira loop e explode a largura.
      wrap.querySelectorAll('.overflow-x-auto, .overflow-auto, .space-y-8, .doc-quadro-fit').forEach(function (n) {
        n.style.width = '100%';
        n.style.maxWidth = '100%';
        n.style.minWidth = '0';
        n.style.overflow = 'visible';
      });

      var scaleEl = wrap.querySelector('[data-edoc-quadro-fit]');
      var shell = wrap.querySelector('[data-edoc-quadro-shell]');
      if (!scaleEl) {
        scaleEl = document.createElement('div');
        scaleEl.setAttribute('data-edoc-quadro-fit', '1');
        while (wrap.firstChild) scaleEl.appendChild(wrap.firstChild);
        shell = document.createElement('div');
        shell.setAttribute('data-edoc-quadro-shell', '1');
        shell.appendChild(scaleEl);
        wrap.appendChild(shell);
      }
      if (!shell) shell = wrap;

      scaleEl.style.transform = '';
      scaleEl.style.transformOrigin = 'top left';
      scaleEl.style.width = '100%';
      scaleEl.style.maxWidth = '100%';
      shell.style.width = '100%';
      shell.style.maxWidth = '100%';
      shell.style.height = 'auto';
      shell.style.overflow = 'hidden';

      Array.prototype.forEach.call(tables, function (table) {
        table.style.setProperty('width', '100%', 'important');
        table.style.setProperty('max-width', '100%', 'important');
        table.style.setProperty('min-width', '0', 'important');
        table.style.setProperty('table-layout', 'fixed', 'important');
        Array.prototype.forEach.call(table.querySelectorAll('th, td'), function (cel) {
          cel.style.setProperty('white-space', 'normal', 'important');
          cel.style.setProperty('word-break', 'normal', 'important');
          cel.style.setProperty('overflow-wrap', 'normal', 'important');
          cel.style.setProperty('min-width', '0', 'important');
          cel.style.setProperty('padding', '1px 2px', 'important');
          // Semestres agrupam só duas colunas: o título precisa de fonte menor.
          var fonteCabecalho = cel.tagName === 'TH' && cel.colSpan === 2 ? '0.85em' : 'inherit';
          cel.style.setProperty('font-size', fonteCabecalho, 'important');
          cel.style.setProperty('line-height', '1.05', 'important');
        });
      });

      // Se ainda passar da área útil, escala de verdade (medida com a tabela já limitada).
      var need = Math.max(scaleEl.scrollWidth || 0, wrap.scrollWidth || 0, 0);
      if (need > alvo + 2) {
        var s = Math.min(1, alvo / need);
        if (s < 0.35) s = 0.35;
        scaleEl.style.transform = 'scale(' + s + ')';
        var naturalH = Math.max(scaleEl.scrollHeight || 0, scaleEl.offsetHeight || 0);
        shell.style.height = Math.ceil(naturalH * s) + 'px';
        shell.style.width = Math.ceil(alvo) + 'px';
      }
    });
  }

  function labelTipo(t) {
    var map = {
      titulo: 'Título', texto: 'Texto', texto_rico: 'Texto rico', logo: 'Logo', imagem: 'Imagem',
      tabela: 'Tabela', html: 'HTML', linha: 'Linha', espacador: 'Espaçador', pagina: 'Página', quebra_pagina: 'Quebra',
      dados_escola: 'Escola', dados_aluno: 'Aluno', dados_responsavel: 'Responsável', dados_turma: 'Turma',
      frequencia: 'Frequência', observacoes: 'Observações', assinaturas: 'Assinaturas',
      tabela_aluno: 'Tabela aluno', tabela_notas: 'Notas', tabela_frequencia: 'Freq. tabela',
      boletim: 'Boletim', demonstrativo_notas: 'Demonstrativo de Notas',
      historico: 'Histórico', resultado_final: 'Resultado', qrcode: 'QR Code'
    };
    return map[t] || t;
  }

  C.varsAmostra = Object.assign({}, C.varsPreview || {});

  function varsDaFolha() {
    if ((state.preview || state.demo) && C.varsPreview) return C.varsPreview;
    return C.varsAmostra || C.varsPreview || {};
  }

  function ph(html) {
    if (!html) return '';
    var bag = varsDaFolha();
    var out = String(html).replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, function (_, k) {
      if (bag && Object.prototype.hasOwnProperty.call(bag, k) && String(bag[k]) !== '') {
        return String(bag[k]);
      }
      if (k.indexOf('boletim_celula_') === 0) return '—';
      var campo = campoVariavelPorChave(k);
      if (campo) return '<span class="edoc-ph">' + esc(campo.rotulo) + '</span>';
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
    $all('[data-edoc-token]', clone).forEach(function (cel) {
      var chave = String(cel.getAttribute('data-edoc-token') || '');
      if (!/^[a-z0-9_]{1,80}$/i.test(chave)) {
        cel.removeAttribute('data-edoc-token');
        return;
      }
      cel.textContent = '{{' + chave + '}}';
    });
    return sanitizeHtml(clone.innerHTML);
  }

  function atualizarTokensDasCelulas(body) {
    if (!body) return;
    var bag = varsDaFolha();
    $all('[data-edoc-token]', body).forEach(function (cel) {
      var chave = String(cel.getAttribute('data-edoc-token') || '');
      var texto = (cel.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
      var token = '{{' + chave + '}}';
      var resolvido = '';
      if (bag && Object.prototype.hasOwnProperty.call(bag, chave)) {
        resolvido = String(bag[chave]).replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
      }
      if (!texto || texto === token || texto === resolvido) return;
      cel.removeAttribute('data-edoc-token');
    });
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

  function htmlBarraFmt(comImagem, somenteTexto) {
    var html = '<button type="button" data-fmt="bold" title="Negrito (Ctrl+B)"><i class="fa-solid fa-bold"></i></button>'
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
      + '<span class="edoc-fmt-sep"></span>';
    if (somenteTexto) return html;
    return html + '<button type="button" data-fmt="tableInsert" title="Inserir tabela"><i class="fa-solid fa-table"></i></button>'
      + '<button type="button" data-fmt="tableInsRow" title="Inserir linha"><i class="fa-solid fa-plus"></i></button>'
      + '<button type="button" data-fmt="tableInsCol" title="Inserir coluna"><i class="fa-solid fa-grip-lines-vertical"></i></button>'
      + '<button type="button" data-fmt="tableMerge" title="Mesclar células selecionadas"><i class="fa-solid fa-object-group"></i></button>'
      + '<button type="button" data-fmt="cellVertical" title="Texto na vertical"><i class="fa-solid fa-arrow-down-short-wide"></i></button>'
      + '<button type="button" data-fmt="cellFaixa" title="Faixa cinza"><i class="fa-solid fa-fill-drip"></i></button>'
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
      if (ptSel >= 6) return Math.max(6, Math.min(72, ptSel));
    }
    if (body && body.isConnected) {
      var bruto = (body.style && body.style.fontSize) || window.getComputedStyle(body).fontSize;
      var ptBody = cssSizeParaPt(bruto);
      if (ptBody >= 6) return Math.max(6, Math.min(72, ptBody));
    }
    if (el && el.style && el.style.fontSize != null && el.style.fontSize !== '') {
      var raw = String(el.style.fontSize);
      var ptEl = cssSizeParaPt(/[a-z%]/i.test(raw) ? raw : raw + 'pt');
      if (ptEl >= 6) return Math.max(6, Math.min(72, ptEl));
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
    pt = Math.max(6, Math.min(72, parseInt(pt, 10) || 12));
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
      $all('table, td, th', body).forEach(function (nodo) { nodo.style.fontSize = pt + 'pt'; });
      var folha = paper && el.id ? corpoDoElemento(paper.querySelector('.edoc-el[data-id="' + el.id + '"]')) : null;
      if (folha && folha !== body) {
        folha.style.fontSize = pt + 'pt';
        $all('table, td, th', folha).forEach(function (nodo) { nodo.style.fontSize = pt + 'pt'; });
      }
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
    if (cmd === 'cellVertical' || cmd === 'cellFaixa') {
      alternarClasseCelulas(cmd === 'cellVertical' ? 'edoc-vert' : 'edoc-faixa', body);
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

  var destinoNovaTabela = null;

  function abrirCriadorTabela(colunaId) {
    persistirEdicaoSeHouver();
    destinoNovaTabela = colunaId;
    var dialogo = $('#edoc-table-dialog');
    if (!dialogo) return;
    atualizarAmostraTabela();
    dialogo.showModal();
    $('#edoc-table-rows').focus();
  }

  function atualizarAmostraTabela() {
    var linhas = Math.max(1, Math.min(40, parseInt($('#edoc-table-rows').value, 10) || 1));
    var colunas = Math.max(1, Math.min(20, parseInt($('#edoc-table-cols').value, 10) || 1));
    var cabecalho = $('#edoc-table-header').checked;
    var amostra = $('#edoc-table-sample');
    amostra.style.gridTemplateColumns = 'repeat(' + Math.min(colunas, 8) + ', 1fr)';
    var html = '';
    for (var r = 0; r < Math.min(linhas, 5); r++) {
      for (var c = 0; c < Math.min(colunas, 8); c++) html += '<span' + (cabecalho && r === 0 ? ' class="edoc-table-sample-header"' : '') + '></span>';
    }
    amostra.innerHTML = html;
    $('#edoc-table-description').textContent = linhas + (linhas === 1 ? ' linha' : ' linhas') + ' × ' + colunas + (colunas === 1 ? ' coluna' : ' colunas') + (cabecalho ? ', incluindo a linha de cabeçalho.' : '.');
  }

  function bindCriadorTabela() {
    var form = $('#edoc-table-form');
    if (!form) return;
    ['#edoc-table-rows', '#edoc-table-cols', '#edoc-table-header'].forEach(function (id) {
      $(id).addEventListener('input', atualizarAmostraTabela);
    });
    $('#edoc-table-cancel').addEventListener('click', function () { $('#edoc-table-dialog').close(); });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!form.reportValidity()) return;
      var config = {
        linhas: parseInt($('#edoc-table-rows').value, 10),
        colunas: parseInt($('#edoc-table-cols').value, 10),
        cabecalho: $('#edoc-table-header').checked
      };
      $('#edoc-table-dialog').close();
      insertElement(destinoNovaTabela || colunaAlvo(), 'tabela', false, config);
    });
  }

  function tabelaDoElemento(el) {
    if (!el || el.type !== 'html') return false;
    var modelo = document.createElement('template');
    modelo.innerHTML = String((el.props || {}).html || '');
    var partes = Array.prototype.filter.call(modelo.content.childNodes, function (n) {
      return n.nodeType === 1 ? n.tagName !== 'STYLE' : !!String(n.textContent || '').trim();
    });
    return partes.length === 1 && partes[0].nodeType === 1 && partes[0].tagName === 'TABLE';
  }

  function htmlAcoesTabela() {
    var acoes = [
      ['tableInsRow', 'Adicionar linha', 'fa-plus'],
      ['tableInsCol', 'Adicionar coluna', 'fa-columns'],
      ['tableMerge', 'Mesclar células', 'fa-object-group'],
      ['cellFaixa', 'Fundo cinza', 'fa-fill-drip']
    ];
    var botoes = function (itens) {
      return itens.map(function (a) {
        return '<button type="button" class="edoc-btn" data-fmt="' + a[0] + '"><i class="fa-solid ' + a[2] + '" aria-hidden="true"></i>' + a[1] + '</button>';
      }).join('');
    };
    return '<div class="edoc-table-actions">' + botoes(acoes) + '</div>'
      + '<details class="edoc-table-advanced"><summary>Mais ações da tabela</summary><div class="edoc-table-actions">'
      + botoes([['tableDelRow', 'Excluir linha', 'fa-minus'], ['tableDelCol', 'Excluir coluna', 'fa-minus'], ['tableSelCol', 'Selecionar coluna', 'fa-table-columns'], ['cellVertical', 'Texto vertical', 'fa-arrow-down-short-wide']])
      + '</div></details>';
  }

  function bindAcoesTabela(box, target) {
    $all('[data-fmt]', box).forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function () {
        var corpo = corpoVivoDoElemento(target);
        if (!corpo || (!celulaDaSelecao(corpo) && !selecaoGrade && !colunaMarcada)) {
          setStatus('Primeiro clique na célula da tabela que deseja alterar.');
          return;
        }
        executarFmt(b.getAttribute('data-fmt'), target, corpo);
        if (corpo.isConnected) sincronizarBloco(target, corpo);
        atualizarLabelFonte(tamanhoFontePt(target, corpo));
      });
    });
  }

  function htmlTabelaVazia(linhas, cols, larguraMm, cabecalho) {
    linhas = Math.max(1, Math.min(40, parseInt(linhas, 10) || 5));
    cols = Math.max(1, Math.min(20, parseInt(cols, 10) || 6));
    var pg = mmPage();
    var util = Math.max(20, larguraMm || (pg.w - (pg.margin.left || 0) - (pg.margin.right || 0)));
    var colMm = Math.round((util / cols) * 10) / 10;
    var soma = Math.round(colMm * cols * 10) / 10;
    var html = '<table class="dados seed-folha edoc-tabela-editavel" style="width:' + soma.toFixed(1) + 'mm;border-collapse:collapse;table-layout:fixed;"><colgroup>';
    var r;
    var c;
    for (c = 0; c < cols; c++) html += '<col style="width:' + colMm.toFixed(1) + 'mm;">';
    html += '</colgroup>';
    for (r = 0; r < linhas; r++) {
      html += '<tr style="height:6.0mm;">';
      for (c = 0; c < cols; c++) {
        var tag = cabecalho && r === 0 ? 'th' : 'td';
        html += '<' + tag + (tag === 'th' ? ' class="edoc-faixa" scope="col"' : '') + ' style="vertical-align:middle;">&nbsp;</' + tag + '>';
      }
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
    if (el && body.contains(el)) {
      var celula = el.closest('td,th');
      if (celula) return celula;
    }
    return ultimaCelulaEditavel && ultimaCelulaEditavel.isConnected && body.contains(ultimaCelulaEditavel) ? ultimaCelulaEditavel : null;
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
    nova.style.height = tr.style.height;
    var referencia = table.classList.contains('edoc-tabela-editavel') && td.tagName === 'TH'
      ? (table.querySelector('td') || document.createElement('td')) : td;
    for (c = 0; c < largura; c++) nova.appendChild(novaCelulaComo(referencia));
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

  function celulasDaSelecaoAtual(body) {
    if (selecaoGrade && selecaoGrade.table && paper && paper.contains(selecaoGrade.table)) {
      return {
        table: selecaoGrade.table,
        r1: selecaoGrade.r1,
        c1: selecaoGrade.c1,
        r2: selecaoGrade.r2,
        c2: selecaoGrade.c2
      };
    }
    var td = body ? celulaDaSelecao(body) : null;
    if (!td) return null;
    var table = td.closest('table');
    var pos = table ? indiceVisualCelula(matrizTabela(table), td) : null;
    if (!table || !pos) return null;
    return { table: table, r1: pos.row, c1: pos.col, r2: pos.row, c2: pos.col };
  }

  function alternarClasseCelulas(classe, body) {
    var sel = celulasDaSelecaoAtual(body);
    if (!sel) {
      setStatus('Selecione uma ou mais células da tabela.');
      return;
    }
    var ctx = contextoTabela(sel.table);
    var alvo = ctx ? ctx.fonte : sel.table;
    var grid = matrizTabela(alvo);
    var ra = Math.min(sel.r1, sel.r2);
    var rb = Math.max(sel.r1, sel.r2);
    var ca = Math.min(sel.c1, sel.c2);
    var cb = Math.max(sel.c1, sel.c2);
    var vistos = [];
    var ligar = false;
    var r;
    var c;
    for (r = ra; r <= rb; r++) {
      for (c = ca; c <= cb; c++) {
        var cell = grid[r] && grid[r][c];
        if (!cell || vistos.indexOf(cell) >= 0) continue;
        vistos.push(cell);
        if (!cell.classList.contains(classe)) ligar = true;
      }
    }
    if (!vistos.length) {
      setStatus('Não foi possível alterar essas células.');
      return;
    }
    vistos.forEach(function (cell) { cell.classList.toggle(classe, ligar); });
    if (ctx) gravarContexto(ctx);
    else setStatus(ligar ? 'Estilo aplicado' : 'Estilo removido');
    if (ctx) setStatus(ligar ? (classe === 'edoc-vert' ? 'Texto na vertical' : 'Faixa cinza') : 'Estilo removido');
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

  function excluirColunaNaTabela(table, col) {
    var grid = matrizTabela(table);
    if (larguraGrade(grid) <= 1) return false;
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
    return true;
  }

  function aplicarNaTabelaFonte(table, mutar) {
    var ctx = contextoTabela(table);
    if (!ctx) {
      setStatus('Não foi possível alterar esta tabela.');
      return false;
    }
    if (!mutar(ctx.fonte)) return false;
    gravarContexto(ctx);
    return true;
  }

  function selecaoTemVariasCelulas() {
    if (!selecaoGrade || !selecaoGrade.table || !paper || !paper.contains(selecaoGrade.table)) return false;
    var linhas = Math.abs(selecaoGrade.r2 - selecaoGrade.r1) + 1;
    var colunas = Math.abs(selecaoGrade.c2 - selecaoGrade.c1) + 1;
    return linhas * colunas > 1;
  }

  function apagarSelecaoPeloTeclado(e) {
    if (e.key !== 'Delete' && e.key !== 'Backspace') return false;
    if (e.metaKey || e.ctrlKey || e.altKey || state.preview) return false;
    if (!selecaoTemVariasCelulas()) return false;
    var tag = ((e.target && e.target.tagName) || '').toUpperCase();
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return false;
    e.preventDefault();
    e.stopPropagation();
    excluirLinhasSelecionadas();
    return true;
  }

  function excluirLinhasSelecionadas() {
    if (!selecaoGrade || !selecaoGrade.table) return;
    var table = selecaoGrade.table;
    var de = Math.min(selecaoGrade.r1, selecaoGrade.r2);
    var ate = Math.max(selecaoGrade.r1, selecaoGrade.r2);
    var n = ate - de + 1;
    if (!aplicarNaTabelaFonte(table, function (fonte) {
      if (fonte.rows.length - n < 1) {
        setStatus('A tabela precisa ficar com ao menos uma linha.');
        return false;
      }
      var r;
      for (r = ate; r >= de; r--) {
        if (fonte.rows[r]) fonte.rows[r].remove();
      }
      return true;
    })) return;
    setStatus(n > 1 ? 'Linhas excluídas' : 'Linha excluída');
  }

  function excluirColunasSelecionadas() {
    if (!selecaoGrade || !selecaoGrade.table) return;
    var table = selecaoGrade.table;
    var de = Math.min(selecaoGrade.c1, selecaoGrade.c2);
    var ate = Math.max(selecaoGrade.c1, selecaoGrade.c2);
    var n = ate - de + 1;
    if (!aplicarNaTabelaFonte(table, function (fonte) {
      var c;
      for (c = ate; c >= de; c--) {
        if (!excluirColunaNaTabela(fonte, c)) {
          setStatus('A tabela precisa ficar com ao menos uma coluna.');
          return false;
        }
      }
      return true;
    })) return;
    setStatus(n > 1 ? 'Colunas excluídas' : 'Coluna excluída');
  }

  function esconderMenuCelula() {
    var menu = $('#edoc-menu-celula');
    if (menu) menu.classList.remove('is-open');
  }

  function garantirMenuCelula() {
    var menu = $('#edoc-menu-celula');
    if (menu) return menu;
    menu = document.createElement('div');
    menu.id = 'edoc-menu-celula';
    menu.innerHTML = '<button type="button" data-menu-celula="vertical">Texto na vertical</button>'
      + '<button type="button" data-menu-celula="faixa">Faixa cinza</button>'
      + '<button type="button" data-menu-celula="linha">Excluir linha</button>'
      + '<button type="button" data-menu-celula="coluna">Excluir coluna</button>';
    document.body.appendChild(menu);
    menu.addEventListener('mousedown', function (e) { e.preventDefault(); e.stopPropagation(); });
    menu.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-menu-celula]');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      esconderMenuCelula();
      var acao = btn.getAttribute('data-menu-celula');
      if (acao === 'coluna') excluirColunasSelecionadas();
      else if (acao === 'vertical') alternarClasseCelulas('edoc-vert', null);
      else if (acao === 'faixa') alternarClasseCelulas('edoc-faixa', null);
      else excluirLinhasSelecionadas();
    });
    document.addEventListener('mousedown', function (e) {
      if (!menu.contains(e.target)) esconderMenuCelula();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') esconderMenuCelula();
    });
    var stage = $('.edoc-stage');
    if (stage) stage.addEventListener('scroll', esconderMenuCelula);
    return menu;
  }

  function abrirMenuCelula(e, celula) {
    var tabela = celula.closest('table');
    var grade = matrizTabela(tabela);
    var pos = indiceVisualCelula(grade, celula);
    if (!tabela || !pos) return;
    var dentro = selecaoGrade && selecaoGrade.table === tabela
      && pos.row >= Math.min(selecaoGrade.r1, selecaoGrade.r2)
      && pos.row <= Math.max(selecaoGrade.r1, selecaoGrade.r2)
      && pos.col >= Math.min(selecaoGrade.c1, selecaoGrade.c2)
      && pos.col <= Math.max(selecaoGrade.c1, selecaoGrade.c2);
    if (!dentro) definirSelecao(tabela, pos.row, pos.col, pos.row, pos.col);
    var menu = garantirMenuCelula();
    var linhas = Math.abs(selecaoGrade.r2 - selecaoGrade.r1) + 1;
    var colunas = Math.abs(selecaoGrade.c2 - selecaoGrade.c1) + 1;
    menu.querySelector('[data-menu-celula="linha"]').textContent = linhas > 1 ? 'Excluir linhas' : 'Excluir linha';
    menu.querySelector('[data-menu-celula="coluna"]').textContent = colunas > 1 ? 'Excluir colunas' : 'Excluir coluna';
    menu.classList.add('is-open');
    menu.style.left = '0px';
    menu.style.top = '0px';
    var largura = menu.offsetWidth;
    var altura = menu.offsetHeight;
    var x = Math.max(8, Math.min(e.clientX, window.innerWidth - largura - 8));
    var y = Math.max(8, Math.min(e.clientY, window.innerHeight - altura - 8));
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
  }

  function alterarTabela(body, cmd) {
    if (cmd === 'tableInsert') {
      abrirCriadorTabela(colunaAlvo());
      return;
    }
    if (cmd === 'tableMerge' && selecaoGrade && body.contains(selecaoGrade.table)) {
      mesclarRetangulo(selecaoGrade.table, selecaoGrade.r1, selecaoGrade.c1, selecaoGrade.r2, selecaoGrade.c2);
      return;
    }
    var td = celulaDaSelecao(body);
    if (!td) {
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
      if (selecaoGrade && body.contains(selecaoGrade.table)) {
        excluirLinhasSelecionadas();
        return;
      }
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
    if (!anterior || anterior.indexOf('seed-folha') < 0 || anterior.indexOf('edoc-tabela-editavel') >= 0) return novo;
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

  function ajustarColunasTabelaEditavel(body) {
    $all('table.edoc-tabela-editavel', body).forEach(function (tabela) {
      var quantidade = larguraGrade(matrizTabela(tabela));
      var grupo = tabela.querySelector('colgroup');
      if (!grupo || !quantidade) return;
      var incompleta = grupo.children.length !== quantidade || $all('col', grupo).some(function (c) { return !c.style.width; });
      if (!incompleta) return;
      var largura = /width:\s*([0-9.]+)mm/i.exec(tabela.getAttribute('style') || '');
      if (!largura) return;
      while (grupo.children.length > quantidade) grupo.lastElementChild.remove();
      while (grupo.children.length < quantidade) grupo.appendChild(document.createElement('col'));
      $all('col', grupo).forEach(function (c) { c.style.width = (parseFloat(largura[1]) / quantidade).toFixed(2) + 'mm'; });
    });
  }

  function sincronizarBloco(el, body) {
    if (!el || !body) return;
    ajustarColunasTabelaEditavel(body);
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

  function mostrarBarraInline(elNode) {
    var body = corpoDoElemento(elNode);
    var id = elNode && elNode.getAttribute('data-id');
    var path = id ? findPath(id) : null;
    if (path && path.element) atualizarLabelFonte(tamanhoFontePt(path.element, body));
  }

  function esconderBarraInline() {
    var bar = $('#edoc-inline-bar');
    if (!bar) return;
    bar.classList.remove('is-open');
    if (bar._off) { bar._off(); bar._off = null; }
  }

  function corpoVivoDoElemento(target) {
    if (!target || !paper) return null;
    if (editando && editando.id === target.id) {
      var editado = corpoDoElemento(editando.node);
      if (editado && paper.contains(editado)) return editado;
    }
    var node = paper.querySelector('.edoc-el[data-id="' + target.id + '"]');
    var body = corpoDoElemento(node);
    return body && paper.contains(body) ? body : null;
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
    ultimaCelulaEditavel = celula;
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
      if (e.key === 'Tab') {
        var atual = e.target.closest && e.target.closest('td, th');
        var tabela = atual && atual.closest('table');
        if (tabela && tabela.classList.contains('edoc-tabela-editavel')) {
          var celulas = $all('th, td', tabela);
          var indice = celulas.indexOf(atual);
          var proxima = indice + (e.shiftKey ? -1 : 1);
          if (proxima >= celulas.length) {
            inserirLinhaApos(atual);
            sincronizarBloco(path.element, body);
            celulas = $all('th, td', tabela);
          }
          if (proxima >= 0 && celulas[proxima]) {
            e.preventDefault();
            focarCelulaEditavel(celulas[proxima]);
          }
          return;
        }
      }
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
    body.oninput = function () {
      atualizarTokensDasCelulas(body);
      sincronizarBloco(path.element, body);
    };
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
      if (!(p.html || p.text)) {
        return '<div class="edoc-el-body edoc-escreva" style="' + st + '"><br></div>';
      }
      return '<div class="edoc-el-body" style="' + st + '">' + htmlTextoInterno(el, 'Texto') + '</div>';
    }
    if (t === 'html') return '<div class="edoc-html-raw edoc-el-body edoc-ajuste-folha" style="' + st + '">' + htmlTextoInterno(el) + '</div>';
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
    if (t === 'boletim' || t === 'demonstrativo_notas' || t === 'tabela_notas') {
      var bag = varsDaFolha() || {};
      var bim = parseInt(p.bimestre || 0, 10) || 0;
      var chaveLive = t === 'boletim'
        ? 'boletim_html'
        : (String(p.chave || '').trim()
          || (bim >= 1 && bim <= 4 ? ('quadro_notas_b' + bim + '_html')
            : (bim > 0 ? ('quadro_notas_p' + bim + '_html') : 'quadro_notas_html')));
      var liveQuadro = String(bag[chaveLive] || '').trim();
      var htmlBol = String((p.html || '')).trim();
      // Legado: bloco Boletim com {{quadro_notas_html}} — força boletim_html.
      if (t === 'boletim' && htmlBol && /\{\{\s*quadro_notas_html\s*\}\}/i.test(htmlBol)) {
        htmlBol = htmlBol.replace(/\{\{\s*quadro_notas_html\s*\}\}/gi, '{{boletim_html}}');
        el.props = el.props || {};
        el.props.html = htmlBol;
      }
      // Demonstrativo por bimestre: na prévia usa o HTML real do período (PP/PU/CH/PE).
      if ((t === 'demonstrativo_notas' || t === 'tabela_notas') && (state.demo || state.preview) && liveQuadro) {
        return '<div class="edoc-quadro-notas edoc-el-body" style="' + st + '">'
          + sanitizeHtml(liveQuadro) + '</div>';
      }
      // O HTML do modelo define o layout; os campos recebem os dados do aluno.
      if (!htmlBol && (state.demo || state.preview) && liveQuadro) {
        return '<div class="edoc-quadro-notas edoc-el-body" style="' + st + '">'
          + sanitizeHtml(liveQuadro) + '</div>';
      }
      if (htmlBol) {
        return '<div class="edoc-quadro-notas edoc-el-body" style="' + st + '">'
          + sanitizeHtml(ph(htmlBol)) + '</div>';
      }
      var temRegra = parseInt((state.estrutura && state.estrutura.fonte_regra_academica_id) || 0, 10) || 0;
      var fonteBol = parseInt((state.estrutura && state.estrutura.fonte_boletim_id) || 0, 10) || 0;
      var fonteDemo = parseInt((state.estrutura && state.estrutura.fonte_demonstrativo_id) || 0, 10) || 0;
      if ((t === 'boletim' || t === 'demonstrativo_notas') && temRegra <= 0 && fonteBol <= 0 && fonteDemo <= 0) {
        return '<div class="edoc-quadro-notas edoc-el-body" style="' + st + '">'
          + '<span class="edoc-ph">Escolha a regra, o curso/série e o aluno na prévia</span></div>';
      }
      return htmlBlocoHtml(
        chaveLive,
        st,
        'edoc-quadro-notas',
        (t === 'boletim' || t === 'demonstrativo_notas') ? 'Escolha regra, curso/série e aluno na prévia' : ''
      );
    }
    if (t === 'tabela_coletiva') {
      return htmlBlocoHtml('tabela_html', st, 'edoc-html-raw');
    }
    if (t === 'tabela_frequencia' || t === 'frequencia') {
      return '<div class="edoc-html-raw edoc-el-body" style="' + st + '">'
        + '<p style="margin:0 0 6px;font-weight:bold;">Frequência</p>'
        + '<p style="margin:0;">' + ph('{{frequencia_percentual}}') + '</p>'
        + htmlVar('frequencia_html') + '</div>';
    }
    if (t === 'historico') return htmlBlocoHtml('historico_html', st, 'edoc-html-raw');
    if (t === 'observacoes') {
      return '<div class="edoc-html-raw edoc-el-body" style="' + st + '">'
        + '<p style="margin:0 0 6px;font-weight:bold;">Observações</p>'
        + '<p style="margin:0;">' + ph('{{observacoes}}') + '</p></div>';
    }
    if (t === 'assinaturas') return htmlAssinaturas(el, st);
    if (t === 'dados_escola') {
      return tabelaDados([
        ['Escola', '{{escola_nome}}'],
        ['CNPJ', '{{escola_cnpj_numero}}'],
        ['Endereço', '{{escola_endereco}}'],
        ['Contato', '{{escola_docs}}']
      ], st);
    }
    if (t === 'dados_aluno' || t === 'tabela_aluno') {
      return tabelaDados([
        ['Aluno(a)', '{{aluno_nome}}'],
        ['Código', '{{aluno_codigo}}'],
        ['RA', '{{aluno_ra}}'],
        ['Turma', '{{turma_nome}}'],
        ['Série / Curso', '{{serie}} · {{curso_nome}}']
      ], st);
    }
    if (t === 'dados_responsavel') {
      return tabelaDados([
        ['Responsável', '{{resp_nome}}'],
        ['CPF', '{{resp_cpf}}'],
        ['Parentesco', '{{resp_parentesco}}'],
        ['Telefone', '{{resp_telefone}}']
      ], st);
    }
    if (t === 'dados_turma') {
      return tabelaDados([
        ['Turma', '{{turma_nome}}'],
        ['Série', '{{serie}}'],
        ['Curso', '{{curso_nome}}'],
        ['Ano letivo', '{{ano_letivo}}'],
        ['Período', '{{periodo_label}}']
      ], st);
    }
    if (t === 'resultado_final') {
      return tabelaDados([
        ['Situação final', '{{situacao_final}}'],
        ['Frequência', '{{frequencia_percentual}}']
      ], st);
    }
    return '<div class="edoc-el-body" style="' + st + '">' + htmlTextoInterno(el, labelTipo(t)) + '</div>';
  }

  function htmlVar(chave) {
    var bag = varsDaFolha();
    var v = bag && bag[chave] != null ? String(bag[chave]) : '';
    if (!v) return '';
    if (v.indexOf('<') >= 0) return sanitizeHtml(v);
    return ph(v);
  }

  function htmlBlocoHtml(chave, st, classe, vazio) {
    var miolo = htmlVar(chave);
    if (!miolo) {
      miolo = '<span class="edoc-ph">' + esc(vazio || ('{{' + chave + '}}')) + '</span>';
    }
    return '<div class="' + classe + ' edoc-el-body" style="' + st + '">' + miolo + '</div>';
  }

  function tabelaDados(pares, st) {
    var rows = '';
    pares.forEach(function (par) {
      rows += '<tr><td class="label" style="width:32%;font-weight:bold;padding:4px 8px;border:1px solid #ccc;">'
        + esc(par[0]) + '</td><td style="padding:4px 8px;border:1px solid #ccc;">' + ph(par[1]) + '</td></tr>';
    });
    return '<div class="edoc-html-raw edoc-el-body" style="' + st + '"><table class="dados" style="width:100%;border-collapse:collapse;margin:0;">'
      + rows + '</table></div>';
  }

  function htmlAssinaturas(el, st) {
    var qtd = Math.max(1, Math.min(3, parseInt((el.props || {}).quantidade, 10) || 2));
    var blocos = [
      '<p style="text-align:center;margin:28px 0 0;">________________________</p>'
        + '<p style="text-align:center;margin:4px 0 0;font-weight:bold;">' + ph('{{resp_nome}}') + '</p>'
        + '<p style="text-align:center;margin:0;">Responsável</p>',
      '<p style="text-align:center;margin:28px 0 0;">________________________</p>'
        + '<p style="text-align:center;margin:4px 0 0;font-weight:bold;">' + ph('{{assinante_nome}}') + '</p>'
        + '<p style="text-align:center;margin:0;">' + ph('{{assinante_cargo}}') + '</p>',
      '<p style="text-align:center;margin:28px 0 0;">________________________</p>'
        + '<p style="text-align:center;margin:4px 0 0;font-weight:bold;">' + ph('{{secretario_nome}}') + '</p>'
        + '<p style="text-align:center;margin:0;">Secretaria</p>'
    ];
    var larguras = qtd === 1 ? [100] : (qtd === 2 ? [50, 50] : [34, 33, 33]);
    var cols = '';
    var tds = '';
    for (var i = 0; i < qtd; i++) {
      cols += '<col style="width:' + larguras[i] + '%">';
      tds += '<td style="width:' + larguras[i] + '%;vertical-align:top;border:none;">' + blocos[i] + '</td>';
    }
    return '<div class="edoc-el-body" style="' + st + '"><table style="width:100%;border-collapse:collapse;table-layout:fixed;">'
      + '<colgroup>' + cols + '</colgroup><tr>' + tds + '</tr></table></div>';
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
      html += '<div class="edoc-col-drop">Clique para escrever</div>';
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

  function campoVariavelPorChave(chave) {
    var lista = state.estrutura.campos_variaveis || [];
    chave = String(chave || '').toLowerCase();
    for (var i = 0; i < lista.length; i++) {
      if (lista[i] && lista[i].chave === chave) return lista[i];
    }
    return null;
  }

  function slugCampoVariavel(rotulo, ignorar) {
    var base = String(rotulo || '').toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, 32);
    if (!base) base = 'campo';
    var chave = 'cv_' + base;
    var usadas = {};
    (state.estrutura.campos_variaveis || []).forEach(function (c) {
      if (c && c.chave && c.chave !== ignorar) usadas[c.chave] = true;
    });
    var tentativa = chave;
    var n = 2;
    while (usadas[tentativa]) {
      tentativa = (chave + '_' + n).slice(0, 43);
      n++;
    }
    return tentativa;
  }

  function rotuloMascara(chave) {
    var map = C.mascaras || {};
    return map[chave] || 'Texto';
  }

  function pintarCamposVariaveis() {
    var box = $('#edoc-campos-lista');
    if (!box) return;
    var lista = state.estrutura.campos_variaveis || [];
    var html = '';
    lista.forEach(function (campo) {
      html += '<div class="edoc-campo-item">'
        + '<button type="button" class="edoc-var-chip" draggable="true" data-drag-var="' + esc(campo.chave) + '" data-var-nome="' + esc(campo.rotulo) + '" title="Arraste para a folha">'
        + '<span class="edoc-var-nome">' + esc(campo.rotulo) + (campo.obrigatorio ? ' *' : '') + '</span>'
        + '<span class="edoc-var-token">{{' + esc(campo.chave) + '}} · ' + esc(rotuloMascara(campo.mascara)) + '</span>'
        + '</button>'
        + '<div class="edoc-campo-acoes">'
        + '<button type="button" data-campo-edit="' + esc(campo.chave) + '" title="Editar"><i class="fa-solid fa-pen"></i></button>'
        + '<button type="button" data-campo-del="' + esc(campo.chave) + '" title="Excluir"><i class="fa-solid fa-trash-can"></i></button>'
        + '</div></div>';
    });
    box.innerHTML = html;
    $all('[data-drag-var]', box).forEach(ligarArrasteVariavel);
  }

  var campoEditando = null;

  function abrirModalCampo(campo) {
    var modal = $('#edoc-campo-modal');
    if (!modal) return;
    campoEditando = campo ? campo.chave : null;
    var titulo = $('#edoc-campo-titulo');
    if (titulo) titulo.textContent = campo ? 'Editar campo' : 'Novo campo';
    var rotulo = $('#edoc-campo-rotulo');
    var mascara = $('#edoc-campo-mascara');
    var obrigatorio = $('#edoc-campo-obrigatorio');
    if (rotulo) rotulo.value = campo ? campo.rotulo : '';
    if (mascara) mascara.value = campo ? campo.mascara : 'texto';
    if (obrigatorio) obrigatorio.checked = campo ? !!campo.obrigatorio : true;
    modal.classList.add('open');
    if (rotulo) rotulo.focus();
  }

  function fecharModalCampo() {
    var modal = $('#edoc-campo-modal');
    if (modal) modal.classList.remove('open');
    campoEditando = null;
  }

  function salvarCampoModal() {
    var rotuloEl = $('#edoc-campo-rotulo');
    var mascaraEl = $('#edoc-campo-mascara');
    var obrigatorioEl = $('#edoc-campo-obrigatorio');
    var rotulo = rotuloEl ? rotuloEl.value.replace(/\s+/g, ' ').trim() : '';
    if (rotulo.length < 2) {
      setStatus('Informe o rótulo do campo');
      if (rotuloEl) rotuloEl.focus();
      return;
    }
    var lista = (state.estrutura.campos_variaveis || []).slice();
    var mascara = mascaraEl ? mascaraEl.value : 'texto';
    var obrigatorio = !!(obrigatorioEl && obrigatorioEl.checked);
    if (campoEditando) {
      lista = lista.map(function (c) {
        if (!c || c.chave !== campoEditando) return c;
        return { chave: c.chave, rotulo: rotulo, mascara: mascara, obrigatorio: obrigatorio };
      });
    } else {
      lista.push({
        chave: slugCampoVariavel(rotulo, null),
        rotulo: rotulo,
        mascara: mascara,
        obrigatorio: obrigatorio
      });
    }
    state.estrutura.campos_variaveis = camposVariaveisValidos(lista);
    fecharModalCampo();
    pintarCamposVariaveis();
    scheduleSave();
    setStatus('Campo salvo — arraste para a folha');
  }

  function bindCamposVariaveis() {
    var novo = $('#edoc-campo-novo');
    var modal = $('#edoc-campo-modal');
    var lista = $('#edoc-campos-lista');
    if (novo) novo.addEventListener('click', function () { abrirModalCampo(null); });
    var cancelar = $('#edoc-campo-cancelar');
    var confirmar = $('#edoc-campo-confirmar');
    if (cancelar) cancelar.addEventListener('click', fecharModalCampo);
    if (confirmar) confirmar.addEventListener('click', salvarCampoModal);
    if (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) fecharModalCampo();
      });
    }
    if (lista) {
      lista.addEventListener('click', function (e) {
        var edit = e.target.closest('[data-campo-edit]');
        var del = e.target.closest('[data-campo-del]');
        if (edit) {
          abrirModalCampo(campoVariavelPorChave(edit.getAttribute('data-campo-edit')));
          return;
        }
        if (!del) return;
        var chave = del.getAttribute('data-campo-del');
        var campo = campoVariavelPorChave(chave);
        if (!campo) return;
        if (!window.confirm('Excluir o campo “' + campo.rotulo + '”? O texto já inserido na folha permanece.')) return;
        state.estrutura.campos_variaveis = (state.estrutura.campos_variaveis || []).filter(function (c) {
          return c && c.chave !== chave;
        });
        if (!state.estrutura.campos_variaveis.length) delete state.estrutura.campos_variaveis;
        pintarCamposVariaveis();
        render();
        scheduleSave();
      });
    }
    pintarCamposVariaveis();
  }

  function render() {
    persistirEdicaoSeHouver();
    if (estruturaTemBlocoBoletim()) {
      var ajustouFolha = garantirPaisagemBoletim();
      var ajustouFonte = densificarElementosBoletim();
      if (ajustouFolha || ajustouFonte) {
        state.dirty = true;
        scheduleSave();
      }
    }
    pintarCamposVariaveis();
    var pg = mmPage();
    var stage = $('.edoc-stage');
    if (state.ajustarZoom && stage && stage.clientWidth) {
      state.zoom = Math.max(25, Math.min(150, Math.floor((stage.clientWidth - 68) / (pg.w * 96 / 25.4) * 100)));
    }
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
    paper.classList.toggle('edoc-paper-boletim', estruturaTemBlocoBoletim());
    paper.classList.toggle('edoc-rodape-fim', !!(state.estrutura.page && state.estrutura.page.rodapeNoFim));
    var fiel = JSON.stringify(state.estrutura).indexOf('seed-folha') >= 0
      || JSON.stringify(state.estrutura).indexOf('edoc-grade-livre') >= 0;
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
      if (areaOculta(pair[0])) return;
      var secs = areaOf(pair[0]).sections || [];
      if (fiel && !secs.length) return;
      var areaClass = 'edoc-area' + (pair[0] === 'footer' ? ' edoc-area-footer' : '');
      html += '<div class="' + areaClass + '" data-role="' + pair[0] + '">';
      if (!fiel) html += '<div class="edoc-area-label">' + pair[1] + '</div>';
      if (!secs.length) {
        html += '<div class="edoc-empty edoc-dropzone" data-empty="' + pair[0] + '">Arraste uma estrutura ou um elemento, ou cole com Ctrl+V</div>';
      }
      secs.forEach(function (s) { html += renderSection(s, pair[0]); });
      html += '</div>';
    });
    selecaoGrade = null;
    paper.innerHTML = html;
    desenharReguas(pg);
    avisoFolha(pg);
    bindCanvas();
    renderTree();
    renderProps();
    syncCardBoletim();
    var zl = $('#edoc-zoom-label');
    if (zl) zl.textContent = state.zoom + '%';
    atualizarLinkPdf();
    requestAnimationFrame(function () {
      ajustarQuadroNotasNaFolha();
      avisoFolha(mmPage());
      setTimeout(function () {
        ajustarQuadroNotasNaFolha();
        avisoFolha(mmPage());
      }, 80);
    });
  }

  function capturarDemoSel() {
    var turma = $('#edoc-demo-turma');
    var aluno = $('#edoc-demo-aluno');
    var fonteRegra = $('#edoc-fonte-regra');
    var fonteDemo = $('#edoc-fonte-demonstrativo');
    var fonteBol = $('#edoc-fonte-boletim');
    var tid = turma && turma.value ? String(turma.value) : '';
    var aid = aluno && aluno.value ? String(aluno.value) : '';
    var rid = fonteRegra && fonteRegra.value
      ? (parseInt(fonteRegra.value, 10) || 0)
      : (parseInt((state.estrutura && state.estrutura.fonte_regra_academica_id) || 0, 10) || 0);
    var did = fonteDemo && fonteDemo.value
      ? (parseInt(fonteDemo.value, 10) || 0)
      : (parseInt((state.estrutura && state.estrutura.fonte_demonstrativo_id) || 0, 10) || 0);
    var lid = fonteBol && fonteBol.value
      ? (parseInt(fonteBol.value, 10) || 0)
      : (parseInt((state.estrutura && state.estrutura.fonte_boletim_id) || 0, 10) || 0);
    return {
      turmaId: tid,
      alunoId: aid,
      regraId: rid,
      demonstrativoId: did,
      layoutBoletimId: lid,
      boletimId: did > 0 ? did : lid
    };
  }

  function registrarDemoSel(sel) {
    if (!sel || !sel.turmaId || !sel.alunoId) return;
    state.demoSel = {
      turmaId: String(sel.turmaId),
      alunoId: String(sel.alunoId),
      regraId: parseInt(sel.regraId, 10) || 0,
      demonstrativoId: parseInt(sel.demonstrativoId, 10) || 0,
      layoutBoletimId: parseInt(sel.layoutBoletimId, 10) || 0,
      boletimId: parseInt(sel.boletimId, 10) || 0
    };
  }

  function urlPreviewComDemo(sel) {
    if (!C.previewUrl) return '';
    var url = C.previewUrl;
    sel = sel || state.demoSel || capturarDemoSel();
    var tid = sel.turmaId || '';
    var aid = sel.alunoId || '';
    var rid = parseInt(sel.regraId, 10) || 0;
    var did = parseInt(sel.demonstrativoId, 10) || 0;
    var lid = parseInt(sel.layoutBoletimId, 10) || 0;
    var bid = parseInt(sel.boletimId, 10) || 0;
    if (!tid || !aid) return url;
    url += (url.indexOf('?') >= 0 ? '&' : '?')
      + 'turma_id=' + encodeURIComponent(tid)
      + '&aluno_id=' + encodeURIComponent(aid);
    if (rid > 0) {
      url += '&regra_academica_id=' + encodeURIComponent(String(rid));
    }
    var elPrev = acharElementoBoletim();
    var fonteTipoPrev = (elPrev && elPrev.type === 'boletim') ? 'layout' : 'demonstrativo';
    url += '&fonte_tipo=' + encodeURIComponent(fonteTipoPrev);
    if (did > 0) {
      url += '&demonstrativo_id=' + encodeURIComponent(String(did));
      url += '&boletim_id=' + encodeURIComponent(String(did));
    } else if (lid > 0) {
      url += '&layout_boletim_id=' + encodeURIComponent(String(lid));
      url += '&boletim_id=' + encodeURIComponent(String(lid));
    } else if (bid > 0) {
      url += '&boletim_id=' + encodeURIComponent(String(bid));
    }
    return url;
  }

  function atualizarLinkPdf() {
    var a = $('#edoc-pdf');
    if (!a || !C.previewUrl) return;
    // Prefere a última prévia carregada com sucesso (evita PDF da aluna errada).
    var sel = (state.demoSel && state.demoSel.alunoId) ? state.demoSel : capturarDemoSel();
    a.href = urlPreviewComDemo(sel);
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
    var papel = paper.getBoundingClientRect();
    var pxPorMm = papel.width / pg.w;
    if (!msg && pxPorMm > 0) {
      paper.querySelectorAll('.edoc-section').forEach(function (sec) {
        if (msg) return;
        var alt = sec.getBoundingClientRect().height / pxPorMm;
        if (alt > utilH + 1.5) {
          msg = 'Esta página passa ' + (alt - utilH).toFixed(1) + ' mm da área útil. Reduza a fonte, as linhas ou a margem. O PDF não encolhe.';
        }
      });
    }
    el.textContent = msg;
  }

  function lerChaveDrop(dt) {
    var chave = (dt.getData('text/edoc-var') || '').trim();
    if (chave) return chave;
    var plain = dt.getData('text/plain') || '';
    if (plain.indexOf('edoc-var:') === 0) return plain.slice(9).trim();
    return '';
  }

  function prepararArraste(e, mime, valor, plain) {
    if (!e.dataTransfer) return;
    e.dataTransfer.effectAllowed = 'copy';
    try { e.dataTransfer.setData('text/plain', plain); } catch (err) {}
    try { e.dataTransfer.setData(mime, valor); } catch (err2) {}
  }

  function dadosDoArraste(dt) {
    var layout = '';
    var tipo = '';
    var chave = '';
    var texto = '';
    var arquivo = null;
    if (dt) {
      layout = dt.getData('text/edoc-layout') || '';
      tipo = dt.getData('text/edoc-type') || '';
      chave = lerChaveDrop(dt);
      texto = dt.getData('text/edoc-texto') || '';
      var plain = dt.getData('text/plain') || '';
      if (!layout && !tipo && !chave && !texto) {
        if (plain.indexOf('edoc-layout:') === 0) layout = plain.slice(12);
        else if (plain.indexOf('edoc-type:') === 0) tipo = plain.slice(10);
        else if (plain.indexOf('edoc-texto:') === 0) texto = plain.slice(11);
      } else if (!texto && plain.indexOf('edoc-texto:') === 0) {
        texto = plain.slice(11);
      }
      if (dt.files && dt.files[0]) arquivo = dt.files[0];
    }
    return { layout: layout, tipo: tipo, chave: chave, texto: texto, arquivo: arquivo };
  }

  function largurasDoLayout(layout) {
    try {
      var widths = JSON.parse(layout);
      if (!Array.isArray(widths) || !widths.length) return null;
      return widths;
    } catch (err) {
      return null;
    }
  }

  function soltarNaRole(role, dados) {
    if (!dados) return;
    if (dados.layout) {
      var widths = largurasDoLayout(dados.layout);
      if (widths) addSection(role, widths);
      return;
    }
    if (dados.chave || dados.tipo || dados.texto) {
      addSection(role, [100]);
      var col = areaOf(role).sections.slice(-1)[0].columns[0];
      if (dados.chave) insertVariavel(col.id, dados.chave, true);
      else if (dados.texto) insertTexto(col.id, dados.texto, true);
      else insertElement(col.id, dados.tipo, true);
      return;
    }
    if (dados.arquivo) {
      addSection(role, [100]);
      var colArq = areaOf(role).sections.slice(-1)[0].columns[0];
      inserirArquivoNaColuna(colArq.id, dados.arquivo);
    }
  }

  function secaoSemElementos(section) {
    if (!section) return false;
    var cols = section.columns || [];
    if (!cols.length) return false;
    for (var i = 0; i < cols.length; i++) {
      if ((cols[i].elements || []).length) return false;
    }
    return true;
  }

  function aplicarLayoutNaSecao(section, widths) {
    section.columns = widths.map(function (w) {
      return { id: uid('c'), width: w, vAlign: 'top', elements: [] };
    });
    state.selected = { id: section.id, kind: 'section' };
    pushHist();
    render();
  }

  function soltarNaColuna(colId, dados) {
    if (!dados || !colId) return;
    if (dados.layout) {
      var widths = largurasDoLayout(dados.layout);
      var path = findPath(colId);
      if (widths && path && path.section && secaoSemElementos(path.section)) {
        aplicarLayoutNaSecao(path.section, widths);
        return;
      }
      if (widths) addSection(path ? path.role : 'body', widths);
      return;
    }
    if (dados.chave) {
      insertVariavel(colId, dados.chave);
      return;
    }
    if (dados.texto) {
      insertTexto(colId, dados.texto);
      return;
    }
    if (dados.tipo) {
      insertElement(colId, dados.tipo);
      return;
    }
    if (dados.arquivo) inserirArquivoNaColuna(colId, dados.arquivo);
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
    var tolerancia = Math.min(5, rect.width / 4);
    var direita = rect.right - e.clientX <= tolerancia && e.clientX <= rect.right + 3;
    var esquerda = e.clientX - rect.left <= tolerancia && e.clientX >= rect.left - 3;
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
    var tolerancia = Math.min(4, rect.height / 4);
    var baixo = rect.bottom - e.clientY <= tolerancia && e.clientY <= rect.bottom + 3;
    var cima = e.clientY - rect.top <= tolerancia && e.clientY >= rect.top - 3;
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
    if (celula.tagName === 'TH' && tabela && !tabela.classList.contains('edoc-tabela-editavel') && grade && pos) {
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
        var dados = dadosDoArraste(e.dataTransfer);
        var celula = celulaDoEvento(e);
        if (!celula || (!dados.chave && !dados.texto)) return;
        e.preventDefault();
        e.stopPropagation();
        marcarCelulaDrop(null);
        if (dados.chave) inserirVariavelNaCelula(celula, dados.chave);
        else inserirTextoNaCelula(celula, dados.texto);
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
    if (!paper._edocMenuCelula) {
      paper._edocMenuCelula = true;
      paper.addEventListener('contextmenu', function (e) {
        if (state.preview) return;
        var celula = e.target.closest ? e.target.closest('td, th') : null;
        if (!celula || !paper.contains(celula)) return;
        var tabela = celula.closest('table');
        if (!tabela || !tabela.closest('.edoc-html-raw, .edoc-el-body')) return;
        e.preventDefault();
        abrirMenuCelula(e, celula);
      });
    }
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
        e.stopPropagation();
        col.classList.remove('is-over');
        var celula = celulaDoEvento(e);
        marcarCelulaDrop(null);
        var dados = dadosDoArraste(e.dataTransfer);
        if (dados.chave && celula && inserirVariavelNaCelula(celula, dados.chave)) return;
        if (dados.texto && celula && inserirTextoNaCelula(celula, dados.texto)) return;
        soltarNaColuna(col.getAttribute('data-id'), dados);
      });
    });
    $all('.edoc-gutter', paper).forEach(bindGutter);
    $all('[data-empty]', paper).forEach(function (el) {
      el.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
      });
      el.addEventListener('click', function (e) {
        e.stopPropagation();
        escreverNaArea(el.getAttribute('data-empty'));
      });
      el.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        soltarNaRole(el.getAttribute('data-empty'), dadosDoArraste(e.dataTransfer));
      });
    });
    $all('.edoc-area', paper).forEach(function (area) {
      area.addEventListener('dragover', function (e) {
        if (state.preview) return;
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        area.classList.add('is-over');
      });
      area.addEventListener('dragleave', function (e) {
        if (e.relatedTarget && area.contains(e.relatedTarget)) return;
        area.classList.remove('is-over');
      });
      area.addEventListener('drop', function (e) {
        area.classList.remove('is-over');
        var alvo = e.target;
        if (alvo && alvo.closest && (alvo.closest('.edoc-col') || alvo.closest('[data-empty]'))) return;
        e.preventDefault();
        soltarNaRole(area.getAttribute('data-role') || 'body', dadosDoArraste(e.dataTransfer));
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
    if (!state.preview && !act) {
      var colVazia = e.target.closest('.edoc-col');
      if (colVazia && !e.target.closest('.edoc-el')) {
        escreverNaColuna(colVazia.getAttribute('data-id'));
        return;
      }
      var areaClique = e.target.closest('.edoc-area');
      if (areaClique && !e.target.closest('.edoc-col, .edoc-el, [data-empty]')) {
        escreverNaArea(areaClique.getAttribute('data-role') || 'body');
        return;
      }
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
    if (tipo === 'boletim') {
      el.style = { fontSize: 7.5 };
      el.props = {
        bimestre: 0,
        html: '<div class="quadro-notas-wrap doc-quadro-fit" style="font-size:7.5pt;width:100%;max-width:100%;">{{boletim_html}}</div>'
      };
    }
    if (tipo === 'demonstrativo_notas' || tipo === 'tabela_notas') {
      el.style = { fontSize: 7.5 };
      el.props = {
        bimestre: 0,
        html: '<div class="quadro-notas-wrap doc-quadro-fit" style="font-size:7.5pt;width:100%;max-width:100%;">{{quadro_notas_html}}</div>'
      };
    }
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
    return types.indexOf('text/edoc-var') >= 0 || types.indexOf('text/edoc-texto') >= 0 || types.indexOf('text/plain') >= 0;
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
    destino.setAttribute('data-edoc-token', chave);
    destino.textContent = token;
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

  function inserirTextoNaCelula(celula, texto) {
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
    destino.removeAttribute('data-edoc-token');
    destino.textContent = String(texto || '');
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
    setStatus('Texto colocado na célula');
    return true;
  }

  function insertTexto(colId, texto, skipHist) {
    var path = findPath(colId);
    if (!path || !path.column) return;
    var el = defaultElement('texto');
    el.props = { text: String(texto || '') };
    path.column.elements = path.column.elements || [];
    path.column.elements.push(el);
    state.selected = { id: el.id, kind: 'element' };
    if (!skipHist) pushHist();
    render();
  }

  function insertVariavel(colId, chave, skipHist) {
    if (chaveEhQuadroNotas(chave)) {
      var pathBol = findPath(colId);
      if (!pathBol || !pathBol.column) return;
      var bol;
      if (chave === 'boletim_html') {
        bol = defaultElement('boletim');
      } else {
        bol = defaultElement('demonstrativo_notas');
        var mBim = String(chave).match(/^quadro_notas_b([1-4])_html$/);
        bol.props = bol.props || {};
        bol.props.bimestre = mBim ? parseInt(mBim[1], 10) : 0;
        bol.props.html = '<div class="quadro-notas-wrap doc-quadro-fit" style="font-size:7.5pt;width:100%;max-width:100%;">{{' + chave + '}}</div>';
      }
      pathBol.column.elements = pathBol.column.elements || [];
      pathBol.column.elements.push(bol);
      state.selected = { id: bol.id, kind: 'element' };
      garantirPaisagemBoletim();
      if (!skipHist) pushHist();
      render();
      abrirCardBoletim(true);
      setStatus(bol.type === 'boletim'
        ? 'Boletim na folha. Escolha regra, curso/série e aluno na prévia.'
        : 'Demonstrativo na folha. Escolha regra, curso/série e aluno na prévia.');
      return;
    }
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
    if (chaveEhQuadroNotas(chave)) {
      var selBol = state.selected ? findPath(state.selected.id) : null;
      if (selBol && selBol.element && elementoEhBoletim(selBol.element)) {
        if (boletimHtmlSoDinamico((selBol.element.props || {}).html)) {
          if (!garantirHtmlBoletimEditavel(selBol.element)) garantirHtmlBoletimPadrao(selBol.element);
        }
        pushHist();
        render();
        setStatus('HTML do boletim disponível nas propriedades.');
        return;
      }
      var colBol = (selBol && selBol.column) ? selBol.column.id : primeiraColuna();
      if (!colBol) {
        addSection('body', [100]);
        colBol = areaOf('body').sections.slice(-1)[0].columns[0].id;
      }
      insertVariavel(colBol, chave);
      return;
    }
    var selecionado = state.selected && findPath(state.selected.id);
    if (selecionado && tabelaDoElemento(selecionado.element)) {
      var corpo = corpoVivoDoElemento(selecionado.element);
      var celula = corpo && celulaDaSelecao(corpo);
      if (celula) inserirVariavelNaCelula(celula, chave);
      else setStatus('Clique primeiro na célula que receberá o dado automático.');
      return;
    }
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

  function insertElement(colId, tipo, skipHist, configTabela) {
    if (tipo === 'tabela' && !configTabela) {
      abrirCriadorTabela(colId);
      return null;
    }
    var path = findPath(colId);
    if (!path || !path.column) return;
    var el = defaultElement(tipo);
    if (tipo === 'tabela' && configTabela) {
      var coluna = paper && paper.querySelector('.edoc-col[data-id="' + colId + '"]');
      var largura = coluna ? Math.max(20, (coluna.clientWidth - 4) * 25.4 / 96) : 0;
      el.props.html = htmlTabelaVazia(configTabela.linhas, configTabela.colunas, largura, configTabela.cabecalho);
    }
    path.column.elements = path.column.elements || [];
    path.column.elements.push(el);
    state.selected = { id: el.id, kind: 'element' };
    if (tipo === 'boletim' || tipo === 'demonstrativo_notas') {
      garantirPaisagemBoletim();
    }
    if (tipo === 'boletim') {
      restaurarPlaceholderBoletim(el);
    }
    if (!skipHist) pushHist();
    render();
    if (tipo === 'demonstrativo_notas') {
      abrirCardBoletim(true);
      setStatus('Demonstrativo na folha — escolha regra, curso/série e aluno na prévia');
    } else if (tipo === 'boletim') {
      abrirCardBoletim(true);
      setStatus('Boletim na folha — usa o layout criado em Acadêmico → Boletim (não o Demonstrativo).');
      try {
        if (typeof window.__edocRecarregarDemo === 'function') window.__edocRecarregarDemo();
      } catch (eRecarrega) {}
    }
    if (tipo === 'tabela') {
      var node = paper && paper.querySelector('.edoc-el[data-id="' + el.id + '"]');
      if (node) {
        marcarSelecionado(node, el.id);
        iniciarEdicaoNaFolha(node, node.querySelector('th, td'));
        setStatus('Tabela criada. Digite na célula; use Tab para ir à próxima.');
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

  function focarTexto(id) {
    var node = paper && paper.querySelector('.edoc-el[data-id="' + id + '"]');
    if (!node) return;
    marcarSelecionado(node, id);
    iniciarEdicaoNaFolha(node);
    var body = corpoDoElemento(node);
    if (!body) return;
    body.focus();
    var sel = window.getSelection();
    if (!sel) return;
    var range = document.createRange();
    range.selectNodeContents(body);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
  }

  function escreverNaColuna(colId) {
    if (state.preview) return;
    var path = findPath(colId);
    if (!path || !path.column) return;
    var els = path.column.elements || [];
    if (els.length === 1 && ehTextoEditavel(els[0].type)) {
      state.selected = { id: els[0].id, kind: 'element' };
      render();
      focarTexto(els[0].id);
      return;
    }
    if (els.length) return;
    var el = defaultElement('texto');
    el.props = { text: '' };
    path.column.elements = [el];
    state.selected = { id: el.id, kind: 'element' };
    pushHist();
    render();
    focarTexto(el.id);
    setStatus('Escreva o texto');
  }

  function escreverNaArea(role) {
    if (state.preview) return;
    role = role || 'body';
    var secs = areaOf(role).sections || [];
    var colId = null;
    secs.forEach(function (s) {
      (s.columns || []).forEach(function (c) {
        if (!colId && !(c.elements || []).length) colId = c.id;
      });
    });
    if (!colId) {
      var sec = {
        id: uid('s'),
        type: 'section',
        role: role,
        columns: [{ id: uid('c'), width: 100, vAlign: 'top', elements: [] }]
      };
      areaOf(role).sections.push(sec);
      colId = sec.columns[0].id;
    }
    escreverNaColuna(colId);
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
    var widths = largurasDoLayout(layoutJson);
    if (!widths) return;
    var path = findPath(colId);
    var role = path ? path.role : 'body';
    addSection(role, widths);
  }

  function areaOculta(area) {
    var pagina = state.estrutura.page || {};
    return (area === 'header' && pagina.ocultarCabecalho) || (area === 'footer' && pagina.ocultarRodape);
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
      if (areaOculta(pair[0])) return;
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
      var mudouBol = promoverParaBoletimSeQuadro(sel.element);
      if (elementoEhBoletim(sel.element) && garantirHtmlBoletimEditavel(sel.element)) mudouBol = true;
      if (mudouBol) {
        pushHist();
        render();
        return;
      }
      box.innerHTML = propsElement(sel.element, sel.column);
      bindProps(box, 'element', sel.element, sel.column);
      return;
    }
    if (sel.column) {
      box.innerHTML = '<label>Largura (%)</label>' + inp('width', sel.column.width, 'type="number" min="10" max="90"')
        + '<label>Alinhamento vertical</label><select data-f="vAlign"><option value="top">Topo</option><option value="middle">Meio</option><option value="bottom">Base</option></select>';
      box.querySelector('[data-f="vAlign"]').value = sel.column.vAlign || 'top';
      bindProps(box, 'column', sel.column);
      return;
    }
    var rodapeFim = sel.role === 'footer'
      ? '<label class="edoc-chk"><input type="checkbox" id="edoc-rodape-fim"' + (state.estrutura.page && state.estrutura.page.rodapeNoFim ? ' checked' : '') + '> Jogar o rodapé para o fim da página</label>'
      : '';
    box.innerHTML = '<p class="edoc-empty">Seção — use a barra para mover ou excluir.</p>'
      + rodapeFim
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
      + '<div class="edoc-sec-label">CABEÇALHO E RODAPÉ</div>'
      + '<label class="edoc-chk"><input type="checkbox" data-ocultar-area="ocultarCabecalho"' + (p.ocultarCabecalho ? ' checked' : '') + '> Ocultar cabeçalho</label>'
      + '<label class="edoc-chk"><input type="checkbox" data-ocultar-area="ocultarRodape"' + (p.ocultarRodape ? ' checked' : '') + '> Ocultar rodapé</label>'
      + '<div class="edoc-sec-label">IMAGEM DE REFERÊNCIA</div>'
      + '<p class="edoc-hint">Fundo da folha para copiar o layout da escola. A impressão só inclui a imagem se você marcar abaixo.</p>'
      + '<input type="file" id="edoc-fundo" accept="image/png,image/jpeg,image/webp,image/gif">'
      + (p.fundo ? '<button type="button" class="edoc-btn" id="edoc-fundo-limpar" style="margin-top:6px">Remover imagem</button>' : '')
      + '<label class="edoc-chk"><input type="checkbox" id="edoc-imprimir-fundo"' + (p.imprimirFundo ? ' checked' : '') + '> Imprimir a imagem no PDF</label>'
      + '<label class="edoc-chk"><input type="checkbox" id="edoc-rodape-fim"' + (p.rodapeNoFim ? ' checked' : '') + '> Rodapé no fim da página</label>';
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
    var out = '<details class="edoc-table-advanced"><summary>Medidas avançadas (mm)</summary>'
      + '<p class="edoc-hint">Passo de 0,1 mm. A impressão usa estes números, sem reduzir para caber.</p>'
      + '<div class="edoc-grade-mm">';
    med.cols.forEach(function (mm, i) {
      out += '<label>Coluna ' + (i + 1) + '</label><input type="number" min="1" max="80" step="0.1" data-mm-col="' + i + '" value="' + mm + '">';
    });
    med.rows.forEach(function (mm, i) {
      out += '<label>Linha ' + (i + 1) + '</label><input type="number" min="0.2" max="40" step="0.1" data-mm-row="' + i + '" value="' + mm + '">';
    });
    return out + '</div></details>';
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

  function indentarCssDocumento(css, nivel) {
    var linhas = [];
    var linha = '';
    var profundidade = nivel;
    var parenteses = 0;
    var aspas = '';
    var escapar = false;
    function concluirLinha() {
      if (linha.trim()) linhas.push('  '.repeat(Math.max(0, profundidade)) + linha.trim());
      linha = '';
    }
    for (var i = 0; i < css.length; i++) {
      var c = css[i];
      if (aspas) {
        linha += c;
        if (escapar) escapar = false;
        else if (c === '\\') escapar = true;
        else if (c === aspas) aspas = '';
        continue;
      }
      if (c === '"' || c === "'") { aspas = c; linha += c; continue; }
      if (c === '\\' && i + 1 < css.length) { linha += c + css[++i]; continue; }
      if (css.slice(i, i + 2) === '/*') {
        var fimComentario = css.indexOf('*/', i + 2);
        if (fimComentario < 0) { linha += css.slice(i); break; }
        linha += css.slice(i, fimComentario + 2);
        i = fimComentario + 1;
        continue;
      }
      if (css.slice(i, i + 2) === '{{') {
        var fimVariavel = css.indexOf('}}', i + 2);
        if (fimVariavel >= 0) { linha += css.slice(i, fimVariavel + 2); i = fimVariavel + 1; continue; }
      }
      if (c === '(') parenteses++;
      if (c === ')') parenteses = Math.max(0, parenteses - 1);
      if (!parenteses && c === '{') { linha = linha.trimEnd() + ' {'; concluirLinha(); profundidade++; }
      else if (!parenteses && c === '}') { concluirLinha(); profundidade = Math.max(nivel, profundidade - 1); linha = '}'; concluirLinha(); }
      else if (!parenteses && c === ';') { linha += c; concluirLinha(); }
      else linha += /\s/.test(c) ? (linha.endsWith(' ') ? '' : ' ') : c;
    }
    concluirLinha();
    return linhas.join('\n');
  }

  function indentarHtmlDocumento(html) {
    var modelo = document.createElement('template');
    modelo.innerHTML = String(html || '');
    var blocos = /^(ADDRESS|ARTICLE|ASIDE|BLOCKQUOTE|CAPTION|COLGROUP|DD|DETAILS|DIV|DL|DT|FIELDSET|FIGCAPTION|FIGURE|FOOTER|FORM|H[1-6]|HEADER|HR|LI|MAIN|NAV|OL|P|SECTION|STYLE|SUMMARY|TABLE|TBODY|TD|TFOOT|TH|THEAD|TR|UL)$/;
    function formatar(no, nivel) {
      var prefixo = '  '.repeat(nivel);
      if (no.nodeType === 3) return no.textContent.trim() ? prefixo + esc(no.textContent.trim()) : '';
      if (no.nodeType === 8) return prefixo + '<!--' + no.textContent + '-->';
      if (no.nodeType !== 1) return '';
      if (/^(PRE|TEXTAREA|SCRIPT)$/.test(no.tagName)) return prefixo + no.outerHTML;
      if (no.tagName === 'STYLE') {
        var estiloVazio = no.cloneNode(false).outerHTML;
        return prefixo + estiloVazio.slice(0, estiloVazio.lastIndexOf('</')) + '\n'
          + indentarCssDocumento(no.textContent, nivel + 1) + '\n' + prefixo + '</style>';
      }
      var filhos = Array.prototype.filter.call(no.childNodes, function (n) { return n.nodeType !== 3 || n.textContent.trim(); });
      var tokenHtml = filhos.length === 1 && filhos[0].nodeType === 3 && /^\{\{\s*[a-z0-9_]+_html\s*\}\}$/i.test(filhos[0].textContent.trim());
      var misturado = filhos.some(function (n) { return n.nodeType === 3 || (n.nodeType === 1 && !blocos.test(n.tagName)); });
      if (!filhos.length || (misturado && !tokenHtml)) return prefixo + no.outerHTML;
      var vazio = no.cloneNode(false).outerHTML;
      var fim = vazio.lastIndexOf('</');
      if (fim < 0) return prefixo + no.outerHTML;
      return prefixo + vazio.slice(0, fim) + '\n'
        + filhos.map(function (n) { return formatar(n, nivel + 1); }).filter(Boolean).join('\n')
        + '\n' + prefixo + vazio.slice(fim);
    }
    var raizMista = Array.prototype.some.call(modelo.content.childNodes, function (n) {
      return (n.nodeType === 3 && n.textContent.trim()) || (n.nodeType === 1 && !blocos.test(n.tagName));
    });
    // Espaços entre texto e elementos inline podem fazer parte do documento.
    if (raizMista) return String(html || '');
    return Array.prototype.map.call(modelo.content.childNodes, function (n) { return formatar(n, 0); }).filter(Boolean).join('\n');
  }

  var alvoHtmlExpandido = null;

  function elementoPermiteHtml(el) {
    if (!el) return false;
    if (tabelaDoElemento(el) || elementoEhBoletim(el)) return true;
    return ['titulo', 'texto', 'texto_rico', 'html', 'demonstrativo_notas', 'tabela_notas', 'frequencia', 'historico', 'resultado_final'].indexOf(el.type) >= 0;
  }

  function htmlFonteElemento(el) {
    if (!el) return '';
    var p = el.props || {};
    return String(p.html != null && p.html !== '' ? p.html : (p.text || ''));
  }

  function tituloEditorHtml(el) {
    if (!el) return 'Editar HTML';
    if (elementoEhBoletim(el)) return 'Editar HTML do boletim';
    if (tabelaDoElemento(el)) return 'Editar HTML da tabela';
    if (el.type === 'demonstrativo_notas' || el.type === 'tabela_notas') return 'Editar HTML do demonstrativo';
    return 'Editar HTML — ' + labelTipo(el.type);
  }

  function garantirPainelHtml() {
    var stage = $('#edoc-html-stage');
    if (stage) return stage;
    var centro = document.querySelector('.edoc-center');
    if (!centro) return null;
    stage = document.createElement('section');
    stage.id = 'edoc-html-stage';
    stage.className = 'edoc-html-stage';
    stage.setAttribute('aria-label', 'Editor HTML');
    stage.hidden = true;
    stage.innerHTML = '<form id="edoc-html-form" class="edoc-html-stage-form">'
      + '<header><div><h2 id="edoc-html-title">Editar HTML</h2>'
      + '<p id="edoc-html-desc">Edite o HTML do bloco selecionado.</p></div>'
      + '<div class="edoc-html-stage-actions">'
      + '<button type="button" class="edoc-btn" id="edoc-html-indent">Indentar código</button>'
      + '<button type="button" class="edoc-btn" id="edoc-html-cancel">Voltar à folha</button>'
      + '<button type="submit" class="edoc-btn edoc-btn-primary">Aplicar na folha</button>'
      + '</div></header>'
      + '<label class="edoc-sr-only" for="edoc-html-code">Código HTML</label>'
      + '<textarea id="edoc-html-code" spellcheck="false" autocapitalize="off" autocomplete="off" wrap="off"></textarea>'
      + '<footer><span>As alterações entram na folha ao clicar em Aplicar. Esc volta sem salvar.</span></footer>'
      + '</form>';
    centro.appendChild(stage);
    return stage;
  }

  function fecharHtmlExpandido() {
    var stage = $('#edoc-html-stage');
    var centro = document.querySelector('.edoc-center');
    if (stage) {
      stage.hidden = true;
      stage.setAttribute('hidden', '');
    }
    if (centro) centro.classList.remove('is-html-editing');
    alvoHtmlExpandido = null;
  }

  function abrirHtmlExpandido(target, codigoInicial) {
    if (!target) {
      setStatus('Selecione um bloco na folha para editar o HTML.');
      return;
    }
    if (!elementoPermiteHtml(target)) {
      setStatus('Este bloco não permite edição em HTML.');
      return;
    }
    try { persistirEdicaoSeHouver(); } catch (ePersist) {}
    var stage = garantirPainelHtml();
    bindHtmlExpandido();
    var codigo = $('#edoc-html-code');
    var titulo = $('#edoc-html-title');
    var desc = $('#edoc-html-desc');
    var centro = document.querySelector('.edoc-center');
    if (!stage || !codigo) {
      setStatus('Não foi possível abrir o editor HTML. Recarregue a página.');
      return;
    }
    alvoHtmlExpandido = target.id;
    var fonte = codigoInicial != null ? codigoInicial : htmlFonteElemento(target);
    try {
      codigo.value = indentarHtmlDocumento(fonte);
    } catch (eIndent) {
      codigo.value = String(fonte || '');
    }
    if (titulo) titulo.textContent = tituloEditorHtml(target);
    if (desc) {
      desc.textContent = elementoEhBoletim(target) || target.type === 'demonstrativo_notas' || target.type === 'tabela_notas'
        ? 'Os campos entre {{...}} são preenchidos para cada aluno. Edite a estrutura e os estilos à vontade.'
        : 'Edite o HTML do bloco selecionado. Variáveis {{campo}} entram automaticamente na emissão.';
    }
    stage.removeAttribute('hidden');
    stage.hidden = false;
    if (centro) centro.classList.add('is-html-editing');
    fecharInfoFolha();
    window.setTimeout(function () {
      codigo.focus();
      try { codigo.setSelectionRange(0, 0); } catch (eSel) {}
    }, 0);
    setStatus('Editor HTML aberto no centro da tela.');
  }

  function mmParaPx(mm) {
    return Math.round((Number(mm) || 0) * 96 / 25.4);
  }

  function fmtMm(n) {
    return (Math.round((Number(n) || 0) * 10) / 10).toFixed(1).replace('.', ',');
  }

  function fmtCm(n) {
    return (Math.round((Number(n) || 0)) / 10).toFixed(1).replace('.', ',');
  }

  function dimensoesPapel(size, landscape) {
    var a5 = String(size || 'A4').toUpperCase() === 'A5';
    var w = a5 ? (landscape ? 210 : 148) : (landscape ? 297 : 210);
    var h = a5 ? (landscape ? 148 : 210) : (landscape ? 210 : 297);
    return { w: w, h: h };
  }

  function htmlLinhaMedida(rotulo, w, h, utilW, utilH) {
    return '<dt>' + rotulo + '</dt><dd>'
      + '<strong>Folha:</strong> ' + fmtMm(w) + ' × ' + fmtMm(h) + ' mm'
      + ' · ' + fmtCm(w) + ' × ' + fmtCm(h) + ' cm'
      + ' · ' + mmParaPx(w) + ' × ' + mmParaPx(h) + ' px<br>'
      + '<strong>Área útil:</strong> ' + fmtMm(utilW) + ' × ' + fmtMm(utilH) + ' mm'
      + ' · ' + fmtCm(utilW) + ' × ' + fmtCm(utilH) + ' cm'
      + ' · ' + mmParaPx(utilW) + ' × ' + mmParaPx(utilH) + ' px'
      + '</dd>';
  }

  function htmlInfoFolha() {
    var page = (state.estrutura && state.estrutura.page) || {};
    var size = String(page.size || 'A4').toUpperCase();
    var m = page.margin || {};
    var mt = Number(m.top != null ? m.top : 15);
    var mr = Number(m.right != null ? m.right : 15);
    var mb = Number(m.bottom != null ? m.bottom : 15);
    var ml = Number(m.left != null ? m.left : 15);
    var port = dimensoesPapel(size, false);
    var land = dimensoesPapel(size, true);
    var utilPortW = Math.max(0, port.w - ml - mr);
    var utilPortH = Math.max(0, port.h - mt - mb);
    var utilLandW = Math.max(0, land.w - ml - mr);
    var utilLandH = Math.max(0, land.h - mt - mb);
    var atual = (page.orientation || 'portrait') === 'landscape' ? 'paisagem' : 'retrato';
    return '<h3>Medidas da folha (' + size + ')</h3>'
      + '<p>Referência para o PDF de impressão. Pixels em 96 dpi CSS (mesmo padrão da prévia).</p>'
      + '<dl>'
      + htmlLinhaMedida('Vertical (retrato)' + (atual === 'retrato' ? ' — atual' : ''), port.w, port.h, utilPortW, utilPortH)
      + htmlLinhaMedida('Horizontal (paisagem)' + (atual === 'paisagem' ? ' — atual' : ''), land.w, land.h, utilLandW, utilLandH)
      + '</dl>'
      + '<p class="edoc-folha-info-note">Margens atuais: topo ' + fmtMm(mt) + ' · direita ' + fmtMm(mr)
      + ' · baixo ' + fmtMm(mb) + ' · esquerda ' + fmtMm(ml) + ' mm. A área útil é o espaço disponível para o conteúdo.</p>';
  }

  function fecharInfoFolha() {
    var pop = $('#edoc-folha-info-pop');
    var btn = $('#edoc-folha-info');
    if (pop) pop.hidden = true;
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  function abrirInfoFolha() {
    var pop = $('#edoc-folha-info-pop');
    var btn = $('#edoc-folha-info');
    if (!pop || !btn) return;
    pop.innerHTML = htmlInfoFolha();
    pop.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
  }

  function bindInfoFolha() {
    var btn = $('#edoc-folha-info');
    var pop = $('#edoc-folha-info-pop');
    if (!btn || !pop || btn._edocInfo) return;
    btn._edocInfo = true;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (pop.hidden) abrirInfoFolha();
      else fecharInfoFolha();
    });
    document.addEventListener('click', function (e) {
      if (!pop.hidden && !pop.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
        fecharInfoFolha();
      }
    });
  }

  function bindHtmlExpandido() {
    var stage = garantirPainelHtml();
    var codigo = $('#edoc-html-code');
    if (!stage || !codigo || stage._edocHtmlBound) return;
    stage._edocHtmlBound = true;
    var btnCancel = $('#edoc-html-cancel');
    var btnIndent = $('#edoc-html-indent');
    var form = $('#edoc-html-form');
    if (btnCancel) btnCancel.addEventListener('click', function () { fecharHtmlExpandido(); });
    if (btnIndent) {
      btnIndent.addEventListener('click', function () {
        try { codigo.value = indentarHtmlDocumento(codigo.value); } catch (eIndent) {}
        codigo.focus();
      });
    }
    codigo.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        fecharHtmlExpandido();
        return;
      }
      if (e.key !== 'Tab' || e.shiftKey) return;
      e.preventDefault();
      var inicio = codigo.selectionStart;
      codigo.setRangeText('  ', inicio, codigo.selectionEnd, 'end');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || stage.hidden) return;
      if (document.activeElement === codigo) return;
      fecharHtmlExpandido();
    });
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var caminho = findPath(alvoHtmlExpandido);
        if (!caminho || !caminho.element) {
          fecharHtmlExpandido();
          return;
        }
        var el = caminho.element;
        el.props = el.props || {};
        el.props.html = codigo.value;
        delete el.props.text;
        fecharHtmlExpandido();
        pushHist();
        render();
        setStatus('HTML aplicado na folha. Use Ver resultado para conferir o PDF.');
      });
    }
  }

  function bindBotaoEditarHtml() {
    var props = $('#edoc-props');
    if (!props || props._edocHtmlBtn) return;
    props._edocHtmlBtn = true;
    // mousedown: evita perder o clique quando o contenteditable da folha/RTE perde o foco e recria o painel.
    props.addEventListener('mousedown', function (e) {
      var btn = e.target.closest('#edoc-editar-html, #edoc-html-expand');
      if (!btn || !props.contains(btn)) return;
      e.preventDefault();
    });
    props.addEventListener('click', function (e) {
      var btn = e.target.closest('#edoc-editar-html, #edoc-html-expand');
      if (!btn || !props.contains(btn)) return;
      e.preventDefault();
      e.stopPropagation();
      var sel = state.selected ? findPath(state.selected.id) : null;
      var alvo = sel && sel.element ? sel.element : null;
      if (!alvo) {
        setStatus('Selecione um bloco na folha para editar o HTML.');
        return;
      }
      if (btn.id === 'edoc-html-expand' || btn.id === 'edoc-editar-html') {
        abrirHtmlExpandido(alvo);
      }
    });
  }

  function propsElement(el, coluna) {
    var p = el.props || {};
    var st = el.style || {};
    var html = '<div class="edoc-sec-label">' + labelTipo(el.type).toUpperCase() + '</div>';
    if (tabelaDoElemento(el)) {
      html = '<div class="edoc-sec-label">TABELA</div>'
        + '<p class="edoc-hint">Edite diretamente na folha: clique na célula e digite. Use Tab para avançar. Para mesclar, arraste sobre as células desejadas.</p>'
        + htmlAcoesTabela()
        + '<div class="edoc-sec-label">TEXTO DAS CÉLULAS</div>'
        + '<div class="edoc-rte-bar">' + htmlBarraFmt(false, true) + '</div>'
        + '<button type="button" class="edoc-btn" id="edoc-insert-var" style="margin-top:6px">Inserir dado automático</button>'
        + painelMedidasFolha(p.html || '');
    } else if (el.type === 'titulo' || el.type === 'texto' || el.type === 'texto_rico' || el.type === 'html') {
      html += '<div class="edoc-sec-label">FORMATO</div>';
      html += '<div class="edoc-rte-bar">' + htmlBarraFmt(true) + '</div>';
      html += '<label>Conteúdo</label>';
      html += '<div class="edoc-rte-wrap">'
        + '<div class="edoc-rte" contenteditable="true" role="textbox" aria-label="Conteúdo do texto" aria-multiline="true" spellcheck="true"></div>'
        + '</div>';
      html += '<div class="edoc-prop-row" style="margin-top:6px;gap:6px;">'
        + '<button type="button" class="edoc-btn" id="edoc-insert-var">{ } Variáveis</button>'
        + '<button type="button" class="edoc-btn" id="edoc-editar-html" title="Abre o editor HTML no centro da tela"><i class="fa-solid fa-code" aria-hidden="true"></i> Editar em HTML</button>'
        + '</div>';
    }
    if (elementoEhBoletim(el)) {
      abrirCardBoletim(false);
      if (!String((p.html || '')).trim()) {
        garantirHtmlBoletimPadrao(el);
        p = el.props || {};
      }
      var soDin = boletimHtmlSoDinamico(p.html);
      html += soDin
        ? '<p class="edoc-hint">Este bloco ainda usa <code>{{quadro_notas_html}}</code>. Na prévia, escolha regra de aprovação, curso/série e aluno para carregar a tabela.</p>'
        : '<p class="edoc-hint">Edite o HTML no centro da tela. Os campos <code>{{boletim_celula_…}}</code> recebem as notas de cada aluno.</p>';
      html += '<div class="edoc-prop-row" style="margin-bottom:6px;">'
        + '<button type="button" class="edoc-btn" id="edoc-boletim-html-dinamico" title="Wrapper com variável dinâmica">HTML dinâmico</button>'
        + '<button type="button" class="edoc-btn" id="edoc-boletim-html-previa" title="Editar a estrutura completa com notas dinâmicas">Expor tabela completa</button>'
        + '</div>';
      html += '<div class="edoc-html-label"><label for="edoc-boletim-html">HTML do boletim</label>'
        + '<button type="button" class="edoc-btn" id="edoc-html-expand" title="Abre o editor HTML no centro da tela"><i class="fa-solid fa-code" aria-hidden="true"></i> Editar em HTML</button></div>';
      html += '<textarea data-f="html" id="edoc-boletim-html" class="edoc-html-src" rows="14" spellcheck="false">'
        + esc(indentarHtmlDocumento(p.html || '')) + '</textarea>';
    }
    if (elementoPermiteHtml(el) && !elementoEhBoletim(el)
      && el.type !== 'titulo' && el.type !== 'texto' && el.type !== 'texto_rico' && el.type !== 'html') {
      html += '<button type="button" class="edoc-btn" id="edoc-editar-html" style="margin:8px 0;width:100%" title="Abre o editor HTML no centro da tela">'
        + '<i class="fa-solid fa-code" aria-hidden="true"></i> Editar em HTML</button>';
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
    if (coluna) {
      html += '<label>Vertical na linha</label><div class="edoc-align">'
        + posBtns('colVAlign', coluna.vAlign || 'top', [
          ['top', 'fa-arrow-up', 'Topo'],
          ['middle', 'fa-arrows-up-down', 'Centro'],
          ['bottom', 'fa-arrow-down', 'Base']
        ]) + '</div>';
    }
    if (el.type !== 'logo' && el.type !== 'imagem') {
      var minPt = (el.type === 'boletim' || el.type === 'demonstrativo_notas' || el.type === 'tabela_notas') ? '4' : '8';
      html += '<div class="edoc-sec-label">TIPOGRAFIA</div><div class="edoc-prop-row">'
        + '<div><label>Tamanho (pt)</label>' + inp('fontSize', st.fontSize || '', 'type="number" min="' + minPt + '" max="72" step="0.5"') + '</div>'
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

  function ligarRodapeNoFim(box) {
    var fim = box.querySelector('#edoc-rodape-fim');
    if (!fim) return;
    fim.addEventListener('change', function () {
      state.estrutura.page = state.estrutura.page || {};
      state.estrutura.page.rodapeNoFim = !!fim.checked;
      pushHist();
      render();
    });
  }

  function bindProps(box, kind, target, coluna) {
    if (kind === 'page') {
      var p = state.estrutura.page;
      $all('[data-ocultar-area]', box).forEach(function (campo) {
        campo.addEventListener('change', function () {
          p[campo.getAttribute('data-ocultar-area')] = campo.checked;
          pushHist();
          render();
        });
      });
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
      ligarRodapeNoFim(box);
      return;
    }
    ligarRodapeNoFim(box);
    $all('[data-f]', box).forEach(function (f) {
      f.addEventListener('change', function () {
        // Não recriar o botão Expandir entre o blur do HTML e seu clique.
        if (f.id === 'edoc-boletim-html') { applyField(kind, target, f, true); pushHist(); }
        else applyField(kind, target, f);
      });
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
        var campo = b.getAttribute('data-pos-field');
        if (campo === 'colVAlign' && coluna) {
          coluna.vAlign = b.getAttribute('data-pos-val');
          pushHist(); render();
          return;
        }
        target.props = target.props || {};
        target.props[campo] = b.getAttribute('data-pos-val');
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
    if (kind === 'element' && elementoEhBoletim(target)) {
      var btnDin = $('#edoc-boletim-html-dinamico', box);
      var btnPrev = $('#edoc-boletim-html-previa', box);
      if (btnDin) {
        btnDin.addEventListener('click', function () {
          garantirHtmlBoletimPadrao(target);
          sincronizarHtmlBoletimComEstilo(target);
          pushHist();
          render();
          setStatus('HTML dinâmico com {{quadro_notas_html}}');
        });
      }
      if (btnPrev) {
        btnPrev.addEventListener('click', function () {
          var existente = String((target.props || {}).html || '');
          if (existente.indexOf('{{boletim_celula_') >= 0) {
            abrirHtmlExpandido(target);
            return;
          }
          var modelo = String(C.modeloQuadroHtml || '');
          if (!modelo || modelo.indexOf('{{boletim_celula_') < 0) {
            setStatus('Escolha o demonstrativo e um aluno com notas para expor a tabela completa.');
            abrirCardBoletim(true);
            return;
          }
          // A estrutura contém campos dinâmicos, nunca as notas do aluno da prévia.
          abrirHtmlExpandido(target, '<div class="quadro-notas-wrap doc-quadro-fit">' + modelo + '</div>');
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
        || cmd === 'tableSelCol' || cmd === 'insertImg' || cmd === 'cellVertical' || cmd === 'cellFaixa') return;
      var on = false;
      try { on = document.queryCommandState(cmd); } catch (err) { on = false; }
      b.classList.toggle('active', !!on);
    });
  }

  function bindRte(box, target) {
    if (tabelaDoElemento(target)) { bindAcoesTabela(box, target); return; }
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
      if (!rte.isConnected) return;
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
        var folha = corpoVivoDoElemento(target);
        if (folha) {
          executarFmt(b.getAttribute('data-fmt'), target, folha);
          if (folha.isConnected) sincronizarBloco(target, folha);
          atualizarLabelFonte(tamanhoFontePt(target, folha));
          atualizarBotoesRte(box);
          return;
        }
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
        if (target.type === 'html' || elementoEhBoletim(target) || (String(val).indexOf('<') >= 0)) {
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
        if (name === 'fontSize') {
          var ptFs = parseFloat(String(val).replace(',', '.'));
          target.style[name] = isFinite(ptFs) ? ptFs : 0;
        } else {
          target.style[name] = parseInt(val, 10) || 0;
        }
      } else if (['fontWeight', 'color', 'borderStyle', 'borderColor'].indexOf(name) >= 0) {
        target.style[name] = val;
      } else if (name === 'hideIfEmpty') {
        target.hideIfEmpty = !!val;
      } else {
        target.props[name] = val;
      }
      if (elementoEhBoletim(target) && (name === 'fontSize' || name.indexOf('m_') === 0 || name.indexOf('p_') === 0)) {
        sincronizarHtmlBoletimComEstilo(target);
      }
    }
    if (!silent) { pushHist(); render(); }
    else {
      state.dirty = true;
      scheduleSave();
      var node = paper && paper.querySelector('[data-id="' + target.id + '"]');
      if (node && (name === 'text' || name === 'html')) {
        var inner = corpoDoElemento(node);
        if (inner) {
          if (elementoEhBoletim(target)) {
            inner.innerHTML = sanitizeHtml(ph(String((target.props || {}).html || '')));
            requestAnimationFrame(ajustarQuadroNotasNaFolha);
          } else {
            inner.innerHTML = htmlTextoInterno(target);
          }
        }
      } else if (elementoEhBoletim(target) && (name === 'fontSize' || name.indexOf('m_') === 0 || name.indexOf('p_') === 0)) {
        var taBol = document.querySelector('#edoc-boletim-html');
        if (taBol) taBol.value = (target.props || {}).html || '';
        atualizarCanvasBoletim(target);
      }
    }
  }

  function openVars(target, rte) {
    var modal = $('#edoc-vars');
    var corpoTabela = tabelaDoElemento(target) ? corpoVivoDoElemento(target) : null;
    var celulaDestino = corpoTabela && celulaDaSelecao(corpoTabela);
    modal.classList.add('open');
    modal.onclick = function (e) { if (e.target === modal) modal.classList.remove('open'); };
    $all('[data-var]', modal).forEach(function (b) {
      b.onclick = function () {
        var token = tokenDaChave(b.getAttribute('data-var'));
        modal.classList.remove('open');
        if (corpoTabela) {
          if (celulaDestino && celulaDestino.isConnected) inserirVariavelNaCelula(celulaDestino, b.getAttribute('data-var'));
          else setStatus('Clique primeiro na célula que receberá o dado automático.');
          return;
        }
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
    if (!window.confirm('Montar o layout organizado do boletim? O conteúdo atual do cabeçalho, do corpo e do rodapé será substituído. A folha passa para A4 paisagem (como o boletim impresso).')) {
      return;
    }
    var camposMantidos = camposVariaveisValidos(state.estrutura.campos_variaveis);
    state.estrutura = normalizarEstrutura(clone(C.layoutSugerido));
    garantirPaisagemBoletim();
    if (camposMantidos.length) state.estrutura.campos_variaveis = camposMantidos;
    state.selected = null;
    pushHist();
    render();
    scheduleSave();
    setStatus('Layout do boletim aplicado em paisagem — confira e salve');
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
    if (/1127/i.test(cod)) return '1127';
    return '1127a';
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

  function elementoEhBoletim(el) {
    if (!el) return false;
    if (el.type === 'boletim' || el.type === 'demonstrativo_notas' || el.type === 'tabela_notas') return true;
    var raw = String((el.props || {}).html || (el.props || {}).text || '');
    return raw.indexOf('quadro_notas_html') >= 0
      || raw.indexOf('boletim_html') >= 0
      || raw.indexOf('{{boletim_celula_') >= 0;
  }

  function chaveQuadroDoElemento(el) {
    if (!el) return 'quadro_notas_html';
    if (el.type === 'boletim') return 'boletim_html';
    var bim = parseInt((el.props && el.props.bimestre) || 0, 10) || 0;
    if (bim > 0) return 'quadro_notas_b' + bim + '_html';
    return 'quadro_notas_html';
  }

  /** Garante que blocos Boletim usem só {{boletim_html}} (nunca quadro/demonstrativo). */
  function restaurarPlaceholderBoletim(el) {
    if (!el || el.type !== 'boletim') return false;
    el.props = el.props || {};
    el.props.bimestre = 0;
    var html = String(el.props.html || '');
    var soBoletimToken = boletimHtmlSoDinamico(html) && /\{\{\s*boletim_html\s*\}\}/i.test(html)
      && !/\{\{\s*quadro_notas(?:_b[1-4])?_html\s*\}\}/i.test(html);
    if (soBoletimToken) return false;
    garantirHtmlBoletimPadrao(el);
    return true;
  }

  function restaurarTodosPlaceholdersBoletim() {
    var mudou = false;
    ['header', 'body', 'footer'].forEach(function (role) {
      (areaOf(role).sections || []).forEach(function (sec) {
        (sec.columns || []).forEach(function (col) {
          (col.elements || []).forEach(function (el) {
            if (restaurarPlaceholderBoletim(el)) mudou = true;
          });
        });
      });
    });
    return mudou;
  }

  function acharElementoBoletim() {
    var roles = ['header', 'body', 'footer'];
    for (var r = 0; r < roles.length; r++) {
      var secs = (areaOf(roles[r]).sections || []);
      for (var i = 0; i < secs.length; i++) {
        var cols = secs[i].columns || [];
        for (var j = 0; j < cols.length; j++) {
          var els = cols[j].elements || [];
          for (var k = 0; k < els.length; k++) {
            if (elementoEhBoletim(els[k])) return els[k];
          }
        }
      }
    }
    return null;
  }

  function estruturaTemBlocoBoletim() {
    return !!acharElementoBoletim();
  }

  function garantirBlocoBoletimNaFolha() {
    var existente = acharElementoBoletim();
    if (existente) {
      if (garantirPaisagemBoletim()) {
        pushHist();
        render();
      }
      state.selected = { id: existente.id, kind: 'element' };
      return existente;
    }
    // Não auto-insere: Boletim e cada bimestre do Demonstrativo entram só por clique/arraste.
    return null;
  }

  function abrirCardBoletim(focar) {
    var card = $('#edoc-boletim-card');
    if (!card) return;
    if (estruturaTemBlocoBoletim() && garantirPaisagemBoletim()) {
      pushHist();
      render();
    }
    card.hidden = false;
    var status = $('#edoc-demo-status');
    if (status && !status.textContent) {
      status.textContent = 'Escolha o demonstrativo e a aluna para ver o quadro na folha.';
    }
    if (focar) {
      card.open = true;
      var fonte = $('#edoc-fonte-regra') || $('#edoc-fonte-demonstrativo') || $('#edoc-fonte-boletim');
      if (fonte) {
        try { fonte.focus(); } catch (e) {}
      }
      try { card.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (e2) {}
    }
  }

  function syncCardBoletim() {
    var card = $('#edoc-boletim-card');
    if (!card) return;
    var sel = state.selected ? findPath(state.selected.id) : null;
    if ((sel && sel.element && elementoEhBoletim(sel.element))
      || estruturaTemBlocoBoletim()
      || (state.estrutura && (state.estrutura.fonte_boletim_id
        || state.estrutura.fonte_demonstrativo_id
        || state.estrutura.fonte_regra_academica_id))) {
      card.hidden = false;
    }
  }

  function bindDemonstracao() {
    var box = $('#edoc-demo');
    var card = $('#edoc-boletim-card');
    var turma = $('#edoc-demo-turma');
    var aluno = $('#edoc-demo-aluno');
    var anoSel = $('#edoc-demo-ano');
    var fonteRegra = $('#edoc-fonte-regra');
    var fonteDemonstrativo = $('#edoc-fonte-demonstrativo');
    var fonteBoletim = $('#edoc-fonte-boletim');
    var real = $('#edoc-demo-real');
    var status = $('#edoc-demo-status');
    var comps = $('#edoc-demo-comps');
    if (!turma || !aluno || !C.demoUrl) return;
    if (estruturaTemBlocoBoletim()
      || (state.estrutura && (state.estrutura.fonte_boletim_id
        || state.estrutura.fonte_demonstrativo_id
        || state.estrutura.fonte_regra_academica_id))) {
      abrirCardBoletim(false);
    }
    var req = 0;
    var demoPacote = { componentes: [], periodos: [], html_quadro: '', turmas: [], regras: [] };
    function regraSelecionadaId() {
      var doSelect = fonteRegra ? (parseInt(fonteRegra.value || '0', 10) || 0) : 0;
      if (doSelect > 0) return doSelect;
      return parseInt((state.estrutura && state.estrutura.fonte_regra_academica_id) || 0, 10) || 0;
    }
    function demonstrativoSelecionadoId() {
      var doSelect = fonteDemonstrativo ? (parseInt(fonteDemonstrativo.value || '0', 10) || 0) : 0;
      if (doSelect > 0) return doSelect;
      return parseInt((state.estrutura && state.estrutura.fonte_demonstrativo_id) || 0, 10) || 0;
    }
    function layoutBoletimSelecionadoId() {
      var doSelect = fonteBoletim ? (parseInt(fonteBoletim.value || '0', 10) || 0) : 0;
      if (doSelect > 0) return doSelect;
      return parseInt((state.estrutura && state.estrutura.fonte_boletim_id) || 0, 10) || 0;
    }
    function boletimSelecionadoId() {
      var did = demonstrativoSelecionadoId();
      if (did > 0) return did;
      return layoutBoletimSelecionadoId();
    }
    function gravarFonteRegra(id) {
      id = parseInt(id, 10) || 0;
      if (id > 0) state.estrutura.fonte_regra_academica_id = id;
      else delete state.estrutura.fonte_regra_academica_id;
      state.dirty = true;
      scheduleSave();
    }
    function gravarFonteDemonstrativo(id) {
      id = parseInt(id, 10) || 0;
      if (id > 0) state.estrutura.fonte_demonstrativo_id = id;
      else delete state.estrutura.fonte_demonstrativo_id;
      state.dirty = true;
      scheduleSave();
    }
    function gravarFonteBoletim(id) {
      id = parseInt(id, 10) || 0;
      if (id > 0) state.estrutura.fonte_boletim_id = id;
      else delete state.estrutura.fonte_boletim_id;
      state.dirty = true;
      scheduleSave();
    }
    function pintarOpcoesFonte(select, lista, atual, vazio) {
      if (!select) return;
      var html = '<option value="">' + esc(vazio || 'Selecione…') + '</option>';
      (lista || []).forEach(function (b) {
        var id = Number(b.id || 0);
        if (!id) return;
        var rotulo = b.rotulo || b.nome || ('#' + id);
        html += '<option value="' + id + '"' + (id === atual ? ' selected' : '') + '>' + esc(rotulo) + '</option>';
      });
      select.innerHTML = html;
      if (atual > 0) select.value = String(atual);
    }
    function idExisteNaLista(lista, id) {
      id = parseInt(id, 10) || 0;
      if (id <= 0) return false;
      return (lista || []).some(function (b) { return Number(b.id || 0) === id; });
    }
    function pintarRegras(lista) {
      if (lista) demoPacote.regras = lista;
      pintarOpcoesFonte(fonteRegra, demoPacote.regras || [], regraSelecionadaId(), 'Selecione a regra de aprovação');
    }
    function inserirDemonstrativoPeriodo(bimestre, rotulo, chave) {
      bimestre = parseInt(bimestre, 10) || 0;
      chave = chaveDemonstrativoPeriodo(bimestre, chave);
      var colId = garantirColunaCorpo();
      var path = findPath(colId);
      if (!path || !path.column) return;
      var el = defaultElement('demonstrativo_notas');
      el.props = el.props || {};
      el.props.bimestre = bimestre;
      el.props.chave = chave;
      el.props.html = '<div class="quadro-notas-wrap doc-quadro-fit" style="font-size:7.5pt;width:100%;max-width:100%;">{{' + chave + '}}</div>';
      path.column.elements = path.column.elements || [];
      path.column.elements.push(el);
      state.selected = { id: el.id, kind: 'element' };
      garantirPaisagemBoletim();
      pushHist();
      render();
      abrirCardBoletim(true);
      setStatus((rotulo || 'Demonstrativo') + ' na folha — quadro do período configurado no Demonstrativo.');
      try {
        if (typeof window.__edocRecarregarDemo === 'function') window.__edocRecarregarDemo();
      } catch (eRecarregaDemo) {}
    }
    function fecharMenuDemonstrativo() {
      var picker = $('#edoc-demo-picker');
      var menu = $('#edoc-demo-menu');
      var btn = $('#edoc-demo-abrir');
      if (menu) menu.hidden = true;
      if (picker) picker.classList.remove('is-open');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }
    function abrirMenuDemonstrativo() {
      var picker = $('#edoc-demo-picker');
      var menu = $('#edoc-demo-menu');
      var btn = $('#edoc-demo-abrir');
      if (!menu || !btn) return;
      menu.hidden = false;
      if (picker) picker.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
    }
    function pintarPeriodosDemonstrativo(lista) {
      var boxPeriodos = $('#edoc-periodos-chips');
      var hint = $('#edoc-periodos-hint');
      var btnAbrir = $('#edoc-demo-abrir');
      if (!boxPeriodos) return;
      lista = lista || [];
      demoPacote.periodos_demonstrativo = lista;
      if (!lista.length) {
        boxPeriodos.innerHTML = '';
        if (hint) {
          hint.hidden = false;
          hint.textContent = 'Escolha a regra de aprovação na prévia para listar 1º, 2º bimestre…';
        }
        return;
      }
      if (hint) hint.hidden = true;
      var html = '';
      lista.forEach(function (p) {
        var n = Number(p.n || 0);
        if (n <= 0) return;
        var rotulo = p.rotulo || (n + 'º período');
        var chave = chaveDemonstrativoPeriodo(n, p.chave);
        html += '<button type="button" class="edoc-demo-opcao" role="option" data-demo-bimestre="' + n + '" data-demo-chave="' + esc(chave) + '" data-demo-rotulo="' + esc(rotulo) + '" title="Inserir ' + esc(rotulo) + ' na folha">'
          + '<strong>' + esc(rotulo) + '</strong>'
          + '<small>{{' + esc(chave) + '}}</small></button>';
      });
      boxPeriodos.innerHTML = html;
      $all('[data-demo-bimestre]', boxPeriodos).forEach(function (btn) {
        btn.addEventListener('click', function () {
          inserirDemonstrativoPeriodo(
            btn.getAttribute('data-demo-bimestre'),
            btn.getAttribute('data-demo-rotulo') || btn.textContent,
            btn.getAttribute('data-demo-chave')
          );
          fecharMenuDemonstrativo();
        });
      });
    }
    function ligarSeletorDemonstrativo() {
      var btnAbrir = $('#edoc-demo-abrir');
      var menu = $('#edoc-demo-menu');
      var picker = $('#edoc-demo-picker');
      if (!btnAbrir || !menu || btnAbrir._edocDemoPicker) return;
      btnAbrir._edocDemoPicker = true;
      btnAbrir.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (menu.hidden) {
          if (!(demoPacote.periodos_demonstrativo || []).length) {
            abrirCardBoletim(true);
            setStatus('Escolha a regra de aprovação na prévia para listar os bimestres.');
          }
          abrirMenuDemonstrativo();
        } else {
          fecharMenuDemonstrativo();
        }
      });
      document.addEventListener('click', function (e) {
        if (!picker) return;
        if (picker.contains(e.target)) return;
        fecharMenuDemonstrativo();
      });
    }
    function pintarDemonstrativos(lista) {
      var atual = demonstrativoSelecionadoId();
      if (atual <= 0 && state.estrutura && state.estrutura.fonte_boletim_id) {
        var legado = parseInt(state.estrutura.fonte_boletim_id, 10) || 0;
        if (idExisteNaLista(lista, legado)) {
          atual = legado;
          state.estrutura.fonte_demonstrativo_id = legado;
        }
      }
      pintarOpcoesFonte(fonteDemonstrativo, lista, atual, 'Selecione o demonstrativo de notas');
    }
    function pintarLayoutsBoletim(lista) {
      var atual = layoutBoletimSelecionadoId();
      if (atual > 0 && !idExisteNaLista(lista, atual)) {
        atual = 0;
        if (state.estrutura && state.estrutura.fonte_boletim_id && !state.estrutura.fonte_demonstrativo_id) {
          delete state.estrutura.fonte_boletim_id;
        } else if (state.estrutura && state.estrutura.fonte_demonstrativo_id
          && Number(state.estrutura.fonte_boletim_id) === Number(state.estrutura.fonte_demonstrativo_id)) {
          delete state.estrutura.fonte_boletim_id;
        }
      }
      pintarOpcoesFonte(fonteBoletim, lista, atual, 'Selecione o boletim criado');
    }
    function pintarAnos(turmasLista) {
      if (!anoSel) return;
      var anos = {};
      (turmasLista || []).forEach(function (t) {
        var ano = Number(t.ano_letivo || 0);
        if (ano > 0) anos[ano] = true;
      });
      var lista = Object.keys(anos).map(Number).sort(function (a, b) { return b - a; });
      var atual = anoSel.value || '';
      var html = '<option value="">Todos</option>';
      lista.forEach(function (ano) {
        html += '<option value="' + ano + '"' + (String(ano) === String(atual) ? ' selected' : '') + '>' + ano + '</option>';
      });
      anoSel.innerHTML = html;
      if (atual) anoSel.value = String(atual);
    }
    function pintarBoletins(lista) {
      // Compat: se a API antiga só mandar boletins, usa como demonstrativos.
      pintarDemonstrativos(lista);
    }
    function mapaGlossario() {
      var mapa = {};
      glossarioValido(state.estrutura && state.estrutura.glossario).forEach(function (item) {
        mapa[item.slug] = item.sigla;
      });
      return mapa;
    }
    function siglaDe(comp) {
      var mapa = mapaGlossario();
      return (mapa[comp.slug] || comp.sigla || comp.nome || '').trim();
    }
    function usarSigla() {
      var caixa = $('#edoc-demo-sigla');
      return !!(caixa && caixa.checked);
    }
    function rotuloDisc(comp) {
      return usarSigla() ? siglaDe(comp) : (comp.nome || siglaDe(comp));
    }
    function guardarSigla(slug, nome, sigla) {
      sigla = String(sigla || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 12);
      if (!sigla) return;
      var lista = glossarioValido(state.estrutura.glossario).filter(function (item) {
        return item.slug !== slug;
      });
      lista.push({ slug: slug, nome: nome, sigla: sigla });
      state.estrutura.glossario = lista;
      state.dirty = true;
      scheduleSave();
    }
    function celulaToken(chave) {
      return '<td data-edoc-token="' + esc(chave) + '" style="border:0.4px solid #222;padding:2px;vertical-align:middle;text-align:center;">{{' + esc(chave) + '}}</td>';
    }
    function periodosDemo() {
      var pers = demoPacote.periodos || [];
      if (pers.length) return pers;
      return [
        { n: 1, rotulo: '1º Bimestre', bimestres: [1] },
        { n: 2, rotulo: '2º Bimestre', bimestres: [2] },
        { n: 3, rotulo: '3º Bimestre', bimestres: [3] },
        { n: 4, rotulo: '4º Bimestre', bimestres: [4] }
      ];
    }
    function htmlListaEditavel() {
      var comps = demoPacote.componentes || [];
      if (!comps.length) return '';
      var html = '<table class="dados" style="width:100%;border-collapse:collapse;"><tr><td style="border:0.4px solid #222;padding:3px;font-weight:bold;">Componente curricular</td></tr>';
      comps.forEach(function (c) {
        html += '<tr><td style="border:0.4px solid #222;padding:3px;">' + esc(rotuloDisc(c)) + '</td></tr>';
      });
      return html + '</table>';
    }
    function htmlBoletimEditavel() {
      var comps = demoPacote.componentes || [];
      var pers = periodosDemo();
      if (!comps.length) return '';
      var th = 'border:0.4px solid #222;padding:2px;background:#e5e7eb;font-weight:bold;text-align:center;';
      var html = '<table class="dados quadro-notas" style="width:100%;border-collapse:collapse;font-size:7pt;">';
      html += '<thead><tr><th rowspan="2" style="' + th + '">Componente</th>';
      pers.forEach(function (p) {
        html += '<th colspan="2" style="' + th + '">' + esc(p.rotulo || '') + '</th>';
      });
      html += '<th rowspan="2" style="' + th + '">Média</th><th rowspan="2" style="' + th + '">Resultado</th></tr><tr>';
      pers.forEach(function () {
        html += '<th style="' + th + '">Nota</th><th style="' + th + '">Falta</th>';
      });
      html += '</tr></thead><tbody>';
      comps.forEach(function (c) {
        html += '<tr><td style="border:0.4px solid #222;padding:3px;">' + esc(rotuloDisc(c)) + '</td>';
        pers.forEach(function (p) {
          html += celulaToken('nota_' + c.slug + '_' + p.n);
          html += celulaToken('falta_' + c.slug + '_' + p.n);
        });
        html += celulaToken(c.media || ('media_final_' + c.slug));
        html += celulaToken(c.media || ('media_final_' + c.slug));
        html += '</tr>';
      });
      return html + '</tbody></table>';
    }
    function htmlGlossario() {
      var comps = demoPacote.componentes || [];
      if (!comps.length) return '';
      var th = 'border:0.4px solid #222;padding:3px;background:#e5e7eb;font-weight:bold;';
      var td = 'border:0.4px solid #222;padding:3px;';
      var html = '<p style="margin:0 0 4px;font-weight:bold;">Glossário</p>';
      html += '<table class="dados" style="width:100%;border-collapse:collapse;"><tr><th style="' + th + '">Sigla</th><th style="' + th + '">Componente</th></tr>';
      comps.forEach(function (c) {
        html += '<tr><td style="' + td + '">' + esc(siglaDe(c)) + '</td><td style="' + td + '">' + esc(c.nome || '') + '</td></tr>';
      });
      return html + '</table>';
    }
    function gravarGradeDemo() {
      var comps = demoPacote.componentes || [];
      var pers = periodosDemo();
      if (!comps.length) return;
      state.estrutura.grade = {
        tipo: 'ficha',
        periodos: pers.map(function (p) {
          var bims = Array.isArray(p.bimestres) && p.bimestres.length ? p.bimestres : [p.n];
          return { n: p.n, rotulo: p.rotulo || '', bimestres: bims };
        }),
        componentes: comps.map(function (c) {
          return { slug: c.slug, nome: c.nome, grupo: '', vaga: false, aliases: [c.nome] };
        })
      };
    }
    function inserirPersonalizado(html, aviso) {
      if (!html) {
        if (status) status.textContent = 'Escolha o aluno para montar as disciplinas da série.';
        return;
      }
      gravarGradeDemo();
      inserirBlocoHtml(html);
      setStatus(aviso);
    }
    function pintarTurmas(lista) {
      if (lista) demoPacote.turmas = lista;
      var anoFiltro = anoSel && anoSel.value ? parseInt(anoSel.value, 10) || 0 : 0;
      var atual = turma.value;
      var html = '<option value="">Selecione a turma / série</option>';
      (demoPacote.turmas || []).forEach(function (t) {
        var anoT = Number(t.ano_letivo || 0);
        if (anoFiltro > 0 && anoT > 0 && anoT !== anoFiltro) return;
        html += '<option value="' + parseInt(t.id, 10) + '" data-ano="' + anoT + '">' + esc(t.rotulo || t.nome || '') + '</option>';
      });
      turma.innerHTML = html;
      if (atual) turma.value = atual;
      if (atual && !turma.value) {
        aluno.value = '';
        pintarAlunos([]);
      }
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
    function ligarArrasteTexto(el) {
      if (!el || el._edocDragTxt) return;
      el._edocDragTxt = true;
      el.setAttribute('draggable', 'true');
      var dragging = false;
      el.addEventListener('dragstart', function (e) {
        dragging = true;
        var texto = el.getAttribute('data-drag-texto') || '';
        var slug = el.getAttribute('data-sigla-drag') || '';
        if (slug) {
          var campo = el.closest('.edoc-disc') && el.closest('.edoc-disc').querySelector('[data-sigla]');
          texto = (campo && campo.value.trim()) || texto;
        }
        prepararArraste(e, 'text/edoc-texto', texto, 'edoc-texto:' + texto);
      });
      el.addEventListener('dragend', function () {
        marcarCelulaDrop(null);
        setTimeout(function () { dragging = false; }, 0);
      });
      el.addEventListener('click', function () {
        if (dragging) return;
        var texto = el.getAttribute('data-drag-texto') || '';
        var slug = el.getAttribute('data-sigla-drag') || '';
        if (slug) {
          var campo = el.closest('.edoc-disc') && el.closest('.edoc-disc').querySelector('[data-sigla]');
          texto = (campo && campo.value.trim()) || texto;
        }
        var sel = state.selected ? findPath(state.selected.id) : null;
        var colId = (sel && sel.column) ? sel.column.id : garantirColunaCorpo();
        insertTexto(colId, texto);
      });
    }
    function pintarComponentes(lista) {
      var drop = $('#edoc-demo-comps-drop');
      var contagem = $('#edoc-demo-comps-count');
      if (!comps) return;
      demoPacote.componentes = lista || [];
      if (!lista || !lista.length) {
        comps.innerHTML = '';
        if (drop) drop.hidden = true;
        return;
      }
      var mapa = mapaGlossario();
      var pers = periodosDemo();
      var html = '';
      lista.forEach(function (c) {
        var nome = c.nome || '';
        var sigla = mapa[c.slug] || c.sigla || '';
        var eventos = Array.isArray(c.eventos) ? c.eventos : [];
        html += '<div class="edoc-disc">';
        html += '<button type="button" class="edoc-disc-nome" draggable="true" data-drag-texto="' + esc(nome) + '" title="Arraste o nome para a folha">' + esc(nome) + '</button>';
        html += '<label class="edoc-disc-sigla">Sigla <input type="text" maxlength="12" data-sigla="' + esc(c.slug) + '" data-nome="' + esc(nome) + '" value="' + esc(sigla) + '"></label>';
        html += '<div class="edoc-disc-chips">';
        html += '<button type="button" data-drag-texto="' + esc(nome) + '">Nome</button>';
        html += '<button type="button" data-drag-texto="' + esc(sigla) + '" data-sigla-drag="' + esc(c.slug) + '">Sigla</button>';
        if (eventos.length) {
          var grupos = [];
          var porGrupo = {};
          eventos.forEach(function (evento) {
            var grupo = String((evento && evento.grupo) || 'Campos').trim() || 'Campos';
            if (!porGrupo[grupo]) {
              porGrupo[grupo] = [];
              grupos.push(grupo);
            }
            porGrupo[grupo].push(evento);
          });
          grupos.forEach(function (grupo) {
            html += '<div class="edoc-disc-grupo">';
            html += '<span class="edoc-disc-grupo-titulo">' + esc(grupo) + '</span>';
            html += '<div class="edoc-disc-grupo-chips">';
            porGrupo[grupo].forEach(function (evento) {
              var rotulo = String((evento && evento.rotulo) || evento.codigo || 'Campo');
              html += '<button type="button" data-drag-var="' + esc(evento.chave) + '" title="' + esc(grupo + ' · ' + rotulo) + '">' + esc(rotulo) + '</button>';
            });
            html += '</div></div>';
          });
        } else {
          pers.forEach(function (p, i) {
            var nota = (c.notas && c.notas[i]) || ('nota_' + c.slug + '_' + p.n);
            var falta = (c.faltas && c.faltas[i]) || ('falta_' + c.slug + '_' + p.n);
            html += '<button type="button" data-drag-var="' + esc(nota) + '" title="Nota de ' + esc(p.rotulo || '') + '">' + esc(String(p.n)) + 'º</button>';
            html += '<button type="button" data-drag-var="' + esc(falta) + '" title="Falta de ' + esc(p.rotulo || '') + '">F' + esc(String(p.n)) + '</button>';
          });
          html += '<button type="button" data-drag-var="' + esc(c.media || ('media_final_' + c.slug)) + '">MF</button>';
          html += '<button type="button" data-drag-var="' + esc('media_anual_' + c.slug) + '">MA</button>';
        }
        html += '</div></div>';
      });
      comps.innerHTML = html;
      $all('[data-drag-var]', comps).forEach(ligarArrasteVariavel);
      $all('[data-drag-texto]', comps).forEach(ligarArrasteTexto);
      $all('[data-sigla]', comps).forEach(function (campo) {
        campo.addEventListener('change', function () {
          var siglaLimpa = String(campo.value || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 12);
          campo.value = siglaLimpa;
          guardarSigla(campo.getAttribute('data-sigla') || '', campo.getAttribute('data-nome') || '', siglaLimpa);
          var botao = campo.closest('.edoc-disc') && campo.closest('.edoc-disc').querySelector('[data-sigla-drag]');
          if (botao) botao.setAttribute('data-drag-texto', siglaLimpa);
        });
      });
      if (contagem) contagem.textContent = String(lista.length);
      if (drop) drop.hidden = false;
    }
    function aplicarDemo(data) {
      if (!data || data.ok === false) {
        if (status) status.textContent = (data && data.error) || 'Não foi possível carregar a demonstração.';
        return;
      }
      if (!turma.dataset.pronto) {
        demoPacote.turmas = data.turmas || [];
        pintarAnos(demoPacote.turmas);
        pintarTurmas(demoPacote.turmas);
        turma.dataset.pronto = '1';
        if (!turma.value && demoPacote.turmas.length) {
          turma.value = String(demoPacote.turmas[0].id);
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
      if (Array.isArray(data.turmas)) {
        demoPacote.turmas = data.turmas;
        var anoRegra = (data.regra && parseInt(data.regra.ano_letivo, 10)) || 0;
        if (anoRegra > 0 && anoSel) {
          anoSel.value = String(anoRegra);
        }
        pintarAnos(demoPacote.turmas);
        var turmaAntes = turma.value;
        pintarTurmas(demoPacote.turmas);
        if (turmaAntes && !turma.value) {
          aluno.value = '';
          pintarAlunos([]);
        } else if (!turma.value && demoPacote.turmas.length === 1) {
          turma.value = String(demoPacote.turmas[0].id);
          carregar();
          return;
        }
      }
      pintarRegras(data.regras_aprovacao || []);
      pintarPeriodosDemonstrativo(data.periodos_demonstrativo || []);
      pintarDemonstrativos(data.demonstrativos || data.boletins || []);
      pintarLayoutsBoletim(data.layouts_boletim || []);
      if (data.demonstrativo_id) {
        var didResolvido = parseInt(data.demonstrativo_id, 10) || 0;
        if (didResolvido > 0) {
          state.estrutura.fonte_demonstrativo_id = didResolvido;
          if (fonteDemonstrativo) fonteDemonstrativo.value = String(didResolvido);
        }
      }
      if (data.layout_boletim_id) {
        var lidResolvido = parseInt(data.layout_boletim_id, 10) || 0;
        if (lidResolvido > 0) {
          state.estrutura.fonte_boletim_id = lidResolvido;
          if (fonteBoletim) fonteBoletim.value = String(lidResolvido);
        }
      }
      if (data.regra_academica_id) {
        var ridResolvido = parseInt(data.regra_academica_id, 10) || 0;
        if (ridResolvido > 0) {
          state.estrutura.fonte_regra_academica_id = ridResolvido;
          if (fonteRegra) fonteRegra.value = String(ridResolvido);
        }
      }
      if (Array.isArray(data.periodos) && data.periodos.length) demoPacote.periodos = data.periodos;
      demoPacote.html_quadro = data.html_quadro || '';
      demoPacote.html_boletim = data.html_boletim || '';
      pintarComponentes(data.componentes || []);
      if (!data.vars && data.resumo) {
        C.varsPreview = { quadro_notas_html: '', boletim_html: '' };
        C.modeloQuadroHtml = '';
        state.demo = !!(real && real.checked);
        render();
      }
      if (data.vars) {
        persistirEdicaoSeHouver();
        if (restaurarTodosPlaceholdersBoletim()) {
          state.dirty = true;
          scheduleSave();
        }
        C.varsPreview = data.vars;
        C.modeloQuadroHtml = data.modelo_quadro_html || '';
        if (data.html_quadro) {
          C.varsPreview.quadro_notas_html = data.html_quadro;
          garantirPaisagemBoletim();
        }
        // Boletim = layout criado; nunca herda o quadro do Demonstrativo.
        C.varsPreview.boletim_html = data.html_boletim || '';
        if (data.html_por_bimestre && typeof data.html_por_bimestre === 'object') {
          Object.keys(data.html_por_bimestre).forEach(function (n) {
            var codigo = parseInt(n, 10) || 0;
            var chavePeriodo = chaveDemonstrativoPeriodo(codigo, '');
            (data.periodos_demonstrativo || []).forEach(function (p) {
              if (Number(p.n || 0) === codigo && p.chave) chavePeriodo = p.chave;
            });
            C.varsPreview[chavePeriodo] = data.html_por_bimestre[n] || '';
            if (codigo >= 1 && codigo <= 4) {
              C.varsPreview['quadro_notas_b' + codigo + '_html'] = data.html_por_bimestre[n] || '';
            }
          });
        }
        // Demonstrativo: restaura token do bimestre (não materializa o Boletim no bloco).
        var rolesDemo = ['header', 'body', 'footer'];
        for (var rd = 0; rd < rolesDemo.length; rd++) {
          var secsDemo = (areaOf(rolesDemo[rd]).sections || []);
          for (var id = 0; id < secsDemo.length; id++) {
            var colsDemo = secsDemo[id].columns || [];
            for (var jd = 0; jd < colsDemo.length; jd++) {
              (colsDemo[jd].elements || []).forEach(function (elDemo) {
                if (elDemo && restaurarPlaceholderDemonstrativo(elDemo)) {
                  state.dirty = true;
                  scheduleSave();
                }
              });
            }
          }
        }
        registrarDemoSel(capturarDemoSel());
        atualizarLinkPdf();
        state.demo = !!(real && real.checked);
        if (status) {
          if (boletimSelecionadoId() && (data.html_boletim || data.html_quadro)) {
            status.textContent = data.resumo || 'Quadro na folha. Troque a aluna para atualizar a prévia.';
          } else if (boletimSelecionadoId()) {
            status.textContent = data.resumo || 'Fonte escolhida. Se o quadro não aparecer, confira o assistente do modelo.';
          } else {
            status.textContent = data.resumo || 'Dados reais na folha.';
          }
        }
        render();
        return;
      }
      if (status) {
        if (data.resumo) status.textContent = data.resumo;
        else if (turma.value && (!data.alunos || !data.alunos.length)) status.textContent = 'Esta turma não tem alunos matriculados.';
        else if (turma.value) status.textContent = 'Escolha a aluna para ver o layout na folha.';
        else status.textContent = 'Nenhuma turma ativa encontrada.';
      }
    }
    function tipoFonteDoBloco() {
      var el = state.selected ? findPath(state.selected.id) : null;
      var alvo = (el && el.element && elementoEhBoletim(el.element)) ? el.element : null;
      if (!alvo) {
        // Prefere Boletim se existir na folha — evita prévia cair no Demonstrativo.
        var roles = ['header', 'body', 'footer'];
        for (var r = 0; r < roles.length; r++) {
          var secs = (areaOf(roles[r]).sections || []);
          for (var i = 0; i < secs.length; i++) {
            var cols = secs[i].columns || [];
            for (var j = 0; j < cols.length; j++) {
              var els = cols[j].elements || [];
              for (var k = 0; k < els.length; k++) {
                if (els[k] && els[k].type === 'boletim') return 'layout';
              }
            }
          }
        }
        alvo = acharElementoBoletim();
      }
      if (alvo && alvo.type === 'boletim') return 'layout';
      if (alvo && alvo.type === 'demonstrativo_notas') return 'demonstrativo';
      return 'demonstrativo';
    }
    function carregar() {
      var n = ++req;
      var rid = regraSelecionadaId();
      var did = demonstrativoSelecionadoId();
      var lid = layoutBoletimSelecionadoId();
      var fonteTipo = tipoFonteDoBloco();
      // Com regra, o backend resolve o vínculo; o tipo do bloco define Demonstrativo vs Boletim.
      if (rid > 0) {
        did = 0;
        lid = 0;
      } else if (did > 0) {
        lid = 0;
        fonteTipo = 'demonstrativo';
      } else if (lid > 0) {
        fonteTipo = 'layout';
      }
      var url = C.demoUrl
        + '?turma_id=' + encodeURIComponent(turma.value || '0')
        + '&aluno_id=' + encodeURIComponent(aluno.value || '0')
        + '&regra_academica_id=' + encodeURIComponent(String(rid))
        + '&fonte_tipo=' + encodeURIComponent(fonteTipo)
        + '&demonstrativo_id=' + encodeURIComponent(String(did))
        + '&layout_boletim_id=' + encodeURIComponent(String(lid))
        + '&boletim_id=' + encodeURIComponent(String(did > 0 ? did : lid))
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
    if (anoSel) {
      anoSel.addEventListener('change', function () {
        aluno.value = '';
        pintarAlunos([]);
        pintarComponentes([]);
        pintarTurmas();
        carregar();
      });
    }
    turma.addEventListener('change', function () {
      aluno.value = '';
      pintarAlunos([]);
      pintarComponentes([]);
      carregar();
    });
    aluno.addEventListener('change', function () {
      carregar();
    });
    if (fonteRegra) {
      if (state.estrutura && state.estrutura.fonte_regra_academica_id) {
        fonteRegra.value = String(state.estrutura.fonte_regra_academica_id);
      }
      fonteRegra.addEventListener('change', function () {
        gravarFonteRegra(fonteRegra.value);
        abrirCardBoletim(false);
        // Troca de regra: limpa turma fora da vigência; chips de bimestre vêm no pacote.
        aluno.value = '';
        pintarAlunos([]);
        carregar();
      });
    }
    if (fonteDemonstrativo) {
      if (state.estrutura && state.estrutura.fonte_demonstrativo_id) {
        fonteDemonstrativo.value = String(state.estrutura.fonte_demonstrativo_id);
      } else if (state.estrutura && state.estrutura.fonte_boletim_id) {
        fonteDemonstrativo.value = String(state.estrutura.fonte_boletim_id);
      }
    }
    if (fonteBoletim) {
      if (state.estrutura && state.estrutura.fonte_boletim_id) {
        fonteBoletim.value = String(state.estrutura.fonte_boletim_id);
      }
    }
    if (real) {
      real.addEventListener('change', function () {
        state.demo = !!real.checked;
        render();
      });
    }
    ligarSeletorDemonstrativo();
    window.__edocRecarregarDemo = carregar;
    carregar();
  }

  function bindBuscaElementos() {
    var input = $('#edoc-element-search');
    if (!input) return;
    var grupos = $all('[data-edoc-palette-group]');
    var abertos = null;
    function normalizarBusca(texto) {
      return String(texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }
    input.addEventListener('input', function () {
      var busca = normalizarBusca(input.value).trim();
      var total = 0;
      if (busca && !abertos) abertos = $all('details', $('#pane-elementos')).map(function (g) { return { node: g, open: g.open }; });
      grupos.forEach(function (grupo) {
        var encontrados = 0;
        $all('[data-drag-type], [data-drag-layout], [data-drag-var], [data-emissao-preset], #edoc-demo-abrir', grupo).forEach(function (item) {
          var texto = item.textContent + ' ' + (item.getAttribute('title') || '') + ' ' + (item.getAttribute('data-drag-type') || '') + ' ' + (item.getAttribute('data-drag-var') || '') + ' ' + (item.getAttribute('data-var-nome') || '') + ' demonstrativo bimestre';
          item.hidden = !!busca && normalizarBusca(texto).indexOf(busca) < 0;
          if (!item.hidden) encontrados++;
          var wrap = item.closest('.edoc-demo-picker-wrap');
          if (wrap) wrap.hidden = !!item.hidden;
        });
        grupo.hidden = encontrados === 0;
        if (busca && encontrados) {
          grupo.open = true;
          $all('details', grupo).forEach(function (g) { g.open = true; });
        }
        total += encontrados;
      });
      if (!busca && abertos) {
        abertos.forEach(function (g) { g.node.open = g.open; });
        abertos = null;
      }
      $('.edoc-left').classList.toggle('is-searching', !!busca);
      $('#edoc-element-empty').hidden = total > 0;
    });
  }

  var requisicaoPreviaPdf = 0;
  var controlePreviaPdf = null;
  var arquivoPreviaPdf = null;

  function alternarResultadoPdf(ativo) {
    if (ativo) fecharHtmlExpandido();
    state.preview = ativo;
    document.body.classList.toggle('edoc-result-mode', ativo);
    $('#edoc-pdf-preview').hidden = !ativo;
    var botao = $('#edoc-preview-mode');
    botao.classList.toggle('active', ativo);
    botao.setAttribute('aria-pressed', String(ativo));
    botao.innerHTML = ativo
      ? '<i class="fa-solid fa-pen" aria-hidden="true"></i> Voltar à edição'
      : '<i class="fa-solid fa-eye" aria-hidden="true"></i> Ver resultado';
    if (!ativo) {
      requisicaoPreviaPdf++;
      if (controlePreviaPdf) controlePreviaPdf.abort();
      $('#edoc-pdf-frame').removeAttribute('src');
      if (arquivoPreviaPdf) URL.revokeObjectURL(arquivoPreviaPdf);
      arquivoPreviaPdf = null;
    }
    render();
    if (ativo) carregarResultadoPdf();
  }

  function carregarResultadoPdf() {
    persistirEdicaoSeHouver();
    clearTimeout(saveTimer);
    var numero = ++requisicaoPreviaPdf;
    if (controlePreviaPdf) controlePreviaPdf.abort();
    controlePreviaPdf = new AbortController();
    var sinal = controlePreviaPdf.signal;
    var frame = $('#edoc-pdf-frame');
    var mensagem = $('#edoc-pdf-message');
    var atualizar = $('#edoc-pdf-refresh');
    var selecao = (state.demoSel && state.demoSel.alunoId) ? clone(state.demoSel) : capturarDemoSel();
    frame.onload = null;
    frame.onerror = null;
    frame.removeAttribute('src');
    if (arquivoPreviaPdf) URL.revokeObjectURL(arquivoPreviaPdf);
    arquivoPreviaPdf = null;
    frame.hidden = true;
    mensagem.hidden = false;
    mensagem.textContent = 'Salvando e preparando o PDF…';
    atualizar.disabled = true;
    save().then(function (resposta) {
      if (numero !== requisicaoPreviaPdf || !state.preview) return;
      if (!resposta || !resposta.ok || !C.previewUrl) {
        mensagem.textContent = 'Não foi possível salvar o documento. Volte à edição ou tente atualizar a prévia.';
        atualizar.disabled = false;
        return;
      }
      mensagem.textContent = 'Gerando o PDF. Aguarde…';
      var url = urlPreviewComDemo(selecao);
      url += (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
      return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/pdf' }, signal: sinal })
        .then(function (respostaPdf) {
          var tipo = (respostaPdf.headers.get('Content-Type') || '').toLowerCase();
          if (!respostaPdf.ok || tipo.indexOf('application/pdf') !== 0) throw new Error('Resposta sem PDF');
          return respostaPdf.blob();
        }).then(function (arquivo) {
          return arquivo.slice(0, 5).text().then(function (assinatura) {
            if (assinatura !== '%PDF-') throw new Error('Arquivo PDF inválido');
            if (numero !== requisicaoPreviaPdf || !state.preview) return;
            if (arquivoPreviaPdf) URL.revokeObjectURL(arquivoPreviaPdf);
            arquivoPreviaPdf = URL.createObjectURL(arquivo);
            frame.src = arquivoPreviaPdf;
            frame.hidden = false;
            mensagem.hidden = true;
            atualizar.disabled = false;
          });
        });
    }).catch(function () {
      if (numero !== requisicaoPreviaPdf || !state.preview) return;
      mensagem.hidden = false;
      mensagem.textContent = 'Não foi possível gerar o PDF. Verifique sua sessão e tente atualizar a prévia.';
      frame.hidden = true;
      atualizar.disabled = false;
    });
  }

  function bindPalette() {
    $all('[data-drag-type]').forEach(function (el) {
      el.setAttribute('draggable', 'true');
      var dragging = false;
      el.addEventListener('dragstart', function (e) {
        dragging = true;
        var tipo = el.getAttribute('data-drag-type') || '';
        prepararArraste(e, 'text/edoc-type', tipo, 'edoc-type:' + tipo);
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
        var layout = el.getAttribute('data-drag-layout') || '';
        prepararArraste(e, 'text/edoc-layout', layout, 'edoc-layout:' + layout);
      });
      el.addEventListener('click', function () {
        addSection('body', JSON.parse(el.getAttribute('data-drag-layout')));
      });
    });
    $all('[data-drag-var]').forEach(ligarArrasteVariavel);
    $all('[data-emissao-preset]').forEach(function (botao) {
      botao.addEventListener('click', function () {
        var campo = JSON.parse(botao.getAttribute('data-emissao-preset'));
        var campos = state.estrutura.campos_variaveis || [];
        if (!campoVariavelPorChave(campo.chave)) {
          if (campos.length >= 24) { setStatus('O modelo já tem 24 campos da emissão.'); return; }
          state.estrutura.campos_variaveis = camposVariaveisValidos(campos.concat([campo]));
        }
        insertVariavelIntoSelection(campo.chave);
        pintarCamposVariaveis();
        setStatus('Campo adicionado. O valor será preenchido ao emitir.');
      });
    });
  }

  function ligarArrasteVariavel(el) {
    if (!el || el._edocDrag) return;
    el._edocDrag = true;
    el.setAttribute('draggable', 'true');
    var dragging = false;
    el.addEventListener('dragstart', function (e) {
      dragging = true;
      var chave = el.getAttribute('data-drag-var') || '';
      prepararArraste(e, 'text/edoc-var', chave, 'edoc-var:' + chave);
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
    persistirEdicaoSeHouver();
    var nova = normalizarEstrutura(estrutura);
    var temConteudo = ['header', 'body', 'footer'].some(function (area) {
      return (state.estrutura[area].sections || []).some(function (secao) {
        return secao.columns.some(function (coluna) { return coluna.elements.length > 0; });
      });
    });
    if (temConteudo) {
      ['header', 'body', 'footer'].forEach(function (area) {
        state.estrutura[area].sections = state.estrutura[area].sections.concat(nova[area].sections);
      });
    } else {
      nova.page.ocultarCabecalho = state.estrutura.page.ocultarCabecalho;
      nova.page.ocultarRodape = state.estrutura.page.ocultarRodape;
      nova.campos_variaveis = state.estrutura.campos_variaveis;
      nova.emissao = state.estrutura.emissao;
      nova.fonte_boletim_id = state.estrutura.fonte_boletim_id;
      state.estrutura = nova;
    }
    state.selected = null;
    pushHist();
    render();
    setStatus('HTML inserido na folha. Use Ver resultado para conferir a impressão.');
  }

  function limparHtmlReproducao(html) {
    var modelo = document.createElement('template');
    modelo.innerHTML = String(html || '');
    var tags = /^(P|BR|STRONG|B|EM|I|U|SPAN|DIV|TABLE|THEAD|TBODY|TR|TD|TH|COLGROUP|COL|H1|H2|H3|UL|OL|LI|HR|IMG)$/;
    var atributos = /^(class|style|width|height|colspan|rowspan|span|align|valign|alt)$/;
    modelo.content.querySelectorAll('*').forEach(function (el) {
      if (!tags.test(el.tagName)) {
        if (/^(A|SECTION|ARTICLE|MAIN|HEADER|FOOTER)$/.test(el.tagName)) el.replaceWith.apply(el, Array.from(el.childNodes));
        else el.remove();
        return;
      }
      if (el.tagName === 'IMG' && !/^data:image\/(png|jpeg|jpg|gif|webp);base64,[a-z0-9+/]+=*$/i.test(el.getAttribute('src') || '')) {
        el.remove();
        return;
      }
      Array.from(el.attributes).forEach(function (attr) {
        if (el.tagName === 'IMG' && attr.name === 'src') return;
        var css = attr.name === 'style' ? attr.value.toLowerCase() : '';
        var funcoes = Array.from(css.matchAll(/([a-z0-9_-]+)\s*\(/g), function (m) { return m[1]; });
        var cssSeguro = !/[@\\]|\/\*/.test(css) && funcoes.every(function (nome) { return /^(rgb|rgba|hsl|hsla|calc|min|max|clamp)$/.test(nome); });
        if (!atributos.test(attr.name) || !cssSeguro) el.removeAttribute(attr.name);
      });
      if (el.tagName === 'TABLE') el.classList.add('edoc-tabela-reproduzida');
      ['position', 'inset', 'top', 'left', 'right', 'bottom', 'z-index'].forEach(function (nome) { el.style.removeProperty(nome); });
    });
    return modelo.innerHTML;
  }

  function abrirRevisaoIa(estrutura, titulo) {
    var dialogo = $('#edoc-ia-revisao');
    $('#edoc-ia-revisao-titulo').textContent = titulo;
    var codigo = $('#edoc-ia-html');
    var previa = $('#edoc-ia-previa');
    var aviso = $('#edoc-ia-revisao-erro');
    var elementos = [];
    ['header', 'body', 'footer'].forEach(function (area) {
      ((estrutura[area] || {}).sections || []).forEach(function (secao) {
        (secao.columns || []).forEach(function (coluna) {
          elementos = elementos.concat(coluna.elements || []);
        });
      });
    });
    var editavel = elementos.length === 1 && elementos[0].type === 'html';
    var html = elementos.map(function (el) {
      var props = el.props || {};
      return props.html || '<p>' + esc(props.text || '') + '</p>';
    }).join('\n');
    codigo.readOnly = !editavel;
    codigo.value = indentarHtmlDocumento(html);
    aviso.hidden = true;
    function atualizar() {
      previa.srcdoc = '<!doctype html><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'">'
        + '<style>body{font:11pt Arial,sans-serif;color:#222;margin:12px}table{border-collapse:collapse;max-width:100%}td,th{vertical-align:middle}p{margin:0 0 8px}.edoc-vert{writing-mode:vertical-rl;transform:rotate(180deg)}.edoc-faixa{background:#d1d5db}</style>'
        + '<style>' + (C.cssAjusteFolha || '') + '</style>'
        + '<main class="edoc-ajuste-folha">' + limparHtmlReproducao(ph(codigo.value)) + '</main>';
    }
    codigo.oninput = atualizar;
    $('#edoc-ia-revisao-cancelar').onclick = function () { dialogo.close(); };
    $('#edoc-ia-revisao-form').onsubmit = function (e) {
      e.preventDefault();
      if (editavel) {
        var limpo = limparHtmlReproducao(codigo.value).trim();
        if (!limpo) {
          aviso.textContent = 'O HTML está vazio. Cole ou edite o conteúdo antes de inserir.';
          aviso.hidden = false;
          return;
        }
        elementos[0].props.html = limpo;
      }
      dialogo.close();
      aplicarEstruturaIa(estrutura);
    };
    dialogo.onclose = function () { previa.removeAttribute('srcdoc'); };
    atualizar();
    dialogo.showModal();
    setStatus('HTML pronto para revisão.');
  }

  function consultarJobIa(jobId, btn, file, tentativa) {
    if (tentativa > 45) {
      btn.disabled = false;
      marcarReproducaoIa(false, 'A IA demorou demais. Tente de novo.');
      return;
    }
    fetch(C.urlBase + '/admin/ai-job/' + jobId + '/status')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.status === 'done') {
          btn.disabled = false;
          var est = data.result && data.result.estrutura;
          if (!est) {
            marcarReproducaoIa(false, 'A IA não devolveu um layout.');
            return;
          }
          marcarReproducaoIa(false);
          abrirRevisaoIa(est, 'Reprodução da imagem');
          return;
        }
        if (data.status === 'failed' || data.status === 'error' || data.status === 'not_found') {
          btn.disabled = false;
          marcarReproducaoIa(false, data.error || 'Não foi possível reproduzir a imagem.');
          return;
        }
        marcarReproducaoIa(true, tentativa < 3 ? 'Lendo a imagem…' : 'Montando cabeçalho, corpo e rodapé…');
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
    btn.disabled = true;
    marcarReproducaoIa(true, 'Enviando imagem…');
    var fd = new FormData();
    fd.append('_token', C.csrf);
    fd.append('imagem', file, file.name || 'documento.png');
    fetch(C.iaUrl, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.success && data.job_id) {
          marcarReproducaoIa(true, 'Imagem enviada. A IA está lendo o documento…');
          consultarJobIa(data.job_id, btn, file, 1);
          return;
        }
        btn.disabled = false;
        marcarReproducaoIa(false, (data && data.error) || 'Não foi possível enviar a imagem.');
      })
      .catch(function () {
        btn.disabled = false;
        marcarReproducaoIa(false, 'Erro de conexão. Tente novamente.');
      });
  }

  function bindReproduzirImagem() {
    var btn = $('#edoc-ia');
    var input = $('#edoc-ia-file');
    var modal = $('#edoc-ia-modal');
    var zona = $('#edoc-ia-cola');
    if (!btn || !input || !modal || !C.iaUrl) return;
    function fechar() {
      modal.classList.remove('open');
      if (zona) zona.classList.remove('is-drag');
    }
    function receberArquivo(file) {
      if (!imagemAceitaIa(file)) {
        setStatus('Use PNG, JPG ou WebP.');
        return;
      }
      fechar();
      enviarImagemIa(file);
    }
    btn.addEventListener('click', function () {
      modal.classList.add('open');
      if (zona) zona.focus();
    });
    var escolher = $('#edoc-ia-escolher');
    if (escolher) escolher.addEventListener('click', function () { input.click(); });
    var fecharBtn = $('#edoc-ia-fechar');
    function cancelar() {
      fechar();
      $('#edoc-assistente-abrir').focus();
    }
    if (fecharBtn) fecharBtn.addEventListener('click', cancelar);
    modal.addEventListener('click', function (e) { if (e.target === modal) cancelar(); });
    modal.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') cancelar();
    });
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      input.value = '';
      if (!file) return;
      receberArquivo(file);
    });
    if (zona) {
      zona.addEventListener('click', function () { zona.focus(); });
      zona.addEventListener('dragenter', function (e) {
        e.preventDefault();
        zona.classList.add('is-drag');
      });
      zona.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        zona.classList.add('is-drag');
      });
      zona.addEventListener('dragleave', function () { zona.classList.remove('is-drag'); });
      zona.addEventListener('drop', function (e) {
        e.preventDefault();
        zona.classList.remove('is-drag');
        var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        receberArquivo(file);
      });
    }
    document.addEventListener('paste', function (e) {
      if (!modal.classList.contains('open')) return;
      var file = arquivoDoClipboard(e.clipboardData);
      e.preventDefault();
      e.stopPropagation();
      receberArquivo(file);
    }, true);
  }

  async function esperarRespostaAssistente(jobId, avisarDemora) {
    for (var tentativa = 0; ; tentativa++) {
      var resposta = await fetch(C.urlBase + '/admin/ai-job/' + encodeURIComponent(jobId) + '/status')
        .catch(function () { return null; });
      if (resposta && resposta.redirected) throw new Error('Sessão expirada. Recarregue o editor.');
      if (resposta && resposta.status < 500) {
        var job = await resposta.json().catch(function () { return null; });
        if (job && job.status === 'done' && job.result && job.result.mensagem) return job.result;
        if (!resposta.ok || (job && ['done', 'failed', 'error', 'not_found', 'cancelled'].includes(job.status))) {
          throw new Error((job && job.error) || 'Não foi possível obter a resposta do assistente.');
        }
      }
      if (tentativa === 30) avisarDemora();
      await new Promise(function (resolver) { setTimeout(resolver, tentativa < 30 ? 2000 : 8000); });
    }
  }

  function consultarVariaveisAssistente(texto) {
    function palavras(valor) {
      return valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().match(/[a-z0-9]+/g) || [];
    }
    var termos = Array.from(new Set(palavras(texto))).filter(function (termo) {
      return termo.length > 1 && !/^(ao|as|da|de|do|em|na|no|os|ou|um)$/.test(termo);
    });
    var campos = new Map();
    $all('#pane-elementos [data-drag-var][data-var-nome]').forEach(function (botao) {
      var chave = botao.dataset.dragVar;
      var rotulo = botao.dataset.varNome;
      var termosCampo = new Set(palavras(chave + ' ' + rotulo));
      var pontos = termos.filter(function (termo) { return termosCampo.has(termo); }).length;
      if (texto.toLowerCase().includes('{{' + chave + '}}') || texto.trim().toLowerCase() === chave) pontos += 100;
      if (pontos > 0) campos.set(chave, { chave: chave, rotulo: rotulo, pontos: pontos });
    });
    var resultados = Array.from(campos.values()).sort(function (a, b) { return b.pontos - a.pontos; });
    if (!resultados.length) {
      return 'Não encontrei essa variável. Tente buscar pelo nome do campo, como “foto do aluno” ou “CNPJ da escola”.';
    }
    return resultados.filter(function (campo) { return campo.pontos === resultados[0].pontos; }).slice(0, 4).map(function (campo) {
      return campo.rotulo + ' — ' + tokenDaChave(campo.chave);
    }).join('\n');
  }

  function bindAssistenteDocumento() {
    var abrir = $('#edoc-assistente-abrir');
    if (!abrir) return;
    var painel = $('#edoc-assistente-painel');
    var opcoes = $('#edoc-assistente-opcoes');
    var chat = $('#edoc-assistente-chat');
    var imagem = $('#edoc-ia');
    function alternarPainel(visivel, devolverFoco) {
      painel.hidden = !visivel;
      abrir.setAttribute('aria-expanded', String(visivel));
      if (visivel) {
        if (conversa) conversa.hidden = true;
        opcoes.hidden = false;
        (chat || imagem).focus();
      } else if (devolverFoco) {
        abrir.focus();
      }
    }
    abrir.addEventListener('click', function () { alternarPainel(painel.hidden, true); });
    $('#edoc-assistente-fechar').addEventListener('click', function () { alternarPainel(false, true); });
    painel.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') alternarPainel(false, true);
    });
    if (imagem) imagem.addEventListener('click', function () { alternarPainel(false, false); });

    var conversa = $('#edoc-assistente-conversa');
    if (!chat || !conversa) return;
    var form = $('#edoc-assistente-form');
    var campo = $('#edoc-assistente-mensagem');
    var enviar = form.querySelector('button[type="submit"]');
    var mensagens = $('#edoc-assistente-mensagens');
    var exemplos = $('#edoc-assistente-exemplos');
    var status = $('#edoc-assistente-status');
    var historico = [];

    function avisar(texto, erro) {
      status.textContent = texto;
      status.dataset.erro = String(!!erro);
      status.hidden = !texto;
    }
    function exibirMensagem(papel, texto, estrutura) {
      var balao = document.createElement('div');
      balao.className = 'edoc-assistente-balao';
      balao.dataset.papel = papel;
      var autor = document.createElement('strong');
      autor.textContent = papel === 'user' ? 'Você' : 'Assistente';
      var conteudo = document.createElement('p');
      conteudo.textContent = texto;
      balao.append(autor, conteudo);
      if (estrutura) {
        var revisar = document.createElement('button');
        revisar.type = 'button';
        revisar.className = 'edoc-btn';
        revisar.textContent = 'Revisar layout';
        revisar.addEventListener('click', function () {
          abrirRevisaoIa(JSON.parse(JSON.stringify(estrutura)), 'Proposta de layout');
        });
        balao.appendChild(revisar);
      }
      mensagens.appendChild(balao);
      mensagens.scrollTop = mensagens.scrollHeight;
      return balao;
    }
    chat.addEventListener('click', function () {
      opcoes.hidden = true;
      conversa.hidden = false;
      mensagens.scrollTop = mensagens.scrollHeight;
      campo.focus();
    });
    $('#edoc-assistente-voltar').addEventListener('click', function () {
      conversa.hidden = true;
      opcoes.hidden = false;
      chat.focus();
    });
    exemplos.querySelectorAll('button').forEach(function (botao) {
      botao.addEventListener('click', function () {
        campo.value = botao.textContent;
        campo.focus();
      });
    });
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var texto = campo.value.trim();
      if (!texto || enviar.disabled) return;
      campo.value = '';
      campo.focus();
      exemplos.hidden = true;
      var balao = exibirMensagem('user', texto);
      if (!C.assistenteUrl) {
        exibirMensagem('assistant', consultarVariaveisAssistente(texto));
        return;
      }
      var mensagem = { role: 'user', content: texto };
      var dados = new FormData();
      var ultimas = historico.slice(-10);
      var ultimaProposta = ultimas.slice().reverse().find(function (item) { return item.html; });
      dados.append('_token', C.csrf);
      dados.append('mensagens', JSON.stringify(ultimas.map(function (item) {
        var content = item.content;
        if (item === ultimaProposta) content += '\n\nHTML da proposta anterior:\n' + item.html;
        return { role: item.role, content: content };
      }).concat(mensagem)));

      enviar.disabled = true;
      avisar('Preparando resposta…');
      try {
        var resposta = await fetch(C.assistenteUrl, { method: 'POST', body: dados });
        var pedido = await resposta.json();
        if (!resposta.ok || !pedido.job_id) throw new Error(pedido.error || 'Não foi possível enviar a mensagem.');
        var resultado = await esperarRespostaAssistente(pedido.job_id, function () {
          avisar('Ainda aguardando a resposta. Você pode continuar editando a folha.');
        });
        var estrutura = resultado.estrutura;
        var proposta = estrutura && estrutura.body.sections[0].columns[0].elements[0].props.html;
        historico = ultimas.concat(mensagem, { role: 'assistant', content: resultado.mensagem, html: proposta });
        exibirMensagem('assistant', resultado.mensagem, estrutura);
        avisar('');
      } catch (erro) {
        balao.remove();
        if (!campo.value.trim()) campo.value = texto;
        exemplos.hidden = historico.length > 0;
        avisar(erro instanceof SyntaxError ? 'Não foi possível ler a resposta do assistente.' : erro.message, true);
      } finally {
        enviar.disabled = false;
      }
    });
  }

  function bindChrome() {
    function onClick(sel, fn) {
      var el = $(sel);
      if (el) el.addEventListener('click', fn);
    }
    onClick('#edoc-undo', undo);
    onClick('#edoc-redo', redo);
    onClick('#edoc-zoom-out', function () { state.ajustarZoom = false; state.zoom = Math.max(25, state.zoom - 10); render(); });
    onClick('#edoc-zoom-in', function () { state.ajustarZoom = false; state.zoom = Math.min(150, state.zoom + 10); render(); });
    onClick('#edoc-zoom-fit', function () { state.ajustarZoom = true; render(); });
    onClick('#edoc-layout-sugerido', aplicarLayoutSugerido);
    window.addEventListener('resize', function () { if (state.ajustarZoom && !state.preview) render(); });
    bindReproduzirImagem();
    bindAssistenteDocumento();
    onClick('#edoc-preview-mode', function () { alternarResultadoPdf(!state.preview); });
    onClick('#edoc-pdf-refresh', carregarResultadoPdf);
    onClick('#edoc-save', save);
    bindEmissao();
    onClick('#edoc-pdf', function (e) {
      e.preventDefault();
      // Congela a aluna/turma da folha ANTES do save (select pode mudar no meio).
      var snap = (state.demoSel && state.demoSel.alunoId) ? state.demoSel : capturarDemoSel();
      save().then(function (j) {
        if (!j || !j.ok || !C.previewUrl) return;
        state.demo = true;
        registrarDemoSel(snap);
        var base = urlPreviewComDemo(snap);
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        window.open(base + sep + 't=' + Date.now(), '_blank');
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
      if (apagarSelecaoPeloTeclado(e)) return;
      var meta = e.metaKey || e.ctrlKey;
      var typing = estaDigitando(e.target);
      if (meta && e.key === 's') { e.preventDefault(); if (state.preview) carregarResultadoPdf(); else save(); return; }
      if (state.preview) return;
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
      if (!stage._edocDrop) {
        stage._edocDrop = true;
        stage.addEventListener('dragover', function (e) {
          if (state.preview || !paper) return;
          var sob = document.elementFromPoint(e.clientX, e.clientY);
          if (!sob || (sob !== paper && !paper.contains(sob))) return;
          e.preventDefault();
          if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        });
        stage.addEventListener('drop', function (e) {
          if (state.preview || !paper) return;
          if (e.target && (e.target === paper || paper.contains(e.target))) return;
          var sob = document.elementFromPoint(e.clientX, e.clientY);
          if (!sob || (sob !== paper && !paper.contains(sob))) return;
          var dados = dadosDoArraste(e.dataTransfer);
          if (!dados.layout && !dados.tipo && !dados.chave && !dados.texto && !dados.arquivo) return;
          e.preventDefault();
          var col = sob.closest ? sob.closest('.edoc-col') : null;
          if (col) {
            soltarNaColuna(col.getAttribute('data-id'), dados);
            return;
          }
          var vazio = sob.closest ? sob.closest('[data-empty]') : null;
          var area = sob.closest ? sob.closest('.edoc-area') : null;
          var role = vazio ? vazio.getAttribute('data-empty') : (area ? area.getAttribute('data-role') : 'body');
          soltarNaRole(role || 'body', dados);
        });
      }
    }
  }

  try {
    state.history = [clone(state.estrutura)];
    state.histI = 0;
    bindPalette();
    bindBuscaElementos();
    bindCriadorTabela();
    bindHtmlExpandido();
    bindBotaoEditarHtml();
    bindInfoFolha();
    bindVarSearch();
    bindCamposVariaveis();
    bindDemonstracao();
    bindChrome();
    render();
  } catch (err) {
    console.error('[editor-documento]', err);
    setStatus('Erro ao carregar o editor');
  }
})();
