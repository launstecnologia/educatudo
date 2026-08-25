-- Componentes Curriculares — aplicabilidade por etapa de ensino + ampliação
-- dos catálogos de Área do Conhecimento e Tipo/Categoria.
-- Tenant. Idempotente. Rollback: 2026_08_18_componentes_curriculares_etapas_rollback.sql
--
-- Arquivo separado de 2026_08_18_componentes_curriculares_campos.sql de propósito:
-- aquele já rodou em produção (Colag) antes destes campos existirem, e o painel
-- Master rastreia execução por nome de arquivo — editar o arquivo já executado
-- faria essas escolas nunca receberem as colunas abaixo.
--
-- 1) Novas colunas: etapa_infantil/etapa_fund_i/etapa_fund_ii/etapa_medio —
--    cada uma indica se o componente é 'obrigatoria', ofertado como eletiva
--    ('oferta') ou 'nao_aplica' para aquela etapa de ensino.
-- 2) Amplia o ENUM de `area_conhecimento` (adiciona ensino_religioso,
--    interdisciplinar, tecnologia, computacao, arte) e de `tipo` (adiciona
--    lingua_adicional, complementar) — só ADICIONA valores, os já existentes
--    (linguagens, matematica, ciencias_natureza, ciencias_humanas, outra /
--    formacao_geral, eletiva, itinerario_formativo, extracurricular, outra)
--    continuam válidos, nenhuma linha existente é afetada.

SET @db := DATABASE();
SET @has_materias := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias');
SET @has_tipo := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='tipo');

-- Novas colunas de etapa (dependem de `tipo` já existir, criado por componentes_curriculares_campos.sql)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_infantil');
SET @sql := IF(@has_materias>0 AND @has_tipo>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `etapa_infantil` ENUM('nao_aplica','obrigatoria','oferta') NOT NULL DEFAULT 'nao_aplica' AFTER `tipo`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_fund_i');
SET @sql := IF(@has_materias>0 AND @col=0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_infantil')>0,
  "ALTER TABLE materias ADD COLUMN `etapa_fund_i` ENUM('nao_aplica','obrigatoria','oferta') NOT NULL DEFAULT 'nao_aplica' AFTER `etapa_infantil`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_fund_ii');
SET @sql := IF(@has_materias>0 AND @col=0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_fund_i')>0,
  "ALTER TABLE materias ADD COLUMN `etapa_fund_ii` ENUM('nao_aplica','obrigatoria','oferta') NOT NULL DEFAULT 'nao_aplica' AFTER `etapa_fund_i`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_medio');
SET @sql := IF(@has_materias>0 AND @col=0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='etapa_fund_ii')>0,
  "ALTER TABLE materias ADD COLUMN `etapa_medio` ENUM('nao_aplica','obrigatoria','oferta') NOT NULL DEFAULT 'nao_aplica' AFTER `etapa_fund_ii`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Amplia o ENUM de area_conhecimento (idempotente: só roda se ainda não tem 'computacao')
SET @precisa_ampliar_area := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='area_conhecimento'
    AND COLUMN_TYPE NOT LIKE '%computacao%'
);
SET @sql := IF(@has_materias>0 AND @precisa_ampliar_area>0,
  "ALTER TABLE materias MODIFY COLUMN `area_conhecimento` ENUM('linguagens','matematica','ciencias_natureza','ciencias_humanas','ensino_religioso','interdisciplinar','tecnologia','computacao','arte','outra') NOT NULL DEFAULT 'outra'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Amplia o ENUM de tipo (idempotente: só roda se ainda não tem 'complementar')
SET @precisa_ampliar_tipo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='tipo'
    AND COLUMN_TYPE NOT LIKE '%complementar%'
);
SET @sql := IF(@has_materias>0 AND @precisa_ampliar_tipo>0,
  "ALTER TABLE materias MODIFY COLUMN `tipo` ENUM('formacao_geral','lingua_adicional','eletiva','itinerario_formativo','extracurricular','complementar','outra') NOT NULL DEFAULT 'formacao_geral'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
