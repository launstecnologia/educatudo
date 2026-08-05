<?php
/**
 * EducaTudo - IA da Apostila (Professor)
 * Módulo NOVO, distinto de Apostilas/TeacherApostilaController (que continua existindo
 * separadamente). Aqui o professor conversa com a IA sobre o conteúdo de uma apostila
 * processada pelo microserviço Python (extração de páginas, chunking, vector store OpenAI).
 */

if (!class_exists('TeacherApostilaIaController')) {
class TeacherApostilaIaController extends BaseController
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
        if (!$user || ($user['tipo'] ?? '') !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
            return;
        }
    }

    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
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

    private function professorId(): int
    {
        $user = $this->auth->getUser();
        return (int)($user['id'] ?? 0);
    }

    /**
     * Escopo de visibilidade do professor: apostilas vinculadas diretamente a ele
     * (professor_id) OU vinculadas a uma turma/disciplina que ele leciona.
     * Replica a mesma lógica de escopo já usada em Apostilas/TeacherApostilaController,
     * adaptada para o modelo de dados desta tabela (professor_id direto + turma/disciplina).
     */
    private function readItems(bool $apenasProntas = true): array
    {
        $professorId = $this->professorId();

        $sql = "SELECT ai.id, ai.titulo, ai.serie_id, ai.turma_id, ai.disciplina_id, ai.professor_id,
                       ai.status, ai.total_paginas, ai.erro, ai.created_at, ai.arquivo_pdf, ai.capa_personalizada,
                       t.nome AS turma_nome, m.nome AS disciplina_nome
                FROM apostilas_ia ai
                LEFT JOIN turmas t ON t.id = ai.turma_id
                LEFT JOIN materias m ON m.id = ai.disciplina_id
                WHERE (
                    ai.professor_id = :professor_id
                    OR ai.id IN (
                        SELECT ait.apostila_id FROM apostila_ia_turmas ait
                        WHERE ait.turma_id IN (
                            SELECT DISTINCT turma_id FROM grade_horaria WHERE professor_id = :professor_id_gh
                        )
                    )
                    OR ai.disciplina_id IN (
                        SELECT DISTINCT materia_id FROM grade_horaria WHERE professor_id = :professor_id_gh2
                    )
                )";

        if ($apenasProntas) {
            $sql .= " AND ai.status = 'pronto'";
        }

        $sql .= " ORDER BY ai.created_at DESC, ai.id DESC";

        try {
            return $this->db->fetchAll($sql, [
                'professor_id' => $professorId,
                'professor_id_gh' => $professorId,
                'professor_id_gh2' => $professorId,
            ]);
        } catch (\Throwable $e) {
            // Compatibilidade: se a tabela grade_horaria não existir/diferir neste tenant,
            // cai para o escopo mínimo seguro (apenas apostilas vinculadas diretamente ao professor).
            $sqlFallback = "SELECT ai.id, ai.titulo, ai.serie_id, ai.turma_id, ai.disciplina_id, ai.professor_id,
                                    ai.status, ai.total_paginas, ai.erro, ai.created_at, ai.arquivo_pdf,
                                    t.nome AS turma_nome, m.nome AS disciplina_nome
                             FROM apostilas_ia ai
                             LEFT JOIN turmas t ON t.id = ai.turma_id
                             LEFT JOIN materias m ON m.id = ai.disciplina_id
                             WHERE ai.professor_id = :professor_id" .
                             ($apenasProntas ? " AND ai.status = 'pronto'" : "") .
                             " ORDER BY ai.created_at DESC, ai.id DESC";
            return $this->db->fetchAll($sqlFallback, ['professor_id' => $professorId]);
        }
    }

    /**
     * Ownership check: retorna a apostila apenas se este professor pode acessá-la
     * (mesmo critério de escopo usado em readItems(), sem o filtro de status).
     */
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
        $this->viewWithLayout('professor', 'teacher/apostilas-ia/index', [
            'title' => 'IA da Apostila - EducaTudo',
            'page_title' => 'IA da Apostila',
            'user' => $this->auth->getUser(),
            'items' => $this->readItems(false),
            'current_page' => 'apostilas-ia',
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * Lista (em JSON) as apostilas prontas e acessíveis a este professor —
     * usado pelo seletor de "Importar exercícios da Apostila" nas telas de
     * Prova e Jornada (fora deste módulo).
     */
    public function apostilasDisponiveisJson()
    {
        $itens = $this->readItems(true); // só status='pronto'
        $resultado = array_map(function ($item) {
            return ['id' => (int)$item['id'], 'titulo' => (string)$item['titulo']];
        }, $itens);
        $this->json($resultado);
    }

    public function abrir($id = null)
    {
        $id = (int)($id ?? $_GET['id'] ?? 0);
        $item = $id > 0 ? $this->findAccessibleItem($id) : null;

        if (!$item) {
            $this->setFlashMessage('Apostila não encontrada ou indisponível.', 'error');
            $this->redirect('/professor/apostilas-ia');
            return;
        }

        if ($item['status'] !== 'pronto') {
            $this->setFlashMessage('Esta apostila ainda está sendo processada pela IA.', 'error');
            $this->redirect('/professor/apostilas-ia');
            return;
        }

        require_once __DIR__ . '/../../Services/ApostilaIaSessaoService.php';
        $sessaoService = new ApostilaIaSessaoService($this->db);
        $sessaoIdParam = (int)($_GET['sessao'] ?? 0);
        $sessaoAtiva = $sessaoService->resolverAtiva(
            $id,
            $this->professorId(),
            'professor',
            $sessaoIdParam > 0 ? $sessaoIdParam : null
        );
        $sessoes = $sessaoService->listar($id, $this->professorId(), 'professor');
        $historico = $sessaoService->historicoDaSessao((int)$sessaoAtiva['id']);

        $metaRow = $this->db->fetch(
            "SELECT sugestoes_chat FROM apostilas_ia WHERE id = :id LIMIT 1",
            ['id' => $id]
        );
        $sugestoesChat = $sessaoService->parseSugestoesChat(
            isset($metaRow['sugestoes_chat']) ? (string)$metaRow['sugestoes_chat'] : null
        );
        if ($sugestoesChat === []) {
            $sugestoesChat = $sessaoService->sugestoesPadrao('professor');
        }

        $this->viewWithLayout('professor', 'teacher/apostilas-ia/chat', [
            'title' => (string)$item['titulo'] . ' - IA da Apostila',
            'page_title' => 'IA da Apostila',
            'user' => $this->auth->getUser(),
            'item' => $item,
            'historico' => $historico,
            'sessoes' => $sessoes,
            'sessao_ativa' => $sessaoAtiva,
            'sugestoes_chat' => $sugestoesChat,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'apostilas-ia',
            'chat_base_url' => URL . '/professor/apostilas-ia/' . $id,
        ]);
    }

    public function novaSessao($id = null)
    {
        $id = (int)($id ?? 0);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/professor/apostilas-ia/' . $id);
            return;
        }

        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item || $item['status'] !== 'pronto') {
            $this->setFlashMessage('Apostila não encontrada ou indisponível.', 'error');
            $this->redirect('/professor/apostilas-ia');
            return;
        }

        require_once __DIR__ . '/../../Services/ApostilaIaSessaoService.php';
        $sessaoService = new ApostilaIaSessaoService($this->db);
        $novaSessaoId = $sessaoService->criar($id, $this->professorId(), 'professor');

        $this->redirect('/professor/apostilas-ia/' . $id . '?sessao=' . $novaSessaoId);
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
            $this->json(['error' => 'Apostila não encontrada ou indisponível.'], 404);
            return;
        }

        if ($item['status'] !== 'pronto') {
            $this->json(['error' => 'Esta apostila ainda está sendo processada pela IA.'], 409);
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
            $this->professorId(),
            'professor',
            $sessaoId > 0 ? $sessaoId : null
        );
        $sessaoId = (int)$sessao['id'];

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $resultado = $client->chat($id, $this->professorId(), $pergunta, $sessaoId);
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
            $this->json(['error' => 'Falha ao consultar a IA da Apostila: ' . $e->getMessage()], 502);
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
                ['error' => 'Apostila não encontrada ou indisponível.'],
                JSON_UNESCAPED_UNICODE
            ) . "\n\n";
            flush();
            return;
        }

        if ($item['status'] !== 'pronto') {
            header('Content-Type: text/event-stream; charset=utf-8');
            echo "event: error\ndata: " . json_encode(
                ['error' => 'Esta apostila ainda está sendo processada pela IA.'],
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
            $this->professorId(),
            'professor',
            $sessaoId > 0 ? $sessaoId : null
        );
        $sessaoId = (int)$sessao['id'];
        $sessaoService->garantirTituloPrimeiraMensagem($sessaoId, $pergunta);

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $client->emitChatStream($id, $this->professorId(), $pergunta, $sessaoId);
        } catch (\Throwable $e) {
            header('Content-Type: text/event-stream; charset=utf-8');
            echo "event: error\ndata: " . json_encode(
                ['error' => 'Falha ao consultar a IA da Apostila: ' . $e->getMessage()],
                JSON_UNESCAPED_UNICODE
            ) . "\n\n";
            flush();
        }
    }

    /**
     * Capa da apostila (página 1 renderizada) — usada na galeria "Meu Material".
     */
    public function capa($id = null)
    {
        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item) {
            http_response_code(404);
            return;
        }

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

    /**
     * Abre o PDF original da apostila inline no navegador (botão "Ver Apostila").
     * O arquivo está num volume compartilhado entre PHP e o microserviço Python
     * (ver APOSTILA_AI_SHARED_UPLOADS_PATH) — lido direto do disco, sem proxy
     * HTTP, já que ambos rodam no mesmo host.
     */
    public function pdf($id = null)
    {
        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item) {
            $this->setFlashMessage('Apostila não encontrada ou indisponível.', 'error');
            $this->redirect('/professor/apostilas-ia');
            return;
        }

        // 1) Caminho rápido: lê direto do disco. Tenta o volume compartilhado
        //    (APOSTILA_AI_SHARED_UPLOADS_PATH) E o storage local do app — este
        //    último é onde o admin grava quando a env não está setada. Sem este
        //    fallback, o arquivo existe no disco mas o professor procura no
        //    lugar errado e cai no proxy (404 quando o microservico nao tem /pdf).
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

        // 2) Fallback: faz proxy via microserviço Python (mesmo padrão da capa e
        //    das imagens de página), cobrindo ambientes onde PHP e Python NÃO
        //    compartilham o volume de uploads.
        $pdf = null;
        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $pdf = $client->buscarPdf($id);
        } catch (\Throwable $e) {
            $pdf = null;
        }

        if ($pdf === null) {
            // Evita 404 de corpo vazio (substituido pela pagina de erro do nginx).
            $this->setFlashMessage('Não foi possível abrir o PDF desta apostila.', 'error');
            $this->redirect('/professor/apostilas-ia');
            return;
        }

        header('Content-Type: ' . ($pdf['content_type'] !== '' ? $pdf['content_type'] : 'application/pdf'));
        header('Content-Disposition: inline; filename="apostila-' . $id . '.pdf"');
        header('Content-Length: ' . strlen($pdf['body']));
        echo $pdf['body'];
    }

    /**
     * Proxy autenticado entre o navegador do professor e o microserviço
     * Python: serve a imagem extraída de uma página da apostila (figura ou
     * diagrama associado a um exercício/explicação), se existir. Confirma
     * ownership antes de servir, como nos demais endpoints deste controller.
     */
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

    /**
     * Acrescenta `imagem_url` (apontando para o proxy de imagem desta apostila)
     * em cada item cujo `tem_imagem` veio true do microserviço Python — assim o
     * JSON copiado no chat já leva a imagem certa para Prova/Jornada, que já
     * sabem usar o campo `imagem_url` na criação de questão/exercício.
     */
    private function anexarImagemUrl(int $apostilaId, array $itens): array
    {
        foreach ($itens as &$item) {
            if (!empty($item['tem_imagem']) && !empty($item['pagina'])) {
                $item['imagem_url'] = URL . '/professor/apostilas-ia/' . $apostilaId
                    . '/pagina/' . (int)$item['pagina'] . '/imagem';
            }
        }
        return $itens;
    }

    public function exercicios($id = null)
    {
        $id = (int)($id ?? 0);
        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item) {
            $this->json(['error' => 'Apostila não encontrada ou indisponível.'], 404);
            return;
        }

        $filtros = [
            'tema' => trim((string)($_GET['tema'] ?? '')),
            'pagina' => trim((string)($_GET['pagina'] ?? '')),
            'tipo' => trim((string)($_GET['tipo'] ?? '')),
        ];

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $exercicios = $client->listarExercicios($id, $filtros);
            $this->json($this->anexarImagemUrl($id, $exercicios));
        } catch (\Throwable $e) {
            $this->json(['error' => 'Falha ao consultar exercícios: ' . $e->getMessage()], 502);
        }
    }

    public function gerarProva($id = null)
    {
        $id = (int)($id ?? 0);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido. Recarregue a página e tente novamente.'], 419);
            return;
        }

        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item) {
            $this->json(['error' => 'Apostila não encontrada ou indisponível.'], 404);
            return;
        }

        $tema = trim((string)($_POST['tema'] ?? ''));
        $quantidade = (int)($_POST['quantidade'] ?? 10);
        $nivel = trim((string)($_POST['nivel'] ?? 'media'));
        $incluirGabarito = !empty($_POST['incluir_gabarito']);

        if ($quantidade <= 0) {
            $quantidade = 10;
        }

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $resultado = $client->gerarProva($id, $this->professorId(), $tema, $quantidade, $nivel, $incluirGabarito);
            if (!empty($resultado['questoes']) && is_array($resultado['questoes'])) {
                $resultado['questoes'] = $this->anexarImagemUrl($id, $resultado['questoes']);
            }
            $this->json($resultado);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Falha ao gerar prova: ' . $e->getMessage()], 502);
        }
    }

    /**
     * Prepara o `conteudo` (roteiro em Markdown) para gerar slides desta apostila,
     * baseado em conteúdo real recuperado via RAG sobre o capítulo/tema informado.
     * NÃO chama o Gamma aqui — o front-end usa o `conteudo` retornado para chamar
     * o endpoint já existente `/professor/gerar-slides/api`
     * (Integrations/SlidesController@gerar), reaproveitando toda a integração
     * com créditos e geração já implementada lá.
     *
     * O slide final de fonte (apostila + páginas) é sempre acrescentado aqui,
     * de forma determinística, em vez de depender do modelo de IA incluí-lo.
     */
    public function prepararSlides($id = null)
    {
        $id = (int)($id ?? 0);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido. Recarregue a página e tente novamente.'], 419);
            return;
        }

        $item = $id > 0 ? $this->findAccessibleItem($id) : null;
        if (!$item) {
            $this->json(['error' => 'Apostila não encontrada ou indisponível.'], 404);
            return;
        }

        if ($item['status'] !== 'pronto') {
            $this->json(['error' => 'Esta apostila ainda está sendo processada pela IA.'], 409);
            return;
        }

        $capituloOuTema = trim((string)($_POST['capitulo_ou_tema'] ?? ''));
        if ($capituloOuTema === '') {
            $this->json(['error' => 'Descreva o capítulo ou tema que você quer apresentar.'], 422);
            return;
        }

        $numSlides = (int)($_POST['num_slides'] ?? 8);
        if ($numSlides < 3 || $numSlides > 30) {
            $numSlides = 8;
        }

        try {
            require_once __DIR__ . '/../../Services/ApostilaIaClientService.php';
            $client = new ApostilaIaClientService();
            $resultado = $client->buscarContextoParaSlides($id, $capituloOuTema, $numSlides);

            $conteudoSugerido = trim((string)($resultado['conteudo_sugerido'] ?? ''));
            $paginasUsadas = $resultado['paginas_usadas'] ?? [];

            $paginasLabel = !empty($paginasUsadas)
                ? 'Página(s) ' . implode(', ', array_map('intval', $paginasUsadas))
                : 'Páginas não identificadas';

            $slideFonte = "\n\n---\n\n## Fonte\n\nConteúdo extraído da apostila **"
                . (string)$item['titulo'] . "**.\n\n" . $paginasLabel . ".";

            $this->json([
                'conteudo' => $conteudoSugerido . $slideFonte,
                'tema' => $capituloOuTema,
                'paginas_usadas' => $paginasUsadas,
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Falha ao preparar conteúdo para os slides: ' . $e->getMessage()], 502);
        }
    }
}
}
