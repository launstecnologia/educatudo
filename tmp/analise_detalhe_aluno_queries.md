# Análise de Queries — Admin: Detalhe do Aluno
**Arquivo:** `app/Services/AdminStudentProfileService.php` (1.024 linhas)  
**Rota:** `GET /admin/students/{id}`  
**Método principal:** `getStudentProfile(int $id)`

---

## Veredicto direto

**Não condiz com rapidez.** Um único clique no detalhe do aluno dispara entre **22 e 35 queries sequenciais** ao banco, além de queries DDL (`SHOW TABLES`, `INFORMATION_SCHEMA`) executadas sem cache. Sistemas que abrem em menos de 1 segundo com dados igualmente complexos fazem isso resolvendo tudo em 5–8 queries com JOINs e agregações — não em 20+ roundtrips.

Com o `innodb_buffer_pool_size` atual de **128 MB** (enquanto o banco tem 700 MB+ de dados quentes), cada query extra vai ao disco. O efeito é multiplicado.

---

## Mapa completo de queries por ordem de execução

| # | Linha | Bloco | SQL resumido | Avaliação |
|---|---|---|---|---|
| 1 | 50 | Dados do aluno | `SELECT a.* + JOIN turmas + JOIN series + subquery GROUP_CONCAT responsaveis` | ✅ Bem montado |
| 2 | 92 | Número de chamada | `SELECT numero_chamada FROM alunos_turma_chamada WHERE aluno_id + turma_id + ano_id` | ⚠️ Poderia ser JOIN na query 1 |
| 3 | 119 | Responsáveis | `SELECT r.* FROM alunos_responsaveis JOIN responsaveis` | ⚠️ Parcialmente duplica a subquery GROUP_CONCAT da query 1 |
| 4 | 180 | Stats combinadas | `SELECT (8 subqueries escalares: COUNT jornadas, COUNT exercícios, AVG exercícios, COUNT redações x2, AVG redações, COUNT conversas, COUNT mensagens)` | ✅ 8 subqueries em 1 roundtrip — melhor que N+1 |
| 5 | 266 | Listas IA | `SELECT COUNT(DISTINCT) FROM listas_personalizadas_sessoes WHERE status='finalizado'` | ⚠️ 1 roundtrip para 1 número — poderia estar na query 4 |
| 6 | 273 | Flashcards | `SELECT COUNT(*) FROM flashcard_explicacoes` | ⚠️ Idem |
| 7 | 281 | Mural total | `SELECT COUNT(*) FROM mural_recados WHERE enviar_para_todos=1 OR EXISTS(...)` | ⚠️ Idem |
| 8 | 291 | Mural vistos | `SELECT COUNT(*) FROM mural_recados_vistos WHERE aluno_id` | ⚠️ Idem |
| 9 | 316 | Conversas Tudinha | `SELECT cc.* + 5 subqueries correlacionadas por linha` | 🔴 **CRÍTICO** — multiplica por número de conversas |
| 10 | 348 | Mensagens das conversas | `SELECT mc.* FROM tudinha_mensagens WHERE conversa_id IN (N ids)` | ✅ Correto — resolve N+1 das mensagens |
| 11 | 384 | Redações | `SELECT r.* FROM redacoes WHERE aluno_id LIMIT 50` | ✅ OK |
| 12 | 421 | Histórico de turmas | `SELECT FROM alunos_turmas_historico JOIN turmas` | ✅ OK |
| 13 | 435 | Ocorrências | `SELECT FROM alunos_ocorrencias JOIN usuarios GROUP BY o.id LIMIT 100` | ✅ OK |
| 14 | 453 | Jornadas concluídas | `SELECT j.id, j.titulo, MAX(data_conclusao) GROUP BY j.id` | ✅ OK |
| 15 | 478 | Histórico de acesso | `SELECT login_at FROM alunos_sessoes_acesso LIMIT 50` | ✅ OK |
| 16 | 496 | Verifica tabela matrícula | `SHOW TABLES LIKE 'matricula'` via `temTabela()` | ⚠️ DDL sem cache Redis |
| 17 | 498 | Matrículas | `SELECT m.* JOIN turmas JOIN ano_letivo JOIN curso` | ✅ OK |
| 18 | 509 | Turmas disponíveis | `SELECT t.id, t.nome FROM turmas WHERE ativo=1` | ⚠️ Lista global, poderia ser cacheada |
| 19 | 516 | Anos letivos | `SELECT id, ano FROM ano_letivo WHERE ativo=1` | ⚠️ Dado estático, poderia ser cacheado |
| 20 | 524 | Status do aluno | `SELECT ativo, status FROM alunos WHERE id=:id` | 🔴 **REDUNDANTE** — aluno já está em memória da query 1 |
| 21 | 556 | Verifica tabelas boletim | `SHOW TABLES LIKE 'boletim_resultados_gerados'` + `SHOW TABLES LIKE 'boletim_regras'` | ⚠️ 2 DDLs sem cache |
| 22 | 572 | Séries ativas | `SELECT DISTINCT turma_id, serie_id FROM matricula JOIN turmas JOIN serie` | ✅ OK |
| 23 | 655 | Catálogo de regras | `SELECT FROM boletim_regras WHERE ativo=1 AND vis_coordenacao=1 LIMIT 500` | ⚠️ Até 500 linhas para filtrar em PHP depois |
| 24 | 675 | Contagem de componentes | `SELECT regra_id, COUNT(*) FROM boletim_componentes GROUP BY regra_id` | ✅ Bem resolvido em 1 query |
| 25 | 729 | Fallback regras notas | `SELECT FROM boletim_regras WHERE ativo=1 LIMIT 200` | 🔴 **Potencial dupla execução** da query 23 |
| 26+ | 933–984 | Verificações de schema | `SHOW TABLES LIKE` + `INFORMATION_SCHEMA.COLUMNS` por tabela | 🔴 DDL a cada request, sem cache |

