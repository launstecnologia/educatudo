
<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Editar Prova: <?= htmlspecialchars($prova['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Edite as informações da prova e gerencie as questões
            </p>
            <?php if (!empty($prova['observacao_coordenacao'])): ?>
            <span class="inline-flex items-center mt-2 px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800 border border-amber-300">
                ↩️ Retornada ao professor
            </span>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <?php if (!empty($is_admin_edit)): ?>
                <span class="bg-indigo-100 text-indigo-800 px-6 py-3 rounded-xl font-medium">Edição pela coordenação — use a tela “Ver prova” para Aprovar ou Retornar ao professor.</span>
            <?php elseif (in_array($prova['status'] ?? 'rascunho', ['rascunho', 'agendada', 'reprovada', 'pendente'])): ?>
                <button onclick="finalizarProva()"
                        class="bg-green-600 text-white px-6 py-3 rounded-xl hover:bg-green-700 transition-colors">
                    ✅ Enviar para Aprovação
                </button>
            <?php elseif ($prova['status'] === 'enviada'): ?>
                <span class="bg-yellow-500 text-white px-6 py-3 rounded-xl">
                    ⏳ Aguardando Aprovação da Coordenação
                </span>
            <?php elseif ($prova['status'] === 'aprovada'): ?>
                <span class="bg-green-500 text-white px-6 py-3 rounded-xl">
                    ✅ Prova Aprovada
                </span>
            <?php endif; ?>
            <?php
            $totalQuestoesHeader = isset($questoes) && is_array($questoes) ? count($questoes) : 0;
            $mostrarPreviewHeader = !empty($numero_questoes_obrigatorio) && (int)$numero_questoes_obrigatorio > 0 && $totalQuestoesHeader === (int)$numero_questoes_obrigatorio;
            if ($mostrarPreviewHeader): ?>
            <a href="<?= URL ?>/professor/provas/preview/<?= (int)($prova['id'] ?? 0) ?>" target="_blank" rel="noopener" class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-colors inline-flex items-center">
                👁 Visualizar Prova
            </a>
            <?php endif; ?>
            <a href="<?= !empty($voltar_url) ? htmlspecialchars($voltar_url) : (URL . '/professor/provas') ?>" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700">
                Voltar
            </a>
        </div>
    </div>
</div>

<?php if (!empty($prova['observacao_coordenacao'])): ?>
<!-- Observações da coordenação (prova retornada) -->
<div class="bg-amber-50 border-2 border-amber-300 rounded-xl p-6 mb-6">
    <h3 class="text-lg font-semibold text-amber-900 mb-2 flex items-center">
        ↩️ Retornada ao professor
        <?php if (!empty($prova['observacao_coordenacao_data'])): ?>
        <span class="ml-2 text-sm font-normal text-amber-700">
            (em <?= date('d/m/Y \à\s H:i', strtotime($prova['observacao_coordenacao_data'])) ?>)
        </span>
        <?php endif; ?>
    </h3>
    <p class="text-sm font-medium text-amber-800 mb-2">O que a coordenação pediu para alterar:</p>
    <div class="bg-white border border-amber-200 rounded-lg p-4 text-gray-800 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($prova['observacao_coordenacao'])) ?></div>
</div>
<?php endif; ?>

<!-- Informações da Prova -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações da Prova</h3>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Matéria</label>
                <div class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                    <?php 
                    $materiaNome = '';
                    foreach ($materias as $materia) {
                        if ($materia['id'] == $prova['materia_id']) {
                            $materiaNome = htmlspecialchars($materia['nome']);
                            break;
                        }
                    }
                    echo $materiaNome ?: 'Não definida';
                    ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Título</label>
                <div class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                    <?= htmlspecialchars($prova['titulo']) ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Data/Hora Início</label>
                <div class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                    <?= date('d/m/Y H:i', strtotime($prova['data_inicio'])) ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Data/Hora Término</label>
                <div class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-700">
                    <?= date('d/m/Y H:i', strtotime($prova['data_fim'])) ?>
                </div>
            </div>
            <?php if (!empty($evento) && !empty($evento['prazo_entrega_professor'])): ?>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Prazo para Envio da Prova 
                    <span class="text-orange-600 text-xs">(Definido pelo Administrador)</span>
                </label>
                <div class="w-full px-4 py-3 border-2 border-orange-400 rounded-lg bg-orange-50 text-gray-900 font-semibold">
                    <?= date('d/m/Y H:i', strtotime($evento['prazo_entrega_professor'])) ?>
                </div>
                <p class="text-xs text-orange-600 mt-1 font-medium">
                    ⚠️ Este prazo foi definido pelo administrador no bloco de provas. 
                    Após este prazo, provas não enviadas serão automaticamente marcadas como "Não Enviadas".
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($numero_questoes_obrigatorio) && $numero_questoes_obrigatorio > 0): ?>
<?php $totalAtual = isset($questoes) && is_array($questoes) ? count($questoes) : 0; ?>
<div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
    <p class="text-sm font-semibold text-blue-900">
        📋 Quantidade de questões (bloco): 
        <span id="numero-questoes-atual"><?= $totalAtual ?></span> de <strong><?= $numero_questoes_obrigatorio ?></strong> obrigatórias.
    </p>
    <p class="text-xs text-blue-700 mt-1">
        Ao finalizar, a prova deve ter exatamente <strong><?= $numero_questoes_obrigatorio ?></strong> <?= (int)$numero_questoes_obrigatorio === 1 ? 'questão' : 'questões' ?> (incluindo questões feitas à mão e por IA).
        <?php if ($totalAtual < $numero_questoes_obrigatorio): ?>
            <span class="font-semibold">Faltam <?= $numero_questoes_obrigatorio - $totalAtual ?>.</span>
        <?php elseif ($totalAtual > $numero_questoes_obrigatorio): ?>
            <span class="font-semibold">Há <?= $totalAtual - $numero_questoes_obrigatorio ?> a mais; remova para poder finalizar.</span>
        <?php else: ?>
            <span class="font-semibold text-green-700">Quantidade correta.</span>
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<!-- Formulário de Criar Questão Manualmente -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-blue-200">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Criar Questão Manualmente</h3>
        <div class="flex flex-wrap items-center gap-3">
            <?php
            if (!class_exists('CreditosModuleRegistry', false)) {
                require_once __DIR__ . '/../../../Core/CreditosModuleRegistry.php';
            }
            $tudicoinsProvaIa = \CreditosModuleRegistry::acaoIaDisponivel('gerar_exercicio_ia_professor');
            ?>
            <?php if ($tudicoinsProvaIa): ?>
            <button onclick="abrirModalLerImagem()" class="px-6 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 transition-colors">
                📷 Ler Imagem
            </button>
            <button onclick="abrirModalGerarIA()" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors">
                🤖 Gerar com IA
            </button>
            <?php endif; ?>
            <button onclick="abrirModalBancoQuestoes()" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                Banco de Questões
            </button>
            <button onclick="abrirModalColarJson()" class="px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition-colors">
                📚 Importar da Apostila
            </button>
        </div>
    </div>

    <!-- Form Manual -->
    <div id="form-manual" class="questao-form">
        <form id="adicionarQuestaoForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Questão *</label>
                    <select name="tipo" id="tipo-questao" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="multipla_escolha">Múltipla Escolha</option>
                        <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                        <option value="dissertativa">Dissertativa</option>
                    </select>
                </div>
                
                <?php if (empty($evento) || $evento['configuracao_nota'] !== 'coordenacao_calcula'): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Pontuação <?php if (!empty($evento) && $evento['configuracao_nota'] === 'professor_por_questao'): ?><span class="text-red-500">*</span><?php endif; ?>
                    </label>
                    <input type="number" name="valor" id="valor-questao" step="0.1" min="0" value="1.00" 
                           <?php if (!empty($evento) && $evento['configuracao_nota'] === 'professor_por_questao'): ?>required<?php endif; ?>
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                           <?php if (!empty($evento) && $evento['configuracao_nota'] === 'professor_por_questao'): ?>border-blue-500 bg-blue-50<?php endif; ?>">
                    <?php if (!empty($evento) && $evento['configuracao_nota'] === 'professor_por_questao'): ?>
                        <p class="text-xs text-blue-600 mt-1">Você deve definir a pontuação de cada questão</p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <input type="hidden" name="valor" id="valor-questao" value="0">
                <?php endif; ?>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Enunciado *</label>
                <input type="hidden" name="enunciado" id="enunciado-questao" required>
                <div class="editor-toolbar">
                    <button type="button" class="tb-btn" title="Negrito" onmousedown="event.preventDefault()" onclick="fmtEnunciado('bold')"><b>B</b></button>
                    <button type="button" class="tb-btn" title="Itálico" onmousedown="event.preventDefault()" onclick="fmtEnunciado('italic')"><i>I</i></button>
                    <button type="button" class="tb-btn" title="Sublinhado" onmousedown="event.preventDefault()" onclick="fmtEnunciado('underline')"><u>U</u></button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn" title="Lista com marcadores" onmousedown="event.preventDefault();saveSelEnunciado();fmtEnunciado('insertUnorderedList')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/></svg>
                    </button>
                    <button type="button" class="tb-btn" title="Lista numerada" onmousedown="event.preventDefault();saveSelEnunciado();fmtEnunciado('insertOrderedList')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4M4 10h2" stroke-linecap="round"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1" stroke-linecap="round"/></svg>
                    </button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn tb-font-size" title="Diminuir letra" onmousedown="event.preventDefault();fmtFontSize('editor-enunciado', -1)"><span>A−</span></button>
                    <button type="button" class="tb-btn tb-font-size" title="Aumentar letra" onmousedown="event.preventDefault();fmtFontSize('editor-enunciado', 1)"><span>A+</span></button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn" title="Alinhar esquerda" onmousedown="event.preventDefault()" onclick="fmtEnunciado('justifyLeft')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
                    </button>
                    <button type="button" class="tb-btn" title="Centralizar" onmousedown="event.preventDefault()" onclick="fmtEnunciado('justifyCenter')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                    </button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-math-btn" data-math-open="editor-enunciado" onmousedown="event.preventDefault()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 7h16M4 12h10M4 17h7"/><path d="M18 14l2 2-2 2M22 14l-2 2 2 2"/></svg>
                        ∑ Inserir Equação
                    </button>
                    <div class="tb-sep"></div>
                    <input type="file" id="enunciado-insert-image-input" accept="image/*" class="hidden">
                    <button type="button" class="tb-math-btn tb-img-btn" title="Inserir ou colar imagem (Ctrl+V)" onclick="document.getElementById('enunciado-insert-image-input').click()">
                        <svg class="tb-img-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        Imagem
                    </button>
                </div>
                <div id="editor-enunciado" class="rich-editor" contenteditable="true" data-math="inline"
                     data-placeholder="Digite o enunciado aqui... Use ∑ Inserir Equação para adicionar fórmulas visuais."></div>
            </div>
            <input type="hidden" id="imagem-url" name="imagem_url">
            <div id="opcoes-container" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Alternativas (Múltipla Escolha) *</label>
                <input type="file" id="alt-insert-image-input" accept="image/*" class="hidden">
                <div id="opcoes-lista" class="alt-list mb-3"></div>
                <div class="flex flex-wrap items-center gap-4 mt-2">
                    <button type="button" onclick="adicionarOpcao()" class="px-4 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors">
                        + Adicionar Alternativa
                    </button>
                    <span class="text-xs text-gray-500 flex-shrink-0">Máximo 5 alternativas (A, B, C, D, E)</span>
                </div>
            </div>
            
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                Adicionar Questão
            </button>
        </form>
    </div>

</div>

<!-- Modal Importar da Apostila -->
<div id="modal-colar-json" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Importar exercícios da IA da Apostila</h3>
            <button onclick="fecharModalColarJson()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>

        <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Apostila</label>
            <select id="apostila-select" class="w-full text-sm border border-gray-300 rounded-lg p-2 bg-white">
                <option value="">Carregando apostilas...</option>
            </select>
        </div>

        <div id="apostila-filtros" class="hidden grid grid-cols-1 md:grid-cols-3 gap-2 mb-3">
            <input type="text" id="apostila-filtro-busca" placeholder="Buscar por tema/enunciado..." class="text-sm border border-gray-300 rounded-lg p-2">
            <select id="apostila-filtro-tipo" class="text-sm border border-gray-300 rounded-lg p-2 bg-white">
                <option value="">Todos os tipos</option>
                <option value="objetiva">Objetiva</option>
                <option value="discursiva">Discursiva</option>
                <option value="verdadeiro_falso">Verdadeiro/Falso</option>
            </select>
            <select id="apostila-filtro-dificuldade" class="text-sm border border-gray-300 rounded-lg p-2 bg-white">
                <option value="">Qualquer dificuldade</option>
                <option value="facil">Fácil</option>
                <option value="media">Média</option>
                <option value="dificil">Difícil</option>
            </select>
        </div>

        <p id="apostila-status" class="text-sm text-gray-500 mb-2"></p>

        <div id="apostila-exercicios-lista" class="space-y-2 max-h-80 overflow-y-auto border border-gray-100 rounded-lg p-2"></div>

        <p id="colar-json-resultado" class="text-sm text-gray-500 mt-2"></p>

        <div class="flex justify-between items-center mt-4">
            <span id="apostila-selecionados-count" class="text-sm text-gray-600"></span>
            <div class="flex gap-3">
                <button type="button" onclick="fecharModalColarJson()" class="px-4 py-2 text-gray-600 hover:text-gray-900">Cancelar</button>
                <button type="button" onclick="importarSelecionados()" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Importar selecionados</button>
            </div>
        </div>

        <details class="mt-4">
            <summary class="text-sm text-gray-500 cursor-pointer">Opção avançada: colar JSON manualmente</summary>
            <textarea id="colar-json-textarea" rows="6" class="w-full text-sm border border-gray-300 rounded-lg p-3 font-mono mt-2" placeholder='[{"enunciado": "...", "tipo": "objetiva", "alternativas": ["...", "..."], "gabarito": "..."}]'></textarea>
            <button type="button" onclick="importarQuestoesJson()" class="mt-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">Importar JSON colado</button>
        </details>
    </div>
</div>

<!-- Modal Editar Questão -->
<div id="modal-editar-questao" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-900">Editar Questão</h3>
            <button onclick="fecharModalEditar()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form id="editarQuestaoForm" class="space-y-4">
            <input type="hidden" id="edit-questao-id" name="questao_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Questão *</label>
                    <select name="tipo" id="edit-tipo-questao" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="multipla_escolha">Múltipla Escolha</option>
                        <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                        <option value="dissertativa">Dissertativa</option>
                    </select>
                </div>
                
                <?php if (empty($evento) || $evento['configuracao_nota'] !== 'coordenacao_calcula'): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Pontuação <?php if (!empty($evento) && $evento['configuracao_nota'] === 'professor_por_questao'): ?><span class="text-red-500">*</span><?php endif; ?>
                    </label>
                    <input type="number" name="valor" id="edit-valor" step="0.1" min="0" value="1.00" 
                           <?php if (!empty($evento) && $evento['configuracao_nota'] === 'professor_por_questao'): ?>required<?php endif; ?>
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                           <?php if (!empty($evento) && $evento['configuracao_nota'] === 'professor_por_questao'): ?>border-blue-500 bg-blue-50<?php endif; ?>">
                    <?php if (!empty($evento) && $evento['configuracao_nota'] === 'professor_por_questao'): ?>
                        <p class="text-xs text-blue-600 mt-1">Você deve definir a pontuação de cada questão</p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <input type="hidden" name="valor" id="edit-valor" value="0">
                <?php endif; ?>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Enunciado *</label>
                <input type="hidden" name="enunciado" id="edit-enunciado-value">
                <div class="editor-toolbar">
                    <button type="button" class="tb-btn" title="Negrito" onmousedown="event.preventDefault()" onclick="fmtEditEnunciado('bold')"><b>B</b></button>
                    <button type="button" class="tb-btn" title="Itálico" onmousedown="event.preventDefault()" onclick="fmtEditEnunciado('italic')"><i>I</i></button>
                    <button type="button" class="tb-btn" title="Sublinhado" onmousedown="event.preventDefault()" onclick="fmtEditEnunciado('underline')"><u>U</u></button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn" title="Lista com marcadores" onmousedown="event.preventDefault();saveSelEditEnunciado();fmtEditEnunciado('insertUnorderedList')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/></svg>
                    </button>
                    <button type="button" class="tb-btn" title="Lista numerada" onmousedown="event.preventDefault();saveSelEditEnunciado();fmtEditEnunciado('insertOrderedList')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4M4 10h2" stroke-linecap="round"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1" stroke-linecap="round"/></svg>
                    </button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn tb-font-size" title="Diminuir letra" onmousedown="event.preventDefault();fmtFontSize('edit-enunciado', -1)"><span>A−</span></button>
                    <button type="button" class="tb-btn tb-font-size" title="Aumentar letra" onmousedown="event.preventDefault();fmtFontSize('edit-enunciado', 1)"><span>A+</span></button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn" title="Alinhar esquerda" onmousedown="event.preventDefault()" onclick="fmtEditEnunciado('justifyLeft')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
                    </button>
                    <button type="button" class="tb-btn" title="Centralizar" onmousedown="event.preventDefault()" onclick="fmtEditEnunciado('justifyCenter')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                    </button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-math-btn" data-math-open="edit-enunciado" onmousedown="event.preventDefault()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 7h16M4 12h10M4 17h7"/><path d="M18 14l2 2-2 2M22 14l-2 2 2 2"/></svg>
                        ∑ Inserir Equação
                    </button>
                    <div class="tb-sep"></div>
                    <input type="file" id="edit-enunciado-insert-image-input" accept="image/*" class="hidden">
                    <button type="button" class="tb-math-btn tb-img-btn" title="Inserir ou colar imagem (Ctrl+V)" onclick="document.getElementById('edit-enunciado-insert-image-input').click()">
                        <svg class="tb-img-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        Imagem
                    </button>
                </div>
                <div id="edit-enunciado" class="rich-editor rich-editor-tall" contenteditable="true" data-math="inline"
                     data-placeholder="Digite o enunciado aqui... Use ∑ Inserir Equação para adicionar fórmulas visuais."></div>
            </div>
            <input type="hidden" id="edit-imagem-url" name="imagem_url">
            <div id="edit-imagem-preview-container" class="hidden mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Imagem da Questão</label>
                <div class="relative inline-block">
                    <img id="edit-imagem-preview" src="" alt="Imagem da questão" 
                         class="max-w-full max-h-64 rounded-lg border border-gray-300 shadow-sm cursor-pointer"
                         onclick="this.classList.toggle('max-h-64'); this.classList.toggle('max-h-none');">
                    <button type="button" onclick="removerImagemEdicao()" 
                            class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-7 h-7 flex items-center justify-center text-sm hover:bg-red-600 shadow-md"
                            title="Remover imagem">&times;</button>
                </div>
            </div>
            <div id="edit-opcoes-container" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Alternativas (Múltipla Escolha) *</label>
                <div id="edit-opcoes-lista" class="space-y-3 mb-3"></div>
                <div class="flex flex-wrap items-center gap-4 mt-2">
                    <button type="button" onclick="adicionarOpcaoEdicao()" class="px-4 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors">
                        + Adicionar Alternativa
                    </button>
                    <span class="text-xs text-gray-500 flex-shrink-0">Máximo 5 alternativas (A, B, C, D, E)</span>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <button type="button" onclick="fecharModalEditar()" 
                        class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    Cancelar
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Gerar Questões com IA -->
<div id="modal-gerar-ia" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-900">🤖 Gerar Questões com IA</h3>
            <button onclick="fecharModalGerarIA()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form id="gerarQuestoesIAForm" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Vincular Planos de Aula (Opcional - para gerar questões baseadas nos objetivos)
                </label>
                <div class="border border-gray-300 rounded-lg p-4 max-h-60 overflow-y-auto">
                    <?php if (!empty($planosAula)): ?>
                        <div class="space-y-2">
                            <?php foreach ($planosAula as $plano): ?>
                                <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                                    <input type="checkbox" 
                                           name="planos_aula_id[]" 
                                           value="<?= htmlspecialchars($plano['id']) ?>" 
                                           class="plano-aula-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                           data-plano-id="<?= htmlspecialchars($plano['id']) ?>">
                                    <span class="text-sm text-gray-700">
                                        <?= htmlspecialchars($plano['titulo']) ?> - 
                                        <?= htmlspecialchars($plano['materia_nome'] ?? '') ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">Nenhum plano de aula disponível</p>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Selecione um ou mais planos de aula. Os objetivos serão automaticamente adicionados ao contexto adicional abaixo.
                </p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contexto Adicional (Opcional)</label>
                <textarea name="contexto" id="contexto-adicional-ia" rows="4"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Os objetivos dos planos de aula selecionados aparecerão aqui automaticamente. Você pode adicionar informações adicionais se desejar..."></textarea>
                <p class="text-xs text-gray-500 mt-1">
                    Este campo será preenchido automaticamente com os objetivos dos planos de aula selecionados. Você pode editar ou adicionar informações adicionais.
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Questão *</label>
                    <select name="tipo" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="alternativas">Múltipla Escolha</option>
                        <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                        <option value="dissertativa">Dissertativa</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Série / Ano</label>
                    <select name="serie" id="serie-ia"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Detectar automaticamente</option>
                        <optgroup label="Ensino Fundamental II">
                            <option value="6º ano">6º ano</option>
                            <option value="7º ano">7º ano</option>
                            <option value="8º ano">8º ano</option>
                            <option value="9º ano">9º ano</option>
                        </optgroup>
                        <optgroup label="Ensino Médio">
                            <option value="1ª série EM">1ª série EM</option>
                            <option value="2ª série EM">2ª série EM</option>
                            <option value="3ª série EM">3ª série EM</option>
                        </optgroup>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">A IA adapta linguagem e complexidade conforme a série.</p>
                </div>
            </div>

            <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="com_imagens" id="com-imagens-ia" value="1" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                </label>
                <div>
                    <span class="text-sm font-medium text-gray-800">Gerar questões com imagens</span>
                    <p class="text-xs text-gray-500">Gráficos, figuras geométricas, diagramas e ilustrações quando o conteúdo se beneficiar</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Níveis de Dificuldade e Quantidades *</label>
                <div class="space-y-3">
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-700 font-medium min-w-[70px]">Fácil:</span>
                        <input type="number" 
                               name="quantidade_facil" 
                               id="quantidade-facil"
                               min="0" 
                               max="20" 
                               value="0"
                               class="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm text-gray-500">questões</span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-700 font-medium min-w-[70px]">Médio:</span>
                        <input type="number" 
                               name="quantidade_medio" 
                               id="quantidade-medio"
                               min="0" 
                               max="20" 
                               value="0"
                               class="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm text-gray-500">questões</span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-700 font-medium min-w-[70px]">Difícil:</span>
                        <input type="number" 
                               name="quantidade_dificil" 
                               id="quantidade-dificil"
                               min="0" 
                               max="20" 
                               value="0"
                               class="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm text-gray-500">questões</span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-700 font-medium min-w-[70px]">Desafio:</span>
                        <input type="number" 
                               name="quantidade_desafio" 
                               id="quantidade-desafio"
                               min="0" 
                               max="20" 
                               value="0"
                               class="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm text-gray-500">questões</span>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    Informe a quantidade desejada para cada nível. Total = soma de todas as questões.
                </p>
                <div class="text-xs text-gray-600 mt-2 space-y-1 border-l-2 border-gray-200 pl-3">
                    <p><strong>Fácil:</strong> Memorização e compreensão. Perguntas diretas.</p>
                    <p><strong>Médio:</strong> Aplicação. Situações-problema.</p>
                    <p><strong>Difícil:</strong> Análise e avaliação. Múltiplos conceitos; distratores plausíveis.</p>
                    <p><strong>Desafio:</strong> Estilo vestibular/concurso (ENEM, FUVEST, ITA). Questões complexas, enunciados longos e interdisciplinares.</p>
                </div>
            </div>
            
            
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <button type="button" onclick="fecharModalGerarIA()" 
                        class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    Cancelar
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    🤖 Gerar Questões
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ler Imagem -->
<div id="modal-ler-imagem" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-900">📷 Ler Questão de Imagem</h3>
            <button onclick="fecharModalLerImagem()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form id="lerImagemForm" class="space-y-6" enctype="multipart/form-data">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Envie uma imagem com a questão (PNG, JPG, JPEG)
                </label>
                <div id="upload-area" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-green-500 transition-colors cursor-pointer">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600">
                            <label for="imagem-questao-ler" class="relative cursor-pointer bg-white rounded-md font-medium text-green-600 hover:text-green-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-green-500">
                                <span>Selecione uma imagem</span>
                                <input id="imagem-questao-ler" name="imagem" type="file" accept="image/png,image/jpeg,image/jpg" class="sr-only">
                            </label>
                            <p class="pl-1">ou arraste e solte</p>
                        </div>
                        <p class="text-xs text-gray-500">PNG, JPG, JPEG até 10MB | Ou cole com Ctrl+V</p>
                    </div>
                </div>
                <div id="preview-imagem-ler" class="mt-4 hidden">
                    <img id="preview-img-ler" src="" alt="Preview" class="max-w-full max-h-64 rounded-lg border border-gray-300">
                    <button type="button" onclick="removerImagemLer()" class="mt-2 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors text-sm">
                        Remover Imagem
                    </button>
                </div>
                <input type="hidden" id="imagem-data-ler" name="imagem_data">
            </div>
            
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <button type="button" onclick="fecharModalLerImagem()" 
                        class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    Cancelar
                </button>
                <button type="submit" id="btn-ler-imagem" 
                        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    📷 Ler e Extrair Questão
                </button>
            </div>
        </form>
        
        <div id="loading-ler-imagem" class="hidden mt-4">
            <div class="flex items-center justify-center space-x-2 text-green-600">
                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Processando imagem e extraindo questão...</span>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Questões -->
<div class="bg-white rounded-xl shadow-lg p-6 border border-blue-200">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Questões da Prova (<?= count($questoes) ?>)</h3>
    </div>
    
    <div id="questoesList" class="space-y-4">
        <?php if (empty($questoes)): ?>
            <div class="text-center py-8 text-gray-500">
                <p class="mb-2">Nenhuma questão adicionada ainda</p>
                <p class="text-sm text-gray-400">Crie questões manualmente ou gere com IA usando os formulários acima</p>
            </div>
        <?php else: ?>
            <?php foreach ($questoes as $index => $questao): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors" data-questao-id="<?= $questao['id'] ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center mb-2 flex-wrap gap-2">
                                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                    Questão <?= $index + 1 ?>
                                </span>
                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded">
                                    <?php
                                    $tipos = [
                                        'multipla_escolha' => 'Múltipla Escolha',
                                        'verdadeiro_falso' => 'Verdadeiro/Falso',
                                        'dissertativa' => 'Dissertativa'
                                    ];
                                    echo $tipos[$questao['tipo']] ?? $questao['tipo'];
                                    ?>
                                </span>
                                <?php if (!empty($questao['nivel_dificuldade'])): ?>
                                    <?php
                                    $nivel = $questao['nivel_dificuldade'];
                                    $labelsNivel = ['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil', 'desafio' => 'Desafio'];
                                    $nivelLabel = $labelsNivel[$nivel] ?? $nivel;
                                    $coresNivel = [
                                        'Fácil' => 'bg-green-100 text-green-800', 'facil' => 'bg-green-100 text-green-800',
                                        'Médio' => 'bg-yellow-100 text-yellow-800', 'medio' => 'bg-yellow-100 text-yellow-800',
                                        'Difícil' => 'bg-red-100 text-red-800', 'dificil' => 'bg-red-100 text-red-800',
                                        'Desafio' => 'bg-purple-100 text-purple-800', 'desafio' => 'bg-purple-100 text-purple-800'
                                    ];
                                    $corNivel = $coresNivel[$nivel] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs <?= $corNivel ?> rounded font-medium">
                                        <?= htmlspecialchars($nivelLabel) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">
                                    <?php if (empty($evento) || $evento['configuracao_nota'] !== 'coordenacao_calcula'): ?>
                                        <?= number_format($questao['valor'], 2, ',', '.') ?> pontos
                                    <?php else: ?>
                                        Pontuação definida pela coordenação
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php
                            $enunc = $questao['enunciado'] ?? '';
                            $enunc_texto = trim(strip_tags($enunc));
                            $enunc_preview = mb_substr($enunc_texto, 0, 80);
                            if (mb_strlen($enunc_texto) > 80) $enunc_preview .= '...';
                            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $enunc, $m_img);
                            $enunc_img_src = isset($m_img[1]) ? $m_img[1] : ($questao['imagem_url'] ?? null);
                            ?>
                            <h4 class="font-semibold text-gray-900 mb-2"><?= htmlspecialchars($enunc_preview) ?></h4>
                            <?php if ($enunc_img_src): ?>
                                <img src="<?= htmlspecialchars($enunc_img_src) ?>" alt="" class="questao-card-img-preview rounded border border-gray-300 mt-1">
                            <?php endif; ?>
                        </div>
                        <div class="flex space-x-2 ml-4">
                            <button onclick="editarQuestao(<?= $questao['id'] ?>)" 
                                    class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors">
                                Editar
                            </button>
                            <button onclick="removerQuestao(<?= $questao['id'] ?>)" 
                                    class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">
                                Remover
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Banco de Questões -->
<div id="modal-banco-questoes" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-6xl w-full mx-4 max-h-[92vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-xl font-bold text-gray-900">Banco de Questões</h3>
            <button onclick="fecharModalBancoQuestoes()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <input id="bq-q" type="text" placeholder="Buscar por texto ou ID" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <select id="bq-materia" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
            <select id="bq-tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
            <input id="bq-ano" type="text" placeholder="Ano (ex: 2025)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <select id="bq-dificuldade" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
            <select id="bq-topico" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
            <select id="bq-tag" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
            <select id="bq-origem" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
        </div>
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button type="button" onclick="buscarBancoQuestoes(true)" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Buscar</button>
            <button type="button" onclick="limparFiltrosBancoQuestoes()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Limpar Filtros</button>
            <span id="bq-total-info" class="text-sm text-gray-600"></span>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 min-w-0">
                <div id="bq-loading" class="hidden text-sm text-indigo-700 mb-2">Carregando questões...</div>
                <div id="bq-lista" class="space-y-3 max-h-[48vh] overflow-y-auto pr-1"></div>
            </div>
            <aside class="lg:col-span-1 border border-gray-200 rounded-lg p-3 bg-gray-50">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-gray-800">Selecionadas</h4>
                    <span id="bq-selecionadas" class="text-xs px-2 py-1 rounded bg-indigo-100 text-indigo-700">0</span>
                </div>
                <div id="bq-selecionadas-lista" class="space-y-2 max-h-[42vh] overflow-y-auto pr-1 text-sm text-gray-700">
                    <p class="text-xs text-gray-500">Nenhuma questão selecionada.</p>
                </div>
            </aside>
        </div>
        <div class="flex items-center justify-between mt-4">
            <div class="flex items-center gap-2">
                <button type="button" id="bq-prev" onclick="mudarPaginaBancoQuestoes(-1)" class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50">Anterior</button>
                <button type="button" id="bq-next" onclick="mudarPaginaBancoQuestoes(1)" class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50">Próxima</button>
            </div>
            <div class="text-sm text-gray-600">Selecione as questões e clique em OK para importar.</div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <button type="button" onclick="importarSelecionadasBancoQuestoes()" class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">OK (Importar)</button>
            <button type="button" onclick="fecharModalBancoQuestoes()" class="px-5 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">Fechar</button>
        </div>
    </div>
</div>

<script>
let opcoesCount = 0;
let opcoesCountEdicao = 0;
const letras = ['A', 'B', 'C', 'D', 'E'];
let imagemUrlAtual = null;
let imagemUrlEdicao = null;
let questaoAtualEdicao = null;

// Dados das questões com alternativas (passados do PHP)
const questoesData = <?= json_encode(array_map(function($q) {
    $questao = [
        'id' => $q['id'],
        'enunciado' => $q['enunciado'],
        'tipo' => $q['tipo'],
        'valor' => $q['valor'],
        'imagem_url' => $q['imagem_url'] ?? null,
        'ordem' => $q['ordem']
    ];
    if ($q['tipo'] === 'multipla_escolha' && isset($q['alternativas'])) {
        $questao['alternativas'] = $q['alternativas'];
    }
    return $questao;
}, $questoes)) ?>;

// Função auxiliar para fazer parse seguro de JSON
async function parseJsonResponse(response) {
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        throw new Error(`Resposta não é JSON. Status: ${response.status}. Resposta: ${text.substring(0, 200)}`);
    }
    return response.json();
}

