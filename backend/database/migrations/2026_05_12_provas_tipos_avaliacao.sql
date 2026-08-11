-- Tipos de Avaliação (CRUD) + vínculo no evento de prova online

CREATE TABLE IF NOT EXISTS provas_tipos_avaliacao (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    descricao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_provas_tipos_avaliacao_nome (nome),
    KEY idx_provas_tipos_avaliacao_ativo_ordem (ativo, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provas_blocos'
      AND COLUMN_NAME = 'tipo_avaliacao_id'
);
SET @sql_add_col := IF(
    @col_exists = 0,
    'ALTER TABLE provas_blocos ADD COLUMN tipo_avaliacao_id INT UNSIGNED NULL AFTER bimestre',
    'SELECT 1'
);
PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_provas_blocos_tipo_avaliacao'
      AND TABLE_NAME = 'provas_blocos'
);
SET @sql_add_fk := IF(
    @fk_exists = 0,
    'ALTER TABLE provas_blocos ADD CONSTRAINT fk_provas_blocos_tipo_avaliacao FOREIGN KEY (tipo_avaliacao_id) REFERENCES provas_tipos_avaliacao(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_add_fk FROM @sql_add_fk;
EXECUTE stmt_add_fk;
DEALLOCATE PREPARE stmt_add_fk;

INSERT IGNORE INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem) VALUES
('Prova Semanal', 'Avaliação aplicada semanalmente.', 1, 10),
('Prova Bimestral', 'Avaliação principal do bimestre.', 1, 20),
('Simulado', 'Simulado preparatório.', 1, 30);
