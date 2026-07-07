# Auditoria de Performance — EducaTudo (educa_002_colag)
**Data:** 2026-06-30  
**Banco auditado:** `educa_002_colag` (tenant de produção)

---

## Resumo Executivo

Os 5 problemas críticos encontrados, por ordem de impacto:

1. **`innodb_buffer_pool_size` é 128 MB** — absurdamente pequeno para um banco com 540 MB só em `logs_auditoria` + 130 MB em `jornadas_exercicios_auditoria`. O MySQL faz I/O de disco a cada query de log. Impacto: alto, afeta todas as queries.

2. **`slow_query_log` desligado** — sem visibilidade das queries lentas em produção. Impede diagnóstico contínuo.

3. **`professor_questoes_api` tem 473 MB para 3.884 linhas** — média de 50 KB de JSON por linha (`source_payload`) e máximo de 1,2 MB. Isso força leituras de páginas enormes para qualquer scan, mesmo filtrado. Sem índices de texto e sem separação do payload de exibição. A tabela deve crescer muito mais.

4. **`StudentController::getProvasRealizadasAlunoComBloco` executa 11 `SHOW COLUMNS` por requisição** — gerado toda vez que o dashboard do aluno é carregado. `SHOW COLUMNS` não é cacheado a nível de query cache no MySQL 8 e gera round-trips desnecessários.

5. **`logs_auditoria` com 1,19 M linhas e 542 MB sem política de retenção visível** — `AUTO_INCREMENT` já está em 1.426.164 e crescendo. Nenhum índice composto cobre a consulta mais comum (`user_id + created_at`). A tabela vai degradar à medida que crescer.

---

## 1. Análise de Tabelas e Índices

### Tabelas com problema crítico

| Tabela | Linhas | Tamanho dados | Tamanho índices | Problema |
|---|---|---|---|---|
| `logs_auditoria` | 1.194.011 | 542 MB | 148 MB | Sem índice composto `(user_id, created_at)`, sem retenção |
| `professor_questoes_api` | 3.884 | 473 MB | 0,5 MB | JSON de até 1,2 MB por linha em `source_payload`, sem índice full-text |
| `jornadas_exercicios_auditoria` | 444.747 | 130 MB | 84 MB | Sem índice composto `(aluno_id, created_at)` — os individuais criam fat index |
| `jornadas_progresso_alunos` | 440.391 | 37 MB | 103 MB | AUTO_INCREMENT em 476.586; índices redundantes (ver seção 2) |

### Tabelas com problema moderado

| Tabela | Problema |
|---|---|
| `alunos_sessoes_acesso` | 193.237 linhas; registros com `status='ativo'` antigos nunca purgados (sem TTL); `AUTO_INCREMENT` em 213.748 |
| `listas_personalizadas_respostas` | 25.407 linhas, sem índice composto `(sessao_id, aluno_id)` — queries de sessão fazem 2 lookups independentes |
| `provas_blocos_notas_lancadas` | 13.265 linhas; índice `idx_bloco_prof_mat` cobre 3 colunas mas a UNIQUE `uq_bloco_prof_mat_tur_aluno` cobre 5 — a leitura por `(bloco_id, aluno_id)` faz scan no índice errado |
| `mural_recados_vistos` | 32.278 linhas; UNIQUE `uniq_mural_aluno` já cobre `(mural_recado_id, aluno_id)` mas o índice simples `idx_mural_vistos_recado` é redundante com o UNIQUE |
| `validacao_tokens_apps` | UNIQUE `uniq_token` e índice `idx_token` no mesmo campo `token` — duplicado |
| `notificacoes_destinatarios` | Sem índice composto `(destinatario_id, lida)` para a query "listar não lidas por usuário" |

### Tabelas ok

`matricula`, `turmas`, `alunos`, `provas_respostas`, `provas_realizacoes`, `jornadas_tempo_alunos`, `redacoes_orientadas_entregas`, `redacoes_orientadas_correcoes`, `carteira_movimentacoes`.

---

## 2. Problemas de Indexação

### Índices faltando (alto impacto)

**`logs_auditoria` — índice composto `(user_id, created_at)`**

- Query afetada: qualquer busca de log de um usuário em range de data, que é o uso mais comum de auditoria.
- Hoje existem índices separados em `user_id` e `created_at`, mas o otimizador só usa um deles, fazendo filesort no outro.
- SQL:
```sql
ALTER TABLE logs_auditoria ADD INDEX idx_logs_user_data (user_id, created_at);
```

**`logs_auditoria` — índice composto `(user_role, created_at)`**
- Para listagens de admin "ver todos os logins de professores nas últimas 24h".
```sql
ALTER TABLE logs_auditoria ADD INDEX idx_logs_role_data (user_role, created_at);
```

