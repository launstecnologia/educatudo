-- ============================================================================
-- AVA / EAD - Fase 1 (parte 2): flag de tutoria no professor
-- ----------------------------------------------------------------------------
-- Executar DEPOIS de 2026_06_26_ava_01_fase1.sql (independente, mas mantida a
-- ordem por nome). Adiciona professores.pode_tutoria sem criar novo tipo de
-- usuario. Idempotente (so adiciona a coluna se nao existir).
-- Rollback: 2026_06_26_ava_02_professor_tutoria_rollback.sql
-- ============================================================================

SET @db := DATABASE();
SET @has_col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professores' AND COLUMN_NAME = 'pode_tutoria'
);
SET @sql := IF(
  @has_col > 0,
  'SELECT 1',
  'ALTER TABLE professores ADD COLUMN pode_tutoria TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE stmt_ava_tutoria FROM @sql;
EXECUTE stmt_ava_tutoria;
DEALLOCATE PREPARE stmt_ava_tutoria;
