<?php
/**
 * Chat Formatter - Formatação de mensagens do Chat Tudinha
 * Baseado na lógica do frontend React
 */
class ChatFormatter
{
    /**
     * Detecta se o conteúdo é HTML
     */
    public static function isHtmlContent($text)
    {
        // Verifica se contém tags HTML básicas
        $htmlPatterns = [
            '/<h[1-6][^>]*>/i',           // Headers
            '/<p[^>]*>/i',                // Parágrafos
            '/<ul[^>]*>/i',               // Listas não ordenadas
            '/<ol[^>]*>/i',               // Listas ordenadas
            '/<li[^>]*>/i',               // Itens de lista
            '/<strong[^>]*>/i',            // Negrito
            '/<em[^>]*>/i',               // Itálico
            '/<br[^>]*>/i',               // Quebras de linha
            '/<sub[^>]*>/i',              // Subscrito
            '/<sup[^>]*>/i',              // Sobrescrito
            '/<[^>]+>/'                   // Qualquer tag HTML
        ];
        
        foreach ($htmlPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Normaliza o texto do chat (converte para Markdown quando necessário)
     */
    public static function normalizeChatText($text)
    {
        // 1. Conversão de caracteres especiais
        $text = str_replace('\\nn', "\n\n", $text);
        $text = str_replace('\\n', "\n", $text);
        
        // 2. Conversão para Markdown - Títulos
        // "Como funciona:" → "### Como funciona"
        $text = preg_replace('/^([A-Z][^:\n]+):\s*$/m', '### $1', $text);
        
        // "**Título:**" → "### Título"
        $text = preg_replace('/^\*\*([^*]+):\*\*\s*$/m', '### $1', $text);
        
        // 3. Conversão para Markdown - Listas
        // "- item" ou "• item" → "- item" (já está correto)
        $text = preg_replace('/^•\s+/m', '- ', $text);
        
        // "1. item" → "1. item" (já está correto)
        
        // 4. Limpeza
        // Remove tabs excessivos
        $text = preg_replace('/\t+/', ' ', $text);
        
        // Ajusta espaçamentos entre parágrafos e seções
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Garante quebras de linha após headings
        $text = preg_replace('/(#{1,6}[^\n]+)\n([^#\n])/', "$1\n\n$2", $text);
        
        return trim($text);
    }
    
    /**
     * Processa equações matemáticas LaTeX para formato MathJax
     */
    public static function processMathEquations($text)
    {
        // Valida entrada
        if ($text === null || !is_string($text)) {
            return $text ?? '';
        }
        
        // Converte delimitadores LaTeX para formato MathJax
        // Usa uma abordagem simples e segura
        
        // Primeiro, normaliza escapes múltiplos que podem aparecer
        // Remove escapes excessivos mantendo apenas um
        while (strpos($text, '\\\\[') !== false) {
            $text = str_replace('\\\\[', '\\[', $text);
        }
        while (strpos($text, '\\\\]') !== false) {
            $text = str_replace('\\\\]', '\\]', $text);
        }
        while (strpos($text, '\\\(') !== false) {
            $text = str_replace('\\\(', '\(', $text);
        }
        while (strpos($text, '\\\)') !== false) {
            $text = str_replace('\\\)', '\)', $text);
        }
        
        // Agora, se o texto contém \[ ... \] ou \( ... \), já está no formato correto
        // Apenas processa $$ ... $$ que precisa ser convertido
        $text = preg_replace('/\$\$(.*?)\$\$/s', '\\[\\1\\]', $text);
        
        return $text;
    }
    
    /**
     * Renderiza Markdown para HTML
     */
    public static function renderMarkdown($markdown)
    {
        $html = $markdown;
        
        // Processa equações matemáticas primeiro
        $html = self::processMathEquations($html);
        
        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3 class="text-lg font-semibold text-purple-600 mb-2 mt-4">$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2 class="text-xl font-semibold text-purple-600 mb-3 mt-5">$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1 class="text-2xl font-bold text-purple-600 mb-4 mt-6">$1</h1>', $html);
        
        // Bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong class="font-semibold">$1</strong>', $html);
        
        // Italic
        $html = preg_replace('/\*(.+?)\*/', '<em class="italic">$1</em>', $html);
        
        // Code inline
        $html = preg_replace('/`(.+?)`/', '<code class="bg-gray-100 px-1 py-0.5 rounded text-sm font-mono">$1</code>', $html);
        
        // Code blocks
        $html = preg_replace('/```([^`]+)```/', '<pre class="bg-gray-100 p-4 rounded-lg overflow-x-auto my-4"><code class="text-sm font-mono">$1</code></pre>', $html);
        
        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline">$1</a>', $html);
        
        // Lists
        $html = preg_replace('/^- (.+)$/m', '<li class="ml-4 mb-1">$1</li>', $html);
        $html = preg_replace('/^(\d+)\. (.+)$/m', '<li class="ml-4 mb-1">$2</li>', $html);
        
        // Wrap consecutive list items in ul/ol
        $html = preg_replace('/(<li[^>]*>.*<\/li>(\s*<li[^>]*>.*<\/li>)*)/', '<ul class="list-disc list-inside mb-4">$1</ul>', $html);
        
        // Paragraphs
        $html = preg_replace('/^(?!<[h1-6]|<ul|<ol|<pre|<li)(.+)$/m', '<p class="mb-3 text-sm leading-relaxed break-words">$1</p>', $html);
        
        // Line breaks
        $html = preg_replace('/\n/', '<br>', $html);
        
        return $html;
    }
    
    /**
     * Sanitiza HTML removendo elementos perigosos
     */
    public static function sanitizeHtml($html)
    {
        // Tags permitidas (incluindo sub e sup que vêm do webhook)
        $allowedTags = '<p><br><strong><b><em><i><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><table><thead><tbody><tr><th><td><code><pre><blockquote><sub><sup>';
        
        // Atributos permitidos
        $allowedAttributes = [
            'href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel', 'style'
        ];
        
        // Remove tags não permitidas
        $html = strip_tags($html, $allowedTags);
        
        // Remove atributos não permitidos (exceto os permitidos)
        $allowedAttrsPattern = implode('|', array_map('preg_quote', $allowedAttributes));
        $html = preg_replace('/\s+(?!' . $allowedAttrsPattern . ')[a-zA-Z-]+="[^"]*"/', '', $html);
        
        // Remove iframes completamente
        $html = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $html);
        
        // Permitir style apenas para imagens (para max-width, etc)
        // Primeiro, preservar imagens com style
        $html = preg_replace_callback('/<img([^>]*)>/i', function($matches) {
            $attrs = $matches[1];
            // Manter style em imagens
            return '<img' . $attrs . '>';
        }, $html);
        
        // Depois, remover style de outros elementos
        $html = preg_replace('/(?<!<img[^>]*)\s+style="[^"]*"/', '', $html);
        
        // Remove atributos data-*
        $html = preg_replace('/\s+data-[^=]*="[^"]*"/', '', $html);
        
        // Adiciona target="_blank" e rel="noopener noreferrer" para links externos
        $html = preg_replace('/<a([^>]*)href="([^"]*)"([^>]*)>/', '<a$1href="$2"$3 target="_blank" rel="noopener noreferrer">', $html);
        
        return $html;
    }
    
    /**
     * Formata uma mensagem completa do chat
     */
    public static function formatMessage($text)
    {
        // Processa equações matemáticas primeiro (mesmo em HTML)
        $text = self::processMathEquations($text);
        
        // Se for HTML, sanitiza e retorna
        if (self::isHtmlContent($text)) {
            return self::sanitizeHtml($text);
        }
        
        // Se for texto simples, normaliza para Markdown e renderiza
        $normalized = self::normalizeChatText($text);
        return self::renderMarkdown($normalized);
    }
    
    /**
     * Formata uma mensagem com classes CSS específicas do sistema
     */
    public static function formatMessageWithClasses($text, $isTudinha = true)
    {
        // Processa equações matemáticas primeiro (mesmo em HTML)
        $text = self::processMathEquations($text);
        
        // Se for HTML (contém tags HTML), apenas sanitiza e retorna
        // A Tudinha sempre retorna HTML, então vamos verificar se já é HTML
        if (self::isHtmlContent($text)) {
            // Se já é HTML, apenas sanitizar e retornar
            return self::sanitizeHtml($text);
        }
        
        // Se for texto simples, normaliza para Markdown e renderiza
        $normalized = self::normalizeChatText($text);
        $formatted = self::renderMarkdown($normalized);
        
        if ($isTudinha) {
            // Adiciona classes específicas para mensagens da Tudinha (apenas para Markdown)
            $formatted = str_replace('<p class="mb-3 text-sm leading-relaxed break-words">', 
                '<p class="mb-3 text-sm leading-relaxed break-words text-gray-800">', $formatted);
            
            $formatted = str_replace('<h3 class="text-lg font-semibold text-purple-600 mb-2 mt-4">', 
                '<h3 class="text-lg font-semibold text-purple-600 mb-2 mt-4">', $formatted);
            
            $formatted = str_replace('<h2 class="text-xl font-semibold text-purple-600 mb-3 mt-5">', 
                '<h2 class="text-xl font-semibold text-purple-600 mb-3 mt-5">', $formatted);
            
            $formatted = str_replace('<h1 class="text-2xl font-bold text-purple-600 mb-4 mt-6">', 
                '<h1 class="text-2xl font-bold text-purple-600 mb-4 mt-6">', $formatted);
        }
        
        return $formatted;
    }
}
