-- M6: FKs em turmas para ano_letivo_id e serie_id.
-- Executar SOMENTE após backfill 027 e validação (turmas com ano_letivo_id e serie_id preenchidos).
-- Se ainda houver turmas com ano_letivo_id ou serie_id NULL, esta migration pode falhar.

SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'turmas'
    AND CONSTRAINT_NAME = 'fk_turmas_ano_letivo'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk_exists = 0,
  'ALTER TABLE `turmas` ADD CONSTRAINT `fk_turmas_ano_letivo` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'turmas'
    AND CONSTRAINT_NAME = 'fk_turmas_serie'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk_exists = 0,
  'ALTER TABLE `turmas` ADD CONSTRAINT `fk_turmas_serie` FOREIGN KEY (`serie_id`) REFERENCES `serie` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
