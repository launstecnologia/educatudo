-- Créditos – banco master (executar APENAS no banco administrador).
-- Este arquivo é ignorado pelo runner de migrations dos tenants (nome contém "master").

CREATE TABLE IF NOT EXISTS `modulos_preco_creditos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modulo_key` VARCHAR(64) NOT NULL,
  `creditos` INT UNSIGNED NOT NULL DEFAULT 1,
  `nome_exibicao` VARCHAR(255) NOT NULL DEFAULT '',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_modulos_preco_key` (`modulo_key`),
  KEY `idx_modulos_preco_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Precificação global em créditos por ação/módulo';

CREATE TABLE IF NOT EXISTS `recargas_mensais_escolas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `escola_id` INT UNSIGNED NOT NULL,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `mes` TINYINT UNSIGNED NOT NULL,
  `executada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_recargas_escola_ano_mes` (`escola_id`, `ano`, `mes`),
  KEY `idx_recargas_escola` (`escola_id`),
  CONSTRAINT `fk_recargas_escola` FOREIGN KEY (`escola_id`) REFERENCES `escolas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Controle de recarga mensal B2B por escola (evita duplicar no mesmo mês)';

-- Inserir módulos consumíveis iniciais (custos padrão em créditos)
INSERT IGNORE INTO `modulos_preco_creditos` (`modulo_key`, `creditos`, `nome_exibicao`, `ativo`) VALUES
('tudinha_mensagem', 1, 'Tudinha (mensagem IA)', 1),
('exercicio_ia_aluno', 1, 'Exercício gerado por IA (aluno)', 1),
('gerar_exercicios_jornada', 2, 'Gerar exercícios de jornada (IA)', 1),
('gerar_slides', 2, 'Gerar slides (IA)', 1),
('redacao_correcao_ia', 1, 'Correção de redação (IA)', 1),
('ai_agents', 1, 'Agentes de IA (TudinhaProf)', 1);
