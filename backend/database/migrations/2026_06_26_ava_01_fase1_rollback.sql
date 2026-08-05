-- Rollback do AVA Fase 1 (2026_06_26_ava_01_fase1.sql)
-- Drop em ordem reversa de dependencia (filhas antes das pais).
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `ava_progresso_video`;
DROP TABLE IF EXISTS `ava_progresso_aula`;
DROP TABLE IF EXISTS `ava_matriculas_disciplina`;
DROP TABLE IF EXISTS `ava_aula_anexos`;
DROP TABLE IF EXISTS `ava_aulas`;
DROP TABLE IF EXISTS `ava_modulos`;
DROP TABLE IF EXISTS `ava_disciplinas`;
DROP TABLE IF EXISTS `ava_semestres`;
DROP TABLE IF EXISTS `ava_cursos`;
DROP TABLE IF EXISTS `ava_categorias`;
SET FOREIGN_KEY_CHECKS = 1;