**`jornadas_exercicios_auditoria` — índice composto `(aluno_id, jornada_id, created_at)`**
- Os índices individuais `idx_jornadas_auditoria_aluno` e `idx_jornadas_auditoria_jornada` existem, mas queries que filtram `aluno_id AND jornada_id` usam apenas um. Um composto elimina a segunda travessia.
```sql
ALTER TABLE jornadas_exercicios_auditoria ADD INDEX idx_jea_aluno_jornada_data (aluno_id, jornada_id, created_at);
```

**`notificacoes_destinatarios` — índice composto `(destinatario_id, lida)`**
- Query mais frequente: "buscar notificações não lidas de um usuário específico".
```sql
ALTER TABLE notificacoes_destinatarios ADD INDEX idx_notif_dest_nao_lida (destinatario_id, lida);
```

**`listas_personalizadas_respostas` — índice composto `(sessao_id, aluno_id)`**
- `sessao_id` e `aluno_id` existem como índices separados; queries de resultado de sessão filtram os dois juntos.
```sql
ALTER TABLE listas_personalizadas_respostas ADD INDEX idx_lpr_sessao_aluno (sessao_id, aluno_id);
```

**`alunos_sessoes_acesso` — índice em `(aluno_id, ultima_atividade_at)` para limpeza de sessões expiradas**
- Cron ou query de expiração vai fazer full scan sem esse índice.
```sql
ALTER TABLE alunos_sessoes_acesso ADD INDEX idx_asa_aluno_ultima (aluno_id, ultima_atividade_at);
```

**`provas_blocos_notas_lancadas` — índice composto `(bloco_id, aluno_id)`**
- Query de boletim: "nota do aluno X no bloco Y" não é coberta por nenhum índice eficiente (a UNIQUE cobre 5 colunas).
```sql
ALTER TABLE provas_blocos_notas_lancadas ADD INDEX idx_pbni_bloco_aluno (bloco_id, aluno_id);
```

### Índices redundantes ou ineficientes

| Tabela | Índice redundante | Motivo |
|---|---|---|
| `validacao_tokens_apps` | `idx_token` | `UNIQUE KEY uniq_token (token)` já é um índice B-tree; o `idx_token` duplica cobertura. |
| `mural_recados_vistos` | `idx_mural_vistos_recado` | `UNIQUE KEY uniq_mural_aluno (mural_recado_id, aluno_id)` cobre a coluna `mural_recado_id` no prefixo esquerdo — o índice simples nunca será preferido. |
| `jornadas_progresso_alunos` | `aluno_id`, `jornada_id`, `aula_id`, `exercicio_id`, `modulo_id`, `exercicio_modulo_id` (6 índices simples) | Todos estão cobertos pelos índices compostos `idx_jpa_jornada_aluno_status` e `idx_jpa_aluno_tipo_jornada_exmod_pont`. Os 6 índices simples aumentam o custo de INSERT/UPDATE sem benefício de leitura nas queries compostas. Manter só se houver queries que filtram coluna única isolada — verificar antes de dropar. |

```sql
-- Dropar após confirmar que não há queries que filtram token sem UNIQUE
ALTER TABLE validacao_tokens_apps DROP INDEX idx_token;

-- Dropar após confirmar uso
ALTER TABLE mural_recados_vistos DROP INDEX idx_mural_vistos_recado;
```

---

## 3. Problemas no Código PHP

### 3.1 — 11 `SHOW COLUMNS` por requisição de dashboard do aluno

**Arquivo:** `src/app/Controllers/User/StudentController.php`, linhas 372–382  
**Método:** `getProvasRealizadasAlunoComBloco()`

O método executa **11 queries `SHOW COLUMNS`** antes de construir o SQL principal, a cada chamada. `SHOW COLUMNS` é uma instrução DDL — não é cacheado no plan cache do MySQL 8, gera um round-trip completo ao servidor por chamada, e pior: a função é chamada no carregamento do dashboard do aluno.

```php
// PROBLEMA — executado toda vez que o aluno abre o dashboard:
try { $hasProvasMateriaId = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas LIKE 'materia_id'")); } catch (\Exception $e) {}
try { $hasRealizacoesMateria = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'materia'")); } catch (\Exception $e) {}
// ... mais 9 iguais
```

**Causa raiz:** o código mantém compatibilidade com múltiplas versões de schema em vez de padronizar a estrutura via migration.

**Solução correta:** executar essas verificações uma vez no boot do tenant (ou num Service com resultado cacheado em Redis por 1h) e armazenar as flags em uma propriedade estática ou no Redis. O schema não muda em runtime.

