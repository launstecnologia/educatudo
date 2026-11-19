# Plano de implementação — Fase B, só após aprovação

Nada deste arquivo foi aplicado. Não há deploy. A ordem abaixo evita fechar uma porta e deixar a mesma imagem sair por outra.

## Princípio de compatibilidade

As URLs já gravadas em HTML de exercício, documento e prova continuam existindo. O que muda é quem pode recebê-las. `<img>` e `<a>` no mesmo host enviam o cookie de sessão. Por isso exigir sessão não exige trocar o `src` no banco.

O que quebra se a sessão for exigida cedo demais:

- página de login ou e-mail que mostre a imagem sem cookie;
- cache compartilhado (Cloudflare ou `Cache-Control: public`) que ainda tenha o arquivo;
- URL assinada de S3 já emitida, válida por até 900 segundos (`getViewUrl`);
- app ou integração que baixe a URL sem cookie. Em `familia/` não há montagem dessa URL. Se a API devolver a URL pronta, o cliente só a abre: o cookie do site não vai no app. Esse caso precisa de teste com o JSON real antes de exigir sessão nas fotos (SEC-007). Para exercício, o consumidor localizado é a própria página web.

## Onda 1 — parar o anônimo (SEC-001 e SEC-006)

Arquivos:

- `backend/app/Infra/Services/AcessoArquivoPrivado.php` — `pastaDireta`
- `backend/app/Dominio/Escola/Controllers/ServeImagemExercicioController.php`
- `backend/app/Modulos/provas/Controllers/ServeExamQuestionImageController.php`
- `backend/bootstrap/app.php` — só se a resposta 401/403 do ramo negado precisar de cabeçalho uniforme. A lógica fica no serviço.

Mudança:

1. `pastaDireta` passa a devolver `'negado'` no final.
2. Lista explícita `'publico'`: `uploads/layout/`, `uploads/essays/proposals/`, `uploads/redacoes_temas/`.
3. `uploads/jornadas/exercicios/` e `uploads/provas/questoes/` continuam `'rota'`, para o controller decidir.
4. Os ramos que já chamam `podeLer` (redação, arquivo, mensagem, ticket, essay de aluno, caderno) permanecem.
5. `ServeImagemExercicioController::serve` exige `user_id` e `user_type` (ou `master_user_id`) antes de `getViewUrl` e antes de `readfile`. Sem sessão: 401, sem corpo do arquivo. `Cache-Control: private, no-store`.
6. O mesmo gate no `ServeExamQuestionImageController`, além do `Auth` que já existe, para a resposta da imagem ser 401 e não o HTML do login.
7. Não emitir URL assinada de S3 para quem não passou no gate.

Fora desta onda: saber se o aluno está na jornada. Qualquer sessão da escola ainda verá o enunciado. Isso é aceitável por um intervalo curto e é fechado na onda 2. Documentos e vídeos em `/uploads/documentos` e `/uploads/videos` passam a 403 na URL direta já na onda 1. Quem usa `/media/serve` com sessão continua vendo, até a onda 2 exigir o vínculo.

Dependência: nenhuma. Não muda esquema de banco nem Nginx.

Regressão:

- enunciado em tela de aluno, professor e admin logados;
- HTML antigo com `/uploads/jornadas/exercicios/`;
- HTML novo com `/media/serve?type=jornadas_exercicios` (este ainda só exige sessão, comportamento atual de `podeLer`);
- logo em `/uploads/layout/` e tema de redação;
- prova do aluno logado em `/uploads/provas/questoes/`;
- documento aberto pela URL antiga `/uploads/documentos/...` dentro de página logada: passa a 403. Se alguma view ainda aponta para essa URL direta, a onda 1 quebra o link. Antes de subir, buscar no banco não é possível daqui; no código, `TeacherJourneyController` grava a URL por `getDisplayUrl('jornadas_documentos')`, que para esse tipo não está em `$localFilesTypes`. Com armazenamento local, `getDisplayUrl` (linhas 289–293 de `MediaStorageService`) devolve `/{local_path}/{key}`, isto é `/public/uploads/documentos/...` ou o caminho configurado. Confirmar na implementação o string exato e, se a view usar a URL direta, trocar o `src` para `/media/serve?type=jornadas_documentos&key=` na mesma entrega. Sem essa troca, o documento some para todo mundo.

Risco de regressão: alto nos documentos e vídeos se a view usar a URL direta. Médio na imagem de exercício (cookie same-origin cobre o caso logado).

## Onda 2 — vínculo do recurso (SEC-005)

Arquivo principal: `AcessoArquivoPrivado::podeLer`.

