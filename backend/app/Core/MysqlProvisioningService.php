<?php
/**
 * EducaTudo - Criação de banco e usuário MySQL a partir do painel Master.
 * Usa credenciais de administrador (DB_ADMIN_* no .env) para executar CREATE DATABASE, CREATE USER e GRANT.
 * Opcional: só funciona se DB_ADMIN_USER e DB_ADMIN_PASS estiverem definidos.
 */

class MysqlProvisioningService
{
    /**
     * Cria o banco de dados e o usuário MySQL e concede privilégios.
     *
     * @param string $adminHost Host do MySQL (ex.: localhost)
     * @param int $adminPort Porta (ex.: 3306)
     * @param string $adminUser Usuário com CREATE DATABASE, CREATE USER, GRANT
     * @param string $adminPass Senha do admin
     * @param string $dbName Nome do banco a criar
     * @param string $dbUser Usuário a criar (acesso ao banco)
     * @param string $dbPass Senha do usuário
     * @throws Exception Se conexão ou DDL falhar
     */
    public static function createDatabaseAndUser(
        string $adminHost,
        int $adminPort,
        string $adminUser,
        string $adminPass,
        string $dbName,
        string $dbUser,
        string $dbPass
    ): void {
        self::validateIdentifier($dbName, 'nome do banco');
        self::validateIdentifier($dbUser, 'usuário');

        $dsn = "mysql:host=" . self::dsnEscape($adminHost) . ";port=" . $adminPort . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, $adminUser, $adminPass, $options);