```php
// Criar src/app/Services/SchemaCapabilitiesService.php
class SchemaCapabilitiesService {
    private static ?array $cache = null;

    public static function get(Database $db): array {
        if (self::$cache !== null) return self::$cache;
        $key = 'schema_caps_' . (defined('TENANT_SLUG') ? TENANT_SLUG : 'default');
        $cached = RedisCache::get($key);
        if ($cached) return self::$cache = $cached;

        $caps = [
            'provas_materia_id'             => !empty($db->fetchAll("SHOW COLUMNS FROM provas LIKE 'materia_id'")),
            'realizacoes_materia'           => !empty($db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'materia'")),
            'realizacoes_disciplina'        => !empty($db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'disciplina'")),
            'realizacoes_area_conhecimento' => !empty($db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'area_conhecimento'")),
            'realizacoes_questoes_total'    => !empty($db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'questoes_total'")),
            'realizacoes_total_questoes'    => !empty($db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'total_questoes'")),
            'realizacoes_qtd_questoes'      => !empty($db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'qtd_questoes'")),
            'realizacoes_questoes_corretas' => !empty($db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'questoes_corretas'")),
            'realizacoes_acertos'           => !empty($db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'acertos'")),
            'realizacoes_qtd_acertos'       => !empty($db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'qtd_acertos'")),
            'respostas_correta'             => !empty($db->fetchAll("SHOW COLUMNS FROM provas_respostas LIKE 'correta'")),
        ];
        RedisCache::set($key, $caps, 3600);
        return self::$cache = $caps;
    }
}
```

### 3.2 — Corretude lógica: jornadas filtradas por JSON em PHP

**Arquivo:** `src/app/Controllers/User/StudentController.php`, linhas 815–828

```php
$jornadasAll = $this->db->fetchAll(
    "SELECT ... FROM jornadas j ...
     WHERE (j.turma_id = :turma_id OR (j.estrutura IS NOT NULL AND TRIM(j.estrutura) != ''))
       AND (j.ativo = 1 OR j.ativo IS NULL)
     ORDER BY j.created_at DESC",
    ['turma_id' => $turmaId]
);
// Depois filtra em PHP com json_decode para checar turmas_selecionadas
```

A query traz **todas as jornadas com `estrutura` preenchida** (independente de turma) para filtrar em PHP. Se a escola tiver 500 jornadas com estrutura, tudo vai para memória PHP para filtrar talvez 5.

**Solução com MySQL 8 JSON_CONTAINS:**
```sql
SELECT j.id, j.titulo, j.status, j.materia_id, j.turma_id, j.estrutura, m.nome as materia_nome
FROM jornadas j
LEFT JOIN materias m ON j.materia_id = m.id
WHERE (j.ativo = 1 OR j.ativo IS NULL)
  AND (
    j.turma_id = :turma_id
    OR (
      j.estrutura IS NOT NULL
      AND JSON_VALID(j.estrutura) = 1
      AND JSON_CONTAINS(
            JSON_EXTRACT(j.estrutura, '$.turmas_selecionadas'),
            CAST(:turma_id2 AS JSON)
          )
    )
  )
ORDER BY j.created_at DESC
```

Ou, melhor a longo prazo, normalizar `turmas_selecionadas` numa tabela `jornadas_turmas(jornada_id, turma_id)` com índice em `turma_id`.

### 3.3 — Subconsulta correlacionada no fallback de acertos (`provas_respostas`)

**Arquivo:** `src/app/Controllers/User/StudentController.php`, linha 402

```php
if ($hasProvasRespostasCorreta) {
    $acertosParts[] = "(SELECT COUNT(*) FROM provas_respostas prs WHERE prs.prova_id = pr.prova_id AND prs.aluno_id = pr.aluno_id AND prs.correta = 1)";
}
```

Esta subconsulta correlacionada é executada **uma vez por linha de `provas_realizacoes`** retornada (até 100 linhas). Com 33.801 linhas em `provas_respostas`, cada subconsulta pode percorrer muitas linhas.

A coluna `correta` existe em `provas_respostas` (confirmado no `CREATE TABLE` — `correta tinyint(1)`). O `COALESCE` resolve isto quando as colunas de acertos existem, mas se elas não existirem em algum tenant, a subconsulta vai ser acionada.

**Solução:** garantir que a migration que adiciona `acertos` em `provas_realizacoes` foi aplicada em todos os tenants e remover o fallback de subconsulta correlacionada.

### 3.4 — `SELECT *` com colunas LONGTEXT desnecessárias

| Arquivo | Query | Colunas LONGTEXT trazidas | Impacto |
|---|---|---|---|
| `EssaySubmission.php:61` | `SELECT * FROM redacoes_orientadas_entregas WHERE ...` | `content_text`, `ocr_text`, `ocr_text_structure_json`, `ocr_layout_json` (4 × LONGTEXT) | Transfere o texto da redação + OCR JSON quando só precisa de `id, status, proposal_id` |

