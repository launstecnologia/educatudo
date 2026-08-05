<!-- Header Section -->
<div class="mb-8">
    <div>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Criar e Editar Exercícios - <?= htmlspecialchars($modulo['titulo'] ?? 'Módulo') ?>
            </h2>
            <p class="text-gray-600">
                Jornada: <?= htmlspecialchars($jornada['titulo']) ?> • 
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?>
            </p>
        </div>
        <?php
        if (!class_exists('CreditosModuleRegistry', false)) {
            require_once __DIR__ . '/../../../Core/CreditosModuleRegistry.php';
        }
        $tudicoinsAcaoExercicioIa = \CreditosModuleRegistry::acaoIaDisponivel('gerar_exercicio_ia_professor');
        ?>
        <div class="flex flex-wrap gap-3 mt-4">
            <a href="<?= URL ?>/professor/jornadas/modulos/<?= (int)$modulo['id'] ?>/exercicios" class="bg-gray-100 text-gray-800 px-6 py-3 rounded-xl hover:bg-gray-200 transition-all duration-300 shadow-sm border border-gray-300">
                Lista de Exercícios
            </a>
            <?php if ($tudicoinsAcaoExercicioIa): ?>
            <button onclick="abrirModalLerImagem()" class="bg-green-600 text-white px-6 py-3 rounded-xl hover:bg-green-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                Extrair Imagem
            </button>
            <button onclick="abrirModalGerarIA()" class="bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                Exercícios com a Tudinha
            </button>
            <?php endif; ?>
            <button type="button" onclick="abrirModalBancoQuestoes()" class="bg-indigo-600 text-white px-6 py-3 rounded-xl hover:bg-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                Banco de Questões
            </button>
            <button type="button" onclick="abrirModalColarJson()" class="bg-purple-600 text-white px-6 py-3 rounded-xl hover:bg-purple-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                📚 Importar da Apostila
            </button>
            <a href="<?= URL ?>/professor/jornadas/<?= $modulo['jornada_id'] ?>/modulos"
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                Sair
            </a>
        </div>
    </div>
</div>

<style>
.select-safari {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M5 7.5L10 12.5L15 7.5" stroke="%236B7280" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>');
    background-repeat: no-repeat;
    background-position: right 0.65rem center;
    background-size: 0.95rem;
    padding-right: 2rem;
}

@keyframes tudinha-bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

</style>

<!-- Formulário de Criação Manual -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-blue-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-6">Criar Exercício Manualmente</h3>
    
    <!-- Form Manual -->
    <div id="form-manual" class="exercicio-form">
        <form id="adicionarExercicioForm" class="space-y-4">
            <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
            <input type="hidden" name="exercicio_id" id="exercicio-id-edicao-pagina" value="">
            <input type="hidden" name="status" id="status-edicao-pagina" value="publicado">
            <input type="hidden" name="imagem_url" id="imagem-url-edicao-pagina" value="">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Exercício *</label>
                    <select name="tipo" required class="select-safari w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="alternativas">Alternativas (Múltipla Escolha)</option>
                        <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                        <option value="preencher_lacuna">Preencha a Lacuna (Arrastar Palavra)</option>
                        <option value="dissertativa">Dissertativa</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pontuação</label>
                    <input type="number" name="pontuacao" step="0.1" min="0" value="1.00" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Enunciado *</label>
                <input type="hidden" name="enunciado" id="enunciado-jornada" required>
                <div class="editor-toolbar">
                    <button type="button" class="tb-btn" title="Negrito" onmousedown="event.preventDefault();saveSelJornada()" onclick="fmtEnunciadoJornada('bold')"><b>B</b></button>
                    <button type="button" class="tb-btn" title="Itálico" onmousedown="event.preventDefault()" onclick="fmtEnunciadoJornada('italic')"><i>I</i></button>
                    <button type="button" class="tb-btn" title="Sublinhado" onmousedown="event.preventDefault()" onclick="fmtEnunciadoJornada('underline')"><u>U</u></button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn" title="Lista com marcadores" onmousedown="event.preventDefault();saveSelJornada();fmtEnunciadoJornada('insertUnorderedList')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/></svg>
                    </button>
                    <button type="button" class="tb-btn" title="Lista numerada" onmousedown="event.preventDefault();saveSelJornada();fmtEnunciadoJornada('insertOrderedList')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4M4 10h2" stroke-linecap="round"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1" stroke-linecap="round"/></svg>
                    </button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn tb-font-size" title="Diminuir letra" onmousedown="event.preventDefault();fmtFontSizeJornada(-1)"><span>A−</span></button>
                    <button type="button" class="tb-btn tb-font-size" title="Aumentar letra" onmousedown="event.preventDefault();fmtFontSizeJornada(1)"><span>A+</span></button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn" title="Alinhar esquerda" onmousedown="event.preventDefault()" onclick="fmtEnunciadoJornada('justifyLeft')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
                    </button>
                    <button type="button" class="tb-btn" title="Centralizar" onmousedown="event.preventDefault()" onclick="fmtEnunciadoJornada('justifyCenter')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                    </button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-math-btn" data-math-open="editor-enunciado-jornada" onmousedown="event.preventDefault()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 7h16M4 12h10M4 17h7"/><path d="M18 14l2 2-2 2M22 14l-2 2 2 2"/></svg>
                        ∑ Inserir Equação
                    </button>
                    <div class="tb-sep"></div>
                    <input type="file" id="enunciado-jornada-insert-image-input" accept="image/*" class="hidden">
                    <button type="button" class="tb-math-btn tb-img-btn" title="Inserir ou colar imagem (Ctrl+V)" onclick="document.getElementById('enunciado-jornada-insert-image-input').click()">
                        <svg class="tb-img-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        Imagem
                    </button>
                </div>
                <div id="editor-enunciado-jornada" class="rich-editor" contenteditable="true" data-math="inline"
                     data-placeholder="Digite o enunciado aqui... Use ∑ Inserir Equação para adicionar fórmulas visuais."></div>
            </div>
            
            <div id="opcoes-container" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Alternativas (Múltipla Escolha) *</label>
                <div id="opcoes-lista" class="alt-list mb-3">
                    <?php foreach (['a','b','c','d','e'] as $i => $letra): $l = chr(65+$i); ?>
                    <div class="alt-item" data-opcao-index="<?= $i ?>">
                        <div class="alt-letter"><?= $l ?></div>
                        <div class="alt-input-wrap">
                            <div class="alt-toolbar">
                                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('bold')" title="Negrito"><b>B</b></button>
                                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('italic')" title="Itálico"><i>I</i></button>
                                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('underline')" title="Sublinhado"><u>U</u></button>
                                <div class="alt-tb-sep"></div>
                                <button type="button" class="alt-tb-btn alt-tb-eq" onmousedown="event.preventDefault();abrirMathAltJornada(this)" title="Inserir equação">∑ eq</button>
                                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();abrirInserirImagemAltJornada(this)" title="Inserir ou colar imagem">📷</button>
                            </div>
                            <div class="alt-editor" id="opcao-editor-jornada-<?= $i ?>" contenteditable="true" data-math="inline" data-placeholder="Alternativa <?= $l ?>..."></div>
                        </div>
                        <div class="flex items-center space-x-2 flex-shrink-0">
                            <input type="checkbox" name="resposta_opcao[]" value="<?= $i ?>" id="radio-jornada-<?= $i ?>" class="w-5 h-5 text-blue-600 focus:ring-blue-500">
                            <label for="radio-jornada-<?= $i ?>" class="alt-correct">Gabarito</label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="file" id="alt-jornada-insert-image-input" accept="image/*" class="hidden">
                <span class="text-xs text-gray-500">Preencha pelo menos 2 alternativas e marque uma ou mais como gabarito.</span>
            </div>

            <div id="lacuna-container" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Palavras/Blocos para Arrastar *</label>
                <textarea id="lacuna_opcoes" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Uma palavra por linha&#10;mitocôndria&#10;lisossomo&#10;ribossomo"></textarea>
                <p class="text-xs text-gray-500 mt-1">No enunciado, use <strong>___</strong> para marcar a lacuna.</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Gabarito</label>
                <p id="gabarito-hint-alternativas" class="text-xs text-gray-500 mb-1 hidden">Defina o gabarito marcando uma ou mais alternativas.</p>
                <textarea name="resposta_correta" id="campo_resposta_correta" rows="2"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50"
                          placeholder="Marque as alternativas de gabarito (campo preenchido automaticamente)"></textarea>
            </div>
            
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                Adicionar Exercício
            </button>
        </form>
    </div>

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

        <p id="jornada-colar-json-resultado" class="text-sm text-gray-500 mt-2"></p>

        <div class="flex justify-between items-center mt-4">
            <span id="apostila-selecionados-count" class="text-sm text-gray-600"></span>
            <div class="flex gap-3">
                <button type="button" onclick="fecharModalColarJson()" class="px-4 py-2 text-gray-600 hover:text-gray-900">Cancelar</button>
                <button type="button" onclick="importarSelecionados()" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Importar selecionados</button>
            </div>
        </div>

        <details class="mt-4">
            <summary class="text-sm text-gray-500 cursor-pointer">Opção avançada: colar JSON manualmente</summary>
            <textarea id="jornada-colar-json-textarea" rows="6" class="w-full text-sm border border-gray-300 rounded-lg p-3 font-mono mt-2" placeholder='[{"enunciado": "...", "tipo": "objetiva", "alternativas": ["...", "..."], "gabarito": "..."}]'></textarea>
            <button type="button" onclick="importarExerciciosJson()" class="mt-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">Importar JSON colado</button>
        </details>
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
            <input id="bq-q" type="text" placeholder="Buscar por texto ou ID"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <select id="bq-materia" class="select-safari w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
            <select id="bq-tipo" class="select-safari w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
            <input id="bq-ano" type="text" placeholder="Ano (ex: 2025)"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <select id="bq-dificuldade" class="select-safari w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
            <select id="bq-topico" class="select-safari w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
            <select id="bq-tag" class="select-safari w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
            <select id="bq-origem" class="select-safari w-full px-3 py-2 border border-gray-300 rounded-lg bg-white"></select>
        </div>

        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button type="button" onclick="buscarBancoQuestoes(true)"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                Buscar
            </button>
            <button type="button" onclick="limparFiltrosBancoQuestoes()"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                Limpar Filtros
            </button>
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
                <button type="button" id="bq-prev" onclick="mudarPaginaBancoQuestoes(-1)"
                        class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-100">
                    Anterior
                </button>
                <button type="button" id="bq-next" onclick="mudarPaginaBancoQuestoes(1)"
                        class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-100">
                    Próxima
                </button>
            </div>
            <div class="text-sm text-gray-600">Selecione as questões e clique em OK para importar.</div>
        </div>

        <div class="flex justify-end mt-4">
            <button type="button" onclick="importarSelecionadasBancoQuestoes()"
                    class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors mr-2">
                OK (Importar)
            </button>
            <button type="button" onclick="fecharModalBancoQuestoes()"
                    class="px-5 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                Fechar
            </button>
        </div>
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

<!-- Modal Gerar Exercícios com IA -->
<div id="modal-gerar-ia" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-900">Gerar exercícios pela Tudinha</h3>
            <button onclick="fecharModalGerarIA()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form id="gerarExercicioIAForm" class="space-y-6">
            <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contexto Adicional (Opcional)</label>
                <textarea name="contexto" id="contexto-adicional-ia" rows="4"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Descreva o contexto para a Tudinha (tema, objetivo da aula, habilidade da turma, etc.)."></textarea>
                <p class="text-xs text-gray-500 mt-1">
                    Esse contexto ajuda a Tudinha a gerar exercícios mais aderentes ao momento da turma.
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Questão *</label>
                    <select name="tipo" required 
                            class="select-safari w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="alternativas">Múltipla Escolha</option>
                        <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                        <option value="dissertativa">Dissertativa</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Série / Ano</label>
                    <select name="serie" id="serie-ia"
                            class="select-safari w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
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
                    Informe a quantidade desejada por nível. Total = soma das questões.
                </p>
                <div class="text-xs text-gray-600 mt-2 space-y-1 border-l-2 border-gray-200 pl-3">
                    <p><strong>Fácil:</strong> Memorização e compreensão. Perguntas diretas.</p>
                    <p><strong>Médio:</strong> Aplicação. Situações-problema.</p>
                    <p><strong>Difícil:</strong> Análise e avaliação. Múltiplos conceitos; distratores plausíveis.</p>
                    <p><strong>Desafio:</strong> Estilo vestibular/concurso (ENEM, FUVEST, ITA). Questões complexas, enunciados longos e interdisciplinares.</p>
                </div>
                <p id="total-questoes-ia" class="text-sm font-medium text-gray-700 mt-3">Total: 0 questões</p>
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

