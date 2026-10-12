<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">📚 Educa Livros</h1>
            <p class="text-gray-600 mt-2">Explore milhares de livros do Google Books</p>
        </div>
    </div>
</div>

<!-- Barra de Busca -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <form id="searchForm" class="flex space-x-4">
        <div class="flex-1">
            <input 
                type="text" 
                id="searchInput" 
                name="q" 
                value="<?= htmlspecialchars($query ?? '') ?>" 
                placeholder="Buscar livros por título, autor, assunto..." 
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                autocomplete="off">
        </div>
        <button 
            type="submit" 
            class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            Buscar
        </button>
    </form>
</div>

<!-- Resultados -->
<div id="resultsContainer">
    <?php 
    // Debug: verificar se há livros
    $debugInfo = '';
    if (defined('DEBUG') && DEBUG) {
        $debugInfo = '<!-- Debug: Total de livros: ' . count($livros) . ' -->';
    }
    echo $debugInfo;
    ?>
    <?php if (!empty($carregarDestaquesViaAjax)): ?>
        <div class="mb-4">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">📚 Livros em Destaque</h2>
            <p class="text-gray-600">Seleção especial de livros nacionais para estudantes do ensino médio</p>
        </div>
        <div id="destaquesLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php for ($i = 0; $i < 8; $i++): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="aspect-[3/4] bg-gray-200 flex items-center justify-center">
                        <div class="w-8 h-8 border-4 border-gray-300 border-t-green-600 rounded-full animate-spin"></div>
                    </div>
                    <div class="p-4 space-y-2 animate-pulse">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    <?php elseif (!empty($livros)): ?>
        <?php if (!empty($query)): ?>
            <div class="mb-4">
                <p class="text-gray-600">
                    Encontrados <strong><?= number_format($totalItems, 0, ',', '.') ?></strong> resultado(s) para "<strong><?= htmlspecialchars($query) ?></strong>"
                </p>
            </div>
        <?php else: ?>
            <div class="mb-4">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">📚 Livros em Destaque</h2>
                <p class="text-gray-600">Seleção especial de livros nacionais para estudantes do ensino médio</p>
            </div>
        <?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($livros as $livro): ?>
                    <?php
                    $volumeInfo = $livro['volumeInfo'] ?? [];
                    $thumbnail = $volumeInfo['imageLinks']['thumbnail'] ?? $volumeInfo['imageLinks']['smallThumbnail'] ?? '';
                    $title = $volumeInfo['title'] ?? 'Sem título';
                    $authors = $volumeInfo['authors'] ?? [];
                    $publishedDate = $volumeInfo['publishedDate'] ?? '';
                    $description = $volumeInfo['description'] ?? '';
                    $previewLink = $volumeInfo['previewLink'] ?? '';
                    $infoLink = $volumeInfo['infoLink'] ?? '';
                    $bookId = $livro['id'] ?? '';
                    ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                        <a href="<?= URL ?>/livros/<?= htmlspecialchars($bookId) ?>">
                            <div class="aspect-[3/4] bg-gray-200 relative overflow-hidden book-card-cover">
                                <?php if ($thumbnail): ?>
                                    <div class="absolute inset-0 flex items-center justify-center book-card-spinner">
                                        <div class="w-8 h-8 border-4 border-gray-300 border-t-green-600 rounded-full animate-spin"></div>
                                    </div>
                                    <img src="<?= htmlspecialchars(str_replace('http://', 'https://', $thumbnail)) ?>"
                                         alt="<?= htmlspecialchars($title) ?>"
                                         loading="lazy"
                                         onload="bookCardImgLoaded(this)"
                                         onerror="bookCardImgFailed(this)"
                                         style="opacity:0; transition: opacity .2s;"
                                         class="absolute inset-0 w-full h-full object-cover">
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2" title="<?= htmlspecialchars($title) ?>">
                                    <?= htmlspecialchars($title) ?>
                                </h3>
                                <?php if (!empty($authors)): ?>
                                    <p class="text-sm text-gray-600 mb-2">
                                        Por <?= htmlspecialchars(implode(', ', array_slice($authors, 0, 2))) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($publishedDate): ?>
                                    <p class="text-xs text-gray-500">
                                        <?= htmlspecialchars($publishedDate) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Paginação -->
            <?php if ($totalItems > count($livros)): ?>
                <div class="mt-6 flex justify-center">
                    <button id="loadMoreBtn" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                        Carregar Mais
                    </button>
                </div>
            <?php endif; ?>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <p class="text-gray-600 text-lg font-medium">Nenhum livro encontrado</p>
            <p class="text-sm text-gray-400 mt-2">Tente buscar com outros termos</p>
            <?php if (!empty($erro)): ?>
                <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg text-left text-sm max-w-2xl mx-auto">
                    <p class="text-red-800 font-semibold mb-2">Erro ao buscar livros:</p>
                    <p class="text-red-600"><?= htmlspecialchars($erro) ?></p>
                </div>
            <?php endif; ?>
            <?php if (defined('DEBUG') && DEBUG): ?>
                <div class="mt-4 p-4 bg-gray-100 rounded text-left text-xs max-w-2xl mx-auto">
                    <strong>Debug Info:</strong><br>
                    Livros no array: <?= count($livros ?? []) ?><br>
                    Total items: <?= $totalItems ?? 0 ?><br>
                    Query: "<?= htmlspecialchars($query ?? '') ?>"<br>
                    <?php if (!empty($erro)): ?>
                        <span class="text-red-600">Erro: <?= htmlspecialchars($erro) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
