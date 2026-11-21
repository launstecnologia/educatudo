# SEC-02 — Preparação para CSP Enforcement restritivo

**Data:** 2026-11-21  
**Escopo:** monólito PHP EducaTudo — **sem** promoção automática em produção  
**Reteste que motivou:** produção ainda com `unsafe-inline` / `unsafe-eval` em `/`, `/login`, `/professor`

---

## 1. Diagnóstico

| Item | Estado |
|---|---|
| Emissor CSP | Único: `App\Core\CspPolitica` via `bootstrap/app.php` |
| Enforcement padrão | Baseline com `unsafe-inline` + `unsafe-eval` (compatibilidade) |
| Report-Only | Política restritiva: nonce + `strict-dynamic`, sem `unsafe-*` em `script-src` |
| Nginx / Cloudflare | Não emitem CSP |
| `eval()` PHP | Só server-side (boletim/fechamento) — irrelevante ao CSP do browser |
| Logs `/api/csp-report` | Endpoint ativo (amostra 1/10); sem histórico local neste ambiente |

**Bloqueadores reais para tirar `unsafe-inline`:**

1. Atributos `on*` restantes (~750 após migração parcial; eram ~1765).
2. Scripts inline sem nonce no **fonte** (~379) — **mitigados na resposta** por `CspPolitica::aplicarNonceEmHtml()` no `BaseController::view` / `viewWithLayout`.
3. MathJax / CDNs — cobertos por allowlist + nonce em tags `<script src>` (necessário com `strict-dynamic`).

---

## 2. O que foi alterado nesta rodada

### Núcleo
- `backend/app/Core/CspPolitica.php` — baseline / restrita / flag `CSP_STRICT_ENFORCEMENT` (default **false**)
- `backend/app/Core/BaseController.php` — nonce no HTML final de toda view/layout
- `backend/.env.example` — documenta `CSP_STRICT_ENFORCEMENT=false`
- `backend/public/static/js/csp-event-delegation.js` — `data-csp-click` / `data-csp-change` / submit de form

### Migração de handlers
- ~1000+ `onclick="fn()"` / `fn('x')` / numéricos → `data-csp-click`
- Modal de símbolos matemáticos (`inserirSimbolo`) → `data-csp-click` + `data-csp-arg`
- Sidebars: `toggleMenuGroup` (onda anterior)
- Logins: carregam `csp-event-delegation.js`

### Testes
- `backend/tests/Seguranca/Sec02CspPoliticaTest.php` — baseline, candidata, modo restrito, nonce

### Docs
- `especificacoes/sec02-csp-hardening.md` (atualizar na mesma entrega)
- Este relatório

---

## 3. Como homologar o modo restrito (sem ligar produção)

```bash
# .env local / staging apenas
CSP_STRICT_ENFORCEMENT=true
```

Com a flag:

- Só sai `Content-Security-Policy` restritiva (nonce, sem `unsafe-inline`/`unsafe-eval` em scripts).
- Produção deve permanecer `CSP_STRICT_ENFORCEMENT=false` até o checklist abaixo.

```bash
cd backend && ./vendor/bin/phpunit tests/Seguranca/Sec02CspPoliticaTest.php
curl -sI http://colag.localhost/ | grep -i content-security
```

---

## 4. Validação manual obrigatória (navegador autenticado)

Prioridade **professor** (reteste apontou o portal):

1. Login `/professor` e logout  
2. Dashboard + sidebar (expandir grupos)  
3. Provas — `provas/.../edit.php` (ainda com muitos `on*` complexos)  
4. Jornadas — módulos/exercícios  
5. Mural / diário (Quill)  
6. Planos de aula  
7. MathJax em enunciados  
8. OneSignal (se habilitado no tenant)  
9. WebSocket presença (admin/prof)

Demais perfis: aluno (prova + MathJax + VLibras), admin (filtros drawers), master, pais, monitor.

No DevTools → Console: violações CSP **não** devem aparecer como bloqueio no modo baseline; no modo restrito, cada violação restante é bug a corrigir.

---

## 5. Riscos restantes

| Risco | Severidade | Nota |
|---|---|---|
| ~750 `on*` / `onchange` complexos | Alta se `CSP_STRICT=true` | Quebram clique até migrar |
| Provas professor `edit.php` (~78 handlers) | Alta | Hotspot |
| MathJax sem `unsafe-eval` | Média | Validar tex-svg em prova/jornada |
| `strict-dynamic` + CDN | Baixa se nonce no HTML final | Coberto pelo BaseController |
| `polyfill.io` na allowlist | Baixa/média | Ainda usado no ai-agent |
| Promoção acidental em prod | Controlada | Flag default false |

**Critério de conclusão (produto):** ainda **não** atingido a 100% em produção — baseline continua com `unsafe-*`. A aplicação **está preparada** para rodar restrito via flag após zerar violações injustificadas na homologação.

---

## 6. Rollback

1. Garantir `CSP_STRICT_ENFORCEMENT=false` (ou ausente).  
2. Reverter commit desta entrega se necessário.  
3. Sem migration / sem Nginx.

---

## 7. Próximos passos recomendados

1. Migrar hotspots professor (`provas/edit`, `jornadas/modulos-exercicios`).  
2. Rodar staging com `CSP_STRICT_ENFORCEMENT=true` 48h + DevTools.  
3. Só então autorizar produção com a flag (ou promover a string restrita como default com aprovação explícita).
