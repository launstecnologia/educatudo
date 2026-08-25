#!/usr/bin/env bash
# Diagnóstico rápido da VPS EducaTudo — rode na VPS como root ou com docker.
# Uso: cd /opt/educatudo && ./scripts/diagnostico-vps.sh

set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
COMPOSE=(docker compose -f "$ROOT/docker-compose.vps.yml")
ENV_MASTER_DOMAIN="$(grep -E '^MASTER_DOMAIN=' "$ROOT/backend/.env" 2>/dev/null | head -1 | cut -d= -f2- | tr -d "\"'" | tr -d '\r' || true)"
DOMAIN="${DIAG_DOMAIN:-${ENV_MASTER_DOMAIN:-master.educatudo.com}}"

hr() { printf '\n%s\n' "════════════════════════════════════════"; }
ok() { printf '✓ %s\n' "$1"; }
fail() { printf '✗ %s\n' "$1"; }
warn() { printf '⚠ %s\n' "$1"; }

hr
echo "EducaTudo — diagnóstico VPS"
echo "Raiz: $ROOT"
echo "Domínio teste: $DOMAIN"

hr
echo "1. IP público desta VPS"
if command -v curl >/dev/null 2>&1; then
  VPS_IP="$(curl -4 -s --max-time 5 ifconfig.me 2>/dev/null || curl -4 -s --max-time 5 icanhazip.com 2>/dev/null || true)"
  echo "   IP: ${VPS_IP:-não detectado}"
else
  warn "curl não instalado"
fi

hr
echo "2. DNS (deve apontar para o IP acima)"
if command -v dig >/dev/null 2>&1; then
  DNS_IP="$(dig +short "$DOMAIN" A 2>/dev/null | tail -1)"
  echo "   $DOMAIN → ${DNS_IP:-sem registro A}"
  if [[ -n "${VPS_IP:-}" && -n "${DNS_IP:-}" && "$VPS_IP" != "$DNS_IP" ]]; then
    fail "DNS NÃO aponta para esta VPS ($VPS_IP ≠ $DNS_IP)"
    echo "   Se usa Cloudflare proxy (nuvem laranja), o IP pode ser da Cloudflare — OK."
  elif [[ -n "${VPS_IP:-}" && "$VPS_IP" == "${DNS_IP:-}" ]]; then
    ok "DNS aponta direto para esta VPS"
  fi
else
  warn "dig não instalado — instale: apt install dnsutils"
fi

hr
echo "3. Firewall local (UFW)"
if command -v ufw >/dev/null 2>&1; then
  ufw status 2>/dev/null | head -20 || true
else
  warn "ufw não instalado — verifique firewall no painel Hostinger"
fi

hr
echo "4. Portas 80/443 escutando"
if command -v ss >/dev/null 2>&1; then
  ss -tlnp | grep -E ':80 |:443 ' || fail "Nada escutando em 80 ou 443"
else
  netstat -tlnp 2>/dev/null | grep -E ':80 |:443 ' || warn "ss/netstat indisponível"
fi

hr
echo "5. Containers Docker"
if command -v docker >/dev/null 2>&1; then
  "${COMPOSE[@]}" ps 2>/dev/null || docker ps --filter name=educatudo
else
  fail "Docker não instalado"
fi

hr
echo "6. Teste local HTTP (nginx na VPS)"
if curl -sS -o /dev/null -w "   HTTP local: %{http_code} em %{time_total}s\n" --max-time 10 \
  -H "Host: $DOMAIN" http://127.0.0.1/master/ 2>/dev/null; then
  :
else
  fail "curl local falhou — stack não responde em localhost:80"
fi

