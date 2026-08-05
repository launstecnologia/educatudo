<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Gerenciar Tema de Redação - <?= htmlspecialchars($modulo['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Jornada: <?= htmlspecialchars($jornada['titulo']) ?> • 
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/professor/jornadas/<?= $modulo['jornada_id'] ?>" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Formulário de Tema -->
<div class="bg-white rounded-xl shadow-lg p-6 border border-blue-200 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Configurar Tema da Redação</h3>
    
    <form id="formTemaRedacao" class="space-y-6" enctype="multipart/form-data">
        <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <!-- Tema -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tema da Redação <span class="text-red-500">*</span>
            </label>
            <input type="text" name="tema" required
                   value="<?= htmlspecialchars($redacao_jornada['tema_sugerido'] ?? '') ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Ex: Desafios da educação no Brasil contemporâneo">
            <p class="mt-1 text-sm text-gray-500">Tema que será apresentado aos alunos</p>
        </div>
        
        <!-- Descrição / Repertório (WYSIWYG com colar imagem) -->
        <div>
            <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                <label class="block text-sm font-medium text-gray-700">
                    Repertório / Descrição do Tema
                </label>
                <div class="flex items-center gap-2">
                    <input type="file" id="descricaoInsertImage" accept="image/*" class="hidden">
                    <button type="button" id="descricaoBtnImage" class="text-sm px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Inserir imagem</button>
                    <?php
                    if (!class_exists('CreditosModuleRegistry', false)) {
                        require_once __DIR__ . '/../../../Core/CreditosModuleRegistry.php';
                    }
                    if (\CreditosModuleRegistry::acaoIaDisponivel('redacao_gerar_tema_aluno')):
                    ?>
                    <button type="button" id="btnGerarDescricaoIA" 
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Gerar com IA
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <input type="hidden" name="descricao" id="descricaoTemaHidden" value="">
            <div id="descricaoTemaWrap" class="border border-gray-300 rounded-lg min-h-[140px] bg-white">
                <div id="descricaoTemaEditor" class="descricao-rich-editor min-h-[140px] px-4 py-3 focus:outline-none focus:ring-0" contenteditable="true" data-placeholder="Descreva o tema, repertório e orientações para os alunos... Você pode colar imagens (Ctrl+V)."></div>
            </div>
            <p class="mt-1 text-sm text-gray-500">Texto rico: negrito, listas e imagens. Use "Inserir imagem" ou cole com Ctrl+V.</p>
        </div>
        
        <!-- Imagem do Tema -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Imagem do Tema (Opcional)
            </label>
            <?php if (!empty($redacao_jornada['imagem_tema'])): ?>
                <div class="mb-3">
                    <img src="<?= URL ?>/<?= htmlspecialchars($redacao_jornada['imagem_tema']) ?>" 
                         alt="Imagem do tema" 
                         class="max-w-md rounded-lg border border-gray-300 shadow-sm">
                    <p class="text-sm text-gray-500 mt-2">Imagem atual</p>
                </div>
            <?php endif; ?>
            <input type="file" name="imagem_tema" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <p class="mt-1 text-sm text-gray-500">Formatos aceitos: JPG, PNG, GIF, WEBP (máx. 5MB)</p>
        </div>
        
        <!-- Documento do Tema -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Documento do Tema (Opcional)
            </label>
            <?php if (!empty($redacao_jornada['documento_tema'])): ?>
                <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-300">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <a href="<?= URL ?>/<?= htmlspecialchars($redacao_jornada['documento_tema']) ?>" 
                           target="_blank"
                           class="text-blue-600 hover:text-blue-800 underline">
                            Ver documento atual
                        </a>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Documento atual</p>
                </div>
            <?php endif; ?>
            <input type="file" name="documento_tema" 
                   accept=".pdf,.doc,.docx,.txt,.rtf"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <p class="mt-1 text-sm text-gray-500">Formatos aceitos: PDF, DOC, DOCX, TXT, RTF (máx. 10MB)</p>
        </div>
        
        <!-- Correção Automática por IA -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-gray-700">
                <strong>Correção:</strong> Apenas o professor corrige as redações. O professor pode solicitar a correção da IA como referência para análise, mas a correção final é sempre do professor.
            </p>
            <input type="hidden" name="correcao_ia_automatica" value="0">
        </div>
        
        <div class="flex justify-end space-x-3 pt-4 border-t">
            <button type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                Salvar Tema
            </button>
        </div>
    </form>
</div>

<!-- Informações -->
<?php if ($redacao_jornada): ?>
<div class="bg-white rounded-xl shadow-lg p-6 border border-blue-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações</h3>
    <div class="space-y-2 text-sm">
        <p><span class="font-medium text-gray-700">Criado em:</span> 
           <span class="text-gray-600"><?= date('d/m/Y H:i', strtotime($redacao_jornada['created_at'])) ?></span></p>
        <?php if ($redacao_jornada['updated_at'] && $redacao_jornada['updated_at'] !== $redacao_jornada['created_at']): ?>
            <p><span class="font-medium text-gray-700">Atualizado em:</span> 
               <span class="text-gray-600"><?= date('d/m/Y H:i', strtotime($redacao_jornada['updated_at'])) ?></span></p>
        <?php endif; ?>
        <p><span class="font-medium text-gray-700">Status:</span> 
           <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
               <?= ucfirst($redacao_jornada['status']) ?>
           </span></p>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    const token = document.querySelector('input[name="_token"]') && document.querySelector('input[name="_token"]').value;
    const descricaoInitial = <?= json_encode(!empty($redacao_jornada['descricao_tema']) ? $redacao_jornada['descricao_tema'] : '') ?>;
    const descricaoEditor = document.getElementById('descricaoTemaEditor');
    const descricaoHidden = document.getElementById('descricaoTemaHidden');

    // Inicializar editor WYSIWYG e sync com hidden
    if (descricaoEditor && descricaoHidden) {
        descricaoEditor.innerHTML = descricaoInitial || '';
        function syncDescricao() {
            descricaoHidden.value = descricaoEditor.innerHTML || '';
        }
        descricaoEditor.addEventListener('input', syncDescricao);
        descricaoEditor.addEventListener('blur', syncDescricao);
        syncDescricao();
    }

    // Upload de imagem para o editor (paste ou arquivo)
    function uploadDescricaoImage(file) {
        if (!file || !file.type.startsWith('image/')) return Promise.reject(new Error('Arquivo não é uma imagem'));
        const fd = new FormData();
        fd.append('_token', token);
        fd.append('imagem', file);
        return fetch('<?= URL ?>/professor/redacao-configuravel/upload-imagem', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (!d || !d.success) throw new Error(d && d.error ? d.error : 'Erro no upload');
                return d.image_url || '';
            });
    }
    function insertImageInDescricao(url) {
        if (!descricaoEditor || !url) return;
        descricaoEditor.focus();
        const img = document.createElement('img');
        img.src = url;
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        const sel = window.getSelection();
        let range = sel.rangeCount ? sel.getRangeAt(0) : null;
        if (!range || !descricaoEditor.contains(range.commonAncestorContainer)) {
            range = document.createRange();
            range.selectNodeContents(descricaoEditor);
            range.collapse(true);
        }
        range.deleteContents();
        range.insertNode(img);
        range.setStartAfter(img);
        range.setEndAfter(img);
        sel.removeAllRanges();
        sel.addRange(range);
        if (descricaoHidden) descricaoHidden.value = descricaoEditor.innerHTML;
    }
    document.addEventListener('paste', function(e) {
        if (!e.target || e.target.id !== 'descricaoTemaEditor') return;
        const items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                e.preventDefault();
                const file = items[i].getAsFile();
                if (!file) return;
                uploadDescricaoImage(file).then(url => insertImageInDescricao(url)).catch(err => alert('Erro ao enviar imagem: ' + (err.message || err)));
                return;
            }
        }
    });
    const descricaoInsertImage = document.getElementById('descricaoInsertImage');
    const descricaoBtnImage = document.getElementById('descricaoBtnImage');
    if (descricaoBtnImage && descricaoInsertImage) {
        descricaoBtnImage.addEventListener('click', function() { descricaoInsertImage.click(); });
        descricaoInsertImage.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (!file) return;
            uploadDescricaoImage(file).then(url => insertImageInDescricao(url)).catch(err => alert('Erro ao enviar imagem: ' + (err.message || err)));
            this.value = '';
        });
    }

    // Gerar descrição com IA
    var btnGerarDescricaoIA = document.getElementById('btnGerarDescricaoIA');
    if (btnGerarDescricaoIA) {
    btnGerarDescricaoIA.addEventListener('click', function() {
        const temaInput = document.querySelector('input[name="tema"]');
        const btnGerar = this;
        const tema = temaInput.value.trim();

        if (!tema) {
            alert('Por favor, preencha o tema da redação primeiro.');
            temaInput.focus();
            return;
        }

        btnGerar.disabled = true;
        const textoOriginal = btnGerar.innerHTML;
        btnGerar.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Gerando...';

        const formData = new FormData();
        formData.append('tema', tema);
        formData.append('modulo_id', document.querySelector('input[name="modulo_id"]').value);
        formData.append('_token', token);

        fetch('<?= URL ?>/professor/jornadas/modulos/gerar-descricao-redacao-ia', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.descricao) {
                descricaoEditor.innerHTML = (data.descricao || '').replace(/\n/g, '<br>');
                if (descricaoHidden) descricaoHidden.value = descricaoEditor.innerHTML;
                alert('Descrição gerada com sucesso!');
            } else {
                alert('Erro: ' + (data.error || 'Erro ao gerar descrição'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro de conexão ao gerar descrição');
        })
        .finally(() => {
            btnGerar.disabled = false;
            btnGerar.innerHTML = textoOriginal;
        });
    });
    }
})();