**Total por cenário:**
- Aluno novo, sem conversas Tudinha, sem matrículas: **~18 queries**
- Aluno ativo com 15 conversas Tudinha + matrícula: **~28–35 queries**
- Com fallback de stats (query 4 falha): **+8 queries individuais extras**

---

## Os 5 problemas mais graves — com SQL correto

---

### Problema 1 — 5 subqueries correlacionadas nas conversas Tudinha (linha 316) 🔴

**Por que é crítico:** cada linha retornada pela query principal executa 5 subqueries filhas. Com 15 conversas = 75 subqueries extras embutidas. Com 50 conversas (limite) = 250 subqueries.

**Hoje (ruim):**
```sql
-- Executado 1 vez, mas cada linha dispara 5 subqueries internas
SELECT cc.*,
    (SELECT COUNT(*)   FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id)                              AS total_mensagens,
    (SELECT COUNT(*)   FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id AND mc.is_ia = 0)            AS total_perguntas,
    (SELECT COUNT(*)   FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id AND mc.is_ia = 1)            AS total_respostas,
    (SELECT mc.mensagem  FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id ORDER BY mc.created_at DESC LIMIT 1) AS ultima_mensagem,
    (SELECT mc.created_at FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id ORDER BY mc.created_at DESC LIMIT 1) AS ultima_mensagem_data
FROM tudinha_conversas cc
WHERE cc.aluno_id = :id AND cc.excluida = 0
ORDER BY cc.updated_at DESC
LIMIT 50;
```

**Correto — 1 query com JOIN e agregação:**
```sql
SELECT
    cc.*,
    COALESCE(agg.total_mensagens, 0)  AS total_mensagens,
    COALESCE(agg.total_perguntas, 0)  AS total_perguntas,
    COALESCE(agg.total_respostas, 0)  AS total_respostas,
    ult.ultima_mensagem,
    ult.ultima_mensagem_data
FROM tudinha_conversas cc
LEFT JOIN (
    SELECT
        conversa_id,
        COUNT(*)         AS total_mensagens,
        SUM(is_ia = 0)   AS total_perguntas,
        SUM(is_ia = 1)   AS total_respostas
    FROM tudinha_mensagens
    GROUP BY conversa_id
) agg ON agg.conversa_id = cc.id
LEFT JOIN (
    SELECT m1.conversa_id, m1.mensagem AS ultima_mensagem, m1.created_at AS ultima_mensagem_data
    FROM tudinha_mensagens m1
    INNER JOIN (
        SELECT conversa_id, MAX(created_at) AS max_ts
        FROM tudinha_mensagens
        GROUP BY conversa_id
    ) m2 ON m2.conversa_id = m1.conversa_id AND m2.max_ts = m1.created_at
) ult ON ult.conversa_id = cc.id
WHERE cc.aluno_id = :id AND cc.excluida = 0
ORDER BY cc.updated_at DESC
LIMIT 50;
```

