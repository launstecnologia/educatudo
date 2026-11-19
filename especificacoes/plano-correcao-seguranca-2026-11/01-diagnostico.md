# Diagnóstico de segurança — Fase A

**Data:** 18/11/2026  
**Escopo:** código deste repositório. Nenhum arquivo de aplicação, Nginx, autenticação ou banco foi alterado. Nenhum pedido foi enviado a `https://educa.educatudo.com` nesta fase. Nenhum e-mail foi disparado e nenhum login foi feito.

A evidência HTTP do PNG de exercício (status 200, 67 bytes, sem sessão) é a informada pela auditoria pública. O arquivo local correspondente existe e tem 67 bytes: `backend/public/uploads/jornadas/exercicios/auditoria-canario-e7c4a91f.png`. O código explica esse 200. Esta fase não repetiu o pedido.

MinIO não aparece no código. O armazenamento de mídia é local ou AWS S3 (`MEDIA_STORAGE` em `backend/config/app.php`, linhas 176–192).

## Tabela consolidada

| ID | Achado | Evidência | Arquivo/função | Correção proposta | Prioridade | Status |
|---|---|---|---|---|---|---|
| SEC-001 | Imagem de exercício sai sem sessão | Auditoria: `GET /uploads/jornadas/exercicios/auditoria-canario-e7c4a91f.png` → 200, PNG de 67 bytes. Código não exige sessão. Arquivo local tem 67 bytes. | `ServeImagemExercicioController::serve` (linhas 19–51); rota em `config/routes/public.php` linha 66 | Exigir sessão da escola antes de ler ou redirecionar. Manter a URL. Cache privado. Dono/turma na etapa SEC-005. | Alta | Comprovado em código e na auditoria HTTP |
| SEC-002 | Recuperação de senha e primeiro acesso sem limite | Não há chamada de limite em `enviarRecuperacao`, `enviarRecuperacaoProfessor`, `recuperarSenhaAlunoPergunta`, `recuperarSenhaAlunoReset` nem `validarPrimeiroAcessoResult`. Login web tem limite. | `AuthController` linhas 283–360, 533–605, 820–830, 942–973, 1420–1537, 1875–1970 | Reusar a janela do login: por IP e por identificador, resposta única, log sem token. | Média | Comprovado em código. POST não foi executado. |
| SEC-003 | CSP e Permissions-Policy ausentes | Não há `Content-Security-Policy` nem `Permissions-Policy` no PHP nem nos `docker/nginx/*.conf`. `Referrer-Policy` existe no Nginx do repositório e não foi incluído no proxy descrito no relatório ZAP de 18/11. | `backend/bootstrap/app.php` linha 215 (só HSTS); `docker/nginx/vps-prod-http.conf` linhas 17–19 | CSP Report-Only pelo PHP, sem mexer no Nginx. Referrer no PHP para páginas. Permissions-Policy sem desligar câmera e microfone. | Média/baixa | Ausência comprovada no repositório. Resposta atual da borda não foi remediada aqui. |
| SEC-004 | OpenAPI público com 26 caminhos | Rotas sem middleware. `EspecificacaoOpenApiService::operacoes` declara 26 operações. | `config/routes/public.php` linhas 42–43; `SwaggerController`; `EspecificacaoOpenApiService.php` | Manter público. Não restringir nesta etapa. Revisar o texto que orienta usar CPF real. | Revisão | Exposição intencional no código. Segredo não localizado no spec. |
| SEC-005 | Sessão basta para vários tipos privados | `podeLer` devolve verdadeiro para todo usuário logado quando o tipo não é chat, redação, ticket, arquivo ou mensagem. | `AcessoArquivoPrivado::podeLer` linhas 16–26; `MediaServeController::serve` linhas 82–90 | Resolver o registro na escola e negar sem vínculo. Sem registro, negar. | Alta | Comprovado em código. Teste com duas contas não foi feito. |
| SEC-006 | Pasta de upload desconhecida é pública | `pastaDireta` termina em `'publico'`. O bootstrap envia o arquivo se ele existir em `public/uploads/`. | `AcessoArquivoPrivado::pastaDireta` linhas 47–82; `bootstrap/app.php` linhas 745–782 | Padrão `'negado'`. Lista explícita do que é público. | Alta | Comprovado em código. Pedido a uma pasta desconhecida não foi feito. |
| SEC-007 | Avatar e foto de professor sem login | `avatars` e `professores` estão em `PUBLIC_TYPES`. | `MediaServeController` linhas 41–47 e 82–86 | Não mudar agora. Confirmar política e todos os `<img>` antes de exigir sessão. | Média potencial | Público no código. Não há chave fictícia exercitada. |
| SEC-008 | Middleware Auth não compara o perfil com a URL | `handle` só exige sessão. O perfil é rechecado no construtor de parte dos controllers. | `AuthMiddleware::handle` linhas 30–61; `config/routes.php` linhas 18–30 | Recusa por prefixo só depois de uma lista de exceções. Testes por perfil antes de ligar. | Alta potencial | Lacuna estrutural comprovada. Vazamento de um método específico não foi fechado nesta leitura. |
| COMP-01 | Log de recuperação grava a URL com o token | `reset_url` entra no contexto do log quando o envio falha. | `AuthController::enviarRecuperacao` linhas 1504–1512; o mesmo em `enviarRecuperacaoProfessor` linhas 1937–1948 | Logar só o fato do envio e o tipo de usuário. Sem e-mail completo, nome e token. | Média | Comprovado em código. |
| COMP-02 | Fallback S3 sem prefixo da escola | Depois da chave com tenant, a mesma chave é buscada com `tenant_prefix` falso. | `MediaServeController::serve` linhas 145–158 | Desligar o fallback para objeto novo. Lista fechada só se ainda houver chave antiga. | Suspeita | Código existe. Bucket compartilhado não foi confirmado. |
| COMP-03 | Token "entrar como" reutilizável se o Redis cair | `RedisCache::get` devolve `null` se o Redis não conecta. O fluxo segue. | `AuthController::entrarComo` linhas 1221–1238; `RedisCache::get` linhas 131–145 | Recusar o token se o Redis não responder. | Baixa | Comprovado em código. |
| COMP-04 | `/api/v1/status` descreve a escola | Slug, nome, logo, cor e se a API facial está configurada. CORS `*`. | `ApiStatusController::index` linhas 12–45 | Manter. Não é dado de aluno. | Informativo | Comprovado em código. |
| COMP-05 | `/storage/images/` sai pelo Nginx, sem PHP | Alias direto para `storage/images/`. | `docker/nginx/vps-prod-http.conf` linhas 63–66; bootstrap linhas 814–835 | Fora desta etapa (não alterar Nginx). Sem evidência de arquivo de aluno nessa pasta. | Informativo | Caminho público no repositório. Conteúdo real não foi listado. |

