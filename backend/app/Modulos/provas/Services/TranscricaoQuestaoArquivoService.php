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

        $extraidas = $this->estruturar($texto, count($figuras));
        $lista = [];
        foreach ($extraidas as $questao) {
            $indice = (int) ($questao['figura'] ?? 0);
            $urls = [];
            if ($indice >= 1 && isset($figuras[$indice - 1])) {
                $urls[] = $figuras[$indice - 1];
            } elseif (count($extraidas) === 1 && count($figuras) === 1 && !empty($questao['tem_figura'])) {
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
        $trecho = $texto;
        if (mb_strlen($texto) > 24000) {
            $trecho = mb_substr($texto, 0, 18000) . "\n\n[texto do meio omitido]\n\n" . mb_substr($texto, -6000);
        }
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
