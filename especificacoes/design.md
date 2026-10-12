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
| 2026-08-22 | Regras Acadêmicas viram cadastro versionado (`regras_academicas`) + serviço único (`ResultadoAcademicoService`). O evento de boletim (`boletim_regras`) continua compondo notas/layout; a situação (aprovado/recuperação/frequência) sai do motor. Sem regra cadastrada, fallback na mínima do próprio evento. Fórmulas `max`/`min` saem do `BoletimConfigController` para o mesmo serviço. | Cada escola tem regimento diferente; hardcodar `média >= 6` em boletim, histórico e dashboards gerava resultados divergentes. Versionar a regra evita que mudar a mínima em 2027 altere 2026. Conselho de Classe e simulação de impacto ficam para depois. |
| 2026-08-19 | Reestruturação do Diário de Classe (Fase 1) reorganiza a UI do módulo já existente (`diario_aulas`/`diario_frequencias`) numa visão agregada por Turma+Componente+Professor+Ano Letivo, mas **não** mexe em três pontos que a spec original também tocava: módulo legado "Só Faltas" (`faltas_*`), amarração de Grade Horária a Ano Letivo/Calendário Letivo, e migração dos dados já lançados em `diario_frequencias.nota`. Detalhe em `especificacoes/PRD.md` §9. | Investigação no código mostrou que o Diário já é um módulo funcional (não uma tela "virtual" de chamada) e que unificar as duas fontes de falta ou amarrar Grade Horária ao calendário letivo são projetos do tamanho da própria reestruturação — bundlar tudo seria o "big-bang" que o projeto evita (mesmo espírito do rename PT/EN em `.claude/docs/nomenclatura.md` e da Matriz Curricular em 2026-08-18). Nota sai da tela de chamada porque passa a ter fonte única em Eventos de Nota, mas apagar dado histórico já lançado é decisão de produto separada, não técnica. |
| 2026-08-18 | Matriz Curricular (nova) referencia `curso`/`serie` reais (tabelas já existentes, migrations 023/024/028/031) via `curso_id`/`serie_id`, não texto livre. Primeira entrega é CRUD simples sem versionamento/trava e sem "Regras Acadêmicas" (motor de aprovação separado, criado depois). Detalhe em `especificacoes/PRD.md` §9. | Investigação mostrou que `turmas.serie` (texto livre, legado) é usado com comparação por igualdade de string em ~30 arquivos (Jornadas, Exercícios, Blocos de Prova, Histórico Escolar, Plano de Aula) e duplicado em `alunos.serie` — migrar isso é projeto à parte, não deveria travar a Matriz. Já existe FK `turmas.serie_id → serie` e `curso_novo_id → curso` (fluxo "nova estrutura" em `ClassController`, parcialmente adotado) — a Matriz usa essa estrutura já pronta em vez de reinventar. "Regras Acadêmicas" (média/frequência mínima, resultado aprovado/reprovado) não existe hoje em nenhuma camada do sistema (`boletim_regras` só calcula nota) — é motor do tamanho da própria Matriz; bundlar tudo junto seria o "big-bang" que o projeto evita (ver `.claude/docs/nomenclatura.md`). |
| 2026-07-09 | Créditos de IA passam a se chamar **TudiCoins** na UI. Com sistema off, módulos 100% IA somem; em módulos mistos some só a ação/botão. Opções: exibir carteira ao aluno ou ocultar (escola paga / pool). EducaInclui debita carteira da escola (por laudo e por prova gerada). Catálogos Master (tabelas/pacotes) continuam; Asaas para compra. Detalhe em `.claude/docs/tudicoins.md`. | Precisa separar produto “módulo” de “ação cobrável”, permitir escola absorver custo sem o aluno ver saldo, e cobrar uso da coordenação — sem quebrar o fluxo atual de carteira do aluno. Rename de schema fica para depois (só UI nesta fase). |
| 2026-07-08 | Reverter nomenclatura de código novo para português (era "inglês obrigatório"); tabelas legadas em inglês só renomeiam caso a caso, nunca em lote. Detalhe e inventário em `.claude/docs/nomenclatura.md`. | Auditoria mostrou que a regra antiga criou duas eras no mesmo repo: núcleo histórico 100% PT vs módulos criados desde ~jun/2026 (Finance, Enrollment, Accommodations, Inventory, Patrimony, School Calendar/Communication, Mobile*, Facial*) em inglês, sem ganho real — o vocabulário de negócio (coluna/variável/input) nunca foi traduzido, só a moldura (classe/tabela). Rename retroativo de ~300 arquivos e 58 tabelas foi propositalmente deixado fora de escopo agora (alto risco em produção multi-tenant); tratado como dívida a pagar módulo a módulo. Pares de tabela ambíguos (`matricula`/`enrollment`, `financeiro_valores_mensais`/`finance_*`) exigem decisão de produto antes de qualquer rename — não é só nomenclatura. |
