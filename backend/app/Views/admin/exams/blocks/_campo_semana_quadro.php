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
        <div class="flex items-center justify-between mb-2 gap-3">
            <label class="block text-sm font-medium text-gray-700">Destinos no quadro de notas</label>
            <button type="button" id="btn-add-vinculo-grupo"
                    class="btn-primary-custom px-4 py-2 text-sm font-semibold rounded-lg transition-colors hover:opacity-90">
                + Adicionar destino
            </button>
        </div>
        <div id="lista-vinculos-grupo" class="space-y-3"></div>
        <button type="button" id="btn-add-vinculo-grupo-baixo"
                class="mt-3 w-full px-4 py-2 border border-dashed border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fa-solid fa-plus mr-2 text-gray-500"></i>Adicionar destino
        </button>
        <input type="hidden" id="semana" name="semana" value="<?= $semanaSel > 0 ? $semanaSel : '' ?>">
        <p class="text-xs text-gray-500 mt-1">O quadro é anual. Escolha o bloco (A/B): a semana (S1, S2…) entra sozinha conforme as provas já lançadas neste bimestre. Cadastre o molde em Acadêmico → Quadro de Notas.</p>
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

            function setDica(row, texto, erro) {
                var box = row.querySelector('.dica-semana-quadro');
                if (!box) return;
                box.textContent = texto || '';
                box.classList.toggle('hidden', !texto);
                box.classList.toggle('text-red-700', !!erro);
                box.classList.toggle('text-indigo-950', !erro);
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
                wrap.className = 'vinculo-grupo-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end border border-gray-200 rounded-lg p-3';
                wrap.innerHTML =
                    '<div class="md:col-span-4"><label class="block text-xs font-medium text-gray-500 mb-1">Quadro</label>' +
                    '<select class="sel-grupo w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></select></div>' +
                    '<div class="md:col-span-3 wrap-tipo hidden"><label class="block text-xs font-medium text-gray-500 mb-1">Bloco de disciplinas</label>' +
                    '<select class="sel-tipo w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><option value="">Bloco de disciplinas</option></select></div>' +
                    '<div class="md:col-span-3 wrap-marca hidden"><label class="block text-xs font-medium text-gray-500 mb-1">Coluna</label>' +
                    '<select class="sel-marca w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><option value="">Coluna</option></select></div>' +
                    '<div class="md:col-span-2"><button type="button" class="btn-rm-vinculo w-full px-3 py-2 border border-red-200 text-red-700 rounded-lg text-sm hover:bg-red-50">Remover</button></div>' +
                    '<div class="md:col-span-12"><p class="dica-semana-quadro hidden text-sm rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2"></p></div>';
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

            function onAddDestino() { addLinha({}); }
            if (btnAdd) btnAdd.addEventListener('click', onAddDestino);
            var btnAddBaixo = document.getElementById('btn-add-vinculo-grupo-baixo');
            if (btnAddBaixo) btnAddBaixo.addEventListener('click', onAddDestino);
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
