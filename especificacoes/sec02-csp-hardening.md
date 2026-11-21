# SEC-02 — Hardening CSP (EducaTudo)

**Atualizado:** 2026-11-21  
**Status:** Ondas 0–3 implementadas no código; Onda 4 (promoção restritiva) **não** ativada sem autorização.

## Onde a CSP é emitida

| Camada | CSP? |
|---|---|
| [`backend/app/Core/CspPolitica.php`](../backend/app/Core/CspPolitica.php) via [`bootstrap/app.php`](../backend/bootstrap/app.php) | **Sim** — única fonte |
| Nginx | Não |
| Cloudflare (repo) | Não |

Headers por resposta HTML:

1. `Content-Security-Policy` — **enforcement (baseline)** com `'unsafe-inline'` + `'unsafe-eval'` + allowlist completa (Quill, CKEditor, Tiny, OneSignal, VLibras, esm.sh, Jitsi, CDNs).
2. `Content-Security-Policy-Report-Only` — **candidata** sem `'unsafe-eval'`, sem `'unsafe-inline'` em `script-src`, com `'nonce-…'`, `report-uri /api/csp-report`, connect/frame mais explícitos.

**Importante:** o enforcement **não** inclui nonce (em CSP2+ nonce invalida `unsafe-inline` e quebraria telas ainda não migradas).

## Nonce

- Gerado com `random_bytes(16)` → base64, **por request** (`CspPolitica::nonce()`).
- Injetado nas views via `BaseController::enriquecerDadosCsp()` (`$csp_nonce`, `$csp_nonce_attr`).
- `$additional_js` recebe `CspPolitica::aplicarNonceEmHtml()`.
- Layouts/auth/módulos críticos: tags `<script>` inline com nonce; MathJax/SW/delegação em `/public/static/js/csp-*.js`.

## Relatórios

`POST /api/csp-report` → [`CspReportController`](../backend/app/Webhooks/Controllers/CspReportController.php) (rota pública). Logs em canal `csp`.

## Rollback

1. Em `CspPolitica::emitirCabecalhos()`, emitir só Report-Only ou reverter allowlist.
2. Ou restaurar header inline antigo no bootstrap.
3. Sem migration / sem Nginx.

## Promoção (Onda 4) — exige autorização explícita

Checklist antes de promover a candidata para enforcement:

- [ ] Violações Report-Only estáveis nos fluxos críticos (login, prova, MathJax, Jitsi, Quill, WebSocket).
- [ ] Remoção progressiva de `on*` restantes (~1800) ou aceitar risco residual documentado.
- [ ] MathJax validado sem `unsafe-eval` (ou exceção documentada).
- [ ] Testes `Sec02CspPoliticaTest` + smoke Playwright por perfil.
- [ ] Aprovar por escrito a troca: candidata → `Content-Security-Policy`.

## Testes

```bash
cd backend && ./vendor/bin/phpunit tests/Seguranca/Sec02CspPoliticaTest.php
curl -sI http://colag.localhost/ | grep -i content-security
```

## Riscos residuais

- Enforcement ainda com `unsafe-*` → XSS inline não é bloqueado de forma efetiva.
- Handlers `on*` e scripts de módulos não migrados geram violações só na candidata.
- Wildcards `https:`/`wss:` permanecem no enforcement até promoção.
