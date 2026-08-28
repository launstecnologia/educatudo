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
        $categoria = $this->categoriaDaRequest();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/modelos-documentos/index', [
            'title' => 'Layout de documentos — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'modelos_documentos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'lista' => $this->service->listarPorCategoria($categoria, false),
            'categoria' => $categoria,
            'categorias' => ModeloDocumentoService::CATEGORIAS,
            'schema_pronto' => $this->service->schemaReady(),
            'layout_pronto' => $this->service->layoutPadraoReady(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function layout(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'visualizar', false)) {
            return;
        }
        $flash = $this->getFlashMessage();
        $layout = $this->service->getLayoutPadrao();
        $unidades = [];
        try {
            require_once BASE_PATH . '/app/Models/Education/SchoolUnit.php';
            $unidades = (new \SchoolUnit())->getActive() ?: [];
        } catch (\Throwable $e) {
            $unidades = [];
        }
        $this->viewWithLayout('admin', 'admin/modelos-documentos/layout', [
            'title' => 'Papel timbrado — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'modelos_documentos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'layout' => $layout,
            'unidades' => $unidades,
            'cargos' => ModeloDocumentoService::CARGOS_ASSINANTE,
            'layout_pronto' => $this->service->layoutPadraoReady(),
            'csrf_token' => $this->generateCsrfToken(),
            'preview_cabecalho' => $this->service->resolverImagemSrc((string) ($layout['imagem_cabecalho'] ?? ''), $this->config),
            'preview_rodape' => $this->service->resolverImagemSrc((string) ($layout['imagem_rodape'] ?? ''), $this->config),
        ]);
    }

    public function salvarLayout(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? $_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/modelos-documentos/layout');
            return;
        }
        try {
            $data = [
                'cabecalho_html' => $_POST['cabecalho_html'] ?? '',
                'rodape_html' => $_POST['rodape_html'] ?? '',
                'razao_social' => $_POST['razao_social'] ?? '',
                'cnpj' => $_POST['cnpj'] ?? '',
                'unidade_assinatura_id' => (int) ($_POST['unidade_assinatura_id'] ?? 0),
                'cargo_assinante' => $_POST['cargo_assinante'] ?? 'direcao',
                'assinante_nome' => $_POST['assinante_nome'] ?? '',
            ];
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
            $this->service->salvarLayoutPadrao($data, $this->auth->getUser() ?: null);
            $this->setFlashMessage('Papel timbrado salvo. Declarações e documentos oficiais que herdarem o layout passam a usar esta identidade.', 'success');
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        $this->redirect('/admin/modelos-documentos/layout');
    }

    public function create(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'cadastrar', false)) {
            return;
        }
        if (!empty($_GET['legado'])) {
            $this->renderForm(null);
            return;
        }
        $this->renderEditor(null);
    }

    public function edit(int $id): void
    {
        $modelo = $this->service->findById($id);
        if (!$modelo) {
            $this->setFlashMessage('Modelo não encontrado.', 'error');
            $this->redirect($this->urlLista());
            return;
        }
        if (!$this->podeEditarModelo($modelo)) {
            return;
        }
        if (!empty($_GET['legado'])) {
            $this->renderForm($modelo);
            return;
        }
        $this->renderEditor($modelo);
    }

    public function editor(?int $id = null): void
    {
        if ($id && $id > 0) {
            $this->edit($id);
            return;
        }
        $this->create();
    }

    public function salvarEstruturaNovo(): void
    {
        $this->persistirEstrutura(0);
    }

    public function salvarEstrutura(int $id): void
    {
        $this->persistirEstrutura($id);
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
            $this->redirect($this->urlLista());
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
            $this->redirect($this->urlLista());
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $this->service->excluir($id);
            $this->setFlashMessage('Modelo excluído.', 'success');
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        $this->redirect($this->urlLista());
    }

    /** PDF de pré-visualização com dados fictícios (abre em nova aba). */
    public function preview(int $id): void
    {
        $modelo = $this->service->findById($id);
        if (!$modelo) {
            $this->setFlashMessage('Modelo não encontrado.', 'error');
            $this->redirect($this->urlLista());
            return;
        }

        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'visualizar', false)) {
            return;
        }

        $vars = ModeloDocumentoService::varsExemplo();
        $estilo = ModeloDocumentoService::estiloDoModelo($modelo);
        $html = $this->service->renderHtml($modelo, $vars, $estilo, $this->config);
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
            $this->service->aplicarPapelDompdf($dompdf, $modelo);
            $dompdf->render();
            $pdfBin = $dompdf->output();

            if (!headers_sent()) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="preview_' . $nome . '.pdf"');
                header('Content-Length: ' . strlen($pdfBin));
                header('Cache-Control: no-store, no-cache, must-revalidate');
                header('Pragma: no-cache');
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
            $this->redirect($id ? '/admin/modelos-documentos/' . $id . '/edit' : '/admin/modelos-documentos/create' . $this->queryCategoria());
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
                'formato_papel' => $_POST['formato_papel'] ?? 'a4',
                'margem_mm' => $_POST['margem_mm'] ?? 20,
                'espacamento_linha' => $_POST['espacamento_linha'] ?? 1.5,
                'usar_layout_padrao' => isset($_POST['usar_layout_padrao']) ? 1 : 0,
            ];

            $codigoNovo = $this->service->normalizarCodigo((string) $data['codigo']);
            $data['codigo'] = $codigoNovo;
            if (($id === null || $id <= 0) && ModeloDocumentoService::isCodigoSistema($codigoNovo)) {
                throw new \InvalidArgumentException(
                    'Este código pertence a um modelo do sistema. Edite o modelo existente em vez de criar outro com o mesmo código.'
                );
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
            $this->redirect($id ? '/admin/modelos-documentos/' . $id . '/edit' : '/admin/modelos-documentos/create' . $this->queryCategoria());
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

    private function persistirEstrutura(int $id): void
    {
        $perm = $id > 0 ? 'alterar' : 'cadastrar';
        if (!$this->enforceAdminPermissionKey('modelos_documentos', $perm, true)) {
            return;
        }
        $body = $this->lerJson();
        $token = (string) ($body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$this->validateCsrf($token)) {
            $this->json(['ok' => false, 'error' => 'Sessão expirada.'], 403);
            return;
        }
        if ($id > 0) {
            $modelo = $this->service->findById($id);
            if (!$modelo) {
                $this->json(['ok' => false, 'error' => 'Modelo não encontrado.'], 404);
                return;
            }
        }
        $estrutura = $body['estrutura'] ?? null;
        if (!is_array($estrutura)) {
            $this->json(['ok' => false, 'error' => 'Estrutura inválida.'], 400);
            return;
        }
        try {
            $saved = $this->service->salvarEstrutura($id, $estrutura, $body, $this->auth->getUser() ?: null);
            $this->json(['ok' => true, 'id' => $saved]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('ModeloDocumento salvarEstrutura: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Não foi possível salvar o modelo.'], 400);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function lerJson(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return $_POST;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function renderEditor(?array $modelo): void
    {
        $categoria = $modelo
            ? ModeloDocumentoService::categoriaDoCodigo((string) ($modelo['codigo'] ?? ''))
            : $this->categoriaDaRequest();
        $estrutura = $modelo
            ? $this->service->estruturaDoModelo($modelo)
            : ModeloDocumentoService::estruturaVazia();
        $layoutSugerido = ModeloDocumentoService::estruturaSugeridaParaCodigo(
            (string) ($modelo['codigo'] ?? '')
        );
        $logoPreview = '';
        try {
            $logoPreview = $this->service->logoHtmlInstitucional(null, $this->config);
            if (preg_match('/src="([^"]+)"/', $logoPreview, $m)) {
                $logoPreview = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            } else {
                $logoPreview = '';
            }
        } catch (\Throwable $e) {
            $logoPreview = '';
        }
        $this->view('admin/modelos-documentos/editor', [
            'title' => ($modelo ? 'Editar' : 'Novo') . ' modelo — EducaTudo',
            'modelo' => $modelo ?: [],
            'estrutura' => $estrutura,
            'catalogo' => ModeloDocumentoService::catalogoElementosEditor(),
            'placeholders' => ModeloDocumentoService::PLACEHOLDERS,
            'grupos_placeholders' => ModeloDocumentoService::gruposPlaceholders(),
            'categoria' => $categoria,
            'csrf_token' => $this->generateCsrfToken(),
            'codigo_sistema' => $modelo ? ModeloDocumentoService::isCodigoSistema((string) ($modelo['codigo'] ?? '')) : false,
            'vars_preview' => ModeloDocumentoService::varsExemplo(),
            'logo_preview' => $logoPreview,
            'layout_sugerido' => $layoutSugerido,
        ]);
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
        $categoria = $modelo
            ? ModeloDocumentoService::categoriaDoCodigo((string) ($modelo['codigo'] ?? ''))
            : $this->categoriaDaRequest();
        $this->viewWithLayout('admin', 'admin/modelos-documentos/form', [
            'title' => ($modelo ? 'Editar' : 'Novo') . ' modelo de documento — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'modelos_documentos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'modelo' => $modelo,
            'placeholders' => ModeloDocumentoService::PLACEHOLDERS,
            'grupos_placeholders' => ModeloDocumentoService::gruposPlaceholders(),
            'blocos' => ModeloDocumentoService::blocosEditor(),
            'estruturas' => ModeloDocumentoService::estruturasEditor(),
            'categoria' => $categoria,
            'categorias' => ModeloDocumentoService::CATEGORIAS,
            'csrf_token' => $this->generateCsrfToken(),
            'schema_pronto' => $this->service->schemaReady(),
            'layout_pronto' => $this->service->layoutPadraoReady(),
            'preview_cabecalho' => $previewCab,
            'preview_rodape' => $previewRod,
        ]);
    }

    private function categoriaDaRequest(): string
    {
        $raw = strtolower(trim((string) ($_GET['categoria'] ?? $_POST['categoria'] ?? 'todos')));
        if ($raw === '' || $raw === 'todos') {
            return 'todos';
        }
        return isset(ModeloDocumentoService::CATEGORIAS[$raw]) ? $raw : 'declaracao';
    }

    private function queryCategoria(): string
    {
        $cat = $this->categoriaDaRequest();
        return $cat !== '' ? ('?categoria=' . rawurlencode($cat)) : '';
    }

    private function urlLista(): string
    {
        return '/admin/modelos-documentos' . $this->queryCategoria();
    }
}
}
