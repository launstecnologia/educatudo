#!/usr/bin/env bash
# Deploy de produção a partir da branch main.
#
# Uso no servidor:
#   cd /opt/educatudo
#   bash scripts/deploy-main.sh
#
# Requer arquivos locais fora do Git:
#   backend/.env
#   ssl/cloudflare-origin.pem
#   ssl/cloudflare-origin.key

set -euo pipefail

ROOT="${DEPLOY_ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
BRANCH="${DEPLOY_BRANCH:-main}"
REMOTE="${DEPLOY_REMOTE:-origin}"
HOST_HEADER="${DEPLOY_HEALTH_HOST:-master.educatudo.com}"
HEALTH_PATH="${DEPLOY_HEALTH_PATH:-/master}"

cd "$ROOT"

echo "==> EducaTudo deploy"
echo "    Root:   $ROOT"
echo "    Branch: $BRANCH"
echo "    Remote: $REMOTE"

if ! command -v git >/dev/null 2>&1; then
  echo "ERRO: git nao encontrado." >&2
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "ERRO: docker nao encontrado." >&2
  exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
  echo "ERRO: docker compose v2 nao encontrado." >&2
  exit 1
fi

if [[ ! -f backend/.env ]]; then
  echo "ERRO: backend/.env ausente no servidor." >&2
  exit 1
fi

if [[ ! -f ssl/cloudflare-origin.pem || ! -f ssl/cloudflare-origin.key ]]; then
  echo "ERRO: certificado Cloudflare Origin ausente em ssl/." >&2
  echo "Esperado: ssl/cloudflare-origin.pem e ssl/cloudflare-origin.key" >&2
  exit 1
fi

if ! git diff --quiet -- . ':!backend/.env'; then
  echo "ERRO: existem mudancas rastreadas nao commitadas no servidor." >&2
  git status --short
  exit 1
fi

if ! git diff --cached --quiet -- . ':!backend/.env'; then
  echo "ERRO: existem mudancas staged no servidor." >&2
  git status --short
  exit 1
fi

echo "==> Atualizando codigo"
git fetch "$REMOTE" "$BRANCH"

current_branch="$(git branch --show-current)"
if [[ "$current_branch" != "$BRANCH" ]]; then
  echo "    Trocando branch $current_branch -> $BRANCH"
  git switch "$BRANCH"
fi

git pull --ff-only "$REMOTE" "$BRANCH"

mkdir -p backend/storage/logs backend/storage/cache backend/storage/sessions backend/uploads

if [[ ! -f backend/vendor/autoload.php || backend/composer.lock -nt backend/vendor/autoload.php ]]; then
  echo "==> Instalando dependencias PHP"
  docker run --rm \
    -v "$ROOT/backend:/var/www/html" \
    -w /var/www/html \
    composer:2 \
    composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
fi

echo "==> Subindo containers com SSL Origin"
docker compose -f docker-compose.vps.yml -f docker-compose.vps.ssl-origin.yml pull nginx redis || true
docker compose -f docker-compose.vps.yml -f docker-compose.vps.ssl-origin.yml build php
docker compose -f docker-compose.vps.yml -f docker-compose.vps.ssl-origin.yml up -d --remove-orphans

echo "==> Ajustando permissoes de storage/uploads"
if command -v sudo >/dev/null 2>&1; then
  sudo chown -R ubuntu:www-data backend/storage backend/uploads 2>/dev/null || true
  sudo chmod -R ug+rwX backend/storage backend/uploads 2>/dev/null || true
else
  chmod -R ug+rwX backend/storage backend/uploads 2>/dev/null || true
fi

echo "==> Health check local"
status="$(curl -k -s -o /tmp/educatudo-deploy-health.html -w "%{http_code}" -H "Host: $HOST_HEADER" "https://127.0.0.1$HEALTH_PATH" || true)"
case "$status" in
  200|302)
    echo "    OK: $HEALTH_PATH retornou $status"
    ;;
  *)
    echo "ERRO: health check retornou $status" >&2
    docker compose -f docker-compose.vps.yml -f docker-compose.vps.ssl-origin.yml logs --tail=120 php >&2 || true
    exit 1
    ;;
esac

echo "==> Deploy concluido"
git log --oneline --decorate -1
