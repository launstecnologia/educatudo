<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Gestão de Blocos de Provas 📚
            </h2>
            <p class="text-gray-600">
                Agrupe provas de diferentes matérias em blocos e defina data/horário
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/provas/tipos-avaliacao" 
               class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3 rounded-xl hover:from-amber-600 hover:to-orange-600 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                Tipos de Avaliação
            </a>
            <a href="<?= URL ?>/admin/blocos-modelo" 
               class="bg-gradient-to-r from-blue-600 to-cyan-600 text-white px-6 py-3 rounded-xl hover:from-blue-700 hover:to-cyan-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-3zM14 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1h-4a1 1 0 01-1-1v-3z"></path>
                </svg>
                Blocos Modelo
            </a>
            <a href="<?= URL ?>/admin/provas/blocos/criar" 
               class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Nova Prova
            </a>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<?php if (isset($stats)): ?>
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Total</p>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['total_blocos'] ?? 0 ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-amber-500">
        <div>
            <p class="text-sm text-gray-600">Aguardando</p>
            <p class="text-3xl font-bold text-gray-900"><?= $stats['blocos_aguardando'] ?? 0 ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-indigo-500">
        <div>
            <p class="text-sm text-gray-600">Aprovado</p>
            <p class="text-3xl font-bold text-gray-900"><?= $stats['blocos_aprovados'] ?? 0 ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <div>
            <p class="text-sm text-gray-600">Liberado</p>
            <p class="text-3xl font-bold text-gray-900"><?= $stats['blocos_liberados'] ?? 0 ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-gray-500">
        <div>
            <p class="text-sm text-gray-600">Concluído</p>
            <p class="text-3xl font-bold text-gray-900"><?= $stats['blocos_concluidos'] ?? 0 ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Provas Pendentes</p>
                <p class="text-3xl font-bold text-gray-900"><?= count($provas_pendentes ?? []) ?></p>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Provas Pendentes Alert -->
