#!/usr/bin/env bash
# Deploy de código (main): git fetch + reset --hard + composer se necessário + restart do PHP.
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
#
# Preserva na VPS (não sobrescreve):
#   docker-compose*.yml, docker/nginx/nginx.conf, docker/php/php.ini

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
  docker/nginx/nginx.conf
  docker/php/php.ini
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

# SEC-10: /master só responde 200/302 no host MASTER_DOMAIN. Sem isso o health check
# falha com 404 e o Actions marca o deploy como vermelho (o git reset já ocorreu).
master_domain_env="$(
  grep -E '^[[:space:]]*MASTER_DOMAIN[[:space:]]*=' backend/.env \
    | tail -n 1 \
    | sed -E 's/^[[:space:]]*MASTER_DOMAIN[[:space:]]*=[[:space:]]*//; s/[[:space:]]+#.*$//; s/^["'\'']//; s/["'\'']$//; s/[[:space:]]*$//' \
    | tr '[:upper:]' '[:lower:]'
)"
if [[ -z "$master_domain_env" ]]; then
  echo "ERRO: MASTER_DOMAIN ausente ou vazio em backend/.env." >&2
  echo "Defina MASTER_DOMAIN=master.educatudo.com (sem comentario na mesma linha) e reexecute o Deploy main." >&2
  exit 1
fi
if [[ -z "${DEPLOY_HEALTH_HOST:-}" ]]; then
  HOST_HEADER="$master_domain_env"
fi
echo "    MASTER_DOMAIN: $master_domain_env"
echo "    Health Host:   $HOST_HEADER"

if [[ ! -f ssl/cloudflare-origin.pem || ! -f ssl/cloudflare-origin.key ]]; then
  echo "ERRO: certificado Cloudflare Origin ausente em ssl/." >&2
  echo "Esperado: ssl/cloudflare-origin.pem e ssl/cloudflare-origin.key" >&2
  exit 1
fi

preserve_dir=""
restore_server_config() {
  if [[ -z "${preserve_dir:-}" || ! -d "$preserve_dir" ]]; then
    return
  fi
  local f base
  for f in "${PRESERVE_FILES[@]}"; do
    base="$(basename "$f")"
    if [[ -f "$preserve_dir/$base" ]]; then
      mkdir -p "$ROOT/$(dirname "$f")"
      cp "$preserve_dir/$base" "$ROOT/$f"
      echo "    restaurado: $f"
    fi
  done
  rm -rf "$preserve_dir"
  preserve_dir=""
}
trap restore_server_config EXIT

echo "==> Preservando config local da VPS"
preserve_dir="$(mktemp -d /tmp/educatudo-deploy-preserve-XXXXXX)"
for f in "${PRESERVE_FILES[@]}"; do
  if [[ -f "$f" ]]; then
    cp "$f" "$preserve_dir/$(basename "$f")"
    echo "    salvo: $f"
  fi
done

# Backup de hotfixes de app (nao-preservados) antes do hard reset
backup_dir="$(mktemp -d /tmp/educatudo-deploy-dirty-XXXXXX)"
backed_up=0
while IFS= read -r f; do
  [[ -n "$f" ]] || continue
  skip=0
  for p in "${PRESERVE_FILES[@]}"; do
    [[ "$f" == "$p" || "$f" == "backend/.env" ]] && skip=1 && break
  done
  ((skip)) && continue
  if [[ -f "$f" ]]; then
    mkdir -p "$backup_dir/$(dirname "$f")"
    cp "$f" "$backup_dir/$f"
    echo "    backup hotfix: $f"
    backed_up=1
  fi
done < <(git diff --name-only HEAD 2>/dev/null || true)
if ((backed_up)); then
  echo "    backup em $backup_dir"
else
  rm -rf "$backup_dir"
fi

echo "==> Atualizando codigo ($REMOTE/$BRANCH)"
git fetch "$REMOTE" "$BRANCH"

current_branch="$(git branch --show-current)"
if [[ "$current_branch" != "$BRANCH" ]]; then
  echo "    Trocando branch $current_branch -> $BRANCH"
  git switch "$BRANCH"
fi

if ! git reset --hard "$REMOTE/$BRANCH"; then
  echo "ERRO: git reset --hard falhou. Verifique permissao de escrita no repo." >&2
  echo "Sugestao: sudo chown -R ubuntu:ubuntu /opt/educatudo" >&2
  echo "          (mantenha storage/uploads acessiveis ao www-data depois)" >&2
  ls -la backend/app/Painel/Auth/Controllers/AuthController.php 2>/dev/null || true
  exit 1
