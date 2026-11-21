<?php
/**
 * Aplica no Docker local a migration master do 2FA (OTP).
 * Usa o mesmo serviço do painel Master (MysqlProvisioningService).
 *
 * Uso: docker exec php_app_educatudo php /var/www/html/scripts/aplicar_migration_master_otp_local.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/app/Core/MysqlProvisioningService.php';

use App\Core\MysqlProvisioningService;

$env = [];
foreach (file(BASE_PATH . '/.env') as $line) {
    $line = trim($line);
    if ($line === '' || ($line[0] ?? '') === '#' || strpos($line, '=') === false) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}

$host = $env['DB_HOST'] ?? 'mysql';
$port = $env['DB_PORT'] ?? '3306';
$name = $env['DB_NAME'] ?? 'educatudo_master';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$arquivo = '2026_11_19_usuarios_master_codigos_acesso_master.sql';
$executadas = MysqlProvisioningService::runMasterMigrationsSelected($pdo, [$arquivo]);

$ok = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_master_codigos_acesso'"
)->fetchColumn();

echo json_encode([
    'migration' => $arquivo,
    'executadas' => $executadas,
    'tabela_pronta' => $ok === 1,
    'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($ok === 1 ? 0 : 1);
