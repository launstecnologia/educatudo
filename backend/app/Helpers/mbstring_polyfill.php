<?php

/**
 * Polyfill mínimo de mbstring para ambientes CLI sem a extensão.
 *
 * Alguns servidores têm `mbstring` habilitado no PHP-FPM, mas o binário do PHP
 * CLI usado pelos workers (ex.: cron/process_ai_jobs.php) é outro build SEM a
 * extensão. Nesses casos chamadas como mb_strtolower() disparam o fatal
 * "Call to undefined function mb_*()" e o job (ex.: flashcards) falha.
 *
 * Este arquivo define APENAS as funções usadas pelo projeto e somente quando
 * ausentes. NÃO substitui a extensão nativa; é uma rede de segurança. A
 * cobertura de maiúsculas/minúsculas acentuadas é suficiente para o português
 * (Latin-1 Supplement). O ideal continua sendo habilitar a extensão mbstring
 * no PHP CLI do servidor.
 */

if (!function_exists('mb_internal_encoding')) {
    function mb_internal_encoding($encoding = null)
    {
        return $encoding === null ? 'UTF-8' : true;
    }
}

if (!function_exists('mb_detect_encoding')) {
    function mb_detect_encoding($string, $encodings = null, $strict = false)
    {
        return 'UTF-8';
    }
}

if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding($string, $to_encoding, $from_encoding = null)
    {
        return (string) $string;
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null)
    {
        $string = (string) $string;
        if ($string === '') {
            return 0;
        }
        $n = preg_match_all('/./us', $string);
        return $n === false ? strlen($string) : $n;
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null)
    {
        $string = (string) $string;
        $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return (string) substr($string, (int) $start, $length === null ? null : (int) $length);
        }
        $slice = array_slice($chars, (int) $start, $length === null ? null : (int) $length);
        return implode('', $slice);
    }
}

if (!function_exists('_polyfill_mb_case')) {
    /**
     * Conversão de caixa byte-safe para UTF-8 sem mbstring.
     * strtolower/strtoupper só afetam ASCII (a-z/A-Z) no locale C, então as
     * sequências multibyte ficam intactas e são tratadas pelo mapa de acentos.
     */
    function _polyfill_mb_case(string $string, bool $upper): string
    {
        $lowerToUpper = [
            'à' => 'À', 'á' => 'Á', 'â' => 'Â', 'ã' => 'Ã', 'ä' => 'Ä', 'å' => 'Å',
            'ç' => 'Ç', 'è' => 'È', 'é' => 'É', 'ê' => 'Ê', 'ë' => 'Ë',
            'ì' => 'Ì', 'í' => 'Í', 'î' => 'Î', 'ï' => 'Ï', 'ñ' => 'Ñ',
            'ò' => 'Ò', 'ó' => 'Ó', 'ô' => 'Ô', 'õ' => 'Õ', 'ö' => 'Ö',
            'ù' => 'Ù', 'ú' => 'Ú', 'û' => 'Û', 'ü' => 'Ü', 'ý' => 'Ý',
        ];

        if ($upper) {
            $string = strtoupper($string);
            return strtr($string, $lowerToUpper);
        }

        $string = strtolower($string);
        return strtr($string, array_flip($lowerToUpper));
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null)
    {
        return _polyfill_mb_case((string) $string, false);
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string, $encoding = null)
    {
        return _polyfill_mb_case((string) $string, true);
    }
}
