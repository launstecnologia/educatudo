<?php
/**
 * Dashboard pedagógico — resultados do bloco de provas (nova tela).
 */
$d = $dashboard ?? [];
$ind = $d['indicadores'] ?? [];
$blocoId = (int) ($bloco['id'] ?? 0);
$mediaMin = (float) ($d['media_minima'] ?? 55);
$jsonDashboard = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div id="results-dashboard-root" class="pb-12">
    <div class="rd-print-only mb-4 hidden">
        <h2 class="text-xl font-bold text-gray-900">Resultados da Avaliação</h2>
        <p class="text-sm text-gray-700"><?= htmlspecialchars($bloco['titulo'] ?? '') ?></p>
        <p class="text-xs text-gray-500">Exportado em <?= date('d/m/Y H:i') ?></p>
    </div>
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Resultados da Avaliação</h2>
            <p class="text-gray-600 mt-1"><?= htmlspecialchars($bloco['titulo'] ?? '') ?></p>
            <p class="text-sm text-gray-500">Análise completa de desempenho dos alunos.</p>
        </div>
        <div class="flex flex-wrap gap-2 items-center rd-no-print">
            <button type="button" id="rd-btn-filtros"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M10 12h4M12 16h0"/></svg>
                Filtros
            </button>
            <button type="button" id="rd-btn-export-pdf"
                    class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 text-sm font-medium">
                Exportar PDF
            </button>
            <button type="button" id="rd-btn-export-excel"
                    class="px-4 py-2 rounded-lg bg-teal-600 text-white hover:bg-teal-700 text-sm font-medium">
                Exportar Excel
            </button>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/relatorio-acertos" target="_blank"
               class="px-4 py-2 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 text-sm font-medium rd-no-print">
                Matriz de Desempenho
            </a>
            <?php if (!empty($bloco['gabarito_liberado'])): ?>
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-100 text-emerald-800 text-sm font-medium">
                <i class="fa-solid fa-unlock-keyhole" aria-hidden="true"></i>
                Gabarito liberado
            </span>
            <?php else: ?>
            <form method="post" action="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/liberar-gabarito" class="inline"
                  onsubmit="return confirm('Liberar o gabarito deste bloco para todos os alunos?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($csrf_token ?? '')) ?>">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 text-sm font-medium">
                    <i class="fa-solid fa-unlock-keyhole" aria-hidden="true"></i>
                    Liberar gabarito
                </button>
            </form>
            <?php endif; ?>
            <button type="button" id="rd-btn-ia"
                    class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-medium">
                Analisar com IA
            </button>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar"
               class="px-4 py-2 rounded-lg bg-gray-600 text-white hover:bg-gray-700 text-sm font-medium">Voltar</a>
        </div>
    </div>

    <!-- Indicadores -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6 gap-4 mb-8">
        <?php
        $cards = [
            ['label' => 'Aprovados', 'value' => (int)($ind['aprovados'] ?? 0), 'color' => 'green', 'sub' => '≥ ' . $mediaMin . '%'],
            ['label' => 'Precisam de atenção', 'value' => (int)($ind['precisam_atencao'] ?? 0), 'color' => 'amber', 'sub' => '< meta'],
            ['label' => 'Concluíram', 'value' => (int)($ind['concluiram'] ?? 0), 'color' => 'blue', 'sub' => 'finalizaram'],
            ['label' => 'Não realizaram', 'value' => (int)($ind['nao_realizaram'] ?? 0), 'color' => 'slate', 'sub' => 'elegíveis'],
            ['label' => 'Média geral', 'value' => number_format((float)($ind['media_geral'] ?? 0), 1, ',', '') . '%', 'color' => 'indigo', 'sub' => 'do bloco'],
            ['label' => 'Tempo médio', 'value' => $ind['tempo_medio_label'] ?? '—', 'color' => 'purple', 'sub' => 'por aluno'],
        ];
        $border = ['green' => 'border-green-200', 'amber' => 'border-amber-200', 'blue' => 'border-blue-200', 'slate' => 'border-slate-200', 'indigo' => 'border-indigo-200', 'purple' => 'border-purple-200'];
        $text = ['green' => 'text-green-700', 'amber' => 'text-amber-700', 'blue' => 'text-blue-700', 'slate' => 'text-slate-700', 'indigo' => 'text-indigo-700', 'purple' => 'text-purple-700'];
        foreach ($cards as $c):
        ?>
        <div class="bg-white rounded-xl shadow-sm border <?= $border[$c['color']] ?> p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide"><?= htmlspecialchars($c['label']) ?></p>
            <p class="text-2xl font-bold <?= $text[$c['color']] ?> mt-1"><?= htmlspecialchars((string)$c['value']) ?></p>
            <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($c['sub']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Desempenho por turma</h3>
            <div id="rd-chart-turmas" class="space-y-3 min-h-[120px]"></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Desempenho por disciplina</h3>
            <div id="rd-chart-disciplinas" class="space-y-3 min-h-[120px]"></div>
        </div>
    </div>

    <!-- Heatmap -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8 overflow-x-auto">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Heatmap pedagógico</h3>
            <div class="flex flex-wrap gap-3 text-xs text-gray-600">
                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-200"></span> Acima da meta (≥70%)</span>
                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-200"></span> Atenção</span>
                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-200"></span> Crítico</span>
            </div>
        </div>
        <div id="rd-heatmap"></div>
    </div>

    <!-- Questões críticas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-red-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Questões mais erradas</h3>
            <div id="rd-questoes-erradas" class="space-y-3 max-h-96 overflow-y-auto"></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-green-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Questões mais acertadas</h3>
            <div id="rd-questoes-acertadas" class="space-y-3 max-h-96 overflow-y-auto"></div>
        </div>
    </div>

    <!-- Alunos em atenção -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-8">
        <h3 class="text-lg font-semibold text-amber-950 mb-2">Alunos em atenção</h3>
        <p class="text-sm text-amber-800 mb-4">Nota abaixo de <?= $mediaMin ?>%, não concluiu ou alto índice de erros.</p>
        <div id="rd-alunos-atencao" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4"></div>
        <div id="rd-atencao-paginacao" class="mt-4 flex justify-center gap-2"></div>
    </div>

    <!-- Rankings -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top 10 alunos</h3>
            <div id="rd-ranking-alunos"></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top 10 turmas</h3>
            <div id="rd-ranking-turmas"></div>
        </div>
    </div>

    <!-- Tabela completa -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Tabela completa de alunos</h3>
            <div class="flex flex-wrap gap-2 items-center">
                <input type="search" id="rd-busca" placeholder="Buscar aluno ou RA…"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm min-w-[200px] w-full sm:w-auto" autocomplete="off">
                <select id="rd-page-size" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="20">20 por página</option>
                    <option value="50">50 por página</option>
                    <option value="100">100 por página</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Nome</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">RA</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Série</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Turma</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Acertos</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Erros</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Pendentes</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Respondidas</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">% geral</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Tempo</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Status</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Ações</th>
                    </tr>
                </thead>
                <tbody id="rd-tabela-body" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
        <div id="rd-tabela-paginacao" class="mt-4 flex justify-center gap-2"></div>
    </div>
</div>

<!-- Drawer filtros -->
<div id="rd-drawer-backdrop" class="fixed inset-0 bg-black/40 z-40 hidden" aria-hidden="true"></div>
<aside id="rd-drawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col">
    <div class="p-5 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Filtros</h3>
        <button type="button" id="rd-drawer-close" class="p-2 rounded-lg hover:bg-gray-100" aria-label="Fechar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto p-5 space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Série</label>
            <select id="rd-f-serie" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Todas</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
            <select id="rd-f-turma" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Todas</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Disciplina</label>
            <select id="rd-f-materia" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Todas</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Professor</label>
            <select id="rd-f-professor" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Todos</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select id="rd-f-status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Todos</option>
                <option value="concluido">Concluído</option>
                <option value="em_andamento">Em andamento</option>
                <option value="nao_iniciado">Não iniciado</option>
                <option value="cancelada">Cancelada</option>
            </select>
        </div>
    </div>
    <div class="p-5 border-t border-gray-200 flex gap-2">
        <button type="button" id="rd-f-limpar" class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium">Limpar</button>
        <button type="button" id="rd-f-aplicar" class="flex-1 px-4 py-2 rounded-lg bg-violet-600 text-white hover:bg-violet-700 text-sm font-medium">Aplicar filtros</button>
    </div>
</aside>

<!-- Modal IA -->
<div id="rd-modal-ia" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" data-close-ia></div>
    <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Análise pedagógica com IA</h3>
        <p class="text-sm text-gray-600 mb-4">Em breve: resumo automático, pontos fortes, plano de recuperação e atividades recomendadas com base nos resultados desta avaliação.</p>
        <button type="button" data-close-ia class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm font-medium">Fechar</button>
    </div>
</div>

<script>
(function() {
    var DASH = <?= $jsonDashboard ?: '{}' ?>;
    var BLOCO_ID = <?= $blocoId ?>;
    var BASE_URL = <?= json_encode(URL) ?>;

    var state = {
        filtros: { serie: '', turma: '', materia: '', professor: '', status: '' },
        busca: '',
        pageSize: 20,
        pageTabela: 1,
        pageAtencao: 1,
        pageSizeAtencao: 20
    };

    var buscaDebounce = null;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function filtrarAlunos() {
        var list = (DASH.alunos || []).slice();
        var f = state.filtros;
        if (f.serie) list = list.filter(function(a) { return a.serie === f.serie; });
        if (f.turma) list = list.filter(function(a) { return a.turma_nome === f.turma; });
        if (f.status) list = list.filter(function(a) { return a.status === f.status; });
        if (state.busca) {
            var q = state.busca.toLowerCase();
            list = list.filter(function(a) {
                return (a.nome || '').toLowerCase().indexOf(q) >= 0
                    || (a.ra || '').toLowerCase().indexOf(q) >= 0;
            });
        }
        return list;
    }

    function writeDrawerFilters() {
        document.getElementById('rd-f-serie').value = state.filtros.serie || '';
        document.getElementById('rd-f-turma').value = state.filtros.turma || '';
        document.getElementById('rd-f-materia').value = state.filtros.materia || '';
        document.getElementById('rd-f-professor').value = state.filtros.professor || '';
        document.getElementById('rd-f-status').value = state.filtros.status || '';
    }

    function readDrawerFilters() {
        state.filtros.serie = document.getElementById('rd-f-serie').value;
        state.filtros.turma = document.getElementById('rd-f-turma').value;
        state.filtros.materia = document.getElementById('rd-f-materia').value;
        state.filtros.professor = document.getElementById('rd-f-professor').value;
        state.filtros.status = document.getElementById('rd-f-status').value;
    }

    function applyFilters(resetPage) {
        if (resetPage !== false) {
            state.pageTabela = 1;
            state.pageAtencao = 1;
        }
        renderAll();
    }

    function clearDrawerFilters() {
        state.filtros = { serie: '', turma: '', materia: '', professor: '', status: '' };
        writeDrawerFilters();
        applyFilters();
    }

    function fillSelect(id, options) {
        var sel = document.getElementById(id);
        if (!sel) return;
        (options || []).forEach(function(o) {
            var opt = document.createElement('option');
            opt.value = o;
            opt.textContent = o;
            sel.appendChild(opt);
        });
    }

    function barChart(containerId, items, labelKey, valueKey) {
        var el = document.getElementById(containerId);
        if (!el) return;
        if (!items || !items.length) {
            el.innerHTML = '<p class="text-sm text-gray-500">Sem dados para exibir.</p>';
            return;
        }
        var max = Math.max.apply(null, items.map(function(i) { return i[valueKey] || 0; }).concat([100]));
        el.innerHTML = items.map(function(item) {
            var pct = item[valueKey] || 0;
            var w = max > 0 ? Math.round((pct / max) * 100) : 0;
            return '<div><div class="flex justify-between text-sm mb-1"><span class="font-medium text-gray-800 truncate pr-2">' + esc(item[labelKey]) + '</span><span class="text-indigo-600 font-semibold shrink-0">' + pct + '%</span></div>' +
                '<div class="h-2.5 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-indigo-500 rounded-full" style="width:' + w + '%"></div></div></div>';
        }).join('');
    }

    function renderHeatmap() {
        var el = document.getElementById('rd-heatmap');
        var hm = DASH.heatmap || {};
        var turmas = hm.turmas || [];
        var discs = hm.disciplinas || [];
        var cells = hm.cells || {};
        if (!turmas.length || !discs.length) {
            el.innerHTML = '<p class="text-sm text-gray-500">Sem dados para o heatmap.</p>';
            return;
        }
        var nivelBg = { bom: 'bg-green-200 text-green-900', atencao: 'bg-amber-200 text-amber-900', critico: 'bg-red-200 text-red-900' };
        var html = '<table class="min-w-full text-xs"><thead><tr><th class="p-2 text-left text-gray-500">Disciplina</th>';
        turmas.forEach(function(t) { html += '<th class="p-2 text-center text-gray-600">' + esc(t) + '</th>'; });
        html += '</tr></thead><tbody>';
        discs.forEach(function(d) {
            html += '<tr><td class="p-2 font-medium text-gray-800 whitespace-nowrap">' + esc(d) + '</td>';
            turmas.forEach(function(t) {
                var cell = cells[t] && cells[t][d];
                if (!cell) {
                    html += '<td class="p-2 text-center text-gray-300">—</td>';
                } else {
                    var cls = nivelBg[cell.nivel] || 'bg-gray-100';
                    html += '<td class="p-2 text-center"><span class="inline-block px-2 py-1 rounded ' + cls + ' font-semibold">' + cell.percentual + '%</span></td>';
                }
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        el.innerHTML = html;
    }

    function renderQuestoes(containerId, list, tipo) {
        var el = document.getElementById(containerId);
        if (!el) return;
        if (!list || !list.length) {
            el.innerHTML = '<p class="text-sm text-gray-500">Nenhuma questão com respostas.</p>';
            return;
        }
        el.innerHTML = list.map(function(q, i) {
            var taxa = tipo === 'erro' ? (q.taxa_erro || 0) : (q.taxa_acerto || 0);
            var badge = tipo === 'erro' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
            return '<div class="border border-gray-100 rounded-lg p-3">' +
                '<div class="flex justify-between gap-2 mb-1">' +
                '<span class="text-xs text-gray-500">Q' + (q.numero || (i+1)) + ' · ' + esc(q.materia_nome || '') + '</span>' +
                '<span class="text-xs font-semibold px-2 py-0.5 rounded ' + badge + '">' + taxa + '%</span></div>' +
                '<p class="text-sm text-gray-800">' + esc(q.enunciado_curto || q.enunciado || 'Questão') + '</p>' +
                '<p class="text-xs text-gray-500 mt-1">' + (q.total_respostas || 0) + ' respostas</p></div>';
        }).join('');
    }

    function statusBadge(status) {
        var map = {
            concluido: 'bg-green-100 text-green-800',
            em_andamento: 'bg-blue-100 text-blue-800',
            nao_iniciado: 'bg-gray-100 text-gray-700',
            cancelada: 'bg-amber-100 text-amber-800'
        };
        return map[status] || 'bg-gray-100 text-gray-700';
    }

    function paginate(list, page, size) {
        var start = (page - 1) * size;
        return { items: list.slice(start, start + size), total: list.length, pages: Math.max(1, Math.ceil(list.length / size)) };
    }

    function renderPaginacao(containerId, page, pages, onPage) {
        var el = document.getElementById(containerId);
        if (!el || pages <= 1) { if (el) el.innerHTML = ''; return; }
        var html = '';
        for (var p = 1; p <= pages; p++) {
            if (p === 1 || p === pages || Math.abs(p - page) <= 1) {
                html += '<button type="button" data-page="' + p + '" class="px-3 py-1 rounded text-sm ' + (p === page ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200') + '">' + p + '</button>';
            } else if (p === page - 2 || p === page + 2) {
                html += '<span class="px-1 text-gray-400">…</span>';
            }
        }
        el.innerHTML = html;
        el.querySelectorAll('[data-page]').forEach(function(btn) {
            btn.addEventListener('click', function() { onPage(parseInt(btn.getAttribute('data-page'), 10)); });
        });
    }

    function renderAtencao() {
        var list = filtrarAlunos().filter(function(a) { return a.precisa_atencao; });
        var pg = paginate(list, state.pageAtencao, state.pageSizeAtencao);
        var el = document.getElementById('rd-alunos-atencao');
        if (!pg.items.length) {
            el.innerHTML = '<p class="text-sm text-amber-800 col-span-full">Nenhum aluno em atenção com os filtros atuais.</p>';
            renderPaginacao('rd-atencao-paginacao', 1, 1, function() {});
            return;
        }
        el.innerHTML = pg.items.map(function(a) {
            return '<div class="bg-white rounded-lg border border-amber-200 p-4">' +
                '<p class="font-semibold text-gray-900">' + esc(a.nome) + '</p>' +
                '<p class="text-xs text-gray-500">' + esc(a.turma_nome) + ' · RA ' + esc(a.ra || '—') + '</p>' +
                '<div class="flex flex-wrap gap-2 mt-2 text-xs">' +
                '<span class="px-2 py-0.5 rounded ' + statusBadge(a.status) + '">' + esc(a.status_label) + '</span>' +
                '<span class="text-gray-600">' + (a.total_respostas || 0) + '/' + (a.total_questoes_bloco || 0) + ' respondidas · ' + (a.total_pendentes || 0) + ' pendentes</span>' +
                '<span class="text-gray-600">' + (a.percentual || 0) + '% geral · ' + esc(a.tempo_label) + '</span></div>' +
                '<a href="' + BASE_URL + '/admin/provas/blocos/' + BLOCO_ID + '/aluno/' + a.aluno_id + '/resultado" class="mt-3 inline-block text-sm text-violet-600 hover:text-violet-800 font-medium">Ver resultado →</a></div>';
        }).join('');
        renderPaginacao('rd-atencao-paginacao', state.pageAtencao, pg.pages, function(p) { state.pageAtencao = p; renderAtencao(); });
    }

    function renderRanking(containerId, items, tipo) {
        var el = document.getElementById(containerId);
        if (!items || !items.length) {
            el.innerHTML = '<p class="text-sm text-gray-500">Sem dados.</p>';
            return;
        }
        el.innerHTML = '<ol class="space-y-2">' + items.map(function(item, i) {
            var label = tipo === 'turma' ? item.turma_nome : item.nome;
            var pct = item.percentual || 0;
            if (tipo === 'aluno') {
                return '<li class="flex items-start gap-3 text-sm border-b border-gray-100 last:border-b-0 py-2">' +
                    '<span class="w-6 h-6 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-xs font-bold flex-shrink-0">' + (i+1) + '</span>' +
                    '<span class="flex-1 min-w-0"><span class="block font-medium text-gray-800">' + esc(label) + '</span>' +
                    '<span class="block text-xs text-gray-500">' + (item.total_acertos || 0) + ' acertos · ' + (item.total_erros || 0) + ' erros · ' + (item.total_pendentes || 0) + ' pendentes</span>' +
                    '<span class="block text-xs text-gray-500">' + (item.total_respostas || 0) + '/' + (item.total_questoes_bloco || 0) + ' respondidas · ' + (item.percentual_respondidas || 0) + '% das respondidas</span></span>' +
                    '<span class="font-semibold text-indigo-600 whitespace-nowrap">' + pct + '% geral</span></li>';
            }
            return '<li class="flex items-center gap-3 text-sm"><span class="w-6 h-6 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-xs font-bold">' + (i+1) + '</span>' +
                '<span class="flex-1 font-medium text-gray-800">' + esc(label) + '</span>' +
                '<span class="font-semibold text-indigo-600">' + pct + '%</span></li>';
        }).join('') + '</ol>';
    }

    function renderTabela() {
        var list = filtrarAlunos();
        var pg = paginate(list, state.pageTabela, state.pageSize);
        var tbody = document.getElementById('rd-tabela-body');
        if (!pg.items.length) {
            tbody.innerHTML = '<tr><td colspan="12" class="px-3 py-8 text-center text-gray-500">Nenhum aluno encontrado.</td></tr>';
            renderPaginacao('rd-tabela-paginacao', 1, 1, function() {});
            return;
        }
        tbody.innerHTML = pg.items.map(function(a) {
            var pctCls = (a.percentual || 0) >= <?= (int)$mediaMin ?> ? 'text-green-700' : 'text-red-600';
            return '<tr class="hover:bg-gray-50">' +
                '<td class="px-3 py-2 font-medium text-gray-900">' + esc(a.nome) + '</td>' +
                '<td class="px-3 py-2 text-gray-600">' + esc(a.ra || '—') + '</td>' +
                '<td class="px-3 py-2 text-gray-600">' + esc(a.serie || '—') + '</td>' +
                '<td class="px-3 py-2 text-gray-600">' + esc(a.turma_nome) + '</td>' +
                '<td class="px-3 py-2 text-green-700">' + (a.total_acertos || 0) + '</td>' +
                '<td class="px-3 py-2 text-red-600">' + (a.total_erros || 0) + '</td>' +
                '<td class="px-3 py-2 text-gray-600">' + (a.total_pendentes || 0) + '</td>' +
                '<td class="px-3 py-2 text-gray-600">' + (a.total_respostas || 0) + '/' + (a.total_questoes_bloco || 0) + '</td>' +
                '<td class="px-3 py-2 font-semibold ' + pctCls + '">' + (a.percentual || 0) + '%</td>' +
                '<td class="px-3 py-2 text-gray-600">' + esc(a.tempo_label) + '</td>' +
                '<td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs ' + statusBadge(a.status) + '">' + esc(a.status_label) + '</span></td>' +
                '<td class="px-3 py-2"><a href="' + BASE_URL + '/admin/provas/blocos/' + BLOCO_ID + '/aluno/' + a.aluno_id + '/resultado" class="text-violet-600 hover:text-violet-800 font-medium">Ver</a></td></tr>';
        }).join('');
        renderPaginacao('rd-tabela-paginacao', state.pageTabela, pg.pages, function(p) { state.pageTabela = p; renderTabela(); });
    }

    function csvCell(value) {
        var text = value == null ? '' : String(value);
        return '"' + text.replace(/"/g, '""') + '"';
    }

    function slugArquivo(text) {
        return String(text || 'bloco').replace(/[^a-zA-Z0-9]+/g, '_').replace(/^_|_$/g, '');
    }

    function exportDashboardExcel() {
        var list = filtrarAlunos();
        var ind = DASH.indicadores || {};
        var linhas = [
            ['Resultados da Avaliação', <?= json_encode($bloco['titulo'] ?? '', JSON_HEX_TAG) ?>],
            ['Exportado em', new Date().toLocaleString('pt-BR')],
            [],
            ['Indicador', 'Valor'],
            ['Aprovados', ind.aprovados || 0],
            ['Precisam de atenção', ind.precisam_atencao || 0],
            ['Concluíram', ind.concluiram || 0],
            ['Não realizaram', ind.nao_realizaram || 0],
            ['Média geral', (ind.media_geral || 0) + '%'],
            ['Tempo médio', ind.tempo_medio_label || '—'],
            [],
            ['Nome', 'RA', 'Série', 'Turma', 'Acertos', 'Erros', 'Pendentes', 'Respondidas', 'Percentual geral', 'Percentual respondidas', 'Tempo', 'Status']
        ];
        list.forEach(function(a) {
            linhas.push([
                a.nome || '',
                a.ra || '',
                a.serie || '',
                a.turma_nome || '',
                a.total_acertos || 0,
                a.total_erros || 0,
                a.total_pendentes || 0,
                (a.total_respostas || 0) + '/' + (a.total_questoes_bloco || 0),
                (a.percentual || 0) + '%',
                (a.percentual_respondidas || 0) + '%',
                a.tempo_label || '—',
                a.status_label || ''
            ]);
        });
        var csv = '\uFEFF' + linhas.map(function(row) {
            return row.map(csvCell).join(';');
        }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'resultados-' + slugArquivo(<?= json_encode($bloco['titulo'] ?? 'bloco', JSON_HEX_TAG) ?>) + '.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function exportDashboardPdf() {
        var savedPage = state.pageTabela;
        var savedSize = state.pageSize;
        state.pageTabela = 1;
        state.pageSize = 99999;
        if (document.getElementById('rd-page-size')) {
            document.getElementById('rd-page-size').value = '100';
        }
        renderTabela();
        var restore = function() {
            state.pageTabela = savedPage;
            state.pageSize = savedSize;
            if (document.getElementById('rd-page-size')) {
                document.getElementById('rd-page-size').value = String(savedSize);
            }
            renderTabela();
        };
        window.addEventListener('afterprint', function() {
            restore();
        }, { once: true });
        setTimeout(function() { window.print(); }, 150);
    }

    function disciplinasFiltradas() {
        var list = (DASH.por_disciplina || []).slice();
        if (state.filtros.materia) {
            list = list.filter(function(d) { return d.materia_nome === state.filtros.materia; });
        }
        if (state.filtros.professor) {
            list = list.filter(function(d) { return d.professor_nome === state.filtros.professor; });
        }
        return list;
    }

    function renderAll() {
        barChart('rd-chart-turmas', DASH.por_turma || [], 'turma_nome', 'percentual');
        barChart('rd-chart-disciplinas', disciplinasFiltradas().reverse(), 'materia_nome', 'percentual');
        renderHeatmap();
        renderQuestoes('rd-questoes-erradas', DASH.questoes_mais_erradas, 'erro');
        renderQuestoes('rd-questoes-acertadas', DASH.questoes_mais_acertadas, 'acerto');
        renderAtencao();
        renderRanking('rd-ranking-alunos', DASH.ranking_alunos, 'aluno');
        renderRanking('rd-ranking-turmas', DASH.ranking_turmas, 'turma');
        renderTabela();
    }

    function openDrawer() {
        document.getElementById('rd-drawer').classList.remove('translate-x-full');
        document.getElementById('rd-drawer-backdrop').classList.remove('hidden');
    }
    function closeDrawer() {
        document.getElementById('rd-drawer').classList.add('translate-x-full');
        document.getElementById('rd-drawer-backdrop').classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var filtros = DASH.filtros || {};
        fillSelect('rd-f-serie', filtros.series);
        fillSelect('rd-f-turma', filtros.turmas);
        fillSelect('rd-f-materia', filtros.materias);
        fillSelect('rd-f-professor', filtros.professores);

        renderAll();

        document.getElementById('rd-busca').addEventListener('input', function(e) {
            clearTimeout(buscaDebounce);
            buscaDebounce = setTimeout(function() {
                state.busca = (e.target.value || '').trim();
                state.pageTabela = 1;
                renderTabela();
            }, 250);
        });

        document.getElementById('rd-page-size').addEventListener('change', function(e) {
            state.pageSize = parseInt(e.target.value, 10) || 20;
            state.pageTabela = 1;
            renderTabela();
        });

        document.getElementById('rd-btn-filtros').addEventListener('click', function() {
            writeDrawerFilters();
            openDrawer();
        });
        document.getElementById('rd-drawer-close').addEventListener('click', closeDrawer);
        document.getElementById('rd-drawer-backdrop').addEventListener('click', closeDrawer);

        document.getElementById('rd-f-aplicar').addEventListener('click', function() {
            readDrawerFilters();
            closeDrawer();
            applyFilters();
        });

        document.getElementById('rd-f-limpar').addEventListener('click', clearDrawerFilters);

        document.getElementById('rd-btn-export-pdf').addEventListener('click', exportDashboardPdf);
        document.getElementById('rd-btn-export-excel').addEventListener('click', exportDashboardExcel);

        document.getElementById('rd-btn-ia').addEventListener('click', function() {
            document.getElementById('rd-modal-ia').classList.remove('hidden');
            document.getElementById('rd-modal-ia').classList.add('flex');
        });
        document.querySelectorAll('[data-close-ia]').forEach(function(el) {
            el.addEventListener('click', function() {
                document.getElementById('rd-modal-ia').classList.add('hidden');
                document.getElementById('rd-modal-ia').classList.remove('flex');
            });
        });
    });
})();
</script>

<style>
.rd-print-only { display: none; }
#rd-drawer input, #rd-drawer select,
#rd-busca, #rd-page-size {
    -webkit-appearance: none;
    appearance: none;
    min-height: 44px;
    font-size: 16px;
}
@media (max-width: 640px) {
    #rd-drawer { max-width: 100%; }
}
@page { size: A4 portrait; margin: 10mm; }
@media print {
    .rd-no-print,
    #rd-drawer, #rd-drawer-backdrop, #rd-modal-ia,
    #sidebar, #sidebar-overlay, header, footer,
    nav, .mobile-content > footer { display: none !important; }
    .rd-print-only { display: block !important; }
    body { background: #fff !important; }
    main, .mobile-content { padding: 0 !important; margin: 0 !important; }
    #results-dashboard-root { padding: 0 !important; }
    #rd-tabela-paginacao, #rd-atencao-paginacao { display: none !important; }
    #rd-busca, #rd-page-size { display: none !important; }
}
</style>
