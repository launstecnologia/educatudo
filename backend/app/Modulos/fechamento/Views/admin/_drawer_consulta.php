<?php
$fechConsultaAno = (int) ($ano_letivo ?? date('Y'));
$fechConsultaPeriodoTipo = (string) ($periodo_tipo ?? 'ano');
$fechConsultaPeriodoNumero = (int) ($periodo_numero ?? 0);
?>
<div id="fechamentoConsultaBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="fecharFechamentoConsulta()"></div>
<aside id="fechamentoConsultaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true"
       data-ano="<?= $fechConsultaAno ?>"
       data-periodo-tipo="<?= htmlspecialchars($fechConsultaPeriodoTipo, ENT_QUOTES, 'UTF-8') ?>"
       data-periodo-numero="<?= $fechConsultaPeriodoNumero ?>">
    <div class="flex items-center justify-between px-4 sm:px-8 py-5 border-b border-gray-200">
        <div>
            <h2 id="fechamentoConsultaTitulo" class="text-xl font-bold text-gray-900">Consulta</h2>
            <p id="fechamentoConsultaSubtitulo" class="text-sm text-gray-500 mt-0.5"></p>
        </div>
        <button type="button" onclick="fecharFechamentoConsulta()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <div id="fechamentoConsultaBody" class="flex-1 overflow-y-auto px-4 sm:px-8 py-6 space-y-6"></div>
    <div class="px-4 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
        <button type="button" onclick="fecharFechamentoConsulta()"
                class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
            Fechar
        </button>
    </div>
