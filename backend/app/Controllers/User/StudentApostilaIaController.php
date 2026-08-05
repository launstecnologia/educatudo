<?php
/**
 * EducaTudo - Meu Material / IA da Apostila (Aluno)
 * Espelha as partes de leitura/chat do TeacherApostilaIaController, mas
 * escopado pela turma do aluno em vez de por professor. O aluno não tem
 * acesso a geração de provas/slides nem ao seletor de exercícios — só
 * visualização da apostila e chat de dúvidas (Assistente).
 */

if (!class_exists('StudentApostilaIaController')) {
class StudentApostilaIaController extends BaseController
{
    private $auth;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();

        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }

        $user = $this->auth->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
            return;
        }
    }

    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'monitor':
                $this->redirect('/monitor/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin');
                break;
            default:
                $this->redirect('/');
        }
    }

    private function alunoId(): int
    {
        $user = $this->auth->getUser();
        return (int)($user['id'] ?? 0);
    }

    private function turmaId(): ?int
    {
        $aluno = $this->db->fetch(
            "SELECT turma_id FROM alunos WHERE id = :id LIMIT 1",
            ['id' => $this->alunoId()]
        );
        return !empty($aluno['turma_id']) ? (int)$aluno['turma_id'] : null;
    }

    /**
     * Escopo de visibilidade do aluno: apostilas vinculadas à turma dele.
     * Diferente do professor (que também vê por professor_id/disciplina via
     * grade_horaria), o aluno só vê o que foi destinado à sua turma.
     */
    private function readItems(bool $apenasProntas = true): array
    {
        $turmaId = $this->turmaId();
        if ($turmaId === null) {
            return [];
        }

        $sql = "SELECT ai.id, ai.titulo, ai.serie_id, ai.turma_id, ai.disciplina_id, ai.professor_id,
                       ai.status, ai.total_paginas, ai.erro, ai.created_at, ai.arquivo_pdf, ai.capa_personalizada,
                       t.nome AS turma_nome, m.nome AS disciplina_nome
                FROM apostilas_ia ai
                LEFT JOIN turmas t ON t.id = ai.turma_id
                LEFT JOIN materias m ON m.id = ai.disciplina_id
                WHERE (
                    ai.turma_id = :turma_id
                    OR ai.id IN (SELECT apostila_id FROM apostila_ia_turmas WHERE turma_id = :turma_id_join)
                )";

        if ($apenasProntas) {
            $sql .= " AND ai.status = 'pronto'";
        }

        $sql .= " ORDER BY ai.created_at DESC, ai.id DESC";

        $items = $this->db->fetchAll($sql, ['turma_id' => $turmaId, 'turma_id_join' => $turmaId]);
        foreach ($items as &$item) {
            $item['is_legado'] = strpos((string)($item['arquivo_pdf'] ?? ''), 'legado:') === 0;
        }
        unset($item);
        return $items;
    }

    private function findAccessibleItem(int $id): ?array
    {
        $items = $this->readItems(false);
        foreach ($items as $item) {
            if ((int)$item['id'] === $id) {
                return $item;
            }
        }
        return null;
    }

    public function index()
    {
        $this->viewWithLayout('student', 'student/apostilas-ia/index', [
            'title' => 'Meu Material - EducaTudo',
            'page_title' => 'Meu Material',
            'user' => $this->auth->getUser(),
            'items' => $this->readItems(false),
            'current_page' => 'apostilas-ia',
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function abrir($id = null)
    {
        $id = (int)($id ?? $_GET['id'] ?? 0);
        $item = $id > 0 ? $this->findAccessibleItem($id) : null;

        if (!$item) {
            $this->setFlashMessage('Material não encontrado ou indisponível.', 'error');
            $this->redirect('/aluno/apostilas-ia');
            return;
        }

        if ($item['status'] !== 'pronto') {
            $this->setFlashMessage('Este material ainda está sendo processado pela IA.', 'error');
            $this->redirect('/aluno/apostilas-ia');
            return;
        }

        require_once __DIR__ . '/../../Services/ApostilaIaSessaoService.php';
        $sessaoService = new ApostilaIaSessaoService($this->db);
        $sessaoIdParam = (int)($_GET['sessao'] ?? 0);
        $sessaoAtiva = $sessaoService->resolverAtiva(
            $id,
            $this->alunoId(),
            'aluno',
            $sessaoIdParam > 0 ? $sessaoIdParam : null
        );
        $sessoes = $sessaoService->listar($id, $this->alunoId(), 'aluno');
        $historico = $sessaoService->historicoDaSessao((int)$sessaoAtiva['id']);

        $metaRow = $this->db->fetch(
            "SELECT sugestoes_chat FROM apostilas_ia WHERE id = :id LIMIT 1",
            ['id' => $id]
        );
        $sugestoesChat = $sessaoService->parseSugestoesChat(
            isset($metaRow['sugestoes_chat']) ? (string)$metaRow['sugestoes_chat'] : null
        );
        if ($sugestoesChat === []) {
            $sugestoesChat = $sessaoService->sugestoesPadrao('aluno');
        }

        $this->viewWithLayout('student', 'student/apostilas-ia/chat', [
            'title' => (string)$item['titulo'] . ' - Meu Material',
            'page_title' => 'Meu Material',
            'user' => $this->auth->getUser(),
            'item' => $item,
            'historico' => $historico,
            'sessoes' => $sessoes,
            'sessao_ativa' => $sessaoAtiva,
            'sugestoes_chat' => $sugestoesChat,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'apostilas-ia',
            'chat_base_url' => URL . '/aluno/apostilas-ia/' . $id,
        ]);
    }

    public function novaSessao($id = null)
    {
        $id = (int)($id ?? 0);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/aluno/apostilas-ia/' . $id);
            return;
        }

        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item || $item['status'] !== 'pronto') {
            $this->setFlashMessage('Material não encontrado ou indisponível.', 'error');
            $this->redirect('/aluno/apostilas-ia');
            return;
        }

        require_once __DIR__ . '/../../Services/ApostilaIaSessaoService.php';
        $sessaoService = new ApostilaIaSessaoService($this->db);
        $novaSessaoId = $sessaoService->criar($id, $this->alunoId(), 'aluno');

        $this->redirect('/aluno/apostilas-ia/' . $id . '?sessao=' . $novaSessaoId);
    }

    public function chat($id = null)
    {
        $id = (int)($id ?? 0);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido. Recarregue a página e tente novamente.'], 419);
            return;
        }

        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item) {
            $this->json(['error' => 'Material não encontrado ou indisponível.'], 404);
            return;
        }

        if ($item['status'] !== 'pronto') {
            $this->json(['error' => 'Este material ainda está sendo processado pela IA.'], 409);
            return;
        }

        $pergunta = trim((string)($_POST['pergunta'] ?? ''));
        if ($pergunta === '') {
            $this->json(['error' => 'Digite uma pergunta antes de enviar.'], 422);
            return;
        }

        $sessaoId = (int)($_POST['sessao_id'] ?? 0);
        require_once __DIR__ . '/../../Services/ApostilaIaSessaoService.php';
        $sessaoService = new ApostilaIaSessaoService($this->db);
        $sessao = $sessaoService->resolverAtiva(
            $id,
            $this->alunoId(),
            'aluno',
            $sessaoId > 0 ? $sessaoId : null
        );
        $sessaoId = (int)$sessao['id'];

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $resultado = $client->chat($id, $this->alunoId(), $pergunta, $sessaoId);
            $sessaoService->garantirTituloPrimeiraMensagem($sessaoId, $pergunta);

            // Persistência feita pelo microserviço Python (rag_service) — não duplicar aqui.
            $this->json([
                'resposta' => $resultado['resposta'] ?? '',
                'sessao_id' => $sessaoId,
                'paginas_usadas' => $resultado['paginas_usadas'] ?? [],
                'paginas_com_imagem' => $resultado['paginas_com_imagem'] ?? [],
                'fontes' => $resultado['fontes'] ?? [],
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Falha ao consultar o Assistente: ' . $e->getMessage()], 502);
        }
    }

    /**
     * Chat com streaming SSE — resposta token a token (Fase A UX).
     */
    public function chatStream($id = null)
    {
        $id = (int)($id ?? 0);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            header('Content-Type: text/event-stream; charset=utf-8');
            echo "event: error\ndata: " . json_encode(
                ['error' => 'Token inválido. Recarregue a página e tente novamente.'],
                JSON_UNESCAPED_UNICODE
            ) . "\n\n";
            flush();
            return;
        }

        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item) {
            header('Content-Type: text/event-stream; charset=utf-8');
            echo "event: error\ndata: " . json_encode(
                ['error' => 'Material não encontrado ou indisponível.'],
                JSON_UNESCAPED_UNICODE
            ) . "\n\n";
            flush();
            return;
        }

        if ($item['status'] !== 'pronto') {
            header('Content-Type: text/event-stream; charset=utf-8');
            echo "event: error\ndata: " . json_encode(
                ['error' => 'Este material ainda está sendo processado pela IA.'],
                JSON_UNESCAPED_UNICODE
            ) . "\n\n";
            flush();
            return;
        }

        $pergunta = trim((string)($_POST['pergunta'] ?? ''));
        if ($pergunta === '') {
            header('Content-Type: text/event-stream; charset=utf-8');
            echo "event: error\ndata: " . json_encode(
                ['error' => 'Digite uma pergunta antes de enviar.'],
                JSON_UNESCAPED_UNICODE
            ) . "\n\n";
            flush();
            return;
        }

        set_time_limit(120);

        $sessaoId = (int)($_POST['sessao_id'] ?? 0);
        require_once __DIR__ . '/../../Services/ApostilaIaSessaoService.php';
        $sessaoService = new ApostilaIaSessaoService($this->db);
        $sessao = $sessaoService->resolverAtiva(
            $id,
            $this->alunoId(),
            'aluno',
            $sessaoId > 0 ? $sessaoId : null
        );
        $sessaoId = (int)$sessao['id'];
        $sessaoService->garantirTituloPrimeiraMensagem($sessaoId, $pergunta);

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $client->emitChatStream($id, $this->alunoId(), $pergunta, $sessaoId);
        } catch (\Throwable $e) {
            header('Content-Type: text/event-stream; charset=utf-8');
            echo "event: error\ndata: " . json_encode(
                ['error' => 'Falha ao consultar o Assistente: ' . $e->getMessage()],
                JSON_UNESCAPED_UNICODE
            ) . "\n\n";
            flush();
        }
    }

    public function capa($id = null)
    {
        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item) {
            http_response_code(404);
            return;
        }

        // Capa personalizada enviada pelo admin tem prioridade.
        $capaPersonalizada = (string)($item['capa_personalizada'] ?? '');
        if ($capaPersonalizada !== '' && strpos($capaPersonalizada, '..') === false) {
            $shared = rtrim((string)env('APOSTILA_AI_SHARED_UPLOADS_PATH', ''), '/');
            $base = $shared !== '' ? $shared : __DIR__ . '/../../../storage/uploads/apostilas-ia';
            $fullPath = $base . '/' . $capaPersonalizada;
            if (is_file($fullPath)) {
                $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
                header('Content-Type: ' . ($mimeMap[$ext] ?? 'image/jpeg'));
                header('Cache-Control: private, max-age=86400');
                header('Content-Length: ' . filesize($fullPath));
                readfile($fullPath);
                return;
            }
        }

        $isLegado = strpos((string)($item['arquivo_pdf'] ?? ''), 'legado:') === 0;
        if ($isLegado) {
            http_response_code(404);
            return;
        }

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $capa = $client->buscarCapa($id);
        } catch (\Throwable $e) {
            http_response_code(502);
            return;
        }

        if ($capa === null) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $capa['content_type']);
        header('Cache-Control: private, max-age=86400');
        echo $capa['body'];
    }

    public function pdf($id = null)
    {
        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item) {
            $this->setFlashMessage('Material não encontrado ou indisponível.', 'error');
            $this->redirect('/aluno/apostilas-ia');
            return;
        }

        // 1) Caminho rápido: lê direto do disco. Tenta o volume compartilhado
        //    (APOSTILA_AI_SHARED_UPLOADS_PATH) E o storage local do app — este
        //    último é onde o admin grava quando a env não está setada.
        $relativo = (string)($item['arquivo_pdf'] ?? '');
        if ($relativo !== '' && strpos($relativo, '..') === false) {
            // Apostilas migradas do módulo legado: usar MediaStorageService (suporta S3 e local).
            if (strpos($relativo, 'legado:') === 0) {
                $caminho = substr($relativo, strlen('legado:'));
                if ($caminho !== '' && strpos($caminho, '..') === false && strpos($caminho, 'sem-arquivo') === false) {
                    $mediaKey = basename($caminho);
                    if (strpos($caminho, 'apostilas/') === 0) {
                        $mediaKey = substr($caminho, strlen('apostilas/'));
                    }
                    require_once __DIR__ . '/../../Services/MediaStorageService.php';
                    $media = new MediaStorageService($this->config);
                    if ($media->isS3()) {
                        if ($media->streamInline('apostilas', $mediaKey, basename($caminho), 'application/pdf')) {
                            return;
                        }
                    } else {
                        $localPath = $media->getLocalPath('apostilas', $mediaKey);
                        if ($localPath !== null && is_file($localPath)) {
                            header('Content-Type: application/pdf');
                            header('Content-Disposition: inline; filename="' . basename($caminho) . '"');
                            header('Content-Length: ' . filesize($localPath));
                            readfile($localPath);
                            return;
                        }
                    }
                }
            } else {
                $bases = [];
                $shared = rtrim((string)env('APOSTILA_AI_SHARED_UPLOADS_PATH', ''), '/');
                if ($shared !== '') {
                    $bases[] = $shared;
                }
                $bases[] = __DIR__ . '/../../../storage/uploads/apostilas-ia';
                foreach ($bases as $base) {
                    $caminho = $base . '/' . $relativo;
                    if (is_file($caminho)) {
                        header('Content-Type: application/pdf');
                        header('Content-Disposition: inline; filename="' . basename($caminho) . '"');
                        header('Content-Length: ' . filesize($caminho));
                        readfile($caminho);
                        return;
                    }
                }
            }
        }

        // 2) Fallback: proxy via microserviço Python (mesmo padrão da capa),
        //    cobrindo ambientes onde PHP e Python NÃO compartilham o volume.
        $pdf = null;
        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $pdf = $client->buscarPdf($id);
        } catch (\Throwable $e) {
            $pdf = null;
        }

        if ($pdf === null) {
            $this->setFlashMessage('Não foi possível abrir o PDF deste material.', 'error');
            $this->redirect('/aluno/apostilas-ia');
            return;
        }

        header('Content-Type: ' . ($pdf['content_type'] !== '' ? $pdf['content_type'] : 'application/pdf'));
        header('Content-Disposition: inline; filename="apostila-' . $id . '.pdf"');
        header('Content-Length: ' . strlen($pdf['body']));
        echo $pdf['body'];
    }

    public function imagemPagina($id = null, $numeroPagina = null)
    {
        $id = (int)($id ?? 0);
        $numeroPagina = (int)($numeroPagina ?? 0);

        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item || $numeroPagina <= 0) {
            http_response_code(404);
            return;
        }

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $imagem = $client->buscarImagemPagina($id, $numeroPagina);
        } catch (\Throwable $e) {
            http_response_code(502);
            return;
        }

        if ($imagem === null) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $imagem['content_type']);
        header('Cache-Control: private, max-age=86400');
        echo $imagem['body'];
    }
}
}
