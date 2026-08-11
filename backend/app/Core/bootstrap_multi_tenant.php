<?php
/**
 * EducaTudo - Bootstrap multi-tenant
 * Quando MULTI_TENANT=true no .env: conecta ao banco master, resolve o tenant (escola)
 * e registra a conexão do tenant como a instância atual de Database.
 * Deve ser incluído em index.php antes de new App().
 */

if (!defined('ENV_FILE_PATH')) {
    return;
}

$envPath = ENV_FILE_PATH;
$paths = [$envPath, __DIR__ . '/../../.env', __DIR__ . '/../../../.env'];
$multiTenant = 'false';
$masterDomain = '';
foreach ($paths as $p) {
    $resolved = is_file($p) ? $p : (realpath($p) ?: $p);
    if (!is_file($resolved)) {
        continue;
    }
    $lines = @file($resolved, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        continue;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (stripos($line, 'MULTI_TENANT') === 0 && preg_match('/MULTI_TENANT\s*=\s*(.+)$/i', $line, $m)) {
            $v = trim($m[1]);
            if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                $v = substr($v, 1, -1);
            }
            $multiTenant = trim($v);
        }
        if (stripos($line, 'MASTER_DOMAIN') === 0 && preg_match('/MASTER_DOMAIN\s*=\s*(.+)$/i', $line, $m)) {
            $v = trim($m[1]);
            if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                $v = substr($v, 1, -1);
            }
            $masterDomain = trim($v);
        }
    }
    if ($multiTenant !== 'false') {
        break;
    }
}

if (strtolower($multiTenant) !== 'true') {
    return;
}

define('MULTI_TENANT_ACTIVE', true);
if ($masterDomain !== '') {
    define('MASTER_DOMAIN', $masterDomain);
}

// Se a requisição for para o painel /master, usa conexão master (não resolve tenant)
$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($uri, PHP_URL_PATH);
$path = $path === false ? '' : $path;
$folder = defined('FOLDER') ? FOLDER : '';
$pathRelative = $folder !== '' && strpos($path, $folder) === 0 ? substr($path, strlen($folder)) : $path;
$pathRelative = trim($pathRelative, '/');
$isMasterPath = ($pathRelative === 'master' || strpos($pathRelative, 'master/') === 0);
$host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
if ($host !== '' && strpos($host, ':') !== false) {
    $host = explode(':', $host, 2)[0];
}
$isMasterDomain = false;
if (defined('MASTER_DOMAIN') && MASTER_DOMAIN !== '') {
    $isMasterDomain = (strtolower(trim(MASTER_DOMAIN)) === $host);
} else {
    $isMasterDomain = (strpos($host, 'master.') === 0);
}

// Redirecionar raiz do domínio master para /master (ex.: master.educatudo.com -> master.educatudo.com/master)
if (!$isMasterPath && $pathRelative === '') {
    if ($isMasterDomain) {
        $redirectUrl = ($folder !== '' ? $folder : '') . '/master';
        if (!headers_sent()) {
            header('Location: ' . $redirectUrl, true, 302);
            exit;
        }
    }
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TenantResolver.php';
require_once __DIR__ . '/DatabaseManager.php';

$masterConfig = Database::getConfigFromEnv();
$required = ['host', 'name', 'user', 'pass'];
foreach ($required as $key) {
    if (empty($masterConfig[$key]) && $masterConfig[$key] !== '0') {
        throw new Exception("Multi-tenant ativo mas configuração do banco master incompleta no .env (DB_HOST, DB_NAME, DB_USER, DB_PASS).");
    }
}
$port = isset($masterConfig['port']) && $masterConfig['port'] !== '' ? (int) $masterConfig['port'] : 3306;
$connectTimeoutRaw = getenv('DB_CONNECT_TIMEOUT');
$connectTimeout = ($connectTimeoutRaw !== false && $connectTimeoutRaw !== null && $connectTimeoutRaw !== '')
    ? (int) $connectTimeoutRaw
    : 15;
if ($connectTimeout < 1) {
    $connectTimeout = 15;
} elseif ($connectTimeout > 60) {
    $connectTimeout = 60;
}
$dsn = "mysql:host={$masterConfig['host']};port={$port};dbname={$masterConfig['name']};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Conexão não-persistente evita "Packets out of order" quando o MySQL fecha conexões idle
    PDO::ATTR_PERSISTENT => false,
    PDO::ATTR_TIMEOUT => $connectTimeout,
];
$masterPdo = new PDO($dsn, (string) $masterConfig['user'], (string) $masterConfig['pass'], $options);
$masterPdo->exec("SET time_zone = '-03:00'");
$masterPdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$GLOBALS['_educatudo_master_pdo'] = $masterPdo;

if ($isMasterPath || $isMasterDomain) {
    Database::setCurrentInstance(Database::createFromPdo($masterPdo));
    return;
}

$resolver = new TenantResolver($masterPdo);
$tenant = $resolver->resolveTenant();
if ($tenant === null) {
    $errorHost = $_SERVER['HTTP_HOST'] ?? 'N/A';
    if (defined('MASTER_DOMAIN') && MASTER_DOMAIN !== '' && strtolower(trim((string) MASTER_DOMAIN)) === strtolower(trim((string) $errorHost))) {
        Database::setCurrentInstance(Database::createFromPdo($masterPdo));
        return;
    }
    $errorHost = $errorHost !== '' ? $errorHost : 'N/A';
    if ($errorHost !== 'N/A' && strpos($errorHost, ':') !== false) {
        $errorHost = explode(':', $errorHost, 2)[0];
    }
    $errorHost = strtolower($errorHost);
    throw new Exception("Multi-tenant: nenhuma escola ativa encontrada para este domínio/host ({$errorHost}). Cadastre a escola e o domínio na tabela escolas (banco master) ou use o header X-Tenant com o slug da escola.");
}

if (!isset($GLOBALS['_educatudo_db_manager'])) {
    $GLOBALS['_educatudo_db_manager'] = new DatabaseManager($masterPdo);
}
$manager = $GLOBALS['_educatudo_db_manager'];
$tenantDb = $manager->getConnectionForTenant((int) $tenant['id']);
Database::setCurrentInstance($tenantDb);
if (!defined('TENANT_ID')) {
    define('TENANT_ID', (int) $tenant['id']);
}
if (!defined('TENANT_SLUG')) {
    define('TENANT_SLUG', (string) ($tenant['slug'] ?? ''));
}
if (!defined('TENANT_DOMAIN')) {
    define('TENANT_DOMAIN', (string) ($tenant['dominio'] ?? ''));
}
