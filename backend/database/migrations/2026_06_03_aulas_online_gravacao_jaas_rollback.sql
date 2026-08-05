-- Rollback: remove colunas de gravação JaaS de aulas_online
ALTER TABLE `aulas_online`
  DROP COLUMN IF EXISTS `jaas_recording_url`,
  DROP COLUMN IF EXISTS `jaas_recording_path`,
  DROP COLUMN IF EXISTS `jaas_recording_session_id`,
  DROP COLUMN IF EXISTS `jaas_recording_uploaded_at`,
  DROP COLUMN IF EXISTS `jaas_recording_webhook_raw`;
