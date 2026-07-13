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
| 2026-07-09 | Créditos de IA passam a se chamar **TudiCoins** na UI. Com sistema off, módulos 100% IA somem; em módulos mistos some só a ação/botão. Opções: exibir carteira ao aluno ou ocultar (escola paga / pool). EducaInclui debita carteira da escola (por laudo e por prova gerada). Catálogos Master (tabelas/pacotes) continuam; Asaas para compra. Detalhe em `.claude/docs/tudicoins.md`. | Precisa separar produto “módulo” de “ação cobrável”, permitir escola absorver custo sem o aluno ver saldo, e cobrar uso da coordenação — sem quebrar o fluxo atual de carteira do aluno. Rename de schema fica para depois (só UI nesta fase). |
| 2026-07-08 | Reverter nomenclatura de código novo para português (era "inglês obrigatório"); tabelas legadas em inglês só renomeiam caso a caso, nunca em lote. Detalhe e inventário em `.claude/docs/nomenclatura.md`. | Auditoria mostrou que a regra antiga criou duas eras no mesmo repo: núcleo histórico 100% PT vs módulos criados desde ~jun/2026 (Finance, Enrollment, Accommodations, Inventory, Patrimony, School Calendar/Communication, Mobile*, Facial*) em inglês, sem ganho real — o vocabulário de negócio (coluna/variável/input) nunca foi traduzido, só a moldura (classe/tabela). Rename retroativo de ~300 arquivos e 58 tabelas foi propositalmente deixado fora de escopo agora (alto risco em produção multi-tenant); tratado como dívida a pagar módulo a módulo. Pares de tabela ambíguos (`matricula`/`enrollment`, `financeiro_valores_mensais`/`finance_*`) exigem decisão de produto antes de qualquer rename — não é só nomenclatura. |
