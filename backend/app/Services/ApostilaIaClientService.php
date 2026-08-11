<?php
/**
 * EducaTudo - Cliente HTTP para o microserviço Python "IA da Apostila".
 * O microserviço (FastAPI, fora deste repositório PHP) processa PDFs de apostila
 * (extração de texto/páginas, chunking, vector store OpenAI, detecção de exercícios)
 * e responde ao chat de professores sobre o conteúdo.
 *
 * Convenção de cliente HTTP seguida deste arquivo: cURL bruto, timeout explícito,
 * sem exceções "engolidas" — em caso de erro de conexão ou status não-2xx, lança
 * ApostilaIaClientException com o corpo/erro da resposta anexado (ver
 * App\Services\Asaas\AsaasApiClient para o padrão equivalente neste codebase).
 */

require_once __DIR__ . '/ApostilaIaClientException.php';

class ApostilaIaClientService
{
    private string $baseUrl;
    private string $internalKey;

    public function __construct(?string $baseUrl = null, ?string $internalKey = null)
    {
        $this->baseUrl = rtrim((string)($baseUrl ?? env('APOSTILA_AI_URL', 'http://apostila-ai:8088')), '/');
        $this->internalKey = (string)($internalKey ?? env('APOSTILA_AI_INTERNAL_KEY', ''));
    }

