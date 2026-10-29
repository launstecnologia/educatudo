-- Escolha da recuperação: onde a nota entra no par de bimestres,
-- fórmula própria e se quem não ficou de recuperação pode fazer a prova.
-- Tenant. Sem escola_id: o isolamento é o banco. Idempotente.
-- Rollback: 2026_10_28_recuperacao_escolha_rollback.sql

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'recuperacoes'
    AND COLUMN_NAME = 'aplica_em'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `recuperacoes`
     ADD COLUMN `aplica_em` VARCHAR(20) NULL DEFAULT NULL
       COMMENT ''par = os dois bimestres; cada = cada bimestre. Só quando quando=par''
     AFTER `efeito`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'recuperacoes'
    AND COLUMN_NAME = 'formula'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `recuperacoes`
     ADD COLUMN `formula` VARCHAR(255) NULL DEFAULT NULL
       COMMENT ''Expressão quando efeito=formula. Variáveis: media e rec''
     AFTER `aplica_em`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'recuperacoes'
    AND COLUMN_NAME = 'permite_aumentar'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `recuperacoes`
     ADD COLUMN `permite_aumentar` TINYINT(1) NOT NULL DEFAULT 0
       COMMENT ''1 = aluno que não ficou de recuperação pode fazer para aumentar a nota''
     AFTER `formula`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
