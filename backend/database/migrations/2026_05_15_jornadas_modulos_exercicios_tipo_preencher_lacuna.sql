-- Adiciona novo tipo de exercício: preencher_lacuna
ALTER TABLE jornadas_modulos_exercicios
  MODIFY COLUMN tipo ENUM('alternativas','verdadeiro_falso','dissertativa','preencher_lacuna')
  NOT NULL DEFAULT 'alternativas';

