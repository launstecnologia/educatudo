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

        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        $aulaId = (int) ($_GET['aula_id'] ?? 0);
        $aluno = $alunoId > 0 ? $model->dadosAluno($alunoId) : null;
        $aula = $aulaId > 0 ? (new ClassDiary())->getAula($aulaId) : null;

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ocorrencias/index', [
            'title' => 'Ocorrências - EducaTudo',
            'page_title' => 'Ocorrências',
            'user' => $this->auth->getUser(),
            'current_page' => 'ocorrencias',
            'ocorrencias' => $model->listar($filtros, $perPage, $offset),
            'categorias' => $model->categorias(false),
            'categorias_ativas' => $model->categorias(true),
            'turmas' => $model->turmasAtivas(),
            'filtros' => $filtros,
            'aluno_preenchido' => $aluno,
            'aula' => $aula,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
                'total_pages' => $totalPages,
            ],
            'schema_estendido' => $model->schemaEstendido(),
            'schema_anexos' => $model->schemaAnexos(),
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
        $qs = ['novo' => '1'];
        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        $aulaId = (int) ($_GET['aula_id'] ?? 0);
        if ($alunoId > 0) {
            $qs['aluno_id'] = $alunoId;
        }
        if ($aulaId > 0) {
            $qs['aula_id'] = $aulaId;
        }
        $this->redirect('/admin/ocorrencias?' . http_build_query($qs));
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'cadastrar', false)) {
            return;
        }
        $qsErro = ['novo' => '1'];
        $alunoIdErro = (int) ($_POST['aluno_id'] ?? 0);
        if ($alunoIdErro <= 0) {
            $alunosPost = $_POST['alunos'] ?? [];
            if (is_array($alunosPost)) {
                $alunoIdErro = (int) ($alunosPost[0] ?? 0);
            }
        }
        if ($alunoIdErro > 0) {
            $qsErro['aluno_id'] = $alunoIdErro;
        }
        if ((int) ($_POST['diario_aula_id'] ?? 0) > 0) {
            $qsErro['aula_id'] = (int) $_POST['diario_aula_id'];
        }
        $voltarErro = '/admin/ocorrencias?' . http_build_query($qsErro);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect($voltarErro);
            return;
        }

        $user = $this->auth->getUser();
        $result = $this->service()->criar($_POST, (int) ($user['id'] ?? 0), 'admin', 0, $_FILES['anexos'] ?? []);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível salvar', 'error');
            $this->redirect($voltarErro);
            return;
        }

        $this->setFlashMessage('Ocorrência registrada.', 'success');
        if ((int) ($result['anexos'] ?? 0) < 1 && $this->houveUploadAnexos($_FILES['anexos'] ?? [])) {
            $this->setFlashMessage('Ocorrência registrada. Nenhum anexo válido foi salvo (JPG, PNG, WebP, GIF, PDF ou Word, até 10 MB).', 'success');
        }
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
            'anexos' => $model->listarAnexos((int) $id),
            'schema_estendido' => $model->schemaEstendido(),
            'schema_anexos' => $model->schemaAnexos(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function anexar($id): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'alterar', false)) {
            return;
        }
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/ocorrencias/' . $id);
            return;
        }
        $item = $this->service()->model()->findById($id);
        if (!$item) {
            $this->setFlashMessage('Ocorrência não encontrada.', 'error');
            $this->redirect('/admin/ocorrencias');
            return;
        }
        if (!$this->service()->model()->schemaAnexos()) {
            $this->setFlashMessage('Atualize o banco (migration de anexos) para enviar arquivos.', 'error');
            $this->redirect('/admin/ocorrencias/' . $id);
            return;
        }
        $salvos = $this->service()->salvarAnexos($id, $_FILES['anexos'] ?? []);
        if ($salvos < 1) {
            $this->setFlashMessage('Nenhum arquivo válido. Use JPG, PNG, WebP, GIF, PDF ou Word de até 10 MB.', 'error');
        } else {
            $this->setFlashMessage($salvos === 1 ? 'Anexo enviado.' : $salvos . ' anexos enviados.', 'success');
        }
        $this->redirect('/admin/ocorrencias/' . $id);
    }

    public function baixarAnexo($id, $anexoId): void
    {
        if (!$this->enforceAdminPermissionKey('ocorrencias', 'visualizar', false)) {
            return;
        }
        $id = (int) $id;
        $anexoId = (int) $anexoId;
        $anexo = $this->service()->model()->findAnexo($id, $anexoId);
        if (!$anexo) {
            $this->setFlashMessage('Anexo não encontrado.', 'error');
            $this->redirect('/admin/ocorrencias/' . $id);
            return;
        }
        $path = $this->service()->caminhoFisicoAnexo((string) ($anexo['caminho'] ?? ''));
        if ($path === null) {
            $this->setFlashMessage('Arquivo não encontrado no servidor.', 'error');
            $this->redirect('/admin/ocorrencias/' . $id);
            return;
        }
        $mimePermitidos = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/webp' => true,
            'image/gif' => true,
            'application/pdf' => true,
            'application/msword' => true,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => true,
        ];
        $mime = (string) ($anexo['mime'] ?? '');
        if (!isset($mimePermitidos[$mime])) {
            $mime = 'application/octet-stream';
        }
        $nome = (string) ($anexo['nome'] ?? 'anexo');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $nome) . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
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

    /**
     * @param array<string,mixed> $files
     */
    private function houveUploadAnexos(array $files): bool
    {
        $names = $files['name'] ?? null;
        if ($names === null || $names === '' || $names === []) {
            return false;
        }
        if (!is_array($names)) {
            return trim((string) $names) !== '';
        }
        foreach ($names as $n) {
            if (trim((string) $n) !== '') {
                return true;
            }
        }
        return false;
    }
}
}
