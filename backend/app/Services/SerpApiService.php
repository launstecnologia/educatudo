<?php

namespace App\Services;

/**
 * EducaTudo - wrapper fino pra SerpApi (serpapi.com), usado pelo motor de
 * questões pra trazer referência bibliográfica REAL em vez da IA inventar
 * uma. Best-effort: falha de qualquer tipo (sem key, rede, sem resultado)
 * retorna array vazio, nunca lança exceção — é enriquecimento opcional, não
 * pode derrubar a geração de questão.
 */
class SerpApiService
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = $this->resolveApiKey();
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * @return array<int, array{titulo: string, link: string, snippet: string}>
     */
    public function buscarReferencias(string $query, int $limite = 3): array
    {
        if (!$this->isConfigured() || trim($query) === '') {
            return [];
        }

        try {
            $url = 'https://serpapi.com/search.json?' . http_build_query([
                'engine' => 'google_scholar',
                'q' => $query,
                'api_key' => $this->apiKey,
                'num' => max(1, min(10, $limite)),
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $httpCode !== 200) {
                return [];
            }

            $dados = json_decode($body, true);
            $resultados = $dados['organic_results'] ?? [];
            if (!is_array($resultados)) {
                return [];
            }

            $referencias = [];
            foreach (array_slice($resultados, 0, $limite) as $r) {
                $titulo = trim((string) ($r['title'] ?? ''));
                if ($titulo === '') {
                    continue;
                }
                $referencias[] = [
                    'titulo' => $titulo,
                    'link' => (string) ($r['link'] ?? ''),
                    'snippet' => trim((string) ($r['snippet'] ?? '')),
                ];
            }

            return $referencias;
        } catch (\Throwable $e) {
            error_log('SerpApiService: falha ao buscar referências: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mesmo padrão de 5 prioridades já usado em OpenAIService pra
     * OPENAI_API_KEY/ELEVENLABS_API_KEY.
     */
    private function resolveApiKey(): ?string
    {
        $apiKey = null;

        try {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            if (class_exists('Database', false)) {
                $db = \Database::getInstance();
                $config = $db->fetch(
                    "SELECT config_value FROM config_layout WHERE config_key = ?",
                    ['serpapi_api_key']
                );
                if ($config && !empty($config['config_value'])) {
                    $apiKey = trim($config['config_value']);
                }
            }
        } catch (\Exception $e) {
            error_log('Erro ao buscar SerpApi key do banco: ' . $e->getMessage());
        }

        if (empty($apiKey)) {
            try {
                $config = require __DIR__ . '/../../config/app.php';
                $apiKey = $config['ai']['serpapi_api_key'] ?? null;
            } catch (\Exception $e) {
                $apiKey = null;
            }
        }

        if (empty($apiKey)) {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $content = file_get_contents($envPath);
                if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                    $content = substr($content, 3);
                }
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'SERPAPI_KEY=') === 0) {
                        $apiKey = trim(substr($line, strlen('SERPAPI_KEY=')), " \t\r\n\"'");
                        break;
                    }
                }
            }
        }

        if (empty($apiKey)) {
            $apiKey = getenv('SERPAPI_KEY');
        }

        if (empty($apiKey)) {
            $apiKey = $_ENV['SERPAPI_KEY'] ?? null;
        }

        return $apiKey ?: null;
    }
}