function abrirModalColarJson() {
    const modal = document.getElementById('modal-colar-json');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    document.getElementById('colar-json-resultado').textContent = '';
    document.getElementById('apostila-status').textContent = '';
    document.getElementById('apostila-exercicios-lista').innerHTML = '';
    document.getElementById('apostila-filtros').classList.add('hidden');
    carregarApostilasDisponiveis();
}

function fecharModalColarJson() {
    const modal = document.getElementById('modal-colar-json');
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    var selectApostila = document.getElementById('apostila-select');
    if (selectApostila) {
        selectApostila.addEventListener('change', function () {
            carregarExerciciosDaApostila(selectApostila.value);
        });
    }
    ['apostila-filtro-busca', 'apostila-filtro-tipo', 'apostila-filtro-dificuldade'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', renderizarListaExercicios);
            el.addEventListener('change', renderizarListaExercicios);
        }
    });
});

// Mapeia os tipos usados pela IA da Apostila (objetiva/discursiva/verdadeiro_falso/
// associacao/outro) para os tipos aceitos por esta tela (multipla_escolha/discursiva/
// verdadeiro_falso) — os nomes não coincidem entre os dois módulos.
function mapTipoParaProva(tipo) {
    var t = (tipo || '').toString().toLowerCase();
    if (t === 'objetiva' || t === 'multipla_escolha') return 'multipla_escolha';
    if (t === 'verdadeiro_falso') return 'verdadeiro_falso';
    return 'discursiva'; // discursiva, associacao, outro, ou tipo desconhecido
}

