<?php
/**
 * Helper para processar markdown de imagens
 */

class MarkdownHelper
{
    /**
     * Converte markdown de imagem para tag HTML img
     * Ex: ![](url) -> <img src="url" alt="" />
     */
    public static function convertImageMarkdown($text)
    {
        // Pattern para encontrar ![](url) ou ![alt](url)
        $pattern = '/!\[([^\]]*)\]\(([^)]+)\)/';
        
        // Substituir por tag <img>
        $replacement = '<img src="$2" alt="$1" class="max-w-full h-auto mx-auto rounded-lg shadow-md border border-gray-200" style="max-height: 400px;" />';
        
        return preg_replace($pattern, $replacement, $text);
    }
    
    /**
     * Processa todo o markdown básico (texto, negrito, itálico, imagens)
     */
    public static function processMarkdown($text)
    {
        if (empty($text)) {
            return '';
        }
        
        // Escapar HTML para segurança (exceto o que vamos processar)
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // Converter quebras de linha
        $text = nl2br($text);
        
        // Converter markdown de imagem (depois do escape, mas antes de converter negrito)
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function($matches) {
            $alt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $src = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return '<img src="' . $src . '" alt="' . $alt . '" class="max-w-full h-auto mx-auto rounded-lg shadow-md border border-gray-200" style="max-height: 400px;" />';
        }, $text);
        
        // Converter negrito **texto** ou __texto__ (já escapado)
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        
        // Converter itálico *texto* ou _texto_ (já escapado)
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);
        
        return $text;
    }
}