```php
// Substituir em findByProposalAndStudent (usado para checar se submissão existe):
"SELECT id, proposal_id, student_id, status, submitted_at, updated_at
 FROM redacoes_orientadas_entregas
 WHERE proposal_id = :proposal_id AND student_id = :student_id
 ORDER BY id DESC LIMIT 1"
```

### 3.5 — `ClassRoom::supportsCursos()` executa 3–5 queries DDL por request

**Arquivo:** `src/app/Models/Education/ClassRoom.php`, linhas 397–430

O método faz lazy init com cache em propriedade de instância (`$this->cursosSupported`), o que é útil apenas se a instância for reutilizada. Em PHP-FPM, cada request cria um novo `ClassRoom` — as queries `SHOW TABLES LIKE 'cursos'`, `SHOW TABLES LIKE 'tipos_curso'`, `SHOW COLUMNS FROM turmas LIKE 'curso_id'` são executadas toda request que use ClassRoom. O método `supportsCursoNovo()` adiciona mais 2 queries DDL.

**Total:** até 5 queries DDL por request que carrega listagem de turmas.

**Solução:** migrar para o mesmo `SchemaCapabilitiesService` com Redis sugerido em 3.1.

### 3.6 — `EssaySubmission::ensureStructuredColumns()` executa INFORMATION_SCHEMA + potencial `ALTER TABLE` em produção

**Arquivo:** `src/app/Models/Essays/EssaySubmission.php`, linhas 128–161

O constructor verifica via `INFORMATION_SCHEMA.COLUMNS` se colunas existem toda vez que `EssaySubmission` é instanciado. Protegido por `static $structuredColumnsEnsured` por processo FPM (não por request), mas a verificação custa 2 queries `INFORMATION_SCHEMA` na primeira request de cada worker. Se as colunas não existirem, executa `ALTER TABLE` diretamente em produção durante uma request HTTP.

**Solução:** remover o `ensureStructuredColumns()` do constructor. As colunas devem existir via migration aplicada pelo painel Master. Deixar o código quebrar explicitamente se a migration não foi aplicada — é mais fácil de diagnosticar do que um `ALTER TABLE` silencioso em produção.

---

## 4. Configuração MySQL

### Parâmetros atuais vs recomendados

| Variável | Valor atual | Valor recomendado | Impacto |
|---|---|---|---|
| `innodb_buffer_pool_size` | **128 MB** | **2–4 GB** (dependendo da RAM do host) | CRÍTICO — banco tem 800+ MB só nas top 3 tabelas; quase tudo vai a disco |
| `slow_query_log` | **OFF** | **ON** | Alto — sem visibilidade de queries lentas em produção |
| `long_query_time` | 10s | **1s** (ou 0.5s) | Alto — queries de 2–9s passam despercebidas |
| `max_connections` | 151 | 151 (ok para PHP-FPM com pool) | OK se usando persistent connections ou pool |
| `tmp_table_size` | 16 MB | **64 MB** | Médio — subqueries agrupadas em `jornadas_progresso_alunos` excedem facilmente 16MB |
| `max_heap_table_size` | default 16 MB | **64 MB** | Médio — deve ser igual ao `tmp_table_size` |
| `join_buffer_size` | 256 KB | **4 MB** | Baixo — joins sem índice se beneficiam |

**Status atual:**
- `Slow_queries`: 16 (desde o último restart — número artificialmente baixo com log desligado)
- `Questions`: 27.408.466 — banco está sendo usado ativamente
- `Threads_connected`: 1 no momento da consulta (carga baixa)

### Recomendações de configuração

```ini
# /etc/mysql/conf.d/educatudo.cnf (ou my.cnf dentro do container)

[mysqld]
# Buffer pool — usar 70-75% da RAM disponível no host (mínimo 2G para este workload)
innodb_buffer_pool_size = 2G
innodb_buffer_pool_instances = 2   # 1 instância por GB

# Slow query log
slow_query_log = ON
slow_query_log_file = /var/lib/mysql/slow.log
long_query_time = 1
log_queries_not_using_indexes = ON

# Temp tables
tmp_table_size = 64M
max_heap_table_size = 64M

# Join buffer
join_buffer_size = 4M
```

---

## 5. Problema específico: `professor_questoes_api` com 473 MB / 3.884 linhas

A coluna `source_payload JSON` tem **média de 50 KB e máximo de 1,2 MB por linha**. Isso é o payload bruto de uma API externa de banco de questões.

**Problema:** qualquer query que faça scan (ex: `WHERE materia = 'Matemática'`) precisa ler 473 MB de dados para filtrar 3.884 linhas porque o InnoDB armazena os dados em páginas junto com o JSON enorme.

