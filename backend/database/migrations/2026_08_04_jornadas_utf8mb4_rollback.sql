-- Reverte titulo/descricao/status para utf8mb3 (não suporta emoji).

ALTER TABLE jornadas
    MODIFY COLUMN status VARCHAR(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'ativa',
    MODIFY COLUMN titulo VARCHAR(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
    MODIFY COLUMN descricao TEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci;
