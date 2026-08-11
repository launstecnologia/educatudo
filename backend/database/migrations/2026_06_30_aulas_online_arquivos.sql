-- Arquivos/documentos vinculados a uma aula online específica
CREATE TABLE IF NOT EXISTS `aulas_online_arquivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aula_id` int NOT NULL,
  `nome_original` varchar(255) NOT NULL,
  `caminho` varchar(500) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aula_id` (`aula_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
