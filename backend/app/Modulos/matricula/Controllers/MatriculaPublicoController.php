<?php
/**
 * Trilha pública do responsável (contrato / assinatura).
 */

require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../Models/MatriculaProcesso.php';
require_once __DIR__ . '/../Services/MatriculaProcessoService.php';

use App\Modulos\Matricula\Models\MatriculaProcesso;
use App\Modulos\Matricula\Services\MatriculaProcessoService;

if (!class_exists('MatriculaPublicoController')) {
class MatriculaPublicoController extends BaseController
{
    private MatriculaProcessoService $service;
    private MatriculaProcesso $model;

    public function __construct()
    {
        parent::__construct();
        $this->service = new MatriculaProcessoService();
        $this->model = $this->service->getModel();
    }

    /**
     * Formulário público de interesse de matrícula (captação).
     */
    public function captacaoForm(): void
    {
        if (!$this->captacaoDisponivel()) {
            $this->renderError('Captação de matrícula indisponível no momento.');
            return;
        }

        $escola = $this->service->getEscola();
        $nomeEscola = $escola['nome'] ?? 'EducaTudo';

        // PRG: sucesso após POST (flash só é lido com ?ok=1)
        if (!empty($_GET['ok'])) {
            $flash = $this->getFlashMessage();
            if (($flash['type'] ?? '') === 'success') {
                $meta = is_array($flash['meta'] ?? null) ? $flash['meta'] : [];
                $this->renderModuloView('interesse_ok', [
                    'escola' => $escola,
                    'nomeEscola' => $nomeEscola,
                    'aluno_nome' => (string) ($meta['aluno_nome'] ?? ''),
                ]);
                return;
            }
        }

        $this->renderModuloView('interesse', $this->dadosFormularioCaptacao($escola, $nomeEscola, [], []));
    }

    /**
     * Grava interesse (processo status=rascunho, origem=site).
     */
    public function captacaoStore(): void
    {
        if (!$this->captacaoDisponivel()) {
            $this->renderError('Captação de matrícula indisponível no momento.');
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->renderError('Token de segurança inválido. Recarregue a página.');
            return;
        }

        $escola = $this->service->getEscola();
        $nomeEscola = $escola['nome'] ?? 'EducaTudo';

        try {
            $this->service->criarCaptacaoInteresse($_POST, $_FILES);
        } catch (\InvalidArgumentException $e) {
            $this->renderModuloView('interesse', $this->dadosFormularioCaptacao($escola, $nomeEscola, $_POST, [$e->getMessage()]));
            return;
        } catch (\Throwable $e) {
            error_log('[MatriculaPublico] captacaoStore: ' . $e->getMessage());
            $this->renderError('Não foi possível registrar seu interesse. Tente novamente em instantes.');
            return;
        }

        $this->setFlashMessage('interesse_ok', 'success', [
            'aluno_nome' => trim((string) ($_POST['aluno_nome'] ?? '')),
        ]);
        $this->redirect(URL . '/matricula/interesse?ok=1');
    }

    /** @param array<string,mixed> $escola */
    private function dadosFormularioCaptacao(array $escola, string $nomeEscola, array $old, array $erros): array
    {
        $anos = $this->service->getAnosLetivos();
        $anoAtivo = (int) ($old['ano_letivo_id'] ?? 0);
        if ($anoAtivo <= 0) {
            foreach ($anos as $al) {
                if (!empty($al['ativo'])) {
                    $anoAtivo = (int) $al['id'];
                    break;
                }
            }
        }

        $turmas = [];
        try {
            $turmas = $this->service->getTurmas();
        } catch (\Throwable $e) {
            $turmas = [];
        }

        return [
            'escola' => $escola,
            'nomeEscola' => $nomeEscola,
            'anos_letivos' => $anos,
            'turmas' => $turmas,
            'ano_letivo_padrao' => $anoAtivo,
            'csrf_token' => $this->generateCsrfToken(),
            'old' => $old,
            'erros' => $erros,
        ];
    }

    private function captacaoDisponivel(): bool
    {
        if (!$this->model->schemaReady()) {
            return false;
        }
        if (!class_exists('LayoutHelper', false)) {
            $helper = dirname(__DIR__, 3) . '/Core/LayoutHelper.php';
            if (is_file($helper)) {
                require_once $helper;
            }
        }
        if (class_exists('LayoutHelper', false) && !\LayoutHelper::isModuleEnabled('processo_matricula')) {
            return false;
        }
        return true;
    }

    public function verContrato(string $token): void
    {
        $token = preg_replace('/[^a-f0-9]/i', '', $token);
        $enrollment = $this->model->findByToken($token);

        if (!$enrollment) {
            $this->renderError('Link inválido ou expirado.');
            return;
        }

        if (in_array($enrollment['status'], ['confirmada', 'enturmada'], true)) {
            $this->renderModuloView('contrato_assinado', ['enrollment' => $enrollment]);
            return;
        }

        if (in_array($enrollment['status'], ['cancelada', 'abandonada'], true)) {
            $this->renderError('Esta matrícula foi ' . $enrollment['status'] . '.');
            return;
        }

        $etapa = $this->service->etapaTrilha($enrollment, $_GET['etapa'] ?? null);
        $escola = $this->service->getEscola();

        if ($etapa === 'dados') {
            $this->renderModuloView('trilha_dados', [
                'enrollment' => $enrollment,
                'escola' => $escola,
                'csrf_token' => $this->generateCsrfToken(),
            ]);
            return;
        }

        $contratoHtml = null;
        try {
            $contratoHtml = $this->service->htmlContratoParaAssinatura($enrollment, $escola);
        } catch (\Throwable $e) {
            error_log('[MatriculaPublico] htmlContrato: ' . $e->getMessage());
        }

        $this->renderModuloView('contrato', [
            'enrollment' => $enrollment,
            'escola' => $escola,
            'csrf_token' => $this->generateCsrfToken(),
            'zapsign_sign_url' => $enrollment['zapsign_sign_url'] ?? null,
            'contrato_html' => $contratoHtml,
        ]);
    }

    public function confirmarDados(string $token): void
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
        try {
            $this->service->atualizarDadosTrilha($enrollment);
        } catch (\Throwable $e) {
            $this->renderError($e->getMessage());
            return;
        }
        $this->redirect(URL . '/matricula/contrato/' . $token . '?etapa=contrato');
    }

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

        if (!in_array($enrollment['status'], ['rascunho', 'aguardando_contrato', 'aguardando_assinatura'], true)) {
            $this->renderError('Este contrato já foi processado.');
            return;
        }

        if ($this->model->temColuna('dados_confirmados_em') && empty($enrollment['dados_confirmados_em'])) {
            $this->redirect(URL . '/matricula/contrato/' . $token . '?etapa=dados');
            return;
        }

        $nomeAssinante = trim($_POST['nome_assinante'] ?? $enrollment['resp_nome']);
        if ($nomeAssinante === '') {
            $this->renderError('Por favor, informe seu nome para assinar.');
            return;
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (is_string($ip) && str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $this->service->registrarAssinatura($enrollment, (string) $ip, $nomeAssinante);

        $this->renderModuloView('contrato_assinado', [
            'enrollment' => $this->model->findById((int) $enrollment['id']),
        ]);
    }

    public function downloadPdf(string $token): void
    {
        $token = preg_replace('/[^a-f0-9]/i', '', $token);
        $enrollment = $this->model->findByToken($token);
        if (!$enrollment || empty($enrollment['contrato_pdf_path'])) {
            $this->renderError('PDF não disponível.');
            return;
        }
        $relative = ltrim(str_replace(['..', '\\'], '', (string) $enrollment['contrato_pdf_path']), '/');
        if ($relative === '' || !str_starts_with($relative, 'storage/')) {
            $this->renderError('Arquivo não encontrado.');
            return;
        }
        $base = realpath(dirname(__DIR__, 4) . '/storage');
        $path = realpath(dirname(__DIR__, 4) . '/' . $relative);
        if ($base === false || $path === false || !str_starts_with($path, $base) || !is_file($path)) {
            $this->renderError('Arquivo não encontrado.');
            return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="contrato.pdf"');
        readfile($path);
        exit;
    }

    private function renderModuloView(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = dirname(__DIR__) . '/Views/publico/' . $view . '.php';
        if (!is_file($viewPath)) {
            // fallback legado
            $legacy = dirname(__DIR__, 3) . '/Views/public/enrollment/' . $view . '.php';
            if (is_file($legacy)) {
                $viewPath = $legacy;
            } else {
                echo '<p>View não encontrada: ' . htmlspecialchars($view) . '</p>';
                return;
            }
        }
        $layout = dirname(__DIR__, 3) . '/Views/layouts/public_enrollment.php';
        if (is_file($layout)) {
            ob_start();
            require $viewPath;
            $content = ob_get_clean();
            require $layout;
            return;
        }
        require $viewPath;
    }

    private function renderError(string $message): void
    {
        http_response_code(400);
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Erro</title></head><body style="font-family:sans-serif;padding:40px;text-align:center;">';
        echo '<h1>Não foi possível continuar</h1><p>' . htmlspecialchars($message) . '</p></body></html>';
    }
}
}
