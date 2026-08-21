-- Log de Provas (Master, somente leitura — ver specs/tasks.md "Log de Provas"):
-- registra anomalias durante a realização de provas online pelo aluno (erro de
-- sessão/salvar resposta/finalizar, tentativa de burlar o modo seguro/tela
-- cheia). NÃO registra fluxo normal (acessar, responder, avançar, finalizar
-- sem erro) — só evento de erro/anomalia.
--
-- Sem FK: é tabela de log/incidente, a gravação não pode falhar por causa de
-- uma referência quebrada (ex.: aluno_id desconhecido porque a sessão já
-- caiu antes de sabermos quem é — exatamente o caso que queremos capturar).
--
-- Tenant. Idempotente. Rollback: 2026_08_21_provas_log_eventos_rollback.sql

SET @db := DATABASE();

SET @has_tabela := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='provas_log_eventos');
SET @sql := IF(@has_tabela=0,
  "CREATE TABLE `provas_log_eventos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `tipo_evento` VARCHAR(40) NOT NULL COMMENT 'erro_sessao, erro_salvar_resposta, erro_finalizar, saida_modo_seguro, tentativa_sair_tela_cheia, tentativa_atualizar_pagina, tentativa_voltar_navegador, outro',
    `aluno_id` INT DEFAULT NULL COMMENT 'NULL quando a sessão já caiu antes de identificar o aluno',
    `prova_id` INT DEFAULT NULL,
    `bloco_id` INT DEFAULT NULL,
    `detalhe` TEXT DEFAULT NULL COMMENT 'Mensagem/contexto técnico do evento',
    `ip` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_provas_log_eventos_created_at` (`created_at`),
    KEY `idx_provas_log_eventos_tipo` (`tipo_evento`),
    KEY `idx_provas_log_eventos_aluno` (`aluno_id`),
    KEY `idx_provas_log_eventos_prova` (`prova_id`),
    KEY `idx_provas_log_eventos_bloco` (`bloco_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
