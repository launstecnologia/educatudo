<?php
/** Extraído de show.php — conteúdo existente, sem alteração de regra de negócio. */
?>
            <!-- Drawer: Financeiro do Aluno -->
            <div id="drawerFinanceiro" class="fixed inset-0 z-[9998] hidden" aria-modal="true">
                <!-- Overlay -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="fecharFinanceiro()"></div>
                <!-- Painel -->
                <div class="absolute right-0 top-0 h-full w-full max-w-2xl bg-white shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300" id="drawerFinanceiroPanel">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Financeiro</h3>
                            <p class="text-sm text-gray-500" id="drawerFinanceiroAluno"></p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a id="drawerFinanceiroExtratoLink" href="#" target="_blank"
                               class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                                <i class="fa-solid fa-receipt mr-1.5"></i> Extrato completo
                            </a>
                            <button onclick="fecharFinanceiro()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                                <i class="fa-solid fa-xmark text-lg"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Conteúdo -->
                    <div class="flex-1 overflow-y-auto px-6 py-4" id="drawerFinanceiroBody">
                        <div class="flex items-center justify-center h-40">
                            <div class="text-center text-gray-400">
                                <i class="fa-solid fa-spinner fa-spin text-3xl mb-3 block text-green-500"></i>
                                <p class="text-sm">Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Drawer: EducaInclui / Laudo do Aluno -->
            <div id="drawerInclusao" class="fixed inset-0 z-[9998] hidden" aria-modal="true">
                <!-- Overlay -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="fecharInclusao()"></div>
                <!-- Painel -->
                <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300" id="drawerInclusaoPanel">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">EducaInclui / Laudo</h3>
                            <p class="text-sm text-gray-500" id="drawerInclusaoAluno"></p>
                        </div>
                        <button onclick="fecharInclusao()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                    <!-- Conteúdo -->
                    <div class="flex-1 overflow-y-auto px-6 py-4" id="drawerInclusaoBody">
                        <div class="flex items-center justify-center h-40">
                            <div class="text-center text-gray-400">
                                <i class="fa-solid fa-spinner fa-spin text-3xl mb-3 block text-green-500"></i>
                                <p class="text-sm">Carregando...</p>
                            </div>
                        </div>
                    </div>
                    <!-- Rodapé -->
                    <div class="px-6 py-4 border-t border-gray-200 flex-shrink-0">
                        <a id="drawerInclusaoManageLink" href="#"
                           class="btn-primary-custom w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors">
                            <i class="fa-solid fa-pen-to-square mr-2"></i> Abrir máscara completa
                        </a>
                    </div>
                </div>
            </div>
            <script>
            function abrirInclusao(alunoId) {
                const drawer    = document.getElementById('drawerInclusao');
                const panel     = document.getElementById('drawerInclusaoPanel');
                const body      = document.getElementById('drawerInclusaoBody');
                const alunoSpan = document.getElementById('drawerInclusaoAluno');
                const manageLink = document.getElementById('drawerInclusaoManageLink');

                manageLink.href = '<?= URL ?>/admin/inclusao/aluno/' + alunoId;

                drawer.classList.remove('hidden');
                requestAnimationFrame(() => panel.classList.remove('translate-x-full'));
                document.body.style.overflow = 'hidden';

                body.innerHTML = '<div class="flex items-center justify-center h-40"><div class="text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin text-3xl mb-3 block text-green-500"></i><p class="text-sm">Carregando...</p></div></div>';

                fetch('<?= URL ?>/admin/inclusao/aluno/' + alunoId + '/resumo')
                    .then(r => r.json())
                    .then(data => renderInclusaoResumo(data, alunoSpan))
                    .catch(() => {
                        body.innerHTML = '';
                        const err = document.createElement('div');
                        err.className = 'text-center py-10 text-gray-400';
                        err.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-3xl mb-3 block text-amber-400"></i>';
                        const p = document.createElement('p');
                        p.textContent = 'Erro ao carregar dados do EducaInclui.';
                        err.appendChild(p);
                        body.appendChild(err);
                    });
            }

            function fecharInclusao() {
                const drawer = document.getElementById('drawerInclusao');
                const panel  = document.getElementById('drawerInclusaoPanel');
                panel.classList.add('translate-x-full');
                setTimeout(() => { drawer.classList.add('hidden'); document.body.style.overflow = ''; }, 300);
            }

            function renderInclusaoResumo(data, alunoSpan) {
                const body = document.getElementById('drawerInclusaoBody');
                body.innerHTML = '';
                if (data.error) {
                    const p = document.createElement('p');
                    p.className = 'text-center text-gray-400 py-10';
                    p.textContent = 'Não foi possível carregar os dados.';
                    body.appendChild(p);
                    return;
                }
                if (data.aluno_nome) alunoSpan.textContent = data.aluno_nome;

                if (!data.has_accommodation) {
                    const empty = document.createElement('div');
                    empty.className = 'text-center py-10 text-gray-400';
                    empty.innerHTML = '<i class="fa-solid fa-universal-access text-4xl mb-4 block text-gray-200"></i>';
                    const p = document.createElement('p');
                    p.textContent = 'Este aluno ainda não tem máscara de acessibilidade cadastrada.';
                    empty.appendChild(p);
                    body.appendChild(empty);
                    return;
                }

                const statusLabel = { rascunho: 'Rascunho', ativa: 'Ativa', suspensa: 'Suspensa', encerrada: 'Encerrada' };
                const statusCls = {
                    rascunho: 'bg-slate-100 text-slate-700',
                    ativa: 'bg-green-100 text-green-800',
                    suspensa: 'bg-amber-100 text-amber-800',
                    encerrada: 'bg-red-100 text-red-800',
                };
                const tipoLabel = { acesso: 'Acesso', significativa: 'Significativa' };

                const statusRow = document.createElement('div');
                statusRow.className = 'flex items-center gap-2 mb-4';
                const badge = document.createElement('span');
                badge.className = 'inline-flex px-3 py-1 rounded-full text-xs font-semibold ' + (statusCls[data.status] || statusCls.rascunho);
                badge.textContent = statusLabel[data.status] || data.status || '—';
                statusRow.appendChild(badge);
                const tipo = document.createElement('span');
                tipo.className = 'text-sm text-gray-600';
                tipo.textContent = tipoLabel[data.tipo_adaptacao] || data.tipo_adaptacao || '';
                statusRow.appendChild(tipo);
                body.appendChild(statusRow);

                const laudoP = document.createElement('p');
                laudoP.className = 'text-sm text-gray-600 mb-4 flex items-center gap-2';
                laudoP.innerHTML = '<i class="fa-solid fa-file-shield text-gray-400"></i>';
                const laudoSpan = document.createElement('span');
                laudoSpan.textContent = data.laudo_count > 0
                    ? data.laudo_count + ' laudo(s) anexado(s)'
                    : 'Nenhum laudo anexado ainda';
                laudoP.appendChild(laudoSpan);
                body.appendChild(laudoP);

                const title = document.createElement('p');
                title.className = 'text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2';
                title.textContent = 'Regras de acessibilidade ativas';
                body.appendChild(title);

                const ul = document.createElement('ul');
                ul.className = 'list-disc pl-5 text-sm text-gray-700 space-y-1';
                if (!data.regras_ativas || data.regras_ativas.length === 0) {
                    const li = document.createElement('li');
                    li.className = 'text-gray-400 list-none';
                    li.textContent = 'Nenhuma regra ativa.';
                    ul.appendChild(li);
                } else {
                    data.regras_ativas.forEach(r => {
                        const li = document.createElement('li');
                        li.textContent = r;
                        ul.appendChild(li);
                    });
                }
                body.appendChild(ul);
            }

            document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharInclusao(); });

            function abrirFinanceiro(alunoId) {
                const drawer    = document.getElementById('drawerFinanceiro');
                const panel     = document.getElementById('drawerFinanceiroPanel');
                const body      = document.getElementById('drawerFinanceiroBody');
                const alunoSpan = document.getElementById('drawerFinanceiroAluno');
                const extLink   = document.getElementById('drawerFinanceiroExtratoLink');

                alunoSpan.textContent = document.querySelector('.student-name-display')?.textContent?.trim() || '';
                extLink.href = '<?= URL ?>/admin/finance/aluno/' + alunoId + '/extrato';

                drawer.classList.remove('hidden');
                requestAnimationFrame(() => panel.classList.remove('translate-x-full'));
                document.body.style.overflow = 'hidden';

                body.innerHTML = '<div class="flex items-center justify-center h-40"><div class="text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin text-3xl mb-3 block text-green-500"></i><p class="text-sm">Carregando...</p></div></div>';

                fetch('<?= URL ?>/admin/finance/aluno/' + alunoId + '/resumo')
                    .then(r => r.json())
                    .then(data => renderFinanceiro(data, alunoId, alunoSpan))
                    .catch(() => {
                        body.innerHTML = '<div class="text-center py-10 text-gray-400"><i class="fa-solid fa-triangle-exclamation text-3xl mb-3 block text-amber-400"></i><p>Erro ao carregar dados financeiros.</p></div>';
                    });
            }

            function fecharFinanceiro() {
                const drawer = document.getElementById('drawerFinanceiro');
                const panel  = document.getElementById('drawerFinanceiroPanel');
                panel.classList.add('translate-x-full');
                setTimeout(() => { drawer.classList.add('hidden'); document.body.style.overflow = ''; }, 300);
            }

            function renderFinanceiro(data, alunoId, alunoSpan) {
                const body = document.getElementById('drawerFinanceiroBody');
                if (data.nome) alunoSpan.textContent = data.nome;

                const brl = v => 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                const dt  = s => s ? s.split('-').reverse().join('/') : '—';
                const stCls = {
                    pendente: 'bg-amber-100 text-amber-700',
                    vencido:  'bg-red-100 text-red-700',
                    pago:     'bg-green-100 text-green-700',
                };
                const stLabel = { pendente: 'A pagar', vencido: 'Vencido', pago: 'Pago' };

                const totAberto = (data.faturas || []).filter(f => ['pendente','vencido'].includes(f.status)).reduce((s,f) => s + Number(f.valor_total||0), 0);
                const totPago   = (data.faturas || []).filter(f => f.status === 'pago').reduce((s,f) => s + Number(f.valor_total||0), 0);

                let html = `
                    <div class="grid grid-cols-3 gap-3 mb-5">
                        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center shadow-sm">
                            <p class="text-xs text-gray-500 mb-0.5">Saldo</p>
                            <p class="text-base font-bold ${data.saldo >= 0 ? 'text-green-700' : 'text-red-600'}">${brl(Math.abs(data.saldo || 0))}</p>
                            <p class="text-xs text-gray-400">${data.saldo >= 0 ? 'credor' : 'devedor'}</p>
                        </div>
                        <div class="bg-amber-50 rounded-xl border border-amber-200 p-3 text-center shadow-sm">
                            <p class="text-xs text-amber-600 mb-0.5">Em aberto</p>
                            <p class="text-base font-bold text-amber-700">${brl(totAberto)}</p>
                        </div>
                        <div class="bg-green-50 rounded-xl border border-green-200 p-3 text-center shadow-sm">
                            <p class="text-xs text-green-600 mb-0.5">Pago</p>
                            <p class="text-base font-bold text-green-700">${brl(totPago)}</p>
                        </div>
                    </div>
                    <div class="flex gap-3 mb-5">
                        <a href="<?= URL ?>/admin/finance/aluno/${alunoId}/charge"
                           class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-primary text-primary text-sm font-semibold hover:opacity-90 transition-opacity">
                            <i class="fa-solid fa-plus mr-2"></i> Cobrança Avulsa
                        </a>
                        <a href="<?= URL ?>/admin/finance/aluno/${alunoId}/extrato"
                           class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fa-solid fa-receipt mr-2"></i> Extrato Completo
                        </a>
                    </div>`;

                const faturas = data.faturas || [];
                const abertas = faturas.filter(f => ['pendente','vencido'].includes(f.status));
                const pagas   = faturas.filter(f => f.status === 'pago');

                if (abertas.length) {
                    html += `<h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center gap-1.5"><i class="fa-solid fa-circle-exclamation text-amber-500"></i> Em aberto (${abertas.length})</h4>
                        <div class="space-y-2 mb-5">`;
                    abertas.forEach(f => {
                        const cls = stCls[f.status] || stCls.pendente;
                        const tipo = f.tipo === 'cobrança' ? `<span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700 mr-1">${f.categoria_label||f.categoria}</span>` : '';
                        html += `<div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center justify-between gap-3 shadow-sm">
                            <div class="flex-1 min-w-0">
                                ${tipo}<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${stLabel[f.status]||f.status}</span>
                                <p class="text-sm font-semibold text-gray-900 mt-0.5 truncate">${f.descricao}</p>
                                <p class="text-xs text-gray-400">Vence ${dt(f.data_vencimento)}</p>
                            </div>
                            <p class="text-base font-bold text-gray-900 flex-shrink-0">${brl(f.valor_total)}</p>
                        </div>`;
                    });
                    html += `</div>`;
                }

                if (pagas.length) {
                    html += `<h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center gap-1.5"><i class="fa-solid fa-circle-check text-green-500"></i> Pagos (${pagas.length})</h4>
                        <div class="space-y-2">`;
                    pagas.forEach(f => {
                        const tipo = f.tipo === 'cobrança' ? `<span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700 mr-1">${f.categoria_label||f.categoria}</span>` : '';
                        html += `<div class="bg-gray-50 rounded-xl border border-gray-100 px-4 py-3 flex items-center justify-between gap-3 opacity-80">
                            <div class="flex-1 min-w-0">
                                ${tipo}
                                <p class="text-sm font-medium text-gray-700 truncate">${f.descricao}</p>
                                <p class="text-xs text-gray-400">Pago em ${dt(f.data_pagamento)}</p>
                            </div>
                            <p class="text-sm font-semibold text-green-700 flex-shrink-0">${brl(f.valor_total)}</p>
                        </div>`;
                    });
                    html += `</div>`;
                }

                if (!faturas.length) {
                    html += `<div class="text-center py-10 text-gray-400"><i class="fa-solid fa-file-invoice text-4xl mb-4 block text-gray-200"></i><p>Nenhuma fatura encontrada.</p></div>`;
                }

                body.innerHTML = html;
            }

            // Fechar com Escape
            document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharFinanceiro(); });
            </script>

            <!-- Modal: Declarações do Aluno -->
            <div id="modalDeclaracoes" class="doc-modal hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4" style="display:none;">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                        <h3 class="text-lg font-semibold text-slate-900">
                            <i class="fa-solid fa-file-lines text-amber-500 mr-2"></i> Declarações
                        </h3>
                        <button type="button" onclick="fecharModalDoc('Declaracoes')" class="text-slate-400 hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-xl"></i>
                        </button>
                    </div>
                    <div class="p-6 space-y-3">
                        <p class="text-xs text-slate-500">Os dados institucionais do cabeçalho vêm da unidade vinculada ao aluno.</p>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes/matricula/pdf" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-graduation-cap"></i></span>
                            <span><span class="block font-medium text-slate-800">Declaração de Matrícula</span><span class="block text-xs text-slate-500">Comprovante de matrícula ativa</span></span>
                        </a>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes/transferencia/pdf" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-right-from-bracket"></i></span>
                            <span><span class="block font-medium text-slate-800">Declaração de Transferência</span><span class="block text-xs text-slate-500">Conclusão / saída do aluno</span></span>
                        </a>

                        <div class="p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-calendar-check"></i></span>
                                <span><span class="block font-medium text-slate-800">Declaração de Frequência</span><span class="block text-xs text-slate-500">Calculada a partir do diário de classe</span></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Início</label>
                                    <input type="date" id="decl_freq_inicio" value="<?= date('Y') ?>-01-01" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Fim</label>
                                    <input type="date" id="decl_freq_fim" value="<?= date('Y-m-d') ?>" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                            </div>
                            <button type="button" onclick="gerarDeclaracaoFrequencia()" class="w-full px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">
                                <i class="fa-solid fa-download mr-1"></i> Gerar PDF
                            </button>
                        </div>

                        <div class="p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fa-solid fa-user-check"></i></span>
                                <span><span class="block font-medium text-slate-800">Declaração de Comparecimento</span><span class="block text-xs text-slate-500">Presença em data específica</span></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Data</label>
                                    <input type="date" id="decl_comp_data" value="<?= date('Y-m-d') ?>" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Período (opcional)</label>
                                    <input type="text" id="decl_comp_periodo" placeholder="Ex: 08h às 12h" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                            </div>
                            <button type="button" onclick="gerarDeclaracaoComparecimento()" class="w-full px-3 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700 transition-colors">
                                <i class="fa-solid fa-download mr-1"></i> Gerar PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal: Documentação do Aluno -->
            <div id="modalDocumentacao" class="doc-modal hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4" style="display:none;">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                        <h3 class="text-lg font-semibold text-slate-900">
                            <i class="fa-solid fa-folder-open text-rose-500 mr-2"></i> Documentação
                        </h3>
                        <button type="button" onclick="fecharModalDoc('Documentacao')" class="text-slate-400 hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-xl"></i>
                        </button>
                    </div>
                    <div class="p-6 space-y-3">
                        <p class="text-xs text-slate-500">Os dados institucionais do cabeçalho vêm da unidade vinculada ao aluno.</p>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/historico-escolar"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center"><i class="fa-solid fa-clock-rotate-left"></i></span>
                            <span><span class="block font-medium text-slate-800">Histórico Escolar</span><span class="block text-xs text-slate-500">Documento oficial (rascunho → emissão → assinatura → QR)</span></span>
                        </a>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes/historico/pdf" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-dashed border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-slate-50 text-slate-500 flex items-center justify-center"><i class="fa-solid fa-file-pdf"></i></span>
                            <span><span class="block font-medium text-slate-700">PDF rápido (legado)</span><span class="block text-xs text-slate-500">Extrato de boletins sem workflow jurídico</span></span>
                        </a>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes/ficha_matricula/pdf" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center"><i class="fa-solid fa-id-card"></i></span>
                            <span><span class="block font-medium text-slate-800">Ficha de Matrícula</span><span class="block text-xs text-slate-500">Dados cadastrais e responsáveis</span></span>
                        </a>

                        <a href="<?= URL ?>/admin/resultados-finais/aluno/<?= (int) ($student['id'] ?? 0) ?>/ficha<?= !empty($student['turma_id']) ? ('?turma_id=' . (int) $student['turma_id']) : '' ?>"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-file-lines"></i></span>
                            <span><span class="block font-medium text-slate-800">Ficha Individual</span><span class="block text-xs text-slate-500">Notas, frequência e situação do ano letivo</span></span>
                        </a>

                        <a href="<?= URL ?>/admin/resultados-finais/aluno/<?= (int) ($student['id'] ?? 0) ?>/boletim/pdf<?= !empty($student['turma_id']) ? ('?turma_id=' . (int) $student['turma_id']) : '' ?>"
                           target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-file-pdf"></i></span>
                            <span><span class="block font-medium text-slate-800">Boletim oficial (PDF)</span><span class="block text-xs text-slate-500">Usa o snapshot homologado quando o ano já foi fechado</span></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Modal: Autorizações do Aluno -->
            <div id="modalAutorizacoes" class="doc-modal hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4" style="display:none;">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                        <h3 class="text-lg font-semibold text-slate-900">
                            <i class="fa-solid fa-file-signature text-cyan-600 mr-2"></i> Autorizações
                        </h3>
                        <button type="button" onclick="fecharModalDoc('Autorizacoes')" class="text-slate-400 hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-xl"></i>
                        </button>
                    </div>
                    <div class="p-6 space-y-3">
                        <p class="text-xs text-slate-500">Os dados institucionais do cabeçalho vêm da unidade vinculada ao aluno.</p>

                        <div class="p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-9 h-9 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center"><i class="fa-solid fa-door-open"></i></span>
                                <span><span class="block font-medium text-slate-800">Autorização de Saída</span><span class="block text-xs text-slate-500">Saída antecipada do aluno</span></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Data</label>
                                    <input type="date" id="aut_saida_data" value="<?= date('Y-m-d') ?>" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Horário</label>
                                    <input type="text" id="aut_saida_horario" placeholder="Ex: 11h30" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                            </div>
                            <input type="text" id="aut_saida_motivo" placeholder="Motivo (opcional)" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm mb-3">
                            <button type="button" onclick="gerarAutSaida()" class="w-full px-3 py-2 rounded-lg bg-orange-600 text-white text-sm font-medium hover:bg-orange-700 transition-colors">
                                <i class="fa-solid fa-download mr-1"></i> Gerar PDF
                            </button>
                        </div>

                        <div class="p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-9 h-9 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center"><i class="fa-solid fa-people-arrows"></i></span>
                                <span><span class="block font-medium text-slate-800">Autorização de Retirada</span><span class="block text-xs text-slate-500">Retirada do aluno por terceiros</span></span>
                            </div>
                            <input type="text" id="aut_ret_nome" placeholder="Nome da pessoa autorizada" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm mb-2">
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <input type="text" id="aut_ret_doc" placeholder="Documento (RG/CPF)" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                <input type="text" id="aut_ret_parentesco" placeholder="Parentesco/vínculo" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                            </div>
                            <button type="button" onclick="gerarAutRetirada()" class="w-full px-3 py-2 rounded-lg bg-cyan-600 text-white text-sm font-medium hover:bg-cyan-700 transition-colors">
                                <i class="fa-solid fa-download mr-1"></i> Gerar PDF
                            </button>
                        </div>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes/aut_imagem/pdf" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-fuchsia-50 text-fuchsia-600 flex items-center justify-center"><i class="fa-solid fa-camera"></i></span>
                            <span><span class="block font-medium text-slate-800">Autorização de Uso de Imagem</span><span class="block text-xs text-slate-500">Consentimento de uso de imagem/voz</span></span>
                        </a>

                        <div class="p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-9 h-9 rounded-lg bg-lime-50 text-lime-600 flex items-center justify-center"><i class="fa-solid fa-bus"></i></span>
                                <span><span class="block font-medium text-slate-800">Autorização de Passeio/Excursão</span><span class="block text-xs text-slate-500">Participação em atividade externa</span></span>
                            </div>
                            <input type="text" id="aut_pas_local" placeholder="Destino / local" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm mb-2">
                            <div class="grid grid-cols-3 gap-2 mb-3">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Data</label>
                                    <input type="date" id="aut_pas_data" value="<?= date('Y-m-d') ?>" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Saída</label>
                                    <input type="text" id="aut_pas_saida" placeholder="08h" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Retorno</label>
                                    <input type="text" id="aut_pas_retorno" placeholder="17h" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                            </div>
                            <button type="button" onclick="gerarAutPasseio()" class="w-full px-3 py-2 rounded-lg bg-lime-600 text-white text-sm font-medium hover:bg-lime-700 transition-colors">
                                <i class="fa-solid fa-download mr-1"></i> Gerar PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                (function () {
                    var baseDecl = '<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes';
                    var moved = {};
                    function val(id) {
                        var el = document.getElementById(id);
                        return el ? el.value : '';
                    }
                    function abrir(tipo, params) {
                        var qs = '';
                        if (params) {
                            var parts = [];
                            Object.keys(params).forEach(function (k) {
                                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
                            });
                            qs = parts.length ? ('?' + parts.join('&')) : '';
                        }
                        window.open(baseDecl + '/' + tipo + '/pdf' + qs, '_blank', 'noopener');
                    }
                    window.abrirModalDoc = function (nome) {
                        var m = document.getElementById('modal' + nome);
                        if (!m) { return; }
                        // Move para o body para que "position: fixed" se ancore na viewport
                        // (evita que ancestrais com transform/backdrop-blur prendam o modal).
                        if (!moved[nome]) { document.body.appendChild(m); moved[nome] = true; }
                        m.style.display = 'flex';
                    };
                    window.fecharModalDoc = function (nome) {
                        var m = document.getElementById('modal' + nome);
                        if (m) { m.style.display = 'none'; }
                    };
                    window.gerarDeclaracaoFrequencia = function () {
                        abrir('frequencia', { inicio: val('decl_freq_inicio'), fim: val('decl_freq_fim') });
                    };
                    window.gerarDeclaracaoComparecimento = function () {
                        abrir('comparecimento', { data: val('decl_comp_data'), periodo: val('decl_comp_periodo') });
                    };
                    window.gerarAutSaida = function () {
                        abrir('aut_saida', { data: val('aut_saida_data'), horario: val('aut_saida_horario'), motivo: val('aut_saida_motivo') });
                    };
                    window.gerarAutRetirada = function () {
                        abrir('aut_retirada', { nome_autorizado: val('aut_ret_nome'), documento: val('aut_ret_doc'), parentesco: val('aut_ret_parentesco') });
                    };
                    window.gerarAutPasseio = function () {
                        abrir('aut_passeio', { local: val('aut_pas_local'), data: val('aut_pas_data'), hora_saida: val('aut_pas_saida'), hora_retorno: val('aut_pas_retorno') });
                    };
                    document.addEventListener('click', function (e) {
                        if (e.target && e.target.classList && e.target.classList.contains('doc-modal')) {
                            e.target.style.display = 'none';
                        }
                    });
                })();
            </script>

            <div id="successCard" class="hidden bg-green-50 border border-green-200 rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-lg font-medium text-green-800">Senha Alterada com Sucesso!</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>A senha do aluno <strong id="alunoNomeConfirmacao"></strong> foi alterada para a senha padrão:</p>
                            <div class="mt-2 bg-green-100 border border-green-300 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-lg font-bold text-green-800">123456</span>
                                    <button onclick="copiarSenha()" class="text-green-600 hover:text-green-800 text-sm font-medium">📋 Copiar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button onclick="fecharCardSucesso()" class="text-green-400 hover:text-green-600 ml-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>
