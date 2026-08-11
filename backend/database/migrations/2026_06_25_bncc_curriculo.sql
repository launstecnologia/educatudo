-- BNCC + Plano de Curso
-- Catálogo de habilidades da BNCC, planos de curso por série/matéria e o
-- vínculo das habilidades previstas (com marcação de "trabalhada") usado para
-- calcular a cobertura curricular no Painel de Conformidade.
-- Executar em cada banco de tenant. Idempotente.

CREATE TABLE IF NOT EXISTS `bncc_habilidades` (
  `id`                  INT(11) NOT NULL AUTO_INCREMENT,
  `codigo`              VARCHAR(20) NOT NULL,
  `descricao`           TEXT NOT NULL,
  `etapa`               VARCHAR(40) NULL COMMENT 'Educação Infantil, Ensino Fundamental, Ensino Médio',
  `componente`          VARCHAR(120) NULL COMMENT 'Componente curricular / área',
  `ano_serie`           VARCHAR(40) NULL,
  `unidade_tematica`    VARCHAR(255) NULL,
  `objeto_conhecimento` VARCHAR(255) NULL,
  `ativo`               TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`          DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bncc_codigo` (`codigo`),
  KEY `idx_bncc_componente` (`componente`),
  KEY `idx_bncc_ano` (`ano_serie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plano_curso` (
  `id`                     INT(11) NOT NULL AUTO_INCREMENT,
  `ano_letivo_id`          INT(11) NULL,
  `serie_id`               INT(11) NULL,
  `materia_id`             INT(11) NOT NULL,
  `carga_horaria_prevista` INT(11) NOT NULL DEFAULT 0 COMMENT 'horas/aula previstas no ano',
  `avaliacoes_previstas`   INT(11) NOT NULL DEFAULT 0,
  `conteudo_previsto`      TEXT NULL,
  `objetivos`              TEXT NULL,
  `metodologia`            TEXT NULL,
  `status`                 ENUM('rascunho','aprovado') NOT NULL DEFAULT 'rascunho',
  `created_by`             INT(11) NULL,
  `created_at`             DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plano_curso` (`ano_letivo_id`, `serie_id`, `materia_id`),
  KEY `idx_plano_curso_materia` (`materia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plano_curso_habilidades` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `plano_curso_id`  INT(11) NOT NULL,
  `habilidade_id`   INT(11) NOT NULL,
  `trabalhada`      TINYINT(1) NOT NULL DEFAULT 0,
  `trabalhada_em`   DATETIME NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plano_curso_hab` (`plano_curso_id`, `habilidade_id`),
  KEY `idx_pch_habilidade` (`habilidade_id`),
  CONSTRAINT `fk_pch_plano` FOREIGN KEY (`plano_curso_id`) REFERENCES `plano_curso` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed mínimo de habilidades (amostra para demonstração/validação). O catálogo
-- completo da BNCC pode ser importado depois via /admin/bncc (upload de JSON/CSV).
INSERT IGNORE INTO `bncc_habilidades` (`codigo`, `descricao`, `etapa`, `componente`, `ano_serie`, `unidade_tematica`, `objeto_conhecimento`) VALUES
('EF06MA01', 'Comparar, ordenar, ler e escrever números naturais e números racionais.', 'Ensino Fundamental', 'Matemática', '6º ano', 'Números', 'Sistema de numeração decimal'),
('EF06MA17', 'Quantificar e estabelecer relações entre o número de vértices, faces e arestas de poliedros.', 'Ensino Fundamental', 'Matemática', '6º ano', 'Geometria', 'Polígonos e poliedros'),
('EF06MA22', 'Utilizar medidas de comprimento, área, volume, capacidade e massa em situações do cotidiano.', 'Ensino Fundamental', 'Matemática', '6º ano', 'Grandezas e medidas', 'Problemas com medidas'),
('EF06MA30', 'Calcular a probabilidade de um evento aleatório expressando-a por número racional.', 'Ensino Fundamental', 'Matemática', '6º ano', 'Probabilidade e estatística', 'Cálculo de probabilidade'),
('EF06LP01', 'Reconhecer a impossibilidade de uma neutralidade absoluta no relato de fatos.', 'Ensino Fundamental', 'Língua Portuguesa', '6º ano', 'Leitura', 'Reconstrução do contexto'),
('EF06LP04', 'Analisar a função e as flexões de substantivos e adjetivos.', 'Ensino Fundamental', 'Língua Portuguesa', '6º ano', 'Análise linguística', 'Morfologia'),
('EF06CI01', 'Classificar materiais em recicláveis e não recicláveis.', 'Ensino Fundamental', 'Ciências', '6º ano', 'Matéria e energia', 'Transformações químicas'),
('EF06HI01', 'Identificar diferentes formas de compreensão da noção de tempo.', 'Ensino Fundamental', 'História', '6º ano', 'História: tempo e espaço', 'A questão do tempo'),
('EF06GE01', 'Comparar modificações das paisagens nos lugares de vivência.', 'Ensino Fundamental', 'Geografia', '6º ano', 'O sujeito e seu lugar no mundo', 'Identidade sociocultural'),
('EF08MA19', 'Resolver e elaborar problemas que envolvam medidas de área de figuras geométricas.', 'Ensino Fundamental', 'Matemática', '8º ano', 'Grandezas e medidas', 'Área de figuras planas');
