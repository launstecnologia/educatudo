<?php

namespace App\Core;
/**
 * EducaTudo - Controller Base
 * Classe base para todos os controllers
 */

abstract class BaseController
{
    protected $config;

    public function __construct()
    {
        // Define constantes se não existirem (para requisições AJAX diretas)
        if (!defined('FOLDER')) {
            // Detecta automaticamente o FOLDER baseado no caminho do script
            $scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $folder = ($scriptPath === '/' || $scriptPath === '') ? '' : $scriptPath;
            define('FOLDER', $folder);
        }
        if (!defined('URL')) {
            // Detecta URL automaticamente
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            define('URL', $protocol . '://' . $host . FOLDER);
        }
        if (!defined('ENVIRONMENT')) {
            define('ENVIRONMENT', 'production');
        }
        if (!defined('DEBUG')) {
            define('DEBUG', ENVIRONMENT === 'development');
        }
        
        // Configurar error reporting
        if (ENVIRONMENT === 'production') {
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            ini_set('error_log', __DIR__ . '/../../storage/logs/error.log');
        } else {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }

        // Carrega config a cada requisição para refletir o .env atual (ex.: \SCHOOL_CODE)
        $loaded = require __DIR__ . '/../../config/app.php';
        $this->config = is_array($loaded) ? $loaded : [];
    }
    
    /**
     * Prefixo legado da view (2º segmento) → chave física em Modulos/<chave>/.
     * Ex.: admin/exams/index → Modulos/provas/Views/admin/index.php
     *
     * @var array<string, string>
     */
    private const VIEW_PREFIX_TO_MODULE = [
        'exams' => 'provas',
        'essays' => 'redacoes',
        'essays-analytics' => 'redacoes',
        'journeys' => 'jornadas',
        'flashcards' => 'flashcards',
        'tickets' => 'tickets',
        'forum' => 'forum',
        'minicursos' => 'minicursos',
        'ava' => 'ava',
        'educalabs' => 'educalabs',
        'educa-hits' => 'educahits',
        'jogos' => 'jogos',
        'jogo-milhao' => 'jogos',
        'notes' => 'notes',
        'aulas-online' => 'aulas-online',
        'apostilas' => 'apostilas',
        'apostilas-ia' => 'apostilas',
        'exercises' => 'exercicios',
        'exercises-personalizados' => 'exercicios',
        'mural-recados' => 'mural',
        'finance' => 'financeiro',
        'financeiro' => 'financeiro',
        'notifications' => 'notificacoes',
        'push-notifications' => 'notificacoes',
        'conformidade' => 'conformidade',
        'patrimony' => 'patrimonio',
        'inventory' => 'patrimonio',
        'inclusao' => 'educainclui',
        'school-communication' => 'comunicacao',
        'reunioes' => 'comunicacao',
        'comunicacao' => 'comunicacao',
        'external-apps' => 'external-apps',
        'noticias' => 'noticias',
        'simulations' => 'provas',
        'blocos-modelo' => 'provas',
        'planos-aula' => 'jornadas',
        'caderno' => 'notes',
        'livros' => 'apostilas',
        'materiais' => 'arquivos',
        'ingles' => 'minicursos',
        'chat' => 'comunicacao',
        'redacao_livre' => 'redacoes',
        'ai-agent' => 'jornadas',
        'questoes' => 'provas',
        'enrollment' => 'matricula',
        'boletim' => 'boletins',
        'boletim_guia' => 'boletins',
        'declarations' => 'modelos-documentos',
        'resultados-finais' => 'modelos-documentos',
        'historico-escolar' => 'modelos-documentos',
        'ui-modelos' => 'modelos-documentos',
        'documentos-institucionais' => 'modelos-documentos',
        'facial-recognition' => 'presenca',
        'faltas' => 'presenca',
        'patrimonio' => 'patrimonio',
        'almoxarifado' => 'patrimonio',
        'saude-academica' => 'dashboard-gestao',
        'assistente' => 'dashboard-gestao',
        'escrever-redacao' => 'redacoes',
        'escrever-redacao-livre' => 'redacoes',
        'historico-redacoes' => 'redacoes',
        'ver-redacao' => 'redacoes',
        'transcrever-imagem' => 'redacoes',
        'desempenho-jornadas' => 'jornadas',
        'desempenho-provas' => 'provas',
        'notas' => 'boletins',
        'notas-boletins' => 'boletins',
        'educashop' => 'educahits',
        'jornadas-aluno' => 'jornadas',
        'jornadas-relatorio' => 'jornadas',
        'student-exams' => 'provas',
        'student-journey' => 'jornadas',
    ];