<script>
let opcoesCount = 5;
const letras = ['A', 'B', 'C', 'D', 'E'];

const baseUrl = '<?= rtrim(URL, "/") ?>';
const urlListaExerciciosModulo = '<?= URL ?>/professor/jornadas/modulos/<?= (int)$modulo['id'] ?>/exercicios';
let modoEdicaoPagina = false;

async function parseJsonResponse(response) {
    const contentType = response.headers.get('content-type') || '';
    const text = await response.text();
    if (!contentType.includes('application/json')) {
        throw new Error(`Resposta inválida do servidor (HTTP ${response.status}). ${text.substring(0, 300)}`);
    }
    try {
        return JSON.parse(text);
    } catch (e) {
        throw new Error(`JSON inválido (HTTP ${response.status}). ${text.substring(0, 300)}`);
    }
}

var savedSelJornada = null;
function saveSelJornada() {
    savedSelJornada = null;
    var el = document.getElementById('editor-enunciado-jornada');
    if (!el) return;
    var sel = window.getSelection();
    try {
        if (sel.rangeCount && sel.anchorNode && el.contains(sel.anchorNode)) {
            savedSelJornada = sel.getRangeAt(0).cloneRange();
        }
    } catch (e) {}
}

function applyListToEditorJornada(el, ordered) {
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

function fmtEnunciadoJornada(cmd) {
    var el = document.getElementById('editor-enunciado-jornada');
    if (!el) return;
    el.focus();
    var sel = window.getSelection();
    if (savedSelJornada) {
        try {
            sel.removeAllRanges();
            sel.addRange(savedSelJornada);
        } catch (e) {}
        savedSelJornada = null;
    }
    if (cmd === 'insertOrderedList' || cmd === 'insertUnorderedList') {
        applyListToEditorJornada(el, cmd === 'insertOrderedList');
    } else {
        document.execCommand(cmd, false, null);
    }
}

function fmtFontSizeJornada(delta) {
    var el = document.getElementById('editor-enunciado-jornada');
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

var savedSelEdicao = null;
function saveSelEdicao() {
    savedSelEdicao = null;
    var el = document.getElementById('editor-enunciado-edicao');
    if (!el) return;
    var sel = window.getSelection();
    try {
        if (sel.rangeCount && sel.anchorNode && el.contains(sel.anchorNode)) {
            savedSelEdicao = sel.getRangeAt(0).cloneRange();
        }
    } catch (e) {}
}

function applyListToEditorEdicao(el, ordered) {
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

function fmtEnunciadoEdicao(cmd) {
    var el = document.getElementById('editor-enunciado-edicao');
    if (!el) return;
    el.focus();
    var sel = window.getSelection();
    if (savedSelEdicao) {
        try {
            sel.removeAllRanges();
            sel.addRange(savedSelEdicao);
        } catch (e) {}
        savedSelEdicao = null;
    }
    if (cmd === 'insertOrderedList' || cmd === 'insertUnorderedList') {
        applyListToEditorEdicao(el, cmd === 'insertOrderedList');
    } else {
        document.execCommand(cmd, false, null);
    }
    syncEnunciadoEdicao();
}

function fmtFontSizeEdicao(delta) {
    var el = document.getElementById('editor-enunciado-edicao');
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
    syncEnunciadoEdicao();
}

function syncEnunciadoEdicao() {
    var editor = document.getElementById('editor-enunciado-edicao');
    var hidden = document.getElementById('edit-enunciado-hidden');
    if (!editor || !hidden) return;
    var html = (editor.innerHTML || '').trim();
    if (html && (editor.querySelector('ul, ol, img') || /<ul|<ol|<li|<img/i.test(html))) {
        hidden.value = html;
    } else if (typeof MathEditor !== 'undefined' && MathEditor.serializarParaLaTeX) {
        hidden.value = MathEditor.serializarParaLaTeX(editor);
    } else {
        hidden.value = (editor.innerText || '').trim();
    }
}

function abrirMathAltJornada(btn) {
    var item = btn.closest('[data-opcao-index]');
    if (!item) return;
    var idx = item.getAttribute('data-opcao-index');
    var ed = item.querySelector('.alt-editor') || document.getElementById('opcao-editor-jornada-' + idx);
    if (ed && typeof MathEditor !== 'undefined') MathEditor.abrir(ed);
}

// ── Imagem no enunciado e alternativas (igual à prova) ──
var altEditorForImageJornada = null;

function uploadImageForEditorJornada(file) {
    if (!file || !file.type.startsWith('image/')) return Promise.reject(new Error('Arquivo não é uma imagem'));
    var formData = new FormData();
    formData.append('imagem', file);
    return fetch(baseUrl + '/professor/jornadas/modulos/upload-imagem-exercicio', { method: 'POST', body: formData })
        .then(function(r) {
            if (!r.ok) throw new Error('Upload falhou: ' + r.status);
            return r.json();
        })
        .then(function(data) {
            if (!data || !data.success) throw new Error(data && data.error ? data.error : 'Erro no upload');
            var url = data.image_url;
            if (!url || typeof url !== 'string') throw new Error('URL da imagem não retornada');
            return url;
        });
}

function normalizarUrlImagemJornada(url) {
    if (!url) return '';
    url = String(url).trim();
    if (url.indexOf('http://') === 0 || url.indexOf('https://') === 0 || url.indexOf('data:') === 0) return url;
    return baseUrl + (url.charAt(0) === '/' ? '' : '/') + url;
}

function insertImageInEditorJornada(editorEl, url) {
    if (!editorEl || !url) return;
    url = normalizarUrlImagemJornada(url);
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

function abrirInserirImagemAltJornada(btn) {
    var item = btn.closest('[data-opcao-index]');
    if (!item) return;
    var ed = item.querySelector('.alt-editor');
    if (ed) {
        altEditorForImageJornada = ed;
        ed.focus();
        document.getElementById('alt-jornada-insert-image-input').click();
    }
}

function applyImageSizeJornada(img, pct) {
    if (!img || img.tagName !== 'IMG') return;
    pct = Math.max(10, Math.min(100, parseInt(pct, 10) || 100));
    img.style.maxWidth = pct + '%';
    img.style.width = pct + '%';
    img.style.height = 'auto';
}

function getImageSizePctJornada(img) {
    if (!img || img.tagName !== 'IMG') return 100;
    var w = img.style.width || img.style.maxWidth || '';
    if (w) { var n = parseInt(w, 10); if (!isNaN(n)) return n; }
    var styleAttr = img.getAttribute('style') || '';
    var match = styleAttr.match(/(?:width|max-width)\s*:\s*(\d+)\s*%?/i);
    return match ? (parseInt(match[1], 10) || 100) : 100;
}

function showImageResizePopoverJornada(img) {
    var id = 'editor-image-resize-popover-jornada';
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
            if (pct) applyImageSizeJornada(img, parseInt(pct, 10));
            else if (step === '-') applyImageSizeJornada(img, Math.max(10, getImageSizePctJornada(img) - 15));
            else if (step === '+') applyImageSizeJornada(img, Math.min(100, getImageSizePctJornada(img) + 15));
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

function uploadImagemEnunciadoManual(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const imgEl = document.getElementById('imagem-enunciado-img');
    const previewEl = document.getElementById('imagem-enunciado-preview');
    const actionsEl = document.getElementById('imagem-enunciado-actions');
    if (!imgEl || !previewEl) return;
    imgEl.src = URL.createObjectURL(file);
    previewEl.classList.remove('hidden');
    if (actionsEl) actionsEl.classList.add('hidden');
    const formData = new FormData();
    formData.append('imagem', file);
    fetch(baseUrl + '/professor/jornadas/modulos/upload-imagem-exercicio', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('imagem-url-manual').value = data.image_url;
                /* mantém o preview com blob URL para não quebrar se a URL do servidor não carregar */
            } else alert('Erro: ' + (data.error || 'Upload falhou'));
        })
        .catch(() => alert('Erro ao enviar imagem'));
}

function removerImagemEnunciadoManual() {
    const imgEl = document.getElementById('imagem-enunciado-img');
    if (imgEl && imgEl.src && imgEl.src.startsWith('blob:')) URL.revokeObjectURL(imgEl.src);
    document.getElementById('imagem-url-manual').value = '';
    document.getElementById('imagem-enunciado').value = '';
    document.getElementById('imagem-enunciado-preview').classList.add('hidden');
    var actions = document.getElementById('imagem-enunciado-actions');
    if (actions) actions.classList.remove('hidden');
}

function uploadImagemEnunciadoEdicao(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const imgEl = document.getElementById('edit-imagem-enunciado-img');
    const previewEl = document.getElementById('edit-imagem-enunciado-preview');
    const actionsEl = document.getElementById('edit-imagem-enunciado-actions');
    if (!imgEl || !previewEl) return;
    imgEl.src = URL.createObjectURL(file);
    previewEl.classList.remove('hidden');
    if (actionsEl) actionsEl.classList.add('hidden');
    const formData = new FormData();
    formData.append('imagem', file);
    fetch(baseUrl + '/professor/jornadas/modulos/upload-imagem-exercicio', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit-imagem-url').value = data.image_url;
                /* mantém o preview com blob URL */
            } else alert('Erro: ' + (data.error || 'Upload falhou'));
        })
        .catch(() => alert('Erro ao enviar imagem'));
}

function removerImagemEnunciadoEdicao() {
    const imgEl = document.getElementById('edit-imagem-enunciado-img');
    if (imgEl && imgEl.src && imgEl.src.startsWith('blob:')) URL.revokeObjectURL(imgEl.src);
    document.getElementById('edit-imagem-url').value = '';
    const inp = document.getElementById('edit-imagem-enunciado');
    if (inp) inp.value = '';
    document.getElementById('edit-imagem-enunciado-preview').classList.add('hidden');
    var editActions = document.getElementById('edit-imagem-enunciado-actions');
    if (editActions) editActions.classList.remove('hidden');
}

function colarImagemEnviar(base64, callbackUrl, callbackPreview) {
    const formData = new FormData();
    formData.append('imagem_base64', base64);
    fetch(baseUrl + '/professor/jornadas/modulos/upload-imagem-exercicio', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (callbackUrl) callbackUrl(data.image_url);
                if (callbackPreview) callbackPreview(data.image_url);
            } else alert('Erro ao colar imagem: ' + (data.error || ''));
        })
        .catch(() => alert('Erro ao enviar imagem colada'));
}

function ativarColarImagemManual() {
    const once = (e) => {
        e.preventDefault();
        const item = Array.from(e.clipboardData.items || []).find(x => x.type.startsWith('image/'));
        if (item) {
            const file = item.getAsFile();
            const reader = new FileReader();
            reader.onload = () => {
                var imgEl = document.getElementById('imagem-enunciado-img');
                var previewEl = document.getElementById('imagem-enunciado-preview');
                var actionsEl = document.getElementById('imagem-enunciado-actions');
                if (imgEl && previewEl) {
                    imgEl.src = reader.result;
                    previewEl.classList.remove('hidden');
                    if (actionsEl) actionsEl.classList.add('hidden');
                }
                colarImagemEnviar(reader.result, (url) => {
                    document.getElementById('imagem-url-manual').value = url;
                    /* preview continua com data URL para não quebrar */
                });
            };
            reader.readAsDataURL(file);
        } else alert('Nenhuma imagem na área de transferência. Copie uma imagem e tente novamente.');
        document.removeEventListener('paste', once);
    };
    document.addEventListener('paste', once);
    alert('Pressione Ctrl+V (ou Cmd+V) agora para colar a imagem.');
}

