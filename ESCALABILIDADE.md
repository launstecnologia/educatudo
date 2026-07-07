# Auditoria de Escalabilidade — EducaTudo

Levantamento feito em 2026-07-04 por dois subagentes em paralelo (infraestrutura multi-tenant + performance de queries), cruzando código-fonte com o doc interno `docs/INFRAESTRUTURA_SERVIDORES.md` (números reais de produção, levantados via SSH em 2026-06-21). Nada foi alterado — isso é só investigação.

## Veredito geral

**Hoje aguenta a operação atual (4 escolas, tráfego baixo) sem problema.** Mas existem 3 tipos de barreira que vão aparecer em momentos diferentes do crescimento:

1. **Mais usuários simultâneos, mesmo com poucas escolas** → o maior risco real. Geração de exercício/explicação/OCR por IA roda **síncrona dentro do request HTTP**, prendendo um worker PHP-FPM por até 4-5 minutos por chamada. Poucos professores gerando conteúdo ao mesmo tempo já reduz a capacidade de atender outros usuários (alunos fazendo prova, login, etc.) na mesma hora.
2. **Mais escolas usando ao mesmo tempo** → hoje há **1 único MySQL** (7.8GB RAM) hospedando todos os tenants no mesmo `mysqld`, sem connection pooling, cada request abrindo uma conexão PDO nova. Cresce mal em conexões simultâneas e cron jobs que iteram todas as escolas em série.
3. **Adicionar um segundo servidor de aplicação** (escalar horizontalmente) → **hoje isso simplesmente não funciona direito**: sessão PHP em arquivo local, Redis hardcoded pra `127.0.0.1`, e rate limit de login em arquivo local. Um load balancer sem sticky session ia derrubar usuários aleatoriamente.

---

## Contexto real de produção (não é suposição)

Confirmado em `docs/INFRAESTRUTURA_SERVIDORES.md`: **1 servidor de aplicação** (4 vCPU/16GB) + **1 servidor de banco** (7.8GB RAM, MySQL 9 em Docker), hospedando todos os tenants no mesmo processo `mysqld` (uma escola já com ~3.6GB de banco). Redis roda local, no mesmo host da aplicação. Apenas 4 escolas ativas hoje.

---

## P0 — Resolver antes de crescer em usuários simultâneos

### 1. Chamadas de IA síncronas presas no worker PHP-FPM
Viola a própria regra do `CLAUDE.md` ("chamadas de IA >2s devem ser assíncronas"). O padrão correto **já existe** no código (`AIJobService::enqueue()`, usado corretamente por correção de redação e flashcards) — mas várias rotas de geração de exercício/explicação/OCR não o usam:

| Local | Timeout esticado |
|---|---|
| `TeacherJourneyController.php:4676-4790` (`gerarExercicioIAModulo`) | `set_time_limit(240)` |
| `TeacherJourneyController.php:1998-2056` (`gerarExplicacaoComplementar`) | síncrono, sem enqueue |
| `TeacherJourneyController.php:5013-5058` (`lerImagemExercicio`, OCR) | `set_time_limit(300)` |
| `TeacherJourneyController.php:7656-7659` (`gerarDescricaoRedacaoIA`) | síncrono |
| `ExamController.php:6129/6475` | `set_time_limit(240/300)` |
| `CustomExerciseController.php:221-222/368-369` | `set_time_limit(300)` |
| `Essays/TeacherRedacaoLivreController.php:198` | `set_time_limit(300)` |

**Ação:** migrar essas rotas pro mesmo padrão `AIJobService::enqueue()` + polling que já funciona pra redação/flashcards. Não é preciso inventar nada novo, só replicar.

Reforça o risco: **não há rate limiting nas rotas de geração de IA** — só o sistema de créditos limita por custo, não por taxa. Um usuário com créditos pode disparar N requests simultâneas, cada uma prendendo um worker por minutos.

### 2. Confirmar se a fila de IA está de fato agendada em produção
O próprio doc de infra da empresa levanta essa dúvida: no `crontab -l` real só `atualizar_status_jornadas.php` aparece nominalmente — `process_ai_jobs.php` (que deveria rodar a cada 1 min processando a fila) pode estar em job hash do aaPanel ou **não estar agendado**. Se não estiver, o fallback de `AIJobService::tryProcessImmediately()` (`AIJobService.php:55-83`) tenta rodar um processo PHP CLI em background via `exec()` — e se `exec` estiver desabilitado (comum em hospedagem compartilhada/aaPanel), cai pra **processar inline dentro da própria request**, ou seja, toda a "assincronia" pode estar sendo uma ilusão hoje. **Precisa confirmar isso direto no painel/servidor**, não dá pra saber só lendo código.