## Como o arquivo chega ao navegador

1. O Nginx do repositório (`docker/nginx/vps-prod-http.conf`, linhas 43–48) reescreve `/uploads/` e `/public/uploads/` para `index.php`. O prefixo `^~` impede que o `location` de estáticos (linhas 73–77) entregue o PNG direto.
2. `backend/bootstrap/app.php` (linhas 745–782) chama `AcessoArquivoPrivado::pastaDireta`.
   - `'rota'`: o bootstrap não lê o arquivo. O roteador segue.
   - `'publico'` ou `'privado'`: o bootstrap lê `public/uploads/...` se a extensão não for de script e o `realpath` continuar dentro de `public/uploads`.
   - `'negado'`: HTTP 403.
3. A rota nomeada, quando existe, cai no controller.

O proxy da frente (`/opt/proxy/nginx/conf.d/educatudo.conf`, citado no relatório ZAP) não está neste repositório. Esta fase não o releu.

## Onde cada tipo fica

Configuração em `backend/config/app.php`, `media.local_paths` (linhas 181–192). Prefixos completos em `MediaStorageService::$typePrefixes` (linhas 24–48).

| Tipo | Disco quando `MEDIA_STORAGE=local` | Quem entrega | Sessão hoje |
|---|---|---|---|
| `jornadas_exercicios` | `public/uploads/jornadas/exercicios` | Rota pública `ServeImagemExercicioController` (`pastaDireta` devolve `'rota'`) | Não |
| `provas_questoes` | `storage/provas/questoes` | `/media/serve` e, com sessão, `ServeExamQuestionImageController` | Sessão em `/media/serve`; a rota `/uploads/provas/questoes/{filename}` está dentro do grupo `Auth` (`provas/routes.php` linha 163, carregada por `config/routes.php` linhas 18–29) |
| `jornadas_documentos` | `public/uploads/documentos` | `/media/serve` exige sessão. URL direta `/uploads/documentos/...` cai no padrão `'publico'` | Sessão só em `/media/serve` |
| `jornadas_videos` | `public/uploads/videos` | Igual aos documentos | Igual |
| `apostilas` | `public/uploads/apostilas` | Igual | Igual |
| `redacoes`, `arquivos`, `mensagens`, `tickets` | sob `public/uploads/` | `pastaDireta` chama `podeLer` | Dono ou registro |
| `chat` | `storage/chat` | `/media/serve` e `/storage/chat/` no bootstrap (linhas 784–812) | Dono ou registro |
| `layout`, `avatars`, `professores`, `notifications` | `storage/files/{slug}/...` (`$localFilesTypes`, linha 18) | `/media/serve` | `layout`, `avatars` e `professores` sem sessão. `notifications` exige sessão e cai no `podeLer` genérico |
| `essays_proposals` | `public/uploads/essays/proposals` | `/media/serve` público e URL direta pública | Não |
| `dashboard_sliders` | `storage/files/{slug}/dashboard/sliders` | `/media/serve` público | Não |

