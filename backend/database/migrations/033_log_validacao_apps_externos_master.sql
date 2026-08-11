-- Mesma tabela de log, mas para o banco MASTER.
-- Quando o endpoint de validação é master.educatudo.com e o jogo/app não envia
-- o parâmetro "slug" (ou envia só "inst"), a falha é registrada no banco master.
-- Execute no banco MASTER (não no tenant).

CREATE TABLE IF NOT EXISTS `log_validacao_apps_externos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'games, educalabs, notes, ou app key',
  `evento` VARCHAR(64) NOT NULL DEFAULT 'validate.fail' COMMENT 'validate.fail, token_expirado, etc',
  `detalhes` JSON NULL COMMENT 'slug, diagnostic, host, uri, etc',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_app` (`app`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de falhas (quando slug não enviado ou escola não identificada)';
