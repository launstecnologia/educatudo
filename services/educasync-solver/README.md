# educasync-solver — motor de grade horária do EducaSync

Microsserviço Python (FastAPI) **stateless**: recebe o cenário em JSON, resolve com
CP-SAT (OR-Tools) e devolve JSON. Sem banco, sem arquivos, sem estado entre requests.
O PHP monta o cenário a partir do banco do tenant e persiste o resultado.

## Stack

Python 3.11+, FastAPI, Uvicorn, Pydantic v2, python-dotenv, OR-Tools.

## Rodar local

```bash
cd services/educasync-solver
python3.11 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env   # definir INTERNAL_API_KEY
uvicorn app.main:app --host 0.0.0.0 --port 8091 --reload
```

Docker: `docker compose up -d --build` (porta 8091).

## Env

| Variável | Padrão | Observação |
|---|---|---|
| `INTERNAL_API_KEY` | — | Obrigatória. Vazia => rotas protegidas respondem 401 |
| `PORT` | `8091` | |
| `MAX_TIME_S_LIMITE` | `300` | Teto para `limites.max_time_s` |
| `MAX_WORKERS_LIMITE` | `8` | Teto para `limites.num_workers` |

## Endpoints

| Rota | Auth | Descrição |
|---|---|---|
| `GET /health` | não | Liveness |
| `POST /solve` | `X-Internal-Api-Key` | Resolve o cenário |
| `POST /validate` | `X-Internal-Api-Key` | Valida o cenário sem resolver (`valido`, `erros`, `avisos`) |
| `POST /metrics` | `X-Internal-Api-Key` | Métricas de uma grade pronta (`slots`, `demandas`, `alocacoes`): janelas por professor e conflitos |

Cenário inválido em `/solve` => HTTP 422 `{"error":"validation_error","erros":[...]}`.

### Request `/solve`

```json
{
  "cenario_id": 1,
  "slots": [{"id":1,"dia":1,"ordem":1,"horario_de":"07:00","horario_ate":"07:50"}],
  "demandas": [{"id":10,"turma_id":3,"materia_id":5,"qtd":2,"professor_id":9,"sala_id":null}],
  "disponibilidades": [{"tipo":"professor","ref_id":9,"slot_id":1,"livre":true}],
  "restricoes_hard": [],
  "restricoes_soft": [{"tipo":"minimizar_janelas","peso":100}],
  "pesos": {"janelas": 100},
  "locks": [],
  "limites": {"max_time_s": 60, "num_workers": 4}
}
```

### Response

```json
{
  "status": "OPTIMAL",
  "alocacoes": [{"demanda_id":10,"slot_id":1,"professor_id":9,"sala_id":null}],
  "metricas": {"aulas_alocadas": 2, "janelas_total": 0, "tempo_ms": 12},
  "objetivo": 0,
  "best_bound": 0,
  "tempo_ms": 12
}
```

`status`: `OPTIMAL` | `FEASIBLE` (achou solução, tempo acabou antes de provar ótimo) |
`INFEASIBLE` | `MODEL_INVALID` | `UNKNOWN` (sem solução no tempo). Mapeamento direto do
`CpSolverStatus`; `FEASIBLE` nunca é reportado como `OPTIMAL`. Sem solução, `alocacoes` é
`[]` e `objetivo`/`best_bound` são `null`.

## Modelo (MVP)

- Toda demanda exige `professor_id` (null => erro de validação).
- Cada demanda ocupa exatamente `qtd` slots distintos.
- Turma, professor e sala (se houver): no máximo 1 aula por slot.
- Disponibilidade (`professor`, `turma`, `sala`): se há registros para a entidade, só
  slots com `livre=true` são permitidos; sem registros, todos são livres.
- `locks`: fixa a demanda em um slot (inviável se o slot for indisponível). Serve para
  reotimização: trave o que já está decidido e o resto é recalculado. Várias locks por
  demanda (até `qtd`); duplicatas viram aviso, locks em conflito (mesma turma/professor/sala
  no mesmo slot) ou acima de `qtd` viram erro de validação.
- `restricoes_hard`: `proibir_slot` (`demanda_id` + `slot_id` ou `dia`). Tipo desconhecido => erro.
- `restricoes_soft`: `minimizar_janelas` — penaliza slots vazios entre a primeira e a última
  aula do dia de cada professor (adjacência por `ordem` dentro do dia). Peso: `peso` da
  restrição, senão `pesos.janelas`, senão 100. Tipos soft desconhecidos viram aviso em `/validate`.
  - `evitar_disciplina_slot` — `{"tipo":"evitar_disciplina_slot","peso":50,"materia_id":4,"dia":5,"ordem":5}`
    (`dia`, `ordem` e/ou `slot_id`; ao menos um): soma `peso * x` das aulas da matéria nos slots
    que casam. Não proíbe. Peso: `peso`, senão `pesos.evitar_disciplina_slot`, senão 50.
  - `concentrar_disciplina` — `{"tipo":"concentrar_disciplina","peso":50,"materia_id":4,"turma_id":7}`
    (`turma_id` opcional = todas as turmas da matéria): soma `peso * nº de dias distintos` usados
    pela matéria em cada turma. Peso: `peso`, senão `pesos.concentrar_disciplina`, senão 50.
  - `materia_id` ausente ou sem demandas (ou `turma_id` sem demandas) => aviso em `/validate`
    e a soft é ignorada/sem efeito. Todos os termos soft entram no mesmo `Minimize`.
- `metricas.janelas_total` é recalculado a partir da solução (independe do objetivo).

## Testes

```bash
pytest -q
```