`simulados_questoes` e `simulados_alternativas` existem em `TYPE_PREFIXES` e não entram em `$allowedTypes` de `MediaServeController` (linhas 60–64). `/media/serve` responde 400 para esses tipos.

Com `MEDIA_STORAGE=s3`, `ServeImagemExercicioController` (linhas 27–32) redireciona para a URL assinada de `MediaStorageService::getViewUrl` (linhas 242–263, validade padrão 900 segundos) antes de qualquer sessão. Isso só ocorre quando S3 está ligado. O valor de produção não foi lido (`.env` não é documentação).

## Inventário das rotas públicas ligadas a este plano

A lista completa já levantada está em `especificacoes/auditoria-publica-2026-11-18/rotas_publicas_auditoria.csv`. Abaixo, só as rotas deste plano. Middleware "nenhum" significa que `config/routes/public.php` não envolve a rota em `Auth`.

| Rota | Controller | Middleware | Perfil | Recurso | Risco |
|---|---|---|---|---|---|
| `GET /uploads/jornadas/exercicios/{filename}` | `ServeImagemExercicioController::serve` | nenhum | anônimo | imagem de enunciado | SEC-001 |
| `GET /media/serve` | `MediaServeController::serve` | nenhum no roteador; sessão conforme `PUBLIC_TYPES` | anônimo nos tipos públicos; qualquer sessão nos demais | arquivo local ou S3 | SEC-005, SEC-007 |
| `GET /uploads/provas/questoes/{filename}` | `ServeExamQuestionImageController::serve` | `Auth` (grupo de `config/routes.php`) | qualquer sessão | imagem de questão | SEC-005. Anônimo não passa do middleware. |
| `GET /uploads/{pasta-não-listada}/...` | não há controller; `bootstrap/app.php` | nenhum | anônimo se o arquivo existir | o que estiver em `public/uploads/` | SEC-006 |
| `POST /enviar-recuperacao` | `AuthController::enviarRecuperacao` | nenhum; CSRF no método | anônimo | e-mail de redefinição de admin | SEC-002, COMP-01 |
| `POST /professor/enviar-recuperacao` | `AuthController::enviarRecuperacaoProfessor` | nenhum; CSRF | anônimo | e-mail de redefinição de professor | SEC-002, COMP-01 |
| `POST /aluno/recuperar-senha/pergunta` | `AuthController::recuperarSenhaAlunoPergunta` | nenhum; CSRF | anônimo | pergunta de segurança | SEC-002 |
| `POST /aluno/recuperar-senha/reset` | `AuthController::recuperarSenhaAlunoReset` | nenhum; CSRF e sessão `recuperacao_aluno_id` | quem passou da pergunta | senha do aluno | SEC-002 |
| `POST /primeiro-acesso` e `POST /primeiro-acesso/validar` | `AuthController::primeiroAcesso` / `validarPrimeiroAcesso` | nenhum; CSRF | anônimo | existência e nome de aluno | SEC-002 |
| `GET /api/docs` e `GET /api/openapi.json` | `SwaggerController` | nenhum | anônimo | contrato da API do responsável | SEC-004 |
| `GET /api/v1/status` | `ApiStatusController::index` | nenhum | anônimo | nome, slug, logo, cor, status da API facial | COMP-04 |
| `GET /auth/entrar-como` | `AuthController::entrarComo` | nenhum; token HMAC | o usuário do token | sessão | COMP-03 |

