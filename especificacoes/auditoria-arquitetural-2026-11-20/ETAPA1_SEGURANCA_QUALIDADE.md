# Etapa 1 — Fortalecimento da Segurança e Qualidade

**Data:** 2026-11-20  
**Status:** implementada (A + B + C)  
**Plano de origem:** Cursor plan `Etapa 1 Segurança`  
**Docs de arquitetura:** nesta mesma pasta (`ARQUITETURA_TECNICA_*`, `DIAGNOSTICO_*`, `MAPA_*`, `PLANO_*`)

---

## Como retomar depois

1. Ler este arquivo.
2. Inventário de ownership pendente: [inventario-ownership-proximos.md](inventario-ownership-proximos.md).
3. SEC-10 chat (já corrigido antes desta etapa): `especificacoes/correcao-sec-10-idor-chat-professor-2026-11-20.md`.
4. Revalidar testes:

```bash
cd backend && ./vendor/bin/phpunit tests/Seguranca --testdox
```

---

## O que foi feito

### Entrega A — CI de Pull Request

| Item | Detalhe |
|---|---|
| Arquivo | `.github/workflows/pr-validacao.yml` |
| Disparo | `pull_request` em `main`/`master` + `workflow_dispatch` |
| Gate | lint PHP alterados + PHPUnit `tests/Seguranca` |
| Informativo | suite PHPUnit completa com `continue-on-error` (há falhas pré-existentes fora do escopo) |
| **Não tocado** | `.github/workflows/deploy-main.yml` |

**Rollback A:** apagar `pr-validacao.yml`.

### Entrega B — Ownership professor→aluno

| Item | Detalhe |
|---|---|
| Policy | `backend/app/Policies/PoliticaAcessoProfessorAluno.php` (já existia — chat SEC-10) |
| Wiring | `backend/app/Painel/User/Controllers/TeacherController.php` |
| Métodos | `updateStudentPassword`, `viewStudent`, `studentProvas`, `studentRelatorio` |
| Testes | `backend/tests/Seguranca/ProfessorAlunoOwnershipResetSenhaTest.php` |
| Inventário | `inventario-ownership-proximos.md` |

**Regra:** professor só acessa aluno com vínculo (turma via JSON `professores.turmas` **ou** `jornadas`) + aluno ativo + matrícula ativa se houver.

**Importante:** rotas `/professor/student*` ainda passam por `alunosProfessorPausado()` (feature pausada → redirect dashboard). A policy protege o código **quando** o pause for removido.

**Rollback B:** reverter diff do `TeacherController` + remover teste/inventário novos.

### Entrega C — CSRF centralizado (gradual)

| Item | Detalhe |
|---|---|
| Middleware | `backend/app/Middleware/CsrfMiddleware.php` |
| Exceções | `backend/config/csrf_excecoes.php` |
| Registro | `backend/config/routes.php` — `Csrf` aninhado **dentro** de `Auth` |
| Env | `CSRF_MODE=audit` em `backend/.env.example` |
| Testes | `backend/tests/Seguranca/CsrfMiddlewareTest.php` |

**Comportamento:**
- Só métodos `POST` / `PUT` / `PATCH` / `DELETE` no grupo Auth (sessão).
- Token: `$_POST[_token]` ou headers `X-CSRF-TOKEN` / `X-XSRF-TOKEN`.
- `audit` (padrão): loga falha e **não** bloqueia.
- `enforce`: bloqueia (403 JSON / 419 HTML).
- **Fora do middleware:** JWT (`/api/*`), webhooks, `routes.public.php`, rotas Master (ainda gap residual).

**Próximo passo CSRF (quando for o caso):**

1. Rodar em `audit` e olhar logs `[CSRF] validação falhou`.
2. Corrigir POSTs sem `_token` ou listar em `csrf_excecoes.php` com justificativa.
3. Ligar `CSRF_MODE=enforce` no `.env` (não commitar segredo; só a chave no example).

**Rollback C:** remover nest `Csrf` em `routes.php`; apagar middleware, config, teste; remover `CSRF_MODE` do `.env` se existir.

---

## Testes executados (2026-11-20)

```text
cd backend && ./vendor/bin/phpunit tests/Seguranca --testdox
→ OK (24 tests, 42 assertions)
```

Inclui: AcessoArquivoPrivado, CsrfMiddleware, PoliticaAcessoProfessorAluno, ProfessorAlunoOwnershipResetSenha.

Suite completa: 3 falhas **pré-existentes** (boletim / grupo regras) — não introduzidas nesta etapa; CI não bloqueia por elas.

---

## Riscos residuais (para próxima puxada)

1. CSRF ainda em `audit` — proteção efetiva só com `enforce`.
2. Painel Master fora do CSRF centralizado.
3. Ownership TeacherController atrás do pause de produto.
4. Candidatos do inventário: jornadas, ocorrências, comunicação, `relatorioAluno`, reteste HTTP pais.
5. CI PHP 8.4 vs docs que citam 8.2 (drift documental).

---

## O que esta etapa **não** fez (de propósito)

- Migrations / alteração de banco
- Mudança em boletim / fechamento / notas
- Microsserviços ou troca de framework
- Alteração do workflow de deploy
- Pentest / HTTP em produção
- Ligar `CSRF_MODE=enforce` em produção

---

## Ordem sugerida na próxima sessão

1. Revisar logs CSRF em ambiente local/staging → decidir `enforce`.
2. Avançar inventário ownership (um endpoint por vez).
3. Opcional: CSRF no grupo Master com as mesmas regras.
4. Opcional: tornar a suite PHPUnit completa um gate depois de corrigir as 3 falhas legadas.

---

## Mapa rápido de arquivos

```
.github/workflows/pr-validacao.yml
backend/app/Middleware/CsrfMiddleware.php
backend/app/Painel/User/Controllers/TeacherController.php
backend/config/csrf_excecoes.php
backend/config/routes.php
backend/.env.example
backend/tests/Seguranca/CsrfMiddlewareTest.php
backend/tests/Seguranca/ProfessorAlunoOwnershipResetSenhaTest.php
especificacoes/auditoria-arquitetural-2026-11-20/
  ETAPA1_SEGURANCA_QUALIDADE.md          ← este arquivo
  inventario-ownership-proximos.md
  ARQUITETURA_TECNICA_EDUCATUDO.md
  DIAGNOSTICO_ARQUITETURAL.md
  MAPA_MODULOS_EDUCATUDO.md
  PLANO_EVOLUCAO_ARQUITETURAL.md
```

*Documento de continuidade — não altera runtime; serve para retomar o trabalho.*
