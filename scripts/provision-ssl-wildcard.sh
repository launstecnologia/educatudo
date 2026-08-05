#!/usr/bin/env bash
# Emite ou renova certificado wildcard Let's Encrypt via DNS-01 (Cloudflare).
# Execute no servidor de origem (root ou sudo). Não commitar cloudflare.ini com token.
#
# Pré-requisitos:
#   - DNS na Cloudflare: A/CNAME * e master → IP deste servidor
#   - Arquivo /etc/letsencrypt/cloudflare.ini:
#       dns_cloudflare_api_token = SEU_TOKEN_COM_Zone_DNS_Edit
#   - certbot + python3-certbot-dns-cloudflare instalados
#
# Uso:
#   ./scripts/provision-ssl-wildcard.sh educatudo.com
#   ./scripts/provision-ssl-wildcard.sh educatudo.com --dry-run

set -euo pipefail

BASE_DOMAIN="${1:-educatudo.com}"
DRY_RUN=""
if [[ "${2:-}" == "--dry-run" ]]; then
  DRY_RUN="--dry-run"
fi

CF_INI="${CLOUDFLARE_INI:-/etc/letsencrypt/cloudflare.ini}"

if [[ ! -f "$CF_INI" ]]; then
  echo "Erro: crie $CF_INI com dns_cloudflare_api_token = ..." >&2
  exit 1
fi

chmod 600 "$CF_INI"

echo "== Certificado wildcard para ${BASE_DOMAIN} =="

certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials "$CF_INI" \
  --agree-tos \
  --non-interactive \
  --email "${LETSENCRYPT_EMAIL:-admin@${BASE_DOMAIN}}" \
  -d "${BASE_DOMAIN}" \
  -d "*.${BASE_DOMAIN}" \
  -d "master.${BASE_DOMAIN}" \
  $DRY_RUN

if [[ -z "$DRY_RUN" ]]; then
  echo "Certificados em /etc/letsencrypt/live/${BASE_DOMAIN}/"
  echo "Configure nginx (docker/nginx/wildcard-prod.conf) e recarregue:"
  echo "  nginx -t && nginx -s reload"
  echo ""
  echo "Cron de renovação (exemplo):"
  echo "  0 3 * * * certbot renew --quiet && nginx -s reload"
fi

echo "Concluído."
