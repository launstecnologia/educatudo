<?php

namespace App\Modulos\Provas\Services;

use App\Services\MediaStorageService;
use App\Services\OpenAIService;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\XObject\Image;

/**
 * Lê um PDF ou uma imagem de questão e devolve enunciado, alternativas e as figuras do arquivo.
 */
class TranscricaoQuestaoArquivoService
{
    private string $slugEscola = '';
    /**
     * @return list<array{enunciado:string,tipo:string,valor:float,alternativas:list<array{texto:string,correta:int,ordem:int}>,explicacao:?string}>
     */
    public function transcrever(string $caminho, string $mime, int $usuarioId, string $slugEscola): array
    {
        $this->slugEscola = $this->slugLimpo($slugEscola);
        $figuras = [];
        $texto = '';

        if ($mime === 'application/pdf') {
            [$texto, $figuras, $maiorFigura] = $this->lerPdf($caminho, $usuarioId);
            if (mb_strlen(trim($texto)) < 40 && $maiorFigura !== '') {
                $openai = new OpenAIService();
                $texto = trim((string) $openai->transcreverComGoogleVision(base64_encode($maiorFigura), true));
            }
        } else {
            $texto = $this->ocrDoArquivo($caminho, $mime);
            $urlOrigem = $this->publicarArquivo($caminho, $mime, $usuarioId, 'origem');
            if ($urlOrigem !== null) {
                $figuras[] = $urlOrigem;
            }
        }

        $texto = trim($texto);
        if ($texto === '') {
            throw new \RuntimeException('Não foi possível ler o texto deste arquivo.');
        }

        $recortesPdf = $mime === 'application/pdf' ? $this->recortarFigurasDoPdf($caminho, $usuarioId) : ['recortes' => [], 'ancoras' => []];
        $extraidas = $this->atribuirGabarito($texto, $this->estruturar($texto, count($figuras) + count($recortesPdf['recortes'])));
        $lista = [];
        foreach ($extraidas as $indiceQuestao => $questao) {
            $indice = (int) ($questao['figura'] ?? 0);
            $urls = [];
            foreach ($recortesPdf['recortes'] as $recorte) {
                if ($this->recorteDaQuestao($recorte, $indiceQuestao + 1, $recortesPdf['ancoras'])) {
                    $urls[] = $recorte['url'];
                }
            }
            if ($urls === [] && $indice >= 1 && isset($figuras[$indice - 1])) {
                $urls[] = $figuras[$indice - 1];
            } elseif ($urls === [] && count($extraidas) === 1 && count($figuras) === 1 && !empty($questao['tem_figura'])) {
                $urls[] = $figuras[0];
            }
            $questao['enunciado'] = $this->montarEnunciado((string) ($questao['enunciado'] ?? ''), $urls);
            unset($questao['figura'], $questao['tem_figura']);
            $lista[] = $questao;
        }
        if ($lista === []) {
            throw new \RuntimeException('Não foi possível separar as questões deste arquivo.');
        }

        return $lista;
    }

    /**
     * @return array{0:string,1:list<string>,2:string}
     */
    private function lerPdf(string $caminho, int $usuarioId): array
    {
        if (!class_exists(Parser::class)) {
            return ['', [], ''];
        }
        $config = new Config();
        $config->setRetainImageContent(true);
        $pdf = (new Parser([], $config))->parseFile($caminho);
        $texto = trim($pdf->getText());
        $urls = [];
        $maior = '';
        $vistos = [];
        $paginas = 0;
        foreach ($pdf->getPages() as $pagina) {
            if (++$paginas > 8 || count($urls) >= 8) {
                break;
            }
            foreach ($pagina->getXObjects() as $objeto) {
                if (!$objeto instanceof Image || count($urls) >= 8) {
                    continue;
                }
                $bytes = $objeto->getContent();
                if (!is_string($bytes) || strlen($bytes) < 8000) {
                    continue;
                }
                $formato = $this->formatoImagem($bytes);
                if ($formato === null) {
                    continue;
                }
                $hash = md5($bytes);
                if (isset($vistos[$hash])) {
                    continue;
                }
                $vistos[$hash] = true;
                if (strlen($bytes) > strlen($maior)) {
                    $maior = $bytes;
                }
                $url = $this->publicarBytes($bytes, $formato['ext'], $formato['mime'], $usuarioId, 'figura');
                if ($url !== null) {
                    $urls[] = $url;
                }
            }
        }
        if (mb_strlen($texto) < 40 && $urls === []) {
            $texto = '';
        }

        return [$texto, $urls, $maior];
    }

