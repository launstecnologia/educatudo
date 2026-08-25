#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

# O que você passou no comando (TOTAL_ALUNOS=5 VUS=2 ...) vale mais que o .env
CLI_VUS="${VUS-}"
CLI_TOTAL="${TOTAL_ALUNOS-}"
CLI_DURATION="${DURATION-}"
CLI_BASE="${BASE_URL-}"

if [[ -f "$ROOT/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "$ROOT/.env"
  set +a
fi

[[ -n "$CLI_VUS" ]] && VUS="$CLI_VUS"
[[ -n "$CLI_TOTAL" ]] && TOTAL_ALUNOS="$CLI_TOTAL"
[[ -n "$CLI_DURATION" ]] && DURATION="$CLI_DURATION"
[[ -n "$CLI_BASE" ]] && BASE_URL="$CLI_BASE"

PEDIDO="${1:-criar}"
case "$PEDIDO" in
  criar|cadastro) CENARIO="cadastro" ;;
  navegar|acesso) CENARIO="acesso" ;;
  matricula|enrollment) CENARIO="matricula" ;;
  smoke|prova|jornada|misto) CENARIO="$PEDIDO" ;;
  *)
    echo "Use: criar | navegar | matricula | smoke | prova | jornada | misto"
    echo "  criar     — cadastro de aluno (admin/students/create)"
    echo "  navegar   — alunos entram e clicam nas páginas"
    echo "  matricula — Nova Matrícula + preenche wizard + anexa RG/CPF/comprovante"
    exit 1
    ;;
esac
ARQUIVO="$ROOT/scenarios/${CENARIO}.js"

if [[ -z "${BASE_URL:-}" ]]; then
  echo "Defina BASE_URL no .env ou no ambiente."
  exit 1
fi

if [[ "$CENARIO" == "cadastro" || "$CENARIO" == "matricula" ]]; then
  if [[ -z "${ADMIN_LOGIN:-}" || -z "${ADMIN_SENHA:-}" ]]; then
    echo "Preencha ADMIN_LOGIN e ADMIN_SENHA no .env"
    exit 1
  fi
fi

mkdir -p "$ROOT/relatorios"
echo "→ k6 ${CENARIO} em ${BASE_URL} (VUs=${VUS:-10} TOTAL=${TOTAL_ALUNOS:-1000})"
echo "→ relatório em loadtests/k6/relatorios/${CENARIO}-ultimo.html"

if [[ "${INSECURE_TLS:-}" == "1" ]]; then
  exec k6 run --insecure-skip-tls-verify \
    -e BASE_URL="${BASE_URL}" \
    -e TENANT_SLUG="${TENANT_SLUG:-}" \
    -e ADMIN_LOGIN="${ADMIN_LOGIN:-}" \
    -e ADMIN_SENHA="${ADMIN_SENHA:-}" \
    -e ALUNO_SENHA_PADRAO="${ALUNO_SENHA_PADRAO:-Carga@2026}" \
    -e PREFIXO_ALUNO="${PREFIXO_ALUNO:-carga}" \
    -e TOTAL_ALUNOS="${TOTAL_ALUNOS:-1000}" \
    -e PROVA_ID="${PROVA_ID:-}" \
    -e JORNADA_ID="${JORNADA_ID:-}" \
    -e VUS="${VUS:-10}" \
    -e DURATION="${DURATION:-40m}" \
    -e CENARIO="${CENARIO}" \
    "$ARQUIVO"
fi

exec k6 run \
  -e BASE_URL="${BASE_URL}" \
  -e TENANT_SLUG="${TENANT_SLUG:-}" \
  -e ADMIN_LOGIN="${ADMIN_LOGIN:-}" \
  -e ADMIN_SENHA="${ADMIN_SENHA:-}" \
  -e ALUNO_SENHA_PADRAO="${ALUNO_SENHA_PADRAO:-Carga@2026}" \
  -e PREFIXO_ALUNO="${PREFIXO_ALUNO:-carga}" \
  -e TOTAL_ALUNOS="${TOTAL_ALUNOS:-1000}" \
  -e PROVA_ID="${PROVA_ID:-}" \
  -e JORNADA_ID="${JORNADA_ID:-}" \
  -e VUS="${VUS:-10}" \
  -e DURATION="${DURATION:-40m}" \
  -e CENARIO="${CENARIO}" \
  "$ARQUIVO"
