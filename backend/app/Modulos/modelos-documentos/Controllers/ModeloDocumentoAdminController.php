<?php
/**
 * Admin — CRUD de modelos de documentos (HTML editável + placeholders).
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/ModeloDocumentoService.php';

use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;

if (!class_exists('ModeloDocumentoAdminController')) {
class ModeloDocumentoAdminController extends AdminBaseController
{
    private ModeloDocumentoService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ModeloDocumentoService($this->db);
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'visualizar', false)) {
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/modelos-documentos/index', [
            'title' => 'Contratos e outros modelos — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'modelos_documentos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'lista' => $this->service->listarExcetoDeclaracoes(false),
            'schema_pronto' => $this->service->schemaReady(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function create(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'cadastrar', false)) {
            return;
        }
        $this->renderForm(null);
    }

    public function edit(int $id): void
    {
        $modelo = $this->service->findById($id);
        if (!$modelo) {
            $this->setFlashMessage('Modelo não encontrado.', 'error');
            $this->redirect('/admin/modelos-documentos');
            return;
        }
        if (!$this->podeEditarModelo($modelo)) {
            return;
        }
        $this->renderForm($modelo);
    }

    public function store(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'cadastrar', false)) {
            return;
        }
        $this->persist(null);
    }

    public function update(int $id): void
    {
        $modelo = $this->service->findById($id);
        if (!$modelo) {
            $this->setFlashMessage('Modelo não encontrado.', 'error');
            $this->redirect('/admin/modelos-documentos');
            return;
        }
        if (!$this->podeEditarModelo($modelo)) {
            return;
        }
        $this->persist($id);
    }

    public function destroy(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'excluir', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? $_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/modelos-documentos');
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $this->service->excluir($id);
            $this->setFlashMessage('Modelo excluído.', 'success');
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        $this->redirect('/admin/modelos-documentos');
    }

    /** PDF de pré-visualização com dados fictícios (abre em nova aba). */
    public function preview(int $id): void
    {
        $modelo = $this->service->findById($id);
        if (!$modelo) {
            $this->setFlashMessage('Modelo não encontrado.', 'error');
            $this->redirect('/admin/modelos-documentos');
            return;
        }

        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'visualizar', false)) {
            return;
        }

        $vars = ModeloDocumentoService::varsExemplo();
        $estilo = ModeloDocumentoService::isModeloDeclaracao($modelo) ? 'declaracao' : 'simples';
        $html = $this->service->renderHtml($modelo, $vars, $estilo, $this->config);
        $orientacao = $this->service->orientacaoDompdf($modelo);
        $nome = preg_replace('/[^a-z0-9_-]+/i', '_', (string) ($modelo['codigo'] ?? 'modelo')) ?: 'modelo';

        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            // Remote off: evita SSRF via <img src="http://…"> no HTML editável pelo admin.
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $chroot = defined('BASE_PATH') ? (BASE_PATH . '/storage') : null;
            if (is_string($chroot) && is_dir($chroot)) {
                $options->setChroot($chroot);
            }

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $orientacao);
            $dompdf->render();
            $pdfBin = $dompdf->output();

            if (!headers_sent()) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="preview_' . $nome . '.pdf"');
                header('Content-Length: ' . strlen($pdfBin));
                header('Cache-Control: private, max-age=0, must-revalidate');
            }
            echo $pdfBin;
            exit;
        } catch (\Throwable $e) {
            ini_set('display_errors', (string) $oldDisplayErrors);
            $this->setFlashMessage('Falha ao gerar pré-visualização: ' . $e->getMessage(), 'error');
            $this->redirect('/admin/modelos-documentos/' . $id . '/edit');
        }
    }

    private function persist(?int $id): void
    {
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? $_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect($id ? '/admin/modelos-documentos/' . $id . '/edit' : '/admin/modelos-documentos/create');
            return;
        }

        try {
            $data = [
                'codigo' => $_POST['codigo'] ?? '',
                'nome' => $_POST['nome'] ?? '',
                'descricao' => $_POST['descricao'] ?? '',
                'cabecalho_html' => $_POST['cabecalho_html'] ?? '',
                'corpo_html' => $_POST['corpo_html'] ?? '',
                'rodape_html' => $_POST['rodape_html'] ?? '',
                'ativo' => isset($_POST['ativo']) ? 1 : 0,
                'orientacao' => ($_POST['orientacao'] ?? '') === 'paisagem' ? 'paisagem' : 'retrato',
            ];

            $codigoNovo = strtolower(trim((string) $data['codigo']));
            $ehDeclaracaoNova = str_starts_with($codigoNovo, 'declaracao_');
            if ($id === null || $id <= 0) {
                if ($ehDeclaracaoNova) {
                    throw new \InvalidArgumentException(
                        'Declarações e autorizações ainda não são gerenciadas nesta tela. Use códigos de contrato ou modelos customizados.'
                    );
                }
            } else {
                $existente = $this->service->findById($id);
                $eraDeclaracao = $existente && ModeloDocumentoService::isModeloDeclaracao($existente);
                if ($eraDeclaracao !== $ehDeclaracaoNova) {
                    throw new \InvalidArgumentException(
                        'Não é permitido mudar o código entre declaração/autorização e outros modelos nesta tela.'
                    );
                }
            }

            if (!empty($_POST['remover_imagem_cabecalho'])) {
                $data['imagem_cabecalho'] = '';
            } elseif (!empty($_FILES['imagem_cabecalho']['tmp_name'])) {
                $data['imagem_cabecalho'] = $this->salvarUploadImagem($_FILES['imagem_cabecalho'], 'cab');
            }
            if (!empty($_POST['remover_imagem_rodape'])) {
                $data['imagem_rodape'] = '';
            } elseif (!empty($_FILES['imagem_rodape']['tmp_name'])) {
                $data['imagem_rodape'] = $this->salvarUploadImagem($_FILES['imagem_rodape'], 'rod');
            }

            $savedId = $this->service->salvar($data, $id, $this->auth->getUser() ?: null);

            $this->setFlashMessage('Modelo salvo com sucesso.', 'success');
            $this->redirect('/admin/modelos-documentos/' . $savedId . '/edit');
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect($id ? '/admin/modelos-documentos/' . $id . '/edit' : '/admin/modelos-documentos/create');
        }
    }

    /** @param array<string,mixed> $modelo */
    private function podeEditarModelo(array $modelo): bool
    {
        return $this->enforceAdminPermissionKey('modelos_documentos', 'alterar', false);
    }

    /**
     * Upload de PNG/JPG — S3 se disponível; senão storage local com TENANT_SLUG.
     */
    private function salvarUploadImagem(array $file, string $prefixo): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload da imagem.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Imagem muito grande (máx. 5MB).');
        }

        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }
        }
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Use imagem PNG ou JPG (melhor compatibilidade com PDF).');
        }
        $ext = $allowed[$mime];
        $slug = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG) : 'tenant';
        if ($slug === '') {
            $slug = 'tenant';
        }
        $nome = $prefixo . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $key = $slug . '/modelos_documentos/' . $nome;

        require_once BASE_PATH . '/app/Services/MediaStorageService.php';
        $media = new \MediaStorageService($this->config);
        if ($media->isS3() && $media->put('arquivos', $key, $file['tmp_name'], $mime)) {
            return $key;
        }

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $dir = $base . '/storage/modelos_documentos/' . $slug;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar pasta de imagens.');
        }
        $dest = $dir . '/' . $nome;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            if (!copy($file['tmp_name'], $dest)) {
                throw new \RuntimeException('Falha ao gravar imagem localmente.');
            }
        }
        return 'storage/modelos_documentos/' . $slug . '/' . $nome;
    }

    private function renderForm(?array $modelo): void
    {
        $flash = $this->getFlashMessage();
        $previewCab = '';
        $previewRod = '';
        if ($modelo) {
            $previewCab = $this->service->resolverImagemSrc((string) ($modelo['imagem_cabecalho'] ?? ''), $this->config);
            $previewRod = $this->service->resolverImagemSrc((string) ($modelo['imagem_rodape'] ?? ''), $this->config);
        }
        $this->viewWithLayout('admin', 'admin/modelos-documentos/form', [
            'title' => ($modelo ? 'Editar' : 'Novo') . ' modelo de documento — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'modelos_documentos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'modelo' => $modelo,
            'placeholders' => ModeloDocumentoService::PLACEHOLDERS,
            'csrf_token' => $this->generateCsrfToken(),
            'schema_pronto' => $this->service->schemaReady(),
            'preview_cabecalho' => $previewCab,
            'preview_rodape' => $previewRod,
        ]);
    }
}
}
