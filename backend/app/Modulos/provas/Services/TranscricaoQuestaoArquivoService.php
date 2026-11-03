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
     * @return array{enunciado:string,tipo:string,valor:float,alternativas:list<array{texto:string,correta:bool}>,outras:int}
     */
    public function transcrever(string $caminho, string $mime, int $usuarioId, string $slugEscola): array
    {
        $this->slugEscola = $this->slugLimpo($slugEscola);
        $figuras = [];
        $texto = '';
        $anexarOrigem = false;

        if ($mime === 'application/pdf') {
            [$texto, $figuras, $maiorFigura] = $this->lerPdf($caminho, $usuarioId);
            if (mb_strlen(trim($texto)) < 40 && $maiorFigura !== '') {
                $openai = new OpenAIService();
                $texto = trim((string) $openai->transcreverComGoogleVision(base64_encode($maiorFigura), true));
            }
        } else {
            $texto = $this->ocrDoArquivo($caminho, $mime);
            $anexarOrigem = true;
            $urlOrigem = $this->publicarArquivo($caminho, $mime, $usuarioId, 'origem');
            if ($urlOrigem !== null) {
                $figuras[] = $urlOrigem;
            }
        }

        $texto = trim($texto);
        if ($texto === '') {
            throw new \RuntimeException('Não foi possível ler o texto deste arquivo.');
        }

        $questao = $this->estruturar($texto);
        $urls = $figuras;
        if ($anexarOrigem && empty($questao['tem_figura'])) {
            array_pop($urls);
        }
        $questao['enunciado'] = $this->montarEnunciado((string) ($questao['enunciado'] ?? $texto), $urls);
        unset($questao['tem_figura']);

        return $questao;
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
            if (++$paginas > 4 || count($urls) >= 4) {
                break;
            }
            foreach ($pagina->getXObjects() as $objeto) {
                if (!$objeto instanceof Image || count($urls) >= 4) {
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
     * @return array{enunciado:string,tipo:string,valor:float,alternativas:list<array{texto:string,correta:bool}>,outras:int,tem_figura:bool}
     */
    private function estruturar(string $texto): array
    {
        $systemPrompt = 'Você extrai uma questão de prova a partir de texto transcrito. '
            . 'Devolva somente JSON com enunciado, tipo (multipla_escolha, verdadeiro_falso ou dissertativa), '
            . 'valor numérico, alternativas (texto e correta sempre false) e tem_figura (true se houver gráfico, mapa, figura ou tabela visual). '
            . 'outras é o número de questões além da primeira, se o texto tiver mais de uma. '
            . 'O enunciado é texto puro, sem HTML e sem as alternativas. Não invente gabarito.';
        $openai = new OpenAIService();
        $response = $openai->chatCompletion(
            [[
                'role' => 'user',
                'content' => "Extraia a primeira questão deste texto:\n\n" . mb_substr($texto, 0, 12000),
            ]],
            $systemPrompt,
            'gpt-4o',
            0.2,
            4000
        );
        $conteudo = (string) ($response['resposta'] ?? '');
        $json = $conteudo;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $conteudo, $bloco)) {
            $json = trim($bloco[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $conteudo, $bloco)) {
            $json = $bloco[0];
        }
        $questao = json_decode($json, true);
        if (!is_array($questao)) {
            $questao = [];
        }
        $tipo = (string) ($questao['tipo'] ?? 'multipla_escolha');
        if (!in_array($tipo, ['multipla_escolha', 'verdadeiro_falso', 'dissertativa'], true)) {
            $tipo = 'multipla_escolha';
        }
        $alternativas = [];
        foreach ((array) ($questao['alternativas'] ?? []) as $alternativa) {
            $textoAlt = trim((string) (is_array($alternativa) ? ($alternativa['texto'] ?? '') : $alternativa));
            if ($textoAlt === '') {
                continue;
            }
            $alternativas[] = ['texto' => $textoAlt, 'correta' => false];
            if (count($alternativas) >= 5) {
                break;
            }
        }
        $enunciado = trim((string) ($questao['enunciado'] ?? ''));
        if ($enunciado === '') {
            $enunciado = $texto;
        }

        return [
            'enunciado' => $enunciado,
            'tipo' => $tipo,
            'valor' => is_numeric($questao['valor'] ?? null) ? (float) $questao['valor'] : 1.0,
            'alternativas' => $alternativas,
            'outras' => max(0, (int) ($questao['outras'] ?? 0)),
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