let currentStartIndex = <?= count($livros ?? []) ?>;
let currentQuery = '<?= htmlspecialchars($query ?? '') ?>';
let isLoading = false;

// Capa do card: spinner enquanto carrega; se a imagem falhar (URL quebrada,
// bloqueio externo etc.), mostra um ícone fixo em vez de deixar a célula
// vazia/cinza sem nenhuma indicação.
function bookCardFallbackHtml() {
    return `<div class="absolute inset-0 flex items-center justify-center">
        <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
        </svg>
    </div>`;
}
function bookCardImgLoaded(img) {
    const spinner = img.previousElementSibling;
    if (spinner && spinner.classList.contains('book-card-spinner')) {
        spinner.remove();
    }
    img.style.opacity = 1;
}
function bookCardImgFailed(img) {
    const spinner = img.previousElementSibling;
    if (spinner && spinner.classList.contains('book-card-spinner')) {
        spinner.remove();
    }
    const cover = img.closest('.book-card-cover');
    img.remove();
    if (cover) {
        cover.insertAdjacentHTML('beforeend', bookCardFallbackHtml());
    }
}

<?php if (!empty($carregarDestaquesViaAjax)): ?>
fetch('<?= URL ?>/livros/destaques', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (result) {
        const container = document.getElementById('destaquesLoading');
        if (!container) return;
        if (result.success && result.items && result.items.length > 0) {
            container.innerHTML = result.items.map(criarCardLivro).join('');
        } else {
            container.outerHTML = '<div class="bg-white rounded-xl shadow-lg p-12 text-center"><p class="text-gray-600 text-lg font-medium">Não foi possível carregar os destaques agora</p><p class="text-sm text-gray-400 mt-2">Tente buscar por um assunto na barra acima</p></div>';
        }
    })
    .catch(function () {
        const container = document.getElementById('destaquesLoading');
        if (container) {
            container.outerHTML = '<div class="bg-white rounded-xl shadow-lg p-12 text-center"><p class="text-gray-600 text-lg font-medium">Erro ao carregar os destaques</p></div>';
        }
    });
<?php endif; ?>

document.getElementById('searchForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const query = document.getElementById('searchInput').value.trim();
    if (query) {
        window.location.href = '<?= URL ?>/livros?q=' + encodeURIComponent(query);
    }
});

document.getElementById('loadMoreBtn')?.addEventListener('click', async function() {
    if (isLoading || !currentQuery) return;
    
    isLoading = true;
    this.disabled = true;
    this.textContent = 'Carregando...';
    
    try {
        const response = await fetch(`<?= URL ?>/livros/buscar?q=${encodeURIComponent(currentQuery)}&startIndex=${currentStartIndex}&maxResults=20`);
        const result = await response.json();
        
        if (result.success && result.items && result.items.length > 0) {
            const container = document.querySelector('.grid');
            result.items.forEach(livro => {
                const livroHtml = criarCardLivro(livro);
                container.insertAdjacentHTML('beforeend', livroHtml);
            });
            
            currentStartIndex += result.items.length;
            
            if (currentStartIndex >= result.totalItems) {
                this.remove();
            }
        } else {
            this.remove();
        }
    } catch (error) {
        console.error('Erro ao carregar mais livros:', error);
        alert('Erro ao carregar mais livros');
    } finally {
        isLoading = false;
        this.disabled = false;
        this.textContent = 'Carregar Mais';
    }
});

function criarCardLivro(livro) {
    const volumeInfo = livro.volumeInfo || {};
    const thumbnail = volumeInfo.imageLinks?.thumbnail || volumeInfo.imageLinks?.smallThumbnail || '';
    const title = volumeInfo.title || 'Sem título';
    const authors = volumeInfo.authors || [];
    const publishedDate = volumeInfo.publishedDate || '';
    const bookId = livro.id || '';
    
    const thumbnailUrl = thumbnail ? thumbnail.replace('http://', 'https://') : '';
    const thumbnailHtml = thumbnailUrl
        ? `<div class="absolute inset-0 flex items-center justify-center book-card-spinner">
            <div class="w-8 h-8 border-4 border-gray-300 border-t-green-600 rounded-full animate-spin"></div>
          </div>
          <img src="${escapeHtml(thumbnailUrl)}" alt="${escapeHtml(title)}" loading="lazy"
               onload="bookCardImgLoaded(this)"
               onerror="bookCardImgFailed(this)"
               style="opacity:0; transition: opacity .2s;"
               class="absolute inset-0 w-full h-full object-cover">`
        : bookCardFallbackHtml();

    return `
        <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
            <a href="<?= URL ?>/livros/${escapeHtml(bookId)}">
                <div class="aspect-[3/4] bg-gray-200 relative overflow-hidden book-card-cover">
                    ${thumbnailHtml}
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2" title="${escapeHtml(title)}">
                        ${escapeHtml(title)}
                    </h3>
                    ${authors.length > 0 ? `<p class="text-sm text-gray-600 mb-2">Por ${escapeHtml(authors.slice(0, 2).join(', '))}</p>` : ''}
                    ${publishedDate ? `<p class="text-xs text-gray-500">${escapeHtml(publishedDate)}</p>` : ''}
                </div>
            </a>
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

