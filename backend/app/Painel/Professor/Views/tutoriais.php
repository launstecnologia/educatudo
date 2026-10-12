<!-- Header Section -->
<div class="mb-6 md:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">📹 Tutoriais</h1>
            <p class="text-sm md:text-base text-gray-600 mt-2">Acesse vídeos tutoriais para aprender a usar o sistema</p>
        </div>
    </div>
</div>

<!-- Filtros e Busca -->
<div class="bg-white rounded-xl shadow-lg border border-gray-200 mb-4 md:mb-6">
    <div class="p-4 md:p-6">
        <div class="flex flex-col md:flex-row gap-4">
            <!-- Busca por Título -->
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar por título</label>
                <input 
                    type="text" 
                    id="filtro-titulo" 
                    placeholder="Digite o título do tutorial..."
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
            </div>
            
            <!-- Filtro por Ordem -->
            <div class="w-full md:w-48">
                <label class="block text-sm font-medium text-gray-700 mb-2">Ordenar por</label>
                <select 
                    id="filtro-ordem" 
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="ordem">Ordem (Padrão)</option>
                    <option value="titulo">Título (A-Z)</option>
                    <option value="recente">Mais Recente</option>
                </select>
            </div>
            
            <!-- Botão Limpar -->
            <div class="flex items-end">
                <button 
                    onclick="limparFiltros()" 
                    class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
                >
                    Limpar
                </button>
            </div>
        </div>
        
        <!-- Contador de resultados -->
        <div class="mt-4 pt-4 border-t border-gray-200">
            <p class="text-sm text-gray-600">
                Mostrando <span id="contador-resultados" class="font-semibold text-indigo-600"><?= count($tutoriais) ?></span> tutorial(is)
            </p>
        </div>
    </div>
</div>

