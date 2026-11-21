<?php

namespace App\Middleware;

/**
 * CSRF centralizado para rotas web com sessão (grupo Auth).
 *
 * Modos (env CSRF_MODE):
 * - audit (padrão): registra token ausente/inválido e segue (sem quebrar legado).
 * - enforce: bloqueia com 403 JSON ou 419 HTML.
 *
 * Não aplicar em JWT/webhooks: essas rotas ficam fora do middleware Auth+Csrf.
 */
class CsrfMiddleware
{
    private const METODOS_MUTAVEIS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var string audit|enforce */
    private string $modo;

    /** @var list<string> */
    private array $excecoes;

    /** @var callable|null */
    private $logger;

    /**
     * @param array{modo?:string,excecoes?:list<string>,logger?:callable}|null $opcoes
     */
    public function __construct(?array $opcoes = null)
    {
        $modoEnv = strtolower((string) ($_ENV['CSRF_MODE'] ?? getenv('CSRF_MODE') ?: 'audit'));
        $this->modo = $opcoes['modo'] ?? ($modoEnv === 'enforce' ? 'enforce' : 'audit');
        $this->excecoes = $opcoes['excecoes'] ?? $this->carregarExcecoes();
        $this->logger = $opcoes['logger'] ?? null;
    }

    public function handle(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, self::METODOS_MUTAVEIS, true)) {
            return;
        }

        $path = $this->pathAtual();
        if ($this->estaIsento($path)) {
            return;
        }

        $token = $this->tokenDaRequisicao();
        $sessao = (string) ($_SESSION['csrf_token'] ?? '');

        if ($sessao !== '' && $token !== '' && hash_equals($sessao, $token)) {
            return;
        }

        $motivo = $sessao === '' ? 'sessao_sem_token' : ($token === '' ? 'token_ausente' : 'token_invalido');
        $this->registrar($path, $method, $motivo);

        if ($this->modo !== 'enforce') {
            $this->avisarAuditEmProducao();
            return;
        }

        $this->bloquear();
    }

    /**
     * Em produção, audit não bloqueia — registra alerta único por worker para lembrar do CSRF_MODE=enforce.
     */
    private function avisarAuditEmProducao(): void
    {
        static $avisou = false;
        if ($avisou) {
            return;
        }
        $env = defined('ENVIRONMENT') ? (string) ENVIRONMENT : 'production';
        if ($env !== 'production') {
            return;
        }
        $avisou = true;
        try {
            if (class_exists(\App\Core\Logger::class, false) || class_exists('Logger')) {
                \App\Core\Logger::warning(
                    '[CSRF] modo audit em produção — defina CSRF_MODE=enforce após homologar formulários',
                    ['modo' => $this->modo],
                    'security'
                );
            }
        } catch (\Throwable $e) {
            // não interromper
        }
    }

    /** @return list<string> */
    private function carregarExcecoes(): array
    {
        $arquivo = dirname(__DIR__, 2) . '/config/csrf_excecoes.php';
        if (!is_file($arquivo)) {
            return [];
        }
        $lista = require $arquivo;
        return is_array($lista) ? array_values(array_filter($lista, 'is_string')) : [];
    }

    private function pathAtual(): string
    {
        $raw = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($raw, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        if (defined('FOLDER') && FOLDER !== '' && strpos($path, FOLDER) === 0) {
            $path = substr($path, strlen(FOLDER)) ?: '/';
        }
        return $path;
    }

    private function estaIsento(string $path): bool
    {
        foreach ($this->excecoes as $prefixo) {
            if ($prefixo !== '' && str_starts_with($path, $prefixo)) {
                return true;
            }
        }
        return false;
    }

    private function tokenDaRequisicao(): string
    {
        $nome = (string) ($_ENV['CSRF_TOKEN_NAME'] ?? getenv('CSRF_TOKEN_NAME') ?: '_token');
        if (isset($_POST[$nome]) && is_string($_POST[$nome])) {
            return $_POST[$nome];
        }
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';
        if (is_string($header) && $header !== '') {
            return $header;
        }
        // Alguns clients enviam no header genérico
        $xToken = $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
        return is_string($xToken) ? $xToken : '';
    }

    private function registrar(string $path, string $method, string $motivo): void
    {
        $contexto = [
            'path' => $path,
            'method' => $method,
            'motivo' => $motivo,
            'modo' => $this->modo,
        ];
        if (is_callable($this->logger)) {
            ($this->logger)($contexto);
            return;
        }
        try {
            if (class_exists(\App\Core\Logger::class, false) || class_exists('Logger')) {
                \App\Core\Logger::warning('[CSRF] validação falhou', $contexto, 'security');
            }
        } catch (\Throwable $e) {
            // Não interromper request em audit
        }
    }

    private function bloquear(): void
    {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $acceptsJson = isset($_SERVER['HTTP_ACCEPT'])
            && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        $wantsJson = $isAjax || $acceptsJson
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains((string) $_SERVER['CONTENT_TYPE'], 'application/json'));

        if ($wantsJson) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Token CSRF inválido ou ausente.',
                'code' => 'csrf_invalid',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(419);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Token CSRF inválido ou ausente. Recarregue a página e tente novamente.';
        exit;
    }
}

if (!class_exists('CsrfMiddleware', false) && class_exists(__NAMESPACE__ . '\\CsrfMiddleware', false)) {
    class_alias(__NAMESPACE__ . '\\CsrfMiddleware', 'CsrfMiddleware');
}
