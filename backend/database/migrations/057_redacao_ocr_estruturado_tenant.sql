SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redacoes_orientadas_entregas'
          AND COLUMN_NAME = 'ocr_text_structure_json'
    ),
    'SELECT 1',
    'ALTER TABLE redacoes_orientadas_entregas ADD COLUMN ocr_text_structure_json LONGTEXT NULL AFTER ocr_text'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'redacoes_orientadas_entregas'
          AND COLUMN_NAME = 'ocr_layout_json'
    ),
    'SELECT 1',
    'ALTER TABLE redacoes_orientadas_entregas ADD COLUMN ocr_layout_json LONGTEXT NULL AFTER ocr_text_structure_json'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
