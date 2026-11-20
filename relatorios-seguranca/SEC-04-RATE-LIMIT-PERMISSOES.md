# SEC-04 — Rate limit e permissões (admin / master / monitor)

**Data:** 2026-11-20  
**Ambiente:** Docker local EducaTudo (`colag.localhost`, `escola2.localhost`, `master.localhost` via `nginx_educatudo:80`)  
**Escopo:** Rate limit de login e controles de autorização dos portais `/admin`, `/master` e `/monitor`  
**Regra desta etapa:** evidência no código e testes locais; **sem correção automática**

**Veredito (atualizado 2026-11-20):** V-01/V-02/V-03/V-05 tratados. Harness rate/perms **19/19 APROVADO**. CSRF log no path canônico; Nginx prod com `cloudflare-real-ip.conf` (`CF-Connecting-IP` só de ranges CF).

---

## 1. Ambiente

| Item | Valor |
|---|---|
| Contêineres | `nginx_educatudo`, `php_app_educatudo`, `mysql_db_educatudo`, `redis_educatudo` |
| Hosts | `colag.localhost` (tenant 1), `escola2.localhost` (tenant 2), `master.localhost` |
| Bancos | `educatudo_colag`, `educatudo_escola2`, `educatudo_master` |
| Config efetiva (`config/app.php`) | `max_login_attempts=5`, `lockout_duration=900`, `max_login_attempts_ip=50` |
| Redis | disponível (`PONG`), **não** usado no rate limit de login web |

Contas de laboratório (senhas não registradas neste relatório): admin Colag, aluno Colag, monitor temporário `sec04.monitor@colag.local` (criado e removido pelo harness).

Artefatos de teste:

- `backend/tests/Seguranca/sec04_http_runner.php`
- `backend/tests/Seguranca/Sec04SessaoTenantTest.php`
- `backend/tests/Seguranca/Sec04CodigoAcessoMasterTest.php`
- `relatorios-seguranca/SEC-04-resultados-runner.json`

---

## 2. Mapeamento de rotas e middlewares

| Portal | GET login | POST autenticação | Controller | Middleware de rota |
|---|---|---|---|---|
| Admin | `GET /admin` | `POST /login` (`tipo=admin_escola`) | `Auth/AuthController@loginAdmin` / `@autenticar` | Público (fora do grupo `Auth`) |
| Monitor | `GET /monitor` | `POST /login` (`tipo=monitor`) | `Auth/AuthController@loginMonitor` / `@autenticar` | Público |
| Master | `GET /master` | `POST /master/login` | `Master/MasterAuthController@autenticar` | Público (`config/routes/master.php`, **fora** do `Auth`) |
| Master OTP | `GET /master/verificar` | `POST /master/verificar` | `@confirmarCodigo` | Público + sessão de desafio 2FA |
| Painéis autenticados admin/monitor | `/admin/*`, `/monitor/*` | — | Controllers de perfil | `Auth` + `Csrf` (`config/routes.php`) |
| Painéis Master | `/master/*` (exceto login/webhook/cron) | — | Controllers Master | **Sem** `AuthMiddleware`; checagem `master_user_id` por controller |

Fluxo de grupos (`backend/config/routes.php`):

1. `master.php` e `public.php` — sem `Auth`
2. Grupo `middleware('Auth')` → `Csrf` → `student` / `monitor` / `teacher` / `admin` / `parents` + módulos

`AuthMiddleware` garante sessão válida (`AuthManager::isLoggedIn` + `sessaoConfereComEscolaAtual`), **não** o papel (admin vs monitor). O papel é reforçado nos controllers (`AdminBaseController`, `MonitorController`).

---

## 3. Rate limit por portal

### 3.1 Admin e Monitor (mesmo `POST /login`)

