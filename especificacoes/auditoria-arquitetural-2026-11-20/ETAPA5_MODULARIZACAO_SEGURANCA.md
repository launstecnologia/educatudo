# Etapa 5 — Modularização, segurança e ownership

**Data:** 2026-11-20  
**Branch de trabalho:** continuidade da auditoria arquitetural  
**Deploy / migrations / schema / CSRF global / créditos / prompts IA / notas-boletim:** não alterados

Baseline histórico SC: ~8183 · Após Etapa 3: ~7163 · Após Etapa 4: ~7163 · **Após Etapa 5: 6836 linhas** (−327 vs Etapa 4; −1347 vs baseline histórico).

---

## 1. Resumo executivo

- **5.1** — Escritas do legado `/redacoes` (`salvarRascunho`, `excluirRascunho`, `ocultar`) com `verifyCsrfToken` no controller (bloqueio efetivo mesmo com `CSRF_MODE=audit`). Views enviam `_token`. Ownership por `aluno_id` no service.
- **5.2** — Hub (`/redacoes`) e `salvarRascunho` migrados para `AlunoRedacaoLegadoController` / `RedacaoAlunoLegadoService`. URLs e contratos preservados. IA/créditos **não** migrados.
- **5.3** — Ownership de jornadas aluno caracterizado (`jornadaPermiteTurma` / `jornadaPermiteAluno`); `show` exige checagem. Sem mudança de regra acadêmica. Nenhuma falha IDOR inequívoca exigindo patch além do já existente.
- **5.4** — SQL de planos `aprovado` centralizado em `PlanoAulaAlunoService` para Parent (web), ParentAcademicReadService e API ParentController. Assimetria listagem (turma [± ano civil]) × detalhe (turma ou série+ano) **documentada e preservada**.
- **5.5** — Gate módulos **72 OK** / segurança **40 OK**; smoke autenticado **não executado**; HTTP local sem servidor neste ambiente.
- **5.6** — Controllers enxutos no legado redação; policy de status no service de planos; sem interfaces artificiais.
- **5.7** — Este relatório + `estrutura.md` + preparação Etapa 6 (IA legada).

---

## 2. Arquivos modificados / criados

### Criados
- `backend/tests/Seguranca/RedacaoLegadoCsrfOwnershipTest.php`
- `backend/tests/Modulos/JornadaAlunoOwnershipTest.php`
- Este arquivo

### Modificados (principais)
- `AlunoRedacaoLegadoController.php` — hub, salvarRascunho, CSRF nas páginas de escrita
- `RedacaoAlunoLegadoService.php` — hub + salvarRascunho (já na fatia)
- `Modulos/redacoes/routes.php` — hub/salvar no módulo
- `PlanoAulaAlunoService.php` — `listarParaTurma($anoAtual = null|false)`, `turma_serie` no detalhe
- `ParentController.php` (web) — listagem/detalhe via service
- `ParentAcademicReadService.php` — lessonPlans/lessonPlan via service (sem filtro de ano)
- `API/Controllers/ParentController.php` — lessonPlans via service (com ano civil)
- `PlanoAulaAlunoVisibilidadeStatusTest.php`, `RedacaoAlunoLegadoCaracterizacaoTest.php`
- `.github/workflows/pr-validacao.yml` — filter inclui `JornadaAlunoOwnership`
- `backend/doc_sistema/estrutura.md`
- `PLANO_EXTRACAO_REDACOES_IA.md` (atualização Etapa 6)

---

## 3. Funcionalidades migradas (5.2)

| URL | Método HTTP | Destino |
|---|---|---|
| `/redacoes` | GET | `AlunoRedacaoLegadoController@hub` |
| `/redacoes/salvar-rascunho` | POST | `AlunoRedacaoLegadoController@salvarRascunho` |
| (já Etapa 3/4) historico, ver, rascunhos, ocultar, páginas escrever | GET/POST | módulo `redacoes` |

**Não migrado (Etapa 6):** `createEssay`, `gerarTemaIA`, `transcreverImagem`, `corrigirRedacao`.

---

## 4. Métodos removidos do StudentController

Nesta etapa (já refletido no working tree): hub `essays`, `salvarRascunho` e helpers de listagem do hub — responsabilidade no módulo. Contagem atual do arquivo: **6836** linhas.

