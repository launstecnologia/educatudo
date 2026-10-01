<?php
/**
 * Admin — CRUD de modelos de documentos (HTML editável + placeholders).
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/ModeloDocumentoService.php';
require_once __DIR__ . '/../Services/ImportadorModeloPlanilhaService.php';
require_once __DIR__ . '/../Services/DemonstracaoDocumentoService.php';

use App\Modulos\ModelosDocumentos\Services\DemonstracaoDocumentoService;
use App\Modulos\ModelosDocumentos\Services\ImportadorModeloPlanilhaService;
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

    /**
     * Turmas, alunos e notas reais para a demonstração do editor.
     */
    public function demonstracao(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'visualizar')) {
            return;
        }
        $turmaId = max(0, (int) ($_GET['turma_id'] ?? 0));
        $alunoId = max(0, (int) ($_GET['aluno_id'] ?? 0));
        $chave = (string) ($_GET['chave'] ?? '1127');
        try {
            $pacote = (new DemonstracaoDocumentoService($this->db))->pacote($turmaId, $alunoId, $chave);
            $this->json($pacote);
        } catch (\Throwable $e) {
            error_log('ModeloDocumento demonstracao: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Não foi possível carregar os dados da demonstração.'], 400);
        }
    }

    /**
     * Envia a foto de um documento para a IA montar o layout do editor.
     */
    public function reproduzirImagem(): void
    {
        if (!class_exists('AdminPermissionMatrix')) {
            require_once __DIR__ . '/../../../Core/AdminPermissionMatrix.php';
        }
        $user = $this->auth->getUser() ?: [];
        $permissions = \AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user);
        $pode = \AdminPermissionMatrix::can($permissions, 'modelos_documentos', 'alterar')
            || \AdminPermissionMatrix::can($permissions, 'modelos_documentos', 'cadastrar');
        if (!$pode) {
            $this->json(['success' => false, 'error' => 'Sem permissão para esta ação.'], 403);
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? $_POST['csrf_token'] ?? ''))) {
            $this->json(['success' => false, 'error' => 'Sessão expirada.'], 403);
            return;
        }

        $file = $_FILES['imagem'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'error' => 'Envie uma imagem do documento (foto ou scan).'], 400);
            return;
        }
        if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
            $this->json(['success' => false, 'error' => 'Imagem muito grande. Use até 8 MB.'], 400);
            return;
        }

        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, (string) ($file['tmp_name'] ?? ''));
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            $this->json(['success' => false, 'error' => 'Use JPG, PNG ou WebP.'], 400);
            return;
        }

        require_once __DIR__ . '/../../../Services/AIJobService.php';
        try {
            $slug = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG) : 'tenant';
            if (!is_string($slug) || $slug === '') {
                $slug = 'tenant';
            }
            $base = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4)) . '/storage/tmp/modelos_documentos_ia/' . $slug;
            if (!is_dir($base) && !mkdir($base, 0700, true) && !is_dir($base)) {
                throw new \RuntimeException('Não foi possível preparar o envio da imagem.');
            }
            $path = $base . '/doc-' . (int) ($user['id'] ?? 0) . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            if (!move_uploaded_file((string) $file['tmp_name'], $path)) {
                throw new \RuntimeException('Não foi possível salvar a imagem para processamento.');
            }
            @chmod($path, 0600);

            $jobId = \App\Services\AIJobService::enqueue('modelo_documento_reproduzir_imagem', [
                'arquivo' => [
                    'path' => $path,
                    'mime' => $mime,
                    'slug' => $slug,
                ],
            ], (int) ($user['id'] ?? 0), 'admin');

            $this->json([
                'success' => true,
                'job_id' => $jobId,
                'message' => 'Imagem enviada. A IA está montando o layout.',
            ]);
        } catch (\Throwable $e) {
            if (isset($path) && is_file($path)) {
                @unlink($path);
            }
            error_log('Reproduzir modelo por imagem: ' . $e->getMessage());
            $avisos = [
                'Não foi possível preparar o envio da imagem.',
                'Não foi possível salvar a imagem para processamento.',
            ];
            $aviso = in_array($e->getMessage(), $avisos, true)
                ? $e->getMessage()
                : 'Não foi possível enviar a imagem.';
            $this->json(['success' => false, 'error' => $aviso], 500);
        }
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
        $vars['_pdf_teste'] = true;
        $estilo = ModeloDocumentoService::estiloDoModelo($modelo);
        $html = $this->service->renderHtml($modelo, $vars, $estilo, $this->config);
        $nome = preg_replace('/[^a-z0-9_-]+/i', '_', (string) ($modelo['codigo'] ?? 'modelo')) ?: 'modelo';

        require_once __DIR__ . '/../Services/GeradorPdfFolhaService.php';
        $pdfBin = (new \App\Modulos\ModelosDocumentos\Services\GeradorPdfFolhaService())->gerarSeFolhaOficial($html);

        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!is_string($pdfBin)) {
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
            }

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
            'ia_disponivel' => true,
            'cursos_emissao' => $this->service->listarCursosParaEmissao(),
            'series_emissao' => $this->service->listarSeriesParaEmissao(),
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

    public function importar(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'cadastrar', false)) {
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/modelos-documentos/importar', [
            'title' => 'Importar modelo — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'modelos_documentos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'csrf_token' => $this->generateCsrfToken(),
            'catalogo' => ImportadorModeloPlanilhaService::catalogo(),
        ]);
    }

    public function aplicarCatalogo(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? $_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/modelos-documentos/importar');
            return;
        }
        $chave = trim((string) ($_POST['chave'] ?? ''));
        $meta = ImportadorModeloPlanilhaService::catalogo()[$chave] ?? null;
        if (!is_array($meta)) {
            $this->setFlashMessage('Modelo oficial desconhecido.', 'error');
            $this->redirect('/admin/modelos-documentos/importar');
            return;
        }
        try {
            $importador = new ImportadorModeloPlanilhaService();
            $estrutura = $importador->estruturaDoCatalogo($chave);
            $meta['chave'] = $chave;
            $aplicarEmissao = !empty($_POST['aplicar_emissao']);
            $user = $this->auth->getUser() ?: null;
            $gravado = $this->gravarModeloImportado($meta, $estrutura, $aplicarEmissao, $user);
            $this->setFlashMessage($this->mensagemGravados([$gravado]), 'success');
            $this->redirect('/admin/modelos-documentos/' . (int) $gravado['id'] . '/editor');
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/admin/modelos-documentos/importar');
        }
    }

    public function processarImportacao(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? $_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/modelos-documentos/importar');
            return;
        }
        $arquivo = $_FILES['planilha'] ?? null;
        if (!is_array($arquivo)) {
            $this->setFlashMessage('Selecione a planilha do modelo.', 'error');
            $this->redirect('/admin/modelos-documentos/importar');
            return;
        }
        try {
            $caminho = $this->validarPlanilha($arquivo);
            $analise = (new ImportadorModeloPlanilhaService())->analisarArquivo($caminho);
            $aplicarEmissao = !empty($_POST['aplicar_emissao']);
            $user = $this->auth->getUser() ?: null;
            $gravados = [];
            $pendentes = [];
            $chaves = [];
            $emissoes = [];
            foreach ($analise as $folha) {
                if (!empty($folha['reconhecido'])) {
                    $chave = (string) ($folha['chave'] ?? '');
                    if ($chave === '' || isset($chaves[$chave])) {
                        continue;
                    }
                    $chaves[$chave] = true;
                    $meta = is_array($folha['meta'] ?? null) ? $folha['meta'] : [];
                    $estrutura = is_array($folha['estrutura'] ?? null) ? $folha['estrutura'] : [];
                    $codigoEmissao = (string) ($meta['codigo_emissao'] ?? '');
                    $aplicarEsta = $aplicarEmissao && $codigoEmissao !== '' && !isset($emissoes[$codigoEmissao]);
                    if ($aplicarEsta) {
                        $emissoes[$codigoEmissao] = true;
                    }
                    $gravados[] = $this->gravarModeloImportado($meta, $estrutura, $aplicarEsta, $user);
                    continue;
                }
                $pendentes[] = $folha;
            }
            if ($pendentes !== []) {
                $_SESSION['importacao_modelo_planilha'] = [
                    'pendentes' => $pendentes,
                    'aplicar_emissao' => $aplicarEmissao,
                    'gravados' => $gravados,
                ];
                $this->redirect('/admin/modelos-documentos/importar/conferir');
                return;
            }
            if ($gravados === []) {
                throw new \InvalidArgumentException('Nenhuma folha pôde ser importada.');
            }
            $this->setFlashMessage($this->mensagemGravados($gravados), 'success');
            $destino = $gravados[0];
            foreach ($gravados as $item) {
                if (!empty($item['emissao'])) {
                    $destino = $item;
                    break;
                }
            }
            $this->redirect('/admin/modelos-documentos/' . (int) $destino['id'] . '/editor');
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/admin/modelos-documentos/importar');
        }
    }

    public function conferirImportacao(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'cadastrar', false)) {
            return;
        }
        $pacote = $_SESSION['importacao_modelo_planilha'] ?? null;
        if (!is_array($pacote) || !is_array($pacote['pendentes'] ?? null) || $pacote['pendentes'] === []) {
            $this->setFlashMessage('Não há planilha pendente de conferência.', 'error');
            $this->redirect('/admin/modelos-documentos/importar');
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/modelos-documentos/conferir', [
            'title' => 'Conferir importação — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'modelos_documentos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'csrf_token' => $this->generateCsrfToken(),
            'pendentes' => $pacote['pendentes'],
            'gravados' => is_array($pacote['gravados'] ?? null) ? $pacote['gravados'] : [],
            'placeholders' => ModeloDocumentoService::PLACEHOLDERS,
        ]);
    }

    public function confirmarImportacao(): void
    {
        if (!$this->enforceAdminPermissionKey('modelos_documentos', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? $_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/modelos-documentos/importar');
            return;
        }
        $pacote = $_SESSION['importacao_modelo_planilha'] ?? null;
        if (!is_array($pacote)) {
            $this->setFlashMessage('A conferência expirou. Envie a planilha de novo.', 'error');
            $this->redirect('/admin/modelos-documentos/importar');
            return;
        }
        try {
            $rotulos = $_POST['rotulo'] ?? [];
            $placeholders = $_POST['placeholder'] ?? [];
            if (!is_array($rotulos) || !is_array($placeholders)) {
                throw new \InvalidArgumentException('Conferência inválida.');
            }
            $campos = [];
            foreach ($rotulos as $i => $rotulo) {
                $campos[] = [
                    'rotulo' => (string) $rotulo,
                    'placeholder' => (string) ($placeholders[$i] ?? ''),
                ];
            }
            $nome = trim((string) ($_POST['nome'] ?? ''));
            if ($nome === '') {
                $nome = 'Modelo importado da planilha';
            }
            $orientacao = (($_POST['orientacao'] ?? '') === 'paisagem') ? 'paisagem' : 'retrato';
            $estrutura = (new ImportadorModeloPlanilhaService())->estruturaConferida($nome, $orientacao, $campos);
            $id = $this->service->salvarEstrutura(0, $estrutura, [
                'codigo' => 'planilha_' . date('YmdHis'),
                'nome' => $nome,
                'descricao' => 'Importado de planilha com conferência de campos.',
                'orientacao' => $orientacao,
                'formato_papel' => 'a4',
                'margem_mm' => 12,
                'usar_layout_padrao' => 0,
                'ativo' => 1,
            ], $this->auth->getUser() ?: null);
            unset($_SESSION['importacao_modelo_planilha']);
            $this->setFlashMessage('Modelo gravado com os campos conferidos.', 'success');
            $this->redirect('/admin/modelos-documentos/' . $id . '/editor');
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/admin/modelos-documentos/importar/conferir');
        }
    }

    /**
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $estrutura
     * @param array<string,mixed>|null $user
     * @return array{id:int,nome:string,emissao:bool}
     */
    private function gravarModeloImportado(array $meta, array $estrutura, bool $aplicarEmissao, ?array $user): array
    {
        $codigo = (string) ($meta['codigo'] ?? '');
        $nome = (string) ($meta['nome'] ?? 'Modelo importado');
        $orientacao = (($meta['orientacao'] ?? '') === 'paisagem') ? 'paisagem' : 'retrato';
        $existente = $this->service->findPorCodigo($codigo);
        $id = $this->service->salvarEstrutura((int) ($existente['id'] ?? 0), $estrutura, [
            'codigo' => $codigo,
            'nome' => $nome,
            'descricao' => 'Layout oficial importado da planilha ' . (string) ($meta['seed'] ?? '') . '.',
            'orientacao' => $orientacao,
            'formato_papel' => 'a4',
            'margem_mm' => 8,
            'usar_layout_padrao' => 0,
            'ativo' => 1,
        ], $user);

        $emissao = false;
        $codigoEmissao = (string) ($meta['codigo_emissao'] ?? '');
        if ($aplicarEmissao && $codigoEmissao !== '') {
        $oficial = $this->service->findPorCodigo($codigoEmissao);
        $idOficial = (int) ($oficial['id'] ?? 0);
        if ($idOficial <= 0) {
            $idOficial = $this->service->salvar([
                'codigo' => $codigoEmissao,
                'nome' => $nome,
                'descricao' => 'Layout aplicado pela importação ' . (string) ($meta['seed'] ?? ''),
                'cabecalho_html' => '',
                'corpo_html' => '<p>&nbsp;</p>',
                'rodape_html' => '',
                'ativo' => 1,
                'orientacao' => $orientacao,
                'formato_papel' => 'a4',
                'margem_mm' => 8,
                'usar_layout_padrao' => 0,
            ], null, $user);
        }
        $nomeOficial = is_array($oficial) ? (string) ($oficial['nome'] ?? $nome) : $nome;
        $descOficial = is_array($oficial)
            ? (string) ($oficial['descricao'] ?? ('Layout aplicado pela importação ' . ($meta['seed'] ?? '')))
            : ('Layout aplicado pela importação ' . (string) ($meta['seed'] ?? ''));
        $this->service->salvarEstrutura($idOficial, $estrutura, [
                'codigo' => $codigoEmissao,
                'nome' => $nomeOficial,
                'descricao' => $descOficial,
                'orientacao' => $orientacao,
                'formato_papel' => 'a4',
                'margem_mm' => 8,
                'usar_layout_padrao' => 0,
                'ativo' => 1,
            ], $user);
            $emissao = true;
        }

        return ['id' => $id, 'nome' => $nome, 'emissao' => $emissao];
    }

    /**
     * @param list<array{id:int,nome:string,emissao:bool}> $gravados
     */
    private function mensagemGravados(array $gravados): string
    {
        $nomes = [];
        foreach ($gravados as $item) {
            $nomes[] = (string) ($item['nome'] ?? 'modelo');
        }
        $msg = 'Modelo importado: ' . implode('; ', $nomes) . '.';
        foreach ($gravados as $item) {
            if (!empty($item['emissao'])) {
                $msg .= ' A emissão oficial passou a usar este layout.';
                break;
            }
        }
        return $msg;
    }

    /**
     * @param array<string,mixed> $arquivo
     */
    private function validarPlanilha(array $arquivo): string
    {
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload da planilha.');
        }
        $tmp = (string) ($arquivo['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Arquivo de upload inválido.');
        }
        if (($arquivo['size'] ?? 0) > 8 * 1024 * 1024) {
            throw new \RuntimeException('Planilha muito grande (máx. 8 MB).');
        }
        $nome = (string) ($arquivo['name'] ?? '');
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xlsm'], true)) {
            throw new \RuntimeException('Envie um arquivo .xlsx ou .xlsm.');
        }
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }
        }
        $permitidos = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel.sheet.macroenabled.12',
            'application/vnd.ms-excel.sheet.macroEnabled.12',
            'application/zip',
            'application/octet-stream',
        ];
        if (!in_array($mime, $permitidos, true)) {
            throw new \RuntimeException('O arquivo não parece uma planilha do Excel.');
        }
        $cabeca = (string) file_get_contents($tmp, false, null, 0, 2);
        if ($cabeca !== 'PK') {
            throw new \RuntimeException('O arquivo não é uma planilha Office Open XML.');
        }
        return $tmp;
    }
}
}
