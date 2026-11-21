# Etapa 2.1 — Comparativo das contagens do mural

**Data:** 2026-11-20  
**Status:** **Opção A consolidada** (Dashboard → `MuralRecadoService`)  
**Escopo:** contagens do mural no dashboard do aluno alinhadas ao portal `/mural-recados`.

---

## 1. Consumidores de `muralResumo` / contagens

| Método | Arquivo | Consumidor |
|---|---|---|
| `DashboardAlunoService::muralResumo` → `contarMural` → `MuralRecadoService::contarParaAluno` | `DashboardAlunoService.php` | `montarCards` → JSON `/dashboard/api/montar` (`mural_nao_lidos`, `mural_total`, popup) |
| `MuralRecadoService::contarParaAluno` | `MuralRecadoService.php` | Dashboard HTML (StudentController) + portal `/mural-recados` |
| `MuralRecadoService::listarNaoVistosResumoParaAluno` | idem | Lista do dashboard HTML (já alinhada; **não** usada pelo popup da API) |

---

## 2. Contrato atual de `/dashboard/api/montar`

Resposta (estrutura preservada):

```json
{
  "success": true,
  "cards": {
    "jornadas_abertas": 0,
    "mural_nao_lidos": 0,
    "provas_disponiveis": 0,
    "mural_total": 0,
    "jornada_redacao_pendentes": 0
  },
  "mural": {
    "nao_lidos": 0,
    "total": 0,
    "exibir_popup": false,
    "qtd_popup": 0
  }
}
```

| Campo | Tipo | Uso no front (`dashboard-index-loader.js`) |
|---|---|---|
| `cards.mural_nao_lidos` | int | Badge do card mural |
| `cards.mural_total` | int | Texto “N recados” no link rápido |
| `mural.exibir_popup` | bool | Abre modal se true |
| `mural.qtd_popup` | int | Texto “N recados novos” no modal (`min(nao_lidos, 10)`) |

**Popup:** só contagem + CTA para `/mural-recados`. **Não** há listagem de títulos na API. Sem alteração adicional no popup.

---

## 3. Tabela comparativa (legado × oficial)

| Critério | Legado `sqlMuralBase` (removido) | Oficial `MuralRecadoService` (atual) |
|---|---|---|
| Recado individual (`aluno_id`) | Não | **Sim** |
| `publico = professores` | Podia contar | **Não** |
| Turma / todos / validade / não lidos | Sim | Sim |

Fixture aluno 10 / turma 24:

| Antes (legado) | Depois (oficial) |
|---|---|
| Conjunto `{2,3,6}` | Conjunto `{1,2,3}` |

---

## 4. Cache Redis (30s)

| Item | Situação |
|---|---|
| Chave | `dashboard:cards:v2:{TENANT_SLUG}:{alunoId}:{turmaId}:{acao}:{jornadas}` |
| Isolamento | Por **escola** (`TENANT_SLUG`) e **aluno** |
| Versionamento | **v1 → v2** na consolidação (evita servir contagem legada até TTL) |
| Invalidação | Troca de turma + **marcar visto** (`AlunoMuralController`) |
| FLUSHDB/ALL | **Não** usados |

Risco residual: até 30s de stale se Redis falhar na invalidação; TTL curto mitiga.

---

## 5. Popup / listagem

Não foi preciso alterar outra consulta: o modal da API não lista recados. A lista do dashboard SSR já usava `listarNaoVistosResumoParaAluno`.

---

## 6. Testes

- `backend/tests/Modulos/MuralContagemDuplicadaCaracterizacaoTest.php` — baseline histórico legado×oficial  
- `backend/tests/Modulos/MuralDashboardContagemConsolidacaoTest.php` — paridade pós-A + contrato JSON  
- `backend/tests/Modulos/MuralRecadoAlunoServiceTest.php`  
- Suite `backend/tests/Seguranca/`

---

## 7. Rollback

1. Reverter `DashboardAlunoService` (restaurar `sqlMuralBase` / `paramsMuralBase`, cache `v1`).  
2. Reverter invalidação em `AlunoMuralController::marcarVisto` se necessário.  
3. Sem migration; chave `v2` expira sozinha (30s).

---

## 8. Arquivos alterados (Opção A)

| Arquivo | Mudança |
|---|---|
| `DashboardAlunoService.php` | Delega contagem; remove SQL legado; cache `v2` |
| `AlunoMuralController.php` | Invalida cache após marcar visto |
| Testes + este MD | Paridade e contrato |