hr
echo "7. Teste MySQL (container PHP → banco remoto)"
if [[ -f "$ROOT/backend/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source <(grep -E '^(DB_HOST|DB_PORT|DB_NAME|DB_USER|DB_PASS|REDIS_HOST|REDIS_PORT|REDIS_PASSWORD)=' "$ROOT/backend/.env" | sed 's/\r$//')
  set +a
  echo "   DB_HOST=${DB_HOST:-?} REDIS_HOST=${REDIS_HOST:-?}"
  if "${COMPOSE[@]}" exec -T php php -r '
    $env = parse_ini_file("/var/www/html/.env", false, INI_SCANNER_RAW) ?: [];
    $read = static function (string $key, $default = "") use ($env) {
      $value = getenv($key);
      if ($value === false || $value === "") {
        $value = $env[$key] ?? $default;
      }
      if (!is_string($value)) {
        return $value;
      }
      $value = trim($value);
      return trim($value, chr(34) . chr(39));
    };
    $h=$read("DB_HOST"); $p=$read("DB_PORT", 3306); $n=$read("DB_NAME"); $u=$read("DB_USER"); $pw=$read("DB_PASS");
    if(!$h||!$n){fwrite(STDERR,"DB_* ausente no .env\n"); exit(2);}
    new PDO("mysql:host=$h;port=$p;dbname=$n;charset=utf8mb4",$u,$pw,[PDO::ATTR_TIMEOUT=>5]);
    echo "   MySQL OK\n";
  ' 2>&1; then
    ok "PHP conectou no MySQL remoto"
  else
    fail "PHP NÃO conectou no MySQL — libere IP desta VPS no firewall do MySQL"
  fi
else
  fail "backend/.env ausente"
fi

hr
echo "8. Redis (container e PHP)"
if command -v docker >/dev/null 2>&1; then
  REDIS_HOST_DIAG="${REDIS_HOST:-redis}"
  REDIS_PORT_DIAG="${REDIS_PORT:-6379}"
  REDIS_AUTH_EXEC_ENV=()
  REDIS_AUTH_DOCKER_ENV=()
  if [[ -n "${REDIS_PASSWORD:-}" ]]; then
    REDIS_AUTH_EXEC_ENV=("REDISCLI_AUTH=${REDIS_PASSWORD}")
    REDIS_AUTH_DOCKER_ENV=(-e "REDISCLI_AUTH=${REDIS_PASSWORD}")
  fi
  if [[ "$REDIS_HOST_DIAG" =~ ^(redis|127\.0\.0\.1|localhost)$ ]] && "${COMPOSE[@]}" ps redis >/dev/null 2>&1; then
    REDIS_CLI=("${COMPOSE[@]}" exec -T redis env "${REDIS_AUTH_EXEC_ENV[@]}" redis-cli)
  else
    REDIS_CLI=(docker run --rm --network host "${REDIS_AUTH_DOCKER_ENV[@]}" redis:7-alpine redis-cli -h "$REDIS_HOST_DIAG" -p "$REDIS_PORT_DIAG")
  fi

  if "${REDIS_CLI[@]}" ping 2>/dev/null | grep -q PONG; then
    ok "Redis respondeu PONG em ${REDIS_HOST_DIAG}:${REDIS_PORT_DIAG}"
    "${REDIS_CLI[@]}" INFO server memory persistence clients 2>/dev/null \
      | grep -E '^(redis_version|used_memory_human|connected_clients|maxmemory_human|appendonly|aof_enabled|aof_last_write_status|rdb_last_bgsave_status):' || true
  else
    fail "Redis não respondeu PONG em ${REDIS_HOST_DIAG}:${REDIS_PORT_DIAG}"
  fi

  if "${COMPOSE[@]}" exec -T php php -r '
    $env = parse_ini_file("/var/www/html/.env", false, INI_SCANNER_RAW) ?: [];
    $read = static function (string $key, $default = "") use ($env) {
      $value = getenv($key);
      if ($value === false || $value === "") {
        $value = $env[$key] ?? $default;
      }
      if (!is_string($value)) {
        return $value;
      }
      $value = trim($value);
      return trim($value, chr(34) . chr(39));
    };
    $host = $read("REDIS_HOST", "127.0.0.1");
    $port = (int) $read("REDIS_PORT", 6379);
    $password = $read("REDIS_PASSWORD", "");
    if (!class_exists("Redis")) {
      fwrite(STDERR, "Extensão Redis ausente no PHP\n");
      exit(2);
    }
    $redis = new Redis();
    if (!$redis->connect($host, $port, 1.0)) {
      fwrite(STDERR, "Falha ao conectar em Redis $host:$port\n");
      exit(3);
    }
    if ($password !== "" && !$redis->auth($password)) {
      fwrite(STDERR, "Falha ao autenticar no Redis\n");
      exit(5);
    }
    $pong = $redis->ping();
    if ($pong !== true && $pong !== "+PONG" && $pong !== "PONG") {
      fwrite(STDERR, "PING inesperado: " . var_export($pong, true) . "\n");
      exit(4);
    }
    echo "   PHP Redis OK em {$host}:{$port}\n";
  ' 2>&1; then
    ok "PHP conectou no Redis"
  else
    fail "PHP NÃO conectou no Redis — em Docker, REDIS_HOST deve ser redis"
  fi
else
  fail "Docker não instalado"
fi

hr
echo "9. Certificado SSL (Let's Encrypt)"
CERT="/etc/letsencrypt/live/educatudo.com/fullchain.pem"
if [[ -f "$CERT" ]]; then
  ok "Cert encontrado: $CERT"
  openssl x509 -in "$CERT" -noout -dates 2>/dev/null || true
else
  warn "Cert ainda não emitido — rode: ./scripts/setup-vps-ssl-docker.sh educatudo.com"
  echo "   Enquanto isso, Cloudflare SSL/TLS → Flexible (HTTP na origem)"
fi

hr
echo "10. Cloudflare (se proxy ativo)"
echo "   SSL/TLS recomendado:"
echo "   - Sem cert na VPS: Flexible"
echo "   - Com cert na VPS: Full (strict) + docker-compose.vps.ssl.yml"

hr
echo "Fim. Se DNS/firewall OK mas browser trava: quase sempre origem inacessível na 80/443."
