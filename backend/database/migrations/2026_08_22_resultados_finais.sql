-- Resultados finais: homologação imutável, layouts por escola, emissão de
-- boletim/ficha/ata/histórico/relatórios a partir do snapshot homologado.
-- Tenant. Idempotente. Rollback: 2026_08_22_resultados_finais_rollback.sql

SET @db := DATABASE();

-- ── 1. Resultado acadêmico vigente (1 por aluno × turma × ano × período) ──
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico');
SET @sql := IF(@has=0,
  "CREATE TABLE `resultado_academico` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `aluno_id` INT NOT NULL,
    `turma_id` INT NOT NULL,
    `ano_letivo` SMALLINT UNSIGNED NOT NULL,
    `periodo_tipo` ENUM('bimestre','trimestre','semestre','ano') NOT NULL DEFAULT 'ano',
    `periodo_numero` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = ano inteiro; 1-4 = etapa',
    `versao` INT NOT NULL DEFAULT 1,
    `status` ENUM('em_andamento','homologado','reaberto') NOT NULL DEFAULT 'em_andamento',
    `situacao` VARCHAR(40) NOT NULL DEFAULT 'em_andamento',
    `rotulo` VARCHAR(120) NOT NULL DEFAULT 'Em andamento',
    `media_final` DECIMAL(8,2) NULL,
    `frequencia_percentual` DECIMAL(5,2) NULL,
    `faltas` INT NULL,
    `regra_id` INT NULL,
    `regra_versao` INT NULL,
    `conselho_sessao_id` INT NULL,
    `conselho_resultado` VARCHAR(80) NULL,
    `snapshot_json` MEDIUMTEXT NULL,
    `homologado_em` DATETIME NULL,
    `homologado_por` INT NULL,
    `reaberto_em` DATETIME NULL,
    `reaberto_por` INT NULL,
    `reaberto_motivo` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_resultado_aluno_periodo` (`aluno_id`, `turma_id`, `ano_letivo`, `periodo_tipo`, `periodo_numero`),
    KEY `idx_resultado_turma_periodo` (`turma_id`, `ano_letivo`, `periodo_tipo`, `periodo_numero`, `status`),
    KEY `idx_resultado_situacao` (`situacao`, `status`),
    KEY `idx_resultado_aluno` (`aluno_id`, `ano_letivo`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. Itens (componente) do resultado vigente ──
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico_itens');
SET @sql := IF(@has=0,
  "CREATE TABLE `resultado_academico_itens` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `resultado_id` INT NOT NULL,
    `materia_id` INT NULL,
    `materia_nome` VARCHAR(180) NOT NULL,
    `carga_horaria` INT NULL,
    `media` DECIMAL(8,2) NULL,
    `recuperacao` DECIMAL(8,2) NULL,
    `media_final` DECIMAL(8,2) NULL,
    `faltas` INT NULL,
    `frequencia_percentual` DECIMAL(5,2) NULL,
    `situacao` VARCHAR(40) NOT NULL DEFAULT 'em_andamento',
    `rotulo` VARCHAR(120) NOT NULL DEFAULT 'Em andamento',
    `situacao_especial` VARCHAR(40) NULL COMMENT 'dispensado, aproveitamento, progressao_parcial, dependencia',
    `observacao` VARCHAR(255) NULL,
    `ordem` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_resultado_itens_resultado` (`resultado_id`),
    CONSTRAINT `fk_resultado_itens_resultado` FOREIGN KEY (`resultado_id`) REFERENCES `resultado_academico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. Histórico de versões (reabertura / re-homologação) ──
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico_historico');
SET @sql := IF(@has=0,
  "CREATE TABLE `resultado_academico_historico` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `resultado_id` INT NOT NULL,
    `versao` INT NOT NULL,
    `status` VARCHAR(20) NOT NULL,
    `situacao` VARCHAR(40) NOT NULL,
    `rotulo` VARCHAR(120) NOT NULL,
    `snapshot_json` MEDIUMTEXT NULL,
    `motivo` TEXT NULL,
    `usuario_id` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_resultado_hist_resultado` (`resultado_id`, `versao`),
    CONSTRAINT `fk_resultado_hist_resultado` FOREIGN KEY (`resultado_id`) REFERENCES `resultado_academico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. Situações especiais (dispensa / aproveitamento / dependência) ──
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_situacoes_especiais');
SET @sql := IF(@has=0,
  "CREATE TABLE `resultado_situacoes_especiais` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `aluno_id` INT NOT NULL,
    `turma_id` INT NULL,
    `ano_letivo` SMALLINT UNSIGNED NOT NULL,
    `materia_id` INT NULL COMMENT 'NULL = situação geral do aluno',
    `tipo` ENUM('dispensado','aproveitamento','progressao_parcial','dependencia','transferencia','classificacao') NOT NULL,
    `observacao` TEXT NULL,
    `criado_por` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_resultado_esp_aluno_ano` (`aluno_id`, `ano_letivo`),
    KEY `idx_resultado_esp_tipo` (`tipo`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 5. Layout escolhido pela escola por tipo de documento ──
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_documento_layouts');
SET @sql := IF(@has=0,
  "CREATE TABLE `resultado_documento_layouts` (
    `tipo` VARCHAR(40) NOT NULL,
    `modelo_codigo` VARCHAR(80) NOT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`tipo`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `resultado_documento_layouts` (`tipo`, `modelo_codigo`)
SELECT * FROM (
  SELECT 'boletim' AS tipo, 'resultado_boletim_padrao' AS modelo_codigo UNION ALL
  SELECT 'ficha_individual', 'resultado_ficha_individual' UNION ALL
  SELECT 'ata_resultados', 'resultado_ata_finais' UNION ALL
  SELECT 'historico', 'resultado_historico' UNION ALL
  SELECT 'relatorio_aprovados', 'resultado_relatorio_padrao' UNION ALL
  SELECT 'relatorio_reprovados', 'resultado_relatorio_padrao' UNION ALL
  SELECT 'relatorio_recuperacao', 'resultado_relatorio_padrao' UNION ALL
  SELECT 'relatorio_frequencia', 'resultado_relatorio_padrao' UNION ALL
  SELECT 'relatorio_desempenho', 'resultado_relatorio_padrao' UNION ALL
  SELECT 'relatorio_classificacao', 'resultado_relatorio_padrao' UNION ALL
  SELECT 'relatorio_pendencias', 'resultado_relatorio_padrao' UNION ALL
  SELECT 'relatorio_fechamento', 'resultado_relatorio_padrao'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `resultado_documento_layouts` d WHERE d.tipo = seed.tipo);

-- ── 6. Configuração do fechamento ──
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_fechamento_config');
SET @sql := IF(@has=0,
  "CREATE TABLE `resultado_fechamento_config` (
    `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `exigir_conselho` TINYINT(1) NOT NULL DEFAULT 0,
    `exigir_frequencia` TINYINT(1) NOT NULL DEFAULT 0,
    `exigir_notas` TINYINT(1) NOT NULL DEFAULT 1,
    `atualizado_por` INT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `resultado_fechamento_config` (`id`, `exigir_conselho`, `exigir_frequencia`, `exigir_notas`)
SELECT 1, 0, 0, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `resultado_fechamento_config` WHERE `id` = 1);

-- ── 7. Emissões oficiais ──
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_documento_emissoes');
SET @sql := IF(@has=0,
  "CREATE TABLE `resultado_documento_emissoes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `tipo` VARCHAR(40) NOT NULL,
    `modelo_codigo` VARCHAR(80) NULL,
    `aluno_id` INT NULL,
    `turma_id` INT NULL,
    `resultado_id` INT NULL,
    `ano_letivo` SMALLINT UNSIGNED NULL,
    `periodo_tipo` VARCHAR(20) NULL,
    `periodo_numero` TINYINT UNSIGNED NULL,
    `numero` INT NOT NULL DEFAULT 0,
    `hash_validacao` CHAR(64) NULL,
    `snapshot_json` MEDIUMTEXT NULL,
    `emitido_por` INT NULL,
    `emitido_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_resultado_emis_tipo_ano` (`tipo`, `ano_letivo`),
    KEY `idx_resultado_emis_aluno` (`aluno_id`),
    KEY `idx_resultado_emis_turma` (`turma_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FKs opcionais (tenant antigo pode não ter as tabelas alvo)
SET @has_res := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico');
SET @has_alunos := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos');
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico' AND CONSTRAINT_NAME='fk_resultado_aluno' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0 AND @has_res>0 AND @has_alunos>0,
  "ALTER TABLE `resultado_academico` ADD CONSTRAINT `fk_resultado_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_turmas := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas');
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico' AND CONSTRAINT_NAME='fk_resultado_turma' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0 AND @has_res>0 AND @has_turmas>0,
  "ALTER TABLE `resultado_academico` ADD CONSTRAINT `fk_resultado_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 8. Seed dos modelos HTML (só se a tabela de modelos existir) ──
SET @has_mod := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');

-- Ficha individual
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'resultado_ficha_individual',
     'Ficha Individual (padrão)',
     'Documento acadêmico do aluno no ano letivo. Placeholders: {{quadro_notas_html}}, {{situacao_final}}, {{frequencia_percentual}}.',
     '<div style=\"text-align:center;margin-bottom:12px;\"><h1 style=\"margin:0;font-size:16pt;\">{{escola_nome}}</h1><p style=\"margin:4px 0;font-size:9pt;\">{{escola_endereco}} {{escola_cnpj}}</p><h2 style=\"margin:10px 0 0;font-size:13pt;\">Ficha Individual do Aluno</h2><p style=\"margin:4px 0;font-size:9pt;\">Ano letivo {{ano_letivo}} · {{periodo_label}}</p></div>',
     '<table class=\"dados\"><tr><td class=\"label\">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class=\"label\">Matrícula / RA</td><td>{{aluno_codigo}}</td></tr><tr><td class=\"label\">Nascimento</td><td>{{aluno_data_nasc}}</td></tr><tr><td class=\"label\">Turma</td><td>{{turma_nome}}</td></tr><tr><td class=\"label\">Série / Curso</td><td>{{serie}} · {{curso_nome}}</td></tr><tr><td class=\"label\">Frequência</td><td>{{frequencia_percentual}}</td></tr><tr><td class=\"label\">Situação final</td><td>{{situacao_final}}</td></tr></table><h3>Componentes curriculares</h3>{{quadro_notas_html}}<p>{{observacoes}}</p>',
     '<div style=\"margin-top:28px;font-size:8pt;color:#666;text-align:center;\">Emitido em {{data_hoje}} · {{escola_nome}} · nº {{numero}}/{{ano}}</div>',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_ficha_individual')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ata de resultados finais
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'resultado_ata_finais',
     'Ata de Resultados Finais (padrão)',
     'Documento coletivo da turma. Placeholders: {{tabela_html}}, {{turma_nome}}, {{periodo_label}}.',
     '<div style=\"text-align:center;margin-bottom:12px;\"><h1 style=\"margin:0;font-size:16pt;\">{{escola_nome}}</h1><p style=\"margin:4px 0;font-size:9pt;\">{{escola_docs}}</p><h2 style=\"margin:10px 0 0;font-size:13pt;\">Ata de Resultados Finais</h2><p style=\"margin:4px 0;font-size:9pt;\">Turma {{turma_nome}} · {{periodo_label}} / {{ano_letivo}}</p></div>',
     '<p>A direção registra os resultados finais dos estudantes da turma <strong>{{turma_nome}}</strong> no período <strong>{{periodo_label}}</strong> do ano letivo <strong>{{ano_letivo}}</strong>.</p>{{tabela_html}}<p style=\"margin-top:16px;\">Total: {{total_alunos}} alunos · Homologados: {{total_homologados}} · Pendências: {{total_pendencias}}</p><p>{{observacoes}}</p>',
     '<div class=\"assinaturas\"><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{diretor_nome}}</div><div class=\"cargo\">Direção</div></div><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{secretario_nome}}</div><div class=\"cargo\">Secretaria</div></div></div><p style=\"text-align:center;font-size:8pt;color:#666;margin-top:16px;\">{{cidade_data}} · nº {{numero}}/{{ano}}</p>',
     'paisagem', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_ata_finais')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Boletim oficial (a partir do snapshot)
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'resultado_boletim_padrao',
     'Boletim oficial (padrão)',
     'Boletim emitido a partir do resultado homologado. {{quadro_notas_html}}.',
     '<div style=\"text-align:center;\"><h1 style=\"margin:0;font-size:15pt;\">{{escola_nome}}</h1><h2 style=\"margin:8px 0 0;font-size:13pt;\">Boletim Escolar</h2><p style=\"font-size:9pt;\">{{aluno_nome}} · {{turma_nome}} · {{ano_letivo}}</p></div>',
     '<p><strong>Situação:</strong> {{situacao_final}} &nbsp;|&nbsp; <strong>Frequência:</strong> {{frequencia_percentual}}</p>{{quadro_notas_html}}<p>{{observacoes}}</p>',
     '<p style=\"text-align:center;font-size:8pt;color:#666;\">Emitido em {{data_hoje}} · {{assinante_nome}} ({{assinante_cargo}})</p>',
     'paisagem', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_boletim_padrao')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Relatório genérico
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'resultado_relatorio_padrao',
     'Relatório acadêmico (padrão)',
     'Usado por aprovados/reprovados/frequência/pendências. {{titulo_relatorio}} + {{tabela_html}}.',
     '<div style=\"text-align:center;\"><h1 style=\"margin:0;font-size:15pt;\">{{escola_nome}}</h1><h2 style=\"margin:8px 0 0;font-size:13pt;\">{{titulo_relatorio}}</h2><p style=\"font-size:9pt;\">{{turma_nome}} · {{periodo_label}} / {{ano_letivo}}</p></div>',
     '{{tabela_html}}<p style=\"margin-top:12px;\">{{observacoes}}</p>',
     '<p style=\"text-align:center;font-size:8pt;color:#666;\">Emitido em {{data_hoje}} · {{escola_nome}}</p>',
     'paisagem', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_relatorio_padrao')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Histórico (layout extra; o workflow oficial em historico_documentos continua)
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'resultado_historico',
     'Histórico Escolar (layout homologado)',
     'Layout opcional alimentado pelos resultados homologados. O workflow jurídico continua em /historico-escolar.',
     '<div style=\"text-align:center;\"><h1 style=\"margin:0;font-size:15pt;\">{{escola_nome}}</h1><h2 style=\"margin:8px 0 0;\">Histórico Escolar</h2><p>{{aluno_nome}} · {{aluno_cpf}}</p></div>',
     '{{quadro_notas_html}}<p><strong>Situação:</strong> {{situacao_final}}</p>',
     '<p style=\"text-align:center;font-size:8pt;\">{{cidade_data}}</p>',
     'paisagem', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_historico')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
