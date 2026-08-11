<?php
/**
 * Serviço de integração com OneSignal (notificações push)
 * Documentação: https://documentation.onesignal.com/reference/create-notification
 */
require_once __DIR__ . '/../Core/Logger.php';

class OneSignalService
{
    private $appId;
    private $restApiKey;
    private static $baseUrl = 'https://api.onesignal.com/notifications';

    public function __construct()
    {
        // Prioridade: Dev Settings (layout_config) > .env (env()) > getenv() > constantes
        $appId = '';
        $restKey = '';
        if (!class_exists('LayoutHelper')) {
            $layoutHelperPath = __DIR__ . '/../Core/LayoutHelper.php';
            if (file_exists($layoutHelperPath)) {
                require_once $layoutHelperPath;
            }
        }
        if (class_exists('LayoutHelper')) {
            $appId = trim(LayoutHelper::get('onesignal_app_id', ''));
            $restKey = trim(LayoutHelper::get('onesignal_rest_api_key', ''));
        }
        if ($appId === '') {
            $appId = (function_exists('env') ? (env('ONESIGNAL_APP_ID', '') ?: '') : '')
                ?: getenv('ONESIGNAL_APP_ID')
                ?: (defined('ONESIGNAL_APP_ID') ? ONESIGNAL_APP_ID : '');
        }
        if ($restKey === '') {
            $restKey = (function_exists('env') ? (env('ONESIGNAL_REST_API_KEY', '') ?: '') : '')
                ?: getenv('ONESIGNAL_REST_API_KEY')
                ?: (defined('ONESIGNAL_REST_API_KEY') ? ONESIGNAL_REST_API_KEY : '');
        }
        $this->appId = $appId;
        $this->restApiKey = $restKey;
    }

    /**
     * Verifica se o serviço está configurado
     */
    public function isConfigured()
    {
        return !empty($this->appId) && !empty($this->restApiKey);
    }

    /**
     * Envia notificação push usando filtros (tags)
     *
     * @param string $titulo
     * @param string $mensagem
     * @param string|null $url URL para abrir ao clicar
     * @param array $filters Filtros OneSignal, ex: [["field"=>"tag","key"=>"role","relation"=>"=","value"=>"aluno"]]
     * @param array $data Dados extras no payload (ex: tracking_token, notificacao_id)
     * @return array ['success' => bool, 'id' => string|null, 'error' => string|null]
     */
    public function send($titulo, $mensagem, $url = null, array $filters = [], array $data = [])
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'id' => null, 'error' => 'OneSignal não configurado (ONESIGNAL_APP_ID / ONESIGNAL_REST_API_KEY)'];
        }

        $payload = [
            'app_id' => $this->appId,
            'contents' => ['en' => $mensagem],
            'headings' => ['en' => $titulo],
            'data' => $data
        ];

        if ($url) {
            $payload['url'] = $url;
        }

        if (!empty($filters)) {
            $payload['filters'] = $filters;
        }

        return $this->request($payload);
    }

    /**
     * Envia notificação push para um usuário específico (external_user_id = usuarios.id).
     * Usado para tracking individual (cada push leva data.tracking_token).
     *
     * @param string $titulo
     * @param string $mensagem
     * @param string|null $url
     * @param array $data Dados no payload (tracking_token, notificacao_id)
     * @param int $userId usuarios.id (external_id no OneSignal)
     * @return array ['success' => bool, 'id' => string|null, 'error' => string|null]
     */
    public function sendToUser($titulo, $mensagem, $url = null, array $data = [], $userId = null)
    {
        if (!$this->isConfigured()) {
            Logger::warning('OneSignal: não configurado (sendToUser)', ['user_id' => $userId], 'push');
            return ['success' => false, 'id' => null, 'error' => 'OneSignal não configurado'];
        }
        if ($userId === null || $userId === '') {
            Logger::warning('OneSignal: userId vazio em sendToUser', [], 'push');
            return ['success' => false, 'id' => null, 'error' => 'userId obrigatório'];
        }

        $payload = [
            'app_id' => $this->appId,
            'contents' => ['en' => $mensagem],
            'headings' => ['en' => $titulo],
            'data' => $data,
            'target_channel' => 'push',
            'include_aliases' => [
                'external_id' => [ (string) $userId ]
            ]
        ];

        if ($url) {
            $payload['url'] = $url;
        }

        $result = $this->request($payload);
        if (!empty($result['success'])) {
            $osId = $result['id'] ?? null;
            if ($osId === null || $osId === '') {
                Logger::warning('OneSignal: onesignal_id vazio – pode não haver dispositivo inscrito com esse external_id (user_id)', ['user_id' => $userId], 'push');
            }
        } else {
            Logger::warning('OneSignal: envio falhou', ['user_id' => $userId, 'error' => $result['error'] ?? 'desconhecido'], 'push');
        }
        return $result;
    }

    /**
     * Executa POST para a API do OneSignal
     */
    private function request(array $payload)
    {
        $ch = curl_init(self::$baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $this->restApiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error('OneSignal: erro cURL', ['error' => $curlError], 'push');
            return ['success' => false, 'id' => null, 'error' => $curlError];
        }

        $body = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $id = $body['id'] ?? null;
            return ['success' => true, 'id' => $id, 'error' => null];
        }

        $errorMsg = $body['errors'][0] ?? $response ?? 'Erro desconhecido';
        $errorStr = is_array($errorMsg) ? json_encode($errorMsg) : (is_string($errorMsg) ? $errorMsg : 'Erro desconhecido');
        Logger::error('OneSignal: API retornou erro', [
            'http_code' => $httpCode,
            'error' => $errorStr,
            'response_preview' => is_string($response) ? substr($response, 0, 500) : null
        ], 'push');
        return ['success' => false, 'id' => null, 'error' => $errorStr];
    }

    /**
     * Monta filtros para enviar a todos de um role (ex: todos os pais)
     */
    public static function filtersPorRole($role)
    {
        return [
            ['field' => 'tag', 'key' => 'role', 'relation' => '=', 'value' => $role]
        ];
    }

    /**
     * Monta filtros para turma (alunos da turma ou pais dos alunos da turma)
     * tag turmas deve conter o nome da turma (ex: "7A")
     */
    public static function filtersPorTurma($turmaNomeOuId, $role = 'aluno')
    {
        return [
            ['field' => 'tag', 'key' => 'role', 'relation' => '=', 'value' => $role],
            ['operator' => 'AND'],
            ['field' => 'tag', 'key' => 'turmas', 'relation' => 'contains', 'value' => (string) $turmaNomeOuId]
        ];
    }

    /**
     * Monta filtro para usuário específico (user_id da tabela usuarios)
     */
    public static function filtersPorUsuario($userId)
    {
        return [
            ['field' => 'tag', 'key' => 'user_id', 'relation' => '=', 'value' => (string) $userId]
        ];
    }
}