    /**
     * Prefixo legado → Domínio (Pessoas|Escola). Mantém o prefixo no path.
     * Ex.: admin/students/index → Dominio/Pessoas/Views/admin/students/index.php
     *
     * @var array<string, string>
     */
    private const VIEW_PREFIX_TO_DOMINIO = [
        'students' => 'Pessoas',
        'teachers' => 'Pessoas',
        'teachers-documentos' => 'Pessoas',
        'avatares-alunos' => 'Pessoas',
        'monitores' => 'Pessoas',
        'users' => 'Pessoas',
        'classes' => 'Escola',
        'turmas' => 'Escola',
        'units' => 'Escola',
        'ano-letivo' => 'Escola',
        'cursos' => 'Escola',
        'curso' => 'Escola',
        'cursos-series' => 'Escola',
        'componentes-curriculares' => 'Escola',
        'matrizes-curriculares' => 'Escola',
        'bncc' => 'Escola',
        'grade-horaria' => 'Escola',
        'settings' => 'Escola',
        'school-calendar' => 'Escola',
        'school-settings' => 'Escola',
    ];

    /**
     * Resolve caminho da view:
     * 1) Views/ legado
     * 2) Painel/<perfil>/Views/
     * 3) Dominio/<Pessoas|Escola>/Views/
     * 4) Modulos/<chave>/Views/<perfil>/ (chave = 2º segmento ou alias)
     *
     * Ex.: student/arquivos/index → Modulos/arquivos/Views/aluno/index.php
     * Ex.: admin/exams/index → Modulos/provas/Views/admin/index.php
     */
    protected function resolveViewPath(string $view): ?string
    {
        $view = str_replace('\\', '/', $view);
        $legacy = __DIR__ . '/../Views/' . $view . '.php';
        if (is_file($legacy)) {
            return $legacy;
        }

        // Notificações do usuário (sem prefixo de perfil): notifications/index
        if (preg_match('#^notifications/(.+)$#', $view, $nm)) {
            $notifView = __DIR__ . '/../Modulos/notificacoes/Views/shared/' . $nm[1] . '.php';
            if (is_file($notifView)) {
                return $notifView;
            }
        }

        // AVA público (sem perfil): ava/certificado_validar
        if (preg_match('#^ava/(.+)$#', $view, $am)) {
            $avaView = __DIR__ . '/../Modulos/ava/Views/public/' . $am[1] . '.php';
            if (is_file($avaView)) {
                return $avaView;
            }
        }

        // Painel/<perfil>/Views/... (ex.: admin/hub → Painel/Admin/Views/hub.php)
        if (preg_match('#^(aluno|professor|student|teacher|admin|master|parents|monitor)/(.+)$#', $view, $pm)) {
            $perfil = $pm[1];
            $map = [
                'student' => 'Aluno',
                'aluno' => 'Aluno',
                'teacher' => 'Professor',
                'professor' => 'Professor',
                'admin' => 'Admin',
                'master' => 'Master',
                'parents' => 'Pais',
                'monitor' => 'Monitor',
            ];
            $pasta = $map[$perfil] ?? null;
            if ($pasta !== null) {
                $painelView = __DIR__ . '/../Painel/' . $pasta . '/Views/' . $pm[2] . '.php';
                if (is_file($painelView)) {
                    return $painelView;
                }
            }
        }

        if (!preg_match('#^(aluno|professor|student|teacher|admin|master|parents|monitor)/([^/]+)(?:/(.+))?$#', $view, $m)) {
            return null;
        }

        $perfilView = $m[1];
        if ($perfilView === 'student') {
            $perfilView = 'aluno';
        } elseif ($perfilView === 'teacher') {
            $perfilView = 'professor';
        }
        $prefix = $m[2];
        $rest = $m[3] ?? 'index';

        // Domínio: mantém o prefixo no path (students/index.php)
        if (isset(self::VIEW_PREFIX_TO_DOMINIO[$prefix])) {
            $dominio = self::VIEW_PREFIX_TO_DOMINIO[$prefix];
            $dominioView = __DIR__ . '/../Dominio/' . $dominio . '/Views/' . $perfilView . '/' . $prefix . '/' . $rest . '.php';
            if (is_file($dominioView)) {
                return $dominioView;
            }
            // fallback: arquivo direto sob o prefixo
            $dominioFile = __DIR__ . '/../Dominio/' . $dominio . '/Views/' . $perfilView . '/' . $prefix . '.php';
            if ($rest === 'index' && is_file($dominioFile)) {
                return $dominioFile;
            }
        }

        // Módulo: alias do prefixo legado → chave física
        $chave = self::VIEW_PREFIX_TO_MODULE[$prefix] ?? $prefix;
        $candidates = [
            // com prefixo preservado (ex.: admin/reunioes/geral → .../Views/admin/reunioes/geral.php)
            __DIR__ . '/../Modulos/' . $chave . '/Views/' . $perfilView . '/' . $prefix . '/' . $rest . '.php',
            // achatado (ex.: admin/exams/index → .../Views/admin/index.php)
            __DIR__ . '/../Modulos/' . $chave . '/Views/' . $perfilView . '/' . $rest . '.php',
            // view de um único arquivo: student/essays → .../Views/aluno/essays.php
            __DIR__ . '/../Modulos/' . $chave . '/Views/' . $perfilView . '/' . $prefix . '.php',
            // controller já usa a chave PT: admin/provas/index
            __DIR__ . '/../Modulos/' . $prefix . '/Views/' . $perfilView . '/' . $rest . '.php',
            __DIR__ . '/../Modulos/' . $prefix . '/Views/' . $perfilView . '/' . $prefix . '/' . $rest . '.php',
            __DIR__ . '/../Modulos/' . $prefix . '/Views/' . $perfilView . '/' . $prefix . '.php',
        ];
        foreach ($candidates as $moduloView) {
            if (is_file($moduloView)) {
                return $moduloView;
            }
        }

        return null;
    }