Formulários que só exibem tela, sem efeito: `GET /recuperar-senha`, `GET /professor/recuperar-senha`, `GET /aluno/recuperar-senha`, `GET /primeiro-acesso`.

## SEC-001 — imagem de exercício sem sessão

`ServeImagemExercicioController::serve` (`backend/app/Dominio/Escola/Controllers/ServeImagemExercicioController.php`, linhas 19–51):

- rejeita vazio, `..` e caracteres fora de `a-zA-Z0-9_.-/`;
- não lê `$_SESSION`;
- se S3 estiver ativo, responde `Location` para URL assinada;
- senão lê o arquivo e envia `Cache-Control: public, max-age=86400`.

A classe pedida no `use` é `App\Services\MediaStorageService`, mapeada em `backend/app/Compat/services_map.php` linha 146 para `App\Infra\Services\MediaStorageService`.

`pastaDireta` (linhas 49–51) devolve `'rota'` para `uploads/jornadas/exercicios/`. Por isso o bootstrap não aplica `podeLer`. A rota em `public.php` linha 66 está fora do `Auth`.

Quem grava a URL nova:

- `JourneyController::buildStableJourneyExerciseMediaUrl` (linhas 3759–3772) monta `/media/serve?type=jornadas_exercicios&key=`.
- `TeacherJourneyController::buildStableJourneyExerciseMediaUrl` (linhas 4573–4586) faz o mesmo.
- `MonitorController` (linhas 647–660) ainda reescreve caminho antigo para `/uploads/jornadas/exercicios/`.

O enunciado fica no HTML do exercício. A URL antiga continua dentro desse HTML. Por isso a rota pública não pode ser apagada de uma vez.

Não há referência a `/uploads/jornadas/exercicios` nem a `/media/serve` em `familia/` (busca em `*.dart`). O app dos pais recebe os campos da API; não monta essa URL no cliente.

Imagem de questão (`ServeExamQuestionImageController::serve`, linhas 19–51) repete a ausência de dono, mas a rota está dentro do `Auth`. Anônimo não chega no `readfile`. Qualquer sessão chega.

## SEC-002 — limite e enumeração

Limite que já existe e pode ser reaproveitado:

| Fluxo | Onde | Regra |
|---|---|---|
| Login web por IP | `AuthController::checkLoginRateLimit` linhas 942–973, chamado na linha 821 | 50 falhas em 900 s (`config/app.php` linhas 117–120). Arquivo `storage/logs/login_rate_limit.json`. Usa `REMOTE_ADDR` (linha 820), não o primeiro `X-Forwarded-For`. |
| Login web por identificador | `AuthManager` | 5 falhas; aluno em 300 s por nickname, demais perfis em 900 s |
| `POST /api/auth/login` | `API\Controllers\AuthController::applyRateLimit` linhas 106–127 | 10 em 60 s por IP, arquivo em `sys_get_temp_dir` |
| `POST /api/v1/auth/login` | `MobileAuthController::applyRateLimit` linhas 190–230 | 5 em 300 s por tenant, CPF e `REMOTE_ADDR` |

`getIpAddress` (linhas 1122–1131) aceita `HTTP_CLIENT_IP` e o primeiro endereço de `X-Forwarded-For`. O login web não usa esse método. A recuperação também não deve usá-lo: o primeiro salto pode ser forjado se o proxy não apagar o cabeçalho. O IP do limite novo deve ser o mesmo `REMOTE_ADDR` do login, que atrás da Cloudflare é o IP que o proxy de origem enxerga. Confirmar na implantação se esse proxy grava o IP do visitante em `REMOTE_ADDR`. Isso não foi lido aqui porque o proxy não está no repositório.

