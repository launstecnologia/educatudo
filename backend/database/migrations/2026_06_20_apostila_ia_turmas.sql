-- IA da Apostila / Meu Material: suporte a múltiplas turmas por apostila
-- (mesmo padrão já usado em modulos_apostilas_turmas, no módulo Apostilas simples).
-- apostilas_ia.turma_id continua existindo como "turma principal" (compatibilidade
-- com código já escrito), mas a visibilidade real passa a considerar também esta
-- tabela de ligação N:N.
-- Executar em cada banco de tenant.

CREATE TABLE IF NOT EXISTS apostila_ia_turmas (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    apostila_id BIGINT UNSIGNED NOT NULL,
    turma_id    BIGINT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_apostila_turma (apostila_id, turma_id),
    INDEX idx_turma (turma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: linhas já existentes com turma_id preenchido passam a ter também
-- uma entrada equivalente na tabela de ligação, para não perder visibilidade
-- quando as consultas passarem a considerar apostila_ia_turmas.
INSERT INTO apostila_ia_turmas (apostila_id, turma_id)
SELECT ai.id, ai.turma_id
FROM apostilas_ia ai
WHERE ai.turma_id IS NOT NULL
ON DUPLICATE KEY UPDATE apostila_ia_turmas.turma_id = apostila_ia_turmas.turma_id;
