-- Rollback do perfil "aee". ATENÇÃO: usuários com perfil_admin='aee' devem ser
-- reatribuídos antes de rodar (senão a coluna não aceitará o valor existente).

UPDATE usuarios SET perfil_admin = 'coordenador' WHERE perfil_admin = 'aee';

ALTER TABLE usuarios
  MODIFY COLUMN perfil_admin ENUM('dev','diretor','coordenador','financeiro','secretaria')
  CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
  COMMENT 'Aplicável apenas quando tipo = admin_escola';
