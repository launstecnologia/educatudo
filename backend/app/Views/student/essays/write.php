<?php
$primaryColor = LayoutHelper::get('primary_color', '#6366f1');
$proposalId = (int)($proposal['id'] ?? 0);
$proposalImages = [];
if (!empty($proposal['images_json'])) {
    $proposalImages = is_string($proposal['images_json']) ? json_decode($proposal['images_json'], true) : $proposal['images_json'];
    if (!is_array($proposalImages)) $proposalImages = [];
}
$showTitleField = (int)($proposal['show_title_field'] ?? 1);
$startsAt = !empty($proposal['starts_at']) ? $proposal['starts_at'] : null;
$endsAt = !empty($proposal['ends_at']) ? $proposal['ends_at'] : null;
$themeMode = $proposal['theme_mode'] ?? 'configurar';
$submissionMode = $proposal['submission_mode'] ?? 'texto';
$isPhotoOnlyMode = ($submissionMode === 'foto');
$isFlexibleMode = ($submissionMode === 'texto_ou_foto');
$temaProntoFile = !empty($proposal['tema_pronto_file']) ? $proposal['tema_pronto_file'] : null;
$repertoriosList = [];
$hasRepertoire = false;
if (!empty($proposal['repertoire'])) {
    $raw = trim($proposal['repertoire']);
    if ($raw !== '' && $raw !== '[]' && $raw !== 'null') {
        if (preg_match('/^\s*\[/', $raw)) {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                $repertoriosList = array_filter($dec, fn($v) => is_string($v) && trim($v) !== '');
                $hasRepertoire = !empty($repertoriosList);
            }
        } else {
            $hasRepertoire = true;
        }
    }
}
?>
<style>
    :root {
        --line-height-mobile: 28px;
        --line-height-desktop: 32px;
    }
    .essay-container {
        position: relative;
        overflow: hidden;
    }
    .essay-lines {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        pointer-events: none;
        z-index: 0;
    }
    .essay-line-numbers {
        display: none;
    }
    .essay-line-number {
        height: var(--line-height-mobile);
        line-height: var(--line-height-mobile);
        font-size: 13px;
        color: #9ca3af;
        text-align: center;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        user-select: none;
    }
    .essay-line {
        height: var(--line-height-mobile);
        border-bottom: 1px solid #d1d5db;
        box-sizing: border-box;
    }
    @media (min-width: 640px) {
        .essay-line-number {
            height: var(--line-height-desktop);
            line-height: var(--line-height-desktop);
        }
        .essay-line {
            height: var(--line-height-desktop);
        }
    }
    .essay-lined-paper {
        position: relative;
        z-index: 1;
        background: transparent !important;
        line-height: var(--line-height-mobile) !important;
        font-family: 'Times New Roman', Georgia, serif !important;
        font-size: 15px !important;
        padding: 0 16px !important;
        -webkit-overflow-scrolling: touch;
    }
    @media (min-width: 640px) {
        .essay-lined-paper {
            line-height: var(--line-height-desktop) !important;
            font-size: 16px !important;
            padding: 0 18px !important;
        }
    }
    .essay-lined-paper::placeholder {
        color: #9ca3af;
    }
    .timer-badge {
        background: linear-gradient(135deg, <?= htmlspecialchars($primaryColor) ?> 0%, <?= htmlspecialchars($primaryColor) ?>cc 100%);
    }
    .btn-primary-gradient {
        background: linear-gradient(135deg, <?= htmlspecialchars($primaryColor) ?> 0%, <?= htmlspecialchars($primaryColor) ?>dd 100%);
    }
    .btn-primary-gradient:hover {
        filter: brightness(1.1);
    }
    .pdf-viewer-container {
        height: 50vh;
        min-height: 300px;
    }
    @media (min-width: 1024px) {
        .pdf-viewer-container {
            height: calc(100vh - 200px);
            max-height: 700px;
        }
    }
    .mobile-scroll-area {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }
    #essay-write-page {
        min-height: 100vh;
        min-height: 100dvh;
    }
</style>

