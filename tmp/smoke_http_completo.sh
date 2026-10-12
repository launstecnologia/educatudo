#!/bin/bash
# Smoke HTTP colag.localhost — rotas tocadas no refactor
set -u
BASE="http://colag.localhost"
HOST_HDR=(-H "Host: colag.localhost")
COOKIE=$(mktemp)
COOKIE_ADMIN=$(mktemp)
REPORT=$(mktemp)
trap 'rm -f "$COOKIE" "$COOKIE_ADMIN"' EXIT

ok=0
fail=0
warn=0

pass() { echo "OK   $1"; ok=$((ok+1)); echo "OK|$1" >> "$REPORT"; }
bad()  { echo "FAIL $1 — $2"; fail=$((fail+1)); echo "FAIL|$1|$2" >> "$REPORT"; }
soft() { echo "WARN $1 — $2"; warn=$((warn+1)); echo "WARN|$1|$2" >> "$REPORT"; }

# code body_file label expected_codes(csv) error_patterns
hit() {
  local method="$1" url="$2" label="$3" expect="$4"
  local body="/tmp/smoke_body_$$.html"
  local code
  if [[ "$method" == "GET" ]]; then
    code=$(curl -sS "${HOST_HDR[@]}" -c "$COOKIE" -b "$COOKIE" -o "$body" -w "%{http_code}" --max-time 20 "$BASE$url" || echo "000")
  else
    code=$(curl -sS "${HOST_HDR[@]}" -c "$COOKIE" -b "$COOKIE" -o "$body" -w "%{http_code}" --max-time 20 -X "$method" "$BASE$url" || echo "000")
  fi
  local ok_code=0
  IFS=',' read -ra codes <<< "$expect"
  for c in "${codes[@]}"; do
    [[ "$code" == "$c" ]] && ok_code=1
  done
  if grep -qiE "Fatal error|Parse error|Uncaught Error|Uncaught Exception|Class ['\"]?[^'\"]+['\"]? not found|Failed opening required" "$body" 2>/dev/null; then
    local snip
    snip=$(grep -iE "Fatal error|Parse error|Uncaught|not found|Failed opening" "$body" | head -1 | cut -c1-180)
    bad "$label [$code]" "$snip"
    return
  fi
  if [[ $ok_code -eq 1 ]]; then
    pass "$label [$code]"
  else
    bad "$label [$code]" "esperado $expect"
  fi
}

csrf_from() {
  local file="$1"
  php -r '
    $h = file_get_contents($argv[1]);
    foreach (["csrf_token","_token","csrf"] as $n) {
      if (preg_match("/name=\"{$n}\"[^>]*value=\"([^\"]+)/", $h, $m)
       || preg_match("/value=\"([^\"]+)\"[^>]*name=\"{$n}\"/", $h, $m)) { echo $m[1]; exit; }
    }
  ' "$file"
}

echo "======== 1) Público / API / PWA (sem login) ========"
hit GET "/login" "login page" "200"
hit GET "/api/v1/status" "API status" "200"
hit GET "/api/docs" "API swagger docs" "200,301,302"
hit GET "/api/openapi.json" "API openapi" "200"
hit GET "/manifest-aluno.json" "PWA aluno" "200"
hit GET "/manifest-professor.json" "PWA professor" "200"
hit GET "/manifest-admin.json" "PWA admin" "200"
hit GET "/manifest-pais.json" "PWA pais" "200"
hit GET "/validar/historico/hash-inexistente-smoke" "Publico historico" "200,404"
hit GET "/educalabs/validate-token" "EducaLabs validate-token" "200,400,401,403,302"
hit GET "/games/validate-token" "Games validate-token" "200,400,401,403,302"
hit GET "/notes/validate-token" "Notes validate-token" "200,400,401,403,302"
hit GET "/external-apps/validate-token" "ExternalApps validate-token" "200,400,401,403,302"
hit GET "/api/auth/login" "API auth GET (method)" "200,400,401,405"

echo ""
echo "======== 2) Login aluno (admin@colag.local) ========"
curl -sS "${HOST_HDR[@]}" -c "$COOKIE" -b "$COOKIE" -o /tmp/smoke_login.html "$BASE/login"
CSRF=$(csrf_from /tmp/smoke_login.html)
LOGIN_CODE=$(curl -sS "${HOST_HDR[@]}" -c "$COOKIE" -b "$COOKIE" -o /tmp/smoke_login_post.html -w "%{http_code}" \
  -X POST "$BASE/login" \
  --data-urlencode "email=admin@colag.local" \
  --data-urlencode "password=123456" \
  --data-urlencode "site_url=" \
  --data-urlencode "csrf_token=${CSRF}" \
  --data-urlencode "_token=${CSRF}" \
  -D /tmp/smoke_login_hdrs.txt)
LOC=$(grep -i '^Location:' /tmp/smoke_login_hdrs.txt | tr -d '\r' | awk '{print $2}')
if [[ "$LOGIN_CODE" == "302" || "$LOGIN_CODE" == "200" ]]; then
  pass "login aluno POST [$LOGIN_CODE] → ${LOC:-same}"
else
  bad "login aluno POST [$LOGIN_CODE]" "falhou"
fi

