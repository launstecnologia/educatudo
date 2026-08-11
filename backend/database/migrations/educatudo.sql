-- MySQL dump 10.13  Distrib 8.0.45, for macos15 (arm64)
--
-- Host: 72.61.28.136    Database: educa_002_colag
-- ------------------------------------------------------
-- Server version	9.6.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/* ajustes para a migration */
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;
SET AUTOCOMMIT = 0;

START TRANSACTION;

--
-- Table structure for table `alertas_sensiveis`
--

DROP TABLE IF EXISTS `alertas_sensiveis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alertas_sensiveis` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `retention_reason` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_alertas_aluno` (`aluno_id`),
  KEY `idx_alertas_status` (`status`),
  KEY `idx_alertas_nivel` (`nivel`),
  KEY `idx_alertas_turma` (`turma_id`),
  KEY `idx_alertas_expires` (`expires_at`),
  KEY `idx_alertas_anonymized` (`anonymized_at`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alertas_sensiveis_acoes`
--

DROP TABLE IF EXISTS `alertas_sensiveis_acoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alertas_sensiveis_acoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `alerta_id` int NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `acao` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL,
  `observacoes` text COLLATE utf8mb3_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `anonymized_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `retention_reason` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_alerta` (`alerta_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_alertas_acoes_expires` (`expires_at`),
  KEY `idx_alertas_acoes_anonymized` (`anonymized_at`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alunos`
--

DROP TABLE IF EXISTS `alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ra` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Registro Acadêmico (opcional)',
  `turma_id` int DEFAULT NULL,
  `serie` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `data_nasc` date DEFAULT NULL,
  `responsavel_id` int DEFAULT NULL COMMENT 'Pai/Responsável vinculado',
  `ativo` tinyint(1) DEFAULT '1',
  `status` enum('ACTIVE','INACTIVE','GRADUATED','SUSPENDED') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `pagante` tinyint(1) DEFAULT '1' COMMENT 'Indica se a escola paga por este aluno',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `nickname` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nickname` (`nickname`),
  KEY `idx_turma` (`turma_id`),
  KEY `idx_responsavel` (`responsavel_id`),
  KEY `idx_ativo` (`ativo`),
  CONSTRAINT `alunos_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `alunos_ibfk_3` FOREIGN KEY (`responsavel_id`) REFERENCES `responsaveis` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=618 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alunos_acoes_diarias`
--

DROP TABLE IF EXISTS `alunos_acoes_diarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alunos_acoes_diarias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `acao` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de ação: gerar_tema_redacao, corrigir_redacao, etc.',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `acao` (`acao`),
  KEY `created_at` (`created_at`),
  KEY `idx_aluno_acao_data` (`aluno_id`,`acao`,`created_at`),
  CONSTRAINT `alunos_acoes_diarias_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4442 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registra ações diárias dos alunos para controle de limites';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alunos_historico_status`
--