function ativarColarImagemEdicao() {
    const once = (e) => {
        e.preventDefault();
        const item = Array.from(e.clipboardData.items || []).find(x => x.type.startsWith('image/'));
        if (item) {
            const file = item.getAsFile();
            const reader = new FileReader();
            reader.onload = () => {
                var imgEl = document.getElementById('edit-imagem-enunciado-img');
                var previewEl = document.getElementById('edit-imagem-enunciado-preview');
                var actionsEl = document.getElementById('edit-imagem-enunciado-actions');
                if (imgEl && previewEl) {
                    imgEl.src = reader.result;
                    previewEl.classList.remove('hidden');
                    if (actionsEl) actionsEl.classList.add('hidden');
                }
                colarImagemEnviar(reader.result, null, (url) => {
                    document.getElementById('edit-imagem-url').value = url;
                    /* preview continua com data URL */
                });
            };
            reader.readAsDataURL(file);
        } else alert('Nenhuma imagem na área de transferência.');
        document.removeEventListener('paste', once);
    };
    document.addEventListener('paste', once);
    alert('Pressione Ctrl+V (ou Cmd+V) agora para colar a imagem.');
}

const btnColarImagemManual = document.getElementById('btn-colar-imagem');
if (btnColarImagemManual) {
    btnColarImagemManual.addEventListener('click', ativarColarImagemManual);
}

function adicionarOpcao() {
    if (opcoesCount >= 5) {
        alert('Máximo de 5 alternativas permitidas (A, B, C, D, E)');
        return;
    }
    
    const letra = letras[opcoesCount];
    opcoesCount++;
    const container = document.getElementById('opcoes-lista');
    const div = document.createElement('div');
    div.className = 'flex items-start space-x-3 p-3 border border-gray-300 rounded-lg bg-gray-50';
    div.setAttribute('data-opcao-index', opcoesCount - 1);
    const inputId = `opcao-${opcoesCount - 1}`;
    div.innerHTML = `
        <div class="flex items-center justify-center w-8 h-8 bg-blue-600 text-white rounded-full font-bold text-sm flex-shrink-0 mt-1">
            ${letra}
        </div>
        <div class="flex-1 min-w-0">
            <textarea name="opcoes[]" id="${inputId}" rows="2" placeholder="Digite o texto da alternativa ${letra}" 
                   class="w-full min-h-[2.5rem] resize-y px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm leading-relaxed"
                   required></textarea>
        </div>
        <div class="flex items-center space-x-2 flex-shrink-0 pt-1">
            <input type="checkbox" name="resposta_opcao[]" value="${opcoesCount - 1}" id="radio-${opcoesCount - 1}" 
                   class="w-5 h-5 text-blue-600 focus:ring-blue-500">
            <label for="radio-${opcoesCount - 1}" class="text-sm text-gray-700 cursor-pointer">Gabarito</label>
        </div>
        <button type="button" onclick="removerOpcao(this)" 
                class="px-3 py-2 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">
            Remover
        </button>
    `;
    container.appendChild(div);
    atualizarIndicesOpcoes();
}

function removerOpcao(button) {
    const div = button.closest('div[data-opcao-index]');
    const index = parseInt(div.getAttribute('data-opcao-index'));
    div.remove();
    opcoesCount--;
    atualizarIndicesOpcoes();
}

function atualizarIndicesOpcoes() {
    const container = document.getElementById('opcoes-lista');
    if (!container) return;
    const opcoes = container.querySelectorAll('div[data-opcao-index]');
    opcoes.forEach((opcao, index) => {
        const letra = letras[index];
        opcao.setAttribute('data-opcao-index', index);
        const letraEl = opcao.querySelector('.alt-letter') || opcao.querySelector('.bg-blue-600');
        if (letraEl) letraEl.textContent = letra;
        const editor = opcao.querySelector('.alt-editor');
        const textarea = opcao.querySelector('textarea[name="opcoes[]"]');
        if (editor) editor.setAttribute('data-placeholder', 'Alternativa ' + letra + '...');
        if (textarea) textarea.placeholder = 'Digite o texto da alternativa ' + letra;
        const radio = opcao.querySelector('input[type="checkbox"]');
        if (radio) {
            radio.value = index;
            radio.id = 'radio-jornada-' + index;
            radio.name = 'resposta_opcao[]';
        }
        const label = opcao.querySelector('label');
        if (label) label.setAttribute('for', 'radio-jornada-' + index);
    });
    sincronizarGabaritoDoRadio();
}

(function() {
    function obterConteudoEditorRich(ed) {
        if (!ed) return '';
        var html = (ed.innerHTML || '').trim();
        if (!html) return '';
        // Quando houver imagem/lista, preserva o HTML para não perder o conteúdo visual.
        if (ed.querySelector('ul, ol, img') || /<ul|<ol|<li|<img/i.test(html)) {
            return html;
        }
        if (typeof MathEditor !== 'undefined' && MathEditor.serializarParaLaTeX) {
            var serializado = MathEditor.serializarParaLaTeX(ed);
            if (serializado && String(serializado).trim() !== '') {
                return String(serializado).trim();
            }
        }
        return (ed.innerText || '').trim();
    }

    var form = document.getElementById('adicionarExercicioForm');
    if (!form) return;
    form.addEventListener('submit', function(e) {
    e.preventDefault();
    var editorEnunciado = document.getElementById('editor-enunciado-jornada');
    var hiddenEnunciado = document.getElementById('enunciado-jornada');
    if (editorEnunciado && hiddenEnunciado) {
        hiddenEnunciado.value = obterConteudoEditorRich(editorEnunciado);
    }
    var formData = new FormData(this);
    
    // Coleta opções se for múltipla escolha (a partir dos .alt-editor)
    if (formData.get('tipo') === 'alternativas' || formData.get('tipo') === 'verdadeiro_falso') {
        const opcoes = [];
        const altItems = document.querySelectorAll('#opcoes-lista .alt-item');
        const respostaIndexes = formData.getAll('resposta_opcao[]');
        
        if (altItems.length < 2) {
            alert('Adicione pelo menos 2 alternativas');
            e.preventDefault();
            return;
        }
        
        if (!respostaIndexes || respostaIndexes.length === 0) {
            alert('Selecione pelo menos uma alternativa de gabarito');
            e.preventDefault();
            return;
        }
        
        const respostaSet = new Set(respostaIndexes.map(v => String(v)));
        altItems.forEach((item, index) => {
            var ed = item.querySelector('.alt-editor');
            var texto = obterConteudoEditorRich(ed);
            var letraEl = item.querySelector('.alt-letter');
            var letra = letraEl ? letraEl.textContent.trim() : letras[index];
            if (texto) {
                opcoes.push({
                    letra: letra,
                    texto: texto.trim(),
                    correta: respostaSet.has(index.toString())
                });
            }
        });
        
        if (opcoes.length < 2) {
            alert('Adicione pelo menos 2 alternativas válidas');
            e.preventDefault();
            return;
        }
        
        formData.set('questoes_json', JSON.stringify({ opcoes: opcoes }));
        const letrasCorretas = respostaIndexes
            .map(v => letras[parseInt(v, 10)])
            .filter(Boolean);
        formData.set('resposta_correta', letrasCorretas.join('|'));
    } else if (formData.get('tipo') === 'preencher_lacuna') {
        const enunciado = (formData.get('enunciado') || '').toString();
        if (enunciado.indexOf('___') === -1) {
            alert('Para "Preencha a Lacuna", o enunciado deve conter "___" para marcar a lacuna.');
            e.preventDefault();
            return;
        }
        const opcoesBrutas = (document.getElementById('lacuna_opcoes')?.value || '')
            .split(/[\n,]/)
            .map(v => v.trim())
            .filter(Boolean);
        if (opcoesBrutas.length < 2) {
            alert('Adicione pelo menos 2 palavras/blocos para a lacuna.');
            e.preventDefault();
            return;
        }
        const respostaCorretaRaw = (formData.get('resposta_correta') || '').toString().trim();
        if (!respostaCorretaRaw) {
            alert('Informe a resposta correta para a lacuna.');
            e.preventDefault();
            return;
        }
        const respostasCorretas = respostaCorretaRaw
            .split(/[|,]/)
            .map(v => v.trim())
            .filter(Boolean);
        if (respostasCorretas.length === 0) {
            alert('Informe ao menos uma resposta correta para a lacuna.');
            e.preventDefault();
            return;
        }
        const qtdLacunas = (enunciado.match(/___/g) || []).length;
        if (qtdLacunas > 1 && respostasCorretas.length !== qtdLacunas) {
            alert('Para múltiplas lacunas, informe a resposta na mesma ordem, separando por vírgula.');
            e.preventDefault();
            return;
        }
        const opcoesNorm = opcoesBrutas.map(v => v.toLowerCase());
        const faltantes = respostasCorretas.filter(v => !opcoesNorm.includes(v.toLowerCase()));
        if (faltantes.length > 0) {
            alert('A resposta correta precisa existir na lista de palavras/blocos.');
            e.preventDefault();
            return;
        }
        formData.set('questoes_json', JSON.stringify({ opcoes_lacuna: opcoesBrutas }));
        formData.set('resposta_correta', respostasCorretas.join('|'));
    }
    
    const endpoint = modoEdicaoPagina
        ? '<?= URL ?>/professor/jornadas/modulos/atualizar-exercicio'
        : '<?= URL ?>/professor/jornadas/modulos/adicionar-exercicio';

    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (modoEdicaoPagina) {
                alert('Exercício atualizado com sucesso!');
                window.location.href = urlListaExerciciosModulo;
            } else {
                alert('Exercício adicionado com sucesso!');
                window.location.href = urlListaExerciciosModulo;
            }
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
    });
})();

// Funções para abrir/fechar modal de ler imagem
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

// Sincronizar enunciado Jornada (contenteditable -> hidden)
document.addEventListener('DOMContentLoaded', function() {
    var editorEnunciado = document.getElementById('editor-enunciado-jornada');
    var hiddenEnunciado = document.getElementById('enunciado-jornada');
    if (editorEnunciado && hiddenEnunciado) {
        function syncEnunciadoJornada() {
            if (editorEnunciado.innerHTML && (editorEnunciado.querySelector('ul, ol, img') || /<ul|<ol|<li|<img/i.test(editorEnunciado.innerHTML))) {
                hiddenEnunciado.value = editorEnunciado.innerHTML || '';
            } else if (typeof MathEditor !== 'undefined' && MathEditor.serializarParaLaTeX) {
                hiddenEnunciado.value = MathEditor.serializarParaLaTeX(editorEnunciado);
            } else {
                hiddenEnunciado.value = editorEnunciado.innerText || '';
            }
        }
        editorEnunciado.addEventListener('input', syncEnunciadoJornada);
        editorEnunciado.addEventListener('blur', syncEnunciadoJornada);
    }
});