**Índices necessários para essa query ser rápida:**
```sql
-- Se não existirem, criar:
ALTER TABLE tudinha_conversas  ADD INDEX IF NOT EXISTS idx_aluno_excluida_updated (aluno_id, excluida, updated_at);
ALTER TABLE tudinha_mensagens  ADD INDEX IF NOT EXISTS idx_conversa_ts_ia (conversa_id, created_at, is_ia);
```

**Ganho:** de 75–250 subqueries para 1 query. Diferença estimada: 800ms → 30ms só nesse bloco.

---

### Problema 2 — 4 queries individuais para 4 COUNTs (linhas 266–291) ⚠️

**Por que é ruim:** 4 roundtrips de rede para buscar 4 números que cabem em 1 query. Cada roundtrip custa 3–10ms de latência ao banco remoto.

**Hoje (ruim):**
```sql
-- Query 5: roundtrip 1
SELECT COUNT(DISTINCT sep.id) as total
FROM listas_personalizadas_sessoes sep
WHERE sep.aluno_id = :id AND sep.status = 'finalizado';

-- Query 6: roundtrip 2
SELECT COUNT(*) as total FROM flashcard_explicacoes WHERE aluno_id = :id;

-- Query 7: roundtrip 3
SELECT COUNT(*) as total FROM mural_recados r
WHERE (r.enviar_para_todos = 1 OR EXISTS (...)) AND CURDATE() <= r.data_sai_mural;

-- Query 8: roundtrip 4
SELECT COUNT(*) as total FROM mural_recados_vistos WHERE aluno_id = :id;
```

**Correto — fundido nas subqueries escalares da query 4 (linha 180):**
```sql
SELECT
    -- já existem na query 4:
    (SELECT COUNT(DISTINCT jornada_id) FROM jornadas_progresso_alunos WHERE aluno_id = :id1 AND status = 'concluido')           AS jornadas_concluidas,
    (SELECT COUNT(DISTINCT h.id)       FROM exercicios_historico h WHERE h.aluno_id = :id2)                                     AS exercicios_resolvidos,
    (SELECT AVG(percentual_acerto)     FROM exercicios_historico WHERE aluno_id = :id3)                                         AS media_exercicios,
    (SELECT COUNT(*)                   FROM redacoes WHERE aluno_id = :id4)                                                     AS redacoes_total,
    (SELECT COUNT(*)                   FROM redacoes WHERE aluno_id = :id5 AND (corrigida_em IS NOT NULL OR nota IS NOT NULL))   AS redacoes_corrigidas,
    (SELECT AVG(COALESCE(nota,nota_final)) FROM redacoes WHERE aluno_id = :id6 AND nota IS NOT NULL)                            AS media_redacoes,
    (SELECT COUNT(*)                   FROM tudinha_conversas WHERE aluno_id = :id7 AND excluida = 0)                           AS conversas_total,
    (SELECT COUNT(*)                   FROM tudinha_mensagens mc JOIN tudinha_conversas cc ON mc.conversa_id = cc.id WHERE cc.aluno_id = :id8 AND cc.excluida = 0) AS interacoes_chat,

    -- NOVOS — adicionar aqui em vez de queries separadas:
    (SELECT COUNT(DISTINCT sep.id)     FROM listas_personalizadas_sessoes sep WHERE sep.aluno_id = :id9  AND sep.status = 'finalizado') AS exercicios_ia_resolvidos,
    (SELECT COUNT(*)                   FROM flashcard_explicacoes WHERE aluno_id = :id10)                                        AS total_flashcards,
    (SELECT COUNT(*)                   FROM mural_recados_vistos WHERE aluno_id = :id11)                                         AS mural_recados_vistos;
-- mural_recados_total fica separado pois depende de turma_id (filtro dinâmico)
```

