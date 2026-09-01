<?php
/**
 * EducaTudo - Ofícios da secretaria (admin)
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/OficioService.php';

use App\Modulos\VidaEscolar\Services\OficioService;

if (!class_exists('OficioAdminController', false)) {
class OficioAdminController extends AdminBaseController
{
    private function service(): OficioService
    {
        return new OficioService();
    }

    private function exigirPermissao(string $acao): bool
    {
        if (!class_exists('LayoutHelper', false)) {
            require_once dirname(__DIR__, 3) . '/Core/LayoutHelper.php';
        }
        if (!\LayoutHelper::isModuleEnabled('vida_escolar')) {
            $this->setFlashMessage('O módulo Vida Escolar está desativado nesta escola.', 'error');
            $this->redirect('/admin/dashboard');
            return false;
        }
        return $this->enforceAdminPermissionKey('vida_escolar', $acao, false);
    }

    public function index(): void
    {
        if (!$this->exigirPermissao('visualizar')) {
            return;
        }
        $model = $this->service()->model();
        $filtros = [
            'ano' => (int) ($_GET['ano'] ?? date('Y')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'aluno_id' => (int) ($_GET['aluno_id'] ?? 0),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        $perPage = 10;
        $total = $model->contar($filtros);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
        $offset = ($page - 1) * $perPage;
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/vida-escolar/oficios/index', [
            'title' => 'Ofícios - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'vida_escolar_oficios',
            'oficios' => $model->listar($filtros, $perPage, $offset),
            'filtros' => $filtros,
            'schema_pronto' => $model->schemaPronto(),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
                'total_pages' => $totalPages,
            ],
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function form($id = null): void
    {
        $id = (int) $id;
        $acao = $id > 0 ? 'alterar' : 'cadastrar';
        if (!$this->exigirPermissao($acao)) {
            return;
        }
        $model = $this->service()->model();
        $oficio = null;
        if ($id > 0) {
            $oficio = $model->findById($id);
            if (!$oficio) {
                $this->setFlashMessage('Ofício não encontrado.', 'error');
                $this->redirect('/admin/vida-escolar/oficios');
                return;
            }
        }
        $turmaId = (int) ($oficio['turma_id'] ?? $_GET['turma_id'] ?? 0);
        $alunoId = (int) ($oficio['aluno_id'] ?? $_GET['aluno_id'] ?? 0);
        $alunos = $turmaId > 0 ? $model->alunosDaTurma($turmaId) : [];
        if ($alunoId > 0) {
            $ja = false;
            foreach ($alunos as $a) {
                if ((int) ($a['id'] ?? 0) === $alunoId) {
                    $ja = true;
                    break;
                }
            }
            if (!$ja) {
                $row = $this->db->fetch(
                    'SELECT id, nome, turma_id FROM alunos WHERE id = :id LIMIT 1',
                    ['id' => $alunoId]
                );
                if (is_array($row)) {
                    if ($turmaId <= 0) {
                        $turmaId = (int) ($row['turma_id'] ?? 0);
                        if ($turmaId > 0) {
                            $alunos = $model->alunosDaTurma($turmaId);
                        }
                    }
                    $ainda = false;
                    foreach ($alunos as $a) {
                        if ((int) ($a['id'] ?? 0) === $alunoId) {
                            $ainda = true;
                            break;
                        }
                    }
                    if (!$ainda) {
                        $alunos[] = $row;
                    }
                }
            }
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/vida-escolar/oficios/form', [
            'title' => ($id > 0 ? 'Editar ofício' : 'Novo ofício') . ' - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'vida_escolar_oficios',
            'oficio' => $oficio,
            'turmas' => $model->turmasAtivas(),
            'alunos' => $alunos,
            'turma_id' => $turmaId,
            'aluno_id' => $alunoId,
            'schema_pronto' => $model->schemaPronto(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function salvar($id = null): void
    {
        $id = (int) $id;
        $acao = $id > 0 ? 'alterar' : 'cadastrar';
        if (!$this->exigirPermissao($acao)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect($id > 0 ? '/admin/vida-escolar/oficios/' . $id . '/editar' : '/admin/vida-escolar/oficios/novo');
            return;
        }
        $user = $this->auth->getUser();
        $res = $this->service()->salvar($_POST, $id > 0 ? $id : null, (int) ($user['id'] ?? 0));
        if (empty($res['success'])) {
            $this->setFlashMessage($res['error'] ?? 'Não foi possível salvar.', 'error');
            $this->redirect($id > 0 ? '/admin/vida-escolar/oficios/' . $id . '/editar' : '/admin/vida-escolar/oficios/novo');
            return;
        }
        $this->setFlashMessage('Ofício salvo.', 'success');
        $this->redirect('/admin/vida-escolar/oficios/' . (int) $res['id'] . '/editar');
    }

    public function pdf($id): void
    {
        if (!$this->exigirPermissao('visualizar')) {
            return;
        }
        $oficio = $this->service()->model()->findById((int) $id);
        if (!$oficio) {
            $this->setFlashMessage('Ofício não encontrado.', 'error');
            $this->redirect('/admin/vida-escolar/oficios');
            return;
        }
        if ((string) ($oficio['status'] ?? '') === 'cancelado') {
            $this->setFlashMessage('Ofício cancelado não gera PDF.', 'error');
            $this->redirect('/admin/vida-escolar/oficios');
            return;
        }
        try {
            $this->service()->emitirPdf($oficio, $this->config ?? null);
        } catch (\Throwable $e) {
            error_log('OficioAdminController PDF: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível gerar o PDF. Confira o Layout de documentos (modelo Ofício).', 'error');
            $this->redirect('/admin/vida-escolar/oficios/' . (int) $id . '/editar');
        }
    }

    public function emitir($id): void
    {
        if (!$this->exigirPermissao('alterar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/vida-escolar/oficios');
            return;
        }
        $id = (int) $id;
        $res = $this->service()->emitir($id);
        if (empty($res['success'])) {
            $this->setFlashMessage($res['error'] ?? 'Não foi possível emitir.', 'error');
            $this->redirect('/admin/vida-escolar/oficios/' . $id . '/editar');
            return;
        }
        $this->redirect('/admin/vida-escolar/oficios/' . $id . '/pdf');
    }

    public function cancelar($id): void
    {
        if (!$this->exigirPermissao('alterar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/vida-escolar/oficios');
            return;
        }
        $res = $this->service()->cancelar((int) $id);
        $this->setFlashMessage(
            !empty($res['success']) ? 'Ofício cancelado.' : ($res['error'] ?? 'Não foi possível cancelar.'),
            !empty($res['success']) ? 'success' : 'error'
        );
        $this->redirect('/admin/vida-escolar/oficios');
    }

    public function alunosJson(): void
    {
        if (!class_exists('LayoutHelper', false)) {
            require_once dirname(__DIR__, 3) . '/Core/LayoutHelper.php';
        }
        if (!\LayoutHelper::isModuleEnabled('vida_escolar')) {
            $this->json(['error' => 'Módulo desativado'], 403);
            return;
        }
        if (!$this->enforceAdminPermissionKey('vida_escolar', 'visualizar', true)) {
            return;
        }
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $alunos = $turmaId > 0 ? $this->service()->model()->alunosDaTurma($turmaId) : [];
        $this->json(['alunos' => $alunos]);
    }
}
}
