# Etapa 2 — Migração Mural do Aluno (concluída)

**Data:** 2026-11-20  
**Deploy:** não executado  
**CSRF global:** inalterado (`audit`)

---

## 1. Arquivos

| Ação | Path |
|---|---|
| Criado | `backend/app/Modulos/mural/Controllers/AlunoMuralController.php` |
| Criado | `backend/tests/Modulos/MuralRecadoAlunoServiceTest.php` |
| Modificado | `backend/app/Modulos/mural/Services/MuralRecadoService.php` |
| Modificado | `backend/app/Modulos/mural/routes.php` |
| Modificado | `backend/config/routes/student.php` |
| Modificado | `backend/app/Painel/User/Controllers/StudentController.php` |
| Modificado | `backend/tests/Seguranca/CsrfMiddlewareTest.php` |
| Modificado | `backend/doc_sistema/estrutura.md` |
| Este relatório | `especificacoes/auditoria-arquitetural-2026-11-20/ETAPA2_MURAL_MIGRACAO.md` |

## 2. Linhas no StudentController

| Antes | Depois | Removidas (aprox.) |
|---:|---:|---:|
| 8183 | 7970 | **~213** |

## 3. Responsabilidades transferidas

| De StudentController | Para |
|---|---|
| `muralRecados` | `AlunoMuralController::index` |
| `marcarVistoMuralRecado` | `AlunoMuralController::marcarVisto` |
| Listagem/filtros/contagem/resumo não lidos | `MuralRecadoService::listarParaAluno`, `contarParaAluno`, `listarNaoVistosResumoParaAluno`, filtros |
| Contadores do dashboard (incl. Grupo Seb) | Chamadas ao mesmo Service |

URLs: `GET /mural-recados`, `POST /mural-recados/marcar-visto` — mesmas; handler só em `mural/routes.php`.

## 4. Testes

```bash
cd backend && ./vendor/bin/phpunit tests/Modulos/MuralRecadoAlunoServiceTest.php tests/Seguranca --testdox
```

- MuralRecadoAlunoService: **7 OK**
- Seguranca (incl. CSRF mural audit/enforce): suite Seguranca **OK**

## 5. Diferenças de comportamento

**Nenhuma intencional.** SQL de listagem/contagem espelha o StudentController.

**Não alterado de propósito:** `DashboardAlunoService` ainda tem SQL de mural próprio (ligeiramente diferente, sem `publico`/`aluno_id` da mesma forma). Usado só por `dashboardApiMontar`. Unificar exigiria aprovação (possível mudança funcional na API de cards).

## 6. Riscos residuais

1. Smoke HTTP local não exercitado neste ambiente (recomendado: login aluno → mural → marcar lido).
2. Duplicidade SQL em `DashboardAlunoService::contarMural` (próxima fatia opcional).
3. CSRF continua em `audit` globalmente; controller ainda valida `_token` no POST marcar-visto.

## 7. Rollback

1. Restaurar rotas em `student.php` para `User/StudentController@muralRecados` / `marcarVistoMuralRecado`.
2. Remover as duas linhas de aluno em `mural/routes.php`.
3. `git checkout` dos métodos no StudentController + Service methods se necessário.
4. Sem migration — só código.

---

*Fatia 1 da Etapa 2. Próxima candidata no inventário: planos-aula / redações.*
