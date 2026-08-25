<?php

namespace App\Modulos\CensoEscolar\Services;

/**
 * Normaliza texto para o leiaute INEP (ISO-8859-1, maiúsculas, sem acento).
 */
class CensoNormalizador
{
    public static function nomeExibicao(string $valor): string
    {
        $valor = trim($valor);
        $valor = preg_replace('/\s*[—–]\s*\d+[ªºo]?\s*(série|serie|ano).*$/iu', '', $valor) ?? $valor;
        $valor = preg_replace('/\s*[—–]\s*(1ª|2ª|3ª|6º|7º|8º|9º).*$/u', '', $valor) ?? $valor;
        return trim($valor);
    }

    public static function nome(string $valor): string
    {
        $valor = self::nomeExibicao($valor);
        $valor = self::ascii(trim($valor));
        $valor = strtoupper($valor);
        $valor = preg_replace('/[^A-Z ]+/', ' ', $valor) ?? $valor;
        $valor = preg_replace('/\s+/', ' ', $valor) ?? $valor;
        return trim($valor);
    }

    public static function alfanumerico(string $valor, int $max = 100): string
    {
        $valor = self::ascii(trim($valor));
        $valor = strtoupper($valor);
        $valor = preg_replace('/[^A-Z0-9 ªº\-\/\.,]/u', ' ', $valor) ?? $valor;
        $valor = preg_replace('/\s+/', ' ', $valor) ?? $valor;
        return self::cortar(trim($valor), $max);
    }

    public static function digits(string $valor, int $tamanho = 0): string
    {
        $d = preg_replace('/\D+/', '', $valor) ?? '';
        if ($tamanho > 0) {
            return substr($d, 0, $tamanho);
        }
        return $d;
    }

    public static function data(?string $valor): string
    {
        $valor = trim((string) $valor);
        if ($valor === '' || $valor === '0000-00-00') {
            return '';
        }
        $ts = strtotime($valor);
        if ($ts === false) {
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor)) {
                return $valor;
            }
            return '';
        }
        return date('d/m/Y', $ts);
    }

    public static function sexo($valor): string
    {
        $v = mb_strtolower(trim((string) $valor), 'UTF-8');
        if (in_array($v, ['m', 'masc', 'masculino', '1', 'h', 'homem'], true)) {
            return '1';
        }
        if (in_array($v, ['f', 'fem', 'feminino', '2', 'mulher'], true)) {
            return '2';
        }
        return '';
    }

    public static function corRaca($valor): string
    {
        $v = self::ascii(mb_strtolower(trim((string) $valor), 'UTF-8'));
        $mapa = [
            'nao declarada' => '0', 'não declarada' => '0', 'nd' => '0', '' => '0',
            'branca' => '1', 'branco' => '1',
            'preta' => '2', 'preto' => '2', 'negra' => '2',
            'parda' => '3', 'pardo' => '3',
            'amarela' => '4', 'amarela' => '4',
            'indigena' => '5', 'indígena' => '5',
        ];
        return $mapa[$v] ?? '0';
    }

    /**
     * Maior nível de escolaridade (registro 30, c56).
     * Aceita códigos oficiais (1, 2, 7, 6) e valores antigos do formulário (3–5).
     */
    public static function escolaridadeInep(string $valor): string
    {
        $v = trim($valor);
        $mapa = [
            '1' => '1',
            '2' => '2',
            '3' => '2',
            '4' => '7',
            '5' => '7',
            '6' => '6',
            '7' => '7',
        ];
        return $mapa[$v] ?? '';
    }

    public static function situacaoFuncional(string $valor): string
    {
        $v = trim($valor);
        return in_array($v, ['1', '2', '3', '4'], true) ? $v : '4';
    }

    public static function dependencia($valor): string
    {
        $v = self::ascii(mb_strtolower(trim((string) $valor), 'UTF-8'));
        if (str_contains($v, 'federal') || $v === '1') {
            return '1';
        }
        if (str_contains($v, 'estadual') || $v === '2') {
            return '2';
        }
        if (str_contains($v, 'municipal') || $v === '3') {
            return '3';
        }
        return '4';
    }

    public static function cortar(string $valor, int $max): string
    {
        if ($max <= 0) {
            return $valor;
        }
        return mb_substr($valor, 0, $max, 'UTF-8');
    }

    public static function ascii(string $valor): string
    {
        $map = [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
            'Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','Ä'=>'A',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
            'Ó'=>'O','Ò'=>'O','Õ'=>'O','Ô'=>'O','Ö'=>'O',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
            'Ç'=>'C','Ñ'=>'N',
        ];
        return strtr($valor, $map);
    }
}