</aside>
<script>
(function () {
    const URL_BASE = <?= json_encode(defined('URL') ? URL : '') ?>;
    const drawer = document.getElementById('fechamentoConsultaDrawer');
    const backdrop = document.getElementById('fechamentoConsultaBackdrop');
    const tituloEl = document.getElementById('fechamentoConsultaTitulo');
    const subtituloEl = document.getElementById('fechamentoConsultaSubtitulo');
    const bodyEl = document.getElementById('fechamentoConsultaBody');
    if (!drawer || !backdrop || !tituloEl || !bodyEl) return;

    function esc(valor) {
        const el = document.createElement('div');
        el.textContent = valor == null ? '' : String(valor);
        return el.innerHTML;
    }

    function fecharDropdowns() {
        document.querySelectorAll('[data-dropdown-menu]').forEach(function (el) {
            el.classList.add('hidden');
        });
    }

    function badgeStatus(status, rotulo) {
        const mapa = {
            HOMOLOGADO: 'bg-green-100 text-green-700',
            RETIFICADO: 'bg-purple-100 text-purple-700',
            EM_RECUPERACAO: 'bg-amber-100 text-amber-800',
            EM_FECHAMENTO: 'bg-sky-100 text-sky-800'
        };
        const cls = mapa[status] || 'bg-gray-100 text-gray-600';
        return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ' + cls + '">' + esc(rotulo) + '</span>';
    }

    function queryPeriodo() {
        const params = new URLSearchParams();
        params.set('ano_letivo', drawer.getAttribute('data-ano') || '');
        params.set('periodo_tipo', drawer.getAttribute('data-periodo-tipo') || 'ano');
        params.set('periodo_numero', drawer.getAttribute('data-periodo-numero') || '0');
        return params.toString();
    }

    function mostrarDrawer() {
        backdrop.classList.remove('hidden');
        drawer.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(function () {
            drawer.classList.remove('translate-x-full');
        });
        document.body.classList.add('overflow-hidden');
    }

    window.fecharFechamentoConsulta = function () {
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    function renderHomologacoes(data, turmaNome) {
        if (!data.schema_pronto) {
            bodyEl.innerHTML = '<div class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">O livro de homologações ainda não está disponível neste banco.</div>';
            return;
        }
        const registros = Array.isArray(data.registros) ? data.registros : [];
        if (registros.length === 0) {
            bodyEl.innerHTML = '<p class="text-sm text-gray-500 text-center py-10">Nenhum registro de fechamento nesta turma/ano.</p>';
            return;
        }
        let html = '<div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs text-gray-500 uppercase border-b border-gray-200"><tr>'
            + '<th class="py-2 pr-3">Turma</th><th class="py-2 pr-3">Período</th><th class="py-2 pr-3">Status</th>'
            + '<th class="py-2 pr-3">Vigente</th><th class="py-2 pr-3">Homologação</th><th class="py-2">Auditoria</th>'
            + '</tr></thead><tbody class="divide-y divide-gray-100">';
        registros.forEach(function (reg) {
            const quando = [reg.homologado_em, reg.homologado_por_nome].filter(Boolean).join(' · ') || '—';
            const aud = (reg.auditoria || []).map(function (linha) {
                return '<div>' + esc(linha) + '</div>';
            }).join('') || '—';
            html += '<tr class="align-top">'
                + '<td class="py-3 pr-3 font-medium text-gray-900">' + esc(reg.turma_nome) + '</td>'
                + '<td class="py-3 pr-3 text-gray-700">' + esc(reg.periodo_ref) + '</td>'
                + '<td class="py-3 pr-3">' + badgeStatus(reg.status, reg.status_rotulo) + '</td>'
                + '<td class="py-3 pr-3">' + (reg.vigente ? 'Sim' : 'Histórico') + '</td>'
                + '<td class="py-3 pr-3 text-gray-700">' + esc(quando)
                + (reg.justificativa ? '<div class="text-xs text-gray-500 mt-1">' + esc(reg.justificativa) + '</div>' : '')
                + (reg.retificado_de_id ? '<div class="text-xs text-purple-700 mt-1">Retifica #' + esc(reg.retificado_de_id) + '</div>' : '')
                + '</td><td class="py-3 text-xs text-gray-600">' + aud + '</td></tr>';
        });
        html += '</tbody></table></div>';
        if (!turmaNome) {
            subtituloEl.textContent = 'Livro de registros e retificações do ano ' + (data.ano_letivo || '');
        }
        bodyEl.innerHTML = html;
    }

    function renderDocumentos(data) {
        const turmaId = parseInt(data.turma_id || 0, 10);
        const qs = data.qs || queryPeriodo();
        let html = '';
        if (turmaId > 0) {
            const aviso = (data.homologados > 0)
                ? 'Documentos oficiais usam o snapshot homologado.'
                : 'Ainda não homologado — PDF sai como rascunho/prévia, sem valor de fechamento oficial.';
            html += '<section><h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Emissão</h3>'
                + '<p class="text-sm text-gray-500 mb-4">' + esc(aviso) + '</p>'
                + '<div class="flex flex-wrap gap-2 mb-4">'
                + '<a href="' + URL_BASE + '/admin/resultados-finais/turma/' + turmaId + '/ata?' + esc(qs) + '" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Ata (prévia)</a>'
                + '<a href="' + URL_BASE + '/admin/resultados-finais/turma/' + turmaId + '/ata/pdf?' + esc(qs) + '" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Ata PDF</a>'
                + '</div>';
            const linhas = Array.isArray(data.linhas) ? data.linhas : [];
            if (linhas.length === 0) {
                html += '<p class="text-sm text-gray-500">Nenhum aluno nesta turma.</p>';
            } else {
                html += '<div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs text-gray-500 uppercase border-b border-gray-200"><tr>'
                    + '<th class="py-2 pr-4">Aluno</th><th class="py-2 pr-4">Situação</th><th class="py-2">Documentos</th></tr></thead><tbody class="divide-y divide-gray-100">';
                linhas.forEach(function (linha) {
                    const aid = parseInt(linha.aluno_id || 0, 10);
                    const qsAluno = qs + (qs ? '&' : '') + 'turma_id=' + turmaId;
                    html += '<tr><td class="py-2 pr-4">' + esc(linha.aluno_nome) + '</td>'
                        + '<td class="py-2 pr-4">' + esc(linha.rotulo) + '</td>'
                        + '<td class="py-2">'
                        + '<a class="text-accent underline mr-3" href="' + URL_BASE + '/admin/resultados-finais/aluno/' + aid + '/ficha?' + esc(qsAluno) + '">Ficha</a>'
                        + '<a class="text-accent underline" target="_blank" rel="noopener" href="' + URL_BASE + '/admin/resultados-finais/aluno/' + aid + '/boletim/pdf?' + esc(qsAluno) + '">Boletim PDF</a>'
                        + '</td></tr>';
                });
                html += '</tbody></table></div>';
            }
            html += '</section>';
        } else {
            html += '<p class="text-sm text-gray-500">Para emitir ata, ficha e boletim, abra Documentos na ação da turma.</p>';
        }

        html += '<section><h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Histórico de emissões</h3>';
        const emissoes = Array.isArray(data.emissoes) ? data.emissoes : [];
        if (emissoes.length === 0) {
            html += '<p class="text-sm text-gray-500">Nenhuma emissão neste filtro.</p>';
        } else {
            html += '<div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs text-gray-500 uppercase border-b border-gray-200"><tr>'
                + '<th class="py-2 pr-3">Quando</th><th class="py-2 pr-3">Tipo</th><th class="py-2 pr-3">Turma / aluno</th><th class="py-2">Nº</th></tr></thead><tbody class="divide-y divide-gray-100">';
            emissoes.forEach(function (em) {
                const alvo = [em.turma_nome || '—', em.aluno_nome].filter(Boolean).join(' · ');
                html += '<tr><td class="py-2 pr-3 text-gray-700">' + esc(em.emitido_em) + '</td>'
                    + '<td class="py-2 pr-3">' + esc(em.tipo_rotulo) + '</td>'
                    + '<td class="py-2 pr-3 text-gray-700">' + esc(alvo) + '</td>'
                    + '<td class="py-2">' + esc(em.numero) + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        html += '</section>';
        bodyEl.innerHTML = html;
    }

    window.abrirFechamentoConsulta = function (tipo, turmaId, turmaNome) {
        fecharDropdowns();
        const id = parseInt(turmaId || 0, 10);
        const nome = turmaNome || '';
        const ehDoc = tipo === 'documentos';
        tituloEl.textContent = ehDoc
            ? (nome ? ('Documentos — ' + nome) : 'Documentos do período')
            : (nome ? ('Homologações — ' + nome) : 'Homologações');
        subtituloEl.textContent = ehDoc
            ? 'Boletins, atas e fichas da turma.'
            : 'Livro de registros e retificações. Somente consulta.';
        bodyEl.innerHTML = '<div class="py-16 text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin text-2xl"></i></div>';
        mostrarDrawer();

        const caminho = ehDoc ? '/documentos/dados' : '/homologacoes/dados';
        fetch(URL_BASE + '/admin/fechamento/turma/' + id + caminho + '?' + queryPeriodo(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    bodyEl.innerHTML = '<p class="text-sm text-red-600">' + esc((data && data.error) || 'Não foi possível carregar.') + '</p>';
                    return;
                }
                if (ehDoc) {
                    renderDocumentos(data);
                } else {
                    renderHomologacoes(data, nome);
                }
            })
            .catch(function () {
                bodyEl.innerHTML = '<p class="text-sm text-red-600">Erro de conexão ao carregar.</p>';
            });
    };

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-fechamento-consulta]');
        if (!btn) return;
        e.preventDefault();
        abrirFechamentoConsulta(
            btn.getAttribute('data-fechamento-consulta'),
            btn.getAttribute('data-turma-id'),
            btn.getAttribute('data-turma-nome')
        );
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.getAttribute('aria-hidden') === 'false') {
            fecharFechamentoConsulta();
        }
    });
})();
</script>
