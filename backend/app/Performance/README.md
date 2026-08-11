# Performance Profiler — EducaTudo

Diagnóstico de queries/páginas lentas, N+1, EXPLAIN automático e sugestão de
índice (heurística). **Só existe pra achar gargalo — não é um teste de "quantos
usuários aguenta"** (isso é o k6, em `loadtests/k6/`).

## Instalação

Nenhuma migration, nenhuma dependência nova no `composer.json` (o PDF usa o
`dompdf` que já estava lá). Só rode, por segurança/consistência do autoload:

```bash
composer dump-autoload --working-dir=/var/www/html
```

(Testei sem rodar isso e funcionou — o autoload PSR-4 já resolve classe nova
dentro de um namespace que já existe (`App\`) mesmo sem regenerar. Mas se algum
dia o build usar `composer install/dump-autoload -a` — "classmap authoritative"
— aí passa a ser obrigatório. Rodar não faz mal nenhum, então recomendo.)

## Como ligar/desligar

Só roda quando `APP_DEBUG=true` no `.env` (mesma variável que o resto do código
já usa). Com `APP_DEBUG=false` (ou ausente), o profiler inteiro fica desligado —
overhead: **um `bool` cacheado por request**, nada mais.

```bash
# .env
APP_DEBUG=true

# opcional — ajusta os limiares (todos têm default sensato, não precisa mexer)
PERF_EXPLAIN_THRESHOLD_MS=50     # query acima disso dispara EXPLAIN ANALYZE automático
PERF_N1_THRESHOLD=5              # nº de repetições do mesmo SQL pra virar alerta de N+1
PERF_MAX_EXPLAINS=5              # teto de EXPLAIN ANALYZE reais por request (limita overhead)
PERF_PROFILER=true               # desliga só o profiler completo mesmo com APP_DEBUG=true (opcional)
```

Depois de mudar `.env`, reinicie o `php-fpm` (`docker restart php_app_educatudo`
ou equivalente) — o `.env` é lido uma vez por processo.

### Pausar/retomar pelo painel (sem editar `.env`, sem reiniciar nada)

`APP_DEBUG=true` é o **interruptor mestre** — só existe a possibilidade de ligar
com ele (trava fixa, não contornável pelo painel, de propósito: nunca deve vazar
em produção sem alguém editar o `.env` conscientemente). Com o mestre ligado,
o botão **Pausar coleta / Retomar coleta** no topo de `Master → Performance`
liga/desliga a coleta **em tempo real**, sem editar arquivo nem reiniciar
processo — útil pra, por exemplo, pausar durante um horário de pico real (não
gerar log de produção o dia inteiro) e retomar só na hora de investigar algo.

Tecnicamente: cria/apaga um arquivo `storage/logs/performance/.paused` — cada
request checa a existência desse arquivo (1 `is_file()`, desprezível).

### Limpeza automática

`requests_*.jsonl` mais velhos que `PERF_RETENTION_DAYS` (default: **7 dias**)
são apagados sozinhos — checado no máximo 1x por dia por processo (marcador em
arquivo), não a cada request. `PERF_RETENTION_DAYS=0` desativa a limpeza (guarda
tudo pra sempre).

## Onde ver o resultado

`master.localhost/master/performance` (menu **Ferramentas → Performance**, só
aparece com `APP_DEBUG=true`). Painel **master-only** de propósito: os logs
cobrem *todas* as escolas (é um único processo PHP atendendo todo mundo), então
não faz sentido um admin de escola ver isso.

Filtros: período, escola, rota, controller, tempo mínimo, quantidade mínima de
queries, "só com alerta". Exporta CSV, JSON ou PDF.

## Onde o dado fica

`storage/logs/performance/requests_YYYY-MM-DD.jsonl` — um arquivo por dia, uma
linha JSON por request. Sem banco de dados novo, sem migration. Cada linha:

```json
{
  "route": "/dashboard", "controller_action": "User/StudentController@dashboard",
  "tenant_slug": "colag", "user_id": 618, "user_type": "aluno",
  "time_php_ms": 12.3, "time_sql_ms": 150.8, "time_view_ms": 58.0, "time_total_ms": 203.6,
  "queries_count": 7, "slowest_query_ms": 112.9,
  "memory_peak_mb": 6.0,
  "n_plus_one": [ { "fingerprint": "...", "count": 12, "wasted_ms": 34.2, "suggestion": "..." } ],
  "slow_queries": [ { "sql": "...", "duration_ms": 112.9, "explain": {...}, "index_advice": {...} } ],
  "alerts": [ { "level": "warning", "message": "Full scan detectado numa query lenta..." } ]
}
```

**Nunca grava valor de parâmetro de query (potencial PII) no arquivo** — só
`params_count`. Os valores reais dos parâmetros ficam em memória (por request,
descartados no fim) só pra o EXPLAIN ANALYZE conseguir rodar de verdade.

## Como funciona (arquitetura)

```
app/Performance/
├── Profiler.php              gate único (APP_DEBUG) + limiares configuráveis
├── QueryCollector.php        coleta TODA query do request (não só as lentas — base do N+1)
├── NPlusOneDetector.php      agrupa por "fingerprint" de SQL, alerta repetição
├── IndexAdvisor.php          sugere índice (heurística sobre o texto do SQL — nunca cria)
├── ExplainAnalyzer.php       EXPLAIN ANALYZE (fallback EXPLAIN FORMAT=JSON) nas queries lentas
├── RequestProfiler.php       orquestra: start()/finish(), tempos de controller/view/sql
├── PerformanceLogger.php     grava o JSONL (1 escrita por request)
└── Reports/
    ├── RequestLogReader.php  lê/agrega os JSONL pro dashboard
    ├── CsvExporter.php
    └── PdfExporter.php       usa o dompdf (já é dependência do projeto)
```

Ganchos nos arquivos **existentes** (edições mínimas, nenhuma regra de negócio
tocada):

| Arquivo | O que foi adicionado |
|---|---|
| `app/Core/Database.php` | dentro de `recordMetrics()`: chama `QueryCollector::record()` (mesmo lugar que já chama o `PerfLogger` que já existia) |
| `app/Core/Router.php` | ao redor da chamada do controller: `setController()` + `markControllerStart()/End()` |
| `app/Core/BaseController.php` | ao redor de `view()`/`viewWithLayout()`: `markViewStart()/End()` |
| `bootstrap/app.php` | `RequestProfiler::start()` cedo + `register_shutdown_function` chamando `finish()` (do lado do shutdown que já existia pro `PerfLogger`) |

Já existia no projeto um `PerfLogger` (grava só *queries lentas* e *requests
lentos*, arquivo separado `slow_queries_*.jsonl`/`slow_requests_*.jsonl`) — o
Profiler novo **não substitui isso**, complementa: o `PerfLogger` já existente
continua rodando igual; o Profiler novo é quem sabe **todas** as queries do
request (não só as lentas), o que é necessário pra detectar N+1 (uma query de
2ms repetida 120x não aparece no log de "lentas", mas em conjunto é o maior tipo
de gargalo do EducaTudo).

## Limitação conhecida — granularidade Service/Repository/Helper

O pedido original queria tempo individual de Controller, Service, Repository,
Helper, View e Middleware. Esse app **não tem uma camada de Service/Repository
consistente** (é MVC "clássico", lógica dentro do Controller/Model), e
instrumentar cada classe uma a uma exigiria tocar em centenas de arquivos — alto
risco, baixo benefício comparado ao que já dá pra medir nos pontos de extensão
que já existem (Controller total, SQL total, View). O "tempo PHP puro" no
relatório é a diferença (`Controller − SQL − View`) — é uma aproximação boa o
suficiente pra saber "essa página é lenta por causa de banco, de renderização,
ou de outra coisa", que é a pergunta que mais importa no dia a dia. Para
profiling linha-a-linha de verdade (função por função), use **Xdebug (modo
profile) ou Blackfire** como complemento — ferramentas feitas exatamente pra
isso, sem reinventar a roda em userland PHP.

## O que foi deixado de fora (de propósito, pra não entregar algo raso)

- **Excel (.xlsx) de verdade** — hoje exporta CSV (abre bem no Excel/Sheets).
  Um `.xlsx` nativo precisaria adicionar `phpoffice/phpspreadsheet` como
  dependência nova do composer — decisão que achei melhor deixar pra você
  aprovar, já que é uma lib grande.
- **Prometheus/OpenTelemetry** — o formato de dado (JSONL estruturado, um
  registro por request com campos nomeados) já foi pensado pra ser fácil de
  alimentar num exportador desses no futuro, mas não implementei o exportador
  em si (é infraestrutura adicional — coletor rodando, etc. — fora do escopo de
  "achar o gargalo agora").
- **Heatmap de calendário** — o dashboard mostra ranking/barra de progresso em
  vez de heatmap visual; cobre a mesma pergunta ("o que é mais lento") sem
  precisar de uma lib de gráfico nova.
- **Barra de debug flutuante na própria página** — o pedido original (item 13)
  pede um **dashboard separado**, que é o que foi construído; não adicionei um
  toolbar inline em cima de todas as páginas do sistema (risco de quebrar CSS/JS
  existente em produção mesmo com o profiler desligado, se alguém esquecer um
  `<?php` mal fechado).

Nenhum desses é "não dá pra fazer" — são só cortes de escopo conscientes pra
entregar o núcleo (profiler + dashboard + k6) testado de ponta a ponta em vez de
20 itens pela metade.
