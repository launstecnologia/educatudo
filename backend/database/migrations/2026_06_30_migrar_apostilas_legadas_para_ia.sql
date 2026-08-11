-- Migra apostilas do módulo legado (modulos_apostilas) para o Meu Material (apostilas_ia).
-- Idempotente: usa legado_modulo_id para não duplicar apostilas já migradas.
-- Os arquivos físicos permanecem no diretório original (storage/uploads/apostilas/);
-- o controller reconhece o prefixo "legado:" e serve o PDF de lá.

-- 1. Garante coluna de rastreio de migração em apostilas_ia
SET @schema := DATABASE();

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'apostilas_ia'
      AND COLUMN_NAME = 'legado_modulo_id'
);
SET @sql_col := IF(@col_exists = 0,
    'ALTER TABLE apostilas_ia ADD COLUMN legado_modulo_id INT NULL COMMENT "ID de modulos_apostilas de origem" AFTER updated_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Migra as apostilas que ainda não foram migradas (legado_modulo_id não existe em apostilas_ia)
INSERT INTO apostilas_ia (titulo, turma_id, disciplina_id, professor_id, arquivo_pdf, status, created_at, legado_modulo_id)
SELECT
    ma.titulo,
    ma.turma_id,
    ma.materia_id,
    ma.professor_id,
    COALESCE(
        (
            SELECT CONCAT('legado:', aa.caminho)
            FROM modulos_apostilas_anexos aa
            WHERE aa.modulo_apostila_id = ma.id
              AND (aa.extensao = 'pdf' OR aa.mime_type = 'application/pdf')
            ORDER BY aa.ordem ASC
            LIMIT 1
        ),
        CONCAT('legado:sem-arquivo/', ma.id)
    ),
    'pronto',
    ma.created_at,
    ma.id
FROM modulos_apostilas ma
WHERE NOT EXISTS (
    SELECT 1 FROM apostilas_ia ai WHERE ai.legado_modulo_id = ma.id
);

-- 3. Migra as turmas das apostilas recém-inseridas para apostila_ia_turmas
INSERT INTO apostila_ia_turmas (apostila_id, turma_id)
SELECT ai.id, mat.turma_id
FROM apostilas_ia ai
INNER JOIN modulos_apostilas_turmas mat ON mat.modulo_apostila_id = ai.legado_modulo_id
WHERE ai.legado_modulo_id IS NOT NULL
ON DUPLICATE KEY UPDATE apostila_ia_turmas.turma_id = apostila_ia_turmas.turma_id;
