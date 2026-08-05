(function () {
    'use strict';

    function setDashValue(id, value) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.textContent = String(value);
        el.classList.remove('opacity-0');
        el.removeAttribute('aria-hidden');
        var wrap = el.parentElement;
        if (wrap) {
            var skel = wrap.querySelector('.jornadas-dash-skel');
            if (skel) {
                skel.remove();
            }
        }
    }

    function setText(id, value) {
        if (String(id).indexOf('dash') === 0) {
            setDashValue(id, value);
            return;
        }
        var el = document.getElementById(id);
        if (el) {
            el.textContent = String(value);
        }
    }

    function clearJornadaCountSkeleton() {
        var el = document.getElementById('jornadaCount');
        if (!el) {
            return;
        }
        var skel = el.querySelector('.jornadas-count-skel');
        if (skel) {
            skel.remove();
        }
    }

    function populateSelect(select, values, placeholder) {
        if (!select) {
            return;
        }
        var current = select.value;
        select.innerHTML = '';
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = placeholder;
        select.appendChild(opt0);
        values.forEach(function (v) {
            var opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            select.appendChild(opt);
        });
        if (current && values.indexOf(current) !== -1) {
            select.value = current;
        }
    }

    function applyDashboard(dashboard) {
        if (!dashboard) {
            return;
        }
        setText('dashTotalJornadas', dashboard.total ?? '—');
        setText('dashConcluidas', dashboard.concluidas ?? '—');
        setText('dashEmAndamento', dashboard.jornadas_em_andamento ?? '—');
        setText('dashExpiradas', dashboard.jornadas_expiradas ?? '—');
        setText('dashQuestoesTotal', dashboard.questoes_total ?? '—');
        setText('dashQuestoesAcertos', dashboard.questoes_acertos ?? '—');
        setText('dashQuestoesErros', dashboard.questoes_erros ?? '—');
    }

    function initFilters() {
        var searchInput = document.getElementById('searchInput');
        var materiaFilter = document.getElementById('materiaFilter');
        var statusFilter = document.getElementById('statusFilter');
        var professorFilter = document.getElementById('professorFilter');
        var dataDeFilter = document.getElementById('dataDeFilter');
        var dataAteFilter = document.getElementById('dataAteFilter');
        var jornadaCount = document.getElementById('jornadaCount');
        var urlParams = new URLSearchParams(window.location.search);

        var dashBaseline = window.jornadasDashBaseline || null;

        function setDash(id, n) {
            setText(id, n);
        }

        function isFiltroJornadasAtivo() {
            var t = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
            if (t.length > 0) return true;
            if (materiaFilter && materiaFilter.value) return true;
            if (professorFilter && professorFilter.value) return true;
            if (dataDeFilter && dataDeFilter.value) return true;
            if (dataAteFilter && dataAteFilter.value) return true;
            var st = (statusFilter && statusFilter.value) ? String(statusFilter.value).trim() : '';
            return st !== '';
        }

        function restoreDashboardBaseline() {
            if (dashBaseline) {
                applyDashboard({
                    total: dashBaseline.total,
                    concluidas: dashBaseline.concluidas,
                    jornadas_em_andamento: dashBaseline.emAndamento,
                    jornadas_expiradas: dashBaseline.expiradas,
                    questoes_total: dashBaseline.questoesTotal,
                    questoes_acertos: dashBaseline.questoesAcertos,
                    questoes_erros: dashBaseline.questoesErros,
                });
            }
        }

        function updateDashboardFromVisibleJornadas() {
            var cards = document.querySelectorAll('.jornada-card');
            var total = 0, concl = 0, em = 0, exp = 0, qTot = 0, qAc = 0, qEr = 0;
            cards.forEach(function (card) {
                if (card.style.display === 'none') return;
                total++;
                var st = card.dataset.status || '';
                if (st === 'concluido') concl++;
                else if (st === 'em_andamento') em++;
                else if (st === 'expirado') exp++;
                qTot += parseInt(card.getAttribute('data-questoes-total') || '0', 10) || 0;
                qAc += parseInt(card.getAttribute('data-questoes-acertos') || '0', 10) || 0;
                qEr += parseInt(card.getAttribute('data-questoes-erros') || '0', 10) || 0;
            });
            setDash('dashTotalJornadas', total);
            setDash('dashConcluidas', concl);
            setDash('dashEmAndamento', em);
            setDash('dashExpiradas', exp);
            setDash('dashQuestoesTotal', qTot);
            setDash('dashQuestoesAcertos', qAc);
            setDash('dashQuestoesErros', qEr);
        }

        function filterJornadas() {
            var cards = document.querySelectorAll('.jornada-card');
            var searchTerm = (searchInput && searchInput.value || '').toLowerCase().trim();
            var selectedMateria = materiaFilter ? materiaFilter.value : '';
            var selectedStatus = statusFilter ? statusFilter.value : '';
            var selectedProfessor = professorFilter ? professorFilter.value : '';
            var dataDe = dataDeFilter ? dataDeFilter.value : '';
            var dataAte = dataAteFilter ? dataAteFilter.value : '';
            var visibleCount = 0;

            cards.forEach(function (card) {
                var titulo = (card.dataset.titulo || '').toLowerCase();
                var materia = card.dataset.materia || '';
                var status = card.dataset.status || '';
                var professor = card.dataset.professor || '';
                var dataInicio = card.dataset.dataInicio || '';
                var dataFim = card.dataset.dataFim || '';

                var matchesSearch = !searchTerm || titulo.indexOf(searchTerm) !== -1;
                var matchesMateria = !selectedMateria || materia === selectedMateria;
                var matchesStatus = !selectedStatus || status === selectedStatus;
                var matchesProfessor = !selectedProfessor || professor === selectedProfessor;

                var matchesDataDe = true;
                if (dataDe && dataFim) matchesDataDe = dataFim >= dataDe;
                else if (dataDe && dataInicio) matchesDataDe = dataInicio >= dataDe;
                var matchesDataAte = true;
                if (dataAte && dataInicio) matchesDataAte = dataInicio <= dataAte;
                else if (dataAte && dataFim) matchesDataAte = dataFim <= dataAte;

                if (matchesSearch && matchesMateria && matchesStatus && matchesProfessor && matchesDataDe && matchesDataAte) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (jornadaCount) {
                clearJornadaCountSkeleton();
                jornadaCount.textContent = visibleCount + ' jornada' + (visibleCount !== 1 ? 's' : '');
            }
            if (isFiltroJornadasAtivo()) {
                updateDashboardFromVisibleJornadas();
            } else {
                restoreDashboardBaseline();
            }
        }

        if (searchInput) searchInput.addEventListener('input', filterJornadas);
        if (materiaFilter) materiaFilter.addEventListener('change', filterJornadas);
        if (statusFilter) statusFilter.addEventListener('change', filterJornadas);
        if (professorFilter) professorFilter.addEventListener('change', filterJornadas);
        if (dataDeFilter) dataDeFilter.addEventListener('change', filterJornadas);
        if (dataAteFilter) dataAteFilter.addEventListener('change', filterJornadas);

        var statusQuery = (urlParams.get('status') || '').trim().toLowerCase();
        if (statusQuery && ['aguardando', 'em_andamento', 'concluido', 'expirado'].indexOf(statusQuery) !== -1 && statusFilter) {
            statusFilter.value = statusQuery;
        }
        var materiaQuery = (urlParams.get('materia') || '').trim();
        if (materiaQuery && materiaFilter) materiaFilter.value = materiaQuery;
        var professorQuery = (urlParams.get('professor') || '').trim();
        if (professorQuery && professorFilter) professorFilter.value = professorQuery;
        var dataDeQuery = (urlParams.get('data_de') || '').trim();
        if (dataDeQuery && dataDeFilter) dataDeFilter.value = dataDeQuery;
        var dataAteQuery = (urlParams.get('data_ate') || '').trim();
        if (dataAteQuery && dataAteFilter) dataAteFilter.value = dataAteQuery;

        filterJornadas();
    }

    function fetchJson(url, options) {
        var timeoutMs = 120000;
        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = controller ? setTimeout(function () { controller.abort(); }, timeoutMs) : null;
        var fetchOpts = Object.assign(
            { credentials: 'same-origin', headers: { Accept: 'application/json' } },
            options || {}
        );
        if (controller) {
            fetchOpts.signal = controller.signal;
        }
        return fetch(url, fetchOpts).then(function (res) {
            if (timer) clearTimeout(timer);
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok || !data.success) {
                    throw new Error(data.error || ('HTTP ' + res.status));
                }
                return data;
            });
        }).catch(function (err) {
            if (timer) clearTimeout(timer);
            if (err && err.name === 'AbortError') {
                throw new Error('Tempo esgotado (' + (timeoutMs / 1000) + 's)');
            }
            throw err;
        });
    }

    async function carregarJornadasIndex(config) {
        var grid = document.getElementById('jornadasGrid');

        try {
            var montar = await fetchJson(config.apiMontar);

            applyDashboard(montar.dashboard);
            window.jornadasDashBaseline = {
                total: montar.dashboard.total,
                concluidas: montar.dashboard.concluidas,
                emAndamento: montar.dashboard.jornadas_em_andamento,
                expiradas: montar.dashboard.jornadas_expiradas,
                questoesTotal: montar.dashboard.questoes_total,
                questoesAcertos: montar.dashboard.questoes_acertos,
                questoesErros: montar.dashboard.questoes_erros,
            };

            populateSelect(document.getElementById('materiaFilter'), montar.filtros.materias || [], 'Todas as matérias');
            populateSelect(document.getElementById('professorFilter'), montar.filtros.professores || [], 'Todos os professores');

            if (grid) {
                grid.innerHTML = montar.html || '';
            }

            var dashPanel = document.getElementById('jornadasDashPanel');
            if (dashPanel) {
                dashPanel.removeAttribute('aria-busy');
            }

            initFilters();
        } catch (err) {
            if (grid) {
                grid.innerHTML = '<div class="col-span-full rounded-xl border border-red-200 bg-red-50 p-8 text-center text-red-800">Não foi possível carregar jornadas. Atualize a página.</div>';
            }
        }
    }

    window.jornadasIndexBoot = function () {
        var config = window.jornadasIndexConfig;
        if (!config || !config.asyncLoad) {
            initFilters();
            return;
        }
        carregarJornadasIndex(config);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.jornadasIndexBoot);
    } else {
        window.jornadasIndexBoot();
    }
})();
