<?php
/**
 * EducaTudo - IA da Apostila (Admin)
 * Módulo NOVO e independente do módulo "Apostilas" (Apostilas/AdminApostilaController),
 * que continua existindo separadamente (upload simples de PDF/imagem, tabela modulos_apostilas).
 * Este módulo usa as tabelas apostilas_ia / apostila_ia_* e delega o processamento de IA
 * (extração de páginas, chunking, exercícios, chat) ao microserviço Python via
 * ApostilaIaClientService.
 */

require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('ApostilaIaAdminController')) {
class ApostilaIaAdminController extends AdminBaseController
{
    private const PERMISSION_KEY = 'apostilas_ia';
    private const MAX_UPLOAD_BYTES = 200 * 1024 * 1024; // 200MB, espelha MAX_UPLOAD_MB do microserviço Python
    private const UPLOAD_SUBDIR = 'apostilas-ia';

    private function tenantSlug(): string
    {
        if (defined('TENANT_SLUG') && trim((string)TENANT_SLUG) !== '') {
            return trim((string)TENANT_SLUG);
        }
        return trim((string)env('SCHOOL_CODE', 'default'));
    }

    /**
     * Diretório onde o PDF é salvo no disco local (lado PHP). Por padrão fica dentro
     * do storage/ do próprio app — mas o microserviço Python roda em container Docker
     * separado e só vê o volume montado em seu próprio docker-compose.yml. Por isso,
     * em produção, APOSTILA_AI_SHARED_UPLOADS_PATH deve apontar para esse volume
     * compartilhado (ex.: /opt/apostila-ai/app/storage/uploads) para que o PDF salvo
     * aqui seja visível de dentro do container quando ele processar pdf_path.
     */
    private function uploadBaseDir(): string
    {
        $shared = trim((string)env('APOSTILA_AI_SHARED_UPLOADS_PATH', ''));
        if ($shared !== '') {
            return rtrim($shared, '/');
        }
        return __DIR__ . '/../../../storage/uploads/' . self::UPLOAD_SUBDIR;
    }

    /**
     * Caminho equivalente, mas como visto de DENTRO do container Python (via volume
     * montado em docker-compose.yml: ./app/storage:/app/app/storage). Usado apenas
     * para montar o pdf_path enviado ao microserviço — nunca para acesso local.
     */
    private function containerUploadsPath(): string
    {
        return rtrim((string)env('APOSTILA_AI_CONTAINER_UPLOADS_PATH', '/app/app/storage/uploads'), '/');
    }

    private function detectMime(string $tmpPath): string
    {
        if (class_exists('finfo')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $tmpPath);
                @finfo_close($finfo);
                if (!empty($mime)) {
                    return (string)$mime;
                }
            }
        }
        return 'application/octet-stream';
    }

    private function readItems(): array
    {
        $items = $this->db->fetchAll(
            "SELECT ai.id, ai.titulo, ai.serie_id, ai.turma_id, ai.disciplina_id, ai.professor_id,
                    ai.status, ai.total_paginas, ai.erro, ai.created_at, ai.updated_at, ai.arquivo_pdf,
                    t.nome AS turma_nome, m.nome AS disciplina_nome,
                    p.nome AS professor_nome,
                    s.nome AS serie_nome, c.nome AS curso_nome
             FROM apostilas_ia ai
             LEFT JOIN turmas t ON t.id = ai.turma_id
             LEFT JOIN materias m ON m.id = ai.disciplina_id
             LEFT JOIN professores p ON p.id = ai.professor_id
             LEFT JOIN serie s ON s.id = ai.serie_id
             LEFT JOIN curso c ON c.id = s.curso_id
             ORDER BY ai.created_at DESC, ai.id DESC"
        );

        if (empty($items)) {
            return $items;
        }

        $ids = array_map(static fn($item) => (int)$item['id'], $items);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $turmasPorApostila = [];
        $rows = $this->db->fetchAll(
            "SELECT ait.apostila_id, t.nome
             FROM apostila_ia_turmas ait
             JOIN turmas t ON t.id = ait.turma_id
             WHERE ait.apostila_id IN ($placeholders)
             ORDER BY t.nome ASC",
            $ids
        );
        foreach ($rows as $row) {
            $turmasPorApostila[(int)$row['apostila_id']][] = $row['nome'];
        }

        foreach ($items as &$item) {
            $item['turmas_nomes'] = $turmasPorApostila[(int)$item['id']] ?? [];
            $item['pdf_disponivel'] = $this->pdfDisponivel((string)($item['arquivo_pdf'] ?? ''));
            $item['is_legado'] = strpos((string)($item['arquivo_pdf'] ?? ''), 'legado:') === 0;
        }
        unset($item);

        return $items;
    }

    /**
     * Extrai o MediaStorageService key de um caminho legado.
     * "apostilas/apostila_xxx.pdf" → "apostila_xxx.pdf" (a chave que o MediaStorageService usa).
     */
    private function legadoMediaKey(string $caminho): string
    {
        $path = ltrim(str_replace('\\', '/', $caminho), '/');
        if (strpos($path, 'apostilas/') === 0) {
            return substr($path, strlen('apostilas/'));
        }
        return basename($path);
    }

    /**
     * Serve o PDF de uma apostila legada (modulos_apostilas) via MediaStorageService,
     * suportando tanto S3 quanto disco local — igual ao StudentApostilaController original.
     * Retorna true se serviu o arquivo, false se não encontrou.
     */
    private function servirPdfLegado(string $arquivoPdf): bool
    {
        $caminho = substr($arquivoPdf, strlen('legado:'));
        if ($caminho === '' || strpos($caminho, '..') !== false || strpos($caminho, 'sem-arquivo') !== false) {
            return false;
        }
        $mediaKey = $this->legadoMediaKey($caminho);
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        if ($media->isS3()) {
            return (bool) $media->streamInline('apostilas', $mediaKey, basename($caminho), 'application/pdf');
        }
        $path = $media->getLocalPath('apostilas', $mediaKey);
        if ($path !== null && is_file($path)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($caminho) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            return true;
        }
        return false;
    }

    /**
     * Indica se o PDF original existe no volume acessível ao PHP. Para legados,
     * usa MediaStorageService (suporta S3 e local). Para novos, checa disco direto.
     */
    private function pdfDisponivel(string $relativo): bool
    {
        if ($relativo === '' || strpos($relativo, '..') !== false) {
            return false;
        }
        if (strpos($relativo, 'legado:') === 0) {
            $caminho = substr($relativo, strlen('legado:'));
            if ($caminho === '' || strpos($caminho, 'sem-arquivo') !== false) {
                return false;
            }
            $mediaKey = $this->legadoMediaKey($caminho);
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $media = new MediaStorageService($this->config);
            if ($media->isS3()) {
                return $media->exists('apostilas', $mediaKey);
            }
            $path = $media->getLocalPath('apostilas', $mediaKey);
            return $path !== null && is_file($path);
        }
        return is_file($this->uploadBaseDir() . '/' . $relativo);
    }

    private function findItemById(int $id): ?array
    {
        $item = $this->db->fetch(
            "SELECT ai.* FROM apostilas_ia ai WHERE ai.id = :id LIMIT 1",
            ['id' => $id]
        );
        return $item ?: null;
    }

    /**
     * Serve uma imagem de capa personalizada (JPEG/PNG/WEBP) do disco ou S3.
     * Retorna true se serviu, false se não encontrou.
     */
    private function servirCapaPersonalizada(string $capaPath): bool
    {
        if ($capaPath === '' || strpos($capaPath, '..') !== false) {
            return false;
        }
        $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $ext = strtolower(pathinfo($capaPath, PATHINFO_EXTENSION));
        $mime = $mimeMap[$ext] ?? 'image/jpeg';

        $fullPath = $this->uploadBaseDir() . '/' . $capaPath;
        if (is_file($fullPath)) {
            header('Content-Type: ' . $mime);
            header('Cache-Control: private, max-age=86400');
            header('Content-Length: ' . filesize($fullPath));
            readfile($fullPath);
            return true;
        }
        return false;
    }

    private function getTurmasAtivas(): array
    {
        return $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC");
    }

    private function getMateriasAtivas(): array
    {
        return $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome ASC");
    }

    private function getProfessoresAtivos(): array
    {
        return $this->db->fetchAll("SELECT id, nome, email FROM professores WHERE ativo = 1 ORDER BY nome ASC");
    }

    /**
     * Série = ano (ex.: "1º Ano"), sempre dentro de um Curso = nível (ex.: "Ensino
     * Médio"). Esta é a estrutura acadêmica real e ativa da plataforma (menu Admin
     * → Acadêmico → Curso / Série / Turmas; turmas.serie_id referencia esta mesma
     * tabela `serie`). O nome da série isolado é ambíguo (existe "1º Ano" tanto no
     * Ensino Fundamental quanto no Ensino Médio), por isso sempre exibimos junto o
     * nome do curso (nível).
     */
    private function getSeriesAtivas(): array
    {
        return $this->db->fetchAll(
            "SELECT s.id, s.nome, c.nome AS curso_nome
             FROM serie s
             JOIN curso c ON c.id = s.curso_id
             WHERE s.ativo = 1
             ORDER BY c.ordem ASC, s.ordem ASC, s.nome ASC"
        );
    }

    public function index()
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'visualizar', false)) {
            return;
        }

        $this->viewWithLayout('admin', 'admin/apostilas-ia/index', [
            'title' => 'Meu Material - Admin',
            'page_title' => 'Meu Material',
            'user' => $this->auth->getUser(),
            'items' => $this->readItems(),
            'current_page' => 'apostilas-ia',
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function criar()
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'cadastrar', false)) {
            return;
        }

        $this->viewWithLayout('admin', 'admin/apostilas-ia/criar', [
            'title' => 'Novo Material - Admin',
            'page_title' => 'Meu Material',
            'user' => $this->auth->getUser(),
            'series' => $this->getSeriesAtivas(),
            'turmas' => $this->getTurmasAtivas(),
            'materias' => $this->getMateriasAtivas(),
            'professores' => $this->getProfessoresAtivos(),
            'current_page' => 'apostilas-ia',
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function upload()
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'cadastrar', false)) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/apostilas-ia/criar');
            return;
        }

        if (!isset($_FILES['arquivo']) || (int)($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->setFlashMessage('Selecione um arquivo PDF válido para enviar.', 'error');
            $this->redirect('/admin/apostilas-ia/criar');
            return;
        }

        $file = $_FILES['arquivo'];
        $originalName = trim((string)($file['name'] ?? ''));
        $tmpPath = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $serieId = (int)($_POST['serie_id'] ?? 0);
        $turmaIdsRaw = $_POST['turma_ids'] ?? ($_POST['turma_id'] ?? []);
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', (array)$turmaIdsRaw), static fn($v) => $v > 0)));
        $disciplinaId = (int)($_POST['disciplina_id'] ?? 0);
        $professorId = (int)($_POST['professor_id'] ?? 0);

        if ($titulo === '') {
            $this->setFlashMessage('Informe um título para a apostila.', 'error');
            $this->redirect('/admin/apostilas-ia/criar');
            return;
        }

        if ($originalName === '' || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $this->setFlashMessage('Arquivo inválido.', 'error');
            $this->redirect('/admin/apostilas-ia/criar');
            return;
        }

        if ($size > self::MAX_UPLOAD_BYTES) {
            $this->setFlashMessage('Arquivo excede o tamanho máximo permitido (200MB).', 'error');
            $this->redirect('/admin/apostilas-ia/criar');
            return;
        }

        // Nunca confiar na extensão ou no $_FILES['type'] declarado pelo navegador — valida o MIME real do conteúdo.
        $mime = $this->detectMime($tmpPath);
        if ($mime !== 'application/pdf') {
            $this->setFlashMessage('Apenas arquivos PDF são aceitos para a IA da Apostila.', 'error');
            $this->redirect('/admin/apostilas-ia/criar');
            return;
        }

        if ($serieId > 0) {
            $serie = $this->db->fetch("SELECT id FROM serie WHERE id = :id AND ativo = 1 LIMIT 1", ['id' => $serieId]);
            if (!$serie) {
                $this->setFlashMessage('Série selecionada é inválida.', 'error');
                $this->redirect('/admin/apostilas-ia/criar');
                return;
            }
        }
        foreach ($turmaIds as $turmaId) {
            $turma = $this->db->fetch("SELECT id FROM turmas WHERE id = :id AND ativo = 1 LIMIT 1", ['id' => $turmaId]);
            if (!$turma) {
                $this->setFlashMessage('Uma das turmas selecionadas é inválida.', 'error');
                $this->redirect('/admin/apostilas-ia/criar');
                return;
            }
        }
        if ($disciplinaId > 0) {
            $materia = $this->db->fetch("SELECT id FROM materias WHERE id = :id LIMIT 1", ['id' => $disciplinaId]);
            if (!$materia) {
                $this->setFlashMessage('Disciplina selecionada é inválida.', 'error');
                $this->redirect('/admin/apostilas-ia/criar');
                return;
            }
        }
        if ($professorId > 0) {
            $professor = $this->db->fetch("SELECT id FROM professores WHERE id = :id AND ativo = 1 LIMIT 1", ['id' => $professorId]);
            if (!$professor) {
                $this->setFlashMessage('Professor selecionado é inválido.', 'error');
                $this->redirect('/admin/apostilas-ia/criar');
                return;
            }
        }

        // Prefixo de tenant no path físico (regra de segurança do CLAUDE.md): storage/uploads/apostilas-ia/{TENANT_SLUG}/...
        $tenantSlug = $this->tenantSlug();
        $dir = $this->uploadBaseDir() . '/' . $tenantSlug;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->setFlashMessage('Falha ao preparar diretório de armazenamento.', 'error');
            $this->redirect('/admin/apostilas-ia/criar');
            return;
        }

        $safeName = 'apostila_ia_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $destPath = $dir . '/' . $safeName;

        if (!@move_uploaded_file($tmpPath, $destPath)) {
            $this->setFlashMessage('Falha ao salvar o arquivo enviado.', 'error');
            $this->redirect('/admin/apostilas-ia/criar');
            return;
        }

        // Identificador relativo persistido no banco (tenant/arquivo) — usado para
        // reconstruir tanto o caminho físico local quanto o caminho visto pelo
        // container Python (containerUploadsPath()), nunca um caminho absoluto fixo.
        $relativePath = $tenantSlug . '/' . $safeName;
        $pdfPathForPython = $this->containerUploadsPath() . '/' . $relativePath;

        // turma_id segue como "turma principal" (compatibilidade com código já escrito
        // que só conhece uma turma); a visibilidade real para professor/aluno considera
        // todas as turmas selecionadas, via apostila_ia_turmas.
        $turmaPrincipal = $turmaIds[0] ?? 0;

        $apostilaId = (int) $this->db->insert(
            "INSERT INTO apostilas_ia (titulo, serie_id, turma_id, disciplina_id, professor_id, arquivo_pdf, status, total_paginas)
             VALUES (:titulo, :serie_id, :turma_id, :disciplina_id, :professor_id, :arquivo_pdf, 'pendente', 0)",
            [
                'titulo' => $titulo,
                'serie_id' => $serieId > 0 ? $serieId : null,
                'turma_id' => $turmaPrincipal > 0 ? $turmaPrincipal : null,
                'disciplina_id' => $disciplinaId > 0 ? $disciplinaId : null,
                'professor_id' => $professorId > 0 ? $professorId : null,
                'arquivo_pdf' => $relativePath,
            ]
        );

        foreach ($turmaIds as $turmaId) {
            $this->db->insert(
                "INSERT INTO apostila_ia_turmas (apostila_id, turma_id) VALUES (:apostila_id, :turma_id)",
                ['apostila_id' => $apostilaId, 'turma_id' => $turmaId]
            );
        }

        // Dispara processamento assíncrono no microserviço Python (resposta imediata
        // com status "processando"; acompanhamento via polling em /status).
        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $this->db->query(
                "UPDATE apostilas_ia SET status = 'processando', erro = NULL WHERE id = :id",
                ['id' => $apostilaId]
            );
            $client->processar($apostilaId, $pdfPathForPython, $titulo);
            $this->setFlashMessage('Apostila enviada. Processamento por IA iniciado.', 'success');
        } catch (\Throwable $e) {
            $this->db->query(
                "UPDATE apostilas_ia SET status = 'erro', erro = :erro WHERE id = :id",
                ['id' => $apostilaId, 'erro' => $e->getMessage()]
            );
            $this->setFlashMessage(
                'Apostila enviada, mas houve falha ao iniciar o processamento por IA. Use "Reprocessar" mais tarde.',
                'error'
            );
        }

        $this->redirect('/admin/apostilas-ia');
    }

    public function reprocessar($id = null)
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'alterar', false)) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findItemById($id) : null;
        if (!$item) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        if (strpos((string)($item['arquivo_pdf'] ?? ''), 'legado:') === 0) {
            $this->setFlashMessage(
                'Apostilas migradas do sistema antigo não podem ser reprocessadas. Envie um PDF novo para substituir o arquivo.',
                'error'
            );
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $this->db->query(
            "UPDATE apostilas_ia SET status = 'processando', erro = NULL WHERE id = :id",
            ['id' => $id]
        );

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $pdfPathForPython = $this->containerUploadsPath() . '/' . (string)$item['arquivo_pdf'];
            $client->processar($id, $pdfPathForPython, (string)$item['titulo']);
            $this->setFlashMessage('Reprocessamento iniciado.', 'success');
        } catch (\Throwable $e) {
            $this->db->query(
                "UPDATE apostilas_ia SET status = 'erro', erro = :erro WHERE id = :id",
                ['id' => $id, 'erro' => $e->getMessage()]
            );
            $this->setFlashMessage('Falha ao reprocessar: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/admin/apostilas-ia');
    }

    public function status($id = null)
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'visualizar', true)) {
            return;
        }

        $id = (int)($id ?? $_GET['id'] ?? 0);
        $item = $id > 0 ? $this->findItemById($id) : null;

        if (!$item) {
            $this->json(['error' => 'Apostila não encontrada.'], 404);
            return;
        }

        $this->json([
            'id' => (int)$item['id'],
            'status' => (string)$item['status'],
            'total_paginas' => (int)$item['total_paginas'],
            'erro' => $item['erro'] ?? null,
        ]);
    }

    /**
     * Abre o PDF original da apostila inline (botão "Ver Apostila"). Tenta o
     * volume compartilhado primeiro e, se o arquivo não estiver acessível ao
     * PHP, faz proxy via microserviço Python (mesmo padrão do lado professor/aluno).
     */
    public function pdf($id = null)
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'visualizar', false)) {
            return;
        }

        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findItemById($id) : null;
        if (!$item) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $relativo = (string)($item['arquivo_pdf'] ?? '');

        // Apostila migrada do módulo legado — usar MediaStorageService (suporta S3 e local).
        if (strpos($relativo, 'legado:') === 0) {
            if ($this->servirPdfLegado($relativo)) {
                return;
            }
            $this->setFlashMessage('PDF não encontrado. O arquivo pode ter sido armazenado em outro servidor.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        if ($relativo !== '' && strpos($relativo, '..') === false) {
            $caminho = $this->uploadBaseDir() . '/' . $relativo;
            if (is_file($caminho)) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . basename($caminho) . '"');
                header('Content-Length: ' . filesize($caminho));
                readfile($caminho);
                return;
            }
        }

        // Fallback: proxy via microserviço Python.
        $pdf = null;
        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $pdf = $client->buscarPdf($id);
        } catch (\Throwable $e) {
            $pdf = null;
        }

        if ($pdf === null) {
            // Evita devolver um 404/502 de corpo vazio (que o nginx substitui pela
            // própria página de erro). Redireciona com mensagem clara: o PDF não
            // está acessível ao PHP e o microserviço também não o serviu — em geral
            // basta reenviar o PDF por "Enviar PDF".
            $this->setFlashMessage(
                'Não foi possível abrir o PDF original. Reenvie o arquivo pelo botão "Enviar PDF" desta apostila.',
                'error'
            );
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        header('Content-Type: ' . ($pdf['content_type'] !== '' ? $pdf['content_type'] : 'application/pdf'));
        header('Content-Disposition: inline; filename="apostila-' . $id . '.pdf"');
        header('Content-Length: ' . strlen($pdf['body']));
        echo $pdf['body'];
    }

    /**
     * (Re)envia apenas o PDF original de uma apostila já existente, para que o
     * botão "Ver Apostila" funcione. NÃO dispara reprocessamento de IA — o
     * conteúdo (chunks/exercícios/chat) já processado é preservado.
     */
    public function editar($id = null)
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'alterar', false)) {
            return;
        }

        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findItemById($id) : null;
        if (!$item) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $turmasVinculadas = array_column(
            $this->db->fetchAll(
                "SELECT turma_id FROM apostila_ia_turmas WHERE apostila_id = :id",
                ['id' => $id]
            ),
            'turma_id'
        );

        $capaAtualUrl = null;
        if (!empty($item['capa_personalizada'])) {
            $capaAtualUrl = URL . '/admin/apostilas-ia/' . $id . '/capa';
        }

        $this->viewWithLayout('admin', 'admin/apostilas-ia/editar', [
            'title' => 'Editar Material - Admin',
            'page_title' => 'Meu Material',
            'user' => $this->auth->getUser(),
            'item' => $item,
            'capa_atual_url' => $capaAtualUrl,
            'turmas_vinculadas' => $turmasVinculadas,
            'series' => $this->getSeriesAtivas(),
            'turmas' => $this->getTurmasAtivas(),
            'materias' => $this->getMateriasAtivas(),
            'professores' => $this->getProfessoresAtivos(),
            'current_page' => 'apostilas-ia',
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function atualizar($id = null)
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'alterar', false)) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findItemById($id) : null;
        if (!$item) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $titulo = trim((string)($_POST['titulo'] ?? ''));
        if ($titulo === '') {
            $this->setFlashMessage('Informe um título para a apostila.', 'error');
            $this->redirect('/admin/apostilas-ia/' . $id . '/editar');
            return;
        }

        $serieId = (int)($_POST['serie_id'] ?? 0);
        $turmaIdsRaw = $_POST['turma_ids'] ?? ($_POST['turma_id'] ?? []);
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', (array)$turmaIdsRaw), static fn($v) => $v > 0)));
        $disciplinaId = (int)($_POST['disciplina_id'] ?? 0);
        $professorId = (int)($_POST['professor_id'] ?? 0);
        $turmaPrincipal = $turmaIds[0] ?? 0;

        $this->db->query(
            "UPDATE apostilas_ia SET titulo = :titulo, serie_id = :serie_id, turma_id = :turma_id,
             disciplina_id = :disciplina_id, professor_id = :professor_id WHERE id = :id",
            [
                'titulo' => $titulo,
                'serie_id' => $serieId > 0 ? $serieId : null,
                'turma_id' => $turmaPrincipal > 0 ? $turmaPrincipal : null,
                'disciplina_id' => $disciplinaId > 0 ? $disciplinaId : null,
                'professor_id' => $professorId > 0 ? $professorId : null,
                'id' => $id,
            ]
        );

        $this->db->query("DELETE FROM apostila_ia_turmas WHERE apostila_id = :id", ['id' => $id]);
        foreach ($turmaIds as $turmaId) {
            $this->db->insert(
                "INSERT INTO apostila_ia_turmas (apostila_id, turma_id) VALUES (:apostila_id, :turma_id)",
                ['apostila_id' => $id, 'turma_id' => $turmaId]
            );
        }

        $this->setFlashMessage('Apostila atualizada com sucesso.', 'success');
        $this->redirect('/admin/apostilas-ia');
    }

    public function capa($id = null)
    {
        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findItemById($id) : null;
        if (!$item) {
            http_response_code(404);
            return;
        }

        if (!empty($item['capa_personalizada']) && $this->servirCapaPersonalizada((string)$item['capa_personalizada'])) {
            return;
        }

        $isLegado = strpos((string)($item['arquivo_pdf'] ?? ''), 'legado:') === 0;
        if ($isLegado) {
            http_response_code(404);
            return;
        }

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $capaData = $client->buscarCapa($id);
        } catch (\Throwable $e) {
            http_response_code(502);
            return;
        }

        if ($capaData === null) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $capaData['content_type']);
        header('Cache-Control: private, max-age=86400');
        echo $capaData['body'];
    }

    public function enviarCapa($id = null)
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'alterar', false)) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findItemById($id) : null;
        if (!$item) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        if (!isset($_FILES['capa']) || (int)($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->setFlashMessage('Selecione uma imagem válida para a capa.', 'error');
            $this->redirect('/admin/apostilas-ia/' . $id . '/editar');
            return;
        }

        $file = $_FILES['capa'];
        $tmpPath = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($tmpPath === '' || !is_uploaded_file($tmpPath) || $size > 10 * 1024 * 1024) {
            $this->setFlashMessage('Arquivo inválido ou excede 10MB.', 'error');
            $this->redirect('/admin/apostilas-ia/' . $id . '/editar');
            return;
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = $this->detectMime($tmpPath);
        if (!in_array($mime, $allowedMimes, true)) {
            $this->setFlashMessage('Apenas imagens JPG, PNG ou WEBP são aceitas para a capa.', 'error');
            $this->redirect('/admin/apostilas-ia/' . $id . '/editar');
            return;
        }

        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext = $extMap[$mime];
        $tenantSlug = $this->tenantSlug();
        $dir = $this->uploadBaseDir() . '/' . $tenantSlug;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->setFlashMessage('Falha ao preparar diretório de armazenamento.', 'error');
            $this->redirect('/admin/apostilas-ia/' . $id . '/editar');
            return;
        }

        $safeName = 'capa_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $dir . '/' . $safeName;

        if (!@move_uploaded_file($tmpPath, $destPath)) {
            $this->setFlashMessage('Falha ao salvar a imagem de capa.', 'error');
            $this->redirect('/admin/apostilas-ia/' . $id . '/editar');
            return;
        }

        $relativePath = $tenantSlug . '/' . $safeName;
        $this->db->query(
            "UPDATE apostilas_ia SET capa_personalizada = :capa WHERE id = :id",
            ['capa' => $relativePath, 'id' => $id]
        );

        $this->setFlashMessage('Capa enviada com sucesso.', 'success');
        $this->redirect('/admin/apostilas-ia/' . $id . '/editar');
    }

    public function enviarPdf($id = null)
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'alterar', false)) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findItemById($id) : null;
        if (!$item) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        if (!isset($_FILES['arquivo']) || (int)($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->setFlashMessage('Selecione um arquivo PDF válido para enviar.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $file = $_FILES['arquivo'];
        $tmpPath = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $this->setFlashMessage('Arquivo inválido.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        if ($size > self::MAX_UPLOAD_BYTES) {
            $this->setFlashMessage('Arquivo excede o tamanho máximo permitido (200MB).', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        if ($this->detectMime($tmpPath) !== 'application/pdf') {
            $this->setFlashMessage('Apenas arquivos PDF são aceitos.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $tenantSlug = $this->tenantSlug();
        $dir = $this->uploadBaseDir() . '/' . $tenantSlug;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->setFlashMessage('Falha ao preparar diretório de armazenamento.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $safeName = 'apostila_ia_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $destPath = $dir . '/' . $safeName;

        if (!@move_uploaded_file($tmpPath, $destPath)) {
            $this->setFlashMessage('Falha ao salvar o arquivo enviado.', 'error');
            $this->redirect('/admin/apostilas-ia');
            return;
        }

        $relativePath = $tenantSlug . '/' . $safeName;
        $processarIa = ($_POST['modo_upload'] ?? 'exibir') === 'ia';

        if ($processarIa) {
            $titulo = (string)($item['titulo'] ?? '');
            $pdfPathForPython = $this->containerUploadsPath() . '/' . $relativePath;
            $this->db->query(
                "UPDATE apostilas_ia SET arquivo_pdf = :arquivo_pdf, status = 'processando', total_paginas = 0, erro = NULL WHERE id = :id",
                ['arquivo_pdf' => $relativePath, 'id' => $id]
            );
            try {
                require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
                $client = new ApostilaIaClientService();
                $client->processar($id, $pdfPathForPython, $titulo);
                $this->setFlashMessage('PDF enviado. Processamento por IA iniciado.', 'success');
            } catch (\Throwable $e) {
                $this->db->query(
                    "UPDATE apostilas_ia SET status = 'erro', erro = :erro WHERE id = :id",
                    ['id' => $id, 'erro' => $e->getMessage()]
                );
                $this->setFlashMessage('PDF enviado, mas houve falha ao iniciar o processamento por IA. Use "Reprocessar" mais tarde.', 'error');
            }
        } else {
            $this->db->query(
                "UPDATE apostilas_ia SET arquivo_pdf = :arquivo_pdf, status = 'pronto' WHERE id = :id",
                ['arquivo_pdf' => $relativePath, 'id' => $id]
            );
            $this->setFlashMessage('PDF enviado. A apostila já está disponível para visualização.', 'success');
        }

        $this->redirect('/admin/apostilas-ia');
    }
}
}