| Camada | Limite | Janela | Storage | Observação |
|---|---|---|---|---|
| Por identificador (login/e-mail) | `max_login_attempts` (efetivo 5 → bloqueio na 5ª tentativa após 4 falhas, `allowUntil = max-1`) | `lockout_duration` (900 s) | MySQL `tentativas_login` (tenant) | `AuthManager::authenticate` / `getFailedAttemptsCount` / `recordLoginAttempt` |
| Por IP | `max_login_attempts_ip` (50) | `lockout_duration` (900 s) | Arquivo JSON + `flock` | `AuthController::checkLoginRateLimit` / `recordLoginRateLimitAttempt` |

**Uso das configs nos três portais**

| Chave | Admin | Monitor | Master (senha) | Master (OTP) |
|---|---|---|---|---|
| `max_login_attempts` | Sim (`AuthManager`) | Sim | Sim (`MasterAuthController`) | Não (usa `CodigoAcessoMasterService::MAX_TENTATIVAS=5`) |
| `lockout_duration` | Sim | Sim | Sim | Não (`VALIDADE_MINUTOS=10`) |
| `max_login_attempts_ip` | Sim (`AuthController`) | Sim | Sim | Não |

### 3.2 Master — etapa senha

`MasterAuthController::autenticar` valida CSRF + rate limit + `password_verify` e inicia o 2FA.  
Contadores em `storage/logs/master_login_rate_limit.json` (e-mail em SHA-256 + IP), lendo `max_login_attempts` (5), `max_login_attempts_ip` e `lockout_duration`. Bloqueio alinhado ao AuthManager (`allowUntil = max-1`). Logs em `master_auth.log` incluem bloqueio por rate limit.

### 3.3 Master — etapa OTP

`CodigoAcessoMasterService`: validade 10 min, máx. 5 tentativas por código, reenvio mínimo 60 s, hash do código no banco master. **Não** reutiliza `max_login_attempts*`.  
Tabela criada localmente via `MysqlProvisioningService::runMasterMigrationsSelected` (`scripts/aplicar_migration_master_otp_local.php`). Reteste: 5 códigos errados → `esgotado`; código válido em novo desafio → `ok` (**APROVADO**).

### 3.4 IP real (Cloudflare / Nginx)

- Rate limit de login web usa **somente** `$_SERVER['REMOTE_ADDR']` (`AuthController@autenticar`).
- Nginx local/prod do repo **não** configura `real_ip` / `CF-Connecting-IP`.
- Teste: `X-Forwarded-For: 203.0.113.50` **não** virou chave do contador (APROVADO contra spoofing de cliente).
- Efeito colateral: atrás de proxy/CF, o IP visto é o do hop anterior (no lab: `172.20.0.1`), agregando usuários no mesmo contador.
- `Logger::getRealIpAddress` e `AuthController::getIpAddress` (consentimentos) **aceitam** headers de cliente — fora do gate de rate limit de login, mas relevante para auditoria de IP.

### 3.5 Persistência entre workers / contêineres

| Contador | Entre workers FPM (mesmo container) | Entre containers |
|---|---|---|
| `tentativas_login` (MySQL) | Sim | Sim (mesmo banco tenant) |
| JSON IP | Sim (arquivo + `flock` no volume) | Só se o volume for compartilhado; **não** é Redis |
| Master senha | N/A (sem contador) | N/A |

**Bug de caminho (evidência):** `AuthController` grava o JSON em `dirname(__DIR__, 3) . '/storage/logs'` → `/var/www/html/app/storage/logs/login_rate_limit.json`, e não `backend/storage/logs/`. O rate limit por IP funciona, mas fora do diretório canônico de logs.

### 3.6 Logs de tentativas / bloqueios

| Evento | Onde |
|---|---|
| Falha/sucesso por identificador | `tentativas_login` (+ UI admin Tentativas de login) |
| Bloqueio por identificador | Mensagem flash ao usuário; sem log estruturado dedicado de “lock/unlock” |
| Rate limit IP | Arquivo JSON; sem log de bloqueio/desbloqueio |
| Master senha | `master_auth.log` |
| Master OTP | `error_log` / `master_auth.log` (código recusado / esgotado) |