    /**
     * Dispara o processamento do PDF no microserviço Python (fire-and-forget).
     * O Python responde imediatamente com status "processando" e executa o
     * trabalho pesado em background — timeout curto (15s) basta para acionar.
     *
     * @return array<string,mixed>
     */
    public function processar(int $apostilaId, string $pdfPath, string $titulo): array
    {
        return $this->request(
            'POST',
            "/apostilas/{$apostilaId}/processar",
            ['pdf_path' => $pdfPath, 'titulo' => $titulo],
            15
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function chat(int $apostilaId, ?int $professorId, string $pergunta, ?int $sessaoId = null): array
    {
        return $this->request(
            'POST',
            '/chat/apostila',
            [
                'apostila_id' => $apostilaId,
                'professor_id' => $professorId,
                'sessao_id' => $sessaoId,
                'pergunta' => $pergunta,
            ],
            30
        );
    }

    /**
     * Repassa o stream SSE do microserviço Python diretamente ao navegador.
     * A persistência da conversa fica a cargo do Python (rag_service).
     */
    public function emitChatStream(int $apostilaId, ?int $usuarioId, string $pergunta, ?int $sessaoId = null): void
    {
        $url = $this->baseUrl . '/chat/apostila/stream';
        $body = json_encode([
            'apostila_id' => $apostilaId,
            'professor_id' => $usuarioId,
            'sessao_id' => $sessaoId,
            'pergunta' => $pergunta,
        ], JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            throw new ApostilaIaClientException('Falha ao serializar payload do chat stream');
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $streamOk = true;
        $ch = curl_init($url);
        if ($ch === false) {
            echo "event: error\ndata: " . json_encode(['error' => 'Falha ao inicializar cURL'], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            return;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'X-Internal-Api-Key: ' . $this->internalKey,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$streamOk): int {
                if (preg_match('#HTTP/\d\.\d\s+(\d{3})#', $headerLine, $matches)) {
                    $code = (int) $matches[1];
                    if ($code < 200 || $code >= 300) {
                        $streamOk = false;
                    }
                }
                return strlen($headerLine);
            },
            CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use (&$streamOk): int {
                if (connection_aborted()) {
                    return -1;
                }
                if (!$streamOk) {
                    return strlen($chunk);
                }
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($ok === false || $curlErrno !== 0) {
            echo "event: error\ndata: " . json_encode(
                ['error' => 'Falha de conexão com o serviço IA da Apostila: ' . $curlError],
                JSON_UNESCAPED_UNICODE
            ) . "\n\n";
            flush();
            return;
        }

        if (!$streamOk || $httpCode < 200 || $httpCode >= 300) {
            echo "event: error\ndata: " . json_encode(
                ['error' => 'Serviço IA da Apostila retornou HTTP ' . $httpCode . '. Confirme se o container apostila-ai foi atualizado e reiniciado.'],
                JSON_UNESCAPED_UNICODE
            ) . "\n\n";
            flush();
        }
    }

    /**
     * Busca a imagem extraída de uma página (figura/diagrama de um exercício,
     * quando existir). Retorna null se a página não tiver imagem (404) — não
     * é um erro, só significa "sem imagem para esta página".
     *
     * @return array{body: string, content_type: string}|null
     */
    public function buscarImagemPagina(int $apostilaId, int $numeroPagina): ?array
    {
        return $this->buscarArquivoBruto("/apostilas/{$apostilaId}/paginas/{$numeroPagina}/imagem");
    }

    /**
     * Busca a capa da apostila (página 1 renderizada inteira). Retorna null
     * se ainda não existir (apostila processada antes deste recurso existir,
     * ou falha pontual ao gerar) — não é erro, o front-end mostra um ícone
     * padrão nesse caso.
     *
     * @return array{body: string, content_type: string}|null
     */
    public function buscarCapa(int $apostilaId): ?array
    {
        return $this->buscarArquivoBruto("/apostilas/{$apostilaId}/capa");
    }

    /**
     * Busca o PDF original da apostila (botão "Ver Apostila"). Usado como
     * fallback quando o volume de uploads não é compartilhado entre PHP e o
     * microserviço Python. Retorna null se o serviço responder 404.
     *
     * @return array{body: string, content_type: string}|null
     */
    public function buscarPdf(int $apostilaId): ?array
    {
        return $this->buscarArquivoBruto("/apostilas/{$apostilaId}/pdf", 30);
    }

    /**
     * @return array{body: string, content_type: string}|null
     */
    private function buscarArquivoBruto(string $path, int $timeoutSeconds = 15): ?array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new ApostilaIaClientException("Falha ao inicializar cURL para {$url}");
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-Internal-Api-Key: ' . $this->internalKey],
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlErrno !== 0) {
            throw new ApostilaIaClientException(
                "Falha de conexão ao buscar arquivo ({$path}): {$curlError}"
            );
        }

        if ($httpCode === 404) {
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new ApostilaIaClientException(
                "Serviço IA da Apostila retornou HTTP {$httpCode} ao buscar arquivo ({$path})",
                $httpCode
            );
        }

        return [
            'body' => (string) $body,
            'content_type' => $contentType !== '' ? $contentType : 'application/octet-stream',
        ];
    }

    /**
     * @param array<string,mixed> $filtros Apenas chaves 'tema', 'pagina', 'tipo' são consideradas.
     * @return array<string,mixed>
     */
    public function listarExercicios(int $apostilaId, array $filtros = []): array
    {
        $query = [];
        foreach (['tema', 'pagina', 'tipo'] as $key) {
            if (isset($filtros[$key]) && $filtros[$key] !== '' && $filtros[$key] !== null) {
                $query[$key] = $filtros[$key];
            }
        }

        $path = "/apostilas/{$apostilaId}/exercicios";
        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        return $this->request('GET', $path, null, 20);
    }

    /**
     * @return array<string,mixed>
     */
    public function gerarProva(
        int $apostilaId,
        int $professorId,
        string $tema,
        int $quantidade,
        string $nivel,
        bool $incluirGabarito
    ): array {
        return $this->request(
            'POST',
            "/apostilas/{$apostilaId}/gerar-prova",
            [
                'professor_id' => $professorId,
                'tema' => $tema,
                'quantidade' => $quantidade,
                'nivel' => $nivel,
                'incluir_gabarito' => $incluirGabarito,
            ],
            60
        );
    }

    /**
     * Busca o conteúdo da apostila relevante para um capítulo/tema (via RAG) e
     * já devolve um roteiro de slides em Markdown sugerido (`conteudo_sugerido`),
     * pronto para ser usado como `conteudo` na geração de slides via Gamma
     * (Integrations/SlidesController, já existente — esta chamada NÃO gera
     * slides, só prepara o texto-base grounded no material da apostila).
     *
     * @return array<string,mixed> {conteudo_sugerido: string, paginas_usadas: int[]}
     */
    public function buscarContextoParaSlides(int $apostilaId, string $capituloOuTema, int $numSlides = 8): array
    {
        return $this->request(
            'POST',
            "/apostilas/{$apostilaId}/contexto",
            [
                'capitulo_ou_tema' => $capituloOuTema,
                'num_slides' => $numSlides,
            ],
            30
        );
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     * @throws ApostilaIaClientException
     */
    private function request(string $method, string $path, ?array $body, int $timeoutSeconds): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $ch = curl_init($url);
        if ($ch === false) {
            throw new ApostilaIaClientException("Falha ao inicializar cURL para {$url}");
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Internal-Api-Key: ' . $this->internalKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlErrno !== 0) {
            throw new ApostilaIaClientException(
                "Falha de conexão com o serviço IA da Apostila ({$method} {$path}): {$curlError}",
                $httpCode
            );
        }

        $decoded = json_decode((string)$response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorBody = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : (string)$response;
            throw new ApostilaIaClientException(
                "Serviço IA da Apostila retornou HTTP {$httpCode} em {$method} {$path}: {$errorBody}",
                $httpCode
            );
        }

        if (!is_array($decoded)) {
            throw new ApostilaIaClientException(
                "Resposta inválida (não-JSON) do serviço IA da Apostila em {$method} {$path}: " . (string)$response,
                $httpCode
            );
        }

        return $decoded;
    }
}
