<?php
/**
 * EducaTudo - Gerenciador de conexões por tenant (escola)
 * Usado quando MULTI_TENANT=true. Obtém credenciais do banco master (config_escolas_banco)
 * e retorna instância de Database conectada ao banco do tenant.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/RedisCache.php';
require_once __DIR__ . '/MasterSecretVault.php';

class DatabaseManager
{
    /** @var PDO|null Conexão com o banco master (lazy: só abre em cache miss) */
    private $masterPdo;

    /** @var Database[] Cache de conexões por escola_id na mesma requisição */
    private $tenantConnections = [];

    public function __construct(?PDO $masterPdo = null)
    {
        $this->masterPdo = $masterPdo;
    }

    /**
     * Abre o MASTER somente sob demanda (cache miss ou serviços que precisam dele).
     */
    public function ensureMasterPdo(): PDO
    {
        if ($this->masterPdo instanceof PDO) {
            return $this->masterPdo;
        }
        $global = $GLOBALS['_educatudo_master_pdo'] ?? null;
        if ($global instanceof PDO) {
            $this->masterPdo = $global;
            return $this->masterPdo;
        }
        $this->masterPdo = Database::createMasterPdo();
        $GLOBALS['_educatudo_master_pdo'] = $this->masterPdo;
        return $this->masterPdo;
    }

    /**
     * Cria a conexão TENANT a partir da configuração já armazenada no Redis,
     * sem consultar o banco MASTER.
     *
     * @param array $config host, porta, nome_banco, usuario, senha_criptografada, charset
     */
    public function createConnectionFromConfig(array $config, ?int $escolaId = null): Database
    {
        if ($escolaId !== null && isset($this->tenantConnections[$escolaId])) {
            return $this->tenantConnections[$escolaId];
        }

        $pdo = $this->createPdoForTenant($config);
        $db = Database::createFromPdo($pdo, $this->normalizeDatabaseConfig($config));
        if ($escolaId !== null) {
            $this->tenantConnections[$escolaId] = $db;
        }

        return $db;
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

        return $this->createConnectionFromConfig($config, $escolaId);
    }

    /**
     * Busca configuração do tenant em config_escolas_banco.
     *
     * @return array|null host, porta, nome_banco, usuario, senha_criptografada, charset
     */
    private function getConfigTenant(int $escolaId): ?array
    {
        $cached = self::cachedConfig($escolaId);
        if ($cached !== null) {
            return $cached;
        }
        $stmt = $this->ensureMasterPdo()->prepare(
            "SELECT host, porta, nome_banco, usuario, senha_criptografada, charset
             FROM config_escolas_banco
             WHERE escola_id = :escola_id LIMIT 1"
        );
        $stmt->execute(['escola_id' => $escolaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false && $row !== null) {
            RedisCache::set('tenant_config_' . $escolaId, json_encode($row), 300);
            return $row;
        }
        return null;
    }

    /**
     * Configuração de banco do tenant já cacheada no Redis (TTL 300s).
     * Retorna null se a chave não existir ou se o JSON estiver incompleto
     * (e nesse caso a chave é removida para o próximo fluxo consultar o MASTER).
     */
    public static function cachedConfig(int $escolaId): ?array
    {
        if ($escolaId < 1) {
            return null;
        }
        $cached = RedisCache::get('tenant_config_' . $escolaId);
        if ($cached === null) {
            return null;
        }
        $decoded = json_decode($cached, true);
        if (!is_array($decoded) || !self::configTenantValida($decoded)) {
            RedisCache::delete('tenant_config_' . $escolaId);
            return null;
        }
        return $decoded;
    }

    public static function configTenantValida(array $config): bool
    {
        $host = trim((string) ($config['host'] ?? ''));
        $nomeBanco = trim((string) ($config['nome_banco'] ?? ''));
        $usuario = trim((string) ($config['usuario'] ?? ''));
        return $host !== '' && $nomeBanco !== '' && $usuario !== '';
    }

    private function createPdoForTenant(array $config): PDO
    {
        $normalized = $this->normalizeDatabaseConfig($config);
        $host = $normalized['host'];
        $port = (int) $normalized['port'];
        $name = $normalized['name'];
        $user = $normalized['user'];
        $pass = $normalized['pass'];
        $charset = strtolower((string) $normalized['charset']);
        if (!in_array($charset, ['utf8mb4', 'utf8'], true)) {
            $charset = 'utf8mb4';
        }

        if ($name === '') {
            throw new Exception("Nome do banco do tenant não pode ser vazio (config_escolas_banco.nome_banco).");
        }

        $connectTimeout = Database::resolveConnectTimeout();

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
        $connectTimeout = Database::resolveConnectTimeout();
        return [
            'host' => $config['host'] ?? 'localhost',
            'port' => (int) ($config['porta'] ?? 3306),
            'name' => $config['nome_banco'] ?? '',
            'user' => $config['usuario'] ?? '',
            'pass' => MasterSecretVault::decryptDbPassword($config['senha_criptografada'] ?? ''),
            'charset' => in_array(strtolower((string) ($config['charset'] ?? 'utf8mb4')), ['utf8mb4', 'utf8'], true)
                ? strtolower((string) ($config['charset'] ?? 'utf8mb4'))
                : 'utf8mb4',
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
}
