# Plano de testes em homologação — autorização e tenant

Usar só duas escolas fictícias, criadas para o teste. Não usar aluno, responsável, arquivo ou token de produção. Não reutilizar senha real. Os nomes abaixo são rótulos, não contas existentes.

| Papel | Escola Alfa | Escola Beta |
| --- | --- | --- |
| Admin | admin.alfa | admin.beta |
| Professor da turma T1 | prof.alfa | prof.beta |
| Aluno da turma T1 | aluno.alfa.1 | aluno.beta.1 |
| Outro aluno da mesma turma | aluno.alfa.2 | — |
| Aluno de outra turma | aluno.alfa.3 | — |
| Responsável de aluno.alfa.1 | pai.alfa.1 | pai.beta.1 |
| Responsável sem vínculo | pai.alfa.sem-filho | — |
| Monitor da turma T1 | monitor.alfa | — |

Cada escola no próprio host de homologação (`alfa.homolog...` e `beta.homolog...`). O teste entre escolas usa os dois hosts. O teste entre usuários usa o mesmo host.

Antes de cada caso, anotar só o código HTTP e se o corpo contém o marcador fictício. Não gravar cookie, token nem o conteúdo do arquivo.

## 1. Escola diferente

1. Login de aluno.alfa.1 no host Alfa. Copiar o cookie de sessão.
2. Repetir o mesmo cookie no host Beta, em `GET /dashboard` e `GET /boletim`.
3. Esperado: o host Beta não abre a sessão da Alfa. Resposta de login ou sessão vazia. Nenhum nome da Alfa no HTML.

4. Emitir JWT de pai.alfa.1 no host Alfa.
5. Chamar `GET /api/v1/students` e `GET /api/parents/children` no host Beta com esse Bearer.
6. Esperado: 401. O middleware compara o tenant do token com o host.

7. `GET /media/serve?type=layout&key=<logo-da-beta>&tenant=<slug-beta>` no host Alfa, sem sessão e com sessão de aluno.alfa.1.
8. Esperado para tipo que não seja `layout`: 403. Para `layout`, o código permite a troca de tenant. Registrar se o logo da Beta aparece. Isso confirma o A4 e o limite do A1.

## 2. Outro aluno na mesma escola

Contas: aluno.alfa.1 e aluno.alfa.2.

1. Com aluno.alfa.2, enviar uma redação, uma mensagem de chat e um anexo de ticket. Anotar só o nome do arquivo gerado.
2. Com aluno.alfa.1, pedir:
   - `GET /media/serve?type=redacoes&key=<chave>`
   - o mesmo para `chat` e `tickets`
   - a URL direta `/uploads/redacoes/<chave>`
3. Esperado: 401 sem sessão, 403 com a sessão do outro aluno. 200 só para o dono, o responsável vinculado, o professor da turma ou o admin.

4. Repetir com `type=jornadas_documentos`, `jornadas_exercicios`, `jornadas_videos`, `apostilas`, `notifications` e `provas_questoes`, usando uma chave que pertença ao aluno.alfa.2.
5. Se vier 200 para aluno.alfa.1, o A1 está reproduzido.

6. `GET /uploads/jornadas/exercicios/<arquivo>` sem cookie.
7. Se vier 200, o A3 está reproduzido.

8. Criar um arquivo fictício numa pasta de upload que não seja redação, arquivo, mensagem, ticket ou caderno. Pedir a URL sem sessão.
9. Se vier 200, o A2 está reproduzido. Apagar o arquivo ao final.

## 3. Responsável

1. pai.alfa.1 abre `GET /pais/filhos/{id}` com o id de aluno.alfa.1. Esperado: 200.
2. O mesmo usuário troca o id para aluno.alfa.2 e para aluno.alfa.3. Esperado: não mostra nota, redação nem documento.
3. pai.alfa.sem-filho usa o id de aluno.alfa.1. Esperado: o mesmo bloqueio.
4. Na API, `GET /api/v1/students/{id}/report-card` e `GET /api/parents/child/{id}/essays` com o id do filho e, em seguida, com o id do outro aluno. Esperado: 200 só no filho vinculado. O outro id não devolve nota nem texto.

## 4. Professor e monitor

1. prof.alfa abre a ficha, as provas e o relatório de aluno.alfa.1 e de aluno.alfa.3.
2. Esperado: aluno da turma T1 visível; aluno de outra turma, não. A ficha `GET /professor/student/{id}` hoje retorna antes da consulta. Registrar se a tela fica vazia em vez de vazar.
3. monitor.alfa abre `GET /monitor/aluno/{id}` do aluno da T1 e do aluno de outra turma. Esperado: a outra turma volta para o dashboard com a mensagem de fora do escopo.

## 5. Admin e perfil trocado

1. Sessão de aluno.alfa.1 em `GET /admin/students` e `GET /admin/documentos-familia`.
2. Esperado: redireciona para o painel do aluno, sem lista de alunos.
3. Sessão de prof.alfa nas mesmas URLs. Esperado: painel do professor.
4. admin.alfa abre um boletim e um histórico do aluno.alfa.1. Esperado: 200. Repetir o id de um aluno que não exista. Esperado: não devolver arquivo de outro id por engano.

## 6. Primeiro acesso e entrar como

1. `POST /primeiro-acesso` com um nome que existe e com um nome que não existe, na escola Alfa.
2. Esperado hoje, se o A5 seguir o código: as respostas diferem. O teste só registra isso. Não percorrer a criação de senha de um aluno real.
3. Gerar um token de entrar como para aluno.alfa.1, usar uma vez, usar de novo. Esperado: a segunda vez entra só se o Redis estiver fora. Não testar com o Redis de produção.

## 7. O que fica de fora

Não chamar produção. Não usar o cookie obtido no ZAP. Não pedir arquivo cuja chave tenha vindo de um aluno real. Não medir a API facial com código de pareamento de uma escola em uso.

Os GET sem efeito colateral estão em `rotas_auditoria.txt`. Eles servem para ver se a homologação responde. Não substituem os casos acima.
