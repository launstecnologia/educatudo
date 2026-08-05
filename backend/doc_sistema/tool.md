# Tools — EducaTudo

Catálogo vivo das **tools de consulta** usadas pelo Assistente admin e pelos servers MCP.

> **Como manter:** toda tool nova ou alteração de args/retorno deve atualizar este arquivo na mesma PR.  
> **Wiki:** [Master](/master/documentacao/tool) · [Admin](/admin/doc-sistema/tool) · índice [index.md](index.md)  
> Última atualização: **2026-07-21**

---

## Visão geral

| Superfície | URL / caminho | Quem usa |
|---|---|---|
| Chat Assistente | `/admin/assistente` | Coordenação/direção (`dev`, `diretor`, `coordenador`) |
| MCP Provas aluno | `POST /admin/consulta-provas-aluno/mcp/ferramenta` | `mcp/provas-aluno` |
| MCP Jornadas aluno | `POST /admin/consulta-jornadas-aluno/mcp/ferramenta` | `mcp/jornadas-aluno` |
| MCP Provas professor | `POST /admin/consulta-provas-professor/mcp/ferramenta` | `mcp/provas-professor` |
| MCP Consulta ampliada | `POST /admin/consulta-assistente/mcp/ferramenta` | `mcp/assistente-consulta` |
| Assistente de Boletim | `/admin/boletim-configuracao/assistente/*` | `mcp/boletim-assistente` (config de regras) |

**Modelo do chat:** a LLM **não monta SQL**. Emite:

```text
<<<CONSULTA>>>
{"tool":"NOME","args":{...}}
<<<FIM>>>
```

O PHP executa a tool via Service (prepared statements) e devolve JSON + painel na UI.

**Orquestrador do chat:** `app/Services/ProvasAlunoAssistenteService.php` → `executarTool()`.

---

## Índice rápido (chat Assistente)

| # | Tool | Domínio |
|---|---|---|
| 1 | `buscar_alunos` | Aluno |
| 2 | `listar_materias` | Catálogo |
| 3 | `listar_tipos_avaliacao` | Catálogo |
| 4 | `listar_provas_aluno` | Provas aluno |
| 5 | `detalhar_prova_aluno` | Provas aluno |
| 6 | `resumo_provas_aluno` | Provas aluno |
| 7 | `listar_materias_jornadas` | Jornadas aluno |
| 8 | `listar_jornadas_aluno` | Jornadas aluno |
| 9 | `detalhar_jornada_aluno` | Jornadas aluno |
| 10 | `resumo_jornadas_aluno` | Jornadas aluno |
| 11 | `buscar_professores` | Professor |
| 12 | `listar_turmas_professor` | Professor |
| 13 | `listar_provas_professor` | Professor |
| 14 | `resumo_provas_professor` | Professor |
| 15 | `detalhar_prova_professor` | Professor |
| 16 | `ranking_erros_prova_professor` | Professor |
| 17 | `saude_turmas_professor` | Professor / saúde |
| 18 | `resumo_jornadas_professor` | Professor / jornadas |
| 19 | `saude_turma` | Turma |
| 20 | `resumo_provas_turma` | Turma |
| 21 | `buscar_blocos` | Bloco de prova |
| 22 | `resultados_bloco` | Bloco de prova |
| 23 | `boletim_aluno` | Boletim / notas |
| 24 | `faltas_aluno` | Frequência |

---

## 1. Aluno — provas

**Service:** `ProvasAlunoConsultaService`  
**MCP:** `mcp/provas-aluno`  
**Painéis UI:** `candidatos`, `lista`, `detalhe_prova`, `resumo`

### `buscar_alunos`

Busca alunos por nome/RA; opcional filtro de turma.

| Arg | Tipo | Obrig. | Notas |
|---|---|---|---|
| `termo` / `nome` | string | sim | mín. ~2 chars |
| `turma` / `turma_nome` | string | não | ex.: `2 Ano B`, `2ªB` |

**Retorno:** lista `{id, nome, ra, turma_id, turma_nome}`.

---

### `listar_materias`

Lista matérias da escola (filtro de provas).

| Arg | — |
|---|---|
| _(nenhum)_ | |

---

### `listar_tipos_avaliacao`