// Tenta casar o texto do gabarito com uma das alternativas para marcar a
// resposta correta. Se não houver correspondência confiável, NENHUMA
// alternativa é marcada como certa (evita marcar uma resposta errada).
function montarAlternativasProva(questao) {
    var brutas = Array.isArray(questao.alternativas) ? questao.alternativas : [];
    var textos = brutas
        .map(function (a) { return typeof a === 'string' ? a : ((a && (a.texto || a.text)) || ''); })
        .map(function (s) { return s.toString().trim(); })
        .filter(Boolean);

    if (textos.length === 0) {
        return { alternativas: [], gabaritoEncontrado: true }; // sem alternativas: nada a avisar
    }

    var gabarito = (questao.gabarito || '').toString().trim().toLowerCase();
    var indiceCorreta = -1;
    if (gabarito) {
        indiceCorreta = textos.findIndex(function (texto) {
            var t = texto.toLowerCase();
            return t === gabarito || t.indexOf(gabarito) !== -1 || gabarito.indexOf(t) !== -1;
        });
    }

    var alternativas = textos.map(function (texto, index) {
        return { texto: texto, correta: index === indiceCorreta ? 1 : 0, ordem: index };
    });

    return { alternativas: alternativas, gabaritoEncontrado: indiceCorreta !== -1 };
}

async function importarLista(lista) {
    var resultadoEl = document.getElementById('colar-json-resultado');

    if (!Array.isArray(lista) || lista.length === 0) {
        resultadoEl.textContent = 'Nenhuma questão selecionada.';
        resultadoEl.className = 'text-sm text-red-600 mt-2';
        return;
    }

    resultadoEl.textContent = 'Importando ' + lista.length + ' questão(ões)...';
    resultadoEl.className = 'text-sm text-gray-600 mt-2';

    var sucesso = 0, falhas = 0, semGabarito = 0;

    for (var i = 0; i < lista.length; i++) {
        var questao = lista[i] || {};
        var enunciado = (questao.enunciado || '').toString().trim();
        if (!enunciado) {
            falhas++;
            continue;
        }

        var tipo = mapTipoParaProva(questao.tipo);
        var altInfo = tipo === 'multipla_escolha'
            ? montarAlternativasProva(questao)
            : { alternativas: [], gabaritoEncontrado: true };

        if (tipo === 'multipla_escolha' && !altInfo.gabaritoEncontrado) {
            semGabarito++;
        }

        var payload = {
            enunciado: enunciado,
            imagem_url: questao.imagem_url || null,
            tipo: tipo,
            valor: 1.00,
            alternativas: altInfo.alternativas
        };

        try {
            var response = await fetch('<?= URL ?>/professor/provas/adicionar-questao/<?= $prova['id'] ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            var data = await response.json().catch(function () { return null; });
            if (response.ok && data && !data.error) {
                sucesso++;
            } else {
                falhas++;
            }
        } catch (err) {
            falhas++;
        }
    }

    var msg = sucesso + ' questão(ões) importada(s)';
    if (falhas > 0) msg += ', ' + falhas + ' falharam';
    if (semGabarito > 0) msg += ', ' + semGabarito + ' sem resposta correta identificada automaticamente (revise manualmente)';
    msg += '.';

    resultadoEl.textContent = msg;
    resultadoEl.className = sucesso > 0 ? 'text-sm text-green-700 mt-2' : 'text-sm text-red-600 mt-2';

    if (sucesso > 0) {
        setTimeout(function () { window.location.reload(); }, 1800);
    }
}

async function importarQuestoesJson() {
    var textarea = document.getElementById('colar-json-textarea');
    var resultadoEl = document.getElementById('colar-json-resultado');
    var lista;
    try {
        var parsed = JSON.parse(textarea.value);
        lista = Array.isArray(parsed) ? parsed : (parsed.questoes || parsed.exercicios || []);
    } catch (e) {
        resultadoEl.textContent = 'JSON inválido: ' + e.message;
        resultadoEl.className = 'text-sm text-red-600 mt-2';
        return;
    }
    await importarLista(lista);
}

// ===== Seletor visual de exercícios da IA da Apostila =====
var apostilaExerciciosCache = [];
var apostilaExerciciosSelecionados = {};

function badgeTipo(tipo) {
    var labels = { objetiva: 'Objetiva', discursiva: 'Discursiva', verdadeiro_falso: 'V/F' };
    return labels[(tipo || '').toLowerCase()] || (tipo || '—');
}

function badgeDificuldade(dificuldade) {
    var labels = { facil: 'Fácil', media: 'Média', dificil: 'Difícil' };
    return labels[(dificuldade || '').toLowerCase()] || '';
}

async function carregarApostilasDisponiveis() {
    var select = document.getElementById('apostila-select');
    select.innerHTML = '<option value="">Carregando apostilas...</option>';
    try {
        var res = await fetch('<?= URL ?>/professor/apostilas-ia-disponiveis', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        var apostilas = await res.json();
        if (!Array.isArray(apostilas) || apostilas.length === 0) {
            select.innerHTML = '<option value="">Nenhuma apostila pronta disponível</option>';
            return;
        }
        select.innerHTML = '<option value="">Selecione uma apostila...</option>' +
            apostilas.map(function (a) { return '<option value="' + a.id + '">' + escapeHtml(a.titulo) + '</option>'; }).join('');
    } catch (e) {
        select.innerHTML = '<option value="">Falha ao carregar apostilas</option>';
    }
}

async function carregarExerciciosDaApostila(apostilaId) {
    var statusEl = document.getElementById('apostila-status');
    var listaEl = document.getElementById('apostila-exercicios-lista');
    document.getElementById('apostila-filtros').classList.add('hidden');
    listaEl.innerHTML = '';
    apostilaExerciciosCache = [];
    apostilaExerciciosSelecionados = {};
    atualizarContagemSelecionados();

    if (!apostilaId) {
        return;
    }

    statusEl.textContent = 'Carregando exercícios...';
    try {
        var res = await fetch('<?= URL ?>/professor/apostilas-ia/' + apostilaId + '/exercicios', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        var exercicios = await res.json();
        if (!Array.isArray(exercicios) || exercicios.length === 0) {
            statusEl.textContent = 'Nenhum exercício encontrado nesta apostila.';
            return;
        }
        apostilaExerciciosCache = exercicios;
        statusEl.textContent = exercicios.length + ' exercício(s) encontrado(s).';
        document.getElementById('apostila-filtros').classList.remove('hidden');
        renderizarListaExercicios();
    } catch (e) {
        statusEl.textContent = 'Falha ao carregar exercícios desta apostila.';
    }
}

function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = s || '';
    return div.innerHTML;
}

function renderizarListaExercicios() {
    var listaEl = document.getElementById('apostila-exercicios-lista');
    var busca = (document.getElementById('apostila-filtro-busca').value || '').toLowerCase();
    var tipoFiltro = document.getElementById('apostila-filtro-tipo').value;
    var dificuldadeFiltro = document.getElementById('apostila-filtro-dificuldade').value;

    var filtrados = apostilaExerciciosCache.filter(function (ex) {
        if (tipoFiltro && ex.tipo !== tipoFiltro) return false;
        if (dificuldadeFiltro && ex.dificuldade !== dificuldadeFiltro) return false;
        if (busca) {
            var alvo = ((ex.enunciado || '') + ' ' + (ex.tema || '')).toLowerCase();
            if (alvo.indexOf(busca) === -1) return false;
        }
        return true;
    });

    if (filtrados.length === 0) {
        listaEl.innerHTML = '<p class="text-sm text-gray-400 p-2">Nenhum exercício corresponde ao filtro.</p>';
        return;
    }

    listaEl.innerHTML = filtrados.map(function (ex) {
        var marcado = !!apostilaExerciciosSelecionados[ex.id];
        var enunciadoCurto = (ex.enunciado || '').substring(0, 180);
        var imgHtml = ex.tem_imagem && ex.imagem_url
            ? '<img src="' + ex.imagem_url + '" loading="lazy" class="mt-1 max-h-20 rounded border border-gray-200">'
            : '';
        return '<label class="flex items-start gap-2 p-2 border border-gray-100 rounded-lg hover:bg-gray-50 cursor-pointer">' +
            '<input type="checkbox" class="mt-1 exercicio-checkbox" data-id="' + ex.id + '" ' + (marcado ? 'checked' : '') + '>' +
            '<span class="flex-1 text-sm">' +
                '<span class="inline-block text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 mr-1">' + badgeTipo(ex.tipo) + '</span>' +
                (ex.dificuldade ? '<span class="inline-block text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 mr-1">' + badgeDificuldade(ex.dificuldade) + '</span>' : '') +
                '<span class="text-xs text-gray-400">pág. ' + ex.pagina + '</span>' +
                '<div class="text-gray-800 mt-1">' + escapeHtml(enunciadoCurto) + (ex.enunciado && ex.enunciado.length > 180 ? '...' : '') + '</div>' +
                imgHtml +
            '</span>' +
        '</label>';
    }).join('');

    listaEl.querySelectorAll('.exercicio-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var id = parseInt(cb.getAttribute('data-id'), 10);
            if (cb.checked) {
                apostilaExerciciosSelecionados[id] = apostilaExerciciosCache.find(function (e) { return e.id === id; });
            } else {
                delete apostilaExerciciosSelecionados[id];
            }
            atualizarContagemSelecionados();
        });
    });
}

function atualizarContagemSelecionados() {
    var n = Object.keys(apostilaExerciciosSelecionados).length;
    document.getElementById('apostila-selecionados-count').textContent = n > 0 ? n + ' selecionado(s)' : '';
}

async function importarSelecionados() {
    var selecionados = Object.values(apostilaExerciciosSelecionados);
    await importarLista(selecionados);
    apostilaExerciciosSelecionados = {};
    atualizarContagemSelecionados();
}

function abrirModalGerarIA() {
    const modal = document.getElementById('modal-gerar-ia');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function fecharModalGerarIA() {
    const modal = document.getElementById('modal-gerar-ia');
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

function abrirModalBancoQuestoes() {
    const modal = document.getElementById('modal-banco-questoes');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
    inicializarBancoQuestoes();
}

function fecharModalBancoQuestoes() {
    const modal = document.getElementById('modal-banco-questoes');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
}

// Fechar modal ao clicar fora
document.getElementById('modal-gerar-ia').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalGerarIA();
    }
});

function abrirModalLerImagem() {
    const modal = document.getElementById('modal-ler-imagem');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
    // Resetar estado
    const uploadAreaEl = document.getElementById('upload-area');
    const previewEl = document.getElementById('preview-imagem-ler');
    const imagemInputEl = document.getElementById('imagem-questao-ler');
    const imagemDataEl = document.getElementById('imagem-data-ler');
    
    if (uploadAreaEl) uploadAreaEl.classList.remove('hidden');
    if (previewEl) previewEl.classList.add('hidden');
    if (imagemInputEl) imagemInputEl.value = '';
    if (imagemDataEl) imagemDataEl.value = '';
}

function fecharModalLerImagem() {
    const modal = document.getElementById('modal-ler-imagem');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
    // Limpar formulário
    const formEl = document.getElementById('lerImagemForm');
    if (formEl) formEl.reset();
    
    const previewEl = document.getElementById('preview-imagem-ler');
    const loadingEl = document.getElementById('loading-ler-imagem');
    const imagemDataEl = document.getElementById('imagem-data-ler');
    const imagemInputEl = document.getElementById('imagem-questao-ler');
    const uploadAreaEl = document.getElementById('upload-area');
    
    if (previewEl) previewEl.classList.add('hidden');
    if (loadingEl) loadingEl.classList.add('hidden');
    if (imagemDataEl) imagemDataEl.value = '';
    if (imagemInputEl) imagemInputEl.value = '';
    if (uploadAreaEl) uploadAreaEl.classList.remove('hidden');
}

