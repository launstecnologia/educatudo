<?php

namespace App\Core;

/**
 * Caminhos estáveis para Views compartilhadas (layouts, partials, components).
 * Use nas views de módulos/domínio em vez de __DIR__ relativo frágil.
 */
class ViewPaths
{
    /** Raiz de app/ */
    public static function app(): string
    {
        return dirname(__DIR__);
    }

    public static function base(): string
    {
        return self::app() . '/Views';
    }

    public static function modulo(string $chave, string $relative = ''): string
    {
        $path = self::app() . '/Modulos/' . trim(str_replace('\\', '/', $chave), '/');
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        return $relative === '' ? $path : $path . '/' . $relative;
    }

    public static function dominio(string $nome, string $relative = ''): string
    {
        $path = self::app() . '/Dominio/' . trim(str_replace('\\', '/', $nome), '/');
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        return $relative === '' ? $path : $path . '/' . $relative;
    }

    public static function painel(string $perfil, string $relative = ''): string
    {
        $path = self::app() . '/Painel/' . trim(str_replace('\\', '/', $perfil), '/');
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        return $relative === '' ? $path : $path . '/' . $relative;
    }

    public static function file(string $relative): string
    {
        return self::base() . '/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    public static function adminPartials(): string
    {
        return self::base() . '/admin/_partials';
    }

    public static function adminPartial(string $relative): string
    {
        return self::adminPartials() . '/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    public static function component(string $relative): string
    {
        return self::base() . '/components/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    public static function layoutComponent(string $relative): string
    {
        return self::base() . '/layouts/components/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    /**
     * Partial de domínio/feature. Ordem: Views/partials (legado) → boletins → provas.
     */
    public static function partial(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $candidates = [
            self::base() . '/partials/' . $relative,
            self::modulo('boletins', 'Views/partials/' . $relative),
            self::modulo('provas', 'Views/partials/' . $relative),
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        // fallback: caminho legado (mantém comportamento antigo se arquivo ainda não existir)
        return $candidates[0];
    }

    public static function helpers(): string
    {
        return dirname(__DIR__) . '/Helpers';
    }

    public static function helper(string $relative): string
    {
        return self::helpers() . '/' . ltrim(str_replace('\\', '/', $relative), '/');
    }
}