---

## 4. Permissões — políticas observadas

| Cenário | Mecanismo | Classificação |
|---|---|---|
| 1. Admin escola A → recursos escola B | PDO por tenant + `AuthManager::sessaoConfereComEscolaAtual()` (`$_SESSION['tenant_id']` vs `TENANT_ID`) | APROVADO (HTTP: cookie Colag em `escola2.localhost` não mantém dashboard) |
| 2. Admin → rotas Master | Sessão Master distinta (`master_user_id`); rotas Master fora do `Auth` tenant | APROVADO |
| 3. Monitor → rotas Admin | `AdminBaseController` redireciona se `tipo` ∉ {admin, admin_escola} | APROVADO |
| 4. Usuário comum → admin | Mesmo gate de papel + `Auth` | APROVADO (aluno lab) |
| 5. Master com sessão Master | `requireMaster()` / `requireMasterAuth()` / checagens inline | APROVADO (anônimo e POST sem sessão rejeitados; crons exigem key) |
| 6. Tampering de IDs escola/usuário/aluno | Isolamento por conexão; IDs de aluno em admin ficam no banco do Host atual | APROVADO no isolamento de escola; ownership fino depende do módulo (fora do escopo profundo deste SEC-04) |
| 7. POST/API direto sem UI | Grupo `Auth`+`Csrf` para admin; Master exige sessão; crons com key | APROVADO nos smoke tests |

**Ressalvas**

- `AuthMiddleware` não filtra perfil; falha de um controller sem checagem de `tipo` seria bypass de papel.
- Alguns Controllers Master aceitam `master_user_email` / `master_user_nome` além de `master_user_id` (checagem mais frouxa).
- Cookie `master_auth` (HMAC) pode restaurar sessão Master — superfície adicional a auditar em SEC específico de sessão.

---

## 5. Resultado dos testes automatizados

### 5.1 PHPUnit

```text
Sec04SessaoTenantTest + Sec04CodigoAcessoMasterTest → OK
(limites OTP: VALIDADE=10, MAX_TENTATIVAS=5, REENVIO=60; máscara de e-mail)
```

### 5.2 Harness HTTP/DB (`sec04_http_runner.php`)

| ID | Status | Nota |
|---|---|---|
| RL-01 bloqueio identificador admin | APROVADO | 4 falhas seed → mensagem de bloqueio |
| RL-02 recuperação após janela | APROVADO | Falhas antigas (`2020-01-01`) não bloqueiam |
| RL-03 IP não confia em XFF cliente | APROVADO | Contador usou `172.20.0.1`, não `203.0.113.50` |
| RL-04 bloqueio por IP (arquivo) | APROVADO | `c>=50` no IP real bloqueia |
| RL-05 contador compartilhado | APROVADO | Arquivo + flock (path real: `app/storage/logs/`) |
| RL-06 Master senha rate limit | APROVADO | Bloqueia com `max_login_attempts=5` |
| RL-07 OTP Master max tentativas | APROVADO | Tabela criada; 5 erros → esgotado; código válido → ok |
| PERM-01…12 | APROVADO | Anônimo, cross-perfil, cross-tenant, POST direto, cron sem key |

JSON: `relatorios-seguranca/SEC-04-resultados-runner.json`  
Resumo da última execução: **19 APROVADO · 0 FALHOU · 0 NÃO EXECUTADO**

---

## 6. Matriz de permissões (resumo)

| Origem ↓ / Destino → | `/admin/*` | `/monitor/*` | `/master/*` | Outro tenant |
|---|---|---|---|---|
| Anônimo | Negado (302 login) | Negado | Negado (302) | — |
| Aluno | Negado (redirect) | Negado | Negado | Sessão invalidada se Host ≠ tenant |
| Monitor | Negado (redirect) | Permitido (próprias rotas) | Negado | Sessão amarrada ao tenant |
| Admin escola | Permitido (+ matriz `AdminPermissionMatrix`) | N/A (redirect de papel) | Negado | Negado (`sessaoConfereComEscolaAtual`) |
| Master | N/A (banco master / impersonação à parte) | N/A | Permitido com `master_user_id` | Opera via conexões tenant explícitas no Master |

