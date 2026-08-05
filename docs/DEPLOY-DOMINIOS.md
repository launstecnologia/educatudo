# Deploy de domínios multi-tenant — EducaTudo

Guia para produção com **wildcard DNS na Cloudflare** e **Let's Encrypt na origem**.

## Visão geral

| Camada | Responsável | O quê |
|--------|-------------|--------|
| Master (PHP) | Automático | Gera `{slug}.educatudo.com`, grava em `escolas.dominio`, verifica HTTPS |
| Cloudflare | Configuração única | `*` e `master` → IP do servidor |
| Servidor | Script + nginx | Cert wildcard + vhost `*.educatudo.com` |

O Master **não** cria registros DNS por escola (wildcard cobre todos os subdomínios).

## 1. Variáveis de ambiente (`.env` do backend)

```env
MULTI_TENANT=true
MASTER_DOMAIN=master.educatudo.com
TENANT_BASE_DOMAIN=educatudo.com
DOMINIO_WILDCARD_HABILITADO=true
SESSION_DOMAIN=.educatudo.com
SESSION_SECURE=true

SSL_CERT_PATH=/etc/letsencrypt/live/educatudo.com/fullchain.pem
SSL_KEY_PATH=/etc/letsencrypt/live/educatudo.com/privkey.pem

DOMINIO_VERIFICAR_CRON_KEY=uma-chave-secreta-longa
```

## 2. Cloudflare (uma vez)

1. Zona `educatudo.com` na Cloudflare.
2. Registros DNS (proxied laranja, se usar CDN):
   - `A` ou `CNAME` `@` → IP do servidor
   - `A` ou `CNAME` `*` → IP do servidor
   - `A` ou `CNAME` `master` → IP do servidor
3. SSL/TLS → **Full (strict)** (origem precisa de certificado válido).

Token API (só no servidor, para certbot — **não** no `.env` do PHP):

1. Cloudflare → My Profile → API Tokens → Create Token
2. Permissão: Zone → DNS → Edit (zona `educatudo.com`)
3. Salvar em `/etc/letsencrypt/cloudflare.ini`:

```ini
dns_cloudflare_api_token = SEU_TOKEN
```

```bash
chmod 600 /etc/letsencrypt/cloudflare.ini
```

## 3. Certificado wildcard (Let's Encrypt)

No servidor:

```bash
chmod +x scripts/provision-ssl-wildcard.sh
./scripts/provision-ssl-wildcard.sh educatudo.com
```

Teste sem emitir:

```bash
./scripts/provision-ssl-wildcard.sh educatudo.com --dry-run
```

Renovação automática (crontab root):

```cron
0 3 * * * certbot renew --quiet && nginx -s reload
```

## 4. Nginx

Copie/adapte [`docker/nginx/wildcard-prod.conf`](../docker/nginx/wildcard-prod.conf):

- `root` → caminho real de `backend/public`
- `fastcgi_pass` → socket PHP do servidor
- Paths `ssl_certificate` → `/etc/letsencrypt/live/educatudo.com/`

```bash
nginx -t && nginx -s reload
```

## 5. Migration master

Execute no painel Master (`/master/migrations`) ou via script:

`backend/database/migrations/2026_08_05_escolas_dominio_provisionamento_master.sql`

Adiciona colunas `dns_status`, `ssl_status`, `ssl_verificado_em`, `ssl_expira_em`, `dominio_ultimo_erro` em `escolas`.

## 6. Uso no Master

1. **Nova escola** → informe slug; domínio é preenchido como `{slug}.educatudo.com`.
2. **Editar escola** → seção **Status do domínio** mostra DNS (wildcard) e HTTPS.
3. **Verificar HTTPS agora** → testa `https://{dominio}/` e atualiza status.
4. Listagem de escolas → coluna **HTTPS**.

## 7. Cron de verificação

CLI (recomendado):

```bash
php backend/cron/verificar_dominios_escolas.php
php backend/cron/verificar_dominios_escolas.php 30   # limite
```

HTTP (alternativa):

```
GET https://master.educatudo.com/master/escolas/verificar-dominios-cron?key=DOMINIO_VERIFICAR_CRON_KEY
```

Crontab exemplo (a cada 6 h):

```cron
0 0,6,12,18 * * * /usr/bin/php /caminho/backend/cron/verificar_dominios_escolas.php >> /caminho/backend/storage/logs/cron_verificar_dominios.log 2>&1
```

## 8. Desenvolvimento local

Sem Cloudflare/certbot:

```env
TENANT_BASE_DOMAIN=localhost
# ou deixe vazio — Master gera {slug}.localhost
```

Use `./scripts/init-local.sh` e `/etc/hosts` ou `*.localhost` no nginx local.

## Solução de problemas

| Sintoma | Causa provável |
|---------|----------------|
| Master: DNS OK, HTTPS pendente | Cert wildcard ainda não emitido ou nginx sem vhost wildcard |
| HTTPS erro após criar escola | Propagação DNS ou Cloudflare em Flexible (use Full strict) |
| Tenant não encontrado | `escolas.dominio` diferente do `Host` da requisição |
| Verificação timeout | Firewall bloqueando saída HTTPS do servidor PHP |

Logs cron: `backend/storage/logs/cron_verificar_dominios.log`
