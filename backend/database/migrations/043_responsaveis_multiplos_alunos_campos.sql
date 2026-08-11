-- Suporte a multiplos responsaveis por aluno e campos adicionais no aluno

CREATE TABLE IF NOT EXISTS alunos_responsaveis (
    id INT NOT NULL AUTO_INCREMENT,
    aluno_id INT NOT NULL,
    responsavel_id INT NOT NULL,
    tipo_vinculo VARCHAR(30) DEFAULT NULL,
    is_financeiro TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aluno_responsavel (aluno_id, responsavel_id),
    KEY idx_alunos_responsaveis_aluno (aluno_id),
    KEY idx_alunos_responsaveis_responsavel (responsavel_id),
    KEY idx_alunos_responsaveis_financeiro (is_financeiro),
    CONSTRAINT fk_alunos_responsaveis_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id) ON DELETE CASCADE,
    CONSTRAINT fk_alunos_responsaveis_responsavel FOREIGN KEY (responsavel_id) REFERENCES responsaveis (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_force_password_change_exists := (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'responsaveis'
      AND column_name = 'force_password_change'
);
SET @sql_col_force_password_change := IF(
    @col_force_password_change_exists = 0,
    'ALTER TABLE responsaveis ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER senha_hash',
    'SELECT 1'
);
PREPARE stmt_col_force_password_change FROM @sql_col_force_password_change;
EXECUTE stmt_col_force_password_change;
DEALLOCATE PREPARE stmt_col_force_password_change;

SET @col_codigo_aluno_exists := (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'alunos'
      AND column_name = 'codigo_aluno'
);
SET @sql_col_codigo_aluno := IF(
    @col_codigo_aluno_exists = 0,
    'ALTER TABLE alunos ADD COLUMN codigo_aluno VARCHAR(100) DEFAULT NULL AFTER ra',
    'SELECT 1'
);
PREPARE stmt_col_codigo_aluno FROM @sql_col_codigo_aluno;
EXECUTE stmt_col_codigo_aluno;
DEALLOCATE PREPARE stmt_col_codigo_aluno;

SET @col_alunos_cpf_exists := (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'alunos'
      AND column_name = 'cpf'
);
SET @sql_col_alunos_cpf := IF(
    @col_alunos_cpf_exists = 0,
    'ALTER TABLE alunos ADD COLUMN cpf VARCHAR(14) DEFAULT NULL AFTER email',
    'SELECT 1'
);
PREPARE stmt_col_alunos_cpf FROM @sql_col_alunos_cpf;
EXECUTE stmt_col_alunos_cpf;
DEALLOCATE PREPARE stmt_col_alunos_cpf;

SET @col_foto_url_exists := (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'alunos'
      AND column_name = 'foto_url'
);
SET @sql_col_foto_url := IF(
    @col_foto_url_exists = 0,
    'ALTER TABLE alunos ADD COLUMN foto_url TEXT DEFAULT NULL AFTER cpf',
    'SELECT 1'
);
PREPARE stmt_col_foto_url FROM @sql_col_foto_url;
EXECUTE stmt_col_foto_url;
DEALLOCATE PREPARE stmt_col_foto_url;

SET @idx_codigo_aluno_exists := (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'alunos'
      AND index_name = 'idx_alunos_codigo_aluno'
);
SET @sql_idx_codigo_aluno := IF(@idx_codigo_aluno_exists = 0, 'CREATE INDEX idx_alunos_codigo_aluno ON alunos (codigo_aluno)', 'SELECT 1');
PREPARE stmt_idx_codigo_aluno FROM @sql_idx_codigo_aluno;
EXECUTE stmt_idx_codigo_aluno;
DEALLOCATE PREPARE stmt_idx_codigo_aluno;

SET @idx_alunos_cpf_exists := (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'alunos'
      AND index_name = 'idx_alunos_cpf'
);
SET @sql_idx_alunos_cpf := IF(@idx_alunos_cpf_exists = 0, 'CREATE INDEX idx_alunos_cpf ON alunos (cpf)', 'SELECT 1');
PREPARE stmt_idx_alunos_cpf FROM @sql_idx_alunos_cpf;
EXECUTE stmt_idx_alunos_cpf;
DEALLOCATE PREPARE stmt_idx_alunos_cpf;
