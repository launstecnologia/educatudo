-- Log de falhas de validação de token (apps externos / jogos / EducaLabs / Notes).
-- Usado no Master > Escola > Apps Externos para diagnosticar por que o jogo ou app não abriu.
-- Execute no banco do TENANT (da escola). Se o endpoint de validação for master.educatudo.com, execute também 033 no banco master.

CREATE TABLE IF NOT EXISTS `log_validacao_apps_externos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'games, educalabs, notes, ou app key',
  `evento` VARCHAR(64) NOT NULL DEFAULT 'validate.fail' COMMENT 'validate.fail, token_expirado, etc',
  `detalhes` JSON NULL COMMENT 'slug, diagnostic, host, uri, etc',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_app` (`app`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de falhas ao validar token de apps externos (master ver em Apps Externos)';
