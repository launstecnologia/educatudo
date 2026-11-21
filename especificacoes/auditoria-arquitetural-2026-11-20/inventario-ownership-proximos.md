# Inventário ownership — próximos candidatos (Etapa 1 / Entrega B)

**Data:** 2026-11-20  
**Escopo:** endpoints que usam `aluno_id` / id de aluno sem (ainda) `PoliticaAcessoProfessorAluno` ou equivalente.

## Já tratados nesta entrega

| Endpoint / método | Status |
|---|---|
| Chat professor↔aluno (`ChatController`) | OK (SEC-10) |
| `TeacherController::updateStudentPassword` | Policy adicionada (fluxo ainda pausado por `alunosProfessorPausado`) |
| `TeacherController::viewStudent` / `studentProvas` / `studentRelatorio` | Policy no caminho quando o pause for removido |

## Próximos candidatos (não alterados nesta entrega)

| Área | Arquivo / rota | Observação |
|---|---|---|
| Relatório aluno | `TeacherController::relatorioAluno` + `/professor/relatorios/aluno/{id}` | Verificar filtro de turma |
| Monitor reset senha | `MonitorController::updateStudentPassword` | Já usa `fetchAlunoAutorizado` — revisar paridade com policy |
| Jornadas professor | `TeacherJourneyController` (muitos `aluno_id`) | Cluster acadêmico; tratar com cuidado |
| Ocorrências | Controllers em `ocorrencias/` | Professor cria/vê por aluno |
| Comunicação escolar | `TeacherComunicacaoController` | Escopo por turma |
| Painel pais web | `ParentController::getFilhoById` | Código OK; reteste HTTP pendente |
| API notificações mobile | Controllers V1 | Ownership por usuário JWT |

## Regra

Código novo professor→aluno: chamar `PoliticaAcessoProfessorAluno::podeAcessar` (ou extrair service compartilhado) antes de ler/mutar.
