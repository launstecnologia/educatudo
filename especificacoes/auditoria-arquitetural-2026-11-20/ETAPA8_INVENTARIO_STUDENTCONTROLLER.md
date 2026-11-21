# Etapa 8.1 — Inventário do StudentController

**Data:** 2026-11-21  
**Arquivo:** `backend/app/Painel/User/Controllers/StudentController.php`  
**Linhas no inventário (início da Etapa 8):** 4020 → **após Etapa 8: 3790**

Legenda: **A** migrar módulo · **B** módulo novo · **C** manter · **D** morto (removido nesta etapa)

---

## Públicos (rotas)

| Método | Rota | Class. | Notas |
|---|---|---|---|
| `dashboard` | GET `/dashboard` | C | Orquestra vários services; shell do aluno |
| `dashboardApiMontar` | GET `/dashboard/api/montar` | C | API montagem cards |
| `dashboardAgendaJson` | GET `/dashboard/agenda` | C | Agenda |
| `trocarTurmaCurso` | POST `/dashboard/trocar-turma` | C | Matrícula multi-turma |
| `perfil` | GET `/perfil` | C | Perfil |
| `desempenhoJornadas` | GET `/desempenho/jornadas` | C / A futuro | SQL denso; módulo jornadas |
| `desempenhoProvas` | GET `/desempenho/provas` | C / A futuro | Módulo provas |
| `notasBoletins` / `notas` / `boletim` | GET `/notas*` `/boletim` | C | **Não migrar** sem decisão (notas) |
| `carteira*` / `educaShop*` | `/carteira*` `/educashop*` | C | Ledger/créditos — só leitura possível depois; **não alterar cobrança** |
| `alterarSenhaObrigatoria` / `processarAlteracaoSenha` | `/aluno/alterar-senha*` | C | Auth |
| `vozParaTexto` | POST `/aluno/provas/voz-para-texto` | C | Compartilhado provas |
| `salvarRedacaoJornada` / `finalizarRedacaoJornada` | POST `/jornadas/redacao/*` | A | Alto risco; permanece no SC nesta etapa |
| `calendarioLetivo` | GET `/calendario-letivo` | **A feito** | → `AlunoCalendarioController` |

## Privados relevantes

| Método | Class. | Notas |
|---|---|---|
| Helpers boletim/provas (`boletinsVisiveis*`, `buildProvasMatriz*`) | C | Acoplados a notas/provas |
| Dashboard helpers (`getActiveJourneys`, `getStudentStats`, …) | C | Extrair só com testes de dashboard |
| `getLimitesDiarios` / `contarUsoDiarioAgrupado` / `podeExecutarAcao` / … | **D/A** | → `AlunoLimiteDiarioService`; mortos no SC removidos |
| `generateAIResponse` | **D** | Simulação chat — removido |
| `assertAlunoAtivo` | C | Usado por jornadas redação |

## Não reativar

Chat Tudinha (`/chat*`) — Etapa 7; handler 410.

## Decisões de negócio pendentes

1. Migrar `salvarRedacaoJornada` / `finalizarRedacaoJornada` para `Modulos/jornadas` (caracterizar créditos/OCR antes).  
2. Extrair desempenho jornadas/provas.  
3. Qualquer mudança em notas/boletim/carteira write.
