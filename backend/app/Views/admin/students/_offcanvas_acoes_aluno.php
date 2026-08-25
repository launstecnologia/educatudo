<?php
$alunoIdAcoes = (int) ($student['id'] ?? 0);
$alunoNomeAcoes = (string) ($student['nome'] ?? 'Aluno');
$alunoRaAcoes = (string) ($student['ra'] ?? '-');
$inclusaoVisivel = !class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('inclusao');
$alunoAtivoAcoes = (int) ($student['ativo'] ?? 0) === 1;
$semResponsaveis = empty($responsaveis_aluno);

$gruposAcoesAluno = [
    'acesso' => [
        'titulo' => 'Acesso e segurança',
        'itens' => [
            [
                'id' => 'acessar-aluno',
                'label' => 'Acessar como aluno',
                'descricao' => 'Abrir o portal com a conta deste aluno',
                'icon' => 'fa-right-to-bracket',
                'perm_key' => 'acao_rapida_acessar_aluno',
                'perm_action' => 'visualizar',
                'href' => URL . '/admin/students/' . $alunoIdAcoes . '/acessar-como',
            ],
            [
                'id' => 'acessar-pai',
                'label' => 'Acessar como responsável',
                'descricao' => 'Entrar no portal dos pais com um responsável vinculado',
                'icon' => 'fa-people-roof',
                'perm_key' => 'acao_rapida_acessar_pai',
                'perm_action' => 'visualizar',
                'onclick' => 'abrirModalAcessarComoPai()',
                'disabled' => $semResponsaveis,
            ],
            [
                'id' => 'resetar-senha',
                'label' => 'Redefinir senha',
                'descricao' => 'Gerar uma nova senha padrão para o aluno',
                'icon' => 'fa-key',
                'perm_key' => 'acao_rapida_resetar_senha',
                'perm_action' => 'alterar',
                'onclick' => 'alterarSenhaPadrao(' . $alunoIdAcoes . ', ' . json_encode($alunoNomeAcoes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ')',
            ],
        ],
    ],
    'vinculos' => [
        'titulo' => 'Matrícula e vínculos',
        'itens' => array_values(array_filter([
            $matriculas_schema_ready ? [
                'id' => 'nova-matricula',
                'label' => 'Nova matrícula',
                'descricao' => 'Vincular o aluno a uma turma e ano letivo',
                'icon' => 'fa-graduation-cap',
                'perm_key' => 'matriculas_aluno',
                'perm_action' => 'cadastrar',
                'onclick' => 'abrirModalMatricula()',
            ] : null,
            [
                'id' => 'rematricular',
                'label' => 'Rematricular',
                'descricao' => 'Iniciar o processo de rematrícula',
                'icon' => 'fa-file-signature',
                'href' => URL . '/admin/enrollment/create?aluno_id=' . $alunoIdAcoes . '&tipo=rematricula',
            ],
            [
                'id' => 'cadastrar-responsavel',
                'label' => 'Cadastrar responsável',
                'descricao' => 'Vincular um novo responsável a este aluno',
                'icon' => 'fa-user-plus',
                'perm_key' => 'acao_rapida_cadastrar_responsavel',
                'perm_action' => 'cadastrar',
                'onclick' => 'abrirModalCadastrarPai(' . $alunoIdAcoes . ')',
            ],
            [
                'id' => 'autorizacoes',
                'label' => 'Autorizações de retirada',
                'descricao' => 'Emitir autorizações de saída, retirada, imagem e passeio',
                'icon' => 'fa-file-signature',
                'perm_key' => 'declaracoes_aluno',
                'perm_action' => 'visualizar',
                'onclick' => "abrirModalDoc('Autorizacoes')",
            ],
            (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('financeiro')) ? [
                'id' => 'financeiro',
                'label' => 'Financeiro',
                'descricao' => 'Ver saldo, faturas e extrato do aluno',
                'icon' => 'fa-dollar-sign',
                'onclick' => 'abrirFinanceiro(' . $alunoIdAcoes . ')',
            ] : null,
        ])),
    ],
    'documentos' => [
        'titulo' => 'Documentos e atendimento',
        'itens' => array_values(array_filter([
            [
                'id' => 'declaracao',
                'label' => 'Gerar declaração',
                'descricao' => 'Matrícula, transferência, frequência e comparecimento',
                'icon' => 'fa-file-lines',
                'perm_key' => 'declaracoes_aluno',
                'perm_action' => 'visualizar',
                'onclick' => "abrirModalDoc('Declaracoes')",
            ],
            [
                'id' => 'reuniao',
                'label' => 'Registrar reunião com responsáveis',
                'descricao' => 'Abrir a ata de reunião com pais',
                'icon' => 'fa-comments',
                'href' => URL . '/admin/reunioes/aluno?aluno_id=' . $alunoIdAcoes,
            ],
            [
                'id' => 'documentacao',
                'label' => 'Documentação',
                'descricao' => 'Histórico escolar, ficha e boletim oficial',
                'icon' => 'fa-folder-open',
                'perm_key' => 'declaracoes_aluno',
                'perm_action' => 'visualizar',
                'onclick' => "abrirModalDoc('Documentacao')",
            ],
            $inclusaoVisivel ? [
                'id' => 'educainclui',
                'label' => 'EducaInclui / Laudo',
                'descricao' => 'Máscara de acessibilidade e laudos do aluno',
                'icon' => 'fa-universal-access',
                'perm_key' => 'inclusao',
                'perm_action' => 'visualizar',
                'onclick' => 'abrirInclusao(' . $alunoIdAcoes . ')',
            ] : null,
        ])),
    ],
    'outros' => [
        'titulo' => 'Outros',
        'itens' => [
            [
                'id' => 'tudinha',
                'label' => 'Análise da Tudinha',
                'descricao' => 'Gerar análise pedagógica com inteligência artificial',
                'icon' => 'fa-wand-magic-sparkles',
                'perm_key' => 'acao_rapida_analise_tudinha',
                'perm_action' => 'alterar',
                'onclick' => 'abrirModalAnalise(' . $alunoIdAcoes . ')',
            ],
            [
                'id' => 'facial',
                'label' => 'Entrada e saída facial',
                'descricao' => 'Eventos de reconhecimento facial deste aluno',
                'icon' => 'fa-door-open',
                'perm_key' => 'reconhecimento_facial',
                'perm_action' => 'visualizar',
                'href' => URL . '/admin/reconhecimento-facial/alunos/' . $alunoIdAcoes . '/eventos',
            ],
        ],
    ],
    'critico' => [
        'titulo' => 'Área crítica',
        'danger' => true,
        'itens' => [
            $alunoAtivoAcoes ? [
                'id' => 'inativar',
                'label' => 'Inativar / Transferir',
                'descricao' => 'Encerra matrículas e registra o motivo da saída',
                'icon' => 'fa-user-slash',
                'perm_key' => 'acao_rapida_ativar_desativar',
                'perm_action' => 'alterar',
                'onclick' => 'abrirModalInativarAluno()',
                'danger' => true,
            ] : [
                'id' => 'ativar',
                'label' => 'Ativar aluno',
                'descricao' => 'Reativar o acesso e o vínculo deste aluno',
                'icon' => 'fa-user-check',
                'perm_key' => 'acao_rapida_ativar_desativar',
                'perm_action' => 'alterar',
                'onclick' => 'abrirModalAtivarAluno()',
            ],
            [
                'id' => 'excluir',
                'label' => 'Excluir aluno',
                'descricao' => 'Oculta o aluno da visualização. Os dados não são apagados.',
                'icon' => 'fa-trash-can',
                'perm_key' => 'acao_rapida_excluir_aluno',
                'perm_action' => 'excluir',
                'onclick' => 'abrirModalExcluirAluno()',
                'danger' => true,
            ],
        ],
    ],
];
?>
<div id="offcanvasAcoesAlunoBackdrop"
     class="fixed inset-0 bg-slate-900/40 z-[90] hidden"
     onclick="fecharOffcanvasAcoesAluno()"
     aria-hidden="true"></div>
<aside id="offcanvasAcoesAluno"
       class="fixed top-0 right-0 h-full w-full max-w-[28rem] md:max-w-[30rem] bg-white shadow-2xl z-[91] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       role="dialog"
       aria-modal="true"
       aria-labelledby="offcanvasAcoesAlunoTitulo"
       aria-hidden="true">
    <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-200 flex-shrink-0">
        <div class="min-w-0">
            <h2 id="offcanvasAcoesAlunoTitulo" class="text-lg font-bold text-gray-900">Ações do aluno</h2>
            <p class="text-sm text-gray-500 mt-0.5 truncate"><?= safe_htmlspecialchars($alunoNomeAcoes) ?> · RA <?= safe_htmlspecialchars($alunoRaAcoes) ?></p>
        </div>
        <button type="button"
                onclick="fecharOffcanvasAcoesAluno()"
                class="w-9 h-9 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                aria-label="Fechar ações do aluno">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>
    <div class="px-5 py-3 border-b border-gray-100 flex-shrink-0">
        <label for="offcanvasAcoesAlunoBusca" class="sr-only">Buscar ação</label>
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="search"
                   id="offcanvasAcoesAlunoBusca"
                   placeholder="Buscar ação..."
                   autocomplete="off"
                   oninput="filtrarAcoesAluno(this.value)"
                   class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
    </div>
    <div id="offcanvasAcoesAlunoLista" class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
        <?php foreach ($gruposAcoesAluno as $grupoId => $grupo): ?>
            <?php $isDanger = !empty($grupo['danger']); ?>
            <section class="aluno-acao-grupo <?= $isDanger ? 'pt-4 border-t border-red-100' : '' ?>" data-grupo="<?= safe_htmlspecialchars($grupoId) ?>">
                <h3 class="text-xs font-semibold uppercase tracking-wide mb-2 <?= $isDanger ? 'text-red-600' : 'text-slate-400' ?>">
                    <?= safe_htmlspecialchars($grupo['titulo']) ?>
                </h3>
                <div class="space-y-1">
                    <?php foreach ($grupo['itens'] as $item): ?>
                        <?php
                        $dangerItem = !empty($item['danger']);
                        $disabledItem = !empty($item['disabled']);
                        $permKey = (string) ($item['perm_key'] ?? '');
                        $permAction = (string) ($item['perm_action'] ?? 'visualizar');
                        $itemClass = 'aluno-acao-item w-full flex items-start gap-3 px-3 py-2.5 rounded-lg text-left transition-colors ';
                        if ($disabledItem) {
                            $itemClass .= 'opacity-50 cursor-not-allowed';
                        } elseif ($dangerItem) {
                            $itemClass .= 'border border-red-200 text-red-700 hover:bg-red-50';
                        } else {
                            $itemClass .= 'hover:bg-slate-50 text-slate-800';
                        }
                        $iconWrap = $dangerItem
                            ? 'w-9 h-9 rounded-lg bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0'
                            : 'w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0';
                        $attrs = 'data-acao-label="' . htmlspecialchars(mb_strtolower($item['label'] . ' ' . ($item['descricao'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') . '"';
                        if ($permKey !== '') {
                            $attrs .= ' data-perm-key="' . htmlspecialchars($permKey, ENT_QUOTES, 'UTF-8') . '" data-perm-action="' . htmlspecialchars($permAction, ENT_QUOTES, 'UTF-8') . '"';
                        }
                        $onclick = $disabledItem ? '' : (string) ($item['onclick'] ?? '');
                        if ($onclick !== '') {
                            $onclick .= ';fecharOffcanvasAcoesAluno();';
                        }
                        ?>
                        <?php if (!empty($item['href']) && !$disabledItem): ?>
                            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                               class="<?= $itemClass ?>"
                               <?= $attrs ?>>
                                <span class="<?= $iconWrap ?>"><i class="fa-solid <?= safe_htmlspecialchars($item['icon']) ?>"></i></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold"><?= safe_htmlspecialchars($item['label']) ?></span>
                                    <span class="block text-xs text-slate-500 mt-0.5"><?= safe_htmlspecialchars($item['descricao'] ?? '') ?></span>
                                </span>
                            </a>
                        <?php else: ?>
                            <button type="button"
                                    class="<?= $itemClass ?>"
                                    <?= $attrs ?>
                                    <?= $disabledItem ? 'disabled' : '' ?>
                                    <?= $onclick !== '' ? 'onclick="' . htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                <span class="<?= $iconWrap ?>"><i class="fa-solid <?= safe_htmlspecialchars($item['icon']) ?>"></i></span>
                                <span class="min-w-0 text-left">
                                    <span class="block text-sm font-semibold"><?= safe_htmlspecialchars($item['label']) ?></span>
                                    <span class="block text-xs text-slate-500 mt-0.5"><?= safe_htmlspecialchars($item['descricao'] ?? '') ?></span>
                                </span>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <p id="offcanvasAcoesAlunoVazio" class="hidden text-sm text-slate-500 text-center py-8">Nenhuma ação encontrada.</p>
        <p class="text-xs text-slate-400 pt-2">Ações sensíveis ficam registradas na auditoria do aluno.</p>
    </div>
</aside>
<script>
(function () {
    var drawer = document.getElementById('offcanvasAcoesAluno');
    var backdrop = document.getElementById('offcanvasAcoesAlunoBackdrop');
    var lastTrigger = null;
    var previousOverflow = '';

    function focaveis() {
        if (!drawer) return [];
        return Array.prototype.slice.call(drawer.querySelectorAll('a[href], button:not([disabled]), input, textarea, select, [tabindex]:not([tabindex="-1"])'))
            .filter(function (el) { return el.offsetParent !== null && !el.classList.contains('hidden'); });
    }

    window.abrirOffcanvasAcoesAluno = function (trigger) {
        if (!drawer || !backdrop) return;
        lastTrigger = trigger || document.activeElement;
        backdrop.classList.remove('hidden');
        drawer.setAttribute('aria-hidden', 'false');
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(function () {
            drawer.classList.remove('translate-x-full');
            var busca = document.getElementById('offcanvasAcoesAlunoBusca');
            if (busca) {
                busca.value = '';
                filtrarAcoesAluno('');
                busca.focus();
            }
        });
    };

    window.fecharOffcanvasAcoesAluno = function () {
        if (!drawer || !backdrop) return;
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        setTimeout(function () {
            backdrop.classList.add('hidden');
            document.body.style.overflow = previousOverflow || '';
            if (lastTrigger && typeof lastTrigger.focus === 'function') {
                lastTrigger.focus();
            }
        }, 280);
    };

    window.filtrarAcoesAluno = function (termo) {
        var q = (termo || '').toString().trim().toLowerCase();
        var grupos = document.querySelectorAll('#offcanvasAcoesAlunoLista .aluno-acao-grupo');
        var visiveis = 0;
        grupos.forEach(function (grupo) {
            var itens = grupo.querySelectorAll('.aluno-acao-item');
            var grupoVisivel = 0;
            itens.forEach(function (item) {
                if (item.getAttribute('data-perm-hidden') === '1') {
                    return;
                }
                var hay = item.getAttribute('data-acao-label') || '';
                var ok = !q || hay.indexOf(q) !== -1;
                item.classList.toggle('hidden', !ok);
                if (ok) {
                    grupoVisivel += 1;
                    visiveis += 1;
                }
            });
            grupo.classList.toggle('hidden', grupoVisivel === 0);
        });
        var vazio = document.getElementById('offcanvasAcoesAlunoVazio');
        if (vazio) vazio.classList.toggle('hidden', visiveis > 0);
    };

    document.addEventListener('keydown', function (e) {
        if (!drawer || drawer.getAttribute('aria-hidden') === 'true') return;
        if (e.key === 'Escape') {
            e.preventDefault();
            fecharOffcanvasAcoesAluno();
            return;
        }
        if (e.key !== 'Tab') return;
        var lista = focaveis();
        if (!lista.length) return;
        var primeiro = lista[0];
        var ultimo = lista[lista.length - 1];
        if (e.shiftKey && document.activeElement === primeiro) {
            e.preventDefault();
            ultimo.focus();
        } else if (!e.shiftKey && document.activeElement === ultimo) {
            e.preventDefault();
            primeiro.focus();
        }
    });
})();
</script>
