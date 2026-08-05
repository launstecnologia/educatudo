#!/usr/bin/env bash
# Opcional: força resolução de *.localhost (só se o browser não resolver sozinho).
set -euo pipefail

MARKER="# educatudo-localhost-dev"
BLOCK="$MARKER
127.0.0.1 master.localhost
127.0.0.1 colag.localhost
"

if grep -q "$MARKER" /etc/hosts 2>/dev/null; then
  echo "Entradas já existem em /etc/hosts."
  grep -A2 "$MARKER" /etc/hosts || true
  exit 0
fi

echo "Adicionando master.localhost e colag.localhost em /etc/hosts..."
echo "$BLOCK" | sudo tee -a /etc/hosts > /dev/null
grep -A2 "$MARKER" /etc/hosts
