<?php
/**
 * View: Seleção de Avatar do Aluno
 */
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    👤 Meu Perfil
                </h1>
                <p class="text-gray-600">
                    Gerencie seu avatar e visualize seus dados de onboarding
                </p>
            </div>
            
            <div class="text-right">
                <div class="text-sm text-gray-600">
                    Aluno: <?= htmlspecialchars($aluno['nome'] ?? '') ?>
                </div>
                <div class="text-sm text-gray-600">
                    Turma: <?= htmlspecialchars($aluno['turma_nome'] ?? '') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mensagens de Sucesso/Erro -->
    <?php if (isset($_GET['success'])): ?>
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        ✅ <?= htmlspecialchars($_GET['success']) ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        ❌ <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Preview do Avatar e Dados de Onboarding -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Avatar -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">👤 Seu Avatar</h2>
                
                <div class="text-center">
                    <div class="w-48 h-48 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center overflow-hidden border-4 border-purple-200">
                        <?php if ($avatar && !empty($avatar['avatar_url'])): ?>
                            <img id="avatar-preview" src="<?= htmlspecialchars($avatar['avatar_url']) ?>" alt="Avatar" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div id="avatar-placeholder" class="text-gray-400 text-6xl">👤</div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($avatar && !empty($avatar['descricao_objetivos'])): ?>
                    <div class="mt-4 p-3 bg-purple-50 rounded-lg">
                        <p class="text-sm text-gray-700 font-medium mb-1">Meus Objetivos:</p>
                        <p class="text-sm text-gray-600">"<?= htmlspecialchars($avatar['descricao_objetivos']) ?>"</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dados de Onboarding -->
            <?php if (!empty($onboarding) && $onboarding['completado']): ?>
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">📋 Meu Perfil de Onboarding</h2>
                
                <div class="space-y-4">
                    <?php if (!empty($onboarding['meu_sonho'])): ?>
                    <div class="bg-yellow-50 rounded-lg p-3 border-l-4 border-yellow-400">
                        <div class="flex items-center mb-1">
                            <span class="text-xl mr-2">☀️</span>
                            <span class="text-sm font-semibold text-gray-800">Meu Sonho</span>
                        </div>
                        <p class="text-sm text-gray-700 italic">"<?= htmlspecialchars($onboarding['meu_sonho']) ?>"</p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($onboarding['objetivo_principal'])): ?>
                    <div class="bg-pink-50 rounded-lg p-3 border-l-4 border-pink-400">
                        <div class="flex items-center mb-1">
                            <span class="text-xl mr-2">🎯</span>
                            <span class="text-sm font-semibold text-gray-800">Objetivo Principal</span>
                        </div>
                        <p class="text-sm text-gray-700"><?= htmlspecialchars($onboarding['objetivo_principal']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($onboarding['nivel_comprometimento'])): ?>
                    <div class="bg-orange-50 rounded-lg p-3 border-l-4 border-orange-400">
                        <div class="flex items-center mb-1">
                            <span class="text-xl mr-2">💪</span>
                            <span class="text-sm font-semibold text-gray-800">Comprometimento</span>
                        </div>
                        <p class="text-sm text-gray-700"><?= htmlspecialchars($onboarding['nivel_comprometimento']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($onboarding['tempo_estudo_dia'])): ?>
                    <div class="bg-green-50 rounded-lg p-3 border-l-4 border-green-400">
                        <div class="flex items-center mb-1">
                            <span class="text-xl mr-2">⏰</span>
                            <span class="text-sm font-semibold text-gray-800">Tempo de Estudo</span>
                        </div>
                        <p class="text-sm text-gray-700"><?= htmlspecialchars($onboarding['tempo_estudo_dia']) ?>/dia</p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($onboarding['pontos_fortes'])): ?>
                    <div class="bg-green-50 rounded-lg p-3 border-l-4 border-green-400">
                        <div class="flex items-center mb-1">
                            <span class="text-xl mr-2">✅</span>
                            <span class="text-sm font-semibold text-gray-800">Pontos Fortes</span>
                        </div>
                        <p class="text-sm text-gray-700"><?= htmlspecialchars($onboarding['pontos_fortes']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($onboarding['pontos_dificuldade'])): ?>
                    <div class="bg-red-50 rounded-lg p-3 border-l-4 border-red-400">
                        <div class="flex items-center mb-1">
                            <span class="text-xl mr-2">⚠️</span>
                            <span class="text-sm font-semibold text-gray-800">Dificuldades</span>
                        </div>
                        <p class="text-sm text-gray-700"><?= htmlspecialchars($onboarding['pontos_dificuldade']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($onboarding['estilo_aprendizado'])): ?>
                    <div class="bg-blue-50 rounded-lg p-3 border-l-4 border-blue-400">
                        <div class="flex items-center mb-1">
                            <span class="text-xl mr-2">📚</span>
                            <span class="text-sm font-semibold text-gray-800">Estilo de Aprendizado</span>
                        </div>
                        <p class="text-sm text-gray-700"><?= htmlspecialchars($onboarding['estilo_aprendizado']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200">
                    <a href="<?= URL ?>/perfil" 
                       class="text-sm text-purple-600 hover:text-purple-700 font-medium">
                        Ver perfil completo →
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-sm text-yellow-800">
                        Complete seu <a href="<?= URL ?>/dashboard" class="underline font-medium">perfil de onboarding</a> para ver suas informações aqui.
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Seleção de Avatar -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md border border-gray-200 mb-6 p-6">
                <form method="POST" action="<?= URL ?>/avatar/selecionar" id="form-avatar-selecionado">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <?php
                    $avatarSelecionadoAtual = '';
                    if (!empty($avatar['avatar_url'])) {
                        $candidato = basename(parse_url((string) $avatar['avatar_url'], PHP_URL_PATH) ?: (string) $avatar['avatar_url']);
                        if (in_array($candidato, $avatars_predefinidos ?? [], true)) {
                            $avatarSelecionadoAtual = $candidato;
                        }
                    }
                    ?>
                    <input type="hidden" name="avatar_selecionado" id="avatar_selecionado" value="<?= htmlspecialchars($avatarSelecionadoAtual) ?>">

                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Escolha um avatar:</h3>

                    <?php if (empty($avatars_predefinidos)): ?>
                        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Nenhum avatar disponível no momento. Peça ao suporte para adicionar imagens em
                            <code class="text-xs">public/assets/avatars/</code>.
                        </div>
                    <?php else: ?>
                    <div class="grid grid-cols-5 gap-4 mb-6">
                        <?php foreach ($avatars_predefinidos as $avatarFile): ?>
                            <?php
                            $avatarUrl = URL . '/public/assets/avatars/' . rawurlencode($avatarFile);
                            $avatarUrlAtual = (string) ($avatar['avatar_url'] ?? '');
                            $isSelected = $avatarUrlAtual !== '' && (
                                basename(parse_url($avatarUrlAtual, PHP_URL_PATH) ?: $avatarUrlAtual) === $avatarFile
                                || substr($avatarUrlAtual, -strlen($avatarFile)) === $avatarFile
                            );
                            ?>
                            <div class="avatar-option cursor-pointer <?= $isSelected ? 'ring-4 ring-purple-500' : 'hover:ring-2 hover:ring-purple-300' ?> rounded-lg p-2 transition-all"
                                 data-avatar="<?= htmlspecialchars($avatarFile) ?>"
                                 onclick="selecionarAvatar('<?= htmlspecialchars($avatarFile, ENT_QUOTES) ?>')">
                                <img src="<?= htmlspecialchars($avatarUrl) ?>"
                                     alt="Avatar"
                                     class="w-full h-auto rounded-lg">
                                <?php if ($isSelected): ?>
                                    <div class="text-center mt-2">
                                        <span class="text-xs text-purple-600 font-semibold">✓ Selecionado</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label for="descricao_objetivos" class="block text-sm font-medium text-gray-700 mb-2">
                            O que você quer fazer quando terminar os estudos? (Opcional)
                        </label>
                        <textarea id="descricao_objetivos"
                                  name="descricao_objetivos"
                                  rows="3"
                                  placeholder="Ex: Quero ser médico e ajudar pessoas, ou Engenheiro e criar soluções inovadoras..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"><?= htmlspecialchars($avatar['descricao_objetivos'] ?? '') ?></textarea>
                    </div>

                    <button type="submit"
                            class="w-full px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-semibold">
                        💾 Salvar Avatar Selecionado
                    </button>
                </form>
            </div>

            <!-- Botão Voltar -->
            <div class="text-center mt-4">
                <a href="<?= URL ?>/dashboard" 
                   class="inline-block px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    ← Voltar ao Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function selecionarAvatar(avatarFile) {
    // Remover seleção anterior
    document.querySelectorAll('.avatar-option').forEach(option => {
        option.classList.remove('ring-4', 'ring-purple-500');
        option.classList.add('hover:ring-2', 'hover:ring-purple-300');
        const selectedText = option.querySelector('.text-xs');
        if (selectedText) {
            selectedText.remove();
        }
    });
    
    // Selecionar novo avatar
    const selectedOption = document.querySelector(`[data-avatar="${avatarFile}"]`);
    if (selectedOption) {
        selectedOption.classList.add('ring-4', 'ring-purple-500');
        selectedOption.classList.remove('hover:ring-2', 'hover:ring-purple-300');
        
        // Adicionar texto "Selecionado"
        const selectedText = document.createElement('div');
        selectedText.className = 'text-center mt-2';
        selectedText.innerHTML = '<span class="text-xs text-purple-600 font-semibold">✓ Selecionado</span>';
        selectedOption.appendChild(selectedText);
        
        // Atualizar preview
        const avatarUrl = '<?= URL ?>/public/assets/avatars/' + encodeURIComponent(avatarFile);
        const preview = document.getElementById('avatar-preview');
        const placeholder = document.getElementById('avatar-placeholder');
        
        if (preview) {
            preview.src = avatarUrl;
            preview.style.display = 'block';
        } else if (placeholder) {
            placeholder.innerHTML = '<img id="avatar-preview" src="' + avatarUrl + '" alt="Avatar" class="w-full h-full object-cover">';
        }
        
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    }
    
    // Atualizar campo hidden
    document.getElementById('avatar_selecionado').value = avatarFile;
}
</script>