Enumeração já visível no código, sem POST executado:

- E-mail encontrado e enviado: `"Instruções de recuperação enviadas para seu email"` (linhas 1519 e 1953). E-mail ausente: `"Se o email estiver cadastrado, você receberá instruções de recuperação"` (linhas 1468 e 1901).
- Nickname de aluno com pergunta: o JSON traz `pergunta` (linha 569). Sem nickname ou sem pergunta: mensagem genérica, sem `pergunta` (linhas 549–565).
- `recuperarSenhaAlunoReset` (linhas 572–605) não conta tentativas da resposta.
- Primeiro acesso (`validarPrimeiroAcessoResult`, linhas 345–360): "não encontrado", "acesso já criado" ou redirecionamento para criar a senha. Vários homônimos devolvem `id` e `nome` (linhas 335–340). A view escapa o nome com `htmlspecialchars` (já descrito na auditoria). Não é XSS.

O comentário em `recuperarSenhaAlunoPergunta` (linhas 529–531) já pede para não revelar nickname inexistente. A resposta ainda muda quando a pergunta existe.

Consumidores desses POST: as views `backend/app/Views/auth/recuperar-senha.php`, `recuperar-senha-professor.php` e `recuperar-aluno.php`. Não há chamada correspondente em `familia/`.

## SEC-003 — cabeçalhos

Presentes no Nginx do repositório (`vps-prod-http.conf` linhas 17–19): `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy: strict-origin-when-cross-origin`. O PHP envia HSTS quando o pedido é HTTPS (`bootstrap/app.php` linha 215).

Ausentes no PHP e no Nginx do repositório: `Content-Security-Policy`, `Permissions-Policy`.

Origens externas confirmadas nas views (amostra, não lista exaustiva de cada tela):

- `cdnjs.cloudflare.com` — Font Awesome, pdf.js
- `unpkg.com` — HTMX em `layouts/student.php`
- `cdn.jsdelivr.net` — MathJax, Chart.js, OpenDyslexic, Mermaid, Marked
- `fonts.googleapis.com` e `fonts.gstatic.com` — fontes
- Jitsi aparece como plataforma de aula em `StudentOnlineClassController` (linha 264). O domínio do iframe depende do cadastro da aula e não foi fixado num único host nesta leitura.

Há script e estilo inline (login, prova, fontes). Uma CSP com `default-src 'self'` quebra essas telas. A proposta é Report-Only, com `'unsafe-inline'` enquanto o relatório não for limpo.

`Permissions-Policy` com `camera=()` e `microphone=()` atingiria aula online e reconhecimento facial. A proposta não desliga esses dois.

`Referrer-Policy` no PHP cobre resposta que passa pelo PHP. CSS estático continua dependendo do Nginx ou do proxy. Esta etapa não altera Nginx.

## SEC-004 — documentação da API

`GET /api/docs` e `GET /api/openapi.json` estão em `public.php` linhas 42–43, sem middleware. `SwaggerController` (linhas 15–29) devolve o HTML e o JSON gerado por `EspecificacaoOpenApiService`.

26 caminhos, um método cada:

1. `POST /api/autenticacao/entrar`
2. `POST /api/autenticacao/renovar`
3. `POST /api/autenticacao/sair`
4. `GET /api/eu`
5. `GET /api/alunos`
6. `GET /api/alunos/{id}/painel`
7. `GET /api/alunos/{id}/provas`
8. `GET /api/alunos/{id}/exercicios`
9. `GET /api/alunos/{id}/jornadas`
10. `GET /api/alunos/{id}/planos-de-aula`
11. `GET /api/alunos/{id}/planos-de-aula/{planId}`
12. `GET /api/alunos/{id}/jornadas-de-redacao`
13. `GET /api/alunos/{id}/redacoes`
14. `GET /api/alunos/{id}/avisos`
15. `GET /api/alunos/{id}/boletim`
16. `GET /api/alunos/{id}/acessos`
17. `GET /api/alunos/{id}/financeiro`
18. `GET /api/alunos/{id}/financeiro/faturas/{origem}/{faturaId}/pagamento`
19. `GET /api/alunos/{id}/comunicados`
20. `GET /api/alunos/{id}/comunicados/{communicationId}`
21. `POST /api/alunos/{id}/comunicados/{communicationId}/lido`
22. `POST /api/alunos/{id}/comunicados/{communicationId}/respostas`
23. `GET /api/alunos/{id}/calendario`
24. `POST /api/alunos/{id}/calendario/{eventId}/lido`
25. `GET /api/notificacoes`
26. `POST /api/notificacoes/{id}/lida`

