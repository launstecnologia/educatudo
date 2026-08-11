# Sistema de Migrations - EducaTudo

## Visão Geral

O sistema de migrations permite gerenciar alterações no banco de dados de forma organizada e controlada. As migrations são executadas automaticamente em ordem durante o deploy.

## Master vs escola (tenant)

O runner do painel Master trata assim:

- Arquivo cujo nome contém **`master`** (ex.: `033_xxx_master.sql`): executa **apenas no banco master** (administrador). Use para `modulos_preco_creditos`, `escolas`, etc.
- Arquivo **sem** `master` no nome: executa em **cada banco de escola** (tenant). Não coloque SQL que só exista no master (ex.: `INSERT` em `modulos_preco_creditos`) nesses arquivos — a execução falha na escola.

## Estrutura

- **Diretório**: `database/migrations/`
- **Formato de nome**: `YYYYMMDD_HHMMSS_descricao.sql` ou `YYYYMMDD_HHMMSS_descricao.sql`
- **Exemplo**: `20260124_100000_add_aulas_tarde_oficinas_to_planos_aula.sql`

## Como Funciona

### 1. Criação de Migrations

Crie um arquivo SQL no diretório `database/migrations/` seguindo o padrão de nomenclatura:

```sql
-- Exemplo: 20260125_120000_minha_migration.sql
ALTER TABLE minha_tabela 
ADD COLUMN nova_coluna VARCHAR(255) NULL;
```

### 2. Execução Automática no Deploy

As migrations são executadas automaticamente ao rodar o script de deploy:

**Linux/Mac:**
```bash
bash scripts/deploy.sh
```

**Windows:**
```cmd
scripts\deploy.bat
```

### 3. Execução Manual

**Banco Principal:**
```bash
php scripts/run_migrations.php
```

**Escola Específica:**
```bash
php scripts/run_migrations.php --escola-id=1
```

**Modo Dry-Run (simulação):**
```bash
php scripts/run_migrations.php --dry-run
```

### 4. Interface Web (Admin)

Acesse `/admin/dev/migrations` para:
- Ver todas as migrations disponíveis
- Ver status de execução por escola
- Executar migrations individualmente
- Executar todas as migrations pendentes

## Controle de Execução

### Banco Principal
- Tabela: `migrations_log`
- Rastreia quais migrations foram executadas no banco principal

### Escolas
- Tabela: `migrations_executadas`
- Rastreia quais migrations foram executadas em cada escola
- Vinculada à tabela `escolas_database_config`

## Boas Práticas

1. **Sempre use IF NOT EXISTS** quando possível:
   ```sql
   ALTER TABLE minha_tabela 
   ADD COLUMN IF NOT EXISTS nova_coluna VARCHAR(255) NULL;
   ```

2. **Verifique existência de colunas** antes de adicionar:
   ```sql
   SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'minha_tabela' 
                  AND COLUMN_NAME = 'nova_coluna');
   SET @sqlstmt := IF(@exist = 0, 
       'ALTER TABLE minha_tabela ADD COLUMN nova_coluna VARCHAR(255) NULL', 
       'SELECT ''Coluna já existe''');
   PREPARE stmt FROM @sqlstmt;
   EXECUTE stmt;
   DEALLOCATE PREPARE stmt;
   ```

3. **Use transações** para operações críticas
4. **Teste migrations** antes de fazer deploy
5. **Documente** migrations complexas com comentários

## Ordem de Execução

As migrations são executadas em ordem alfabética (por nome do arquivo). Por isso, é importante usar o formato de data no nome do arquivo:

- ✅ `20260124_100000_primeira.sql`
- ✅ `20260124_110000_segunda.sql`
- ❌ `primeira.sql` (será executada depois das com data)

## Troubleshooting

### Migration não executou
- Verifique se o arquivo está no diretório correto
- Verifique se o nome segue o padrão
- Verifique logs em `storage/logs/`

### Erro ao executar migration
- Verifique a sintaxe SQL
- Verifique se a tabela/coluna já existe
- Use modo dry-run para testar antes

### Migration executada duas vezes
- O sistema previne execução duplicada
- Verifique a tabela `migrations_log` ou `migrations_executadas`

