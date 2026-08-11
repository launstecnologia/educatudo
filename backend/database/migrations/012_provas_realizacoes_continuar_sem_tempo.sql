-- Permite ao admin liberar que o aluno continue a prova mesmo com tempo encerrado (sem contar tempo)
ALTER TABLE `provas_realizacoes`
  ADD COLUMN `continuar_sem_tempo` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1 = liberado pelo admin para continuar sem limite de tempo' AFTER `ordem_questoes`;
