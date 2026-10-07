-- Remove só os fechamentos de diário criados para a população ET25.
-- Tenant. Par de 2026_10_07_diario_et25_fechado.sql

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DELETE FROM diario_fechamentos
WHERE observacoes = 'ET25 diário fechado com o trimestre';
