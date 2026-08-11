-- Calendário Letivo (dias letivos / carga horária anual — LDB art. 24)
-- Configuração anual de metas (mínimo de 200 dias / 800 horas) e eventos que
-- afetam a contagem de dias letivos (feriados, recessos, reposições, suspensões).
-- Executar em cada banco de tenant. Idempotente.

CREATE TABLE IF NOT EXISTS `calendario_letivo` (
  `id`                   INT(11) NOT NULL AUTO_INCREMENT,
  `ano`                  INT(11) NOT NULL,
  `dias_meta`            INT(11) NOT NULL DEFAULT 200,
  `carga_horaria_meta`   INT(11) NOT NULL DEFAULT 800,
  `observacao`           VARCHAR(255) NULL,
  `created_at`           DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_calendario_ano` (`ano`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendario_letivo_eventos` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `calendario_id` INT(11) NOT NULL,
  `data_inicio`   DATE NOT NULL,
  `data_fim`      DATE NOT NULL,
  `tipo`          ENUM('feriado','recesso','reposicao','evento','suspensao') NOT NULL DEFAULT 'feriado',
  `descricao`     VARCHAR(255) NOT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cal_eventos_calendario` (`calendario_id`),
  KEY `idx_cal_eventos_data` (`data_inicio`, `data_fim`),
  CONSTRAINT `fk_cal_eventos_calendario` FOREIGN KEY (`calendario_id`) REFERENCES `calendario_letivo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
