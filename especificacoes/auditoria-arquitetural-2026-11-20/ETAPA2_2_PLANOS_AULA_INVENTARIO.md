# Etapa 2.2 — Inventário Planos de Aula (aluno)

**Data:** 2026-11-20  
**Status:** migração para `Modulos/jornadas` (comportamento preservado)

---

## 1. Rotas atuais

| Método HTTP | URL | Handler legado |
|---|---|---|
| GET | `/aluno/planos-aula` | `User/StudentController@planosAula` |
| GET | `/aluno/planos-aula/visualizar/{id}` | `User/StudentController@visualizarPlanoAula` |

Definidas em `backend/app/Modulos/jornadas/routes.php` (não em `student.php`).

## 2. Views

| Ação | View |
|---|---|
| Listagem | `student/planos-aula/index` → `Painel/Aluno/Views/planos-aula/index.php` |
| Detalhe | `student/planos-aula/visualizar` → `Painel/Aluno/Views/planos-aula/visualizar.php` |

Contrato de dados a preservar:

- Listagem: `title`, `user`, `aluno`, `planos_aula` (com `data_exibicao`), `current_page=planos-aula`
- Detalhe: `title`, `user`, `aluno`, `plano_aula`, `current_page=planos-aula`

## 3. Tabelas / SQL

| Operação | Tabelas | Filtros |
|---|---|---|
| Aluno | `alunos`, `turmas` | `a.id = :user_id` |
| Listagem | `planos_aula`, `professores`, `materias`, `turmas` | `turma_id` + `deleted_at IS NULL` + `YEAR(created_at)=ano` |
| Detalhe | idem | `pa.id` + `deleted_at IS NULL` |
| Auth detalhe | `turmas` (aluno e plano) | mesma turma **OU** mesma `serie`+`ano_letivo` |

**Sem filtro de `status`** (rascunho/publicado) — regra atual preservada.

## 4. Assimetria intencional (não “corrigir”)

| | Listagem | Visualizar |
|---|---|---|
| Turma | Só `pa.turma_id = aluno.turma_id` | Turma igual **ou** série+ano iguais |
| Ano | `YEAR(created_at) = ano atual` | Sem filtro de ano |
| Status | Ignorado | Ignorado |

## 5. Sessão / auth

- Perfil `aluno` (middleware + check no controller do módulo).
- Sem aluno → `/logout`.
- ID inválido / inexistente / não autorizado → flash + redirect `/aluno/planos-aula`.

## 6. Services existentes

`LessonPlan` (Model) e `LessonPlanController` (prof/admin) **não** cobrem o portal aluno. Novo: `PlanoAulaAlunoService` + `AlunoPlanoAulaController`.

## 7. Proposta

1. Extrair SQL + `formatarData` + regra `podeVisualizar` para `PlanoAulaAlunoService`.
2. `AlunoPlanoAulaController` com `index` / `visualizar`.
3. Rotas apontam para `Modulos/jornadas/AlunoPlanoAulaController@…`.
4. Remover métodos + helper do `StudentController`.
5. Views inalteradas.

## 8. Fora de escopo

Pais (`ParentController`), admin/professor `LessonPlanController`, notas/boletins, migrations.
