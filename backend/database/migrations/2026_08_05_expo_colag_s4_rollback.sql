-- Rollback Expo Colag S4
-- Justificativa: remove tarefas, stands/QR, programação e coluna origem de materiais.

DROP TABLE IF EXISTS `expo_colag_programacao`;
DROP TABLE IF EXISTS `expo_colag_stands`;
DROP TABLE IF EXISTS `expo_colag_setores`;
DROP TABLE IF EXISTS `expo_colag_projeto_tarefa_atribuicoes`;
DROP TABLE IF EXISTS `expo_colag_projeto_tarefas`;

SET @has_origem := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'expo_colag_projeto_materiais'
      AND COLUMN_NAME = 'origem'
);
SET @sql_drop_origem := IF(
    @has_origem = 0,
    'SELECT 1',
    'ALTER TABLE `expo_colag_projeto_materiais` DROP COLUMN `origem`'
);
PREPARE stmt_drop_origem FROM @sql_drop_origem;
EXECUTE stmt_drop_origem;
DEALLOCATE PREPARE stmt_drop_origem;
