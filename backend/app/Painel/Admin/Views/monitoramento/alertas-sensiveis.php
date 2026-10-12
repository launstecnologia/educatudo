<?php
// alertas-sensiveis com botão/link "Ver conversa" (senha + modal)
$statusLabels = [
    'novo' => 'Novo',
    'visualizado' => 'Visualizado',
    'em_acompanhamento' => 'Em acompanhamento',
    'resolvido' => 'Resolvido'
];
$nivelClasses = [
    'baixo' => 'bg-green-100 text-green-700',
    'medio' => 'bg-yellow-100 text-yellow-700',
    'alto' => 'bg-orange-100 text-orange-700',
    'critico' => 'bg-red-100 text-red-700'
];
?>
<!-- alertas-sensiveis ver-conversa -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-semibold text-gray-900">Alertas Sensíveis</h2>
        <p class="text-sm text-gray-500">Monitoramento automático das mensagens do chat da Tudinha.</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-xs text-gray-500">Novos</span>
        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
            <?= (int)($alertas_novos ?? 0) ?>
        </span>
    </div>
</div>

<form method="get" class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="text-sm font-medium text-gray-600">Status</label>
            <select name="status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="todos" <?= ($filtros['status'] ?? '') === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="novo" <?= ($filtros['status'] ?? '') === 'novo' ? 'selected' : '' ?>>Novo</option>
                <option value="visualizado" <?= ($filtros['status'] ?? '') === 'visualizado' ? 'selected' : '' ?>>Visualizado</option>
                <option value="em_acompanhamento" <?= ($filtros['status'] ?? '') === 'em_acompanhamento' ? 'selected' : '' ?>>Em acompanhamento</option>
                <option value="resolvido" <?= ($filtros['status'] ?? '') === 'resolvido' ? 'selected' : '' ?>>Resolvido</option>
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">Nível</label>
            <select name="nivel" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos</option>
                <option value="baixo" <?= ($filtros['nivel'] ?? '') === 'baixo' ? 'selected' : '' ?>>Baixo</option>
                <option value="medio" <?= ($filtros['nivel'] ?? '') === 'medio' ? 'selected' : '' ?>>Médio</option>
                <option value="alto" <?= ($filtros['nivel'] ?? '') === 'alto' ? 'selected' : '' ?>>Alto</option>
                <option value="critico" <?= ($filtros['nivel'] ?? '') === 'critico' ? 'selected' : '' ?>>Crítico</option>
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">Categoria</label>
            <select name="categoria" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todas</option>
                <option value="saude_emocional" <?= ($filtros['categoria'] ?? '') === 'saude_emocional' ? 'selected' : '' ?>>Saúde emocional</option>
                <option value="violencia" <?= ($filtros['categoria'] ?? '') === 'violencia' ? 'selected' : '' ?>>Violência</option>
                <option value="abuso_ou_assedio" <?= ($filtros['categoria'] ?? '') === 'abuso_ou_assedio' ? 'selected' : '' ?>>Abuso/Assédio</option>
                <option value="drogas" <?= ($filtros['categoria'] ?? '') === 'drogas' ? 'selected' : '' ?>>Drogas</option>
                <option value="sexual_improprio" <?= ($filtros['categoria'] ?? '') === 'sexual_improprio' ? 'selected' : '' ?>>Sexual impróprio</option>
                <option value="discurso_de_odio" <?= ($filtros['categoria'] ?? '') === 'discurso_de_odio' ? 'selected' : '' ?>>Discurso de ódio</option>
                <option value="linguagem_agressiva_recorrente" <?= ($filtros['categoria'] ?? '') === 'linguagem_agressiva_recorrente' ? 'selected' : '' ?>>Linguagem agressiva (recorrente)</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full bg-purple-600 text-white rounded-lg px-4 py-2 text-sm hover:bg-purple-700">
                Filtrar
            </button>
        </div>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nível</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resumo</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-52">Ações</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($alertas)): ?>
                <tr>
                    <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">
                        Nenhum alerta encontrado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($alertas as $alerta): ?>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= date('d/m/Y H:i', strtotime($alerta['created_at'])) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <?= htmlspecialchars($alerta['aluno_nome'] ?? 'Aluno') ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= htmlspecialchars($alerta['turma_nome'] ?? '-') ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= htmlspecialchars(str_replace('_', ' ', $alerta['categoria'])) ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php $nivel = $alerta['nivel'] ?? 'baixo'; ?>
                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $nivelClasses[$nivel] ?? 'bg-gray-100 text-gray-600' ?>">
                                <?= ucfirst($nivel) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <?= htmlspecialchars($alerta['mensagem_resumo'] ?? '') ?>
                            <div class="mt-2">
                                <button type="button" class="ver-conversa text-amber-600 hover:text-amber-700 font-medium text-xs underline"
                                        data-alerta-id="<?= (int)$alerta['id'] ?>">
                                    Ver conversa
                                </button>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= $statusLabels[$alerta['status']] ?? ucfirst($alerta['status']) ?>
                        </td>
                        <td class="px-4 py-3 text-sm align-top w-52">
                            <div class="space-y-2">
                                <button type="button" class="ver-conversa w-full bg-amber-600 text-white rounded-md px-3 py-2 text-xs font-medium hover:bg-amber-700 shadow-sm"
                                        data-alerta-id="<?= (int)$alerta['id'] ?>">
                                    Ver conversa
                                </button>
                                <form method="post" action="<?= URL ?>/admin/monitoramento/alertas/atualizar" class="space-y-2">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <input type="hidden" name="alerta_id" value="<?= (int)$alerta['id'] ?>">
                                    <select name="acao" class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs">
                                        <option value="visualizado">Marcar visualizado</option>
                                        <option value="acompanhamento">Em acompanhamento</option>
                                        <option value="resolvido">Resolvido</option>
                                        <option value="observacao">Adicionar observação</option>
                                    </select>
                                    <textarea name="observacoes" rows="2" class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs" placeholder="Observações internas"></textarea>
                                    <button type="submit" class="w-full bg-gray-900 text-white rounded-md px-2 py-1 text-xs hover:bg-gray-800">
                                        Salvar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Ver conversa sensível -->
