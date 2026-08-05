#!/usr/bin/env bash
# EducaTudo — setup local (master.localhost + colag.localhost)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKEND="$ROOT/backend"

echo "==> EducaTudo — init local"

if [[ ! -f "$BACKEND/.env" ]]; then
  echo "    Copiando backend/.env.docker.example → backend/.env"
  cp "$BACKEND/.env.docker.example" "$BACKEND/.env"
elif grep -qE '^DB_HOST=(72\.|31\.|[^m])' "$BACKEND/.env" 2>/dev/null && ! grep -q '^DB_HOST=mysql' "$BACKEND/.env"; then
  backup="$BACKEND/.env.backup.$(date +%Y%m%d_%H%M%S)"
  echo "    .env aponta para servidor remoto — backup em $(basename "$backup")"
  cp "$BACKEND/.env" "$backup"
  cp "$BACKEND/.env.docker.example" "$BACKEND/.env"
  echo "    backend/.env reconfigurado para Docker local"
fi

mkdir -p "$BACKEND/storage/logs" "$BACKEND/storage/cache" "$BACKEND/storage/sessions"

if [[ -f "$BACKEND/.env" ]] && grep -q '^DB_HOST=mysql' "$BACKEND/.env"; then
  patch_env() {
    local key="$1" val="$2"
    if grep -q "^${key}=" "$BACKEND/.env"; then
      sed -i.bak "s|^${key}=.*|${key}=${val}|" "$BACKEND/.env"
    else
      echo "${key}=${val}" >> "$BACKEND/.env"
    fi
  }
  patch_env "MASTER_DOMAIN" "master.localhost"
  patch_env "APP_URL" "http://master.localhost"
  patch_env "SESSION_DOMAIN" ".localhost"
  patch_env "SESSION_SECURE" "false"
  patch_env "REDIS_HOST" "redis"
  patch_env "REDIS_PORT" "6379"
  patch_env "SCHOOL_CODE" "colag"
  patch_env "MEDIA_TENANT_PREFIX" "true"
  rm -f "$BACKEND/.env.bak"
fi

if [[ ! -d "$BACKEND/vendor" ]]; then
  echo "    composer install..."
  (cd "$BACKEND" && composer install --no-interaction)
fi

echo "    Subindo Docker (Nginx :80 + PHP-FPM + MySQL + Redis)..."
docker compose -f "$ROOT/docker-compose.yml" up -d --build

echo "    Aguardando MySQL..."
for i in {1..60}; do
  if docker compose -f "$ROOT/docker-compose.yml" exec -T mysql mysqladmin ping -h localhost -uroot -proot --silent 2>/dev/null; then
    break
  fi
  sleep 2
  if [[ $i -eq 60 ]]; then
    echo "ERRO: MySQL não respondeu a tempo."
    exit 1
  fi
done

echo "    Inicializando banco master + escola Colag..."
docker compose -f "$ROOT/docker-compose.yml" exec -T php php scripts/init_local_multitenant.php --force-school --skip-master-migrations --skip-tenant-migrations

echo "    Importando schema base tenant (educa_core.sql — pode levar 1–2 min)..."
docker compose -f "$ROOT/docker-compose.yml" exec -T mysql sh -c "mysql -uroot -proot educatudo_colag" < "$BACKEND/database/educa_core.sql"

echo "    Rodando migrations tenant..."
docker compose -f "$ROOT/docker-compose.yml" exec -T php php scripts/init_local_multitenant.php --tenant-migrations-only

echo "    Patches de schema usados pelos E2E (idempotentes)..."
docker compose -f "$ROOT/docker-compose.yml" exec -T mysql mysql -uroot -proot educatudo_colag \
  < "$BACKEND/database/migrations/043_responsaveis_multiplos_alunos_campos.sql" 2>/dev/null || true
docker compose -f "$ROOT/docker-compose.yml" exec -T mysql mysql -uroot -proot educatudo_colag \
  < "$BACKEND/database/migrations/2026_05_07_jornadas_ano_letivo_bimestre.sql" 2>/dev/null || true
docker compose -f "$ROOT/docker-compose.yml" exec -T mysql mysql -uroot -proot educatudo_colag -e \
  "ALTER TABLE jornadas ADD COLUMN avaliativo TINYINT(1) NOT NULL DEFAULT 1 AFTER bimestre;" 2>/dev/null || true

echo "    Criando usuários de teste..."
docker compose -f "$ROOT/docker-compose.yml" exec -T php php scripts/seed_local_usuarios_teste.php

echo ""
echo "Master: http://master.localhost/master"
echo "        admin@local.educatudo / Teste@123"
echo ""
echo "Colag:  http://colag.localhost"
echo "        Admin: admin@colag.local / Teste@123"
echo "        Aluno: aluno.teste / Teste@123"
echo ""
echo "MySQL: 127.0.0.1:3307 | educatudo_master + educatudo_colag | root/root"
echo ""
echo "Se *.localhost não resolver, adicione ao /etc/hosts:"
echo "  127.0.0.1 master.localhost colag.localhost"
