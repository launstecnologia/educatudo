<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Corrigir Redação - <?= htmlspecialchars($redacao['aluno_nome']) ?>
            </h2>
            <p class="text-gray-600">
                <?= htmlspecialchars($jornada['titulo']) ?> • 
                <?= htmlspecialchars($redacao['tema_sugerido'] ?? 'Tema não definido') ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/exercicios-alunos" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<form id="formCorrigirRedacao" method="POST" action="<?= URL ?>/professor/jornadas/corrigir-redacao">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="redacao_id" value="<?= $redacao['id'] ?? $redacao['redacao_id'] ?? '' ?>">
    <input type="hidden" name="jornada_id" value="<?= $jornada['id'] ?? $redacao['jornada_id'] ?? '' ?>">
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Redação do Aluno -->
        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Redação do Aluno</h3>
            
            <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600"><strong>Aluno:</strong> <?= htmlspecialchars($redacao['aluno_nome']) ?></p>
                <?php if ($redacao['aluno_ra']): ?>
                    <p class="text-sm text-gray-600"><strong>RA:</strong> <?= htmlspecialchars($redacao['aluno_ra']) ?></p>
                <?php endif; ?>
                <p class="text-sm text-gray-600"><strong>Versão:</strong> <?= $redacao['versao'] ?? 1 ?></p>
                <p class="text-sm text-gray-600"><strong>Entregue em:</strong> <?= date('d/m/Y H:i', strtotime($redacao['created_at'])) ?></p>
                <?php if ($redacao['tempo_escrita']): ?>
                    <p class="text-sm text-gray-600"><strong>Tempo de escrita:</strong> <?= gmdate('H:i:s', $redacao['tempo_escrita']) ?></p>
                <?php endif; ?>
            </div>
            
            <style>
                .redacao-pautada { background-color: #fefefe; background-image: repeating-linear-gradient( transparent 0px, transparent 27px, rgba(203, 213, 225, 0.6) 27px, rgba(203, 213, 225, 0.6) 28px ); line-height: 28px; }
            </style>
            <div class="prose max-w-none">
                <div class="redacao-pautada whitespace-pre-wrap text-gray-800 border border-gray-200 rounded-lg px-4 py-4 min-h-[400px] max-h-[600px] overflow-y-auto">
                    <?= htmlspecialchars($redacao['conteudo'] ?? $redacao['texto'] ?? '') ?>
                </div>
            </div>
        </div>
        
        <!-- Formulário de Correção -->
        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Sua Correção</h3>
                    <p class="text-sm text-gray-600 mt-1">Preencha as competências, comentários e sugestões de melhoria</p>
                </div>
                <div class="flex gap-2">
                    <?php if (($versao_atual ?? 1) > 1 && !empty($correcao_versao_anterior ?? null)): ?>
                        <button 
                            type="button"
                            onclick="usarCorrecaoVersaoAnterior()"
                            id="btnUsarVersaoAnterior"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium flex items-center gap-2"
                            title="Preencher com a correção da versão anterior"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Usar Correção da Versão <?= $versao_anterior_numero ?? 1 ?>
                        </button>
                    <?php endif; ?>
                    <?php
                    if (!class_exists('CreditosModuleRegistry', false)) {
                        require_once __DIR__ . '/../../../Core/CreditosModuleRegistry.php';
                    }
                    $tudicoinsCorrecaoIa = \CreditosModuleRegistry::acaoIaDisponivel('redacao_correcao_ia');
                    ?>
                    <?php if ($tudicoinsCorrecaoIa): ?>
                    <button 
                        type="button"
                        onclick="solicitarCorrecaoIA()"
                        id="btnCorrigirIA"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        Corrigir com IA
                    </button>
                    <?php if (!empty($redacao['feedback_ia'])): ?>
                    <button 
                        type="button"
                        onclick="solicitarCorrecaoIA()"
                        id="btnRefazerCorrecaoIA"
                        class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors text-sm font-medium flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Refazer a correção
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Competências -->
            <div class="space-y-4 mb-6">
                <?php 
                $competencias = [
                    1 => 'Domínio da norma padrão da Língua Portuguesa',
                    2 => 'Compreensão da proposta e desenvolvimento do tema',
                    3 => 'Seleção e organização de argumentos',
                    4 => 'Coesão e coerência',
                    5 => 'Proposta de intervenção'
                ];
                
                foreach ($competencias as $num => $nome): 
                    // Buscar nota do professor (salva em competencia_X)
                    $notaProfessor = $redacao["competencia_{$num}"] ?? null;
                    
                    // Buscar explicação do professor
                    $explicacaoAtual = $redacao["competencia_{$num}_explicacao_professor"] ?? '';
                    
                    // Verificar se deve mostrar ao aluno (padrão: marcado se não houver valor salvo)
                    $mostrarAoAluno = isset($redacao["mostrar_competencia_{$num}_aluno"]) 
                        ? (int)$redacao["mostrar_competencia_{$num}_aluno"] 
                        : 1; // Por padrão, vem marcado
                    
                    // Extrair nota da IA do feedback_ia
                    $notaIA = null;
                    $explicacaoIA = '';
                    if (!empty($redacao['feedback_ia'])) {
                        $feedbackIA = json_decode($redacao['feedback_ia'], true);
                        if ($feedbackIA && isset($feedbackIA["competencia_{$num}"])) {
                            $notaIA = $feedbackIA["competencia_{$num}"]['nota'] ?? null;
                            $explicacaoIA = $feedbackIA["competencia_{$num}"]['explicacao'] ?? '';
                        }
                    }
                ?>
                    <div class="border border-gray-200 rounded-lg p-4 mb-4">
                        <div class="mb-3">
                            <label class="text-sm font-semibold text-gray-900">
                                Competência <?= $num ?>
                            </label>
                            <p class="text-xs text-gray-600 mt-1"><?= htmlspecialchars($nome) ?></p>
                        </div>
                        
                        <?php if ($notaIA !== null || !empty($explicacaoIA)): ?>
                        <!-- Correção da IA (SEMPRE EM CIMA) -->
                        <div class="mb-4 p-4 bg-blue-50 border-2 border-blue-300 rounded-lg">
                            <div class="mb-3">
                                <span class="text-sm font-bold text-blue-900 uppercase">Correção Tudinha (IA)</span>
                            </div>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-sm font-medium text-blue-800">Nota:</span>
                                <span class="text-xl font-bold text-blue-600"><?= $notaIA ?? 0 ?>/200</span>
                            </div>
                            <?php if ($explicacaoIA): ?>
                                <div class="mt-3 pt-3 border-t border-blue-200">
                                    <p class="text-xs font-semibold text-blue-900 mb-2">Explicação:</p>
                                    <p class="text-sm text-blue-800 leading-relaxed"><?= htmlspecialchars($explicacaoIA) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Correção do Professor (SEMPRE EM BAIXO) -->
                        <div class="p-4 bg-gray-50 border-2 border-gray-300 rounded-lg">
                            <div class="mb-3">
                                <span class="text-sm font-bold text-gray-900 uppercase">Sua Correção (Professor)</span>
                            </div>
                            
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    Descrição / feedback da IA (somente leitura)
                                </label>
                                <textarea
                                    id="explicacao_ia_<?= $num ?>"
                                    rows="4"
                                    readonly
                                    class="w-full px-3 py-2 border border-blue-200 rounded-lg bg-blue-50 text-blue-900"
                                    placeholder="A explicação da IA aparecerá aqui..."
                                ><?= htmlspecialchars($explicacaoIA) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <label class="block text-xs font-medium text-gray-700">
                                        Descrição / comentário do professor
                                    </label>
                                    <button
                                        type="button"
                                        class="text-xs px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100"
                                        onclick="copiarFeedbackIAParaProfessor(<?= $num ?>)"
                                    >
                                        Copiar texto da IA
                                    </button>
                                </div>
                                <textarea 
                                    name="explicacao_<?= $num ?>" 
                                    id="explicacao_<?= $num ?>"
                                    rows="5" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                                    placeholder="Adicione aqui seu comentário para o aluno..."
                                ><?= htmlspecialchars($explicacaoAtual) ?></textarea>
                            </div>

                            <div class="mb-3 hidden" id="copyFeedbackMsg_<?= $num ?>">
                                <p class="text-xs text-green-700 font-medium">Texto da IA copiado para o campo do professor.</p>
                            </div>

                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    Nota (0-200)
                                </label>
                                <input 
                                    type="number" 
                                    name="competencia_<?= $num ?>" 
                                    id="competencia_<?= $num ?>"
                                    min="0" 
                                    max="200" 
                                    value="<?= $notaProfessor ?>" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                                    onchange="calcularNotaFinal()"
                                    required
                                >
                            </div>
                            
                            <!-- Checkbox para mostrar ao aluno -->
                            <div class="pt-3 border-t border-gray-300">
                                <label class="flex items-center cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="mostrar_competencia_<?= $num ?>_aluno" 
                                        value="1" 
                                        id="mostrarCompetencia<?= $num ?>"
                                        <?= $mostrarAoAluno ? 'checked' : '' ?>
                                        class="mr-2 w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    >
                                    <span class="text-xs text-gray-700">Mostrar esta competência para o aluno</span>
                                </label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php 
            // Calcular nota da IA se existir
            $notaIAFinal = 0;
            if (!empty($redacao['feedback_ia'])) {
                $feedbackIA = json_decode($redacao['feedback_ia'], true);
                if ($feedbackIA) {
                    for ($i = 1; $i <= 5; $i++) {
                        if (isset($feedbackIA["competencia_{$i}"]['nota'])) {
                            $notaIAFinal += (int)$feedbackIA["competencia_{$i}"]['nota'];
                        }
                    }
                }
            }
            $usarMedia = $redacao['usar_media_notas'] ?? 0;
            $notaMediaSalva = $redacao['nota_media'] ?? null;
            ?>
            
            <!-- Nota Final Calculada -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600 mb-1" id="notaFinalDisplay">
                        0/1000
                    </div>
                    <div class="text-sm text-blue-800">Nota Final (Soma das Competências)</div>
                </div>
            </div>
            
            <?php if ($notaIAFinal > 0): ?>
            <!-- Opção de Média -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <label class="flex items-start cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="usar_media_notas" 
                        value="1" 
                        id="usarMediaNotas"
                        <?= $usarMedia ? 'checked' : '' ?>
                        onchange="calcularNotaFinal()"
                        class="mt-1 mr-3 w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    >
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-gray-900 mb-1">
                            Usar média entre minha nota e a nota da IA
                        </div>
                        <div class="text-xs text-gray-600 mb-2">
                            Nota IA: <span id="notaIADisplay"><?= $notaIAFinal ?>/1000</span>
                        </div>
                    </div>
                </label>
            </div>
            
            <!-- Campo Nota Média (aparece quando checkbox está marcado) -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4" id="campoNotaMedia" style="display: <?= $usarMedia ? 'block' : 'none' ?>;">
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600 mb-1" id="notaMediaDisplay">
                        <?= $notaMediaSalva ? $notaMediaSalva : '0' ?>/1000
                    </div>
                    <div class="text-sm text-green-800">Nota Final (Média)</div>
                </div>
            </div>
            <?php endif; ?>
            
            
            
            <!-- Comentários Gerais -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Comentários Gerais
                </label>
                <textarea 
                    name="comentarios_gerais" 
                    id="comentarios_gerais"
                    rows="4" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Comentários gerais sobre a redação..."
                ><?= htmlspecialchars($redacao['comentarios_gerais_professor'] ?? '') ?></textarea>
            </div>
            
            <!-- Sugestões de Melhoria -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Sugestões de Melhoria
                </label>
                <textarea 
                    name="sugestoes_melhoria" 
                    id="sugestoes_melhoria"
                    rows="4" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Sugestões para o aluno melhorar..."
                ><?= htmlspecialchars($redacao['sugestoes_melhoria_professor'] ?? '') ?></textarea>
            </div>
            
            <!-- Opção para permitir refazer redação -->
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <label class="flex items-center cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="permitir_refazer" 
                        value="1" 
                        id="permitirRefazer"
                        <?= ($redacao['permitir_refazer'] ?? 0) ? 'checked' : '' ?>
                        class="mr-3 w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    >
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-gray-900 mb-1">
                            Permitir que o aluno refaça esta redação
                        </div>
                        <div class="text-xs text-gray-600">
                            Se marcado, o aluno poderá criar uma nova versão da redação após ver a correção. A redação anterior será mantida no histórico.
                        </div>
                    </div>
                </label>
            </div>
            
            <!-- Botão Salvar -->
            <button 
                type="submit" 
                class="w-full bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl flex items-center justify-center"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Salvar Correção
            </button>
        </div>
    </div>
</form>

<div id="modalCorrecaoIa" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/50" aria-modal="true" role="dialog">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-8 text-center">
        <div class="mx-auto mb-4 w-14 h-14 rounded-full border-4 border-purple-200 border-t-purple-600 animate-spin"></div>
        <h4 class="text-xl font-semibold text-gray-900 mb-2">Tudinha está corrigindo a redação</h4>
        <p class="text-sm text-gray-600">Aguarde um instante. Estamos enviando o texto para análise e preparando a correção com as competências, observações e sugestões.</p>
        <p class="text-xs text-gray-500 mt-4">Esse processo pode levar alguns segundos.</p>
        <pre id="modalCorrecaoIaDebug" class="hidden mt-4 p-3 text-left text-xs bg-gray-100 text-gray-700 rounded-lg overflow-x-auto max-h-40 whitespace-pre-wrap"></pre>
    </div>
</div>

<script>
// Nota da IA (extraída do PHP)
const notaIA = <?= $notaIAFinal ?>;

// Dados da correção da versão anterior (se existir)
const correcaoVersaoAnterior = <?= json_encode($correcao_versao_anterior ?? null) ?>;

function calcularNotaFinal() {
    let soma = 0;
    for (let i = 1; i <= 5; i++) {
        const notaInput = document.getElementById('competencia_' + i);
        const nota = notaInput ? parseInt(notaInput.value) || 0 : 0;
        soma += nota;
    }
    
    // Verificar se deve usar média
    const usarMediaCheckbox = document.getElementById('usarMediaNotas');
    const usarMedia = usarMediaCheckbox && usarMediaCheckbox.checked && notaIA > 0;
    
    if (usarMedia) {
        // Calcular média
        const notaMedia = Math.round((soma + notaIA) / 2);
        
        // Atualizar display da nota final (mostra a média)
        const notaFinalDisplay = document.getElementById('notaFinalDisplay');
        if (notaFinalDisplay) {
            notaFinalDisplay.textContent = notaMedia + '/1000';
        }
        
        // Mostrar campo de nota média
        const campoNotaMedia = document.getElementById('campoNotaMedia');
        const notaMediaDisplay = document.getElementById('notaMediaDisplay');
        if (campoNotaMedia) {
            campoNotaMedia.style.display = 'block';
        }
        if (notaMediaDisplay) {
            notaMediaDisplay.textContent = notaMedia + '/1000';
        }
    } else {
        // Atualizar display da nota final (soma simples)
        const notaFinalDisplay = document.getElementById('notaFinalDisplay');
        if (notaFinalDisplay) {
            notaFinalDisplay.textContent = soma + '/1000';
        }
        
        // Ocultar campo de nota média
        const campoNotaMedia = document.getElementById('campoNotaMedia');
        if (campoNotaMedia) {
            campoNotaMedia.style.display = 'none';
        }
    }
}

function setCorrecaoIaDebug(text) {
    const el = document.getElementById('modalCorrecaoIaDebug');
    if (!el) return;
    if (!text) {
        el.classList.add('hidden');
        el.textContent = '';
        return;
    }
    el.textContent = text;
    el.classList.remove('hidden');
}

function formatCorrecaoIaDebugLog(log) {
    if (!Array.isArray(log) || !log.length) return '';
    return log.map(function (entry, i) {
        return (i + 1) + '. ' + JSON.stringify(entry);
    }).join('\n');
}

function openCorrecaoIaModal() {
    const modal = document.getElementById('modalCorrecaoIa');
    setCorrecaoIaDebug('');
    if (modal) modal.classList.remove('hidden');
}

function closeCorrecaoIaModal() {
    const modal = document.getElementById('modalCorrecaoIa');
    setCorrecaoIaDebug('');
    if (modal) modal.classList.add('hidden');
}

function restaurarBotoesCorrecaoIA(btnIA, btnRefazerIA, textoOriginal, textoOriginalRefazer) {
    if (btnIA) {
        btnIA.disabled = false;
        btnIA.innerHTML = textoOriginal;
    }
    if (btnRefazerIA) {
        btnRefazerIA.disabled = false;
        btnRefazerIA.innerHTML = textoOriginalRefazer;
    }
}

function solicitarCorrecaoIA() {
    const btnIA = document.getElementById('btnCorrigirIA');
    const btnRefazerIA = document.getElementById('btnRefazerCorrecaoIA');
    const redacaoIdInput = document.querySelector('input[name="redacao_id"]');
    const tokenInput = document.querySelector('input[name="_token"]');

    if (!btnIA || !redacaoIdInput || !redacaoIdInput.value || !tokenInput || !tokenInput.value) {
        alert('Erro: dados da redação ou token de segurança não encontrados.');
        return;
    }

    const textoOriginal = btnIA.innerHTML;
    const textoOriginalRefazer = btnRefazerIA ? btnRefazerIA.innerHTML : '';
    const loadingHtml = '<svg class="animate-spin w-4 h-4 inline-block mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Corrigindo...';

    btnIA.disabled = true;
    btnIA.innerHTML = loadingHtml;
    if (btnRefazerIA) {
        btnRefazerIA.disabled = true;
        btnRefazerIA.innerHTML = loadingHtml;
    }
    openCorrecaoIaModal();

    fetch('<?= URL ?>/professor/jornadas/corrigir-redacao-ia', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            redacao_id: redacaoIdInput.value,
            _token: tokenInput.value
        })
    })
    .then(function(response) {
        return response.text().then(function(text) {
            var data = null;
            if (text) {
                try { data = JSON.parse(text); } catch (e) { data = null; }
            }
            return { ok: response.ok, status: response.status, data: data, text: text };
        });
    })
    .then(function(result) {
        if (!result.ok || !result.data || !result.data.job_id) {
            closeCorrecaoIaModal();
            restaurarBotoesCorrecaoIA(btnIA, btnRefazerIA, textoOriginal, textoOriginalRefazer);
            var msg = (result.data && result.data.error) ? result.data.error : ('Erro HTTP ' + result.status);
            if (result.data && result.data.debug) {
                msg += '\n\nDebug: ' + JSON.stringify(result.data.debug, null, 2);
            } else if (result.text) {
                msg += '\n\nResposta: ' + result.text.substring(0, 300);
            }
            console.error('[corrigir-redacao-ia] enqueue falhou', result);
            alert('Erro: ' + msg);
            return;
        }

        console.log('[corrigir-redacao-ia] enqueue ok', result.data);
        setCorrecaoIaDebug(
            'Job #' + result.data.job_id + '\n' +
            'Status URL: ' + (result.data.status_url || '(não informada)') + '\n' +
            (result.data.debug ? ('Debug: ' + JSON.stringify(result.data.debug)) : '')
        );

        if (typeof AIJobPoller === 'undefined') {
            closeCorrecaoIaModal();
            restaurarBotoesCorrecaoIA(btnIA, btnRefazerIA, textoOriginal, textoOriginalRefazer);
            alert('Erro: componente de acompanhamento da correção não carregou. Recarregue a página.');
            return;
        }

        new AIJobPoller(result.data.job_id, {
            debug: true,
            statusUrl: result.data.status_url || '<?= URL ?>/professor/jornadas/corrigir-redacao-ia/status/{id}',
            statusUrls: [
                '<?= URL ?>/professor/jornadas/corrigir-redacao-ia/status/{id}',
                '<?= URL ?>/professor/jornadas/ai-job/{id}/status',
                '<?= URL ?>/ai-job/{id}/status'
            ],
            onDebug: function(entry) {
                if (entry.type === 'fetch' || entry.type === 'response' || entry.type === '404_fallback' || entry.type === 'error') {
                    setCorrecaoIaDebug('Polling job #' + result.data.job_id + '...\n' + entry.type + ': ' + (entry.url || JSON.stringify(entry)));
                }
            },
            onDone: function() {
                closeCorrecaoIaModal();
                window.location.reload();
            },
            onFailed: function(err, debugLog) {
                closeCorrecaoIaModal();
                restaurarBotoesCorrecaoIA(btnIA, btnRefazerIA, textoOriginal, textoOriginalRefazer);
                var detail = err;
                if (debugLog && debugLog.length) {
                    detail += '\n\n--- Diagnóstico ---\n' + formatCorrecaoIaDebugLog(debugLog);
                }
                console.error('[corrigir-redacao-ia] polling falhou', { err: err, debugLog: debugLog });
                alert('Falha na correção por IA:\n\n' + detail);
            }
        });
    })
    .catch(function(error) {
        closeCorrecaoIaModal();
        restaurarBotoesCorrecaoIA(btnIA, btnRefazerIA, textoOriginal, textoOriginalRefazer);
        alert('Erro ao processar solicitação: ' + (error && error.message ? error.message : 'falha desconhecida'));
    });
}