<div id="essay-write-page" class="bg-gray-50">
    <div class="w-full px-3 sm:px-4 py-3 sm:py-4">
        <!-- Header compacto mobile -->
        <div class="flex items-center justify-between gap-2 mb-3">
            <div class="flex-1 min-w-0">
                <h1 class="text-base sm:text-xl font-bold text-gray-900 truncate"><?= htmlspecialchars($proposal['title'] ?? '') ?></h1>
                <?php if ($startsAt || $endsAt): ?>
                <p class="text-xs sm:text-sm text-gray-500 truncate">
                    Disponível <?php
                    if ($startsAt && $endsAt) {
                        echo date('d/m/Y H:i', strtotime($startsAt)) . ' a ' . date('d/m/Y H:i', strtotime($endsAt));
                    } elseif ($startsAt) {
                        echo 'a partir de ' . date('d/m/Y H:i', strtotime($startsAt));
                    } else {
                        echo 'até ' . date('d/m/Y H:i', strtotime($endsAt));
                    }
                    ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <div id="essayTimerWrap" class="hidden items-center gap-1.5 px-3 py-1.5 rounded-full text-white timer-badge shadow-md text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span id="essayTimer" class="font-mono font-bold">00:00</span>
                </div>
                <a href="<?= URL ?>/jornada-redacao/<?= $proposalId ?>" class="text-gray-600 text-sm px-2.5 py-1.5 bg-white rounded-lg border border-gray-200 hover:bg-gray-50">
                    ← Voltar
                </a>
            </div>
        </div>

        <!-- Layout responsivo -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-3 sm:gap-4">
            <!-- Coluna principal: Escrever redação -->
            <div class="lg:col-span-3 space-y-3">
                <?php if ($isFlexibleMode): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-2 flex gap-2">
                    <button type="button" id="tabModoTexto" class="flex-1 px-3 py-2 rounded-lg text-sm font-medium bg-purple-600 text-white">Digitar redação</button>
                    <button type="button" id="tabModoFoto" class="flex-1 px-3 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700">Enviar só a foto</button>
                </div>
                <?php endif; ?>

                <!-- Painel: somente foto -->
                <div id="painelFotoRedacao" class="<?= ($isPhotoOnlyMode || $isFlexibleMode) ? '' : 'hidden' ?> <?= $isPhotoOnlyMode ? '' : 'hidden' ?> space-y-3">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-purple-100 flex items-center justify-center">
                            <i class="fas fa-camera text-2xl text-purple-600"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Envie a foto da sua redação</h3>
                        <p class="text-sm text-gray-600 mb-4">Fotografe ou escolha a imagem da folha manuscrita. Não é necessário digitar o texto.</p>
                        <label class="cursor-pointer inline-flex items-center gap-2 px-5 py-3 rounded-lg text-white text-sm font-semibold btn-primary-gradient">
                            <i class="fas fa-image"></i> Selecionar foto
                            <input type="file" id="imagemRedacaoFoto" class="sr-only" accept="image/*" capture="environment">
                        </label>
                        <p id="nomeArquivoFoto" class="text-xs text-gray-500 mt-3"></p>
                        <div id="previewFotoRedacao" class="mt-4 hidden">
                            <img id="previewFotoImg" src="" alt="Preview" class="max-h-64 mx-auto rounded-lg border border-gray-200 shadow-sm">
                        </div>
                    </div>
                    <form id="photoOnlyForm">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <input type="hidden" id="content_image_base64_foto" name="content_image_base64" value="">
                        <input type="hidden" id="content_image_mime_foto" name="content_image_mime" value="">
                        <input type="hidden" name="photo_only" value="1">
                        <input type="hidden" name="content_text" value="">
                        <div id="msgFoto" class="hidden mb-3"></div>
                        <div class="flex justify-end gap-2">
                            <button type="submit" name="submit" value="0" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm">Rascunho</button>
                            <button type="submit" name="submit" value="1" class="px-5 py-2 text-white rounded-lg text-sm font-semibold btn-primary-gradient">Enviar foto</button>
                        </div>
                    </form>
                </div>

                <!-- Painel: digitar / OCR -->
                <div id="painelTextoRedacao" class="<?= $isPhotoOnlyMode ? 'hidden' : '' ?> space-y-3">
                <!-- Barra de ações compacta -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white text-sm font-medium btn-primary-gradient">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="hidden sm:inline">Upload Redação</span>
                                <span class="sm:hidden">Upload</span>
                                <input type="file" id="imagemRedacao" class="sr-only" accept="image/*" onchange="uploadAndTranscribe(this)">
                            </label>
                            <a id="downloadAnexoBtn" href="#" download class="hidden items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-5l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                <span class="hidden sm:inline">Download anexo</span>
                                <span class="sm:hidden">Download</span>
                            </a>
                        </div>
                        <div class="flex items-center gap-2 sm:gap-3">
                            <span id="autosaveStatus" class="text-xs text-gray-500 hidden sm:inline">Autosave ativo</span>
                            <span id="nomeArquivo" class="text-xs text-gray-500 truncate max-w-[100px] sm:max-w-[150px]"></span>
                            <span class="text-sm text-gray-600"><span id="wordCount" class="font-semibold">0</span> <span class="hidden sm:inline">palavras</span><span class="sm:hidden">pal.</span></span>
                        </div>
                    </div>
                </div>

                <!-- Área de escrita com linhas -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <form id="writeForm">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <input type="hidden" id="text_structure_json" name="text_structure_json" value="<?= htmlspecialchars((string) ($submission['ocr_text_structure_json'] ?? '')) ?>">
                        <input type="hidden" id="ocr_layout_json" name="ocr_layout_json" value="<?= htmlspecialchars((string) ($submission['ocr_layout_json'] ?? '')) ?>">
                        <input type="hidden" id="content_image_base64" name="content_image_base64" value="">
                        <input type="hidden" id="content_image_mime" name="content_image_mime" value="">
                        
                        <?php if ($showTitleField): ?>
                        <div class="border-b border-gray-200 px-3 py-2 sm:p-4">
                            <input type="text" id="essay_title" name="essay_title" value="" 
                                placeholder="Título da redação (opcional)"
                                class="w-full text-base sm:text-lg font-semibold text-gray-900 placeholder-gray-400 border-0 focus:ring-0 p-0 bg-transparent"
                                style="outline: none;">
                        </div>
                        <?php endif; ?>

                        <div class="essay-container">
                            <div id="essayLines" class="essay-lines"></div>
                            <textarea id="content_text" name="content_text"
                                class="w-full border-0 focus:ring-0 resize-none essay-lined-paper mobile-scroll-area"
                                placeholder="Comece a escrever sua redação aqui..."
                                spellcheck="false" autocorrect="off" autocapitalize="sentences"
                                style="min-height: 50vh; max-height: 70vh; outline: none;"><?= $submission ? htmlspecialchars($submission['content_text'] ?? $submission['ocr_text'] ?? '') : '' ?></textarea>
                        </div>

                        <div id="msg" class="hidden mx-3 mb-3"></div>

                        <!-- Botões de ação -->
                        <div class="border-t border-gray-200 p-3 bg-gray-50 flex flex-wrap items-center justify-between gap-2">
                            <button type="submit" name="submit" value="0" class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium">
                                💾 Rascunho
                            </button>
                            <button type="submit" name="submit" value="1" class="px-4 sm:px-5 py-2 text-white rounded-lg text-sm font-semibold flex items-center gap-2 btn-primary-gradient">
                                Enviar
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
                </div><!-- /painelTextoRedacao -->
            </div>

            <!-- Coluna lateral: acesso rápido -->
            <div class="lg:col-span-2 space-y-3">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                    <h3 class="font-semibold text-gray-800 text-sm mb-2">📘 Tema e repertório</h3>
                    <p class="text-sm text-gray-600 mb-3">Abra em outra tela para consultar os materiais de apoio enquanto escreve.</p>
                    <a href="<?= URL ?>/jornada-redacao/<?= (int) $proposal['id'] ?>" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-white"
                        style="background-color: <?= htmlspecialchars($primaryColor) ?>;">
                        <i class="fas fa-external-link-alt"></i> Abrir tema e repertório
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal loading transcrição -->
<div id="transcriptionModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xs w-full p-6 text-center">
        <div class="relative w-14 h-14 mx-auto mb-3">
            <div class="absolute inset-0 rounded-full border-4 border-gray-200"></div>
            <div class="absolute inset-0 rounded-full border-4 border-t-transparent animate-spin" style="border-color: <?= htmlspecialchars($primaryColor) ?>; border-top-color: transparent;"></div>
        </div>
        <p class="text-gray-800 font-semibold">Transcrevendo...</p>
        <p class="text-xs text-gray-500 mt-1">Aguarde alguns segundos</p>
    </div>
