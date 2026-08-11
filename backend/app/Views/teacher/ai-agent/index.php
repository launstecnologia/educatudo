<?php
$layout = 'professor';
$title = $title ?? 'Agentes de IA - EducaTudo';
?>

<?php if (isset($erro_setup)): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Atenção:</strong> <?= htmlspecialchars($erro_setup) ?>
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($erro) && $erro): ?>
    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-red-800 mb-2">Erro ao carregar agentes</h3>
                <p class="text-sm text-red-700 mb-2"><strong>Mensagem:</strong> <?= htmlspecialchars($erro['mensagem']) ?></p>
                <p class="text-xs text-red-600 mb-1"><strong>Arquivo:</strong> <?= htmlspecialchars($erro['arquivo']) ?></p>
                <p class="text-xs text-red-600 mb-2"><strong>Linha:</strong> <?= $erro['linha'] ?></p>
                <details class="mt-2">
                    <summary class="cursor-pointer text-xs text-red-600 hover:text-red-800 font-medium">Ver Stack Trace</summary>
                    <pre class="mt-2 text-xs bg-gray-800 text-green-400 p-3 rounded overflow-auto max-h-64"><?= htmlspecialchars($erro['trace']) ?></pre>
                </details>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Agentes de IA 🤖
            </h2>
            <p class="text-gray-600">
                Crie agentes de IA personalizados alimentados com seus documentos
            </p>
        </div>
        <a href="<?= URL ?>/professor/ai-agents/criar" 
           class="bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Novo Agente
        </a>
    </div>
</div>

<?php if (empty($agentes)): ?>
    <div class="bg-white rounded-xl shadow-lg p-12 text-center">
        <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
            </svg>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">Nenhum agente criado</h3>
        <p class="text-gray-600 mb-6">Crie seu primeiro agente de IA para começar</p>
        <a href="<?= URL ?>/professor/ai-agents/criar" 
           class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Criar Primeiro Agente
        </a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($agentes as $agente): ?>
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow border border-gray-200">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1"><?= htmlspecialchars($agente['nome']) ?></h3>
                            <?php if (!empty($agente['descricao'])): ?>
                                <p class="text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($agente['descricao']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full <?= $agente['ativo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $agente['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </div>
                    
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span><?= $agente['total_documentos'] ?? 0 ?> documento(s)</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <span><?= $agente['total_conversas'] ?? 0 ?> conversa(s)</span>
                        </div>
                    </div>
                    
                    <div class="flex space-x-2">
                        <a href="<?= URL ?>/professor/ai-agents/<?= $agente['id'] ?>" 
                           class="flex-1 bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors text-center text-sm font-medium">
                            Abrir
                        </a>
                        <a href="<?= URL ?>/professor/ai-agents/<?= $agente['id'] ?>/editar" 
                           class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
                            Editar
                        </a>
                        <button onclick="excluirAgente(<?= $agente['id'] ?>, '<?= htmlspecialchars($agente['nome'], ENT_QUOTES) ?>')" 
                                class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors text-sm font-medium">
                            🗑️
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function excluirAgente(agenteId, nomeAgente) {
    if (!confirm(`Tem certeza que deseja excluir o agente "${nomeAgente}"?\n\nEsta ação não pode ser desfeita e excluirá todos os documentos e conversas associados.`)) {
        return;
    }
    
    fetch(`<?= URL ?>/professor/ai-agents/${agenteId}/excluir`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Agente excluído com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao excluir agente'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}
</script>