// Fechar modal ao clicar fora
document.getElementById('modal-ler-imagem').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalLerImagem();
    }
});

// Função para processar imagem (arquivo ou dados)
function processarImagem(fileOrDataUrl) {
    let file;
    let dataUrl;
    
    if (fileOrDataUrl instanceof File) {
        file = fileOrDataUrl;
        const reader = new FileReader();
        reader.onload = function(e) {
            dataUrl = e.target.result;
            mostrarPreviewLer(dataUrl);
            // Atualizar input file
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            const imagemInputEl = document.getElementById('imagem-questao-ler');
            if (imagemInputEl) {
                imagemInputEl.files = dataTransfer.files;
            }
            const imagemDataEl = document.getElementById('imagem-data-ler');
            if (imagemDataEl) {
                imagemDataEl.value = dataUrl;
            }
        };
        reader.readAsDataURL(file);
    } else {
        dataUrl = fileOrDataUrl;
        mostrarPreviewLer(dataUrl);
        const imagemDataEl = document.getElementById('imagem-data-ler');
        if (imagemDataEl) {
            imagemDataEl.value = dataUrl;
        }
    }
}

function mostrarPreviewLer(dataUrl) {
    const previewImgEl = document.getElementById('preview-img-ler');
    const previewEl = document.getElementById('preview-imagem-ler');
    const uploadAreaEl = document.getElementById('upload-area');
    
    if (previewImgEl) previewImgEl.src = dataUrl;
    if (previewEl) previewEl.classList.remove('hidden');
    if (uploadAreaEl) uploadAreaEl.classList.add('hidden');
}

// Função para remover imagem do modal de ler imagem
function removerImagemLer() {
    const previewEl = document.getElementById('preview-imagem-ler');
    const uploadAreaEl = document.getElementById('upload-area');
    const imagemInputEl = document.getElementById('imagem-questao-ler');
    const imagemDataEl = document.getElementById('imagem-data-ler');
    
    if (previewEl) previewEl.classList.add('hidden');
    if (uploadAreaEl) uploadAreaEl.classList.remove('hidden');
    if (imagemInputEl) imagemInputEl.value = '';
    if (imagemDataEl) imagemDataEl.value = '';
}

// Função para remover imagem do formulário manual (mantida para compatibilidade)
function removerImagem() {
    imagemUrlAtual = null;
    document.getElementById('imagem-preview-container').classList.add('hidden');
    document.getElementById('imagem-preview').src = '';
    document.getElementById('imagem-url').value = '';
}

// Guardar seleção antes do clique na toolbar (para listas e formatação)
var savedSelEditor = null;
var savedSelEdit = null;

function saveSelEnunciado() {
    savedSelEditor = null;
    var el = document.getElementById('editor-enunciado');
    if (!el) return;
    var sel = window.getSelection();
    try {
        if (sel.rangeCount && sel.anchorNode && el.contains(sel.anchorNode)) {
            savedSelEditor = sel.getRangeAt(0).cloneRange();
        }
    } catch (e) {}
}
function saveSelEditEnunciado() {
    savedSelEdit = null;
    var el = document.getElementById('edit-enunciado');
    if (!el) return;
    var sel = window.getSelection();
    try {
        if (sel.rangeCount && sel.anchorNode && el.contains(sel.anchorNode)) {
            savedSelEdit = sel.getRangeAt(0).cloneRange();
        }
    } catch (e) {}
}

function applyListToEditor(el, ordered) {
    if (!el) return;
    el.focus();
    var sel = window.getSelection();
    var range = sel.rangeCount ? sel.getRangeAt(0) : null;
    if (!range || !el.contains(range.commonAncestorContainer)) return;
    try {
        var tag = ordered ? 'ol' : 'ul';
        var fragment = range.extractContents();
        var list = document.createElement(tag);
        list.className = 'editor-list';
        var items = fragment.querySelectorAll('li');
        if (items.length > 0) {
            while (fragment.firstChild) list.appendChild(fragment.firstChild);
        } else {
            var text = (fragment.textContent || '').trim();
            var lines = text.split(/\r?\n/).filter(function(s) { return s.trim().length > 0; });
            if (lines.length <= 1) {
                var li = document.createElement('li');
                while (fragment.firstChild) li.appendChild(fragment.firstChild);
                if (!li.firstChild) li.textContent = text || '';
                list.appendChild(li);
            } else {
                lines.forEach(function(line) {
                    var li = document.createElement('li');
                    li.textContent = line.trim();
                    list.appendChild(li);
                });
            }
        }
        if (!list.querySelector('li')) {
            var li = document.createElement('li');
            list.appendChild(li);
        }
        range.insertNode(list);
        range.setStartAfter(list);
        range.setEndAfter(list);
        sel.removeAllRanges();
        sel.addRange(range);
    } catch (e) {
        document.execCommand(ordered ? 'insertOrderedList' : 'insertUnorderedList', false, null);
    }
}

function fmtEnunciado(cmd) {
    var el = document.getElementById('editor-enunciado');
    if (!el) return;
    el.focus();
    var sel = window.getSelection();
    if (savedSelEditor) {
        try {
            sel.removeAllRanges();
            sel.addRange(savedSelEditor);
        } catch (e) {}
        savedSelEditor = null;
    }
    if (cmd === 'insertOrderedList' || cmd === 'insertUnorderedList') {
        applyListToEditor(el, cmd === 'insertOrderedList');
    } else {
        document.execCommand(cmd, false, null);
    }
}
function fmtEditEnunciado(cmd) {
    var el = document.getElementById('edit-enunciado');
    if (!el) return;
    el.focus();
    var sel = window.getSelection();
    if (savedSelEdit) {
        try {
            sel.removeAllRanges();
            sel.addRange(savedSelEdit);
        } catch (e) {}
        savedSelEdit = null;
    }
    if (cmd === 'insertOrderedList' || cmd === 'insertUnorderedList') {
        applyListToEditor(el, cmd === 'insertOrderedList');
    } else {
        document.execCommand(cmd, false, null);
    }
}

// Aumentar/diminuir tamanho da fonte no editor
function fmtFontSize(editorId, delta) {
    var el = document.getElementById(editorId);
    if (!el) return;
    el.focus();
    var sel = window.getSelection();
    var range = sel.rangeCount ? sel.getRangeAt(0) : null;
    if (!range) return;
    if (!el.contains(range.commonAncestorContainer)) {
        range = document.createRange();
        range.selectNodeContents(el);
        range.collapse(true);
    }
    if (range.collapsed) {
        var start = range.startContainer;
        var block = start.nodeType === 3 ? start.parentNode : start;
        while (block && block !== el && !/^(P|DIV|H[1-6]|LI)$/i.test(block.tagName)) block = block.parentNode;
        if (block && block !== el) {
            range.setStart(block, 0);
            range.setEnd(block, block.childNodes.length);
        }
    }
    try {
        var fragment = range.extractContents();
        if (!fragment.childNodes.length && !fragment.textContent) return;
        var span = document.createElement('span');
        span.style.fontSize = delta > 0 ? '1.2em' : '0.85em';
        span.appendChild(fragment);
        range.insertNode(span);
        range.setStartAfter(span);
        range.setEndAfter(span);
        sel.removeAllRanges();
        sel.addRange(range);
    } catch (e) {
        document.execCommand('fontSize', false, delta > 0 ? '5' : '2');
    }
}

// ── Colar/Inserir imagem no enunciado e alternativas ──
var altEditorForImage = null;

var BASE_URL_IMAGEM = '<?= rtrim(URL, "/") ?>';
var CSRF_TOKEN = '<?= addslashes($_SESSION["csrf_token"] ?? "") ?>';

/** URL base para upload: usa a mesma origem/caminho da página (funciona em colag, demo, local). */
function getUploadImagemQuestaoUrl() {
    var path = window.location.pathname || '';
    var idx = path.indexOf('/professor');
    if (idx !== -1) {
        var base = window.location.origin + path.substring(0, idx);
        return base + '/professor/provas/upload-imagem-questao';
    }
    return (typeof BASE_URL_IMAGEM !== 'undefined' && BASE_URL_IMAGEM ? BASE_URL_IMAGEM : window.location.origin) + '/professor/provas/upload-imagem-questao';
}

function getCsrfTokenForUpload() {
    if (typeof CSRF_TOKEN !== 'undefined' && CSRF_TOKEN) return CSRF_TOKEN;
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.getAttribute('content')) return meta.getAttribute('content');
    return '';
}

function uploadImageForEditor(file) {
    if (!file || !(file instanceof File || file instanceof Blob)) return Promise.reject(new Error('Arquivo inválido'));
    var isImage = (file.type && file.type.indexOf('image') === 0) || (!file.type || file.type === '');
    if (!isImage) return Promise.reject(new Error('Arquivo não é uma imagem'));
    var formData = new FormData();
    formData.append('imagem', file, (file instanceof File && file.name) ? file.name : 'imagem.png');
    var token = getCsrfTokenForUpload();
    if (token) formData.append('_token', token);
    var headers = {};
    if (token) headers['X-CSRF-Token'] = token;
    var uploadUrl = getUploadImagemQuestaoUrl();
    return fetch(uploadUrl, { method: 'POST', body: formData, credentials: 'include', headers: headers })
        .then(function(r) {
            return r.text().then(function(text) {
                var data = null;
                try { data = text ? JSON.parse(text) : null; } catch (e) { }
                if (!r.ok) {
                    var msg = (data && data.error) ? data.error : (r.status === 400 ? 'Erro 400 no servidor.' : 'Upload falhou: ' + r.status);
                    if (!data && text && text.length < 500) msg += ' Resposta: ' + text.trim();
                    throw new Error(msg);
                }
                return data || {};
            });
        })
        .then(function(data) {
            if (!data || !data.success) throw new Error(data && data.error ? data.error : 'Erro no upload');
            var url = data.image_url;
            if (!url || typeof url !== 'string') throw new Error('URL da imagem não retornada');
            return url;
        });
}

function normalizarUrlImagem(url) {
    if (!url) return '';
    url = String(url).trim();
    if (url.indexOf('http://') === 0 || url.indexOf('https://') === 0 || url.indexOf('data:') === 0) return url;
    var base = BASE_URL_IMAGEM || (window.location.origin + (window.location.pathname.indexOf('/professor') !== -1 ? window.location.pathname.split('/professor')[0] : ''));
    return base + (url.charAt(0) === '/' ? '' : '/') + url;
}

function insertImageInEditor(editorEl, url) {
    if (!editorEl || !url) return;
    url = normalizarUrlImagem(url);
    if (!url) return;
    editorEl.focus();
    var img = document.createElement('img');
    img.src = url;
    img.setAttribute('data-uploaded', '1');
    img.style.maxWidth = '100%';
    img.style.height = 'auto';
    img.style.verticalAlign = 'middle';
    var sel = window.getSelection();
    var range = sel.rangeCount ? sel.getRangeAt(0) : null;
    if (!range || !editorEl.contains(range.commonAncestorContainer)) {
        range = document.createRange();
        range.selectNodeContents(editorEl);
        range.collapse(true);
    }
    range.deleteContents();
    range.insertNode(img);
    range.setStartAfter(img);
    range.setEndAfter(img);
    sel.removeAllRanges();
    sel.addRange(range);
}

function abrirInserirImagemAlt(btn) {
    var item = btn.closest('[data-opcao-index]');
    if (!item) return;
    var ed = item.querySelector('.alt-editor');
    if (ed) {
        altEditorForImage = ed;
        ed.focus();
        document.getElementById('alt-insert-image-input').click();
    }
}

function applyImageSize(img, pct) {
    if (!img || img.tagName !== 'IMG') return;
    pct = Math.max(10, Math.min(100, parseInt(pct, 10) || 100));
    img.style.maxWidth = pct + '%';
    img.style.width = pct + '%';
    img.style.height = 'auto';
}

function getImageSizePct(img) {
    if (!img || img.tagName !== 'IMG') return 100;
    var w = img.style.width || img.style.maxWidth || '';
    if (w) { var n = parseInt(w, 10); if (!isNaN(n)) return n; }
    var styleAttr = img.getAttribute('style') || '';
    var match = styleAttr.match(/(?:width|max-width)\s*:\s*(\d+)\s*%?/i);
    if (match) return parseInt(match[1], 10) || 100;
    return 100;
}

function showImageResizePopover(img) {
    var id = 'editor-image-resize-popover';
    var existing = document.getElementById(id);
    if (existing) existing.remove();
    var div = document.createElement('div');
    div.id = id;
    div.className = 'editor-image-resize-popover';
    div.innerHTML = '<div class="editor-image-resize-title">Redimensionar imagem</div>' +
        '<div class="editor-image-resize-row">' +
        '<button type="button" class="editor-image-resize-btn" data-pct="25">25%</button>' +
        '<button type="button" class="editor-image-resize-btn" data-pct="50">50%</button>' +
        '<button type="button" class="editor-image-resize-btn" data-pct="75">75%</button>' +
        '<button type="button" class="editor-image-resize-btn" data-pct="100">100%</button>' +
        '</div>' +
        '<div class="editor-image-resize-row editor-image-resize-row-steps">' +
        '<button type="button" class="editor-image-resize-btn editor-image-resize-btn-step" title="Diminuir" data-step="-">−</button>' +
        '<button type="button" class="editor-image-resize-btn editor-image-resize-btn-step" title="Aumentar" data-step="+">+</button>' +
        '</div>';
    document.body.appendChild(div);
    function positionPopover() {
        var rect = img.getBoundingClientRect();
        div.style.position = 'fixed';
        div.style.left = rect.left + 'px';
        div.style.top = (rect.bottom + 6) + 'px';
    }
    positionPopover();
    div.querySelectorAll('.editor-image-resize-btn[data-pct], .editor-image-resize-btn[data-step]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var pct = btn.getAttribute('data-pct');
            var step = btn.getAttribute('data-step');
            if (pct) {
                applyImageSize(img, parseInt(pct, 10));
            } else if (step === '-') {
                var num = getImageSizePct(img);
                applyImageSize(img, Math.max(10, num - 15));
            } else if (step === '+') {
                var num = getImageSizePct(img);
                applyImageSize(img, Math.min(100, num + 15));
            }
            div.remove();
            document.removeEventListener('click', closeHandler);
        });
    });
    function closeHandler(e) {
        if (div.parentNode && !div.contains(e.target) && e.target !== img) {
            div.remove();
            document.removeEventListener('click', closeHandler);
        }
    }
    setTimeout(function() { document.addEventListener('click', closeHandler); }, 10);
}

// Preview da imagem ao selecionar arquivo no modal de ler imagem
const imagemQuestaoLerEl = document.getElementById('imagem-questao-ler');
if (imagemQuestaoLerEl) {
    imagemQuestaoLerEl.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            processarImagem(file);
        }
    });
}