<div id="modalVerConversa" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" aria-hidden="true"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Visualizar conversa sensível</h3>
            </div>
            <div id="modalVerConversaAviso" class="p-6 space-y-4 overflow-y-auto flex-1">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-amber-800 text-sm">
                    <p class="font-medium mb-2">Aviso importante</p>
                    <p>Você está prestes a acessar o conteúdo completo de uma conversa sensível entre um aluno e a Tudinha. Ao prosseguir, você se responsabiliza por <strong>não repassar essas mensagens a terceiros</strong> e por tratar essas informações com a devida confidencialidade.</p>
                </div>
                <div>
                    <label for="modalVerConversaSenha" class="block text-sm font-medium text-gray-700 mb-1">Sua senha</label>
                    <input type="password" id="modalVerConversaSenha" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Digite sua senha para confirmar" autocomplete="current-password">
                    <p id="modalVerConversaErro" class="mt-2 text-sm text-red-600 hidden"></p>
                </div>
            </div>
            <div id="modalVerConversaConteudo" class="p-6 space-y-4 overflow-y-auto flex-1 hidden">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase mb-1">Pergunta do aluno</p>
                    <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-800 whitespace-pre-wrap" id="modalVerConversaPergunta"></div>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase mb-1">Resposta da Tudinha</p>
                    <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-800 whitespace-pre-wrap" id="modalVerConversaResposta"></div>
                </div>
            </div>
            <div class="p-6 border-t border-gray-200 flex justify-end gap-2">
                <button type="button" id="modalVerConversaFechar" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Fechar</button>
                <button type="button" id="modalVerConversaConfirmar" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm hover:bg-amber-700">Confirmar e ver conversa</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('modalVerConversa');
    var aviso = document.getElementById('modalVerConversaAviso');
    var conteudo = document.getElementById('modalVerConversaConteudo');
    var senhaInput = document.getElementById('modalVerConversaSenha');
    var erroEl = document.getElementById('modalVerConversaErro');
    var perguntaEl = document.getElementById('modalVerConversaPergunta');
    var respostaEl = document.getElementById('modalVerConversaResposta');
    var btnFechar = document.getElementById('modalVerConversaFechar');
    var btnConfirmar = document.getElementById('modalVerConversaConfirmar');
    var csrfToken = <?= json_encode($csrf_token ?? '') ?>;
    var urlVerConteudo = <?= json_encode(URL . '/admin/monitoramento/alertas/ver-conteudo') ?>;
    var alertaIdAtual = null;

    function abrirModal(alertaId) {
        alertaIdAtual = alertaId;
        aviso.classList.remove('hidden');
        conteudo.classList.add('hidden');
        btnConfirmar.classList.remove('hidden');
        senhaInput.value = '';
        erroEl.classList.add('hidden');
        erroEl.textContent = '';
        modal.classList.remove('hidden');
        senhaInput.focus();
    }

    function fecharModal() {
        modal.classList.add('hidden');
        alertaIdAtual = null;
    }

    document.querySelectorAll('.ver-conversa').forEach(function(btn) {
        btn.addEventListener('click', function() {
            abrirModal(parseInt(this.getAttribute('data-alerta-id'), 10));
        });
    });

    btnFechar.addEventListener('click', fecharModal);
    modal.querySelector('.bg-black\\/50').addEventListener('click', fecharModal);

    btnConfirmar.addEventListener('click', function() {
        if (!alertaIdAtual) return;
        var senha = senhaInput.value.trim();
        if (!senha) {
            erroEl.textContent = 'Digite sua senha.';
            erroEl.classList.remove('hidden');
            return;
        }
        erroEl.classList.add('hidden');
        btnConfirmar.disabled = true;
        var form = new FormData();
        form.append('_token', csrfToken);
        form.append('alerta_id', alertaIdAtual);
        form.append('senha', senha);
        fetch(urlVerConteudo, {
            method: 'POST',
            body: form
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data }; }); })
        .then(function(result) {
            btnConfirmar.disabled = false;
            if (result.ok && result.data.success) {
                var pergunta = result.data.pergunta_aluno || '';
                var resposta = result.data.resposta_tudinha || '';
                if (!pergunta && !resposta) {
                    pergunta = 'Conteúdo completo não disponível para este alerta.';
                    resposta = '';
                }
                perguntaEl.textContent = pergunta;
                respostaEl.textContent = resposta;
                aviso.classList.add('hidden');
                conteudo.classList.remove('hidden');
                btnConfirmar.classList.add('hidden');
                return;
            }
            erroEl.textContent = (result.data && result.data.error) ? result.data.error : 'Erro ao carregar conteúdo. Tente novamente.';
            erroEl.classList.remove('hidden');
        })
        .catch(function() {
            btnConfirmar.disabled = false;
            erroEl.textContent = 'Erro de conexão. Tente novamente.';
            erroEl.classList.remove('hidden');
        });
    });
})();
</script>

