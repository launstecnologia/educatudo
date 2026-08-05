-- Multi-Tenant EducaTudo – Tabelas do banco administrador (master)
-- Executar APENAS no banco master (ex.: educatudo_master). Não altera bancos das escolas.
-- Convenção: CONVENCAO_NOME_TABELAS.md (português, plural, snake_case, config_*)

-- 1. Cadastro de escolas (tenants)
CREATE TABLE IF NOT EXISTS escolas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL COMMENT 'Nome da escola',
    slug VARCHAR(100) NOT NULL COMMENT 'Identificador único (ex.: subdomínio)',
    dominio VARCHAR(255) NULL COMMENT 'Domínio principal (ex.: escola1.educatudo.com.br)',
    ativo TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=suspenso',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_escolas_slug (slug),
    KEY idx_escolas_dominio (dominio),
    KEY idx_escolas_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Tenants (escolas) – banco master';

-- 2. Configuração de conexão ao banco de cada escola
CREATE TABLE IF NOT EXISTS config_escolas_banco (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    escola_id INT UNSIGNED NOT NULL,
    host VARCHAR(255) NOT NULL DEFAULT 'localhost',
    porta INT UNSIGNED NOT NULL DEFAULT 3306,
    nome_banco VARCHAR(128) NOT NULL COMMENT 'DB_NAME do tenant',
    usuario VARCHAR(128) NOT NULL COMMENT 'DB_USER',
    senha_criptografada VARCHAR(512) NULL COMMENT 'DB_PASS (criptografar em produção)',
    charset VARCHAR(32) NOT NULL DEFAULT 'utf8mb4',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_config_escolas_banco_escola (escola_id),
    CONSTRAINT fk_config_escolas_banco_escola
        FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Configuração de conexão ao banco de cada escola – banco master';

-- 3. (Opcional) Controle de migrations por escola
CREATE TABLE IF NOT EXISTS migrations_escolas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    escola_id INT UNSIGNED NOT NULL,
    migration_name VARCHAR(255) NOT NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_migrations_escolas_escola (escola_id),
    UNIQUE KEY uk_migrations_escolas (escola_id, migration_name),
    CONSTRAINT fk_migrations_escolas_escola
        FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Migrations já executadas por escola – banco master';

-- 4. Usuários do painel admin master (login separado das escolas)
CREATE TABLE IF NOT EXISTS usuarios_master (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    nome VARCHAR(255) NOT NULL DEFAULT 'Admin Master',
    avatar_url VARCHAR(500) NULL DEFAULT NULL COMMENT 'URL da foto de perfil (storage/files/master/avatars/)',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuarios_master_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Login do painel admin master (gerencia escolas/tenants)';

-- 5. Layout e módulos por escola (cor, logo, URL de imagens, módulos liberados)
CREATE TABLE IF NOT EXISTS config_escolas_layout (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    escola_id INT UNSIGNED NOT NULL,
    config_key VARCHAR(128) NOT NULL,
    config_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_config_escolas_layout (escola_id, config_key),
    CONSTRAINT fk_config_escolas_layout_escola
        FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Layout e módulos por escola (primary_color, logo_url, module_*, etc.)';
