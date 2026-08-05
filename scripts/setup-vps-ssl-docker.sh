#!/usr/bin/env bash
# Emite cert wildcard Let's Encrypt (DNS Cloudflare) e sobe Nginx Docker com HTTPS.
#
# Pré-requisitos:
#   - Cloudflare: A * e master → IP desta VPS
#   - /etc/letsencrypt/cloudflare.ini com dns_cloudflare_api_token
#   - Stack base no ar: ./scripts/up-vps.sh
#
# Uso:
#   ./scripts/setup-vps-ssl-docker.sh educatudo.com
#   ./scripts/setup-vps-ssl-docker.sh educatudo.com --dry-run

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_DOMAIN="${1:-educatudo.com}"
DRY="${2:-}"

echo "==> Certificado wildcard: $BASE_DOMAIN"

if [[ ! -f /etc/letsencrypt/cloudflare.ini ]]; then
  echo "Erro: crie /etc/letsencrypt/cloudflare.ini" >&2
  echo "  dns_cloudflare_api_token = TOKEN_COM_Zone_DNS_Edit" >&2
  exit 1
fi

if ! command -v certbot >/dev/null 2>&1; then
  echo "Instalando certbot + plugin Cloudflare..."
  apt-get update -qq
  apt-get install -y -qq certbot python3-certbot-dns-cloudflare
fi

chmod 600 /etc/letsencrypt/cloudflare.ini

CERTBOT_ARGS=(
  certonly
  --dns-cloudflare
  --dns-cloudflare-credentials /etc/letsencrypt/cloudflare.ini
  --agree-tos
  --non-interactive
  --email "${LETSENCRYPT_EMAIL:-admin@${BASE_DOMAIN}}"
  -d "${BASE_DOMAIN}"
  -d "*.${BASE_DOMAIN}"
  -d "master.${BASE_DOMAIN}"
)

if [[ "$DRY" == "--dry-run" ]]; then
  certbot "${CERTBOT_ARGS[@]}" --dry-run
  echo "Dry-run OK."
  exit 0
fi

certbot "${CERTBOT_ARGS[@]}"

CERT="/etc/letsencrypt/live/${BASE_DOMAIN}/fullchain.pem"
if [[ ! -f "$CERT" ]]; then
  echo "Erro: cert não encontrado em $CERT" >&2
  exit 1
fi

echo "==> Reiniciando Nginx com HTTPS (443)..."
cd "$ROOT"
docker compose -f docker-compose.vps.yml -f docker-compose.vps.ssl.yml up -d nginx

echo ""
echo "Certificado em /etc/letsencrypt/live/${BASE_DOMAIN}/"
echo "Cloudflare → SSL/TLS → Full (strict)"
echo "Teste: curl -I https://master.${BASE_DOMAIN}/master/"
