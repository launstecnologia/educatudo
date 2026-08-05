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
echo "    PHP mem_limit: ${PHP_MEM_LIMIT:-4g} (override com PHP_MEM_LIMIT no .env da raiz)"

# OPcache com validate_timestamps=0: após git pull sem rebuild, reinicie o PHP-FPM
# para carregar código novo: docker compose -f docker-compose.vps.yml restart php

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
echo "    PHP   : mem_limit=${PHP_MEM_LIMIT:-4g}, FPM max_children=50, OPcache+Redis"

if docker exec php_app_educatudo php -m 2>/dev/null | grep -qi '^redis$'; then
  echo "    Redis PHP: OK (extensão carregada)"
else
  echo "    AVISO: extensão redis ausente no PHP — rode com build: ./scripts/up-vps.sh (sem --no-build)"
fi

echo ""
echo "Próximos passos:"
echo "  1. Diagnóstico: ./scripts/diagnostico-vps.sh"
echo "  2. DNS (*.educatudo.com, master) → IP desta VPS; firewall 80/443 aberto"
echo "  3. Cloudflare sem cert ainda: SSL/TLS → Flexible"
echo "  4. Cert wildcard + HTTPS: ./scripts/setup-vps-ssl-docker.sh educatudo.com"
echo "  5. Teste: curl -H 'Host: master.educatudo.com' http://127.0.0.1/master/"
echo "  6. Após git pull (código): docker compose -f docker-compose.vps.yml restart php"
