<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Models/Enrollment/Enrollment.php';
require_once __DIR__ . '/../../Services/EnrollmentService.php';

use App\Models\Enrollment\Enrollment;
use App\Services\EnrollmentService;

if (!class_exists('EnrollmentPublicController')) {
class EnrollmentPublicController extends BaseController
{
    private EnrollmentService $service;
    private Enrollment $model;

    public function __construct()
    {
        parent::__construct();
        $this->service = new EnrollmentService();
        $this->model   = $this->service->getModel();
    }

    // ── Página pública do contrato (para o responsável assinar) ───────────────

    public function verContrato(string $token): void
    {
        $token      = preg_replace('/[^a-f0-9]/i', '', $token);
        $enrollment = $this->model->findByToken($token);

        if (!$enrollment) {
            $this->renderError('Link inválido ou expirado.');
            return;
        }

        if ($enrollment['status'] === 'confirmada' || $enrollment['status'] === 'enturmada') {
            $this->renderView('public/enrollment/contrato_assinado', [
                'enrollment' => $enrollment,
            ]);
            return;
        }

        if ($enrollment['status'] === 'cancelada' || $enrollment['status'] === 'abandonada') {
            $this->renderError('Esta matrícula foi ' . $enrollment['status'] . '.');
            return;
        }

        $escola = $this->service->getEscola();
        $this->renderView('public/enrollment/contrato', [
            'enrollment'  => $enrollment,
            'escola'      => $escola,
            'csrf_token'  => $this->generateCsrfToken(),
        ]);
    }

    // ── POST: confirma assinatura ─────────────────────────────────────────────

    public function assinar(string $token): void
    {
        $token = preg_replace('/[^a-f0-9]/i', '', $token);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->renderError('Token de segurança inválido. Recarregue a página.');
            return;
        }

        $enrollment = $this->model->findByToken($token);
        if (!$enrollment) {
            $this->renderError('Link inválido.');
            return;
        }

        if (!in_array($enrollment['status'], ['rascunho','aguardando_contrato','aguardando_assinatura'], true)) {
            $this->renderError('Este contrato já foi processado.');
            return;
        }

        $nomeAssinante = trim($_POST['nome_assinante'] ?? $enrollment['resp_nome']);
        if ($nomeAssinante === '') {
            $this->renderError('Por favor, informe seu nome para assinar.');
            return;
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->service->registrarAssinatura($enrollment, $ip, $nomeAssinante);

        $this->renderView('public/enrollment/contrato_assinado', [
            'enrollment' => $this->model->findById($enrollment['id']),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function renderView(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = __DIR__ . '/../../Views/' . $view . '.php';
        if (!file_exists($viewPath)) {
            echo '<p>View não encontrada: ' . htmlspecialchars($view) . '</p>';
            return;
        }
        // Layout público simples (sem sidebar admin)
        $escola = $data['escola'] ?? $this->service->getEscola();
        $nomeEscola = $escola['nome'] ?? (defined('TENANT_SLUG') ? TENANT_SLUG : 'EducaTudo');
        include __DIR__ . '/../../Views/layouts/public_enrollment.php';
    }

    private function renderError(string $msg): void
    {
        $error  = $msg;
        $escola = $this->service->getEscola();
        $nomeEscola = $escola['nome'] ?? 'EducaTudo';
        include __DIR__ . '/../../Views/layouts/public_enrollment.php';
    }
}
}
