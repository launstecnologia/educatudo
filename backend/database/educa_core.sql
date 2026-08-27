-- EducaTudo — schema base do tenant (somente estrutura, sem dados)
-- Gerado em 2026-08-25 00:21:10
-- Usado ao criar escola no Master (MysqlProvisioningService::aplicarSchemaBaseTenant).

SET NAMES utf8mb4;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;

--
-- admin_perfis_permissao
--
CREATE TABLE IF NOT EXISTS `admin_perfis_permissao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo_base` enum('dev','diretor','coordenador','financeiro','secretaria') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `permissoes_json` json NOT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_perfis_permissao_nome` (`nome`),
  KEY `idx_admin_perfis_permissao_tipo_base` (`tipo_base`),
  KEY `idx_admin_perfis_permissao_ativo` (`ativo`),
  KEY `idx_admin_perfis_permissao_criado_por` (`criado_por`),
  CONSTRAINT `fk_admin_perfis_permissao_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- ai_jobs
--
CREATE TABLE IF NOT EXISTS `ai_jobs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `job_type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'gerar_exercicio | gerar_prova | corrigir_redacao | gerar_flashcards | gerar_slides',
  `status` enum('pending','processing','done','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payload` json NOT NULL COMMENT 'Parâmetros de entrada serializados',
  `result` json DEFAULT NULL COMMENT 'Resultado retornado pela IA',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` int unsigned DEFAULT NULL COMMENT 'Usuário que disparou o job',
  `user_role` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'aluno | professor | admin',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_user` (`user_id`),
  KEY `idx_cleanup` (`completed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- alertas_sensiveis
--
CREATE TABLE IF NOT EXISTS `alertas_sensiveis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `categoria` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `nivel` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `mensagem_resumo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `mensagem_aluno` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Texto completo da mensagem do aluno',
  `mensagem_chat_id` int DEFAULT NULL COMMENT 'ID em mensagens_chat para localizar resposta Tudinha',
  `status` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'novo',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  `anonymized_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `retention_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_alertas_aluno` (`aluno_id`),
  KEY `idx_alertas_status` (`status`),
  KEY `idx_alertas_nivel` (`nivel`),
  KEY `idx_alertas_turma` (`turma_id`),
  KEY `idx_alertas_expires` (`expires_at`),
  KEY `idx_alertas_anonymized` (`anonymized_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- alertas_sensiveis_acoes
--
CREATE TABLE IF NOT EXISTS `alertas_sensiveis_acoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `alerta_id` int NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `acao` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `observacoes` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `anonymized_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `retention_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_alerta` (`alerta_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_alertas_acoes_expires` (`expires_at`),
  KEY `idx_alertas_acoes_anonymized` (`anonymized_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- aluno_agenda_itens
--
CREATE TABLE IF NOT EXISTS `aluno_agenda_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pessoal',
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `data` date NOT NULL,
  `hora` time DEFAULT NULL,
  `banca` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `local` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno_data` (`aluno_id`,`data`),
  CONSTRAINT `aai_fk_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- alunos
--
CREATE TABLE IF NOT EXISTS `alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `nome_social` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `celular` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cpf` varchar(14) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `rg` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `logradouro` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `numero` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `complemento` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `bairro` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cidade` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `uf` char(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cep` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `foto_url` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `senha_hash` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ra` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Registro Acadêmico - login do aluno',
  `codigo_aluno` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `unidade_id` int DEFAULT NULL,
  `serie` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `data_nasc` date DEFAULT NULL,
  `responsavel_id` int DEFAULT NULL COMMENT 'Pai/Responsável vinculado',
  `ativo` tinyint(1) DEFAULT '1',
  `status` enum('ACTIVE','INACTIVE','GRADUATED','SUSPENDED','PENDING') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `pagante` tinyint(1) DEFAULT '1' COMMENT 'Indica se a escola paga por este aluno',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `nickname` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT '1',
  `sexo` enum('M','F','N') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `nome_mae` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `nome_pai` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `codigo_inep` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `nacionalidade` varchar(60) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `naturalidade` varchar(120) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `uf_nascimento` char(2) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cor_raca` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `orgao_emissor` varchar(30) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `uf_rg` char(2) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `certidao_nascimento` varchar(80) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `certidao_livro` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `certidao_folha` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `certidao_termo` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `nis` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `passaporte` varchar(30) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `rne` varchar(30) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `zona` varchar(10) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `pais` varchar(60) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `email_secundario` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ra` (`ra`),
  UNIQUE KEY `nickname` (`nickname`),
  KEY `idx_turma` (`turma_id`),
  KEY `idx_responsavel` (`responsavel_id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_alunos_codigo_aluno` (`codigo_aluno`),
  KEY `idx_alunos_cpf` (`cpf`),
  KEY `idx_alunos_unidade` (`unidade_id`),
  CONSTRAINT `alunos_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `alunos_ibfk_3` FOREIGN KEY (`responsavel_id`) REFERENCES `responsaveis` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- alunos_acoes_diarias
--
CREATE TABLE IF NOT EXISTS `alunos_acoes_diarias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `acao` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de ação: gerar_tema_redacao, corrigir_redacao, etc.',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `acao` (`acao`),
  KEY `created_at` (`created_at`),
  KEY `idx_aluno_acao_data` (`aluno_id`,`acao`,`created_at`),
  CONSTRAINT `alunos_acoes_diarias_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registra ações diárias dos alunos para controle de limites';

--
-- alunos_documentos
--
CREATE TABLE IF NOT EXISTS `alunos_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `arquivo_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_tamanho` int DEFAULT NULL,
  `observacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entregue_em` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alunos_documentos_aluno` (`aluno_id`),
  KEY `idx_alunos_documentos_aluno_tipo` (`aluno_id`,`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- alunos_ficha_complementar
--
CREATE TABLE IF NOT EXISTS `alunos_ficha_complementar` (
  `aluno_id` int NOT NULL,
  `tipo_sanguineo` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alergias` text COLLATE utf8mb4_unicode_ci,
  `medicamentos_uso` text COLLATE utf8mb4_unicode_ci,
  `condicoes_cronicas` text COLLATE utf8mb4_unicode_ci,
  `deficiencias_obs` text COLLATE utf8mb4_unicode_ci,
  `plano_saude` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plano_saude_numero` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hospital_referencia` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contato_emergencia_nome` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contato_emergencia_telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contato_emergencia_parentesco` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `restricoes_alimentares` text COLLATE utf8mb4_unicode_ci,
  `alimentacao_obs` text COLLATE utf8mb4_unicode_ci,
  `usa_transporte_escolar` tinyint(1) NOT NULL DEFAULT '0',
  `transporte_tipo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transporte_rota` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transporte_ponto` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transporte_responsavel` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transporte_telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes_gerais` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- alunos_historico_status
--
CREATE TABLE IF NOT EXISTS `alunos_historico_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `old_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `observation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` int NOT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_status_student` (`student_id`),
  KEY `idx_student_status_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- alunos_ocorrencias
--
CREATE TABLE IF NOT EXISTS `alunos_ocorrencias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `categoria_id` int DEFAULT NULL,
  `data_ocorrencia` datetime NOT NULL,
  `titulo` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `detalhe` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `nivel_gravidade` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'aberta',
  `turma_id` int DEFAULT NULL,
  `ano_letivo_id` int DEFAULT NULL,
  `diario_aula_id` int DEFAULT NULL,
  `materia_id` int DEFAULT NULL,
  `local` varchar(120) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `encaminhamento` text COLLATE utf8mb3_unicode_ci,
  `atitude_coordenacao` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `retorno_em` date DEFAULT NULL,
  `enviar_pais` tinyint(1) NOT NULL DEFAULT '0',
  `responsavel_comunicado_em` datetime DEFAULT NULL,
  `encerrado_em` datetime DEFAULT NULL,
  `encerrado_por` int DEFAULT NULL,
  `criado_por` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ocorrencias_aluno` (`aluno_id`),
  KEY `idx_ocorrencias_data` (`data_ocorrencia`),
  KEY `idx_ocorrencias_gravidade` (`nivel_gravidade`),
  KEY `idx_ocorrencias_enviar` (`enviar_pais`),
  KEY `idx_ocorrencias_status` (`status`),
  KEY `idx_ocorrencias_categoria` (`categoria_id`),
  KEY `idx_ocorrencias_turma` (`turma_id`),
  KEY `idx_ocorrencias_aula` (`diario_aula_id`),
  CONSTRAINT `fk_ocorrencias_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `ocorrencias_categorias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ocorrencias_diario_aula` FOREIGN KEY (`diario_aula_id`) REFERENCES `diario_aulas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- alunos_ocorrencias_itens
--
CREATE TABLE IF NOT EXISTS `alunos_ocorrencias_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ocorrencia_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ocorrencia` (`ocorrencia_id`),
  KEY `idx_aluno` (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- alunos_onboarding
--
CREATE TABLE IF NOT EXISTS `alunos_onboarding` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `meu_sonho` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objetivo_principal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nivel_comprometimento` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pontos_dificuldade` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempo_estudo_dia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pontos_fortes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estilo_aprendizado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completado` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno` (`aluno_id`),
  KEY `idx_completado` (`completado`),
  CONSTRAINT `alunos_onboarding_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- alunos_responsaveis
--
CREATE TABLE IF NOT EXISTS `alunos_responsaveis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `responsavel_id` int NOT NULL,
  `tipo_vinculo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_financeiro` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `parentesco` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profissao` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pode_retirar` tinyint(1) NOT NULL DEFAULT '0',
  `recebe_boletos` tinyint(1) NOT NULL DEFAULT '0',
  `recebe_boletim` tinyint(1) NOT NULL DEFAULT '0',
  `recebe_notificacoes` tinyint(1) NOT NULL DEFAULT '0',
  `responsavel_pedagogico` tinyint(1) NOT NULL DEFAULT '0',
  `guarda_judicial` tinyint(1) NOT NULL DEFAULT '0',
  `assina_documentos` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aluno_responsavel` (`aluno_id`,`responsavel_id`),
  KEY `idx_alunos_responsaveis_aluno` (`aluno_id`),
  KEY `idx_alunos_responsaveis_responsavel` (`responsavel_id`),
  KEY `idx_alunos_responsaveis_financeiro` (`is_financeiro`),
  CONSTRAINT `fk_alunos_responsaveis_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alunos_responsaveis_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `responsaveis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- alunos_seguranca
--
CREATE TABLE IF NOT EXISTS `alunos_seguranca` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `pergunta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resposta_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno` (`aluno_id`),
  CONSTRAINT `alunos_seguranca_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- alunos_sessoes_acesso
--
CREATE TABLE IF NOT EXISTS `alunos_sessoes_acesso` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `login_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_atividade_at` datetime DEFAULT NULL,
  `contexto_tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contexto_id` int unsigned DEFAULT NULL,
  `contexto_label` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logout_at` datetime DEFAULT NULL,
  `tempo_uso_segundos` int DEFAULT NULL COMMENT 'Tempo em segundos desde login até logout',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('ativo','finalizado','expirado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `session_id` (`session_id`),
  KEY `login_at` (`login_at`),
  KEY `status` (`status`),
  KEY `idx_ativo` (`aluno_id`,`status`),
  KEY `idx_online` (`status`,`login_at`),
  CONSTRAINT `fk_sessoes_acesso_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- alunos_turma_chamada
--
CREATE TABLE IF NOT EXISTS `alunos_turma_chamada` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `ano_letivo_id` int NOT NULL,
  `numero_chamada` smallint unsigned NOT NULL,
  `entrada_tardia` tinyint(1) NOT NULL DEFAULT '0',
  `marcado_tr` tinyint(1) NOT NULL DEFAULT '0',
  `data_entrada_turma` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aluno_turma_ano` (`aluno_id`,`turma_id`,`ano_letivo_id`),
  UNIQUE KEY `uq_turma_numero` (`turma_id`,`ano_letivo_id`,`numero_chamada`),
  KEY `idx_chamada_turma` (`turma_id`,`ano_letivo_id`),
  KEY `fk_chamada_ano` (`ano_letivo_id`),
  CONSTRAINT `fk_chamada_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chamada_ano` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_chamada_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- alunos_turmas_historico
--
CREATE TABLE IF NOT EXISTS `alunos_turmas_historico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `ano_letivo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_turma` (`turma_id`),
  KEY `idx_ano` (`ano_letivo`),
  CONSTRAINT `alunos_turmas_historico_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alunos_turmas_historico_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ano_letivo
--
CREATE TABLE IF NOT EXISTS `ano_letivo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ano` int NOT NULL COMMENT 'Ano civil do ano letivo (ex: 2025)',
  `data_inicio` date DEFAULT NULL COMMENT 'Início do ano letivo',
  `data_fim` date DEFAULT NULL COMMENT 'Fim do ano letivo',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ano_letivo_ano` (`ano`),
  KEY `idx_ano_letivo_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- apostila_ia_chunks
--
CREATE TABLE IF NOT EXISTS `apostila_ia_chunks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `apostila_id` bigint unsigned NOT NULL,
  `pagina_inicio` int NOT NULL,
  `pagina_fim` int NOT NULL,
  `conteudo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata_json` json DEFAULT NULL,
  `embedding_provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'openai',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apostila` (`apostila_id`),
  KEY `idx_paginas` (`pagina_inicio`,`pagina_fim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- apostila_ia_conversas
--
CREATE TABLE IF NOT EXISTS `apostila_ia_conversas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `apostila_id` bigint unsigned NOT NULL,
  `professor_id` bigint unsigned DEFAULT NULL,
  `sessao_id` bigint unsigned DEFAULT NULL,
  `pergunta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resposta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paginas_usadas` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apostila_professor` (`apostila_id`,`professor_id`),
  KEY `idx_sessao` (`sessao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- apostila_ia_exercicios
--
CREATE TABLE IF NOT EXISTS `apostila_ia_exercicios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `apostila_id` bigint unsigned NOT NULL,
  `pagina` int NOT NULL,
  `capitulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tema` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` enum('objetiva','discursiva','verdadeiro_falso','associacao','outro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `enunciado` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativas` json DEFAULT NULL,
  `gabarito` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dificuldade` enum('facil','media','dificil','nao_identificada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_identificada',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apostila_pagina` (`apostila_id`,`pagina`),
  KEY `idx_tema` (`tema`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- apostila_ia_paginas
--
CREATE TABLE IF NOT EXISTS `apostila_ia_paginas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `apostila_id` bigint unsigned NOT NULL,
  `numero_pagina` int NOT NULL,
  `texto_extraido` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `imagem_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apostila_pagina` (`apostila_id`,`numero_pagina`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- apostila_ia_sessoes
--
CREATE TABLE IF NOT EXISTS `apostila_ia_sessoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `apostila_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `usuario_tipo` enum('professor','aluno') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nova conversa',
  `resumo` text COLLATE utf8mb4_unicode_ci COMMENT 'Resumo rolling da sessão para contexto longo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apostila_usuario` (`apostila_id`,`usuario_id`,`usuario_tipo`),
  KEY `idx_apostila_updated` (`apostila_id`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- apostila_ia_turmas
--
CREATE TABLE IF NOT EXISTS `apostila_ia_turmas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `apostila_id` bigint unsigned NOT NULL,
  `turma_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_apostila_turma` (`apostila_id`,`turma_id`),
  KEY `idx_turma` (`turma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- apostilas_ia
--
CREATE TABLE IF NOT EXISTS `apostilas_ia` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `escola_id` bigint unsigned DEFAULT NULL,
  `serie_id` bigint unsigned DEFAULT NULL,
  `turma_id` bigint unsigned DEFAULT NULL,
  `disciplina_id` bigint unsigned DEFAULT NULL,
  `professor_id` bigint unsigned DEFAULT NULL,
  `arquivo_pdf` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pendente','processando','pronto','erro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `total_paginas` int NOT NULL DEFAULT '0',
  `erro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `vector_store_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID do vector store OpenAI gerado pelo microserviÃ§o Python',
  `sugestoes_chat` json DEFAULT NULL COMMENT 'Sugestões dinâmicas de perguntas para o chat',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legado_modulo_id` int DEFAULT NULL COMMENT 'ID de modulos_apostilas de origem',
  `capa_personalizada` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_professor` (`professor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- assinaturas_creditos
--
CREATE TABLE IF NOT EXISTS `assinaturas_creditos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_type` enum('aluno','professor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `plano_id` int unsigned NOT NULL,
  `inicio_em` date NOT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT '1',
  `ultima_recarga_em` date DEFAULT NULL COMMENT 'Último mês em que foi recarregado',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assinaturas_user` (`user_type`,`user_id`),
  KEY `idx_assinaturas_ativa` (`ativa`),
  KEY `idx_assinaturas_plano` (`plano_id`),
  CONSTRAINT `fk_assinaturas_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos_creditos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assinaturas ativas de planos de créditos';

--
-- assistente_conversas
--
CREATE TABLE IF NOT EXISTS `assistente_conversas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL COMMENT 'usuarios.id do admin dono do chat',
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nova conversa',
  `aluno_id` int DEFAULT NULL,
  `aluno_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assistente_conv_usuario` (`usuario_id`,`deleted_at`,`updated_at`),
  KEY `idx_assistente_conv_aluno` (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- assistente_mensagens
--
CREATE TABLE IF NOT EXISTS `assistente_mensagens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversa_id` int unsigned NOT NULL,
  `role` enum('user','assistant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `painel_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assistente_msg_conversa` (`conversa_id`,`id`),
  CONSTRAINT `fk_assistente_msg_conversa` FOREIGN KEY (`conversa_id`) REFERENCES `assistente_conversas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- aulas_online
--
CREATE TABLE IF NOT EXISTS `aulas_online` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `plataforma` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_aula` varchar(700) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `inicio_em` datetime NOT NULL,
  `fim_em` datetime DEFAULT NULL,
  `enviar_para_todos` tinyint(1) NOT NULL DEFAULT '0',
  `publicado` tinyint(1) NOT NULL DEFAULT '1',
  `gerar_panda` tinyint(1) NOT NULL DEFAULT '0',
  `panda_integracao_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_solicitada',
  `panda_integracao_erro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `panda_integracao_tentativas` int NOT NULL DEFAULT '0',
  `panda_integracao_ultima_tentativa_em` datetime DEFAULT NULL,
  `panda_live_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_stream_key_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_live_player` varchar(700) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_live_hls` varchar(700) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_rtmp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_stream_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_video_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_player` varchar(700) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_hls` varchar(700) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_synced_at` datetime DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `jaas_recording_url` varchar(1200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jaas_recording_path` varchar(700) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jaas_recording_session_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jaas_recording_uploaded_at` datetime DEFAULT NULL,
  `jaas_recording_webhook_raw` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `link_gravacao` varchar(1200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL da gravação Jitsi/Jibri enviada via webhook',
  PRIMARY KEY (`id`),
  KEY `idx_aulas_online_inicio` (`inicio_em`),
  KEY `idx_aulas_online_publicado` (`publicado`),
  KEY `idx_aulas_online_ativo` (`ativo`),
  KEY `idx_aulas_online_criado_por` (`criado_por`),
  KEY `idx_aulas_online_panda_status` (`panda_integracao_status`),
  CONSTRAINT `fk_aulas_online_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- aulas_online_arquivos
--
CREATE TABLE IF NOT EXISTS `aulas_online_arquivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aula_id` int NOT NULL,
  `nome_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aula_id` (`aula_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- aulas_online_chat_messages
--
CREATE TABLE IF NOT EXISTS `aulas_online_chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aula_online_id` int NOT NULL,
  `user_id` int NOT NULL,
  `user_tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_nome` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aulas_online_chat_aula` (`aula_online_id`),
  KEY `idx_aulas_online_chat_created` (`created_at`),
  KEY `idx_aulas_online_chat_ativo` (`ativo`),
  CONSTRAINT `fk_aulas_online_chat_aula` FOREIGN KEY (`aula_online_id`) REFERENCES `aulas_online` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- aulas_online_turmas
--
CREATE TABLE IF NOT EXISTS `aulas_online_turmas` (
  `aula_online_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`aula_online_id`,`turma_id`),
  KEY `idx_aulas_online_turmas_turma` (`turma_id`),
  CONSTRAINT `fk_aulas_online_turmas_aula` FOREIGN KEY (`aula_online_id`) REFERENCES `aulas_online` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aulas_online_turmas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_atividade_entrega_arquivos
--
CREATE TABLE IF NOT EXISTS `ava_atividade_entrega_arquivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entrega_id` int NOT NULL,
  `arquivo_key` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_entrega_arq_entrega` (`entrega_id`),
  CONSTRAINT `fk_ava_entrega_arq_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `ava_atividade_entregas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_atividade_entregas
--
CREATE TABLE IF NOT EXISTS `ava_atividade_entregas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `atividade_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `texto` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `link` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('rascunho','enviada','avaliada','reenviar') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'enviada',
  `nota` decimal(5,2) DEFAULT NULL,
  `feedback` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rubrica_resultado_json` json DEFAULT NULL,
  `atrasada` tinyint(1) NOT NULL DEFAULT '0',
  `enviada_em` datetime DEFAULT NULL,
  `avaliada_em` datetime DEFAULT NULL,
  `avaliada_por` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_entrega` (`atividade_id`,`aluno_id`),
  KEY `idx_ava_entrega_aluno` (`aluno_id`),
  CONSTRAINT `fk_ava_entrega_atividade` FOREIGN KEY (`atividade_id`) REFERENCES `ava_atividades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_atividades
--
CREATE TABLE IF NOT EXISTS `ava_atividades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `disciplina_id` int NOT NULL,
  `modulo_id` int DEFAULT NULL,
  `aula_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `rubrica_id` int DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `instrucoes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tipo_entrega` enum('arquivo','texto','link','multiplo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'arquivo',
  `peso` decimal(5,2) NOT NULL DEFAULT '1.00',
  `nota_maxima` decimal(5,2) NOT NULL DEFAULT '10.00',
  `data_abertura` datetime DEFAULT NULL,
  `data_entrega` datetime DEFAULT NULL,
  `aceita_atraso` tinyint(1) NOT NULL DEFAULT '0',
  `permite_reenvio` tinyint(1) NOT NULL DEFAULT '1',
  `max_arquivos` int NOT NULL DEFAULT '5',
  `tamanho_max_mb` int NOT NULL DEFAULT '20',
  `status` enum('rascunho','publicada','encerrada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publicada',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_atv_disc` (`disciplina_id`),
  KEY `idx_ava_atv_modulo` (`modulo_id`),
  KEY `idx_ava_atv_aula` (`aula_id`),
  KEY `idx_ava_atv_professor` (`professor_id`),
  KEY `idx_ava_atv_rubrica` (`rubrica_id`),
  CONSTRAINT `fk_ava_atv_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ava_atv_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ava_atv_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `ava_modulos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ava_atv_rubrica` FOREIGN KEY (`rubrica_id`) REFERENCES `ava_rubricas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_aula_anexos
--
CREATE TABLE IF NOT EXISTS `ava_aula_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aula_id` int NOT NULL,
  `tipo` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'arquivo',
  `arquivo_key` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_anexos_aula` (`aula_id`),
  CONSTRAINT `fk_ava_anexos_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_aulas
--
CREATE TABLE IF NOT EXISTS `ava_aulas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `professor_id` int DEFAULT NULL,
  `tipo` enum('video','texto','pdf','apresentacao','audio','link','html','quiz') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'video',
  `conteudo_html` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `video_provider` enum('none','mp4','youtube','vimeo','bunny','cloudflare','panda') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `video_ref` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duracao_seg` int NOT NULL DEFAULT '0',
  `imagem_key` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempo_estimado_min` int NOT NULL DEFAULT '0',
  `data_liberacao` datetime DEFAULT NULL,
  `data_encerramento` datetime DEFAULT NULL,
  `obrigatoria` tinyint(1) NOT NULL DEFAULT '1',
  `permite_download` tinyint(1) NOT NULL DEFAULT '0',
  `permite_comentarios` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_aulas_modulo` (`modulo_id`),
  KEY `idx_ava_aulas_professor` (`professor_id`),
  CONSTRAINT `fk_ava_aulas_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `ava_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_aulas_ao_vivo
--
CREATE TABLE IF NOT EXISTS `ava_aulas_ao_vivo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `disciplina_id` int NOT NULL,
  `modulo_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `plataforma` enum('jitsi','panda','externo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'jitsi',
  `link_externo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inicio_em` datetime DEFAULT NULL,
  `fim_em` datetime DEFAULT NULL,
  `status` enum('agendada','ao_vivo','encerrada','cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'agendada',
  `panda_live_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_live_player` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_player` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_hls` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_synced_at` datetime DEFAULT NULL,
  `gravacao_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_live_disc` (`disciplina_id`),
  KEY `idx_ava_live_modulo` (`modulo_id`),
  KEY `idx_ava_live_professor` (`professor_id`),
  KEY `idx_ava_live_inicio` (`inicio_em`),
  CONSTRAINT `fk_ava_live_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ava_live_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `ava_modulos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_categorias
--
CREATE TABLE IF NOT EXISTS `ava_categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_categorias_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_certificados
--
CREATE TABLE IF NOT EXISTS `ava_certificados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `disciplina_id` int DEFAULT NULL,
  `curso_id` int DEFAULT NULL,
  `tipo` enum('disciplina','curso') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disciplina',
  `codigo` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aluno_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carga_horaria` int NOT NULL DEFAULT '0',
  `nota_final` decimal(5,2) DEFAULT NULL,
  `emitido_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_cert_codigo` (`codigo`),
  UNIQUE KEY `uk_ava_cert_aluno_disc` (`aluno_id`,`disciplina_id`),
  KEY `idx_ava_cert_aluno` (`aluno_id`),
  KEY `idx_ava_cert_disc` (`disciplina_id`),
  CONSTRAINT `fk_ava_cert_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_comentarios
--
CREATE TABLE IF NOT EXISTS `ava_comentarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aula_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `autor_tipo` enum('aluno','professor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aluno',
  `autor_id` int NOT NULL,
  `autor_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fixado` tinyint(1) NOT NULL DEFAULT '0',
  `removido` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_coment_aula` (`aula_id`),
  KEY `idx_ava_coment_parent` (`parent_id`),
  CONSTRAINT `fk_ava_coment_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ava_coment_parent` FOREIGN KEY (`parent_id`) REFERENCES `ava_comentarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_cursos
--
CREATE TABLE IF NOT EXISTS `ava_cursos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modalidade` enum('fundamental','medio','tecnico','graduacao','pos','livre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'livre',
  `categoria_id` int DEFAULT NULL,
  `carga_horaria` int NOT NULL DEFAULT '0',
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `objetivos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `competencias` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bibliografia` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `certificacao` tinyint(1) NOT NULL DEFAULT '0',
  `imagem_key` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banner_key` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('rascunho','ativo','arquivado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_cursos_categoria` (`categoria_id`),
  KEY `idx_ava_cursos_status` (`status`),
  CONSTRAINT `fk_ava_cursos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `ava_categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_disciplina_avaliacoes
--
CREATE TABLE IF NOT EXISTS `ava_disciplina_avaliacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `disciplina_id` int NOT NULL,
  `prova_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requisito_progresso_pct` decimal(5,2) NOT NULL DEFAULT '80.00',
  `obrigatoria` tinyint(1) NOT NULL DEFAULT '1',
  `peso` decimal(5,2) NOT NULL DEFAULT '1.00',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_disc_aval` (`disciplina_id`,`prova_id`),
  KEY `idx_ava_disc_aval_disc` (`disciplina_id`),
  KEY `idx_ava_disc_aval_prova` (`prova_id`),
  CONSTRAINT `fk_ava_disc_aval_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_disciplinas
--
CREATE TABLE IF NOT EXISTS `ava_disciplinas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `curso_id` int NOT NULL,
  `semestre_id` int DEFAULT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `tutor_id` int DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `carga_horaria` int NOT NULL DEFAULT '0',
  `horas_ead` int NOT NULL DEFAULT '0',
  `horas_presenciais` int NOT NULL DEFAULT '0',
  `ementa` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `objetivos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `competencias` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `materia_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `status` enum('rascunho','ativo','arquivado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_disc_curso` (`curso_id`),
  KEY `idx_ava_disc_semestre` (`semestre_id`),
  KEY `idx_ava_disc_professor` (`professor_id`),
  KEY `idx_ava_disc_tutor` (`tutor_id`),
  KEY `idx_ava_disc_materia` (`materia_id`),
  KEY `idx_ava_disc_turma` (`turma_id`),
  CONSTRAINT `fk_ava_disc_curso` FOREIGN KEY (`curso_id`) REFERENCES `ava_cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ava_disc_semestre` FOREIGN KEY (`semestre_id`) REFERENCES `ava_semestres` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_matriculas_disciplina
--
CREATE TABLE IF NOT EXISTS `ava_matriculas_disciplina` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `disciplina_id` int NOT NULL,
  `origem` enum('erp','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `status` enum('ativa','concluida','trancada','cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativa',
  `progresso_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `nota_final` decimal(5,2) DEFAULT NULL,
  `data_matricula` datetime DEFAULT CURRENT_TIMESTAMP,
  `concluida_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_matricula` (`aluno_id`,`disciplina_id`),
  KEY `idx_ava_matricula_disc` (`disciplina_id`),
  KEY `idx_ava_matricula_aluno` (`aluno_id`),
  CONSTRAINT `fk_ava_matricula_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_modulos
--
CREATE TABLE IF NOT EXISTS `ava_modulos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `disciplina_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ordem` int NOT NULL DEFAULT '0',
  `status` enum('rascunho','publicado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publicado',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_modulos_disc` (`disciplina_id`),
  CONSTRAINT `fk_ava_modulos_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_progresso_aula
--
CREATE TABLE IF NOT EXISTS `ava_progresso_aula` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `aula_id` int NOT NULL,
  `status` enum('nao_iniciada','em_andamento','concluida') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_iniciada',
  `percentual` decimal(5,2) NOT NULL DEFAULT '0.00',
  `concluida_em` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_prog_aula` (`aluno_id`,`aula_id`),
  KEY `idx_ava_prog_aula_aula` (`aula_id`),
  CONSTRAINT `fk_ava_prog_aula_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_progresso_video
--
CREATE TABLE IF NOT EXISTS `ava_progresso_video` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `aula_id` int NOT NULL,
  `segundo_atual` int NOT NULL DEFAULT '0',
  `tempo_assistido_seg` int NOT NULL DEFAULT '0',
  `duracao_seg` int NOT NULL DEFAULT '0',
  `percentual` decimal(5,2) NOT NULL DEFAULT '0.00',
  `concluido` tinyint(1) NOT NULL DEFAULT '0',
  `ultimo_acesso` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_prog_video` (`aluno_id`,`aula_id`),
  KEY `idx_ava_prog_video_aula` (`aula_id`),
  CONSTRAINT `fk_ava_prog_video_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_rubrica_criterios
--
CREATE TABLE IF NOT EXISTS `ava_rubrica_criterios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rubrica_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `peso` decimal(5,2) NOT NULL DEFAULT '1.00',
  `pontuacao_max` decimal(5,2) NOT NULL DEFAULT '10.00',
  `ordem` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_ava_rub_crit_rubrica` (`rubrica_id`),
  CONSTRAINT `fk_ava_rub_crit_rubrica` FOREIGN KEY (`rubrica_id`) REFERENCES `ava_rubricas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_rubricas
--
CREATE TABLE IF NOT EXISTS `ava_rubricas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `disciplina_id` int DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_rubricas_disc` (`disciplina_id`),
  CONSTRAINT `fk_ava_rubricas_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ava_semestres
--
CREATE TABLE IF NOT EXISTS `ava_semestres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `curso_id` int NOT NULL,
  `nome` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_semestres_curso` (`curso_id`),
  CONSTRAINT `fk_ava_semestres_curso` FOREIGN KEY (`curso_id`) REFERENCES `ava_cursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- avatares_alunos
--
CREATE TABLE IF NOT EXISTS `avatares_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `nome_social` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `avatar_seed` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `avatar_updated_at` datetime DEFAULT NULL,
  `descricao_objetivos` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `tipo_rosto` enum('redondo','oval','quadrado','triangular') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'oval',
  `cor_pele` enum('clara','media','escura') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'media',
  `tipo_cabelo` enum('curto','medio','longo','careca') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'curto',
  `cor_cabelo` enum('preto','castanho','loiro','ruivo','grisalho') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'preto',
  `estilo_cabelo` enum('liso','ondulado','cacheado','afro') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'liso',
  `cor_olhos` enum('castanho','azul','verde','preto') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'castanho',
  `tipo_sobrancelha` enum('fina','media','grossa') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'media',
  `tipo_nariz` enum('pequeno','medio','grande') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'medio',
  `tipo_boca` enum('pequena','media','grande') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'media',
  `cor_labios` enum('natural','vermelho','rosa') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'natural',
  `oculos` tinyint(1) DEFAULT '0',
  `tipo_oculos` enum('comum','escuro','leitura') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'comum',
  `barba` tinyint(1) DEFAULT '0',
  `tipo_barba` enum('bigode','cavanhaque','barba_completa') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'bigode',
  `cor_camisa` enum('azul','vermelho','verde','amarelo','preto','branco','cinza') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'azul',
  `estilo_camisa` enum('social','casual','esportiva') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'casual',
  `cor_fundo` enum('azul','verde','roxo','laranja','rosa','cinza') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'azul',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno_avatar` (`aluno_id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_criado_em` (`criado_em`),
  KEY `idx_avatar_url` (`avatar_url`),
  KEY `idx_avatar_seed` (`avatar_seed`),
  CONSTRAINT `avatares_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- billing_message_log
--
CREATE TABLE IF NOT EXISTS `billing_message_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `installment_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `responsavel_id` int DEFAULT NULL,
  `canal` enum('app','email','whatsapp') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_usado` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destinatario` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('enviado','falha','simulado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'simulado',
  `erro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_installment` (`installment_id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- billing_rule_config
--
CREATE TABLE IF NOT EXISTS `billing_rule_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dias_relativo` int NOT NULL,
  `canal` enum('app','email','whatsapp') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_titulo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_corpo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- bncc_habilidades
--
CREATE TABLE IF NOT EXISTS `bncc_habilidades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `etapa` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Educação Infantil, Ensino Fundamental, Ensino Médio',
  `componente` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Componente curricular / área',
  `ano_serie` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade_tematica` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objeto_conhecimento` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bncc_codigo` (`codigo`),
  KEY `idx_bncc_componente` (`componente`),
  KEY `idx_bncc_ano` (`ano_serie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- boletim_componentes
--
CREATE TABLE IF NOT EXISTS `boletim_componentes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `regra_id` int NOT NULL,
  `codigo` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` enum('provas_sistema','manual','jornadas','calculado','evento_boletim','faltas_evento','nenhuma') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'provas_sistema',
  `calc_type` enum('media','soma','maior','ultima') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `peso` decimal(8,3) NOT NULL DEFAULT '1.000',
  `filtro_titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloco_id` int DEFAULT NULL,
  `blocos_ids` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `materia_id` int DEFAULT NULL,
  `materias_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `materia_unica` tinyint(1) NOT NULL DEFAULT '0',
  `usar_percentual` tinyint(1) NOT NULL DEFAULT '1',
  `escala_max` decimal(8,2) NOT NULL DEFAULT '10.00',
  `obrigatorio` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_componentes_regra_codigo` (`regra_id`,`codigo`),
  KEY `idx_boletim_componentes_regra` (`regra_id`),
  KEY `idx_boletim_componentes_bloco` (`bloco_id`),
  CONSTRAINT `fk_boletim_componentes_regra` FOREIGN KEY (`regra_id`) REFERENCES `boletim_regras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- boletim_log_geracoes
--
CREATE TABLE IF NOT EXISTS `boletim_log_geracoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `regra_id` int NOT NULL,
  `periodo_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `usuario_nome` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alunos_processados` int NOT NULL DEFAULT '0',
  `linhas_geradas` int NOT NULL DEFAULT '0',
  `erros` int NOT NULL DEFAULT '0',
  `alunos_mudanca_significativa` int NOT NULL DEFAULT '0',
  `detalhes_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boletim_log_geracoes_regra` (`regra_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- boletim_geracoes
--
CREATE TABLE IF NOT EXISTS `boletim_geracoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `regra_id` int NOT NULL,
  `periodo_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `versao` int NOT NULL DEFAULT '1',
  `vigente` tinyint(1) NOT NULL DEFAULT '1',
  `modo` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gerar',
  `usuario_id` int DEFAULT NULL,
  `usuario_nome` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alunos_processados` int NOT NULL DEFAULT '0',
  `alunos_preservados` int NOT NULL DEFAULT '0',
  `linhas_geradas` int NOT NULL DEFAULT '0',
  `erros` int NOT NULL DEFAULT '0',
  `alunos_mudanca_significativa` int NOT NULL DEFAULT '0',
  `detalhes_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_geracoes_regra_periodo_versao` (`regra_id`,`periodo_ref`,`versao`),
  KEY `idx_boletim_geracoes_regra_vigente` (`regra_id`,`periodo_ref`,`vigente`),
  CONSTRAINT `fk_boletim_geracoes_regra` FOREIGN KEY (`regra_id`) REFERENCES `boletim_regras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- boletim_alunos_travados
--
CREATE TABLE IF NOT EXISTS `boletim_alunos_travados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `regra_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `periodo_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `usuario_nome` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_alunos_travados` (`regra_id`,`aluno_id`,`periodo_ref`),
  KEY `idx_boletim_alunos_travados_regra` (`regra_id`,`periodo_ref`),
  CONSTRAINT `fk_boletim_alunos_travados_regra` FOREIGN KEY (`regra_id`) REFERENCES `boletim_regras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_boletim_alunos_travados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- boletim_notas_manuais
--
CREATE TABLE IF NOT EXISTS `boletim_notas_manuais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `regra_id` int NOT NULL,
  `componente_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `materia_id` int NOT NULL DEFAULT '0',
  `periodo_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota` decimal(8,2) DEFAULT NULL,
  `bloqueado` tinyint(1) NOT NULL DEFAULT '0',
  `observacao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_notas_manuais_item_materia` (`componente_id`,`aluno_id`,`periodo_ref`,`materia_id`),
  KEY `idx_boletim_notas_manuais_aluno` (`aluno_id`),
  KEY `idx_boletim_notas_manuais_regra` (`regra_id`),
  KEY `idx_boletim_notas_manuais_componente` (`componente_id`),
  CONSTRAINT `fk_boletim_notas_manuais_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_boletim_notas_manuais_componente` FOREIGN KEY (`componente_id`) REFERENCES `boletim_componentes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_boletim_notas_manuais_regra` FOREIGN KEY (`regra_id`) REFERENCES `boletim_regras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- boletim_observacoes
--
CREATE TABLE IF NOT EXISTS `boletim_observacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_observacoes_aluno` (`aluno_id`),
  CONSTRAINT `fk_boletim_observacoes_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- boletim_regras
--
CREATE TABLE IF NOT EXISTS `boletim_regras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao_curta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formula_final` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `formula_materias_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `extras_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `materias_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `series_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `turmas_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `exibir_em` enum('notas','boletim') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'boletim',
  `ano_letivo` smallint unsigned DEFAULT NULL,
  `bimestre` tinyint unsigned DEFAULT NULL,
  `nota_minima_aprovacao` decimal(8,2) DEFAULT NULL,
  `usar_resultado_aprovacao` tinyint(1) NOT NULL DEFAULT '1',
  `vis_aluno` tinyint(1) NOT NULL DEFAULT '1',
  `vis_pais` tinyint(1) NOT NULL DEFAULT '1',
  `vis_coordenacao` tinyint(1) NOT NULL DEFAULT '1',
  `round_mode` enum('none','half') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `default_data_inicio` date DEFAULT NULL,
  `default_data_fim` date DEFAULT NULL,
  `decimal_places` tinyint(1) NOT NULL DEFAULT '2',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boletim_regras_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- boletim_resultados_gerados
--
CREATE TABLE IF NOT EXISTS `boletim_resultados_gerados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `regra_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `periodo_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `materia_id` int DEFAULT NULL,
  `materia_nome` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `materia_ref` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem_linha` int NOT NULL DEFAULT '0',
  `colunas_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notas_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `media_final` decimal(8,2) DEFAULT NULL,
  `preview` tinyint(1) NOT NULL DEFAULT '0',
  `geracao_id` int DEFAULT NULL,
  `versao` int NOT NULL DEFAULT '1',
  `vigente` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boletim_resultados_aluno` (`aluno_id`),
  KEY `idx_boletim_resultados_regra` (`regra_id`),
  KEY `idx_boletim_resultados_lookup` (`regra_id`,`aluno_id`,`periodo_ref`),
  KEY `idx_boletim_resultados_preview` (`regra_id`,`aluno_id`,`periodo_ref`,`preview`),
  KEY `idx_boletim_resultados_vigente` (`regra_id`,`aluno_id`,`periodo_ref`,`vigente`,`preview`),
  KEY `idx_boletim_resultados_geracao` (`geracao_id`),
  CONSTRAINT `fk_boletim_resultados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_boletim_resultados_regra` FOREIGN KEY (`regra_id`) REFERENCES `boletim_regras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_boletim_resultados_geracao` FOREIGN KEY (`geracao_id`) REFERENCES `boletim_geracoes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- cadernos_aluno
--
CREATE TABLE IF NOT EXISTS `cadernos_aluno` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `materia_id` int DEFAULT NULL COMMENT 'Matéria relacionada (opcional)',
  `pasta_id` int DEFAULT NULL COMMENT 'Pasta de estudo (opcional)',
  `observacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Texto/anotação livre',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_caderno_aluno_id` (`aluno_id`),
  KEY `idx_caderno_materia` (`materia_id`),
  KEY `idx_caderno_pasta` (`pasta_id`),
  CONSTRAINT `cadernos_aluno_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cadernos_aluno_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_caderno_pasta` FOREIGN KEY (`pasta_id`) REFERENCES `cadernos_aluno_pastas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- cadernos_aluno_anexos
--
CREATE TABLE IF NOT EXISTS `cadernos_aluno_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `caderno_id` int NOT NULL,
  `tipo` enum('imagem','documento') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'documento',
  `nome_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int unsigned DEFAULT NULL COMMENT 'Tamanho em bytes',
  `anotacao_canvas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON do canvas Fabric.js (desenhos, setas, texto)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_anexo_caderno` (`caderno_id`),
  CONSTRAINT `cadernos_aluno_anexos_ibfk_1` FOREIGN KEY (`caderno_id`) REFERENCES `cadernos_aluno` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- cadernos_aluno_pastas
--
CREATE TABLE IF NOT EXISTS `cadernos_aluno_pastas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pasta_aluno` (`aluno_id`),
  CONSTRAINT `cadernos_aluno_pastas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- calendario_letivo
--
CREATE TABLE IF NOT EXISTS `calendario_letivo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ano` int NOT NULL,
  `dias_meta` int NOT NULL DEFAULT '200',
  `carga_horaria_meta` int NOT NULL DEFAULT '800',
  `observacao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_calendario_ano` (`ano`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- calendario_letivo_eventos
--
CREATE TABLE IF NOT EXISTS `calendario_letivo_eventos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `calendario_id` int NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `tipo` enum('feriado','recesso','reposicao','evento','suspensao','avaliacao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'feriado',
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link_reuniao` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `local_evento` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visivel_aluno` tinyint(1) NOT NULL DEFAULT '0',
  `visivel_professor` tinyint(1) NOT NULL DEFAULT '0',
  `visivel_pais` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cal_eventos_calendario` (`calendario_id`),
  KEY `idx_cal_eventos_data` (`data_inicio`,`data_fim`),
  CONSTRAINT `fk_cal_eventos_calendario` FOREIGN KEY (`calendario_id`) REFERENCES `calendario_letivo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- carteira_movimentacoes
--
CREATE TABLE IF NOT EXISTS `carteira_movimentacoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_type` enum('aluno','professor','escola') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `tipo` enum('recarga_mensal','cortesia','compra','consumo','estorno','recarga_plano','recarga_inicial') COLLATE utf8mb4_unicode_ci NOT NULL,
  `saldo_origem` enum('escola','comprado','misto') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor` decimal(14,4) NOT NULL COMMENT 'Positivo=entrada, negativo=consumo',
  `modulo_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacao` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Detalhe amigável do consumo (ex.: descrição enviada pelo app)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mov_user_created` (`user_type`,`user_id`,`created_at`),
  KEY `idx_mov_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de movimentações da carteira';

--
-- carteira_usuarios
--
CREATE TABLE IF NOT EXISTS `carteira_usuarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_type` enum('aluno','professor','escola') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `saldo` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `saldo_escola` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `saldo_comprado` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_carteira_user` (`user_type`,`user_id`),
  KEY `idx_carteira_user_type_id` (`user_type`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Carteira de créditos por usuário (aluno/professor)';

--
-- censo_auditoria
--
CREATE TABLE IF NOT EXISTS `censo_auditoria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `acao` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidade_tipo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entidade_id` int DEFAULT NULL,
  `dados_anteriores_json` json DEFAULT NULL,
  `dados_novos_json` json DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_censo_auditoria_edicao` (`edicao_id`,`criado_em`),
  CONSTRAINT `fk_censo_auditoria_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_complementos_aluno
--
CREATE TABLE IF NOT EXISTS `censo_complementos_aluno` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `status_validacao` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `conferido` tinyint(1) NOT NULL DEFAULT '0',
  `incluir_exportacao` tinyint(1) NOT NULL DEFAULT '1',
  `dados_json` json DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_comp_aluno_edicao` (`edicao_id`,`aluno_id`),
  KEY `idx_censo_aluno_status` (`edicao_id`,`status_validacao`),
  CONSTRAINT `fk_censo_comp_aluno_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_complementos_escola
--
CREATE TABLE IF NOT EXISTS `censo_complementos_escola` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `unidade_id` int NOT NULL DEFAULT '0',
  `status_validacao` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `conferido` tinyint(1) NOT NULL DEFAULT '0',
  `incluir_exportacao` tinyint(1) NOT NULL DEFAULT '1',
  `dados_json` json DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_comp_escola_edicao` (`edicao_id`,`unidade_id`),
  CONSTRAINT `fk_censo_comp_escola_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_complementos_gestor
--
CREATE TABLE IF NOT EXISTS `censo_complementos_gestor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `professor_id` int DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `cargo_codigo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_inep` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpf` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_validacao` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `conferido` tinyint(1) NOT NULL DEFAULT '0',
  `incluir_exportacao` tinyint(1) NOT NULL DEFAULT '1',
  `dados_json` json DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_censo_gestor_edicao` (`edicao_id`),
  KEY `idx_censo_gestor_professor` (`professor_id`),
  CONSTRAINT `fk_censo_gestor_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_complementos_profissional
--
CREATE TABLE IF NOT EXISTS `censo_complementos_profissional` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `codigo_inep` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpf` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_validacao` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `conferido` tinyint(1) NOT NULL DEFAULT '0',
  `incluir_exportacao` tinyint(1) NOT NULL DEFAULT '1',
  `dados_json` json DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_comp_prof_edicao` (`edicao_id`,`professor_id`),
  CONSTRAINT `fk_censo_comp_prof_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_complementos_turma
--
CREATE TABLE IF NOT EXISTS `censo_complementos_turma` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `codigo_inep` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etapa_codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modalidade_codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_validacao` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `conferido` tinyint(1) NOT NULL DEFAULT '0',
  `incluir_exportacao` tinyint(1) NOT NULL DEFAULT '1',
  `dados_json` json DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_comp_turma_edicao` (`edicao_id`,`turma_id`),
  KEY `idx_censo_turma_etapa` (`etapa_codigo`),
  CONSTRAINT `fk_censo_comp_turma_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_edicoes
--
CREATE TABLE IF NOT EXISTS `censo_edicoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unidade_id` int NOT NULL DEFAULT '0',
  `ano` smallint unsigned NOT NULL,
  `etapa_coleta` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'matricula_inicial',
  `data_referencia` date DEFAULT NULL,
  `versao_layout` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `layout_id` int DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `responsavel_id` int DEFAULT NULL,
  `ultima_validacao_em` datetime DEFAULT NULL,
  `ultima_validacao_por` int DEFAULT NULL,
  `fechado_em` datetime DEFAULT NULL,
  `fechado_por` int DEFAULT NULL,
  `reaberto_em` datetime DEFAULT NULL,
  `reaberto_por` int DEFAULT NULL,
  `motivo_reabertura` text COLLATE utf8mb4_unicode_ci,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_edicao_unidade_ano_etapa` (`unidade_id`,`ano`,`etapa_coleta`),
  KEY `idx_censo_edicoes_status` (`status`),
  KEY `idx_censo_edicoes_ano` (`ano`,`etapa_coleta`),
  KEY `idx_censo_edicoes_layout` (`layout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_exportacoes
--
CREATE TABLE IF NOT EXISTS `censo_exportacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `snapshot_id` int DEFAULT NULL,
  `layout_id` int DEFAULT NULL,
  `versao` int unsigned NOT NULL,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'migracao',
  `arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanho_bytes` int unsigned NOT NULL DEFAULT '0',
  `total_linhas` int unsigned NOT NULL DEFAULT '0',
  `resumo_json` json DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gerado',
  `gerado_por` int DEFAULT NULL,
  `gerado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_censo_exportacoes_edicao` (`edicao_id`,`versao`),
  CONSTRAINT `fk_censo_exportacao_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_layouts
--
CREATE TABLE IF NOT EXISTS `censo_layouts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ano` smallint unsigned NOT NULL,
  `versao` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `etapa_coleta` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'matricula_inicial',
  `vigencia_inicio` date DEFAULT NULL,
  `vigencia_fim` date DEFAULT NULL,
  `configuracao_json` longtext COLLATE utf8mb4_unicode_ci,
  `hash_configuracao` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fonte_oficial` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oficial` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_layouts_ano_versao_etapa` (`ano`,`versao`,`etapa_coleta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_matriculas
--
CREATE TABLE IF NOT EXISTS `censo_matriculas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `censo_turma_id` int DEFAULT NULL,
  `data_ingresso` date DEFAULT NULL,
  `situacao_referencia` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_validacao` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `incluir_exportacao` tinyint(1) NOT NULL DEFAULT '1',
  `motivo_exclusao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identificador_retorno` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dados_json` json DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_matricula_edicao_aluno_turma` (`edicao_id`,`aluno_id`,`turma_id`),
  KEY `idx_censo_matricula_turma` (`turma_id`),
  CONSTRAINT `fk_censo_matricula_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_retornos
--
CREATE TABLE IF NOT EXISTS `censo_retornos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `exportacao_id` int DEFAULT NULL,
  `arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inconsistencia',
  `hash_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resumo_json` json DEFAULT NULL,
  `aplicado` tinyint(1) NOT NULL DEFAULT '0',
  `importado_por` int DEFAULT NULL,
  `importado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_censo_retornos_edicao` (`edicao_id`),
  KEY `idx_censo_retornos_exportacao` (`exportacao_id`),
  CONSTRAINT `fk_censo_retorno_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_situacoes_aluno
--
CREATE TABLE IF NOT EXISTS `censo_situacoes_aluno` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `censo_matricula_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `situacao_codigo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resultado_academico` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `confirmado_por` int DEFAULT NULL,
  `confirmado_em` datetime DEFAULT NULL,
  `justificativa` text COLLATE utf8mb4_unicode_ci,
  `status_validacao` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_situacao_matricula` (`censo_matricula_id`),
  KEY `idx_censo_situacao_edicao` (`edicao_id`),
  CONSTRAINT `fk_censo_situacao_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_censo_situacao_matricula` FOREIGN KEY (`censo_matricula_id`) REFERENCES `censo_matriculas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_snapshots
--
CREATE TABLE IF NOT EXISTS `censo_snapshots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `versao` int unsigned NOT NULL,
  `dados_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `criado_por` int DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_snapshot_edicao_versao` (`edicao_id`,`versao`),
  CONSTRAINT `fk_censo_snapshot_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_tabelas_auxiliares
--
CREATE TABLE IF NOT EXISTS `censo_tabelas_auxiliares` (
  `id` int NOT NULL AUTO_INCREMENT,
  `layout_id` int NOT NULL,
  `tabela` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadados_json` json DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_aux_layout_tabela_codigo` (`layout_id`,`tabela`,`codigo`),
  KEY `idx_censo_aux_tabela` (`tabela`,`codigo`),
  CONSTRAINT `fk_censo_aux_layout` FOREIGN KEY (`layout_id`) REFERENCES `censo_layouts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_validacoes
--
CREATE TABLE IF NOT EXISTS `censo_validacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `entidade_tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidade_id` int NOT NULL DEFAULT '0',
  `regra_codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severidade` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensagem` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orientacao` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `campo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberta',
  `justificativa` text COLLATE utf8mb4_unicode_ci,
  `resolvido_por` int DEFAULT NULL,
  `resolvido_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_censo_validacoes_edicao` (`edicao_id`,`severidade`,`status`),
  KEY `idx_censo_validacoes_entidade` (`entidade_tipo`,`entidade_id`),
  CONSTRAINT `fk_censo_validacoes_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- censo_vinculos_profissionais
--
CREATE TABLE IF NOT EXISTS `censo_vinculos_profissionais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edicao_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `censo_turma_id` int DEFAULT NULL,
  `materia_id` int DEFAULT NULL,
  `componente_codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funcao_codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_validacao` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `incluir_exportacao` tinyint(1) NOT NULL DEFAULT '1',
  `dados_json` json DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_vinculo_edicao` (`edicao_id`,`professor_id`,`turma_id`,`materia_id`),
  CONSTRAINT `fk_censo_vinculo_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- chat_professores_alunos
--
CREATE TABLE IF NOT EXISTS `chat_professores_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `ultima_mensagem_id` int DEFAULT NULL,
  `ultima_atividade` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `aluno_professor` (`aluno_id`,`professor_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `professor_id` (`professor_id`),
  KEY `ultima_atividade` (`ultima_atividade`),
  CONSTRAINT `chat_professores_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_professores_alunos_ibfk_2` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- chat_professores_alunos_anexos
--
CREATE TABLE IF NOT EXISTS `chat_professores_alunos_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mensagem_id` int NOT NULL,
  `nome_arquivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho_arquivo` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mensagem_id` (`mensagem_id`),
  CONSTRAINT `chat_professores_alunos_anexos_ibfk_1` FOREIGN KEY (`mensagem_id`) REFERENCES `chat_professores_alunos_mensagens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- chat_professores_alunos_mensagens
--
CREATE TABLE IF NOT EXISTS `chat_professores_alunos_mensagens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `chat_id` int NOT NULL,
  `remetente_tipo` enum('aluno','professor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remetente_id` int NOT NULL,
  `mensagem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lida` tinyint(1) DEFAULT '0',
  `lida_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `chat_id` (`chat_id`),
  KEY `remetente_id` (`remetente_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `chat_professores_alunos_mensagens_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `chat_professores_alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- compras_creditos
--
CREATE TABLE IF NOT EXISTS `compras_creditos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_type` enum('aluno','professor','escola') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `pacote_id` int unsigned NOT NULL,
  `valor_centavos` int unsigned NOT NULL,
  `status` enum('pending','paid','failed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `gateway_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID do pedido no gateway de pagamento',
  `asaas_payment_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout_url` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_notified_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `billing_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_compras_asaas_payment` (`asaas_payment_id`),
  KEY `idx_compras_user` (`user_type`,`user_id`),
  KEY `idx_compras_status` (`status`),
  KEY `idx_compras_gateway` (`gateway_id`),
  KEY `fk_compras_pacote` (`pacote_id`),
  CONSTRAINT `fk_compras_pacote` FOREIGN KEY (`pacote_id`) REFERENCES `pacotes_creditos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de compras de créditos (pendente até confirmação do gateway)';

--
-- config_dev
--
CREATE TABLE IF NOT EXISTS `config_dev` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dev_settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- config_escolas_database
--
CREATE TABLE IF NOT EXISTS `config_escolas_database` (
  `id` int NOT NULL AUTO_INCREMENT,
  `escola_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_port` int DEFAULT '3306',
  `db_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_pass` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ssh_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ssh_port` int DEFAULT '22',
  `ssh_user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ssh_pass` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ssh_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_escola_nome` (`escola_nome`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- config_layout
--
CREATE TABLE IF NOT EXISTS `config_layout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `config_value` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `config_type` enum('color','image','text','number') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'text',
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`),
  KEY `idx_config_layout_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- config_simulados
--
CREATE TABLE IF NOT EXISTS `config_simulados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `tempo_limite_padrao` int DEFAULT '1800',
  `quantidade_questoes_padrao` int DEFAULT '10',
  `disciplinas_permitidas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `anos_permitidos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `ativo` tinyint(1) DEFAULT '1',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- conselho_atas
--
CREATE TABLE IF NOT EXISTS `conselho_atas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `pauta` text COLLATE utf8mb4_unicode_ci,
  `sintese` text COLLATE utf8mb4_unicode_ci,
  `decisoes` text COLLATE utf8mb4_unicode_ci,
  `conteudo_json` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Snapshot da matriz e deliberações no momento da geração',
  `gerada_por` int DEFAULT NULL,
  `gerada_em` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conselho_atas_sessao` (`sessao_id`),
  CONSTRAINT `fk_conselho_atas_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `conselho_sessoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- conselho_deliberacoes
--
CREATE TABLE IF NOT EXISTS `conselho_deliberacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `materia_id` int DEFAULT NULL COMMENT 'NULL = decisão da situação geral do aluno',
  `resultado_anterior` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resultado_decisao` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `justificativa` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `registrado_por` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conselho_deliberacoes_sessao_aluno` (`sessao_id`,`aluno_id`),
  KEY `idx_conselho_deliberacoes_aluno` (`aluno_id`),
  CONSTRAINT `fk_conselho_deliberacoes_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conselho_deliberacoes_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `conselho_sessoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- conselho_encaminhamentos
--
CREATE TABLE IF NOT EXISTS `conselho_encaminhamentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `tipo` enum('recuperacao','acompanhamento_pedagogico','contato_responsavel','atendimento','encaminhamento','observacao','decisao_final') COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalhe` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ocorrencia_id` int DEFAULT NULL,
  `criado_por` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conselho_encaminhamentos_sessao_aluno` (`sessao_id`,`aluno_id`),
  KEY `idx_conselho_encaminhamentos_ocorrencia` (`ocorrencia_id`),
  KEY `fk_conselho_encaminhamentos_aluno` (`aluno_id`),
  CONSTRAINT `fk_conselho_encaminhamentos_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conselho_encaminhamentos_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `conselho_sessoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- conselho_observacoes
--
CREATE TABLE IF NOT EXISTS `conselho_observacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conselho_observacoes` (`sessao_id`,`aluno_id`,`professor_id`),
  KEY `fk_conselho_observacoes_aluno` (`aluno_id`),
  CONSTRAINT `fk_conselho_observacoes_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conselho_observacoes_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `conselho_sessoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- conselho_participantes
--
CREATE TABLE IF NOT EXISTS `conselho_participantes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `professor_id` int DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cargo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'professor',
  `presente` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conselho_participantes_sessao` (`sessao_id`),
  KEY `idx_conselho_participantes_professor` (`professor_id`),
  CONSTRAINT `fk_conselho_participantes_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `conselho_sessoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- conselho_sessoes
--
CREATE TABLE IF NOT EXISTS `conselho_sessoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `turma_id` int NOT NULL,
  `ano_letivo` smallint unsigned NOT NULL,
  `bimestre` tinyint unsigned NOT NULL COMMENT 'Bimestre 1 a 4',
  `status` enum('em_preparacao','em_andamento','finalizado','reaberto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'em_preparacao',
  `data_reuniao` date DEFAULT NULL,
  `pauta` text COLLATE utf8mb4_unicode_ci,
  `criado_por` int DEFAULT NULL,
  `aberto_por` int DEFAULT NULL,
  `aberto_em` datetime DEFAULT NULL,
  `finalizado_por` int DEFAULT NULL,
  `finalizado_em` datetime DEFAULT NULL,
  `reaberto_por` int DEFAULT NULL,
  `reaberto_em` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conselho_sessao_turma_periodo` (`turma_id`,`ano_letivo`,`bimestre`),
  KEY `idx_conselho_sessoes_status` (`status`),
  KEY `idx_conselho_sessoes_ano` (`ano_letivo`,`bimestre`),
  CONSTRAINT `fk_conselho_sessoes_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- curso
--
CREATE TABLE IF NOT EXISTS `curso` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('regular','extra') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'regular' COMMENT 'regular=com série; extra=livre',
  `possui_serie` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0=curso extra sem série',
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_curso_ativo` (`ativo`),
  KEY `idx_curso_ordem` (`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- cursos
--
CREATE TABLE IF NOT EXISTS `cursos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo_curso_id` int NOT NULL,
  `nome` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cursos_tipo_nome` (`tipo_curso_id`,`nome`),
  UNIQUE KEY `uq_cursos_slug` (`slug`),
  KEY `idx_cursos_tipo` (`tipo_curso_id`),
  KEY `idx_cursos_ativo` (`ativo`),
  CONSTRAINT `fk_cursos_tipo_curso` FOREIGN KEY (`tipo_curso_id`) REFERENCES `tipos_curso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- dashboard_jornadas_resumo
--
CREATE TABLE IF NOT EXISTS `dashboard_jornadas_resumo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `segmento` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `jornadas_escopo` int NOT NULL DEFAULT '0',
  `pares_atribuidos` int NOT NULL DEFAULT '0',
  `concluidos` int NOT NULL DEFAULT '0',
  `pendentes` int NOT NULL DEFAULT '0',
  `taxa_conclusao` decimal(5,2) NOT NULL DEFAULT '0.00',
  `atualizado_em` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dashboard_jornadas_resumo_segmento` (`segmento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- declaracoes_emitidas
--
CREATE TABLE IF NOT EXISTS `declaracoes_emitidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `unidade_id` int DEFAULT NULL,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'matricula|frequencia|comparecimento|transferencia',
  `numero` int NOT NULL DEFAULT '0' COMMENT 'Sequencial por ano',
  `ano` smallint unsigned NOT NULL,
  `emitido_por` int DEFAULT NULL COMMENT 'usuarios.id do admin que emitiu',
  `emitido_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_json` text COLLATE utf8mb4_unicode_ci COMMENT 'Parâmetros usados (período, data, etc.)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_decl_aluno` (`aluno_id`),
  KEY `idx_decl_tipo` (`tipo`),
  KEY `idx_decl_ano_numero` (`ano`,`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- diario_aulas
--
CREATE TABLE IF NOT EXISTS `diario_aulas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade_horaria_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `plano_aula_id` int DEFAULT NULL,
  `evento_bloco_id` int DEFAULT NULL,
  `data_aula` date NOT NULL,
  `horario_de` time NOT NULL,
  `horario_ate` time NOT NULL,
  `execucao` enum('conforme_planejado','parcial','alterado','nao_realizada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'conforme_planejado',
  `conteudo_realizado` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tipo_aula` enum('regular','avaliacao','revisao','recuperacao','atividade','projeto','evento_escolar','reposicao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'regular',
  `status` enum('rascunho','finalizada','cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `finalizada_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_diario_grade_data` (`grade_horaria_id`,`data_aula`),
  KEY `idx_diario_prof_data` (`professor_id`,`data_aula`),
  KEY `idx_diario_turma_data` (`turma_id`,`data_aula`),
  KEY `idx_diario_status` (`status`),
  KEY `idx_diario_evento_bloco` (`evento_bloco_id`),
  CONSTRAINT `fk_diario_aulas_evento_bloco` FOREIGN KEY (`evento_bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- diario_fechamentos
--
CREATE TABLE IF NOT EXISTS `diario_fechamentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `turma_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `ano_letivo` smallint unsigned NOT NULL,
  `bimestre` tinyint unsigned NOT NULL COMMENT 'Bimestre 1 a 4',
  `status` enum('aberto','fechado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberto',
  `fechado_por` int DEFAULT NULL,
  `fechado_em` datetime DEFAULT NULL,
  `reaberto_por` int DEFAULT NULL,
  `reaberto_em` datetime DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_diario_fechamento` (`turma_id`,`materia_id`,`professor_id`,`ano_letivo`,`bimestre`),
  KEY `idx_diario_fechamento_turma` (`turma_id`),
  KEY `idx_diario_fechamento_professor` (`professor_id`),
  KEY `idx_diario_fechamento_status` (`status`),
  KEY `fk_diario_fechamento_materia` (`materia_id`),
  CONSTRAINT `fk_diario_fechamento_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_diario_fechamento_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_diario_fechamento_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- diario_frequencias
--
CREATE TABLE IF NOT EXISTS `diario_frequencias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `diario_aula_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `situacao` enum('presente','falta','falta_justificada','atraso','saida_antecipada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'presente',
  `nota` decimal(5,2) DEFAULT NULL,
  `observacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `origem` enum('manual_diario','integracao','entrada_saida','ajuste_gestao','importacao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual_diario',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_diario_aluno` (`diario_aula_id`,`aluno_id`),
  KEY `idx_diario_freq_aluno` (`aluno_id`),
  KEY `idx_diario_freq_situacao` (`situacao`),
  CONSTRAINT `fk_diario_freq_aula` FOREIGN KEY (`diario_aula_id`) REFERENCES `diario_aulas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- documentos_institucionais
--
CREATE TABLE IF NOT EXISTS `documentos_institucionais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('ppp','regimento','plano_curso','calendario','outro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `versao` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_mime` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_tamanho` int DEFAULT NULL,
  `observacao` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_inst_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- drive_compartilhamentos
--
CREATE TABLE IF NOT EXISTS `drive_compartilhamentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int unsigned NOT NULL,
  `shared_with_id` int unsigned NOT NULL,
  `shared_with_type` enum('student','teacher') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` enum('view','edit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'view',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_share` (`item_id`,`shared_with_id`,`shared_with_type`),
  KEY `idx_shared_with` (`shared_with_id`,`shared_with_type`),
  CONSTRAINT `fk_drive_share_item` FOREIGN KEY (`item_id`) REFERENCES `drive_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- drive_itens
--
CREATE TABLE IF NOT EXISTS `drive_itens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` int unsigned NOT NULL,
  `owner_type` enum('student','teacher') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('folder','file') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho relativo no storage para arquivos',
  `file_size` bigint unsigned DEFAULT NULL,
  `mime_type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`owner_id`,`owner_type`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_owner_parent` (`owner_id`,`owner_type`,`parent_id`),
  CONSTRAINT `fk_drive_parent` FOREIGN KEY (`parent_id`) REFERENCES `drive_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- educa_hits_requests
--
CREATE TABLE IF NOT EXISTS `educa_hits_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'ID do aluno (alunos.id)',
  `school_id` int DEFAULT NULL COMMENT 'ID da escola (multi-tenant)',
  `class_id` int DEFAULT NULL COMMENT 'ID da turma (turmas.id)',
  `grade` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Série do aluno',
  `subject` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Matéria',
  `topic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tema',
  `music_style` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estilo musical',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Descrição da música solicitada',
  `status` enum('pending','in_progress','completed','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` datetime DEFAULT NULL COMMENT 'Preenchido pelo Master ao arquivar',
  PRIMARY KEY (`id`),
  KEY `idx_educa_hits_requests_user` (`user_id`),
  KEY `idx_educa_hits_requests_status` (`status`),
  KEY `idx_educa_hits_requests_created` (`created_at`),
  KEY `idx_educa_hits_requests_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- educa_hits_track_classes
--
CREATE TABLE IF NOT EXISTS `educa_hits_track_classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `track_id` int NOT NULL,
  `class_id` int NOT NULL COMMENT 'turmas.id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_track_class` (`track_id`,`class_id`),
  KEY `idx_educa_hits_tc_class` (`class_id`),
  CONSTRAINT `fk_educa_hits_tc_class` FOREIGN KEY (`class_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_educa_hits_tc_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- educa_hits_track_grades
--
CREATE TABLE IF NOT EXISTS `educa_hits_track_grades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `track_id` int NOT NULL,
  `grade` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Série (ex: 1º ano, 2º ano)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_track_grade` (`track_id`,`grade`),
  KEY `idx_educa_hits_tg_grade` (`grade`),
  CONSTRAINT `fk_educa_hits_tg_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- educa_hits_track_schools
--
CREATE TABLE IF NOT EXISTS `educa_hits_track_schools` (
  `id` int NOT NULL AUTO_INCREMENT,
  `track_id` int NOT NULL,
  `school_id` int DEFAULT NULL COMMENT 'NULL = todas as escolas (single-tenant)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_track_school` (`track_id`,`school_id`),
  KEY `idx_educa_hits_ts_school` (`school_id`),
  CONSTRAINT `fk_educa_hits_ts_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- educa_hits_track_users
--
CREATE TABLE IF NOT EXISTS `educa_hits_track_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `track_id` int NOT NULL,
  `user_id` int NOT NULL COMMENT 'alunos.id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_track_user` (`track_id`,`user_id`),
  KEY `idx_educa_hits_tu_user` (`user_id`),
  CONSTRAINT `fk_educa_hits_tu_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- educa_hits_tracks
--
CREATE TABLE IF NOT EXISTS `educa_hits_tracks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int DEFAULT NULL COMMENT 'Pedido que originou (opcional)',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `audio_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Caminho do áudio',
  `cover_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Capa da música',
  `lyrics` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Letra da música',
  `duration` int DEFAULT NULL COMMENT 'Duração em segundos',
  `created_by_admin` int DEFAULT NULL,
  `status` enum('draft','published','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_educa_hits_tracks_request` (`request_id`),
  KEY `idx_educa_hits_tracks_status` (`status`),
  KEY `idx_educa_hits_tracks_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- educalabs_messages
--
CREATE TABLE IF NOT EXISTS `educalabs_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_id` (`project_id`),
  CONSTRAINT `fk_educalabs_messages_project` FOREIGN KEY (`project_id`) REFERENCES `educalabs_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- educalabs_projects
--
CREATE TABLE IF NOT EXISTS `educalabs_projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `share_id` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `html` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `css` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `js` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_public_id` (`public_id`),
  UNIQUE KEY `uniq_share_id` (`share_id`),
  KEY `idx_owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- educalabs_tokens
--
CREATE TABLE IF NOT EXISTS `educalabs_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- enem_alternativas
--
CREATE TABLE IF NOT EXISTS `enem_alternativas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question_id` int NOT NULL,
  `letter` char(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `text` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `file` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `is_correct` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `enem_alternativas_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `enem_questoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- enem_disciplinas
--
CREATE TABLE IF NOT EXISTS `enem_disciplinas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `label` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `value` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `enem_disciplinas_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `enem_provas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- enem_provas
--
CREATE TABLE IF NOT EXISTS `enem_provas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `year` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- enem_questoes
--
CREATE TABLE IF NOT EXISTS `enem_questoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `discipline` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `language` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `question_index` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `context` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `correct_alternative` char(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `alternatives_introduction` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `year` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `enem_questoes_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `enem_provas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- enem_questoes_arquivos
--
CREATE TABLE IF NOT EXISTS `enem_questoes_arquivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question_id` int NOT NULL,
  `file_url` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `enem_questoes_arquivos_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `enem_questoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- enem_questoes_vinculo
--
CREATE TABLE IF NOT EXISTS `enem_questoes_vinculo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ano` int NOT NULL,
  `indice` int NOT NULL,
  `disciplina` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `idioma` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `contexto` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `enunciado` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `alternativas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `correta` char(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `imagem` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ano_indice` (`ano`,`indice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- estatisticas_dama
--
CREATE TABLE IF NOT EXISTS `estatisticas_dama` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `total_partidas` int DEFAULT '0',
  `partidas_vencidas` int DEFAULT '0',
  `partidas_perdidas` int DEFAULT '0',
  `partidas_empate` int DEFAULT '0',
  `maior_sequencia_vitorias` int DEFAULT '0',
  `sequencia_atual_vitorias` int DEFAULT '0',
  `total_pecas_capturadas` int DEFAULT '0',
  `total_movimentos` int DEFAULT '0',
  `tempo_total_jogo` int DEFAULT '0',
  `nivel_preferido` enum('facil','medio','dificil') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'facil',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno` (`aluno_id`),
  CONSTRAINT `estatisticas_dama_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- execucao_exercicios
--
CREATE TABLE IF NOT EXISTS `execucao_exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `data_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` timestamp NULL DEFAULT NULL,
  `tempo_total` int DEFAULT '0',
  `questoes_corretas` int DEFAULT '0',
  `questoes_total` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `status` enum('em_andamento','finalizado','pausado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'em_andamento',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_execucao_aluno` (`aluno_id`),
  KEY `idx_execucao_lista` (`lista_id`),
  KEY `idx_execucao_lista_status` (`lista_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Execução de lista de exercícios por aluno (AdminController)';

--
-- exercicios
--
CREATE TABLE IF NOT EXISTS `exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lista_id` int DEFAULT NULL,
  `pergunta` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_b` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_c` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_d` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `resposta_correta` enum('A','B','C','D') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `explicacao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ordem` int DEFAULT '1',
  `jornada_id` int DEFAULT NULL,
  `enunciado` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `resposta` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('multipla_escolha','dissertativa') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `gerado_ia` tinyint(1) DEFAULT '0',
  `aprovado` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jornada` (`jornada_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_gerado_ia` (`gerado_ia`),
  KEY `idx_aprovado` (`aprovado`),
  KEY `exercicios_ibfk_2` (`lista_id`),
  CONSTRAINT `exercicios_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- exercicios_estatisticas_alunos
--
CREATE TABLE IF NOT EXISTS `exercicios_estatisticas_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `materia` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `total_exercicios` int DEFAULT '0',
  `total_acertos` int DEFAULT '0',
  `total_tempo` int DEFAULT '0',
  `percentual_medio` decimal(5,2) DEFAULT '0.00',
  `ultima_atividade` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_estatistica` (`aluno_id`,`materia`,`serie`),
  KEY `idx_estatisticas_aluno` (`aluno_id`),
  CONSTRAINT `exercicios_estatisticas_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- exercicios_estatisticas_turmas
--
CREATE TABLE IF NOT EXISTS `exercicios_estatisticas_turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `turma_id` int NOT NULL,
  `materia` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `total_alunos` int DEFAULT '0',
  `total_exercicios` int DEFAULT '0',
  `percentual_medio` decimal(5,2) DEFAULT '0.00',
  `tempo_medio` int DEFAULT '0',
  `ultima_atividade` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_estatistica_turma` (`turma_id`,`materia`,`serie`),
  KEY `idx_estatisticas_turma` (`turma_id`),
  CONSTRAINT `exercicios_estatisticas_turmas_ibfk_1` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- exercicios_execucoes
--
CREATE TABLE IF NOT EXISTS `exercicios_execucoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `data_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` timestamp NULL DEFAULT NULL,
  `tempo_total` int DEFAULT '0',
  `questoes_corretas` int DEFAULT '0',
  `questoes_total` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `status` enum('em_andamento','finalizado','pausado') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'em_andamento',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_execucao_ativa` (`aluno_id`,`lista_id`,`status`),
  KEY `idx_execucao_aluno` (`aluno_id`),
  KEY `idx_execucao_lista` (`lista_id`),
  CONSTRAINT `exercicios_execucoes_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_execucoes_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- exercicios_historico
--
CREATE TABLE IF NOT EXISTS `exercicios_historico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `sessao_id` int NOT NULL,
  `data_execucao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tempo_total` int DEFAULT '0',
  `questoes_corretas` int DEFAULT '0',
  `questoes_total` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `status` enum('finalizado','abandonado') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'finalizado',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lista_id` (`lista_id`),
  KEY `sessao_id` (`sessao_id`),
  KEY `idx_aluno_lista_data` (`aluno_id`,`lista_id`,`data_execucao`),
  KEY `idx_aluno_data` (`aluno_id`,`data_execucao`),
  CONSTRAINT `exercicios_historico_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_historico_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_historico_ibfk_3` FOREIGN KEY (`sessao_id`) REFERENCES `exercicios_sessoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- exercicios_respostas
--
CREATE TABLE IF NOT EXISTS `exercicios_respostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `exercicio_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `resposta` enum('A','B','C','D') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `tempo_resposta` int DEFAULT NULL COMMENT 'Tempo em segundos',
  `answered_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessao` (`sessao_id`),
  KEY `idx_exercicio` (`exercicio_id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_answered_at` (`answered_at`),
  CONSTRAINT `exercicios_respostas_ibfk_1` FOREIGN KEY (`sessao_id`) REFERENCES `exercicios_sessoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_respostas_ibfk_2` FOREIGN KEY (`exercicio_id`) REFERENCES `questoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_respostas_ibfk_3` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- exercicios_sessoes
--
CREATE TABLE IF NOT EXISTS `exercicios_sessoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT NULL COMMENT 'Tempo em minutos',
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_lista` (`lista_id`),
  KEY `idx_started_at` (`started_at`),
  CONSTRAINT `exercicios_sessoes_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_sessoes_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- expo_colag_alunos_autorizacao_imagem
--
CREATE TABLE IF NOT EXISTS `expo_colag_alunos_autorizacao_imagem` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aluno_id` int unsigned NOT NULL,
  `status` enum('Autorizado_total','Autorizado_interno','Nao_autorizado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nao_autorizado',
  `autorizado_por_responsavel_id` int unsigned DEFAULT NULL,
  `registrado_por` int unsigned DEFAULT NULL COMMENT 'admin/coordenação que registrou',
  `registrado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revogado_em` datetime DEFAULT NULL,
  `observacao` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expo_colag_aut_img_aluno` (`aluno_id`),
  KEY `idx_expo_colag_aut_img_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_edicoes
--
CREATE TABLE IF NOT EXISTS `expo_colag_edicoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Expo Colag',
  `edicao` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2026',
  `tema` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_evento` date DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fim` time DEFAULT NULL,
  `local` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mapa_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config` json DEFAULT NULL COMMENT 'Parâmetros pedagógicos da edição (grupo, prazos, rubrica…)',
  `programacao_publica_em` datetime DEFAULT NULL,
  `voto_publico_ativo` tinyint(1) NOT NULL DEFAULT '1',
  `votacao_inicio` datetime DEFAULT NULL,
  `votacao_fim` datetime DEFAULT NULL,
  `checkin_ativo` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('Planejamento','Publicada','Em_andamento','Encerrada','Arquivada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Planejamento',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_edicoes_status` (`status`),
  KEY `idx_expo_colag_edicoes_data` (`data_evento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_inscricoes
--
CREATE TABLE IF NOT EXISTS `expo_colag_inscricoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `papel_id` int unsigned DEFAULT NULL,
  `justificativa` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Aguardando','Aprovada','Recusada','Lista_espera','Cancelada_aluno','Removido_professor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Aguardando',
  `motivo_recusa` text COLLATE utf8mb4_unicode_ci,
  `inscrito_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decidido_em` datetime DEFAULT NULL,
  `decidido_por` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expo_colag_insc_proj_aluno` (`projeto_id`,`aluno_id`),
  KEY `idx_expo_colag_insc_projeto_status` (`projeto_id`,`status`),
  KEY `idx_expo_colag_insc_aluno_status` (`aluno_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_programacao
--
CREATE TABLE IF NOT EXISTS `expo_colag_programacao` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `edicao_id` int unsigned NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `tipo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Geral',
  `hora_inicio` datetime NOT NULL,
  `hora_fim` datetime DEFAULT NULL,
  `local` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setor_id` int unsigned DEFAULT NULL,
  `ordem` int unsigned NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_prog_edicao` (`edicao_id`,`hora_inicio`),
  KEY `idx_expo_colag_prog_setor` (`setor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_encontros
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_encontros` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `rotulo` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_hora` datetime NOT NULL,
  `link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sala_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_encontros_projeto` (`projeto_id`),
  KEY `idx_expo_colag_encontros_data` (`data_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_etapas
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_etapas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `ordem` int unsigned NOT NULL DEFAULT '1',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `data_limite` date DEFAULT NULL,
  `entregavel_esperado` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Pendente','Em_andamento','Concluida','Atrasada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendente',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_etapas_projeto` (`projeto_id`,`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_habilidades
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_habilidades` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `codigo_habilidade` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `habilidade_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expo_colag_hab` (`projeto_id`,`codigo_habilidade`),
  KEY `idx_expo_colag_hab_codigo` (`codigo_habilidade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_materiais
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_materiais` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `etapa_id` int unsigned DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'link',
  `arquivo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_externo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visibilidade` json DEFAULT NULL,
  `enviado_por` int unsigned DEFAULT NULL,
  `versao` int unsigned NOT NULL DEFAULT '1',
  `origem` enum('Wizard','Execucao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Wizard' COMMENT 'Wizard = sincronizado no formulário; Execucao = adicionado no painel',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_materiais_projeto` (`projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_materias
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_materias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `materia_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expo_colag_proj_materia` (`projeto_id`,`materia_id`),
  KEY `idx_expo_colag_proj_materias_materia` (`materia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_objetivos
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_objetivos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `ordem` int unsigned NOT NULL DEFAULT '1',
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_obj_projeto` (`projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_papeis
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_papeis` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `vagas` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_papeis_projeto` (`projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_professores
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_professores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `professor_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expo_colag_proj_prof` (`projeto_id`,`professor_id`),
  KEY `idx_expo_colag_proj_prof_prof` (`professor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_rubrica
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_rubrica` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `criterio` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `peso` decimal(5,2) NOT NULL DEFAULT '0.00',
  `descricao` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_rubrica_projeto` (`projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_tarefa_atribuicoes
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_tarefa_atribuicoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tarefa_id` int unsigned NOT NULL,
  `inscricao_id` int unsigned NOT NULL,
  `status` enum('Pendente','Em_andamento','Entregue','Concluida','Atrasada','Devolvida') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendente',
  `entrega_conteudo` text COLLATE utf8mb4_unicode_ci,
  `entrega_arquivo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entregue_em` datetime DEFAULT NULL,
  `avaliado_em` datetime DEFAULT NULL,
  `comentario_professor` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expo_colag_tarefa_insc` (`tarefa_id`,`inscricao_id`),
  KEY `idx_expo_colag_atr_insc_status` (`inscricao_id`,`status`,`tarefa_id`),
  KEY `idx_expo_colag_atr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_tarefas
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_tarefas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `etapa_id` int unsigned DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `tipo_entregavel` enum('Nenhum','Arquivo','Texto','Link') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nenhum',
  `data_limite` datetime DEFAULT NULL,
  `obrigatoria` tinyint(1) NOT NULL DEFAULT '1',
  `criada_por` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_tarefas_projeto` (`projeto_id`),
  KEY `idx_expo_colag_tarefas_etapa` (`etapa_id`),
  KEY `idx_expo_colag_tarefas_limite` (`data_limite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_tipos_trabalho
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_tipos_trabalho` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `tipo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_tipos_projeto` (`projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projeto_visibilidade
--
CREATE TABLE IF NOT EXISTS `expo_colag_projeto_visibilidade` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int unsigned NOT NULL,
  `escopo` enum('Serie','Turma','Aluno') COLLATE utf8mb4_unicode_ci NOT NULL,
  `referencia_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expo_colag_visib` (`projeto_id`,`escopo`,`referencia_id`),
  KEY `idx_expo_colag_visib_ref` (`escopo`,`referencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_projetos
--
CREATE TABLE IF NOT EXISTS `expo_colag_projetos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `edicao_id` int unsigned DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capa_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `professor_id` int unsigned NOT NULL,
  `materia_principal_id` int unsigned DEFAULT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `contexto_pratico` text COLLATE utf8mb4_unicode_ci,
  `produto_esperado` text COLLATE utf8mb4_unicode_ci,
  `conexoes_interdisciplinares` text COLLATE utf8mb4_unicode_ci,
  `pre_requisitos` text COLLATE utf8mb4_unicode_ci,
  `modalidade` enum('Individual','Grupo','Grupo_com_papeis') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Grupo',
  `vagas_totais` int unsigned NOT NULL DEFAULT '5',
  `vagas_minimas` int unsigned NOT NULL DEFAULT '3',
  `tamanho_grupo` int unsigned DEFAULT NULL,
  `modo_ingresso` enum('Livre','Com_aprovacao','Convite_direto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Livre',
  `exige_justificativa` tinyint(1) NOT NULL DEFAULT '0',
  `lista_espera_ativa` tinyint(1) NOT NULL DEFAULT '1',
  `publicar_em` datetime DEFAULT NULL,
  `inscricoes_inicio` datetime DEFAULT NULL,
  `inscricoes_fim` datetime DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `data_apresentacao` datetime DEFAULT NULL,
  `briefing_entrega` text COLLATE utf8mb4_unicode_ci,
  `formatos_aceitos` json DEFAULT NULL,
  `vale_nota` tinyint(1) NOT NULL DEFAULT '0',
  `evento_avaliativo_id` int unsigned DEFAULT NULL,
  `tudinha_ativa` tinyint(1) NOT NULL DEFAULT '0',
  `tudinha_contexto` text COLLATE utf8mb4_unicode_ci,
  `custo_tudicoins` decimal(10,2) NOT NULL DEFAULT '0.00',
  `permite_solicitacao_recursos` tinyint(1) NOT NULL DEFAULT '1',
  `destaque` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('Rascunho','Publicado','Inscricoes_abertas','Em_execucao','Entrega','Avaliacao','Concluido','Cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Rascunho',
  `motivo_cancelamento` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_projetos_status_pub` (`status`,`publicar_em`),
  KEY `idx_expo_colag_projetos_professor` (`professor_id`),
  KEY `idx_expo_colag_projetos_edicao` (`edicao_id`),
  KEY `idx_expo_colag_projetos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_setores
--
CREATE TABLE IF NOT EXISTS `expo_colag_setores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `edicao_id` int unsigned NOT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_expo_colag_setores_edicao` (`edicao_id`,`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- expo_colag_stands
--
CREATE TABLE IF NOT EXISTS `expo_colag_stands` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `edicao_id` int unsigned NOT NULL,
  `projeto_id` int unsigned NOT NULL,
  `setor_id` int unsigned DEFAULT NULL,
  `numero` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posicao_mapa` json DEFAULT NULL,
  `qr_token` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `horario_apresentacao` datetime DEFAULT NULL,
  `resumo_publico` text COLLATE utf8mb4_unicode_ci,
  `capa_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expo_colag_stand_projeto` (`projeto_id`),
  UNIQUE KEY `uk_expo_colag_stand_qr` (`qr_token`),
  KEY `idx_expo_colag_stand_edicao` (`edicao_id`),
  KEY `idx_expo_colag_stand_setor` (`setor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- facial_device_pairing_codes
--
CREATE TABLE IF NOT EXISTS `facial_device_pairing_codes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `code_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_facial_pairing_code_hash` (`code_hash`),
  KEY `idx_facial_pairing_expiration` (`expires_at`,`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- facial_devices
--
CREATE TABLE IF NOT EXISTS `facial_devices` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `device_uid` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `paired_by_user_id` int DEFAULT NULL,
  `paired_at` datetime NOT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_facial_device_uid` (`device_uid`),
  UNIQUE KEY `uq_facial_device_token` (`token_hash`),
  KEY `idx_facial_device_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- faltas_eventos
--
CREATE TABLE IF NOT EXISTS `faltas_eventos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bimestre` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ano_letivo` int NOT NULL,
  `turmas_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `materias_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'IDs das matérias em colunas; NULL = grade horária',
  `origem` enum('manual','diario') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `created_by` int DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faltas_eventos_ano` (`ano_letivo`),
  KEY `idx_faltas_eventos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- faltas_lancamentos
--
CREATE TABLE IF NOT EXISTS `faltas_lancamentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `evento_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `materia_id` int NOT NULL DEFAULT '0',
  `faltas` decimal(6,2) NOT NULL DEFAULT '0.00',
  `observacao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_faltas_evento_aluno_materia` (`evento_id`,`aluno_id`,`materia_id`),
  KEY `idx_faltas_lanc_aluno` (`aluno_id`),
  KEY `idx_faltas_lanc_materia` (`materia_id`),
  CONSTRAINT `fk_faltas_lanc_evento` FOREIGN KEY (`evento_id`) REFERENCES `faltas_eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_audit
--
CREATE TABLE IF NOT EXISTS `finance_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entidade` enum('contract','installment','payment','discount') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidade_id` int NOT NULL,
  `acao` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dados_antes` json DEFAULT NULL,
  `dados_depois` json DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `usuario_nome` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entidade` (`entidade`,`entidade_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_bank_accounts
--
CREATE TABLE IF NOT EXISTS `finance_bank_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL COMMENT 'Ex: Conta Corrente Bradesco, Caixa Físico',
  `tipo` enum('corrente','poupanca','caixa','investimento') NOT NULL DEFAULT 'corrente',
  `banco_nome` varchar(80) DEFAULT NULL,
  `agencia` varchar(20) DEFAULT NULL,
  `conta` varchar(30) DEFAULT NULL,
  `saldo_inicial` decimal(12,2) NOT NULL DEFAULT '0.00',
  `saldo_atual` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- finance_bills
--
CREATE TABLE IF NOT EXISTS `finance_bills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int DEFAULT NULL COMMENT 'FK finance_chart_accounts',
  `descricao` varchar(255) NOT NULL,
  `fornecedor` varchar(120) DEFAULT NULL,
  `valor` decimal(12,2) NOT NULL,
  `valor_pago` decimal(12,2) DEFAULT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `data_competencia` date DEFAULT NULL COMMENT 'Mês de competência (pode diferir do caixa)',
  `status` enum('pendente','pago','vencido','cancelado') NOT NULL DEFAULT 'pendente',
  `recorrente` tinyint(1) NOT NULL DEFAULT '0',
  `recorrencia_dia` tinyint DEFAULT NULL COMMENT 'Dia do mês para gerar próxima',
  `banco_id` int DEFAULT NULL COMMENT 'Reservado: integração bancária futura',
  `banco_transacao_id` varchar(100) DEFAULT NULL COMMENT 'ID da transação no banco (Open Finance)',
  `comprovante_path` varchar(255) DEFAULT NULL,
  `observacoes` text,
  `criado_por` int DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `finance_bills_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `finance_chart_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- finance_charges
--
CREATE TABLE IF NOT EXISTS `finance_charges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `ano_letivo_id` int DEFAULT NULL,
  `categoria` enum('mensalidade','matricula','material_didatico','uniforme','taxa','passeio','ingresso','evento','outros') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outros',
  `descricao` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `forma_pagamento` enum('dinheiro','pix','boleto','transferencia','cartao','outro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `juros_aplicado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `multa_aplicada` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pendente','pago','vencido','cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `boleto_codigo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `unidade_id` int DEFAULT NULL COMMENT 'Empresa emissora da NF',
  `batch_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_status` (`status`),
  KEY `idx_vencimento` (`data_vencimento`),
  KEY `idx_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_chart_accounts
--
CREATE TABLE IF NOT EXISTS `finance_chart_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `tipo` enum('receita','despesa','ativo','passivo','patrimonio') NOT NULL,
  `grupo` varchar(80) DEFAULT NULL COMMENT 'Agrupamento: ex. Receitas Operacionais, Despesas Administrativas',
  `descricao` text,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- finance_config
--
CREATE TABLE IF NOT EXISTS `finance_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `juros_mensal` decimal(5,2) NOT NULL DEFAULT '1.00',
  `multa_atraso` decimal(5,2) NOT NULL DEFAULT '2.00',
  `dias_carencia` tinyint NOT NULL DEFAULT '0',
  `dia_vencimento_padrao` tinyint NOT NULL DEFAULT '10',
  `gerar_debito_auto` tinyint(1) NOT NULL DEFAULT '1',
  `email_remetente` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome_escola_boleto` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `desconto_pontualidade_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `desconto_pontualidade_dia` tinyint NOT NULL DEFAULT '5',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_contract_discounts
--
CREATE TABLE IF NOT EXISTS `finance_contract_discounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contract_id` int NOT NULL,
  `discount_rule_id` int DEFAULT NULL,
  `tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculo` enum('percentual','fixo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentual',
  `valor` decimal(10,2) NOT NULL,
  `valor_aplicado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `irmao_aluno_id` int DEFAULT NULL,
  `aprovado_por` int DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `status` enum('pendente','aprovado','rejeitado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aprovado',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contract` (`contract_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_contract_items
--
CREATE TABLE IF NOT EXISTS `finance_contract_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contract_id` int NOT NULL,
  `plan_item_id` int DEFAULT NULL,
  `price_table_id` int DEFAULT NULL,
  `categoria` enum('mensalidade','matricula','material_didatico','uniforme','taxa','outros') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `quantidade` decimal(10,3) NOT NULL DEFAULT '1.000',
  `valor_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_desconto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_liquido` decimal(10,2) NOT NULL DEFAULT '0.00',
  `num_parcelas` int NOT NULL DEFAULT '1',
  `mes_inicio` tinyint NOT NULL DEFAULT '1',
  `mes_fim` tinyint DEFAULT NULL,
  `dia_vencimento` tinyint DEFAULT NULL,
  `fornecedor_externo` tinyint(1) NOT NULL DEFAULT '0',
  `nome_instituicao` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade_id` int DEFAULT NULL,
  `status` enum('ativo','cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contract` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_contracts
--
CREATE TABLE IF NOT EXISTS `finance_contracts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `matricula_id` int DEFAULT NULL,
  `enrollment_id` int DEFAULT NULL,
  `plan_id` int DEFAULT NULL,
  `ano_letivo_id` int NOT NULL,
  `responsavel_id` int DEFAULT NULL,
  `responsavel_nome` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `responsavel_cpf` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsavel_email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsavel_telefone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_bruto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_desconto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_liquido` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('rascunho','ativo','cancelado','encerrado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `plano_pagamento` enum('mensal','semestral','anual','avulso') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mensal',
  `num_parcelas` int NOT NULL DEFAULT '12',
  `dia_vencimento` tinyint NOT NULL DEFAULT '10',
  `mes_inicio` tinyint NOT NULL DEFAULT '1',
  `mes_fim` tinyint NOT NULL DEFAULT '12',
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `criado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_ano_letivo` (`ano_letivo_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_discount_rules
--
CREATE TABLE IF NOT EXISTS `finance_discount_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('bolsa','irmaos','convenio','funcionario','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculo` enum('percentual','fixo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentual',
  `valor` decimal(10,2) NOT NULL,
  `acumulavel` tinyint(1) NOT NULL DEFAULT '0',
  `limite_acumulado` decimal(10,2) DEFAULT NULL,
  `categorias_aplicaveis` json DEFAULT NULL,
  `requer_aprovacao` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_installments
--
CREATE TABLE IF NOT EXISTS `finance_installments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contract_id` int NOT NULL,
  `contract_item_id` int DEFAULT NULL,
  `num_parcela` int NOT NULL,
  `categoria` enum('mensalidade','matricula','material_didatico','uniforme','taxa','outros') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_nominal` decimal(10,2) NOT NULL,
  `valor_desconto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_cobrado` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `juros_aplicado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `multa_aplicada` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pendente','pago','vencido','cancelado','renegociado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `boleto_codigo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boleto_gerado_em` datetime DEFAULT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contract` (`contract_id`),
  KEY `idx_vencimento` (`data_vencimento`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_ledger
--
CREATE TABLE IF NOT EXISTS `finance_ledger` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `tipo` enum('debito','credito','estorno','ajuste') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outros',
  `descricao` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `saldo_acumulado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `data_lancamento` date NOT NULL,
  `referencia_tipo` enum('installment','charge','payment','estorno','ajuste','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` int DEFAULT NULL,
  `contract_id` int DEFAULT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `gerado_auto` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_data` (`data_lancamento`),
  KEY `idx_ref` (`referencia_tipo`,`referencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_payments
--
CREATE TABLE IF NOT EXISTS `finance_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `installment_id` int NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_pagamento` date NOT NULL,
  `forma_pagamento` enum('dinheiro','pix','boleto','transferencia','cartao','outro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `referencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `registrado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_installment` (`installment_id`),
  KEY `idx_data` (`data_pagamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_plan_items
--
CREATE TABLE IF NOT EXISTS `finance_plan_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plan_id` int NOT NULL,
  `categoria` enum('mensalidade','matricula','material_didatico','uniforme','taxa','outros') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_base` decimal(10,2) NOT NULL DEFAULT '0.00',
  `num_parcelas` int NOT NULL DEFAULT '1',
  `mes_inicio` tinyint NOT NULL DEFAULT '1',
  `mes_fim` tinyint DEFAULT NULL,
  `dia_vencimento` tinyint DEFAULT NULL,
  `fornecedor_externo` tinyint(1) NOT NULL DEFAULT '0',
  `nome_instituicao` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade_id` int DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_plans
--
CREATE TABLE IF NOT EXISTS `finance_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ano_letivo_id` int NOT NULL,
  `serie_id` int DEFAULT NULL,
  `plano_origem_id` int DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ano` (`ano_letivo_id`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_price_table
--
CREATE TABLE IF NOT EXISTS `finance_price_table` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ano_letivo_id` int NOT NULL,
  `serie_id` int DEFAULT NULL,
  `curso_id` int DEFAULT NULL,
  `categoria` enum('mensalidade','matricula','material_didatico','uniforme','taxa','outros') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_base` decimal(10,2) NOT NULL DEFAULT '0.00',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ano_letivo` (`ano_letivo_id`),
  KEY `idx_serie` (`serie_id`),
  KEY `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_receipts
--
CREATE TABLE IF NOT EXISTS `finance_receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payment_id` int DEFAULT NULL,
  `charge_id` int DEFAULT NULL,
  `aluno_id` int NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_pagamento` date NOT NULL,
  `numero` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pdf_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enviado_email` tinyint(1) NOT NULL DEFAULT '0',
  `enviado_wpp` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_numero` (`numero`),
  KEY `idx_aluno` (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- finance_renegotiations
--
CREATE TABLE IF NOT EXISTS `finance_renegotiations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `valor_total_divida` decimal(10,2) NOT NULL,
  `valor_entrada` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_parcelado` decimal(10,2) NOT NULL,
  `num_parcelas` int NOT NULL DEFAULT '1',
  `dia_vencimento` tinyint NOT NULL DEFAULT '10',
  `mes_inicio` tinyint NOT NULL,
  `ano_inicio` int NOT NULL,
  `status` enum('ativo','quitado','cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `criado_por` int DEFAULT NULL,
  `aprovado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_contract` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- financeiro_valores_mensais
--
CREATE TABLE IF NOT EXISTS `financeiro_valores_mensais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mes_referencia` date NOT NULL COMMENT 'Mês de referência (primeiro dia do mês)',
  `total_alunos_pagantes` int NOT NULL DEFAULT '0',
  `total_professores_pagantes` int NOT NULL DEFAULT '0',
  `total_usuarios_pagantes` int NOT NULL DEFAULT '0' COMMENT 'Soma de alunos + professores',
  `valor_por_usuario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_total` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total a pagar no mês',
  `status` enum('aberto','pago') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberto',
  `data_vencimento` date DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `registrado_por` int DEFAULT NULL COMMENT 'ID do usuário que registrou',
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mes_referencia` (`mes_referencia`),
  KEY `idx_mes_referencia` (`mes_referencia`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status_valores_cobrados_mensais` (`status`),
  KEY `idx_data_vencimento_valores_cobrados_mensais` (`data_vencimento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- flashcard_explicacoes
--
CREATE TABLE IF NOT EXISTS `flashcard_explicacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `deck_id` int NOT NULL,
  `card_id` int NOT NULL,
  `explicacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `origem` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ia' COMMENT 'Origem da explicação: ia',
  `numero_tentativa` int NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno_deck_card` (`aluno_id`,`deck_id`,`card_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- flashcards_baralhos
--
CREATE TABLE IF NOT EXISTS `flashcards_baralhos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `topic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_flashcard_decks_aluno` (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- flashcards_cartas
--
CREATE TABLE IF NOT EXISTS `flashcards_cartas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `deck_id` int NOT NULL,
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_flashcards_deck` (`deck_id`),
  CONSTRAINT `flashcards_cartas_ibfk_1` FOREIGN KEY (`deck_id`) REFERENCES `flashcards_baralhos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- flashcards_modelos
--
CREATE TABLE IF NOT EXISTS `flashcards_modelos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `topic_normalized` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_templates_lookup` (`topic_normalized`,`grade`,`quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- flashcards_modelos_cartas
--
CREATE TABLE IF NOT EXISTS `flashcards_modelos_cartas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_tc_template` (`template_id`),
  CONSTRAINT `flashcards_modelos_cartas_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `flashcards_modelos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- forum_anexos
--
CREATE TABLE IF NOT EXISTS `forum_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `topic_id` int DEFAULT NULL,
  `reply_id` int DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL DEFAULT 'application/octet-stream',
  `file_size` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_forum_att_topic` (`topic_id`),
  KEY `idx_forum_att_reply` (`reply_id`),
  CONSTRAINT `forum_anexos_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_anexos_ibfk_2` FOREIGN KEY (`reply_id`) REFERENCES `forum_respostas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- forum_denuncias
--
CREATE TABLE IF NOT EXISTS `forum_denuncias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `topic_id` int DEFAULT NULL,
  `reply_id` int DEFAULT NULL,
  `reporter_id` int NOT NULL,
  `reporter_role` varchar(20) NOT NULL DEFAULT 'student',
  `reason` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_forum_reports_status` (`status`),
  KEY `idx_forum_reports_topic` (`topic_id`),
  KEY `idx_forum_reports_reply` (`reply_id`),
  CONSTRAINT `forum_denuncias_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_denuncias_ibfk_2` FOREIGN KEY (`reply_id`) REFERENCES `forum_respostas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- forum_moderacao_alertas
--
CREATE TABLE IF NOT EXISTS `forum_moderacao_alertas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'topic ou reply',
  `topic_id` int DEFAULT NULL,
  `reply_id` int DEFAULT NULL,
  `author_id` int NOT NULL,
  `author_role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `content_preview` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo_ia` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending, visto',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- forum_respostas
--
CREATE TABLE IF NOT EXISTS `forum_respostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `topic_id` int NOT NULL,
  `author_id` int NOT NULL,
  `author_role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_best_answer` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_forum_replies_topic` (`topic_id`),
  KEY `idx_forum_replies_author` (`author_id`,`author_role`),
  KEY `idx_forum_replies_best` (`topic_id`,`is_best_answer`),
  CONSTRAINT `forum_respostas_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- forum_topicos
--
CREATE TABLE IF NOT EXISTS `forum_topicos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_id` int NOT NULL,
  `author_role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `subject_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_forum_topics_author` (`author_id`,`author_role`),
  KEY `idx_forum_topics_resolved` (`is_resolved`),
  KEY `idx_forum_topics_created` (`created_at`),
  KEY `idx_forum_topics_turma` (`turma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- forum_topicos_turmas
--
CREATE TABLE IF NOT EXISTS `forum_topicos_turmas` (
  `topic_id` int NOT NULL,
  `turma_id` int NOT NULL,
  PRIMARY KEY (`topic_id`,`turma_id`),
  KEY `idx_turma` (`turma_id`),
  CONSTRAINT `forum_topicos_turmas_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- forum_usuarios_reputacao
--
CREATE TABLE IF NOT EXISTS `forum_usuarios_reputacao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `user_role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `points` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_forum_rep_user` (`user_id`,`user_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- forum_votos
--
CREATE TABLE IF NOT EXISTS `forum_votos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reply_id` int NOT NULL,
  `voter_id` int NOT NULL,
  `voter_role` varchar(20) NOT NULL DEFAULT 'student',
  `vote_type` varchar(10) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_forum_votes_reply_voter` (`reply_id`,`voter_id`,`voter_role`),
  KEY `idx_forum_votes_reply` (`reply_id`),
  CONSTRAINT `forum_votos_ibfk_1` FOREIGN KEY (`reply_id`) REFERENCES `forum_respostas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- grade_horaria
--
CREATE TABLE IF NOT EXISTS `grade_horaria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dia_semana` tinyint NOT NULL COMMENT '1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado, 7=Domingo',
  `horario_de` time NOT NULL,
  `horario_ate` time NOT NULL,
  `turma_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `periodo` enum('manha','tarde') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manha',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dia_semana` (`dia_semana`),
  KEY `idx_turma` (`turma_id`),
  KEY `idx_professor` (`professor_id`),
  KEY `idx_periodo` (`periodo`),
  KEY `fk_gh_materia` (`materia_id`),
  CONSTRAINT `fk_gh_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gh_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gh_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- historico_assinaturas
--
CREATE TABLE IF NOT EXISTS `historico_assinaturas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `historico_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `usuario_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` enum('Diretor','Secretario_Escolar','Outro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_registro` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` enum('Eletronica_Simples','GovBr','ICP_Brasil') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Eletronica_Simples',
  `assinado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_origem` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_historico_assinatura_cargo` (`historico_id`,`cargo`),
  KEY `idx_historico_assinaturas_doc` (`historico_id`),
  CONSTRAINT `fk_historico_assinatura_doc` FOREIGN KEY (`historico_id`) REFERENCES `historico_documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- historico_documentos
--
CREATE TABLE IF NOT EXISTS `historico_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `unidade_id` int DEFAULT NULL,
  `versao` int NOT NULL DEFAULT '1',
  `status` enum('Rascunho','Conferido','Emitido','Assinado','Entregue','Cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Rascunho',
  `hash_validacao` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalidade` enum('Transferencia','Conclusao','Solicitacao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Solicitacao',
  `observacoes_gerais` text COLLATE utf8mb4_unicode_ci,
  `numero_registro_sed` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nº SED/GDAE (Campo 6 modelo SP)',
  `snapshot_json` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Foto imutável dos dados do aluno/unidade na emissão',
  `pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emitido_em` datetime DEFAULT NULL,
  `emitido_por` int DEFAULT NULL,
  `conferido_em` datetime DEFAULT NULL,
  `conferido_por` int DEFAULT NULL,
  `substitui_id` int DEFAULT NULL COMMENT 'Versão anterior cancelada',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_historico_hash` (`hash_validacao`),
  KEY `idx_historico_aluno` (`aluno_id`),
  KEY `idx_historico_status` (`status`),
  KEY `idx_historico_aluno_versao` (`aluno_id`,`versao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- historico_itens
--
CREATE TABLE IF NOT EXISTS `historico_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `historico_id` int NOT NULL,
  `ano_letivo` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `serie_ano` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `componente` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `materia_id` int DEFAULT NULL,
  `resultado_valor` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parecer_descritivo` text COLLATE utf8mb4_unicode_ci,
  `carga_horaria` int DEFAULT NULL,
  `frequencia_percentual` decimal(5,2) DEFAULT NULL,
  `origem` enum('Interno','Externo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Interno',
  `escola_origem` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_historico_itens_doc` (`historico_id`),
  KEY `idx_historico_itens_ano` (`historico_id`,`ano_letivo`),
  CONSTRAINT `fk_historico_itens_doc` FOREIGN KEY (`historico_id`) REFERENCES `historico_documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- historico_resultados_anuais
--
CREATE TABLE IF NOT EXISTS `historico_resultados_anuais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `historico_id` int NOT NULL,
  `ano_letivo` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `serie_ano` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resultado` enum('Aprovado','Aprovado_Conselho','Retido','Transferido','Evadido','Cursando') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cursando',
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_historico_resultado_ano` (`historico_id`,`ano_letivo`,`serie_ano`),
  CONSTRAINT `fk_historico_resultado_doc` FOREIGN KEY (`historico_id`) REFERENCES `historico_documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ingles_conversas
--
CREATE TABLE IF NOT EXISTS `ingles_conversas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  CONSTRAINT `ingles_conversas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ingles_mensagens
--
CREATE TABLE IF NOT EXISTS `ingles_mensagens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `conversa_id` int NOT NULL,
  `role` enum('user','assistant') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `conversa_id` (`conversa_id`),
  CONSTRAINT `ingles_mensagens_ibfk_1` FOREIGN KEY (`conversa_id`) REFERENCES `ingles_conversas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- inventory_items
--
CREATE TABLE IF NOT EXISTS `inventory_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(220) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidade_medida` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'un',
  `categoria` enum('limpeza','escritorio','didatico','merenda','higiene','laboratorio','outro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `estoque_minimo` decimal(12,3) NOT NULL DEFAULT '0.000',
  `estoque_maximo` decimal(12,3) DEFAULT NULL,
  `ponto_reposicao` decimal(12,3) NOT NULL DEFAULT '0.000',
  `custo_medio` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_items_codigo` (`codigo`),
  KEY `idx_inventory_items_categoria` (`categoria`),
  KEY `idx_inventory_items_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- inventory_lots
--
CREATE TABLE IF NOT EXISTS `inventory_lots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `lote` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validade` date DEFAULT NULL,
  `quantidade_atual` decimal(12,3) NOT NULL DEFAULT '0.000',
  `custo_unitario` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `entrada_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_lots_item_warehouse` (`item_id`,`warehouse_id`),
  KEY `idx_inventory_lots_validade` (`validade`),
  KEY `idx_inventory_lots_quantidade` (`quantidade_atual`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- inventory_movements
--
CREATE TABLE IF NOT EXISTS `inventory_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `warehouse_destino_id` int DEFAULT NULL,
  `lot_id` int DEFAULT NULL,
  `tipo` enum('entrada','saida','transferencia','ajuste','baixa') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `custo_unitario` decimal(12,4) DEFAULT NULL,
  `documento` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fornecedor_id` int DEFAULT NULL,
  `requisicao_id` int DEFAULT NULL,
  `setor` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `motivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `realizado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_movements_item` (`item_id`),
  KEY `idx_inventory_movements_created` (`created_at`),
  KEY `idx_inventory_movements_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- inventory_requisitions
--
CREATE TABLE IF NOT EXISTS `inventory_requisitions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `setor` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `solicitante_nome` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `justificativa` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pendente','aprovada','atendida','rejeitada','cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `solicitado_por` int DEFAULT NULL,
  `aprovado_por` int DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `atendido_por` int DEFAULT NULL,
  `atendido_em` datetime DEFAULT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_requisitions_status` (`status`),
  KEY `idx_inventory_requisitions_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- inventory_suppliers
--
CREATE TABLE IF NOT EXISTS `inventory_suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnpj` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contato` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_suppliers_nome` (`nome`),
  KEY `idx_inventory_suppliers_cnpj` (`cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- inventory_warehouses
--
CREATE TABLE IF NOT EXISTS `inventory_warehouses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('central','cantina','laboratorio','limpeza','secretaria','outro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'central',
  `location_id` int DEFAULT NULL,
  `responsavel_nome` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_warehouses_location` (`location_id`),
  KEY `idx_inventory_warehouses_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jogos_acoes
--
CREATE TABLE IF NOT EXISTS `jogos_acoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `partida_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` datetime NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partida_id` (`partida_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `fk_game_actions_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_game_actions_partida` FOREIGN KEY (`partida_id`) REFERENCES `jogos_milhao_partidas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jogos_milhao_partidas
--
CREATE TABLE IF NOT EXISTS `jogos_milhao_partidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `pontuacao_atual` decimal(10,2) DEFAULT '0.00',
  `pergunta_atual` int DEFAULT '1',
  `ajudas_usadas` json DEFAULT NULL,
  `status` enum('em_andamento','finalizada','abandonada') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'em_andamento',
  `data_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` timestamp NULL DEFAULT NULL,
  `premio_final` decimal(10,2) DEFAULT '0.00',
  `perguntas_usadas` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `last_activity` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_partidas_aluno` (`aluno_id`),
  KEY `idx_partidas_status` (`status`),
  KEY `idx_last_activity_status` (`last_activity`,`status`),
  CONSTRAINT `jogos_milhao_partidas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- jogos_milhao_perguntas
--
CREATE TABLE IF NOT EXISTS `jogos_milhao_perguntas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pergunta` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_b` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_c` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_d` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `resposta_correta` enum('A','B','C','D') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `explicacao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `nivel_dificuldade` enum('facil','medio','dificil') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tema` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ativa` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_perguntas_nivel` (`nivel_dificuldade`),
  KEY `idx_perguntas_tema` (`tema`),
  KEY `idx_perguntas_ativa` (`ativa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- jogos_milhao_respostas
--
CREATE TABLE IF NOT EXISTS `jogos_milhao_respostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `partida_id` int NOT NULL,
  `pergunta_id` int NOT NULL,
  `resposta_escolhida` enum('A','B','C','D') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `resposta_correta` enum('A','B','C','D') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `acertou` tinyint(1) NOT NULL,
  `ajuda_usada` enum('plateia','universitarios','pular','nenhuma') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'nenhuma',
  `tempo_resposta` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pergunta_id` (`pergunta_id`),
  KEY `idx_respostas_partida` (`partida_id`),
  CONSTRAINT `jogos_milhao_respostas_ibfk_1` FOREIGN KEY (`partida_id`) REFERENCES `jogos_milhao_partidas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jogos_milhao_respostas_ibfk_2` FOREIGN KEY (`pergunta_id`) REFERENCES `jogos_milhao_perguntas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- jogos_sessoes
--
CREATE TABLE IF NOT EXISTS `jogos_sessoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `partida_id` int NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `aluno_id` (`aluno_id`),
  KEY `partida_id` (`partida_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `fk_game_sessions_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_game_sessions_partida` FOREIGN KEY (`partida_id`) REFERENCES `jogos_milhao_partidas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jogos_tokens_externos
--
CREATE TABLE IF NOT EXISTS `jogos_tokens_externos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas
--
CREATE TABLE IF NOT EXISTS `jornadas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `materia_id` int DEFAULT NULL,
  `ano_letivo` smallint unsigned DEFAULT NULL COMMENT 'Ano letivo da jornada',
  `bimestre` tinyint unsigned DEFAULT NULL COMMENT 'Bimestre 1 a 4 da jornada',
  `avaliativo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Sempre 1 (sim)',
  `plano_aula_id` int DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `estrutura` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Estrutura da jornada (resumo, exercícios, dúvidas)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ativo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_professor` (`professor_id`),
  KEY `idx_turma` (`turma_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_jornadas_turma` (`turma_id`),
  KEY `idx_jornadas_professor` (`professor_id`),
  KEY `idx_plano_aula_id` (`plano_aula_id`),
  KEY `idx_jornadas_turma_ativo` (`turma_id`,`ativo`),
  KEY `idx_estrutura` (`estrutura`(100)),
  CONSTRAINT `jornadas_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_aulas
--
CREATE TABLE IF NOT EXISTS `jornadas_aulas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jornada_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `nome_aula` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resumo_oficial` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pontos_principais` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `conteudos_adicionais` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('ativa','pausada','finalizada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `ordem` (`ordem`),
  KEY `idx_ja_jornada_status` (`jornada_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_blocos_conteudo
--
CREATE TABLE IF NOT EXISTS `jornadas_blocos_conteudo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jornada_id` int NOT NULL,
  `tipo_bloco_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `conteudo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ordem` int NOT NULL DEFAULT '0',
  `obrigatorio` tinyint(1) DEFAULT '1',
  `tempo_estimado` int DEFAULT NULL COMMENT 'Tempo em minutos',
  `status` enum('ativo','inativo','rascunho') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `configuracoes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Configurações específicas do bloco',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `tipo_bloco_id` (`tipo_bloco_id`),
  KEY `ordem` (`ordem`),
  KEY `idx_jornadas_blocos_ordem` (`jornada_id`,`ordem`),
  CONSTRAINT `jornadas_blocos_conteudo_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_blocos_conteudo_ibfk_2` FOREIGN KEY (`tipo_bloco_id`) REFERENCES `jornadas_tipos_blocos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_duvidas
--
CREATE TABLE IF NOT EXISTS `jornadas_duvidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `aula_id` int NOT NULL,
  `duvida` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resposta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `respondido_por` int DEFAULT NULL,
  `respondido_em` timestamp NULL DEFAULT NULL,
  `status` enum('pendente','respondida','arquivada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `aula_id` (`aula_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_exercicios
--
CREATE TABLE IF NOT EXISTS `jornadas_exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jornada_id` int NOT NULL,
  `aula_id` int DEFAULT NULL,
  `tipo` enum('manual','ia_gerado','ia_aprovado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `questoes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('rascunho','aprovado','publicado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `aprovado_por` int DEFAULT NULL,
  `aprovado_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `aula_id` (`aula_id`),
  KEY `idx_je_jornada_status` (`jornada_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_exercicios_auditoria
--
CREATE TABLE IF NOT EXISTS `jornadas_exercicios_auditoria` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `modulo_id` int NOT NULL,
  `exercicio_id` int NOT NULL,
  `tipo_acao` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `de_valor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `para_valor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `resposta_final` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `correto` tinyint(1) DEFAULT NULL,
  `pontuacao` decimal(10,2) DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detalhes_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jornadas_auditoria_aluno` (`aluno_id`),
  KEY `idx_jornadas_auditoria_jornada` (`jornada_id`),
  KEY `idx_jornadas_auditoria_modulo` (`modulo_id`),
  KEY `idx_jornadas_auditoria_exercicio` (`exercicio_id`),
  KEY `idx_jornadas_auditoria_acao` (`tipo_acao`),
  KEY `idx_jornadas_auditoria_data` (`created_at`),
  KEY `idx_jea_aluno_created` (`aluno_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_materias
--
CREATE TABLE IF NOT EXISTS `jornadas_materias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cor` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#3B82F6',
  `icone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'book',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_mensagens
--
CREATE TABLE IF NOT EXISTS `jornadas_mensagens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jornada_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `remetente_tipo` enum('aluno','professor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remetente_id` int NOT NULL,
  `mensagem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lida` tinyint(1) DEFAULT '0',
  `lida_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `professor_id` (`professor_id`),
  KEY `remetente_id` (`remetente_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `jornadas_mensagens_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_mensagens_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_mensagens_ibfk_3` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_mensagens_anexos
--
CREATE TABLE IF NOT EXISTS `jornadas_mensagens_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mensagem_id` int NOT NULL,
  `nome_arquivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho_arquivo` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mensagem_id` (`mensagem_id`),
  CONSTRAINT `jornadas_mensagens_anexos_ibfk_1` FOREIGN KEY (`mensagem_id`) REFERENCES `jornadas_mensagens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_modulos
--
CREATE TABLE IF NOT EXISTS `jornadas_modulos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jornada_id` int NOT NULL,
  `aula_id` int DEFAULT NULL,
  `tipo_modulo` enum('resumo_aluno','resumo_professor','duvidas_ia','redacao','exercicios','sugestoes','video','dica_professor','conteudo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ordem` int NOT NULL DEFAULT '1',
  `obrigatorio` tinyint(1) DEFAULT '0',
  `status` enum('ativo','inativo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `aula_id` (`aula_id`),
  KEY `tipo_modulo` (`tipo_modulo`),
  KEY `idx_jm_jornada` (`jornada_id`),
  CONSTRAINT `jornadas_modulos_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_modulos_ibfk_2` FOREIGN KEY (`aula_id`) REFERENCES `jornadas_aulas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_modulos_documentos
--
CREATE TABLE IF NOT EXISTS `jornadas_modulos_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `arquivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_tamanho` int DEFAULT NULL,
  `tipo_arquivo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `status` enum('ativo','inativo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `modulo_id` (`modulo_id`),
  KEY `idx_jmd_modulo_status` (`modulo_id`,`status`),
  CONSTRAINT `jornadas_modulos_documentos_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_modulos_exercicios
--
CREATE TABLE IF NOT EXISTS `jornadas_modulos_exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int NOT NULL,
  `tipo` enum('alternativas','verdadeiro_falso','dissertativa','preencher_lacuna') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alternativas',
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enunciado` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'HTML from CKEditor (sanitized)',
  `questoes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'JSON with opcoes[].texto as HTML',
  `resposta_correta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `gabarito` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pontuacao` decimal(5,2) DEFAULT '1.00',
  `ordem` int NOT NULL DEFAULT '1',
  `gerado_ia` tinyint(1) DEFAULT '0',
  `status` enum('rascunho','publicado','arquivado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `imagem_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL da imagem do enunciado (upload ou colagem)',
  `nivel_dificuldade` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'facil, medio, dificil',
  PRIMARY KEY (`id`),
  KEY `modulo_id` (`modulo_id`),
  KEY `tipo` (`tipo`),
  KEY `idx_jme_modulo_status` (`modulo_id`,`status`),
  CONSTRAINT `jornadas_modulos_exercicios_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_modulos_textos
--
CREATE TABLE IF NOT EXISTS `jornadas_modulos_textos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ordem` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_modulo_id` (`modulo_id`),
  CONSTRAINT `fk_jmt_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_modulos_videos
--
CREATE TABLE IF NOT EXISTS `jornadas_modulos_videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int NOT NULL,
  `tipo` enum('youtube','upload','link_externo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'youtube',
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url_youtube` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_video` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_tamanho` int DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `status` enum('ativo','inativo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `modulo_id` (`modulo_id`),
  KEY `tipo` (`tipo`),
  KEY `idx_jmv_modulo_status` (`modulo_id`,`status`),
  CONSTRAINT `jornadas_modulos_videos_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_progresso_alunos
--
CREATE TABLE IF NOT EXISTS `jornadas_progresso_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `aula_id` int DEFAULT NULL,
  `modulo_id` int DEFAULT NULL,
  `exercicio_id` int DEFAULT NULL,
  `exercicio_modulo_id` int DEFAULT NULL,
  `atividade_tipo` enum('aula','exercicio','resumo','duvida','modulo','exercicio_modulo','jornada_concluida','visualizacao') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tempo_gasto` int DEFAULT '0',
  `status` enum('iniciado','em_andamento','concluido','pausado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'iniciado',
  `pontuacao` decimal(5,2) DEFAULT '0.00',
  `resposta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Resposta do aluno (pode ser JSON)',
  `data_inicio` timestamp NULL DEFAULT NULL,
  `data_conclusao` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `aula_id` (`aula_id`),
  KEY `exercicio_id` (`exercicio_id`),
  KEY `modulo_id` (`modulo_id`),
  KEY `exercicio_modulo_id` (`exercicio_modulo_id`),
  KEY `idx_jpa_jornada_aluno_status` (`jornada_id`,`aluno_id`,`status`),
  KEY `idx_jpa_jornada_aluno_tipo_status` (`jornada_id`,`aluno_id`,`atividade_tipo`,`status`),
  KEY `idx_jornadas_progresso_aluno_jornada` (`aluno_id`,`jornada_id`),
  KEY `idx_jpa_aluno_jornada_tipo` (`aluno_id`,`jornada_id`,`atividade_tipo`),
  KEY `idx_jpa_aluno_modulo_tipo` (`aluno_id`,`modulo_id`,`atividade_tipo`),
  KEY `idx_jpa_aluno_ex_modulo_tipo` (`aluno_id`,`exercicio_modulo_id`,`atividade_tipo`),
  CONSTRAINT `jornadas_progresso_alunos_ibfk_exercicio_modulo` FOREIGN KEY (`exercicio_modulo_id`) REFERENCES `jornadas_modulos_exercicios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_progresso_alunos_ibfk_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_progresso_blocos
--
CREATE TABLE IF NOT EXISTS `jornadas_progresso_blocos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `bloco_id` int NOT NULL,
  `status` enum('nao_iniciado','em_andamento','concluido','bloqueado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'nao_iniciado',
  `data_inicio` timestamp NULL DEFAULT NULL,
  `data_conclusao` timestamp NULL DEFAULT NULL,
  `tempo_gasto` int DEFAULT NULL COMMENT 'Tempo em minutos',
  `tentativas` int DEFAULT '0',
  `pontuacao` decimal(5,2) DEFAULT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dados_progresso` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Dados específicos do progresso',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `aluno_jornada_bloco` (`aluno_id`,`jornada_id`,`bloco_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `bloco_id` (`bloco_id`),
  KEY `status` (`status`),
  KEY `idx_progresso_blocos_status` (`status`,`data_conclusao`),
  KEY `idx_progresso_blocos_aluno_jornada` (`aluno_id`,`jornada_id`,`status`),
  CONSTRAINT `jornadas_progresso_blocos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_progresso_blocos_ibfk_2` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_progresso_blocos_ibfk_3` FOREIGN KEY (`bloco_id`) REFERENCES `jornadas_blocos_conteudo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_redacoes
--
CREATE TABLE IF NOT EXISTS `jornadas_redacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jornada_id` int NOT NULL,
  `aula_id` int DEFAULT NULL COMMENT 'Aula específica da jornada (opcional)',
  `professor_id` int NOT NULL COMMENT 'Professor que sugeriu o tema',
  `tema_sugerido` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tema sugerido pelo professor',
  `descricao_tema` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Descrição detalhada do tema',
  `imagem_tema` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho da imagem do tema',
  `documento_tema` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho do documento do tema (PDF, DOC, DOCX, etc.)',
  `status` enum('pendente','em_andamento','entregue','corrigida','retornada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `data_limite` datetime DEFAULT NULL COMMENT 'Data limite para entrega',
  `correcao_ia_automatica` tinyint(1) DEFAULT '1' COMMENT 'Se 1, corrige automaticamente pela IA quando o aluno finaliza. Se 0, não corrige automaticamente.',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `aula_id` (`aula_id`),
  KEY `professor_id` (`professor_id`),
  KEY `status` (`status`),
  CONSTRAINT `jornadas_redacoes_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_redacoes_ibfk_2` FOREIGN KEY (`aula_id`) REFERENCES `jornadas_aulas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jornadas_redacoes_ibfk_3` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_redacoes_alunos
--
CREATE TABLE IF NOT EXISTS `jornadas_redacoes_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jornada_redacao_id` int NOT NULL COMMENT 'ID da redação da jornada',
  `redacao_id` int NOT NULL COMMENT 'ID da redação do aluno',
  `aluno_id` int NOT NULL COMMENT 'ID do aluno',
  `versao` int DEFAULT '1' COMMENT 'Versão da redação (1, 2, 3...)',
  `status` enum('rascunho','entregue','corrigida_ia','corrigida_professor','retornada','aprovada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `correcao_ia_feita` tinyint(1) DEFAULT '0' COMMENT 'Indica se a IA já corrigiu',
  `correcao_professor_feita` tinyint(1) DEFAULT '0' COMMENT 'Indica se o professor já corrigiu',
  `usar_correcao_professor` tinyint(1) DEFAULT '0' COMMENT 'Professor escolheu usar sua correção ao invés da IA',
  `retornada_para_reescrever` tinyint(1) DEFAULT '0' COMMENT 'Indica se foi retornada para reescrever',
  `observacoes_professor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Observações do professor para o aluno',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usar_media_notas` tinyint(1) DEFAULT '0' COMMENT 'Usar média entre nota do professor e IA',
  `nota_media` decimal(5,2) DEFAULT NULL COMMENT 'Média calculada entre nota do professor e IA',
  `nota_final_utilizada` decimal(5,2) DEFAULT NULL COMMENT 'Nota final que será utilizada (professor, IA ou média)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_redacao_jornada` (`redacao_id`,`jornada_redacao_id`),
  KEY `jornada_redacao_id` (`jornada_redacao_id`),
  KEY `redacao_id` (`redacao_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `status` (`status`),
  KEY `idx_jra_jornada_redacao_id` (`jornada_redacao_id`),
  CONSTRAINT `jornadas_redacoes_alunos_ibfk_1` FOREIGN KEY (`jornada_redacao_id`) REFERENCES `jornadas_redacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_redacoes_alunos_ibfk_2` FOREIGN KEY (`redacao_id`) REFERENCES `redacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_redacoes_alunos_ibfk_3` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_relatorios
--
CREATE TABLE IF NOT EXISTS `jornadas_relatorios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `tempo_total` int DEFAULT '0',
  `aulas_concluidas` int DEFAULT '0',
  `exercicios_concluidos` int DEFAULT '0',
  `resumos_feitos` int DEFAULT '0',
  `duvidas_enviadas` int DEFAULT '0',
  `pontuacao_total` decimal(5,2) DEFAULT '0.00',
  `percentual_conclusao` decimal(5,2) DEFAULT '0.00',
  `relatorio_detalhado` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `gerado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `jornada_id` (`jornada_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_resumos_alunos
--
CREATE TABLE IF NOT EXISTS `jornadas_resumos_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `jornada_id` int DEFAULT NULL,
  `aula_id` int DEFAULT NULL,
  `modulo_id` int DEFAULT NULL,
  `resumo_aluno` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `analise_ia` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `lacunas_identificadas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `explicacoes_complementares` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pontuacao` decimal(5,2) DEFAULT '0.00',
  `status` enum('em_analise','analisado','revisado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'em_analise',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `observacoes_professor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Observações do professor (podem ser exibidas ao aluno)',
  `nota` decimal(4,2) DEFAULT NULL COMMENT 'Nota 0 a 10 do professor',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_resumo_aluno_modulo` (`aluno_id`,`modulo_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `aula_id` (`aula_id`),
  KEY `modulo_id` (`modulo_id`),
  KEY `jornada_id` (`jornada_id`),
  CONSTRAINT `jornadas_resumos_alunos_ibfk_jornada` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_resumos_alunos_ibfk_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_tempo_alunos
--
CREATE TABLE IF NOT EXISTS `jornadas_tempo_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `data_inicio` datetime NOT NULL COMMENT 'Quando o aluno começou o primeiro módulo',
  `data_fim` datetime NOT NULL COMMENT 'Quando o aluno finalizou o último módulo',
  `tempo_total_segundos` int unsigned NOT NULL DEFAULT '0' COMMENT 'Diferença em segundos entre data_fim e data_inicio',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_aluno_jornada` (`aluno_id`,`jornada_id`),
  KEY `idx_jornada` (`jornada_id`),
  KEY `idx_aluno` (`aluno_id`),
  CONSTRAINT `jornadas_tempo_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_tempo_alunos_ibfk_2` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_tipos_blocos
--
CREATE TABLE IF NOT EXISTS `jornadas_tipos_blocos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cor` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem_padrao` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- jornadas_tudinha_explicacao_exercicio
--
CREATE TABLE IF NOT EXISTS `jornadas_tudinha_explicacao_exercicio` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `exercicio_modulo_id` int NOT NULL,
  `fonte_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 dos dados da questão e da resposta do aluno',
  `explicacao_html` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_aluno_exercicio_modulo` (`aluno_id`,`exercicio_modulo_id`),
  KEY `idx_jtee_exercicio` (`exercicio_modulo_id`),
  CONSTRAINT `jtee_fk_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jtee_fk_exercicio` FOREIGN KEY (`exercicio_modulo_id`) REFERENCES `jornadas_modulos_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- laudos_alunos
--
CREATE TABLE IF NOT EXISTS `laudos_alunos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `mascara_id` bigint NOT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash_arquivo` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_mime` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `algoritmo_cripto` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AES-256-GCM',
  `enviado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_laudo_mascara` (`mascara_id`),
  CONSTRAINT `fk_laudo_mascara` FOREIGN KEY (`mascara_id`) REFERENCES `mascaras_alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- listas_exercicios
--
CREATE TABLE IF NOT EXISTS `listas_exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `materia` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `nivel_dificuldade` enum('Fácil','Médio','Difícil') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `criado_por` int NOT NULL,
  `tipo_usuario` enum('admin','professor') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `criado_por` (`criado_por`),
  KEY `idx_lista_exercicios_materia` (`materia`),
  KEY `idx_lista_exercicios_serie` (`serie`),
  KEY `idx_lista_exercicios_dificuldade` (`nivel_dificuldade`),
  CONSTRAINT `listas_exercicios_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- listas_exercicios_personalizadas
--
CREATE TABLE IF NOT EXISTS `listas_exercicios_personalizadas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `materia` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `nivel_dificuldade` enum('Fácil','Médio','Difícil') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `questoes_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `criado_por` int NOT NULL,
  `tipo_usuario` enum('admin','professor') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `turma_id` int DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `criado_por` (`criado_por`),
  KEY `turma_id` (`turma_id`),
  CONSTRAINT `listas_exercicios_personalizadas_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listas_exercicios_personalizadas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- listas_personalizadas_exercicios
--
CREATE TABLE IF NOT EXISTS `listas_personalizadas_exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tema` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: Reforma Protestante',
  `materia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade_exercicios` int NOT NULL,
  `niveis_selecionados` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'fácil,médio,difícil ou combinações',
  `status` enum('gerando','concluido','erro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'gerando',
  `mensagem_erro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_listas_exercicios_personalizados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- listas_personalizadas_respostas
--
CREATE TABLE IF NOT EXISTS `listas_personalizadas_respostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `questao_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `resposta` enum('A','B','C','D','E') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `tempo_resposta` int DEFAULT NULL,
  `answered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sessao_id` (`sessao_id`),
  KEY `questao_id` (`questao_id`),
  KEY `aluno_id` (`aluno_id`),
  CONSTRAINT `fk_respostas_exercicios_personalizados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_respostas_exercicios_personalizados_questao` FOREIGN KEY (`questao_id`) REFERENCES `questoes_personalizadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_respostas_exercicios_personalizados_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `listas_personalizadas_sessoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- listas_personalizadas_sessoes
--
CREATE TABLE IF NOT EXISTS `listas_personalizadas_sessoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT NULL,
  `status` enum('em_andamento','finalizado','abandonado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'em_andamento',
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `lista_id` (`lista_id`),
  KEY `started_at` (`started_at`),
  CONSTRAINT `fk_sessoes_exercicios_personalizados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sessoes_exercicios_personalizados_lista` FOREIGN KEY (`lista_id`) REFERENCES `listas_personalizadas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- log_validacao_apps_externos
--
CREATE TABLE IF NOT EXISTS `log_validacao_apps_externos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `app` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'games, educalabs, notes, ou app key',
  `evento` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'validate.fail' COMMENT 'validate.fail, token_expirado, etc',
  `detalhes` json DEFAULT NULL COMMENT 'slug, diagnostic, host, uri, etc',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_app` (`app`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de falhas ao validar token de apps externos (master ver em Apps Externos)';

--
-- logs_auditoria
--
CREATE TABLE IF NOT EXISTS `logs_auditoria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `user_role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_accessed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_role` (`user_role`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- logs_senhas
--
CREATE TABLE IF NOT EXISTS `logs_senhas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `alterado_por` int NOT NULL,
  `motivo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `alterado_por` (`alterado_por`),
  CONSTRAINT `logs_senhas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `logs_senhas_ibfk_2` FOREIGN KEY (`alterado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- logs_uso_llm
--
CREATE TABLE IF NOT EXISTS `logs_uso_llm` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Modelo OpenAI (ex: gpt-4o, gpt-4o-mini)',
  `prompt_tokens` int unsigned NOT NULL DEFAULT '0',
  `completion_tokens` int unsigned NOT NULL DEFAULT '0',
  `total_tokens` int unsigned NOT NULL DEFAULT '0',
  `cost_usd` decimal(12,6) NOT NULL DEFAULT '0.000000',
  `usage_type` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general' COMMENT 'ex: exercicios, chat, correcao_redacao, prova, gerar_tema, chat_completion',
  `source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api' COMMENT 'api = chamada real; backfill = importado',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_usage_type` (`usage_type`),
  KEY `idx_model` (`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- mascaras_alunos
--
CREATE TABLE IF NOT EXISTS `mascaras_alunos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `status` enum('rascunho','ativa','suspensa','encerrada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `tipo_adaptacao` enum('acesso','significativa') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'acesso',
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `base_legal` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_consentimento` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `criado_por` int DEFAULT NULL,
  `aprovado_por` int DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mascara_aluno_status` (`aluno_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- materias
--
CREATE TABLE IF NOT EXISTS `materias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sigla` varchar(10) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `area_conhecimento` enum('linguagens','matematica','ciencias_natureza','ciencias_humanas','ensino_religioso','interdisciplinar','tecnologia','computacao','arte','outra') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'outra',
  `tipo` enum('formacao_geral','lingua_adicional','eletiva','itinerario_formativo','extracurricular','complementar','outra') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'formacao_geral',
  `etapa_infantil` enum('nao_aplica','obrigatoria','oferta') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'nao_aplica',
  `etapa_fund_i` enum('nao_aplica','obrigatoria','oferta') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'nao_aplica',
  `etapa_fund_ii` enum('nao_aplica','obrigatoria','oferta') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'nao_aplica',
  `etapa_medio` enum('nao_aplica','obrigatoria','oferta') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'nao_aplica',
  `descricao` text COLLATE utf8mb3_unicode_ci,
  `cor` varchar(7) COLLATE utf8mb3_unicode_ci DEFAULT '#3B82F6',
  `ordem` int NOT NULL DEFAULT '0',
  `permite_avaliacao` tinyint(1) NOT NULL DEFAULT '1',
  `permite_frequencia` tinyint(1) NOT NULL DEFAULT '1',
  `permite_plano_aula` tinyint(1) NOT NULL DEFAULT '1',
  `permite_diario` tinyint(1) NOT NULL DEFAULT '1',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- matricula
--
CREATE TABLE IF NOT EXISTS `matricula` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `ano_letivo_id` int NOT NULL,
  `data_entrada` date NOT NULL,
  `data_saida` date DEFAULT NULL,
  `status` enum('ativa','transferido','concluido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativa',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resultado_ano` enum('nao_lancado','aprovado','reprovado','parcial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_lancado',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_matricula_aluno_turma_ano` (`aluno_id`,`turma_id`,`ano_letivo_id`),
  KEY `idx_matricula_aluno` (`aluno_id`),
  KEY `idx_matricula_turma` (`turma_id`),
  KEY `idx_matricula_ano_letivo` (`ano_letivo_id`),
  KEY `idx_matricula_status` (`status`),
  CONSTRAINT `fk_matricula_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_matricula_ano_letivo` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_matricula_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_campanha_planos
--
CREATE TABLE IF NOT EXISTS `matricula_campanha_planos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `campanha_id` int unsigned NOT NULL,
  `plano_origem_id` int DEFAULT NULL,
  `serie_id` int DEFAULT NULL,
  `turma_origem_id` int DEFAULT NULL,
  `plano_destino_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_campanha` (`campanha_id`),
  CONSTRAINT `fk_mcp_campanha` FOREIGN KEY (`campanha_id`) REFERENCES `matricula_campanhas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_campanhas
--
CREATE TABLE IF NOT EXISTS `matricula_campanhas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ano_origem_id` int NOT NULL,
  `ano_destino_id` int NOT NULL,
  `inicio` date NOT NULL,
  `fim` date NOT NULL,
  `status` enum('rascunho','aberta','encerrada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `plano_padrao_id` int DEFAULT NULL,
  `reajuste_pct` decimal(6,2) DEFAULT NULL,
  `fila_auto_oferecer` tinyint(1) NOT NULL DEFAULT '1',
  `exige_censo` tinyint(1) NOT NULL DEFAULT '0',
  `criado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mc_status` (`status`),
  KEY `idx_mc_anos` (`ano_origem_id`,`ano_destino_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_checklist_itens
--
CREATE TABLE IF NOT EXISTS `matricula_checklist_itens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tipo_processo` enum('nova','rematricula','transferencia') COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rotulo` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `obrigatorio` tinyint(1) NOT NULL DEFAULT '1',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mci_tipo_codigo` (`tipo_processo`,`codigo`),
  KEY `idx_mci_tipo` (`tipo_processo`,`ativo`,`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_contrato_regras
--
CREATE TABLE IF NOT EXISTS `matricula_contrato_regras` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('matricula','mensalidade','material_didatico','uniforme','taxa','outros') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'matricula',
  `modelo_documento_codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `enviar_zapsign` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_matricula_contrato_regras_tipo` (`tipo`),
  KEY `idx_matricula_contrato_regras_ativo` (`ativo`,`ordem`),
  KEY `idx_matricula_contrato_regras_modelo` (`modelo_documento_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_processos
--
CREATE TABLE IF NOT EXISTS `matricula_processos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('nova','rematricula','transferencia') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nova',
  `status` enum('rascunho','aguardando_contrato','aguardando_assinatura','confirmada','enturmada','abandonada','cancelada','lista_espera') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `aluno_id` int DEFAULT NULL,
  `ano_letivo_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `serie_id` int DEFAULT NULL,
  `campanha_id` int unsigned DEFAULT NULL,
  `fila_posicao` int unsigned DEFAULT NULL,
  `entrou_fila_em` datetime DEFAULT NULL,
  `reserva_ate` datetime DEFAULT NULL,
  `aluno_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `aluno_cpf` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_rg` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_data_nasc` date DEFAULT NULL,
  `aluno_genero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_telefone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_endereco` text COLLATE utf8mb4_unicode_ci,
  `aluno_end_numero` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_end_bairro` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_end_cidade` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_end_uf` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_end_cep` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_escola_anterior` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_nome_mae` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_nome_pai` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_codigo_inep` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_cor_raca` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_nacionalidade` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_end_complemento` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resp_cpf` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_telefone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_parentesco` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_endereco` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `finance_plan_id` int DEFAULT NULL,
  `finance_cobrancas` json DEFAULT NULL,
  `pagamento_status` enum('nao_solicitado','aguardando','pago','dispensado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_solicitado',
  `pagante_modo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT 'um',
  `documento_assinatura_codigo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dados_confirmados_em` datetime DEFAULT NULL,
  `contrato_pdf_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contrato_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contrato_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contrato_assinado_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assinado_em` datetime DEFAULT NULL,
  `assinante_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assinante_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zapsign_doc_token` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zapsign_signer_token` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zapsign_sign_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zapsign_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zapsign_enviado_em` datetime DEFAULT NULL,
  `origem` enum('interno','site','whatsapp','indicacao','evento','rede_social','outros') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interno',
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `expira_em` datetime DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enrollment_contrato_token` (`contrato_token`),
  KEY `idx_enrollment_aluno` (`aluno_id`),
  KEY `idx_enrollment_turma` (`turma_id`),
  KEY `idx_enrollment_ano_letivo` (`ano_letivo_id`),
  KEY `idx_enrollment_status` (`status`),
  KEY `idx_enrollment_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_processos_auditorias
--
CREATE TABLE IF NOT EXISTS `matricula_processos_auditorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enrollment_id` int NOT NULL,
  `status_de` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_para` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `acao` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `usuario_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_enrollment` (`enrollment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_processos_contratos
--
CREATE TABLE IF NOT EXISTS `matricula_processos_contratos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `enrollment_id` int NOT NULL,
  `regra_id` int unsigned DEFAULT NULL,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'matricula',
  `nome` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelo_documento_codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contrato_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contrato_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zapsign_doc_token` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zapsign_signer_token` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zapsign_sign_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zapsign_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zapsign_enviado_em` datetime DEFAULT NULL,
  `assinado_em` datetime DEFAULT NULL,
  `assinante_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pendente','gerado','enviado','assinado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mpc_enrollment_tipo` (`enrollment_id`,`tipo`),
  KEY `idx_mpc_enrollment` (`enrollment_id`),
  KEY `idx_mpc_regra` (`regra_id`),
  KEY `idx_mpc_zapsign` (`zapsign_doc_token`),
  CONSTRAINT `fk_mpc_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `matricula_processos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mpc_regra` FOREIGN KEY (`regra_id`) REFERENCES `matricula_contrato_regras` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_processos_documentos
--
CREATE TABLE IF NOT EXISTS `matricula_processos_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enrollment_id` int NOT NULL,
  `tipo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int unsigned DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpd_processo` (`enrollment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_processos_produtos
--
CREATE TABLE IF NOT EXISTS `matricula_processos_produtos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enrollment_id` int NOT NULL,
  `plan_item_id` int DEFAULT NULL,
  `tipo` enum('mensalidade','matricula','material_didatico','taxa','uniforme','outros') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mensalidade',
  `descricao` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `incluir` tinyint(1) NOT NULL DEFAULT '1',
  `valor_base` decimal(10,2) NOT NULL DEFAULT '0.00',
  `num_parcelas` int unsigned NOT NULL DEFAULT '1',
  `mes_inicio` tinyint unsigned DEFAULT NULL,
  `fornecedor_externo` tinyint(1) NOT NULL DEFAULT '0',
  `nome_instituicao` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo_documento_codigo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finance_contract_id` int unsigned DEFAULT NULL,
  `status` enum('pendente','contratado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `ordem` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpp_processo` (`enrollment_id`),
  KEY `idx_mpp_tipo` (`enrollment_id`,`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_processos_responsaveis
--
CREATE TABLE IF NOT EXISTS `matricula_processos_responsaveis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enrollment_id` int NOT NULL COMMENT 'FK lógica → matricula_processos.id',
  `ordem` tinyint unsigned NOT NULL DEFAULT '1',
  `tipo_vinculo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_pedagogico` tinyint(1) NOT NULL DEFAULT '1',
  `is_financeiro` tinyint(1) NOT NULL DEFAULT '0',
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_documento` enum('cpf','cnpj') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cpf',
  `documento` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rg` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `estado_civil` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profissao` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endereco` text COLLATE utf8mb4_unicode_ci,
  `end_cep` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_numero` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_complemento` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_bairro` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_cidade` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_uf` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentual` decimal(5,2) DEFAULT NULL,
  `finance_contract_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpr_processo` (`enrollment_id`),
  KEY `idx_mpr_ordem` (`enrollment_id`,`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_processos_scores
--
CREATE TABLE IF NOT EXISTS `matricula_processos_scores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `ciclo` int NOT NULL,
  `score` tinyint NOT NULL DEFAULT '0',
  `faixa` enum('verde','amarelo','vermelho') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'verde',
  `freq_n` tinyint DEFAULT NULL,
  `desemp_n` tinyint DEFAULT NULL,
  `inad_n` tinyint DEFAULT NULL,
  `engaj_n` tinyint DEFAULT NULL,
  `tempo_n` tinyint DEFAULT NULL,
  `motivos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `calculado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_score_aluno_ciclo` (`aluno_id`,`ciclo`),
  KEY `idx_score_faixa` (`faixa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matricula_transferencias
--
CREATE TABLE IF NOT EXISTS `matricula_transferencias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `protocolo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direcao` enum('entrada','saida') COLLATE utf8mb4_unicode_ci NOT NULL,
  `aluno_id` int DEFAULT NULL,
  `enrollment_id` int DEFAULT NULL,
  `turma_origem_id` int DEFAULT NULL,
  `escola_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escola_cidade` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escola_uf` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escola_inep` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `data_transferencia` date NOT NULL,
  `docs_entregues_em` datetime DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_protocolo` (`protocolo`),
  KEY `idx_mt_aluno` (`aluno_id`),
  KEY `idx_mt_direcao` (`direcao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matrizes_curriculares
--
CREATE TABLE IF NOT EXISTS `matrizes_curriculares` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `curso_id` int NOT NULL,
  `serie_id` int NOT NULL,
  `modalidade` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `turno` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carga_horaria_anual_prevista` decimal(6,2) DEFAULT NULL,
  `dias_letivos_previstos` int DEFAULT NULL,
  `duracao_padrao_aula_minutos` int NOT NULL DEFAULT '50',
  `base_legal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_matrizes_codigo` (`codigo`),
  KEY `idx_matrizes_curso_serie` (`curso_id`,`serie_id`),
  KEY `idx_matrizes_ativo` (`ativo`),
  KEY `fk_matrizes_serie` (`serie_id`),
  CONSTRAINT `fk_matrizes_curso` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_matrizes_serie` FOREIGN KEY (`serie_id`) REFERENCES `serie` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- matrizes_curriculares_componentes
--
CREATE TABLE IF NOT EXISTS `matrizes_curriculares_componentes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `matriz_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `aulas_semana` smallint unsigned NOT NULL DEFAULT '1',
  `obrigatorio` tinyint(1) NOT NULL DEFAULT '1',
  `ordem_boletim` int NOT NULL DEFAULT '0',
  `ordem_historico` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_matriz_materia` (`matriz_id`,`materia_id`),
  KEY `fk_mcc_materia` (`materia_id`),
  CONSTRAINT `fk_mcc_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mcc_matriz` FOREIGN KEY (`matriz_id`) REFERENCES `matrizes_curriculares` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- migrations_executadas
--
CREATE TABLE IF NOT EXISTS `migrations_executadas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `escola_database_config_id` int NOT NULL,
  `migration_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `executada_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `executado_por` int DEFAULT NULL,
  `status` enum('sucesso','erro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'sucesso',
  `mensagem_erro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_migration_escola` (`escola_database_config_id`,`migration_file`),
  KEY `idx_escola` (`escola_database_config_id`),
  KEY `idx_migration_file` (`migration_file`),
  KEY `idx_executada_em` (`executada_em`),
  CONSTRAINT `migrations_executadas_ibfk_1` FOREIGN KEY (`escola_database_config_id`) REFERENCES `config_escolas_database` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- minicursos
--
CREATE TABLE IF NOT EXISTS `minicursos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `imagem_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagem_caminho` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho do upload',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` smallint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativo_ordem` (`ativo`,`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- minicursos_arquivos
--
CREATE TABLE IF NOT EXISTS `minicursos_arquivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `minicurso_id` int NOT NULL,
  `tipo` enum('upload','link') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` smallint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_minicurso` (`minicurso_id`),
  CONSTRAINT `minicursos_arquivos_ibfk_1` FOREIGN KEY (`minicurso_id`) REFERENCES `minicursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- minicursos_aulas
--
CREATE TABLE IF NOT EXISTS `minicursos_aulas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('video','slides','pdf','link','texto') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url_ou_caminho` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'URL ou caminho; vazio se tipo=texto',
  `link_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Texto do botão para tipo link',
  `conteudo_html` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Conteúdo HTML (tipo texto)',
  `duracao_minutos` int unsigned DEFAULT NULL,
  `ordem` smallint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_modulo` (`modulo_id`),
  CONSTRAINT `minicursos_aulas_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `minicursos_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- minicursos_modulos
--
CREATE TABLE IF NOT EXISTS `minicursos_modulos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `minicurso_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ordem` smallint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_minicurso` (`minicurso_id`),
  CONSTRAINT `minicursos_modulos_ibfk_1` FOREIGN KEY (`minicurso_id`) REFERENCES `minicursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- minicursos_progresso
--
CREATE TABLE IF NOT EXISTS `minicursos_progresso` (
  `aluno_id` int NOT NULL,
  `minicurso_id` int NOT NULL,
  `aulas_vistas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Array de aula_id já visualizadas',
  `concluido_em` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`aluno_id`,`minicurso_id`),
  KEY `idx_minicurso` (`minicurso_id`),
  CONSTRAINT `minicursos_progresso_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `minicursos_progresso_ibfk_2` FOREIGN KEY (`minicurso_id`) REFERENCES `minicursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- mobile_auth_sessions
--
CREATE TABLE IF NOT EXISTS `mobile_auth_sessions` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int NOT NULL,
  `refresh_token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mobile_auth_refresh` (`refresh_token_hash`),
  KEY `idx_mobile_auth_parent_active` (`parent_id`,`revoked_at`,`expires_at`),
  CONSTRAINT `fk_mobile_auth_parent` FOREIGN KEY (`parent_id`) REFERENCES `responsaveis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- mobile_devices
--
CREATE TABLE IF NOT EXISTS `mobile_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int NOT NULL,
  `device_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fcm_token` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'android',
  `app_version` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mobile_devices_device` (`device_id`),
  UNIQUE KEY `uq_mobile_devices_token_hash` (`token_hash`),
  KEY `idx_mobile_devices_parent_enabled` (`parent_id`,`enabled`),
  KEY `idx_mobile_devices_last_seen` (`last_seen_at`),
  CONSTRAINT `fk_mobile_devices_parent` FOREIGN KEY (`parent_id`) REFERENCES `responsaveis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- modulos_apostilas
--
CREATE TABLE IF NOT EXISTS `modulos_apostilas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `turma_id` int NOT NULL,
  `curso_id` int DEFAULT NULL,
  `materia_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `visibilidade` enum('aluno','professor','ambos') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ambos',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_modulos_apostilas_turma` (`turma_id`),
  KEY `idx_modulos_apostilas_visibilidade` (`visibilidade`),
  KEY `idx_modulos_apostilas_created_at` (`created_at`),
  CONSTRAINT `fk_modulos_apostilas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- modulos_apostilas_anexos
--
CREATE TABLE IF NOT EXISTS `modulos_apostilas_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_apostila_id` int NOT NULL,
  `caminho` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `extensao` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` bigint DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_modulos_apostilas_anexos_modulo` (`modulo_apostila_id`),
  CONSTRAINT `fk_modulos_apostilas_anexos_modulo` FOREIGN KEY (`modulo_apostila_id`) REFERENCES `modulos_apostilas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- modulos_apostilas_turmas
--
CREATE TABLE IF NOT EXISTS `modulos_apostilas_turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_apostila_id` int NOT NULL,
  `turma_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modulo_apostila_turma` (`modulo_apostila_id`,`turma_id`),
  KEY `idx_modulos_apostilas_turmas_turma` (`turma_id`),
  CONSTRAINT `fk_modulos_apostilas_turmas_modulo` FOREIGN KEY (`modulo_apostila_id`) REFERENCES `modulos_apostilas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_modulos_apostilas_turmas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- modulos_arquivos
--
CREATE TABLE IF NOT EXISTS `modulos_arquivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `turma_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `professor_id` int DEFAULT NULL,
  `aluno_id` int DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recuperacao` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = aparece em Recuperação no aluno',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `pasta_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_turma` (`turma_id`),
  KEY `idx_materia` (`materia_id`),
  KEY `idx_professor` (`professor_id`),
  KEY `idx_modulos_arquivos_aluno_id` (`aluno_id`),
  KEY `idx_pasta_id` (`pasta_id`),
  KEY `idx_recuperacao` (`recuperacao`),
  CONSTRAINT `modulos_arquivos_ibfk_1` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `modulos_arquivos_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `modulos_arquivos_ibfk_3` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- modulos_arquivos_anexos
--
CREATE TABLE IF NOT EXISTS `modulos_arquivos_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_arquivo_id` int NOT NULL,
  `caminho` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Caminho relativo no servidor (ex: public/uploads/arquivos/xxx.pdf)',
  `nome_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `extensao` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanho` int unsigned DEFAULT '0',
  `ordem` smallint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_modulo_arquivo` (`modulo_arquivo_id`),
  CONSTRAINT `modulos_arquivos_anexos_ibfk_1` FOREIGN KEY (`modulo_arquivo_id`) REFERENCES `modulos_arquivos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- modulos_arquivos_pastas
--
CREATE TABLE IF NOT EXISTS `modulos_arquivos_pastas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int DEFAULT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6366f1',
  `professor_id` int DEFAULT NULL COMMENT 'NULL = pasta criada pelo admin',
  `criado_por_tipo` enum('professor','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'professor',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_professor` (`professor_id`),
  KEY `idx_tipo` (`criado_por_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pastas de organização do módulo de arquivos';

--
-- modulos_arquivos_turmas
--
CREATE TABLE IF NOT EXISTS `modulos_arquivos_turmas` (
  `modulo_arquivo_id` int NOT NULL,
  `turma_id` int NOT NULL,
  PRIMARY KEY (`modulo_arquivo_id`,`turma_id`),
  KEY `idx_turma` (`turma_id`),
  CONSTRAINT `modulos_arquivos_turmas_ibfk_1` FOREIGN KEY (`modulo_arquivo_id`) REFERENCES `modulos_arquivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `modulos_arquivos_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- modulos_arquivos_videos
--
CREATE TABLE IF NOT EXISTS `modulos_arquivos_videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_arquivo_id` int NOT NULL,
  `url` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL do vídeo (YouTube, Vimeo, ou link direto)',
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ma_videos_modulo` (`modulo_arquivo_id`),
  CONSTRAINT `fk_ma_videos_modulo` FOREIGN KEY (`modulo_arquivo_id`) REFERENCES `modulos_arquivos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links de vídeo (YouTube, Vimeo) por publicação de arquivos';

--
-- monitor_acoes_log
--
CREATE TABLE IF NOT EXISTS `monitor_acoes_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `monitor_id` int unsigned NOT NULL,
  `aluno_id` int unsigned DEFAULT NULL,
  `acao` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalhes` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_monitor_acoes_monitor` (`monitor_id`),
  KEY `idx_monitor_acoes_aluno` (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- monitores
--
CREATE TABLE IF NOT EXISTS `monitores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `turmas` json DEFAULT NULL COMMENT 'IDs das turmas que o monitor pode acompanhar',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitores_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- mural_recados
--
CREATE TABLE IF NOT EXISTS `mural_recados` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `autor_tipo` enum('professor','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'professor',
  `autor_id` int unsigned NOT NULL COMMENT 'professor_id ou usuario id (admin)',
  `materia_id` int unsigned DEFAULT NULL,
  `enviar_para_todos` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = todas as turmas',
  `data_publicacao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sai_mural` date NOT NULL COMMENT 'Prazo máximo 30 dias a partir de data_publicacao',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mural_recados_data_sai` (`data_sai_mural`),
  KEY `idx_mural_recados_publicacao` (`data_publicacao`),
  KEY `idx_mural_recados_materia` (`materia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- mural_recados_anexos
--
CREATE TABLE IF NOT EXISTS `mural_recados_anexos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mural_recado_id` int unsigned NOT NULL,
  `arquivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Caminho relativo no servidor',
  `arquivo_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME type',
  `tamanho` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mural_anexos_recado` (`mural_recado_id`),
  CONSTRAINT `fk_mural_anexos_recado` FOREIGN KEY (`mural_recado_id`) REFERENCES `mural_recados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- mural_recados_turmas
--
CREATE TABLE IF NOT EXISTS `mural_recados_turmas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mural_recado_id` int unsigned NOT NULL,
  `turma_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mural_turma` (`mural_recado_id`,`turma_id`),
  KEY `idx_mural_recados_turmas_mural` (`mural_recado_id`),
  KEY `idx_mural_recados_turmas_turma` (`turma_id`),
  CONSTRAINT `fk_mural_turmas_recado` FOREIGN KEY (`mural_recado_id`) REFERENCES `mural_recados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- mural_recados_vistos
--
CREATE TABLE IF NOT EXISTS `mural_recados_vistos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mural_recado_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `visto_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mural_aluno` (`mural_recado_id`,`aluno_id`),
  KEY `idx_mural_vistos_recado` (`mural_recado_id`),
  KEY `idx_mural_vistos_aluno` (`aluno_id`),
  KEY `idx_mural_recados_vistos_aluno_recado` (`aluno_id`,`mural_recado_id`),
  CONSTRAINT `fk_mural_vistos_recado` FOREIGN KEY (`mural_recado_id`) REFERENCES `mural_recados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- notas_semanais_config
--
CREATE TABLE IF NOT EXISTS `notas_semanais_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `semanas_grupo_a` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1,3,5,7',
  `semanas_grupo_b` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2,4,6,8',
  `peso_media_sem` decimal(5,2) NOT NULL DEFAULT '4.00',
  `peso_prova_bim` decimal(5,2) NOT NULL DEFAULT '4.00',
  `peso_enac` decimal(5,2) NOT NULL DEFAULT '1.00',
  `peso_participacao` decimal(5,2) NOT NULL DEFAULT '0.50',
  `peso_trabalho` decimal(5,2) NOT NULL DEFAULT '0.50',
  `regra_recuperacao` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'maior',
  `media_minima` decimal(4,2) NOT NULL DEFAULT '6.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- notas_semanais_materias
--
CREATE TABLE IF NOT EXISTS `notas_semanais_materias` (
  `materia_id` int NOT NULL,
  `grupo` char(1) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'A ou B',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`materia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- notes_tokens
--
CREATE TABLE IF NOT EXISTS `notes_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- noticias
--
CREATE TABLE IF NOT EXISTS `noticias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `imagem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `categoria` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fonte` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_publicacao` datetime DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_noticias_link` (`link`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- notificacoes
--
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo_conteudo` enum('texto','mensagem','video') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'texto',
  `tipos_conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Tipos de conteúdo selecionados (texto,imagem,video)',
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_tamanho` int DEFAULT NULL,
  `video_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL do vídeo (YouTube, Vimeo, etc.)',
  `enviado_por` int NOT NULL,
  `tipo_enviador` enum('admin','professor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `perfil_enviador` enum('dev','diretor','coordenador','professor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prioridade` enum('baixa','normal','alta','urgente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `data_envio` datetime DEFAULT NULL,
  `data_expiracao` datetime DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `is_update` tinyint(1) DEFAULT '0' COMMENT 'Se é uma notificação de atualização do sistema',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `enviado_por` (`enviado_por`),
  KEY `tipo_enviador` (`tipo_enviador`),
  KEY `perfil_enviador` (`perfil_enviador`),
  KEY `prioridade` (`prioridade`),
  KEY `ativo` (`ativo`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- notificacoes_api
--
CREATE TABLE IF NOT EXISTS `notificacoes_api` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mensagem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `imagem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `categoria` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- notificacoes_configuracoes
--
CREATE TABLE IF NOT EXISTS `notificacoes_configuracoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `tipo_usuario` enum('admin','professor','aluno','pai') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `receber_notificacoes` tinyint(1) DEFAULT '1',
  `receber_por_email` tinyint(1) DEFAULT '0',
  `receber_urgentes` tinyint(1) DEFAULT '1',
  `receber_gerais` tinyint(1) DEFAULT '1',
  `receber_turma` tinyint(1) DEFAULT '1',
  `som_notificacao` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_tipo` (`usuario_id`,`tipo_usuario`),
  KEY `tipo_usuario` (`tipo_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- notificacoes_destinatarios
--
CREATE TABLE IF NOT EXISTS `notificacoes_destinatarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notificacao_id` int NOT NULL,
  `tipo_destinatario` enum('todos','usuarios','professores','alunos','pais','turma','todos_alunos','todos_professores','todos_admins','todos_pais') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `destinatario_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `lida` tinyint(1) DEFAULT '0',
  `lida_em` datetime DEFAULT NULL,
  `visualizada_em` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notificacao_id` (`notificacao_id`),
  KEY `tipo_destinatario` (`tipo_destinatario`),
  KEY `destinatario_id` (`destinatario_id`),
  KEY `turma_id` (`turma_id`),
  KEY `lida` (`lida`),
  CONSTRAINT `notificacoes_destinatarios_ibfk_1` FOREIGN KEY (`notificacao_id`) REFERENCES `notificacoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- notificacoes_historico
--
CREATE TABLE IF NOT EXISTS `notificacoes_historico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notificacao_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `tipo_usuario` enum('admin','professor','aluno','pai') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `acao` enum('enviada','visualizada','lida','atualizada','excluida') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalhes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notificacao_id` (`notificacao_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `tipo_usuario` (`tipo_usuario`),
  KEY `acao` (`acao`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `notificacoes_historico_ibfk_1` FOREIGN KEY (`notificacao_id`) REFERENCES `notificacoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- notificacoes_push
--
CREATE TABLE IF NOT EXISTS `notificacoes_push` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensagem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL para redirecionar ao clicar',
  `tipo_destino` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'todos|pais|alunos|professores|turma|usuario',
  `destino_id` int unsigned DEFAULT NULL COMMENT 'ID da turma ou usuario conforme tipo_destino',
  `criado_por` int unsigned NOT NULL COMMENT 'usuario_id do admin',
  `onesignal_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID do envio no OneSignal',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_push_notif_created` (`created_at`),
  KEY `idx_push_notif_tipo` (`tipo_destino`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- notificacoes_push_envios
--
CREATE TABLE IF NOT EXISTS `notificacoes_push_envios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `notificacao_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL COMMENT 'usuarios.id do destinatário',
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'pai|aluno|professor|admin_escola',
  `tracking_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Token para API de tracking (ex: visualizado/clicado)',
  `entregue` tinyint(1) NOT NULL DEFAULT '0',
  `visualizado` tinyint(1) NOT NULL DEFAULT '0',
  `clicado` tinyint(1) NOT NULL DEFAULT '0',
  `entregue_em` timestamp NULL DEFAULT NULL,
  `visualizado_em` timestamp NULL DEFAULT NULL,
  `clicado_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_push_envio_token` (`tracking_token`),
  UNIQUE KEY `uk_push_envio_notif_role_user` (`notificacao_id`,`role`,`user_id`),
  KEY `idx_push_envio_user` (`user_id`),
  KEY `idx_push_envio_notif` (`notificacao_id`),
  CONSTRAINT `notificacoes_push_envios_ibfk_1` FOREIGN KEY (`notificacao_id`) REFERENCES `notificacoes_push` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ocorrencias_categorias
--
CREATE TABLE IF NOT EXISTS `ocorrencias_categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` smallint NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ocorrencias_categorias_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ocorrencias_historico
--
CREATE TABLE IF NOT EXISTS `ocorrencias_historico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ocorrencia_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `acao` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ocorrencias_historico_ocorrencia` (`ocorrencia_id`),
  CONSTRAINT `fk_ocorrencias_historico_ocorrencia` FOREIGN KEY (`ocorrencia_id`) REFERENCES `alunos_ocorrencias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- pacotes_creditos
--
CREATE TABLE IF NOT EXISTS `pacotes_creditos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `creditos` int unsigned NOT NULL,
  `valor_centavos` int unsigned NOT NULL DEFAULT '0',
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex: Pacote 10 créditos',
  `categoria` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'inicio|intermediario|premium',
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `imagem_url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destaque` tinyint(1) NOT NULL DEFAULT '0',
  `ordem` int NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `catalogo_pacote_id` int unsigned DEFAULT NULL COMMENT 'FK lógico ao master',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pacotes_catalogo_pacote_id` (`catalogo_pacote_id`),
  KEY `idx_pacotes_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pacotes de créditos à venda (preço em centavos)';

--
-- partidas_dama
--
CREATE TABLE IF NOT EXISTS `partidas_dama` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `nivel_dificuldade` enum('facil','medio','dificil') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'facil',
  `status` enum('em_andamento','finalizada','abandonada') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'em_andamento',
  `resultado` enum('vitoria_aluno','vitoria_robo','empate') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `tabuleiro` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `vez_jogador` enum('aluno','robo') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'aluno',
  `movimentos` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `pontuacao_aluno` int DEFAULT '0',
  `pontuacao_robo` int DEFAULT '0',
  `data_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno_status` (`aluno_id`,`status`),
  KEY `idx_data_inicio` (`data_inicio`),
  CONSTRAINT `partidas_dama_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- patrimony_assets
--
CREATE TABLE IF NOT EXISTS `patrimony_assets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_patrimonio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(220) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` enum('mobiliario','informatica','projetor','climatizacao','laboratorio','veiculo','instrumento','outro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `numero_serie` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marca` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_aquisicao` date DEFAULT NULL,
  `valor_aquisicao` decimal(12,2) NOT NULL DEFAULT '0.00',
  `nota_fiscal` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fornecedor_id` int DEFAULT NULL,
  `garantia_ate` date DEFAULT NULL,
  `vida_util_meses` int NOT NULL DEFAULT '60',
  `location_id` int DEFAULT NULL,
  `responsavel_nome` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem` enum('proprio','comodato','cedido','doado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proprio',
  `status` enum('ativo','manutencao','emprestado','baixado','nao_localizado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patrimony_assets_numero` (`numero_patrimonio`),
  KEY `idx_patrimony_assets_location` (`location_id`),
  KEY `idx_patrimony_assets_status` (`status`),
  KEY `idx_patrimony_assets_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- patrimony_inventory_checks
--
CREATE TABLE IF NOT EXISTS `patrimony_inventory_checks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_id` int NOT NULL,
  `location_id` int DEFAULT NULL,
  `status_conferencia` enum('ok','local_errado','nao_localizado','sem_plaqueta','avariado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ok',
  `observacoes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conferido_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_patrimony_checks_asset` (`asset_id`),
  KEY `idx_patrimony_checks_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- patrimony_movements
--
CREATE TABLE IF NOT EXISTS `patrimony_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_id` int NOT NULL,
  `tipo` enum('transferencia','emprestimo','manutencao_envio','manutencao_retorno','inventario','baixa') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_origem_id` int DEFAULT NULL,
  `location_destino_id` int DEFAULT NULL,
  `responsavel_origem` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsavel_destino` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `realizado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_patrimony_movements_asset` (`asset_id`),
  KEY `idx_patrimony_movements_tipo` (`tipo`),
  KEY `idx_patrimony_movements_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- plano_curso
--
CREATE TABLE IF NOT EXISTS `plano_curso` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ano_letivo_id` int DEFAULT NULL,
  `serie_id` int DEFAULT NULL,
  `materia_id` int NOT NULL,
  `carga_horaria_prevista` int NOT NULL DEFAULT '0' COMMENT 'horas/aula previstas no ano',
  `avaliacoes_previstas` int NOT NULL DEFAULT '0',
  `conteudo_previsto` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `objetivos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metodologia` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('rascunho','aprovado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plano_curso` (`ano_letivo_id`,`serie_id`,`materia_id`),
  KEY `idx_plano_curso_materia` (`materia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- plano_curso_habilidades
--
CREATE TABLE IF NOT EXISTS `plano_curso_habilidades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plano_curso_id` int NOT NULL,
  `habilidade_id` int NOT NULL,
  `trabalhada` tinyint(1) NOT NULL DEFAULT '0',
  `trabalhada_em` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plano_curso_hab` (`plano_curso_id`,`habilidade_id`),
  KEY `idx_pch_habilidade` (`habilidade_id`),
  CONSTRAINT `fk_pch_plano` FOREIGN KEY (`plano_curso_id`) REFERENCES `plano_curso` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- planos_aula
--
CREATE TABLE IF NOT EXISTS `planos_aula` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `data_aula` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dias_aula` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex: 28 e 31 (TERÇA E SEXTA-FEIRA)',
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ano_disciplina` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex: 1° ANO A / BIOLOGIA',
  `modulo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Módulo do conteúdo',
  `aula_num` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Aula Nº (Ex: 76 a 79)',
  `paginas` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Páginas (Ex: 5 a 18)',
  `conteudo_lista` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Lista de conteúdos com bullets',
  `objetivos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `objetivos_lista` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Lista de objetivos específicos',
  `conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metodologia` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `periodo_tarde_tema` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tema do período da tarde',
  `periodo_tarde_exercicios` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Exercícios do período da tarde',
  `recursos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Recursos utilizados na aula',
  `recursos_lista` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON com recursos selecionados (checkboxes)',
  `aulas_tarde_oficinas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `avaliacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Como será avaliado',
  `avaliacao_apostila` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Apostila da avaliação bimestral',
  `avaliacao_conteudo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Conteúdo da avaliação bimestral',
  `avaliacao_paginas` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Páginas da avaliação bimestral',
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `contexto_llm` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('rascunho','aprovado','rejeitado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `professor_id` (`professor_id`),
  KEY `materia_id` (`materia_id`),
  KEY `turma_id` (`turma_id`),
  KEY `data_aula` (`data_aula`(768)),
  KEY `status` (`status`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `planos_aula_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `planos_aula_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `planos_aula_ibfk_3` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- planos_creditos
--
CREATE TABLE IF NOT EXISTS `planos_creditos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `creditos_mensais` int unsigned NOT NULL,
  `valor_mensal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `destino` enum('aluno','professor','ambos') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ambos',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `catalogo_plano_id` int unsigned DEFAULT NULL COMMENT 'FK lógico ao master',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_planos_catalogo_plano_id` (`catalogo_plano_id`),
  KEY `idx_planos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planos mensais que concedem créditos (assinatura)';

--
-- pontuacao_alunos
--
CREATE TABLE IF NOT EXISTS `pontuacao_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `total_partidas` int DEFAULT '0',
  `partidas_vencidas` int DEFAULT '0',
  `maior_premio` decimal(10,2) DEFAULT '0.00',
  `total_premio` decimal(10,2) DEFAULT '0.00',
  `nivel_atual` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'Iniciante',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno` (`aluno_id`),
  CONSTRAINT `pontuacao_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- presenca_config
--
CREATE TABLE IF NOT EXISTS `presenca_config` (
  `id` tinyint unsigned NOT NULL,
  `tolerancia_atraso_min` smallint unsigned NOT NULL DEFAULT '10',
  `minutos_corte_sem_entrada` smallint unsigned NOT NULL DEFAULT '30',
  `criar_aula_rascunho` tinyint(1) NOT NULL DEFAULT '1',
  `consolidar_boletim` tinyint(1) NOT NULL DEFAULT '0',
  `data_corte` date DEFAULT NULL COMMENT 'Diário só alimenta boletim a partir desta data',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- presenca_eventos
--
CREATE TABLE IF NOT EXISTS `presenca_eventos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `aluno_id` int DEFAULT NULL,
  `tipo` enum('entrada','saida') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ocorrido_em` datetime NOT NULL,
  `origem` enum('integracao','manual_secretaria','facial','importacao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'integracao',
  `integracao_id` int DEFAULT NULL,
  `id_externo` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identificador_bruto` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registrado_por` int DEFAULT NULL,
  `processado_em` datetime DEFAULT NULL,
  `erro_processamento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_presenca_id_externo` (`id_externo`),
  KEY `idx_presenca_aluno_quando` (`aluno_id`,`ocorrido_em`),
  KEY `idx_presenca_quando` (`ocorrido_em`),
  KEY `idx_presenca_origem` (`origem`),
  KEY `fk_presenca_evt_integracao` (`integracao_id`),
  CONSTRAINT `fk_presenca_evt_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_presenca_evt_integracao` FOREIGN KEY (`integracao_id`) REFERENCES `presenca_integracoes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- presenca_identificadores
--
CREATE TABLE IF NOT EXISTS `presenca_identificadores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `tipo` enum('cartao','ra','codigo_aluno','externo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cartao',
  `valor` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_presenca_ident_tipo_valor` (`tipo`,`valor`),
  KEY `idx_presenca_ident_aluno` (`aluno_id`),
  CONSTRAINT `fk_presenca_ident_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- presenca_integracoes
--
CREATE TABLE IF NOT EXISTS `presenca_integracoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provedor` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generico',
  `modo` enum('webhook','polling') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'webhook',
  `mapeamento_identificador` enum('ra','codigo_aluno','aluno_id','cartao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ra',
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ultimo_erro` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_presenca_int_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professor_questoes_api
--
CREATE TABLE IF NOT EXISTS `professor_questoes_api` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `professor_id` bigint unsigned DEFAULT NULL,
  `external_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `materia` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assunto` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nivel_dificuldade` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api_externa',
  `origem_referencia` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enunciado_html` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `alternativas_json` json DEFAULT NULL,
  `gabarito` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolucao_html` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bncc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tags` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `topicos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `source_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prof_questoes_external` (`external_id`),
  KEY `idx_prof_questoes_materia` (`materia`),
  KEY `idx_prof_questoes_tipo` (`tipo`),
  KEY `idx_prof_questoes_professor` (`professor_id`,`updated_at`),
  KEY `idx_prof_questoes_nivel` (`nivel_dificuldade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professor_questoes_montagem_itens
--
CREATE TABLE IF NOT EXISTS `professor_questoes_montagem_itens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `montagem_id` bigint unsigned NOT NULL,
  `questao_id` bigint unsigned NOT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prof_montagem_item` (`montagem_id`,`questao_id`),
  KEY `idx_prof_montagem_itens_montagem` (`montagem_id`),
  KEY `idx_prof_montagem_itens_questao` (`questao_id`),
  CONSTRAINT `fk_prof_montagem_itens_montagem` FOREIGN KEY (`montagem_id`) REFERENCES `professor_questoes_montagens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prof_montagem_itens_questao` FOREIGN KEY (`questao_id`) REFERENCES `professor_questoes_api` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professor_questoes_montagens
--
CREATE TABLE IF NOT EXISTS `professor_questoes_montagens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `professor_id` bigint unsigned NOT NULL,
  `titulo` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prof_montagens_professor` (`professor_id`),
  KEY `idx_prof_montagens_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professores
--
CREATE TABLE IF NOT EXISTS `professores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `senha_hash` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `codigo_prof` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Código do professor - login',
  `materias` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Lista de matérias que leciona',
  `turmas` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ativo` tinyint(1) DEFAULT '1',
  `pagante` tinyint(1) DEFAULT '1' COMMENT 'Indica se a escola paga por este professor',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `pode_tutoria` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_codigo_prof` (`codigo_prof`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professores_documentos
--
CREATE TABLE IF NOT EXISTS `professores_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `tipo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pendente','entregue','dispensado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacao` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entregue_em` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prof_doc` (`professor_id`,`tipo`),
  KEY `idx_prof_doc_prof` (`professor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professores_ia_agentes
--
CREATE TABLE IF NOT EXISTS `professores_ia_agentes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Configurações do agente em formato JSON (escolhas do professor)',
  `instrucoes_sistema` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Instruções personalizadas para o agente',
  `system_prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Prompt do sistema gerado automaticamente a partir de config_json',
  `modelo_ia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'gpt-4o-mini' COMMENT 'Modelo OpenAI a ser usado',
  `temperatura` decimal(3,2) DEFAULT '0.70',
  `max_tokens` int DEFAULT '2000',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_professor` (`professor_id`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professores_ia_conversas
--
CREATE TABLE IF NOT EXISTS `professores_ia_conversas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `agente_id` int unsigned NOT NULL,
  `professor_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Título gerado automaticamente da primeira pergunta',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_professor` (`professor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professores_ia_documentos
--
CREATE TABLE IF NOT EXISTS `professores_ia_documentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `agente_id` int unsigned NOT NULL,
  `professor_id` int NOT NULL,
  `nome_arquivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho_bytes` bigint unsigned DEFAULT NULL,
  `caminho_arquivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `texto_extraido` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Texto completo extraído do documento',
  `status_processamento` enum('pendente','processando','concluido','erro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `erro_processamento` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `total_chunks` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_professor` (`professor_id`),
  KEY `idx_status` (`status_processamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professores_ia_documentos_chunks
--
CREATE TABLE IF NOT EXISTS `professores_ia_documentos_chunks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `documento_id` int unsigned NOT NULL,
  `agente_id` int unsigned NOT NULL,
  `chunk_index` int NOT NULL COMMENT 'Índice sequencial do chunk no documento',
  `texto` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokens` int DEFAULT '0' COMMENT 'Número aproximado de tokens',
  `embedding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Vetor de embedding (1536 dimensões para text-embedding-3-small)',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Metadados adicionais (página, seção, etc)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_documento` (`documento_id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_chunk_index` (`chunk_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professores_ia_mensagens
--
CREATE TABLE IF NOT EXISTS `professores_ia_mensagens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conversa_id` int unsigned NOT NULL,
  `role` enum('user','assistant') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `chunks_usados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'IDs dos chunks usados para gerar a resposta',
  `tokens_usados` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversa` (`conversa_id`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- professores_slides
--
CREATE TABLE IF NOT EXISTS `professores_slides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Título do slide (extraído do conteúdo ou gerado)',
  `conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Conteúdo original usado para gerar o slide',
  `url_gamma` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL da apresentação no Gamma',
  `generation_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID da geração no Gamma',
  `numero_slides` int DEFAULT '8' COMMENT 'Número de slides gerados',
  `tema` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tema usado na geração',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_professor_id` (`professor_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_professor_slides_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Slides gerados pelos professores';

--
-- provas
--
CREATE TABLE IF NOT EXISTS `provas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `turma_id` int DEFAULT NULL COMMENT 'NULL = múltiplas escolas/turmas',
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL,
  `tempo_limite` int DEFAULT NULL COMMENT 'Tempo em minutos, NULL = sem limite',
  `valor_total` decimal(10,2) DEFAULT '100.00',
  `mostrar_resultado` tinyint(1) DEFAULT '1',
  `permite_correcao` tinyint(1) DEFAULT '0',
  `liberar_resultado` enum('imediatamente','apos_todos','nao_liberar') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'imediatamente',
  `ativo` tinyint(1) DEFAULT '1',
  `liberada` tinyint(1) DEFAULT '0' COMMENT '0 = bloqueada, 1 = liberada para alunos',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_reprovacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `coordenador_id` int DEFAULT NULL,
  `data_reprovacao` datetime DEFAULT NULL,
  `data_envio` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `observacao_coordenacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `observacao_coordenacao_data` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `professor_id` (`professor_id`),
  KEY `materia_id` (`materia_id`),
  KEY `turma_id` (`turma_id`),
  KEY `data_inicio` (`data_inicio`),
  KEY `data_fim` (`data_fim`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_status` (`status`),
  CONSTRAINT `provas_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_ibfk_3` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_alternativas
--
CREATE TABLE IF NOT EXISTS `provas_alternativas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `questao_id` int NOT NULL,
  `texto` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `correta` tinyint(1) DEFAULT '0',
  `ordem` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `questao_id` (`questao_id`),
  KEY `ordem` (`ordem`),
  CONSTRAINT `provas_alternativas_ibfk_1` FOREIGN KEY (`questao_id`) REFERENCES `provas_questoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_blocos
--
CREATE TABLE IF NOT EXISTS `provas_blocos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome do bloco (ex: "Prova Bimestral 1º Bimestre")',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'aguardando',
  `conclusao_manual` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = concluído manualmente pela coordenação; não reabrir por prazo',
  `prazo_entrega_professor` datetime DEFAULT NULL COMMENT 'Prazo para professores enviarem suas provas',
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Descrição do bloco',
  `ano_letivo` smallint unsigned DEFAULT NULL COMMENT 'Ano letivo do evento',
  `bimestre` tinyint unsigned DEFAULT NULL COMMENT 'Bimestre 1 a 4',
  `semana` tinyint unsigned DEFAULT NULL COMMENT 'Semana do bimestre no quadro (S1 a S8)',
  `tipo_avaliacao_id` int unsigned DEFAULT NULL,
  `visivel_no_portal_aluno` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=aluno vê no portal; 0=só coordenação/professor',
  `data_prova` date NOT NULL COMMENT 'Data da prova',
  `hora_inicio` time NOT NULL COMMENT 'Horário de início',
  `hora_fim` time NOT NULL COMMENT 'Horário de término',
  `criado_por` int NOT NULL COMMENT 'ID do usuário que criou (admin/coordenador/diretor)',
  `professor_id` int DEFAULT NULL COMMENT 'Professor responsável pelo evento',
  `materia_id` int DEFAULT NULL COMMENT 'Matéria do evento',
  `tipo_prova` enum('original','substitutiva') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'original' COMMENT 'Tipo de prova',
  `formato_evento` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online_questoes' COMMENT 'online_questoes=prova com questões; lancamento_nota=professor lança nota por aluno/turma',
  `configuracao_nota` enum('professor_por_questao','coordenacao_calcula') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'professor_por_questao' COMMENT 'Quem define a nota',
  `nota_unica_todas_materias` tinyint(1) NOT NULL DEFAULT '0',
  `liberar_gabarito` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'imediatamente' COMMENT 'Quando liberar gabarito: imediatamente ou datetime',
  `turma_id` int DEFAULT NULL COMMENT 'NULL = múltiplas turmas, ou turma específica',
  `bloco_modelo_id` int unsigned DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `liberado` tinyint(1) DEFAULT '0' COMMENT '0 = não liberado, 1 = liberado para alunos',
  `gabarito_liberado` tinyint(1) DEFAULT '0' COMMENT '0 = gabarito bloqueado para alunos, 1 = gabarito liberado pela coordenação',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `criado_por` (`criado_por`),
  KEY `turma_id` (`turma_id`),
  KEY `data_prova` (`data_prova`),
  KEY `idx_liberado` (`liberado`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `professor_id` (`professor_id`),
  KEY `materia_id` (`materia_id`),
  KEY `idx_provas_blocos_status` (`status`),
  KEY `idx_provas_blocos_deleted_by` (`deleted_by`),
  KEY `idx_provas_blocos_bloco_modelo_id` (`bloco_modelo_id`),
  KEY `fk_provas_blocos_tipo_avaliacao` (`tipo_avaliacao_id`),
  CONSTRAINT `fk_provas_blocos_tipo_avaliacao` FOREIGN KEY (`tipo_avaliacao_id`) REFERENCES `provas_tipos_avaliacao` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `provas_blocos_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `provas_blocos_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `provas_blocos_ibfk_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `provas_blocos_ibfk_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_blocos_modelos
--
CREATE TABLE IF NOT EXISTS `provas_blocos_modelos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome do modelo (ex: Bloco A, Bloco Simulado ENEM)',
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Descrição opcional do modelo',
  `criado_por` int NOT NULL COMMENT 'ID do usuário que criou (coordenação)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `criado_por` (`criado_por`),
  KEY `deleted_at` (`deleted_at`),
  KEY `idx_blocos_modelo_criado_por` (`criado_por`),
  CONSTRAINT `provas_blocos_modelos_ibfk_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_blocos_modelos_professores
--
CREATE TABLE IF NOT EXISTS `provas_blocos_modelos_professores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modelo_id` int NOT NULL COMMENT 'ID do bloco modelo',
  `professor_id` int NOT NULL COMMENT 'ID do professor',
  `materia_id` int NOT NULL COMMENT 'ID da matéria',
  `numero_questoes` int DEFAULT '0' COMMENT 'Número de questões solicitadas',
  `ordem` int DEFAULT '0' COMMENT 'Ordem de exibição',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modelo_professor_materia` (`modelo_id`,`professor_id`,`materia_id`),
  KEY `modelo_id` (`modelo_id`),
  KEY `professor_id` (`professor_id`),
  KEY `materia_id` (`materia_id`),
  KEY `idx_blocos_modelo_professores_modelo` (`modelo_id`),
  CONSTRAINT `provas_blocos_modelos_professores_ibfk_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_blocos_modelos_professores_ibfk_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `provas_blocos_modelos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_blocos_modelos_professores_ibfk_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_blocos_notas_lancadas
--
CREATE TABLE IF NOT EXISTS `provas_blocos_notas_lancadas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bloco_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `nota` decimal(6,2) DEFAULT NULL,
  `observacao` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bloco_prof_mat_tur_aluno` (`bloco_id`,`professor_id`,`materia_id`,`turma_id`,`aluno_id`),
  KEY `idx_bloco_prof_mat` (`bloco_id`,`professor_id`,`materia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_blocos_professores
--
CREATE TABLE IF NOT EXISTS `provas_blocos_professores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bloco_id` int NOT NULL COMMENT 'ID do bloco de provas',
  `professor_id` int NOT NULL COMMENT 'ID do professor',
  `materia_id` int NOT NULL COMMENT 'ID da matéria do professor neste bloco',
  `quantidade_questoes` int unsigned NOT NULL DEFAULT '5' COMMENT 'Número de questões que o professor deve criar para este bloco/matéria',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bloco_professor_materia` (`bloco_id`,`professor_id`,`materia_id`),
  KEY `bloco_id` (`bloco_id`),
  KEY `professor_id` (`professor_id`),
  KEY `materia_id` (`materia_id`),
  CONSTRAINT `provas_blocos_professores_ibfk_bloco` FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_blocos_professores_ibfk_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_blocos_professores_ibfk_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_blocos_professores_turmas
--
CREATE TABLE IF NOT EXISTS `provas_blocos_professores_turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bloco_professor_id` int NOT NULL COMMENT 'ID do relacionamento bloco-professor',
  `turma_id` int NOT NULL COMMENT 'ID da turma',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bloco_professor_turma` (`bloco_professor_id`,`turma_id`),
  KEY `bloco_professor_id` (`bloco_professor_id`),
  KEY `turma_id` (`turma_id`),
  CONSTRAINT `provas_blocos_professores_turmas_ibfk_bloco_professor` FOREIGN KEY (`bloco_professor_id`) REFERENCES `provas_blocos_professores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_blocos_professores_turmas_ibfk_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_blocos_turmas
--
CREATE TABLE IF NOT EXISTS `provas_blocos_turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bloco_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bloco_turma` (`bloco_id`,`turma_id`),
  KEY `bloco_id` (`bloco_id`),
  KEY `turma_id` (`turma_id`),
  CONSTRAINT `provas_blocos_turmas_ibfk_1` FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_blocos_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_blocos_vinculo
--
CREATE TABLE IF NOT EXISTS `provas_blocos_vinculo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bloco_id` int NOT NULL,
  `prova_id` int NOT NULL,
  `ordem` int DEFAULT '0' COMMENT 'Ordem de exibição no bloco',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bloco_prova` (`bloco_id`,`prova_id`),
  KEY `bloco_id` (`bloco_id`),
  KEY `prova_id` (`prova_id`),
  KEY `ordem` (`ordem`),
  CONSTRAINT `provas_blocos_vinculo_ibfk_1` FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_blocos_vinculo_ibfk_2` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_final
--
CREATE TABLE IF NOT EXISTS `provas_final` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bloco_id` int NOT NULL COMMENT 'ID do bloco de provas',
  `prova_id` int NOT NULL COMMENT 'ID da prova unificada (referência à tabela provas)',
  `data_prova` date NOT NULL COMMENT 'Data da prova final',
  `horario_prova` time NOT NULL COMMENT 'Horário da prova final',
  `publicada` tinyint(1) DEFAULT '0' COMMENT 'Se a prova foi publicada para alunos',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bloco_id` (`bloco_id`),
  KEY `prova_id` (`prova_id`),
  CONSTRAINT `provas_final_ibfk_bloco` FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_final_ibfk_prova` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_log_eventos
--
CREATE TABLE IF NOT EXISTS `provas_log_eventos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo_evento` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'erro_sessao, erro_salvar_resposta, erro_finalizar, saida_modo_seguro, tentativa_sair_tela_cheia, tentativa_atualizar_pagina, tentativa_voltar_navegador, outro',
  `aluno_id` int DEFAULT NULL COMMENT 'NULL quando a sessão já caiu antes de identificar o aluno',
  `prova_id` int DEFAULT NULL,
  `bloco_id` int DEFAULT NULL,
  `detalhe` text COLLATE utf8mb4_unicode_ci COMMENT 'Mensagem/contexto técnico do evento',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_provas_log_eventos_created_at` (`created_at`),
  KEY `idx_provas_log_eventos_tipo` (`tipo_evento`),
  KEY `idx_provas_log_eventos_aluno` (`aluno_id`),
  KEY `idx_provas_log_eventos_prova` (`prova_id`),
  KEY `idx_provas_log_eventos_bloco` (`bloco_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_professores
--
CREATE TABLE IF NOT EXISTS `provas_professores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bloco_id` int NOT NULL COMMENT 'ID do bloco de provas',
  `professor_id` int NOT NULL COMMENT 'ID do professor',
  `materia_id` int NOT NULL COMMENT 'ID da matéria',
  `numero_questoes` int DEFAULT '0' COMMENT 'Número de questões solicitadas',
  `status` enum('em_andamento','enviada','nao_enviada','aprovada','reprovada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'em_andamento',
  `data_envio` datetime DEFAULT NULL COMMENT 'Data em que o professor enviou a prova',
  `travada` tinyint(1) DEFAULT '0' COMMENT 'Se a prova está travada (não pode ser editada)',
  `prova_id` int DEFAULT NULL COMMENT 'ID da prova criada pelo professor (referência à tabela provas)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bloco_professor_materia` (`bloco_id`,`professor_id`,`materia_id`),
  KEY `bloco_id` (`bloco_id`),
  KEY `professor_id` (`professor_id`),
  KEY `materia_id` (`materia_id`),
  KEY `prova_id` (`prova_id`),
  KEY `status` (`status`),
  KEY `idx_provas_professores_status` (`status`),
  CONSTRAINT `provas_professores_ibfk_bloco` FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_professores_ibfk_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_professores_ibfk_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_professores_ibfk_prova` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_questoes
--
CREATE TABLE IF NOT EXISTS `provas_questoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `enunciado` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `imagem_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL da imagem do enunciado',
  `tipo` enum('multipla_escolha','verdadeiro_falso','dissertativa') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'multipla_escolha',
  `valor` decimal(10,2) DEFAULT '1.00',
  `invalidada` tinyint(1) NOT NULL DEFAULT '0',
  `observacao_invalidacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `invalidada_por` int DEFAULT NULL,
  `invalidada_em` datetime DEFAULT NULL,
  `nivel_dificuldade` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int DEFAULT '0',
  `explicacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `prova_id` (`prova_id`),
  KEY `ordem` (`ordem`),
  KEY `idx_provas_questoes_invalidada` (`invalidada`),
  CONSTRAINT `provas_questoes_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_realizacoes
--
CREATE TABLE IF NOT EXISTS `provas_realizacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `iniciado_em` datetime NOT NULL,
  `finalizado_em` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT NULL COMMENT 'Tempo em minutos',
  `nota` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem_questoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON com ordem das questões sorteadas',
  `continuar_sem_tempo` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = liberado pelo admin para continuar sem limite de tempo',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `marcacao_final_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prova_aluno` (`prova_id`,`aluno_id`),
  KEY `prova_id` (`prova_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `status` (`status`),
  KEY `idx_provas_realizacoes_aluno_status` (`aluno_id`,`status`,`prova_id`),
  CONSTRAINT `provas_realizacoes_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_realizacoes_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_respostas
--
CREATE TABLE IF NOT EXISTS `provas_respostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `questao_id` int NOT NULL,
  `alternativa_id` int DEFAULT NULL COMMENT 'Para múltipla escolha',
  `resposta_texto` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Para dissertativa',
  `correta` tinyint(1) DEFAULT NULL COMMENT 'NULL = não corrigida',
  `pontuacao` decimal(10,2) DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prova_aluno_questao` (`prova_id`,`aluno_id`,`questao_id`),
  KEY `prova_id` (`prova_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `questao_id` (`questao_id`),
  KEY `provas_respostas_ibfk_4` (`alternativa_id`),
  CONSTRAINT `provas_respostas_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_respostas_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_respostas_ibfk_3` FOREIGN KEY (`questao_id`) REFERENCES `provas_questoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_respostas_ibfk_4` FOREIGN KEY (`alternativa_id`) REFERENCES `provas_alternativas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_respostas_log
--
CREATE TABLE IF NOT EXISTS `provas_respostas_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `prova_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `questao_id` int unsigned NOT NULL,
  `alternativa_id` int unsigned DEFAULT NULL,
  `resposta_texto` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tipo_acao` enum('marcou','alterou') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prova_aluno_created` (`prova_id`,`aluno_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_tipos_avaliacao
--
CREATE TABLE IF NOT EXISTS `provas_tipos_avaliacao` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `chave_quadro` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Papel no quadro de notas: semanal, prova_bim, enac, participacao, trabalho, recuperacao',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_provas_tipos_avaliacao_nome` (`nome`),
  KEY `idx_provas_tipos_avaliacao_ativo_ordem` (`ativo`,`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_turmas
--
CREATE TABLE IF NOT EXISTS `provas_turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prova_turma` (`prova_id`,`turma_id`),
  KEY `prova_id` (`prova_id`),
  KEY `turma_id` (`turma_id`),
  KEY `idx_provas_turmas_prova_id` (`prova_id`),
  KEY `idx_provas_turmas_turma_prova` (`turma_id`,`prova_id`),
  CONSTRAINT `provas_turmas_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- provas_validacoes_log
--
CREATE TABLE IF NOT EXISTS `provas_validacoes_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `bloco_id` int DEFAULT NULL,
  `nota` decimal(10,2) DEFAULT NULL,
  `validado_por_id` int DEFAULT NULL,
  `validado_por_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validado_por_tipo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prova_aluno` (`prova_id`,`aluno_id`),
  KEY `idx_bloco` (`bloco_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- questoes
--
CREATE TABLE IF NOT EXISTS `questoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lista_id` int NOT NULL,
  `pergunta` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_b` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_c` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_d` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_e` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `resposta_correta` enum('A','B','C','D','E') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `explicacao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `tempo_estimado` int DEFAULT '60',
  `ordem` int NOT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_questoes_lista` (`lista_id`),
  CONSTRAINT `questoes_ibfk_1` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- questoes_personalizadas
--
CREATE TABLE IF NOT EXISTS `questoes_personalizadas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lista_id` int NOT NULL,
  `pergunta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativa_a` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativa_b` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativa_c` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativa_d` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativa_e` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `resposta_correta` enum('A','B','C','D','E') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `explicacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel_dificuldade` enum('Fácil','Médio','Difícil') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int DEFAULT '1',
  `gerado_ia` tinyint(1) DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lista_id` (`lista_id`),
  KEY `nivel_dificuldade` (`nivel_dificuldade`),
  CONSTRAINT `fk_questoes_personalizadas_lista` FOREIGN KEY (`lista_id`) REFERENCES `listas_personalizadas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacao_livre_correcoes
--
CREATE TABLE IF NOT EXISTS `redacao_livre_correcoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `envio_id` int unsigned NOT NULL,
  `grades_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Notas por competência (IA)',
  `teacher_grades_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Notas do professor por competência',
  `feedback_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `suggestions_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `total_score` decimal(6,2) DEFAULT NULL,
  `ai_total_score` decimal(6,2) DEFAULT NULL,
  `teacher_total_score` decimal(6,2) DEFAULT NULL,
  `use_average` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = média entre IA e professor',
  `corrected_by_teacher_id` int unsigned DEFAULT NULL,
  `teacher_adjusted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `envio_id` (`envio_id`),
  KEY `envio_id_2` (`envio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacao_livre_envios
--
CREATE TABLE IF NOT EXISTS `redacao_livre_envios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `student_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nome do aluno (pode vir do arquivo ou digitado)',
  `student_id` int unsigned DEFAULT NULL COMMENT 'Aluno vinculado (opcional)',
  `turma_id` int unsigned DEFAULT NULL COMMENT 'Sala/turma vinculada (opcional)',
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_image_path` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho ou key do arquivo (imagem/PDF)',
  `content_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Texto transcrito ou digitado',
  `ocr_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Texto extraído por OCR se houver',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `student_id` (`student_id`),
  KEY `turma_id` (`turma_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes
--
CREATE TABLE IF NOT EXISTS `redacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `tema_id` int DEFAULT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `conteudo` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('padrao','livre','ia_gerado','transcricao') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'padrao',
  `eh_rascunho` tinyint(1) DEFAULT '0',
  `tema_gerado` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `imagem_path` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `texto_transcrito` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `competencia_1_nota` int DEFAULT NULL,
  `competencia_1_explicacao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `competencia_2_nota` int DEFAULT NULL,
  `competencia_2_explicacao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `competencia_3_nota` int DEFAULT NULL,
  `competencia_3_explicacao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `competencia_4_nota` int DEFAULT NULL,
  `competencia_4_explicacao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `competencia_5_nota` int DEFAULT NULL,
  `competencia_5_explicacao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `comentarios_gerais` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `sugestoes_melhoria` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `nota_final` int DEFAULT NULL COMMENT 'Nota de 0 a 1000',
  `competencia_1` int DEFAULT NULL COMMENT 'Nota competência 1',
  `competencia_2` int DEFAULT NULL COMMENT 'Nota competência 2',
  `competencia_3` int DEFAULT NULL COMMENT 'Nota competência 3',
  `competencia_4` int DEFAULT NULL COMMENT 'Nota competência 4',
  `competencia_5` int DEFAULT NULL COMMENT 'Nota competência 5',
  `feedback_ia` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `corrigida_em` datetime DEFAULT NULL,
  `tema` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tema_texto` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `tempo_escrita` int DEFAULT '0' COMMENT 'Tempo de escrita em segundos',
  `texto` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `imagem_url` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Caso seja foto enviada',
  `correcao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Correção gerada pela IA',
  `nota` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `oculto` tinyint(1) DEFAULT '0',
  `jornada_id` int DEFAULT NULL COMMENT 'ID da jornada (se for redação da jornada)',
  `correcao_professor` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Correção completa do professor',
  `competencia_1_professor` int DEFAULT NULL COMMENT 'Nota competência 1 do professor',
  `competencia_1_explicacao_professor` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Explicação competência 1 do professor',
  `competencia_2_professor` int DEFAULT NULL COMMENT 'Nota competência 2 do professor',
  `competencia_2_explicacao_professor` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Explicação competência 2 do professor',
  `competencia_3_professor` int DEFAULT NULL COMMENT 'Nota competência 3 do professor',
  `competencia_3_explicacao_professor` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Explicação competência 3 do professor',
  `competencia_4_professor` int DEFAULT NULL COMMENT 'Nota competência 4 do professor',
  `competencia_4_explicacao_professor` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Explicação competência 4 do professor',
  `competencia_5_professor` int DEFAULT NULL COMMENT 'Nota competência 5 do professor',
  `competencia_5_explicacao_professor` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Explicação competência 5 do professor',
  `nota_final_professor` int DEFAULT NULL COMMENT 'Nota final do professor',
  `comentarios_gerais_professor` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Comentários gerais do professor',
  `sugestoes_melhoria_professor` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Sugestões de melhoria do professor',
  `permitir_refazer` tinyint(1) DEFAULT '0' COMMENT 'Indica se o professor permite que o aluno refaça a redação (1 = permitido, 0 = não permitido)',
  `corrigida_por_professor` int DEFAULT NULL COMMENT 'ID do professor que corrigiu',
  `corrigida_em_professor` datetime DEFAULT NULL COMMENT 'Data da correção do professor',
  `usar_correcao_professor` tinyint(1) DEFAULT '0' COMMENT 'Usar correção do professor ao invés da IA',
  `retornada_para_reescrever` tinyint(1) DEFAULT '0' COMMENT 'Indica se foi retornada para reescrever',
  `observacoes_professor` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT 'Observações do professor para o aluno',
  `usar_media_notas` tinyint(1) DEFAULT '0' COMMENT 'Usar média entre nota do professor e IA',
  `nota_media` decimal(5,2) DEFAULT NULL COMMENT 'Média calculada entre nota do professor e IA',
  `nota_final_utilizada` decimal(5,2) DEFAULT NULL COMMENT 'Nota final que será utilizada (professor, IA ou média)',
  `mostrar_correcao_ia_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra a correção da IA para o aluno. Se 0, mostra apenas a correção do professor.',
  `mostrar_competencia_1_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 1 para o aluno',
  `mostrar_competencia_2_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 2 para o aluno',
  `mostrar_competencia_3_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 3 para o aluno',
  `mostrar_competencia_4_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 4 para o aluno',
  `mostrar_competencia_5_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 5 para o aluno',
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_tema` (`tema`),
  KEY `idx_nota` (`nota`),
  KEY `idx_created_at` (`created_at`),
  KEY `tema_id` (`tema_id`),
  KEY `idx_rascunho` (`eh_rascunho`,`aluno_id`),
  KEY `jornada_id` (`jornada_id`),
  CONSTRAINT `redacoes_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_ibfk_2` FOREIGN KEY (`tema_id`) REFERENCES `redacoes_temas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_ibfk_jornada` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_orientadas_correcoes
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_correcoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `prompt_id` int DEFAULT NULL,
  `raw_response_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `grades_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Scores per criterion',
  `feedback_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `suggestions_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Sugestões de melhoria (IA ou editado pelo professor)',
  `total_score` decimal(6,2) DEFAULT NULL,
  `ai_total_score` decimal(6,2) DEFAULT NULL COMMENT 'Soma das notas IA por critério',
  `teacher_grades_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Notas e feedback por critério (professor)',
  `teacher_total_score` decimal(6,2) DEFAULT NULL COMMENT 'Soma das notas do professor',
  `use_average` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = exibir média (IA + professor)',
  `corrected_by_teacher_id` int DEFAULT NULL,
  `teacher_adjusted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `teacher_feedback_audio_key` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `teacher_annotations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_annotations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `annotated_image_key` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_essay_corrections_submission` (`submission_id`),
  KEY `idx_essay_corrections_submission` (`submission_id`),
  KEY `prompt_id` (`prompt_id`),
  KEY `corrected_by_teacher_id` (`corrected_by_teacher_id`),
  CONSTRAINT `redacoes_orientadas_correcoes_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `redacoes_orientadas_entregas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_orientadas_correcoes_ibfk_2` FOREIGN KEY (`prompt_id`) REFERENCES `redacoes_orientadas_prompts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `redacoes_orientadas_correcoes_ibfk_3` FOREIGN KEY (`corrected_by_teacher_id`) REFERENCES `professores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_orientadas_correcoes_logs
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_correcoes_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `correction_id` int NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_essay_correction_logs_correction` (`correction_id`),
  CONSTRAINT `redacoes_orientadas_correcoes_logs_ibfk_1` FOREIGN KEY (`correction_id`) REFERENCES `redacoes_orientadas_correcoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_orientadas_criterios
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_criterios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `text_type_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `max_score` decimal(5,2) NOT NULL DEFAULT '200.00',
  `order_position` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_essay_criteria_type_slug` (`text_type_id`,`slug`),
  KEY `idx_essay_criteria_text_type` (`text_type_id`),
  CONSTRAINT `redacoes_orientadas_criterios_ibfk_1` FOREIGN KEY (`text_type_id`) REFERENCES `redacoes_orientadas_tipos_texto` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_orientadas_entregas
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_entregas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proposal_id` int NOT NULL,
  `student_id` int NOT NULL,
  `content_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `content_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ocr_text_structure_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ocr_layout_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_essay_submissions_proposal` (`proposal_id`),
  KEY `idx_essay_submissions_student` (`student_id`),
  KEY `idx_essay_submissions_status` (`status`),
  CONSTRAINT `redacoes_orientadas_entregas_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `redacoes_orientadas_propostas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_orientadas_entregas_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_orientadas_prompts
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_prompts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `board_id` int NOT NULL,
  `text_type_id` int NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  `prompt_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_essay_prompts_board_type` (`board_id`,`text_type_id`),
  KEY `idx_essay_prompts_active` (`board_id`,`text_type_id`,`is_active`),
  KEY `text_type_id` (`text_type_id`),
  CONSTRAINT `redacoes_orientadas_prompts_ibfk_1` FOREIGN KEY (`board_id`) REFERENCES `redacoes_orientadas_quadros` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_orientadas_prompts_ibfk_2` FOREIGN KEY (`text_type_id`) REFERENCES `redacoes_orientadas_tipos_texto` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_orientadas_propostas
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_propostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `board_id` int NOT NULL,
  `text_type_id` int NOT NULL,
  `theme_mode` enum('configurar','arquivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'configurar' COMMENT 'Como o tema foi definido',
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `contexto` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Contexto/descrição da redação (pode ser gerado por IA)',
  `repertoire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tema_pronto_file` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL ou path do PDF/imagem quando theme_mode=arquivo',
  `images_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'URLs or paths of attached images',
  `show_title_field` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=exibir campo título para o aluno',
  `submission_mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'texto',
  `starts_at` datetime DEFAULT NULL COMMENT 'Início do período de realização',
  `ends_at` datetime DEFAULT NULL COMMENT 'Fim do período de realização',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_essay_proposals_teacher` (`teacher_id`),
  KEY `idx_essay_proposals_board_type` (`board_id`,`text_type_id`),
  KEY `idx_essay_proposals_status` (`status`),
  KEY `text_type_id` (`text_type_id`),
  CONSTRAINT `redacoes_orientadas_propostas_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_orientadas_propostas_ibfk_2` FOREIGN KEY (`board_id`) REFERENCES `redacoes_orientadas_quadros` (`id`),
  CONSTRAINT `redacoes_orientadas_propostas_ibfk_3` FOREIGN KEY (`text_type_id`) REFERENCES `redacoes_orientadas_tipos_texto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_orientadas_propostas_alunos
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_propostas_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proposal_id` int NOT NULL,
  `student_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_essay_proposal_students` (`proposal_id`,`student_id`),
  KEY `idx_essay_proposal_students_proposal` (`proposal_id`),
  KEY `idx_essay_proposal_students_student` (`student_id`),
  CONSTRAINT `redacoes_orientadas_propostas_alunos_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `redacoes_orientadas_propostas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_orientadas_propostas_alunos_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_orientadas_propostas_professores
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_propostas_professores` (
  `proposal_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `granted_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`proposal_id`,`professor_id`),
  KEY `idx_professor_id` (`professor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- redacoes_orientadas_propostas_turmas
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_propostas_turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proposal_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_essay_proposal_turmas` (`proposal_id`,`turma_id`),
  KEY `idx_essay_proposal_turmas_proposal` (`proposal_id`),
  KEY `idx_essay_proposal_turmas_turma` (`turma_id`),
  CONSTRAINT `redacoes_orientadas_propostas_turmas_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `redacoes_orientadas_propostas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_orientadas_propostas_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_orientadas_quadros
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_quadros` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_essay_boards_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_orientadas_tipos_texto
--
CREATE TABLE IF NOT EXISTS `redacoes_orientadas_tipos_texto` (
  `id` int NOT NULL AUTO_INCREMENT,
  `board_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_essay_text_types_board_slug` (`board_id`,`slug`),
  KEY `idx_essay_text_types_board` (`board_id`),
  CONSTRAINT `redacoes_orientadas_tipos_texto_ibfk_1` FOREIGN KEY (`board_id`) REFERENCES `redacoes_orientadas_quadros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- redacoes_temas
--
CREATE TABLE IF NOT EXISTS `redacoes_temas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('Temas Autorais','Temas de Vestibulares','Redações Pré-existentes') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `instrucoes` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- redefinicoes_senha
--
CREATE TABLE IF NOT EXISTS `redefinicoes_senha` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- regras_academicas
--
CREATE TABLE IF NOT EXISTS `regras_academicas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ano_letivo` smallint unsigned DEFAULT NULL,
  `curso_id` int DEFAULT NULL,
  `serie_id` int DEFAULT NULL,
  `matriz_curricular_id` int DEFAULT NULL,
  `materia_id` int DEFAULT NULL COMMENT 'Exceção por componente; NULL = todos',
  `periodo_tipo` enum('bimestre','trimestre','semestre','etapa_unica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bimestre',
  `periodo_numero` tinyint unsigned DEFAULT NULL COMMENT '1-4; NULL = vale para o ano todo',
  `media_minima` decimal(8,2) NOT NULL DEFAULT '6.00',
  `frequencia_minima` decimal(5,2) NOT NULL DEFAULT '75.00',
  `usar_frequencia` tinyint(1) NOT NULL DEFAULT '0',
  `round_mode` enum('none','half') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `decimal_places` tinyint(1) NOT NULL DEFAULT '2',
  `formula_media` text COLLATE utf8mb4_unicode_ci,
  `formula_final` text COLLATE utf8mb4_unicode_ci,
  `recuperacao_tipo` enum('nenhuma','continua','periodo','final') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'periodo',
  `recuperacao_composicao` enum('maior_nota','substitui','composicao','formula') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'maior_nota',
  `min_avaliacoes` smallint unsigned DEFAULT NULL,
  `max_avaliacoes` smallint unsigned DEFAULT NULL,
  `componentes_sem_nota` tinyint(1) NOT NULL DEFAULT '0',
  `aprovacao_so_frequencia` tinyint(1) NOT NULL DEFAULT '0',
  `situacoes_json` text COLLATE utf8mb4_unicode_ci,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `versao` int NOT NULL DEFAULT '1',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_regras_academicas_codigo` (`codigo`),
  KEY `idx_regras_academicas_ano` (`ano_letivo`,`ativo`),
  KEY `idx_regras_academicas_curso_serie` (`curso_id`,`serie_id`),
  KEY `idx_regras_academicas_materia` (`materia_id`),
  KEY `fk_regras_acad_serie` (`serie_id`),
  KEY `fk_regras_acad_matriz` (`matriz_curricular_id`),
  CONSTRAINT `fk_regras_acad_curso` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_regras_acad_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_regras_acad_matriz` FOREIGN KEY (`matriz_curricular_id`) REFERENCES `matrizes_curriculares` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_regras_acad_serie` FOREIGN KEY (`serie_id`) REFERENCES `serie` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- regras_academicas_historico
--
CREATE TABLE IF NOT EXISTS `regras_academicas_historico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `regra_id` int NOT NULL,
  `versao` int NOT NULL,
  `parametros_json` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `usuario_nome` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_regras_academicas_historico_versao` (`regra_id`,`versao`),
  KEY `idx_regras_academicas_historico_regra` (`regra_id`,`created_at`),
  CONSTRAINT `fk_regras_acad_hist_regra` FOREIGN KEY (`regra_id`) REFERENCES `regras_academicas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- regras_mascara
--
CREATE TABLE IF NOT EXISTS `regras_mascara` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `mascara_id` bigint NOT NULL,
  `chave_regra` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_regra` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precedencia` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_regra_mascara_chave` (`mascara_id`,`chave_regra`),
  CONSTRAINT `fk_regra_mascara` FOREIGN KEY (`mascara_id`) REFERENCES `mascaras_alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- relatorios
--
CREATE TABLE IF NOT EXISTS `relatorios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `tipo` enum('desempenho','jornada','redacao') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `dados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Dados detalhados do relatório',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `relatorios_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- responsaveis
--
CREATE TABLE IF NOT EXISTS `responsaveis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `senha_hash` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `force_password_change` tinyint(1) NOT NULL DEFAULT '0',
  `cpf` varchar(14) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `rg` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `celular` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `endereco` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `numero` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `complemento` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `bairro` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cidade` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `uf` char(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cep` varchar(9) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `observacoes` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_cpf` (`cpf`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- resultado_academico
--
CREATE TABLE IF NOT EXISTS `resultado_academico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `ano_letivo` smallint unsigned NOT NULL,
  `periodo_tipo` enum('bimestre','trimestre','semestre','ano') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ano',
  `periodo_numero` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0 = ano inteiro; 1-4 = etapa',
  `versao` int NOT NULL DEFAULT '1',
  `status` enum('em_andamento','homologado','reaberto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'em_andamento',
  `situacao` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'em_andamento',
  `rotulo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Em andamento',
  `media_final` decimal(8,2) DEFAULT NULL,
  `frequencia_percentual` decimal(5,2) DEFAULT NULL,
  `faltas` int DEFAULT NULL,
  `regra_id` int DEFAULT NULL,
  `regra_versao` int DEFAULT NULL,
  `conselho_sessao_id` int DEFAULT NULL,
  `conselho_resultado` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot_json` mediumtext COLLATE utf8mb4_unicode_ci,
  `homologado_em` datetime DEFAULT NULL,
  `homologado_por` int DEFAULT NULL,
  `reaberto_em` datetime DEFAULT NULL,
  `reaberto_por` int DEFAULT NULL,
  `reaberto_motivo` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resultado_aluno_periodo` (`aluno_id`,`turma_id`,`ano_letivo`,`periodo_tipo`,`periodo_numero`),
  KEY `idx_resultado_turma_periodo` (`turma_id`,`ano_letivo`,`periodo_tipo`,`periodo_numero`,`status`),
  KEY `idx_resultado_situacao` (`situacao`,`status`),
  KEY `idx_resultado_aluno` (`aluno_id`,`ano_letivo`),
  CONSTRAINT `fk_resultado_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_resultado_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- resultado_academico_historico
--
CREATE TABLE IF NOT EXISTS `resultado_academico_historico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `resultado_id` int NOT NULL,
  `versao` int NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `situacao` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rotulo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `snapshot_json` mediumtext COLLATE utf8mb4_unicode_ci,
  `motivo` text COLLATE utf8mb4_unicode_ci,
  `usuario_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resultado_hist_resultado` (`resultado_id`,`versao`),
  CONSTRAINT `fk_resultado_hist_resultado` FOREIGN KEY (`resultado_id`) REFERENCES `resultado_academico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- resultado_academico_itens
--
CREATE TABLE IF NOT EXISTS `resultado_academico_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `resultado_id` int NOT NULL,
  `materia_id` int DEFAULT NULL,
  `materia_nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carga_horaria` int DEFAULT NULL,
  `media` decimal(8,2) DEFAULT NULL,
  `recuperacao` decimal(8,2) DEFAULT NULL,
  `media_final` decimal(8,2) DEFAULT NULL,
  `faltas` int DEFAULT NULL,
  `frequencia_percentual` decimal(5,2) DEFAULT NULL,
  `situacao` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'em_andamento',
  `rotulo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Em andamento',
  `situacao_especial` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'dispensado, aproveitamento, progressao_parcial, dependencia',
  `observacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resultado_itens_resultado` (`resultado_id`),
  CONSTRAINT `fk_resultado_itens_resultado` FOREIGN KEY (`resultado_id`) REFERENCES `resultado_academico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- resultado_documento_emissoes
--
CREATE TABLE IF NOT EXISTS `resultado_documento_emissoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelo_codigo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `resultado_id` int DEFAULT NULL,
  `ano_letivo` smallint unsigned DEFAULT NULL,
  `periodo_tipo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `periodo_numero` tinyint unsigned DEFAULT NULL,
  `numero` int NOT NULL DEFAULT '0',
  `hash_validacao` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot_json` mediumtext COLLATE utf8mb4_unicode_ci,
  `emitido_por` int DEFAULT NULL,
  `emitido_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resultado_emis_tipo_ano` (`tipo`,`ano_letivo`),
  KEY `idx_resultado_emis_aluno` (`aluno_id`),
  KEY `idx_resultado_emis_turma` (`turma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- resultado_documento_layouts
--
CREATE TABLE IF NOT EXISTS `resultado_documento_layouts` (
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelo_codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- resultado_fechamento_config
--
CREATE TABLE IF NOT EXISTS `resultado_fechamento_config` (
  `id` tinyint unsigned NOT NULL DEFAULT '1',
  `exigir_conselho` tinyint(1) NOT NULL DEFAULT '0',
  `exigir_frequencia` tinyint(1) NOT NULL DEFAULT '0',
  `exigir_notas` tinyint(1) NOT NULL DEFAULT '1',
  `atualizado_por` int DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- resultado_situacoes_especiais
--
CREATE TABLE IF NOT EXISTS `resultado_situacoes_especiais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `turma_id` int DEFAULT NULL,
  `ano_letivo` smallint unsigned NOT NULL,
  `materia_id` int DEFAULT NULL COMMENT 'NULL = situação geral do aluno',
  `tipo` enum('dispensado','aproveitamento','progressao_parcial','dependencia','transferencia','classificacao') COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `criado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resultado_esp_aluno_ano` (`aluno_id`,`ano_letivo`),
  KEY `idx_resultado_esp_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- reuniao_anexos
--
CREATE TABLE IF NOT EXISTS `reuniao_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reuniao_id` int NOT NULL,
  `nome` varchar(255) NOT NULL,
  `caminho` varchar(500) NOT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reuniao` (`reuniao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- reuniao_turmas
--
CREATE TABLE IF NOT EXISTS `reuniao_turmas` (
  `reuniao_id` int NOT NULL,
  `turma_id` int NOT NULL,
  PRIMARY KEY (`reuniao_id`,`turma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- reunioes
--
CREATE TABLE IF NOT EXISTS `reunioes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('pais','geral') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pais',
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_reuniao` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fim` time DEFAULT NULL,
  `local_reuniao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `aluno_id` int DEFAULT NULL,
  `responsavel_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_tipo_data` (`tipo`,`data_reuniao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_calendar_event_classes
--
CREATE TABLE IF NOT EXISTS `school_calendar_event_classes` (
  `event_id` bigint unsigned NOT NULL,
  `turma_id` int unsigned NOT NULL,
  PRIMARY KEY (`event_id`,`turma_id`),
  KEY `idx_school_calendar_class` (`turma_id`,`event_id`),
  CONSTRAINT `fk_school_calendar_class` FOREIGN KEY (`event_id`) REFERENCES `school_calendar_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_calendar_event_reads
--
CREATE TABLE IF NOT EXISTS `school_calendar_event_reads` (
  `event_id` bigint unsigned NOT NULL,
  `responsavel_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `lido_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`,`responsavel_id`,`aluno_id`),
  CONSTRAINT `fk_school_calendar_read` FOREIGN KEY (`event_id`) REFERENCES `school_calendar_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_calendar_event_students
--
CREATE TABLE IF NOT EXISTS `school_calendar_event_students` (
  `event_id` bigint unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  PRIMARY KEY (`event_id`,`aluno_id`),
  KEY `idx_school_calendar_student` (`aluno_id`,`event_id`),
  CONSTRAINT `fk_school_calendar_student` FOREIGN KEY (`event_id`) REFERENCES `school_calendar_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_calendar_events
--
CREATE TABLE IF NOT EXISTS `school_calendar_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `categoria` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'evento',
  `prioridade` enum('normal','importante','urgente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `local` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inicio_em` datetime NOT NULL,
  `fim_em` datetime DEFAULT NULL,
  `dia_inteiro` tinyint(1) NOT NULL DEFAULT '0',
  `publico` enum('todos','turmas','alunos') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('rascunho','publicado','cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publicado',
  `criado_por` int unsigned NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_school_calendar_period` (`inicio_em`,`fim_em`),
  KEY `idx_school_calendar_status` (`status`,`inicio_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_communication_attachments
--
CREATE TABLE IF NOT EXISTS `school_communication_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `communication_id` bigint unsigned NOT NULL,
  `arquivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_school_communication_attachment` (`communication_id`),
  CONSTRAINT `fk_school_communication_attachment` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_communication_classes
--
CREATE TABLE IF NOT EXISTS `school_communication_classes` (
  `communication_id` bigint unsigned NOT NULL,
  `turma_id` int unsigned NOT NULL,
  PRIMARY KEY (`communication_id`,`turma_id`),
  KEY `idx_school_communication_class` (`turma_id`,`communication_id`),
  CONSTRAINT `fk_school_communication_class` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_communication_reads
--
CREATE TABLE IF NOT EXISTS `school_communication_reads` (
  `communication_id` bigint unsigned NOT NULL,
  `responsavel_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `lido_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`communication_id`,`responsavel_id`,`aluno_id`),
  KEY `idx_school_communication_parent_read` (`responsavel_id`,`lido_em`),
  CONSTRAINT `fk_school_communication_read` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_communication_replies
--
CREATE TABLE IF NOT EXISTS `school_communication_replies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `communication_id` bigint unsigned NOT NULL,
  `responsavel_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `sender_type` enum('responsavel','admin','professor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_id` int unsigned NOT NULL,
  `mensagem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lido_em` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_school_communication_reply` (`communication_id`,`created_at`),
  CONSTRAINT `fk_school_communication_reply` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_communication_students
--
CREATE TABLE IF NOT EXISTS `school_communication_students` (
  `communication_id` bigint unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  PRIMARY KEY (`communication_id`,`aluno_id`),
  KEY `idx_school_communication_student` (`aluno_id`,`communication_id`),
  CONSTRAINT `fk_school_communication_student` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_communications
--
CREATE TABLE IF NOT EXISTS `school_communications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioridade` enum('normal','importante','urgente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `permite_resposta` tinyint(1) NOT NULL DEFAULT '1',
  `publico` enum('todos','turmas','alunos') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `autor_tipo` enum('admin','professor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `autor_id` int unsigned NOT NULL,
  `status` enum('rascunho','publicado','arquivado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publicado',
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_school_communications_status` (`status`,`published_at`),
  KEY `idx_school_communications_priority` (`prioridade`,`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- school_locations
--
CREATE TABLE IF NOT EXISTS `school_locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('sala','laboratorio','cantina','deposito','biblioteca','secretaria','quadra','outro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sala',
  `capacidade` int unsigned DEFAULT NULL,
  `bloco` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `andar` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsavel_nome` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_locations_codigo` (`codigo`),
  KEY `idx_school_locations_tipo` (`tipo`),
  KEY `idx_school_locations_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- secretaria_declaracoes_layouts
--
CREATE TABLE IF NOT EXISTS `secretaria_declaracoes_layouts` (
  `id` tinyint unsigned NOT NULL,
  `cabecalho_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `rodape_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `imagem_cabecalho` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagem_rodape` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `razao_social` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnpj` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade_assinatura_id` int DEFAULT NULL,
  `cargo_assinante` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'direcao',
  `assinante_nome` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atualizado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- secretaria_modelos_documentos
--
CREATE TABLE IF NOT EXISTS `secretaria_modelos_documentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex.: contrato_matricula',
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cabecalho_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `corpo_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `rodape_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `estrutura_json` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON do editor visual (seções/colunas/elementos)',
  `imagem_cabecalho` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagem_rodape` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orientacao` enum('retrato','paisagem') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'retrato',
  `formato_papel` enum('a4','a5') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a4',
  `margem_mm` tinyint unsigned NOT NULL DEFAULT '20',
  `espacamento_linha` decimal(3,2) NOT NULL DEFAULT '1.50',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `usar_layout_padrao` tinyint(1) NOT NULL DEFAULT '0',
  `criado_por` int DEFAULT NULL,
  `atualizado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_secretaria_modelos_documentos_codigo` (`codigo`),
  KEY `idx_secretaria_modelos_documentos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- serie
--
CREATE TABLE IF NOT EXISTS `serie` (
  `id` int NOT NULL AUTO_INCREMENT,
  `curso_id` int NOT NULL,
  `nome` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_serie_curso_nome` (`curso_id`,`nome`),
  KEY `idx_serie_curso` (`curso_id`),
  KEY `idx_serie_ativo` (`ativo`),
  CONSTRAINT `fk_serie_curso` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- sessoes
--
CREATE TABLE IF NOT EXISTS `sessoes` (
  `id` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `usuario_id` int NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `sessoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- simulados
--
CREATE TABLE IF NOT EXISTS `simulados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `ano` int NOT NULL,
  `disciplina` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `tipo_vestibular` enum('ENEM','FUVEST','VUNESP','UNICAMP','UFMG','OUTROS') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'ENEM',
  `idioma` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'Português',
  `quantidade_questoes` int NOT NULL DEFAULT '10',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `iniciado_em` datetime DEFAULT NULL,
  `finalizado_em` datetime DEFAULT NULL,
  `status` enum('criado','em_andamento','finalizado','cancelado') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'criado',
  `tempo_total` int DEFAULT '0',
  `tempo_limite` int DEFAULT '0',
  `total_acertos` int DEFAULT '0',
  `total_erros` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `nota_final` decimal(5,2) DEFAULT '0.00',
  `oculto` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_simulados_aluno` (`aluno_id`),
  KEY `idx_simulados_status` (`status`),
  KEY `idx_simulados_ano` (`ano`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- simulados_estatisticas
--
CREATE TABLE IF NOT EXISTS `simulados_estatisticas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `simulado_id` int NOT NULL,
  `materia` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `total_questoes` int DEFAULT '0',
  `acertos` int DEFAULT '0',
  `erros` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `tempo_medio` decimal(8,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_simulado_estatisticas_simulado` (`simulado_id`),
  KEY `idx_simulado_estatisticas_materia` (`materia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- simulados_questoes
--
CREATE TABLE IF NOT EXISTS `simulados_questoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `simulado_id` int NOT NULL,
  `questao_index` int NOT NULL,
  `questao_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `enunciado` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a_file` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `alternativa_b` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_b_file` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `alternativa_c` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_c_file` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `alternativa_d` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_d_file` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `alternativa_e` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_e_file` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `resposta_certa` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `resposta_aluno` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `acertou` tinyint(1) DEFAULT NULL,
  `respondido_em` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT '0',
  `materia` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `dificuldade` enum('facil','medio','dificil') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'medio',
  `materias_path_json` json DEFAULT NULL COMMENT 'Array JSON: ["Disciplina","Tópico","Subtópico",...]',
  PRIMARY KEY (`id`),
  KEY `idx_simulado_questoes_simulado` (`simulado_id`),
  KEY `idx_simulado_questoes_index` (`questao_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- student_access_events
--
CREATE TABLE IF NOT EXISTS `student_access_events` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `kind` enum('entrada','saida') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_at` datetime NOT NULL,
  `confidence` decimal(6,5) DEFAULT NULL,
  `provider_presence_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `registered_by_user_id` int DEFAULT NULL,
  `notified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_access_event_provider` (`provider_presence_id`),
  KEY `idx_access_event_student_date` (`student_id`,`event_at`),
  KEY `idx_access_event_date` (`event_at`),
  CONSTRAINT `fk_access_event_student` FOREIGN KEY (`student_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- student_face_profiles
--
CREATE TABLE IF NOT EXISTS `student_face_profiles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `external_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `consent_at` datetime NOT NULL,
  `consent_by_user_id` int DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_face_profile_student` (`student_id`),
  UNIQUE KEY `uq_face_profile_external_key` (`external_key`),
  CONSTRAINT `fk_face_profile_student` FOREIGN KEY (`student_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- student_face_samples
--
CREATE TABLE IF NOT EXISTS `student_face_samples` (
  `id` int NOT NULL AUTO_INCREMENT,
  `face_profile_id` int NOT NULL,
  `provider_face_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_face_sample_provider` (`provider_face_id`),
  KEY `idx_face_sample_profile` (`face_profile_id`),
  CONSTRAINT `fk_face_sample_profile` FOREIGN KEY (`face_profile_id`) REFERENCES `student_face_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- suporte_tickets
--
CREATE TABLE IF NOT EXISTS `suporte_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `assunto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'geral',
  `modulo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('aberto','em_andamento','respondido','fechado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'aberto',
  `prioridade` enum('baixa','normal','alta','urgente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `admin_atribuido_id` int DEFAULT NULL COMMENT 'Admin do admin_educatudo que está cuidando do ticket',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `fechado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_status` (`status`),
  KEY `idx_categoria` (`categoria`),
  KEY `idx_criado_em` (`criado_em`),
  KEY `idx_tickets_aluno_status` (`aluno_id`,`status`),
  CONSTRAINT `suporte_tickets_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- suporte_tickets_mensagens
--
CREATE TABLE IF NOT EXISTS `suporte_tickets_mensagens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `remetente_tipo` enum('aluno','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remetente_id` int NOT NULL COMMENT 'ID do aluno ou admin',
  `mensagem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `anexo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nome do arquivo anexado',
  `lida` tinyint(1) DEFAULT '0',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_criado_em` (`criado_em`),
  KEY `idx_lida` (`lida`),
  KEY `idx_mensagens_ticket_criado` (`ticket_id`,`criado_em`),
  CONSTRAINT `suporte_tickets_mensagens_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `suporte_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- tentativas_login
--
CREATE TABLE IF NOT EXISTS `tentativas_login` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL,
  `tipo` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'aluno, admin_escola, professor, pai',
  `motivo_falha` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'nickname_invalido, senha_invalida',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tentativas_login_tipo_created` (`tipo`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- tipos_curso
--
CREATE TABLE IF NOT EXISTS `tipos_curso` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tipos_curso_nome` (`nome`),
  UNIQUE KEY `uq_tipos_curso_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- tudinha_analises
--
CREATE TABLE IF NOT EXISTS `tudinha_analises` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `data_ate` date NOT NULL COMMENT 'Data limite da análise',
  `analise_completa` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Análise completa gerada pela IA',
  `dificuldades` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Dificuldades identificadas',
  `facilidades` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Facilidades identificadas',
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Observações gerais',
  `recomendacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Recomendações para pais e coordenadores',
  `resumo_estatisticas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Resumo das estatísticas analisadas',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL COMMENT 'ID do admin que gerou a análise',
  `anonymized_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `retention_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `data_ate` (`data_ate`),
  KEY `created_at` (`created_at`),
  KEY `idx_analises_expires` (`expires_at`),
  KEY `idx_analises_anonymized` (`anonymized_at`),
  CONSTRAINT `fk_analises_tudinha_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- tudinha_conversas
--
CREATE TABLE IF NOT EXISTS `tudinha_conversas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `materia` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `total_interacoes` int DEFAULT '0',
  `ultima_atividade` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluida` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_updated_at` (`updated_at`),
  KEY `idx_conversas_aluno` (`aluno_id`),
  CONSTRAINT `tudinha_conversas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- tudinha_memorias_aluno
--
CREATE TABLE IF NOT EXISTS `tudinha_memorias_aluno` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `chave` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'slug curto definido pela IA, ex: apelido_preferido, prova_alvo',
  `valor` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aluno_chave` (`aluno_id`,`chave`),
  KEY `idx_aluno` (`aluno_id`),
  CONSTRAINT `tma_fk_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- tudinha_mensagens
--
CREATE TABLE IF NOT EXISTS `tudinha_mensagens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `conversa_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `mensagem` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('texto','imagem','audio','pdf') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'texto',
  `image_url` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `is_ia` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `anonymized_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `retention_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conversa` (`conversa_id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_mensagens_conversa` (`conversa_id`),
  KEY `idx_mensagens_aluno` (`aluno_id`),
  KEY `idx_mensagens_is_ia` (`is_ia`),
  KEY `idx_mensagens_image_url` (`image_url`),
  KEY `idx_mensagens_expires` (`expires_at`),
  KEY `idx_mensagens_anonymized` (`anonymized_at`),
  CONSTRAINT `tudinha_mensagens_ibfk_1` FOREIGN KEY (`conversa_id`) REFERENCES `tudinha_conversas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tudinha_mensagens_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- turmas
--
CREATE TABLE IF NOT EXISTS `turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Ex: 1ºA, 2ºB',
  `ano_letivo` int NOT NULL,
  `ano_letivo_id` int DEFAULT NULL,
  `serie` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie_id` int DEFAULT NULL,
  `matriz_curricular_id` int DEFAULT NULL,
  `curso_id` int DEFAULT NULL,
  `curso_novo_id` int DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tipo_ensino` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `vagas` int unsigned DEFAULT NULL COMMENT 'NULL ou 0 = sem limite',
  `turma_origem_id` int DEFAULT NULL COMMENT 'Turma do ano anterior (virada)',
  `turno` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `sala_padrao_id` int DEFAULT NULL,
  `observacoes` text COLLATE utf8mb3_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_ano_letivo` (`ano_letivo`),
  KEY `idx_serie` (`serie`),
  KEY `idx_ativo` (`ativo`),
  KEY `fk_turmas_curso` (`curso_id`),
  KEY `fk_turmas_ano_letivo` (`ano_letivo_id`),
  KEY `fk_turmas_serie` (`serie_id`),
  KEY `fk_turmas_curso_novo` (`curso_novo_id`),
  KEY `fk_turmas_matriz_curricular` (`matriz_curricular_id`),
  KEY `fk_turmas_sala_padrao` (`sala_padrao_id`),
  CONSTRAINT `fk_turmas_ano_letivo` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_turmas_curso_novo` FOREIGN KEY (`curso_novo_id`) REFERENCES `curso` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_turmas_matriz_curricular` FOREIGN KEY (`matriz_curricular_id`) REFERENCES `matrizes_curriculares` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_turmas_sala_padrao` FOREIGN KEY (`sala_padrao_id`) REFERENCES `school_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_turmas_serie` FOREIGN KEY (`serie_id`) REFERENCES `serie` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- turmas_lista_config
--
CREATE TABLE IF NOT EXISTS `turmas_lista_config` (
  `turma_id` int NOT NULL,
  `ano_letivo_id` int NOT NULL,
  `criterio_ordem` enum('alfabetica','meninas_primeiro','meninos_primeiro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alfabetica',
  `data_corte` date DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`turma_id`,`ano_letivo_id`),
  KEY `idx_turmas_lista_ano` (`ano_letivo_id`),
  CONSTRAINT `fk_turmas_lista_ano` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_turmas_lista_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- tutoriais
--
CREATE TABLE IF NOT EXISTS `tutoriais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `link_youtube` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ordem` (`ordem`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- unidades
--
CREATE TABLE IF NOT EXISTS `unidades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('matriz','filial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'matriz',
  `razao_social` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnpj` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inep` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dependencia_administrativa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endereco` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `complemento` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bairro` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cidade` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uf` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cep` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diretor_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secretario_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ato_autorizacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ato_credenciamento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ato_reconhecimento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diretor_registro` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secretario_registro` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_unidades_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- usuarios
--
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('admin_escola') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `perfil_admin` enum('dev','diretor','coordenador','aee','financeiro','secretaria') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Aplicável apenas quando tipo = admin_escola',
  `nome` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Apenas para admins e pais',
  `senha_hash` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `avatar_url` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `criado_por` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `permissoes_admin_json` json DEFAULT NULL COMMENT 'Permissões administrativas por módulo e ação (visualizar, cadastrar, alterar, excluir)',
  `perfil_permissao_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_email` (`email`),
  KEY `idx_perfil_admin` (`perfil_admin`),
  KEY `criado_por` (`criado_por`),
  KEY `idx_usuarios_perfil_permissao_id` (`perfil_permissao_id`),
  CONSTRAINT `fk_usuarios_perfil_permissao` FOREIGN KEY (`perfil_permissao_id`) REFERENCES `admin_perfis_permissao` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- usuarios_consentimentos
--
CREATE TABLE IF NOT EXISTS `usuarios_consentimentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `user_role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `accepted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_consent` (`user_id`,`user_role`,`document_type`,`document_version`),
  KEY `idx_user_role` (`user_role`),
  KEY `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- validacao_tokens_apps
--
CREATE TABLE IF NOT EXISTS `validacao_tokens_apps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `app` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tenant_slug` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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

--
-- versoes_adaptadas
--
CREATE TABLE IF NOT EXISTS `versoes_adaptadas` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `mascara_id` bigint NOT NULL,
  `tipo_versao` enum('acesso','significativa') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'acesso',
  `hash_prova_origem` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adapted_prova_id` int DEFAULT NULL,
  `regras_snapshot_json` json NOT NULL,
  `status_aprovacao` enum('pendente','aprovada_professor','aprovada_aee','aprovada','invalidada_drift','rejeitada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `aprovado_por_professor` int DEFAULT NULL,
  `aprovado_por_aee` int DEFAULT NULL,
  `gerado_de_versao_id` bigint DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_versao_prova_aluno` (`prova_id`,`aluno_id`),
  KEY `idx_versao_status` (`status_aprovacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- versoes_adaptadas_logs
--
CREATE TABLE IF NOT EXISTS `versoes_adaptadas_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `versao_adaptada_id` bigint DEFAULT NULL,
  `mascara_id` bigint DEFAULT NULL,
  `aluno_id` int DEFAULT NULL,
  `prova_id` int DEFAULT NULL,
  `acao` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detalhes_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_versao` (`versao_adaptada_id`),
  KEY `idx_log_aluno` (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- webhooks
--
CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `endpoint` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('chat_ia','chat','geral') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `escola_id` int DEFAULT NULL COMMENT 'NULL para webhook global',
  `ativo` tinyint(1) DEFAULT '1',
  `configuracao` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Configurações adicionais do webhook',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_escola` (`escola_id`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
DROP TRIGGER IF EXISTS `trg_alertas_sensiveis_no_delete`$$
CREATE TRIGGER `trg_alertas_sensiveis_no_delete` BEFORE DELETE ON `alertas_sensiveis` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'LGPD: alertas_sensiveis não podem ser apagados';
END$$

DROP TRIGGER IF EXISTS `trg_alertas_sensiveis_acoes_no_delete`$$
CREATE TRIGGER `trg_alertas_sensiveis_acoes_no_delete` BEFORE DELETE ON `alertas_sensiveis_acoes` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'LGPD: alertas_sensiveis_acoes não podem ser apagados';
END$$

DELIMITER ;

SET UNIQUE_CHECKS = 1;
SET FOREIGN_KEY_CHECKS = 1;
