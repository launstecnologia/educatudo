<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Gerador de Slides com IA 📘</h1>
            <p class="text-gray-600 mt-2">Crie apresentações profissionais automaticamente usando a Gamma API</p>
        </div>
        <div>
            <a href="<?= URL ?>/professor/meus-slides" 
               class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Meus Slides
            </a>
        </div>
    </div>
</div>

<!-- Formulário de Geração -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-bold mb-4">Gerar Slide da Aula</h2>

    <form id="formGerarSlides" class="space-y-6">
        <div>
            <label for="conteudo" class="block mb-2 font-semibold text-gray-700">Conteúdo da Aula</label>
            <textarea 
                id="conteudo" 
                name="conteudo"
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" 
                rows="8"
                placeholder="Digite o conteúdo da aula que deseja transformar em slides. Você pode incluir títulos, objetivos, tópicos, explicações, exemplos, etc."
                required
            ></textarea>
            <p class="text-sm text-gray-500 mt-1">Descreva o conteúdo que será transformado em slides</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="slides" class="block mb-2 font-semibold text-gray-700">Número de Slides</label>
                <input 
                    type="number" 
                    id="slides" 
                    name="slides"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" 
                    min="3" 
                    max="30" 
                    value="8"
                    required
                />
                <p class="text-sm text-gray-500 mt-1">Entre 3 e 30 slides</p>
            </div>

            <div>
                <label for="tema" class="block mb-2 font-semibold text-gray-700">Modelo (Tema Gamma)</label>
                <select 
                    id="tema" 
                    name="tema"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                >
                    <option value="">Padrão (Auto)</option>
                    <option value="clean">Clean</option>
                    <option value="modern">Modern</option>
                    <option value="simple">Simple</option>
                    <option value="dark">Dark Mode</option>
                </select>
                <p class="text-sm text-gray-500 mt-1">Escolha o estilo visual da apresentação</p>
            </div>

            <div>
                <label for="nivelDetalhamento" class="block mb-2 font-semibold text-gray-700">Conteúdo de Texto</label>
                <select 
                    id="nivelDetalhamento" 
                    name="nivelDetalhamento"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                >
                    <option value="brief">Mínimo</option>
                    <option value="medium" selected>Conciso</option>
                    <option value="detailed">Detalhado</option>
                    <option value="extensive">Extenso</option>
                </select>
                <p class="text-sm text-gray-500 mt-1">Nível de detalhamento do conteúdo</p>
            </div>
        </div>

        <!-- Opções de Imagem -->
        <div>
            <label for="estiloImagem" class="block mb-2 font-semibold text-gray-700">Estilo de Arte da Imagem</label>
            <select 
                id="estiloImagem" 
                name="estiloImagem"
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
                <option value="">Automático (Auto)</option>
                <option value="illustration">Ilustração</option>
                <option value="photo">Foto</option>
                <option value="abstract">Abstrato</option>
                <option value="3d">3D</option>
                <option value="line-art">Arte Linear</option>
                <option value="custom">Personalizado</option>
            </select>
            <p class="text-sm text-gray-500 mt-1">Estilo visual das imagens geradas</p>
        </div>

        <button 
            type="submit" 
            id="btnGerar"
            class="w-full bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-700 transition-colors duration-200 flex items-center justify-center"
        >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            <span id="btnText">Gerar Slide</span>
        </button>
    </form>
</div>

