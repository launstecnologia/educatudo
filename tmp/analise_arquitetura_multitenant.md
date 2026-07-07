# Análise Arquitetural: Multi-Tenant por Subdomínio — EducaTudo

Data: 2026-06-30  
Analisado por: Claude Sonnet 4.6  
Base de código: `/Users/lucasmoraes/Projects/educatudo/src/`

---

## 1. Fluxo Completo de uma Request (passo a passo)

Cenário: usuário acessa `colag.educatudo.com/dashboard`

```
[Nginx/Apache]
    │  HTTP_HOST = colag.educatudo.com
    ▼
index.php (linha 1–676)
    │
    ├─ Lê .env linha por linha para detectar SESSION_DOMAIN (linhas 69–78)
    │   PROBLEMA: leitura de arquivo por request, sem cache
    │
    ├─ session_start() (linha 93)
    │
    ├─ Lê .env de novo para detectar DISABLE_BOOTSTRAP_METRICS (linhas 354–364)
    │   PROBLEMA: segunda leitura do mesmo arquivo
    │
    ├─ require bootstrap_multi_tenant.php (linha 676)
    │       │
    │       ├─ Lê .env uma TERCEIRA vez (linhas 17–48) para MULTI_TENANT e MASTER_DOMAIN
    │       │
    │       ├─ Detecta HTTP_HOST e REQUEST_URI para verificar se é painel /master
    │       │
    │       ├─ new PDO(master_dsn) — abre conexão TCP ao banco master (linha 119)
    │       │   PDO::ATTR_PERSISTENT => false (não persistente)
    │       │
    │       ├─ $masterPdo->exec("SET time_zone")   — roundtrip 1 ao master
    │       ├─ $masterPdo->exec("SET NAMES")       — roundtrip 2 ao master
    │       │
    │       ├─ new TenantResolver($masterPdo)
    │       ├─ $resolver->resolveTenant()
    │       │       │
    │       │       ├─ RedisCache::connect() — conecta ao Redis 127.0.0.1:6379
    │       │       ├─ RedisCache::get("tenant_colag.educatudo.com")
    │       │       │
    │       │       ├─ SE CACHE HIT (TTL=60s): retorna array {id, slug, dominio}
    │       │       │
    │       │       └─ SE CACHE MISS:
    │       │               SELECT id, slug, dominio FROM escolas
    │       │               WHERE dominio = 'colag.educatudo.com'
    │       │               AND ativo = 1 LIMIT 1
    │       │               — roundtrip 3 ao master
    │       │               RedisCache::set(..., TTL=60s)
    │       │
    │       ├─ new DatabaseManager($masterPdo)
    │       ├─ $manager->getConnectionForTenant(escola_id)
    │       │       │
    │       │       ├─ RedisCache::get("tenant_config_42")
    │       │       │
    │       │       ├─ SE CACHE HIT (TTL=300s): usa config cacheada
    │       │       │
    │       │       └─ SE CACHE MISS:
    │       │               SELECT host, porta, nome_banco, usuario,
    │       │                      senha_criptografada, charset
    │       │               FROM config_escolas_banco
    │       │               WHERE escola_id = 42 LIMIT 1
    │       │               — roundtrip 4 ao master
    │       │               RedisCache::set(..., TTL=300s)
    │       │
    │       ├─ new PDO(tenant_dsn) — abre conexão TCP ao banco da escola
    │       │   PDO::ATTR_PERSISTENT => false (não persistente)
    │       │
    │       ├─ $tenantPdo->exec("SET time_zone")   — roundtrip 1 ao tenant
    │       ├─ $tenantPdo->exec("SET NAMES")       — roundtrip 2 ao tenant
    │       │
    │       ├─ Database::setCurrentInstance(tenantDb)
    │       └─ define(TENANT_ID, TENANT_SLUG, TENANT_DOMAIN)
    │
    └─ new App() → Router → Middleware → Controller
            │
            └─ Primeira query de negócio
```

