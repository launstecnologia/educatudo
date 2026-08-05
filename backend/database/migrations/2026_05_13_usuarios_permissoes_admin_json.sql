ALTER TABLE usuarios
  ADD COLUMN permissoes_admin_json JSON NULL
  COMMENT 'Permissões administrativas por módulo e ação (visualizar, cadastrar, alterar, excluir)';
