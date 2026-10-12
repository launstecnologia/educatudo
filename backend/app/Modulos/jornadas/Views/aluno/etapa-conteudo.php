<?php
// Arquivo incluído para renderizar o conteúdo de cada tipo de módulo
// Variáveis disponíveis: $moduloAtual, $estaConcluidaAtual, $jornada

// Garantir que as variáveis estão definidas
if (!isset($moduloAtual) || !is_array($moduloAtual)) {
    echo '<div class="text-red-500 p-4">Erro: Módulo não encontrado</div>';
    return;
}

$modulo = $moduloAtual;
$estaConcluida = $estaConcluidaAtual ?? false;

// Garantir que $jornada está disponível
if (!isset($jornada) || !is_array($jornada)) {
    // Tentar buscar do escopo global
    global $jornada;
    if (!isset($jornada)) {
        echo '<div class="text-red-500 p-4">Erro: Jornada não encontrada</div>';
        return;
    }
}

if (!function_exists('jornadaBuildMediaKey')) {
    function jornadaBuildMediaKey($path, $segment)
    {
        $raw = trim((string) $path);
        if ($raw === '') {
            return '';
        }
        $raw = str_replace('\\', '/', $raw);
        $parts = @parse_url($raw);
        if (is_array($parts) && isset($parts['path'])) {
            $raw = $parts['path'];
        }
        $raw = ltrim($raw, '/');

        if (strpos($raw, 'public/uploads/' . $segment . '/') === 0) {
            return substr($raw, strlen('public/uploads/' . $segment . '/'));
        }
        if (strpos($raw, 'uploads/' . $segment . '/') === 0) {
            return substr($raw, strlen('uploads/' . $segment . '/'));
        }
        if (strpos($raw, $segment . '/') === 0) {
            return substr($raw, strlen($segment . '/'));
        }

        $needle = '/' . $segment . '/';
        $pos = strpos('/' . $raw, $needle);
        if ($pos !== false) {
            $from = substr('/' . $raw, $pos + strlen($needle));
            return ltrim($from, '/');
        }

        return basename($raw);
    }
}
?>

