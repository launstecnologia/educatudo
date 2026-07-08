# Como programar com IA neste projeto

> Guia prático pra qualquer dev do time: como pedir uma alteração, criar um
> módulo novo, ou corrigir um bug usando Claude Code (ou outra IA) neste
> repositório. Não repete o que já está no `CLAUDE.md` — assume que você já
> leu aquele arquivo pelo menos uma vez.

## 1. O que a IA já sabe sem você explicar

Antes de pedir qualquer coisa, vale saber o que já está documentado e é lido
automaticamente (ou quase):

| Arquivo | O que tem | Carregado quando |
|---|---|---|
| `CLAUDE.md` (raiz) | Arquitetura, convenções, regras de segurança, mapa do código | Sempre, em toda conversa neste repo |
| `.claude/docs/architecture.md` | Fluxo de resolução de tenant, diagrama completo | Sob demanda (`@` no CLAUDE.md) |
| `.claude/docs/environment.md` | Env vars, integrações externas | Sob demanda |
| `.claude/docs/coding-standards.md` | Padrão de código com exemplos | Sob demanda |
| `.claude/examples/` | `ExampleController.php`, `ExampleModel.php`, `ExampleService.php` — estrutura de referência pra copiar | Quando a IA cria um módulo novo |
| `specs/PRD.md`, `specs/design.md`, `specs/tasks.md` | O quê o sistema faz, porquê das decisões técnicas, backlog atual | Sempre (referenciados no CLAUDE.md) |
| `prompts/admin_ui_sistema.md` | Design system das telas do admin (cores, tabela, formulário, dropdown, paginação) | Sempre que a tela for do admin (instrução no CLAUDE.md) |
| Skill `educatudo-admin-ui` | Padrão de offcanvas pra cadastro simples (exceção do doc acima) | Auto, quando você menciona offcanvas/cadastro/drawer, ou telas como Usuários/Professores |
| Skill `testing` | Como rodar/escrever testes Playwright | Auto, quando você menciona testar/testes |

Na prática: você **não precisa colar contexto do projeto no pedido** — a IA já
lê isso sozinha. O que você precisa é ser específico sobre **o que muda**, não
sobre **como o projeto funciona**.

## 2. Os 4 comandos prontos (`.claude/commands/`)

Digite `/comando <descrição>` no chat. Cada um segue um checklist fixo —
prefira usá-los em vez de pedir "faça X" solto, porque eles já garantem que a
IA não pule etapas (registrar no backlog, chamar o subagent certo, etc.).

| Comando | Quando usar | O que ele garante |
|---|---|---|
| `/task <pedido>` | Qualquer coisa que não é bug nem módulo novo nem migration — feature, tela, ajuste, relatório | Estrutura o pedido (objetivo/perfis/dados/escopo), registra em `specs/tasks.md`, planeja se tocar 3+ arquivos, implementa, roda `code-reviewer`/`migration-checker` conforme o caso, fecha com resumo |
| `/new-module <nome>` | Módulo admin/aluno/professor novo do zero | Cria Controller (magro) + Model + Service + Views por perfil, rotas em `config/routes/<perfil>.php`, registra no `FeatureGate` se for opcional por escola |
| `/migration <descrição>` | Precisa de tabela/coluna nova | Descobre o próximo número, decide tenant vs master, escreve idempotente, **sempre** cria o rollback, valida com `migration-checker` — não executa no banco |
| `/fix-issue <problema>` | Bug relatado (comportamento errado, erro em produção) | Localiza a causa raiz (rota → Controller → Service/Model, checa `storage/logs/`), corrige o mínimo necessário, roda `code-reviewer` |

Se o pedido não se encaixa em nenhum desses 4 claramente, use `/task` — é o
fluxo padrão genérico.

## 3. Como pedir uma tela nova ou alterada do admin

Além do fluxo do `/task`, telas do admin têm uma decisão extra: **offcanvas
ou página própria?** Você não precisa saber a resposta de antemão — a IA
decide isso lendo `prompts/admin_ui_sistema.md` §1d, mas ajuda se você já
sinalizar:

- **CRUD simples** (uma entidade só, sem sub-recursos, cabe num formulário
  curto — tipo "cadastro de X"): tende a ser offcanvas.
- **Fluxo com várias etapas ou conteúdo rico** (prova com questões, jornada
  com módulos, redação com propostas): sempre página própria, nunca
  offcanvas — não peça offcanvas pra esse tipo de tela.

Se você já sabe que quer manter consistência com uma tela existente
específica, cite o nome dela no pedido (ex.: "igual a tela de Professores").

## 4. Exemplo de pedido bom vs pedido vago

**Vago (a IA vai ter que perguntar antes de começar):**
> "cria uma tela de bolsas de estudo"

**Bom (a IA já consegue estruturar e ir direto pro plano):**
> "cria a tela de admin de Bolsas de Estudo — CRUD simples (nome, percentual,
> critério, ativo/inativo), só admin/diretor acessa, sem migration (a tabela
> `bolsas` já existe), não precisa ser opcional por escola"

Você não precisa escrever um pedido tão detalhado sempre — a IA pergunta o
que falta. Mas quanto mais específico, menos idas e vindas.

## 5. O que acontece automaticamente (você não precisa pedir)

- **Lint de PHP** (`php -l`) roda sozinho depois de qualquer edição/criação de arquivo `.php` (hook em `.claude/settings.json`).
- **Edição direta de `.env` é bloqueada** — a IA vai editar `.env.example` e te avisar, nunca o `.env` real.
- **Subagent `code-reviewer`** deveria rodar depois de qualquer mudança em PHP, antes de considerar a tarefa pronta — se você usou `/task`, `/new-module` ou `/fix-issue`, isso já está no checklist. Se pediu algo fora desses comandos, pode pedir explicitamente: "roda o code-reviewer nisso".
- **Subagent `migration-checker`** roda depois de qualquer `.sql` novo/alterado em `database/migrations/`.

## 6. O que você sempre precisa aprovar manualmente

- **Plan Mode**: pra qualquer tarefa que toque 3+ arquivos ou tenha mais de uma forma de implementar, a IA entra em modo de planejamento e mostra o plano antes de escrever código — você aprova ou pede ajuste.
- **Commit e push**: a IA nunca commita/dá push sozinha a menos que você peça explicitamente.
- **Migrations no banco**: a IA cria o `.sql`, mas quem roda é você, pelo painel Master ou `php src/scripts/run_migrations.php` — nunca direto no banco, nunca pela IA.
- **Ações destrutivas** (force push, `git reset --hard`, apagar branch, apagar arquivo não gerado nesta sessão): sempre confirmadas antes.

## 7. Regras que a IA nunca vai quebrar (não precisa lembrar toda vez)

Resumo do CLAUDE.md, só pra não esquecer que existe — não precisa repetir no
pedido:

- Isolamento multi-tenant por conexão PDO — nunca `WHERE escola_id`.
- Prepared statements sempre, zero concatenação em SQL.
- CSRF em toda rota POST fora de `/api/*`.
- Ownership validado em recurso de aluno.
- Chamada de IA que demora mais de 2s é sempre assíncrona (`AIJobService`).
- Nomenclatura em inglês pra código novo (tabelas do banco continuam em PT, é legado).

## 8. Onde cavar mais fundo

| Preciso de... | Onde olhar |
|---|---|
| Visão geral do projeto e regras de arquitetura | `CLAUDE.md` |
| Arquitetura detalhada, fluxo de tenant | `.claude/docs/architecture.md` |
| Padrões de código com exemplo | `.claude/docs/coding-standards.md` |
| Design system das telas do admin | `prompts/admin_ui_sistema.md` |
| Padrão de offcanvas em cadastro simples | `.claude/skills/educatudo-admin-ui/SKILL.md` |
| O que o produto faz, pra quem | `specs/PRD.md` |
| Decisões técnicas e o porquê | `specs/design.md` |
| Backlog atual | `specs/tasks.md` |
