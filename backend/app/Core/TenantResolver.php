<?php

namespace App\Core;
/**
 * EducaTudo - Resolução de tenant (escola) por requisição
 * Usado quando MULTI_TENANT=true. Consulta o banco master para obter escola_id por domínio ou slug.
 */


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

    public function __construct(\PDO $masterPdo)
    {
        $this->masterPdo = $masterPdo;
    }

    /**
     * Resolve o tenant da requisição atual.
     * Em produção a escola sai só do domínio cadastrado.
     * Fora de produção, o header X-Tenant (slug) tem prioridade sobre o domínio.
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
     * Em produção a escola sai só do domínio cadastrado.
     * Fora de produção, o header X-Tenant (slug) tem prioridade sobre o domínio.
     *
     * @return array{id:int,slug:string,dominio:?string}|null
     */
    public function resolveTenant(): ?array
    {
        $cached = self::cachedTenantFromRequest();
        if ($cached !== null) {
            return $cached;
        }

        $slug = self::slugFromHeader();
        $host = self::hostFromRequest();
        $cacheKey = self::cacheKeyFromRequest();

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
     * Remove cache de resolução e de config de banco para a escola (todos os hosts conhecidos).
     * Usar ao ativar/desativar ou alterar domínio — não depende só do TTL.
     *
     * @param array{id?:int|string,slug?:string,dominio?:string} $escola
     */
    public static function invalidarCacheEscola(array $escola): void
    {
        $id = (int) ($escola['id'] ?? 0);
        $slug = strtolower(trim((string) ($escola['slug'] ?? '')));
        $dominio = strtolower(trim((string) ($escola['dominio'] ?? '')));
        if ($dominio !== '' && strpos($dominio, ':') !== false) {
            $dominio = explode(':', $dominio, 2)[0];
        }

        if ($id > 0) {
            RedisCache::delete('tenant_config_' . $id);
        }
        if ($dominio !== '') {
            RedisCache::delete('tenant_' . $dominio);
            if ($slug !== '') {
                RedisCache::delete('tenant_' . $dominio . '_' . $slug);
            }
        }
    }

    /**
     * Resolve metadados mesmo quando a escola esta inativa.
     * Usado para responder uma pagina controlada em vez de excecao no bootstrap.
     *
     * @return array{id:int,nome:string,slug:string,dominio:?string,ativo:int}|null
     */
    public function resolveTenantIncludingInactive(): ?array
    {
        $slug = self::slugFromHeader();
        if ($slug !== null) {
            return $this->resolveBySlugIncludingInactive($slug);
        }

        $host = self::hostFromRequest();
        if ($host === '') {
            return null;
        }

        return $this->resolveByDominioIncludingInactive($host);
    }

    /**
     * Chave Redis de resolução de tenant para a requisição atual (TTL 60s).
     * Formato: tenant_<host>. Fora de produção, inclui o slug quando X-Tenant está presente.
     */
    public static function cacheKeyFromRequest(): string
    {
        $slug = self::slugFromHeader();
        $host = self::hostFromRequest();
        return 'tenant_' . $host . ($slug !== null ? '_' . $slug : '');
    }

    /**
     * Metadados do tenant já cacheados no Redis, sem consultar o MASTER.
     * Fail closed: exige ativo=1 e identidade coerente com Host/X-Tenant.
     * Cache legado sem `ativo` ou com identidade divergente é descartado.
     *
     * @return array{id:int,slug:string,dominio:?string,ativo:int}|null
     */
    public static function cachedTenantFromRequest(): ?array
    {
        $cacheKey = self::cacheKeyFromRequest();
        $cached = RedisCache::get($cacheKey);
        if ($cached === null) {
            return null;
        }
        $decoded = json_decode($cached, true);
        $tenant = self::validarTenantCacheado(
            is_array($decoded) ? $decoded : null,
            self::hostFromRequest(),
            self::slugFromHeader()
        );
        if ($tenant === null) {
            RedisCache::delete($cacheKey);
            return null;
        }
        return $tenant;
    }

    /**
     * Valida payload de cache de tenant (fail closed).
     * Exposto para testes unitários sem Redis.
     *
     * @param array<string,mixed>|null $decoded
     * @return array{id:int,slug:string,dominio:?string,ativo:int}|null
     */
    public static function validarTenantCacheado(?array $decoded, string $host, ?string $slugHeader): ?array
    {
        if ($decoded === null || !isset($decoded['id'], $decoded['slug']) || (int) $decoded['id'] <= 0) {
            return null;
        }
        if (!array_key_exists('ativo', $decoded) || (int) $decoded['ativo'] !== 1) {
            return null;
        }

        $slugCached = strtolower(trim((string) $decoded['slug']));
        if ($slugHeader !== null) {
            if ($slugCached === '' || $slugCached !== strtolower(trim($slugHeader))) {
                return null;
            }
        } else {
            $dominioCached = strtolower(trim((string) ($decoded['dominio'] ?? '')));
            if ($dominioCached !== '' && strpos($dominioCached, ':') !== false) {
                $dominioCached = explode(':', $dominioCached, 2)[0];
            }
            $host = strtolower(trim($host));
            if ($host !== '' && strpos($host, ':') !== false) {
                $host = explode(':', $host, 2)[0];
            }
            if ($host === '' || $dominioCached === '' || $dominioCached !== $host) {
                return null;
            }
        }

        return [
            'id' => (int) $decoded['id'],
            'slug' => (string) $decoded['slug'],
            'dominio' => isset($decoded['dominio']) ? (string) $decoded['dominio'] : null,
            'ativo' => 1,
        ];
    }

    public static function hostFromRequest(): string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && strpos($host, ':') !== false) {
            $host = explode(':', $host, 2)[0];
        }
        return strtolower($host);
    }

    /**
     * Header X-Tenant só fora de produção. Em produção o cabeçalho é ignorado.
     */
    private static function slugFromHeader(): ?string
    {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            return null;
        }

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
            "SELECT id, slug, dominio, ativo FROM escolas WHERE slug = :slug AND ativo = 1 LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'slug' => (string) ($row['slug'] ?? ''),
            'dominio' => isset($row['dominio']) ? (string) $row['dominio'] : null,
            'ativo' => 1,
        ];
    }

    private function resolveByDominio(string $dominio): ?array
    {
        $stmt = $this->masterPdo->prepare(
            "SELECT id, slug, dominio, ativo FROM escolas WHERE dominio = :dominio AND ativo = 1 LIMIT 1"
        );
        $stmt->execute(['dominio' => $dominio]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'slug' => (string) ($row['slug'] ?? ''),
            'dominio' => isset($row['dominio']) ? (string) $row['dominio'] : null,
            'ativo' => 1,
        ];
    }

    private function resolveBySlugIncludingInactive(string $slug): ?array
    {
        $stmt = $this->masterPdo->prepare(
            "SELECT id, nome, slug, dominio, ativo FROM escolas WHERE slug = :slug LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->formatTenantWithStatus($row) : null;
    }

    private function resolveByDominioIncludingInactive(string $dominio): ?array
    {
        $stmt = $this->masterPdo->prepare(
            "SELECT id, nome, slug, dominio, ativo FROM escolas WHERE dominio = :dominio LIMIT 1"
        );
        $stmt->execute(['dominio' => $dominio]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->formatTenantWithStatus($row) : null;
    }

    /**
     * @return array{id:int,nome:string,slug:string,dominio:?string,ativo:int}
     */
    private function formatTenantWithStatus(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'nome' => (string) ($row['nome'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'dominio' => isset($row['dominio']) ? (string) $row['dominio'] : null,
            'ativo' => (int) ($row['ativo'] ?? 0),
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
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = (int) $row['id'];
        }
        return $ids;
    }
}

if (!class_exists('TenantResolver', false) && class_exists(__NAMESPACE__ . '\\TenantResolver', false)) {
    class_alias(__NAMESPACE__ . '\\TenantResolver', 'TenantResolver');
}
