<?php
/**
 * EducaTudo - Integração Evolution API (WhatsApp)
 * Envia mensagens para um grupo via Evolution API (ex.: notificação de logs).
 *
 * Configuração (prioridade):
 *   1. Dev Settings (Admin → Chaves de API → WhatsApp – Evolution API)
 *   2. Variáveis no .env: EVOLUTION_API_URL, EVOLUTION_INSTANCE, EVOLUTION_GROUP_ID, EVOLUTION_API_KEY
 *
 * Se a API Key estiver vazia, o envio é desabilitado.
 */

class EvolutionApiService
{
    private static function getConfig()
    {
        $url = '';
        $instance = '';
        $groupId = '';
        $apikey = '';

        // 1. Dev Settings (layout_config) – prioridade máxima
        if (!class_exists('LayoutHelper') && file_exists(__DIR__ . '/../Core/LayoutHelper.php')) {
            require_once __DIR__ . '/../Core/LayoutHelper.php';
        }
        if (class_exists('LayoutHelper')) {
            $url = (string) LayoutHelper::get('evolution_api_url', '');
            $instance = (string) LayoutHelper::get('evolution_instance', '');
            $groupId = (string) LayoutHelper::get('evolution_group_id', '');
            $apikey = (string) LayoutHelper::get('evolution_api_key', '');
        }

        // 2. .env (env() ou leitura direta)
        if ($apikey === '' && function_exists('env')) {
            $apikey = (string) env('EVOLUTION_API_KEY', '');
        }
        if ($url === '' && function_exists('env')) {
            $url = (string) env('EVOLUTION_API_URL', '');
        }
        if ($instance === '' && function_exists('env')) {
            $instance = (string) env('EVOLUTION_INSTANCE', '');
        }
        if ($groupId === '' && function_exists('env')) {
            $groupId = (string) env('EVOLUTION_GROUP_ID', '');
        }

        if ($apikey === '' || $url === '') {
            $envPath = defined('ROOT_PATH') ? ROOT_PATH . '/.env' : __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                        continue;
                    }
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value, " \t\"'");
                    if ($name === 'EVOLUTION_API_URL' && $url === '') $url = $value;
                    if ($name === 'EVOLUTION_INSTANCE' && $instance === '') $instance = $value;
                    if ($name === 'EVOLUTION_GROUP_ID' && $groupId === '') $groupId = $value;
                    if ($name === 'EVOLUTION_API_KEY' && $apikey === '') $apikey = $value;
                }
            }
        }

        $baseUrl = $url !== '' ? rtrim($url, '/') : 'https://evolutionapi.launs.com.br';
        if (strpos($baseUrl, 'http://') === 0) {
            $baseUrl = 'https://' . substr($baseUrl, 7);
        }
        return [
            'url' => $baseUrl,
            'instance' => $instance !== '' ? $instance : 'Lucas_Moraes_Vivo',
            'group_id' => $groupId !== '' ? $groupId : '120363406502358415@g.us',
            'apikey' => $apikey,
        ];
    }

    /**
     * Registra falha do Evolution API no log (categoria evolution) para diagnóstico.
     */
    private static function logEvolutionError($message, $context = [])
    {
        if (class_exists('Logger')) {
            Logger::warning($message, $context, 'evolution');
        }
    }

    /**
     * Envia um texto para o grupo de logs no WhatsApp (Evolution API).
     * Não lança exceção; retorna false em caso de falha.
     * Em caso de falha, grava detalhes em storage/logs (categoria evolution).
     *
     * @param string $text Mensagem (será truncada se passar de ~1000 caracteres)
     * @param int $timeoutSeconds Timeout da requisição em segundos
     * @return bool true se enviado com sucesso, false caso contrário
     */
    public static function sendTextToLogGroup($text, $timeoutSeconds = 3)
    {
        $config = self::getConfig();
        if (empty($config['apikey'])) {
            // Evolution opcional: quando não há API key configurada, apenas não envia.
            return false;
        }

        $text = trim($text);
        if ($text === '') {
            self::logEvolutionError('Evolution API: envio não realizado – texto vazio.');
            return false;
        }

        // Limitar tamanho para evitar erro na API
        if (mb_strlen($text) > 1000) {
            $text = mb_substr($text, 0, 997) . '...';
        }

        $baseUrl = rtrim($config['url'], '/');
        // Usar sempre HTTPS para evitar 308 redirect: o PHP ao seguir redirect pode reenviar como GET e o servidor responde "Cannot GET"
        if (strpos($baseUrl, 'http://') === 0) {
            $baseUrl = 'https://' . substr($baseUrl, 7);
        }
        $instance = $config['instance'];
        $endpoint = $baseUrl . '/message/sendText/' . $instance;

        $payload = json_encode([
            'number' => $config['group_id'],
            'text' => $text,
        ], JSON_UNESCAPED_UNICODE);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' =>
                    "Content-Type: application/json\r\n" .
                    "apikey: " . $config['apikey'] . "\r\n",
                'content' => $payload,
                'timeout' => (float) $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        try {
            $response = @file_get_contents($endpoint, false, $ctx);
            $statusLine = (isset($http_response_header[0]) && is_array($http_response_header)) ? $http_response_header[0] : 'sem resposta';
            if ($response === false) {
                self::logEvolutionError('Evolution API: falha na requisição HTTP (sem corpo de resposta).', [
                    'endpoint' => $endpoint,
                    'status' => $statusLine,
                    'php_error' => error_get_last() ? (error_get_last()['message'] ?? '') : '',
                ]);
                return false;
            }
            $data = json_decode($response, true);
            $statusLine = isset($http_response_header[0]) ? $http_response_header[0] : '';
            $httpOk = (strpos($statusLine, '200') !== false || strpos($statusLine, '201') !== false);
            $ok = $httpOk && (
                isset($data['key']) ||
                (isset($data['status']) && $data['status'] !== 'error') ||
                isset($data['messageId']) ||
                isset($data['message']['key'])
            );
            if (!$ok) {
                self::logEvolutionError('Evolution API: resposta indicou erro.', [
                    'endpoint' => $endpoint,
                    'http_status' => $statusLine,
                    'response_body' => $response,
                    'decoded' => $data,
                ]);
            }
            return $ok;
        } catch (Throwable $e) {
            self::logEvolutionError('Evolution API: exceção ao enviar.', [
                'endpoint' => $endpoint,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
