(function () {
    'use strict';

    var root = document.getElementById('dashboard-async-root');
    if (!root || root.dataset.async !== '1') {
        return;
    }

    var montarUrl = root.dataset.montarUrl || '';
    if (!montarUrl) {
        return;
    }

    function setCount(id, value) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.textContent = String(value);
        el.classList.remove('animate-pulse', 'text-gray-300');
    }

    function setSkeletonLoading() {
        ['dash-card-jornadas', 'dash-card-mural', 'dash-card-provas', 'dash-card-redacao'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.textContent = '—';
                el.classList.add('animate-pulse', 'text-gray-300');
            }
        });
    }

    function atualizarLinkMural(total) {
        var wrap = document.getElementById('dashboard-mural-quick-link');
        if (!wrap || total <= 0) {
            return;
        }
        wrap.classList.remove('hidden');
        var countEl = document.getElementById('dashboard-mural-total-label');
        if (countEl) {
            countEl.textContent = total === 1 ? '1 recado' : total + ' recados';
        }
    }

    function exibirModalMural(qtd, primaryColor) {
        if (qtd <= 0 || document.getElementById('modalMuralRecado')) {
            return;
        }
        var cor = primaryColor || '#3b82f6';
        var texto = qtd === 1
            ? 'Existe <strong>1 recado novo</strong> para você no mural.'
            : 'Existem <strong>' + qtd + ' recados novos</strong> para você no mural.';

        var modal = document.createElement('div');
        modal.id = 'modalMuralRecado';
        modal.className = 'fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4';
        modal.innerHTML =
            '<div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">' +
            '<div class="px-6 py-4 text-white flex items-center justify-between" style="background-color:' + cor + '">' +
            '<h2 class="text-xl font-bold flex items-center"><span class="mr-2">📌</span> Mural de Recados</h2>' +
            '<button type="button" id="btnFecharModalMural" class="text-white hover:bg-white/20 p-2 rounded-lg">' +
            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>' +
            '</button></div>' +
            '<div class="p-6"><p class="text-gray-700 mb-6">' + texto + '</p></div>' +
            '<div class="p-4 border-t bg-gray-50 flex justify-end gap-2">' +
            '<button type="button" id="btnFecharModalMural2" class="px-4 py-2 text-gray-700 hover:bg-gray-200 rounded-lg">Fechar</button>' +
            '<a href="' + (root.dataset.muralUrl || '/mural-recados') + '" class="px-4 py-2 text-white rounded-lg hover:opacity-90 transition-opacity inline-block" style="background-color:' + cor + '">Visualizar</a>' +
            '</div></div>';

        document.body.appendChild(modal);
        function fechar() {
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
        }
        document.getElementById('btnFecharModalMural')?.addEventListener('click', fechar);
        document.getElementById('btnFecharModalMural2')?.addEventListener('click', fechar);
    }

    setSkeletonLoading();

    fetch(montarUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            if (!data || !data.success) {
                return;
            }
            var cards = data.cards || {};
            setCount('dash-card-jornadas', cards.jornadas_abertas ?? 0);
            setCount('dash-card-mural', cards.mural_nao_lidos ?? 0);
            setCount('dash-card-provas', cards.provas_disponiveis ?? 0);
            if (document.getElementById('dash-card-redacao')) {
                setCount('dash-card-redacao', cards.jornada_redacao_pendentes ?? 0);
            }
            atualizarLinkMural(parseInt(cards.mural_total, 10) || 0);

            var mural = data.mural || {};
            if (mural.exibir_popup) {
                exibirModalMural(parseInt(mural.qtd_popup, 10) || 0, root.dataset.primaryColor || '');
            }
        })
        .catch(function () {
            ['dash-card-jornadas', 'dash-card-mural', 'dash-card-provas', 'dash-card-redacao'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) {
                    el.textContent = '0';
                    el.classList.remove('animate-pulse', 'text-gray-300');
                }
            });
        });
})();