### 3. N+1 de até ~1.400 queries numa única tela
- `JornadasRelatorioService.php:40-110` — 4 queries por jornada dentro do loop (até 350 jornadas = ~1.400 queries). Corrigível com `WHERE jornada_id IN (...)` agregando por jornada+aluno.
- `AdminJourneyController.php:785-889` — mesmo padrão, com N+1 **aninhado** na seção de resumos (query por aluno dentro de query por módulo). Curiosidade: o código equivalente em `TeacherJourneyController.php:5770` já foi corrigido pra uma query batched com comentário explícito "evita N+1" — é a versão admin que ficou pra trás, sinal de lógica duplicada entre controllers que deveria estar num Service só.
- `ChatController.php` (linhas 159-179, 411-431, 587-605, 763-782, repetido 4x) — carrega **todo o histórico de chat sem paginação** e busca anexos com 1 query por mensagem. É o pior caso: N+1 + zero paginação, num endpoint de alto tráfego (chat é reaberto o tempo todo).
- `ExamController.php:160-236` (tela inicial de provas do professor) — 3 queries por bloco de prova, sem limite de histórico.

---

## P1 — Resolver antes de adicionar um segundo servidor de aplicação

### 4. Sessão PHP em arquivo local
Nenhum `session_set_save_handler`/Redis pra sessão em todo o código — usa o storage padrão em disco (`index.php:97` só chama `session_start()` puro). Funciona hoje (1 servidor). No dia de um segundo servidor atrás de load balancer sem sticky session, usuários seriam deslogados aleatoriamente. **Ação:** configurar `session.save_handler=redis` antes de qualquer plano de 2º servidor.

### 5. Redis hardcoded pro localhost
`RedisCache.php:10-11` tem `HOST`/`PORT` fixos em `127.0.0.1:6379`, **ignorando a variável `REDIS_URL`** do `.env` (que está documentada no `CLAUDE.md` como obrigatória em produção, mas não é lida em lugar nenhum do código). Funciona hoje porque Redis roda no mesmo host da aplicação. Também explica uma imprecisão que eu tinha repetido antes: **não existe fallback de arquivo pra cache** — se o Redis falhar, o sistema simplesmente vai sem cache nenhum pro banco master a cada request (`RedisCache.php:19-40`), não existe `FileCache`. **Ação:** ler host/porta de `REDIS_URL`, e ao escalar horizontalmente, apontar todos os servidores pro mesmo Redis compartilhado (nunca um Redis local por servidor).

### 6. Rate limiting em arquivo local
Login web (`AuthController.php:906-965`), login API de pais (`Api/AuthController.php:88-114`) e login mobile facial (`MobileAuthController.php:173-207`) usam arquivo local com `flock()`, não Redis. Com múltiplos servidores, um atacante distribuído via round-robin ganha N× o limite antes de ser bloqueado — falha silenciosa, não crasha nada, só para de proteger. **Ação:** migrar pra `INCR`+`EXPIRE` no Redis (mesma infra do item 5).

---

## P2 — Otimizações de banco (baixo risco hoje, ficam piores com o tempo)

### 7. Índices compostos ausentes em `jornadas_progresso_alunos`
Só tem índices de coluna única. As queries reais filtram por combinações (`jornada_id + modulo_id + atividade_tipo`, `jornada_id + aluno_id IN (...)`). Recomendado: `ADD INDEX idx_jornada_modulo (jornada_id, modulo_id, atividade_tipo)` e `ADD INDEX idx_jornada_aluno (jornada_id, aluno_id)`. Tabela já tem 43.630 linhas no dump de desenvolvimento — só cresce.

### 8. Lançamento de notas em massa sem transação
`ExamBlockManualGrade::upsertLinhas` (`app/Models/Exams/ExamBlockManualGrade.php:309-341`) grava nota por aluno em autocommit, sem `beginTransaction()`. Se a request cair no meio de um lançamento de uma escola inteira, fica com notas parcialmente salvas sem rollback. O padrão correto (transação com `beginTransaction`/`commit`/`rollBack`) já existe em `ListaChamadaService`/`AlunoMovimentacaoService` — só falta replicar aqui.