Para cada tipo hoje genérico, localizar a linha na conexão da escola e reutilizar `sessaoPodeRegistro` (já distingue aluno dono, pai vinculado, professor da turma, monitor da turma, admin).

| Tipo | Onde o código já grava a chave | Quem pode, em linha com o que `sessaoPodeRegistro` já faz |
|---|---|---|
| `jornadas_documentos` | `jornadas_modulos_documentos.arquivo`, jornada em `jornadas` | aluno da jornada ou da turma; pai com filho nessa turma; professor da jornada ou da turma; admin |
| `jornadas_exercicios` | enunciado em `jornadas_modulos_exercicios` (o HTML contém a key) | o mesmo conjunto da jornada |
| `jornadas_videos` | a confirmar no `INSERT` do módulo de vídeo na Fase B, antes de escrever a query | o mesmo conjunto |
| `provas_questoes` | a confirmar no serviço que grava a questão (`ExamImageService` monta a URL na linha 380) | aluno que pode fazer ou já fez a prova; professor da prova; admin. Pai só se a tela de resultado do responsável já mostra a imagem |
| `apostilas` | a confirmar no módulo `apostilas` antes da query | aluno da turma de publicação; professor; admin |
| `notifications` | a confirmar em `NotificationController` (a URL é montada na linha 404) | só o destinatário, o admin e o master |

Sem linha no banco: negar. Admin e master continuam como hoje (linhas 20–22).

Não usar `?tenant=` para ler outra escola. Esse bloqueio já existe e permanece.

Teste dinâmico desta onda só em homologação, com as contas fictícias de `especificacoes/auditoria-acesso-2026-11-18/plano_testes_homologacao.md`. Não usar aluno real.

Regressão: aluno que vê o exercício, professor que pré-visualiza, admin que abre a prova, responsável que abre documento compartilhado. Um join errado esconde conteúdo legítimo. Por isso a onda 2 entra depois dos testes de leitura do próprio recurso, não junto com a onda 1.

## Onda 3 — recuperação e primeiro acesso (SEC-002 e COMP-01)

Arquivo: `backend/app/Painel/Auth/Controllers/AuthController.php`. Views só se o JSON mudar de forma: `recuperar-senha.php`, `recuperar-senha-professor.php`, `recuperar-aluno.php`, `primeiro-acesso.php`.

Limite, nos mesmos números já configurados para o login, para não inventar outro teto:

- por identificador normalizado (e-mail em minúsculas, ou nickname em minúsculas): 5 pedidos na janela `lockout_duration` (900 s);
- por IP (`REMOTE_ADDR`, o mesmo do login): 50 pedidos na mesma janela (`max_login_attempts_ip`).

Os dois contadores são independentes do contador de senha errada. Um laboratório com um IP só não esgota o limite por causa de outro aluno, até 50 pedidos no intervalo. Um único e-mail não recebe mais de 5 mensagens no intervalo. Se a escola precisar de outro teto, a variável fica ao lado das que já existem em `config/app.php` (`security`), com esses valores como padrão. Não criar chave nova no `.env` sem espelhar em `.env.example`; se der para só ler as chaves de login, melhor.

Resposta única para e-mail existente ou não: a frase que já é usada quando o e-mail não existe. O envio continua ocorrendo quando o cadastro existe. O JSON deixa de distinguir.

Pergunta do aluno: o corpo e o status da primeira resposta ficam iguais para nickname inexistente e existente. A pergunta só aparece se a view seguinte ler a sessão `recuperacao_aluno_id`. Ajustar `recuperar-aluno.php` na mesma entrega. O reset passa a contar erro de resposta no mesmo limite por nickname.

Primeiro acesso: a mensagem de nome inexistente, nome duplicado e nome com acesso já criado deixa de ser três textos diferentes na primeira resposta. Homônimo é decisão de produto: em vez de devolver a lista de nomes, pedir um segundo dado que o aluno já tenha (RA ou data de nascimento) antes de escolher o cadastro. Isso muda o formulário. Não fazer na mesma entrega do limite se o produto ainda não escolher o segundo dado. O limite por IP e por nome normalizado entra mesmo assim.

Log: em falha de envio, registrar tipo de fluxo e se a exceção ocorreu. Não registrar `reset_url`, token, e-mail completo nem nome.

CSRF permanece. Não há mudança de contrato para o app: `familia/` não chama esses POST.

Regressão: quem espera a frase "enviadas para seu email"; aluno que recupera a senha pela pergunta; primeiro acesso de homônimo, se a lista de nomes for retirada na mesma onda. O limite de 50 por IP preserva o laboratório no patamar que o login já aceita.

