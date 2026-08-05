<?php
// Função helper para processar conteúdo HTML
function processarConteudo($texto) {
    if (empty($texto)) return '';
    
    // Remove tags HTML desnecessárias mas mantém estrutura
    $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Converte <br> e <br/> em quebras de linha
    $texto = preg_replace('/<br\s*\/?>/i', "\n", $texto);
    
    // Converte <p> em parágrafos (remove tags, mantém conteúdo)
    $texto = preg_replace('/<\/p>/i', "\n\n", $texto);
    $texto = preg_replace('/<p[^>]*>/i', '', $texto);
    
    // Remove outras tags HTML comuns mas mantém o texto
    $texto = strip_tags($texto);
    
    // Limpa múltiplas quebras de linha e espaços
    $texto = preg_replace('/\n{3,}/', "\n\n", $texto);
    $texto = preg_replace('/[ \t]+/', ' ', $texto);
    
    // Remove linhas vazias no início e fim
    $texto = trim($texto);
    
    // Converte quebras de linha em <br> para exibição
    $texto = nl2br($texto);
    
    return $texto;
}

// Função para processar lista de objetivos
function processarObjetivos($texto) {
    if (empty($texto)) return '';
    
    $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace('/<br\s*\/?>/i', "\n", $texto);
    $texto = preg_replace('/<\/p>/i', "\n", $texto);
    $texto = preg_replace('/<p[^>]*>/i', '', $texto);
    $texto = strip_tags($texto);
    
    // Limpa múltiplas quebras de linha
    $texto = preg_replace('/\n{3,}/', "\n\n", $texto);
    $texto = trim($texto);
    
    // Divide por linhas e formata como lista
    $linhas = preg_split('/\n+/', $texto);
    $linhas = array_filter(array_map('trim', $linhas));
    
    if (empty($linhas)) return '';
    
    $resultado = '';
    foreach ($linhas as $linha) {
        if (!empty($linha)) {
            $linhaLimpa = trim($linha);
            // Se começa com "-", mantém como item de lista
            if (strpos($linhaLimpa, '-') === 0) {
                $conteudo = trim($linhaLimpa, '- ');
                $resultado .= '<div class="flex items-start mb-3"><span class="text-green-600 mr-3 mt-1">•</span><span class="flex-1">' . htmlspecialchars($conteudo) . '</span></div>';
            } else {
                $resultado .= '<div class="mb-3">' . htmlspecialchars($linhaLimpa) . '</div>';
            }
        }
    }
    
    return $resultado;
}
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= URL ?>/aluno/planos-aula" class="text-blue-600 hover:text-blue-700 mb-4 inline-flex items-center transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar para Planos de Aula
            </a>
            <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($plano_aula['titulo'] ?? 'Plano de Aula') ?></h1>
        </div>
    </div>
</div>

