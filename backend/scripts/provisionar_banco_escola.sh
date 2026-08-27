#!/usr/bin/env bash
# Cria banco + usuário da escola e importa educa_core.sql (somente estrutura).
#
# No SSH do MySQL (arquivo SQL já no servidor):
#   bash backend/scripts/provisionar_banco_escola.sh educatudo_seb_ribeirania educatudo_seb_ribeirania 'SENHA_FORTE'
#
# Se o SQL estiver noutro caminho:
#   SCHEMA=/tmp/educa_core.sql bash backend/scripts/provisionar_banco_escola.sh ...
#
# Se o mysql client estiver na VPS da aplicação (não no host do MySQL):
#   MYSQL_HOST=IP_DO_MYSQL MYSQL_USER=root bash backend/scripts/provisionar_banco_escola.sh ...

set -euo pipefail

DB_NAME="${1:-}"
DB_USER="${2:-}"
DB_PASS="${3:-}"

if [[ -z "$DB_NAME" || -z "$DB_USER" || -z "$DB_PASS" ]]; then
  echo "Uso: $0 <nome_banco> <usuario> <senha>" >&2
  echo "Ex.: $0 educatudo_seb_ribeirania educatudo_seb_ribeirania 'SenhaForte'" >&2
  exit 1
fi

if [[ ! "$DB_NAME" =~ ^[a-zA-Z0-9_]+$ || ! "$DB_USER" =~ ^[a-zA-Z0-9_]+$ ]]; then
  echo "Banco e usuário: só letras, números e underscore." >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SCHEMA="${SCHEMA:-$ROOT/backend/database/educa_core.sql}"
if [[ ! -f "$SCHEMA" ]]; then
  echo "SQL não encontrado: $SCHEMA" >&2
  echo "Copie educa_core.sql ou defina SCHEMA=/caminho/educa_core.sql" >&2
  exit 1
fi

MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_USER="${MYSQL_USER:-root}"

echo "==> MySQL ${MYSQL_USER}@${MYSQL_HOST}:${MYSQL_PORT}"
echo "    banco=$DB_NAME usuario=$DB_USER"
echo "    schema=$SCHEMA"

MYSQL=(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER")
if [[ -n "${MYSQL_PWD:-}" ]]; then
  export MYSQL_PWD
elif [[ -z "${MYSQL_PWD+x}" ]]; then
  echo "Senha do admin MySQL (${MYSQL_USER}):"
  "${MYSQL[@]}" -p -e "SELECT 1" >/dev/null
  MYSQL+=(-p)
fi

PASS_ESC="${DB_PASS//\'/\'\'}"
"${MYSQL[@]}" -e "
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${PASS_ESC}';
ALTER USER '${DB_USER}'@'%' IDENTIFIED BY '${PASS_ESC}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
"

echo "==> Importando schema (pode levar ~1 min)..."
"${MYSQL[@]}" --default-character-set=utf8mb4 "$DB_NAME" < "$SCHEMA"

TABELAS="$("${MYSQL[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_type='BASE TABLE'")"
echo "==> Pronto. Tabelas em ${DB_NAME}: ${TABELAS}"
echo
echo "No Master, edite a escola SEM marcar criar automaticamente:"
echo "  Host: ${MYSQL_HOST}   Porta: ${MYSQL_PORT}"
echo "  Banco: ${DB_NAME}"
echo "  Usuário: ${DB_USER}"
echo "  Senha: (a que você passou neste comando)"
