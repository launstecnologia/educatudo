-- Rollback da migration 2026_08_23_censo_escolar.sql
-- Remove tabelas do módulo Censo Escolar (tenant). Ordem respeita FKs.

SET @db := DATABASE();

DROP TABLE IF EXISTS `censo_auditoria`;
DROP TABLE IF EXISTS `censo_retornos`;
DROP TABLE IF EXISTS `censo_exportacoes`;
DROP TABLE IF EXISTS `censo_snapshots`;
DROP TABLE IF EXISTS `censo_validacoes`;
DROP TABLE IF EXISTS `censo_situacoes_aluno`;
DROP TABLE IF EXISTS `censo_vinculos_profissionais`;
DROP TABLE IF EXISTS `censo_matriculas`;
DROP TABLE IF EXISTS `censo_complementos_profissional`;
DROP TABLE IF EXISTS `censo_complementos_aluno`;
DROP TABLE IF EXISTS `censo_complementos_turma`;
DROP TABLE IF EXISTS `censo_complementos_gestor`;
DROP TABLE IF EXISTS `censo_complementos_escola`;
DROP TABLE IF EXISTS `censo_edicoes`;
DROP TABLE IF EXISTS `censo_tabelas_auxiliares`;
DROP TABLE IF EXISTS `censo_layouts`;
