<?php

/**
 * URL do CSS Tailwind compilado (substitui o Play CDN).
 */
class EstilosPlataforma
{
    public static function caminhoArquivo(): string
    {
        $raiz = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        return $raiz . '/public/static/css/tailwind.css';
    }

    public static function href(): string
    {
        $base = defined('URL') ? rtrim((string) URL, '/') : '';
        $arquivo = self::caminhoArquivo();
        $ver = is_file($arquivo) ? (string) filemtime($arquivo) : '1';
        return $base . '/static/css/tailwind.css?v=' . rawurlencode($ver);
    }

    public static function tagLink(): string
    {
        return '<link rel="stylesheet" href="' . htmlspecialchars(self::href(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
