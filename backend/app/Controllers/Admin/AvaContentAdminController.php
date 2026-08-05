<?php

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Services/AvaCourseService.php';

/**
 * EducaTudo - AVA: gestão de conteúdo (módulos, aulas e anexos) - admin.
 */
if (!class_exists('AvaContentAdminController')) {
class AvaContentAdminController extends AdminBaseController
{
    private const EXTENSOES = ['pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'mp3', 'mp4'];

    private AvaCourseService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AvaCourseService();
    }

    // ---- Módulos ----------------------------------------------------------

    public function storeModulo(): void
    {
        if (!$this->guard('cadastrar')) {
            return;
        }
        $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        if ($titulo !== '' && $disciplinaId > 0) {
            $this->service->modulesModel()->save(
                $disciplinaId,
                $titulo,
                trim((string) ($_POST['descricao'] ?? '')) ?: null,
                (int) ($_POST['ordem'] ?? 0),
                (string) ($_POST['status'] ?? 'publicado')
            );
            $this->setFlashMessage('Módulo salvo.', 'success');
        }
        $this->redirect('/admin/ava/disciplinas/' . $disciplinaId);
    }

    public function updateModulo(string $id): void
    {
        if (!$this->guard('alterar')) {
            return;
        }
        $modulo = $this->service->modulesModel()->find((int) $id);
        $disciplinaId = (int) ($modulo['disciplina_id'] ?? ($_POST['disciplina_id'] ?? 0));
        $this->service->modulesModel()->save(
            $disciplinaId,
            trim((string) ($_POST['titulo'] ?? ($modulo['titulo'] ?? ''))),
            trim((string) ($_POST['descricao'] ?? '')) ?: null,
            (int) ($_POST['ordem'] ?? 0),
            (string) ($_POST['status'] ?? 'publicado'),
            (int) $id
        );
        $this->setFlashMessage('Módulo atualizado.', 'success');
        $this->redirect('/admin/ava/disciplinas/' . $disciplinaId);
    }

    public function deleteModulo(string $id): void
    {
        if (!$this->guard('excluir')) {
            return;
        }
        $modulo = $this->service->modulesModel()->find((int) $id);
        $disciplinaId = (int) ($modulo['disciplina_id'] ?? 0);
        $this->service->modulesModel()->delete((int) $id);
        $this->setFlashMessage('Módulo removido.', 'success');
        $this->redirect('/admin/ava/disciplinas/' . $disciplinaId);
    }

    // ---- Aulas ------------------------------------------------------------

    public function createAula(string $moduloId): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'cadastrar', false)) {
            return;
        }
        $modulo = $this->service->modulesModel()->find((int) $moduloId);
        if (!$modulo) {
            $this->setFlashMessage('Módulo não encontrado.', 'error');
            $this->redirect('/admin/ava');
            return;
        }
        $this->renderAulaForm($modulo, null);
    }

    public function editAula(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'alterar', false)) {
            return;
        }
        $aula = $this->service->lessonsModel()->find((int) $id);
        if (!$aula) {
            $this->setFlashMessage('Aula não encontrada.', 'error');
            $this->redirect('/admin/ava');
            return;
        }
        $modulo = $this->service->modulesModel()->find((int) $aula['modulo_id']);
        $this->renderAulaForm($modulo, $aula);
    }

    private function renderAulaForm(?array $modulo, ?array $aula): void
    {
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ava/aula_form', [
            'title' => ($aula ? 'Editar Aula' : 'Nova Aula') . ' - AVA',
            'user' => $this->auth->getUser(),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'modulo' => $modulo,
            'aula' => $aula,
            'anexos' => $aula ? $this->service->lessonsModel()->attachments((int) $aula['id']) : [],
            'tipos' => Lesson::TIPOS,
            'providers' => Lesson::PROVIDERS,
            'base_url' => '/admin/ava',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function storeAula(string $moduloId): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/ava/modulos/' . (int) $moduloId . '/aulas/nova');
            return;
        }
        $data = $_POST;
        $data['modulo_id'] = (int) $moduloId;
        $id = $this->service->lessonsModel()->save($data);
        $this->setFlashMessage('Aula criada.', 'success');
        $this->redirect('/admin/ava/aulas/' . $id . '/editar');
    }

    public function updateAula(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/ava/aulas/' . (int) $id . '/editar');
            return;
        }
        $aula = $this->service->lessonsModel()->find((int) $id);
        if (!$aula) {
            $this->redirect('/admin/ava');
            return;
        }
        $data = $_POST;
        $data['modulo_id'] = (int) $aula['modulo_id'];
        $this->service->lessonsModel()->save($data, (int) $id);
        $this->setFlashMessage('Aula atualizada.', 'success');
        $this->redirect('/admin/ava/disciplinas/' . (int) $aula['disciplina_id']);
    }

    public function deleteAula(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'excluir', false)) {
            return;
        }
        $aula = $this->service->lessonsModel()->find((int) $id);
        $disciplinaId = (int) ($aula['disciplina_id'] ?? 0);
        $this->service->lessonsModel()->delete((int) $id);
        $this->setFlashMessage('Aula removida.', 'success');
        $this->redirect('/admin/ava/disciplinas/' . $disciplinaId);
    }

    // ---- Anexos -----------------------------------------------------------

    public function uploadAnexo(string $aulaId): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/admin/ava/aulas/' . (int) $aulaId . '/editar');
            return;
        }
        $aula = $this->service->lessonsModel()->find((int) $aulaId);
        if (!$aula) {
            $this->redirect('/admin/ava');
            return;
        }
        $url = trim((string) ($_POST['url'] ?? ''));
        if ($url !== '') {
            $this->service->lessonsModel()->addAttachment((int) $aulaId, [
                'tipo' => 'link',
                'nome' => trim((string) ($_POST['nome'] ?? '')) ?: $url,
                'url' => $url,
            ]);
            $this->setFlashMessage('Link adicionado.', 'success');
        } elseif (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
            try {
                $arq = $this->processarUpload($_FILES['arquivo']);
                $this->service->lessonsModel()->addAttachment((int) $aulaId, [
                    'tipo' => 'arquivo',
                    'arquivo_key' => $arq['key'],
                    'nome' => $arq['nome'],
                    'mime' => $arq['mime'],
                    'tamanho' => $arq['tamanho'],
                ]);
                $this->setFlashMessage('Anexo enviado.', 'success');
            } catch (Throwable $e) {
                $this->setFlashMessage($e->getMessage(), 'error');
            }
        }
        $this->redirect('/admin/ava/aulas/' . (int) $aulaId . '/editar');
    }

    public function deleteAnexo(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'excluir', false)) {
            return;
        }
        $anexo = $this->service->lessonsModel()->findAttachment((int) $id);
        $aulaId = (int) ($anexo['aula_id'] ?? 0);
        if ($anexo && !empty($anexo['arquivo_key'])) {
            try {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                (new MediaStorageService($this->config))->delete('arquivos', (string) $anexo['arquivo_key']);
            } catch (Throwable $e) {
                // best-effort
            }
        }
        $this->service->lessonsModel()->deleteAttachment((int) $id);
        $this->setFlashMessage('Anexo removido.', 'success');
        $this->redirect('/admin/ava/aulas/' . $aulaId . '/editar');
    }

    // ---- Helpers ----------------------------------------------------------

    private function guard(string $acao): bool
    {
        if (!$this->enforceAdminPermissionKey('ava', $acao, false)) {
            return false;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/ava');
            return false;
        }
        return true;
    }

    /** @return array{key:string,nome:string,mime:string,tamanho:int} */
    private function processarUpload(array $file): array
    {
        $ext = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSOES, true)) {
            throw new Exception('Tipo de arquivo não permitido.');
        }
        if ($file['size'] > 100 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Máximo 100MB.');
        }
        $mimeReal = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if ($detected) {
                    $mimeReal = $detected;
                }
            }
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $slug = trim((string) ($this->config['tenant']['slug'] ?? $this->config['school']['code'] ?? '')) ?: 'default';
        $nomeUnico = 'ava_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $key = $slug . '/ava/anexos/' . $nomeUnico;
        if (!$media->put('arquivos', $key, $file['tmp_name'], $mimeReal)) {
            throw new Exception('Falha ao armazenar o arquivo.');
        }
        return [
            'key' => $key,
            'nome' => substr((string) $file['name'], 0, 255),
            'mime' => $mimeReal,
            'tamanho' => (int) $file['size'],
        ];
    }
}
}
