<?php

namespace App\Modulos\LeitorGabarito\Services;

/**
 * Desenho fixo da folha A4 (150 dpi). A impressão e a leitura usam estes mesmos pontos.
 */
class GeometriaFolha
{
    public const VERSAO = 1;
    public const LARGURA = 1240;
    public const ALTURA = 1754;
    public const LADO_MARCA = 56;
    public const MARGEM_MARCA = 32;
    public const MAX_QUESTOES = 60;
    public const QUESTOES_POR_COLUNA = 30;
    public const RAIO_BOLHA = 14;
    public const RAIO_LEITURA = 6;
    public const PASSO_LINHA = 38;
    public const Y_BOLHAS = 268;
    public const QR_X = 990;
    public const QR_Y = 102;
    public const QR_LADO = 148;
    public const BITS_CODIGO = 32;

    /** @var list<int> */
    private const COLUNAS_X = [72, 660];

    /**
     * @return array<string, array{x:int,y:int,lado:int}>
     */
    public static function marcas(): array
    {
        $m = self::MARGEM_MARCA;
        $s = self::LADO_MARCA;
        $w = self::LARGURA;
        $h = self::ALTURA;

        return [
            'sup_esq' => ['x' => $m, 'y' => $m, 'lado' => $s],
            'sup_dir' => ['x' => $w - $m - $s, 'y' => $m, 'lado' => $s],
            'inf_esq' => ['x' => $m, 'y' => $h - $m - $s, 'lado' => $s],
            'inf_dir' => ['x' => $w - $m - $s, 'y' => $h - $m - $s, 'lado' => $s],
        ];
    }

    /**
     * @param array{x:int,y:int,lado:int} $ret
     * @return array{0:float,1:float}
     */
    public static function centro(array $ret): array
    {
        return [$ret['x'] + $ret['lado'] / 2, $ret['y'] + $ret['lado'] / 2];
    }

    /**
     * 24 bits do id + 8 bits de conferência. O id 0 não é usado.
     *
     * @return list<int>
     */
    public static function bitsDoCodigo(int $folhaId): array
    {
        if ($folhaId < 1 || $folhaId > 0xFFFFFF) {
            throw new \InvalidArgumentException('Identificador da folha fora da faixa do código.');
        }
        $soma = ($folhaId ^ ($folhaId >> 8) ^ ($folhaId >> 16)) & 0xFF;
        $valor = ($folhaId * 256) + $soma;
        $bits = [];
        for ($i = self::BITS_CODIGO - 1; $i >= 0; $i--) {
            $bits[] = (int) (($valor >> $i) & 1);
        }
        return $bits;
    }

    /**
     * @param list<int> $bits
     */
    public static function idDosBits(array $bits): ?int
    {
        if (count($bits) !== self::BITS_CODIGO) {
            return null;
        }
        $valor = 0;
        foreach ($bits as $bit) {
            $valor = ($valor * 2) + ((int) $bit === 1 ? 1 : 0);
        }
        $folhaId = intdiv($valor, 256);
        $soma = $valor % 256;
        $esperado = ($folhaId ^ ($folhaId >> 8) ^ ($folhaId >> 16)) & 0xFF;
        if ($soma !== $esperado || $folhaId < 1) {
            return null;
        }
        return $folhaId;
    }

    /**
     * @return array{x:int,y:int,lado:int}
     */
    public static function retanguloQr(): array
    {
        return ['x' => self::QR_X, 'y' => self::QR_Y, 'lado' => self::QR_LADO];
    }

    /**
     * Quadrados do código. A leitura olha o miolo, longe da borda preta.
     *
     * @return list<array{x:int,y:int,lado:int,margem_leitura:int}>
     */
    public static function celulasCodigo(): array
    {
        $lado = 16;
        $gap = 4;
        $x = 80;
        $y = 132;
        $lista = [];
        for ($i = 0; $i < self::BITS_CODIGO; $i++) {
            $lista[] = [
                'x' => $x + $i * ($lado + $gap),
                'y' => $y,
                'lado' => $lado,
                'margem_leitura' => 4,
            ];
        }
        return $lista;
    }

    /**
     * @return array<string, int> letra => centro x
     */
    public static function centrosLetras(int $coluna): array
    {
        $x0 = self::COLUNAS_X[$coluna] + 56;
        $letras = ['A', 'B', 'C', 'D', 'E'];
        $centros = [];
        foreach ($letras as $i => $letra) {
            $centros[$letra] = $x0 + ($i * 40);
        }
        return $centros;
    }

    /**
     * @param list<array{questao_id:int,valor:float|int,alternativas:list<array{letra:string,id:int}>}> $questoes
     * @return list<array{numero:int,questao_id:int,valor:float,cy:int,bolhas:list<array{letra:string,alternativa_id:int,cx:int,cy:int,raio:int,raio_leitura:int}>}>
     */
    public static function bolhas(array $questoes): array
    {
        $saida = [];
        $cursor = 0;
        foreach (array_values($questoes) as $i => $questao) {
            if ($cursor >= self::MAX_QUESTOES) {
                break;
            }
            $rotulo = trim((string) ($questao['rotulo'] ?? ''));
            $cyRotulo = null;
            if ($rotulo !== '') {
                $posRotulo = self::posicaoLinha($cursor);
                if ($posRotulo === null) {
                    break;
                }
                $cyRotulo = $posRotulo['cy'];
                $cursor++;
            }
            if ($cursor >= self::MAX_QUESTOES) {
                break;
            }
            $pos = self::posicaoLinha($cursor);
            if ($pos === null) {
                break;
            }
            $cursor++;
            $coluna = $pos['coluna'];
            $cy = $pos['cy'];
            $centros = self::centrosLetras($coluna);
            $bolhas = [];
            foreach ($questao['alternativas'] as $alt) {
                $letra = (string) $alt['letra'];
                if (!isset($centros[$letra])) {
                    continue;
                }
                $bolhas[] = [
                    'letra' => $letra,
                    'alternativa_id' => (int) $alt['id'],
                    'cx' => $centros[$letra],
                    'cy' => $cy,
                    'raio' => self::RAIO_BOLHA,
                    'raio_leitura' => self::RAIO_LEITURA,
                ];
            }
            $saida[] = [
                'numero' => (int) ($questao['numero'] ?? ($i + 1)),
                'questao_id' => (int) $questao['questao_id'],
                'prova_id' => (int) ($questao['prova_id'] ?? 0),
                'valor' => (float) ($questao['valor'] ?? 1),
                'coluna' => $coluna,
                'cx_numero' => self::COLUNAS_X[$coluna],
                'cy' => $cy,
                'rotulo' => $rotulo,
                'cy_rotulo' => $cyRotulo,
                'bolhas' => $bolhas,
            ];
        }
        return $saida;
    }

    /**
     * @return array{coluna:int,cy:int}|null
     */
    private static function posicaoLinha(int $cursor): ?array
    {
        $coluna = intdiv($cursor, self::QUESTOES_POR_COLUNA);
        if (!isset(self::COLUNAS_X[$coluna]) || $cursor >= self::MAX_QUESTOES) {
            return null;
        }
        $linha = $cursor % self::QUESTOES_POR_COLUNA;
        return [
            'coluna' => $coluna,
            'cy' => self::Y_BOLHAS + ($linha * self::PASSO_LINHA),
        ];
    }
}
