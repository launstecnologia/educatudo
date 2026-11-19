# Checklist de validação

Uma onda só é dada como feita quando todos os itens dela passam no ambiente autorizado (local ou homologação fictícia). Produção não entra nesta lista. Nenhum item foi marcado como executado.

## SEC-001 — imagem de exercício

- [ ] Sem cookie, `GET /uploads/jornadas/exercicios/{fixture}` responde 401 e o corpo não contém o PNG.
- [ ] Sem cookie, `GET /media/serve?type=jornadas_exercicios&key={fixture}` responde 401.
- [ ] Aluno da jornada, com sessão, recebe 200 na URL antiga e na URL `/media/serve`.
- [ ] Professor da jornada recebe 200.
- [ ] Admin da escola recebe 200.
- [ ] `Cache-Control` da resposta autenticada é `private`.
- [ ] Com S3 desligado no teste, não há cabeçalho `Location`.
- [ ] Com S3 ligado só no ambiente de teste, a URL assinada não é emitida para o anônimo.
- [ ] Nome com `..` responde 400.
- [ ] A tela do exercício do aluno mostra a imagem.

## SEC-006 — padrão privado

- [ ] `pastaDireta('uploads/seg-nao-listada/a.png')` devolve `negado`.
- [ ] `GET` anônimo dessa URL responde 403.
- [ ] `GET` anônimo de `uploads/documentos/{fixture}` responde 403.
- [ ] `GET` anônimo de `uploads/videos/` e `uploads/apostilas/` responde 403 quando o fixture existir.
- [ ] `GET` anônimo de `uploads/layout/{fixture}` responde 200.
- [ ] `uploads/redacoes_temas/` e `uploads/essays/proposals/` continuam públicos, de propósito.
- [ ] Redação, arquivo, mensagem e ticket continuam negados para anônimo e para aluno que não é o dono.
- [ ] Caderno (`uploads/cadernos_aluno/`) continua negado.
- [ ] Extensão `php`, `html`, `js` e `svg` em `public/uploads/` continua sem execução, como o bootstrap já faz.

## SEC-005 — dono do arquivo

- [ ] Tipos `provas_questoes`, `jornadas_exercicios`, `jornadas_documentos`, `jornadas_videos`, `apostilas` e `notifications` não retornam verdadeiro só porque há `user_id`.
- [ ] Sem registro da chave, a resposta é 403 para aluno, pai, professor e monitor.
- [ ] Admin da mesma escola recebe o arquivo.
- [ ] Aluno dono, ou aluno da turma quando o recurso é da turma, recebe 200.
- [ ] Outro aluno, sem essa turma, recebe 403.
- [ ] Pai vinculado ao aluno do recurso recebe 200 só onde a tela do responsável já mostra o arquivo.
- [ ] Pai sem vínculo recebe 403.
- [ ] Professor da jornada ou da turma recebe 200. Professor de outra turma recebe 403.
- [ ] `?tenant=` de outra escola, com sessão de aluno, responde 403 nos tipos privados.
- [ ] Chat, redação, ticket, arquivo e mensagem continuam no ramo que já consulta o registro.

## SEC-002 — limite e resposta genérica

- [ ] O sexto pedido do mesmo e-mail fictício, dentro de 900 segundos, não chama o envio.
- [ ] O quinquagésimo primeiro pedido do mesmo IP, com identificadores diferentes, é recusado.
- [ ] Seis identificadores diferentes no mesmo IP, um pedido cada, são aceitos.
- [ ] E-mail cadastrado e e-mail ausente devolvem o mesmo JSON e o mesmo status.
- [ ] A primeira resposta da pergunta do aluno é igual para nickname ausente e nickname com pergunta.
- [ ] Resposta errada da pergunta de segurança entra no limite.
- [ ] CSRF ausente continua 400, sem envio e sem contar como sucesso.
- [ ] Log de falha de envio não contém token, URL de redefinição, e-mail completo nem nome.
- [ ] Aluno fictício consegue concluir a recuperação dentro do limite.
- [ ] Professor fictício consegue pedir o e-mail uma vez e abrir o formulário de nova senha em local, com dublê de e-mail.
- [ ] Primeiro acesso continua utilizável para um nome fictício único. Lista de homônimos só muda se o produto tiver escolhido o segundo dado.

## SEC-003 — cabeçalhos

- [ ] A resposta HTML local contém `Content-Security-Policy-Report-Only`.
- [ ] Não contém `Content-Security-Policy` obrigatório.
- [ ] Login do aluno, professor, admin e a tela de prova carregam Font Awesome, HTMX, MathJax e a fonte.
- [ ] Aula Jitsi de fixture não fica bloqueada pelo Report-Only a ponto de a página quebrar (Report-Only não bloqueia; o relatório é o que se lê).
- [ ] `Referrer-Policy: strict-origin-when-cross-origin` sai na resposta PHP.
- [ ] `Permissions-Policy` não zera `camera` nem `microphone`.
- [ ] Nginx do repositório permanece igual ao commit anterior.

## SEC-004 — OpenAPI

- [ ] `GET /api/docs` e `GET /api/openapi.json` seguem 200 sem sessão, até decisão contrária.
- [ ] Os 26 caminhos continuam no JSON.
- [ ] `bearerAuth` continua documentado.
- [ ] O texto não orienta digitar a senha real de um responsável.
- [ ] Nenhuma rota nova foi acrescentada ao spec por causa desta revisão.

## SEC-007 — fotos

- [ ] Inventário de `avatar_url`, `photo_url`, `type=avatars` e `type=professores` escrito antes de qualquer mudança de `PUBLIC_TYPES`.
- [ ] Enquanto o inventário não fechar, os dois tipos continuam públicos, como hoje.
- [ ] Logo (`type=layout`) não entra nessa mudança.

## SEC-008 — perfil

- [ ] Aluno em `/admin/students` não vê a lista.
- [ ] Professor em `/admin/students` não vê a lista.
- [ ] Pai em `/professor/dashboard` cai no painel do responsável.
- [ ] Monitor em `/pais/dashboard` cai no painel do monitor.
- [ ] Admin abre o painel admin.
- [ ] Aluno, professor e admin ainda abrem a prova pelas rotas que `ExamController` já atende.
- [ ] `POST /aluno/provas/log-evento` continua aceitando o fluxo que o controller já tem para sessão caída.
- [ ] `/uploads/provas/questoes/` com sessão autorizada continua 200.
- [ ] Cada exceção da tabela do middleware tem um teste.

## Complementos

- [ ] COMP-03: com Redis indisponível no teste, `GET /auth/entrar-como` não cria sessão.
- [ ] COMP-03: com Redis no ar, o segundo uso do mesmo token fictício não cria sessão.
- [ ] COMP-02: objeto com prefixo da escola continua sendo lido. Fallback sem prefixo só se a lista legada da homologação o incluir.
- [ ] COMP-04: `/api/v1/status` continua sem nota, aluno e segredo.

## Regressão mínima antes de qualquer implantação

- [ ] Login dos quatro portais em local.
- [ ] Exercício com imagem, usuário autorizado e visitante.
- [ ] Documento de jornada pela tela.
- [ ] Logo da tela de login.
- [ ] Recuperação com dublê, um pedido apenas.
- [ ] Nenhuma suíte apontou para produção.
