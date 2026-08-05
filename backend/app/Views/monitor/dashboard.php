<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Alunos online agora</h1>
    <p class="text-gray-600">
        <?php if (!empty($turmas)): ?>
            Turmas: <?= htmlspecialchars(implode(', ', array_column($turmas, 'nome'))) ?>
        <?php else: ?>
            Nenhuma turma vinculada. Peça ao administrador para configurar seu acesso.
        <?php endif; ?>
    </p>
    <p id="aviso-total-escola" class="hidden mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"></p>
</div>

<?php if (!empty($eventos)): ?>
<div class="bg-white rounded-xl shadow p-4 mb-6 border border-gray-100">
    <label for="filtro-bloco" class="block text-sm font-medium text-gray-700 mb-2">Filtrar por evento / bloco de prova</label>
    <select id="filtro-bloco" class="w-full md:w-auto min-w-[280px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
        <option value="">Todas as turmas (sem filtro de evento)</option>
        <?php foreach ($eventos as $ev): ?>
            <?php
                $dataFmt = !empty($ev['data_prova']) ? date('d/m/Y', strtotime($ev['data_prova'])) : '';
                $horaFmt = !empty($ev['hora_inicio']) ? substr($ev['hora_inicio'], 0, 5) : '';
                $label = trim(($ev['titulo'] ?? 'Evento') . ($dataFmt ? ' — ' . $dataFmt : '') . ($horaFmt ? ' ' . $horaFmt : ''));
                if (!empty($ev['em_andamento'])) {
                    $label .= ' [EM ANDAMENTO]';
                }
            ?>
            <option value="<?= (int)$ev['id'] ?>" <?= (int)($bloco_id ?? 0) === (int)$ev['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<div id="banner-alertas" class="hidden mb-6 rounded-xl border-2 border-red-300 bg-red-50 p-4">
    <div class="flex items-start gap-3">
        <span class="text-2xl" aria-hidden="true">⚠️</span>
        <div>
            <p class="font-semibold text-red-800">
                <span id="alerta-canceladas-count">0</span> aluno(s) com prova <strong>cancelada</strong> (saída do modo seguro)
            </p>
            <p class="text-sm text-red-700 mt-1">Clique no card do aluno para ver detalhes e apoiar na liberação com a coordenação.</p>
        </div>
    </div>
</div>

<div id="banner-em-prova" class="hidden mb-6 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
    <span id="alerta-em-prova-count">0</span> aluno(s) em prova neste momento.
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow border-l-4 border-teal-500 p-6">
        <p class="text-sm text-gray-500">Online agora</p>
        <p id="contador-online" class="text-4xl font-bold text-teal-700">0</p>
    </div>
    <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-gray-500">Provas canceladas</p>
        <p id="contador-canceladas" class="text-4xl font-bold text-red-600">0</p>
    </div>
    <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-gray-500">Em prova agora</p>
        <p id="contador-em-prova" class="text-4xl font-bold text-cyan-700">0</p>
    </div>
</div>

<div id="lista-alunos" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <div class="col-span-full text-center text-gray-500 py-12 bg-white rounded-xl border border-dashed border-gray-200">
        Carregando alunos online...
    </div>
</div>

<script>
(function() {
    const lista = document.getElementById('lista-alunos');
    const contador = document.getElementById('contador-online');
    const contadorCanceladas = document.getElementById('contador-canceladas');
    const contadorEmProva = document.getElementById('contador-em-prova');
    const bannerAlertas = document.getElementById('banner-alertas');
    const bannerEmProva = document.getElementById('banner-em-prova');
    const alertaCanceladasCount = document.getElementById('alerta-canceladas-count');
    const alertaEmProvaCount = document.getElementById('alerta-em-prova-count');
    const filtroBloco = document.getElementById('filtro-bloco');

    let blocoId = <?= json_encode($bloco_id ?? null) ?>;
    let eventSource = null;

    function queryBloco() {
        return blocoId ? ('?bloco_id=' + encodeURIComponent(blocoId)) : '';
    }

    function labelContexto(aluno) {
        if (aluno.alerta === 'prova_cancelada') {
            return '⚠ Prova cancelada' + (aluno.prova_titulo ? ': ' + aluno.prova_titulo : '');
        }
        if (aluno.prova_titulo) return aluno.prova_titulo;
        if (aluno.contexto_label) return aluno.contexto_label;
        if (aluno.contexto_tipo === 'prova') return 'Prova em andamento';
        if (aluno.contexto_tipo === 'jornada') return 'Jornada';
        return 'Navegando na plataforma';
    }

    function cardClasses(aluno) {
        if (aluno.alerta === 'prova_cancelada') {
            return 'border-red-400 bg-red-50 ring-2 ring-red-200';
        }
        if (aluno.alerta === 'prova_andamento' || aluno.contexto_tipo === 'prova') {
            return 'border-teal-400 bg-teal-50/50';
        }
        return 'border-green-200';
    }

    const avisoTotalEscola = document.getElementById('aviso-total-escola');

    function render(data) {
        const total = data.total || 0;
        const totalEscola = data.total_escola || 0;
        const canceladas = (data.alertas && data.alertas.canceladas) || 0;
        const emProva = (data.alertas && data.alertas.em_prova) || 0;

        contador.textContent = total;

        if (avisoTotalEscola) {
            if (totalEscola > total) {
                avisoTotalEscola.classList.remove('hidden');
                let msg = 'Na escola há <strong>' + totalEscola + '</strong> aluno(s) online no painel, mas <strong>' + total + '</strong> estão nas turmas vinculadas a você.';
                if (data.turmas_incompletas) {
                    msg += ' Peça à coordenação para revisar as turmas do seu cadastro de monitor (Admin → Monitores).';
                }
                avisoTotalEscola.innerHTML = msg;
            } else {
                avisoTotalEscola.classList.add('hidden');
                avisoTotalEscola.textContent = '';
            }
        }
        contadorCanceladas.textContent = canceladas;
        contadorEmProva.textContent = emProva;

        if (canceladas > 0) {
            bannerAlertas.classList.remove('hidden');
            alertaCanceladasCount.textContent = canceladas;
        } else {
            bannerAlertas.classList.add('hidden');
        }

        if (emProva > 0) {
            bannerEmProva.classList.remove('hidden');
            alertaEmProvaCount.textContent = emProva;
        } else {
            bannerEmProva.classList.add('hidden');
        }

        if (!data.alunos || data.alunos.length === 0) {
            lista.innerHTML = '<div class="col-span-full text-center text-gray-500 py-12 bg-white rounded-xl">Nenhum aluno online no momento.</div>';
            return;
        }

        lista.innerHTML = data.alunos.map(function(aluno) {
            const cls = cardClasses(aluno);
            const pulse = aluno.alerta === 'prova_cancelada' ? 'bg-red-500' : 'bg-green-500';
            const ctxClass = aluno.alerta === 'prova_cancelada' ? 'text-red-700 font-semibold' : 'text-teal-700 font-medium';
            return '<a href="' + aluno.url + '" class="block bg-white border-2 ' + cls + ' rounded-xl p-5 hover:shadow-lg transition-shadow">' +
                '<div class="flex items-center justify-between mb-2">' +
                '<span class="font-semibold text-gray-900">' + escapeHtml(aluno.nome) + '</span>' +
                '<span class="w-2.5 h-2.5 ' + pulse + ' rounded-full animate-pulse"></span>' +
                '</div>' +
                '<p class="text-sm text-gray-600"><strong>RA:</strong> ' + escapeHtml(aluno.ra || '-') + '</p>' +
                '<p class="text-sm text-gray-600"><strong>Turma:</strong> ' + escapeHtml(aluno.turma_nome) + '</p>' +
                '<p class="text-sm mt-2 ' + ctxClass + '">' + escapeHtml(labelContexto(aluno)) + '</p>' +
                '<p class="text-xs text-gray-400 mt-1">Tempo online: ' + (aluno.tempo_online?.formatado || '-') + '</p>' +
                '</a>';
        }).join('');
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function carregar() {
        fetch('<?= URL ?>/monitor/api/alunos-online' + queryBloco(), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(render)
            .catch(() => {});
    }

    function iniciarStream() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
        if (!window.EventSource) {
            carregar();
            setInterval(carregar, 15000);
            return;
        }
        eventSource = new EventSource('<?= URL ?>/monitor/api/alunos-online/stream' + queryBloco());
        eventSource.addEventListener('online', function(e) {
            try { render(JSON.parse(e.data || '{}')); } catch (err) {}
        });
        eventSource.addEventListener('error', function() {
            eventSource.close();
            eventSource = null;
            setTimeout(iniciarStream, 5000);
            carregar();
        });
    }

    if (filtroBloco) {
        filtroBloco.addEventListener('change', function() {
            const v = this.value;
            blocoId = v ? parseInt(v, 10) : null;
            const url = new URL(window.location.href);
            if (blocoId) url.searchParams.set('bloco_id', blocoId);
            else url.searchParams.delete('bloco_id');
            window.history.replaceState({}, '', url);
            iniciarStream();
            carregar();
        });
    }

    iniciarStream();
})();
</script>
