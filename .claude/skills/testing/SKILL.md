---
name: testing
description: Como rodar e escrever testes E2E do EducaTudo com Playwright. Use ao criar testes, verificar uma feature no navegador ou quando o usuário pedir para testar algo na plataforma.
---

# Testes E2E — EducaTudo

Os testes Playwright ficam na **raiz do repo** (`playwright.config.ts`), não em `src/`. A pasta `tests/` é ignorada pelo git (só `src/` é versionado).

## Pré-requisito

Plataforma rodando local: `docker-compose up -d` → http://localhost:8000

## Rodar

```bash
npx playwright test                 # tudo
npx playwright test tests/foo.spec.ts
npx playwright test --headed        # com navegador visível
npx playwright show-report          # relatório da última execução
```

## Escrever teste novo

- Um arquivo por fluxo: `tests/<modulo>-<perfil>.spec.ts` (ex.: `essays-student.spec.ts`).
- URLs por perfil: aluno `/`, professor `/professor`, admin `/admin`, master `/master`.
- Login: fazer via UI no `beforeEach` (não há fixture de sessão pronta) ou reusar `storageState` se o teste anterior já criou.
- Multi-tenant local: single-tenant por padrão (`MULTI_TENANT=false` no `.env`); para testar resolução de tenant, enviar header `X-Tenant: <slug>`.
- Nunca apontar teste para ambiente de produção.

## O que priorizar

Fluxos críticos com dinheiro ou nota: pagamento/créditos, envio e correção de redação, provas. Smoke de login por perfil antes de qualquer coisa.
