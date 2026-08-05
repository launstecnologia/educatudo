-- Corrige overflow ao salvar nota máxima (1000) de redação: total_score,
-- ai_total_score e teacher_total_score eram DECIMAL(5,2) (máximo 999.99),
-- mas o total ENEM (5 competências x 200) pode chegar a 1000.00, estourando
-- a coluna com "SQLSTATE[22003]: Out of range value for column 'total_score'".
-- MODIFY COLUMN é idempotente (rodar de novo com o mesmo tipo não dá erro).
ALTER TABLE redacoes_orientadas_correcoes
  MODIFY COLUMN total_score DECIMAL(6,2) DEFAULT NULL,
  MODIFY COLUMN ai_total_score DECIMAL(6,2) DEFAULT NULL COMMENT 'Soma das notas IA por critério',
  MODIFY COLUMN teacher_total_score DECIMAL(6,2) DEFAULT NULL COMMENT 'Soma das notas do professor';