**Solução — separar o payload pesado:**

```sql
CREATE TABLE professor_questoes_api_payload (
  questao_id BIGINT UNSIGNED NOT NULL,
  source_payload JSON,
  PRIMARY KEY (questao_id),
  CONSTRAINT fk_pqap_questao FOREIGN KEY (questao_id)
    REFERENCES professor_questoes_api(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO professor_questoes_api_payload (questao_id, source_payload)
SELECT id, source_payload FROM professor_questoes_api WHERE source_payload IS NOT NULL;

-- Verificar contagens antes de dropar
-- SELECT COUNT(*) FROM professor_questoes_api_payload;
ALTER TABLE professor_questoes_api DROP COLUMN source_payload;
```

Após a separação, a tabela principal reduz de 473 MB para ~5 MB, viabilizando scans e indexação.

---

## 6. Política de retenção de logs

**`logs_auditoria`** — 1,19 M linhas, 542 MB, sem retenção visible. O AUTO_INCREMENT em 1.426.164 cresce continuamente.

**`jornadas_exercicios_auditoria`** — 444K linhas, 130 MB.

**`alunos_sessoes_acesso`** — 193K linhas com sessões finalizadas/expiradas antigas.

```sql
-- Purgar logs com mais de 90 dias (executar em loop até ROW_COUNT() = 0)
DELETE FROM logs_auditoria
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
LIMIT 50000;

-- Purgar auditoria de exercícios > 180 dias
DELETE FROM jornadas_exercicios_auditoria
WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
LIMIT 50000;

-- Purgar sessões finalizadas > 30 dias
DELETE FROM alunos_sessoes_acesso
WHERE status IN ('finalizado', 'expirado')
  AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
LIMIT 10000;
```

---

## 7. Plano de Ação

### Prioridade Alta (impacto imediato, sem risco)

1. **Ligar `slow_query_log`** — `SET GLOBAL slow_query_log = ON; SET GLOBAL long_query_time = 1;` sem restart.
2. **Criar índices compostos em `logs_auditoria`** — `(user_id, created_at)` e `(user_role, created_at)`.
3. **Criar índice composto em `jornadas_exercicios_auditoria`** — `(aluno_id, jornada_id, created_at)`.
4. **Criar índice composto em `notificacoes_destinatarios`** — `(destinatario_id, lida)`.
5. **Purgar sessões obsoletas em `alunos_sessoes_acesso`** — libera ~30–40 MB imediatamente.

### Prioridade Média (próximos 30 dias)

6. **Aumentar `innodb_buffer_pool_size` para 2 GB** — requer restart do container. Planejar janela de manutenção.
7. **Cachear flags de schema em Redis** — refatorar os 11 SHOW COLUMNS em `StudentController` e os 5 SHOW TABLES/COLUMNS em `ClassRoom` para um `SchemaCapabilitiesService` com cache Redis de 1h.
8. **Remover índices redundantes** — `validacao_tokens_apps.idx_token` e confirmar/dropar `mural_recados_vistos.idx_mural_vistos_recado`.
9. **Purgar `logs_auditoria`** — registros > 90 dias (potencial liberação de 400+ MB).
10. **Separar `source_payload` de `professor_questoes_api`** — reduzir a tabela de 473 MB para ~5 MB.

### Prioridade Baixa (backlog)

11. **Normalizar `jornadas.estrutura` JSON** — criar tabela `jornadas_turmas(jornada_id, turma_id)` e eliminar o filtro PHP sobre JSON.
12. **Remover `EssaySubmission::ensureStructuredColumns()`** do constructor.
13. **Criar índices faltantes em** `listas_personalizadas_respostas`, `provas_blocos_notas_lancadas`, `alunos_sessoes_acesso`.
14. **Implementar partitioning por `created_at` em `logs_auditoria`** para purge eficiente no futuro.

---

## 8. SQLs prontos para aplicar

