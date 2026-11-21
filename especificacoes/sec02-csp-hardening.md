# SEC-02 — Hardening CSP (EducaTudo)

**Atualizado:** 2026-11-21  
**Produção:** enforcement **baseline** (`unsafe-inline` + `unsafe-eval` em `script-src`) + Report-Only restritiva.  
**Homologação restrita:** `CSP_STRICT_ENFORCEMENT=true` (não ligar em produção sem checklist).

Relatório desta preparação: [`relatorios-seguranca/SEC-02-PREPARACAO-ENFORCEMENT-RESTRITO-2026-11-21.md`](../relatorios-seguranca/SEC-02-PREPARACAO-ENFORCEMENT-RESTRITO-2026-11-21.md).

## Componente

`App\Core\CspPolitica` ← `bootstrap/app.php` → `emitirCabecalhos()`.

| Modo | Header |
|---|---|
| Padrão | `Content-Security-Policy` baseline + `Content-Security-Policy-Report-Only` restrita |
| `CSP_STRICT_ENFORCEMENT=true` | Só `Content-Security-Policy` restrita (nonce, sem unsafe em scripts, `strict-dynamic`) |

## Nonce

- `random_bytes(16)` → base64 por request.
- Views: `$csp_nonce` / `$csp_nonce_attr` via `BaseController::enriquecerDadosCsp`.
- **Rede de segurança:** `aplicarNonceEmHtml()` no HTML final de `view()` e `viewWithLayout()` (inclui `<script src>` — exigido por `strict-dynamic`).

## Handlers HTML

Preferir `data-csp-click` / `data-csp-change` / `data-csp-action` + `/public/static/js/csp-event-delegation.js`.  
Ainda existem handlers `on*` legados (~750) — bloqueiam modo restrito até migrar.

## Relatórios

`POST /api/csp-report` → `CspReportController` (amostra 1/10).

## Testes

```bash
cd backend && ./vendor/bin/phpunit tests/Seguranca/Sec02CspPoliticaTest.php
```

## Checklist antes de produção restrita

- [ ] Staging com `CSP_STRICT_ENFORCEMENT=true` sem violações injustificadas
- [ ] Login professor/aluno/admin + provas + jornadas + Quill + MathJax + Jitsi
- [ ] Hotspots `on*` do professor migrados ou justificados
- [ ] Aprovação explícita para ligar a flag em produção

## Rollback

`CSP_STRICT_ENFORCEMENT=false` ou remover a variável.