    /**
     * Recorta gráfico e figura desenhados na página, que não vêm como arquivo de imagem.
     *
     * @return array{recortes:list<array{url:string,pagina:int,y:float}>,ancoras:list<array{numero:int,pagina:int,y:float}>}
     */
    private function recortarFigurasDoPdf(string $caminho, int $usuarioId): array
    {
        $vazio = ['recortes' => [], 'ancoras' => []];
        if (!function_exists('imagecreatefrompng') || !$this->temBinario('pdftoppm') || !$this->temBinario('pdftotext')) {
            return $vazio;
        }
        $dir = sys_get_temp_dir() . '/prova_fig_' . bin2hex(random_bytes(4));
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            return $vazio;
        }
        try {
            $html = $this->executar(['pdftotext', '-bbox-layout', '-f', '1', '-l', '6', $caminho, '-']);
            $codigo = $this->executarCodigo(['pdftoppm', '-png', '-r', '110', '-f', '1', '-l', '6', $caminho, $dir . '/pagina']);
            if ($html === '' || $codigo !== 0) {
                return $vazio;
            }
            preg_match_all('/<page width="([\d.]+)" height="([\d.]+)"[^>]*>(.*?)<\/page>/s', $html, $paginas, PREG_SET_ORDER);
            $ancoras = [];
            $vistos = [];
            $recortes = [];
            foreach ($paginas as $indice => $pagina) {
                if (count($recortes) >= 8) {
                    break;
                }
                $numero = $indice + 1;
                $png = $dir . '/pagina-' . $numero . '.png';
                if (!is_file($png)) {
                    continue;
                }
                $palavras = $this->palavrasDaPagina($pagina[3]);
                foreach ($palavras as $palavra) {
                    if (!preg_match('/^\d+\.$/', $palavra['texto'])) {
                        continue;
                    }
                    $n = (int) $palavra['texto'];
                    if ($n < 1 || isset($vistos[$n])) {
                        continue;
                    }
                    $vistos[$n] = true;
                    $ancoras[] = ['numero' => $n, 'pagina' => $numero, 'y' => $palavra['y0']];
                }
                foreach ($this->recortesDaPagina($png, $palavras, (float) $pagina[1], (float) $pagina[2]) as $recorte) {
                    if (count($recortes) >= 8) {
                        break;
                    }
                    $url = $this->publicarBytes($recorte['bytes'], 'png', 'image/png', $usuarioId, 'figura');
                    if ($url === null) {
                        continue;
                    }
                    $recortes[] = ['url' => $url, 'pagina' => $numero, 'y' => $recorte['y']];
                }
            }

            return ['recortes' => $recortes, 'ancoras' => $ancoras];
        } finally {
            foreach (glob($dir . '/*') ?: [] as $arquivo) {
                @unlink($arquivo);
            }
            @rmdir($dir);
        }
    }

    /**
     * @param list<array{x0:float,y0:float,x1:float,y1:float,texto:string}> $palavras
     * @return list<array{bytes:string,y:float}>
     */
    private function recortesDaPagina(string $png, array $palavras, float $larguraPt, float $alturaPt): array
    {
        $im = imagecreatefrompng($png);
        if ($im === false || $larguraPt <= 0 || $alturaPt <= 0) {
            return [];
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        $largura = imagesx($im);
        $altura = imagesy($im);
        $sx = $largura / $larguraPt;
        $sy = $altura / $alturaPt;
        $mascara = imagecreatetruecolor($largura, $altura);
        $preto = imagecolorallocate($mascara, 0, 0, 0);
        $branco = imagecolorallocate($mascara, 255, 255, 255);
        imagefilledrectangle($mascara, 0, 0, $largura, $altura, $preto);
        foreach ($palavras as $palavra) {
            imagefilledrectangle(
                $mascara,
                (int) max(0, ($palavra['x0'] - 1.5) * $sx),
                (int) max(0, ($palavra['y0'] - 1.5) * $sy),
                (int) min($largura - 1, ($palavra['x1'] + 1.5) * $sx),
                (int) min($altura - 1, ($palavra['y1'] + 1.5) * $sy),
                $branco
            );
        }
        $celula = 8;
        $tinta = [];
        for ($y = 0; $y < $altura; $y += 2) {
            for ($x = 0; $x < $largura; $x += 2) {
                if (imagecolorat($mascara, $x, $y) !== 0) {
                    continue;
                }
                $cor = imagecolorat($im, $x, $y);
                if ((($cor >> 16) & 255) > 245 && (($cor >> 8) & 255) > 245 && ($cor & 255) > 245) {
                    continue;
                }
                $tinta[(intdiv($x, $celula)) . ',' . intdiv($y, $celula)] = true;
            }
        }
        $grupos = $this->agruparCelulas(array_keys($tinta));
        $linhasLargas = $this->linhasLargas($palavras);
        $saida = [];
        foreach ($grupos as $grupo) {
            $xs = [];
            $ys = [];
            foreach ($grupo as $chave) {
                [$cx, $cy] = array_map('intval', explode(',', $chave));
                $xs[] = $cx;
                $ys[] = $cy;
            }
            $x0 = min($xs) * $celula / $sx;
            $y0 = min($ys) * $celula / $sy;
            $x1 = (max($xs) + 1) * $celula / $sx;
            $y1 = (max($ys) + 1) * $celula / $sy;
            if (($x1 - $x0) < 18 || ($y1 - $y0) < 12 || (($y1 - $y0) < 8 && ($x1 - $x0) > $larguraPt * 0.6)) {
                continue;
            }
            $caixa = [$x0, $y0, $x1, $y1];
            foreach ($palavras as $palavra) {
                $faixa = (int) round($palavra['y0'] / 3);
                if (isset($linhasLargas[$faixa]) || preg_match('/^\d+\.$/', $palavra['texto']) || preg_match('/^[A-E]\)$/', $palavra['texto'])) {
                    continue;
                }
                if ($palavra['x1'] < $x0 - 30 || $palavra['x0'] > $x1 + 24 || $palavra['y1'] < $y0 - 30 || $palavra['y0'] > $y1 + 16) {
                    continue;
                }
                $caixa[0] = min($caixa[0], $palavra['x0']);
                $caixa[1] = min($caixa[1], $palavra['y0']);
                $caixa[2] = max($caixa[2], $palavra['x1']);
                $caixa[3] = max($caixa[3], $palavra['y1']);
            }
            $px0 = (int) max(0, ($caixa[0] - 4) * $sx);
            $py0 = (int) max(0, ($caixa[1] - 4) * $sy);
            $px1 = (int) min($largura, ($caixa[2] + 4) * $sx);
            $py1 = (int) min($altura, ($caixa[3] + 4) * $sy);
            if ($px1 - $px0 < 40 || $py1 - $py0 < 24 || !$this->temDesenho($im, $px0, $py0, $px1, $py1)) {
                continue;
            }
            $corte = imagecrop($im, ['x' => $px0, 'y' => $py0, 'width' => $px1 - $px0, 'height' => $py1 - $py0]);
            if ($corte === false) {
                continue;
            }
            ob_start();
            imagepng($corte, null, 6);
            $bytes = ob_get_clean();
            if (!is_string($bytes) || $bytes === '') {
                continue;
            }
            $saida[] = ['bytes' => $bytes, 'y' => $y0];
        }

        return $saida;
    }

    private function temDesenho($im, int $x0, int $y0, int $x1, int $y1): bool
    {
        $amostras = 0;
        $coloridos = 0;
        for ($y = $y0; $y < $y1; $y += 2) {
            for ($x = $x0; $x < $x1; $x += 2) {
                $amostras++;
                $cor = imagecolorat($im, $x, $y);
                if ((($cor >> 16) & 255) < 210 || (($cor >> 8) & 255) < 210 || ($cor & 255) < 210) {
                    $coloridos++;
                }
            }
        }

        return $amostras > 0 && ($coloridos / $amostras) >= 0.08;
    }

    /**
     * @param list<string> $chaves
     * @return list<list<string>>
     */
    private function agruparCelulas(array $chaves): array
    {
        $conjunto = array_fill_keys($chaves, true);
        $pai = [];
        $encontrar = function (string $chave) use (&$pai, &$encontrar): string {
            if (!isset($pai[$chave])) {
                $pai[$chave] = $chave;
            }
            if ($pai[$chave] !== $chave) {
                $pai[$chave] = $encontrar($pai[$chave]);
            }

            return $pai[$chave];
        };
        foreach ($chaves as $chave) {
            [$cx, $cy] = array_map('intval', explode(',', $chave));
            for ($dx = -5; $dx <= 5; $dx++) {
                for ($dy = -5; $dy <= 5; $dy++) {
                    $vizinho = ($cx + $dx) . ',' . ($cy + $dy);
                    if (!isset($conjunto[$vizinho])) {
                        continue;
                    }
                    $raizA = $encontrar($chave);
                    $raizB = $encontrar($vizinho);
                    if ($raizA !== $raizB) {
                        $pai[$raizB] = $raizA;
                    }
                }
            }
        }
        $grupos = [];
        foreach ($chaves as $chave) {
            $grupos[$encontrar($chave)][] = $chave;
        }

        return array_values($grupos);
    }

    /**
     * @param list<array{x0:float,y0:float,x1:float,y1:float,texto:string}> $palavras
     * @return array<int,true>
     */
    private function linhasLargas(array $palavras): array
    {
        $linhas = [];
        foreach ($palavras as $palavra) {
            $faixa = (int) round($palavra['y0'] / 3);
            $linhas[$faixa][] = $palavra;
        }
        $largas = [];
        foreach ($linhas as $faixa => $grupo) {
            $temAlternativa = false;
            $x0 = $grupo[0]['x0'];
            $x1 = $grupo[0]['x1'];
            foreach ($grupo as $palavra) {
                $x0 = min($x0, $palavra['x0']);
                $x1 = max($x1, $palavra['x1']);
                if (preg_match('/^[A-E]\)$/', $palavra['texto'])) {
                    $temAlternativa = true;
                }
            }
            if ($temAlternativa || ($x1 - $x0) > 150) {
                $largas[$faixa] = true;
            }
        }

        return $largas;
    }

    /**
     * @return list<array{x0:float,y0:float,x1:float,y1:float,texto:string}>
     */
    private function palavrasDaPagina(string $html): array
    {
        preg_match_all('/<word xMin="([\d.]+)" yMin="([\d.]+)" xMax="([\d.]+)" yMax="([\d.]+)">(.*?)<\/word>/s', $html, $achados, PREG_SET_ORDER);
        $palavras = [];
        foreach ($achados as $achado) {
            $palavras[] = [
                'x0' => (float) $achado[1],
                'y0' => (float) $achado[2],
                'x1' => (float) $achado[3],
                'y1' => (float) $achado[4],
                'texto' => html_entity_decode(trim($achado[5]), ENT_QUOTES, 'UTF-8'),
            ];
        }

        return $palavras;
    }

    /**
     * @param array{url:string,pagina:int,y:float} $recorte
     * @param list<array{numero:int,pagina:int,y:float}> $ancoras
     */
    private function recorteDaQuestao(array $recorte, int $numero, array $ancoras): bool
    {
        $desta = null;
        foreach ($ancoras as $ancora) {
            if ($ancora['numero'] === $numero) {
                $desta = $ancora;
                break;
            }
        }
        if ($desta === null || $recorte['pagina'] !== $desta['pagina'] || $recorte['y'] < $desta['y'] - 8) {
            return false;
        }
        foreach ($ancoras as $ancora) {
            if ($ancora['pagina'] === $desta['pagina'] && $ancora['numero'] !== $numero && $ancora['y'] > $desta['y'] && $recorte['y'] >= $ancora['y'] - 4) {
                return false;
            }
        }

        return true;
    }

    private function temBinario(string $nome): bool
    {
        $codigo = 1;
        exec('command -v ' . escapeshellarg($nome) . ' >/dev/null 2>&1', $saida, $codigo);

        return $codigo === 0;
    }

    /**
     * @param list<string> $comando
     */
    private function executar(array $comando): string
    {
        $saida = '';
        $codigo = $this->executarCodigo($comando, $saida);

        return $codigo === 0 ? $saida : '';
    }

    /**
     * @param list<string> $comando
     */
    private function executarCodigo(array $comando, ?string &$saida = null): int
    {
        $proc = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($proc)) {
            $saida = '';

            return 1;
        }
        $saida = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return proc_close($proc);
    }

    private function ocrDoArquivo(string $caminho, string $mime): string
    {
        $bytes = file_get_contents($caminho);
        if ($bytes === false || $bytes === '') {
            return '';
        }
        if ($mime === 'application/pdf') {
            return '';
        }
        $openai = new OpenAIService();
        return trim((string) $openai->transcreverComGoogleVision(base64_encode($bytes), true));
    }

    /**
     * @return list<array{enunciado:string,tipo:string,valor:float,alternativas:list<array{texto:string,correta:int,ordem:int}>,explicacao:?string,figura:int,tem_figura:bool}>
     */
    private function estruturar(string $texto, int $quantidadeFiguras): array
    {
        $systemPrompt = 'Você extrai TODAS as questões de uma prova, na ordem, a partir do texto. '
            . 'Se houver gabarito, folha do professor ou resposta indicada, marque a alternativa correta. Não invente gabarito quando ele não estiver no texto. '
            . 'A página de gabarito não é uma questão nova. '
            . 'Devolva somente JSON: {"questoes":[{"enunciado":"texto puro sem alternativas","tipo":"multipla_escolha|verdadeiro_falso|dissertativa","valor":2,"gabarito":"B","explicacao":"resolução curta ou vazio","figura":0,"tem_figura":false,"alternativas":[{"texto":"..."}]}]}. '
            . 'gabarito é só a letra A, B, C, D ou E, ou string vazia quando o arquivo não trouxer a resposta. figura é o número da figura do arquivo (1 a ' . $quantidadeFiguras . ') que pertence à questão, ou 0. '
            . 'No máximo 40 questões.';
        $trecho = $this->trechoParaModelo($texto);
        $openai = new OpenAIService();
        $response = $openai->chatCompletion(
            [[
                'role' => 'user',
                'content' => "Extraia todas as questões desta prova. Há {$quantidadeFiguras} figura(s) no arquivo, na ordem em que aparecem.\n\n" . $trecho,
            ]],
            $systemPrompt,
            'gpt-4o',
            0.2,
            8000
        );
        $conteudo = (string) ($response['resposta'] ?? '');
        $json = $conteudo;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $conteudo, $bloco)) {
            $json = trim($bloco[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $conteudo, $bloco)) {
            $json = $bloco[0];
        } elseif (preg_match('/\[[\s\S]*\]/', $conteudo, $bloco)) {
            $json = $bloco[0];
        }
        $decodificado = json_decode($json, true);
        $brutas = [];
        if (isset($decodificado['questoes']) && is_array($decodificado['questoes'])) {
            $brutas = $decodificado['questoes'];
        } elseif (is_array($decodificado) && array_is_list($decodificado)) {
            $brutas = $decodificado;
        } elseif (is_array($decodificado) && isset($decodificado['enunciado'])) {
            $brutas = [$decodificado];
        }

        $lista = [];
        foreach ($brutas as $questao) {
            if (!is_array($questao) || count($lista) >= 40) {
                continue;
            }
            $normalizada = $this->normalizarQuestao($questao);
            if ($normalizada['enunciado'] === '') {
                continue;
            }
            $lista[] = $normalizada;
        }

        return $lista;
    }

    private function letraDoGabarito(string $valor): string
    {
        $valor = strtoupper(trim(preg_replace('/\s+/', ' ', $valor) ?? ''));
        if (preg_match('/^(?:ALTERNATIVA|LETRA|RESPOSTA|GABARITO)?\s*[:\-]?\s*([A-E])(?:[).:\-])?$/', $valor, $letra)) {
            return $letra[1];
        }

        return '';
    }

    /**
     * @param array<string,mixed> $questao
     * @return array{enunciado:string,tipo:string,valor:float,alternativas:list<array{texto:string,correta:int,ordem:int}>,explicacao:?string,figura:int,tem_figura:bool}
     */
    private function normalizarQuestao(array $questao): array
    {
        $tipo = (string) ($questao['tipo'] ?? 'multipla_escolha');
        if (!in_array($tipo, ['multipla_escolha', 'verdadeiro_falso', 'dissertativa'], true)) {
            $tipo = 'multipla_escolha';
        }
        $letraGabarito = $this->letraDoGabarito((string) ($questao['gabarito'] ?? ''));
        $alternativas = [];
        foreach ((array) ($questao['alternativas'] ?? []) as $alternativa) {
            $textoAlt = trim((string) (is_array($alternativa) ? ($alternativa['texto'] ?? '') : $alternativa));
            if ($textoAlt === '') {
                continue;
            }
            $ordem = count($alternativas);
            $letraAlt = chr(65 + $ordem);
            $correta = $letraGabarito !== '' && $letraAlt === $letraGabarito;
            $alternativas[] = [
                'texto' => $textoAlt,
                'correta' => $correta ? 1 : 0,
                'ordem' => $ordem,
            ];
            if (count($alternativas) >= 5) {
                break;
            }
        }
        $explicacao = trim(strip_tags((string) ($questao['explicacao'] ?? '')));
        if ($explicacao !== '') {
            $explicacao = mb_substr($explicacao, 0, 1500);
        }

        return [
            'enunciado' => trim((string) ($questao['enunciado'] ?? '')),
            'tipo' => $tipo,
            'valor' => is_numeric($questao['valor'] ?? null) && (float) $questao['valor'] > 0 ? (float) $questao['valor'] : 1.0,
            'alternativas' => $alternativas,
            'explicacao' => $explicacao !== '' ? $explicacao : null,
            'figura' => max(0, (int) ($questao['figura'] ?? 0)),
            'tem_figura' => !empty($questao['tem_figura']),
        ];
    }

    /**
     * Segunda leitura: o modelo vê o texto original e marca a alternativa que o arquivo indica,
     * seja por letra, pelo texto da opção ou por outro formato de gabarito.
     *
     * @param list<array{enunciado:string,tipo:string,valor:float,alternativas:list<array{texto:string,correta:int,ordem:int}>,explicacao:?string,figura:int,tem_figura:bool}> $questoes
     * @return list<array{enunciado:string,tipo:string,valor:float,alternativas:list<array{texto:string,correta:int,ordem:int}>,explicacao:?string,figura:int,tem_figura:bool}>
     */
    private function atribuirGabarito(string $texto, array $questoes): array
    {
        if ($questoes === []) {
            return $questoes;
        }
        $resumo = [];
        foreach ($questoes as $indice => $questao) {
            $alternativas = [];
            foreach ($questao['alternativas'] as $ordem => $alternativa) {
                $alternativas[] = chr(65 + $ordem) . ') ' . mb_substr((string) $alternativa['texto'], 0, 180);
            }
            $resumo[] = [
                'numero' => $indice + 1,
                'enunciado' => mb_substr((string) $questao['enunciado'], 0, 400),
                'alternativas' => $alternativas,
            ];
        }
        $enviado = $this->trechoParaModelo($texto);
        try {
            $openai = new OpenAIService();
            $response = $openai->chatCompletion(
                [[
                    'role' => 'user',
                    'content' => "Texto da prova:\n\n" . $enviado
                        . "\n\nQuestões já separadas:\n" . json_encode($resumo, JSON_UNESCAPED_UNICODE),
                ]],
                'Você localiza a resposta de cada questão somente se ela estiver escrita no texto da prova. '
                . 'O gabarito pode ser letra, o texto da alternativa, uma tabela, uma folha do professor ou outra forma. '
                . 'Não calcule e não invente. Se o texto não trouxer a resposta, deixe letra e resposta vazias. '
                . 'Devolva somente JSON: {"gabaritos":[{"numero":1,"letra":"B","resposta":"66","trecho":"cópia fiel do pedaço que prova a resposta"}]}. '
                . 'O trecho precisa existir no texto, citar o número da questão e a resposta (letra ao lado de gabarito, alternativa ou resposta, ou o texto da opção certa). Não copie a lista de alternativas da questão.',
                'gpt-4o',
                0.1,
                4000
            );
            $decodificado = $this->decodificarJson((string) ($response['resposta'] ?? ''));
        } catch (\Throwable $e) {
            return $questoes;
        }
        $mapa = [];
        foreach ((array) ($decodificado['gabaritos'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $numero = (int) ($item['numero'] ?? 0);
            if ($numero >= 1 && $numero <= count($questoes)) {
                $mapa[$numero - 1] = $item;
            }
        }
        foreach ($questoes as $indice => &$questao) {
            $item = $mapa[$indice] ?? null;
            if (!is_array($item)) {
                continue;
            }
            $escolhida = $this->indiceComprovado($questao['alternativas'], $indice + 1, $item, $texto);
            if ($escolhida === null) {
                continue;
            }
            foreach ($questao['alternativas'] as $ordem => &$alternativa) {
                $alternativa['correta'] = $ordem === $escolhida ? 1 : 0;
            }
            unset($alternativa);
        }
        unset($questao);

        return $questoes;
    }

    private function trechoParaModelo(string $texto): string
    {
        if (mb_strlen($texto) > 24000) {
            return mb_substr($texto, 0, 18000) . "\n\n[texto do meio omitido]\n\n" . mb_substr($texto, -6000);
        }

        return $texto;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodificarJson(string $conteudo): array
    {
        $json = $conteudo;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $conteudo, $bloco)) {
            $json = trim($bloco[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $conteudo, $bloco)) {
            $json = $bloco[0];
        } elseif (preg_match('/\[[\s\S]*\]/', $conteudo, $bloco)) {
            $json = $bloco[0];
        }
        $decodificado = json_decode($json, true);

        return is_array($decodificado) ? $decodificado : [];
    }

    private function textoContem(string $origem, string $trecho): bool
    {
        $trecho = $this->compactar($trecho);
        $origem = $this->compactar($origem);
        if (mb_strlen($trecho) < 8) {
            return false;
        }
        $posicao = 0;
        while (($posicao = mb_strpos($origem, $trecho, $posicao)) !== false) {
            $seguinte = mb_substr($origem, $posicao + mb_strlen($trecho), 1);
            if ($seguinte === '' || preg_match('/[\p{L}\p{N}]/u', $seguinte) !== 1) {
                return true;
            }
            $posicao += mb_strlen($trecho);
        }

        return false;
    }

    private function compactar(string $valor): string
    {
        $valor = mb_strtolower($valor);

        return trim(preg_replace('/\s+/u', ' ', $valor) ?? $valor);
    }

    /**
     * @param list<array{texto:string,correta:int,ordem:int}> $alternativas
     * @param array<string,mixed> $item
     */
    private function indiceComprovado(array $alternativas, int $numero, array $item, string $textoOriginal): ?int
    {
        $trecho = trim((string) ($item['trecho'] ?? ''));
        if (!$this->textoContem($textoOriginal, $trecho) || !$this->trechoTemNumero($trecho, $numero)) {
            return null;
        }
        $letra = $this->letraDoGabarito((string) ($item['letra'] ?? ''));
        $indiceLetra = null;
        if ($letra !== '' && $this->letraEhMarcadorDeGabarito($trecho, $numero, $letra)) {
            $posicao = ord($letra) - 65;
            $indiceLetra = isset($alternativas[$posicao]) ? $posicao : null;
        }
        $indiceTexto = $this->indicePeloTexto($alternativas, (string) ($item['resposta'] ?? ''));
        $textoNoTrecho = $indiceTexto !== null
            && $this->respostaEstaNoTrecho($trecho, (string) $alternativas[$indiceTexto]['texto']);
        if ($indiceLetra !== null && $textoNoTrecho && $indiceLetra !== $indiceTexto) {
            return null;
        }
        if ($indiceLetra !== null) {
            return $indiceLetra;
        }
        if ($textoNoTrecho && $this->contarAlternativasNoTrecho($alternativas, $trecho) === 1) {
            return $indiceTexto;
        }

        return null;
    }

    private function trechoTemNumero(string $trecho, int $numero): bool
    {
        return preg_match('/(?:^|\D)' . $numero . '(?:\D|$)/', $this->compactar($trecho)) === 1;
    }

    private function letraEhMarcadorDeGabarito(string $trecho, int $numero, string $letra): bool
    {
        $texto = mb_strtoupper($this->compactar($trecho));
        $fim = '(?!\s*\p{L})(?!\))';
        $padroes = [
            '/(?:ALTERNATIVA|LETRA|RESPOSTA|GABARITO)\s*[:\-]?\s*' . $letra . $fim . '/u',
            '/(?:QUESTAO|QUESTÃO)\s*' . $numero . '\s*[:.\-)]\s*' . $letra . $fim . '/u',
            '/(?:^|\s)' . $numero . '\s*[:.\-]\s*' . $letra . $fim . '/u',
            '/(?:^|\s)' . $numero . '\s+' . $letra . $fim . '/u',
        ];
        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    private function respostaEstaNoTrecho(string $trecho, string $textoAlternativa): bool
    {
        $alvo = $this->compactar($textoAlternativa);
        $base = $this->compactar($trecho);
        if (mb_strlen($alvo) < 2) {
            return false;
        }
        $posicao = 0;
        while (($posicao = mb_strpos($base, $alvo, $posicao)) !== false) {
            if ($this->fronteiraDeResposta($base, $posicao, mb_strlen($alvo))) {
                return true;
            }
            $posicao += mb_strlen($alvo);
        }

        return false;
    }

    private function fronteiraDeResposta(string $base, int $posicao, int $tamanho): bool
    {
        $anterior = $posicao === 0 ? '' : mb_substr($base, $posicao - 1, 1);
        $seguinte = mb_substr($base, $posicao + $tamanho, 1);
        if ($this->continuacaoNumerica($base, $posicao, $anterior, true) || $this->continuacaoNumerica($base, $posicao + $tamanho, $seguinte, false)) {
            return false;
        }
        $antesOk = $anterior === '' || preg_match('/[\p{L}\p{N}]/u', $anterior) !== 1;
        $depoisOk = $seguinte === '' || preg_match('/[\p{L}\p{N}]/u', $seguinte) !== 1;

        return $antesOk && $depoisOk;
    }

    private function continuacaoNumerica(string $base, int $posicaoDoSeparador, string $separador, bool $antes): bool
    {
        if ($separador !== '.' && $separador !== ',') {
            return false;
        }
        $vizinho = $antes
            ? ($posicaoDoSeparador < 2 ? '' : mb_substr($base, $posicaoDoSeparador - 2, 1))
            : mb_substr($base, $posicaoDoSeparador + 1, 1);

        return $vizinho !== '' && preg_match('/\p{N}/u', $vizinho) === 1;
    }

    /**
     * @param list<array{texto:string,correta:int,ordem:int}> $alternativas
     */
    private function contarAlternativasNoTrecho(array $alternativas, string $trecho): int
    {
        $total = 0;
        foreach ($alternativas as $alternativa) {
            if ($this->respostaEstaNoTrecho($trecho, (string) $alternativa['texto'])) {
                $total++;
            }
        }

        return $total;
    }

    /**
     * @param list<array{texto:string,correta:int,ordem:int}> $alternativas
     */
    private function indicePeloTexto(array $alternativas, string $resposta): ?int
    {
        $alvo = $this->normalizarComparacao($resposta);
        if (mb_strlen($alvo) < 2) {
            return null;
        }
        $achados = [];
        foreach ($alternativas as $indice => $alternativa) {
            $texto = $this->normalizarComparacao((string) $alternativa['texto']);
            if ($texto === '' || $texto !== $alvo) {
                continue;
            }
            $achados[] = $indice;
        }

        return count($achados) === 1 ? $achados[0] : null;
    }

    private function normalizarComparacao(string $valor): string
    {
        $valor = mb_strtolower($valor);
        $valor = str_replace(['r$', ' '], '', $valor);
        $valor = preg_replace('/[^a-z0-9\/,.]/u', '', $valor) ?? $valor;

        return $valor;
    }

    /**
     * @param list<string> $urls
     */
    private function montarEnunciado(string $texto, array $urls): string
    {
        $html = nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'));
        foreach ($urls as $url) {
            $segura = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $html .= '<p><img src="' . $segura . '" data-uploaded="1" alt="" style="max-width:100%;height:auto;"></p>';
        }
        return $html;
    }

    /**
     * @return array{ext:string,mime:string}|null
     */
    private function formatoImagem(string $bytes): ?array
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return ['ext' => 'jpg', 'mime' => 'image/jpeg'];
        }
        if (str_starts_with($bytes, "\x89PNG")) {
            return ['ext' => 'png', 'mime' => 'image/png'];
        }
        return null;
    }

    private function publicarArquivo(string $caminho, string $mime, int $usuarioId, string $prefixo): ?string
    {
        $mapa = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $ext = $mapa[$mime] ?? 'png';
        $bytes = file_get_contents($caminho);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        return $this->publicarBytes($bytes, $ext, $mime, $usuarioId, $prefixo);
    }

    private function publicarBytes(string $bytes, string $ext, string $mime, int $usuarioId, string $prefixo): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'questao_fig_');
        if ($tmp === false) {
            return null;
        }
        file_put_contents($tmp, $bytes);
        $nome = $prefixo . '_' . $usuarioId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $chave = MediaStorageService::userKey('teacher', $usuarioId, $nome);
        $config = $this->configuracao();
        $media = new MediaStorageService($config);
        $ok = $media->put('provas_questoes', $chave, $tmp, $mime);
        @unlink($tmp);
        if (!$ok) {
            return null;
        }
        $base = rtrim((string) ($config['app']['url'] ?? ''), '/');
        if ($base === '' && defined('URL')) {
            $base = rtrim((string) URL, '/');
        }
        return $base . '/media/serve?type=provas_questoes&key=' . rawurlencode($chave);
    }

    /**
     * @return array<string,mixed>
     */
    private function configuracao(): array
    {
        $arquivo = dirname(__DIR__, 4) . '/config/app.php';
        $config = [];
        if (is_file($arquivo)) {
            $carregado = require $arquivo;
            if (is_array($carregado)) {
                $config = $carregado;
            }
        }
        $slug = $this->slugLimpo($this->slugEscola);
        $config['tenant']['slug'] = $slug;
        $config['school']['code'] = $slug;
        return $config;
    }

    private function slugLimpo(string $slug): string
    {
        $limpo = strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $slug));
        if ($limpo === '') {
            throw new \RuntimeException('Escola da transcrição não identificada.');
        }
        return $limpo;
    }
}