<?php if ($modulo['tipo_modulo'] === 'video' || $modulo['tipo_modulo'] === 'conteudo' || $modulo['tipo_modulo'] === 'dica_professor'): ?>
    <!-- Módulo de Vídeo/Conteúdo/Dica do Professor -->
    <?php 
    $modulo['textos'] = $modulo['textos'] ?? [];
    $temConteudo = !empty($modulo['videos']) || !empty($modulo['documentos']) || !empty($modulo['textos']);
    ?>
    <?php if ($temConteudo): ?>
        <?php if (!$estaConcluida): ?>
            <!-- Botão Iniciar etapa (conteúdo só aparece após clicar) -->
            <div id="iniciar-etapa-wrap-<?= $modulo['id'] ?>" class="text-center py-8">
                <p class="text-gray-600 mb-4">Clique em iniciar para ver o conteúdo. O tempo será contabilizado até você finalizar.</p>
                <button type="button" onclick="iniciarEtapa(<?= $modulo['id'] ?>)" 
                        class="px-8 py-4 bg-blue-500 text-white rounded-lg text-lg font-semibold hover:bg-blue-600 transition-colors inline-flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Iniciar etapa
                </button>
            </div>
        <?php endif; ?>
        <div class="space-y-4 <?= !$estaConcluida ? 'hidden' : '' ?>" <?= !$estaConcluida ? 'id="conteudo-etapa-real-' . (int)$modulo['id'] . '"' : '' ?>>
            <?php if (!$estaConcluida): ?>
                <p class="text-sm text-gray-600 flex items-center gap-2">
                    <span>Tempo nesta etapa:</span>
                    <strong id="timer-etapa-<?= $modulo['id'] ?>">00:00</strong>
                </p>
            <?php endif; ?>
            <!-- Textos / Dicas em texto (sem anexo) -->
            <?php if (!empty($modulo['textos'])): ?>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-3">📝 Dicas do professor</h4>
                    <div class="space-y-4">
                        <?php foreach ($modulo['textos'] as $txt): ?>
                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/50 hover:bg-gray-50 transition-colors">
                                <h5 class="font-medium text-gray-900 mb-2"><?= htmlspecialchars($txt['titulo']) ?></h5>
                                <?php if (!empty($txt['conteudo'])): ?>
                                    <div class="text-gray-700 prose prose-sm max-w-none">
                                        <?= rich_text_render($txt['conteudo']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Vídeos / Link externo (um conteúdo por bloco) -->
            <?php if (!empty($modulo['videos'])): ?>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-3">🎥 Conteúdo</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($modulo['videos'] as $video): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                <?php if (($video['tipo'] ?? '') === 'link_externo' && !empty($video['url_youtube'])): ?>
                                    <!-- Link externo: botão clicável -->
                                    <h5 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($video['titulo']) ?></h5>
                                    <?php if (!empty($video['descricao'])): ?>
                                        <div class="text-sm text-gray-600 prose prose-sm max-w-none mb-3">
                                            <?= rich_text_render($video['descricao']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <a href="<?= htmlspecialchars($video['url_youtube'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600 transition-colors">
                                        Abrir link
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                    </a>
                                <?php elseif ($video['tipo'] === 'youtube' && $video['url_youtube']): ?>
                                    <?php
                                    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $video['url_youtube'], $matches);
                                    $videoId = $matches[1] ?? null;
                                    ?>
                                    <?php if ($videoId): ?>
                                        <div class="aspect-video bg-gray-200 rounded-lg overflow-hidden mb-3">
                                            <iframe class="w-full h-full" 
                                                    src="https://www.youtube.com/embed/<?= $videoId ?>" 
                                                    frameborder="0" 
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                    allowfullscreen></iframe>
                                        </div>
                                    <?php endif; ?>
                                    <h5 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($video['titulo']) ?></h5>
                                    <?php if ($video['descricao']): ?>
                                        <div class="text-sm text-gray-600 prose prose-sm max-w-none">
                                            <?= rich_text_render($video['descricao']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php
                                    $videoRef = $video['arquivo_video'] ?? '';
                                    $videoKey = (strpos($videoRef, 'public/uploads/') === 0 || strpos($videoRef, 'uploads/') === 0)
                                        ? jornadaBuildMediaKey($videoRef, 'videos') : (trim($videoRef) !== '' ? $videoRef : '');
                                    $videoSrc = $videoKey !== '' ? (URL . '/media/serve?type=jornadas_videos&key=' . rawurlencode($videoKey)) : '';
                                    ?>
                                    <div class="aspect-video bg-gray-200 rounded-lg flex items-center justify-center mb-3">
                                        <video controls class="w-full h-full">
                                            <source src="<?= htmlspecialchars($videoSrc, ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                                        </video>
                                    </div>
                                    <h5 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($video['titulo']) ?></h5>
                                    <?php if ($video['descricao']): ?>
                                        <div class="text-sm text-gray-600 prose prose-sm max-w-none">
                                            <?= rich_text_render($video['descricao']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Documentos -->
            <?php if (!empty($modulo['documentos'])): ?>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-3">📄 Documentos</h4>
                    <div class="space-y-2">
                        <?php foreach ($modulo['documentos'] as $doc): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-center space-x-3 flex-1 min-w-0">
                                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <span class="text-xl">📄</span>
                                        </div>
                                        <div class="min-w-0">
                                            <h5 class="font-medium text-gray-900"><?= htmlspecialchars($doc['titulo']) ?></h5>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($doc['arquivo_nome']) ?></p>
                                        </div>
                                    </div>
                                    <?php
                                    $docRef = $doc['arquivo'] ?? '';
                                    $docKey = (strpos($docRef, 'public/uploads/') === 0 || strpos($docRef, 'uploads/') === 0)
                                        ? jornadaBuildMediaKey($docRef, 'documentos') : (trim($docRef) !== '' ? $docRef : '');
                                    $docUrl = $docKey !== '' ? (URL . '/media/serve?type=jornadas_documentos&key=' . rawurlencode($docKey)) : '#';
                                    ?>
                                    <a href="<?= htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" 
                                       class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600 transition-colors flex-shrink-0">
                                        Ver
                                    </a>
                                </div>
                                <?php if (!empty($doc['descricao'])): ?>
                                    <div class="mt-3 pt-3 border-t border-gray-100 text-gray-700 prose prose-sm max-w-none">
                                        <?= rich_text_render($doc['descricao']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Botão Finalizar -->
            <?php if (!$estaConcluida): ?>
                <div class="mt-6 flex justify-end">
                    <button type="button" class="js-btn-finalizar px-6 py-3 bg-green-500 text-white rounded-lg font-medium hover:bg-green-600 transition-colors flex items-center" onclick="finalizarEtapa(<?= $modulo['id'] ?>, '<?= $modulo['tipo_modulo'] ?>')">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Finalizar e Continuar
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4 inline-block">
                <p class="text-sm text-yellow-800">
                    <strong>⚠️</strong> Este módulo ainda não possui conteúdo configurado pelo professor.
                </p>
            </div>
            <?php if (!$estaConcluida): ?>
                <div class="mt-4">
                    <button type="button" class="js-btn-finalizar px-6 py-3 bg-green-500 text-white rounded-lg font-medium hover:bg-green-600 transition-colors inline-flex items-center" onclick="finalizarEtapa(<?= $modulo['id'] ?>, '<?= $modulo['tipo_modulo'] ?>')">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                        Avançar para próxima etapa
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
<?php elseif ($modulo['tipo_modulo'] === 'resumo_aluno'): ?>
    <!-- Módulo de Resumo do Aluno -->
    <div class="space-y-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-blue-800">
                <strong>📝 Instruções:</strong> 
                <?php if (!empty($modulo['descricao'])): ?>
                    <?= nl2br(htmlspecialchars($modulo['descricao'])) ?>
                <?php else: ?>
                    Leia o conteúdo e escreva um resumo sobre o que você entendeu.
                <?php endif; ?>
            </p>
        </div>
        
        <?php if ($estaConcluida && $modulo['resumo']): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="font-semibold text-green-900 mb-2">Seu Resumo:</h4>
                <div class="prose prose-sm max-w-none text-gray-700 resumo-html">
                    <?php 
                    $resumoTexto = $modulo['resumo']['resumo_aluno'] ?? '';
                    if (strpos($resumoTexto, '<') !== false && class_exists(\App\Utils\HtmlSanitizer::class)) {
                        echo \App\Utils\HtmlSanitizer::clean($resumoTexto);
                    } else {
                        echo nl2br(htmlspecialchars($resumoTexto));
                    }
                    ?>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    Enviado em: <?= date('d/m/Y H:i', strtotime($modulo['resumo']['created_at'] ?? 'now')) ?>
                </p>
            </div>
            <?php
            $notaResumo = isset($modulo['resumo']['nota']) && $modulo['resumo']['nota'] !== null && $modulo['resumo']['nota'] !== '' ? (float)$modulo['resumo']['nota'] : null;
            $obsProfessor = trim($modulo['resumo']['observacoes_professor'] ?? '');
            if ($notaResumo !== null || $obsProfessor !== ''):
            ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                <h4 class="font-semibold text-blue-900 mb-2">Avaliação do professor</h4>
                <?php if ($notaResumo !== null): ?>
                    <p class="text-sm text-blue-800 mb-2"><strong>Nota:</strong> <?= number_format($notaResumo, 1) ?> / 10</p>
                <?php endif; ?>
                <?php if ($obsProfessor !== ''): ?>
                    <p class="text-sm text-blue-800 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($obsProfessor)) ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <form id="form-resumo-<?= $modulo['id'] ?>" class="space-y-4">
                <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
                <input type="hidden" name="jornada_id" value="<?= isset($jornada['id']) ? $jornada['id'] : '' ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Seu Resumo *</label>
                    <textarea id="textarea-resumo-<?= $modulo['id'] ?>" name="resumo" rows="8" required
                              class="js-resumo-textarea w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Escreva aqui o seu resumo sobre o conteúdo estudado..."
                              data-modulo-id="<?= (int)$modulo['id'] ?>"></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="btn-enviar-resumo-<?= $modulo['id'] ?>" onclick="enviarResumo(<?= $modulo['id'] ?>, 'resumo_aluno')" 
                            class="px-6 py-3 bg-green-500 text-white rounded-lg font-medium hover:bg-green-600 transition-colors flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Enviar Resumo
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
<?php elseif ($modulo['tipo_modulo'] === 'exercicios' || $modulo['tipo_modulo'] === 'exercicio'): ?>
    <!-- Módulo de Exercícios -->
    <?php 
    if (!isset($modulo['exercicios'])) {
        $modulo['exercicios'] = [];
    }
    ?>
    <?php if (!empty($modulo['exercicios'])): ?>
        <?php 
        $todosExerciciosConcluidos = true;
        foreach ($modulo['exercicios'] as $ex) {
            if ($ex['progresso_status'] !== 'concluido') {
                $todosExerciciosConcluidos = false;
                break;
            }
        }
        ?>
        <div class="space-y-6">
            <div class="flex justify-center items-center py-8 gap-4">
                <?php if ($todosExerciciosConcluidos): ?>
                    <!-- Botão Visualizar Exercícios (quando concluído) -->
                    <a href="<?= URL ?>/jornadas/<?= isset($jornada['id']) ? $jornada['id'] : '' ?>/modulos/<?= $modulo['id'] ?>/exercicios<?= !empty($preview) ? '?preview=1' : '' ?>" 
                       class="px-8 py-4 bg-green-500 text-white rounded-lg text-lg font-semibold hover:bg-green-600 transition-colors flex items-center gap-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        Visualizar Exercícios
                    </a>
                <?php else: ?>
                    <!-- Botão Iniciar Exercícios (quando não concluído) -->
                    <a href="<?= URL ?>/jornadas/<?= isset($jornada['id']) ? $jornada['id'] : '' ?>/modulos/<?= $modulo['id'] ?>/exercicios<?= !empty($preview) ? '?preview=1' : '' ?>" 
                       class="px-8 py-4 bg-blue-500 text-white rounded-lg text-lg font-semibold hover:bg-blue-600 transition-colors flex items-center gap-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Iniciar Exercícios
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Botão Finalizar Módulo -->
            <?php if ($todosExerciciosConcluidos && !$estaConcluida): ?>
                <div class="flex justify-end">
                    <button type="button" class="js-btn-finalizar px-6 py-3 bg-green-500 text-white rounded-lg font-medium hover:bg-green-600 transition-colors flex items-center" onclick="finalizarEtapa(<?= $modulo['id'] ?>, 'exercicios')">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Finalizar e Continuar
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4 inline-block">
                <p class="text-sm text-yellow-800">
                    <strong>⚠️</strong> Nenhum exercício disponível neste módulo.
                </p>
            </div>
            <?php if (!$estaConcluida): ?>
                <div class="mt-4">
                    <button type="button" class="js-btn-finalizar px-6 py-3 bg-green-500 text-white rounded-lg font-medium hover:bg-green-600 transition-colors inline-flex items-center" onclick="finalizarEtapa(<?= $modulo['id'] ?>, 'exercicios')">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                        Avançar para próxima etapa
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
<?php else: ?>
    <!-- Outros tipos de módulo -->
    <div class="text-center py-6">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4 inline-block">
            <p class="text-sm text-yellow-800">
                <strong>⚠️</strong> Este módulo ainda não possui conteúdo configurado.
            </p>
        </div>
        <?php if (!$estaConcluida): ?>
            <div class="mt-4">
                <button type="button" class="js-btn-finalizar px-6 py-3 bg-green-500 text-white rounded-lg font-medium hover:bg-green-600 transition-colors inline-flex items-center" onclick="finalizarEtapa(<?= $modulo['id'] ?>, '<?= $modulo['tipo_modulo'] ?>')">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                    Avançar para próxima etapa
                </button>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

