# Etapa 4 — Consolidação arquitetural e preparação

**Data:** 2026-11-20  
**Branch:** `etapa4-consolidacao-arquitetural`  
**Deploy / migrations / CSRF global / deploy-main / preços-créditos / notas-boletim:** não alterados

Baseline histórico SC: ~8183 · Após Etapa 3: ~7163 · Após Etapa 4: **7163** (sem nova remoção de métodos SC; foco em regra de planos + docs).

---

## 1. Resumo executivo

- **4.1** — Aluno (e APIs/portal pais que espelham o aluno) **não veem** planos `rascunho`/`rejeitado`; só `aprovado`.  
- **4.2** — Regressão módulos **63 OK**, segurança **34 OK**; smoke sem auth **302**. Smoke autenticado **não executado** (sem contas de teste).  
- **4.3** — Mapa de ownership; correções só onde a regra era clara (planos).  
- **4.4–4.6** — Padrão, acoplamento, planos de extração redação IA e Tudinha (sem migrar críticos).  
- **4.7** — CI já executa gate de módulos migrados (inclui novos testes de planos).  
- **4.8** — Este relatório + docs auxiliares.

---

## 2. Status por sub-etapa

| Etapa | Status |
|---|---|
| 4.1 Visibilidade planos | **Concluída** |
| 4.2 Homologação | **Concluída** (auth smoke pendente) |
| 4.3 Ownership | **Concluída** (mapa + correções claras) |
| 4.4 Padronização | **Concluída** (docs + sem refatoração em massa) |
| 4.5 Redações IA | **Concluída** (plano; sem migração IA) |
| 4.6 Tudinha | **Concluída** (inventário/plano) |
| 4.7 CI | **Concluída** |
| 4.8 Documentação | **Concluída** |

---

## 3. Arquivos criados / modificados (4.x)

### Criados
- `tests/Modulos/PlanoAulaAlunoVisibilidadeStatusTest.php`
- `ETAPA4_OWNERSHIP_AUDITORIA.md`
- `PADRAO_ARQUITETURAL_MODULOS.md`
- `MAPA_ACOPLAMENTO_MODULOS.md`
- `PLANO_EXTRACAO_REDACOES_IA.md`
- `PLANO_EXTRACAO_TUDINHA.md`
- Este arquivo

### Modificados
- `PlanoAulaAlunoService.php` — filtro `status IN ('aprovado')` em listar/buscar + `alunoPodeVisualizar`
- `ParentController.php` — listagem/detalhe/PDF só `aprovado`
- `ParentAcademicReadService.php` — API academic só `aprovado`
- `API/Controllers/ParentController.php` — listagem só `aprovado`
- `PlanoAulaAlunoCaracterizacaoTest.php` — asserts alinhados à 4.1
- `estrutura.md`
- `.github/workflows/pr-validacao.yml` (comentário de cobertura)

---

## 4. Regras de segurança corrigidas

1. Rascunhos de planos inacessíveis por listagem, ID e APIs do portal/pais.  
2. Status desconhecido/`publicado`/`rejeitado`/`null` **não** liberados (allowlist `aprovado` apenas).  
3. Professor/admin: fluxos `LessonPlanController` **inalterados**.

Anexos dedicados de planos: **não encontrados** no código — N/A.

---

## 5. Testes executados (resultados reais)

| Suite | Resultado |
|---|---|
| PHPUnit módulos (filter migrados + planos) | **63 OK**, 187 asserts |
| PHPUnit Seguranca | **34 OK**, 58 asserts |
| Smoke HTTP local sem auth | planos/mural/redacoes → **302 /**; exercicios → **302 /dashboard** |
| Smoke autenticado | **Não executado** — sem contas de teste autorizadas |
| Suite completa | Não gate; falhas legadas pré-existentes |

---

## 6. Mudanças de comportamento autorizadas

- Portal aluno / pais / API pais: só planos **`aprovado`**.  
- Rascunho por URL → tratado como “não encontrado” (flash+redirect / null API).

---

## 7. Responsabilidades extraídas (acumulado 2–4)

Mural, planos, redação legado (GET/ownership), exercícios mortos removidos; contagens dashboard no `MuralRecadoService`.

---

## 8. Dependências

Ver `MAPA_ACOPLAMENTO_MODULOS.md`.

---

## 9. Riscos residuais

1. Homologação autenticada pendente.  
2. Assimetria turma×série na listagem vs detalhe de planos.  
3. SQL planos ainda duplicado (aluno service vs Parent*).  
4. IA redação + Tudinha ainda no SC.  
5. `ocultar` redação legado ainda sem CSRF explícito no controller (middleware audit).

---

## 10. Pendências de produto

1. Confirmar se algum status além de `aprovado` deve liberar (hoje: não).  
2. Unificar ou manter assimetria listagem/detalhe planos.  
3. Prioridade da próxima fatia: hub `/redacoes` vs Tudinha leitura.

---

## 11. Redução SC

| Marco | Linhas (aprox.) |
|---|---:|
| Inventário Etapa 2 | 8183 |
| Pós Etapa 3 | 7163 |
| Pós Etapa 4 | **7163** |

---

## 12. Próximas candidatas

1. Hub `/redacoes` sem IA (`essays` + `salvarRascunho`)  
2. Ownership jornadas aluno  
3. Tudinha consulta (só leitura)  
4. Extrair helper compartilhado de planos aprovados  

---

## 13. Rollback 4.1

1. Remover cláusulas `status`/`status IN` do `PlanoAulaAlunoService` e Parent*/API.  
2. Reverter testes de visibilidade.  
3. Sem migration.
