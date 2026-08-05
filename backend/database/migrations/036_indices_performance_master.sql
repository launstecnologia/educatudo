-- Índices para performance no banco MASTER (multi-tenant).
-- Execute no banco master. Otimiza TenantResolver e DatabaseManager.

-- escolas: resolução por slug e por dominio (TenantResolver::resolveBySlug, resolveByDominio)
CREATE INDEX idx_escolas_slug_ativo ON escolas(slug, ativo);
CREATE INDEX idx_escolas_dominio_ativo ON escolas(dominio, ativo);

-- config_escolas_banco: lookup por escola_id (DatabaseManager::getConfigTenant)
CREATE INDEX idx_config_escolas_banco_escola_id ON config_escolas_banco(escola_id);
