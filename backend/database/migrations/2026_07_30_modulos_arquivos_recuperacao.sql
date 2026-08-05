-- Marca publicação do módulo de arquivos como material de recuperação.
-- Quando 1, o aluno vê em /aluno/recuperacao; quando 0, em /aluno/arquivos.
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'modulos_arquivos'
      AND COLUMN_NAME = 'recuperacao'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE `modulos_arquivos` ADD COLUMN `recuperacao` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = aparece em Recuperação no aluno'' AFTER `descricao`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'modulos_arquivos'
      AND INDEX_NAME = 'idx_recuperacao'
);

SET @sql_idx := IF(
    @idx_exists = 0,
    'ALTER TABLE `modulos_arquivos` ADD KEY `idx_recuperacao` (`recuperacao`)',
    'SELECT 1'
);

PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
