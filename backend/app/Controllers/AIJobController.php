<?php

use App\Services\AIJobService;

/**
 * Endpoint de polling para jobs de IA assíncronos.
 * O frontend faz GET /ai-job/{id}/status a cada 2s até receber status 'done' ou 'failed'.
 */
class AIJobController
{
    public function status($id): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $jobId = (int) $id;
            if ($jobId <= 0) {
                http_response_code(400);
                $this->jsonResponse(['status' => 'invalid', 'error' => 'ID de job inválido']);
                return;
            }

            $db = \Database::getInstance();
            if (!$db->tableExists('ai_jobs')) {
                http_response_code(503);
                $this->jsonResponse([
                    'status' => 'failed',
                    'error'  => 'Fila de correção por IA não está configurada nesta escola. Contate o suporte.',
                ]);
                return;
            }

            $job = AIJobService::getJob($jobId);
            if (!$job) {
                $this->jsonResponse(['id' => $jobId, 'error' => 'Job não encontrado', 'status' => 'not_found']);
                return;
            }

            $currentUserId = $this->resolveCurrentUserId();
            $userType      = (string) ($_SESSION['user_type'] ?? $_SESSION['perfil'] ?? '');
            if ($userType === '' && class_exists('AuthManager')) {
                $authUser = (new \AuthManager())->getUser();
                $userType = (string) ($authUser['tipo'] ?? '');
            }
            $isPrivileged  = in_array($userType, ['admin', 'admin_escola', 'master', 'dev'], true);

            if ($currentUserId === null && !$isPrivileged) {
                http_response_code(401);
                $this->jsonResponse(['error' => 'Não autenticado', 'status' => 'forbidden']);
                return;
            }

            if (!$isPrivileged && (int) ($job['user_id'] ?? 0) !== (int) $currentUserId) {
                http_response_code(403);
                $this->jsonResponse(['error' => 'Acesso negado', 'status' => 'forbidden']);
                return;
            }

            $response = [
                'id'     => (int) $job['id'],
                'status' => (string) ($job['status'] ?? 'pending'),
                'created_at' => (string) ($job['created_at'] ?? ''),
                'completed_at' => (string) ($job['completed_at'] ?? ''),
            ];

            $decoded = json_decode((string) ($job['result'] ?? ''), true);
            if (is_array($decoded)) {
                if (!empty($decoded['iniciado_em'])) {
                    $response['iniciado_em'] = (string) $decoded['iniciado_em'];
                }
                if (!empty($decoded['finalizado_em'])) {
                    $response['finalizado_em'] = (string) $decoded['finalizado_em'];
                }
            }

            if (!empty($_GET['debug']) && $_GET['debug'] === '1') {
                $response['debug'] = [
                    'job_id' => $jobId,
                    'user_id_session' => $currentUserId,
                    'user_type' => $userType,
                    'job_user_id' => (int) ($job['user_id'] ?? 0),
                    'job_user_role' => (string) ($job['user_role'] ?? ''),
                    'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                ];
            }

            if ($job['status'] === 'done') {
                $response['result'] = is_array($decoded) ? $decoded : ['raw' => $job['result']];
            }

            if ($job['status'] === 'failed') {
                $response['error'] = (string) ($job['error_message'] ?? 'Falha ao processar.');
            }

            $this->jsonResponse($response);
        } catch (\Throwable $e) {
            if (class_exists('\Logger')) {
                \Logger::error('AIJobController::status falhou', [
                    'job_id' => $id ?? null,
                    'error'  => $e->getMessage(),
                ], 'ai_jobs');
            }

            http_response_code(500);
            $this->jsonResponse([
                'status' => 'error',
                'error'  => 'Erro ao consultar status do processamento.',
            ]);
        }
    }

    private function resolveCurrentUserId(): ?int
    {
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== '') {
            return (int) $_SESSION['user_id'];
        }

        if (class_exists('AuthManager')) {
            $auth = new \AuthManager();
            if ($auth->isLoggedIn()) {
                $user = $auth->getUser();
                if (!empty($user['id'])) {
                    return (int) $user['id'];
                }
            }
        }

        return null;
    }

    private function jsonResponse(array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = json_encode(['status' => 'error', 'error' => 'Falha ao serializar resposta.'], JSON_UNESCAPED_UNICODE);
        }
        echo $json;
    }
}
