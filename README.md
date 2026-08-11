# EducaTudo

Repositorio de trabalho do EducaTudo. A raiz funciona como um workspace: ela junta a plataforma web PHP, o app mobile, servicos auxiliares, infraestrutura, documentacao e testes.

## Mapa rapido

| Pasta/arquivo | Papel |
| --- | --- |
| `backend/` | Backend/web PHP. Codigo em `app/`, `config/`, `bootstrap/`; **docroot web = `backend/public/`** apenas. |
| `familia/` | App Flutter dos pais/responsaveis. Contem `lib/`, `android/`, `web/`, `assets/`, `test/` e `pubspec.yaml`. |
| `services/` | Servicos auxiliares independentes, como `apostila-ai`. |
| `ws-server/` | Servidor WebSocket/Node separado da plataforma PHP. |
| `database/` | SQL global do workspace, principalmente migracoes e arquivos compartilhados fora do backend. |
| `docker/` + `docker-compose.yml` | Infra local (Nginx + PHP-FPM). Monta `./backend` em `/var/www/html`; docroot Nginx = `public/`. |
| `docs/` | Documentacao tecnica e operacional geral. |
| `specs/` | PRD, design e tarefas de produto. |
| `prompts/` | Prompts e materiais de apoio para IA. |
| `.claude/` + `CLAUDE.md` | Contexto, comandos e padroes para agentes de IA. |
| `package.json` | Testes E2E Playwright do workspace. |
| `tmp/` | Rascunhos, auditorias temporarias e saidas descartaveis. |

## Regra de organizacao

- Codigo de produto fica dentro da aplicacao dona: backend em `backend/`, mobile em `familia/`, servicos em `services/<nome>/`, WebSocket em `ws-server/`.
- Arquivos de orquestracao que afetam mais de uma aplicacao ficam na raiz: `docker-compose.yml`, `package.json`, `playwright.config.ts`, `README.md`, `CLAUDE.md`.
- Documentacao permanente fica em `docs/` ou `specs/`. Analises temporarias ficam em `tmp/`.
- Saidas geradas nao devem competir com codigo na raiz. Use `tmp/`, `playwright-report/`, `test-results/`, `graphify-out/` ou a pasta de build da propria aplicacao.
- **Seguranca:** o servidor web deve apontar **somente** para `backend/public/`. Ver `backend/STRUCTURE.md`.

## Git

Todo o codigo (backend PHP, app Flutter, infra, testes) vive neste repositorio:

**https://github.com/launstecnologia/educatudo**

```bash
git clone git@github.com:launstecnologia/educatudo.git
cd educatudo
./scripts/init-local.sh
```

## Setup local

Domínios locais (padrão igual produção, com TLD `.localhost`):

| Ambiente | Local | Produção |
|----------|-------|----------|
| Master | http://master.localhost | https://master.educatudo.com |
| Colag | http://colag.localhost | https://colag.educatudo.com |

```bash
./scripts/init-local.sh
```

Ambiente **VPS** (MySQL remoto, sem container mysql):

```bash
cp backend/.env.vps.example backend/.env   # editar DB_HOST e credenciais
./scripts/up-vps.sh --pull --composer
```

Ver `docs/DEPLOY-VPS.md` e `docs/DEPLOY-DOMINIOS.md`.

O script configura `backend/.env`, sobe Docker na porta **80** e cria os bancos `educatudo_master` + `educatudo_colag`.

**Primeiro acesso:** http://master.localhost → criar admin master.

**Colag:** http://colag.localhost

`*.localhost` resolve para `127.0.0.1` na maioria dos browsers. Se não funcionar:

```bash
sudo ./scripts/setup-local-hosts.sh
```

Reinit banco:

```bash
docker compose exec php php scripts/init_local_multitenant.php --force-school
```

## Leitura recomendada

- `backend/STRUCTURE.md` — layout seguro e deploy.
- `CLAUDE.md` para contexto tecnico, arquitetura multi-tenant e padroes de codigo.
- `specs/PRD.md` para escopo de produto.
- `familia/docs/` para arquitetura do app Flutter.
- `services/apostila-ai/README.md` para o servico de apostilas com IA.
