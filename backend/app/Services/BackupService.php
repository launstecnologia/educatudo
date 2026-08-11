<?php
/**
 * EducaTudo - Serviço de Backup
 * Gerencia backups do banco de dados e arquivos para Google Drive
 */

class BackupService
{
    private $db;
    private $config;
    private $backupDir;
    private $googleDriveService;
    private $projectRoot;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->config = require __DIR__ . '/../../config/app.php';
        
        // Determinar diretório raiz do projeto
        $this->projectRoot = realpath(__DIR__ . '/../..') ?: __DIR__ . '/../..';
        
        $this->backupDir = $this->projectRoot . '/storage/backups';
        
        // Criar diretório de backups se não existir
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Executa backup completo (banco de dados + arquivos)
     * 
     * @param bool $uploadToDrive Se deve fazer upload para Google Drive
     * @return array Informações do backup criado
     */
    public function createFullBackup($uploadToDrive = true)
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "educatudo_backup_{$timestamp}";
        $backupPath = $this->backupDir . '/' . $backupName;
        
        // Criar diretório do backup
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }
        
        $result = [
            'success' => true,
            'backup_name' => $backupName,
            'backup_path' => $backupPath,
            'timestamp' => $timestamp,
            'files' => [],
            'errors' => []
        ];
        
        try {
            // 1. Backup do banco de dados
            $dbBackup = $this->backupDatabase($backupPath, $backupName);
            if ($dbBackup['success']) {
                $result['files'][] = $dbBackup['file'];
            } else {
                $result['errors'][] = $dbBackup['error'];
                $result['success'] = false;
            }
            
            // 2. Backup dos arquivos de upload
            $filesBackup = $this->backupUploadFiles($backupPath, $backupName);
            if ($filesBackup['success']) {
                $result['files'][] = $filesBackup['file'];
            } else {
                $result['errors'][] = $filesBackup['error'];
            }
            
            // 3. Criar arquivo de metadados
            $metadata = $this->createMetadata($backupPath, $backupName, $result);
            $result['metadata'] = $metadata;
            
            // 4. Compactar tudo
            $archive = $this->compressBackup($backupPath, $backupName);
            if ($archive['success']) {
                $result['archive'] = $archive; // Manter o array completo com 'file' e 'size'
                
                // Remover diretório temporário
                $this->removeDirectory($backupPath);
                
                // 5. Upload para Google Drive (se solicitado)
                if ($uploadToDrive && $result['success']) {
                    $uploadResult = $this->uploadToGoogleDrive($result['archive']['file'], $backupName);
                    if ($uploadResult['success']) {
                        $result['drive_file_id'] = $uploadResult['file_id'];
                        $result['drive_url'] = $uploadResult['url'];
                    } else {
                        $result['errors'][] = 'Falha no upload para Google Drive: ' . $uploadResult['error'];
                        // Não marcar como falha se apenas o upload falhar
                    }
                }
            } else {
                $result['errors'][] = $archive['error'];
                $result['success'] = false;
            }
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['errors'][] = 'Erro ao criar backup: ' . $e->getMessage();
            error_log("Erro no backup: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Faz backup do banco de dados MySQL
     */
    private function backupDatabase($backupPath, $backupName)
    {
        try {
            $dbConfig = $this->config['database'];
            $dbFile = $backupPath . '/' . $backupName . '_database.sql';
            
            // Detectar sistema operacional
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            
            // Tentar usar mysqldump primeiro (mais eficiente)
            $mysqldumpPath = $this->findMysqldumpPath();
            
            if ($mysqldumpPath) {
                // Usar mysqldump
                if ($isWindows) {
                    // Windows: usar redirecionamento diferente
                    $command = sprintf(
                        '%s -h %s -P %s -u %s -p%s %s',
                        escapeshellarg($mysqldumpPath),
                        escapeshellarg($dbConfig['host']),
                        escapeshellarg($dbConfig['port']),
                        escapeshellarg($dbConfig['user']),
                        escapeshellarg($dbConfig['pass']),
                        escapeshellarg($dbConfig['name'])
                    );
                    
                    // No Windows, usar popen ou file_put_contents com exec
                    $handle = popen($command . ' 2>&1', 'r');
                    if ($handle) {
                        $output = '';
                        while (!feof($handle)) {
                            $output .= fread($handle, 8192);
                        }
                        pclose($handle);
                        file_put_contents($dbFile, $output);
                    } else {
                        throw new Exception('Falha ao executar mysqldump');
                    }
                } else {
                    // Linux/Unix: redirecionamento normal
                    $command = sprintf(
                        '%s -h %s -P %s -u %s -p%s %s > %s 2>&1',
                        escapeshellarg($mysqldumpPath),
                        escapeshellarg($dbConfig['host']),
                        escapeshellarg($dbConfig['port']),
                        escapeshellarg($dbConfig['user']),
                        escapeshellarg($dbConfig['pass']),
                        escapeshellarg($dbConfig['name']),
                        escapeshellarg($dbFile)
                    );
                    
                    exec($command, $output, $returnCode);
                    
                    if ($returnCode !== 0) {
                        throw new Exception('mysqldump retornou código: ' . $returnCode . ' - ' . implode("\n", $output));
                    }
                }
            } else {
                // Fallback: usar PDO para fazer dump manual
                $this->backupDatabaseWithPDO($dbFile, $dbConfig);
            }
            
            // Verificar se o arquivo foi criado
            if (!file_exists($dbFile)) {
                return [
                    'success' => false,
                    'error' => 'Arquivo de backup do banco de dados não foi criado'
                ];
            }
            
            // Verificar se o arquivo não está vazio
            if (filesize($dbFile) === 0) {
                return [
                    'success' => false,
                    'error' => 'Arquivo de backup do banco de dados está vazio'
                ];
            }
            
            return [
                'success' => true,
                'file' => $dbFile,
                'size' => filesize($dbFile)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao fazer backup do banco: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Encontra o caminho do mysqldump
     */
    private function findMysqldumpPath()
    {
        // Tentar caminhos comuns
        $paths = [
            'mysqldump', // No PATH
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\wamp\\bin\\mysql\\mysql5.7.23\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/opt/mysql/bin/mysqldump'
        ];
        
        foreach ($paths as $path) {
            if (is_executable($path) || (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && file_exists($path))) {
                return $path;
            }
        }
        
        // Tentar encontrar no PATH
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $command = $isWindows ? 'where mysqldump' : 'which mysqldump';
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }
        
        return null;
    }
    
    /**
     * Faz backup do banco usando PDO (fallback quando mysqldump não está disponível)
     */
    private function backupDatabaseWithPDO($dbFile, $dbConfig)
    {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4",
            $dbConfig['user'],
            $dbConfig['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $output = fopen($dbFile, 'w');
        
        // Cabeçalho do dump
        fwrite($output, "-- EducaTudo Database Backup\n");
        fwrite($output, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($output, "SET FOREIGN_KEY_CHECKS=0;\n\n");
        
        // Obter todas as tabelas
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // Estrutura da tabela
            fwrite($output, "-- Table structure for `{$table}`\n");
            $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            fwrite($output, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($output, $createTable['Create Table'] . ";\n\n");
            
            // Dados da tabela
            fwrite($output, "-- Dumping data for table `{$table}`\n");
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $pdo->quote($value);
                        }
                    }
                    fwrite($output, "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n");
                }
            }
            
            fwrite($output, "\n");
        }
        
        fwrite($output, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($output);
    }
    
    /**
     * Faz backup dos arquivos de upload
     */
    private function backupUploadFiles($backupPath, $backupName)
    {
        try {
            $uploadsDir = $this->config['upload']['path'];
            $backupFilesDir = $backupPath . '/uploads';
            
            if (!is_dir($uploadsDir)) {
                return [
                    'success' => false,
                    'error' => 'Diretório de uploads não encontrado: ' . $uploadsDir
                ];
            }
            
            // Copiar diretório de uploads
            $this->copyDirectory($uploadsDir, $backupFilesDir);
            
            // Criar arquivo compactado dos uploads
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $hasZipExtension = extension_loaded('zip');
            
            // Decidir formato baseado no sistema e extensões disponíveis
            if ($isWindows && $hasZipExtension) {
                $uploadsArchive = $backupPath . '/' . $backupName . '_uploads.zip';
                // Windows: usar ZipArchive (nativo do PHP)
                try {
                    $zip = new ZipArchive();
                    if ($zip->open($uploadsArchive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                        throw new Exception('Falha ao abrir arquivo ZIP');
                    }
                    
                    $this->addDirectoryToZip($backupFilesDir, $zip, '');
                    $zip->close();
                } catch (Exception $e) {
                    // Se ZipArchive falhar, tentar método alternativo
                    $this->createZipManually($backupFilesDir, $uploadsArchive);
                }
            } else {
                // Usar tar.gz (funciona em ambos os sistemas se tar estiver disponível)
                $uploadsArchive = $backupPath . '/' . $backupName . '_uploads.tar.gz';
                $command = sprintf(
                    'tar -czf %s -C %s . 2>&1',
                    escapeshellarg($uploadsArchive),
                    escapeshellarg($backupFilesDir)
                );
                
                exec($command, $output, $returnCode);
                
                if ($returnCode !== 0 || !file_exists($uploadsArchive)) {
                    // Se tar falhar, criar ZIP manualmente sem ZipArchive
                    if ($isWindows) {
                        $uploadsArchive = $backupPath . '/' . $backupName . '_uploads.zip';
                        $this->createZipManually($backupFilesDir, $uploadsArchive);
                    } else {
                        return [
                            'success' => false,
                            'error' => 'Falha ao compactar uploads: ' . implode("\n", $output) . '. Instale tar ou habilite extensão zip do PHP.'
                        ];
                    }
                }
            }
            
            if (!file_exists($uploadsArchive)) {
                return [
                    'success' => false,
                    'error' => 'Arquivo compactado não foi criado'
                ];
            }
            
            // Remover diretório temporário
            $this->removeDirectory($backupFilesDir);
            
            return [
                'success' => true,
                'file' => $uploadsArchive,
                'size' => filesize($uploadsArchive)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao fazer backup dos arquivos: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cria arquivo de metadados do backup
     */
    private function createMetadata($backupPath, $backupName, $backupInfo)
    {
        $metadata = [
            'backup_name' => $backupName,
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => $this->config['app']['version'],
            'environment' => $this->config['app']['environment'],
            'database' => [
                'host' => $this->config['database']['host'],
                'name' => $this->config['database']['name']
            ],
            'files' => [],
            'total_size' => 0
        ];
        
        foreach ($backupInfo['files'] as $file) {
            if (file_exists($file)) {
                $metadata['files'][] = [
                    'path' => basename($file),
                    'size' => filesize($file)
                ];
                $metadata['total_size'] += filesize($file);
            }
        }
        
        $metadataFile = $backupPath . '/metadata.json';
        file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $metadata;
    }
    
    /**
     * Compacta o backup em um único arquivo
     */
    private function compressBackup($backupPath, $backupName)
    {
        try {
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $hasZipExtension = extension_loaded('zip');
            
            // Decidir formato baseado no sistema e extensões disponíveis
            if ($isWindows && $hasZipExtension) {
                $archiveFile = $this->backupDir . '/' . $backupName . '.zip';
                // Windows: usar ZipArchive
                $zip = new ZipArchive();
                if ($zip->open($archiveFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                    return [
                        'success' => false,
                        'error' => 'Falha ao criar arquivo ZIP'
                    ];
                }
                
                $this->addDirectoryToZip($backupPath, $zip, '');
                $zip->close();
            } else {
                // Usar tar.gz (funciona em ambos os sistemas se tar estiver disponível)
                $archiveFile = $this->backupDir . '/' . $backupName . '.tar.gz';
                $command = sprintf(
                    'tar -czf %s -C %s . 2>&1',
                    escapeshellarg($archiveFile),
                    escapeshellarg($backupPath)
                );
                
                exec($command, $output, $returnCode);
                
                if ($returnCode !== 0 || !file_exists($archiveFile)) {
                    // Se tar falhar e estiver no Windows, tentar PowerShell
                    if ($isWindows) {
                        $archiveFile = $this->backupDir . '/' . $backupName . '.zip';
                        try {
                            $this->createZipManually($backupPath, $archiveFile);
                        } catch (Exception $e) {
                            return [
                                'success' => false,
                                'error' => 'Falha ao compactar backup: ' . $e->getMessage() . '. Habilite a extensão zip do PHP (php_zip.dll) ou instale tar/7-zip.'
                            ];
                        }
                    } else {
                        return [
                            'success' => false,
                            'error' => 'Falha ao compactar backup: ' . implode("\n", $output) . '. Instale tar ou habilite extensão zip do PHP.'
                        ];
                    }
                }
            }
            
            if (!file_exists($archiveFile)) {
                return [
                    'success' => false,
                    'error' => 'Arquivo compactado não foi criado'
                ];
            }
            
            return [
                'success' => true,
                'file' => $archiveFile,
                'size' => filesize($archiveFile)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao compactar backup: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Adiciona diretório recursivamente a um ZIP
     */
    private function addDirectoryToZip($dir, $zip, $zipPath)
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            $zipFilePath = $zipPath . ($zipPath ? '/' : '') . $file;
            
            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipFilePath);
                $this->addDirectoryToZip($filePath, $zip, $zipFilePath);
            } else {
                $zip->addFile($filePath, $zipFilePath);
            }
        }
    }
    
    /**
     * Faz upload do backup para Google Drive
     */
    private function uploadToGoogleDrive($filePath, $backupName)
    {
        try {
            // Carregar autoloader do Composer
            $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
            if (!file_exists($autoloadPath)) {
                return [
                    'success' => false,
                    'error' => 'Autoloader do Composer não encontrado. Execute "composer install" primeiro.'
                ];
            }
            
            require_once $autoloadPath;
            
            // Verificar se as classes estão disponíveis após carregar autoloader
            // Aguardar um pouco para o autoloader processar
            if (!class_exists('Google_Client')) {
                // Tentar carregar manualmente se necessário
                $googleClientPath = __DIR__ . '/../../vendor/google/apiclient/src/Google/Client.php';
                if (file_exists($googleClientPath)) {
                    require_once $googleClientPath;
                }
                
                if (!class_exists('Google_Client')) {
                    return [
                        'success' => false,
                        'error' => 'Biblioteca Google API Client não instalada. Execute "composer require google/apiclient" e depois "composer install"'
                    ];
                }
            }
            
            $credentialsPath = $this->projectRoot . '/storage/backup-credentials.json';
            
            if (!file_exists($credentialsPath)) {
                return [
                    'success' => false,
                    'error' => 'Arquivo de credenciais do Google Drive não encontrado. Configure em: ' . $credentialsPath
                ];
            }
            
            $client = new \Google_Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(\Google_Service_Drive::DRIVE_FILE);
            $client->setAccessType('offline');
            
            // Configurar acesso ao Google Drive
            // Service Accounts não têm quota própria, precisam usar Shared Drive ou OAuth Delegation
            $credentialsData = json_decode(file_get_contents($credentialsPath), true);
            
            // Opção 1: Shared Drive (Recomendado)
            $sharedDriveId = $credentialsData['shared_drive_id'] ?? null;
            
            // Opção 2: OAuth Delegation (Domain-wide delegation)
            $delegatedUser = $credentialsData['delegated_user'] ?? null;
            
            if ($sharedDriveId) {
                // Usar Shared Drive - não precisa de setSubject
                // O supportsAllDrives será usado nas operações
            } elseif ($delegatedUser) {
                // Usar OAuth Delegation
                $client->setSubject($delegatedUser);
            } else {
                // Nenhuma opção configurada
                return [
                    'success' => false,
                    'error' => 'Service Accounts não têm quota de armazenamento. Configure "shared_drive_id" (recomendado) ou "delegated_user" no arquivo de credenciais. Veja docs/BACKUP_SETUP.md para instruções.'
                ];
            }
            
            // Configurar verificação SSL (para Windows/desenvolvimento)
            $httpClient = new \GuzzleHttp\Client([
                'verify' => $this->getCaBundlePath()
            ]);
            $client->setHttpClient($httpClient);
            
            $service = new \Google_Service_Drive($client);
            
            // Obter ID da pasta de backups
            $folderId = $this->getBackupFolderId($service, $sharedDriveId);
            
            // Criar arquivo no Google Drive
            $fileMetadata = new \Google_Service_Drive_DriveFile([
                'name' => $backupName . (strpos($filePath, '.zip') !== false ? '.zip' : '.tar.gz'),
                'parents' => [$folderId]
            ]);
            
            // Opções de upload com suporte a Shared Drive
            $uploadOptions = [
                'data' => file_get_contents($filePath),
                'mimeType' => strpos($filePath, '.zip') !== false ? 'application/zip' : 'application/gzip',
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink'
            ];
            
            // Se usar Shared Drive, adicionar supportsAllDrives
            if ($sharedDriveId) {
                $uploadOptions['supportsAllDrives'] = true;
            }
            
            $file = $service->files->create($fileMetadata, $uploadOptions);
            
            $content = file_get_contents($filePath);
            $file = $service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/gzip',
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink'
            ]);
            
            return [
                'success' => true,
                'file_id' => $file->getId(),
                'url' => $file->getWebViewLink()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao fazer upload para Google Drive: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtém ou cria a pasta de backups no Google Drive
     */
    private function getBackupFolderId($service, $sharedDriveId = null)
    {
        $folderName = 'EducaTudo_Backups';
        
        // Construir query de busca
        $query = "name='{$folderName}' and mimeType='application/vnd.google-apps.folder' and trashed=false";
        
        // Se usar Shared Drive, buscar dentro dele
        if ($sharedDriveId) {
            $query .= " and '{$sharedDriveId}' in parents";
        }
        
        $listOptions = [
            'q' => $query,
            'fields' => 'files(id, name)'
        ];
        
        // Se usar Shared Drive, adicionar supportsAllDrives
        if ($sharedDriveId) {
            $listOptions['supportsAllDrives'] = true;
            $listOptions['includeItemsFromAllDrives'] = true;
            $listOptions['corpora'] = 'drive';
            $listOptions['driveId'] = $sharedDriveId;
        }
        
        // Buscar pasta existente
        $response = $service->files->listFiles($listOptions);
        
        if (count($response->getFiles()) > 0) {
            return $response->getFiles()[0]->getId();
        }
        
        // Criar nova pasta
        $fileMetadata = new \Google_Service_Drive_DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ]);
        
        // Se usar Shared Drive, definir como pai
        if ($sharedDriveId) {
            $fileMetadata->setParents([$sharedDriveId]);
        }
        
        $createOptions = ['fields' => 'id'];
        if ($sharedDriveId) {
            $createOptions['supportsAllDrives'] = true;
        }
        
        $folder = $service->files->create($fileMetadata, $createOptions);
        return $folder->getId();
    }
    
    /**
     * Lista backups disponíveis
     */
    public function listBackups($includeDrive = false)
    {
        $backups = [];
        
        // Backups locais
        $files = glob($this->backupDir . '/*.tar.gz');
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                'location' => 'local'
            ];
        }
        
        // Backups no Google Drive (se solicitado)
        if ($includeDrive) {
            try {
                $driveBackups = $this->listDriveBackups();
                $backups = array_merge($backups, $driveBackups);
            } catch (Exception $e) {
                error_log("Erro ao listar backups do Drive: " . $e->getMessage());
            }
        }
        
        // Ordenar por data (mais recente primeiro)
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $backups;
    }
    
    /**
     * Lista backups no Google Drive
     */
    private function listDriveBackups()
    {
        $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            return [];
        }
        
        require_once $autoloadPath;
        
        if (!class_exists('Google_Client')) {
            return [];
        }
        
        $credentialsPath = __DIR__ . '/../../storage/backup-credentials.json';
        if (!file_exists($credentialsPath)) {
            return [];
        }
        
        $client = new \Google_Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope(\Google_Service_Drive::DRIVE_FILE);
        
        // Verificar se usa Shared Drive ou OAuth Delegation
        $credentialsData = json_decode(file_get_contents($credentialsPath), true);
        $sharedDriveId = $credentialsData['shared_drive_id'] ?? null;
        
        if (isset($credentialsData['delegated_user']) && !empty($credentialsData['delegated_user'])) {
            $client->setSubject($credentialsData['delegated_user']);
        }
        
        // Configurar verificação SSL
        $httpClient = new \GuzzleHttp\Client([
            'verify' => $this->getCaBundlePath()
        ]);
        $client->setHttpClient($httpClient);
        
        $service = new \Google_Service_Drive($client);
        $folderId = $this->getBackupFolderId($service, $sharedDriveId);
        
        // Construir query (buscar tanto .tar.gz quanto .zip)
        $query = "('{$folderId}' in parents) and (mimeType='application/gzip' or mimeType='application/zip') and trashed=false";
        
        $listOptions = [
            'q' => $query,
            'fields' => 'files(id, name, createdTime, size)',
            'orderBy' => 'createdTime desc'
        ];
        
        // Se usar Shared Drive, adicionar suporte
        if ($sharedDriveId) {
            $listOptions['supportsAllDrives'] = true;
            $listOptions['includeItemsFromAllDrives'] = true;
            $listOptions['corpora'] = 'drive';
            $listOptions['driveId'] = $sharedDriveId;
        }
        
        $response = $service->files->listFiles($listOptions);
        
        $backups = [];
        foreach ($response->getFiles() as $file) {
            $backups[] = [
                'name' => $file->getName(),
                'file_id' => $file->getId(),
                'size' => $file->getSize(),
                'created_at' => $file->getCreatedTime(),
                'location' => 'drive'
            ];
        }
        
        return $backups;
    }
    
    /**
     * Remove backup local antigo
     */
    public function deleteLocalBackup($backupName)
    {
        $filePath = $this->backupDir . '/' . $backupName;
        if (file_exists($filePath)) {
            unlink($filePath);
            return true;
        }
        return false;
    }
    
    /**
     * Remove backup do Google Drive
     */
    public function deleteDriveBackup($fileId)
    {
        try {
            if (!class_exists('Google_Client')) {
                require_once __DIR__ . '/../../vendor/autoload.php';
            }
            
            $credentialsPath = $this->projectRoot . '/storage/backup-credentials.json';
            if (!file_exists($credentialsPath)) {
                return false;
            }
            
            $client = new \Google_Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(\Google_Service_Drive::DRIVE_FILE);
            
            // Configurar verificação SSL
            $httpClient = new \GuzzleHttp\Client([
                'verify' => $this->getCaBundlePath()
            ]);
            $client->setHttpClient($httpClient);
            
            $service = new \Google_Service_Drive($client);
            $service->files->delete($fileId);
            
            return true;
        } catch (Exception $e) {
            error_log("Erro ao deletar backup do Drive: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Copia diretório recursivamente
     */
    private function copyDirectory($source, $destination)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item, $destPath);
            }
        }
    }
    
    /**
     * Remove diretório recursivamente
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    /**
     * Remove diretório (método público para uso externo)
     */
    public function removeDirectoryPublic($dir)
    {
        $this->removeDirectory($dir);
    }
    
    /**
     * Obtém o caminho do bundle de certificados CA ou false para desabilitar verificação
     */
    private function getCaBundlePath()
    {
        // Tentar encontrar certificados CA em locais comuns
        $caPaths = [
            __DIR__ . '/../../vendor/guzzlehttp/guzzle/src/cacert.pem',
            __DIR__ . '/../../cacert.pem',
            'C:/php/extras/ssl/cacert.pem',
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile')
        ];
        
        foreach ($caPaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        
        // Se estiver em desenvolvimento, pode desabilitar verificação (NÃO recomendado para produção)
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            return false; // Desabilita verificação SSL apenas em desenvolvimento
        }
        
        // Para produção, tentar usar o bundle do sistema ou retornar true (usar padrão)
        return true;
    }
    
    /**
     * Baixa backup do Google Drive
     */
    public function downloadFromDrive($fileId)
    {
        try {
            $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
            if (!file_exists($autoloadPath)) {
                return false;
            }
            
            require_once $autoloadPath;
            
            if (!class_exists('Google_Client')) {
                return false;
            }
            
            $credentialsPath = $this->projectRoot . '/storage/backup-credentials.json';
            if (!file_exists($credentialsPath)) {
                return false;
            }
            
            $client = new \Google_Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(\Google_Service_Drive::DRIVE_FILE);
            
            // Verificar se usa Shared Drive ou OAuth Delegation
            $credentialsData = json_decode(file_get_contents($credentialsPath), true);
            $sharedDriveId = $credentialsData['shared_drive_id'] ?? null;
            
            if (isset($credentialsData['delegated_user']) && !empty($credentialsData['delegated_user'])) {
                $client->setSubject($credentialsData['delegated_user']);
            }
            
            // Configurar verificação SSL
            $httpClient = new \GuzzleHttp\Client([
                'verify' => $this->getCaBundlePath()
            ]);
            $client->setHttpClient($httpClient);
            
            $service = new \Google_Service_Drive($client);
            
            // Opções para get
            $getOptions = ['fields' => 'name, size'];
            if ($sharedDriveId) {
                $getOptions['supportsAllDrives'] = true;
            }
            
            // Obter informações do arquivo
            $file = $service->files->get($fileId, $getOptions);
            
            // Opções para download
            $downloadOptions = ['alt' => 'media'];
            if ($sharedDriveId) {
                $downloadOptions['supportsAllDrives'] = true;
            }
            
            // Baixar arquivo
            $content = $service->files->get($fileId, $downloadOptions);
            
            $localFile = $this->backupDir . '/' . $file->getName();
            file_put_contents($localFile, $content->getBody()->getContents());
            
            return $localFile;
            
        } catch (Exception $e) {
            error_log("Erro ao baixar backup do Drive: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Copia diretório (método público para uso externo)
     */
    public function copyDirectoryPublic($source, $destination)
    {
        $this->copyDirectory($source, $destination);
    }
}

