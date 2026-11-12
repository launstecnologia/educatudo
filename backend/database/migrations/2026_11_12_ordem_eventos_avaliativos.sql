-- Ordem de exibição de eventos avaliativos e subeventos (provas_blocos).
-- Tenant. Idempotente. Rollback: 2026_11_12_ordem_eventos_avaliativos_rollback.sql

SET @db := DATABASE();

SET @tem_ev := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'eventos_avaliativos' AND COLUMN_NAME = 'ordem'
);
SET @sql := IF(@tem_ev = 0,
  'ALTER TABLE `eventos_avaliativos`
     ADD COLUMN `ordem` INT NOT NULL DEFAULT 0
       COMMENT ''Ordem de exibição no período (ano + bimestre)''
     AFTER `ativo`,
     ADD KEY `idx_eventos_avaliativos_ordem` (`ano_letivo`, `bimestre`, `ordem`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_pb := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'ordem'
);
SET @sql := IF(@tem_pb = 0,
  'ALTER TABLE `provas_blocos`
     ADD COLUMN `ordem` INT NOT NULL DEFAULT 0
       COMMENT ''Ordem de exibição entre subeventos do mesmo evento'',
     ADD KEY `idx_provas_blocos_ordem_evento` (`evento_avaliativo_id`, `ordem`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill só quando o período ainda está todo em ordem 0 (não sobrescreve ordem manual)
UPDATE `eventos_avaliativos` e
INNER JOIN (
  SELECT id,
         ROW_NUMBER() OVER (
           PARTITION BY ano_letivo, bimestre
           ORDER BY nome ASC, id ASC
         ) AS nova_ordem,
         MAX(ordem) OVER (PARTITION BY ano_letivo, bimestre) AS max_ordem
    FROM `eventos_avaliativos`
   WHERE deleted_at IS NULL
) x ON x.id = e.id
SET e.ordem = x.nova_ordem
WHERE e.deleted_at IS NULL
  AND x.max_ordem = 0;

-- Backfill subeventos só quando o evento ainda está todo em ordem 0
UPDATE `provas_blocos` pb
INNER JOIN (
  SELECT id,
         ROW_NUMBER() OVER (
           PARTITION BY evento_avaliativo_id
           ORDER BY semana ASC, data_prova ASC, id ASC
         ) AS nova_ordem,
         MAX(ordem) OVER (PARTITION BY evento_avaliativo_id) AS max_ordem
    FROM `provas_blocos`
   WHERE deleted_at IS NULL
     AND evento_avaliativo_id IS NOT NULL
) x ON x.id = pb.id
SET pb.ordem = x.nova_ordem
WHERE pb.deleted_at IS NULL
  AND pb.evento_avaliativo_id IS NOT NULL
  AND x.max_ordem = 0;
