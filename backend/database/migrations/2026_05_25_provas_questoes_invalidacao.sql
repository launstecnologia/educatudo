-- Permite invalidar questão e registrar metadados da ação da coordenação.
ALTER TABLE provas_questoes
  ADD COLUMN invalidada TINYINT(1) NOT NULL DEFAULT 0 AFTER valor,
  ADD COLUMN observacao_invalidacao TEXT NULL AFTER invalidada,
  ADD COLUMN invalidada_por INT NULL AFTER observacao_invalidacao,
  ADD COLUMN invalidada_em DATETIME NULL AFTER invalidada_por;

CREATE INDEX idx_provas_questoes_invalidada ON provas_questoes (invalidada);
