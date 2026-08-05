<?php
/**
 * EducaTudo - Helper para crons em modo multi-tenant
 * Quando MULTI_TENANT=true: executa o callback para cada escola ativa (conexão do tenant já registrada).
 * Quando MULTI_TENANT=false: executa o callback uma vez (Database::getInstance() usa .env como hoje).
 *
 * Uso no cron:
 *   require_once __DIR__ . '/../app/Core/cron_multi_tenant_helper.php';
 *   CronMultiTenantHelper::run(function (?int $escolaId) {
 *       $db = Database::getInstance();
 *       // ... lógica do cron ...
 *   });
 */

// Garante mb_* mesmo quando o PHP CLI do servidor não tem a extensão mbstring
// (comum em workers/crons cujo binário difere do PHP-FPM). Evita fatais
// "Call to undefined function mb_*()" ao processar jobs de IA.
require_once __DIR__ . '/../Helpers/mbstring_polyfill.php';

class CronMultiTenantHelper
{
    /**
     * Executa $callback uma vez (single-tenant) ou uma vez por escola ativa (multi-tenant).
     * Em multi-tenant, $escolaId é passado para o callback; em single-tenant é null.
     *
     * @param callable $callback function(?int $escolaId): void
     * @param bool $forceIterateTenants Se true, sempre itera os bancos das escolas (útil para crons no servidor master com MULTI_TENANT=false)
     */
    public static function run(callable $callback, bool $forceIterateTenants = false): void
    {
        $multiTenant = self::isMultiTenantEnabled();
        if (!$multiTenant && !$forceIterateTenants) {
            Database::setCurrentInstance(null);
            $callback(null);
            return;
        }

        require_once __DIR__ . '/TenantResolver.php';
        require_once __DIR__ . '/DatabaseManager.php';

        $masterConfig = Database::getConfigFromEnv();
        $masterPdo = self::createMasterPdo($masterConfig);
        $resolver = new TenantResolver($masterPdo);
        $manager = new DatabaseManager($masterPdo);
        $escolasIds = $resolver->listarEscolasAtivas();

        if (empty($escolasIds)) {
            if (!$forceIterateTenants) {
                return;
            }
            // forceIterateTenants mas nenhuma escola ativa: roda uma vez com conexão padrão (comportamento legado)
            Database::setCurrentInstance(null);
            $callback(null);
            return;
        }

        $masterDbName = trim($masterConfig['name'] ?? '');
        foreach ($escolasIds as $escolaId) {
            try {
                // Evitar rodar lógica de tenant contra o banco master (escola com nome_banco = master)
                if ($masterDbName !== '') {
                    $stmt = $masterPdo->prepare(
                        "SELECT nome_banco FROM config_escolas_banco WHERE escola_id = :escola_id LIMIT 1"
                    );
                    $stmt->execute(['escola_id' => $escolaId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && trim($row['nome_banco'] ?? '') === $masterDbName) {
                        error_log("Cron multi-tenant: pulando escola_id={$escolaId} (banco aponta para o master; configure um banco próprio em config_escolas_banco).");
                        continue;
                    }
                }
                $tenantDb = $manager->getConnectionForTenant($escolaId);
                Database::setCurrentInstance($tenantDb);
                $callback($escolaId);
            } catch (Throwable $e) {
                error_log("Cron multi-tenant: erro para escola_id={$escolaId}: " . $e->getMessage());
                // Continua para a próxima escola
            } finally {
                Database::setCurrentInstance(null);
            }
        }
    }

    private static function isMultiTenantEnabled(): bool
    {
        $envPath = defined('ENV_FILE_PATH') ? ENV_FILE_PATH : (__DIR__ . '/../../.env');
        $paths = [$envPath, __DIR__ . '/../../.env', __DIR__ . '/../../../.env'];
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
                if (strpos($line, 'MULTI_TENANT=') === 0) {
                    $v = trim(substr($line, 12));
                    if (strlen($v) >= 2 && $v[0] === '"' && substr($v, -1) === '"') {
                        $v = substr($v, 1, -1);
                    }
                    return strtolower($v) === 'true';
                }
            }
            break;
        }
        return false;
    }

    private static function createMasterPdo(array $config): PDO
    {
        $host = $config['host'] ?? 'localhost';
        $port = (int) ($config['port'] ?? 3306);
        $name = $config['name'] ?? '';
        $user = $config['user'] ?? '';
        $pass = $config['pass'] ?? '';
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET time_zone = '-03:00'");
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        return $pdo;
    }
}
