# Testes de segurança

Nenhum teste desta lista foi executado nesta fase. Os que exigem sessão ficam escritos e só rodam depois da autorização, em ambiente local ou de homologação com escola fictícia. Não apontar para `https://educa.educatudo.com`. Não usar aluno, senha ou arquivo real.

O Playwright do repositório usa `playwright.config.ts` na raiz e a base local `http://localhost:8000`. Teste que não precisa de navegador fica em `backend/tests/Seguranca/`, no PHPUnit já configurado em `backend/phpunit.xml`.

## Fixtures

Criar na hora do teste, com nome fixo, e apagar ao final. Não reutilizar os canários que já estão no disco do repositório como se fossem dados de escola.

| Fixture | Conteúdo | Onde, só no ambiente de teste |
|---|---|---|
| `seg-exercicio.png` | PNG mínimo, bytes conhecidos | `public/uploads/jornadas/exercicios/` |
| `seg-questao.png` | PNG mínimo | `storage/provas/questoes/` |
| `seg-doc.pdf` | PDF mínimo com a frase `FIXTURE-DOC` | `public/uploads/documentos/` |
| `seg-publico.png` | PNG de marca | `public/uploads/layout/` |
| `seg-desconhecido.png` | PNG | `public/uploads/seg-nao-listada/` |
| `seg-fora.png` | PNG | tentativa de chave `../seg-fora.png` |

Contas, só se a homologação autorizada já tiver as escolas fictícias do plano `especificacoes/auditoria-acesso-2026-11-18/plano_testes_homologacao.md`: `aluno.alfa.1`, `aluno.alfa.2`, `prof.alfa`, `pai.alfa.1`, `admin.alfa`, e o par na escola Beta. Sem essas contas, os casos autenticados ficam pulados, não improvisados com usuário real.

## 1. Acesso anônimo — pode rodar no PHP local sem senha

Ambiente: `docker compose` local, fixture copiada para o disco do container de teste.

| Caso | Pedido | Esperado hoje | Esperado depois da onda 1 |
|---|---|---|---|
| A1 | `GET /uploads/jornadas/exercicios/seg-exercicio.png` | 200 e o PNG | 401, corpo sem os bytes do PNG |
| A2 | `GET /media/serve?type=provas_questoes&key=seg-questao.png` | 401 | 401 |
| A3 | `GET /media/serve?type=jornadas_exercicios&key=seg-exercicio.png` | 401 | 401 |
| A4 | `GET /uploads/documentos/seg-doc.pdf` | 200 e `FIXTURE-DOC` | 403 |
| A5 | `GET /uploads/seg-nao-listada/seg-desconhecido.png` | 200 | 403 |
| A6 | `GET /uploads/layout/seg-publico.png` | 200 | 200 |
| A7 | `GET /media/serve?type=layout&key=seg-publico.png` | 200 se o arquivo estiver no caminho de layout | 200 |
| A8 | `GET /` | 200, tela de login do aluno | 200 |
| A9 | `GET /api/parents/children` sem `Authorization` | 401 | 401 |
| A10 | `GET /api/openapi.json` | 200 e 26 caminhos | 200, enquanto SEC-004 não for restringido |
| A11 | `GET /uploads/jornadas/exercicios/../seg-fora.png` e `key=../seg-fora.png` | 400 | 400 |
| A12 | `GET /media/serve?type=tipo_inexistente&key=a.png` | 400 | 400 |

Comando, quando o spec existir:

```bash
cd backend && ./vendor/bin/phpunit tests/Seguranca/EntregaArquivoAnonimaTest.php
```

O teste sobe só a decisão de `pastaDireta` e, se o ambiente local estiver no ar, o HTTP. Se o container não estiver no ar, a parte HTTP é ignorada e o relatório diz isso. Não substituir por pedido ao host público.

`pastaDireta` pode ser assertado sem HTTP:

- `uploads/jornadas/exercicios/a.png` → `rota` (antes e depois; quem muda é o controller);
- `uploads/layout/a.png` → `publico`;
- `uploads/documentos/a.pdf` → hoje `publico`, depois `negado`;
- `uploads/seg-nao-listada/a.png` → hoje `publico`, depois `negado`;
- `uploads/redacoes/aluno/1/a.png` sem sessão → `negado`.

## 2. Autorização — escrever agora, executar só com autorização

Não rodar nesta fase.

