#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if ! command -v flutter >/dev/null 2>&1; then
  echo "Flutter não encontrado no PATH. Instale o Flutter estável antes de continuar." >&2
  exit 1
fi

if [[ -d "${APP_DIR}/android" || -f "${APP_DIR}/pubspec.yaml" ]]; then
  echo "O projeto Flutter já parece ter sido gerado em ${APP_DIR}." >&2
  exit 1
fi

flutter create \
  --platforms=android \
  --org=br.com.educatudo \
  --project-name=educatudo_pais \
  "${APP_DIR}"

echo "Base Android criada. Configure os ambientes e o Firebase antes de executar o app."
