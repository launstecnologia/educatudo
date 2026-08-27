<?php
/**
 * EducaTudo - Histórico Escolar oficial (admin/secretaria).
 * Workflow: rascunho → conferido → emitido → assinado + PDF + validação pública.
 */

require_once __DIR__ . '/../Admin/AdminBaseController.php';
require_once __DIR__ . '/../../Services/HistoricoEscolarService.php';
require_once __DIR__ . '/../../Services/DeclarationService.php';
require_once __DIR__ . '/../../Modulos/vida-escolar/Services/VidaEscolarPdfService.php';

use App\Services\HistoricoEscolarService;
use App\Services\DeclarationService;
use App\Modulos\VidaEscolar\Services\VidaEscolarPdfService;

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
        $dados['resultado_labels'] = HistoricoEscolarService::RESULTADO_LABELS;
        $nome = preg_replace('/[^a-z0-9]+/i', '_', (string) (($dados['aluno']['nome'] ?? 'aluno'))) ?: 'aluno';
        $filename = 'historico_escolar_v' . (int) ($doc['versao'] ?? 1) . '_' . $nome . '.pdf';
        try {
            (new VidaEscolarPdfService($this->db))->emitirHistorico($dados, $this->config ?? null, $filename);
        } catch (\Throwable $e) {
            error_log('AdminHistoricoEscolarController pdf: ' . $e->getMessage());
            $_SESSION['error_message'] = 'Não foi possível gerar o PDF. Confira o modelo “Histórico escolar oficial” em Layout de documentos.';
            $this->redirect('/admin/students/' . $alunoId . '/historico-escolar/' . $historicoId);
        }
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
}
}