Tipos cadastrados (Semanal, Bimestral, ENAC…).

| Arg | — |
|---|---|
| _(nenhum)_ | |

---

### `listar_provas_aluno`

Lista provas do aluno com realização (nota, acertos, erros, %).

| Arg | Tipo | Obrig. | Notas |
|---|---|---|---|
| `aluno_id` | int | preferencial | |
| `aluno_nome` / `nome` | string | alt. | se ambíguo → `candidatos` |
| `turma_nome` / `turma` | string | não | desambiguação |
| `materia_id` / `materia_nome` | int/string | não | |
| `tipo_avaliacao_id` / `tipo_avaliacao_nome` / `tipo` | int/string | não | |
| `bimestre` | int 1–4 | não | |
| `data_inicio` / `data_fim` | string | não | `YYYY-MM-DD` ou `dd/mm/aaaa` |
| `status` | string | não | `finalizado` (default), `todos`, `em_andamento`, `cancelada` |
| `limite` | int | não | default ~50 |

**Painel:** `lista`.

---

### `detalhar_prova_aluno`

Questão a questão: enunciado, marcada × correta, acerto/erro.

| Arg | Tipo | Obrig. | Notas |
|---|---|---|---|
| `aluno_id` | int | sim* | *ou resolve por nome se contexto |
| `prova_id` | int | preferencial | |
| `somente_erros` | bool | não | `true` = só erradas |
| `materia_nome` / `titulo` | string | não | resolve prova se id ausente |

**Painel:** `detalhe_prova` (ou `candidatos_provas`).

---

### `resumo_provas_aluno`

Consolidado: `total_acertos`, `total_erros`, %; por tipo / matéria / bimestre.

| Arg | Tipo | Obrig. | Notas |
|---|---|---|---|
| `aluno_id` / `aluno_nome` | int/string | sim | + `turma` se preciso |
| filtros de período/matéria/tipo | — | não | mesmos da listagem |

**Painel:** `resumo`.  
**Quando usar:** “quantos acertos/erros”, consolidado — **não** detalhe de questão.

---

## 2. Aluno — jornadas

**Service:** `JornadasAlunoConsultaService`  
**MCP:** `mcp/jornadas-aluno`  
**Painéis:** `lista_jornadas`, `detalhe_jornada`, `resumo_jornadas`

### `listar_materias_jornadas`

Matérias usadas em jornadas.

### `listar_jornadas_aluno`

| Arg | Tipo | Notas |
|---|---|---|
| `aluno_id` / `aluno_nome` | int/string | |
| `turma_nome` | string | |
| `materia_id` / `materia_nome` | | |
| `bimestre` | int | |
| `status_aluno` | string | `concluida` \| `em andamento` \| `nao iniciada` |
| `data_inicio` / `data_fim` | string | |
| `limite` | int | |

### `detalhar_jornada_aluno`

| Arg | Tipo | Notas |
|---|---|---|
| `aluno_id` | int | |
| `jornada_id` | int | |
| `somente_erros` | bool | só exercícios errados |

### `resumo_jornadas_aluno`

Totais: concluídas / em andamento / não iniciadas, % médio, por matéria/bimestre.

---

## 3. Professor — provas e saúde

**Service:** `ProvasProfessorConsultaService`  
**MCP:** `mcp/provas-professor`  
**Painéis:** `candidatos_professores`, `resumo_professor`, `lista_provas_professor`, `detalhe_prova_professor`, `ranking_erros_professor`, `saude_professor`

### `buscar_professores`

| Arg | Tipo |
|---|---|
| `termo` / `nome` / `professor_nome` | string |

### `listar_turmas_professor`

| Arg | Tipo |
|---|---|
| `professor_id` ou `professor_nome` | |

### `listar_provas_professor`

Provas do professor com agregados de acertos/erros dos alunos.

| Arg | Notas |
|---|---|
| `professor_id` / `professor_nome` | |
| `turma_nome`, `materia_nome` | filtros |
| `data_inicio` / `data_fim` | |
| `limite` | |

### `resumo_provas_professor`

Totais de acertos/erros das provas do professor (por matéria/turma).

### `detalhar_prova_professor`

