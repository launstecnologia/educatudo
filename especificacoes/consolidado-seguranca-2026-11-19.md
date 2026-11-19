# EducaTudo — consolidado de segurança (19/11/2026)

Fontes: tabela da auditoria de 19/11, auditoria Python `20261119T032856Z` (51 pedidos em `https://educa.educatudo.com`) e o ZAP do mesmo dia. Cada linha abaixo foi conferida no código deste repositório.

## Está ok

| Ponto | O que o teste viu | Onde o código fecha |
|---|---|---|
| Imagem de exercício sem login | Canário `/uploads/jornadas/exercicios/auditoria-canario-e7c4a91f.png`: antes 200, depois **401** na origem e na Cloudflare | `ServeImagemExercicioController` exige sessão e `AcessoArquivoPrivado::podeLer`. Sem sessão o bootstrap também responde 401 |
| `/public/uploads/` | 404 no teste | `pastaDireta` trata `uploads/...`; pasta desconhecida responde negado |
| `/media/serve` de prova sem login | 401 | `MediaServeController` não entrega tipo privado sem sessão |
| API de aluno e de responsável | `/api/v1/students` e `/api/parents/children`: 401 | JWT nas rotas; o teste anônimo para na autenticação |
| Páginas públicas (`/`, login, recuperação, primeiro acesso, guia) | 200, sem vazar dado de aluno | Rotas públicas de propósito. Marcador inerte em nome, tipo e etapa não virou script |
| Arquivos sensíveis (18 caminhos) | HEAD 404 em `.env`, `.git`, `backup.sql`, `dump.sql`, `phpinfo.php`, `config/database.php`, logs | Nginx: `deny all` para `\.env`, `\.git`, `storage`, `config` e para qualquer caminho que começa com ponto |
| Nginx e Docker | Container nas duas redes, sem porta publicada no teste | Configuração do repositório reescreve `/uploads/` para o `index.php` |
| Recuperação de senha | O POST que envia e-mail não foi retestado, de propósito | Limite de tentativas, a mesma frase para e-mail existente e inexistente, log sem o link de redefinição |
| Entrar como | Fora do scan anônimo | O token com `jti` só vale uma vez (`SET NX`). Se o Redis não gravar, o token é recusado |

Os seis cabeçalhos da página estão na home em produção: `nosniff`, `X-Frame-Options`, HSTS, `Referrer-Policy`, `Permissions-Policy` e `Content-Security-Policy-Report-Only`. Os três primeiros saem do proxy da Oracle. Os três últimos saem do PHP (`backend/bootstrap/app.php`).

## O scanner ainda marca, e não é falha

| Alerta | Por que não muda nada |
|---|---|
| CSP “ausente” (ZAP, médio) | A política que bloqueia quebraria Font Awesome, HTMX, MathJax, Google Fonts e o Jitsi. O que está no ar é relatório (`Content-Security-Policy-Report-Only`). O próprio ZAP registra esse cabeçalho como informativo |
| `X-Frame-Options` duas vezes e HSTS duas vezes | O proxy e o PHP mandam o mesmo valor. Os dois são `SAMEORIGIN` e `max-age=31536000; includeSubDomains`. Não abre clique em quadro nem tira o HTTPS |
| COOP, COEP e CORP ausentes (ZAP, baixo) | Ligar esses três corta script, fonte e mídia de outro domínio. Atinge os CDNs e a videoaula. Ficam desligados de propósito |
| `Permissions-Policy` sem câmera e microfone | A política só desliga geolocalização, pagamento e USB. Câmera e microfone continuam para o Jitsi e o reconhecimento facial |
| Timestamp `1791844919` (ZAP, baixo) | É o `?v=` do CSS, para o navegador buscar o arquivo de novo. Não é data de sessão nem segredo |
| Conteúdo não cacheável, cookie `EDUCATUDO_SESSION` | A página de login não deve ir para cache. O cookie de sessão é o comportamento esperado |
| Logo e CSS com cache longo | Logo de layout é público. CSS em `/static/` é arquivo estático. Os dois podem ser cacheados |
| `/api/docs` e `/api/openapi.json` públicos (26 caminhos) | A documentação da API do responsável é pública de propósito. O spec não contém senha. O texto do guia deixou de pedir senha de pessoa real |
| Portas 80 e 443 abertas | São a Cloudflare, na frente do proxy. É o caminho normal do site |
| Portas 8080 e 8443 “abertas”, demais portas em timeout | O teste bateu no IP público da borda, não na origem. Timeout não prova porta fechada, e porta aberta na borda não prova serviço da escola (MySQL, Redis, Docker, SSH) |
| XSS em `/primeiro-acesso` | O ZAP só viu o valor do formulário de volta no HTML, sem aspas e sem script. `aluno_nome` e o token passam por `htmlspecialchars`. `_token` é o CSRF. `turma_id` é gravado como inteiro |
| Login, CSRF e SQLi “não retestados” | Esta rodada não fez POST nem login. Não é um furo encontrado. CSRF segue no POST e o SQL segue com prepared statement |
| HEAD 404 “não comprova o GET” | A regra do Nginx é `deny all` no `location`, não só no HEAD. O GET cai no mesmo 404 |

