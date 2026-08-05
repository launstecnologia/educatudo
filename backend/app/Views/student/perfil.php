<?php
/**
 * View: Perfil do Aluno com Onboarding
 */
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    📋 Meu Perfil de Onboarding
                </h1>
                <p class="text-gray-600 mt-2">
                    Visualize e edite suas informações de perfil
                </p>
            </div>
            <button onclick="editarPerfil()" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Editar Perfil
            </button>
        </div>
    </div>

    <?php if (empty($onboarding) || !$onboarding['completado']): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
        <div class="flex items-center">
            <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <h3 class="font-semibold text-yellow-800">Perfil não completado</h3>
                <p class="text-sm text-yellow-700">Complete seu perfil de onboarding para personalizar sua experiência de aprendizado.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Grid de Informações -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Meu Sonho -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-400">
            <div class="flex items-center mb-4">
                <span class="text-3xl mr-3">☀️</span>
                <h3 class="text-lg font-semibold text-gray-900">Meu Sonho</h3>
            </div>
            <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                <p class="text-gray-700 italic">"<?= htmlspecialchars($onboarding['meu_sonho'] ?? 'Não informado') ?>"</p>
            </div>
        </div>

        <!-- Objetivo Principal -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-pink-400">
            <div class="flex items-center mb-4">
                <span class="text-3xl mr-3">🎯</span>
                <h3 class="text-lg font-semibold text-gray-900">Objetivo Principal</h3>
            </div>
            <div class="bg-pink-50 rounded-lg p-4 border border-pink-200 flex items-center">
                <?php if (!empty($onboarding['objetivo_principal'])): ?>
                    <span class="text-2xl mr-3">🏛️</span>
                    <p class="text-gray-700 font-medium"><?= htmlspecialchars($onboarding['objetivo_principal']) ?></p>
                <?php else: ?>
                    <p class="text-gray-500">Não informado</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nível de Comprometimento -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-400">
            <div class="flex items-center mb-4">
                <span class="text-3xl mr-3">💪</span>
                <h3 class="text-lg font-semibold text-gray-900">Nível de Comprometimento</h3>
            </div>
            <div class="bg-orange-50 rounded-lg p-4 border border-orange-200 flex items-center">
                <?php if (!empty($onboarding['nivel_comprometimento'])): ?>
                    <span class="text-2xl mr-3">💪</span>
                    <p class="text-gray-700 font-medium"><?= htmlspecialchars($onboarding['nivel_comprometimento']) ?></p>
                <?php else: ?>
                    <p class="text-gray-500">Não informado</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pontos de Dificuldade -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-400">
            <div class="flex items-center mb-4">
                <span class="text-3xl mr-3">⚠️</span>
                <h3 class="text-lg font-semibold text-gray-900">Pontos de Dificuldade</h3>
            </div>
            <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                <p class="text-gray-700"><?= htmlspecialchars($onboarding['pontos_dificuldade'] ?? 'Não informado') ?></p>
            </div>
        </div>

        <!-- Tempo de Estudo por Dia -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-400">
            <div class="flex items-center mb-4">
                <span class="text-3xl mr-3">⏰</span>
                <h3 class="text-lg font-semibold text-gray-900">Tempo de Estudo por Dia</h3>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <p class="text-gray-700 font-medium text-lg"><?= htmlspecialchars($onboarding['tempo_estudo_dia'] ?? 'Não informado') ?></p>
            </div>
        </div>

        <!-- Pontos Fortes -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-400">
            <div class="flex items-center mb-4">
                <span class="text-3xl mr-3">✅</span>
                <h3 class="text-lg font-semibold text-gray-900">Pontos Fortes</h3>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <p class="text-gray-700"><?= htmlspecialchars($onboarding['pontos_fortes'] ?? 'Não informado') ?></p>
            </div>
        </div>

        <!-- Estilo de Aprendizado -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-400 md:col-span-2">
            <div class="flex items-center mb-4">
                <span class="text-3xl mr-3">📚</span>
                <h3 class="text-lg font-semibold text-gray-900">Estilo de Aprendizado</h3>
            </div>
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200 flex items-center">
                <?php if (!empty($onboarding['estilo_aprendizado'])): ?>
                    <span class="text-2xl mr-3">📖</span>
                    <p class="text-gray-700 font-medium"><?= htmlspecialchars($onboarding['estilo_aprendizado']) ?></p>
                <?php else: ?>
                    <p class="text-gray-500">Não informado</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Edição -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4 hidden" style="backdrop-filter: blur(2px);">
    <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[95vh] overflow-hidden flex flex-col mx-auto">
        <!-- Header do Modal -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold flex items-center">
                        <span class="mr-2">✏️</span>
                        Editar Perfil de Onboarding
                    </h2>
                    <p class="text-sm text-blue-100 mt-1">Atualize suas informações de perfil</p>
                </div>
                <button onclick="fecharModal()" 
                        class="text-white hover:bg-white/20 p-2 rounded-lg transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Conteúdo do Modal (Scrollável) -->
        <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
        <form id="editForm">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Meu Sonho -->
                <div class="bg-white rounded-xl p-5 border-2 border-yellow-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">☀️</span>
                        <span class="font-bold text-gray-800 text-lg">Meu Sonho</span>
                    </label>
                    <input type="text" 
                           name="meu_sonho" 
                           placeholder="Ex: sonhar"
                           class="w-full px-4 py-3 border-2 border-yellow-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 bg-white transition-all"
                           value="<?= htmlspecialchars($onboarding['meu_sonho'] ?? '') ?>">
                </div>
                
                <!-- Objetivo Principal -->
                <div class="bg-white rounded-xl p-5 border-2 border-pink-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">🎯</span>
                        <span class="font-bold text-gray-800 text-lg">Objetivo Principal</span>
                    </label>
                    <select name="objetivo_principal" 
                            class="w-full px-4 py-3 border-2 border-pink-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-pink-400 bg-white transition-all">
                        <option value="">Selecione...</option>
                        <option value="Concurso Público" <?= ($onboarding['objetivo_principal'] ?? '') === 'Concurso Público' ? 'selected' : '' ?>>Concurso Público</option>
                        <option value="Vestibular" <?= ($onboarding['objetivo_principal'] ?? '') === 'Vestibular' ? 'selected' : '' ?>>Vestibular</option>
                        <option value="ENEM" <?= ($onboarding['objetivo_principal'] ?? '') === 'ENEM' ? 'selected' : '' ?>>ENEM</option>
                        <option value="Melhorar Notas" <?= ($onboarding['objetivo_principal'] ?? '') === 'Melhorar Notas' ? 'selected' : '' ?>>Melhorar Notas</option>
                        <option value="Aprender Mais" <?= ($onboarding['objetivo_principal'] ?? '') === 'Aprender Mais' ? 'selected' : '' ?>>Aprender Mais</option>
                        <option value="Outro" <?= ($onboarding['objetivo_principal'] ?? '') === 'Outro' ? 'selected' : '' ?>>Outro</option>
                    </select>
                </div>
                
                <!-- Nível de Comprometimento -->
                <div class="bg-white rounded-xl p-5 border-2 border-orange-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">💪</span>
                        <span class="font-bold text-gray-800 text-lg">Nível de Comprometimento</span>
                    </label>
                    <select name="nivel_comprometimento" 
                            class="w-full px-4 py-3 border-2 border-orange-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 bg-white transition-all">
                        <option value="">Selecione...</option>
                        <option value="Dedicado" <?= ($onboarding['nivel_comprometimento'] ?? '') === 'Dedicado' ? 'selected' : '' ?>>Dedicado</option>
                        <option value="Moderado" <?= ($onboarding['nivel_comprometimento'] ?? '') === 'Moderado' ? 'selected' : '' ?>>Moderado</option>
                        <option value="Iniciante" <?= ($onboarding['nivel_comprometimento'] ?? '') === 'Iniciante' ? 'selected' : '' ?>>Iniciante</option>
                    </select>
                </div>
                
                <!-- Pontos de Dificuldade -->
                <div class="bg-white rounded-xl p-5 border-2 border-red-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">⚠️</span>
                        <span class="font-bold text-gray-800 text-lg">Pontos de Dificuldade</span>
                    </label>
                    <input type="text" 
                           name="pontos_dificuldade" 
                           placeholder="Ex: Redação, Matemática..."
                           class="w-full px-4 py-3 border-2 border-red-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-red-400 bg-white transition-all"
                           value="<?= htmlspecialchars($onboarding['pontos_dificuldade'] ?? '') ?>">
                </div>
                
                <!-- Tempo de Estudo por Dia -->
                <div class="bg-white rounded-xl p-5 border-2 border-green-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">⏰</span>
                        <span class="font-bold text-gray-800 text-lg">Tempo de Estudo por Dia</span>
                    </label>
                    <select name="tempo_estudo_dia" 
                            class="w-full px-4 py-3 border-2 border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-green-400 bg-white transition-all">
                        <option value="">Selecione...</option>
                        <option value="1 h" <?= ($onboarding['tempo_estudo_dia'] ?? '') === '1 h' ? 'selected' : '' ?>>1 hora</option>
                        <option value="2 h" <?= ($onboarding['tempo_estudo_dia'] ?? '') === '2 h' ? 'selected' : '' ?>>2 horas</option>
                        <option value="3 h" <?= ($onboarding['tempo_estudo_dia'] ?? '') === '3 h' ? 'selected' : '' ?>>3 horas</option>
                        <option value="4 h" <?= ($onboarding['tempo_estudo_dia'] ?? '') === '4 h' ? 'selected' : '' ?>>4 horas</option>
                        <option value="5+ h" <?= ($onboarding['tempo_estudo_dia'] ?? '') === '5+ h' ? 'selected' : '' ?>>5+ horas</option>
                    </select>
                </div>
                
                <!-- Pontos Fortes -->
                <div class="bg-white rounded-xl p-5 border-2 border-green-300 shadow-md hover:shadow-lg transition-shadow">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">✅</span>
                        <span class="font-bold text-gray-800 text-lg">Pontos Fortes</span>
                    </label>
                    <input type="text" 
                           name="pontos_fortes" 
                           placeholder="Ex: Humanas, Exatas..."
                           class="w-full px-4 py-3 border-2 border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-green-400 bg-white transition-all"
                           value="<?= htmlspecialchars($onboarding['pontos_fortes'] ?? '') ?>">
                </div>
                
                <!-- Estilo de Aprendizado -->
                <div class="bg-white rounded-xl p-5 border-2 border-blue-300 shadow-md hover:shadow-lg transition-shadow md:col-span-2">
                    <label class="flex items-center mb-3">
                        <span class="text-3xl mr-3">📚</span>
                        <span class="font-bold text-gray-800 text-lg">Estilo de Aprendizado</span>
                    </label>
                    <select name="estilo_aprendizado" 
                            class="w-full px-4 py-3 border-2 border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 bg-white transition-all">
                        <option value="">Selecione...</option>
                        <option value="Lendo (Textos/Resumos)" <?= ($onboarding['estilo_aprendizado'] ?? '') === 'Lendo (Textos/Resumos)' ? 'selected' : '' ?>>Lendo (Textos/Resumos)</option>
                        <option value="Assistindo (Vídeos/Aulas)" <?= ($onboarding['estilo_aprendizado'] ?? '') === 'Assistindo (Vídeos/Aulas)' ? 'selected' : '' ?>>Assistindo (Vídeos/Aulas)</option>
                        <option value="Praticando (Exercícios)" <?= ($onboarding['estilo_aprendizado'] ?? '') === 'Praticando (Exercícios)' ? 'selected' : '' ?>>Praticando (Exercícios)</option>
                        <option value="Ouvindo (Áudios/Podcasts)" <?= ($onboarding['estilo_aprendizado'] ?? '') === 'Ouvindo (Áudios/Podcasts)' ? 'selected' : '' ?>>Ouvindo (Áudios/Podcasts)</option>
                        <option value="Misto" <?= ($onboarding['estilo_aprendizado'] ?? '') === 'Misto' ? 'selected' : '' ?>>Misto</option>
                    </select>
                </div>
            </div>
        </form>
        </div>
        
        <!-- Footer do Modal -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
            <button type="button" 
                    onclick="fecharModal()"
                    class="px-6 py-2.5 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors font-semibold">
                Cancelar
            </button>
            <button type="submit" 
                    form="editForm"
                    class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all font-semibold shadow-md hover:shadow-lg flex items-center">
                <span class="mr-2">💾</span>
                Salvar Alterações
            </button>
        </div>
    </div>
</div>

<script>
function editarPerfil() {
    document.getElementById('editModal').classList.remove('hidden');
}

function fecharModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Fechar modal ao clicar fora dele
document.getElementById('editModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModal();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('editModal');
        if (modal && !modal.classList.contains('hidden')) {
            fecharModal();
        }
    }
});

document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="mr-2">⏳</span> Salvando...';
    }
    
    const formData = new FormData(form);
    
    fetch('<?= URL ?>/onboarding/salvar', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensagem de sucesso
            const successMsg = document.createElement('div');
            successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            successMsg.innerHTML = '✅ Perfil atualizado com sucesso!';
            document.body.appendChild(successMsg);
            
            setTimeout(() => {
                fecharModal();
                location.reload();
            }, 1000);
        } else {
            alert('Erro: ' + (data.error || 'Erro ao salvar perfil'));
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span class="mr-2">💾</span> Salvar Alterações';
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar perfil. Tente novamente.');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span class="mr-2">💾</span> Salvar Alterações';
        }
    });
});
</script>
