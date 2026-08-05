-- Caminho disciplina > tópico > subtópicos (catálogo Master e tenants com simulados_questoes).
-- Não usar AFTER status: em vários tenants a coluna status ainda não existe nessa tabela.
ALTER TABLE simulados_questoes
  ADD COLUMN materias_path_json JSON NULL
  COMMENT 'Array JSON: ["Disciplina","Tópico","Subtópico",...]';
