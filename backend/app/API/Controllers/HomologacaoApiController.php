<?php

namespace App\API\Controllers;

use App\API\Services\HomologacaoApiService;
use App\Core\BaseController;

/**
 * Sandbox da API do responsável. Só responde com APP_ENV=development.
 */
class HomologacaoApiController extends BaseController
{
    private HomologacaoApiService $servico;

    public function __construct()
    {
        parent::__construct();
        $this->servico = new HomologacaoApiService();
    }

    public function login(): void
    {
        if (!$this->liberar()) {
            return;
        }
        $corpo = $this->corpo();
        $resposta = $this->servico->login((string) ($corpo['email'] ?? ''), (string) ($corpo['password'] ?? ''));
        $this->json($resposta['body'], $resposta['status']);
    }

    public function renovar(): void
    {
        if (!$this->liberar()) {
            return;
        }
        $corpo = $this->corpo();
        $resposta = $this->servico->renovar((string) ($corpo['refresh_token'] ?? ''));
        $this->json($resposta['body'], $resposta['status']);
    }

    public function sair(): void
    {
        if (!$this->exigirSessao()) {
            return;
        }
        $resposta = $this->servico->sair();
        $this->json($resposta['body'], $resposta['status']);
    }

    public function eu(): void
    {
        if (!$this->exigirSessao()) {
            return;
        }
        $resposta = $this->servico->eu();
        $this->json($resposta['body'], $resposta['status']);
    }

    public function alunos(): void
    {
        if (!$this->exigirSessao()) {
            return;
        }
        $resposta = $this->servico->alunos();
        $this->json($resposta['body'], $resposta['status']);
    }

    public function painel($id): void { $this->porAluno($id, 'dashboard'); }
    public function provas($id): void { $this->porAluno($id, 'exams'); }
    public function exercicios($id): void { $this->porAluno($id, 'exercises'); }
    public function jornadas($id): void { $this->porAluno($id, 'journeys'); }
    public function planosAula($id): void { $this->porAluno($id, 'lesson_plans'); }
    public function jornadasRedacao($id): void { $this->porAluno($id, 'writing_journeys'); }
    public function redacoes($id): void { $this->porAluno($id, 'essays'); }
    public function avisos($id): void { $this->porAluno($id, 'notices'); }
    public function boletim($id): void { $this->porAluno($id, 'report_card'); }
    public function acessos($id): void { $this->porAluno($id, 'access_events'); }
    public function financeiro($id): void { $this->porAluno($id, 'finance'); }
    public function comunicados($id): void { $this->porAluno($id, 'communications'); }
    public function calendario($id): void { $this->porAluno($id, 'calendar'); }

    public function planoAula($id, $planId): void
    {
        $this->porAluno($id, 'lesson_plan', ['plan_id' => $planId]);
    }

    public function pagamento($id, $source, $invoiceId): void
    {
        $this->porAluno($id, 'payment', ['source' => $source, 'invoice_id' => $invoiceId]);
    }

    public function comunicado($id, $communicationId): void
    {
        $this->porAluno($id, 'communication', ['communication_id' => $communicationId]);
    }

    public function comunicadoLido($id, $communicationId): void
    {
        $this->porAluno($id, 'communication_read', ['communication_id' => $communicationId]);
    }

    public function comunicadoResposta($id, $communicationId): void
    {
        $corpo = $this->corpo();
        $this->porAluno($id, 'communication_reply', [
            'communication_id' => $communicationId,
            'message' => (string) ($corpo['message'] ?? ''),
        ]);
    }

    public function calendarioLido($id, $eventId): void
    {
        $this->porAluno($id, 'calendar_read', ['event_id' => $eventId]);
    }

    public function notificacoes(): void
    {
        if (!$this->exigirSessao()) {
            return;
        }
        $resposta = $this->servico->notificacoes();
        $this->json($resposta['body'], $resposta['status']);
    }

    public function notificacaoLida($id): void
    {
        if (!$this->exigirSessao()) {
            return;
        }
        $resposta = $this->servico->notificacaoLida($id);
        $this->json($resposta['body'], $resposta['status']);
    }

    /** @param array<string,mixed> $extra */
    private function porAluno($id, string $recurso, array $extra = []): void
    {
        if (!$this->exigirSessao()) {
            return;
        }
        $resposta = $this->servico->recursoAluno($recurso, $id, $extra);
        $this->json($resposta['body'], $resposta['status']);
    }

    private function liberar(): bool
    {
        if ($this->servico->disponivel()) {
            header('Cache-Control: no-store');
            return true;
        }
        header('Cache-Control: no-store');
        $this->json(['error' => ['code' => 'not_found', 'message' => 'Recurso não encontrado.']], 404);
        return false;
    }

    private function exigirSessao(): bool
    {
        if (!$this->liberar()) {
            return false;
        }
        if ($this->servico->tokenValido($this->bearer())) {
            return true;
        }
        $this->json(['error' => ['code' => 'unauthorized', 'message' => 'Token de acesso não informado ou inválido.']], 401);
        return false;
    }

    private function bearer(): string
    {
        $header = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }
        return '';
    }

    /** @return array<string,mixed> */
    private function corpo(): array
    {
        $bruto = file_get_contents('php://input');
        $dados = $bruto !== false ? json_decode($bruto, true) : null;
        return is_array($dados) ? $dados : [];
    }
}

if (!class_exists('HomologacaoApiController', false) && class_exists(__NAMESPACE__ . '\\HomologacaoApiController', false)) {
    class_alias(__NAMESPACE__ . '\\HomologacaoApiController', 'HomologacaoApiController');
}