function copiarFeedbackIAParaProfessor(competencia) {
    const origem = document.getElementById('explicacao_ia_' + competencia);
    const destino = document.getElementById('explicacao_' + competencia);
    const msg = document.getElementById('copyFeedbackMsg_' + competencia);
    if (!origem || !destino) return;
    destino.value = origem.value || '';
    destino.focus();
    if (msg) {
        msg.classList.remove('hidden');
        setTimeout(function() {
            msg.classList.add('hidden');
        }, 1800);
    }
}

    // Calcular ao carregar
    document.addEventListener('DOMContentLoaded', function() {
        calcularNotaFinal();
        
        // Validar formulário antes de enviar
        document.getElementById('formCorrigirRedacao').addEventListener('submit', function(e) {
            let todasPreenchidas = true;
            let primeiraNotaInvalida = null;
            
            for (let i = 1; i <= 5; i++) {
                const notaInput = document.getElementById('competencia_' + i);
                const nota = notaInput ? parseInt(notaInput.value) : null;
                
                if (nota === null || isNaN(nota) || nota < 0 || nota > 200) {
                    todasPreenchidas = false;
                    if (!primeiraNotaInvalida) {
                        primeiraNotaInvalida = i;
                    }
                }
            }
            
            if (!todasPreenchidas) {
                e.preventDefault();
                alert('Por favor, preencha todas as competências com notas válidas entre 0 e 200.');
                if (primeiraNotaInvalida) {
                    const input = document.getElementById('competencia_' + primeiraNotaInvalida);
                    if (input) {
                        input.focus();
                    }
                }
                return false;
            }
            
            // Se tudo estiver válido, permitir o submit normal
            console.log('Formulário válido, enviando...');
            
            // Mostrar loading
            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<svg class="animate-spin w-5 h-5 inline-block mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Salvando...';
            }
        });
    });