**Overhead mínimo por request (sem Redis):**
- 3 leituras do arquivo .env
- 2 conexões TCP novas (master + tenant)
- 4 roundtrips ao master MySQL
- 2 roundtrips ao tenant MySQL de inicialização
- Total: **~8 roundtrips de banco antes da primeira query de negócio**

**Overhead com Redis funcionando (cache quente):**
- 3 leituras do arquivo .env
- 1 conexão TCP nova ao master
- 2 roundtrips ao master (SET time_zone + SET NAMES)
- 2 Redis GETs (tenant_host e tenant_config)
- 1 conexão TCP nova ao tenant
- 2 roundtrips ao tenant (SET time_zone + SET NAMES)
- Total: **~4 roundtrips de banco + 2 Redis, mas ainda 2 conexões TCP novas**

---

## 2. Como o Tenant é Resolvido

**Arquivo:** `app/Core/TenantResolver.php`

**Campo HTTP lido:** `$_SERVER['HTTP_HOST']` (linha 54), com fallback para header `X-Tenant` (linhas 91–98).

**Ordem de resolução:**
1. Header `X-Tenant: colag` (usado para dev) → query por `slug`
2. `HTTP_HOST` → query por `dominio`

**Cache key:** `"tenant_" . $host` (ex.: `educatudo_tenant_colag.educatudo.com`)  
**TTL do cache:** 60 segundos (linha 71)

**Query executada no cache miss** (linha 119–125):
```sql
SELECT id, slug, dominio FROM escolas
WHERE dominio = 'colag.educatudo.com' AND ativo = 1 LIMIT 1
```

**Problema de detalhe:** a cache key inclui o host completo com porta se a requisição vier com porta não-padrão (`:8080`), mas há strip de porta na linha 56: `$host = explode(':', $host, 2)[0]`. Isso está correto.

**Inconsistência não óbvia:** o `$GLOBALS['_educatudo_db_manager']` é criado com `new DatabaseManager($masterPdo)` no bootstrap (linha 146), mas `DatabaseManager::tenantConnections` é um array de instância — não sobrevive entre requests em PHP-FPM. O cache de conexão em `tenantConnections` funciona **apenas dentro da mesma request**, não entre requests. Isso é correto, mas vale documentar.

---

## 3. Como a Conexão é Criada

**Arquivo:** `app/Core/DatabaseManager.php` e `app/Core/bootstrap_multi_tenant.php`

### Conexão com o banco master

Criada em `bootstrap_multi_tenant.php` linha 119:
```php
$options = [
    PDO::ATTR_PERSISTENT => false,   // linha 116
    PDO::ATTR_TIMEOUT => 15,         // configurável via DB_CONNECT_TIMEOUT
];
$masterPdo = new PDO($dsn, $user, $pass, $options);
```

### Conexão com o banco do tenant

Criada em `DatabaseManager::createPdoForTenant()` (linha 78):
```php
$options = [
    PDO::ATTR_PERSISTENT => false,   // linha 100
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 15,
];
```

### Lifecycle do singleton

`Database::$currentInstance` (linha 88 de Database.php) é uma propriedade estática. Em PHP-FPM:
- **Por processo:** a variável estática persiste enquanto o worker PHP estiver vivo
- **Por request:** `Database::setCurrentInstance()` é chamado no início de cada request (no bootstrap), sobrescrevendo a instância anterior

Isso é **correto**: cada request define a instância correta para o tenant. O worker pode ter atendido o tenant "colag" na request anterior, mas no início desta request o bootstrap sobrescreve `$currentInstance` com a conexão correta.

**Risco de vazamento:** Se um Controller chamar `Database::getInstance()` ANTES do bootstrap rodar (por exemplo em autoload), ele pegaria a instância errada. Analisando o código, o bootstrap roda na linha 676 do index.php, antes de `new App()`, então esse risco não existe na arquitetura atual.

### Persistent connections

