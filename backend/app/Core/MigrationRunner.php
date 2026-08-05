<?php

namespace App\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Classe para executar migrations do banco de dados em ordem
 */
class MigrationRunner
{
    private $db;
    private $migrationsDir;
    private $targetDb = null; // null = banco principal, ou array com credenciais de outra escola
    
    public function __construct($targetDb = null)
    {
        $this->db = Database::getInstance();
        // Caminho correto: de app/Core para database/migrations (2 níveis acima)
        $this->migrationsDir = dirname(dirname(__DIR__)) . '/database/migrations';
        $this->targetDb = $targetDb;
    }
    
    /**
     * Executa todas as migrations pendentes em ordem
     */
    public function run($dryRun = false)
    {
        $migrations = $this->getMigrations();
        $executadas = $this->getExecutedMigrations();
        $pendentes = array_filter($migrations, function($migration) use ($executadas) {
            return !in_array($migration['file'], $executadas);
        });
        
        if (empty($pendentes)) {
            return [
                'success' => true,
                'message' => 'Nenhuma migration pendente',
                'executed' => 0,
                'total' => count($migrations)
            ];
        }
        
        $results = [];
        $pdo = $this->getConnection();
        
        foreach ($pendentes as $migration) {
            try {
                if (!$dryRun) {
                    $this->executeMigration($pdo, $migration);
                    $this->recordMigration($migration['file'], 'sucesso');
                }
                
                $results[] = [
                    'file' => $migration['file'],
                    'status' => 'sucesso',
                    'message' => $dryRun ? 'Seria executada' : 'Executada com sucesso'
                ];
            } catch (Exception $e) {
                $this->recordMigration($migration['file'], 'erro', $e->getMessage());
                $results[] = [
                    'file' => $migration['file'],
                    'status' => 'erro',
                    'message' => $e->getMessage()
                ];
                
                // Em caso de erro, parar execução
                break;
            }
        }
        
        return [
            'success' => true,
            'executed' => count($results),
            'total' => count($migrations),
            'results' => $results
        ];
    }
    
    /**
     * Obtém lista de migrations ordenadas por nome (data)
     */
    private function getMigrations()
    {
        $migrations = [];
        
        if (!is_dir($this->migrationsDir)) {
            return $migrations;
        }
        
        $files = scandir($this->migrationsDir);
        foreach ($files as $file) {
            if (stripos($file, '_rollback') !== false) {
                continue; // rollback scripts não são aplicados como migration
            }
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $migrations[] = [
                    'file' => $file,
                    'path' => $this->migrationsDir . '/' . $file,
                    'size' => filesize($this->migrationsDir . '/' . $file),
                    'modified' => filemtime($this->migrationsDir . '/' . $file)
                ];
            }
        }
        
        // Ordenar por nome (que contém data)
        usort($migrations, function($a, $b) {
            return strcmp($a['file'], $b['file']);
        });
        