// Função para usar correção da versão anterior
function usarCorrecaoVersaoAnterior() {
    if (!correcaoVersaoAnterior) {
        alert('Não há correção da versão anterior disponível.');
        return;
    }
    
    const versaoAnteriorNum = <?= $versao_anterior_numero ?? 1 ?>;
    if (!confirm('Deseja preencher os campos com a correção da versão ' + versaoAnteriorNum + '? Os campos atuais serão substituídos.')) {
        return;
    }
    
    // Preencher notas das competências
    for (let i = 1; i <= 5; i++) {
        const notaInput = document.getElementById('competencia_' + i);
        const explicacaoInput = document.getElementById('explicacao_' + i);
        
        if (notaInput && correcaoVersaoAnterior['competencia_' + i] !== null) {
            notaInput.value = correcaoVersaoAnterior['competencia_' + i] || '';
        }
        
        if (explicacaoInput && correcaoVersaoAnterior['competencia_' + i + '_explicacao_professor']) {
            explicacaoInput.value = correcaoVersaoAnterior['competencia_' + i + '_explicacao_professor'] || '';
        }
    }
    
    // Preencher comentários gerais
    const comentariosInput = document.getElementById('comentarios_gerais');
    if (comentariosInput && correcaoVersaoAnterior.comentarios_gerais_professor) {
        comentariosInput.value = correcaoVersaoAnterior.comentarios_gerais_professor || '';
    }
    
    // Preencher sugestões de melhoria
    const sugestoesInput = document.getElementById('sugestoes_melhoria');
    if (sugestoesInput && correcaoVersaoAnterior.sugestoes_melhoria_professor) {
        sugestoesInput.value = correcaoVersaoAnterior.sugestoes_melhoria_professor || '';
    }
    
    // Recalcular nota final
    calcularNotaFinal();
    
    // Mostrar mensagem de sucesso
    alert('Campos preenchidos com a correção da versão ' + versaoAnteriorNum + '. Você pode ajustar o que for necessário.');
}

</script>

<?php include __DIR__ . '/../../components/ai-job-poller.php'; ?>
