(function () {
    'use strict';

    var root = document.getElementById('dash-gestao-root');
    if (!root) return;

    var widgetUrl = root.dataset.widgetUrl || '';
    var filtrosUrl = root.dataset.filtrosUrl || '';
    var baseUrl = root.dataset.baseUrl || '';
    var form = document.getElementById('dash-filtros');
    var chartEvolucao = null;

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

    function fmtNum(n) {
        if (n === null || n === undefined || n === '') return '—';
        return Number(n).toLocaleString('pt-BR');
    }

    function cardHtml(c) {
        var valor = c.vazio ? c.vazio : (fmtNum(c.valor) + (c.sufixo || ''));
        var inner =
            '<div class="flex items-center justify-between gap-3">' +
            '<div class="min-w-0"><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">' + esc(c.label) + '</p>' +
            '<p class="mt-1 text-2xl font-bold leading-none ' + esc(c.valueClass || 'text-gray-900') + '">' + esc(valor) + '</p></div>' +
            '<div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ' + esc(c.iconClass || 'bg-slate-100 text-slate-600') + '">' +
            '<i class="fa-solid ' + esc(c.icon || 'fa-circle') + ' text-sm"></i></div></div>';
        var cls = 'rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm hover:shadow-md transition-shadow block';
        if (c.href) {
            return '<a href="' + esc(href(c.href)) + '" class="' + cls + '">' + inner + '</a>';
        }
        return '<div class="' + cls + '">' + inner + '</div>';
    }

    function cardsGrid(cards) {
        return '<div class="grid grid-cols-2 xl:grid-cols-4 gap-3">' + (cards || []).map(cardHtml).join('') + '</div>';
    }

    function indisponivel(data) {
        var link = data.href
            ? '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.href)) + '">Abrir módulo</a>'
            : '';
        return '<div class="text-sm text-gray-500"><p>' + esc(data.motivo || 'Bloco indisponível neste recorte.') + '</p>' +
            (link ? '<p class="mt-2">' + link + '</p>' : '') + '</div>';
    }

    function titulo(t, extra) {
        return '<div class="flex items-center justify-between mb-4"><h3 class="text-sm font-semibold text-gray-900">' +
            esc(t) + '</h3>' + (extra || '') + '</div>';
    }

    function metricas(items) {
        return '<div class="grid grid-cols-2 gap-3">' + items.map(function (it) {
            return '<div><p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">' + esc(it.label) +
                '</p><p class="mt-1 text-xl font-bold text-gray-900">' + esc(it.valor) + '</p></div>';
        }).join('') + '</div>';
    }

    function renderKpis(el, data) {
        el.innerHTML = cardsGrid(data.cards || []);
    }
    function renderPendencias(el, data) {
        el.innerHTML = cardsGrid(data.cards || []);
    }

    function renderFrequenciaHoje(el, data) {
        var r = data.resumo || {};
        var links = '';
        if (data.links && data.links.presenca) {
            links += '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.links.presenca)) + '">Gestão de Presença</a>';
        }
        if (data.links && data.links.chamadas) {
            links += (links ? ' · ' : '') + '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.links.chamadas)) + '">Chamadas pendentes</a>';
        }
        el.innerHTML = titulo('Frequência hoje', links) +
            metricas([
                { label: 'Percentual', valor: r.percentual == null ? '—' : r.percentual + '%' },
                { label: 'Presentes', valor: fmtNum(r.presentes) },
                { label: 'Ausentes', valor: fmtNum(r.ausentes) },
                { label: 'Faltas justificadas', valor: fmtNum(r.justificadas) },
                { label: 'Chamadas pendentes', valor: fmtNum(r.chamadas_pendentes) }
            ]) +
            (data.nota ? '<p class="mt-3 text-xs text-gray-400">' + esc(data.nota) + '</p>' : '');
    }

    function renderDesempenho(el, data) {
        if (!data.disponivel) {
            el.innerHTML = titulo('Desempenho acadêmico') + indisponivel(data);
            return;
        }
        var b = data.buckets || {};
        var rotulos = data.rotulos || {};
        var link = data.href ? '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.href)) + '">Resultados</a>' : '';
        el.innerHTML = titulo('Desempenho acadêmico', link) +
            metricas([
                { label: rotulos.dentro_criterio || 'Dentro do critério', valor: fmtNum(b.dentro_criterio) },
                { label: rotulos.atencao || 'Em atenção', valor: fmtNum(b.atencao) },
                { label: rotulos.recuperacao || 'Recuperação', valor: fmtNum(b.recuperacao) },
                { label: rotulos.risco || 'Risco acadêmico', valor: fmtNum(b.risco) }
            ]) +
            (data.nota ? '<p class="mt-3 text-xs text-gray-400">' + esc(data.nota) + '</p>' : '');
    }

    function renderEvolucao(el, data) {
        el.innerHTML = titulo('Evolução acadêmica') +
            '<canvas id="dash-evolucao-chart" height="90"></canvas>' +
            (data.nota ? '<p class="mt-3 text-xs text-gray-400">' + esc(data.nota) + '</p>' : '');
        if (typeof Chart === 'undefined') return;
        var canvas = document.getElementById('dash-evolucao-chart');
        if (!canvas) return;
        var datasets = [];
        var colors = ['#7c3aed', '#0ea5e9'];
        (data.series || []).forEach(function (s, i) {
            if (s.ocultar) return;
            datasets.push({
                label: s.label,
                data: (s.data || []).map(function (v) { return v == null ? null : v; }),
                borderColor: colors[i % colors.length],
                backgroundColor: 'transparent',
                tension: 0.25,
                spanGaps: true
            });
        });
        if (chartEvolucao) chartEvolucao.destroy();
        chartEvolucao = new Chart(canvas, {
            type: 'line',
            data: { labels: data.labels || [], datasets: datasets },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    function renderAtencao(el, data) {
        var linhas = data.linhas || [];
        var rows = linhas.length === 0
            ? '<tr><td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500">Nenhum aluno em atenção neste recorte.</td></tr>'
            : linhas.map(function (l) {
                var badge = l.badge === 'critico'
                    ? 'bg-red-100 text-red-700'
                    : 'bg-amber-100 text-amber-800';
                return '<tr class="border-t border-gray-100"><td class="px-3 py-2 text-sm text-gray-900">' + esc(l.aluno) +
                    '</td><td class="px-3 py-2 text-sm text-gray-600">' + esc(l.turma) +
                    '</td><td class="px-3 py-2 text-sm text-gray-600">' + esc(l.indicador) +
                    '</td><td class="px-3 py-2"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ' + badge + '">' +
                    esc(l.situacao) + '</span></td></tr>';
            }).join('');
        el.innerHTML = titulo('Atenção pedagógica') +
            '<div class="overflow-x-auto"><table class="min-w-full"><thead><tr class="text-left text-[11px] uppercase tracking-wide text-gray-500">' +
            '<th class="px-3 py-2">Aluno</th><th class="px-3 py-2">Turma</th><th class="px-3 py-2">Indicador</th><th class="px-3 py-2">Situação</th></tr></thead><tbody>' +
            rows + '</tbody></table></div>' +
            (data.nota ? '<p class="mt-3 text-xs text-gray-400">' + esc(data.nota) + '</p>' : '');
    }

    function renderDiarios(el, data) {
        if (!data.disponivel) {
            el.innerHTML = titulo('Diários') + indisponivel(data);
            return;
        }
        var link = data.href ? '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.href)) + '">Abrir diários</a>' : '';
        el.innerHTML = titulo('Diários', link) + metricas([
            { label: 'Preenchido', valor: data.percentual == null ? '—' : data.percentual + '%' },
            { label: 'Completos', valor: fmtNum(data.completos) },
            { label: 'Com pendências', valor: fmtNum(data.com_pendencias) },
            { label: 'Sem atualização', valor: fmtNum(data.sem_atualizacao) },
            { label: 'Aulas sem conteúdo', valor: fmtNum(data.aulas_sem_conteudo) },
            { label: 'Chamadas não realizadas', valor: fmtNum(data.chamadas_nao_realizadas) }
        ]);
    }

    function renderAvaliacoes(el, data) {
        var proximas = (data.proximas || []).map(function (p) {
            return '<li class="flex justify-between text-sm py-1 border-t border-gray-50"><span class="truncate pr-2">' +
                esc(p.titulo) + '</span><span class="text-gray-500 shrink-0">' + esc(fmtData(p.data)) + '</span></li>';
        }).join('');
        var link = data.href ? '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.href)) + '">Avaliações</a>' : '';
        el.innerHTML = titulo('Avaliações', link) + metricas([
            { label: 'Hoje', valor: fmtNum(data.hoje) },
            { label: 'Na semana', valor: fmtNum(data.semana) },
            { label: 'Aguardando correção', valor: fmtNum(data.aguardando_correcao) },
            { label: 'Notas pendentes', valor: fmtNum(data.notas_pendentes) }
        ]) + (proximas ? '<p class="mt-4 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Próximas</p><ul>' + proximas + '</ul>' : '');
    }

    function renderConselho(el, data) {
        if (!data.disponivel) {
            el.innerHTML = titulo('Conselho de Classe') + indisponivel(data);
            return;
        }
        var link = data.href ? '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.href)) + '">Conselhos</a>' : '';
        el.innerHTML = titulo('Conselho de Classe', link) + metricas([
            { label: 'Turmas prontas', valor: fmtNum(data.turmas_prontas) },
            { label: 'Com pendências', valor: fmtNum(data.turmas_com_pendencias) },
            { label: 'Realizados', valor: fmtNum(data.realizados) },
            { label: 'Aguardando', valor: fmtNum(data.aguardando) }
        ]);
    }

    function renderOcorrencias(el, data) {
        if (!data.disponivel) {
            el.innerHTML = titulo('Ocorrências') + indisponivel(data);
            return;
        }
        var cats = (data.por_categoria || []).map(function (c) {
            return '<li class="flex justify-between text-sm py-1"><span>' + esc(c.nome) + '</span><span class="font-medium">' + fmtNum(c.total) + '</span></li>';
        }).join('');
        var link = data.href ? '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.href)) + '">Ocorrências</a>' : '';
        el.innerHTML = titulo('Ocorrências', link) + metricas([
            { label: 'Total (14 dias)', valor: fmtNum(data.total) },
            { label: 'Aguardando encaminhamento', valor: fmtNum(data.aguardando_encaminhamento) }
        ]) + (cats ? '<ul class="mt-3">' + cats + '</ul>' : '') +
            (data.nota ? '<p class="mt-3 text-xs text-gray-400">' + esc(data.nota) + '</p>' : '');
    }

    function renderCalendario(el, data) {
        var itens = (data.eventos || []).map(function (e) {
            return '<li class="flex items-start justify-between gap-3 py-2 border-t border-gray-50">' +
                '<div class="min-w-0"><p class="text-sm text-gray-900 truncate">' + esc(e.titulo) + '</p>' +
                '<p class="text-xs text-gray-500">' + esc(e.tipo) + '</p></div>' +
                '<span class="text-xs text-gray-500 shrink-0">' + esc(fmtData(e.data)) + '</span></li>';
        }).join('');
        var links = '';
        if (data.href_escolar) links += '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.href_escolar)) + '">Escolar</a>';
        if (data.href_letivo) links += (links ? ' · ' : '') + '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.href_letivo)) + '">Letivo</a>';
        el.innerHTML = titulo('Próximos eventos', links) +
            (itens ? '<ul>' + itens + '</ul>' : '<p class="text-sm text-gray-500">Nenhum evento próximo.</p>');
    }

    function renderMatriculas(el, data) {
        var link = data.href ? '<a class="text-sm text-primary-600 hover:underline" href="' + esc(href(data.href)) + '">Alunos</a>' : '';
        el.innerHTML = titulo('Matrículas e movimentações', link) + metricas([
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
        diarios: renderDiarios,
        avaliacoes: renderAvaliacoes,
        conselho: renderConselho,
        ocorrencias: renderOcorrencias,
        calendario: renderCalendario,
        matriculas: renderMatriculas
    };

    function falha(el) {
        el.innerHTML = '<p class="text-sm text-gray-500">Não foi possível carregar este bloco.</p>';
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
        sel.innerHTML = '';
        if (keepFirst) sel.appendChild(keepFirst);
        else {
            var opt0 = document.createElement('option');
            opt0.value = '';
            opt0.textContent = 'Todas';
            sel.appendChild(opt0);
        }
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

    if (form) {
        form.addEventListener('change', function (ev) {
            var name = ev.target && ev.target.name;
            atualizarOpcoes(name);
        });
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            carregarTodos();
        });
    }

    carregarTodos();
})();