</div>

<script>
(function() {
    const proposalId = <?= $proposalId ?>;
    const baseUrl = '<?= URL ?>';
    const submissionMode = <?= json_encode($submissionMode) ?>;
    const token = document.querySelector('input[name="_token"]').value;
    const msg = document.getElementById('msg');
    const textarea = document.getElementById('content_text');
    const downloadAnexoBtn = document.getElementById('downloadAnexoBtn');
    const textStructureInput = document.getElementById('text_structure_json');
    const layoutInput = document.getElementById('ocr_layout_json');
    const contentImageBase64Input = document.getElementById('content_image_base64');
    const contentImageMimeInput = document.getElementById('content_image_mime');
    const lineNumbersContainer = document.getElementById('essayLineNumbers');
    var lastStructuredText = textarea.value.trim();
    var uploadedAnexoObjectUrl = '';
    var ocrLineNumbers = [];
    var paragraphIndentSpaces = 4;
    var autosaveTimer = null;
    var autosaveInFlight = false;
    var autosaveDirty = false;
    var AUTOSAVE_DEBOUNCE_MS = 2500;

    function safeParseJson(raw) {
        if (!raw || typeof raw !== 'string') return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function normalizeIndentSpacesFromLayout(layout) {
        var indentPx = parseInt((layout && layout.paragraph_indent) || 28, 10);
        if (isNaN(indentPx) || indentPx < 0) indentPx = 28;
        paragraphIndentSpaces = Math.max(2, Math.min(8, Math.round(indentPx / 7)));
        return paragraphIndentSpaces;
    }

    function structureToText(structure, indentSpaces) {
        if (!Array.isArray(structure) || !structure.length) return '';
        var linesByNumber = {};
        var maxLineNumber = 0;
        var pad = new Array((indentSpaces || 4) + 1).join(' ');
        for (var i = 0; i < structure.length; i++) {
            var paragraph = structure[i] || {};
            var lines = Array.isArray(paragraph.lines) ? paragraph.lines : [];
            for (var j = 0; j < lines.length; j++) {
                var line = lines[j] || {};
                var lineNumber = parseInt(line.line_number || 0, 10);
                if (isNaN(lineNumber) || lineNumber <= 0) continue;
                var lineText = (line.text || '').toString().trim();
                if (lineText === '') continue;
                if (j === 0) {
                    lineText = pad + lineText;
                }
                linesByNumber[lineNumber] = lineText;
                if (lineNumber > maxLineNumber) maxLineNumber = lineNumber;
            }
        }
        if (maxLineNumber <= 0) return '';
        var outputLines = [];
        for (var n = 1; n <= maxLineNumber; n++) {
            outputLines.push(linesByNumber[n] || '');
        }
        return outputLines.join('\n').replace(/\n+$/g, '');
    }

    function extractLineNumbersFromStructure(structure) {
        if (!Array.isArray(structure) || !structure.length) return [];
        var numbers = [];
        for (var i = 0; i < structure.length; i++) {
            var paragraph = structure[i] || {};
            var lines = Array.isArray(paragraph.lines) ? paragraph.lines : [];
            for (var j = 0; j < lines.length; j++) {
                var parsed = parseInt((lines[j] && lines[j].line_number) || 0, 10);
                if (!isNaN(parsed) && parsed > 0) numbers.push(parsed);
            }
        }
        return numbers;
    }

    function formatLineNumber(value) {
        var parsed = parseInt(value || 0, 10);
        if (isNaN(parsed) || parsed <= 0) return '';
        return parsed < 10 ? ('0' + parsed) : String(parsed);
    }

    function bindDownloadAnexo(file) {
        if (!downloadAnexoBtn || !file) return;
        if (uploadedAnexoObjectUrl) {
            URL.revokeObjectURL(uploadedAnexoObjectUrl);
            uploadedAnexoObjectUrl = '';
        }
        uploadedAnexoObjectUrl = URL.createObjectURL(file);
        downloadAnexoBtn.href = uploadedAnexoObjectUrl;
        downloadAnexoBtn.download = file.name || 'redacao-anexo';
        downloadAnexoBtn.classList.remove('hidden');
        downloadAnexoBtn.classList.add('inline-flex');
    }

    function showTranscriptionModal() { document.getElementById('transcriptionModal').classList.remove('hidden'); }
    function hideTranscriptionModal() { document.getElementById('transcriptionModal').classList.add('hidden'); }

    function setAutosaveStatus(message, type) {
        var el = document.getElementById('autosaveStatus');
        if (!el) return;
        el.textContent = message;
        if (type === 'ok') {
            el.className = 'text-xs text-green-600 hidden sm:inline';
        } else if (type === 'saving') {
            el.className = 'text-xs text-blue-600 hidden sm:inline';
        } else if (type === 'error') {
            el.className = 'text-xs text-red-600 hidden sm:inline';
        } else {
            el.className = 'text-xs text-gray-500 hidden sm:inline';
        }
    }

    function saveDraftSilently() {
        if (autosaveInFlight) return;
        if (!autosaveDirty) return;
        var text = (textarea.value || '').trim();
        if (!text) return;

        autosaveInFlight = true;
        setAutosaveStatus('Salvando...', 'saving');

        var fd = new FormData();
        fd.append('_token', token);
        fd.append('content_text', textarea.value);
        fd.append('submit', '0');
        fd.append('text_structure_json', textStructureInput ? textStructureInput.value : '');
        fd.append('ocr_layout_json', layoutInput ? layoutInput.value : '');

        fetch(baseUrl + '/jornada-redacao/' + proposalId + '/salvar-texto', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d && d.success) {
                    autosaveDirty = false;
                    setAutosaveStatus('Salvo às ' + new Date().toLocaleTimeString('pt-BR'), 'ok');
                } else {
                    setAutosaveStatus('Erro ao salvar', 'error');
                }
            })
            .catch(function() {
                setAutosaveStatus('Sem conexão', 'error');
            })
            .finally(function() {
                autosaveInFlight = false;
            });
    }

    function scheduleAutosave() {
        autosaveDirty = true;
        setAutosaveStatus('Alterações pendentes...', 'idle');
        if (autosaveTimer) clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(saveDraftSilently, AUTOSAVE_DEBOUNCE_MS);
    }

    function mostrarErro(m) {
        var modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4';
        modal.innerHTML = '<div class="bg-white rounded-xl p-5 max-w-sm w-full shadow-2xl"><p class="text-gray-700 mb-4 text-sm">' + (m || 'Erro') + '</p><button onclick="this.closest(\'.fixed\').remove()" class="w-full py-2.5 rounded-lg text-white text-sm font-medium btn-primary-gradient">Fechar</button></div>';
        document.body.appendChild(modal);
        modal.addEventListener('click', function(e) { if (e.target === modal) modal.remove(); });
    }

    function mostrarAvisoRevisao() {
        var modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4';
        modal.innerHTML = '<div class="bg-white rounded-xl p-5 max-w-sm w-full shadow-2xl text-center">' +
            '<div class="text-4xl mb-3">✅</div>' +
            '<h3 class="text-lg font-bold text-gray-800 mb-2">Texto transcrito!</h3>' +
            '<p class="text-gray-600 text-sm mb-4">Revise o texto para corrigir possíveis erros antes de enviar.</p>' +
            '<button onclick="this.closest(\'.fixed\').remove()" class="w-full py-2.5 rounded-lg text-white text-sm font-semibold btn-primary-gradient">Ok, vou revisar</button>' +
            '</div>';
        document.body.appendChild(modal);
    }

    window.uploadAndTranscribe = function(input) {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];
        if (file.size > 10 * 1024 * 1024) { alert('Arquivo muito grande! Máximo 10MB.'); return; }
        if (file.type.indexOf('image/') !== 0) { 
            alert('Envie uma imagem (JPG, PNG) da redação.'); 
            input.value = '';
            return; 
        }
        
        document.getElementById('nomeArquivo').textContent = file.name;
        bindDownloadAnexo(file);
        showTranscriptionModal();
        
        var reader = new FileReader();
        reader.onload = function() {
            var dataUrl = String(reader.result || '');
            var mimeMatch = dataUrl.match(/^data:(image\/[a-zA-Z0-9.+-]+);base64,/);
            var detectedMime = mimeMatch ? mimeMatch[1] : '';
            var base64 = dataUrl.replace(/^data:image\/[a-zA-Z0-9.+-]+;base64,/, '');
            if (contentImageBase64Input) {
                contentImageBase64Input.value = base64;
            }
            if (contentImageMimeInput) {
                contentImageMimeInput.value = detectedMime;
            }
            var formData = new FormData();
            formData.append('_token', token);
            formData.append('image_base64', base64);
            fetch(baseUrl + '/jornada-redacao/ocr', { method: 'POST', body: formData })
                .then(function(r) { return r.text().then(function(t) { return { ok: r.ok, text: t }; }); })
                .then(function(res) {
                    hideTranscriptionModal();
                    try {
                        var d = JSON.parse(res.text);
                        var hasTextPayload = !!(d && (d.raw_text || d.text));
                        var isSuccess = (typeof d.success === 'undefined') ? hasTextPayload : !!d.success;
                        if (isSuccess && hasTextPayload) {
                            var apiStructure = Array.isArray(d.text_structure) ? d.text_structure : [];
                            normalizeIndentSpacesFromLayout(d.layout || {});
                            var structuredText = apiStructure.length ? structureToText(apiStructure, paragraphIndentSpaces) : (d.raw_text || d.text || '');
                            textarea.value = structuredText;
                            if (textStructureInput) {
                                textStructureInput.value = apiStructure.length ? JSON.stringify(apiStructure) : '';
                            }
                            if (layoutInput) {
                                layoutInput.value = JSON.stringify(d.layout || {});
                            }
                            ocrLineNumbers = apiStructure.length ? extractLineNumbersFromStructure(apiStructure) : [];
                            lastStructuredText = structuredText.trim();
                            updateCounts();
                            startEssayTimer();
                            updateLines();
                            mostrarAvisoRevisao();
                        } else {
                            mostrarErro(d.error || 'Não foi possível extrair o texto.');
                        }
                    } catch (e) {
                        mostrarErro(res.ok ? 'Resposta inválida.' : 'Erro de conexão.');
                    }
                    input.value = '';
                    document.getElementById('nomeArquivo').textContent = '';
                })
                .catch(function() {
                    hideTranscriptionModal();
                    mostrarErro('Erro de conexão.');
                    input.value = '';
                    document.getElementById('nomeArquivo').textContent = '';
                });
        };
        reader.readAsDataURL(file);
    };

    // Cronômetro
    var essayTimerStarted = false;
    var essayTimerStartAt = 0;
    var essayTimerInterval = null;
    
    function formatEssayTime(seconds) {
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;
        if (h > 0) return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }
    
    function startEssayTimer() {
        if (essayTimerStarted) return;
        essayTimerStarted = true;
        essayTimerStartAt = Math.floor(Date.now() / 1000);
        var wrap = document.getElementById('essayTimerWrap');
        var el = document.getElementById('essayTimer');
        if (wrap) {
            wrap.classList.remove('hidden');
            wrap.classList.add('flex');
        }
        essayTimerInterval = setInterval(function() {
            var sec = Math.floor(Date.now() / 1000) - essayTimerStartAt;
            if (el) el.textContent = formatEssayTime(sec);
        }, 1000);
    }

    // Contador de palavras
    function updateCounts() {
        var t = textarea.value.trim();
        var words = t ? t.split(/\s+/).length : 0;
        document.getElementById('wordCount').textContent = words;
    }

    // Linhas pautadas dinâmicas
    var linesContainer = document.getElementById('essayLines');
    var lineHeight = window.innerWidth >= 640 ? 32 : 28;
    
    function updateLines() {
        var textareaHeight = textarea.scrollHeight;
        var numLines = Math.max(Math.ceil(textareaHeight / lineHeight), 30);
        if (ocrLineNumbers.length) {
            numLines = Math.max(numLines, ocrLineNumbers.length + 2);
        }
        var currentLines = linesContainer.children.length;
        var currentNumbers = lineNumbersContainer ? lineNumbersContainer.children.length : 0;
        
        if (currentLines < numLines) {
            for (var i = currentLines; i < numLines; i++) {
                var line = document.createElement('div');
                line.className = 'essay-line';
                linesContainer.appendChild(line);
            }
        } else if (currentLines > numLines) {
            for (var i = currentLines; i > numLines; i--) {
                linesContainer.removeChild(linesContainer.lastChild);
            }
        }
        if (lineNumbersContainer) {
            if (currentNumbers < numLines) {
                for (var j = currentNumbers; j < numLines; j++) {
                    var numberRow = document.createElement('div');
                    numberRow.className = 'essay-line-number';
                    lineNumbersContainer.appendChild(numberRow);
                }
            } else if (currentNumbers > numLines) {
                for (var k = currentNumbers; k > numLines; k--) {
                    lineNumbersContainer.removeChild(lineNumbersContainer.lastChild);
                }
            }
            var lastBase = ocrLineNumbers.length ? ocrLineNumbers[ocrLineNumbers.length - 1] : 0;
            for (var n = 0; n < numLines; n++) {
                var value = ocrLineNumbers[n] || (ocrLineNumbers.length ? (lastBase + (n - ocrLineNumbers.length + 1)) : (n + 1));
                lineNumbersContainer.children[n].textContent = formatLineNumber(value);
            }
        }
        linesContainer.style.transform = 'translateY(-' + textarea.scrollTop + 'px)';
        if (lineNumbersContainer) {
            lineNumbersContainer.style.transform = 'translateY(-' + textarea.scrollTop + 'px)';
        }
    }
    
    function syncScroll() {
        linesContainer.style.transform = 'translateY(-' + textarea.scrollTop + 'px)';
        if (lineNumbersContainer) {
            lineNumbersContainer.style.transform = 'translateY(-' + textarea.scrollTop + 'px)';
        }
    }
    
    textarea.addEventListener('scroll', syncScroll);
    textarea.addEventListener('keydown', function(e) {
        if (e.key !== 'Tab') return;
        e.preventDefault();

        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var indent = '    ';

        if (start === end) {
            textarea.value = value.slice(0, start) + indent + value.slice(end);
            textarea.selectionStart = textarea.selectionEnd = start + indent.length;
        } else {
            var selected = value.slice(start, end);
            var indented = selected.split('\n').map(function(line) {
                return indent + line;
            }).join('\n');
            textarea.value = value.slice(0, start) + indented + value.slice(end);
            textarea.selectionStart = start;
            textarea.selectionEnd = start + indented.length;
        }

        updateCounts();
        updateLines();
        startEssayTimer();
        scheduleAutosave();
    });
    textarea.addEventListener('input', function() {
        if (lastStructuredText && textarea.value.trim() !== lastStructuredText) {
            if (textStructureInput) textStructureInput.value = '';
            if (layoutInput) layoutInput.value = '';
            lastStructuredText = '';
            ocrLineNumbers = [];
        }
        updateLines();
        startEssayTimer();
        updateCounts();
        scheduleAutosave();
    });
    
    // Init lines
    updateLines();
    window.addEventListener('resize', function() {
        lineHeight = window.innerWidth >= 640 ? 32 : 28;
        updateLines();
    });

    textarea.addEventListener('keydown', startEssayTimer);
    textarea.addEventListener('focus', function() {
        this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    
    // Init
    var initialStructure = safeParseJson(textStructureInput ? textStructureInput.value : '');
    var initialLayout = safeParseJson(layoutInput ? layoutInput.value : '');
    normalizeIndentSpacesFromLayout(initialLayout || {});
    if (Array.isArray(initialStructure) && initialStructure.length) {
        ocrLineNumbers = extractLineNumbersFromStructure(initialStructure);
        if (!textarea.value.trim()) {
            textarea.value = structureToText(initialStructure, paragraphIndentSpaces);
            lastStructuredText = textarea.value.trim();
        }
    }
    updateCounts();
    setAutosaveStatus('Autosave ativo', 'idle');

    // Submit
    document.getElementById('writeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var isSubmit = e.submitter && e.submitter.value === '1';
        var fd = new FormData(this);
        fd.append('content_text', textarea.value);
        fd.append('submit', isSubmit ? '1' : '0');
        msg.classList.add('hidden');
        
        fetch(baseUrl + '/jornada-redacao/' + proposalId + '/salvar-texto', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    msg.innerHTML = '<span class="text-green-700 text-sm">✓ ' + d.message + '</span>';
                    msg.className = 'p-3 rounded-lg bg-green-50 border border-green-200';
                    msg.classList.remove('hidden');
                    if (isSubmit && d.submission_id) {
                        setTimeout(function() { window.location.href = baseUrl + '/jornada-redacao/correcao/' + d.submission_id; }, 1200);
                    }
                } else {
                    msg.innerHTML = '<span class="text-red-700 text-sm">✕ ' + (d.error || 'Erro') + '</span>';
                    msg.className = 'p-3 rounded-lg bg-red-50 border border-red-200';
                    msg.classList.remove('hidden');
                }
            })
            .catch(function() {
                msg.innerHTML = '<span class="text-red-700 text-sm">✕ Erro de conexão.</span>';
                msg.className = 'p-3 rounded-lg bg-red-50 border border-red-200';
                msg.classList.remove('hidden');
            });
    });

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            saveDraftSilently();
        }
    });

    window.addEventListener('beforeunload', function() {
        saveDraftSilently();
        if (uploadedAnexoObjectUrl) {
            URL.revokeObjectURL(uploadedAnexoObjectUrl);
            uploadedAnexoObjectUrl = '';
        }
    });

    // Modo foto / abas
    var painelFoto = document.getElementById('painelFotoRedacao');
    var painelTexto = document.getElementById('painelTextoRedacao');
    var tabTexto = document.getElementById('tabModoTexto');
    var tabFoto = document.getElementById('tabModoFoto');
    function showPainelFoto() {
        if (painelFoto) painelFoto.classList.remove('hidden');
        if (painelTexto) painelTexto.classList.add('hidden');
        if (tabFoto) { tabFoto.classList.add('bg-purple-600', 'text-white'); tabFoto.classList.remove('bg-gray-100', 'text-gray-700'); }
        if (tabTexto) { tabTexto.classList.remove('bg-purple-600', 'text-white'); tabTexto.classList.add('bg-gray-100', 'text-gray-700'); }
    }
    function showPainelTexto() {
        if (painelFoto) painelFoto.classList.add('hidden');
        if (painelTexto) painelTexto.classList.remove('hidden');
        if (tabTexto) { tabTexto.classList.add('bg-purple-600', 'text-white'); tabTexto.classList.remove('bg-gray-100', 'text-gray-700'); }
        if (tabFoto) { tabFoto.classList.remove('bg-purple-600', 'text-white'); tabFoto.classList.add('bg-gray-100', 'text-gray-700'); }
    }
    if (submissionMode === 'foto') {
        showPainelFoto();
    }
    if (tabFoto) tabFoto.addEventListener('click', showPainelFoto);
    if (tabTexto) tabTexto.addEventListener('click', showPainelTexto);

    var imagemFotoInput = document.getElementById('imagemRedacaoFoto');
    var previewFoto = document.getElementById('previewFotoRedacao');
    var previewFotoImg = document.getElementById('previewFotoImg');
    var base64FotoInput = document.getElementById('content_image_base64_foto');
    var mimeFotoInput = document.getElementById('content_image_mime_foto');
    if (imagemFotoInput) {
        imagemFotoInput.addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            var file = this.files[0];
            if (file.size > 10 * 1024 * 1024) { alert('Arquivo muito grande! Máximo 10MB.'); return; }
            document.getElementById('nomeArquivoFoto').textContent = file.name;
            var reader = new FileReader();
            reader.onload = function() {
                var dataUrl = String(reader.result || '');
                var mimeMatch = dataUrl.match(/^data:(image\/[a-zA-Z0-9.+-]+);base64,/);
                if (base64FotoInput) base64FotoInput.value = dataUrl.replace(/^data:image\/[a-zA-Z0-9.+-]+;base64,/, '');
                if (mimeFotoInput) mimeFotoInput.value = mimeMatch ? mimeMatch[1] : 'image/jpeg';
                if (previewFotoImg) previewFotoImg.src = dataUrl;
                if (previewFoto) previewFoto.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });
    }
    var photoOnlyForm = document.getElementById('photoOnlyForm');
    if (photoOnlyForm) {
        photoOnlyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var isSubmit = e.submitter && e.submitter.value === '1';
            if (isSubmit && (!base64FotoInput || !base64FotoInput.value)) {
                alert('Selecione a foto da redação antes de enviar.');
                return;
            }
            var fd = new FormData(this);
            fd.append('submit', isSubmit ? '1' : '0');
            var msgFoto = document.getElementById('msgFoto');
            fetch(baseUrl + '/jornada-redacao/' + proposalId + '/salvar-texto', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (!msgFoto) return;
                    if (d.success) {
                        msgFoto.innerHTML = '<span class="text-green-700 text-sm">✓ ' + d.message + '</span>';
                        msgFoto.className = 'p-3 rounded-lg bg-green-50 border border-green-200';
                        msgFoto.classList.remove('hidden');
                        if (isSubmit && d.submission_id) {
                            setTimeout(function() { window.location.href = baseUrl + '/jornada-redacao/correcao/' + d.submission_id; }, 1200);
                        }
                    } else {
                        msgFoto.innerHTML = '<span class="text-red-700 text-sm">✕ ' + (d.error || 'Erro') + '</span>';
                        msgFoto.className = 'p-3 rounded-lg bg-red-50 border border-red-200';
                        msgFoto.classList.remove('hidden');
                    }
                });
        });
    }
})();
</script>
