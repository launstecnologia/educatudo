# Estrutura backend (docroot seguro)

Instância PHP com **docroot restrito a `public/`**. Código sensível fica fora do alcance HTTP.

```
backend/                          ← Plataforma PHP (= /var/www/html no Docker)
│
├── app/                          ← Código (ACESSO RESTRITO via Nginx)
├── bootstrap/
│   └── app.php                   ← Bootstrap (métricas, autoload, App)
├── config/                       ← Rotas, app.php, monitoring
├── public/                       ← ÚNICO docroot web
│   ├── index.php                 ← Front controller HTTP
│   ├── health.php
│   ├── .htaccess
│   ├── static/                   ← CSS/JS (URLs legadas /public/static/...)
│   └── uploads/
├── storage/                      ← Runtime (bloqueado no Nginx)
├── database/migrations/
├── cron/
├── vendor/
└── .env
```

## Docker local (workspace)

```
docker-compose.yml                ← nginx:8000 + php-fpm + mysql + redis
docker/nginx/default.conf         ← root /var/www/html/public
```

```bash
docker compose up -d --build
```

Acesse: http://master.localhost (master) · http://colag.localhost (Colag)

## Produção (Nginx / aaPanel)

Configure o **document root** do site para:

```
/caminho/do/projeto/public
```

**Não** aponte para a raiz do repo (`backend/` ou equivalente).

Referência de vhost: `docker/nginx/VHOST.example.conf` (na raiz do workspace).

## Compatibilidade de URLs

- `/public/static/*` → alias Nginx para `public/static/*`
- `/public/uploads/*` e `/uploads/*` → `public/uploads/`
- `/storage/chat/` e `/storage/images/` → aliases controlados
- Demais paths em `/storage/` → bloqueados
- **`index.php` na raiz do backend** → retorna 403 (docroot incorreto)

## Crons e CLI

Scripts rodam pela raiz do backend, não via HTTP:

```bash
php backend/cron/nome_do_script.php
php backend/scripts/run_migrations.php
```

## Entrada HTTP

```
Request → public/index.php → bootstrap/app.php → App::run()
```
