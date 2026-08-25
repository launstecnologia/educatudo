<?php
/**
 * EducaTudo - Conselho de Classe (admin / coordenação)
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/ConselhoService.php';

use App\Modulos\ConselhoClasse\Services\ConselhoService;

if (!class_exists('ConselhoAdminController', false)) {
class ConselhoAdminController extends AdminBaseController
{
    private function service(): ConselhoService
    {
        return new ConselhoService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'visualizar', false)) {
            return;
        }

        $svc = $this->service();
        $anos = $svc->model()->anosLetivosTurmas();
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? ($anos[0] ?? date('Y')));
        if (!in_array($anoLetivo, $anos, true)) {
            $anoLetivo = (int) ($anos[0] ?? date('Y'));
        }
        $bimestre = (int) ($_GET['bimestre'] ?? $this->bimestreAtual());
        $bimestre = max(1, min(4, $bimestre));
        $turmaId = (int) ($_GET['turma_id'] ?? 0);

        $flash = $this->getFlashMessage();
        if (!$svc->model()->schemaPronto() && empty($flash['message'])) {
            $flash = [
                'message' => 'Execute a migration do Conselho de Classe (painel Master) antes de usar o módulo.',
                'type' => 'error',
            ];
        }
        $this->viewWithLayout('admin', 'admin/conselho-classe/index', [
            'title' => 'Conselho de Classe - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'conselho_classe',
            'anos' => $anos,
            'ano_letivo' => $anoLetivo,
            'bimestre' => $bimestre,
            'turma_id' => $turmaId,
            'turmas' => $svc->model()->turmasAtivas($anoLetivo),
            'linhas' => $svc->painel($anoLetivo, $bimestre, $turmaId),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function nova(): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'cadastrar', false)) {
            return;
        }

        $svc = $this->service();
        $anos = $svc->model()->anosLetivosTurmas();
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? ($anos[0] ?? date('Y')));
        $bimestre = max(1, min(4, (int) ($_GET['bimestre'] ?? $this->bimestreAtual())));
        $turmaId = (int) ($_GET['turma_id'] ?? 0);

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/conselho-classe/form', [
            'title' => 'Iniciar Conselho de Classe - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'conselho_classe',
            'anos' => $anos,
            'ano_letivo' => $anoLetivo,
            'bimestre' => $bimestre,
            'turma_id' => $turmaId,
            'turmas' => $svc->model()->turmasAtivas($anoLetivo),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'cadastrar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/conselhos/novo');
            return;
        }

        $user = $this->auth->getUser();
        $result = $this->service()->criar($_POST, (int) ($user['id'] ?? 0));
        if (empty($result['success'])) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível iniciar o Conselho', 'error');
            $this->redirect('/admin/conselhos/novo');
            return;
        }

        $this->setFlashMessage('Conselho iniciado em preparação.', 'success');
        $this->redirect('/admin/conselhos/' . (int) $result['id']);
    }

    public function show($id): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'visualizar', false)) {
            return;
        }
        $sessao = $this->sessaoOuRedirect((int) $id);
        if (!$sessao) {
            return;
        }

        $svc = $this->service();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/conselho-classe/show', [
            'title' => 'Conselho de Classe - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'conselho_classe',
            'sessao' => $sessao,
            'matriz' => $svc->matriz($sessao),
            'ata' => $svc->model()->getAta((int) $id),
            'pode_registrar' => $svc->podeRegistrar($sessao),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function aluno($id, $alunoId): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'visualizar', false)) {
            return;
        }
        $sessao = $this->sessaoOuRedirect((int) $id);
        if (!$sessao) {
            return;
        }

        $ficha = $this->service()->fichaAluno($sessao, (int) $alunoId);
        if (!$ficha) {
            $this->setFlashMessage('Aluno não encontrado neste Conselho.', 'error');
            $this->redirect('/admin/conselhos/' . (int) $id);
            return;
        }

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/conselho-classe/aluno', [
            'title' => 'Ficha no Conselho - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'conselho_classe',
            'sessao' => $sessao,
            'ficha' => $ficha,
            'pode_registrar' => $this->service()->podeRegistrar($sessao),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function abrir($id): void
    {
        $this->postStatus((int) $id, 'alterar', fn (ConselhoService $svc, int $sid, int $uid) => $svc->abrir($sid, $uid), 'Conselho em andamento.');
    }

    public function finalizar($id): void
    {
        $this->postStatus((int) $id, 'alterar', fn (ConselhoService $svc, int $sid, int $uid) => $svc->finalizar($sid, $uid), 'Conselho finalizado.');
    }

    public function reabrir($id): void
    {
        $this->postStatus((int) $id, 'alterar', fn (ConselhoService $svc, int $sid, int $uid) => $svc->reabrir($sid, $uid), 'Conselho reaberto. Alterações passam a ser auditadas.');
    }

    public function participantes($id): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'alterar', false)) {
            return;
        }
        if (!$this->csrfOuRedirect('/admin/conselhos/' . (int) $id)) {
            return;
        }
        $result = $this->service()->salvarParticipantes((int) $id, $_POST);
        $this->setFlashMessage($result['success'] ? 'Participantes atualizados.' : ($result['error'] ?? 'Falha ao salvar'), $result['success'] ? 'success' : 'error');
        $this->redirect('/admin/conselhos/' . (int) $id);
    }

    public function deliberar($id): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'alterar', false)) {
            return;
        }
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $voltar = '/admin/conselhos/' . (int) $id . '/aluno/' . $alunoId;
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $result = $this->service()->deliberar((int) $id, $_POST, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage($result['success'] ? 'Deliberação registrada. A nota original do evento não foi alterada.' : ($result['error'] ?? 'Falha'), $result['success'] ? 'success' : 'error');
        $this->redirect($voltar);
    }

    public function encaminhar($id): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'alterar', false)) {
            return;
        }
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $voltar = '/admin/conselhos/' . (int) $id . '/aluno/' . $alunoId;
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $result = $this->service()->encaminhar((int) $id, $_POST, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage($result['success'] ? 'Encaminhamento registrado.' : ($result['error'] ?? 'Falha'), $result['success'] ? 'success' : 'error');
        $this->redirect($voltar);
    }

    public function ata($id): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'visualizar', false)) {
            return;
        }
        $sessao = $this->sessaoOuRedirect((int) $id);
        if (!$sessao) {
            return;
        }
        $svc = $this->service();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/conselho-classe/ata', [
            'title' => 'Ata do Conselho - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'conselho_classe',
            'sessao' => $sessao,
            'ata' => $svc->model()->getAta((int) $id),
            'matriz' => $svc->matriz($sessao),
            'pode_registrar' => $svc->podeRegistrar($sessao) || (string) $sessao['status'] === 'finalizado',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function salvarAta($id): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'alterar', false)) {
            return;
        }
        if (!$this->csrfOuRedirect('/admin/conselhos/' . (int) $id . '/ata')) {
            return;
        }
        $result = $this->service()->gerarAta((int) $id, $_POST, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage($result['success'] ? 'Ata gerada com o snapshot do Conselho.' : ($result['error'] ?? 'Falha'), $result['success'] ? 'success' : 'error');
        $this->redirect('/admin/conselhos/' . (int) $id . '/ata');
    }

    public function ataPdf($id): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', 'visualizar', false)) {
            return;
        }
        $sessao = $this->sessaoOuRedirect((int) $id);
        if (!$sessao) {
            return;
        }
        $svc = $this->service();
        $ata = $svc->model()->getAta((int) $id);
        if (!$ata) {
            $this->setFlashMessage('Gere a ata antes de exportar o PDF.', 'error');
            $this->redirect('/admin/conselhos/' . (int) $id . '/ata');
            return;
        }

        $escola = class_exists('LayoutHelper') ? (string) LayoutHelper::getSystemTitle() : 'Escola';
        ob_start();
        extract([
            'sessao' => $sessao,
            'ata' => $ata,
            'matriz' => $svc->matriz($sessao),
            'escola' => $escola,
            'periodo_label' => $svc->periodoLabel((int) $sessao['bimestre']),
        ], EXTR_SKIP);
        require __DIR__ . '/../Views/admin/ata_pdf.php';
        $html = (string) ob_get_clean();

        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $filename = 'ata_conselho_' . (int) $id . '_' . date('Ymd_His') . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }

    /**
     * @param callable(ConselhoService,int,int):array $acao
     */
    private function postStatus(int $id, string $permissao, callable $acao, string $ok): void
    {
        if (!$this->enforceAdminPermissionKey('conselho_classe', $permissao, false)) {
            return;
        }
        if (!$this->csrfOuRedirect('/admin/conselhos/' . $id)) {
            return;
        }
        $result = $acao($this->service(), $id, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage($result['success'] ? $ok : ($result['error'] ?? 'Falha'), $result['success'] ? 'success' : 'error');
        $this->redirect('/admin/conselhos/' . $id);
    }

    private function csrfOuRedirect(string $voltar): bool
    {
        if ($this->verifyCsrfToken($_POST['_token'] ?? '')) {
            return true;
        }
        $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
        $this->redirect($voltar);
        return false;
    }

    private function sessaoOuRedirect(int $id): ?array
    {
        $sessao = $this->service()->model()->findById($id);
        if ($sessao) {
            return $sessao;
        }
        $this->setFlashMessage('Conselho não encontrado.', 'error');
        $this->redirect('/admin/conselhos');
        return null;
    }

    private function bimestreAtual(): int
    {
        return (int) ceil(((int) date('n')) / 3);
    }
}
}
