#!/usr/bin/env bash
# Habilita HTTPS na VPS com certificado Origin da Cloudflare (SEM API token).
#
# 1. Cloudflare → SSL/TLS → Origin Server → Create Certificate
# 2. Hostnames: educatudo.com, *.educatudo.com
# 3. Salve os arquivos abaixo e rode este script.
#
# Uso:
#   mkdir -p ssl
#   nano ssl/cloudflare-origin.pem    # colar certificado
#   nano ssl/cloudflare-origin.key    # colar chave privada
#   chmod 600 ssl/cloudflare-origin.*
#   ./scripts/setup-vps-ssl-cloudflare-origin.sh

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CERT="$ROOT/ssl/cloudflare-origin.pem"
KEY="$ROOT/ssl/cloudflare-origin.key"

if [[ ! -f "$CERT" || ! -f "$KEY" ]]; then
  echo "Erro: coloque os arquivos antes de continuar:" >&2
  echo "  $CERT" >&2
  echo "  $KEY" >&2
  echo "" >&2
  echo "Cloudflare → SSL/TLS → Origin Server → Create Certificate" >&2
  echo "Hostnames: educatudo.com, *.educatudo.com" >&2
  exit 1
fi

chmod 600 "$CERT" "$KEY"

echo "==> Subindo Nginx com HTTPS (443)..."
cd "$ROOT"
docker compose -f docker-compose.vps.yml -f docker-compose.vps.ssl-origin.yml up -d nginx

echo ""
echo "Cloudflare → SSL/TLS → Full (strict)"
echo "Teste: curl -I https://master.educatudo.com/master/"
