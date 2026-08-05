<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Teste de exercícios') ?></title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen p-6">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Teste de geração de exercícios (jornada)</h1>

        <!-- Prompt template -->
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <label class="block text-sm font-semibold text-slate-700 mb-2">Prompt (template da jornada)</label>
            <textarea id="promptTemplate" rows="14" class="w-full border border-slate-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"><?= htmlspecialchars($prompt_default ?? '') ?></textarea>
        </div>

        <!-- Gerar exercícios -->
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Gerar exercícios</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contexto adicional</label>
                    <input type="text" id="contexto" placeholder="Ex: Transpiração vegetal, ensino médio" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de gráfico/diagrama desejado</label>
                    <input type="text" id="tipo_grafico" placeholder="Ex.: Gráfico de barras, diagrama de ciclo da água, gráfico de pizza" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Descreva o que você quer: barras, linha, pizza, diagrama, esquema, mapa, etc.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tipo</label>
                    <select id="tipo" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="alternativas">Alternativas (Múltipla Escolha)</option>
                        <option value="dissertativa">Dissertativa</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantidade</label>
                    <input type="number" id="quantidade" min="1" max="10" value="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <button type="button" id="btnGerarExercicios" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
                Gerar exercícios
            </button>
            <div id="exerciciosLoading" class="mt-4 hidden text-slate-600 text-sm">Gerando exercícios e imagem...</div>
            <div id="exerciciosResult" class="mt-4 hidden">
                <p class="text-xs text-slate-500 mb-2">Estilo ENEM: cada questão tem <strong>enunciado</strong> → <strong>imagem</strong> (gerada a partir de imagem_prompt) → <strong>pergunta</strong> → alternativas A a E.</p>
                <div id="exerciciosCards" class="space-y-6"></div>
            </div>
            <div id="exerciciosError" class="mt-4 hidden px-4 py-3 rounded-lg bg-red-100 text-red-800 text-sm"></div>
        </div>

        <!-- Imagem para contexto -->
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Imagem para contexto</h2>
            <p class="text-sm text-slate-600 mb-3">Gere uma imagem (gráfico, charge, diagrama) para usar como contexto. Ex.: &quot;Gráfico de transpiração vegetal para ensino médio&quot;, &quot;Charge sobre reciclagem&quot;.</p>
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Prompt da imagem</label>
                    <input type="text" id="promptImagem" placeholder="Educational diagram of plant transpiration for high school" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="button" id="btnGerarImagem" class="px-6 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500">
                    Gerar imagem
                </button>
            </div>
            <div id="imagemResult" class="mt-4 hidden">
                <p class="text-sm font-medium text-slate-700 mb-2">Imagem gerada:</p>
                <img id="imagemPreview" src="" alt="Preview" class="max-w-md rounded-lg border border-slate-200 shadow mb-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm text-slate-600">URL:</span>
                    <input type="text" id="imagemUrl" readonly class="flex-1 min-w-0 px-3 py-1.5 border border-slate-200 rounded bg-slate-50 text-sm">
                    <button type="button" id="btnAprovarImagem" class="px-4 py-1.5 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-600">
                        Aprovar / Usar
                    </button>
                </div>
            </div>
            <div id="imagemError" class="mt-4 hidden px-4 py-3 rounded-lg bg-red-100 text-red-800 text-sm"></div>
            <div id="imagemLoading" class="mt-4 hidden text-slate-600 text-sm">Gerando imagem...</div>
        </div>
    </div>

    <script>
    (function() {
        var urlBase = <?= json_encode($url ?? '') ?>;

        function escapeHtml(s) { return (s || '').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
        function renderExercicios(list) {
            var container = document.getElementById('exerciciosCards');
            container.innerHTML = '';
            if (!Array.isArray(list)) list = [];
            list.forEach(function(q, idx) {
                var alt = q.alternativas || {};
                var correta = (q.correta || q.resposta_correta || '').toString().toUpperCase().trim();
                if (correta.length === 1) correta = correta; else correta = correta.toLowerCase();
                var letters = ['A','B','C','D','E'];
                var altKeys = letters.filter(function(k) { return alt[k] || alt[k.toLowerCase()]; });
                if (altKeys.length === 0) altKeys = ['a','b','c','d','e'].filter(function(k) { return alt[k]; });
                var altHtml = altKeys.map(function(k) {
                    var key = alt[k] !== undefined ? k : k.toLowerCase();
                    var text = alt[k] || alt[key] || '';
                    var isCorreta = (key === correta || key.toUpperCase() === correta);
                    return '<div class="flex items-start gap-2 py-1.5 ' + (isCorreta ? 'bg-green-50 rounded px-2' : '') + '">' +
                        '<span class="font-medium text-slate-700">' + (key.length === 1 ? key.toUpperCase() : key) + ')</span>' +
                        '<span>' + escapeHtml(text) + '</span>' +
                        (isCorreta ? ' <span class="text-green-700 text-sm font-medium">(Correta)</span>' : '') + '</div>';
                }).join('');
                var titulo = q.titulo || ('Exercício ' + (idx + 1));
                var enunciado = escapeHtml(q.enunciado || '');
                var pergunta = escapeHtml(q.pergunta || '');
                var imgBlock = (q.imagem_url) ? '<div class="my-3"><img src="' + (q.imagem_url).replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '" alt="Parte do enunciado" class="max-w-lg rounded-lg border border-slate-200 shadow"></div>' : '';
                var ordem = '<div class="mb-4">';
                if (enunciado) ordem += '<p class="text-slate-700">' + enunciado + '</p>';
                ordem += imgBlock;
                if (pergunta) ordem += '<p class="text-slate-700 font-medium mt-2">' + pergunta + '</p>';
                ordem += '</div>';
                var card = document.createElement('div');
                card.className = 'bg-slate-50 border border-slate-200 rounded-xl p-5';
                card.innerHTML = '<h3 class="font-semibold text-slate-800 mb-2">' + escapeHtml(titulo) + '</h3>' +
                    ordem +
                    '<div class="mb-4"><p class="text-sm font-medium text-slate-600 mb-2">Alternativas</p>' + altHtml + '</div>' +
                    (q.explicacao ? '<div class="pt-2 border-t border-slate-200"><p class="text-sm font-medium text-slate-600 mb-1">Explicação</p><p class="text-slate-700 text-sm">' + escapeHtml(q.explicacao) + '</p></div>' : '');
                container.appendChild(card);
            });
        }

        document.getElementById('btnGerarExercicios').addEventListener('click', function() {
            var resultado = document.getElementById('exerciciosResult');
            var errDiv = document.getElementById('exerciciosError');
            var loading = document.getElementById('exerciciosLoading');
            resultado.classList.add('hidden');
            errDiv.classList.add('hidden');
            loading.classList.remove('hidden');

            var payload = {
                contexto: document.getElementById('contexto').value || 'Contexto de teste',
                tipo_grafico: (document.getElementById('tipo_grafico') && document.getElementById('tipo_grafico').value) ? document.getElementById('tipo_grafico').value.trim() : '',
                tipo: document.getElementById('tipo').value,
                quantidade: parseInt(document.getElementById('quantidade').value, 10) || 3,
                _token: (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
            };

            fetch(urlBase + '/master/teste-exercicios/gerar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(res) {
                if (!res.ok || !res.data.success) {
                    loading.classList.add('hidden');
                    errDiv.textContent = res.data.error || 'Erro ao gerar exercícios';
                    errDiv.classList.remove('hidden');
                    return;
                }
                var raw = res.data.exercicios || {};
                var list = Array.isArray(raw) ? raw : (raw.exercicios || raw.questoes || []);
                if (!Array.isArray(list)) list = [];

                function gerarImagemParaQuestao(promptTexto) {
                    var p = 'Apenas o gráfico ou diagrama, estilo educacional, fundo branco. Sem texto de pergunta nem alternativas. ' + (promptTexto || '');
                    return fetch(urlBase + '/api/generate-image', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ prompt: p })
                    }).then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); });
                }

                function proxima(idx) {
                    if (idx >= list.length) {
                        loading.classList.add('hidden');
                        renderExercicios(list);
                        resultado.classList.remove('hidden');
                        return;
                    }
                    var q = list[idx];
                    if (q.imagem_prompt) {
                        gerarImagemParaQuestao(q.imagem_prompt).then(function(imgRes) {
                            if (imgRes.ok && imgRes.data.success && imgRes.data.image_url) {
                                q.imagem_url = imgRes.data.image_url;
                            }
                            proxima(idx + 1);
                        }).catch(function() { proxima(idx + 1); });
                    } else {
                        proxima(idx + 1);
                    }
                }
                proxima(0);
            })
            .catch(function(e) {
                loading.classList.add('hidden');
                errDiv.textContent = 'Erro de conexão: ' + e.message;
                errDiv.classList.remove('hidden');
            });
        });

        document.getElementById('btnGerarImagem').addEventListener('click', function() {
            var promptImagem = document.getElementById('promptImagem').value.trim();
            if (!promptImagem) {
                document.getElementById('imagemError').textContent = 'Digite o prompt da imagem.';
                document.getElementById('imagemError').classList.remove('hidden');
                return;
            }

            var imgResult = document.getElementById('imagemResult');
            var imgError = document.getElementById('imagemError');
            var imgLoading = document.getElementById('imagemLoading');
            imgResult.classList.add('hidden');
            imgError.classList.add('hidden');
            imgLoading.classList.remove('hidden');

            fetch(urlBase + '/api/generate-image', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prompt: promptImagem })
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(res) {
                imgLoading.classList.add('hidden');
                if (res.ok && res.data.success && res.data.image_url) {
                    document.getElementById('imagemPreview').src = res.data.image_url;
                    document.getElementById('imagemUrl').value = res.data.image_url;
                    imgResult.classList.remove('hidden');
                } else {
                    imgError.textContent = res.data.error || 'Falha ao gerar imagem';
                    imgError.classList.remove('hidden');
                }
            })
            .catch(function(e) {
                imgLoading.classList.add('hidden');
                imgError.textContent = 'Erro de conexão: ' + e.message;
                imgError.classList.remove('hidden');
            });
        });

        document.getElementById('btnAprovarImagem').addEventListener('click', function() {
            var urlInput = document.getElementById('imagemUrl');
            var url = urlInput.value.trim();
            if (url) {
                var contexto = document.getElementById('contexto');
                var texto = (contexto.value || '') + (contexto.value ? '\n' : '') + 'Imagem de contexto: ' + url;
                contexto.value = texto;
            }
        });
    })();
    </script>
</body>
</html>