```sql
-- ============================================================
-- ETAPA 1: Ligar slow query log (sem restart, efeito imediato)
-- ============================================================
SET GLOBAL slow_query_log = ON;
SET GLOBAL long_query_time = 1;
SET GLOBAL log_queries_not_using_indexes = ON;

-- ============================================================
-- ETAPA 2: Índices de alto impacto
--           MySQL 8 faz online DDL — não trava leituras,
--           apenas atrasa writes durante a fase final de swap.
--           Executar um por vez em horário de baixo uso.
-- ============================================================
ALTER TABLE logs_auditoria
  ADD INDEX idx_logs_user_data (user_id, created_at);

ALTER TABLE logs_auditoria
  ADD INDEX idx_logs_role_data (user_role, created_at);

ALTER TABLE jornadas_exercicios_auditoria
  ADD INDEX idx_jea_aluno_jornada_data (aluno_id, jornada_id, created_at);

ALTER TABLE notificacoes_destinatarios
  ADD INDEX idx_notif_dest_nao_lida (destinatario_id, lida);

ALTER TABLE listas_personalizadas_respostas
  ADD INDEX idx_lpr_sessao_aluno (sessao_id, aluno_id);

ALTER TABLE alunos_sessoes_acesso
  ADD INDEX idx_asa_aluno_ultima (aluno_id, ultima_atividade_at);

ALTER TABLE provas_blocos_notas_lancadas
  ADD INDEX idx_pbni_bloco_aluno (bloco_id, aluno_id);

-- ============================================================
-- ETAPA 3: Remover índices redundantes
-- ============================================================
ALTER TABLE validacao_tokens_apps DROP INDEX idx_token;
-- Confirmar uso antes:
-- EXPLAIN SELECT * FROM mural_recados_vistos WHERE mural_recado_id = 1;
-- Se usar uniq_mural_aluno, dropar:
-- ALTER TABLE mural_recados_vistos DROP INDEX idx_mural_vistos_recado;

-- ============================================================
-- ETAPA 4: Purge de dados obsoletos
--           Executar em loop até ROW_COUNT() = 0
-- ============================================================

-- Sessões antigas (seguro — dados operacionais, não analíticos)
DELETE FROM alunos_sessoes_acesso
WHERE status IN ('finalizado', 'expirado')
  AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
LIMIT 10000;

-- Logs de auditoria > 90 dias (confirmar política de retenção com o negócio)
DELETE FROM logs_auditoria
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
LIMIT 50000;

-- Auditoria de exercícios > 180 dias
DELETE FROM jornadas_exercicios_auditoria
WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
LIMIT 50000;

-- ============================================================
-- ETAPA 5: Separar payload pesado de professor_questoes_api
--           (executar em manutenção — pode demorar 2-5 min)
-- ============================================================
CREATE TABLE IF NOT EXISTS professor_questoes_api_payload (
  questao_id BIGINT UNSIGNED NOT NULL,
  source_payload JSON,
  PRIMARY KEY (questao_id),
  CONSTRAINT fk_pqap_questao FOREIGN KEY (questao_id)
    REFERENCES professor_questoes_api(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO professor_questoes_api_payload (questao_id, source_payload)
SELECT id, source_payload
FROM professor_questoes_api
WHERE source_payload IS NOT NULL;

-- Confirmar contagem antes de dropar:
-- SELECT COUNT(*) FROM professor_questoes_api_payload;
-- SELECT COUNT(*) FROM professor_questoes_api WHERE source_payload IS NOT NULL;
-- Se iguais, dropar:
ALTER TABLE professor_questoes_api DROP COLUMN source_payload;
```

---

## 🔬 Análise Cirúrgica: Admin → Detalhe do Aluno (`/admin/students/{id}`)

> **Veredicto direto:** não, o que existe hoje não condiz com rapidez. Um único clique no detalhe do aluno dispara entre **22 e 35 queries** ao banco, várias com subqueries correlacionadas que executam uma vez por linha de resultado. Sistemas que abrem em menos de 1 segundo com dados mais complexos fazem isso porque resolvem tudo em 1–3 queries com JOINs e agregações, não em 20+ roundtrips sequenciais.

---

### Mapa de queries executadas em `AdminStudentProfileService::getStudentProfile()`