| Arg | Tipo |
|---|---|
| `professor_id` | int |
| `prova_id` | int |

Acertos/erros **por aluno** na prova.

### `ranking_erros_prova_professor`

| Arg | Tipo |
|---|---|
| `professor_id` | int |
| `prova_id` | int |
| `limite` | int (default 15) |

Questões mais erradas da prova.

### `saude_turmas_professor`

KPIs crítico/atenção via `SaudeAprendizagemService`, restrito às turmas do professor.

| Arg | Notas |
|---|---|
| `professor_id` / `professor_nome` | |
| `turma_nome` / `turma_id` | opcional |
| `ano_letivo_id` | opcional |
| `nivel` | `critico` \| `atencao` \| `monitorar` \| `saudavel` \| `sem_dados` |

---

## 4. Turma, bloco, jornadas do professor, boletim, faltas

**Service:** `AssistenteConsultaAmpliadaService`  
**MCP:** `mcp/assistente-consulta`  
**Controller:** `AssistenteConsultaMcpController`

### `saude_turma`

Saúde de **uma turma** (sem precisar de professor).

| Arg | Tipo | Obrig. |
|---|---|---|
| `turma_id` ou `turma_nome` / `turma` | | sim |
| `ano_letivo_id` | int | não |
| `nivel` | string | não |

**Painel:** `saude_turma`.  
**Retorno:** `kpis`, `alunos_atencao[]`, `ano_letivo`.

---

### `resumo_provas_turma`

Totais de acertos/erros das provas dos alunos da turma, por matéria.

| Arg | Tipo |
|---|---|
| `turma_id` / `turma_nome` | |
| `data_inicio` / `data_fim` | |

**Painel:** `resumo_turma`.

---

### `buscar_blocos`

| Arg | Tipo |
|---|---|
| `titulo` / `termo` | string |
| `turma_id` / `turma_nome` | |
| `limite` | int (máx. 30) |

**Painel:** `lista_blocos`.

---

### `resultados_bloco`

Dashboard do bloco: indicadores, por turma, questões mais erradas, alunos em atenção.

| Arg | Tipo | Obrig. |
|---|---|---|
| `bloco_id` | int | preferencial |
| `titulo` / `bloco_titulo` | string | alt. (pode devolver `candidatos`) |

**Painel:** `resultados_bloco` ou `candidatos_blocos`.  
**Obs.:** blocos `lancamento_nota` são rejeitados (usar relatório de notas).

**Service interno:** `ExamBlockResultsDashboardService::build`.

---

### `resumo_jornadas_professor`

Consolidado de jornadas do professor (`JornadasRelatorioService`).

| Arg | Tipo |
|---|---|
| `professor_id` / `professor_nome` | |
| `turma_id` / `turma_nome` | opcional (`tipo=turma`) |
| `ano_letivo` / `jr_ano_letivo` | ano **numérico** (não id) |
| `somente_atencao` | bool |

**Painel:** `resumo_jornadas_professor`.  
**Retorno:** `totais` (`atribuidas`, `concluidas`, `pendentes`, `taxa_pct`), `alunos_atencao[]`.

---

### `boletim_aluno`

Boletins **já gerados** visíveis para coordenação.

| Arg | Tipo |
|---|---|
| `aluno_id` / `aluno_nome` | |
| `turma` / `turma_nome` | desambiguação |
| `exibir_em` | `boletim` \| `notas` (opcional) |

**Painel:** `boletim_aluno`.  
**Service:** `BoletimConfig::getGeneratedBoletinsByAluno(..., 'coordenacao')`.  
**Limite no payload:** até 12 eventos (campo `total_eventos` = total real).

---

### `faltas_aluno`

Frequência do aluno via diário de classe (`FrequencyService`).

| Arg | Tipo |
|---|---|
| `aluno_id` / `aluno_nome` | |
| `turma` / `turma_nome` | |
| `data_inicio` / `data_fim` | default = datas do ano letivo ativo |
| `ano_letivo_id` | se período omitido |

**Painel:** `faltas_aluno`.  
**Retorno:** `frequencia` (`total_aulas`, `presencas`, `faltas`, `faltas_justificadas`, `percentual`), `turma_percentual`.

---

