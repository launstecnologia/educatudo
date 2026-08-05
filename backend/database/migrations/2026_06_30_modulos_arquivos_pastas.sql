-- Pastas para o módulo de arquivos (professor e admin)
CREATE TABLE IF NOT EXISTS `modulos_arquivos_pastas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `cor` varchar(7) NOT NULL DEFAULT '#6366f1',
  `professor_id` int DEFAULT NULL COMMENT 'NULL = pasta criada pelo admin',
  `criado_por_tipo` enum('professor','admin') NOT NULL DEFAULT 'professor',
  `ordem` int NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_professor` (`professor_id`),
  KEY `idx_tipo` (`criado_por_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Pastas de organização do módulo de arquivos';

-- Adiciona coluna pasta_id em modulos_arquivos (compatível com MySQL 5.7+)
SET @schema := DATABASE();

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'modulos_arquivos' AND COLUMN_NAME = 'pasta_id'
);
SET @sql_col := IF(@has_col = 0,
  'ALTER TABLE `modulos_arquivos` ADD COLUMN `pasta_id` int DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona índice idx_pasta_id se não existir
SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'modulos_arquivos' AND INDEX_NAME = 'idx_pasta_id'
);
SET @sql_idx := IF(@has_idx = 0,
  'ALTER TABLE `modulos_arquivos` ADD KEY `idx_pasta_id` (`pasta_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