| # | Arquivo:linha | O que faz | Problema |
|---|---|---|---|
| 1 | linha 50 | SELECT aluno + turma + série + responsáveis (subquery GROUP_CONCAT) | OK — bem montado |
| 2 | linha 92 | SELECT número chamada | Query separada desnecessária — poderia ser JOIN na query 1 |
| 3 | linha 119 | SELECT responsáveis completos | Duplica dados já buscados na query 1 via subquery |
| 4 | linha 180 | SELECT 8 COUNTs + 2 AVGs via 10 subqueries correlacionadas | 10 subqueries em 1 SQL — melhor que N+1 mas ainda pesado |
| 5 | linha 266 | COUNT listas_personalizadas | Query individual para 1 número |
| 6 | linha 273 | COUNT flashcard_explicacoes | Query individual para 1 número |
| 7 | linha 281 | COUNT mural_recados | Query individual para 1 número |
| 8 | linha 291 | COUNT mural_recados_vistos | Query individual para 1 número |
| 9 | linha 316 | SELECT conversas + **5 subqueries correlacionadas por linha** | Se aluno tem 20 conversas = 100 subqueries |
| 10 | linha 348 | SELECT todas as mensagens das conversas (IN com N ids) | OK — resolve N+1 das mensagens |
| 11 | linha 384 | SELECT redações com JOINs | OK |
| 12 | linha 421 | SELECT histórico de turmas | OK |
| 13 | linha 435 | SELECT ocorrências | OK |
| 14 | linha 453 | SELECT jornadas realizadas com GROUP BY | OK |
| 15 | linha 478 | SELECT histórico de acesso | OK |
| 16 | linha 498 | SELECT matrículas | OK |
| 17 | linha 509 | SELECT turmas disponíveis para matrícula | OK |
| 18 | linha 516 | SELECT anos letivos | Poderia ser cacheado — não muda durante a request |
| 19 | linha 524 | SELECT `ativo, status` do aluno | **Redundante** — aluno já foi carregado na query 1 |
| 20+ | linha 572 | SELECT séries ativas para boletim | OK |
| 21+ | linha 655 | SELECT catálogo de notas | OK |
| 22+ | linha 675 | SELECT competências do catálogo | N+1 por entrada do catálogo |
| 23+ | linha 729 | SELECT notas do aluno | OK |
| 24 | linha 941 | `SHOW TABLES LIKE 'logs_auditoria'` | DDL query a cada request — deveria ser cacheado |
| 25 | linha 972 | `INFORMATION_SCHEMA.COLUMNS` por tabela | DDL por tabela, cada clique |
| 26 | linha 1011 | SELECT 30 linhas de `logs_auditoria` (542 MB, sem índice útil) | Full scan em tabela de 1.2M linhas |

**Total mínimo: ~22 queries. Com aluno ativo (10+ conversas, boletim com 5+ catálogos): 30–35 queries.**

---

### Os 3 piores gargalos

#### Gargalo 1 — Subqueries correlacionadas no bloco de conversas Tudinha (linha 316)

**Problema:** 5 subqueries `SELECT COUNT(*)`/`SELECT mensagem` executam uma vez por conversa retornada. Com 20 conversas = 100 queries extras embutidas.

```sql
-- HOJE (ruim): 5 subqueries correlacionadas por linha
SELECT cc.*,
    (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id) as total_mensagens,
    (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id AND mc.is_ia = 0) as total_perguntas,
    (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id AND mc.is_ia = 1) as total_respostas,
    (SELECT mc.mensagem FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id ORDER BY mc.created_at DESC LIMIT 1) as ultima_mensagem,
    (SELECT mc.created_at FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id ORDER BY mc.created_at DESC LIMIT 1) as ultima_mensagem_data
FROM tudinha_conversas cc
WHERE cc.aluno_id = :id AND cc.excluida = 0
ORDER BY cc.updated_at DESC LIMIT 50;
```

```sql
-- CORRETO: 1 query com JOIN e agregação — MySQL resolve em memória
SELECT
    cc.*,
    COUNT(mc.id)                                          AS total_mensagens,
    SUM(mc.is_ia = 0)                                     AS total_perguntas,
    SUM(mc.is_ia = 1)                                     AS total_respostas,
    MAX(CASE WHEN mc.created_at = sub.ultima_data THEN mc.mensagem END) AS ultima_mensagem,
    sub.ultima_data                                        AS ultima_mensagem_data
FROM tudinha_conversas cc
LEFT JOIN tudinha_mensagens mc ON mc.conversa_id = cc.id
LEFT JOIN (
    SELECT conversa_id, MAX(created_at) AS ultima_data
    FROM tudinha_mensagens
    GROUP BY conversa_id
) sub ON sub.conversa_id = cc.id
WHERE cc.aluno_id = :id AND cc.excluida = 0
GROUP BY cc.id
ORDER BY cc.updated_at DESC
LIMIT 50;

-- Índice necessário para isso ser rápido:
ALTER TABLE tudinha_mensagens ADD INDEX idx_conversa_ts (conversa_id, created_at, is_ia);
ALTER TABLE tudinha_conversas  ADD INDEX idx_aluno_updated (aluno_id, excluida, updated_at);
```

**Ganho esperado:** de ~100 subqueries para 1 query. Em servidor com innodb_buffer_pool pequeno (128 MB atual), a diferença é de segundos.

---

#### Gargalo 2 — 4 queries individuais de COUNT que deveriam ser 1 (linhas 266–291)

**Problema:** 4 queries separadas para buscar 4 números de estatísticas simples.

```sql
-- HOJE (ruim): 4 roundtrips para 4 números
SELECT COUNT(DISTINCT sep.id) as total FROM listas_personalizadas_sessoes sep WHERE sep.aluno_id = :id AND sep.status = 'finalizado';
SELECT COUNT(*) as total FROM flashcard_explicacoes WHERE aluno_id = :id;
SELECT COUNT(*) as total FROM mural_recados r WHERE ...;
SELECT COUNT(*) as total FROM mural_recados_vistos WHERE aluno_id = :id;
```

