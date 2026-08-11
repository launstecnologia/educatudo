-- Cria a nova tabela de validacao de tokens para apps externos
-- (tenant DB) e migra tokens legados de notes_tokens quando existir.

CREATE TABLE IF NOT EXISTS `validacao_tokens_apps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `app` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_nickname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tenant_slug` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_tenant_slug` (`tenant_slug`),
  KEY `idx_app` (`app`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_notes_tokens := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = 'notes_tokens'
);

SET @copy_tokens_sql := IF(
  @has_notes_tokens > 0,
  'INSERT IGNORE INTO validacao_tokens_apps (token, app, user_id, user_name, user_nickname, tenant_slug, created_at, expires_at, used) SELECT n.token, NULL, n.user_id, n.user_name, NULL, NULL, n.created_at, n.expires_at, n.used FROM notes_tokens n',
  'SELECT 1'
);

PREPARE stmt_copy_tokens FROM @copy_tokens_sql;
EXECUTE stmt_copy_tokens;
DEALLOCATE PREPARE stmt_copy_tokens;
