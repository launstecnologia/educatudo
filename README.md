# EducaTudo

Repositorio de trabalho do EducaTudo. A raiz funciona como um workspace: ela junta a plataforma web PHP, o app mobile, servicos auxiliares, infraestrutura, documentacao e testes.

## Mapa rapido

| Pasta/arquivo | Papel |
| --- | --- |
| `src/` | Backend/web PHP. Contem `index.php`, `app/`, `config/`, `public/`, `cron/`, `storage/`, `uploads/` e dependencias Composer/NPM do backend. |
| `app/` | App Flutter dos pais/responsaveis. Contem `lib/`, `android/`, `web/`, `assets/`, `test/` e `pubspec.yaml`. |
| `services/` | Servicos auxiliares independentes, como `apostila-ai`. |
| `ws-server/` | Servidor WebSocket/Node separado da plataforma PHP. |
| `database/` | SQL global do workspace, principalmente migracoes e arquivos compartilhados fora do backend. |
| `docker/` + `docker-compose.yml` | Infra local. O compose monta `./src` em `/var/www/html`. |
| `docs/` | Documentacao tecnica e operacional geral. |
| `specs/` | PRD, design e tarefas de produto. |
| `prompts/` | Prompts e materiais de apoio para IA. |
| `.claude/` + `CLAUDE.md` | Contexto, comandos e padroes para agentes de IA. |
| `package.json` | Testes E2E Playwright do workspace. |
| `tmp/` | Rascunhos, auditorias temporarias e saidas descartaveis. |

## Regra de organizacao

- Codigo de produto fica dentro da aplicacao dona: backend em `src/`, mobile em `app/`, servicos em `services/<nome>/`, WebSocket em `ws-server/`.
- Arquivos de orquestracao que afetam mais de uma aplicacao ficam na raiz: `docker-compose.yml`, `package.json`, `playwright.config.ts`, `README.md`, `CLAUDE.md`.
- Documentacao permanente fica em `docs/` ou `specs/`. Analises temporarias ficam em `tmp/`.
- Saidas geradas nao devem competir com codigo na raiz. Use `tmp/`, `playwright-report/`, `test-results/`, `graphify-out/` ou a pasta de build da propria aplicacao.
- Nao mover ou renomear `src/` sem planejar a migracao, porque Docker, deploy e o repositorio Git interno dependem desse caminho hoje.

## Leitura recomendada

- `CLAUDE.md` para contexto tecnico, arquitetura multi-tenant e padroes de codigo.
- `specs/PRD.md` para escopo de produto.
- `app/docs/` para arquitetura do app Flutter.
- `services/apostila-ai/README.md` para o servico de apostilas com IA.