// Suporte para arrastar e soltar
const uploadArea = document.getElementById('upload-area');
uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('border-green-500', 'bg-green-50');
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('border-green-500', 'bg-green-50');
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('border-green-500', 'bg-green-50');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const file = files[0];
        if (file.type.startsWith('image/')) {
            processarImagem(file);
        } else {
            alert('Por favor, selecione apenas arquivos de imagem (PNG, JPG, JPEG)');
        }
    }
});

// Suporte para colar imagem (Ctrl+V)
document.addEventListener('paste', function(e) {
    // Verifica se o modal está aberto
    const modal = document.getElementById('modal-ler-imagem');
    if (modal && modal.style.display !== 'none' && !modal.classList.contains('hidden')) {
        const items = e.clipboardData.items;
        
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                e.preventDefault();
                const blob = items[i].getAsFile();
                
                // Cria um File object a partir do blob
                const file = new File([blob], 'imagem-colada.png', { type: blob.type || 'image/png' });
                
                // Atualiza o input file
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                const imagemInputEl = document.getElementById('imagem-questao-ler');
                if (imagemInputEl) {
                    imagemInputEl.files = dataTransfer.files;
                }
                
                // Processa a imagem
                processarImagem(file);
                break;
            }
        }
    }
});

// Clique na área de upload
if (uploadArea) {
    uploadArea.addEventListener('click', function(e) {
        if (e.target === this || e.target.closest('#upload-area')) {
            const imagemInputEl = document.getElementById('imagem-questao-ler');
            if (imagemInputEl) {
                imagemInputEl.click();
            }
        }
    });
}

function uploadImagemQuestao(input) {
    if (!input.files || !input.files[0]) return;
    
    const formData = new FormData();
    formData.append('imagem', input.files[0]);
    var token = getCsrfTokenForUpload();
    if (token) formData.append('_token', token);
    var headers = {};
    if (token) headers['X-CSRF-Token'] = token;
    
    const loadingBtn = input.nextElementSibling;
    const originalText = loadingBtn.textContent;
    loadingBtn.disabled = true;
    loadingBtn.textContent = 'Enviando...';
    
    fetch(typeof getUploadImagemQuestaoUrl === 'function' ? getUploadImagemQuestaoUrl() : '<?= rtrim(URL, "/") ?>/professor/provas/upload-imagem-questao', {
        method: 'POST',
        body: formData,
        credentials: 'include',
        headers: headers
    })
    .then(async response => {
        var data = await parseJsonResponse(response).catch(function() { return {}; });
        if (!response.ok) {
            throw new Error(data.error || ('Erro ' + response.status));
        }
        return data;
    })
    .then(data => {
        loadingBtn.disabled = false;
        loadingBtn.textContent = originalText;
        if (data.success) {
            imagemUrlAtual = data.image_url;
            document.getElementById('imagem-url').value = data.image_url;
            document.getElementById('imagem-preview').src = data.image_url;
            document.getElementById('imagem-preview-container').classList.remove('hidden');
        } else {
            alert('Erro: ' + (data.error || 'Erro ao fazer upload'));
        }
    })
    .catch(error => {
        loadingBtn.disabled = false;
        loadingBtn.textContent = originalText;
        alert('Erro ao fazer upload: ' + (error.message || 'tente novamente'));
        console.error(error);
    });
}

function removerImagem() {
    imagemUrlAtual = null;
    document.getElementById('imagem-url').value = '';
    document.getElementById('imagem-questao').value = '';
    document.getElementById('imagem-preview-container').classList.add('hidden');
}

function abrirMathAlt(btn) {
    var item = btn.closest('[data-opcao-index]');
    if (!item) return;
    var ed = item.querySelector('.alt-editor');
    if (ed && typeof MathEditor !== 'undefined') MathEditor.abrir(ed);
}

function adicionarOpcao() {
    if (opcoesCount >= 5) {
        alert('Máximo de 5 alternativas permitidas (A, B, C, D, E)');
        return;
    }
    
    const letra = letras[opcoesCount];
    const idx = opcoesCount;
    opcoesCount++;
    const container = document.getElementById('opcoes-lista');
    const div = document.createElement('div');
    div.className = 'alt-item';
    div.setAttribute('data-opcao-index', idx);
    div.innerHTML = `
        <div class="alt-letter">${letra}</div>
        <div class="alt-input-wrap">
            <div class="alt-toolbar">
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('bold')" title="Negrito"><b>B</b></button>
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('italic')" title="Itálico"><i>I</i></button>
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('underline')" title="Sublinhado"><u>U</u></button>
                <div class="alt-tb-sep"></div>
                <button type="button" class="alt-tb-btn alt-tb-eq" onmousedown="event.preventDefault();abrirMathAlt(this)" title="Inserir equação">∑ eq</button>
                <div class="alt-tb-sep"></div>
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();abrirInserirImagemAlt(this)" title="Inserir ou colar imagem">📷</button>
            </div>
            <div class="alt-editor" id="opcao-editor-${idx}" contenteditable="true" data-math="inline" data-placeholder="Alternativa ${letra}..."></div>
        </div>
        <div class="alt-actions flex items-center gap-3 flex-shrink-0">
            <label for="radio-${idx}" class="flex items-center gap-2 cursor-pointer alt-actions-label">
                <input type="radio" name="resposta_opcao" value="${idx}" id="radio-${idx}" class="w-5 h-5 text-blue-600 focus:ring-blue-500">
                <span class="alt-correct">Correta</span>
            </label>
            <button type="button" onclick="removerOpcao(this)" class="px-3 py-2 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors whitespace-nowrap">
                Remover
            </button>
        </div>
    `;
    container.appendChild(div);
    atualizarIndicesOpcoes();
}

function removerOpcao(button) {
    const div = button.closest('div[data-opcao-index]');
    div.remove();
    opcoesCount--;
    atualizarIndicesOpcoes();
}

function atualizarIndicesOpcoes() {
    const container = document.getElementById('opcoes-lista');
    const opcoes = container.querySelectorAll('.alt-item');
    opcoes.forEach((opcao, index) => {
        const letra = letras[index];
        opcao.setAttribute('data-opcao-index', index);
        var letterEl = opcao.querySelector('.alt-letter');
        if (letterEl) letterEl.textContent = letra;
        var editor = opcao.querySelector('.alt-editor');
        if (editor) {
            editor.id = 'opcao-editor-' + index;
            editor.setAttribute('data-placeholder', 'Alternativa ' + letra + '...');
        }
        var radio = opcao.querySelector('input[type="radio"]');
        if (radio) {
            radio.value = index;
            radio.id = 'radio-' + index;
        }
        var label = opcao.querySelector('label.alt-actions-label, label.alt-correct, label[for^="radio-"]');
        if (label) label.setAttribute('for', 'radio-' + index);
    });
}

// Função para atualizar opções baseado no tipo
function atualizarOpcoesPorTipo() {
    const tipoSelect = document.getElementById('tipo-questao');
    const container = document.getElementById('opcoes-container');
    if (tipoSelect.value === 'multipla_escolha') {
        container.classList.remove('hidden');
        if (opcoesCount === 0) {
            adicionarOpcao();
            adicionarOpcao();
        }
    } else {
        container.classList.add('hidden');
        document.getElementById('opcoes-lista').innerHTML = '';
        opcoesCount = 0;
    }
}

// Atualizar opções baseado no tipo quando mudar
document.getElementById('tipo-questao').addEventListener('change', atualizarOpcoesPorTipo);

// Verificar valor inicial quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    atualizarOpcoesPorTipo();
    // Sincroniza contenteditable do enunciado com o hidden (para required e submit)
    var editorEnunciado = document.getElementById('editor-enunciado');
    if (editorEnunciado) {
        function syncEnunciado() {
            var h = document.getElementById('enunciado-questao');
            if (h && typeof MathEditor !== 'undefined' && MathEditor.serializarParaLaTeX) {
                h.value = MathEditor.serializarParaLaTeX(editorEnunciado);
            }
        }
        editorEnunciado.addEventListener('input', syncEnunciado);
        editorEnunciado.addEventListener('blur', syncEnunciado);
    }
    var editEnunciado = document.getElementById('edit-enunciado');
    if (editEnunciado) {
        function syncEditEnunciado() {
            var h = document.getElementById('edit-enunciado-value');
            if (h && typeof MathEditor !== 'undefined' && MathEditor.serializarParaLaTeX) {
                h.value = MathEditor.serializarParaLaTeX(editEnunciado);
            }
        }
        editEnunciado.addEventListener('input', syncEditEnunciado);
        editEnunciado.addEventListener('blur', syncEditEnunciado);
    }

    // Paste imagem no enunciado e alternativas; texto/HTML preservando formatação
    document.addEventListener('paste', function(e) {
        var editor = e.target && (e.target.id === 'editor-enunciado' || e.target.id === 'edit-enunciado' || e.target.classList.contains('alt-editor')) ? e.target : (e.target && e.target.closest ? e.target.closest('#editor-enunciado, #edit-enunciado, .alt-editor') : null);
        if (!editor) return;
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                e.preventDefault();
                var file = items[i].getAsFile();
                if (!file) return;
                uploadImageForEditor(file).then(function(url) {
                    insertImageInEditor(editor, url);
                }).catch(function(err) {
                    alert('Erro ao enviar imagem: ' + (err.message || err));
                });
                return;
            }
        }
        // Colagem de texto: preservar exatamente parágrafos e quebras de linha
        var html = e.clipboardData.getData('text/html');
        var text = e.clipboardData.getData('text/plain');
        if (!text && !html) return;
        e.preventDefault();
        var toInsert = '';
        if (html && html.trim()) {
            toInsert = sanitizePasteHtml(html);
        }
        if (!toInsert && text) {
            toInsert = plainTextToHtml(text);
        }
        if (!toInsert) return;
        var sel = window.getSelection();
        var range = sel && sel.rangeCount ? sel.getRangeAt(0) : null;
        if (range && editor.contains(range.commonAncestorContainer)) {
            range.deleteContents();
            var frag = document.createRange().createContextualFragment(toInsert);
            range.insertNode(frag);
            range.collapse(false);
            sel.removeAllRanges();
            sel.addRange(range);
        } else {
            editor.focus();
            if (document.execCommand('insertHTML', false, toInsert) === false) {
                editor.innerHTML = editor.innerHTML + toInsert;
            }
        }
        editor.dispatchEvent(new Event('input', { bubbles: true }));
    });

    function sanitizePasteHtml(html) {
        var div = document.createElement('div');
        div.innerHTML = html;
        var allowed = { P:1, DIV:1, BR:1, SPAN:1, SUB:1, SUP:1, B:1, I:1, U:1, STRONG:1, EM:1, IMG:1 };
        function sanitizeNode(node) {
            if (node.nodeType === 3) return node.cloneNode(true);
            if (node.nodeType !== 1) return null;
            var tag = node.tagName.toUpperCase();
            if (!allowed[tag]) return document.createTextNode(node.textContent || '');
            var out = document.createElement(tag);
            if (tag === 'IMG') {
                var src = node.getAttribute('src');
                if (src) out.setAttribute('src', src);
            }
            for (var i = 0; i < node.childNodes.length; i++) {
                var c = sanitizeNode(node.childNodes[i]);
                if (c) out.appendChild(c);
            }
            return out;
        }
        var out = document.createElement('div');
        for (var i = 0; i < div.childNodes.length; i++) {
            var c = sanitizeNode(div.childNodes[i]);
            if (c) out.appendChild(c);
        }
        // Remover parágrafos/blocos consecutivos duplicados (evita colagem duplicada)
        dedupeBlockNodes(out);
        var htmlOut = out.innerHTML;
        htmlOut = htmlOut.replace(/m\/s2\b/g, 'm/s²').replace(/\b(v)02\b/g, '$1₀²').replace(/\b(v)0\b/g, '$1₀').replace(/\bv2\s*=/g, 'v² =');
        return htmlOut;
    }

    function dedupeBlockNodes(container) {
        var blockTags = { P:1, DIV:1 };
        var prevText = '';
        var toRemove = [];
        for (var i = 0; i < container.childNodes.length; i++) {
            var node = container.childNodes[i];
            if (node.nodeType === 1 && blockTags[node.tagName.toUpperCase()]) {
                var t = (node.textContent || '').replace(/\s+/g, ' ').trim();
                if (t && t === prevText) toRemove.push(node);
                else prevText = t;
            } else {
                prevText = '';
            }
        }
        toRemove.forEach(function(n) { n.parentNode && n.parentNode.removeChild(n); });
    }

    function plainTextToHtml(text) {
        if (!text) return '';
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        // Remover linhas consecutivas idênticas (evita duplicação na colagem)
        text = text.replace(/([^\n]+)(\n\1)+/g, '$1');
        // Normalizar padrões comuns de física: m/s2 -> m/s², v0 -> v₀, v02 -> v₀²
        text = text.replace(/m\/s2\b/g, 'm/s²').replace(/\b(v)02\b/g, '$1₀²').replace(/\b(v)0\b/g, '$1₀').replace(/\bv2\s*=/g, 'v² =');
        var parts = text.split(/\n\n+/);
        var seen = {};
        parts = parts.filter(function(p) {
            var key = p.replace(/\s+/g, ' ').trim();
            if (seen[key]) return false;
            if (key) seen[key] = true;
            return true;
        });
        var html = parts.map(function(block) {
            var line = block.replace(/\n/g, '<br>');
            return '<p>' + line + '</p>';
        }).join('');
        return html || '<p><br></p>';
    }

    // Clique na imagem para redimensionar (enunciado e alternativas)
    document.addEventListener('click', function(e) {
        var img = e.target && e.target.tagName === 'IMG' ? e.target : null;
        if (!img) return;
        if (!img.closest('#editor-enunciado, #edit-enunciado, .alt-editor')) return;
        e.preventDefault();
        e.stopPropagation();
        showImageResizePopover(img);
    }, true);

    // Inserir imagem por arquivo: enunciado (criar)
    var enunciadoImageInput = document.getElementById('enunciado-insert-image-input');
    if (enunciadoImageInput) {
        enunciadoImageInput.addEventListener('change', function() {
            var file = this.files && this.files[0];
            if (!file) return;
            var el = document.getElementById('editor-enunciado');
            uploadImageForEditor(file).then(function(url) {
                insertImageInEditor(el, url);
            }).catch(function(err) {
                alert('Erro ao enviar imagem: ' + (err.message || err));
            });
            this.value = '';
        });
    }
    // Inserir imagem por arquivo: enunciado (editar)
    var editEnunciadoImageInput = document.getElementById('edit-enunciado-insert-image-input');
    if (editEnunciadoImageInput) {
        editEnunciadoImageInput.addEventListener('change', function() {
            var file = this.files && this.files[0];
            if (!file) return;
            var el = document.getElementById('edit-enunciado');
            uploadImageForEditor(file).then(function(url) {
                insertImageInEditor(el, url);
            }).catch(function(err) {
                alert('Erro ao enviar imagem: ' + (err.message || err));
            });
            this.value = '';
        });
    }
    // Inserir imagem por arquivo: alternativas
    var altImageInput = document.getElementById('alt-insert-image-input');
    if (altImageInput) {
        altImageInput.addEventListener('change', function() {
            var file = this.files && this.files[0];
            if (!file) return;
            var el = altEditorForImage || document.querySelector('.alt-editor');
            if (!el) return;
            uploadImageForEditor(file).then(function(url) {
                insertImageInEditor(el, url);
            }).catch(function(err) {
                alert('Erro ao enviar imagem: ' + (err.message || err));
            });
            this.value = '';
            altEditorForImage = null;
        });
    }
});