## Testes recentes de 19/11

Ambiente `https://educa.educatudo.com`. Páginas públicas, sem login e sem envio de formulário.

| Verificação | O que foi visto | Avaliação |
|---|---|---|
| GET nas 5 páginas | `/`, `/login`, `/recuperar-senha`, `/aluno/recuperar-senha` e `/primeiro-acesso`: HTTP 200 | Esperado |
| Cookies | `EDUCATUDO_SESSION` com Secure, HttpOnly e SameSite=Lax | Adequado |
| Cache de páginas sensíveis | Recuperar senha, recuperar senha do aluno e primeiro acesso: `no-store` | Adequado |
| Cabeçalhos | HSTS, `X-Frame-Options` e `X-Content-Type-Options` duplicados | Ajustar. O valor é o mesmo nas duas cópias |
| Content Security Policy | Só `Content-Security-Policy-Report-Only` | Revisar. A política que bloqueia continua desligada de propósito |
| Formulário `/login` | POST com `_token`, `tipo`, `site_url`, `login` e `senha` | Estrutura identificada. O POST não foi enviado |
| Formulário `/recuperar-senha` | POST para `/enviar-recuperacao` com `_token`, `tipo` e `email` | Estrutura identificada. O POST não foi enviado |
| Formulário `/primeiro-acesso` | POST com `_token`, `turma_id`, `aluno_nome` e `aluno_id` | Estrutura identificada. Autorização do POST não foi exercitada |
| Formulário da recuperação do aluno | O HTML não declara `method`. O JavaScript usa `preventDefault` e `fetch` POST | O envio real depende do script. Sem JavaScript o formulário não segue o POST previsto |
| POST da recuperação do aluno | O script aponta para `/pergunta` e `/reset`. Nenhum POST foi enviado nesta etapa | Não testado |
| OPTIONS `/pergunta` e `/reset` | HTTP 404 nos dois, com `cache-control: no-store` | Inconclusivo para o POST |
| GET `/pergunta` | HTTP 404 em 0,178 s | Esperado. A pergunta só existe no POST |
| GET `/reset` | HTTP 404 em 0,155 s | Esperado. A troca de senha só existe no POST |

Nesta rodada não apareceu SQL injection, XSS, CSRF quebrado nem redefinição de senha sem autorização. GET e OPTIONS com 404 não provam o POST.

## Parecer

O que foi testado não encontrou vulnerabilidade. Isso não garante o sistema inteiro: o que entrou na prova foi página pública, arquivo sem login, caminho sensível, cabeçalho e a leitura do SQL de login e primeiro acesso. Formulário foi identificado, e o POST não foi enviado.

A auditoria de 19/11 pediu o canário de exercício sem cookie e recebeu 401, na origem e na Cloudflare. O mesmo 401 apareceu em `/media/serve` de prova, em `/api/v1/students` e em `/api/parents/children`. Os 18 caminhos de `.env`, `.git`, backup, dump, `phpinfo.php`, configuração e log responderam 404. Home, login, recuperação, primeiro acesso e guia responderam 200 sem devolver dado de aluno. O marcador colocado em nome, tipo e etapa voltou como texto, sem virar script.

Os alertas que o ZAP manteve nesse conjunto também não são vulnerabilidade. A CSP que está no ar é a de relatório. `X-Frame-Options` e HSTS saem duas vezes, com o mesmo valor. COOP, COEP e CORP ficam desligados porque cortariam CDN e videoaula. O número `1791844919` é a versão do CSS. O cookie de sessão e a página sem cache são o comportamento do login. A documentação em `/api/docs` é pública de propósito e o spec não traz senha. As portas 80 e 443 são a Cloudflare.

No login e no primeiro acesso, o SQL lido no código não concatena o que o usuário digita. Apelido, e-mail, nome, turma e id entram como parâmetro (`:nickname`, `:login`, `:nome`, `:id`). A senha é conferida com `password_verify` fora da consulta. O ZAP não registrou alerta de injeção SQL.