fi

echo "==> Restaurando config local da VPS"
restore_server_config
trap - EXIT

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

echo "==> Recriando PHP para limpar opcache e recarregar env_file (MASTER_DOMAIN)"
# docker restart NAO relê env_file; sem recreate o processo pode ficar com env antigo.
# O bootstrap tambem le backend/.env do disco — recreate cobre os dois caminhos.
if docker compose version >/dev/null 2>&1; then
  compose_files=()
  [[ -f docker-compose.yml ]] && compose_files+=(-f docker-compose.yml)
  [[ -f docker-compose.vps.yml ]] && compose_files+=(-f docker-compose.vps.yml)
  [[ -f docker-compose.vps.ssl-origin.yml ]] && compose_files+=(-f docker-compose.vps.ssl-origin.yml)
  [[ -f docker-compose.vps.ssl.yml ]] && compose_files+=(-f docker-compose.vps.ssl.yml)
  if ((${#compose_files[@]})) && docker compose "${compose_files[@]}" ps -q php >/dev/null 2>&1; then
    docker compose "${compose_files[@]}" up -d --force-recreate --no-deps php
  else
    docker restart "$PHP_CONTAINER"
  fi
else
  docker restart "$PHP_CONTAINER"
fi

echo "==> Conferindo MASTER_DOMAIN visivel no container"
# Aguarda o PHP-FPM aceitar conexoes apos recreate (Actions falhava no health cedo demais).
for _wait in 1 2 3 4 5 6 7 8 9 10; do
  if docker exec "$PHP_CONTAINER" php -v >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
docker exec "$PHP_CONTAINER" php -r '
$f = "/var/www/html/.env";
if (!is_file($f)) { fwrite(STDERR, "ERRO: .env ausente no container\n"); exit(1); }
$master = "";
foreach (file($f, FILE_IGNORE_NEW_LINES) ?: [] as $i => $line) {
  $line = trim((string) $line);
  if ($i === 0) { $line = preg_replace("/^\xEF\xBB\xBF/", "", $line) ?? $line; }
  if ($line === "" || $line[0] === "#") { continue; }
  if (preg_match("/^MASTER_DOMAIN\s*=\s*(.+)$/i", $line, $m)) {
    $v = trim($m[1]);
    if ($v !== "" && ($v[0] ?? "") !== "\"" && ($v[0] ?? "") !== "'\''") {
      $hash = strpos($v, "#");
      if ($hash !== false) { $v = trim(substr($v, 0, $hash)); }
    }
    $v = trim($v, " \t\"'\''");
    if ($v !== "") { $master = strtolower($v); }
  }
}
$proc = getenv("MASTER_DOMAIN");
echo "    arquivo: " . ($master !== "" ? $master : "(vazio)") . PHP_EOL;
echo "    getenv:  " . (is_string($proc) && trim($proc) !== "" ? strtolower(trim($proc)) : "(vazio)") . PHP_EOL;
if ($master === "") { fwrite(STDERR, "ERRO: MASTER_DOMAIN vazio no .env montado no PHP\n"); exit(1); }
'
# Nginx precisa ver o upstream PHP de novo apos recreate.
sleep 3

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
    echo "ERRO: health check retornou $status (Host: $HOST_HEADER$HEALTH_PATH)" >&2
    if [[ "$status" == "404" ]]; then
      echo "Dica SEC-10: 404 em /master costuma significar que o PHP nao ve MASTER_DOMAIN=$HOST_HEADER." >&2
      echo "Confira: grep MASTER_DOMAIN backend/.env && docker exec $PHP_CONTAINER php -r \"echo getenv('MASTER_DOMAIN')?:'(vazio)';\"" >&2
    fi
    if [[ -f /tmp/educatudo-deploy-health.html ]]; then
      echo "---- corpo (primeiras linhas) ----" >&2
      head -n 20 /tmp/educatudo-deploy-health.html >&2 || true
      echo "---- fim ----" >&2
    fi
    docker logs --tail=120 "$PHP_CONTAINER" >&2 || true
    exit 1
    ;;
esac

echo "==> Deploy concluido"
git log --oneline --decorate -1
echo "    (compose/php.ini/nginx locais preservados; status M neles e esperado)"