`PDO::ATTR_PERSISTENT => false` está explícito em todos os lugares (bootstrap linha 116, DatabaseManager linha 100, Database::connect() linha 184). **Não há persistent connections.** Isso é uma decisão deliberada, com comentário explicando: "evita 'Packets out of order' quando o MySQL fecha conexões idle". É correta para evitar bugs, mas tem custo de performance real.

---

## 4. Análise do Cache

### Redis

**Arquivo:** `app/Core/RedisCache.php`

**Conexão:** `127.0.0.1:6379`, timeout de 2 segundos (linha 33), sem senha, sem URL configurável via variável de ambiente. O host está **hardcoded como constante** (linha 10–11). Se o Redis estiver em outro host (ex.: Redis gerenciado na AWS), isso quebra.

**O que é cacheado:**

| Chave | TTL | Conteúdo |
|---|---|---|
| `tenant_{host}` | 60s | `{id, slug, dominio}` da escola |
| `tenant_config_{escola_id}` | 300s | credenciais do banco do tenant (host, porta, nome_banco, usuario, senha_criptografada) |

**Problema de segurança:** as credenciais do banco do tenant ficam no Redis como JSON, incluindo `senha_criptografada`. Se o Redis não tiver autenticação, qualquer processo no servidor pode ler essas credenciais. O nome do campo é `senha_criptografada`, mas se a criptografia for reversível (AES-256 via `MASTER_ENCRYPTION_KEY`), quem tiver acesso ao Redis + à chave tem acesso a todos os bancos de todos os tenants.

**Fallback sem Redis:** Se `RedisCache::connect()` falhar (linha 36–38), `self::$failed = true` e todos os `get()`/`set()` retornam `null`/`false` silenciosamente. O sistema continua funcionando, mas **toda request que não encontrar Redis faz 2 queries ao banco master** para resolver o tenant. Sem Redis em produção, o banco master é consultado em 100% das requests.

**Não há fallback para file cache.** O CLAUDE.md instrui: "Não criar fallback de file cache em produção para sessão ou config de tenant — Redis é obrigatório." O código implementa isso corretamente.

### Sessão PHP

A sessão é iniciada em `index.php` linha 93 com `session_start()`. **Não há configuração de `session.save_handler = redis`** em nenhum arquivo lido. A sessão usa o handler padrão do PHP (arquivos em `/tmp` ou configurado no php.ini). Em ambiente multi-servidor (load balancer), sessões de arquivo causam problema de sticky sessions ou sessão perdida entre nós.

---

## 5. Veredicto: Isso Escala?

**Resposta direta: funciona bem para até ~20-30 escolas com carga moderada. Com 50+ escolas ou picos de 100+ req/s simultâneas, a arquitetura vai mostrar os limites.**

### Por que funciona hoje

- O Redis com TTL de 60s/300s protege o banco master: para uma escola com 500 alunos ativos, o banco master recebe ~1 query de tenant por 60 segundos por worker PHP, não por request
- O modelo PHP-FPM com isolamento por processo é correto para o modelo multi-tenant — não há risco de dados de uma escola vazando para outra

### Por que não escala bem

**O problema central é a conexão PDO não persistente.** Com `PDO::ATTR_PERSISTENT => false`, toda request abre dois handshakes TCP novos (master + tenant). O MySQL tem overhead de ~1-3ms por handshake TCP em localhost, ~5-20ms em rede. Em 100 req/s isso são 200 novas conexões por segundo ao MySQL.

MySQL suporta `max_connections` tipicamente entre 151 e 500. Com 20 workers PHP-FPM e cada worker abrindo nova conexão por request, o pico de conexões simultâneas é limitado pelo pool de workers, não pelo número de requests por segundo. Esse risco é **aceitável em PHP-FPM** porque o worker fecha a conexão ao fim do request. O problema real é a latência adicionada pelo handshake em cada request.

**O problema secundário são as 3 leituras de arquivo .env por request.** `file()` é uma syscall que vai ao filesystem. Em disco SSD negligenciável (~0.01ms), mas é desnecessário — `getenv()` ou uma variável global carregada uma vez seria suficiente.

