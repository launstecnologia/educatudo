<?php

require_once __DIR__ . '/../../Services/AvaCertificateService.php';

/**
 * EducaTudo - AVA: certificados de conclusão na área do aluno.
 */
if (!class_exists('AvaCertificateStudentController')) {
class AvaCertificateStudentController extends BaseController
{
    private $authManager;
    private AvaCertificateService $service;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirect('/');
        }
        $this->service = new AvaCertificateService($this->config);
    }

    private function alunoId(): int
    {
        $user = $this->authManager->getUser();
        return (int) ($user['id'] ?? 0);
    }

    public function index(): void
    {
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('student', 'student/ava/certificados', [
            'title' => 'Meus certificados - EducaTudo',
            'current_page' => 'cursos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'certificados' => $this->service->model()->listForStudent($this->alunoId()),
        ]);
    }

    /** Emite (se necessário) e exibe o PDF do certificado da disciplina. */
    public function generate(string $id): void
    {
        $disciplinaId = (int) $id;
        $check = $this->service->canIssueDiscipline($this->alunoId(), $disciplinaId);
        if (empty($check['ok'])) {
            $this->setFlashMessage((string) ($check['reason'] ?? 'Certificado indisponível.'), 'info');
            $this->redirect('/cursos/disciplina/' . $disciplinaId);
            return;
        }
        $cert = $this->service->issueDiscipline($this->alunoId(), $disciplinaId, $check['context']);
        if (empty($cert)) {
            $this->setFlashMessage('Não foi possível gerar o certificado.', 'error');
            $this->redirect('/cursos/disciplina/' . $disciplinaId);
            return;
        }
        $html = $this->service->renderHtml($cert);
        $filename = 'certificado_' . preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($cert['codigo'] ?? 'ava')) . '.pdf';
        $this->outputPdf($html, $filename);
    }

    private function outputPdf(string $html, string $filename): void
    {
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }
}
}
