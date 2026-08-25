-- Não remove `provas_blocos.tipo_avaliacao_id`: a coluna pode ter vindo da migration
-- 2026_05_12_provas_tipos_avaliacao.sql e eventos reais já a usam.

SET @db := DATABASE();

-- Tabelas criadas só por este par; o DROP reverte o quadro semanal.
DROP TABLE IF EXISTS `notas_semanais_materias`;
DROP TABLE IF EXISTS `notas_semanais_config`;

SET @has_pb := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos'
);
SET @col_semana := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'semana'
);
SET @sql := IF(
    @has_pb > 0 AND @col_semana > 0,
    'ALTER TABLE provas_blocos DROP COLUMN `semana`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_tipos := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao'
);
SET @col_chave := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'chave_quadro'
);
SET @sql := IF(
    @has_tipos > 0 AND @col_chave > 0,
    'ALTER TABLE provas_tipos_avaliacao DROP COLUMN `chave_quadro`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE provas_tipos_avaliacao
SET deleted_at = NOW()
WHERE deleted_at IS NULL
  AND nome IN ('ENAC', 'Participação', 'Trabalho', 'Recuperação');
