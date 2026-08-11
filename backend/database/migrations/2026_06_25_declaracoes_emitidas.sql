-- Histórico/auditoria de declarações oficiais emitidas para alunos.
-- Cada emissão recebe um número sequencial por ano (numero/ano) e registra
-- quem emitiu, o tipo e a unidade usada no cabeçalho do documento.
--
-- Executar em cada banco de tenant. Idempotente.

CREATE TABLE IF NOT EXISTS `declaracoes_emitidas` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `aluno_id`      INT(11) NOT NULL,
  `unidade_id`    INT(11) NULL,
  `tipo`          VARCHAR(40) NOT NULL COMMENT 'matricula|frequencia|comparecimento|transferencia',
  `numero`        INT(11) NOT NULL DEFAULT 0 COMMENT 'Sequencial por ano',
  `ano`           SMALLINT(5) UNSIGNED NOT NULL,
  `emitido_por`   INT(11) NULL COMMENT 'usuarios.id do admin que emitiu',
  `emitido_nome`  VARCHAR(255) NULL,
  `meta_json`     TEXT NULL COMMENT 'Parâmetros usados (período, data, etc.)',
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_decl_aluno` (`aluno_id`),
  KEY `idx_decl_tipo` (`tipo`),
  KEY `idx_decl_ano_numero` (`ano`, `numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