Ainda no SC (IA): `createEssay`, `gerarTemaIA`, `transcreverImagem`, `corrigirRedacao` (+ redação de jornada).

---

## 5. Linhas antes/depois

| Momento | Linhas SC (aprox.) |
|---|---|
| Baseline histórico | 8183 |
| Pós Etapa 3 | 7163 |
| Pós Etapa 4 | 7163 |
| **Pós Etapa 5** | **6836** |

Delta Etapa 5: **−327** linhas.

---

## 6. Correções de segurança

1. CSRF **efetivo no controller** para POST salvar/excluir/ocultar redação legada (independente de audit global).
2. Ownership `aluno_id` em update/insert/delete de rascunho e ocultar.
3. Páginas escrever/continuar geram CSRF para o front.
4. Planos: allowlist `aprovado` única via service em todos os leitores aluno/pais/API.
5. Jornadas: testes de autorização por turma / `turmas_selecionadas`; sem regressão de regra.

**CSRF global:** não alterado. Exceções globais: não ampliadas.

---

## 7. Testes executados

| Suite | Resultado |
|---|---|
| PHPUnit módulos (filter migrados + jornadas ownership) | **72 OK**, 208 asserts |
| PHPUnit Seguranca | **40 OK**, 83 asserts |
| **Total gate** | **112 testes**, **291 assertions** |
| Baseline Etapa 4 | 63 + 34 = 97 testes · 245 asserts |
| PHP lint arquivos tocados | OK |
| Smoke HTTP local | Servidor não respondeu neste ambiente (não classificado como aprovado) |
| Smoke autenticado | **Não executado** — sem contas de teste |

---

## 8. Falhas encontradas

- Teste inicial `show` com janela curta de source — corrigido (assert ampliado).
- Sem falha de ownership de jornada exigindo patch de produto nesta fatia.

---

## 9. Riscos residuais

1. Rotas IA legadas ainda no SC — CSRF/ownership a revalidar na Etapa 6.
2. `CSRF_MODE=audit` continua sem bloquear rotas que **não** chamam `verifyCsrfToken`.
3. Assimetria planos listagem×detalhe e listagem com/sem ano civil entre adaptadores pais.
4. Superfície grande de `JourneyController` — ownership centralizado em métodos privados; extração para Policy/Service fica para fatia futura.
5. Smoke autenticado pendente de homologação.

---

## 10. Pendências de homologação

- [ ] Smoke autenticado aluno: hub `/redacoes`, salvar rascunho, ocultar, excluir.
- [ ] Smoke jornadas: acesso por ID da própria turma vs outra turma.
- [ ] Pais/API: listagem planos aprovados e detalhe 404 para rascunho.
- [ ] Confirmar clientes móveis/API não dependem de planos fora do ano civil na API Parent (filtro ano preservado).

---

## 11. Rollback

1. Reverter rotas de `/redacoes` e `/redacoes/salvar-rascunho` para `StudentController` **somente se** o código dos métodos for restaurado do histórico git.
2. Reverter consumers de `PlanoAulaAlunoService` nos Parent* para SQL local (mantendo `status = 'aprovado'`).
3. Não reativar rotas legadas desabilitadas.
4. Sem migration — rollback só de código.

---

## 12. Próximas prioridades (Etapa 6)

Ver `PLANO_EXTRACAO_REDACOES_IA.md` atualizado:

1. Caracterizar `createEssay` (sem IA) + CSRF/ownership.
2. `gerarTemaIA` com mock OpenAI + débito/limite/idempotência.
3. `transcreverImagem` (upload MIME + OCR mock).
4. `corrigirRedacao` por último; job async se >2s; **não** alterar prompts/preços.

Checklist pré-migração IA: créditos, chamadas externas, persistência, idempotência, falhas, jobs/filas, testes obrigatórios no plano.

---

## Assimetria planos (obrigatório documentar)

| Consumidor | Listagem | Detalhe |
|---|---|---|
| Aluno / Parent web / API Parent | turma + **ano civil** `created_at` + `aprovado` | `buscarNaoDeletado` + `alunoPodeVisualizar` (turma **ou** série+ano letivo) |
| ParentAcademicReadService | turma + `aprovado` **sem** filtro de ano (`listarParaTurma(..., false)`) | mesma turma do aluno + `aprovado` (mais restrito que série) |

Não unificar automaticamente sem decisão de produto.
