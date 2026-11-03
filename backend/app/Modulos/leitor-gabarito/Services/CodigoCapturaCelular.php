<?php

namespace App\Modulos\LeitorGabarito\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR que o professor ou a coordenação escaneia para fotografar o gabarito no celular.
 * O link vale algumas horas e só serve para enviar a foto desta prova, nesta escola.
 */
class CodigoCapturaCelular
{
    private const HORAS = 8;

    public static function emitir(int $provaId, int $professorId, int $conjunto = 0): ?string
    {
        if ($provaId < 1 || self::chave() === '' || self::slug() === '') {
            return null;
        }
        $professorId = max(0, $professorId);
        $conjunto = $conjunto === 1 ? 1 : 0;
        $expira = time() + (self::HORAS * 3600);
        $corpo = $provaId . '.' . $professorId . '.' . $expira . '.' . self::slug();
        if ($conjunto === 1) {
            $corpo .= '.1';
        }
        $assinatura = hash_hmac('sha256', $corpo, self::chave());
        return self::base64($corpo . '|' . $assinatura);
    }

    /**
     * @return array{prova_id:int,professor_id:int,conjunto:int}|null
     */
    public static function abrir(string $token): ?array
    {
        if (self::chave() === '' || self::slug() === '' || $token === '') {
            return null;
        }
        $bruto = self::base64Decode($token);
        if ($bruto === null || !str_contains($bruto, '|')) {
            return null;
        }
        [$corpo, $assinatura] = explode('|', $bruto, 2);
        $esperada = hash_hmac('sha256', $corpo, self::chave());
        if (!hash_equals($esperada, $assinatura)) {
            return null;
        }
        $partes = explode('.', $corpo);
        if (count($partes) !== 4 && count($partes) !== 5) {
            return null;
        }
        $provaId = (int) $partes[0];
        $professorId = (int) $partes[1];
        $expira = (int) $partes[2];
        $slug = (string) $partes[3];
        $conjunto = count($partes) === 5 ? (int) $partes[4] : 0;
        if ($provaId < 1 || $professorId < 0 || ($conjunto !== 0 && $conjunto !== 1) || $expira < time() || !hash_equals(self::slug(), $slug)) {
            return null;
        }
        return ['prova_id' => $provaId, 'professor_id' => $professorId, 'conjunto' => $conjunto];
    }

    /**
     * @return array{url:string,png:string}|null
     */
    public static function imagem(int $provaId, int $professorId, int $conjunto = 0): ?array
    {
        $token = self::emitir($provaId, $professorId, $conjunto);
        if ($token === null || !defined('URL')) {
            return null;
        }
        $url = rtrim((string) URL, '/') . '/captura-gabarito/' . $token;
        $opcoes = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 6,
            'imageBase64' => false,
        ]);
        $png = (new QRCode($opcoes))->render($url);
        if (!is_string($png) || $png === '') {
            return null;
        }
        return [
            'url' => $url,
            'png' => 'data:image/png;base64,' . base64_encode($png),
        ];
    }

    private static function chave(): string
    {
        if (!function_exists('env')) {
            return '';
        }
        $chave = trim((string) env('SCHOOL_SECRET_KEY', ''));
        return strlen($chave) >= 16 ? $chave : '';
    }

    private static function slug(): string
    {
        if (!defined('TENANT_SLUG')) {
            return '';
        }
        $limpo = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) TENANT_SLUG) ?? '';
        return $limpo;
    }

    private static function base64(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }

    private static function base64Decode(string $valor): ?string
    {
        $pad = strlen($valor) % 4;
        if ($pad > 0) {
            $valor .= str_repeat('=', 4 - $pad);
        }
        $bruto = base64_decode(strtr($valor, '-_', '+/'), true);
        return is_string($bruto) && $bruto !== '' ? $bruto : null;
    }
}