---

## 6. Problemas Encontrados

### CRÍTICO — Redis host hardcoded

**Arquivo:** `app/Core/RedisCache.php`, linhas 10–11
```php
private const HOST = '127.0.0.1';
private const PORT = 6379;
```

**Impacto:** impossível usar Redis gerenciado (AWS ElastiCache, Redis Cloud) sem editar o código. Em produção multi-servidor, o Redis precisa ser em host dedicado.

**Solução:** ler `REDIS_URL` ou `REDIS_HOST`/`REDIS_PORT` do `.env`. A variável já existe no CLAUDE.md como obrigatória em produção, mas o código a ignora.

---

### ALTO — Nenhuma conexão persistente ao MySQL = handshake TCP por request

**Arquivo:** `app/Core/DatabaseManager.php` linha 100, `app/Core/bootstrap_multi_tenant.php` linha 116

**Impacto:** cada request abre 2 conexões TCP novas. Em 50 req/s = 100 handshakes MySQL/segundo. Não há connection pool.

**Contexto:** a decisão foi tomada para evitar o bug "Packets out of order" do MySQL com persistent connections. É uma troca válida, mas aumenta latência de ~2-5ms por request.

**Solução real:** ProxySQL ou PlanetScale (MySQL) na frente do banco oferece connection pooling sem o bug de persistent connections do PHP. Alternativamente, PgBouncer se migrasse para Postgres.

**Solução paliativa no PHP-FPM:** `PDO::ATTR_PERSISTENT => true` funciona se o PHP-FPM tiver `pm.max_children` adequado e o MySQL tiver `wait_timeout` configurado. O código atual tenta `SET SESSION wait_timeout=600` (config/app.php linha 89) apenas no singleton single-tenant, não no multi-tenant.

---

### ALTO — Sessão PHP não está no Redis

**Impacto:** em qualquer ambiente com mais de um servidor (load balancer), sessões de arquivo causam problema imediato — o usuário perde a sessão ao ser roteado para outro nó. Mesmo em single-servidor, sessões em arquivo em `/tmp` com muitos usuários simultâneos criam contenção de I/O.

**Solução:** `session.save_handler = redis` + `session.save_path = "tcp://redis-host:6379"` no php.ini ou via `ini_set()` antes do `session_start()`.

---

### MÉDIO — .env lido 3 vezes por request

**Arquivos:**
- `index.php` linha 70: lê `.env` para `SESSION_DOMAIN`
- `index.php` linha 356: lê `.env` para `DISABLE_BOOTSTRAP_METRICS`
- `bootstrap_multi_tenant.php` linha 22: lê `.env` para `MULTI_TENANT` e `MASTER_DOMAIN`

São 3 chamadas `file()` lendo o mesmo arquivo. Em produção com muitos requests, isso é barulho desnecessário.

**Solução:** carregar o `.env` uma única vez em memória no início do `index.php` e armazenar em `$_ENV` ou `$GLOBALS`.

---

### MÉDIO — Credenciais de banco de tenant no Redis sem TTL de segurança adequado

**Arquivo:** `app/Core/DatabaseManager.php` linha 72
```php
RedisCache::set($cacheKey, json_encode($row), 300);
```

O campo `senha_criptografada` é armazenado no Redis com TTL de 5 minutos. Se a senha de um tenant for rotacionada no banco master, o sistema continua usando a senha antiga por até 5 minutos.

**Impacto de segurança:** se o Redis não tem autenticação (o código hardcoda sem senha), qualquer processo no servidor lê as credenciais de todos os tenants com um simples `redis-cli keys '*tenant_config*'`.

**Solução:** configurar `requirepass` no Redis. Atualizar `RedisCache` para ler a senha do `.env`.

---

### MÉDIO — Reconexão ao banco do tenant falha silenciosamente no multi-tenant

**Arquivo:** `app/Core/Database.php` método `reconnectIfPossible()` (linha 253)

