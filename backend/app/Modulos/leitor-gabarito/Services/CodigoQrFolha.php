<?php

namespace App\Modulos\LeitorGabarito\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR Code impresso na folha. O texto carrega a folha e o aluno, com conferência.
 */
class CodigoQrFolha
{
    public static function texto(int $folhaId, int $alunoId): string
    {
        if ($folhaId < 1 || $alunoId < 1) {
            throw new \InvalidArgumentException('O QR Code precisa da folha e do aluno.');
        }
        return 'ETG1.' . $folhaId . '.' . $alunoId . '.' . self::soma($folhaId, $alunoId);
    }

    /**
     * @return array{folha_id:int,aluno_id:int}|null
     */
    public static function interpretar(string $texto): ?array
    {
        if (preg_match('/^ETG1\.(\d+)\.(\d+)\.(\d+)$/', trim($texto), $partes) !== 1) {
            return null;
        }
        $folhaId = (int) $partes[1];
        $alunoId = (int) $partes[2];
        $soma = (int) $partes[3];
        if ($folhaId < 1 || $alunoId < 1 || $soma !== self::soma($folhaId, $alunoId)) {
            return null;
        }
        return ['folha_id' => $folhaId, 'aluno_id' => $alunoId];
    }

    public static function desenhar(\GdImage $im, int $folhaId, int $alunoId): void
    {
        $ret = GeometriaFolha::retanguloQr();
        $opcoes = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 4,
            'imageBase64' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
        ]);
        $png = (new QRCode($opcoes))->render(self::texto($folhaId, $alunoId));
        if (!is_string($png) || $png === '') {
            throw new \RuntimeException('Não foi possível gerar o QR Code do aluno.');
        }
        $qr = imagecreatefromstring($png);
        if (!$qr instanceof \GdImage) {
            throw new \RuntimeException('Não foi possível gerar o QR Code do aluno.');
        }
        imagecopyresampled(
            $im,
            $qr,
            $ret['x'],
            $ret['y'],
            0,
            0,
            $ret['lado'],
            $ret['lado'],
            imagesx($qr),
            imagesy($qr)
        );
    }

    private static function soma(int $folhaId, int $alunoId): int
    {
        return ($folhaId ^ ($alunoId * 31) ^ 0x5A5A) & 0xFFFF;
    }
}
