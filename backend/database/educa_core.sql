-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: educatudo_mysql:3306
-- Tempo de geração: 06/07/2026 às 23:39
-- Versão do servidor: 9.7.1
-- Versão do PHP: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `educa_core`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `accommodation_documents`
--

CREATE TABLE `accommodation_documents` (
  `id` bigint NOT NULL,
  `accommodation_id` bigint NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enc_algo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AES-256-GCM',
  `uploaded_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `accommodation_rules`
--

CREATE TABLE `accommodation_rules` (
  `id` bigint NOT NULL,
  `accommodation_id` bigint NOT NULL,
  `rule_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precedence` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_perfis_permissao`
--

CREATE TABLE `admin_perfis_permissao` (
  `id` int NOT NULL,
  `nome` varchar(120) COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo_base` enum('dev','diretor','coordenador','financeiro','secretaria') COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `permissoes_json` json NOT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ai_jobs`
--

CREATE TABLE `ai_jobs` (
  `id` int UNSIGNED NOT NULL,
  `job_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'gerar_exercicio | gerar_prova | corrigir_redacao | gerar_flashcards | gerar_slides',
  `status` enum('pending','processing','done','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payload` json NOT NULL COMMENT 'Parâmetros de entrada serializados',
  `result` json DEFAULT NULL COMMENT 'Resultado retornado pela IA',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `user_id` int UNSIGNED DEFAULT NULL COMMENT 'Usuário que disparou o job',
  `user_role` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'aluno | professor | admin',
  `attempts` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alertas_sensiveis`
--

CREATE TABLE `alertas_sensiveis` (
  `id` int NOT NULL,
  `aluno_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `categoria` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `nivel` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `mensagem_resumo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `mensagem_aluno` longtext COLLATE utf8mb3_unicode_ci COMMENT 'Texto completo da mensagem do aluno',
  `mensagem_chat_id` int DEFAULT NULL COMMENT 'ID em mensagens_chat para localizar resposta Tudinha',
  `status` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'novo',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  `anonymized_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `retention_reason` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Acionadores `alertas_sensiveis`
--
DELIMITER $$
CREATE TRIGGER `trg_alertas_sensiveis_no_delete` BEFORE DELETE ON `alertas_sensiveis` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'LGPD: alertas_sensiveis não podem ser apagados';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alertas_sensiveis_acoes`
--

CREATE TABLE `alertas_sensiveis_acoes` (
  `id` int NOT NULL,
  `alerta_id` int NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `acao` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL,
  `observacoes` text COLLATE utf8mb3_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `anonymized_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `retention_reason` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Acionadores `alertas_sensiveis_acoes`
--
DELIMITER $$
CREATE TRIGGER `trg_alertas_sensiveis_acoes_no_delete` BEFORE DELETE ON `alertas_sensiveis_acoes` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'LGPD: alertas_sensiveis_acoes não podem ser apagados';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos`
--

CREATE TABLE `alunos` (
  `id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `celular` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cpf` varchar(14) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `rg` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `logradouro` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `numero` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `complemento` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `bairro` varchar(120) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cidade` varchar(120) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `uf` char(2) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cep` varchar(8) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `foto_url` text COLLATE utf8mb3_unicode_ci,
  `senha_hash` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `ra` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Registro Acadêmico - login do aluno',
  `codigo_aluno` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `serie` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `data_nasc` date DEFAULT NULL,
  `responsavel_id` int DEFAULT NULL COMMENT 'Pai/Responsável vinculado',
  `ativo` tinyint(1) DEFAULT '1',
  `status` enum('ACTIVE','INACTIVE','GRADUATED','SUSPENDED','PENDING') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `pagante` tinyint(1) DEFAULT '1' COMMENT 'Indica se a escola paga por este aluno',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `nickname` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT '1',
  `sexo` enum('M','F','N') COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `nome_mae` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `nome_pai` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `codigo_inep` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_acoes_diarias`
--

CREATE TABLE `alunos_acoes_diarias` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `acao` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de ação: gerar_tema_redacao, corrigir_redacao, etc.',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registra ações diárias dos alunos para controle de limites';

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_historico_status`
--

CREATE TABLE `alunos_historico_status` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `old_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observation` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` int NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_ocorrencias`
--

CREATE TABLE `alunos_ocorrencias` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `data_ocorrencia` datetime NOT NULL,
  `titulo` varchar(120) COLLATE utf8mb3_unicode_ci NOT NULL,
  `detalhe` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `nivel_gravidade` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `atitude_coordenacao` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `retorno_em` date DEFAULT NULL,
  `enviar_pais` tinyint(1) NOT NULL DEFAULT '0',
  `criado_por` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_ocorrencias_itens`
--

CREATE TABLE `alunos_ocorrencias_itens` (
  `id` int NOT NULL,
  `ocorrencia_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_onboarding`
--

CREATE TABLE `alunos_onboarding` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `meu_sonho` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objetivo_principal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nivel_comprometimento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pontos_dificuldade` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempo_estudo_dia` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pontos_fortes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estilo_aprendizado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completado` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_responsaveis`
--

CREATE TABLE `alunos_responsaveis` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `responsavel_id` int NOT NULL,
  `tipo_vinculo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_financeiro` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_seguranca`
--

CREATE TABLE `alunos_seguranca` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `pergunta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resposta_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_sessoes_acesso`
--

CREATE TABLE `alunos_sessoes_acesso` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_atividade_at` datetime DEFAULT NULL,
  `contexto_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contexto_id` int UNSIGNED DEFAULT NULL,
  `contexto_label` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logout_at` datetime DEFAULT NULL,
  `tempo_uso_segundos` int DEFAULT NULL COMMENT 'Tempo em segundos desde login até logout',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `status` enum('ativo','finalizado','expirado') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_turmas_historico`
--

CREATE TABLE `alunos_turmas_historico` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `ano_letivo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_turma_chamada`
--

CREATE TABLE `alunos_turma_chamada` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `ano_letivo_id` int NOT NULL,
  `numero_chamada` smallint UNSIGNED NOT NULL,
  `entrada_tardia` tinyint(1) NOT NULL DEFAULT '0',
  `marcado_tr` tinyint(1) NOT NULL DEFAULT '0',
  `data_entrada_turma` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ano_letivo`
--

CREATE TABLE `ano_letivo` (
  `id` int NOT NULL,
  `ano` int NOT NULL COMMENT 'Ano civil do ano letivo (ex: 2025)',
  `data_inicio` date DEFAULT NULL COMMENT 'Início do ano letivo',
  `data_fim` date DEFAULT NULL COMMENT 'Fim do ano letivo',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `apostilas_ia`
--

CREATE TABLE `apostilas_ia` (
  `id` bigint UNSIGNED NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `escola_id` bigint UNSIGNED DEFAULT NULL,
  `serie_id` bigint UNSIGNED DEFAULT NULL,
  `turma_id` bigint UNSIGNED DEFAULT NULL,
  `disciplina_id` bigint UNSIGNED DEFAULT NULL,
  `professor_id` bigint UNSIGNED DEFAULT NULL,
  `arquivo_pdf` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pendente','processando','pronto','erro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `total_paginas` int NOT NULL DEFAULT '0',
  `erro` text COLLATE utf8mb4_unicode_ci,
  `vector_store_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID do vector store OpenAI gerado pelo microserviÃ§o Python',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `legado_modulo_id` int DEFAULT NULL COMMENT 'ID de modulos_apostilas de origem',
  `capa_personalizada` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `apostila_ia_chunks`
--

CREATE TABLE `apostila_ia_chunks` (
  `id` bigint UNSIGNED NOT NULL,
  `apostila_id` bigint UNSIGNED NOT NULL,
  `pagina_inicio` int NOT NULL,
  `pagina_fim` int NOT NULL,
  `conteudo` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata_json` json DEFAULT NULL,
  `embedding_provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'openai',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `apostila_ia_conversas`
--

CREATE TABLE `apostila_ia_conversas` (
  `id` bigint UNSIGNED NOT NULL,
  `apostila_id` bigint UNSIGNED NOT NULL,
  `professor_id` bigint UNSIGNED DEFAULT NULL,
  `pergunta` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `resposta` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `paginas_usadas` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `apostila_ia_exercicios`
--

CREATE TABLE `apostila_ia_exercicios` (
  `id` bigint UNSIGNED NOT NULL,
  `apostila_id` bigint UNSIGNED NOT NULL,
  `pagina` int NOT NULL,
  `capitulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tema` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` enum('objetiva','discursiva','verdadeiro_falso','associacao','outro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `enunciado` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativas` json DEFAULT NULL,
  `gabarito` longtext COLLATE utf8mb4_unicode_ci,
  `dificuldade` enum('facil','media','dificil','nao_identificada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_identificada',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `apostila_ia_paginas`
--

CREATE TABLE `apostila_ia_paginas` (
  `id` bigint UNSIGNED NOT NULL,
  `apostila_id` bigint UNSIGNED NOT NULL,
  `numero_pagina` int NOT NULL,
  `texto_extraido` longtext COLLATE utf8mb4_unicode_ci,
  `imagem_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `apostila_ia_turmas`
--

CREATE TABLE `apostila_ia_turmas` (
  `id` bigint UNSIGNED NOT NULL,
  `apostila_id` bigint UNSIGNED NOT NULL,
  `turma_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `assessment_versions`
--

CREATE TABLE `assessment_versions` (
  `id` bigint NOT NULL,
  `assessment_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `accommodation_id` bigint NOT NULL,
  `version_type` enum('acesso','significativa') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'acesso',
  `source_assessment_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adapted_prova_id` int DEFAULT NULL,
  `rules_snapshot_json` json NOT NULL,
  `approval_status` enum('pendente','aprovada_professor','aprovada_aee','aprovada','invalidada_drift','rejeitada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `approved_by_professor` int DEFAULT NULL,
  `approved_by_aee` int DEFAULT NULL,
  `generated_from_version_id` bigint DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `assessment_version_logs`
--

CREATE TABLE `assessment_version_logs` (
  `id` bigint NOT NULL,
  `assessment_version_id` bigint DEFAULT NULL,
  `accommodation_id` bigint DEFAULT NULL,
  `aluno_id` int DEFAULT NULL,
  `assessment_id` int DEFAULT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `assinaturas_creditos`
--

CREATE TABLE `assinaturas_creditos` (
  `id` int UNSIGNED NOT NULL,
  `user_type` enum('aluno','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `plano_id` int UNSIGNED NOT NULL,
  `inicio_em` date NOT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT '1',
  `ultima_recarga_em` date DEFAULT NULL COMMENT 'Último mês em que foi recarregado',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assinaturas ativas de planos de créditos';

-- --------------------------------------------------------

--
-- Estrutura para tabela `aulas_online`
--

CREATE TABLE `aulas_online` (
  `id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `plataforma` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_aula` varchar(700) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inicio_em` datetime NOT NULL,
  `fim_em` datetime DEFAULT NULL,
  `enviar_para_todos` tinyint(1) NOT NULL DEFAULT '0',
  `publicado` tinyint(1) NOT NULL DEFAULT '1',
  `gerar_panda` tinyint(1) NOT NULL DEFAULT '0',
  `panda_integracao_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_solicitada',
  `panda_integracao_erro` text COLLATE utf8mb4_unicode_ci,
  `panda_integracao_tentativas` int NOT NULL DEFAULT '0',
  `panda_integracao_ultima_tentativa_em` datetime DEFAULT NULL,
  `panda_live_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_stream_key_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_live_player` varchar(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_live_hls` varchar(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_rtmp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_stream_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_video_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_player` varchar(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_hls` varchar(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_synced_at` datetime DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `jaas_recording_url` varchar(1200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jaas_recording_path` varchar(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jaas_recording_session_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jaas_recording_uploaded_at` datetime DEFAULT NULL,
  `jaas_recording_webhook_raw` longtext COLLATE utf8mb4_unicode_ci,
  `link_gravacao` varchar(1200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL da gravação Jitsi/Jibri enviada via webhook'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `aulas_online_arquivos`
--

CREATE TABLE `aulas_online_arquivos` (
  `id` int NOT NULL,
  `aula_id` int NOT NULL,
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `aulas_online_turmas`
--

CREATE TABLE `aulas_online_turmas` (
  `aula_online_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avatares_alunos`
--

CREATE TABLE `avatares_alunos` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `nome_social` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `avatar_seed` varchar(500) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(500) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `avatar_updated_at` datetime DEFAULT NULL,
  `descricao_objetivos` text COLLATE utf8mb3_unicode_ci,
  `tipo_rosto` enum('redondo','oval','quadrado','triangular') COLLATE utf8mb3_unicode_ci DEFAULT 'oval',
  `cor_pele` enum('clara','media','escura') COLLATE utf8mb3_unicode_ci DEFAULT 'media',
  `tipo_cabelo` enum('curto','medio','longo','careca') COLLATE utf8mb3_unicode_ci DEFAULT 'curto',
  `cor_cabelo` enum('preto','castanho','loiro','ruivo','grisalho') COLLATE utf8mb3_unicode_ci DEFAULT 'preto',
  `estilo_cabelo` enum('liso','ondulado','cacheado','afro') COLLATE utf8mb3_unicode_ci DEFAULT 'liso',
  `cor_olhos` enum('castanho','azul','verde','preto') COLLATE utf8mb3_unicode_ci DEFAULT 'castanho',
  `tipo_sobrancelha` enum('fina','media','grossa') COLLATE utf8mb3_unicode_ci DEFAULT 'media',
  `tipo_nariz` enum('pequeno','medio','grande') COLLATE utf8mb3_unicode_ci DEFAULT 'medio',
  `tipo_boca` enum('pequena','media','grande') COLLATE utf8mb3_unicode_ci DEFAULT 'media',
  `cor_labios` enum('natural','vermelho','rosa') COLLATE utf8mb3_unicode_ci DEFAULT 'natural',
  `oculos` tinyint(1) DEFAULT '0',
  `tipo_oculos` enum('comum','escuro','leitura') COLLATE utf8mb3_unicode_ci DEFAULT 'comum',
  `barba` tinyint(1) DEFAULT '0',
  `tipo_barba` enum('bigode','cavanhaque','barba_completa') COLLATE utf8mb3_unicode_ci DEFAULT 'bigode',
  `cor_camisa` enum('azul','vermelho','verde','amarelo','preto','branco','cinza') COLLATE utf8mb3_unicode_ci DEFAULT 'azul',
  `estilo_camisa` enum('social','casual','esportiva') COLLATE utf8mb3_unicode_ci DEFAULT 'casual',
  `cor_fundo` enum('azul','verde','roxo','laranja','rosa','cinza') COLLATE utf8mb3_unicode_ci DEFAULT 'azul',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_atividades`
--

CREATE TABLE `ava_atividades` (
  `id` int NOT NULL,
  `disciplina_id` int NOT NULL,
  `modulo_id` int DEFAULT NULL,
  `aula_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `rubrica_id` int DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `instrucoes` mediumtext COLLATE utf8mb4_unicode_ci,
  `tipo_entrega` enum('arquivo','texto','link','multiplo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'arquivo',
  `peso` decimal(5,2) NOT NULL DEFAULT '1.00',
  `nota_maxima` decimal(5,2) NOT NULL DEFAULT '10.00',
  `data_abertura` datetime DEFAULT NULL,
  `data_entrega` datetime DEFAULT NULL,
  `aceita_atraso` tinyint(1) NOT NULL DEFAULT '0',
  `permite_reenvio` tinyint(1) NOT NULL DEFAULT '1',
  `max_arquivos` int NOT NULL DEFAULT '5',
  `tamanho_max_mb` int NOT NULL DEFAULT '20',
  `status` enum('rascunho','publicada','encerrada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publicada',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_atividade_entregas`
--

CREATE TABLE `ava_atividade_entregas` (
  `id` int NOT NULL,
  `atividade_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `texto` mediumtext COLLATE utf8mb4_unicode_ci,
  `link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('rascunho','enviada','avaliada','reenviar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'enviada',
  `nota` decimal(5,2) DEFAULT NULL,
  `feedback` mediumtext COLLATE utf8mb4_unicode_ci,
  `rubrica_resultado_json` json DEFAULT NULL,
  `atrasada` tinyint(1) NOT NULL DEFAULT '0',
  `enviada_em` datetime DEFAULT NULL,
  `avaliada_em` datetime DEFAULT NULL,
  `avaliada_por` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_atividade_entrega_arquivos`
--

CREATE TABLE `ava_atividade_entrega_arquivos` (
  `id` int NOT NULL,
  `entrega_id` int NOT NULL,
  `arquivo_key` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_aulas`
--

CREATE TABLE `ava_aulas` (
  `id` int NOT NULL,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `professor_id` int DEFAULT NULL,
  `tipo` enum('video','texto','pdf','apresentacao','audio','link','html','quiz') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'video',
  `conteudo_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `video_provider` enum('none','mp4','youtube','vimeo','bunny','cloudflare','panda') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `video_ref` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duracao_seg` int NOT NULL DEFAULT '0',
  `imagem_key` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempo_estimado_min` int NOT NULL DEFAULT '0',
  `data_liberacao` datetime DEFAULT NULL,
  `data_encerramento` datetime DEFAULT NULL,
  `obrigatoria` tinyint(1) NOT NULL DEFAULT '1',
  `permite_download` tinyint(1) NOT NULL DEFAULT '0',
  `permite_comentarios` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_aulas_ao_vivo`
--

CREATE TABLE `ava_aulas_ao_vivo` (
  `id` int NOT NULL,
  `disciplina_id` int NOT NULL,
  `modulo_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `plataforma` enum('jitsi','panda','externo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'jitsi',
  `link_externo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inicio_em` datetime DEFAULT NULL,
  `fim_em` datetime DEFAULT NULL,
  `status` enum('agendada','ao_vivo','encerrada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'agendada',
  `panda_live_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_live_player` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_player` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_hls` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panda_recording_synced_at` datetime DEFAULT NULL,
  `gravacao_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_aula_anexos`
--

CREATE TABLE `ava_aula_anexos` (
  `id` int NOT NULL,
  `aula_id` int NOT NULL,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'arquivo',
  `arquivo_key` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_categorias`
--

CREATE TABLE `ava_categorias` (
  `id` int NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_certificados`
--

CREATE TABLE `ava_certificados` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `disciplina_id` int DEFAULT NULL,
  `curso_id` int DEFAULT NULL,
  `tipo` enum('disciplina','curso') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disciplina',
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aluno_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carga_horaria` int NOT NULL DEFAULT '0',
  `nota_final` decimal(5,2) DEFAULT NULL,
  `emitido_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_comentarios`
--

CREATE TABLE `ava_comentarios` (
  `id` int NOT NULL,
  `aula_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `autor_tipo` enum('aluno','professor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aluno',
  `autor_id` int NOT NULL,
  `autor_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conteudo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fixado` tinyint(1) NOT NULL DEFAULT '0',
  `removido` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_cursos`
--

CREATE TABLE `ava_cursos` (
  `id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modalidade` enum('fundamental','medio','tecnico','graduacao','pos','livre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'livre',
  `categoria_id` int DEFAULT NULL,
  `carga_horaria` int NOT NULL DEFAULT '0',
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `objetivos` text COLLATE utf8mb4_unicode_ci,
  `competencias` text COLLATE utf8mb4_unicode_ci,
  `bibliografia` text COLLATE utf8mb4_unicode_ci,
  `certificacao` tinyint(1) NOT NULL DEFAULT '0',
  `imagem_key` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banner_key` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('rascunho','ativo','arquivado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_disciplinas`
--

CREATE TABLE `ava_disciplinas` (
  `id` int NOT NULL,
  `curso_id` int NOT NULL,
  `semestre_id` int DEFAULT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `tutor_id` int DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `carga_horaria` int NOT NULL DEFAULT '0',
  `horas_ead` int NOT NULL DEFAULT '0',
  `horas_presenciais` int NOT NULL DEFAULT '0',
  `ementa` text COLLATE utf8mb4_unicode_ci,
  `objetivos` text COLLATE utf8mb4_unicode_ci,
  `competencias` text COLLATE utf8mb4_unicode_ci,
  `materia_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `status` enum('rascunho','ativo','arquivado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_disciplina_avaliacoes`
--

CREATE TABLE `ava_disciplina_avaliacoes` (
  `id` int NOT NULL,
  `disciplina_id` int NOT NULL,
  `prova_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requisito_progresso_pct` decimal(5,2) NOT NULL DEFAULT '80.00',
  `obrigatoria` tinyint(1) NOT NULL DEFAULT '1',
  `peso` decimal(5,2) NOT NULL DEFAULT '1.00',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_matriculas_disciplina`
--

CREATE TABLE `ava_matriculas_disciplina` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `disciplina_id` int NOT NULL,
  `origem` enum('erp','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `status` enum('ativa','concluida','trancada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativa',
  `progresso_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `nota_final` decimal(5,2) DEFAULT NULL,
  `data_matricula` datetime DEFAULT CURRENT_TIMESTAMP,
  `concluida_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_modulos`
--

CREATE TABLE `ava_modulos` (
  `id` int NOT NULL,
  `disciplina_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `ordem` int NOT NULL DEFAULT '0',
  `status` enum('rascunho','publicado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publicado',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_progresso_aula`
--

CREATE TABLE `ava_progresso_aula` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `aula_id` int NOT NULL,
  `status` enum('nao_iniciada','em_andamento','concluida') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_iniciada',
  `percentual` decimal(5,2) NOT NULL DEFAULT '0.00',
  `concluida_em` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_progresso_video`
--

CREATE TABLE `ava_progresso_video` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `aula_id` int NOT NULL,
  `segundo_atual` int NOT NULL DEFAULT '0',
  `tempo_assistido_seg` int NOT NULL DEFAULT '0',
  `duracao_seg` int NOT NULL DEFAULT '0',
  `percentual` decimal(5,2) NOT NULL DEFAULT '0.00',
  `concluido` tinyint(1) NOT NULL DEFAULT '0',
  `ultimo_acesso` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_rubricas`
--

CREATE TABLE `ava_rubricas` (
  `id` int NOT NULL,
  `disciplina_id` int DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_rubrica_criterios`
--

CREATE TABLE `ava_rubrica_criterios` (
  `id` int NOT NULL,
  `rubrica_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `peso` decimal(5,2) NOT NULL DEFAULT '1.00',
  `pontuacao_max` decimal(5,2) NOT NULL DEFAULT '10.00',
  `ordem` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ava_semestres`
--

CREATE TABLE `ava_semestres` (
  `id` int NOT NULL,
  `curso_id` int NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `backup_enem_questions_20251028`
--

CREATE TABLE `backup_enem_questions_20251028` (
  `id` int NOT NULL DEFAULT '0',
  `exam_id` int NOT NULL,
  `discipline` varchar(150) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `language` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `question_index` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `context` longtext COLLATE utf8mb3_unicode_ci,
  `correct_alternative` char(1) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `alternatives_introduction` text COLLATE utf8mb3_unicode_ci,
  `year` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `billing_message_log`
--

CREATE TABLE `billing_message_log` (
  `id` int NOT NULL,
  `installment_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `responsavel_id` int DEFAULT NULL,
  `canal` enum('app','email','whatsapp') COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_usado` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destinatario` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('enviado','falha','simulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'simulado',
  `erro` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `billing_rule_config`
--

CREATE TABLE `billing_rule_config` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dias_relativo` int NOT NULL,
  `canal` enum('app','email','whatsapp') COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_corpo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `bncc_habilidades`
--

CREATE TABLE `bncc_habilidades` (
  `id` int NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `etapa` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Educação Infantil, Ensino Fundamental, Ensino Médio',
  `componente` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Componente curricular / área',
  `ano_serie` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade_tematica` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objeto_conhecimento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `boletim_componentes`
--

CREATE TABLE `boletim_componentes` (
  `id` int NOT NULL,
  `regra_id` int NOT NULL,
  `codigo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` enum('provas_sistema','manual','jornadas','calculado','evento_boletim','faltas_evento','nenhuma') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'provas_sistema',
  `calc_type` enum('media','soma','maior','ultima') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `peso` decimal(8,3) NOT NULL DEFAULT '1.000',
  `filtro_titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloco_id` int DEFAULT NULL,
  `blocos_ids` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config_json` text COLLATE utf8mb4_unicode_ci,
  `materia_id` int DEFAULT NULL,
  `materias_ids` text COLLATE utf8mb4_unicode_ci,
  `materia_unica` tinyint(1) NOT NULL DEFAULT '0',
  `usar_percentual` tinyint(1) NOT NULL DEFAULT '1',
  `escala_max` decimal(8,2) NOT NULL DEFAULT '10.00',
  `obrigatorio` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `boletim_log_geracoes`
--

CREATE TABLE `boletim_log_geracoes` (
  `id` int NOT NULL,
  `regra_id` int NOT NULL,
  `periodo_ref` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `usuario_nome` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alunos_processados` int NOT NULL DEFAULT '0',
  `linhas_geradas` int NOT NULL DEFAULT '0',
  `erros` int NOT NULL DEFAULT '0',
  `alunos_mudanca_significativa` int NOT NULL DEFAULT '0',
  `detalhes_json` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `boletim_notas_manuais`
--

CREATE TABLE `boletim_notas_manuais` (
  `id` int NOT NULL,
  `regra_id` int NOT NULL,
  `componente_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `materia_id` int NOT NULL DEFAULT '0',
  `periodo_ref` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota` decimal(8,2) NOT NULL,
  `bloqueado` tinyint(1) NOT NULL DEFAULT '0',
  `observacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `boletim_observacoes`
--

CREATE TABLE `boletim_observacoes` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `conteudo` text COLLATE utf8mb4_unicode_ci,
  `updated_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `boletim_regras`
--

CREATE TABLE `boletim_regras` (
  `id` int NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao_curta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formula_final` text COLLATE utf8mb4_unicode_ci,
  `formula_materias_json` text COLLATE utf8mb4_unicode_ci,
  `extras_json` text COLLATE utf8mb4_unicode_ci,
  `materias_ids` text COLLATE utf8mb4_unicode_ci,
  `series_ids` text COLLATE utf8mb4_unicode_ci,
  `turmas_ids` text COLLATE utf8mb4_unicode_ci,
  `exibir_em` enum('notas','boletim') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'boletim',
  `ano_letivo` smallint UNSIGNED DEFAULT NULL,
  `bimestre` tinyint UNSIGNED DEFAULT NULL,
  `nota_minima_aprovacao` decimal(8,2) DEFAULT NULL,
  `usar_resultado_aprovacao` tinyint(1) NOT NULL DEFAULT '1',
  `vis_aluno` tinyint(1) NOT NULL DEFAULT '1',
  `vis_pais` tinyint(1) NOT NULL DEFAULT '1',
  `vis_coordenacao` tinyint(1) NOT NULL DEFAULT '1',
  `round_mode` enum('none','half') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `default_data_inicio` date DEFAULT NULL,
  `default_data_fim` date DEFAULT NULL,
  `decimal_places` tinyint(1) NOT NULL DEFAULT '2',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `boletim_resultados_gerados`
--

CREATE TABLE `boletim_resultados_gerados` (
  `id` int NOT NULL,
  `regra_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `periodo_ref` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `materia_id` int DEFAULT NULL,
  `materia_nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `materia_ref` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem_linha` int NOT NULL DEFAULT '0',
  `colunas_json` text COLLATE utf8mb4_unicode_ci,
  `notas_json` text COLLATE utf8mb4_unicode_ci,
  `media_final` decimal(8,2) DEFAULT NULL,
  `preview` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cadernos_aluno`
--

CREATE TABLE `cadernos_aluno` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `materia_id` int DEFAULT NULL COMMENT 'Matéria relacionada (opcional)',
  `pasta_id` int DEFAULT NULL COMMENT 'Pasta de estudo (opcional)',
  `observacao` text COLLATE utf8mb4_unicode_ci COMMENT 'Texto/anotação livre',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cadernos_aluno_anexos`
--

CREATE TABLE `cadernos_aluno_anexos` (
  `id` int NOT NULL,
  `caderno_id` int NOT NULL,
  `tipo` enum('imagem','documento') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'documento',
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int UNSIGNED DEFAULT NULL COMMENT 'Tamanho em bytes',
  `anotacao_canvas` longtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON do canvas Fabric.js (desenhos, setas, texto)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cadernos_aluno_pastas`
--

CREATE TABLE `cadernos_aluno_pastas` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `calendario_letivo`
--

CREATE TABLE `calendario_letivo` (
  `id` int NOT NULL,
  `ano` int NOT NULL,
  `dias_meta` int NOT NULL DEFAULT '200',
  `carga_horaria_meta` int NOT NULL DEFAULT '800',
  `observacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `calendario_letivo_eventos`
--

CREATE TABLE `calendario_letivo_eventos` (
  `id` int NOT NULL,
  `calendario_id` int NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `tipo` enum('feriado','recesso','reposicao','evento','suspensao','avaliacao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'feriado',
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link_reuniao` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `local_evento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visivel_aluno` tinyint(1) NOT NULL DEFAULT '0',
  `visivel_professor` tinyint(1) NOT NULL DEFAULT '0',
  `visivel_pais` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `carteira_movimentacoes`
--

CREATE TABLE `carteira_movimentacoes` (
  `id` int UNSIGNED NOT NULL,
  `user_type` enum('aluno','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `tipo` enum('recarga_mensal','cortesia','compra','consumo','estorno','recarga_plano','recarga_inicial') COLLATE utf8mb4_unicode_ci NOT NULL,
  `saldo_origem` enum('escola','comprado','misto') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor` decimal(14,4) NOT NULL COMMENT 'Positivo=entrada, negativo=consumo',
  `modulo_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacao` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Detalhe amigável do consumo (ex.: descrição enviada pelo app)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de movimentações da carteira';

-- --------------------------------------------------------

--
-- Estrutura para tabela `carteira_usuarios`
--

CREATE TABLE `carteira_usuarios` (
  `id` int UNSIGNED NOT NULL,
  `user_type` enum('aluno','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `saldo` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `saldo_escola` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `saldo_comprado` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Carteira de créditos por usuário (aluno/professor)';

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_professores_alunos`
--

CREATE TABLE `chat_professores_alunos` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `ultima_mensagem_id` int DEFAULT NULL,
  `ultima_atividade` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_professores_alunos_anexos`
--

CREATE TABLE `chat_professores_alunos_anexos` (
  `id` int NOT NULL,
  `mensagem_id` int NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho_arquivo` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_professores_alunos_mensagens`
--

CREATE TABLE `chat_professores_alunos_mensagens` (
  `id` int NOT NULL,
  `chat_id` int NOT NULL,
  `remetente_tipo` enum('aluno','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `remetente_id` int NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lida` tinyint(1) DEFAULT '0',
  `lida_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `compras_creditos`
--

CREATE TABLE `compras_creditos` (
  `id` int UNSIGNED NOT NULL,
  `user_type` enum('aluno','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `pacote_id` int UNSIGNED NOT NULL,
  `valor_centavos` int UNSIGNED NOT NULL,
  `status` enum('pending','paid','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `gateway_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID do pedido no gateway de pagamento',
  `asaas_payment_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout_url` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_notified_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `billing_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de compras de créditos (pendente até confirmação do gateway)';

-- --------------------------------------------------------

--
-- Estrutura para tabela `config_dev`
--

CREATE TABLE `config_dev` (
  `id` int NOT NULL,
  `key_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `config_escolas_database`
--

CREATE TABLE `config_escolas_database` (
  `id` int NOT NULL,
  `escola_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_port` int DEFAULT '3306',
  `db_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_pass` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ssh_host` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ssh_port` int DEFAULT '22',
  `ssh_user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ssh_pass` text COLLATE utf8mb4_unicode_ci,
  `ssh_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `config_layout`
--

CREATE TABLE `config_layout` (
  `id` int NOT NULL,
  `config_key` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb3_unicode_ci,
  `config_type` enum('color','image','text','number') COLLATE utf8mb3_unicode_ci DEFAULT 'text',
  `description` text COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `config_simulados`
--

CREATE TABLE `config_simulados` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb3_unicode_ci,
  `tempo_limite_padrao` int DEFAULT '1800',
  `quantidade_questoes_padrao` int DEFAULT '10',
  `disciplinas_permitidas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `anos_permitidos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `ativo` tinyint(1) DEFAULT '1',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `curso`
--

CREATE TABLE `curso` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('regular','extra') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'regular' COMMENT 'regular=com série; extra=livre',
  `possui_serie` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0=curso extra sem série',
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cursos`
--

CREATE TABLE `cursos` (
  `id` int NOT NULL,
  `tipo_curso_id` int NOT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `dashboard_jornadas_resumo`
--

CREATE TABLE `dashboard_jornadas_resumo` (
  `id` bigint UNSIGNED NOT NULL,
  `segmento` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jornadas_escopo` int NOT NULL DEFAULT '0',
  `pares_atribuidos` int NOT NULL DEFAULT '0',
  `concluidos` int NOT NULL DEFAULT '0',
  `pendentes` int NOT NULL DEFAULT '0',
  `taxa_conclusao` decimal(5,2) NOT NULL DEFAULT '0.00',
  `atualizado_em` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `diario_aulas`
--

CREATE TABLE `diario_aulas` (
  `id` int NOT NULL,
  `grade_horaria_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `plano_aula_id` int DEFAULT NULL,
  `data_aula` date NOT NULL,
  `horario_de` time NOT NULL,
  `horario_ate` time NOT NULL,
  `execucao` enum('conforme_planejado','parcial','alterado','nao_realizada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'conforme_planejado',
  `conteudo_realizado` text COLLATE utf8mb4_unicode_ci,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('rascunho','finalizada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `finalizada_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `diario_frequencias`
--

CREATE TABLE `diario_frequencias` (
  `id` int NOT NULL,
  `diario_aula_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `situacao` enum('presente','falta','falta_justificada','atraso') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'presente',
  `nota` decimal(5,2) DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `drive_compartilhamentos`
--

CREATE TABLE `drive_compartilhamentos` (
  `id` int UNSIGNED NOT NULL,
  `item_id` int UNSIGNED NOT NULL,
  `shared_with_id` int UNSIGNED NOT NULL,
  `shared_with_type` enum('student','teacher') COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` enum('view','edit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'view',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `drive_itens`
--

CREATE TABLE `drive_itens` (
  `id` int UNSIGNED NOT NULL,
  `owner_id` int UNSIGNED NOT NULL,
  `owner_type` enum('student','teacher') COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('folder','file') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho relativo no storage para arquivos',
  `file_size` bigint UNSIGNED DEFAULT NULL,
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `educalabs_messages`
--

CREATE TABLE `educalabs_messages` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `educalabs_projects`
--

CREATE TABLE `educalabs_projects` (
  `id` int NOT NULL,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `share_id` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `html` longtext COLLATE utf8mb4_unicode_ci,
  `css` longtext COLLATE utf8mb4_unicode_ci,
  `js` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `educalabs_tokens`
--

CREATE TABLE `educalabs_tokens` (
  `id` int NOT NULL,
  `token` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `educa_hits_requests`
--

CREATE TABLE `educa_hits_requests` (
  `id` int NOT NULL,
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
  `archived_at` datetime DEFAULT NULL COMMENT 'Preenchido pelo Master ao arquivar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `educa_hits_tracks`
--

CREATE TABLE `educa_hits_tracks` (
  `id` int NOT NULL,
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
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `educa_hits_track_classes`
--

CREATE TABLE `educa_hits_track_classes` (
  `id` int NOT NULL,
  `track_id` int NOT NULL,
  `class_id` int NOT NULL COMMENT 'turmas.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `educa_hits_track_grades`
--

CREATE TABLE `educa_hits_track_grades` (
  `id` int NOT NULL,
  `track_id` int NOT NULL,
  `grade` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Série (ex: 1º ano, 2º ano)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `educa_hits_track_schools`
--

CREATE TABLE `educa_hits_track_schools` (
  `id` int NOT NULL,
  `track_id` int NOT NULL,
  `school_id` int DEFAULT NULL COMMENT 'NULL = todas as escolas (single-tenant)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `educa_hits_track_users`
--

CREATE TABLE `educa_hits_track_users` (
  `id` int NOT NULL,
  `track_id` int NOT NULL,
  `user_id` int NOT NULL COMMENT 'alunos.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enem_alternativas`
--

CREATE TABLE `enem_alternativas` (
  `id` int NOT NULL,
  `question_id` int NOT NULL,
  `letter` char(1) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `text` text COLLATE utf8mb3_unicode_ci,
  `file` text COLLATE utf8mb3_unicode_ci,
  `is_correct` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enem_disciplinas`
--

CREATE TABLE `enem_disciplinas` (
  `id` int NOT NULL,
  `exam_id` int NOT NULL,
  `label` varchar(150) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `value` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enem_provas`
--

CREATE TABLE `enem_provas` (
  `id` int NOT NULL,
  `title` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `year` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enem_questoes`
--

CREATE TABLE `enem_questoes` (
  `id` int NOT NULL,
  `exam_id` int NOT NULL,
  `discipline` varchar(150) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `language` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `question_index` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `context` longtext COLLATE utf8mb3_unicode_ci,
  `correct_alternative` char(1) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `alternatives_introduction` text COLLATE utf8mb3_unicode_ci,
  `year` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enem_questoes_arquivos`
--

CREATE TABLE `enem_questoes_arquivos` (
  `id` int NOT NULL,
  `question_id` int NOT NULL,
  `file_url` text COLLATE utf8mb3_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enem_questoes_vinculo`
--

CREATE TABLE `enem_questoes_vinculo` (
  `id` int NOT NULL,
  `ano` int NOT NULL,
  `indice` int NOT NULL,
  `disciplina` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `idioma` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `contexto` longtext COLLATE utf8mb3_unicode_ci,
  `enunciado` longtext COLLATE utf8mb3_unicode_ci,
  `alternativas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `correta` char(1) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `imagem` text COLLATE utf8mb3_unicode_ci,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enrollment`
--

CREATE TABLE `enrollment` (
  `id` int NOT NULL,
  `tipo` enum('nova','rematricula','transferencia') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nova',
  `status` enum('rascunho','aguardando_contrato','aguardando_assinatura','confirmada','enturmada','abandonada','cancelada','lista_espera') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `aluno_id` int DEFAULT NULL,
  `ano_letivo_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `serie_id` int DEFAULT NULL,
  `aluno_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `aluno_cpf` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_data_nasc` date DEFAULT NULL,
  `aluno_genero` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aluno_telefone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resp_cpf` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_telefone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_parentesco` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resp_endereco` text COLLATE utf8mb4_unicode_ci,
  `contrato_pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contrato_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contrato_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assinado_em` datetime DEFAULT NULL,
  `assinante_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assinante_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem` enum('interno','site','whatsapp','indicacao','evento') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interno',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `expira_em` datetime DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enrollment_audit`
--

CREATE TABLE `enrollment_audit` (
  `id` int NOT NULL,
  `enrollment_id` int NOT NULL,
  `status_de` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_para` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `acao` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `usuario_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enrollment_score`
--

CREATE TABLE `enrollment_score` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `ciclo` int NOT NULL,
  `score` tinyint NOT NULL DEFAULT '0',
  `faixa` enum('verde','amarelo','vermelho') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'verde',
  `freq_n` tinyint DEFAULT NULL,
  `desemp_n` tinyint DEFAULT NULL,
  `inad_n` tinyint DEFAULT NULL,
  `engaj_n` tinyint DEFAULT NULL,
  `tempo_n` tinyint DEFAULT NULL,
  `motivos` text COLLATE utf8mb4_unicode_ci,
  `calculado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `estatisticas_dama`
--

CREATE TABLE `estatisticas_dama` (
  `id` int NOT NULL,
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
  `nivel_preferido` enum('facil','medio','dificil') COLLATE utf8mb3_unicode_ci DEFAULT 'facil',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `execucao_exercicios`
--

CREATE TABLE `execucao_exercicios` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `data_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` timestamp NULL DEFAULT NULL,
  `tempo_total` int DEFAULT '0',
  `questoes_corretas` int DEFAULT '0',
  `questoes_total` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `status` enum('em_andamento','finalizado','pausado') COLLATE utf8mb4_unicode_ci DEFAULT 'em_andamento',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Execução de lista de exercícios por aluno (AdminController)';

-- --------------------------------------------------------

--
-- Estrutura para tabela `exercicios`
--

CREATE TABLE `exercicios` (
  `id` int NOT NULL,
  `lista_id` int DEFAULT NULL,
  `pergunta` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_b` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_c` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_d` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `resposta_correta` enum('A','B','C','D') COLLATE utf8mb3_unicode_ci NOT NULL,
  `explicacao` text COLLATE utf8mb3_unicode_ci,
  `ordem` int DEFAULT '1',
  `jornada_id` int DEFAULT NULL,
  `enunciado` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `resposta` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('multipla_escolha','dissertativa') COLLATE utf8mb3_unicode_ci NOT NULL,
  `gerado_ia` tinyint(1) DEFAULT '0',
  `aprovado` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `exercicios_estatisticas_alunos`
--

CREATE TABLE `exercicios_estatisticas_alunos` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `total_exercicios` int DEFAULT '0',
  `total_acertos` int DEFAULT '0',
  `total_tempo` int DEFAULT '0',
  `percentual_medio` decimal(5,2) DEFAULT '0.00',
  `ultima_atividade` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `exercicios_estatisticas_turmas`
--

CREATE TABLE `exercicios_estatisticas_turmas` (
  `id` int NOT NULL,
  `turma_id` int NOT NULL,
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `total_alunos` int DEFAULT '0',
  `total_exercicios` int DEFAULT '0',
  `percentual_medio` decimal(5,2) DEFAULT '0.00',
  `tempo_medio` int DEFAULT '0',
  `ultima_atividade` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `exercicios_execucoes`
--

CREATE TABLE `exercicios_execucoes` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `data_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` timestamp NULL DEFAULT NULL,
  `tempo_total` int DEFAULT '0',
  `questoes_corretas` int DEFAULT '0',
  `questoes_total` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `status` enum('em_andamento','finalizado','pausado') COLLATE utf8mb3_unicode_ci DEFAULT 'em_andamento',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `exercicios_historico`
--

CREATE TABLE `exercicios_historico` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `sessao_id` int NOT NULL,
  `data_execucao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tempo_total` int DEFAULT '0',
  `questoes_corretas` int DEFAULT '0',
  `questoes_total` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `status` enum('finalizado','abandonado') COLLATE utf8mb3_unicode_ci DEFAULT 'finalizado',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `exercicios_respostas`
--

CREATE TABLE `exercicios_respostas` (
  `id` int NOT NULL,
  `sessao_id` int NOT NULL,
  `exercicio_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `resposta` enum('A','B','C','D') COLLATE utf8mb3_unicode_ci NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `tempo_resposta` int DEFAULT NULL COMMENT 'Tempo em segundos',
  `answered_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `exercicios_sessoes`
--

CREATE TABLE `exercicios_sessoes` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT NULL COMMENT 'Tempo em minutos'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `facial_devices`
--

CREATE TABLE `facial_devices` (
  `id` bigint NOT NULL,
  `device_uid` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `paired_by_user_id` int DEFAULT NULL,
  `paired_at` datetime NOT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `facial_device_pairing_codes`
--

CREATE TABLE `facial_device_pairing_codes` (
  `id` bigint NOT NULL,
  `code_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `faltas_eventos`
--

CREATE TABLE `faltas_eventos` (
  `id` int NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bimestre` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ano_letivo` int NOT NULL,
  `turmas_json` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `materias_json` text COLLATE utf8mb4_unicode_ci COMMENT 'IDs das matérias em colunas; NULL = grade horária',
  `created_by` int DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `faltas_lancamentos`
--

CREATE TABLE `faltas_lancamentos` (
  `id` int NOT NULL,
  `evento_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `materia_id` int NOT NULL DEFAULT '0',
  `faltas` decimal(6,2) NOT NULL DEFAULT '0.00',
  `observacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `financeiro_valores_mensais`
--

CREATE TABLE `financeiro_valores_mensais` (
  `id` int NOT NULL,
  `mes_referencia` date NOT NULL COMMENT 'Mês de referência (primeiro dia do mês)',
  `total_alunos_pagantes` int NOT NULL DEFAULT '0',
  `total_professores_pagantes` int NOT NULL DEFAULT '0',
  `total_usuarios_pagantes` int NOT NULL DEFAULT '0' COMMENT 'Soma de alunos + professores',
  `valor_por_usuario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_total` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total a pagar no mês',
  `status` enum('aberto','pago') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberto',
  `data_vencimento` date DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `registrado_por` int DEFAULT NULL COMMENT 'ID do usuário que registrou',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_audit`
--

CREATE TABLE `finance_audit` (
  `id` int NOT NULL,
  `entidade` enum('contract','installment','payment','discount') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidade_id` int NOT NULL,
  `acao` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dados_antes` json DEFAULT NULL,
  `dados_depois` json DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `usuario_nome` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_bank_accounts`
--

CREATE TABLE `finance_bank_accounts` (
  `id` int NOT NULL,
  `nome` varchar(80) NOT NULL COMMENT 'Ex: Conta Corrente Bradesco, Caixa Físico',
  `tipo` enum('corrente','poupanca','caixa','investimento') NOT NULL DEFAULT 'corrente',
  `banco_nome` varchar(80) DEFAULT NULL,
  `agencia` varchar(20) DEFAULT NULL,
  `conta` varchar(30) DEFAULT NULL,
  `saldo_inicial` decimal(12,2) NOT NULL DEFAULT '0.00',
  `saldo_atual` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_bills`
--

CREATE TABLE `finance_bills` (
  `id` int NOT NULL,
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
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_charges`
--

CREATE TABLE `finance_charges` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `ano_letivo_id` int DEFAULT NULL,
  `categoria` enum('mensalidade','matricula','material_didatico','uniforme','taxa','passeio','ingresso','evento','outros') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outros',
  `descricao` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `forma_pagamento` enum('dinheiro','pix','boleto','transferencia','cartao','outro') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `juros_aplicado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `multa_aplicada` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pendente','pago','vencido','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `boleto_codigo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `unidade_id` int DEFAULT NULL COMMENT 'Empresa emissora da NF',
  `batch_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_chart_accounts`
--

CREATE TABLE `finance_chart_accounts` (
  `id` int NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `tipo` enum('receita','despesa','ativo','passivo','patrimonio') NOT NULL,
  `grupo` varchar(80) DEFAULT NULL COMMENT 'Agrupamento: ex. Receitas Operacionais, Despesas Administrativas',
  `descricao` text,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_config`
--

CREATE TABLE `finance_config` (
  `id` int NOT NULL,
  `juros_mensal` decimal(5,2) NOT NULL DEFAULT '1.00',
  `multa_atraso` decimal(5,2) NOT NULL DEFAULT '2.00',
  `dias_carencia` tinyint NOT NULL DEFAULT '0',
  `dia_vencimento_padrao` tinyint NOT NULL DEFAULT '10',
  `gerar_debito_auto` tinyint(1) NOT NULL DEFAULT '1',
  `email_remetente` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome_escola_boleto` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `desconto_pontualidade_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `desconto_pontualidade_dia` tinyint NOT NULL DEFAULT '5'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_contracts`
--

CREATE TABLE `finance_contracts` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `matricula_id` int DEFAULT NULL,
  `enrollment_id` int DEFAULT NULL,
  `plan_id` int DEFAULT NULL,
  `ano_letivo_id` int NOT NULL,
  `responsavel_id` int DEFAULT NULL,
  `responsavel_nome` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `responsavel_cpf` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsavel_email` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsavel_telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_bruto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_desconto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_liquido` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('rascunho','ativo','cancelado','encerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `plano_pagamento` enum('mensal','semestral','anual','avulso') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mensal',
  `num_parcelas` int NOT NULL DEFAULT '12',
  `dia_vencimento` tinyint NOT NULL DEFAULT '10',
  `mes_inicio` tinyint NOT NULL DEFAULT '1',
  `mes_fim` tinyint NOT NULL DEFAULT '12',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `criado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_contract_discounts`
--

CREATE TABLE `finance_contract_discounts` (
  `id` int NOT NULL,
  `contract_id` int NOT NULL,
  `discount_rule_id` int DEFAULT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculo` enum('percentual','fixo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentual',
  `valor` decimal(10,2) NOT NULL,
  `valor_aplicado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `irmao_aluno_id` int DEFAULT NULL,
  `aprovado_por` int DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `status` enum('pendente','aprovado','rejeitado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aprovado',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_contract_items`
--

CREATE TABLE `finance_contract_items` (
  `id` int NOT NULL,
  `contract_id` int NOT NULL,
  `plan_item_id` int DEFAULT NULL,
  `price_table_id` int DEFAULT NULL,
  `categoria` enum('mensalidade','matricula','material_didatico','uniforme','taxa','outros') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `nome_instituicao` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade_id` int DEFAULT NULL,
  `status` enum('ativo','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_discount_rules`
--

CREATE TABLE `finance_discount_rules` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('bolsa','irmaos','convenio','funcionario','manual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculo` enum('percentual','fixo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentual',
  `valor` decimal(10,2) NOT NULL,
  `acumulavel` tinyint(1) NOT NULL DEFAULT '0',
  `limite_acumulado` decimal(10,2) DEFAULT NULL,
  `categorias_aplicaveis` json DEFAULT NULL,
  `requer_aprovacao` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_installments`
--

CREATE TABLE `finance_installments` (
  `id` int NOT NULL,
  `contract_id` int NOT NULL,
  `contract_item_id` int DEFAULT NULL,
  `num_parcela` int NOT NULL,
  `categoria` enum('mensalidade','matricula','material_didatico','uniforme','taxa','outros') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_nominal` decimal(10,2) NOT NULL,
  `valor_desconto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_cobrado` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `juros_aplicado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `multa_aplicada` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pendente','pago','vencido','cancelado','renegociado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `boleto_codigo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boleto_gerado_em` datetime DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_ledger`
--

CREATE TABLE `finance_ledger` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `tipo` enum('debito','credito','estorno','ajuste') COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outros',
  `descricao` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `saldo_acumulado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `data_lancamento` date NOT NULL,
  `referencia_tipo` enum('installment','charge','payment','estorno','ajuste','manual') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` int DEFAULT NULL,
  `contract_id` int DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `gerado_auto` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_payments`
--

CREATE TABLE `finance_payments` (
  `id` int NOT NULL,
  `installment_id` int NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_pagamento` date NOT NULL,
  `forma_pagamento` enum('dinheiro','pix','boleto','transferencia','cartao','outro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `registrado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_plans`
--

CREATE TABLE `finance_plans` (
  `id` int NOT NULL,
  `nome` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `ano_letivo_id` int NOT NULL,
  `serie_id` int DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_plan_items`
--

CREATE TABLE `finance_plan_items` (
  `id` int NOT NULL,
  `plan_id` int NOT NULL,
  `categoria` enum('mensalidade','matricula','material_didatico','uniforme','taxa','outros') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_base` decimal(10,2) NOT NULL DEFAULT '0.00',
  `num_parcelas` int NOT NULL DEFAULT '1',
  `mes_inicio` tinyint NOT NULL DEFAULT '1',
  `mes_fim` tinyint DEFAULT NULL,
  `dia_vencimento` tinyint DEFAULT NULL,
  `fornecedor_externo` tinyint(1) NOT NULL DEFAULT '0',
  `nome_instituicao` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade_id` int DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_price_table`
--

CREATE TABLE `finance_price_table` (
  `id` int NOT NULL,
  `ano_letivo_id` int NOT NULL,
  `serie_id` int DEFAULT NULL,
  `curso_id` int DEFAULT NULL,
  `categoria` enum('mensalidade','matricula','material_didatico','uniforme','taxa','outros') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_base` decimal(10,2) NOT NULL DEFAULT '0.00',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_receipts`
--

CREATE TABLE `finance_receipts` (
  `id` int NOT NULL,
  `payment_id` int DEFAULT NULL,
  `charge_id` int DEFAULT NULL,
  `aluno_id` int NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_pagamento` date NOT NULL,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enviado_email` tinyint(1) NOT NULL DEFAULT '0',
  `enviado_wpp` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `finance_renegotiations`
--

CREATE TABLE `finance_renegotiations` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `valor_total_divida` decimal(10,2) NOT NULL,
  `valor_entrada` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_parcelado` decimal(10,2) NOT NULL,
  `num_parcelas` int NOT NULL DEFAULT '1',
  `dia_vencimento` tinyint NOT NULL DEFAULT '10',
  `mes_inicio` tinyint NOT NULL,
  `ano_inicio` int NOT NULL,
  `status` enum('ativo','quitado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `criado_por` int DEFAULT NULL,
  `aprovado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `flashcards_baralhos`
--

CREATE TABLE `flashcards_baralhos` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `topic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `flashcards_cartas`
--

CREATE TABLE `flashcards_cartas` (
  `id` int NOT NULL,
  `deck_id` int NOT NULL,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `flashcards_modelos`
--

CREATE TABLE `flashcards_modelos` (
  `id` int NOT NULL,
  `topic_normalized` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `flashcards_modelos_cartas`
--

CREATE TABLE `flashcards_modelos_cartas` (
  `id` int NOT NULL,
  `template_id` int NOT NULL,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `flashcard_explicacoes`
--

CREATE TABLE `flashcard_explicacoes` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `deck_id` int NOT NULL,
  `card_id` int NOT NULL,
  `explicacao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `origem` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ia' COMMENT 'Origem da explicação: ia',
  `numero_tentativa` int NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `forum_anexos`
--

CREATE TABLE `forum_anexos` (
  `id` int NOT NULL,
  `topic_id` int DEFAULT NULL,
  `reply_id` int DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL DEFAULT 'application/octet-stream',
  `file_size` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `forum_denuncias`
--

CREATE TABLE `forum_denuncias` (
  `id` int NOT NULL,
  `topic_id` int DEFAULT NULL,
  `reply_id` int DEFAULT NULL,
  `reporter_id` int NOT NULL,
  `reporter_role` varchar(20) NOT NULL DEFAULT 'student',
  `reason` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `forum_moderacao_alertas`
--

CREATE TABLE `forum_moderacao_alertas` (
  `id` int NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'topic ou reply',
  `topic_id` int DEFAULT NULL,
  `reply_id` int DEFAULT NULL,
  `author_id` int NOT NULL,
  `author_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `content_preview` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo_ia` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending, visto',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `forum_respostas`
--

CREATE TABLE `forum_respostas` (
  `id` int NOT NULL,
  `topic_id` int NOT NULL,
  `author_id` int NOT NULL,
  `author_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_best_answer` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `forum_topicos`
--

CREATE TABLE `forum_topicos` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_id` int NOT NULL,
  `author_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `subject_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `forum_topicos_turmas`
--

CREATE TABLE `forum_topicos_turmas` (
  `topic_id` int NOT NULL,
  `turma_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `forum_usuarios_reputacao`
--

CREATE TABLE `forum_usuarios_reputacao` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `user_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `points` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `forum_votos`
--

CREATE TABLE `forum_votos` (
  `id` int NOT NULL,
  `reply_id` int NOT NULL,
  `voter_id` int NOT NULL,
  `voter_role` varchar(20) NOT NULL DEFAULT 'student',
  `vote_type` varchar(10) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `grade_horaria`
--

CREATE TABLE `grade_horaria` (
  `id` int NOT NULL,
  `dia_semana` tinyint NOT NULL COMMENT '1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado, 7=Domingo',
  `horario_de` time NOT NULL,
  `horario_ate` time NOT NULL,
  `turma_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `periodo` enum('manha','tarde') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manha',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ingles_conversas`
--

CREATE TABLE `ingles_conversas` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ingles_mensagens`
--

CREATE TABLE `ingles_mensagens` (
  `id` int NOT NULL,
  `conversa_id` int NOT NULL,
  `role` enum('user','assistant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int NOT NULL,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidade_medida` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'un',
  `categoria` enum('limpeza','escritorio','didatico','merenda','higiene','laboratorio','outro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `estoque_minimo` decimal(12,3) NOT NULL DEFAULT '0.000',
  `estoque_maximo` decimal(12,3) DEFAULT NULL,
  `ponto_reposicao` decimal(12,3) NOT NULL DEFAULT '0.000',
  `custo_medio` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `inventory_lots`
--

CREATE TABLE `inventory_lots` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `lote` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validade` date DEFAULT NULL,
  `quantidade_atual` decimal(12,3) NOT NULL DEFAULT '0.000',
  `custo_unitario` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `entrada_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `warehouse_destino_id` int DEFAULT NULL,
  `lot_id` int DEFAULT NULL,
  `tipo` enum('entrada','saida','transferencia','ajuste','baixa') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `custo_unitario` decimal(12,4) DEFAULT NULL,
  `documento` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fornecedor_id` int DEFAULT NULL,
  `requisicao_id` int DEFAULT NULL,
  `setor` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `realizado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `inventory_requisitions`
--

CREATE TABLE `inventory_requisitions` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `setor` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `solicitante_nome` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `justificativa` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pendente','aprovada','atendida','rejeitada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `solicitado_por` int DEFAULT NULL,
  `aprovado_por` int DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `atendido_por` int DEFAULT NULL,
  `atendido_em` datetime DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `inventory_suppliers`
--

CREATE TABLE `inventory_suppliers` (
  `id` int NOT NULL,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnpj` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contato` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `inventory_warehouses`
--

CREATE TABLE `inventory_warehouses` (
  `id` int NOT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('central','cantina','laboratorio','limpeza','secretaria','outro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'central',
  `location_id` int DEFAULT NULL,
  `responsavel_nome` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jogos_acoes`
--

CREATE TABLE `jogos_acoes` (
  `id` int NOT NULL,
  `partida_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` datetime NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jogos_milhao_partidas`
--

CREATE TABLE `jogos_milhao_partidas` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `pontuacao_atual` decimal(10,2) DEFAULT '0.00',
  `pergunta_atual` int DEFAULT '1',
  `ajudas_usadas` json DEFAULT NULL,
  `status` enum('em_andamento','finalizada','abandonada') COLLATE utf8mb3_unicode_ci DEFAULT 'em_andamento',
  `data_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` timestamp NULL DEFAULT NULL,
  `premio_final` decimal(10,2) DEFAULT '0.00',
  `perguntas_usadas` text COLLATE utf8mb3_unicode_ci,
  `last_activity` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jogos_milhao_perguntas`
--

CREATE TABLE `jogos_milhao_perguntas` (
  `id` int NOT NULL,
  `pergunta` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a` varchar(500) COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_b` varchar(500) COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_c` varchar(500) COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_d` varchar(500) COLLATE utf8mb3_unicode_ci NOT NULL,
  `resposta_correta` enum('A','B','C','D') COLLATE utf8mb3_unicode_ci NOT NULL,
  `explicacao` text COLLATE utf8mb3_unicode_ci,
  `nivel_dificuldade` enum('facil','medio','dificil') COLLATE utf8mb3_unicode_ci NOT NULL,
  `tema` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `ativa` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jogos_milhao_respostas`
--

CREATE TABLE `jogos_milhao_respostas` (
  `id` int NOT NULL,
  `partida_id` int NOT NULL,
  `pergunta_id` int NOT NULL,
  `resposta_escolhida` enum('A','B','C','D') COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `resposta_correta` enum('A','B','C','D') COLLATE utf8mb3_unicode_ci NOT NULL,
  `acertou` tinyint(1) NOT NULL,
  `ajuda_usada` enum('plateia','universitarios','pular','nenhuma') COLLATE utf8mb3_unicode_ci DEFAULT 'nenhuma',
  `tempo_resposta` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jogos_sessoes`
--

CREATE TABLE `jogos_sessoes` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `partida_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jogos_tokens_externos`
--

CREATE TABLE `jogos_tokens_externos` (
  `id` int NOT NULL,
  `token` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas`
--

CREATE TABLE `jornadas` (
  `id` int NOT NULL,
  `professor_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `materia_id` int DEFAULT NULL,
  `ano_letivo` smallint UNSIGNED DEFAULT NULL COMMENT 'Ano letivo da jornada',
  `bimestre` tinyint UNSIGNED DEFAULT NULL COMMENT 'Bimestre 1 a 4 da jornada',
  `avaliativo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Sempre 1 (sim)',
  `plano_aula_id` int DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT 'ativa',
  `titulo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb3_unicode_ci,
  `estrutura` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Estrutura da jornada (resumo, exercícios, dúvidas)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ativo` tinyint(1) DEFAULT '1'
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_aulas`
--

CREATE TABLE `jornadas_aulas` (
  `id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `nome_aula` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resumo_oficial` text COLLATE utf8mb4_unicode_ci,
  `pontos_principais` text COLLATE utf8mb4_unicode_ci,
  `conteudos_adicionais` text COLLATE utf8mb4_unicode_ci,
  `status` enum('ativa','pausada','finalizada') COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_blocos_conteudo`
--

CREATE TABLE `jornadas_blocos_conteudo` (
  `id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `tipo_bloco_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `conteudo` longtext COLLATE utf8mb4_unicode_ci,
  `ordem` int NOT NULL DEFAULT '0',
  `obrigatorio` tinyint(1) DEFAULT '1',
  `tempo_estimado` int DEFAULT NULL COMMENT 'Tempo em minutos',
  `status` enum('ativo','inativo','rascunho') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `configuracoes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Configurações específicas do bloco',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_duvidas`
--

CREATE TABLE `jornadas_duvidas` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `aula_id` int NOT NULL,
  `duvida` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `resposta` text COLLATE utf8mb4_unicode_ci,
  `respondido_por` int DEFAULT NULL,
  `respondido_em` timestamp NULL DEFAULT NULL,
  `status` enum('pendente','respondida','arquivada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_exercicios`
--

CREATE TABLE `jornadas_exercicios` (
  `id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `aula_id` int DEFAULT NULL,
  `tipo` enum('manual','ia_gerado','ia_aprovado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `questoes_json` longtext COLLATE utf8mb4_unicode_ci,
  `status` enum('rascunho','aprovado','publicado') COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `aprovado_por` int DEFAULT NULL,
  `aprovado_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_exercicios_auditoria`
--

CREATE TABLE `jornadas_exercicios_auditoria` (
  `id` bigint UNSIGNED NOT NULL,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `modulo_id` int NOT NULL,
  `exercicio_id` int NOT NULL,
  `tipo_acao` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `de_valor` text COLLATE utf8mb4_unicode_ci,
  `para_valor` text COLLATE utf8mb4_unicode_ci,
  `resposta_final` text COLLATE utf8mb4_unicode_ci,
  `correto` tinyint(1) DEFAULT NULL,
  `pontuacao` decimal(10,2) DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detalhes_json` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_materias`
--

CREATE TABLE `jornadas_materias` (
  `id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `cor` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#3B82F6',
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'book',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_mensagens`
--

CREATE TABLE `jornadas_mensagens` (
  `id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `remetente_tipo` enum('aluno','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `remetente_id` int NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lida` tinyint(1) DEFAULT '0',
  `lida_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_mensagens_anexos`
--

CREATE TABLE `jornadas_mensagens_anexos` (
  `id` int NOT NULL,
  `mensagem_id` int NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho_arquivo` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_modulos`
--

CREATE TABLE `jornadas_modulos` (
  `id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `aula_id` int DEFAULT NULL,
  `tipo_modulo` enum('resumo_aluno','resumo_professor','duvidas_ia','redacao','exercicios','sugestoes','video','dica_professor','conteudo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `ordem` int NOT NULL DEFAULT '1',
  `obrigatorio` tinyint(1) DEFAULT '0',
  `status` enum('ativo','inativo') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_modulos_documentos`
--

CREATE TABLE `jornadas_modulos_documentos` (
  `id` int NOT NULL,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_tamanho` int DEFAULT NULL,
  `tipo_arquivo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `status` enum('ativo','inativo') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_modulos_exercicios`
--

CREATE TABLE `jornadas_modulos_exercicios` (
  `id` int NOT NULL,
  `modulo_id` int NOT NULL,
  `tipo` enum('alternativas','verdadeiro_falso','dissertativa','preencher_lacuna') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alternativas',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enunciado` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'HTML from CKEditor (sanitized)',
  `questoes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'JSON with opcoes[].texto as HTML',
  `resposta_correta` text COLLATE utf8mb4_unicode_ci,
  `gabarito` text COLLATE utf8mb4_unicode_ci,
  `pontuacao` decimal(5,2) DEFAULT '1.00',
  `ordem` int NOT NULL DEFAULT '1',
  `gerado_ia` tinyint(1) DEFAULT '0',
  `status` enum('rascunho','publicado','arquivado') COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `imagem_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL da imagem do enunciado (upload ou colagem)',
  `nivel_dificuldade` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'facil, medio, dificil'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_modulos_textos`
--

CREATE TABLE `jornadas_modulos_textos` (
  `id` int NOT NULL,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text COLLATE utf8mb4_unicode_ci,
  `ordem` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_modulos_videos`
--

CREATE TABLE `jornadas_modulos_videos` (
  `id` int NOT NULL,
  `modulo_id` int NOT NULL,
  `tipo` enum('youtube','upload','link_externo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'youtube',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `url_youtube` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_video` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_tamanho` int DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `status` enum('ativo','inativo') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_progresso_alunos`
--

CREATE TABLE `jornadas_progresso_alunos` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `aula_id` int DEFAULT NULL,
  `modulo_id` int DEFAULT NULL,
  `exercicio_id` int DEFAULT NULL,
  `exercicio_modulo_id` int DEFAULT NULL,
  `atividade_tipo` enum('aula','exercicio','resumo','duvida','modulo','exercicio_modulo','jornada_concluida','visualizacao') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tempo_gasto` int DEFAULT '0',
  `status` enum('iniciado','em_andamento','concluido','pausado') COLLATE utf8mb4_unicode_ci DEFAULT 'iniciado',
  `pontuacao` decimal(5,2) DEFAULT '0.00',
  `resposta` text COLLATE utf8mb4_unicode_ci COMMENT 'Resposta do aluno (pode ser JSON)',
  `data_inicio` timestamp NULL DEFAULT NULL,
  `data_conclusao` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_progresso_blocos`
--

CREATE TABLE `jornadas_progresso_blocos` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `bloco_id` int NOT NULL,
  `status` enum('nao_iniciado','em_andamento','concluido','bloqueado') COLLATE utf8mb4_unicode_ci DEFAULT 'nao_iniciado',
  `data_inicio` timestamp NULL DEFAULT NULL,
  `data_conclusao` timestamp NULL DEFAULT NULL,
  `tempo_gasto` int DEFAULT NULL COMMENT 'Tempo em minutos',
  `tentativas` int DEFAULT '0',
  `pontuacao` decimal(5,2) DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `dados_progresso` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Dados específicos do progresso',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_redacoes`
--

CREATE TABLE `jornadas_redacoes` (
  `id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `aula_id` int DEFAULT NULL COMMENT 'Aula específica da jornada (opcional)',
  `professor_id` int NOT NULL COMMENT 'Professor que sugeriu o tema',
  `tema_sugerido` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tema sugerido pelo professor',
  `descricao_tema` text COLLATE utf8mb4_unicode_ci COMMENT 'Descrição detalhada do tema',
  `imagem_tema` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho da imagem do tema',
  `documento_tema` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho do documento do tema (PDF, DOC, DOCX, etc.)',
  `status` enum('pendente','em_andamento','entregue','corrigida','retornada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `data_limite` datetime DEFAULT NULL COMMENT 'Data limite para entrega',
  `correcao_ia_automatica` tinyint(1) DEFAULT '1' COMMENT 'Se 1, corrige automaticamente pela IA quando o aluno finaliza. Se 0, não corrige automaticamente.',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_redacoes_alunos`
--

CREATE TABLE `jornadas_redacoes_alunos` (
  `id` int NOT NULL,
  `jornada_redacao_id` int NOT NULL COMMENT 'ID da redação da jornada',
  `redacao_id` int NOT NULL COMMENT 'ID da redação do aluno',
  `aluno_id` int NOT NULL COMMENT 'ID do aluno',
  `versao` int DEFAULT '1' COMMENT 'Versão da redação (1, 2, 3...)',
  `status` enum('rascunho','entregue','corrigida_ia','corrigida_professor','retornada','aprovada') COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `correcao_ia_feita` tinyint(1) DEFAULT '0' COMMENT 'Indica se a IA já corrigiu',
  `correcao_professor_feita` tinyint(1) DEFAULT '0' COMMENT 'Indica se o professor já corrigiu',
  `usar_correcao_professor` tinyint(1) DEFAULT '0' COMMENT 'Professor escolheu usar sua correção ao invés da IA',
  `retornada_para_reescrever` tinyint(1) DEFAULT '0' COMMENT 'Indica se foi retornada para reescrever',
  `observacoes_professor` text COLLATE utf8mb4_unicode_ci COMMENT 'Observações do professor para o aluno',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usar_media_notas` tinyint(1) DEFAULT '0' COMMENT 'Usar média entre nota do professor e IA',
  `nota_media` decimal(5,2) DEFAULT NULL COMMENT 'Média calculada entre nota do professor e IA',
  `nota_final_utilizada` decimal(5,2) DEFAULT NULL COMMENT 'Nota final que será utilizada (professor, IA ou média)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_relatorios`
--

CREATE TABLE `jornadas_relatorios` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `tempo_total` int DEFAULT '0',
  `aulas_concluidas` int DEFAULT '0',
  `exercicios_concluidos` int DEFAULT '0',
  `resumos_feitos` int DEFAULT '0',
  `duvidas_enviadas` int DEFAULT '0',
  `pontuacao_total` decimal(5,2) DEFAULT '0.00',
  `percentual_conclusao` decimal(5,2) DEFAULT '0.00',
  `relatorio_detalhado` longtext COLLATE utf8mb4_unicode_ci,
  `gerado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_resumos_alunos`
--

CREATE TABLE `jornadas_resumos_alunos` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `jornada_id` int DEFAULT NULL,
  `aula_id` int DEFAULT NULL,
  `modulo_id` int DEFAULT NULL,
  `resumo_aluno` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `analise_ia` text COLLATE utf8mb4_unicode_ci,
  `lacunas_identificadas` text COLLATE utf8mb4_unicode_ci,
  `explicacoes_complementares` text COLLATE utf8mb4_unicode_ci,
  `pontuacao` decimal(5,2) DEFAULT '0.00',
  `status` enum('em_analise','analisado','revisado') COLLATE utf8mb4_unicode_ci DEFAULT 'em_analise',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `observacoes_professor` text COLLATE utf8mb4_unicode_ci COMMENT 'Observações do professor (podem ser exibidas ao aluno)',
  `nota` decimal(4,2) DEFAULT NULL COMMENT 'Nota 0 a 10 do professor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_tempo_alunos`
--

CREATE TABLE `jornadas_tempo_alunos` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `data_inicio` datetime NOT NULL COMMENT 'Quando o aluno começou o primeiro módulo',
  `data_fim` datetime NOT NULL COMMENT 'Quando o aluno finalizou o último módulo',
  `tempo_total_segundos` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Diferença em segundos entre data_fim e data_inicio',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_tipos_blocos`
--

CREATE TABLE `jornadas_tipos_blocos` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cor` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem_padrao` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jornadas_tudinha_explicacao_exercicio`
--

CREATE TABLE `jornadas_tudinha_explicacao_exercicio` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `exercicio_modulo_id` int NOT NULL,
  `fonte_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 dos dados da questão e da resposta do aluno',
  `explicacao_html` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `listas_exercicios`
--

CREATE TABLE `listas_exercicios` (
  `id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `nivel_dificuldade` enum('Fácil','Médio','Difícil') COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb3_unicode_ci,
  `criado_por` int NOT NULL,
  `tipo_usuario` enum('admin','professor') COLLATE utf8mb3_unicode_ci NOT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `listas_exercicios_personalizadas`
--

CREATE TABLE `listas_exercicios_personalizadas` (
  `id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb3_unicode_ci,
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `nivel_dificuldade` enum('Fácil','Médio','Difícil') COLLATE utf8mb3_unicode_ci NOT NULL,
  `questoes_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `criado_por` int NOT NULL,
  `tipo_usuario` enum('admin','professor') COLLATE utf8mb3_unicode_ci NOT NULL,
  `turma_id` int DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `listas_personalizadas_exercicios`
--

CREATE TABLE `listas_personalizadas_exercicios` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tema` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: Reforma Protestante',
  `materia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade_exercicios` int NOT NULL,
  `niveis_selecionados` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'fácil,médio,difícil ou combinações',
  `status` enum('gerando','concluido','erro') COLLATE utf8mb4_unicode_ci DEFAULT 'gerando',
  `mensagem_erro` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `listas_personalizadas_respostas`
--

CREATE TABLE `listas_personalizadas_respostas` (
  `id` int NOT NULL,
  `sessao_id` int NOT NULL,
  `questao_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `resposta` enum('A','B','C','D','E') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `tempo_resposta` int DEFAULT NULL,
  `answered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `listas_personalizadas_sessoes`
--

CREATE TABLE `listas_personalizadas_sessoes` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT NULL,
  `status` enum('em_andamento','finalizado','abandonado') COLLATE utf8mb4_unicode_ci DEFAULT 'em_andamento'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_auditoria`
--

CREATE TABLE `logs_auditoria` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `user_role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_accessed` text COLLATE utf8mb4_unicode_ci,
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_senhas`
--

CREATE TABLE `logs_senhas` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `alterado_por` int NOT NULL,
  `motivo` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_uso_llm`
--

CREATE TABLE `logs_uso_llm` (
  `id` bigint UNSIGNED NOT NULL,
  `model` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Modelo OpenAI (ex: gpt-4o, gpt-4o-mini)',
  `prompt_tokens` int UNSIGNED NOT NULL DEFAULT '0',
  `completion_tokens` int UNSIGNED NOT NULL DEFAULT '0',
  `total_tokens` int UNSIGNED NOT NULL DEFAULT '0',
  `cost_usd` decimal(12,6) NOT NULL DEFAULT '0.000000',
  `usage_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general' COMMENT 'ex: exercicios, chat, correcao_redacao, prova, gerar_tema, chat_completion',
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api' COMMENT 'api = chamada real; backfill = importado',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `log_validacao_apps_externos`
--

CREATE TABLE `log_validacao_apps_externos` (
  `id` int UNSIGNED NOT NULL,
  `app` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'games, educalabs, notes, ou app key',
  `evento` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'validate.fail' COMMENT 'validate.fail, token_expirado, etc',
  `detalhes` json DEFAULT NULL COMMENT 'slug, diagnostic, host, uri, etc',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de falhas ao validar token de apps externos (master ver em Apps Externos)';

-- --------------------------------------------------------

--
-- Estrutura para tabela `materias`
--

CREATE TABLE `materias` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `matricula`
--

CREATE TABLE `matricula` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `ano_letivo_id` int NOT NULL,
  `data_entrada` date NOT NULL,
  `data_saida` date DEFAULT NULL,
  `status` enum('ativa','transferido','concluido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativa',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `migrations_executadas`
--

CREATE TABLE `migrations_executadas` (
  `id` int NOT NULL,
  `escola_database_config_id` int NOT NULL,
  `migration_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `executada_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `executado_por` int DEFAULT NULL,
  `status` enum('sucesso','erro') COLLATE utf8mb4_unicode_ci DEFAULT 'sucesso',
  `mensagem_erro` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `minicursos`
--

CREATE TABLE `minicursos` (
  `id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `imagem_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagem_caminho` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho do upload',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` smallint UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `minicursos_arquivos`
--

CREATE TABLE `minicursos_arquivos` (
  `id` int NOT NULL,
  `minicurso_id` int NOT NULL,
  `tipo` enum('upload','link') COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` smallint UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `minicursos_aulas`
--

CREATE TABLE `minicursos_aulas` (
  `id` int NOT NULL,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('video','slides','pdf','link','texto') COLLATE utf8mb4_unicode_ci NOT NULL,
  `url_ou_caminho` text COLLATE utf8mb4_unicode_ci COMMENT 'URL ou caminho; vazio se tipo=texto',
  `link_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Texto do botão para tipo link',
  `conteudo_html` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Conteúdo HTML (tipo texto)',
  `duracao_minutos` int UNSIGNED DEFAULT NULL,
  `ordem` smallint UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `minicursos_modulos`
--

CREATE TABLE `minicursos_modulos` (
  `id` int NOT NULL,
  `minicurso_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `ordem` smallint UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `minicursos_progresso`
--

CREATE TABLE `minicursos_progresso` (
  `aluno_id` int NOT NULL,
  `minicurso_id` int NOT NULL,
  `aulas_vistas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Array de aula_id já visualizadas',
  `concluido_em` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mobile_auth_sessions`
--

CREATE TABLE `mobile_auth_sessions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int NOT NULL,
  `refresh_token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mobile_devices`
--

CREATE TABLE `mobile_devices` (
  `id` bigint UNSIGNED NOT NULL,
  `parent_id` int NOT NULL,
  `device_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fcm_token` varchar(4096) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'android',
  `app_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modulos_apostilas`
--

CREATE TABLE `modulos_apostilas` (
  `id` int NOT NULL,
  `turma_id` int NOT NULL,
  `curso_id` int DEFAULT NULL,
  `materia_id` int DEFAULT NULL,
  `professor_id` int DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `visibilidade` enum('aluno','professor','ambos') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ambos',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modulos_apostilas_anexos`
--

CREATE TABLE `modulos_apostilas_anexos` (
  `id` int NOT NULL,
  `modulo_apostila_id` int NOT NULL,
  `caminho` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extensao` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` bigint DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modulos_apostilas_turmas`
--

CREATE TABLE `modulos_apostilas_turmas` (
  `id` int NOT NULL,
  `modulo_apostila_id` int NOT NULL,
  `turma_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modulos_arquivos`
--

CREATE TABLE `modulos_arquivos` (
  `id` int NOT NULL,
  `turma_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `professor_id` int DEFAULT NULL,
  `aluno_id` int DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `pasta_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modulos_arquivos_anexos`
--

CREATE TABLE `modulos_arquivos_anexos` (
  `id` int NOT NULL,
  `modulo_arquivo_id` int NOT NULL,
  `caminho` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Caminho relativo no servidor (ex: public/uploads/arquivos/xxx.pdf)',
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extensao` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanho` int UNSIGNED DEFAULT '0',
  `ordem` smallint UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modulos_arquivos_pastas`
--

CREATE TABLE `modulos_arquivos_pastas` (
  `id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6366f1',
  `professor_id` int DEFAULT NULL COMMENT 'NULL = pasta criada pelo admin',
  `criado_por_tipo` enum('professor','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'professor',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pastas de organização do módulo de arquivos';

-- --------------------------------------------------------

--
-- Estrutura para tabela `modulos_arquivos_turmas`
--

CREATE TABLE `modulos_arquivos_turmas` (
  `modulo_arquivo_id` int NOT NULL,
  `turma_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modulos_arquivos_videos`
--

CREATE TABLE `modulos_arquivos_videos` (
  `id` int NOT NULL,
  `modulo_arquivo_id` int NOT NULL,
  `url` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL do vídeo (YouTube, Vimeo, ou link direto)',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links de vídeo (YouTube, Vimeo) por publicação de arquivos';

-- --------------------------------------------------------

--
-- Estrutura para tabela `monitores`
--

CREATE TABLE `monitores` (
  `id` int UNSIGNED NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `turmas` json DEFAULT NULL COMMENT 'IDs das turmas que o monitor pode acompanhar',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `monitor_acoes_log`
--

CREATE TABLE `monitor_acoes_log` (
  `id` bigint UNSIGNED NOT NULL,
  `monitor_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED DEFAULT NULL,
  `acao` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalhes` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mural_recados`
--

CREATE TABLE `mural_recados` (
  `id` int UNSIGNED NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text COLLATE utf8mb4_unicode_ci,
  `autor_tipo` enum('professor','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'professor',
  `autor_id` int UNSIGNED NOT NULL COMMENT 'professor_id ou usuario id (admin)',
  `materia_id` int UNSIGNED DEFAULT NULL,
  `enviar_para_todos` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = todas as turmas',
  `data_publicacao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sai_mural` date NOT NULL COMMENT 'Prazo máximo 30 dias a partir de data_publicacao',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mural_recados_anexos`
--

CREATE TABLE `mural_recados_anexos` (
  `id` int UNSIGNED NOT NULL,
  `mural_recado_id` int UNSIGNED NOT NULL,
  `arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Caminho relativo no servidor',
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME type',
  `tamanho` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mural_recados_turmas`
--

CREATE TABLE `mural_recados_turmas` (
  `id` int UNSIGNED NOT NULL,
  `mural_recado_id` int UNSIGNED NOT NULL,
  `turma_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mural_recados_vistos`
--

CREATE TABLE `mural_recados_vistos` (
  `id` int UNSIGNED NOT NULL,
  `mural_recado_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL,
  `visto_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notes_tokens`
--

CREATE TABLE `notes_tokens` (
  `id` int NOT NULL,
  `token` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `noticias`
--

CREATE TABLE `noticias` (
  `id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `imagem` text COLLATE utf8mb4_unicode_ci,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fonte` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_publicacao` datetime DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` int NOT NULL,
  `tipo_conteudo` enum('texto','mensagem','video') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'texto',
  `tipos_conteudo` text COLLATE utf8mb4_unicode_ci COMMENT 'Tipos de conteúdo selecionados (texto,imagem,video)',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivo_tamanho` int DEFAULT NULL,
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL do vídeo (YouTube, Vimeo, etc.)',
  `enviado_por` int NOT NULL,
  `tipo_enviador` enum('admin','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `perfil_enviador` enum('dev','diretor','coordenador','professor') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prioridade` enum('baixa','normal','alta','urgente') COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `data_envio` datetime DEFAULT NULL,
  `data_expiracao` datetime DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `is_update` tinyint(1) DEFAULT '0' COMMENT 'Se é uma notificação de atualização do sistema',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes_api`
--

CREATE TABLE `notificacoes_api` (
  `id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci,
  `imagem` text COLLATE utf8mb4_unicode_ci,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes_configuracoes`
--

CREATE TABLE `notificacoes_configuracoes` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `tipo_usuario` enum('admin','professor','aluno','pai') COLLATE utf8mb4_unicode_ci NOT NULL,
  `receber_notificacoes` tinyint(1) DEFAULT '1',
  `receber_por_email` tinyint(1) DEFAULT '0',
  `receber_urgentes` tinyint(1) DEFAULT '1',
  `receber_gerais` tinyint(1) DEFAULT '1',
  `receber_turma` tinyint(1) DEFAULT '1',
  `som_notificacao` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes_destinatarios`
--

CREATE TABLE `notificacoes_destinatarios` (
  `id` int NOT NULL,
  `notificacao_id` int NOT NULL,
  `tipo_destinatario` enum('todos','usuarios','professores','alunos','pais','turma','todos_alunos','todos_professores','todos_admins','todos_pais') COLLATE utf8mb4_unicode_ci NOT NULL,
  `destinatario_id` int DEFAULT NULL,
  `turma_id` int DEFAULT NULL,
  `lida` tinyint(1) DEFAULT '0',
  `lida_em` datetime DEFAULT NULL,
  `visualizada_em` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes_historico`
--

CREATE TABLE `notificacoes_historico` (
  `id` int NOT NULL,
  `notificacao_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `tipo_usuario` enum('admin','professor','aluno','pai') COLLATE utf8mb4_unicode_ci NOT NULL,
  `acao` enum('enviada','visualizada','lida','atualizada','excluida') COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalhes` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes_push`
--

CREATE TABLE `notificacoes_push` (
  `id` int UNSIGNED NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL para redirecionar ao clicar',
  `tipo_destino` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'todos|pais|alunos|professores|turma|usuario',
  `destino_id` int UNSIGNED DEFAULT NULL COMMENT 'ID da turma ou usuario conforme tipo_destino',
  `criado_por` int UNSIGNED NOT NULL COMMENT 'usuario_id do admin',
  `onesignal_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID do envio no OneSignal',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes_push_envios`
--

CREATE TABLE `notificacoes_push_envios` (
  `id` bigint UNSIGNED NOT NULL,
  `notificacao_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'usuarios.id do destinatário',
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'pai|aluno|professor|admin_escola',
  `tracking_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Token para API de tracking (ex: visualizado/clicado)',
  `entregue` tinyint(1) NOT NULL DEFAULT '0',
  `visualizado` tinyint(1) NOT NULL DEFAULT '0',
  `clicado` tinyint(1) NOT NULL DEFAULT '0',
  `entregue_em` timestamp NULL DEFAULT NULL,
  `visualizado_em` timestamp NULL DEFAULT NULL,
  `clicado_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pacotes_creditos`
--

CREATE TABLE `pacotes_creditos` (
  `id` int UNSIGNED NOT NULL,
  `creditos` int UNSIGNED NOT NULL,
  `valor_centavos` int UNSIGNED NOT NULL DEFAULT '0',
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex: Pacote 10 créditos',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `catalogo_pacote_id` int UNSIGNED DEFAULT NULL COMMENT 'FK lógico ao master'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pacotes de créditos à venda (preço em centavos)';

-- --------------------------------------------------------

--
-- Estrutura para tabela `partidas_dama`
--

CREATE TABLE `partidas_dama` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `nivel_dificuldade` enum('facil','medio','dificil') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'facil',
  `status` enum('em_andamento','finalizada','abandonada') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'em_andamento',
  `resultado` enum('vitoria_aluno','vitoria_robo','empate') COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `tabuleiro` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `vez_jogador` enum('aluno','robo') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'aluno',
  `movimentos` text COLLATE utf8mb3_unicode_ci,
  `pontuacao_aluno` int DEFAULT '0',
  `pontuacao_robo` int DEFAULT '0',
  `data_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `patrimony_assets`
--

CREATE TABLE `patrimony_assets` (
  `id` int NOT NULL,
  `numero_patrimonio` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` enum('mobiliario','informatica','projetor','climatizacao','laboratorio','veiculo','instrumento','outro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outro',
  `numero_serie` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marca` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_aquisicao` date DEFAULT NULL,
  `valor_aquisicao` decimal(12,2) NOT NULL DEFAULT '0.00',
  `nota_fiscal` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fornecedor_id` int DEFAULT NULL,
  `garantia_ate` date DEFAULT NULL,
  `vida_util_meses` int NOT NULL DEFAULT '60',
  `location_id` int DEFAULT NULL,
  `responsavel_nome` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem` enum('proprio','comodato','cedido','doado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proprio',
  `status` enum('ativo','manutencao','emprestado','baixado','nao_localizado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `patrimony_inventory_checks`
--

CREATE TABLE `patrimony_inventory_checks` (
  `id` int NOT NULL,
  `asset_id` int NOT NULL,
  `location_id` int DEFAULT NULL,
  `status_conferencia` enum('ok','local_errado','nao_localizado','sem_plaqueta','avariado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ok',
  `observacoes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conferido_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `patrimony_movements`
--

CREATE TABLE `patrimony_movements` (
  `id` int NOT NULL,
  `asset_id` int NOT NULL,
  `tipo` enum('transferencia','emprestimo','manutencao_envio','manutencao_retorno','inventario','baixa') COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_origem_id` int DEFAULT NULL,
  `location_destino_id` int DEFAULT NULL,
  `responsavel_origem` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsavel_destino` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `realizado_por` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `planos_aula`
--

CREATE TABLE `planos_aula` (
  `id` int NOT NULL,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `data_aula` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `dias_aula` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex: 28 e 31 (TERÇA E SEXTA-FEIRA)',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ano_disciplina` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex: 1° ANO A / BIOLOGIA',
  `modulo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Módulo do conteúdo',
  `aula_num` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Aula Nº (Ex: 76 a 79)',
  `paginas` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Páginas (Ex: 5 a 18)',
  `conteudo_lista` text COLLATE utf8mb4_unicode_ci COMMENT 'Lista de conteúdos com bullets',
  `objetivos` text COLLATE utf8mb4_unicode_ci,
  `objetivos_lista` text COLLATE utf8mb4_unicode_ci COMMENT 'Lista de objetivos específicos',
  `conteudo` text COLLATE utf8mb4_unicode_ci,
  `metodologia` text COLLATE utf8mb4_unicode_ci,
  `periodo_tarde_tema` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tema do período da tarde',
  `periodo_tarde_exercicios` text COLLATE utf8mb4_unicode_ci COMMENT 'Exercícios do período da tarde',
  `recursos` text COLLATE utf8mb4_unicode_ci COMMENT 'Recursos utilizados na aula',
  `recursos_lista` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON com recursos selecionados (checkboxes)',
  `aulas_tarde_oficinas` text COLLATE utf8mb4_unicode_ci,
  `avaliacao` text COLLATE utf8mb4_unicode_ci COMMENT 'Como será avaliado',
  `avaliacao_apostila` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Apostila da avaliação bimestral',
  `avaliacao_conteudo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Conteúdo da avaliação bimestral',
  `avaliacao_paginas` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Páginas da avaliação bimestral',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `contexto_llm` longtext COLLATE utf8mb4_unicode_ci,
  `status` enum('rascunho','aprovado','rejeitado') COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `planos_creditos`
--

CREATE TABLE `planos_creditos` (
  `id` int UNSIGNED NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creditos_mensais` int UNSIGNED NOT NULL,
  `valor_mensal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `destino` enum('aluno','professor','ambos') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ambos',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `catalogo_plano_id` int UNSIGNED DEFAULT NULL COMMENT 'FK lógico ao master'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planos mensais que concedem créditos (assinatura)';

-- --------------------------------------------------------

--
-- Estrutura para tabela `plano_curso`
--

CREATE TABLE `plano_curso` (
  `id` int NOT NULL,
  `ano_letivo_id` int DEFAULT NULL,
  `serie_id` int DEFAULT NULL,
  `materia_id` int NOT NULL,
  `carga_horaria_prevista` int NOT NULL DEFAULT '0' COMMENT 'horas/aula previstas no ano',
  `avaliacoes_previstas` int NOT NULL DEFAULT '0',
  `conteudo_previsto` text COLLATE utf8mb4_unicode_ci,
  `objetivos` text COLLATE utf8mb4_unicode_ci,
  `metodologia` text COLLATE utf8mb4_unicode_ci,
  `status` enum('rascunho','aprovado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `plano_curso_habilidades`
--

CREATE TABLE `plano_curso_habilidades` (
  `id` int NOT NULL,
  `plano_curso_id` int NOT NULL,
  `habilidade_id` int NOT NULL,
  `trabalhada` tinyint(1) NOT NULL DEFAULT '0',
  `trabalhada_em` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pontuacao_alunos`
--

CREATE TABLE `pontuacao_alunos` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `total_partidas` int DEFAULT '0',
  `partidas_vencidas` int DEFAULT '0',
  `maior_premio` decimal(10,2) DEFAULT '0.00',
  `total_premio` decimal(10,2) DEFAULT '0.00',
  `nivel_atual` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT 'Iniciante',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professores`
--

CREATE TABLE `professores` (
  `id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `codigo_prof` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Código do professor - login',
  `materias` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Lista de matérias que leciona',
  `turmas` longtext COLLATE utf8mb3_unicode_ci,
  `ativo` tinyint(1) DEFAULT '1',
  `pagante` tinyint(1) DEFAULT '1' COMMENT 'Indica se a escola paga por este professor',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `pode_tutoria` tinyint(1) NOT NULL DEFAULT '0'
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professores_ia_agentes`
--

CREATE TABLE `professores_ia_agentes` (
  `id` int UNSIGNED NOT NULL,
  `professor_id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Configurações do agente em formato JSON (escolhas do professor)',
  `instrucoes_sistema` text COLLATE utf8mb4_unicode_ci COMMENT 'Instruções personalizadas para o agente',
  `system_prompt` text COLLATE utf8mb4_unicode_ci COMMENT 'Prompt do sistema gerado automaticamente a partir de config_json',
  `modelo_ia` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'gpt-4o-mini' COMMENT 'Modelo OpenAI a ser usado',
  `temperatura` decimal(3,2) DEFAULT '0.70',
  `max_tokens` int DEFAULT '2000',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professores_ia_conversas`
--

CREATE TABLE `professores_ia_conversas` (
  `id` int UNSIGNED NOT NULL,
  `agente_id` int UNSIGNED NOT NULL,
  `professor_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Título gerado automaticamente da primeira pergunta',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professores_ia_documentos`
--

CREATE TABLE `professores_ia_documentos` (
  `id` int UNSIGNED NOT NULL,
  `agente_id` int UNSIGNED NOT NULL,
  `professor_id` int NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho_bytes` bigint UNSIGNED DEFAULT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `texto_extraido` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Texto completo extraído do documento',
  `status_processamento` enum('pendente','processando','concluido','erro') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `erro_processamento` text COLLATE utf8mb4_unicode_ci,
  `total_chunks` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professores_ia_documentos_chunks`
--

CREATE TABLE `professores_ia_documentos_chunks` (
  `id` int UNSIGNED NOT NULL,
  `documento_id` int UNSIGNED NOT NULL,
  `agente_id` int UNSIGNED NOT NULL,
  `chunk_index` int NOT NULL COMMENT 'Índice sequencial do chunk no documento',
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokens` int DEFAULT '0' COMMENT 'Número aproximado de tokens',
  `embedding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Vetor de embedding (1536 dimensões para text-embedding-3-small)',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Metadados adicionais (página, seção, etc)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professores_ia_mensagens`
--

CREATE TABLE `professores_ia_mensagens` (
  `id` int UNSIGNED NOT NULL,
  `conversa_id` int UNSIGNED NOT NULL,
  `role` enum('user','assistant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `chunks_usados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'IDs dos chunks usados para gerar a resposta',
  `tokens_usados` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professores_slides`
--

CREATE TABLE `professores_slides` (
  `id` int NOT NULL,
  `professor_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Título do slide (extraído do conteúdo ou gerado)',
  `conteudo` text COLLATE utf8mb4_unicode_ci COMMENT 'Conteúdo original usado para gerar o slide',
  `url_gamma` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL da apresentação no Gamma',
  `generation_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID da geração no Gamma',
  `numero_slides` int DEFAULT '8' COMMENT 'Número de slides gerados',
  `tema` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tema usado na geração',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Slides gerados pelos professores';

-- --------------------------------------------------------

--
-- Estrutura para tabela `professor_questoes_api`
--

CREATE TABLE `professor_questoes_api` (
  `id` bigint UNSIGNED NOT NULL,
  `external_id` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `materia` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enunciado_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `alternativas_json` json DEFAULT NULL,
  `gabarito` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolucao_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `bncc` text COLLATE utf8mb4_unicode_ci,
  `tags` text COLLATE utf8mb4_unicode_ci,
  `topicos` text COLLATE utf8mb4_unicode_ci,
  `source_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professor_questoes_montagem_itens`
--

CREATE TABLE `professor_questoes_montagem_itens` (
  `id` bigint UNSIGNED NOT NULL,
  `montagem_id` bigint UNSIGNED NOT NULL,
  `questao_id` bigint UNSIGNED NOT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professor_questoes_montagens`
--

CREATE TABLE `professor_questoes_montagens` (
  `id` bigint UNSIGNED NOT NULL,
  `professor_id` bigint UNSIGNED NOT NULL,
  `titulo` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas`
--

CREATE TABLE `provas` (
  `id` int NOT NULL,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `turma_id` int DEFAULT NULL COMMENT 'NULL = múltiplas escolas/turmas',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL,
  `tempo_limite` int DEFAULT NULL COMMENT 'Tempo em minutos, NULL = sem limite',
  `valor_total` decimal(10,2) DEFAULT '100.00',
  `mostrar_resultado` tinyint(1) DEFAULT '1',
  `permite_correcao` tinyint(1) DEFAULT '0',
  `liberar_resultado` enum('imediatamente','apos_todos','nao_liberar') COLLATE utf8mb4_unicode_ci DEFAULT 'imediatamente',
  `ativo` tinyint(1) DEFAULT '1',
  `liberada` tinyint(1) DEFAULT '0' COMMENT '0 = bloqueada, 1 = liberada para alunos',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_reprovacao` text COLLATE utf8mb4_unicode_ci,
  `coordenador_id` int DEFAULT NULL,
  `data_reprovacao` datetime DEFAULT NULL,
  `data_envio` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `observacao_coordenacao` text COLLATE utf8mb4_unicode_ci,
  `observacao_coordenacao_data` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_alternativas`
--

CREATE TABLE `provas_alternativas` (
  `id` int NOT NULL,
  `questao_id` int NOT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci,
  `correta` tinyint(1) DEFAULT '0',
  `ordem` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_blocos`
--

CREATE TABLE `provas_blocos` (
  `id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome do bloco (ex: "Prova Bimestral 1º Bimestre")',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'aguardando',
  `prazo_entrega_professor` datetime DEFAULT NULL COMMENT 'Prazo para professores enviarem suas provas',
  `descricao` text COLLATE utf8mb4_unicode_ci COMMENT 'Descrição do bloco',
  `ano_letivo` smallint UNSIGNED DEFAULT NULL COMMENT 'Ano letivo do evento',
  `bimestre` tinyint UNSIGNED DEFAULT NULL COMMENT 'Bimestre 1 a 4',
  `tipo_avaliacao_id` int UNSIGNED DEFAULT NULL,
  `visivel_no_portal_aluno` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=aluno vê no portal; 0=só coordenação/professor',
  `data_prova` date NOT NULL COMMENT 'Data da prova',
  `hora_inicio` time NOT NULL COMMENT 'Horário de início',
  `hora_fim` time NOT NULL COMMENT 'Horário de término',
  `criado_por` int NOT NULL COMMENT 'ID do usuário que criou (admin/coordenador/diretor)',
  `professor_id` int DEFAULT NULL COMMENT 'Professor responsável pelo evento',
  `materia_id` int DEFAULT NULL COMMENT 'Matéria do evento',
  `tipo_prova` enum('original','substitutiva') COLLATE utf8mb4_unicode_ci DEFAULT 'original' COMMENT 'Tipo de prova',
  `formato_evento` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online_questoes' COMMENT 'online_questoes=prova com questões; lancamento_nota=professor lança nota por aluno/turma',
  `configuracao_nota` enum('professor_por_questao','coordenacao_calcula') COLLATE utf8mb4_unicode_ci DEFAULT 'professor_por_questao' COMMENT 'Quem define a nota',
  `nota_unica_todas_materias` tinyint(1) NOT NULL DEFAULT '0',
  `liberar_gabarito` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'imediatamente' COMMENT 'Quando liberar gabarito: imediatamente ou datetime',
  `turma_id` int DEFAULT NULL COMMENT 'NULL = múltiplas turmas, ou turma específica',
  `bloco_modelo_id` int UNSIGNED DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `liberado` tinyint(1) DEFAULT '0' COMMENT '0 = não liberado, 1 = liberado para alunos',
  `gabarito_liberado` tinyint(1) DEFAULT '0' COMMENT '0 = gabarito bloqueado para alunos, 1 = gabarito liberado pela coordenação',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_blocos_modelos`
--

CREATE TABLE `provas_blocos_modelos` (
  `id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome do modelo (ex: Bloco A, Bloco Simulado ENEM)',
  `descricao` text COLLATE utf8mb4_unicode_ci COMMENT 'Descrição opcional do modelo',
  `criado_por` int NOT NULL COMMENT 'ID do usuário que criou (coordenação)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_blocos_modelos_professores`
--

CREATE TABLE `provas_blocos_modelos_professores` (
  `id` int NOT NULL,
  `modelo_id` int NOT NULL COMMENT 'ID do bloco modelo',
  `professor_id` int NOT NULL COMMENT 'ID do professor',
  `materia_id` int NOT NULL COMMENT 'ID da matéria',
  `numero_questoes` int DEFAULT '0' COMMENT 'Número de questões solicitadas',
  `ordem` int DEFAULT '0' COMMENT 'Ordem de exibição',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_blocos_notas_lancadas`
--

CREATE TABLE `provas_blocos_notas_lancadas` (
  `id` int UNSIGNED NOT NULL,
  `bloco_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `nota` decimal(6,2) DEFAULT NULL,
  `observacao` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_blocos_professores`
--

CREATE TABLE `provas_blocos_professores` (
  `id` int NOT NULL,
  `bloco_id` int NOT NULL COMMENT 'ID do bloco de provas',
  `professor_id` int NOT NULL COMMENT 'ID do professor',
  `materia_id` int NOT NULL COMMENT 'ID da matéria do professor neste bloco',
  `quantidade_questoes` int UNSIGNED NOT NULL DEFAULT '5' COMMENT 'Número de questões que o professor deve criar para este bloco/matéria',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_blocos_professores_turmas`
--

CREATE TABLE `provas_blocos_professores_turmas` (
  `id` int NOT NULL,
  `bloco_professor_id` int NOT NULL COMMENT 'ID do relacionamento bloco-professor',
  `turma_id` int NOT NULL COMMENT 'ID da turma',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_blocos_turmas`
--

CREATE TABLE `provas_blocos_turmas` (
  `id` int NOT NULL,
  `bloco_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_blocos_vinculo`
--

CREATE TABLE `provas_blocos_vinculo` (
  `id` int NOT NULL,
  `bloco_id` int NOT NULL,
  `prova_id` int NOT NULL,
  `ordem` int DEFAULT '0' COMMENT 'Ordem de exibição no bloco',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_final`
--

CREATE TABLE `provas_final` (
  `id` int NOT NULL,
  `bloco_id` int NOT NULL COMMENT 'ID do bloco de provas',
  `prova_id` int NOT NULL COMMENT 'ID da prova unificada (referência à tabela provas)',
  `data_prova` date NOT NULL COMMENT 'Data da prova final',
  `horario_prova` time NOT NULL COMMENT 'Horário da prova final',
  `publicada` tinyint(1) DEFAULT '0' COMMENT 'Se a prova foi publicada para alunos',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_professores`
--

CREATE TABLE `provas_professores` (
  `id` int NOT NULL,
  `bloco_id` int NOT NULL COMMENT 'ID do bloco de provas',
  `professor_id` int NOT NULL COMMENT 'ID do professor',
  `materia_id` int NOT NULL COMMENT 'ID da matéria',
  `numero_questoes` int DEFAULT '0' COMMENT 'Número de questões solicitadas',
  `status` enum('em_andamento','enviada','nao_enviada','aprovada','reprovada') COLLATE utf8mb4_unicode_ci DEFAULT 'em_andamento',
  `data_envio` datetime DEFAULT NULL COMMENT 'Data em que o professor enviou a prova',
  `travada` tinyint(1) DEFAULT '0' COMMENT 'Se a prova está travada (não pode ser editada)',
  `prova_id` int DEFAULT NULL COMMENT 'ID da prova criada pelo professor (referência à tabela provas)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_questoes`
--

CREATE TABLE `provas_questoes` (
  `id` int NOT NULL,
  `prova_id` int NOT NULL,
  `enunciado` text COLLATE utf8mb4_unicode_ci,
  `imagem_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL da imagem do enunciado',
  `tipo` enum('multipla_escolha','verdadeiro_falso','dissertativa') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'multipla_escolha',
  `valor` decimal(10,2) DEFAULT '1.00',
  `invalidada` tinyint(1) NOT NULL DEFAULT '0',
  `observacao_invalidacao` text COLLATE utf8mb4_unicode_ci,
  `invalidada_por` int DEFAULT NULL,
  `invalidada_em` datetime DEFAULT NULL,
  `nivel_dificuldade` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int DEFAULT '0',
  `explicacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_realizacoes`
--

CREATE TABLE `provas_realizacoes` (
  `id` int NOT NULL,
  `prova_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `iniciado_em` datetime NOT NULL,
  `finalizado_em` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT NULL COMMENT 'Tempo em minutos',
  `nota` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem_questoes` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON com ordem das questões sorteadas',
  `continuar_sem_tempo` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = liberado pelo admin para continuar sem limite de tempo',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `marcacao_final_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_respostas`
--

CREATE TABLE `provas_respostas` (
  `id` int NOT NULL,
  `prova_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `questao_id` int NOT NULL,
  `alternativa_id` int DEFAULT NULL COMMENT 'Para múltipla escolha',
  `resposta_texto` text COLLATE utf8mb4_unicode_ci COMMENT 'Para dissertativa',
  `correta` tinyint(1) DEFAULT NULL COMMENT 'NULL = não corrigida',
  `pontuacao` decimal(10,2) DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_respostas_log`
--

CREATE TABLE `provas_respostas_log` (
  `id` int UNSIGNED NOT NULL,
  `prova_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL,
  `questao_id` int UNSIGNED NOT NULL,
  `alternativa_id` int UNSIGNED DEFAULT NULL,
  `resposta_texto` text COLLATE utf8mb4_unicode_ci,
  `tipo_acao` enum('marcou','alterou') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_tipos_avaliacao`
--

CREATE TABLE `provas_tipos_avaliacao` (
  `id` int UNSIGNED NOT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_turmas`
--

CREATE TABLE `provas_turmas` (
  `id` int NOT NULL,
  `prova_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `provas_validacoes_log`
--

CREATE TABLE `provas_validacoes_log` (
  `id` int NOT NULL,
  `prova_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `bloco_id` int DEFAULT NULL,
  `nota` decimal(10,2) DEFAULT NULL,
  `validado_por_id` int DEFAULT NULL,
  `validado_por_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validado_por_tipo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `questoes`
--

CREATE TABLE `questoes` (
  `id` int NOT NULL,
  `lista_id` int NOT NULL,
  `pergunta` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_b` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_c` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_d` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_e` text COLLATE utf8mb3_unicode_ci,
  `resposta_correta` enum('A','B','C','D','E') COLLATE utf8mb3_unicode_ci NOT NULL,
  `explicacao` text COLLATE utf8mb3_unicode_ci,
  `tempo_estimado` int DEFAULT '60',
  `ordem` int NOT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `questoes_personalizadas`
--

CREATE TABLE `questoes_personalizadas` (
  `id` int NOT NULL,
  `lista_id` int NOT NULL,
  `pergunta` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativa_a` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativa_b` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativa_c` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativa_d` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternativa_e` text COLLATE utf8mb4_unicode_ci,
  `resposta_correta` enum('A','B','C','D','E') COLLATE utf8mb4_unicode_ci NOT NULL,
  `explicacao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel_dificuldade` enum('Fácil','Médio','Difícil') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int DEFAULT '1',
  `gerado_ia` tinyint(1) DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacao_livre_correcoes`
--

CREATE TABLE `redacao_livre_correcoes` (
  `id` int UNSIGNED NOT NULL,
  `envio_id` int UNSIGNED NOT NULL,
  `grades_json` text COLLATE utf8mb4_unicode_ci COMMENT 'Notas por competência (IA)',
  `teacher_grades_json` text COLLATE utf8mb4_unicode_ci COMMENT 'Notas do professor por competência',
  `feedback_text` text COLLATE utf8mb4_unicode_ci,
  `suggestions_text` text COLLATE utf8mb4_unicode_ci,
  `total_score` decimal(6,2) DEFAULT NULL,
  `ai_total_score` decimal(6,2) DEFAULT NULL,
  `teacher_total_score` decimal(6,2) DEFAULT NULL,
  `use_average` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = média entre IA e professor',
  `corrected_by_teacher_id` int UNSIGNED DEFAULT NULL,
  `teacher_adjusted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacao_livre_envios`
--

CREATE TABLE `redacao_livre_envios` (
  `id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `student_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nome do aluno (pode vir do arquivo ou digitado)',
  `student_id` int UNSIGNED DEFAULT NULL COMMENT 'Aluno vinculado (opcional)',
  `turma_id` int UNSIGNED DEFAULT NULL COMMENT 'Sala/turma vinculada (opcional)',
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_image_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho ou key do arquivo (imagem/PDF)',
  `content_text` text COLLATE utf8mb4_unicode_ci COMMENT 'Texto transcrito ou digitado',
  `ocr_text` text COLLATE utf8mb4_unicode_ci COMMENT 'Texto extraído por OCR se houver',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes`
--

CREATE TABLE `redacoes` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `tema_id` int DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `conteudo` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('padrao','livre','ia_gerado','transcricao') COLLATE utf8mb3_unicode_ci DEFAULT 'padrao',
  `eh_rascunho` tinyint(1) DEFAULT '0',
  `tema_gerado` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `imagem_path` varchar(500) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `texto_transcrito` text COLLATE utf8mb3_unicode_ci,
  `competencia_1_nota` int DEFAULT NULL,
  `competencia_1_explicacao` text COLLATE utf8mb3_unicode_ci,
  `competencia_2_nota` int DEFAULT NULL,
  `competencia_2_explicacao` text COLLATE utf8mb3_unicode_ci,
  `competencia_3_nota` int DEFAULT NULL,
  `competencia_3_explicacao` text COLLATE utf8mb3_unicode_ci,
  `competencia_4_nota` int DEFAULT NULL,
  `competencia_4_explicacao` text COLLATE utf8mb3_unicode_ci,
  `competencia_5_nota` int DEFAULT NULL,
  `competencia_5_explicacao` text COLLATE utf8mb3_unicode_ci,
  `comentarios_gerais` text COLLATE utf8mb3_unicode_ci,
  `sugestoes_melhoria` text COLLATE utf8mb3_unicode_ci,
  `nota_final` int DEFAULT NULL COMMENT 'Nota de 0 a 1000',
  `competencia_1` int DEFAULT NULL COMMENT 'Nota competência 1',
  `competencia_2` int DEFAULT NULL COMMENT 'Nota competência 2',
  `competencia_3` int DEFAULT NULL COMMENT 'Nota competência 3',
  `competencia_4` int DEFAULT NULL COMMENT 'Nota competência 4',
  `competencia_5` int DEFAULT NULL COMMENT 'Nota competência 5',
  `feedback_ia` text COLLATE utf8mb3_unicode_ci,
  `corrigida_em` datetime DEFAULT NULL,
  `tema` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `tema_texto` text COLLATE utf8mb3_unicode_ci,
  `tempo_escrita` int DEFAULT '0' COMMENT 'Tempo de escrita em segundos',
  `texto` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `imagem_url` varchar(500) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Caso seja foto enviada',
  `correcao` text COLLATE utf8mb3_unicode_ci COMMENT 'Correção gerada pela IA',
  `nota` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `oculto` tinyint(1) DEFAULT '0',
  `jornada_id` int DEFAULT NULL COMMENT 'ID da jornada (se for redação da jornada)',
  `correcao_professor` text COLLATE utf8mb3_unicode_ci COMMENT 'Correção completa do professor',
  `competencia_1_professor` int DEFAULT NULL COMMENT 'Nota competência 1 do professor',
  `competencia_1_explicacao_professor` text COLLATE utf8mb3_unicode_ci COMMENT 'Explicação competência 1 do professor',
  `competencia_2_professor` int DEFAULT NULL COMMENT 'Nota competência 2 do professor',
  `competencia_2_explicacao_professor` text COLLATE utf8mb3_unicode_ci COMMENT 'Explicação competência 2 do professor',
  `competencia_3_professor` int DEFAULT NULL COMMENT 'Nota competência 3 do professor',
  `competencia_3_explicacao_professor` text COLLATE utf8mb3_unicode_ci COMMENT 'Explicação competência 3 do professor',
  `competencia_4_professor` int DEFAULT NULL COMMENT 'Nota competência 4 do professor',
  `competencia_4_explicacao_professor` text COLLATE utf8mb3_unicode_ci COMMENT 'Explicação competência 4 do professor',
  `competencia_5_professor` int DEFAULT NULL COMMENT 'Nota competência 5 do professor',
  `competencia_5_explicacao_professor` text COLLATE utf8mb3_unicode_ci COMMENT 'Explicação competência 5 do professor',
  `nota_final_professor` int DEFAULT NULL COMMENT 'Nota final do professor',
  `comentarios_gerais_professor` text COLLATE utf8mb3_unicode_ci COMMENT 'Comentários gerais do professor',
  `sugestoes_melhoria_professor` text COLLATE utf8mb3_unicode_ci COMMENT 'Sugestões de melhoria do professor',
  `permitir_refazer` tinyint(1) DEFAULT '0' COMMENT 'Indica se o professor permite que o aluno refaça a redação (1 = permitido, 0 = não permitido)',
  `corrigida_por_professor` int DEFAULT NULL COMMENT 'ID do professor que corrigiu',
  `corrigida_em_professor` datetime DEFAULT NULL COMMENT 'Data da correção do professor',
  `usar_correcao_professor` tinyint(1) DEFAULT '0' COMMENT 'Usar correção do professor ao invés da IA',
  `retornada_para_reescrever` tinyint(1) DEFAULT '0' COMMENT 'Indica se foi retornada para reescrever',
  `observacoes_professor` text COLLATE utf8mb3_unicode_ci COMMENT 'Observações do professor para o aluno',
  `usar_media_notas` tinyint(1) DEFAULT '0' COMMENT 'Usar média entre nota do professor e IA',
  `nota_media` decimal(5,2) DEFAULT NULL COMMENT 'Média calculada entre nota do professor e IA',
  `nota_final_utilizada` decimal(5,2) DEFAULT NULL COMMENT 'Nota final que será utilizada (professor, IA ou média)',
  `mostrar_correcao_ia_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra a correção da IA para o aluno. Se 0, mostra apenas a correção do professor.',
  `mostrar_competencia_1_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 1 para o aluno',
  `mostrar_competencia_2_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 2 para o aluno',
  `mostrar_competencia_3_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 3 para o aluno',
  `mostrar_competencia_4_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 4 para o aluno',
  `mostrar_competencia_5_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 5 para o aluno'
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_correcoes`
--

CREATE TABLE `redacoes_orientadas_correcoes` (
  `id` int NOT NULL,
  `submission_id` int NOT NULL,
  `prompt_id` int DEFAULT NULL,
  `raw_response_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `grades_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Scores per criterion',
  `feedback_text` longtext COLLATE utf8mb4_unicode_ci,
  `suggestions_text` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Sugestões de melhoria (IA ou editado pelo professor)',
  `total_score` decimal(5,2) DEFAULT NULL,
  `ai_total_score` decimal(5,2) DEFAULT NULL COMMENT 'Soma das notas IA por critério',
  `teacher_grades_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Notas e feedback por critério (professor)',
  `teacher_total_score` decimal(5,2) DEFAULT NULL COMMENT 'Soma das notas do professor',
  `use_average` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = exibir média (IA + professor)',
  `corrected_by_teacher_id` int DEFAULT NULL,
  `teacher_adjusted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `teacher_feedback_audio_key` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `teacher_annotations_json` longtext COLLATE utf8mb4_unicode_ci,
  `image_annotations_json` longtext COLLATE utf8mb4_unicode_ci,
  `annotated_image_key` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_correcoes_logs`
--

CREATE TABLE `redacoes_orientadas_correcoes_logs` (
  `id` int NOT NULL,
  `correction_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_criterios`
--

CREATE TABLE `redacoes_orientadas_criterios` (
  `id` int NOT NULL,
  `text_type_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `max_score` decimal(5,2) NOT NULL DEFAULT '200.00',
  `order_position` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_entregas`
--

CREATE TABLE `redacoes_orientadas_entregas` (
  `id` int NOT NULL,
  `proposal_id` int NOT NULL,
  `student_id` int NOT NULL,
  `content_text` longtext COLLATE utf8mb4_unicode_ci,
  `content_image_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ocr_text` longtext COLLATE utf8mb4_unicode_ci,
  `ocr_text_structure_json` longtext COLLATE utf8mb4_unicode_ci,
  `ocr_layout_json` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_prompts`
--

CREATE TABLE `redacoes_orientadas_prompts` (
  `id` int NOT NULL,
  `board_id` int NOT NULL,
  `text_type_id` int NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  `prompt_text` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_propostas`
--

CREATE TABLE `redacoes_orientadas_propostas` (
  `id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `board_id` int NOT NULL,
  `text_type_id` int NOT NULL,
  `theme_mode` enum('configurar','arquivo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'configurar' COMMENT 'Como o tema foi definido',
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme` text COLLATE utf8mb4_unicode_ci,
  `contexto` text COLLATE utf8mb4_unicode_ci COMMENT 'Contexto/descrição da redação (pode ser gerado por IA)',
  `repertoire` text COLLATE utf8mb4_unicode_ci,
  `tema_pronto_file` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL ou path do PDF/imagem quando theme_mode=arquivo',
  `images_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'URLs or paths of attached images',
  `show_title_field` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=exibir campo título para o aluno',
  `submission_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'texto',
  `starts_at` datetime DEFAULT NULL COMMENT 'Início do período de realização',
  `ends_at` datetime DEFAULT NULL COMMENT 'Fim do período de realização',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_propostas_alunos`
--

CREATE TABLE `redacoes_orientadas_propostas_alunos` (
  `id` int NOT NULL,
  `proposal_id` int NOT NULL,
  `student_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_propostas_professores`
--

CREATE TABLE `redacoes_orientadas_propostas_professores` (
  `proposal_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `granted_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_propostas_turmas`
--

CREATE TABLE `redacoes_orientadas_propostas_turmas` (
  `id` int NOT NULL,
  `proposal_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_quadros`
--

CREATE TABLE `redacoes_orientadas_quadros` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_orientadas_tipos_texto`
--

CREATE TABLE `redacoes_orientadas_tipos_texto` (
  `id` int NOT NULL,
  `board_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redacoes_temas`
--

CREATE TABLE `redacoes_temas` (
  `id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('Temas Autorais','Temas de Vestibulares','Redações Pré-existentes') COLLATE utf8mb3_unicode_ci NOT NULL,
  `instrucoes` text COLLATE utf8mb3_unicode_ci,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `redefinicoes_senha`
--

CREATE TABLE `redefinicoes_senha` (
  `id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `relatorios`
--

CREATE TABLE `relatorios` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `tipo` enum('desempenho','jornada','redacao') COLLATE utf8mb3_unicode_ci NOT NULL,
  `dados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Dados detalhados do relatório',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `responsaveis`
--

CREATE TABLE `responsaveis` (
  `id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `force_password_change` tinyint(1) NOT NULL DEFAULT '0',
  `cpf` varchar(14) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `rg` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `celular` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `endereco` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `numero` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `complemento` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `bairro` varchar(120) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cidade` varchar(120) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `uf` char(2) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `cep` varchar(9) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb3_unicode_ci,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `reuniao_anexos`
--

CREATE TABLE `reuniao_anexos` (
  `id` int NOT NULL,
  `reuniao_id` int NOT NULL,
  `nome` varchar(255) NOT NULL,
  `caminho` varchar(500) NOT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `reuniao_turmas`
--

CREATE TABLE `reuniao_turmas` (
  `reuniao_id` int NOT NULL,
  `turma_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `reunioes`
--

CREATE TABLE `reunioes` (
  `id` int NOT NULL,
  `tipo` enum('pais','geral') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pais',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_reuniao` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fim` time DEFAULT NULL,
  `local_reuniao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `aluno_id` int DEFAULT NULL,
  `responsavel_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_calendar_events`
--

CREATE TABLE `school_calendar_events` (
  `id` bigint UNSIGNED NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'evento',
  `prioridade` enum('normal','importante','urgente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `local` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inicio_em` datetime NOT NULL,
  `fim_em` datetime DEFAULT NULL,
  `dia_inteiro` tinyint(1) NOT NULL DEFAULT '0',
  `publico` enum('todos','turmas','alunos') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('rascunho','publicado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publicado',
  `criado_por` int UNSIGNED NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_calendar_event_classes`
--

CREATE TABLE `school_calendar_event_classes` (
  `event_id` bigint UNSIGNED NOT NULL,
  `turma_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_calendar_event_reads`
--

CREATE TABLE `school_calendar_event_reads` (
  `event_id` bigint UNSIGNED NOT NULL,
  `responsavel_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL,
  `lido_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_calendar_event_students`
--

CREATE TABLE `school_calendar_event_students` (
  `event_id` bigint UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_communications`
--

CREATE TABLE `school_communications` (
  `id` bigint UNSIGNED NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioridade` enum('normal','importante','urgente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `permite_resposta` tinyint(1) NOT NULL DEFAULT '1',
  `publico` enum('todos','turmas','alunos') COLLATE utf8mb4_unicode_ci NOT NULL,
  `autor_tipo` enum('admin','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `autor_id` int UNSIGNED NOT NULL,
  `status` enum('rascunho','publicado','arquivado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publicado',
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_communication_attachments`
--

CREATE TABLE `school_communication_attachments` (
  `id` bigint UNSIGNED NOT NULL,
  `communication_id` bigint UNSIGNED NOT NULL,
  `arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_communication_classes`
--

CREATE TABLE `school_communication_classes` (
  `communication_id` bigint UNSIGNED NOT NULL,
  `turma_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_communication_reads`
--

CREATE TABLE `school_communication_reads` (
  `communication_id` bigint UNSIGNED NOT NULL,
  `responsavel_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL,
  `lido_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_communication_replies`
--

CREATE TABLE `school_communication_replies` (
  `id` bigint UNSIGNED NOT NULL,
  `communication_id` bigint UNSIGNED NOT NULL,
  `responsavel_id` int UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL,
  `sender_type` enum('responsavel','admin','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_id` int UNSIGNED NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lido_em` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_communication_students`
--

CREATE TABLE `school_communication_students` (
  `communication_id` bigint UNSIGNED NOT NULL,
  `aluno_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `school_locations`
--

CREATE TABLE `school_locations` (
  `id` int NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('sala','laboratorio','cantina','deposito','biblioteca','secretaria','quadra','outro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sala',
  `bloco` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `andar` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsavel_nome` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `serie`
--

CREATE TABLE `serie` (
  `id` int NOT NULL,
  `curso_id` int NOT NULL,
  `nome` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `sessoes`
--

CREATE TABLE `sessoes` (
  `id` varchar(128) COLLATE utf8mb3_unicode_ci NOT NULL,
  `usuario_id` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb3_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb3_unicode_ci,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `simulados`
--

CREATE TABLE `simulados` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `ano` int NOT NULL,
  `disciplina` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `tipo_vestibular` enum('ENEM','FUVEST','VUNESP','UNICAMP','UFMG','OUTROS') COLLATE utf8mb3_unicode_ci DEFAULT 'ENEM',
  `idioma` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT 'Português',
  `quantidade_questoes` int NOT NULL DEFAULT '10',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `iniciado_em` datetime DEFAULT NULL,
  `finalizado_em` datetime DEFAULT NULL,
  `status` enum('criado','em_andamento','finalizado','cancelado') COLLATE utf8mb3_unicode_ci DEFAULT 'criado',
  `tempo_total` int DEFAULT '0',
  `tempo_limite` int DEFAULT '0',
  `total_acertos` int DEFAULT '0',
  `total_erros` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `nota_final` decimal(5,2) DEFAULT '0.00',
  `oculto` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `simulados_estatisticas`
--

CREATE TABLE `simulados_estatisticas` (
  `id` int NOT NULL,
  `simulado_id` int NOT NULL,
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `total_questoes` int DEFAULT '0',
  `acertos` int DEFAULT '0',
  `erros` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `tempo_medio` decimal(8,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `simulados_questoes`
--

CREATE TABLE `simulados_questoes` (
  `id` int NOT NULL,
  `simulado_id` int NOT NULL,
  `questao_index` int NOT NULL,
  `questao_id` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `enunciado` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a_file` text COLLATE utf8mb3_unicode_ci,
  `alternativa_b` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_b_file` text COLLATE utf8mb3_unicode_ci,
  `alternativa_c` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_c_file` text COLLATE utf8mb3_unicode_ci,
  `alternativa_d` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_d_file` text COLLATE utf8mb3_unicode_ci,
  `alternativa_e` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_e_file` text COLLATE utf8mb3_unicode_ci,
  `resposta_certa` varchar(1) COLLATE utf8mb3_unicode_ci NOT NULL,
  `resposta_aluno` varchar(1) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `acertou` tinyint(1) DEFAULT NULL,
  `respondido_em` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT '0',
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `dificuldade` enum('facil','medio','dificil') COLLATE utf8mb3_unicode_ci DEFAULT 'medio',
  `materias_path_json` json DEFAULT NULL COMMENT 'Array JSON: ["Disciplina","Tópico","Subtópico",...]'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `student_access_events`
--

CREATE TABLE `student_access_events` (
  `id` bigint NOT NULL,
  `student_id` int NOT NULL,
  `kind` enum('entrada','saida') COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_at` datetime NOT NULL,
  `confidence` decimal(6,5) DEFAULT NULL,
  `provider_presence_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registered_by_user_id` int DEFAULT NULL,
  `notified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `student_accommodations`
--

CREATE TABLE `student_accommodations` (
  `id` bigint NOT NULL,
  `aluno_id` int NOT NULL,
  `status` enum('rascunho','ativa','suspensa','encerrada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `tipo_adaptacao` enum('acesso','significativa') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'acesso',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `legal_basis` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consent_ref` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `student_face_profiles`
--

CREATE TABLE `student_face_profiles` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `external_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `consent_at` datetime NOT NULL,
  `consent_by_user_id` int DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `student_face_samples`
--

CREATE TABLE `student_face_samples` (
  `id` int NOT NULL,
  `face_profile_id` int NOT NULL,
  `provider_face_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `suporte_tickets`
--

CREATE TABLE `suporte_tickets` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `assunto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'geral',
  `modulo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('aberto','em_andamento','respondido','fechado') COLLATE utf8mb4_unicode_ci DEFAULT 'aberto',
  `prioridade` enum('baixa','normal','alta','urgente') COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `admin_atribuido_id` int DEFAULT NULL COMMENT 'Admin do admin_educatudo que está cuidando do ticket',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `fechado_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `suporte_tickets_mensagens`
--

CREATE TABLE `suporte_tickets_mensagens` (
  `id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `remetente_tipo` enum('aluno','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `remetente_id` int NOT NULL COMMENT 'ID do aluno ou admin',
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `anexo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nome do arquivo anexado',
  `lida` tinyint(1) DEFAULT '0',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tentativas_login`
--

CREATE TABLE `tentativas_login` (
  `id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb3_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'aluno, admin_escola, professor, pai',
  `motivo_falha` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'nickname_invalido, senha_invalida',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_curso`
--

CREATE TABLE `tipos_curso` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tudinha_analises`
--

CREATE TABLE `tudinha_analises` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `data_ate` date NOT NULL COMMENT 'Data limite da análise',
  `analise_completa` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Análise completa gerada pela IA',
  `dificuldades` text COLLATE utf8mb4_unicode_ci COMMENT 'Dificuldades identificadas',
  `facilidades` text COLLATE utf8mb4_unicode_ci COMMENT 'Facilidades identificadas',
  `observacoes` text COLLATE utf8mb4_unicode_ci COMMENT 'Observações gerais',
  `recomendacoes` text COLLATE utf8mb4_unicode_ci COMMENT 'Recomendações para pais e coordenadores',
  `resumo_estatisticas` text COLLATE utf8mb4_unicode_ci COMMENT 'Resumo das estatísticas analisadas',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL COMMENT 'ID do admin que gerou a análise',
  `anonymized_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `retention_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tudinha_conversas`
--

CREATE TABLE `tudinha_conversas` (
  `id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `total_interacoes` int DEFAULT '0',
  `ultima_atividade` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `excluida` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tudinha_mensagens`
--

CREATE TABLE `tudinha_mensagens` (
  `id` int NOT NULL,
  `conversa_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `mensagem` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('texto','imagem','audio') COLLATE utf8mb3_unicode_ci DEFAULT 'texto',
  `image_url` varchar(500) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `is_ia` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `anonymized_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `retention_reason` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `turmas`
--

CREATE TABLE `turmas` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Ex: 1ºA, 2ºB',
  `ano_letivo` int NOT NULL,
  `ano_letivo_id` int DEFAULT NULL,
  `serie` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie_id` int DEFAULT NULL,
  `curso_id` int DEFAULT NULL,
  `curso_novo_id` int DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tipo_ensino` varchar(30) COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `turmas_lista_config`
--

CREATE TABLE `turmas_lista_config` (
  `turma_id` int NOT NULL,
  `ano_letivo_id` int NOT NULL,
  `criterio_ordem` enum('alfabetica','meninas_primeiro','meninos_primeiro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alfabetica',
  `data_corte` date DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tutoriais`
--

CREATE TABLE `tutoriais` (
  `id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `link_youtube` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `tipo` enum('admin_escola') COLLATE utf8mb3_unicode_ci NOT NULL,
  `perfil_admin` enum('dev','diretor','coordenador','aee','financeiro','secretaria') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Aplicável apenas quando tipo = admin_escola',
  `nome` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Apenas para admins e pais',
  `senha_hash` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `avatar_url` varchar(500) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `criado_por` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `permissoes_admin_json` json DEFAULT NULL COMMENT 'Permissões administrativas por módulo e ação (visualizar, cadastrar, alterar, excluir)',
  `perfil_permissao_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios_consentimentos`
--

CREATE TABLE `usuarios_consentimentos` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `user_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accepted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `validacao_tokens_apps`
--

CREATE TABLE `validacao_tokens_apps` (
  `id` int NOT NULL,
  `token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `app` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tenant_slug` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `webhooks`
--

CREATE TABLE `webhooks` (
  `id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `endpoint` varchar(500) COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('chat_ia','chat','geral') COLLATE utf8mb3_unicode_ci NOT NULL,
  `escola_id` int DEFAULT NULL COMMENT 'NULL para webhook global',
  `ativo` tinyint(1) DEFAULT '1',
  `configuracao` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Configurações adicionais do webhook',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `accommodation_documents`
--
ALTER TABLE `accommodation_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_accdoc_accommodation` (`accommodation_id`);

--
-- Índices de tabela `accommodation_rules`
--
ALTER TABLE `accommodation_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_accrule_key` (`accommodation_id`,`rule_key`);

--
-- Índices de tabela `admin_perfis_permissao`
--
ALTER TABLE `admin_perfis_permissao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_admin_perfis_permissao_nome` (`nome`),
  ADD KEY `idx_admin_perfis_permissao_tipo_base` (`tipo_base`),
  ADD KEY `idx_admin_perfis_permissao_ativo` (`ativo`),
  ADD KEY `idx_admin_perfis_permissao_criado_por` (`criado_por`);

--
-- Índices de tabela `ai_jobs`
--
ALTER TABLE `ai_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_created` (`status`,`created_at`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_cleanup` (`completed_at`);

--
-- Índices de tabela `alertas_sensiveis`
--
ALTER TABLE `alertas_sensiveis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alertas_aluno` (`aluno_id`),
  ADD KEY `idx_alertas_status` (`status`),
  ADD KEY `idx_alertas_nivel` (`nivel`),
  ADD KEY `idx_alertas_turma` (`turma_id`),
  ADD KEY `idx_alertas_expires` (`expires_at`),
  ADD KEY `idx_alertas_anonymized` (`anonymized_at`);

--
-- Índices de tabela `alertas_sensiveis_acoes`
--
ALTER TABLE `alertas_sensiveis_acoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alerta` (`alerta_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_alertas_acoes_expires` (`expires_at`),
  ADD KEY `idx_alertas_acoes_anonymized` (`anonymized_at`);

--
-- Índices de tabela `alunos`
--
ALTER TABLE `alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ra` (`ra`),
  ADD UNIQUE KEY `nickname` (`nickname`),
  ADD KEY `idx_turma` (`turma_id`),
  ADD KEY `idx_responsavel` (`responsavel_id`),
  ADD KEY `idx_ativo` (`ativo`),
  ADD KEY `idx_alunos_codigo_aluno` (`codigo_aluno`),
  ADD KEY `idx_alunos_cpf` (`cpf`);

--
-- Índices de tabela `alunos_acoes_diarias`
--
ALTER TABLE `alunos_acoes_diarias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `acao` (`acao`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_aluno_acao_data` (`aluno_id`,`acao`,`created_at`);

--
-- Índices de tabela `alunos_historico_status`
--
ALTER TABLE `alunos_historico_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_status_student` (`student_id`),
  ADD KEY `idx_student_status_created` (`created_at`);

--
-- Índices de tabela `alunos_ocorrencias`
--
ALTER TABLE `alunos_ocorrencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ocorrencias_aluno` (`aluno_id`),
  ADD KEY `idx_ocorrencias_data` (`data_ocorrencia`),
  ADD KEY `idx_ocorrencias_gravidade` (`nivel_gravidade`),
  ADD KEY `idx_ocorrencias_enviar` (`enviar_pais`);

--
-- Índices de tabela `alunos_ocorrencias_itens`
--
ALTER TABLE `alunos_ocorrencias_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ocorrencia` (`ocorrencia_id`),
  ADD KEY `idx_aluno` (`aluno_id`);

--
-- Índices de tabela `alunos_onboarding`
--
ALTER TABLE `alunos_onboarding`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_aluno` (`aluno_id`),
  ADD KEY `idx_completado` (`completado`);

--
-- Índices de tabela `alunos_responsaveis`
--
ALTER TABLE `alunos_responsaveis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_aluno_responsavel` (`aluno_id`,`responsavel_id`),
  ADD KEY `idx_alunos_responsaveis_aluno` (`aluno_id`),
  ADD KEY `idx_alunos_responsaveis_responsavel` (`responsavel_id`),
  ADD KEY `idx_alunos_responsaveis_financeiro` (`is_financeiro`);

--
-- Índices de tabela `alunos_seguranca`
--
ALTER TABLE `alunos_seguranca`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_aluno` (`aluno_id`);

--
-- Índices de tabela `alunos_sessoes_acesso`
--
ALTER TABLE `alunos_sessoes_acesso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `login_at` (`login_at`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_ativo` (`aluno_id`,`status`),
  ADD KEY `idx_online` (`status`,`login_at`);

--
-- Índices de tabela `alunos_turmas_historico`
--
ALTER TABLE `alunos_turmas_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_turma` (`turma_id`),
  ADD KEY `idx_ano` (`ano_letivo`);

--
-- Índices de tabela `alunos_turma_chamada`
--
ALTER TABLE `alunos_turma_chamada`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_aluno_turma_ano` (`aluno_id`,`turma_id`,`ano_letivo_id`),
  ADD UNIQUE KEY `uq_turma_numero` (`turma_id`,`ano_letivo_id`,`numero_chamada`),
  ADD KEY `idx_chamada_turma` (`turma_id`,`ano_letivo_id`),
  ADD KEY `fk_chamada_ano` (`ano_letivo_id`);

--
-- Índices de tabela `ano_letivo`
--
ALTER TABLE `ano_letivo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ano_letivo_ano` (`ano`),
  ADD KEY `idx_ano_letivo_ativo` (`ativo`);

--
-- Índices de tabela `apostilas_ia`
--
ALTER TABLE `apostilas_ia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_professor` (`professor_id`);

--
-- Índices de tabela `apostila_ia_chunks`
--
ALTER TABLE `apostila_ia_chunks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_apostila` (`apostila_id`),
  ADD KEY `idx_paginas` (`pagina_inicio`,`pagina_fim`);

--
-- Índices de tabela `apostila_ia_conversas`
--
ALTER TABLE `apostila_ia_conversas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_apostila_professor` (`apostila_id`,`professor_id`);

--
-- Índices de tabela `apostila_ia_exercicios`
--
ALTER TABLE `apostila_ia_exercicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_apostila_pagina` (`apostila_id`,`pagina`),
  ADD KEY `idx_tema` (`tema`);

--
-- Índices de tabela `apostila_ia_paginas`
--
ALTER TABLE `apostila_ia_paginas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_apostila_pagina` (`apostila_id`,`numero_pagina`);

--
-- Índices de tabela `apostila_ia_turmas`
--
ALTER TABLE `apostila_ia_turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_apostila_turma` (`apostila_id`,`turma_id`),
  ADD KEY `idx_turma` (`turma_id`);

--
-- Índices de tabela `assessment_versions`
--
ALTER TABLE `assessment_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assessver_assessment_aluno` (`assessment_id`,`aluno_id`),
  ADD KEY `idx_assessver_status` (`approval_status`);

--
-- Índices de tabela `assessment_version_logs`
--
ALTER TABLE `assessment_version_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assesslog_version` (`assessment_version_id`),
  ADD KEY `idx_assesslog_aluno` (`aluno_id`);

--
-- Índices de tabela `assinaturas_creditos`
--
ALTER TABLE `assinaturas_creditos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assinaturas_user` (`user_type`,`user_id`),
  ADD KEY `idx_assinaturas_ativa` (`ativa`),
  ADD KEY `idx_assinaturas_plano` (`plano_id`);

--
-- Índices de tabela `aulas_online`
--
ALTER TABLE `aulas_online`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aulas_online_inicio` (`inicio_em`),
  ADD KEY `idx_aulas_online_publicado` (`publicado`),
  ADD KEY `idx_aulas_online_ativo` (`ativo`),
  ADD KEY `idx_aulas_online_criado_por` (`criado_por`),
  ADD KEY `idx_aulas_online_panda_status` (`panda_integracao_status`);

--
-- Índices de tabela `aulas_online_arquivos`
--
ALTER TABLE `aulas_online_arquivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aula_id` (`aula_id`);

--
-- Índices de tabela `aulas_online_turmas`
--
ALTER TABLE `aulas_online_turmas`
  ADD PRIMARY KEY (`aula_online_id`,`turma_id`),
  ADD KEY `idx_aulas_online_turmas_turma` (`turma_id`);

--
-- Índices de tabela `avatares_alunos`
--
ALTER TABLE `avatares_alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_aluno_avatar` (`aluno_id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_criado_em` (`criado_em`),
  ADD KEY `idx_avatar_url` (`avatar_url`),
  ADD KEY `idx_avatar_seed` (`avatar_seed`);

--
-- Índices de tabela `ava_atividades`
--
ALTER TABLE `ava_atividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_atv_disc` (`disciplina_id`),
  ADD KEY `idx_ava_atv_modulo` (`modulo_id`),
  ADD KEY `idx_ava_atv_aula` (`aula_id`),
  ADD KEY `idx_ava_atv_professor` (`professor_id`),
  ADD KEY `idx_ava_atv_rubrica` (`rubrica_id`);

--
-- Índices de tabela `ava_atividade_entregas`
--
ALTER TABLE `ava_atividade_entregas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ava_entrega` (`atividade_id`,`aluno_id`),
  ADD KEY `idx_ava_entrega_aluno` (`aluno_id`);

--
-- Índices de tabela `ava_atividade_entrega_arquivos`
--
ALTER TABLE `ava_atividade_entrega_arquivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_entrega_arq_entrega` (`entrega_id`);

--
-- Índices de tabela `ava_aulas`
--
ALTER TABLE `ava_aulas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_aulas_modulo` (`modulo_id`),
  ADD KEY `idx_ava_aulas_professor` (`professor_id`);

--
-- Índices de tabela `ava_aulas_ao_vivo`
--
ALTER TABLE `ava_aulas_ao_vivo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_live_disc` (`disciplina_id`),
  ADD KEY `idx_ava_live_modulo` (`modulo_id`),
  ADD KEY `idx_ava_live_professor` (`professor_id`),
  ADD KEY `idx_ava_live_inicio` (`inicio_em`);

--
-- Índices de tabela `ava_aula_anexos`
--
ALTER TABLE `ava_aula_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_anexos_aula` (`aula_id`);

--
-- Índices de tabela `ava_categorias`
--
ALTER TABLE `ava_categorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_categorias_slug` (`slug`);

--
-- Índices de tabela `ava_certificados`
--
ALTER TABLE `ava_certificados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ava_cert_codigo` (`codigo`),
  ADD UNIQUE KEY `uk_ava_cert_aluno_disc` (`aluno_id`,`disciplina_id`),
  ADD KEY `idx_ava_cert_aluno` (`aluno_id`),
  ADD KEY `idx_ava_cert_disc` (`disciplina_id`);

--
-- Índices de tabela `ava_comentarios`
--
ALTER TABLE `ava_comentarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_coment_aula` (`aula_id`),
  ADD KEY `idx_ava_coment_parent` (`parent_id`);

--
-- Índices de tabela `ava_cursos`
--
ALTER TABLE `ava_cursos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_cursos_categoria` (`categoria_id`),
  ADD KEY `idx_ava_cursos_status` (`status`);

--
-- Índices de tabela `ava_disciplinas`
--
ALTER TABLE `ava_disciplinas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_disc_curso` (`curso_id`),
  ADD KEY `idx_ava_disc_semestre` (`semestre_id`),
  ADD KEY `idx_ava_disc_professor` (`professor_id`),
  ADD KEY `idx_ava_disc_tutor` (`tutor_id`),
  ADD KEY `idx_ava_disc_materia` (`materia_id`),
  ADD KEY `idx_ava_disc_turma` (`turma_id`);

--
-- Índices de tabela `ava_disciplina_avaliacoes`
--
ALTER TABLE `ava_disciplina_avaliacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ava_disc_aval` (`disciplina_id`,`prova_id`),
  ADD KEY `idx_ava_disc_aval_disc` (`disciplina_id`),
  ADD KEY `idx_ava_disc_aval_prova` (`prova_id`);

--
-- Índices de tabela `ava_matriculas_disciplina`
--
ALTER TABLE `ava_matriculas_disciplina`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ava_matricula` (`aluno_id`,`disciplina_id`),
  ADD KEY `idx_ava_matricula_disc` (`disciplina_id`),
  ADD KEY `idx_ava_matricula_aluno` (`aluno_id`);

--
-- Índices de tabela `ava_modulos`
--
ALTER TABLE `ava_modulos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_modulos_disc` (`disciplina_id`);

--
-- Índices de tabela `ava_progresso_aula`
--
ALTER TABLE `ava_progresso_aula`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ava_prog_aula` (`aluno_id`,`aula_id`),
  ADD KEY `idx_ava_prog_aula_aula` (`aula_id`);

--
-- Índices de tabela `ava_progresso_video`
--
ALTER TABLE `ava_progresso_video`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ava_prog_video` (`aluno_id`,`aula_id`),
  ADD KEY `idx_ava_prog_video_aula` (`aula_id`);

--
-- Índices de tabela `ava_rubricas`
--
ALTER TABLE `ava_rubricas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_rubricas_disc` (`disciplina_id`);

--
-- Índices de tabela `ava_rubrica_criterios`
--
ALTER TABLE `ava_rubrica_criterios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_rub_crit_rubrica` (`rubrica_id`);

--
-- Índices de tabela `ava_semestres`
--
ALTER TABLE `ava_semestres`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ava_semestres_curso` (`curso_id`);

--
-- Índices de tabela `billing_message_log`
--
ALTER TABLE `billing_message_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_installment` (`installment_id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Índices de tabela `billing_rule_config`
--
ALTER TABLE `billing_rule_config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `bncc_habilidades`
--
ALTER TABLE `bncc_habilidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_bncc_codigo` (`codigo`),
  ADD KEY `idx_bncc_componente` (`componente`),
  ADD KEY `idx_bncc_ano` (`ano_serie`);

--
-- Índices de tabela `boletim_componentes`
--
ALTER TABLE `boletim_componentes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_boletim_componentes_regra_codigo` (`regra_id`,`codigo`),
  ADD KEY `idx_boletim_componentes_regra` (`regra_id`),
  ADD KEY `idx_boletim_componentes_bloco` (`bloco_id`);

--
-- Índices de tabela `boletim_log_geracoes`
--
ALTER TABLE `boletim_log_geracoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_boletim_log_geracoes_regra` (`regra_id`,`created_at`);

--
-- Índices de tabela `boletim_notas_manuais`
--
ALTER TABLE `boletim_notas_manuais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_boletim_notas_manuais_item_materia` (`componente_id`,`aluno_id`,`periodo_ref`,`materia_id`),
  ADD KEY `idx_boletim_notas_manuais_aluno` (`aluno_id`),
  ADD KEY `idx_boletim_notas_manuais_regra` (`regra_id`),
  ADD KEY `idx_boletim_notas_manuais_componente` (`componente_id`);

--
-- Índices de tabela `boletim_observacoes`
--
ALTER TABLE `boletim_observacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_boletim_observacoes_aluno` (`aluno_id`);

--
-- Índices de tabela `boletim_regras`
--
ALTER TABLE `boletim_regras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_boletim_regras_ativo` (`ativo`);

--
-- Índices de tabela `boletim_resultados_gerados`
--
ALTER TABLE `boletim_resultados_gerados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_boletim_resultados_aluno` (`aluno_id`),
  ADD KEY `idx_boletim_resultados_regra` (`regra_id`),
  ADD KEY `idx_boletim_resultados_lookup` (`regra_id`,`aluno_id`,`periodo_ref`),
  ADD KEY `idx_boletim_resultados_preview` (`regra_id`,`aluno_id`,`periodo_ref`,`preview`);

--
-- Índices de tabela `cadernos_aluno`
--
ALTER TABLE `cadernos_aluno`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_caderno_aluno_id` (`aluno_id`),
  ADD KEY `idx_caderno_materia` (`materia_id`),
  ADD KEY `idx_caderno_pasta` (`pasta_id`);

--
-- Índices de tabela `cadernos_aluno_anexos`
--
ALTER TABLE `cadernos_aluno_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_anexo_caderno` (`caderno_id`);

--
-- Índices de tabela `cadernos_aluno_pastas`
--
ALTER TABLE `cadernos_aluno_pastas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pasta_aluno` (`aluno_id`);

--
-- Índices de tabela `calendario_letivo`
--
ALTER TABLE `calendario_letivo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_calendario_ano` (`ano`);

--
-- Índices de tabela `calendario_letivo_eventos`
--
ALTER TABLE `calendario_letivo_eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cal_eventos_calendario` (`calendario_id`),
  ADD KEY `idx_cal_eventos_data` (`data_inicio`,`data_fim`);

--
-- Índices de tabela `carteira_movimentacoes`
--
ALTER TABLE `carteira_movimentacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mov_user_created` (`user_type`,`user_id`,`created_at`),
  ADD KEY `idx_mov_tipo` (`tipo`);

--
-- Índices de tabela `carteira_usuarios`
--
ALTER TABLE `carteira_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_carteira_user` (`user_type`,`user_id`),
  ADD KEY `idx_carteira_user_type_id` (`user_type`,`user_id`);

--
-- Índices de tabela `chat_professores_alunos`
--
ALTER TABLE `chat_professores_alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `aluno_professor` (`aluno_id`,`professor_id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `ultima_atividade` (`ultima_atividade`);

--
-- Índices de tabela `chat_professores_alunos_anexos`
--
ALTER TABLE `chat_professores_alunos_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mensagem_id` (`mensagem_id`);

--
-- Índices de tabela `chat_professores_alunos_mensagens`
--
ALTER TABLE `chat_professores_alunos_mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_id` (`chat_id`),
  ADD KEY `remetente_id` (`remetente_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Índices de tabela `compras_creditos`
--
ALTER TABLE `compras_creditos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_compras_asaas_payment` (`asaas_payment_id`),
  ADD KEY `idx_compras_user` (`user_type`,`user_id`),
  ADD KEY `idx_compras_status` (`status`),
  ADD KEY `idx_compras_gateway` (`gateway_id`),
  ADD KEY `fk_compras_pacote` (`pacote_id`);

--
-- Índices de tabela `config_dev`
--
ALTER TABLE `config_dev`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_dev_settings_key` (`key_name`);

--
-- Índices de tabela `config_escolas_database`
--
ALTER TABLE `config_escolas_database`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_escola_nome` (`escola_nome`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `config_layout`
--
ALTER TABLE `config_layout`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `idx_config_layout_config_key` (`config_key`);

--
-- Índices de tabela `config_simulados`
--
ALTER TABLE `config_simulados`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `curso`
--
ALTER TABLE `curso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_curso_ativo` (`ativo`),
  ADD KEY `idx_curso_ordem` (`ordem`);

--
-- Índices de tabela `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cursos_tipo_nome` (`tipo_curso_id`,`nome`),
  ADD UNIQUE KEY `uq_cursos_slug` (`slug`),
  ADD KEY `idx_cursos_tipo` (`tipo_curso_id`),
  ADD KEY `idx_cursos_ativo` (`ativo`);

--
-- Índices de tabela `dashboard_jornadas_resumo`
--
ALTER TABLE `dashboard_jornadas_resumo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dashboard_jornadas_resumo_segmento` (`segmento`);

--
-- Índices de tabela `diario_aulas`
--
ALTER TABLE `diario_aulas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_diario_grade_data` (`grade_horaria_id`,`data_aula`),
  ADD KEY `idx_diario_prof_data` (`professor_id`,`data_aula`),
  ADD KEY `idx_diario_turma_data` (`turma_id`,`data_aula`),
  ADD KEY `idx_diario_status` (`status`);

--
-- Índices de tabela `diario_frequencias`
--
ALTER TABLE `diario_frequencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_diario_aluno` (`diario_aula_id`,`aluno_id`),
  ADD KEY `idx_diario_freq_aluno` (`aluno_id`),
  ADD KEY `idx_diario_freq_situacao` (`situacao`);

--
-- Índices de tabela `drive_compartilhamentos`
--
ALTER TABLE `drive_compartilhamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_share` (`item_id`,`shared_with_id`,`shared_with_type`),
  ADD KEY `idx_shared_with` (`shared_with_id`,`shared_with_type`);

--
-- Índices de tabela `drive_itens`
--
ALTER TABLE `drive_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner` (`owner_id`,`owner_type`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_owner_parent` (`owner_id`,`owner_type`,`parent_id`);

--
-- Índices de tabela `educalabs_messages`
--
ALTER TABLE `educalabs_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_id` (`project_id`);

--
-- Índices de tabela `educalabs_projects`
--
ALTER TABLE `educalabs_projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_public_id` (`public_id`),
  ADD UNIQUE KEY `uniq_share_id` (`share_id`),
  ADD KEY `idx_owner_id` (`owner_id`);

--
-- Índices de tabela `educalabs_tokens`
--
ALTER TABLE `educalabs_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Índices de tabela `educa_hits_requests`
--
ALTER TABLE `educa_hits_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_educa_hits_requests_user` (`user_id`),
  ADD KEY `idx_educa_hits_requests_status` (`status`),
  ADD KEY `idx_educa_hits_requests_created` (`created_at`),
  ADD KEY `idx_educa_hits_requests_archived` (`archived_at`);

--
-- Índices de tabela `educa_hits_tracks`
--
ALTER TABLE `educa_hits_tracks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_educa_hits_tracks_request` (`request_id`),
  ADD KEY `idx_educa_hits_tracks_status` (`status`),
  ADD KEY `idx_educa_hits_tracks_created` (`created_at`);

--
-- Índices de tabela `educa_hits_track_classes`
--
ALTER TABLE `educa_hits_track_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_track_class` (`track_id`,`class_id`),
  ADD KEY `idx_educa_hits_tc_class` (`class_id`);

--
-- Índices de tabela `educa_hits_track_grades`
--
ALTER TABLE `educa_hits_track_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_track_grade` (`track_id`,`grade`),
  ADD KEY `idx_educa_hits_tg_grade` (`grade`);

--
-- Índices de tabela `educa_hits_track_schools`
--
ALTER TABLE `educa_hits_track_schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_track_school` (`track_id`,`school_id`),
  ADD KEY `idx_educa_hits_ts_school` (`school_id`);

--
-- Índices de tabela `educa_hits_track_users`
--
ALTER TABLE `educa_hits_track_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_track_user` (`track_id`,`user_id`),
  ADD KEY `idx_educa_hits_tu_user` (`user_id`);

--
-- Índices de tabela `enem_alternativas`
--
ALTER TABLE `enem_alternativas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Índices de tabela `enem_disciplinas`
--
ALTER TABLE `enem_disciplinas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Índices de tabela `enem_provas`
--
ALTER TABLE `enem_provas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `enem_questoes`
--
ALTER TABLE `enem_questoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Índices de tabela `enem_questoes_arquivos`
--
ALTER TABLE `enem_questoes_arquivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Índices de tabela `enem_questoes_vinculo`
--
ALTER TABLE `enem_questoes_vinculo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ano_indice` (`ano`,`indice`);

--
-- Índices de tabela `enrollment`
--
ALTER TABLE `enrollment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_enrollment_contrato_token` (`contrato_token`),
  ADD KEY `idx_enrollment_aluno` (`aluno_id`),
  ADD KEY `idx_enrollment_turma` (`turma_id`),
  ADD KEY `idx_enrollment_ano_letivo` (`ano_letivo_id`),
  ADD KEY `idx_enrollment_status` (`status`),
  ADD KEY `idx_enrollment_tipo` (`tipo`);

--
-- Índices de tabela `enrollment_audit`
--
ALTER TABLE `enrollment_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_enrollment` (`enrollment_id`);

--
-- Índices de tabela `enrollment_score`
--
ALTER TABLE `enrollment_score`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_score_aluno_ciclo` (`aluno_id`,`ciclo`),
  ADD KEY `idx_score_faixa` (`faixa`);

--
-- Índices de tabela `estatisticas_dama`
--
ALTER TABLE `estatisticas_dama`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_aluno` (`aluno_id`);

--
-- Índices de tabela `execucao_exercicios`
--
ALTER TABLE `execucao_exercicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_execucao_aluno` (`aluno_id`),
  ADD KEY `idx_execucao_lista` (`lista_id`),
  ADD KEY `idx_execucao_lista_status` (`lista_id`,`status`);

--
-- Índices de tabela `exercicios`
--
ALTER TABLE `exercicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jornada` (`jornada_id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_gerado_ia` (`gerado_ia`),
  ADD KEY `idx_aprovado` (`aprovado`),
  ADD KEY `exercicios_ibfk_2` (`lista_id`);

--
-- Índices de tabela `exercicios_estatisticas_alunos`
--
ALTER TABLE `exercicios_estatisticas_alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_estatistica` (`aluno_id`,`materia`,`serie`),
  ADD KEY `idx_estatisticas_aluno` (`aluno_id`);

--
-- Índices de tabela `exercicios_estatisticas_turmas`
--
ALTER TABLE `exercicios_estatisticas_turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_estatistica_turma` (`turma_id`,`materia`,`serie`),
  ADD KEY `idx_estatisticas_turma` (`turma_id`);

--
-- Índices de tabela `exercicios_execucoes`
--
ALTER TABLE `exercicios_execucoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_execucao_ativa` (`aluno_id`,`lista_id`,`status`),
  ADD KEY `idx_execucao_aluno` (`aluno_id`),
  ADD KEY `idx_execucao_lista` (`lista_id`);

--
-- Índices de tabela `exercicios_historico`
--
ALTER TABLE `exercicios_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lista_id` (`lista_id`),
  ADD KEY `sessao_id` (`sessao_id`),
  ADD KEY `idx_aluno_lista_data` (`aluno_id`,`lista_id`,`data_execucao`),
  ADD KEY `idx_aluno_data` (`aluno_id`,`data_execucao`);

--
-- Índices de tabela `exercicios_respostas`
--
ALTER TABLE `exercicios_respostas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessao` (`sessao_id`),
  ADD KEY `idx_exercicio` (`exercicio_id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_answered_at` (`answered_at`);

--
-- Índices de tabela `exercicios_sessoes`
--
ALTER TABLE `exercicios_sessoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_lista` (`lista_id`),
  ADD KEY `idx_started_at` (`started_at`);

--
-- Índices de tabela `facial_devices`
--
ALTER TABLE `facial_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_facial_device_uid` (`device_uid`),
  ADD UNIQUE KEY `uq_facial_device_token` (`token_hash`),
  ADD KEY `idx_facial_device_enabled` (`enabled`);

--
-- Índices de tabela `facial_device_pairing_codes`
--
ALTER TABLE `facial_device_pairing_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_facial_pairing_code_hash` (`code_hash`),
  ADD KEY `idx_facial_pairing_expiration` (`expires_at`,`used_at`);

--
-- Índices de tabela `faltas_eventos`
--
ALTER TABLE `faltas_eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_faltas_eventos_ano` (`ano_letivo`),
  ADD KEY `idx_faltas_eventos_ativo` (`ativo`);

--
-- Índices de tabela `faltas_lancamentos`
--
ALTER TABLE `faltas_lancamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_faltas_evento_aluno_materia` (`evento_id`,`aluno_id`,`materia_id`),
  ADD KEY `idx_faltas_lanc_aluno` (`aluno_id`),
  ADD KEY `idx_faltas_lanc_materia` (`materia_id`);

--
-- Índices de tabela `financeiro_valores_mensais`
--
ALTER TABLE `financeiro_valores_mensais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mes_referencia` (`mes_referencia`),
  ADD KEY `idx_mes_referencia` (`mes_referencia`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status_valores_cobrados_mensais` (`status`),
  ADD KEY `idx_data_vencimento_valores_cobrados_mensais` (`data_vencimento`);

--
-- Índices de tabela `finance_audit`
--
ALTER TABLE `finance_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entidade` (`entidade`,`entidade_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Índices de tabela `finance_bank_accounts`
--
ALTER TABLE `finance_bank_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `finance_bills`
--
ALTER TABLE `finance_bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Índices de tabela `finance_charges`
--
ALTER TABLE `finance_charges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_vencimento` (`data_vencimento`),
  ADD KEY `idx_batch_id` (`batch_id`);

--
-- Índices de tabela `finance_chart_accounts`
--
ALTER TABLE `finance_chart_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `finance_config`
--
ALTER TABLE `finance_config`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `finance_contracts`
--
ALTER TABLE `finance_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_ano_letivo` (`ano_letivo_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `finance_contract_discounts`
--
ALTER TABLE `finance_contract_discounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contract` (`contract_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `finance_contract_items`
--
ALTER TABLE `finance_contract_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contract` (`contract_id`);

--
-- Índices de tabela `finance_discount_rules`
--
ALTER TABLE `finance_discount_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `finance_installments`
--
ALTER TABLE `finance_installments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contract` (`contract_id`),
  ADD KEY `idx_vencimento` (`data_vencimento`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `finance_ledger`
--
ALTER TABLE `finance_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_data` (`data_lancamento`),
  ADD KEY `idx_ref` (`referencia_tipo`,`referencia_id`);

--
-- Índices de tabela `finance_payments`
--
ALTER TABLE `finance_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_installment` (`installment_id`),
  ADD KEY `idx_data` (`data_pagamento`);

--
-- Índices de tabela `finance_plans`
--
ALTER TABLE `finance_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ano` (`ano_letivo_id`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `finance_plan_items`
--
ALTER TABLE `finance_plan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plan` (`plan_id`);

--
-- Índices de tabela `finance_price_table`
--
ALTER TABLE `finance_price_table`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ano_letivo` (`ano_letivo_id`),
  ADD KEY `idx_serie` (`serie_id`),
  ADD KEY `idx_categoria` (`categoria`);

--
-- Índices de tabela `finance_receipts`
--
ALTER TABLE `finance_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_numero` (`numero`),
  ADD KEY `idx_aluno` (`aluno_id`);

--
-- Índices de tabela `finance_renegotiations`
--
ALTER TABLE `finance_renegotiations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_contract` (`contract_id`);

--
-- Índices de tabela `flashcards_baralhos`
--
ALTER TABLE `flashcards_baralhos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_flashcard_decks_aluno` (`aluno_id`);

--
-- Índices de tabela `flashcards_cartas`
--
ALTER TABLE `flashcards_cartas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_flashcards_deck` (`deck_id`);

--
-- Índices de tabela `flashcards_modelos`
--
ALTER TABLE `flashcards_modelos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_templates_lookup` (`topic_normalized`,`grade`,`quantity`);

--
-- Índices de tabela `flashcards_modelos_cartas`
--
ALTER TABLE `flashcards_modelos_cartas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tc_template` (`template_id`);

--
-- Índices de tabela `flashcard_explicacoes`
--
ALTER TABLE `flashcard_explicacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno_deck_card` (`aluno_id`,`deck_id`,`card_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Índices de tabela `forum_anexos`
--
ALTER TABLE `forum_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_forum_att_topic` (`topic_id`),
  ADD KEY `idx_forum_att_reply` (`reply_id`);

--
-- Índices de tabela `forum_denuncias`
--
ALTER TABLE `forum_denuncias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_forum_reports_status` (`status`),
  ADD KEY `idx_forum_reports_topic` (`topic_id`),
  ADD KEY `idx_forum_reports_reply` (`reply_id`);

--
-- Índices de tabela `forum_moderacao_alertas`
--
ALTER TABLE `forum_moderacao_alertas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Índices de tabela `forum_respostas`
--
ALTER TABLE `forum_respostas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_forum_replies_topic` (`topic_id`),
  ADD KEY `idx_forum_replies_author` (`author_id`,`author_role`),
  ADD KEY `idx_forum_replies_best` (`topic_id`,`is_best_answer`);

--
-- Índices de tabela `forum_topicos`
--
ALTER TABLE `forum_topicos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_forum_topics_author` (`author_id`,`author_role`),
  ADD KEY `idx_forum_topics_resolved` (`is_resolved`),
  ADD KEY `idx_forum_topics_created` (`created_at`),
  ADD KEY `idx_forum_topics_turma` (`turma_id`);

--
-- Índices de tabela `forum_topicos_turmas`
--
ALTER TABLE `forum_topicos_turmas`
  ADD PRIMARY KEY (`topic_id`,`turma_id`),
  ADD KEY `idx_turma` (`turma_id`);

--
-- Índices de tabela `forum_usuarios_reputacao`
--
ALTER TABLE `forum_usuarios_reputacao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_forum_rep_user` (`user_id`,`user_role`);

--
-- Índices de tabela `forum_votos`
--
ALTER TABLE `forum_votos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_forum_votes_reply_voter` (`reply_id`,`voter_id`,`voter_role`),
  ADD KEY `idx_forum_votes_reply` (`reply_id`);

--
-- Índices de tabela `grade_horaria`
--
ALTER TABLE `grade_horaria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dia_semana` (`dia_semana`),
  ADD KEY `idx_turma` (`turma_id`),
  ADD KEY `idx_professor` (`professor_id`),
  ADD KEY `idx_periodo` (`periodo`),
  ADD KEY `fk_gh_materia` (`materia_id`);

--
-- Índices de tabela `ingles_conversas`
--
ALTER TABLE `ingles_conversas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aluno_id` (`aluno_id`);

--
-- Índices de tabela `ingles_mensagens`
--
ALTER TABLE `ingles_mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversa_id` (`conversa_id`);

--
-- Índices de tabela `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_items_codigo` (`codigo`),
  ADD KEY `idx_inventory_items_categoria` (`categoria`),
  ADD KEY `idx_inventory_items_ativo` (`ativo`);

--
-- Índices de tabela `inventory_lots`
--
ALTER TABLE `inventory_lots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_lots_item_warehouse` (`item_id`,`warehouse_id`),
  ADD KEY `idx_inventory_lots_validade` (`validade`),
  ADD KEY `idx_inventory_lots_quantidade` (`quantidade_atual`);

--
-- Índices de tabela `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_movements_item` (`item_id`),
  ADD KEY `idx_inventory_movements_created` (`created_at`),
  ADD KEY `idx_inventory_movements_tipo` (`tipo`);

--
-- Índices de tabela `inventory_requisitions`
--
ALTER TABLE `inventory_requisitions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_requisitions_status` (`status`),
  ADD KEY `idx_inventory_requisitions_item` (`item_id`);

--
-- Índices de tabela `inventory_suppliers`
--
ALTER TABLE `inventory_suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_suppliers_nome` (`nome`),
  ADD KEY `idx_inventory_suppliers_cnpj` (`cnpj`);

--
-- Índices de tabela `inventory_warehouses`
--
ALTER TABLE `inventory_warehouses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_warehouses_location` (`location_id`),
  ADD KEY `idx_inventory_warehouses_ativo` (`ativo`);

--
-- Índices de tabela `jogos_acoes`
--
ALTER TABLE `jogos_acoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partida_id` (`partida_id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `timestamp` (`timestamp`);

--
-- Índices de tabela `jogos_milhao_partidas`
--
ALTER TABLE `jogos_milhao_partidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_partidas_aluno` (`aluno_id`),
  ADD KEY `idx_partidas_status` (`status`),
  ADD KEY `idx_last_activity_status` (`last_activity`,`status`);

--
-- Índices de tabela `jogos_milhao_perguntas`
--
ALTER TABLE `jogos_milhao_perguntas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_perguntas_nivel` (`nivel_dificuldade`),
  ADD KEY `idx_perguntas_tema` (`tema`),
  ADD KEY `idx_perguntas_ativa` (`ativa`);

--
-- Índices de tabela `jogos_milhao_respostas`
--
ALTER TABLE `jogos_milhao_respostas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pergunta_id` (`pergunta_id`),
  ADD KEY `idx_respostas_partida` (`partida_id`);

--
-- Índices de tabela `jogos_sessoes`
--
ALTER TABLE `jogos_sessoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `partida_id` (`partida_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Índices de tabela `jogos_tokens_externos`
--
ALTER TABLE `jogos_tokens_externos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Índices de tabela `jornadas`
--
ALTER TABLE `jornadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_professor` (`professor_id`),
  ADD KEY `idx_turma` (`turma_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_jornadas_turma` (`turma_id`),
  ADD KEY `idx_jornadas_professor` (`professor_id`),
  ADD KEY `idx_plano_aula_id` (`plano_aula_id`),
  ADD KEY `idx_jornadas_turma_ativo` (`turma_id`,`ativo`);

--
-- Índices de tabela `jornadas_aulas`
--
ALTER TABLE `jornadas_aulas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jornada_id` (`jornada_id`),
  ADD KEY `ordem` (`ordem`),
  ADD KEY `idx_ja_jornada_status` (`jornada_id`,`status`);

--
-- Índices de tabela `jornadas_blocos_conteudo`
--
ALTER TABLE `jornadas_blocos_conteudo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jornada_id` (`jornada_id`),
  ADD KEY `tipo_bloco_id` (`tipo_bloco_id`),
  ADD KEY `ordem` (`ordem`),
  ADD KEY `idx_jornadas_blocos_ordem` (`jornada_id`,`ordem`);

--
-- Índices de tabela `jornadas_duvidas`
--
ALTER TABLE `jornadas_duvidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `aula_id` (`aula_id`);

--
-- Índices de tabela `jornadas_exercicios`
--
ALTER TABLE `jornadas_exercicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jornada_id` (`jornada_id`),
  ADD KEY `aula_id` (`aula_id`),
  ADD KEY `idx_je_jornada_status` (`jornada_id`,`status`);

--
-- Índices de tabela `jornadas_exercicios_auditoria`
--
ALTER TABLE `jornadas_exercicios_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jornadas_auditoria_aluno` (`aluno_id`),
  ADD KEY `idx_jornadas_auditoria_jornada` (`jornada_id`),
  ADD KEY `idx_jornadas_auditoria_modulo` (`modulo_id`),
  ADD KEY `idx_jornadas_auditoria_exercicio` (`exercicio_id`),
  ADD KEY `idx_jornadas_auditoria_acao` (`tipo_acao`),
  ADD KEY `idx_jornadas_auditoria_data` (`created_at`);

--
-- Índices de tabela `jornadas_materias`
--
ALTER TABLE `jornadas_materias`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `jornadas_mensagens`
--
ALTER TABLE `jornadas_mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jornada_id` (`jornada_id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `remetente_id` (`remetente_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Índices de tabela `jornadas_mensagens_anexos`
--
ALTER TABLE `jornadas_mensagens_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mensagem_id` (`mensagem_id`);

--
-- Índices de tabela `jornadas_modulos`
--
ALTER TABLE `jornadas_modulos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jornada_id` (`jornada_id`),
  ADD KEY `aula_id` (`aula_id`),
  ADD KEY `tipo_modulo` (`tipo_modulo`),
  ADD KEY `idx_jm_jornada` (`jornada_id`);

--
-- Índices de tabela `jornadas_modulos_documentos`
--
ALTER TABLE `jornadas_modulos_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modulo_id` (`modulo_id`);

--
-- Índices de tabela `jornadas_modulos_exercicios`
--
ALTER TABLE `jornadas_modulos_exercicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modulo_id` (`modulo_id`),
  ADD KEY `tipo` (`tipo`);

--
-- Índices de tabela `jornadas_modulos_textos`
--
ALTER TABLE `jornadas_modulos_textos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_modulo_id` (`modulo_id`);

--
-- Índices de tabela `jornadas_modulos_videos`
--
ALTER TABLE `jornadas_modulos_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modulo_id` (`modulo_id`),
  ADD KEY `tipo` (`tipo`);

--
-- Índices de tabela `jornadas_progresso_alunos`
--
ALTER TABLE `jornadas_progresso_alunos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `jornada_id` (`jornada_id`),
  ADD KEY `aula_id` (`aula_id`),
  ADD KEY `exercicio_id` (`exercicio_id`),
  ADD KEY `modulo_id` (`modulo_id`),
  ADD KEY `exercicio_modulo_id` (`exercicio_modulo_id`),
  ADD KEY `idx_jpa_jornada_aluno_status` (`jornada_id`,`aluno_id`,`status`),
  ADD KEY `idx_jpa_jornada_aluno_tipo_status` (`jornada_id`,`aluno_id`,`atividade_tipo`,`status`);

--
-- Índices de tabela `jornadas_progresso_blocos`
--
ALTER TABLE `jornadas_progresso_blocos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `aluno_jornada_bloco` (`aluno_id`,`jornada_id`,`bloco_id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `jornada_id` (`jornada_id`),
  ADD KEY `bloco_id` (`bloco_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_progresso_blocos_status` (`status`,`data_conclusao`),
  ADD KEY `idx_progresso_blocos_aluno_jornada` (`aluno_id`,`jornada_id`,`status`);

--
-- Índices de tabela `jornadas_redacoes`
--
ALTER TABLE `jornadas_redacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jornada_id` (`jornada_id`),
  ADD KEY `aula_id` (`aula_id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `status` (`status`);

--
-- Índices de tabela `jornadas_redacoes_alunos`
--
ALTER TABLE `jornadas_redacoes_alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_redacao_jornada` (`redacao_id`,`jornada_redacao_id`),
  ADD KEY `jornada_redacao_id` (`jornada_redacao_id`),
  ADD KEY `redacao_id` (`redacao_id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_jra_jornada_redacao_id` (`jornada_redacao_id`);

--
-- Índices de tabela `jornadas_relatorios`
--
ALTER TABLE `jornadas_relatorios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `jornada_id` (`jornada_id`);

--
-- Índices de tabela `jornadas_resumos_alunos`
--
ALTER TABLE `jornadas_resumos_alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_resumo_aluno_modulo` (`aluno_id`,`modulo_id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `aula_id` (`aula_id`),
  ADD KEY `modulo_id` (`modulo_id`),
  ADD KEY `jornada_id` (`jornada_id`);

--
-- Índices de tabela `jornadas_tempo_alunos`
--
ALTER TABLE `jornadas_tempo_alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_aluno_jornada` (`aluno_id`,`jornada_id`),
  ADD KEY `idx_jornada` (`jornada_id`),
  ADD KEY `idx_aluno` (`aluno_id`);

--
-- Índices de tabela `jornadas_tipos_blocos`
--
ALTER TABLE `jornadas_tipos_blocos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `jornadas_tudinha_explicacao_exercicio`
--
ALTER TABLE `jornadas_tudinha_explicacao_exercicio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_aluno_exercicio_modulo` (`aluno_id`,`exercicio_modulo_id`),
  ADD KEY `idx_jtee_exercicio` (`exercicio_modulo_id`);

--
-- Índices de tabela `listas_exercicios`
--
ALTER TABLE `listas_exercicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `idx_lista_exercicios_materia` (`materia`),
  ADD KEY `idx_lista_exercicios_serie` (`serie`),
  ADD KEY `idx_lista_exercicios_dificuldade` (`nivel_dificuldade`);

--
-- Índices de tabela `listas_exercicios_personalizadas`
--
ALTER TABLE `listas_exercicios_personalizadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `turma_id` (`turma_id`);

--
-- Índices de tabela `listas_personalizadas_exercicios`
--
ALTER TABLE `listas_personalizadas_exercicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Índices de tabela `listas_personalizadas_respostas`
--
ALTER TABLE `listas_personalizadas_respostas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessao_id` (`sessao_id`),
  ADD KEY `questao_id` (`questao_id`),
  ADD KEY `aluno_id` (`aluno_id`);

--
-- Índices de tabela `listas_personalizadas_sessoes`
--
ALTER TABLE `listas_personalizadas_sessoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `lista_id` (`lista_id`),
  ADD KEY `started_at` (`started_at`);

--
-- Índices de tabela `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_user_role` (`user_role`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ip_address` (`ip_address`);

--
-- Índices de tabela `logs_senhas`
--
ALTER TABLE `logs_senhas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `alterado_por` (`alterado_por`);

--
-- Índices de tabela `logs_uso_llm`
--
ALTER TABLE `logs_uso_llm`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_usage_type` (`usage_type`),
  ADD KEY `idx_model` (`model`);

--
-- Índices de tabela `log_validacao_apps_externos`
--
ALTER TABLE `log_validacao_apps_externos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_app` (`app`);

--
-- Índices de tabela `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `matricula`
--
ALTER TABLE `matricula`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_matricula_aluno_turma_ano` (`aluno_id`,`turma_id`,`ano_letivo_id`),
  ADD KEY `idx_matricula_aluno` (`aluno_id`),
  ADD KEY `idx_matricula_turma` (`turma_id`),
  ADD KEY `idx_matricula_ano_letivo` (`ano_letivo_id`),
  ADD KEY `idx_matricula_status` (`status`);

--
-- Índices de tabela `migrations_executadas`
--
ALTER TABLE `migrations_executadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_migration_escola` (`escola_database_config_id`,`migration_file`),
  ADD KEY `idx_escola` (`escola_database_config_id`),
  ADD KEY `idx_migration_file` (`migration_file`),
  ADD KEY `idx_executada_em` (`executada_em`);

--
-- Índices de tabela `minicursos`
--
ALTER TABLE `minicursos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ativo_ordem` (`ativo`,`ordem`);

--
-- Índices de tabela `minicursos_arquivos`
--
ALTER TABLE `minicursos_arquivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_minicurso` (`minicurso_id`);

--
-- Índices de tabela `minicursos_aulas`
--
ALTER TABLE `minicursos_aulas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_modulo` (`modulo_id`);

--
-- Índices de tabela `minicursos_modulos`
--
ALTER TABLE `minicursos_modulos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_minicurso` (`minicurso_id`);

--
-- Índices de tabela `minicursos_progresso`
--
ALTER TABLE `minicursos_progresso`
  ADD PRIMARY KEY (`aluno_id`,`minicurso_id`),
  ADD KEY `idx_minicurso` (`minicurso_id`);

--
-- Índices de tabela `mobile_auth_sessions`
--
ALTER TABLE `mobile_auth_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mobile_auth_refresh` (`refresh_token_hash`),
  ADD KEY `idx_mobile_auth_parent_active` (`parent_id`,`revoked_at`,`expires_at`);

--
-- Índices de tabela `mobile_devices`
--
ALTER TABLE `mobile_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mobile_devices_device` (`device_id`),
  ADD UNIQUE KEY `uq_mobile_devices_token_hash` (`token_hash`),
  ADD KEY `idx_mobile_devices_parent_enabled` (`parent_id`,`enabled`),
  ADD KEY `idx_mobile_devices_last_seen` (`last_seen_at`);

--
-- Índices de tabela `modulos_apostilas`
--
ALTER TABLE `modulos_apostilas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_modulos_apostilas_turma` (`turma_id`),
  ADD KEY `idx_modulos_apostilas_visibilidade` (`visibilidade`),
  ADD KEY `idx_modulos_apostilas_created_at` (`created_at`);

--
-- Índices de tabela `modulos_apostilas_anexos`
--
ALTER TABLE `modulos_apostilas_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_modulos_apostilas_anexos_modulo` (`modulo_apostila_id`);

--
-- Índices de tabela `modulos_apostilas_turmas`
--
ALTER TABLE `modulos_apostilas_turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_modulo_apostila_turma` (`modulo_apostila_id`,`turma_id`),
  ADD KEY `idx_modulos_apostilas_turmas_turma` (`turma_id`);

--
-- Índices de tabela `modulos_arquivos`
--
ALTER TABLE `modulos_arquivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_turma` (`turma_id`),
  ADD KEY `idx_materia` (`materia_id`),
  ADD KEY `idx_professor` (`professor_id`),
  ADD KEY `idx_modulos_arquivos_aluno_id` (`aluno_id`),
  ADD KEY `idx_pasta_id` (`pasta_id`);

--
-- Índices de tabela `modulos_arquivos_anexos`
--
ALTER TABLE `modulos_arquivos_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_modulo_arquivo` (`modulo_arquivo_id`);

--
-- Índices de tabela `modulos_arquivos_pastas`
--
ALTER TABLE `modulos_arquivos_pastas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_professor` (`professor_id`),
  ADD KEY `idx_tipo` (`criado_por_tipo`);

--
-- Índices de tabela `modulos_arquivos_turmas`
--
ALTER TABLE `modulos_arquivos_turmas`
  ADD PRIMARY KEY (`modulo_arquivo_id`,`turma_id`),
  ADD KEY `idx_turma` (`turma_id`);

--
-- Índices de tabela `modulos_arquivos_videos`
--
ALTER TABLE `modulos_arquivos_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ma_videos_modulo` (`modulo_arquivo_id`);

--
-- Índices de tabela `monitores`
--
ALTER TABLE `monitores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_monitores_email` (`email`);

--
-- Índices de tabela `monitor_acoes_log`
--
ALTER TABLE `monitor_acoes_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_monitor_acoes_monitor` (`monitor_id`),
  ADD KEY `idx_monitor_acoes_aluno` (`aluno_id`);

--
-- Índices de tabela `mural_recados`
--
ALTER TABLE `mural_recados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mural_recados_data_sai` (`data_sai_mural`),
  ADD KEY `idx_mural_recados_publicacao` (`data_publicacao`),
  ADD KEY `idx_mural_recados_materia` (`materia_id`);

--
-- Índices de tabela `mural_recados_anexos`
--
ALTER TABLE `mural_recados_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mural_anexos_recado` (`mural_recado_id`);

--
-- Índices de tabela `mural_recados_turmas`
--
ALTER TABLE `mural_recados_turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mural_turma` (`mural_recado_id`,`turma_id`),
  ADD KEY `idx_mural_recados_turmas_mural` (`mural_recado_id`),
  ADD KEY `idx_mural_recados_turmas_turma` (`turma_id`);

--
-- Índices de tabela `mural_recados_vistos`
--
ALTER TABLE `mural_recados_vistos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mural_aluno` (`mural_recado_id`,`aluno_id`),
  ADD KEY `idx_mural_vistos_recado` (`mural_recado_id`),
  ADD KEY `idx_mural_vistos_aluno` (`aluno_id`);

--
-- Índices de tabela `notes_tokens`
--
ALTER TABLE `notes_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Índices de tabela `noticias`
--
ALTER TABLE `noticias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_noticias_link` (`link`(255));

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enviado_por` (`enviado_por`),
  ADD KEY `tipo_enviador` (`tipo_enviador`),
  ADD KEY `perfil_enviador` (`perfil_enviador`),
  ADD KEY `prioridade` (`prioridade`),
  ADD KEY `ativo` (`ativo`),
  ADD KEY `created_at` (`created_at`);

--
-- Índices de tabela `notificacoes_api`
--
ALTER TABLE `notificacoes_api`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `notificacoes_configuracoes`
--
ALTER TABLE `notificacoes_configuracoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_tipo` (`usuario_id`,`tipo_usuario`),
  ADD KEY `tipo_usuario` (`tipo_usuario`);

--
-- Índices de tabela `notificacoes_destinatarios`
--
ALTER TABLE `notificacoes_destinatarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notificacao_id` (`notificacao_id`),
  ADD KEY `tipo_destinatario` (`tipo_destinatario`),
  ADD KEY `destinatario_id` (`destinatario_id`),
  ADD KEY `turma_id` (`turma_id`),
  ADD KEY `lida` (`lida`);

--
-- Índices de tabela `notificacoes_historico`
--
ALTER TABLE `notificacoes_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notificacao_id` (`notificacao_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `tipo_usuario` (`tipo_usuario`),
  ADD KEY `acao` (`acao`),
  ADD KEY `created_at` (`created_at`);

--
-- Índices de tabela `notificacoes_push`
--
ALTER TABLE `notificacoes_push`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_push_notif_created` (`created_at`),
  ADD KEY `idx_push_notif_tipo` (`tipo_destino`);

--
-- Índices de tabela `notificacoes_push_envios`
--
ALTER TABLE `notificacoes_push_envios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_push_envio_token` (`tracking_token`),
  ADD UNIQUE KEY `uk_push_envio_notif_role_user` (`notificacao_id`,`role`,`user_id`),
  ADD KEY `idx_push_envio_user` (`user_id`),
  ADD KEY `idx_push_envio_notif` (`notificacao_id`);

--
-- Índices de tabela `pacotes_creditos`
--
ALTER TABLE `pacotes_creditos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pacotes_catalogo_pacote_id` (`catalogo_pacote_id`),
  ADD KEY `idx_pacotes_ativo` (`ativo`);

--
-- Índices de tabela `partidas_dama`
--
ALTER TABLE `partidas_dama`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno_status` (`aluno_id`,`status`),
  ADD KEY `idx_data_inicio` (`data_inicio`);

--
-- Índices de tabela `patrimony_assets`
--
ALTER TABLE `patrimony_assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_patrimony_assets_numero` (`numero_patrimonio`),
  ADD KEY `idx_patrimony_assets_location` (`location_id`),
  ADD KEY `idx_patrimony_assets_status` (`status`),
  ADD KEY `idx_patrimony_assets_categoria` (`categoria`);

--
-- Índices de tabela `patrimony_inventory_checks`
--
ALTER TABLE `patrimony_inventory_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patrimony_checks_asset` (`asset_id`),
  ADD KEY `idx_patrimony_checks_created` (`created_at`);

--
-- Índices de tabela `patrimony_movements`
--
ALTER TABLE `patrimony_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patrimony_movements_asset` (`asset_id`),
  ADD KEY `idx_patrimony_movements_tipo` (`tipo`),
  ADD KEY `idx_patrimony_movements_created` (`created_at`);

--
-- Índices de tabela `planos_aula`
--
ALTER TABLE `planos_aula`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `turma_id` (`turma_id`),
  ADD KEY `data_aula` (`data_aula`(768)),
  ADD KEY `status` (`status`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Índices de tabela `planos_creditos`
--
ALTER TABLE `planos_creditos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_planos_catalogo_plano_id` (`catalogo_plano_id`),
  ADD KEY `idx_planos_ativo` (`ativo`);

--
-- Índices de tabela `plano_curso`
--
ALTER TABLE `plano_curso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_plano_curso` (`ano_letivo_id`,`serie_id`,`materia_id`),
  ADD KEY `idx_plano_curso_materia` (`materia_id`);

--
-- Índices de tabela `plano_curso_habilidades`
--
ALTER TABLE `plano_curso_habilidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_plano_curso_hab` (`plano_curso_id`,`habilidade_id`),
  ADD KEY `idx_pch_habilidade` (`habilidade_id`);

--
-- Índices de tabela `pontuacao_alunos`
--
ALTER TABLE `pontuacao_alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_aluno` (`aluno_id`);

--
-- Índices de tabela `professores`
--
ALTER TABLE `professores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_codigo_prof` (`codigo_prof`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `professores_ia_agentes`
--
ALTER TABLE `professores_ia_agentes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_professor` (`professor_id`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `professores_ia_conversas`
--
ALTER TABLE `professores_ia_conversas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agente` (`agente_id`),
  ADD KEY `idx_professor` (`professor_id`);

--
-- Índices de tabela `professores_ia_documentos`
--
ALTER TABLE `professores_ia_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agente` (`agente_id`),
  ADD KEY `idx_professor` (`professor_id`),
  ADD KEY `idx_status` (`status_processamento`);

--
-- Índices de tabela `professores_ia_documentos_chunks`
--
ALTER TABLE `professores_ia_documentos_chunks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_documento` (`documento_id`),
  ADD KEY `idx_agente` (`agente_id`),
  ADD KEY `idx_chunk_index` (`chunk_index`);

--
-- Índices de tabela `professores_ia_mensagens`
--
ALTER TABLE `professores_ia_mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversa` (`conversa_id`),
  ADD KEY `idx_role` (`role`);

--
-- Índices de tabela `professores_slides`
--
ALTER TABLE `professores_slides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_professor_id` (`professor_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `professor_questoes_api`
--
ALTER TABLE `professor_questoes_api`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prof_questoes_external` (`external_id`),
  ADD KEY `idx_prof_questoes_materia` (`materia`),
  ADD KEY `idx_prof_questoes_tipo` (`tipo`);

--
-- Índices de tabela `professor_questoes_montagem_itens`
--
ALTER TABLE `professor_questoes_montagem_itens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prof_montagem_item` (`montagem_id`,`questao_id`),
  ADD KEY `idx_prof_montagem_itens_montagem` (`montagem_id`),
  ADD KEY `idx_prof_montagem_itens_questao` (`questao_id`);

--
-- Índices de tabela `professor_questoes_montagens`
--
ALTER TABLE `professor_questoes_montagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prof_montagens_professor` (`professor_id`),
  ADD KEY `idx_prof_montagens_created` (`created_at`);

--
-- Índices de tabela `provas`
--
ALTER TABLE `provas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `turma_id` (`turma_id`),
  ADD KEY `data_inicio` (`data_inicio`),
  ADD KEY `data_fim` (`data_fim`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `provas_alternativas`
--
ALTER TABLE `provas_alternativas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `questao_id` (`questao_id`),
  ADD KEY `ordem` (`ordem`);

--
-- Índices de tabela `provas_blocos`
--
ALTER TABLE `provas_blocos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `turma_id` (`turma_id`),
  ADD KEY `data_prova` (`data_prova`),
  ADD KEY `idx_liberado` (`liberado`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `idx_provas_blocos_status` (`status`),
  ADD KEY `idx_provas_blocos_deleted_by` (`deleted_by`),
  ADD KEY `idx_provas_blocos_bloco_modelo_id` (`bloco_modelo_id`),
  ADD KEY `fk_provas_blocos_tipo_avaliacao` (`tipo_avaliacao_id`);

--
-- Índices de tabela `provas_blocos_modelos`
--
ALTER TABLE `provas_blocos_modelos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `deleted_at` (`deleted_at`),
  ADD KEY `idx_blocos_modelo_criado_por` (`criado_por`);

--
-- Índices de tabela `provas_blocos_modelos_professores`
--
ALTER TABLE `provas_blocos_modelos_professores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `modelo_professor_materia` (`modelo_id`,`professor_id`,`materia_id`),
  ADD KEY `modelo_id` (`modelo_id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `idx_blocos_modelo_professores_modelo` (`modelo_id`);

--
-- Índices de tabela `provas_blocos_notas_lancadas`
--
ALTER TABLE `provas_blocos_notas_lancadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bloco_prof_mat_tur_aluno` (`bloco_id`,`professor_id`,`materia_id`,`turma_id`,`aluno_id`),
  ADD KEY `idx_bloco_prof_mat` (`bloco_id`,`professor_id`,`materia_id`);

--
-- Índices de tabela `provas_blocos_professores`
--
ALTER TABLE `provas_blocos_professores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bloco_professor_materia` (`bloco_id`,`professor_id`,`materia_id`),
  ADD KEY `bloco_id` (`bloco_id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `materia_id` (`materia_id`);

--
-- Índices de tabela `provas_blocos_professores_turmas`
--
ALTER TABLE `provas_blocos_professores_turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bloco_professor_turma` (`bloco_professor_id`,`turma_id`),
  ADD KEY `bloco_professor_id` (`bloco_professor_id`),
  ADD KEY `turma_id` (`turma_id`);

--
-- Índices de tabela `provas_blocos_turmas`
--
ALTER TABLE `provas_blocos_turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bloco_turma` (`bloco_id`,`turma_id`),
  ADD KEY `bloco_id` (`bloco_id`),
  ADD KEY `turma_id` (`turma_id`);

--
-- Índices de tabela `provas_blocos_vinculo`
--
ALTER TABLE `provas_blocos_vinculo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bloco_prova` (`bloco_id`,`prova_id`),
  ADD KEY `bloco_id` (`bloco_id`),
  ADD KEY `prova_id` (`prova_id`),
  ADD KEY `ordem` (`ordem`);

--
-- Índices de tabela `provas_final`
--
ALTER TABLE `provas_final`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bloco_id` (`bloco_id`),
  ADD KEY `prova_id` (`prova_id`);

--
-- Índices de tabela `provas_professores`
--
ALTER TABLE `provas_professores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bloco_professor_materia` (`bloco_id`,`professor_id`,`materia_id`),
  ADD KEY `bloco_id` (`bloco_id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `prova_id` (`prova_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_provas_professores_status` (`status`);

--
-- Índices de tabela `provas_questoes`
--
ALTER TABLE `provas_questoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prova_id` (`prova_id`),
  ADD KEY `ordem` (`ordem`),
  ADD KEY `idx_provas_questoes_invalidada` (`invalidada`);

--
-- Índices de tabela `provas_realizacoes`
--
ALTER TABLE `provas_realizacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `prova_aluno` (`prova_id`,`aluno_id`),
  ADD KEY `prova_id` (`prova_id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `status` (`status`);

--
-- Índices de tabela `provas_respostas`
--
ALTER TABLE `provas_respostas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `prova_aluno_questao` (`prova_id`,`aluno_id`,`questao_id`),
  ADD KEY `prova_id` (`prova_id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `questao_id` (`questao_id`),
  ADD KEY `provas_respostas_ibfk_4` (`alternativa_id`);

--
-- Índices de tabela `provas_respostas_log`
--
ALTER TABLE `provas_respostas_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prova_aluno_created` (`prova_id`,`aluno_id`,`created_at`);

--
-- Índices de tabela `provas_tipos_avaliacao`
--
ALTER TABLE `provas_tipos_avaliacao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_provas_tipos_avaliacao_nome` (`nome`),
  ADD KEY `idx_provas_tipos_avaliacao_ativo_ordem` (`ativo`,`ordem`);

--
-- Índices de tabela `provas_turmas`
--
ALTER TABLE `provas_turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `prova_turma` (`prova_id`,`turma_id`),
  ADD KEY `prova_id` (`prova_id`),
  ADD KEY `turma_id` (`turma_id`),
  ADD KEY `idx_provas_turmas_prova_id` (`prova_id`);

--
-- Índices de tabela `provas_validacoes_log`
--
ALTER TABLE `provas_validacoes_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prova_aluno` (`prova_id`,`aluno_id`),
  ADD KEY `idx_bloco` (`bloco_id`);

--
-- Índices de tabela `questoes`
--
ALTER TABLE `questoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_questoes_lista` (`lista_id`);

--
-- Índices de tabela `questoes_personalizadas`
--
ALTER TABLE `questoes_personalizadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lista_id` (`lista_id`),
  ADD KEY `nivel_dificuldade` (`nivel_dificuldade`);

--
-- Índices de tabela `redacao_livre_correcoes`
--
ALTER TABLE `redacao_livre_correcoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `envio_id` (`envio_id`),
  ADD KEY `envio_id_2` (`envio_id`);

--
-- Índices de tabela `redacao_livre_envios`
--
ALTER TABLE `redacao_livre_envios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `turma_id` (`turma_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Índices de tabela `redacoes`
--
ALTER TABLE `redacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_tema` (`tema`),
  ADD KEY `idx_nota` (`nota`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `tema_id` (`tema_id`),
  ADD KEY `idx_rascunho` (`eh_rascunho`,`aluno_id`),
  ADD KEY `jornada_id` (`jornada_id`);

--
-- Índices de tabela `redacoes_orientadas_correcoes`
--
ALTER TABLE `redacoes_orientadas_correcoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_essay_corrections_submission` (`submission_id`),
  ADD KEY `idx_essay_corrections_submission` (`submission_id`),
  ADD KEY `prompt_id` (`prompt_id`),
  ADD KEY `corrected_by_teacher_id` (`corrected_by_teacher_id`);

--
-- Índices de tabela `redacoes_orientadas_correcoes_logs`
--
ALTER TABLE `redacoes_orientadas_correcoes_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_essay_correction_logs_correction` (`correction_id`);

--
-- Índices de tabela `redacoes_orientadas_criterios`
--
ALTER TABLE `redacoes_orientadas_criterios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_essay_criteria_type_slug` (`text_type_id`,`slug`),
  ADD KEY `idx_essay_criteria_text_type` (`text_type_id`);

--
-- Índices de tabela `redacoes_orientadas_entregas`
--
ALTER TABLE `redacoes_orientadas_entregas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_essay_submissions_proposal` (`proposal_id`),
  ADD KEY `idx_essay_submissions_student` (`student_id`),
  ADD KEY `idx_essay_submissions_status` (`status`);

--
-- Índices de tabela `redacoes_orientadas_prompts`
--
ALTER TABLE `redacoes_orientadas_prompts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_essay_prompts_board_type` (`board_id`,`text_type_id`),
  ADD KEY `idx_essay_prompts_active` (`board_id`,`text_type_id`,`is_active`),
  ADD KEY `text_type_id` (`text_type_id`);

--
-- Índices de tabela `redacoes_orientadas_propostas`
--
ALTER TABLE `redacoes_orientadas_propostas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_essay_proposals_teacher` (`teacher_id`),
  ADD KEY `idx_essay_proposals_board_type` (`board_id`,`text_type_id`),
  ADD KEY `idx_essay_proposals_status` (`status`),
  ADD KEY `text_type_id` (`text_type_id`);

--
-- Índices de tabela `redacoes_orientadas_propostas_alunos`
--
ALTER TABLE `redacoes_orientadas_propostas_alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_essay_proposal_students` (`proposal_id`,`student_id`),
  ADD KEY `idx_essay_proposal_students_proposal` (`proposal_id`),
  ADD KEY `idx_essay_proposal_students_student` (`student_id`);

--
-- Índices de tabela `redacoes_orientadas_propostas_professores`
--
ALTER TABLE `redacoes_orientadas_propostas_professores`
  ADD PRIMARY KEY (`proposal_id`,`professor_id`),
  ADD KEY `idx_professor_id` (`professor_id`);

--
-- Índices de tabela `redacoes_orientadas_propostas_turmas`
--
ALTER TABLE `redacoes_orientadas_propostas_turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_essay_proposal_turmas` (`proposal_id`,`turma_id`),
  ADD KEY `idx_essay_proposal_turmas_proposal` (`proposal_id`),
  ADD KEY `idx_essay_proposal_turmas_turma` (`turma_id`);

--
-- Índices de tabela `redacoes_orientadas_quadros`
--
ALTER TABLE `redacoes_orientadas_quadros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_essay_boards_active` (`is_active`);

--
-- Índices de tabela `redacoes_orientadas_tipos_texto`
--
ALTER TABLE `redacoes_orientadas_tipos_texto`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_essay_text_types_board_slug` (`board_id`,`slug`),
  ADD KEY `idx_essay_text_types_board` (`board_id`);

--
-- Índices de tabela `redacoes_temas`
--
ALTER TABLE `redacoes_temas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `redefinicoes_senha`
--
ALTER TABLE `redefinicoes_senha`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Índices de tabela `relatorios`
--
ALTER TABLE `relatorios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `responsaveis`
--
ALTER TABLE `responsaveis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cpf` (`cpf`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `reuniao_anexos`
--
ALTER TABLE `reuniao_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reuniao` (`reuniao_id`);

--
-- Índices de tabela `reuniao_turmas`
--
ALTER TABLE `reuniao_turmas`
  ADD PRIMARY KEY (`reuniao_id`,`turma_id`);

--
-- Índices de tabela `reunioes`
--
ALTER TABLE `reunioes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_tipo_data` (`tipo`,`data_reuniao`);

--
-- Índices de tabela `school_calendar_events`
--
ALTER TABLE `school_calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school_calendar_period` (`inicio_em`,`fim_em`),
  ADD KEY `idx_school_calendar_status` (`status`,`inicio_em`);

--
-- Índices de tabela `school_calendar_event_classes`
--
ALTER TABLE `school_calendar_event_classes`
  ADD PRIMARY KEY (`event_id`,`turma_id`),
  ADD KEY `idx_school_calendar_class` (`turma_id`,`event_id`);

--
-- Índices de tabela `school_calendar_event_reads`
--
ALTER TABLE `school_calendar_event_reads`
  ADD PRIMARY KEY (`event_id`,`responsavel_id`,`aluno_id`);

--
-- Índices de tabela `school_calendar_event_students`
--
ALTER TABLE `school_calendar_event_students`
  ADD PRIMARY KEY (`event_id`,`aluno_id`),
  ADD KEY `idx_school_calendar_student` (`aluno_id`,`event_id`);

--
-- Índices de tabela `school_communications`
--
ALTER TABLE `school_communications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school_communications_status` (`status`,`published_at`),
  ADD KEY `idx_school_communications_priority` (`prioridade`,`published_at`);

--
-- Índices de tabela `school_communication_attachments`
--
ALTER TABLE `school_communication_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school_communication_attachment` (`communication_id`);

--
-- Índices de tabela `school_communication_classes`
--
ALTER TABLE `school_communication_classes`
  ADD PRIMARY KEY (`communication_id`,`turma_id`),
  ADD KEY `idx_school_communication_class` (`turma_id`,`communication_id`);

--
-- Índices de tabela `school_communication_reads`
--
ALTER TABLE `school_communication_reads`
  ADD PRIMARY KEY (`communication_id`,`responsavel_id`,`aluno_id`),
  ADD KEY `idx_school_communication_parent_read` (`responsavel_id`,`lido_em`);

--
-- Índices de tabela `school_communication_replies`
--
ALTER TABLE `school_communication_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school_communication_reply` (`communication_id`,`created_at`);

--
-- Índices de tabela `school_communication_students`
--
ALTER TABLE `school_communication_students`
  ADD PRIMARY KEY (`communication_id`,`aluno_id`),
  ADD KEY `idx_school_communication_student` (`aluno_id`,`communication_id`);

--
-- Índices de tabela `school_locations`
--
ALTER TABLE `school_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_school_locations_codigo` (`codigo`),
  ADD KEY `idx_school_locations_tipo` (`tipo`),
  ADD KEY `idx_school_locations_ativo` (`ativo`);

--
-- Índices de tabela `serie`
--
ALTER TABLE `serie`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_serie_curso_nome` (`curso_id`,`nome`),
  ADD KEY `idx_serie_curso` (`curso_id`),
  ADD KEY `idx_serie_ativo` (`ativo`);

--
-- Índices de tabela `sessoes`
--
ALTER TABLE `sessoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Índices de tabela `simulados`
--
ALTER TABLE `simulados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_simulados_aluno` (`aluno_id`),
  ADD KEY `idx_simulados_status` (`status`),
  ADD KEY `idx_simulados_ano` (`ano`);

--
-- Índices de tabela `simulados_estatisticas`
--
ALTER TABLE `simulados_estatisticas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_simulado_estatisticas_simulado` (`simulado_id`),
  ADD KEY `idx_simulado_estatisticas_materia` (`materia`);

--
-- Índices de tabela `simulados_questoes`
--
ALTER TABLE `simulados_questoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_simulado_questoes_simulado` (`simulado_id`),
  ADD KEY `idx_simulado_questoes_index` (`questao_index`);

--
-- Índices de tabela `student_access_events`
--
ALTER TABLE `student_access_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_access_event_provider` (`provider_presence_id`),
  ADD KEY `idx_access_event_student_date` (`student_id`,`event_at`),
  ADD KEY `idx_access_event_date` (`event_at`);

--
-- Índices de tabela `student_accommodations`
--
ALTER TABLE `student_accommodations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_accommodation_aluno_status` (`aluno_id`,`status`);

--
-- Índices de tabela `student_face_profiles`
--
ALTER TABLE `student_face_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_face_profile_student` (`student_id`),
  ADD UNIQUE KEY `uq_face_profile_external_key` (`external_key`);

--
-- Índices de tabela `student_face_samples`
--
ALTER TABLE `student_face_samples`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_face_sample_provider` (`provider_face_id`),
  ADD KEY `idx_face_sample_profile` (`face_profile_id`);

--
-- Índices de tabela `suporte_tickets`
--
ALTER TABLE `suporte_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_categoria` (`categoria`),
  ADD KEY `idx_criado_em` (`criado_em`),
  ADD KEY `idx_tickets_aluno_status` (`aluno_id`,`status`);

--
-- Índices de tabela `suporte_tickets_mensagens`
--
ALTER TABLE `suporte_tickets_mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_criado_em` (`criado_em`),
  ADD KEY `idx_lida` (`lida`),
  ADD KEY `idx_mensagens_ticket_criado` (`ticket_id`,`criado_em`);

--
-- Índices de tabela `tentativas_login`
--
ALTER TABLE `tentativas_login`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_tentativas_login_tipo_created` (`tipo`,`created_at`);

--
-- Índices de tabela `tipos_curso`
--
ALTER TABLE `tipos_curso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tipos_curso_nome` (`nome`),
  ADD UNIQUE KEY `uq_tipos_curso_slug` (`slug`);

--
-- Índices de tabela `tudinha_analises`
--
ALTER TABLE `tudinha_analises`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `data_ate` (`data_ate`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_analises_expires` (`expires_at`),
  ADD KEY `idx_analises_anonymized` (`anonymized_at`);

--
-- Índices de tabela `tudinha_conversas`
--
ALTER TABLE `tudinha_conversas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_updated_at` (`updated_at`),
  ADD KEY `idx_conversas_aluno` (`aluno_id`);

--
-- Índices de tabela `tudinha_mensagens`
--
ALTER TABLE `tudinha_mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversa` (`conversa_id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_mensagens_conversa` (`conversa_id`),
  ADD KEY `idx_mensagens_aluno` (`aluno_id`),
  ADD KEY `idx_mensagens_is_ia` (`is_ia`),
  ADD KEY `idx_mensagens_image_url` (`image_url`),
  ADD KEY `idx_mensagens_expires` (`expires_at`),
  ADD KEY `idx_mensagens_anonymized` (`anonymized_at`);

--
-- Índices de tabela `turmas`
--
ALTER TABLE `turmas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ano_letivo` (`ano_letivo`),
  ADD KEY `idx_serie` (`serie`),
  ADD KEY `idx_ativo` (`ativo`),
  ADD KEY `fk_turmas_curso` (`curso_id`),
  ADD KEY `fk_turmas_ano_letivo` (`ano_letivo_id`),
  ADD KEY `fk_turmas_serie` (`serie_id`),
  ADD KEY `fk_turmas_curso_novo` (`curso_novo_id`);

--
-- Índices de tabela `turmas_lista_config`
--
ALTER TABLE `turmas_lista_config`
  ADD PRIMARY KEY (`turma_id`,`ano_letivo_id`),
  ADD KEY `idx_turmas_lista_ano` (`ano_letivo_id`);

--
-- Índices de tabela `tutoriais`
--
ALTER TABLE `tutoriais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ordem` (`ordem`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_perfil_admin` (`perfil_admin`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `idx_usuarios_perfil_permissao_id` (`perfil_permissao_id`);

--
-- Índices de tabela `usuarios_consentimentos`
--
ALTER TABLE `usuarios_consentimentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_consent` (`user_id`,`user_role`,`document_type`,`document_version`),
  ADD KEY `idx_user_role` (`user_role`),
  ADD KEY `idx_document_type` (`document_type`);

--
-- Índices de tabela `validacao_tokens_apps`
--
ALTER TABLE `validacao_tokens_apps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_tenant_slug` (`tenant_slug`),
  ADD KEY `idx_app` (`app`);

--
-- Índices de tabela `webhooks`
--
ALTER TABLE `webhooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_escola` (`escola_id`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `accommodation_documents`
--
ALTER TABLE `accommodation_documents`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `accommodation_rules`
--
ALTER TABLE `accommodation_rules`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `admin_perfis_permissao`
--
ALTER TABLE `admin_perfis_permissao`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ai_jobs`
--
ALTER TABLE `ai_jobs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alertas_sensiveis`
--
ALTER TABLE `alertas_sensiveis`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alertas_sensiveis_acoes`
--
ALTER TABLE `alertas_sensiveis_acoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos`
--
ALTER TABLE `alunos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos_acoes_diarias`
--
ALTER TABLE `alunos_acoes_diarias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos_historico_status`
--
ALTER TABLE `alunos_historico_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos_ocorrencias`
--
ALTER TABLE `alunos_ocorrencias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos_ocorrencias_itens`
--
ALTER TABLE `alunos_ocorrencias_itens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos_onboarding`
--
ALTER TABLE `alunos_onboarding`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos_responsaveis`
--
ALTER TABLE `alunos_responsaveis`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos_seguranca`
--
ALTER TABLE `alunos_seguranca`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos_sessoes_acesso`
--
ALTER TABLE `alunos_sessoes_acesso`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos_turmas_historico`
--
ALTER TABLE `alunos_turmas_historico`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `alunos_turma_chamada`
--
ALTER TABLE `alunos_turma_chamada`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ano_letivo`
--
ALTER TABLE `ano_letivo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `apostilas_ia`
--
ALTER TABLE `apostilas_ia`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `apostila_ia_chunks`
--
ALTER TABLE `apostila_ia_chunks`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `apostila_ia_conversas`
--
ALTER TABLE `apostila_ia_conversas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `apostila_ia_exercicios`
--
ALTER TABLE `apostila_ia_exercicios`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `apostila_ia_paginas`
--
ALTER TABLE `apostila_ia_paginas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `apostila_ia_turmas`
--
ALTER TABLE `apostila_ia_turmas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `assessment_versions`
--
ALTER TABLE `assessment_versions`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `assessment_version_logs`
--
ALTER TABLE `assessment_version_logs`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `assinaturas_creditos`
--
ALTER TABLE `assinaturas_creditos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `aulas_online`
--
ALTER TABLE `aulas_online`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `aulas_online_arquivos`
--
ALTER TABLE `aulas_online_arquivos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `avatares_alunos`
--
ALTER TABLE `avatares_alunos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_atividades`
--
ALTER TABLE `ava_atividades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_atividade_entregas`
--
ALTER TABLE `ava_atividade_entregas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_atividade_entrega_arquivos`
--
ALTER TABLE `ava_atividade_entrega_arquivos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_aulas`
--
ALTER TABLE `ava_aulas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_aulas_ao_vivo`
--
ALTER TABLE `ava_aulas_ao_vivo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_aula_anexos`
--
ALTER TABLE `ava_aula_anexos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_categorias`
--
ALTER TABLE `ava_categorias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_certificados`
--
ALTER TABLE `ava_certificados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_comentarios`
--
ALTER TABLE `ava_comentarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_cursos`
--
ALTER TABLE `ava_cursos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_disciplinas`
--
ALTER TABLE `ava_disciplinas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_disciplina_avaliacoes`
--
ALTER TABLE `ava_disciplina_avaliacoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_matriculas_disciplina`
--
ALTER TABLE `ava_matriculas_disciplina`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_modulos`
--
ALTER TABLE `ava_modulos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_progresso_aula`
--
ALTER TABLE `ava_progresso_aula`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_progresso_video`
--
ALTER TABLE `ava_progresso_video`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_rubricas`
--
ALTER TABLE `ava_rubricas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_rubrica_criterios`
--
ALTER TABLE `ava_rubrica_criterios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ava_semestres`
--
ALTER TABLE `ava_semestres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `billing_message_log`
--
ALTER TABLE `billing_message_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `billing_rule_config`
--
ALTER TABLE `billing_rule_config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `bncc_habilidades`
--
ALTER TABLE `bncc_habilidades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `boletim_componentes`
--
ALTER TABLE `boletim_componentes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `boletim_log_geracoes`
--
ALTER TABLE `boletim_log_geracoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `boletim_notas_manuais`
--
ALTER TABLE `boletim_notas_manuais`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `boletim_observacoes`
--
ALTER TABLE `boletim_observacoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `boletim_regras`
--
ALTER TABLE `boletim_regras`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `boletim_resultados_gerados`
--
ALTER TABLE `boletim_resultados_gerados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cadernos_aluno`
--
ALTER TABLE `cadernos_aluno`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cadernos_aluno_anexos`
--
ALTER TABLE `cadernos_aluno_anexos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cadernos_aluno_pastas`
--
ALTER TABLE `cadernos_aluno_pastas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `calendario_letivo`
--
ALTER TABLE `calendario_letivo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `calendario_letivo_eventos`
--
ALTER TABLE `calendario_letivo_eventos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `carteira_movimentacoes`
--
ALTER TABLE `carteira_movimentacoes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `carteira_usuarios`
--
ALTER TABLE `carteira_usuarios`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `chat_professores_alunos`
--
ALTER TABLE `chat_professores_alunos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `chat_professores_alunos_anexos`
--
ALTER TABLE `chat_professores_alunos_anexos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `chat_professores_alunos_mensagens`
--
ALTER TABLE `chat_professores_alunos_mensagens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `compras_creditos`
--
ALTER TABLE `compras_creditos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `config_dev`
--
ALTER TABLE `config_dev`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `config_escolas_database`
--
ALTER TABLE `config_escolas_database`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `config_layout`
--
ALTER TABLE `config_layout`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `config_simulados`
--
ALTER TABLE `config_simulados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `curso`
--
ALTER TABLE `curso`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `dashboard_jornadas_resumo`
--
ALTER TABLE `dashboard_jornadas_resumo`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `diario_aulas`
--
ALTER TABLE `diario_aulas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `diario_frequencias`
--
ALTER TABLE `diario_frequencias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `drive_compartilhamentos`
--
ALTER TABLE `drive_compartilhamentos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `drive_itens`
--
ALTER TABLE `drive_itens`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `educalabs_messages`
--
ALTER TABLE `educalabs_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `educalabs_projects`
--
ALTER TABLE `educalabs_projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `educalabs_tokens`
--
ALTER TABLE `educalabs_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `educa_hits_requests`
--
ALTER TABLE `educa_hits_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `educa_hits_tracks`
--
ALTER TABLE `educa_hits_tracks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `educa_hits_track_classes`
--
ALTER TABLE `educa_hits_track_classes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `educa_hits_track_grades`
--
ALTER TABLE `educa_hits_track_grades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `educa_hits_track_schools`
--
ALTER TABLE `educa_hits_track_schools`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `educa_hits_track_users`
--
ALTER TABLE `educa_hits_track_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enem_alternativas`
--
ALTER TABLE `enem_alternativas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enem_disciplinas`
--
ALTER TABLE `enem_disciplinas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enem_provas`
--
ALTER TABLE `enem_provas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enem_questoes`
--
ALTER TABLE `enem_questoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enem_questoes_arquivos`
--
ALTER TABLE `enem_questoes_arquivos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enem_questoes_vinculo`
--
ALTER TABLE `enem_questoes_vinculo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enrollment`
--
ALTER TABLE `enrollment`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enrollment_audit`
--
ALTER TABLE `enrollment_audit`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enrollment_score`
--
ALTER TABLE `enrollment_score`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `estatisticas_dama`
--
ALTER TABLE `estatisticas_dama`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `execucao_exercicios`
--
ALTER TABLE `execucao_exercicios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `exercicios`
--
ALTER TABLE `exercicios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `exercicios_estatisticas_alunos`
--
ALTER TABLE `exercicios_estatisticas_alunos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `exercicios_estatisticas_turmas`
--
ALTER TABLE `exercicios_estatisticas_turmas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `exercicios_execucoes`
--
ALTER TABLE `exercicios_execucoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `exercicios_historico`
--
ALTER TABLE `exercicios_historico`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `exercicios_respostas`
--
ALTER TABLE `exercicios_respostas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `exercicios_sessoes`
--
ALTER TABLE `exercicios_sessoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `facial_devices`
--
ALTER TABLE `facial_devices`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `facial_device_pairing_codes`
--
ALTER TABLE `facial_device_pairing_codes`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `faltas_eventos`
--
ALTER TABLE `faltas_eventos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `faltas_lancamentos`
--
ALTER TABLE `faltas_lancamentos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `financeiro_valores_mensais`
--
ALTER TABLE `financeiro_valores_mensais`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_audit`
--
ALTER TABLE `finance_audit`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_bank_accounts`
--
ALTER TABLE `finance_bank_accounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_bills`
--
ALTER TABLE `finance_bills`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_charges`
--
ALTER TABLE `finance_charges`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_chart_accounts`
--
ALTER TABLE `finance_chart_accounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_config`
--
ALTER TABLE `finance_config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_contracts`
--
ALTER TABLE `finance_contracts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_contract_discounts`
--
ALTER TABLE `finance_contract_discounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_contract_items`
--
ALTER TABLE `finance_contract_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_discount_rules`
--
ALTER TABLE `finance_discount_rules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_installments`
--
ALTER TABLE `finance_installments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_ledger`
--
ALTER TABLE `finance_ledger`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_payments`
--
ALTER TABLE `finance_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_plans`
--
ALTER TABLE `finance_plans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_plan_items`
--
ALTER TABLE `finance_plan_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_price_table`
--
ALTER TABLE `finance_price_table`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_receipts`
--
ALTER TABLE `finance_receipts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `finance_renegotiations`
--
ALTER TABLE `finance_renegotiations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `flashcards_baralhos`
--
ALTER TABLE `flashcards_baralhos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `flashcards_cartas`
--
ALTER TABLE `flashcards_cartas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `flashcards_modelos`
--
ALTER TABLE `flashcards_modelos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `flashcards_modelos_cartas`
--
ALTER TABLE `flashcards_modelos_cartas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `flashcard_explicacoes`
--
ALTER TABLE `flashcard_explicacoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `forum_anexos`
--
ALTER TABLE `forum_anexos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `forum_denuncias`
--
ALTER TABLE `forum_denuncias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `forum_moderacao_alertas`
--
ALTER TABLE `forum_moderacao_alertas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `forum_respostas`
--
ALTER TABLE `forum_respostas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `forum_topicos`
--
ALTER TABLE `forum_topicos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `forum_usuarios_reputacao`
--
ALTER TABLE `forum_usuarios_reputacao`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `forum_votos`
--
ALTER TABLE `forum_votos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `grade_horaria`
--
ALTER TABLE `grade_horaria`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ingles_conversas`
--
ALTER TABLE `ingles_conversas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ingles_mensagens`
--
ALTER TABLE `ingles_mensagens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `inventory_lots`
--
ALTER TABLE `inventory_lots`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `inventory_requisitions`
--
ALTER TABLE `inventory_requisitions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `inventory_suppliers`
--
ALTER TABLE `inventory_suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `inventory_warehouses`
--
ALTER TABLE `inventory_warehouses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jogos_acoes`
--
ALTER TABLE `jogos_acoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jogos_milhao_partidas`
--
ALTER TABLE `jogos_milhao_partidas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jogos_milhao_perguntas`
--
ALTER TABLE `jogos_milhao_perguntas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jogos_milhao_respostas`
--
ALTER TABLE `jogos_milhao_respostas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jogos_sessoes`
--
ALTER TABLE `jogos_sessoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jogos_tokens_externos`
--
ALTER TABLE `jogos_tokens_externos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas`
--
ALTER TABLE `jornadas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_aulas`
--
ALTER TABLE `jornadas_aulas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_blocos_conteudo`
--
ALTER TABLE `jornadas_blocos_conteudo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_duvidas`
--
ALTER TABLE `jornadas_duvidas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_exercicios`
--
ALTER TABLE `jornadas_exercicios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_exercicios_auditoria`
--
ALTER TABLE `jornadas_exercicios_auditoria`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_materias`
--
ALTER TABLE `jornadas_materias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_mensagens`
--
ALTER TABLE `jornadas_mensagens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_mensagens_anexos`
--
ALTER TABLE `jornadas_mensagens_anexos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_modulos`
--
ALTER TABLE `jornadas_modulos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_modulos_documentos`
--
ALTER TABLE `jornadas_modulos_documentos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_modulos_exercicios`
--
ALTER TABLE `jornadas_modulos_exercicios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_modulos_textos`
--
ALTER TABLE `jornadas_modulos_textos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_modulos_videos`
--
ALTER TABLE `jornadas_modulos_videos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_progresso_alunos`
--
ALTER TABLE `jornadas_progresso_alunos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_progresso_blocos`
--
ALTER TABLE `jornadas_progresso_blocos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_redacoes`
--
ALTER TABLE `jornadas_redacoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_redacoes_alunos`
--
ALTER TABLE `jornadas_redacoes_alunos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_relatorios`
--
ALTER TABLE `jornadas_relatorios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_resumos_alunos`
--
ALTER TABLE `jornadas_resumos_alunos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_tempo_alunos`
--
ALTER TABLE `jornadas_tempo_alunos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_tipos_blocos`
--
ALTER TABLE `jornadas_tipos_blocos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jornadas_tudinha_explicacao_exercicio`
--
ALTER TABLE `jornadas_tudinha_explicacao_exercicio`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `listas_exercicios`
--
ALTER TABLE `listas_exercicios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `listas_exercicios_personalizadas`
--
ALTER TABLE `listas_exercicios_personalizadas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `listas_personalizadas_exercicios`
--
ALTER TABLE `listas_personalizadas_exercicios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `listas_personalizadas_respostas`
--
ALTER TABLE `listas_personalizadas_respostas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `listas_personalizadas_sessoes`
--
ALTER TABLE `listas_personalizadas_sessoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs_senhas`
--
ALTER TABLE `logs_senhas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs_uso_llm`
--
ALTER TABLE `logs_uso_llm`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `log_validacao_apps_externos`
--
ALTER TABLE `log_validacao_apps_externos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `materias`
--
ALTER TABLE `materias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `matricula`
--
ALTER TABLE `matricula`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `migrations_executadas`
--
ALTER TABLE `migrations_executadas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `minicursos`
--
ALTER TABLE `minicursos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `minicursos_arquivos`
--
ALTER TABLE `minicursos_arquivos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `minicursos_aulas`
--
ALTER TABLE `minicursos_aulas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `minicursos_modulos`
--
ALTER TABLE `minicursos_modulos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mobile_devices`
--
ALTER TABLE `mobile_devices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `modulos_apostilas`
--
ALTER TABLE `modulos_apostilas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `modulos_apostilas_anexos`
--
ALTER TABLE `modulos_apostilas_anexos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `modulos_apostilas_turmas`
--
ALTER TABLE `modulos_apostilas_turmas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `modulos_arquivos`
--
ALTER TABLE `modulos_arquivos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `modulos_arquivos_anexos`
--
ALTER TABLE `modulos_arquivos_anexos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `modulos_arquivos_pastas`
--
ALTER TABLE `modulos_arquivos_pastas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `modulos_arquivos_videos`
--
ALTER TABLE `modulos_arquivos_videos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `monitores`
--
ALTER TABLE `monitores`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `monitor_acoes_log`
--
ALTER TABLE `monitor_acoes_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mural_recados`
--
ALTER TABLE `mural_recados`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mural_recados_anexos`
--
ALTER TABLE `mural_recados_anexos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mural_recados_turmas`
--
ALTER TABLE `mural_recados_turmas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mural_recados_vistos`
--
ALTER TABLE `mural_recados_vistos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notes_tokens`
--
ALTER TABLE `notes_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `noticias`
--
ALTER TABLE `noticias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes_api`
--
ALTER TABLE `notificacoes_api`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes_configuracoes`
--
ALTER TABLE `notificacoes_configuracoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes_destinatarios`
--
ALTER TABLE `notificacoes_destinatarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes_historico`
--
ALTER TABLE `notificacoes_historico`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes_push`
--
ALTER TABLE `notificacoes_push`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes_push_envios`
--
ALTER TABLE `notificacoes_push_envios`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pacotes_creditos`
--
ALTER TABLE `pacotes_creditos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `partidas_dama`
--
ALTER TABLE `partidas_dama`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `patrimony_assets`
--
ALTER TABLE `patrimony_assets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `patrimony_inventory_checks`
--
ALTER TABLE `patrimony_inventory_checks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `patrimony_movements`
--
ALTER TABLE `patrimony_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `planos_aula`
--
ALTER TABLE `planos_aula`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `planos_creditos`
--
ALTER TABLE `planos_creditos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `plano_curso`
--
ALTER TABLE `plano_curso`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `plano_curso_habilidades`
--
ALTER TABLE `plano_curso_habilidades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pontuacao_alunos`
--
ALTER TABLE `pontuacao_alunos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professores`
--
ALTER TABLE `professores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professores_ia_agentes`
--
ALTER TABLE `professores_ia_agentes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professores_ia_conversas`
--
ALTER TABLE `professores_ia_conversas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professores_ia_documentos`
--
ALTER TABLE `professores_ia_documentos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professores_ia_documentos_chunks`
--
ALTER TABLE `professores_ia_documentos_chunks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professores_ia_mensagens`
--
ALTER TABLE `professores_ia_mensagens`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professores_slides`
--
ALTER TABLE `professores_slides`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professor_questoes_api`
--
ALTER TABLE `professor_questoes_api`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professor_questoes_montagem_itens`
--
ALTER TABLE `professor_questoes_montagem_itens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professor_questoes_montagens`
--
ALTER TABLE `professor_questoes_montagens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas`
--
ALTER TABLE `provas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_alternativas`
--
ALTER TABLE `provas_alternativas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_blocos`
--
ALTER TABLE `provas_blocos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_blocos_modelos`
--
ALTER TABLE `provas_blocos_modelos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_blocos_modelos_professores`
--
ALTER TABLE `provas_blocos_modelos_professores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_blocos_notas_lancadas`
--
ALTER TABLE `provas_blocos_notas_lancadas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_blocos_professores`
--
ALTER TABLE `provas_blocos_professores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_blocos_professores_turmas`
--
ALTER TABLE `provas_blocos_professores_turmas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_blocos_turmas`
--
ALTER TABLE `provas_blocos_turmas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_blocos_vinculo`
--
ALTER TABLE `provas_blocos_vinculo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_final`
--
ALTER TABLE `provas_final`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_professores`
--
ALTER TABLE `provas_professores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_questoes`
--
ALTER TABLE `provas_questoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_realizacoes`
--
ALTER TABLE `provas_realizacoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_respostas`
--
ALTER TABLE `provas_respostas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_respostas_log`
--
ALTER TABLE `provas_respostas_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_tipos_avaliacao`
--
ALTER TABLE `provas_tipos_avaliacao`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_turmas`
--
ALTER TABLE `provas_turmas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `provas_validacoes_log`
--
ALTER TABLE `provas_validacoes_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `questoes`
--
ALTER TABLE `questoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `questoes_personalizadas`
--
ALTER TABLE `questoes_personalizadas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacao_livre_correcoes`
--
ALTER TABLE `redacao_livre_correcoes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacao_livre_envios`
--
ALTER TABLE `redacao_livre_envios`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes`
--
ALTER TABLE `redacoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_orientadas_correcoes`
--
ALTER TABLE `redacoes_orientadas_correcoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_orientadas_correcoes_logs`
--
ALTER TABLE `redacoes_orientadas_correcoes_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_orientadas_criterios`
--
ALTER TABLE `redacoes_orientadas_criterios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_orientadas_entregas`
--
ALTER TABLE `redacoes_orientadas_entregas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_orientadas_prompts`
--
ALTER TABLE `redacoes_orientadas_prompts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_orientadas_propostas`
--
ALTER TABLE `redacoes_orientadas_propostas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_orientadas_propostas_alunos`
--
ALTER TABLE `redacoes_orientadas_propostas_alunos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_orientadas_propostas_turmas`
--
ALTER TABLE `redacoes_orientadas_propostas_turmas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_orientadas_quadros`
--
ALTER TABLE `redacoes_orientadas_quadros`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_orientadas_tipos_texto`
--
ALTER TABLE `redacoes_orientadas_tipos_texto`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redacoes_temas`
--
ALTER TABLE `redacoes_temas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `redefinicoes_senha`
--
ALTER TABLE `redefinicoes_senha`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `relatorios`
--
ALTER TABLE `relatorios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `responsaveis`
--
ALTER TABLE `responsaveis`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `reuniao_anexos`
--
ALTER TABLE `reuniao_anexos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `reunioes`
--
ALTER TABLE `reunioes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `school_calendar_events`
--
ALTER TABLE `school_calendar_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `school_communications`
--
ALTER TABLE `school_communications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `school_communication_attachments`
--
ALTER TABLE `school_communication_attachments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `school_communication_replies`
--
ALTER TABLE `school_communication_replies`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `school_locations`
--
ALTER TABLE `school_locations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `serie`
--
ALTER TABLE `serie`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `simulados`
--
ALTER TABLE `simulados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `simulados_estatisticas`
--
ALTER TABLE `simulados_estatisticas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `simulados_questoes`
--
ALTER TABLE `simulados_questoes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `student_access_events`
--
ALTER TABLE `student_access_events`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `student_accommodations`
--
ALTER TABLE `student_accommodations`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `student_face_profiles`
--
ALTER TABLE `student_face_profiles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `student_face_samples`
--
ALTER TABLE `student_face_samples`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `suporte_tickets`
--
ALTER TABLE `suporte_tickets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `suporte_tickets_mensagens`
--
ALTER TABLE `suporte_tickets_mensagens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tentativas_login`
--
ALTER TABLE `tentativas_login`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tipos_curso`
--
ALTER TABLE `tipos_curso`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tudinha_analises`
--
ALTER TABLE `tudinha_analises`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tudinha_conversas`
--
ALTER TABLE `tudinha_conversas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tudinha_mensagens`
--
ALTER TABLE `tudinha_mensagens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `turmas`
--
ALTER TABLE `turmas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tutoriais`
--
ALTER TABLE `tutoriais`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios_consentimentos`
--
ALTER TABLE `usuarios_consentimentos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `validacao_tokens_apps`
--
ALTER TABLE `validacao_tokens_apps`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `webhooks`
--
ALTER TABLE `webhooks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `accommodation_documents`
--
ALTER TABLE `accommodation_documents`
  ADD CONSTRAINT `fk_accdoc_accommodation` FOREIGN KEY (`accommodation_id`) REFERENCES `student_accommodations` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `accommodation_rules`
--
ALTER TABLE `accommodation_rules`
  ADD CONSTRAINT `fk_accrule_accommodation` FOREIGN KEY (`accommodation_id`) REFERENCES `student_accommodations` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `admin_perfis_permissao`
--
ALTER TABLE `admin_perfis_permissao`
  ADD CONSTRAINT `fk_admin_perfis_permissao_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `alunos`
--
ALTER TABLE `alunos`
  ADD CONSTRAINT `alunos_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `alunos_ibfk_3` FOREIGN KEY (`responsavel_id`) REFERENCES `responsaveis` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `alunos_acoes_diarias`
--
ALTER TABLE `alunos_acoes_diarias`
  ADD CONSTRAINT `alunos_acoes_diarias_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `alunos_onboarding`
--
ALTER TABLE `alunos_onboarding`
  ADD CONSTRAINT `alunos_onboarding_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `alunos_responsaveis`
--
ALTER TABLE `alunos_responsaveis`
  ADD CONSTRAINT `fk_alunos_responsaveis_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_alunos_responsaveis_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `responsaveis` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `alunos_seguranca`
--
ALTER TABLE `alunos_seguranca`
  ADD CONSTRAINT `alunos_seguranca_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `alunos_sessoes_acesso`
--
ALTER TABLE `alunos_sessoes_acesso`
  ADD CONSTRAINT `fk_sessoes_acesso_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `alunos_turmas_historico`
--
ALTER TABLE `alunos_turmas_historico`
  ADD CONSTRAINT `alunos_turmas_historico_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alunos_turmas_historico_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `alunos_turma_chamada`
--
ALTER TABLE `alunos_turma_chamada`
  ADD CONSTRAINT `fk_chamada_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chamada_ano` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chamada_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `assinaturas_creditos`
--
ALTER TABLE `assinaturas_creditos`
  ADD CONSTRAINT `fk_assinaturas_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos_creditos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Restrições para tabelas `aulas_online`
--
ALTER TABLE `aulas_online`
  ADD CONSTRAINT `fk_aulas_online_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `aulas_online_turmas`
--
ALTER TABLE `aulas_online_turmas`
  ADD CONSTRAINT `fk_aulas_online_turmas_aula` FOREIGN KEY (`aula_online_id`) REFERENCES `aulas_online` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aulas_online_turmas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `avatares_alunos`
--
ALTER TABLE `avatares_alunos`
  ADD CONSTRAINT `avatares_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_atividades`
--
ALTER TABLE `ava_atividades`
  ADD CONSTRAINT `fk_ava_atv_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ava_atv_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ava_atv_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `ava_modulos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ava_atv_rubrica` FOREIGN KEY (`rubrica_id`) REFERENCES `ava_rubricas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `ava_atividade_entregas`
--
ALTER TABLE `ava_atividade_entregas`
  ADD CONSTRAINT `fk_ava_entrega_atividade` FOREIGN KEY (`atividade_id`) REFERENCES `ava_atividades` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_atividade_entrega_arquivos`
--
ALTER TABLE `ava_atividade_entrega_arquivos`
  ADD CONSTRAINT `fk_ava_entrega_arq_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `ava_atividade_entregas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_aulas`
--
ALTER TABLE `ava_aulas`
  ADD CONSTRAINT `fk_ava_aulas_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `ava_modulos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_aulas_ao_vivo`
--
ALTER TABLE `ava_aulas_ao_vivo`
  ADD CONSTRAINT `fk_ava_live_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ava_live_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `ava_modulos` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `ava_aula_anexos`
--
ALTER TABLE `ava_aula_anexos`
  ADD CONSTRAINT `fk_ava_anexos_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_certificados`
--
ALTER TABLE `ava_certificados`
  ADD CONSTRAINT `fk_ava_cert_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_comentarios`
--
ALTER TABLE `ava_comentarios`
  ADD CONSTRAINT `fk_ava_coment_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ava_coment_parent` FOREIGN KEY (`parent_id`) REFERENCES `ava_comentarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_cursos`
--
ALTER TABLE `ava_cursos`
  ADD CONSTRAINT `fk_ava_cursos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `ava_categorias` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `ava_disciplinas`
--
ALTER TABLE `ava_disciplinas`
  ADD CONSTRAINT `fk_ava_disc_curso` FOREIGN KEY (`curso_id`) REFERENCES `ava_cursos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ava_disc_semestre` FOREIGN KEY (`semestre_id`) REFERENCES `ava_semestres` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `ava_disciplina_avaliacoes`
--
ALTER TABLE `ava_disciplina_avaliacoes`
  ADD CONSTRAINT `fk_ava_disc_aval_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_matriculas_disciplina`
--
ALTER TABLE `ava_matriculas_disciplina`
  ADD CONSTRAINT `fk_ava_matricula_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_modulos`
--
ALTER TABLE `ava_modulos`
  ADD CONSTRAINT `fk_ava_modulos_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_progresso_aula`
--
ALTER TABLE `ava_progresso_aula`
  ADD CONSTRAINT `fk_ava_prog_aula_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_progresso_video`
--
ALTER TABLE `ava_progresso_video`
  ADD CONSTRAINT `fk_ava_prog_video_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_rubricas`
--
ALTER TABLE `ava_rubricas`
  ADD CONSTRAINT `fk_ava_rubricas_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_rubrica_criterios`
--
ALTER TABLE `ava_rubrica_criterios`
  ADD CONSTRAINT `fk_ava_rub_crit_rubrica` FOREIGN KEY (`rubrica_id`) REFERENCES `ava_rubricas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ava_semestres`
--
ALTER TABLE `ava_semestres`
  ADD CONSTRAINT `fk_ava_semestres_curso` FOREIGN KEY (`curso_id`) REFERENCES `ava_cursos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `boletim_componentes`
--
ALTER TABLE `boletim_componentes`
  ADD CONSTRAINT `fk_boletim_componentes_regra` FOREIGN KEY (`regra_id`) REFERENCES `boletim_regras` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `boletim_notas_manuais`
--
ALTER TABLE `boletim_notas_manuais`
  ADD CONSTRAINT `fk_boletim_notas_manuais_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_boletim_notas_manuais_componente` FOREIGN KEY (`componente_id`) REFERENCES `boletim_componentes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_boletim_notas_manuais_regra` FOREIGN KEY (`regra_id`) REFERENCES `boletim_regras` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `boletim_observacoes`
--
ALTER TABLE `boletim_observacoes`
  ADD CONSTRAINT `fk_boletim_observacoes_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `boletim_resultados_gerados`
--
ALTER TABLE `boletim_resultados_gerados`
  ADD CONSTRAINT `fk_boletim_resultados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_boletim_resultados_regra` FOREIGN KEY (`regra_id`) REFERENCES `boletim_regras` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cadernos_aluno`
--
ALTER TABLE `cadernos_aluno`
  ADD CONSTRAINT `cadernos_aluno_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cadernos_aluno_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_caderno_pasta` FOREIGN KEY (`pasta_id`) REFERENCES `cadernos_aluno_pastas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `cadernos_aluno_anexos`
--
ALTER TABLE `cadernos_aluno_anexos`
  ADD CONSTRAINT `cadernos_aluno_anexos_ibfk_1` FOREIGN KEY (`caderno_id`) REFERENCES `cadernos_aluno` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cadernos_aluno_pastas`
--
ALTER TABLE `cadernos_aluno_pastas`
  ADD CONSTRAINT `cadernos_aluno_pastas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `calendario_letivo_eventos`
--
ALTER TABLE `calendario_letivo_eventos`
  ADD CONSTRAINT `fk_cal_eventos_calendario` FOREIGN KEY (`calendario_id`) REFERENCES `calendario_letivo` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `chat_professores_alunos`
--
ALTER TABLE `chat_professores_alunos`
  ADD CONSTRAINT `chat_professores_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_professores_alunos_ibfk_2` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `chat_professores_alunos_anexos`
--
ALTER TABLE `chat_professores_alunos_anexos`
  ADD CONSTRAINT `chat_professores_alunos_anexos_ibfk_1` FOREIGN KEY (`mensagem_id`) REFERENCES `chat_professores_alunos_mensagens` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `chat_professores_alunos_mensagens`
--
ALTER TABLE `chat_professores_alunos_mensagens`
  ADD CONSTRAINT `chat_professores_alunos_mensagens_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `chat_professores_alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `compras_creditos`
--
ALTER TABLE `compras_creditos`
  ADD CONSTRAINT `fk_compras_pacote` FOREIGN KEY (`pacote_id`) REFERENCES `pacotes_creditos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Restrições para tabelas `cursos`
--
ALTER TABLE `cursos`
  ADD CONSTRAINT `fk_cursos_tipo_curso` FOREIGN KEY (`tipo_curso_id`) REFERENCES `tipos_curso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Restrições para tabelas `diario_frequencias`
--
ALTER TABLE `diario_frequencias`
  ADD CONSTRAINT `fk_diario_freq_aula` FOREIGN KEY (`diario_aula_id`) REFERENCES `diario_aulas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `drive_compartilhamentos`
--
ALTER TABLE `drive_compartilhamentos`
  ADD CONSTRAINT `fk_drive_share_item` FOREIGN KEY (`item_id`) REFERENCES `drive_itens` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `drive_itens`
--
ALTER TABLE `drive_itens`
  ADD CONSTRAINT `fk_drive_parent` FOREIGN KEY (`parent_id`) REFERENCES `drive_itens` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `educalabs_messages`
--
ALTER TABLE `educalabs_messages`
  ADD CONSTRAINT `fk_educalabs_messages_project` FOREIGN KEY (`project_id`) REFERENCES `educalabs_projects` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `educa_hits_track_classes`
--
ALTER TABLE `educa_hits_track_classes`
  ADD CONSTRAINT `fk_educa_hits_tc_class` FOREIGN KEY (`class_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_educa_hits_tc_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `educa_hits_track_grades`
--
ALTER TABLE `educa_hits_track_grades`
  ADD CONSTRAINT `fk_educa_hits_tg_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `educa_hits_track_schools`
--
ALTER TABLE `educa_hits_track_schools`
  ADD CONSTRAINT `fk_educa_hits_ts_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `educa_hits_track_users`
--
ALTER TABLE `educa_hits_track_users`
  ADD CONSTRAINT `fk_educa_hits_tu_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `enem_alternativas`
--
ALTER TABLE `enem_alternativas`
  ADD CONSTRAINT `enem_alternativas_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `enem_questoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `enem_disciplinas`
--
ALTER TABLE `enem_disciplinas`
  ADD CONSTRAINT `enem_disciplinas_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `enem_provas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `enem_questoes`
--
ALTER TABLE `enem_questoes`
  ADD CONSTRAINT `enem_questoes_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `enem_provas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `enem_questoes_arquivos`
--
ALTER TABLE `enem_questoes_arquivos`
  ADD CONSTRAINT `enem_questoes_arquivos_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `enem_questoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `estatisticas_dama`
--
ALTER TABLE `estatisticas_dama`
  ADD CONSTRAINT `estatisticas_dama_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `exercicios`
--
ALTER TABLE `exercicios`
  ADD CONSTRAINT `exercicios_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exercicios_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `exercicios_estatisticas_alunos`
--
ALTER TABLE `exercicios_estatisticas_alunos`
  ADD CONSTRAINT `exercicios_estatisticas_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `exercicios_estatisticas_turmas`
--
ALTER TABLE `exercicios_estatisticas_turmas`
  ADD CONSTRAINT `exercicios_estatisticas_turmas_ibfk_1` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `exercicios_execucoes`
--
ALTER TABLE `exercicios_execucoes`
  ADD CONSTRAINT `exercicios_execucoes_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exercicios_execucoes_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `exercicios_historico`
--
ALTER TABLE `exercicios_historico`
  ADD CONSTRAINT `exercicios_historico_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exercicios_historico_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exercicios_historico_ibfk_3` FOREIGN KEY (`sessao_id`) REFERENCES `exercicios_sessoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `exercicios_respostas`
--
ALTER TABLE `exercicios_respostas`
  ADD CONSTRAINT `exercicios_respostas_ibfk_1` FOREIGN KEY (`sessao_id`) REFERENCES `exercicios_sessoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exercicios_respostas_ibfk_2` FOREIGN KEY (`exercicio_id`) REFERENCES `questoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exercicios_respostas_ibfk_3` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `exercicios_sessoes`
--
ALTER TABLE `exercicios_sessoes`
  ADD CONSTRAINT `exercicios_sessoes_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exercicios_sessoes_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `faltas_lancamentos`
--
ALTER TABLE `faltas_lancamentos`
  ADD CONSTRAINT `fk_faltas_lanc_evento` FOREIGN KEY (`evento_id`) REFERENCES `faltas_eventos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `finance_bills`
--
ALTER TABLE `finance_bills`
  ADD CONSTRAINT `finance_bills_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `finance_chart_accounts` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `flashcards_cartas`
--
ALTER TABLE `flashcards_cartas`
  ADD CONSTRAINT `flashcards_cartas_ibfk_1` FOREIGN KEY (`deck_id`) REFERENCES `flashcards_baralhos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `flashcards_modelos_cartas`
--
ALTER TABLE `flashcards_modelos_cartas`
  ADD CONSTRAINT `flashcards_modelos_cartas_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `flashcards_modelos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `forum_anexos`
--
ALTER TABLE `forum_anexos`
  ADD CONSTRAINT `forum_anexos_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_anexos_ibfk_2` FOREIGN KEY (`reply_id`) REFERENCES `forum_respostas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `forum_denuncias`
--
ALTER TABLE `forum_denuncias`
  ADD CONSTRAINT `forum_denuncias_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_denuncias_ibfk_2` FOREIGN KEY (`reply_id`) REFERENCES `forum_respostas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `forum_respostas`
--
ALTER TABLE `forum_respostas`
  ADD CONSTRAINT `forum_respostas_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `forum_topicos_turmas`
--
ALTER TABLE `forum_topicos_turmas`
  ADD CONSTRAINT `forum_topicos_turmas_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `forum_votos`
--
ALTER TABLE `forum_votos`
  ADD CONSTRAINT `forum_votos_ibfk_1` FOREIGN KEY (`reply_id`) REFERENCES `forum_respostas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `grade_horaria`
--
ALTER TABLE `grade_horaria`
  ADD CONSTRAINT `fk_gh_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_gh_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_gh_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ingles_conversas`
--
ALTER TABLE `ingles_conversas`
  ADD CONSTRAINT `ingles_conversas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ingles_mensagens`
--
ALTER TABLE `ingles_mensagens`
  ADD CONSTRAINT `ingles_mensagens_ibfk_1` FOREIGN KEY (`conversa_id`) REFERENCES `ingles_conversas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jogos_acoes`
--
ALTER TABLE `jogos_acoes`
  ADD CONSTRAINT `fk_game_actions_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_game_actions_partida` FOREIGN KEY (`partida_id`) REFERENCES `jogos_milhao_partidas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jogos_milhao_partidas`
--
ALTER TABLE `jogos_milhao_partidas`
  ADD CONSTRAINT `jogos_milhao_partidas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jogos_milhao_respostas`
--
ALTER TABLE `jogos_milhao_respostas`
  ADD CONSTRAINT `jogos_milhao_respostas_ibfk_1` FOREIGN KEY (`partida_id`) REFERENCES `jogos_milhao_partidas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jogos_milhao_respostas_ibfk_2` FOREIGN KEY (`pergunta_id`) REFERENCES `jogos_milhao_perguntas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jogos_sessoes`
--
ALTER TABLE `jogos_sessoes`
  ADD CONSTRAINT `fk_game_sessions_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_game_sessions_partida` FOREIGN KEY (`partida_id`) REFERENCES `jogos_milhao_partidas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas`
--
ALTER TABLE `jornadas`
  ADD CONSTRAINT `jornadas_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_blocos_conteudo`
--
ALTER TABLE `jornadas_blocos_conteudo`
  ADD CONSTRAINT `jornadas_blocos_conteudo_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_blocos_conteudo_ibfk_2` FOREIGN KEY (`tipo_bloco_id`) REFERENCES `jornadas_tipos_blocos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_mensagens`
--
ALTER TABLE `jornadas_mensagens`
  ADD CONSTRAINT `jornadas_mensagens_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_mensagens_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_mensagens_ibfk_3` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_mensagens_anexos`
--
ALTER TABLE `jornadas_mensagens_anexos`
  ADD CONSTRAINT `jornadas_mensagens_anexos_ibfk_1` FOREIGN KEY (`mensagem_id`) REFERENCES `jornadas_mensagens` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_modulos`
--
ALTER TABLE `jornadas_modulos`
  ADD CONSTRAINT `jornadas_modulos_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_modulos_ibfk_2` FOREIGN KEY (`aula_id`) REFERENCES `jornadas_aulas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `jornadas_modulos_documentos`
--
ALTER TABLE `jornadas_modulos_documentos`
  ADD CONSTRAINT `jornadas_modulos_documentos_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_modulos_exercicios`
--
ALTER TABLE `jornadas_modulos_exercicios`
  ADD CONSTRAINT `jornadas_modulos_exercicios_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_modulos_textos`
--
ALTER TABLE `jornadas_modulos_textos`
  ADD CONSTRAINT `fk_jmt_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_modulos_videos`
--
ALTER TABLE `jornadas_modulos_videos`
  ADD CONSTRAINT `jornadas_modulos_videos_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_progresso_alunos`
--
ALTER TABLE `jornadas_progresso_alunos`
  ADD CONSTRAINT `jornadas_progresso_alunos_ibfk_exercicio_modulo` FOREIGN KEY (`exercicio_modulo_id`) REFERENCES `jornadas_modulos_exercicios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_progresso_alunos_ibfk_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_progresso_blocos`
--
ALTER TABLE `jornadas_progresso_blocos`
  ADD CONSTRAINT `jornadas_progresso_blocos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_progresso_blocos_ibfk_2` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_progresso_blocos_ibfk_3` FOREIGN KEY (`bloco_id`) REFERENCES `jornadas_blocos_conteudo` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_redacoes`
--
ALTER TABLE `jornadas_redacoes`
  ADD CONSTRAINT `jornadas_redacoes_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_redacoes_ibfk_2` FOREIGN KEY (`aula_id`) REFERENCES `jornadas_aulas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `jornadas_redacoes_ibfk_3` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_redacoes_alunos`
--
ALTER TABLE `jornadas_redacoes_alunos`
  ADD CONSTRAINT `jornadas_redacoes_alunos_ibfk_1` FOREIGN KEY (`jornada_redacao_id`) REFERENCES `jornadas_redacoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_redacoes_alunos_ibfk_2` FOREIGN KEY (`redacao_id`) REFERENCES `redacoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_redacoes_alunos_ibfk_3` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_resumos_alunos`
--
ALTER TABLE `jornadas_resumos_alunos`
  ADD CONSTRAINT `jornadas_resumos_alunos_ibfk_jornada` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_resumos_alunos_ibfk_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_tempo_alunos`
--
ALTER TABLE `jornadas_tempo_alunos`
  ADD CONSTRAINT `jornadas_tempo_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_tempo_alunos_ibfk_2` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `jornadas_tudinha_explicacao_exercicio`
--
ALTER TABLE `jornadas_tudinha_explicacao_exercicio`
  ADD CONSTRAINT `jtee_fk_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jtee_fk_exercicio` FOREIGN KEY (`exercicio_modulo_id`) REFERENCES `jornadas_modulos_exercicios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `listas_exercicios`
--
ALTER TABLE `listas_exercicios`
  ADD CONSTRAINT `listas_exercicios_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `listas_exercicios_personalizadas`
--
ALTER TABLE `listas_exercicios_personalizadas`
  ADD CONSTRAINT `listas_exercicios_personalizadas_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `listas_exercicios_personalizadas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `listas_personalizadas_exercicios`
--
ALTER TABLE `listas_personalizadas_exercicios`
  ADD CONSTRAINT `fk_listas_exercicios_personalizados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `listas_personalizadas_respostas`
--
ALTER TABLE `listas_personalizadas_respostas`
  ADD CONSTRAINT `fk_respostas_exercicios_personalizados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_respostas_exercicios_personalizados_questao` FOREIGN KEY (`questao_id`) REFERENCES `questoes_personalizadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_respostas_exercicios_personalizados_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `listas_personalizadas_sessoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `listas_personalizadas_sessoes`
--
ALTER TABLE `listas_personalizadas_sessoes`
  ADD CONSTRAINT `fk_sessoes_exercicios_personalizados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sessoes_exercicios_personalizados_lista` FOREIGN KEY (`lista_id`) REFERENCES `listas_personalizadas_exercicios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `logs_senhas`
--
ALTER TABLE `logs_senhas`
  ADD CONSTRAINT `logs_senhas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `logs_senhas_ibfk_2` FOREIGN KEY (`alterado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `matricula`
--
ALTER TABLE `matricula`
  ADD CONSTRAINT `fk_matricula_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_matricula_ano_letivo` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_matricula_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `migrations_executadas`
--
ALTER TABLE `migrations_executadas`
  ADD CONSTRAINT `migrations_executadas_ibfk_1` FOREIGN KEY (`escola_database_config_id`) REFERENCES `config_escolas_database` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `minicursos_arquivos`
--
ALTER TABLE `minicursos_arquivos`
  ADD CONSTRAINT `minicursos_arquivos_ibfk_1` FOREIGN KEY (`minicurso_id`) REFERENCES `minicursos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `minicursos_aulas`
--
ALTER TABLE `minicursos_aulas`
  ADD CONSTRAINT `minicursos_aulas_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `minicursos_modulos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `minicursos_modulos`
--
ALTER TABLE `minicursos_modulos`
  ADD CONSTRAINT `minicursos_modulos_ibfk_1` FOREIGN KEY (`minicurso_id`) REFERENCES `minicursos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `minicursos_progresso`
--
ALTER TABLE `minicursos_progresso`
  ADD CONSTRAINT `minicursos_progresso_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `minicursos_progresso_ibfk_2` FOREIGN KEY (`minicurso_id`) REFERENCES `minicursos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `mobile_auth_sessions`
--
ALTER TABLE `mobile_auth_sessions`
  ADD CONSTRAINT `fk_mobile_auth_parent` FOREIGN KEY (`parent_id`) REFERENCES `responsaveis` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `mobile_devices`
--
ALTER TABLE `mobile_devices`
  ADD CONSTRAINT `fk_mobile_devices_parent` FOREIGN KEY (`parent_id`) REFERENCES `responsaveis` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `modulos_apostilas`
--
ALTER TABLE `modulos_apostilas`
  ADD CONSTRAINT `fk_modulos_apostilas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `modulos_apostilas_anexos`
--
ALTER TABLE `modulos_apostilas_anexos`
  ADD CONSTRAINT `fk_modulos_apostilas_anexos_modulo` FOREIGN KEY (`modulo_apostila_id`) REFERENCES `modulos_apostilas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `modulos_apostilas_turmas`
--
ALTER TABLE `modulos_apostilas_turmas`
  ADD CONSTRAINT `fk_modulos_apostilas_turmas_modulo` FOREIGN KEY (`modulo_apostila_id`) REFERENCES `modulos_apostilas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_modulos_apostilas_turmas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `modulos_arquivos`
--
ALTER TABLE `modulos_arquivos`
  ADD CONSTRAINT `modulos_arquivos_ibfk_1` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modulos_arquivos_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modulos_arquivos_ibfk_3` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `modulos_arquivos_anexos`
--
ALTER TABLE `modulos_arquivos_anexos`
  ADD CONSTRAINT `modulos_arquivos_anexos_ibfk_1` FOREIGN KEY (`modulo_arquivo_id`) REFERENCES `modulos_arquivos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `modulos_arquivos_turmas`
--
ALTER TABLE `modulos_arquivos_turmas`
  ADD CONSTRAINT `modulos_arquivos_turmas_ibfk_1` FOREIGN KEY (`modulo_arquivo_id`) REFERENCES `modulos_arquivos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modulos_arquivos_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `modulos_arquivos_videos`
--
ALTER TABLE `modulos_arquivos_videos`
  ADD CONSTRAINT `fk_ma_videos_modulo` FOREIGN KEY (`modulo_arquivo_id`) REFERENCES `modulos_arquivos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `mural_recados_anexos`
--
ALTER TABLE `mural_recados_anexos`
  ADD CONSTRAINT `fk_mural_anexos_recado` FOREIGN KEY (`mural_recado_id`) REFERENCES `mural_recados` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `mural_recados_turmas`
--
ALTER TABLE `mural_recados_turmas`
  ADD CONSTRAINT `fk_mural_turmas_recado` FOREIGN KEY (`mural_recado_id`) REFERENCES `mural_recados` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `mural_recados_vistos`
--
ALTER TABLE `mural_recados_vistos`
  ADD CONSTRAINT `fk_mural_vistos_recado` FOREIGN KEY (`mural_recado_id`) REFERENCES `mural_recados` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `notificacoes_destinatarios`
--
ALTER TABLE `notificacoes_destinatarios`
  ADD CONSTRAINT `notificacoes_destinatarios_ibfk_1` FOREIGN KEY (`notificacao_id`) REFERENCES `notificacoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `notificacoes_historico`
--
ALTER TABLE `notificacoes_historico`
  ADD CONSTRAINT `notificacoes_historico_ibfk_1` FOREIGN KEY (`notificacao_id`) REFERENCES `notificacoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `notificacoes_push_envios`
--
ALTER TABLE `notificacoes_push_envios`
  ADD CONSTRAINT `notificacoes_push_envios_ibfk_1` FOREIGN KEY (`notificacao_id`) REFERENCES `notificacoes_push` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `partidas_dama`
--
ALTER TABLE `partidas_dama`
  ADD CONSTRAINT `partidas_dama_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `planos_aula`
--
ALTER TABLE `planos_aula`
  ADD CONSTRAINT `planos_aula_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `planos_aula_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `planos_aula_ibfk_3` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `plano_curso_habilidades`
--
ALTER TABLE `plano_curso_habilidades`
  ADD CONSTRAINT `fk_pch_plano` FOREIGN KEY (`plano_curso_id`) REFERENCES `plano_curso` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pontuacao_alunos`
--
ALTER TABLE `pontuacao_alunos`
  ADD CONSTRAINT `pontuacao_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `professores_slides`
--
ALTER TABLE `professores_slides`
  ADD CONSTRAINT `fk_professor_slides_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `professor_questoes_montagem_itens`
--
ALTER TABLE `professor_questoes_montagem_itens`
  ADD CONSTRAINT `fk_prof_montagem_itens_montagem` FOREIGN KEY (`montagem_id`) REFERENCES `professor_questoes_montagens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prof_montagem_itens_questao` FOREIGN KEY (`questao_id`) REFERENCES `professor_questoes_api` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas`
--
ALTER TABLE `provas`
  ADD CONSTRAINT `provas_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_ibfk_3` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `provas_alternativas`
--
ALTER TABLE `provas_alternativas`
  ADD CONSTRAINT `provas_alternativas_ibfk_1` FOREIGN KEY (`questao_id`) REFERENCES `provas_questoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas_blocos`
--
ALTER TABLE `provas_blocos`
  ADD CONSTRAINT `fk_provas_blocos_tipo_avaliacao` FOREIGN KEY (`tipo_avaliacao_id`) REFERENCES `provas_tipos_avaliacao` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `provas_blocos_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `provas_blocos_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `provas_blocos_ibfk_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `provas_blocos_ibfk_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `provas_blocos_modelos`
--
ALTER TABLE `provas_blocos_modelos`
  ADD CONSTRAINT `provas_blocos_modelos_ibfk_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas_blocos_modelos_professores`
--
ALTER TABLE `provas_blocos_modelos_professores`
  ADD CONSTRAINT `provas_blocos_modelos_professores_ibfk_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_blocos_modelos_professores_ibfk_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `provas_blocos_modelos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_blocos_modelos_professores_ibfk_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas_blocos_professores`
--
ALTER TABLE `provas_blocos_professores`
  ADD CONSTRAINT `provas_blocos_professores_ibfk_bloco` FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_blocos_professores_ibfk_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_blocos_professores_ibfk_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas_blocos_professores_turmas`
--
ALTER TABLE `provas_blocos_professores_turmas`
  ADD CONSTRAINT `provas_blocos_professores_turmas_ibfk_bloco_professor` FOREIGN KEY (`bloco_professor_id`) REFERENCES `provas_blocos_professores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_blocos_professores_turmas_ibfk_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas_blocos_turmas`
--
ALTER TABLE `provas_blocos_turmas`
  ADD CONSTRAINT `provas_blocos_turmas_ibfk_1` FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_blocos_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas_blocos_vinculo`
--
ALTER TABLE `provas_blocos_vinculo`
  ADD CONSTRAINT `provas_blocos_vinculo_ibfk_1` FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_blocos_vinculo_ibfk_2` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas_final`
--
ALTER TABLE `provas_final`
  ADD CONSTRAINT `provas_final_ibfk_bloco` FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_final_ibfk_prova` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas_professores`
--
ALTER TABLE `provas_professores`
  ADD CONSTRAINT `provas_professores_ibfk_bloco` FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_professores_ibfk_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_professores_ibfk_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_professores_ibfk_prova` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `provas_questoes`
--
ALTER TABLE `provas_questoes`
  ADD CONSTRAINT `provas_questoes_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas_realizacoes`
--
ALTER TABLE `provas_realizacoes`
  ADD CONSTRAINT `provas_realizacoes_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_realizacoes_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `provas_respostas`
--
ALTER TABLE `provas_respostas`
  ADD CONSTRAINT `provas_respostas_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_respostas_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_respostas_ibfk_3` FOREIGN KEY (`questao_id`) REFERENCES `provas_questoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_respostas_ibfk_4` FOREIGN KEY (`alternativa_id`) REFERENCES `provas_alternativas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `provas_turmas`
--
ALTER TABLE `provas_turmas`
  ADD CONSTRAINT `provas_turmas_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provas_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `questoes`
--
ALTER TABLE `questoes`
  ADD CONSTRAINT `questoes_ibfk_1` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `questoes_personalizadas`
--
ALTER TABLE `questoes_personalizadas`
  ADD CONSTRAINT `fk_questoes_personalizadas_lista` FOREIGN KEY (`lista_id`) REFERENCES `listas_personalizadas_exercicios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `redacoes`
--
ALTER TABLE `redacoes`
  ADD CONSTRAINT `redacoes_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `redacoes_ibfk_2` FOREIGN KEY (`tema_id`) REFERENCES `redacoes_temas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `redacoes_ibfk_jornada` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `redacoes_orientadas_correcoes`
--
ALTER TABLE `redacoes_orientadas_correcoes`
  ADD CONSTRAINT `redacoes_orientadas_correcoes_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `redacoes_orientadas_entregas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `redacoes_orientadas_correcoes_ibfk_2` FOREIGN KEY (`prompt_id`) REFERENCES `redacoes_orientadas_prompts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `redacoes_orientadas_correcoes_ibfk_3` FOREIGN KEY (`corrected_by_teacher_id`) REFERENCES `professores` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `redacoes_orientadas_correcoes_logs`
--
ALTER TABLE `redacoes_orientadas_correcoes_logs`
  ADD CONSTRAINT `redacoes_orientadas_correcoes_logs_ibfk_1` FOREIGN KEY (`correction_id`) REFERENCES `redacoes_orientadas_correcoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `redacoes_orientadas_criterios`
--
ALTER TABLE `redacoes_orientadas_criterios`
  ADD CONSTRAINT `redacoes_orientadas_criterios_ibfk_1` FOREIGN KEY (`text_type_id`) REFERENCES `redacoes_orientadas_tipos_texto` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `redacoes_orientadas_entregas`
--
ALTER TABLE `redacoes_orientadas_entregas`
  ADD CONSTRAINT `redacoes_orientadas_entregas_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `redacoes_orientadas_propostas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `redacoes_orientadas_entregas_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `redacoes_orientadas_prompts`
--
ALTER TABLE `redacoes_orientadas_prompts`
  ADD CONSTRAINT `redacoes_orientadas_prompts_ibfk_1` FOREIGN KEY (`board_id`) REFERENCES `redacoes_orientadas_quadros` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `redacoes_orientadas_prompts_ibfk_2` FOREIGN KEY (`text_type_id`) REFERENCES `redacoes_orientadas_tipos_texto` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `redacoes_orientadas_propostas`
--
ALTER TABLE `redacoes_orientadas_propostas`
  ADD CONSTRAINT `redacoes_orientadas_propostas_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `redacoes_orientadas_propostas_ibfk_2` FOREIGN KEY (`board_id`) REFERENCES `redacoes_orientadas_quadros` (`id`),
  ADD CONSTRAINT `redacoes_orientadas_propostas_ibfk_3` FOREIGN KEY (`text_type_id`) REFERENCES `redacoes_orientadas_tipos_texto` (`id`);

--
-- Restrições para tabelas `redacoes_orientadas_propostas_alunos`
--
ALTER TABLE `redacoes_orientadas_propostas_alunos`
  ADD CONSTRAINT `redacoes_orientadas_propostas_alunos_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `redacoes_orientadas_propostas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `redacoes_orientadas_propostas_alunos_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `redacoes_orientadas_propostas_turmas`
--
ALTER TABLE `redacoes_orientadas_propostas_turmas`
  ADD CONSTRAINT `redacoes_orientadas_propostas_turmas_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `redacoes_orientadas_propostas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `redacoes_orientadas_propostas_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `redacoes_orientadas_tipos_texto`
--
ALTER TABLE `redacoes_orientadas_tipos_texto`
  ADD CONSTRAINT `redacoes_orientadas_tipos_texto_ibfk_1` FOREIGN KEY (`board_id`) REFERENCES `redacoes_orientadas_quadros` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `relatorios`
--
ALTER TABLE `relatorios`
  ADD CONSTRAINT `relatorios_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `school_calendar_event_classes`
--
ALTER TABLE `school_calendar_event_classes`
  ADD CONSTRAINT `fk_school_calendar_class` FOREIGN KEY (`event_id`) REFERENCES `school_calendar_events` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `school_calendar_event_reads`
--
ALTER TABLE `school_calendar_event_reads`
  ADD CONSTRAINT `fk_school_calendar_read` FOREIGN KEY (`event_id`) REFERENCES `school_calendar_events` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `school_calendar_event_students`
--
ALTER TABLE `school_calendar_event_students`
  ADD CONSTRAINT `fk_school_calendar_student` FOREIGN KEY (`event_id`) REFERENCES `school_calendar_events` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `school_communication_attachments`
--
ALTER TABLE `school_communication_attachments`
  ADD CONSTRAINT `fk_school_communication_attachment` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `school_communication_classes`
--
ALTER TABLE `school_communication_classes`
  ADD CONSTRAINT `fk_school_communication_class` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `school_communication_reads`
--
ALTER TABLE `school_communication_reads`
  ADD CONSTRAINT `fk_school_communication_read` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `school_communication_replies`
--
ALTER TABLE `school_communication_replies`
  ADD CONSTRAINT `fk_school_communication_reply` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `school_communication_students`
--
ALTER TABLE `school_communication_students`
  ADD CONSTRAINT `fk_school_communication_student` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `serie`
--
ALTER TABLE `serie`
  ADD CONSTRAINT `fk_serie_curso` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Restrições para tabelas `sessoes`
--
ALTER TABLE `sessoes`
  ADD CONSTRAINT `sessoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `student_access_events`
--
ALTER TABLE `student_access_events`
  ADD CONSTRAINT `fk_access_event_student` FOREIGN KEY (`student_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `student_face_profiles`
--
ALTER TABLE `student_face_profiles`
  ADD CONSTRAINT `fk_face_profile_student` FOREIGN KEY (`student_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `student_face_samples`
--
ALTER TABLE `student_face_samples`
  ADD CONSTRAINT `fk_face_sample_profile` FOREIGN KEY (`face_profile_id`) REFERENCES `student_face_profiles` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `suporte_tickets`
--
ALTER TABLE `suporte_tickets`
  ADD CONSTRAINT `suporte_tickets_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `suporte_tickets_mensagens`
--
ALTER TABLE `suporte_tickets_mensagens`
  ADD CONSTRAINT `suporte_tickets_mensagens_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `suporte_tickets` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tudinha_analises`
--
ALTER TABLE `tudinha_analises`
  ADD CONSTRAINT `fk_analises_tudinha_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tudinha_conversas`
--
ALTER TABLE `tudinha_conversas`
  ADD CONSTRAINT `tudinha_conversas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tudinha_mensagens`
--
ALTER TABLE `tudinha_mensagens`
  ADD CONSTRAINT `tudinha_mensagens_ibfk_1` FOREIGN KEY (`conversa_id`) REFERENCES `tudinha_conversas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tudinha_mensagens_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `turmas`
--
ALTER TABLE `turmas`
  ADD CONSTRAINT `fk_turmas_ano_letivo` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_turmas_curso_novo` FOREIGN KEY (`curso_novo_id`) REFERENCES `curso` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_turmas_serie` FOREIGN KEY (`serie_id`) REFERENCES `serie` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `turmas_lista_config`
--
ALTER TABLE `turmas_lista_config`
  ADD CONSTRAINT `fk_turmas_lista_ano` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_turmas_lista_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_perfil_permissao` FOREIGN KEY (`perfil_permissao_id`) REFERENCES `admin_perfis_permissao` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
