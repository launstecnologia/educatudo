-- Áudio de feedback do professor na correção da redação configurável
-- (também criado em runtime via EssayCorrection::ensureTeacherFeedbackAudioColumn)

ALTER TABLE redacoes_orientadas_correcoes
  ADD COLUMN teacher_feedback_audio_key VARCHAR(512) NULL DEFAULT NULL;
