-- Links de vídeo (YouTube, Vimeo, etc.) nas publicações do módulo de arquivos.
-- Vídeos enviados como arquivo continuam em modulos_arquivos_anexos.
-- FK omitida para permitir rodar em tenants que ainda não têm a tabela modulos_arquivos
-- (essa tabela vem do dump completo, não de migrations numeradas).

CREATE TABLE IF NOT EXISTS `modulos_arquivos_videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_arquivo_id` int NOT NULL COMMENT 'Referência a modulos_arquivos.id (quando a tabela existir)',
  `url` varchar(1024) NOT NULL COMMENT 'URL do vídeo (YouTube, Vimeo, ou link direto)',
  `titulo` varchar(255) DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ma_videos_modulo` (`modulo_arquivo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Links de vídeo (YouTube, Vimeo) por publicação de arquivos';