<!-- Plano de Aula Card -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <!-- Header com informações principais -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-8 py-6 border-b border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php if (!empty($plano_aula['materia_nome'])): ?>
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Matéria</p>
                    <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($plano_aula['materia_nome']) ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($plano_aula['professor_nome'])): ?>
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Professor</p>
                    <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($plano_aula['professor_nome']) ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($plano_aula['turma_nome'])): ?>
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Turma</p>
                    <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($plano_aula['turma_nome']) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="p-8">
    
        <!-- Datas -->
        <?php if (!empty($plano_aula['data_aula']) || !empty($plano_aula['data_inicio']) || !empty($plano_aula['data_fim'])): ?>
        <div class="mb-8 pb-8 border-b border-gray-200">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Data(s) da Aula</h2>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <?php if (!empty($plano_aula['data_aula'])): ?>
                    <?php 
                    $dataAula = $plano_aula['data_aula'];
                    // Se for JSON, decodifica
                    if (is_string($dataAula) && (substr($dataAula, 0, 1) === '[' || substr($dataAula, 0, 1) === '{')) {
                        $dataAula = json_decode($dataAula, true);
                    }
                    // Se for array, mostra todas as datas
                    if (is_array($dataAula) && !empty($dataAula)) {
                        $datas = array_filter($dataAula);
                        foreach ($datas as $data): ?>
                            <div class="flex items-center px-4 py-2 bg-blue-50 rounded-lg text-gray-700">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span class="font-medium"><?= date('d/m/Y', strtotime($data)) ?></span>
                            </div>
                        <?php endforeach;
                    } else {
                        // Se for string simples, mostra a data
                        ?>
                        <div class="flex items-center px-4 py-2 bg-blue-50 rounded-lg text-gray-700">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span class="font-medium"><?= date('d/m/Y', strtotime($dataAula)) ?></span>
                        </div>
                    <?php } ?>
                <?php elseif (!empty($plano_aula['data_inicio']) && !empty($plano_aula['data_fim'])): ?>
                    <div class="flex items-center px-4 py-2 bg-blue-50 rounded-lg text-gray-700">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="font-medium"><?= date('d/m/Y', strtotime($plano_aula['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($plano_aula['data_fim'])) ?></span>
                    </div>
                <?php elseif (!empty($plano_aula['data_inicio'])): ?>
                    <div class="flex items-center px-4 py-2 bg-blue-50 rounded-lg text-gray-700">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="font-medium">A partir de <?= date('d/m/Y', strtotime($plano_aula['data_inicio'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Conteúdo -->
        <?php if (!empty($plano_aula['conteudo'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Conteúdo</h2>
            </div>
            <div class="bg-gray-50 rounded-lg p-6 text-gray-700 leading-relaxed">
                <?= processarConteudo($plano_aula['conteudo']) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Objetivos -->
        <?php if (!empty($plano_aula['objetivos'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Objetivos de Aprendizagem</h2>
            </div>
            <div class="bg-green-50 rounded-lg p-6 text-gray-700 leading-relaxed">
                <?= processarObjetivos($plano_aula['objetivos']) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Metodologia -->
        <?php if (!empty($plano_aula['metodologia'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Metodologia</h2>
            </div>
            <div class="bg-purple-50 rounded-lg p-6 text-gray-700 leading-relaxed">
                <?= processarConteudo($plano_aula['metodologia']) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recursos -->
        <?php if (!empty($plano_aula['recursos'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Recursos Didáticos</h2>
            </div>
            <div class="bg-yellow-50 rounded-lg p-6 text-gray-700 leading-relaxed">
                <?= processarConteudo($plano_aula['recursos']) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Avaliação -->
        <?php if (!empty($plano_aula['avaliacao'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Avaliação</h2>
            </div>
            <div class="bg-red-50 rounded-lg p-6 text-gray-700 leading-relaxed">
                <?= processarConteudo($plano_aula['avaliacao']) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Conteúdo Lista -->
        <?php if (!empty($plano_aula['conteudo_lista'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Conteúdo (Lista)</h2>
            </div>
            <div class="bg-indigo-50 rounded-lg p-6 text-gray-700 leading-relaxed">
                <?= processarConteudo($plano_aula['conteudo_lista']) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Objetivos Lista -->
        <?php if (!empty($plano_aula['objetivos_lista'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Objetivos (Lista)</h2>
            </div>
            <div class="bg-green-50 rounded-lg p-6 text-gray-700 leading-relaxed">
                <?= processarObjetivos($plano_aula['objetivos_lista']) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recursos Lista -->
        <?php if (!empty($plano_aula['recursos_lista'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Recursos (Lista)</h2>
            </div>
            <div class="bg-yellow-50 rounded-lg p-6 text-gray-700 leading-relaxed">
                <?php 
                $recursosLista = is_string($plano_aula['recursos_lista']) ? json_decode($plano_aula['recursos_lista'], true) : $plano_aula['recursos_lista'];
                if (is_array($recursosLista)) {
                    foreach ($recursosLista as $recurso) {
                        echo '<div class="flex items-start mb-2"><span class="text-yellow-600 mr-2">•</span><span>' . htmlspecialchars($recurso) . '</span></div>';
                    }
                } else {
                    echo processarConteudo($plano_aula['recursos_lista']);
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Aulas da Tarde (Oficinas) -->
        <?php if (!empty($plano_aula['aulas_tarde_oficinas'])): ?>
        <?php require_once __DIR__ . '/../../../Helpers/LessonPlanAfternoonHelper.php'; ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-amber-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Aulas da Tarde (Oficinas de Aprendizagem / Salas de Estudo)</h2>
            </div>
            <div class="bg-amber-50 rounded-lg p-6 text-gray-700 leading-relaxed">
                <?= LessonPlanAfternoonHelper::renderHtml($plano_aula['aulas_tarde_oficinas']) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Período Tarde -->
        <?php if (!empty($plano_aula['periodo_tarde_tema']) || !empty($plano_aula['periodo_tarde_exercicios'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Período da Tarde</h2>
            </div>
            <div class="bg-orange-50 rounded-lg p-6 space-y-4">
                <?php if (!empty($plano_aula['periodo_tarde_tema'])): ?>
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-2">Tema</p>
                    <div class="text-gray-700 leading-relaxed">
                        <?= processarConteudo($plano_aula['periodo_tarde_tema']) ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($plano_aula['periodo_tarde_exercicios'])): ?>
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-2">Exercícios</p>
                    <div class="text-gray-700 leading-relaxed">
                        <?= processarConteudo($plano_aula['periodo_tarde_exercicios']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Detalhes da Avaliação -->
        <?php if (!empty($plano_aula['avaliacao_apostila']) || !empty($plano_aula['avaliacao_conteudo']) || !empty($plano_aula['avaliacao_paginas'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Detalhes da Avaliação</h2>
            </div>
            <div class="bg-red-50 rounded-lg p-6 space-y-4">
                <?php if (!empty($plano_aula['avaliacao_apostila'])): ?>
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-2">Apostila</p>
                    <div class="text-gray-700"><?= htmlspecialchars($plano_aula['avaliacao_apostila']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($plano_aula['avaliacao_conteudo'])): ?>
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-2">Conteúdo</p>
                    <div class="text-gray-700 leading-relaxed"><?= processarConteudo($plano_aula['avaliacao_conteudo']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($plano_aula['avaliacao_paginas'])): ?>
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-2">Páginas</p>
                    <div class="text-gray-700"><?= htmlspecialchars($plano_aula['avaliacao_paginas']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Informações Adicionais -->
        <?php if (!empty($plano_aula['modulo']) || !empty($plano_aula['aula_num']) || !empty($plano_aula['paginas']) || !empty($plano_aula['ano_disciplina'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Informações Adicionais</h2>
            </div>
            <div class="bg-gray-50 rounded-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php if (!empty($plano_aula['modulo'])): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Módulo</p>
                        <p class="text-gray-900"><?= htmlspecialchars($plano_aula['modulo']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($plano_aula['aula_num'])): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Número da Aula</p>
                        <p class="text-gray-900"><?= htmlspecialchars($plano_aula['aula_num']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($plano_aula['paginas'])): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Páginas</p>
                        <p class="text-gray-900"><?= htmlspecialchars($plano_aula['paginas']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($plano_aula['ano_disciplina'])): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Ano da Disciplina</p>
                        <?php $anoDisciplina = preg_replace('/\s*\(\+\d+\)/', '', $plano_aula['ano_disciplina']); ?>
                        <p class="text-gray-900"><?= htmlspecialchars($anoDisciplina) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Observações -->
        <?php if (!empty($plano_aula['observacoes'])): ?>
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10m-7 4h7"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900">Observações</h2>
            </div>
            <div class="bg-gray-50 rounded-lg p-6 text-gray-700 leading-relaxed">
                <?= processarConteudo($plano_aula['observacoes']) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

