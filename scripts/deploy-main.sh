#!/usr/bin/env bash
# Deploy de código (main): git pull + composer se necessário + restart do PHP.
# Não recria Nginx/Redis nem aplica compose da VPS — infra fica no servidor.
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
PHP_CONTAINER="${DEPLOY_PHP_CONTAINER:-php_app_educatudo}"
PRESERVE_FILES=(
  docker-compose.yml
  docker-compose.vps.yml
  docker-compose.vps.ssl-origin.yml
  docker-compose.vps.ssl.yml
)

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

if [[ ! -f backend/.env ]]; then
  echo "ERRO: backend/.env ausente no servidor." >&2
  exit 1
fi

if [[ ! -f ssl/cloudflare-origin.pem || ! -f ssl/cloudflare-origin.key ]]; then
  echo "ERRO: certificado Cloudflare Origin ausente em ssl/." >&2
  echo "Esperado: ssl/cloudflare-origin.pem e ssl/cloudflare-origin.key" >&2
  exit 1
fi

# Compose/.env do servidor nao bloqueiam e nao sao sobrescritos pelo deploy de codigo.
GIT_IGNORE_LOCAL=(
  ':!backend/.env'
  ':!docker-compose.yml'
  ':!docker-compose.vps.yml'
  ':!docker-compose.vps.ssl-origin.yml'
  ':!docker-compose.vps.ssl.yml'
)

if ! git diff --quiet -- . "${GIT_IGNORE_LOCAL[@]}"; then
  echo "ERRO: existem mudancas rastreadas nao commitadas no servidor." >&2
  git status --short
  exit 1
fi

if ! git diff --cached --quiet -- . "${GIT_IGNORE_LOCAL[@]}"; then
  echo "ERRO: existem mudancas staged no servidor." >&2
  git status --short
  exit 1
fi

preserve_dir=""
restore_server_config() {
  if [[ -z "${preserve_dir:-}" || ! -d "$preserve_dir" ]]; then
    return
  fi
  local f
  for f in "${PRESERVE_FILES[@]}"; do
    if [[ -f "$preserve_dir/$f" ]]; then
      cp "$preserve_dir/$f" "$ROOT/$f"
    fi
  done
  rm -rf "$preserve_dir"
  preserve_dir=""
}
trap restore_server_config EXIT

preserve_dir="$(mktemp -d)"
for f in "${PRESERVE_FILES[@]}"; do
  if [[ -f "$f" ]]; then
    cp "$f" "$preserve_dir/$f"
    git checkout -- "$f" 2>/dev/null || true
  fi
done

echo "==> Atualizando codigo"
git fetch "$REMOTE" "$BRANCH"

current_branch="$(git branch --show-current)"
if [[ "$current_branch" != "$BRANCH" ]]; then
  echo "    Trocando branch $current_branch -> $BRANCH"
  git switch "$BRANCH"
fi

git pull --ff-only "$REMOTE" "$BRANCH"
restore_server_config

mkdir -p backend/storage/logs backend/storage/cache backend/storage/sessions backend/uploads

if [[ ! -f backend/vendor/autoload.php || backend/composer.lock -nt backend/vendor/autoload.php ]]; then
  echo "==> Instalando dependencias PHP"
  docker run --rm \
    -v "$ROOT/backend:/var/www/html" \
    -w /var/www/html \
    composer:2 \
    composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
fi

if ! docker inspect "$PHP_CONTAINER" >/dev/null 2>&1; then
  echo "ERRO: container PHP $PHP_CONTAINER nao encontrado. Infra da VPS nao e recriada neste deploy." >&2
  exit 1
fi

echo "==> Reiniciando PHP para limpar opcache (sem recriar Nginx/infra)"
docker restart "$PHP_CONTAINER"

echo "==> Ajustando permissoes de storage/uploads"
if command -v sudo >/dev/null 2>&1; then
  sudo chown -R ubuntu:www-data backend/storage backend/uploads 2>/dev/null || true
  sudo chmod -R ug+rwX backend/storage backend/uploads 2>/dev/null || true
else
  chmod -R ug+rwX backend/storage backend/uploads 2>/dev/null || true
fi

echo "==> Health check local"
status="000"
for attempt in 1 2 3 4 5 6; do
  status="$(curl -k -s -o /tmp/educatudo-deploy-health.html -w "%{http_code}" -H "Host: $HOST_HEADER" "https://127.0.0.1$HEALTH_PATH" || true)"
  case "$status" in
    200|302)
      echo "    OK: $HEALTH_PATH retornou $status"
      break
      ;;
    *)
      echo "    Tentativa $attempt/6 retornou $status; aguardando PHP/Nginx estabilizar..."
      sleep 5
      ;;
  esac
done

case "$status" in
  200|302) ;;
  *)
    echo "ERRO: health check retornou $status" >&2
    docker logs --tail=120 "$PHP_CONTAINER" >&2 || true
    exit 1
    ;;
esac

echo "==> Deploy concluido"
git log --oneline --decorate -1