---

## 7. Vulnerabilidades e achados

| ID | Severidade | Achado | Evidência |
|---|---|---|---|
| V-01 | ~~Alta~~ **Mitigado** | Login Master (senha) sem rate limit — **corrigido** com arquivo + configs de security | `MasterAuthController` rate limit; reteste local 4ª falha já bloqueia |
| V-02 | ~~Média~~ **Mitigado** | Contador IP em path errado — agora `BASE_PATH/storage/logs/login_rate_limit.json` + migração one-shot do legado | `AuthController` helpers; reteste migração/bloqueio/flock |
| V-03 | ~~Média~~ **Mitigado (config)** | Sem `real_ip` CF — adicionado `docker/nginx/cloudflare-real-ip.conf` + include nos vhosts SSL; `getIpAddress()` só `REMOTE_ADDR` | Requer reload Nginx em produção |
| V-04 | **Baixa** | Migration `tipo` em `tentativas_login` (020) **não** aplicada em `educatudo_colag` | `SHOW COLUMNS` sem `tipo`; código já trata ausência |
| V-05 | ~~Média~~ **Mitigado (lab)** | Tabela OTP Master ausente — **aplicada** `2026_11_19_…_master.sql` no Docker | RL-07 APROVADO |
| V-06 | Informativo | `AuthMiddleware` sem enforcement de perfil | Depende de cada Controller |
| V-07 | Informativo | Logs de bloqueio/desbloqueio IP incompletos | Só JSON de contagem |

---

## 8. Riscos residuais

1. Contador IP compartilhado por todos atrás do mesmo NAT/proxy (sem `real_ip` CF).
2. Controllers admin de módulos que não estendem `AdminBaseController` podem esquecer checagem de `tipo`.
3. Timezone MySQL vs PHP pode distorcer a janela de `tentativas_login` se `created_at`/`NOW()` divergirem.
---

## 9. Recomendações (sem implementar nesta etapa)

1. Em produção: fazer deploy dos confs Nginx + `nginx -t && reload` para ativar V-03; atualizar faixas CF periodicamente (`https://www.cloudflare.com/ips/`).
2. Aplicar migration `020_tentativas_login_tipo_motivo` nos tenants legados.
3. Centralizar `requireMasterAuth` (trait/base) e exigir `master_user_id` de forma uniforme.
4. Opcional: mover contador IP para Redis para consistência multi-réplica.
5. Opcional: alinhar `Logger::getRealIpAddress` ao mesmo critério (só REMOTE_ADDR ou validar proxy).

---

## 10. Classificação consolidada dos controles SEC-04

| Controle | Status |
|---|---|
| Rate limit identificador admin | APROVADO |
| Rate limit identificador monitor | APROVADO (mesmo pipeline; login lab ok) |
| Rate limit IP admin/monitor | APROVADO (path canônico após V-02) |
| Uso de `max_login_attempts` / `lockout_duration` / `max_login_attempts_ip` no Master senha | APROVADO (após correção) |
| Proteção OTP Master (dinâmica) | APROVADO |
| Constantes/limites OTP (código) | APROVADO (PHPUnit) |
| IP sem confiar em header de cliente no login | APROVADO |
| Isolamento Admin A × Escola B | APROVADO |
| Admin ↛ Master | APROVADO |
| Monitor ↛ Admin | APROVADO |
| Usuário comum ↛ Admin | APROVADO |
| Master exige sessão Master | APROVADO |
| POST direto / cron sem key | APROVADO |

**SEC-04 corrigido?** Sim no código/config do repo (V-01…V-03/V-05). Ativar V-03 em produção exige reload do Nginx com o snippet CF.