**Ganho:** 3 roundtrips eliminados. Isso não parece muito, mas em 50 admins abrindo perfis simultaneamente = 150 queries a menos por segundo.

---

### Problema 3 — Query redundante: SELECT ativo/status do aluno (linha 524) 🔴

**Por que é crítico:** o aluno completo (incluindo `ativo` e `status`) já foi carregado na query 1, linha 50. Esta query re-busca dois campos que já estão em memória.

**Hoje (ruim):**
```php
// linha 524 — após syncAlunoStatusMatricula()
$statusRow = $this->db->fetch('SELECT ativo, status FROM alunos WHERE id = :id', ['id' => $id]);
if ($statusRow) {
    $aluno['ativo'] = $statusRow['ativo'];
    $aluno['status'] = $statusRow['status'];
}
```

**Correto:**
```php
// syncAlunoStatusMatricula() pode ter alterado o registro no banco.
// Basta re-ler os dois campos após o sync, sem uma query extra se
// o sync retornar os valores atualizados, ou invalidar e re-buscar
// somente quando o sync retornar que houve mudança:
$statusAtualizado = $this->controller->syncAlunoStatusMatricula((int) $id);
// Se syncAlunoStatusMatricula() retornar o array com os campos novos:
if (is_array($statusAtualizado)) {
    $aluno['ativo']  = $statusAtualizado['ativo']  ?? $aluno['ativo'];
    $aluno['status'] = $statusAtualizado['status'] ?? $aluno['status'];
}
// Zero queries extras.
```

**Ganho:** 1 roundtrip eliminado. Simples de corrigir.

---

### Problema 4 — DDL sem cache Redis (linhas 496, 556, 933) 🔴

**Por que é crítico:** `SHOW TABLES LIKE` e `SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS` são queries DDL — o MySQL precisa consultar o dicionário de dados interno, que tem lock próprio. Em carga alta, isso cria contenção. São executadas a cada clique em qualquer perfil de aluno.

**Hoje (ruim):**
```php
// linha 933 — executa em toda request
private function temTabela(string $tabela): bool {
    static $cache = []; // cache SOMENTE dentro do mesmo request PHP
    // ...
    $cache[$chave] = $this->db->fetch("SHOW TABLES LIKE :t", ['t' => $tabela]) !== false;
    return $cache[$chave];
}

// linha 972 — idem, somente cache intra-request
private function colunasDe(string $tabela): array {
    static $cache = [];
    // SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS ...
}
```

O `static $cache` existe mas só persiste dentro de 1 request. No próximo clique em outro aluno = tudo executa de novo.

**Correto — cache Redis com TTL:**
```php
private function temTabela(string $tabela): bool {
    $redisKey = 'schema:' . TENANT_SLUG . ':table:' . $tabela;
    
    // Tenta Redis primeiro
    try {
        $cached = \RedisCache::get($redisKey);
        if ($cached !== null) {
            return (bool) $cached;
        }
    } catch (\Throwable $e) {}

    // Executa DDL UMA vez e cacheia por 1h
    $existe = $this->db->fetch("SHOW TABLES LIKE :t", ['t' => $tabela]) !== false;
    
    try {
        \RedisCache::set($redisKey, $existe ? '1' : '0', 3600);
    } catch (\Throwable $e) {}

    return $existe;
}

// Mesmo padrão para colunasDe()
private function colunasDe(string $tabela): array {
    $redisKey = 'schema:' . TENANT_SLUG . ':cols:' . $tabela;
    
    try {
        $cached = \RedisCache::get($redisKey);
        if ($cached !== null) {
            return json_decode($cached, true) ?: [];
        }
    } catch (\Throwable $e) {}

    $rows = $this->db->fetchAll(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t",
        ['t' => $tabela]
    );
    $cols = [];
    foreach ($rows as $r) {
        $cols[(string) $r['COLUMN_NAME']] = true;
    }
    
    try {
        \RedisCache::set($redisKey, json_encode($cols), 3600);
    } catch (\Throwable $e) {}

    return $cols;
}
```