// Formulário de ler imagem
document.getElementById('lerImagemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Verifica se tem imagem
    const imagemQuestaoLerEl = document.getElementById('imagem-questao-ler');
    const imagemDataLerEl = document.getElementById('imagem-data-ler');
    const imagemFile = imagemQuestaoLerEl ? imagemQuestaoLerEl.files[0] : null;
    const imagemData = imagemDataLerEl ? imagemDataLerEl.value : '';
    
    if (!imagemFile && !imagemData) {
        alert('Por favor, selecione ou cole uma imagem antes de continuar.');
        return;
    }
    
    const formData = new FormData();
    const provaId = <?= $prova['id'] ?>;
    formData.append('prova_id', provaId);
    
    // Se tem arquivo, usa o arquivo
    if (imagemFile) {
        formData.append('imagem', imagemFile);
    } else if (imagemData) {
        // Se tem dados (colado), converte para blob
        fetch(imagemData)
            .then(res => res.blob())
            .then(blob => {
                const file = new File([blob], 'imagem-colada.png', { type: 'image/png' });
                formData.append('imagem', file);
                enviarFormulario(formData, provaId);
            })
            .catch(error => {
                console.error('Erro ao processar imagem colada:', error);
                alert('Erro ao processar imagem colada. Tente novamente.');
            });
        return;
    }
    
    enviarFormulario(formData, provaId);
});

function enviarFormulario(formData, provaId) {
    const btnLer = document.getElementById('btn-ler-imagem');
    const loadingDiv = document.getElementById('loading-ler-imagem');
    const originalText = btnLer.textContent;
    
    btnLer.disabled = true;
    btnLer.textContent = 'Processando...';
    loadingDiv.classList.remove('hidden');
    
    fetch(`<?= URL ?>/professor/provas/ler-imagem-questao/${provaId}`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        // Verifica status antes de ler o body
        if (!response.ok) {
            const text = await response.text();
            throw new Error(`Erro ${response.status}: ${text.substring(0, 200)}`);
        }
        
        // Verifica content-type sem ler o body
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error('Resposta do servidor não é JSON válido: ' + text.substring(0, 200));
        }
        
        // Tenta parsear JSON diretamente
        try {
            return await response.json();
        } catch (error) {
            // Se falhar, cria um novo clone para ler como texto para debug
            // (não podemos usar response.text() novamente porque já foi consumido)
            console.error('Erro ao parsear JSON:', error);
            console.error('Erro detalhado:', error.message);
            throw new Error('Erro ao processar resposta do servidor. A resposta pode não ser um JSON válido.');
        }
    })
    .then(data => {
        if (data.success) {
            // Preencher formulário com os dados extraídos
            if (data.questao) {
                const questao = data.questao;
                
                // Preencher enunciado (contenteditable + hidden)
                var editorEnunciado = document.getElementById('editor-enunciado');
                var hiddenEnunciado = document.getElementById('enunciado-questao');
                var textoEnunciado = questao.enunciado || '';
                if (hiddenEnunciado) hiddenEnunciado.value = textoEnunciado;
                if (editorEnunciado && typeof MathEditor !== 'undefined' && MathEditor.preencherDeLaTeX) {
                    MathEditor.preencherDeLaTeX(editorEnunciado, textoEnunciado);
                }
                
                // Preencher tipo
                if (questao.tipo) {
                    const tipoEl = document.getElementById('tipo-questao');
                    if (tipoEl) {
                        tipoEl.value = questao.tipo;
                        atualizarOpcoesPorTipo();
                    }
                }
                
                // Preencher pontuação (se o campo existir)
                const valorEl = document.getElementById('valor-questao');
                if (valorEl && questao.valor) {
                    valorEl.value = questao.valor;
                }
                
                // Preencher alternativas se for múltipla escolha
                if (questao.tipo === 'multipla_escolha' && questao.alternativas && questao.alternativas.length > 0) {
                    document.getElementById('opcoes-lista').innerHTML = '';
                    opcoesCount = 0;
                    questao.alternativas.forEach(function() { adicionarOpcao(); });
                    setTimeout(function() {
                        var itens = document.querySelectorAll('#opcoes-lista .alt-item');
                        questao.alternativas.forEach(function(alt, index) {
                            var ed = itens[index] ? itens[index].querySelector('.alt-editor') : null;
                            if (!ed) return;
                            var t = (alt.texto || '').trim();
                            if (t.indexOf('eq-chip') !== -1 || t.indexOf('data-latex') !== -1) {
                                var serializado = typeof enunciadoHtmlParaLaTeXSerializado === 'function' ? enunciadoHtmlParaLaTeXSerializado(t) : t;
                                if (typeof MathEditor !== 'undefined' && MathEditor.preencherDeLaTeX) MathEditor.preencherDeLaTeX(ed, serializado);
                                else ed.innerHTML = t;
                            } else if (t.indexOf('<') !== -1 || t.indexOf('&') !== -1) {
                                ed.innerHTML = t;
                                if ((t.indexOf('eq-chip') !== -1 || t.indexOf('eq-chip-math') !== -1) && typeof MathEditor !== 'undefined' && MathEditor.renderMathInEditor) {
                                    setTimeout(function() { MathEditor.renderMathInEditor(ed); }, 0);
                                }
                            } else if (typeof MathEditor !== 'undefined' && MathEditor.preencherDeLaTeX && t.indexOf('\\(') !== -1) {
                                MathEditor.preencherDeLaTeX(ed, t);
                            } else {
                                ed.textContent = t;
                            }
                            var radio = itens[index] ? itens[index].querySelector('input[name="resposta_opcao"]') : null;
                            if (alt.correta && radio) radio.checked = true;
                        });
                    }, 50);
                }
                
                alert('Questão extraída com sucesso! Revise os campos e clique em "Adicionar Questão".');
                fecharModalLerImagem();
            } else {
                alert('Questão extraída, mas não foi possível processar os dados.');
            }
        } else {
            throw new Error(data.error || 'Erro ao processar imagem');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar imagem: ' + error.message);
    })
    .finally(() => {
        btnLer.disabled = false;
        btnLer.textContent = originalText;
        loadingDiv.classList.add('hidden');
    });
}

// Formulário de adicionar questão
document.getElementById('adicionarQuestaoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var editorEnunciado = document.getElementById('editor-enunciado');
    if (editorEnunciado) {
        document.getElementById('enunciado-questao').value = editorEnunciado.innerHTML || '';
    }
    var enunciadoVal = document.getElementById('enunciado-questao') && document.getElementById('enunciado-questao').value;
    if (!enunciadoVal || String(enunciadoVal).trim() === '') {
        alert('O enunciado é obrigatório. Digite ou cole o texto da questão no campo Enunciado.');
        return;
    }
    const formData = new FormData(this);
    
    // Coleta opções se for múltipla escolha (dos contenteditable .alt-editor)
    if (formData.get('tipo') === 'multipla_escolha') {
        const opcoes = [];
        const itens = document.querySelectorAll('#opcoes-lista .alt-item');
        const respostaIndex = formData.get('resposta_opcao');
        
        if (itens.length < 2) {
            alert('Adicione pelo menos 2 alternativas');
            return;
        }
        
        if (respostaIndex === null || respostaIndex === '') {
            alert('Selecione a alternativa correta');
            return;
        }
        
        itens.forEach((item, index) => {
            var ed = item.querySelector('.alt-editor');
            var texto = ed ? (ed.innerHTML || '').trim() : '';
            if (texto) {
                opcoes.push({
                    texto: texto,
                    correta: index.toString() === respostaIndex ? 1 : 0,
                    ordem: index
                });
            }
        });
        
        if (opcoes.length < 2) {
            alert('Adicione pelo menos 2 alternativas válidas');
            return;
        }
        
        formData.set('alternativas', JSON.stringify(opcoes));
    }
    
    const data = {
        enunciado: formData.get('enunciado'),
        imagem_url: formData.get('imagem_url') || null,
        tipo: formData.get('tipo'),
        valor: parseFloat(formData.get('valor')) || 1.00,
        alternativas: formData.get('tipo') === 'multipla_escolha' ? JSON.parse(formData.get('alternativas')) : []
    };
    
    fetch('<?= URL ?>/professor/provas/adicionar-questao/<?= $prova['id'] ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
        credentials: 'same-origin'
    })
    .then(async response => {
        if (!response.ok) {
            const text = await response.text();
            var msg = text;
            try {
                var j = JSON.parse(text);
                if (j && j.error) msg = j.error;
            } catch (err) {}
            throw new Error(msg || ('Erro ' + response.status));
        }
        return parseJsonResponse(response);
    })
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro: ' + (error.message || 'Erro de conexão. Verifique se está logado e tente novamente.'));
        console.error(error);
    });
});

// Formulário de gerar com IA
document.getElementById('gerarQuestoesIAForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Coleta níveis e quantidades (inclui nível quando quantidade > 0)
    const niveis = [];
    const quantidadesPorNivel = {};
    
    const qtdFacil = parseInt(document.getElementById('quantidade-facil').value) || 0;
    if (qtdFacil > 0) {
        niveis.push('Fácil');
        quantidadesPorNivel['Fácil'] = qtdFacil;
    }
    const qtdMedio = parseInt(document.getElementById('quantidade-medio').value) || 0;
    if (qtdMedio > 0) {
        niveis.push('Médio');
        quantidadesPorNivel['Médio'] = qtdMedio;
    }
    const qtdDificil = parseInt(document.getElementById('quantidade-dificil').value) || 0;
    if (qtdDificil > 0) {
        niveis.push('Difícil');
        quantidadesPorNivel['Difícil'] = qtdDificil;
    }
    const qtdDesafio = parseInt(document.getElementById('quantidade-desafio').value) || 0;
    if (qtdDesafio > 0) {
        niveis.push('Desafio');
        quantidadesPorNivel['Desafio'] = qtdDesafio;
    }
    
    const quantidadeTotal = Object.values(quantidadesPorNivel).reduce((sum, qtd) => sum + qtd, 0);
    if (niveis.length === 0 || quantidadeTotal === 0) {
        alert('Informe a quantidade de questões em pelo menos um nível de dificuldade');
        return;
    }
    
    if (!confirm(`Deseja gerar ${quantidadeTotal} questão(ões) com IA? Pode levar até 2-3 minutos (inclui geração de imagens). Aguarde e não feche a página.`)) {
        return;
    }
    
    // Fechar modal
    fecharModalGerarIA();
    
    // Mostrar loading
    const loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'loading-overlay';
    loadingOverlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center; flex-direction: column;';
    loadingOverlay.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p style="font-size: 18px; color: #333; margin: 0;">Gerando questões com IA...</p>
            <p style="font-size: 14px; color: #666; margin-top: 10px;">Gerando questões e imagens... Pode levar até 2-3 minutos. Não feche nem recarregue a página.</p>
        </div>
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `;
    document.body.appendChild(loadingOverlay);
    
    const data = {
        tipo: formData.get('tipo'),
        serie: formData.get('serie') || '',
        com_imagens: document.getElementById('com-imagens-ia').checked ? '1' : '0',
        quantidade: quantidadeTotal,
        niveis: niveis,
        quantidades_por_nivel: quantidadesPorNivel,
        planos_aula_id: formData.getAll('planos_aula_id[]'),
        contexto: formData.get('contexto') || ''
    };
    
    var controller = new AbortController();
    var timeoutId = setTimeout(function() { controller.abort(); }, 240000);
    fetch('<?= URL ?>/professor/provas/gerar-questoes-ia/<?= $prova['id'] ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
        signal: controller.signal
    })
    .then(async response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            // Surfaça a mensagem real do backend (ex.: "Créditos insuficientes")
            // em vez de mascarar tudo como timeout.
            let msg = 'Erro ' + response.status;
            try {
                const text = await response.text();
                try {
                    const j = JSON.parse(text);
                    msg = (j && j.error) ? j.error : (msg + ': ' + text.substring(0, 200));
                } catch (e) {
                    if (text) msg += ': ' + text.substring(0, 200);
                }
            } catch (e) {}
            throw new Error(msg);
        }
        return parseJsonResponse(response);
    })
    .then(data => {
        if (loadingOverlay.parentNode) {
            loadingOverlay.parentNode.removeChild(loadingOverlay);
        }
        
        if (data.success) {
            const quantidade = data.questoes_ids ? data.questoes_ids.length : 0;
            alert(`${quantidade} questão(ões) gerada(s) com sucesso!`);
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(function(error) {
        clearTimeout(timeoutId);
        if (loadingOverlay.parentNode) {
            loadingOverlay.parentNode.removeChild(loadingOverlay);
        }
        if (error.name === 'AbortError') {
            alert('A requisição demorou muito. A IA pode levar até 2 minutos. Tente novamente ou recarregue a página.');
        } else if (error && error.message) {
            alert(error.message);
        } else {
            alert('Erro de conexão ou timeout. A geração por IA pode levar até 2 minutos. Se persistir, recarregue a página e tente novamente.');
        }
        console.error(error);
    });
});

let bqOffset = 0;
let bqLimit = 20;
let bqTotal = 0;
let bqSelecionadas = new Set();
let bqSelecionadasMeta = {};

function filtroBancoQuestoesAtual() {
    const getVal = (id) => {
        const el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    };
    const filtro = {
        q: getVal('bq-q'),
        materia: getVal('bq-materia'),
        tipo: getVal('bq-tipo'),
        ano: getVal('bq-ano'),
        dificuldade: getVal('bq-dificuldade'),
        topico: getVal('bq-topico'),
        tag: getVal('bq-tag'),
        origem_titulo: getVal('bq-origem')
    };
    Object.keys(filtro).forEach((k) => {
        if (!filtro[k]) delete filtro[k];
    });
    return filtro;
}

function preencherSelectBancoQuestoes(id, itens, placeholder) {
    const el = document.getElementById(id);
    if (!el) return;
    const atual = el.value;
    const options = [`<option value="">${placeholder}</option>`];
    (itens || []).forEach((item) => {
        let valor = '';
        let total = null;
        if (typeof item === 'string') {
            valor = item;
        } else if (item && typeof item === 'object') {
            valor = String(item.valor || item.materia || item.tipo || '').trim();
            total = Number(item.total);
        }
        if (!valor) return;
        const label = (Number.isFinite(total) && total >= 0) ? `${valor} (${total})` : valor;
        options.push(`<option value="${escapeHtml(valor)}">${escapeHtml(label)}</option>`);
    });
    el.innerHTML = options.join('');
    el.value = atual || '';
}

function inicializarBancoQuestoes() {
    const loading = document.getElementById('bq-loading');
    if (loading) loading.classList.remove('hidden');
    const filtro = filtroBancoQuestoesAtual();
    const query = new URLSearchParams(filtro).toString();
    fetch(`<?= URL ?>/professor/provas/banco-questoes/facets/<?= (int)$prova['id'] ?>${query ? '?' + query : ''}`)
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) throw new Error(data.error || 'Erro ao carregar filtros');
            const facets = (data.data && data.data.facets) ? data.data.facets : {};
            preencherSelectBancoQuestoes('bq-materia', facets.materias || [], 'Todas as matérias');
            preencherSelectBancoQuestoes('bq-tipo', facets.tipos || [], 'Todos os tipos');
            preencherSelectBancoQuestoes('bq-origem', facets.origens_titulo || [], 'Todas as origens');
            preencherSelectBancoQuestoes('bq-dificuldade', facets.dificuldades || [], 'Todas as dificuldades');
            preencherSelectBancoQuestoes('bq-topico', facets.topicos || [], 'Todos os tópicos');
            preencherSelectBancoQuestoes('bq-tag', facets.tags || [], 'Todas as tags');
            buscarBancoQuestoes(true);
        })
        .catch((err) => {
            if (loading) loading.classList.add('hidden');
            alert('Erro ao carregar banco de questões: ' + (err.message || err));
        });
}

function limparFiltrosBancoQuestoes() {
    ['bq-q', 'bq-materia', 'bq-tipo', 'bq-ano', 'bq-dificuldade', 'bq-topico', 'bq-tag', 'bq-origem'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    bqOffset = 0;
    bqSelecionadas = new Set();
    bqSelecionadasMeta = {};
    atualizarTotalSelecionadasBancoQuestoes();
    inicializarBancoQuestoes();
}

function buscarBancoQuestoes(resetOffset) {
    if (resetOffset) bqOffset = 0;
    const loading = document.getElementById('bq-loading');
    const lista = document.getElementById('bq-lista');
    if (loading) loading.classList.remove('hidden');
    if (lista) lista.innerHTML = '';

    const filtro = filtroBancoQuestoesAtual();
    const params = new URLSearchParams({
        ...filtro,
        limit: String(bqLimit),
        offset: String(bqOffset)
    });
    fetch(`<?= URL ?>/professor/provas/banco-questoes/listar/<?= (int)$prova['id'] ?>?${params.toString()}`)
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) throw new Error(data.error || 'Erro ao listar questões');
            renderBancoQuestoes(data.data || {});
        })
        .catch((err) => {
            alert('Erro ao buscar questões: ' + (err.message || err));
        })
        .finally(() => {
            if (loading) loading.classList.add('hidden');
        });
}

function renderBancoQuestoes(payload) {
    const lista = document.getElementById('bq-lista');
    if (!lista) return;
    const questoes = Array.isArray(payload.questoes) ? payload.questoes : [];
    bqTotal = parseInt(payload.total || 0, 10) || 0;
    const limit = parseInt(payload.limit || bqLimit, 10) || bqLimit;
    const offset = parseInt(payload.offset || bqOffset, 10) || bqOffset;
    bqLimit = limit;
    bqOffset = offset;

    if (!questoes.length) {
        lista.innerHTML = '<div class="rounded-lg border border-gray-200 p-4 text-gray-600">Nenhuma questão encontrada com os filtros atuais.</div>';
    } else {
        lista.innerHTML = questoes.map((q) => {
            const id = String(q.id || '');
            const checked = bqSelecionadas.has(id) ? 'checked' : '';
            const enunciadoTexto = ((q.enunciado_html || q.enunciado || '') + '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            if (checked) {
                bqSelecionadasMeta[id] = {
                    id: id,
                    materia: String(q.materia || ''),
                    tipo: String(q.tipo || ''),
                    enunciado: enunciadoTexto
                };
            }
            const materia = q.materia ? `<span class="text-xs px-2 py-1 rounded bg-blue-50 text-blue-700">${escapeHtml(String(q.materia))}</span>` : '';
            const dif = q.dificuldade ? `<span class="text-xs px-2 py-1 rounded bg-amber-50 text-amber-700">${escapeHtml(String(q.dificuldade))}</span>` : '';
            const tipo = q.tipo ? `<span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">${escapeHtml(String(q.tipo))}</span>` : '';
            const origem = q.origem && q.origem.raw ? escapeHtml(String(q.origem.raw)) : '';
            const enunciado = q.enunciado_html || q.enunciado || '';
            return `
                <label class="block border border-gray-200 rounded-lg p-3 hover:border-indigo-300 cursor-pointer">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" data-bq-id="${escapeHtml(id)}" ${checked} onchange="toggleSelecionadaBancoQuestoes(this)">
                        <div class="min-w-0 w-full">
                            <div class="flex flex-wrap gap-2 mb-2">
                                <span class="text-xs px-2 py-1 rounded bg-indigo-50 text-indigo-700">ID ${escapeHtml(id)}</span>
                                ${materia}
                                ${tipo}
                                ${dif}
                            </div>
                            <div class="text-sm text-gray-800 leading-relaxed prose prose-sm max-w-none">${enunciado}</div>
                            ${origem ? `<div class="mt-2 text-xs text-gray-500">${origem}</div>` : ''}
                        </div>
                    </div>
                </label>
            `;
        }).join('');
    }

    const info = document.getElementById('bq-total-info');
    if (info) {
        const ate = Math.min(bqOffset + bqLimit, bqTotal);
        info.textContent = bqTotal > 0 ? `Mostrando ${bqOffset + 1}-${ate} de ${bqTotal}` : '0 resultados';
    }

    const btnPrev = document.getElementById('bq-prev');
    const btnNext = document.getElementById('bq-next');
    if (btnPrev) btnPrev.disabled = bqOffset <= 0;
    if (btnNext) btnNext.disabled = (bqOffset + bqLimit) >= bqTotal;
    renderSelecionadasLateralBancoQuestoes();
}

