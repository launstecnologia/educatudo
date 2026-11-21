# Etapa A — Auditoria de Planos de Aula (aluno)

**Data:** 2026-11-20  
**Código-fonte da verdade:** `PlanoAulaAlunoService` + `AlunoPlanoAulaController`  
**Testes:** `tests/Modulos/PlanoAulaAlunoCaracterizacaoTest.php`

---

## Achados

| Tema | Comportamento atual | Avaliação |
|---|---|---|
| Publicação | Admin/prof usam `status` (rascunho/aprovado/rejeitado) | Fluxo de aprovação existe no `LessonPlanController` |
| Rascunhos no portal aluno | **Visíveis** — SQL aluno **não** filtra `status` | **Dúvida de produto** — não corrigir sem decisão |
| Autorização listagem | Só `turma_id` do aluno + `YEAR(created_at)` | Comprovado |
| Autorização detalhe | Turma igual **ou** série+`ano_letivo` iguais | Comprovado; assimétrico vs listagem |
| Ano letivo | Listagem usa ano civil de `created_at`; detalhe usa `turmas.ano_letivo` na auth cruzada | Assimetria documentada |
| Acesso por ID | Soft-delete bloqueia; ID inválido → flash+redirect | OK |
| Isolamento escolas | PDO por tenant; sem `WHERE escola_id` | OK |

## Correções (Etapa 4.1 — decisão de produto)

- Allowlist: apenas `aprovado` no portal aluno, pais e APIs relacionadas.  
- `rascunho`, `rejeitado`, `null`, `publicado` (não usado no fluxo de planos) → **não** liberados.  
- Professor/admin inalterados.

## Testes

`PlanoAulaAlunoCaracterizacaoTest` + `PlanoAulaAlunoVisibilidadeStatusTest`.

## Pendência residual

Assimetria listagem (só turma) × detalhe (série+ano) permanece de propósito legado.