// Paste e clique em imagem: enunciado e alternativas jornada
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('paste', function(e) {
        var editor = e.target && (e.target.id === 'editor-enunciado-jornada' || (e.target.classList && e.target.classList.contains('alt-editor'))) ? e.target : (e.target && e.target.closest ? e.target.closest('#editor-enunciado-jornada, #opcoes-lista .alt-editor, #edit-opcoes-lista .alt-editor') : null);
        if (!editor) return;
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                e.preventDefault();
                var file = items[i].getAsFile();
                if (!file) return;
                uploadImageForEditorJornada(file).then(function(url) {
                    insertImageInEditorJornada(editor, url);
                }).catch(function(err) {
                    alert('Erro ao enviar imagem: ' + (err.message || err));
                });
                return;
            }
        }
    });
    document.addEventListener('click', function(e) {
        var img = e.target && e.target.tagName === 'IMG' ? e.target : null;
        if (!img) return;
        if (!img.closest('#editor-enunciado-jornada') && !img.closest('#opcoes-lista .alt-editor') && !img.closest('#edit-opcoes-lista .alt-editor')) return;
        e.preventDefault();
        e.stopPropagation();
        showImageResizePopoverJornada(img);
    }, true);
    var enunciadoImgInput = document.getElementById('enunciado-jornada-insert-image-input');
    if (enunciadoImgInput) {
        enunciadoImgInput.addEventListener('change', function() {
            var file = this.files && this.files[0];
            if (!file) return;
            var el = document.getElementById('editor-enunciado-jornada');
            if (el) {
                uploadImageForEditorJornada(file).then(function(url) {
                    insertImageInEditorJornada(el, url);
                }).catch(function(err) {
                    alert('Erro ao enviar imagem: ' + (err.message || err));
                });
            }
            this.value = '';
        });
    }
    var editEnunciadoImgInput = document.getElementById('edit-enunciado-insert-image-input');
    if (editEnunciadoImgInput) {
        editEnunciadoImgInput.addEventListener('change', function() {
            var file = this.files && this.files[0];
            if (!file) return;
            var el = document.getElementById('editor-enunciado-edicao');
            if (el) {
                uploadImageForEditorJornada(file).then(function(url) {
                    insertImageInEditorJornada(el, url);
                    syncEnunciadoEdicao();
                }).catch(function(err) {
                    alert('Erro ao enviar imagem: ' + (err.message || err));
                });
            }
            this.value = '';
        });
    }
    var altImgInput = document.getElementById('alt-jornada-insert-image-input');
    if (altImgInput) {
        altImgInput.addEventListener('change', function() {
            var file = this.files && this.files[0];
            if (!file) return;
            var el = altEditorForImageJornada || document.querySelector('#edit-opcoes-lista .alt-editor, #opcoes-lista .alt-editor');
            if (el) {
                uploadImageForEditorJornada(file).then(function(url) {
                    insertImageInEditorJornada(el, url);
                }).catch(function(err) {
                    alert('Erro ao enviar imagem: ' + (err.message || err));
                });
            }
            this.value = '';
            altEditorForImageJornada = null;
        });
    }
});

// Fechar modal ao clicar fora
document.addEventListener('DOMContentLoaded', function() {
    const modalLerImagem = document.getElementById('modal-ler-imagem');
    if (modalLerImagem) {
        modalLerImagem.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalLerImagem();
            }
        });
    }
    const modalBancoQuestoes = document.getElementById('modal-banco-questoes');
    if (modalBancoQuestoes) {
        modalBancoQuestoes.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalBancoQuestoes();
            }
        });
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

// Preview da imagem ao selecionar arquivo no modal de ler imagem
document.addEventListener('DOMContentLoaded', function() {
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
    if (uploadArea) {
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
        
        // Clique na área de upload
        uploadArea.addEventListener('click', function(e) {
            if (e.target === this || e.target.closest('#upload-area')) {
                const imagemInputEl = document.getElementById('imagem-questao-ler');
                if (imagemInputEl) {
                    imagemInputEl.click();
                }
            }
        });
    }
    
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
    
    // Formulário de ler imagem
    const lerImagemForm = document.getElementById('lerImagemForm');
    if (lerImagemForm) {
        lerImagemForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const imagemInputEl = document.getElementById('imagem-questao-ler');
            if (!imagemInputEl || !imagemInputEl.files || !imagemInputEl.files[0]) {
                alert('Por favor, selecione uma imagem');
                return;
            }
            
            const formData = new FormData();
            formData.append('imagem', imagemInputEl.files[0]);
            formData.append('modulo_id', '<?= $modulo['id'] ?>');
            
            const btnLer = document.getElementById('btn-ler-imagem');
            const loadingEl = document.getElementById('loading-ler-imagem');
            
            btnLer.disabled = true;
            if (loadingEl) loadingEl.classList.remove('hidden');
            
            fetch('<?= URL ?>/professor/jornadas/modulos/ler-imagem-exercicio/<?= $modulo['id'] ?>', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(`Erro ${response.status}: ${text.substring(0, 200)}`);
                }
                return response.json();
            })
            .then(data => {
                if (loadingEl) loadingEl.classList.add('hidden');
                btnLer.disabled = false;
                
                if (data.success && data.exercicio) {
                    // Preencher formulário manual com os dados extraídos
                    const exercicio = data.exercicio;
                    
                    // Tipo
                    const tipoSelect = document.querySelector('select[name="tipo"]');
                    if (tipoSelect) {
                        if (exercicio.tipo === 'multipla_escolha' || exercicio.tipo === 'alternativas' || exercicio.tipo === 'verdadeiro_falso') {
                            tipoSelect.value = 'alternativas';
                        } else {
                            tipoSelect.value = 'dissertativa';
                        }
                        // Dispara evento change para atualizar opções
                        if (tipoSelect.value === 'alternativas') {
                            atualizarOpcoesAlternativas(tipoSelect);
                        }
                    }
                    
                    // Enunciado (editor rich + hidden, igual provas)
                    var enunciadoVal = exercicio.enunciado || '';
                    var editorEnunciado = document.getElementById('editor-enunciado-jornada');
                    var hiddenEnunciado = document.getElementById('enunciado-jornada');
                    if (editorEnunciado && hiddenEnunciado) {
                        preencherEditorInteligente(editorEnunciado, enunciadoVal);
                        if (typeof MathEditor !== 'undefined') {
                            hiddenEnunciado.value = MathEditor.serializarParaLaTeX(editorEnunciado);
                        } else {
                            hiddenEnunciado.value = enunciadoVal;
                        }
                    }
                    
                    // Alternativas (5 fixas A–E; .alt-editor opcao-editor-jornada-0..4)
                    if ((exercicio.tipo === 'multipla_escolha' || exercicio.tipo === 'alternativas') && exercicio.alternativas && Array.isArray(exercicio.alternativas)) {
                        exercicio.alternativas.forEach(function(alt, index) {
                            if (index >= 5) return;
                            var ed = document.getElementById('opcao-editor-jornada-' + index);
                            var texto = alt.texto || alt.text || '';
                            if (ed) {
                                preencherEditorInteligente(ed, texto);
                            }
                            if (alt.correta === true || alt.correta === 1 || alt.correta === '1') {
                                var radio = document.getElementById('radio-jornada-' + index);
                                if (radio) radio.checked = true;
                            }
                        });
                        sincronizarGabaritoDoRadio();
                    }
                    
                    // Fechar modal
                    fecharModalLerImagem();
                    
                    alert('Questão extraída com sucesso! Revise os campos e clique em "Adicionar Exercício".');
                } else {
                    alert('Erro: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                if (loadingEl) loadingEl.classList.add('hidden');
                btnLer.disabled = false;
                alert('Erro de conexão: ' + error.message);
                console.error(error);
            });
        });
    }
});

function abrirModalColarJson() {
    var modal = document.getElementById('modal-colar-json');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    document.getElementById('jornada-colar-json-resultado').textContent = '';
    document.getElementById('apostila-status').textContent = '';
    document.getElementById('apostila-exercicios-lista').innerHTML = '';
    document.getElementById('apostila-filtros').classList.add('hidden');
    carregarApostilasDisponiveis();
}

