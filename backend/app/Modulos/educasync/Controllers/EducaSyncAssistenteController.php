<?php

namespace App\Modulos\EducaSync\Controllers;

use App\Modulos\EducaSync\Models\Cenario;
use App\Modulos\EducaSync\Services\EducaSyncAssistenteService;
use App\Modulos\EducaSync\Services\EducaSyncPropostaService;
use App\Painel\Admin\Controllers\AdminBaseController;

/**
 * EducaSync — endpoints JSON do assistente (chat e confirmação de proposta).
 * O assistente nunca publica a grade; confirmar só aplica as alterações tipadas da proposta.
 */
class EducaSyncAssistenteController extends AdminBaseController
{
    private const PERMISSAO = 'educasync';

    public function mensagem($id): void
    {
        // Mesma chave das ações de cadastro do cenário (disponibilidade / gerar).
        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, 'cadastrar', true)) {
            return;
        }
        $corpo = $this->lerCorpo();
        $this->exigirCsrf($corpo);
        $cenarioId = $this->cenarioOuJson((int) $id);

        if (!EducaSyncAssistenteService::disponivel()) {
            $this->json(['success' => false, 'error' => 'Assistente indisponível. Ative TudiCoins para esta escola no Master.'], 403);
            return;
        }

        $historico = [];
        if (is_array($corpo['historico'] ?? null)) {
            foreach (array_slice($corpo['historico'], -10) as $linha) {
                if (is_array($linha)) {
                    $historico[] = ['role' => (string) ($linha['role'] ?? ''), 'content' => (string) ($linha['content'] ?? '')];
                }
            }
        }

        // Captura o usuário ANTES de liberar a sessão: AuthManager::isLoggedIn()
        // exige PHP_SESSION_ACTIVE; depois do write_close virava usuario_id=null
        // e a confirmação falhava com "proposta gerada por outro usuário".
        $usuarioId = $this->usuarioId();
        if ($usuarioId <= 0) {
            $this->json(['success' => false, 'error' => 'Sessão expirada. Recarregue a página e faça login novamente.'], 401);
            return;
        }

        // Chat síncrono: libera o lock de sessão para não travar o restante do painel.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $resultado = (new EducaSyncAssistenteService())->processarMensagem(
            $cenarioId,
            $usuarioId,
            (string) ($corpo['mensagem'] ?? ''),
            $historico
        );
        $this->json($resultado, !empty($resultado['success']) ? 200 : 400);
    }

    public function confirmarProposta($id): void
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, 'cadastrar', true)) {
            return;
        }
        $corpo = $this->lerCorpo();
        $this->exigirCsrf($corpo);
        $cenarioId = $this->cenarioOuJson((int) $id);

        $confirmar = $corpo['confirmar'] ?? '';
        if ($confirmar !== 1 && $confirmar !== '1' && $confirmar !== true) {
            $this->json(['success' => false, 'error' => 'Confirmação explícita obrigatória (confirmar=1).'], 422);
            return;
        }

        $resultado = (new EducaSyncPropostaService())->confirmar(
            $cenarioId,
            (int) ($corpo['comando_ia_id'] ?? 0),
            $this->usuarioId()
        );
        $this->json($resultado, !empty($resultado['success']) ? 200 : 422);
    }

    /**
     * JSON do corpo (fetch) ou $_POST (formulário).
     *
     * @return array<string,mixed>
     */
    private function lerCorpo(): array
    {
        $bruto = (string) file_get_contents('php://input');
        if ($bruto !== '' && strlen($bruto) <= 100000) {
            $decodificado = json_decode($bruto, true);
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }
        return $_POST;
    }

    /**
     * @param array<string,mixed> $corpo
     */
    private function exigirCsrf(array $corpo): void
    {
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($corpo['_token'] ?? ''));
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['success' => false, 'error' => 'Token inválido. Recarregue a página e tente novamente.'], 419);
        }
    }

    private function cenarioOuJson(int $id): int
    {
        if ($id <= 0 || !(new Cenario())->findById($id)) {
            $this->json(['success' => false, 'error' => 'Cenário não encontrado.'], 404);
        }
        return $id;
    }

    private function usuarioId(): int
    {
        return (int) (($this->auth->getUser() ?? [])['id'] ?? 0);
    }
}
