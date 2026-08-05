<?php
/**
 * Conexão PDO ao banco de uma escola (tenant) a partir do escolas.id — uso no Master / webhooks.
 */
class MasterTenantConnection
{
    private static function getMasterPdo(): ?PDO
    {
        $masterPdo = $GLOBALS['_educatudo_master_pdo'] ?? null;
        if ($masterPdo instanceof PDO) {
            return $masterPdo;
        }
        return null;
    }

    /**
     * @return array{pdo:\PDO,escola:array}|null
     */
    public static function getPdoAndEscola(int $escolaId): ?array
    {
        $masterPdo = self::getMasterPdo();
        if (!$masterPdo) {
            return null;
        }
        $stmt = $masterPdo->prepare(
            "SELECT e.id, e.nome, e.slug, b.host, b.porta, b.nome_banco, b.usuario, b.senha_criptografada
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.id = ?"
        );
        $stmt->execute([$escolaId]);
        $escola = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$escola) {
            return null;
        }
        $host = $escola['host'] ?? '';
        $port = (int) ($escola['porta'] ?? 3306);
        $dbName = $escola['nome_banco'] ?? '';
        $user = $escola['usuario'] ?? '';
        require_once __DIR__ . '/MasterSecretVault.php';
        $pass = MasterSecretVault::decryptDbPassword($escola['senha_criptografada'] ?? '');
        if ($host === '' || $dbName === '' || $user === '') {
            return null;
        }
        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            return ['pdo' => $pdo, 'escola' => $escola];
        } catch (PDOException $e) {
            error_log('[MasterTenantConnection] ' . $e->getMessage());
            return null;
        }
    }
}