function fecharModalColarJson() {
    var modal = document.getElementById('modal-colar-json');
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

function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = s || '';
    return div.innerHTML;
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

async function importarExerciciosJson() {
    var textarea = document.getElementById('jornada-colar-json-textarea');
    var resultadoEl = document.getElementById('jornada-colar-json-resultado');
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

// Mapeia tipos da IA da Apostila (objetiva/discursiva/verdadeiro_falso/associacao/
// outro) para os tipos desta tela (alternativas/verdadeiro_falso/dissertativa) —
// os nomes não coincidem entre os dois módulos.
function mapTipoParaJornada(tipo) {
    var t = (tipo || '').toString().toLowerCase();
    if (t === 'verdadeiro_falso') return 'verdadeiro_falso';
    if (t === 'objetiva' || t === 'alternativas' || t === 'multipla_escolha') return 'alternativas';
    return 'dissertativa'; // discursiva, associacao, outro, ou tipo desconhecido
}

function mapDificuldadeParaJornada(dificuldade) {
    var d = (dificuldade || '').toString().toLowerCase();
    if (d === 'media') return 'medio';
    if (d === 'facil' || d === 'medio' || d === 'dificil') return d;
    return '';
}

var LETRAS_JORNADA = ['A', 'B', 'C', 'D', 'E'];

// Tenta casar o gabarito com uma alternativa. Sem correspondência confiável,
// não marca nenhuma como certa (melhor deixar sem gabarito do que errado).
function montarOpcoesJornada(exercicio) {
    var brutas = Array.isArray(exercicio.alternativas) ? exercicio.alternativas : [];
    var textos = brutas
        .map(function (a) { return typeof a === 'string' ? a : ((a && (a.texto || a.text)) || ''); })
        .map(function (s) { return s.toString().trim(); })
        .filter(Boolean)
        .slice(0, 5); // máximo 5 alternativas (A-E) nesta tela

    if (textos.length === 0) {
        return { opcoes: [], respostaCorreta: '', gabaritoEncontrado: true };
    }

    var gabarito = (exercicio.gabarito || '').toString().trim().toLowerCase();
    var indiceCorreta = -1;
    if (gabarito) {
        indiceCorreta = textos.findIndex(function (texto) {
            var t = texto.toLowerCase();
            return t === gabarito || t.indexOf(gabarito) !== -1 || gabarito.indexOf(t) !== -1;
        });
    }

    var opcoes = textos.map(function (texto, index) {
        return { letra: LETRAS_JORNADA[index], texto: texto, correta: index === indiceCorreta };
    });

    return {
        opcoes: opcoes,
        respostaCorreta: indiceCorreta !== -1 ? LETRAS_JORNADA[indiceCorreta] : '',
        gabaritoEncontrado: indiceCorreta !== -1
    };
}

function montarOpcoesVerdadeiroFalso(exercicio) {
    var gabarito = (exercicio.gabarito || '').toString().trim().toLowerCase();
    var ehVerdadeiro = /^(v|verdadeiro|true)\b/.test(gabarito);
    var ehFalso = /^(f|falso|false)\b/.test(gabarito);
    var opcoes = [
        { letra: 'V', texto: 'Verdadeiro', correta: ehVerdadeiro },
        { letra: 'F', texto: 'Falso', correta: ehFalso }
    ];
    return {
        opcoes: opcoes,
        respostaCorreta: ehVerdadeiro ? 'V' : (ehFalso ? 'F' : ''),
        gabaritoEncontrado: ehVerdadeiro || ehFalso
    };
}

async function importarLista(lista) {
    var resultadoEl = document.getElementById('jornada-colar-json-resultado');
    var moduloId = <?= (int)$modulo['id'] ?>;

    if (!Array.isArray(lista) || lista.length === 0) {
        resultadoEl.textContent = 'Nenhum exercício selecionado.';
        resultadoEl.className = 'text-sm text-red-600 mt-2';
        return;
    }

    resultadoEl.textContent = 'Importando ' + lista.length + ' exercício(s)...';
    resultadoEl.className = 'text-sm text-gray-600 mt-2';

    var sucesso = 0, falhas = 0, semGabarito = 0;

    for (var i = 0; i < lista.length; i++) {
        var exercicio = lista[i] || {};
        var enunciado = (exercicio.enunciado || '').toString().trim();
        if (!enunciado) {
            falhas++;
            continue;
        }

        var tipo = mapTipoParaJornada(exercicio.tipo);
        var info = { opcoes: [], respostaCorreta: '', gabaritoEncontrado: true };
        if (tipo === 'alternativas') {
            info = montarOpcoesJornada(exercicio);
        } else if (tipo === 'verdadeiro_falso') {
            info = montarOpcoesVerdadeiroFalso(exercicio);
        }

        if ((tipo === 'alternativas' || tipo === 'verdadeiro_falso') && !info.gabaritoEncontrado) {
            semGabarito++;
        }

        var formData = new FormData();
        formData.append('modulo_id', String(moduloId));
        formData.append('enunciado', enunciado);
        formData.append('tipo', tipo);
        formData.append('gabarito', exercicio.gabarito || '');
        formData.append('pontuacao', '1.00');
        formData.append('nivel_dificuldade', mapDificuldadeParaJornada(exercicio.dificuldade));
        if (exercicio.imagem_url) {
            formData.append('imagem_url', exercicio.imagem_url);
        }
        if (info.opcoes.length > 0) {
            formData.append('questoes_json', JSON.stringify({ opcoes: info.opcoes }));
            formData.append('resposta_correta', info.respostaCorreta);
        }

        try {
            var response = await fetch('<?= URL ?>/professor/jornadas/modulos/adicionar-exercicio', {
                method: 'POST',
                body: formData
            });
            var data = await response.json().catch(function () { return null; });
            if (response.ok && data && data.success) {
                sucesso++;
            } else {
                falhas++;
            }
        } catch (err) {
            falhas++;
        }
    }

    var msg = sucesso + ' exercício(s) importado(s)';
    if (falhas > 0) msg += ', ' + falhas + ' falharam';
    if (semGabarito > 0) msg += ', ' + semGabarito + ' sem resposta correta identificada automaticamente (revise manualmente)';
    msg += '.';

    resultadoEl.textContent = msg;
    resultadoEl.className = sucesso > 0 ? 'text-sm text-green-700 mt-2' : 'text-sm text-red-600 mt-2';

    if (sucesso > 0) {
        setTimeout(function () { window.location.reload(); }, 1800);
    }
}

// Funções para abrir/fechar modal
function abrirModalGerarIA() {
    const modal = document.getElementById('modal-gerar-ia');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
}

function fecharModalGerarIA() {
    const modal = document.getElementById('modal-gerar-ia');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
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

function pareceHtml(valor) {
    const s = String(valor || '').trim();
    if (!s) return false;
    return /<\s*\/?\s*(p|div|span|br|ul|ol|li|strong|em|b|i|u|img|table|tr|td|th|h[1-6]|math)\b/i.test(s);
}

function preencherEditorInteligente(editorEl, valor) {
    if (!editorEl) return;
    const conteudo = String(valor || '');
    if (pareceHtml(conteudo)) {
        editorEl.innerHTML = conteudo;
        return;
    }
    if (typeof MathEditor !== 'undefined' && MathEditor.preencherDeLaTeX) {
        MathEditor.preencherDeLaTeX(editorEl, conteudo);
        return;
    }
    editorEl.innerText = conteudo;
}

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
    fetch(`${baseUrl}/professor/jornadas/modulos/banco-questoes/facets${query ? '?' + query : ''}`, {
        headers: { 'Accept': 'application/json' }
    })
        .then((r) => parseJsonResponse(r))
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
    fetch(`${baseUrl}/professor/jornadas/modulos/banco-questoes/listar?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    })
        .then((r) => parseJsonResponse(r))
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
    if (!confirm('Importar as questões selecionadas para esta jornada?')) {
        return;
    }
    const payload = {
        modulo_id: <?= (int)$modulo['id'] ?>,
        questao_ids: Array.from(bqSelecionadas)
    };
    fetch(`${baseUrl}/professor/jornadas/modulos/banco-questoes/importar`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then((r) => parseJsonResponse(r))
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
        mostrarModalOk(msg, function() {
            window.location.href = urlListaExerciciosModulo;
        });
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

function mostrarModalOk(mensagem, onOk) {
    const modalExistente = document.getElementById('modal-ok-custom');
    if (modalExistente) modalExistente.remove();

    const modal = document.createElement('div');
    modal.id = 'modal-ok-custom';
    modal.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 16px;';
    modal.innerHTML = `
        <div style="background:#fff; width:100%; max-width:520px; border-radius:16px; box-shadow:0 20px 40px rgba(0,0,0,0.25); overflow:hidden;">
            <div style="padding:22px 24px; color:#374151; font-size:30px; line-height:1.5;">${mensagem}</div>
            <div style="height:1px; background:#e5e7eb;"></div>
            <div style="padding:14px 24px; display:flex; justify-content:flex-end;">
                <button id="btn-modal-ok-custom" type="button" style="color:#2563eb; font-weight:600; font-size:28px;">OK</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const closeModal = () => {
        modal.remove();
        if (typeof onOk === 'function') onOk();
    };

    const btnOk = document.getElementById('btn-modal-ok-custom');
    if (btnOk) btnOk.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
}

// Atualiza total de questões no modal IA
function atualizarTotalQuestoesIA() {
    const f = parseInt(document.getElementById('quantidade-facil').value) || 0;
    const m = parseInt(document.getElementById('quantidade-medio').value) || 0;
    const d = parseInt(document.getElementById('quantidade-dificil').value) || 0;
    const x = parseInt(document.getElementById('quantidade-desafio').value) || 0;
    const total = f + m + d + x;
    const el = document.getElementById('total-questoes-ia');
    if (el) el.textContent = 'Total: ' + total + (total === 1 ? ' questão' : ' questões');
}
document.getElementById('quantidade-facil').addEventListener('input', atualizarTotalQuestoesIA);
document.getElementById('quantidade-medio').addEventListener('input', atualizarTotalQuestoesIA);
document.getElementById('quantidade-dificil').addEventListener('input', atualizarTotalQuestoesIA);
document.getElementById('quantidade-desafio').addEventListener('input', atualizarTotalQuestoesIA);
atualizarTotalQuestoesIA();

document.getElementById('gerarExercicioIAForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    const qFacil = parseInt(document.getElementById('quantidade-facil').value) || 0;
    const qMedio = parseInt(document.getElementById('quantidade-medio').value) || 0;
    const qDificil = parseInt(document.getElementById('quantidade-dificil').value) || 0;
    const qDesafio = parseInt(document.getElementById('quantidade-desafio').value) || 0;
    const quantidade = qFacil + qMedio + qDificil + qDesafio;
    const quantidadesPorNivel = {};
    const niveis = [];
    if (qFacil > 0) { niveis.push('Fácil'); quantidadesPorNivel['Fácil'] = qFacil; }
    if (qMedio > 0) { niveis.push('Médio'); quantidadesPorNivel['Médio'] = qMedio; }
    if (qDificil > 0) { niveis.push('Difícil'); quantidadesPorNivel['Difícil'] = qDificil; }
    if (qDesafio > 0) { niveis.push('Desafio'); quantidadesPorNivel['Desafio'] = qDesafio; }
    
    if (quantidade === 0 || niveis.length === 0) {
        alert('Defina pelo menos uma quantidade maior que zero em um nível de dificuldade.');
        return;
    }
    
    if (!confirm('Deseja gerar ' + quantidade + ' exercício(s) pela Tudinha? Pode demorar um pouco.')) {
        return;
    }
    
    // Fechar modal
    fecharModalGerarIA();
    
    // Mostrar loading
    const loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'loading-overlay';
    loadingOverlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center; flex-direction: column;';
    loadingOverlay.innerHTML = `
        <div style="background: white; padding: 24px; border-radius: 12px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-width: 360px; width: calc(100% - 32px);">
            <img src="<?= URL ?>/public/static/images/animacao-tuidnha.gif" alt="Tudinha carregando" style="width: 140px; height: 140px; object-fit: contain; margin: 0 auto 12px; border-radius: 10px; animation: tudinha-bounce 1.4s ease-in-out infinite;" />
            <p style="font-size: 18px; color: #1f2937; margin: 0; font-weight: 600;">A Tudinha está criando os exercícios...</p>
            <p style="font-size: 14px; color: #6b7280; margin-top: 8px;">Isso pode demorar um pouco. Aguarde sem fechar a página.</p>
        </div>
    `;
    document.body.appendChild(loadingOverlay);

    const contexto = (document.getElementById('contexto-adicional-ia') && document.getElementById('contexto-adicional-ia').value) ? document.getElementById('contexto-adicional-ia').value.trim() : '';
    const data = {
        modulo_id: formData.get('modulo_id'),
        tipo: formData.get('tipo'),
        serie: formData.get('serie') || '',
        com_imagens: document.getElementById('com-imagens-ia') && document.getElementById('com-imagens-ia').checked ? '1' : '0',
        quantidade: quantidade,
        niveis: niveis,
        quantidades_por_nivel: quantidadesPorNivel,
        quantidade_facil: qFacil,
        quantidade_medio: qMedio,
        quantidade_dificil: qDificil,
        quantidade_desafio: qDesafio,
        contexto: contexto,
        modelo_geracao: 'padrao'
    };
    
    var controller = new AbortController();
    var timeoutId = setTimeout(function() { controller.abort(); }, 240000);
    fetch('<?= URL ?>/professor/jornadas/modulos/gerar-exercicio-ia', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
        signal: controller.signal
    })
    .then(async function(response) {
        clearTimeout(timeoutId);
        if (!response.ok) {
            var errorMessage = 'Erro ' + response.status;
            try {
                var contentType = response.headers.get('content-type') || '';
                if (contentType.indexOf('application/json') !== -1) {
                    var payload = await response.json();
                    if (payload && payload.error) {
                        errorMessage = payload.error;
                    }
                } else {
                    var text = await response.text();
                    if (text) {
                        errorMessage = text.substring(0, 240);
                    }
                }
            } catch (e) {
                // Mantém mensagem padrão caso parsing falhe.
            }
            throw new Error(errorMessage);
        }
        return response.json();
    })
    .then(function(data) {
        if (loadingOverlay.parentNode) {
            loadingOverlay.parentNode.removeChild(loadingOverlay);
        }
        if (data.success) {
            var quantidade = data.exercicios_ids ? data.exercicios_ids.length : 0;
            mostrarModalOk(quantidade + ' exercício(s) gerado(s) com sucesso!', function() {
                window.location.href = urlListaExerciciosModulo;
            });
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
            alert('A requisição demorou muito. A IA pode levar até 4 minutos em horários de pico. Tente novamente.');
        } else {
            var detalhe = (error && error.message) ? ('\nDetalhe: ' + error.message) : '';
            alert('Erro ao gerar exercícios com IA.' + detalhe);
        }
        console.error(error);
    });
});

// Função para mostrar/esconder opções baseado no tipo (5 alternativas fixas A–E)
function atualizarOpcoesAlternativas(selectElement) {
    const container = document.getElementById('opcoes-container');
    const lacunaContainer = document.getElementById('lacuna-container');
    const campoGabarito = document.getElementById('campo_resposta_correta');
    const hintGabarito = document.getElementById('gabarito-hint-alternativas');
    if (selectElement.value === 'alternativas' || selectElement.value === 'verdadeiro_falso') {
        container.classList.remove('hidden');
        if (lacunaContainer) lacunaContainer.classList.add('hidden');
        if (campoGabarito) {
            campoGabarito.readOnly = true;
            campoGabarito.classList.add('bg-gray-50');
            campoGabarito.placeholder = 'Marque a bolinha «Correta» na alternativa desejada (campo preenchido automaticamente)';
        }
        if (hintGabarito) hintGabarito.classList.remove('hidden');
        sincronizarGabaritoDoRadio();
    } else if (selectElement.value === 'preencher_lacuna') {
        container.classList.add('hidden');
        if (lacunaContainer) lacunaContainer.classList.remove('hidden');
        if (campoGabarito) {
            campoGabarito.readOnly = false;
            campoGabarito.classList.remove('bg-gray-50');
            campoGabarito.placeholder = 'Digite a palavra/bloco correto para preencher a lacuna...';
        }
        if (hintGabarito) hintGabarito.classList.add('hidden');
    } else {
        container.classList.add('hidden');
        if (lacunaContainer) lacunaContainer.classList.add('hidden');
        if (campoGabarito) {
            campoGabarito.readOnly = false;
            campoGabarito.classList.remove('bg-gray-50');
            campoGabarito.placeholder = 'Digite a resposta correta ou gabarito...';
        }
        if (hintGabarito) hintGabarito.classList.add('hidden');
    }
}

// Quando o usuário marca a bolinha "Correta", esse é o gabarito (campo abaixo só espelha)
function sincronizarGabaritoDoRadio() {
    const campo = document.getElementById('campo_resposta_correta');
    if (!campo) return;
    const radios = Array.from(document.querySelectorAll('input[name="resposta_opcao[]"]:checked'));
    if (!radios.length) {
        campo.value = '';
        return;
    }
    const letrasMarcadas = radios
        .map(r => letras[parseInt(r.value, 10)])
        .filter(Boolean);
    campo.value = letrasMarcadas.join('|');
}

function sincronizarGabaritoDoRadioEdicao() {
    const campo = document.getElementById('edit-resposta_correta');
    if (!campo) return;
    const radios = Array.from(document.querySelectorAll('input[name="resposta_opcao_edit[]"]:checked'));
    if (!radios.length) {
        campo.value = '';
        return;
    }
    const letrasMarcadas = radios
        .map(r => letras[parseInt(r.value, 10)])
        .filter(Boolean);
    campo.value = letrasMarcadas.join('|');
}

// Função para inicializar o select de tipo
function inicializarSelectTipo() {
    const tipoSelect = document.querySelector('select[name="tipo"]');
    if (!tipoSelect) {
        // Se não encontrou, tenta novamente após um delay
        setTimeout(inicializarSelectTipo, 100);
        return;
    }
    
    // Adiciona listener de mudança
    tipoSelect.addEventListener('change', function() {
        atualizarOpcoesAlternativas(this);
    });
    
    // Verifica se já está selecionado como alternativas
    if (tipoSelect.value === 'alternativas') {
        atualizarOpcoesAlternativas(tipoSelect);
    }
}

// Inicializa quando o DOM estiver pronto
function initPage() {
    inicializarSelectTipo();
    const params = new URLSearchParams(window.location.search);
    const editarId = params.get('editar');
    if (editarId) {
        carregarExercicioParaEdicaoPagina(editarId);
    }
    if (params.get('ia') === '1') {
        abrirModalGerarIA();
    }
}

function carregarExercicioParaEdicaoPagina(id) {
    fetch(`<?= URL ?>/professor/jornadas/modulos/buscar-exercicio?exercicio_id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.exercicio) {
                alert('Erro ao carregar exercício para edição.');
                return;
            }
            preencherFormularioPrincipalComExercicio(data.exercicio);
        })
        .catch(error => {
            alert('Erro de conexão ao carregar exercício.');
            console.error(error);
        });
}

function preencherFormularioPrincipalComExercicio(exercicio) {
    modoEdicaoPagina = true;
    const form = document.getElementById('adicionarExercicioForm');
    const hiddenId = document.getElementById('exercicio-id-edicao-pagina');
    const hiddenStatus = document.getElementById('status-edicao-pagina');
    const hiddenImagemUrl = document.getElementById('imagem-url-edicao-pagina');
    const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
    const tituloTela = document.querySelector('h3.text-lg.font-semibold.text-gray-900.mb-6');

    if (hiddenId) hiddenId.value = exercicio.id || '';
    if (hiddenStatus) hiddenStatus.value = exercicio.status || 'publicado';
    // Sem isso, salvar por esta tela zera imagem_url no banco (o form não
    // tinha esse campo) mesmo com a imagem continuando visível dentro do
    // enunciado — quebra quem lê a coluna isolada (ex.: miniatura no
    // seletor de exercícios da apostila).
    if (hiddenImagemUrl) hiddenImagemUrl.value = exercicio.imagem_url || '';
    if (submitBtn) submitBtn.textContent = 'Salvar Alterações';
    if (tituloTela) tituloTela.textContent = 'Editar Exercício';

    const tipoSelect = document.querySelector('select[name="tipo"]');
    const tipoAtual = (exercicio.tipo === 'verdadeiro_falso') ? 'alternativas' : (exercicio.tipo || 'alternativas');
    if (tipoSelect) {
        tipoSelect.value = tipoAtual;
        atualizarOpcoesAlternativas(tipoSelect);
    }

    const pontuacao = document.querySelector('input[name="pontuacao"]');
    if (pontuacao) pontuacao.value = exercicio.pontuacao || '1.00';

    const editorEnunciado = document.getElementById('editor-enunciado-jornada');
    const hiddenEnunciado = document.getElementById('enunciado-jornada');
    const enunciadoVal = exercicio.enunciado || '';
    if (editorEnunciado && hiddenEnunciado) {
        preencherEditorInteligente(editorEnunciado, enunciadoVal);
        // Esta tela (edição via ?editar= na página, diferente do modal) não
        // tem um campo de prévia dedicado pra imagem_url — a imagem gerada
        // pela IA (ou anexada manualmente) só existe visualmente dentro do
        // enunciado, mesmo padrão do botão "Inserir ou colar imagem" logo
        // acima. Sem isso, a imagem salva no exercício nunca aparecia aqui.
        if (exercicio.imagem_url && !editorEnunciado.innerHTML.includes(exercicio.imagem_url)) {
            const imgTag = document.createElement('img');
            imgTag.src = exercicio.imagem_url;
            imgTag.style.maxWidth = '400px';
            imgTag.style.width = '100%';
            imgTag.style.borderRadius = '8px';
            imgTag.style.marginTop = '8px';
            editorEnunciado.appendChild(imgTag);
        }
        hiddenEnunciado.value = editorEnunciado.innerHTML;
    }

    const campoGabarito = document.getElementById('campo_resposta_correta');
    if (campoGabarito) campoGabarito.value = exercicio.resposta_correta || '';

    if (tipoAtual === 'preencher_lacuna') {
        let opcoesLacuna = [];
        if (exercicio.questoes_json && exercicio.questoes_json.opcoes_lacuna && Array.isArray(exercicio.questoes_json.opcoes_lacuna)) {
            opcoesLacuna = exercicio.questoes_json.opcoes_lacuna;
        } else if (typeof exercicio.questoes_json === 'string') {
            try {
                const parsed = JSON.parse(exercicio.questoes_json);
                if (parsed && Array.isArray(parsed.opcoes_lacuna)) opcoesLacuna = parsed.opcoes_lacuna;
            } catch (e) {}
        }
        const txtLacuna = document.getElementById('lacuna_opcoes');
        if (txtLacuna) txtLacuna.value = opcoesLacuna.join('\n');
        return;
    }

    if (tipoAtual === 'alternativas') {
        let opcoes = [];
        if (exercicio.questoes_json) {
            if (exercicio.questoes_json.opcoes && Array.isArray(exercicio.questoes_json.opcoes)) {
                opcoes = exercicio.questoes_json.opcoes;
            } else if (Array.isArray(exercicio.questoes_json)) {
                opcoes = exercicio.questoes_json;
            } else if (typeof exercicio.questoes_json === 'string') {
                try {
                    const parsed = JSON.parse(exercicio.questoes_json);
                    if (parsed && Array.isArray(parsed.opcoes)) opcoes = parsed.opcoes;
                    else if (Array.isArray(parsed)) opcoes = parsed;
                } catch (e) {}
            }
        }
        const corretas = (exercicio.resposta_correta || '')
            .toString()
            .split('|')
            .map(v => v.trim().toUpperCase())
            .filter(Boolean);

        const altItems = document.querySelectorAll('#opcoes-lista .alt-item');
        altItems.forEach((item, index) => {
            const ed = item.querySelector('.alt-editor');
            const checkbox = item.querySelector('input[name="resposta_opcao[]"]');
            const opc = opcoes[index] || {};
            const texto = opc.texto || opc.text || '';
            if (ed) {
                preencherEditorInteligente(ed, texto);
            }
            if (checkbox) {
                const letra = letras[index];
                const marcadaPorFlag = (opc.correta === true || opc.correta === 1 || opc.correta === '1');
                checkbox.checked = marcadaPorFlag || corretas.includes((letra || '').toUpperCase());
            }
        });
        sincronizarGabaritoDoRadio();
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPage);
} else {
    initPage();
}

// Quando clicar na bolinha "Correta", esse é o gabarito (campo abaixo atualiza automaticamente)
document.addEventListener('change', function(e) {
    if (e.target && e.target.matches('input[name="resposta_opcao[]"]')) {
        sincronizarGabaritoDoRadio();
    }
    if (e.target && e.target.matches('input[name="resposta_opcao_edit[]"]')) {
        sincronizarGabaritoDoRadioEdicao();
    }
});

function alternarStatusExercicio(id) {
    const formData = new FormData();
    formData.append('exercicio_id', id);
    formData.append('_token', <?= json_encode($csrf_token) ?>);
    
    fetch('<?= URL ?>/professor/jornadas/modulos/alternar-status-exercicio', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const statusText = data.novo_status === 'publicado' ? 'publicado' : 'despublicado';
            alert('Exercício ' + statusText + ' com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
}

function removerExercicio(id) {
    if (!confirm('Tem certeza que deseja remover este exercício?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('exercicio_id', id);
    formData.append('_token', <?= json_encode($csrf_token) ?>);
    
    fetch('<?= URL ?>/professor/jornadas/modulos/remover-exercicio', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Exercício removido com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
}

let exercicioEditando = null;

function editarExercicio(id) {
    // Busca dados do exercício
    fetch(`<?= URL ?>/professor/jornadas/modulos/buscar-exercicio?exercicio_id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Exercício carregado:', data.exercicio);
                exercicioEditando = data.exercicio;
                abrirModalEdicao(data.exercicio);
            } else {
                alert('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            alert('Erro de conexão');
            console.error(error);
        });
}

function abrirModalEdicao(exercicio) {
    // Remove modal anterior se existir
    const modalAnterior = document.getElementById('modal-editar-exercicio');
    if (modalAnterior) {
        modalAnterior.remove();
    }
    
    // Cria novo modal
    const modal = document.createElement('div');
    modal.id = 'modal-editar-exercicio';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl p-6 max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Editar Exercício</h3>
                    <button onclick="fecharModalEdicao()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="form-editar-exercicio" class="space-y-4">
                    <input type="hidden" name="exercicio_id" id="edit-exercicio-id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Exercício *</label>
                            <select name="tipo" id="edit-tipo" required class="select-safari w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="alternativas">Alternativas (Múltipla Escolha)</option>
                                <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                                <option value="preencher_lacuna">Preencha a Lacuna (Arrastar Palavra)</option>
                                <option value="dissertativa">Dissertativa</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pontuação</label>
                            <input type="number" name="pontuacao" id="edit-pontuacao" step="0.1" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Enunciado *</label>
                        <input type="hidden" name="enunciado" id="edit-enunciado-hidden" required>
                        <div class="editor-toolbar">
                            <button type="button" class="tb-btn" title="Negrito" onmousedown="event.preventDefault();saveSelEdicao()" onclick="fmtEnunciadoEdicao('bold')"><b>B</b></button>
                            <button type="button" class="tb-btn" title="Itálico" onmousedown="event.preventDefault()" onclick="fmtEnunciadoEdicao('italic')"><i>I</i></button>
                            <button type="button" class="tb-btn" title="Sublinhado" onmousedown="event.preventDefault()" onclick="fmtEnunciadoEdicao('underline')"><u>U</u></button>
                            <div class="tb-sep"></div>
                            <button type="button" class="tb-btn" title="Lista com marcadores" onmousedown="event.preventDefault();saveSelEdicao();fmtEnunciadoEdicao('insertUnorderedList')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/></svg>
                            </button>
                            <button type="button" class="tb-btn" title="Lista numerada" onmousedown="event.preventDefault();saveSelEdicao();fmtEnunciadoEdicao('insertOrderedList')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4M4 10h2" stroke-linecap="round"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1" stroke-linecap="round"/></svg>
                            </button>
                            <div class="tb-sep"></div>
                            <button type="button" class="tb-btn tb-font-size" title="Diminuir letra" onmousedown="event.preventDefault();fmtFontSizeEdicao(-1)"><span>A−</span></button>
                            <button type="button" class="tb-btn tb-font-size" title="Aumentar letra" onmousedown="event.preventDefault();fmtFontSizeEdicao(1)"><span>A+</span></button>
                            <div class="tb-sep"></div>
                            <button type="button" class="tb-btn" title="Alinhar esquerda" onmousedown="event.preventDefault()" onclick="fmtEnunciadoEdicao('justifyLeft')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
                            </button>
                            <button type="button" class="tb-btn" title="Centralizar" onmousedown="event.preventDefault()" onclick="fmtEnunciadoEdicao('justifyCenter')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                            </button>
                            <div class="tb-sep"></div>
                            <button type="button" class="tb-math-btn" data-math-open="editor-enunciado-edicao" onmousedown="event.preventDefault()">
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
                        <div id="editor-enunciado-edicao" class="rich-editor" contenteditable="true" data-math="inline"
                             data-placeholder="Digite o enunciado aqui... Use ∑ Inserir Equação para adicionar fórmulas visuais."></div>
                        <div class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
                            <button type="button" id="btn-ver-codigo-latex-enunciado" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                📋 Ver código LaTeX do enunciado (para copiar em Inserir equação)
                            </button>
                            <div id="edit-codigo-latex-enunciado-wrap" class="hidden mt-2">
                                <textarea id="edit-codigo-latex-enunciado" readonly rows="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm font-mono"></textarea>
                                <button type="button" id="btn-copiar-codigo-latex-enunciado" class="mt-2 px-3 py-1.5 text-sm bg-gray-700 text-white rounded-lg hover:bg-gray-800">Copiar</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="rounded-xl border-2 border-dashed border-gray-200 bg-gray-50/50 p-4">
                        <p class="text-sm font-medium text-gray-700 mb-3">Imagem do Enunciado (opcional)</p>
                        <div id="edit-imagem-enunciado-preview" class="hidden mb-3">
                            <img id="edit-imagem-enunciado-img" src="" alt="Preview" class="max-w-full max-h-40 rounded-lg border border-gray-200 shadow-sm object-contain">
                            <button type="button" onclick="removerImagemEnunciadoEdicao()" class="mt-2 px-3 py-1.5 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors">Remover imagem</button>
                        </div>
                        <div id="edit-imagem-enunciado-actions" class="flex flex-wrap items-center gap-2">
                            <input type="file" id="edit-imagem-enunciado" accept="image/*" class="hidden" onchange="uploadImagemEnunciadoEdicao(this)">
                            <button type="button" onclick="document.getElementById('edit-imagem-enunciado').click()"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border-2 border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50 hover:border-gray-400 transition-colors shadow-sm">📷 Escolher arquivo</button>
                            <button type="button" id="btn-colar-imagem-edit" onclick="ativarColarImagemEdicao()"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border-2 border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50 hover:border-gray-400 transition-colors shadow-sm">📋 Colar imagem</button>
                        </div>
                        <input type="hidden" name="imagem_url" id="edit-imagem-url" value="">
                    </div>
                    
                    <div id="edit-opcoes-container" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Alternativas (Múltipla Escolha) *</label>
                        <div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
                            <button type="button" id="btn-ver-codigo-latex-alternativas" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                📋 Ver código LaTeX das alternativas (para copiar em Inserir equação)
                            </button>
                            <div id="edit-codigo-latex-alternativas-wrap" class="hidden mt-2">
                                <textarea id="edit-codigo-latex-alternativas" readonly rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm font-mono"></textarea>
                                <button type="button" id="btn-copiar-codigo-latex-alternativas" class="mt-2 px-3 py-1.5 text-sm bg-gray-700 text-white rounded-lg hover:bg-gray-800">Copiar tudo</button>
                            </div>
                        </div>
                        <div id="edit-opcoes-lista" class="space-y-3 mb-3"></div>
                        <div class="flex items-center justify-between">
                            <button type="button" onclick="adicionarOpcaoEdicao()" class="px-4 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors">
                                + Adicionar Alternativa
                            </button>
                            <span class="text-xs text-gray-500">Máximo 5 alternativas (A, B, C, D, E)</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Gabarito</label>
                        <p id="edit-gabarito-hint" class="text-xs text-gray-500 mb-1 hidden">Defina o gabarito marcando uma ou mais alternativas.</p>
                        <textarea name="resposta_correta" id="edit-resposta_correta" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" id="edit-status" class="select-safari w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="rascunho">Rascunho</option>
                            <option value="publicado">Publicado</option>
                            <option value="arquivado">Arquivado</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" onclick="fecharModalEdicao()" 
                                class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        `;
    document.body.appendChild(modal);
    // Vincular "Inserir Equação" ao editor do modal (modal é criado dinamicamente, MathEditor.init() já rodou no load)
    var mathBtnEdicao = modal.querySelector('[data-math-open="editor-enunciado-edicao"]');
    var editorEnunciadoEdicao = document.getElementById('editor-enunciado-edicao');
    if (mathBtnEdicao && editorEnunciadoEdicao && typeof MathEditor !== 'undefined' && MathEditor.abrir) {
        mathBtnEdicao.addEventListener('click', function(e) {
            e.preventDefault();
            MathEditor.abrir(editorEnunciadoEdicao);
        });
    }
    var editEnunciadoInput = document.getElementById('edit-enunciado-insert-image-input');
    if (editEnunciadoInput) {
        editEnunciadoInput.addEventListener('change', function() {
            var file = this.files && this.files[0];
            if (!file) return;
            var el = document.getElementById('editor-enunciado-edicao');
            if (el) {
                uploadImageForEditorJornada(file).then(function(url) {
                    insertImageInEditorJornada(el, url);
                    syncEnunciadoEdicao();
                }).catch(function(err) {
                    alert('Erro ao enviar imagem: ' + (err.message || err));
                });
            }
            this.value = '';
        });
    }
    
    // Define a variável global antes de preencher o formulário
    exercicioEditando = exercicio;
    
    // Preenche formulário
    document.getElementById('edit-exercicio-id').value = exercicio.id;
    document.getElementById('edit-tipo').value = (exercicio.tipo === 'verdadeiro_falso' ? 'alternativas' : (exercicio.tipo || 'alternativas'));
    var enunciadoVal = exercicio.enunciado || '';
    var editEditor = document.getElementById('editor-enunciado-edicao');
    if (editEditor) {
        if (/<[a-z][\s\S]*>/i.test(enunciadoVal)) {
            editEditor.innerHTML = enunciadoVal;
        } else if (typeof MathEditor !== 'undefined' && MathEditor.preencherDeLaTeX) {
            MathEditor.preencherDeLaTeX(editEditor, enunciadoVal);
        } else {
            editEditor.innerText = enunciadoVal;
        }
    }
    syncEnunciadoEdicao();
    // Código LaTeX do enunciado (para copiar em Inserir equação)
    var codigoWrap = document.getElementById('edit-codigo-latex-enunciado-wrap');
    var codigoTextarea = document.getElementById('edit-codigo-latex-enunciado');
    var btnVerCodigo = document.getElementById('btn-ver-codigo-latex-enunciado');
    var btnCopiarCodigo = document.getElementById('btn-copiar-codigo-latex-enunciado');
    function atualizarCodigoLaTeXEnunciado() {
        if (!codigoTextarea) return;
        var latex = '';
        if (typeof MathEditor !== 'undefined' && MathEditor.serializarParaLaTeX && editEditor) {
            latex = MathEditor.serializarParaLaTeX(editEditor);
        }
        if (!latex && enunciadoVal) latex = enunciadoVal;
        codigoTextarea.value = latex;
    }
    if (btnVerCodigo && codigoWrap) {
        btnVerCodigo.addEventListener('click', function() {
            atualizarCodigoLaTeXEnunciado();
            codigoWrap.classList.toggle('hidden');
        });
    }
    if (btnCopiarCodigo && codigoTextarea) {
        btnCopiarCodigo.addEventListener('click', function() {
            codigoTextarea.select();
            document.execCommand('copy');
            btnCopiarCodigo.textContent = 'Copiado!';
            setTimeout(function() { btnCopiarCodigo.textContent = 'Copiar'; }, 1500);
        });
    }
    atualizarCodigoLaTeXEnunciado();
    // Código LaTeX das alternativas
    var wrapAlt = document.getElementById('edit-codigo-latex-alternativas-wrap');
    var textareaAlt = document.getElementById('edit-codigo-latex-alternativas');
    var btnVerAlt = document.getElementById('btn-ver-codigo-latex-alternativas');
    var btnCopiarAlt = document.getElementById('btn-copiar-codigo-latex-alternativas');
    function atualizarCodigoLaTeXAlternativas() {
        if (!textareaAlt) return;
        var lista = document.getElementById('edit-opcoes-lista');
        var editors = lista ? lista.querySelectorAll('.alt-editor') : [];
        var linhas = [];
        var letras = ['A','B','C','D','E'];
        editors.forEach(function(ed, i) {
            var latex = (typeof MathEditor !== 'undefined' && MathEditor.serializarParaLaTeX) ? MathEditor.serializarParaLaTeX(ed) : (ed.innerText || '');
            if (latex) linhas.push(letras[i] + ': ' + latex);
        });
        textareaAlt.value = linhas.join('\n\n');
    }
    if (btnVerAlt && wrapAlt) {
        btnVerAlt.addEventListener('click', function() {
            atualizarCodigoLaTeXAlternativas();
            wrapAlt.classList.toggle('hidden');
        });
    }
    if (btnCopiarAlt && textareaAlt) {
        btnCopiarAlt.addEventListener('click', function() {
            textareaAlt.select();
            document.execCommand('copy');
            btnCopiarAlt.textContent = 'Copiado!';
            setTimeout(function() { btnCopiarAlt.textContent = 'Copiar tudo'; }, 1500);
        });
    }
    document.getElementById('edit-pontuacao').value = exercicio.pontuacao || '1.00';
    document.getElementById('edit-resposta_correta').value = exercicio.resposta_correta || '';
    document.getElementById('edit-status').value = (exercicio.status || 'publicado').toLowerCase();
    document.getElementById('edit-imagem-url').value = exercicio.imagem_url || '';
    if (exercicio.imagem_url) {
        document.getElementById('edit-imagem-enunciado-img').src = exercicio.imagem_url;
        document.getElementById('edit-imagem-enunciado-preview').classList.remove('hidden');
        var editActions = document.getElementById('edit-imagem-enunciado-actions');
        if (editActions) editActions.classList.add('hidden');
    } else {
        document.getElementById('edit-imagem-enunciado-preview').classList.add('hidden');
        var editActions = document.getElementById('edit-imagem-enunciado-actions');
        if (editActions) editActions.classList.remove('hidden');
    }
    
    // Carrega opções se for alternativas (ou verdadeiro_falso legado)
    if (exercicio.tipo === 'alternativas' || exercicio.tipo === 'verdadeiro_falso') {
        const container = document.getElementById('edit-opcoes-container');
        const lista = document.getElementById('edit-opcoes-lista');
        const campoGabarito = document.getElementById('edit-resposta_correta');
        const hintGabarito = document.getElementById('edit-gabarito-hint');
        container.classList.remove('hidden');
        if (campoGabarito) {
            campoGabarito.readOnly = true;
            campoGabarito.classList.add('bg-gray-50');
        }
        if (hintGabarito) hintGabarito.classList.remove('hidden');
        lista.innerHTML = '';
        opcoesCountEdicao = 0;
        
        let opcoes = null;
        if (exercicio.questoes_json) {
            if (exercicio.questoes_json.opcoes && Array.isArray(exercicio.questoes_json.opcoes)) {
                opcoes = exercicio.questoes_json.opcoes;
            } else if (Array.isArray(exercicio.questoes_json)) {
                opcoes = exercicio.questoes_json;
            } else if (typeof exercicio.questoes_json === 'string') {
                try {
                    const parsed = JSON.parse(exercicio.questoes_json);
                    if (parsed && parsed.opcoes && Array.isArray(parsed.opcoes)) {
                        opcoes = parsed.opcoes;
                    } else if (Array.isArray(parsed)) {
                        opcoes = parsed;
                    }
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e);
                }
            }
        }
        
        if (opcoes && opcoes.length > 0) {
            opcoes.forEach((opcao) => {
                adicionarOpcaoEdicao(opcao);
            });
            
            setTimeout(() => {
                let respostaCorretaEncontrada = false;
                opcoes.forEach((opcao, index) => {
                    if (opcao.correta === true || opcao.correta === 1 || opcao.correta === '1') {
                        const radio = document.getElementById(`radio-edit-${index}`);
                        if (radio) {
                            radio.checked = true;
                            respostaCorretaEncontrada = true;
                        }
                    }
                });
                
                if (!respostaCorretaEncontrada && exercicio.resposta_correta) {
                    const letrasCorretas = exercicio.resposta_correta
                        .split('|')
                        .map(v => v.trim().toUpperCase())
                        .filter(Boolean);
                    letrasCorretas.forEach(letraCorreta => {
                        const indexCorreto = letras.indexOf(letraCorreta);
                        if (indexCorreto >= 0 && indexCorreto < opcoesCountEdicao) {
                            const radio = document.getElementById(`radio-edit-${indexCorreto}`);
                            if (radio) radio.checked = true;
                        }
                    });
                }
                sincronizarGabaritoDoRadioEdicao();
            }, 200);
        } else {
            adicionarOpcaoEdicao();
            adicionarOpcaoEdicao();
        }
    } else {
        document.getElementById('edit-opcoes-container').classList.add('hidden');
    }
    
    modal.classList.remove('hidden');
    
    // Renderizar LaTeX no modal após conteúdo dinâmico
    if (window.MathJax && window.MathJax.typesetPromise) {
        MathJax.typesetPromise([modal]).catch(function(err) { console.warn('MathJax typeset:', err); });
    } else {
        var checkMathJax = setInterval(function() {
            if (window.MathJax && window.MathJax.typesetPromise) {
                clearInterval(checkMathJax);
                MathJax.typesetPromise([modal]).catch(function(err) { console.warn('MathJax typeset:', err); });
            }
        }, 100);
        setTimeout(function() { clearInterval(checkMathJax); }, 5000);
    }
    
    const tipoSelect = document.getElementById('edit-tipo');
    tipoSelect.addEventListener('change', function() {
        const container = document.getElementById('edit-opcoes-container');
        const campoGabarito = document.getElementById('edit-resposta_correta');
        const hintGabarito = document.getElementById('edit-gabarito-hint');
        if (this.value === 'alternativas') {
            container.classList.remove('hidden');
            if (campoGabarito) {
                campoGabarito.readOnly = true;
                campoGabarito.classList.add('bg-gray-50');
            }
            if (hintGabarito) hintGabarito.classList.remove('hidden');
            if (opcoesCountEdicao === 0) {
                adicionarOpcaoEdicao();
                adicionarOpcaoEdicao();
            }
            sincronizarGabaritoDoRadioEdicao();
        } else {
            container.classList.add('hidden');
            if (campoGabarito) {
                campoGabarito.readOnly = false;
                campoGabarito.classList.remove('bg-gray-50');
            }
            if (hintGabarito) hintGabarito.classList.add('hidden');
            document.getElementById('edit-opcoes-lista').innerHTML = '';
            opcoesCountEdicao = 0;
        }
    });
    
    setTimeout(() => {
        adicionarListenerFormEdicao();
        var editEditor = document.getElementById('editor-enunciado-edicao');
        if (editEditor) {
            editEditor.addEventListener('input', syncEnunciadoEdicao);
            editEditor.addEventListener('blur', syncEnunciadoEdicao);
            editEditor.addEventListener('keyup', syncEnunciadoEdicao);
        }
    }, 100);
}

function fecharModalEdicao() {
    const modal = document.getElementById('modal-editar-exercicio');
    if (modal) {
        modal.remove();
    }
    exercicioEditando = null;
    opcoesCountEdicao = 0;
}

let opcoesCountEdicao = 0;

function adicionarOpcaoEdicao(opcaoExistente = null) {
    if (opcoesCountEdicao >= 5) {
        alert('Máximo de 5 alternativas permitidas (A, B, C, D, E)');
        return;
    }
    
    const letra = letras[opcoesCountEdicao];
    const index = opcoesCountEdicao;
    opcoesCountEdicao++;
    const container = document.getElementById('edit-opcoes-lista');
    const div = document.createElement('div');
    div.className = 'p-3 border border-gray-300 rounded-lg bg-gray-50';
    div.setAttribute('data-opcao-index', index);
    
    let texto = '';
    if (opcaoExistente) {
        texto = opcaoExistente.texto || opcaoExistente.text || opcaoExistente.label || opcaoExistente.conteudo || '';
    }
    
    let correta = false;
    if (opcaoExistente) {
        correta = opcaoExistente.correta === true || 
                  opcaoExistente.correta === 1 || 
                  opcaoExistente.correta === '1' ||
                  opcaoExistente.correta === 'true' ||
                  (opcaoExistente.letra && typeof exercicioEditando !== 'undefined' && exercicioEditando && exercicioEditando.resposta_correta && 
                   exercicioEditando.resposta_correta.split('|').map(v => v.trim().toUpperCase()).includes(opcaoExistente.letra.toUpperCase()));
    }
    
    div.className = 'alt-item';
    const editorId = `opcao-editor-edicao-${index}`;
    div.innerHTML = `
        <div class="alt-letter">${letra}</div>
        <div class="alt-input-wrap">
            <label class="opcao-texto-label block text-xs font-medium text-gray-700 mb-1">Editar texto da alternativa ${letra}</label>
            <div class="alt-toolbar">
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('bold')" title="Negrito"><b>B</b></button>
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('italic')" title="Itálico"><i>I</i></button>
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();document.execCommand('underline')" title="Sublinhado"><u>U</u></button>
                <div class="alt-tb-sep"></div>
                <button type="button" class="alt-tb-btn alt-tb-eq" onmousedown="event.preventDefault();abrirMathAltJornada(this)" title="Inserir equação">∑ eq</button>
                <button type="button" class="alt-tb-btn" onmousedown="event.preventDefault();abrirInserirImagemAltJornada(this)" title="Inserir ou colar imagem">📷</button>
            </div>
            <div class="alt-editor" id="${editorId}" contenteditable="true" data-math="inline" data-placeholder="Alternativa ${letra}..."></div>
        </div>
        <div class="flex items-center space-x-2 flex-shrink-0">
            <input type="checkbox" name="resposta_opcao_edit[]" value="${index}" id="radio-edit-${index}" class="w-5 h-5 text-blue-600 focus:ring-blue-500">
            <label for="radio-edit-${index}" class="opcao-radio-label alt-correct">Gabarito</label>
        </div>
        <button type="button" onclick="removerOpcaoEdicao(this)" class="px-3 py-2 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">Remover</button>
    `;
    
    const editor = div.querySelector('.alt-editor');
    if (editor) {
        if (/<[a-z][\s\S]*>/i.test(texto)) {
            editor.innerHTML = texto;
        } else if (typeof MathEditor !== 'undefined' && MathEditor.preencherDeLaTeX) {
            MathEditor.preencherDeLaTeX(editor, texto);
        } else {
            editor.innerText = texto;
        }
    }
    
    const radio = div.querySelector('input[type="radio"]');
    if (radio && correta) {
        radio.checked = true;
    }
    
    container.appendChild(div);
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
    const opcoes = container.querySelectorAll('div[data-opcao-index]');
    opcoes.forEach((opcao, index) => {
        const letra = letras[index];
        opcao.setAttribute('data-opcao-index', index);
        const letraEl = opcao.querySelector('.alt-letter') || opcao.querySelector('.bg-blue-600');
        if (letraEl) letraEl.textContent = letra;
        const editor = opcao.querySelector('.alt-editor');
        if (editor) {
            editor.id = `opcao-editor-edicao-${index}`;
            editor.setAttribute('data-placeholder', `Alternativa ${letra}...`);
        }
        const labelTexto = opcao.querySelector('.opcao-texto-label');
        if (labelTexto) labelTexto.textContent = `Editar texto da alternativa ${letra}`;
        const radio = opcao.querySelector('input[type="radio"]');
        if (radio) {
            radio.value = index;
            radio.id = `radio-edit-${index}`;
        }
        const labelRadio = opcao.querySelector('.opcao-radio-label');
        if (labelRadio) labelRadio.setAttribute('for', `radio-edit-${index}`);
    });
}

function adicionarListenerFormEdicao() {
    const formEdit = document.getElementById('form-editar-exercicio');
    if (formEdit && !formEdit.hasAttribute('data-listener-adicionado')) {
        formEdit.setAttribute('data-listener-adicionado', 'true');
        formEdit.addEventListener('submit', function(e) {
                e.preventDefault();
                syncEnunciadoEdicao();
                const obterConteudoEditorRichEdicao = function(ed) {
                    if (!ed) return '';
                    var html = (ed.innerHTML || '').trim();
                    if (!html) return '';
                    if (ed.querySelector('ul, ol, img') || /<ul|<ol|<li|<img/i.test(html)) {
                        return html;
                    }
                    if (typeof MathEditor !== 'undefined' && MathEditor.serializarParaLaTeX) {
                        var serializado = MathEditor.serializarParaLaTeX(ed);
                        if (serializado && String(serializado).trim() !== '') {
                            return String(serializado).trim();
                        }
                    }
                    return (ed.innerText || '').trim();
                };
                const formData = new FormData(this);
                formData.append('_token', <?= json_encode($csrf_token) ?>);
                
                if (formData.get('tipo') === 'alternativas') {
                    const opcoes = [];
                    const opcoesItems = document.querySelectorAll('#edit-opcoes-lista .alt-item');
                    const respostaIndexes = formData.getAll('resposta_opcao_edit[]');
                    
                    if (opcoesItems.length < 2) {
                        alert('Adicione pelo menos 2 alternativas');
                        return;
                    }
                    
                    if (!respostaIndexes || respostaIndexes.length === 0) {
                        alert('Selecione pelo menos uma alternativa de gabarito');
                        return;
                    }
                    
                    const respostaSet = new Set(respostaIndexes.map(v => String(v)));
                    opcoesItems.forEach((item, index) => {
                        var ed = item.querySelector('.alt-editor');
                        var texto = obterConteudoEditorRichEdicao(ed);
                        if (texto) {
                            opcoes.push({
                                letra: letras[index],
                                texto: texto.trim(),
                                correta: respostaSet.has(index.toString())
                            });
                        }
                    });
                    
                    if (opcoes.length < 2) {
                        alert('Adicione pelo menos 2 alternativas válidas');
                        return;
                    }
                    
                    formData.set('questoes_json', JSON.stringify({ opcoes: opcoes }));
                    const letrasCorretas = respostaIndexes
                        .map(v => letras[parseInt(v, 10)])
                        .filter(Boolean);
                    formData.set('resposta_correta', letrasCorretas.join('|'));
                }
                
                // Garantir que o enunciado foi sincronizado (evita enviar vazio e apagar no banco)
                var enunciadoEnviar = formData.get('enunciado');
                if (!enunciadoEnviar || String(enunciadoEnviar).trim() === '') {
                    var edEnunciado = document.getElementById('editor-enunciado-edicao');
                    if (edEnunciado) {
                        var html = (edEnunciado.innerHTML || '').trim();
                        var txt = (edEnunciado.innerText || '').trim();
                        if (html) formData.set('enunciado', html);
                        else if (txt) formData.set('enunciado', txt);
                    }
                }
                
                fetch('<?= URL ?>/professor/jornadas/modulos/atualizar-exercicio', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Exercício atualizado com sucesso!');
                        fecharModalEdicao();
                        location.reload();
                    } else {
                        alert('Erro: ' + (data.error || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    alert('Erro de conexão');
                    console.error(error);
                });
            });
    }
}

</script>
