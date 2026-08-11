<?php

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Services/ComplianceService.php';
require_once __DIR__ . '/../../Services/PendencyService.php';
require_once __DIR__ . '/../../Services/ComplianceAIService.php';

/**
 * EducaTudo - ComplianceController
 * Painel de Conformidade Pedagógica, Central de Pendências e Modo Auditoria.
 */
if (!class_exists('ComplianceController')) {
class ComplianceController extends AdminBaseController
{
    public function dashboard(): void
    {
        if (!$this->enforceAdminPermissionKey('conformidade', 'visualizar', false)) {
            return;
        }
        $service = new ComplianceService($this->db);
        $this->viewWithLayout('admin', 'admin/conformidade/dashboard', [
            'title' => 'Painel de Conformidade - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'conformidade',
            'painel' => $service->dashboard(),
        ]);
    }

    public function pendencias(): void
    {
        if (!$this->enforceAdminPermissionKey('conformidade', 'visualizar', false)) {
            return;
        }
        $area = (string) ($_GET['area'] ?? '');
        if ($area !== '' && !isset(PendencyService::AREAS[$area])) {
            $area = '';
        }
        $service = new PendencyService($this->db);
        $pendencias = $service->listar($area);
        $this->viewWithLayout('admin', 'admin/conformidade/pendencias', [
            'title' => 'Central de Pendências - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'conformidade',
            'area' => $area,
            'areas' => PendencyService::AREAS,
            'pendencias' => $pendencias,
        ]);
    }

    public function auditoria(): void
    {
        if (!$this->enforceAdminPermissionKey('conformidade', 'visualizar', false)) {
            return;
        }
        $service = new ComplianceService($this->db);
        $this->viewWithLayout('admin', 'admin/conformidade/auditoria', [
            'title' => 'Modo Auditoria - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'conformidade',
            'painel' => $service->dashboard(),
        ]);
    }

    public function ia(): void
    {
        if (!$this->enforceAdminPermissionKey('conformidade', 'visualizar', false)) {
            return;
        }
        $this->viewWithLayout('admin', 'admin/conformidade/ia', [
            'title' => 'IA Auditora - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'conformidade',
            'sugestoes' => (new ComplianceAIService($this->db))->sugestoes(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function iaPerguntar(): void
    {
        if (!$this->enforceAdminPermissionKey('conformidade', 'visualizar', true)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->json(['error' => 'Sessão expirada.'], 419);
            return;
        }
        $pergunta = trim((string) ($_POST['pergunta'] ?? ''));
        if ($pergunta === '') {
            $this->json(['error' => 'Digite uma pergunta.'], 400);
            return;
        }
        $resposta = (new ComplianceAIService($this->db))->responder($pergunta);
        $this->json(['ok' => true] + $resposta);
    }
}
}
