<?php
/**
 * EducaTudo - Script de Backup Automatizado
 * Executa backup diário do banco de dados e arquivos
 * 
 * Uso: php cron/backup.php [--no-upload]
 * 
 * Opções:
 *   --no-upload    Não faz upload para Google Drive (apenas backup local)
 */

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Definir constantes necessárias (normalmente definidas no index.php)
if (!defined('FOLDER')) {
    define('FOLDER', '');
}
if (!defined('URL')) {
    define('URL', 'http://localhost');
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'production'); // Para scripts CLI, usar production por padrão
}
if (!defined('DEBUG')) {
    define('DEBUG', ENVIRONMENT === 'development');
}

// Carregar autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar classes necessárias
if (!defined('ENV_FILE_PATH')) {
    define('ENV_FILE_PATH', __DIR__ . '/../.env');
}
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Services/BackupService.php';
if (file_exists(__DIR__ . '/../app/Core/cron_multi_tenant_helper.php')) {
    require_once __DIR__ . '/../app/Core/cron_multi_tenant_helper.php';
}

// Verificar argumentos
$uploadToDrive = true;
if (isset($argv[1]) && $argv[1] === '--no-upload') {
    $uploadToDrive = false;
}

echo "========================================\n";
echo "EducaTudo - Backup Automatizado\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    $runBackup = function (?int $escolaId) use ($uploadToDrive) {
        $prefix = $escolaId !== null ? "[escola {$escolaId}] " : "";
        $backupService = new BackupService();
        echo $prefix . "Iniciando backup completo...\n";
        $result = $backupService->createFullBackup($uploadToDrive);

        if ($result['success']) {
            echo $prefix . "✓ Backup criado com sucesso!\n";
            echo "  Nome: {$result['backup_name']}\n";
            if (isset($result['archive']) && is_array($result['archive'])) {
                $sizeMB = round($result['archive']['size'] / 1024 / 1024, 2);
                echo "  Arquivo: {$result['archive']['file']}\n";
                echo "  Tamanho: {$sizeMB} MB\n";
            }
            if (isset($result['drive_file_id'])) {
                echo "  ✓ Upload para Google Drive concluído\n";
            } elseif ($uploadToDrive && !empty($result['errors'])) {
                echo "  ⚠ Upload para Google Drive não realizado\n";
            }
            $backups = $backupService->listBackups(false);
            $cutoffDate = strtotime('-7 days');
            $deleted = 0;
            foreach ($backups as $backup) {
                if (strtotime($backup['created_at']) < $cutoffDate) {
                    if ($backupService->deleteLocalBackup($backup['name'])) {
                        $deleted++;
                    }
                }
            }
            if ($deleted > 0) {
                echo "  Backups antigos removidos: {$deleted}\n";
            }
        } else {
            echo $prefix . "✗ Erro ao criar backup!\n";
            foreach ($result['errors'] as $error) {
                echo "  - {$error}\n";
            }
            error_log("ERRO NO BACKUP: " . implode(" | ", $result['errors']));
        }
    };

    if (class_exists('CronMultiTenantHelper')) {
        CronMultiTenantHelper::run($runBackup);
    } else {
        $runBackup(null);
    }

    echo "\n========================================\n";
    echo "Backup concluído.\n";
    echo "========================================\n";
    exit(0);

} catch (Exception $e) {
    echo "✗ Erro fatal: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    error_log("ERRO FATAL NO BACKUP: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    exit(1);
}