```sql
-- CORRETO: 1 query, 4 números
SELECT
    (SELECT COUNT(DISTINCT sep.id)
     FROM listas_personalizadas_sessoes sep
     WHERE sep.aluno_id = :id1 AND sep.status = 'finalizado')        AS listas_finalizadas,

    (SELECT COUNT(*)
     FROM flashcard_explicacoes
     WHERE aluno_id = :id2)                                           AS flashcards_total,

    (SELECT COUNT(*)
     FROM mural_recados r
     WHERE r.enviar_para_todos = 1
        OR EXISTS (
            SELECT 1 FROM mural_recados_turmas rt
            WHERE rt.mural_recado_id = r.id AND rt.turma_id = :turma_id
        ))                                                             AS murais_total,

    (SELECT COUNT(*)
     FROM mural_recados_vistos
     WHERE aluno_id = :id3)                                           AS murais_vistos;
```

**Ganho:** 3 roundtrips eliminados por request.

---

#### Gargalo 3 — Query redundante + DDL a cada clique (linhas 524 e 941)

**Query redundante (linha 524):**
```sql
-- HOJE: busca aluno novamente após já ter sido carregado na linha 50
SELECT ativo, status FROM alunos WHERE id = :id;

-- CORRETO: usar $aluno['ativo'] e $aluno['status'] que já existem em memória.
-- Zero queries. Uma linha de PHP.
```

**DDL a cada request (linha 941 e 972):**
```sql
-- HOJE: executa a cada clique no perfil do aluno
SHOW TABLES LIKE 'logs_auditoria';
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'redacoes';
-- (repetido para várias tabelas)

-- CORRETO: cache Redis com TTL de 1h
// No PHP:
$cacheKey = 'schema:' . TENANT_SLUG . ':capabilities';
$caps = $redis->get($cacheKey);
if (!$caps) {
    $caps = $this->detectarCapabilities(); // faz os SHOW TABLES / INFORMATION_SCHEMA uma vez
    $redis->setex($cacheKey, 3600, json_encode($caps));
}
```

---

### Query unificada ideal para o "cabeçalho" do aluno

Em vez de 3 queries separadas para montar o topo da página (aluno, número chamada, responsável), uma query resolve tudo:

```sql
SELECT
    a.*,
    t.nome                          AS turma_nome,
    t.tipo_ensino                   AS turma_tipo_ensino,
    s.nome                          AS serie_nome,
    atc.numero_chamada,
    GROUP_CONCAT(
        DISTINCT r.nome
        ORDER BY r.nome
        SEPARATOR ', '
    )                               AS responsaveis_nomes
FROM alunos a
LEFT JOIN turmas          t   ON t.id = a.turma_id
LEFT JOIN series          s   ON s.id = t.serie_id
LEFT JOIN alunos_turma_chamada atc
          ON atc.aluno_id = a.id AND atc.turma_id = a.turma_id
LEFT JOIN responsaveis_alunos ra ON ra.aluno_id = a.id
LEFT JOIN responsaveis        r  ON r.id = ra.responsavel_id AND r.ativo = 1
WHERE a.id = :id
GROUP BY a.id;

-- Índices que essa query precisa (verificar se existem):
-- alunos:                 PRIMARY KEY (id) ✓
-- turmas:                 INDEX (id) ✓
-- series:                 INDEX (id) ✓
-- alunos_turma_chamada:   INDEX (aluno_id, turma_id) — provavelmente falta
-- responsaveis_alunos:    INDEX (aluno_id) — verificar
```

---

### Resumo: o que impede < 1 segundo hoje

| Causa | Impacto | Solução |
|---|---|---|
| `innodb_buffer_pool = 128 MB` com 700 MB+ de dados quentes | **Todo I/O vai ao disco** | Aumentar para 2 GB |
| 5 subqueries correlacionadas nas conversas Tudinha | +50–100 queries extras se aluno ativo | Reescrever com JOIN+GROUP BY |
| 22–35 queries sequenciais no perfil | Cada roundtrip = ~2–5 ms → 60–150 ms só em latência | Consolidar em 8–10 queries |
| `SHOW TABLES` + `INFORMATION_SCHEMA` sem cache | DDL queries são lentas no MySQL | Cache Redis 1h |
| `logs_auditoria` sem índice composto em 1.2M linhas | Full scan a cada carregamento do perfil | `ADD INDEX (resource_accessed, created_at)` |
| Query redundante (linha 524) | 1 roundtrip desnecessário | Remover, usar dado já em memória |

**Com o buffer pool corrigido + as 3 queries reescritas + os índices adicionados: estimativa de 200–400 ms → < 100 ms por carregamento do perfil.**

