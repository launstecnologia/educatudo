<?php
/**
 * EducaTudo - Gerenciador de conexões por tenant (escola)
 * Usado quando MULTI_TENANT=true. Obtém credenciais do banco master (config_escolas_banco)
 * e retorna instância de Database conectada ao banco do tenant.
 */

require_once __DIR__ . '/RedisCache.php';
require_once __DIR__ . '/MasterSecretVault.php';

class DatabaseManager
{
    /** @var PDO Conexão com o banco master */
    private $masterPdo;

    /** @var Database[] Cache de conexões por escola_id na mesma requisição */
    private $tenantConnections = [];

    public function __construct(PDO $masterPdo)
    {
        $this->masterPdo = $masterPdo;
    }

    /**
     * Retorna conexão (Database) para o banco do tenant.
     * Reutiliza conexão já criada na mesma requisição (cache por escola_id).
     *
     * @param int $escolaId ID da escola em escolas.id
     * @return Database Instância com mesma interface que Database::getInstance()
     * @throws Exception Se escola não tiver config ou conexão falhar
     */
    public function getConnectionForTenant(int $escolaId): Database
    {
        if (isset($this->tenantConnections[$escolaId])) {
            return $this->tenantConnections[$escolaId];
        }

        $config = $this->getConfigTenant($escolaId);
        if (!$config) {
            throw new Exception("Configuração de banco não encontrada para a escola ID {$escolaId}. Verifique a tabela config_escolas_banco no banco master.");
        }

        $pdo = $this->createPdoForTenant($config);
        $db = Database::createFromPdo($pdo, $this->normalizeDatabaseConfig($config));
        $this->tenantConnections[$escolaId] = $db;

        return $db;
    }

    /**
     * Busca configuração do tenant em config_escolas_banco.
     *
     * @return array|null host, porta, nome_banco, usuario, senha_criptografada, charset
     */
    private function getConfigTenant(int $escolaId): ?array
    {
        $cacheKey = 'tenant_config_' . $escolaId;
        $cached = RedisCache::get($cacheKey);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $stmt = $this->masterPdo->prepare(
            "SELECT host, porta, nome_banco, usuario, senha_criptografada, charset
             FROM config_escolas_banco
             WHERE escola_id = :escola_id LIMIT 1"
        );
        $stmt->execute(['escola_id' => $escolaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false && $row !== null) {
            RedisCache::set($cacheKey, json_encode($row), 300);
            return $row;
        }
        return null;
    }

    private function createPdoForTenant(array $config): PDO
    {
        $normalized = $this->normalizeDatabaseConfig($config);
        $host = $normalized['host'];
        $port = (int) $normalized['port'];
        $name = $normalized['name'];
        $user = $normalized['user'];
        $pass = $normalized['pass'];
        $charset = $normalized['charset'];

        if ($name === '') {
            throw new Exception("Nome do banco do tenant não pode ser vazio (config_escolas_banco.nome_banco).");
        }

        $connectTimeout = $this->resolveConnectTimeout();

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Emulado (padrão do PDO, igual ao Database.php single-tenant): 1 round-trip
            // por query em vez de 3 (PREPARE/EXECUTE/DEALLOCATE) — com o MySQL em outro
            // servidor, o modo nativo triplicava a latência de rede de toda query.
            // Também evita o erro HY093 com parâmetros nomeados repetidos.
            // Reativado em 2026-07-11 depois de corrigir Database::query() para fazer
            // bind explícito de PDO::PARAM_INT/STR/NULL por parâmetro (ver
            // Database::bindParamsComTipo) — antes dessa correção, execute($params)
            // tratava todo valor como string e gerava "LIMIT '5'" (com aspas), que o
            // MySQL rejeita. Testado com LIMIT/OFFSET posicional, WHERE nomeado e
            // INSERT/UPDATE/DELETE antes de reativar.
            PDO::ATTR_EMULATE_PREPARES => true,
            // Conexão não-persistente evita "Packets out of order" quando o MySQL fecha conexões idle
            PDO::ATTR_PERSISTENT => false,
            // Timeout de conexão para reduzir falhas por oscilações curtas de rede
            PDO::ATTR_TIMEOUT => $connectTimeout,
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET time_zone = '-03:00'");
        $pdo->exec("SET NAMES {$charset} COLLATE {$charset}_unicode_ci");
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    private function normalizeDatabaseConfig(array $config): array
    {
        $connectTimeout = $this->resolveConnectTimeout();
        return [
            'host' => $config['host'] ?? 'localhost',
            'port' => (int) ($config['porta'] ?? 3306),
            'name' => $config['nome_banco'] ?? '',
            'user' => $config['usuario'] ?? '',
            'pass' => MasterSecretVault::decryptDbPassword($config['senha_criptografada'] ?? ''),
            'charset' => $config['charset'] ?? 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Reativado — ver comentário na outra conexão de tenant acima (getPdo())
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => $connectTimeout,
            ],
        ];
    }

    private function resolveConnectTimeout(): int
    {
        $raw = getenv('DB_CONNECT_TIMEOUT');
        if ($raw === false || $raw === null || $raw === '') {
            return 15;
        }
        $value = (int) $raw;
        if ($value < 1) {
            return 15;
        }
        if ($value > 60) {
            return 60;
        }
        return $value;
    }
}