echo ""
echo "======== 3) Área aluno / módulos movidos ========"
hit GET "/" "home aluno" "200"
hit GET "/educalabs" "EducaLabs index" "200,302,403"
hit GET "/jogos" "Jogos index" "200,302,403"
hit GET "/jogo-milhao" "Jogo milhão" "200,302,403"
hit GET "/notes/access" "Notes access" "200,302,403"
hit GET "/educahits" "EducaHits" "200,302,403,404"
hit GET "/tudinha" "Tudinha (se rota)" "200,302,404"
hit GET "/flashcards" "Flashcards" "200,302,403,404"
hit GET "/provas" "Provas aluno" "200,302,403,404"

echo ""
echo "======== 4) Login admin ========"
curl -sS "${HOST_HDR[@]}" -c "$COOKIE_ADMIN" -b "$COOKIE_ADMIN" -o /tmp/smoke_admin_login.html "$BASE/admin/login"
# fallback /login if admin/login 404
if ! grep -qi "password\|senha\|csrf" /tmp/smoke_admin_login.html 2>/dev/null; then
  curl -sS "${HOST_HDR[@]}" -c "$COOKIE_ADMIN" -b "$COOKIE_ADMIN" -o /tmp/smoke_admin_login.html "$BASE/login"
fi
CSRF_A=$(csrf_from /tmp/smoke_admin_login.html)
# try a few known emails
ADMIN_OK=0
for email in "admin@colag.local" "secretaria@colag.local" "diretor@colag.local"; do
  rm -f /tmp/smoke_admin_hdrs.txt
  CODE=$(curl -sS "${HOST_HDR[@]}" -c "$COOKIE_ADMIN" -b "$COOKIE_ADMIN" -o /tmp/smoke_admin_post.html -w "%{http_code}" \
    -X POST "$BASE/login" \
    --data-urlencode "email=$email" \
    --data-urlencode "password=123456" \
    --data-urlencode "site_url=" \
    --data-urlencode "csrf_token=${CSRF_A}" \
    --data-urlencode "_token=${CSRF_A}" \
    -D /tmp/smoke_admin_hdrs.txt)
  # refresh csrf for next try
  curl -sS "${HOST_HDR[@]}" -c "$COOKIE_ADMIN" -b "$COOKIE_ADMIN" -o /tmp/smoke_admin_login.html "$BASE/login" >/dev/null
  CSRF_A=$(csrf_from /tmp/smoke_admin_login.html)
  LOC_A=$(grep -i '^Location:' /tmp/smoke_admin_hdrs.txt 2>/dev/null | tr -d '\r' | awk '{print $2}')
  # probe /admin with this cookie
  ACODE=$(curl -sS "${HOST_HDR[@]}" -c "$COOKIE_ADMIN" -b "$COOKIE_ADMIN" -o /tmp/smoke_admin_dash.html -w "%{http_code}" "$BASE/admin")
  TITLE=$(php -r '$h=file_get_contents("/tmp/smoke_admin_dash.html"); if(preg_match("/<title>([^<]+)/",$h,$m)) echo $m[1];')
  if [[ "$ACODE" == "200" ]] && ! grep -qi "Login Administração\|name=\"password\"" /tmp/smoke_admin_dash.html; then
    pass "admin session via $email → /admin [$ACODE] ($TITLE)"
    ADMIN_OK=1
    break
  else
    soft "admin try $email" "login=$CODE loc=${LOC_A:-} admin=$ACODE title=$TITLE"
  fi
done

# use admin cookie for subsequent hits if ok, else student cookie still in COOKIE — switch
if [[ $ADMIN_OK -eq 1 ]]; then
  cp "$COOKIE_ADMIN" "$COOKIE"
fi

echo ""
echo "======== 5) Páginas admin (Dominio / Painel / Modulos) ========"
# These should not fatal even if 302/403
for path_label in \
  "/admin|dashboard" \
  "/admin/students|alunos (Dominio Pessoas)" \
  "/admin/teachers|professores" \
  "/admin/tudicoins|tudicoins" \
  "/admin/classes|turmas" \
  "/admin/exams|provas" \
  "/admin/finance|financeiro" \
  "/admin/compliance|conformidade" \
  "/admin/patrimony|patrimonio" \
  "/admin/enrollment|matricula" \
  "/admin/users|usuarios" \
  "/admin/school-settings|config escola" \
  "/admin/mural|mural" \
  "/admin/boletins|boletins" \
  "/admin/dev-settings/pwa|dev PWA settings"
do
  path="${path_label%%|*}"
  label="${path_label#*|}"
  hit GET "$path" "admin: $label" "200,302,403,404"
done

echo ""
echo "======== 6) Professor / pais shells ========"
hit GET "/professor" "professor shell" "200,302,401,403"
hit GET "/pais" "pais shell" "200,302,401,403"
hit GET "/monitor" "monitor shell" "200,302,401,403"

echo ""
echo "======== RESUMO HTTP: OK=$ok FAIL=$fail WARN=$warn ========"
if [[ $fail -gt 0 ]]; then
  echo "Falhas:"
  grep '^FAIL|' "$REPORT" | sed 's/^FAIL|/  - /'
  exit 1
fi
exit 0