<!-- Resultado -->
<div id="resultado" class="bg-white rounded-xl shadow-lg p-6 hidden">
    <h2 class="text-xl font-bold mb-4">Apresentação Gerada</h2>
    <div id="resultadoContent"></div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl p-8 max-w-md w-full mx-4">
        <div class="flex flex-col items-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600 mb-4"></div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Gerando Apresentação...</h3>
            <p class="text-sm text-gray-600 text-center">Isso pode levar alguns segundos. Por favor, aguarde.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formGerarSlides');
    const btnGerar = document.getElementById('btnGerar');
    const btnText = document.getElementById('btnText');
    const resultado = document.getElementById('resultado');
    const resultadoContent = document.getElementById('resultadoContent');
    const loadingOverlay = document.getElementById('loadingOverlay');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const conteudo = document.getElementById('conteudo').value.trim();
        const slides = parseInt(document.getElementById('slides').value);
        const tema = document.getElementById('tema').value;
        const nivelDetalhamento = document.getElementById('nivelDetalhamento').value;
        const estiloImagem = document.getElementById('estiloImagem').value;
        const salvarSlide = true; // Sempre salvar automaticamente

        // Validação
        if (!conteudo) {
            alert('Por favor, preencha o conteúdo da aula.');
            return;
        }

        if (slides < 3 || slides > 30) {
            alert('O número de slides deve estar entre 3 e 30.');
            return;
        }

        // Desabilitar botão e mostrar loading
        btnGerar.disabled = true;
        btnText.textContent = 'Gerando...';
        loadingOverlay.classList.remove('hidden');
        resultado.classList.add('hidden');

        try {
            const response = await fetch('<?= URL ?>/professor/gerar-slides/api', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    conteudo: conteudo,
                    slides: slides,
                    tema: tema,
                    nivelDetalhamento: nivelDetalhamento,
                    estiloImagem: estiloImagem,
                    salvarSlide: salvarSlide
                })
            });

            const data = await response.json();

            if (data.status === 'ok' && data.url) {
                // Sucesso
                let cardsHtml = '';
                
                // Se houver cards, exibir antes da URL
                if (data.cards && Array.isArray(data.cards) && data.cards.length > 0) {
                    cardsHtml = `
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Preview dos Cards Gerados</h3>
                            <div class="space-y-4 max-h-96 overflow-y-auto">
                                ${data.cards.map((card, index) => {
                                    const titulo = card.title || card.titulo || card.heading || `Card ${index + 1}`;
                                    const conteudo = card.content || card.conteudo || card.text || card.body || '';
                                    const imagem = card.image || card.imagem || card.imageUrl || '';
                                    
                                    return `
                                        <div class="border border-gray-300 rounded-lg p-4 bg-white shadow-sm hover:shadow-md transition-shadow">
                                            <div class="flex items-start">
                                                <span class="flex-shrink-0 w-8 h-8 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center font-semibold text-sm mr-3">
                                                    ${index + 1}
                                                </span>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="text-base font-semibold text-gray-900 mb-2">${escapeHtml(titulo)}</h4>
                                                    ${imagem ? `
                                                        <div class="mb-2">
                                                            <img src="${escapeHtml(imagem)}" alt="${escapeHtml(titulo)}" class="w-full rounded-lg max-h-48 object-cover">
                                                        </div>
                                                    ` : ''}
                                                    ${conteudo ? `
                                                        <div class="text-sm text-gray-700 whitespace-pre-wrap">${escapeHtml(conteudo)}</div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    `;
                }
                
                resultadoContent.innerHTML = `
                    <div class="mb-4">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-green-800 font-semibold">Slide Gerado com Sucesso!</p>
                            </div>
                        </div>
                        
                        ${cardsHtml}
                        
                        <div class="flex flex-col sm:flex-row gap-3 mb-4">
                            <a 
                                href="${data.url}" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                class="flex-1 bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-700 transition-colors text-center"
                            >
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                Abrir no Gamma
                            </a>
                            <button 
                                onclick="copiarLink('${data.url}')"
                                class="flex-1 bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-700 transition-colors"
                            >
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                Copiar Link
                            </button>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-8 bg-gray-50 text-center">
                            <div class="max-w-md mx-auto">
                                <svg class="w-16 h-16 text-purple-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Apresentação Pronta!</h3>
                                <p class="text-gray-600 mb-4">Sua apresentação foi gerada com sucesso. Use os botões acima para abrir ou copiar o link.</p>
                                <p class="text-xs text-gray-500">Nota: O Gamma não permite preview em iframe por questões de segurança. Clique em "Abrir no Gamma" para visualizar a apresentação completa.</p>
                            </div>
                        </div>
                    </div>
                `;
                resultado.classList.remove('hidden');
            } else {
                // Erro
                let errorHtml = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center mb-2">
                            <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-red-800 font-semibold">Erro ao gerar apresentação</p>
                        </div>
                        <p class="text-red-600 mt-2 font-medium">${data.error || 'Ocorreu um erro inesperado. Tente novamente.'}</p>
                `;
                
                // Não exibir detalhes técnicos
                
                errorHtml += `</div>`;
                
                resultadoContent.innerHTML = errorHtml;
                resultado.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Erro:', error);
            resultadoContent.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-red-800 font-semibold">Erro de conexão</p>
                    </div>
                    <p class="text-red-600 mt-2">Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.</p>
                </div>
            `;
            resultado.classList.remove('hidden');
        } finally {
            // Reabilitar botão e esconder loading
            btnGerar.disabled = false;
            btnText.textContent = 'Gerar Slide';
            loadingOverlay.classList.add('hidden');
        }
    });
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function copiarLink(url) {
    navigator.clipboard.writeText(url).then(function() {
        alert('Link copiado para a área de transferência!');
    }, function() {
        // Fallback para navegadores mais antigos
        const textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Link copiado para a área de transferência!');
    });
}
</script>

<style>
#loadingOverlay {
    backdrop-filter: blur(4px);
}

#resultado {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

