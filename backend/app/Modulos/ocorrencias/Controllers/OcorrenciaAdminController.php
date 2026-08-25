<?php
/**
 * EducaTudo - Ocorrências do aluno (admin / coordenação / secretaria)
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Models/Ocorrencia.php';
require_once __DIR__ . '/../../../Models/Education/ClassDiary.php';
require_once __DIR__ . '/../Services/OcorrenciaService.php';

use App\Modulos\Ocorrencias\Services\OcorrenciaService;

if (!class_exists('OcorrenciaAdminController')) {
class OcorrenciaAdminController extends AdminBaseController
{
    private function service(): OcorrenciaService
    {
        return new OcorrenciaService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'visualizar', false)) {
            return;
        }

        $filtros = [
            'data_inicio' => trim((string) ($_GET['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_GET['data_fim'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'categoria_id' => (int) ($_GET['categoria_id'] ?? 0),
            'turma_id' => (int) ($_GET['turma_id'] ?? 0),
        ];

        $model = $this->service()->model();
        $perPage = 10;
        $total = $model->contar($filtros);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
        $offset = ($page - 1) * $perPage;

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ocorrencias/index', [
            'title' => 'Ocorrências - EducaTudo',
            'page_title' => 'Ocorrências',
            'user' => $this->auth->getUser(),
            'current_page' => 'ocorrencias',
            'ocorrencias' => $model->listar($filtros, $perPage, $offset),
            'categorias' => $model->categorias(false),
            'turmas' => $model->turmasAtivas(),
            'filtros' => $filtros,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
                'total_pages' => $totalPages,
            ],
            'schema_estendido' => $model->schemaEstendido(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function nova(): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'cadastrar', false)) {
            return;
        }

        $model = $this->service()->model();
        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        $aulaId = (int) ($_GET['aula_id'] ?? 0);
        $aluno = $alunoId > 0 ? $model->dadosAluno($alunoId) : null;
        $aula = $aulaId > 0 ? (new ClassDiary())->getAula($aulaId) : null;

        $voltar = URL . '/admin/ocorrencias';
        if ($alunoId > 0) {
            $voltar = URL . '/admin/students/' . $alunoId;
        } elseif ($aulaId > 0) {
            $voltar = URL . '/admin/diario/aula?id=' . $aulaId;
        }

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ocorrencias/form', [
            'title' => 'Nova ocorrência - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'ocorrencias',
            'categorias' => $model->categorias(true),
            'aluno_preenchido' => $aluno,
            'aula' => $aula,
            'voltar_url' => $voltar,
            'csrf_token' => $this->generateCsrfToken(),
            'schema_estendido' => $model->schemaEstendido(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'cadastrar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/ocorrencias/nova');
            return;
        }

        $user = $this->auth->getUser();
        $result = $this->service()->criar($_POST, (int) ($user['id'] ?? 0), 'admin');
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível salvar', 'error');
            $qs = [];
            if ((int) ($_POST['aluno_id'] ?? 0) > 0) {
                $qs['aluno_id'] = (int) $_POST['aluno_id'];
            }
            if ((int) ($_POST['diario_aula_id'] ?? 0) > 0) {
                $qs['aula_id'] = (int) $_POST['diario_aula_id'];
            }
            $this->redirect('/admin/ocorrencias/nova' . ($qs ? ('?' . http_build_query($qs)) : ''));
            return;
        }

        $this->setFlashMessage('Ocorrência registrada.', 'success');
        $this->redirect('/admin/ocorrencias/' . (int) $result['id']);
    }

    public function show($id): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'visualizar', false)) {
            return;
        }
        $model = $this->service()->model();
        $item = $model->findById((int) $id);
        if (!$item) {
            $this->setFlashMessage('Ocorrência não encontrada.', 'error');
            $this->redirect('/admin/ocorrencias');
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ocorrencias/show', [
            'title' => 'Ocorrência - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'ocorrencias',
            'ocorrencia' => $item,
            'historico' => $model->historico((int) $id),
            'schema_estendido' => $model->schemaEstendido(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function atualizarStatus($id): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'alterar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/ocorrencias/' . (int) $id);
            return;
        }
        $status = trim((string) ($_POST['status'] ?? ''));
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $result = $this->service()->alterarStatus((int) $id, $status, (int) ($this->auth->getUser()['id'] ?? 0), $motivo !== '' ? $motivo : null);
        $this->setFlashMessage($result['success'] ? 'Status atualizado.' : ($result['error'] ?? 'Falha ao atualizar'), $result['success'] ? 'success' : 'error');
        $this->redirect('/admin/ocorrencias/' . (int) $id);
    }

    public function atualizarPais($id): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'alterar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/ocorrencias/' . (int) $id);
            return;
        }
        $enviar = !empty($_POST['enviar_pais']);
        $result = $this->service()->definirVisibilidadePais((int) $id, $enviar, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage($result['success'] ? 'Visibilidade para os pais atualizada.' : ($result['error'] ?? 'Falha'), $result['success'] ? 'success' : 'error');
        $this->redirect('/admin/ocorrencias/' . (int) $id);
    }

    public function atualizarEncaminhamento($id): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'alterar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/ocorrencias/' . (int) $id);
            return;
        }
        $result = $this->service()->salvarEncaminhamento(
            (int) $id,
            (string) ($_POST['encaminhamento'] ?? ''),
            (int) ($this->auth->getUser()['id'] ?? 0)
        );
        $this->setFlashMessage($result['success'] ? 'Encaminhamento salvo.' : ($result['error'] ?? 'Falha'), $result['success'] ? 'success' : 'error');
        $this->redirect('/admin/ocorrencias/' . (int) $id);
    }

    public function salvarCategoria(): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'cadastrar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/ocorrencias');
            return;
        }
        $result = $this->service()->criarCategoria((string) ($_POST['nome'] ?? ''));
        $this->setFlashMessage($result['success'] ? 'Categoria cadastrada.' : ($result['error'] ?? 'Falha'), $result['success'] ? 'success' : 'error');
        $this->redirect('/admin/ocorrencias');
    }

    public function buscarAlunos(): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'visualizar', true)) {
            return;
        }
        $alunos = $this->service()->model()->buscarAlunos(
            trim((string) ($_GET['term'] ?? '')),
            (int) ($_GET['turma_id'] ?? 0)
        );
        $this->json(['success' => true, 'alunos' => $alunos]);
    }
}
}
