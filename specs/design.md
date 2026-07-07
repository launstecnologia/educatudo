# Design — decisões técnicas do EducaTudo

> Decisões de arquitetura com o "porquê". Detalhe operacional está no CLAUDE.md; aqui fica o racional.

## Decisões vigentes

### 1. Isolamento por conexão PDO, não por `WHERE escola_id`
Cada tenant tem banco próprio; `Database::setCurrentInstance()` troca o PDO por request.
**Porquê:** vazamento de dado entre escolas vira impossível por construção, não depende de disciplina em cada query. Um `WHERE escola_id` em query de tenant é sintoma de bug de arquitetura.

### 2. PHP síncrono (FPM), sem framework
**Porquê:** singleton global de conexão só é seguro com isolamento por processo. Migrar para Swoole/RoadRunner exige refatorar `Database` antes.

### 3. IA sempre assíncrona acima de 2s
Correção de redação, flashcards, slides → job em fila (`AIJobService`), nunca na request.
**Porquê:** request HTTP presa em chamada de IA derruba workers do FPM para a escola inteira.

### 4. Redis obrigatório em produção multi-instância
Cache de tenant e sessões. Fallback de file cache existe só para dev local.
**Porquê:** file cache não é compartilhado entre servidores → sessão quebra atrás de load balancer.

### 5. Migrations SQL versionadas com rollback
Todo `NNN_descricao.sql` tem par `NNN_descricao_rollback.sql`. Execução via painel Master ou `run_migrations.php` — nunca direto no banco.
**Porquê:** migrations rodam por escola; uma falha no meio da frota precisa ser reversível escola a escola.

## Registro de decisões novas

<!-- Formato: data, decisão, alternativas consideradas, porquê -->

| Data | Decisão | Porquê |
|---|---|---|
| | | |
