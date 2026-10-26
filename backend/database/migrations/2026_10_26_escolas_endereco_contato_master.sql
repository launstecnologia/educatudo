-- MASTER. Endereço e contato da instituição no cadastro da escola.
-- Rollback: 2026_10_26_escolas_endereco_contato_master_rollback.sql

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'escolas'
       AND COLUMN_NAME = 'rua') = 0,
    'ALTER TABLE `escolas`
        ADD COLUMN `rua` VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN `numero` VARCHAR(20) NULL DEFAULT NULL,
        ADD COLUMN `complemento` VARCHAR(120) NULL DEFAULT NULL,
        ADD COLUMN `bairro` VARCHAR(120) NULL DEFAULT NULL,
        ADD COLUMN `cidade` VARCHAR(120) NULL DEFAULT NULL,
        ADD COLUMN `uf` CHAR(2) NULL DEFAULT NULL,
        ADD COLUMN `telefone` VARCHAR(30) NULL DEFAULT NULL,
        ADD COLUMN `contato_nome` VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
