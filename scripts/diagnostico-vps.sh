#!/usr/bin/env bash
# Diagnóstico rápido da VPS EducaTudo — rode na VPS como root ou com docker.
# Uso: cd /opt/educatudo && ./scripts/diagnostico-vps.sh

set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
COMPOSE=(docker compose -f "$ROOT/docker-compose.vps.yml")
DOMAIN="${DIAG_DOMAIN:-master.educatudo.com}"

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
  source <(grep -E '^(DB_HOST|DB_PORT|DB_NAME|DB_USER|DB_PASS|REDIS_HOST)=' "$ROOT/backend/.env" | sed 's/\r$//')
  set +a
  echo "   DB_HOST=${DB_HOST:-?} REDIS_HOST=${REDIS_HOST:-?}"
  if "${COMPOSE[@]}" exec -T php php -r '
    require "vendor/autoload.php";
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
    $h=$_ENV["DB_HOST"]??""; $p=$_ENV["DB_PORT"]??3306; $n=$_ENV["DB_NAME"]??""; $u=$_ENV["DB_USER"]??""; $pw=$_ENV["DB_PASS"]??"";
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
echo "8. Certificado SSL (Let's Encrypt)"
CERT="/etc/letsencrypt/live/educatudo.com/fullchain.pem"
if [[ -f "$CERT" ]]; then
  ok "Cert encontrado: $CERT"
  openssl x509 -in "$CERT" -noout -dates 2>/dev/null || true
else
  warn "Cert ainda não emitido — rode: ./scripts/setup-vps-ssl-docker.sh educatudo.com"
  echo "   Enquanto isso, Cloudflare SSL/TLS → Flexible (HTTP na origem)"
fi

hr
echo "9. Cloudflare (se proxy ativo)"
echo "   SSL/TLS recomendado:"
echo "   - Sem cert na VPS: Flexible"
echo "   - Com cert na VPS: Full (strict) + docker-compose.vps.ssl.yml"

hr
echo "Fim. Se DNS/firewall OK mas browser trava: quase sempre origem inacessível na 80/443."
