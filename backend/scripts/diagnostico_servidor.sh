#!/usr/bin/env bash
# EducaTudo — diagnóstico rápido do servidor de arquivos (somente leitura).
# Uso no servidor de produção:
#   cd /www/wwwroot/master.educatudo.com   # ou o path do deploy
#   bash scripts/diagnostico_servidor.sh
#
# Opcional: APP_ROOT=/caminho/do/deploy bash scripts/diagnostico_servidor.sh

set -u

APP_ROOT="${APP_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
TS="$(date '+%Y-%m-%d %H:%M:%S %Z')"

hr() { printf '\n%s\n' "────────────────────────────────────────"; }
section() { hr; printf '▶ %s\n' "$1"; }

warn() { printf '⚠ %s\n' "$1"; }
ok() { printf '✓ %s\n' "$1"; }
fail() { printf '✗ %s\n' "$1"; }

section "EducaTudo — diagnóstico do servidor ($TS)"
printf 'APP_ROOT=%s\n' "$APP_ROOT"

section "Sistema (CPU / memória / load)"
if command -v uptime >/dev/null 2>&1; then uptime; fi
if command -v free >/dev/null 2>&1; then free -h; fi
if command -v vmstat >/dev/null 2>&1; then vmstat 1 3; fi

section "Disco"
if command -v df >/dev/null 2>&1; then df -hT; fi
if [ -d "$APP_ROOT/storage" ]; then
  printf '\nTamanho storage/:\n'
  du -sh "$APP_ROOT/storage"/* 2>/dev/null | sort -hr | head -15
fi

section "Processos (top CPU / memória)"
if command -v ps >/dev/null 2>&1; then
  printf '\nTop CPU:\n'
  ps aux --sort=-%cpu 2>/dev/null | head -8 || ps aux | head -8
  printf '\nTop memória:\n'
  ps aux --sort=-%mem 2>/dev/null | head -8 || true
fi

section "PHP-FPM"
if command -v systemctl >/dev/null 2>&1; then
  for svc in php-fpm php8.2-fpm php8.1-fpm php8.0-fpm; do
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
      ok "Serviço ativo: $svc"
      systemctl status "$svc" --no-pager -l 2>/dev/null | head -12 || true
    fi
  done
fi
if [ -S /tmp/php-cgi.sock ] || [ -S /var/run/php/php-fpm.sock ]; then
  ok "Socket PHP-FPM encontrado"
else
  warn "Socket PHP-FPM padrão não encontrado (pode ser config customizada)"
fi

section "Web server"
if command -v systemctl >/dev/null 2>&1; then
  for svc in nginx apache2 httpd; do
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
      ok "Serviço ativo: $svc"
    fi
  done
fi

section "Redis"
if command -v redis-cli >/dev/null 2>&1; then
  if redis-cli ping 2>/dev/null | grep -q PONG; then
    ok "Redis respondeu PONG"
    redis-cli INFO stats 2>/dev/null | grep -E '^(total_connections_received|instantaneous_ops|used_memory_human|connected_clients):' || true
  else
    fail "redis-cli ping falhou"
  fi
else
  warn "redis-cli não instalado — Redis é crítico em multi-tenant"
fi

section "Conectividade MySQL (porta 3306)"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
if command -v nc >/dev/null 2>&1; then
  if nc -z -w 3 "$DB_HOST" "$DB_PORT" 2>/dev/null; then
    ok "Porta MySQL $DB_HOST:$DB_PORT acessível"
  else
    fail "Porta MySQL $DB_HOST:$DB_PORT NÃO respondeu em 3s (causa comum de Connection timed out)"
  fi
elif command -v bash >/dev/null 2>&1; then
  if timeout 3 bash -c "echo >/dev/tcp/$DB_HOST/$DB_PORT" 2>/dev/null; then
    ok "Porta MySQL $DB_HOST:$DB_PORT acessível"
  else
    fail "Porta MySQL $DB_HOST:$DB_PORT NÃO respondeu em 3s"
  fi
else
  warn "nc/timeout não disponível — teste manual: telnet $DB_HOST $DB_PORT"
fi

section "Logs da aplicação (últimas linhas de erro)"
LOG_DIR="$APP_ROOT/storage/logs"
if [ -d "$LOG_DIR" ]; then
  printf 'Arquivos de log maiores:\n'
  ls -lhS "$LOG_DIR"/*.log 2>/dev/null | head -8 || true
  LATEST="$(ls -t "$LOG_DIR"/app*.log 2>/dev/null | head -1 || true)"
  if [ -n "$LATEST" ]; then
    printf '\nÚltimos erros em %s:\n' "$LATEST"
    grep -iE 'error|exception|timeout|slow|fatal' "$LATEST" 2>/dev/null | tail -20 || tail -10 "$LATEST"
  fi
else
  warn "Diretório $LOG_DIR não encontrado"
fi

section "Health check HTTP (se curl disponível)"
if command -v curl >/dev/null 2>&1 && [ -f "$APP_ROOT/health.php" ]; then
  HEALTH_URL="${HEALTH_URL:-http://127.0.0.1/health.php}"
  printf 'GET %s\n' "$HEALTH_URL"
  curl -sS -m 10 -w '\nHTTP %{http_code} em %{time_total}s\n' "$HEALTH_URL" | tail -20
else
  warn "curl ou health.php indisponível — acesse /health.php no navegador"
fi

section "Fim"
printf 'Próximo passo: php scripts/diagnostico_mysql.php --all-tenants\n'
