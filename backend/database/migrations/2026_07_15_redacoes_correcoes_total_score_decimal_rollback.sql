-- Rollback: volta pro DECIMAL(5,2) original. Atenção: se algum registro já
-- tiver total_score/ai_total_score/teacher_total_score = 1000.00, este
-- rollback falha (999.99 é o máximo do tipo antigo) — resolver esses
-- registros manualmente antes de reverter, se necessário.
ALTER TABLE redacoes_orientadas_correcoes
  MODIFY COLUMN total_score DECIMAL(5,2) DEFAULT NULL,
  MODIFY COLUMN ai_total_score DECIMAL(5,2) DEFAULT NULL COMMENT 'Soma das notas IA por critério',
  MODIFY COLUMN teacher_total_score DECIMAL(5,2) DEFAULT NULL COMMENT 'Soma das notas do professor';
