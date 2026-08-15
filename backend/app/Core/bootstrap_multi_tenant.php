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
require_once __DIR__ . '/RedisCache.php';

if (!function_exists('educatudo_definir_constantes_tenant')) {
    function educatudo_definir_constantes_tenant(array $tenant): void
    {
        if (!defined('TENANT_ID')) {
            define('TENANT_ID', (int) $tenant['id']);
        }
        if (!defined('TENANT_SLUG')) {
            define('TENANT_SLUG', (string) ($tenant['slug'] ?? ''));
        }
        if (!defined('TENANT_DOMAIN')) {
            define('TENANT_DOMAIN', (string) ($tenant['dominio'] ?? ''));
        }
    }
}

// Rotas /master e domínio MASTER continuam no fluxo original (sempre abrem o MASTER).
if ($isMasterPath || $isMasterDomain) {
    $masterPdo = Database::createMasterPdo();
    $GLOBALS['_educatudo_master_pdo'] = $masterPdo;
    Database::setCurrentInstance(Database::createFromPdo($masterPdo));
    return;
}

// Fast-path: Redis HIT em tenant + config → abre só o TENANT, sem handshake no MASTER.
$fastPathAplicado = false;
try {
    $tenantCache = TenantResolver::cachedTenantFromRequest();
    if (is_array($tenantCache) && isset($tenantCache['id'], $tenantCache['slug'])) {
        $escolaId = (int) $tenantCache['id'];
        $configCache = $escolaId > 0 ? DatabaseManager::cachedConfig($escolaId) : null;
        if (is_array($configCache)) {
            $manager = new DatabaseManager(null);
            $tenantDb = $manager->createConnectionFromConfig($configCache, $escolaId);
            Database::setCurrentInstance($tenantDb);
            $GLOBALS['_educatudo_db_manager'] = $manager;
            educatudo_definir_constantes_tenant($tenantCache);
            $fastPathAplicado = true;
        }
    }
} catch (Throwable $e) {
    Database::setCurrentInstance(null);
    unset($GLOBALS['_educatudo_db_manager']);
    $escolaId = isset($escolaId) ? (int) $escolaId : 0;
    if ($escolaId > 0) {
        RedisCache::delete('tenant_config_' . $escolaId);
        RedisCache::delete(TenantResolver::cacheKeyFromRequest());
    }
    error_log('[bootstrap_multi_tenant] fast-path falhou, fallback MASTER: ' . $e->getMessage());
}

if ($fastPathAplicado) {
    return;
}

// Fallback (cache miss / inválido / falha de conexão): MASTER → resolve/cacheia → TENANT
$masterPdo = Database::createMasterPdo();
$GLOBALS['_educatudo_master_pdo'] = $masterPdo;

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
educatudo_definir_constantes_tenant($tenant);
