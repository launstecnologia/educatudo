# Dois passos: cadastrar e navegar

O K6 faz o mesmo que você faria no navegador, contra `https://hml-escola.educatudo.com`.

1. **criar** — entra no admin, abre `/admin/students/create`, cadastra aluno com nickname + senha
2. **navegar** — cada aluno entra em `/` com esse login e clica nas páginas do portal

## Preparar uma vez

```bash
brew install k6
cd loadtests/k6
cp .env.example .env
```

Edite o `.env`:

```
BASE_URL=https://hml-escola.educatudo.com
ADMIN_LOGIN=email-do-admin-da-hml
ADMIN_SENHA=senha-do-admin
TOTAL_ALUNOS=5000
VUS=10
```

Na HML, enquanto testar: `MAX_LOGIN_ATTEMPTS_IP=20000`.

Cada aluno fica assim:

| Campo | Valor |
|---|---|
| Nickname (login) | `carga00001` … `carga05000` |
| Senha | `Carga@2026` |

## 1) Cadastrar os 5 mil

```bash
cd loadtests/k6
./run.sh criar
```

O script abre a tela de cadastro, envia nome + nickname + senha e libera o primeiro acesso (senão o aluno não entra).

Teste com 20 antes dos 5 mil:

```bash
TOTAL_ALUNOS=20 VUS=5 DURATION=5m ./run.sh criar
```

## 2) Esses alunos entram e navegam

```bash
VUS=50 DURATION=40m ./run.sh navegar
```

Cada um faz login e passa por dashboard, provas, jornadas, notas, redações, jogos, mural, livros, caderno, avatar, simulados, desempenho, fórum e flashcards — em ordem sorteada, com pausa entre as páginas.

`VUS=50` = 50 alunos ao mesmo tempo. Os 5 mil passam ao longo da execução.

Depois, se a HML aguentar: `VUS=100`, `VUS=200`. Não use `VUS=5000`.

## Conferir

No admin da HML, busque `carga00001`. Deve existir e entrar em `https://hml-escola.educatudo.com` com senha `Carga@2026`.
