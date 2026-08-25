-- Rollback: 2026_08_18_componentes_curriculares_etapas.sql
--
-- Atenção: se alguma linha já usa um valor de enum introduzido por esta
-- migration (ex.: area_conhecimento='tecnologia' ou tipo='complementar'),
-- reduzir o ENUM de volta pode trocar esse valor por '' silenciosamente
-- (comportamento padrão do MySQL para enum fora da lista, fora de modo
-- estrito). Ajuste essas linhas antes de rodar este rollback, se necessário.

SET @db := DATABASE();
SET @has_materias := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias');

SET @tem_tipo_ampliado := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='tipo'
    AND COLUMN_TYPE LIKE '%complementar%'
);
SET @sql := IF(@has_materias>0 AND @tem_tipo_ampliado>0,
  "ALTER TABLE materias MODIFY COLUMN `tipo` ENUM('formacao_geral','eletiva','itinerario_formativo','extracurricular','outra') NOT NULL DEFAULT 'formacao_geral'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_area_ampliada := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='area_conhecimento'
    AND COLUMN_TYPE LIKE '%computacao%'
);
SET @sql := IF(@has_materias>0 AND @tem_area_ampliada>0,
  "ALTER TABLE materias MODIFY COLUMN `area_conhecimento` ENUM('linguagens','matematica','ciencias_natureza','ciencias_humanas','outra') NOT NULL DEFAULT 'outra'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_medio');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `etapa_medio`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_fund_ii');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `etapa_fund_ii`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_fund_i');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `etapa_fund_i`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_infantil');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `etapa_infantil`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