DROP TABLE IF EXISTS `alunos_historico_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alunos_historico_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `old_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observation` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` int NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_status_student` (`student_id`),
  KEY `idx_student_status_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alunos_ocorrencias`
--

DROP TABLE IF EXISTS `alunos_ocorrencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alunos_ocorrencias` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ocorrencias_aluno` (`aluno_id`),
  KEY `idx_ocorrencias_data` (`data_ocorrencia`),
  KEY `idx_ocorrencias_gravidade` (`nivel_gravidade`),
  KEY `idx_ocorrencias_enviar` (`enviar_pais`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alunos_ocorrencias_itens`
--

DROP TABLE IF EXISTS `alunos_ocorrencias_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alunos_ocorrencias_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ocorrencia_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ocorrencia` (`ocorrencia_id`),
  KEY `idx_aluno` (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alunos_onboarding`
--

DROP TABLE IF EXISTS `alunos_onboarding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alunos_onboarding` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno` (`aluno_id`),
  KEY `idx_completado` (`completado`),
  CONSTRAINT `alunos_onboarding_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=472 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alunos_seguranca`
--

DROP TABLE IF EXISTS `alunos_seguranca`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alunos_seguranca` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `pergunta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resposta_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno` (`aluno_id`),
  CONSTRAINT `alunos_seguranca_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=525 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alunos_sessoes_acesso`
--

DROP TABLE IF EXISTS `alunos_sessoes_acesso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alunos_sessoes_acesso` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `logout_at` datetime DEFAULT NULL,
  `tempo_uso_segundos` int DEFAULT NULL COMMENT 'Tempo em segundos desde login até logout',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `status` enum('ativo','finalizado','expirado') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
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
) ENGINE=InnoDB AUTO_INCREMENT=8777 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alunos_turmas_historico`
--

DROP TABLE IF EXISTS `alunos_turmas_historico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alunos_turmas_historico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `ano_letivo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_turma` (`turma_id`),
  KEY `idx_ano` (`ano_letivo`),
  CONSTRAINT `alunos_turmas_historico_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alunos_turmas_historico_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `avatares_alunos`
--

DROP TABLE IF EXISTS `avatares_alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `avatares_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno_avatar` (`aluno_id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_criado_em` (`criado_em`),
  KEY `idx_avatar_url` (`avatar_url`),
  KEY `idx_avatar_seed` (`avatar_seed`),
  CONSTRAINT `avatares_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=174 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_enem_questions_20251028`
--

DROP TABLE IF EXISTS `backup_enem_questions_20251028`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cadernos_aluno`
--

DROP TABLE IF EXISTS `cadernos_aluno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cadernos_aluno` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `materia_id` int DEFAULT NULL COMMENT 'Matéria relacionada (opcional)',
  `pasta_id` int DEFAULT NULL COMMENT 'Pasta de estudo (opcional)',
  `observacao` text COLLATE utf8mb4_unicode_ci COMMENT 'Texto/anotação livre',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_caderno_aluno_id` (`aluno_id`),
  KEY `idx_caderno_materia` (`materia_id`),
  KEY `idx_caderno_pasta` (`pasta_id`),
  CONSTRAINT `cadernos_aluno_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cadernos_aluno_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_caderno_pasta` FOREIGN KEY (`pasta_id`) REFERENCES `cadernos_aluno_pastas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cadernos_aluno_anexos`
--

DROP TABLE IF EXISTS `cadernos_aluno_anexos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cadernos_aluno_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `caderno_id` int NOT NULL,
  `tipo` enum('imagem','documento') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'documento',
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int unsigned DEFAULT NULL COMMENT 'Tamanho em bytes',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_anexo_caderno` (`caderno_id`),
  CONSTRAINT `cadernos_aluno_anexos_ibfk_1` FOREIGN KEY (`caderno_id`) REFERENCES `cadernos_aluno` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cadernos_aluno_pastas`
--

DROP TABLE IF EXISTS `cadernos_aluno_pastas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cadernos_aluno_pastas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pasta_aluno` (`aluno_id`),
  CONSTRAINT `cadernos_aluno_pastas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_professores_alunos`
--

DROP TABLE IF EXISTS `chat_professores_alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_professores_alunos` (
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_professores_alunos_anexos`
--

DROP TABLE IF EXISTS `chat_professores_alunos_anexos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_professores_alunos_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mensagem_id` int NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho_arquivo` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mensagem_id` (`mensagem_id`),
  CONSTRAINT `chat_professores_alunos_anexos_ibfk_1` FOREIGN KEY (`mensagem_id`) REFERENCES `chat_professores_alunos_mensagens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_professores_alunos_mensagens`
--

DROP TABLE IF EXISTS `chat_professores_alunos_mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_professores_alunos_mensagens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `chat_id` int NOT NULL,
  `remetente_tipo` enum('aluno','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `remetente_id` int NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lida` tinyint(1) DEFAULT '0',
  `lida_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `chat_id` (`chat_id`),
  KEY `remetente_id` (`remetente_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `chat_professores_alunos_mensagens_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `chat_professores_alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `config_dev`
--

DROP TABLE IF EXISTS `config_dev`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `config_dev` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dev_settings_key` (`key_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `config_escolas_database`
--

DROP TABLE IF EXISTS `config_escolas_database`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `config_escolas_database` (
  `id` int NOT NULL AUTO_INCREMENT,
  `escola_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_port` int DEFAULT '3306',
  `db_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_pass` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_escola_nome` (`escola_nome`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `config_layout`
--

DROP TABLE IF EXISTS `config_layout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `config_layout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb3_unicode_ci,
  `config_type` enum('color','image','text','number') COLLATE utf8mb3_unicode_ci DEFAULT 'text',
  `description` text COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=934 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `config_simulados`
--

DROP TABLE IF EXISTS `config_simulados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `config_simulados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb3_unicode_ci,
  `tempo_limite_padrao` int DEFAULT '1800',
  `quantidade_questoes_padrao` int DEFAULT '10',
  `disciplinas_permitidas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `anos_permitidos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `ativo` tinyint(1) DEFAULT '1',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `config_simulados_chk_1` CHECK (json_valid(`disciplinas_permitidas`)),
  CONSTRAINT `config_simulados_chk_2` CHECK (json_valid(`anos_permitidos`))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `drive_compartilhamentos`
--

DROP TABLE IF EXISTS `drive_compartilhamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `drive_compartilhamentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int unsigned NOT NULL,
  `shared_with_id` int unsigned NOT NULL,
  `shared_with_type` enum('student','teacher') COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` enum('view','edit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'view',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_share` (`item_id`,`shared_with_id`,`shared_with_type`),
  KEY `idx_shared_with` (`shared_with_id`,`shared_with_type`),
  CONSTRAINT `fk_drive_share_item` FOREIGN KEY (`item_id`) REFERENCES `drive_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `drive_itens`
--

DROP TABLE IF EXISTS `drive_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `drive_itens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` int unsigned NOT NULL,
  `owner_type` enum('student','teacher') COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('folder','file') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho relativo no storage para arquivos',
  `file_size` bigint unsigned DEFAULT NULL,
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`owner_id`,`owner_type`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_owner_parent` (`owner_id`,`owner_type`,`parent_id`),
  CONSTRAINT `fk_drive_parent` FOREIGN KEY (`parent_id`) REFERENCES `drive_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `educalabs_messages`
--

DROP TABLE IF EXISTS `educalabs_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `educalabs_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_id` (`project_id`),
  CONSTRAINT `fk_educalabs_messages_project` FOREIGN KEY (`project_id`) REFERENCES `educalabs_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `educalabs_projects`
--

DROP TABLE IF EXISTS `educalabs_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `educalabs_projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `share_id` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `html` longtext COLLATE utf8mb4_unicode_ci,
  `css` longtext COLLATE utf8mb4_unicode_ci,
  `js` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_public_id` (`public_id`),
  UNIQUE KEY `uniq_share_id` (`share_id`),
  KEY `idx_owner_id` (`owner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `educalabs_tokens`
--

DROP TABLE IF EXISTS `educalabs_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `educalabs_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enem_alternativas`
--

DROP TABLE IF EXISTS `enem_alternativas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enem_alternativas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question_id` int NOT NULL,
  `letter` char(1) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `text` text COLLATE utf8mb3_unicode_ci,
  `file` text COLLATE utf8mb3_unicode_ci,
  `is_correct` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `enem_alternativas_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `enem_questoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14813 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enem_disciplinas`
--

DROP TABLE IF EXISTS `enem_disciplinas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enem_disciplinas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `label` varchar(150) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `value` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `enem_disciplinas_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `enem_provas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enem_provas`
--

DROP TABLE IF EXISTS `enem_provas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enem_provas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `year` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enem_questoes`
--

DROP TABLE IF EXISTS `enem_questoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enem_questoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `discipline` varchar(150) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `language` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `question_index` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `context` longtext COLLATE utf8mb3_unicode_ci,
  `correct_alternative` char(1) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `alternatives_introduction` text COLLATE utf8mb3_unicode_ci,
  `year` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `enem_questoes_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `enem_provas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2965 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enem_questoes_arquivos`
--

DROP TABLE IF EXISTS `enem_questoes_arquivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enem_questoes_arquivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question_id` int NOT NULL,
  `file_url` text COLLATE utf8mb3_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `enem_questoes_arquivos_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `enem_questoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1218 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enem_questoes_vinculo`
--

DROP TABLE IF EXISTS `enem_questoes_vinculo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enem_questoes_vinculo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ano` int NOT NULL,
  `indice` int NOT NULL,
  `disciplina` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `idioma` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `contexto` longtext COLLATE utf8mb3_unicode_ci,
  `enunciado` longtext COLLATE utf8mb3_unicode_ci,
  `alternativas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `correta` char(1) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `imagem` text COLLATE utf8mb3_unicode_ci,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ano_indice` (`ano`,`indice`),
  CONSTRAINT `enem_questoes_vinculo_chk_1` CHECK (json_valid(`alternativas`))
) ENGINE=InnoDB AUTO_INCREMENT=2858 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estatisticas_dama`
--

DROP TABLE IF EXISTS `estatisticas_dama`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estatisticas_dama` (
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
  `nivel_preferido` enum('facil','medio','dificil') COLLATE utf8mb3_unicode_ci DEFAULT 'facil',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno` (`aluno_id`),
  CONSTRAINT `estatisticas_dama_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exercicios`
--

DROP TABLE IF EXISTS `exercicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jornada` (`jornada_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_gerado_ia` (`gerado_ia`),
  KEY `idx_aprovado` (`aprovado`),
  KEY `lista_id` (`lista_id`),
  CONSTRAINT `exercicios_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exercicios_estatisticas_alunos`
--

DROP TABLE IF EXISTS `exercicios_estatisticas_alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exercicios_estatisticas_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=512 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exercicios_estatisticas_turmas`
--

DROP TABLE IF EXISTS `exercicios_estatisticas_turmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exercicios_estatisticas_turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `turma_id` int NOT NULL,
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exercicios_execucoes`
--

DROP TABLE IF EXISTS `exercicios_execucoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exercicios_execucoes` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_execucao_ativa` (`aluno_id`,`lista_id`,`status`),
  KEY `idx_execucao_aluno` (`aluno_id`),
  KEY `idx_execucao_lista` (`lista_id`),
  CONSTRAINT `exercicios_execucoes_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_execucoes_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exercicios_historico`
--

DROP TABLE IF EXISTS `exercicios_historico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exercicios_historico` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lista_id` (`lista_id`),
  KEY `sessao_id` (`sessao_id`),
  KEY `idx_aluno_lista_data` (`aluno_id`,`lista_id`,`data_execucao`),
  KEY `idx_aluno_data` (`aluno_id`,`data_execucao`),
  CONSTRAINT `exercicios_historico_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_historico_ibfk_2` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercicios_historico_ibfk_3` FOREIGN KEY (`sessao_id`) REFERENCES `exercicios_sessoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2941 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exercicios_respostas`
--

DROP TABLE IF EXISTS `exercicios_respostas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exercicios_respostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `exercicio_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `resposta` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=13437 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exercicios_sessoes`
--

DROP TABLE IF EXISTS `exercicios_sessoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exercicios_sessoes` (
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
) ENGINE=InnoDB AUTO_INCREMENT=2957 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `financeiro_valores_mensais`
--

DROP TABLE IF EXISTS `financeiro_valores_mensais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financeiro_valores_mensais` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mes_referencia` (`mes_referencia`),
  KEY `idx_mes_referencia` (`mes_referencia`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status_valores_cobrados_mensais` (`status`),
  KEY `idx_data_vencimento_valores_cobrados_mensais` (`data_vencimento`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flashcard_explicacoes`
--

DROP TABLE IF EXISTS `flashcard_explicacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flashcard_explicacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `deck_id` int NOT NULL,
  `card_id` int NOT NULL,
  `explicacao` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Texto da explicação gerado pela IA',
  `origem` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ia' COMMENT 'Origem da explicação: ia',
  `numero_tentativa` int NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno_deck_card` (`aluno_id`,`deck_id`,`card_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flashcards_baralhos`
--

DROP TABLE IF EXISTS `flashcards_baralhos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flashcards_baralhos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `topic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_flashcard_decks_aluno` (`aluno_id`)
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flashcards_cartas`
--

DROP TABLE IF EXISTS `flashcards_cartas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flashcards_cartas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `deck_id` int NOT NULL,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_flashcards_deck` (`deck_id`),
  CONSTRAINT `flashcards_cartas_ibfk_1` FOREIGN KEY (`deck_id`) REFERENCES `flashcards_baralhos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=705 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flashcards_modelos`
--

DROP TABLE IF EXISTS `flashcards_modelos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flashcards_modelos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `topic_normalized` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_templates_lookup` (`topic_normalized`,`grade`,`quantity`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flashcards_modelos_cartas`
--

DROP TABLE IF EXISTS `flashcards_modelos_cartas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flashcards_modelos_cartas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_tc_template` (`template_id`),
  CONSTRAINT `flashcards_modelos_cartas_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `flashcards_modelos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=622 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forum_anexos`
--

DROP TABLE IF EXISTS `forum_anexos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `topic_id` int DEFAULT NULL,
  `reply_id` int DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb3_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'application/octet-stream',
  `file_size` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_forum_att_topic` (`topic_id`),
  KEY `idx_forum_att_reply` (`reply_id`),
  CONSTRAINT `forum_anexos_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_anexos_ibfk_2` FOREIGN KEY (`reply_id`) REFERENCES `forum_respostas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forum_denuncias`
--

DROP TABLE IF EXISTS `forum_denuncias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_denuncias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `topic_id` int DEFAULT NULL,
  `reply_id` int DEFAULT NULL,
  `reporter_id` int NOT NULL,
  `reporter_role` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'student',
  `reason` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_forum_reports_status` (`status`),
  KEY `idx_forum_reports_topic` (`topic_id`),
  KEY `idx_forum_reports_reply` (`reply_id`),
  CONSTRAINT `forum_denuncias_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_denuncias_ibfk_2` FOREIGN KEY (`reply_id`) REFERENCES `forum_respostas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forum_moderacao_alertas`
--

DROP TABLE IF EXISTS `forum_moderacao_alertas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_moderacao_alertas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'topic ou reply',
  `topic_id` int DEFAULT NULL,
  `reply_id` int DEFAULT NULL,
  `author_id` int NOT NULL,
  `author_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `content_preview` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo_ia` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending, visto',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forum_respostas`
--

DROP TABLE IF EXISTS `forum_respostas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_respostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `topic_id` int NOT NULL,
  `author_id` int NOT NULL,
  `author_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_best_answer` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_forum_replies_topic` (`topic_id`),
  KEY `idx_forum_replies_author` (`author_id`,`author_role`),
  KEY `idx_forum_replies_best` (`topic_id`,`is_best_answer`),
  CONSTRAINT `forum_respostas_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forum_topicos`
--

DROP TABLE IF EXISTS `forum_topicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_topicos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_id` int NOT NULL,
  `author_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forum_topicos_turmas`
--

DROP TABLE IF EXISTS `forum_topicos_turmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_topicos_turmas` (
  `topic_id` int NOT NULL,
  `turma_id` int NOT NULL,
  PRIMARY KEY (`topic_id`,`turma_id`),
  KEY `idx_turma` (`turma_id`),
  CONSTRAINT `forum_topicos_turmas_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forum_usuarios_reputacao`
--

DROP TABLE IF EXISTS `forum_usuarios_reputacao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_usuarios_reputacao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `user_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `points` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_forum_rep_user` (`user_id`,`user_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forum_votos`
--

DROP TABLE IF EXISTS `forum_votos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_votos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reply_id` int NOT NULL,
  `voter_id` int NOT NULL,
  `voter_role` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'student',
  `vote_type` varchar(10) COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_forum_votes_reply_voter` (`reply_id`,`voter_id`,`voter_role`),
  KEY `idx_forum_votes_reply` (`reply_id`),
  CONSTRAINT `forum_votos_ibfk_1` FOREIGN KEY (`reply_id`) REFERENCES `forum_respostas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grade_horaria`
--

DROP TABLE IF EXISTS `grade_horaria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grade_horaria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dia_semana` tinyint NOT NULL COMMENT '1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado, 7=Domingo',
  `horario_de` time NOT NULL,
  `horario_ate` time NOT NULL,
  `turma_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `periodo` enum('manha','tarde') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manha',
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jogos_acoes`
--

DROP TABLE IF EXISTS `jogos_acoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jogos_acoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `partida_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` datetime NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partida_id` (`partida_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `fk_game_actions_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_game_actions_partida` FOREIGN KEY (`partida_id`) REFERENCES `jogos_milhao_partidas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4700 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jogos_milhao_partidas`
--

DROP TABLE IF EXISTS `jogos_milhao_partidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jogos_milhao_partidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `pontuacao_atual` decimal(10,2) DEFAULT '0.00',
  `pergunta_atual` int DEFAULT '1',
  `ajudas_usadas` json DEFAULT (_utf8mb4'{"plateia": false, "universitarios": false, "pular": false}'),
  `status` enum('em_andamento','finalizada','abandonada') COLLATE utf8mb3_unicode_ci DEFAULT 'em_andamento',
  `data_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` timestamp NULL DEFAULT NULL,
  `premio_final` decimal(10,2) DEFAULT '0.00',
  `perguntas_usadas` text COLLATE utf8mb3_unicode_ci,
  `last_activity` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_partidas_aluno` (`aluno_id`),
  KEY `idx_partidas_status` (`status`),
  KEY `idx_last_activity_status` (`last_activity`,`status`),
  CONSTRAINT `jogos_milhao_partidas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=975 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jogos_milhao_perguntas`
--

DROP TABLE IF EXISTS `jogos_milhao_perguntas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jogos_milhao_perguntas` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_perguntas_nivel` (`nivel_dificuldade`),
  KEY `idx_perguntas_tema` (`tema`),
  KEY `idx_perguntas_ativa` (`ativa`)
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jogos_milhao_respostas`
--

DROP TABLE IF EXISTS `jogos_milhao_respostas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jogos_milhao_respostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `partida_id` int NOT NULL,
  `pergunta_id` int NOT NULL,
  `resposta_escolhida` enum('A','B','C','D') COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `resposta_correta` enum('A','B','C','D') COLLATE utf8mb3_unicode_ci NOT NULL,
  `acertou` tinyint(1) NOT NULL,
  `ajuda_usada` enum('plateia','universitarios','pular','nenhuma') COLLATE utf8mb3_unicode_ci DEFAULT 'nenhuma',
  `tempo_resposta` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pergunta_id` (`pergunta_id`),
  KEY `idx_respostas_partida` (`partida_id`),
  CONSTRAINT `jogos_milhao_respostas_ibfk_1` FOREIGN KEY (`partida_id`) REFERENCES `jogos_milhao_partidas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jogos_milhao_respostas_ibfk_2` FOREIGN KEY (`pergunta_id`) REFERENCES `jogos_milhao_perguntas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7020 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jogos_sessoes`
--

DROP TABLE IF EXISTS `jogos_sessoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jogos_sessoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `partida_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jogos_tokens_externos`
--

DROP TABLE IF EXISTS `jogos_tokens_externos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jogos_tokens_externos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2681 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas`
--

DROP TABLE IF EXISTS `jornadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `materia_id` int DEFAULT NULL,
  `plano_aula_id` int DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT 'ativa',
  `titulo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb3_unicode_ci,
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
  CONSTRAINT `jornadas_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_chk_1` CHECK (json_valid(`estrutura`))
) ENGINE=InnoDB AUTO_INCREMENT=209 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_aulas`
--

DROP TABLE IF EXISTS `jornadas_aulas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_aulas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jornada_id` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `nome_aula` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resumo_oficial` text COLLATE utf8mb4_unicode_ci,
  `pontos_principais` text COLLATE utf8mb4_unicode_ci,
  `conteudos_adicionais` text COLLATE utf8mb4_unicode_ci,
  `status` enum('ativa','pausada','finalizada') COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `ordem` (`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_blocos_conteudo`
--

DROP TABLE IF EXISTS `jornadas_blocos_conteudo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_blocos_conteudo` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `tipo_bloco_id` (`tipo_bloco_id`),
  KEY `ordem` (`ordem`),
  KEY `idx_jornadas_blocos_ordem` (`jornada_id`,`ordem`),
  CONSTRAINT `jornadas_blocos_conteudo_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_blocos_conteudo_ibfk_2` FOREIGN KEY (`tipo_bloco_id`) REFERENCES `jornadas_tipos_blocos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_blocos_conteudo_chk_1` CHECK (json_valid(`configuracoes`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_duvidas`
--

DROP TABLE IF EXISTS `jornadas_duvidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_duvidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `aula_id` int NOT NULL,
  `duvida` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `resposta` text COLLATE utf8mb4_unicode_ci,
  `respondido_por` int DEFAULT NULL,
  `respondido_em` timestamp NULL DEFAULT NULL,
  `status` enum('pendente','respondida','arquivada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `aula_id` (`aula_id`),
  KEY `respondido_por` (`respondido_por`),
  CONSTRAINT `jornadas_duvidas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_duvidas_ibfk_2` FOREIGN KEY (`aula_id`) REFERENCES `jornadas_aulas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_duvidas_ibfk_3` FOREIGN KEY (`respondido_por`) REFERENCES `professores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_exercicios`
--

DROP TABLE IF EXISTS `jornadas_exercicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `aula_id` (`aula_id`),
  KEY `aprovado_por` (`aprovado_por`),
  CONSTRAINT `jornadas_exercicios_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_exercicios_ibfk_2` FOREIGN KEY (`aula_id`) REFERENCES `jornadas_aulas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jornadas_exercicios_ibfk_3` FOREIGN KEY (`aprovado_por`) REFERENCES `professores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_materias`
--

DROP TABLE IF EXISTS `jornadas_materias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_materias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#3B82F6',
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'book',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_mensagens`
--

DROP TABLE IF EXISTS `jornadas_mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_mensagens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jornada_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `remetente_tipo` enum('aluno','professor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `remetente_id` int NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_mensagens_anexos`
--

DROP TABLE IF EXISTS `jornadas_mensagens_anexos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_mensagens_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mensagem_id` int NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho_arquivo` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mensagem_id` (`mensagem_id`),
  CONSTRAINT `jornadas_mensagens_anexos_ibfk_1` FOREIGN KEY (`mensagem_id`) REFERENCES `jornadas_mensagens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_modulos`
--

DROP TABLE IF EXISTS `jornadas_modulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_modulos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jornada_id` int NOT NULL,
  `aula_id` int DEFAULT NULL,
  `tipo_modulo` enum('resumo_aluno','resumo_professor','duvidas_ia','redacao','exercicios','sugestoes','video','dica_professor','conteudo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `ordem` int NOT NULL DEFAULT '1',
  `obrigatorio` tinyint(1) DEFAULT '0',
  `status` enum('ativo','inativo') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jornada_id` (`jornada_id`),
  KEY `aula_id` (`aula_id`),
  KEY `tipo_modulo` (`tipo_modulo`),
  CONSTRAINT `jornadas_modulos_ibfk_1` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_modulos_ibfk_2` FOREIGN KEY (`aula_id`) REFERENCES `jornadas_aulas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=360 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_modulos_documentos`
--

DROP TABLE IF EXISTS `jornadas_modulos_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_modulos_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_tamanho` int DEFAULT NULL,
  `tipo_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `status` enum('ativo','inativo') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `modulo_id` (`modulo_id`),
  CONSTRAINT `jornadas_modulos_documentos_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_modulos_exercicios`
--

DROP TABLE IF EXISTS `jornadas_modulos_exercicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_modulos_exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int NOT NULL,
  `tipo` enum('alternativas','verdadeiro_falso','dissertativa','preencher_lacuna') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alternativas',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enunciado` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `questoes_json` longtext COLLATE utf8mb4_unicode_ci,
  `resposta_correta` text COLLATE utf8mb4_unicode_ci,
  `gabarito` text COLLATE utf8mb4_unicode_ci,
  `pontuacao` decimal(5,2) DEFAULT '1.00',
  `ordem` int NOT NULL DEFAULT '1',
  `gerado_ia` tinyint(1) DEFAULT '0',
  `status` enum('rascunho','publicado','arquivado') COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `imagem_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL da imagem do enunciado (upload ou colagem)',
  `nivel_dificuldade` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'facil, medio, dificil',
  PRIMARY KEY (`id`),
  KEY `modulo_id` (`modulo_id`),
  KEY `tipo` (`tipo`),
  CONSTRAINT `jornadas_modulos_exercicios_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=820 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_modulos_textos`
--

DROP TABLE IF EXISTS `jornadas_modulos_textos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_modulos_textos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text COLLATE utf8mb4_unicode_ci,
  `ordem` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_modulo_id` (`modulo_id`),
  CONSTRAINT `fk_jmt_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_modulos_videos`
--

DROP TABLE IF EXISTS `jornadas_modulos_videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_modulos_videos` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `modulo_id` (`modulo_id`),
  KEY `tipo` (`tipo`),
  CONSTRAINT `jornadas_modulos_videos_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_progresso_alunos`
--

DROP TABLE IF EXISTS `jornadas_progresso_alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_progresso_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `jornada_id` int NOT NULL,
  `aula_id` int DEFAULT NULL,
  `exercicio_id` int DEFAULT NULL,
  `modulo_id` int DEFAULT NULL,
  `exercicio_modulo_id` int DEFAULT NULL,
  `atividade_tipo` enum('aula','exercicio','resumo','duvida','modulo','exercicio_modulo','jornada_concluida','visualizacao') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tempo_gasto` int DEFAULT '0',
  `status` enum('iniciado','em_andamento','concluido','pausado','visualizado','nao_visualizado') COLLATE utf8mb4_unicode_ci DEFAULT 'iniciado',
  `pontuacao` decimal(5,2) DEFAULT '0.00',
  `resposta` text COLLATE utf8mb4_unicode_ci COMMENT 'Resposta do aluno (pode ser JSON)',
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
  CONSTRAINT `jornadas_progresso_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_progresso_alunos_ibfk_2` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_progresso_alunos_ibfk_3` FOREIGN KEY (`aula_id`) REFERENCES `jornadas_aulas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jornadas_progresso_alunos_ibfk_4` FOREIGN KEY (`exercicio_id`) REFERENCES `jornadas_exercicios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jornadas_progresso_alunos_ibfk_exercicio_modulo` FOREIGN KEY (`exercicio_modulo_id`) REFERENCES `jornadas_modulos_exercicios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_progresso_alunos_ibfk_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43630 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_progresso_blocos`
--

DROP TABLE IF EXISTS `jornadas_progresso_blocos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_progresso_blocos` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  CONSTRAINT `jornadas_progresso_blocos_ibfk_3` FOREIGN KEY (`bloco_id`) REFERENCES `jornadas_blocos_conteudo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_progresso_blocos_chk_1` CHECK (json_valid(`dados_progresso`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_redacoes`
--

DROP TABLE IF EXISTS `jornadas_redacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_redacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_redacoes_alunos`
--

DROP TABLE IF EXISTS `jornadas_redacoes_alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_redacoes_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `usar_media_notas` tinyint(1) DEFAULT '0' COMMENT 'Usar média das notas das competências',
  `nota_media` decimal(5,2) DEFAULT NULL COMMENT 'Média das notas das competências',
  `nota_final_utilizada` decimal(5,2) DEFAULT NULL COMMENT 'Nota final que será utilizada',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_redacao_jornada` (`redacao_id`,`jornada_redacao_id`),
  KEY `jornada_redacao_id` (`jornada_redacao_id`),
  KEY `redacao_id` (`redacao_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `status` (`status`),
  CONSTRAINT `jornadas_redacoes_alunos_ibfk_1` FOREIGN KEY (`jornada_redacao_id`) REFERENCES `jornadas_redacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_redacoes_alunos_ibfk_2` FOREIGN KEY (`redacao_id`) REFERENCES `redacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_redacoes_alunos_ibfk_3` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_relatorios`
--

DROP TABLE IF EXISTS `jornadas_relatorios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_relatorios` (
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
  `relatorio_detalhado` longtext COLLATE utf8mb4_unicode_ci,
  `gerado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `jornada_id` (`jornada_id`),
  CONSTRAINT `jornadas_relatorios_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_relatorios_ibfk_2` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_resumos_alunos`
--

DROP TABLE IF EXISTS `jornadas_resumos_alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_resumos_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `aula_id` int DEFAULT NULL,
  `modulo_id` int DEFAULT NULL,
  `jornada_id` int DEFAULT NULL,
  `resumo_aluno` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `analise_ia` text COLLATE utf8mb4_unicode_ci,
  `lacunas_identificadas` text COLLATE utf8mb4_unicode_ci,
  `explicacoes_complementares` text COLLATE utf8mb4_unicode_ci,
  `pontuacao` decimal(5,2) DEFAULT '0.00',
  `status` enum('em_analise','analisado','revisado') COLLATE utf8mb4_unicode_ci DEFAULT 'em_analise',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `observacoes_professor` text COLLATE utf8mb4_unicode_ci,
  `nota` decimal(4,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `aula_id` (`aula_id`),
  KEY `modulo_id` (`modulo_id`),
  KEY `jornada_id` (`jornada_id`),
  CONSTRAINT `jornadas_resumos_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_resumos_alunos_ibfk_2` FOREIGN KEY (`aula_id`) REFERENCES `jornadas_aulas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_resumos_alunos_ibfk_jornada` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jornadas_resumos_alunos_ibfk_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `jornadas_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=677 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_tempo_alunos`
--

DROP TABLE IF EXISTS `jornadas_tempo_alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_tempo_alunos` (
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
) ENGINE=InnoDB AUTO_INCREMENT=7501 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jornadas_tipos_blocos`
--

DROP TABLE IF EXISTS `jornadas_tipos_blocos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jornadas_tipos_blocos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cor` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem_padrao` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `listas_exercicios`
--

DROP TABLE IF EXISTS `listas_exercicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `listas_exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `serie` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `nivel_dificuldade` enum('Fácil','Médio','Difícil') COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb3_unicode_ci,
  `criado_por` int NOT NULL,
  `tipo_usuario` enum('admin','professor') COLLATE utf8mb3_unicode_ci NOT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `criado_por` (`criado_por`),
  KEY `idx_lista_exercicios_materia` (`materia`),
  KEY `idx_lista_exercicios_serie` (`serie`),
  KEY `idx_lista_exercicios_dificuldade` (`nivel_dificuldade`),
  CONSTRAINT `listas_exercicios_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `listas_exercicios_personalizadas`
--

DROP TABLE IF EXISTS `listas_exercicios_personalizadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `listas_exercicios_personalizadas` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `criado_por` (`criado_por`),
  KEY `turma_id` (`turma_id`),
  CONSTRAINT `listas_exercicios_personalizadas_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listas_exercicios_personalizadas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `listas_exercicios_personalizadas_chk_1` CHECK (json_valid(`questoes_ids`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `listas_personalizadas_exercicios`
--

DROP TABLE IF EXISTS `listas_personalizadas_exercicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `listas_personalizadas_exercicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tema` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ex: Reforma Protestante',
  `materia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade_exercicios` int NOT NULL,
  `niveis_selecionados` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'fácil,médio,difícil ou combinações',
  `status` enum('gerando','concluido','erro') COLLATE utf8mb4_unicode_ci DEFAULT 'gerando',
  `mensagem_erro` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_listas_exercicios_personalizados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1229 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `listas_personalizadas_respostas`
--

DROP TABLE IF EXISTS `listas_personalizadas_respostas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `listas_personalizadas_respostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sessao_id` int NOT NULL,
  `questao_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `resposta` enum('A','B','C','D','E') COLLATE utf8mb4_unicode_ci NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=9866 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `listas_personalizadas_sessoes`
--

DROP TABLE IF EXISTS `listas_personalizadas_sessoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `listas_personalizadas_sessoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `lista_id` int NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT NULL,
  `status` enum('em_andamento','finalizado','abandonado') COLLATE utf8mb4_unicode_ci DEFAULT 'em_andamento',
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `lista_id` (`lista_id`),
  KEY `started_at` (`started_at`),
  CONSTRAINT `fk_sessoes_exercicios_personalizados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sessoes_exercicios_personalizados_lista` FOREIGN KEY (`lista_id`) REFERENCES `listas_personalizadas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1199 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs_auditoria`
--

DROP TABLE IF EXISTS `logs_auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs_auditoria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `user_role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_accessed` text COLLATE utf8mb4_unicode_ci,
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_role` (`user_role`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip_address` (`ip_address`),
  CONSTRAINT `logs_auditoria_chk_1` CHECK (json_valid(`request_payload`))
) ENGINE=InnoDB AUTO_INCREMENT=104629 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs_senhas`
--

DROP TABLE IF EXISTS `logs_senhas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs_senhas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `alterado_por` int NOT NULL,
  `motivo` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `alterado_por` (`alterado_por`),
  CONSTRAINT `logs_senhas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `logs_senhas_ibfk_2` FOREIGN KEY (`alterado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs_uso_llm`
--

DROP TABLE IF EXISTS `logs_uso_llm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs_uso_llm` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Modelo OpenAI (ex: gpt-4o, gpt-4o-mini)',
  `prompt_tokens` int unsigned NOT NULL DEFAULT '0',
  `completion_tokens` int unsigned NOT NULL DEFAULT '0',
  `total_tokens` int unsigned NOT NULL DEFAULT '0',
  `cost_usd` decimal(12,6) NOT NULL DEFAULT '0.000000',
  `usage_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general' COMMENT 'ex: exercicios, chat, correcao_redacao, prova, gerar_tema, chat_completion',
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api' COMMENT 'api = chamada real; backfill = importado',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_usage_type` (`usage_type`),
  KEY `idx_model` (`model`)
) ENGINE=InnoDB AUTO_INCREMENT=3591 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `materias`
--

DROP TABLE IF EXISTS `materias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `materias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations_executadas`
--

DROP TABLE IF EXISTS `migrations_executadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations_executadas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `escola_database_config_id` int NOT NULL,
  `migration_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `executada_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `executado_por` int DEFAULT NULL,
  `status` enum('sucesso','erro') COLLATE utf8mb4_unicode_ci DEFAULT 'sucesso',
  `mensagem_erro` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_migration_escola` (`escola_database_config_id`,`migration_file`),
  KEY `idx_escola` (`escola_database_config_id`),
  KEY `idx_migration_file` (`migration_file`),
  KEY `idx_executada_em` (`executada_em`),
  CONSTRAINT `migrations_executadas_ibfk_1` FOREIGN KEY (`escola_database_config_id`) REFERENCES `config_escolas_database` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `minicursos`
--

DROP TABLE IF EXISTS `minicursos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `minicursos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `imagem_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagem_caminho` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho do upload',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` smallint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativo_ordem` (`ativo`,`ordem`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `minicursos_arquivos`
--

DROP TABLE IF EXISTS `minicursos_arquivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `minicursos_arquivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `minicurso_id` int NOT NULL,
  `tipo` enum('upload','link') COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` smallint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_minicurso` (`minicurso_id`),
  CONSTRAINT `minicursos_arquivos_ibfk_1` FOREIGN KEY (`minicurso_id`) REFERENCES `minicursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `minicursos_aulas`
--

DROP TABLE IF EXISTS `minicursos_aulas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `minicursos_aulas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('video','slides','pdf','link','texto') COLLATE utf8mb4_unicode_ci NOT NULL,
  `url_ou_caminho` text COLLATE utf8mb4_unicode_ci COMMENT 'URL ou caminho; vazio se tipo=texto',
  `link_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Texto do botão para tipo link',
  `conteudo_html` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Conteúdo HTML (tipo texto)',
  `duracao_minutos` int unsigned DEFAULT NULL,
  `ordem` smallint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_modulo` (`modulo_id`),
  CONSTRAINT `minicursos_aulas_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `minicursos_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `minicursos_modulos`
--

DROP TABLE IF EXISTS `minicursos_modulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `minicursos_modulos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `minicurso_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `ordem` smallint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_minicurso` (`minicurso_id`),
  CONSTRAINT `minicursos_modulos_ibfk_1` FOREIGN KEY (`minicurso_id`) REFERENCES `minicursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `minicursos_progresso`
--

DROP TABLE IF EXISTS `minicursos_progresso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `minicursos_progresso` (
  `aluno_id` int NOT NULL,
  `minicurso_id` int NOT NULL,
  `aulas_vistas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Array de aula_id já visualizadas',
  `concluido_em` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`aluno_id`,`minicurso_id`),
  KEY `idx_minicurso` (`minicurso_id`),
  CONSTRAINT `minicursos_progresso_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `minicursos_progresso_ibfk_2` FOREIGN KEY (`minicurso_id`) REFERENCES `minicursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `minicursos_progresso_chk_1` CHECK (json_valid(`aulas_vistas`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modulos_arquivos`
--

DROP TABLE IF EXISTS `modulos_arquivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modulos_arquivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `turma_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `aluno_id` int DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_turma` (`turma_id`),
  KEY `idx_materia` (`materia_id`),
  KEY `idx_professor` (`professor_id`),
  KEY `idx_modulos_arquivos_aluno_id` (`aluno_id`),
  CONSTRAINT `modulos_arquivos_ibfk_1` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `modulos_arquivos_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `modulos_arquivos_ibfk_3` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modulos_arquivos_anexos`
--

DROP TABLE IF EXISTS `modulos_arquivos_anexos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modulos_arquivos_anexos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modulo_arquivo_id` int NOT NULL,
  `caminho` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Caminho relativo no servidor (ex: public/uploads/arquivos/xxx.pdf)',
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extensao` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanho` int unsigned DEFAULT '0',
  `ordem` smallint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_modulo_arquivo` (`modulo_arquivo_id`),
  CONSTRAINT `modulos_arquivos_anexos_ibfk_1` FOREIGN KEY (`modulo_arquivo_id`) REFERENCES `modulos_arquivos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modulos_arquivos_turmas`
--

DROP TABLE IF EXISTS `modulos_arquivos_turmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modulos_arquivos_turmas` (
  `modulo_arquivo_id` int NOT NULL,
  `turma_id` int NOT NULL,
  PRIMARY KEY (`modulo_arquivo_id`,`turma_id`),
  KEY `idx_turma` (`turma_id`),
  CONSTRAINT `modulos_arquivos_turmas_ibfk_1` FOREIGN KEY (`modulo_arquivo_id`) REFERENCES `modulos_arquivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `modulos_arquivos_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mural_recados`
--

DROP TABLE IF EXISTS `mural_recados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mural_recados` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` text COLLATE utf8mb4_unicode_ci,
  `autor_tipo` enum('professor','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'professor',
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
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mural_recados_anexos`
--

DROP TABLE IF EXISTS `mural_recados_anexos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mural_recados_anexos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mural_recado_id` int unsigned NOT NULL,
  `arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Caminho relativo no servidor',
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME type',
  `tamanho` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mural_anexos_recado` (`mural_recado_id`),
  CONSTRAINT `fk_mural_anexos_recado` FOREIGN KEY (`mural_recado_id`) REFERENCES `mural_recados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mural_recados_turmas`
--

DROP TABLE IF EXISTS `mural_recados_turmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mural_recados_turmas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mural_recado_id` int unsigned NOT NULL,
  `turma_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mural_turma` (`mural_recado_id`,`turma_id`),
  KEY `idx_mural_recados_turmas_mural` (`mural_recado_id`),
  KEY `idx_mural_recados_turmas_turma` (`turma_id`),
  CONSTRAINT `fk_mural_turmas_recado` FOREIGN KEY (`mural_recado_id`) REFERENCES `mural_recados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mural_recados_vistos`
--

DROP TABLE IF EXISTS `mural_recados_vistos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mural_recados_vistos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mural_recado_id` int unsigned NOT NULL,
  `aluno_id` int unsigned NOT NULL,
  `visto_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mural_aluno` (`mural_recado_id`,`aluno_id`),
  KEY `idx_mural_vistos_recado` (`mural_recado_id`),
  KEY `idx_mural_vistos_aluno` (`aluno_id`),
  CONSTRAINT `fk_mural_vistos_recado` FOREIGN KEY (`mural_recado_id`) REFERENCES `mural_recados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1361 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notes_tokens`
--

DROP TABLE IF EXISTS `notes_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=310 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `noticias`
--

DROP TABLE IF EXISTS `noticias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `noticias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `imagem` text COLLATE utf8mb4_unicode_ci,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fonte` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_publicacao` datetime DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_noticias_link` (`link`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificacoes`
--

DROP TABLE IF EXISTS `notificacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `enviado_por` (`enviado_por`),
  KEY `tipo_enviador` (`tipo_enviador`),
  KEY `perfil_enviador` (`perfil_enviador`),
  KEY `prioridade` (`prioridade`),
  KEY `ativo` (`ativo`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificacoes_api`
--

DROP TABLE IF EXISTS `notificacoes_api`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacoes_api` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci,
  `imagem` text COLLATE utf8mb4_unicode_ci,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificacoes_configuracoes`
--

DROP TABLE IF EXISTS `notificacoes_configuracoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacoes_configuracoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `tipo_usuario` enum('admin','professor','aluno','pai') COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificacoes_destinatarios`
--

DROP TABLE IF EXISTS `notificacoes_destinatarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacoes_destinatarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notificacao_id` int NOT NULL,
  `tipo_destinatario` enum('todos','usuarios','professores','alunos','pais','turma','todos_alunos','todos_professores','todos_admins','todos_pais') COLLATE utf8mb4_unicode_ci NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=3120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificacoes_historico`
--

DROP TABLE IF EXISTS `notificacoes_historico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacoes_historico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notificacao_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `tipo_usuario` enum('admin','professor','aluno','pai') COLLATE utf8mb4_unicode_ci NOT NULL,
  `acao` enum('enviada','visualizada','lida','atualizada','excluida') COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalhes` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notificacao_id` (`notificacao_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `tipo_usuario` (`tipo_usuario`),
  KEY `acao` (`acao`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `notificacoes_historico_ibfk_1` FOREIGN KEY (`notificacao_id`) REFERENCES `notificacoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificacoes_push`
--

DROP TABLE IF EXISTS `notificacoes_push`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacoes_push` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL para redirecionar ao clicar',
  `tipo_destino` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'todos|pais|alunos|professores|turma|usuario',
  `destino_id` int unsigned DEFAULT NULL COMMENT 'ID da turma ou usuario conforme tipo_destino',
  `criado_por` int unsigned NOT NULL COMMENT 'usuario_id do admin',
  `onesignal_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID do envio no OneSignal',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_push_notif_created` (`created_at`),
  KEY `idx_push_notif_tipo` (`tipo_destino`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificacoes_push_envios`
--

DROP TABLE IF EXISTS `notificacoes_push_envios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacoes_push_envios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `notificacao_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL COMMENT 'usuarios.id do destinatário',
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'pai|aluno|professor|admin_escola',
  `tracking_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Token para API de tracking (ex: visualizado/clicado)',
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partidas_dama`
--

DROP TABLE IF EXISTS `partidas_dama`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partidas_dama` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno_status` (`aluno_id`,`status`),
  KEY `idx_data_inicio` (`data_inicio`),
  CONSTRAINT `partidas_dama_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `planos_aula`
--

DROP TABLE IF EXISTS `planos_aula`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `planos_aula` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `data_aula` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Datas da aula em formato JSON (array de datas)',
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
) ENGINE=InnoDB AUTO_INCREMENT=1161 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pontuacao_alunos`
--

DROP TABLE IF EXISTS `pontuacao_alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pontuacao_alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `total_partidas` int DEFAULT '0',
  `partidas_vencidas` int DEFAULT '0',
  `maior_premio` decimal(10,2) DEFAULT '0.00',
  `total_premio` decimal(10,2) DEFAULT '0.00',
  `nivel_atual` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT 'Iniciante',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno` (`aluno_id`),
  CONSTRAINT `pontuacao_alunos_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `professores`
--

DROP TABLE IF EXISTS `professores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(500) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'URL da foto de perfil do professor',
  `senha_hash` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `codigo_prof` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Código do professor - login',
  `materias` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Lista de matérias que leciona',
  `turmas` longtext COLLATE utf8mb3_unicode_ci,
  `ativo` tinyint(1) DEFAULT '1',
  `pagante` tinyint(1) DEFAULT '1' COMMENT 'Indica se a escola paga por este professor',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_codigo_prof` (`codigo_prof`),
  KEY `idx_ativo` (`ativo`),
  CONSTRAINT `professores_chk_1` CHECK (json_valid(`materias`))
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `professores_ia_agentes`
--

DROP TABLE IF EXISTS `professores_ia_agentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professores_ia_agentes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `instrucoes_sistema` text COLLATE utf8mb4_unicode_ci COMMENT 'Instruções personalizadas para o agente',
  `modelo_ia` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'gpt-4o-mini' COMMENT 'Modelo OpenAI a ser usado',
  `temperatura` decimal(3,2) DEFAULT '0.70',
  `max_tokens` int DEFAULT '2000',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_professor` (`professor_id`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `professores_ia_conversas`
--

DROP TABLE IF EXISTS `professores_ia_conversas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professores_ia_conversas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `agente_id` int unsigned NOT NULL,
  `professor_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Título gerado automaticamente da primeira pergunta',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_professor` (`professor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `professores_ia_documentos`
--

DROP TABLE IF EXISTS `professores_ia_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professores_ia_documentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `agente_id` int unsigned NOT NULL,
  `professor_id` int NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho_bytes` bigint unsigned DEFAULT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `texto_extraido` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Texto completo extraído do documento',
  `status_processamento` enum('pendente','processando','concluido','erro') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `erro_processamento` text COLLATE utf8mb4_unicode_ci,
  `total_chunks` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_professor` (`professor_id`),
  KEY `idx_status` (`status_processamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `professores_ia_documentos_chunks`
--

DROP TABLE IF EXISTS `professores_ia_documentos_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professores_ia_documentos_chunks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `documento_id` int unsigned NOT NULL,
  `agente_id` int unsigned NOT NULL,
  `chunk_index` int NOT NULL COMMENT 'Índice sequencial do chunk no documento',
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokens` int DEFAULT '0' COMMENT 'Número aproximado de tokens',
  `embedding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Vetor de embedding (1536 dimensões para text-embedding-3-small)',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Metadados adicionais (página, seção, etc)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_documento` (`documento_id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_chunk_index` (`chunk_index`),
  CONSTRAINT `professores_ia_documentos_chunks_chk_1` CHECK (json_valid(`embedding`)),
  CONSTRAINT `professores_ia_documentos_chunks_chk_2` CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `professores_ia_mensagens`
--

DROP TABLE IF EXISTS `professores_ia_mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professores_ia_mensagens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conversa_id` int unsigned NOT NULL,
  `role` enum('user','assistant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `conteudo` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `chunks_usados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'IDs dos chunks usados para gerar a resposta',
  `tokens_usados` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversa` (`conversa_id`),
  KEY `idx_role` (`role`),
  CONSTRAINT `professores_ia_mensagens_chk_1` CHECK (json_valid(`chunks_usados`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `professores_slides`
--

DROP TABLE IF EXISTS `professores_slides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professores_slides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Título do slide (extraído do conteúdo ou gerado)',
  `conteudo` text COLLATE utf8mb4_unicode_ci COMMENT 'Conteúdo original usado para gerar o slide',
  `url_gamma` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL da apresentação no Gamma',
  `generation_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID da geração no Gamma',
  `numero_slides` int DEFAULT '8' COMMENT 'Número de slides gerados',
  `tema` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tema usado na geração',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_professor_id` (`professor_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_professor_slides_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Slides gerados pelos professores';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas`
--

DROP TABLE IF EXISTS `provas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `turma_id` int DEFAULT NULL COMMENT 'NULL = múltiplas escolas/turmas',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `observacao_coordenacao` text COLLATE utf8mb4_unicode_ci COMMENT 'Observações da coordenação ao retornar prova ao professor',
  `observacao_coordenacao_data` datetime DEFAULT NULL COMMENT 'Data em que a coordenação retornou a prova',
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL,
  `data_prova` date DEFAULT NULL COMMENT 'Data da prova',
  `data_limite_envio` datetime DEFAULT NULL COMMENT 'Data limite para o professor enviar a prova',
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
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_alternativas`
--

DROP TABLE IF EXISTS `provas_alternativas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_alternativas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `questao_id` int NOT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci,
  `correta` tinyint(1) DEFAULT '0',
  `ordem` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `questao_id` (`questao_id`),
  KEY `ordem` (`ordem`),
  CONSTRAINT `provas_alternativas_ibfk_1` FOREIGN KEY (`questao_id`) REFERENCES `provas_questoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1451 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_blocos`
--

DROP TABLE IF EXISTS `provas_blocos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_blocos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome do bloco (ex: "Prova Bimestral 1º Bimestre")',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'aguardando',
  `prazo_entrega_professor` datetime DEFAULT NULL COMMENT 'Prazo para professores enviarem suas provas',
  `descricao` text COLLATE utf8mb4_unicode_ci COMMENT 'Descrição do bloco',
  `data_prova` date NOT NULL COMMENT 'Data da prova',
  `hora_inicio` time NOT NULL COMMENT 'Horário de início',
  `hora_fim` time NOT NULL COMMENT 'Horário de término',
  `criado_por` int NOT NULL COMMENT 'ID do usuário que criou (admin/coordenador/diretor)',
  `professor_id` int DEFAULT NULL COMMENT 'Professor responsável pelo evento',
  `materia_id` int DEFAULT NULL COMMENT 'Matéria do evento',
  `tipo_prova` enum('original','substitutiva') COLLATE utf8mb4_unicode_ci DEFAULT 'original' COMMENT 'Tipo de prova',
  `configuracao_nota` enum('professor_por_questao','coordenacao_calcula') COLLATE utf8mb4_unicode_ci DEFAULT 'professor_por_questao' COMMENT 'Quem define a nota',
  `liberar_gabarito` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'imediatamente' COMMENT 'Quando liberar gabarito: imediatamente ou datetime',
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
  CONSTRAINT `provas_blocos_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `provas_blocos_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `provas_blocos_ibfk_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `provas_blocos_ibfk_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_blocos_modelos`
--

DROP TABLE IF EXISTS `provas_blocos_modelos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_blocos_modelos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_blocos_modelos_professores`
--

DROP TABLE IF EXISTS `provas_blocos_modelos_professores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_blocos_modelos_professores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modelo_id` int NOT NULL,
  `professor_id` int NOT NULL,
  `materia_id` int NOT NULL,
  `numero_questoes` int NOT NULL DEFAULT '0',
  `ordem` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modelo_professor_materia` (`modelo_id`,`professor_id`,`materia_id`),
  KEY `modelo_id` (`modelo_id`),
  KEY `professor_id` (`professor_id`),
  KEY `materia_id` (`materia_id`),
  CONSTRAINT `provas_blocos_modelos_professores_ibfk_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_blocos_modelos_professores_ibfk_modelo` FOREIGN KEY (`modelo_id`) REFERENCES `provas_blocos_modelos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_blocos_modelos_professores_ibfk_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_blocos_professores`
--

DROP TABLE IF EXISTS `provas_blocos_professores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_blocos_professores` (
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
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_blocos_professores_turmas`
--

DROP TABLE IF EXISTS `provas_blocos_professores_turmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_blocos_professores_turmas` (
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
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_blocos_turmas`
--

DROP TABLE IF EXISTS `provas_blocos_turmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_blocos_turmas` (
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
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_blocos_vinculo`
--

DROP TABLE IF EXISTS `provas_blocos_vinculo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_blocos_vinculo` (
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
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_final`
--

DROP TABLE IF EXISTS `provas_final`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_final` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_professores`
--

DROP TABLE IF EXISTS `provas_professores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_professores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bloco_id` int NOT NULL COMMENT 'ID do bloco de provas',
  `professor_id` int NOT NULL COMMENT 'ID do professor',
  `materia_id` int NOT NULL COMMENT 'ID da matéria',
  `numero_questoes` int DEFAULT '0' COMMENT 'Número de questões solicitadas',
  `status` enum('em_andamento','enviada','nao_enviada','aprovada','reprovada') COLLATE utf8mb4_unicode_ci DEFAULT 'em_andamento',
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_questoes`
--

DROP TABLE IF EXISTS `provas_questoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_questoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `enunciado` text COLLATE utf8mb4_unicode_ci,
  `imagem_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL da imagem do enunciado',
  `tipo` enum('multipla_escolha','verdadeiro_falso','dissertativa') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'multipla_escolha',
  `valor` decimal(10,2) DEFAULT '1.00',
  `nivel_dificuldade` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int DEFAULT '0',
  `explicacao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `prova_id` (`prova_id`),
  KEY `ordem` (`ordem`),
  CONSTRAINT `provas_questoes_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=265 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_realizacoes`
--

DROP TABLE IF EXISTS `provas_realizacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_realizacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `iniciado_em` datetime NOT NULL,
  `finalizado_em` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT NULL COMMENT 'Tempo em minutos',
  `nota` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem_questoes` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON com ordem das questões sorteadas',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prova_aluno` (`prova_id`,`aluno_id`),
  KEY `prova_id` (`prova_id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `status` (`status`),
  CONSTRAINT `provas_realizacoes_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_realizacoes_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1445 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_respostas`
--

DROP TABLE IF EXISTS `provas_respostas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_respostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `questao_id` int NOT NULL,
  `alternativa_id` int DEFAULT NULL COMMENT 'Para múltipla escolha',
  `resposta_texto` text COLLATE utf8mb4_unicode_ci COMMENT 'Para dissertativa',
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
) ENGINE=InnoDB AUTO_INCREMENT=4685 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provas_turmas`
--

DROP TABLE IF EXISTS `provas_turmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provas_turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prova_turma` (`prova_id`,`turma_id`),
  KEY `prova_id` (`prova_id`),
  KEY `turma_id` (`turma_id`),
  CONSTRAINT `provas_turmas_ibfk_1` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provas_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `questoes`
--

DROP TABLE IF EXISTS `questoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `questoes` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_questoes_lista` (`lista_id`),
  CONSTRAINT `questoes_ibfk_1` FOREIGN KEY (`lista_id`) REFERENCES `listas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=519 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `questoes_personalizadas`
--

DROP TABLE IF EXISTS `questoes_personalizadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `questoes_personalizadas` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lista_id` (`lista_id`),
  KEY `nivel_dificuldade` (`nivel_dificuldade`),
  CONSTRAINT `fk_questoes_personalizadas_lista` FOREIGN KEY (`lista_id`) REFERENCES `listas_personalizadas_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12050 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes`
--

DROP TABLE IF EXISTS `redacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `tema_id` int DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `tema_texto` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `conteudo` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `eh_rascunho` tinyint(1) DEFAULT '0',
  `tempo_escrita` int DEFAULT '0' COMMENT 'Tempo de escrita em segundos',
  `tipo` enum('padrao','livre','ia_gerado','transcricao') COLLATE utf8mb3_unicode_ci DEFAULT 'padrao',
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
  `texto` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `imagem_url` varchar(500) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Caso seja foto enviada',
  `correcao` text COLLATE utf8mb3_unicode_ci COMMENT 'Correção gerada pela IA',
  `nota` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `oculto` tinyint(1) DEFAULT '0',
  `usar_media_notas` tinyint(1) DEFAULT '0' COMMENT 'Usar média entre nota do professor e IA',
  `nota_media` decimal(5,2) DEFAULT NULL COMMENT 'Média calculada entre nota do professor e IA',
  `nota_final_utilizada` decimal(5,2) DEFAULT NULL COMMENT 'Nota final que será utilizada (professor, IA ou média)',
  `mostrar_correcao_ia_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra a correção da IA para o aluno. Se 0, mostra apenas a correção do professor.',
  `mostrar_competencia_1_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 1 para o aluno',
  `mostrar_competencia_2_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 2 para o aluno',
  `mostrar_competencia_3_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 3 para o aluno',
  `mostrar_competencia_4_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 4 para o aluno',
  `mostrar_competencia_5_aluno` tinyint(1) DEFAULT '0' COMMENT 'Se 1, mostra competência 5 para o aluno',
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
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_tema` (`tema`),
  KEY `idx_nota` (`nota`),
  KEY `idx_created_at` (`created_at`),
  KEY `tema_id` (`tema_id`),
  KEY `jornada_id` (`jornada_id`),
  CONSTRAINT `redacoes_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_ibfk_2` FOREIGN KEY (`tema_id`) REFERENCES `redacoes_temas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_ibfk_jornada` FOREIGN KEY (`jornada_id`) REFERENCES `jornadas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `redacoes_chk_1` CHECK (json_valid(`tema_gerado`))
) ENGINE=InnoDB AUTO_INCREMENT=589 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_orientadas_correcoes`
--

DROP TABLE IF EXISTS `redacoes_orientadas_correcoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_orientadas_correcoes` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_essay_corrections_submission` (`submission_id`),
  KEY `idx_essay_corrections_submission` (`submission_id`),
  KEY `prompt_id` (`prompt_id`),
  KEY `corrected_by_teacher_id` (`corrected_by_teacher_id`),
  CONSTRAINT `redacoes_orientadas_correcoes_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `redacoes_orientadas_entregas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_orientadas_correcoes_ibfk_2` FOREIGN KEY (`prompt_id`) REFERENCES `redacoes_orientadas_prompts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `redacoes_orientadas_correcoes_ibfk_3` FOREIGN KEY (`corrected_by_teacher_id`) REFERENCES `professores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `redacoes_orientadas_correcoes_chk_1` CHECK (json_valid(`raw_response_json`)),
  CONSTRAINT `redacoes_orientadas_correcoes_chk_2` CHECK (json_valid(`grades_json`)),
  CONSTRAINT `redacoes_orientadas_correcoes_chk_3` CHECK (json_valid(`teacher_grades_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_orientadas_correcoes_logs`
--

DROP TABLE IF EXISTS `redacoes_orientadas_correcoes_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_orientadas_correcoes_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `correction_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_essay_correction_logs_correction` (`correction_id`),
  CONSTRAINT `redacoes_orientadas_correcoes_logs_ibfk_1` FOREIGN KEY (`correction_id`) REFERENCES `redacoes_orientadas_correcoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_orientadas_correcoes_logs_chk_1` CHECK (json_valid(`details_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_orientadas_criterios`
--

DROP TABLE IF EXISTS `redacoes_orientadas_criterios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_orientadas_criterios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `text_type_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `max_score` decimal(5,2) NOT NULL DEFAULT '200.00',
  `order_position` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_essay_criteria_type_slug` (`text_type_id`,`slug`),
  KEY `idx_essay_criteria_text_type` (`text_type_id`),
  CONSTRAINT `redacoes_orientadas_criterios_ibfk_1` FOREIGN KEY (`text_type_id`) REFERENCES `redacoes_orientadas_tipos_texto` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_orientadas_entregas`
--

DROP TABLE IF EXISTS `redacoes_orientadas_entregas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_orientadas_entregas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proposal_id` int NOT NULL,
  `student_id` int NOT NULL,
  `content_text` longtext COLLATE utf8mb4_unicode_ci,
  `content_image_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ocr_text` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_orientadas_prompts`
--

DROP TABLE IF EXISTS `redacoes_orientadas_prompts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_orientadas_prompts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `board_id` int NOT NULL,
  `text_type_id` int NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  `prompt_text` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_orientadas_propostas`
--

DROP TABLE IF EXISTS `redacoes_orientadas_propostas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_orientadas_propostas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `board_id` int NOT NULL,
  `text_type_id` int NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme` text COLLATE utf8mb4_unicode_ci,
  `repertoire` text COLLATE utf8mb4_unicode_ci,
  `images_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'URLs or paths of attached images',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_essay_proposals_teacher` (`teacher_id`),
  KEY `idx_essay_proposals_board_type` (`board_id`,`text_type_id`),
  KEY `idx_essay_proposals_status` (`status`),
  KEY `text_type_id` (`text_type_id`),
  CONSTRAINT `redacoes_orientadas_propostas_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redacoes_orientadas_propostas_ibfk_2` FOREIGN KEY (`board_id`) REFERENCES `redacoes_orientadas_quadros` (`id`),
  CONSTRAINT `redacoes_orientadas_propostas_ibfk_3` FOREIGN KEY (`text_type_id`) REFERENCES `redacoes_orientadas_tipos_texto` (`id`),
  CONSTRAINT `redacoes_orientadas_propostas_chk_1` CHECK (json_valid(`images_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_orientadas_propostas_alunos`
--

DROP TABLE IF EXISTS `redacoes_orientadas_propostas_alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_orientadas_propostas_alunos` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_orientadas_propostas_turmas`
--

DROP TABLE IF EXISTS `redacoes_orientadas_propostas_turmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_orientadas_propostas_turmas` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_orientadas_quadros`
--

DROP TABLE IF EXISTS `redacoes_orientadas_quadros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_orientadas_quadros` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_essay_boards_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_orientadas_tipos_texto`
--

DROP TABLE IF EXISTS `redacoes_orientadas_tipos_texto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_orientadas_tipos_texto` (
  `id` int NOT NULL AUTO_INCREMENT,
  `board_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_essay_text_types_board_slug` (`board_id`,`slug`),
  KEY `idx_essay_text_types_board` (`board_id`),
  CONSTRAINT `redacoes_orientadas_tipos_texto_ibfk_1` FOREIGN KEY (`board_id`) REFERENCES `redacoes_orientadas_quadros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redacoes_temas`
--

DROP TABLE IF EXISTS `redacoes_temas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redacoes_temas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('Temas Autorais','Temas de Vestibulares','Redações Pré-existentes') COLLATE utf8mb3_unicode_ci NOT NULL,
  `instrucoes` text COLLATE utf8mb3_unicode_ci,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redefinicoes_senha`
--

DROP TABLE IF EXISTS `redefinicoes_senha`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redefinicoes_senha` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `relatorios`
--

DROP TABLE IF EXISTS `relatorios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `relatorios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `tipo` enum('desempenho','jornada','redacao') COLLATE utf8mb3_unicode_ci NOT NULL,
  `dados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Dados detalhados do relatório',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `relatorios_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `relatorios_chk_1` CHECK (json_valid(`dados`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `responsaveis`
--

DROP TABLE IF EXISTS `responsaveis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `responsaveis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `cpf` varchar(14) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_cpf` (`cpf`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessoes`
--

DROP TABLE IF EXISTS `sessoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessoes` (
  `id` varchar(128) COLLATE utf8mb3_unicode_ci NOT NULL,
  `usuario_id` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb3_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb3_unicode_ci,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `sessoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `simulados`
--

DROP TABLE IF EXISTS `simulados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `simulados` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `oculto` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_simulados_aluno` (`aluno_id`),
  KEY `idx_simulados_status` (`status`),
  KEY `idx_simulados_ano` (`ano`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `simulados_estatisticas`
--

DROP TABLE IF EXISTS `simulados_estatisticas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `simulados_estatisticas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `simulado_id` int NOT NULL,
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `total_questoes` int DEFAULT '0',
  `acertos` int DEFAULT '0',
  `erros` int DEFAULT '0',
  `percentual_acerto` decimal(5,2) DEFAULT '0.00',
  `tempo_medio` decimal(8,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_simulado_estatisticas_simulado` (`simulado_id`),
  KEY `idx_simulado_estatisticas_materia` (`materia`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `simulados_questoes`
--

DROP TABLE IF EXISTS `simulados_questoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `simulados_questoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `simulado_id` int NOT NULL,
  `questao_index` int NOT NULL,
  `questao_id` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `enunciado` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_a` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_b` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_c` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_d` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `alternativa_e` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `resposta_certa` varchar(1) COLLATE utf8mb3_unicode_ci NOT NULL,
  `resposta_aluno` varchar(1) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `acertou` tinyint(1) DEFAULT NULL,
  `respondido_em` datetime DEFAULT NULL,
  `tempo_gasto` int DEFAULT '0',
  `materia` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `dificuldade` enum('facil','medio','dificil') COLLATE utf8mb3_unicode_ci DEFAULT 'medio',
  `alternativa_a_file` text COLLATE utf8mb3_unicode_ci COMMENT 'URL da imagem da alternativa A',
  `alternativa_b_file` text COLLATE utf8mb3_unicode_ci COMMENT 'URL da imagem da alternativa B',
  `alternativa_c_file` text COLLATE utf8mb3_unicode_ci COMMENT 'URL da imagem da alternativa C',
  `alternativa_d_file` text COLLATE utf8mb3_unicode_ci COMMENT 'URL da imagem da alternativa D',
  `alternativa_e_file` text COLLATE utf8mb3_unicode_ci COMMENT 'URL da imagem da alternativa E',
  PRIMARY KEY (`id`),
  KEY `idx_simulado_questoes_simulado` (`simulado_id`),
  KEY `idx_simulado_questoes_index` (`questao_index`)
) ENGINE=InnoDB AUTO_INCREMENT=757 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suporte_tickets`
--

DROP TABLE IF EXISTS `suporte_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suporte_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `assunto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'geral',
  `modulo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('aberto','em_andamento','respondido','fechado') COLLATE utf8mb4_unicode_ci DEFAULT 'aberto',
  `prioridade` enum('baixa','normal','alta','urgente') COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suporte_tickets_mensagens`
--

DROP TABLE IF EXISTS `suporte_tickets_mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suporte_tickets_mensagens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `remetente_tipo` enum('aluno','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `remetente_id` int NOT NULL COMMENT 'ID do aluno ou admin',
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `anexo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nome do arquivo anexado',
  `lida` tinyint(1) DEFAULT '0',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_criado_em` (`criado_em`),
  KEY `idx_lida` (`lida`),
  KEY `idx_mensagens_ticket_criado` (`ticket_id`,`criado_em`),
  CONSTRAINT `suporte_tickets_mensagens_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `suporte_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tentativas_login`
--

DROP TABLE IF EXISTS `tentativas_login`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tentativas_login` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb3_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tudinha_analises`
--

DROP TABLE IF EXISTS `tudinha_analises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tudinha_analises` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `retention_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `aluno_id` (`aluno_id`),
  KEY `data_ate` (`data_ate`),
  KEY `created_at` (`created_at`),
  KEY `idx_analises_expires` (`expires_at`),
  KEY `idx_analises_anonymized` (`anonymized_at`),
  CONSTRAINT `fk_analises_tudinha_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tudinha_conversas`
--

DROP TABLE IF EXISTS `tudinha_conversas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tudinha_conversas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `materia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=671 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tudinha_mensagens`
--

DROP TABLE IF EXISTS `tudinha_mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tudinha_mensagens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `conversa_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `mensagem` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('texto','imagem','audio') COLLATE utf8mb4_unicode_ci DEFAULT 'texto',
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_ia` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `anonymized_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `retention_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=2949 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `turmas`
--

DROP TABLE IF EXISTS `turmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Ex: 1ºA, 2ºB',
  `ano_letivo` int NOT NULL,
  `serie` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tipo_ensino` varchar(30) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ano_letivo` (`ano_letivo`),
  KEY `idx_serie` (`serie`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tutoriais`
--

DROP TABLE IF EXISTS `tutoriais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutoriais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `link_youtube` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ordem` (`ordem`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('admin_escola') COLLATE utf8mb3_unicode_ci NOT NULL,
  `perfil_admin` enum('dev','diretor','coordenador','financeiro','secretaria') COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Aplicável apenas quando tipo = admin_escola',
  `permissoes_admin_json` json DEFAULT NULL COMMENT 'Permissões administrativas por módulo e ação (visualizar, cadastrar, alterar, excluir)',
  `perfil_permissao_id` int DEFAULT NULL,
  `nome` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Apenas para admins e pais',
  `senha_hash` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `avatar_url` varchar(500) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `criado_por` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_email` (`email`),
  KEY `idx_perfil_admin` (`perfil_admin`),
  KEY `idx_usuarios_perfil_permissao_id` (`perfil_permissao_id`),
  KEY `criado_por` (`criado_por`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_perfis_permissao`
--

DROP TABLE IF EXISTS `admin_perfis_permissao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_perfis_permissao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo_base` enum('dev','diretor','coordenador','financeiro','secretaria') COLLATE utf8mb3_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios_consentimentos`
--

DROP TABLE IF EXISTS `usuarios_consentimentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios_consentimentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `user_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accepted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_consent` (`user_id`,`user_role`,`document_type`,`document_version`),
  KEY `idx_user_role` (`user_role`),
  KEY `idx_document_type` (`document_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1940 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhooks`
--

DROP TABLE IF EXISTS `webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhooks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `endpoint` varchar(500) COLLATE utf8mb3_unicode_ci NOT NULL,
  `tipo` enum('chat_ia','chat','geral') COLLATE utf8mb3_unicode_ci NOT NULL,
  `escola_id` int DEFAULT NULL COMMENT 'NULL para webhook global',
  `ativo` tinyint(1) DEFAULT '1',
  `configuracao` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Configurações adicionais do webhook',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_escola` (`escola_id`),
  KEY `idx_ativo` (`ativo`),
  CONSTRAINT `webhooks_chk_1` CHECK (json_valid(`configuracao`))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;


COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
SET UNIQUE_CHECKS = 1;


/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-27 11:47:21
