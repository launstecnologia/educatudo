# Deploy na VPS (MySQL remoto)

Stack Docker **sem MySQL local** — banco na VPS remota ou serviço gerenciado.

## Estrutura esperada

Clone/cópia completa de `plataforma_educatudo/` na VPS:

```
plataforma_educatudo/
├── backend/          ← submódulo educatudo_oficial
├── docker/
├── docker-compose.vps.yml
└── scripts/up-vps.sh
```

## Passo a passo

1. **Instalar Docker** (Engine + Compose plugin) na VPS.

2. **Clonar o repo** e **copiar o `.env` de produção** para `backend/.env` (mesma `MASTER_ENCRYPTION_KEY`). Ajuste só se necessário:
   - `REDIS_HOST=redis`
   - `REDIS_PORT=6379`

3. **Liberar MySQL remoto** para o IP da VPS (firewall / security group).

4. **Subir containers:**

```bash
chmod +x scripts/up-vps.sh scripts/diagnostico-vps.sh
./scripts/up-vps.sh --pull --composer
```

5. **Diagnóstico (obrigatório se o site só carrega):**

```bash
./scripts/diagnostico-vps.sh
```

6. **DNS + firewall:** `master` e `*` devem chegar na VPS; portas **80** e **443** abertas (UFW + painel Hostinger).

7. **SSL:**
   - **Rápido (teste):** Cloudflare → SSL/TLS → **Flexible** (origem HTTP na 80).
   - **Produção:** `./scripts/setup-vps-ssl-docker.sh educatudo.com` + Cloudflare **Full (strict)**.

8. **Migrations:** só pendentes — banco remoto já em produção.

## Comandos úteis

```bash
# Logs
docker compose -f docker-compose.vps.yml logs -f php nginx

# Parar
docker compose -f docker-compose.vps.yml down

# Rebuild PHP após mudança no Dockerfile
./scripts/up-vps.sh --pull
```

## Diferença do ambiente local

| Item | Local (`init-local.sh`) | VPS (`up-vps.sh`) |
|------|-------------------------|-------------------|
| Compose | `docker-compose.yml` | `docker-compose.vps.yml` |
| MySQL | Container `mysql` | Remoto (`DB_HOST`) |
| Redis | Container | Container |
| `.env` template | `.env.docker.example` | `.env.vps.example` |
