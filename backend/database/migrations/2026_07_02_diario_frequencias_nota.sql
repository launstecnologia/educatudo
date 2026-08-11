-- Adiciona campo de nota individual por aluno na chamada do diário de aulas
ALTER TABLE `diario_frequencias`
  ADD COLUMN `nota` DECIMAL(5,2) NULL DEFAULT NULL AFTER `situacao`;

-- Expande observacao de VARCHAR(255) para TEXT para suportar conteúdo rich text
ALTER TABLE `diario_frequencias`
  MODIFY COLUMN `observacao` TEXT NULL;