### 9. Dashboard de resultado de prova recalcula do zero a cada view
`ExamBlockResultsDashboardService::build()` — bem escrito (batched, sem N+1), mas sem cache. Vários professores olhando o mesmo resultado logo após a prova (momento de pico) recalculam a mesma agregação em paralelo. Cache Redis com TTL de 60-120s por `blocoId` resolve a maior parte do custo.

### 10. `ClassDiary::aulasPendentes` — N+1 por dia
Loop dia-a-dia disparando 1 query por dia (até 62 queries). O mesmo arquivo tem `indicadores()` (linhas 303-354) que já faz a agregação certa com `GROUP BY` — só não foi replicado aqui.

### 11. Padrão sistêmico: `SELECT *`
532 ocorrências no código (129 em Models, 386 em Controllers). Não quebra índice sozinho, mas invalida covering indexes e aumenta I/O em queries de listagem de alto volume. Vale padronizar colunas explícitas nas queries de listagem/dashboard mais quentes antes de investir em índices cobertos.

---

## P3 — Avaliar com métricas reais antes de decidir (não dá pra saber só lendo código)

### 12. Connection pooling / sharding do MySQL único
Todas as conexões PDO são não-persistentes (`Database.php:184`, comentário "evita Packets out of order"), sem ProxySQL/HAProxy na frente. Com centenas de escolas e picos correlacionados (ex: todos os alunos entrando às 8h), o único MySQL pode esbarrar em `max_connections`. **Antes de decidir**, medir em produção: `SHOW VARIABLES LIKE 'max_connections'` e `Threads_connected` sob carga real. Se necessário: ProxySQL/HAProxy pra poolar, ou eventualmente sharding de tenants em mais de um servidor MySQL.

### 13. Cron multi-tenant sequencial sem lock
`CronMultiTenantHelper` itera escolas em série (sem paralelismo), com uma query extra ao master por escola a cada execução. `AIJobService::processNext()` usa `SELECT ... FOR UPDATE SKIP LOCKED` (evita processar o mesmo job 2x), mas **não há lock global** contra sobreposição de execuções do cron inteiro — com centenas de escolas, a execução pode passar de 60s e sobrepor com a próxima chamada, multiplicando conexões simultâneas ao MySQL. Considerar `flock()`/`GET_LOCK()` no início do cron, e eventualmente paralelizar (múltiplos processos CLI) em vez de loop serial.

### 14. Migrations em lote já existem, mas sequenciais
`MasterMigrationsController::executarTodas()` já roda em lote pra todas as escolas — não é "uma por uma manualmente" como eu supus antes de investigar. Mas é sequencial; uma migration pesada (`ALTER TABLE` numa tabela grande) rodando em lote em centenas de escolas pode demorar muito, sem streaming de progresso. Baixa prioridade — é operacional, não constante.

---

## Resumo executivo — ordem de execução recomendada

1. Migrar geração de exercício/explicação/OCR por IA pro padrão `AIJobService::enqueue()` (P0.1) — maior risco concreto, e o padrão já existe no código.
2. Confirmar no painel/servidor se `process_ai_jobs.php` está realmente agendado (P0.2) — sem isso, não dá pra saber se a fila assíncrona funciona de verdade hoje.
3. Corrigir os N+1 de jornadas e reescrever o chat pra paginar (P0.3) — telas de uso frequente, multiplicador alto de queries.
4. Antes de cogitar um segundo servidor de aplicação: Redis compartilhado (não hardcoded em `127.0.0.1`) + sessão via Redis + rate limit via Redis (P1, itens 4-6) — sem isso, escalar horizontalmente vai gerar bugs intermitentes difíceis de debugar (sessão perdida, cache inconsistente).
5. Índices compostos em `jornadas_progresso_alunos`, transação em lançamento de notas, cache no dashboard de resultado (P2) — ganho de performance com risco baixo de regressão.
6. Medir `max_connections`/`Threads_connected` reais em produção antes de decidir sobre pooling/sharding do MySQL (P3) — não decidir isso sem dado real.

**Limite desta auditoria:** é análise estática de código + 1 doc de infra já existente. Não há acesso a `EXPLAIN` real, métricas de CPU/RAM sob carga, `pm.max_children` do PHP-FPM, ou tamanho real das tabelas por escola. Onde isso importa, o relatório sinaliza explicitamente em vez de supor.