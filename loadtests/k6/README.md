# Teste de carga (k6) — EducaTudo

Simula uso real (login → navegar → pausar → navegar), **não** um flood de GET. Serve
pra achar página/consulta que não escala, junto com o **Performance Profiler**
(`Master → Performance`, ver `../../backend/app/Performance/README.md`).

## Instalar

```bash
brew install k6
# ou, sem instalar nada no Mac: docker run --rm -i grafana/k6 run - < scenarios/realistic-journey.js
```

## Rodar

```bash
cd loadtests/k6/scenarios

# Smoke test rápido (poucos VUs, patamares curtos) — rode este primeiro
k6 run -e BASE_URL=http://colag.localhost -e LOGIN=aluno.teste -e PASSWORD='Teste@123' \
       -e MAX_VUS=5 -e POOL_SIZE=2 -e STAGE_RAMP=3s -e STAGE_HOLD=5s \
       realistic-journey.js

# Rampa completa 1 → 5 → 20 → 50 → 100 → 200 VUs (padrão: 15s de subida + 30s de patamar em cada nível, ~6min total)
k6 run -e BASE_URL=http://colag.localhost -e LOGIN=aluno.teste -e PASSWORD='Teste@123' \
       realistic-journey.js

# Só até 50 VUs (pula 100/200)
k6 run -e MAX_VUS=50 realistic-journey.js
```

Variáveis de ambiente aceitas (todas opcionais, com default sensato):

| Var | Default | O que faz |
|---|---|---|
| `BASE_URL` | `http://colag.localhost` | Host/tenant a testar |
| `LOGIN` / `PASSWORD` / `TIPO` | `aluno.teste` / `Teste@123` / `aluno` | Credencial usada pro pool de sessões |
| `POOL_SIZE` | `5` | Quantas sessões autenticadas distintas criar em `setup()` |
| `MAX_VUS` | `200` | Teto da rampa (filtra os patamares 1/5/20/50/100/200 até esse valor) |
| `STAGE_RAMP` / `STAGE_HOLD` | `15s` / `30s` | Duração da subida e do patamar em cada nível |

## ⚠️ Importante — rate limit de login

O EducaTudo bloqueia login por **IP** depois de `MAX_LOGIN_ATTEMPTS_IP` tentativas
(padrão no `.env`: **50**) dentro de `LOCKOUT_DURATION` segundos (padrão: 900 = 15min).
Como o k6 roda de uma única máquina (um IP só), o script **nunca loga a cada
iteração** — ele autentica só `POOL_SIZE` vezes, uma única vez, em `setup()`
(antes da rampa de VUs começar), e cada VU reaproveita uma dessas sessões
(round-robin). Isso também é mais realista: gente de verdade não re-loga a cada
clique.

Se quiser testar o **login em si** sob carga (não é o foco deste script), crie um
cenário separado com poucos VUs (bem abaixo de 50) batendo só em `/login`.

## O que o cenário faz por iteração

1. `GET /dashboard`
2. 2 a 3 páginas aleatórias entre `/caderno`, `/redacoes`, `/redacoes/historico`,
   `/flashcards`, `/forum` — com pausa de 1 a 4s entre cada uma (tempo de leitura)
3. Pausa de 2 a 5s antes da próxima "sessão de uso" do mesmo VU

## Depois de rodar

1. Garanta que `APP_DEBUG=true` no `.env` do servidor sendo testado (senão o
   Performance Profiler não grava nada — ver `backend/app/Performance/README.md`).
2. Abra `http://master.localhost/master/performance` — filtre pela janela de
   tempo do teste e veja: páginas mais lentas, top queries, N+1, sugestão de
   índice.
3. Exporte CSV/PDF/JSON direto do painel se quiser levar pra outro lugar.

## O que este script NÃO cobre (de propósito)

- Ações de escrita (POST com CSRF) — o foco aqui é achar gargalo de leitura, que
  é a maior parte do tráfego típico. Dá pra estender com um cenário `write-actions.js`
  reaproveitando `lib/login.js` se quiser incluir criar redação/caderno etc.
- Teste de capacidade máxima ("quantos usuários aguenta") — isso é outro tipo de
  teste (breakpoint/stress), com objetivo diferente do que foi pedido aqui
  (achar gargalo, não achar o limite).
