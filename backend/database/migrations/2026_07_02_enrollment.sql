-- Módulo de Matrículas e Rematrículas
-- Cria tabela enrollment (separada da matricula existente, que é vínculo operacional).
-- Ao ser confirmada, gera/atualiza o registro em `matricula`.

CREATE TABLE IF NOT EXISTS `enrollment` (
  `id`                 int(11)      NOT NULL AUTO_INCREMENT,
  `tipo`               enum('nova','rematricula','transferencia') NOT NULL DEFAULT 'nova',
  `status`             enum('rascunho','aguardando_contrato','aguardando_assinatura','confirmada','enturmada','abandonada','cancelada','lista_espera') NOT NULL DEFAULT 'rascunho',
  `aluno_id`           int(11)      DEFAULT NULL,
  `ano_letivo_id`      int(11)      DEFAULT NULL,
  `turma_id`           int(11)      DEFAULT NULL,
  `serie_id`           int(11)      DEFAULT NULL,
  -- Dados do aluno (capturados no momento da matrícula nova)
  `aluno_nome`         varchar(255) NOT NULL DEFAULT '',
  `aluno_cpf`          varchar(20)  DEFAULT NULL,
  `aluno_data_nasc`    date         DEFAULT NULL,
  `aluno_genero`       varchar(30)  DEFAULT NULL,
  `aluno_email`        varchar(255) DEFAULT NULL,
  `aluno_telefone`     varchar(30)  DEFAULT NULL,
  -- Responsável financeiro/legal
  `resp_nome`          varchar(255) NOT NULL DEFAULT '',
  `resp_cpf`           varchar(20)  DEFAULT NULL,
  `resp_email`         varchar(255) DEFAULT NULL,
  `resp_telefone`      varchar(30)  DEFAULT NULL,
  `resp_parentesco`    varchar(60)  DEFAULT NULL,
  `resp_endereco`      text         DEFAULT NULL,
  -- Contrato e assinatura
  `contrato_pdf_path`  varchar(500) DEFAULT NULL,
  `contrato_token`     varchar(64)  DEFAULT NULL,
  `contrato_hash`      varchar(64)  DEFAULT NULL,
  `assinado_em`        datetime     DEFAULT NULL,
  `assinante_ip`       varchar(45)  DEFAULT NULL,
  `assinante_nome`     varchar(255) DEFAULT NULL,
  -- Metadados
  `origem`             enum('interno','site','whatsapp','indicacao','evento') NOT NULL DEFAULT 'interno',
  `observacoes`        text         DEFAULT NULL,
  `expira_em`          datetime     DEFAULT NULL,
  `criado_por`         int(11)      DEFAULT NULL,
  `created_at`         datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enrollment_contrato_token` (`contrato_token`),
  KEY `idx_enrollment_aluno`      (`aluno_id`),
  KEY `idx_enrollment_turma`      (`turma_id`),
  KEY `idx_enrollment_ano_letivo` (`ano_letivo_id`),
  KEY `idx_enrollment_status`     (`status`),
  KEY `idx_enrollment_tipo`       (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de auditoria de transições de estado
CREATE TABLE IF NOT EXISTS `enrollment_audit` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `enrollment_id` int(11)      NOT NULL,
  `status_de`     varchar(50)  DEFAULT NULL,
  `status_para`   varchar(50)  NOT NULL,
  `acao`          varchar(100) DEFAULT NULL,
  `usuario_id`    int(11)      DEFAULT NULL,
  `usuario_nome`  varchar(255) DEFAULT NULL,
  `ip`            varchar(45)  DEFAULT NULL,
  `created_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_enrollment` (`enrollment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Score de propensão à rematrícula por aluno/ciclo
CREATE TABLE IF NOT EXISTS `enrollment_score` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `aluno_id`     int(11)      NOT NULL,
  `ciclo`        int(4)       NOT NULL,
  `score`        tinyint(3)   NOT NULL DEFAULT 0,
  `faixa`        enum('verde','amarelo','vermelho') NOT NULL DEFAULT 'verde',
  `freq_n`       tinyint(3)   DEFAULT NULL,
  `desemp_n`     tinyint(3)   DEFAULT NULL,
  `inad_n`       tinyint(3)   DEFAULT NULL,
  `engaj_n`      tinyint(3)   DEFAULT NULL,
  `tempo_n`      tinyint(3)   DEFAULT NULL,
  `motivos`      text         DEFAULT NULL,
  `calculado_em` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_score_aluno_ciclo` (`aluno_id`, `ciclo`),
  KEY `idx_score_faixa` (`faixa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