O esquema `bearerAuth` está em `components.securitySchemes` (linhas 37–44). O guia (linhas 51–66) diz que a API lê o banco da escola do endereço aberto e orienta usar CPF e senha de um responsável cadastrado. Não há segredo, senha de exemplo nem token fixo no texto lido. Há descrição de nota, boletim, acesso à escola e pagamento.

A exposição é intencional: a rota está no arquivo público e o comentário da classe chama isso de documentação da API do responsável. Restringir agora quebraria quem integra por essa página. Recomendação: manter pública e enxugar o parágrafo que manda usar credencial real. Uma versão reduzida ou interna fica como decisão de produto, não como correção automática.

## SEC-005 — propriedade em `/media/serve`

`podeLer` (linhas 16–26):

- admin, `admin_escola` ou `master_user_id`: verdadeiro;
- tipo fora de `chat`, `redacoes`, `tickets`, `arquivos`, `mensagens`: verdadeiro para qualquer `user_id` com `user_type`;
- esses cinco tipos: consulta o registro e o vínculo.

Tipos que passam na segunda regra e mesmo assim são privados em `MediaServeController` (não estão em `PUBLIC_TYPES`): `provas_questoes`, `jornadas_exercicios`, `jornadas_documentos`, `jornadas_videos`, `apostilas`, `notifications`.

Anônimo recebe 401 (linhas 83–85). Aluno da mesma escola, com a chave de outro recurso, recebe o arquivo porque `podeLer` não consulta o dono. `?tenant=` de outra escola é recusado para esses tipos (linhas 102–107), salvo sessão Master.

Registro já existente no código para documento de jornada: tabela `jornadas_modulos_documentos`, coluna usada como `arquivo`, gravada em `TeacherJourneyController` por volta das linhas 4132–4159, com a jornada em `jornadas` e o módulo em `jornadas_modulos`. A checagem nova deve localizar essa linha (e as equivalentes de vídeo, exercício, prova, apostila e notificação) antes de liberar. A lista fechada de tabelas de vídeo, prova, apostila e notificação fica para a Fase B, lendo o `INSERT` de cada uma. Não foi assumida aqui.

`/media/serve` também é o endereço que o HTML novo de exercício usa. Fechar SEC-001 na rota antiga e deixar `/media/serve?type=jornadas_exercicios` só com "qualquer sessão" mantém a imagem acessível a qualquer usuário logado da escola.

## SEC-006 — padrão público

`pastaDireta` cobre caderno (`negado`), exercício e questão (`rota`), redação, arquivo, mensagem, essay de aluno e ticket (`podeLer`). O que sobra retorna `'publico'` (linha 82). O comentário do bootstrap (linhas 745–747) descreve esse resto como público.

Consequência no código: um arquivo em `public/uploads/documentos/`, `public/uploads/videos/` ou `public/uploads/apostilas/` é enviado sem sessão, com `Cache-Control: public`, se existir no disco do host. A mesma escola que passa por `/media/serve` exige sessão. São duas portas para o mesmo diretório.

Isso não foi reproduzido com um arquivo novo nesta fase. O canário de exercício não cai nesse ramo: ele cai em `'rota'`.

Lista que deve continuar explícita e pública, porque o código já trata como marca ou material de tema:

- `uploads/layout/`
- `uploads/essays/proposals/`
- `uploads/redacoes_temas/` (hoje fica de fora do ramo privado de `uploads/redacoes/`, linha 56)

