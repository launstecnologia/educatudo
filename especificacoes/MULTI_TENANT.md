# Multi-Tenant EducaTudo

## Ativação

- **Variável no `.env`:** `MULTI_TENANT=false` (padrão). Para ativar: `MULTI_TENANT=true`.
- Enquanto `MULTI_TENANT=false`, o sistema continua usando uma única conexão (`.env`: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`), sem alteração de comportamento.

## Quando MULTI_TENANT=true

1. **Banco master:** O `.env` deve apontar para o **banco administrador** (onde estão as tabelas `escolas` e `config_escolas_banco`). Ex.: `DB_NAME=educatudo_master`.
2. **Resolução do tenant:** No início de cada requisição web, o sistema resolve a escola por:
   - Header `X-Tenant` (valor = slug da escola), ou
   - Domínio da requisição (`HTTP_HOST`) comparado ao campo `dominio` da tabela `escolas`.
3. **Conexão por escola:** A aplicação usa a conexão do banco **da escola** resolvida (credenciais em `config_escolas_banco`). Todo código que chama `Database::getInstance()` passa a receber essa conexão.

## Painel admin master (login e senha)

Com `MULTI_TENANT=true`, o painel administrativo **master** (gerencia escolas/tenants) fica em:

- **URL:** `{URL_BASE}/master` (ex.: `https://master.educatudo.com/master`).
- **Redirecionamento:** Se você usar um subdomínio só para o master (ex.: `master.educatudo.com`), defina no `.env` a variável `MASTER_DOMAIN=master.educatudo.com`. Assim, ao acessar a raiz (`https://master.educatudo.com`) o sistema redireciona automaticamente para `/master`.
- **Primeiro acesso:** Se não existir nenhum usuário na tabela `usuarios_master`, a tela exibida é **“Criar primeiro administrador”** (nome, e-mail e senha). Após criar, o usuário já entra no painel.
- **Acessos seguintes:** Tela de login (e-mail + senha). As credenciais são validadas na tabela `usuarios_master` do banco master.
- **Após login:** Dashboard em `/master/dashboard` (lista escolas). Logout em `/master/logout`.

Não é necessário definir login/senha no código: o primeiro admin é criado pela própria interface.

## Configurações por escola (dev settings no Master)

Com multi-tenant ativo, **todas** as configurações que antes eram editadas em "Configurações Avançadas" (admin da escola, perfil dev) passam a ser gerenciadas no **painel Master**, por escola:

- **Layout:** cor primária, logo, capa de login, título e subtítulo do sistema.
- **Módulos do sistema:** estado (Habilitado / Desabilitado / Inativo) dos módulos Geral, Professor e Aluno (Planos de Aula, Arquivos, Jornada, Prova, Chat, Redação, Mini Cursos, Educa Livros, Educa Labs, Flash Card, Exercícios, Tudinha, Fórum, Drive, Games, etc.).
- **Links no submenu:** links personalizados no menu do aluno e do professor (nome, URL, abrir em nova guia).
- **Apps externos:** URLs do EducaLabs, Games e Notes, e ID da instituição (inst) para acesso com token.
- **E-mail (SMTP):** servidor, porta, usuário, senha, remetente e reply-to.
- **Prompts de IA:** textos usados pelos modelos (redação, correção, Tudinha, OCR, prova, exercícios).
- **Chaves de API:** OpenAI, Gamma, Nanobanana (e outras da aba Chaves de API).

Essas configurações são gravadas na tabela **config_escolas_layout** (banco master) e, ao salvar a escola (ou ao marcar para sincronizar), são enviadas para a tabela **config_layout** do banco da escola (tenant). O aplicativo da escola usa sempre o `config_layout` do próprio tenant (via LayoutHelper).

**Comportamento do Dev na escola (tenant):** Quando `MULTI_TENANT=true`, a tela "Configurações Avançadas" no admin da escola permanece **visível em modo somente leitura**: é exibido um aviso de que as alterações são feitas no painel Master e os formulários são desabilitados. As rotas de salvamento (módulos, layout, e-mail, prompts, chaves de API) retornam 403 nesse contexto.

## SQL do banco master

Executar **apenas no banco master** (não nos bancos das escolas):

- Arquivo: `src/database/migrations/multi_tenant_master.sql`
- Cria: `escolas`, `config_escolas_banco`, `migrations_escolas`, `usuarios_master` (login do painel master).

## Primeira escola (migração)

1. Criar o banco master e rodar `multi_tenant_master.sql`.
2. Inserir um registro em `escolas` (nome, slug, dominio, ativo=1).
3. Inserir um registro em `config_escolas_banco` com as credenciais do banco **atual** da escola (host, porta, nome_banco, usuario, senha_criptografada).
4. No `.env`: apontar `DB_*` para o banco **master** e definir `MULTI_TENANT=true`.
5. Acessar pelo domínio configurado em `escolas.dominio` (ou enviar header `X-Tenant` com o slug).

## Crons

Quando `MULTI_TENANT=true`, os crons que usam `CronMultiTenantHelper::run()` executam a lógica **uma vez por escola ativa** (cada uma com sua conexão). Crons adaptados: `atualizar_status_jornadas.php`, `backup.php`, `llm_usage_daily.php`, `rss_update.php`.

**Importante:** Cada escola deve ter em `config_escolas_banco` um `nome_banco` que seja o **banco da escola** (tenant), e não o banco do master. Se uma escola estiver apontando para o mesmo banco do master (ex.: `educa_master`), o helper **pula** essa escola no cron (evitando erro "table jornadas doesn't exist") e registra no log. Corrija no painel Master em Editar escola → Banco de dados.

## Upload de logo/capa (Master) e S3

No formulário de escola do painel Master (Layout da escola), é possível **enviar arquivo** para logo, logo 1x1 e capa do login. O destino depende do `.env` do servidor onde o Master roda:

- **`MEDIA_STORAGE=local`** (padrão): as imagens são salvas em disco (ex.: `public/uploads/layout/`). A URL gravada aponta para o próprio servidor.
- **`MEDIA_STORAGE=s3`**: as imagens são enviadas para o **AWS S3**. É obrigatório preencher no mesmo `.env`: `AWS_BUCKET`, `AWS_ACCESS_KEY`, `AWS_SECRET_KEY` e opcionalmente `AWS_REGION`. A URL gravada será do tipo `{URL}/media/serve?type=layout&key=...` (o app gera URL assinada ao exibir).

Se o upload falhar (ex.: S3 com credenciais erradas ou `MEDIA_STORAGE=local` com pasta sem permissão), o sistema exibe mensagem de erro e não grava a URL. Verifique o `.env` e as permissões do storage.

## Nginx (evitar 404 em /master)

O servidor precisa repassar **todas** as requisições ao `index.php`. Se ao acessar `/master` aparecer **404 Not Found** do nginx, ajuste o server block:

```nginx
server {
    server_name master.educatudo.com;
    root /caminho/para/o/projeto;   # pasta onde está o index.php
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;   # ou 127.0.0.1:9000
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

O importante é **`try_files $uri $uri/ /index.php?$query_string;`**: assim, ao pedir `/master` ou `/master/dashboard`, o nginx envia a requisição ao `index.php` e o PHP trata a rota.

## Subdomínio (configuração no Master)

O **subdomínio/domínio** de cada escola é configurado **no próprio painel Master**:

- No formulário de **Nova escola** ou **Editar escola**, o campo **"Domínio (URL)"** define em qual endereço a escola responde.
- Exemplos: `escola1.localhost`, `escola1.educatudo.com.br`, `colag.seudominio.com`.
- O sistema compara o `HTTP_HOST` da requisição com o valor de `escolas.dominio`; se bater, aquele request usa o banco daquela escola.
- **Dica:** Use o mesmo slug da escola no subdomínio (ex.: slug `escola1` → domínio `escola1.seudominio.com`). No formulário, ao preencher o slug você pode preencher o domínio manualmente; em alguns ambientes há sugestão automática.

O painel **não** cria registro DNS nem configura Nginx/Apache: isso é infraestrutura. Você precisa:

1. Configurar DNS (ex.: wildcard `*.educatudo.com.br` ou CNAME por escola).
2. Configurar o servidor web para aceitar esse host e repassar ao PHP (o mesmo `index.php` atende todos os tenants).

## Criar banco e usuário MySQL pelo Master (opcional)

Hoje o Master apenas **guarda** as credenciais do banco da escola em `config_escolas_banco`. O banco e o usuário MySQL costumam ser criados manualmente (ou por script de deploy).

É possível ativar a **criação automática** de banco e usuário ao cadastrar/editar uma escola:

1. No **.env** (servidor onde o Master roda), defina um usuário MySQL com privilégios de administrador:
   - `DB_ADMIN_USER=root` (ou outro usuário com `CREATE DATABASE`, `CREATE USER`, `GRANT`)
   - `DB_ADMIN_PASS=senha_do_admin`
   - Opcional: `DB_ADMIN_HOST=localhost` e `DB_ADMIN_PORT=3306` (padrão: mesmo `DB_HOST`/`DB_PORT` do master).

2. No formulário de escola (Nova ou Editar), preencha **Nome do banco**, **Usuário** e **Senha** do futuro tenant e marque **"Criar banco e usuário MySQL automaticamente"**.

3. Ao salvar, o sistema irá:
   - Conectar ao MySQL com `DB_ADMIN_*`;
   - Executar `CREATE DATABASE`, `CREATE USER` e `GRANT ... ON db.* TO user`;
   - Rodar as migrations do EducaTudo no novo banco (tabelas do tenant);
   - Gravar as credenciais em `config_escolas_banco` como hoje.

**Segurança:** Use `DB_ADMIN_*` apenas em ambientes controlados; o usuário tem privilégios altos. Em muitos provedores de banco (ex.: RDS, Cloud SQL) o usuário da aplicação não tem permissão para criar bancos; nesses casos deixe `DB_ADMIN_USER` em branco e crie banco/usuário manualmente ou por pipeline.

## Rollback

- Definir `MULTI_TENANT=false` no `.env`.
- Opcionalmente, apontar `DB_*` de volta para o banco da escola (comportamento single-tenant original).
