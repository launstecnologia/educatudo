<?php

namespace App\Modulos\LeitorGabarito\Services;

/**
 * Acha as marcas dos cantos, endireita a foto e mede o miolo de cada bolha.
 */
class LeitorFolha
{
    private const LADO_MAXIMO = 1800;

    public function preparar(string $caminho): AlinhamentoFolha
    {
        $imagem = $this->carregar($caminho);
        $imagem = $this->redimensionar($imagem);
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        if ($largura < 700 || $altura < 900) {
            throw new \RuntimeException('A imagem está pequena demais. Envie o scan da folha inteira.');
        }

        $luminancia = $this->mapaLuminancia($imagem, $largura, $altura);
        $limiar = $this->limiarOtsu($luminancia);
        $cantos = $this->detectarCantos($luminancia, $largura, $altura, $limiar);
        $homografia = $this->homografia($cantos);
        $escala = $this->escala($cantos);
        $porBits = $this->lerCodigo($imagem, $homografia, $escala, $limiar);
        $porQr = $this->lerQr($imagem, $homografia);
        if ($porQr !== null && $porBits !== null && $porQr['folha_id'] !== $porBits) {
            return new AlinhamentoFolha($imagem, $homografia, $escala, $limiar, null, false, null);
        }
        $folhaId = $porQr['folha_id'] ?? $porBits;
        $alunoQr = $porQr['aluno_id'] ?? null;

        return new AlinhamentoFolha($imagem, $homografia, $escala, $limiar, $folhaId, $folhaId !== null, $alunoQr);
    }

    /**
     * @param list<array{questao_id:int,valor:float|int,alternativas:list<array{letra:string,id:int}>}> $questoes
     * @return list<array{numero:int,questao_id:int,prova_id:int,rotulo:string,valor:float,situacao:string,letra:?string,alternativa_id:?int,opcoes:list<array{letra:string,alternativa_id:int,preenchimento:float}>}>
     */
    public function lerMarcacoes(AlinhamentoFolha $alinhamento, array $questoes): array
    {
        $saida = [];
        foreach (GeometriaFolha::bolhas($questoes) as $questao) {
            $opcoes = [];
            $preenchimentos = [];
            foreach ($questao['bolhas'] as $bolha) {
                [$x, $y] = $this->projetar($alinhamento->homografia, (float) $bolha['cx'], (float) $bolha['cy']);
                $raio = max(2.0, (float) $bolha['raio_leitura'] * $alinhamento->escala);
                $taxa = $this->taxaEscura($alinhamento->imagem, $x, $y, $raio, $alinhamento->limiar);
                $opcoes[] = [
                    'letra' => $bolha['letra'],
                    'alternativa_id' => (int) $bolha['alternativa_id'],
                    'preenchimento' => round($taxa, 3),
                ];
                $preenchimentos[$bolha['letra']] = $taxa;
            }
            [$situacao, $letra] = $this->classificar($preenchimentos);
            $alternativaId = null;
            if ($letra !== null) {
                foreach ($opcoes as $opcao) {
                    if ($opcao['letra'] === $letra) {
                        $alternativaId = $opcao['alternativa_id'];
                        break;
                    }
                }
            }
            $saida[] = [
                'numero' => (int) $questao['numero'],
                'questao_id' => (int) $questao['questao_id'],
                'prova_id' => (int) ($questao['prova_id'] ?? 0),
                'rotulo' => trim((string) ($questao['rotulo'] ?? '')),
                'valor' => (float) $questao['valor'],
                'situacao' => $situacao,
                'letra' => $letra,
                'alternativa_id' => $alternativaId,
                'opcoes' => $opcoes,
            ];
        }
        return $saida;
    }

