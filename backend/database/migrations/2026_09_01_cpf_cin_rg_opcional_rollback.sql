-- Rollback: 2026_09_01_cpf_cin_rg_opcional.sql
-- Restaura RG obrigatório e o rótulo CPF no checklist da matrícula.

SET @db := DATABASE();

SET @has_checklist := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matricula_checklist_itens'
);

SET @sql_rg := IF(
  @has_checklist > 0,
  "UPDATE `matricula_checklist_itens` SET `obrigatorio` = 1 WHERE `codigo` = 'rg'",
  'SELECT 1'
);
PREPARE stmt_rg FROM @sql_rg;
EXECUTE stmt_rg;
DEALLOCATE PREPARE stmt_rg;

SET @sql_cpf := IF(
  @has_checklist > 0,
  "UPDATE `matricula_checklist_itens` SET `rotulo` = 'CPF' WHERE `codigo` = 'cpf'",
  'SELECT 1'
);
PREPARE stmt_cpf FROM @sql_cpf;
EXECUTE stmt_cpf;
DEALLOCATE PREPARE stmt_cpf;
