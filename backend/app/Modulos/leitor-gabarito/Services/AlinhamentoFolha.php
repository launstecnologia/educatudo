<?php

namespace App\Modulos\LeitorGabarito\Services;

/**
 * Imagem já endireitada em relação ao desenho da folha.
 */
class AlinhamentoFolha
{
    public \GdImage $imagem;
    /** @var array<int, array<int, float>> */
    public array $homografia;
    public float $escala;
    public int $limiar;
    public ?int $folhaId;
    public bool $codigoOk;
    public ?int $alunoQr;

    public function __construct(\GdImage $imagem, array $homografia, float $escala, int $limiar, ?int $folhaId, bool $codigoOk, ?int $alunoQr = null)
    {
        $this->imagem = $imagem;
        $this->homografia = $homografia;
        $this->escala = $escala;
        $this->limiar = $limiar;
        $this->folhaId = $folhaId;
        $this->codigoOk = $codigoOk;
        $this->alunoQr = $alunoQr;
    }
}
