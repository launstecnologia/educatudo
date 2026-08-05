-- jornadas.titulo/descricao/status em utf8mb3 rejeitam emoji (4 bytes) gerado pela IA.
-- Apenas as 3 colunas textuais; estrutura permanece utf8mb4_bin.

ALTER TABLE jornadas
    MODIFY COLUMN status VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
    MODIFY COLUMN titulo VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY COLUMN descricao TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
