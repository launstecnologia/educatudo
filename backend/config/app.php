<?php
/**
 * EducaTudo - Configurações da Aplicação
 * Este arquivo deve permanecer na pasta config/ (config/app.php).
 * Carrega configurações do arquivo .env na raiz do projeto.
 */

// Função para carregar variáveis do .env (apenas se não existir)
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path) || !is_readable($path)) {
            return [];
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }
        $env = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $env[trim($name)] = trim($value);
        }
        return $env;
    }
}

// Carregar variáveis do .env (pasta acima de config = raiz do projeto)
// Usa $GLOBALS para garantir escopo global mesmo quando incluido dentro de metodo/funcao
$GLOBALS['env'] = loadEnv(__DIR__ . '/../.env');
$env = &$GLOBALS['env'];
$tenantSlugRuntime = '';
$tenantIdRuntime = null;
$tenantDomainRuntime = '';
if (defined('MULTI_TENANT_ACTIVE') && MULTI_TENANT_ACTIVE) {
    if (defined('TENANT_SLUG')) {
        $tenantSlugRuntime = trim((string) TENANT_SLUG);
    }
    if (defined('TENANT_ID')) {
        $tenantIdRuntime = (int) TENANT_ID;
    }
    if (defined('TENANT_DOMAIN')) {
        $tenantDomainRuntime = trim((string) TENANT_DOMAIN);
    }
}

// Função helper para obter variável do .env
if (!function_exists('env')) {
    function env($key, $default = null) {
        global $env;
        $v = isset($env[$key]) ? $env[$key] : $default;
        if ($v === '' || $v === null) {
            return $default;
        }
        return $v;
    }
}

$schoolCodeConfig = $tenantSlugRuntime !== '' ? $tenantSlugRuntime : env('SCHOOL_CODE', 'default');

return [
    'app' => [
        'name' => 'EducaTudo',
        'version' => '1.0.0',
        'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'production',
        'debug' => defined('DEBUG') ? DEBUG : false,
        'url' => defined('URL') ? URL : '',
        'folder' => defined('FOLDER') ? FOLDER : '',
    ],

    'database' => [
        'host' => env('DB_HOST', ''),
        'port' => (($p = env('DB_PORT', '3306')) !== '' && $p !== null) ? (int) $p : 3306,
        'name' => env('DB_NAME', ''),
        'user' => env('DB_USER', ''),
        'pass' => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=600, interactive_timeout=600",
        ]
    ],

    'session' => [
        'name' => 'EDUCATUDO_SESSION',
        'lifetime' => (int) env('SESSION_LIFETIME', 3600),
        'domain' => env('SESSION_DOMAIN', ''),
        'secure' => env('SESSION_SECURE', 'true') === 'true',
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    'security' => [
        'csrf_token_name' => env('CSRF_TOKEN_NAME', '_token'),
        'password_min_length' => (int) env('PASSWORD_MIN_LENGTH', 8),
        'max_login_attempts' => (int) env('MAX_LOGIN_ATTEMPTS', 5),
        'lockout_duration' => (int) env('LOCKOUT_DURATION', 900),
        // Limite por IP: maior que o por nickname, para não bloquear outros usuários no mesmo IP (ex.: laboratório)
        'max_login_attempts_ip' => (int) env('MAX_LOGIN_ATTEMPTS_IP', 50),
        // Chave para "Entrar como" (master): se não definir ENTRA_COMO_SECRET, usa valor derivado do banco no .env
        'entra_como_secret' => env('ENTRA_COMO_SECRET') !== '' ? env('ENTRA_COMO_SECRET') : hash('sha256', 'entrar-como-' . env('DB_HOST', '') . env('DB_NAME', '') . env('DB_USER', '') . env('DB_PASS', '')),
    ],

    'upload' => [
        'max_size' => (int) env('UPLOAD_MAX_SIZE', 10 * 1024 * 1024),
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'webp'],
        'path' => __DIR__ . '/../storage/uploads/',
        'chat_path' => __DIR__ . '/../storage/chat/',
    ],

    'ai' => [
        'openai_api_key' => env('OPENAI_API_KEY', ''),
        'elevenlabs_api_key' => env('ELEVENLABS_API_KEY', ''),
        'serpapi_api_key' => env('SERPAPI_KEY', ''),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 2000),
        'temperature' => (float) env('AI_TEMPERATURE', 0.7),
    ],

    'email' => [
        'host' => env('MAIL_HOST', 'smtp.gmail.com'),
        'port' => (int) env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'from_address' => env('MAIL_FROM_ADDRESS', ''),
        'from_name' => env('MAIL_FROM_NAME', 'EducaTudo'),
    ],

    'logs' => [
        'level' => env('LOG_LEVEL', 'error'),
        'path' => env('LOG_PATH', __DIR__ . '/../storage/logs/'),
    ],

    'school' => [
        'code' => $schoolCodeConfig,
        'name' => env('ESCOLA_NOME', 'Colag'),
        'logo' => env('ESCOLA_LOGO', ''),
        'colors' => [
            'primary' => env('ESCOLA_CORES_PRIMARY', '#3b82f6'),
            'secondary' => env('ESCOLA_CORES_SECONDARY', '#0ea5e9'),
        ],
    ],

    'aws' => [
        'bucket' => env('AWS_BUCKET', ''),
        'key' => env('AWS_ACCESS_KEY', ''),
        'secret' => env('AWS_SECRET_KEY', ''),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],

    'drive' => [
        'storage' => env('DRIVE_STORAGE', 'local'),
        'local_path' => __DIR__ . '/../storage/drive',
    ],

    'media' => [
        'storage' => env('MEDIA_STORAGE', 'local'),
        'local_base' => __DIR__ . '/..',
        'files_base' => 'storage/files',
        'tenant_prefix' => env('MEDIA_TENANT_PREFIX', '') === 'true',
        'local_paths' => [
            'layout' => 'public/uploads/layout',
            'provas_questoes' => 'storage/provas/questoes',
            'chat' => 'storage/chat',
            'jornadas_exercicios' => 'public/uploads/jornadas/exercicios',
            'jornadas_documentos' => 'public/uploads/documentos',
            'jornadas_videos' => 'public/uploads/videos',
            'redacoes' => 'public/uploads/redacoes',
            'essays_proposals' => 'public/uploads/essays/proposals',
            'arquivos' => 'public/uploads/arquivos',
            'apostilas' => 'public/uploads/apostilas',
        ],
    ],

    'tenant' => [
        'id' => $tenantIdRuntime,
        'slug' => $tenantSlugRuntime,
        'domain' => $tenantDomainRuntime,
    ],

    'perfiles' => [
        'admin_escola' => ['diretor', 'coordenador', 'dev'],
        'professor' => [],
        'aluno' => [],
        'pai' => [],
    ],

    'routes' => [
        'aluno' => '/',
        'professor' => '/professor',
        'admin' => '/admin',
        'pais' => '/pais',
    ]
];