function toggleSelecionadaBancoQuestoes(el) {
    const id = String(el.getAttribute('data-bq-id') || '').trim();
    if (!id) return;
    if (el.checked) bqSelecionadas.add(id);
    else {
        bqSelecionadas.delete(id);
        delete bqSelecionadasMeta[id];
    }
    atualizarTotalSelecionadasBancoQuestoes();
}

function atualizarTotalSelecionadasBancoQuestoes() {
    const el = document.getElementById('bq-selecionadas');
    if (el) el.textContent = String(bqSelecionadas.size);
    renderSelecionadasLateralBancoQuestoes();
}

function renderSelecionadasLateralBancoQuestoes() {
    const box = document.getElementById('bq-selecionadas-lista');
    if (!box) return;
    const ids = Array.from(bqSelecionadas);
    if (!ids.length) {
        box.innerHTML = '<p class="text-xs text-gray-500">Nenhuma questão selecionada.</p>';
        return;
    }
    box.innerHTML = ids.map((id) => {
        const meta = bqSelecionadasMeta[id] || { id, materia: '', tipo: '', enunciado: '' };
        const texto = meta.enunciado ? meta.enunciado.slice(0, 120) + (meta.enunciado.length > 120 ? '...' : '') : 'Questão selecionada';
        return `
            <div class="border border-gray-200 rounded-lg p-2 bg-white">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-xs text-indigo-700 font-semibold">ID ${escapeHtml(meta.id)}</div>
                        <div class="text-xs text-gray-500">${escapeHtml([meta.materia, meta.tipo].filter(Boolean).join(' • '))}</div>
                    </div>
                    <button type="button" class="text-xs text-red-600 hover:text-red-700" onclick="removerSelecionadaBancoQuestoes('${escapeHtml(meta.id)}')">Remover</button>
                </div>
                <p class="text-xs text-gray-700 mt-1 leading-snug">${escapeHtml(texto)}</p>
            </div>
        `;
    }).join('');
}

function removerSelecionadaBancoQuestoes(id) {
    const key = String(id || '').trim();
    if (!key) return;
    bqSelecionadas.delete(key);
    delete bqSelecionadasMeta[key];
    const checks = document.querySelectorAll('input[data-bq-id]');
    checks.forEach((el) => {
        if (String(el.getAttribute('data-bq-id') || '') === key) {
            el.checked = false;
        }
    });
    atualizarTotalSelecionadasBancoQuestoes();
}

function mudarPaginaBancoQuestoes(direction) {
    const novoOffset = bqOffset + (direction * bqLimit);
    if (novoOffset < 0) return;
    if (direction > 0 && novoOffset >= bqTotal) return;
    bqOffset = novoOffset;
    buscarBancoQuestoes(false);
}

function importarSelecionadasBancoQuestoes() {
    if (bqSelecionadas.size === 0) {
        alert('Selecione ao menos uma questão para importar.');
        return;
    }
    if (!confirm('Importar as questões selecionadas para esta prova?')) {
        return;
    }
    const payload = { questao_ids: Array.from(bqSelecionadas) };
    fetch(`<?= URL ?>/professor/provas/banco-questoes/importar/<?= (int)$prova['id'] ?>`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then((r) => r.json())
    .then((data) => {
        if (!data.success) throw new Error(data.error || 'Erro ao importar');
        const falhas = Array.isArray(data.falhas) ? data.falhas.length : 0;
        const creditosConsumidos = Number(data.creditos_consumidos || 0);
        const detalheFalha = falhas > 0
            ? `\nDetalhe: ${String((data.falhas[0] && data.falhas[0].erro) || 'erro não informado')}`
            : '';
        const msg = falhas > 0
            ? `${data.importados} questão(ões) importada(s) com ${falhas} falha(s). Créditos consumidos: ${creditosConsumidos}.${detalheFalha}`
            : `${data.importados} questão(ões) importada(s) com sucesso. Créditos consumidos: ${creditosConsumidos}.`;
        alert(msg);
        window.location.href = '<?= URL ?>/professor/provas/editar/<?= (int)$prova['id'] ?>';
    })
    .catch((err) => {
        alert('Erro ao importar questões: ' + (err.message || err));
    });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Formulário de atualizar prova foi removido - informações são somente leitura

// Buscar objetivos de múltiplos planos de aula e preencher contexto adicional
async function buscarObjetivosPlanosParaContexto(planosIds) {
    const contextoField = document.getElementById('contexto-adicional-ia');
    if (!contextoField) return;
    
    if (planosIds.length === 0) {
        contextoField.value = '';
        return;
    }
    
    try {
        // Busca objetivos de todos os planos selecionados
        const promises = planosIds.map(async (planoId) => {
            try {
                const response = await fetch('<?= URL ?>/professor/jornadas/buscar-objetivo-plano-aula?plano_aula_id=' + planoId);
                const data = await response.json();
                if (data.success && data.objetivo) {
                    return data.objetivo.trim();
                }
            } catch (error) {
                console.error('Erro ao buscar objetivo do plano ' + planoId + ':', error);
            }
            return null;
        });
        
        const resultados = await Promise.all(promises);
        const objetivosFiltrados = resultados.filter(obj => obj !== null && obj !== '');
        
        // Concatena todos os objetivos, separando por quebra de linha dupla
        if (objetivosFiltrados.length > 0) {
            contextoField.value = objetivosFiltrados.join('\n\n');
        } else {
            contextoField.value = '';
        }
    } catch (error) {
        console.error('Erro ao buscar objetivos dos planos de aula:', error);
    }
}

// Atualizar contexto adicional quando planos são selecionados/deselecionados no modal
document.addEventListener('DOMContentLoaded', function() {
    // Aguarda o modal ser criado
    setTimeout(() => {
        const checkboxes = document.querySelectorAll('#modal-gerar-ia .plano-aula-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const planosSelecionados = Array.from(document.querySelectorAll('#modal-gerar-ia .plano-aula-checkbox:checked'))
                    .map(cb => cb.value);
                
                // Busca objetivos de todos os planos selecionados e preenche contexto adicional
                buscarObjetivosPlanosParaContexto(planosSelecionados);
            });
        });
        
        // Carregar objetivos automaticamente se já houver planos selecionados ao abrir o modal
        const planosSelecionadosInicial = Array.from(document.querySelectorAll('#modal-gerar-ia .plano-aula-checkbox:checked'))
            .map(cb => cb.value);
        if (planosSelecionadosInicial.length > 0) {
            buscarObjetivosPlanosParaContexto(planosSelecionadosInicial);
        }
    }, 100);
});

function removerQuestao(questaoId) {
    if (!confirm('Tem certeza que deseja remover esta questão?')) {
        return;
    }
    
    fetch('<?= URL ?>/professor/provas/remover-questao/<?= $prova['id'] ?>/' + questaoId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(async response => {
        if (!response.ok) {
            const text = await response.text();
            throw new Error(`Erro ${response.status}: ${text.substring(0, 200)}`);
        }
        return parseJsonResponse(response);
    })
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao remover questão'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}

function abrirModalEditar() {
    const modal = document.getElementById('modal-editar-questao');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function fecharModalEditar() {
    const modal = document.getElementById('modal-editar-questao');
    modal.classList.add('hidden');
    modal.style.display = 'none';
    // Limpa os dados
    questaoAtualEdicao = null;
    imagemUrlEdicao = null;
    document.getElementById('editarQuestaoForm').reset();
    document.getElementById('edit-opcoes-lista').innerHTML = '';
    opcoesCountEdicao = 0;
    document.getElementById('edit-opcoes-container').classList.add('hidden');
    const previewContainer = document.getElementById('edit-imagem-preview-container');
    if (previewContainer) previewContainer.classList.add('hidden');
}

// Fechar modal ao clicar fora
document.getElementById('modal-editar-questao').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalEditar();
    }
});

/** Converte HTML do enunciado (com eq-chip/MathML) de volta para texto + \\( latex \\) para exibir no editor. */
function enunciadoHtmlParaLaTeXSerializado(htmlStr) {
    if (!htmlStr || !htmlStr.trim()) return '';
    var div = document.createElement('div');
    div.innerHTML = htmlStr.trim();
    var out = [];
    function walk(node) {
        if (node.nodeType === 3) {
            out.push(node.textContent || '');
            return;
        }
        if (node.nodeType === 1) {
            if (node.classList && node.classList.contains('eq-chip')) {
                var latex = node.getAttribute('data-latex');
                if (latex != null) out.push(' \\( ' + latex + ' \\) ');
                return;
            }
            for (var i = 0; i < node.childNodes.length; i++) walk(node.childNodes[i]);
        }
    }
    for (var i = 0; i < div.childNodes.length; i++) walk(div.childNodes[i]);
    return out.join('').replace(/\s+/g, ' ').trim();
}