        return $migrations;
    }
    
    /**
     * Obtém lista de migrations já executadas
     */
    private function getExecutedMigrations()
    {
        try {
            // Se for banco de outra escola, usar tabela de migrations_executadas
            if ($this->targetDb && isset($this->targetDb['id'])) {
                $executadas = $this->db->fetchAll(
                    "SELECT migration_file FROM migrations_executadas 
                     WHERE escola_database_config_id = :escola_id AND status = 'sucesso'",
                    ['escola_id' => $this->targetDb['id']]
                );
                return array_column($executadas, 'migration_file');
            }
            
            // Para banco principal, usar tabela migrations_log
            $executadas = $this->db->fetchAll(
                "SELECT migration_file FROM migrations_log WHERE status = 'sucesso'"
            );
            return array_column($executadas, 'migration_file');
        } catch (Exception $e) {
            // Se tabela não existir, retornar array vazio
            return [];
        }
    }
    
    /**
     * Executa uma migration específica
     */
    public function executeMigration($pdo, $migration)
    {
        $sql = file_get_contents($migration['path']);
        if (empty($sql)) {
            throw new Exception('Arquivo de migration vazio');
        }
        
        // Dividir por ; e executar cada query
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        
        $pdo->beginTransaction();
        try {
            foreach ($queries as $query) {
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new Exception("Erro ao executar migration {$migration['file']}: " . $e->getMessage());
        }
    }
    
    /**
     * Registra migration como executada
     */
    private function recordMigration($migrationFile, $status, $errorMessage = null)
    {
        try {
            // Se for banco de outra escola
            if ($this->targetDb && isset($this->targetDb['id'])) {
                $existing = $this->db->fetch(
                    "SELECT id FROM migrations_executadas 
                     WHERE escola_database_config_id = :escola_id AND migration_file = :file",
                    ['escola_id' => $this->targetDb['id'], 'file' => $migrationFile]
                );
                
                if ($existing) {
                    $this->db->update(
                        "UPDATE migrations_executadas 
                         SET status = :status, mensagem_erro = :error, executada_em = NOW()
                         WHERE id = :id",
                        [
                            'status' => $status,
                            'error' => $errorMessage,
                            'id' => $existing['id']
                        ]
                    );
                } else {
                    $this->db->insert(
                        "INSERT INTO migrations_executadas 
                         (escola_database_config_id, migration_file, status, mensagem_erro) 
                         VALUES (:escola_id, :file, :status, :error)",
                        [
                            'escola_id' => $this->targetDb['id'],
                            'file' => $migrationFile,
                            'status' => $status,
                            'error' => $errorMessage
                        ]
                    );
                }
            } else {
                // Para banco principal
                $existing = $this->db->fetch(
                    "SELECT id FROM migrations_log WHERE migration_file = :file",
                    ['file' => $migrationFile]
                );
                
                if ($existing) {
                    $this->db->update(
                        "UPDATE migrations_log 
                         SET status = :status, mensagem_erro = :error, executada_em = NOW()
                         WHERE id = :id",
                        [
                            'status' => $status,
                            'error' => $errorMessage,
                            'id' => $existing['id']
                        ]
                    );
                } else {
                    $this->db->insert(
                        "INSERT INTO migrations_log (migration_file, status, mensagem_erro) 
                         VALUES (:file, :status, :error)",
                        [
                            'file' => $migrationFile,
                            'status' => $status,
                            'error' => $errorMessage
                        ]
                    );
                }
            }
        } catch (Exception $e) {
            // Log erro mas não interromper execução
            error_log("Erro ao registrar migration: " . $e->getMessage());
        }
    }
    
    /**
     * Obtém conexão PDO (banco principal ou escola específica)
     */
    public function getConnection()
    {
        if ($this->targetDb && isset($this->targetDb['db_host'])) {
            // Conexão com banco de outra escola
            $dsn = "mysql:host={$this->targetDb['db_host']};port={$this->targetDb['db_port']};dbname={$this->targetDb['db_name']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ];
            
            $pdo = new PDO($dsn, $this->targetDb['db_user'], $this->targetDb['db_pass'], $options);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            return $pdo;
        }
        
        // Conexão com banco principal
        return $this->db->getConnection();
    }
    
    /**
     * Cria tabela de controle de migrations no banco principal (se não existir)
     */
    public static function createMigrationsTable()
    {
        $db = Database::getInstance();
        
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS migrations_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration_file VARCHAR(255) NOT NULL UNIQUE,
                    executada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('sucesso', 'erro') DEFAULT 'sucesso',
                    mensagem_erro TEXT,
                    INDEX idx_migration_file (migration_file),
                    INDEX idx_executada_em (executada_em)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            return true;
        } catch (Exception $e) {
            error_log("Erro ao criar tabela migrations_log: " . $e->getMessage());
            return false;
        }
    }
}

