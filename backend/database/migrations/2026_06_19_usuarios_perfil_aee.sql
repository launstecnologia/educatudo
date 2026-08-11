-- EducaInclui — Perfil admin_escola "aee" (Atendimento Educacional Especializado).
-- Acesso focado no EducaInclui (vê laudo) + leitura de aluno/avaliações.

ALTER TABLE usuarios
  MODIFY COLUMN perfil_admin ENUM('dev','diretor','coordenador','aee','financeiro','secretaria')
  CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
  COMMENT 'Aplicável apenas quando tipo = admin_escola';
