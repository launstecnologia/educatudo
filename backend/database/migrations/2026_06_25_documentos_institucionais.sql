-- Documentos Institucionais (PPP, Regimento Escolar, etc.) e Documentos do Professor
-- Executar em cada banco de tenant. Idempotente.

CREATE TABLE IF NOT EXISTS `documentos_institucionais` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `tipo`             ENUM('ppp','regimento','plano_curso','calendario','outro') NOT NULL DEFAULT 'outro',
  `titulo`           VARCHAR(255) NOT NULL,
  `versao`           VARCHAR(40) NULL,
  `arquivo_key`      VARCHAR(255) NULL,
  `arquivo_nome`     VARCHAR(255) NULL,
  `arquivo_mime`     VARCHAR(120) NULL,
  `arquivo_tamanho`  INT(11) NULL,
  `observacao`       VARCHAR(500) NULL,
  `created_by`       INT(11) NULL,
  `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_inst_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `professores_documentos` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `professor_id`     INT(11) NOT NULL,
  `tipo`             VARCHAR(60) NOT NULL,
  `status`           ENUM('pendente','entregue','dispensado') NOT NULL DEFAULT 'pendente',
  `titulo`           VARCHAR(255) NULL,
  `observacao`       VARCHAR(500) NULL,
  `entregue_em`      DATETIME NULL,
  `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prof_doc` (`professor_id`, `tipo`),
  KEY `idx_prof_doc_prof` (`professor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
