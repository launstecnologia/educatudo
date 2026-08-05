<!-- Header Section -->
<div class="mb-6">
    <a href="<?= URL ?>/livros" class="inline-flex items-center text-green-600 hover:text-green-700 mb-4">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Voltar para busca
    </a>
    <h1 class="text-3xl font-bold text-gray-900">Detalhes do Livro</h1>
</div>

<?php
$volumeInfo = $livro['volumeInfo'] ?? [];
$thumbnail = $volumeInfo['imageLinks']['large'] ?? $volumeInfo['imageLinks']['thumbnail'] ?? $volumeInfo['imageLinks']['smallThumbnail'] ?? '';
$title = $volumeInfo['title'] ?? 'Sem título';
$subtitle = $volumeInfo['subtitle'] ?? '';
$authors = $volumeInfo['authors'] ?? [];
$publishedDate = $volumeInfo['publishedDate'] ?? '';
$description = $volumeInfo['description'] ?? '';
$publisher = $volumeInfo['publisher'] ?? '';
$pageCount = $volumeInfo['pageCount'] ?? '';
$categories = $volumeInfo['categories'] ?? [];
$language = $volumeInfo['language'] ?? '';
$previewLink = $volumeInfo['previewLink'] ?? '';
$infoLink = $volumeInfo['infoLink'] ?? '';
$accessInfo = $livro['accessInfo'] ?? [];
$webReaderLink = $accessInfo['webReaderLink'] ?? '';
$viewability = $accessInfo['viewability'] ?? '';
$isbn = '';
if (isset($volumeInfo['industryIdentifiers'])) {
    foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
        if ($identifier['type'] === 'ISBN_13' || $identifier['type'] === 'ISBN_10') {
            $isbn = $identifier['identifier'];
            break;
        }
    }
}
?>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="md:flex">
        <!-- Capa do Livro -->
        <div class="md:w-1/3 p-6 bg-gray-50 flex items-center justify-center">
            <?php if ($thumbnail): ?>
                <img src="<?= htmlspecialchars(str_replace('http://', 'https://', $thumbnail)) ?>" 
                     alt="<?= htmlspecialchars($title) ?>" 
                     class="max-w-full h-auto rounded-lg shadow-md">
            <?php else: ?>
                <div class="w-full aspect-[3/4] bg-gray-200 rounded-lg flex items-center justify-center">
                    <svg class="w-32 h-32 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Informações do Livro -->
        <div class="md:w-2/3 p-6">
            <h2 class="text-3xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($title) ?></h2>
            
            <?php if ($subtitle): ?>
                <p class="text-xl text-gray-600 mb-4"><?= htmlspecialchars($subtitle) ?></p>
            <?php endif; ?>
            
            <?php if (!empty($authors)): ?>
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-1">Autor(es):</p>
                    <p class="text-lg text-gray-900"><?= htmlspecialchars(implode(', ', $authors)) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <?php if ($publishedDate): ?>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Data de Publicação:</p>
                        <p class="text-gray-900"><?= htmlspecialchars($publishedDate) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($publisher): ?>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Editora:</p>
                        <p class="text-gray-900"><?= htmlspecialchars($publisher) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($pageCount): ?>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Páginas:</p>
                        <p class="text-gray-900"><?= number_format($pageCount, 0, ',', '.') ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($language): ?>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Idioma:</p>
                        <p class="text-gray-900"><?= strtoupper($language) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($isbn): ?>
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-1">ISBN:</p>
                    <p class="text-gray-900"><?= htmlspecialchars($isbn) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($categories)): ?>
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-2">Categorias:</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($categories as $category): ?>
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">
                                <?= htmlspecialchars($category) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Botões de Ação -->
            <div class="flex flex-wrap gap-3 mt-6">
                <?php if ($previewLink): ?>
                    <a href="<?= htmlspecialchars($previewLink) ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        Abrir no Google Books
                    </a>
                <?php endif; ?>
                
                <?php if ($infoLink): ?>
                    <a href="<?= htmlspecialchars($infoLink) ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium transition-colors flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Mais Informações
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Descrição -->
    <?php if ($description): ?>
        <div class="p-6 border-t border-gray-200">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Sinopse</h3>
            <div class="text-gray-700 leading-relaxed prose max-w-none">
                <?= nl2br(htmlspecialchars($description)) ?>
            </div>
        </div>
    <?php endif; ?>
</div>

