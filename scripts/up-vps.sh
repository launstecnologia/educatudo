#!/usr/bin/env bash
# EducaTudo — sobe stack Docker na VPS (Nginx + PHP-FPM + Redis).
# MySQL fica FORA do Docker (remoto). Estrutura esperada: raiz plataforma_educatudo/
#
# Uso:
#   ./scripts/up-vps.sh              # build + up
#   ./scripts/up-vps.sh --pull       # pull nginx/redis + build php
#   ./scripts/up-vps.sh --no-build   # só sobe containers
#   ./scripts/up-vps.sh --composer   # roda composer install no container
#
# Pré-requisitos: Docker + Docker Compose v2, backend/.env configurado

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKEND="$ROOT/backend"
COMPOSE_FILE="$ROOT/docker-compose.vps.yml"

DO_PULL=false
DO_BUILD=true
DO_COMPOSER=false

for arg in "$@"; do
  case "$arg" in
    --pull) DO_PULL=true ;;
    --no-build) DO_BUILD=false ;;
    --composer) DO_COMPOSER=true ;;
    -h|--help)
      sed -n '2,12p' "$0"
      exit 0
      ;;
    *)
      echo "Opção desconhecida: $arg (use --help)" >&2
      exit 1
      ;;
  esac
done

if ! command -v docker >/dev/null 2>&1; then
  echo "ERRO: Docker não encontrado." >&2
  exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
  echo "ERRO: docker compose (v2) não encontrado." >&2
  exit 1
fi

echo "==> EducaTudo — deploy VPS (MySQL remoto)"
echo "    Raiz: $ROOT"
echo "    Compose: docker-compose.vps.yml"

if [[ ! -d "$BACKEND" ]]; then
  echo "ERRO: pasta backend/ não encontrada. Clone o repo plataforma_educatudo completo." >&2
  exit 1
fi

mkdir -p "$BACKEND/storage/logs" "$BACKEND/storage/cache" "$BACKEND/storage/sessions"

if [[ ! -f "$BACKEND/.env" ]]; then
  if [[ -f "$BACKEND/.env.vps.example" ]]; then
    cp "$BACKEND/.env.vps.example" "$BACKEND/.env"
    echo "    Criado backend/.env a partir de .env.vps.example — EDITE DB_HOST, DB_* e MASTER_ENCRYPTION_KEY antes de produção."
  else
    echo "ERRO: backend/.env ausente. Copie backend/.env.vps.example → backend/.env" >&2
    exit 1
  fi
fi

if grep -q 'SEU_HOST_MYSQL' "$BACKEND/.env" 2>/dev/null; then
  echo "AVISO: backend/.env ainda contém SEU_HOST_MYSQL — configure o MySQL remoto." >&2
fi

if [[ ! -d "$BACKEND/vendor" ]] || [[ "$DO_COMPOSER" == true ]]; then
  echo "    composer install (one-off container)..."
  docker run --rm \
    -v "$BACKEND:/var/www/html" \
    -w /var/www/html \
    composer:2 \
    composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
fi

if [[ "$DO_PULL" == true ]]; then
  echo "    Pull imagens base (nginx, redis)..."
  docker compose -f "$COMPOSE_FILE" pull nginx redis
fi

COMPOSE_UP=(docker compose -f "$COMPOSE_FILE")
if [[ "$DO_BUILD" == true ]]; then
  echo "    Build imagem PHP (educatudo-php:latest)..."
  "${COMPOSE_UP[@]}" build php
fi

echo "    Subindo containers..."
"${COMPOSE_UP[@]}" up -d

echo ""
echo "    Status:"
"${COMPOSE_UP[@]}" ps

echo ""
echo "==> Stack no ar"
echo "    Nginx :${NGINX_HTTP_PORT:-80} → backend/public/"
echo "    Redis :${REDIS_PUBLISH_PORT:-6379}"
echo "    MySQL : remoto (backend/.env DB_HOST)"
echo ""
echo "Próximos passos:"
echo "  1. Aponte DNS (*.educatudo.com, master) para esta VPS"
echo "  2. Nginx host + cert wildcard: docs/DEPLOY-DOMINIOS.md"
echo "  3. Migrations master: painel /master/migrations ou php backend/scripts/run_migrations.php"
echo "  4. Teste: curl -H 'Host: master.SEUDOMINIO' http://127.0.0.1/master/"
