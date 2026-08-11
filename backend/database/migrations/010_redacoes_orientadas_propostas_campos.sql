-- Novos campos para Proposta de Redação (tenant)
-- theme_mode: configurar = tema/repertório manual; arquivo = tema pronto (PDF/imagem)
-- tema_pronto_file: caminho/URL do arquivo quando theme_mode = arquivo
-- show_title_field: 1 = exibir campo título da redação para o aluno
-- starts_at / ends_at: período para o aluno realizar a redação

ALTER TABLE `redacoes_orientadas_propostas`
  ADD COLUMN `theme_mode` ENUM('configurar','arquivo') NOT NULL DEFAULT 'configurar' COMMENT 'Como o tema foi definido' AFTER `text_type_id`,
  ADD COLUMN `tema_pronto_file` VARCHAR(500) NULL COMMENT 'URL ou path do PDF/imagem quando theme_mode=arquivo' AFTER `repertoire`,
  ADD COLUMN `show_title_field` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=exibir campo título para o aluno' AFTER `images_json`,
  ADD COLUMN `starts_at` DATETIME NULL COMMENT 'Início do período de realização' AFTER `show_title_field`,
  ADD COLUMN `ends_at` DATETIME NULL COMMENT 'Fim do período de realização' AFTER `starts_at`;
