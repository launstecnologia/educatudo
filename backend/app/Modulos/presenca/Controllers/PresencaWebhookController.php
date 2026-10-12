<?php

namespace App\Modulos\Presenca\Controllers;


use App\Modulos\Presenca\Services\PresencaConectorGenerico;
use App\Modulos\Presenca\Services\PresencaEventoService;
use App\Modulos\Presenca\Models\PresencaIntegracao;

if (!class_exists(__NAMESPACE__ . '\\PresencaWebhookController', false)) {
class PresencaWebhookController extends \App\Core\BaseController
{
    public function receber($provedor = null): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'error' => 'Método não permitido'], 405);
            return;
        }
        if (!class_exists('LayoutHelper', false) || !\App\Core\LayoutHelper::isModuleEnabled('presenca')) {
            $this->json(['success' => false, 'error' => 'Módulo desabilitado'], 403);
            return;
        }

        $integracao = $this->autenticar();
        if (!$integracao) {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 401);
            return;
        }

        $raw = (string) file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $this->json(['success' => false, 'error' => 'JSON inválido'], 400);
            return;
        }

        $provedorRota = is_string($provedor) ? strtolower(trim($provedor)) : '';
        if ($provedorRota !== '' && $provedorRota !== (string) $integracao['provedor'] && $provedorRota !== 'generico') {
            $this->json(['success' => false, 'error' => 'Provedor não confere com a integração'], 422);
            return;
        }

        try {
            $normalizado = (new PresencaConectorGenerico())->normalizar($payload);
            $service = new PresencaEventoService();
            $resultado = $service->registrar([
                'aluno_id' => $normalizado['aluno_id'],
                'tipo' => $normalizado['tipo'],
                'ocorrido_em' => $normalizado['ocorrido_em'],
                'origem' => 'integracao',
                'id_externo' => 'wh:' . (int) $integracao['id'] . ':' . $normalizado['id_externo'],
                'integracao_id' => (int) $integracao['id'],
                'identificador_bruto' => $normalizado['identificador'] !== '' ? $normalizado['identificador'] : null,
                'mapeamento' => (string) ($integracao['mapeamento_identificador'] ?? 'ra'),
            ]);
            $this->json([
                'success' => true,
                'duplicado' => !empty($resultado['duplicado']),
                'evento_id' => $resultado['evento_id'],
                'aluno_id' => $resultado['aluno_id'],
                'aplicacao' => $resultado['aplicacao'],
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            (new PresencaIntegracao())->registrarErro((int) $integracao['id'], $e->getMessage());
            $this->json(['success' => false, 'error' => 'Falha ao processar evento'], 500);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function autenticar(): ?array
    {
        $header = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        $token = trim((string) ($_SERVER['HTTP_X_PRESENCA_TOKEN'] ?? ''));
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            $token = $m[1];
        }
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return null;
        }
        return (new PresencaIntegracao())->findByTokenHash(hash('sha256', strtolower($token)));
    }
}
}

if (!class_exists('PresencaWebhookController', false) && class_exists(__NAMESPACE__ . '\\PresencaWebhookController', false)) {
    class_alias(__NAMESPACE__ . '\\PresencaWebhookController', 'PresencaWebhookController');
}