**Ganho:** DDLs passam de N execuções/minuto para 1 execução/hora por tenant.

---

### Problema 5 — Double fetch de boletim_regras em fallback (linhas 655 e 729) 🔴

**Por que é ruim:** quando `$boletimEventosNotas` fica vazio após o primeiro loop (linha 655), o código executa **outra query** em `boletim_regras` (linha 729) com filtros diferentes. No pior caso as duas queries são executadas e a segunda busca até 200 linhas sem filtro de série.

**Hoje:**
```php
// linha 655: primeira tentativa — até 500 regras
$rowsCatalogo = $this->db->fetchAll("SELECT ... FROM boletim_regras WHERE ativo=1 ... LIMIT 500");

// linha 729: se ainda vazio após filtrar $rowsCatalogo em PHP → segunda query
$rowsNotas = $this->db->fetchAll("SELECT ... FROM boletim_regras WHERE ativo=1 ... LIMIT 200");
```

**Correto — uma query parametrizada que já traz os dados certos:**
```sql
-- Filtrar por série/turma do aluno DIRETO no SQL, não em PHP
SELECT br.id, br.nome, br.codigo, br.updated_at,
       br.default_data_inicio, br.default_data_fim,
       br.bimestre, br.ano_letivo,
       COUNT(bc.id) AS componentes_qtd
FROM boletim_regras br
LEFT JOIN boletim_componentes bc ON bc.regra_id = br.id AND bc.ativo = 1
WHERE br.ativo = 1
  AND br.vis_coordenacao = 1
  AND br.exibir_em = 'notas'
  AND br.codigo IS NOT NULL AND br.codigo <> ''
  AND (
      -- regra sem restrição de série/turma = vale para todos
      (br.series_ids IS NULL OR br.series_ids = '' OR br.series_ids = '[]')
      -- OU regra que inclui a série do aluno
      OR JSON_CONTAINS(br.series_ids, CAST(:serie_id AS JSON))
      -- OU regra que inclui a turma do aluno
      OR JSON_CONTAINS(br.turmas_ids, CAST(:turma_id AS JSON))
  )
GROUP BY br.id
ORDER BY br.updated_at DESC, br.id DESC
LIMIT 100;
```

Isso elimina o fallback e o filtro PHP, produzindo apenas as regras relevantes ao aluno diretamente.

---

## Query ideal para o cabeçalho do aluno (queries 1+2 unificadas)

**Hoje:** 2 queries separadas (aluno na linha 50, número de chamada na linha 92).

**Correto — 1 query:**
```sql
SELECT
    a.*,
    t.nome                    AS turma_nome,
    t.serie_id                AS turma_serie_id,
    s.nome                    AS serie_nome,
    atc.numero_chamada,
    COALESCE(
        (SELECT GROUP_CONCAT(DISTINCT r2.nome ORDER BY r2.nome SEPARATOR ', ')
         FROM alunos_responsaveis ar2
         INNER JOIN responsaveis r2 ON r2.id = ar2.responsavel_id
         WHERE ar2.aluno_id = a.id AND ar2.ativo = 1),
        p.nome
    ) AS responsavel_nome
FROM alunos a
LEFT JOIN turmas                t   ON t.id = a.turma_id
LEFT JOIN series                s   ON s.id = t.serie_id
LEFT JOIN responsaveis          p   ON p.id = a.responsavel_id
LEFT JOIN alunos_turma_chamada  atc ON atc.aluno_id = a.id
                                   AND atc.turma_id = a.turma_id
                                   AND atc.ano_letivo_id = :ano_letivo_id
WHERE a.id = :id;
```

Exige apenas que `$anoLetivoIdChamada` seja resolvido antes (1 chamada ao método `resolverAnoLetivoIdParaTurma()` que já existe), unificando 2 queries em 1.

---

## Índices que faltam para essas queries funcionarem rápido

