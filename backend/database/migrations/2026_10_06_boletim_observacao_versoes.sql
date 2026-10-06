-- Versões e log da observação da coordenação no boletim.
-- Tenant. Idempotente. Rollback: 2026_10_06_boletim_observacao_versoes_rollback.sql

CREATE TABLE IF NOT EXISTS boletim_observacao_versoes (
    id INT NOT NULL AUTO_INCREMENT,
    aluno_id INT NOT NULL,
    conteudo TEXT NOT NULL,
    usuario_id INT NULL,
    usuario_nome VARCHAR(150) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_boletim_obs_versoes_aluno (aluno_id, id),
    CONSTRAINT fk_boletim_obs_versoes_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boletim_observacao_log (
    id INT NOT NULL AUTO_INCREMENT,
    aluno_id INT NOT NULL,
    acao VARCHAR(30) NOT NULL,
    usuario_id INT NULL,
    usuario_nome VARCHAR(150) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_boletim_obs_log_aluno (aluno_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();

SET @has_obs := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_observacoes'
);

SET @sql_versoes := IF(
  @has_obs > 0,
  "INSERT INTO boletim_observacao_versoes (aluno_id, conteudo, usuario_id, usuario_nome, created_at) SELECT o.aluno_id, TRIM(o.conteudo), o.updated_by, NULLIF(LEFT(COALESCE(u.nome, ''), 150), ''), COALESCE(o.updated_at, o.created_at, NOW()) FROM boletim_observacoes o LEFT JOIN usuarios u ON u.id = o.updated_by WHERE o.conteudo IS NOT NULL AND CHAR_LENGTH(TRIM(o.conteudo)) > 0 AND NOT EXISTS (SELECT 1 FROM boletim_observacao_versoes v WHERE v.aluno_id = o.aluno_id)",
  'SELECT 1'
);
PREPARE stmt_versoes FROM @sql_versoes;
EXECUTE stmt_versoes;
DEALLOCATE PREPARE stmt_versoes;

SET @sql_log := IF(
  @has_obs > 0,
  "INSERT INTO boletim_observacao_log (aluno_id, acao, usuario_id, usuario_nome, created_at) SELECT o.aluno_id, 'salvou', o.updated_by, NULLIF(LEFT(COALESCE(u.nome, ''), 150), ''), COALESCE(o.updated_at, o.created_at, NOW()) FROM boletim_observacoes o LEFT JOIN usuarios u ON u.id = o.updated_by WHERE o.conteudo IS NOT NULL AND CHAR_LENGTH(TRIM(o.conteudo)) > 0 AND NOT EXISTS (SELECT 1 FROM boletim_observacao_log l WHERE l.aluno_id = o.aluno_id)",
  'SELECT 1'
);
PREPARE stmt_log FROM @sql_log;
EXECUTE stmt_log;
DEALLOCATE PREPARE stmt_log;
