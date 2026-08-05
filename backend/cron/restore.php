<?php
/**
 * EducaTudo - Script de Restauração de Backup
 * Restaura banco de dados e arquivos de um backup
 * 
 * Uso: php cron/restore.php <backup_file> [--database-only] [--files-only]
 * 
 * Exemplos:
 *   php cron/restore.php storage/backups/educatudo_backup_2024-01-15_10-30-00.tar.gz
 *   php cron/restore.php --drive-file-id <file_id>
 *   php cron/restore.php storage/backups/backup.tar.gz --database-only
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
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Services/BackupService.php';

// Verificar argumentos
$backupFile = null;
$driveFileId = null;
$restoreDatabase = true;
$restoreFiles = true;

foreach ($argv as $i => $arg) {
    if ($i === 0) continue; // Nome do script
    
    if ($arg === '--database-only') {
        $restoreFiles = false;
    } elseif ($arg === '--files-only') {
        $restoreDatabase = false;
    } elseif ($arg === '--drive-file-id' && isset($argv[$i + 1])) {
        $driveFileId = $argv[$i + 1];
    } elseif (!str_starts_with($arg, '--')) {
        $backupFile = $arg;
    }
}

echo "========================================\n";
echo "EducaTudo - Restauração de Backup\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// Validar argumentos
if (!$backupFile && !$driveFileId) {
    echo "✗ Erro: Especifique um arquivo de backup ou um ID do Google Drive\n";
    echo "\nUso:\n";
    echo "  php cron/restore.php <backup_file> [opções]\n";
    echo "  php cron/restore.php --drive-file-id <file_id> [opções]\n";
    echo "\nOpções:\n";
    echo "  --database-only    Restaura apenas o banco de dados\n";
    echo "  --files-only      Restaura apenas os arquivos\n";
    exit(1);
}

try {
    $backupService = new BackupService();
    $config = require __DIR__ . '/../config/app.php';
    
    // Se for backup do Drive, baixar primeiro
    if ($driveFileId) {
        echo "Baixando backup do Google Drive...\n";
        $backupFile = $backupService->downloadFromDrive($driveFileId);
        if (!$backupFile) {
            echo "✗ Erro ao baixar backup do Google Drive\n";
            exit(1);
        }
        echo "✓ Backup baixado: {$backupFile}\n\n";
    }
    
    // Verificar se arquivo existe
    if (!file_exists($backupFile)) {
        echo "✗ Erro: Arquivo de backup não encontrado: {$backupFile}\n";
        exit(1);
    }
    
    // Extrair backup
    echo "Extraindo backup...\n";
    $extractDir = sys_get_temp_dir() . '/educatudo_restore_' . time();
    mkdir($extractDir, 0755, true);
    
    $command = sprintf(
        'tar -xzf %s -C %s 2>&1',
        escapeshellarg($backupFile),
        escapeshellarg($extractDir)
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        echo "✗ Erro ao extrair backup: " . implode("\n", $output) . "\n";
        exit(1);
    }
    
    echo "✓ Backup extraído\n\n";
    
    // Ler metadados
    $metadataFile = $extractDir . '/metadata.json';
    $metadata = null;
    if (file_exists($metadataFile)) {
        $metadata = json_decode(file_get_contents($metadataFile), true);
        echo "Informações do backup:\n";
        echo "  Data: {$metadata['timestamp']}\n";
        echo "  Versão: {$metadata['version']}\n";
        echo "  Ambiente: {$metadata['environment']}\n\n";
    }
    
    // Restaurar banco de dados
    if ($restoreDatabase) {
        echo "Restaurando banco de dados...\n";
        
        $dbFiles = glob($extractDir . '/*_database.sql');
        if (empty($dbFiles)) {
            echo "✗ Erro: Arquivo de backup do banco de dados não encontrado\n";
            exit(1);
        }
        
        $dbFile = $dbFiles[0];
        $dbConfig = $config['database'];
        
        // Confirmar restauração
        echo "\n⚠ ATENÇÃO: Esta operação irá SOBRESCREVER o banco de dados atual!\n";
        echo "Banco de dados: {$dbConfig['name']}\n";
        echo "Host: {$dbConfig['host']}\n";
        echo "\nDeseja continuar? (digite 'SIM' para confirmar): ";
        
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if ($line !== 'SIM') {
            echo "Restauração cancelada.\n";
            exit(0);
        }
        
        // Fazer backup antes de restaurar (segurança)
        echo "\nCriando backup de segurança antes da restauração...\n";
        $safetyBackup = $backupService->createFullBackup(false);
        if ($safetyBackup['success']) {
            echo "✓ Backup de segurança criado: {$safetyBackup['backup_name']}\n";
        }
        
        // Restaurar banco
        $command = sprintf(
            'mysql -h %s -P %s -u %s -p%s %s < %s 2>&1',
            escapeshellarg($dbConfig['host']),
            escapeshellarg($dbConfig['port']),
            escapeshellarg($dbConfig['user']),
            escapeshellarg($dbConfig['pass']),
            escapeshellarg($dbConfig['name']),
            escapeshellarg($dbFile)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            echo "✗ Erro ao restaurar banco de dados: " . implode("\n", $output) . "\n";
            exit(1);
        }
        
        echo "✓ Banco de dados restaurado com sucesso!\n\n";
    }
    
    // Restaurar arquivos
    if ($restoreFiles) {
        echo "Restaurando arquivos de upload...\n";
        
        $uploadsArchive = glob($extractDir . '/*_uploads.tar.gz');
        if (!empty($uploadsArchive)) {
            $uploadsDir = $config['upload']['path'];
            
            // Fazer backup do diretório atual
            if (is_dir($uploadsDir)) {
                $backupDir = $uploadsDir . '_backup_' . date('Y-m-d_H-i-s');
                echo "Fazendo backup do diretório atual: {$backupDir}\n";
                $backupService->copyDirectoryPublic($uploadsDir, $backupDir);
            }
            
            // Extrair arquivos
            $command = sprintf(
                'tar -xzf %s -C %s 2>&1',
                escapeshellarg($uploadsArchive[0]),
                escapeshellarg($uploadsDir)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                echo "✗ Erro ao restaurar arquivos: " . implode("\n", $output) . "\n";
                exit(1);
            }
            
            echo "✓ Arquivos restaurados com sucesso!\n\n";
        } else {
            echo "⚠ Aviso: Arquivo de backup de uploads não encontrado\n";
        }
    }
    
    // Limpar arquivos temporários
    echo "Limpando arquivos temporários...\n";
    $files = array_diff(scandir($extractDir), ['.', '..']);
    foreach ($files as $file) {
        $path = $extractDir . '/' . $file;
        is_dir($path) ? $backupService->removeDirectoryPublic($path) : unlink($path);
    }
    rmdir($extractDir);
    echo "✓ Limpeza concluída\n\n";
    
    echo "========================================\n";
    echo "Restauração concluída com sucesso!\n";
    echo "========================================\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "✗ Erro fatal: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    error_log("ERRO FATAL NA RESTAURAÇÃO: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    exit(1);
}

