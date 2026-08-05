-- Log de cada interação do aluno com as questões (marcou/alterou resposta)
CREATE TABLE IF NOT EXISTS `provas_respostas_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `prova_id` int(11) unsigned NOT NULL,
  `aluno_id` int(11) unsigned NOT NULL,
  `questao_id` int(11) unsigned NOT NULL,
  `alternativa_id` int(11) unsigned DEFAULT NULL,
  `resposta_texto` text DEFAULT NULL,
  `tipo_acao` enum('marcou','alterou') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prova_aluno_created` (`prova_id`,`aluno_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Key do comprovante de marcação final (imagem salva no S3/storage)
ALTER TABLE `provas_realizacoes`
  ADD COLUMN `marcacao_final_key` varchar(255) DEFAULT NULL;