## 5. Assistente de Boletim (configuração de regras)

**Não** entram no chat `/admin/assistente`. Servem ao wizard de configuração de boletim.

**Controller:** `BoletimAssistenteController`  
**MCP:** `mcp/boletim-assistente`

| Tool | Descrição |
|---|---|
| `listar_tipos_avaliacao` | Tipos de eventos de prova |
| `listar_turmas` | Turmas ativas |
| `listar_materias` | Matérias |
| `listar_regras` | Regras/eventos de boletim cadastrados |
| `obter_regra` | Regra completa (`regra_id`) |
| `listar_eventos_prova` | Blocos; filtro `tipo_avaliacao_id` |
| `resolver_blocos_por_tipo` | `tipo` + `data_inicio`/`data_fim` → IDs de blocos |
| `propor_regra_nl` | Pedido NL → rascunho JSON (**não salva** sozinho) |
| `contexto_catalogo` | Snapshot tipos/turmas/matérias/regras |

---

## Painéis UI (`montarPainel`)

| `tipo` | Origem típica |
|---|---|
| `candidatos` | vários alunos |
| `candidatos_provas` | várias provas |
| `candidatos_professores` | vários professores |
| `candidatos_turmas` | várias turmas (`saude_turma`, `resumo_provas_turma`) |
| `candidatos_blocos` | vários blocos (`resultados_bloco`) |
| `resumo` / `lista` / `detalhe_prova` | provas aluno |
| `resumo_jornadas` / `lista_jornadas` / `detalhe_jornada` | jornadas aluno |
| `resumo_professor` / `lista_provas_professor` / `detalhe_prova_professor` / `ranking_erros_professor` / `saude_professor` | professor |
| `saude_turma` / `resumo_turma` | turma |
| `lista_blocos` / `resultados_bloco` | bloco |
| `resumo_jornadas_professor` | jornadas professor |
| `boletim_aluno` / `faltas_aluno` | boletim / frequência |

Arquivo da view: `app/Views/admin/assistente/index.php`.

---

## Arquivos-chave

| Peça | Caminho |
|---|---|
| Orquestrador chat | `app/Services/ProvasAlunoAssistenteService.php` |
| Provas aluno | `app/Services/ProvasAlunoConsultaService.php` |
| Jornadas aluno | `app/Services/JornadasAlunoConsultaService.php` |
| Provas professor | `app/Services/ProvasProfessorConsultaService.php` |
| Turma/bloco/boletim/faltas | `app/Services/AssistenteConsultaAmpliadaService.php` |
| Controller chat | `app/Controllers/Admin/ProvasAlunoAssistenteController.php` |
| MCP ampliado | `app/Controllers/Admin/AssistenteConsultaMcpController.php` |
| Rotas | `config/routes/admin.php` |
| Permissões | `app/Core/AdminPermissionMatrix.php` (`assistente`, `provas_online`, …) |
| Créditos IA | módulo `provas_aluno_assistente_mensagem` (TudiCoins) |

---

## Regras de produto (para quem implementa / LLM)

1. **Somente leitura** nas tools de consulta — nunca gravar notas/faltas/jornadas.
2. Sempre preferir `*_id` quando já conhecido; nome + `turma` para desambiguar.
3. Pedido de “total de erros/acertos” → **resumo**, não detalhe de questão.
4. Pedido “qual questão errou” → `detalhar_*` com `somente_erros=true`.
5. Turma **sem** professor → `saude_turma` / `resumo_provas_turma`; **com** professor → `saude_turmas_professor` / `resumo_provas_professor`.
6. Isolamento multi-tenant = conexão PDO; **nunca** `WHERE escola_id` em query de tenant.
7. Ambíguo → devolver `candidatos` + aviso; UI/MCP devem preservar a lista.

---

## Changelog deste doc

| Data | Mudança |
|---|---|
| 2026-07-21 | Criação inicial: 24 tools do chat + MCP + tools do boletim-config |
| 2026-07-21 | Inclusão turma/bloco/jornadas professor/boletim/faltas (`AssistenteConsultaAmpliadaService`) |
| 2026-07-21 | Wiki no Master (`/master/documentacao`) + páginas estrutura/multi-tenant/perfis/assistente |
