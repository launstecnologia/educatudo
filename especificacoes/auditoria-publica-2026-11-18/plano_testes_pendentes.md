# Testes públicos ainda não executados

Host indicado: `https://educa.educatudo.com`. Nenhum pedido foi enviado. Nenhum e-mail foi disparado. Os dois arquivos canário existem só neste repositório.

## O que já está no disco local

| Arquivo | URL quando copiado ao host | Sem sessão, no código de hoje | Sem sessão, o que a auditoria espera |
| --- | --- | --- | --- |
| `backend/public/uploads/jornadas/exercicios/auditoria-canario-e7c4a91f.png` | `/uploads/jornadas/exercicios/auditoria-canario-e7c4a91f.png` | 200, se o arquivo estiver no disco daquele host | 401 |
| `backend/storage/provas/questoes/auditoria-canario-m3b8d26e.png` | `/media/serve?type=provas_questoes&key=auditoria-canario-m3b8d26e.png` | 401 | 401 |

Enquanto os arquivos não forem copiados para o host, a primeira URL responde 404. A segunda responde 401 mesmo sem o arquivo, porque a sessão é exigida antes da leitura.

## Ordem, só leitura

1. Confirmar que o host é a homologação e não uma escola com aluno real.
2. Copiar os dois canários para o armazenamento desse host, sem mudar permissão de pasta.
3. Pedir as duas URLs sem cookie. Anotar só o status.
4. `GET /media/serve?type=avatars&key=` e `type=professores` só se existir uma chave fictícia já gravada. Sem sessão o código atual entrega o arquivo.
5. `GET /primeiro-acesso` e `GET /aluno/recuperar-senha` uma vez, para ver o formulário. Não enviar o POST.
6. `POST /login` só com o formato já descrito na análise estática: corpo de formulário, `_token` de 64 caracteres, `site_url` vazio. Credencial inexistente deve ser 401 no cliente JSON, não 400.

## Não executar nesta etapa

- `POST /enviar-recuperacao` e `POST /professor/enviar-recuperacao`: enviam e-mail.
- `POST /recuperar-senha/reset`, `POST /aluno/recuperar-senha/reset` e `POST /primeiro-acesso/salvar`: gravam senha.
- `POST /primeiro-acesso` com nome de aluno real: o código responde diferente se o nome existe.
- Webhooks, checkout Asaas, pareamento facial, consumo de crédito e LEX.
- Qualquer corpo de injeção. O login usa parâmetro nomeado; o 400 visto antes para no honeypot `site_url`.

## Limites conferidos no código

| Fluxo | Limite |
| --- | --- |
| Login web, por IP | 50 falhas em 900 segundos, depois do CSRF |
| Login web, aluno | 5 falhas em 300 segundos, por nickname |
| Login web, outros perfis | 5 falhas em 900 segundos, pelo valor de `config/app.php` |
| Primeiro acesso | sem limite |
| Recuperação de senha (e-mail, professor e aluno) | sem limite |
| `POST /api/auth/login` | 10 em 60 segundos, por IP |
| `POST /api/v1/auth/login` | 5 em 300 segundos, por CPF e IP |

## Inserção de HTML em página sem login

A página pública `GET /guia/implantar-academico` lê `location.hash` só para achar o id de uma etapa. Não escreve esse valor no HTML.

`backend/app/Views/auth/login.php` usa `innerHTML`, com texto fixo do script. Nenhuma rota atual chama essa view. As telas de login em uso (`login-aluno.php` e as outras `login-*.php`) não usam `location.search`, `location.hash`, `innerHTML` nem `document.write`.

Os scripts de painel (`editor-documento.js`, assistente de boletim, dashboard) ficam atrás de sessão. Ficam fora desta bateria pública.
