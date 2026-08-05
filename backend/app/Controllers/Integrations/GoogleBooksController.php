<?php
/**
 * EducaTudo - Controller de Google Books
 * Gerencia busca e exibição de livros do Google Books
 */

if (!class_exists('GoogleBooksController')) {
class GoogleBooksController extends BaseController
{
    private $authManager;
    private $apiKey = 'AIzaSyC9iaxlGVpKj-jLmW-fg4VsXoexbVDIRGo';
    private $baseUrl = 'https://www.googleapis.com/books/v1';
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        
        // Verifica se está logado
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        
        // Verifica se é aluno
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'aluno') {
            $this->redirect('/' . ($user['tipo'] === 'professor' ? 'professor/dashboard' : 'admin/dashboard'));
            return;
        }
    }
    
    /**
     * Lista de livros - página principal
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirect('/login');
            return;
        }
        
        $query = $_GET['q'] ?? '';
        $livros = [];
        $totalItems = 0;
        $erro = null;
        $carregarDestaquesViaAjax = false;

        if (!empty($query)) {
            $resultado = $this->buscarLivros($query, 0, 20, false);
            $livros = $resultado['items'] ?? [];
            $totalItems = $resultado['totalItems'] ?? 0;
        } else {
            // A seleção de "Livros em Destaque" faz várias chamadas à API do Google
            // (uma por categoria) e demorava pra carregar a página inteira. Em vez de
            // fazer isso aqui (bloqueando o primeiro render), a página carrega vazia
            // e busca os destaques via AJAX em /livros/destaques.
            $carregarDestaquesViaAjax = true;
        }

        $this->viewWithLayout('student', 'student/livros/index', [
            'title' => 'Educa Livros - EducaTudo',
            'user' => $user,
            'livros' => $livros,
            'query' => $query,
            'totalItems' => $totalItems,
            'current_page' => 'livros',
            'erro' => $erro,
            'carregarDestaquesViaAjax' => $carregarDestaquesViaAjax,
        ]);
    }

    /**
     * AJAX: livros em destaque (seleção nacional para ensino médio). Extraído de
     * index() pra não bloquear o primeiro render da página com ~10 chamadas
     * sequenciais à API do Google Books.
     */
    public function destaques()
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->json(['success' => false, 'error' => 'Não autenticado'], 401);
            return;
        }

        $queries = [
            'literatura brasileira ensino médio',
            'clássicos literatura brasileira',
            'livros didáticos ensino médio',
            'história do brasil',
            'geografia brasil',
            'matemática ensino médio',
            'física ensino médio',
            'química ensino médio',
            'biologia ensino médio',
            'português ensino médio'
        ];

        $livros = [];
        $livrosEncontrados = [];

        try {
            foreach ($queries as $q) {
                try {
                    $resultado = $this->buscarLivros($q, 0, 3, true);
                    if (isset($resultado['items']) && is_array($resultado['items'])) {
                        foreach ($resultado['items'] as $item) {
                            $bookId = $item['id'] ?? '';
                            if ($bookId && !isset($livrosEncontrados[$bookId])) {
                                $livrosEncontrados[$bookId] = true;
                                $livros[] = $item;
                                if (count($livros) >= 20) {
                                    break 2;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erro ao buscar livros para query '$q': " . $e->getMessage());
                }
            }

            if (count($livros) < 10) {
                try {
                    $resultado = $this->buscarLivros('literatura brasileira', 0, 20, true);
                    if (isset($resultado['items']) && is_array($resultado['items'])) {
                        foreach ($resultado['items'] as $item) {
                            $bookId = $item['id'] ?? '';
                            if ($bookId && !isset($livrosEncontrados[$bookId])) {
                                $livrosEncontrados[$bookId] = true;
                                $livros[] = $item;
                                if (count($livros) >= 20) break;
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erro ao buscar livros genéricos: " . $e->getMessage());
                }
            }

            $this->json(['success' => true, 'items' => $livros, 'totalItems' => count($livros)]);
        } catch (Exception $e) {
            error_log("Erro ao buscar livros em destaque: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Detalhes de um livro
     */
    public function detalhes($bookId)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirect('/login');
            return;
        }
        
        $livro = $this->buscarLivroPorId($bookId);
        
        if (!$livro) {
            $_SESSION['error_message'] = 'Livro não encontrado';
            $this->redirect('/livros');
            return;
        }
        
        $this->viewWithLayout('student', 'student/livros/detalhes', [
            'title' => ($livro['volumeInfo']['title'] ?? 'Livro') . ' - Educa Livros',
            'user' => $user,
            'livro' => $livro,
            'current_page' => 'livros'
        ]);
    }
    
    /**
     * API: Busca livros
     */
    public function buscar()
    {
        $query = $_GET['q'] ?? '';
        $startIndex = (int)($_GET['startIndex'] ?? 0);
        $maxResults = (int)($_GET['maxResults'] ?? 20);
        
        if (empty($query)) {
            $this->json(['success' => false, 'error' => 'Query é obrigatória']);
            return;
        }
        
        try {
            $resultado = $this->buscarLivros($query, $startIndex, $maxResults, false);
            $this->json([
                'success' => true,
                'items' => $resultado['items'] ?? [],
                'totalItems' => $resultado['totalItems'] ?? 0
            ]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Busca livros na API do Google Books
     */
    private function buscarLivros($query, $startIndex = 0, $maxResults = 20, $restrictLang = false)
    {
        $params = [
            'q' => $query,
            'startIndex' => $startIndex,
            'maxResults' => $maxResults,
            'key' => $this->apiKey
        ];
        
        // Adiciona restrição de idioma apenas se solicitado
        if ($restrictLang) {
            $params['langRestrict'] = 'pt';
        }
        
        $url = $this->baseUrl . '/volumes?' . http_build_query($params);
        
        error_log("Google Books API - Query: $query, URL: " . str_replace($this->apiKey, '***', $url));
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("Erro cURL ao buscar livros: " . $error);
            throw new Exception('Erro ao buscar livros: ' . $error);
        }
        
        if ($httpCode !== 200) {
            $errorResponse = substr($response, 0, 500);
            error_log("Erro HTTP $httpCode ao buscar livros. Resposta: $errorResponse");
            throw new Exception('Erro HTTP ' . $httpCode . ': ' . $errorResponse);
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erro JSON ao buscar livros: " . json_last_error_msg() . " - Resposta: " . substr($response, 0, 500));
            throw new Exception('Erro ao decodificar resposta JSON: ' . json_last_error_msg());
        }
        
        if (!isset($data['items']) && isset($data['totalItems']) && $data['totalItems'] > 0) {
            error_log("Aviso: API retornou totalItems=" . $data['totalItems'] . " mas não há 'items' na resposta");
        }
        
        error_log("Google Books API - Sucesso: " . ($data['totalItems'] ?? 0) . " livros encontrados, " . count($data['items'] ?? []) . " retornados");
        
        return $data;
    }
    
    /**
     * Busca um livro específico por ID
     */
    private function buscarLivroPorId($bookId)
    {
        $url = $this->baseUrl . '/volumes/' . urlencode($bookId) . '?key=' . $this->apiKey;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $data;
    }
}
}
