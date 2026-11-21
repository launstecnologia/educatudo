# Etapa 3 — Execução contínua de modernização

**Data:** 2026-11-20  
**Deploy:** não executado  
**Migrations / schemas / CSRF global / deploy-main / notas-boletim-fechamento:** inalterados

Baseline `StudentController` no inventário Etapa 2: **~8183** linhas.  
Após esta execução: **7164** linhas (−**~1019**).

---

## Etapa A — Auditoria Planos de Aula

| Campo | Valor |
|---|---|
| Status | Concluída (sem mudança funcional) |
| Arquivos | `ETAPA_A_PLANOS_AULA_AUDITORIA.md`; testes já em `PlanoAulaAlunoCaracterizacaoTest` |
| Achado principal | Rascunhos **visíveis** ao aluno (sem filtro `status`) — **pendência de produto** |
| Correções | Nenhuma (dúvida de negócio) |
| Testes | 13 OK |
| Rollback | N/A |

---

## Etapa B — Regressão das migrações

| Campo | Valor |
|---|---|
| Status | Concluída |
| PHPUnit Segurança | **34 OK** |
| PHPUnit módulos (filter) | **56 OK / 171 asserts** |
| Smoke HTTP local | Sem auth: `/aluno/planos-aula`, `/mural-recados`, `/redacoes/historico`, `/redacoes/escrever` → **302** `Location: /`; `/exercicios` → **302** `/dashboard`. Contas de teste autenticadas **não** usadas (credenciais reais não acessadas). |
| Regressões | Nenhuma |
| Riscos | Smoke autenticado ainda pendente de homologação |

---

## Etapa C — Modularização de Redações (fatia baixa)

| Campo | Valor |
|---|---|
| Status | Concluída (parcial — fatia sem IA) |
| Criados | `AlunoRedacaoLegadoController.php`, `RedacaoAlunoLegadoService.php`, `RedacaoAlunoLegadoCaracterizacaoTest.php` |
| Alterados | `redacoes/routes.php`, `StudentController.php` (−~291 nesta fatia) |
| Métodos migrados | `historicoRedacoes`, `verRedacao`, `escreverRedacao`, `escreverRedacaoLivre`, `transcreverImagemPage`, `continuarRascunho`, `excluirRascunho`, `ocultarRedacao` |
| Correção técnica | `ocultar`: caminho “não encontrado” usava `$redacaoJornada` indefinido → agora JSON **404**; UPDATE com `aluno_id` |
| Não migrado (alto acoplamento / IA / créditos) | `essays`, `createEssay`, `gerarTemaIA`, `transcreverImagem`, `corrigirRedacao`, `salvarRascunho` + helpers |
| URLs | Preservadas |
| Testes | 7 OK caracterização + suite módulos |
| Rollback | Reapontar rotas para `StudentController` + restore métodos |

---

## Etapa D — Exercícios

| Campo | Valor |
|---|---|
| Status | Concluída (sem reativação) |
| Rotas ativas | `Modulos/exercicios` → `ExerciseController` / `CustomExerciseController` (`/exercicios`, `/exercicios-personalizados`) |
| Legado SC | `exercises`, `startExercise`, `submitExerciseAnswer` **sem rota** → **removidos** (−~181 linhas) + helpers |
| Não feito | Não reativar endpoints legados |
| Testes | Regressão módulos + segurança OK |

---

## Etapa E — Qualidade e CI

| Campo | Valor |
|---|---|
| Status | Concluída |
| Alterado | `.github/workflows/pr-validacao.yml` — gate **módulos migrados** (filter PHPUnit) |
| Não alterado | `deploy-main.yml`; suite completa continua `continue-on-error` |
| Lint | `php -l` nos PHP novos/alterados — OK |

---

## Etapa F — Documentação

| Campo | Valor |
|---|---|
| Status | Este arquivo + `estrutura.md` atualizado |
| Inventário A | `ETAPA_A_PLANOS_AULA_AUDITORIA.md` |

---

## Resumo executivo

### Redução StudentController
~8183 → **7164** (−~12,5% / ~1019 linhas nesta modernização desde o inventário).

### Modularizado
- Mural aluno (+ contagens dashboard)
- Planos de aula aluno
- Redação legada (GET + ownership + ocultar/excluir rascunho)
- Exercícios mortos removidos do SC

### Controllers / Services criados (esta execução)
- `AlunoRedacaoLegadoController`, `RedacaoAlunoLegadoService`
- (prévias: mural, planos, consolidação contagem)

### Testes
| Suite | Resultado |
|---|---|
| Seguranca | 34 OK |
| Módulos migrados (filter) | 56 OK |
| Suite completa | não usada como gate (falhas legadas pré-existentes) |

### Segurança
- Ownership redação no ocultar reforçado (`aluno_id` no UPDATE)
- Bug variável indefinida em ocultar corrigido
- Isolamento tenant por PDO mantido

### Pendências humanas
1. Planos: filtrar só `aprovado` no portal aluno?
2. Homologar smoke autenticado (mural, planos, redação legado)
3. Migrar restante `/redacoes` com IA/créditos (não nesta rodada)
4. Assimetria listagem×detalhe planos (produto)

### Próximas prioridades
1. Decisão produto status planos  
2. Fatia redação IA com testes de crédito/limites  
3. Extrair chat/Tudinha do SC (alto risco)  
4. Dashboard permanece no Painel  

### Rollback geral
Git revert por fatia (rotas + controller/service + SC). Sem migration.
