# Etapa 8 — Consolidação dos Controllers legados

**Data:** 2026-11-21  
**Deploy / migrations / schema / créditos / prompts / CSRF global / notas-boletim:** não alterados  
**Chat Tudinha:** permanece descontinuado (não reativado)

Baseline histórico SC: ~8183 · Pós Etapa 7: **4020** · **Pós Etapa 8: 3790** (−230 nesta etapa; −4393 vs histórico).

---

## 1. Resumo executivo

- Inventário completo do SC restante (doc 8.1).
- Limites diários extraídos para `AlunoLimiteDiarioService` (dashboard + redações IA); helpers mortos do SC removidos.
- Calendário letivo do aluno migrado para `AlunoCalendarioController` (URL preservada).
- Código morto pós-chat: `generateAIResponse`, `ChatSafetyMonitorService`, referência `/chat` no service-worker.
- Carteira / notas / redação de jornada: **mantidos** no SC (risco comercial/acadêmico).
- Gate: **149 testes** / **431 assertions** (baseline Etapa 7: 137 / 397).

---

## 2. Inventário

Ver `ETAPA8_INVENTARIO_STUDENTCONTROLLER.md`.

---

## 3. Funcionalidades migradas / consolidadas

| Item | Destino |
|---|---|
| Limites diários (dashboard + gate) | `Dominio/Pessoas/Services/AlunoLimiteDiarioService` |
| Redação IA usa o mesmo service de limite | `RedacaoAlunoIaService` delega |
| GET `/calendario-letivo` | `User/AlunoCalendarioController@index` |

---

## 4. Arquivos criados / modificados

### Criados
- `AlunoLimiteDiarioService.php`
- `AlunoCalendarioController.php`
- `tests/Modulos/AlunoLimiteDiarioServiceTest.php`
- `tests/Modulos/Etapa8ConsolidacaoControllersTest.php`
- `ETAPA8_INVENTARIO_STUDENTCONTROLLER.md`
- Este arquivo

### Removidos
- `Infra/Services/ChatSafetyMonitorService.php` (+ entrada em `services_map.php`)

### Modificados
- `StudentController.php` — dashboard usa service; remoção helpers mortos + calendário
- `RedacaoAlunoIaService.php` — limites via service compartilhado
- `config/routes/student.php`
- `public/service-worker.js`
- `pr-validacao.yml`, `estrutura.md`

---

## 5. Controllers / Services novos

- `AlunoCalendarioController`
- `AlunoLimiteDiarioService`

---

## 6. Métodos removidos do SC

- `calendarioLetivo`
- `getLimitesDiarios`, `contarUsoDiarioAgrupado`, `getLimiteDiario`, `contarUsoDiario`, `podeExecutarAcao`
- `generateAIResponse`

---

## 7. Linhas antes/depois

| Momento | SC |
|---|---|
| Histórico | ~8183 |
| Pós Etapa 7 | 4020 |
| **Pós Etapa 8** | **3790** |

Delta Etapa 8: **−230**.

---

## 8. Testes executados

| Suite | Resultado |
|---|---|
| PHPUnit módulos (filter gate) | **109 OK**, 348 asserts |
| PHPUnit Seguranca | **40 OK**, 83 asserts |
| **Total** | **149 testes**, **431 assertions** |
| Baseline Etapa 7 | 137 · 397 |
| PHP lint | OK nos arquivos tocados |
| Smoke autenticado | **Não executado** |

---

## 9. Correções de segurança

Nenhuma falha IDOR nova corrigida nesta etapa. Desabilitação do chat (Etapa 7) permanece. Limites e calendário preservam isolamento PDO (sem `escola_id`).

---

## 10. Dependências eliminadas

- SC ↔ helpers de limite locais (agora Domínio)
- SC ↔ calendário (controller dedicado)
- Autoload de `ChatSafetyMonitorService`
- SW sem path exclusivo `/chat`

---

## 11. Riscos residuais

1. `salvarRedacaoJornada` / `finalizarRedacaoJornada` ainda no SC (~900 linhas) — alto risco de migrar sem suite dedicada.
2. Carteira/EducaShop write paths no SC.
3. Notas/boletim no SC.
4. Flag FeatureGate `chat` ainda necessária para jornadas.
5. Dashboard ainda concentra SQL de jornadas/provas/redações recentes.

---

## 12. Pendências de homologação

- [ ] Dashboard: widget de limites diários coerente.
- [ ] `/calendario-letivo` aluno (visibilidade `visivel_aluno`).
- [ ] Gerar tema / corrigir redação (limites + créditos).
- [ ] Confirmar 410 chat + jornadas “explica exercício”.

---

## 13. Rollback

1. Reapontar `/calendario-letivo` para `StudentController@calendarioLetivo` (restaurar método do git).
2. Restaurar helpers de limite no SC e reverter `AlunoLimiteDiarioService` no dashboard/redações.
3. Restaurar `ChatSafetyMonitorService` + map se necessário (não há consumidor HTTP).
4. Sem migration.

---

## 14. Recomendações Etapa 9

1. Caracterizar e migrar redação de jornada → `Modulos/jornadas`.
2. Extrair desempenho jornadas/provas com testes de contrato.
3. Avaliar leitura-only de carteira num service (sem tocar ledger).
4. Decidir rename da flag `chat` → “explica exercício”.
5. Continuar redução do dashboard via `DashboardAlunoService`.