O reconect chama `$this->connect()` que usa `$this->config['database']`. Para conexões criadas via `createFromPdo()` (o caminho multi-tenant), `$this->config['database']` está vazio (linha 103: `$this->config = ['database' => []]`). Então `canReconnect()` retorna `false` (linha 231) e o reconect nunca acontece.

**Impacto:** se o MySQL fechar uma conexão idle (wait_timeout) durante uma request multi-tenant longa (upload de arquivo, geração de IA), a query falha com "MySQL server has gone away" e **não há retry**. O usuário vê erro 500.

---

### BAIXO — DatabaseManager instanciado em `$GLOBALS` mas sem reuso real

**Arquivo:** `bootstrap_multi_tenant.php` linhas 145–148
```php
if (!isset($GLOBALS['_educatudo_db_manager'])) {
    $GLOBALS['_educatudo_db_manager'] = new DatabaseManager($masterPdo);
}
```

O guard `!isset()` protege contra dupla instância dentro da mesma request. Mas como `$masterPdo` é criado no mesmo script e o bootstrap só roda uma vez por request, esse guard nunca é exercido. Não é um bug, mas é código morto.

---

### BAIXO — Inconsistência em ATTR_EMULATE_PREPARES

- `Database::loadDbConfigFromEnv()` (linha 82): `PDO::ATTR_EMULATE_PREPARES => true`
- `Database::connect()` (linha 191): `setAttribute(ATTR_EMULATE_PREPARES, true)` (redundante)
- `DatabaseManager::createPdoForTenant()` (linha 98): `PDO::ATTR_EMULATE_PREPARES => false`

O banco do tenant usa prepares nativos (`false`) mas o banco master usa emulados (`true`). Comportamento diferente para o mesmo código de query pode causar bugs sutis se uma query for executada nos dois contextos.

---

## 7. O Que Está Bem Feito

**Isolamento por conexão PDO separada:** o princípio fundamental está correto. Não há risco de cross-tenant data leak por query, porque a conexão em si é isolada. Isso é superior a abordagens que usam `WHERE escola_id = X` em uma conexão compartilhada.

**Cache Redis com TTL duplo:** usar TTL curto (60s) para identidade do tenant e TTL mais longo (300s) para credenciais do banco é uma decisão sensata — permite que o domínio de uma escola seja atualizado com propagação rápida.

**Fallback sem Redis:** o sistema não quebra quando o Redis está indisponível. Degrada para consultas ao banco master, que é o comportamento correto. A flag `self::$failed` evita tentar reconectar em toda query da mesma request.

**`Database::$connectionFailed` flag:** evita loop de reconexão catastrófico quando o banco está fora. Uma vez que a conexão falha, todas as instâncias subsequentes na mesma request falham rápido (linha 147–150 de Database.php).

**Prepared statements consistentes:** não há concatenação de variáveis em SQL encontrada nos arquivos core. O uso de `?` e `:named` é uniforme.

**Detecção de `isGoneAwayException()` com retry (linha 366 de Database.php):** há lógica para detectar erro 2006/2013 e tentar reconectar. O problema está no multi-tenant onde `canReconnect()` sempre retorna false, como descrito no item 6.

**Workaround para HY093 documentado:** o `normalizeNamedParamsForSql()` resolve um bug real do driver MySQL com parâmetros nomeados repetidos. Está documentado no código e no CLAUDE.md.

---

## 8. Recomendações (por prioridade)

### Alta — fazer agora

**1. Redis host configurável via variável de ambiente**

Arquivo: `app/Core/RedisCache.php`

```php
// Antes:
private const HOST = '127.0.0.1';
private const PORT = 6379;

// Depois: ler do ambiente
private static function getConfig(): array {
    $url = getenv('REDIS_URL');
    if ($url) {
        $parts = parse_url($url);
        return [
            'host' => $parts['host'] ?? '127.0.0.1',
            'port' => $parts['port'] ?? 6379,
            'pass' => $parts['pass'] ?? null,
        ];
    }
    return [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        'pass' => getenv('REDIS_PASSWORD') ?: null,
    ];
}
```