Qualquer outra pasta passa a `'negado'` até entrar na lista.

## SEC-007 — avatares e fotos

`PUBLIC_TYPES` (linhas 41–47) inclui `layout`, `dashboard_sliders`, `professores`, `avatars`, `essays_proposals`. O comentário da classe (linhas 7–8) chama logo, capa e avatar de tipos públicos.

Uso confirmado:

- `layouts/professor.php` e `professor_sidebar.php` montam `/media/serve?type=professores&key=`.
- `TeacherController` por volta da linha 2141 grava a mesma forma.
- `CamposDocumentoEditorTest.php` usa `type=avatars` em documento.
- `StudentPhotoHelper` reconhece `/media/serve`.

Esses arquivos ficam em `storage/files/{slug}/`, não em `public/uploads/`. A URL direta de upload não os entrega. Quem souber `type` e `key` recebe a imagem sem sessão.

Não há, neste repositório, uma política escrita que diga se a foto do professor é pública. Mudar o tipo para privado quebra `<img>` em tela de login e documento se a página não enviar cookie. O app em `familia/` não monta essa URL; se a API devolver a URL pronta, o app só a exibe. Por isso a mudança espera o levantamento dos JSON que devolvem `photo_url` / `avatar_url`.

## SEC-008 — perfil no middleware

`AuthMiddleware::handle` (linhas 30–61) exige `isLoggedIn()`. Não compara `user_type` com `/admin`, `/professor`, `/pais`, `/monitor` ou `/dashboard`.

Checagem que existe no construtor:

| Controller | Linhas | O que exige |
|---|---|---|
| `AdminBaseController` | 31–35 | `admin` ou `admin_escola` |
| `StudentController` | 33–37 | `aluno` |
| `TeacherController` | 40–43 | `professor` |
| `ParentController` | 34–38 | `pai` |
| `MonitorController` | 34–37 | `monitor` |
| `DocumentoFamiliaAdminController` | 18–24 | `admin` ou `admin_escola` |
| `ExamController` | 73–90 | logado e um de `professor`, `admin`, `admin_escola`, `aluno` |
| `JourneyController` | 35–38 | só logado; vários métodos repetem `tipo === 'aluno'` |

Um controller novo dentro do grupo `Auth` que não repetir o `tipo` atende qualquer sessão. Esta leitura não abriu os 1.910 métodos do inventário anterior. Não há, nesta fase, um método nomeado em que aluno leia dado de admin. O risco é estrutural.

Um bloqueio cego por prefixo quebra rotas legítimas sem prefixo de perfil: `/uploads/provas/questoes/`, `/simulados`, `/notifications`. A correção precisa de uma lista de exceções, não de um `if` no prefixo.

## Complementos que não são vulnerabilidade fechada

- Isolamento de escola: a conexão PDO é a da escola do host. `?tenant=` em tipo privado é recusado fora de sessão Master. Troca de cookie entre hosts não foi testada.
- Travessia de caminho: `..` é recusado no controller de exercício, em `/media/serve` (linha 69) e em `MediaStorageService::normalizeKey` (linhas 634–640). O bootstrap exige `realpath` dentro de `public/uploads`. Barra no nome continua permitida e limita a leitura ao diretório daquele tipo.
- SQL dos fluxos lidos (primeiro acesso, recuperação, `podeLer`) usa parâmetro nomeado. Não há concatenação nesses trechos. Isso não estende a conclusão ao restante das rotas públicas.
- `/api/v1/status` não devolve aluno, nota nem segredo. Informa se a API facial está configurada.
- Cache: tipo público e o ramo `'publico'` do bootstrap usam `public, max-age=86400`. Tipo privado em `/media/serve` usa `private, max-age=3600` (linha 192). Imagem de exercício, hoje pública, pode permanecer em cache compartilhado mesmo depois da correção, até a cópia expirar ou ser limpa.

## O que esta fase não fez

- Não pediu a URL do canário de novo.
- Não copiou arquivo para o host.
- Não enviou POST de recuperação, primeiro acesso ou login.
- Não abriu os 2.256 métodos do inventário de autorização.
- Não leu `.env` nem o Nginx de `/opt/proxy`.