function editarQuestao(questaoId) {
    // Busca os dados da questão
    const questao = questoesData.find(q => parseInt(q.id) === parseInt(questaoId));
    
    if (!questao) {
        alert('Questão não encontrada');
        return;
    }
    
    questaoAtualEdicao = questao;
    
    // Preenche o formulário
    document.getElementById('edit-questao-id').value = questao.id;
    document.getElementById('edit-tipo-questao').value = questao.tipo;
    const editValorEl = document.getElementById('edit-valor');
    if (editValorEl) {
        editValorEl.value = questao.valor || 0;
    }
    const editEnunciadoEl = document.getElementById('edit-enunciado');
    const editEnunciadoValueEl = document.getElementById('edit-enunciado-value');
    if (editEnunciadoValueEl) editEnunciadoValueEl.value = questao.enunciado || '';
    var enunciadoStr = (questao.enunciado || '').trim();
    if (editEnunciadoEl) {
        if (enunciadoStr.indexOf('<img') !== -1) {
            editEnunciadoEl.innerHTML = enunciadoStr.replace(/\/public\/uploads\/provas\/questoes\//g, '/uploads/provas/questoes/');
            if ((enunciadoStr.indexOf('eq-chip') !== -1 || enunciadoStr.indexOf('eq-chip-math') !== -1) && typeof MathEditor !== 'undefined' && MathEditor.renderMathInEditor) {
                setTimeout(function() { MathEditor.renderMathInEditor(editEnunciadoEl); }, 0);
            }
        } else if ((enunciadoStr.indexOf('eq-chip') !== -1 || enunciadoStr.indexOf('data-latex') !== -1) && enunciadoStr.indexOf('<') !== -1) {
            // Enunciado foi salvo como HTML (chips + MathML); converter para \\( ... \\) e preencher para exibir chips renderizados
            var latexSerializado = enunciadoHtmlParaLaTeXSerializado(enunciadoStr);
            if (editEnunciadoValueEl) editEnunciadoValueEl.value = latexSerializado;
            if (typeof MathEditor !== 'undefined' && MathEditor.preencherDeLaTeX) {
                MathEditor.preencherDeLaTeX(editEnunciadoEl, latexSerializado);
            } else {
                editEnunciadoEl.innerHTML = enunciadoStr;
                if (typeof MathEditor !== 'undefined' && MathEditor.renderMathInEditor) setTimeout(function() { MathEditor.renderMathInEditor(editEnunciadoEl); }, 0);
            }
        } else if (enunciadoStr.indexOf('<') !== -1 && /<(div|p|br|span|b|i|u|strong|em)[\s>]|&nbsp;|<\/[a-z]+>/i.test(enunciadoStr)) {
            // HTML rico (parágrafos, formatação) — renderizar como HTML, não como texto
            editEnunciadoEl.innerHTML = enunciadoStr;
            if ((enunciadoStr.indexOf('eq-chip') !== -1 || enunciadoStr.indexOf('eq-chip-math') !== -1) && typeof MathEditor !== 'undefined' && MathEditor.renderMathInEditor) {
                setTimeout(function() { MathEditor.renderMathInEditor(editEnunciadoEl); }, 0);
            }
        } else if (typeof MathEditor !== 'undefined' && MathEditor.preencherDeLaTeX) {
            MathEditor.preencherDeLaTeX(editEnunciadoEl, enunciadoStr);
        } else {
            editEnunciadoEl.innerHTML = enunciadoStr;
            if ((enunciadoStr.indexOf('eq-chip') !== -1 || enunciadoStr.indexOf('eq-chip-math') !== -1) && typeof MathEditor !== 'undefined' && MathEditor.renderMathInEditor) {
                setTimeout(function() { MathEditor.renderMathInEditor(editEnunciadoEl); }, 0);
            }
        }
    }
    document.getElementById('edit-imagem-url').value = questao.imagem_url || '';

    var previewContainer = document.getElementById('edit-imagem-preview-container');
    var previewImg = document.getElementById('edit-imagem-preview');
    if (questao.imagem_url && questao.imagem_url.trim() !== '') {
        if (previewImg) previewImg.src = normalizarUrlImagem(questao.imagem_url);
        if (previewContainer) previewContainer.classList.remove('hidden');
    } else {
        if (previewContainer) previewContainer.classList.add('hidden');
    }

    // Configura alternativas se for múltipla escolha
    const container = document.getElementById('edit-opcoes-container');
    const lista = document.getElementById('edit-opcoes-lista');
    
    if (questao.tipo === 'multipla_escolha') {
        container.classList.remove('hidden');
        lista.innerHTML = '';
        opcoesCountEdicao = 0;
        
        if (questao.alternativas && questao.alternativas.length > 0) {
            questao.alternativas.forEach((alt, index) => {
                adicionarOpcaoEdicao(alt.texto, alt.correta == 1);
            });
        } else {
            // Se não tiver alternativas, adiciona duas vazias
            adicionarOpcaoEdicao();
            adicionarOpcaoEdicao();
        }
    } else {
        container.classList.add('hidden');
        lista.innerHTML = '';
        opcoesCountEdicao = 0;
    }
    
    // Abre o modal
    abrirModalEditar();
}

function adicionarOpcaoEdicao(texto = '', correta = false) {
    if (opcoesCountEdicao >= 5) {
        alert('Máximo de 5 alternativas permitidas (A, B, C, D, E)');
        return;
    }
    
    const letra = letras[opcoesCountEdicao];
    const index = opcoesCountEdicao;
    opcoesCountEdicao++;
    
    const container = document.getElementById('edit-opcoes-lista');
    const div = document.createElement('div');
    div.className = 'alt-item flex items-start gap-3 p-3 border border-gray-300 rounded-lg bg-gray-50';
    div.setAttribute('data-opcao-index', index);
    div.innerHTML = `
        <div class="alt-letter flex-shrink-0">${letra}</div>
        <div class="alt-input-wrap flex-1 min-w-0">
            <div class="alt-toolbar">
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('bold')" title="Negrito"><b>B</b></button>
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('italic')" title="Itálico"><i>I</i></button>
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('underline')" title="Sublinhado"><u>U</u></button>
                <div class="alt-tb-sep"></div>
                <button type="button" class="alt-tb-btn alt-tb-eq" onmousedown="event.preventDefault();abrirMathAlt(this)" title="Inserir equação">∑ eq</button>
                <div class="alt-tb-sep"></div>
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();abrirInserirImagemAlt(this)" title="Inserir ou colar imagem">📷</button>
            </div>
            <div class="alt-editor min-h-[60px] px-3 py-2 border border-gray-200 rounded-b-lg text-gray-900 outline-none" id="edit-opcao-editor-${index}" contenteditable="true" data-math="inline" data-placeholder="Alternativa ${letra}..."></div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <input type="radio" name="resposta_opcao_edit" value="${index}" id="edit-radio-${index}" class="w-5 h-5 text-blue-600 focus:ring-blue-500" ${correta ? 'checked' : ''}>
            <label for="edit-radio-${index}" class="text-sm text-gray-700 cursor-pointer">Correta</label>
        </div>
        <button type="button" onclick="removerOpcaoEdicao(this)" class="px-3 py-2 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors flex-shrink-0">
            Remover
        </button>
    `;
    container.appendChild(div);
    var ed = div.querySelector('.alt-editor');
    if (ed && texto) {
        var s = (texto || '').trim();
        if (s) {
        if (s.indexOf('eq-chip') !== -1 || s.indexOf('data-latex') !== -1) {
            var latexSerializado = typeof enunciadoHtmlParaLaTeXSerializado === 'function' ? enunciadoHtmlParaLaTeXSerializado(s) : s;
            if (typeof MathEditor !== 'undefined' && MathEditor.preencherDeLaTeX) {
                MathEditor.preencherDeLaTeX(ed, latexSerializado);
            } else {
                ed.innerHTML = s;
                if (typeof MathEditor !== 'undefined' && MathEditor.renderMathInEditor) setTimeout(function() { MathEditor.renderMathInEditor(ed); }, 0);
            }
        } else if (s.indexOf('<') !== -1 || s.indexOf('&') !== -1) {
            // HTML ou entidades (ex.: &nbsp;) — usar innerHTML para renderizar
            ed.innerHTML = s;
            if ((s.indexOf('eq-chip') !== -1 || s.indexOf('eq-chip-math') !== -1) && typeof MathEditor !== 'undefined' && MathEditor.renderMathInEditor) {
                setTimeout(function() { MathEditor.renderMathInEditor(ed); }, 0);
            }
        } else if (typeof MathEditor !== 'undefined' && MathEditor.preencherDeLaTeX && s.indexOf('\\(') !== -1) {
            MathEditor.preencherDeLaTeX(ed, s);
        } else {
            ed.textContent = s;
        }
        }
    }
    atualizarIndicesOpcoesEdicao();
}

function removerOpcaoEdicao(button) {
    const div = button.closest('div[data-opcao-index]');
    div.remove();
    opcoesCountEdicao--;
    atualizarIndicesOpcoesEdicao();
}

function atualizarIndicesOpcoesEdicao() {
    const container = document.getElementById('edit-opcoes-lista');
    const opcoes = container.querySelectorAll('.alt-item[data-opcao-index]');
    opcoes.forEach((opcao, index) => {
        const letra = letras[index];
        opcao.setAttribute('data-opcao-index', index);
        var letterEl = opcao.querySelector('.alt-letter');
        if (letterEl) letterEl.textContent = letra;
        var ed = opcao.querySelector('.alt-editor');
        if (ed) {
            ed.id = 'edit-opcao-editor-' + index;
            ed.setAttribute('data-placeholder', 'Alternativa ' + letra + '...');
        }
        const radio = opcao.querySelector('input[type="radio"]');
        if (radio) {
            radio.value = index;
            radio.id = 'edit-radio-' + index;
        }
        var label = opcao.querySelector('label');
        if (label) label.setAttribute('for', 'edit-radio-' + index);
    });
}

function uploadImagemQuestaoEdicao(input) {
    if (!input.files || !input.files[0]) return;
    
    const formData = new FormData();
    formData.append('imagem', input.files[0]);
    var token = getCsrfTokenForUpload();
    if (token) formData.append('_token', token);
    var headers = {};
    if (token) headers['X-CSRF-Token'] = token;
    
    const loadingBtn = input.nextElementSibling;
    const originalText = loadingBtn.textContent;
    loadingBtn.disabled = true;
    loadingBtn.textContent = 'Enviando...';
    
    fetch(typeof getUploadImagemQuestaoUrl === 'function' ? getUploadImagemQuestaoUrl() : '<?= rtrim(URL, "/") ?>/professor/provas/upload-imagem-questao', {
        method: 'POST',
        body: formData,
        credentials: 'include',
        headers: headers
    })
    .then(async response => {
        var data = await parseJsonResponse(response).catch(function() { return {}; });
        if (!response.ok) {
            throw new Error(data.error || ('Erro ' + response.status));
        }
        return data;
    })
    .then(data => {
        loadingBtn.disabled = false;
        loadingBtn.textContent = originalText;
        if (data.success) {
            imagemUrlEdicao = data.image_url;
            document.getElementById('edit-imagem-url').value = data.image_url;
            const previewContainer = document.getElementById('edit-imagem-preview-container');
            if (previewContainer) {
                const img = document.getElementById('edit-imagem-preview');
                if (img) { img.src = data.image_url; }
                previewContainer.classList.remove('hidden');
            }
        } else {
            alert('Erro: ' + (data.error || 'Erro ao fazer upload'));
        }
    })
    .catch(error => {
        loadingBtn.disabled = false;
        loadingBtn.textContent = originalText;
        alert('Erro ao fazer upload: ' + (error.message || 'tente novamente'));
        console.error(error);
    });
}

function removerImagemEdicao() {
    imagemUrlEdicao = null;
    const urlEl = document.getElementById('edit-imagem-url');
    if (urlEl) urlEl.value = '';
    const fileInput = document.getElementById('edit-imagem-questao');
    if (fileInput) fileInput.value = '';
    const previewContainer = document.getElementById('edit-imagem-preview-container');
    if (previewContainer) previewContainer.classList.add('hidden');
}

// Atualizar opções baseado no tipo (edição)
document.getElementById('edit-tipo-questao').addEventListener('change', function() {
    const container = document.getElementById('edit-opcoes-container');
    if (this.value === 'multipla_escolha') {
        container.classList.remove('hidden');
        if (opcoesCountEdicao === 0) {
            adicionarOpcaoEdicao();
            adicionarOpcaoEdicao();
        }
    } else {
        container.classList.add('hidden');
        document.getElementById('edit-opcoes-lista').innerHTML = '';
        opcoesCountEdicao = 0;
    }
});

// Formulário de editar questão
document.getElementById('editarQuestaoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var editEditor = document.getElementById('edit-enunciado');
    if (editEditor) {
        document.getElementById('edit-enunciado-value').value = editEditor.innerHTML || '';
    }
    const formData = new FormData(this);
    
    const questaoId = formData.get('questao_id');
    
    // Coleta opções se for múltipla escolha (dos contenteditable .alt-editor, preservando imagens e equações)
    if (formData.get('tipo') === 'multipla_escolha') {
        const opcoes = [];
        const itens = document.querySelectorAll('#edit-opcoes-lista .alt-item');
        const respostaIndex = formData.get('resposta_opcao_edit');
        
        if (itens.length < 2) {
            alert('Adicione pelo menos 2 alternativas');
            return;
        }
        
        if (respostaIndex === null || respostaIndex === '') {
            alert('Selecione a alternativa correta');
            return;
        }
        
        itens.forEach((item, index) => {
            var ed = item.querySelector('.alt-editor');
            var texto = ed ? (ed.innerHTML || '').trim() : '';
            if (texto) {
                opcoes.push({
                    texto: texto,
                    correta: index.toString() === respostaIndex ? 1 : 0,
                    ordem: index
                });
            }
        });
        
        if (opcoes.length < 2) {
            alert('Adicione pelo menos 2 alternativas válidas');
            return;
        }
        
        formData.set('alternativas', JSON.stringify(opcoes));
    }
    
    const data = {
        enunciado: formData.get('enunciado'),
        imagem_url: formData.get('imagem_url') || null,
        tipo: formData.get('tipo'),
        valor: parseFloat(formData.get('valor')) || 1.00,
        ordem: questaoAtualEdicao ? questaoAtualEdicao.ordem : 0,
        alternativas: formData.get('tipo') === 'multipla_escolha' ? JSON.parse(formData.get('alternativas')) : []
    };
    
    fetch('<?= URL ?>/professor/provas/atualizar-questao/<?= $prova['id'] ?>/' + questaoId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(async response => {
        if (!response.ok) {
            const text = await response.text();
            throw new Error(`Erro ${response.status}: ${text.substring(0, 200)}`);
        }
        return parseJsonResponse(response);
    })
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao atualizar questão'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
});

// Função para salvar informações da prova (removida - não há mais campos editáveis nesta seção)
function salvarInformacoesProva() {
    alert('As informações da prova são definidas pelo administrador no bloco de provas e não podem ser alteradas aqui.');
}

// Função para finalizar prova para aprovação
function finalizarProva() {
    var obrigatorio = parseInt('<?= (int)($numero_questoes_obrigatorio ?? 0) ?>', 10);
    if (obrigatorio > 0) {
        var atual = document.querySelectorAll('#lista-questoes .questao-item, .questao-item').length;
        if (typeof window.totalQuestoesAtual === 'number') {
            atual = window.totalQuestoesAtual;
        } else if (document.getElementById('numero-questoes-atual')) {
            atual = parseInt(document.getElementById('numero-questoes-atual').textContent, 10) || 0;
        }
        if (atual < obrigatorio) {
            alert('Faltam ' + (obrigatorio - atual) + ' questão(ões). O total deve ser ' + obrigatorio + ' para enviar para aprovação.');
            return;
        }
        if (atual > obrigatorio) {
            alert('Há ' + (atual - obrigatorio) + ' questão(ões) a mais. Remova para que o total seja exatamente ' + obrigatorio + '.');
            return;
        }
    }
    if (!confirm('Deseja finalizar esta prova e enviar para aprovação da coordenação? Após finalizar, você não poderá mais editar a prova até que seja aprovada ou rejeitada.')) {
        return;
    }
    
    fetch('<?= URL ?>/professor/provas/finalizar/<?= $prova['id'] ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(async response => {
        // Verifica se a resposta é JSON válido
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error('Resposta do servidor não é JSON válido: ' + text.substring(0, 200));
        }
        
        const data = await response.json();
        if (data.success) {
            alert('Prova finalizada com sucesso! Aguardando aprovação da coordenação.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao finalizar prova'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}
</script>
