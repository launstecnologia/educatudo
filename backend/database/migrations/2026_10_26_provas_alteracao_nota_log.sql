-- Histórico de alterações manuais de acerto/nota em prova online, feitas pela coordenação.
-- Tenant. Idempotente. Rollback: 2026_10_26_provas_alteracao_nota_log_rollback.sql

CREATE TABLE IF NOT EXISTS provas_notas_alteracoes_log (
    id INT NOT NULL AUTO_INCREMENT,
    lote VARCHAR(32) NOT NULL,
    bloco_id INT NULL,
    prova_id INT NOT NULL,
    aluno_id INT NULL,
    usuario_id INT NOT NULL,
    usuario_nome VARCHAR(255) NOT NULL,
    observacao TEXT NOT NULL,
    ciente TINYINT(1) NOT NULL DEFAULT 1,
    escopo VARCHAR(20) NOT NULL,
    nota_anterior DECIMAL(10,2) NULL,
    nota_nova DECIMAL(10,2) NULL,
    detalhes_json MEDIUMTEXT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_prova_nota_alt (prova_id, id),
    KEY idx_bloco_nota_alt (bloco_id),
    KEY idx_lote_nota_alt (lote)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
