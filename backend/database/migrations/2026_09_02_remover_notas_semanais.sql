-- Remove tabelas do módulo notas-semanais (substituído por grupos_regras_notas).
-- Tenant. Idempotente. Rollback: 2026_09_02_remover_notas_semanais_rollback.sql
-- DROP justificado: cadastro A/B + S1–S8 passou a viver em grupos_regras_notas.

DROP TABLE IF EXISTS `notas_semanais_materias`;
DROP TABLE IF EXISTS `notas_semanais_config`;
