# Etapa 2.2 — Migração Planos de Aula (aluno)

**Data:** 2026-11-20  
**Deploy:** não executado  
**CSRF / migrations / notas / boletins:** inalterados

---

## 1. Arquivos

| Ação | Path |
|---|---|
| Criado | `backend/app/Modulos/jornadas/Controllers/AlunoPlanoAulaController.php` |
| Criado | `backend/app/Modulos/jornadas/Services/PlanoAulaAlunoService.php` |
| Criado | `backend/tests/Modulos/PlanoAulaAlunoCaracterizacaoTest.php` |
| Modificado | `backend/app/Modulos/jornadas/routes.php` |
| Modificado | `backend/app/Painel/User/Controllers/StudentController.php` |
| Modificado | `backend/doc_sistema/estrutura.md` |
| Inventário | `ETAPA2_2_PLANOS_AULA_INVENTARIO.md` |
| Este relatório | `ETAPA2_2_PLANOS_AULA_MIGRACAO.md` |

## 2. Linhas no StudentController

| Antes | Depois | Removidas (aprox.) |
|---:|---:|---:|
| 7970 | 7636 | **~334** |

Removidos: `formatarDataPlanoAula`, `planosAula`, `visualizarPlanoAula` (+ debug noise).

## 3. Responsabilidades transferidas

| De StudentController | Para |
|---|---|
| `planosAula` | `AlunoPlanoAulaController::index` |
| `visualizarPlanoAula` | `AlunoPlanoAulaController::visualizar` |
| SQL listagem / detalhe / auth série+ano | `PlanoAulaAlunoService` |
| `formatarDataPlanoAula` | `PlanoAulaAlunoService::formatarDataExibicao` |

URLs preservadas: `GET /aluno/planos-aula`, `GET /aluno/planos-aula/visualizar/{id}`.  
Views preservadas: `student/planos-aula/index`, `student/planos-aula/visualizar`.

## 4. Comportamento preservado (assimetria)

| | Listagem | Visualizar |
|---|---|---|
| Turma | só `turma_id` do aluno | turma igual **ou** série+ano_letivo |
| Ano | `YEAR(created_at)` | sem filtro |
| Status | ignorado | ignorado |

Nenhuma alteração funcional intencional.

## 5. Testes

```bash
cd backend && ./vendor/bin/phpunit \
  tests/Modulos/PlanoAulaAlunoCaracterizacaoTest.php \
  tests/Modulos/MuralRecadoAlunoServiceTest.php \
  tests/Modulos/MuralDashboardContagemConsolidacaoTest.php \
  tests/Seguranca --testdox
```

## 6. Riscos residuais

1. Smoke HTTP local recomendado (login aluno → listar → abrir plano).
2. Assimetria listagem×detalhe permanece (legado); unificar exigiria aprovação.
3. Portal pais (`ParentController`) fora de escopo.

## 7. Rollback

1. Restaurar rotas em `jornadas/routes.php` para `User/StudentController@planosAula` / `visualizarPlanoAula`.
2. `git checkout` dos métodos no StudentController.
3. Remover `AlunoPlanoAulaController` + `PlanoAulaAlunoService` se desejado.
4. Sem migration.
