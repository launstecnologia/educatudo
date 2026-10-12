#!/bin/bash
set -euo pipefail
COOKIE=$(mktemp)
BASE="http://colag.localhost"
trap 'rm -f "$COOKIE"' EXIT

curl -sS -c "$COOKIE" -b "$COOKIE" -o /tmp/smoke_login_page.html "$BASE/login" || true
CSRF=$(php -r '
$html = file_get_contents("/tmp/smoke_login_page.html");
foreach (["csrf_token", "_token", "csrf"] as $name) {
  if (preg_match("/name=[\"'\'']" . preg_quote($name, "/") . "[\"'\''][^>]*value=[\"'\'']([^\"'\'']+)/i", $html, $m)
   || preg_match("/value=[\"'\'']([^\"'\'']+)[\"'\''][^>]*name=[\"'\'']" . preg_quote($name, "/") . "[\"'\'']/i", $html, $m)) {
    echo $m[1];
    exit;
  }
}
')
echo "CSRF len: ${#CSRF}"

ARGS=(
  -sS -o /tmp/smoke_login_body.txt -w "%{http_code} %{redirect_url}"
  -c "$COOKIE" -b "$COOKIE"
  -X POST "$BASE/login"
  -H "Content-Type: application/x-www-form-urlencoded"
  --data-urlencode "email=admin@colag.local"
  --data-urlencode "password=123456"
  --data-urlencode "site_url="
)
if [[ -n "$CSRF" ]]; then
  ARGS+=(--data-urlencode "csrf_token=$CSRF" --data-urlencode "_token=$CSRF")
fi
RESP=$(curl "${ARGS[@]}")
echo "LOGIN: $RESP"
head -c 240 /tmp/smoke_login_body.txt; echo

ADMIN=$(curl -sS -o /tmp/smoke_admin.txt -w "%{http_code}" -c "$COOKIE" -b "$COOKIE" "$BASE/admin")
echo "ADMIN: $ADMIN"
if grep -qiE "Fatal error|Parse error|Uncaught|Class .* not found" /tmp/smoke_admin.txt; then
  echo "ERRORS FOUND:"
  grep -iE "Fatal error|Parse error|Uncaught|Class .* not found" /tmp/smoke_admin.txt | head -10
  exit 1
fi
echo "No fatal/class errors in admin HTML"

HOME_CODE=$(curl -sS -o /tmp/smoke_home.txt -w "%{http_code}" -c "$COOKIE" -b "$COOKIE" "$BASE/")
echo "HOME: $HOME_CODE"
