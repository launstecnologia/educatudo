# Nomenclatura — padrão e estratégia de padronização

> Decisão de 2026-07-08: reverter a convenção anterior (inglês obrigatório em código novo) para português. Motivo completo em `specs/design.md` (registro de decisões). Este documento é o mapa de trabalho para quem for renomear algo — não um checklist a executar de uma vez.

## Como chegamos aqui

O projeto rodava com a regra "classes/métodos/arquivos novos em inglês, tabela em PT como legado". Uma auditoria em 2026-07-08 mostrou que essa regra criou duas eras dentro do mesmo repo:

- **Núcleo histórico** (jornadas, provas, redações, matrícula, diário — código de antes de ~jun/2026): 100% português, inclusive nas tabelas.
- **Módulos recentes** (Finance, Enrollment, Accommodations, Inventory, Patrimony, School Calendar/Communication, Mobile*, Facial*, e boa parte de Controllers/Models mesmo em domínio PT): nomes de arquivo/classe/tabela em inglês, seguindo a regra antiga.

Achado importante: o **"miolo"** do sistema — nome de coluna, chave de array passada pro Model, atributo `name=` do formulário — já está ~100% consistente em português em todos os módulos auditados (Usuários, Enrollment, Provas, Finance, Jornadas), mesmo quando a "casca" (tabela/classe/pasta) está em inglês. Ou seja: o vocabulário de negócio nunca foi de fato traduzido, só a moldura arquitetural. O trabalho de padronização está concentrado em nome de arquivo/classe/pasta/tabela, não em centenas de nomes de coluna espalhados.

## Padrão vigente (a partir de 2026-07-08)

- **Classes, métodos, arquivos, pastas**: português. Ex.: `ProvaController`, `app/Models/Prova/Prova.php`, `app/Views/professor/provas/`.
- **Coluna do banco = chave do array de dados = atributo `name=` do input**: sempre o mesmo nome. Ex.: coluna `nome` → `$_POST['nome']` → `$dados['nome']` → `<input name="nome">`. Isso já é a prática real; só está sendo formalizado.
- **Comentários de prosa**: português. PHPDoc (`@param`, `@return array<string,mixed>`) mantém a sintaxe/tipos em inglês — é convenção de tooling, não prosa.
- **Sufixo arquitetural continua em inglês**: `Controller`, `Service`, `Model`, `Middleware` como sufixo de classe (ex.: `ProvaController`, não `ControladorProva`) — é papel na arquitetura, não vocabulário de negócio.
- **Exceções que não se traduzem**:
  - Siglas oficiais do MEC: `BNCC`, `ENEM`, `INEP`.
  - Nomes de marca/produto/módulo interno: `Tudinha`, `EducaLabs`, `EducaHits`, `EducaInclui`, `EducaShop`. Regra geral: **qualquer nome que apareça no menu/sidebar como identidade de módulo, ou como chave de `FeatureGate`, é nome próprio — não se traduz**, mesmo que o nome do arquivo/classe interno esteja em inglês (ex.: `AccommodationController` implementa o módulo `EducaInclui`, não deve virar "Acomodação").

**Antes de nomear um rename, grepar o vocabulário interno do módulo** (docblocks, comentários, `setFlashMessage`, títulos de tela) — o código quase sempre já fala o termo de domínio em português por dentro, mesmo com classe/arquivo em inglês por fora. Exemplo real (`AccommodationController`/EducaInclui): a tradução literal de "accommodation" seria "acomodação", mas o código já usa "Máscara de Acessibilidade" e "Laudo" internamente — os nomes certos são `MascaraAluno`, `RegraMascara`, `LaudoAluno`, não uma tradução ao pé da letra. Não adivinhar/traduzir sem checar primeiro.
- **Tabelas do banco**: seguem em português. As 58 tabelas legadas em inglês (lista abaixo) só são renomeadas caso a caso — nunca em lote — por causa do risco de rodar migration em cada banco de escola (ver `specs/design.md` §5 e `CLAUDE.md` — Migrações).

## Inventário — código em inglês (candidatos a rename incremental)

Não é uma lista para executar de uma vez; é a referência de onde está a dívida quando alguém for mexer em cada módulo (boy-scout rule).

