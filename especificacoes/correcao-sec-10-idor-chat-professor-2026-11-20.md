# Correção SEC-10 — IDOR/BOLA chat professor↔aluno · 20/11/2026

## Critério de aprovação

Professor **sem vínculo** com o aluno **não** cria, visualiza nem envia mensagens; **nenhuma** linha indevida no banco.

**Resultado: APROVADO** (local).

## Arquivos alterados

| Arquivo | Ação |
|---|---|
| `backend/app/Policies/PoliticaAcessoProfessorAluno.php` | Novo — regra de vínculo |
| `backend/app/Painel/Integrations/Controllers/ChatController.php` | Validação em listar/abrir/enviar/buscar (professor e aluno) |
| `backend/tests/Seguranca/PoliticaAcessoProfessorAlunoTest.php` | Novo — 9 testes PHPUnit |

## Regra de vínculo

O professor autenticado (id **somente da sessão**) acessa o aluno se:

1. Aluno `ativo = 1` com `turma_id` válido  
2. Matrícula: se houver linhas em `matricula`, exige `status = 'ativa'` na turma atual  
3. Professor leciona a turma via **`jornadas`** (`ativo = 1` ou `NULL`) **ou** JSON **`professores.turmas`**

Conversa antiga em `chat_professores_alunos` **não** autoriza se o vínculo foi revogado.

Isolamento entre escolas permanece na conexão PDO do tenant.

## Endpoints cobertos

- `GET /professor/chat` (filtra listagem)
- `GET /professor/chat/{aluno_id}`
- `POST /professor/chat/enviar-mensagem`
- `GET /professor/chat/buscar-mensagens`
- `GET /chat-professor` / `{professor_id}` / enviar / buscar (lado aluno)

Negado: JSON **403** `{"error":"Acesso negado"}`; HTML redireciona sem dados do aluno e **sem** `INSERT`.

## Testes executados (local)

### PHPUnit

```bash
cd backend && ./vendor/bin/phpunit tests/Seguranca/PoliticaAcessoProfessorAlunoTest.php --testdox
```

| Caso | Resultado |
|---|---|
| Vinculado por JSON turmas | OK |
| Vinculado por jornada | OK |
| Não vinculado | OK (nega) |
| Aluno outra turma | OK (nega) |
| Aluno “outro tenant” (ausente no PDO) | OK (nega) |
| Vínculo revogado (conversa antiga) | OK (nega) |
| Matrícula inativa | OK (nega) |
| Matrícula ativa | OK |
| IDs inválidos | OK |

**OK (9 tests, 11 assertions)**

### HTTP Docker (`colag.localhost`)

| Cenário | Resultado |
|---|---|
| Prof `sec10.novinculo@colag.local` (`turmas=[]`) → `GET /professor/chat/619` | Redirect lista; **0** rows `chat_professores_alunos` para prof 61 |
| Mesmo prof → `GET .../buscar-mensagens?aluno_id=619` | **403** `Acesso negado` |
| Prof `mat.e2e@colag.local` (turma 24, aluno 619 na 24) → buscar | **200** |
| Contagem mensagens/chats do prof sem vínculo após tentativas | **0** |

## Observação sobre a reprodução original

O professor 56 **não** tinha jornadas, mas o JSON `turmas` inclui a turma 24 do aluno 619 — vínculo legítimo pelo cadastro. A falha real era permitir acesso **sem** jornada **e sem** turma no JSON (e confiar só na existência/criação da conversa). O caso `sec10.novinculo` cobre isso.

## Como revalidar

```bash
cd backend && ./vendor/bin/phpunit tests/Seguranca/PoliticaAcessoProfessorAlunoTest.php
# HTTP: login professor sem turmas → GET /professor/chat/{aluno_outra_turma}
# Esperado: 403 no buscar-mensagens; zero INSERT em chat_professores_alunos
```
