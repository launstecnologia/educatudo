-- Destrutivo: remove regras e instâncias de contrato geradas (PDF/ZapSign).
-- Só usar se a feature for revertida antes de produção com dados reais.

DROP TABLE IF EXISTS matricula_processos_contratos;
DROP TABLE IF EXISTS matricula_contrato_regras;