    /**
     * Renderiza uma view
     */
    protected function view($view, $data = [])
    {
        $viewFile = $this->resolveViewPath($view);

        if ($viewFile === null) {
            throw new \Exception("View não encontrada: {$view}");
        }

        // Extrai as variáveis para o escopo da view
        extract($data);

        // Inclui a view
        $profiler = class_exists(\App\Performance\RequestProfiler::class);
        if ($profiler) {
            \App\Performance\RequestProfiler::markViewStart();
        }
        try {
            include $viewFile;
        } finally {
            if ($profiler) {
                \App\Performance\RequestProfiler::markViewEnd();
            }
        }
    }
    
    /**
     * Renderiza uma view com layout
     */
    protected function viewWithLayout($layout, $view, $data = [])
    {
        // Evita cache do navegador e do Service Worker: após logout, outro usuário não deve ver dados do anterior
        $this->disableCache();

        // Obter todos os argumentos para verificar o que foi realmente passado
        $args = func_get_args();
        
        // Garantir que $data é sempre um array
        if (!is_array($data)) {
            error_log("BaseController::viewWithLayout - \$data não é array");
            $data = [];
        }
        
        $layoutFile = __DIR__ . '/../Views/layouts/' . $layout . '.php';
        $viewFile = $this->resolveViewPath($view);

        if (!file_exists($layoutFile)) {
            throw new \Exception("Layout não encontrado: {$layout}");
        }

        if ($viewFile === null) {
            throw new \Exception("View não encontrada: {$view}");
        }
        
        // Limpa qualquer output anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Preserva o array $data original ANTES de qualquer extract
        // Faz uma cópia profunda para garantir que não será modificado
        $dataForLayout = is_array($data) ? unserialize(serialize($data)) : [];

        $profiler = class_exists(\App\Performance\RequestProfiler::class);
        if ($profiler) {
            \App\Performance\RequestProfiler::markViewStart();
        }

        // Captura o conteúdo da view
        ob_start();
        try {
            // Cria uma cópia para o extract da view também
            $dataForView = is_array($data) ? unserialize(serialize($data)) : [];
            extract($dataForView);
            include $viewFile;
            $content = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            if ($profiler) {
                \App\Performance\RequestProfiler::markViewEnd();
            }
            Logger::error("Erro ao renderizar view {$view}: " . $e->getMessage(), [
                'exception' => $e,
                'view' => $view,
                'layout' => $layout,
                'data_type' => gettype($data),
                'data_keys' => is_array($data) ? array_keys($data) : []
            ], 'general');
            throw $e;
        }
        
        ob_start();
        try {
            // Verifica novamente antes de fazer extract
            if (!is_array($dataForLayout)) {
                error_log("BaseController::viewWithLayout - \$dataForLayout não é array na linha 116. Tipo: " . gettype($dataForLayout));
                error_log("BaseController::viewWithLayout - Conteúdo: " . var_export($dataForLayout, true));
                $dataForLayout = [];
            }
            extract($dataForLayout);
            include $layoutFile;
            ob_end_flush();
            if ($profiler) {
                \App\Performance\RequestProfiler::markViewEnd();
            }
        } catch (\Throwable $e) {
            ob_end_clean();
            if ($profiler) {
                \App\Performance\RequestProfiler::markViewEnd();
            }
            Logger::error("Erro ao renderizar layout {$layout}: " . $e->getMessage(), [
                'exception' => $e,
                'view' => $view,
                'layout' => $layout,
                'data_type' => gettype($dataForLayout),
                'data_keys' => is_array($dataForLayout) ? array_keys($dataForLayout) : []
            ], 'general');
            throw $e;
        }
    }
    
