<?php
/**
 * EducaTudo - Resolução de tenant (escola) por requisição
 * Usado quando MULTI_TENANT=true. Consulta o banco master para obter escola_id por domínio ou slug.
 */

require_once __DIR__ . '/RedisCache.php';

class TenantResolver
{
    /**
     * Chave de tenant pra isolar caches `static` dentro de um mesmo worker de
     * PHP-FPM. `static` em método/função persiste pela vida do worker, não
     * pelo request — e o mesmo worker atende requests de escolas diferentes
     * (um único pool de PHP-FPM serve todos os domínios). Qualquer cache
     * `static $cache = ...` que guarde algo específico de tenant (ex.: "essa
     * tabela/coluna existe?") precisa incluir essa chave, senão o resultado de
     * uma escola vaza pra próxima escola atendida pelo mesmo worker.
     */
    public static function workerCacheKey(): string
    {
        return defined('TENANT_ID') ? ('t' . TENANT_ID) : 'no_tenant';
    }

    /** @var PDO Conexão com o banco master (escolas, config_escolas_banco) */
    private $masterPdo;

    public function __construct(PDO $masterPdo)
    {
        $this->masterPdo = $masterPdo;
    }

    /**
     * Resolve o tenant da requisição atual.
     * Ordem: header X-Tenant (slug), depois HTTP_HOST (dominio).
     *
     * @return int|null escola_id ou null se não encontrar / inativo
     */
    public function resolve(): ?int
    {
        $tenant = $this->resolveTenant();
        return $tenant ? (int) $tenant['id'] : null;
    }

    /**
     * Resolve metadados do tenant da requisição atual.
     * Ordem: header X-Tenant (slug), depois HTTP_HOST (dominio).
     *
     * @return array{id:int,slug:string,dominio:?string}|null
     */
    public function resolveTenant(): ?array
    {
        $slug = $this->getSlugFromHeader();
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && strpos($host, ':') !== false) {
            $host = explode(':', $host, 2)[0];
        }
        $host = strtolower($host);
        $cacheKey = 'tenant_' . $host . ($slug !== null ? '_' . $slug : '');
        $cached = RedisCache::get($cacheKey);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded) && isset($decoded['id'], $decoded['slug'])) {
                return $decoded;
            }
        }

        if ($slug !== null) {
            $tenant = $this->resolveBySlug($slug);
            if ($tenant !== null) {
                RedisCache::set($cacheKey, json_encode($tenant), 60);
            }
            return $tenant;
        }

        if ($host === '') {
            return null;
        }

        $tenant = $this->resolveByDominio($host);
        if ($tenant !== null) {
            RedisCache::set($cacheKey, json_encode($tenant), 60);
        }
        return $tenant;
    }

    /**
     * Header X-Tenant para dev (valor = slug da escola).
     */
    private function getSlugFromHeader(): ?string
    {
        if (function_exists('getallheaders')) {
            $headers = array_change_key_case(getallheaders(), CASE_LOWER);
            $slug = strtolower(trim((string) ($headers['x-tenant'] ?? '')));
            return $slug !== '' ? $slug : null;
        }
        $slug = strtolower(trim((string) ($_SERVER['HTTP_X_TENANT'] ?? '')));
        return $slug !== '' ? $slug : null;
    }

    private function resolveBySlug(string $slug): ?array
    {
        $stmt = $this->masterPdo->prepare(
            "SELECT id, slug, dominio FROM escolas WHERE slug = :slug AND ativo = 1 LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'slug' => (string) ($row['slug'] ?? ''),
            'dominio' => isset($row['dominio']) ? (string) $row['dominio'] : null,
        ];
    }

    private function resolveByDominio(string $dominio): ?array
    {
        $stmt = $this->masterPdo->prepare(
            "SELECT id, slug, dominio FROM escolas WHERE dominio = :dominio AND ativo = 1 LIMIT 1"
        );
        $stmt->execute(['dominio' => $dominio]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'slug' => (string) ($row['slug'] ?? ''),
            'dominio' => isset($row['dominio']) ? (string) $row['dominio'] : null,
        ];
    }

    /**
     * Lista IDs de todas as escolas ativas (para crons que iteram tenants).
     *
     * @return int[]
     */
    public function listarEscolasAtivas(): array
    {
        $stmt = $this->masterPdo->query("SELECT id FROM escolas WHERE ativo = 1 ORDER BY id");
        $ids = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ids[] = (int) $row['id'];
        }
        return $ids;
    }
}
