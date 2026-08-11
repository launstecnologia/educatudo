<?php

namespace App\Services;

use Database;

class FinanceBoletoService
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // Gera código de barras simulado (44 dígitos no padrão FEBRABAN, sem validade bancária)
    public function gerarCodigo(int $installmentId, float $valor, string $vencimento): string
    {
        $banco   = '001'; // BB (simulado)
        $moeda   = '9';
        $venc    = $this->fatorVencimento($vencimento);
        $valStr  = str_pad((int)round($valor * 100), 10, '0', STR_PAD_LEFT);
        $livreId = str_pad($installmentId, 25, '0', STR_PAD_LEFT);

        $semDv   = $banco . $moeda . $venc . $valStr . $livreId;
        $dv      = $this->modulo11($semDv);

        return substr($semDv, 0, 4) . $dv . substr($semDv, 4);
    }

    // Linha digitável formatada (simulada)
    public function formatarLinhaDigitavel(string $codigo): string
    {
        if (strlen($codigo) < 44) return $codigo;
        return implode(' ', [
            substr($codigo, 0, 10),
            substr($codigo, 10, 11),
            substr($codigo, 21, 11),
            substr($codigo, 32, 1),
            substr($codigo, 33),
        ]);
    }

    public function gerarEPersistir(int $installmentId, float $valor, string $vencimento): string
    {
        $codigo = $this->gerarCodigo($installmentId, $valor, $vencimento);
        $this->db->update(
            "UPDATE finance_installments SET boleto_codigo = ?, boleto_gerado_em = NOW() WHERE id = ?",
            [$codigo, $installmentId]
        );
        return $codigo;
    }

    private function fatorVencimento(string $data): string
    {
        $base    = new \DateTime('1997-10-07');
        $alvo    = new \DateTime($data);
        $diff    = (int)$base->diff($alvo)->days;
        return str_pad($diff, 4, '0', STR_PAD_LEFT);
    }

    private function modulo11(string $numero): int
    {
        $soma = 0; $mult = 2;
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $soma += (int)$numero[$i] * $mult;
            $mult = $mult === 9 ? 2 : $mult + 1;
        }
        $resto = $soma % 11;
        if ($resto === 0 || $resto === 1) return 1;
        return 11 - $resto;
    }
}
