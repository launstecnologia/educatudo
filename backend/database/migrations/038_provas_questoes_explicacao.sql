-- Adiciona campo explicacao nas questões de prova (gerado pela IA para feedback ao aluno)
ALTER TABLE `provas_questoes` ADD COLUMN `explicacao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `ordem`;