<?php if (!empty($provas_pendentes)): ?>
<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm text-yellow-700">
                <strong><?= count($provas_pendentes) ?> prova(s) pendente(s)</strong> aguardando agrupamento em blocos.
                <a href="<?= URL ?>/admin/provas/blocos/criar" class="font-medium underline ml-1">Criar novo bloco</a>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Blocos Table -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Lista de Blocos</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Horário</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($blocos)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg mb-2">Nenhum bloco criado ainda</p>
                            <p class="text-sm text-gray-400 mb-4">Comece criando um novo bloco de provas</p>
                            <a href="<?= URL ?>/admin/provas/blocos/criar" 
                               class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg hover:opacity-90">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Criar Primeiro Bloco
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($blocos as $bloco): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($bloco['titulo']) ?></div>
                                <?php if ($bloco['descricao']): ?>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($bloco['descricao'], 0, 50)) ?><?= strlen($bloco['descricao']) > 50 ? '...' : '' ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?= date('d/m/Y', strtotime($bloco['data_prova'])) ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?= date('H:i', strtotime($bloco['hora_inicio'])) ?> - <?= date('H:i', strtotime($bloco['hora_fim'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $entregues = (int)($bloco['total_provas_entregues'] ?? 0);
                                $esperadas = (int)($bloco['total_provas_esperadas'] ?? 0);
                                $textoProvas = $esperadas > 0 ? "{$entregues}/{$esperadas}" : ($bloco['total_provas'] ?? 0);
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    <?= $textoProvas ?> <?= $esperadas > 0 ? '' : 'prova(s)' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?php
                                $turmasTexto = trim($bloco['turmas_demarcadas'] ?? '');
                                if ($turmasTexto === '' && !empty(trim($bloco['turmas_por_professor'] ?? ''))) {
                                    $turmasTexto = trim($bloco['turmas_por_professor']);
                                }
                                if ($turmasTexto !== '') {
                                    echo htmlspecialchars($turmasTexto);
                                } else {
                                    echo $bloco['turma_nome'] ? htmlspecialchars($bloco['turma_nome']) : '<span class="text-gray-400">Todas</span>';
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $st = $bloco['status'] ?? 'aguardando';
                                $labels = ['aguardando' => ['Aguardando', 'bg-amber-100 text-amber-800'], 'aprovado' => ['Aprovado', 'bg-indigo-100 text-indigo-800'], 'liberado' => ['Liberado', 'bg-green-100 text-green-800'], 'concluido' => ['Concluído', 'bg-gray-100 text-gray-800']];
                                $lb = $labels[$st] ?? ['Aguardando', 'bg-amber-100 text-amber-800'];
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $lb[1] ?>"><?= $lb[0] ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium">
                                <div class="flex flex-wrap gap-2">
                                    <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/gerenciar" 
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 text-green-700 hover:bg-green-100 border border-green-200 transition-colors" title="Gerenciar Provas">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                        <span>Gerenciar</span>
                                    </a>
                                    <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/editar" 
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 transition-colors" title="Editar">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        <span>Editar</span>
                                    </a>
                                    <?php if ($st === 'aprovado'): ?>
                                    <button type="button" onclick="toggleLiberado(<?= $bloco['id'] ?>, 1)" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition-colors" title="Liberar para alunos">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                        <span>Liberar</span>
                                    </button>
                                    <?php elseif ($st === 'liberado'): ?>
                                    <button type="button" onclick="toggleLiberado(<?= $bloco['id'] ?>, 0)" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 transition-colors" title="Bloquear">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                        </svg>
                                        <span>Bloquear</span>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" onclick="excluirBloco(<?= $bloco['id'] ?>)" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition-colors" title="Excluir">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        <span>Excluir</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleLiberado(blocoId, novoStatus) {
    if (!confirm('Tem certeza que deseja ' + (novoStatus ? 'liberar' : 'bloquear') + ' este bloco?')) {
        return;
    }
    
    fetch(`<?= URL ?>/admin/provas/blocos/${blocoId}/toggle-liberado`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao alterar status do bloco');
    });
}

var blocoIdExcluir = null;
function excluirBloco(blocoId) {
    blocoIdExcluir = blocoId;
    document.getElementById('modalExcluirSenha').classList.remove('hidden');
    document.getElementById('inputSenhaExcluir').value = '';
    document.getElementById('inputSenhaExcluir').focus();
}
function fecharModalExcluir() {
    blocoIdExcluir = null;
    document.getElementById('modalExcluirSenha').classList.add('hidden');
}
function confirmarExcluirComSenha() {
    if (!blocoIdExcluir) return;
    var senha = document.getElementById('inputSenhaExcluir').value.trim();
    if (!senha) {
        alert('Digite sua senha para confirmar.');
        return;
    }
    var btn = document.getElementById('btnConfirmarExcluir');
    if (btn) { btn.disabled = true; btn.textContent = 'Excluindo...'; }
    fetch(`<?= URL ?>/admin/provas/blocos/${blocoIdExcluir}/excluir`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ senha: senha })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            fecharModalExcluir();
            location.reload();
        } else {
            alert(data.error || 'Erro ao excluir');
            if (btn) { btn.disabled = false; btn.textContent = 'Excluir'; }
        }
    })
    .catch(function(err) {
        console.error(err);
        alert('Erro de conexão.');
        if (btn) { btn.disabled = false; btn.textContent = 'Excluir'; }
    });
}
</script>

<!-- Modal: confirmar exclusão com senha -->
<div id="modalExcluirSenha" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" onclick="fecharModalExcluir()"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirmar exclusão</h3>
            <p class="text-sm text-gray-600 mb-4">Para desativar este bloco, digite sua senha. O bloco deixará de aparecer para alunos e professores; os dados são mantidos (LGPD). Quem desativou ficará registrado.</p>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sua senha</label>
            <input type="password" id="inputSenhaExcluir" placeholder="Senha" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4" onkeydown="if (event.key==='Enter') confirmarExcluirComSenha();">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="fecharModalExcluir()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="button" id="btnConfirmarExcluir" onclick="confirmarExcluirComSenha()" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Excluir</button>
            </div>
        </div>
    </div>
</div>