**2. Sessão PHP no Redis**

Antes de `session_start()` em `index.php` (linha 93), configurar:
```php
if (($redisUrl = getenv('REDIS_URL')) || ($redisHost = getenv('REDIS_HOST'))) {
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', $redisUrl ?: "tcp://{$redisHost}:6379");
}
```

**3. Corrigir reconexão no modo multi-tenant**

Em `Database::createFromPdo()`, passar a config completa para permitir reconexão:
```php
public static function createFromPdo(PDO $pdo, ?array $databaseConfig = null): self {
    return new self(['pdo' => $pdo, 'database' => $databaseConfig ?? []]);
}
```

O `DatabaseManager` já passa `normalizeDatabaseConfig($config)` como segundo argumento — mas o campo `pass` está com valor `senha_criptografada` (já decriptografado?). Verificar se a senha está sendo passada em plaintext ou ainda criptografada.

---

### Média — próximos 3 meses

**4. Eliminar as 3 leituras do .env**

Criar `app/Core/EnvLoader.php` que carrega o `.env` uma única vez em `$_ENV` ou em constante global, e reusar em todos os lugares que hoje chamam `file()` diretamente.

**5. ProxySQL ou MySQL connection pooling**

Para qualquer cenário com 10+ escolas ativas simultaneamente, adicionar ProxySQL na frente dos bancos MySQL elimina o overhead de handshake TCP. ProxySQL suporta roteamento por schema (banco) e mantém um pool de conexões abertas.

Configuração básica: ProxySQL escuta na porta 6033, o PHP conecta ao ProxySQL, o ProxySQL reusa conexões já abertas ao MySQL.

**6. Adicionar autenticação ao Redis**

Redis sem senha em servidor compartilhado é inaceitável com credenciais de banco em cache. Configurar `requirepass` no Redis e passar `REDIS_PASSWORD` no `.env`.

**7. Consolidar ATTR_EMULATE_PREPARES**

Definir comportamento consistente para todos os bancos. O driver nativo (`false`) é preferível para segurança (não interpola parâmetros), mas exige cuidado com placeholders repetidos. Avaliar se o motivo original do `true` no master ainda é válido.

---

### Baixa — backlog

**8. Read replica para banco master**

O banco master é consultado para resolver tenant (com cache) e para queries de master admin. Com 50+ escolas, o master começa a ser gargalo. Uma read replica para leituras de `config_escolas_banco` e `escolas` adiciona resiliência.

**9. Instrumentar latência do bootstrap**

Adicionar medição do tempo gasto no bootstrap multi-tenant (tempo de conexão ao master, tempo de resolução de tenant, tempo de conexão ao tenant) e expor como métrica. Hoje o sistema mede queries individuais mas não o overhead de inicialização.

**10. Invalidar cache de tenant ao atualizar escola**

Se o domínio de uma escola for atualizado no painel master, o cache Redis com TTL=60s ainda serve o domínio antigo por até 1 minuto. Adicionar `RedisCache::delete("tenant_{$dominio_antigo}")` no Controller que atualiza a escola.

---

## Resumo executivo

A arquitetura está **conceitualmente correta** e **funcionalmente segura** para o tamanho atual. O isolamento por conexão PDO separada é a abordagem certa. O cache Redis de dois níveis é adequado.

Os problemas são todos de **operação em escala**, não de segurança de dados entre tenants:

1. Sem Redis configurável, não é possível escalar horizontalmente
2. Sem sessão no Redis, um segundo servidor quebra todos os logins
3. Sem connection pooling, o overhead de handshake TCP limita throughput
4. Sem autenticação no Redis, credenciais de banco ficam expostas

Prioridade imediata: itens 1 e 2 do plano de ação (Redis configurável + sessão no Redis). Sem isso, qualquer tentativa de escalar horizontalmente (segundo servidor, auto-scaling) quebra a plataforma.