```sql
-- Verificar e criar apenas os que não existem

-- tudinha (Problema 1)
ALTER TABLE tudinha_conversas  ADD INDEX IF NOT EXISTS idx_aluno_excluida_updated (aluno_id, excluida, updated_at);
ALTER TABLE tudinha_mensagens  ADD INDEX IF NOT EXISTS idx_conversa_ts_ia (conversa_id, created_at, is_ia);

-- jornadas_progresso_alunos (query 4 / stats)
ALTER TABLE jornadas_progresso_alunos ADD INDEX IF NOT EXISTS idx_aluno_status (aluno_id, status, jornada_id);

-- exercicios_historico (query 4 / stats)
ALTER TABLE exercicios_historico ADD INDEX IF NOT EXISTS idx_aluno_acerto (aluno_id, percentual_acerto);

-- listas_personalizadas_sessoes (Problema 2)
ALTER TABLE listas_personalizadas_sessoes ADD INDEX IF NOT EXISTS idx_aluno_status (aluno_id, status);

-- mural_recados_vistos (Problema 2)
ALTER TABLE mural_recados_vistos ADD INDEX IF NOT EXISTS idx_aluno (aluno_id);

-- flashcard_explicacoes (Problema 2)
ALTER TABLE flashcard_explicacoes ADD INDEX IF NOT EXISTS idx_aluno (aluno_id);

-- alunos_turma_chamada (query cabeçalho unificada)
ALTER TABLE alunos_turma_chamada ADD INDEX IF NOT EXISTS idx_aluno_turma_ano (aluno_id, turma_id, ano_letivo_id);

-- alunos_sessoes_acesso (histórico de acesso)
ALTER TABLE alunos_sessoes_acesso ADD INDEX IF NOT EXISTS idx_aluno_login (aluno_id, login_at DESC);
```

---

## Plano de ação por prioridade

### Alta — impacto imediato, baixo risco

| # | O que fazer | Onde | Estimativa |
|---|---|---|---|
| 1 | Reescrever query das conversas Tudinha com JOIN + agregação | linha 316 | 2h |
| 2 | Mover listas_IA + flashcards + mural_vistos para dentro das subqueries escalares da query 4 | linha 266–291 | 1h |
| 3 | Remover `SELECT ativo, status` redundante (linha 524) — usar dados já em memória | linha 524 | 30min |
| 4 | Adicionar cache Redis nas funções `temTabela()` e `colunasDe()` com TTL 1h | linha 933–984 | 2h |
| 5 | Criar índices listados acima | banco | 30min (sem downtime) |

### Média — impacto moderado, requer mais cuidado

| # | O que fazer | Onde | Estimativa |
|---|---|---|---|
| 6 | Unificar queries 1+2 (aluno + número chamada) | linha 50 + 92 | 2h |
| 7 | Reescrever fallback duplo de boletim_regras para query parametrizada por série/turma | linha 655 + 729 | 3h |
| 8 | Cachear `ano_letivo WHERE ativo=1` e `turmas WHERE ativo=1` no Redis (TTL 5min) | linha 509, 516 | 1h |

### Baixa — cleanup futuro

| # | O que fazer |
|---|---|
| 9 | Refatorar `getStudentProfile()` para retornar seções independentes carregáveis via AJAX (boletim, ocorrências, histórico de turmas) — igual ao que já foi feito com as provas (linha 472) |
| 10 | Adicionar instrumentação de tempo por bloco (Logger com `microtime`) para medir impacto real em produção |

---

## Estimativa de melhoria

| Cenário | Hoje (estimado) | Após correções |
|---|---|---|
| Aluno simples, sem Tudinha | ~300–500ms | ~80–120ms |
| Aluno ativo, 15 conversas Tudinha | ~800ms–1.5s | ~100–180ms |
| `innodb_buffer_pool` ainda em 128 MB | Adicionar 200–400ms a todos | — |
| `innodb_buffer_pool` em 2 GB + correções | — | **< 100ms** no percentil 90 |

O maior ganho individual é a correção das subqueries correlacionadas das conversas Tudinha (Problema 1). O segundo maior ganho é o buffer pool (tratado no relatório geral de auditoria).