    private function carregar(string $caminho): \GdImage
    {
        if (!function_exists('imagecreatefromjpeg')) {
            throw new \RuntimeException('A leitura precisa da extensão GD do PHP.');
        }
        if (!is_file($caminho)) {
            throw new \RuntimeException('Arquivo da folha não encontrado.');
        }
        $info = @getimagesize($caminho);
        $tipo = is_array($info) ? (int) ($info[2] ?? 0) : 0;
        $im = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($caminho),
            IMAGETYPE_PNG => @imagecreatefrompng($caminho),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($caminho) : false,
            default => false,
        };
        if (!$im instanceof \GdImage) {
            throw new \RuntimeException('Envie a folha em JPG ou PNG.');
        }
        $im = $this->paraTruecolor($im);
        return $this->aplicarOrientacao($caminho, $im);
    }

    private function paraTruecolor(\GdImage $im): \GdImage
    {
        if (imageistruecolor($im)) {
            return $im;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $nova = imagecreatetruecolor($w, $h);
        $branco = imagecolorallocate($nova, 255, 255, 255);
        imagefill($nova, 0, 0, $branco);
        imagecopy($nova, $im, 0, 0, 0, 0, $w, $h);
        return $nova;
    }

    private function aplicarOrientacao(string $caminho, \GdImage $im): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $im;
        }
        $exif = @exif_read_data($caminho);
        $orientacao = (int) (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1);
        $angulo = match ($orientacao) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angulo === 0) {
            return $im;
        }
        $branco = imagecolorallocate($im, 255, 255, 255);
        $nova = imagerotate($im, $angulo, $branco);
        if (!$nova instanceof \GdImage) {
            throw new \RuntimeException('Não foi possível endireitar a orientação da foto.');
        }
        return $nova;
    }

    private function redimensionar(\GdImage $im): \GdImage
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $maior = max($w, $h);
        if ($maior <= self::LADO_MAXIMO) {
            return $im;
        }
        $fator = self::LADO_MAXIMO / $maior;
        $nw = max(1, (int) round($w * $fator));
        $nh = max(1, (int) round($h * $fator));
        $nova = imagecreatetruecolor($nw, $nh);
        $branco = imagecolorallocate($nova, 255, 255, 255);
        imagefill($nova, 0, 0, $branco);
        imagecopyresampled($nova, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $nova;
    }

    private function mapaLuminancia(\GdImage $im, int $largura, int $altura): string
    {
        $n = $largura * $altura;
        $mapa = str_repeat("\0", $n);
        $i = 0;
        for ($y = 0; $y < $altura; $y++) {
            for ($x = 0; $x < $largura; $x++) {
                $cor = imagecolorat($im, $x, $y);
                $r = ($cor >> 16) & 0xFF;
                $g = ($cor >> 8) & 0xFF;
                $b = $cor & 0xFF;
                $mapa[$i] = chr((int) (($r * 30 + $g * 59 + $b * 11) / 100));
                $i++;
            }
        }
        return $mapa;
    }

    private function limiarOtsu(string $mapa): int
    {
        $hist = array_fill(0, 256, 0);
        $n = strlen($mapa);
        for ($i = 0; $i < $n; $i++) {
            $hist[ord($mapa[$i])]++;
        }
        $soma = 0;
        for ($t = 0; $t < 256; $t++) {
            $soma += $t * $hist[$t];
        }
        $somaFundo = 0;
        $pesoFundo = 0;
        $melhor = 0.0;
        $limiar = 128;
        for ($t = 0; $t < 256; $t++) {
            $pesoFundo += $hist[$t];
            if ($pesoFundo === 0) {
                continue;
            }
            $pesoFrente = $n - $pesoFundo;
            if ($pesoFrente === 0) {
                break;
            }
            $somaFundo += $t * $hist[$t];
            $mediaFundo = $somaFundo / $pesoFundo;
            $mediaFrente = ($soma - $somaFundo) / $pesoFrente;
            $variancia = $pesoFundo * $pesoFrente * ($mediaFundo - $mediaFrente) ** 2;
            if ($variancia > $melhor) {
                $melhor = $variancia;
                $limiar = $t;
            }
        }
        if ($limiar > 200) {
            return 170;
        }
        if ($limiar < 50) {
            return 90;
        }
        return $limiar;
    }

    /**
     * @return array<string, array{0:float,1:float}>
     */
    private function detectarCantos(string $mapa, int $largura, int $altura, int $limiar): array
    {
        $visto = str_repeat("\0", strlen($mapa));
        $n = strlen($mapa);
        $candidatos = [];
        for ($i = 0; $i < $n; $i++) {
            if ($visto[$i] === "\1" || ord($mapa[$i]) >= $limiar) {
                continue;
            }
            $blob = $this->mancha($mapa, $visto, $largura, $altura, $i, $limiar);
            if ($blob === null) {
                continue;
            }
            $candidatos[] = $blob;
        }

        $porCanto = [];
        foreach ($candidatos as $blob) {
            $canto = $this->cantoDoPonto($blob['cx'], $blob['cy'], $largura, $altura);
            if ($canto === null) {
                continue;
            }
            if (!isset($porCanto[$canto]) || $blob['area'] > $porCanto[$canto]['area']) {
                $porCanto[$canto] = $blob;
            }
        }

        $chaves = ['sup_esq', 'sup_dir', 'inf_esq', 'inf_dir'];
        foreach ($chaves as $chave) {
            if (!isset($porCanto[$chave])) {
                throw new \RuntimeException('Não encontrei as quatro marcas pretas dos cantos. A folha precisa aparecer inteira.');
            }
        }
        if ($porCanto['sup_esq']['cx'] >= $porCanto['sup_dir']['cx'] || $porCanto['sup_esq']['cy'] >= $porCanto['inf_esq']['cy']) {
            throw new \RuntimeException('As marcas dos cantos ficaram fora de ordem. Escaneie a folha na vertical.');
        }

        $cantos = [];
        foreach ($chaves as $chave) {
            $cantos[$chave] = [$porCanto[$chave]['cx'], $porCanto[$chave]['cy']];
        }
        return $cantos;
    }

    /**
     * @return array{cx:float,cy:float,area:int}|null
     */
    private function mancha(string $mapa, string &$visto, int $largura, int $altura, int $inicio, int $limiar): ?array
    {
        $fila = [$inicio];
        $visto[$inicio] = "\1";
        $area = 0;
        $minX = $maxX = $inicio % $largura;
        $minY = $maxY = intdiv($inicio, $largura);
        $limite = 25000;
        while ($fila !== []) {
            $p = array_pop($fila);
            $x = $p % $largura;
            $y = intdiv($p, $largura);
            $area++;
            if ($x < $minX) {
                $minX = $x;
            }
            if ($x > $maxX) {
                $maxX = $x;
            }
            if ($y < $minY) {
                $minY = $y;
            }
            if ($y > $maxY) {
                $maxY = $y;
            }
            if ($area > $limite) {
                continue;
            }
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                if ($nx < 0 || $ny < 0 || $nx >= $largura || $ny >= $altura) {
                    continue;
                }
                $ni = $ny * $largura + $nx;
                if ($visto[$ni] === "\1" || ord($mapa[$ni]) >= $limiar) {
                    continue;
                }
                $visto[$ni] = "\1";
                $fila[] = $ni;
            }
        }

        $bw = $maxX - $minX + 1;
        $bh = $maxY - $minY + 1;
        if ($area < 80 || $area > $limite || $bw < 12 || $bh < 12) {
            return null;
        }
        $aspecto = $bw / $bh;
        if ($aspecto < 0.75 || $aspecto > 1.35) {
            return null;
        }
        $preenchimento = $area / ($bw * $bh);
        if ($preenchimento < 0.55) {
            return null;
        }
        $ladoMax = (int) (0.12 * min($largura, $altura));
        if ($bw > $ladoMax || $bh > $ladoMax) {
            return null;
        }
        if ($minX <= 1 || $minY <= 1 || $maxX >= $largura - 2 || $maxY >= $altura - 2) {
            return null;
        }

        return [
            'cx' => ($minX + $maxX) / 2,
            'cy' => ($minY + $maxY) / 2,
            'area' => $area,
        ];
    }

    private function cantoDoPonto(float $x, float $y, int $largura, int $altura): ?string
    {
        $naEsquerda = $x < $largura * 0.38;
        $naDireita = $x > $largura * 0.62;
        $emCima = $y < $altura * 0.38;
        $embaixo = $y > $altura * 0.62;
        if ($emCima && $naEsquerda) {
            return 'sup_esq';
        }
        if ($emCima && $naDireita) {
            return 'sup_dir';
        }
        if ($embaixo && $naEsquerda) {
            return 'inf_esq';
        }
        if ($embaixo && $naDireita) {
            return 'inf_dir';
        }
        return null;
    }

    /**
     * @param array<string, array{0:float,1:float}> $cantosScan
     * @return array<int, array<int, float>>
     */
    private function homografia(array $cantosScan): array
    {
        $ordem = ['sup_esq', 'sup_dir', 'inf_dir', 'inf_esq'];
        $a = [];
        $b = [];
        foreach ($ordem as $chave) {
            [$x, $y] = GeometriaFolha::centro(GeometriaFolha::marcas()[$chave]);
            [$u, $v] = $cantosScan[$chave];
            $a[] = [$x, $y, 1.0, 0.0, 0.0, 0.0, -$x * $u, -$y * $u, $u];
            $a[] = [0.0, 0.0, 0.0, $x, $y, 1.0, -$x * $v, -$y * $v, $v];
        }
        $h = $this->gauss($a);
        return [
            [$h[0], $h[1], $h[2]],
            [$h[3], $h[4], $h[5]],
            [$h[6], $h[7], 1.0],
        ];
    }

    /**
     * @param list<list<float>> $matriz 8 linhas, 9 colunas
     * @return list<float>
     */
    private function gauss(array $matriz): array
    {
        $n = count($matriz);
        for ($col = 0; $col < $n; $col++) {
            $pivo = $col;
            for ($linha = $col + 1; $linha < $n; $linha++) {
                if (abs($matriz[$linha][$col]) > abs($matriz[$pivo][$col])) {
                    $pivo = $linha;
                }
            }
            if (abs($matriz[$pivo][$col]) < 1e-8) {
                throw new \RuntimeException('Não consegui alinhar a folha. Escaneie de novo, com a página inteira.');
            }
            if ($pivo !== $col) {
                $tmp = $matriz[$col];
                $matriz[$col] = $matriz[$pivo];
                $matriz[$pivo] = $tmp;
            }
            $div = $matriz[$col][$col];
            for ($j = $col; $j <= $n; $j++) {
                $matriz[$col][$j] /= $div;
            }
            for ($linha = 0; $linha < $n; $linha++) {
                if ($linha === $col) {
                    continue;
                }
                $fator = $matriz[$linha][$col];
                if ($fator == 0.0) {
                    continue;
                }
                for ($j = $col; $j <= $n; $j++) {
                    $matriz[$linha][$j] -= $fator * $matriz[$col][$j];
                }
            }
        }
        $resultado = [];
        for ($i = 0; $i < $n; $i++) {
            $resultado[] = $matriz[$i][$n];
        }
        return $resultado;
    }

    /**
     * @param array<string, array{0:float,1:float}> $cantosScan
     */
    private function escala(array $cantosScan): float
    {
        [$x1, $y1] = GeometriaFolha::centro(GeometriaFolha::marcas()['sup_esq']);
        [$x2, $y2] = GeometriaFolha::centro(GeometriaFolha::marcas()['sup_dir']);
        $modelo = hypot($x2 - $x1, $y2 - $y1);
        [$u1, $v1] = $cantosScan['sup_esq'];
        [$u2, $v2] = $cantosScan['sup_dir'];
        $scan = hypot($u2 - $u1, $v2 - $v1);
        if ($modelo < 1 || $scan < 1) {
            throw new \RuntimeException('Não consegui medir a escala da folha.');
        }
        return $scan / $modelo;
    }

    /**
     * @param array<int, array<int, float>> $homografia
     * @return array{0:float,1:float}
     */
    private function projetar(array $homografia, float $x, float $y): array
    {
        $w = $homografia[2][0] * $x + $homografia[2][1] * $y + $homografia[2][2];
        if (abs($w) < 1e-8) {
            return [0.0, 0.0];
        }
        return [
            ($homografia[0][0] * $x + $homografia[0][1] * $y + $homografia[0][2]) / $w,
            ($homografia[1][0] * $x + $homografia[1][1] * $y + $homografia[1][2]) / $w,
        ];
    }

    /**
     * @param array<int, array<int, float>> $homografia
     */
    private function lerCodigo(\GdImage $im, array $homografia, float $escala, int $limiar): ?int
    {
        $bits = [];
        foreach (GeometriaFolha::celulasCodigo() as $celula) {
            $margem = (int) $celula['margem_leitura'];
            $cx = $celula['x'] + ($celula['lado'] / 2);
            $cy = $celula['y'] + ($celula['lado'] / 2);
            [$x, $y] = $this->projetar($homografia, $cx, $cy);
            $raio = max(2.0, (($celula['lado'] / 2) - $margem) * $escala);
            $bits[] = $this->taxaEscura($im, $x, $y, $raio, $limiar) >= 0.45 ? 1 : 0;
        }
        return GeometriaFolha::idDosBits($bits);
    }

    /**
     * Recorta o QR no desenho da folha e lê o aluno impresso nele.
     *
     * @param array<int, array<int, float>> $homografia
     * @return array{folha_id:int,aluno_id:int}|null
     */
    private function lerQr(\GdImage $im, array $homografia): ?array
    {
        $ret = GeometriaFolha::retanguloQr();
        $lado = 240;
        $recorte = imagecreatetruecolor($lado, $lado);
        if (!$recorte instanceof \GdImage) {
            return null;
        }
        $branco = imagecolorallocate($recorte, 255, 255, 255);
        imagefill($recorte, 0, 0, $branco);
        $largura = imagesx($im);
        $altura = imagesy($im);
        $passo = $ret['lado'] / $lado;
        for ($y = 0; $y < $lado; $y++) {
            for ($x = 0; $x < $lado; $x++) {
                [$px, $py] = $this->projetar($homografia, $ret['x'] + ($x * $passo), $ret['y'] + ($y * $passo));
                $ix = (int) round($px);
                $iy = (int) round($py);
                if ($ix < 0 || $iy < 0 || $ix >= $largura || $iy >= $altura) {
                    continue;
                }
                imagesetpixel($recorte, $x, $y, imagecolorat($im, $ix, $iy));
            }
        }
        $fluxo = fopen('php://memory', 'w+b');
        if ($fluxo === false) {
            return null;
        }
        imagepng($recorte, $fluxo);
        rewind($fluxo);
        $blob = stream_get_contents($fluxo);
        fclose($fluxo);
        if (!is_string($blob) || $blob === '') {
            return null;
        }
        try {
            $lido = (new \chillerlan\QRCode\QRCode())->readFromBlob($blob);
        } catch (\Throwable $e) {
            return null;
        }
        return CodigoQrFolha::interpretar((string) $lido->data);
    }

    private function taxaEscura(\GdImage $im, float $cx, float $cy, float $raio, int $limiar): float
    {
        $largura = imagesx($im);
        $altura = imagesy($im);
        $r2 = $raio * $raio;
        $x0 = (int) floor($cx - $raio);
        $x1 = (int) ceil($cx + $raio);
        $y0 = (int) floor($cy - $raio);
        $y1 = (int) ceil($cy + $raio);
        $amostras = 0;
        $escuros = 0;
        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                $dx = $x - $cx;
                $dy = $y - $cy;
                if (($dx * $dx) + ($dy * $dy) > $r2) {
                    continue;
                }
                if ($x < 0 || $y < 0 || $x >= $largura || $y >= $altura) {
                    continue;
                }
                $cor = imagecolorat($im, $x, $y);
                $r = ($cor >> 16) & 0xFF;
                $g = ($cor >> 8) & 0xFF;
                $b = $cor & 0xFF;
                $lum = (int) (($r * 30 + $g * 59 + $b * 11) / 100);
                $amostras++;
                if ($lum < $limiar) {
                    $escuros++;
                }
            }
        }
        if ($amostras === 0) {
            return 0.0;
        }
        return $escuros / $amostras;
    }

    /**
     * @param array<string, float> $preenchimentos
     * @return array{0:string,1:?string}
     */
    private function classificar(array $preenchimentos): array
    {
        if ($preenchimentos === []) {
            return ['vazia', null];
        }
        arsort($preenchimentos);
        $letras = array_keys($preenchimentos);
        $valores = array_values($preenchimentos);
        $primeiro = (float) $valores[0];
        $segundo = (float) ($valores[1] ?? 0);
        if ($primeiro >= 0.45 && $segundo >= 0.40) {
            return ['dupla', null];
        }
        if ($primeiro >= 0.40 && ($primeiro - $segundo) >= 0.18) {
            return ['marcada', (string) $letras[0]];
        }
        if ($primeiro >= 0.22) {
            return ['duvida', (string) $letras[0]];
        }
        return ['vazia', null];
    }
}
