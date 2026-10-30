(function () {
    'use strict';

    var root = document.getElementById('dash-gestao-root');
    if (!root) return;

    var widgetUrl = root.dataset.widgetUrl || '';
    var filtrosUrl = root.dataset.filtrosUrl || '';
    var baseUrl = root.dataset.baseUrl || '';
    var form = document.getElementById('dash-filtros');
    var charts = {};
    var coresLinha = ['#7c6af7', '#34d399', '#2dd4bf', '#60a5fa'];
    var coresBarra = ['#fb7185', '#fbbf24', '#60a5fa', '#a78bfa', '#34d399'];
    var coresDisc = ['#34d399', '#38bdf8', '#818cf8', '#fbbf24', '#f472b6', '#2dd4bf', '#a78bfa', '#fb923c'];
    var coresSit = {
        dentro_criterio: '#22c55e',
        recuperacao: '#f59e0b',
        atencao: '#fb923c',
        risco: '#fb7185'
    };
    var ordemSit = ['dentro_criterio', 'recuperacao', 'atencao', 'risco'];

    if (window.Chart && !window.__visaoCentro) {
        window.__visaoCentro = true;
        Chart.register({
            id: 'visaoCentro',
            afterDraw: function (chart) {
                var meta = chart.options.plugins && chart.options.plugins.visaoCentro;
                if (!meta || meta.valor == null) return;
                var ctx = chart.ctx;
                var area = chart.chartArea;
                var x = (area.left + area.right) / 2;
                var y = (area.top + area.bottom) / 2;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#0f172a';
                ctx.font = '700 22px ui-sans-serif, system-ui, sans-serif';
                ctx.fillText(meta.valor, x, y - 8);
                ctx.fillStyle = '#64748b';
                ctx.font = '12px ui-sans-serif, system-ui, sans-serif';
                ctx.fillText(meta.rotulo, x, y + 14);
                ctx.restore();
            }
        });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function href(path) {
        if (!path) return '#';
        if (path.indexOf('http') === 0) return path;
        return baseUrl + path;
    }

    function queryFiltros() {
        if (!form) return '';
        var data = new FormData(form);
        var params = new URLSearchParams();
        data.forEach(function (value, key) {
            if (value !== '') params.append(key, String(value));
        });
        return params.toString();
    }

    function fmtData(iso) {
        if (!iso || iso.length < 10) return '';
        var p = iso.slice(0, 10).split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function partesData(iso) {
        if (!iso || iso.length < 10) return null;
        var p = iso.slice(0, 10).split('-');
        var meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        var idx = (parseInt(p[1], 10) || 1) - 1;
        return { dia: p[2], mes: meses[idx] || '' };
    }

    function fmtNum(n) {
        if (n === null || n === undefined || n === '') return '—';
        var num = Number(n);
        if (!Number.isFinite(num)) return '—';
        return num.toLocaleString('pt-BR', { maximumFractionDigits: 1 });
    }

    function iniciais(nome) {
        var partes = String(nome || '').trim().split(/\s+/).filter(Boolean);
        if (!partes.length) return '?';
        if (partes.length === 1) return partes[0].slice(0, 2).toUpperCase();
        return (partes[0].charAt(0) + partes[partes.length - 1].charAt(0)).toUpperCase();
    }

    function destruirChart(id) {
        if (charts[id]) {
            charts[id].destroy();
            charts[id] = null;
        }
    }

    function opcoesEixo() {
        return {
            grid: { color: '#f1f5f9', drawBorder: false },
            border: { display: false },
            ticks: { color: '#94a3b8', font: { size: 11 } }
        };
    }

    function cardHtml(c) {
        var valor = c.vazio ? '' : (fmtNum(c.valor) + (c.sufixo || ''));
        var extra = '';
        if (c.delta_texto) {
            var cor = c.tom === 'negativo' ? 'text-rose-600' : (c.tom === 'positivo' ? 'text-emerald-600' : 'text-slate-500');
            var seta = c.tom === 'negativo' ? 'fa-arrow-down' : (c.tom === 'positivo' ? 'fa-arrow-up' : 'fa-minus');
            extra = '<p class="mt-1 text-xs ' + cor + '"><i class="fa-solid ' + seta + ' text-[10px] mr-0.5"></i>' +
                esc(c.delta_texto) +
                (c.detalhe ? ' <span class="text-slate-400 font-normal">' + esc(c.detalhe) + '</span>' : '') +
                '</p>';
        } else if (c.detalhe) {
            extra = '<p class="mt-1 text-xs text-slate-400">' + esc(c.detalhe) + '</p>';
        }
        var inner =
            '<div class="flex items-start gap-3">' +
            '<div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full ' + esc(c.iconClass || 'bg-slate-100 text-slate-600') + '">' +
            '<i class="fa-solid ' + esc(c.icon || 'fa-circle') + '"></i></div>' +
            '<div class="min-w-0"><p class="text-xs text-slate-500">' + esc(c.label) + '</p>' +
            (c.vazio
                ? '<p class="mt-1 text-base font-semibold text-slate-500">' + esc(c.vazio) + '</p>'
                : '<p class="mt-0.5 text-[1.65rem] font-bold leading-none tracking-tight text-slate-900">' + esc(valor) + '</p>') +
            extra + '</div></div>';
        var cls = 'rounded-2xl border border-slate-200/80 bg-white px-5 py-4 shadow-sm hover:shadow-md transition-shadow block';
        if (c.href) {
            return '<a href="' + esc(href(c.href)) + '" class="' + cls + '">' + inner + '</a>';
        }
        return '<div class="' + cls + '">' + inner + '</div>';
    }

    function cardsGrid(cards) {
        var n = (cards || []).length;
        var colCls = n <= 3 ? 'grid-cols-1 sm:grid-cols-3' : 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-4';
        return '<div class="grid ' + colCls + ' gap-4">' + (cards || []).map(cardHtml).join('') + '</div>';
    }

    function linkAcao(url, rotulo) {
        if (!url) return '';
        return '<a class="text-xs font-medium text-accent hover:underline" href="' + esc(href(url)) + '">' + esc(rotulo) + '</a>';
    }

    function titulo(t, extra) {
        return '<div class="flex items-center justify-between gap-3 mb-4"><h3 class="text-sm font-semibold text-slate-900">' +
            esc(t) + '</h3>' + (extra || '') + '</div>';
    }

    function vazio(msg) {
        return '<p class="text-sm text-slate-500">' + esc(msg || 'Nada para mostrar neste recorte.') + '</p>';
    }

    function metricas(items) {
        return '<div class="grid grid-cols-2 gap-3">' + items.map(function (it) {
            return '<div><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">' + esc(it.label) +
                '</p><p class="mt-1 text-xl font-bold text-slate-900">' + esc(it.valor) + '</p></div>';
        }).join('') + '</div>';
    }

    function renderKpis(el, data) {
        el.innerHTML = cardsGrid(data.cards || []);
    }
    function renderPendencias(el, data) {
        el.innerHTML = cardsGrid(data.cards || []);
    }

    function renderFrequenciaHoje(el, data) {
        var turmas = (data.turmas || []).slice();
        var link = data.links && data.links.presenca ? linkAcao(data.links.presenca, 'Presença') : linkAcao(data.links && data.links.diario, 'Diário');
        if (!turmas.length) {
            var r = data.resumo || {};
            el.innerHTML = titulo('Frequência por turma', link) +
                vazio('Nenhuma chamada neste período.') +
                (r.percentual != null ? '<p class="mt-3 text-xs text-slate-400">Hoje: ' + esc(String(r.percentual)) + '%</p>' : '');
            return;
        }
        el.innerHTML = titulo('Frequência por turma (top 5)', '<div class="flex items-center gap-3">' + link +
            '<select data-ordem class="rounded-lg border border-slate-200 bg-white py-1 pl-2 pr-7 text-xs text-slate-600">' +
            '<option value="maiores">Maiores</option><option value="menores">Menores</option></select></div>') +
            '<div data-barras></div>';
        var sel = el.querySelector('[data-ordem]');
        var pintar = function () {
            var ordem = sel ? sel.value : 'maiores';
            var lista = turmas.slice().sort(function (a, b) {
                return ordem === 'menores'
                    ? (Number(a.percentual) - Number(b.percentual))
                    : (Number(b.percentual) - Number(a.percentual));
            }).slice(0, 5);
            var box = el.querySelector('[data-barras]');
            if (!box) return;
            box.innerHTML = lista.map(function (t, i) {
                var pct = Math.max(0, Math.min(100, Number(t.percentual) || 0));
                var cor = i === 0 ? '#16a34a' : (i === 1 ? '#22c55e' : (i === 2 ? '#4ade80' : '#86efac'));
                return '<div class="flex items-center gap-3 py-1.5">' +
                    '<span class="w-24 shrink-0 truncate text-sm text-slate-600">' + esc(t.nome) + '</span>' +
                    '<div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">' +
                    '<div class="h-full rounded-full" style="width:' + pct + '%;background:' + cor + '"></div></div>' +
                    '<span class="w-10 text-right text-sm font-semibold text-slate-800">' + esc(String(Math.round(pct))) + '%</span></div>';
            }).join('');
        };
        if (sel) sel.addEventListener('change', pintar);
        pintar();
    }

    function renderDesempenho(el, data) {
        var disc = document.getElementById('dash-slot-disciplinas');
        var dist = document.getElementById('dash-slot-distribuicao');
        var sit = document.getElementById('dash-slot-situacao');
        var msg = (data && data.motivo) || 'Nada para mostrar neste recorte.';
        if (!data || !data.disponivel) {
            if (disc) disc.innerHTML = titulo('Desempenho por disciplina') + vazio(msg);
            if (dist) dist.innerHTML = titulo('Distribuição de notas') + vazio(msg);
            if (sit) sit.innerHTML = titulo('Situação dos alunos') + vazio(msg);
            return;
        }
        var link = linkAcao(data.href, 'Resultados');
        var badge = data.periodo_rotulo
            ? '<span class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-500">' + esc(data.periodo_rotulo) + '</span>'
            : '';
        if (disc) {
            var itens = data.disciplinas || [];
            disc.innerHTML = titulo('Desempenho por disciplina', badge || link) +
                (itens.length ? itens.map(function (it, i) {
                    var pct = Math.max(4, Math.min(100, (Number(it.media) / 10) * 100));
                    return '<div class="flex items-center gap-3 py-1.5">' +
                        '<span class="w-28 shrink-0 truncate text-sm text-slate-600">' + esc(it.nome) + '</span>' +
                        '<div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">' +
                        '<div class="h-full rounded-full" style="width:' + pct + '%;background:' + coresDisc[i % coresDisc.length] + '"></div></div>' +
                        '<span class="w-8 text-right text-sm font-semibold text-slate-800">' + esc(fmtNum(it.media)) + '</span></div>';
                }).join('') : vazio('Sem média homologada por componente neste período.'));
        }
        if (dist) {
            dist.innerHTML = titulo('Distribuição de notas', link) + '<div class="relative h-56"><canvas id="dash-dist-chart"></canvas></div>';
            desenharDistribuicao(data.distribuicao || []);
        }
        if (sit) {
            var buckets = data.buckets || {};
            var rotulos = data.rotulos || {};
            var chaves = (data.chaves && data.chaves.length) ? data.chaves : ordemSit;
            var total = Number(data.total) || 0;
            var totalSit = chaves.reduce(function (acc, k) { return acc + Number(buckets[k] || 0); }, 0);
            if (!totalSit) {
                sit.innerHTML = titulo('Situação dos alunos', link) + vazio(data.nota || 'A situação oficial aparece quando o período é homologado.');
            } else {
            var legenda = chaves.map(function (k) {
                var n = Number(buckets[k] || 0);
                var pct = total > 0 ? Math.round((n / total) * 100) : 0;
                return '<li class="flex items-center justify-between gap-2 text-sm">' +
                    '<span class="flex items-center gap-2 min-w-0 text-slate-600"><span class="h-2.5 w-2.5 rounded-full" style="background:' + (coresSit[k] || '#cbd5e1') + '"></span>' +
                    esc(rotulos[k] || k) + '</span>' +
                    '<span class="shrink-0 text-slate-500">' + pct + '% <span class="text-slate-400">(' + fmtNum(n) + ')</span></span></li>';
            }).join('');
            sit.innerHTML = titulo('Situação dos alunos', link) +
                '<div class="flex items-center gap-2">' +
                '<div class="relative h-44 w-44 shrink-0"><canvas id="dash-sit-chart"></canvas></div>' +
                '<ul class="flex-1 space-y-2">' + legenda + '</ul></div>' +
                (data.nota ? '<p class="mt-3 text-xs text-slate-400">' + esc(data.nota) + '</p>' : '');
            desenharSituacao(buckets, rotulos, total, chaves);
            }
        }
    }

    function desenharDistribuicao(faixas) {
        destruirChart('dist');
        if (typeof Chart === 'undefined') return;
        var canvas = document.getElementById('dash-dist-chart');
        if (!canvas) return;
        charts.dist = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: faixas.map(function (f) { return f.faixa; }),
                datasets: [{
                    data: faixas.map(function (f) { return Number(f.total) || 0; }),
                    backgroundColor: coresBarra,
                    borderRadius: 8,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: Object.assign(opcoesEixo(), { grid: { display: false } }),
                    y: Object.assign(opcoesEixo(), { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8', font: { size: 11 } } })
                }
            }
        });
    }

    function desenharSituacao(buckets, rotulos, total, chaves) {
        destruirChart('sit');
        if (typeof Chart === 'undefined') return;
        var canvas = document.getElementById('dash-sit-chart');
        if (!canvas) return;
        var keys = chaves && chaves.length ? chaves : ordemSit;
        charts.sit = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: keys.map(function (k) { return rotulos[k] || k; }),
                datasets: [{
                    data: keys.map(function (k) { return Number(buckets[k] || 0); }),
                    backgroundColor: keys.map(function (k) { return coresSit[k]; }),
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    visaoCentro: { valor: fmtNum(total), rotulo: 'alunos' }
                }
            }
        });
    }

    function renderEvolucao(el, data) {
        var series = (data.series || []).filter(function (s) { return !s.ocultar; });
        var tem = series.some(function (s) {
            return (s.data || []).some(function (v) { return v != null; });
        });
        var legenda = series.map(function (s, i) {
            return '<span class="inline-flex items-center gap-1.5 text-xs text-slate-500">' +
                '<span class="h-2 w-2 rounded-full" style="background:' + coresLinha[i % coresLinha.length] + '"></span>' +
                esc(s.label) + '</span>';
        }).join('');
        var notas = data.modo === 'notas';
        el.innerHTML = titulo(notas ? 'Evolução das notas' : 'Evolução da frequência', legenda) +
            (tem ? '<div class="relative h-64"><canvas id="dash-evolucao-chart"></canvas></div>' : vazio(data.nota || 'Sem chamada no ano letivo.')) +
            (tem && data.nota ? '<p class="mt-3 text-xs text-slate-400">' + esc(data.nota) + '</p>' : '');
        destruirChart('evo');
        if (!tem || typeof Chart === 'undefined') return;
        var canvas = document.getElementById('dash-evolucao-chart');
        if (!canvas) return;
        charts.evo = new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: series.map(function (s, i) {
                    var cor = coresLinha[i % coresLinha.length];
                    return {
                        label: s.label,
                        data: (s.data || []).map(function (v) { return v == null ? null : Number(v); }),
                        borderColor: cor,
                        backgroundColor: i === 0 ? 'rgba(124,106,247,0.12)' : 'transparent',
                        fill: i === 0,
                        tension: 0.35,
                        spanGaps: true,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        borderWidth: 2
                    };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#fff',
                        titleColor: '#0f172a',
                        bodyColor: '#475569',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function (ctx) {
                                var v = ctx.parsed.y;
                                var sufixo = notas ? '' : '%';
                                return ' ' + ctx.dataset.label + '  ' + (v == null ? '—' : v.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + sufixo);
                            }
                        }
                    }
                },
                scales: {
                    x: Object.assign(opcoesEixo(), { grid: { display: false } }),
                    y: Object.assign(opcoesEixo(), notas ? {
                        suggestedMin: 0,
                        suggestedMax: 10,
                        ticks: { color: '#94a3b8', font: { size: 11 } }
                    } : {
                        suggestedMin: 60,
                        suggestedMax: 100,
                        ticks: { callback: function (v) { return v + '%'; }, color: '#94a3b8', font: { size: 11 } }
                    })
                }
            }
        });
    }

    function renderAtencao(el, data) {
        var linhas = data.linhas || [];
        var cores = ['bg-violet-100 text-violet-700', 'bg-sky-100 text-sky-700', 'bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700', 'bg-rose-100 text-rose-700'];
        var ponto = { critico: 'bg-rose-500', atencao: 'bg-amber-400', recuperacao: 'bg-emerald-500' };
        var texto = { critico: 'text-rose-600', atencao: 'text-amber-600', recuperacao: 'text-emerald-600' };
        var rows = linhas.length === 0
            ? '<tr><td colspan="4" class="px-2 py-8 text-center text-sm text-slate-500">Nenhum aluno em atenção neste recorte.</td></tr>'
            : linhas.slice(0, 5).map(function (l, i) {
                return '<tr class="border-t border-slate-100">' +
                    '<td class="px-2 py-2.5"><div class="flex items-center gap-2">' +
                    '<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ' + cores[i % cores.length] + '">' + esc(iniciais(l.aluno)) + '</span>' +
                    '<span class="text-sm font-medium text-slate-800">' + esc(l.aluno) + '</span></div></td>' +
                    '<td class="px-2 py-2.5 text-sm text-slate-500">' + esc(l.turma) + '</td>' +
                    '<td class="px-2 py-2.5 text-sm text-slate-600">' + esc(l.indicador || '') +
                    (l.valor ? ' <span class="font-semibold text-slate-800">' + esc(l.valor) + '</span>' : '') + '</td>' +
                    '<td class="px-2 py-2.5 text-sm ' + (texto[l.badge] || 'text-slate-600') + '">' +
                    '<span class="mr-1.5 inline-block h-2 w-2 rounded-full ' + (ponto[l.badge] || 'bg-slate-300') + '"></span>' +
                    esc(l.situacao) + '</td></tr>';
            }).join('');
        el.innerHTML = titulo('Atenção pedagógica', linkAcao(data.href, 'Ver todos')) +
            '<div class="overflow-x-auto"><table class="min-w-full"><thead><tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-400">' +
            '<th class="px-2 py-2">Aluno</th><th class="px-2 py-2">Turma</th><th class="px-2 py-2">Indicador</th><th class="px-2 py-2">Situação</th></tr></thead><tbody>' +
            rows + '</tbody></table></div>';
    }

    function renderAvaliacoes(el, data) {
        var proximas = (data.proximas || []).map(function (p) {
            return '<li class="flex justify-between text-sm py-1 border-t border-slate-100"><span class="truncate pr-2">' +
                esc(p.titulo) + '</span><span class="text-slate-500 shrink-0">' + esc(fmtData(p.data)) + '</span></li>';
        }).join('');
        el.innerHTML = titulo('Avaliações', linkAcao(data.href, 'Avaliações')) + metricas([
            { label: 'Hoje', valor: fmtNum(data.hoje) },
            { label: 'Na semana', valor: fmtNum(data.semana) },
            { label: 'Aguardando correção', valor: fmtNum(data.aguardando_correcao) },
            { label: 'Notas pendentes', valor: fmtNum(data.notas_pendentes) }
        ]) + (proximas ? '<p class="mt-4 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Próximas</p><ul>' + proximas + '</ul>' : '');
    }

    function renderConselho(el, data) {
        if (!data.disponivel) {
            el.innerHTML = titulo('Conselho de Classe') + vazio(data.motivo);
            return;
        }
        el.innerHTML = titulo('Conselho de Classe', linkAcao(data.href, 'Conselhos')) + metricas([
            { label: 'Turmas prontas', valor: fmtNum(data.turmas_prontas) },
            { label: 'Com pendências', valor: fmtNum(data.turmas_com_pendencias) },
            { label: 'Realizados', valor: fmtNum(data.realizados) },
            { label: 'Aguardando', valor: fmtNum(data.aguardando) }
        ]);
    }

    function renderOcorrencias(el, data) {
        if (!data.disponivel) {
            el.innerHTML = titulo('Ocorrências (últimos 14 dias)') + vazio(data.motivo);
            return;
        }
        var cats = data.por_categoria || [];
        el.innerHTML = titulo('Ocorrências (últimos 14 dias)', linkAcao(data.href, 'Ver todas')) +
            (cats.length
                ? '<div class="relative h-52"><canvas id="dash-oco-chart"></canvas></div>'
                : vazio('Nenhuma ocorrência nos últimos 14 dias.'));
        destruirChart('oco');
        if (!cats.length || typeof Chart === 'undefined') return;
        var canvas = document.getElementById('dash-oco-chart');
        if (!canvas) return;
        charts.oco = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: cats.map(function (c) { return c.nome; }),
                datasets: [{
                    data: cats.map(function (c) { return Number(c.total) || 0; }),
                    backgroundColor: ['#fb7185', '#fb923c', '#93c5fd', '#60a5fa', '#a78bfa', '#34d399'],
                    borderRadius: 8,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: Object.assign(opcoesEixo(), { grid: { display: false } }),
                    y: Object.assign(opcoesEixo(), { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8', font: { size: 11 } } })
                }
            }
        });
    }

    function renderCalendario(el, data) {
        var itens = (data.eventos || []).slice(0, 4).map(function (e) {
            var dt = partesData(e.data);
            var detalhe = [e.hora, e.local || e.tipo].filter(Boolean).join(' · ');
            var badge = dt
                ? '<div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-violet-50 text-violet-700">' +
                  '<span class="text-base font-bold leading-none">' + esc(dt.dia) + '</span>' +
                  '<span class="text-[10px] uppercase tracking-wide">' + esc(dt.mes) + '</span></div>'
                : '';
            return '<li class="flex items-center gap-3 py-2">' + badge +
                '<div class="min-w-0"><p class="truncate text-sm font-medium text-slate-800">' + esc(e.titulo) + '</p>' +
                '<p class="text-xs text-slate-400">' + esc(detalhe || fmtData(e.data)) + '</p></div></li>';
        }).join('');
        var link = linkAcao(data.href_letivo || data.href_escolar, 'Ver calendário');
        el.innerHTML = titulo('Próximos eventos', link) +
            (itens ? '<ul>' + itens + '</ul>' : vazio('Nenhum evento próximo.'));
    }

    function renderMatriculas(el, data) {
        el.innerHTML = titulo('Matrículas e movimentações', linkAcao(data.href, 'Alunos')) + metricas([
            { label: 'Ativas', valor: fmtNum(data.ativas) },
            { label: 'Novas no período', valor: fmtNum(data.novas) },
            { label: 'Transferências', valor: fmtNum(data.transferencias) },
            { label: 'Outras movimentações', valor: fmtNum(data.cancelamentos) }
        ]);
    }

    var renderers = {
        kpis: renderKpis,
        pendencias: renderPendencias,
        frequencia_hoje: renderFrequenciaHoje,
        desempenho: renderDesempenho,
        evolucao: renderEvolucao,
        atencao_pedagogica: renderAtencao,
        avaliacoes: renderAvaliacoes,
        conselho: renderConselho,
        ocorrencias: renderOcorrencias,
        calendario: renderCalendario,
        matriculas: renderMatriculas
    };

    function falha(el) {
        if (el.dataset.widget === 'desempenho') {
            renderDesempenho(el, { disponivel: false, motivo: 'Não foi possível carregar este bloco.' });
            return;
        }
        el.innerHTML = '<p class="text-sm text-slate-500">Não foi possível carregar este bloco.</p>';
    }

    function carregarWidget(el) {
        var chave = el.dataset.widget;
        if (!chave) return;
        var qs = queryFiltros();
        fetch(widgetUrl + '/' + encodeURIComponent(chave) + (qs ? '?' + qs : ''), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.ok === false) {
                    falha(el);
                    return;
                }
                var fn = renderers[chave];
                if (fn) fn(el, data);
            })
            .catch(function () { falha(el); });
    }

    function carregarTodos() {
        root.querySelectorAll('[data-widget]').forEach(carregarWidget);
    }

    function preencherSelect(sel, itens, valueKey, labelKey, atual) {
        if (!sel) return;
        var keepFirst = sel.querySelector('option[value=""]');
        var rotuloPrimeiro = keepFirst ? keepFirst.textContent : 'Todas';
        sel.innerHTML = '';
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = rotuloPrimeiro;
        sel.appendChild(opt0);
        (itens || []).forEach(function (it) {
            var opt = document.createElement('option');
            opt.value = String(it[valueKey]);
            opt.textContent = String(it[labelKey]);
            if (String(atual) === String(it[valueKey])) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    function atualizarOpcoes(changed) {
        if (!filtrosUrl || !form) {
            carregarTodos();
            return;
        }
        var qs = queryFiltros();
        fetch(filtrosUrl + (qs ? '?' + qs : ''), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var f = (data && data.filtro) || {};
                if (changed === 'curso_id' || changed === 'ano_letivo_id') {
                    preencherSelect(form.querySelector('[name="serie_id"]'), data.series, 'id', 'nome', f.serie_id);
                    preencherSelect(form.querySelector('[name="turma_id"]'), data.turmas, 'id', 'nome', f.turma_id);
                } else if (changed === 'serie_id') {
                    preencherSelect(form.querySelector('[name="turma_id"]'), data.turmas, 'id', 'nome', f.turma_id);
                } else if (changed === 'bimestre' || changed === 'turno') {
                    preencherSelect(form.querySelector('[name="turma_id"]'), data.turmas, 'id', 'nome', f.turma_id);
                }
                carregarTodos();
            })
            .catch(function () { carregarTodos(); });
    }

    function encaixarFiltros() {
        var slot = document.getElementById('admin-header-slot');
        var ancora = document.getElementById('dash-filtros-anchor');
        if (!slot || !form || !ancora) return;
        var largo = window.matchMedia('(min-width: 1280px)').matches;
        if (largo) {
            slot.appendChild(form);
            slot.classList.remove('hidden');
            slot.classList.add('flex', 'flex-1', 'justify-end', 'items-center');
            ancora.classList.add('hidden');
        } else if (form.parentElement === slot) {
            ancora.appendChild(form);
            slot.classList.add('hidden');
            slot.classList.remove('flex', 'flex-1', 'justify-end', 'items-center');
            ancora.classList.remove('hidden');
        }
    }

    if (form) {
        form.addEventListener('change', function (ev) {
            var name = ev.target && ev.target.name;
            if (!name) return;
            atualizarOpcoes(name);
        });
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            carregarTodos();
        });
        var btnMais = document.getElementById('dash-mais-filtros');
        var extra = document.getElementById('dash-filtros-extra');
        if (btnMais && extra) {
            btnMais.addEventListener('click', function (ev) {
                ev.stopPropagation();
                extra.classList.toggle('hidden');
                btnMais.textContent = extra.classList.contains('hidden') ? 'Mais filtros' : 'Menos filtros';
            });
        }
    }

    encaixarFiltros();
    window.addEventListener('resize', encaixarFiltros);
    carregarTodos();
})();
