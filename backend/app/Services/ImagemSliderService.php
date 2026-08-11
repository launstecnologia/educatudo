<?php

require_once __DIR__ . '/MediaStorageService.php';

class ImagemSliderService
{
    private const TIPO_MIDIA = 'dashboard_sliders';
    private const TAMANHO_MAXIMO = 10485760;
    private const LIMITE_COMPACTACAO = 1048576;
    private const LARGURA_MAXIMA = 1600;
    private const ALTURA_MAXIMA = 900;
    private const TOTAL_PIXELS_MAXIMO = 40000000;
    private const QUALIDADE_WEBP = 82;

    private MediaStorageService $media;
    private string $baseUrl;
    private string $tenantSlug;

    public function __construct(array $config)
    {
        $this->media = new MediaStorageService($config);
        $this->baseUrl = rtrim((string) ($config['app']['url'] ?? (defined('URL') ? URL : '')), '/');
        $this->tenantSlug = trim((string) ($config['tenant']['slug'] ?? $config['school']['code'] ?? ''));
    }

    /**
     * Salva a imagem original e, quando necessário, gera uma versão WebP para exibição.
     *
     * @return array{image_url:string, original_image_url:string, image_optimized:int}
     */
    public function salvar(array $arquivo): array
    {
        if ((int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('O upload da imagem não foi concluído.');
        }

        $caminhoTemporario = (string) ($arquivo['tmp_name'] ?? '');
        $tamanho = (int) ($arquivo['size'] ?? 0);
        if ($caminhoTemporario === '' || !is_file($caminhoTemporario) || $tamanho <= 0) {
            throw new InvalidArgumentException('O arquivo enviado é inválido.');
        }
        if (!is_uploaded_file($caminhoTemporario)) {
            throw new InvalidArgumentException('A origem do arquivo enviado não é válida.');
        }
        if ($tamanho > self::TAMANHO_MAXIMO) {
            throw new InvalidArgumentException('A imagem deve ter no máximo 10 MB.');
        }

        $mime = $this->detectarMime($caminhoTemporario);
        $extensoes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($extensoes[$mime])) {
            throw new InvalidArgumentException('Use uma imagem JPG, PNG, WebP ou GIF válida.');
        }

        $dimensoes = @getimagesize($caminhoTemporario);
        if (!is_array($dimensoes) || empty($dimensoes[0]) || empty($dimensoes[1])) {
            throw new InvalidArgumentException('Não foi possível ler as dimensões da imagem.');
        }
        if ((int) $dimensoes[0] * (int) $dimensoes[1] > self::TOTAL_PIXELS_MAXIMO) {
            throw new InvalidArgumentException('A resolução da imagem é muito alta.');
        }

        $identificador = date('Ymd_His') . '_' . bin2hex(random_bytes(6));
        $chaveOriginal = 'originais/slider_' . $identificador . '.' . $extensoes[$mime];
        if (!$this->media->put(self::TIPO_MIDIA, $chaveOriginal, $caminhoTemporario, $mime)) {
            throw new RuntimeException('Não foi possível salvar a imagem original.');
        }

        $urlOriginal = $this->montarUrl($chaveOriginal);
        $precisaCompactar = $tamanho > self::LIMITE_COMPACTACAO
            || (int) $dimensoes[0] > self::LARGURA_MAXIMA
            || (int) $dimensoes[1] > self::ALTURA_MAXIMA;

        if (!$precisaCompactar) {
            return [
                'image_url' => $urlOriginal,
                'original_image_url' => $urlOriginal,
                'image_optimized' => 0,
            ];
        }

        $compactada = $this->criarWebpCompactada(
            $caminhoTemporario,
            (int) $dimensoes[0],
            (int) $dimensoes[1]
        );
        if ($compactada === null) {
            return [
                'image_url' => $urlOriginal,
                'original_image_url' => $urlOriginal,
                'image_optimized' => 0,
            ];
        }

        $chaveCompactada = 'otimizadas/slider_' . $identificador . '.webp';
        try {
            $salvouCompactada = $this->media->put(self::TIPO_MIDIA, $chaveCompactada, $compactada, 'image/webp');
        } finally {
            @unlink($compactada);
        }

        return [
            'image_url' => $salvouCompactada ? $this->montarUrl($chaveCompactada) : $urlOriginal,
            'original_image_url' => $urlOriginal,
            'image_optimized' => $salvouCompactada ? 1 : 0,
        ];
    }

    private function detectarMime(string $caminho): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return (string) $finfo->file($caminho);
    }

    private function criarWebpCompactada(string $origem, int $largura, int $altura): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            return null;
        }

        $conteudo = @file_get_contents($origem);
        if ($conteudo === false) {
            return null;
        }
        $imagem = @imagecreatefromstring($conteudo);
        if ($imagem === false) {
            return null;
        }

        $escala = min(
            1,
            self::LARGURA_MAXIMA / $largura,
            self::ALTURA_MAXIMA / $altura
        );
        $novaLargura = max(1, (int) round($largura * $escala));
        $novaAltura = max(1, (int) round($altura * $escala));
        $destino = imagecreatetruecolor($novaLargura, $novaAltura);
        if ($destino === false) {
            imagedestroy($imagem);
            return null;
        }

        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        $transparente = imagecolorallocatealpha($destino, 0, 0, 0, 127);
        imagefilledrectangle($destino, 0, 0, $novaLargura, $novaAltura, $transparente);
        imagecopyresampled($destino, $imagem, 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura);

        $temporario = tempnam(sys_get_temp_dir(), 'slider_webp_');
        if ($temporario === false || !imagewebp($destino, $temporario, self::QUALIDADE_WEBP)) {
            if (is_string($temporario)) {
                @unlink($temporario);
            }
            imagedestroy($destino);
            imagedestroy($imagem);
            return null;
        }

        imagedestroy($destino);
        imagedestroy($imagem);
        return $temporario;
    }

    private function montarUrl(string $chave): string
    {
        $url = $this->baseUrl
            . '/media/serve?type=' . rawurlencode(self::TIPO_MIDIA)
            . '&key=' . rawurlencode($chave);
        if ($this->tenantSlug !== '' && $this->tenantSlug !== 'default') {
            $url .= '&tenant=' . rawurlencode($this->tenantSlug);
        }
        return $url;
    }
}