document.getElementById('formTemaRedacao').addEventListener('submit', function(e) {
    e.preventDefault();
    var descricaoEditor = document.getElementById('descricaoTemaEditor');
    var descricaoHidden = document.getElementById('descricaoTemaHidden');
    if (descricaoEditor && descricaoHidden) descricaoHidden.value = descricaoEditor.innerHTML || '';

    console.log('=== INÍCIO DO ENVIO DO FORMULÁRIO ===');

    const formData = new FormData(this);
    
    // Debug: verificar se o arquivo está no FormData
    console.log('FormData criado');
    for (let [key, value] of formData.entries()) {
        if (value instanceof File) {
            console.log(`${key}:`, value.name, value.size, value.type);
        } else {
            console.log(`${key}:`, value);
        }
    }
    
    const url = '<?= URL ?>/professor/jornadas/modulos/salvar-tema-redacao';
    console.log('Enviando para:', url);
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Resposta recebida. Status:', response.status);
        console.log('Content-Type:', response.headers.get('content-type'));
        return response.text().then(text => {
            console.log('Resposta completa:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Erro ao parsear JSON:', e);
                console.error('Texto recebido:', text);
                throw new Error('Resposta não é JSON válido: ' + text.substring(0, 200));
            }
        });
    })
    .then(data => {
        console.log('Dados recebidos:', data);
        if (data.success) {
            alert('Tema salvo com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro completo:', error);
        alert('Erro de conexão: ' + error.message);
    });
});
</script>

