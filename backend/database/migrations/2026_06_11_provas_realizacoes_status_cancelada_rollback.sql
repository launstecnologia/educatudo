-- Rollback: restaura status de provas_realizacoes para ENUM restrito
-- ATENÇÃO: se existirem registros com status='cancelada', este rollback irá truncá-los.
ALTER TABLE `provas_realizacoes`
  MODIFY COLUMN `status` ENUM('iniciado','finalizado') NULL DEFAULT NULL;
