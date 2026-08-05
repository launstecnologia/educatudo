<?php

require_once __DIR__ . '/AdminBaseController.php';

/**
 * EducaTudo - InstitutionalDocsController
 * PPP, Regimento Escolar e demais documentos institucionais (upload/download).
 */
if (!class_exists('InstitutionalDocsController')) {
class InstitutionalDocsController extends AdminBaseController
{
    private const TIPOS = [
        'ppp' => 'Projeto Político Pedagógico (PPP)',
        'regimento' => 'Regimento Escolar',
        'plano_curso' => 'Plano de Curso',
        'calendario' => 'Calendário Escolar',
        'outro' => 'Outro documento',
    ];

    private const EXTENSOES = ['pdf', 'doc', 'docx', 'odt', 'jpg', 'jpeg', 'png'];

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('documentos_institucionais', 'visualizar', false)) {
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/documentos-institucionais/index', [
            'title' => 'Documentos Institucionais - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'documentos_institucionais',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'documentos' => $this->listar(),
            'tipos' => self::TIPOS,
            'schema_pronto' => $this->tableExists(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey('documentos_institucionais', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/documentos-institucionais');
            return;
        }
        if (!$this->tableExists()) {
            $this->setFlashMessage('Recurso indisponível. Execute a migration de documentos institucionais.', 'error');
            $this->redirect('/admin/documentos-institucionais');
            return;
        }
        $tipo = (string) ($_POST['tipo'] ?? 'outro');
        if (!isset(self::TIPOS[$tipo])) {
            $tipo = 'outro';
        }
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        if ($titulo === '') {
            $titulo = self::TIPOS[$tipo];
        }
        $arquivo = null;
        if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
            try {
                $arquivo = $this->processarUpload($_FILES['arquivo']);
            } catch (Throwable $e) {
                $this->setFlashMessage($e->getMessage(), 'error');
                $this->redirect('/admin/documentos-institucionais');
                return;
            }
        }
        $user = $this->auth->getUser();
        $this->db->insert(
            "INSERT INTO documentos_institucionais (tipo, titulo, versao, arquivo_key, arquivo_nome, arquivo_mime, arquivo_tamanho, observacao, created_by)
             VALUES (:tipo, :titulo, :versao, :arquivo_key, :arquivo_nome, :arquivo_mime, :arquivo_tamanho, :observacao, :created_by)",
            [
                'tipo' => $tipo,
                'titulo' => mb_substr($titulo, 0, 255),
                'versao' => trim((string) ($_POST['versao'] ?? '')) ?: null,
                'arquivo_key' => $arquivo['key'] ?? null,
                'arquivo_nome' => $arquivo['nome'] ?? null,
                'arquivo_mime' => $arquivo['mime'] ?? null,
                'arquivo_tamanho' => $arquivo['tamanho'] ?? null,
                'observacao' => trim((string) ($_POST['observacao'] ?? '')) ?: null,
                'created_by' => isset($user['id']) ? (int) $user['id'] : null,
            ]
        );
        $this->setFlashMessage('Documento institucional salvo.', 'success');
        $this->redirect('/admin/documentos-institucionais');
    }

    public function excluir(): void
    {
        if (!$this->enforceAdminPermissionKey('documentos_institucionais', 'excluir', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/documentos-institucionais');
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $doc = $this->find($id);
        if ($doc) {
            if (!empty($doc['arquivo_key'])) {
                try {
                    require_once __DIR__ . '/../../Services/MediaStorageService.php';
                    (new MediaStorageService($this->config))->delete('arquivos', (string) $doc['arquivo_key']);
                } catch (Throwable $e) {
                    // best-effort
                }
            }
            $this->db->query("DELETE FROM documentos_institucionais WHERE id = :id", ['id' => $id]);
        }
        $this->setFlashMessage('Documento removido.', 'success');
        $this->redirect('/admin/documentos-institucionais');
    }

    public function baixar(): void
    {
        if (!$this->enforceAdminPermissionKey('documentos_institucionais', 'visualizar', false)) {
            return;
        }
        $doc = $this->find((int) ($_GET['id'] ?? 0));
        if (!$doc || empty($doc['arquivo_key'])) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $conteudo = (new MediaStorageService($this->config))->getContents('arquivos', (string) $doc['arquivo_key']);
        if ($conteudo === null) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: ' . (string) ($doc['arquivo_mime'] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) ($doc['arquivo_nome'] ?? 'documento')) . '"');
            header('Content-Length: ' . strlen($conteudo));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }
        echo $conteudo;
    }

    /** @return array<string,mixed>|null */
    private function find(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists()) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM documentos_institucionais WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    private function listar(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        return $this->db->fetchAll("SELECT * FROM documentos_institucionais ORDER BY tipo ASC, created_at DESC") ?: [];
    }

    /** @return array{key:string,nome:string,mime:string,tamanho:int} */
    private function processarUpload(array $file): array
    {
        $ext = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSOES, true)) {
            throw new Exception('Tipo de arquivo não permitido. Use: ' . implode(', ', self::EXTENSOES));
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Máximo 20MB.');
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
        $nomeUnico = 'inst_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $key = $slug . '/documentos_institucionais/' . $nomeUnico;
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

    private function tableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'documentos_institucionais'");
            $cache = $row !== false && !empty($row);
        } catch (Throwable $e) {
            $cache = false;
        }
        return $cache;
    }
}
}