| Caso | Sessão | Pedido | Esperado depois da onda 2 |
|---|---|---|---|
| B1 | `aluno.alfa.1` | imagem do exercício da jornada dele | 200 |
| B2 | `aluno.alfa.2` | a mesma imagem, se a jornada não for dele | 403 |
| B3 | `aluno.alfa.1` | `type=jornadas_documentos` do aluno 2 | 403 |
| B4 | `aluno.alfa.1` | `type=redacoes` do aluno 2 | 403 (já é o código atual) |
| B5 | `pai.alfa.1` | documento do filho vinculado | 200 se o pai já vê esse documento na tela |
| B6 | `pai.alfa.1` | documento de aluno sem vínculo | 403 |
| B7 | `prof.alfa` | documento da jornada que ele criou | 200 |
| B8 | `prof.alfa` | documento de jornada de outro professor, outra turma | 403 |
| B9 | `aluno.alfa.1` | `GET /admin/students` | redireciona ao painel do aluno, sem lista |
| B10 | `prof.alfa` | `GET /admin/students` | redireciona ao painel do professor |
| B11 | cookie de Alfa no host Beta | `GET /dashboard` | sem nome da Alfa |
| B12 | JWT de `pai.alfa.1` no host Beta | `GET /api/v1/students` | 401 |

O caso B2 depende da regra de negócio: se o exercício é da turma inteira, `aluno.alfa.2` da mesma turma recebe 200. O teste precisa da turma gravada na fixture, não de um 403 fixo.

Comando previsto, só no host de homologação autorizado:

```bash
npx playwright test tests/seguranca/autorizacao-midia.spec.ts
```

## 3. Entradas

| Caso | Entrada | Esperado |
|---|---|---|
| C1 | `key` com `..`, barra invertida, `%00` ou caractere fora do padrão já rejeitado na linha 69 de `MediaServeController` | 400 |
| C2 | `filename` vazio | 400 |
| C3 | `POST /primeiro-acesso` com nome `"><script>` | o HTML devolvido contém o texto escapado, sem tag script criada pelo valor |
| C4 | `POST /login` sem `_token` | 403 no JSON, ou a mensagem de sessão expirada no formulário |
| C5 | `POST /enviar-recuperacao` sem `_token` | 400 e nenhum envio |
| C6 | nome de aluno no primeiro acesso com aspas | consulta continua com parâmetro nomeado; a resposta não muda por causa da aspas (não é erro de SQL) |

C5 não pode ser exercido contra caixa de e-mail real. No teste local o serviço de e-mail fica substituído por um dublê que só conta chamadas. Se o dublê não existir na implementação, o teste não sobe o POST.

Não há corpo de injeção neste plano. O critério é o código continuar com parâmetro nomeado e o teste recusar concatenação nova nesses métodos.

## 4. Limite de recuperação — local, dublê de e-mail

| Caso | Ação | Esperado depois da onda 3 |
|---|---|---|
| D1 | 6 POST do mesmo e-mail fictício dentro de 900 s | o sexto responde limite, e o dublê recebeu no máximo 5 envios |
| D2 | e-mail inexistente e e-mail existente | mesmo JSON, mesmo status |
| D3 | nickname inexistente e nickname com pergunta | primeira resposta sem campo que exista só num dos dois |
| D4 | 51 POST do mesmo IP com e-mails diferentes | o 51º é limite |
| D5 | 6 alunos fictícios diferentes no mesmo IP, um pedido cada | todos seguem (abaixo de 50) |

D1–D5 só com dublê. Não disparar SMTP.

## 5. Regressão funcional — depois da correção, em local

| Fluxo | Confirmação |
|---|---|
| Login admin, professor, aluno e responsável | cada um cai no próprio painel |
| Aluno abre exercício que tem imagem | a imagem carrega com a sessão |
| Professor abre o mesmo exercício | a imagem carrega |
| Visitante sem cookie | a imagem não carrega |
| Documento da jornada pela tela, não pela URL solta | o arquivo abre para o professor dono e para o aluno da jornada |
| Logo na tela de login | continua visível sem sessão |
| App | só se a API de homologação devolver URL de mídia: abrir essa URL com o JWT do responsável fictício e sem JWT. Não usar token de produção |

Comando de fumaça já existente no repositório, se for mantido só em local:

```bash
npx playwright test tests/seguranca/regressao-login.spec.ts
```

O arquivo ainda não foi criado. Criar na Fase B, junto com a correção, e rodar antes de considerar a onda pronta.

## 6. O que não automatizar

- Pedido ao host público.
- `POST` que grave senha.
- Webhook Asaas, Jitsi, ZapSign, facial e LEX.
- Varredura de nomes reais de aluno.
- Teste de carga.

## Resultado desta fase

Os testes estão especificados. Nenhum arquivo de teste foi adicionado e nenhum comando foi executado, porque a Fase A não altera o código e não usa dado real. A correção de cada onda só fecha quando a coluna "esperado depois" desta lista passar no ambiente autorizado.