<!-- Lista de Tutoriais -->
<div id="tutoriais-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($tutoriais)): ?>
        <div class="col-span-full bg-white rounded-xl shadow-lg p-12 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Nenhum tutorial disponível</h3>
            <p class="text-gray-600">Não há tutoriais cadastrados no momento.</p>
        </div>
    <?php else: ?>
        <?php foreach ($tutoriais as $tutorial): ?>
            <?php
            // Extrair ID do vídeo do YouTube
            $videoId = '';
            $link = $tutorial['link_youtube'];
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $link, $matches)) {
                if (isset($matches[1])) {
                    $videoId = $matches[1];
                }
            }
            // Tentar outro padrão
            if (empty($videoId)) {
                if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $link, $matches)) {
                    $videoId = $matches[1];
                } elseif (preg_match('/youtu\.be\/([^?]+)/', $link, $matches)) {
                    $videoId = $matches[1];
                }
            }
            ?>
            <div class="tutorial-card bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden hover:shadow-xl transition-all duration-300 hover:-translate-y-1" 
                 data-titulo="<?= htmlspecialchars(strtolower($tutorial['titulo'])) ?>"
                 data-ordem="<?= $tutorial['ordem'] ?>"
                 data-id="<?= $tutorial['id'] ?>">
                <!-- Thumbnail do Vídeo -->
                <div class="relative aspect-video bg-gradient-to-br from-purple-500 to-indigo-600">
                    <?php if ($videoId): ?>
                        <iframe 
                            width="100%" 
                            height="100%" 
                            src="https://www.youtube.com/embed/<?= htmlspecialchars($videoId) ?>?rel=0" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen
                            class="w-full h-full">
                        </iframe>
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <a href="<?= htmlspecialchars($link) ?>" target="_blank" 
                               class="inline-flex flex-col items-center px-6 py-4 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors shadow-lg">
                                <svg class="w-12 h-12 mb-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                                <span class="font-semibold text-sm">Assistir no YouTube</span>
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if ($videoId): ?>
                        <div class="absolute top-2 right-2 bg-green-500 text-white px-2 py-1 rounded-full text-xs font-semibold flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Disponível
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Conteúdo do Card -->
                <div class="p-5">
                    <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-2" title="<?= htmlspecialchars($tutorial['titulo']) ?>">
                        <?= htmlspecialchars($tutorial['titulo']) ?>
                    </h3>
                    
                    <?php if (!empty($tutorial['descricao'])): ?>
                        <p class="text-sm text-gray-600 mb-4 line-clamp-3 leading-relaxed">
                            <?= htmlspecialchars($tutorial['descricao']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            <span>Ordem: <?= $tutorial['ordem'] ?></span>
                        </div>
                        <?php if (!$videoId): ?>
                            <a href="<?= htmlspecialchars($link) ?>" target="_blank" 
                               class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-xs font-medium">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                                YouTube
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Mensagem quando não há resultados -->
<div id="sem-resultados" class="hidden bg-white rounded-xl shadow-lg p-12 text-center">
    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
    </svg>
    <h3 class="text-xl font-semibold text-gray-900 mb-2">Nenhum tutorial encontrado</h3>
    <p class="text-gray-600">Tente ajustar os filtros de busca.</p>
</div>

<script>
(function() {
    const filtroTitulo = document.getElementById('filtro-titulo');
    const filtroOrdem = document.getElementById('filtro-ordem');
    const contador = document.getElementById('contador-resultados');
    const semResultados = document.getElementById('sem-resultados');
    const container = document.getElementById('tutoriais-container');
    
    function filtrarTutoriais() {
        const termoBusca = filtroTitulo.value.toLowerCase().trim();
        const ordem = filtroOrdem.value;
        const cards = container.querySelectorAll('.tutorial-card');
        let visiveis = 0;
        
        // Converter NodeList para Array para poder ordenar
        const cardsArray = Array.from(cards);
        
        // Filtrar por título
        cardsArray.forEach(card => {
            const titulo = card.getAttribute('data-titulo') || '';
            const corresponde = titulo.includes(termoBusca);
            
            if (corresponde) {
                card.style.display = '';
                visiveis++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Ordenar cards visíveis
        if (ordem === 'titulo') {
            const cardsVisiveis = cardsArray.filter(c => c.style.display !== 'none');
            cardsVisiveis.sort((a, b) => {
                const tituloA = a.getAttribute('data-titulo') || '';
                const tituloB = b.getAttribute('data-titulo') || '';
                return tituloA.localeCompare(tituloB);
            });
            
            // Reordenar no DOM
            cardsVisiveis.forEach(card => {
                container.appendChild(card);
            });
        } else if (ordem === 'recente') {
            const cardsVisiveis = cardsArray.filter(c => c.style.display !== 'none');
            cardsVisiveis.sort((a, b) => {
                const idA = parseInt(a.getAttribute('data-id') || '0');
                const idB = parseInt(b.getAttribute('data-id') || '0');
                return idB - idA; // Mais recente primeiro (maior ID)
            });
            
            cardsVisiveis.forEach(card => {
                container.appendChild(card);
            });
        } else {
            // Ordem padrão (por ordem)
            const cardsVisiveis = cardsArray.filter(c => c.style.display !== 'none');
            cardsVisiveis.sort((a, b) => {
                const ordemA = parseInt(a.getAttribute('data-ordem') || '0');
                const ordemB = parseInt(b.getAttribute('data-ordem') || '0');
                return ordemA - ordemB;
            });
            
            cardsVisiveis.forEach(card => {
                container.appendChild(card);
            });
        }
        
        // Atualizar contador
        contador.textContent = visiveis;
        
        // Mostrar/ocultar mensagem de sem resultados
        if (visiveis === 0) {
            semResultados.classList.remove('hidden');
            semResultados.classList.add('col-span-full');
            container.style.display = 'grid';
        } else {
            semResultados.classList.add('hidden');
            container.style.display = 'grid';
        }
    }
    
    function limparFiltros() {
        filtroTitulo.value = '';
        filtroOrdem.value = 'ordem';
        filtrarTutoriais();
    }
    
    // Event listeners
    filtroTitulo.addEventListener('input', filtrarTutoriais);
    filtroOrdem.addEventListener('change', filtrarTutoriais);
    
    // Tornar função global
    window.limparFiltros = limparFiltros;
})();
</script>

<style>
/* Estilos para os cards de tutoriais */
.tutorial-card {
    transition: all 0.3s ease;
}

.tutorial-card:hover {
    transform: translateY(-4px);
}

/* Limitar linhas de texto */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Grid responsivo */
@media (max-width: 768px) {
    #tutoriais-container {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    #tutoriais-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1025px) {
    #tutoriais-container {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

