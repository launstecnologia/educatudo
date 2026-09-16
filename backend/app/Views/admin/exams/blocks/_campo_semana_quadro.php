<?php
$bloco = is_array($bloco ?? null) ? $bloco : [];
$gruposRegrasNotas = is_array($grupos_regras_notas ?? null) ? $grupos_regras_notas : [];
$temGruposRegras = $gruposRegrasNotas !== [];
$semanaSel = (int) ($bloco['semana'] ?? 0);
$blocoIdCampo = (int) ($bloco['id'] ?? 0);
$vinculosIni = is_array($bloco['grupos_regras_vinculos'] ?? null) ? $bloco['grupos_regras_vinculos'] : [];
if ($vinculosIni === []) {
    $gid0 = (int) ($bloco['grupo_regras_notas_id'] ?? 0);
    if ($gid0 > 0) {
        $tid0 = (int) ($bloco['grupo_regras_tipo_id'] ?? 0);
        $mid0 = (int) ($bloco['grupo_regras_marca_id'] ?? 0);
        $vinculosIni[] = [
            'grupo_id' => $gid0,
            'tipo_id' => $tid0 > 0 ? $tid0 : null,
            'marca_id' => $mid0 > 0 ? $mid0 : null,
        ];
    }
}
?>
<div class="mb-6" id="campo-semana-evento">
    <?php if ($temGruposRegras): ?>
        <div class="mb-2">
            <label class="block text-sm font-medium text-gray-700">Destinos no quadro de notas</label>
            <p class="text-xs text-gray-500 mt-1">Escolha o quadro e o bloco (A/B). A semana (S1, S2…) entra sozinha neste bimestre.</p>
        </div>
        <div id="lista-vinculos-grupo" class="space-y-3"></div>
        <button type="button" id="btn-add-vinculo-grupo"
                class="mt-3 w-full px-4 py-2.5 border border-dashed border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fa-solid fa-plus mr-2 text-gray-500"></i>Adicionar destino
        </button>
        <input type="hidden" id="semana" name="semana" value="<?= $semanaSel > 0 ? $semanaSel : '' ?>">
        <script>
        (function () {
            var catalogo = <?= json_encode($gruposRegrasNotas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?> || [];
            var vinculosIni = <?= json_encode($vinculosIni, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?> || [];
            var blocoId = <?= $blocoIdCampo ?>;
            var urlBase = <?= json_encode(rtrim((string) URL, '/') . '/admin/grupos-regras-notas/', JSON_UNESCAPED_SLASHES) ?>;
            var lista = document.getElementById('lista-vinculos-grupo');
            var hidS = document.getElementById('semana');
            var btnAdd = document.getElementById('btn-add-vinculo-grupo');
            if (!lista) return;

            function grupoPorId(id) {
                id = Number(id) || 0;
                for (var i = 0; i < catalogo.length; i++) {
                    if (Number(catalogo[i].id) === id) return catalogo[i];
                }
                return null;
            }

            function fillSelect(sel, placeholder, itens, selectedId, labelKey) {
                sel.innerHTML = '<option value="">' + placeholder + '</option>';
                (itens || []).forEach(function (item) {
                    var opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item[labelKey] || item.nome || item.codigo || ('#' + item.id);
                    if (item.numero != null) opt.setAttribute('data-numero', item.numero);
                    if (Number(item.id) === Number(selectedId)) opt.selected = true;
                    sel.appendChild(opt);
                });
            }

            function periodoAtual() {
                return {
                    ano: parseInt((document.getElementById('ano_letivo') || {}).value, 10) || 0,
                    bim: parseInt((document.getElementById('bimestre') || {}).value, 10) || 0
                };
            }

            function escTxt(s) {
                return String(s || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function setDica(row, texto, erro) {
                var box = row.querySelector('.dica-semana-quadro');
                if (!box) return;
                var selM = row.querySelector('.sel-marca');
                var opt = selM && selM.options[selM.selectedIndex];
                var n = opt ? parseInt(opt.getAttribute('data-numero') || '', 10) : 0;
                var selT = row.querySelector('.sel-tipo');
                var blocoNome = '';
                if (selT && selT.selectedIndex > 0) {
                    blocoNome = (selT.options[selT.selectedIndex].textContent || '').trim();
                }
                box.classList.remove('hidden', 'border-indigo-100', 'bg-indigo-50', 'text-indigo-950', 'border-red-200', 'bg-red-50', 'text-red-800', 'border-amber-200', 'bg-amber-50', 'text-amber-900');
                if (!texto && n <= 0) {
                    box.classList.add('hidden');
                    box.innerHTML = '';
                    return;
                }
                if (erro) {
                    box.classList.add('border-red-200', 'bg-red-50', 'text-red-800');
                    box.innerHTML = '<div class="flex items-start gap-2"><i class="fa-solid fa-circle-exclamation mt-0.5"></i><span>' + escTxt(texto) + '</span></div>';
                    return;
                }
                if (n > 0) {
                    var sub = texto || ('S' + n + ' neste período.');
                    box.classList.add('border-indigo-100', 'bg-indigo-50', 'text-indigo-950');
                    box.innerHTML =
                        '<div class="flex items-center gap-3">' +
                            '<span class="inline-flex h-12 min-w-[3rem] items-center justify-center rounded-xl bg-indigo-600 text-white text-lg font-bold px-3">S' + n + '</span>' +
                            '<div class="min-w-0">' +
                                '<p class="font-semibold text-indigo-950 leading-tight">' + escTxt(blocoNome ? ('Vai para S' + n + ' · ' + blocoNome) : ('Vai para S' + n)) + '</p>' +
                                '<p class="text-xs text-indigo-800 mt-0.5 leading-snug">' + escTxt(sub) + '</p>' +
                            '</div>' +
                        '</div>';
                    return;
                }
                box.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-900');
                box.innerHTML = '<div class="flex items-start gap-2"><i class="fa-solid fa-circle-info mt-0.5"></i><span>' + escTxt(texto) + '</span></div>';
            }

            function preencherMarca(row, marcaId, numero, nome) {
                var selM = row.querySelector('.sel-marca');
                if (!selM) return;
                selM.innerHTML = '';
                var opt = document.createElement('option');
                opt.value = marcaId ? String(marcaId) : '';
                opt.textContent = nome || (numero ? ('S' + numero) : 'Coluna');
                if (numero) opt.setAttribute('data-numero', String(numero));
                if (marcaId) opt.selected = true;
                selM.appendChild(opt);
                syncSemana();
            }

            function resolverProxima(row, forcar) {
                var selG = row.querySelector('.sel-grupo');
                var selT = row.querySelector('.sel-tipo');
                var wrapM = row.querySelector('.wrap-marca');
                var g = grupoPorId(selG.value);
                var tipos = (g && Array.isArray(g.tipos)) ? g.tipos : [];
                var auto = tipos.length > 0;
                if (wrapM) wrapM.classList.toggle('hidden', auto);
                if (!auto) {
                    setDica(row, '');
                    return;
                }
                var tipoId = parseInt(selT.value, 10) || 0;
                var p = periodoAtual();
                if (!tipoId) {
                    preencherMarca(row, 0, 0, '');
                    setDica(row, 'Escolha o bloco. A semana (S1, S2…) entra sozinha neste bimestre.');
                    return;
                }
                if (!p.ano || !p.bim) {
                    preencherMarca(row, 0, 0, '');
                    setDica(row, 'Informe o ano letivo e o período para definir a semana.');
                    return;
                }
                if (!forcar && row.getAttribute('data-marca-ok') === '1') {
                    var selM = row.querySelector('.sel-marca');
                    var opt = selM && selM.options[selM.selectedIndex];
                    var n = opt ? parseInt(opt.getAttribute('data-numero') || '', 10) : 0;
                    var nome = opt ? opt.textContent : '';
                    if (n > 0) {
                        setDica(row, nome + ' neste período (já vinculada a esta prova).');
                        syncSemana();
                        return;
                    }
                }
                row.removeAttribute('data-marca-ok');
                setDica(row, 'Definindo a semana…');
                var qs = 'tipo_id=' + encodeURIComponent(tipoId)
                    + '&ano=' + encodeURIComponent(p.ano)
                    + '&bimestre=' + encodeURIComponent(p.bim)
                    + '&exceto_bloco_id=' + encodeURIComponent(blocoId || 0);
                fetch(urlBase + g.id + '/proxima-coluna?' + qs, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (!data || !data.ok) {
                        preencherMarca(row, 0, 0, '');
                        setDica(row, (data && data.error) ? data.error : 'Não foi possível definir a semana.', true);
                        return;
                    }
                    preencherMarca(row, data.marca_id, data.semana, data.nome);
                    setDica(row, data.dica || ((data.nome || '') + ' neste período.'));
                }).catch(function () {
                    preencherMarca(row, 0, 0, '');
                    setDica(row, 'Falha ao definir a semana deste bloco.', true);
                });
            }

            function syncLinha(row) {
                var selG = row.querySelector('.sel-grupo');
                var selT = row.querySelector('.sel-tipo');
                var selM = row.querySelector('.sel-marca');
                var wrapT = row.querySelector('.wrap-tipo');
                var wrapM = row.querySelector('.wrap-marca');
                var g = grupoPorId(selG.value);
                var tipos = (g && Array.isArray(g.tipos)) ? g.tipos : [];
                var marcas = (g && Array.isArray(g.marcas)) ? g.marcas : [];
                if (wrapT) wrapT.classList.toggle('hidden', tipos.length === 0);
                var auto = tipos.length > 0;
                if (wrapM) wrapM.classList.toggle('hidden', auto || marcas.length === 0);
                var tipoSel = parseInt(selT.getAttribute('data-keep') || selT.value, 10) || 0;
                fillSelect(selT, 'Bloco de disciplinas', tipos, tipoSel, 'nome');
                selT.removeAttribute('data-keep');
                if (auto) {
                    var keepM = parseInt(selM.getAttribute('data-keep') || selM.value, 10) || 0;
                    selM.removeAttribute('data-keep');
                    if (keepM > 0) {
                        var marcaKeep = null;
                        marcas.forEach(function (m) { if (Number(m.id) === keepM) marcaKeep = m; });
                        if (marcaKeep) {
                            preencherMarca(row, marcaKeep.id, marcaKeep.numero, marcaKeep.nome);
                            row.setAttribute('data-marca-ok', '1');
                        }
                    }
                    resolverProxima(row, keepM <= 0);
                    return;
                }
                var tipo = null;
                tipos.forEach(function (t) { if (Number(t.id) === (parseInt(selT.value, 10) || 0)) tipo = t; });
                var idsOk = tipo && Array.isArray(tipo.marcas_ids) && tipo.marcas_ids.length
                    ? tipo.marcas_ids.map(Number)
                    : marcas.map(function (m) { return Number(m.id); });
                var marcasOk = marcas.filter(function (m) {
                    return idsOk.indexOf(Number(m.id)) >= 0 && m.papel !== 'calculada';
                });
                fillSelect(selM, 'Coluna', marcasOk, parseInt(selM.getAttribute('data-keep') || selM.value, 10) || 0, 'nome');
                selM.removeAttribute('data-keep');
                setDica(row, '');
                syncSemana();
            }

            function addLinha(data) {
                data = data || {};
                var wrap = document.createElement('div');
                wrap.className = 'vinculo-grupo-row rounded-xl border border-gray-200 bg-gray-50/60 p-4 space-y-3';
                wrap.innerHTML =
                    '<div class="flex flex-col lg:flex-row gap-3 lg:items-end">' +
                    '<div class="flex-1 min-w-0"><label class="block text-xs font-medium text-gray-500 mb-1">Quadro</label>' +
                    '<select class="sel-grupo w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></select></div>' +
                    '<div class="flex-1 min-w-0 wrap-tipo hidden"><label class="block text-xs font-medium text-gray-500 mb-1">Bloco de disciplinas</label>' +
                    '<select class="sel-tipo w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><option value="">Bloco de disciplinas</option></select></div>' +
                    '<div class="flex-1 min-w-0 wrap-marca hidden"><label class="block text-xs font-medium text-gray-500 mb-1">Coluna</label>' +
                    '<select class="sel-marca w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><option value="">Coluna</option></select></div>' +
                    '<div class="shrink-0"><button type="button" class="btn-rm-vinculo w-full lg:w-auto px-4 py-2 border border-red-200 text-red-700 rounded-lg text-sm font-medium hover:bg-red-50">Remover</button></div>' +
                    '</div>' +
                    '<div class="dica-semana-quadro hidden text-sm rounded-xl border px-4 py-3"></div>';
                var selG = wrap.querySelector('.sel-grupo');
                fillSelect(selG, 'Nenhum quadro', catalogo, data.grupo_id || '', 'nome');
                wrap.querySelector('.sel-tipo').setAttribute('data-keep', data.tipo_id || '');
                wrap.querySelector('.sel-marca').setAttribute('data-keep', data.marca_id || '');
                selG.addEventListener('change', function () {
                    wrap.querySelector('.sel-tipo').value = '';
                    wrap.querySelector('.sel-tipo').removeAttribute('data-keep');
                    wrap.querySelector('.sel-marca').setAttribute('data-keep', '');
                    wrap.removeAttribute('data-marca-ok');
                    syncLinha(wrap);
                });
                wrap.querySelector('.sel-tipo').addEventListener('change', function () {
                    wrap.removeAttribute('data-marca-ok');
                    wrap.querySelector('.sel-marca').setAttribute('data-keep', '');
                    syncLinha(wrap);
                });
                wrap.querySelector('.sel-marca').addEventListener('change', syncSemana);
                wrap.querySelector('.btn-rm-vinculo').addEventListener('click', function () {
                    wrap.remove();
                    if (!lista.querySelector('.vinculo-grupo-row')) addLinha({});
                    syncSemana();
                });
                lista.appendChild(wrap);
                syncLinha(wrap);
            }

            function syncSemana() {
                var n = 0;
                lista.querySelectorAll('.vinculo-grupo-row').forEach(function (row) {
                    if (n > 0) return;
                    var selM = row.querySelector('.sel-marca');
                    var opt = selM && selM.options[selM.selectedIndex];
                    n = opt ? parseInt(opt.getAttribute('data-numero') || '', 10) : 0;
                });
                if (hidS) hidS.value = n > 0 ? String(n) : '';
            }

            window.coletarVinculosGrupoRegras = function () {
                var wrapCampo = document.getElementById('campo-semana-evento');
                if (wrapCampo && wrapCampo.classList.contains('hidden')) return null;
                var out = [];
                lista.querySelectorAll('.vinculo-grupo-row').forEach(function (row) {
                    var g = parseInt(row.querySelector('.sel-grupo').value, 10) || 0;
                    if (!g) return;
                    var t = parseInt(row.querySelector('.sel-tipo').value, 10) || 0;
                    var m = parseInt(row.querySelector('.sel-marca').value, 10) || 0;
                    out.push({
                        grupo_id: g,
                        tipo_id: t > 0 ? t : null,
                        marca_id: m > 0 ? m : null
                    });
                });
                return out;
            };

            window.resumoDestinosQuadro = function () {
                var wrapCampo = document.getElementById('campo-semana-evento');
                if (wrapCampo && wrapCampo.classList.contains('hidden')) return '—';
                var itens = [];
                lista.querySelectorAll('.vinculo-grupo-row').forEach(function (row) {
                    var selG = row.querySelector('.sel-grupo');
                    var g = parseInt(selG && selG.value, 10) || 0;
                    if (!g) return;
                    var quadro = ((selG.options[selG.selectedIndex] || {}).textContent || '').trim();
                    var selT = row.querySelector('.sel-tipo');
                    var wrapT = row.querySelector('.wrap-tipo');
                    var t = parseInt(selT && selT.value, 10) || 0;
                    var blocoTxt = (t > 0 && wrapT && !wrapT.classList.contains('hidden'))
                        ? ((selT.options[selT.selectedIndex] || {}).textContent || '').trim()
                        : '';
                    var selM = row.querySelector('.sel-marca');
                    var m = parseInt(selM && selM.value, 10) || 0;
                    var optM = selM && selM.options[selM.selectedIndex];
                    var n = optM ? parseInt(optM.getAttribute('data-numero') || '', 10) : 0;
                    var s = n > 0 ? ('S' + n) : (m > 0 && optM ? String(optM.textContent || '').trim() : '');
                    var txt = [quadro, blocoTxt, s].filter(Boolean).join(' · ');
                    if (txt) itens.push(txt);
                });
                if (itens.length) return itens.join('; ');
                var semanaEl = document.getElementById('semana');
                if (semanaEl && semanaEl.tagName === 'SELECT' && semanaEl.value) {
                    return (semanaEl.options[semanaEl.selectedIndex].textContent || '').trim() || '—';
                }
                return '—';
            };

            function onAddDestino() { addLinha({}); }
            if (btnAdd) btnAdd.addEventListener('click', onAddDestino);
            if (vinculosIni.length) {
                vinculosIni.forEach(function (v) { addLinha(v); });
            } else {
                addLinha({});
            }
            syncSemana();
            function reprocessarPeriodo() {
                lista.querySelectorAll('.vinculo-grupo-row').forEach(function (row) {
                    row.removeAttribute('data-marca-ok');
                    resolverProxima(row, true);
                });
            }
            var anoEl = document.getElementById('ano_letivo');
            var bimEl = document.getElementById('bimestre');
            if (anoEl) anoEl.addEventListener('change', reprocessarPeriodo);
            if (bimEl) bimEl.addEventListener('change', reprocessarPeriodo);
        })();
        </script>
    <?php else: ?>
        <label class="block text-sm font-medium text-gray-700 mb-2">
            Semana no quadro
        </label>
        <select id="semana" name="semana" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <option value="">Não se aplica</option>
            <?php for ($s = 1; $s <= 20; $s++): ?>
                <option value="<?= $s ?>" <?= $semanaSel === $s ? 'selected' : '' ?>>S<?= $s ?></option>
            <?php endfor; ?>
        </select>
        <p class="text-xs text-gray-500 mt-1">Para prova semanal, escolha a semana (S1, S2…). Cadastre um Quadro de Notas com blocos A/B para a semana entrar sozinha ao escolher o bloco.</p>
    <?php endif; ?>
</div>
<script>
(function () {
    var sel = document.getElementById('tipo_avaliacao_id');
    var wrap = document.getElementById('campo-semana-evento');
    var semana = document.getElementById('semana');
    if (!sel || !wrap) return;
    function syncSemana() {
        var opt = sel.options[sel.selectedIndex];
        var chave = (opt && opt.getAttribute('data-chave-quadro')) || '';
        var hide = chave !== '' && chave !== 'semanal';
        wrap.classList.toggle('hidden', hide);
        if (hide && semana) semana.value = '';
    }
    sel.addEventListener('change', syncSemana);
    syncSemana();
})();
</script>
