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

2. **Configurar `.env`:**

```bash
cp backend/.env.vps.example backend/.env
nano backend/.env   # DB_HOST, credenciais, MASTER_ENCRYPTION_KEY
```

3. **Liberar MySQL remoto** para o IP da VPS (firewall / security group).

4. **Subir containers:**

```bash
chmod +x scripts/up-vps.sh
./scripts/up-vps.sh --pull --composer
```

5. **DNS + SSL:** ver [DEPLOY-DOMINIOS.md](./DEPLOY-DOMINIOS.md).

6. **Migrations:** `/master/migrations` ou `docker compose -f docker-compose.vps.yml exec php php scripts/run_migrations.php`.

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
