<?php
/**
 * EducaTudo - Histórico Escolar oficial (admin/secretaria).
 * Workflow: rascunho → conferido → emitido → assinado + PDF + validação pública.
 */

require_once __DIR__ . '/../Admin/AdminBaseController.php';
require_once __DIR__ . '/../../Services/HistoricoEscolarService.php';
require_once __DIR__ . '/../../Services/DeclarationService.php';

use App\Services\HistoricoEscolarService;
use App\Services\DeclarationService;

if (!class_exists('AdminHistoricoEscolarController')) {
class AdminHistoricoEscolarController extends AdminBaseController
{
    private function service(): HistoricoEscolarService
    {
        return new HistoricoEscolarService($this->db);
    }

    private function podeGerenciar(string $action = 'visualizar'): bool
    {
        return $this->enforceAdminPermissionKey('declaracoes_aluno', $action, false);
    }

    public function index($id): void
    {
        if (!$this->podeGerenciar('visualizar')) {
            return;
        }
        $alunoId = (int) $id;
        $svc = $this->service();
        $decl = new DeclarationService($this->db);
        $aluno = $decl->getAluno($alunoId);
        if (!$aluno) {
            $_SESSION['error_message'] = 'Aluno não encontrado';
            $this->redirect('/admin/students');
            return;
        }

        $docs = $svc->listarPorAluno($alunoId);
        $checklist = $svc->checklist($alunoId);
        $flash = $this->getFlashMessage();

        $this->viewWithLayout('admin', 'admin/historico-escolar/index', [
            'title' => 'Histórico Escolar - ' . ($aluno['nome'] ?? 'Aluno'),
            'student' => $aluno,
            'documentos' => $docs,
            'checklist' => $checklist,
            'schema_ready' => $svc->schemaPronto(),
            'resultado_labels' => HistoricoEscolarService::RESULTADO_LABELS,
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'alunos',
            'flash_message' => $flash['message'] ?? ($_SESSION['success_message'] ?? $_SESSION['error_message'] ?? ''),
            'flash_type' => $flash['type'] ?? (!empty($_SESSION['error_message']) ? 'error' : 'success'),
        ]);
        unset($_SESSION['success_message'], $_SESSION['error_message']);
    }

    public function show($id, $historicoId): void
    {
        if (!$this->podeGerenciar('visualizar')) {
            return;
        }
        $alunoId = (int) $id;
        $historicoId = (int) $historicoId;
        $svc = $this->service();
        $detalhe = $svc->detalhe($historicoId);
        if (!$detalhe || (int) ($detalhe['documento']['aluno_id'] ?? 0) !== $alunoId) {
            $_SESSION['error_message'] = 'Histórico não encontrado';
            $this->redirect('/admin/students/' . $alunoId . '/historico-escolar');
            return;
        }

        $decl = new DeclarationService($this->db);
        $aluno = $decl->getAluno($alunoId);
        $flash = $this->getFlashMessage();

        $this->viewWithLayout('admin', 'admin/historico-escolar/show', [
            'title' => 'Histórico Escolar v' . (int) ($detalhe['documento']['versao'] ?? 1),
            'student' => $aluno,
            'detalhe' => $detalhe,
            'checklist' => $svc->checklist($alunoId),
            'resultado_labels' => HistoricoEscolarService::RESULTADO_LABELS,
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'alunos',
            'flash_message' => $flash['message'] ?? ($_SESSION['success_message'] ?? $_SESSION['error_message'] ?? ''),
            'flash_type' => $flash['type'] ?? (!empty($_SESSION['error_message']) ? 'error' : 'success'),
        ]);
        unset($_SESSION['success_message'], $_SESSION['error_message']);
    }

    public function gerarRascunho($id): void
    {
        if (!$this->podeGerenciar('alterar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token CSRF inválido';
            $this->redirect('/admin/students/' . (int) $id . '/historico-escolar');
            return;
        }
        $user = $this->auth->getUser();
        $res = $this->service()->gerarRascunho(
            (int) $id,
            (string) ($_POST['finalidade'] ?? 'Solicitacao'),
            isset($user['id']) ? (int) $user['id'] : null,
            trim((string) ($_POST['observacoes_gerais'] ?? '')) ?: null
        );
        if (empty($res['success'])) {
            $_SESSION['error_message'] = $res['error'] ?? 'Falha ao gerar rascunho';
            $this->redirect('/admin/students/' . (int) $id . '/historico-escolar');
            return;
        }
        $_SESSION['success_message'] = 'Rascunho do histórico gerado/atualizado com dados do boletim.';
        $this->redirect('/admin/students/' . (int) $id . '/historico-escolar/' . (int) $res['id']);
    }

    public function conferir($id, $historicoId): void
    {
        $this->postAcao($id, $historicoId, function (HistoricoEscolarService $svc, int $hid, array $user) {
            return $svc->conferir($hid, (int) ($user['id'] ?? 0));
        }, 'Histórico conferido.');
    }

    public function voltarRascunho($id, $historicoId): void
    {
        $this->postAcao($id, $historicoId, function (HistoricoEscolarService $svc, int $hid) {
            return $svc->voltarRascunho($hid);
        }, 'Documento reaberto como rascunho.');
    }

    public function emitir($id, $historicoId): void
    {
        $this->postAcao($id, $historicoId, function (HistoricoEscolarService $svc, int $hid, array $user) {
            return $svc->emitir($hid, (int) ($user['id'] ?? 0));
        }, 'Histórico emitido. Documento imutável — use o QR para validação pública.');
    }

    public function assinar($id, $historicoId): void
    {
        $this->postAcao($id, $historicoId, function (HistoricoEscolarService $svc, int $hid, array $user) {
            $cargo = (string) ($_POST['cargo'] ?? '');
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            return $svc->assinar(
                $hid,
                (int) ($user['id'] ?? 0),
                (string) ($user['nome'] ?? 'Admin'),
                $cargo,
                is_string($ip) ? $ip : null
            );
        }, 'Assinatura registrada.');
    }

    public function novaVersao($id, $historicoId): void
    {
        $this->postAcao($id, $historicoId, function (HistoricoEscolarService $svc, int $hid, array $user) {
            return $svc->novaVersao($hid, isset($user['id']) ? (int) $user['id'] : null);
        }, 'Nova versão criada.', true);
    }

    public function adicionarItemExterno($id, $historicoId): void
    {
        $this->postAcao($id, $historicoId, function (HistoricoEscolarService $svc, int $hid) {
            return $svc->adicionarItemExterno($hid, $_POST);
        }, 'Item externo incluído.');
    }

    public function excluirItemExterno($id, $historicoId, $itemId): void
    {
        $this->postAcao($id, $historicoId, function (HistoricoEscolarService $svc, int $hid) use ($itemId) {
            return $svc->excluirItemExterno($hid, (int) $itemId);
        }, 'Item externo removido.', false, 'excluir');
    }

    public function salvarObservacoes($id, $historicoId): void
    {
        $this->postAcao($id, $historicoId, function (HistoricoEscolarService $svc, int $hid) {
            return $svc->atualizarObservacoes(
                $hid,
                trim((string) ($_POST['observacoes_gerais'] ?? '')) ?: null,
                ['numero_registro_sed' => trim((string) ($_POST['numero_registro_sed'] ?? '')) ?: null]
            );
        }, 'Observações salvas.');
    }

    public function salvarResultado($id, $historicoId): void
    {
        $this->postAcao($id, $historicoId, function (HistoricoEscolarService $svc, int $hid) {
            return $svc->atualizarResultadoAnual(
                $hid,
                trim((string) ($_POST['ano_letivo'] ?? '')),
                trim((string) ($_POST['serie_ano'] ?? '')),
                trim((string) ($_POST['resultado'] ?? '')),
                trim((string) ($_POST['observacao'] ?? '')) ?: null
            );
        }, 'Resultado anual atualizado.');
    }

    public function pdf($id, $historicoId): void
    {
        if (!$this->podeGerenciar('visualizar')) {
            return;
        }
        $alunoId = (int) $id;
        $historicoId = (int) $historicoId;
        $svc = $this->service();
        $dados = $svc->dadosParaPdf($historicoId);
        if (!$dados || (int) ($dados['documento']['aluno_id'] ?? 0) !== $alunoId) {
            $_SESSION['error_message'] = 'Histórico não encontrado';
            $this->redirect('/admin/students/' . $alunoId . '/historico-escolar');
            return;
        }

        $doc = $dados['documento'];
        $viewData = [
            'titulo' => 'Histórico Escolar',
            'dados' => $dados,
            'documento' => $doc,
            'assinaturas' => $dados['assinaturas'] ?? [],
            'validation_url' => $dados['validation_url'] ?? '',
            'resultado_labels' => HistoricoEscolarService::RESULTADO_LABELS,
            'logo_data' => $this->resolveLogo($dados['unidade'] ?? null),
            'cidade_data' => $this->cidadeData($dados['unidade'] ?? null),
            'gerado_em' => date('d/m/Y H:i'),
        ];

        $html = $this->renderTemplate($viewData);
        $nome = preg_replace('/[^a-z0-9]+/i', '_', (string) (($dados['aluno']['nome'] ?? 'aluno'))) ?: 'aluno';
        $filename = 'historico_escolar_v' . (int) ($doc['versao'] ?? 1) . '_' . $nome . '.pdf';
        $this->outputPdf($html, $filename);
    }

    /**
     * @param callable(HistoricoEscolarService, int, array): array $fn
     */
    private function postAcao($id, $historicoId, callable $fn, string $okMsg, bool $redirectToNew = false, string $permAction = 'alterar'): void
    {
        $alunoId = (int) $id;
        $historicoId = (int) $historicoId;
        if (!$this->podeGerenciar($permAction)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token CSRF inválido';
            $this->redirect('/admin/students/' . $alunoId . '/historico-escolar/' . $historicoId);
            return;
        }
        $svc = $this->service();
        $doc = $svc->findById($historicoId);
        if (!$doc || (int) $doc['aluno_id'] !== $alunoId) {
            $_SESSION['error_message'] = 'Histórico não encontrado';
            $this->redirect('/admin/students/' . $alunoId . '/historico-escolar');
            return;
        }
        $user = $this->auth->getUser() ?: [];
        $res = $fn($svc, $historicoId, $user);
        if (empty($res['success'])) {
            $_SESSION['error_message'] = $res['error'] ?? 'Operação não realizada';
            $this->redirect('/admin/students/' . $alunoId . '/historico-escolar/' . $historicoId);
            return;
        }
        $_SESSION['success_message'] = $okMsg;
        $destId = $redirectToNew && !empty($res['id']) ? (int) $res['id'] : $historicoId;
        $this->redirect('/admin/students/' . $alunoId . '/historico-escolar/' . $destId);
    }

    /**
     * @param array<string, mixed> $viewData
     */
    private function renderTemplate(array $viewData): string
    {
        $templateFile = __DIR__ . '/../../Views/admin/historico-escolar/pdf.php';
        ob_start();
        extract($viewData, EXTR_SKIP);
        require $templateFile;
        return (string) ob_get_clean();
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

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }

    /**
     * @param array<string, mixed>|null $unidade
     */
    private function resolveLogo(?array $unidade): string
    {
        $candidates = [];
        if ($unidade && !empty($unidade['logo_url'])) {
            $candidates[] = (string) $unidade['logo_url'];
        }
        try {
            $layoutLogo = (string) \LayoutHelper::get('logo_url', '');
            if ($layoutLogo !== '') {
                $candidates[] = $layoutLogo;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        foreach ($candidates as $url) {
            $data = $this->logoToDataUri($url);
            if ($data !== '') {
                return $data;
            }
        }
        return '';
    }

    private function logoToDataUri(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (strpos($url, 'data:') === 0) {
            return $url;
        }
        $path = $url;
        if (preg_match('#^https?://#i', $url)) {
            $base = defined('URL') ? rtrim((string) URL, '/') : '';
            if ($base !== '' && strpos($url, $base) === 0) {
                $path = substr($url, strlen($base));
            } else {
                return '';
            }
        }
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $root = dirname(__DIR__, 3);
        $full = $root . '/public' . $path;
        if (!is_file($full)) {
            $full = $root . $path;
        }
        if (!is_file($full)) {
            return '';
        }
        $mime = 'image/png';
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($ext === 'gif') {
            $mime = 'image/gif';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        } elseif ($ext === 'svg') {
            $mime = 'image/svg+xml';
        }
        $bin = @file_get_contents($full);
        if ($bin === false) {
            return '';
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }

    /**
     * @param array<string, mixed>|null $unidade
     */
    private function cidadeData(?array $unidade): string
    {
        $cidade = trim((string) ($unidade['cidade'] ?? ''));
        $uf = trim((string) ($unidade['uf'] ?? ''));
        $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];
        $dia = (int) date('j');
        $mes = $meses[(int) date('n')] ?? date('m');
        $ano = date('Y');
        $local = $cidade !== '' ? $cidade : 'Local';
        if ($uf !== '') {
            $local .= '/' . $uf;
        }
        return $local . ', ' . $dia . ' de ' . $mes . ' de ' . $ano;
    }
}
}
