# SEC-10 / remediação complementar — sessão, cache tenant e master

**Data:** 2026-11-21  
**Ambiente:** análise estática + PHPUnit local (SQLite em memória / evidência de código)  
**Escopo:** professor inativo com sessão, cache Redis de escolas desativadas, painel master, revalidação IDOR chat  
**Regra:** correções locais; **sem deploy**, **sem migrations**, **sem limpeza global de Redis**  
**SEC-10 (IDOR chat):** **não encerrada automaticamente** — permanece em homologação

---

## Resumo executivo

| Problema | Status | Resultado |
|---|---|---|
| P1 — Professor desativado com sessão ativa | Confirmado + corrigido | Aprovado (unitário) |
| P2 — Cache Redis de escolas desativadas | Confirmado (fast-path) + reforçado | Aprovado (unitário) |
| P3 — Segurança do painel master | Proteções existentes + hardening produção | Aprovado (evidência código) |
| P4 — IDOR chat pós-alterações | Mantido + reforços ativo/CSRF/erros | Aprovado (unitário) |
| Multi-tenant complementar | X-Tenant prod OK; identidade no cache | Aprovado (unitário) |

---

## Tabela de entregáveis

| Problema | Causa-raiz | Arquivos afetados | Correção | Testes | Resultado | Impacto | Risco residual |
|---|---|---|---|---|---|---|---|
| Professor inativo via jornada | `professorLecionaTurmaPorJornada` não exigia `professores.ativo=1`; sessão não revalidava ativo | `PoliticaAcessoProfessorAluno.php`, `AuthManager.php`, `AuthMiddleware.php`, `ChatController.php` | Gate `professorEstaAtivo()`; `cadastroAindaAtivoNoBanco()` em `isLoggedIn()`; chat exige ativo na sessão | `PoliticaAcessoProfessorAlunoTest`, `AuthManagerCadastroAtivoTest`, `Sec10ChatIdorComplementoTest` | Aprovado | Toda rota protegida por Auth passa a consultar ativo (1 query/request) | Homologar desativação com sessão real no Docker |
| Cache escola desativada | Fast-path aceitava cache legado sem `ativo` até TTL | `TenantResolver.php`, `bootstrap_multi_tenant.php`, `MasterEscolasController.php`, `ClonarEscolaService.php` | Cache exige `ativo=1` + Host/slug coerente; invalidação central `invalidarCacheEscola` | `TenantCacheValidacaoTest` | Aprovado | Cache legado é descartado uma vez (miss → MASTER) | Homologar desativar escola no Master com Redis vivo |
| Master via path em host de escola | `/master` em qualquer host abria PDO master | `bootstrap_multi_tenant.php` | Em produção + `MASTER_DOMAIN` definido: path `/master` fora do domínio master → 404 | `MasterAcessoCodigoTest` | Aprovado | Dev/local continua permitindo `/master` em outros hosts | Confirmar `MASTER_DOMAIN` e `APP_ENV=production` no servidor |
| IDOR chat | Hipótese de regressão após P1 | `ChatController.php`, política | Mantidas checagens de vínculo; IDs só da sessão; erros genéricos | `Sec10ChatIdorComplementoTest` + política | Aprovado | Nenhum | Harness HTTP horizontal (aluno A/B, 2 escolas) ainda recomendado |

---

## Evidências de testes (PHPUnit)

```text
AuthManagerCadastroAtivoTest ........ 5 OK
Sec10ChatIdorComplementoTest ........ 6 OK
PoliticaAcessoProfessorAlunoTest .... 14 OK
TenantCacheValidacaoTest ............ 7 OK
MasterAcessoCodigoTest .............. 4 OK
Total: 36 testes — todos aprovados
```

Comando (a partir de `backend/`):

```bash
./vendor/bin/phpunit tests/Seguranca/AuthManagerCadastroAtivoTest.php
./vendor/bin/phpunit tests/Seguranca/Sec10ChatIdorComplementoTest.php
./vendor/bin/phpunit tests/Seguranca/PoliticaAcessoProfessorAlunoTest.php
./vendor/bin/phpunit tests/Seguranca/TenantCacheValidacaoTest.php
./vendor/bin/phpunit tests/Seguranca/MasterAcessoCodigoTest.php
```

