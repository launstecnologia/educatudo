<?php

namespace App\Modulos\LeitorGabarito\Services;

/**
 * Desenha a folha em PNG, nas coordenadas de GeometriaFolha.
 */
class DesenhistaFolha
{
    private string $fonte;

    public function __construct()
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('A geração da folha precisa da extensão GD do PHP.');
        }
        $this->fonte = dirname(__DIR__, 4) . '/public/static/fonts/DejaVuSans.ttf';
    }

    /**
     * @param list<array{questao_id:int,valor:float|int,alternativas:list<array{letra:string,id:int}>}> $questoes
     */
    public function criarImagem(array $questoes, int $folhaId, int $alunoId, string $nomeAluno, string $tituloProva): \GdImage
    {
        $im = imagecreatetruecolor(GeometriaFolha::LARGURA, GeometriaFolha::ALTURA);
        if ($im === false) {
            throw new \RuntimeException('Não foi possível criar a imagem da folha.');
        }
        $branco = imagecolorallocate($im, 255, 255, 255);
        $preto = imagecolorallocate($im, 0, 0, 0);
        $cinza = imagecolorallocate($im, 80, 80, 80);
        imagefill($im, 0, 0, $branco);

        foreach (GeometriaFolha::marcas() as $marca) {
            imagefilledrectangle(
                $im,
                $marca['x'],
                $marca['y'],
                $marca['x'] + $marca['lado'] - 1,
                $marca['y'] + $marca['lado'] - 1,
                $preto
            );
        }

        $textoX = GeometriaFolha::MARGEM_MARCA + GeometriaFolha::LADO_MARCA + 24;
        $this->texto($im, $preto, 18, $textoX, 78, $this->cortar($tituloProva, 42), true);
        $this->texto($im, $cinza, 12, $textoX, 108, $this->cortar($nomeAluno, 40), false);
        $this->texto($im, $cinza, 10, GeometriaFolha::QR_X, GeometriaFolha::QR_Y - 8, 'QR do aluno', false);
        CodigoQrFolha::desenhar($im, $folhaId, $alunoId);

        $bits = GeometriaFolha::bitsDoCodigo($folhaId);
        $celulas = GeometriaFolha::celulasCodigo();
        foreach ($celulas as $i => $celula) {
            $x1 = $celula['x'];
            $y1 = $celula['y'];
            $x2 = $x1 + $celula['lado'] - 1;
            $y2 = $y1 + $celula['lado'] - 1;
            imagerectangle($im, $x1, $y1, $x2, $y2, $preto);
            if (!empty($bits[$i])) {
                imagefilledrectangle($im, $x1 + 1, $y1 + 1, $x2 - 1, $y2 - 1, $preto);
            }
        }

        $bolhas = GeometriaFolha::bolhas($questoes);
        $colunasComCabecalho = [];
        foreach ($bolhas as $questao) {
            $coluna = (int) $questao['coluna'];
            if (!isset($colunasComCabecalho[$coluna])) {
                $colunasComCabecalho[$coluna] = true;
                $yLetras = (int) $questao['cy'] - 22;
                if (!empty($questao['rotulo']) && $questao['cy_rotulo'] !== null) {
                    $yLetras = (int) $questao['cy_rotulo'] - 20;
                }
                foreach (GeometriaFolha::centrosLetras($coluna) as $letra => $cx) {
                    $this->texto($im, $cinza, 11, $cx - 4, $yLetras, $letra, true);
                }
            }
            if (!empty($questao['rotulo']) && $questao['cy_rotulo'] !== null) {
                $this->texto($im, $preto, 12, (int) $questao['cx_numero'], (int) $questao['cy_rotulo'] + 4, (string) $questao['rotulo'], true);
            }
            $this->texto($im, $preto, 11, (int) $questao['cx_numero'], (int) $questao['cy'] + 4, (string) $questao['numero'], false);
            foreach ($questao['bolhas'] as $bolha) {
                imageellipse(
                    $im,
                    (int) $bolha['cx'],
                    (int) $bolha['cy'],
                    GeometriaFolha::RAIO_BOLHA * 2,
                    GeometriaFolha::RAIO_BOLHA * 2,
                    $preto
                );
            }
        }

        $this->texto(
            $im,
            $cinza,
            11,
            GeometriaFolha::MARGEM_MARCA + GeometriaFolha::LADO_MARCA + 24,
            GeometriaFolha::ALTURA - 48,
            'Preencha só uma bolha por questão, com caneta preta. O QR Code identifica o aluno na leitura.',
            false
        );

        return $im;
    }

    public function gravarPng(\GdImage $im, string $caminho): void
    {
        $dir = dirname($caminho);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar a pasta da folha.');
        }
        if (!imagepng($im, $caminho, 6)) {
            throw new \RuntimeException('Não foi possível gravar a folha.');
        }
    }

    private function texto(\GdImage $im, int $cor, int $tamanho, int $x, int $y, string $texto, bool $negrito): void
    {
        $texto = trim(str_replace(["\r", "\n"], ' ', $texto));
        if ($texto === '') {
            return;
        }
        $fonte = $negrito
            ? dirname(__DIR__, 4) . '/public/static/fonts/DejaVuSans-Bold.ttf'
            : $this->fonte;
        if (!is_file($fonte)) {
            $fonte = $this->fonte;
        }
        if (is_file($fonte)) {
            imagettftext($im, $tamanho, 0, $x, $y, $cor, $fonte, $texto);
            return;
        }
        imagestring($im, 3, $x, $y - 10, substr($texto, 0, 90), $cor);
    }

    private function cortar(string $texto, int $limite): string
    {
        $texto = trim($texto);
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($texto, 0, $limite, '…', 'UTF-8');
        }
        return strlen($texto) > $limite ? substr($texto, 0, $limite) : $texto;
    }
}
