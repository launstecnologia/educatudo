# EducaTudo — Content-Security-Policy (CSP)

**Última atualização:** 2026-11-21  
**Status atual:** `Content-Security-Policy` em **enforcement** (baseline) **+** `Content-Security-Policy-Report-Only` (candidata SEC-02).

Documento detalhado da remediação: [`sec02-csp-hardening.md`](sec02-csp-hardening.md).

## Emissão

| Item | Valor |
|---|---|
| Componente | `App\Core\CspPolitica` |
| Bootstrap | `backend/bootstrap/app.php` → `CspPolitica::emitirCabecalhos()` |
| Nginx / Cloudflare | Não emitem CSP |

## Baseline (enforcement)

Ainda inclui `'unsafe-inline'` e `'unsafe-eval'` por compatibilidade (MathJax, scripts/on* legados). Allowlist cobre Quill, CKEditor, TinyMCE, OneSignal, VLibras, esm.sh, Jitsi e CDNs.

## Candidata (Report-Only)

Sem `'unsafe-eval'`; `script-src` com nonce e **sem** `'unsafe-inline'`; `report-uri /api/csp-report`. Não promove sozinha — exige autorização (Onda 4).

## Verificação

```bash
curl -sI https://educa.educatudo.com/ | grep -i content-security
```

Esperado: ambos os headers presentes.

## Texto para auditoria

> A plataforma EducaTudo emite Content-Security-Policy em modo enforcement (baseline compatível) e uma política candidata em Report-Only sem unsafe-eval e com nonces, centralizada em `App\Core\CspPolitica`. A promoção da candidata para bloqueio pleno depende da conclusão da migração de scripts inline/handlers e autorização explícita. Referência: `especificacoes/sec02-csp-hardening.md`.
