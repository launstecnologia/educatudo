<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Redação ✍️</h1>
            <p class="text-gray-600 mt-2">Escolha uma das opções abaixo para começar sua redação!</p>
        </div>
        <div>
            <button onclick="window.location.href='<?= URL ?>/redacoes/historico'"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                <i class="fas fa-history mr-2"></i>Histórico
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
</div>

<!-- Opções de Redação -->
<?php
if (!class_exists('CreditosModuleRegistry', false)) {
    require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
}
$tudicoinsTemaIa = \CreditosModuleRegistry::acaoIaDisponivel('redacao_gerar_tema_aluno');
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <?php if ($tudicoinsTemaIa): ?>
    <!-- Opção 1: Tema Gerado pela IA -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 hover:shadow-xl transition-shadow">
        <div class="p-6">
            <div class="text-center mb-4">
                <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Tema Personalizado</h3>
                <p class="text-gray-600 text-sm">Peça um tema específico e a IA gerará uma proposta de redação</p>
            </div>

            <div class="space-y-3 mb-6">
                <div class="flex items-center text-sm text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Escolha o assunto (ex: tecnologia e sociedade)
                </div>
                <div class="flex items-center text-sm text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Tema gerado automaticamente
                </div>
                <div class="flex items-center text-sm text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Correção automática ENEM
                </div>
            </div>

            <button onclick="openAIGeneratedThemeModal()" 
                    class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-3 rounded-lg hover:from-blue-600 hover:to-purple-700 transition-all font-semibold">
                🎯 Gerar Tema Personalizado
            </button>
        </div>
    </div>
    <?php endif; ?>
            
    <!-- Opção 2: Nova Redação -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 hover:shadow-xl transition-shadow">
        <div class="p-6">
            <div class="text-center mb-4">
                <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Nova Redação</h3>
                <p class="text-gray-600 text-sm">Escreva livremente ou transcreva de uma imagem</p>
            </div>
            
            <div class="space-y-3 mb-6">
                <div class="flex items-center text-sm text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Escrita livre ou transcrição de imagem
                </div>
                <div class="flex items-center text-sm text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Escolha seu próprio tema
                </div>
                <div class="flex items-center text-sm text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Correção automática ENEM
                </div>
            </div>

            <button onclick="openNovaRedacaoModal()" 
                    class="w-full bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-4 py-3 rounded-lg hover:from-blue-600 hover:to-indigo-700 transition-all font-semibold">
                ✍️ Nova Redação
            </button>
        </div>
    </div>

    <!-- Rascunhos - ocupa as 2 colunas -->
    <?php 
    ?>
    <?php if (!empty($rascunhos) && is_array($rascunhos) && count($rascunhos) > 0): ?>
    <div class="md:col-span-2 bg-white rounded-xl shadow-lg border border-gray-200 mb-6">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">📝 Rascunhos</h2>
            <p class="text-sm text-gray-600 mt-1">Continue suas redações de onde parou</p>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php foreach ($rascunhos as $rascunho): ?>
                    <div class="border border-blue-200 rounded-lg p-4 hover:shadow-md transition-shadow bg-blue-50" data-rascunho-id="<?= $rascunho['id'] ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($rascunho['titulo']) ?></h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?php if ($rascunho['tema_titulo']): ?>
                                        Tema: <?= htmlspecialchars($rascunho['tema_titulo']) ?>
                                    <?php elseif ($rascunho['tema_texto']): ?>
                                        <?php 
                                        // Tentar decodificar JSON se for tema gerado pela IA
                                        $tema_decodificado = json_decode($rascunho['tema_texto'], true);
                                        if ($tema_decodificado && is_array($tema_decodificado) && isset($tema_decodificado['titulo'])) {
                                            // É JSON válido - tema gerado pela IA
                                            echo 'Tema: ' . htmlspecialchars($tema_decodificado['titulo']);
                                        } else {
                                            // É texto simples - redação livre
                                            echo 'Tema: ' . htmlspecialchars($rascunho['tema_texto']);
                                        }
                                        ?>
                                    <?php else: ?>
                                        Rascunho
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Última atualização: <?= date('d/m/Y H:i', strtotime($rascunho['updated_at'] ?? $rascunho['created_at'])) ?>
                                </p>
                                <p class="text-xs text-blue-600 mt-1">
                                    <?php 
                                    $wordCount = $rascunho['conteudo'] ? str_word_count($rascunho['conteudo']) : 0;
                                    echo $wordCount . ' palavras';
                                    ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="<?= URL ?>/redacoes/rascunho/<?= $rascunho['id'] ?>" 
                                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Continuar
                                </a>
                                <button onclick="excluirRascunho(<?= $rascunho['id'] ?>)" 
                                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                                    Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Redações - ocupa as 2 colunas -->
    <div class="md:col-span-2 bg-white rounded-xl shadow-lg border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Suas Redações</h2>
            <p class="text-sm text-gray-600 mt-1">Todas as suas redações, corrigidas e em correção</p>
        </div>
        <div class="p-6">
            <?php if (empty($redacoes_aluno)): ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <p class="text-gray-500">Nenhuma redação encontrada</p>
                    <p class="text-sm text-gray-400">Escolha uma das opções acima para começar!</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($redacoes_aluno as $redacao): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow" data-redacao-id="<?= $redacao['id'] ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($redacao['titulo']) ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <?php if ($redacao['tema_titulo']): ?>
                                            Tema: <?= htmlspecialchars($redacao['tema_titulo']) ?>
                                        <?php else: ?>
                                            <?= ucfirst($redacao['tipo'] ?? 'Redação') ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?= date('d/m/Y H:i', strtotime($redacao['created_at'])) ?>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($redacao['nota_final'] || $redacao['nota']): ?>
                                        <?php $nota = $redacao['nota_final'] ?? $redacao['nota'] ?? 0; ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-sm font-medium">
                                            <?= $nota ?> pontos
                                        </span>
                                        <a href="<?= URL ?>/redacoes/<?= $redacao['id'] ?>" 
                                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm font-medium transition-colors">
                                            Ver Detalhes
                                        </a>
                                    <?php elseif ($redacao['corrigida_em']): ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-sm font-medium">
                                            Corrigida
                                        </span>
                                        <a href="<?= URL ?>/redacoes/<?= $redacao['id'] ?>" 
                                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm font-medium transition-colors">
                                            Ver Detalhes
                                        </a>
                                    <?php else: ?>
                                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-sm font-medium">
                                            Aguardando correção
                                        </span>
                                        <a href="<?= URL ?>/redacoes/<?= $redacao['id'] ?>" 
                                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm font-medium transition-colors">
                                            Ver Detalhes
                                        </a>
                                    <?php endif; ?>
                                    <button onclick="ocultarRedacao(<?= $redacao['id'] ?>)" 
                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-medium transition-colors">
                                        Remover
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <script>
        // Função para excluir rascunho (exclusão real do banco)
        async function excluirRascunho(id) {
            if (!confirm('Tem certeza que deseja excluir este rascunho? Esta ação não pode ser desfeita e o rascunho será permanentemente removido.')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('_token', <?= json_encode($_SESSION['csrf_token'] ?? '') ?>);
                
                const response = await fetch(`<?= URL ?>/redacoes/rascunho/${id}/excluir`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const rascunhoElement = document.querySelector(`[data-rascunho-id="${id}"]`);
                    if (rascunhoElement) {
                        rascunhoElement.style.transition = 'opacity 0.3s ease-out';
                        rascunhoElement.style.opacity = '0';
                        setTimeout(() => {
                            rascunhoElement.remove();
                        }, 300);
                    }
                    showNotification('Rascunho excluído permanentemente!', 'success');
                } else {
                    showNotification('Erro ao excluir rascunho: ' + (data.error || 'Erro desconhecido'), 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showNotification('Erro de conexão. Tente novamente.', 'error');
            }
        }

        // Função para ocultar redação
        async function ocultarRedacao(id) {
            if (!confirm('Tem certeza que deseja remover esta redação da sua lista? Ela não será excluída do sistema.')) {
                return;
            }
            
            try {
                const response = await fetch(`<?= URL ?>/redacoes/${id}/ocultar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remover o elemento da lista
                    const redacaoElement = document.querySelector(`[data-redacao-id="${id}"]`);
                    if (redacaoElement) {
                        redacaoElement.style.transition = 'opacity 0.3s ease-out';
                        redacaoElement.style.opacity = '0';
                        setTimeout(() => {
                            redacaoElement.remove();
                        }, 300);
                    }
                    
                    showNotification('Redação removida com sucesso!', 'success');
                } else {
                    showNotification('Erro ao remover redação: ' + (data.error || 'Erro desconhecido'), 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showNotification('Erro de conexão. Tente novamente.', 'error');
            }
        }
        
        // Função para mostrar notificações
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                type === 'warning' ? 'bg-yellow-500 text-white' :
                'bg-blue-500 text-white'
            }`;
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Modal 1: Tema Gerado pela IA
        function openAIGeneratedThemeModal() {
            console.log('Abrindo modal AI');
            const modal = document.getElementById('aiThemeModal');
            if (modal) {
                modal.classList.remove('hidden');
            } else {
                console.error('Modal aiThemeModal não encontrado!');
            }
        }

        function closeAITThemeModal() {
            console.log('Fechando modal AI');
            const modal = document.getElementById('aiThemeModal');
            if (modal) {
                modal.classList.add('hidden');
                const form = document.getElementById('aiThemeForm');
                if (form) {
                    form.reset();
                }
            }
        }

        // Modal Nova Redação
        function openNovaRedacaoModal() {
            console.log('Abrindo modal Nova Redação');
            const modal = document.getElementById('novaRedacaoModal');
            if (modal) {
                modal.classList.remove('hidden');
            } else {
                console.error('Modal novaRedacaoModal não encontrado!');
            }
        }

        function closeNovaRedacaoModal() {
            console.log('Fechando modal Nova Redação');
            const modal = document.getElementById('novaRedacaoModal');
            if (modal) {
                modal.classList.add('hidden');
            } else {
                console.error('Modal novaRedacaoModal não encontrado!');
            }
        }

        // Modal 2: Redação Livre
        function openFreeEssayModal() {
            console.log('Abrindo modal Redação Livre');
            const modal = document.getElementById('freeEssayModal');
            if (modal) {
                modal.classList.remove('hidden');
            } else {
                console.error('Modal freeEssayModal não encontrado!');
            }
        }

        function closeFreeEssayModal() {
            console.log('Fechando modal Redação Livre');
            const modal = document.getElementById('freeEssayModal');
            if (modal) {
                modal.classList.add('hidden');
                const form = document.getElementById('freeEssayForm');
                if (form) {
                    form.reset();
                }
            }
        }

        // Modal 3: Transcrição de Imagem
        function openImageTranscriptionModal() {
            console.log('Abrindo modal Transcrição');
            const modal = document.getElementById('imageTranscriptionModal');
            if (modal) {
                modal.classList.remove('hidden');
            } else {
                console.error('Modal imageTranscriptionModal não encontrado!');
            }
        }

        function closeImageTranscriptionModal() {
            console.log('Fechando modal Transcrição');
            const modal = document.getElementById('imageTranscriptionModal');
            if (modal) {
                modal.classList.add('hidden');
                const form = document.getElementById('imageTranscriptionForm');
                if (form) {
                    form.reset();
                }
                const preview = document.getElementById('imagePreview');
                const uploadArea = document.getElementById('uploadArea');
                if (preview) preview.classList.add('hidden');
                if (uploadArea) uploadArea.classList.remove('hidden');
            }
        }

        // Função para gerar tema
        function gerarTema() {
            const themeRequest = document.getElementById('themeRequest').value;

            if (!themeRequest.trim()) {
                alert('Por favor, descreva o tema desejado!');
                return;
            }

            // Mostrar modal de loading
            showThemeGenerationModal();

            // Criar formulário para envio
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= URL ?>/redacoes/gerar-tema';

            // Adicionar campos
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
            form.appendChild(csrfToken);

            const themeInput = document.createElement('input');
            themeInput.type = 'hidden';
            themeInput.name = 'theme_request';
            themeInput.value = themeRequest;
            form.appendChild(themeInput);

            // Adicionar ao DOM e submeter
            document.body.appendChild(form);
            form.submit();
        }

        // Funções para modal de geração de tema
        function showThemeGenerationModal() {
            const modal = document.getElementById('themeGenerationModal');
            if (modal) {
                modal.classList.remove('hidden');
            }
        }

        function hideThemeGenerationModal() {
            const modal = document.getElementById('themeGenerationModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM carregado, inicializando event listeners');

            // Formulário agora faz submit tradicional, não precisa de JavaScript

            // Formulário de Redação Livre
            const freeEssayForm = document.getElementById('freeEssayForm');
            if (freeEssayForm) {
                freeEssayForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);

                    fetch('<?= URL ?>/redacoes/criar', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                showNotification('Redação enviada com sucesso! Aguarde a correção automática.', 'success');
                                closeFreeEssayModal();
                                location.reload();
                            } else {
                                showNotification(data.error || 'Erro ao enviar redação', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            showNotification('Erro ao enviar redação: ' + error.message, 'error');
                        });
                });
            }

            // Formulário de Transcrição de Imagem
            const imageTranscriptionForm = document.getElementById('imageTranscriptionForm');
            if (imageTranscriptionForm) {
                imageTranscriptionForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);

                    fetch('<?= URL ?>/redacoes/transcrever-imagem', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                showNotification('Redação enviada com sucesso! Aguarde a correção automática.', 'success');
                                closeImageTranscriptionModal();
                                location.reload();
                            } else {
                                showNotification(data.error || 'Erro ao enviar redação', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            showNotification('Erro ao enviar redação: ' + error.message, 'error');
                        });
                });
            }

            // Upload de imagem
            const imageUpload = document.getElementById('imageUpload');
            if (imageUpload) {
                imageUpload.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const preview = document.getElementById('imagePreview');
                            const uploadArea = document.getElementById('uploadArea');
                            if (preview) {
                                preview.src = e.target.result;
                                preview.classList.remove('hidden');
                            }
                            if (uploadArea) {
                                uploadArea.classList.add('hidden');
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>

    <!-- Modal 1: Tema Gerado pela IA -->
    <div id="aiThemeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden" style="z-index: 9999;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Tema Personalizado</h3>
                                <p class="text-blue-100 text-sm">Descreva o tema que você gostaria de escrever</p>
                            </div>
                        </div>
                        <button onclick="closeAITThemeModal()" class="text-white hover:text-gray-200 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6">
                    <form action="<?= URL ?>/redacoes/gerar-tema" method="POST">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                        <div class="mb-4">
                            <label for="themeRequest" class="block text-sm font-medium text-gray-700 mb-2">
                                Descreva o tema desejado
                            </label>
                            <textarea id="themeRequest" name="theme_request" rows="4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Ex: tecnologia e sociedade, meio ambiente, educação no Brasil..."
                                required></textarea>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="closeAITThemeModal()"
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancelar
                            </button>
                            <button type="button" onclick="gerarTema()"
                                class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:from-blue-600 hover:to-purple-700 transition-all font-semibold">
                                🎯 Gerar Tema
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 2: Redação Livre -->
    <div id="freeEssayModal" class="fixed inset-0 bg-black bg-opacity-50 hidden" style="z-index: 9999;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-500 to-teal-600 p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Redação Livre</h3>
                                <p class="text-green-100 text-sm">Escreva sobre qualquer tema que desejar</p>
                            </div>
                        </div>
                        <button onclick="closeFreeEssayModal()" class="text-white hover:text-gray-200 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6">
                    <form id="freeEssayForm">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="tipo" value="livre">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="freeEssayTitle" class="block text-sm font-medium text-gray-700 mb-2">
                                    Título da Redação
                                </label>
                                <input type="text" id="freeEssayTitle" name="titulo"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    placeholder="Digite o título da sua redação"
                                    required>
                            </div>

                            <div>
                                <label for="freeEssayTheme" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tema (opcional)
                                </label>
                                <input type="text" id="freeEssayTheme" name="tema_livre"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    placeholder="Ex: Meio ambiente, Tecnologia, Educação...">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="freeEssayContent" class="block text-sm font-medium text-gray-700 mb-2">
                                Conteúdo da Redação
                            </label>
                            <textarea id="freeEssayContent" name="conteudo" rows="12"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="Escreva sua redação aqui..."
                                required></textarea>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" onclick="closeFreeEssayModal()"
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="px-6 py-2 bg-gradient-to-r from-green-500 to-teal-600 text-white rounded-lg hover:from-green-600 hover:to-teal-700 transition-all font-semibold">
                                ✍️ Enviar Redação
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 3: Transcrição de Imagem -->
    <div id="imageTranscriptionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden" style="z-index: 9999;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-orange-500 to-red-600 p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Transcrição de Imagem</h3>
                                <p class="text-orange-100 text-sm">Faça upload de uma imagem e transcreva o texto</p>
                            </div>
                        </div>
                        <button onclick="closeImageTranscriptionModal()" class="text-white hover:text-gray-200 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6">
                    <form id="imageTranscriptionForm">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="tipo" value="transcricao">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="imageEssayTitle" class="block text-sm font-medium text-gray-700 mb-2">
                                    Título da Redação
                                </label>
                                <input type="text" id="imageEssayTitle" name="titulo"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    placeholder="Digite o título da sua redação"
                                    required>
                            </div>

                            <div>
                                <label for="imageUpload" class="block text-sm font-medium text-gray-700 mb-2">
                                    Imagem para Transcrever
                                </label>
                                <div id="uploadArea" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-orange-500 transition-colors cursor-pointer">
                                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    <p class="text-gray-600">Clique para fazer upload da imagem</p>
                                    <p class="text-sm text-gray-500">JPG, PNG (máx. 10MB)</p>
                                </div>
                                <input type="file" id="imageUpload" name="imagem" accept="image/*" class="hidden" required>
                                <img id="imagePreview" class="hidden w-full h-48 object-cover rounded-lg mt-2">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="imageEssayContent" class="block text-sm font-medium text-gray-700 mb-2">
                                Conteúdo da Redação
                            </label>
                            <textarea id="imageEssayContent" name="conteudo" rows="8"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                placeholder="Escreva sua redação aqui..."
                                required></textarea>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" onclick="closeImageTranscriptionModal()"
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="px-6 py-2 bg-gradient-to-r from-orange-500 to-red-600 text-white rounded-lg hover:from-orange-600 hover:to-red-700 transition-all font-semibold">
                                📷 Enviar Redação
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nova Redação -->
    <div id="novaRedacaoModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Nova Redação</h3>
                        <p class="text-gray-600 text-sm">Escolha como deseja criar sua redação</p>
                    </div>

                    <div class="space-y-4">
                        <button type="button" onclick="window.location.href='<?= URL ?>/redacoes/escrever-livre'"
                            class="w-full bg-gradient-to-r from-green-500 to-teal-600 text-white px-4 py-3 rounded-lg hover:from-green-600 hover:to-teal-700 transition-all font-semibold flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                            ✍️ Redação Livre
                        </button>

                        <button type="button" onclick="window.location.href='<?= URL ?>/redacoes/transcrever'"
                            class="w-full bg-gradient-to-r from-orange-500 to-red-600 text-white px-4 py-3 rounded-lg hover:from-orange-600 hover:to-red-700 transition-all font-semibold flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            📷 Transcrição de Imagem
                        </button>
                    </div>

                    <div class="mt-6 text-center">
                        <button onclick="closeNovaRedacaoModal()"
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Loading para Geração de Tema -->
    <div id="themeGenerationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden" style="z-index: 10000;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
                <div class="p-8 text-center">
                    <div class="w-20 h-20 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-6">
                        <div class="animate-spin rounded-full h-12 w-12 border-4 border-white border-t-transparent"></div>
                    </div>

                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Gerando Tema</h3>
                    <p class="text-gray-600 mb-4">A IA está criando um tema personalizado para sua redação...</p>

                    <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                        <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-2 rounded-full animate-pulse" style="width: 100%"></div>
                    </div>

                    <p class="text-sm text-gray-500">Aguarde enquanto processamos sua solicitação</p>
                </div>
            </div>
        </div>
    </div>