-- Perfil admin_escola "secretaria" (acesso restrito no painel).
-- Inclui "financeiro" no ENUM caso a coluna ainda não o tivesse (ambientes antigos).

ALTER TABLE usuarios
  MODIFY COLUMN perfil_admin ENUM('dev','diretor','coordenador','financeiro','secretaria')
  CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
  COMMENT 'Aplicável apenas quando tipo = admin_escola';