    /**
     * Retorna JSON
     */
    protected function json($data, $statusCode = 200)
    {
        // Descarta todo output já gerado (evita misturar erros PHP/HTML com JSON)
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            Logger::error('Falha ao serializar resposta JSON no controller.', [
                'status_code' => $statusCode,
                'json_error' => json_last_error_msg(),
                'data_type' => gettype($data),
            ], 'general');
            $json = json_encode([
                'error' => 'Falha interna ao serializar a resposta do servidor.',
                'json_error' => json_last_error_msg(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        echo $json;
        exit;
    }
    
    /**
     * Desabilita cache do navegador (útil para telas de login e formulários sensíveis).
     * Também envia o ID do usuário autenticado para o HTMX detectar troca de sessão entre abas.
     */
    protected function disableCache()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: no-cache, private');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Vary: Cookie');

        $authUserId = (int) ($_SESSION['user_id'] ?? 0);
        $authUserType = (string) ($_SESSION['user_type'] ?? '');
        if ($authUserId > 0 && $authUserType !== '') {
            header('X-Educatudo-Auth-User: ' . $authUserType . ':' . $authUserId);
        }
    }

    /**
     * Redireciona para uma URL
     */
    protected function redirect($url, $statusCode = 302)
    {
        // Garantir que a URL seja absoluta
        if (strpos($url, 'http') === 0) {
            $redirectUrl = $url;
        } else {
            $redirectUrl = URL . $url;
        }
        
        http_response_code($statusCode);
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    /**
     * Valida dados de entrada
     */
    protected function validate($data, $rules)
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if (strpos($rule, 'required') !== false && empty($value)) {
                $errors[$field] = "O campo {$field} é obrigatório";
            }
            
            if (strpos($rule, 'email') !== false && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "O campo {$field} deve ser um email válido";
            }
            
            if (strpos($rule, 'min:') !== false) {
                $min = (int) substr($rule, strpos($rule, 'min:') + 4);
                if (strlen($value) < $min) {
                    $errors[$field] = "O campo {$field} deve ter pelo menos {$min} caracteres";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Gera token CSRF. NÃO gera novo a cada request; NÃO sobrescreve token existente.
     * Token persiste durante a sessão (único por sessão, suporta múltiplas abas).
     */
    protected function generateCsrf()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Alias para compatibilidade com código existente.
     */
    protected function generateCsrfToken()
    {
        return $this->generateCsrf();
    }

    /**
     * Gera um novo token CSRF (sobrescreve o anterior). Usado quando o cliente envia token inválido
     * (ex.: sessão renovada noutra aba) para que a resposta devolva o token atual e o front atualize.
     */
    protected function refreshCsrfToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida token CSRF de forma segura (hash_equals).
     */
    protected function validateCsrf($token)
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Alias para compatibilidade com código existente.
     */
    protected function verifyCsrfToken($token)
    {
        return $this->validateCsrf($token);
    }
    
    /**
     * Define mensagem flash
     *
     * @param array<string,mixed>|null $meta Opcional: dados extras (ex.: debug para console no browser)
     */
    protected function setFlashMessage($message, $type = 'info', $meta = null)
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        if (is_array($meta)) {
            $_SESSION['flash_meta'] = $meta;
        } else {
            unset($_SESSION['flash_meta']);
        }
    }
    
    /**
     * Retorna mensagem flash
     *
     * @return array{message: ?string, type: string, meta: ?array}
     */
    protected function getFlashMessage()
    {
        $message = $_SESSION['flash_message'] ?? null;
        $type = $_SESSION['flash_type'] ?? 'info';
        $meta = $_SESSION['flash_meta'] ?? null;

        if ($message) {
            unset($_SESSION['flash_message'], $_SESSION['flash_type'], $_SESSION['flash_meta']);

            return [
                'message' => $message,
                'type' => $type,
                'meta' => is_array($meta) ? $meta : null,
            ];
        }

        if (is_array($meta)) {
            unset($_SESSION['flash_meta']);
        }

        return ['message' => null, 'type' => 'info', 'meta' => null];
    }
    
}

if (!class_exists('BaseController', false) && class_exists(__NAMESPACE__ . '\\BaseController', false)) {
    class_alias(__NAMESPACE__ . '\\BaseController', 'BaseController');
}