---

## Arquivos modificados

1. `backend/app/Policies/PoliticaAcessoProfessorAluno.php`
2. `backend/app/Core/AuthManager.php`
3. `backend/app/Middleware/AuthMiddleware.php`
4. `backend/app/Painel/Integrations/Controllers/ChatController.php`
5. `backend/app/Core/TenantResolver.php`
6. `backend/app/Core/bootstrap_multi_tenant.php`
7. `backend/app/Painel/Master/Controllers/MasterEscolasController.php`
8. `backend/app/Painel/Master/Services/ClonarEscolaService.php`
9. `backend/tests/Seguranca/PoliticaAcessoProfessorAlunoTest.php` (ampliado)
10. `backend/tests/Seguranca/TenantCacheValidacaoTest.php` (novo)
11. `backend/tests/Seguranca/AuthManagerCadastroAtivoTest.php` (novo)
12. `backend/tests/Seguranca/MasterAcessoCodigoTest.php` (novo)
13. `backend/tests/Seguranca/Sec10ChatIdorComplementoTest.php` (novo)
14. `relatorios-seguranca/SEC-10-REMEDIACAO-SESSAO-CACHE-MASTER.md` (este)

---

## Diff resumido

- **Política:** `podeAcessar` / listagem falham se `professores.ativo != 1` (mesmo com jornada ativa).
- **Auth:** `isLoggedIn()` consulta ativo no banco do tenant; logout se inativo/ausente/erro.
- **Chat:** sessão só com cadastro ativo; respostas de erro sem detalhe interno.
- **Tenant cache:** payload com `ativo`; validação de identidade; miss fail-closed.
- **Bootstrap:** produção restringe `/master` ao `MASTER_DOMAIN`; fast-path exige `ativo=1`.
- **Master update/clone:** invalidação via `TenantResolver::invalidarCacheEscola`.

---

## Problema 3 — evidências (sem mudança desnecessária além do hardening)

- Sessão master: `master_user_id` (não `user_id` da escola).
- Controllers Master chamam `requireMaster` / `requireMasterAuth`.
- Login school (`AuthManager`) não grava `master_user_id`.
- Conexão master ≠ privilégio; OTP master permanece no fluxo existente.
- `X-Tenant` ignorado quando `ENVIRONMENT === production` (definido antes do bootstrap MT).

---

## Homologação pendente (produção / Docker)

1. Desativar professor com sessão aberta → próxima request em `/professor/chat` deve negar.
2. Desativar escola no Master → request imediata no domínio deve 403 (não depender do TTL).
3. Em produção: `https://<escola>/master` → 404; `https://<MASTER_DOMAIN>/master` → login.
4. Cookie de escola A em host B → sem painel (já coberto SEC-04; revalidar).
5. Harness HTTP IDOR chat (dois alunos, dois professores, duas escolas) — **não usar dados reais**.

---

## Rollback seguro

1. Reverter o commit/diff dos arquivos listados.
2. Não é necessário migration.
3. Cache: chaves `educatudo_tenant_*` e `educatudo_tenant_config_*` regeneram sozinhas; opcional invalidar só essas por escola afetada (não `FLUSHALL`).

---

## Critérios de aceite

| Critério | Estado |
|---|---|
| Professores inativos sem chat protegido | Atendido (código + unitário) |
| Escolas desativadas após invalidação/confirmção | Atendido (código + unitário); homologar Redis |
| Cache não seleciona tenant errado | Atendido |
| Rotas master protegidas | Atendido + hardening prod |
| Sessões não cruzam escolas | Mantido (`sessaoConfereComEscolaAtual`) |
| Correção IDOR íntegra | Mantida; SEC-10 **não** marcada encerrada |
| Testes automatizados | 36 OK |
| Monólito modular | Preservado |