| Camada | Contagem levantada em 2026-07-08 | Onde se concentra |
|---|---|---|
| Controllers | 166 arquivos, maioria em inglês mesmo em domínio PT | `Education/` mistura os dois padrões lado a lado (`CursoController` PT + `CourseCatalogController` EN); módulos 100% novos: Finance, Enrollment, Accommodations, Inventory, Patrimony, School Calendar/Communication, Mobile*, Facial* |
| Models | 84 arquivos, maioria em inglês | Mesmos módulos acima |
| Services | 85 arquivos, ~17% já em PT (Créditos, Bncc, Tudinha, SaudeAcademica, Jornadas, AlunoMovimentacao, ListaChamada) | Restante em inglês |
| Views | 641 arquivos, ~150 pastas — mistura mais visível porque pasta e arquivo dentro dela foram nomeados em épocas diferentes | Ex.: `teacher/journeys/` (pasta EN) contém `blocos-conteudo.php`, `corrigir-redacao.php` (PT); `admin/financeiro` (PT) e `admin/finance` (EN) coexistem |

Divergência interna encontrada (exemplo a corrigir quando tocar no módulo): `app/Controllers/User/UserController.php` (arquivo EN) contém `class UsuarioController` (classe PT) — arquivo e classe já deveriam bater e estão ambos em português quando corrigidos.

## Banco de dados

### Tabelas em inglês (58 de 343, ~17%)

```
accommodation_documents, accommodation_rules, ai_jobs,
assessment_versions, assessment_version_logs,
billing_message_log, billing_rule_config, config_dev,
enrollment, enrollment_audit, enrollment_score,
facial_device_pairing_codes, facial_devices,
finance_audit, finance_bank_accounts, finance_bills, finance_charges,
finance_chart_accounts, finance_config, finance_contract_discounts,
finance_contract_items, finance_contracts, finance_discount_rules,
finance_installments, finance_ledger, finance_payments, finance_plan_items,
finance_plans, finance_price_table, finance_receipts, finance_renegotiations,
inventory_items, inventory_lots, inventory_movements, inventory_requisitions,
inventory_suppliers, inventory_warehouses,
mobile_auth_sessions, mobile_devices, notes_tokens,
patrimony_assets, patrimony_inventory_checks, patrimony_movements,
school_calendar_events, school_calendar_event_classes, school_calendar_event_reads,
school_calendar_event_students, school_communications,
school_communication_attachments, school_communication_classes,
school_communication_reads, school_communication_replies,
school_communication_students, school_locations,
student_access_events, student_face_profiles, student_face_samples, webhooks
```

### Pares ambíguos — decidir com o time ANTES de renomear qualquer um dos dois

Estes não são só nomenclatura errada — parecem sistemas paralelos por decisão de produto. Renomear sem entender o porquê pode mascarar uma migração de dados incompleta:

- `matricula` (PT, antiga) vs `enrollment` (EN, nova) — a própria migration de `enrollment` documenta que é "separada da matricula existente, que é vínculo operacional".
- `finance_contracts` tem **as duas colunas ao mesmo tempo**: `matricula_id` e `enrollment_id`.
- `financeiro_valores_mensais` (PT) vs cluster `finance_*` (17 tabelas, EN) — dois sistemas financeiros paralelos.
- `calendario_letivo`/`calendario_letivo_eventos` (PT) vs `school_calendar_events` e derivadas (EN) — dois módulos de calendário letivo.
- `sessoes` (PT, sessão de usuário web) vs `mobile_auth_sessions` (EN, sessão do app) — aqui não é ambíguo, são conceitos distintos (web vs mobile); manter os dois, só ajustar nome se um dia forem unificados.

### Inconsistências internas ao português (independente do rename EN→PT)

- Singular vs plural sem critério: `curso`/`cursos`, `plano_curso`/`planos_aula`/`planos_creditos`, `reuniao_anexos`/`reunioes`.
- Separador de nome de pasta de View: a maioria usa `-` (`ano-letivo`), mas convive com `_` em alguns lugares (`boletim_guia`, `tentativas_login`).

## Como executar um rename (quando chegar a hora)

1. **Nunca big-bang.** Um módulo por vez, no espírito do item já existente em `specs/tasks.md` (renomear ao tocar no módulo por outro motivo).
2. **Código** (Controller/Model/Service/arquivo/pasta de View): renomear arquivo + classe + referências (rotas em `config/routes/<perfil>.php`, `require`/`use`), rodar `code-reviewer`, testar o fluxo manualmente ou via Playwright antes de considerar pronto.
3. **Banco** (tabela/coluna): `migration` + `_rollback.sql` correspondente, validado com o subagent `migration-checker`, executado só via painel Master (`/master/migrations`) — nunca direto no banco. Para tabelas dos pares ambíguos acima, resolver a decisão de produto primeiro.
4. Atualizar a tabela de "conflitos conhecidos" em `.claude/docs/coding-standards.md` conforme cada rename for feito, e mover o item correspondente para "Feito" em `specs/tasks.md`.
