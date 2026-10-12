<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Gerenciamento de Migrations 🗄️
            </h2>
            <p class="text-gray-600">
                Execute migrations em bancos de dados de diferentes escolas
            </p>
        </div>
        <button onclick="abrirModalEscola()" 
                class="btn-primary-custom px-6 py-3 rounded-xl transition-all duration-300 flex items-center shadow-lg hover:shadow-xl hover:opacity-90">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Adicionar Escola
        </button>
    </div>
</div>

<!-- Escolas Cadastradas -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Escolas Cadastradas</h3>
    </div>
    <div class="p-6">
        <?php if (empty($escolas)): ?>
            <div class="text-center py-8">
                <p class="text-gray-500">Nenhuma escola cadastrada. Clique em "Adicionar Escola" para começar.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($escolas as $escola): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($escola['escola_nome']) ?></h4>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?= htmlspecialchars($escola['db_host']) ?>:<?= $escola['db_port'] ?> / <?= htmlspecialchars($escola['db_name']) ?>
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="px-3 py-1 rounded-full text-xs font-medium <?= $escola['ativo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= $escola['ativo'] ? 'Ativa' : 'Inativa' ?>
                                </span>
                                <button onclick="editarEscola(<?= htmlspecialchars(json_encode($escola)) ?>)" 
                                        class="btn-primary-custom px-3 py-1 rounded-lg text-sm hover:opacity-90">
                                    Editar
                                </button>
                                <button onclick="deletarEscola(<?= $escola['id'] ?>)" 
                                        class="px-3 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                    Deletar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Migrations Disponíveis -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Migrations Disponíveis</h3>
        <p class="text-sm text-gray-600 mt-1">Selecione uma escola e execute as migrations desejadas</p>
    </div>
    <div class="p-6">
        <?php if (empty($migrations)): ?>
            <div class="text-center py-8">
                <p class="text-gray-500">Nenhuma migration encontrada na pasta database/migrations</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($migrations as $migration): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($migration['file']) ?></h4>
                                <p class="text-sm text-gray-600 mt-1">
                                    Tamanho: <?= number_format($migration['size'] / 1024, 2) ?> KB | 
                                    Modificado: <?= date('d/m/Y H:i', $migration['modified']) ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Seleção de Escola e Execução -->
                        <div class="flex items-center space-x-4">
                            <select id="escola_<?= md5($migration['file']) ?>" 
                                    data-migration-file="<?= htmlspecialchars($migration['file']) ?>"
                                    class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Selecione uma escola...</option>
                                <?php foreach ($escolas as $escola): ?>
                                    <option value="<?= $escola['id'] ?>">
                                        <?= htmlspecialchars($escola['escola_nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <?php
                            // Verificar status para cada escola
                            $statusPorEscola = [];
                            foreach ($escolas as $escola) {
                                $migExecutada = $escola['migrations_executadas'][$migration['file']] ?? null;
                                $statusPorEscola[$escola['id']] = $migExecutada;
                            }
                            ?>
                            
                            <button onclick="executarMigration('<?= htmlspecialchars($migration['file']) ?>', '<?= htmlspecialchars(json_encode($statusPorEscola)) ?>')" 
                                    class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                Executar
                            </button>
                        </div>
                        
                        <!-- Status por Escola -->
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <p class="text-sm font-medium text-gray-700 mb-2">Status por Escola:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <?php foreach ($escolas as $escola): ?>
                                    <?php
                                    $migExecutada = $escola['migrations_executadas'][$migration['file']] ?? null;
                                    ?>
                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                        <span class="text-sm text-gray-700"><?= htmlspecialchars($escola['escola_nome']) ?></span>
                                        <?php if ($migExecutada): ?>
                                            <?php if ($migExecutada['status'] === 'sucesso'): ?>
                                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-medium">
                                                    ✓ Executada em <?= date('d/m/Y H:i', strtotime($migExecutada['executada_em'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-medium" 
                                                      title="<?= htmlspecialchars($migExecutada['mensagem_erro'] ?? 'Erro desconhecido') ?>">
                                                    ✗ Erro
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">
                                                Pendente
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Adicionar/Editar Escola -->
<div id="modalEscola" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900" id="modalEscolaTitulo">Adicionar Escola</h3>
        </div>
        <form id="formEscola" class="p-6 space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="id" id="escola_id">
            
            <div>
                <label for="escola_nome" class="block text-sm font-medium text-gray-700 mb-2">
                    Nome da Escola <span class="text-red-500">*</span>
                </label>
                <input type="text" id="escola_nome" name="escola_nome" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="db_host" class="block text-sm font-medium text-gray-700 mb-2">
                        Host do Banco <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="db_host" name="db_host" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                    <label for="db_port" class="block text-sm font-medium text-gray-700 mb-2">
                        Porta <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="db_port" name="db_port" value="3306" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            
            <div>
                <label for="db_name" class="block text-sm font-medium text-gray-700 mb-2">
                    Nome do Banco <span class="text-red-500">*</span>
                </label>
                <input type="text" id="db_name" name="db_name" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="db_user" class="block text-sm font-medium text-gray-700 mb-2">
                        Usuário <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="db_user" name="db_user" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                    <label for="db_pass" class="block text-sm font-medium text-gray-700 mb-2">
                        Senha <span class="text-red-500">*</span>
                    </label>
                    <input type="password" id="db_pass" name="db_pass" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            
            <!-- Configurações SSH (apenas para demo.educatudo.com) -->
            <?php 
            $isDemo = strpos($_SERVER['HTTP_HOST'] ?? '', 'demo.educatudo.com') !== false;
            if ($isDemo): 
            ?>
            <div class="border-t border-gray-200 pt-4 mt-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-4">Configurações SSH (Opcional)</h4>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="ssh_host" class="block text-sm font-medium text-gray-700 mb-2">
                            SSH Host
                        </label>
                        <input type="text" id="ssh_host" name="ssh_host"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label for="ssh_port" class="block text-sm font-medium text-gray-700 mb-2">
                            SSH Porta
                        </label>
                        <input type="number" id="ssh_port" name="ssh_port" value="22"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <label for="ssh_user" class="block text-sm font-medium text-gray-700 mb-2">
                            SSH Usuário
                        </label>
                        <input type="text" id="ssh_user" name="ssh_user"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label for="ssh_pass" class="block text-sm font-medium text-gray-700 mb-2">
                            SSH Senha
                        </label>
                        <input type="password" id="ssh_pass" name="ssh_pass"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="ssh_path" class="block text-sm font-medium text-gray-700 mb-2">
                        Caminho do Projeto no Servidor
                    </label>
                    <input type="text" id="ssh_path" name="ssh_path" placeholder="/home/usuario/projeto"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Caminho completo onde o projeto está localizado no servidor</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="ativo" id="escola_ativo" value="1" checked
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Ativa</span>
                </label>
            </div>
            
            <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                <button type="button" onclick="fecharModalEscola()" 
                        class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                    Cancelar
                </button>
                <button type="submit" 
                        class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let escolaEditando = null;

function abrirModalEscola(escola = null) {
    escolaEditando = escola;
    const modal = document.getElementById('modalEscola');
    const form = document.getElementById('formEscola');
    const titulo = document.getElementById('modalEscolaTitulo');
    
    if (escola) {
        titulo.textContent = 'Editar Escola';
        document.getElementById('escola_id').value = escola.id;
        document.getElementById('escola_nome').value = escola.escola_nome;
        document.getElementById('db_host').value = escola.db_host;
        document.getElementById('db_port').value = escola.db_port;
        document.getElementById('db_name').value = escola.db_name;
        document.getElementById('db_user').value = escola.db_user;
        document.getElementById('db_pass').value = escola.db_pass;
        document.getElementById('escola_ativo').checked = escola.ativo == 1;
        
        // Campos SSH
        if (document.getElementById('ssh_host')) {
            document.getElementById('ssh_host').value = escola.ssh_host || '';
            document.getElementById('ssh_port').value = escola.ssh_port || 22;
            document.getElementById('ssh_user').value = escola.ssh_user || '';
            document.getElementById('ssh_pass').value = escola.ssh_pass || '';
            document.getElementById('ssh_path').value = escola.ssh_path || '';
        }
    } else {
        titulo.textContent = 'Adicionar Escola';
        form.reset();
        document.getElementById('escola_id').value = '';
        document.getElementById('escola_ativo').checked = true;
        if (document.getElementById('ssh_port')) {
            document.getElementById('ssh_port').value = 22;
        }
    }
    
    modal.classList.remove('hidden');
}

function fecharModalEscola() {
    document.getElementById('modalEscola').classList.add('hidden');
    escolaEditando = null;
}

function editarEscola(escola) {
    abrirModalEscola(escola);
}

function deletarEscola(id) {
    if (!confirm('Tem certeza que deseja deletar esta configuração? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('_token', '<?= htmlspecialchars($csrf_token) ?>');
    formData.append('id', id);
    
    fetch('<?= URL ?>/admin/dev/migrations/escola/deletar', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro de conexão');
    });
}

function executarMigration(migrationFile, statusPorEscolaJson) {
    const statusPorEscola = JSON.parse(statusPorEscolaJson);
    
    // Encontrar o select da migration pelo atributo data
    const select = document.querySelector(`select[data-migration-file="${migrationFile}"]`);
    
    if (!select || !select.value) {
        alert('Por favor, selecione uma escola primeiro');
        return;
    }
    
    const escolaId = parseInt(select.value);
    const status = statusPorEscola[escolaId];
    
    if (status && status.status === 'sucesso') {
        if (!confirm('Esta migration já foi executada com sucesso para esta escola. Deseja executar novamente?')) {
            return;
        }
    }
    
    if (!confirm(`Tem certeza que deseja executar a migration "${migrationFile}" na escola selecionada?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('_token', '<?= htmlspecialchars($csrf_token) ?>');
    formData.append('escola_id', escolaId);
    formData.append('migration_file', migrationFile);
    
    // Mostrar loading
    const button = select.nextElementSibling;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Executando...';
    
    fetch('<?= URL ?>/admin/dev/migrations/executar', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.textContent = originalText;
        
        if (data.success) {
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        button.disabled = false;
        button.textContent = originalText;
        alert('❌ Erro de conexão');
    });
}

// Submit do formulário de escola
document.getElementById('formEscola').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('<?= URL ?>/admin/dev/migrations/escola/salvar', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            fecharModalEscola();
            window.location.reload();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro de conexão');
    });
});

// Fechar modal ao clicar fora
document.getElementById('modalEscola').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalEscola();
    }
});
</script>