## Onda 4 — perfil no middleware (SEC-008)

Só depois dos testes de perfil descritos em `04-testes-seguranca.md`, ainda sem executar contra produção.

`AuthMiddleware` passa a conhecer o prefixo:

- `/admin` → `admin` ou `admin_escola` (a matriz fina que o middleware já aplica continua);
- `/professor` → `professor`;
- `/pais` → `pai`;
- `/monitor` → `monitor`;
- `/dashboard` e rotas de aluno já cobertas por `StudentController` → `aluno`.

Exceções obrigatórias, lidas do código atual, para não serem pegas pelo prefixo:

- `/uploads/provas/questoes/` (qualquer sessão; o dono fica na onda 2);
- `/simulados`;
- `/notifications` se a rota for compartilhada;
- `POST /aluno/provas/log-evento`, que o `ExamController` trata com sessão ou token de prova.

A lista de exceções sai de uma tabela no middleware, com teste para cada item. Sem essa tabela, a onda não sobe.

Regressão: alta. É a mudança com maior chance de derrubar uma tela que hoje funciona com mais de um perfil (`ExamController` aceita aluno, professor e admin).

## Onda 5 — cabeçalhos (SEC-003)

Sem editar Nginx.

Em `backend/bootstrap/app.php`, perto do HSTS (linha 215), nas respostas HTML:

- `Content-Security-Policy-Report-Only` com `default-src 'self'`, `script-src` e `style-src` incluindo `'unsafe-inline'` e as origens já encontradas: `cdnjs.cloudflare.com`, `unpkg.com`, `cdn.jsdelivr.net`, `fonts.googleapis.com`, `fonts.gstatic.com`;
- `img-src 'self' data: blob:` e o host do S3 somente se `MEDIA_STORAGE=s3` estiver ativo naquela instalação;
- `frame-src` para o host de Jitsi usado pela escola, senão o Report-Only vai encher de aula online;
- `Referrer-Policy: strict-origin-when-cross-origin`;
- `Permissions-Policy` sem `camera` e sem `microphone`. Pode restringir `payment`, `usb` e `geolocation` depois de uma busca que confirme que nenhuma tela pede geolocalização. Essa busca faz parte da onda, não está fechada.

Modo Report-Only não bloqueia. A onda seguinte, fora deste plano, só promove a CSP obrigatória quando o relatório de uma semana em homologação não mostrar origem nova.

CSS estático continua sem esses cabeçalhos até uma mudança de Nginx, que esta etapa não faz.

## Onda 6 — documentação e fotos (SEC-004 e SEC-007)

SEC-004: alterar só o texto de `EspecificacaoOpenApiService::guia` para não orientar o uso de senha real na página pública. Manter os 26 caminhos e o acesso anônimo até decisão explícita de restringir.

SEC-007: inventário, sem mudar `PUBLIC_TYPES`.

1. Buscar `type=professores`, `type=avatars`, `avatar_url` e `photo_url` em views, JSON da API v1 e da API legada.
2. Separar o que aparece antes do login (logo é `layout`, não avatar) do que aparece no painel.
3. Se a decisão for tornar a foto privada: exigir sessão da mesma escola, manter a URL `/media/serve`, e não colocar avatar em `PUBLIC_TYPES`. O app que recebe URL absoluta precisa continuar recebendo uma URL que ele consegue abrir (cookie não segue no app). Alternativa compatível: a API passar a devolver a imagem por um endpoint com o JWT que o app já envia, e o site continuar com cookie. Isso é contrato novo e só entra com a lista de consumidores fechada.

## Onda 7 — Redis e S3 (COMP-02 e COMP-03)

COMP-03: se `RedisCache::connect` falhar, `entrarComo` redireciona para o login. Não cria a sessão. Regressão: "entrar como" para de funcionar durante uma queda do Redis. É o comportamento pedido.

COMP-02: remover o segundo laço que zera `tenant_prefix` (linhas 145–158), depois de conferir em homologação se ainda existe objeto antigo sem pasta. Se existir, a lista é finita e explícita, não um fallback geral. Sem essa conferência, a onda não sobe: o risco é 404 em logo antigo, não exposição nova.

## Fora de escopo até nova autorização

- Nginx, proxy e Cloudflare.
- Apagar ou mover os canários no host.
- Migrations.
- Troca de senha real, envio de e-mail real, login em produção.
- Restringir `/api/docs`.
- `camera=()` na Permissions-Policy.
- Reescrever todas as URLs antigas no banco.