        $dbNameQuoted = '`' . str_replace('`', '``', $dbName) . '`';
        $userQuoted = "'" . str_replace("'", "''", $dbUser) . "'@'%'";
        $passQuoted = $pdo->quote($dbPass);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbNameQuoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // CREATE USER IF NOT EXISTS (MySQL 5.7.6+)
        try {
            $pdo->exec("CREATE USER IF NOT EXISTS {$userQuoted} IDENTIFIED BY {$passQuoted}");
            $pdo->exec("GRANT ALL PRIVILEGES ON {$dbNameQuoted}.* TO {$userQuoted}");
        } catch (PDOException $e) {
            // Fallback: MySQL 5.7 ou sintaxe antiga (GRANT ... IDENTIFIED BY cria o usuário)
            if (strpos($e->getMessage(), 'IDENTIFIED') !== false || strpos($e->getMessage(), 'syntax') !== false || strpos($e->getMessage(), 'IF NOT EXISTS') !== false) {
                $pdo->exec("GRANT ALL PRIVILEGES ON {$dbNameQuoted}.* TO {$userQuoted} IDENTIFIED BY {$passQuoted}");
            } else {
                throw $e;
            }
        }
        $pdo->exec("FLUSH PRIVILEGES");
    }

    /**
     * Permite apenas caracteres seguros para identificadores MySQL (banco e usuário).
     */
    private static function validateIdentifier(string $value, string $label): void
    {
        if ($value === '') {
            throw new Exception("O {$label} não pode ser vazio.");
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            throw new Exception("O {$label} deve conter apenas letras, números e underscore.");
        }
        if (strlen($value) > 64) {
            throw new Exception("O {$label} é longo demais.");
        }
    }

    private static function isSchemaConflict(PDOException $e): bool
    {
        $msg = $e->getMessage();
        if (preg_match('/SQLSTATE\[(42S01|42S21|1060|1050)\]/', $msg)) {
            return true;
        }
        return stripos($msg, 'Duplicate column') !== false
            || stripos($msg, 'already exists') !== false
            || stripos($msg, 'Duplicate key name') !== false;
    }

    private static function dsnEscape(string $host): string
    {
        return str_replace([':', ';', ' '], '', $host);
    }

    /**
     * Indica se a criação automática está disponível (DB_ADMIN_USER e DB_ADMIN_PASS definidos).
     */
    public static function isAvailable(): bool
    {
        $user = function_exists('env') ? env('DB_ADMIN_USER', '') : '';
        $pass = function_exists('env') ? env('DB_ADMIN_PASS', '') : '';
        return $user !== '' && $pass !== '';
    }

    /**
     * Retorna host e porta do admin (usa DB_ADMIN_HOST/DB_ADMIN_PORT ou fallback para DB_HOST/DB_PORT).
     */
    public static function getAdminConnectionParams(): array
    {
        $host = function_exists('env') ? env('DB_ADMIN_HOST', env('DB_HOST', 'localhost')) : 'localhost';
        $port = (int) (function_exists('env') ? env('DB_ADMIN_PORT', env('DB_PORT', '3306')) : 3306);
        return ['host' => $host, 'port' => $port];
    }

    /**
     * Executa as migrations do tenant no banco novo (exclui multi_tenant_master.sql).
     * Registra em migrations_escolas no banco master.
     *
     * @param PDO $masterPdo Conexão com o banco master
     * @param PDO $tenantPdo Conexão com o banco da escola (já selecionado)
     * @param int $escolaId ID da escola em escolas.id
     * @param bool $ignorarConflitosSchema true = trata coluna/tabela já existente como migration aplicada (bootstrap local com educa_core)
     * @throws Exception
     */
    public static function runTenantMigrations(
        PDO $masterPdo,
        PDO $tenantPdo,
        int $escolaId,
        bool $ignorarConflitosSchema = false
    ): void
    {
        $migrationsDir = dirname(dirname(__DIR__)) . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            return;
        }
        $files = scandir($migrationsDir);
        $list = [];
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
                continue;
            }
            if (stripos($file, '_rollback') !== false) {
                continue; // arquivos de rollback nunca são aplicados como migration
            }
            if (stripos($file, 'master') !== false) {
                continue;
            }
            $list[] = ['file' => $file, 'path' => $migrationsDir . '/' . $file];
        }
        usort($list, function ($a, $b) {
            return strcmp($a['file'], $b['file']);
        });

        $executadas = [];
        try {
            $st = $masterPdo->prepare("SELECT migration_name FROM migrations_escolas WHERE escola_id = ?");
            $st->execute([$escolaId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $executadas[$row['migration_name']] = true;
            }
        } catch (PDOException $e) {
            // Tabela pode não existir
        }
        $stmt = $masterPdo->prepare(
            "INSERT IGNORE INTO migrations_escolas (escola_id, migration_name) VALUES (?, ?)"
        );
        foreach ($list as $m) {
            if (isset($executadas[$m['file']])) {
                continue;
            }
            $sql = @file_get_contents($m['path']);
            if ($sql === false || trim($sql) === '') {
                continue;
            }
            if ($ignorarConflitosSchema && stripos($sql, 'DELIMITER') !== false) {
                $stmt->execute([$escolaId, $m['file']]);
                continue;
            }
            $queries = self::splitSqlStatements($sql);
            $hasOwnTransaction = (
                stripos($sql, 'START TRANSACTION') !== false ||
                stripos($sql, 'BEGIN;') !== false
            );
            if (!$hasOwnTransaction) {
                $tenantPdo->beginTransaction();
            }
            try {
                foreach ($queries as $q) {
                    self::executeStatementAndConsumeResult($tenantPdo, $q);
                }
                if (!$hasOwnTransaction && $tenantPdo->inTransaction()) {
                    $tenantPdo->commit();
                }
            } catch (PDOException $e) {
                if (!$hasOwnTransaction && $tenantPdo->inTransaction()) {
                    try { $tenantPdo->rollBack(); } catch (PDOException $ignored) {}
                }
                if ($ignorarConflitosSchema && self::isSchemaConflict($e)) {
                    $stmt->execute([$escolaId, $m['file']]);
                    continue;
                }
                throw new Exception(
                    "Erro ao executar migration {$m['file']} no banco da escola: " . $e->getMessage()
                );
            }
            $stmt->execute([$escolaId, $m['file']]);
        }
    }

    /**
     * Executa apenas as migrations selecionadas no banco da escola.
     * Útil para rodar só uma ou algumas migrations (ex.: novo módulo ainda não habilitado).
     *
     * @param PDO $masterPdo Conexão com o banco master
     * @param PDO $tenantPdo Conexão com o banco da escola
     * @param int $escolaId ID da escola
     * @param string[] $selectedFiles Lista de nomes de arquivo (ex.: ['009_modulos_arquivos_videos.sql'])
     * @throws Exception
     */
    public static function runTenantMigrationsSelected(PDO $masterPdo, PDO $tenantPdo, int $escolaId, array $selectedFiles): void
    {
        if (empty($selectedFiles)) {
            return;
        }
        $migrationsDir = dirname(dirname(__DIR__)) . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            return;
        }
        $allFiles = scandir($migrationsDir);
        $fullList = [];
        foreach ($allFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
                continue;
            }
            if (stripos($file, '_rollback') !== false) {
                continue; // arquivos de rollback nunca são aplicados como migration
            }
            if (stripos($file, 'master') !== false) {
                continue;
            }
            $fullList[] = ['file' => $file, 'path' => $migrationsDir . '/' . $file];
        }
        usort($fullList, function ($a, $b) {
            return strcmp($a['file'], $b['file']);
        });

        $selectedSet = array_flip(array_values($selectedFiles));
        $list = array_filter($fullList, function ($m) use ($selectedSet) {
            return isset($selectedSet[$m['file']]);
        });
        $list = array_values($list);

        $executadas = [];
        try {
            $st = $masterPdo->prepare("SELECT migration_name FROM migrations_escolas WHERE escola_id = ?");
            $st->execute([$escolaId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $executadas[$row['migration_name']] = true;
            }
        } catch (PDOException $e) {
        }

        $stmt = $masterPdo->prepare(
            "INSERT IGNORE INTO migrations_escolas (escola_id, migration_name) VALUES (?, ?)"
        );
        foreach ($list as $m) {
            if (isset($executadas[$m['file']])) {
                continue;
            }
            $sql = @file_get_contents($m['path']);
            if ($sql === false || trim($sql) === '') {
                continue;
            }
            $queries = self::splitSqlStatements($sql);
            $hasOwnTransaction = (
                stripos($sql, 'START TRANSACTION') !== false ||
                stripos($sql, 'BEGIN;') !== false
            );
            if (!$hasOwnTransaction) {
                $tenantPdo->beginTransaction();
            }
            try {
                foreach ($queries as $q) {
                    self::executeStatementAndConsumeResult($tenantPdo, $q);
                }
                if (!$hasOwnTransaction && $tenantPdo->inTransaction()) {
                    $tenantPdo->commit();
                }
            } catch (PDOException $e) {
                if (!$hasOwnTransaction && $tenantPdo->inTransaction()) {
                    try { $tenantPdo->rollBack(); } catch (PDOException $ignored) {}
                }
                throw new Exception(
                    "Erro ao executar migration {$m['file']} no banco da escola: " . $e->getMessage()
                );
            }
            $stmt->execute([$escolaId, $m['file']]);
        }
    }

    /**
     * Executa as migrations do MASTER no próprio banco master (arquivos *_master.sql).
     * Cria a tabela migrations_master se não existir e registra cada migration executada.
     *
     * @param PDO $masterPdo Conexão com o banco master
     * @return string[] Lista de nomes de arquivos executados
     * @throws Exception
     */
    public static function runMasterMigrations(PDO $masterPdo): array
    {
        $masterPdo->exec("
            CREATE TABLE IF NOT EXISTS migrations_master (
                migration_name VARCHAR(255) NOT NULL PRIMARY KEY,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $migrationsDir = dirname(dirname(__DIR__)) . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            return [];
        }
        $allFiles = scandir($migrationsDir);
        $list = [];
        foreach ($allFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
                continue;
            }
            if (stripos($file, '_rollback') !== false) {
                continue; // arquivos de rollback nunca são aplicados como migration
            }
            if (stripos($file, 'master') === false) {
                continue;
            }
            $list[] = ['file' => $file, 'path' => $migrationsDir . '/' . $file];
        }
        usort($list, function ($a, $b) {
            return strcmp($a['file'], $b['file']);
        });

        $executadas = [];
        try {
            $st = $masterPdo->query("SELECT migration_name FROM migrations_master");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $executadas[$row['migration_name']] = true;
            }
        } catch (PDOException $e) {
        }

        $stmt = $masterPdo->prepare(
            "INSERT INTO migrations_master (migration_name) VALUES (?)"
        );
        $executed = [];
        foreach ($list as $m) {
            if (isset($executadas[$m['file']])) {
                continue;
            }
            $sql = @file_get_contents($m['path']);
            if ($sql === false || trim($sql) === '') {
                continue;
            }
            $queries = self::splitSqlStatements($sql);
            $hasOwnTransaction = (
                stripos($sql, 'START TRANSACTION') !== false ||
                stripos($sql, 'BEGIN;') !== false
            );
            if (!$hasOwnTransaction) {
                $masterPdo->beginTransaction();
            }
            try {
                foreach ($queries as $q) {
                    self::executeStatementAndConsumeResult($masterPdo, $q);
                }
                if (!$hasOwnTransaction && $masterPdo->inTransaction()) {
                    $masterPdo->commit();
                }
            } catch (PDOException $e) {
                if (!$hasOwnTransaction && $masterPdo->inTransaction()) {
                    try { $masterPdo->rollBack(); } catch (PDOException $ignored) {}
                }
                throw new Exception(
                    "Erro ao executar migration {$m['file']} no banco master: " . $e->getMessage()
                );
            }
            $stmt->execute([$m['file']]);
            $executed[] = $m['file'];
        }
        return $executed;
    }

    /**
     * Executa apenas as migrations do master selecionadas (mesma lógica, lista restrita).
     *
     * @param PDO $masterPdo Conexão com o banco master
     * @param string[] $selectedFiles Nomes dos arquivos (ex.: ['033_log_validacao_apps_externos_master.sql'])
     * @return string[] Lista de nomes de arquivos executados
     * @throws Exception
     */
    public static function runMasterMigrationsSelected(PDO $masterPdo, array $selectedFiles): array
    {
        if (empty($selectedFiles)) {
            return [];
        }
        $masterPdo->exec("
            CREATE TABLE IF NOT EXISTS migrations_master (
                migration_name VARCHAR(255) NOT NULL PRIMARY KEY,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $migrationsDir = dirname(dirname(__DIR__)) . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            return [];
        }
        $allFiles = scandir($migrationsDir);
        $fullList = [];
        foreach ($allFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') continue;
            if (stripos($file, '_rollback') !== false) continue;
            if (stripos($file, 'master') === false) continue;
            $fullList[] = ['file' => $file, 'path' => $migrationsDir . '/' . $file];
        }
        usort($fullList, function ($a, $b) { return strcmp($a['file'], $b['file']); });

        $selectedSet = array_flip(array_values($selectedFiles));
        $list = array_values(array_filter($fullList, function ($m) use ($selectedSet) {
            return isset($selectedSet[$m['file']]);
        }));

        $executadas = [];
        try {
            $st = $masterPdo->query("SELECT migration_name FROM migrations_master");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $executadas[$row['migration_name']] = true;
            }
        } catch (PDOException $e) {
        }

        $stmt = $masterPdo->prepare("INSERT INTO migrations_master (migration_name) VALUES (?)");
        $executed = [];
        foreach ($list as $m) {
            if (isset($executadas[$m['file']])) continue;
            $sql = @file_get_contents($m['path']);
            if ($sql === false || trim($sql) === '') continue;
            $queries = self::splitSqlStatements($sql);
            $hasOwnTransaction = (
                stripos($sql, 'START TRANSACTION') !== false ||
                stripos($sql, 'BEGIN;') !== false
            );
            if (!$hasOwnTransaction) {
                $masterPdo->beginTransaction();
            }
            try {
                foreach ($queries as $q) {
                    self::executeStatementAndConsumeResult($masterPdo, $q);
                }
                if (!$hasOwnTransaction && $masterPdo->inTransaction()) {
                    $masterPdo->commit();
                }
            } catch (PDOException $e) {
                if (!$hasOwnTransaction && $masterPdo->inTransaction()) {
                    try { $masterPdo->rollBack(); } catch (PDOException $ignored) {}
                }
                throw new Exception(
                    "Erro ao executar migration {$m['file']} no banco master: " . $e->getMessage()
                );
            }
            $stmt->execute([$m['file']]);
            $executed[] = $m['file'];
        }
        return $executed;
    }

    /**
     * Executa um statement SQL no PDO e sempre consome/fecha o statement (evita erro 2014 unbuffered queries).
     * Usa query() para todos os statements e em seguida fetchAll() + closeCursor() (ou nextRowset em loop)
     * para nunca deixar result set ativo na conexão.
     */
    private static function executeStatementAndConsumeResult(PDO $pdo, string $sql): void
    {
        $sql = trim($sql);
        if ($sql === '') {
            return;
        }
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return;
        }
        try {
            do {
                $stmt->fetchAll(PDO::FETCH_ASSOC);
            } while ($stmt->nextRowset());
        } finally {
            $stmt->closeCursor();
        }
    }

    /**
     * Divide SQL em statements respeitando strings, comentarios e conditional comments.
     *
     * @return string[] Lista de statements SQL executaveis
     */
    private static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $len = strlen($sql);
        $i = 0;

        while ($i < $len) {
            $char = $sql[$i];

            // -- single-line comment: skip to end of line
            if ($char === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $i++; // skip \n
                continue;
            }

            // /* block comment */
            if ($char === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $isConditional = ($i + 2 < $len && $sql[$i + 2] === '!');
                if ($isConditional) {
                    // /*!nnnnn ... */ — keep it, MySQL executes conditionally
                    $current .= $char;
                    $i++;
                    while ($i < $len) {
                        $current .= $sql[$i];
                        if ($sql[$i] === '*' && $i + 1 < $len && $sql[$i + 1] === '/') {
                            $current .= $sql[$i + 1];
                            $i += 2;
                            break;
                        }
                        $i++;
                    }
                } else {
                    // Regular comment — skip entirely
                    $i += 2;
                    while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                        $i++;
                    }
                    $i += 2; // skip */
                }
                continue;
            }

            // String literal ('...' or "...")
            if ($char === "'" || $char === '"') {
                $quote = $char;
                $current .= $char;
                $i++;
                while ($i < $len) {
                    $c = $sql[$i];
                    $current .= $c;
                    if ($c === '\\') {
                        // escaped char — add next char too
                        $i++;
                        if ($i < $len) {
                            $current .= $sql[$i];
                        }
                    } elseif ($c === $quote) {
                        // doubled quote escape ('') or end of string
                        if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                            $current .= $sql[$i + 1];
                            $i++;
                        } else {
                            break;
                        }
                    }
                    $i++;
                }
                $i++;
                continue;
            }

            // Statement separator
            if ($char === ';') {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $current = '';
                $i++;
                continue;
            }

            $current .= $char;
            $i++;
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
