<?php
/**
 * EducaTudo - Geração de imagens para questões de prova via IA
 *
 * Pipeline:
 *  - gráfico / infográfico → QuickChart (Chart.js server-side, gratuito, dados exatos)
 *  - geometria             → SVG via GPT-4o (chat/completions, texto)
 *  - diagrama / ilustração / mapa → Nano Banana 2 (Gemini) → fallback gpt-image-2
 */

class ExamImageService
{
    private $apiKey;
    private $config;
    private $mediaService;
    private $nanoBanana;
    private $logFile;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->apiKey = $this->resolveApiKey();

        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $this->logFile = $logDir . '/exam_images.log';

        require_once __DIR__ . '/MediaStorageService.php';
        $this->mediaService = new MediaStorageService($config);

        require_once __DIR__ . '/NanoBananaService.php';
        $this->nanoBanana = new \NanoBananaService();

        $this->imageLog("========== INIT ExamImageService ==========");
        $this->imageLog("OpenAI key: " . (empty($this->apiKey) ? 'NAO CONFIGURADA' : 'OK (' . substr($this->apiKey, -6) . ')'));
        $this->imageLog("NanoBanana: " . ($this->nanoBanana->isConfigured() ? 'CONFIGURADO' : 'NAO CONFIGURADO'));
    }

    private function imageLog(string $msg): void
    {
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($this->logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Processa todas as imagens de uma prova gerada pelo GPT-4o.
     * Recebe as questões com metadados visuais e retorna com URLs prontas.
     */
    public function processarImagens(array $questoes, array $params): array
    {
        $total = count($questoes);
        $comImagem = 0;
        foreach ($questoes as $q) {
            if (!empty($q['imagem']) && $q['imagem'] !== null) $comImagem++;
        }
        $this->imageLog("--- processarImagens: {$total} questões, {$comImagem} com imagem | materia={$params['materia']} serie={$params['serie']}");

        foreach ($questoes as &$questao) {
            if (empty($questao['imagem']) || $questao['imagem'] === null) {
                $questao['imagem'] = null;
                continue;
            }

            $imagem = $questao['imagem'];
            $tipo = $imagem['tipo'] ?? '';
            $numero = $questao['numero'] ?? '?';
            $url = null;

            $this->imageLog("Questão #{$numero} | tipo_imagem={$tipo} | descricao=" . mb_substr($imagem['descricao'] ?? '', 0, 100));

            try {
                switch ($tipo) {
                    case 'grafico':
                    case 'infografico':
                        if (!empty($imagem['dados_grafico'])) {
                            $this->imageLog("  -> QuickChart: gerando gráfico...");
                            $url = $this->gerarGraficoQuickChart($imagem['dados_grafico']);
                            $this->imageLog("  -> QuickChart: " . ($url ? "OK url={$url}" : "FALHOU"));
                        } else {
                            $this->imageLog("  -> QuickChart: SKIP (sem dados_grafico)");
                        }
                        break;

                    case 'geometria':
                        if (!empty($imagem['prompt_imagem'])) {
                            $this->imageLog("  -> SVG/GPT-4o: gerando geometria...");
                            $url = $this->gerarSVGGeometria($imagem['prompt_imagem']);
                            $this->imageLog("  -> SVG/GPT-4o: " . ($url ? "OK url={$url}" : "FALHOU"));
                        } else {
                            $this->imageLog("  -> SVG/GPT-4o: SKIP (sem prompt_imagem)");
                        }
                        break;

                    case 'diagrama':
                    case 'ilustracao':
                    case 'mapa':
                        if (!empty($imagem['prompt_imagem'])) {
                            if ($this->nanoBanana->isConfigured()) {
                                $this->imageLog("  -> NanoBanana: tentando... prompt=" . mb_substr($imagem['prompt_imagem'], 0, 120));
                                $url = $this->gerarImagemNanoBanana($imagem['prompt_imagem'], $params);
                                $this->imageLog("  -> NanoBanana: " . ($url ? "OK url={$url}" : "FALHOU (tentando fallback gpt-image-2)"));
                            } else {
                                $this->imageLog("  -> NanoBanana: SKIP (não configurado, direto para gpt-image-2)");
                            }
                            if (!$url) {
                                $this->imageLog("  -> gpt-image-2: gerando...");
                                $url = $this->gerarImagemGPT($imagem['prompt_imagem'], $params);
                                $this->imageLog("  -> gpt-image-2: " . ($url ? "OK url={$url}" : "FALHOU"));
                            }
                        } else {
                            $this->imageLog("  -> SKIP (sem prompt_imagem)");
                        }
                        break;

                    default:
                        $this->imageLog("  -> tipo desconhecido: '{$tipo}' — SKIP");
                        break;
                }
            } catch (\Exception $e) {
                $this->imageLog("  -> EXCEPTION: " . $e->getMessage());
                error_log("ExamImageService: erro na questão {$numero}: " . $e->getMessage());
            }

            $questao['imagem'] = [
                'url' => $url,
                'descricao' => $imagem['descricao'] ?? '',
                'tipo' => $tipo,
            ];

            $this->imageLog("Questão #{$numero} RESULTADO FINAL: " . ($url ? "url={$url}" : "SEM IMAGEM"));
        }

        $this->imageLog("--- processarImagens CONCLUÍDO ---");
        return $questoes;
    }

    /**
     * Gráficos via QuickChart (Chart.js server-side) — gratuito, precisão numérica.
     */
    private function gerarGraficoQuickChart(array $dados): ?string
    {
        $chartConfig = [
            'type' => $dados['chart_type'] ?? 'line',
            'data' => [
                'labels' => $dados['eixo_x']['valores'] ?? [],
                'datasets' => array_map(function ($ds) {
                    return [
                        'label' => $ds['label'] ?? '',
                        'data' => $ds['pontos'] ?? [],
                        'borderColor' => $ds['cor'] ?? '#3B82F6',
                        'backgroundColor' => ($ds['cor'] ?? '#3B82F6') . '20',
                        'fill' => false,
                        'tension' => 0.4,
                        'pointRadius' => 4,
                        'pointBackgroundColor' => $ds['cor'] ?? '#3B82F6',
                    ];
                }, $dados['datasets'] ?? []),
            ],
            'options' => [
                'responsive' => false,
                'plugins' => [
                    'title' => [
                        'display' => !empty($dados['titulo_grafico']),
                        'text' => $dados['titulo_grafico'] ?? '',
                        'font' => ['size' => 16],
                    ],
                ],
                'scales' => [
                    'x' => [
                        'title' => ['display' => true, 'text' => $dados['eixo_x']['label'] ?? 'x'],
                        'grid' => ['color' => '#e5e7eb'],
                    ],
                    'y' => [
                        'title' => ['display' => true, 'text' => $dados['eixo_y']['label'] ?? 'y'],
                        'min' => $dados['eixo_y']['min'] ?? null,
                        'max' => $dados['eixo_y']['max'] ?? null,
                        'grid' => ['color' => '#e5e7eb'],
                    ],
                ],
            ],
        ];

        $quickchartUrl = 'https://quickchart.io/chart?c='
            . urlencode(json_encode($chartConfig))
            . '&w=600&h=400&bkg=white&f=png';

        $ctx = stream_context_create(['http' => ['timeout' => 30]]);
        $imageData = @file_get_contents($quickchartUrl, false, $ctx);

        if ($imageData === false) {
            error_log("ExamImageService: QuickChart falhou para URL (truncada): " . substr($quickchartUrl, 0, 200));
            return null;
        }

        return $this->salvarImagem($imageData, 'grafico', 'png');
    }

    /**
     * Geometria via GPT-4o — gera SVG como texto (chat/completions).
     */
    private function gerarSVGGeometria(string $descricao): ?string
    {
        if (empty($this->apiKey)) {
            error_log("ExamImageService: API key não configurada para SVG");
            return null;
        }

        $prompt = "Gere APENAS código SVG puro para a seguinte figura geométrica educacional. "
            . "Sem markdown, sem explicação, sem ```.\n\n"
            . "DESCRIÇÃO: {$descricao}\n\n"
            . "REGRAS DO SVG:\n"
            . "- viewBox=\"0 0 500 500\"\n"
            . "- Fundo branco (rect fill=\"#ffffff\")\n"
            . "- Linhas em #333333, stroke-width 2\n"
            . "- Labels: font-family=\"Arial, sans-serif\", font-size 14px\n"
            . "- Vértices marcados com circles r=4 fill=\"#333\"\n"
            . "- Medidas e ângulos indicados quando relevante\n"
            . "- Estilo limpo de livro didático\n"
            . "- Comece com <svg e termine com </svg>";

        $payload = [
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 2000,
            'temperature' => 0.3,
        ];

        $response = $this->chamarOpenAI('https://api.openai.com/v1/chat/completions', $payload);
        if (!$response) {
            return null;
        }

        $svgCode = $response['choices'][0]['message']['content'] ?? null;
        if (!$svgCode || strpos($svgCode, '<svg') === false) {
            error_log("ExamImageService: GPT-4o não retornou SVG válido");
            return null;
        }

        $svgCode = preg_replace('/^```[a-z]*\n?/', '', $svgCode);
        $svgCode = preg_replace('/\n?```$/', '', $svgCode);
        $svgCode = trim($svgCode);

        return $this->salvarImagem($svgCode, 'geometria', 'svg');
    }

    /**
     * Imagens via gpt-image-2 (OpenAI Images API).
     */
    private function gerarImagemGPT(string $promptImagem, array $params): ?string
    {
        if (empty($this->apiKey)) {
            error_log("ExamImageService: API key não configurada para imagem");
            return null;
        }

        $promptCompleto = "Imagem EDUCACIONAL para prova de {$params['materia']}"
            . (!empty($params['serie']) ? ", série {$params['serie']}" : "") . ".\n\n"
            . "ESTILO OBRIGATÓRIO:\n"
            . "- Fundo branco limpo\n"
            . "- Estilo de livro didático moderno, técnico e didático\n"
            . "- Sem textos longos (apenas labels curtos, letras, números de medida)\n"
            . "- Cores sólidas e contrastantes\n"
            . "- Sem sombras decorativas, sem 3D realista — priorize clareza\n"
            . "- Não inclua textos de enunciado ou alternativas\n"
            . "- Não inclua marcas d'água\n\n"
            . "DESCRIÇÃO TÉCNICA:\n{$promptImagem}";

        $payload = [
            'model' => 'gpt-image-2',
            'prompt' => $promptCompleto,
            'n' => 1,
            'size' => '1024x1024',
            'quality' => 'medium',
        ];

        $response = $this->chamarOpenAI('https://api.openai.com/v1/images/generations', $payload, 120);
        if (!$response) {
            return null;
        }

        $b64 = $response['data'][0]['b64_json'] ?? null;
        if ($b64) {
            $imageData = base64_decode($b64, true);
        } else {
            $imageUrl = $response['data'][0]['url'] ?? null;
            if (!$imageUrl) {
                error_log("ExamImageService: gpt-image-2 não retornou imagem");
                return null;
            }
            $imageData = @file_get_contents($imageUrl);
        }

        if (!$imageData || strlen($imageData) === 0) {
            return null;
        }

        return $this->salvarImagem($imageData, 'img', 'png');
    }

    /**
     * Imagens via Nano Banana 2 (Google Gemini) — alternativa gratuita/barata ao gpt-image-2.
     */
    private function gerarImagemNanoBanana(string $promptImagem, array $params): ?string
    {
        $promptCompleto = "Crie uma imagem EDUCACIONAL para uma prova escolar.\n\n"
            . "Disciplina: {$params['materia']}\n"
            . (!empty($params['serie']) ? "Série: {$params['serie']}\n" : "")
            . "\nESTILO OBRIGATÓRIO:\n"
            . "- Fundo branco limpo\n"
            . "- Estilo de livro didático moderno, técnico e didático\n"
            . "- Sem textos longos (apenas labels curtos)\n"
            . "- Cores sólidas e contrastantes\n"
            . "- Não inclua textos de enunciado ou alternativas\n"
            . "- Não inclua marcas d'água\n\n"
            . "O QUE GERAR:\n{$promptImagem}";

        try {
            $resultado = $this->nanoBanana->gerarImagem($promptCompleto);

            if (!$resultado || !$resultado['imagem_bytes']) {
                return null;
            }

            $ext = 'png';
            if (!empty($resultado['mime_type'])) {
                $map = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
                $ext = $map[$resultado['mime_type']] ?? 'png';
            }

            return $this->salvarImagem($resultado['imagem_bytes'], 'nb', $ext);
        } catch (\Exception $e) {
            error_log("ExamImageService: NanoBanana erro: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Salva imagem no MediaStorageService (S3 ou local) e retorna URL.
     */
    private function salvarImagem(string $data, string $prefix, string $extension): ?string
    {
        $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $key = 'provas_ia/' . $filename;

        $this->imageLog("  [storage] Salvando: key={$key} | size=" . strlen($data) . " bytes | ext={$extension}");

        $tmpFile = tempnam(sys_get_temp_dir(), 'edu_img_');
        if ($tmpFile === false) {
            $this->imageLog("  [storage] ERRO: não criou arquivo temporário");
            return null;
        }

        file_put_contents($tmpFile, $data);

        $contentType = $extension === 'svg' ? 'image/svg+xml' : 'image/png';
        $ok = $this->mediaService->put('provas_questoes', $key, $tmpFile, $contentType);
        @unlink($tmpFile);

        if (!$ok) {
            $this->imageLog("  [storage] ERRO: MediaStorage->put falhou para key={$key}");
            return null;
        }

        // NUNCA usar MediaStorageService::getDisplayUrl() aqui pra S3: ela
        // devolve uma URL assinada com validade de 15 minutos
        // (MediaStorageService::getViewUrl, expiresIn padrão 900s), e essa
        // URL é persistida PERMANENTEMENTE em `imagem_url` no banco — passado
        // esse tempo a imagem para de carregar pro professor/aluno, mesmo
        // com tudo salvo corretamente. Usa sempre o proxy estável
        // /media/serve (mesma rota que já serve local + S3 sem expirar,
        // usada em MediaServeController::serve()).
        $baseUrl = rtrim((string) ($this->config['app']['url'] ?? ''), '/');
        $path = $baseUrl . '/media/serve?type=provas_questoes&key=' . rawurlencode($key);
        $this->imageLog("  [storage] OK via serve path (estável, não expira): {$path}");
        return $path;
    }

    /**
     * Chamada HTTP genérica para a API da OpenAI.
     */
    private function chamarOpenAI(string $url, array $payload, int $timeout = 60): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("ExamImageService: cURL error: {$curlError}");
            return null;
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $msg = is_array($data) && isset($data['error']['message'])
                ? $data['error']['message']
                : "HTTP {$httpCode}";
            error_log("ExamImageService: OpenAI API error: {$msg}");
            return null;
        }

        return $data;
    }

    /**
     * Resolve a API key do .env ou config.
     */
    private function resolveApiKey(): string
    {
        $key = getenv('OPENAI_API_KEY');
        if ($key !== false && $key !== null && trim($key) !== '') {
            return trim($key);
        }

        $envPath = defined('ENV_FILE_PATH') ? ENV_FILE_PATH : (__DIR__ . '/../../.env');
        $paths = [$envPath, __DIR__ . '/../../.env'];
        foreach ($paths as $p) {
            if (!is_file($p)) continue;
            $lines = @file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) continue;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, 'OPENAI_API_KEY=') !== 0) continue;
                $value = trim(substr($line, strlen('OPENAI_API_KEY=')));
                if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                    $value = substr($value, 1, -1);
                }
                if ($value !== '') return $value;
            }
        }

        return '';
    }
}
