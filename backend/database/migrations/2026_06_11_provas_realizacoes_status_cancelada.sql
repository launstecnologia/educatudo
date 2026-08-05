-- Garante que provas_realizacoes.status aceita o valor 'cancelada' (modo prova segura).
-- Em bancos antigos a coluna é ENUM('iniciado','finalizado') e o INSERT/UPDATE de 'cancelada'
-- falha com "Data truncated for column 'status'".
ALTER TABLE `provas_realizacoes`
  MODIFY COLUMN `status` VARCHAR(20) NULL DEFAULT NULL;
